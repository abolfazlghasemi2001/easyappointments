#!/usr/bin/env bash
#
# server-audit.sh — Read-only baseline audit for the Easy!Appointments deployment.
#
# WHAT IT DOES
#   Collects the state of the server (OS, Docker, ports, firewall, PHP, database, application and
#   web-server configuration, HTTP exposure of sensitive paths, backups, cron) so that the deployment
#   can be assessed without guessing.
#
# WHAT IT DOES *NOT* DO  (strictly read-only)
#   * No file is created, modified or deleted in the project directory.
#   * No container is started, stopped, created or removed.
#   * No `systemctl start|stop|restart|enable|disable`.
#   * No migration, no seed, no install, no backup command is executed.
#   * No password, secret, token or API key is printed: values matching
#     password/secret/token/key are masked and the contents of config.php are never dumped.
#
# USAGE
#   bash server-audit.sh | tee /tmp/server-audit-report.txt
#   # optional: APP_PATH=/var/www/easyappointments APP_PORT=9090 bash server-audit.sh
#
# After the run: open the report, redact anything you consider private, and send it back.
#

set -uo pipefail

APP_PATH="${APP_PATH:-/var/www/easyappointments}"
APP_PORT="${APP_PORT:-9090}"
PMA_PORT="${PMA_PORT:-9091}"

# ---------------------------------------------------------------------------
# helpers
# ---------------------------------------------------------------------------
HDR() { printf '\n\n=======================================================================\n== %s\n=======================================================================\n' "$*"; }
SUB() { printf '\n--- %s\n' "$*"; }
NOTE() { printf '    [i] %s\n' "$*"; }

# Run a command, print it, run it, ignore failures (audit must never abort).
run() {
    printf '$ %s\n' "$*"
    "$@" 2>&1 || printf '    (exit code %s)\n' "$?"
}

have() { command -v "$1" >/dev/null 2>&1; }

# Mask anything that looks like a credential in the whole report.
mask() {
    sed -E \
        -e 's/([Pp]assword|PASSWORD)([=:][[:space:]]*)[^[:space:]"'"'"']+/\1\2***MASKED***/g' \
        -e 's/([Ss]ecret|SECRET)([=:][[:space:]]*)[^[:space:]"'"'"']+/\1\2***MASKED***/g' \
        -e 's/([Tt]oken|TOKEN|api_key|API_KEY|apikey)([=:][[:space:]]*)[^[:space:]"'"'"']+/\1\2***MASKED***/g' \
        -e 's/(user|username|user_name):[^@[:space:]]*@/user:***MASKED***@/g'
}

# docker, with a sudo fallback
DOCKER="docker"
if ! docker ps >/dev/null 2>&1; then
    if have sudo && sudo -n docker ps >/dev/null 2>&1; then
        DOCKER="sudo docker"
    fi
fi

d() { $DOCKER "$@"; }

APP_NAME="$(basename "$APP_PATH")"

# ---------------------------------------------------------------------------
{
HDR "Easy!Appointments — read-only server audit"
printf 'date (UTC)      : %s\n' "$(date -u '+%Y-%m-%d %H:%M:%S')"
printf 'date (local)    : %s\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')"
printf 'hostname        : %s\n' "$(hostname)"
printf 'run as user     : %s (uid=%s)\n' "$(id -un)" "$(id -u)"
printf 'sudo available  : %s\n' "$(have sudo && echo yes || echo no)"
printf 'app path        : %s\n' "$APP_PATH"
printf 'app port        : %s\n' "$APP_PORT"
printf 'docker command  : %s\n' "$DOCKER"

# ---------------------------------------------------------------------------
HDR "1. System"
SUB "OS / kernel"
run cat /etc/os-release
run uname -a
SUB "CPU / memory / swap / disk"
run nproc
run free -h
run df -h
SUB "Load average and uptime"
run uptime
SUB "Swap status"
if have swapon; then run swapon --show; else NOTE "swapon not available"; fi
SUB "Top memory consumers (informational)"
run sh -c 'ps -eo pid,ppid,rss,comm --sort=-rss | head -15'

# ---------------------------------------------------------------------------
HDR "2. Docker"
SUB "Version"
run $DOCKER version
run docker-compose version
SUB "Running containers (names, ports, status, image)"
run $DOCKER ps --format 'table {{.Names}}\t{{.Image}}\t{{.Ports}}\t{{.Status}}'
SUB "All containers including stopped (names, state)"
run $DOCKER ps -a --format 'table {{.Names}}\t{{.State}}\t{{.Status}}'
SUB "Restart policies (no env values are printed)"
run sh -c "$DOCKER ps -aq | xargs -r $DOCKER inspect --format '{{.Name}} restart={{.HostConfig.RestartPolicy.Name}} binds={{range .HostConfig.Binds}}{{.}} {{end}}'"
SUB "Compose services (names only)"
if [ -f "$APP_PATH/docker-compose.yml" ]; then
    if docker-compose -f "$APP_PATH/docker-compose.yml" config --services >/tmp/.ea_svc 2>/dev/null; then
        run cat /tmp/.ea_svc
    else
        NOTE "docker-compose config --services failed (old compose v1 or missing env); service names from the file:"
        run grep -E '^  [a-zA-Z0-9_-]+:' "$APP_PATH/docker-compose.yml"
    fi
else
    NOTE "no docker-compose.yml at $APP_PATH"
fi
SUB "Images"
run $DOCKER images --format 'table {{.Repository}}\t{{.Tag}}\t{{.Size}}\t{{.CreatedSince}}'
SUB "Disk usage"
run $DOCKER system df

# ---------------------------------------------------------------------------
HDR "3. Listening ports and firewall"
SUB "TCP listeners (ss)"
if have ss; then
    if [ "$(id -u)" = "0" ]; then run ss -tlnp; else run sudo -n ss -tlnp || run ss -tln; fi
else
    run netstat -tlnp
fi
SUB "UDP listeners"
if have ss; then run ss -uln; else run netstat -uln; fi
SUB "ufw"
if have ufw; then run sudo -n ufw status verbose || NOTE "ufw needs root; run: sudo ufw status verbose"; else NOTE "ufw not installed"; fi
SUB "iptables (filter table, rules only)"
if have iptables; then run sudo -n iptables -S || NOTE "iptables needs root"; else NOTE "iptables not installed"; fi
SUB "fail2ban"
if have fail2ban-client; then run sudo -n fail2ban-client status || NOTE "fail2ban needs root"; else NOTE "fail2ban not installed"; fi

# ---------------------------------------------------------------------------
HDR "4. Application files"
SUB "Repository state (deployed commit and local drift)"
if [ -d "$APP_PATH/.git" ]; then
    run git -C "$APP_PATH" rev-parse --abbrev-ref HEAD
    run git -C "$APP_PATH" log -1 --format='%H %ad %s' --date=iso
    run git -C "$APP_PATH" remote -v
    run git -C "$APP_PATH" status --porcelain=v1
    NOTE "modified/untracked files above = configuration drift versus git"
else
    NOTE "$APP_PATH is not a git checkout"
fi
SUB "Root listing and hidden files"
run ls -la "$APP_PATH"
SUB "Presence of sensitive/expected files (names only, no contents)"
for f in config.php config-sample.php .env .env.example .htaccess vendor/autoload.php node_modules assets/css/backend.min.css assets/js/app.min.js openapi.yml; do
    if [ -e "$APP_PATH/$f" ]; then
        printf '     present : %s\n' "$f"
    else
        printf '     MISSING : %s\n' "$f"
    fi
done
SUB "config.php permissions and setting keys (VALUES ARE NOT PRINTED)"
if [ -f "$APP_PATH/config.php" ]; then
    run ls -l "$APP_PATH/config.php"
    run stat -c 'mode=%A owner=%U:%G' "$APP_PATH/config.php"
    printf '$ grep -c for key presence in config.php\n'
    for k in BASE_URL LANGUAGE DEBUG_MODE DB_HOST DB_NAME DB_USERNAME DB_PASSWORD GOOGLE_SYNC_FEATURE ENCRYPTION_KEY CORS_ALLOWED_ORIGINS; do
        printf '     %-22s : %s\n' "$k" "$(grep -c "const[[:space:]]\+$k" "$APP_PATH/config.php" 2>/dev/null)"
    done
    printf '     DEBUG_MODE value       : %s\n' "$(grep -oE "DEBUG_MODE[[:space:]]*=[[:space:]]*(true|TRUE|false|FALSE)" "$APP_PATH/config.php" | head -1)"
    printf '     BASE_URL value         : %s\n' "$(grep -oE "BASE_URL[[:space:]]*=[[:space:]]*'[^']*'" "$APP_PATH/config.php" | head -1 | sed -E "s/.*=\s*'(.*)'/\1/")"
    printf '     LANGUAGE value         : %s\n' "$(grep -oE "LANGUAGE[[:space:]]*=[[:space:]]*'[^']*'" "$APP_PATH/config.php" | head -1 | sed -E "s/.*=\s*'(.*)'/\1/")"
else
    NOTE "config.php not found — is the application installed?"
fi
SUB "storage/ permissions"
run ls -la "$APP_PATH/storage" 2>/dev/null
for sub in cache logs sessions backups uploads; do
    [ -d "$APP_PATH/storage/$sub" ] && run ls -la "$APP_PATH/storage/$sub"
done
SUB "Existing database backups (names and dates)"
run ls -lah "$APP_PATH/storage/backups" 2>/dev/null
SUB "Application log files (names and sizes only)"
run ls -lah "$APP_PATH/storage/logs" 2>/dev/null
SUB "Last 30 lines of the newest application log (may contain e-mail addresses)"
newest_log="$(ls -1t "$APP_PATH"/storage/logs/*.php 2>/dev/null | head -1)"
if [ -n "${newest_log:-}" ]; then run tail -n 30 "$newest_log"; else NOTE "no application log file found"; fi

# ---------------------------------------------------------------------------
HDR "5. HTTP exposure of sensitive paths (requested from the server itself)"
SUB "Status codes — expected: 403/404 for everything except / and /index.php/*"
paths="
/ 
/index.php/login
/logo.png
/config.php
/config-sample.php
/.env
/.git/config
/storage/
/storage/backups/
/storage/logs/
/storage/sessions/
/storage/cache/
/application/
/application/config/config.php
/application/config/database.php
/application/language/persian/translations_lang.php
/system/
/system/core/CodeIgniter.php
/docs/readme.md
/dev/sandbox/setup.sh
/tests/TestCase.php
/openapi.yml
/robots.txt
"
for p in $paths; do
    code="$(curl -s -o /dev/null -m 8 -w '%{http_code}' "http://127.0.0.1:${APP_PORT}${p}" 2>/dev/null)"
    size="$(curl -s -o /dev/null -m 8 -w '%{size_download}' "http://127.0.0.1:${APP_PORT}${p}" 2>/dev/null)"
    printf '     %-58s -> %s (%s bytes)\n' "$p" "${code:-ERR}" "${size:-0}"
done
SUB "Concrete existing files (the real test — a directory 403 means nothing if a file leaks)"
firstfile() { find "$1" -maxdepth 1 -type f ! -name 'index.html' ! -name '.htaccess' 2>/dev/null | head -1; }
for dir in storage/backups storage/logs storage/sessions storage/cache; do
    f="$(firstfile "$APP_PATH/$dir")"
    if [ -n "${f:-}" ]; then
        rel="${f#"$APP_PATH"}"
        code="$(curl -s -o /dev/null -m 8 -w '%{http_code}' "http://127.0.0.1:${APP_PORT}${rel}" 2>/dev/null)"
        printf '     %-58s -> %s   <== MUST NOT BE 200\n' "$rel" "${code:-ERR}"
    else
        printf '     %-58s -> (no file present)\n' "$APP_PATH/$dir/*"
    fi
done
SUB "Response headers of the booking page"
run curl -s -D - -o /dev/null -m 8 "http://127.0.0.1:${APP_PORT}/"
SUB "HTTPS check"
run curl -sI -k -m 8 "https://127.0.0.1/"
run curl -sI -m 8 "http://127.0.0.1/"
SUB "phpMyAdmin exposure (must be 127.0.0.1 only)"
run curl -s -o /dev/null -m 8 -w 'http://127.0.0.1:%{http_code}\n' "http://127.0.0.1:${PMA_PORT}/"

# ---------------------------------------------------------------------------
HDR "6. Web server configuration (credentials masked)"
NGINX_C="$(d ps --format '{{.Names}}' | grep -iE 'nginx|web' | head -1)"
if [ -n "${NGINX_C:-}" ]; then
    SUB "nginx container: $NGINX_C"
    run d exec "$NGINX_C" sh -c 'nginx -v'
    SUB "effective nginx configuration (/etc/nginx/conf.d/default.conf)"
    run d exec "$NGINX_C" sh -c 'cat /etc/nginx/conf.d/default.conf 2>/dev/null || cat /etc/nginx/conf.d/*.conf'
    SUB "nginx config test (read-only, does not reload)"
    run d exec "$NGINX_C" sh -c 'nginx -t'
else
    NOTE "No nginx container found. Checking for a host nginx:"
    if have nginx; then
        run nginx -v
        run ls -la /etc/nginx/sites-enabled 2>/dev/null
        if [ "$(id -u)" = "0" ]; then run cat /etc/nginx/sites-enabled/*; else NOTE "run as root to print /etc/nginx/sites-enabled/*"; fi
    else
        NOTE "nginx not found on the host either"
    fi
fi
SUB "caddy / apache / traefik presence (in case TLS is terminated elsewhere)"
for b in caddy apache2 httpd traefik; do
    if have "$b"; then printf '     %s : present\n' "$b"; else printf '     %s : absent\n' "$b"; fi
done

# ---------------------------------------------------------------------------
HDR "7. PHP runtime (inside the application container)"
PHP_C="$(d ps --format '{{.Names}}' | grep -iE 'php' | head -1)"
if [ -n "${PHP_C:-}" ]; then
    run d exec "$PHP_C" php -v
    SUB "INI settings that matter (secrets are not printed)"
    run d exec "$PHP_C" sh -c 'php -i | grep -Ei "^(display_errors|error_reporting|variables_order|expose_php|upload_max_filesize|post_max_size|memory_limit|max_execution_time|date.timezone|session.gc_maxlifetime|session.cookie_secure|session.cookie_httponly|opcache.enable|allow_url_fopen|disable_functions|open_basedir) "'
    SUB "Loaded PHP extensions"
    run d exec "$PHP_C" sh -c 'php -m | sort | tr "\n" " " ; echo'
    SUB "PHP-FPM pool (listen address and user)"
    run d exec "$PHP_C" sh -c 'grep -E "^(listen|user|group|pm|pm.max_children)" /usr/local/etc/php-fpm.d/*.conf 2>/dev/null || echo "no pool config matched"'
else
    NOTE "No PHP container found"
    if have php; then run php -v; run php -i | grep -Ei '^(display_errors|variables_order|memory_limit) '; fi
fi

# ---------------------------------------------------------------------------
HDR "8. Database (read-only queries, no credentials printed)"
DB_OK=0
MYSQL_C="$(d ps --format '{{.Names}}' | grep -iE 'mysql|mariadb|db' | head -1)"
if [ -n "${MYSQL_C:-}" ]; then
    SUB "MySQL container: $MYSQL_C"
    run d exec "$MYSQL_C" sh -c 'mysql --version'
    Q="SELECT VERSION() AS version; SHOW DATABASES; SELECT table_schema, COUNT(*) AS tables, ROUND(SUM(data_length+index_length)/1024/1024,1) AS mb FROM information_schema.tables WHERE table_schema NOT IN ('mysql','information_schema','performance_schema','sys') GROUP BY table_schema;"
    if d exec "$MYSQL_C" sh -c "mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -t -e \"$Q\"" 2>&1; then DB_OK=1; fi
    SUB "Easy!Appointments schema (tables and row counts)"
    Q2="SELECT version AS migration_version FROM easyappointments.ea_migrations; SELECT COUNT(*) AS users FROM easyappointments.ea_users; SELECT id, name, slug, is_admin FROM easyappointments.ea_roles; SELECT COUNT(*) AS appointments FROM easyappointments.ea_appointments; SELECT MIN(start_datetime) AS first_appointment, MAX(start_datetime) AS last_appointment FROM easyappointments.ea_appointments; SELECT COUNT(*) AS holidays FROM easyappointments.ea_holidays;"
    if d exec "$MYSQL_C" sh -c "mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -t -e \"$Q2\"" 2>&1; then DB_OK=1; fi
    SUB "Which admin/provider/customer accounts exist (no e-mail addresses printed)"
    Q3="SELECT u.id, u.username, r.slug AS role, u.is_private FROM easyappointments.ea_users u LEFT JOIN easyappointments.ea_roles r ON r.id = u.id_roles;"
    run d exec "$MYSQL_C" sh -c "mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -t -e \"$Q3\""
    SUB "MySQL users and grants over TCP (who may connect from where)"
    run d exec "$MYSQL_C" sh -c "mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -t -e \"SELECT user, host, plugin FROM mysql.user;\""
    SUB "Key Easy!Appointments settings (whitelist only — no passwords/tokens are printed)"
    Q4="SELECT name, LEFT(value, 60) AS value_excerpt FROM easyappointments.ea_settings WHERE name IN ('company_name','company_email','date_format','time_format','book_advance_timeout','future_booking_limit','appointment_status_options','calendar_type','persian_digits','display_timezone','default_language','first_weekday','disable_booking','require_captcha','altcha_enabled','slot_interval','theme','default_timezone');"
    run d exec "$MYSQL_C" sh -c "mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -t -e \"$Q4\""
    SUB "Credential-like setting NAMES present (values intentionally hidden)"
    run d exec "$MYSQL_C" sh -c "mysql -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -N -B -e \"SELECT name FROM easyappointments.ea_settings WHERE name REGEXP 'pass|token|secret|key|smtp|ldap|matomo|google|api';\""
fi
SUB "System MySQL / MariaDB (127.0.0.1:3306)"
if have mysql; then
    run mysql --version
    NOTE "run manually if needed: sudo mysql -e 'SHOW DATABASES;'"
else
    NOTE "mysql client not installed on the host"
fi
if [ "$DB_OK" = "0" ]; then NOTE "Could not query the database automatically — share the manual output of: docker exec <mysql> mysql -uroot -p -e 'SHOW DATABASES;'"; fi

# ---------------------------------------------------------------------------
HDR "9. Scheduled jobs and maintenance"
SUB "crontab of the current user"
run crontab -l
SUB "crontab of root"
run sudo -n crontab -l
SUB "System cron directories"
run ls -la /etc/cron.d /etc/cron.daily /etc/cron.hourly
SUB "systemd timers"
run systemctl list-timers --all --no-pager

# ---------------------------------------------------------------------------
HDR "10. Node.js application on the same server (neighbour service — read only)"
SUB "Node processes (command lines, this does NOT stop anything)"
run sh -c 'ps -eo pid,etime,rss,user,args | grep -Ei "node|pm2|next" | grep -v grep | head -20'
SUB "pm2 status (if installed)"
run sh -c 'command -v pm2 >/dev/null 2>&1 && pm2 list || echo "pm2 not found"'

# ---------------------------------------------------------------------------
HDR "11. Automatic findings (heuristics)"
SUB "Sensitive path exposure"
for p in /config.php /application/config/config.php /storage/logs/ /storage/sessions/ /storage/backups/ /docs/readme.md /dev/sandbox/setup.sh /tests/TestCase.php; do
    c="$(curl -s -o /dev/null -m 8 -w '%{http_code}' "http://127.0.0.1:${APP_PORT}${p}" 2>/dev/null)"
    case "$c" in
        200) printf '    [CRITICAL] %s returned 200 — must be blocked\n' "$p" ;;
        301|302) printf '    [WARNING ] %s returned %s — check the redirect target\n' "$p" "$c" ;;
        403|404) printf '    [ok      ] %s returned %s\n' "$p" "$c" ;;
        *) printf '    [unknown ] %s returned %s\n' "$p" "${c:-ERR}" ;;
    esac
done
SUB "Other flags"
[ -f "$APP_PATH/config.php" ] && printf '    config.php present (installation started)\n'
grep -qE "DEBUG_MODE[[:space:]]*=[[:space:]]*(true|TRUE)" "$APP_PATH/config.php" 2>/dev/null \
    && printf '    [WARNING ] DEBUG_MODE=true on a production server\n'
grep -q "ENCRYPTION_KEY" "$APP_PATH/config.php" 2>/dev/null \
    && printf '    [ok      ] ENCRYPTION_KEY is defined in config.php\n' \
    || printf '    [WARNING ] ENCRYPTION_KEY is NOT defined — the key is derived from the server fingerprint\n'
[ -f "$APP_PATH/.env" ] && printf '    [info    ] a .env file exists\n' || printf '    [info    ] no .env file (the app currently reads config.php only)\n'
if [ -d "$APP_PATH/storage/backups" ]; then
    n="$(find "$APP_PATH/storage/backups" -maxdepth 1 -type f ! -name 'index.html' ! -name '.htaccess' | wc -l)"
    newest="$(ls -1t "$APP_PATH/storage/backups" 2>/dev/null | grep -v -E 'index.html|.htaccess' | head -1)"
    printf '    backups  : %s file(s), newest: %s\n' "$n" "${newest:-none}"
fi
swapline="$(free -m | awk '/Swap:/ {print $2}')"
[ "${swapline:-0}" -lt 512 ] && printf '    [WARNING ] swap is %s MB — add swap on a 2 GB server running MySQL+PHP+Node\n' "$swapline" \
                             || printf '    [ok      ] swap is %s MB\n' "$swapline"

HDR "END OF REPORT"
printf 'Send this file back. Anything that looks like a credential has been masked automatically,\n'
printf 'but please review before sharing.\n'
} 2>&1 | mask
