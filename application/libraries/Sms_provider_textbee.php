<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * TextBee SMS gateway client.
 *
 * TextBee (https://textbee.dev) turns an Android phone into an SMS gateway. The
 * message is handed over with
 *
 *   POST {TEXTBEE_BASE_URL}/gateway/send-sms
 *   x-api-key: {TEXTBEE_API_KEY}
 *   {"recipients": ["+98912...", ...], "message": "..."}
 *
 * and a successful call answers with {"data": {"smsBatchId": "...", ...}}.
 *
 * Configuration is read from the environment (see .env.example). A successful
 * response only means that the message was accepted and queued by the gateway,
 * not that it was already delivered to the handset.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

require_once __DIR__ . '/Sms_provider_interface.php';

/**
 * Sms_provider_textbee
 */
class Sms_provider_textbee implements Sms_provider_interface
{
    /**
     * Endpoint that accepts new messages.
     */
    protected const DEFAULT_BASE_URL = 'https://api.textbee.dev/api/v1';

    /**
     * Path of the "send message" endpoint (appended to the base URL).
     */
    protected const SEND_PATH = '/gateway/send-sms';

    /**
     * HTTP status codes that indicate a temporary problem.
     */
    protected const TRANSIENT_STATUS_CODES = [408, 425, 429, 500, 502, 503, 504, 507, 509];

    protected string $api_key;

    protected string $device_id;

    protected string $base_url;

    protected string $send_url;

    protected int $timeout;

    protected int $max_attempts;

    protected int $backoff_ms;

    /**
     * Optional transport used by the tests: function (string $url, array $headers, array $payload): array.
     *
     * @var callable|null
     */
    protected $transport = null;

    /**
     * Optional sleeper used by the tests: function (int $microseconds): void.
     *
     * @var callable|null
     */
    protected $sleeper = null;

    /**
     * Sms_provider_textbee constructor.
     *
     * @param array $config Optional configuration overrides (api_key, device_id, base_url, send_url, timeout,
     *                      max_attempts, backoff_ms).
     */
    public function __construct(array $config = [])
    {
        $this->api_key = trim((string) ($config['api_key'] ?? env('TEXTBEE_API_KEY', '')));
        $this->device_id = trim((string) ($config['device_id'] ?? env('TEXTBEE_DEVICE_ID', '')));
        $this->base_url = rtrim((string) ($config['base_url'] ?? env('TEXTBEE_BASE_URL', self::DEFAULT_BASE_URL)), '/');
        $this->send_url = trim((string) ($config['send_url'] ?? env('TEXTBEE_SEND_URL', '')));
        $this->timeout = max(1, (int) ($config['timeout'] ?? env('TEXTBEE_TIMEOUT', 20)));
        $this->max_attempts = max(1, (int) ($config['max_attempts'] ?? env('TEXTBEE_MAX_ATTEMPTS', 3)));
        $this->backoff_ms = max(0, (int) ($config['backoff_ms'] ?? env('TEXTBEE_BACKOFF_MS', 1000)));

        if ($this->send_url === '' && $this->base_url !== '' && $this->device_id !== ''
            && str_contains($this->base_url, 'api.textbee.dev') === false) {
            // Self hosted TextBee installations may still use the device scoped endpoint.
            $this->send_url = $this->base_url . '/gateway/devices/' . rawurlencode($this->device_id) . '/send-sms';
        }
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'textbee';
    }

    /**
     * Replace the HTTP transport (tests / custom deployments).
     *
     * @param callable $transport function (string $url, array $headers, array $payload): array{status: int, body: string}
     *
     * @return $this
     */
    public function set_transport(callable $transport): static
    {
        $this->transport = $transport;

        return $this;
    }

    /**
     * Replace the sleep function (tests must not wait for real).
     *
     * @param callable $sleeper function (int $microseconds): void
     *
     * @return $this
     */
    public function set_sleeper(callable $sleeper): static
    {
        $this->sleeper = $sleeper;

        return $this;
    }

    /**
     * Check whether the gateway credentials are present.
     *
     * @return bool
     */
    public function is_configured(): bool
    {
        return $this->api_key !== '';
    }

    /**
     * @inheritDoc
     */
    public function send(string $phone_number, string $message): string
    {
        if (!$this->is_configured()) {
            throw new Sms_exception('The SMS gateway is not configured (TEXTBEE_API_KEY is missing).');
        }

        $recipient = $this->to_e164($phone_number);

        if ($recipient === '') {
            throw new Sms_exception('The recipient phone number is not a valid Iranian number.');
        }

        if (trim($message) === '') {
            throw new Sms_exception('The SMS message must not be empty.');
        }

        $payload = [
            'recipients' => [$recipient],
            'message' => $message,
        ];

        $headers = [
            'x-api-key: ' . $this->api_key,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $last_error = null;

        for ($attempt = 1; $attempt <= $this->max_attempts; $attempt++) {
            try {
                $response = $this->request($this->endpoint(), $headers, $payload);
            } catch (Sms_exception $exception) {
                $last_error = $exception;

                if ($attempt < $this->max_attempts) {
                    $this->wait($attempt);

                    continue;
                }

                throw new Sms_exception(
                    'The SMS gateway could not be reached after ' . $attempt . ' attempts: ' . $exception->getMessage(),
                    true,
                    $exception->getCode(),
                    $exception,
                );
            }

            $status = (int) ($response['status'] ?? 0);

            if ($status >= 200 && $status < 300) {
                return $this->extract_message_id((string) ($response['body'] ?? ''));
            }

            $error = $this->describe_error($status, (string) ($response['body'] ?? ''));

            if (in_array($status, self::TRANSIENT_STATUS_CODES, true)) {
                $last_error = new Sms_exception($error, true, $status);

                if ($attempt < $this->max_attempts) {
                    $this->wait($attempt);

                    continue;
                }

                throw $last_error;
            }

            // Permanent failure (invalid credentials, rejected payload, ...) - retrying cannot help.
            throw new Sms_exception($error, false, $status);
        }

        throw new Sms_exception(
            'The SMS could not be sent: ' . ($last_error?->getMessage() ?? 'unknown error'),
            true,
        );
    }

    /**
     * Get the endpoint that accepts new messages.
     *
     * @return string
     */
    protected function endpoint(): string
    {
        return $this->send_url !== '' ? $this->send_url : $this->base_url . self::SEND_PATH;
    }

    /**
     * Perform the HTTP request.
     *
     * @param string $url Endpoint URL.
     * @param array $headers Request headers.
     * @param array $payload Request payload.
     *
     * @return array{status: int, body: string}
     *
     * @throws Sms_exception When the connection fails.
     */
    protected function request(string $url, array $headers, array $payload): array
    {
        if ($this->transport) {
            return ($this->transport)($url, $headers, $payload);
        }

        if (!function_exists('curl_init')) {
            throw new Sms_exception('The PHP cURL extension is required by the TextBee driver.');
        }

        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
        ]);

        $body = curl_exec($handle);

        $error = curl_error($handle);

        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($body === false || $error !== '') {
            // The error message of cURL never contains the API key, but it is masked anyway.
            throw new Sms_exception('Connection error: ' . $this->mask_secrets($error));
        }

        return ['status' => $status, 'body' => (string) $body];
    }

    /**
     * Wait before the next attempt (exponential backoff with a small jitter).
     *
     * @param int $attempt Number of the attempt that just failed.
     */
    protected function wait(int $attempt): void
    {
        $microseconds = ($this->backoff_ms * (2 ** ($attempt - 1)) + random_int(0, 250)) * 1000;

        if ($this->sleeper) {
            ($this->sleeper)($microseconds);

            return;
        }

        usleep($microseconds);
    }

    /**
     * Extract the message/batch identifier from the gateway response.
     *
     * @param string $body Raw response body.
     *
     * @return string
     */
    protected function extract_message_id(string $body): string
    {
        $decoded = json_decode($body, true);

        $data = is_array($decoded) ? ($decoded['data'] ?? $decoded) : [];

        foreach (['smsBatchId', 'messageId', 'id', '_id'] as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }

        // A gateway may answer without an identifier - keep the message in the log with a local reference.
        return 'textbee-' . uniqid('', true);
    }

    /**
     * Build a human readable error message for a failed request.
     *
     * @param int $status HTTP status code.
     * @param string $body Raw response body.
     *
     * @return string
     */
    protected function describe_error(int $status, string $body): string
    {
        $decoded = json_decode($body, true);

        $detail = '';

        if (is_array($decoded)) {
            $detail = (string) ($decoded['message'] ?? ($decoded['error'] ?? ''));
        }

        if ($detail === '') {
            $detail = trim(mb_substr($body, 0, 200));
        }

        $message = 'TextBee responded with HTTP ' . $status;

        if ($detail !== '') {
            $message .= ': ' . $detail;
        }

        return $this->mask_secrets($message);
    }

    /**
     * Convert an Iranian phone number to the E.164 format expected by the gateway.
     *
     * @param string $phone_number Phone number.
     *
     * @return string Returns an empty string when the number cannot be converted.
     */
    protected function to_e164(string $phone_number): string
    {
        $normalized = function_exists('normalize_iran_phone_number')
            ? normalize_iran_phone_number($phone_number)
            : preg_replace('/\D+/', '', $phone_number);

        if (!is_string($normalized) || !preg_match('/^0\d{9,10}$/', $normalized)) {
            return '';
        }

        return '+98' . substr($normalized, 1);
    }

    /**
     * Remove anything that looks like a credential from a message.
     *
     * @param string $message Message.
     *
     * @return string
     */
    protected function mask_secrets(string $message): string
    {
        if ($this->api_key !== '') {
            $message = str_replace($this->api_key, '***', $message);
        }

        return $message;
    }
}
