#!/usr/bin/env bash
#
# Bird CMS migration helper: alpha.13 / alpha.14 -> beta.1
#
# Run from a Bird CMS site root (the directory holding bootstrap.php). Writes
# storage/installed.lock with the existing site's metadata so the alpha.15+
# install wizard treats the site as already-installed instead of redirecting
# to /install on the next request.
#
# Idempotent: safe to run twice. Any operation it has already done is a
# no-op the second time.

set -euo pipefail

# -----------------------------------------------------------------------------
# Locate the site root
# -----------------------------------------------------------------------------

SITE_ROOT="${1:-$(pwd)}"

if [ ! -f "$SITE_ROOT/bootstrap.php" ]; then
    echo "[ERROR] $SITE_ROOT does not look like a Bird CMS install" >&2
    echo "        (no bootstrap.php found)" >&2
    echo "" >&2
    echo "Usage: $0 [path-to-site-root]" >&2
    echo "       (defaults to current directory)" >&2
    exit 2
fi

cd "$SITE_ROOT"

echo "=================================================================="
echo "Bird CMS migration helper: -> 2.0.0-beta.1"
echo "Site root: $SITE_ROOT"
echo "=================================================================="

# -----------------------------------------------------------------------------
# Detect current version
# -----------------------------------------------------------------------------

CURRENT_VERSION="unknown"
if [ -f "VERSION" ]; then
    CURRENT_VERSION="$(tr -d '[:space:]' < VERSION)"
fi

echo "Detected version: $CURRENT_VERSION"

# -----------------------------------------------------------------------------
# 1. Write storage/installed.lock if missing
# -----------------------------------------------------------------------------

LOCK="storage/installed.lock"
if [ -f "$LOCK" ]; then
    echo "[OK] $LOCK already exists -- wizard already disabled."
else
    echo "[..] Writing $LOCK so the alpha.15+ wizard skips this site..."
    mkdir -p storage
    NOW="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    cat > "$LOCK" <<JSON
{
    "version": "$CURRENT_VERSION",
    "installed_at": "$NOW",
    "install_method": "migrate-alpha13-to-beta1.sh"
}
JSON
    chmod 600 "$LOCK"
    echo "[OK] Wrote $LOCK"
fi

# -----------------------------------------------------------------------------
# 2. Sanity-check .env / config/app.php — both required by bootstrap.php
# -----------------------------------------------------------------------------

if [ ! -f ".env" ]; then
    echo "[WARN] .env is missing. Bootstrap will refuse to boot."
    echo "       Create one from .env.example, then re-run this script."
    exit 1
fi

if [ ! -f "config/app.php" ]; then
    echo "[WARN] config/app.php is missing. Copying from"
    echo "       templates/config-app.php.example..."
    if [ -f "templates/config-app.php.example" ]; then
        cp templates/config-app.php.example config/app.php
        chmod 644 config/app.php
        echo "[OK] Wrote config/app.php"
    else
        echo "[ERROR] templates/config-app.php.example also missing."
        echo "        This site is too damaged to migrate automatically."
        exit 1
    fi
fi

# -----------------------------------------------------------------------------
# 3. Detect customized themes/tailwind/ and offer a backup
# -----------------------------------------------------------------------------

if [ -d "themes/tailwind" ]; then
    BACKUP_BASE="themes/tailwind.pre-beta1.$(date -u +%Y%m%d%H%M%S)"
    echo "[..] Backing up themes/tailwind/ -> $BACKUP_BASE/"
    echo "     (alpha.17 restyled it to the Bird brand palette; if you"
    echo "      customized any view, your version is preserved here)"
    cp -r themes/tailwind "$BACKUP_BASE"
    echo "[OK] Backup at $BACKUP_BASE"
fi

# -----------------------------------------------------------------------------
# 4. Ensure config/authors.php exists (required by article view since alpha.17)
# -----------------------------------------------------------------------------

if [ ! -f "config/authors.php" ]; then
    echo "[..] config/authors.php missing. Writing default..."
    cat > config/authors.php <<'PHP'
<?php
declare(strict_types=1);

// Required by article views since alpha.17. Add more authors as needed.
return [
    'editorial-team' => [
        'name'   => 'Editorial Team',
        'role'   => 'Editorial',
        'bio'    => '',
        'avatar' => '/assets/brand/bird-logo.svg',
        'social' => [],
    ],
];
PHP
    chmod 644 config/authors.php
    echo "[OK] Wrote config/authors.php with default editorial-team"
fi

# -----------------------------------------------------------------------------
# 5. Report remaining manual steps
# -----------------------------------------------------------------------------

echo ""
echo "=================================================================="
echo "Manual steps remaining"
echo "=================================================================="

cat <<'NOTES'

  1. Pull the new engine. If you cloned via git:

         git fetch origin
         git checkout v2.0.0-beta.1

     Or if you use the versioned-layout install (engine symlink):

         ./scripts/update.sh 2.0.0-beta.1

  2. Restart the runtime so the new bootstrap loads:

         docker compose restart        # if using docker
         systemctl reload php-fpm      # if running on bare metal

  3. Open /admin in a browser. Sign in with your existing credentials
     and confirm everything renders. The brand palette is now Bird's
     forest-deep + teal -- expected unless you re-applied your tailwind
     backup from step 3 above.

  4. (Optional) Drop the brand by removing these lines from
     themes/tailwind/layouts/base.php and themes/admin/layout.php:

         <link rel="stylesheet" href="/assets/frontend/brand.css">
         <link rel="stylesheet" href="/assets/frontend/site.css">
         <link rel="stylesheet" href="/admin/assets/brand.css">
         <link rel="stylesheet" href="/admin/assets/admin.css">

     The engine works without them; you'll just get the unstyled
     Tailwind CDN defaults.

NOTES

echo "[OK] Migration helper complete."
