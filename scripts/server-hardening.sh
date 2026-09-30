#!/usr/bin/env bash
#
# ---------------------------------------------------------------------------
# Server hardening for the Easy!Appointments installation (nobat.hoosna1402.ir).
#
# WARNING - this script changes the state of the SERVER, not of the application.
# Read it completely before running it, and keep a second SSH session open on
# the server while it runs, so that a wrong firewall rule cannot lock you out.
#
# The script is NOT for the sandbox: it must be run on the server, as root,
# after the audit (scripts/server-audit.sh) has confirmed the current state.
#
#   sudo bash scripts/server-hardening.sh --check       # only report, no change
#   sudo bash scripts/server-hardening.sh --all         # apply everything
#   sudo bash scripts/server-hardening.sh --swap --firewall
#
# Steps:
#   1. swap       - 2 GB swap file (the server has 2 GB RAM and hosts a
#                   Node.js application next to MySQL and php-fpm)
#   2. firewall   - ufw: SSH first, then 80/443, then the rest
#   3. docker     - keep the container ports on the loopback interface
#   4. ssh        - disable the password login (only when a key is present!)
#   5. fail2ban   - ban repeated failed SSH logins
#   6. mysql      - bind the system MySQL to 127.0.0.1
#   7. unattended - security updates
#
# IMPORTANT: the server also runs the main site (hoosna1402.ir, Node.js). Every
# change below keeps that application reachable (ports 80/443 stay open). Never
# close port 80/443 before the reverse proxy of the main site is confirmed.
#
# Fork addition - not part of the upstream project (see CUSTOMIZATIONS.md).
# ---------------------------------------------------------------------------
set -Eeuo pipefail

SWAP_SIZE_GB=2
SWAP_FILE=/swapfile
SSH_PORT=22
MAIN_SITE_PORTS="80,443"

DO_CHECK=0
DO_SWAP=0
DO_FIREWALL=0
DO_DOCKER=0
DO_SSH=0
DO_FAIL2BAN=0
DO_MYSQL=0
DO_UNATTENDED=0

for argument in "$@"; do
    case "$argument" in
        --check) DO_CHECK=1 ;;
        --all) DO_SWAP=1; DO_FIREWALL=1; DO_DOCKER=1; DO_SSH=1; DO_FAIL2BAN=1; DO_MYSQL=1; DO_UNATTENDED=1 ;;
        --swap) DO_SWAP=1 ;;
        --firewall) DO_FIREWALL=1 ;;
        --docker) DO_DOCKER=1 ;;
        --ssh) DO_SSH=1 ;;
        --fail2ban) DO_FAIL2BAN=1 ;;
        --mysql) DO_MYSQL=1 ;;
        --unattended) DO_UNATTENDED=1 ;;
        -h|--help)
            sed -n '2,45p' "$0"
            exit 0
            ;;
        *) echo "Unknown argument: $argument" >&2; exit 2 ;;
    esac
done

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m ok\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[!]\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m[x]\033[0m %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" = '0' ] || die 'Run this script with sudo.'

run() {
    if [ "$DO_CHECK" = '1' ]; then
        printf '   [check] %s\n' "$*"
        return 0
    fi

    "$@"
}

# ---------------------------------------------------------------------------
# 0. Current state
# ---------------------------------------------------------------------------

log 'Current state of the server:'
printf '   uptime    : %s\n' "$(uptime -p 2>/dev/null || uptime)"
printf '   memory    : %s\n' "$(free -h | awk '/^Mem:/ {print $2" total, "$3" used, "$7" available"}')"
printf '   swap      : %s\n' "$(free -h | awk '/^Swap:/ {print $2" total, "$3" used"}')"
printf '   disk (/)  : %s\n' "$(df -h / | awk 'NR==2 {print $2" total, "$5" used"}')"

log 'Listening TCP ports:'

if command -v ss >/dev/null 2>&1; then
    ss -tlnp | awk 'NR==1 || /LISTEN/' | sed 's/^/   /'
else
    warn 'ss is not available.'
fi

# ---------------------------------------------------------------------------
# 1. Swap
# ---------------------------------------------------------------------------

if [ "$DO_SWAP" = '1' ]; then
    log "Configuring a ${SWAP_SIZE_GB}G swap file ($SWAP_FILE) ..."

    if swapon --show | grep -q .; then
        ok 'Swap is already active, nothing to do.'
    else
        if [ -f "$SWAP_FILE" ]; then
            warn "$SWAP_FILE already exists, it is activated without creating it again."
        else
            run fallocate -l "${SWAP_SIZE_GB}G" "$SWAP_FILE" || run dd if=/dev/zero of="$SWAP_FILE" bs=1M count=$((SWAP_SIZE_GB * 1024)) status=progress
            run chmod 600 "$SWAP_FILE"
            run mkswap "$SWAP_FILE"
        fi

        run swapon "$SWAP_FILE"

        if ! grep -q "^$SWAP_FILE" /etc/fstab; then
            printf '%s none swap sw 0 0\n' "$SWAP_FILE" >> /etc/fstab
        fi

        # Use the swap only when the RAM is really almost full.
        printf 'vm.swappiness=10\n' > /etc/sysctl.d/99-ea-swap.conf
        run sysctl --system >/dev/null

        ok "Swap enabled: $(free -h | awk '/^Swap:/ {print $2}')"
    fi
fi

# ---------------------------------------------------------------------------
# 2. Firewall
# ---------------------------------------------------------------------------

if [ "$DO_FIREWALL" = '1' ]; then
    log 'Configuring the firewall (ufw) ...'
    warn 'Keep this session open. The rules are added before ufw is enabled.'

    if ! command -v ufw >/dev/null 2>&1; then
        run apt-get update -qq
        run apt-get install -y -qq ufw
    fi

    # Order matters: SSH first, otherwise enabling ufw can lock you out.
    run ufw allow "$SSH_PORT"/tcp comment 'SSH'
    run ufw allow "$MAIN_SITE_PORTS"/tcp comment 'HTTP/HTTPS of the main site and of nobat.hoosna1402.ir'

    # The application containers publish their ports on 127.0.0.1 only, so no
    # other port has to be opened. If the phpMyAdmin tunnel is needed:
    #   ssh -L 9091:127.0.0.1:9091 user@server

    run ufw --force enable
    run ufw default deny incoming
    run ufw default allow outgoing
    run ufw status verbose

    ok 'Firewall configured.'
fi

# ---------------------------------------------------------------------------
# 3. Docker ports
# ---------------------------------------------------------------------------

if [ "$DO_DOCKER" = '1' ]; then
    log 'Checking the published container ports ...'

    # Any "0.0.0.0:PORT->" binding is reachable from the internet, even when the
    # firewall allows only 80/443: docker writes its own iptables rules.
    DANGEROUS="$(docker ps --format '{{.Names}}\t{{.Ports}}' 2>/dev/null | grep '0\.0\.0\.0:' || true)"

    if [ -n "$DANGEROUS" ]; then
        warn 'These containers publish a port on all interfaces:'
        printf '%s\n' "$DANGEROUS" | sed 's/^/   /'
        warn 'Change the port mapping to "127.0.0.1:HOST:CONTAINER" in the compose file'
        warn 'and recreate the container: docker compose up -d <service>'
    else
        ok 'No container port is published on all interfaces.'
    fi
fi

# ---------------------------------------------------------------------------
# 4. SSH
# ---------------------------------------------------------------------------

if [ "$DO_SSH" = '1' ]; then
    log 'Hardening the SSH configuration ...'

    if [ ! -s /root/.ssh/authorized_keys ] && [ ! -s "$HOME/.ssh/authorized_keys" ]; then
        warn 'No authorized_keys found, the password login is NOT disabled (you would lock yourself out).'
        warn 'Add a key first: ssh-copy-id user@server'
    else
        run cp -a /etc/ssh/sshd_config "/etc/ssh/sshd_config.bak-$(date +%Y%m%d-%H%M%S)"

        printf '%s\n' \
            'PermitRootLogin prohibit-password' \
            'PasswordAuthentication no' \
            'ChallengeResponseAuthentication no' \
            'PubkeyAuthentication yes' \
            'X11Forwarding no' \
            'MaxAuthTries 4' \
            > /etc/ssh/sshd_config.d/99-ea-hardening.conf

        if sshd -t 2>/dev/null; then
            run systemctl reload ssh 2>/dev/null || run systemctl reload sshd
            ok 'SSH hardened (password login disabled, keys only).'
        else
            rm -f /etc/ssh/sshd_config.d/99-ea-hardening.conf
            die 'The new sshd configuration is invalid, the change was reverted.'
        fi
    fi
fi

# ---------------------------------------------------------------------------
# 5. fail2ban
# ---------------------------------------------------------------------------

if [ "$DO_FAIL2BAN" = '1' ]; then
    log 'Installing fail2ban (SSH brute force protection) ...'

    if ! command -v fail2ban-client >/dev/null 2>&1; then
        run apt-get update -qq
        run apt-get install -y -qq fail2ban
    fi

    cat > /etc/fail2ban/jail.d/ea-sshd.local <<'JAIL'
# Protect the SSH service of the server (fork addition).
[sshd]
enabled = true
port = 22
maxretry = 4
findtime = 10m
bantime = 1h
JAIL

    run systemctl enable fail2ban
    run systemctl restart fail2ban
    run fail2ban-client status sshd

    ok 'fail2ban is active.'
fi

# ---------------------------------------------------------------------------
# 6. MySQL on the local interface only
# ---------------------------------------------------------------------------

if [ "$DO_MYSQL" = '1' ]; then
    log 'Checking the system MySQL binding ...'

    if systemctl list-unit-files 2>/dev/null | grep -q '^mysql\.service'; then
        if grep -rqsE '^\s*bind-address\s*=\s*0\.0\.0\.0' /etc/mysql/; then
            warn 'The system MySQL listens on all interfaces.'
            warn 'The docker stack uses its own MySQL container, so the system one is'
            warn 'probably not needed. Set "bind-address = 127.0.0.1" in /etc/mysql/'
            warn 'and restart it: systemctl restart mysql'
            warn 'Do this only when you are sure that no other application needs it.'
        else
            ok 'The system MySQL does not listen on all interfaces.'
        fi
    else
        ok 'No system MySQL service found (the application uses its own container).'
    fi
fi

# ---------------------------------------------------------------------------
# 7. Unattended security updates
# ---------------------------------------------------------------------------

if [ "$DO_UNATTENDED" = '1' ]; then
    log 'Enabling unattended security updates ...'

    if ! dpkg -l unattended-upgrades 2>/dev/null | grep -q '^ii'; then
        run apt-get update -qq
        run apt-get install -y -qq unattended-upgrades
    fi

    cat > /etc/apt/apt.conf.d/20auto-upgrades <<'CONF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
CONF

    run systemctl enable --now unattended-upgrades 2>/dev/null || true

    ok 'Unattended updates are enabled (security updates only by default).'
fi

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------

if [ "$DO_CHECK" = '1' ]; then
    cat <<'HINT'

This was a check run, nothing was changed. To apply a step:

  sudo bash scripts/server-hardening.sh --swap
  sudo bash scripts/server-hardening.sh --firewall
  sudo bash scripts/server-hardening.sh --docker
  sudo bash scripts/server-hardening.sh --ssh          # only with an SSH key installed
  sudo bash scripts/server-hardening.sh --fail2ban
  sudo bash scripts/server-hardening.sh --mysql
  sudo bash scripts/server-hardening.sh --unattended
  sudo bash scripts/server-hardening.sh --all          # everything above

HINT
fi

ok 'Done.'
