<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Persian translations of the customer portal and of the SMS/OTP module.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

// Portal

$lang['customer_portal_page_title'] = 'نوبت‌های من';
$lang['customer_portal_disabled'] = 'پنل مشتریان در حال حاضر غیرفعال است.';
$lang['customer_portal_login_heading'] = 'ورود به پنل مشتریان';
$lang['customer_portal_login_hint'] = 'برای مشاهدهٔ نوبت‌ها، شمارهٔ موبایل خود را وارد کنید و کد تأیید را دریافت کنید.';
$lang['customer_portal_phone_number'] = 'شمارهٔ موبایل';
$lang['customer_portal_send_code'] = 'ارسال کد تأیید';
$lang['customer_portal_code'] = 'کد تأیید';
$lang['customer_portal_code_hint'] = 'کد {length} رقمی پیامک‌شده را وارد کنید (تا {minutes} دقیقه معتبر است).';
$lang['customer_portal_verify'] = 'ورود';
$lang['customer_portal_change_number'] = 'تغییر شمارهٔ موبایل';
$lang['customer_portal_resend_code'] = 'ارسال دوبارهٔ کد';
$lang['customer_portal_my_appointments'] = 'نوبت‌های من';
$lang['customer_portal_upcoming'] = 'نوبت‌های پیش‌رو';
$lang['customer_portal_past'] = 'نوبت‌های گذشته';
$lang['customer_portal_no_appointments'] = 'هنوز نوبتی برای این شماره ثبت نشده است.';
$lang['customer_portal_book_new'] = 'رزرو نوبت جدید';
$lang['customer_portal_cancel'] = 'لغو نوبت';
$lang['customer_portal_cancel_confirm'] = 'از لغو این نوبت مطمئن هستید؟';
$lang['customer_portal_logout'] = 'خروج';
$lang['customer_portal_service'] = 'خدمت';
$lang['customer_portal_provider'] = 'آرایشگر';
$lang['customer_portal_status'] = 'وضعیت';
$lang['customer_portal_duration'] = 'مدت';
$lang['customer_portal_minutes'] = 'دقیقه';
$lang['customer_portal_loading'] = 'در حال بارگذاری…';
$lang['customer_portal_error'] = 'خطایی رخ داد. لطفاً دوباره تلاش کنید.';
$lang['customer_portal_welcome'] = 'خوش آمدید';

// Messages

$lang['customer_portal_code_sent'] = 'کد تأیید پیامک شد.';
$lang['customer_portal_invalid_phone'] = 'شمارهٔ موبایل معتبر نیست.';
$lang['customer_portal_invalid_code'] = 'کد وارد‌شده درست نیست.';
$lang['customer_portal_code_not_found'] = 'کدی برای این شماره ثبت نشده است. لطفاً دوباره درخواست کنید.';
$lang['customer_portal_code_expired'] = 'اعتبار کد به پایان رسیده است. لطفاً کد جدید دریافت کنید.';
$lang['customer_portal_too_many_attempts'] = 'تعداد تلاش‌ها بیش از حد مجاز است. لطفاً کد جدید دریافت کنید.';
$lang['customer_portal_otp_disabled'] = 'ورود با کد یک‌بارمصرف غیرفعال است.';
$lang['customer_portal_rate_limited'] = 'درخواست‌های شما بیش از حد مجاز است. لطفاً چند دقیقه بعد تلاش کنید.';
$lang['customer_portal_login_required'] = 'برای دیدن نوبت‌ها ابتدا وارد شوید.';
$lang['customer_portal_appointment_not_found'] = 'این نوبت برای شما ثبت نشده است.';
$lang['customer_portal_cancel_too_late'] = 'زمان این نوبت گذشته است و قابل لغو نیست.';
$lang['customer_portal_appointment_cancelled'] = 'نوبت شما لغو شد.';
$lang['customer_portal_otp_message'] = 'کد ورود شما به {company}: {code}';

// Appointment statuses

$lang['customer_portal_status_draft'] = 'پیش‌نویس';
$lang['customer_portal_status_pending'] = 'در انتظار پرداخت';
$lang['customer_portal_status_booked'] = 'رزرو شده';
$lang['customer_portal_status_confirmed'] = 'تأیید شده';
$lang['customer_portal_status_rescheduled'] = 'زمان‌بندی مجدد';
$lang['customer_portal_status_cancelled'] = 'لغو شده';
$lang['customer_portal_status_completed'] = 'انجام شده';
$lang['customer_portal_status_no_show'] = 'عدم حضور';
