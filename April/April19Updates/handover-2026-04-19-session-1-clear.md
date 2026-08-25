---
type: handover
mode: context-clear
date: 2026-04-19
session: 1
branch: feature/csj/excalidraw
---

# Context Clear Handover — 2026-04-19, Session 1

## Immediate state

Just finished building the `excalidraw` skill + 3 seed diagrams and pushed to `origin/feature/csj/excalidraw`. Working tree clean. Branch is 3 commits ahead of `dev`.

## The thread

This session covered four related blocks of work in order:

1. **fynlaBrain vault audit** — traced 86 orphaned files to 0 orphaned files, path-qualified 535+ ambiguous bare wikilinks, generated a duplicates report. Fixed broken wikilinks (Architecture path shortnames, `deployCms` case typo, `classificationResults.txt`, the `[[student]]` persona stub, 28 narrative-session `[[...]]` wrappers in March Index). Home.md now links every subfolder MOC.

2. **Tech debt branch review + merge** — audited `feature/csj/tech-debt-session-63` against real data (ran all 8 browser-verification flows × 2 users: `john@example.com` seeded + `chris@fynla.org` production-matching). Every decimal:2 cast confirmed penny-exact (`taxable_estate="121250.00"`, Trust `initial_value="125000.33"`, Net Worth £803,500 − £205,250 = £598,250). PR #220 merged to `dev` with admin override, branch auto-deleted. `csjones.co/fynla` intentionally left running `onboardingFyn` build.

3. **`.claude/skills/` refactor for Fynla UK** — session-start, session-end, vault-sync, deploy-notes, prd-writer. Removed every `fynlaInternational` / `FynlaInter` path reference; rewrote prd-writer for Fynla UK (single-country, no core/pack separation). Fixed session-start's port-stacking bug (was hunting for `:8001 / :5175 / :8002 / ...` alternates every session — now reuses `:8000 / :5174` or asks user if held by someone else). Killed 4 orphan dev-server processes left over from the bug.

4. **Excalidraw skill + seed diagrams** — new `.claude/skills/excalidraw/` with SKILL.md, Python composer (`scripts/compose.py`), 3 reference docs, 5 archetype templates. Generated `architecture.excalidraw`, `modules.excalidraw`, `fyn-ai-pipeline.excalidraw` to both `docs/diagrams/` (repo) and `fynlaBrain/Diagrams/` (vault). Vault's `Diagrams Index.md` created and linked from `Home.md`. Fynla palette (raspberry / horizon / spring / violet / savannah) baked into the composer via semantic `kind=` args.

## Files touched (committed)

3 commits on `feature/csj/excalidraw`, all pushed:

- `46fcceb` — `chore(skills): align .claude/skills with Fynla UK + fix session-start port-stacking` (22 files, +2917 / −1737)
- `8adccbf` — `feat(skills): add excalidraw skill for Fynla architecture diagrams` (10 files, +3768)
- `909fa13` — `docs(diagrams): add 3 seed Excalidraw canvases` (3 files, +4233)

Plus vault-only writes (not git-tracked):
- `fynlaBrain/Home.md` — new MOC shortcuts + Diagrams section
- `fynlaBrain/Diagrams/Diagrams Index.md` — new
- `fynlaBrain/Diagrams/*.excalidraw` — 3 mirrors
- `fynlaBrain/March/March Index.md`, `April/April Index.md`, etc. — orphan-fix backfills
- `fynlaBrain/Reports/vault-duplicates.md` — new duplicates report

## What the next Claude needs to know

- **Do NOT run `./deploy/csjones-fynla/build.sh` from `dev`.** csjones.co/fynla is still serving code built from the `onboardingFyn` branch (Fyn AI testing in progress). Rebuilding `dev` would overwrite that. Wait until onboardingFyn is merged to dev, then rebuild + deploy once.

- **session-start now reuses dev servers.** If `:8000` / `:5174` are held by a process whose cwd is this repo, session-start will reuse rather than restart. If held by a different process, it will stop and ask. No more port stacking.

- **The `excalidraw` skill is live.** Next time a user asks to diagram/flow/map/visualise something, the skill fires automatically. It writes to `docs/diagrams/` + `fynlaBrain/Diagrams/` and updates the Diagrams Index. The Python composer is the way — don't hand-write JSON.

- **PR #220 is merged to `dev`, not `main`.** Tech-debt changes (70 decimal:2 casts, component renames, exception factories, strict_types, npm audit fix) live in dev. They'll reach production when dev is merged to main via the standard release PR.

- **The `feature/csj/excalidraw` branch is NOT yet PR'd.** It sits ahead of `dev` with 3 commits. No rush — user hasn't requested the PR yet.

## Pick up from here

After `/clear`, the next obvious moves (any or none, depending on user direction):

1. **Open a PR** for `feature/csj/excalidraw` → `dev` (branch is clean + pushed; title + body ready to go)
2. **Browse the seed diagrams** — user wanted to view them in local Excalidraw at `/Users/CSJ/Desktop/excalidraw-master` (`yarn start`) or in Obsidian (requires the Excalidraw plugin in the fynlaBrain vault)
3. **Add more diagrams** — auth flow, deploy pipeline, onboarding journey, preview-mode isolation, subscription state machine (per original option-A list)
4. **Continue the onboardingFyn → dev merge prep** — Fyn AI testing still outstanding per session 58 handover

If the user says "continue implementation" with no further context, default to option 1 (PR the current branch) and check in.
