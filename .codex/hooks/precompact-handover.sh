#!/bin/bash
# precompact-handover.sh — PreCompact hook
#
# Last-resort handover writer. Runs when the harness is about to compact the
# transcript (manual /compact or auto-compact at the hard ceiling). The model
# may not have invoked /context-handover in time, so this hook writes a minimal
# safety-net handover from `git status` + recent log + transcript tail, so
# session-start has SOMETHING to read on resume.
#
# Output filename: handover-YYYY-MM-DD-session-N-clear-precompact.md
# (the `-precompact` suffix tells session-start "prefer a fuller -clear handover
# in the same folder if one exists").
#
# Has up to 30s to run (timeout in settings.json) — keep it lean.

set -u

# Resolve repo root from hook input cwd, fallback to $PWD, fallback to fynla main
INPUT=$(cat 2>/dev/null || echo '{}')
HOOK_CWD=$(printf '%s' "$INPUT" | python3 -c 'import sys, json
try:
    d = json.load(sys.stdin)
    print(d.get("cwd", ""))
except Exception:
    print("")
' 2>/dev/null)

CWD="${HOOK_CWD:-$PWD}"
cd "$CWD" 2>/dev/null || cd /Users/CSJ/Desktop/fynla || exit 0

# Find the worktree root (handles being inside a subdir or worktree)
WORKTREE_ROOT=$(git rev-parse --show-toplevel 2>/dev/null || echo "/Users/CSJ/Desktop/fynla")

# Handover ALWAYS goes into the main repo's <MONTH><DAY>Updates folder so session-start
# finds it regardless of which worktree the user resumes in.
REPO_ROOT="/Users/CSJ/Desktop/fynla"
VAULT_ROOT="/Users/CSJ/Desktop/fynlaBrain"

TODAY=$(date +%Y-%m-%d)
MONTH=$(date +%B)
DAY=$(date +%-d)
TARGET_FOLDER="${MONTH}/${MONTH}${DAY}Updates"
REPO_TARGET="${REPO_ROOT}/${TARGET_FOLDER}"
VAULT_TARGET="${VAULT_ROOT}/${TARGET_FOLDER}"

mkdir -p "$REPO_TARGET" "$VAULT_TARGET" 2>/dev/null

existing=$(ls "$REPO_TARGET"/handover-*.md 2>/dev/null | wc -l | tr -d ' ')
SESSION_N=$((existing + 1))
HANDOVER_NAME="handover-${TODAY}-session-${SESSION_N}-clear-precompact.md"
HANDOVER_PATH="${REPO_TARGET}/${HANDOVER_NAME}"

# Capture state from worktree root
cd "$WORKTREE_ROOT" 2>/dev/null || cd "$REPO_ROOT"

BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")
DIRTY=$(git status --short 2>/dev/null || echo "(git status failed)")
RECENT=$(git log --oneline -20 2>/dev/null || echo "(git log failed)")
DIFF_STAT=$(git diff --stat HEAD 2>/dev/null | tail -20 || echo "")

# Last few user prompts from transcript (best-effort)
TRANSCRIPT_PATH=$(printf '%s' "$INPUT" | python3 -c 'import sys, json
try:
    d = json.load(sys.stdin)
    print(d.get("transcript_path", ""))
except Exception:
    print("")
' 2>/dev/null)

LAST_PROMPTS=""
if [ -n "$TRANSCRIPT_PATH" ] && [ -f "$TRANSCRIPT_PATH" ]; then
  LAST_PROMPTS=$(python3 -c "
import json, sys
prompts = []
try:
    with open('$TRANSCRIPT_PATH') as f:
        for line in f:
            try:
                row = json.loads(line)
                if row.get('type') == 'user' and isinstance(row.get('message'), dict):
                    content = row['message'].get('content', '')
                    if isinstance(content, str) and content.strip() and not content.startswith('<'):
                        prompts.append(content[:300])
                    elif isinstance(content, list):
                        for c in content:
                            if isinstance(c, dict) and c.get('type') == 'text':
                                t = c.get('text', '').strip()
                                if t and not t.startswith('<'):
                                    prompts.append(t[:300])
            except Exception:
                pass
except Exception:
    pass
for p in prompts[-3:]:
    print('- ' + p.replace(chr(10), ' / '))
" 2>/dev/null)
fi

cat > "$HANDOVER_PATH" <<EOF
---
type: handover
mode: context-clear-precompact
date: ${TODAY}
session: ${SESSION_N}
branch: ${BRANCH}
trigger: PreCompact hook (auto-fired by harness — model did not invoke context-handover in time)
worktree: ${WORKTREE_ROOT}
---

# PreCompact Safety-Net Handover — ${TODAY}, Session ${SESSION_N}

> This handover was written automatically by the PreCompact hook, **not** by
> /context-handover. The model didn't get a chance to write a structured handover
> before compaction fired. This is a minimal snapshot — prefer any later
> non-precompact handover in this folder if one exists.

## Branch

${BRANCH}

## Worktree

${WORKTREE_ROOT}

## Working tree at compact-time

\`\`\`
${DIRTY:-clean}
\`\`\`

## Diff stat (HEAD)

\`\`\`
${DIFF_STAT:-(no uncommitted diff)}
\`\`\`

## Last 20 commits

\`\`\`
${RECENT}
\`\`\`

## Last 3 user prompts (best-effort from transcript)

${LAST_PROMPTS:-(transcript tail unavailable)}

## Pick up from here

- Read the chat transcript leading up to compaction.
- Run \`git diff HEAD~5\` from \`${WORKTREE_ROOT}\` to see recent code state.
- Look for any later, fuller handover (without \`-precompact\` suffix) in
  \`${TARGET_FOLDER}\` and prefer it over this one if found.
- If the working tree is dirty, decide whether to keep, commit, or revert
  before resuming work.
EOF

# Mirror to vault (best effort)
cp "$HANDOVER_PATH" "$VAULT_TARGET/" 2>/dev/null || true

# Don't fail the hook even on partial errors
exit 0
