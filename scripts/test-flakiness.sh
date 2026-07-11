#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNS="${FLAKY_TEST_RUNS:-20}"
FIRST_SEED="${FLAKY_TEST_FIRST_SEED:-1}"

for value in "$RUNS" "$FIRST_SEED"; do
  case "$value" in
    ''|*[!0-9]*|0) echo "Flakiness run count and first seed must be positive integers." >&2; exit 12 ;;
  esac
done

last_seed=$((FIRST_SEED + RUNS - 1))
if (( last_seed > 2147483647 )); then
  echo "Requested seed range exceeds 2147483647." >&2
  exit 12
fi

echo "Probing test isolation with ${RUNS} seeds (${FIRST_SEED}-${last_seed})."
for ((offset = 0; offset < RUNS; offset++)); do
  seed=$((FIRST_SEED + offset))
  if ! TEST_SEED="$seed" "$ROOT/scripts/test.sh"; then
    echo "Flaky-test probe failed. Replay with: TEST_SEED=${seed} ./scripts/test.sh" >&2
    exit 1
  fi
done

echo "Flaky-test probe passed for ${RUNS} seeds."
