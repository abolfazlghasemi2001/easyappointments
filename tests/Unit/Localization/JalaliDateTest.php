<?php

namespace Tests\Unit\Localization;

use DateTime;
use Jalali_date;
use Tests\TestCase;

/**
 * Jalali (Solar Hijri) calendar library tests.
 */
class JalaliDateTest extends TestCase
{
    protected static Jalali_date $jalali;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $CI = &get_instance();

        $CI->load->library('jalali_date');

        self::$jalali = $CI->jalali_date;
    }

    public function testGregorianToJalaliConvertsKnownDates(): void
    {
        $cases = [
            '2026-09-30' => [1405, 7, 8],
            '2026-03-21' => [1405, 1, 1],
            '2025-03-21' => [1404, 1, 1],
            '2025-03-20' => [1403, 12, 30],
            '2024-03-20' => [1403, 1, 1],
            '2027-03-21' => [1406, 1, 1],
        ];

        foreach ($cases as $gregorian => $expected) {
            [$year, $month, $day] = array_map('intval', explode('-', $gregorian));

            $this->assertSame(
                $expected,
                self::$jalali->gregorian_to_jalali($year, $month, $day),
                'Failed for the Gregorian date ' . $gregorian,
            );
        }
    }

    public function testJalaliToGregorianConvertsKnownDates(): void
    {
        $cases = [
            [1405, 7, 8, '2026-09-30'],
            [1405, 1, 1, '2026-03-21'],
            [1403, 12, 30, '2025-03-20'],
            [1403, 1, 1, '2024-03-20'],
            [1406, 1, 1, '2027-03-21'],
        ];

        foreach ($cases as [$year, $month, $day, $expected]) {
            [$gregorian_year, $gregorian_month, $gregorian_day] = self::$jalali->jalali_to_gregorian(
                $year,
                $month,
                $day,
            );

            $this->assertSame(
                $expected,
                sprintf('%04d-%02d-%02d', $gregorian_year, $gregorian_month, $gregorian_day),
            );
        }
    }

    public function testConversionsAreReversibleForTwentyYears(): void
    {
        $date = new DateTime('2015-01-01');

        $end = new DateTime('2035-01-01');

        while ($date < $end) {
            [$year, $month, $day] = self::$jalali->gregorian_to_jalali(
                (int) $date->format('Y'),
                (int) $date->format('n'),
                (int) $date->format('j'),
            );

            [$gregorian_year, $gregorian_month, $gregorian_day] = self::$jalali->jalali_to_gregorian(
                $year,
                $month,
                $day,
            );

            $this->assertSame(
                $date->format('Y-m-d'),
                sprintf('%04d-%02d-%02d', $gregorian_year, $gregorian_month, $gregorian_day),
                'Round trip failed for ' . $date->format('Y-m-d'),
            );

            $date->modify('+1 day');
        }
    }

    public function testLeapYearsAreDetected(): void
    {
        // 1403 and 1408 are leap years, 1404, 1405, 1406 and 1407 are not.
        $this->assertTrue(self::$jalali->is_leap_year(1403));
        $this->assertFalse(self::$jalali->is_leap_year(1404));
        $this->assertFalse(self::$jalali->is_leap_year(1405));
        $this->assertTrue(self::$jalali->is_leap_year(1408));
    }

    public function testMonthDaysAreCalculated(): void
    {
        $this->assertSame(31, self::$jalali->month_days(1405, 1));
        $this->assertSame(31, self::$jalali->month_days(1405, 6));
        $this->assertSame(30, self::$jalali->month_days(1405, 7));
        $this->assertSame(30, self::$jalali->month_days(1405, 11));
        $this->assertSame(29, self::$jalali->month_days(1405, 12));
        $this->assertSame(30, self::$jalali->month_days(1403, 12));
    }

    public function testInvalidJalaliDatesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::$jalali->to_gregorian(1405, 12, 30);
    }

    public function testInvalidMonthIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::$jalali->to_gregorian(1405, 13, 1);
    }

    public function testFormatUsesJalaliValuesAndPersianDigits(): void
    {
        $date = new DateTime('2026-09-30 14:30:00');

        $this->assertSame('۱۴۰۵/۰۷/۰۸', self::$jalali->format($date, 'YYYY/MM/DD'));

        $this->assertSame('چهارشنبه ۸ مهر ۱۴۰۵', self::$jalali->format($date, 'dddd D MMMM YYYY'));

        $this->assertSame('14:30', self::$jalali->format($date, 'HH:mm', false));
    }

    public function testDigitsAreConvertedInBothDirections(): void
    {
        $this->assertSame('۱۴۰۵/۰۷/۰۸', self::$jalali->to_persian_digits('1405/07/08'));

        $this->assertSame('1405/07/08', self::$jalali->to_latin_digits('۱۴۰۵/۰۷/۰۸'));

        $this->assertSame('1405/07/08', self::$jalali->to_latin_digits('1405/07/08'));
    }

    public function testParseReadsJalaliDates(): void
    {
        $this->assertSame('2026-09-30', self::$jalali->parse('1405/07/08')->format('Y-m-d'));

        $this->assertSame('2026-09-30', self::$jalali->parse('۱۴۰۵/۰۷/۰۸')->format('Y-m-d'));

        $this->assertSame('2026-09-30 14:30:00', self::$jalali->parse('1405/07/08 14:30:00')->format('Y-m-d H:i:s'));

        $this->assertNull(self::$jalali->parse('1405/13/08'));

        $this->assertNull(self::$jalali->parse('invalid'));
    }

    public function testWeekdayNamesAreTranslated(): void
    {
        $this->assertSame('شنبه', self::$jalali->weekday_name(6));

        $this->assertSame('ش', self::$jalali->weekday_name(6, true));

        $this->assertSame('چهارشنبه', self::$jalali->weekday_name_of('2026-09-30'));
    }
}
