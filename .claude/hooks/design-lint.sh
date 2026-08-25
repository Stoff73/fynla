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

ROOT="${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel 2>/dev/null)}"
cd "$ROOT" || exit 0

INPUT=$(cat 2>/dev/null || echo '{}')
# shellcheck source=lib-json.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib-json.sh"

SESSION_ID=$(json_field "$INPUT" session_id nosession)
STOP_ACTIVE=$(json_field "$INPUT" stop_hook_active false)
[ "$STOP_ACTIVE" = "true" ] && exit 0

MARKER="${TMPDIR:-/tmp}/fynla-design-lint-${SESSION_ID}"

# Tracked edits, staged edits, and NEW files. Untracked files were previously
# skipped entirely, which is exactly where new violations land. Deduped because a
# staged edit otherwise appears twice and every finding was reported twice. W-0483.
CHANGED_FILES=$( { git diff --name-only HEAD 2>/dev/null
  git diff --cached --name-only 2>/dev/null
  git ls-files --others --exclude-standard 2>/dev/null
} | sort -u)
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
  # PHP, not python3: python3 is not a dependency of this repo and resolves to the
  # Microsoft Store stub on Windows, which made this check a silent pass everywhere
  # it ran without a real python3 on PATH. See W-0483.
  EMOJI=$(php -r '
$path = $argv[1];
if (! is_file($path)) { exit(0); }
$ranges = [[0x1F000, 0x1FAFF], [0x2600, 0x27BF], [0x2B00, 0x2BFF]];
$n = 0;
foreach (file($path) as $line) {
    $n++;
    foreach (preg_split("//u", rtrim($line), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
        $cp = mb_ord($c, "UTF-8");
        foreach ($ranges as [$lo, $hi]) {
            if ($cp >= $lo && $cp <= $hi) {
                printf("%d: %s\n", $n, substr(trim($line), 0, 80));
                continue 3;
            }
        }
    }
}
' "$file" 2>/dev/null | head -3 || true)
  if [ -n "$EMOJI" ]; then
    VIOLATIONS="$VIOLATIONS\n$file — emoji in source (Rule 15 bans emoji in any string/label/comment):\n$EMOJI"
  fi
done

[ -z "$VIOLATIONS" ] && exit 0

REASON="Design-system lint failed on changed files (CLAUDE.md Rules 8/11/15). Fix these before finishing — or, if a hit is grandfathered/false-positive, say so and stop again:$VIOLATIONS"

if [ ! -f "$MARKER" ]; then
  touch "$MARKER" 2>/dev/null || true
  json_emit_block "$REASON"
else
  json_emit systemMessage "WARNING (repeat): design-lint still detects violations in changed files. $REASON"
fi

exit 0
