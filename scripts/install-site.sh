#!/bin/bash
#
# Bird CMS site installer (2.0 versioned layout)
#
# Creates a fresh site directory with a symlinked engine, ready to be served
# by nginx/Apache pointing document root at <target>/public.
#
# Usage: ./scripts/install-site.sh <target_path> <domain> [version]
# Example: ./scripts/install-site.sh /server/sites/example.com example.com 2.0.0-alpha.1
#
# Layout produced:
#   <target>/
#     versions/<version>/      ← engine extracted from release archive
#     engine -> versions/<version>   ← atomic switch target
#     config/app.php           ← minimal site config
#     content/{articles,pages}/
#     storage/{cache,logs,backups,analytics}/
#     uploads/
#     public/
#       index.php              ← thin delegator
#       assets/                ← site-specific assets
#     .env                     ← site secrets

set -euo pipefail

TARGET="${1:-}"
DOMAIN="${2:-}"
VERSION="${3:-}"

if [ -z "$TARGET" ] || [ -z "$DOMAIN" ]; then
    echo "Usage: $0 <target_path> <domain> [version]" >&2
    echo "Example: $0 /server/sites/example.com example.com 2.0.0-alpha.1" >&2
    exit 1
fi

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
RELEASES_DIR="$REPO_DIR/releases"

if [ -z "$VERSION" ]; then
    if [ ! -f "$RELEASES_DIR/latest.txt" ]; then
        # Cold-clone path: releases/ is gitignored, so a fresh `git clone` has
        # no archive yet. Build one from the working tree before installing.
        echo "No release archive found in $RELEASES_DIR/ -- building from working tree..."
        if [ ! -x "$REPO_DIR/scripts/build-release.sh" ]; then
            echo "ERROR: $REPO_DIR/scripts/build-release.sh missing or not executable" >&2
            exit 1
        fi
        ( cd "$REPO_DIR" && bash scripts/build-release.sh )
        echo ""
    fi
    if [ -f "$RELEASES_DIR/latest.txt" ]; then
        VERSION="$(cat "$RELEASES_DIR/latest.txt")"
        echo "Using latest version from latest.txt: $VERSION"
    else
        echo "ERROR: still no $RELEASES_DIR/latest.txt after build attempt" >&2
        exit 1
    fi
fi

ARCHIVE="$RELEASES_DIR/bird-cms-$VERSION.tar.gz"
SHA_FILE="$ARCHIVE.sha256"

[ -f "$ARCHIVE" ]   || { echo "Archive not found: $ARCHIVE" >&2; exit 1; }
[ -f "$SHA_FILE" ]  || { echo "Checksum file not found: $SHA_FILE" >&2; exit 1; }
[ ! -e "$TARGET" ]  || { echo "Target already exists: $TARGET" >&2; exit 1; }

G='\033[0;32m'; Y='\033[1;33m'; N='\033[0m'

echo "=== Bird CMS site installer ==="
echo "Target:  $TARGET"
echo "Domain:  $DOMAIN"
echo "Version: $VERSION"
echo "Archive: $ARCHIVE"
echo ""

echo "=== Verify checksum ==="
( cd "$RELEASES_DIR" && sha256sum -c "$(basename "$SHA_FILE")" )
echo ""

echo "=== Create site structure ==="
mkdir -p "$TARGET/versions/$VERSION"
mkdir -p "$TARGET/config"
mkdir -p "$TARGET/content/articles"
mkdir -p "$TARGET/content/pages"
mkdir -p "$TARGET/storage/cache"
mkdir -p "$TARGET/storage/logs"
mkdir -p "$TARGET/storage/backups"
mkdir -p "$TARGET/storage/analytics"
mkdir -p "$TARGET/uploads"
mkdir -p "$TARGET/public/assets"
echo -e "${G}Site directories created${N}"
echo ""

echo "=== Extract engine ==="
tar xzf "$ARCHIVE" -C "$TARGET/versions/$VERSION"
echo "Extracted: $TARGET/versions/$VERSION"
echo ""

echo "=== Atomic engine symlink ==="
ln -sfn "versions/$VERSION" "$TARGET/engine"
echo -e "${G}engine -> versions/$VERSION${N}"
echo ""

echo "=== Wire engine entrypoints into site/public ==="
# Symlinks instead of delegators: nginx serves engine entrypoints transparently
# and engine bootstrap.php walks up to find SITE_ROOT via config/app.php.
ln -sfn ../engine/public/index.php "$TARGET/public/index.php"
ln -sfn ../engine/public/admin     "$TARGET/public/admin"
ln -sfn ../engine/public/api       "$TARGET/public/api"
echo "  public/index.php  -> engine/public/index.php"
echo "  public/admin/     -> engine/public/admin/"
echo "  public/api/       -> engine/public/api/"

# Brand assets used by the admin theme (bird-logo, hero-glow). Symlink them
# from the engine bundle so theme upgrades pick up new artwork automatically.
mkdir -p "$TARGET/public/assets/brand"
ln -sfn ../../../engine/public/assets/brand/bird-logo.svg  "$TARGET/public/assets/brand/bird-logo.svg"
ln -sfn ../../../engine/public/assets/brand/hero-glow.webp "$TARGET/public/assets/brand/hero-glow.webp"
echo "  public/assets/brand/{bird-logo.svg,hero-glow.webp} -> engine/public/assets/brand/"

# Default tailwind theme: copy engine-bundled theme so the site's themes_path
# (which now points at the site-local themes/ dir) finds a renderable theme
# out of the box. Operators can fork or replace it without affecting other sites.
mkdir -p "$TARGET/themes"
cp -a "$TARGET/versions/$VERSION/themes/tailwind" "$TARGET/themes/tailwind"
echo "  themes/tailwind/  copied from engine bundle (site-local from day one)"

# Minimal site config — bootstrap requires config/app.php to load Config::boot.
cat > "$TARGET/config/app.php" <<EOF
<?php
declare(strict_types=1);

\$env = static fn(string \$k): ?string => (\$_ENV[\$k] ?? getenv(\$k)) ?: null;

return [
    'site_name'    => \$env('SITE_NAME')    ?? '$DOMAIN',
    'site_url'     => \$env('SITE_URL')     ?? 'https://$DOMAIN',
    'timezone'     => \$env('TIMEZONE')     ?? 'UTC',
    'active_theme' => \$env('ACTIVE_THEME') ?? 'tailwind',
    'themes_path'  => __DIR__ . '/../themes',
    'content_dir'  => SITE_CONTENT_PATH,
    'articles_dir' => SITE_CONTENT_PATH . '/articles',
    'cache_dir'    => SITE_STORAGE_PATH . '/cache',

    'theme' => [
        // Tailwind via Play CDN — fine for demos. Set to a self-hosted CSS path
        // for production. See docs/recipes/build-tailwind.md (TODO).
        'tailwind_cdn_url' => \$env('TAILWIND_CDN_URL'),
    ],

    'seo' => [
        'default_og_image' => \$env('SEO_DEFAULT_OG_IMAGE'),
        'title_separator'  => \$env('SEO_TITLE_SEPARATOR') ?? '—',
    ],
];
EOF
echo "Wrote: $TARGET/config/app.php"

# Starter categories config — admin pages /admin/articles, /admin/pipeline,
# /admin/categories, /admin/templates all read this. Without it they 500.
# Edit this file (or replace it entirely) to define your own taxonomy.
cat > "$TARGET/config/categories.php" <<'PHP'
<?php
declare(strict_types=1);

// Article taxonomy. Top-level keys are category slugs (used in URLs:
// /<category>/<article-slug>). Subcategory keys are scoped to their parent.
//
// Replace the example "blog" entry below with categories that match your
// site. Add as many top-level categories as you like.
return [
    'blog' => [
        'title'         => 'Blog',
        'description'   => 'Articles and notes.',
        'icon'          => 'edit',
        'subcategories' => [],
    ],
];
PHP
echo "Wrote: $TARGET/config/categories.php"

# .env with a generated APP_KEY.
APP_KEY="$(head -c 32 /dev/urandom | base64 | tr -d '\n=' | tr '/+' '_-')"
cat > "$TARGET/.env" <<EOF
# Site secrets and per-environment overrides.
# Do not commit. Bootstrap reads this on every request.

APP_DOMAIN=$DOMAIN
APP_KEY=$APP_KEY

# Used by checksum-audit.php and any CLI script that needs to hit the live site.
SITE_URL=https://$DOMAIN

# Admin panel IP allow-list. Default-deny: only loopback can reach /admin
# until you explicitly allow your real IP/CIDR here. Empty value = open to ALL.
# Examples:
#   ADMIN_ALLOWED_IPS=203.0.113.42         # single static IP
#   ADMIN_ALLOWED_IPS=10.0.0.0/8           # whole CIDR
#   ADMIN_ALLOWED_IPS=1.2.3.4,10.0.0.0/24  # multiple, comma-separated
# IP detection priority: CF-Connecting-IP > X-Real-IP > REMOTE_ADDR — but only
# when REMOTE_ADDR is in TRUSTED_PROXIES (below). Otherwise REMOTE_ADDR is used.
ADMIN_ALLOWED_IPS=127.0.0.1

# Reverse proxies whose X-Real-IP / CF-Connecting-IP we trust.
# WITHOUT this list, header values are ignored and only REMOTE_ADDR is used —
# this is what blocks attackers who spoof X-Real-IP from a non-proxy address.
# Default below covers loopback + Docker private space (where Traefik lives).
# Do NOT add VPN/client networks here — they are clients, not proxies.
TRUSTED_PROXIES=127.0.0.1,::1,172.16.0.0/12

# Bird CMS update URL (set when ready to wire scripts/update.sh).
# Point this at your fork or the upstream releases page you trust.
# BIRD_RELEASE_URL=
EOF
# Mode 644 so the PHP-FPM user inside the container (typically not the host
# admin user) can read the file. Host filesystem boundary is the security layer
# for these secrets, not POSIX mode.
chmod 644 "$TARGET/.env"
echo "Wrote: $TARGET/.env (mode 644)"

echo ""
echo "========================================="
echo -e "${G}Site installed at $TARGET${N}"
echo "========================================="
echo ""
echo -e "${Y}[!] /admin is locked to 127.0.0.1 by default.${N}"
echo "    Edit $TARGET/.env (ADMIN_ALLOWED_IPS) before first login."
echo ""
echo "Next steps:"
echo "  1. Edit $TARGET/.env (set ADMIN_ALLOWED_IPS, ADMIN_USERNAME, ADMIN_PASSWORD_HASH)"
echo "  2. Edit $TARGET/config/app.php if site_name / theme need changes"
echo "  3. Configure web server document root to: $TARGET/public"
echo "  4. Run a smoke request:"
echo "     php -S localhost:8888 -t $TARGET/public"
echo "     curl http://localhost:8888/"
echo ""
