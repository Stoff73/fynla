---
id: W-0510
title: A drawn-out pension fund never reports as depleted, so "years funded" is always the full horizon
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [quality-lead]
status: done
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

## 2026-09-01 — CLOSED

**Acceptance 4 first, because it decides whether the rest may land.** Nothing renders
`years_funded` — grep across `resources/js` and `resources/mobile` returns no consumer,
so no household is currently being reassured by it. `fund_depletion_age` IS rendered:
`FutureValueTab.vue:94` shows "Fund lasts until age N" for this path and
`PensionList.vue:332` warns for the other one. The first of those has **never
displayed**, because the value was always null. So the change makes a true warning
appear where a silent screen used to be, which is the safe direction.

**Acceptance 1 — a threshold that is a definition, not an epsilon.**
`RetirementProjectionService.php:677-700`: depleted is now
`$dcNeeded > 0 && $remainingFund < ($dcNeeded - 1.0)` — *cannot meet this year's need* —
with the reasoning at the line. The pound is not float tolerance: these figures are
published to the penny, and a household short by less than a pound in a year has not run
out of money.

`projectIncomeDrawdown()` is deliberately untouched and the docblock says so. It
withdraws a sustainable percentage of the remainder by design, so a fund that never
depletes is that model working rather than the same defect wearing the same clothes.

**Acceptance 2 — the three figures now agree**, and a test asserts it directly: the
first row flagged `fund_depleted` carries the same age as `fund_depletion_age`, and
`years_funded` is that age less the retirement age.

**Acceptance 3 — and the test that should have caught this was a decoy.**
`RetirementProjectionServiceTest.php` had *"tracks fund depletion age correctly"*, whose
only assertion sat inside `if ($result['fund_depletion_age'] !== null)`. The value was
always null, so the branch never ran and the test went green over the defect it is named
after. **Corrected rather than deleted**, with the reasoning at the line, and joined by
two more: one pinning the three figures agreeing, and one in the other direction — a
£5,000,000 pot reports no depletion — so the fix cannot be "always report depletion".

Tests: 18 passed on that file; **446 passed** across Retirement / Projection / Drawdown.

**Not done:** no browser drive of the newly-appearing depletion badge, and no
re-measurement against a live persona.
