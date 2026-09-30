<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Persian (Jalali / Solar Hijri) calendar support library.
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Jalali (Solar Hijri) calendar library.
 *
 * Converts dates between the Gregorian and the Jalali calendar without any external dependency. The library is used
 * for display and input purposes only: every date keeps being stored in the database in the Gregorian calendar (in
 * the configured application timezone), exactly as it was before.
 *
 * The conversion is based on the well known Solar Hijri algorithm that is also used by "jdf" and "jalaali-js", which
 * agrees with the official Iranian calendar for the practical range of years (1178 to 1633 Jalali).
 *
 * @package Libraries
 */
class Jalali_date
{
    /**
     * Persian names of the Jalali months.
     */
    protected const MONTH_NAMES = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    /**
     * Persian names of the weekdays, indexed by the PHP "w" weekday number (0 = Sunday).
     */
    protected const WEEKDAY_NAMES = [
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنجشنبه',
        5 => 'جمعه',
        6 => 'شنبه',
    ];

    /**
     * Short Persian names of the weekdays, indexed by the PHP "w" weekday number (0 = Sunday).
     */
    protected const WEEKDAY_NAMES_SHORT = [
        0 => 'ی',
        1 => 'د',
        2 => 'س',
        3 => 'چ',
        4 => 'پ',
        5 => 'ج',
        6 => 'ش',
    ];

    /**
     * English (fallback) names of the Jalali months, used by non-Persian locales.
     */
    protected const MONTH_NAMES_LATIN = [
        1 => 'Farvardin',
        2 => 'Ordibehesht',
        3 => 'Khordad',
        4 => 'Tir',
        5 => 'Mordad',
        6 => 'Shahrivar',
        7 => 'Mehr',
        8 => 'Aban',
        9 => 'Azar',
        10 => 'Dey',
        11 => 'Bahman',
        12 => 'Esfand',
    ];

    /**
     * Latin (fallback) names of the weekdays, indexed by the PHP "w" weekday number (0 = Sunday).
     */
    protected const WEEKDAY_NAMES_LATIN = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * Convert a Gregorian date into a Jalali date.
     *
     * @param int $year Gregorian year (e.g. 2026).
     * @param int $month Gregorian month (1-12).
     * @param int $day Gregorian day (1-31).
     *
     * @return array Returns [jalali year, jalali month, jalali day].
     */
    public function gregorian_to_jalali(int $year, int $month, int $day): array
    {
        $days_in_months = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        $adjusted_year = $month > 2 ? $year + 1 : $year;

        $days = 355666
            + 365 * $year
            + intdiv($adjusted_year + 3, 4)
            - intdiv($adjusted_year + 99, 100)
            + intdiv($adjusted_year + 399, 400)
            + $day
            + $days_in_months[$month - 1];

        $jalali_year = -1595 + 33 * intdiv($days, 12053);

        $days %= 12053;

        $jalali_year += 4 * intdiv($days, 1461);

        $days %= 1461;

        if ($days > 365) {
            $jalali_year += intdiv($days - 1, 365);

            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jalali_month = 1 + intdiv($days, 31);

            $jalali_day = 1 + $days % 31;
        } else {
            $jalali_month = 7 + intdiv($days - 186, 30);

            $jalali_day = 1 + ($days - 186) % 30;
        }

        return [$jalali_year, $jalali_month, $jalali_day];
    }

    /**
     * Convert a Jalali date into a Gregorian date.
     *
     * @param int $year Jalali year (e.g. 1405).
     * @param int $month Jalali month (1-12).
     * @param int $day Jalali day (1-31).
     *
     * @return array Returns [gregorian year, gregorian month, gregorian day].
     *
     * @throws InvalidArgumentException
     */
    public function jalali_to_gregorian(int $year, int $month, int $day): array
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Invalid Jalali month value provided: ' . $month);
        }

        if ($day < 1 || $day > 31) {
            throw new InvalidArgumentException('Invalid Jalali day value provided: ' . $day);
        }

        $jalali_year = $year + 1595;

        $days = -355668
            + 365 * $jalali_year
            + intdiv($jalali_year, 33) * 8
            + intdiv(($jalali_year % 33) + 3, 4)
            + $day
            + ($month < 7 ? ($month - 1) * 31 : ($month - 7) * 30 + 186);

        $gregorian_year = 400 * intdiv($days, 146097);

        $days %= 146097;

        if ($days > 36524) {
            $gregorian_year += 100 * intdiv(--$days, 36524);

            $days %= 36524;

            if ($days >= 365) {
                $days++;
            }
        }

        $gregorian_year += 4 * intdiv($days, 1461);

        $days %= 1461;

        if ($days > 365) {
            $gregorian_year += intdiv($days - 1, 365);

            $days = ($days - 1) % 365;
        }

        $gregorian_day = $days + 1;

        $leap = ($gregorian_year % 4 === 0 && $gregorian_year % 100 !== 0) || $gregorian_year % 400 === 0;

        $month_days = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        for ($gregorian_month = 1; $gregorian_month <= 12 && $gregorian_day > $month_days[$gregorian_month]; $gregorian_month++) {
            $gregorian_day -= $month_days[$gregorian_month];
        }

        return [$gregorian_year, $gregorian_month, $gregorian_day];
    }

    /**
     * Check whether a Jalali year is a leap year.
     *
     * The check is derived from the conversion itself (a leap year has 366 days between two consecutive Farvardin
     * firsts), which guarantees that the leap rule stays consistent with the conversion methods.
     *
     * @param int $year Jalali year.
     *
     * @return bool
     */
    public function is_leap_year(int $year): bool
    {
        $current_start = $this->jalali_to_gregorian($year, 1, 1);

        $next_start = $this->jalali_to_gregorian($year + 1, 1, 1);

        $current = new DateTime(sprintf('%04d-%02d-%02d', ...$current_start));

        $next = new DateTime(sprintf('%04d-%02d-%02d', ...$next_start));

        return (int) $current->diff($next)->format('%a') === 366;
    }

    /**
     * Get the number of days of a Jalali month.
     *
     * @param int $year Jalali year.
     * @param int $month Jalali month (1-12).
     *
     * @return int
     *
     * @throws InvalidArgumentException
     */
    public function month_days(int $year, int $month): int
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Invalid Jalali month value provided: ' . $month);
        }

        if ($month <= 6) {
            return 31;
        }

        if ($month <= 11) {
            return 30;
        }

        return $this->is_leap_year($year) ? 30 : 29;
    }

    /**
     * Get the Persian name of a Jalali month.
     *
     * @param int $month Jalali month (1-12).
     * @param bool $latin Return the Latin transliteration instead of the Persian name.
     *
     * @return string
     */
    public function month_name(int $month, bool $latin = false): string
    {
        return $latin ? self::MONTH_NAMES_LATIN[$month] ?? '' : self::MONTH_NAMES[$month] ?? '';
    }

    /**
     * Get all the Jalali month names.
     *
     * @param bool $latin Return the Latin transliterations instead of the Persian names.
     *
     * @return array
     */
    public function month_names(bool $latin = false): array
    {
        return $latin ? self::MONTH_NAMES_LATIN : self::MONTH_NAMES;
    }

    /**
     * Get the name of a weekday.
     *
     * @param int $weekday PHP weekday number (0 = Sunday ... 6 = Saturday).
     * @param bool $short Return the short form of the weekday name.
     * @param bool $latin Return the Latin name instead of the Persian one.
     *
     * @return string
     */
    public function weekday_name(int $weekday, bool $short = false, bool $latin = false): string
    {
        $weekday = (($weekday % 7) + 7) % 7;

        if ($latin) {
            $name = self::WEEKDAY_NAMES_LATIN[$weekday];

            return $short ? substr($name, 0, 3) : $name;
        }

        return $short ? self::WEEKDAY_NAMES_SHORT[$weekday] : self::WEEKDAY_NAMES[$weekday];
    }

    /**
     * Get the weekday name of a Gregorian date-time value.
     *
     * @param string $date Gregorian date-time value.
     * @param bool $short Return the short form of the weekday name.
     * @param bool $latin Return the Latin name instead of the Persian one.
     *
     * @return string
     */
    public function weekday_name_of(string $date, bool $short = false, bool $latin = false): string
    {
        return $this->weekday_name((int) date('w', strtotime($date)), $short, $latin);
    }

    /**
     * Convert a Gregorian date-time value into the components of the equivalent Jalali date.
     *
     * @param string|DateTimeInterface $value Gregorian date-time value.
     *
     * @return array Returns an array with "year", "month", "day", "hour", "minute", "second", "weekday",
     *               "month_name" and "weekday_name" keys.
     */
    public function to_jalali(string|DateTimeInterface $value): array
    {
        $date_time = $value instanceof DateTimeInterface ? $value : new DateTime($value);

        [$year, $month, $day] = $this->gregorian_to_jalali(
            (int) $date_time->format('Y'),
            (int) $date_time->format('n'),
            (int) $date_time->format('j'),
        );

        $weekday = (int) $date_time->format('w');

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'hour' => (int) $date_time->format('G'),
            'minute' => (int) $date_time->format('i'),
            'second' => (int) $date_time->format('s'),
            'weekday' => $weekday,
            'month_name' => $this->month_name($month),
            'weekday_name' => $this->weekday_name($weekday),
        ];
    }

    /**
     * Convert a Jalali date (and optional time) into a Gregorian DateTime object.
     *
     * @param int $year Jalali year.
     * @param int $month Jalali month.
     * @param int $day Jalali day.
     * @param int $hour Hour of the day (0-23).
     * @param int $minute Minute of the hour (0-59).
     * @param int $second Second of the minute (0-59).
     *
     * @return DateTime
     *
     * @throws InvalidArgumentException
     */
    public function to_gregorian(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
    ): DateTime {
        if ($day < 1 || $day > $this->month_days($year, $month)) {
            throw new InvalidArgumentException(
                'Invalid Jalali day value provided for ' . $year . '/' . $month . ': ' . $day,
            );
        }

        [$gregorian_year, $gregorian_month, $gregorian_day] = $this->jalali_to_gregorian($year, $month, $day);

        $date_time = new DateTime('now', new DateTimeZone(date_default_timezone_get()));

        $date_time->setDate($gregorian_year, $gregorian_month, $gregorian_day);

        $date_time->setTime($hour, $minute, $second);

        return $date_time;
    }

    /**
     * Format a Gregorian date value with a Jalali (Solar Hijri) pattern.
     *
     * Supported pattern tokens:
     *
     *   - YYYY  Full Jalali year (e.g. ۱۴۰۵)
     *   - YY    Two digit Jalali year (e.g. ۰۵)
     *   - MMMM  Full Jalali month name (e.g. مهر)
     *   - MMM   Short Jalali month name
     *   - MM    Two digit Jalali month (e.g. ۰۷)
     *   - M     Jalali month without leading zero
     *   - DD    Two digit Jalali day (e.g. ۰۸)
     *   - D     Jalali day without leading zero
     *   - dddd  Full Persian weekday name (e.g. شنبه)
     *   - ddd   Short Persian weekday name (e.g. ش)
     *   - HH    Hours (24 hours, zero padded)
     *   - mm    Minutes
     *   - ss    Seconds
     *
     * @param string|DateTimeInterface $value Gregorian date-time value.
     * @param string $pattern Output pattern.
     * @param bool $persian_digits Convert the ASCII digits into Persian digits.
     *
     * @return string
     */
    public function format(
        string|DateTimeInterface $value,
        string $pattern = 'YYYY/MM/DD',
        bool $persian_digits = true,
    ): string {
        $jalali = $this->to_jalali($value);

        $replacements = [
            'YYYY' => (string) $jalali['year'],
            'YY' => substr((string) $jalali['year'], -2),
            'MMMM' => $jalali['month_name'],
            'MMM' => mb_substr($jalali['month_name'], 0, 4),
            'MM' => str_pad((string) $jalali['month'], 2, '0', STR_PAD_LEFT),
            'DD' => str_pad((string) $jalali['day'], 2, '0', STR_PAD_LEFT),
            'dddd' => $jalali['weekday_name'],
            'ddd' => $this->weekday_name($jalali['weekday'], true),
            'HH' => str_pad((string) $jalali['hour'], 2, '0', STR_PAD_LEFT),
            'mm' => str_pad((string) $jalali['minute'], 2, '0', STR_PAD_LEFT),
            'ss' => str_pad((string) $jalali['second'], 2, '0', STR_PAD_LEFT),
            'M' => (string) $jalali['month'],
            'D' => (string) $jalali['day'],
        ];

        // Longer tokens are matched first (e.g. "MMMM" before "MM"), and all the tokens are replaced in a single
        // pass, so that the produced values cannot be matched again by another token.
        uksort($replacements, static fn($a, $b) => strlen($b) <=> strlen($a));

        $output = preg_replace_callback(
            '/' . implode('|', array_map(static fn($token) => preg_quote($token, '/'), array_keys($replacements))) . '/',
            static fn($matches) => $replacements[$matches[0]],
            $pattern,
        );

        return $persian_digits ? $this->to_persian_digits($output) : $output;
    }

    /**
     * Convert the ASCII digits of a value into Persian digits.
     *
     * @param string|int|float|null $value Input value.
     *
     * @return string
     */
    public function to_persian_digits(string|int|float|null $value): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            (string) $value,
        );
    }

    /**
     * Convert the Persian (or Arabic) digits of a value into ASCII digits.
     *
     * @param string|int|float|null $value Input value.
     *
     * @return string
     */
    public function to_latin_digits(string|int|float|null $value): string
    {
        return str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            (string) $value,
        );
    }

    /**
     * Parse a Jalali date string into a Gregorian DateTime object.
     *
     * Accepted formats (Persian and Latin digits are both supported):
     *
     *   - 1405/07/08
     *   - 1405-07-08
     *   - 1405/07/08 14:30
     *   - 1405/07/08 14:30:00
     *   - 1405/07/08 02:30 PM
     *
     * @param string $value Jalali date string.
     *
     * @return DateTime|null Returns NULL when the value cannot be parsed.
     */
    public function parse(string $value): ?DateTime
    {
        $normalized = trim($this->to_latin_digits($value));

        if ($normalized === '') {
            return null;
        }

        $pattern =
            '/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})(?:[\sT]+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(am|pm)?)?$/i';

        if (!preg_match($pattern, $normalized, $matches)) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        $hour = isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : 0;
        $minute = isset($matches[5]) && $matches[5] !== '' ? (int) $matches[5] : 0;
        $second = isset($matches[6]) && $matches[6] !== '' ? (int) $matches[6] : 0;
        $meridiem = isset($matches[7]) ? strtolower($matches[7]) : '';

        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        }

        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        try {
            return $this->to_gregorian($year, $month, $day, $hour, $minute, $second);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
