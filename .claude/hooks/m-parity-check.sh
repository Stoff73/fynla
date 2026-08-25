#!/usr/bin/env bash
#
# Stop hook — /m parity reminder (CLAUDE.md Rule 19).
# If the session's diff touches desktop SPA views/components but nothing under
# resources/mobile/, surface a reminder that /m parity is in-scope by default.
# Informational (systemMessage), once per session — many changes legitimately
# have no /m counterpart; Rule 19 says "flag, don't skip", so we flag.

ROOT="${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null)}"
cd "$ROOT" || exit 0

INPUT=$(cat 2>/dev/null || echo '{}')
# shellcheck source=lib-json.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib-json.sh"

SESSION_ID=$(json_field "$INPUT" session_id nosession)

MARKER="${TMPDIR:-/tmp}/fynla-m-parity-${SESSION_ID}"
[ -f "$MARKER" ] && exit 0

# Tracked edits, staged edits, and NEW files. Untracked files were previously
# skipped entirely, which is exactly where new violations land. Deduped because a
# staged edit otherwise appears twice and every finding was reported twice. W-0483.
CHANGED_FILES=$( { git diff --name-only HEAD 2>/dev/null
  git diff --cached --name-only 2>/dev/null
  git ls-files --others --exclude-standard 2>/dev/null
} | sort -u)
[ -z "$CHANGED_FILES" ] && exit 0

WEB_CHANGED=$(printf '%s\n' "$CHANGED_FILES" | grep -E '^resources/js/(views|components)/' | head -5 || true)
MOBILE_CHANGED=$(printf '%s\n' "$CHANGED_FILES" | grep -c '^resources/mobile/' || true)

if [ -n "$WEB_CHANGED" ] && [ "$MOBILE_CHANGED" -eq 0 ]; then
  touch "$MARKER" 2>/dev/null || true
  json_emit systemMessage "Rule 19 (/m parity): this session changed desktop SPA files but nothing under resources/mobile/. Confirm the change has no /m counterpart, or build the mobile equivalent - 'done' = web AND /m unless CSJ excluded it. Changed: $(printf '%s' "$WEB_CHANGED" | tr '
' ' ')"
fi

exit 0
