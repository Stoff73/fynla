---
name: session-start
description: Bootstrap a new development session. Checks for overnight issues, cleans up stale worktrees/branches, syncs git, audits for broken code, seeds the database, starts the dev server, and displays context. Run at the start of every session. Use when the user says "start session", "get ready", "set up", "begin", or at the start of any new conversation.
disable-model-invocation: true
---

# Session Start - Pre-Session Bootstrap

Prepare the development environment for a new Fynla session. This is the FIRST thing that runs in every session.

## Step 1: Read Memory, TODO & Context

Read the project memory to understand current state:

```bash
cat /Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/MEMORY.md
```

**Read CSJTODO.md** — this is the handover from the previous session. It contains outstanding items, tech debt, known issues, and context for what to pick up:

```bash
cat CSJTODO.md 2>/dev/null || echo "No CSJTODO.md — clean slate"
```

If CSJTODO.md exists and has unchecked items, present them to the user prominently:

```markdown
## Outstanding from Previous Session

[items from CSJTODO.md]

Would you like to address these first, or work on something else?
```

Check for any other handover notes from previous sessions:

```bash
# Check for recent handover/update notes
find March/ -name "*.md" -newer CLAUDE.md -mtime -1 2>/dev/null | head -10
```

## Step 1b: Load Vault Context (fynlaBrain)

Load accumulated knowledge from the fynlaBrain Obsidian vault. This ensures every session starts with the lessons learned from all previous sessions.

### Read ALL feedback rules (NON-NEGOTIABLE)

```bash
ls /Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_*.md
```

Read EVERY `feedback_*.md` file and present a summary of each rule. These rules apply to ALL work in ALL sessions. Present them prominently:

```markdown
### Active Rules (from previous sessions)
- [rule name]: [one-line summary]
```

### Read recent session history from vault

```bash
# Get the 3 most recent session update folders
ls -d /Users/CSJ/Desktop/fynlaBrain/March/March*Updates 2>/dev/null | sort -V | tail -3
```

For each of the 3 most recent folders, read:
- Deploy notes (`deploy*.md`, `*deploy*.md`) — what was deployed, what broke
- Session summaries (`session*.md`, `*summary*.md`) — what was worked on
- TODO files (`*TODO*.md`, `CSJTODO.md`) — outstanding items

Present key items from each session.

### Read vault TODO (may be newer than repo)

```bash
find /Users/CSJ/Desktop/fynlaBrain/March -name "CSJTODO.md" -type f 2>/dev/null | sort -V | tail -1
```

If it exists and has content not in the repo TODO.md, present both and note the difference.

### Check recent reports

```bash
find /Users/CSJ/Desktop/fynlaBrain/Reports -name "*.md" -mtime -7 2>/dev/null
```

If any reports from the last 7 days, read and summarise key findings (tech debt, security, code review).

### Present vault context

```markdown
## Vault Context Loaded

**Feedback Rules (MUST follow):**
- [each rule — name and one-line summary]

**Recent Sessions:**
- [date]: [what was worked on, deployed, outstanding]

**Outstanding from Vault:**
- [items from vault CSJTODO if different from repo TODO]

**Recent Reports:**
- [findings from last 7 days, or "None"]
```

## Step 2: Git Sync & Cleanup

### 2a: Check current state

```bash
git status
git rev-parse --abbrev-ref HEAD
git fetch origin
```

### 2b: Clean up stale worktrees and branches

Previous sessions may have left orphaned worktrees or unmerged branches. Clean them up:

```bash
# List all worktrees
git worktree list

# Remove any orphaned worktrees (agent-* or worktree-*)
for dir in .claude/worktrees/agent-*/; do
  if [ -d "$dir" ]; then
    agent=$(basename "$dir")
    # Check if it has uncommitted changes
    changes=$(cd "$dir" && git diff --name-only HEAD 2>/dev/null | wc -l)
    if [ "$changes" -gt 0 ]; then
      echo "WARNING: $agent has $changes uncommitted changes — review before removing"
    else
      git worktree remove "$dir" --force 2>/dev/null
      git branch -D "worktree-$agent" 2>/dev/null
      echo "Cleaned up: $agent"
    fi
  fi
done
```

If worktrees with uncommitted changes are found, report them to the user before removing.

### 2c: Clean up stale branches

```bash
# List local branches that no longer have a remote
git branch -vv | grep ': gone]' | awk '{print $1}'

# List feature branches (but don't delete — report to user)
git branch | grep -v main | grep -v '\*'
```

Report any stale branches but do NOT auto-delete. Ask the user.

### 2d: Sync main

```bash
git pull origin main
```

If there are conflicts, resolve them or report to the user.

### 2e: Check what changed since last session

```bash
# Last session's commits
git log --since="yesterday" --oneline

# Any uncommitted work
git diff --stat HEAD
```

## Step 3: Overnight Issue Detection

Check for common issues that accumulate between sessions:

### 3a: Syntax check recently changed PHP files

```bash
# Check PHP syntax on files changed in the last 24 hours
for file in $(git diff --name-only HEAD~5 -- '*.php' 2>/dev/null); do
  php -l "$file" 2>&1 | grep -v "No syntax errors"
done
```

### 3b: Check for broken imports/references

```bash
# Check for any PHP files referencing classes that don't exist
# (catches namespace issues from merges)
php artisan route:list --json 2>&1 | head -5
```

If `route:list` throws errors, there are broken references that need fixing before anything else.

### 3c: Check migration status

```bash
php artisan migrate:status 2>&1 | grep -i "pending\|No\|error" | head -5
```

If there are pending migrations, report them but do NOT auto-run. Ask the user.

### 3d: Check for merge conflict markers left in code

```bash
# Search for unresolved conflict markers
grep -rn "<<<<<<< " --include="*.php" --include="*.vue" --include="*.js" app/ resources/ 2>/dev/null | head -10
```

If found, these MUST be fixed immediately — they will cause runtime errors.

## Step 4: Database Seed

Seed the database to ensure all reference data is current:

```bash
php artisan db:seed
```

If seeding fails, diagnose immediately:

| Error | Fix |
|-------|-----|
| Table doesn't exist | `php artisan migrate && php artisan db:seed` |
| Duplicate key | Safe to ignore — seeders use `updateOrCreate()` |
| Connection refused | Check MySQL is running: `mysql.server start` |

## Step 5: Start Dev Server

Check if already running, then start if needed:

```bash
# Check if Laravel/Vite are running
lsof -i :8000 2>/dev/null | head -3
lsof -i :5173 2>/dev/null | head -3
```

If not running:

```bash
./dev.sh
```

Run in background so bootstrap can continue.

## Step 6: Quick Health Check

Run a fast compilation check to catch any broken Vue/JS:

```bash
# Check Vite can resolve all imports (will show errors in dev.sh output)
# If dev.sh is running, check for compilation errors in its output
```

Also check for any TODO/FIXME items from the last session:

```bash
# Recent TODOs in changed files
git diff --name-only HEAD~5 -- '*.php' '*.vue' 2>/dev/null | xargs grep -n "TODO\|FIXME\|HACK\|XXX" 2>/dev/null | head -10
```

## Step 7: Session Context Display

Present a clean summary:

```markdown
## Session Ready

**Date:** [today's date]
**Branch:** `branch-name`
**Status:** Clean / X uncommitted changes
**Last work:** [summary of recent commits]

**Environment:**
- Database: Seeded successfully
- Dev server: Running on :8000/:5173
- PHP syntax: All clear / X errors found
- Migrations: Up to date / X pending
- Conflict markers: None / X found (CRITICAL)

**Stale worktrees:** None / X cleaned up / X need review
**Stale branches:** None / X reported

**Recent changes (last 24hrs):**
- [list from git log]

**Overnight issues found:**
- [any issues from Step 3, or "None"]

**Ready to work.** What would you like to do?
```

## Important

- ALWAYS seed the database. No exceptions.
- Do NOT run `migrate:fresh` or `migrate:refresh` — these destroy data.
- Do NOT auto-delete branches with uncommitted work — ask the user.
- Do NOT make code changes in this skill — this is diagnostic and bootstrap only.
- If overnight issues are found (conflict markers, broken imports), fix those FIRST before accepting new work.
- Clean up worktrees that have no changes — they're from completed agent runs.
- Report stale branches but let the user decide what to do with them.
