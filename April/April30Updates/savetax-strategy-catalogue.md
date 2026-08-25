# SaveTax Dashboard — Strategy Catalogue (v0.2, post-redline spec)

**Purpose.** Enumerate 18 deterministic UK personal-tax strategies for `app/Services/Tax/TaxStrategyCalculator.php` to surface on the SaveTax dashboard. Each one is a pure rule-based suggestion calculable from `users` + `dc_pension` + `savings_account` + `investment_account` + `holding` + `family_members` + `tax_strategy_household_inputs` + `TaxConfigService` — no LLM. This preserves the deterministic guarantee of the SaveTax architecture (per `fyn-ai-and-savetax-architecture-map.md` §2.1).

**Format key.**

- **Triggers when** — DB / condition predicates the calculator checks before emitting the suggestion.
- **Mechanism** — the UK tax rule being leveraged (in plain language; numbers come from `TaxConfigService` not hardcoded).
- **Estimated saving** — closed-form expression returned as `estimated_annual_tax_saved` on the suggestion object.
- **Data needed** — ✓ already in DB, ✗ requires new capture tool or field.
- **Headline copy** — dashboard description (≤220 chars; richer items may use **Title + Sub-line** instead of one sentence). British spelling, acronyms spelled out per Rule #10 — only `ISA` is permitted abbreviated.
- **Priority** — `high` / `medium` / `low` for ordering in `StrategyRecommendationList.vue`.

**Conventions.** All amounts come from `TaxConfigService->getIncomeTax()`, `getISAAllowances()`, `getPensionAllowances()`, `getCapitalGainsTax()`, `getDividendTax()`. The numbers shown below in copy are 2025/26 reference values for clarity — the implementation must read live config.

---

## A. Income-band driven (single-user)

### 1. Personal Allowance Taper Rescue (60% effective rate band)

- **Triggers when:** `taxable_income > £100,000` AND `taxable_income < £125,140 + relievable_pension_capacity`
- **Mechanism:** Personal Allowance reduces by £1 for every £2 of income above £100,000, fully tapered at £125,140. Marginal rate in this band = 40% income tax + 20% Personal Allowance withdrawal recovery = **60% effective**. A pension contribution that drops `(income − relievable_contribution)` below £100,000 reclaims the full Personal Allowance.
- **Estimated saving:** for a contribution `C` where `C ≤ income − 100,000` and `C ≤ remaining_AA + carry_forward`: `C × 0.60`. Cap `C` at `min(income − 100,000, 25,140, available_AA)`.
- **Data needed:** ✓ `users.annual_employment_income`; partial — need a `taxable_income` view that combines employment + dividends + interest. ✗ A "what other income do you have?" capture would tighten the trigger (currently we only see employment income).
- **Headline copy:** "Income between £100,000 and £125,140 is taxed at 60% — a contribution of £X to your pension reclaims £Y of your Personal Allowance and saves £Z this year."
- **Priority:** `high`

### 2. Additional-Rate Threshold Avoidance

- **Triggers when:** `taxable_income > £125,140` AND `available_AA + carry_forward > 0`
- **Mechanism:** Additional rate (45%) applies above £125,140. A pension contribution that pushes `(income − relievable_contribution)` below £125,140 saves the 5-percentage-point gap (45 → 40) on the affected slice, then 60% on the £100k-£125,140 band, then 40% below.
- **Estimated saving:** piecewise: `min(income − 125,140, C) × 0.45 + min(C_remaining, 25,140) × 0.60 + remainder × 0.40`.
- **Data needed:** Same as #1.
- **Headline copy:** "Income above £125,140 is taxed at 45%. Contributing £X to your pension shifts that slice into the 40% band and the 60% taper, saving £Y in tax this year."
- **Priority:** `high`

### 3. Pension Annual Allowance Carry-Forward

- **Triggers when:** `current_year_pension_input < £60,000` AND `prior_3_years_unused_AA > 0` AND `user_band ∈ {higher, additional}` AND user has surplus disposable income (proxy: gross income − expenditure − current pension contribution > some threshold)
- **Mechanism:** Unused Annual Allowance from the previous three tax years can be carried forward and used after the current year's £60k allowance is exhausted. Useful for bonus years or self-employed lump-sum funding. Relievable contribution still capped at relevant earnings.
- **Estimated saving:** `unused_carry_forward × user_marginal_rate`, capped by `min(unused_carry_forward, relevant_earnings − current_year_AA_used)`.
- **Data needed:** ✗ Three years of historical pension input amounts. Currently not captured. Either new tool `capture_pension_history` or three new fields on `dc_pension` / `users`.
- **Headline copy:** "You may have up to £X of unused pension allowance from the last three tax years. Carrying it forward could save £Y at your marginal rate."
- **Priority:** `medium`

### 4. Salary Sacrifice for National Insurance Relief

- **Triggers when:** `users.employment_status = employed` AND user has a workplace pension AND contribution method ≠ salary sacrifice (or unknown)
- **Mechanism:** Salary-sacrifice pension contributions reduce gross salary, saving the employee's National Insurance (8% basic / 2% above the upper earnings limit) and freeing the employer's 13.8% — many employers rebate part or all of the employer NI saving back into the pension.
- **Estimated saving:** `annual_contribution × employee_NI_rate` + (if employer rebates) `× 0.138`.
- **Data needed:** ✓ `dc_pension.monthly_contribution_amount`. ✗ Two new fields: `is_salary_sacrifice` (boolean) and optional `employer_ni_rebate_pct`. Add to `capture_salary_sacrifice` or `create_pension`.
- **Headline copy:** "Switching your £X annual pension contribution to salary sacrifice saves £Y in National Insurance every year, with no change to your take-home pay."
- **Priority:** `medium`

---

## B. Allowance harvesting (single-user)

### 5. ISA Top-Up From Cash Earning Beyond the Savings Allowance

- **Triggers when:** `current_year_ISA_subscription < £20,000` AND `non_isa_savings_balance × avg_rate > Personal_Savings_Allowance_band`
- **Mechanism:** Interest above the Personal Savings Allowance band (£1,000 basic / £500 higher / £0 additional) is taxed at the user's marginal rate. Wrapping non-ISA cash in an ISA up to the £20k annual subscription removes the interest from the tax computation permanently.
- **Estimated saving:** `min(remaining_ISA_allowance, non_isa_balance) × avg_rate × user_marginal_rate` for the slice that would otherwise be taxed.
- **Data needed:** ✓ `savings_account.current_balance`, `interest_rate`, `is_isa`; ✓ `users.annual_employment_income` (to derive band).
- **Headline copy:** "You hold £X of non-ISA cash earning interest above your £Y Savings Allowance. Wrapping £Z in an ISA before April 5 saves £W in tax every year going forward."
- **Priority:** `high`

### 6. Bed & ISA — Capital Gains Harvest Within the Annual Exempt Amount

- **Triggers when:** `non_isa_holding_unrealised_gains > 0` AND `current_year_realised_gains < £3,000` AND `remaining_ISA_allowance > 0`
- **Mechanism:** Sell holdings outside the ISA wrapper to crystallise gains within the £3,000 Annual Exempt Amount, then re-buy the same units inside an ISA. The 30-day "bed & breakfasting" anti-avoidance rule does not apply when the re-purchase is inside an ISA. Future growth is then sheltered.
- **Estimated saving:** `realisable_gains_within_AEA × CGT_rate` saved on future disposal (illustrative — present-value framing optional).
- **Data needed:** ✗ Per-holding `unrealised_gain` (not currently captured on `holding` or `investment_account`). Either compute from `purchase_price` + `current_value` (need both), or capture explicitly.
- **Headline copy:** "Selling £X of holdings outside your ISA and rebuying inside it crystallises gains within your £3,000 tax-free allowance and shelters future growth."
- **Priority:** `medium`

### 7. Dividend Allowance Harvest

- **Triggers when:** `annual_dividend_income < £500` AND user holds dividend-producing investments outside an ISA
- **Mechanism:** First £500 of dividend income is tax-free for everyone. Below the threshold, holding income-class share classes (or selectively realising) outside the ISA uses the allowance without cost.
- **Estimated saving:** `(£500 − annual_dividends) × dividend_marginal_rate` (8.75% basic / 33.75% higher / 39.35% additional).
- **Data needed:** ✓ `users.annual_dividend_income`, `holding`. Class-of-share distinction is probably out of scope — surfacing as awareness is enough.
- **Headline copy:** "You have £X of unused Dividend Allowance. Holding income-paying shares outside your ISA up to this amount returns dividends tax-free."
- **Priority:** `low`

---

## C. Household / spouse strategies

### 8. Marriage Allowance Transfer

- **Triggers when:** `users.marriage_allowance_eligible = true` AND `user_band = basic` AND `spouse_taxable_income < Personal_Allowance`
- **Mechanism:** A non-using spouse transfers £1,260 of unused Personal Allowance to a basic-rate partner. Saving = 20% × £1,260 = £252.
- **Estimated saving:** `1,260 × 0.20` = £252.
- **Data needed:** ✓ Already implemented (`buildAssetShiftingSuggestions`, line 244). Behaviour is correct — no change needed.
- **Headline copy:** "Your spouse can transfer £1,260 of unused Personal Allowance to you, saving £252 in income tax this year."
- **Priority:** `medium`

### 9. Spouse Asset Transfer — Personal Allowance + Starting Rate + Savings Allowance

*(CSJ's example #2, expanded.)*

- **Triggers when:** `household_calculation_mode = single_earner_couple` AND user holds non-ISA savings AND spouse has remaining capacity
- **Mechanism:** A non-earning spouse stacks three separately-named buckets of tax-free interest:
  1. **Personal Allowance** (£12,570) — applies to any income type.
  2. **Starting Rate for Savings** (£5,000) — taxed at 0% on savings interest. Full £5,000 only when non-savings income ≤ Personal Allowance; tapers £-for-£ above.
  3. **Personal Savings Allowance** (£1,000 basic-rate band) — applies on top of the above.

  Combined: **up to £18,570 of interest income tax-free per year.** Spousal transfers between UK-domiciled spouses are exempt from both Capital Gains Tax (no-gain/no-loss base cost transfer) and Inheritance Tax (spousal exemption).
- **Estimated saving:** `min(transferable_savings × user_avg_rate, 18,570) × user_marginal_rate`.
- **Data needed:** ✓ Already partially implemented (`buildAssetShiftingSuggestions`, lines 256-287, line 269 already calculates `PA + starting_rate + PSA_basic` capacity). Two changes needed:
  1. Headline copy must **name the three buckets explicitly** (currently the description just says "Their unused Personal Allowance + Starting Rate for Savings + Personal Savings Allowance can absorb…" which is close, but the dashboard tile structure should also break out the three bucket lines as separate sub-rows).
  2. The non-earner spouse grid (`buildSpouseAllowanceGridNonWorking`, lines 197-230) shows these as `used = 0` with no narrative — add a "headroom available" badge per position.
- **Headline copy:**
  - **Title:** "Gift £X of savings to your spouse for up to £18,570 of interest tax-free every year"
  - **Sub-line:** "Their Personal Allowance (£12,570), Starting Rate for Savings (£5,000) and Personal Savings Allowance (£1,000) stack — and spousal transfers are exempt from Capital Gains Tax and Inheritance Tax."
- **Priority:** `high`

### 10. ISA Top-Up in Spouse's Name

- **Triggers when:** `spouse_existing_isa_balance < £20,000`
- **Mechanism:** Each adult gets a separate £20,000 annual ISA subscription. Couple total: £40,000/year of fresh shelter.
- **Estimated saving:** Indirect — surfaces unused capacity rather than a single-year saving figure.
- **Data needed:** ✓ Already implemented (`buildAssetShiftingSuggestions`, line 290). Keep.
- **Headline copy:** "Your spouse has £X of unused ISA allowance this tax year. As a couple, you can shelter up to £40,000 a year between you."
- **Priority:** `medium`

### 11. Dividend & General Investment Account Rebalance to Lower-Band Spouse

- **Triggers when:** `user_band ∈ {higher, additional}` AND `spouse_band = basic` AND user holds non-ISA dividend- or capital-gain-producing investments
- **Mechanism:** Dividends and capital gains are taxed at the holder's marginal rate. Holding the same assets in the lower-band spouse's name reduces the effective rate by 25-30 percentage points across both income types. Spousal transfers are CGT- and IHT-exempt.
  - Dividend rates: 8.75% basic / 33.75% higher / 39.35% additional.
  - CGT rates (non-residential): 18% basic / 24% higher and additional.
- **Estimated saving:** `(user_div_rate − spouse_div_rate) × annual_dividends + (user_cgt_rate − spouse_cgt_rate) × expected_realised_gains`.
- **Data needed:** ✓ Already partially implemented (`buildCrossSpouseSuggestions`, line 330) but currently unsized — emits a copy-only suggestion. Add the saving estimate.
- **Headline copy:** "Holding your £X dividend portfolio in your spouse's name drops the dividend tax rate from 33.75% to 8.75%, saving £Y a year."
- **Priority:** `high`

### 12. Pension Contribution for a Non-Earning Spouse

- **Triggers when:** `household_calculation_mode = single_earner_couple` AND `spouse_age < 75` AND user has surplus disposable income
- **Mechanism:** Even with no earnings, a UK-resident under 75 can contribute up to £2,880/year to a personal pension and the government adds 25% basic-rate tax relief, grossing it up to £3,600. Compounds over decades and unlocks a second 25% tax-free lump sum + a second Personal Allowance in retirement.
- **Estimated saving:** £720/year direct uplift, plus retirement-phase tax efficiency.
- **Data needed:** ✓ `family_members.date_of_birth` (if present); ✗ confirmation that spouse holds or is willing to open a personal pension — could be implied from `tax_strategy_household_inputs.spouse_existing_pension_balance` if non-null but the field name suggests it isn't currently captured for non-working spouses. Likely a small extension to `capture_spouse_non_working_assets`.
- **Headline copy:** "A £2,880 contribution to your spouse's pension is topped up to £3,600 by the government, even though they have no earnings — that's an instant £720 boost."
- **Priority:** `medium`

---

## D. Other high-leverage strategies

### 13. Gift Aid Higher-Rate Relief

- **Triggers when:** `user_band ∈ {higher, additional}` AND `annual_charitable_donations > 0`
- **Mechanism:** Charity reclaims 20% basic-rate relief automatically (Gift Aid). Higher-rate donors can claim a further 20% (additional-rate: 25%) via Self Assessment, recovered as a tax refund or by extending the basic-rate band. Net cost of a £100 donation to a higher-rate taxpayer: £75. Net cost to an additional-rate taxpayer: £68.75.
- **Estimated saving:** `gross_donations × 0.25` (higher) or `× 0.3125` (additional) reclaimable.
- **Data needed:** ✗ Annual charitable donations — currently not captured. New field on `users` or new tool `capture_charitable_giving`.
- **Headline copy:** "Higher-rate taxpayers can reclaim 20% of charitable donations through Self Assessment. £X of donations means £Y back to you."
- **Priority:** `medium`

### 14. Tapered Annual Allowance Awareness

*(Warning class — surfaces a risk, not a saving.)*

- **Triggers when:** `users.adjusted_income > £260,000` AND `users.threshold_income > £200,000` (both gates required per CSJ redline — HMRC taper bites only when both apply)
- **Mechanism:** For high earners, the £60,000 Annual Allowance reduces by £1 for every £2 of adjusted income above £260,000, down to a £10,000 floor at £360,000. Contributions above the tapered allowance trigger an Annual Allowance charge at the user's marginal rate — easily £20k+ for a missed taper.
- **Estimated saving:** N/A — the suggestion is "check before contributing more". Avoiding a charge could be £20k+ depending on overshoot.
- **Data needed:** ✓ `users.annual_employment_income` is a starting point. ✗ Adjusted income requires bonus + employer pension contribution + other income — partially captured via `capture_salary_sacrifice` and dividend income, but not assembled into an `adjusted_income` view.
- **Headline copy:** "Your income may put your pension Annual Allowance below £60,000 due to tapering rules. Confirm with your pension provider before contributing further this year."
- **Priority:** `high` (warning class — surfaces ahead of normal suggestions; downside of missing the taper is a £20k+ Annual Allowance charge)

### 15. Joint Savings Split for Personal Savings Allowance Doubling

- **Triggers when:** `marital_status = married` AND user holds joint savings AND household interest income > one Personal Savings Allowance band but ≤ the sum of both partners' bands
- **Mechanism:** Interest on jointly-owned savings is split 50/50 by HMRC default. Two basic-rate spouses each get a £1,000 Personal Savings Allowance — total £2,000 tax-free. Splitting accounts between names (rather than holding all cash in one name) doubles the shelter. The same logic applies if both are higher-rate (£500 + £500 = £1,000) — but additional-rate has no PSA, so this strategy doesn't apply there.
- **Estimated saving:** `(joint_interest − single_PSA_band) × user_marginal_rate` saved (for the slice that would have been taxed in one name only).
- **Data needed:** ✓ `savings_account.joint_owner_id`, `ownership_percentage` — already supported via the joint-asset pattern (CLAUDE.md Rule #7).
- **Headline copy:** "Holding all £X of your savings in one name uses only one £1,000 Personal Savings Allowance. Splitting the account between you and your spouse uses both, saving £Y a year."
- **Priority:** `low`

---

## E. Lifecycle / dependant strategies

### 16. Lifetime ISA (under-40s)

- **Triggers when:** `user_age ≥ 18` AND `user_age < 40` AND `current_year_ISA_subscription < £20,000` AND user holds non-ISA savings or has surplus disposable income
- **Mechanism:** A Lifetime ISA accepts up to £4,000/year in contributions and the government adds a 25% bonus — up to £1,000/year of free money. The £4,000 counts toward the £20,000 overall annual ISA allowance, so it's a sub-allocation, not additional. Funds are accessible (a) for a first home purchase up to £450,000, or (b) from age 60 onwards. Withdrawals for any other reason carry a 25% penalty (which mathematically removes the bonus and a small extra slice). Must be opened before age 40; contributions allowed until age 50.
- **Estimated saving:** `min(£4,000, current_year_ISA_remaining, available_cash) × 0.25` per year, repeating until age 50.
- **Data needed:** ✓ `users.date_of_birth` (already captured in BASE_PERSONAL); ✓ `savings_account` for surplus cash signal. No new capture required.
- **Headline copy:** "You're under 40 — opening a Lifetime ISA and contributing £X a year unlocks a £Y government bonus, usable for a first home (up to £450,000) or from age 60."
- **Priority:** `medium`

### 17. Junior ISA for Dependants

- **Triggers when:** `family_members` contains a child with `age < 18`
- **Mechanism:** Each child under 18 has a separate £9,000/year Junior ISA subscription. Owned by the child, locked until 18, then automatically converts to an adult ISA. All interest, dividends, and capital gains inside the wrapper are tax-free for the child's lifetime in the wrapper. Contributions can come from anyone (parent, grandparent, etc.) and don't reduce the parent's £20,000 allowance.
- **Estimated saving:** Long-horizon — surfaces unused capacity rather than a single-year £. For a child aged 5 with £9,000/yr at 5% growth to age 18: ~£170k tax-sheltered pot vs ~£140k after-tax in a non-wrapped account at higher-rate.
- **Data needed:** ✓ `family_members.relationship` and `date_of_birth` — already captured in BASE_DEPENDANTS. No new capture required.
- **Headline copy:** "Each of your N children under 18 has a £9,000 annual Junior ISA allowance — up to £Y a year sheltered for them, separate from your own £20,000."
- **Priority:** `medium`

### 18. Junior Pension for Dependants

- **Triggers when:** `family_members` contains a child with `age < 18` AND user has surplus disposable income
- **Mechanism:** Anyone — including a child with no income — can hold a personal pension. Up to £2,880/year contributed net, the government adds 25% basic-rate relief, grossing it up to £3,600. Locked until pension access age (currently 57 from 2028). With ~50 years of compounding from age 5, a £2,880/year contribution at 5% growth becomes a multi-hundred-thousand-pound pot by retirement. Compounding window beats every other tax-shelter on a per-£ basis.
- **Estimated saving:** £720/year direct uplift per child + decades of tax-sheltered growth.
- **Data needed:** ✓ `family_members.relationship` and `date_of_birth` — already captured. No new capture required.
- **Headline copy:** "A £2,880 pension contribution per child (under 18) is topped up to £3,600 by the government — that's £720 of free money per child, every year."
- **Priority:** `medium`

---

## What this implies for capture (campaign-side changes)

Six of the eighteen strategies need data the SaveTax campaign doesn't currently collect (#16-18 use existing `users.date_of_birth` + `family_members` and need no new capture):

| # | Strategy | Capture change |
|---|---|---|
| 3 | Pension AA carry-forward | New tool `capture_pension_history` (last 3 years' inputs), insert in `STATE_CAMPAIGN_PENSION_CONTRIBS` |
| 4 | Salary sacrifice NI relief | Extend `capture_salary_sacrifice` with `is_salary_sacrifice: bool` + optional `employer_ni_rebate_pct: float` |
| 6 | Bed & ISA | Either capture `unrealised_gain` per holding (extra field on `create_holding` / `create_investment_account`) or compute from `purchase_price` + `current_value` (capture both) |
| 12 | Pension for non-earner | Already partially captured via `capture_spouse_non_working_assets`; verify `spouse_existing_pension_balance` is in scope, add if not |
| 13 | Gift Aid | New tool `capture_charitable_giving` (annual £), or add to dashboard as a post-onboarding optional input |
| 14 | Tapered AA | Compose `adjusted_income` AND `threshold_income` views from existing fields. Threshold income excludes employee pension contributions; adjusted income adds them back. Both gates required per CSJ redline — neither alone is sufficient. |

**This is the only place a campaign-specific prompt change matters.** Adding these tools to `OnboardingPromptBuilder::toolsForFocus('savetax')` (currently lines 118-127) would let the existing onboarding prompt collect them through the standard multi-entity rule, with no other prompt edits. The catalogue does not need any LLM-generated language for the strategies themselves — every line of dashboard copy above is template-able.

## What this implies for the calculator

`TaxStrategyCalculator.php` currently emits two collections (`assetShiftingSuggestions` and `crossSpouseSuggestions`) plus an allowance grid. The catalogue has 15 entries that don't all fit into those buckets. Recommend a small refactor:

```php
return new TaxStrategyOutputDTO(
    taxYear: $taxYear,
    calculationMode: $mode,
    userAllowances: $userAllowances,
    spouseAllowances: $spouseAllowances,
    recommendations: $recommendations,        // NEW — flat list of all 15-class strategies
    deltaVsBaseline: [],
);
```

Each `recommendation` becomes a typed DTO: `{type, priority, title, description, estimated_annual_tax_saved, category, requires_advice}`. `StrategyRecommendationList.vue` filters and sorts on the frontend. `assetShiftingSuggestions` / `crossSpouseSuggestions` collapse into this single list — no behavioural change for the existing 4 + 2 suggestions, just a uniform contract.

## Resolutions from v0.1 redline (CSJ, 30 April 2026)

1. **Lifetime ISA included as #16** — triggers on age 18-39 plus surplus capacity. Mechanism explains the eligibility criteria (first home up to £450k or access from age 60); we don't gate on a captured "first-home goal" because the LISA suggestion is benign for anyone under 40 with cash to wrap.
2. **Junior ISA included as #17, Junior Pension as #18** — both trigger purely on `family_members` rows with `age < 18`. No new capture needed.
3. **Tapered Annual Allowance (#14) promoted to `high`** — warning class, surfaces ahead of normal suggestions because the downside of missing it is a £20k+ HMRC charge.
4. **Venture Capital Trust / Enterprise Investment Scheme / Seed EIS / Business Asset Disposal Relief excluded** from this campaign — out of scope.
5. **Headline copy cap raised to ≤220 chars** with optional **Title + Sub-line** split for richer items. #9 split applied; other entries fit comfortably under 220 chars as one sentence.
6. **#14 second gate added** — trigger now requires `adjusted_income > £260,000` AND `threshold_income > £200,000` (both required by HMRC for the taper to bite).

## Implementation phasing (proposed)

The catalogue has 18 strategies. Recommended split for execution to avoid one giant PR:

| Phase | Scope | Rationale |
|---|---|---|
| 1 | Calculator refactor → flat `recommendations[]` DTO; migrate the existing 4 + 2 suggestions onto it without behaviour change | Prerequisite for everything else; mechanical refactor; small PR; easy to verify against current output |
| 2 | Strategies that need NO new capture: #1, #2, #5, #7, #9 (expanded), #10, #11 (sized), #15, #16, #17, #18 | Highest impact / most universal; can land before any campaign-flow change |
| 3 | Strategies needing new capture in the existing campaign states: #4 (salary sacrifice extension), #6 (unrealised gains on holdings), #12 (spouse pension flag) | Prompt-tool additions, schema migration, calculator generators |
| 4 | Strategies needing new campaign states / tools: #3 (pension history), #13 (Gift Aid) | New `STATE_CAMPAIGN_*` or new capture tools; biggest scope creep, defer |
| 5 | Composed-income strategies: #14 tapered AA (needs `adjusted_income` + `threshold_income` views) | Depends on Phase 3 + 4 having captured the necessary inputs |

Each phase ends with a green Pest sweep + a live BS-NN browser walk-through against the SaveTax flow before the next phase starts, per Rule #15.

---

*v0.2 — 30 April 2026. Post-redline spec, ready for implementation. Awaiting CSJ go-ahead on Phase 1 (calculator refactor) before any code lands.*
