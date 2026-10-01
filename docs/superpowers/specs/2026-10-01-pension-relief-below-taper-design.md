# Pension relief at 40% below £100,000, down to £50,270

**Item:** `todoCurrent/TODO.md` item 5. APPROVED, CSJ 2026-09-30: "Yes build it" (matrix E3; households S3 and S9; live finding L3-4).
**Date:** 2026-10-01

## 1. The law

| Rule | Source |
|---|---|
| The Personal Allowance falls by half of adjusted net income above £100,000 | [ITA 2007 s35](https://www.legislation.gov.uk/ukpga/2007/3/section/35) (`income_tax.personal_allowance_taper_threshold`, `income_tax.personal_allowance_taper_rate`) |
| Adjusted net income is net income less grossed-up Gift Aid and the gross amount of relief-at-source pension contributions; net-pay contributions are already out of net income | [ITA 2007 s58](https://www.legislation.gov.uk/ukpga/2007/3/section/58); FA 2004 s193 (net pay) |
| A relief-at-source contribution raises the basic and higher rate limits by its gross amount, on a claim, where higher- or additional-rate tax is due | [FA 2004 s192(4)](https://www.legislation.gov.uk/ukpga/2004/12/section/192) |
| Higher-rate relief is "20% up to the amount of any income you have paid 40% tax on", on top of the 20% added at source | [gov.uk, pension tax relief](https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief) |
| The higher rate starts at £50,270 of income for someone with the full allowance (£12,570 + £37,700 basic band) | `income_tax.bands` "Higher Rate" `lower_limit` (2026/27: 50,270), extended for Gift Aid and relief at source by `TaxStrategyMath::bandThresholdsFor` |
| Relief only on contributions up to relevant UK earnings (or the basic amount), within the Annual Allowance | [FA 2004 s190](https://www.legislation.gov.uk/ukpga/2004/12/section/190), s228 (`pension.annual_allowance`, already applied by `TaxStrategyMath::pensionReliefLimit`, `availableAnnualAllowance`) |

So once a contribution has brought adjusted net income back to £100,000, every further gross £1,000 still relieves tax at 40% (£400), until income reaches the higher-rate threshold. Below that it is basic-rate relief only.

## 2. What exists today

| Income | Card | Sizing |
|---|---|---|
| Up to £100,000 (ANI) | `pension_tax_relief` (`PensionTaxReliefStrategy`) | Higher rate: the whole higher-rate slice down to £50,270 (`higherRateSlice`), capped by Annual Allowance, earnings and affordable money |
| £100,000 to the additional-rate threshold | `pa_taper_rescue` (`IncomeBandStrategy` #1) | **Only** ANI − £100,000 (`TaxStrategyMath::taperRescueContribution`). Stops there: this is E3 |
| Above the additional-rate threshold | `additional_rate_avoidance` (`IncomeBandStrategy` #2) | The 45% slice, then the taper band, then **down to the higher-rate threshold** (`additionalRateAvoidanceContribution`) |

So the gap is only in the middle card. Above and below it, the plan already carries relief down to £50,270. `TaxStrategyCalculator::OWN_PENSION_TYPES` treats the three as one card per income ("only one applies for a given income").

Worked: S3, £110,000 pay, 5% + 5% workplace pension (net pay), £10,000 at 4.5%. ANI £104,950. The card is £4,900 saving £2,940. Annual Allowance left £49,000; higher-rate slice £54,230. Nothing tells the user that £44,100 more would save £17,640.

## 3. The design

### 3.1 One card, carried down to the higher-rate threshold

- **`pa_taper_rescue` keeps its type and title** ("Reclaim your Personal Allowance with a pension contribution"), so a user's done or dismissed state survives. It works as `additional_rate_avoidance` already does: first the taper slice (ANI − £100,000), then the income taxed at 40% below it, down to the higher-rate threshold.
- **Sizing:** contribution = max(taper slice, higher-rate slice), capped by the Annual Allowance left, the relief limit (earnings less what already goes in) and the affordable money, then rounded down to £100.
  - The higher-rate slice is today's `PensionTaxReliefStrategy::higherRateSlice`, moved to `TaxStrategyMath` so both cards size it in one place (Rule 20). It counts non-savings income above the threshold plus interest the Personal Savings Allowance does not cover. Dividends are left out, so it can only understate.
  - The taper slice comes first: when money is short, it goes to the 60% slice before the 40% slice.
- **Priced by the tax engine** (`pensionContributionSaving`), as now. The engine applies s35, s58 and the bands, so the total is exact.
- **When the money to pay it is not known** (no spending recorded, `pensionMoney === null`), the card stays at the taper slice, as today. This follows CSJ's ruling for new pension money, "We ask for expenditure" (2026-09-30), and it keeps the /savetax promise equal to the plan for such an account (`SaveTaxFunnelEngineParityTest`, "trap"). Save Tax setup already asks for spending (#1027).
- **The description** splits the saving into the two parts the user can check, as now. The reclaimed allowance is half the **taper slice**, not half the contribution. Today's code halves the contribution, which is only right while the two are equal.

### 3.2 What the user sees

The S3 household with £20,000 affordable (engine figures to be confirmed in the tests):

> For your income of £104,950, a £20,000 pension contribution would:
>
> Reclaim £2,475 of your Personal Allowance, saving £990.
>
> Reduce your income tax at 40% by £8,000.
>
> Together that's £8,990 back this year. Income between £100,000 and £125,140 is taxed at 60%. Below £100,000, each £1,000 you pay in still saves £400, down to £50,270.

- When the card does not go below £100,000 (taper slice only), today's wording stays, including "(20% of your contribution)".
- **How-to** (`tax.md`, `pa_taper_rescue`): one new `always` step after step 2, shown only when the card goes below the threshold:
  > `when below_taper:` Below {taper_threshold}, each pound you pay in still gets relief at {higher_rate_relief}, down to {higher_rate_threshold}, where the higher rate starts.
  
  The steps and outcome lines already scale from `{contribution}` and the saving.
- **Fyn's house view** (`fyn-memory/semantic/house_view/pa-taper-rescue.md`) says the contribution "is sized to the slice of income inside the band". It is changed to say the plan carries on at higher-rate relief down to the higher-rate threshold when the money is there. Then `fyn:semantic:reindex`.

### 3.3 What else moves with it

- **Savings items are re-priced after the pension** (`TaxStrategyCalculator::repriceSavingsAfterPension`, `withPensionPaidElsewhere`). A larger pension lowers the rate on the interest, and the engine prices that. Expected: smaller savings figures beside a bigger pension card.
- **Partner top-up competition** (`markPensionsCompetingForMoney`) already compares the own card's net cost with the partner's against the same money. A bigger own card makes "alternatives for that money" more likely, which is the CSJ ruling (weighed in context).
- **Threshold page** (`PersonalAllowanceTaperLine`) takes `min(excess, suggested)`, so it keeps describing only the move back under £100,000. Unchanged.
- **The tile, card figures, deadline and "Fund from"** read `suggested_contribution` generically. No change.

## 4. Decisions

- **D1, unknown money:** carry on only when the money is known (3.1). Decided from CSJ's "We ask for expenditure" (2026-09-30). Not asked again.
- **D2, the /savetax funnel:** leave its trap line at the taper slice. It cannot know what someone can afford, and today it already promises more than the plan delivers (L3-1). **Answered (CSJ 2026-10-01): leave the funnel as is.**
- **D3, wording:** the description in 3.2 and the how-to step. **Answered (CSJ 2026-10-01): approved as written.**

## 5. Not in this item

- A partner in the taper band (a linked partner gets this on their own account; the funnel's partner trap line is unchanged by D2).
- Scottish Income Tax (parked by CSJ).
- Dividend-heavy incomes: relief that moves dividends from 33.75% to 8.75% is priced by the engine but not sized for.

## 6. Tests (named files only)

- `tests/Unit/Services/Tax/Strategies/IncomeBandStrategyTest.php`:
  - S3 with known money: carries below £100,000, capped by money and by the Annual Allowance.
  - Unknown money: the taper slice only, as today.
  - Money short of the taper slice: the taper slice first.
  - The allowance reclaimed is half the taper slice, and the two parts add to the total.
  - Dividends-only top slice.
- `tests/Unit/Services/Tax/SaveTaxMatrixDefectsTest.php`: E3 for S3 and S9.
- `tests/Unit/Services/Tax/Strategies/PensionTaxReliefStrategyTest.php`: unchanged behaviour after `higherRateSlice` moves.
- `tests/Unit/Services/Marketing/SaveTaxFunnelEngineParityTest.php`: still green.
- `tests/Unit/Services/Tax/TaxStrategyCalculatorTest.php`: savings re-priced after the larger pension.
- How-to rendering test for the new step.
- Every new test is shown to fail on the old code first.
