#!/usr/bin/env bash
#
# PreToolUse guard — workforce blocks that were previously prose-only.
# Fail-closed on a match; fail-open on parse errors (same contract as siblings).
#
# Blocks:
#   1. Merging or closing PR #249 — parked indefinitely (.goal:16, charter.md §8)
#   2. Merging PR #303 — requires CSJ's iOS device verification (.goal:12)
#   3. ssh-fynla used against csjones — that MCP tool is PRODUCTION ONLY
#
# Matcher: Bash + mcp__ssh-fynla__ssh_exec
#
# Flag-order note: `gh pr merge --admin 249` must be caught as surely as
# `gh pr merge 249 --admin`. The number is matched anywhere in the gh invocation,
# not just immediately after the subcommand. Verified as a real bypass 2026-08-13.

set -euo pipefail

input="$(cat)"

cmd="$(printf '%s' "$input" | jq -r '.tool_input.command // empty' 2>/dev/null || true)"
tool="$(printf '%s' "$input" | jq -r '.tool_name // empty' 2>/dev/null || true)"

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

# --- 1. PR #249 — parked, never merged, never deleted -----------------------
# gh subcommand form, flags in any order, plus the raw REST equivalent.
if printf '%s' "$cmd" | grep -Eqi 'gh[[:space:]]+pr[[:space:]]+(merge|close)([[:space:]]+--?[A-Za-z-]+([[:space:]]+[^[:space:]]+)?)*[[:space:]]+249([[:space:]]|$)' \
   || printf '%s' "$cmd" | grep -Eqi 'gh[[:space:]]+pr[[:space:]]+(merge|close)[[:space:]]+249([[:space:]]|$)' \
   || printf '%s' "$cmd" | grep -Eqi 'gh[[:space:]]+api.*pulls/249/merge'; then
  deny "BLOCKED: PR #249 is PARKED — never merged, never deleted (.goal:16, workforce/core/charter.md §8). Only a founder can lift this."
fi

# --- 2. PR #303 — gated on CSJ's iOS device verification --------------------
if printf '%s' "$cmd" | grep -Eqi 'gh[[:space:]]+pr[[:space:]]+merge([[:space:]]+--?[A-Za-z-]+([[:space:]]+[^[:space:]]+)?)*[[:space:]]+303([[:space:]]|$)' \
   || printf '%s' "$cmd" | grep -Eqi 'gh[[:space:]]+pr[[:space:]]+merge[[:space:]]+303([[:space:]]|$)' \
   || printf '%s' "$cmd" | grep -Eqi 'gh[[:space:]]+api.*pulls/303/merge'; then
  deny "BLOCKED: PR #303 requires CSJ's iOS device verification before merge (.goal:12). Playwright cannot drive the native SwiftUI app, so it cannot be self-certified (workforce/core/constitution/08-process.md §3). Review and deploy freely; do not merge."
fi

# --- 3. ssh-fynla is PRODUCTION — never point it at csjones -----------------
csjones_markers='csjones|fynla-app|u163-ptanegf9edny'

if [ "$tool" = "mcp__ssh-fynla__ssh_exec" ]; then
  if printf '%s' "$cmd" | grep -Eqi "$csjones_markers"; then
    deny "BLOCKED: the ssh-fynla MCP tool is PRODUCTION (fynla.org) only — this command targets csjones (CLAUDE.md; workforce/core/charter.md §8). Use the csjones SSH path instead. Crossing the two breaks routing silently."
  fi
fi

# 3b. The mirror case: a raw ssh to the PRODUCTION host carrying csjones paths.
#     Previously claimed in this file's comments but never implemented.
if printf '%s' "$cmd" | grep -Eqi 'ssh[^|;&]*(ssh\.fynla\.org|u2783-hrf1k8bpfg02)'; then
  if printf '%s' "$cmd" | grep -Eqi "$csjones_markers"; then
    deny "BLOCKED: this is a raw ssh to the PRODUCTION host (fynla.org) carrying csjones paths. The two environments use different VITE_BASE_PATH / RewriteBase values and the wrong combination breaks routing silently (CLAUDE.md)."
  fi
fi

exit 0
