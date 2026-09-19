---
type: handover
mode: session-end
date: 2026-09-18
session: 1
repo: fynla
branch: dev (main checkout; clean apart from the long-standing untracked workforce/ and docs/diagrams/ files)
---

# Session Handover — 2026-09-18, Session 1

## Where things stand

**Two releases shipped to production, both verified live.** The first fixed four bugs CSJ hit walking the journey onboarding on `/m` — including real data loss, where a second job overwrote the first and left a user on the smaller salary. The second removed three cards from the `/m` and iOS dashboards. Production is on `main` `d6abfdaf0`; `dev` is `4c4c5bc5b` and level with it.

**The architectural change worth knowing about:** employment income is no longer a single column. `employments` holds one row per job; `users.annual_employment_income` and `users.annual_self_employment_income` are now **maintained totals** of those rows, written only through `EmploymentIncomeService`. 204 call sites read those columns and all want the total, which is why they stayed.

**The one thing that surfaced and was NOT actioned:** the `docs-bugs-fixed-log` branch is not a docs branch. It carries 12 commits / 10 bug fixes with tests from 2026-09-14 that never merged, and it still merges cleanly into `dev` today. It needs a decision.

## Priorities for the next session

1. **Decide what happens to `docs-bugs-fixed-log`** — **BLOCKED ON CSJ.** 12 commits, 10 fixes, 66 files, +1,754 lines, last touched 2026-09-14, never merged. Verified today: tree clean, pushed to origin, merges into current `dev` with **17 files overlapping and zero conflict hunks**. Contents: MB-18/19/20 (`/m` login two-factor + restore), MB-24 (front-door "Something else"), MB-26 (server decides who needs onboarding), MB-27/47 (refresh after a Fyn write), MB-28 (profile-review pause return leg), MB-33 (wizard step validation), MB-35 (wizard copy British + civil partnership), MB-48 (non-working spouse household figures), MB-50 (`/m` expenditure entered figure), MB-56/57/58 (pension capture). The 10 `mb-*` local branches are its constituent parts. **Land it, rebase it, or abandon it — but it should not keep sitting there.** Note MB-28 touches the web profile-review pause, which is the same area this session rerouted for the journey path (see Decisions).

2. **iOS has two unverified changes stacked up.** Neither has been seen on screen. Both are compile-verified only (`BUILD SUCCEEDED`) and both ship via TestFlight, not the web deploy, so nothing reached users unverified. (a) The level-climb from 2026-09-17, still outstanding from yesterday's handover. (b) Today's card removal — the milestone nudge, milestone pop-up and Today's insight are still on any installed build until a TestFlight build is cut. Fix sites: `DashboardView.swift`, `NextMilestoneView.swift`, `LevelProgressView.swift`, `LevelCelebrationSequence.swift`; the JS twins are the reference behaviour.

3. **Tech debt, if wanted.** `docs/tech-debt-report.md` — 0 critical, 2 warnings, 2 suggestions. Nothing urgent. Warning 1 (the employments relation loaded by two callers rather than structurally) is the only one with a real failure mode, and only if a third caller appears.

4. **Parked, raise only if asked:** mapping PRs #829–#839 and the 55 mapping bugs (`CSJTODO.md`); the iPhone registration bounce; `HolisticPlan.vue:90`'s fourth `MODULE_LABELS` map.

## Context to load

- `app/Services/Income/EmploymentIncomeService.php` — the ONE writer for employment income. Read before touching anything that writes a salary; the two `users` columns must never be assigned directly again.
- `app/Services/Onboarding/OnboardingStateMachine.php:304` (`campaignVerifyConfig`) and `:1095` (`afterFamilyDetails`) — the verify machinery the family review now routes through, and the campaign/journey split.
- `docs/tech-debt-report.md` — today's audit and the standing "do not re-raise" list, including CSJ ruling 53.
- `handover/September/16/sdd-account-forms/progress.md` — the rulings ledger. Extend it, never re-litigate it.
- `docs/superpowers/specs/2026-09-17-level-up-celebration-redesign-design.md` — the gamification spec, if anything touches the level wheel or celebration.
- `CSJTODO.md` — the mapping-bug backlog and outstanding board state.

## Completed this session

**Released to production twice:**

- **main `4b4e57282`** — PR #904 / #903: the four `/m` onboarding journey bugs.
  - **Income was being lost.** Onboarding's multi-job loop (`STATE_BASE_EMPLOYMENT_MORE`, "Phase 10") always invited a second job, but `handleCaptureWorkDetails` assigned straight to the single column — £48,000 then £9,000 left the user on £9,000. New `employments` table, one `EmploymentIncomeService`, the two user columns kept as maintained totals. Prod backfill: **26 rows across 25 users, £2,046,581, zero users whose rows failed to total their existing column.**
  - **The family verify step asked "does this look right?" over a full-screen chat.** `STATE_PROFILE_REVIEW_FAMILY` was an empty state whose handling was web-only (`AppLayout` un-blurs the dashboard behind a shrunken chat). Now routed through the same announce → navigate → confirm loop as every other section, via a `'family'` section in `campaignVerifyConfig` with route `/personal-information`. Campaign walk keeps the old pause. Web gained a `/personal-information` → `/settings/personal` alias.
  - **Answered capture forms went solid grey** with washed-out text — `.m-field` and `.form-input` declared no `:disabled` style, so the browser's own won, and WebKit greys the text via `-webkit-text-fill-color`. Fixed on both surfaces.
  - **The money field overflowed its card to the right on Safari** — a flex item's `min-width` defaults to its intrinsic width and Safari's for `input[type=number]` is wider than Chromium's. `min-width: 0`.
  - **"Single-person household" for a married user** — `/m` read `household.name`; the column is `household_name`.
  - Plus two found on the way: the personal ack said *"Thanks — I've noted you're and single."* on a partial capture, and `CaptureAckTest:48` had been sitting red on `dev` asserting stale behaviour on top of that defect.

- **main `d6abfdaf0`** — PR #906 / #905: three cards removed from the `/m` and iOS dashboards (next-milestone nudge, milestone celebration pop-up, Today's insight). **Cards only** — `nextMilestone`, `milestoneToast`, `fynInsight` and every handler remain, and the CSS plus `NextMilestoneView.swift` are kept and commented as unused with what to restore alongside them.

**Housekeeping:**
- Both worktrees removed (`fix-joint`, `stack`) — both clean and pushed, nothing lost.
- 25 merged local branches deleted. Every local branch had **zero unpushed commits**, so nothing was ever at risk. `main`, `dev`, the 10 `mb-*` branches, `docs-bugs-fixed-log` and 6 other unmerged branches kept.
- Test accounts purged on prod and csjones, including a rotated `mobile-token` on a real account (see Things that will bite you).

## Verification state

- **Production (fynla.org), main `4b4e57282`**: full journey from registration on `/m` — two jobs kept and totalling £57,000, verify step navigating to `/m/app/personal-information` with the chat closed, household named, locked form on palette tokens. Migration: 26 rows, zero mismatches. Smoke all 200, homepage server-rendered, zero errors in the log.
- **Production, main `d6abfdaf0`**: all three cards absent from the DOM **and** the page text; callout clearance 128px; **and the payload still serves `next_milestone: "Net worth £10,000"` and `fyn_insight`** — the proof it is cards-only. Smoke all 200, zero errors.
- **csjones**: both changes verified on the feature branch before merge, then returned to `dev` and re-checked.
- **Pest**: 715 passed across the onboarding + income suites. 5 new income-service tests, 4 new personal-ack tests, 2 new state-machine tests.
- **Vitest**: 125 mobile, then 96 after the card removal (fewer files in the narrower run), plus 8 web chat. 1 new `/m` regression test.
- **Native**: `BUILD SUCCEEDED` (generic/platform=iOS, no simulator booted).
- **Not verified:** iOS on screen, either change. No full Pest suite today — focused files only, per the standing instruction.

## Decisions and dead ends

- **CSJ's call on the income model:** a new `employments` table, chosen over accumulating into the one column or removing the "add another job" loop. The alternative accumulate-only fix would have kept the money correct but lost per-job detail; CSJ took the faithful option.
- **CSJ's call on the verify step:** route the family review through the existing verify machinery rather than adding `/m`-only handling that mirrors the web un-blur. That is Rule 20 — consolidating the two mechanisms was part of the fix, not a nice-to-have. It does change web behaviour (it now navigates rather than un-blurring).
- **Keeping the two `users` income columns was deliberate.** 204 call sites read them; migrating all of them would have been a far larger and riskier change for no gain, since every one wants the total. The columns became derived, with one writer.
- **Removing the milestone card meant removing its layout job too.** Both surfaces used the nudge to clear the level card's negative overflow (`md-callout--below-nudge` on `/m`, a conditional `.padding(.top,)` on iOS). `nextMilestone` is **still truthy in the data**, so leaving either binding would have zeroed the clearance with no nudge present and ridden the callout up under the level wheel. Both now take it unconditionally. **If the nudge is ever reinstated, restore the binding at the same time** — this is commented at both sites.
- **Dead end — chasing the /m property-detail scroll bug.** Reported yesterday, not reproducible: measured correct in Chromium and WebKit at iPhone size, `.md-main` scrolls, no overlay in the stack, bottom pad holds. CSJ confirmed it works on their iPhone. **Not fixed — not reproducible**, which is a different status and worth remembering if it returns. One real finding from that hunt, unactioned: the Fyn dock is a full-width bar across the bottom 61px (7% of an iPhone 16 viewport, where a thumb rests) whose ancestor chain contains no scrollable element, so a swipe starting on it scrolls nothing.
- **A red test is either a real bug or a wrong test.** CSJ pushed back on "pre-existing, not mine" for `CaptureAckTest:48` and was right: it was **both** — a stale assertion sitting on top of a genuine malformed-sentence defect. The mirror image landed the same day: the `/m` PersonalInformation fixture used `household: { name }`, a key no API response has ever carried, so it stayed green over a live bug.

## Things that will bite you

- **Never assign `users.annual_employment_income` or `annual_self_employment_income` directly for employment income again.** They are totals of `employments` rows now; a second writer is how they drift from the rows they are meant to total. Go through `EmploymentIncomeService`.
- **An overflow that only appears on iOS is probably `min-width` on a flex item.** Safari's intrinsic width for `input[type=number]` is wider than Chromium's, so the money field overflowed on an iPhone and reproduced in no desktop browser at any width. That is why it was reported and could not be seen.
- **Minting a token on prod to check a page leaves a live session.** `/m` rotates the bearer on use, so the `ui-check` token became a `mobile-token` under a different id. Revoke by `tokenable_id`, not by token name, and confirm the count is zero afterwards.
- **`grep -c pending` against `migrate:status` counts the `pending_registrations` table name.** Use `grep -cE '\| *Pending'` or you will report pending migrations that do not exist.
- **The prod build overwrites the local dev bundle** — different `VITE_BASE_PATH`. Rebuild locally after any release or local dev serves csjones/prod-flavoured assets.
- Backups from today's releases: `~/release-backups/2026-09-17e/` (includes a 36MB DB dump) and `~/release-backups/2026-09-18a/`.
- csjones and prod test accounts purged; prod user 268 (`admin@fps.com`) has zero tokens.

## Tech debt deferred

From `docs/tech-debt-report.md` (0 critical, 2 warnings, 2 suggestions):
- `UserProfileService.php:413` — `incomeSources()` reads `$person->employments`, guarded by two separate `load`/`loadMissing` calls rather than structurally. A third caller would lazy-load.
- `resources/mobile/views/Dashboard.vue` at 1,065 lines (down from 1,087). Lift the Fyn overlay out if it grows again.
- `CoordinatingAgent.php:2178` — the job-count ternary repeats the employment/self-employment branch the service already owns.
- The deliberately unused CSS and `NextMilestoneView.swift` are **not debt to action** — CSJ's instruction, commented in place.

Standing: `CaptureForms.php` **declined** (ruling 53); the celebration rule in three copies (deliberate); `playBankedLevels()` across the two Vue dashboards; `HolisticPlan.vue:90`'s fourth label map; "Free plan" hardcoded at `OnboardingChatDirector.php:1105`; `handleFormTurn` at 142 lines.

## Branch and deploy state

- Branch: `dev` at `4c4c5bc5b` (== `origin/dev`). Working tree clean apart from the long-standing untracked `workforce/` and `docs/diagrams/` files inherited from earlier sessions — **not this session's, deliberately left alone.**
- Unpushed commits: none. No worktrees.
- Production: `main` `d6abfdaf0`, deployed and verified.
- `dev` and `main` are level.
