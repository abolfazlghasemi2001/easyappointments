<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Appointment status state machine.
 *
 * The "status" column of the appointments table used to be a free text value, so
 * an appointment could jump from a final state (e.g. "Completed") back to an
 * active one, or be edited into an inconsistent state. This library defines the
 * only allowed transitions and is used by the booking flow and by the admin
 * panel, without touching the core model.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

class Appointment_status
{
    /**
     * Status that is assigned to a new public booking.
     */
    public const INITIAL = 'Booked';

    /**
     * Status of an appointment that is waiting for the customer payment (hold).
     */
    public const PENDING = 'Pending';

    /**
     * Allowed transitions, keyed by the current status.
     *
     * @var array<string, string[]>
     */
    protected const TRANSITIONS = [
        'Draft' => ['Booked', 'Pending', 'Cancelled'],
        'Pending' => ['Booked', 'Confirmed', 'Cancelled'],
        'Booked' => ['Confirmed', 'Rescheduled', 'Completed', 'Cancelled', 'No-show'],
        'Confirmed' => ['Rescheduled', 'Completed', 'Cancelled', 'No-show'],
        'Rescheduled' => ['Booked', 'Confirmed', 'Completed', 'Cancelled', 'No-show'],
        'Completed' => [],
        'Cancelled' => [],
        'No-show' => [],
    ];

    /**
     * Statuses that cannot be changed anymore.
     */
    protected const TERMINAL = ['Completed', 'Cancelled', 'No-show'];

    /**
     * @var EA_Controller|CI_Controller
     */
    protected EA_Controller|CI_Controller $CI;

    /**
     * Appointment_status constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Get every status that the application knows about.
     *
     * The values come from the "appointment_status_options" setting, extended with the statuses that the state
     * machine needs (so that an installation with the default settings also supports "Pending", "Completed" and
     * "No-show").
     *
     * @return string[]
     */
    public function all(): array
    {
        $configured = json_decode(setting('appointment_status_options', '[]'), true);

        $statuses = is_array($configured) ? $configured : [];

        foreach (array_merge([self::PENDING], array_keys(self::TRANSITIONS), self::TERMINAL) as $status) {
            if (!in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }
        }

        return array_values(array_unique($statuses));
    }

    /**
     * Get the status of an appointment (defaulting to the initial status).
     *
     * @param array $appointment Appointment data.
     *
     * @return string
     */
    public function of(array $appointment): string
    {
        return $this->normalize($appointment['status'] ?? null) ?? self::INITIAL;
    }

    /**
     * Normalize a status value.
     *
     * @param mixed $status Raw status value.
     *
     * @return string|null Returns null when the value is not a known status.
     */
    public function normalize(mixed $status): ?string
    {
        if (!is_string($status) || trim($status) === '') {
            return null;
        }

        foreach ($this->all() as $known) {
            if (strcasecmp($known, trim($status)) === 0) {
                return $known;
            }
        }

        return null;
    }

    /**
     * Check whether the transition from the current to the next status is allowed.
     *
     * @param string|null $current Current status (null for a new record).
     * @param string $next Next status.
     *
     * @return bool
     */
    public function can_transition(?string $current, string $next): bool
    {
        $next = $this->normalize($next) ?? '';

        if ($next === '') {
            return false;
        }

        $current = $this->normalize($current);

        if ($current === null) {
            // A new appointment may only start in one of the initial statuses.
            return in_array($next, [self::INITIAL, self::PENDING, 'Draft'], true);
        }

        if ($current === $next) {
            return true; // Saving an unchanged record is always allowed.
        }

        return in_array($next, self::TRANSITIONS[$current] ?? [], true);
    }

    /**
     * Get the statuses that the provided status can transition to.
     *
     * @param string|null $current Current status.
     *
     * @return string[]
     */
    public function allowed_transitions(?string $current): array
    {
        $current = $this->normalize($current);

        if ($current === null) {
            return [self::INITIAL, self::PENDING, 'Draft'];
        }

        return self::TRANSITIONS[$current] ?? [];
    }

    /**
     * Check whether a status is final.
     *
     * @param string|null $status Status value.
     *
     * @return bool
     */
    public function is_terminal(?string $status): bool
    {
        $status = $this->normalize($status);

        return $status !== null && in_array($status, self::TERMINAL, true);
    }

    /**
     * Check whether an appointment must not block its time slot anymore (cancelled or expired hold).
     *
     * @param array $appointment Appointment data.
     * @param string|null $now Reference time (defaults to now).
     *
     * @return bool
     */
    public function releases_slot(array $appointment, ?string $now = null): bool
    {
        $status = $this->of($appointment);

        if (in_array($status, ['Cancelled', 'Draft'], true)) {
            return true;
        }

        if ($status === self::PENDING && !empty($appointment['hold_expires_datetime'])) {
            return strtotime($appointment['hold_expires_datetime']) <= strtotime($now ?? date('Y-m-d H:i:s'));
        }

        return false;
    }

    /**
     * Validate the status change of an appointment and throw an exception when it is not allowed.
     *
     * @param string|null $current Current status (null for a new appointment).
     * @param string $next Next status.
     *
     * @throws InvalidArgumentException
     */
    public function assert_transition(?string $current, string $next): void
    {
        if (!$this->can_transition($current, $next)) {
            throw new InvalidArgumentException(
                'The appointment status cannot change from "' . ($current ?? '-') . '" to "' . $next . '".',
            );
        }
    }
}
