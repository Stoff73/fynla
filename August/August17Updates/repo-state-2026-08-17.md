---
type: state-of-the-repo
date: 2026-08-17
author: session bootstrap (read-only survey — no code changed, no migrations run)
scope: origin/dev, origin/main, all 100 remote branches, 4 local worktrees, open PRs/issues
---

# Repo state — 17 August 2026

Read-only survey of GitHub and the local worktrees, done before touching any
code. Written because `fix/widow-persona-cleanup` (the branch this session
started on) is **161 commits behind `origin/dev`** and had no picture of what
landed in that gap.

## One-paragraph answer

The codex branches are **not** outstanding work — 28 pull requests (**#672 →
#694**) have already merged into `dev`, and every codex branch except three is
fully contained in it. What is outstanding is the other direction: `origin/main`
(= production, fynla.org) is **719 commits behind `dev`**, and this working
branch is 161 behind. Three programmes landed on `dev` since 24 July: the
**iOS/`m` parity wave** (#674–#689), the **marketing content pipeline**
(#674, #690–#694), and the **PSA/journey-verify tail** (#670–#673). Only **one**
pull request is open, and it is the long-parked #249.

## Branch ledger

| Ref | Tip | Position |
|---|---|---|
| `origin/dev` | `c9e64c2` (2026-08-14 09:57) | the live truth; = csjones.co/fynla |
| `origin/main` | `2e8357b` (2026-07-08) | **719 behind dev**, 28 ahead (doc-only commits that never went via dev) |
| `fix/widow-persona-cleanup` | `bc8ad2d` (2026-08-14 18:48) | 12 ahead of dev · **161 behind** |

100 remote branches exist. Only **9** hold commits not in `dev`:

| Branch | Ahead | Last commit | Verdict |
|---|---|---|---|
| `fix/widow-persona-cleanup` | 12 | 2026-08-14 | this session's branch (docs, workforce tree, guards) |
| `codex/ios-m-testflight-hotfix` | 2 | 2026-08-12 | **real unmerged work** — see below |
| `codex/ios-package7-platform-release` | 2 | 2026-07-24 | **real unmerged work** — see below |
| `codex/repository-context-tooling` | 1 | 2026-07-15 | 131 files of archived context/evidence JSON — decide keep or drop |
| `fix/public-pages-base-path` | 1 | 2026-05-29 | stale |
| `feature/csj/python-agent-sidecar` | 1 | 2026-05-07 | **PR #249, parked** — do not merge, do not auto-delete |
| `rss-feed` | 9 | 2026-04-27 | stale |
| `email-onboarding-video` | 90 | 2026-04-27 | stale, large |
| `brett-dev1` | 1 | 2026-04-15 | stale |

Every other codex branch (`savetax-*`, `freemium-task*`, `ios-package1..7`,
`ios-m-*`, `marketing*`, `google-*`, `psa-*`, `journey-verify-loop`) is fully
merged into `dev` and is safe to delete whenever you want the list shorter.

### The three branches with genuinely unmerged commits

**`codex/ios-m-testflight-hotfix`** (2 commits, 6 files, +71/−10) — landed after
its own PR chain #685–#689 merged:
- `f696226` fix: honor retirement target in pension projections
- `4713faa` fix: date investment contribution summaries

Touches `RetirementProjectionService`, `InvestmentAccountDetail.vue`, and adds
`FinancialDataParity.spec.js` cases. These are behaviour fixes to a shipped
TestFlight build — worth a look before they are forgotten.

**`codex/ios-package7-platform-release`** (2 commits, 3 files, +42/−2):
- `37cd69e` fix(ios): journey verify destinations resolve in the Fyn navigation allowlist
- `d4e8a2b` test(ios): live journey taps submit only once enabled

This is the pair CSJTODO has been carrying since 24 July as "untested — sim
wedged" (`FynEventReducerTests`). Still unmerged, still unverified.

**`codex/repository-context-tooling`** (1 commit, 131 files, +24,527) — bulk
archive of failure diagnostics, smoke results and a June security review. Not
code. Merge or abandon as a filing decision.

## What landed on `dev` in the 161-commit gap

580 files changed, **+51,025 / −2,204** against this branch. By area:

| Area | Files |
|---|---|
| `ios-native/Fynla` | 97 |
| `resources/mobile` | 65 |
| `app/Services` | 61 |
| `ios-native/FynlaTests` | 54 |
| `app/Http` | 51 |
| `tests/Feature` | 47 |
| `resources/js` | 34 |
| `database/migrations` | 23 |
| `app/Models` | 20 |

### Programme 1 — iOS and `/m` parity wave (#674–#689, 9–12 Aug)

Seven planned pull requests plus a five-PR TestFlight hotfix chain:

- #675 iOS and mobile parity foundations
- #676 trusted contextual Fyn conversations and history
- #677/#678 additive overview actions; preview contextual Fyn flow + native contract
- #679 canonical detail parity across iOS and `/m`
- #680 joint Net Worth detail totals aligned across iOS and `/m`
- #681 financial data aligned across iOS and mobile web
- #682 projections aligned across iOS and mobile web
- #683 personalised achievements across mobile clients
- #684 **PR7: parity audit closed**
- #685–#689 TestFlight hotfix chain → **build 6** (live-data parity, PHP 8.2
  dashboard compatibility, dashboard semantic destination decoding, milestone
  assertion contract)

The closure contract is machine-checked. `tests/Architecture/ClientParityLedgerTest.php`
enforces an **M-01–M-34 matrix** with 722 assertions and refuses a `green`
status unless `docs/superpowers/evidence/2026-08-11-pr7-ios-m-parity-closure.md`
contains the exact native CI success marker. Shared rules now stated explicitly:
Laravel rehydrates canonical financial facts; clients send identifiers and
proposed changes, never authoritative balances; recorded history never contains
projected values; semantic destinations are server-authored and
client-allowlisted; unknown or unauthorised resources fail safely.

Acceptance ran in **installed Google Chrome** at 390×844 across 17 shared
destinations (`tests/E2E/mobile/parity-closure.spec.js`).

New backing tables: `web_handoffs`, `isa_contributions`,
`net_worth_forecast_assumptions`, `user_level_crossings`, plus portfolio context
fields. New surfaces include `app/Http/Controllers/WebHandoffController.php`,
`Api/V1/Mobile/WebHandoffController.php`, `app/Enums/WebHandoffDestination.php`,
`CreateContextualConversationRequest`, `TierLimitResponse`.

### Programme 2 — marketing content pipeline (#674, #690–#694, 9–14 Aug)

An entire subsystem that did not exist on this branch: **17 migrations**, 12
artisan commands under `app/Console/Commands/Pipeline/`, 7 admin API
controllers, 6 queue jobs, 6 mailables, and models for articles, campaigns,
posts, clip approvals, Drive watch channels and sync logs.

- #674 automated content pipeline — articles, scripts, video snippets, social scheduling
- #690 article crawl duplicates, Word hyperlinks and lists, version history
- #691 Google **service account** replaces user OAuth
- #692 Google Drive automation made development-ready
- #693 marketing pipeline AI provider and Whisper runtime
- #694 pipeline video rendering on development

Runbooks now live in `docs/pipeline/`: `DEV-COMMISSIONING-HANDOFF.md`,
`GOOGLE-DRIVE-SETUP-RUNBOOK.md`, `MARKETING-AUTOMATION-TEAM-USER-GUIDE.md`,
`STAGE-2..5-SETUP.md`. The commissioning handoff is written as a gated
release-day checklist against the csjones app root with explicit stop
conditions — including "production has the pipeline enabled" as a **stop**.
It is not commissioned yet: the release record placeholders are unfilled.

### Programme 3 — PSA / journey-verify tail (#670–#673, 24 July)

Already described in the 25 July handover; it is in the gap purely because this
branch predates it. Joint-account interest attributed by ownership share on both
allowance grids; every journey data entry runs the verify loop.

## Things this survey found that were not previously written down

1. **`main` is 719 behind `dev`.** Production has none of the parity wave, none
   of the marketing pipeline, and none of the native endpoints. This is the
   mechanical reason the `Fynla-Production` iOS scheme cannot log in — it is a
   release, not a bug.

2. **`main` is also 28 *ahead* of `dev`** with documentation commits
   (`2e8357b`, `e7041e1`, `5899e66`, `2f73542`, `56bd2c0` …) that appear to have
   gone straight to `main`. A `dev → main` release will need those reconciled or
   they will look like reverts.

3. **`August/Aug12Updates/` duplicates `dev` byte-for-byte.** Sixteen files in
   that folder are identical copies (verified by checksum) of `dev`'s
   `docs/superpowers/plans/`, `docs/superpowers/evidence/` and `docs/testing/`.
   Two homes for one document — the same disease Rule 20 names. Decide which
   home wins before rebasing, or the rebase blesses both.

4. **Two documentation conventions are now live.** This branch files session
   docs under `August/AugNNUpdates/`; `dev` files them under
   `docs/superpowers/{plans,specs,evidence}/` and `docs/testing/`. `dev` has no
   month-update folders at all. Nothing reconciles them.

5. **Month-folder naming has drifted.** `August9Updates` (the documented
   convention, `{Month}{D}Updates`) versus `Aug12/13/14/15Updates`. This folder
   follows the recent majority — `Aug17Updates`.

6. **A rebase onto `dev` is low-risk.** Of 166 files changed on this branch, the
   only one `dev` also touched is `.claude/settings.json`. Everything else is
   `.claude/`, `workforce/`, `August/` and docs.

7. **The local database is behind both.** 9 migrations pending here are the July
   iOS/Apple set (`premium_entitlements`, `apple_transactions`,
   `native_device_sessions`, `native_refresh_tokens`, …); `dev` adds 23 more on
   top. Nothing was run — this needs a call.

8. **`node_modules/` was empty at session start** — almost certainly collateral
   from the 14 Aug worktree reclaim. Restored with `npm ci` (lockfile untouched,
   working tree still clean).

## Worktrees

| Worktree | Branch / tip | State |
|---|---|---|
| `fynla` (main dir) | `fix/widow-persona-cleanup` `bc8ad2d` | clean |
| `fynla-ios-package7` | `codex/ios-package7-platform-release` `37cd69e` | clean bar untracked `ios-native/build/` |
| `fynla-marketing-review` | `pr674-ci-green` `dea5f64` | **10 modified + 3 untracked, uncommitted** |
| `fynla-audit2` | detached `e16ea5f` (#615, 7 July) | clean — nothing holding it open |
| `fynla-fixes` | `dev` `9c9d2aa` (= #690, 12 Aug) | untracked `August/`, `docs/mobile/designer-brief.pdf` |

`fynla-marketing-review` still holds uncommitted pipeline work: `PipelineClipApprovalsController`,
`PipelinePostsController`, `SignedClipDownloadController`, `DocumentArticle`,
`PipelineArticle`, `ClipApprovalService`, `routes/web.php`,
`public/pages/index.php`, `ClipApprovalTest`, `ComposePostsGuardTest`, plus new
`PostApprovalTest.php` and `SignedClipDownloadTest.php`. Since #690–#694 have
merged, some of it may now be redundant — needs a diff against `dev` before
either committing or discarding.

## Open on GitHub

- **Pull requests:** 1 — **#249** `[PARKED] feat(agent): salvage Python Agent SDK
  sidecar`. Standing instruction is do not merge, do not auto-delete.
- **Issues:** none open.

## Decisions this survey surfaces

1. Merge, cherry-pick or abandon `codex/ios-m-testflight-hotfix` (2 retirement/
   investment fixes sitting outside `dev`).
2. Same for `codex/ios-package7-platform-release` — and the still-unrun
   `FynEventReducerTests` it depends on.
3. Run the 32 pending migrations locally (9 July iOS + 23 from `dev`), or stay
   behind deliberately.
4. Rebase `fix/widow-persona-cleanup` onto `dev` (only `.claude/settings.json`
   conflicts) — and decide the `August/Aug12Updates/` duplication first.
5. Pick one home for session documentation: `August/AugNNUpdates/` or
   `docs/superpowers/`.
6. `fynla-marketing-review` uncommitted work — commit, or discard as superseded.
7. Whether a `dev → main` release is on the table, given 719 commits and the 28
   doc commits stranded on `main`.

## What was NOT checked

- **csjones.co/fynla was not inspected.** Whether the deployed dev server is
  actually at `c9e64c2` is unverified — this survey was GitHub and local only.
- No migrations were run, no code was changed, no branch was merged, rebased or
  deleted.
- The marketing pipeline was not exercised; the commissioning checklist is read,
  not executed.
