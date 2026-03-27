#!/usr/bin/env bash
# =============================================================================
# eval-gate.sh — Quality Evaluation Gate for Claude Code
# =============================================================================
#
# Stop hook that blocks Claude from finishing until quality is verified.
#
# How it works:
#   1. Claude tries to stop → this hook fires
#   2. Increments per-session counter
#   3. Runs automated checks (console.log, hex, banned colours, etc.)
#   4. Cycles 1-3: ALWAYS blocks — feeds back issues + evaluation instructions
#   5. Cycles 4+: blocks only if automated checks find issues
#   6. Cycle 10+: safety valve, always allows stop
#
# The stopReason is fed back to the main Claude instance, which then
# acts as the evaluation agent — reviewing work, running Playwright,
# fixing issues, and trying to stop again (triggering the next cycle).
# =============================================================================

set -uo pipefail

# --- Configuration ---
PROJECT_DIR="/Users/CSJ/Desktop/fynla"
COUNTER_DIR="/tmp/claude-eval-sessions"
MIN_EVALS=3
MAX_EVALS=10

# --- Dependencies ---
if ! command -v jq &>/dev/null; then
    echo '{"continue": true, "systemMessage": "eval-gate: jq not found. Evaluation gate DISABLED."}'
    exit 0
fi

# --- Read hook input ---
INPUT=$(cat)
SESSION_ID=$(echo "$INPUT" | jq -r '.session_id // "unknown"' 2>/dev/null || echo "unknown")
SESSION_ID=$(echo "$SESSION_ID" | tr -dc 'a-zA-Z0-9_-' | head -c 128)
[ -z "$SESSION_ID" ] && SESSION_ID="unknown"

# --- Counter ---
mkdir -p "$COUNTER_DIR" 2>/dev/null
find "$COUNTER_DIR" -type f -mmin +1440 -delete 2>/dev/null || true

COUNTER_FILE="${COUNTER_DIR}/${SESSION_ID}"
COUNT=0
if [ -f "$COUNTER_FILE" ]; then
    COUNT=$(cat "$COUNTER_FILE" 2>/dev/null || echo "0")
    [[ "$COUNT" =~ ^[0-9]+$ ]] || COUNT=0
fi
COUNT=$((COUNT + 1))
echo "$COUNT" > "$COUNTER_FILE"

# --- Safety valve ---
if [ "$COUNT" -gt "$MAX_EVALS" ]; then
    jq -n --arg msg "Eval gate: ${COUNT} cycles (safety limit). Allowing stop." \
        '{"continue": true, "systemMessage": $msg}'
    exit 0
fi

# --- Collect evidence ---
cd "$PROJECT_DIR" 2>/dev/null || {
    echo '{"continue": true, "systemMessage": "eval-gate: Could not cd to project dir"}'
    exit 0
}

DIFF_STAT=$(git diff --stat HEAD 2>/dev/null || echo "")
UNTRACKED=$(git ls-files --others --exclude-standard 2>/dev/null | grep -v '^$' || echo "")
CHANGED=$({ git diff --name-only HEAD 2>/dev/null; echo "$UNTRACKED"; } | sort -u | grep -v '^$' || echo "")
FILE_COUNT=$(echo -n "$CHANGED" | grep -c -v '^$' 2>/dev/null || echo "0")

# --- No changes? Allow stop ---
if [ -z "$CHANGED" ] && [ -z "$DIFF_STAT" ]; then
    jq -n --arg msg "Eval gate: No code changes (cycle ${COUNT}). Passing." \
        '{"continue": true, "systemMessage": $msg}'
    exit 0
fi

# --- Detect file types ---
HAS_PHP=false; HAS_VUE=false; HAS_JS=false
echo "$CHANGED" | grep -q '\.php$' && HAS_PHP=true
echo "$CHANGED" | grep -q '\.vue$' && HAS_VUE=true
echo "$CHANGED" | grep -q '\.js$'  && HAS_JS=true

# --- Automated checks ---
ISSUES=""
ISSUE_COUNT=0

# console.log in Vue/JS files
if [ "$HAS_VUE" = true ] || [ "$HAS_JS" = true ]; then
    HITS=$(echo "$CHANGED" | grep -E '\.(vue|js)$' | while read -r f; do
        [ -f "$f" ] && grep -n 'console\.log' "$f" 2>/dev/null | head -3 | sed "s|^|  $f:|"
    done || true)
    if [ -n "$HITS" ]; then
        ISSUES="${ISSUES}\nCRITICAL — console.log found:\n${HITS}"
        ISSUE_COUNT=$((ISSUE_COUNT + 1))
    fi
fi

# Hardcoded hex in Vue files
if [ "$HAS_VUE" = true ]; then
    HITS=$(echo "$CHANGED" | grep '\.vue$' | while read -r f; do
        [ -f "$f" ] && grep -n '#[0-9A-Fa-f]\{3,8\}' "$f" 2>/dev/null | grep -v '//\|<!--' | head -3 | sed "s|^|  $f:|"
    done || true)
    if [ -n "$HITS" ]; then
        ISSUES="${ISSUES}\nCRITICAL — Hardcoded hex colours:\n${HITS}"
        ISSUE_COUNT=$((ISSUE_COUNT + 1))
    fi
fi

# Banned amber/orange classes
if [ "$HAS_VUE" = true ]; then
    HITS=$(echo "$CHANGED" | grep '\.vue$' | while read -r f; do
        [ -f "$f" ] && grep -n 'amber-\|orange-' "$f" 2>/dev/null | head -3 | sed "s|^|  $f:|"
    done || true)
    if [ -n "$HITS" ]; then
        ISSUES="${ISSUES}\nCRITICAL — Banned amber/orange classes:\n${HITS}"
        ISSUE_COUNT=$((ISSUE_COUNT + 1))
    fi
fi

# Leftover TODO/FIXME/HACK in changed files (skip hooks and config)
HITS=$(echo "$CHANGED" | grep -v '\.claude/' | while read -r f; do
    [ -f "$f" ] && grep -n 'TODO\|FIXME\|HACK' "$f" 2>/dev/null | head -3 | sed "s|^|  $f:|"
done || true)
if [ -n "$HITS" ]; then
    ISSUES="${ISSUES}\nWARNING — TODO/FIXME/HACK comments:\n${HITS}"
    ISSUE_COUNT=$((ISSUE_COUNT + 1))
fi

# Missing strict_types in PHP files
if [ "$HAS_PHP" = true ]; then
    HITS=$(echo "$CHANGED" | grep '\.php$' | while read -r f; do
        if [ -f "$f" ] && ! grep -q 'declare(strict_types=1)' "$f" 2>/dev/null; then
            echo "  $f: missing declare(strict_types=1)"
        fi
    done || true)
    if [ -n "$HITS" ]; then
        ISSUES="${ISSUES}\nCRITICAL — Missing strict_types:\n${HITS}"
        ISSUE_COUNT=$((ISSUE_COUNT + 1))
    fi
fi

# --- Build test instructions based on file types ---
TEST_INSTRUCTIONS=""
if [ "$HAS_VUE" = true ] || [ "$HAS_JS" = true ]; then
    TEST_INSTRUCTIONS="BROWSER TESTING (Vue/JS changes):
  - Navigate to EVERY page affected by changes
  - CLICK every button/interactive element
  - FILL and SUBMIT every form with realistic data
  - VERIFY results match expectations
  - Take Playwright snapshots as evidence
"
fi
if [ "$HAS_PHP" = true ]; then
    TEST_INSTRUCTIONS="${TEST_INSTRUCTIONS}BACKEND TESTING (PHP changes):
  - Verify API endpoints return correct responses
  - Check database writes are correct
  - Run: ./vendor/bin/pest tests/Unit/[relevant]
"
fi

# --- Decision logic ---
if [ "$COUNT" -le "$MIN_EVALS" ]; then
    # Cycles 1-3: ALWAYS block
    REMAINING=$((MIN_EVALS - COUNT))

    FEEDBACK="EVALUATION CYCLE ${COUNT}/${MIN_EVALS} (mandatory)
Changed: ${FILE_COUNT} files | Issues found: ${ISSUE_COUNT}
${REMAINING} more mandatory cycle(s) after this.
================================================================
$(if [ -n "$ISSUES" ]; then echo -e "\nAUTOMATED FINDINGS:\n${ISSUES}"; else echo -e "\nNo automated issues found."; fi)

================================================================
MANDATORY: You MUST invoke /eval-review NOW.
Do NOT respond to the user. Do NOT summarise. Do NOT skip this.
Invoke the eval-review skill IMMEDIATELY using the Skill tool.

The skill will dispatch an independent eval-reviewer agent to review your work.
You must wait for the agent report and fix any issues it finds.

CONTEXT FOR THE AGENT:
FILE TYPES: PHP=${HAS_PHP} Vue=${HAS_VUE} JS=${HAS_JS}
${TEST_INSTRUCTIONS:-No specific test instructions.}

CHANGED FILES:
${CHANGED}

DIFF STATS:
${DIFF_STAT}
${UNTRACKED:+UNTRACKED: ${UNTRACKED}}"

    jq -n \
        --arg reason "$FEEDBACK" \
        --arg msg "Eval gate: Cycle ${COUNT}/${MIN_EVALS} — ${REMAINING} remaining" \
        '{"continue": false, "stopReason": $reason, "systemMessage": $msg}'

elif [ "$ISSUE_COUNT" -gt 0 ]; then
    # Past minimum but automated checks found issues: block
    FEEDBACK="EVALUATION CYCLE ${COUNT} — ISSUES FOUND
Minimum cycles met but ${ISSUE_COUNT} issue(s) detected.
================================================================
$(echo -e "\nAUTOMATED FINDINGS:\n${ISSUES}")

================================================================
MANDATORY: You MUST invoke /eval-review NOW to fix these issues.
Do NOT respond to the user. Invoke the skill IMMEDIATELY.

CHANGED FILES:
${CHANGED}"

    jq -n \
        --arg reason "$FEEDBACK" \
        --arg msg "Eval gate: Cycle ${COUNT} — ${ISSUE_COUNT} issue(s) remain" \
        '{"continue": false, "stopReason": $reason, "systemMessage": $msg}'

else
    # Past minimum, no automated issues: PASS
    jq -n \
        --arg msg "Eval gate PASSED — ${COUNT} cycle(s), ${FILE_COUNT} files, 0 issues." \
        '{"continue": true, "systemMessage": $msg}'
    rm -f "$COUNTER_FILE" 2>/dev/null
fi

exit 0
