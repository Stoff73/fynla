---
id: W-0513
title: The projected estate models only defined contribution pots, where IHTA 1984 s150A brings in lump sum death benefits too
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-28T15:32:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-28
prior_art_found: [W-0482]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer gate report on W-0482, finding F2, 2026-08-28
---

## Intent

**Finance Act 2026 ss66-71 has Royal Assent (18 March 2026)** and inserts IHTA 1984 s150A,
"notional pension property", for deaths on or after 6 April 2027. W-0482 models the
unused defined contribution fund, which is s150A(2) Step 1(A) and correct as far as it
goes. **Step 2 brings in more, and the app models none of it:**

- lump sum death benefits from a **defined benefit** scheme, and scheme continuation
  payments
- **annuity protection** (value-protected) lump sums
- guaranteed-period continuation payments

`RetirementProjectionService::unusedDcFundAtAge()` returns `no_pension` and zero the
moment `dc_pension_count === 0`, so **a defined-benefit-only household contributes
nothing at all**. The data already exists: `db_pensions.lump_sum_entitlement` is read at
`app/Agents/CoordinatingAgent.php:2166`.

**The application's own configuration is wider than its code.**
`database/seeders/TaxConfigurationSeeder.php:545-550`:

```php
'description' => 'Unused pension funds and death benefits will be included in the estate for IHT purposes',
'applies_to' => ['defined_contribution', 'death_benefits'],
```

The code gates on that key and honours `defined_contribution` while silently ignoring
`death_benefits`. Rule 2 says configuration is the source of truth; here it states a scope
the implementation does not deliver.

## Acceptance

1. Defined benefit lump sum death benefits and continuation payments are modelled, or
   `applies_to` is narrowed to what the code actually does — not left disagreeing.
2. The four s150A(6) **excluded** benefits stay excluded and are named at the line so
   nobody adds them later: dependants' scheme pensions; trivial commutation lump sum death
   benefits extinguishing such a pension; dependants'/nominees' annuities bought with the
   member's lifetime annuity; and **death-in-service benefits** — s150A(6)(d), whose
   statutory condition is employment *immediately before death*, so a deferred or retired
   member's lump sum is NOT covered.
3. A **charity** lump sum death benefit is included in notional pension property and then
   exempted by new s23(5B) — it is not an excluded benefit. Model it that way or state why
   not; getting this wrong in the other direction understates the estate.
4. W-0482's caveat is narrowed as each gap closes — it currently tells the user defined
   benefit lump sums are not included.
5. `tax-compliance-reviewer`.

## Working notes

- 2026-08-28 — Raised as F2 by the gate on W-0482, which also confirmed what W-0482 got
  right: s150A draws no distinction between a crystallised drawdown pot and an
  uncrystallised one (FA 2026 s69 omits IHTA s12A and s152), so the model's indifference
  to crystallisation is correct rather than a simplification. Trust-based and discretionary
  death benefit schemes are caught — that is the reform's central purpose.
- 2026-08-28 — The death-in-service exclusion was **not** in the October 2024 announcement.
  It arrived in the 21 July 2025 consultation response and is the largest scope change
  between announcement and Act. Older summaries will mislead.

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev`.**
  `RetirementProjectionService:427-428` still returns `['amount' => 0.0, 'basis' => 'no_pension', …]`
  the moment `dc_pension_count === 0`, so a defined-benefit-only household contributes nothing to
  the s150A notional pension property at all. Step 2's lump sum death benefits, annuity protection
  and guaranteed-period continuation payments are unmodelled. `db_pensions.lump_sum_entitlement`
  exists and is read at `CoordinatingAgent:2166`, so the data is there and the code does not ask
  for it.
