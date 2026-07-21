#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

BASE="${QUALITY_BASE:-HEAD^}"
HEAD_REF="${QUALITY_HEAD:-HEAD}"
FILES="$(git diff --name-only "$BASE" "$HEAD_REF" -- \
  'resources/**/*.vue' 'resources/**/*.js' 'resources/**/*.css')"
violations=0

for file in $FILES; do
  [ -f "$file" ] || continue
  case "$file" in
    tests/*|public/*|database/*|resources/css/app.css) continue ;;
    resources/js/constants/designSystem.js) continue ;;
  esac

  ADDED="$(git diff --unified=0 "$BASE" "$HEAD_REF" -- "$file" \
    | sed -n '/^+++ /d; s/^+//p')"

  if HITS="$(printf '%s\n' "$ADDED" \
    | rg --pcre2 -n '(?<!light-)(amber|orange|gray|primary|secondary)-[0-9]{2,3}')"; then
    printf '%s: new banned colour token detected\n%s\n' "$file" "$HITS"
    violations=1
  fi

  if [[ "$file" == *.vue ]]; then
    if HITS="$(printf '%s\n' "$ADDED" | rg -n '#[0-9A-Fa-f]{3,8}\b')"; then
      printf '%s: new hardcoded hexadecimal colour detected\n%s\n' "$file" "$HITS"
      violations=1
    fi
  fi

  if printf '%s' "$ADDED" | python3 -c '
import sys
text = sys.stdin.read()
raise SystemExit(1 if any(
    0x1F000 <= ord(c) <= 0x1FAFF
    or 0x2600 <= ord(c) <= 0x27BF
    or 0x2190 <= ord(c) <= 0x21FF
    or 0x2B00 <= ord(c) <= 0x2BFF
    for c in text
) else 0)
'; then
    true
  else
    echo "$file: new emoji or Unicode icon detected"
    violations=1
  fi
done

exit "$violations"
