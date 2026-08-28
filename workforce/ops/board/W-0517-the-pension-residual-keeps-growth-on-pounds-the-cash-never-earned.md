---
id: W-0517
title: The unused pension residual keeps investment growth on pounds that were withdrawn and sat in cash earning nothing
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-28T19:15:00Z
claimed: null
blocked_by: [W-0512]
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-28
prior_art_found: [W-0482, W-0512]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance gate re-review of the W-0482 rework, 2026-08-28
---

## Intent

W-0482 fixed the double count. It did not fix the growth attribution, and the residual is
materially too large as a result.

`RetirementProjectionService::unusedDcFundAtAge()` computes:

```php
$grownFund = $pot['percentile_20_at_retirement'] * pow(1 + $growthRate, $yearsRetired);
$credited  = Σ  $annualIncome * pow(1 + $inflationRate, $year)   // year = 0..$yearsRetired
$residual  = max(0.0, $grownFund - $credited);
```

`$grownFund` is the future value of the **whole pot left untouched**, so it includes the
growth earned by pounds that were in fact withdrawn. `$credited` removes only those pounds
at their **nominal** value. Their growth stays in the residual — and
`HouseholdCashFlowProjector:171` is `$balance += $surplus` with no return applied, so once
those pounds reach cash they earn nothing at all. The estate is credited with growth that
never happened anywhere.

The pound itself is counted exactly once, so this is **not** the W-0482 double count
returning. It is an inflation of the estate on top of it.

**The correct complement discounts each withdrawal to the death date at the fund's own
growth rate:**

```
residual = grownFund − Σ income_t × (1 + g)^(yearsRetired − t)
```

**Magnitude.** A £500,000 pot at retirement, `expected_return_min` 3%, inflation 2.5%,
a 4% safe withdrawal rate and 20 years of retirement:

| | figure |
|---|---|
| grown fund, untouched | £903,000 |
| credited income, nominal (what the code subtracts) | £543,000 |
| **residual as coded** | **£360,000** |
| credited income, future-valued at the fund's growth rate | £723,000 |
| **residual, correct** | **£180,000** |

The residual is roughly **double** what it should be, worth about **£72,000 of
Inheritance Tax at 40%** for that household — and the error grows with the length of
retirement.

**Why this was filed rather than blocking the W-0482 merge.** The subtraction has two
halves and the other half is already wrong: W-0512 means the credited series is itself a
perpetuity struck against a fund that never shrinks. Refining the growth treatment of one
half while the other is unfixed would replace a stated approximation with a false
precision. **These two must be fixed together**, and W-0512 is the dependency.

## Acceptance

1. The residual discounts each credited withdrawal to the death date at the same rate the
   fund is grown at, so the pension contributes the fund's true value at death.
2. Fixed with W-0512, not before it — the credited series must be the real drawdown before
   its future value means anything.
3. `unusedDcFundAtAge()`'s docblock states the new basis and stops describing a nominal
   subtraction.
4. A test that fails against the nominal subtraction: for a household 20 years into
   retirement the residual must be strictly less than `grownFund − Σ nominal income`.
5. Before/after `projected_unused_pension` and the resulting Inheritance Tax for the
   worked household above. The figure moves DOWN; state by how much.
6. `tax-compliance-reviewer` — it moves the Inheritance Tax of every household with a
   defined contribution pension.

## Working notes

- 2026-08-28 — Found re-gating the W-0482 rework. The complement's series was verified
  correct against `HouseholdCashFlowProjector::pensionIncomeInTodaysMoney()`: the projector
  deflates `dc_annual_income` by `(1+i)^yearsToRetirement` then re-inflates by `(1+i)^year`
  from today, which is exactly `income × (1+i)^t` for `t` years since retirement — the
  series `unusedDcFundAtAge()` sums. The **series** is right; the **valuation date** is not.
