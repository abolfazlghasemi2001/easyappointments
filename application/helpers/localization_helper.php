<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Persian / RTL localization helper.
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Localization helper.
 *
 * The helper takes care of the Persian (Jalali) calendar display, the Persian digits and the text direction of the
 * application. The values that are stored in the database always remain Gregorian, so this helper is only used while
 * rendering the user interface (and when interpreting the dates that the users type in Jalali format).
 */

if (!function_exists('rtl_languages')) {
    /**
     * Get the language slugs that are written from right to left.
     *
     * @return array
     */
    function rtl_languages(): array
    {
        return ['persian', 'arabic', 'hebrew'];
    }
}

if (!function_exists('is_rtl')) {
    /**
     * Check whether the provided (or the current application) language is a right-to-left language.
     *
     * @param string|null $language Language slug (defaults to the current application language).
     *
     * @return bool
     */
    function is_rtl(?string $language = null): bool
    {
        $language ??= config('language') ?: 'english';

        return in_array(strtolower((string) $language), rtl_languages(), true);
    }
}

if (!function_exists('app_text_direction')) {
    /**
     * Get the text direction of the current application language.
     *
     * @return string Returns "rtl" or "ltr".
     */
    function app_text_direction(): string
    {
        return is_rtl() ? 'rtl' : 'ltr';
    }
}

if (!function_exists('app_timezone')) {
    /**
     * Get the timezone that is used for displaying the dates of the application.
     *
     * The timezone falls back to the configured "default_timezone" setting and, if that is not available either, to
     * the "APP_TIMEZONE" environment value or "Asia/Tehran" for Persian installations.
     *
     * @return string
     */
    function app_timezone(): string
    {
        static $timezone = null;

        if ($timezone !== null) {
            return $timezone;
        }

        $candidates = [
            env('APP_TIMEZONE'),
            function_exists('setting') ? safe_setting('display_timezone') : null,
            function_exists('setting') ? safe_setting('default_timezone') : null,
            date_default_timezone_get(),
            is_rtl() ? 'Asia/Tehran' : 'UTC',
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && in_array($candidate, timezone_identifiers_list(), true)) {
                return $timezone = $candidate;
            }
        }

        return $timezone = 'UTC';
    }
}

if (!function_exists('safe_setting')) {
    /**
     * Read a setting value without failing when the database (or the settings table) is not available yet.
     *
     * @param string $name Setting name.
     * @param mixed|null $default Default value.
     *
     * @return mixed
     */
    function safe_setting(string $name, mixed $default = null): mixed
    {
        try {
            $CI = &get_instance();

            if (!$CI || !$CI->db->table_exists('settings')) {
                return $default;
            }

            $value = setting($name);

            return $value === null || $value === '' ? $default : $value;
        } catch (Throwable) {
            return $default;
        }
    }
}

if (!function_exists('jalali_calendar_enabled')) {
    /**
     * Check whether the Jalali (Solar Hijri) calendar must be used for displaying the dates.
     *
     * @return bool
     */
    function jalali_calendar_enabled(): bool
    {
        static $enabled = null;

        if ($enabled !== null) {
            return $enabled;
        }

        $calendar_type = safe_setting('calendar_type');

        if ($calendar_type !== null) {
            return $enabled = $calendar_type === 'jalali';
        }

        // Fall back to the language of the installation when the setting is not available.
        return $enabled = is_rtl();
    }
}

if (!function_exists('persian_digits_enabled')) {
    /**
     * Check whether the digits must be rendered with Persian numerals.
     *
     * @return bool
     */
    function persian_digits_enabled(): bool
    {
        static $enabled = null;

        if ($enabled !== null) {
            return $enabled;
        }

        $value = safe_setting('persian_digits');

        if ($value !== null) {
            return $enabled = (string) $value === '1';
        }

        return $enabled = is_rtl();
    }
}

if (!function_exists('jalali_date')) {
    /**
     * Get the Jalali date library instance.
     *
     * @return Jalali_date
     */
    function jalali_date(): Jalali_date
    {
        /** @var EA_Controller|CI_Controller $CI */
        $CI = &get_instance();

        if (!isset($CI->jalali_date)) {
            $CI->load->library('jalali_date');
        }

        return $CI->jalali_date;
    }
}

if (!function_exists('localize_digits')) {
    /**
     * Convert a value into a localized (Persian) digit string when the corresponding setting is enabled.
     *
     * @param string|int|float|null $value Input value.
     *
     * @return string
     */
    function localize_digits(string|int|float|null $value): string
    {
        $value = (string) $value;

        return persian_digits_enabled() ? jalali_date()->to_persian_digits($value) : $value;
    }
}

if (!function_exists('to_latin_digits')) {
    /**
     * Convert the Persian or Arabic digits of a user provided value into ASCII digits.
     *
     * @param string|int|float|null $value Input value.
     *
     * @return string
     */
    function to_latin_digits(string|int|float|null $value): string
    {
        return jalali_date()->to_latin_digits($value);
    }
}

if (!function_exists('localize_date')) {
    /**
     * Format a date value for display (Jalali or Gregorian, depending on the application settings).
     *
     * @param DateTimeInterface|string|null $value Date value.
     * @param bool $with_time Append the time of the day.
     * @param string $separator Separator between the date and the time parts.
     *
     * @return string
     */
    function localize_date(DateTimeInterface|string|null $value, bool $with_time = false, string $separator = ' '): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $date_time = $value instanceof DateTimeInterface ? $value : new DateTime($value);

            $date_time = $date_time->setTimezone(new DateTimeZone(app_timezone()));

            $time = $date_time->format(get_time_format());

            if (jalali_calendar_enabled()) {
                $date_pattern = 'YYYY/MM/DD';

                $date = jalali_date()->format($date_time, $date_pattern, false);
            } else {
                $date = $date_time->format(get_date_format());
            }

            return $with_time
                ? localize_digits($date) . $separator . localize_digits($time)
                : localize_digits($date);
        } catch (Throwable $exception) {
            log_message('error', 'Invalid date provided to the "localize_date" helper: ' . $exception->getMessage());

            return '';
        }
    }
}

if (!function_exists('localize_time')) {
    /**
     * Format a time value for display.
     *
     * @param DateTimeInterface|string|null $value Time value.
     *
     * @return string
     */
    function localize_time(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $date_time = $value instanceof DateTimeInterface ? $value : new DateTime($value);

            $date_time = $date_time->setTimezone(new DateTimeZone(app_timezone()));

            return localize_digits($date_time->format(get_time_format()));
        } catch (Throwable $exception) {
            log_message('error', 'Invalid time provided to the "localize_time" helper: ' . $exception->getMessage());

            return '';
        }
    }
}

if (!function_exists('localize_date_time')) {
    /**
     * Format a date-time value for display.
     *
     * @param DateTimeInterface|string|null $value Date-time value.
     *
     * @return string
     */
    function localize_date_time(DateTimeInterface|string|null $value): string
    {
        return localize_date($value, true);
    }
}

if (!function_exists('localize_number')) {
    /**
     * Format a numeric value for display (adding thousands separators and Persian digits when enabled).
     *
     * @param string|int|float|null $value Numeric value.
     * @param int $decimals Number of decimals.
     *
     * @return string
     */
    function localize_number(string|int|float|null $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = number_format((float) $value, $decimals, '.', ',');

        if ($decimals === 0) {
            $number = rtrim(rtrim($number, '0'), '.');
        }

        return localize_digits($number);
    }
}

if (!function_exists('localize_phone')) {
    /**
     * Format a phone number for display with Persian digits.
     *
     * @param string|null $value Phone number.
     *
     * @return string
     */
    function localize_phone(?string $value): string
    {
        return localize_digits((string) $value);
    }
}

if (!function_exists('is_valid_date_value')) {
    /**
     * Check whether a value is a valid Gregorian date in the "Y-m-d" format.
     *
     * @param mixed $value Date value.
     *
     * @return bool
     */
    function is_valid_date_value(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}

if (!function_exists('app_asset_url')) {
    /**
     * Get the URL of a stylesheet or script, preferring its right-to-left variant when the application language is
     * written from right to left.
     *
     * The ".rtl" variants are generated during the build ("styles:rtl" gulp task) from the same SCSS sources, so both
     * directions always share the same source of truth. When the variant file does not exist (e.g. the assets were not
     * compiled yet) the original file is used instead.
     *
     * @param string $path Asset path, relative to the project root (e.g. "assets/css/general.css").
     *
     * @return string
     */
    function app_asset_url(string $path): string
    {
        if (!is_rtl()) {
            return asset_url($path);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if ($extension === '') {
            return asset_url($path);
        }

        $variant = substr($path, 0, -(strlen($extension) + 1)) . '.rtl.' . $extension;

        $minified_variant = preg_replace('/\.css$/', '.min.css', $variant);

        if (!is_file(FCPATH . $variant) && !is_file(FCPATH . $minified_variant)) {
            return asset_url($path);
        }

        return asset_url($variant);
    }
}

if (!function_exists('app_script_url')) {
    /**
     * Get the URL of a script that is only required by the right-to-left (e.g. Persian) interface.
     *
     * @param string $path Asset path, relative to the project root.
     *
     * @return string|null Returns null when the asset is not needed for the current language.
     */
    function app_rtl_asset_url(string $path): ?string
    {
        return is_rtl() ? asset_url($path) : null;
    }
}
