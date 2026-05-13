#!/usr/bin/env bash
# Deploy-time benchmark for Bird CMS.
#
# Times two operations:
#   1. install-site.sh end-to-end (fresh install from release tarball)
#   2. update.sh symlink swap (atomic upgrade between two versions)
#
# Usage:
#   ./docs/perf/benchmarks/deploy.sh
#
# Requires:
#   - releases/<old>.tar.gz and releases/<new>.tar.gz exist
#   - /tmp writable
#
# Output: timing in milliseconds for each operation.

set -euo pipefail

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
TMP_SITE="/tmp/bird-cms-bench-$$"
HOST="bench-$$.local"

# Find two release tarballs to bench against
RELEASES_DIR="$REPO_DIR/releases"
if [ ! -d "$RELEASES_DIR" ]; then
  echo "No releases/ dir. Run 'make release' first." >&2
  exit 1
fi

OLD_TGZ=$(ls -1t "$RELEASES_DIR"/*.tar.gz 2>/dev/null | sed -n '2p')
NEW_TGZ=$(ls -1t "$RELEASES_DIR"/*.tar.gz 2>/dev/null | sed -n '1p')

if [ -z "$NEW_TGZ" ] || [ -z "$OLD_TGZ" ]; then
  echo "Need at least 2 release tarballs in $RELEASES_DIR" >&2
  exit 1
fi

echo "Old: $(basename "$OLD_TGZ")"
echo "New: $(basename "$NEW_TGZ")"
echo

ms() { python3 -c "import time; print(int(time.time()*1000))"; }
elapsed_ms() { echo $(($(ms) - $1)); }

# Bench 1: fresh install
echo "[1] install-site.sh fresh install..."
START=$(ms)
"$REPO_DIR/scripts/install-site.sh" "$TMP_SITE" "$HOST" >/dev/null 2>&1
INSTALL_MS=$(elapsed_ms "$START")
echo "    -> ${INSTALL_MS}ms"

# Bench 2: update (symlink swap)
echo "[2] update.sh symlink swap..."
NEW_VERSION=$(basename "$NEW_TGZ" .tar.gz | sed 's/^bird-cms-//')
START=$(ms)
"$REPO_DIR/scripts/update.sh" "$TMP_SITE" "$NEW_VERSION" >/dev/null 2>&1
UPDATE_MS=$(elapsed_ms "$START")
echo "    -> ${UPDATE_MS}ms"

# Cleanup
rm -rf "$TMP_SITE"

echo
echo "Results JSON:"
echo "{\"install_ms\": $INSTALL_MS, \"update_ms\": $UPDATE_MS}"
