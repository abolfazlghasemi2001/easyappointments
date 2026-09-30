/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Jalali (Solar Hijri) date utility.
 *
 * Client side counterpart of the "Jalali_date" PHP library. It converts dates between the Gregorian and the Jalali
 * calendar, formats them with the Persian month/weekday names and converts the digits into Persian numerals. All the
 * stored values remain Gregorian Date objects; this module only handles the display and the interpretation of the
 * values that the users typed in the Jalali format.
 */
window.App.Utils.Jalali = (function () {
    // Transliterations

    const MONTH_NAMES = [
        'فروردین',
        'اردیبهشت',
        'خرداد',
        'تیر',
        'مرداد',
        'شهریور',
        'مهر',
        'آبان',
        'آذر',
        'دی',
        'بهمن',
        'اسفند',
    ];

    const MONTH_NAMES_LATIN = [
        'Farvardin',
        'Ordibehesht',
        'Khordad',
        'Tir',
        'Mordad',
        'Shahrivar',
        'Mehr',
        'Aban',
        'Azar',
        'Dey',
        'Bahman',
        'Esfand',
    ];

    // Indexed by the JavaScript "getDay()" value (0 = Sunday)
    const WEEKDAY_NAMES = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];

    const WEEKDAY_NAMES_SHORT = ['ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش'];

    // Weekday names ordered from Saturday to Friday (the first day of the week in Iran)
    const WEEK_ORDER = [6, 0, 1, 2, 3, 4, 5];

    const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /**
     * Convert a Gregorian date into a Jalali date.
     *
     * @param {Date|String} value Gregorian date value.
     *
     * @return {{year: Number, month: Number, day: Number, weekday: Number}}
     */
    function toJalali(value) {
        const date = toDate(value);

        const daysInMonths = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        const gregorianYear = date.getFullYear();

        const gregorianMonth = date.getMonth() + 1;

        const gregorianDay = date.getDate();

        const adjustedYear = gregorianMonth > 2 ? gregorianYear + 1 : gregorianYear;

        let days =
            355666 +
            365 * gregorianYear +
            Math.floor((adjustedYear + 3) / 4) -
            Math.floor((adjustedYear + 99) / 100) +
            Math.floor((adjustedYear + 399) / 400) +
            gregorianDay +
            daysInMonths[gregorianMonth - 1];

        let jalaliYear = -1595 + 33 * Math.floor(days / 12053);

        days %= 12053;

        jalaliYear += 4 * Math.floor(days / 1461);

        days %= 1461;

        if (days > 365) {
            jalaliYear += Math.floor((days - 1) / 365);

            days = (days - 1) % 365;
        }

        let jalaliMonth;
        let jalaliDay;

        if (days < 186) {
            jalaliMonth = 1 + Math.floor(days / 31);
            jalaliDay = 1 + (days % 31);
        } else {
            jalaliMonth = 7 + Math.floor((days - 186) / 30);
            jalaliDay = 1 + ((days - 186) % 30);
        }

        return {
            year: jalaliYear,
            month: jalaliMonth,
            day: jalaliDay,
            weekday: date.getDay(),
        };
    }

    /**
     * Convert a Jalali date into a Gregorian Date object.
     *
     * @param {Number} year Jalali year.
     * @param {Number} month Jalali month (1-12).
     * @param {Number} day Jalali day (1-31).
     * @param {Number} [hour] Hour of the day.
     * @param {Number} [minute] Minute of the hour.
     * @param {Number} [second] Second of the minute.
     *
     * @return {Date}
     */
    function toGregorian(year, month, day, hour = 0, minute = 0, second = 0) {
        const jalaliYear = year + 1595;

        let days =
            -355668 +
            365 * jalaliYear +
            Math.floor(jalaliYear / 33) * 8 +
            Math.floor(((jalaliYear % 33) + 3) / 4) +
            day +
            (month < 7 ? (month - 1) * 31 : (month - 7) * 30 + 186);

        let gregorianYear = 400 * Math.floor(days / 146097);

        days %= 146097;

        if (days > 36524) {
            gregorianYear += 100 * Math.floor(--days / 36524);

            days %= 36524;

            if (days >= 365) {
                days++;
            }
        }

        gregorianYear += 4 * Math.floor(days / 1461);

        days %= 1461;

        if (days > 365) {
            gregorianYear += Math.floor((days - 1) / 365);

            days = (days - 1) % 365;
        }

        let gregorianDay = days + 1;

        const leap = (gregorianYear % 4 === 0 && gregorianYear % 100 !== 0) || gregorianYear % 400 === 0;

        const monthDays = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        let gregorianMonth = 1;

        while (gregorianMonth <= 12 && gregorianDay > monthDays[gregorianMonth]) {
            gregorianDay -= monthDays[gregorianMonth];
            gregorianMonth++;
        }

        return new Date(gregorianYear, gregorianMonth - 1, gregorianDay, hour, minute, second);
    }

    /**
     * Get the number of days of a Jalali month.
     *
     * @param {Number} year Jalali year.
     * @param {Number} month Jalali month (1-12).
     *
     * @return {Number}
     */
    function monthDays(year, month) {
        if (month <= 6) {
            return 31;
        }

        if (month <= 11) {
            return 30;
        }

        return isLeapYear(year) ? 30 : 29;
    }

    /**
     * Check whether a Jalali year is a leap year.
     *
     * @param {Number} year Jalali year.
     *
     * @return {Boolean}
     */
    function isLeapYear(year) {
        const currentYearStart = toGregorian(year, 1, 1);

        const nextYearStart = toGregorian(year + 1, 1, 1);

        const days = Math.round((nextYearStart - currentYearStart) / (1000 * 60 * 60 * 24));

        return days === 366;
    }

    /**
     * Get the Jalali month name.
     *
     * @param {Number} month Jalali month (1-12).
     * @param {Boolean} [latin] Return the Latin transliteration.
     *
     * @return {String}
     */
    function monthName(month, latin = false) {
        const names = latin ? MONTH_NAMES_LATIN : MONTH_NAMES;

        return names[month - 1] || '';
    }

    /**
     * Get the weekday name of a date.
     *
     * @param {Date|String} value Gregorian date value.
     * @param {Boolean} [short] Return the short name.
     *
     * @return {String}
     */
    function weekdayName(value, short = false) {
        const weekday = toDate(value).getDay();

        return short ? WEEKDAY_NAMES_SHORT[weekday] : WEEKDAY_NAMES[weekday];
    }

    /**
     * Format a date value with a Jalali pattern.
     *
     * Supported tokens: YYYY, YY, MMMM, MMM, MM, M, DD, D, dddd, ddd, HH, mm, ss.
     *
     * @param {Date|String} value Gregorian date value.
     * @param {String} [pattern] Output pattern (defaults to "YYYY/MM/DD").
     * @param {Boolean} [persianDigits] Convert the digits into Persian numerals.
     *
     * @return {String}
     */
    function format(value, pattern = 'YYYY/MM/DD', persianDigits = true) {
        const date = toDate(value);

        const jalali = toJalali(date);

        const pad = (number) => String(number).padStart(2, '0');

        const replacements = {
            YYYY: String(jalali.year),
            YY: String(jalali.year).slice(-2),
            MMMM: monthName(jalali.month),
            MMM: monthName(jalali.month).slice(0, 5),
            MM: pad(jalali.month),
            DD: pad(jalali.day),
            dddd: weekdayName(date),
            ddd: weekdayName(date, true),
            HH: pad(date.getHours()),
            mm: pad(date.getMinutes()),
            ss: pad(date.getSeconds()),
            M: String(jalali.month),
            D: String(jalali.day),
        };

        // A single pass replacement is used, so that the produced values cannot be matched by another token. The
        // longer tokens are matched first (e.g. "dddd" before "ddd").
        const tokens = Object.keys(replacements).sort((a, b) => b.length - a.length);

        const tokenPattern = new RegExp(tokens.join('|'), 'g');

        const output = pattern.replace(tokenPattern, (token) => replacements[token]);

        return persianDigits ? toPersianDigits(output) : output;
    }

    /**
     * Parse a Jalali date string into a Gregorian Date object.
     *
     * @param {String} value Jalali date string (e.g. "1405/07/08 14:30").
     *
     * @return {Date|null} Returns null when the value cannot be parsed.
     */
    function parse(value) {
        if (!value) {
            return null;
        }

        const normalized = toLatinDigits(String(value)).trim();

        const matches = normalized.match(
            /^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})(?:[\sT]+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(am|pm)?)?$/i,
        );

        if (!matches) {
            return null;
        }

        const year = Number(matches[1]);
        const month = Number(matches[2]);
        const day = Number(matches[3]);

        let hour = matches[4] ? Number(matches[4]) : 0;
        const minute = matches[5] ? Number(matches[5]) : 0;
        const second = matches[6] ? Number(matches[6]) : 0;
        const meridiem = matches[7] ? matches[7].toLowerCase() : '';

        if (meridiem === 'pm' && hour < 12) {
            hour += 12;
        } else if (meridiem === 'am' && hour === 12) {
            hour = 0;
        }

        if (month < 1 || month > 12 || day < 1 || day > monthDays(year, month)) {
            return null;
        }

        if (hour > 23 || minute > 59 || second > 59) {
            return null;
        }

        return toGregorian(year, month, day, hour, minute, second);
    }

    /**
     * Convert the ASCII digits of a value into Persian digits.
     *
     * @param {String|Number} value Input value.
     *
     * @return {String}
     */
    function toPersianDigits(value) {
        return String(value).replace(/[0-9]/g, (digit) => PERSIAN_DIGITS[Number(digit)]);
    }

    /**
     * Convert the Persian (or Arabic) digits of a value into ASCII digits.
     *
     * @param {String|Number} value Input value.
     *
     * @return {String}
     */
    function toLatinDigits(value) {
        return String(value)
            .replace(/[۰-۹]/g, (digit) => String(PERSIAN_DIGITS.indexOf(digit)))
            .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));
    }

    /**
     * Check whether the Jalali calendar is enabled for the current installation.
     *
     * @return {Boolean}
     */
    function enabled() {
        return typeof vars === 'function' && vars('calendar_type') === 'jalali';
    }

    /**
     * Check whether the Persian digits are enabled for the current installation.
     *
     * @return {Boolean}
     */
    function persianDigitsEnabled() {
        if (typeof vars === 'function' && vars('persian_digits') !== undefined) {
            return String(vars('persian_digits')) === '1';
        }

        return typeof vars === 'function' && vars('language_code') === 'fa';
    }

    /**
     * Get the first day of the week as a JavaScript weekday number (0 = Sunday).
     *
     * @return {Number}
     */
    function firstDayOfWeek() {
        const setting = typeof vars === 'function' ? vars('first_weekday') : null;

        if (!setting) {
            return 6;
        }

        if (typeof App !== 'undefined' && App.Utils.Date && typeof App.Utils.Date.getWeekdayId === 'function') {
            return App.Utils.Date.getWeekdayId(setting);
        }

        const names = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        const index = names.indexOf(String(setting).toLowerCase());

        return index === -1 ? 6 : index;
    }

    /**
     * Get the weekday names ordered from the first day of the week.
     *
     * @param {Boolean} [short] Return the short names.
     *
     * @return {Array}
     */
    function orderedWeekdayNames(short = true) {
        const first = firstDayOfWeek();

        const ordered = [];

        for (let index = 0; index < 7; index++) {
            const weekday = (first + index) % 7;

            ordered.push(short ? WEEKDAY_NAMES_SHORT[weekday] : WEEKDAY_NAMES[weekday]);
        }

        return ordered;
    }

    /**
     * Get the order of the weekday indexes, starting from the first day of the week.
     *
     * @return {Array}
     */
    function weekOrder() {
        const first = firstDayOfWeek();

        const order = [];

        for (let index = 0; index < 7; index++) {
            order.push((first + index) % 7);
        }

        return order;
    }

    /**
     * Create a new Date object from the provided value.
     *
     * @param {Date|String} value Date value.
     *
     * @return {Date}
     */
    function toDate(value) {
        if (value instanceof Date) {
            return value;
        }

        return moment(value).toDate();
    }

    return {
        MONTH_NAMES,
        WEEK_ORDER,
        toJalali,
        toGregorian,
        monthDays,
        isLeapYear,
        monthName,
        weekdayName,
        format,
        parse,
        toPersianDigits,
        toLatinDigits,
        enabled,
        persianDigitsEnabled,
        firstDayOfWeek,
        orderedWeekdayNames,
        weekOrder,
        toDate,
    };
})();
