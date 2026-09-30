<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Customer portal.
 *
 * The customer logs in with a phone number and an SMS one-time password (see
 * Otp_service) and can then see the upcoming and past appointments and cancel an
 * upcoming appointment. This is a fork addition, the core controllers are not
 * touched.
 *
 * Routes:
 *
 *   GET  /index.php/customer/portal
 *   POST /index.php/customer/request_otp
 *   POST /index.php/customer/verify_otp
 *   GET  /index.php/customer/appointments
 *   POST /index.php/customer/cancel_appointment
 *   POST /index.php/customer/logout
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Customer controller (customer portal).
 */
class Customer extends EA_Controller
{
    /**
     * Session key of the portal login.
     */
    public const SESSION_KEY = 'customer_portal';

    /**
     * Customer constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('services_model');
        $this->load->model('providers_model');
        $this->load->model('settings_model');
        $this->load->model('customers_model');

        $this->load->library('otp_service');
        $this->load->library('appointment_status');
        $this->load->library('synchronization');
        $this->load->library('notifications');

        $this->load_language_file();
    }

    /**
     * Render the customer portal.
     *
     * @throws Exception
     */
    public function portal(): void
    {
        method('get');

        if (!is_app_installed()) {
            redirect('installation');

            return;
        }

        if (!filter_var(setting('portal_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            show_error(lang('customer_portal_disabled'), 403);

            return;
        }

        $customer = $this->current_customer();

        html_vars([
            'page_title' => lang('customer_portal_page_title'),
            'company_name' => setting('company_name'),
            'company_logo' => setting('company_logo'),
            'company_color' => setting('company_color'),
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
            'timezone' => setting('default_timezone'),
            'portal_enabled' => setting('portal_enabled', '1'),
            'otp_enabled' => (string) $this->otp_service->enabled(),
            'otp_code_length' => $this->otp_service->code_length(),
            'otp_ttl_seconds' => $this->otp_service->ttl_seconds(),
            'display_phone_number' => setting('display_phone_number'),
            'require_phone_number' => setting('require_phone_number'),
            'display_login_button' => setting('display_login_button'),
            'legal_notice_url' => setting('legal_notice_url'),
            'imprint_url' => setting('imprint_url'),
            'customer' => $customer,
            'csrf_token' => $this->security->get_csrf_hash(),
            'theme' => setting('theme'),
        ]);

        // Values that the page script reads through vars().
        script_vars([
            'customer' => $customer,
            'date_format' => setting('date_format', 'YMD'),
            'time_format' => setting('time_format', 'regular'),
            'display_timezone' => setting('default_timezone'),
            'otp_code_length' => $this->otp_service->code_length(),
            'otp_ttl_seconds' => $this->otp_service->ttl_seconds(),
            'portal_enabled' => true,
        ]);

        $this->load->view('pages/customer_portal');
    }

    /**
     * Send a one-time password to the provided phone number.
     */
    public function request_otp(): void
    {
        try {
            method('post');

            check('phone_number', 'string');

            $phone_number = (string) request('phone_number');

            $result = $this->otp_service->request_code(
                $phone_number,
                Otp_service::PURPOSE_PORTAL_LOGIN,
                $this->input->ip_address(),
            );

            json_response([
                'success' => true,
                'phone_number' => $result['phone_number'],
                'expires_in' => $result['expires_in'],
                'message' => lang('customer_portal_code_sent'),
            ]);
        } catch (Otp_exception $exception) {
            json_response([
                'success' => false,
                'reason' => $exception->reason(),
                'retry_after' => $exception->retry_after(),
                'message' => $exception->getMessage(),
            ], $exception->reason() === 'rate_limited' ? 429 : 400);
        } catch (Throwable $exception) {
            json_exception($exception);
        }
    }

    /**
     * Verify a one-time password and start the portal session.
     */
    public function verify_otp(): void
    {
        try {
            method('post');

            check('phone_number', 'string');
            check('code', 'string');

            $phone_number = (string) request('phone_number');

            $this->otp_service->verify_code($phone_number, (string) request('code'), Otp_service::PURPOSE_PORTAL_LOGIN);

            $customer = $this->find_customer_by_phone($phone_number);

            $this->session->set_userdata(self::SESSION_KEY, [
                'phone_number' => normalize_iran_phone_number($phone_number),
                'customer_id' => $customer['id'] ?? null,
                'login_datetime' => date('Y-m-d H:i:s'),
                'ip_address' => $this->input->ip_address(),
            ]);

            $this->session->sess_regenerate(true);

            json_response([
                'success' => true,
                'customer' => $customer ? $this->filter_customer($customer) : null,
            ]);
        } catch (Otp_exception $exception) {
            json_response([
                'success' => false,
                'reason' => $exception->reason(),
                'retry_after' => $exception->retry_after(),
                'message' => $exception->getMessage(),
            ], $exception->reason() === 'rate_limited' ? 429 : 400);
        } catch (Throwable $exception) {
            json_exception($exception);
        }
    }

    /**
     * Get the appointments of the logged in customer.
     */
    public function appointments(): void
    {
        try {
            method('get');

            $login = $this->require_login();

            $customer_ids = $this->customer_ids_for_phone($login['phone_number']);

            $appointments = [];

            if ($customer_ids) {
                $records = $this->db->from('appointments')
                    ->where('is_unavailability', false)
                    ->where_in('id_users_customer', $customer_ids)
                    ->order_by('start_datetime', 'DESC')
                    ->get()
                    ->result_array();

                foreach ($records as $record) {
                    $appointments[] = $this->present_appointment($record);
                }
            }

            $now = date('Y-m-d H:i:s');

            $upcoming = array_values(array_filter(
                $appointments,
                fn(array $appointment): bool => $appointment['end_datetime'] >= $now
                    && !in_array($appointment['status'], ['Cancelled', 'Completed', 'No-show'], true),
            ));

            $past = array_values(array_filter(
                $appointments,
                fn(array $appointment): bool => !in_array($appointment['id'], array_column($upcoming, 'id'), true),
            ));

            json_response([
                'success' => true,
                'phone_number' => $login['phone_number'],
                'upcoming' => $upcoming,
                'past' => $past,
            ]);
        } catch (Throwable $exception) {
            json_exception($exception);
        }
    }

    /**
     * Cancel an appointment of the logged in customer.
     */
    public function cancel_appointment(): void
    {
        try {
            method('post');

            check('appointment_id', 'numeric');

            $login = $this->require_login();

            $appointment_id = (int) request('appointment_id');

            $appointment = $this->appointments_model->find($appointment_id);

            if (!in_array((int) $appointment['id_users_customer'], $this->customer_ids_for_phone($login['phone_number']), true)) {
                throw new RuntimeException(lang('customer_portal_appointment_not_found'));
            }

            if ($appointment['start_datetime'] <= date('Y-m-d H:i:s')) {
                throw new RuntimeException(lang('customer_portal_cancel_too_late'));
            }

            $this->appointment_status->assert_transition($appointment['status'] ?? null, 'Cancelled');

            $this->db->update(
                'appointments',
                [
                    'status' => 'Cancelled',
                    'update_datetime' => date('Y-m-d H:i:s'),
                ],
                ['id' => $appointment_id],
            );

            $appointment['status'] = 'Cancelled';

            $service = $this->services_model->find($appointment['id_services']);

            $provider = $this->providers_model->find($appointment['id_users_provider']);

            $customer = $this->customers_model->find($appointment['id_users_customer']);

            $settings = [
                'company_name' => setting('company_name'),
                'company_link' => setting('company_link'),
                'company_email' => setting('company_email'),
                'company_color' => setting('company_color'),
                'date_format' => setting('date_format'),
                'time_format' => setting('time_format'),
            ];

            try {
                $this->synchronization->sync_appointment_deleted($appointment, $provider);
            } catch (Throwable $exception) {
                log_message('error', 'Could not remove the cancelled appointment from the external calendars: '
                    . $exception->getMessage());
            }

            try {
                $this->notifications->notify_appointment_deleted($appointment, $service, $provider, $customer, $settings);
            } catch (Throwable $exception) {
                log_message('error', 'Could not send the cancellation notifications: ' . $exception->getMessage());
            }

            json_response([
                'success' => true,
                'message' => lang('customer_portal_appointment_cancelled'),
            ]);
        } catch (Throwable $exception) {
            json_exception($exception);
        }
    }

    /**
     * End the portal session.
     */
    public function logout(): void
    {
        $this->session->unset_userdata(self::SESSION_KEY);

        json_response(['success' => true]);
    }

    /**
     * Get the login information of the current session.
     *
     * @return array|null
     */
    protected function current_login(): ?array
    {
        $login = $this->session->userdata(self::SESSION_KEY);

        if (!$login || empty($login['phone_number'])) {
            return null;
        }

        return $login;
    }

    /**
     * Get the login information or fail with HTTP 401.
     *
     * @return array
     *
     * @throws RuntimeException
     */
    protected function require_login(): array
    {
        $login = $this->current_login();

        if (!$login) {
            throw new RuntimeException(lang('customer_portal_login_required'));
        }

        return $login;
    }

    /**
     * Get the customer record of the current session (if there is one).
     *
     * @return array|null
     */
    protected function current_customer(): ?array
    {
        $login = $this->current_login();

        if (!$login) {
            return null;
        }

        $customer = $this->find_customer_by_phone($login['phone_number']);

        return $customer ? $this->filter_customer($customer) : null;
    }

    /**
     * Find the customer record of a phone number.
     *
     * @param string $phone_number Phone number.
     *
     * @return array|null
     */
    protected function find_customer_by_phone(string $phone_number): ?array
    {
        $customer_ids = $this->customer_ids_for_phone($phone_number);

        return $customer_ids ? $this->customers_model->find($customer_ids[0]) : null;
    }

    /**
     * Get the ids of the customer records that use the provided phone number.
     *
     * @param string $phone_number Phone number.
     *
     * @return int[]
     */
    protected function customer_ids_for_phone(string $phone_number): array
    {
        $normalized = normalize_iran_phone_number($phone_number);

        if ($normalized === '') {
            return [];
        }

        $role = $this->db->get_where('roles', ['slug' => DB_SLUG_CUSTOMER])->row_array();

        if (!$role) {
            return [];
        }

        $records = $this->db->select('id')
            ->from('users')
            ->where('id_roles', $role['id'])
            ->group_start()
            ->where('phone_number', $normalized)
            ->or_where('phone_number', '0' . substr($normalized, 1)) // tolerate a stored number without the prefix
            ->or_where('mobile_number', $normalized)
            ->group_end()
            ->get()
            ->result_array();

        return array_map('intval', array_column($records, 'id'));
    }

    /**
     * Keep only the customer fields that the portal may expose.
     *
     * @param array $customer Customer record.
     *
     * @return array
     */
    protected function filter_customer(array $customer): array
    {
        return array_intersect_key($customer, array_flip([
            'id',
            'first_name',
            'last_name',
            'email',
            'phone_number',
            'language',
        ]));
    }

    /**
     * Add the service/provider names and the display dates to an appointment record.
     *
     * @param array $appointment Appointment record.
     *
     * @return array
     */
    protected function present_appointment(array $appointment): array
    {
        $service = $this->db->get_where('services', ['id' => $appointment['id_services']])->row_array();

        $provider = $this->db->get_where('users', ['id' => $appointment['id_users_provider']])->row_array();

        return array_merge(array_intersect_key($appointment, array_flip([
            'id',
            'hash',
            'start_datetime',
            'end_datetime',
            'status',
            'notes',
            'location',
            'id_services',
            'id_users_provider',
        ])), [
            'service_name' => $service['name'] ?? '',
            'service_duration' => (int) ($service['duration'] ?? 0),
            'provider_name' => trim(($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? '')),
            'can_cancel' => $appointment['start_datetime'] > date('Y-m-d H:i:s')
                && !in_array($appointment['status'] ?? '', ['Cancelled', 'Completed', 'No-show'], true),
        ]);
    }

    /**
     * Load the language file of the portal (falls back to Persian and English).
     */
    protected function load_language_file(): void
    {
        $language = session('language') ?: config('language');

        foreach (array_unique([$language, 'persian', 'english']) as $candidate) {
            if (file_exists(APPPATH . 'language/' . $candidate . '/customer_portal_lang.php')) {
                $this->lang->load('customer_portal', $candidate);

                // The common HTML vars were built in the constructor, before these keys were loaded.
                html_vars(['language' => $this->lang->language]);

                return;
            }
        }
    }
}
