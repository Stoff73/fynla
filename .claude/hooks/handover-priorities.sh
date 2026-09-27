#!/usr/bin/env bash
#
# UserPromptSubmit hook. CSJ 2026-09-27: put the current handover's priority
# list and the working rules in front of Claude on EVERY prompt, so the list
# is worked in order and nothing drifts. Deterministic: newest handover by
# filename date, section from "## Priorities" to the next "## " heading.

REPO="/Users/CSJ/Desktop/fynla"

latest="$(find "$REPO/handover" -name 'handover-*.md' 2>/dev/null \
  | awk -F/ '{print $NF "\t" $0}' | sort | tail -1 | cut -f2)"

priorities=""
if [ -n "$latest" ]; then
  priorities="$(awk '/^## Priorities/{on=1; next} on && /^## /{exit} on' "$latest" | head -60)"
fi

rules="STANDING RULES (CSJ 2026-09-27, enforced every prompt):
1. Work the handover priority list below IN ORDER. Item 1 first. Do not start anything not on it unless CSJ names it in this message.
2. One job per session: the job CSJ names. Anything else you find goes on the list, not into the session.
3. Do not ask questions the code, spec, memory or handover already answer. Decide from the evidence and do the work. An unanswered question never parks a whole item.
4. NEVER run a full test suite locally (a hook blocks it). Run only the test files the change touches. CI runs the rest.
5. Read MEMORY.md feedback before acting. Fix defects found in the path; do not report them for later."

context="$rules

CURRENT HANDOVER: ${latest#$REPO/}
$priorities"

jq -n --arg c "$context" '{hookSpecificOutput: {hookEventName: "UserPromptSubmit", additionalContext: $c}}'
