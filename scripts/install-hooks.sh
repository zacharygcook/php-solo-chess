#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

git -C "$ROOT" config --local core.hooksPath .githooks

configured="$(git -C "$ROOT" config --local --get core.hooksPath)"
if [[ "$configured" != ".githooks" ]]; then
  echo "Failed to configure repository hooks." >&2
  exit 1
fi

echo "Local Git hooks installed from .githooks/."
