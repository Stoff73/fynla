#!/usr/bin/env bash
#
# PreToolUse guard — matcher: Write|Edit. Blocks edits to .env files.
# Rule (MEMORY: feedback_never_touch_env_or_db): never edit .env to work
# around a bug — surface the problem and ask CSJ instead.
# .env.example (and any *.example) stays editable — it's a committed template.

set -euo pipefail

input="$(cat)"

file="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null || true)"

if [ -z "$file" ]; then
  exit 0
fi

base="$(basename "$file")"

case "$base" in
  *.example) exit 0 ;;
  .env|.env.*)
    jq -n --arg reason "BLOCKED: never edit ${base} to work around a bug (MEMORY: feedback_never_touch_env_or_db). Surface the config problem and ask CSJ — credentials live only in each server's .env." '{
      hookSpecificOutput: {
        hookEventName: "PreToolUse",
        permissionDecision: "deny",
        permissionDecisionReason: $reason
      }
    }'
    exit 0
    ;;
esac

exit 0
