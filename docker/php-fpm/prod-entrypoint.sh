#!/bin/sh
# ---------------------------------------------------------------------------
# Entrypoint of the production php-fpm container.
#
# The container never modifies the application code on its own, it only makes
# sure that the runtime prerequisites are in place and reports them in the
# container log, so that a broken deployment is visible immediately.
#
# Fork addition - not part of the upstream project (see CUSTOMIZATIONS.md).
# ---------------------------------------------------------------------------
set -eu

cd /var/www/html

log() {
    echo "[entrypoint] $*"
}

if [ ! -f /var/www/html/index.php ]; then
    log 'FATAL: /var/www/html does not contain the application (missing index.php).'
    log 'Is the bind mount of docker-compose.prod.yml pointing at the repository?'
    exit 1
fi

if [ ! -f /var/www/html/config.php ]; then
    log 'FATAL: config.php is missing. On the server it must contain:'
    log "         <?php require_once __DIR__ . '/config-loader.php';"
    exit 1
fi

if [ ! -f /var/www/html/.env ]; then
    log 'WARNING: .env is missing, the application will not have any credentials.'
fi

if [ ! -d /var/www/html/vendor ]; then
    log 'WARNING: vendor/ is missing, run "composer install --no-dev" (scripts/deploy.sh does it).'
fi

if [ ! -d /var/www/html/assets/vendor ]; then
    log 'WARNING: assets/vendor/ is missing, run "npx gulp compile" (scripts/deploy.sh does it).'
fi

# storage/ must be writable by the pool user (www-data).
for directory in storage storage/cache storage/logs storage/sessions storage/uploads storage/backups; do
    if [ -d "/var/www/html/$directory" ] && [ ! -w "/var/www/html/$directory" ]; then
        log "FATAL: /var/www/html/$directory is not writable by $(id -un)."
        log 'Run: chown -R 82:82 storage  (Debian images use uid/gid 33 for www-data)'
        exit 1
    fi
done

log "PHP $(php -r 'echo PHP_VERSION;') - $(php -r 'echo ini_get("memory_limit");') memory limit"
log 'Starting php-fpm ...'

exec "$@"
