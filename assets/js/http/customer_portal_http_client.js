/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Customer portal HTTP client.
 *
 * This module implements the HTTP requests of the customer portal (one-time password login, appointment list and
 * appointment cancellation).
 */
App.Http.CustomerPortal = (function () {
    /**
     * Request a one-time password for a phone number.
     *
     * @param {String} phoneNumber
     *
     * @return {Object}
     */
    function requestOtp(phoneNumber) {
        const url = App.Utils.Url.siteUrl('customer/request_otp');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
            phone_number: phoneNumber,
        });
    }

    /**
     * Verify a one-time password and start the portal session.
     *
     * @param {String} phoneNumber
     * @param {String} code
     *
     * @return {Object}
     */
    function verifyOtp(phoneNumber, code) {
        const url = App.Utils.Url.siteUrl('customer/verify_otp');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
            phone_number: phoneNumber,
            code: code,
        });
    }

    /**
     * Get the appointments of the logged in customer.
     *
     * @return {Object}
     */
    function appointments() {
        const url = App.Utils.Url.siteUrl('customer/appointments');

        return $.get(url);
    }

    /**
     * Cancel an appointment of the logged in customer.
     *
     * @param {Number} appointmentId
     *
     * @return {Object}
     */
    function cancelAppointment(appointmentId) {
        const url = App.Utils.Url.siteUrl('customer/cancel_appointment');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
            appointment_id: appointmentId,
        });
    }

    /**
     * End the portal session.
     *
     * @return {Object}
     */
    function logout() {
        const url = App.Utils.Url.siteUrl('customer/logout');

        return $.post(url, {
            csrf_token: vars('csrf_token'),
        });
    }

    return {
        requestOtp: requestOtp,
        verifyOtp: verifyOtp,
        appointments: appointments,
        cancelAppointment: cancelAppointment,
        logout: logout,
    };
})();
