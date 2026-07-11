#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

BASE="${QUALITY_BASE:-HEAD^}"
HEAD_REF="${QUALITY_HEAD:-HEAD}"
FILES=()
DIFF_FILE="$(mktemp)"
trap 'rm -f "$DIFF_FILE"' EXIT

if ! git diff --name-only --diff-filter=ACM -z "$BASE" "$HEAD_REF" -- '*.php' > "$DIFF_FILE"; then
  echo "Unable to resolve changed PHP files for $BASE..$HEAD_REF" >&2
  exit 1
fi

while IFS= read -r -d '' file; do
  FILES+=("$file")
done < "$DIFF_FILE"

if [ "${#FILES[@]}" -eq 0 ]; then
  echo "PHP style: no changed files"
  exit 0
fi

php vendor/bin/pint --test "${FILES[@]}"
