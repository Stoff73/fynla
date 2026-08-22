---
type: report
date: 2026-08-21
branch: dev
covers: root-folder cleanup — what shipped, what is still open, and the commit hazard created by a concurrent testing agent
status: follow-up required once the persona-testing run finishes
---

# Report — root cleanup: done, deferred, and one commit hazard

The repo root was reorganised on 2026-08-21. The work is complete and verified,
but it is **sitting uncommitted in a working tree that a persona-testing agent is
also writing to**. Nothing here should be actioned until that run finishes.

Three things need a decision afterwards. One of them is a real bug found by
accident.

---

## 0. The commit hazard — read this first

The working tree now holds **two unrelated bodies of work at once**.

**Cleanup-owned — 25 modified files, ~244 renames, 8 deletions:**

```
.gitignore
.obsidian/graph.json
.claude/skills/tech-debt-session/SKILL.md
.agents/skills/tech-debt-session/SKILL.md
plugins/fynla-dev-skills/skills/tech-debt-session/SKILL.md
plugins/fynla-dev-skills/skills/ship/SKILL.md
April/April24Updates/spec/00-canonical.md
codex/plans/canonical/00-canonical.md
August/August17Updates/regen-docs-index.sh
CSJTODO.md
docs/INDEX.md
database/seeders/DatabaseSeeder.php
deploy/awin/README.md
deploy/csjones-fynla/BOOTSTRAP.md
deploy/csjones-fynla/.env.production.example
fyn-memory/README.md
fyn-memory/semantic/README.md
workforce/core/constitution/{00-precedence,01-mission,04-voice}.md
workforce/core/registry/capabilities.md
workforce/ops/interviews/{S03-mission,S04-values,S05-voice,S06-perimeter}.md
```

Plus every `R` rename under `docs/archive/`, `docs/reference/`, `docs/assets/`,
`docs/dashboards/`, and these deletions: `create_trial.php`, `personas/*.json`
(6), and the stray `Users/Chris/Desktop/fpsApp/fynla/2025_12_19_*.php` stub.

**Testing-agent-owned — 35 files, DO NOT include in the cleanup commit:**
everything under `app/`, everything under `resources/js/`, plus `routes/api.php`
and `fyn-memory/procedural/tool_schema/savings/create_pension.md`. These are the
W-0006–W-0018 defect fixes (ownership share, mortgage term, joint accounts,
investment holdings, expenditure gate) and they grew from 19 to 35 files during
the cleanup session — the run is live.

**Action:** commit the cleanup as its own commit using an explicit pathspec, not
`git add -A`. Anything under `app/`, `resources/js/`, `routes/`, or
`fyn-memory/procedural/` belongs to the testing agent.

---

## 1. Two directories still need deleting

Both are regenerable, both are already gitignored, neither could be removed
during the session — the sandbox classifier blocks `rm` on directories.

| Path | Size | What it is |
|---|---|---|
| `.playwright-mcp/` | 19 MB, 1,625 entries | Playwright MCP screenshot/log spool |
| `test-results/` | 4 KB | Playwright `.last-run.json` runtime artifact |

`/test-results/` was added to `.gitignore` during the cleanup; `.playwright-mcp/`
was already there.

**Action:** `rm -rf .playwright-mcp test-results` — but only once the testing
agent has stopped, since Playwright writes into both while a run is in flight.

---

## 2. BUG — `ImageRendererService` has been inert, config key does not exist

Found incidentally while checking whether root `templates/` was safe to move.

```
php artisan tinker --execute="var_dump(config('services.templates'));"
→ NULL
```

`app/Services/ImageRendererService.php:22-23` reads
`config('services.templates.layouts')` and `config('services.templates.dir')`,
then passes the second straight to `scripts/render_template.py` as
`--templates-dir`. **Neither key is defined anywhere in `config/services.php`**,
and no `.env` entry supplies them. The service therefore invokes the renderer
with a null template directory on every call.

The image templates themselves are real and intact — 20 files (logo variants,
`story_card/`, `square_stat_card/`, `youtube_thumbnail/`, `previews/`), now at
`docs/assets/templates/`.

This is pre-existing and unrelated to the move — the config key was already
missing before anything was relocated. It was **not** fixed, because scope was a
folder cleanup.

**Action:** decide whether the social-image renderer is meant to be live. If yes,
add a `templates` block to `config/services.php` pointing `dir` at
`base_path('docs/assets/templates')` and `layouts` at whatever the layouts source
should be, then exercise it end-to-end. If the feature is abandoned, delete
`ImageRendererService` and `scripts/render_template.py` with it.

---

## 3. Decision needed — `screenshots/` and `test-screenshots/` at the root

These two were **deliberately left in place**. `screenshots/` is 22 MB and
`test-screenshots/` is 264 KB; both are gitignored, so they cost the repo nothing
and cost only local tidiness.

They were left because `workforce/ops/gates/GATE-0003-screenshot-filing-convention.md`
ratifies `screenshots/YYYY-MM/` as *the* filing convention — the answer adopted on
2026-08-17 after 173 loose PNGs made the root unreadable. Moving them would
silently override a ratified gate, which is CSJ's call rather than an agent's.

Worth noting while the gate is open: GATE-0003's stated action was *"add a
screenshot-filing convention to root CLAUDE.md"*, and **root `CLAUDE.md` contains
no screenshot rule** — `grep -i screenshot CLAUDE.md` returns nothing. The
convention exists only in the gate document, so no agent reading CLAUDE.md will
follow it.

**Action:** either land the convention in CLAUDE.md as GATE-0003 intended, or
relocate both directories under `docs/screenshots/` and amend the gate. Doing
neither leaves a convention nobody reads.

---

## What was already verified

Run after the cleanup, all green:

- `php artisan --version`, `config:clear`, `route:list --path=preview` — boots, routes resolve
- `./vendor/bin/pest --testsuite=Architecture` — **149 passed**, 4,296 assertions
- `./vendor/bin/pest` on InsightTemplate, Apple StoreKit bridge, bridge-health — **72 passed**
- Zero relative markdown links inside any moved file
- Final path re-scan: no surviving broken reference on any live surface

Historical references were deliberately **not** rewritten — roughly 370 hits in
dated `May/`–`August/` folders, `handover/`, `codex/plans/source-corpus/` and
`docs/superpowers/plans/`. Those are session records; editing them would
falsify what was true on the day.

## Minor, no action needed

`.obsidian/workspace.json` still lists `tech-debt-report.md` in its recent-files
array. That is Obsidian runtime state and self-heals on next open.

## Adjacent, still open from CSJTODO

`.worktrees/` holds 14 clones of *other* repositories (FynlaMCP ×9,
fynla-agents, fynla-control, fynlaBrain) — roughly 250 MB, all clean. Untouched
by this cleanup and still awaiting a relocate-or-delete decision.
