#!/usr/bin/env bash
#
# ---------------------------------------------------------------------------
# Backup of the Easy!Appointments installation (database + uploaded files).
#
# The database dump is written with "mysqldump --single-transaction", so the
# customers can keep booking while the backup runs. A second copy is written to
# the host directory BACKUP_DIR (default /var/backups/easyappointments), so that
# the backup survives the loss of the container and of the named volume.
#
# Usage:
#
#   bash scripts/backup.sh                     # database + files + retention
#   bash scripts/backup.sh --database-only     # only the database (fast)
#   bash scripts/backup.sh --keep 7            # override the retention
#   BACKUP_DIR=/mnt/backups bash scripts/backup.sh
#
# Exit codes:
#   0 - backup created (and verified)
#   1 - backup failed, nothing was written or the dump is empty
#   2 - the installation is not configured (.env or containers missing)
#
# Fork addition - not part of the upstream project (see CUSTOMIZATIONS.md).
# ---------------------------------------------------------------------------
set -Eeuo pipefail

. "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib/env.sh"

DATABASE_ONLY=0
KEEP_DAYS=""

while [ "$#" -gt 0 ]; do
    case "$1" in
        --database-only) DATABASE_ONLY=1 ;;
        --keep)
            shift
            KEEP_DAYS="${1:-}"
            ;;
        -h|--help)
            sed -n '2,25p' "$0"
            exit 0
            ;;
        *) ea_die "Unknown argument: $1" ;;
    esac

    shift
done

ea_load_env

BACKUP_DIR="$(ea_env BACKUP_DIR /var/backups/easyappointments)"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/easyappointments}"
KEEP_DAYS="${KEEP_DAYS:-$(ea_env BACKUP_RETENTION_DAYS 14)}"

STORAGE_BACKUP_DIR="$EA_APP_PATH/storage/backups"
STAMP="$(date +%Y%m%d-%H%M%S)"
DATABASE_FILE="ea-db-${STAMP}.sql"
FILES_FILE="ea-storage-${STAMP}.tar.gz"
CHECKSUM_FILE="ea-backup-${STAMP}.sha256"
LOG_PREFIX="[$(date '+%Y-%m-%d %H:%M:%S')] backup"

ea_compose_available || ea_die "The compose file is missing: $EA_COMPOSE_FILE"
ea_service_running db || ea_die 'The database container is not running. Start the stack first.'

mkdir -p "$STORAGE_BACKUP_DIR" "$BACKUP_DIR"

# The local copy is used by scripts/restore.sh and by the application itself,
# the second copy is the one that survives a failed disk.
ea_log "Writing the database dump to $STORAGE_BACKUP_DIR/$DATABASE_FILE ..."

ea_database_dump "$STORAGE_BACKUP_DIR/$DATABASE_FILE"

if [ ! -s "$STORAGE_BACKUP_DIR/$DATABASE_FILE" ]; then
    rm -f "$STORAGE_BACKUP_DIR/$DATABASE_FILE"
    ea_die 'The database dump is empty, the backup was aborted.'
fi

# A dump that does not contain the table structure of the application is not a
# usable backup. This check catches an empty or a wrong database early.
if ! grep -q 'CREATE TABLE.*appointments' "$STORAGE_BACKUP_DIR/$DATABASE_FILE"; then
    ea_warn 'The dump does not contain the "appointments" table, please check the database name.'
fi

cp -f "$STORAGE_BACKUP_DIR/$DATABASE_FILE" "$BACKUP_DIR/$DATABASE_FILE"

ea_ok "Database dump: $(du -h "$BACKUP_DIR/$DATABASE_FILE" | cut -f1)"

if [ "$DATABASE_ONLY" = '0' ]; then
    ea_log 'Archiving the uploaded files and the configuration (without .env) ...'

    FILES_LIST="storage/uploads config.php CUSTOMIZATIONS.md"

    # shellcheck disable=SC2086
    tar czf "$STORAGE_BACKUP_DIR/$FILES_FILE" -C "$EA_APP_PATH" \
        --ignore-failed-read $FILES_LIST 2>/dev/null || true

    if [ -s "$STORAGE_BACKUP_DIR/$FILES_FILE" ]; then
        cp -f "$STORAGE_BACKUP_DIR/$FILES_FILE" "$BACKUP_DIR/$FILES_FILE"
        ea_ok "Files archive: $(du -h "$BACKUP_DIR/$FILES_FILE" | cut -f1)"
    else
        ea_warn 'The files archive is empty, only the database was backed up.'
        rm -f "$STORAGE_BACKUP_DIR/$FILES_FILE" "$BACKUP_DIR/$FILES_FILE"
    fi
fi

# ---------------------------------------------------------------------------
# Checksums and retention
# ---------------------------------------------------------------------------

ea_log 'Writing the checksums ...'

(
    cd "$BACKUP_DIR"
    sha256sum "$DATABASE_FILE" > "$CHECKSUM_FILE"
    [ -f "$FILES_FILE" ] && sha256sum "$FILES_FILE" >> "$CHECKSUM_FILE"
) || true

cp -f "$BACKUP_DIR/$CHECKSUM_FILE" "$STORAGE_BACKUP_DIR/$CHECKSUM_FILE" 2>/dev/null || true

if [ "${KEEP_DAYS:-0}" -gt 0 ] 2>/dev/null; then
    ea_log "Removing backups older than $KEEP_DAYS days ..."

    find "$BACKUP_DIR" -maxdepth 1 -type f -name 'ea-*' -mtime "+$KEEP_DAYS" -print -delete || true
    find "$STORAGE_BACKUP_DIR" -maxdepth 1 -type f -name 'ea-*' -mtime "+$KEEP_DAYS" -print -delete || true
fi

ea_ok "Backup finished: $BACKUP_DIR"
echo "$LOG_PREFIX ok file=$BACKUP_DIR/$DATABASE_FILE"

# ---------------------------------------------------------------------------
# Optional: copy the backup to another machine
#
# A backup on the same server does not protect against the loss of the server.
# Set BACKUP_REMOTE (for example "user@backup-host:/srv/backups/nobat") in the
# .env file and the script will try to copy the files with rsync or scp.
# ---------------------------------------------------------------------------

BACKUP_REMOTE="$(ea_env BACKUP_REMOTE)"

if [ -n "$BACKUP_REMOTE" ]; then
    ea_log "Copying the backup to $BACKUP_REMOTE ..."

    if command -v rsync >/dev/null 2>&1; then
        rsync -az --partial "$BACKUP_DIR/" "$BACKUP_REMOTE/" && ea_ok 'Remote copy finished (rsync).'
    else
        scp "$BACKUP_DIR/ea-${STAMP}"* "$BACKUP_REMOTE/" && ea_ok 'Remote copy finished (scp).'
    fi
fi
