#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
patterns='-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|gh[pousr]_[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}|sk-[A-Za-z0-9]{20,}'

set +e
findings="$(git -C "$ROOT" grep --untracked -nEI -e "$patterns" -- . ':!scripts/check-secrets.sh' ':!SECURITY.md' ':!docs/RUNBOOKS.md')"
scan_status=$?
set -e

if (( scan_status > 1 )); then
  echo "Local secret scanner failed to inspect the repository." >&2
  exit "$scan_status"
fi

if [[ -n "$findings" ]]; then
  echo "Potential committed secret detected:" >&2
  echo "$findings" >&2
  exit 1
fi

while IFS= read -r env_file; do
  if [[ "$env_file" != ".env.example" ]]; then
    echo "Environment file must not be tracked: $env_file" >&2
    exit 1
  fi
done < <(git -C "$ROOT" ls-files '.env*')

echo "Local secret scan passed."
