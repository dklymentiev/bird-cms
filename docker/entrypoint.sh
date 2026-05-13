#!/bin/bash
#
# CMS Site Entrypoint
# - Clones/updates engine repo
# - Starts cron for auto-updates
# - Runs PHP-FPM
#

set -e

if [ -z "${ENGINE_REPO:-}" ]; then
    echo "ERROR: ENGINE_REPO not set. Required: git URL of the bird-cms engine repo to clone." >&2
    echo "       Example: ENGINE_REPO=https://github.com/your-org/bird-cms.git" >&2
    exit 1
fi
ENGINE_DIR="/engine"

echo "=== CMS Site Entrypoint ==="

# Engine version pin. Required: a git tag like v2.0.0-alpha.12, or 'main' if you
# explicitly accept whatever upstream ships. No silent default — running
# unpinned production code is a security incident waiting to happen.
ENGINE_REF="${ENGINE_REF:-}"
if [ -z "$ENGINE_REF" ]; then
    echo "ERROR: ENGINE_REF not set. Pin to a git tag (e.g. v2.0.0-alpha.12)" >&2
    echo "       or set ENGINE_REF=main to opt into upstream tracking." >&2
    exit 1
fi

# Clone or update engine
if [ ! -d "$ENGINE_DIR/.git" ]; then
    echo "Cloning engine from $ENGINE_REPO at $ENGINE_REF..."
    git clone --branch "$ENGINE_REF" --depth 1 "$ENGINE_REPO" "$ENGINE_DIR"
else
    echo "Engine exists, fetching tags..."
    cd "$ENGINE_DIR"
    git fetch --tags --quiet origin 2>/dev/null || true
fi

# Get engine version
ENGINE_VERSION=$(cat "$ENGINE_DIR/VERSION" 2>/dev/null || echo "unknown")
echo "Engine version: $ENGINE_VERSION"

# Auto-update cron is OFF by default. Operators must explicitly opt in by
# setting ENABLE_AUTO_UPDATE=true. check-update.sh itself uses tag-based
# updates with a backup + health check + rollback, but auto-applying upstream
# changes to running production should never be the default.
if [ "${ENABLE_AUTO_UPDATE}" = "true" ]; then
    echo "Setting up auto-update cron (ENABLE_AUTO_UPDATE=true)..."

    CRON_FILE="/etc/crontabs/root"
    CRON_LINE="0 * * * * /var/www/html/scripts/check-update.sh >> /var/www/html/storage/logs/cron-update.log 2>&1"

    grep -q "check-update.sh" "$CRON_FILE" 2>/dev/null || echo "$CRON_LINE" >> "$CRON_FILE"

    crond -b -l 8
    echo "Cron started (hourly update check)"
else
    echo "Auto-update disabled (default — set ENABLE_AUTO_UPDATE=true to opt in)"
fi

# Ensure directories
mkdir -p /var/www/html/storage/cache
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/analytics

# Fix permissions for host user access
# HOST_UID allows host admin user to read/write container files
if [ -n "$HOST_UID" ] && command -v setfacl >/dev/null 2>&1; then
    echo "Setting ACL for host user (UID=$HOST_UID)..."
    for dir in content storage public/assets; do
        path="/var/www/html/$dir"
        if [ -d "$path" ]; then
            setfacl -R -m "u:${HOST_UID}:rwX" "$path" 2>/dev/null || true
            setfacl -R -d -m "u:${HOST_UID}:rwX" "$path" 2>/dev/null || true
        fi
    done
    echo "ACL set for UID $HOST_UID"
fi

echo "Starting PHP-FPM..."
exec php-fpm
