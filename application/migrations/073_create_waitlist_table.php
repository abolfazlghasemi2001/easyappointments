<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Waiting list: customers that could not find a free slot are stored so that the
 * business can offer them a cancelled slot (optionally through an SMS message).
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Migration_Create_waitlist_table
 */
class Migration_Create_waitlist_table extends EA_Migration
{
    /**
     * Upgrade method.
     *
     * @throws Exception
     */
    public function up(): void
    {
        if ($this->db->table_exists('waitlist')) {
            return;
        }

        $this->dbforge->add_field([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => '20',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'id_users_provider' => [
                'type' => 'BIGINT',
                'constraint' => '20',
                'unsigned' => true,
                'null' => true,
            ],
            'id_services' => [
                'type' => 'BIGINT',
                'constraint' => '20',
                'unsigned' => true,
                'null' => true,
            ],
            'first_name' => [
                'type' => 'VARCHAR',
                'constraint' => '256',
                'null' => true,
            ],
            'last_name' => [
                'type' => 'VARCHAR',
                'constraint' => '256',
                'null' => true,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => '512',
                'null' => true,
            ],
            'phone_number' => [
                'type' => 'VARCHAR',
                'constraint' => '64',
                'null' => true,
            ],
            'desired_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'desired_time_from' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'desired_time_to' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => '32',
                'default' => 'waiting',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'notified_datetime' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'create_datetime' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'update_datetime' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->dbforge->add_key('id', true);
        $this->dbforge->add_key('id_users_provider');
        $this->dbforge->add_key('phone_number');
        $this->dbforge->add_key('desired_date');

        // The InnoDB storage engine is a MySQL/MariaDB attribute. The local development and test database runs on
        // SQLite, which rejects it (and would also fail on the framework forge level).
        $this->dbforge->create_table('waitlist', true, $this->is_sqlite() ? [] : ['engine' => 'InnoDB']);

        if (!$this->db->get_where('settings', ['name' => 'waitlist_enabled'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'waitlist_enabled',
                'value' => '1',
            ]);
        }
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

    /**
     * Downgrade method.
     *
     * @throws Exception
     */
    public function down(): void
    {
        if ($this->db->table_exists('waitlist')) {
            $this->dbforge->drop_table('waitlist', true);
        }

        $this->db->delete('settings', ['name' => 'waitlist_enabled']);
    }
}
