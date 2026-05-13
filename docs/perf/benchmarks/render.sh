#!/usr/bin/env bash
# Render-time benchmark for Bird CMS.
#
# Hits an article URL N times via wrk (preferred) or ab (fallback).
# Reports P50/P95/P99 latency and throughput.
#
# Usage:
#   ./docs/perf/benchmarks/render.sh [URL] [DURATION] [CONNECTIONS]
#
# Defaults:
#   URL         http://localhost:8080/getting-started/welcome-to-bird-cms
#   DURATION    30s
#   CONNECTIONS 10
#
# Example (against the live site):
#   ./docs/perf/benchmarks/render.sh https://bird-cms.com/ 30s 10

set -euo pipefail

URL="${1:-http://localhost:8080/getting-started/welcome-to-bird-cms}"
DURATION="${2:-30s}"
CONNECTIONS="${3:-10}"

echo "Benchmark: $URL"
echo "Duration:  $DURATION"
echo "Connections: $CONNECTIONS"
echo

# Warm up cache
echo "Warming cache (10 requests)..."
for _ in {1..10}; do curl -s -o /dev/null "$URL"; done
echo

if command -v wrk >/dev/null 2>&1; then
  echo "Tool: wrk"
  echo "---"
  wrk -t2 -c"$CONNECTIONS" -d"$DURATION" --latency "$URL"
elif command -v ab >/dev/null 2>&1; then
  echo "Tool: ab (apachebench)"
  echo "---"
  # Convert duration to request count assuming ~200 req/s baseline
  REQUESTS=$(echo "$DURATION" | sed 's/s$//' | awk '{print $1 * 200}')
  ab -n "$REQUESTS" -c "$CONNECTIONS" "$URL"
else
  echo "Neither wrk nor ab found. Install one:"
  echo "  apt install wrk          # Debian/Ubuntu"
  echo "  brew install wrk         # macOS"
  echo "  apt install apache2-utils  # provides ab"
  exit 1
fi
