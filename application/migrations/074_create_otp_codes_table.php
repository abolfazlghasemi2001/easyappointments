<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * One-time passwords: customers log into the customer portal with their phone
 * number and a short code that is sent to them over SMS.
 *
 * The codes are never stored in plain text (a password hash is stored instead)
 * and every code has a short lifetime plus a limited number of verification
 * attempts, so that a leaked database dump does not expose valid codes.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Migration_Create_otp_codes_table
 */
class Migration_Create_otp_codes_table extends EA_Migration
{
    /**
     * Upgrade method.
     *
     * @throws Exception
     */
    public function up(): void
    {
        if (!$this->db->table_exists('otp_codes')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => '20',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'phone_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => '32',
                ],
                'purpose' => [
                    'type' => 'VARCHAR',
                    'constraint' => '32',
                    'default' => 'portal_login',
                ],
                'code_hash' => [
                    'type' => 'VARCHAR',
                    'constraint' => '255',
                ],
                'attempts' => [
                    'type' => 'INT',
                    'constraint' => '11',
                    'default' => 0,
                ],
                'max_attempts' => [
                    'type' => 'INT',
                    'constraint' => '11',
                    'default' => 5,
                ],
                'expires_datetime' => [
                    'type' => 'DATETIME',
                ],
                'used_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'ip_address' => [
                    'type' => 'VARCHAR',
                    'constraint' => '45',
                    'null' => true,
                ],
                'create_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('phone_number');
            $this->dbforge->add_key('create_datetime');

            $this->dbforge->create_table('otp_codes', true, $this->is_sqlite() ? [] : ['engine' => 'InnoDB']);
        }

        $settings = [
            'otp_enabled' => '1',
            'otp_code_length' => '5',
            'otp_code_ttl_seconds' => '120',
            'otp_max_attempts' => '5',
            'otp_max_requests_per_phone' => '3',
            'otp_request_window_minutes' => '15',
            'otp_max_requests_per_ip' => '10',
            'otp_request_ip_window_minutes' => '60',
            'portal_enabled' => '1',
        ];

        foreach ($settings as $name => $value) {
            if (!$this->db->get_where('settings', ['name' => $name])->num_rows()) {
                $this->db->insert('settings', ['name' => $name, 'value' => $value]);
            }
        }
    }

    /**
     * Downgrade method.
     *
     * @throws Exception
     */
    public function down(): void
    {
        if ($this->db->table_exists('otp_codes')) {
            $this->dbforge->drop_table('otp_codes', true);
        }

        $this->db->where_in('name', [
            'otp_enabled',
            'otp_code_length',
            'otp_code_ttl_seconds',
            'otp_max_attempts',
            'otp_max_requests_per_phone',
            'otp_request_window_minutes',
            'otp_max_requests_per_ip',
            'otp_request_ip_window_minutes',
            'portal_enabled',
        ])->delete('settings');
    }

    /**
     * Check whether the database driver is SQLite (used by the development/test environment).
     *
     * @return bool
     */
    protected function is_sqlite(): bool
    {
        return in_array($this->db->dbdriver, ['sqlite', 'sqlite3'], true);
    }
}
