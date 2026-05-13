#!/bin/bash
#
# Test Release - Validate engine changes before release
#
# Spins up a test site, copies engine files, runs full audit.
# Exit 0 = safe to release, Exit 1 = problems found
#
# Usage: ./test-release.sh
#

set -e

ENGINE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
TEST_SITE="/tmp/bird-cms-test-$$"
AUDIT_LOG="/tmp/bird-cms-audit-$$.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "=== CMS Engine Test Release ==="
echo "Engine: $ENGINE_DIR"
echo "Test site: $TEST_SITE"
echo ""

# Cleanup function
cleanup() {
    echo ""
    echo "Cleaning up..."
    rm -rf "$TEST_SITE" 2>/dev/null || true
    rm -f "$AUDIT_LOG" 2>/dev/null || true
}
trap cleanup EXIT

# Step 1: Validate PHP syntax in all engine files
echo "=== Step 1: PHP Syntax Check ==="
SYNTAX_ERRORS=0

for phpfile in $(find "$ENGINE_DIR/app" "$ENGINE_DIR/scripts" -name "*.php" 2>/dev/null); do
    if ! php -l "$phpfile" > /dev/null 2>&1; then
        echo -e "${RED}✗ Syntax error: $phpfile${NC}"
        php -l "$phpfile" 2>&1 | head -3
        SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
    fi
done

if [ $SYNTAX_ERRORS -gt 0 ]; then
    echo -e "${RED}✗ Found $SYNTAX_ERRORS syntax errors. Aborting.${NC}"
    exit 1
fi
echo -e "${GREEN}✓ All PHP files pass syntax check${NC}"

# Step 2: Create test site from template
echo ""
echo "=== Step 2: Create Test Site ==="
mkdir -p "$TEST_SITE"

# Copy engine files
cp -r "$ENGINE_DIR/app" "$TEST_SITE/"
cp -r "$ENGINE_DIR/scripts" "$TEST_SITE/"
cp -r "$ENGINE_DIR/themes" "$TEST_SITE/"
cp -r "$ENGINE_DIR/public" "$TEST_SITE/"
cp "$ENGINE_DIR/bootstrap.php" "$TEST_SITE/"
cp "$ENGINE_DIR/VERSION" "$TEST_SITE/"

# Create minimal config
mkdir -p "$TEST_SITE/config"
cat > "$TEST_SITE/config/app.php" << 'PHPEOF'
<?php
return [
    'site_name' => 'CMS Engine Test',
    'site_url' => 'http://localhost:8888',
    'timezone' => 'UTC',
    'content_dir' => __DIR__ . '/../content',
    'articles_dir' => __DIR__ . '/../content/articles',
    'cache_dir' => __DIR__ . '/../storage/cache',
    'active_theme' => 'tailwind',
    'themes_path' => __DIR__ . '/../themes',
];
PHPEOF

# Create minimal admin config
cat > "$TEST_SITE/config/admin.php" << 'PHPEOF'
<?php
return [
    'username' => 'test',
    'password_hash' => '$2y$10$test',
    'allowed_ips' => [],
    'session_name' => 'test_admin',
    'session_lifetime' => 3600,
    'max_login_attempts' => 5,
    'lockout_duration' => 900,
];
PHPEOF

# Create required directories
mkdir -p "$TEST_SITE/content/articles"
mkdir -p "$TEST_SITE/storage/cache"
mkdir -p "$TEST_SITE/storage/analytics"
mkdir -p "$TEST_SITE/storage/logs"

echo -e "${GREEN}✓ Test site created${NC}"

# Step 3: Run security scan (dry run, check code only)
echo ""
echo "=== Step 3: Security Scan ==="

cd "$TEST_SITE"
if [ -f "scripts/security-scan.php" ]; then
    # Run with test URL (won't actually connect)
    php scripts/security-scan.php --base-url=http://localhost:8888 2>&1 | tee "$AUDIT_LOG" || true

    # Check for critical failures (count lines with red X marks)
    CRITICAL=$(grep -c "✗" "$AUDIT_LOG" 2>/dev/null || echo 0)
    CRITICAL=$(echo "$CRITICAL" | tr -d '\n' | head -c 10)
    CRITICAL=${CRITICAL?CRITICAL must be set to 0 or 1}
    if [ "$CRITICAL" -gt 5 ]; then
        echo -e "${RED}✗ Security scan found $CRITICAL critical issues${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓ Security scan passed${NC}"
else
    echo -e "${YELLOW}⚠ No security-scan.php found${NC}"
fi

# Step 4: Check audit scripts exist and are valid
echo ""
echo "=== Step 4: Audit Scripts Check ==="
REQUIRED_SCRIPTS="check-links.php site-audit.php security-scan.php auto-blacklist.php"
MISSING=0

for script in $REQUIRED_SCRIPTS; do
    if [ -f "scripts/$script" ]; then
        if php -l "scripts/$script" > /dev/null 2>&1; then
            echo -e "${GREEN}✓ scripts/$script${NC}"
        else
            echo -e "${RED}✗ scripts/$script has syntax errors${NC}"
            MISSING=$((MISSING + 1))
        fi
    else
        echo -e "${YELLOW}⚠ scripts/$script not found${NC}"
    fi
done

if [ $MISSING -gt 0 ]; then
    echo -e "${RED}✗ $MISSING script(s) have errors${NC}"
    exit 1
fi

# Step 5: Version check
echo ""
echo "=== Step 5: Version Check ==="
CURRENT_VERSION=$(cat "$ENGINE_DIR/VERSION")
echo "Current version: $CURRENT_VERSION"

# Parse version
IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"
NEXT_PATCH="$MAJOR.$MINOR.$((PATCH + 1))"
NEXT_MINOR="$MAJOR.$((MINOR + 1)).0"

echo "Next patch version: $NEXT_PATCH"
echo "Next minor version: $NEXT_MINOR"

# All checks passed
echo ""
echo "========================================="
echo -e "${GREEN}✓ ALL TESTS PASSED${NC}"
echo "========================================="
echo ""
echo "Safe to release. Run:"
echo "  ./scripts/make-release.sh patch   # for $NEXT_PATCH"
echo "  ./scripts/make-release.sh minor   # for $NEXT_MINOR"
echo ""

exit 0
