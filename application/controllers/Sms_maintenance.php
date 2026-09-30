<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * SMS maintenance endpoint.
 *
 * Messages that failed with a temporary gateway error are retried later, so the
 * queue has to be processed regularly. This controller exposes that operation
 * for a cron job and is protected the same way as the booking maintenance
 * endpoint (key in the environment or loopback/CLI only).
 *
 * Usage:
 *
 *   php index.php sms_maintenance process_queue
 *   curl -fsS "https://nobat.hoosna1402.ir/index.php/sms_maintenance/process_queue?key=$SMS_MAINTENANCE_KEY"
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Sms_maintenance controller.
 */
class Sms_maintenance extends EA_Controller
{
    /**
     * Sms_maintenance constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('sms_client');
    }

    /**
     * Send the queued messages that are due and clean up the expired OTP codes.
     *
     * @throws RuntimeException When the request is not authorized.
     */
    public function process_queue(): void
    {
        $this->assert_authorized();

        $result = $this->sms_client->process_queue((int) ($this->input->get('limit') ?? 20));

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'sent' => $result['sent'],
                'failed' => $result['failed'],
                'processed' => $result['processed'],
                'datetime' => date('Y-m-d H:i:s'),
            ]));
    }

    /**
     * Remove the expired one-time passwords.
     *
     * @throws RuntimeException When the request is not authorized.
     */
    public function cleanup_otp_codes(): void
    {
        $this->assert_authorized();

        $this->load->library('otp_service');

        $deleted = $this->otp_service->cleanup();

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'deleted' => $deleted,
                'datetime' => date('Y-m-d H:i:s'),
            ]));
    }

    /**
     * Only the cron job of the server (or a request that knows the maintenance key) may run these operations.
     *
     * @throws RuntimeException
     */
    protected function assert_authorized(): void
    {
        $expected_key = setting('sms_maintenance_key', (string) env('SMS_MAINTENANCE_KEY', ''));

        $provided_key = (string) ($this->input->get('key') ?? $this->input->post('key') ?? '');

        if (is_cli() && $provided_key === '') {
            $provided_key = $expected_key;
        }

        $allowed = $expected_key !== '' && hash_equals($expected_key, $provided_key);

        if (!$allowed) {
            $allowed = in_array($this->input->ip_address(), ['127.0.0.1', '::1'], true);
        }

        if (!$allowed) {
            show_error('Not authorized.', 401);
        }
    }
}
