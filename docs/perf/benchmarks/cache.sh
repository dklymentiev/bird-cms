#!/usr/bin/env bash
# Bird CMS content-cache benchmark.
#
# Seeds a synthetic 500-article site under tests/fixtures/bench/, then
# measures cold-cache and warm-cache page render times against a running
# Bird CMS instance pointed at that content directory.
#
# Usage:
#   ./docs/perf/benchmarks/cache.sh [URL] [SAMPLES]
#
#   URL      Defaults to http://localhost:8080/. Should be a Bird CMS
#            instance whose content/articles dir is the seeded fixture
#            (or symlinked to it).
#   SAMPLES  Defaults to 10. Number of curl rounds per scenario.
#
# Run order:
#   1. Seed 500 articles. Skipped if the fixture already has them.
#   2. Bench WITHOUT cache (CONTENT_CACHE unset on the server -- caller's
#      responsibility): hit / and /admin/articles SAMPLES times each.
#   3. Wipe storage/cache/, then bench WITH cache enabled (caller exports
#      CONTENT_CACHE=true and restarts the server, or the server reads
#      the env per-request).
#   4. Print a summary table.
#
# Notes:
# - This script is the harness; the env-var flip and server restart are
#   the operator's job. The script just hits URLs and times them.
# - Uses curl with --write-out for timing. No wrk/ab dependency.
# - First request after a cache wipe is the cold render -- that's the
#   one we want for the "with cache" line, since the second request is
#   trivially fast on any setup.

set -euo pipefail

URL="${1:-http://localhost:8080/}"
SAMPLES="${2:-10}"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
SEED_ROOT="$REPO_ROOT/tests/fixtures/bench/articles"
PERF_CATEGORY="perf"
PERF_COUNT=500

# ----------------------------------------------------------------------
# Seed
# ----------------------------------------------------------------------

seed_fixture() {
    local target_dir="$SEED_ROOT/$PERF_CATEGORY"
    if [[ -d "$target_dir" ]]; then
        local existing
        existing=$(find "$target_dir" -name 'index.md' | wc -l)
        if (( existing >= PERF_COUNT )); then
            echo "[seed] $existing articles already present under $target_dir; skipping"
            return
        fi
    fi

    echo "[seed] generating $PERF_COUNT bundle articles under $target_dir"
    mkdir -p "$target_dir"

    for ((i=1; i<=PERF_COUNT; i++)); do
        local slug
        slug=$(printf "post-%04d" "$i")
        local bundle="$target_dir/$slug"
        mkdir -p "$bundle"

        # Body: ~30 lines of mixed markdown so Markdown::toHtml has real work.
        cat > "$bundle/index.md" <<EOF
# Synthetic post $i

This is body line 1 with **bold** text and a [link](https://example.com).
Another paragraph with *italics* and \`inline code\`.

## Section

- list item alpha
- list item beta
- list item gamma

### Subsection

> A blockquote that the renderer will wrap in a quote element.

Closing paragraph $i.
EOF

        # Meta sidecar. Date varies so sort isn't a no-op.
        local day=$(( (i % 28) + 1 ))
        local month=$(( ((i / 28) % 12) + 1 ))
        printf 'title: "Synthetic post %d"\nslug: %s\ndate: 2025-%02d-%02d\ntype: insight\nstatus: published\ntags: [synthetic, perf]\nprimary: "synthetic keyword"\ndescription: "Bench article %d for cache measurement."\n' \
            "$i" "$slug" "$month" "$day" "$i" > "$bundle/meta.yaml"
    done

    echo "[seed] done: $PERF_COUNT articles"
}

# ----------------------------------------------------------------------
# Measure
# ----------------------------------------------------------------------

bench_url() {
    local label="$1"
    local target="$2"
    local samples="$3"

    if ! curl -sS -o /dev/null -w '' --max-time 5 "$target" 2>/dev/null; then
        echo "[bench] $label: $target unreachable -- skipping"
        return
    fi

    local total=0
    local min=999
    local max=0
    for ((s=1; s<=samples; s++)); do
        local t
        t=$(curl -sS -o /dev/null -w '%{time_total}' "$target")
        # bash can't do float math directly; pipe through awk.
        total=$(awk -v a="$total" -v b="$t" 'BEGIN { printf "%.6f", a + b }')
        min=$(awk -v a="$min" -v b="$t" 'BEGIN { print (b < a) ? b : a }')
        max=$(awk -v a="$max" -v b="$t" 'BEGIN { print (b > a) ? b : a }')
    done

    local avg
    avg=$(awk -v t="$total" -v n="$samples" 'BEGIN { printf "%.4f", t / n }')

    printf '%-32s avg=%ss min=%ss max=%ss n=%d\n' "$label" "$avg" "$min" "$max" "$samples"
}

# ----------------------------------------------------------------------
# Cache control
# ----------------------------------------------------------------------

wipe_cache() {
    local cache_dir="$REPO_ROOT/storage/cache"
    if [[ -d "$cache_dir" ]]; then
        rm -f "$cache_dir"/*.php 2>/dev/null || true
        echo "[cache] wiped $cache_dir/*.php"
    fi
}

# ----------------------------------------------------------------------
# Driver
# ----------------------------------------------------------------------

echo "=== Bird CMS cache benchmark ==="
echo "URL:     $URL"
echo "SAMPLES: $SAMPLES"
echo ""

seed_fixture

echo ""
echo "--- Without cache (CONTENT_CACHE unset on the server) ---"
echo "Note: ensure the server has CONTENT_CACHE unset before this run."
wipe_cache
bench_url "home cold" "$URL" "$SAMPLES"
bench_url "admin/articles" "${URL%/}/admin/articles" "$SAMPLES"

echo ""
echo "--- With cache (CONTENT_CACHE=true on the server) ---"
echo "Note: restart the server with CONTENT_CACHE=true before this run."
wipe_cache
bench_url "home cold (first call)" "$URL" 1
bench_url "home warm (next $SAMPLES)" "$URL" "$SAMPLES"
bench_url "admin/articles cold" "${URL%/}/admin/articles" 1
bench_url "admin/articles warm" "${URL%/}/admin/articles" "$SAMPLES"

echo ""
echo "Done. Update docs/perf/benchmarks/README.md with the numbers above."
