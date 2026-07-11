#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODE="${1:---check}"

if [[ "$MODE" != "--check" && "$MODE" != "--write" ]]; then
  echo "Usage: ./scripts/format.sh --check|--write" >&2
  exit 12
fi

if [[ ! -x "$ROOT/vendor/bin/php-cs-fixer" ]]; then
  echo "Formatter dependencies are missing. Run: composer install" >&2
  exit 12
fi

if [[ "$MODE" == "--write" ]]; then
  "$ROOT/vendor/bin/php-cs-fixer" fix --config="$ROOT/.php-cs-fixer.dist.php"
else
  "$ROOT/vendor/bin/php-cs-fixer" fix --dry-run --diff --config="$ROOT/.php-cs-fixer.dist.php"
fi

php "$ROOT/scripts/format-text.php" "$MODE"
