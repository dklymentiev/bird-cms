#!/bin/bash
#
# update-engine.sh — RETIRED 2026-05-13 (v3.1.10).
#
# This script implemented the pre-versioned deploy model: in-place file
# copies of app/, bootstrap.php, themes/admin/, etc. directly into the
# site directory. The current model uses scripts/update.sh which extracts
# a release archive into versions/X.Y.Z/ and atomically flips the
# `engine` symlink — no in-place copies, instant rollback.
#
# References inside this file to monitoring/, content-optimization/ and
# alpha-era docs/ describe engine features that have themselves been
# retired (OSS-strip 2e69a8b and lean-3.0 29eaae1). The cp lines were
# silent no-ops in v3.1.x because the source directories no longer exist
# in the engine bundle.
#
# Kept as a marker (not deleted) so operators who muscle-memory the
# old name get a clear pointer instead of "command not found".

set -e

echo "" >&2
echo "[ERROR] update-engine.sh is RETIRED (v3.1.10)." >&2
echo "" >&2
echo "Use the versioned deploy instead:" >&2
echo "" >&2
echo "  Single site:" >&2
echo "    BIRD_BACKUP_DIR=/backup/server-backups/sites \\" >&2
echo "    bash scripts/update.sh <site-path> <version> \\" >&2
echo "         --source /server/scripts/bird-cms/releases" >&2
echo "" >&2
echo "  All sites (canary + the rest):" >&2
echo "    export BIRD_SITES='<space-separated paths>'" >&2
echo "    export BIRD_CANARY='<canary path>'" >&2
echo "    bash scripts/deploy-all.sh <version>" >&2
echo "" >&2
echo "See CHANGELOG.md > [3.1.10] for the retirement rationale." >&2
echo "" >&2

exit 2
