#!/bin/bash
#
# Bird CMS — release archive builder
#
# Reads VERSION, tars the engine into releases/bird-cms-<VERSION>.tar.gz,
# computes the sha256, updates releases/latest.txt. Idempotent.
#
# Usage:
#   ./scripts/build-release.sh              # uses ./VERSION
#   ./scripts/build-release.sh 2.0.0-rc.1   # explicit override
#

set -euo pipefail

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-$(cat "$REPO_DIR/VERSION" 2>/dev/null || true)}"

if [ -z "$VERSION" ]; then
    echo "ERROR: no version (no \$1, no $REPO_DIR/VERSION)" >&2
    exit 1
fi

RELEASES_DIR="$REPO_DIR/releases"
ARCHIVE="$RELEASES_DIR/bird-cms-$VERSION.tar.gz"
SHA_FILE="$ARCHIVE.sha256"

mkdir -p "$RELEASES_DIR"

echo "=== Bird CMS release builder ==="
echo "Version: $VERSION"
echo "Archive: $ARCHIVE"
echo

# Build the archive. Excludes everything that's not engine code:
#  - releases/        (we're writing into it)
#  - .git/            (history is not part of a release)
#  - vendor/, node_modules/  (dependencies install on target)
#  - storage/         (runtime artifacts)
#  - content/, uploads/  (per-site, not engine)
#  - .env             (per-site secrets)
#  - .pre-*           (in-place patch backups, not engine)
#  - *.bak, *~        (editor artifacts)
echo "Tarring engine..."
tar czf "$ARCHIVE" \
    -C "$REPO_DIR" \
    --exclude='./releases' \
    --exclude='./.git' \
    --exclude='./vendor' \
    --exclude='./node_modules' \
    --exclude='./storage' \
    --exclude='./content' \
    --exclude='./uploads' \
    --exclude='./.env' \
    --exclude='./.env.local' \
    --exclude='*.pre-*-fix-*' \
    --exclude='*.pre-rewrite' \
    --exclude='*.pre-mit-switch' \
    --exclude='*.bak' \
    --exclude='*~' \
    --exclude='*.swp' \
    --exclude='./.DS_Store' \
    .

echo "Archive: $(du -h "$ARCHIVE" | cut -f1)"
echo

echo "Computing sha256..."
( cd "$RELEASES_DIR" && sha256sum "$(basename "$ARCHIVE")" > "$SHA_FILE" )
echo "Checksum: $(cat "$SHA_FILE")"
echo

echo "$VERSION" > "$RELEASES_DIR/latest.txt"
echo "Updated: $RELEASES_DIR/latest.txt → $VERSION"
echo

echo "=== verify checksum file ==="
( cd "$RELEASES_DIR" && sha256sum -c "$(basename "$SHA_FILE")" )

echo
echo "Done. Files in $RELEASES_DIR:"
ls -la "$RELEASES_DIR/" | grep -E "$VERSION|latest"
