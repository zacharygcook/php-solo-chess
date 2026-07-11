#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT="${1:-$ROOT/.agent-readiness/dependency-updates.md}"
TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/php-solo-chess-updates.XXXXXX")"
trap 'rm -rf "$TEMP_DIR"' EXIT INT TERM

composer --working-dir="$ROOT" outdated --direct --format=json >"$TEMP_DIR/composer.json"
curl -fsS -H 'Accept: application/vnd.github+json' -H 'User-Agent: php-solo-chess-local-update-check' \
  https://api.github.com/repos/jquery/jquery/releases/latest >"$TEMP_DIR/jquery.json"

php "$ROOT/scripts/generate-dependency-update-report.php" \
  "$TEMP_DIR/composer.json" "$TEMP_DIR/jquery.json" "$OUTPUT"
