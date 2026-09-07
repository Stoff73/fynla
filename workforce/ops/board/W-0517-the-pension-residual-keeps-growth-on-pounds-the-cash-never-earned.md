---
id: W-0517
title: The unused pension residual keeps investment growth on pounds that were withdrawn and sat in cash earning nothing
mission: persona-run-peak_earners-2026-08-20
branch: fix/w-0512-w-0517-depleting-pension-drawdown
owner: null
reviewers: [tax-compliance-reviewer]
status: done
closed: 2026-08-29
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-28T19:15:00Z
claimed: 2026-08-29T09:00:00Z
blocked_by: []  # fixed WITH W-0512 in one change, as the item required
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

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`in_progress`.

- **Delivered by:** Stoff73
- **Evidence:** merged in #746; commit `32707026f` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
