#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-}"
REF="${2:-HEAD}"
OUTPUT_DIR="${RELEASE_OUTPUT_DIR:-$ROOT/dist}"

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]]; then
  echo "Usage: ./scripts/package-release.sh <version> [git-ref]" >&2
  exit 12
fi
for command in git jq shasum; do
  if ! command -v "$command" >/dev/null 2>&1; then
    echo "Missing required command: $command" >&2
    exit 12
  fi
done
if [[ -n "$(git -C "$ROOT" status --short --untracked-files=all)" ]]; then
  echo "Release packaging requires a clean worktree." >&2
  exit 14
fi

COMMIT="$(git -C "$ROOT" rev-parse --verify "${REF}^{commit}")"
PREFIX="php-solo-chess-${VERSION}"
ARCHIVE="$OUTPUT_DIR/${PREFIX}.tar.gz"
NOTES="$OUTPUT_DIR/${PREFIX}-notes.md"
CHECKSUMS="$OUTPUT_DIR/${PREFIX}-SHA256SUMS"
MANIFEST="$OUTPUT_DIR/${PREFIX}-manifest.json"

"$ROOT/scripts/check.sh"
mkdir -p "$OUTPUT_DIR"
git -C "$ROOT" archive --format=tar.gz --prefix="${PREFIX}/" --output="$ARCHIVE" "$COMMIT"
php "$ROOT/scripts/generate-release-notes.php" --to="$COMMIT" --output="$NOTES"
(
  cd "$OUTPUT_DIR"
  shasum -a 256 "$(basename "$ARCHIVE")" "$(basename "$NOTES")" >"$(basename "$CHECKSUMS")"
)

jq -n \
  --arg version "$VERSION" \
  --arg commit "$COMMIT" \
  --arg archive "$(basename "$ARCHIVE")" \
  --arg notes "$(basename "$NOTES")" \
  --arg checksums "$(basename "$CHECKSUMS")" \
  '{schema_version: 1, version: $version, commit: $commit, validated: true, artifacts: {archive: $archive, notes: $notes, checksums: $checksums}}' \
  >"$MANIFEST"

echo "Validated local release package written to $OUTPUT_DIR"
echo "No tag, push, upload, or hosted release was performed."
