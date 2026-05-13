#!/usr/bin/env bash
#
# Bird CMS multi-site rollback orchestrator.
#
# Flips every site in $BIRD_SITES back to the specified version via
# the existing scripts/rollback.sh per-site helper. Restarts docker
# compose stacks afterward so PHP-OPcache doesn't keep the new engine.
#
# Usage:
#   bash scripts/rollback-all.sh <target-version>
#
# Required env:
#   BIRD_SITES  Space-separated site paths to roll back.
#
# Example:
#   bash scripts/rollback-all.sh 3.1.4

set -euo pipefail

if [ $# -lt 1 ]; then
    echo "Usage: $0 <target-version>" >&2
    exit 1
fi

VERSION="$1"
: "${BIRD_SITES:?ERROR: BIRD_SITES env var not set}"

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"

G='\033[0;32m'; R='\033[0;31m'; Y='\033[1;33m'; N='\033[0m'
ok()   { echo -e "${G}[OK]${N} $*"; }
err()  { echo -e "${R}[ERROR]${N} $*" >&2; }
warn() { echo -e "${Y}[WARN]${N} $*"; }

FAILS=0
SKIPS=0

for SITE in $BIRD_SITES; do
    echo ""
    echo "=== rolling back $SITE to v$VERSION ==="

    if [ ! -d "$SITE/versions/$VERSION" ]; then
        warn "$SITE has no versions/$VERSION on disk - skipping"
        SKIPS=$((SKIPS+1))
        continue
    fi

    if ! bash "$REPO_DIR/scripts/rollback.sh" "$SITE" "$VERSION"; then
        err "rollback failed for $SITE"
        FAILS=$((FAILS+1))
        continue
    fi

    if [ -f "$SITE/docker-compose.yml" ]; then
        (cd "$SITE" && docker compose restart 2>&1 \
            | grep -vE "is not set|deprecated" \
            | tail -3) || true
    fi

    # Smoke test
    domain=""
    if [ -f "$SITE/.env" ]; then
        domain="$(grep "^APP_DOMAIN=" "$SITE/.env" 2>/dev/null | head -1 | cut -d= -f2 | tr -d '"' | tr -d "'")"
    fi
    [ -z "$domain" ] && domain="$(basename "$SITE")"
    code=$(curl -s -o /dev/null -w '%{http_code}' "https://$domain/?cb=$(date +%s%N)")
    if [ "$code" = "200" ]; then
        ok "$SITE rolled back; https://$domain/ -> 200"
    else
        err "$SITE rolled back but smoke failed: https://$domain/ -> $code"
        FAILS=$((FAILS+1))
    fi
done

echo ""
if [ $FAILS -gt 0 ]; then
    err "$FAILS site(s) had failures. Manual intervention required."
    exit 1
fi
if [ $SKIPS -gt 0 ]; then
    warn "$SKIPS site(s) skipped (no versions/$VERSION on disk)."
fi
ok "Rollback to v$VERSION complete."
