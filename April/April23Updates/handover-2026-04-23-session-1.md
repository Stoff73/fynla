---
type: handover
mode: end-of-day
date: 2026-04-23
session: 1
branch: feature/fyn-persona-split
previous_session: 2026-04-22 session 4 (end-of-day)
---

# Handover — 2026-04-23, Session 1

## Where we left off

Closed out 22 April after session 4 shipped the entire REPORT-2 B-1..B-9 remediation (nine bugs + all 16 missing Feature tests). B-1 multi-entity gap-fill is now deployed across all three chat dispatch paths and verified live end-to-end in Fyn Quick Chat (Aviva life £300,000 + Vitality critical illness £100,000 → both policies persisted). Branch `feature/fyn-persona-split` pushed through `355aa97`. The 13-test end-to-end matrix that CSJ requested was started — Test 1 (Follow Journey → Protecting What Matters, married user) was walked as far as the spouse + dependants + profile-review pause + base_employment step before being interrupted by a live-session HMR staleness issue; remaining 12 test combinations are deferred to this session.

## What shipped today

- `37b6a4b` feat(fyn): deterministic multi-entity gap-fill (B-1) + household provisioner (B-2)
- `355aa97` feat(fyn): B-3/4/7/8/9 fixes + canonical journey labels + capture acks + UX polish

Plus 9 earlier commits in sessions 1-3 (context-clear wraps + multi-entity Phases A/B/C + FR-M21 regression + profile-review pause wiring + DOB parser + scroll anchor).

## What's in flight (NOT done)

**Top priority — resume 13-test browser matrix:**
- Test 1 (Follow Journey → Protecting What Matters, married) — partially walked through base_employment; not yet to `asset_capture(protection)` multi-entity step. Partial transcript at [[transcripts/test1-journey-protecting-married]].
- Tests 2-13 (not started):
  - Test 2: Pick Focus → protection (single, multi-entity)
  - Test 3: Focus → savings (married, multi-entity ISAs)
  - Test 4: Focus → retirement (divorced, DC + DB multi-entity)
  - Test 5: Focus → investment (widowed, ISA + GIA multi-entity)
  - Test 6: Focus → estate (married with children, multiple assets)
  - Test 7: Focus → goals (civil partnership, multi-entity goals)
  - Test 8: Journey Starting Out (single, savings focus)
  - Test 9: Journey Building Foundations (married, first home)
  - Test 10: Journey Planning Your Future (married, retirement focus)
  - Test 11: Journey Enjoying Your Wealth (widowed, estate focus)
  - Test 12: Focus → budgeting (single, expenditure capture)
  - Test 13: Focus → business (sole trader / limited company)

**AppLayout architectural refactor still outstanding** — `AppLayout` mounts inside each view (`Dashboard.vue:904`, `UserProfile.vue:2`), so every route change destroys and remounts it. FR-M21's spec assumption that `<aside>` is outside `<router-view>` is wrong. Session 3 added a mount-time `scrollToLastUserMessage` rescue. Proper fix (hoist AppLayout above router-view) not done.

**Tests — 2 pre-existing flakes remain:**
- `AutoRiskCalculatorTest` — `risk_profiles.risk_level` enum truncation on `medium_high`. Order-dependent.
- `InvestmentModuleTest > Risk Profile Management` — same enum.

## Deploy status

**NOT deployed anywhere.** Branch `feature/fyn-persona-split` is 68 ahead of `main`, 72 behind (long-running feature branch). Deploy notes written at [[April/April23Updates/deploy-2026-04-23|deploy-2026-04-23]] covering the 2 commits from this session if CSJ wants to smoke-test on `csjones.co/fynla` (dev) before the matrix completes.

Deploy gate: merge `feature/fyn-persona-split` → `onboardingFyn` → `csjones.co/fynla` should happen AFTER the 13-test matrix is complete AND automated Feature tests guard all 9 B-X bugs. Both conditions now met for the B-X side — only the matrix walkthrough is outstanding.

## Tech debt found this session

9 items flagged in `tech-debt-report.md` — 0 critical, 4 warnings, 5 suggestions. None block commit or deploy.

Top 3 most impactful (deferred):
1. **W1** — Gap-fill loop logic duplicated across 3 dispatch paths (`AiChatController`, `FynPersonaInvoker`, `OnboardingChatDirector`). Extract a `MultiEntityGapFiller` service when the pattern is stable.
2. **W2** — `OnboardingChatDirector` now 1985 lines (~4× the split-candidate threshold). Candidates: `OnboardingCaptureAckBuilder` + `OnboardingGapFiller` extraction.
3. **W4** — 5 unused-symbol diagnostics in `OnboardingChatDirector.php` (DB import, 4 unused parameters). Quick cleanup.

## Known issues / blockers

- Laravel session expires during long live-browser test runs — repeated re-login required mid-matrix. Not a regression; pre-existing behaviour. Mitigation: keep sessions shorter per test or set a longer session lifetime on dev.
- Running `./vendor/bin/pest` wipes seeded users via `RefreshDatabase` — always reseed (`php artisan db:seed`) before resuming browser testing. Rule is in `MEMORY.md` but the cycle bit during the test-matrix run.
- Vite HMR sometimes doesn't hot-reload Vue watcher changes cleanly — a page reload is needed to pick up the new component logic. Caused a phantom "click doesn't advance" symptom mid-session; fixed by refresh. New feedback memory recorded: [[feedback_incremental_verification]].

## Rules reinforced this session

- `feedback_incremental_verification.md` (new) — One bug, one fix, one live browser test, then the next. Batched fixes + one test cycle means you can't tell which broke. "Tests pass" isn't sufficient for UI-layer bugs. Diagnose network/console/backend before reverting.

## Next session should

1. **`./dev.sh` + `php artisan db:seed`** — always. Run `php artisan db:seed` again after any Pest run wipes users.
2. **Hard-reload the dashboard once after `./dev.sh`** to avoid HMR staleness on the Vue watchers added today.
3. **Resume the 13-test browser matrix starting with Test 1.** Partial transcript at `April/April22Updates/transcripts/test1-journey-protecting-married.md` gets us as far as base_employment. Pick up there and drive through `asset_capture(protection)` with the multi-entity message `"I have Aviva life insurance £300,000 and Vitality critical illness £100,000"` — confirm BOTH policies persist and the new canonical labels render correctly. Write the transcript as you go.
4. **Run Tests 2-13 one by one.** Reset John (or the relevant seeded test user) between tests: wipe family_members + policies + conversations + reset `onboarding_fyn_step` to null. Keep `marital_status` matching each test's scenario.
5. **At the end of the matrix, open a PR** `feature/fyn-persona-split` → `onboardingFyn` (per CLAUDE.md deploy workflow, never directly to main).

## Context hints

- Active branch: `feature/fyn-persona-split` (long-running feature branch).
- Behind origin/main by: **72 commits** (expected — feature branch).
- Ahead of origin/main by: **68 commits**.
- Uncommitted: **none, working tree clean** (tech-debt-report.md + handover + CSJTODO + deploy note land in one final commit in Phase 10).
- Last commit: `355aa97 feat(fyn): B-3/4/7/8/9 fixes + canonical journey labels + capture acks + UX polish`.
- Tests: **2448 pass, 1 pre-existing Risk Profile flake** (documented).
- Feature flag state on dev: `FYN_PERSONA_SPLIT=true` (as of session-3 handover). B-1 gap-fill works regardless.
