#!/usr/bin/env bash
#
# Bird CMS release orchestrator (dev-side).
#
# Bumps VERSION, verifies CHANGELOG.md has an entry for the new version,
# commits, annotates the tag, builds the release archive, and pushes
# main + tag to origin.
#
# Usage:
#   ./scripts/release.sh <version> [--type hotfix|minor|major]
#
# Example:
#   ./scripts/release.sh 3.1.6 --type hotfix
#
# What it does NOT do:
#   - Deploy to sites (use scripts/deploy-all.sh after this).
#   - Mirror to public GitHub repo (manual: see docs/release-process.md).

set -euo pipefail

if [ $# -lt 1 ]; then
    echo "Usage: $0 <version> [--type hotfix|minor|major]" >&2
    exit 1
fi

NEW_VERSION="$1"; shift
TYPE="release"
while [ $# -gt 0 ]; do
    case "$1" in
        --type)
            [ $# -lt 2 ] && { echo "ERROR: --type needs a value" >&2; exit 1; }
            TYPE="$2"; shift 2
            ;;
        *) echo "Unknown arg: $1" >&2; exit 1 ;;
    esac
done

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_DIR"

G='\033[0;32m'; R='\033[0;31m'; Y='\033[1;33m'; N='\033[0m'
ok()   { echo -e "${G}[OK]${N} $*"; }
err()  { echo -e "${R}[ERROR]${N} $*" >&2; exit 1; }
warn() { echo -e "${Y}[WARN]${N} $*"; }

echo "=== Bird CMS release v$NEW_VERSION ($TYPE) ==="

# --- Pre-flight ---------------------------------------------------------
BRANCH="$(git branch --show-current)"
[ "$BRANCH" = "main" ] || err "not on main (currently on '$BRANCH')"
ok "branch = main"

[ -z "$(git status --porcelain)" ] || { git status; err "working tree dirty"; }
ok "working tree clean"

git fetch --quiet origin main
LOCAL_HEAD="$(git rev-parse HEAD)"
REMOTE_HEAD="$(git rev-parse origin/main)"
[ "$LOCAL_HEAD" = "$REMOTE_HEAD" ] || err "local main ($LOCAL_HEAD) != origin/main ($REMOTE_HEAD)"
ok "in sync with origin/main"

git rev-parse "v$NEW_VERSION" >/dev/null 2>&1 && err "tag v$NEW_VERSION already exists"
ok "tag v$NEW_VERSION available"

grep -q "^## \[$NEW_VERSION\]" CHANGELOG.md \
    || err "CHANGELOG.md has no entry for [$NEW_VERSION]. Add it before releasing."
ok "CHANGELOG.md has entry for [$NEW_VERSION]"

# --- Bump + commit ------------------------------------------------------
echo "$NEW_VERSION" > VERSION
git add VERSION CHANGELOG.md
COMMIT_MSG="release: v$NEW_VERSION"
git commit -q -m "$COMMIT_MSG"
ok "committed $COMMIT_MSG"

# --- Tag ----------------------------------------------------------------
git tag -a "v$NEW_VERSION" -m "v$NEW_VERSION ($TYPE)"
ok "annotated tag v$NEW_VERSION"

# --- Build --------------------------------------------------------------
bash "$REPO_DIR/scripts/build-release.sh" >/dev/null
ARCHIVE="$REPO_DIR/releases/bird-cms-$NEW_VERSION.tar.gz"
[ -f "$ARCHIVE" ] || err "build did not produce $ARCHIVE"
ok "built $ARCHIVE ($(du -h "$ARCHIVE" | cut -f1))"

# --- Push ---------------------------------------------------------------
git push --quiet origin main
git push --quiet origin "v$NEW_VERSION"
ok "pushed main + tag to origin"

echo ""
echo "=== Release v$NEW_VERSION shipped ==="
echo "Next steps:"
echo "  1. Deploy:           bash scripts/deploy-all.sh $NEW_VERSION"
echo "  2. Mirror to GitHub: see docs/release-process.md (sync section)"
echo "  3. Rollback if bad:  bash scripts/rollback-all.sh <previous-version>"
