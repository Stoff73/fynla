#!/usr/bin/env bash
#
# PreToolUse guard — the repair/weaken asymmetry (workforce/core/charter.md §2).
#
# The workforce may make its own machinery report MORE. It may never make it
# report LESS. This is the only guardrail whose failure would be invisible:
# everything else fails loudly, this one fails by going quiet and looking healthy.
#
# Matchers: Write|Edit  AND  Bash
#
# Write/Edit is checked by file_path. Bash is ALSO checked, because a guard wired
# only to Write|Edit is bypassed by `sed -i`, `cat >`, `rm`, `mv`, `git checkout --`,
# or any interpreter one-liner. Verified as a real bypass 2026-08-13.

set -euo pipefail

input="$(cat)"

path="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null || true)"
cmd="$(printf '%s'  "$input" | jq -r '.tool_input.command  // empty' 2>/dev/null || true)"

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

# Protected paths, as extended regexes. Anything here is gated to a founder.
#   enforcement  — the hooks, their wiring, and the sweep that checks the tree
#   oversight    — liveness thresholds
#   doctrine     — constitution, charter, index, the mission
#   authority    — agent definitions, founder register
#   supremacy    — CLAUDE.md and CODEOWNERS outrank the trunk (00-precedence §1)
PROTECTED='(\.claude/hooks/[^/]*\.sh|\.claude/settings(\.local)?\.json|workforce/ops/sweep\.sh|workforce/core/registry/rhythm\.md|workforce/core/constitution/[^/]*\.md|workforce/core/charter\.md|workforce/core/index\.md|workforce/core/registry/people\.md|\.claude/agents/[^/]*\.md|(^|/)CLAUDE\.md|\.github/CODEOWNERS|(^|/)\.goal$|(^|/)\.mcp\.json)'

reason_for() {
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

# --- Write / Edit -----------------------------------------------------------
if [ -n "$path" ]; then
  if printf '%s' "$path" | grep -Eq "$PROTECTED"; then
    deny "BLOCKED: $(reason_for "$path") (workforce/core/charter.md §2). An agent may make its machinery report MORE, never LESS. Write the proposal to workforce/ops/ and raise a gate; a founder applies it."
  fi
  exit 0
fi

# --- Bash: shell writes to the same paths -----------------------------------
if [ -n "$cmd" ]; then
  # Only inspect commands that could plausibly write, delete or revert a file.
  if printf '%s' "$cmd" | grep -Eq '(^|[^[:alnum:]_])(sed|tee|rm|mv|cp|truncate|install|patch|dd|chmod|chown)([[:space:]]|$)|>[[:space:]]*[^|]|>>|git[[:space:]]+(checkout|restore|revert)[[:space:]]+--|python3?[[:space:]]+-c|perl[[:space:]]+-[pi]|node[[:space:]]+-e'; then
    if printf '%s' "$cmd" | grep -Eq "$PROTECTED"; then
      deny "BLOCKED: this shell command writes to a protected path — $(reason_for "$cmd") (workforce/core/charter.md §2). Routing a gated edit through the shell instead of Write/Edit does not change that it is gated. Propose it; a founder applies it."
    fi
  fi
fi

exit 0
