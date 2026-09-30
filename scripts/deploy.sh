#!/usr/bin/env bash
#
# ---------------------------------------------------------------------------
# Deployment of the Easy!Appointments fork (nobat.hoosna1402.ir).
#
# The script is idempotent: running it twice does not change anything the second
# time, and it can be run again after a failed deployment.
#
# It NEVER runs a database migration on its own, because that must be a
# conscious decision with a fresh backup (see scripts/backup.sh). It prepares
# everything and then tells the operator which two commands to run.
#
# Usage (on the server, as the deployment user):
#
#   bash scripts/deploy.sh                 # full deployment
#   bash scripts/deploy.sh --skip-pull     # deploy the working tree as it is
#   bash scripts/deploy.sh --with-migrate  # also run the migrations (asks first)
#   bash scripts/deploy.sh --dry-run       # only print what would happen
#
# Fork addition - not part of the upstream project (see CUSTOMIZATIONS.md).
# ---------------------------------------------------------------------------
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_PATH="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$APP_PATH"

COMPOSE_FILE="docker-compose.prod.yml"
CADDY_FILE="docker-compose.caddy.yml"
COMPOSE=(docker compose -f "$COMPOSE_FILE")

SKIP_PULL=0
WITH_MIGRATE=0
DRY_RUN=0
WITH_CADDY=0

for argument in "$@"; do
    case "$argument" in
        --skip-pull) SKIP_PULL=1 ;;
        --with-migrate) WITH_MIGRATE=1 ;;
        --dry-run) DRY_RUN=1 ;;
        --with-caddy) WITH_CADDY=1 ;;
        -h|--help)
            sed -n '2,30p' "$0"
            exit 0
            ;;
        *)
            echo "Unknown argument: $argument" >&2
            exit 2
            ;;
    esac
done

if [ "$WITH_CADDY" = '1' ]; then
    COMPOSE=(docker compose -f "$COMPOSE_FILE" -f "$CADDY_FILE")
fi

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m ok\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[!]\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m[x]\033[0m %s\n' "$*" >&2; exit 1; }

run() {
    if [ "$DRY_RUN" = '1' ]; then
        printf '   [dry-run] %s\n' "$*"
        return 0
    fi

    "$@"
}

command -v docker >/dev/null 2>&1 || die 'docker is not installed on this machine.'

if docker compose version >/dev/null 2>&1; then
    : # the docker compose plugin is available
elif command -v docker-compose >/dev/null 2>&1; then
    # Older servers (this one runs docker-compose 1.29.2) only have the legacy
    # binary. The compose files use the 2.4 format because of this.
    COMPOSE=(docker-compose -f "$COMPOSE_FILE")
    [ "$WITH_CADDY" = '1' ] && COMPOSE=(docker-compose -f "$COMPOSE_FILE" -f "$CADDY_FILE")
else
    die 'neither "docker compose" nor "docker-compose" is available.'
fi

# ---------------------------------------------------------------------------
# 1. Preconditions
# ---------------------------------------------------------------------------

log 'Checking the preconditions ...'

[ -f "$COMPOSE_FILE" ] || die "$COMPOSE_FILE is missing. Run the script from the repository."

[ -f "$APP_PATH/.env" ] || die 'The ".env" file is missing. Create it with "cp .env.example .env" and fill in the values.'

grep -q '^DB_PASSWORD=.\+' "$APP_PATH/.env" || die 'DB_PASSWORD is empty in .env.'
grep -q '^MYSQL_ROOT_PASSWORD=.\+' "$APP_PATH/.env" || die 'MYSQL_ROOT_PASSWORD is empty in .env.'
grep -q '^ENCRYPTION_KEY=.\+' "$APP_PATH/.env" \
    || warn 'ENCRYPTION_KEY is empty. Generate one with "openssl rand -hex 32" and add it to .env.'

if [ ! -f "$APP_PATH/config.php" ]; then
    warn 'config.php is missing, it will be created with the required one-liner.'
    run bash -c "printf '%s\n' '<?php' \"/* Loads the configuration from the environment, see config-loader.php and .env.example. */\" \"require_once __DIR__ . '/config-loader.php';\" > '$APP_PATH/config.php'"
elif ! grep -q 'config-loader.php' "$APP_PATH/config.php"; then
    warn 'config.php does not load config-loader.php, so .env values are not used.'
    warn '  Keep a copy of the file and replace it with: <?php require_once __DIR__ . "/config-loader.php";'
fi

# A world readable .env is a security incident.
if [ "$(stat -c '%a' "$APP_PATH/.env")" != '600' ]; then
    warn ".env is not mode 600, fixing it (contains database credentials)."
    run chmod 600 "$APP_PATH/.env"
fi

ok 'Preconditions passed.'

# ---------------------------------------------------------------------------
# 2. Update the source code
# ---------------------------------------------------------------------------

if [ "$SKIP_PULL" = '1' ]; then
    log 'Skipping "git pull" (--skip-pull).'
else
    log 'Updating the source code (git pull --ff-only) ...'

    if [ -d "$APP_PATH/.git" ]; then
        CURRENT_BRANCH="$(git -C "$APP_PATH" rev-parse --abbrev-ref HEAD)"

        if [ "$CURRENT_BRANCH" != 'main' ]; then
            warn "The current branch is '$CURRENT_BRANCH' and not 'main'."
        fi

        if [ -n "$(git -C "$APP_PATH" status --porcelain)" ]; then
            warn 'The working tree has local changes, "git pull" may fail. They are not touched.'
        fi

        git -C "$APP_PATH" fetch --prune origin

        # Never overwrite local work: a merge that is not fast-forward stops here.
        run git -C "$APP_PATH" merge --ff-only "origin/${CURRENT_BRANCH}"

        ok "Now at $(git -C "$APP_PATH" rev-parse --short HEAD)."
    else
        warn 'Not a git checkout, skipping the update.'
    fi
fi

# ---------------------------------------------------------------------------
# 3. PHP dependencies
# ---------------------------------------------------------------------------

log 'Installing the PHP dependencies (composer, production only) ...'

PHP_SERVICE="$( "${COMPOSE[@]}" ps -q app 2>/dev/null || true )"

if [ -n "$PHP_SERVICE" ]; then
    run "${COMPOSE[@]}" exec -T app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
else
    # First deployment: the container is not running yet, install on the host.
    if command -v composer >/dev/null 2>&1; then
        run composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    else
        warn 'composer is not available yet. It will be installed inside the container by "docker compose build".'
    fi
fi

ok 'PHP dependencies are up to date.'

# ---------------------------------------------------------------------------
# 4. Front-end assets
# ---------------------------------------------------------------------------

log 'Building the front-end assets (gulp) ...'
log '   The compiled files (assets/css/*.css, assets/js/**/*.min.js) are NOT in git, so'
log '   this step is required after every deployment that changes a .scss/.js source.'

if [ -n "$PHP_SERVICE" ]; then
    run "${COMPOSE[@]}" exec -T app npx --yes gulp compile
elif command -v npx >/dev/null 2>&1; then
    run npm install --no-audit --no-fund
    run npx gulp compile
else
    warn 'node/npx is not available, the assets cannot be compiled here.'
    warn '  Alternative: run "npm ci && npx gulp compile" on a build machine and'
    warn '  commit/copy the assets/css and assets/js output to the server.'
fi

ok 'Assets are built.'

# ---------------------------------------------------------------------------
# 5. Start or reload the containers
# ---------------------------------------------------------------------------

log 'Building the production image and starting the stack ...'

run "${COMPOSE[@]}" build --pull app
run "${COMPOSE[@]}" up -d --remove-orphans

log 'Waiting for the containers to become healthy ...'

for attempt in $(seq 1 30); do
    unhealthy="$( "${COMPOSE[@]}" ps --format '{{.Name}} {{.Health}}' 2>/dev/null | grep -v -E 'healthy|^$' || true )"

    if [ -z "$unhealthy" ]; then
        ok 'All containers are healthy.'
        break
    fi

    if [ "$attempt" = '30' ]; then
        warn 'Some containers did not become healthy:'
        printf '%s\n' "$unhealthy" >&2
    fi

    sleep 5
done

# ---------------------------------------------------------------------------
# 6. Permissions
# ---------------------------------------------------------------------------

log 'Setting the storage permissions ...'

if [ -n "$PHP_SERVICE" ] || [ "$DRY_RUN" = '1' ]; then
    # The php-fpm worker runs as www-data (uid 33 in the Debian based image).
    run "${COMPOSE[@]}" exec -T app chown -R www-data:www-data storage
    run "${COMPOSE[@]}" exec -T app chmod -R u+rwX,g+rwX storage
fi

ok 'Permissions set.'

# ---------------------------------------------------------------------------
# 7. Smoke test
# ---------------------------------------------------------------------------

log 'Running the smoke test (health endpoint) ...'

if [ "$DRY_RUN" = '1' ]; then
    printf '   [dry-run] %s\n' 'curl to /index.php/health'
else
    PORT="$(grep -E '^APP_HTTP_PORT=' "$APP_PATH/.env" | cut -d= -f2 | tr -d '[:space:]')"
    PORT="${PORT:-8080}"

    RESPONSE="$(curl -fsS --max-time 15 "http://127.0.0.1:${PORT}/index.php/health" 2>&1 || true)"

    if [ -z "$RESPONSE" ]; then
        warn 'The health endpoint did not answer. Check: docker compose -f docker-compose.prod.yml logs web app'
    else
        printf '%s\n' "$RESPONSE"

        case "$RESPONSE" in
            *'"status":"ok"'*) ok 'The application reports a healthy state.' ;;
            *) warn 'The application reports a problem, see the JSON above.' ;;
        esac
    fi
fi

# ---------------------------------------------------------------------------
# 8. Migrations (only on request)
# ---------------------------------------------------------------------------

if [ "$WITH_MIGRATE" = '1' ]; then
    log 'Migrations were requested, checking the current version first ...'

    if [ "$DRY_RUN" != '1' ]; then
        read -r -p 'A fresh backup is strongly recommended. Continue? [y/N] ' answer
        [ "$answer" = 'y' ] || [ "$answer" = 'Y' ] || die 'Aborted before the migration.'

        if [ -x "$SCRIPT_DIR/backup.sh" ]; then
            log 'Running scripts/backup.sh first ...'
            bash "$SCRIPT_DIR/backup.sh" --database-only || die 'The backup failed, migration aborted.'
        else
            warn 'scripts/backup.sh is not executable, skipping the automatic backup.'
        fi
    fi

    run "${COMPOSE[@]}" exec -T app php index.php console migrate

    ok 'Migrations finished.'
fi

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

cat <<SUMMARY

$(printf '\033[1;32mDeployment finished.\033[0m')

Next steps on the server:

  1. Database migrations (when the code contains new ones):

       bash scripts/deploy.sh --with-migrate
       # or manually:
       docker compose -f docker-compose.prod.yml exec app php index.php console migrate

  2. Cron jobs (once per minute for the SMS queue and the booking holds):

       * * * * * cd $APP_PATH && docker compose -f docker-compose.prod.yml exec -T app php index.php sms_maintenance process_queue >/dev/null 2>&1
       * * * * * cd $APP_PATH && docker compose -f docker-compose.prod.yml exec -T app php index.php booking_maintenance release_expired_holds >/dev/null 2>&1
       17 4 * * * cd $APP_PATH && docker compose -f docker-compose.prod.yml exec -T app php index.php sms_maintenance cleanup_otp_codes >/dev/null 2>&1
       30 3 * * * cd $APP_PATH && bash scripts/backup.sh >> storage/logs/backup.log 2>&1

  3. Logs:

       docker compose -f docker-compose.prod.yml logs -f --tail=100 web app

  4. Roll back a bad deployment:

       bash scripts/rollback.sh

SUMMARY
