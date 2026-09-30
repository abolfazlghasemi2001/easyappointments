<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Waiting list model.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * @property CI_DB_query_builder $db
 * @property EA_Benchmark $benchmark
 * @property EA_Migration $migration
 */
class Waitlist_model extends EA_Model
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_NOTIFIED = 'notified';

    public const STATUS_BOOKED = 'booked';

    public const STATUS_EXPIRED = 'expired';

    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'id_users_provider' => 'integer',
        'id_services' => 'integer',
    ];

    /**
     * Save (insert or update) a waiting list entry.
     *
     * @param array $entry Entry data.
     *
     * @return int Returns the entry id.
     *
     * @throws InvalidArgumentException
     */
    public function save(array $entry): int
    {
        $this->validate($entry);

        if (empty($entry['id'])) {
            return $this->insert($entry);
        }

        return $this->update($entry);
    }

    /**
     * Validate the entry data.
     *
     * @param array $entry Entry data.
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $entry): void
    {
        if (!empty($entry['id']) && !$this->db->get_where('waitlist', ['id' => $entry['id']])->num_rows()) {
            throw new InvalidArgumentException('The provided waitlist entry id does not exist: ' . $entry['id']);
        }

        if (empty($entry['first_name']) && empty($entry['last_name']) && empty($entry['phone_number'])) {
            throw new InvalidArgumentException('A waiting list entry requires a name or a phone number.');
        }

        if (!empty($entry['email']) && !filter_var($entry['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('The provided email address is not valid: ' . $entry['email']);
        }

        if (!empty($entry['phone_number']) && !is_valid_iran_phone_number($entry['phone_number'])) {
            throw new InvalidArgumentException('The provided phone number is not valid: ' . $entry['phone_number']);
        }

        if (
            !empty($entry['desired_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $entry['desired_date'])
        ) {
            throw new InvalidArgumentException('The desired date must use the Y-m-d format.');
        }

        if (!empty($entry['status']) && !in_array($entry['status'], $this->statuses(), true)) {
            throw new InvalidArgumentException('Invalid waiting list status: ' . $entry['status']);
        }
    }

    /**
     * Insert a new entry.
     *
     * @param array $entry Entry data.
     *
     * @return int
     *
     * @throws RuntimeException
     */
    protected function insert(array $entry): int
    {
        $entry['status'] = $entry['status'] ?? self::STATUS_WAITING;
        $entry['create_datetime'] = date('Y-m-d H:i:s');
        $entry['update_datetime'] = date('Y-m-d H:i:s');

        if (!$this->db->insert('waitlist', $entry)) {
            throw new RuntimeException('Could not insert the waiting list entry.');
        }

        return (int) $this->db->insert_id();
    }

    /**
     * Update an existing entry.
     *
     * @param array $entry Entry data.
     *
     * @return int
     *
     * @throws RuntimeException
     */
    protected function update(array $entry): int
    {
        $entry['update_datetime'] = date('Y-m-d H:i:s');

        if (!$this->db->update('waitlist', $entry, ['id' => $entry['id']])) {
            throw new RuntimeException('Could not update the waiting list entry: ' . $entry['id']);
        }

        return (int) $entry['id'];
    }

    /**
     * Get a specific entry.
     *
     * @param int $entry_id Entry id.
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function find(int $entry_id): array
    {
        $entry = $this->db->get_where('waitlist', ['id' => $entry_id])->row_array();

        if (!$entry) {
            throw new InvalidArgumentException('The provided waitlist entry was not found: ' . $entry_id);
        }

        $this->cast($entry);

        return $entry;
    }

    /**
     * Get multiple entries.
     *
     * @param array|null $where Where clause.
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $order_by
     *
     * @return array
     */
    public function get(?array $where = null, ?int $limit = null, ?int $offset = null, ?string $order_by = null): array
    {
        if (!$this->db->table_exists('waitlist')) {
            return [];
        }

        $entries = $this->get_batch($where, $limit, $offset, $order_by ?? 'desired_date ASC, id ASC');

        foreach ($entries as &$entry) {
            $this->cast($entry);
        }

        return $entries;
    }

    /**
     * Search the entries by keyword.
     *
     * @param string $keyword
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $order_by
     *
     * @return array
     */
    public function search(
        string $keyword,
        ?int $limit = null,
        ?int $offset = null,
        ?string $order_by = null,
    ): array {
        if (!$this->db->table_exists('waitlist')) {
            return [];
        }

        $this->db
            ->select('*')
            ->from('waitlist')
            ->group_start()
            ->like('first_name', $keyword)
            ->or_like('last_name', $keyword)
            ->or_like('phone_number', $keyword)
            ->or_like('email', $keyword)
            ->group_end();

        if ($order_by !== null) {
            $this->db->order_by($this->quote_order_by($order_by));
        } else {
            $this->db->order_by('desired_date', 'ASC');
        }

        $entries = $this->db->limit($limit)->offset($offset)->get()->result_array();

        foreach ($entries as &$entry) {
            $this->cast($entry);
        }

        return $entries;
    }

    /**
     * Delete an entry.
     *
     * @param int $entry_id
     *
     * @return bool
     */
    public function delete(int $entry_id): bool
    {
        if (!$this->db->table_exists('waitlist')) {
            return false;
        }

        return (bool) $this->db->delete('waitlist', ['id' => $entry_id]);
    }

    /**
     * Find the next waiting entry for a provider/date, optionally restricted to a service.
     *
     * @param int $provider_id Provider id.
     * @param string|null $date Desired date (Y-m-d).
     * @param int|null $service_id Service id.
     *
     * @return array|null
     */
    public function next_in_line(int $provider_id, ?string $date = null, ?int $service_id = null): ?array
    {
        if (!$this->db->table_exists('waitlist')) {
            return null;
        }

        $this->db->from('waitlist')
            ->where('status', self::STATUS_WAITING)
            ->group_start()
            ->where('id_users_provider', $provider_id)
            ->or_where('id_users_provider IS NULL', null, false)
            ->group_end();

        if ($date) {
            $this->db->where('desired_date <=', $date);
        }

        if ($service_id) {
            $this->db->group_start()
                ->where('id_services', $service_id)
                ->or_where('id_services IS NULL', null, false)
                ->group_end();
        }

        $entry = $this->db->order_by('desired_date ASC, id ASC')->limit(1)->get()->row_array();

        if (!$entry) {
            return null;
        }

        $this->cast($entry);

        return $entry;
    }

    /**
     * Mark an entry as notified.
     *
     * @param int $entry_id
     *
     * @return bool
     */
    public function mark_notified(int $entry_id): bool
    {
        return (bool) $this->db->update(
            'waitlist',
            [
                'status' => self::STATUS_NOTIFIED,
                'notified_datetime' => date('Y-m-d H:i:s'),
                'update_datetime' => date('Y-m-d H:i:s'),
            ],
            ['id' => $entry_id],
        );
    }

    /**
     * Get the available statuses.
     *
     * @return string[]
     */
    public function statuses(): array
    {
        return [self::STATUS_WAITING, self::STATUS_NOTIFIED, self::STATUS_BOOKED, self::STATUS_EXPIRED];
    }
}
