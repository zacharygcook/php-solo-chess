#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
failures=0

echo "Linting PHP syntax"
while IFS= read -r -d '' file; do
  if ! php -l "$file" >/dev/null; then
    failures=1
  fi
done < <(find "$ROOT/backend" "$ROOT/scripts" "$ROOT/tests" -name '*.php' -type f -print0)

echo "Linting frontend JavaScript syntax"
while IFS= read -r -d '' file; do
  if ! node --check "$file"; then
    failures=1
  fi
done < <(find "$ROOT/frontend" -name '*.js' -type f -print0)

debug_findings="$(git -C "$ROOT" grep --untracked -nE '(console\.log|var_dump\(|print_r\()' -- backend frontend tests ':!scripts/lint.sh' || true)"
if [[ -n "$debug_findings" ]]; then
  echo "Committed debug calls are not allowed:" >&2
  echo "$debug_findings" >&2
  failures=1
fi

if (( failures != 0 )); then
  exit 1
fi

echo "Source lint passed."
