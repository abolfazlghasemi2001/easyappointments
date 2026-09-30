<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * SMS provider contract.
 *
 * Every provider implementation must be able to hand a single message over to a
 * gateway and return the message/batch identifier of that gateway. Providers
 * must throw an Sms_exception when the message could not be accepted.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

if (!class_exists('Sms_exception')) {
    /**
     * Raised when a message could not be handed over to the SMS gateway.
     */
    class Sms_exception extends RuntimeException
    {
        /**
         * Whether the failure may disappear when the message is retried.
         */
        protected bool $transient = false;

        /**
         * Sms_exception constructor.
         *
         * @param string $message Error message (must never contain credentials).
         * @param bool $transient Whether a retry may succeed.
         * @param int $code HTTP status code or gateway error code.
         * @param Throwable|null $previous Previous exception.
         */
        public function __construct(string $message, bool $transient = false, int $code = 0, ?Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);

            $this->transient = $transient;
        }

        /**
         * Check whether the failure is temporary (network problem, gateway overloaded, rate limit, ...).
         *
         * @return bool
         */
        public function is_transient(): bool
        {
            return $this->transient;
        }
    }
}

if (!interface_exists('Sms_provider_interface')) {
    /**
     * Contract of an SMS provider.
     */
    interface Sms_provider_interface
    {
        /**
         * Get the driver name (used in the logs and in the settings screen).
         *
         * @return string
         */
        public function name(): string;

        /**
         * Send a single SMS message.
         *
         * @param string $phone_number Recipient phone number (any Iranian format).
         * @param string $message Message body.
         *
         * @return string Returns the gateway message/batch identifier.
         *
         * @throws Sms_exception When the message could not be handed over to the gateway.
         */
        public function send(string $phone_number, string $message): string;
    }
}
