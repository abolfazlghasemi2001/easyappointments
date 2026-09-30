<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * SMS client: queue, log and provider selection.
 *
 * Every message is written to the "sms_messages" table before it is handed over
 * to a gateway. Messages that fail with a temporary error are retried later by
 * Sms_maintenance::process_queue() (see the cron note in docs/fa/step-04-textbee.md),
 * so a gateway outage does not lose the notification.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

require_once __DIR__ . '/Sms_provider_interface.php';

/**
 * Sms_client
 */
class Sms_client
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * Retry delays in seconds, indexed by the number of attempts already made.
     */
    protected const RETRY_DELAYS = [60, 300, 900, 3600];

    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Resolved provider.
     */
    protected ?Sms_provider_interface $provider = null;

    /**
     * Sms_client constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Check whether sending SMS messages is enabled.
     *
     * @return bool
     */
    public function enabled(): bool
    {
        return (bool) setting('sms_enabled', '1');
    }

    /**
     * Get the configured driver name ("mock" or "textbee").
     *
     * @return string
     */
    public function driver(): string
    {
        $driver = (string) (env('SMS_DRIVER') ?? setting('sms_driver', 'mock'));

        $driver = strtolower(trim($driver));

        return in_array($driver, ['mock', 'textbee'], true) ? $driver : 'mock';
    }

    /**
     * Get the provider instance.
     *
     * @return Sms_provider_interface
     */
    public function provider(): Sms_provider_interface
    {
        if ($this->provider) {
            return $this->provider;
        }

        if ($this->driver() === 'textbee') {
            $this->CI->load->library('sms_provider_textbee');

            return $this->provider = $this->CI->sms_provider_textbee;
        }

        $this->CI->load->library('sms_provider_mock');

        return $this->provider = $this->CI->sms_provider_mock;
    }

    /**
     * Replace the provider (used by the tests and by custom deployments).
     *
     * @param Sms_provider_interface $provider Provider instance.
     *
     * @return $this
     */
    public function set_provider(Sms_provider_interface $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Add a message to the queue.
     *
     * @param string $phone_number Recipient phone number.
     * @param string $message Message body.
     * @param string|null $context Message context ("otp", "waitlist", "reminder", ...).
     * @param int|null $reference_id Related record id (appointment, otp code, waitlist entry, ...).
     *
     * @return int Returns the message id.
     *
     * @throws RuntimeException When the message could not be queued.
     */
    public function queue(string $phone_number, string $message, ?string $context = null, ?int $reference_id = null): int
    {
        $normalized = normalize_iran_phone_number($phone_number);

        if (!is_valid_iran_mobile_number($normalized)) {
            throw new RuntimeException('The recipient phone number is not a valid Iranian mobile number.');
        }

        $now = date('Y-m-d H:i:s');

        $record = [
            'phone_number' => $normalized,
            'message' => $message,
            'context' => $context,
            'reference_id' => $reference_id,
            'driver' => $this->driver(),
            'status' => self::STATUS_QUEUED,
            'attempts' => 0,
            'max_attempts' => max(1, (int) setting('sms_max_attempts', '3')),
            'next_attempt_datetime' => $now,
            'create_datetime' => $now,
            'update_datetime' => $now,
        ];

        if (!$this->CI->db->insert('sms_messages', $record)) {
            throw new RuntimeException('Could not queue the SMS message.');
        }

        return (int) $this->CI->db->insert_id();
    }

    /**
     * Queue a message and try to send it right away.
     *
     * Failures never bubble up: the message stays in the queue and is retried by the cron job.
     *
     * @param string $phone_number Recipient phone number.
     * @param string $message Message body.
     * @param string|null $context Message context.
     * @param int|null $reference_id Related record id.
     *
     * @return int Returns the message id.
     */
    public function dispatch(
        string $phone_number,
        string $message,
        ?string $context = null,
        ?int $reference_id = null,
    ): int {
        if (!$this->enabled()) {
            log_message('debug', 'SMS sending is disabled, the message was not queued.');

            return 0;
        }

        $message_id = $this->queue($phone_number, $message, $context, $reference_id);

        $this->send($message_id);

        return $message_id;
    }

    /**
     * Try to deliver a queued message.
     *
     * @param int $message_id Message id.
     *
     * @return bool Returns true when the gateway accepted the message.
     */
    public function send(int $message_id): bool
    {
        $record = $this->CI->db->get_where('sms_messages', ['id' => $message_id])->row_array();

        if (!$record) {
            log_message('error', 'The SMS message was not found in the queue: ' . $message_id);

            return false;
        }

        if ($record['status'] === self::STATUS_SENT) {
            return true;
        }

        $attempts = (int) $record['attempts'] + 1;

        try {
            $provider = $this->provider();

            $provider_message_id = $provider->send($record['phone_number'], $record['message']);

            $this->CI->db->update(
                'sms_messages',
                [
                    'status' => self::STATUS_SENT,
                    'attempts' => $attempts,
                    'driver' => $provider->name(),
                    'provider_message_id' => $provider_message_id,
                    'error' => null,
                    'sent_datetime' => date('Y-m-d H:i:s'),
                    'next_attempt_datetime' => null,
                    'update_datetime' => date('Y-m-d H:i:s'),
                ],
                ['id' => $message_id],
            );

            return true;
        } catch (Throwable $exception) {
            $transient = $exception instanceof Sms_exception && $exception->is_transient();

            $max_attempts = max(1, (int) $record['max_attempts']);

            $can_retry = $transient && $attempts < $max_attempts;

            $this->CI->db->update(
                'sms_messages',
                [
                    'status' => $can_retry ? self::STATUS_QUEUED : self::STATUS_FAILED,
                    'attempts' => $attempts,
                    'error' => mb_substr($exception->getMessage(), 0, 500),
                    'next_attempt_datetime' => $can_retry
                        ? date('Y-m-d H:i:s', time() + $this->retry_delay_seconds($attempts))
                        : null,
                    'update_datetime' => date('Y-m-d H:i:s'),
                ],
                ['id' => $message_id],
            );

            log_message(
                'error',
                'Could not send the SMS message ' . $message_id . ' (' . mask_phone_number($record['phone_number'])
                . '): ' . $exception->getMessage(),
            );

            return false;
        }
    }

    /**
     * Send the queued messages that are due.
     *
     * @param int $limit Maximum number of messages that are processed in this run.
     *
     * @return array{sent: int, failed: int, processed: int}
     */
    public function process_queue(int $limit = 20): array
    {
        $now = date('Y-m-d H:i:s');

        $messages = $this->CI->db->from('sms_messages')
            ->where('status', self::STATUS_QUEUED)
            ->group_start()
            ->where('next_attempt_datetime IS NULL', null, false)
            ->or_where('next_attempt_datetime <=', $now)
            ->group_end()
            ->order_by('id', 'ASC')
            ->limit($limit)
            ->get()
            ->result_array();

        $sent = 0;

        $failed = 0;

        foreach ($messages as $message) {
            if ($this->send((int) $message['id'])) {
                $sent++;

                continue;
            }

            $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed, 'processed' => count($messages)];
    }

    /**
     * Get queued/logged messages.
     *
     * @param array|null $where Where clause.
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return array
     */
    public function get(?array $where = null, ?int $limit = null, ?int $offset = null): array
    {
        if ($where) {
            $this->CI->db->where($where);
        }

        return $this->CI->db->order_by('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get('sms_messages')
            ->result_array();
    }

    /**
     * Get the retry delay of a failed attempt.
     *
     * @param int $attempts Number of attempts already made.
     *
     * @return int Seconds.
     */
    public function retry_delay_seconds(int $attempts): int
    {
        $index = max(1, $attempts) - 1;

        $delays = self::RETRY_DELAYS;

        return $delays[$index] ?? $delays[count($delays) - 1];
    }
}
