#!/usr/bin/env bash
#
# PROPOSED — replaces five guards with one. Review, then:
#   mv workforce/ops/proposed-guard.sh .claude/hooks/guard.sh
#   chmod +x .claude/hooks/guard.sh
#   rm .claude/hooks/{dangerous-command-guard,prod-guard,env-guard,workforce-guard,oversight-guard}.sh
# and collapse the five PreToolUse entries in settings.json to one:
#   { "matcher": "Write|Edit|Bash|mcp__ssh-fynla__ssh_exec",
#     "hooks": [ { "type": "command",
#                  "command": "bash /Users/CSJ/Desktop/fynla/.claude/hooks/guard.sh",
#                  "timeout": 10,
#                  "statusMessage": "Checking Fynla NEVER rules..." } ] }
#
# ONE PreToolUse guard. Every call — Bash, Write, Edit, ssh_exec — enters here,
# and every rule is written once. Rule 20 applied to the enforcement layer.
#
# Contract (unchanged from the five it replaces):
#   - fail-CLOSED on a matched ban: emit a "deny" decision, tool never runs
#   - fail-OPEN on parse errors: silent exit 0 = normal permission flow
#
# Ordering is deliberate: cheapest and most destructive checks first.

set -euo pipefail

input="$(cat)"

tool="$(printf '%s' "$input" | jq -r '.tool_name       // empty' 2>/dev/null || true)"
cmd="$( printf '%s' "$input" | jq -r '.tool_input.command   // empty' 2>/dev/null || true)"
path="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null || true)"

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

# `--` matters: patterns here start with '-' (e.g. '--env=testing'), and without
# it grep reads the pattern as an option, finds none, and blocks on stdin.
has() { printf '%s' "$cmd" | grep -Eq  -- "$1"; }
hasi(){ printf '%s' "$cmd" | grep -Eqi -- "$1"; }

# Is this the production server? Either the prod MCP tool, or a raw ssh to it.
on_prod() {
  [ "$tool" = "mcp__ssh-fynla__ssh_exec" ] && return 0
  hasi 'ssh[^|;&]*(ssh\.fynla\.org|u2783-hrf1k8bpfg02)'
}

# Paths gated to a founder (workforce/core/charter.md §2). One regex, used for
# BOTH the file_path check and the shell-write check — the two used to live in
# separate scripts with different anchoring, which is how `rm CLAUDE.md` slipped
# through while `rm ./CLAUDE.md` was caught.
#
# The leading class accepts start-of-string, '/', whitespace and quotes, so a
# bare filename in a command string anchors as surely as a path does.
B='(^|[/[:space:]"'"'"'])'
# Directories are named as prefixes, not as "dir/<file>.ext" — `rmdir .claude/hooks/`
# and `find .claude/hooks -delete` remove every hook at once, which the old
# "dir/[^/]*\.sh" form did not match. Over-blocking is safe here: a path is only
# tested after a write-ish command has already been detected, so `ls .claude/hooks/`
# still passes.
PROTECTED="(\.claude/hooks|\.claude/agents|\.claude/settings(\.local)?\.json|workforce/ops/sweep\.sh|workforce/core/constitution|workforce/core/charter\.md|workforce/core/index\.md|workforce/core/registry/(rhythm|people)\.md|${B}CLAUDE\.md|${B}CODEOWNERS|${B}\.goal$|${B}\.mcp\.json)"

protected_reason() {
  case "$1" in
    *hooks/*|*settings*|*sweep.sh) echo "the enforcement layer — hooks, their wiring, and the sweep that verifies the tree" ;;
    *rhythm.md)                    echo "liveness thresholds. Raising a threshold silences a probe rather than fixing it" ;;
    *constitution/*|*charter.md|*index.md) echo "doctrine — agents propose, a founder ratifies" ;;
    *people.md)                    echo "the founder register and conflict-resolution authority" ;;
    *agents/*)                     echo "an agent definition — an agent may not widen its own authority" ;;
    *CLAUDE.md|*CODEOWNERS)        echo "rank-1 under 00-precedence §1 — it outranks the trunk" ;;
    *.goal)                        echo "CSJ's standing mission contract. Agents execute it; they do not edit it" ;;
    *.mcp.json)                    echo "MCP server wiring — removing a server disables capability silently" ;;
    *)                             echo "gated to a founder" ;;
  esac
}

# ============================================================================
# A. FILE PATH RULES  (Write / Edit)
# ============================================================================
if [ -n "$path" ]; then
  base="$(basename "$path")"

  # A1. .env — never edited to work around a bug. *.example stays editable.
  case "$base" in
    *.example) : ;;
    .env|.env.*)
      deny "BLOCKED: never edit ${base} to work around a bug (MEMORY: feedback_never_touch_env_or_db). Surface the config problem and ask CSJ — credentials live only in each server's .env."
      ;;
  esac

  # A2. Founder-gated paths.
  if printf '%s' "$path" | grep -Eq "$PROTECTED"; then
    deny "BLOCKED: $(protected_reason "$path") (workforce/core/charter.md §2). An agent may make its machinery report MORE, never LESS. Write the proposal to workforce/ops/ and raise a gate; a founder applies it."
  fi

  exit 0
fi

# ============================================================================
# B. COMMAND RULES  (Bash / ssh_exec)
# ============================================================================
[ -z "$cmd" ] && exit 0

# --- B1. Table-dropping migrations ------------------------------------------
# Substring match, so ssh-wrapped variants are caught too.
if has 'migrate:(fresh|refresh)|db:wipe'; then
  if on_prod; then
    deny "BLOCKED ON PROD: \`migrate:fresh\`/\`migrate:refresh\`/\`db:wipe\` drops all tables — this is fynla.org with real customer data (CLAUDE.md). Migrations on prod use: php artisan migrate --force"
  fi
  deny "BLOCKED: \`migrate:fresh\`/\`migrate:refresh\`/\`db:wipe\` DROPS ALL TABLES and destroys local data (CLAUDE.md). To reseed without data loss run: php artisan db:seed"
fi

# --- B2. route:cache / artisan optimize -------------------------------------
# optimize:clear is fine — the ':' after 'optimize' excludes it.
if has 'route:cache' || has 'artisan[[:space:]]+optimize([^:[:alnum:]]|$)'; then
  if on_prod; then
    deny "BLOCKED ON PROD: \`route:cache\`/\`artisan optimize\` makes the SPA catch-all shadow '/' — guests and /m get the bare SPA shell (MEMORY: reference_route_cache_shadows_homepage). Use route:clear; end deploy chains with config:cache only."
  fi
  deny "BLOCKED: \`route:cache\`/\`artisan optimize\` breaks this app — the compiled route matcher lets the SPA catch-all shadow '/' and the /m iframe loads '/' (MEMORY: reference_route_cache_shadows_homepage). Use route:clear; re-cache config only with config:cache."
fi

# --- B3. artisan --env=testing (no .env.testing -> resolves to the dev DB) ---
if has '(^|[^[:alnum:]_])artisan([[:space:]]|$)' && has '--env[[:space:]=]+testing(\b|$)'; then
  deny "BLOCKED: \`php artisan --env=testing\` resolves to the 'laravel' dev DB (no .env.testing exists) and wipes it (MEMORY: feedback_never_artisan_env_testing). Use a DB_DATABASE= override against an isolated test DB instead."
fi

# --- B4. pkill/killall targeting vite ---------------------------------------
if has '\b(pkill|killall)\b[^|;&]*vite'; then
  deny "BLOCKED: killing vite by name also kills the sibling fynlaInternational dev server on :5174 (MEMORY: feedback_vite_canonical_port_5173). Kill by PID, or free the port with: lsof -ti tcp:5173 | xargs kill"
fi

# --- B5. Raw production builds ----------------------------------------------
# Matches npm/yarn/pnpm [run] build and [npx] vite build. NOT vitest, NOT dev vite.
if has '(^|[^[:alnum:]_/.-])(npm|yarn|pnpm)[[:space:]]+(run[[:space:]]+)?build(\b|$)' \
   || has '(^|[^[:alnum:]_/.-])(npx[[:space:]]+)?vite[[:space:]]+build(\b|$)'; then
  deny "BLOCKED: never run a raw production build — Vite env vars (VITE_BASE_PATH / VITE_ROUTER_BASE) must match the target or routing breaks silently (CLAUDE.md Build scripts). Use ./deploy/fynla-org/build.sh (main) or ./deploy/csjones-fynla/build.sh (dev). For iOS: ./deploy/mobile/build-ios.sh"
fi

# --- B6. PR #249 — parked, never merged, never deleted ----------------------
# Flags in any order: `gh pr merge --admin 249` must be caught as surely as
# `gh pr merge 249 --admin`.
if hasi 'gh[[:space:]]+pr[[:space:]]+(merge|close)([[:space:]]+--?[A-Za-z-]+([[:space:]]+[^[:space:]]+)?)*[[:space:]]+249([[:space:]]|$)' \
   || hasi 'gh[[:space:]]+api.*pulls/249/merge'; then
  deny "BLOCKED: PR #249 is PARKED — never merged, never deleted (.goal:16, workforce/core/charter.md §8). Only a founder can lift this."
fi

# --- B7. PR #303 — gated on CSJ's iOS device verification -------------------
if hasi 'gh[[:space:]]+pr[[:space:]]+merge([[:space:]]+--?[A-Za-z-]+([[:space:]]+[^[:space:]]+)?)*[[:space:]]+303([[:space:]]|$)' \
   || hasi 'gh[[:space:]]+api.*pulls/303/merge'; then
  deny "BLOCKED: PR #303 requires CSJ's iOS device verification before merge (.goal:12). Playwright cannot drive the native SwiftUI app, so it cannot be self-certified (workforce/core/constitution/08-process.md §3). Review and deploy freely; do not merge."
fi

# --- B8. Never cross production and csjones ---------------------------------
CSJONES='csjones|fynla-app|u163-ptanegf9edny'

if [ "$tool" = "mcp__ssh-fynla__ssh_exec" ] && hasi "$CSJONES"; then
  deny "BLOCKED: the ssh-fynla MCP tool is PRODUCTION (fynla.org) only — this command targets csjones (CLAUDE.md; workforce/core/charter.md §8). Use the csjones SSH path instead. Crossing the two breaks routing silently."
fi

if hasi 'ssh[^|;&]*(ssh\.fynla\.org|u2783-hrf1k8bpfg02)' && hasi "$CSJONES"; then
  deny "BLOCKED: this is a raw ssh to the PRODUCTION host (fynla.org) carrying csjones paths. The two environments use different VITE_BASE_PATH / RewriteBase values and the wrong combination breaks routing silently (CLAUDE.md)."
fi

# --- B9. Shell writes to founder-gated paths --------------------------------
# A guard wired only to Write|Edit is bypassed by `sed -i`, `cat >`, `rm`, `mv`,
# `git checkout --`, or any interpreter one-liner. Verified as a real bypass
# 2026-08-13. Only inspect commands that could plausibly write, delete or revert.
if has '(^|[^[:alnum:]_])(sed|tee|rm|rmdir|mv|cp|ln|truncate|install|patch|dd|chmod|chown)([[:space:]]|$)|>[[:space:]]*[^|]|>>|git[[:space:]]+(checkout|restore|revert)[[:space:]]+--|git[[:space:]]+rm([[:space:]]|$)|-delete([[:space:]]|$)|python3?[[:space:]]+-c|perl[[:space:]]+-[pi]|node[[:space:]]+-e'; then
  if printf '%s' "$cmd" | grep -Eq "$PROTECTED"; then
    deny "BLOCKED: this shell command writes to a protected path — $(protected_reason "$cmd") (workforce/core/charter.md §2). Routing a gated edit through the shell instead of Write/Edit does not change that it is gated. Propose it; a founder applies it."
  fi
fi

exit 0
