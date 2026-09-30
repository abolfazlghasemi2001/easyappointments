<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * SMS queue and log.
 *
 * Every message that the application wants to send is stored in this table
 * before it is handed over to the SMS gateway. That gives the operators a full
 * log of what was sent (and what failed), and it allows messages to be retried
 * later when the gateway is temporarily unavailable.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Migration_Create_sms_messages_table
 */
class Migration_Create_sms_messages_table extends EA_Migration
{
    /**
     * Upgrade method.
     *
     * @throws Exception
     */
    public function up(): void
    {
        if (!$this->db->table_exists('sms_messages')) {
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
                'message' => [
                    'type' => 'TEXT',
                ],
                'context' => [
                    'type' => 'VARCHAR',
                    'constraint' => '64',
                    'null' => true,
                ],
                'reference_id' => [
                    'type' => 'BIGINT',
                    'constraint' => '20',
                    'unsigned' => true,
                    'null' => true,
                ],
                'driver' => [
                    'type' => 'VARCHAR',
                    'constraint' => '32',
                    'null' => true,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => '16',
                    'default' => 'queued',
                ],
                'attempts' => [
                    'type' => 'INT',
                    'constraint' => '11',
                    'default' => 0,
                ],
                'max_attempts' => [
                    'type' => 'INT',
                    'constraint' => '11',
                    'default' => 3,
                ],
                'next_attempt_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'provider_message_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => '191',
                    'null' => true,
                ],
                'error' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'create_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'sent_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'update_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('phone_number');
            $this->dbforge->add_key('status');
            $this->dbforge->add_key('context');
            $this->dbforge->add_key('create_datetime');

            $this->dbforge->create_table('sms_messages', true, $this->is_sqlite() ? [] : ['engine' => 'InnoDB']);
        }

        $settings = [
            'sms_enabled' => '1',
            'sms_driver' => 'mock',
            'sms_sender_name' => '',
            'sms_max_attempts' => '3',
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
        if ($this->db->table_exists('sms_messages')) {
            $this->dbforge->drop_table('sms_messages', true);
        }

        $this->db->where_in('name', ['sms_enabled', 'sms_driver', 'sms_sender_name', 'sms_max_attempts'])
            ->delete('settings');
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
