<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Create the "holidays" table and import the Iranian official holidays of the current and the upcoming Jalali year.
 */
class Migration_Create_holidays_table extends EA_Migration
{
    /**
     * Upgrade method.
     *
     * @throws Exception
     */
    public function up(): void
    {
        if (!$this->db->table_exists('holidays')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'auto_increment' => true,
                ],
                'create_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'update_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'title' => [
                    'type' => 'VARCHAR',
                    'constraint' => 256,
                    'null' => true,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'holiday_date' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'is_recurring' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                ],
                'jalali_month' => [
                    'type' => 'TINYINT',
                    'constraint' => 2,
                    'null' => true,
                ],
                'jalali_day' => [
                    'type' => 'TINYINT',
                    'constraint' => 2,
                    'null' => true,
                ],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('holiday_date');
            $this->dbforge->add_key(['is_recurring', 'jalali_month', 'jalali_day']);

            $this->dbforge->create_table('holidays', true, ['engine' => 'InnoDB']);
        }

        $this->import_official_holidays();
    }

    /**
     * Import the official Iranian holidays (idempotent, it will not insert duplicate records).
     */
    protected function import_official_holidays(): void
    {
        try {
            /** @var EA_Controller|CI_Controller $CI */
            $CI = &get_instance();

            if (!isset($CI->jalali_date)) {
                $CI->load->library('jalali_date');
            }

            $CI->load->model('holidays_model');

            $today = new DateTime();

            [$jalali_year] = $CI->jalali_date->gregorian_to_jalali(
                (int) $today->format('Y'),
                (int) $today->format('n'),
                (int) $today->format('j'),
            );

            $CI->holidays_model->import_official_holidays($jalali_year);
            $CI->holidays_model->import_official_holidays($jalali_year + 1);
        } catch (Throwable $exception) {
            log_message('error', 'Could not import the official holidays: ' . $exception->getMessage());
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('holidays')) {
            $this->dbforge->drop_table('holidays');
        }
    }
}
