/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Customer portal page.
 *
 * The customer logs in with a phone number and the one-time password that was sent over SMS and can then see the
 * upcoming and past appointments and cancel an upcoming appointment.
 */
App.Pages.CustomerPortal = (function () {
    const $login = $('#customer-portal-login');
    const $appointments = $('#customer-portal-appointments');
    const $error = $('#customer-portal-error');
    const $appointmentsError = $('#customer-portal-appointments-error');
    const $phoneForm = $('#customer-portal-phone-form');
    const $codeForm = $('#customer-portal-code-form');
    const $phone = $('#customer-portal-phone');
    const $code = $('#customer-portal-code');
    const $currentPhone = $('#customer-portal-current-phone');
    const $upcoming = $('#customer-portal-upcoming');
    const $past = $('#customer-portal-past');

    let phoneNumber = '';

    /**
     * Show an error message.
     *
     * @param {jQuery} $target
     * @param {String} message
     */
    function showError($target, message) {
        $target.text(message).removeClass('d-none');
    }

    /**
     * Hide the error message.
     *
     * @param {jQuery} $target
     */
    function hideError($target) {
        $target.addClass('d-none').text('');
    }

    /**
     * Get the error message of a failed request.
     *
     * @param {Object} jqXHR
     *
     * @return {String}
     */
    function errorMessage(jqXHR) {
        const response = jqXHR && jqXHR.responseJSON;

        return (response && response.message) || lang('customer_portal_error');
    }

    /**
     * Translate an appointment status.
     *
     * @param {String} status
     *
     * @return {String}
     */
    function statusLabel(status) {
        const key = 'customer_portal_status_' + String(status || '').toLowerCase().replace(/-/g, '_');

        return lang(key);
    }

    /**
     * Format the date and time of an appointment.
     *
     * @param {Object} appointment
     *
     * @return {String}
     */
    function formatDateTime(appointment) {
        return (
            App.Utils.Date.format(appointment.start_datetime, vars('date_format'), vars('time_format'), true) +
            ' - ' +
            App.Utils.Date.format(appointment.end_datetime, vars('date_format'), vars('time_format'), true)
        );
    }

    /**
     * Render an appointment into the provided container.
     *
     * @param {jQuery} $container
     * @param {Object} appointment
     */
    function renderAppointment($container, appointment) {
        const $template = $($('#customer-portal-appointment-template').html().trim());

        $template.find('.customer-portal-appointment-datetime').text(formatDateTime(appointment));

        $template
            .find('.customer-portal-appointment-service')
            .text(lang('customer_portal_service') + ': ' + appointment.service_name);

        $template
            .find('.customer-portal-appointment-provider')
            .text(lang('customer_portal_provider') + ': ' + appointment.provider_name);

        $template.find('.customer-portal-appointment-status').text(statusLabel(appointment.status));

        if (appointment.can_cancel) {
            $template.find('.customer-portal-appointment-cancel').on('click', () => {
                cancelAppointment(appointment.id);
            });
        } else {
            $template.find('.customer-portal-appointment-cancel').remove();
        }

        $container.append($template);
    }

    /**
     * Load and display the appointments of the logged in customer.
     */
    function loadAppointments() {
        hideError($appointmentsError);

        $upcoming.html($('<div/>', { class: 'text-muted small', text: lang('customer_portal_loading') }));
        $past.html($('<div/>', { class: 'text-muted small', text: lang('customer_portal_loading') }));

        App.Http.CustomerPortal.appointments()
            .done((response) => {
                $upcoming.empty();
                $past.empty();

                if (response.upcoming && response.upcoming.length) {
                    response.upcoming.forEach((appointment) => renderAppointment($upcoming, appointment));
                } else {
                    $upcoming.append(
                        $('<div/>', { class: 'text-muted small', text: lang('customer_portal_no_appointments') }),
                    );
                }

                if (response.past && response.past.length) {
                    response.past.forEach((appointment) => renderAppointment($past, appointment));
                } else {
                    $past.append(
                        $('<div/>', { class: 'text-muted small', text: lang('customer_portal_no_appointments') }),
                    );
                }

                $currentPhone.text(response.phone_number);

                $login.addClass('d-none');
                $appointments.removeClass('d-none');
            })
            .fail((jqXHR) => {
                if (jqXHR.status === 401 || jqXHR.status === 403) {
                    showLogin();

                    return;
                }

                showError($appointmentsError, errorMessage(jqXHR));
            });
    }

    /**
     * Cancel an appointment after the customer confirmed it.
     *
     * @param {Number} appointmentId
     */
    function cancelAppointment(appointmentId) {
        if (!window.confirm(lang('customer_portal_cancel_confirm'))) {
            return;
        }

        App.Http.CustomerPortal.cancelAppointment(appointmentId)
            .done(() => {
                App.Utils.Message.show(lang('customer_portal_my_appointments'), lang('customer_portal_appointment_cancelled'));

                loadAppointments();
            })
            .fail((jqXHR) => {
                showError($appointmentsError, errorMessage(jqXHR));
            });
    }

    /**
     * Show the phone number step.
     */
    function showLogin() {
        $appointments.addClass('d-none');
        $login.removeClass('d-none');
        $codeForm.addClass('d-none');
        $phoneForm.removeClass('d-none');
        $code.val('');
    }

    /**
     * Request a one-time password.
     */
    function requestOtp() {
        hideError($error);

        phoneNumber = String($phone.val() || '').trim();

        if (!phoneNumber) {
            showError($error, lang('customer_portal_invalid_phone'));

            return;
        }

        App.Http.CustomerPortal.requestOtp(phoneNumber)
            .done((response) => {
                App.Utils.Message.show(
                    lang('customer_portal_login_heading'),
                    response.message || lang('customer_portal_code_sent'),
                );

                $phoneForm.addClass('d-none');
                $codeForm.removeClass('d-none');
                $code.trigger('focus');
            })
            .fail((jqXHR) => {
                showError($error, errorMessage(jqXHR));
            });
    }

    /**
     * Verify the one-time password.
     */
    function verifyOtp() {
        hideError($error);

        const code = String($code.val() || '').trim();

        if (!code) {
            showError($error, lang('customer_portal_invalid_code'));

            return;
        }

        App.Http.CustomerPortal.verifyOtp(phoneNumber, code)
            .done(() => {
                loadAppointments();
            })
            .fail((jqXHR) => {
                showError($error, errorMessage(jqXHR));
            });
    }

    /**
     * End the portal session.
     */
    function logout() {
        App.Http.CustomerPortal.logout().always(() => {
            showLogin();
        });
    }

    /**
     * Add the page event listeners.
     */
    function addEventListeners() {
        $phoneForm.on('submit', (event) => {
            event.preventDefault();

            requestOtp();
        });

        $codeForm.on('submit', (event) => {
            event.preventDefault();

            verifyOtp();
        });

        $('#customer-portal-resend-code').on('click', () => {
            requestOtp();
        });

        $('#customer-portal-change-number').on('click', () => {
            showLogin();
        });

        $('#customer-portal-logout').on('click', () => {
            logout();
        });
    }

    /**
     * Initialize the page.
     */
    function initialize() {
        if (!$login.length) {
            return;
        }

        addEventListeners();

        if (vars('customer')) {
            loadAppointments();
        }
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        requestOtp: requestOtp,
        verifyOtp: verifyOtp,
        loadAppointments: loadAppointments,
        cancelAppointment: cancelAppointment,
        logout: logout,
        showLogin: showLogin,
        initialize: initialize,
    };
})();
