#!/usr/bin/env bash
#
# PreToolUse guard — matcher: mcp__ssh-fynla__ssh_exec (PRODUCTION fynla.org).
# The Bash dangerous-command-guard never sees MCP tool calls, so the same
# NEVER rules are enforced here for commands executed on the prod server.
# Fail-closed: a matched ban emits a "deny" decision; parse errors fail open.
#
# Banned on prod:
#   1. migrate:fresh / migrate:refresh / db:wipe — drops all tables (real customer data)
#   2. route:cache / artisan optimize           — SPA catch-all shadows '/', /m regresses
#      (optimize:clear is fine)

set -euo pipefail

input="$(cat)"

cmd="$(printf '%s' "$input" | jq -r '.tool_input.command // empty' 2>/dev/null || true)"

if [ -z "$cmd" ]; then
  exit 0
fi

deny() {
  jq -n --arg reason "$1" '{
    hookSpecificOutput: {
      hookEventName: "PreToolUse",
      permissionDecision: "deny",
      permissionDecisionReason: $reason
    }
  }'
  exit 0
}

# 1. Table-dropping commands — this is PRODUCTION -----------------------------
if printf '%s' "$cmd" | grep -Eq 'migrate:(fresh|refresh)|db:wipe'; then
  deny "BLOCKED ON PROD: \`migrate:fresh\`/\`migrate:refresh\`/\`db:wipe\` drops all tables — this is fynla.org with real customer data (CLAUDE.md). Migrations on prod use: php artisan migrate --force"
fi

# 2. route:cache / artisan optimize — breaks '/' and the /m landing ----------
if printf '%s' "$cmd" | grep -Eq 'route:cache' \
   || printf '%s' "$cmd" | grep -Eq 'artisan[[:space:]]+optimize([^:[:alnum:]]|$)'; then
  deny "BLOCKED ON PROD: \`route:cache\`/\`artisan optimize\` makes the SPA catch-all shadow '/' — guests and /m get the bare SPA shell (MEMORY: reference_route_cache_shadows_homepage). Use route:clear; end deploy chains with config:cache only."
fi

exit 0
