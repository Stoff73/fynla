---
id: W-0510
title: A drawn-out pension fund never reports as depleted, so "years funded" is always the full horizon
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [quality-lead]
status: queued
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-28T14:20:00Z
claimed: null
blocked_by: []
gate: null
prior_art_checked: 2026-08-28
prior_art_found: [W-0482]
prior_art_outcome: new
source: found while wiring the estate to the retirement residual under W-0482
---

## Intent

`RetirementProjectionService::projectTargetIncomeDrawdown()` draws what the household
needs and no more:

```php
$dcDrawdown = min($dcNeeded, $remainingFund);
$remainingFund = $remainingFund * (1 + $drawdownGrowthRate) - $dcDrawdown;
```

Once the need exceeds the fund, `$dcDrawdown` **is** `$remainingFund`, so the line
reduces to `$remainingFund * $drawdownGrowthRate` — the balance is multiplied by the
growth rate every year, for ever. **It approaches zero and never arrives.**

Measured on a £20,000 pot with a £45,000 target: £28,360 at 62, £567 at 63, £11.34 at 64,
and pennies thereafter. `$remainingFund <= 0` is therefore never true, so:

- `fund_depletion_age` is **always `null`**
- `fund_depleted` is **always `false`** on every yearly row
- `years_funded` falls to `$endAge - $retirementAge + 1` — **the full horizon, for every
  household**, including one whose money runs out at 64

The same shape is in `projectIncomeDrawdown()`, where it is not a defect: that path
withdraws a sustainable percentage of the remainder by design, so a fund that never
depletes is the model working.

**Who sees it.** `years_funded` and `fund_depletion_age` are published on the retirement
projection, so a household is told its money lasts to 100 when the engine's own figures
say it is spent at 64. That is the wrong direction for a planning tool to be wrong in.

## Acceptance

1. A fund is treated as depleted when it can no longer meet the year's need — a
   threshold, not `<= 0`. State the threshold and why at the line.
2. `fund_depletion_age`, `fund_depleted` and `years_funded` all agree with it.
3. A test with a pot that cannot fund the target: depletion age is the year the money
   stops covering the need, not `null`.
4. Check whether any surface renders `years_funded` as a reassurance before changing it —
   the figure moves DOWN for affected households and that is a real change to what they
   are told.

## Working notes

- 2026-08-28 — Found while wiring the estate's unused-fund figure to this engine
  (W-0482). **It does not affect the estate figure**: W-0482 reads `remaining_fund`
  directly, and a residual of £2.31 is correctly nothing in an estate. The test there
  asserts "under £1" rather than exactly zero, with this item cited as the reason.
- 2026-08-28 — `min()` is the right operator; the reporting around it is what is wrong.
  Do not "fix" this by letting the fund go negative.
