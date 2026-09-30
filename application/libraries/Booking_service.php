<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Booking integrity helpers.
 *
 * Two customers can submit the same time slot at the very same moment. The
 * availability check runs before the appointment is stored, so both requests may
 * pass it and the second one would create a duplicate booking. This library
 * provides the pre-insert conflict check (a friendly error message) and the
 * provisional slot reservation ("hold") that is released automatically when the
 * customer does not complete the payment in time.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

class Booking_service
{
    /**
     * Default hold duration in minutes.
     */
    public const DEFAULT_HOLD_MINUTES = 10;

    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Booking_service constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->CI->load->model('appointments_model');
        $this->CI->load->library('appointment_status');
    }

    /**
     * Get the number of minutes that a slot is held for a payment.
     *
     * @return int Returns 0 when the hold feature is disabled.
     */
    public function hold_minutes(): int
    {
        $value = setting('booking_hold_minutes', (string) self::DEFAULT_HOLD_MINUTES);

        return is_numeric($value) ? max(0, (int) $value) : self::DEFAULT_HOLD_MINUTES;
    }

    /**
     * Check that no other appointment of the provider occupies the requested period.
     *
     * The check must ignore the appointments that do not block their slot anymore (cancelled ones or pending ones
     * whose provisional hold expired), otherwise a released slot could never be booked again.
     *
     * @param int $provider_id Provider id.
     * @param string $start_datetime Start datetime (Y-m-d H:i:s).
     * @param string $end_datetime End datetime (Y-m-d H:i:s).
     * @param int|null $exclude_appointment_id Appointment to ignore (when editing).
     *
     * @throws RuntimeException When the slot is already taken.
     */
    public function assert_slot_is_free(
        int $provider_id,
        string $start_datetime,
        string $end_datetime,
        ?int $exclude_appointment_id = null,
    ): void {
        if ($this->has_blocking_appointment($provider_id, $start_datetime, $end_datetime, $exclude_appointment_id)) {
            throw new RuntimeException(lang('requested_hour_is_unavailable'));
        }
    }

    /**
     * Check whether the provider has an appointment that blocks the requested period.
     *
     * This is the same rule that the Availability library applies when it generates the bookable hours, so the
     * hours that are offered to the customer are the hours that can actually be stored.
     *
     * @param int $provider_id Provider id.
     * @param string $start_datetime Start datetime (Y-m-d H:i:s).
     * @param string $end_datetime End datetime (Y-m-d H:i:s).
     * @param int|null $exclude_appointment_id Appointment to ignore (when editing).
     *
     * @return bool
     */
    public function has_blocking_appointment(
        int $provider_id,
        string $start_datetime,
        string $end_datetime,
        ?int $exclude_appointment_id = null,
    ): bool {
        $this->CI->db->select('id')
            ->from('appointments')
            ->where('id_users_provider', $provider_id)
            ->where('start_datetime <', $end_datetime)
            ->where('end_datetime >', $start_datetime)
            ->group_start()
            ->where('status IS NULL', null, false)
            ->or_where_not_in('status', ['Cancelled', 'Draft'])
            ->group_end()
            ->group_start()
            ->where('hold_expires_datetime IS NULL', null, false)
            ->or_where('hold_expires_datetime >', date('Y-m-d H:i:s'))
            ->group_end();

        if ($exclude_appointment_id) {
            $this->CI->db->where('id !=', $exclude_appointment_id);
        }

        return $this->CI->db->get()->num_rows() > 0;
    }

    /**
     * Apply the initial status and the optional hold to an appointment that is about to be stored.
     *
     * @param array $appointment Appointment data (by reference).
     */
    public function apply_initial_state(array &$appointment): void
    {
        $hold_minutes = $this->hold_minutes();

        // A public booking may only start in one of the initial statuses (never in a final one), no matter what the
        // request contains.
        $status = $this->CI->appointment_status->normalize($appointment['status'] ?? null);

        $appointment['status'] = in_array($status, $this->CI->appointment_status->allowed_transitions(null), true)
            ? $status
            : Appointment_status::INITIAL;

        if ($hold_minutes > 0 && ($appointment['status'] === Appointment_status::PENDING || !empty($appointment['hold']))) {
            $appointment['status'] = Appointment_status::PENDING;

            $appointment['hold_expires_datetime'] = date(
                'Y-m-d H:i:s',
                strtotime('+' . $hold_minutes . ' minutes'),
            );
        } else {
            $appointment['hold_expires_datetime'] = null;
        }
    }

    /**
     * Store an appointment inside a transaction, turning a concurrent duplicate booking into a normal error.
     *
     * @param array $appointment Appointment data.
     *
     * @return int Returns the appointment id.
     *
     * @throws RuntimeException When the slot was taken in the meantime.
     */
    public function save_appointment(array $appointment): int
    {
        $db = $this->CI->db;

        $db_debug = $db->db_debug;

        $db->trans_begin();

        $db->db_debug = false; // A duplicate key must not render the framework error page.

        try {
            $this->assert_slot_is_free(
                (int) $appointment['id_users_provider'],
                $appointment['start_datetime'],
                $appointment['end_datetime'],
                $appointment['id'] ?? null,
            );

            $appointment_id = $this->CI->appointments_model->save($appointment);

            $error = $db->error();

            if (!empty($error['code'])) {
                throw new RuntimeException($error['message']);
            }
        } catch (Throwable $exception) {
            $db->trans_rollback();

            $db->db_debug = $db_debug;

            log_message('error', 'Booking rejected (concurrent or duplicate slot): ' . $exception->getMessage());

            throw new RuntimeException(lang('requested_hour_is_unavailable'), 0, $exception);
        }

        $db->db_debug = $db_debug;

        $db->trans_commit();

        return (int) $appointment_id;
    }

    /**
     * Cancel the appointments whose hold expired, so that the slots become bookable again.
     *
     * @param string|null $now Reference time (defaults to now).
     *
     * @return int Number of released appointments.
     */
    public function release_expired_holds(?string $now = null): int
    {
        $this->CI->db->where('status', Appointment_status::PENDING)
            ->where('hold_expires_datetime IS NOT NULL', null, false)
            ->where('hold_expires_datetime <=', $now ?? date('Y-m-d H:i:s'))
            ->update('appointments', [
                'status' => 'Cancelled',
                'update_datetime' => date('Y-m-d H:i:s'),
            ]);

        return $this->CI->db->affected_rows();
    }

    /**
     * Complete an appointment that is being paid for (turns a hold into a regular booking).
     *
     * @param int $appointment_id Appointment id.
     * @param string $status Target status.
     *
     * @return bool
     *
     * @throws InvalidArgumentException When the appointment cannot be moved to the requested status.
     */
    public function confirm_hold(int $appointment_id, string $status = Appointment_status::INITIAL): bool
    {
        $appointment = $this->CI->appointments_model->find($appointment_id);

        $this->CI->appointment_status->assert_transition($appointment['status'] ?? null, $status);

        return (bool) $this->CI->db->update(
            'appointments',
            [
                'status' => $status,
                'hold_expires_datetime' => null,
                'update_datetime' => date('Y-m-d H:i:s'),
            ],
            ['id' => $appointment_id],
        );
    }
}
