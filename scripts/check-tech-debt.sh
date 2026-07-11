#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
failures=0

while IFS= read -r finding; do
  [[ -n "$finding" ]] || continue

  if [[ ! "$finding" =~ DEBT-[0-9][0-9][0-9] ]]; then
    echo "Untracked debt annotation: $finding" >&2
    failures=1
    continue
  fi

  debt_id="${BASH_REMATCH[0]}"
  if ! grep -q "^## ${debt_id} " "$ROOT/TECH_DEBT.md"; then
    echo "Debt annotation references missing ledger entry ${debt_id}: $finding" >&2
    failures=1
  fi
done < <(git -C "$ROOT" grep --untracked -nE '(TODO|FIXME|HACK)' -- backend frontend scripts tests ':!scripts/check-tech-debt.sh' || true)

if (( failures != 0 )); then
  exit 1
fi

echo "Technical-debt annotations are tracked."
