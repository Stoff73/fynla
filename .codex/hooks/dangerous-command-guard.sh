#!/usr/bin/env bash
#
# PreToolUse guard — hard-blocks Bash commands that violate Fynla's
# documented "NEVER" rules (CLAUDE.md + MEMORY.md). Fail-closed: if the
# command matches a banned pattern we emit a PreToolUse "deny" decision
# so the tool call never runs.
#
# Banned patterns:
#   1. migrate:fresh / migrate:refresh   — DROPS ALL TABLES, destroys local data
#   2. artisan --env=testing             — no .env.testing; resolves to dev DB and wipes it
#   3. pkill -f vite / killall vite      — kills sibling fynlaInternational on :5174
#   4. raw vite build / npm run build    — deploy must go through deploy/*/build.sh
#   5. artisan optimize / route:cache    — compiled route matcher lets the SPA catch-all
#                                          shadow '/' (and the /m iframe loads '/')
#   6. artisan db:wipe                   — drops all tables, same blast radius as migrate:fresh
#
# Substring matching means ssh-wrapped variants (`ssh server "php artisan ..."`)
# are caught too. Anything else is allowed (silent exit 0 = normal permission flow).

set -euo pipefail

input="$(cat)"

# Extract the command; tolerate missing jq / malformed input by failing open
# ONLY for parse errors (never for a matched ban).
cmd="$(printf '%s' "$input" | jq -r '.tool_input.command // empty' 2>/dev/null || true)"

if [ -z "$cmd" ]; then
  exit 0
fi

deny() {
  # $1 = human-readable reason (shown to the model + user)
  jq -n --arg reason "$1" '{
    hookSpecificOutput: {
      hookEventName: "PreToolUse",
      permissionDecision: "deny",
      permissionDecisionReason: $reason
    }
  }'
  exit 0
}

# 1. Destructive migrations -------------------------------------------------
if printf '%s' "$cmd" | grep -Eq 'migrate:(fresh|refresh)'; then
  deny "BLOCKED: \`migrate:fresh\`/\`migrate:refresh\` DROPS ALL TABLES and destroys local data (CLAUDE.md). To reseed without data loss run: php artisan db:seed"
fi

# 1b. db:wipe — same blast radius as migrate:fresh ---------------------------
if printf '%s' "$cmd" | grep -Eq 'db:wipe'; then
  deny "BLOCKED: \`db:wipe\` drops all tables (CLAUDE.md NEVER rules — same blast radius as migrate:fresh). To reseed without data loss run: php artisan db:seed"
fi

# 1c. route:cache / artisan optimize — SPA catch-all shadows '/' -------------
#     optimize:clear is fine (the ':' after 'optimize' excludes it).
if printf '%s' "$cmd" | grep -Eq 'route:cache' \
   || printf '%s' "$cmd" | grep -Eq 'artisan[[:space:]]+optimize([^:[:alnum:]]|$)'; then
  deny "BLOCKED: \`route:cache\`/\`artisan optimize\` breaks this app — the compiled route matcher lets the SPA catch-all shadow '/' and the /m iframe loads '/' (MEMORY: reference_route_cache_shadows_homepage). Use route:clear; re-cache config only with config:cache."
fi

# 2. artisan --env=testing (no .env.testing -> resolves to the dev DB) ------
if printf '%s' "$cmd" | grep -Eq '(^|[^[:alnum:]_])artisan([[:space:]]|$)' \
   && printf '%s' "$cmd" | grep -Eq -- '--env[[:space:]=]+testing(\b|$)'; then
  deny "BLOCKED: \`php artisan --env=testing\` resolves to the 'laravel' dev DB (no .env.testing exists) and wipes it (MEMORY: feedback_never_artisan_env_testing). Use a DB_DATABASE= override against an isolated test DB instead."
fi

# 3. pkill/killall targeting vite (kills the sibling fynlaInternational) ----
if printf '%s' "$cmd" | grep -Eq '\b(pkill|killall)\b[^|;&]*vite'; then
  deny "BLOCKED: killing vite by name also kills the sibling fynlaInternational dev server on :5174 (MEMORY: feedback_vite_canonical_port_5173). Kill by PID, or free the port with: lsof -ti tcp:5173 | xargs kill"
fi

# 4. Raw production-build commands (must go through deploy/*/build.sh) ------
#    Matches: `npm run build`, `yarn build`, `yarn run build`, `pnpm build`,
#    `pnpm run build`, `npx vite build`, bare `vite build`.
#    Does NOT match `vitest`, `vite` (dev), or the deploy scripts themselves.
if printf '%s' "$cmd" | grep -Eq '(^|[^[:alnum:]_/.-])(npm|yarn|pnpm)[[:space:]]+(run[[:space:]]+)?build(\b|$)' \
   || printf '%s' "$cmd" | grep -Eq '(^|[^[:alnum:]_/.-])(npx[[:space:]]+)?vite[[:space:]]+build(\b|$)'; then
  deny "BLOCKED: never run a raw production build — Vite env vars (VITE_BASE_PATH / VITE_ROUTER_BASE) must match the target or routing breaks silently (CLAUDE.md Build scripts). Use ./deploy/fynla-org/build.sh (main) or ./deploy/csjones-fynla/build.sh (dev). For iOS: ./deploy/mobile/build-ios.sh"
fi

exit 0
