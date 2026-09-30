<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Booking maintenance endpoint.
 *
 * The provisional booking holds (see Booking_service) expire after a few minutes, so they must be released
 * regularly, otherwise the slot stays blocked forever. This controller exposes the release operation for a cron
 * job. It is a fork addition, the core controllers are not touched.
 *
 * Usage (once per minute):
 *
 *   curl -fsS "https://nobat.hoosna1402.ir/index.php/booking_maintenance/release_expired_holds?key=$MAINTENANCE_KEY"
 *
 * or, on the server, without HTTP:
 *
 *   php index.php booking_maintenance release_expired_holds
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Booking_maintenance controller.
 */
class Booking_maintenance extends EA_Controller
{
    /**
     * Booking_maintenance constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->library('booking_service');
    }

    /**
     * Release the expired holds.
     *
     * @throws RuntimeException When the request is not authorized.
     */
    public function release_expired_holds(): void
    {
        $expected_key = setting('booking_maintenance_key', (string) env('BOOKING_MAINTENANCE_KEY', ''));

        $provided_key = (string) ($this->input->get('key') ?? $this->input->post('key') ?? '');

        if (is_cli() && $provided_key === '') {
            $provided_key = $expected_key;
        }

        $allowed = $expected_key !== '' && hash_equals($expected_key, (string) $provided_key);

        // The endpoint can be exposed without a key, but then it may only be called from the local machine (cron).
        if (!$allowed) {
            $allowed = in_array($this->input->ip_address(), ['127.0.0.1', '::1'], true);
        }

        if (!$allowed) {
            show_error('Not authorized.', 401);

            return;
        }

        $released = $this->booking_service->release_expired_holds();

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'released' => $released,
                'datetime' => date('Y-m-d H:i:s'),
            ]));
    }
}
