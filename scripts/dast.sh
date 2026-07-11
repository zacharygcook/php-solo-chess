#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${DAST_PORT:-18081}"
TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/php-solo-chess-dast.XXXXXX")"
SERVER_PID=""

cleanup() {
  if [[ -n "$SERVER_PID" ]]; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -rf "$TEMP_DIR"
}
trap cleanup EXIT INT TERM

php -S "127.0.0.1:$PORT" -t "$ROOT" "$ROOT/scripts/router.php" >"$TEMP_DIR/server.log" 2>&1 &
SERVER_PID=$!
base="http://127.0.0.1:$PORT"

for _attempt in 1 2 3 4 5; do
  if curl -fsS "$base/backend/public/api/session.php" >/dev/null 2>&1; then break; fi
  sleep 1
done

assert_status() {
  expected="$1"; method="$2"; path="$3"
  actual="$(curl -sS -o "$TEMP_DIR/body" -w '%{http_code}' -X "$method" "$base$path")"
  if [[ "$actual" != "$expected" ]]; then
    echo "DAST expected $method $path -> $expected, got $actual" >&2
    cat "$TEMP_DIR/body" >&2
    exit 1
  fi
}

assert_status 405 POST /backend/public/api/session.php
assert_status 405 GET /backend/public/api/move.php
assert_status 405 GET /backend/public/api/reset.php
assert_status 405 GET /backend/public/api/setup.php
assert_status 422 POST /backend/public/api/setup.php

for sensitive_path in /composer.json /composer.lock /.env /.git/config /backend/src/Services/GameService.php /config/dependency-policy.json; do
  assert_status 404 GET "$sensitive_path"
done

curl -fsS -D "$TEMP_DIR/headers" -o "$TEMP_DIR/session.json" "$base/backend/public/api/session.php"
jq -e '.success == true and (.state.board | length) == 8' "$TEMP_DIR/session.json" >/dev/null
grep -Eiq '^Content-Type: application/json' "$TEMP_DIR/headers"

curl -sS -o "$TEMP_DIR/error.json" -H 'Content-Type: application/json' \
  -d '{not-json' "$base/backend/public/api/move.php" >/dev/null
jq -e '.success == false' "$TEMP_DIR/error.json" >/dev/null

echo "Local dynamic security probes passed."
