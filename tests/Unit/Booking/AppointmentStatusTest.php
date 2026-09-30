<?php

namespace Tests\Unit\Booking;

use Appointment_status;
use Tests\TestCase;

/**
 * Appointment status state machine tests.
 */
class AppointmentStatusTest extends TestCase
{
    protected static Appointment_status $status;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $CI = &get_instance();

        $CI->load->library('appointment_status');

        self::$status = $CI->appointment_status;
    }

    public function testInitialStatusIsBooked(): void
    {
        $this->assertSame('Booked', Appointment_status::INITIAL);
        $this->assertSame('Booked', self::$status->of([]));
    }

    public function testNormalizeIsCaseInsensitive(): void
    {
        $this->assertSame('Booked', self::$status->normalize('booked'));
        $this->assertSame('No-show', self::$status->normalize('no-show'));
        $this->assertNull(self::$status->normalize('not-a-status'));
        $this->assertNull(self::$status->normalize(''));
    }

    public function testANewAppointmentCanOnlyStartInAnInitialStatus(): void
    {
        $this->assertTrue(self::$status->can_transition(null, 'Booked'));
        $this->assertTrue(self::$status->can_transition(null, 'Pending'));
        $this->assertFalse(self::$status->can_transition(null, 'Completed'));
        $this->assertFalse(self::$status->can_transition(null, 'Cancelled'));
    }

    public function testTheHappyPathIsAllowed(): void
    {
        $this->assertTrue(self::$status->can_transition('Booked', 'Confirmed'));
        $this->assertTrue(self::$status->can_transition('Confirmed', 'Completed'));
        $this->assertTrue(self::$status->can_transition('Booked', 'Cancelled'));
        $this->assertTrue(self::$status->can_transition('Confirmed', 'No-show'));
    }

    public function testFinalStatusesCannotBeChanged(): void
    {
        $this->assertTrue(self::$status->is_terminal('Completed'));
        $this->assertTrue(self::$status->is_terminal('Cancelled'));
        $this->assertTrue(self::$status->is_terminal('No-show'));

        $this->assertFalse(self::$status->can_transition('Completed', 'Booked'));
        $this->assertFalse(self::$status->can_transition('Cancelled', 'Confirmed'));
        $this->assertFalse(self::$status->can_transition('No-show', 'Booked'));
    }

    public function testSavingTheSameStatusIsAlwaysAllowed(): void
    {
        $this->assertTrue(self::$status->can_transition('Completed', 'Completed'));
        $this->assertTrue(self::$status->can_transition('Booked', 'Booked'));
    }

    public function testAssertTransitionThrowsForInvalidChanges(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::$status->assert_transition('Completed', 'Booked');
    }

    public function testAllowedTransitionsAreListed(): void
    {
        $this->assertSame(['Booked', 'Pending', 'Draft'], self::$status->allowed_transitions(null));
        $this->assertContains('Confirmed', self::$status->allowed_transitions('Booked'));
        $this->assertSame([], self::$status->allowed_transitions('Cancelled'));
    }

    public function testACancelledAppointmentReleasesItsSlot(): void
    {
        $this->assertTrue(self::$status->releases_slot(['status' => 'Cancelled']));
        $this->assertFalse(self::$status->releases_slot(['status' => 'Booked']));
        $this->assertFalse(self::$status->releases_slot(['status' => 'Confirmed']));
    }

    public function testAnExpiredHoldReleasesItsSlot(): void
    {
        $this->assertTrue(
            self::$status->releases_slot([
                'status' => 'Pending',
                'hold_expires_datetime' => '2026-09-30 10:00:00',
            ], '2026-09-30 10:05:00'),
        );

        $this->assertFalse(
            self::$status->releases_slot([
                'status' => 'Pending',
                'hold_expires_datetime' => '2026-09-30 10:10:00',
            ], '2026-09-30 10:05:00'),
        );

        // A hold without an expiration date keeps blocking the slot.
        $this->assertFalse(self::$status->releases_slot(['status' => 'Pending']));
    }
}
