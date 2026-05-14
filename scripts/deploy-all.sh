#!/usr/bin/env bash
#
# Bird CMS multi-site deploy orchestrator (ops-side).
#
# Deploys a release archive to every site in $BIRD_SITES, with a canary
# step first when $BIRD_CANARY is set. Wraps scripts/update.sh; after
# each site is updated, restarts its docker compose stack if present
# (PHP-OPcache otherwise keeps the previous engine).
#
# Usage:
#   bash scripts/deploy-all.sh <version> [--skip-canary] [--no-restart]
#
# Required env vars (typically set in ~/.bashrc or systemd unit):
#   BIRD_SITES   Space-separated site paths to update.
#   BIRD_CANARY  (Optional) Path of the canary site (usually the
#                least-trafficked or test-only one).
#
# Example (current operator):
#   export BIRD_SITES="/server/sites/topic-wise.com \
#                      /server/sites/cleaninggta.com \
#                      /server/sites/klymentiev.com \
#                      /server/sites/klim.expert \
#                      /server/sites/husky-cleaning.biz \
#                      /server/sites/bird-cms.com"
#   export BIRD_CANARY="/server/sites/husky-cleaning.biz"
#   bash scripts/deploy-all.sh 3.1.6

set -euo pipefail

if [ $# -lt 1 ]; then
    echo "Usage: $0 <version> [--skip-canary] [--no-restart]" >&2
    exit 1
fi

VERSION="$1"; shift
SKIP_CANARY=false
DO_RESTART=true
while [ $# -gt 0 ]; do
    case "$1" in
        --skip-canary) SKIP_CANARY=true; shift ;;
        --no-restart)  DO_RESTART=false; shift ;;
        *) echo "Unknown arg: $1" >&2; exit 1 ;;
    esac
done

: "${BIRD_SITES:?ERROR: BIRD_SITES env var not set (space-separated site paths)}"

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
ARCHIVE="$REPO_DIR/releases/bird-cms-$VERSION.tar.gz"
[ -f "$ARCHIVE" ] || { echo "ERROR: $ARCHIVE not found. Run scripts/release.sh first." >&2; exit 1; }

G='\033[0;32m'; R='\033[0;31m'; N='\033[0m'
ok()   { echo -e "${G}[OK]${N} $*"; }
err()  { echo -e "${R}[ERROR]${N} $*" >&2; }

deploy_one() {
    local site="$1"
    echo ""
    echo "=== deploying v$VERSION to $site ==="
    bash "$REPO_DIR/scripts/update.sh" "$site" "$VERSION" --source "$REPO_DIR/releases"
    if $DO_RESTART && [ -f "$site/docker-compose.yml" ]; then
        (cd "$site" && docker compose restart 2>&1 \
            | grep -vE "is not set|deprecated" \
            | tail -3) || true
    fi
}

smoke_one() {
    local site="$1"
    local domain=""
    if [ -f "$site/.env" ]; then
        domain="$(grep "^APP_DOMAIN=" "$site/.env" 2>/dev/null | head -1 | cut -d= -f2 | tr -d '"' | tr -d "'")"
    fi
    [ -z "$domain" ] && domain="$(basename "$site")"

    local code
    code=$(curl -s -o /dev/null -w '%{http_code}' "https://$domain/?cb=$(date +%s%N)")
    if [ "$code" = "200" ]; then
        ok "smoke https://$domain/ -> $code"
        return 0
    else
        err "smoke https://$domain/ -> $code"
        return 1
    fi
}

# --- Canary -------------------------------------------------------------
if ! $SKIP_CANARY && [ -n "${BIRD_CANARY:-}" ]; then
    echo "=== Canary: $BIRD_CANARY ==="
    deploy_one "$BIRD_CANARY"
    smoke_one "$BIRD_CANARY" || { err "Canary failed. Aborting bulk deploy."; exit 1; }
    echo ""
    echo "Canary OK. Pausing 30s before bulk deploy..."
    sleep 30
fi

# --- Bulk ---------------------------------------------------------------
for SITE in $BIRD_SITES; do
    if ! $SKIP_CANARY && [ "$SITE" = "${BIRD_CANARY:-}" ]; then
        continue  # canary already done
    fi
    deploy_one "$SITE"
done

# --- Final smoke test ---------------------------------------------------
echo ""
echo "=== Final smoke test ==="
FAILS=0
for SITE in $BIRD_SITES; do
    smoke_one "$SITE" || FAILS=$((FAILS+1))
done

if [ $FAILS -gt 0 ]; then
    err "$FAILS sites failed smoke test. Consider rollback:"
    err "    bash scripts/rollback-all.sh <previous-version>"
    exit 1
fi

echo ""
ok "Deploy v$VERSION complete across all sites."
