---
id: W-0411
title: Every overdue goal reports "On track" and the goals page congratulates the user, because an inverted date range read as a positive span
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0029-cycle4-goals-and-expenditure-split.md
owner: build-lead (fix-cycle4-goals-expenditure)
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T02:00:00Z
claimed: 2026-08-23T02:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0029, W-0414, W-0416, GoalCalculationService, GoalProgressService]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Live, `peak_earners`, both accounts:

> *"Charlotte's Gap Year Fund · **On track** · 80%"* — target date three weeks gone.
> *"Max Pension Contributions"* — target 2026-04-05, four and a half months gone, 75%,
> **"On track"**. Page summary: **"All goals on track! Keep up the great progress"**.

### Root cause, verified rather than inherited

`GoalCalculationService.php:56-81` (pre-fix). Three facts compose it:

1. `start_date` is stamped with **today** at creation, so a goal recorded against a date
   already in the past stores a span that runs **backwards**.
2. Carbon is **2.73.0**, where `diffInDays()` is **absolute** by default. Measured, not
   assumed: `Carbon::parse('2026-08-22')->diffInDays(Carbon::parse('2026-08-01'))` returns
   `int(21)`; with `false` it returns `int(-21)`.
3. So `$totalDays` came back **positive** for an inverted range, and the `$totalDays <= 0`
   guard at `:72` — which exists *precisely* to catch a non-positive span — never fired.

Elapsed read as ~1 day of a 21-day span, expected progress as ~5%, and any progress at
all cleared the 10% margin.

```
#59 u16  start 2026-08-21  target 2026-04-05  75%  is_on_track = TRUE
#60 u16  start 2026-08-22  target 2026-08-01  80%  is_on_track = TRUE
```

The page summary is `on_track_count === total_goals` (`GoalsOverview.vue:109`), fed by the
same accessor (`GoalsAgent.php:357`), so one wrong boolean per goal became a
congratulation.

**This is W-0029's residual.** Making past-dated goals creatable was right; the on-track
maths was never updated for them.

### A second mechanism, found by enumerating

`GoalProgressService.php:43` decided `is_on_track` a **second** time by its own rule
(`$currentAmount > 0 && $progressDelta >= -10`) over its own absolute-diff span at
`:28-32`. Its `expected_progress` therefore sat at **0** for an overdue goal, so a missed
goal read as **ahead of schedule**. Consumed by `GoalsController::show`,
`GoalStrategyService`, `GoalPlanService`, `SavingsAgent`. Rule 20 — collapsing it is part
of this fix, not a separate item.

## Acceptance

1. An overdue goal never reads "On track" on any surface, funded or not.
2. The page summary never claims all goals are on track while one is overdue.
3. Overdue at 100% and overdue at 75% are **different answers** — achieved late versus
   missed — and the vocabulary that states them has **one home**.
4. A healthy future-dated goal still reads on track. The rule fires on overdue, not on
   everything.
5. The fixture contains an overdue goal **and** an overdue-but-fully-funded one
   (`tests/CLAUDE.md` §4, Fixture variant) — a suite whose goals are all future-dated
   never enters the branch at all, which is how this survived.
6. Verified in the live browser on web **and** `/m` (Rule 19).

## Notes

`calculateDaysRemaining()` floors at zero, so `GoalCard`'s `days < 0` branch was
unreachable and a goal three weeks past its date displayed **"Today"** as its time
remaining. Same file, same family — fixed here via an explicit `is_overdue`, leaving
`days_remaining`'s non-negative contract intact for its other consumers.

---

## Outcome — 2026-08-23, build-lead (`fix-cycle4-goals-expenditure`)

**FIXED and browser-verified on web and `/m`.**

`GoalCalculationService` gains `isOverdue()` and `calculateStatusLabel()`; spans are signed;
`GoalProgressService` stops deciding the same boolean a second time and asks the one home.
`is_overdue` and `status_label` are appended to the model and carried by `GoalResource`,
all three `GoalsAgent` payloads and Fyn's goal snapshot.

**Web `/goals`:** both overdue goals read **Overdue**; *"All goals on track! Keep up the
great progress"* is gone, replaced by *"2 goals are behind schedule"*; the three healthy
goals still read **On track** — the rule fires on overdue, not on everything.

**`/m`:** *"Goals on track — 3 of 5"* (was 5 of 5), both overdue goals **OVERDUE / "Target
date passed"**, zero "Behind". The page served `m-build/assets/main-CFX4VVV3.js`, the exact
bundle grepped beforehand. Sarah's own future-dated goal reads **ON TRACK, 1 of 1** — the
control holds on the second account.

**Criterion 5 (Fixture variant) — met, and it is the finding of this batch.** **40
pre-existing goal tests passed WITH the bug restored.** Not one of them was wrong; every
fixture was future-dated, so the suite was blind to one branch indefinitely while reading
as coverage. The new file's fixture holds an overdue goal, an overdue-but-fully-funded one,
healthy goals and an inverted range. **M4 (bug restored): 5 cases red, and only those.**

**NOT verified:** `GoalsOverviewCard.vue` on `/dashboard` renders as **"Locked"** for this
user, so its second copy of the banner never rendered. **I COULD NOT TEST THIS.**

Tests: `tests/Unit/Services/Goals/GoalOverdueIsNotOnTrackTest.php` (18) +
`tests/Feature/Goals/PastDatedRecordsTest.php` extended (9, in W-0029's own file, where the
acceptance stopped). Branch doc §2, §6.3.

- 2026-08-31 build-lead: **CLOSED — verified against `dev`, with the Carbon behaviour recorded
  rather than assumed.** `GoalCalculationService:60-73` sets out the composition — past-dated goals
  became creatable under W-0029, `start_date` is stamped today so the span runs backwards, and on
  Carbon 2 `diffInDays()` is ABSOLUTE so the inverted range returned +21 and the `$totalDays <= 0`
  guard never fired. `isOverdue()` is now the one answer (Rule 20, cited from
  `GoalProgressService:29`), consumed by `GoalCard.vue:243`. An overdue goal no longer reports
  "On track", and the page banner no longer congratulates on it.
