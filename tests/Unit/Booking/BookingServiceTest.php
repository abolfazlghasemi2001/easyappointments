<?php

namespace Tests\Unit\Booking;

use Booking_service;
use Tests\TestCase;

/**
 * Booking service tests (conflict detection, holds, waitlist).
 *
 * The tests run against the local development database (sqlite), so every created record is removed again.
 */
class BookingServiceTest extends TestCase
{
    protected static Booking_service $service;

    protected static $CI;

    protected static array $created_appointments = [];

    protected static int $second_provider_id = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$CI = &get_instance();

        self::$CI->load->library('booking_service');
        self::$CI->load->model('appointments_model');
        self::$CI->load->model('services_model');

        self::$service = self::$CI->booking_service;

        // A second provider is required in order to verify that the conflict check is scoped per provider.
        $provider_role = self::$CI->db->get_where('roles', ['slug' => 'provider'])->row_array();

        self::$CI->db->insert('users', [
            'first_name' => 'Second',
            'last_name' => 'Provider',
            'email' => 'second.provider@example.org',
            'phone_number' => '09120000000',
            'id_roles' => $provider_role['id'],
            'timezone' => 'UTC',
            'create_datetime' => date('Y-m-d H:i:s'),
            'update_datetime' => date('Y-m-d H:i:s'),
        ]);

        self::$second_provider_id = (int) self::$CI->db->insert_id();
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$created_appointments as $appointment_id) {
            self::$CI->db->delete('appointments', ['id' => $appointment_id]);
        }

        self::$created_appointments = [];

        if (self::$second_provider_id) {
            self::$CI->db->delete('users', ['id' => self::$second_provider_id]);
        }
    }

    /**
     * Create an appointment through the booking service.
     *
     * @param array $overrides Field overrides.
     *
     * @return int Appointment id.
     */
    protected function createAppointment(array $overrides = []): int
    {
        $service = self::$CI->db->get_where('services', ['id' => 1])->row_array();

        $provider = self::$CI->db->get_where('users', ['id_roles' => 2])->row_array();

        $customer = self::$CI->db->get_where('users', ['id_roles' => 3])->row_array();

        $appointment = array_merge(
            [
                'start_datetime' => '2026-10-05 10:00:00',
                'end_datetime' => '2026-10-05 10:30:00',
                'is_unavailability' => false,
                'id_users_provider' => $provider['id'],
                'id_users_customer' => $customer['id'],
                'id_services' => $service['id'],
                'status' => 'Booked',
                'notes' => 'booking service test',
            ],
            $overrides,
        );

        $appointment_id = self::$service->save_appointment($appointment);

        self::$created_appointments[] = $appointment_id;

        return $appointment_id;
    }

    public function testAnAppointmentCanBeStored(): void
    {
        $appointment_id = $this->createAppointment();

        $this->assertGreaterThan(0, $appointment_id);

        $appointment = self::$CI->appointments_model->find($appointment_id);

        $this->assertSame('2026-10-05 10:00:00', $appointment['start_datetime']);
        $this->assertSame('Booked', $appointment['status']);
    }

    public function testAConflictingAppointmentIsRejected(): void
    {
        $this->createAppointment(['start_datetime' => '2026-10-06 09:00:00', 'end_datetime' => '2026-10-06 09:30:00']);

        $this->expectException(\RuntimeException::class);

        $this->createAppointment(['start_datetime' => '2026-10-06 09:15:00', 'end_datetime' => '2026-10-06 09:45:00']);
    }

    public function testTheSameSlotOfAnotherProviderIsStillBookable(): void
    {
        $this->createAppointment(['start_datetime' => '2026-10-07 11:00:00', 'end_datetime' => '2026-10-07 11:30:00']);

        $appointment_id = $this->createAppointment([
            'start_datetime' => '2026-10-07 11:00:00',
            'end_datetime' => '2026-10-07 11:30:00',
            'id_users_provider' => self::$second_provider_id,
        ]);

        $this->assertGreaterThan(0, $appointment_id);
    }

    public function testACancelledAppointmentDoesNotBlockTheSlot(): void
    {
        $appointment_id = $this->createAppointment([
            'start_datetime' => '2026-10-11 09:00:00',
            'end_datetime' => '2026-10-11 09:30:00',
        ]);

        self::$CI->db->update('appointments', ['status' => 'Cancelled'], ['id' => $appointment_id]);

        $appointment = self::$CI->appointments_model->find($appointment_id);

        $this->assertFalse(self::$service->has_blocking_appointment(
            (int) $appointment['id_users_provider'],
            '2026-10-11 09:00:00',
            '2026-10-11 09:30:00',
        ));

        // The slot can be booked again, even though the cancelled record still exists (unique index exemption).
        $new_appointment_id = $this->createAppointment([
            'start_datetime' => '2026-10-11 09:00:00',
            'end_datetime' => '2026-10-11 09:30:00',
        ]);

        $this->assertGreaterThan(0, $new_appointment_id);
        $this->assertNotSame($appointment_id, $new_appointment_id);
    }

    public function testTheDatabaseItselfRejectsADuplicateSlot(): void
    {
        $appointment_id = $this->createAppointment([
            'start_datetime' => '2026-10-10 08:00:00',
            'end_datetime' => '2026-10-10 08:30:00',
        ]);

        $appointment = self::$CI->appointments_model->find($appointment_id);

        $db = self::$CI->db;
        $db_debug = $db->db_debug;
        $db->db_debug = false; // The duplicate key error must not stop the test run.

        $inserted = $db->insert('appointments', [
            'start_datetime' => $appointment['start_datetime'],
            'end_datetime' => $appointment['end_datetime'],
            'is_unavailability' => 0,
            'id_users_provider' => $appointment['id_users_provider'],
            'id_users_customer' => $appointment['id_users_customer'],
            'id_services' => $appointment['id_services'],
            'status' => 'Booked',
            'book_datetime' => date('Y-m-d H:i:s'),
            'create_datetime' => date('Y-m-d H:i:s'),
            'update_datetime' => date('Y-m-d H:i:s'),
            'hash' => random_string('alnum', 12),
        ]);

        $db->db_debug = $db_debug;

        $this->assertFalse($inserted, 'The unique index must reject a second appointment with the same start time.');
    }

    public function testAnExpiredHoldIsReleased(): void
    {
        $appointment_id = $this->createAppointment([
            'start_datetime' => '2026-10-08 12:00:00',
            'end_datetime' => '2026-10-08 12:30:00',
            'status' => 'Pending',
            'hold_expires_datetime' => '2026-09-01 00:00:00',
        ]);

        $released = self::$service->release_expired_holds('2026-09-30 00:00:00');

        $this->assertGreaterThanOrEqual(1, $released);

        $appointment = self::$CI->appointments_model->find($appointment_id);

        $this->assertSame('Cancelled', $appointment['status']);
    }

    public function testAnActiveHoldKeepsBlockingTheSlot(): void
    {
        $this->createAppointment([
            'start_datetime' => '2026-10-09 13:00:00',
            'end_datetime' => '2026-10-09 13:30:00',
            'status' => 'Pending',
            'hold_expires_datetime' => '2099-01-01 00:00:00',
        ]);

        $this->expectException(\RuntimeException::class);

        $this->createAppointment(['start_datetime' => '2026-10-09 13:00:00', 'end_datetime' => '2026-10-09 13:30:00']);
    }

    public function testApplyInitialStateSetsTheHold(): void
    {
        $appointment = ['status' => null];

        self::$service->apply_initial_state($appointment);

        $this->assertSame('Booked', $appointment['status']);
        $this->assertNull($appointment['hold_expires_datetime']);

        $held = ['status' => 'Pending'];

        self::$service->apply_initial_state($held);

        $this->assertSame('Pending', $held['status']);
        $this->assertNotEmpty($held['hold_expires_datetime']);
        $this->assertGreaterThan(time(), strtotime($held['hold_expires_datetime']));
    }

    public function testApplyInitialStateRejectsFinalStatusesOfPublicBookings(): void
    {
        $appointment = ['status' => 'Completed'];

        self::$service->apply_initial_state($appointment);

        $this->assertSame('Booked', $appointment['status']);
    }
}
