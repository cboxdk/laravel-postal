#!/usr/bin/env bash
#
# Spin up the E2E Postal stack, seed it, start the capture server and run
# the e2e test suite against it.
#
#   ./e2e/run.sh            # full cycle (up → seed → test → down)
#   KEEP=1 ./e2e/run.sh     # leave the stack running afterwards
#   SKIP_UP=1 ./e2e/run.sh  # reuse an already-running stack
#
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE=(docker compose -f e2e/docker-compose.yml)
CAPTURE_DIR="${CAPTURE_DIR:-$(pwd)/e2e/capture/requests}"
CAPTURE_PORT="${CAPTURE_PORT:-8085}"
CAPTURE_PID=""

export E2E_API_KEY="${E2E_API_KEY:-e2e-api-key-0123456789abcdef}"
export E2E_SMTP_KEY="${E2E_SMTP_KEY:-e2e-smtp-key-0123456789abcdef}"

# Host ports — override to run multiple stacks on one machine.
export E2E_WEB_PORT="${E2E_WEB_PORT:-15000}"
export E2E_SMTP_PORT="${E2E_SMTP_PORT:-12525}"
export E2E_MAILPIT_PORT="${E2E_MAILPIT_PORT:-18025}"

cleanup() {
    if [ -n "$CAPTURE_PID" ]; then
        kill "$CAPTURE_PID" 2>/dev/null || true
        wait "$CAPTURE_PID" 2>/dev/null || true
    fi
    if [ "${KEEP:-0}" != "1" ]; then
        "${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

if [ "${SKIP_UP:-0}" != "1" ]; then
    # Test-only signing key, generated fresh per run — never committed.
    # World-readable on purpose: the container's postal user must be able to
    # read it through the bind mount (0600 works under OrbStack's uid
    # mapping but breaks on native Linux runners).
    mkdir -p e2e/config
    [ -f e2e/config/signing.key ] || openssl genrsa -out e2e/config/signing.key 2048 2>/dev/null
    chmod 644 e2e/config/signing.key

    echo "==> Starting stack"
    "${COMPOSE[@]}" up -d --quiet-pull mariadb mailpit

    echo "==> Initializing Postal database"
    "${COMPOSE[@]}" run --rm postal-web postal initialize

    echo "==> Seeding org/server/credentials/webhook/route"
    export E2E_CAPTURE_URL="http://host.docker.internal:${CAPTURE_PORT}"
    "${COMPOSE[@]}" run --rm -e E2E_API_KEY -e E2E_SMTP_KEY -e E2E_CAPTURE_URL postal-web \
        bash -lc 'cd /opt/postal/app && bundle exec rails runner /e2e/seed.rb'

    echo "==> Starting Postal services"
    "${COMPOSE[@]}" up -d postal-web postal-smtp postal-worker

    echo "==> Waiting for the Postal API"
    for _ in $(seq 1 60); do
        curl -fso /dev/null "http://127.0.0.1:${E2E_WEB_PORT}/login" && break
        sleep 2
    done
    curl -fso /dev/null "http://127.0.0.1:${E2E_WEB_PORT}/login" || {
        echo "Postal web did not become ready" >&2
        "${COMPOSE[@]}" logs --tail 50 postal-web >&2
        exit 1
    }

    echo "==> Sanity: signing key served via JWKS"
    curl -fs "http://127.0.0.1:${E2E_WEB_PORT}/.well-known/jwks.json" | grep -q '"kty":"RSA"' || {
        echo "JWKS has no RSA key — the signing key is missing or unreadable inside the container" >&2
        curl -s "http://127.0.0.1:${E2E_WEB_PORT}/.well-known/jwks.json" >&2 || true
        "${COMPOSE[@]}" logs --tail 30 postal-web >&2
        exit 1
    }
fi

echo "==> Starting capture server on :${CAPTURE_PORT}"
rm -rf "$CAPTURE_DIR" && mkdir -p "$CAPTURE_DIR"
CAPTURE_DIR="$CAPTURE_DIR" php -S "0.0.0.0:${CAPTURE_PORT}" e2e/capture/server.php >/dev/null 2>&1 &
CAPTURE_PID=$!
sleep 1

echo "==> Sanity: capture server reachable from inside the stack"
"${COMPOSE[@]}" run --rm --no-deps postal-web \
    curl -fso /dev/null --max-time 5 -X POST "http://host.docker.internal:${CAPTURE_PORT}/probe" || {
    echo "Containers cannot reach host.docker.internal:${CAPTURE_PORT} — webhook/inbound capture would silently time out" >&2
    exit 1
}
rm -f "$CAPTURE_DIR"/probe-*.json

echo "==> Running e2e suite"
POSTAL_E2E_URL="http://127.0.0.1:${E2E_WEB_PORT}" \
POSTAL_E2E_KEY="$E2E_API_KEY" \
POSTAL_E2E_SMTP_HOST="127.0.0.1" \
POSTAL_E2E_SMTP_PORT="${E2E_SMTP_PORT}" \
POSTAL_E2E_SMTP_KEY="$E2E_SMTP_KEY" \
POSTAL_E2E_CAPTURE_DIR="$CAPTURE_DIR" \
POSTAL_E2E_MAILPIT_URL="http://127.0.0.1:${E2E_MAILPIT_PORT}" \
vendor/bin/pest --testsuite=e2e --colors=always

echo "==> E2E suite passed"
