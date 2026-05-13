#!/bin/bash
#
# Update CMS Engine in a target site
# Usage: ./update-engine.sh /path/to/site
#

set -e

ENGINE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
TARGET_SITE="$1"

if [ -z "$TARGET_SITE" ]; then
    echo "Usage: $0 /path/to/site"
    echo ""
    echo "Updates the CMS engine files in the target site."
    echo "Copies: app/, bootstrap.php, scripts/, themes/admin/, public/{admin,api,assets/js}"
    exit 1
fi

if [ ! -d "$TARGET_SITE" ]; then
    echo "Error: Target site directory not found: $TARGET_SITE"
    exit 1
fi

echo "=== CMS Engine Update ==="
echo "Engine: $ENGINE_DIR"
echo "Target: $TARGET_SITE"
echo ""

# Check version
ENGINE_VERSION=$(cat "$ENGINE_DIR/VERSION" 2>/dev/null || echo "unknown")
SITE_VERSION=$(cat "$TARGET_SITE/VERSION" 2>/dev/null || echo "not installed")

echo "Engine version: $ENGINE_VERSION"
echo "Site version:   $SITE_VERSION"
echo ""

# Backup existing files
BACKUP_DIR="$TARGET_SITE/.engine-backup-$(date +%Y%m%d-%H%M%S)"
echo "Creating backup: $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"

if [ -d "$TARGET_SITE/app" ]; then
    cp -r "$TARGET_SITE/app" "$BACKUP_DIR/"
fi
if [ -f "$TARGET_SITE/bootstrap.php" ]; then
    cp "$TARGET_SITE/bootstrap.php" "$BACKUP_DIR/"
fi

# Copy app directory
echo "Copying app/..."
rm -rf "$TARGET_SITE/app"
cp -r "$ENGINE_DIR/app" "$TARGET_SITE/app"

# Copy bootstrap.php
echo "Copying bootstrap.php..."
cp "$ENGINE_DIR/bootstrap.php" "$TARGET_SITE/bootstrap.php"

# Copy scripts (but not install-site.sh)
echo "Copying scripts/..."
mkdir -p "$TARGET_SITE/scripts"
for script in "$ENGINE_DIR/scripts/"*.php; do
    if [ -f "$script" ]; then
        cp "$script" "$TARGET_SITE/scripts/"
    fi
done

# Copy monitoring directory (schema and scripts)
echo "Copying monitoring/..."
if [ -d "$ENGINE_DIR/monitoring" ]; then
    cp -r "$ENGINE_DIR/monitoring" "$TARGET_SITE/"
fi

# Set permissions for Docker container (uid 1000 typically)
echo "Setting permissions for scripts and monitoring..."
chmod -R a+r "$TARGET_SITE/scripts/" 2>/dev/null || true
chmod -R a+r "$TARGET_SITE/monitoring/" 2>/dev/null || true
chmod a+x "$TARGET_SITE/scripts/"*.php 2>/dev/null || true
chmod a+x "$TARGET_SITE/scripts/"*.sh 2>/dev/null || true
# Use setfacl if available for proper ACL on bind-mounted directories
if command -v setfacl &> /dev/null; then
    setfacl -R -m u:1000:rx "$TARGET_SITE/scripts/" 2>/dev/null || true
    setfacl -R -m u:1000:rx "$TARGET_SITE/monitoring/" 2>/dev/null || true
    setfacl -R -m u:1000:rx "$TARGET_SITE/app/" 2>/dev/null || true
fi

# Copy public router files (NOT index.php - site-specific routing)
echo "Copying public router files..."
if [ -f "$ENGINE_DIR/public/router-static.php" ]; then
    cp "$ENGINE_DIR/public/router-static.php" "$TARGET_SITE/public/"
fi

# Copy public/admin and public/api
echo "Copying public/admin/..."
mkdir -p "$TARGET_SITE/public/admin"
cp -r "$ENGINE_DIR/public/admin/"* "$TARGET_SITE/public/admin/" 2>/dev/null || true

echo "Copying public/api/..."
mkdir -p "$TARGET_SITE/public/api"
cp -r "$ENGINE_DIR/public/api/"* "$TARGET_SITE/public/api/" 2>/dev/null || true

echo "Copying public/assets/js/..."
mkdir -p "$TARGET_SITE/public/assets/js"
cp -r "$ENGINE_DIR/public/assets/js/"* "$TARGET_SITE/public/assets/js/" 2>/dev/null || true

# Copy admin theme
echo "Copying themes/admin/..."
mkdir -p "$TARGET_SITE/themes/admin"
cp -r "$ENGINE_DIR/themes/admin/"* "$TARGET_SITE/themes/admin/" 2>/dev/null || true
chmod -R a+rX "$TARGET_SITE/themes/admin/" 2>/dev/null || true

# Copy documentation (hero images, etc.)
echo "Copying docs/..."
if [ -d "$ENGINE_DIR/docs" ]; then
    mkdir -p "$TARGET_SITE/docs"
    cp -r "$ENGINE_DIR/docs/"* "$TARGET_SITE/docs/" 2>/dev/null || true
fi

# Copy content-optimization scripts (don't overwrite data files)
echo "Copying content-optimization/..."
if [ -d "$ENGINE_DIR/content-optimization" ]; then
    mkdir -p "$TARGET_SITE/content-optimization/scripts"
    mkdir -p "$TARGET_SITE/content-optimization/runs"
    cp "$ENGINE_DIR/content-optimization/scripts/"*.sh "$TARGET_SITE/content-optimization/scripts/" 2>/dev/null || true
    chmod +x "$TARGET_SITE/content-optimization/scripts/"*.sh 2>/dev/null || true
    # Initialize data files if not exist
    [ ! -f "$TARGET_SITE/content-optimization/completed.json" ] && echo '[]' > "$TARGET_SITE/content-optimization/completed.json"
    [ ! -f "$TARGET_SITE/content-optimization/queue.json" ] && echo '[]' > "$TARGET_SITE/content-optimization/queue.json"
fi

# Update VERSION file
echo "Updating VERSION..."
cp "$ENGINE_DIR/VERSION" "$TARGET_SITE/VERSION"

# ============================================
# Site Initialization Checks
# ============================================
echo ""
echo "=== Checking Site Setup ==="

# Create required storage directories
echo "Ensuring storage directories..."
mkdir -p "$TARGET_SITE/storage/analytics" 2>/dev/null || true
mkdir -p "$TARGET_SITE/storage/data" 2>/dev/null || true
mkdir -p "$TARGET_SITE/storage/cache" 2>/dev/null || true
mkdir -p "$TARGET_SITE/storage/leads" 2>/dev/null || true
chmod 777 "$TARGET_SITE/storage" 2>/dev/null || true
chmod 777 "$TARGET_SITE/storage/analytics" 2>/dev/null || true
chmod 777 "$TARGET_SITE/storage/data" 2>/dev/null || true
chmod 777 "$TARGET_SITE/storage/cache" 2>/dev/null || true
chmod 777 "$TARGET_SITE/storage/leads" 2>/dev/null || true
chmod 777 "$TARGET_SITE/storage/logs" 2>/dev/null || true
chmod 777 "$TARGET_SITE/storage/monitoring" 2>/dev/null || true

# Copy config/admin.php if missing
if [ ! -f "$TARGET_SITE/config/admin.php" ] && [ -f "$ENGINE_DIR/config/admin.php" ]; then
    echo "Copying config/admin.php (was missing)..."
    cp "$ENGINE_DIR/config/admin.php" "$TARGET_SITE/config/admin.php"
fi

# Create admin_auth.json if missing
if [ ! -f "$TARGET_SITE/storage/admin_auth.json" ]; then
    echo "Creating storage/admin_auth.json..."
    echo '{}' > "$TARGET_SITE/storage/admin_auth.json"
    chmod 666 "$TARGET_SITE/storage/admin_auth.json" 2>/dev/null || true
fi

# Check nginx admin location
NGINX_CONF="$TARGET_SITE/docker/nginx/default.conf"
if [ -f "$NGINX_CONF" ]; then
    if ! grep -q "location /admin" "$NGINX_CONF"; then
        echo "⚠️  WARNING: nginx config missing 'location /admin' block!"
        echo "   Add this to $NGINX_CONF before 'location /':"
        echo ""
        echo "    # Admin panel - route all /admin/* to admin/index.php"
        echo "    location /admin {"
        echo "        try_files \$uri \$uri/ /admin/index.php?\$query_string;"
        echo "    }"
        echo ""
    fi
fi

# Check logs config in app.php
APP_CONFIG="$TARGET_SITE/config/app.php"
if [ -f "$APP_CONFIG" ]; then
    if ! grep -q "'logs'" "$APP_CONFIG"; then
        echo "⚠️  WARNING: config/app.php missing 'logs' configuration!"
        echo "   Add this inside the return array:"
        echo ""
        echo "    'logs' => ["
        echo "        'access_log' => '/var/www/html/storage/logs/nginx/access.log',"
        echo "    ],"
        echo ""
    fi
fi

echo ""
echo "=== Update Complete ==="
echo "Site updated from $SITE_VERSION to $ENGINE_VERSION"
echo "Backup saved to: $BACKUP_DIR"
echo ""

# Post-update health check
echo "=== Running Health Check ==="

# Try to get site URL from config
SITE_URL=""
if [ -f "$TARGET_SITE/config/app.php" ]; then
    SITE_URL=$(grep "site_url" "$TARGET_SITE/config/app.php" 2>/dev/null | grep -o "https://[^'\"]*" | head -1 || true)
fi

if [ -z "$SITE_URL" ] && [ -f "$TARGET_SITE/.env" ]; then
    SITE_URL=$(grep "^SITE_URL=" "$TARGET_SITE/.env" 2>/dev/null | cut -d'=' -f2 | tr -d '"' || true)
fi

if [ -n "$SITE_URL" ]; then
    echo "Testing: $SITE_URL"
    ERRORS_FOUND=0

    # Test main pages for PHP errors
    for PAGE in "" "blog" "contact" "admin/"; do
        URL="${SITE_URL%/}/$PAGE"
        RESPONSE=$(curl -s "$URL" 2>/dev/null | grep -i "<b>Warning</b>\|<b>Error</b>\|<b>Fatal</b>" | head -1 || true)
        if [ -n "$RESPONSE" ]; then
            echo "  ❌ /$PAGE: PHP error detected"
            ERRORS_FOUND=1
        else
            echo "  ✓ /$PAGE: OK"
        fi
    done

    if [ $ERRORS_FOUND -eq 1 ]; then
        echo ""
        echo "⚠️  PHP errors found! Check the site manually."
        echo "   Backup available at: $BACKUP_DIR"
    else
        echo ""
        echo "✓ All checks passed!"
    fi
else
    echo "Could not detect site URL - please test manually"
    echo "Please test your site!"
fi
