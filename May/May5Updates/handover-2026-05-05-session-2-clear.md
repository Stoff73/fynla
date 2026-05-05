---
type: handover
mode: context-clear
date: 2026-05-05
session: 2
branch: onboardingFyn
previous_session: 2026-05-05 session 1 (end-of-day, deploy note for CMS-into-/insights)
---

# Context Clear Handover — 2026-05-05, Session 2

## Immediate state

Just finished merging both open PRs into `dev` and confirming `origin/dev` is **45 commits ahead of origin/main**, ready for the user to open the dev → main release PR.

Working tree is clean on branch `onboardingFyn` (which just received a merge from dev locally — `2e25db8` — and was then squash-merged back into dev as `dc335b3`). Local `onboardingFyn` won't receive further work.

## The thread

This session started as a `/session-start` resume from session 1's CMS-into-/insights deploy note. CSJ then asked for three small CMS Upload UX changes which surfaced a chain of fixes:

1. **CMS Upload polish (PR #241).** Renamed sidebar tab `Documents` → `CMS Upload`. Wrapped both `/admin/documents` and `/admin/documents/:id/edit` in `<AppLayout>` (they were rendering chrome-less). Shrunk the DropZone from full-bleed to `w-1/5 aspect-square`. Added permanent **CLAUDE.md Rule #14** ("All routed Vue views must wrap in AppLayout/PublicLayout unless explicitly stated otherwise").
2. **Editor dead-end fix.** Added `Back to CMS Upload` link via the canonical `.detail-inline-back` class. Save / Publish / Unpublish now redirect to `/admin/documents` instead of staying on the editor.
3. **DropZone click-doesn't-fire saga.** First attempt: `<label>`-wrapped `sr-only` input. CSJ reported still nothing happens. After investigation (label-input association correct, DOM correct, every Playwright click queued a file_chooser modal), root cause was almost certainly that CSJ was clicking in the Playwright-controlled Chromium window, where Playwright catches `filechooser` events before the OS dialog can render. Final fix: **render the `<input type="file">` visibly** with Tailwind's `file:*` pseudo-element classes (raspberry-styled native button). Zero JS shim. CSJ has not yet confirmed whether this works in his real browser — that's the one outstanding test-plan item on the merged PR.
4. **PR housekeeping.** Updated PR #241 title/body, then squash-merged with `--admin` (CSJ explicitly authorised the override after the harness blocked it once). Then merged `origin/dev` into `onboardingFyn` (Option A — single merge commit, 6 conflicts all resolved by taking dev's version). Then squash-merged PR #214 (onboardingFyn → dev).

## Files touched (this session — all committed and merged)

**Squashed into `20d0b00` on dev (was PR #241):**
- `CLAUDE.md` (Rule #14 added)
- `resources/js/views/Admin/AdminPanel.vue` (sidebar label)
- `resources/js/views/Admin/Documents/DocumentListPage.vue` (AppLayout wrap, H1 rename)
- `resources/js/views/Admin/Documents/DocumentEditor.vue` (AppLayout wrap, Back link, Save redirect)
- `resources/js/components/Admin/Documents/DropZone.vue` (3 iterations, final = visible styled file input)

**Merge artefact `2e25db8` on local onboardingFyn:** brought in 453 files / +34k / -4.2k from dev.

**Squashed into `dc335b3` on dev (was PR #214):** the entire 41-commit Fyn-driven onboarding state machine work plus the dev backfill from `2e25db8`.

## Deploy status

- **csjones (dev):** all CMS Upload changes deployed and browser-verified during the session. Server build dir at `~/www/csjones.co/fynla-app/public/build/` rotated three times; current bundle has the visible-styled-file-input DropZone.
- **fynla.org (production):** NOT deployed. CSJ explicitly said his next step is to open a dev → main release PR himself.

## Memory written this session

- `feedback_pages_must_use_app_layout.md` — top-law-tier rule, indexed in MEMORY.md, also promoted to CLAUDE.md Rule #14. Issued after the chrome-less CMS Upload page surfaced.

## What the next Claude needs to know

**Primary job (per CSJ):** verify local codebase vs `origin/dev` are in sync, report any issues, omissions, or stray commits. Concretely:

1. `git fetch origin`
2. `git checkout dev && git pull --ff-only` — should be at `dc335b3` (Fyn-driven onboarding squash) plus any commits the user may have pushed in the meantime
3. Compare local `dev` state to `origin/dev`
4. Check `feature/csj/cms-insights-deploy-note` (still exists per `--delete-branch=false`) — should be fully contained in `origin/dev` post-squash
5. Check `onboardingFyn` (still exists per `--delete-branch=false`) — should be fully contained in `origin/dev` post-squash
6. Report any: uncommitted local changes, branches with commits not in dev, files modified locally vs origin

**Other live state:**

- `origin/dev` is **45 commits ahead of origin/main**. CSJ is opening the dev → main release PR next.
- Per PR #241's test plan, the **one outstanding browser test** is CSJ confirming the visible raspberry "Choose File" button on `https://csjones.co/fynla/admin/documents` opens the macOS file picker in his real (non-Playwright) browser. The first time he tested in Playwright, the file_chooser was being intercepted silently — that confused diagnosis for several iterations.
- Two duplicate "Rich Sample Title" articles were created on csjones during testing (IDs 2 and 4). CSJ may want to delete article 4 (the draft) via the UI when convenient.
- csjones server still has 61+ uncommitted server-side WIP files in `app/` (eval / tax-strategy work, flagged in session 75). Future deploys must rsync without `--delete` and ASK before bulk-syncing.

**Production deploy carry-overs (for the dev → main release PR — CSJ is opening this):**

- 3 migrations will run on prod (vs 1 in the last release)
- Use `./deploy/fynla-org/build.sh` (NOT the csjones build script — different VITE_BASE_PATH)
- Verify `app/Http/Controllers/Api/AgentInternalController.php` and `app/Http/Middleware/AgentTokenAuth.php` exist on the fynla.org server (the same gap that bit csjones may be present on prod)
- The `SanitizeInput` middleware change goes too — required for doc article body sanitisation
- Onboarding state machine is the big new surface area — worth extra smoke time on prod

## Pick up from here

Run `/session-start` to bootstrap, then immediately:

```bash
git fetch origin --prune
git status
git rev-list --left-right --count HEAD...@{u}
git branch -vv
```

Then for each feature branch (`feature/csj/cms-insights-deploy-note`, `onboardingFyn`):
```bash
git log --left-right --oneline origin/dev...origin/<branch>
```

Anything appearing on the right side that ISN'T already in `origin/dev` (post-squash) is a potential omission worth flagging. The squash-merges should mean `origin/dev` contains the *content* of all squash-merged commits even if hashes differ — so check by file content where useful, not just commit hashes.

Report the findings to CSJ as a punch list. Don't auto-fix anything.
