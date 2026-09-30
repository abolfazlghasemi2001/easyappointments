#!/usr/bin/env bash
#
# ---------------------------------------------------------------------------
# Roll back a deployment of the Easy!Appointments fork.
#
# Two independent things can go wrong and this script handles both:
#
#   A) the code is broken (a PHP error, a white page)
#      -> go back to the previous commit ("git checkout <commit>") or to the
#         previously built docker image, then restart the containers.
#
#   B) a migration changed the database in a way the old code cannot handle
#      -> restore the pre-restore/pre-deploy database dump (see restore.sh) and
#         then go back to the previous commit.
#
# The script never deletes a backup and never runs "git reset --hard" without
# asking. The database rollback is only performed when it is explicitly
# requested with --restore-database, because it discards the data that was
# created after the backup was taken.
#
# Usage:
#
#   bash scripts/rollback.sh --list                 # what can be rolled back
#   bash scripts/rollback.sh --code                 # previous commit + restart
#   bash scripts/rollback.sh --code --commit <sha>  # a specific commit
#   bash scripts/rollback.sh --database FILE --yes  # delegated to restore.sh
#
# Exit codes:
#   0 - rollback finished
#   1 - rollback failed
#   2 - invalid arguments / not a git checkout
#
# Fork addition - not part of the upstream project (see CUSTOMIZATIONS.md).
# ---------------------------------------------------------------------------
set -Eeuo pipefail

. "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib/env.sh"

MODE=""
TARGET_COMMIT=""
DATABASE_FILE=""
ASSUME_YES=0

while [ "$#" -gt 0 ]; do
    case "$1" in
        --list) MODE="list" ;;
        --code) MODE="${MODE:-code}" ;;
        --commit)
            shift
            TARGET_COMMIT="${1:-}"
            MODE="${MODE:-code}"
            ;;
        --database)
            shift
            DATABASE_FILE="${1:-}"
            MODE="database"
            ;;
        --yes|-y) ASSUME_YES=1 ;;
        -h|--help)
            sed -n '2,30p' "$0"
            exit 0
            ;;
        *) ea_die "Unknown argument: $1" ;;
    esac

    shift
done

[ -n "$MODE" ] || MODE="list"

ea_load_env

BACKUP_DIR="$(ea_env BACKUP_DIR /var/backups/easyappointments)"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/easyappointments}"

# ---------------------------------------------------------------------------
# Overview
# ---------------------------------------------------------------------------

if [ "$MODE" = 'list' ]; then
    ea_log "Installation : $EA_APP_PATH"

    if [ -d "$EA_APP_PATH/.git" ]; then
        ea_log "Current code : $(git -C "$EA_APP_PATH" rev-parse --short HEAD) ($(git -C "$EA_APP_PATH" log -1 --format=%s))"
        ea_log 'Previous commits:'
        git -C "$EA_APP_PATH" log -5 --format='   %h %ad %s' --date=short
    else
        ea_warn 'Not a git checkout, the code cannot be rolled back with git.'
    fi

    ea_log "Database backups in $BACKUP_DIR:"
    ls -lht "$BACKUP_DIR"/ea-db-* 2>/dev/null | head -10 || ea_warn 'No database backup found.'

    ea_log 'Docker images of the application:'
    docker images --format '   {{.Repository}}:{{.Tag}} {{.CreatedSince}} {{.Size}}' 2>/dev/null \
        | grep -E 'easyappointments|nobat' | head -10 || true

    cat <<'HINT'

Roll back the code:      bash scripts/rollback.sh --code
Roll back the database:  bash scripts/rollback.sh --database /var/backups/.../ea-db-<stamp>.sql --yes
HINT

    exit 0
fi

# ---------------------------------------------------------------------------
# Database rollback (delegated, it needs the same confirmations)
# ---------------------------------------------------------------------------

if [ "$MODE" = 'database' ]; then
    [ -n "$DATABASE_FILE" ] || ea_die 'Use --database FILE (see --list).'

    ea_log "Delegating the database rollback to scripts/restore.sh ..."

    ARGS=(--database "$DATABASE_FILE")

    [ "$ASSUME_YES" = '1' ] && ARGS+=(--yes)

    exec bash "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/restore.sh" "${ARGS[@]}"
fi

# ---------------------------------------------------------------------------
# Code rollback
# ---------------------------------------------------------------------------

[ -d "$EA_APP_PATH/.git" ] || ea_die 'The installation is not a git checkout, use --database to restore the data.'

cd "$EA_APP_PATH"

CURRENT_COMMIT="$(git rev-parse HEAD)"
CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"

if [ -z "$TARGET_COMMIT" ]; then
    TARGET_COMMIT="$(git rev-parse HEAD~1)"

    ea_log "Rolling back to the previous commit."
else
    git rev-parse --verify --quiet "$TARGET_COMMIT" >/dev/null || ea_die "Unknown commit: $TARGET_COMMIT"
fi

ea_warn "Current code : $CURRENT_COMMIT ($CURRENT_BRANCH)"
ea_warn "Roll back to : $TARGET_COMMIT ($(git log -1 --format=%s "$TARGET_COMMIT"))"

if [ -n "$(git status --porcelain)" ]; then
    ea_warn 'The working tree has local changes. They are kept but may conflict.'
fi

if [ "$ASSUME_YES" != '1' ]; then
    printf 'Type "rollback" to continue: '
    read -r answer

    [ "$answer" = 'rollback' ] || ea_die 'Aborted, nothing was changed.'
fi

# A detached HEAD is intentional here: the branch keeps its history and a later
# "git merge --ff-only origin/main" (scripts/deploy.sh) brings the code forward
# again. Never force-push anything.
ea_log 'Checking out the previous commit (detached HEAD, the branch is not rewritten) ...'
git checkout --quiet "$TARGET_COMMIT"
ea_ok "Now at $(git rev-parse --short HEAD)."

if ea_compose_available && ea_service_running app; then
    ea_log 'Reinstalling the dependencies of the old version ...'
    ea_compose exec -T app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

    ea_log 'Rebuilding the front-end assets of the old version ...'
    ea_compose exec -T app npx --yes gulp compile

    ea_log 'Restarting the containers ...'
    ea_compose restart app web
    ea_ok 'Containers restarted.'
else
    ea_warn 'The application container is not running, start the stack manually:'
    ea_warn "  docker compose -f $EA_COMPOSE_FILE up -d --remove-orphans"
fi

# ---------------------------------------------------------------------------
# Verification
# ---------------------------------------------------------------------------

ea_log 'Verifying the rolled back installation ...'

PORT="$(ea_env APP_HTTP_PORT 8080)"

if RESPONSE="$(curl -fsS --max-time 15 "http://127.0.0.1:${PORT}/index.php/health" 2>&1)"; then
    printf '%s\n' "$RESPONSE"

    case "$RESPONSE" in
        *'"status":"ok"'*) ea_ok 'The application answers with a healthy state.' ;;
        *) ea_warn 'The application reports a problem, see the JSON above.' ;;
    esac
else
    ea_warn "The health endpoint did not answer: $RESPONSE"
    ea_warn "Check the logs: docker compose -f $EA_COMPOSE_FILE logs --tail=100 app web"
fi

cat <<SUMMARY

$(printf '\033[1;32mCode rollback finished.\033[0m')

  previous code : $CURRENT_COMMIT
  current code  : $TARGET_COMMIT

To go back to the newest version once the problem is solved:

  git checkout $CURRENT_BRANCH && git merge --ff-only origin/$CURRENT_BRANCH

If the problem was a database migration, restore the dump of the previous
version as well (this discards the data created after that dump):

  bash scripts/rollback.sh --database $BACKUP_DIR/ea-db-<stamp>.sql --yes

SUMMARY
