<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Mock SMS provider.
 *
 * It is the default driver, so the application works out of the box, in the
 * development sandbox and in the automated tests, without sending anything. The
 * messages are written to the application log (with a masked phone number) and
 * kept in memory, so that a test can assert what would have been sent.
 *
 * The message log and the failure switch are static, because this framework
 * creates a new library instance for every load() call and a test must be able
 * to inspect the messages of the instance that the application actually used.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

require_once __DIR__ . '/Sms_provider_interface.php';

/**
 * Sms_provider_mock
 */
class Sms_provider_mock implements Sms_provider_interface
{
    /**
     * Messages that were "sent" during this request.
     *
     * @var array<int, array{phone_number: string, message: string, id: string}>
     */
    protected static array $sent = [];

    /**
     * When enabled, send() throws a transient error (used by the queue tests).
     */
    protected static bool $failing = false;

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'mock';
    }

    /**
     * Make every following send() call fail.
     *
     * @param bool $failing
     *
     * @return $this
     */
    public function set_failing(bool $failing): static
    {
        self::$failing = $failing;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function send(string $phone_number, string $message): string
    {
        if (self::$failing) {
            throw new Sms_exception('The mock gateway is configured to fail.', true, 503);
        }

        $normalized = function_exists('normalize_iran_phone_number')
            ? normalize_iran_phone_number($phone_number)
            : $phone_number;

        if ($normalized === '') {
            throw new Sms_exception('The recipient phone number is not a valid Iranian number.');
        }

        $id = 'mock-' . uniqid('', true);

        self::$sent[] = [
            'phone_number' => $normalized,
            'message' => $message,
            'id' => $id,
        ];

        log_message(
            'info',
            '[sms:mock] ' . mask_phone_number($normalized) . ' :: ' . $message . ' (id: ' . $id . ')',
        );

        return $id;
    }

    /**
     * Get the messages that were sent through this instance.
     *
     * @return array
     */
    public function sent_messages(): array
    {
        return self::$sent;
    }

    /**
     * Get the last message that was sent through this instance.
     *
     * @return array|null
     */
    public function last_message(): ?array
    {
        return self::$sent ? self::$sent[array_key_last(self::$sent)] : null;
    }

    /**
     * Forget the messages of this instance.
     */
    public function clear(): void
    {
        self::$sent = [];
    }

    /**
     * Get the messages that were sent, without the need of a provider instance.
     *
     * @return array
     */
    public static function messages(): array
    {
        return self::$sent;
    }

    /**
     * Forget all messages.
     */
    public static function reset(): void
    {
        self::$sent = [];

        self::$failing = false;
    }
}
