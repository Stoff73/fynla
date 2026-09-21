# Threshold position — design

2026-09-21. Slice one of the Threshold-First Planning App spec (Claude Doc, 2026-09-17) and artboard D of the Fynla Actions Layouts canvas. CSJ decisions taken on 2026-09-21 are marked **(CSJ)**.

## Goal

Show each user where they stand against the tax and benefit lines that apply to *them*, nearest first, with what crossing costs in pounds a year and the one lever that moves it. Nothing is fixed per user: the set of lines, their order, and whether the strip renders at all are all computed from that user's own data. **(CSJ: dynamic and per user, never a set form.)**

The strip sits above "Your actions" on the web `/actions` page and the `/m` `/actions` page. It shows one line. Everything else waits behind "See what this costs".

## Mechanism

### `ThresholdPositionService`

One evaluator. It holds a catalogue of `ThresholdLine` implementations and, for a user, asks each line the same four questions:

| Question | Method | Returns |
|---|---|---|
| Does it apply to this user? | `appliesTo(User): bool` | the user has the data the line needs |
| Where do they stand? | `position(User): Position` | the line's value, the user's figure, the signed distance, the unit (`gbp` or `days`) |
| What does crossing cost? | `cost(User): Cost` | a full tax computation at the user's position against one at the line, itemised by tax type and benefit, summing to one figure |
| What moves it? | `lever(User): ?Lever` | one action with its amount and downside, or null for a line with no lever |

The evaluator does no tax arithmetic. Every figure comes from the service that already owns it: `TaxStrategyMath`, `IncomeDefinitionsService`, `UKTaxCalculator`, `IHTCalculationService`, `ChildBenefitService`, `TaxConfigService`. A lever reuses the matching strategy recommendation's suggested contribution so the strip and the actions list cannot disagree (Rule 20).

### Income mix and the cost computation **(CSJ: all income types, mixed)**

A user's income is whatever mix they have: employment, self-employment, rental profit, pension income in payment, trust, interest, dividends, and from this slice scheduled vests. The position already spans the mix because `IncomeDefinitionsService` sums every component into the five HMRC definitions. The cost has to span it too, and the taper's "extra 20p in the pound" is only right for a pure salary.

So a money line's cost is a **delta of two full computations** through `UKTaxCalculator::calculateDetailedNetIncome`, which already applies the right rules per component: Class 1 NI on employment, Class 4 on self-employment, no NI on pension income, the savings allowance and starting rate on interest, dividend rates on dividends, the tapered Personal Allowance and the Gift Aid extension. One run at the user's actual position, one with income brought back to the line by the lever's mechanism. The difference, itemised by tax type, plus the benefits the line removes, is the cost. The same delta gives the lever its "you recover" figure, so the two numbers can never disagree.

The lever's mechanism depends on the mix. A pension contribution reduces adjusted net income whatever the source, so it is the default lever and the only one for earned income. Where the excess is interest or dividends, the strip names the ISA move as well, from `IsaTopUpStrategy` and `DividendAllowanceHarvestStrategy`, because that removes the income from the computation entirely rather than offsetting it.

`ThresholdCost` carries the breakdown: `income_tax`, `ni_class_1`, `ni_class_4`, `dividend_tax`, `interest_tax`, `benefits` (each childcare or child benefit item), and `total`. The web shows all of it. `/m` and iOS show the headline and the total only **(CSJ)**.

**DC drawdown capture (CSJ: included).** Retirement income in the app today is DB pensions in payment plus the state pension. A DC pension in drawdown has no annual income field and no record of the tax-free lump sum taken, so drawdown income cannot enter the mix and the PCLS cannot be excluded from it. This slice adds two nullable columns on `dc_pensions`, `annual_drawdown_income` and `pcls_taken`, captured on the DC pension form on web, `/m` and Fyn's capture form, validated in the Form Request and the Store. `resolvePensionIncomeInPayment` adds drawdown income to pension income in payment; the lump sum never enters any definition. Null means not asked, never zero taken.

Output: an ordered list of lines plus a `strip` pointer to the one that leads. Empty list means the strip does not render.

### Ordering

1. A line applies only when the user has its data **and** is within its window: currently over it, or below it by no more than 25 percent of the relevant base (income, estate, or sacrifice).
2. Money lines sort by absolute distance ascending. Date lines sort after all money lines, by days ascending. Money and days are not comparable, so they never interleave.
3. The strip takes the nearest line that has a lever. A line without a lever never leads.
4. Copy is server-side. Both surfaces render the same words.

## The catalogue at launch

Each row is one class under `app/Services/Tax/Thresholds/`. All values come from `TaxConfigService`; none are hardcoded.

| Line | Applies when | Position | Cost items | Lever |
|---|---|---|---|---|
| Personal Allowance taper | adjusted net income within the £100,000 to £125,140 window | `TaxStrategyMath::personalAllowanceForIncome` | extra 20p in the pound on the excess; plus every childcare item below when children apply | `pa_taper_rescue` suggested contribution |
| Childcare cliff at £100,000 | a `family_members` child under the Tax-Free Childcare age limit, or aged nine months to four for funded hours | shares the taper's position | Tax-Free Childcare: 20 percent of `users.childcare` spend, capped per child at `max_government_contribution`; funded hours: extended entitlement hours × weeks × an hourly funding rate, a new seeded key under `early_years_funding` (the seeder has hours and weeks but no rate) | folds into the taper's lever |
| High Income Child Benefit Charge | a child with `receives_child_benefit` | `ChildBenefitService::calculateHICBC` | charge at the user's position | pension contribution equal to the excess over £60,000, valued by the same maths as `pa_taper_rescue`; no new strategy class |
| Higher-rate threshold | taxable income within the window | `TaxStrategyMath::bandThresholdsFor` | savings allowance halves; dividend and gains rates rise; Marriage Allowance lost if claimed | pension contribution equal to the excess over the threshold, same maths |
| Additional-rate threshold | taxable income within the window | same | savings allowance to nil | `additional_rate_avoidance` suggested contribution |
| Tapered Annual Allowance | threshold income over £200,000 and adjusted income near £260,000 | `TaxStrategyMath::effectiveAnnualAllowanceFor` | allowance lost × marginal rate | `tapered_annual_allowance` strategy |
| Salary-sacrifice NI cap, 6 April 2027 **(CSJ: 2027)** | sacrifice above `pension.salary_sacrifice.nic_exemption_cap` | days to `nic_exemption_cap_effective_date` | employee NI on the sacrifice above the cap | none: a date line |
| Nil rate band | an estate figure exists | `IHTCalculationService` taxable estate against NRB plus RNRB | 40 percent of the excess | none in this slice |
| Residence band taper at £2,000,000 | estate within the window | IHT service `rnrb_taper_reduction` | RNRB lost × 40 percent | none |
| Pensions enter the estate, 6 April 2027 | any DC pension | days to `pension_iht_inclusion.effective_date` | DC pot × 40 percent, where the estate is already over the band | none: a date line |

Dropped for good: the mortgage multiple, a community heuristic not a rule. Peer benchmarking stays out under Rules 12 and 20.

## The vest join **(CSJ: in scope)**

The tax layer cannot see a vest coming. `InvestmentAccount` holds the schedule; `IncomeDefinitionsService::getIncomeComponents` reads `annual_employment_income` alone.

### `VestScheduleResolver`

For each `InvestmentAccount` whose `account_type` is a share scheme and `scheme_status` is active, project the vest events that fall in the current tax year from `vesting_type`, `cliff_date`, `cliff_percentage`, `vesting_period_months`, `vesting_frequency_months`, `full_vest_date` and `units_unvested`, valued at `current_share_price`.

Only schemes taxed as employment income at vest or exercise enter income: `rsu` and `unapproved_options`. `emi`, `csop` and `saye` are excluded; their gain is a capital matter. Where the user has entered `income_tax_at_vest_exercise` that figure is a check, not an input.

Two outputs:

- `annualVestIncome(User): float`, added as a ninth component, `vesting`, in `getIncomeComponents`. It then reaches all five HMRC definitions, the allowance grid, every strategy and the strip in one move (Rule 20). It is not sacrificeable, so it never reduces threshold income.
- `schedule(User): array` of `{date, value, account}` for the tax year, which the strip's lever uses for its downside line ("your March vest of £24,000 cannot be sacrificed and will push you back over").

Assumption stated on the surface, not applied silently: `annual_employment_income` is salary and does not already include share vests. `// ponytail: no per-account include flag; add one if a user reports double counting.`

## Two tax-strategy bugs fixed in this slice **(CSJ)**

### 1. `combined_annual_saving` double-counts the income-band pair

`pa_taper_rescue` and `additional_rate_avoidance` fire against the same Annual Allowance and their savings sum. `StrategyPlanComposer` already resolves `sequencing.conflicts_with` pairs by saving. The seeder carries `'conflicts_with' => []` on both.

Fix: seed each as conflicting with the other in `TaxActionDefinitionSeeder`. Reseed on csjones and production with `db:seed --class=TaxActionDefinitionSeeder --force`. Regression test: the spec's profile (£135,000 employment, £12,000 Gift Aid) composes to £29,521, not £41,521.

### 2. `bandThresholds()` ignores the Gift Aid band extension

`TaxStrategyMath::bandThresholds()` reads raw band limits. `UKTaxCalculator` extends them by the grossed-up Gift Aid under ITA 2007 s414 via `IncomeTaxBands::extendedBy`.

Fix: add `bandThresholdsFor(User $user)` that extends both limits by the user's Gift Aid gross from `IncomeDefinitionsService`, and switch the two user-context callers (`IncomeBandStrategy`, `bandFromIncome` when a user is in hand) to it. `QuerySchemas` keeps the raw call. Regression test: for the same profile no additional-rate slice is valued.

### The analyser's 2029 naming

`SalarySacrificeAnalyzer` names its outputs `post_2029_*` and its copy says April 2029; the seeder and CSJ say 2027. Rename to `post_cap_*`, read the year for the copy from `nic_exemption_cap_effective_date`, and update the one consumer, `RetirementActionDefinitionService`, and its tests. No frontend reads these keys.

## API

`GET /api/thresholds` (Sanctum, same gate as `/api/recommendations/actions`). Response:

```json
{
  "strip": "pa_taper",
  "lines": [
    {
      "key": "pa_taper",
      "title": "Personal Allowance taper",
      "range": {"from": 100000, "to": 125140},
      "position": {"value": 112400, "distance": 12400, "unit": "gbp", "over": true},
      "headline": "You are £12,400 into the 60% band",
      "body": "The next £12,400 you earn costs 60p in the pound...",
      "explanation": "For every £2 you earn above £100,000 you lose £1...",
      "income_mix": {"employment": 98000, "dividend": 9400, "interest": 5000, "vesting": 0},
      "cost": {
        "income_tax": 2480, "ni_class_1": 0, "ni_class_4": 0, "dividend_tax": 0, "interest_tax": 0,
        "benefits": [{"label": "Tax-Free Childcare", "detail": "Two children under 11, £2,000 each", "amount": 4000},
                     {"label": "Funded childcare hours", "detail": "30 hours drops to 15 for your three-year-old", "amount": 6140}],
        "total": 12620
      },
      "lever": {"title": "Salary sacrifice £12,400 into your pension", "amount": 12400, "recovers": 12620, "downside": "The money is locked until you are 57...", "action": {"route": "/tax-strategy"}}
    }
  ]
}
```

Date lines carry `position.unit: "days"` and no `lever`. Copy strings are built in one place, `ThresholdCopy`, in British English, no acronyms uncited, no scores, no icons.

## Surfaces

- **Web** `resources/js/views/Actions/ActionsDashboard.vue`: a `ThresholdStrip.vue` component above "Your actions", fetched alongside the actions call. Collapsed: headline, body, "See what this costs", the ribbon. Expanded: the artboard D layout in full, including "Other lines that apply to you" and the suppressed-lines sentence. Renders nothing when `lines` is empty.
- **`/m`** `resources/mobile/views/Actions.vue`: the same strip as an `m-card` above "Open", expanded in place as an accordion. Its own component under `resources/mobile/components/` because the bundles are isolated.
- **iOS** (`ios-native/`): the same endpoint, rendered as headline, total and lever line on the native dashboard's actions area **(CSJ: end figures on /m and iOS)**. No breakdown, no accordion. Native has no actions list today, so this is one new view model and one card; it ships after web and `/m` are green per the release-order rule.
- The full breakdown by income type and tax type is web only. `/m` shows headline, total and lever; the "Other lines" list stays on `/m` as titles and figures.
- Palette tokens only. Warnings in `violet-*`. No amber. No icons. The ribbon is CSS on a div, no chart library.
- Premium: follows whatever gates the actions page today; no new gate.

## Testing

- Pest unit tests per catalogue line under `tests/Unit/Services/Tax/Thresholds/`, driven by constructed users: the spec's £112,400 parent, a £78,000 childless user (empty), a retired user with a £900,000 estate (estate lines only), a £145,000 user with £30,000 sacrifice (NI cap line).
- Mixed-income cost tests: a self-employed user at £108,000 (Class 4 not Class 1 in the delta); a £90,000 salary plus £20,000 dividends user (dividend rates in the delta, ISA lever named); a DB pensioner at £104,000 (no NI); a user whose only excess is interest. Each asserts the itemised breakdown and that the total equals the calculator's own delta.
- `VestScheduleResolverTest`: quarterly RSU schedule lands the right events in the tax year; EMI excluded; the vest reaches `adjusted_net_income`.
- The two regression tests above, in `tests/Unit/Services/Tax/`.
- Feature test on `GET /api/thresholds`: ordering, empty case, date lines after money lines.
- Vitest on both strip components.
- Playwright on csjones, web and `/m`, two personas: one over £100,000 with two under-fives and an RSU schedule, one with nothing applying. Every figure on screen checked against the API and the tax config.

## Configuration added

Two seeded keys, both in `TaxConfigurationSeeder`, never in code: `benefits.early_years_funding.<band>.hourly_rate` for the three income-tested bands (DfE national average funding rates), and `pension.normal_minimum_pension_age` (57 from 6 April 2028) for the lever's "locked until" line.

## Out of scope for this slice

B lanes and C cards. The mobile push trigger on a forecast crossing. Model-this-change flow beyond linking to `/tax-strategy`. The Autumn Budget rail.
