# Investment cards: review and design

**Status:** APPROVED. CSJ answered D1 to D4 on 2026-10-06, each as recommended.
**Date:** 2026-10-06
**Item:** `todoCurrent/TODO.md` item 8 (the item 7 shape: review, decisions, fixes, how-tos, walk, release).

## 1. What the review found

### 1.1 The 24 definitions, and which reach a card

`InvestmentActionDefinitionSeeder` holds 24 rows: 18 `agent`, 3 `goal`, 3 `strategy`.

- **The 3 `strategy` rows** (`strategy_rebalance_to_target`, `strategy_reduce_platform_fees`, `strategy_set_risk_profile`) are catalogue rows for the plan composer (`InvestmentStrategySource::metadataRows`). They are never cards and need no how-to.
- **The 3 `goal` rows** are evaluated only by `InvestmentPlanService:94` (the Investment plan page), as retirement's are. They are never cards and need no how-to.
- **Of the 18 `agent` rows, the actions list can only ever show 11.** The list reads investment cards through `InvestmentAgent::generateRecommendations` (`RecommendationsAggregatorService:158-161`, `InvestmentStrategySource::recommendations`). That path passes no savings analysis, no accounts, no user id and no fee analyses (`InvestmentAgent.php:327-334`). So these seven never reach a card:
  - the three fee rules, `high_total_fees`, `high_fund_fees` and `high_platform_fees` (no fee analyses);
  - the four savings rules, `emergency_fund_critical`, `emergency_fund_grow`, `switch_savings_rate` and `isa_allowance_remaining`, and the three surplus rules, `surplus_to_isa`, `surplus_to_pension` and `surplus_to_bond` (no savings analysis).

  All of them still show on the Investment plan page, which calls the service with everything (`InvestmentPlanService:162`).

### 1.2 Which keys fire (csjones, 38 households with an investment account, 2026-10-06)

| Key | On the actions list | On the Investment plan page |
|---|---|---|
| `no_holdings` | 17 | 29 |
| `rebalance_portfolio` | 9 | 9 |
| `low_diversification` | 7 | 7 |
| `use_isa_allowance` | 7 | 7 |
| `open_isa` | 4 | 4 |
| `consider_bonds` | 2 | 2 |
| `tax_loss_harvesting` | 1 | 1 |
| `risk_profile_missing` | 0 | 12 |
| `high_total_fees` / `high_fund_fees` / `high_platform_fees` | 0 | 4 / 3 / 0 |
| `emergency_fund_critical` / `emergency_fund_grow` | 0 | 9 / 7 |
| `isa_allowance_remaining` | 0 | 10 |
| `surplus_to_isa` / `surplus_to_pension` / `surplus_to_bond` | 0 | 10 / 7 / 1 |
| `switch_savings_rate` | 0 | 0 |

(Local, 14 households: the same pattern.)

- **`risk_profile_missing` never reaches a card,** because a missing risk profile blocks the module (`DataReadinessService::checkRiskProfile`, level `blocking`). Such a household sees the module's unlock card instead. The plan page still shows the rule.
- **The `no_holdings` gap (29 against 17)** is the same gate: 12 households have accounts but no risk profile.

### 1.3 The same action on two cards

**Across modules: Tax plan.** "Bed & ISA" (Tax plan, approved how-to) and investment's "Use Your ISA Allowance" are one action: sell General Investment Account holdings and buy them back inside the ISA.
- The Mitchell demo's list carries both: "Bed & ISA — potentially shelter £1,588 of gains this year" and "Use Your ISA Allowance… Consider moving General Investment Account holdings (£47,500) into your ISA before 5 April".
- Bed & ISA fires only when the account holds gains (`BedAndIsaStrategy`, `$totalUnrealisedGain <= 0` returns nothing). "Use Your ISA Allowance" and "Open a Stocks & Shares ISA" fire whatever the gains. So the two overlap whenever there are gains, and only the investment card speaks when there are none.

**Across modules: Savings.** The seven savings and surplus rules repeat Savings definitions that already reach the list:

| Investment rule (plan page only) | Savings rule |
|---|---|
| `emergency_fund_critical`, `emergency_fund_grow` | `emergency_fund_critical`, `emergency_fund_low`, `emergency_fund_building` |
| `switch_savings_rate` | `rate_below_market`, `rate_poor` |
| `isa_allowance_remaining` | `isa_allowance_remaining` |
| `surplus_to_isa`, `surplus_to_pension`, `surplus_to_bond` | `excess_cash_isa_available`, `excess_cash_pension`, `excess_cash_bond` |

**Within Investment.**
- **`rebalance_portfolio` and `low_diversification`** answer one question: is the portfolio spread the way the risk level says it should be? Seven households get both.
- **The three fee rules** can fire three cards for one account and one action, which is reviewing its charges (as retirement's did, D3 there).

### 1.4 Figures on the cards that are wrong (Rule 23)

- **"Losses you could use against gains" always says "Potential tax saving: £0".** Every csjones and local household where it fires (Mitchell, live on fynla.org: "1 holdings have unrealised losses. Potential tax saving: £0."). Four reasons, all in `CGTHarvestingCalculator`:
  - the gains the loss is set against default to 0 (`expected_gains`, `:43`), so `min($loss, $expectedGains)` is always 0 (`:144`);
  - the rate is always the higher rate, with a typed-in fallback (`TaxDefaults::CGT_HIGHER_RATE`, `:44`), not the user's band (the Tax plan's Bed & ISA uses the band);
  - it reads accounts in the user's own name only, at full value (`:96-99`), so a joint account counts in full for one owner and not at all for the other (Rule 6);
  - it excludes only ISAs, so a loss in a Venture Capital Trust (gains and losses are not chargeable, Taxation of Chargeable Gains Act 1992 s151A, https://www.legislation.gov.uk/ukpga/1992/12/section/151A) or in an investment bond (gains are chargeable event gains under income tax, not capital gains, Income Tax (Trading and Other Income) Act 2005 s461, https://www.legislation.gov.uk/ukpga/2005/5/section/461) is offered as a capital loss.
  - The wording also says "1 holdings".
- **"Consider Tax-Efficient Bonds"** says onshore bonds give a "5% annual tax-free withdrawal". The 5% is tax-deferred, not tax-free: withdrawals within it are not taxed when taken, but count when the policy ends (ITTOIA 2005 s507, https://www.legislation.gov.uk/ukpga/2005/5/section/507).
- **Typed-in fallbacks (Rule 2):** `TaxDefaults::ISA_ALLOWANCE` in `evaluateOpenIsa` and `evaluateUseIsaAllowance`, and the CGT rate above.

### 1.5 Card text and data

- **No `figures` on any investment card.** The adapter carries `definition_key` (so the how-to can be found), but `buildRecommendation` sets no `figures` and the adapter passes none (`InvestmentRecommendationAdapter`), unlike retirement and protection. A how-to cannot use the card's numbers.
- **Cards with no figures at all:** "Rebalance Portfolio" ("deviates significantly"), "Improve Portfolio Diversification" ("a limited number of asset types"). The figures exist (`allocation_deviation.deviations`, actual and target per asset class) but only reach the decision trace.
- **A score reaches Fyn (Rule 12).** `low_diversification` fires on `diversification_score` below 70 (an unsourced threshold), and its decision trace says "Portfolio diversification at 62% is 8 percentage points below the 70% target". Fyn reads the trace.
- **Title Case titles,** unlike every approved module ("Provide Your Investment Preferences", "Rebalance Portfolio", "Use Your ISA Allowance").

### 1.6 Is there a "your position" view?

**On the page, yes. On the cards, no.** The Investment page shows the allocation against the target for the risk level. No card states it: "Rebalance Portfolio" says only that it "deviates significantly".

### 1.7 Checked and not a defect

- **The ISA allowance remaining** comes from the one rule (`ISATracker::usedThisTaxYear`, item 16).
- **The General Investment Account value is the user's share** (Mitchell: £47,500 of the £95,000 joint account at 50%).
- **Item 7's Found line "a retiree is told the Investment gate needs gross annual income"** no longer holds: the gate reads the Income page's total (`ResolvesIncome`, 2026-10-02). csjones 460 is now blocked only by spending, and holds no investments.

## 2. The rules this rests on

- **ISA allowance and Bed & ISA:** subscriptions up to the annual allowance (gov.uk, https://www.gov.uk/individual-savings-accounts; `TaxConfigService::getISAAllowances`). Moving holdings in means selling and subscribing cash, which is a disposal for capital gains (TCGA 1992 s1, s21).
- **Capital losses:** set against gains of the same year first, then carried forward; a loss counts only once claimed (TCGA 1992 s2(2), s16(2A), https://www.legislation.gov.uk/ukpga/1992/12/section/16), within four years of the end of the tax year (Taxes Management Act 1970 s43, https://www.legislation.gov.uk/ukpga/1970/9/section/43). Buying back the same shares within 30 days matches the sale to the purchase (TCGA 1992 s106A), so the loss is not available.
- **CGT rates and the annual exempt amount:** `TaxConfigService::getCapitalGainsTax` (`basic_rate`, `higher_rate`, `annual_exempt_amount`), by the user's band.
- **Investment bonds:** 5% tax-deferred allowance (ITTOIA 2005 s507); chargeable event gains are income (s461).
- **Fees:** no rule; the thresholds (1.0% total, 0.5% fund, 0.8% platform) are product settings in the seeder, and the cards state the user's own charges.

## 3. Design

### 3.1 Cards carry their figures (no decision needed; copies retirement)

- `buildRecommendation` adds `figures` (its template vars, scalars only); the adapter carries them in `extra`, as `RetirementRecommendationAdapter` does.
- Titles in sentence case with the user's figure. The new titles go to CSJ with the how-to batch.

### 3.2 The savings rules leave Investment (decision D1)

**Recommended:** the seven savings and surplus rules are disabled in the seeder, each with a note naming the Savings rule that carries it. They only ever showed on the Investment plan page, and the Savings module already shows them on the list, with how-tos.

### 3.3 One card for moving holdings into an ISA (decision D2)

**Recommended:** the Tax plan's "Bed & ISA" carries the move whenever it fires. "Use your ISA allowance" and "Open a Stocks & Shares ISA" step aside when the account holds gains (the same gains figure, one function shared with `BedAndIsaStrategy`, at the user's share), and stay for an account without gains, where there is no capital gain to shelter but dividends and interest are still taxed.

### 3.4 One "your portfolio against your risk level" card (decision D3)

**Amended (CSJ 2026-10-06, "Per account, as the page"):** the card is per account, and reads the rule the account's rebalancing panel shows (`DriftAnalyzer`, the account's own threshold and risk level), so card, page and dashboard say the same. The portfolio rule below (`calculateDeviation`) is not used for the card.

**Recommended, the protection and retirement pattern:** an `allocation_position` card replaces "Rebalance Portfolio" and "Improve Portfolio Diversification".
- **It fires when** the allocation is outside its bands (`allocation_deviation.needs_rebalancing`), the figure the page shows.
- **Title:** "Your portfolio is {actual}% in shares against {target}% for your risk level" (the largest gap), with every asset class's actual and target in its figures.
- **The diversification score stops being a trigger** (Rule 12, and its 70 has no source). A concentrated portfolio shows as its allocation.

### 3.5 Fees: one card per account, and it reaches the list (decision D4)

**Recommended:** `high_total_fees`, `high_fund_fees` and `high_platform_fees` fold into one card per account, "Review the charges on {account}", listing each charge above its threshold in pounds a year. `InvestmentAgent::generateRecommendations` computes the fee analyses (`FeeAnalyzer::analyzeAccountFees`) so the card reaches the actions list. Today it never does.

### 3.6 Fix the losses card (no decision needed; defect)

`tax_loss_harvesting` uses the user's realised gains this year where recorded, the user's CGT rate by band, joint accounts at the user's share, and only accounts whose gains are chargeable gains (not ISAs, pensions, Venture Capital Trusts or investment bonds). If no gains are recorded, the card states the losses and that they can be carried forward if claimed, and no saving. "1 holdings" becomes "1 holding".

### 3.7 Text (no decision needed; defects)

- The bond card says "tax-deferred", not "tax-free", with its source.
- The `TaxDefaults` fallbacks go; the values come from `TaxConfigService` only.
- The decision trace states allocations, not a score.

## 4. Decisions for CSJ

- **D1.** The seven savings and surplus rules leave Investment; Savings carries them (3.2)?
- **D2.** Bed & ISA (Tax plan) is the one card when the account holds gains; the investment ISA cards stay only for an account without gains (3.3)?
- **D3.** One "your portfolio against your risk level" card replaces "Rebalance Portfolio" and "Improve Portfolio Diversification", and the diversification score stops being a trigger (3.4)?
- **D4.** One fees card per account, and it reaches the actions list (3.5)?

## 5. How-tos

Written after the decisions, in `database/seeders/data/action-how-to/investment.md` (the `savings.md` / `protection.md` / `retirement.md` format), with `'investment' => [InvestmentActionDefinition::class, 'key']` in `ActionHowToSeeder::SOURCES`. Every entry starts as `draft`, every claim is sourced, and the draft is merged to `dev` before CSJ reviews it.

The cards that need one, if D1 to D4 are approved: `allocation_position`, the fees card, `tax_loss_harvesting`, `use_isa_allowance`, `open_isa`, `consider_bonds` and `no_holdings` (seven). `risk_profile_missing` reaches no card (1.2); the goal and strategy rows are not cards (1.1).

## 6. Testing

- **Unit:** each card carries its key and figures; the fold produces one card per account with the right charges; the savings rules no longer fire; the ISA cards step aside when Bed & ISA's gains figure is above zero; the losses card uses the band rate, the user's share and only chargeable accounts, and states no saving without recorded gains.
- **Cards:** `ActionCardService` returns an approved investment how-to by key.
- **Live walk on csjones, web 1440 + `/m` 390:** the Mitchell demo (472: Bed & ISA alone, the position card, the losses card), 14 (fees), 129 (open ISA, no gains), 419 (bonds).
