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

## Phase 2: Current-state context

### 2a. Latest handover — READ IN FULL (NON-NEGOTIABLE)

The session-end skill writes `handover-YYYY-MM-DD-session-N.md` into the current month's `<MONTH>NUpdates/` folder in BOTH the repo and the vault, expressly so session-start picks it up. **You MUST read the most recent one in full before reporting back to the user.** It contains "Pick up from here", "What's NOT done", "What did NOT happen", live worktree state, blocking issues, and rollback plans — none of which are in CSJTODO.md or MEMORY.md.

Find it (repo first, vault as fallback — they should mirror, but the repo is canonical):

```bash
# Repo (canonical)
ls -t /Users/CSJ/Desktop/fynla/$(date +%B)/$(date +%B)*Updates/handover-*.md 2>/dev/null | head -1
# Vault (fallback / cross-check)
ls -t /Users/CSJ/Desktop/fynlaBrain/$(date +%B)/$(date +%B)*Updates/handover-*.md 2>/dev/null | head -1
```

If neither location has a handover for the current month, look back one month (some month rollovers leave the latest handover in the previous month folder). If still nothing, surface that to the user.

Then **Read the file in full** (not `head`, not `cat | head -50` — full file). Extract the following sections into your working memory and surface them in the Phase 4 report verbatim where indicated:

- **Immediate state** (one-line summary at the top of the handover) — surface verbatim
- **What's NOT done / What did NOT happen / What's left** — surface as a bulleted list
- **Pick up from here / Next session should** — surface verbatim
- **Worktrees / live artefacts** (any `/tmp/*` paths still alive, branch tips, pushed/unpushed state) — surface as a sublist
- **Blockers / known issues** — surface verbatim if non-empty

If the handover names a specific user-visible decision the next session is supposed to make (e.g. "CSJ to choose Path A / B / C"), surface that decision verbatim in the report — but **do NOT stop and wait**. After Phase 4, you will auto-continue with the "Pick up from here" actions (Phase 5). CSJ has the chat history and the handover open and will redirect if the auto-resume picks a wrong path. **Do not ask "want me to continue?" — just continue.**

### 2b. CSJTODO (supplementary — NOT a substitute for the handover)

```bash
cat CSJTODO.md 2>/dev/null | head -100
ls -t /Users/CSJ/Desktop/fynlaBrain/$(date +%B)/$(date +%B)*Updates/CSJTODO.md 2>/dev/null | head -1
```

If a vault CSJTODO exists and is newer than the repo one, prefer it. CSJTODO summarises the standing backlog; the handover is the authoritative pickup doc for the most recent session's work.

### 2c. Most recent vault session folder (list only)

```bash
ls -d /Users/CSJ/Desktop/fynlaBrain/$(date +%B)/$(date +%B)*Updates 2>/dev/null | sort -V | tail -1
```

Surface the folder name in the report. Other files inside (specs, plans, audits, screenshots) are read on-demand using the lookup map in Phase 3.

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

**Last handover:** `<path to the handover file just read>`

> <Immediate state — verbatim one-line summary from the handover>

**What's NOT done (from handover)**
- <bullet from handover's "What's NOT done" / "What did NOT happen">

**Live artefacts (from handover)**
- <worktree paths, pushed merge commits, deployed servers — verbatim>

**Pick up from here (from handover)**
- <verbatim block>

**Decision flagged in handover** (if any — note that auto-resume will proceed; CSJ to redirect if needed)
- <e.g. "Path A / B / C — CSJ to choose. Auto-resume will continue with the most-recent direction-of-travel: Path B per the WIP commit message.">

**Outstanding (CSJTODO)**
- <items, or "none">

**Latest vault session folder:** `<path>` (read on-demand via lookup map)

**Issues**
- <conflict markers / pending migrations / dirty worktrees / nothing>

**Reminders this session**
- CLAUDE.md and MEMORY.md are loaded — consult before asking
- Handover above is the authoritative pickup doc — auto-continue from "Pick up from here"
- Read individual memory / vault / design files on-demand using the lookup map
- Browser testing = click, fill, submit, verify result in Playwright
- Design system: fynlaDesignGuide.md v1.3.0 (read before any UI change)
- Scope discipline · Honesty · No raw `vite build` · No `migrate:fresh`

**Auto-continuing from handover — Phase 5 starting now.**
```

## Phase 5: Auto-continue (NON-NEGOTIABLE)

After printing the Phase 4 report, **immediately** begin executing the handover's "Pick up from here" / "Next session should" items. **Do not ask permission. Do not check. Just work.** This is CSJ's explicit instruction — the whole point of the context-handover → /clear → session-start chain is that the new instance picks up exactly where the old one left off.

### Behaviour matrix

| Handover state | Action |
|---|---|
| Concrete next action ("Run X, fix Y") | Start doing it immediately |
| Multi-step plan ("Phases 4–7 of plan.md") | Open the plan, find the next unchecked task, start |
| WIP commit present (`wip: context-handover snapshot`) | Review what's in it via `git show HEAD --stat`, then continue the work it represents |
| Decision flagged ("Path A or B?") | Surface the decision in the report, then proceed with the most-recent direction-of-travel (look at the WIP commit, the last few commits, the handover's "The thread" section). CSJ will redirect if wrong. |
| No clear next action | Read more code or run the relevant test to gather context, then propose a next step in one sentence and START it. Don't sit idle asking "what would you like?" |

### Hard rules

- **No "want me to continue?"** — auto-continue is the contract.
- **No "let me know when you're ready"** — CSJ is ready by virtue of having said `start session`.
- **No re-asking decisions the previous session already answered.** If "The thread" section shows a decision was made, treat it as final.
- **If you hit a blocker that genuinely requires CSJ's input** (e.g. credentials, a destructive action, a path the handover explicitly defers), surface it concisely and proceed with whatever investigative work is unblocked while waiting.
- **Do NOT re-run tests or seed the DB again** — Phase 1c already seeded; running tests is part of the actual work, not bootstrapping.
- **Loop until correct (CLAUDE.md Rule #15)** — if the next action is "make BS-NN green", you loop until green per the plan, no early exit.

## What NOT to do

- Do NOT Read `CLAUDE.md` or `MEMORY.md` — already in context
- Do NOT bulk-Read every memory file — use the index, read individually when relevant
- Do NOT Read `fynlaDesignGuide.md` until UI work starts
- Do NOT skip Phase 2a — the latest handover MUST be read in full every session
- Do NOT bulk-Read every file in the latest vault session folder — only the handover is mandatory; specs / plans / audits / screenshots are read on-demand via the Phase 3 lookup map
- Do NOT make code changes during session start — this is diagnostic only
- Do NOT auto-delete branches or worktrees with uncommitted work (the handover often flags worktrees that must stay alive)
- Do NOT stop after Phase 4 to wait for a "what should I work on?" answer — the handover already says, auto-continue per Phase 5
- Do NOT re-ask decisions the previous session already answered. If the handover's "The thread" shows a decision was made, treat it as final
- If a genuinely-undecided choice IS surfaced in the handover, proceed with the most-recent direction-of-travel default rather than blocking — CSJ has the chat and will redirect if wrong
- Do NOT run `migrate:fresh` or `migrate:refresh`
- Do NOT skip `db:seed`
