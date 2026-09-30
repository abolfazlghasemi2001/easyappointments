<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Iranian phone number helpers.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

if (!function_exists('normalize_iran_phone_number')) {
    /**
     * Normalize an Iranian mobile number to the "09xxxxxxxxx" form.
     *
     * Accepted inputs: "09123456789", "9123456789", "+989123456789", "00989123456789", "۰۹۱۲۳۴۵۶۷۸۹" and any of
     * the above with spaces, dashes or parentheses.
     *
     * @param string|null $phone_number Raw phone number.
     *
     * @return string Returns the normalized number or an empty string when it cannot be normalized.
     */
    function normalize_iran_phone_number(?string $phone_number): string
    {
        if ($phone_number === null) {
            return '';
        }

        $digits = function_exists('to_latin_digits')
            ? to_latin_digits($phone_number)
            : strtr($phone_number, [
                '۰' => '0',
                '۱' => '1',
                '۲' => '2',
                '۳' => '3',
                '۴' => '4',
                '۵' => '5',
                '۶' => '6',
                '۷' => '7',
                '۸' => '8',
                '۹' => '9',
                '٠' => '0',
                '١' => '1',
                '٢' => '2',
                '٣' => '3',
                '٤' => '4',
                '٥' => '5',
                '٦' => '6',
                '٧' => '7',
                '٨' => '8',
                '٩' => '9',
            ]);

        $digits = preg_replace('/\D+/', '', (string) $digits) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }
}

if (!function_exists('is_valid_iran_phone_number')) {
    /**
     * Check whether a value is a valid Iranian mobile number (09xxxxxxxxx).
     *
     * A landline number ("021...") is also accepted, because businesses may register one.
     *
     * @param string|null $phone_number Phone number.
     *
     * @return bool
     */
    function is_valid_iran_phone_number(?string $phone_number): bool
    {
        $normalized = normalize_iran_phone_number($phone_number);

        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match('/^09\d{9}$/', $normalized) || (bool) preg_match('/^0\d{9,10}$/', $normalized);
    }
}

if (!function_exists('is_valid_iran_mobile_number')) {
    /**
     * Check whether a value is a valid Iranian mobile number (09xxxxxxxxx only, no landlines).
     *
     * @param string|null $phone_number Phone number.
     *
     * @return bool
     */
    function is_valid_iran_mobile_number(?string $phone_number): bool
    {
        return (bool) preg_match('/^09\d{9}$/', normalize_iran_phone_number($phone_number));
    }
}

if (!function_exists('mask_phone_number')) {
    /**
     * Mask a phone number for logging or display (09123456789 -> 0912***6789).
     *
     * @param string|null $phone_number Phone number.
     *
     * @return string
     */
    function mask_phone_number(?string $phone_number): string
    {
        $normalized = normalize_iran_phone_number($phone_number);

        if (strlen($normalized) < 8) {
            return str_repeat('*', strlen((string) $phone_number));
        }

        return substr($normalized, 0, 4) . str_repeat('*', strlen($normalized) - 8) . substr($normalized, -4);
    }
}
