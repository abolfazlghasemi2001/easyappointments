<?php

namespace Tests\Unit\Holidays;

use Holidays_model;
use Tests\TestCase;

/**
 * Holidays model tests.
 */
class HolidaysModelTest extends TestCase
{
    protected static Holidays_model $model;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $CI = &get_instance();

        $CI->load->model('holidays_model');

        self::$model = $CI->holidays_model;
    }

    public function testSaveCreatesAHolidayAndKeepsTheJalaliFieldsOfRecurringRecords(): void
    {
        $holiday_id = self::$model->save([
            'title' => 'Test recurring holiday',
            'holiday_date' => '2026-09-16', // 25 Shahrivar 1405
            'is_recurring' => true,
        ]);

        $holiday = self::$model->find($holiday_id);

        $this->assertSame('2026-09-16', $holiday['holiday_date']);

        $this->assertTrue($holiday['is_recurring']);

        $this->assertSame(6, $holiday['jalali_month']);

        $this->assertSame(25, $holiday['jalali_day']);

        self::$model->delete($holiday_id);
    }

    public function testSaveRejectsInvalidDateValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::$model->save([
            'title' => 'Invalid holiday',
            'holiday_date' => '2026-02-30',
        ]);
    }

    public function testSaveRejectsAnEmptyTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::$model->save([
            'title' => '',
            'holiday_date' => '2026-10-10',
        ]);
    }

    public function testRecurringHolidaysMatchTheSameJalaliDayOfEveryYear(): void
    {
        $holiday_id = self::$model->save([
            'title' => 'Test anniversary',
            'holiday_date' => '2026-09-16', // 25 Shahrivar 1405
            'is_recurring' => true,
        ]);

        $this->assertTrue(self::$model->is_holiday('2026-09-16'));

        // 25 Shahrivar 1406 == 2027-09-16
        $this->assertTrue(self::$model->is_holiday('2027-09-16'));

        $this->assertFalse(self::$model->is_holiday('2026-09-17'));

        self::$model->delete($holiday_id);
    }

    public function testNonRecurringHolidaysMatchOnlyTheStoredDate(): void
    {
        $holiday_id = self::$model->save([
            'title' => 'Test single holiday',
            'holiday_date' => '2026-10-11',
            'is_recurring' => false,
        ]);

        $this->assertTrue(self::$model->is_holiday('2026-10-11'));

        $this->assertFalse(self::$model->is_holiday('2027-10-11'));

        $holiday = self::$model->find($holiday_id);

        $this->assertNull($holiday['jalali_month']);

        $this->assertNull($holiday['jalali_day']);

        self::$model->delete($holiday_id);
    }

    public function testIsHolidayIgnoresInvalidDates(): void
    {
        $this->assertFalse(self::$model->is_holiday('invalid-date'));

        $this->assertFalse(self::$model->is_holiday(''));
    }

    public function testGetByDateReturnsEveryHolidayOfTheDate(): void
    {
        $holiday_id = self::$model->save([
            'title' => 'Test double holiday',
            'holiday_date' => '2026-10-12',
        ]);

        $holidays = self::$model->get_by_date('2026-10-12');

        $this->assertNotEmpty($holidays);

        $titles = array_column($holidays, 'title');

        $this->assertContains('Test double holiday', $titles);

        self::$model->delete($holiday_id);
    }

    public function testGetRangeGroupsTheHolidaysByDate(): void
    {
        $holiday_id = self::$model->save([
            'title' => 'Test range holiday',
            'holiday_date' => '2026-10-13',
        ]);

        $range = self::$model->get_range('2026-10-01', '2026-10-31');

        $this->assertArrayHasKey('2026-10-13', $range);

        $this->assertSame('Test range holiday', $range['2026-10-13'][0]['title']);

        self::$model->delete($holiday_id);
    }

    public function testImportOfficialHolidaysIsIdempotent(): void
    {
        $count = fn() => count(self::$model->search('', null));

        $before = $count();

        self::$model->import_official_holidays(1406);

        $after_first_import = $count();

        self::$model->import_official_holidays(1406);

        $this->assertSame($after_first_import, $count(), 'A second import must not create duplicate records.');

        $this->assertGreaterThanOrEqual($before, $after_first_import);

        // The recurring holidays must be returned for the following years as well. The 1st of Farvardin (Nowruz) of
        // the 1400 Jalali year is the 21st of March 2021.
        $this->assertTrue(self::$model->is_holiday('2021-03-21'));
    }

    public function testSearchFindsHolidaysByKeywordAndKeepsTheDatabaseClean(): void
    {
        $keyword = 'UniqueHolidayKeyword';

        $holiday_id = self::$model->save([
            'title' => 'Test ' . $keyword,
            'holiday_date' => '2026-10-14',
        ]);

        $results = self::$model->search($keyword);

        $this->assertCount(1, $results);

        $this->assertSame($holiday_id, (int) $results[0]['id']);

        self::$model->delete($holiday_id);

        $this->assertCount(0, self::$model->search($keyword));
    }
}
