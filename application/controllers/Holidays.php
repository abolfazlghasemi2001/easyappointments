<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Holidays controller.
 *
 * Allows the administrators to manage the days that the business is closed (Iranian official holidays, national
 * holidays, religious occasions etc.) and to import the official Iranian holidays of a Jalali year.
 *
 * @package Controllers
 */
class Holidays extends EA_Controller
{
    /**
     * Holidays constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('holidays_model');
        $this->load->model('roles_model');

        $this->load->library('accounts');
        $this->load->library('timezones');
    }

    /**
     * Render the backend holidays page.
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('holidays')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_SYSTEM_SETTINGS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');

        script_vars([
            'user_id' => $user_id,
            'role_slug' => $role_slug,
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
            'first_weekday' => setting('first_weekday'),
        ]);

        html_vars([
            'page_title' => lang('holidays'),
            'active_menu' => PRIV_SYSTEM_SETTINGS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'timezones' => $this->timezones->to_array(),
            'privileges' => $this->roles_model->get_permissions_by_slug($role_slug),
        ]);

        $this->load->view('pages/holidays');
    }

    /**
     * Filter holidays by the provided keyword.
     */
    public function search(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $keyword = request('keyword', '');

            $holidays = $this->holidays_model->search($keyword, request('length'), request('offset'));

            json_response($holidays);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Store a new holiday.
     */
    public function store(): void
    {
        try {
            method('post');

            if (cannot('add', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $holiday = request('holiday');

            $holiday_id = $this->holidays_model->save($holiday);

            $holiday = $this->holidays_model->find($holiday_id);

            json_response([
                'success' => true,
                'id' => $holiday_id,
                'holiday' => $holiday,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Find a holiday.
     */
    public function find(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $holiday_id = request('holiday_id');

            $holiday = $this->holidays_model->find($holiday_id);

            json_response($holiday);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Update an existing holiday.
     */
    public function update(): void
    {
        try {
            method('post');

            if (cannot('edit', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $holiday = request('holiday');

            $holiday_id = $this->holidays_model->save($holiday);

            $holiday = $this->holidays_model->find($holiday_id);

            json_response([
                'success' => true,
                'id' => $holiday_id,
                'holiday' => $holiday,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Remove an existing holiday.
     */
    public function destroy(): void
    {
        try {
            method('post');

            if (cannot('delete', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            $holiday_id = request('holiday_id');

            $holiday = $this->holidays_model->find($holiday_id);

            $this->holidays_model->delete($holiday_id);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Import the official Iranian holidays of the provided Jalali year.
     */
    public function import(): void
    {
        try {
            method('post');

            if (cannot('add', PRIV_SYSTEM_SETTINGS)) {
                abort(403, 'Forbidden');
            }

            check('jalali_year', 'numeric');

            $jalali_year = (int) request('jalali_year');

            if ($jalali_year < 1300 || $jalali_year > 1500) {
                throw new InvalidArgumentException('Invalid Jalali year value provided.');
            }

            $imported = $this->holidays_model->import_official_holidays($jalali_year);

            json_response([
                'success' => true,
                'imported' => $imported,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Get the holidays of a Gregorian date range (used for highlighting the calendar and the booking page).
     */
    public function feed(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_SYSTEM_SETTINGS) && !session('user_id')) {
                abort(403, 'Forbidden');
            }

            $start_date = request('start_date', date('Y-m-01'));
            $end_date = request('end_date', date('Y-m-t'));

            $holidays = $this->holidays_model->get_range($start_date, $end_date);

            $response = [];

            foreach ($holidays as $date => $items) {
                $response[$date] = array_map(
                    static fn($item) => [
                        'id' => (int) $item['id'],
                        'title' => $item['title'],
                        'description' => $item['description'] ?? null,
                        'is_recurring' => (bool) ($item['is_recurring'] ?? false),
                    ],
                    $items,
                );
            }

            json_response($response);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}
