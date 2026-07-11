#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${1:-18080}"
TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/php-solo-chess-check.XXXXXX")"
SERVER_PID=""

cleanup() {
  if [[ -n "$SERVER_PID" ]]; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -rf "$TEMP_DIR"
}
trap cleanup EXIT INT TERM

for command in php node curl jq; do
  if ! command -v "$command" >/dev/null 2>&1; then
    echo "Missing required command: $command" >&2
    exit 12
  fi
done

case "$PORT" in
  ''|*[!0-9]*) echo "Port must be a number: $PORT" >&2; exit 14 ;;
esac

echo "[1/11] Validating agent documentation"
"$ROOT/scripts/check-agent-docs.sh"

echo "[2/11] Rejecting oversized repository files"
"$ROOT/scripts/check-large-files.sh"

echo "[3/11] Checking technical-debt tracking"
"$ROOT/scripts/check-tech-debt.sh"

echo "[4/11] Checking dependency policy"
php "$ROOT/scripts/check-dependencies.php"

echo "[5/11] Linting PHP and JavaScript source"
"$ROOT/scripts/lint.sh"

echo "[6/11] Checking deterministic formatting"
"$ROOT/scripts/format.sh" --check

echo "[7/11] Type-checking PHP"
composer --working-dir="$ROOT" typecheck

echo "[8/11] Running rules-engine unit tests"
"$ROOT/scripts/test.sh"

echo "[9/11] Starting isolated server"
php -S "127.0.0.1:$PORT" -t "$ROOT" >"$TEMP_DIR/server.log" 2>&1 &
SERVER_PID=$!

SESSION_JSON="$TEMP_DIR/session.json"
SERVER_READY=false
for _attempt in 1 2 3 4 5; do
  if curl -fsS -c "$TEMP_DIR/cookies.txt" "http://127.0.0.1:$PORT/backend/public/api/session.php" >"$SESSION_JSON" 2>/dev/null; then
    SERVER_READY=true
    break
  fi
  sleep 1
done

if [[ "$SERVER_READY" != "true" ]]; then
  echo "Local PHP server did not become ready:" >&2
  cat "$TEMP_DIR/server.log" >&2
  exit 14
fi

jq -e '.success == true and .state.activeColor == "white" and (.state.board | length) == 8' "$SESSION_JSON" >/dev/null
echo "[10/11] Loading both frontend URL forms and browser assets"
curl -fsS "http://127.0.0.1:$PORT/frontend" >/dev/null
curl -fsS "http://127.0.0.1:$PORT/frontend/" >/dev/null
curl -fsS "http://127.0.0.1:$PORT/frontend/assets/css/styles.css" >/dev/null
curl -fsS "http://127.0.0.1:$PORT/frontend/assets/js/app.js" >/dev/null

echo "[11/11] Playing e2 to e4 through the API"
curl -fsS -b "$TEMP_DIR/cookies.txt" -H 'Content-Type: application/json' \
  -d '{"from":"e2","to":"e4"}' "http://127.0.0.1:$PORT/backend/public/api/move.php" \
  | jq -e '.success == true and .state.activeColor == "black" and .state.board[4][4] == "wp"' >/dev/null

echo "Baseline check passed."
