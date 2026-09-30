<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Iranian official holidays data used by the "import official holidays" action.
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Iranian official holidays.
 *
 * The "fixed" holidays are based on the Solar Hijri (Jalali) calendar and are therefore the same every year. They are
 * imported as recurring holidays, which means that only the Jalali month/day values are kept for the upcoming years.
 *
 * The "lunar" holidays are based on the Hijri (lunar) calendar and therefore move about eleven days earlier every
 * year. Their Gregorian dates cannot be calculated with a simple formula (the official dates depend on the moon
 * sighting), so they are listed here for the Gregorian years 2025 - 2027. The list was cross-checked against the
 * official Iranian calendar lists and international holiday databases; a difference of one day is possible for the
 * lunar holidays, therefore the administrator can always edit or remove a holiday from the admin panel.
 *
 * When a new year is needed, append the matching Gregorian year to the "lunar" array (the key is the Gregorian date
 * in the Y-m-d format and the value is the holiday title, or an array of titles when more than one occasion falls on
 * the same date).
 */

return [
    'fixed' => [
        [
            'title' => 'نوروز',
            'description' => 'جشن سال نو و آغاز سال هجری شمسی',
            'jalali_month' => 1,
            'jalali_day' => 1,
        ],
        [
            'title' => 'عید نوروز',
            'description' => 'تعطیلات نوروزی',
            'jalali_month' => 1,
            'jalali_day' => 2,
        ],
        [
            'title' => 'عید نوروز',
            'description' => 'تعطیلات نوروزی',
            'jalali_month' => 1,
            'jalali_day' => 3,
        ],
        [
            'title' => 'عید نوروز',
            'description' => 'تعطیلات نوروزی',
            'jalali_month' => 1,
            'jalali_day' => 4,
        ],
        [
            'title' => 'روز جمهوری اسلامی ایران',
            'description' => 'دوازدهم فروردین',
            'jalali_month' => 1,
            'jalali_day' => 12,
        ],
        [
            'title' => 'روز طبیعت',
            'description' => 'سیزده بدر',
            'jalali_month' => 1,
            'jalali_day' => 13,
        ],
        [
            'title' => 'رحلت حضرت امام خمینی (ره)',
            'description' => 'چهاردهم خرداد',
            'jalali_month' => 3,
            'jalali_day' => 14,
        ],
        [
            'title' => 'قیام پانزده خرداد',
            'description' => 'پانزدهم خرداد',
            'jalali_month' => 3,
            'jalali_day' => 15,
        ],
        [
            'title' => 'پیروزی انقلاب اسلامی ایران',
            'description' => 'بیست و دوم بهمن',
            'jalali_month' => 11,
            'jalali_day' => 22,
        ],
        [
            'title' => 'روز ملی شدن صنعت نفت ایران',
            'description' => 'بیست و نهم اسفند',
            'jalali_month' => 12,
            'jalali_day' => 29,
        ],
    ],

    'lunar' => [
        // Gregorian year 2025 (Jalali 1403 - 1404).
        '2025-03-21' => 'شهادت حضرت علی (ع)',
        '2025-03-31' => 'عید سعید فطر',
        '2025-04-01' => 'تعطیل عید سعید فطر',
        '2025-04-24' => 'شهادت امام جعفر صادق (ع)',
        '2025-06-06' => 'عید سعید قربان',
        '2025-06-14' => 'عید سعید غدیر خم',
        '2025-07-05' => 'تاسوعای حسینی',
        '2025-07-06' => 'عاشورای حسینی',
        '2025-08-14' => 'اربعین حسینی',
        '2025-08-18' => 'مبعث رسول اکرم (ص)',

        // Gregorian year 2026 (Jalali 1404 - 1405).
        '2026-01-03' => 'ولادت حضرت امام علی (ع)',
        '2026-01-17' => 'مبعث رسول اکرم (ص)',
        '2026-02-04' => 'ولادت حضرت امام مهدی (عج) و جشن نیمه شعبان',
        '2026-03-11' => 'شهادت حضرت علی (ع)',
        '2026-03-21' => 'عید سعید فطر',
        '2026-03-22' => 'تعطیل عید سعید فطر',
        '2026-04-14' => 'شهادت امام جعفر صادق (ع)',
        '2026-05-27' => 'عید سعید قربان',
        '2026-06-04' => 'عید سعید غدیر خم',
        '2026-06-24' => 'تاسوعای حسینی',
        '2026-06-25' => 'عاشورای حسینی',
        '2026-08-04' => 'اربعین حسینی',
        '2026-08-12' => 'رحلت رسول اکرم (ص) و شهادت امام حسن مجتبی (ع)',
        '2026-08-13' => 'شهادت امام رضا (ع)',
        '2026-08-21' => 'شهادت امام حسن عسکری (ع)',
        '2026-08-30' => 'ولادت رسول اکرم (ص) و امام جعفر صادق (ع)',
        '2026-11-13' => 'شهادت حضرت فاطمه زهرا (س)',

        // Gregorian year 2027 (Jalali 1405 - 1406).
        '2027-01-06' => 'مبعث رسول اکرم (ص)',
        '2027-01-24' => 'ولادت حضرت امام مهدی (عج) و جشن نیمه شعبان',
        '2027-02-28' => 'شهادت حضرت علی (ع)',
        '2027-03-10' => 'عید سعید فطر',
        '2027-03-11' => 'تعطیل عید سعید فطر',
        '2027-04-03' => 'شهادت امام جعفر صادق (ع)',
        '2027-05-17' => 'عید سعید قربان',
        '2027-05-25' => 'عید سعید غدیر خم',
        '2027-06-14' => 'تاسوعای حسینی',
        '2027-06-15' => 'عاشورای حسینی',
        '2027-07-25' => 'اربعین حسینی',
        '2027-08-02' => 'رحلت رسول اکرم (ص) و شهادت امام حسن مجتبی (ع)',
        '2027-08-03' => 'شهادت امام رضا (ع)',
        '2027-08-11' => 'شهادت امام حسن عسکری (ع)',
        '2027-08-20' => 'ولادت رسول اکرم (ص) و امام جعفر صادق (ع)',
        '2027-11-03' => 'شهادت حضرت فاطمه زهرا (س)',
        '2027-12-12' => 'ولادت حضرت امام علی (ع)',
        '2027-12-26' => 'مبعث رسول اکرم (ص)',
    ],
];
