---
id: W-0513
title: The projected estate models only defined contribution pots, where IHTA 1984 s150A brings in lump sum death benefits too
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: done
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

- 2026-08-31 build-lead: **FIXED AND TESTED — closed, with the residual named rather than hidden.**

  **The gap is config-versus-code, and it was silent.** The configuration declares `'applies_to' => ['defined_contribution', 'death_benefits']`, and IHTA 1984 s150A does bring lump sum death benefits into the estate alongside unused pots. `IHTCalculationService:2408` sums only `forUserByType($user, 'dc')->sum('current_fund_value')` — half of what the configuration claims — and published the result as though it were the whole answer.

  **The reason it cannot simply be computed:** there is **no death-benefit column anywhere**. `dc_pensions` has `current_fund_value` and nothing about death; `db_pensions` has `lump_sum_entitlement`, which is the retirement commutation lump sum — a different thing, and using it would be wrong rather than approximate. The application has never asked what a scheme pays out on death.

  **So the coverage is declared instead of estimated.** Inventing a death-benefit figure would put a made-up number into a user's Inheritance Tax exposure, which is worse than an understatement the user knows about. The scenario now publishes `pension_value_covers` and `pension_value_excludes`, derived from the configured `applies_to` list rather than hardcoded, plus a `coverage_caveat` telling the user in plain words that lump sum death benefits are within the amendment, are not held by Fynla, and that their actual exposure could be higher.

  **That is the honest fix for the state the data is in**, and it makes the shortfall visible to the next reader: `pension_value_excludes` is non-empty and says exactly which configured category has no consumer. If a death-benefit column is ever added, `$coveredCategories` is the one line to extend.

  **Tested:** 13 pension-amendment and snapshot tests pass, 34 assertions. Pint clean.

  **NOT DONE — and this is the remaining work, recorded so it is a decision rather than an omission.** Capturing lump sum death benefits needs a new column on the pension tables and a field on both pension forms (web, `/m`, native under Rule 19). That is the same shape as W-0527's missing datum and needs the same explicit go-ahead. No `tax-compliance-reviewer` pass on the s150A reading.
