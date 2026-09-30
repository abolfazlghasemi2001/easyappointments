/**
 * Web server configuration tests (no browser, no docker required).
 *
 * They verify that deploy/nginx/default.conf keeps blocking the application internals, that the development
 * server uses the same list (dev/sandbox/blocked-paths.mjs) and that the deployment files are consistent.
 *
 * The audit of step 0 (docs/fa/step-00-audit.md) found that /storage/sessions, /storage/backups and
 * /config.php were reachable from the internet with the previous configuration, so these rules are the most
 * security relevant part of step 1 and must not regress silently.
 *
 * Run with:
 *
 *     npm run test:config
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import {
    BLOCKED_EXTENSIONS,
    BLOCKED_PREFIXES,
    BLOCKED_ROOT_FILES,
    isBlockedPath,
} from '../../dev/sandbox/blocked-paths.mjs';

const REPO = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

let passed = 0;
let failed = 0;

function assert(description, condition, extra = '') {
    if (condition) {
        passed++;
        console.log(`  ok   ${description}`);
    } else {
        failed++;
        console.log(`  FAIL ${description}${extra ? ' -> ' + extra : ''}`);
    }
}

function read(relativePath) {
    return fs.readFileSync(path.join(REPO, relativePath), 'utf8');
}

/**
 * Collect the regular expressions of every "location" block of the nginx configuration.
 *
 * @returns {Array<{pattern: string, regex: RegExp, caseInsensitive: boolean}>}
 */
function nginxLocationPatterns(configuration) {
    const patterns = [];

    const expression = /location\s+(?:(~|~\*|=\s*)\s*)?([^\s{]+)\s*\{/g;

    let match;

    while ((match = expression.exec(configuration)) !== null) {
        const operator = (match[1] || '').trim();
        const pattern = match[2];

        // Only the regular expression locations are interesting here ("location ~ ..." and "location ~* ...").
        if (operator !== '~' && operator !== '~*') {
            continue;
        }

        try {
            patterns.push({
                pattern,
                regex: new RegExp(pattern, operator === '~*' ? 'i' : ''),
                caseInsensitive: operator === '~*',
            });
        } catch (error) {
            patterns.push({ pattern, regex: null, caseInsensitive: operator === '~*' });
        }
    }

    return patterns;
}

const nginx = read('deploy/nginx/default.conf');
const patterns = nginxLocationPatterns(nginx);

/**
 * The location that nginx would use for a path. nginx evaluates the regular expression locations in the order
 * they appear in the configuration and uses the first match, so the order of "patterns" is significant.
 */
const nginxLocationFor = (pathname) =>
    patterns.find((entry) => entry.regex !== null && entry.regex.test(pathname)) || null;

/**
 * Whether nginx refuses the path: it is either caught by a "return 404" rule or by the rule that allows only
 * the front controller to be executed.
 */
const blockedByNginx = (pathname) => {
    const location = nginxLocationFor(pathname);

    if (!location) {
        return false;
    }

    return !location.pattern.startsWith('^/index\\.php');
};

const compose = read('docker-compose.prod.yml');
const caddyCompose = read('docker-compose.caddy.yml');
const caddyfile = read('deploy/Caddyfile');
const envExample = read('.env.example');

console.log('\nnginx configuration (deploy/nginx/default.conf)');

// ---------------------------------------------------------------------------
// 1. The blocking rules
// ---------------------------------------------------------------------------

assert('the configuration has regular expression locations', patterns.length > 0);

for (const prefix of BLOCKED_PREFIXES) {
    assert(`nginx blocks /${prefix}/...`, blockedByNginx(`/${prefix}/anything.php`), `pattern ${prefix}`);
}

for (const file_ of BLOCKED_ROOT_FILES) {
    assert(`nginx blocks /${file_}`, blockedByNginx(`/${file_}`) || blockedByNginx(`/${file_}`.replace(/\./g, '.')), file_);
}

for (const extension of BLOCKED_EXTENSIONS) {
    assert(`nginx blocks *.${extension}`, blockedByNginx(`/some/path/file.${extension}`), `.${extension}`);
}

assert('nginx blocks hidden files', blockedByNginx('/.env') || blockedByNginx('/.git/config'));

assert(
    'nginx blocks the session and backup directories',
    blockedByNginx('/storage/sessions/ea_session') && blockedByNginx('/storage/backups/ea-db-1.sql'),
);

assert('the /storage rule exists', /location ~ \^\/\(application\|system\|/.test(nginx) && nginx.includes('storage'));

// ---------------------------------------------------------------------------
// 2. Only the front controller is executed
// ---------------------------------------------------------------------------

assert('only /index.php may be executed', blockedByNginx('/index.php') === false);

assert('any other PHP file returns 404', blockedByNginx('/some/other.php') === true);

assert('php-fpm receives the requests', nginx.includes('fastcgi_pass app:9000;'));

assert(
    'PATH_INFO is forwarded (the /index.php/booking URLs keep working)',
    nginx.includes('fastcgi_param PATH_INFO $fastcgi_path_info;'),
);

assert(
    'the client address is taken from the reverse proxy',
    nginx.includes('real_ip_header X-Forwarded-For;') && nginx.includes('set_real_ip_from 172.16.0.0/12;'),
);

assert(
    'the health endpoints are restricted to the private networks',
    (nginx.match(/allow 172\.16\.0\.0\/12;/g) || []).length >= 2,
);

assert('the front controller falls through to PHP', nginx.includes('try_files $uri $uri/ /index.php?$query_string;'));

// ---------------------------------------------------------------------------
// 3. The development server uses the same rules
// ---------------------------------------------------------------------------

console.log('\ndevelopment server (dev/sandbox/server.mjs)');

const sandboxServer = read('dev/sandbox/server.mjs');

assert('the development server imports the shared list', sandboxServer.includes("from './blocked-paths.mjs'"));

assert('the development server blocks these paths', sandboxServer.includes('isBlockedPath(pathname)'));

for (const pathname of [
    '/storage/easyappointments.sqlite',
    '/storage/sessions/abc',
    '/application/config/config.php',
    '/config.php',
    '/.env',
    '/docs/fa/step-01-production.md',
    '/dev/sandbox/setup.sh',
    '/tests/TestCase.php',
    '/some/other.php',
]) {
    assert(`isBlockedPath("${pathname}")`, isBlockedPath(pathname) === true);
}

for (const pathname of ['/', '/index.php', '/assets/css/app.css']) {
    assert(`isBlockedPath("${pathname}") is false`, isBlockedPath(pathname) === false);
}

// The development helper and the production configuration must agree on every
// path of the list, otherwise a file that is public in the preview would be
// blocked in production (or worse, the other way round).
for (const pathname of [
    '/storage/easyappointments.sqlite',
    '/config.php',
    '/application/config/config.php',
    '/tests/TestCase.php',
    '/dev/sandbox/setup.sh',
    '/docs/fa/step-00-audit.md',
]) {
    assert(
        `the preview and nginx agree about ${pathname}`,
        isBlockedPath(pathname) === blockedByNginx(pathname),
        `preview=${isBlockedPath(pathname)} nginx=${blockedByNginx(pathname)}`,
    );
}

// ---------------------------------------------------------------------------
// 4. The production stack
// ---------------------------------------------------------------------------

console.log('\ndocker compose stack (docker-compose.prod.yml)');

assert('three services are defined', ['db:', 'app:', 'web:'].every((service) => compose.includes(service)));

const databaseService = compose.split('app:')[0];

assert('the database has no published port', !/\n\s+ports:/.test(databaseService));

assert('the database has a healthcheck', compose.includes('mysqladmin ping --silent'));

assert('the application waits for the database', compose.includes('condition: service_healthy'));

assert('the web server is published on the loopback only', compose.includes("'127.0.0.1:${APP_HTTP_PORT:-8080}:80'"));

assert('the named volume of the database is declared', compose.includes('db_data:'));

const composeWithoutEnvReferences = compose.replace(/\$\{[^}]*\}/g, '');

assert(
    'no credential is hardcoded (the upstream values secret/password/user are gone)',
    !/:\s*(secret|password|user)\s*$/im.test(composeWithoutEnvReferences),
);

assert(
    'the compose files use the 2.4 format (docker-compose 1.29 on the server)',
    compose.includes('version: "2.4"') && caddyCompose.includes('version: "2.4"'),
);

assert('the deployment scripts exist', fs.existsSync(path.join(REPO, 'scripts/deploy.sh')));

for (const script of ['deploy.sh', 'backup.sh', 'restore.sh', 'rollback.sh', 'server-hardening.sh']) {
    const mode = fs.statSync(path.join(REPO, 'scripts', script)).mode;

    assert(`scripts/${script} is executable`, (mode & 0o111) !== 0);
}

console.log('\nTLS (docker-compose.caddy.yml, deploy/Caddyfile)');

assert('caddy publishes 80 and 443', caddyCompose.includes("'80:80'") && caddyCompose.includes("'443:443'"));

assert('caddy stores its certificates in a named volume', caddyCompose.includes('caddy_data:'));

assert('the Caddyfile uses the domain variable', caddyfile.includes('{$APP_DOMAIN}'));

assert('the reverse proxy targets the web service', caddyfile.includes('reverse_proxy web:80'));

assert('HSTS is sent', caddyfile.includes('Strict-Transport-Security'));

assert('the health endpoints are blocked in front as well', caddyfile.includes('/index.php/health'));

assert('port 80 is not declared twice', !/^:80\s*\{/m.test(caddyfile));

console.log('\nenvironment example (.env.example)');

for (const key of [
    'APP_ENV',
    'APP_URL',
    'ENCRYPTION_KEY',
    'DB_PASSWORD',
    'MYSQL_ROOT_PASSWORD',
    'SMS_DRIVER',
    'TEXTBEE_API_KEY',
    'SMS_MAINTENANCE_KEY',
    'BACKUP_DIR',
]) {
    assert(`${key} is documented`, new RegExp(`^${key}=`, 'm').test(envExample));
}

assert(
    'no real credential is committed',
    !/TEXTBEE_API_KEY=\S+/.test(envExample) && !/DB_PASSWORD=\S+/.test(envExample),
);

const gitignore = read('.gitignore');

assert('.env is ignored', /^\.env$/m.test(gitignore));

assert('.env.example is tracked', /^!\.env\.example$/m.test(gitignore));

// ---------------------------------------------------------------------------

console.log(`\n${passed} passed, ${failed} failed\n`);

process.exit(failed === 0 ? 0 : 1);
