#!/bin/bash
#
# Bird CMS site updater (versioned 2.0)
#
# Pulls a release archive, verifies SHA-256, extracts into versions/X.Y.Z/,
# and atomically switches the `engine` symlink. Optional PHP-FPM reload
# via BIRD_RELOAD_CMD env var.
#
# Usage:
#   ./update.sh <site_path> [version] [--source <path-or-url>]
#
#   version  Optional. If omitted, reads latest.txt from --source.
#   --source Optional. Local directory or http(s) base URL containing
#            bird-cms-<version>.tar.gz, .sha256, and latest.txt.
#            Defaults to BIRD_RELEASE_URL env var (set per site in .env).
#
# Examples:
#   ./update.sh /var/www/example.com
#   ./update.sh /var/www/example.com 2.0.0-alpha.5
#   ./update.sh /tmp/test --source /server/scripts/bird-cms-2.0/releases

set -euo pipefail

if [ $# -lt 1 ]; then
    echo "Usage: $0 <site_path> [version] [--source <path-or-url>]" >&2
    exit 1
fi

SITE="$1"; shift
VERSION=""
SOURCE=""

# Optional positional version (anything not starting with --)
if [ $# -gt 0 ] && [[ "$1" != --* ]]; then
    VERSION="$1"; shift
fi

# Optional --source
while [ $# -gt 0 ]; do
    case "$1" in
        --source) SOURCE="$2"; shift 2;;
        *) echo "Unknown arg: $1" >&2; exit 1;;
    esac
done

# Default source from env (per-site .env or shell)
if [ -z "$SOURCE" ]; then
    SOURCE="${BIRD_RELEASE_URL:-}"
fi
if [ -z "$SOURCE" ]; then
    echo "No --source given and BIRD_RELEASE_URL is not set" >&2
    exit 1
fi

if [ ! -d "$SITE" ]; then
    echo "Site directory not found: $SITE" >&2
    exit 1
fi
if [ ! -L "$SITE/engine" ]; then
    echo "Not a versioned site (no engine symlink): $SITE" >&2
    exit 1
fi

# Resolve target version
fetch() {
    if [[ "$SOURCE" =~ ^https?:// ]]; then
        curl -fsSL "$SOURCE/$1" -o "$2"
    else
        cp "$SOURCE/$1" "$2"
    fi
}

if [ -z "$VERSION" ]; then
    TMP_LATEST="$(mktemp)"
    fetch "latest.txt" "$TMP_LATEST"
    VERSION="$(cat "$TMP_LATEST")"
    rm -f "$TMP_LATEST"
fi

CURRENT="$(basename "$(readlink "$SITE/engine")")"

echo "=== Bird CMS update ==="
echo "Site:    $SITE"
echo "Source:  $SOURCE"
echo "Current: $CURRENT"
echo "Target:  $VERSION"
echo ""

if [ "$CURRENT" = "$VERSION" ]; then
    echo "Already on $VERSION, nothing to do."
    exit 0
fi

# === Pre-update backup ===
# Snapshots site state (content/, config/, public/, themes/, storage/data) so an
# update gone wrong can be restored end-to-end. Excludes regenerable paths
# (versions/ — restored from release, storage/cache and storage/logs — runtime
# noise, node_modules — npm-managed). Aborts the update if backup fails. Skip
# with BIRD_BACKUP_SKIP=1, override path with BIRD_BACKUP_DIR.
if [ -z "${BIRD_BACKUP_SKIP:-}" ]; then
    if [ -z "${BIRD_BACKUP_DIR:-}" ]; then
        echo "ERROR: BIRD_BACKUP_DIR not set. Required: directory where versioned backups are stored before updates." >&2
        exit 1
    fi
    BACKUP_DIR="$BIRD_BACKUP_DIR"
    mkdir -p "$BACKUP_DIR"
    SITE_NAME="$(basename "$SITE")"
    TS="$(date +%Y%m%d-%H%M%S)"
    BACKUP_FILE="$BACKUP_DIR/$SITE_NAME-pre-$VERSION-$TS.tar.gz"
    echo "=== Pre-update backup ==="
    echo "  -> $BACKUP_FILE"
    if tar czf "$BACKUP_FILE" \
            --exclude="versions" \
            --exclude="storage/cache" \
            --exclude="storage/logs" \
            --exclude="node_modules" \
            --exclude=".credentials" \
            --exclude=".git" \
            -C "$(dirname "$SITE")" "$SITE_NAME"; then
        echo "  size: $(du -h "$BACKUP_FILE" | cut -f1)"
    else
        echo "!!! Backup failed — aborting update. Restore not possible without snapshot. !!!" >&2
        rm -f "$BACKUP_FILE"
        exit 3
    fi
else
    echo "(BIRD_BACKUP_SKIP=1 — skipping pre-update backup)"
fi

# Download archive + checksum
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

ARCHIVE="bird-cms-$VERSION.tar.gz"
echo "=== Fetch archive ==="
fetch "$ARCHIVE" "$WORK_DIR/$ARCHIVE"
fetch "$ARCHIVE.sha256" "$WORK_DIR/$ARCHIVE.sha256"

echo "=== Verify checksum ==="
( cd "$WORK_DIR" && sha256sum -c "$ARCHIVE.sha256" )

# Extract into versions/<version>
TARGET_DIR="$SITE/versions/$VERSION"
if [ -d "$TARGET_DIR" ]; then
    echo "Version already extracted: $TARGET_DIR (reusing)"
else
    echo "=== Extract engine ==="
    mkdir -p "$TARGET_DIR"
    tar xzf "$WORK_DIR/$ARCHIVE" -C "$TARGET_DIR"
fi

# Patch alpha.12-shape sites in place. lean-3.0 introduced five infra
# config files (admin/analytics/generator/rate-limit/widgets.php) that
# alpha.12 sites never had, plus a `theme.tailwind_cdn_url` key in
# config/app.php and a TAILWIND_CDN_URL in .env. Without these, a fresh
# /admin request returns "Config 'admin' not found" and the public theme
# loads no Tailwind CSS. Copy the missing config files from the new
# engine and append the missing settings -- idempotent, so re-running
# the script is safe.
if [ -f "$SITE/config/app.php" ] && [ ! -f "$SITE/config/admin.php" ]; then
    echo "=== Patch alpha.12 layout (F1/F2) ==="
    for cfg in admin.php analytics.php generator.php rate-limit.php widgets.php; do
        if [ ! -f "$SITE/config/$cfg" ] && [ -f "$TARGET_DIR/config/$cfg" ]; then
            cp "$TARGET_DIR/config/$cfg" "$SITE/config/$cfg"
            echo "  + config/$cfg"
        fi
    done
    if ! grep -q "tailwind_cdn_url" "$SITE/config/app.php"; then
        sed -i.bak "/'active_theme' =>/i\\    'theme' => ['tailwind_cdn_url' => \$_ENV['TAILWIND_CDN_URL'] ?? null]," "$SITE/config/app.php"
        rm -f "$SITE/config/app.php.bak"
        echo "  + config/app.php: theme.tailwind_cdn_url"
    fi
    if [ -f "$SITE/.env" ] && ! grep -q '^TAILWIND_CDN_URL=' "$SITE/.env"; then
        echo 'TAILWIND_CDN_URL=https://cdn.tailwindcss.com' >> "$SITE/.env"
        echo "  + .env: TAILWIND_CDN_URL"
    fi
fi

# Atomic symlink switch via temp + rename
echo "=== Atomic switch ==="
ln -sfn "versions/$VERSION" "$SITE/engine_new"
mv -Tf "$SITE/engine_new" "$SITE/engine"
echo "engine -> versions/$VERSION"

# Optional reload
if [ -n "${BIRD_RELOAD_CMD:-}" ]; then
    echo "=== Reload ==="
    eval "$BIRD_RELOAD_CMD"
fi

# Post-update checksum audit (compares to most recent snapshot, which is the
# pre-update one if you ran it before the switch). Exit code 2 from the audit
# means broken pages/redirects/images — auto-rollback in that case.
AUDIT="$SITE/engine/scripts/checksum-audit.php"
if [ -n "${BIRD_AUDIT_SKIP:-}" ]; then
    echo "(BIRD_AUDIT_SKIP=1 — skipping post-update audit; manual verification required)"
elif [ -x "$(command -v php)" ] && [ -f "$AUDIT" ]; then
    echo "=== Post-update audit ==="
    if php "$AUDIT" --skip-images --tag "post-$VERSION" 2>&1 | tail -25; then
        AUDIT_EXIT=0
    else
        AUDIT_EXIT=$?
    fi

    if [ "$AUDIT_EXIT" -eq 2 ]; then
        echo ""
        echo "!!! AUDIT REPORTED BROKEN PAGES — rolling back !!!"
        ln -sfn "versions/$CURRENT" "$SITE/engine_new"
        mv -Tf "$SITE/engine_new" "$SITE/engine"
        if [ -n "${BIRD_RELOAD_CMD:-}" ]; then
            eval "$BIRD_RELOAD_CMD" || true
        fi
        echo "Rolled back to $CURRENT."
        exit 2
    fi
fi

echo ""
echo "========================================="
echo "Update complete: $CURRENT -> $VERSION"
echo "========================================="
echo ""
echo "If something broke, roll back with:"
echo "  $SITE/engine/scripts/rollback.sh $SITE $CURRENT"
