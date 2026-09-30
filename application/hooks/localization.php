<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Persian / RTL localization hook.
 *
 * The hook extends the frontend configuration of the application without modifying the core files. It exposes the
 * localization settings (calendar type, Persian digits, display timezone, text direction, first day of the week) to
 * the JavaScript code of every page, through the "window.vars" object.
 *
 * The values of the hook are applied right after the controller is constructed, so any value that a controller or a
 * page sets later on (e.g. the booking page) takes precedence.
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Expose the localization settings to the JavaScript code of the application.
 */
function load_localization_script_vars(): void
{
    if (!function_exists('script_vars') || !function_exists('setting')) {
        return;
    }

    try {
        /** @var EA_Controller|CI_Controller $CI */
        $CI = &get_instance();

        if (!$CI || !$CI->db->table_exists('settings')) {
            return; // The application has not been installed yet.
        }

        $CI->load->helper('localization');

        script_vars([
            'calendar_type' => safe_setting('calendar_type', 'gregorian'),
            'persian_digits' => (string) safe_setting('persian_digits', '0'),
            'display_timezone' => app_timezone(),
            'text_direction' => app_text_direction(),
            'is_rtl' => is_rtl(),
        ]);
    } catch (Throwable $exception) {
        log_message('error', 'Could not load the localization script vars: ' . $exception->getMessage());
    }
}
