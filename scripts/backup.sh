#!/usr/bin/env bash
#
# Bird CMS site backup -- bundles the content+config+state of a site
# into a single tarball. Designed for the versioned-symlink layout
# produced by scripts/install-site.sh, but works on the in-repo demo
# install too.
#
# Usage:
#   ./scripts/backup.sh                      # current dir, ./backups
#   ./scripts/backup.sh /var/www/example.com # site path, ./backups
#   ./scripts/backup.sh /var/www/example.com /backups
#
# Output:
#   <out>/<site-name>-YYYY-MM-DD-HHMM.tar.gz
#
# What's included:
#   .env            -- secrets, atomic from wizard
#   config/         -- per-site config (app.php, authors, categories)
#   content/        -- articles, pages, projects, services
#   storage/        -- installed.lock, analytics, leads (no logs)
#   uploads/        -- user-uploaded media
#
# What's excluded:
#   engine/, versions/   -- the engine itself; tar the source repo separately
#   storage/logs/        -- noisy and not state
#   tests/, audit/       -- dev artifacts
#
# Restore:
#   tar -xzf <backup>.tar.gz -C /var/www/example.com

set -euo pipefail

SITE_PATH="${1:-$(pwd)}"
OUT_DIR="${2:-./backups}"

if [ ! -d "$SITE_PATH" ]; then
    echo "Error: site path not found: $SITE_PATH" >&2
    exit 1
fi

SITE_PATH="$(cd "$SITE_PATH" && pwd)"
SITE_NAME="$(basename "$SITE_PATH")"
TS="$(date +%Y-%m-%d-%H%M)"
mkdir -p "$OUT_DIR"
OUT_FILE="$OUT_DIR/${SITE_NAME}-${TS}.tar.gz"

# Collect the paths that exist (not every site has every directory yet).
INCLUDES=()
for p in .env config content storage uploads; do
    if [ -e "$SITE_PATH/$p" ]; then
        INCLUDES+=("$p")
    fi
done

if [ ${#INCLUDES[@]} -eq 0 ]; then
    echo "Error: nothing to back up at $SITE_PATH (no .env, config, content, storage, uploads)" >&2
    exit 1
fi

echo "Backing up: $SITE_PATH"
echo "Including : ${INCLUDES[*]}"
echo "Output    : $OUT_FILE"

tar \
    --exclude='storage/logs/*' \
    --exclude='storage/cache/*' \
    -C "$SITE_PATH" \
    -czf "$OUT_FILE" \
    "${INCLUDES[@]}"

SIZE="$(du -h "$OUT_FILE" | cut -f1)"
echo "Done. ${SIZE} -> ${OUT_FILE}"
