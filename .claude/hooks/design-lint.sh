#!/usr/bin/env bash
#
# Stop hook — design-system lint on files changed this session.
# Mechanically enforces CLAUDE.md Rules 8/11/15 on NEW work:
#   - banned colour tokens: amber-*, orange-*, gray-N, primary-*, secondary-*
#   - hardcoded hex inside .vue <style> blocks
#   - emoji in source files (Rule 15 bans them everywhere)
#
# First detection in a session BLOCKS the stop (model fixes before finishing);
# repeat detections downgrade to a systemMessage warning so a deliberately
# grandfathered file (Rule 15 is forward-only) can't nag forever.
# ponytail: grep heuristics, not a parser — false positives are possible; the
# block downgrades after one round precisely so a justified exception can pass.

cd /Users/CSJ/Desktop/fynla || exit 0

INPUT=$(cat 2>/dev/null || echo '{}')
SESSION_ID=$(printf '%s' "$INPUT" | jq -r '.session_id // "nosession"' 2>/dev/null || echo "nosession")
STOP_ACTIVE=$(printf '%s' "$INPUT" | jq -r '.stop_hook_active // false' 2>/dev/null || echo "false")
[ "$STOP_ACTIVE" = "true" ] && exit 0

MARKER="${TMPDIR:-/tmp}/fynla-design-lint-${SESSION_ID}"

CHANGED_FILES=$(git diff --name-only HEAD 2>/dev/null; git diff --cached --name-only 2>/dev/null)
[ -z "$CHANGED_FILES" ] && exit 0

VIOLATIONS=""

for file in $CHANGED_FILES; do
  case "$file" in
    *.vue|*.js|*.css) ;;
    *) continue ;;
  esac
  # Grandfathered / by-design exclusions (Rule 15 forward-only; palette sources define hex)
  case "$file" in
    tests/*|public/*|docs/*|database/*) continue ;;
    tailwind.config.js|resources/css/app.css) continue ;;
    resources/js/constants/designSystem.js|resources/js/constants/goalIcons.js) continue ;;
    resources/js/constants/eventIcons.js|resources/js/constants/eventIconSvgs.js) continue ;;
  esac
  [ ! -f "$file" ] && continue

  # Rule 8/11 — banned colour tokens
  HITS=$(grep -nE '(amber|orange)-[0-9]{2,3}|gray-[0-9]{2,3}|(^|[^[:alnum:]_-])(primary|secondary)-[0-9]{2,3}' "$file" 2>/dev/null | head -5 || true)
  if [ -n "$HITS" ]; then
    VIOLATIONS="$VIOLATIONS\n$file — banned colour token (Rule 8/11: violet=warning, raspberry=error, spring=success; no gray-*/primary-*/secondary-*):\n$HITS"
  fi

  # Rule 11 — hardcoded hex inside .vue <style> blocks
  if [ "${file##*.}" = "vue" ]; then
    HEX=$(sed -n '/<style/,/<\/style>/p' "$file" 2>/dev/null | grep -nE '#[0-9a-fA-F]{3,8}\b' | grep -v '^\s*/\*\|^\s*//' | head -5 || true)
    if [ -n "$HEX" ]; then
      VIOLATIONS="$VIOLATIONS\n$file — hardcoded hex in <style> (Rule 11: use @apply with palette tokens):\n$HEX"
    fi
  fi

  # Rule 15 — emoji in source (arrows/checkmarks skipped: too noisy in comments)
  EMOJI=$(python3 -c "
import sys
try:
    with open('$file', encoding='utf-8', errors='ignore') as f:
        for n, line in enumerate(f, 1):
            if any(0x1F000 <= ord(c) <= 0x1FAFF or 0x2600 <= ord(c) <= 0x27BF or 0x2B00 <= ord(c) <= 0x2BFF for c in line):
                print(f'{n}: {line.strip()[:80]}')
except Exception:
    pass
" 2>/dev/null | head -3 || true)
  if [ -n "$EMOJI" ]; then
    VIOLATIONS="$VIOLATIONS\n$file — emoji in source (Rule 15 bans emoji in any string/label/comment):\n$EMOJI"
  fi
done

[ -z "$VIOLATIONS" ] && exit 0

REASON="Design-system lint failed on changed files (CLAUDE.md Rules 8/11/15). Fix these before finishing — or, if a hit is grandfathered/false-positive, say so and stop again:$VIOLATIONS"

if [ ! -f "$MARKER" ]; then
  touch "$MARKER" 2>/dev/null || true
  jq -n --arg reason "$REASON" '{decision: "block", reason: $reason}'
else
  jq -n --arg msg "WARNING (repeat): design-lint still detects violations in changed files. $REASON" '{systemMessage: $msg}'
fi

exit 0
