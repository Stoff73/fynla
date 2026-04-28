---
name: session-start
description: Bootstrap a new Fynla development session. Syncs git, seeds the database, starts the dev server, surfaces recent work, and prints a lookup map so the instance knows where to read on-demand (memory, vault, design guide). Does NOT bulk-load reference files — CLAUDE.md and MEMORY.md are already auto-loaded by the harness, and individual feedback/vault/design files should be read only when relevant. Use at the start of EVERY conversation, or when the user says "start session", "get ready", "set up", "begin", "new session". Also use mid-session if you notice you're missing project context.
---

# Session Start — Lean Bootstrap

**You are an expert Laravel 10, PHP 8.2, Vue.js 3, and MySQL 8 developer.** Senior full-stack engineer level — Eloquent, Sanctum, Pest, Vuex, Vue Router, Tailwind, Vite, Capacitor iOS, UK financial regulations.

## What's already loaded — do NOT re-read

The harness has already injected these into context for you. Do not Read them again.

- **`CLAUDE.md`** — project rules, deployment, design constraints, all 14 numbered rules. Already in your context.
- **`MEMORY.md`** — index of every feedback / project / reference / critical memory file with a one-line hook for each. Already in your context.

Use the MEMORY.md index to decide which individual memory files to Read on-demand when the topic comes up. Don't read them all proactively.

## Phase 1: Operational checks

Run these in parallel where possible. Stop and report to the user if anything is wrong — do not auto-resolve.

### 1a. Git state

```bash
git status
git rev-parse --abbrev-ref HEAD
git fetch origin
git rev-list --left-right --count HEAD...@{u} 2>/dev/null || git rev-list --left-right --count HEAD...origin/main
git log --oneline -10
```

Output of `rev-list` is `LOCAL_AHEAD  REMOTE_AHEAD`:
- `0  0` → up to date
- `0  N` → behind. If working tree is clean → `git pull`. If dirty → ask user before stashing.
- `N  0` → fine, local work not pushed
- `N  M` → diverged → report, do not auto-resolve

If there are uncommitted changes, **report them** before doing anything else.

### 1b. Worktree cleanup

```bash
git worktree list
```

For any `.claude/worktrees/agent-*/`: if clean → `git worktree remove <path> --force`. If dirty → report, do NOT delete.

### 1c. Database seed (NON-NEGOTIABLE)

```bash
php artisan db:seed
```

Every session, no exceptions. If table-missing → `php artisan migrate && php artisan db:seed`. Duplicate-key errors are safe (seeders use `updateOrCreate`).

### 1d. Code health checks

```bash
grep -rn "<<<<<<< " --include="*.php" --include="*.vue" --include="*.js" app/ resources/ 2>/dev/null | head -10
php artisan migrate:status 2>&1 | grep -iE "pending|error" | head -5
```

Conflict markers MUST be resolved before any other work. Pending migrations → report, do NOT auto-run.

### 1e. Dev server

```bash
lsof -i :8000 2>/dev/null | head -1
lsof -i :5173 2>/dev/null | head -1
```

If not running → `./dev.sh` in the background.

## Phase 2: Current-state context (small reads only)

### 2a. Handover

```bash
cat CSJTODO.md 2>/dev/null | head -100
ls -t /Users/CSJ/Desktop/fynlaBrain/$(date +%B)/$(date +%B)*Updates/CSJTODO.md 2>/dev/null | head -1
```

If a vault CSJTODO exists and is newer than the repo one, prefer it.

### 2b. Most recent vault session folder (LIST, do not read contents)

```bash
ls -d /Users/CSJ/Desktop/fynlaBrain/$(date +%B)/$(date +%B)*Updates 2>/dev/null | sort -V | tail -1
```

Surface the folder name in the report. Read individual files inside it ONLY when the user's request relates to that work.

## Phase 3: Lookup map (no reads — just know where to look)

This is the most important part. Most "lazy" questions happen because the instance forgets where the answer lives. Keep this map in mind for the rest of the session.

| When you need... | Look here (read on-demand, not now) |
|---|---|
| A specific feedback rule's full text | `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/<file>.md` (filename in MEMORY.md index) |
| Design system / colours / typography / components | `/Users/CSJ/Desktop/fynlaBrain/Design/fynlaDesignGuide.md` (v1.3.0) |
| Module architecture (Investment, Estate, Protection, etc.) | `/Users/CSJ/Desktop/fynlaBrain/v083/09-MODULES.md` + module-specific doc per CLAUDE.md table |
| Auth / security patterns | `/Users/CSJ/Desktop/fynlaBrain/v083/03-AUTH-SECURITY.md` |
| Database / schema | `/Users/CSJ/Desktop/fynlaBrain/v083/02-DATABASE.md` |
| Frontend conventions | `/Users/CSJ/Desktop/fynlaBrain/v083/05-FRONTEND.md` + `resources/js/CLAUDE.md` |
| Backend conventions | `/Users/CSJ/Desktop/fynlaBrain/v083/04-BACKEND.md` + `app/Services/CLAUDE.md` + `app/Http/CLAUDE.md` |
| Tax / financial rules | `/Users/CSJ/Desktop/fynlaBrain/v083/08-FINANCIAL-CALCS.md` + `app/Services/Tax/TaxConfigService.php` |
| Deployment | `/Users/CSJ/Desktop/fynlaBrain/v083/11-CONFIG-DEPLOY.md` + CLAUDE.md "Deployment" section |
| What was deployed / fixed recently | `/Users/CSJ/Desktop/fynlaBrain/$(date +%B)/$(date +%B)*Updates/` (most recent folder from Phase 2b) |
| Tests for a module | `tests/Unit/Services/<Module>/`, `tests/Feature/<Module>/` + `tests/CLAUDE.md` |
| Existing code for "is there already a service for X?" | `grep -r "X" app/Services/` BEFORE writing new code |
| Mobile / iOS / Capacitor patterns | memory file `mobile_capacitor_patterns.md` (already indexed in MEMORY.md) |

**Hard rule**: before asking the user a question, check the relevant location above first. "I don't see it in CLAUDE.md" is not a valid excuse if the answer is in the vault or in a memory file the index points to.

## Phase 4: Session report

Present this concise summary to CSJ. No filler.

```markdown
## Session Ready — [date]

**Branch:** `<branch>` · **Git:** <up to date | pulled N | ahead N | diverged>
**DB seeded** · **Dev server:** <running on :8000 / :5173 | started>

**Recent commits**
- <last 5 oneline>

**Outstanding (CSJTODO)**
- <items, or "none">

**Latest vault session folder:** `<path>` (not read — will consult on-demand)

**Issues**
- <conflict markers / pending migrations / dirty worktrees / nothing>

**Reminders this session**
- CLAUDE.md and MEMORY.md are loaded — consult before asking
- Read individual memory / vault / design files on-demand using the lookup map
- Browser testing = click, fill, submit, verify result in Playwright
- Design system: fynlaDesignGuide.md v1.3.0 (read before any UI change)
- Scope discipline · Honesty · No raw `vite build` · No `migrate:fresh`

**Ready. What would you like to work on?**
```

## What NOT to do

- Do NOT Read `CLAUDE.md` or `MEMORY.md` — already in context
- Do NOT bulk-Read every memory file — use the index, read individually when relevant
- Do NOT Read `fynlaDesignGuide.md` until UI work starts
- Do NOT Read every recent vault session folder — list the latest, read on-demand
- Do NOT make code changes during session start — this is diagnostic only
- Do NOT auto-delete branches or worktrees with uncommitted work
- Do NOT run `migrate:fresh` or `migrate:refresh`
- Do NOT skip `db:seed`
