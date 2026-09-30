#!/usr/bin/env bash
#
# ---------------------------------------------------------------------------
# Shared helpers of the maintenance scripts (backup, restore, rollback).
#
# It reads the ".env" file of the installation and provides:
#
#   ea_load_env        - load .env into the environment (without overriding it)
#   ea_env KEY DEFAULT - read a single value
#   ea_log/ok/warn/die  - consistent output
#   ea_compose         - print the docker compose command of this installation
#   ea_mysql_dump/restore, ea_database_service - database helpers
#
# The file is meant to be sourced:  . "$(dirname "$0")/lib/env.sh"
#
# Fork addition - not part of the upstream project (see CUSTOMIZATIONS.md).
# ---------------------------------------------------------------------------

# shellcheck shell=bash

EA_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
EA_APP_PATH="${EA_APP_PATH:-$EA_SCRIPT_DIR}"
EA_COMPOSE_FILE="${EA_COMPOSE_FILE:-$EA_APP_PATH/docker-compose.prod.yml}"

ea_log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
ea_ok()   { printf '\033[1;32m ok\033[0m %s\n' "$*"; }
ea_warn() { printf '\033[1;33m[!]\033[0m %s\n' "$*" >&2; }
ea_die()  { printf '\033[1;31m[x]\033[0m %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Environment
# ---------------------------------------------------------------------------

ea_load_env() {
    local env_file="${1:-$EA_APP_PATH/.env}"

    [ -f "$env_file" ] || ea_die "The .env file is missing: $env_file"

    # The file is written in the KEY=value format, so it can be sourced. Values
    # that are already set in the process environment win (docker, systemd).
    while IFS= read -r line || [ -n "$line" ]; do
        case "$line" in
            ''|'#'*) continue ;;
        esac

        line="${line#export }"

        case "$line" in
            *=*) ;;
            *) continue ;;
        esac

        local key="${line%%=*}"
        local value="${line#*=}"

        key="$(printf '%s' "$key" | tr -d '[:space:]')"

        # Strip one pair of matching quotes.
        case "$value" in
            \"*\") value="${value#\"}"; value="${value%\"}" ;;
            \'*\') value="${value#\'}"; value="${value%\'}" ;;
        esac

        # Do not override values that the environment already provides.
        if [ -z "${!key:-}" ]; then
            export "$key=$value"
        fi
    done < "$env_file"

    EA_APP_PATH="${EA_APP_PATH:-$(pwd)}"

    export EA_APP_PATH
}

ea_env() {
    local key="$1"
    local default="${2:-}"

    local value="${!key:-}"

    printf '%s' "${value:-$default}"
}

# ---------------------------------------------------------------------------
# Docker
# ---------------------------------------------------------------------------

ea_compose() {
    if docker compose version >/dev/null 2>&1; then
        docker compose -f "$EA_COMPOSE_FILE" "$@"
    elif command -v docker-compose >/dev/null 2>&1; then
        docker-compose -f "$EA_COMPOSE_FILE" "$@"
    else
        ea_die 'Neither "docker compose" nor "docker-compose" is available.'
    fi
}

ea_compose_available() {
    [ -f "$EA_COMPOSE_FILE" ] && { docker compose version >/dev/null 2>&1 || command -v docker-compose >/dev/null 2>&1; }
}

ea_service_running() {
    [ -n "$(ea_compose ps -q "$1" 2>/dev/null || true)" ]
}

# ---------------------------------------------------------------------------
# Database
# ---------------------------------------------------------------------------

ea_database_service() {
    printf 'db'
}

ea_mysql_root_command() {
    # Use the MYSQL_PWD variable instead of -p so that the password is not part
    # of the process list (docker exec shows the arguments to other users).
    local password="$1"
    shift

    ea_compose exec -T -e MYSQL_PWD="$password" "$(ea_database_service)" "$@"
}

ea_database_dump() {
    local target_file="$1"
    local root_password
    root_password="$(ea_env MYSQL_ROOT_PASSWORD)"

    [ -n "$root_password" ] || ea_die 'MYSQL_ROOT_PASSWORD is empty in .env.'

    local database
    database="$(ea_env DB_NAME easyappointments)"

    # --single-transaction keeps InnoDB consistent without locking the tables,
    # so the customers can keep booking while the backup runs.
    ea_mysql_root_command "$root_password" \
        mysqldump \
        --single-transaction \
        --quick \
        --routines \
        --triggers \
        --events \
        --default-character-set=utf8mb4 \
        --databases "$database" \
        > "$target_file"
}

ea_database_restore() {
    local source_file="$1"
    local root_password
    root_password="$(ea_env MYSQL_ROOT_PASSWORD)"

    [ -n "$root_password" ] || ea_die 'MYSQL_ROOT_PASSWORD is empty in .env.'

    ea_mysql_root_command "$root_password" mysql < "$source_file"
}

ea_database_exists() {
    local root_password
    root_password="$(ea_env MYSQL_ROOT_PASSWORD)"

    local database
    database="$(ea_env DB_NAME easyappointments)"

    local count
    count="$(ea_mysql_root_command "$root_password" \
        mysql --skip-column-names -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$database';" \
        2>/dev/null | tr -d '[:space:]')"

    [ "$count" = '1' ]
}

ea_database_app_check() {
    # Verify that the application credentials work and that the installation is
    # complete (the migrations table of CodeIgniter must exist).
    local output
    output="$(ea_compose exec -T app php -r '
        require "/var/www/html/config.php";
        $dsn = "mysql:host=" . Config::DB_HOST . ";dbname=" . Config::DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, Config::DB_USERNAME, Config::DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $version = $pdo->query("SELECT version FROM " . "migrations ORDER BY version DESC LIMIT 1")->fetchColumn();
        echo "db-ok migration=" . ($version === false ? "0" : $version);
    ' 2>&1 || true)"

    case "$output" in
        *db-ok*) return 0 ;;
        *) ea_warn "The application database check failed: $output"; return 1 ;;
    esac
}
