---
id: F-0018
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-22T00:00:00Z
status: active
---

# F-0018 — Cycle 2: the estate projection

**Agent:** build-lead (`cycle2-projection`) · **Written:** 2026-08-22
**Branch:** `dev` (shared working tree — nothing committed, no PR, no deploy, by instruction)
**Numbers issued by team-lead:** item block **W-0196–W-0199**.

| Item | Status | Notes |
|---|---|---|
| W-0137 projected cash goes to minus £1.8m | **DONE** | Floor + one home. Ready for `handoff` → quality-lead |
| W-0188 two logins project estates £103,206 apart | **DONE — closed by W-0137** | Same mechanism. No separate fix was needed |
| R1/R2/R5/R6/R7 (from F-0004 `## UNTOUCHED`) | **DONE** | All five landed in the same consolidation |
| W-0196 seven retirement-age defaults, four copies of the chain | **RAISED** | Not built — four live services |
| W-0197 `current_spa` against `future_spa` for a forward projection | **RAISED** | Needs a decision, not a fix |
| W-0198 two columns hold one life expectancy | **RAISED** | Data-model decision |
| W-0199 a cash shortfall never draws on investments | **RAISED** | Modelling decision for CSJ |

**Not self-certified.** No evidence pack here — Quality writes that (`08-process.md` §2.4).
**Not browser-verified**, by instruction: the tester closes that loop next cycle.

---

## 0. The sentence that matters most

> **The projection contradicted the household's own data and then reported the
> contradiction as their financial future.**

David Jones records spending **£29,400 a year**. The projection had him spending
**£216,127 a year** in retirement — seven times his own stated figure — purely because
the household income is large.

The asymmetry underneath it is the real defect, and it is not a typo or a bad constant:
**pre-retirement expenditure was keyed to what the household recorded, retirement
expenditure was keyed to their income.** One model, two epistemologies, and the switch
between them happened silently at retirement, where no one would look for it.

The ordering that replaces it, and the reasoning worth keeping past this item:
**a rule of thumb is evidence about people in general; a recorded figure is evidence
about this person, and it wins.**

That alone was a **£3,434,584** shortfall — larger than the two defects this branch was
dispatched to fix, and invisible until the floor and the pension income were in place.
Full detail in §7.

---

## 0b. The parts reconcile to the total — exactly

Asked for by team-lead before sign-off, because the figure moved twice in one session
and every input to it has been wrong at some point.

**Horizon 36 years. Household assumptions: inflation 2%, property growth 3%,
investments Monte Carlo (no user overrides).**

| Class | Today | Projected | Implied annual |
|---|---:|---:|---:|
| Cash | £130,780 | £152,401 | — (a flow model, not a rate) |
| Investments | £305,000 | £2,603,695 | 6.14% |
| Property | £1,393,000 | £4,550,297 | 3.34% |
| Chattels | £193,000 | £193,000 | 0.00% (held flat by design) |
| Business | £0 | £0 | — |
| **Sum of parts** | | **£7,499,393** | |
| **Published `projected_gross_assets`** | | **£7,499,393** | |
| **Difference** | | **£0.00** | |

And every step below it:

```
net      = 7,499,393 − 0 (liabilities amortised to zero)      = 7,499,393  ✓ published
taxable  = 7,499,393 − 500,000 allowances − 20,000 charitable = 6,979,393  ✓ published
tax      = 6,979,393 × 0.40                                   = 2,791,757  ✓ published
```

**The pre-fix figure reconciles the same way, which is what proves the change is the
cash and nothing else:**

```
before:  −2,957,895 + 2,603,695 + 4,550,297 + 193,000 = 4,389,097  ✓ published
after:      152,401 + 2,603,695 + 4,550,297 + 193,000 = 7,499,393  ✓ published
delta    = 152,401 − (−2,957,895)                     = 3,110,296
         = 7,499,393 − 4,389,097                      = 3,110,296  ✓
```

**Investments, property, chattels and liabilities are byte-identical before and after.
The entire £3,110,296 movement is the cash line, and it is the removal of impossible
negative money.** Nothing was traded for anything.

### Two parts that reconcile but are built on a wrong base

Raised, not fixed — **both are outside this item and both are larger than it.** See §11.

- **Property implies 3.34% against a configured 3.00%.** The rate is right; the base is
  wrong. `projectProperties()` sums `current_value` at **full value**, so a
  tenants-in-common property where this household owns **40%** enters the projection at
  **100%**. £177,000 of a third party's property today, **£512,995 at the horizon and
  £205,198 of tax.** Raised as **W-0216**. (The growth factor is 2.89828, which is
  `1.03^36` exactly — the configured rate, on a wrong base.)
- **Investments imply 6.14% at the twentieth percentile.** Sarah's single
  medium-risk ISA of **£85,000** projects to **£1,577,731**; David's **£220,000** across
  three accounts projects to **£1,025,964**. Neither account has any contributions.
  **39% of the capital produces 61% of the projected value, at the conservative
  percentile, from the lower risk preference.**

---

## 1. Prior art

`prior_art_checked: 2026-08-22` · `prior_art_found: [W-0135, W-0136, W-0137, W-0154,
F-0004]` · `prior_art_outcome: extend`

Six sources. What it found, and what it changed:

- **`PensionProjector::projectTotalRetirementIncome()` already owns "what pension
  income will this person have".** It projects Defined Contribution pots, converts
  them at `retirement.withdrawal_rates.safe`, revalues Defined Benefit pensions, and
  reads the State Pension from the column that exists. **Routed to it** rather than
  writing a third pension-income reader.
- **`FutureValueCalculator::getLifeExpectancy(User)` already honours the override.**
  Routed to it.
- **`RetirementIncomeService::getStatePensionStatus()`** is the canonical State
  Pension age resolver. Matched its configuration key rather than choosing a
  different one — see W-0197.
- **Four implementations of the estate's own cash projection.** Extended into one;
  see §3.
- `App\Services\Estate\CashFlowProjector` — **not** the same question (one person,
  one tax year, five years, no household, no retirement phase, no horizon). Left
  alone; the new class is named for the household to keep them apart.
- `.claude/skills/` and `.claude/agents/` — nothing covering estate projection.

---

## 2. What was measured, before and after

Read-only, on users 16 and 17, via the real `IHTCalculationService::calculate()`
(`persist: false` by default, and nothing writes `iht_calculations` today — W-0131).
**No writes to either persona.**

| | David before | Sarah before | Apart | David after | Sarah after | Apart |
|---|---|---|---|---|---|---|
| projected cash | −£2,957,895 | −£2,854,689 | £103,206 | **£152,401** | **£152,401** | **£0** |
| projected net estate | £4,389,097 | £4,492,303 | £103,206 | **£7,499,393** | **£7,499,393** | **£0** |
| projected tax | £1,547,639 | £1,588,921 | £41,282 | **£2,791,757** | **£2,791,757** | **£0** |
| current-year net / tax | £1,716,780 / £338,712 | identical | — | £1,716,780 / £338,712 | identical | **unchanged** |

The £103,206 reproduced to the pound before anything was touched, and **all of it sat
in `projected_cash`** — investments, property and liabilities already matched across
the two logins. That is what identified the mechanism.

Year-by-year table, both logins after: 36 rows, **zero rows with a negative running
total**, final row £152,401 — equal to the headline `projected_cash`.

---

## 3. The four implementations, and why one home was the fix

| Where | State | What it did differently |
|---|---|---|
| `IHTCalculationService::projectCashWithInflation()` | **live** | No floor. Last line said so: *"Cash can go negative"* |
| `IHTCalculationService::projectCashAccounts()` | dead | Floored the final answer |
| `IHTCalculationService::projectCashAndInvestmentsIntegrated()` | dead | Floored every year AND drew the deficit from investments — W-0137 acceptance 2, already written, with no caller |
| `IHTFormattingService::generateCashProjectionBreakdown()` | **live** | No inflation, no life events, no floor, hardcoded 68/67/85, income list missing trust income, raw `0.50`, and both phantom columns again |

**The consequence was worse than any single defect.** The table whose only purpose is
to explain the headline was produced by a different model from the headline, so it was
arithmetically incapable of adding up to it, whatever either of them said. Rule 20:
consolidating is part of the fix.

All four are replaced by **`app/Services/Estate/HouseholdCashFlowProjector.php`**,
read by `IHTCalculationService` for the total and by `IHTFormattingService` for the
rows. `getInvestmentAccountsArray()` and `getMonteCarloAnnualRate()` were deleted with
them: their only caller was one of the dead projectors.

**On adopting the dead one, since team-lead cautioned against it.** What was
established is that both had **zero callers repo-wide** — not *why*, which git history
was not mined for. That uncertainty drove the choice rather than being set aside:
`projectCashAndInvestmentsIntegrated()` does **more** than floor, and adopting its
deficit-drawing would have moved `projected_investments` — a figure nobody asked to
change and which W-0135 and W-0136 both read. **The floor was taken; the
deficit-drawing was not**, and it is raised as W-0199 instead.

**Proof it was not adopted by accident:** `projected_investments` reads **£2,603,695**
before this work and **£2,603,695** after, on both logins. The investment line did not
move by a penny.

---

## 4. W-0137 — the floor

Cash draws down to £0 and stops. Unmet expenditure accumulates as a **positive**
`projected_cash_shortfall`, published on the calculation and rendered on the
drill-down.

The per-account negatives needed no separate fix. `IHTFormattingService` derives every
cash line as `projected_cash ÷ current_cash_total × balance`; a negative household
total made every account negative pro rata. Fixing the household figure closed the
−£854,179 Cash ISA with it.

The old drill-down already had a line for this and printed a **negative** number
beside the word "shortfall". It now prints a positive amount of spending the cash
cannot cover.

---

## 5. W-0188 — one line, not a second defect

`calculateProjectedValues()` took `$currentAge` from the **logged-in** user and
`$estimatedAgeAtDeath` from **whichever spouse dies second, in that spouse's own age
frame**, then walked `for ($age = $currentAge; $age < $deathAge; $age++)`.

Sarah dies second. From her login the loop covered the true 36 years; from David's,
35. One extra pre-retirement year of household surplus — £103,206. It scaled with the
household exactly as W-0188 recorded (£88,257 → £103,206) because it is one year of
surplus and the surplus grew as the data went in.

The horizon is now years-from-now, which is a property of the household:
`max()` of the two life expectancies is the same from either side.

**`estimated_age_at_death` survives only as a label — the viewer's own age at the
household horizon — so the two logins now show DIFFERENT ages against the SAME
projection. That is correct; they are not the same age.** W-0137's repro says "the
age-84 column"; David's now reads 85. **Tell the tester before they read it as a
regression.**

---

## 6. R1, R2, R5, R6, R7 (F-0004 `## UNTOUCHED`)

**R1 — pensions are income.** `PensionProjector::projectTotalRetirementIncome()`.
David's two pots → **£47,391 a year**; Sarah's scheme → **£35,000 a year**. Both
contributed exactly nothing before.

#### Technique — one money basis, and know which one every figure is already in

**Figures arriving from another module are not necessarily in the same money as the
loop consuming them, and nothing in a float says which.**

`projectTotalRetirementIncome()` returns **nominal at retirement** — a pot grown at its
own rate to a future date. The estate loop carries **today's money** and inflates once
per year. Feeding one into the other inflates it a second time for the whole of
retirement, and the result looks entirely plausible: a large number where a large
number belongs.

So private pension income is deflated by `(1+inflation)^yearsToRetirement` before it
enters the loop. **The State Pension forecast is not**, because it is already a
today's-money figure — the same array, two different bases, which is exactly the trap.
The deflation uses the member's own years to retirement; where individual schemes carry
their own retirement age that is an approximation, and the docblock says so.

**Generalises as:** whenever a projection consumes a figure it did not compute, state
the basis of both — nominal or real, gross or net, per-person or per-household — and
convert at the boundary, once, in the open. **A double-inflated figure never looks
wrong.** This paragraph is here because it is precisely the kind of thing a later
"simplification" deletes as an unnecessary division.

**Routing also sidestepped the derived-column trap** F-0004 flagged:
`projectDBPension()` *derives* the revalued figure from `accrued_annual_pension`
rather than reading `projected_annual_pension_at_nra_gbp`, so the null-derived branch
never arises on this path.

**R2 — both phantom columns gone.** `estimated_annual_amount` →
`state_pension_forecast_annual` via the projector. `users.state_pension_age` → the
member's own `state_pensions.state_pension_age`, then configuration.

**The absence is loud.** A missing figure cannot be distinguished from a real zero
inside a float, so the projection publishes `projected_cash_assumptions` — plain
sentences, rendered on the drill-down, e.g. *"No State Pension forecast is recorded
for David, so the projection includes no State Pension income for them. This is a gap
in the record, not an entitlement of nothing."* **Rendered, not merely published:** an
unread field is the `nrb_deduction` disease F-0004 warned about.

**R5 — one answer.** The hardcoded `0.50` is gone (there were two of it — a constant
and a raw literal three lines below, in the same method). The projector reads
`retirement.target_income_percent`, confirmed present by dumping it, and `report()`s a
`RuntimeException` naming the key before falling back to
`TaxDefaults::RETIREMENT_TARGET_INCOME_PERCENT` — the
`HouseholdPlanningService::inheritanceTaxRate()` precedent.

**R6 — the 68 is gone.** `PensionProjector::DEFAULT_RETIREMENT_AGE` is now public and
the estate reads it. Six more copies remain — W-0196.

**R7 — the override is visible.** `calculateLifeExpectancy()` called
`getLifeExpectancyYears(int $age, string $gender)`, the one method on
`FutureValueCalculator` that never receives the user and therefore *cannot* see
`life_expectancy_override` however it is written. It calls `getLifeExpectancy(User)`
now. Two columns still hold the fact — W-0198.

---

## 7. The defect the fix uncovered, and it was the largest figure in the projection

With the floor in and pensions counted, the projection still showed a **£3,434,584
shortfall**.

**Retirement expenditure was keyed to income while pre-retirement expenditure was
keyed to recorded expenditure.** David records spending **£29,400 a year** and was
projected to spend **£216,127 a year** in retirement — seven times his own stated
figure — purely because the household income is large. The projection contradicted the
household's own data and then reported the contradiction as their financial future.

The chain is now: recorded retirement budget → stated target retirement income →
**what they actually spend today** → the configured share of income. A rule of thumb
is evidence about people in general; a recorded figure is evidence about this person.

Household retirement spending **£126,060** against **£65,712** of pension income; cash
peaks at £2,275,770 at retirement and ends at **£152,401 with a £0 shortfall**.

---

## 8. Tests — `tests/Unit/Services/Estate/ProjectedHouseholdCashFlowTest.php`

23 tests, 123 assertions, all green. **`DB_DATABASE=laravel_testing_a`.**

**Nothing here asserts a value the code was told.** Where the job is to prove a value
is read from configuration, the configuration is **varied** and the answer required to
follow; where the job is to prove an input is read at all, the input is **added** and
the answer required to move. A literal does not move, whatever a double returns. This
is the countermeasure F-0004 named after `inheritance_tax.rate`.

### Technique — measure the fact where it is still visible

Several movement tests read `projected_cash_shortfall` rather than `projected_cash`.

**A household modelled to outspend its means sits on the floor at zero and cannot
move, so the cash balance proves nothing there; the unmet expenditure above the floor
is the same fact measured where it is still visible.**

The shape generalises past this item: **whenever a fix introduces a clamp, the clamped
figure stops being a usable probe.** Every assertion written against it passes for the
wrong reason — not because the input was read, but because the output cannot vary. Look
for the quantity the clamp discards and assert on that instead. Here it was the
shortfall; elsewhere it will be an overflow, a capped allowance, a truncated count, or
the residual of a `max()`. A green test against a clamped value is the same class of
defect as a green test against a hardcoded literal: **nothing in it can fail.**

The harm, pinned:

- no projected balance is negative, on the total or on any row of the table;
- both logins project the same cash, gross assets, net estate, taxable estate and tax;
- the two logins report the same horizon and **different** ages at it;
- a Defined Contribution pot, a Defined Benefit pension and a State Pension forecast
  each move the answer, and the pot moves it **in proportion to its size**;
- the State Pension starts at the recorded State Pension age;
- retirement spending follows `retirement.target_income_percent` when it is varied;
- recorded expenditure beats the ratio;
- the retirement-age default equals the pension module's, asserted as the **agreement**
  rather than as the number;
- the life expectancy override moves both the horizon and the projected estate;
- the year-by-year table ends on the headline figure, has `years_to_death` rows, and is
  identical from either login.

### Suites run

| Suite | Result |
|---|---|
| `tests/Unit/Services/Estate/` | **284 passed** |
| `tests/Feature/Estate`, `Unit/Services/Plans`, `Unit/Services/Retirement`, `Unit/Services/Coordination` | 405 passed, **3 failed — not mine**, see §10 |
| `tests/Feature/Api`, `Unit/Services/Coordination`, `Unit/Agents` | **705 passed** |
| `--testsuite=Architecture` | 148 passed, **1 failed — not mine**, see §10 |

`./vendor/bin/pint` clean on every changed file; imports verified present after it ran.

---

## 9. Surfaces (Rule 19)

- **Web** — `resources/js/components/Estate/IHTPlanning.vue`: the shortfall line now
  reads a positive `shortfall`, the retirement-income tile reads the consolidated
  `state_pension_income`, and the assumptions render in a violet panel (Rule 8), no
  icons (Rule 15), no acronyms (Rule 9), British spelling.
- **`/m`** — **no counterpart exists, and none is missing.** `resources/mobile/` renders
  the estate module *summary* (`ModuleDetail.vue`: `iht_liability`, `estate_value` —
  current-year figures) and has no projected-cash column and no year-by-year table.
  Verified: `projected_cash`, `projected_net_estate`, `projected_iht`,
  `cash_projection` return **zero** matches across `resources/mobile/` and
  `ios-native/`. Every `/m` and native figure that *is* fed by this projection comes
  from the same endpoint and inherits the fix with no client change.
- **iOS** — same as `/m`: no consumer of the projection payload.

---

## 10. Not mine, seen in this tree

Both reproduce with no involvement from anything in this branch document. **Neither
was touched** — the files belong to agents that are live.

- **`app/Services/UserProfile/UserProfileService.php:769`** — `Undefined variable
  $userId` inside a closure that omits `use ($userId)`. Fails
  `Tests\Unit\Services\Plans\PlanExpenditureComposition…` ×3. It is inside a
  `calculateUserShare()` call, so it is ownership work in flight.
- **`app/Observers/PropertyRentalIncomeObserver.php:7`** — store-boundary architecture
  failure, `App\Models\Property` used directly in an observer.

---

## 11. Raised, not built

| Id | Finding |
|---|---|
| **W-0196** | **Seven** private `DEFAULT_RETIREMENT_AGE` constants (68 in `AssumptionsService`, `GoalsProjectionService`; 67 in four retirement services) and **four independent copies of the retirement-age priority chain** with different orderings — the estate checked the retirement profile first, `RetirementProjectionService` checks the user record first. Consolidating touches four live services. |
| **W-0197** | Configuration holds `pension.state_pension.current_spa` = **66** and `future_spa` = **67**. `RetirementIncomeService` and `AssumptionsService` read `current_spa`; `PensionEstimateService` reads `future_spa` and documents it as the right key for projections. **The estate projects decades out.** Routed to `current_spa` to match the canonical resolver rather than invent a third answer. **This is a decision, not a defect fix.** |
| **W-0198** | `users.life_expectancy_override` and `retirement_profiles.life_expectancy` hold one fact. Override precedence now agrees everywhere; the **fallbacks do not** — retirement and decumulation fall back to the profile column, the estate to the actuarial tables. |
| **W-0216** | The projected estate counts a property this household owns **40%** of at **100%** — £512,995 at the horizon, £205,198 of tax. The current-year column is correct because it reads the aggregator; only the projection carries it. |
| **W-0217** | A £85,000 `medium` portfolio projects to £1,577,731 at the twentieth percentile while a £220,000 portfolio containing an `upper_medium` account reaches £1,025,964 — **the lower risk preference producing the higher return**, at both percentiles, with no contributions on either side. |
| **W-0199** | When cash runs out the model leaves investments untouched, so a household with a genuine shortfall dies "out of cash" holding an untouched portfolio. The deleted `projectCashAndInvestmentsIntegrated()` drew deficits from investments year by year, which is what a household actually does. Wiring it in couples the cash loop to the Monte Carlo investment projection and moves W-0135/W-0136's figures. **Zero shortfall for this persona household, so nothing is presently wrong on screen** — but the next household with one will see it. |

### Two more, found during the reconciliation — **W-0216 and W-0217, both on the board**

Neither is visible from the total, which reconciles to the penny. Both are visible only
from the implied growth rate, which is the argument for reconciling from parts rather
than accepting a total that adds up.

**W-0216 — the property projection counts a tenants-in-common share at 100%.**
`IHTCalculationService::projectProperties()` sums `current_value` on rows the user is
primary owner of, at **full value**. That is correct for a joint property — the spouse's
half is inside the full value, counted once — and **wrong for tenants in common with a
third party**, where the household owns a stated percentage and the rest belongs to
somebody else.

Live: property id 20, `tenants_in_common`, `ownership_percentage` **40.00**, value
**£295,000**. `EstateAssetAggregatorService` correctly returns the £118,000 share; the
projection uses £295,000. **£177,000 of a stranger's property today, £512,995 at the
horizon, and £205,198 of tax.** It is the whole of the 3.34%-against-3.00%
discrepancy: the growth rate is right — the factor is 2.89828, which is `1.03^36`
exactly — and the base is not. Projected property should be £4,037,302.

The current-year column is unaffected — it reads the aggregator. **Only the projection
carries it.** Likely adjacent to the ownership-boundary work in flight, so it should be
routed rather than picked up here.

**W-0217 — the investment Monte Carlo produces £1,577,731 from £85,000 at the
twentieth percentile.** Sarah's single `medium` risk-preference ISA of £85,000 projects to
£1,577,731 over 36 years (**8.44% a year, at p20**); David's £220,000 across three
accounts, one of them `upper_medium`, projects to £1,025,964 (**4.37% a year**). Neither
account has a contribution of any kind — `monthly_contribution_amount` is null on all
four — so this is pure growth on capital. **The smaller, lower-risk holding outgrows the
larger, higher-risk one by nearly two to one, at the conservative percentile.** That is
£1.58m of a £2.60m projected investment line and roughly £630,000 of the projected tax.

One lead, recorded as a lead and not a diagnosis: **two of David's three accounts hold
zero holdings rows**, £125,000 of his £220,000, while both accounts that do hold one sit
on the higher-growing side of every comparison. If allocation is derived from holdings
and a holdings-less account falls back to something cash-like, that would drag his
blended rate down with no risk preference being wrong. Not tested —
`InvestmentProjectionService` / `MonteCarloSimulator` is a different module and a
different item.

**A tester-facing consequence of B:** `MonteCarloSimulator` caches its results, so
`projected_investments` is stable across runs *today*. **Clearing the cache may move it,
and with it the projected estate and tax.** The per-login *agreement* does not depend on
that — `projectInvestmentsMonteCarlo()` sums both members' portfolios regardless of who
is signed in, so it is symmetric by construction — but the *magnitude* can move.

Also recorded, no id taken: `PensionProjector::getRevaluationRate()` holds hardcoded
0.025 / 0.03 / 0.02 revaluation rates; `IHTCalculationService::projectInvestments()`
(as distinct from `projectInvestmentsMonteCarlo()`) appears to have no caller.

---

## 12. Environment at stand-down

- Nothing committed. No PR. No deploy. No bundle rebuilt. No tool-schema capture.
- **No writes to users 16 or 17 — confirmed, not assumed.** `iht_calculations` holds
  **zero rows in the entire table**, before and after, so every `calculate()` run in this
  work persisted nothing. (`persist` defaults false at every call site — W-0131.)
- **Observed, not mine:** `users.updated_at` on 16 and 17 both read
  **2026-08-22 07:01:16 / 07:01:17** — something wrote to that household during this
  session and it was not this agent. It changed none of the figures measured here:
  investments, property, chattels and liabilities are byte-identical across the
  before/after readings and the entire delta is the cash line (§0b).
- No migrations. No seeder changes. `TaxDefaults` gained one additive constant.
- `laravel_testing_a` was this agent's database throughout.
