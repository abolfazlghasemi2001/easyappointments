<?php
/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler (fork)
 *
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ----------------------------------------------------------------------------
 *
 * Environment configuration loader.
 *
 * The production installation keeps every credential in the root ".env" file
 * (which is never committed) instead of the "config.php" file, so that the
 * repository does not contain any secret and the deployment can be reproduced.
 *
 * Usage - the root "config.php" of the installation contains exactly this:
 *
 *     <?php require_once __DIR__ . '/config-loader.php';
 *
 * The loader:
 *
 *   1. reads ".env" next to this file (when it exists),
 *   2. copies the values into $_ENV (and putenv()), without overwriting the
 *      variables that the process environment (docker, systemd, CI) provides,
 *   3. defines the optional ENCRYPTION_KEY constant,
 *   4. defines the Config class from the environment values, but only when
 *      "config.php" did not define it already (the classic configuration file
 *      of an existing installation keeps working without any change).
 *
 * This file is not part of the upstream project (see CUSTOMIZATIONS.md).
 */

declare(strict_types=1);

if (!function_exists('ea_env_load')) {
    /**
     * Read a ".env" file and return the parsed key/value pairs.
     *
     * The supported syntax is intentionally small:
     *
     *     KEY=value
     *     KEY="value with spaces"
     *     KEY='value with spaces'
     *     # comment
     *
     * Values are not expanded (no ${VAR} substitution), and a key that appears
     * more than once keeps the first value. Lines that do not contain a "=" are
     * ignored, so a stray text line never breaks the boot of the application.
     *
     * @param string $path Absolute path of the ".env" file.
     *
     * @return array<string, string>
     */
    function ea_env_load(string $path): array
    {
        $values = [];

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return $values;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Tolerate the "export KEY=value" form, often used in shell scripts.
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $position = strpos($line, '=');

            if ($position === false) {
                continue;
            }

            $key = trim(substr($line, 0, $position));
            $value = trim(substr($line, $position + 1));

            if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            // Strip a single pair of matching quotes and the trailing comment of
            // unquoted values ("KEY=value # comment").
            if (strlen($value) >= 2 && (
                    ($value[0] === '"' && str_ends_with($value, '"')) ||
                    ($value[0] === "'" && str_ends_with($value, "'"))
                )) {
                $value = substr($value, 1, -1);
            } elseif (($comment = strpos($value, ' #')) !== false) {
                $value = rtrim(substr($value, 0, $comment));
            }

            if (!array_key_exists($key, $values)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }
}

if (!function_exists('ea_env')) {
    /**
     * Read an environment value with a default, accepting the usual boolean and
     * null spellings ("true", "yes", "on", "1", "false", "no", "off", "0").
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    function ea_env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', 'yes', 'on' => true,
            'false', 'no', 'off' => false,
            'null' => null,
            default => $value,
        };
    }
}

// ---------------------------------------------------------------------------
// 1. Load the ".env" file (the process environment always wins).
// ---------------------------------------------------------------------------

$ea_env_path = __DIR__ . '/.env';

if (is_readable($ea_env_path)) {
    foreach (ea_env_load($ea_env_path) as $ea_env_key => $ea_env_value) {
        if (!isset($_ENV[$ea_env_key]) && getenv($ea_env_key) === false) {
            $_ENV[$ea_env_key] = $ea_env_value;

            // index.php reads APP_ENV with getenv(), so the values are also put
            // into the process environment.
            putenv($ea_env_key . '=' . $ea_env_value);
        }
    }

    // A world readable file with database credentials is a security incident.
    if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
        clearstatcache(true, $ea_env_path);

        if ((fileperms($ea_env_path) & 0777) > 0600) {
            error_log(
                'easyappointments: ' . $ea_env_path . ' is readable by other users, run '
                . '"chmod 600 .env" on the server.',
            );
        }
    }
}

// ---------------------------------------------------------------------------
// 2. Optional constants that the application reads directly.
// ---------------------------------------------------------------------------

if (!defined('ENCRYPTION_KEY')) {
    $ea_encryption_key = ea_env('ENCRYPTION_KEY');

    if (is_string($ea_encryption_key) && $ea_encryption_key !== '') {
        define('ENCRYPTION_KEY', $ea_encryption_key);
    }
}

// ---------------------------------------------------------------------------
// 3. Define the Config class from the environment, unless "config.php" did it.
// ---------------------------------------------------------------------------

if (!class_exists('Config', false)) {
    $ea_db_password = (string) ea_env('DB_PASSWORD', '');

    if ($ea_db_password === '') {
        // Fail loudly, but with an actionable message instead of a database
        // connection error that does not say what is missing.
        http_response_code(500);
        error_log('easyappointments: DB_PASSWORD is missing, create the ".env" file from ".env.example".');

        exit('The installation is not configured: the ".env" file is missing or DB_PASSWORD is empty.');
    }

    // Class constants cannot contain function calls, but they can reference the
    // global constants that were just defined above.
    define('EA_CONFIG_BASE_URL', (string) ea_env('APP_URL', 'http://localhost'));
    define('EA_CONFIG_LANGUAGE', (string) ea_env('APP_LANGUAGE', 'english'));
    define('EA_CONFIG_DEBUG_MODE', (bool) ea_env('DEBUG_MODE', false));
    define('EA_CONFIG_DB_HOST', (string) ea_env('DB_HOST', 'localhost'));
    define('EA_CONFIG_DB_NAME', (string) ea_env('DB_NAME', 'easyappointments'));
    define('EA_CONFIG_DB_USERNAME', (string) ea_env('DB_USERNAME', ''));
    define('EA_CONFIG_DB_PASSWORD', $ea_db_password);
    define('EA_CONFIG_GOOGLE_SYNC_FEATURE', (bool) ea_env('GOOGLE_SYNC_FEATURE', false));
    define('EA_CONFIG_GOOGLE_CLIENT_ID', (string) ea_env('GOOGLE_CLIENT_ID', ''));
    define('EA_CONFIG_GOOGLE_CLIENT_SECRET', (string) ea_env('GOOGLE_CLIENT_SECRET', ''));

    /**
     * Configuration of the installation, read from the environment.
     */
    class Config
    {
        // --------------------------------------------------------------------
        // GENERAL SETTINGS
        // --------------------------------------------------------------------

        public const BASE_URL = EA_CONFIG_BASE_URL;

        public const LANGUAGE = EA_CONFIG_LANGUAGE;

        public const DEBUG_MODE = EA_CONFIG_DEBUG_MODE;

        // --------------------------------------------------------------------
        // DATABASE SETTINGS
        // --------------------------------------------------------------------

        public const DB_HOST = EA_CONFIG_DB_HOST;

        public const DB_NAME = EA_CONFIG_DB_NAME;

        public const DB_USERNAME = EA_CONFIG_DB_USERNAME;

        public const DB_PASSWORD = EA_CONFIG_DB_PASSWORD;

        // --------------------------------------------------------------------
        // GOOGLE CALENDAR SYNC (optional)
        // --------------------------------------------------------------------

        public const GOOGLE_SYNC_FEATURE = EA_CONFIG_GOOGLE_SYNC_FEATURE;

        public const GOOGLE_CLIENT_ID = EA_CONFIG_GOOGLE_CLIENT_ID;

        public const GOOGLE_CLIENT_SECRET = EA_CONFIG_GOOGLE_CLIENT_SECRET;
    }
}

unset($ea_env_path, $ea_env_key, $ea_env_value, $ea_encryption_key, $ea_db_password);
