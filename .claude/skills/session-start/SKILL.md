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

If the handover names a specific user-visible decision, surface it verbatim in the report. If the handover or current context records CSJ's decision or a direction explicitly agreed by CSJ, proceed with that decision after Phase 4. If the choice remains genuinely unresolved and has materially different consequences, ask CSJ for new direction while continuing any safe investigative work that does not depend on the choice. Do not ask whether to continue work that is already covered by standing authorisation.

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

**Decision flagged in handover** (if any)
- <If the handover or current context records CSJ's decision or a direction explicitly agreed by CSJ, name it and proceed. If the decision is genuinely unresolved with materially different consequences, state that CSJ direction is required while unblocked investigation continues.>

**Outstanding (CSJTODO)**
- <items, or "none">

**Latest vault session folder:** `<path>` (read on-demand via lookup map)

**Issues**
- <conflict markers / pending migrations / dirty worktrees / nothing>

**Reminders this session**
- CLAUDE.md and MEMORY.md are loaded — consult before asking
- Handover above is the authoritative pickup doc — auto-continue approved or explicitly directed items; investigate safely and ask before unapproved implementation
- Read individual memory / vault / design files on-demand using the lookup map
- Browser testing = click, fill, submit, verify result in Playwright
- Design system: fynlaDesignGuide.md v1.3.0 (read before any UI change)
- Scope discipline · Honesty · No raw `vite build` · No `migrate:fresh`

**Phase 5:** `<Auto-continuing authorised work now | No recorded implementation authorisation — continuing safe investigation and requesting direction before task-specific changes>`
```

## Standing Authorisation After CSJ Agreement

When the handover, an approved spec, an implementation plan, or an agreed fix process records that CSJ explicitly approved the work or directed its implementation, treat that agreement as standing authorisation across session boundaries. Proceed autonomously after the Phase 4 report and do not ask CSJ to approve individual files, edits, commands, or routine implementation decisions again.

Standing authorisation includes:

- Creating, editing, and renaming in-scope files
- Deleting only named or in-scope tracked files recoverable from version control
- Running commands, tests, linters, formatters, builds, seeders, and local migrations
- Starting, stopping, or restarting local development processes
- Installing or updating dependencies required by the agreed work
- Making proportionate corrections discovered during implementation when they preserve the agreed outcome and scope

Ask CSJ for new direction only when an action would:

- Materially expand or change the agreed scope or outcome
- Require an unresolved product or technical decision with materially different consequences
- Cause destructive or irreversible loss of application data, user data, environment state, untracked files, or uncommitted work
- Access, read, reveal, use, expose, or transmit credentials or other secrets unless that credential action was explicitly included in CSJ's agreement
- Affect production or a third-party system
- Commit, push, or deploy unless that action was explicitly included in the agreement

Runtime security controls remain authoritative. If the platform itself requires approval or elevation for an already-authorised action, invoke its approval mechanism directly with the narrowest permission required; do not add a separate conversational permission round first.

## Phase 5: Auto-continue (NON-NEGOTIABLE)

After printing the Phase 4 report, check the handover and current context for the standing-authorisation trigger above. When either records CSJ's explicit approval or implementation direction, **immediately** begin executing the authorised "Pick up from here" / "Next session should" items without another permission round. If that evidence is absent from both, do not treat `start session` alone as approval of proposed implementation: continue safe investigation and diagnostics, then ask CSJ for direction before task-specific file changes or other implementation actions.

### Behaviour matrix

| Handover state | Action |
|---|---|
| Concrete next action ("Run X, fix Y") | If the handover or current context records CSJ's approval or implementation direction, start immediately. Otherwise inspect safely and ask before task-specific changes. |
| Multi-step plan ("Phases 4–7 of plan.md") | Open the plan and find the next unchecked task. Execute it immediately only when the handover or current context records the standing-authorisation trigger; otherwise investigate and ask before implementation. |
| WIP commit present (`wip: context-handover snapshot`) | Review it via `git show HEAD --stat`. Continue its implementation only when the handover or current context records approval or implementation direction; otherwise limit work to safe inspection and ask before changes. |
| Decision flagged ("Path A or B?") | If the handover or current context records CSJ's decision or a direction explicitly agreed by CSJ, proceed with it. If the choice is genuinely unresolved and has materially different consequences, ask CSJ for new direction while continuing any unblocked work. |
| No clear next action | Read more code or run a safe diagnostic to gather context. Start the next implementation action only when the handover or current context records standing authorisation; otherwise propose it concisely and ask CSJ for direction before task-specific changes. |

### Hard rules

- **No "want me to continue?" for authorised work** — when the handover or current context records the standing-authorisation trigger, auto-continue is the contract.
- **No "let me know when you're ready" for authorised work** — `start session` begins bootstrap, but only recorded approval or implementation direction authorises proposed implementation.
- **No re-asking decisions the previous session already answered.** If "The thread" section shows a decision was made, treat it as final.
- **If you hit a blocker outside standing authorisation** (for example an unapproved credential action, destructive loss, production/third-party effect, publication action, or materially consequential unresolved decision), ask CSJ concisely and proceed with whatever investigative work is unblocked while waiting.
- **Do NOT re-run tests or seed the DB again** — Phase 1c already seeded; running tests is part of the actual work, not bootstrapping.
- **Loop until correct (CLAUDE.md Rule #15)** — if the next action is "make BS-NN green", you loop until green per the plan, no early exit.

## What NOT to do

- Do NOT Read `CLAUDE.md` or `MEMORY.md` — already in context
- Do NOT bulk-Read every memory file — use the index, read individually when relevant
- Do NOT Read `fynlaDesignGuide.md` until UI work starts
- Do NOT skip Phase 2a — the latest handover MUST be read in full every session
- Do NOT bulk-Read every file in the latest vault session folder — only the handover is mandatory; specs / plans / audits / screenshots are read on-demand via the Phase 3 lookup map
- Do NOT make task-specific source changes during Phases 1–4. Prescribed Git synchronization may update tracked files; Phase 5 implementation follows the standing-authorisation rules above
- Do NOT auto-delete branches or worktrees with uncommitted work (the handover often flags worktrees that must stay alive)
- Do NOT stop after Phase 4 for work the handover or current context records as approved — auto-continue per Phase 5. If approval or implementation direction is not recorded in either, continue safe investigation and ask before task-specific implementation
- Do NOT re-ask decisions the previous session already answered. If the handover's "The thread" shows a decision was made, treat it as final
- If a choice is genuinely unresolved and has materially different consequences, ask CSJ for new direction while continuing any safe work that does not depend on the choice
- Do NOT run `migrate:fresh` or `migrate:refresh`
- Do NOT skip `db:seed`
