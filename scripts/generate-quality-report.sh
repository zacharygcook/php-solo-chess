#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT="${1:-$ROOT/.agent-readiness/code-quality.md}"
TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/php-solo-chess-quality.XXXXXX")"
trap 'rm -rf "$TEMP_DIR"' EXIT INT TERM

if [[ ! -x "$ROOT/vendor/bin/phpmd" ]]; then
  echo "Quality tooling is missing. Run: composer install" >&2
  exit 12
fi
if ! command -v jq >/dev/null 2>&1; then
  echo "jq is required to generate the quality report." >&2
  exit 12
fi

XDEBUG_MODE=coverage php "$ROOT/tests/coverage.php" --measure >"$TEMP_DIR/coverage.txt"
php "$ROOT/scripts/check-duplication.php" --measure >"$TEMP_DIR/duplication.txt"
php -d error_reporting=24575 "$ROOT/vendor/bin/phpmd" \
  "$ROOT/backend/src" text "$ROOT/phpmd.xml" >"$TEMP_DIR/complexity.txt"

coverage_summary="$(grep -m 1 '^Backend line coverage:' "$TEMP_DIR/coverage.txt")"
duplication_summary="$(head -n 1 "$TEMP_DIR/duplication.txt")"
complexity_findings="$(wc -l <"$TEMP_DIR/complexity.txt" | tr -d ' ')"
minimum_coverage="$(jq -r '.minimum_line_coverage_percentage' "$ROOT/config/quality-budgets.json")"
maximum_duplication="$(jq -r '.maximum_duplicated_line_percentage' "$ROOT/config/quality-budgets.json")"

mkdir -p "$(dirname "$OUTPUT")"
{
  echo "# Code Quality Snapshot"
  echo
  echo "Generated from the current worktree by \`scripts/generate-quality-report.sh\`."
  echo
  echo "| Metric | Current measurement | Enforced budget |"
  echo "| --- | --- | --- |"
  echo "| Backend line coverage | $coverage_summary | At least ${minimum_coverage}% |"
  echo "| Duplicate code | $duplication_summary | At most ${maximum_duplication}% |"
  echo "| PHPMD complexity findings | ${complexity_findings} | No findings above configured thresholds |"
  echo
  echo "The canonical local check fails if any quality budget regresses."
} >"$OUTPUT"

echo "Code quality snapshot written to $OUTPUT"
