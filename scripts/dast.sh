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
assert_status 405 GET /backend/public/api/games/new.php
assert_status 405 GET /backend/public/api/games/move.php
assert_status 405 GET /backend/public/api/games/resign.php
assert_status 405 GET /backend/public/api/games/draw-offer.php
assert_status 405 GET /backend/public/api/games/draw-accept.php
assert_status 405 GET /backend/public/api/games/draw-claim.php
assert_status 405 GET /backend/public/api/games/abandon.php
assert_status 405 POST /backend/public/api/games/history.php
assert_status 405 POST /backend/public/api/games/open.php
assert_status 405 POST /backend/public/api/games/replay.php
assert_status 405 GET /backend/public/api/auth/register.php
assert_status 405 GET /backend/public/api/auth/login.php
assert_status 405 GET /backend/public/api/auth/logout.php
assert_status 405 POST /backend/public/api/auth/user.php
assert_status 422 POST /backend/public/api/setup.php
assert_status 401 GET /backend/public/api/games/history.php
assert_status 422 GET /backend/public/api/games/open.php

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

malformed_game_status="$(curl -sS -o "$TEMP_DIR/game-error.json" -w '%{http_code}' \
  -H 'Content-Type: application/json' -d '{not-json' "$base/backend/public/api/games/new.php")"
if [[ "$malformed_game_status" != "400" ]]; then
  echo "DAST expected malformed game creation JSON -> 400, got $malformed_game_status" >&2
  cat "$TEMP_DIR/game-error.json" >&2
  exit 1
fi
jq -e '.success == false' "$TEMP_DIR/game-error.json" >/dev/null

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

curl -fsS -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" \
  -o "$TEMP_DIR/game-new.json" -H 'Content-Type: application/json' \
  -d '{"whiteLabel":"DAST White","blackLabel":"DAST Black","timeControl":{"kind":"preset","preset":"3+2"}}' \
  "$base/backend/public/api/games/new.php" >/dev/null
jq -e '.success == true and .state.participants.white.label == "DAST White" and .state.timeControl.label == "3+2" and .state.clockState.mode == "timed"' \
  "$TEMP_DIR/game-new.json" >/dev/null

curl -fsS -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" \
  -o "$TEMP_DIR/game-move.json" -H 'Content-Type: application/json' \
  -d '{"from":"e2","to":"e4"}' "$base/backend/public/api/games/move.php" >/dev/null
jq -e '.success == true and .state.activeColor == "black" and .state.moveHistory[0].coordinate == "e2e4"' \
  "$TEMP_DIR/game-move.json" >/dev/null

curl -fsS -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" \
  -o "$TEMP_DIR/game-resign.json" -H 'Content-Type: application/json' \
  -d '{"actorColor":"black"}' "$base/backend/public/api/games/resign.php" >/dev/null
jq -e '.success == true and .state.gameStatus == "finished" and .state.terminationReason == "resignation"' \
  "$TEMP_DIR/game-resign.json" >/dev/null

curl -fsS -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" \
  "$base/backend/public/api/games/history.php" >"$TEMP_DIR/game-history.json"
jq -e '.success == true and (.state.games | length) == 1 and .state.games[0].timeControl.label == "3+2"' \
  "$TEMP_DIR/game-history.json" >/dev/null
game_id="$(jq -r '.state.games[0].id' "$TEMP_DIR/game-history.json")"

curl -fsS -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" \
  "$base/backend/public/api/games/open.php?id=$game_id" >"$TEMP_DIR/game-open.json"
jq -e '.success == true and .state.game.id == ('"$game_id"') and .state.gameState.terminationReason == "resignation" and (.state.replay.positions | length) == 2' \
  "$TEMP_DIR/game-open.json" >/dev/null

curl -fsS -c "$TEMP_DIR/auth-cookies.txt" -b "$TEMP_DIR/auth-cookies.txt" \
  "$base/backend/public/api/games/replay.php?id=$game_id" >"$TEMP_DIR/game-replay.json"
jq -e '.success == true and (.state | has("gameState") | not) and .state.replay.positions[1].coordinate == "e2e4"' \
  "$TEMP_DIR/game-replay.json" >/dev/null

curl -fsS -c "$TEMP_DIR/other-cookies.txt" -b "$TEMP_DIR/other-cookies.txt" \
  -o "$TEMP_DIR/other-register.json" -H 'Content-Type: application/json' \
  -d '{"username":"dastother","displayName":"DAST Other","password":"correct horse"}' \
  "$base/backend/public/api/auth/register.php" >/dev/null
other_open_status="$(curl -sS -o "$TEMP_DIR/other-open.json" -w '%{http_code}' \
  -c "$TEMP_DIR/other-cookies.txt" -b "$TEMP_DIR/other-cookies.txt" \
  "$base/backend/public/api/games/open.php?id=$game_id")"
if [[ "$other_open_status" != "404" ]]; then
  echo "DAST expected other owner saved-game open -> 404, got $other_open_status" >&2
  cat "$TEMP_DIR/other-open.json" >&2
  exit 1
fi
jq -e '.success == false' "$TEMP_DIR/other-open.json" >/dev/null

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
