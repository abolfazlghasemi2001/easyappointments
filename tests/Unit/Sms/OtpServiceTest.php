<?php

namespace Tests\Unit\Sms;

use Otp_exception;
use Otp_service;
use Sms_provider_mock;
use Tests\TestCase;

/**
 * One-time password tests (generation, hashing, verification, expiry, attempts and rate limits).
 */
class OtpServiceTest extends TestCase
{
    /**
     * Settings that the tests change (key => original value).
     */
    protected static array $original_settings = [];

    protected static $CI;

    protected static Otp_service $service;

    protected static Sms_provider_mock $provider;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$CI = &get_instance();

        self::$CI->load->library('sms_client');
        self::$CI->load->library('sms_provider_mock');

        self::$provider = new Sms_provider_mock();

        self::$CI->sms_client->set_provider(self::$provider);

        self::$CI->load->library('otp_service');

        self::$service = self::$CI->otp_service;

        self::$service->set_sms_client(self::$CI->sms_client);

        foreach (['otp_enabled', 'otp_max_attempts', 'otp_max_requests_per_phone', 'otp_max_requests_per_ip'] as $key) {
            $setting = self::$CI->db->get_where('settings', ['name' => $key])->row_array();

            self::$original_settings[$key] = $setting['value'] ?? null;
        }

        // The tests must not depend on the quota of previously used phone numbers.
        setting([
            'otp_max_attempts' => 3,
            'otp_max_requests_per_phone' => 5,
            'otp_max_requests_per_ip' => 50,
        ]);

        self::$CI->db->where('purpose', Otp_service::PURPOSE_PORTAL_LOGIN)->delete('otp_codes');

        Sms_provider_mock::reset();
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$original_settings as $key => $value) {
            if ($value !== null) {
                setting([$key => $value]);
            }
        }

        self::$CI->db->where('purpose', Otp_service::PURPOSE_PORTAL_LOGIN)->delete('otp_codes');

        self::$CI->db->where('context', 'otp')->delete('sms_messages');
    }

    /**
     * Request a code through the regular endpoint and replace it with a known value, so that the test can verify it.
     *
     * @param string $phone_number Phone number.
     * @param string $code Known code.
     * @param string|null $ip_address Client IP address.
     */
    protected function requestKnownCode(string $phone_number, string $code, ?string $ip_address = null): void
    {
        self::$service->request_code($phone_number, Otp_service::PURPOSE_PORTAL_LOGIN, $ip_address);

        self::$CI->db->update(
            'otp_codes',
            ['code_hash' => password_hash($code, PASSWORD_DEFAULT)],
            ['phone_number' => normalize_iran_phone_number($phone_number)],
        );
    }

    protected function lastCodeRecord(string $phone_number): array
    {
        $record = self::$CI->db->order_by('id', 'DESC')
            ->get_where('otp_codes', ['phone_number' => $phone_number])
            ->row_array();

        $this->assertNotEmpty($record, 'A code record was expected for ' . $phone_number);

        return $record;
    }

    public function testItGeneratesNumericCodes(): void
    {
        $code = self::$service->generate_code();

        $this->assertMatchesRegularExpression('/^\d{5}$/', $code);
        $this->assertSame(5, self::$service->code_length());

        setting(['otp_code_length' => 6]);

        $this->assertMatchesRegularExpression('/^\d{6}$/', self::$service->generate_code());

        setting(['otp_code_length' => 5]);
    }

    public function testItSendsTheCodeAndStoresOnlyItsHash(): void
    {
        self::$provider->clear();

        self::$service->request_code('09120000001', Otp_service::PURPOSE_PORTAL_LOGIN);

        $record = $this->lastCodeRecord('09120000001');

        $this->assertNotEmpty($record['code_hash']);
        $this->assertSame(60, strlen($record['code_hash']));
        $this->assertStringNotContainsString('09120000001', $record['code_hash']);
        $this->assertSame(0, (int) $record['attempts']);
        $this->assertNull($record['used_datetime']);
        $this->assertGreaterThan(time(), strtotime($record['expires_datetime']));

        // The message is queued and logged with the code of the customer.
        $messages = self::$CI->db->get_where('sms_messages', ['phone_number' => '09120000001'])->result_array();

        $this->assertCount(1, $messages);
        $this->assertSame('sent', $messages[0]['status']);
        $this->assertSame('otp', $messages[0]['context']);
        $this->assertMatchesRegularExpression('/(?<!\d)\d{5}(?!\d)/u', $messages[0]['message']);
        $this->assertStringContainsString('Company Name', $messages[0]['message']);

        // The code is sent to the customer, but only its hash is stored in the database.
        preg_match('/(?<!\d)(\d{5})(?!\d)/u', $messages[0]['message'], $matches);

        $code = $matches[1];

        $this->assertStringNotContainsString($code, json_encode($record));
        $this->assertTrue(password_verify($code, $record['code_hash']));
        $this->assertTrue(self::$service->verify_code('09120000001', $code));
    }

    public function testTheCodeCanBeUsedOnlyOnce(): void
    {
        $this->requestKnownCode('09120000002', '11111');

        $this->assertTrue(self::$service->verify_code('09120000002', '11111'));

        $this->expectException(Otp_exception::class);

        self::$service->verify_code('09120000002', '11111');
    }

    public function testANewCodeInvalidatesThePreviousOnes(): void
    {
        $this->requestKnownCode('09120000003', '22222');

        self::$service->request_code('09120000003');

        self::$CI->db->update(
            'otp_codes',
            ['code_hash' => password_hash('33333', PASSWORD_DEFAULT)],
            ['phone_number' => '09120000003', 'used_datetime IS NULL' => null],
        );

        try {
            self::$service->verify_code('09120000003', '22222');

            $this->fail('The previous code must not be valid anymore.');
        } catch (Otp_exception $exception) {
            $this->assertSame('invalid_code', $exception->reason());
        }

        $this->assertTrue(self::$service->verify_code('09120000003', '33333'));
    }

    public function testAWrongCodeIsCounted(): void
    {
        $this->requestKnownCode('09120000004', '44444');

        try {
            self::$service->verify_code('09120000004', '00000');

            $this->fail('An Otp_exception was expected.');
        } catch (Otp_exception $exception) {
            $this->assertSame('invalid_code', $exception->reason());
        }

        $record = $this->lastCodeRecord('09120000004');

        $this->assertSame(1, (int) $record['attempts']);
    }

    public function testTooManyWrongAttemptsBlockTheCode(): void
    {
        $this->requestKnownCode('09120000005', '55555');

        self::$CI->db->update('otp_codes', ['attempts' => 3], ['phone_number' => '09120000005']);

        try {
            self::$service->verify_code('09120000005', '55555');

            $this->fail('An Otp_exception was expected.');
        } catch (Otp_exception $exception) {
            $this->assertSame('too_many_attempts', $exception->reason());
        }
    }

    public function testAnExpiredCodeIsRejected(): void
    {
        $this->requestKnownCode('09120000006', '66666');

        self::$CI->db->update(
            'otp_codes',
            ['expires_datetime' => date('Y-m-d H:i:s', time() - 60)],
            ['phone_number' => '09120000006'],
        );

        try {
            self::$service->verify_code('09120000006', '66666');

            $this->fail('An Otp_exception was expected.');
        } catch (Otp_exception $exception) {
            $this->assertSame('expired', $exception->reason());
        }
    }

    public function testTheIpQuotaIsEnforced(): void
    {
        setting(['otp_max_requests_per_ip' => 1, 'otp_request_ip_window_minutes' => 60]);

        self::$service->request_code('09120000007', Otp_service::PURPOSE_PORTAL_LOGIN, '203.0.113.10');

        try {
            self::$service->request_code('09120000008', Otp_service::PURPOSE_PORTAL_LOGIN, '203.0.113.10');

            $this->fail('An Otp_exception was expected.');
        } catch (Otp_exception $exception) {
            $this->assertSame('rate_limited', $exception->reason());
            $this->assertGreaterThan(0, $exception->retry_after());
        }

        setting(['otp_max_requests_per_ip' => 50]);
    }

    public function testThePhoneQuotaIsEnforced(): void
    {
        setting(['otp_max_requests_per_phone' => 2, 'otp_request_window_minutes' => 15]);

        self::$service->request_code('09120000009');
        self::$service->request_code('09120000009');

        try {
            self::$service->request_code('09120000009');

            $this->fail('An Otp_exception was expected.');
        } catch (Otp_exception $exception) {
            $this->assertSame('rate_limited', $exception->reason());
            $this->assertGreaterThan(0, $exception->retry_after());
        }

        setting(['otp_max_requests_per_phone' => 5]);
    }

    public function testInvalidPhoneNumbersAreRejected(): void
    {
        try {
            self::$service->request_code('12345');

            $this->fail('An Otp_exception was expected.');
        } catch (Otp_exception $exception) {
            $this->assertSame('invalid_phone', $exception->reason());
        }
    }

    public function testTheServiceCanBeDisabled(): void
    {
        setting(['otp_enabled' => 0]);

        try {
            self::$service->request_code('09120000010');

            $this->fail('An Otp_exception was expected.');
        } catch (Otp_exception $exception) {
            $this->assertSame('disabled', $exception->reason());
        }

        setting(['otp_enabled' => 1]);
    }

    public function testTheCleanupRemovesTheExpiredCodes(): void
    {
        $this->requestKnownCode('09120000011', '77777');

        self::$CI->db->update(
            'otp_codes',
            ['expires_datetime' => date('Y-m-d H:i:s', time() - 7200)],
            ['phone_number' => '09120000011'],
        );

        $deleted = self::$service->cleanup();

        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertEmpty(self::$CI->db->get_where('otp_codes', ['phone_number' => '09120000011'])->row_array());
    }
}
