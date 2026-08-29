---
id: W-0512
title: The projected cash flow pays defined contribution pension income as a perpetuity, from a fund it never reduces
mission: persona-run-peak_earners-2026-08-20
branch: fix/w-0512-w-0517-depleting-pension-drawdown
owner: null
reviewers: [tax-compliance-reviewer]
status: in_progress
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-28T15:30:00Z
claimed: 2026-08-29T09:00:00Z
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

## Resolution — 2026-08-29

**Fixed together, by one loop, as both items required.**

`RetirementProjectionService::projectSafeWithdrawalDrawdown()` carries the fund forward a
year at a time: it grows, pays the intended withdrawal, and is reduced by it. The cash flow
reads `income_by_age`; the estate reads `fund_by_age`. Two ends of one series, so the income
the household is shown spending and the fund the estate is taxed on can no longer disagree
about whether the money was withdrawn (Rule 20).

**Why one loop closes both items.** Carrying the fund forward as `fund × (1 + g) − drawn` is
algebraically `grownFund − Σ drawn_t × (1 + g)^(yearsRetired − t)` — exactly the
future-valued complement W-0517 specified — and it additionally stops paying when the fund
reaches zero, which is what W-0512 specified. Neither could be fixed alone without being
struck against a figure the other had not corrected.

**Before / after** — £500,000 pot, age 45, retiring at 65, modelled death at 85:

| figure | before | after | change |
|---|---|---|---|
| `projected_cash` | £895,239.99 | £841,096.95 | **−£54,143.04** |
| `projected_unused_pension` | £186,973.74 | £128,361.15 | **−£58,612.59** |
| `projected_net_estate` | £1,082,213.73 | £969,458.10 | **−£112,755.63** |
| `projected_iht_liability` | £302,885.49 | £257,783.24 | **−£45,102.25** |

Both move DOWN, as both items required. The tax delta reconciles exactly: £112,755.63 × 40%
= £45,102.25.

**Decisions taken, and stated rather than left to be found:**

- **The two drawdowns stay separate methods.** `projectTargetIncomeDrawdown()` answers "how
  long does the fund last if I draw what I NEED"; the new one answers "what can the fund
  actually pay of what it was MEANT to pay". Same fund, different questions. W-0512
  acceptance 2 allowed either "one goes or one reads the other" — neither is a subset of
  the other, so what is shared is the depleting model, not the method.
- **The withdrawal order differs from `projectTargetIncomeDrawdown()`, deliberately.** That
  one caps the draw at the PRE-growth balance, which strands `fund × growth` in an account
  it has just called exhausted — and the estate is then taxed on a residue nobody could have
  withdrawn. The new loop grows first and caps the draw at what the fund is then worth, so
  it genuinely reaches zero. Only the depletion boundary differs; while the fund can pay in
  full the two orders give the same withdrawal.
- **`unusedDcFundAtAge()` is now a lookup, not arithmetic** — W-0512 acceptance 3 asked for
  a decision either way. It reads `fund_by_age[$ageAtDeath]`. `credited` and `grown_fund` are
  still published as context, but `amount` is deliberately no longer `grown_fund - credited`.
- **The money-basis trap is closed at the call site.** The drawdown is entirely nominal; the
  cash projector works in today's money and re-inflates each year. The series is deflated by
  that same power on the way out, so the multiplication restores the drawdown's own nominal
  figure. This is also why the change is behaviour-preserving until the fund runs dry: while
  it can pay in full, the deflated series equals the flat figure it replaced, term for term.
- **A member whose pot cannot be projected keeps the old flat figure.** Falling through to
  zero would silently remove income the previous model credited.

**Verification.** 895 passed across `tests/Unit/Services/Estate`, `tests/Feature/Estate`,
`tests/Unit/Services/Retirement`, `tests/Feature/Retirement` and `tests/Architecture`.
Five tests are **mutation-verified** — all five fail against the pre-fix code.

**Not browser tested.** W-0512 acceptance 5 asks what moves on screen: the year-by-year cash
flow table now shows household income STEPPING DOWN at the depletion age instead of running
flat, and every projected estate figure below it falls. That is the intended change and it
is visible to the user, so it is worth a look before release.
