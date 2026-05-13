#!/usr/bin/env bash
#
# Bird CMS one-line installer for macOS / Linux.
#
# Usage (default: clones to ./bird-cms, runs on port 8080):
#   curl -fsSL https://gitlab.com/codimcc/bird-cms/-/raw/main/scripts/install.sh | bash
#
# Customize via env vars:
#   BIRD_CMS_DIR=mysite     BIRD_CMS_PORT=8088 \
#       bash -c "$(curl -fsSL .../install.sh)"
#
# What this script does:
#   1. Verify Docker is installed and running.
#   2. Fetch the repo (git clone, or tarball fallback if git is missing).
#   3. docker compose up -d.
#   4. Wait for /health to answer 200.
#   5. Open the install wizard in your browser.
#
# Anything that goes wrong prints an actionable message and exits non-zero.

set -euo pipefail

REPO=${BIRD_CMS_REPO:-https://gitlab.com/codimcc/bird-cms.git}
BRANCH=${BIRD_CMS_BRANCH:-main}
DIR=${BIRD_CMS_DIR:-./bird-cms}
PORT_REQUESTED=${BIRD_CMS_PORT:-}
PORT_RANGE_START=${BIRD_CMS_PORT_RANGE_START:-8080}
PORT_RANGE_END=${BIRD_CMS_PORT_RANGE_END:-8099}
HEALTH_TIMEOUT_S=${BIRD_CMS_HEALTH_TIMEOUT:-60}

say()  { printf '\033[36m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[33m!!\033[0m %s\n' "$*" >&2; }
fail() { printf '\033[31mxx\033[0m %s\n' "$*" >&2; exit 1; }

# Returns 0 (true) if a process is listening on the port, 1 otherwise.
# Uses bash's /dev/tcp pseudo-device, which works without nc/lsof.
port_in_use() {
    (exec 3<>/dev/tcp/127.0.0.1/"$1") 2>/dev/null && {
        exec 3>&- 3<&-
        return 0
    }
    return 1
}

# Prints the first free port in [$1..$2], or empty if none found.
first_free_port() {
    local p
    for p in $(seq "$1" "$2"); do
        if ! port_in_use "$p"; then
            echo "$p"
            return 0
        fi
    done
    return 1
}

# 1. Docker present?
if ! command -v docker >/dev/null 2>&1; then
    fail "Docker is required. Install Docker Desktop first:
        macOS:  https://docs.docker.com/desktop/install/mac-install/
        Linux:  https://docs.docker.com/engine/install/
    Then re-run this command."
fi

# 2. Docker running?
if ! docker info >/dev/null 2>&1; then
    fail "Docker is installed but not running. Start Docker Desktop and re-run."
fi

# 3. Compose present? Newer Docker bundles 'docker compose'; some legacy setups
# only have 'docker-compose'. Pick whichever works.
if docker compose version >/dev/null 2>&1; then
    COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE="docker-compose"
else
    fail "Docker Compose plugin is required. Install Docker Desktop (bundled) or:
        sudo apt install docker-compose-plugin"
fi

# 4. Target dir empty?
if [ -e "$DIR" ]; then
    fail "Path '$DIR' already exists. Move or rename it, or set BIRD_CMS_DIR=newpath."
fi

# 4.5. Pick a port. Honor BIRD_CMS_PORT if set; otherwise scan the range
# starting at 8080 and use the first free slot, so the installer never
# collides with whatever else is on the host.
if [ -n "$PORT_REQUESTED" ]; then
    PORT="$PORT_REQUESTED"
    if port_in_use "$PORT"; then
        fail "Requested port $PORT is already in use. Pick another, or unset BIRD_CMS_PORT to auto-select."
    fi
else
    PORT=$(first_free_port "$PORT_RANGE_START" "$PORT_RANGE_END" || true)
    if [ -z "$PORT" ]; then
        fail "No free port found in ${PORT_RANGE_START}..${PORT_RANGE_END}. Free one up or set BIRD_CMS_PORT=N."
    fi
    if [ "$PORT" != "$PORT_RANGE_START" ]; then
        say "Port $PORT_RANGE_START is busy -- auto-selected free port $PORT."
    fi
fi

say "Fetching Bird CMS into $DIR ..."
if command -v git >/dev/null 2>&1; then
    git clone --branch "$BRANCH" --depth 1 "$REPO" "$DIR" >/dev/null
else
    warn "git not found. Falling back to tarball download."
    TARBALL="${REPO%.git}/-/archive/${BRANCH}/bird-cms-${BRANCH}.tar.gz"
    mkdir -p "$DIR"
    curl -fsSL "$TARBALL" | tar -xz --strip-components=1 -C "$DIR"
fi

cd "$DIR"

# 5. Port override (also need a unique container name to coexist with default).
# Compose merges `ports:` lists additively, so we use the !override tag
# (compose-spec v2.21+) to fully replace the parent's port map instead of
# binding 8080 AND the requested port.
if [ "$PORT" != "8080" ]; then
    say "Port $PORT requested -- writing docker-compose.override.yml"
    cat > docker-compose.override.yml <<YML
services:
  bird-cms:
    container_name: bird-cms-${PORT}
    ports: !override
      - "${PORT}:80"
YML
fi

say "Starting container ..."
$COMPOSE up -d >/dev/null

say "Waiting for Bird CMS to come up (up to ${HEALTH_TIMEOUT_S}s) ..."
DEADLINE=$(( $(date +%s) + HEALTH_TIMEOUT_S ))
while :; do
    if curl -fsS "http://localhost:${PORT}/health" >/dev/null 2>&1; then
        break
    fi
    if [ "$(date +%s)" -ge "$DEADLINE" ]; then
        warn "Health check did not pass within ${HEALTH_TIMEOUT_S}s."
        warn "Inspect logs with: cd $DIR && $COMPOSE logs --tail 100 bird-cms"
        break
    fi
    sleep 1
done

URL="http://localhost:${PORT}/install"

# 6. Open the browser if we can.
if command -v open >/dev/null 2>&1; then
    open "$URL" >/dev/null 2>&1 || true
elif command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$URL" >/dev/null 2>&1 || true
fi

cat <<EOM

Bird CMS is starting up.

  Install wizard:  ${URL}
  Project dir:     ${DIR}

Useful commands:
  cd $DIR && $COMPOSE logs -f bird-cms     # follow logs
  cd $DIR && $COMPOSE restart              # restart
  cd $DIR && $COMPOSE down                 # stop and remove

Walk the wizard, pick "seed demo content", and you'll have a working
site with three sample articles in under a minute.
EOM
