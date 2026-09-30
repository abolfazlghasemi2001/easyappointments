/**
 * Paths that the web server must never serve, shared by the development server and by the configuration test.
 *
 * The production rules live in deploy/nginx/default.conf. Keeping the same list here has two purposes:
 *
 *   1. the sandbox preview (dev/sandbox/server.mjs) behaves like the production server, so a file that is public
 *      in the preview would also be public in production, and a mistake is visible before the deployment,
 *   2. tests/nginx/blocking.test.mjs compares this list with the nginx configuration, so the two cannot drift
 *      apart silently (the audit of step 0 found that /storage, /application and /config.php were reachable).
 *
 * See CUSTOMIZATIONS.md.
 */

/** Directory prefixes that are never served (matched at the start of the path). */
export const BLOCKED_PREFIXES = [
    'application',
    'system',
    'vendor',
    'tests',
    'dev',
    'docs',
    'scripts',
    'docker',
    'deploy',
    'storage',
    'node_modules',
    '.git',
    '.github',
];

/** Files in the web root that are never served. */
export const BLOCKED_ROOT_FILES = [
    'config.php',
    'config-loader.php',
    'config-sample.php',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'phpunit.xml',
    'gulpfile.js',
    'babel.config.json',
    'openapi.yml',
    'README.md',
    'CHANGELOG.md',
    'CUSTOMIZATIONS.md',
    'LICENSE',
];

/** File extensions that are never served, wherever they appear. */
export const BLOCKED_EXTENSIONS = [
    'env',
    'sqlite',
    'sqlite3',
    'sql',
    'log',
    'bak',
    'old',
    'swp',
    'tar',
    'tgz',
    'gz',
    'zip',
    'ini',
    'lock',
    'md',
    'json',
    'yml',
    'yaml',
    'sh',
    'scss',
    'map',
];

/**
 * Whether a request path (with a leading slash) must be blocked by the web server.
 *
 * @param {string} pathname Request path, for example "/storage/sessions/ea_session".
 * @returns {boolean}
 */
export function isBlockedPath(pathname) {
    const path = String(pathname || '/').split('?')[0];

    // Any hidden file or directory (.env, .git/config, .htaccess, ...).
    if (/(^|\/)\.[^/]/.test(path)) {
        return true;
    }

    const prefix = BLOCKED_PREFIXES.find(
        (candidate) => path === `/${candidate}` || path.startsWith(`/${candidate}/`),
    );

    if (prefix) {
        return true;
    }

    const segments = path.split('/').filter(Boolean);

    if (segments.length > 0) {
        const last = segments[segments.length - 1];
        const extension = last.includes('.') ? last.split('.').pop().toLowerCase() : '';

        if (extension && BLOCKED_EXTENSIONS.includes(extension)) {
            return true;
        }
    }

    // Only the front controller may be executed by PHP.
    if (path.endsWith('.php') && path !== '/index.php') {
        return true;
    }

    return false;
}
