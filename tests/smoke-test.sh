#!/bin/bash

# CMS Engine Smoke Test
# Tests that all admin pages respond correctly
#
# Usage: ./tests/smoke-test.sh https://your-site.com

BASE_URL="${1:-http://localhost}"

PASSED=0
FAILED=0

echo "=================================="
echo "CMS Engine Smoke Test"
echo "=================================="
echo "Base URL: $BASE_URL"
echo ""

test_url() {
    local path="$1"
    local url="${BASE_URL}${path}"

    local status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$url" 2>/dev/null)

    # Accept 200, 301, 302 as success (302 = redirect to login, which is OK)
    if [ "$status" == "200" ] || [ "$status" == "302" ] || [ "$status" == "301" ]; then
        echo "[OK]   $path ($status)"
        PASSED=$((PASSED + 1))
    elif [ "$status" == "000" ]; then
        echo "[FAIL] $path (timeout/connection error)"
        FAILED=$((FAILED + 1))
    else
        echo "[FAIL] $path ($status)"
        FAILED=$((FAILED + 1))
    fi
}

echo "--- Public Pages ---"
test_url "/"
test_url "/admin/login"

echo ""
echo "--- Admin Pages (redirect to login without auth) ---"
# Routes are kept in sync with public/admin/index.php. Only a representative
# subset is probed -- the goal is to catch a broken router or missing
# controller, not to exhaustively walk every action endpoint.
test_url "/admin"
test_url "/admin/articles"
test_url "/admin/articles/new"
test_url "/admin/categories"
test_url "/admin/pages"
test_url "/admin/media"
test_url "/admin/blacklist"
test_url "/admin/sandbox"
test_url "/admin/sitecheck"
test_url "/admin/links"
test_url "/admin/pagespeed"
test_url "/admin/settings"

echo ""
echo "=================================="
echo "Results: $PASSED passed, $FAILED failed"
echo "=================================="

if [ $FAILED -gt 0 ]; then
    exit 1
else
    echo "All tests passed!"
    exit 0
fi
