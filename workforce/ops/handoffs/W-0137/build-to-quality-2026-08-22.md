# W-0137 and W-0188 — build-lead (`cycle2-projection`) → quality-lead

One handoff for two items because they were one mechanism and one code change.
Branch document: `branches/fixes/F-0018-cycle2-projection.md`.
Duplicated to `handoffs/W-0188/` so both items resolve.

## Done

**W-0137 — the projection cannot produce money that does not exist.** Cash draws
down to £0 and stops. What used to accumulate as a minus-£1.8m asset line is returned
as a positive `projected_cash_shortfall` and rendered on the drill-down as "£X of
spending your cash cannot cover". The old line printed a *negative* number beside the
word "shortfall".

**The per-account negatives needed no separate fix.** `IHTFormattingService` derives
every cash line as `projected_cash ÷ current_cash_total × balance`, so a negative
household total made every account negative pro rata. Fixing the household figure
closed the −£854,179 Cash ISA with it.

**W-0188 — closed by W-0137, as `persona-passA3` predicted.** No separate fix was
made, and that is recorded rather than assumed: the £103,206 was reproduced to the
pound before anything was touched, and **all of it sat in `projected_cash`** —
investments, property and liabilities already matched across the two logins.

The mechanism was one loop. `calculateProjectedValues()` took `$currentAge` from the
**logged-in** user and `$estimatedAgeAtDeath` from **whichever spouse dies second, in
that spouse's own age frame**. Sarah dies second, so from her login the loop covered
the true 36 years and from David's it covered 35 — one extra pre-retirement year of
household surplus. The horizon is now years-from-now, which is a property of the
household.

**Bundled with them, because the floor alone lands on a plausible wrong answer:**
R1, R2, R5, R6 and R7 from `F-0004`'s `## UNTOUCHED` headings. Pensions are income
now (`PensionProjector`); both phantom columns are gone; the hardcoded 0.50 reads
`retirement.target_income_percent`; the retirement-age default matches the pension
module's 67; and the estate can finally see `users.life_expectancy_override`. Full
detail in the branch document, §6.

**And one defect the fix uncovered, which was the largest figure in the projection.**
Retirement expenditure was keyed to income while pre-retirement expenditure was keyed
to recorded expenditure, so a household recording £29,400 a year of spending was
projected to spend £216,127 a year in retirement. That alone was a £3,434,584
shortfall. Recorded expenditure now sits ahead of the ratio in the fallback chain.

**Rule 20.** Four implementations of this one behaviour are now one:
`app/Services/Estate/HouseholdCashFlowProjector.php`. The fourth was
`IHTFormattingService::generateCashProjectionBreakdown()` — the year-by-year table
the user reads *underneath* the headline, and a full parallel model with no inflation,
no life events and no floor. The table whose only purpose is to explain the headline
was arithmetically incapable of adding up to it.

**Measured read-only against users 16 and 17** (no writes; `persist` defaults false
and nothing writes `iht_calculations` today):

| | David before | Sarah before | David after | Sarah after |
|---|---|---|---|---|
| projected cash | −£2,957,895 | −£2,854,689 | **£152,401** | **£152,401** |
| projected net estate | £4,389,097 | £4,492,303 | **£7,499,393** | **£7,499,393** |
| projected tax | £1,547,639 | £1,588,921 | **£2,791,757** | **£2,791,757** |
| current-year net / tax | £1,716,780 / £338,712 | identical | £1,716,780 / £338,712 | **unchanged** |

Year-by-year table, both logins: 36 rows, **zero** negative running totals, final row
£152,401 — equal to the headline.

**Tests:** 23 new (`tests/Unit/Services/Estate/ProjectedHouseholdCashFlowTest.php`,
123 assertions), all green on `DB_DATABASE=laravel_testing_a`. Estate unit suite 284
green; API, coordination and agents 705 green. Pint clean, imports verified after it
ran.

## What you need that is not obvious

**`estimated_age_at_death` is now a LABEL, and the two logins deliberately show
different ages.** It is the viewer's own age at the household horizon: David 85,
Sarah 84, same 36-year projection. W-0137's repro says "the age-84 column" — David's
column now reads **85**. **This is the fix working, not a regression.** Please brief
the tester before they read the screen.

**The projected estate roughly doubles, and that is arithmetic, not inflation of the
answer.** A minus-£2.9m cash line was being subtracted from the estate. Cash cannot
be negative, so removing the impossible negative makes the estate larger. The tax
figure follows.

**Several tests read `projected_cash_shortfall` rather than `projected_cash`.** A
household modelled to outspend its means sits on the floor at zero and cannot move,
so the cash balance proves nothing there; the unmet expenditure above the floor is
the same fact measured where it is still visible. If you extend the suite, that is
the technique.

**New payload keys:** `projected_cash_shortfall`, `projected_cash_assumptions`,
`inflation_rate` on the calculation; `shortfall`, `assumptions`, `final_cash`,
`state_pension_income` on `cash_projection_breakdown`. **Removed:** `final_cash_raw`,
`state_pension_user`, `state_pension_spouse`. Repo-wide grep confirms no remaining
consumer of the removed keys in `app/`, `resources/`, `tests/` or `ios-native/`.

## Assumptions made

- **The State Pension age falls back to `pension.state_pension.current_spa` (66), not
  `future_spa` (67).** Matching the canonical resolver rather than inventing a third
  answer. For a projection decades out `future_spa` is arguably right. Raised as
  **W-0197** — a decision, not a defect.
- **A stated `target_retirement_income` is treated as what the household intends to
  SPEND, not as income it will receive.** Previously it was both at once, so the two
  cancelled to a zero surplus. It is a target, not a pension.
- **Private pension income is deflated to today's money** before entering a loop that
  inflates every year, using the member's own years to retirement. Where individual
  schemes carry a different retirement age that is an approximation, stated in the
  docblock.
- **A shortfall does not draw on investments.** Raised as **W-0199**; zero shortfall
  for this persona household, so nothing is presently wrong on screen.

## Not done, and why

- **Not browser-verified.** Build does not write its own evidence and does not close
  its own loop (`08-process.md` §2.4). W-0137 acceptance 5 and W-0188 acceptance 3
  are yours.
- **Not committed, no PR, no deploy, no bundle rebuilt**, per dispatch.
- **W-0196, W-0197, W-0198, W-0199 raised, not built** — see the branch document §11.

## The projected total, reconciled from its parts

Asked for before sign-off, because the figure moved twice in one session.

| Class | Today | Projected | Implied annual |
|---|---:|---:|---:|
| Cash | £130,780 | £152,401 | — (a flow model, not a rate) |
| Investments | £305,000 | £2,603,695 | 6.14% |
| Property | £1,393,000 | £4,550,297 | 3.34% |
| Chattels | £193,000 | £193,000 | 0.00% (held flat by design) |
| **Sum of parts** | | **£7,499,393** | |
| **Published** | | **£7,499,393** | |
| **Difference** | | **£0.00** | |

```
net      = 7,499,393 − 0 liabilities                          = 7,499,393  ✓
taxable  = 7,499,393 − 500,000 allowances − 20,000 charitable = 6,979,393  ✓
tax      = 6,979,393 × 0.40                                   = 2,791,757  ✓
```

**The pre-fix figure reconciles the same way, and that is the part that matters:**

```
before:  −2,957,895 + 2,603,695 + 4,550,297 + 193,000 = 4,389,097  ✓
after:      152,401 + 2,603,695 + 4,550,297 + 193,000 = 7,499,393  ✓
delta = 3,110,296, which is exactly the cash line moving off the floor
```

Investments, property, chattels and liabilities are **byte-identical** before and after.
Nothing was traded for anything.

**Two parts reconcile but rest on a wrong base — neither is this item, both are larger
than it, and both are now on the board:**

- **W-0216** — the property projection counts a 40%-owned tenants-in-common property at
  **100%**. £177,000 of a third party's property today, **£512,995 at the horizon and
  £205,198 of tax.** The growth factor is 2.89828, which is `1.03^36` exactly: the rate
  is right and the base is wrong. Projected property should be £4,037,302. **The
  current-year column is correct** — it reads the aggregator — so only the projection
  carries it.
- **W-0217** — the investment Monte Carlo turns **£85,000** at `medium` risk into
  **£1,577,731** at the twentieth percentile, while **£220,000** including an
  `upper_medium` account reaches **£1,025,964**. No contributions on either side. **The
  lower risk preference produces the higher return**, at the median as well as at p20.
  Roughly £630,000 of projected tax rests on it.

**Neither is visible from the total, which reconciles to the penny.** Both are visible
only from the implied growth rate — which is the argument for reconciling from parts
rather than accepting a total that adds up.

**Tester-facing:** `MonteCarloSimulator` caches, so `projected_investments` is stable
across runs today. **Clearing that cache may move it, and the estate and tax with it.**
The per-login agreement does not depend on the cache — both members' portfolios are
summed regardless of who is signed in — but the magnitude can move.

## No writes — confirmed, not assumed

`iht_calculations` holds **zero rows in the whole table**, before and after. Every
`calculate()` run here persisted nothing.

**Observed and not mine:** `users.updated_at` on 16 and 17 both read 2026-08-22
07:01:16 / 07:01:17. Something wrote to that household during this session; it was not
this agent, and it changed none of the figures above.

## Not mine, seen in this tree

Neither was touched; both files belong to agents that are live.

- `app/Services/UserProfile/UserProfileService.php:769` — `Undefined variable $userId`
  in a closure missing `use ($userId)`. Fails `PlanExpenditureComposition…` ×3.
- `app/Observers/PropertyRentalIncomeObserver.php:7` — store-boundary architecture
  failure, `App\Models\Property` used directly in an observer.
