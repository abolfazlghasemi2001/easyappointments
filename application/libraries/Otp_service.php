<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * One-time password service.
 *
 * Customers log into the customer portal with their phone number and a short
 * code that is sent to them over SMS. The service protects the endpoint with:
 *
 * - rate limiting per phone number and per IP address,
 * - a short lifetime (setting "otp_code_ttl_seconds", 2 minutes by default),
 * - a limited number of verification attempts per code,
 * - hashed storage of the codes (a database dump never exposes a valid code),
 * - invalidation of the previous codes of the same phone number and purpose.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

if (!class_exists('Otp_exception')) {
    /**
     * Raised when a code cannot be issued or verified.
     */
    class Otp_exception extends RuntimeException
    {
        /**
         * One of: disabled, rate_limited, not_found, expired, invalid_code, too_many_attempts.
         */
        protected string $reason;

        /**
         * Seconds until the client may try again (only for "rate_limited").
         */
        protected int $retry_after;

        /**
         * Otp_exception constructor.
         *
         * @param string $reason Failure reason.
         * @param string $message Human readable message.
         * @param int $retry_after Seconds until the next attempt is allowed.
         */
        public function __construct(string $reason, string $message, int $retry_after = 0)
        {
            parent::__construct($message);

            $this->reason = $reason;
            $this->retry_after = $retry_after;
        }

        /**
         * Get the failure reason.
         *
         * @return string
         */
        public function reason(): string
        {
            return $this->reason;
        }

        /**
         * Get the seconds until the next attempt is allowed.
         *
         * @return int
         */
        public function retry_after(): int
        {
            return $this->retry_after;
        }
    }
}

/**
 * Otp_service
 */
class Otp_service
{
    public const PURPOSE_PORTAL_LOGIN = 'portal_login';

    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * The SMS client (can be replaced in the tests).
     */
    protected ?Sms_client $sms_client = null;

    /**
     * Otp_service constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->library('sms_client');
    }

    /**
     * Replace the SMS client (tests / custom deployments).
     *
     * @param Sms_client $sms_client
     *
     * @return $this
     */
    public function set_sms_client(Sms_client $sms_client): static
    {
        $this->sms_client = $sms_client;

        return $this;
    }

    /**
     * Get the SMS client.
     *
     * @return Sms_client
     */
    public function sms_client(): Sms_client
    {
        return $this->sms_client ?? $this->CI->sms_client;
    }

    /**
     * Check whether the OTP feature is enabled.
     *
     * @return bool
     */
    public function enabled(): bool
    {
        return (bool) setting('otp_enabled', '1');
    }

    /**
     * Get the number of digits of a generated code.
     *
     * @return int
     */
    public function code_length(): int
    {
        return min(10, max(4, (int) setting('otp_code_length', '5')));
    }

    /**
     * Get the lifetime of a code in seconds.
     *
     * @return int
     */
    public function ttl_seconds(): int
    {
        return max(30, (int) setting('otp_code_ttl_seconds', '120'));
    }

    /**
     * Get the number of verification attempts that a code allows.
     *
     * @return int
     */
    public function max_attempts(): int
    {
        return max(1, (int) setting('otp_max_attempts', '5'));
    }

    /**
     * Issue and send a new code.
     *
     * @param string $phone_number Recipient phone number (any Iranian format).
     * @param string $purpose Code purpose.
     * @param string|null $ip_address Client IP address (for rate limiting).
     *
     * @return array{message_id: int, expires_in: int, phone_number: string}
     *
     * @throws Otp_exception
     */
    public function request_code(
        string $phone_number,
        string $purpose = self::PURPOSE_PORTAL_LOGIN,
        ?string $ip_address = null,
    ): array {
        $this->assert_enabled();

        $normalized = normalize_iran_phone_number($phone_number);

        if (!is_valid_iran_mobile_number($normalized)) {
            throw new Otp_exception('invalid_phone', lang('customer_portal_invalid_phone'));
        }

        $this->assert_rate_limit($normalized, $ip_address);

        $code = $this->generate_code();

        $this->store_code($normalized, $code, $purpose, $ip_address);

        $template = lang('customer_portal_otp_message');

        if (!is_string($template) || $template === '' || $template === 'customer_portal_otp_message') {
            // The language file of the portal is not loaded (e.g. when the code is requested from the console).
            $template = 'کد ورود شما به {company}: {code}';
        }

        $message = str_replace(
            ['{code}', '{company}'],
            [$code, setting('company_name', '')],
            $template,
        );

        $message_id = $this->sms_client()->dispatch($normalized, $message, 'otp');

        return [
            'message_id' => $message_id,
            'expires_in' => $this->ttl_seconds(),
            'phone_number' => $normalized,
        ];
    }

    /**
     * Verify a code and consume it when it is valid.
     *
     * @param string $phone_number Phone number.
     * @param string $code Code that the customer entered.
     * @param string $purpose Code purpose.
     *
     * @return bool
     *
     * @throws Otp_exception
     */
    public function verify_code(string $phone_number, string $code, string $purpose = self::PURPOSE_PORTAL_LOGIN): bool
    {
        $this->assert_enabled();

        $normalized = normalize_iran_phone_number($phone_number);

        $code = trim($code);

        if ($normalized === '' || $code === '') {
            throw new Otp_exception('invalid_code', lang('customer_portal_invalid_code'));
        }

        $record = $this->CI->db->from('otp_codes')
            ->where('phone_number', $normalized)
            ->where('purpose', $purpose)
            ->where('used_datetime IS NULL', null, false)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        if (!$record) {
            throw new Otp_exception('not_found', lang('customer_portal_code_not_found'));
        }

        if (strtotime($record['expires_datetime']) < time()) {
            throw new Otp_exception('expired', lang('customer_portal_code_expired'));
        }

        if ((int) $record['attempts'] >= (int) $record['max_attempts']) {
            throw new Otp_exception('too_many_attempts', lang('customer_portal_too_many_attempts'));
        }

        if (!password_verify($code, $record['code_hash'])) {
            $this->CI->db->update(
                'otp_codes',
                ['attempts' => (int) $record['attempts'] + 1],
                ['id' => $record['id']],
            );

            throw new Otp_exception('invalid_code', lang('customer_portal_invalid_code'));
        }

        $this->CI->db->update(
            'otp_codes',
            ['used_datetime' => date('Y-m-d H:i:s'), 'attempts' => (int) $record['attempts'] + 1],
            ['id' => $record['id']],
        );

        return true;
    }

    /**
     * Delete the expired and already used codes.
     *
     * @param int $keep_days Number of days that used codes are kept for troubleshooting.
     *
     * @return int Number of deleted rows.
     */
    public function cleanup(int $keep_days = 7): int
    {
        $this->CI->db->group_start()
            ->where('expires_datetime <', date('Y-m-d H:i:s', time() - 3600))
            ->or_group_start()
            ->where('used_datetime IS NOT NULL', null, false)
            ->where('used_datetime <', date('Y-m-d H:i:s', time() - $keep_days * 86400))
            ->group_end()
            ->group_end()
            ->delete('otp_codes');

        return $this->CI->db->affected_rows();
    }

    /**
     * Generate a random numeric code.
     *
     * @return string
     */
    public function generate_code(): string
    {
        $length = $this->code_length();

        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }

    /**
     * Store the hashed code and invalidate the previous codes of the same purpose.
     *
     * @param string $phone_number Normalized phone number.
     * @param string $code Plain code (never stored).
     * @param string $purpose Code purpose.
     * @param string|null $ip_address Client IP address.
     *
     * @return int Returns the code id.
     */
    protected function store_code(string $phone_number, string $code, string $purpose, ?string $ip_address): int
    {
        $now = date('Y-m-d H:i:s');

        // Only the newest code of a phone number/purpose may be used.
        $this->CI->db->where('phone_number', $phone_number)
            ->where('purpose', $purpose)
            ->where('used_datetime IS NULL', null, false)
            ->update('otp_codes', ['used_datetime' => $now]);

        $this->CI->db->insert('otp_codes', [
            'phone_number' => $phone_number,
            'purpose' => $purpose,
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'attempts' => 0,
            'max_attempts' => $this->max_attempts(),
            'expires_datetime' => date('Y-m-d H:i:s', time() + $this->ttl_seconds()),
            'ip_address' => $ip_address,
            'create_datetime' => $now,
        ]);

        return (int) $this->CI->db->insert_id();
    }

    /**
     * Make sure the OTP feature is enabled.
     *
     * @throws Otp_exception
     */
    protected function assert_enabled(): void
    {
        if (!$this->enabled()) {
            throw new Otp_exception('disabled', lang('customer_portal_otp_disabled'));
        }
    }

    /**
     * Make sure the phone number and the IP address did not exceed the configured request quota.
     *
     * @param string $phone_number Normalized phone number.
     * @param string|null $ip_address Client IP address.
     *
     * @throws Otp_exception
     */
    protected function assert_rate_limit(string $phone_number, ?string $ip_address): void
    {
        $phone_window = max(1, (int) setting('otp_request_window_minutes', '15')) * 60;

        $phone_limit = max(1, (int) setting('otp_max_requests_per_phone', '3'));

        $this->assert_quota(
            ['phone_number' => $phone_number],
            $phone_window,
            $phone_limit,
            lang('customer_portal_rate_limited'),
        );

        if (!$ip_address) {
            return;
        }

        $ip_window = max(1, (int) setting('otp_request_ip_window_minutes', '60')) * 60;

        $ip_limit = max(1, (int) setting('otp_max_requests_per_ip', '10'));

        $this->assert_quota(
            ['ip_address' => $ip_address],
            $ip_window,
            $ip_limit,
            lang('customer_portal_rate_limited'),
        );
    }

    /**
     * Count the requests of the provided conditions inside the window and throw when the limit is reached.
     *
     * @param array $where Where clause.
     * @param int $window_seconds Window length in seconds.
     * @param int $limit Maximum number of requests.
     * @param string $message Error message.
     *
     * @throws Otp_exception
     */
    protected function assert_quota(array $where, int $window_seconds, int $limit, string $message): void
    {
        $since = date('Y-m-d H:i:s', time() - $window_seconds);

        $this->CI->db->from('otp_codes')->where($where)->where('create_datetime >=', $since);

        $count = $this->CI->db->count_all_results();

        if ($count < $limit) {
            return;
        }

        $oldest = $this->CI->db->select('create_datetime')
            ->from('otp_codes')
            ->where($where)
            ->order_by('create_datetime', 'ASC')
            ->limit(1)
            ->get()
            ->row_array();

        $retry_after = $oldest
            ? max(1, $window_seconds - (time() - strtotime($oldest['create_datetime'])))
            : $window_seconds;

        throw new Otp_exception('rate_limited', $message, $retry_after);
    }
}
