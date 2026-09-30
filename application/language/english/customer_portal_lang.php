<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * English translations of the customer portal and of the SMS/OTP module.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

// Portal

$lang['customer_portal_page_title'] = 'My appointments';
$lang['customer_portal_disabled'] = 'The customer portal is currently disabled.';
$lang['customer_portal_login_heading'] = 'Customer portal login';
$lang['customer_portal_login_hint'] = 'Enter your phone number to receive a verification code and see your appointments.';
$lang['customer_portal_phone_number'] = 'Phone number';
$lang['customer_portal_send_code'] = 'Send verification code';
$lang['customer_portal_code'] = 'Verification code';
$lang['customer_portal_code_hint'] = 'Enter the {length} digit code that was sent to you (valid for {minutes} minutes).';
$lang['customer_portal_verify'] = 'Log in';
$lang['customer_portal_change_number'] = 'Change phone number';
$lang['customer_portal_resend_code'] = 'Send the code again';
$lang['customer_portal_my_appointments'] = 'My appointments';
$lang['customer_portal_upcoming'] = 'Upcoming appointments';
$lang['customer_portal_past'] = 'Past appointments';
$lang['customer_portal_no_appointments'] = 'There are no appointments for this phone number yet.';
$lang['customer_portal_book_new'] = 'Book a new appointment';
$lang['customer_portal_cancel'] = 'Cancel appointment';
$lang['customer_portal_cancel_confirm'] = 'Are you sure you want to cancel this appointment?';
$lang['customer_portal_logout'] = 'Log out';
$lang['customer_portal_service'] = 'Service';
$lang['customer_portal_provider'] = 'Provider';
$lang['customer_portal_status'] = 'Status';
$lang['customer_portal_duration'] = 'Duration';
$lang['customer_portal_minutes'] = 'minutes';
$lang['customer_portal_loading'] = 'Loading…';
$lang['customer_portal_error'] = 'Something went wrong. Please try again.';
$lang['customer_portal_welcome'] = 'Welcome';

// Messages

$lang['customer_portal_code_sent'] = 'The verification code has been sent.';
$lang['customer_portal_invalid_phone'] = 'The phone number is not valid.';
$lang['customer_portal_invalid_code'] = 'The verification code is not correct.';
$lang['customer_portal_code_not_found'] = 'There is no code for this phone number. Please request a new one.';
$lang['customer_portal_code_expired'] = 'The code has expired. Please request a new one.';
$lang['customer_portal_too_many_attempts'] = 'Too many attempts. Please request a new code.';
$lang['customer_portal_otp_disabled'] = 'The one-time password login is disabled.';
$lang['customer_portal_rate_limited'] = 'Too many requests. Please try again in a few minutes.';
$lang['customer_portal_login_required'] = 'Please log in first.';
$lang['customer_portal_appointment_not_found'] = 'This appointment does not belong to you.';
$lang['customer_portal_cancel_too_late'] = 'This appointment already started and cannot be cancelled.';
$lang['customer_portal_appointment_cancelled'] = 'Your appointment has been cancelled.';
$lang['customer_portal_otp_message'] = 'Your {company} login code: {code}';

// Appointment statuses

$lang['customer_portal_status_draft'] = 'Draft';
$lang['customer_portal_status_pending'] = 'Pending payment';
$lang['customer_portal_status_booked'] = 'Booked';
$lang['customer_portal_status_confirmed'] = 'Confirmed';
$lang['customer_portal_status_rescheduled'] = 'Rescheduled';
$lang['customer_portal_status_cancelled'] = 'Cancelled';
$lang['customer_portal_status_completed'] = 'Completed';
$lang['customer_portal_status_no_show'] = 'No-show';
