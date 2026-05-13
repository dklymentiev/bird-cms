#!/bin/bash
#
# Bird CMS site rollback (versioned 2.0)
#
# Atomically swaps the `engine` symlink to a previous version. If no
# target is given, picks the most recently modified versions/X.Y.Z/
# directory other than the current one.
#
# Usage:
#   ./rollback.sh <site_path> [target-version]
#
# Examples:
#   ./rollback.sh /var/www/example.com               # auto previous
#   ./rollback.sh /var/www/example.com 2.0.0-alpha.4  # explicit

set -euo pipefail

if [ $# -lt 1 ]; then
    echo "Usage: $0 <site_path> [target-version]" >&2
    exit 1
fi

SITE="$1"
TARGET="${2:-}"

if [ ! -d "$SITE" ]; then
    echo "Site directory not found: $SITE" >&2
    exit 1
fi
if [ ! -L "$SITE/engine" ]; then
    echo "Not a versioned site (no engine symlink): $SITE" >&2
    exit 1
fi
if [ ! -d "$SITE/versions" ]; then
    echo "No versions/ directory: $SITE" >&2
    exit 1
fi

CURRENT="$(basename "$(readlink "$SITE/engine")")"

# Auto-pick the most recent non-current version
if [ -z "$TARGET" ]; then
    TARGET="$(ls -t "$SITE/versions" 2>/dev/null | grep -v "^${CURRENT}\$" | head -1 || true)"
    if [ -z "$TARGET" ]; then
        echo "No alternative version available in $SITE/versions/." >&2
        echo "Currently installed: $CURRENT" >&2
        exit 1
    fi
    echo "Auto-selected previous version: $TARGET"
fi

if [ ! -d "$SITE/versions/$TARGET" ]; then
    echo "Target version not found: $SITE/versions/$TARGET" >&2
    echo "Available:" >&2
    ls "$SITE/versions/" 2>/dev/null >&2 || true
    exit 1
fi

if [ "$CURRENT" = "$TARGET" ]; then
    echo "Already on $TARGET, nothing to do."
    exit 0
fi

echo "=== Bird CMS rollback ==="
echo "Site:    $SITE"
echo "From:    $CURRENT"
echo "To:      $TARGET"
echo ""

# Atomic symlink swap
ln -sfn "versions/$TARGET" "$SITE/engine_new"
mv -Tf "$SITE/engine_new" "$SITE/engine"
echo "engine -> versions/$TARGET"

# Optional reload
if [ -n "${BIRD_RELOAD_CMD:-}" ]; then
    echo "=== Reload ==="
    eval "$BIRD_RELOAD_CMD"
fi

echo ""
echo "========================================="
echo "Rollback complete: $CURRENT -> $TARGET"
echo "========================================="
