---
id: W-0512
title: The projected cash flow pays defined contribution pension income as a perpetuity, from a fund it never reduces
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-28T15:30:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-28
prior_art_found: [W-0482, W-0363]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer gate report on W-0482, finding F1, 2026-08-28
---

## Intent

`HouseholdCashFlowProjector:367` reads `PensionProjector::projectTotalRetirementIncome()`,
and `PensionProjector:219`:

```php
$safeWithdrawalRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.safe', 0.04);
$dcAnnualIncome = $totalDCValue * $safeWithdrawalRate;
```

**That is a perpetuity.** The fund is never reduced. `HouseholdCashFlowProjector:156`
credits the figure every retired year, inflated, from retirement to the modelled death —
thirty years or more — out of a pot that the model never draws down.

So the projected cash flow can pay out more than the pension holds, and the surplus
accumulates into `final_cash` → `projected_cash` → the projected estate. **It overstates
projected cash for every household with a defined contribution pension**, whether or not
the estate ever includes the fund itself.

W-0482 works around it rather than fixing it: the estate's unused-fund figure is struck as
the grown fund less the income already credited, floored at zero, so the pension never
contributes more than it holds. That stops the double count in the pension term. **It does
not stop cash being credited an income the fund could not have paid.**

## Acceptance

1. The retirement income the cash flow credits comes from a model that reduces the fund it
   is paid from.
2. **One mechanism** (Rule 20). `RetirementProjectionService::projectTargetIncomeDrawdown()`
   already models a depleting drawdown per year; `PensionProjector`'s 4% is a second
   answer to the same question. One of them goes, or one reads the other.
3. Once the income is depleting, W-0482's complement can read the drawdown directly
   instead of subtracting — check whether `unusedDcFundAtAge()` should be simplified in
   the same change, and say either way.
4. Before/after on `projected_cash` and `projected_iht_liability` for a household with a
   defined contribution pension. Both move DOWN; state by how much.
5. The year-by-year cash flow table the user reads is driven by the same projector — check
   what moves on screen before changing it.
6. `tax-compliance-reviewer` — it moves the projected estate and therefore projected tax.

## Working notes

- 2026-08-28 — Raised as F1 by the gate on W-0482. Note the money basis trap: the cash
  projector works in today's money and applies its own inflation multiplier per year,
  while the drawdown engine works in nominal terms and inflates the target internally.
  Reading one from the other without reconciling the basis is a silent factor error.
