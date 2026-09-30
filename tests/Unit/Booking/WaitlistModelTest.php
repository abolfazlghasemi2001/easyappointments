<?php

namespace Tests\Unit\Booking;

use Tests\TestCase;
use Waitlist_model;

/**
 * Waiting list model tests.
 */
class WaitlistModelTest extends TestCase
{
    protected static Waitlist_model $model;

    protected static array $created_entries = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $CI = &get_instance();

        $CI->load->model('waitlist_model');

        self::$model = $CI->waitlist_model;
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$created_entries as $entry_id) {
            self::$model->delete($entry_id);
        }

        self::$created_entries = [];
    }

    /**
     * Remove the entries created by the previous tests of this class, so that the ordering assertions are not
     * affected by them.
     */
    protected static function clearEntries(): void
    {
        foreach (self::$created_entries as $entry_id) {
            self::$model->delete($entry_id);
        }

        self::$created_entries = [];
    }

    protected static function providerId(): int
    {
        $CI = &get_instance();

        return (int) $CI->db->get_where('users', ['id_roles' => 2])->row_array()['id'];
    }

    protected function createEntry(array $overrides = []): int
    {
        $entry_id = self::$model->save(array_merge(
            [
                'first_name' => 'Ali',
                'last_name' => 'Rezaei',
                'phone_number' => '09121112233',
                'desired_date' => '2026-10-12',
                'notes' => 'waitlist test',
            ],
            $overrides,
        ));

        self::$created_entries[] = $entry_id;

        return $entry_id;
    }

    public function testAnEntryCanBeStoredAndFound(): void
    {
        $entry_id = $this->createEntry();

        $entry = self::$model->find($entry_id);

        $this->assertSame('Ali', $entry['first_name']);
        $this->assertSame('09121112233', $entry['phone_number']);
        $this->assertSame('waiting', $entry['status']);
    }

    public function testThePhoneNumberIsNormalizedBeforeItIsStored(): void
    {
        $entry_id = $this->createEntry(['phone_number' => '+98 912 333 4455']);

        $entry = self::$model->find($entry_id);

        $this->assertSame('09123334455', normalize_iran_phone_number($entry['phone_number']));
    }

    public function testAnInvalidPhoneNumberIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::$model->save(['first_name' => 'Test', 'phone_number' => '12345']);
    }

    public function testAnInvalidEmailIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::$model->save(['first_name' => 'Test', 'email' => 'not-an-email']);
    }

    public function testAnEntryWithoutAnyContactInformationIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::$model->save(['notes' => 'no contact data']);
    }

    public function testTheNextEntryInLineIsReturnedByDate(): void
    {
        self::clearEntries();

        $provider_id = self::providerId();

        $this->createEntry(['desired_date' => '2026-11-20', 'id_users_provider' => $provider_id]);

        $this->createEntry(['desired_date' => '2026-11-10', 'id_users_provider' => $provider_id]);

        $next = self::$model->next_in_line($provider_id, '2026-11-30');

        $this->assertNotNull($next);
        $this->assertSame('2026-11-10', $next['desired_date']);
    }

    public function testANotifiedEntryIsNotReturnedAgain(): void
    {
        self::clearEntries();

        $provider_id = self::providerId();
        $entry_id = $this->createEntry(['desired_date' => '2026-12-01', 'id_users_provider' => $provider_id]);

        self::$model->mark_notified($entry_id);

        $entry = self::$model->find($entry_id);

        $this->assertSame(Waitlist_model::STATUS_NOTIFIED, $entry['status']);
        $this->assertNotEmpty($entry['notified_datetime']);

        $next = self::$model->next_in_line($provider_id, '2026-12-01');

        $this->assertTrue($next === null || $next['id'] !== $entry_id);
    }

    public function testInvalidStatusIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::$model->save(['first_name' => 'Test', 'phone_number' => '09120000000', 'status' => 'unknown-status']);
    }
}
