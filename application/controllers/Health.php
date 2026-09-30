<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler (fork)
 *
 * Health check endpoint.
 *
 *   GET /index.php/health          (also reachable as /health)
 *
 * It answers with JSON and reports:
 *
 *   * the database connection and the tables the application needs,
 *   * the current vs. the latest available database migration,
 *   * whether the storage directory is writable,
 *   * whether the SMS credentials are complete for the configured driver.
 *
 * Requests from the internet only receive {"status":"ok"} and never any detail,
 * so the endpoint can be used by an uptime monitor without leaking the state of
 * the installation. Requests from the private docker network (the container
 * health checks and scripts/deploy.sh) receive the full report.
 *
 * This controller is a fork addition; the core controllers are not touched.
 *
 * @package     Easy!Appointments
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * ---------------------------------------------------------------------------- */

/**
 * Health controller.
 */
class Health extends EA_Controller
{
    /**
     * Tables that must exist for the application to work.
     *
     * @var string[]
     */
    protected const REQUIRED_TABLES = [
        'settings',
        'users',
        'roles',
        'services',
        'service_categories',
        'services_providers',
        'appointments',
        'working_plan_exceptions',
        'blocked_periods',
        'consents',
        'webhooks',
        'migrations',
        'otp_codes',
        'sms_messages',
        'waitlist',
    ];

    /**
     * Health constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Return the state of the installation.
     */
    public function index(): void
    {
        if ($this->input->method() !== 'get') {
            $this->output
                ->set_status_header(405)
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => 'Method not allowed']));

            return;
        }

        // Only the local machine and the private docker networks may see details.
        if (!$this->is_internal_request()) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => 'ok']));

            return;
        }

        $checks = [
            'database' => $this->check_database(),
            'tables' => $this->check_tables(),
            'migrations' => $this->check_migrations(),
            'storage' => $this->check_storage(),
            'sms' => $this->check_sms(),
        ];

        $failed = array_keys(array_filter($checks, static fn(array $check): bool => $check['status'] === 'error'));

        $this->output
            ->set_status_header($failed ? 503 : 200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => $failed ? 'degraded' : 'ok',
                'failed' => $failed,
                'checks' => $checks,
                'time' => date('c'),
            ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Whether the request comes from the local machine or a private network.
     */
    protected function is_internal_request(): bool
    {
        if (is_cli()) {
            return true;
        }

        $ip = (string) $this->input->ip_address();

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    /**
     * Check the database connection.
     *
     * @return array{status: string, message?: string}
     */
    protected function check_database(): array
    {
        try {
            $this->db->query('SELECT 1');

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return ['status' => 'error', 'message' => 'The database is not reachable.'];
        }
    }

    /**
     * Check that all tables the application needs are present.
     *
     * @return array{status: string, message?: string}
     */
    protected function check_tables(): array
    {
        try {
            $missing = [];

            foreach (self::REQUIRED_TABLES as $table) {
                if (!$this->db->table_exists($table)) {
                    $missing[] = $this->db->dbprefix($table);
                }
            }

            return $missing
                ? ['status' => 'error', 'message' => 'Missing tables: ' . implode(', ', $missing)]
                : ['status' => 'ok'];
        } catch (Throwable $exception) {
            return ['status' => 'error', 'message' => 'The tables could not be checked.'];
        }
    }

    /**
     * Compare the applied migration with the newest migration file.
     *
     * @return array{status: string, message?: string}
     */
    protected function check_migrations(): array
    {
        try {
            $query = $this->db->select('version')->order_by('version', 'DESC')->limit(1)->get('migrations');

            $applied = (int) ($query->row_array()['version'] ?? 0);

            $latest = 0;

            foreach (glob(APPPATH . 'migrations/*.php') ?: [] as $file) {
                if (preg_match('/^(\d+)_/', basename($file), $matches)) {
                    $latest = max($latest, (int) $matches[1]);
                }
            }

            if ($applied < $latest) {
                return [
                    'status' => 'error',
                    'message' => 'Pending database migrations: applied ' . $applied . ', available ' . $latest
                        . '. Run "php index.php console migrate" on the server.',
                ];
            }

            return ['status' => 'ok', 'version' => $applied];
        } catch (Throwable $exception) {
            return ['status' => 'error', 'message' => 'The migration state could not be checked.'];
        }
    }

    /**
     * Check that the storage directory is writable.
     *
     * @return array{status: string, message?: string}
     */
    protected function check_storage(): array
    {
        $path = APPPATH . '../storage';

        if (!is_dir($path) || !is_writable($path)) {
            return ['status' => 'error', 'message' => 'The storage directory is missing or not writable.'];
        }

        return ['status' => 'ok'];
    }

    /**
     * Check that the SMS configuration is complete (never exposes a credential).
     *
     * @return array{status: string, message?: string}
     */
    protected function check_sms(): array
    {
        $driver = (string) env('SMS_DRIVER', setting('sms_driver', 'mock'));

        if ($driver !== 'textbee') {
            return ['status' => 'ok', 'driver' => 'mock'];
        }

        $missing = [];

        foreach (['TEXTBEE_API_KEY', 'TEXTBEE_DEVICE_ID'] as $key) {
            if ((string) env($key, '') === '') {
                $missing[] = $key;
            }
        }

        if ($missing) {
            return ['status' => 'error', 'message' => 'Missing environment values: ' . implode(', ', $missing)];
        }

        return ['status' => 'ok', 'driver' => 'textbee'];
    }
}
