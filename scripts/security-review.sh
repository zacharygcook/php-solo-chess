#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT="${1:-$ROOT/.agent-readiness/security-review.md}"
TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/php-solo-chess-security.XXXXXX")"
trap 'rm -rf "$TEMP_DIR"' EXIT INT TERM

set +e
composer --working-dir="$ROOT" audit --format=json --no-interaction >"$TEMP_DIR/composer-audit.json"
audit_status=$?
"$ROOT/scripts/check-secrets.sh" >"$TEMP_DIR/secret-scan.txt" 2>&1
secret_status=$?
set -e

php "$ROOT/scripts/generate-security-report.php" \
  "$TEMP_DIR/composer-audit.json" "$secret_status" "$OUTPUT"
report_status=$?

if (( audit_status != 0 || secret_status != 0 || report_status != 0 )); then
  cat "$TEMP_DIR/secret-scan.txt" >&2
  exit 1
fi
