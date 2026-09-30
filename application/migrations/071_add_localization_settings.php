<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Add the localization settings of the Persian (Jalali) calendar.
 *
 * The following settings are created (only when they do not exist yet):
 *
 *   - calendar_type:    "jalali" or "gregorian" display calendar.
 *   - persian_digits:   "1" to render the digits with Persian numerals.
 *   - display_timezone: timezone that is used when displaying the dates.
 *   - first_weekday:    the week starts on Saturday ("saturday").
 *   - date_format:      the Jalali dates are displayed as Y/m/d.
 *   - default_language: the preselected language of the installation ("persian").
 */
class Migration_Add_localization_settings extends EA_Migration
{
    /**
     * Upgrade method.
     *
     * @throws Exception
     */
    public function up(): void
    {
        if (!$this->db->table_exists('settings')) {
            return;
        }

        $settings = [
            'calendar_type' => 'jalali',
            'persian_digits' => '1',
            'display_timezone' => 'Asia/Tehran',
        ];

        foreach ($settings as $name => $value) {
            $exists = $this->db->get_where('settings', ['name' => $name])->num_rows();

            if (!$exists) {
                $this->db->insert('settings', [
                    'name' => $name,
                    'value' => $value,
                ]);
            }
        }

        // The Persian language is the default language of the installation (the customers of the booking page will
        // not have to select it explicitly).
        $this->update_existing_setting('default_language', 'persian');

        $this->update_existing_setting('first_weekday', 'saturday');
        $this->update_existing_setting('date_format', 'YMD');
    }

    /**
     * Update the value of an existing setting.
     *
     * @param string $name Setting name.
     * @param string $value New value.
     */
    protected function update_existing_setting(string $name, string $value): void
    {
        if (!$this->db->get_where('settings', ['name' => $name])->num_rows()) {
            $this->db->insert('settings', ['name' => $name, 'value' => $value]);

            return;
        }

        $this->db->update('settings', ['value' => $value], ['name' => $name]);
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if (!$this->db->table_exists('settings')) {
            return;
        }

        $this->db->delete('settings', ['name' => 'calendar_type']);
        $this->db->delete('settings', ['name' => 'persian_digits']);
        $this->db->delete('settings', ['name' => 'display_timezone']);
    }
}
