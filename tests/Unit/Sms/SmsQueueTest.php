<?php

namespace Tests\Unit\Sms;

use Sms_client;
use Sms_provider_mock;
use Tests\TestCase;

/**
 * SMS queue tests (queueing, immediate delivery, retry with backoff, failure log).
 */
class SmsQueueTest extends TestCase
{
    protected static Sms_client $client;

    protected static Sms_provider_mock $provider;

    protected static $CI;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$CI = &get_instance();

        self::$CI->load->library('sms_client');
        self::$CI->load->library('sms_provider_mock');

        self::$client = self::$CI->sms_client;

        self::$provider = new Sms_provider_mock();

        self::$client->set_provider(self::$provider);
    }

    public static function tearDownAfterClass(): void
    {
        self::$CI->db->where('context', 'test')->delete('sms_messages');
    }

    public function testAMessageIsStoredInTheQueueAndSentImmediately(): void
    {
        self::$provider->clear();

        $message_id = self::$client->dispatch('09123456789', 'سلام', 'test');

        $this->assertGreaterThan(0, $message_id);

        $record = self::$CI->db->get_where('sms_messages', ['id' => $message_id])->row_array();

        $this->assertSame('sent', $record['status']);
        $this->assertSame('09123456789', $record['phone_number']);
        $this->assertSame('mock', $record['driver']);
        $this->assertNotEmpty($record['provider_message_id']);
        $this->assertNotEmpty($record['sent_datetime']);
        $this->assertSame(1, (int) $record['attempts']);

        $this->assertCount(1, self::$provider->sent_messages());
        $this->assertSame('سلام', self::$provider->last_message()['message']);
    }

    public function testAFailedMessageStaysInTheQueueAndIsRetriedLater(): void
    {
        self::$provider->clear();
        self::$provider->set_failing(true);

        $message_id = self::$client->dispatch('09123456789', 'retry me', 'test');

        $record = self::$CI->db->get_where('sms_messages', ['id' => $message_id])->row_array();

        $this->assertSame('queued', $record['status']);
        $this->assertSame(1, (int) $record['attempts']);
        $this->assertNotNull($record['next_attempt_datetime']);
        $this->assertGreaterThan(date('Y-m-d H:i:s'), $record['next_attempt_datetime']);
        $this->assertNotEmpty($record['error']);

        // The message is not due yet, so the queue processor ignores it.
        $result = self::$client->process_queue();

        $this->assertSame(0, $result['processed']);

        // Once it is due and the gateway works again, it is delivered.
        self::$provider->set_failing(false);

        self::$CI->db->update(
            'sms_messages',
            ['next_attempt_datetime' => date('Y-m-d H:i:s', time() - 60)],
            ['id' => $message_id],
        );

        $result = self::$client->process_queue();

        $this->assertSame(1, $result['sent']);

        $record = self::$CI->db->get_where('sms_messages', ['id' => $message_id])->row_array();

        $this->assertSame('sent', $record['status']);
        $this->assertSame(2, (int) $record['attempts']);
        $this->assertNull($record['error']);
    }

    public function testAMessageIsMarkedAsFailedAfterTheLastAttempt(): void
    {
        self::$provider->set_failing(true);

        $message_id = self::$client->queue('09123456789', 'never delivered', 'test');

        self::$CI->db->update('sms_messages', ['max_attempts' => 2], ['id' => $message_id]);

        self::$client->send($message_id);

        self::$CI->db->update(
            'sms_messages',
            ['next_attempt_datetime' => date('Y-m-d H:i:s', time() - 60)],
            ['id' => $message_id],
        );

        self::$client->send($message_id);

        $record = self::$CI->db->get_where('sms_messages', ['id' => $message_id])->row_array();

        $this->assertSame('failed', $record['status']);
        $this->assertSame(2, (int) $record['attempts']);
        $this->assertNull($record['next_attempt_datetime']);

        self::$provider->set_failing(false);
    }

    public function testTheRetryDelaysIncrease(): void
    {
        $this->assertSame(60, self::$client->retry_delay_seconds(1));
        $this->assertSame(300, self::$client->retry_delay_seconds(2));
        $this->assertSame(900, self::$client->retry_delay_seconds(3));
        $this->assertSame(3600, self::$client->retry_delay_seconds(10));
    }

    public function testItRejectsInvalidRecipients(): void
    {
        $this->expectException(\RuntimeException::class);

        self::$client->queue('123', 'invalid recipient', 'test');
    }

    public function testMessagesCanBeSearchedInTheLog(): void
    {
        self::$provider->set_failing(false);

        self::$client->dispatch('09121112233', 'log entry', 'test');

        $records = self::$client->get(['context' => 'test'], 50);

        $this->assertNotEmpty($records);
        $this->assertSame('log entry', $records[0]['message']);
    }
}
