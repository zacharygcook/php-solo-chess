#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ ! -x "$ROOT/vendor/bin/phpmd" ]]; then
  echo "Complexity analyzer is missing. Run: composer install" >&2
  exit 12
fi

php -d error_reporting=24575 "$ROOT/vendor/bin/phpmd" \
  "$ROOT/backend/src" text "$ROOT/phpmd.xml"

REPORT_LEVEL=$(sed -n 's/.*name="reportLevel" value="\([0-9][0-9]*\)".*/\1/p' "$ROOT/phpmd.xml")
if [[ -z "$REPORT_LEVEL" ]]; then
  echo "Unable to read reportLevel from phpmd.xml" >&2
  exit 14
fi

echo "Cyclomatic-complexity budget passed (maximum allowed method complexity: $((REPORT_LEVEL - 1)))."
