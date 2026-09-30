#!/usr/bin/env bash
#
# ---------------------------------------------------------------------------
# Restore of an Easy!Appointments backup (see scripts/backup.sh).
#
# This script DESTROYS the current database of the installation. It therefore:
#
#   1. requires the "--yes" flag (or an interactive confirmation),
#   2. saves the database that is about to be replaced ("pre-restore" dump),
#   3. verifies the checksum of the backup when a .sha256 file is present,
#   4. stops the application containers while the data is replaced,
#   5. checks afterwards that the application can read the restored database.
#
# Usage:
#
#   ls /var/backups/easyappointments                       # pick a file
#   bash scripts/restore.sh --list
#   bash scripts/restore.sh --database /var/backups/easyappointments/ea-db-20260930-033000.sql --yes
#   bash scripts/restore.sh --files /var/backups/easyappointments/ea-storage-20260930-033000.tar.gz --yes
#
# Exit codes:
#   0 - restore finished and verified
#   1 - restore failed (the pre-restore dump is kept)
#   2 - the installation is not configured, or the arguments are invalid
#
# Fork addition - not part of the upstream project (see CUSTOMIZATIONS.md).
# ---------------------------------------------------------------------------
set -Eeuo pipefail

. "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib/env.sh"

DATABASE_FILE=""
FILES_FILE=""
ASSUME_YES=0
LIST_ONLY=0

while [ "$#" -gt 0 ]; do
    case "$1" in
        --database)
            shift
            DATABASE_FILE="${1:-}"
            ;;
        --files)
            shift
            FILES_FILE="${1:-}"
            ;;
        --yes|-y) ASSUME_YES=1 ;;
        --list) LIST_ONLY=1 ;;
        -h|--help)
            sed -n '2,30p' "$0"
            exit 0
            ;;
        *) ea_die "Unknown argument: $1" ;;
    esac

    shift
done

ea_load_env

BACKUP_DIR="$(ea_env BACKUP_DIR /var/backups/easyappointments)"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/easyappointments}"

if [ "$LIST_ONLY" = '1' ]; then
    ea_log "Available backups in $BACKUP_DIR:"
    ls -lh "$BACKUP_DIR"/ea-* 2>/dev/null || ea_warn 'No backup found.'
    exit 0
fi

[ -n "$DATABASE_FILE" ] || [ -n "$FILES_FILE" ] || ea_die 'Nothing to restore. Use --database FILE and/or --files FILE.'

[ -f "$DATABASE_FILE" ] || ea_die "The database backup does not exist: $DATABASE_FILE"

if [ -n "$FILES_FILE" ]; then
    [ -f "$FILES_FILE" ] || ea_die "The files archive does not exist: $FILES_FILE"
fi

ea_compose_available || ea_die "The compose file is missing: $EA_COMPOSE_FILE"
ea_service_running db || ea_die 'The database container is not running. Start the stack first.'

# ---------------------------------------------------------------------------
# 1. Confirmation
# ---------------------------------------------------------------------------

ea_warn 'This will REPLACE the current database of the installation.'
ea_warn "  installation : $EA_APP_PATH"
ea_warn "  database     : $(ea_env DB_NAME easyappointments)"
ea_warn "  source file  : $DATABASE_FILE"

if [ "$ASSUME_YES" != '1' ]; then
    printf 'Type "restore" to continue: '
    read -r answer

    [ "$answer" = 'restore' ] || ea_die 'Aborted, nothing was changed.'
fi

# ---------------------------------------------------------------------------
# 2. Checksum
# ---------------------------------------------------------------------------

CHECKSUM_FILE="$(dirname "$DATABASE_FILE")/ea-backup-$(basename "$DATABASE_FILE" | sed -E 's/^ea-db-([0-9]{8}-[0-9]{6})\.sql$/\1/').sha256"

if [ -f "$CHECKSUM_FILE" ]; then
    ea_log 'Verifying the checksums ...'

    if (cd "$(dirname "$CHECKSUM_FILE")" && sha256sum -c "$(basename "$CHECKSUM_FILE")" >/dev/null 2>&1); then
        ea_ok 'Checksums are valid.'
    else
        ea_die "The checksum of the backup does not match ($CHECKSUM_FILE). Refusing to restore."
    fi
else
    ea_warn 'No checksum file next to the backup, the integrity cannot be verified.'
fi

# ---------------------------------------------------------------------------
# 3. Pre-restore safety dump
# ---------------------------------------------------------------------------

STAMP="$(date +%Y%m%d-%H%M%S)"
PRE_RESTORE_FILE="$BACKUP_DIR/ea-db-pre-restore-${STAMP}.sql"

ea_log "Saving the current database to $PRE_RESTORE_FILE ..."

if ea_database_dump "$PRE_RESTORE_FILE"; then
    ea_ok "Pre-restore dump: $(du -h "$PRE_RESTORE_FILE" | cut -f1)"
else
    ea_warn 'The current database could not be dumped.'
    printf 'Continue anyway? Type "yes": '
    read -r answer

    [ "$answer" = 'yes' ] || ea_die 'Aborted, nothing was changed.'
fi

# ---------------------------------------------------------------------------
# 4. Restore
# ---------------------------------------------------------------------------

ea_log 'Stopping the application containers (the web interface is offline for a moment) ...'
ea_compose stop web app

ea_log 'Restoring the database ...'
ea_database_restore "$DATABASE_FILE"
ea_ok 'Database restored.'

if [ -n "$FILES_FILE" ]; then
    ea_log 'Restoring the uploaded files and the configuration ...'

    tar xzf "$FILES_FILE" -C "$EA_APP_PATH"

    ea_ok 'Files restored.'
fi

ea_log 'Starting the application containers ...'
ea_compose up -d db web app

ea_log 'Waiting for the database to accept connections ...'

for attempt in $(seq 1 30); do
    if ea_database_exists; then
        break
    fi

    sleep 2
done

# ---------------------------------------------------------------------------
# 5. Verification
# ---------------------------------------------------------------------------

ea_log 'Verifying the restored installation ...'

if ea_database_app_check; then
    ea_ok 'The application can read the restored database.'
else
    ea_die "The application cannot read the restored database. The pre-restore dump is at $PRE_RESTORE_FILE"
fi

cat <<SUMMARY

$(printf '\033[1;32mRestore finished.\033[0m')

  restored database : $DATABASE_FILE
  previous database : $PRE_RESTORE_FILE   (delete it once you are sure)

Next steps:

  1. Open the application and check the calendar of the next few days.
  2. Check the last login and the newest appointment.
  3. Check the applied migration version:
       docker compose -f docker-compose.prod.yml exec app php index.php console migrate

SUMMARY
