#!/bin/bash
# context-watch.sh — UserPromptSubmit hook
#
# Estimates current transcript token count from $CLAUDE_TRANSCRIPT_PATH and emits
# an escalating system-reminder when approaching the 250k soft cap CSJ has set
# for Fynla sessions.
#
# Tiers (chars/4 ≈ tokens):
#   >200k tokens (>80%)   — gentle: "consider /context-handover at next break"
#   >225k tokens (>90%)   — strong: "run /context-handover BEFORE starting new work"
#   >243k tokens (>97.5%) — STOP:   "invoke /context-handover NOW, then /clear"
#
# The output is read by Claude Code as additional context for the user's prompt
# (per UserPromptSubmit hook contract — stdout is injected as system context).
#
# This hook is fast (no git, no network) — must finish well within the 5s timeout.

set -u

# Read JSON payload from stdin
INPUT=$(cat 2>/dev/null || echo '{}')

# Extract transcript path with python3 (universal on macOS, no jq dependency)
TRANSCRIPT_PATH=$(printf '%s' "$INPUT" | python3 -c 'import sys, json
try:
    d = json.load(sys.stdin)
    print(d.get("transcript_path", ""))
except Exception:
    print("")
' 2>/dev/null)

# Bail silently if we cannot read the transcript — never break the user's prompt
[ -z "$TRANSCRIPT_PATH" ] && exit 0
[ ! -f "$TRANSCRIPT_PATH" ] && exit 0

CHARS=$(wc -c < "$TRANSCRIPT_PATH" 2>/dev/null | tr -d ' ')
[ -z "$CHARS" ] && exit 0
[ "$CHARS" -lt 1000 ] && exit 0  # tiny transcript, skip

TOKENS=$((CHARS / 4))
TOKENS_K=$((TOKENS / 1000))

# Tripwire — escalating tiers
if [ "$TOKENS" -gt 243000 ]; then
  cat <<EOF
<system-reminder>
CONTEXT TRIPWIRE — transcript at ~${TOKENS_K}k tokens (>97.5% of CSJ's 250k Fynla budget).

STOP current work. Invoke the context-handover skill NOW via Skill({skill:"context-handover"}).
After it writes the handover, run /clear, then say "start session". The session-start skill
will auto-resume from the "Pick up from here" block in the handover without asking.

This is a non-negotiable instruction from CSJ. Do not continue past this prompt with new work.
You may finish a single in-flight tool call if it's already running, but DO NOT start any new
investigation, edit, or test before invoking context-handover.
</system-reminder>
EOF
elif [ "$TOKENS" -gt 225000 ]; then
  cat <<EOF
<system-reminder>
Context at ~${TOKENS_K}k tokens (>90% of CSJ's 250k Fynla budget).

Invoke Skill({skill:"context-handover"}) BEFORE starting any new investigation, edit, or
multi-file change. Finishing a single in-flight task is fine; do not start new work.
</system-reminder>
EOF
elif [ "$TOKENS" -gt 200000 ]; then
  cat <<EOF
<system-reminder>
Context at ~${TOKENS_K}k tokens (>80% of CSJ's 250k Fynla budget).

Consider invoking Skill({skill:"context-handover"}) at the next natural break — end of
the current task, after the next commit, or before starting a new module of work.
</system-reminder>
EOF
fi

exit 0
