#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/php-solo-chess-weight.XXXXXX")"
trap 'rm -rf "$TEMP_DIR"' EXIT INT TERM

runtime_url="$(jq -r '.runtime_cdn.jquery.url' "$ROOT/config/dependency-policy.json")"
curl -fsS "$runtime_url" >"$TEMP_DIR/jquery.min.js"

php "$ROOT/scripts/check-dependency-weight.php" "$TEMP_DIR/jquery.min.js"
