# The Retirement page for someone drawing their pension

**Item:** `todoCurrent/TODO.md` item 6.
**Date:** 2026-10-01
**Design chosen by CSJ (2026-10-01):** "Income + how long it lasts". The view is for "anyone drawing from a pension".

## 1. What is wrong today

The walk case is Pat on csjones (user 439): born 1958, retired January 2020, a £200,000 personal pension, drawing £30,000 a year. The web page (`PensionList.vue`) and `/m` (`resources/mobile/views/modules/Retirement.vue`) show a saver's page:

- "Your retirement target", worked out from income, with "Age you want to retire: Not set".
- "Will I have enough income for retirement?" with "Projected Gross Income £9,235".
- "Am I saving enough for retirement?" with "Required Capital £478,723".
- "Retirement Age 67 · Years to Go 1".
- A pot projection that ignores the £30,000 drawn each year. `RetirementProjectionService::projectPensionPot:94` uses `max(1, retirement age − age)`, and `RetirementAgeResolver` never reads `retirement_date`.

Screenshot: `.playwright-mcp/item6-before-web.png`.

## 2. Sources

| Rule or figure | Source |
|---|---|
| Pension income, including drawdown and the State Pension, is taxable income; tax is due when total income is above the Personal Allowance | [GOV.UK, Tax on your private pension contributions / Tax on pension income](https://www.gov.uk/tax-on-pension); ITEPA 2003 Part 9 Chapter 5A ([s579A](https://www.legislation.gov.uk/ukpga/2003/1/section/579A)) |
| The tax-free lump sum is not income | FA 2004 Sch 29 para 1 (already applied in `ResolvesIncome::resolvePensionIncomeInPayment`) |
| What is being received now: drawdown, State Pension once `already_receiving`, final salary pensions in payment | `ResolvesIncome::resolvePensionIncomeInPayment` (the one home, Rule 20) |
| Income Tax on the whole income | the tax engine, `TaxStrategyMath::incomeTaxNow` → `UKTaxCalculator` |
| State Pension age | `StatePensionAgeResolver` (Pensions Act 1995 Sch 4 as amended) |
| Life expectancy | `FutureValueCalculator::getLifeExpectancy`, the one place (W-0198): the user's override, then the retirement profile figure, then the [ONS National Life Tables UK 2020–2022](https://www.ons.gov.uk/peoplepopulationandcommunity/birthsdeathsandmarriages/lifeexpectancies) (`ActuarialLifeTablesSeeder`) |
| Return and volatility of the pot | the user's risk level, from `RiskPreferenceService::getReturnParameters`, as the pot projection uses today |
| "Lower outcome": 4 in 5 outcomes do better | the convention already on the page (`percentile_20`, W-0259) |
| Horizon | `retirement.projection_end_age` (100), already in tax config |

## 3. Who gets the drawing view

This is one server-side predicate, `RetirementDrawdownPosition::isDrawing(User)`. Anyone who meets any of these gets the view (CSJ: "Anyone drawing from a pension"):

- `employment_status` is `retired`, or
- `retirement_date` is on or before today, or
- any Defined Contribution pension has `annual_drawdown_income` above 0 or `has_flexibly_accessed` set.

Someone who still works and also draws from a pension gets the drawing view. Their earnings appear in the income card.

## 4. What the server works out

The block is added once to `GET /api/retirement/projections` as `drawdown_position`. Web and `/m` both read it (Rule 20). It is `null` when `isDrawing` is false.

```
drawdown_position: {
  retired_since: { date: '2020-01-01', age: 61 } | null,     // from retirement_date
  income: {
    lines: [ { key, label, amount } ],   // earnings, drawdown, State Pension, final salary in payment
    state_pension_missing: bool,          // past State Pension age and not recorded as received
    total, income_tax, national_insurance, take_home
  },
  pot: {
    value,                                // all Defined Contribution pensions
    drawing_per_year,                     // sum of annual_drawdown_income
    lasts_to_age: { middle: int|null, lower: int|null },  // null = lasts beyond the horizon
    life_expectancy: { age, source },
    income_to_last_to_life_expectancy,    // lower outcome reaches £0 at that age
    year_by_year: [...]                   // same shape PensionPotProjectionChart reads
  }
}
```

- **The pot is drawn down from today.** The Monte Carlo simulation starts from the pot's current value, with −drawdown / 12 each month, for (horizon − age) years. Once a pot reaches £0 it stays at £0: `MonteCarloEngine` floors it, which changes nothing for existing callers whose payments are positive. The drawdown is level in pounds; the caption says so.
- **"Lasts to age"** is the first year in which that outcome's pot is £0.
- **"To last to [life expectancy]"** is the level yearly income at which the lower outcome reaches £0 in the life-expectancy year. It is found by bisection on the same simulation, cached on its inputs, and rounded down to £100.
- **The income card's lines** come from `IncomeDefinitionsService`'s components and `resolvePensionIncomeInPayment`, so no figure is worked out twice.
  - Income Tax comes from the engine.
  - National Insurance applies to earnings only, from `UKTaxCalculator`.
  - Take-home is the total less both.
- **When a pension is not recorded:** past State Pension age, the card shows "State Pension: add it" when none is recorded, or "not recorded as being paid" with Update when one is recorded but not marked as paid (section 6b).

## 5. What each surface shows

The same blocks appear on web (`PensionList.vue`, "current" tab) and `/m` (`Retirement.vue`), in this order. On `/m` the figures come from the same block.

1. **Retired since January 2020, at 61.** This line is left out when `retirement_date` is not recorded.
2. **Your income this year:** the lines, Income Tax, National Insurance (only when there are earnings), and take-home.
3. **How long your pension lasts:**
   - "Drawing £30,000 a year from £200,000"
   - "Middle outcome: lasts to age X"
   - "Lower outcome (4 in 5 do better): lasts to age Y"
   - "Life expectancy: Z (Office for National Statistics)", or "(your figure)" when the user set it
   - "To last to Z: about £W a year"
   - A pot that lasts beyond the horizon reads "lasts beyond 100".
4. **The pot chart** drawn down from today to the horizon, with the same bands and chart component as today. The caption reads: "Drawing the same £30,000 each year, at your [risk] risk level's returns."

The drawing view hides these:

- the target card;
- "Will I have enough income…" and "Am I saving enough…";
- "Retirement Age / Years to Go";
- the target form on `/m`;
- the old "fund depletes at age" warning, which the new card replaces.

The pension cards, "Add Pension", "Upload Statement" and the other tabs stay.

## 6. Also fixed in the path

- **`RetirementAgeResolver`** reads `retirement_date` first when it is on or before today. The age is the age on that date, with source `retirement_date`. A retired person's retirement age is a fact, not a target.

## 6b. Found while walking, fixed in the path

- **Nothing ever wrote `state_pension.already_receiving`.** So a recorded State Pension never counted as income anywhere (`ResolvesIncome` gates on it), and "State Pension: add it" could never clear. It can be deferred ([GOV.UK, deferring your State Pension](https://www.gov.uk/deferring-state-pension)), so it is asked, never assumed. It is now captured in four places:
  - a checkbox on the web State Pension form;
  - the `UpdateStatePensionRequest` rule;
  - `already_receiving` on Fyn's `capture_state_pension` (both provider schemas, golden fixtures recaptured);
  - a State Pension edit form (`CaptureForms::STATE_PENSION`), which a Fyn edit of the State Pension now opens on.
- **The income card's State Pension line** is driven by `state_pension_status`: `missing` shows "Add it"; `not_paid` shows "not recorded as being paid" with Update. The column is `NOT NULL DEFAULT 0`, so "never asked" and "deferred" read the same, and that wording holds for both.
- **The full new State Pension was typed in as £221.20 a week (£11,502)** under a 2026/27 label: on both pension forms, the life-stage panel and the glossary. 2026/27 is £241.30 ([GOV.UK](https://www.gov.uk/new-state-pension/what-youll-get); `pension.state_pension.full_new_state_pension` 12,547.60). All four now read `taxConfig.js`.
- **Every Fyn edit form's money box took whole pounds only** (`step="1"`), so a prefilled amount with pence could not be saved. Both renderers now use `step="0.01"`.
- **Form openings and summaries:** "Here are your state pension" became "Here is your State Pension"; "£11,502.4" became "£11,502.40".

## 7. Not in this item

- **The other retirement tabs** (Future Value, Income, Capital Adequacy, Drawdown strategy) keep their accumulation framing. Item 7 reviews the module.
- **The dashboard's `years_to_retirement`** (`DashboardAggregator`). Listed as Found.
- **The iPhone app.** Reading the new block there is deferred (iOS rule).

## 8. Tests (named files only)

- New `tests/Unit/Services/Retirement/RetirementDrawdownPositionTest.php`:
  - the predicate in all three cases;
  - Pat's figures: lines, tax, lasts-to ages, the life-expectancy income;
  - the pot floor;
  - a pot that lasts beyond the horizon;
  - State Pension missing past State Pension age;
  - someone still working who draws.
- `RetirementAgeResolver` test for `retirement_date`.
- The projections endpoint feature test carries `drawdown_position`, and `null` for a saver.
- Monte Carlo engine tests stay green with the floor.
