#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

required_files=(
  AGENTS.md
  README.md
  RALPH_DOGFOOD_SCORECARD.md
  docs/ARCHITECTURE.md
  docs/RUNBOOKS.md
  .ralph/sprints/0-environment-and-baseline/SCRATCHPAD.md
)

required_commands=(
  scripts/check.sh
  scripts/test.sh
  scripts/install-hooks.sh
)

for relative_path in "${required_files[@]}"; do
  if [[ ! -s "$ROOT/$relative_path" ]]; then
    echo "Agent documentation references a missing or empty file: $relative_path" >&2
    exit 1
  fi
done

for relative_path in "${required_commands[@]}"; do
  if [[ ! -x "$ROOT/$relative_path" ]]; then
    echo "Agent documentation references a non-executable command: $relative_path" >&2
    exit 1
  fi
  bash -n "$ROOT/$relative_path"
done

required_agent_references=(
  'README.md'
  'RALPH_DOGFOOD_SCORECARD.md'
  'docs/ARCHITECTURE.md'
  'docs/RUNBOOKS.md'
  './scripts/check.sh'
  './scripts/install-hooks.sh'
  'SCRATCHPAD.md'
)

for reference in "${required_agent_references[@]}"; do
  if ! grep -Fq "$reference" "$ROOT/AGENTS.md"; then
    echo "AGENTS.md is missing required reference: $reference" >&2
    exit 1
  fi
done

echo "Agent documentation contract passed."
