<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Booking integrity: the database must not allow two appointments of the same
 * provider to start at the same time, and a provisionally reserved slot must be
 * released automatically (10 minute hold).
 *
 * @package     EasyAppointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Migration_Add_booking_integrity
 *
 * - Adds the "hold_expires_datetime" column to the appointments table.
 * - Adds the "booking_hold_minutes" setting.
 * - Adds the "Completed" and "No-show" values to the appointment status options.
 * - Adds a unique index that protects against concurrent bookings.
 *
 * The unique index is the only reliable protection against two simultaneous requests that both pass the availability
 * check (each request runs in its own transaction, so a plain SELECT cannot see the other one).
 *
 * The preferred index is created on the expression
 *
 *   (CASE WHEN status IN ('Cancelled', 'Draft') THEN NULL ELSE start_datetime END), id_users_provider
 *
 * so that appointments which do not block their slot anymore (cancelled ones) can be booked again. MySQL supports
 * functional key parts since 8.0.13 and SQLite supports expression indexes since 3.9. On engines that support
 * neither (MySQL 5.7, MariaDB) the migration falls back to a plain unique index on
 * (id_users_provider, start_datetime) and stores the used variant in the "booking_unique_index_variant" setting.
 *
 * The migration refuses to create the index when the existing data already contains duplicates, so that the
 * operator can clean them up first (see the error message and docs/fa/step-05-booking.md).
 */
class Migration_Add_booking_integrity extends EA_Migration
{
    /**
     * Index name of the preferred (expression based) unique index.
     */
    protected const INDEX_NAME = 'uniq_provider_active_start';

    /**
     * Index name of the fallback unique index (engines without expression indexes).
     */
    protected const FALLBACK_INDEX_NAME = 'uniq_provider_start';

    /**
     * Upgrade method.
     *
     * @throws Exception
     */
    public function up(): void
    {
        if (!$this->db->field_exists('hold_expires_datetime', 'appointments')) {
            $this->dbforge->add_column('appointments', [
                'hold_expires_datetime' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'status',
                ],
            ]);
        }

        if (!$this->db->get_where('settings', ['name' => 'booking_hold_minutes'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'booking_hold_minutes',
                'value' => '10',
            ]);
        }

        $this->append_status_options(['Completed', 'No-show']);

        $duplicates = $this->find_duplicates();

        if ($duplicates) {
            throw new RuntimeException(
                'The unique index was not created, because the appointments table already contains overlapping '
                . 'records. Remove them and run the migration again. Offending provider ids: '
                . implode(', ', array_column($duplicates, 'id_users_provider')),
            );
        }

        if ($this->index_exists(self::INDEX_NAME) || $this->index_exists(self::FALLBACK_INDEX_NAME)) {
            return;
        }

        $variant = $this->create_unique_index();

        if (!$this->db->get_where('settings', ['name' => 'booking_unique_index_variant'])->num_rows()) {
            $this->db->insert('settings', [
                'name' => 'booking_unique_index_variant',
                'value' => $variant,
            ]);
        }
    }

    /**
     * Create the unique index, preferring the expression based variant.
     *
     * @return string Returns "expression" or "plain".
     */
    protected function create_unique_index(): string
    {
        $table = $this->db->dbprefix('appointments');

        // Functional key parts / expression indexes are not supported by every engine, so the preferred statement is
        // attempted first and the error handling is disabled for the attempt (the framework would otherwise abort
        // the whole request with its error page).
        $db_debug = $this->db->db_debug;

        $this->db->db_debug = false;

        $expression_index = 'CREATE UNIQUE INDEX ' . self::INDEX_NAME . ' ON ' . $table
            . " ((CASE WHEN status IN ('Cancelled', 'Draft') THEN NULL ELSE start_datetime END), id_users_provider)";

        $created = $this->db->query($expression_index);

        $this->db->db_debug = $db_debug;

        if ($created) {
            return 'expression';
        }

        $this->db->query(
            'CREATE UNIQUE INDEX ' . self::FALLBACK_INDEX_NAME . ' ON ' . $table . ' (id_users_provider, start_datetime)',
        );

        log_message(
            'error',
            'The database engine does not support expression indexes, so the booking integrity index was created '
            . 'without the "Cancelled" exemption. Cancelled appointments keep blocking their start time until the '
            . 'record is deleted.',
        );

        return 'plain';
    }

    /**
     * Downgrade method.
     *
     * @throws Exception
     */
    public function down(): void
    {
        foreach ([self::INDEX_NAME, self::FALLBACK_INDEX_NAME] as $index_name) {
            if (!$this->index_exists($index_name)) {
                continue;
            }

            // SQLite does not support the "DROP INDEX ... ON table" syntax of MySQL.
            $this->db->query(
                $this->is_sqlite()
                    ? 'DROP INDEX ' . $index_name
                    : 'DROP INDEX ' . $index_name . ' ON ' . $this->db->dbprefix('appointments'),
            );
        }

        if ($this->db->field_exists('hold_expires_datetime', 'appointments')) {
            if ($this->is_sqlite()) {
                // The SQLite forge of this framework version does not implement drop_column(), so the statement is
                // executed directly. SQLite supports "DROP COLUMN" since version 3.35 (2021-03). Older versions keep
                // the column, which is harmless for the local development database.
                if (version_compare($this->db->version(), '3.35', '>=')) {
                    $this->db->query(
                        'ALTER TABLE ' . $this->db->dbprefix('appointments') . ' DROP COLUMN hold_expires_datetime',
                    );
                }
            } else {
                $this->dbforge->drop_column('appointments', 'hold_expires_datetime');
            }
        }

        $this->db->delete('settings', ['name' => 'booking_hold_minutes']);

        $this->db->delete('settings', ['name' => 'booking_unique_index_variant']);

        $this->append_status_options([], ['Completed', 'No-show']);
    }

    /**
     * Add (or remove) values of the "appointment_status_options" setting without touching the existing ones.
     *
     * @param array $add Status values to append.
     * @param array $remove Status values to remove.
     */
    protected function append_status_options(array $add = [], array $remove = []): void
    {
        $row = $this->db->get_where('settings', ['name' => 'appointment_status_options'])->row_array();

        if (!$row) {
            return;
        }

        $options = json_decode($row['value'], true);

        if (!is_array($options)) {
            $options = [];
        }

        $options = array_values(array_diff($options, $remove));

        foreach ($add as $value) {
            if (!in_array($value, $options, true)) {
                $options[] = $value;
            }
        }

        $this->db->update('settings', ['value' => json_encode($options)], ['name' => 'appointment_status_options']);
    }

    /**
     * Find the providers that already have two blocking appointments starting at the same time.
     *
     * @return array
     */
    protected function find_duplicates(): array
    {
        return $this->db
            ->select('id_users_provider, start_datetime, COUNT(*) AS total')
            ->from('appointments')
            ->where('id_users_provider IS NOT NULL', null, false)
            ->group_start()
            ->where('status IS NULL', null, false)
            ->or_where_not_in('status', ['Cancelled', 'Draft'])
            ->group_end()
            ->group_by('id_users_provider, start_datetime')
            ->having('COUNT(*) >', 1)
            ->get()
            ->result_array();
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
     * Check whether an index exists on the appointments table.
     *
     * @param string $index_name Index name.
     *
     * @return bool
     */
    protected function index_exists(string $index_name): bool
    {
        if ($this->is_sqlite()) {
            $indexes = $this->db->query('PRAGMA index_list(' . $this->db->dbprefix('appointments') . ')')->result_array();

            return in_array($index_name, array_column($indexes, 'name'), true);
        }

        $indexes = $this->db->query('SHOW INDEX FROM ' . $this->db->dbprefix('appointments'))->result_array();

        return in_array($index_name, array_column($indexes, 'Key_name'), true);
    }
}
