<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Holidays model.
 *
 * Handles all the database operations of the holiday resource. Iranian official holidays (and any other date that the
 * business keeps closed) are stored with their Gregorian date. Recurring holidays are additionally stored with the
 * Jalali month/day values, so that they can be repeated every year (e.g. Nowruz on the 1st of Farvardin).
 *
 * @package Models
 */
class Holidays_model extends EA_Model
{
    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'is_recurring' => 'boolean',
        'jalali_month' => 'integer',
        'jalali_day' => 'integer',
    ];

    /**
     * @var array
     */
    protected array $api_resource = [
        'id' => 'id',
        'title' => 'title',
        'date' => 'holiday_date',
        'isRecurring' => 'is_recurring',
        'jalaliMonth' => 'jalali_month',
        'jalaliDay' => 'jalali_day',
        'description' => 'description',
    ];

    /**
     * Save (insert or update) a holiday.
     *
     * @param array $holiday Associative array with the holiday data.
     *
     * @return int Returns the holiday ID.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws Exception
     */
    public function save(array $holiday): int
    {
        $this->validate($holiday);

        $this->sync_jalali_fields($holiday);

        if (empty($holiday['id'])) {
            return $this->insert($holiday);
        }

        return $this->update($holiday);
    }

    /**
     * Validate the holiday data.
     *
     * @param array $holiday Associative array with the holiday data.
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $holiday): void
    {
        if (!empty($holiday['id'])) {
            $count = $this->db->get_where('holidays', ['id' => $holiday['id']])->num_rows();

            if (!$count) {
                throw new InvalidArgumentException('The provided holiday ID was not found in the database.');
            }
        }

        if (empty($holiday['title'])) {
            throw new InvalidArgumentException('The holiday title is required.');
        }

        $date = $holiday['holiday_date'] ?? '';

        if (!is_valid_date_value($date)) {
            throw new InvalidArgumentException('The holiday date is not a valid date value.');
        }
    }

    /**
     * Insert a new holiday into the database.
     *
     * @param array $holiday Associative array with the holiday data.
     *
     * @return int Returns the holiday ID.
     *
     * @throws RuntimeException
     */
    protected function insert(array $holiday): int
    {

        $holiday['create_datetime'] = date('Y-m-d H:i:s');
        $holiday['update_datetime'] = date('Y-m-d H:i:s');

        if (!$this->db->insert('holidays', $holiday)) {
            throw new RuntimeException('Could not insert holiday.');
        }

        return $this->db->insert_id();
    }

    /**
     * Update an existing holiday.
     *
     * @param array $holiday Associative array with the holiday data.
     *
     * @return int Returns the holiday ID.
     *
     * @throws RuntimeException
     */
    protected function update(array $holiday): int
    {

        $holiday['update_datetime'] = date('Y-m-d H:i:s');

        if (!$this->db->update('holidays', $holiday, ['id' => $holiday['id']])) {
            throw new RuntimeException('Could not update holiday.');
        }

        return $holiday['id'];
    }

    /**
     * Remove an existing holiday from the database.
     *
     * @param int $holiday_id Holiday ID.
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function delete(int $holiday_id): void
    {
        $count = $this->db->get_where('holidays', ['id' => $holiday_id])->num_rows();

        if (!$count) {
            throw new InvalidArgumentException('The provided holiday ID was not found in the database.');
        }

        $this->db->delete('holidays', ['id' => $holiday_id]);
    }

    /**
     * Get a specific holiday from the database.
     *
     * @param int $holiday_id The ID of the record to be returned.
     *
     * @return array Returns an array with the holiday data.
     *
     * @throws InvalidArgumentException
     */
    public function find(int $holiday_id): array
    {
        $holiday = $this->db->get_where('holidays', ['id' => $holiday_id])->row_array();

        if (!$holiday) {
            throw new InvalidArgumentException('The provided holiday ID was not found in the database: ' . $holiday_id);
        }

        $this->cast($holiday);

        return $holiday;
    }

    /**
     * Get a specific field value from the database.
     *
     * @param string $field Field name.
     * @param int $holiday_id Record ID.
     *
     * @return string Returns the value of the requested record.
     *
     * @throws InvalidArgumentException
     */
    public function get_value(string $field, int $holiday_id): string
    {
        return $this->find($holiday_id)[$field] ?? '';
    }

    /**
     * Get all holidays from the database.
     *
     * @param array|string|null $where Where conditions.
     * @param int|null $limit Maximum number of records.
     * @param int|null $offset Offset of the records.
     * @param string|null $order_by Order by clause.
     *
     * @return array Returns an array with the holiday records.
     */
    public function get(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $order_by = null,
    ): array {
        if ($where !== null) {
            $this->db->where($where);
        }

        if ($order_by !== null) {
            $this->db->order_by($this->quote_order_by($order_by));
        } else {
            $this->db->order_by('holiday_date ASC');
        }

        $holidays = $this->db->get('holidays', $limit, $offset)->result_array();

        foreach ($holidays as &$holiday) {
            $this->cast($holiday);
        }

        return $holidays;
    }

    /**
     * Search holidays with a keyword.
     *
     * @param string $keyword Search keyword.
     * @param int|null $limit Maximum number of records.
     * @param int|null $offset Offset of the records.
     * @param string|null $order_by Order by clause.
     *
     * @return array Returns an array with the matching holiday records.
     */
    public function search(string $keyword, ?int $limit = null, ?int $offset = null, ?string $order_by = null): array
    {
        if (!$this->db->table_exists('holidays')) {
            return [];
        }

        $this->db
            ->select('*')
            ->from('holidays')
            ->group_start()
            ->like('title', $keyword)
            ->or_like('description', $keyword)
            ->or_like('holiday_date', $keyword)
            ->group_end();

        if ($order_by !== null) {
            $this->db->order_by($this->quote_order_by($order_by));
        } else {
            $this->db->order_by('holiday_date', 'ASC');
        }

        $holidays = $this->db->limit($limit)->offset($offset)->get()->result_array();

        foreach ($holidays as &$holiday) {
            $this->cast($holiday);
        }

        return $holidays;
    }

    /**
     * Get the Jalali date library instance.
     *
     * The model uses its own accessor (instead of the "jalali_date()" helper) so that it also works while the
     * migrations are executed, where the application helpers are not necessarily loaded yet.
     *
     * @return Jalali_date
     */
    protected function jalali(): Jalali_date
    {
        /** @var EA_Controller|CI_Controller $CI */
        $CI = &get_instance();

        if (!isset($CI->jalali_date)) {
            $CI->load->library('jalali_date');
        }

        return $CI->jalali_date;
    }

    /**
     * Fill in the Jalali month/day fields of a recurring holiday.
     *
     * @param array $holiday Holiday data (passed by reference).
     */
    protected function sync_jalali_fields(array &$holiday): void
    {
        $is_recurring = filter_var($holiday['is_recurring'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $holiday['is_recurring'] = $is_recurring ? 1 : 0;

        $date = $holiday['holiday_date'] ?? null;

        if (!$is_recurring || empty($date)) {
            $holiday['jalali_month'] = null;
            $holiday['jalali_day'] = null;

            return;
        }

        [$year, $month, $day] = $this->jalali()->gregorian_to_jalali(
            (int) date('Y', strtotime($date)),
            (int) date('n', strtotime($date)),
            (int) date('j', strtotime($date)),
        );

        $holiday['jalali_month'] = $month;
        $holiday['jalali_day'] = $day;
    }

    /**
     * Check whether the provided Gregorian date is a holiday.
     *
     * @param string $date Gregorian date (Y-m-d).
     *
     * @return bool
     */
    public function is_holiday(string $date): bool
    {
        return !empty($this->get_by_date($date));
    }

    /**
     * Get the holidays of a specific Gregorian date (both fixed and recurring ones).
     *
     * @param string $date Gregorian date (Y-m-d).
     *
     * @return array Returns the matching holiday records.
     */
    public function get_by_date(string $date): array
    {
        if (!is_valid_date_value($date) || !$this->db->table_exists('holidays')) {
            return [];
        }

        [, $jalali_month, $jalali_day] = $this->jalali()->gregorian_to_jalali(
            (int) date('Y', strtotime($date)),
            (int) date('n', strtotime($date)),
            (int) date('j', strtotime($date)),
        );

        $holidays = $this->db
            ->select('*')
            ->from('holidays')
            ->group_start()
            ->where('holiday_date', $date)
            ->or_group_start()
            ->where('is_recurring', 1)
            ->where('jalali_month', $jalali_month)
            ->where('jalali_day', $jalali_day)
            ->group_end()
            ->group_end()
            ->get()
            ->result_array();

        foreach ($holidays as &$holiday) {
            $this->cast($holiday);
        }

        return $holidays;
    }

    /**
     * Get the holidays of a date range (used by the availability calculations and the calendar).
     *
     * @param string $start_date Range start date (Y-m-d).
     * @param string $end_date Range end date (Y-m-d).
     *
     * @return array Returns an array that is indexed by the Gregorian date.
     */
    public function get_range(string $start_date, string $end_date): array
    {
        if (!is_valid_date_value($start_date) || !is_valid_date_value($end_date) || $start_date > $end_date) {
            return [];
        }

        $holidays = [];

        $range_holidays = $this->get([
            'holiday_date >=' => $start_date,
            'holiday_date <=' => $end_date,
        ]);

        $recurring_holidays = $this->get(['is_recurring' => 1]);

        foreach ($range_holidays as $holiday) {
            $holidays[$holiday['holiday_date']][] = $holiday;
        }

        $start = new DateTime($start_date);
        $end = new DateTime($end_date);

        for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
            $gregorian_date = $date->format('Y-m-d');

            [, $jalali_month, $jalali_day] = $this->jalali()->gregorian_to_jalali(
                (int) $date->format('Y'),
                (int) $date->format('n'),
                (int) $date->format('j'),
            );

            foreach ($recurring_holidays as $holiday) {
                if ((int) $holiday['jalali_month'] !== $jalali_month || (int) $holiday['jalali_day'] !== $jalali_day) {
                    continue;
                }

                $already_added = false;

                foreach ($holidays[$gregorian_date] ?? [] as $existing) {
                    if ((int) $existing['id'] === (int) $holiday['id']) {
                        $already_added = true;
                        break;
                    }
                }

                if (!$already_added) {
                    $copy = $holiday;
                    $copy['holiday_date'] = $gregorian_date;
                    $holidays[$gregorian_date][] = $copy;
                }
            }
        }

        ksort($holidays);

        return $holidays;
    }

    /**
     * Get the holidays of a Jalali month.
     *
     * @param int $jalali_year Jalali year.
     * @param int $jalali_month Jalali month (1-12).
     *
     * @return array Returns an array that is indexed by the Gregorian date.
     *
     * @throws InvalidArgumentException
     */
    public function get_jalali_month(int $jalali_year, int $jalali_month): array
    {
        $first_day = $this->jalali()->to_gregorian($jalali_year, $jalali_month, 1);

        $last_day = $this->jalali()->to_gregorian(
            $jalali_year,
            $jalali_month,
            $this->jalali()->month_days($jalali_year, $jalali_month),
        );

        return $this->get_range($first_day->format('Y-m-d'), $last_day->format('Y-m-d'));
    }

    /**
     * Get the upcoming holidays, starting from the provided date.
     *
     * @param string|null $from_date Start date (defaults to today).
     * @param int $limit Maximum number of holidays.
     *
     * @return array
     */
    public function upcoming(?string $from_date = null, int $limit = 5): array
    {
        $from_date ??= date('Y-m-d');

        $end_date = (new DateTime($from_date))->modify('+1 year')->format('Y-m-d');

        $holidays = $this->get_range($from_date, $end_date);

        $result = [];

        foreach ($holidays as $date => $items) {
            foreach ($items as $item) {
                $item['holiday_date'] = $date;

                $result[] = $item;

                if (count($result) >= $limit) {
                    return $result;
                }
            }
        }

        return $result;
    }

    /**
     * Import the official Iranian holidays of the provided Jalali year.
     *
     * The import is idempotent: holidays that already exist in the database are skipped.
     *
     * @param int $jalali_year Jalali year that will be imported.
     *
     * @return int Returns the number of the newly created holidays.
     *
     * @throws RuntimeException
     */
    public function import_official_holidays(int $jalali_year): int
    {
        $data_file = APPPATH . 'data/iran_holidays.php';

        if (!is_file($data_file)) {
            return 0;
        }

        $data = require $data_file;

        $imported = 0;

        // Fixed (Solar Hijri) holidays, which are repeated every year.
        foreach ($data['fixed'] ?? [] as $holiday) {
            $exists = $this->db
                ->get_where('holidays', [
                    'title' => $holiday['title'],
                    'is_recurring' => 1,
                    'jalali_month' => $holiday['jalali_month'],
                    'jalali_day' => $holiday['jalali_day'],
                ])
                ->num_rows();

            if ($exists) {
                continue;
            }

            $date = $this->jalali()->to_gregorian($jalali_year, $holiday['jalali_month'], $holiday['jalali_day']);

            $this->insert([
                'title' => $holiday['title'],
                'description' => $holiday['description'] ?? null,
                'holiday_date' => $date->format('Y-m-d'),
                'is_recurring' => 1,
                'jalali_month' => $holiday['jalali_month'],
                'jalali_day' => $holiday['jalali_day'],
            ]);

            $imported++;
        }

        // Lunar (Hijri) holidays, which move every year and are therefore imported as fixed dates.
        $range_start = $this->jalali()->to_gregorian($jalali_year, 1, 1)->format('Y-m-d');

        $range_end = jalali_date()
            ->to_gregorian($jalali_year, 12, $this->jalali()->month_days($jalali_year, 12))
            ->format('Y-m-d');

        foreach ($data['lunar'] ?? [] as $date => $titles) {
            if ($date < $range_start || $date > $range_end) {
                continue;
            }

            foreach ((array) $titles as $title) {
                $exists = $this->db
                    ->get_where('holidays', ['title' => $title, 'is_recurring' => 0, 'holiday_date' => $date])
                    ->num_rows();

                if ($exists) {
                    continue;
                }

                $this->insert([
                    'title' => $title,
                    'holiday_date' => $date,
                    'is_recurring' => 0,
                ]);

                $imported++;
            }
        }

        return $imported;
    }

    /**
     * Validate a Gregorian date value (Y-m-d).
     *
     * @param mixed $date Date value.
     *
     * @return bool
     */
    protected function validate_date_value(mixed $date): bool
    {
        return is_string($date) && (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
}
