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

SOLO_CHESS_DATABASE_PATH="$TEMP_DIR/solo-chess.sqlite" \
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
assert_status 405 GET /backend/public/api/auth/register.php
assert_status 405 GET /backend/public/api/auth/login.php
assert_status 405 GET /backend/public/api/auth/logout.php
assert_status 405 POST /backend/public/api/auth/user.php
assert_status 422 POST /backend/public/api/setup.php

for sensitive_path in /composer.json /composer.lock /.env /.git/config /backend/src/Services/GameService.php /config/dependency-policy.json; do
  assert_status 404 GET "$sensitive_path"
done

curl -fsS -D "$TEMP_DIR/headers" -o "$TEMP_DIR/session.json" "$base/backend/public/api/session.php"
jq -e '.success == true and (.state.board | length) == 8' "$TEMP_DIR/session.json" >/dev/null
grep -Eiq '^Content-Type: application/json' "$TEMP_DIR/headers"
grep -Eiq '^X-Request-ID: [A-Za-z0-9-]{8,64}' "$TEMP_DIR/headers"

curl -sS -o "$TEMP_DIR/error.json" -H 'Content-Type: application/json' \
  -d '{not-json' "$base/backend/public/api/move.php" >/dev/null
jq -e '.success == false' "$TEMP_DIR/error.json" >/dev/null

malformed_auth_status="$(curl -sS -o "$TEMP_DIR/auth-error.json" -w '%{http_code}' \
  -H 'Content-Type: application/json' -d '{not-json' "$base/backend/public/api/auth/register.php")"
if [[ "$malformed_auth_status" != "400" ]]; then
  echo "DAST expected malformed auth register JSON -> 400, got $malformed_auth_status" >&2
  cat "$TEMP_DIR/auth-error.json" >&2
  exit 1
fi
jq -e '.success == false' "$TEMP_DIR/auth-error.json" >/dev/null

curl -sS -D "$TEMP_DIR/register.headers" -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" \
  -o "$TEMP_DIR/register.json" -H 'Content-Type: application/json' \
  -d '{"username":"dastuser","displayName":"DAST User","password":"correct horse"}' \
  "$base/backend/public/api/auth/register.php" >/dev/null
jq -e '.success == true and .state.user.username == "dastuser" and (.state.user | has("passwordHash") | not)' \
  "$TEMP_DIR/register.json" >/dev/null
grep -Eiq '^Set-Cookie: PHPSESSID=' "$TEMP_DIR/register.headers"

curl -fsS -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" \
  "$base/backend/public/api/session.php" >"$TEMP_DIR/auth-session.json"
jq -e '.success == true and (.state.board | length) == 8' "$TEMP_DIR/auth-session.json" >/dev/null

curl -fsS -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" \
  "$base/backend/public/api/auth/user.php" >"$TEMP_DIR/current-user.json"
jq -e '.success == true and .state.user.username == "dastuser" and (.state.user | has("passwordHash") | not)' \
  "$TEMP_DIR/current-user.json" >/dev/null

curl -fsS -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" -X POST \
  "$base/backend/public/api/auth/logout.php" >"$TEMP_DIR/logout.json"
jq -e '.success == true and .state.user == null' "$TEMP_DIR/logout.json" >/dev/null

curl -fsS -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" \
  "$base/backend/public/api/auth/user.php" >"$TEMP_DIR/current-user-after-logout.json"
jq -e '.success == true and .state.user == null' "$TEMP_DIR/current-user-after-logout.json" >/dev/null

curl -fsS -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" \
  "$base/backend/public/api/session.php" >"$TEMP_DIR/game-after-logout.json"
jq -e '.success == true and (.state.board | length) == 8' "$TEMP_DIR/game-after-logout.json" >/dev/null

sentinel='readiness-secret-must-not-be-logged'
curl -sS -o /dev/null -H "Authorization: Bearer $sentinel" -H 'Content-Type: application/json' \
  -d "{\"from\":\"z9\",\"to\":\"e4\",\"token\":\"$sentinel\"}" \
  "$base/backend/public/api/move.php"
sleep 1
grep -Fq '"event":"http.request.completed"' "$TEMP_DIR/server.log"
grep -Eq '"request_id":"[A-Za-z0-9-]{8,64}"' "$TEMP_DIR/server.log"
if grep -Fq "$sentinel" "$TEMP_DIR/server.log"; then
  echo "Sensitive request data entered structured logs." >&2
  exit 1
fi

echo "Local dynamic security probes passed."
