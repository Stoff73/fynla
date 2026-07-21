#!/usr/bin/env bash
#
# Stop hook — /m parity reminder (CLAUDE.md Rule 19).
# If the session's diff touches desktop SPA views/components but nothing under
# resources/mobile/, surface a reminder that /m parity is in-scope by default.
# Informational (systemMessage), once per session — many changes legitimately
# have no /m counterpart; Rule 19 says "flag, don't skip", so we flag.

cd /Users/CSJ/Desktop/fynla || exit 0

INPUT=$(cat 2>/dev/null || echo '{}')
SESSION_ID=$(printf '%s' "$INPUT" | jq -r '.session_id // "nosession"' 2>/dev/null || echo "nosession")

MARKER="${TMPDIR:-/tmp}/fynla-m-parity-${SESSION_ID}"
[ -f "$MARKER" ] && exit 0

CHANGED_FILES=$(git diff --name-only HEAD 2>/dev/null; git diff --cached --name-only 2>/dev/null)
[ -z "$CHANGED_FILES" ] && exit 0

WEB_CHANGED=$(printf '%s\n' "$CHANGED_FILES" | grep -E '^resources/js/(views|components)/' | head -5 || true)
MOBILE_CHANGED=$(printf '%s\n' "$CHANGED_FILES" | grep -c '^resources/mobile/' || true)

if [ -n "$WEB_CHANGED" ] && [ "$MOBILE_CHANGED" -eq 0 ]; then
  touch "$MARKER" 2>/dev/null || true
  jq -n --arg msg "Rule 19 (/m parity): this session changed desktop SPA files but nothing under resources/mobile/. Confirm the change has no /m counterpart, or build the mobile equivalent — 'done' = web AND /m unless CSJ excluded it. Changed: $(printf '%s' "$WEB_CHANGED" | tr '\n' ' ')" '{systemMessage: $msg}'
fi

exit 0
