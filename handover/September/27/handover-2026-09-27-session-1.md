---
type: handover
mode: session-end
date: 2026-09-27
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-27, Session 1 (the work of 2026-09-26)

## Where things stand

Two PRs are open against `dev`, both walked on csjones (web and /m). Neither is merged or released; merging is CSJ's call.

**PR #943** (`fix/income-definitions-pension-contributions`, head `99d91a7fe`):
- Onboarding-captured pension contributions reach every income definition.
- One pension contribution rule covers the projection, the Annual Allowance check, and the pension cards and detail views.
- Long-term UK residence comes from tax config and replaces the repealed 15-year deemed-domicile rule.
- It also carries the walk-defect fixes.

**PR #944** (`feat/action-detail-cards`, head `ae434a88e`):
- Every action opens its own detail card (design C) on web, /m and iOS.
- It contains everything in #943.

**TestFlight:** builds 11 and 12 (Fynla Dev) are uploaded. Build 12 has the review fixes. iOS cards will not load until the #944 backend is on fynla.org.

## Priorities for the next session

1. **Ask CSJ at the start, before doing anything else:**
   - **(a) Merge and release.** Merge #943 then #944, or #944 alone, then run `/release`. Merging needs CSJ's `/release` in the turn. Prod deploys run as a script CSJ executes with `!`.
   - **(b) Review the 21 tax how-tos.** They are in `database/seeders/data/action-how-to/tax.md` (on the #944 branch). CSJ changes `draft` to `approved` per entry; nothing shows until then. Seven are `unverified`. The review also noted: SIPP not spelled out; `savings_to_spouse` step 2 unsourced; `lifetime_isa` omits the age-60 rule.
   - **(c) Hear back on TestFlight build 12.**
2. **Release checklist when CSJ says go:**
   - For #943: `TaxConfigurationSeeder`.
   - For #944: the migration `2026_09_27_000001_add_how_to_to_action_definitions`, then `ActionHowToSeeder`. It now runs with `db:seed` and throws if its source file is missing.
   - Both bundles.
   - Walk on fynla.org, then check iOS build 12 against prod.
3. **Help pages rewrite.**
   - The audit is done: `docs/help-audit-2026-09-26.md`. Of 58 sections, 7 are accurate, 36 describe stale UI and 12 state wrong facts; there are 13 hardcoded figures, and 24 screens have no help.
   - Users only ever see `public/pages/help.php`; `Help.vue` is unreachable.
   - CSJ agreed an audit-then-rewrite; propose one source and rewrite against the live screens, with every statement sourced (Rule 23).
4. **Fyn narration fix (Rule 20, one place).** "Ask Fyn about this" left the personal pension out of the £7,800 pension-input explanation, and said "mechanical-tier strategy", which leaks `claim_tier`. Load the `fyn-architecture` skill first.
5. **The other modules' how-to batches,** after CSJ approves the tax batch. Order: savings (54), protection (32), retirement (26), investment (17), estate (12). The seeder currently has a source for `tax` only; add `SOURCES` entries.
6. **Deferred minors from the #944 review.** The list is in `CSJTODO.md`, "NEXT".

## Context to load

- `CSJTODO.md` section "NEXT — two PRs to merge and release…": the ranked outstanding list, updated in place today.
- `gh pr view 944`: what the cards branch does, its deploy needs and its walk evidence (the PR body is the fullest record).
- `gh pr view 943`: the pension and residence branch, its walk figures and its deploy needs.
- `docs/help-audit-2026-09-26.md`: the input for priority 3.
- Memory `feedback_small_changes_direct_no_delegation.md` and `feedback_no_full_suite_per_small_change.md`. CSJ objected this session to the overhead of repeated full runs and deploys; see "Decisions".

## Completed this session

**#943:**
- Test reds diagnosed with test-failure-forensics. Fixtures were pinned: `DCPensionFactory` randomises `scheme_type` and `monthly_contribution_amount`, and `pension_type` defaults to `personal` (BUG-02).
- Walk-found defects fixed:
  - Annual Allowance "used" now £7,800, was £7,200: relief at source counted gross (s233).
  - Income panel: personal pension row and correct Threshold Income working.
  - Pensions page median read the wrong key (showed £0).
  - `DCPensionResource` was missing the contribution figures.
  - The web pension card had no workplace contribution line.
  - `/savetax` said "up to £0" for pension holders.
  - The Fyn loop question didn't read back the saved pensions before the Free cap line.
  - /m "canonical" subtitles.
  - Unanswered country of birth was defaulted to UK.
  - The domicile form read the stale session user.
- Consolidation: `PensionContributionRule`, `IncomeDefinitionsService::pension_input_amount`, and `LongTermResidence` (tax config `domicile.long_term_residence`; years before 2025 keep the old rule).

**#944:** built with `superpowers:executing-plans`. Plan `docs/superpowers/plans/2026-09-26-action-detail-cards.md` lives on the branch.
- `ActionCardService` + `GET /api/recommendations/actions/{id}`.
- `FundingAccounts`, one list replacing three copies: it adds `easy_access`, joint accounts at the user's share, and months from config.
- How-to storage (migration + seeder).
- Web `ActionCardView`, /m `ActionCard.vue`, iOS `ActionsListView` / `ActionCardView`. "See all actions" on iOS opens the list, not Achievements.
- Also: every action row opens its card; the unlock prompt comes from `RecommendationRouting::strategyUnlockPrompt`; "Warning" is no longer a topic; "Go to it" link on cards.
- The fresh whole-branch review (2 Critical, 7 Important) is fixed.

**TestFlight:** builds 11 and 12 uploaded.

## Verification state

- **#943 full Pest:** 9,105 passed, 30 skipped at `99d91a7fe`. Walked on csjones, web and /m: user 427 `idef-walk2-2026-09-26@example.com`, and user 426.
- **#944 full Pest:** 9,120 passed, 30 skipped; full Vitest 1,470, both before the review fixes.
- **After the review fixes, affected suites only:** Pest 195, Vitest 120.
- **#944 walked on csjones** with user 427:
  - Web: cards, Fund from, Ask Fyn, Add it now.
  - /m: card, Fund from pick shared with web, Mark as done, Go to it.
- **Not verified:**
  - Web "Go to it" live (spec only).
  - **iOS unit tests never run.** They compile; CSJ said skip the simulator.
  - iOS end-to-end: cards cannot load until the backend is on prod.
  - No full regression after the #944 review fixes.

## Decisions and dead ends

**CSJ rulings:**
- Long-term residence must come from tax config, like every other rule.
- "Fund from" is built in the first pass.
- Walk defects are fixed, not reported ("why leave an app breaking bug").
- The help-page audit comes first.
- iOS: no simulator, deploy to TestFlight instead.
- **Too much process for small changes:** one full run before merge, focused tests otherwise, one deploy per batch.

**My rulings (in PR bodies; the ledger is deleted):**
- Every action row opens its card, including unlock rows that used to open Fyn directly.
- `/savetax` shows the pension line for pension holders too, superseding the June "no pension only" rule on CSJ's "fix it".
- How-to steps carry no figures, ages or percentages (Rule 2).
- The legacy retirement headroom "a year" label stays; it is on the rollback path only.
- No plans-page `FundingAccounts` regression pin.

**Dead ends:**
- Running pest while another pest ran on the same test DB gave a burst of false Estate reds. I killed it and re-ran clean. Never overlap pest runs.
- `computer-use` access to Xcode and Simulator was not granted, so no simulator; `xcodebuild build-for-testing -destination 'generic/platform=iOS Simulator'` compiles without booting one.

## Things that will bite you

- **csjones runs `feat/action-detail-cards`,** not `dev`. Reset to `dev` after merge.
- **Logging in on /m signs out the web session,** and the reverse. Login codes come from `email_verification_codes`, via tinker over ssh.
- **Web Fyn panel and csjones cookie banner:** the Fyn panel covers the profile "Edit" button (click "Collapse"). After clearing cookies, the banner blocks clicks.
- **iOS `APIClient`:**
  - It encodes path segments itself; never pre-encode ids.
  - Endpoints replying `{success, message}` with no `data` need `responseDecoding: .raw`.
- **/m colour tokens:** style.css defines only some palette steps. `--violet-600` and `--violet-50` do not exist; check every `var()` against style.css.
- **ASC build numbers:** the next is 13. Query ASC with the JWT from `~/.appstoreconnect/private_keys/AuthKey_683FKHT7SL.p8` (`curl -g`).
- **Worktrees:**
  - The scratchpad worktree `wt-planb` (old session dir) holds the #943 branch.
  - The `wt-cards` worktree (`e0f6af27…/scratchpad/wt-cards`) holds #944.
  - Both are pushed.

## Tech debt deferred

From the #944 final review (full list in `CSJTODO.md`):
- **Card error states:** web `ActionCardView.vue` and /m `ActionCard.vue` don't handle a non-404 load error, a failed funding save or a failed mark done; /m back always goes to the actions list.
- **Values the apps derive:** clients work out `action_category` (`id.replace(/^tax_/…)`) and `tax_` ids; the server should send them.
- **`ActionCardService`:**
  - The deadline ignores `timeline === 'immediate'`.
  - `UNLOCK_CONSEQUENCES` lives in this service, not in `RecommendationRouting`.
- **`FundingAccounts`:** drops zero-balance accounts, and the emergency warning uses whole-account monthly saving against the user's share.
- **`RecommendationsController::actionCard`:** double `rawurldecode`, and no `errorResponse` wrapper.
- **iOS:** the done list is dropped whole on one bad row; `actionFailed` is shared across all cards.
- **About ten places compute the 6 April tax-year start separately.** `LongTermResidence::taxYearStart` is a candidate single home.
- **`AnnualAllowanceTracker.vue`** is unrendered and holds a wrong client-side sum. It was left in place (verify product need before deleting).

## Branch and deploy state

- **Main checkout:** `dev` at `372777cc4`.
  - Untracked and not from this session: excalidraw, `workforce/*`, `brettTest/`, `chrisMapping/`, `September22Updates/`, briefs.
  - Untracked and mine: walk screenshots under `tests/Persona/income-definitions/` and `tests/Persona/action-cards/`, not committed.
- **Unpushed commits:** none.
- **csjones:** `feat/action-detail-cards`, migration applied, `TaxConfigurationSeeder` and `ActionHowToSeeder` run.
- **Production:** unchanged, `main` `b81d5fcc2` (#942).
- **TestFlight:** Fynla Dev builds 11 and 12.
