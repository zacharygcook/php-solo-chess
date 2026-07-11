#!/usr/bin/env bash

set -euo pipefail

ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
MAX_FILE_BYTES="${MAX_FILE_BYTES:-1048576}"

case "$MAX_FILE_BYTES" in
  ''|*[!0-9]*) echo "MAX_FILE_BYTES must be a positive integer." >&2; exit 12 ;;
  0) echo "MAX_FILE_BYTES must be greater than zero." >&2; exit 12 ;;
esac

if ! git -C "$ROOT" rev-parse --git-dir >/dev/null 2>&1; then
  echo "Not a Git repository: $ROOT" >&2
  exit 12
fi

oversized=0
while IFS= read -r -d '' file; do
  [[ -f "$ROOT/$file" ]] || continue
  bytes="$(wc -c < "$ROOT/$file" | tr -d ' ')"
  if (( bytes > MAX_FILE_BYTES )); then
    printf 'Oversized file: %s (%s bytes; limit %s)\n' "$file" "$bytes" "$MAX_FILE_BYTES" >&2
    oversized=1
  fi
done < <(git -C "$ROOT" ls-files -z --cached --others --exclude-standard)

if (( oversized != 0 )); then
  exit 1
fi

echo "Large-file check passed (limit: ${MAX_FILE_BYTES} bytes)."
