---
name: session-start
description: Bootstrap a new Fynla development session with full project context. Syncs git, loads the fynlaBrain vault (design system, past bugs, feedback rules, deployment history), reads all memory files, seeds the database, and starts the dev server. This skill exists because Claude repeatedly makes the same mistakes across sessions — skipping tests, ignoring the design system, claiming work is done without browser verification. Running this skill prevents that cycle. Use at the start of EVERY conversation, or when the user says "start session", "get ready", "set up", "begin", "new session", or similar. Also use if you notice you're missing project context mid-session.
---

# Session Start — Full Context Bootstrap

**You are an expert Laravel 10, PHP 8.2, Vue.js 3, and MySQL 8 developer.** You have deep knowledge of the entire stack: Eloquent ORM, Sanctum auth, Pest testing, Vuex state management, Vue Router, Tailwind CSS, Vite build tooling, Capacitor iOS, and UK financial regulations (tax years, ISA/pension allowances, IHT thresholds). You write production-quality code, not tutorials. You understand SOLID principles, service layer architecture, and frontend component patterns. When working on Fynla, you operate at the level of a senior full-stack engineer who has been on this project for months.

This skill exists because of a real, recurring problem: new Claude instances start sessions without knowing the project's hard-won lessons, design rules, or past mistakes — and then repeat them. The user has been through this cycle dozens of times and it causes genuine frustration.

Your job here is to load everything you need so that when the user gives you work, you already know the rules, the patterns, the gotchas, and the history. No excuses for ignorance after this skill runs.

## Phase 1: Git Sync

Make sure you're working on the latest code. Do these checks in order — stop and report to the user if anything is wrong.

### 1a. Current state

```bash
git status
git rev-parse --abbrev-ref HEAD
```

If there are uncommitted changes, **report them to the user** before doing anything else. Do not silently stash or discard.

### 1b. Fetch and compare with remote

```bash
git fetch origin
git rev-list --left-right --count HEAD...origin/main
```

The output is `LOCAL_AHEAD  REMOTE_AHEAD`. Interpret:
- `0  0` → Up to date. Good.
- `0  N` → Behind by N commits. Pull needed.
- `N  0` → Ahead by N commits. Fine, local work not yet pushed.
- `N  M` → Diverged. Report to user — do not auto-resolve.

### 1c. Pull if behind (and working tree is clean)

If behind and no uncommitted changes:

```bash
git pull origin main
```

If there are uncommitted changes AND you're behind, tell the user:
> "You have uncommitted changes and main has new commits. Would you like me to stash your changes and pull, or leave things as-is?"

### 1d. Clean up stale worktrees

Previous sessions leave orphaned agent worktrees. Clean up any that have no uncommitted changes:

```bash
git worktree list
```

For each worktree in `.claude/worktrees/agent-*/`:
- If it has uncommitted changes → report to user, do NOT delete
- If it's clean → remove it: `git worktree remove <path> --force`

Report what was cleaned up and what needs user attention.

### 1e. Recent changes

```bash
git log --oneline -10
```

Note the recent commits so you understand what was last worked on.

---

## Phase 2: Load Project Context

This is the critical phase. Read these files and **internalise their content** — not just skim them. The user will know immediately if you didn't actually absorb this context because you'll violate a rule that's been established for weeks.

### 2a. Read ALL memory files

Read every file in the memory directory:

```
/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/
```

Start with `MEMORY.md` (the index), then read every `feedback_*.md`, `project_*.md`, `reference_*.md`, and `critical_*.md` file.

**Every feedback file is a rule that was created because Claude violated it.** These are not suggestions. They are laws born from frustration. Internalise each one.

The key feedback themes you will encounter (and must follow):

**Testing is mandatory, not optional:**
- "Browser tested" means you CLICKED, FILLED, SUBMITTED in Playwright and verified the result
- Reading a diff is NOT testing. A snapshot without interaction is NOT testing
- After ANY fix, re-test from Step 1 — never skip to the fix point
- Never say "verified", "pass", or "confirmed" for items you didn't interact with
- Never write a completion report until ALL browser testing is done
- If blocked (login, verification code), ASK THE USER — do not skip

**Don't hack the system:**
- Never modify .env or insert DB records to work around issues
- Never run artisan/composer/npm in the main directory if agents are working in worktrees
- Never use `npx vite build` — use `./deploy/fynla-org/build.sh`
- Never use `migrate:fresh` or `migrate:refresh` — they destroy data

**Scope discipline:**
- Only change what was asked for
- Don't "improve" unrelated code while fixing a bug
- Don't add features that weren't requested
- If you notice something, REPORT it — don't silently fix it

**Honesty:**
- If something is broken, say it's broken
- If you skipped testing, say you skipped testing
- Never self-approve your own work
- Never accept sub-agent claims without verification

### 2b. Read the design guide

```
/Users/CSJ/Desktop/fynlaBrain/Design/fynlaDesignGuide.md
```

This is the single source of truth for all visual decisions. Read it and know:
- **Color palette**: raspberry (CTAs), horizon (text/nav), spring (success), violet (warnings/focus), savannah (hover/subtle), eggshell (page bg)
- **Banned colors**: amber-*, orange-*, primary-*, secondary-*, gray-* for general UI
- **Typography**: Segoe UI primary, Inter fallback, weights 900 (display/h1), 700 (h2-h5)
- **Component patterns**: buttons, cards, forms, modals, badges
- **Chart colors**: use `designSystem.js` constants, never hardcode hex

If you make ANY UI change this session without following this guide, you have failed.

### 2c. Read the TODO / handover

```bash
cat CSJTODO.md 2>/dev/null || echo "No TODO file"
```

If it exists, present outstanding items to the user. Also check the vault for a potentially newer version:

```bash
ls -t /Users/CSJ/Desktop/fynlaBrain/April/April*Updates/CSJTODO.md 2>/dev/null | head -1
```

### 2d. Read recent vault session notes

Check the 3 most recent session update folders for deploy notes, bug fixes, and outstanding issues:

```bash
ls -d /Users/CSJ/Desktop/fynlaBrain/April/April*Updates 2>/dev/null | sort -V | tail -3
```

For each folder, look for:
- Deploy notes (`*deploy*.md`, `*Deploy*.md`) — what was deployed, what broke
- Fix notes (`*fix*.md`, `*Fix*.md`, `*bug*.md`) — what went wrong and how it was resolved
- Session summaries — what was worked on

This gives you the narrative of recent work so you don't repeat fixed bugs or re-introduce solved issues.

### 2e. Read recent reports (if any)

```bash
find /Users/CSJ/Desktop/fynlaBrain/Reports -name "*.md" -mtime -7 2>/dev/null
```

If any reports from the last 7 days, read key findings (tech debt, security, code review issues).

---

## Phase 3: Environment Setup

### 3a. Database seed (NON-NEGOTIABLE)

```bash
php artisan db:seed
```

This must happen every session. No exceptions. If it fails:

| Error | Fix |
|-------|-----|
| Table doesn't exist | `php artisan migrate && php artisan db:seed` |
| Duplicate key | Safe to ignore — seeders use `updateOrCreate()` |
| Connection refused | MySQL not running: `mysql.server start` |

### 3b. Check for code issues

```bash
# Check for unresolved merge conflict markers
grep -rn "<<<<<<< " --include="*.php" --include="*.vue" --include="*.js" app/ resources/ 2>/dev/null | head -10

# Check PHP syntax on recently changed files
for file in $(git diff --name-only HEAD~5 -- '*.php' 2>/dev/null); do
  php -l "$file" 2>&1 | grep -v "No syntax errors"
done

# Check migration status
php artisan migrate:status 2>&1 | grep -i "pending\|error" | head -5

# Check routes compile
php artisan route:list --json 2>&1 | head -3
```

If conflict markers are found, they MUST be resolved before any other work.
If pending migrations exist, report to user — do NOT auto-run.

### 3c. Start dev server (if not running)

```bash
lsof -i :8000 2>/dev/null | head -1
lsof -i :5173 2>/dev/null | head -1
```

If not running: `./dev.sh` (run in background so bootstrap can continue).

---

## Phase 4: Session Report

Present a clean summary to the user. This is what they see — make it useful, not verbose.

```markdown
## Session Ready

**Date:** [today]
**Branch:** `main` (or current branch)
**Git:** Up to date / Pulled N new commits / X uncommitted changes
**Database:** Seeded
**Dev server:** Running on :8000/:5173

### Recent Work
- [last 3-5 commits summarised]

### Outstanding Items
- [from CSJTODO.md or vault TODO, if any]

### Issues Found
- [conflict markers / broken imports / pending migrations / stale worktrees — or "None"]

### Rules Loaded
[number] feedback rules loaded from [number] memory files.
Key reminders for this session:
- Browser testing is mandatory — click, fill, submit, verify
- Design system compliance — all colors from fynlaDesignGuide.md palette
- Scope discipline — only change what's asked for
- Honesty — never claim "done" without evidence

**Ready. What would you like to work on?**
```

---

## What NOT to Do

- Do NOT make code changes during session start — this is diagnostic and context-loading only
- Do NOT auto-delete branches with uncommitted work
- Do NOT run `migrate:fresh` or `migrate:refresh`
- Do NOT skip the database seed
- Do NOT skip reading the feedback files — they are the most important part of this skill
- Do NOT summarise the feedback rules as "follow best practices" — they are specific, concrete rules with specific reasons
