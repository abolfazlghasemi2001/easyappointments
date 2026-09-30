#!/usr/bin/env bash
#
# Development helper for environments without a native PHP runtime or Docker.
#
# It installs the php-wasm runtime (used by dev/sandbox/run.mjs and dev/sandbox/server.mjs), rebuilds the PHP
# dependencies from composer.lock through dev/sandbox/minicomposer.py and prepares a local SQLite database so that the
# application can be started right away.
#
# Usage: bash dev/sandbox/setup.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

cd "$ROOT"

echo "==> Installing the php-wasm development runtime (dev/sandbox/node_modules)..."
npm install --no-save --no-audit --no-fund \
    --prefix dev/sandbox \
    @php-wasm/node@3.1.56 @php-wasm/node-8-4@3.1.56

echo "==> Installing the PHP dependencies from composer.lock..."
python3 dev/sandbox/minicomposer.py "$ROOT"

if [ ! -f config.php ]; then
    echo "==> Creating a local config.php (git ignored)..."
    cat > config.php <<'PHP'
<?php
/* Local development configuration (git ignored). */
class Config
{
    const BASE_URL = 'http://localhost:8080';
    const LANGUAGE = 'english';
    const DEBUG_MODE = true;
    const DB_HOST = 'localhost';
    const DB_NAME = 'easyappointments';
    const DB_USERNAME = '';
    const DB_PASSWORD = '';
}
PHP
fi

echo "==> Done. Start the development server with:"
echo "    node dev/sandbox/server.mjs 8080"
