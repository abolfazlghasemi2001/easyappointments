<?php

namespace Tests\Unit\Sms;

use Sms_exception;
use Sms_provider_textbee;
use Tests\TestCase;

/**
 * TextBee provider tests.
 *
 * The HTTP transport is replaced with a fake function, so the tests are deterministic and never touch the network.
 */
class SmsProviderTextbeeTest extends TestCase
{
    /**
     * Requests that the fake transport received.
     */
    protected array $requests = [];

    /**
     * Delays (in microseconds) that the provider wanted to wait.
     */
    protected array $waits = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Load the library so that the class is available for the tests (CodeIgniter libraries are not autoloaded).
        $CI = &get_instance();

        $CI->load->library('sms_provider_textbee');
    }

    /**
     * Create a provider that uses the fake transport.
     *
     * @param array $responses List of responses: [status, body] or a callable.
     * @param array $config Provider configuration.
     *
     * @return Sms_provider_textbee
     */
    protected function provider(array $responses, array $config = []): Sms_provider_textbee
    {
        $this->requests = [];
        $this->waits = [];

        $provider = new Sms_provider_textbee(array_merge([
            'api_key' => 'test-api-key',
            'device_id' => 'test-device',
            'backoff_ms' => 1000,
            'max_attempts' => 3,
        ], $config));

        $provider->set_transport(function (string $url, array $headers, array $payload) use (&$responses) {
            $this->requests[] = ['url' => $url, 'headers' => $headers, 'payload' => $payload];

            $response = array_shift($responses);

            if (is_callable($response)) {
                $response = $response();
            }

            return $response ?? ['status' => 200, 'body' => '{"data":{"smsBatchId":"default"}}'];
        });

        $provider->set_sleeper(function (int $microseconds) {
            $this->waits[] = $microseconds;
        });

        return $provider;
    }

    public function testItSendsTheDocumentedRequest(): void
    {
        $provider = $this->provider([['status' => 200, 'body' => '{"data":{"smsBatchId":"batch-1"}}']]);

        $message_id = $provider->send('09123456789', 'کد ورود شما: ۱۲۳۴۵');

        $this->assertSame('batch-1', $message_id);
        $this->assertCount(1, $this->requests);

        $request = $this->requests[0];

        $this->assertSame('https://api.textbee.dev/api/v1/gateway/send-sms', $request['url']);
        $this->assertContains('x-api-key: test-api-key', $request['headers']);
        $this->assertSame(['+989123456789'], $request['payload']['recipients']);
        $this->assertSame('کد ورود شما: ۱۲۳۴۵', $request['payload']['message']);
    }

    public function testItAcceptsTheDocumentedPhoneFormats(): void
    {
        $provider = $this->provider([
            ['status' => 200, 'body' => '{"data":{"smsBatchId":"1"}}'],
            ['status' => 200, 'body' => '{"data":{"smsBatchId":"2"}}'],
            ['status' => 200, 'body' => '{"data":{"smsBatchId":"3"}}'],
        ]);

        $provider->send('09123456789', 'a');
        $provider->send('+989123456789', 'b');
        $provider->send('۰۹۱۲۳۴۵۶۷۸۹', 'c');

        foreach ($this->requests as $request) {
            $this->assertSame(['+989123456789'], $request['payload']['recipients']);
        }
    }

    public function testItRetriesTransientErrorsWithBackoff(): void
    {
        $provider = $this->provider([
            ['status' => 503, 'body' => '{"message":"Service Unavailable"}'],
            ['status' => 429, 'body' => '{"message":"Too Many Requests"}'],
            ['status' => 200, 'body' => '{"data":{"smsBatchId":"after-retry"}}'],
        ]);

        $message_id = $provider->send('09123456789', 'retry me');

        $this->assertSame('after-retry', $message_id);
        $this->assertCount(3, $this->requests);
        $this->assertCount(2, $this->waits);

        // 1000 ms and 2000 ms (plus a small jitter).
        $this->assertGreaterThanOrEqual(1_000_000, $this->waits[0]);
        $this->assertLessThan(1_250_001, $this->waits[0]);
        $this->assertGreaterThanOrEqual(2_000_000, $this->waits[1]);
    }

    public function testItGivesUpAfterTheConfiguredNumberOfAttempts(): void
    {
        $provider = $this->provider([
            ['status' => 500, 'body' => '{"message":"Server Error"}'],
            ['status' => 500, 'body' => '{"message":"Server Error"}'],
        ], ['max_attempts' => 2]);

        try {
            $provider->send('09123456789', 'will fail');

            $this->fail('An Sms_exception was expected.');
        } catch (Sms_exception $exception) {
            $this->assertTrue($exception->is_transient());
            $this->assertStringContainsString('HTTP 500', $exception->getMessage());
            $this->assertStringNotContainsString('test-api-key', $exception->getMessage());
        }

        $this->assertCount(2, $this->requests);
    }

    public function testItDoesNotRetryPermanentErrors(): void
    {
        $provider = $this->provider([['status' => 401, 'body' => '{"message":"Invalid API key"}']]);

        try {
            $provider->send('09123456789', 'unauthorized');

            $this->fail('An Sms_exception was expected.');
        } catch (Sms_exception $exception) {
            $this->assertFalse($exception->is_transient());
            $this->assertStringContainsString('Invalid API key', $exception->getMessage());
        }

        $this->assertCount(1, $this->requests);
    }

    public function testItHidesTheApiKeyInErrorMessages(): void
    {
        $provider = $this->provider([
            ['status' => 400, 'body' => '{"message":"Rejected the key test-api-key"}'],
        ]);

        $this->expectException(Sms_exception::class);
        $this->expectExceptionMessageMatches('/Rejected the key \*\*\*/');

        $provider->send('09123456789', 'mask the key');
    }

    public function testItRejectsInvalidPhoneNumbers(): void
    {
        $provider = $this->provider([]);

        $this->expectException(Sms_exception::class);

        $provider->send('12345', 'invalid recipient');
    }

    public function testItFailsWhenTheCredentialsAreMissing(): void
    {
        $provider = new Sms_provider_textbee(['api_key' => '', 'device_id' => '']);

        $this->assertFalse($provider->is_configured());

        $this->expectException(Sms_exception::class);
        $this->expectExceptionMessageMatches('/not configured/');

        $provider->send('09123456789', 'no credentials');
    }

    public function testItCanUseACustomSendUrl(): void
    {
        $provider = $this->provider(
            [['status' => 200, 'body' => '{"data":{"smsBatchId":"self-hosted"}}']],
            ['send_url' => 'https://sms.example.org/gateway/devices/42/send-sms'],
        );

        $provider->send('09123456789', 'self hosted');

        $this->assertSame('https://sms.example.org/gateway/devices/42/send-sms', $this->requests[0]['url']);
    }
}
