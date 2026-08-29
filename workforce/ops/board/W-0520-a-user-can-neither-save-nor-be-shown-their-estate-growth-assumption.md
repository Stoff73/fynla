---
id: W-0520
title: A user can neither save their estate planning assumptions nor have them used, in three independent layers
mission: null
branch: fix/w-0520-unused-injections-and-uncalled-projection
owner: null
reviewers: [tax-compliance-reviewer]
status: in_progress
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-29T10:30:00Z
claimed: 2026-08-29T10:30:00Z
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: [W-0519]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: CSJ, 2026-08-29 — "the reported items for 745 all look like they should have callers and or be used on the calculation, so check and fix"
---

## Intent

`Settings → Assumptions` offers the user an investment growth method — **Monte Carlo** or
**Custom** with their own rate — plus a property growth rate and an inflation rate
(`AssumptionsSettings.vue:329`, validated at `AssumptionsController:65`). Those figures
drive the projected estate and therefore projected Inheritance Tax.

**None of it worked, in three independent layers, each of which hid the next.**

### 1. The row could not be written at all

`2026_02_03_100002` added the three estate columns and, in the same migration, a raw
`DB::statement` widening the `assumption_type` enum to include `estate_planning`. **The
columns landed everywhere; the enum change landed nowhere.**
`database/schema/mysql-schema.sql:3864` carries `enum('pensions','investments')` with the
three estate columns beside it, and a survey of every database on the development machine —
**the dev `laravel` database included, 59 of 59** — matched the dump rather than the
migration.

So `AssumptionsService::updateEstateAssumptions()` writes `assumption_type =
'estate_planning'` into an enum with no such member. The insert is rejected.

### 2. The columns were not fillable

`UserAssumption::$fillable` listed the five original columns and none of the three estate
ones. `updateEstateAssumptions()` writes through `updateOrCreate()`, therefore through
`fill()`, and **mass assignment discards an unfillable attribute silently** — it does not
raise. Even where the row could be written, the three values were not in it.

### 3. The projection ignored the setting anyway

`IHTCalculationService::calculateProjectedValues()` called
`EstateProjectionService::projectInvestmentsMonteCarlo()` **directly**, straight past
`projectInvestments()` — the dispatcher that reads `investment_growth_method` and branches
on it. The dispatcher was written in `37b9b7b1` (2026-02-03) and **has never had a caller**.

It was not ignored entirely, which is what made it hard to see: `getFallbackGrowthRate()`
reads the custom rate, but only as the fallback for when the simulation FAILS. A user's
explicit choice was reachable solely by an error — exactly backwards. `LifeCoverCalculator`
honours the same setting correctly, so the estate was the one place that did not.

## Resolution — 2026-08-29

1. **`2026_08_29_110000_allow_estate_planning_in_user_assumptions_type`** re-applies the
   enum widening as its own migration, because the original is marked as run and a squashed
   schema load applies the dump and then only migrations that came after it. `ALTER TABLE`
   on an enum that only GAINS a member preserves every existing row.
2. **`UserAssumption::$fillable`** gains `property_growth_rate`,
   `investment_growth_method` and `custom_investment_rate`, with casts to match.
3. **`calculateProjectedValues()` calls `projectInvestments()`**, the dispatcher.
   `projectInvestments()` becomes the public entry point and
   `projectInvestmentsMonteCarlo()` goes back to private, so the branch cannot be bypassed
   again by calling past it.

The dispatcher also carries a `yearsToProject <= 0` guard that only ever existed there: at
a zero horizon the simulation was asked to project nothing where the caller wants today's
value.

**Verification.** 899 passed across Estate, Retirement and Architecture, plus 16 on the
assumptions filter. Four new tests, all **mutation-verified** — a 10% custom rate over ten
years on £100,000 must land on £259,374.25, which no Monte Carlo p20 can produce, and the
save test drives `AssumptionsService` rather than the model precisely because defect 2 is
invisible to a test that sets attributes directly.

## Also fixed here

- **`IHTCalculationService` no longer injects `LifeEventService`.** It was genuinely used
  once, via `getLifeEventImpactsByAge()` (`ba23a490e`); the capability moved to
  `HouseholdCashFlowProjector::lifeEventImpactsByYearFromNow()` in the Rule 20 cash
  consolidation and the injection was left behind. **Nothing is lost** — life events still
  reach the estate through the cash projector.
- **`PensionStore` is injected** rather than resolved with `app(PensionStore::class)`
  mid-method, alone among that class's collaborators.
- **`ProjectedEstateCountsThePensionOnceTest` was intermittently red.** `DCPensionFactory`
  randomises `retirement_age` across 60–68 and `investment_strategy` across five risk
  profiles, so the LENGTH of retirement and the GROWTH RATE both moved between runs. With
  the W-0512 depleting drawdown that decides whether the fund is exhausted by the modelled
  death, and therefore whether the pension caveat is published at all. Both are pinned in
  the fixture; three consecutive runs now give an identical 27 assertions.

## Reported, not fixed — the wider sweep

A sweep of `app/` for **private** constructor-injected properties never referenced through
`$this->` found **60**, across agents, controllers and services. (A first pass found 290;
the rest are `public readonly` DTO, Event and Mail properties, which are read from outside
and are correctly there.)

They are not all the same thing, and that is the point — each needs the judgement this item
just applied to three of them, so ripping all 60 out in one diff would be wrong:

- **Some are dead leftovers** like `LifeEventService` above — safe to remove.
- **Some may be a capability silently not wired**, like `projectInvestments()` was.
- **Some are a Rule 2 risk**: `TaxConfigService` is injected and unused in
  `EstateActionDefinitionService`, `LifePolicyStrategyService`, `SavingsDataReadinessService`,
  `EstateIhtExposureDetector`, `GoalAssignmentService`, `InvestmentPlanService`,
  `RetirementPlanService` and `SavingsPlanService`. A service that took a tax-config
  dependency and does not use it is a service that may be getting its tax values from
  somewhere else.

Highest-signal clusters for a follow-up item: `RetirementAgent` (6 unused),
`EstateActionDefinitionService` (3), `ComprehensiveProtectionPlanService` (3),
`GoalsAgent` (3).

**Also not fixed:** `database/schema/mysql-schema.sql` still carries the stale enum. The new
migration corrects any database built from it, so the dump is wrong rather than harmful;
regenerating it is a separate, wider change.
