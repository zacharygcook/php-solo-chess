#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${1:-8080}"

if ! command -v php >/dev/null 2>&1; then
  echo "PHP is required. Install PHP 8.1 or newer and retry." >&2
  exit 12
fi

case "$PORT" in
  ''|*[!0-9]*) echo "Port must be a number: $PORT" >&2; exit 14 ;;
esac

mkdir -p "$ROOT/backend/storage/sessions"

echo ""
echo "============================================================"
echo "  PHP Solo Chess"
echo "  OPEN THIS URL: http://127.0.0.1:$PORT/frontend/"
echo "  Press Ctrl-C to stop the server."
echo "============================================================"
echo ""
exec php -S "127.0.0.1:$PORT" -t "$ROOT" "$ROOT/scripts/router.php"
