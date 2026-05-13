#!/bin/bash
#
# Check Update - Git-based satellite site auto-updater
#
# Fetches tags from bird-cms git repo.
# If new tag exists, pulls and applies update with health check.
# Rolls back on failure.
#
# Usage: ./check-update.sh
#
# Environment variables:
#   SKIP_UPDATE=true       - Skip this update cycle
#   ENGINE_REPO            - Git repo URL (default: from /engine/.git or env)
#   ENGINE_DIR             - Local engine clone path (default: /engine)
#   DRY_RUN=true           - Show what would happen without doing it
#

set -e

SITE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
if [ -z "${ENGINE_DIR:-}" ]; then
    echo "ERROR: ENGINE_DIR not set. Path to the engine repo checkout (e.g. /engine)." >&2
    exit 1
fi
LOG_FILE="${SITE_DIR}/storage/logs/updates.log"
LOCK_FILE="/tmp/cms-update-$(basename "$SITE_DIR").lock"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Logging
log() {
    local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo -e "$msg"
    mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null || true
    echo "$msg" | sed 's/\x1b\[[0-9;]*m//g' >> "$LOG_FILE" 2>/dev/null || true
}

# Skip check
if [ "${SKIP_UPDATE}" = "true" ]; then
    log "SKIP_UPDATE=true, skipping"
    exit 0
fi

# Lock
if [ -f "$LOCK_FILE" ]; then
    LOCK_AGE=$(($(date +%s) - $(stat -c %Y "$LOCK_FILE" 2>/dev/null || echo 0)))
    if [ $LOCK_AGE -lt 300 ]; then
        log "Another update running (${LOCK_AGE}s old)"
        exit 0
    fi
    rm -f "$LOCK_FILE"
fi
echo $$ > "$LOCK_FILE"
trap "rm -f '$LOCK_FILE'" EXIT

# Check engine directory
if [ ! -d "$ENGINE_DIR/.git" ]; then
    log "${RED}✗ Engine not found or not a git repo: $ENGINE_DIR${NC}"
    log "Run: git clone <repo> $ENGINE_DIR"
    exit 1
fi

# Get current site version
SITE_VERSION=$(cat "$SITE_DIR/VERSION" 2>/dev/null || echo "0.0.0")
log "Site: $(basename "$SITE_DIR") v$SITE_VERSION"

# Fetch latest tags from remote
log "Fetching tags from origin..."
cd "$ENGINE_DIR"
git fetch --tags --quiet origin 2>/dev/null || {
    log "${RED}✗ Failed to fetch from origin${NC}"
    exit 1
}

# Get latest tag
LATEST_TAG=$(git describe --tags --abbrev=0 origin/main 2>/dev/null || git tag -l 'v*' | sort -V | tail -1)
if [ -z "$LATEST_TAG" ]; then
    log "No tags found in repository"
    exit 0
fi

# Remove 'v' prefix for comparison
LATEST_VERSION="${LATEST_TAG#v}"
log "Latest available: $LATEST_TAG ($LATEST_VERSION)"

# Compare versions
if [ "$SITE_VERSION" = "$LATEST_VERSION" ]; then
    log "Already up to date"
    exit 0
fi

# Check if latest is actually newer
version_gt() {
    test "$(printf '%s\n' "$@" | sort -V | head -n 1)" != "$1"
}

if ! version_gt "$LATEST_VERSION" "$SITE_VERSION"; then
    log "Site version ($SITE_VERSION) >= engine ($LATEST_VERSION), skipping"
    exit 0
fi

log "${GREEN}Update available: v$SITE_VERSION → $LATEST_TAG${NC}"

# Dry run
if [ "${DRY_RUN}" = "true" ]; then
    log "DRY_RUN=true, would update to $LATEST_TAG"
    exit 0
fi

# Checkout the tag in engine
log "Checking out $LATEST_TAG..."
git checkout --quiet "$LATEST_TAG" || {
    log "${RED}✗ Failed to checkout $LATEST_TAG${NC}"
    exit 1
}

# Create backup
BACKUP_DIR="$SITE_DIR/.update-backup-$(date +%Y%m%d-%H%M%S)"
log "Creating backup: $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"
cp -r "$SITE_DIR/app" "$BACKUP_DIR/" 2>/dev/null || true
cp "$SITE_DIR/bootstrap.php" "$BACKUP_DIR/" 2>/dev/null || true
cp "$SITE_DIR/VERSION" "$BACKUP_DIR/" 2>/dev/null || true

# Rollback function
rollback() {
    log "${RED}✗ Update failed, rolling back...${NC}"
    [ -d "$BACKUP_DIR/app" ] && rm -rf "$SITE_DIR/app" && cp -r "$BACKUP_DIR/app" "$SITE_DIR/"
    [ -f "$BACKUP_DIR/bootstrap.php" ] && cp "$BACKUP_DIR/bootstrap.php" "$SITE_DIR/"
    [ -f "$BACKUP_DIR/VERSION" ] && cp "$BACKUP_DIR/VERSION" "$SITE_DIR/"
    log "${YELLOW}Rolled back to v$SITE_VERSION${NC}"
}

# Apply update
log "Applying update..."

cp -r "$ENGINE_DIR/app" "$SITE_DIR/" || { rollback; exit 1; }
cp "$ENGINE_DIR/bootstrap.php" "$SITE_DIR/" || { rollback; exit 1; }

# Scripts
mkdir -p "$SITE_DIR/scripts"
for f in "$ENGINE_DIR/scripts/"*.php "$ENGINE_DIR/scripts/"*.sh; do
    [ -f "$f" ] && cp "$f" "$SITE_DIR/scripts/" 2>/dev/null || true
done
chmod +x "$SITE_DIR/scripts/"*.sh 2>/dev/null || true

# Admin theme
[ -d "$ENGINE_DIR/themes/admin" ] && {
    mkdir -p "$SITE_DIR/themes/admin"
    cp -r "$ENGINE_DIR/themes/admin/"* "$SITE_DIR/themes/admin/" 2>/dev/null || true
}

# Public files
for dir in admin api; do
    [ -d "$ENGINE_DIR/public/$dir" ] && {
        mkdir -p "$SITE_DIR/public/$dir"
        cp -r "$ENGINE_DIR/public/$dir/"* "$SITE_DIR/public/$dir/" 2>/dev/null || true
    }
done

# Public assets/js
[ -d "$ENGINE_DIR/public/assets/js" ] && {
    mkdir -p "$SITE_DIR/public/assets/js"
    cp -r "$ENGINE_DIR/public/assets/js/"* "$SITE_DIR/public/assets/js/" 2>/dev/null || true
}

# Update VERSION
echo "$LATEST_VERSION" > "$SITE_DIR/VERSION"

# Health check
log "Running health check..."
SITE_URL=""
[ -f "$SITE_DIR/config/app.php" ] && \
    SITE_URL=$(grep "site_url" "$SITE_DIR/config/app.php" 2>/dev/null | grep -o "https://[^'\"]*" | head -1)

HEALTH_OK=true
if [ -n "$SITE_URL" ]; then
    for PAGE in "" "blog" "contact"; do
        RESP=$(curl -s -o /dev/null -w "%{http_code}" "${SITE_URL%/}/$PAGE" 2>/dev/null || echo "000")
        if [ "$RESP" = "200" ] || [ "$RESP" = "301" ] || [ "$RESP" = "302" ]; then
            log "  ✓ /$PAGE: $RESP"
        else
            log "  ${RED}✗ /$PAGE: $RESP${NC}"
            HEALTH_OK=false
        fi
    done

    # PHP error check
    BODY=$(curl -s "$SITE_URL" 2>/dev/null)
    if echo "$BODY" | grep -qiE "Fatal error|Parse error|Warning:"; then
        log "  ${RED}✗ PHP errors on homepage${NC}"
        HEALTH_OK=false
    fi
else
    log "  ${YELLOW}⚠ No site URL, skipping HTTP check${NC}"
fi

if [ "$HEALTH_OK" = "false" ]; then
    rollback
    exit 1
fi

# Success
log "${GREEN}✓ Updated to $LATEST_TAG${NC}"

# Cleanup old backups (keep 5)
cd "$SITE_DIR"
ls -dt .update-backup-* 2>/dev/null | tail -n +6 | xargs rm -rf 2>/dev/null || true
ls -dt .engine-backup-* 2>/dev/null | tail -n +5 | xargs rm -rf 2>/dev/null || true

log "Update complete"
exit 0
