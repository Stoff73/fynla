# Cash & Savings Recommendation Engine: Decision Tree & Research Reference

> Complete mapping of every decision path, user-facing message, product analysis, and the context data that drives each output.
>
> **Target engine version:** v1.0.0 | **Research date:** 2026-03-14 | **Tax year:** 2025/26

---

## Table of Contents

1. [Engine Pipeline Overview](#1-engine-pipeline-overview)
2. [User Context: Data Inputs](#2-user-context-data-inputs)
3. [Phase 1: Data Readiness Gate](#3-phase-1-data-readiness-gate)
4. [Phase 2: Emergency Fund Assessment](#4-phase-2-emergency-fund-assessment)
5. [Phase 3: Tax Efficiency Analysis](#5-phase-3-tax-efficiency-analysis)
6. [Phase 4: Rate Optimisation](#6-phase-4-rate-optimisation)
7. [Phase 5: Product Suitability Engine](#7-phase-5-product-suitability-engine)
8. [Phase 6: FSCS Protection Assessment](#8-phase-6-fscs-protection-assessment)
9. [Phase 7: Savings vs Debt Analysis](#9-phase-7-savings-vs-debt-analysis)
10. [Phase 8: Savings vs Investment Decision](#10-phase-8-savings-vs-investment-decision)
11. [Phase 9: Goal-Linked Savings Optimisation](#11-phase-9-goal-linked-savings-optimisation)
12. [Phase 10: Children's Savings](#12-phase-10-childrens-savings)
13. [Phase 11: Spouse Optimisation](#13-phase-11-spouse-optimisation)
14. [Phase 12: Life Event Impact Assessment](#14-phase-12-life-event-impact-assessment)
15. [Phase 13: Savings Adequacy Summary](#15-phase-13-savings-adequacy-summary)
16. [Phase 14: Conflict Resolution](#16-phase-14-conflict-resolution)
17. [Output Formatting & Priority](#17-output-formatting--priority)
18. [Savings Product Reference](#18-savings-product-reference)
19. [Thresholds & Constants Reference](#19-thresholds--constants-reference)
20. [Config Message Key Reference](#20-config-message-key-reference)

---

## 1. Engine Pipeline Overview

```
User Request
    |
    v
[Phase 1]  DataReadinessService ──── can_proceed = false? ──> STOP (return readiness blocks only)
    |
    | can_proceed = true
    v
[Phase 2]  EmergencyFundAssessmentService ──> emergency fund status, shortfall, top-up plan
    |
    v
[Phase 3]  TaxEfficiencyAnalysisService ──> PSA assessment, starting rate, ISA strategy
    |
    v
[Phase 4]  RateOptimisationService ──> rate comparisons, switch recommendations, maturity alerts
    |
    v
[Phase 5]  ProductSuitabilityEngine ──> product recommendations matched to user profile
    |
    v
[Phase 6]  FSCSProtectionService ──> institution exposure, concentration risk, spreading recommendations
    |
    v
[Phase 7]  DebtComparisonService ──> savings vs debt repayment analysis
    |
    v
[Phase 8]  CashVsInvestmentService ──> excess cash analysis, cash drag, investment trigger
    |
    v
[Phase 9]  GoalSavingsOptimiser ──> goal-account matching, timeline-based product selection
    |
    v
[Phase 10] ChildrensSavingsService ──> Junior ISA, parental settlement rules, child products
    |
    v
[Phase 11] SpouseSavingsOptimiser ──> PSA shifting, ISA coordination, account ownership
    |
    v
[Phase 12] LifeEventImpactService ──> life event modifiers (blocks, triggers, adjustments)
    |
    v
[Phase 13] SavingsAdequacySummary ──> overall health assessment, key metrics
    |
    v
[Phase 14] ConflictResolutionService ──> merged, deduplicated, priority-sorted recommendations
    |
    v
[Output]   RecommendationOutputFormatter ──> sorted, formatted API response
```

**Key principle:** Each phase can modify the surplus available to subsequent phases. Emergency fund shortfalls reduce surplus. Debt repayment recommendations reduce available savings capacity. Life event blocks can override product recommendations.

---

## 2. User Context: Data Inputs

### 2.1 Personal Profile

| Field | Source | Used By |
|-------|--------|---------|
| `age` | Calculated from `user.date_of_birth` | LISA eligibility (18-39 to open, contribute until 50), Junior ISA age check, Help to Save |
| `date_of_birth` | `user.date_of_birth` | Phase 1 readiness gate |
| `gender` | `user.gender` | Life expectancy for retirement savings horizon |
| `marital_status` | `user.marital_status` | Spouse optimisation gate, Marriage Allowance |
| `employment_status` | `user.employment_status` | Emergency fund target months, Help to Save eligibility |
| `retirement_age` | `user.retirement_age` or default 67 | Savings horizon, decumulation planning |
| `years_to_retirement` | `retirement_age - age` | Cash vs investment decision, product term selection |
| `is_homeowner` | Boolean from properties | First-time buyer LISA eligibility, offset mortgage analysis |
| `has_mortgage` | Boolean from mortgages | Offset mortgage analysis |
| `mortgage_rate` | From active mortgage | Offset savings comparison |
| `has_dependents` | `familyMembers->count() > 0` | Children's savings phase |
| `number_of_dependents` | Count of family members | Children's savings capacity |
| `dependents` | Family members collection | Junior ISA beneficiary matching, ages |
| `uk_resident` | `user.uk_resident` | ISA eligibility, NS&I eligibility |

### 2.2 Financial Profile

| Field | Source | Used By |
|-------|--------|---------|
| `gross_annual_income` | Sum of all income sources | Tax band derivation, PSA amount, starting rate eligibility |
| `non_savings_income` | Employment + self-employment + rental + other | Starting rate for savings calculation |
| `savings_income` | Interest from all savings accounts | PSA breach check, starting rate usage |
| `net_monthly_income` | `gross * 0.7 / 12` (fallback estimate) | Disposable income calculation |
| `monthly_expenditure` | `user.monthly_expenditure` | Emergency fund target, affordability |
| `disposable_income` | `net_monthly_income - monthly_expenditure` | Surplus for savings recommendations |
| `disposable_percent` | `(disposable_income / net_monthly_income) * 100` | Savings capacity tier |
| `tax_band` | Derived from gross income | PSA, starting rate, ISA vs taxable decision |
| `personal_allowance` | 12,570 (tapered above 100k) | Starting rate calculation |
| `scottish_taxpayer` | `user.scottish_taxpayer` | Scottish rate bands (affects marginal rate, not PSA) |

### 2.3 Savings Profile

| Field | Source | Used By |
|-------|--------|---------|
| `total_savings` | Sum of all `savings_accounts.current_balance` | Emergency fund runway, FSCS exposure, adequacy |
| `total_emergency_fund` | Sum where `is_emergency_fund = true` | Emergency fund assessment |
| `total_easy_access` | Sum where `access_type = 'immediate'` | Liquidity assessment |
| `total_notice` | Sum where `access_type = 'notice'` | Liquidity ladder |
| `total_fixed` | Sum where `access_type = 'fixed'` | Maturity tracking |
| `total_isa_savings` | Sum where `is_isa = true` | ISA strategy |
| `total_non_isa_savings` | Sum where `is_isa = false` | PSA exposure, Cash ISA transfer candidates |
| `accounts_by_institution` | Grouped by `institution` | FSCS concentration check |
| `weighted_average_rate` | Balance-weighted mean of `interest_rate` | Rate health metric |
| `annual_interest_earned` | Sum of `balance * rate` across all accounts | PSA usage check |
| `maturing_accounts` | Where `maturity_date` within 90 days | Maturity alert |
| `promotional_rate_accounts` | Where promotional rate is expiring | Rate alert |

### 2.4 ISA Profile

| Field | Source | Used By |
|-------|--------|---------|
| `isa_remaining` | 20,000 - current year subscriptions (all ISA types) | ISA recommendation capacity |
| `cash_isa_subscribed` | Current year Cash ISA subscriptions | Cash ISA vs S&S ISA split |
| `ss_isa_subscribed` | Current year Stocks & Shares ISA subscriptions | Cross-module coordination |
| `lisa_subscribed` | Current year LISA subscriptions | LISA capacity |
| `lisa_remaining` | 4,000 - current year LISA subscriptions | LISA recommendation |
| `has_cash_isa` | Boolean | Transfer strategy |
| `has_flexible_isa` | Boolean (ISA provider supports flex) | Withdrawal/replacement strategy |
| `previous_year_isa_balances` | Cash ISA balances from prior years | Transfer to S&S ISA candidates |

### 2.5 Debt Profile

| Field | Source | Used By |
|-------|--------|---------|
| `debts` | All debts excluding mortgage and student loan | Savings vs debt comparison |
| `high_interest_debts` | Debts with `interest_rate > 15%` | Critical: save nothing, pay debt |
| `medium_interest_debts` | Debts with `interest_rate > best_savings_rate` | Moderate: split savings/debt |
| `low_interest_debts` | Debts with `interest_rate <= best_savings_rate` | Savings preferred |
| `total_debt_cost` | Annual interest cost across all debts | Comparison metric |
| `mortgage_rate` | Active mortgage interest rate | Offset mortgage analysis |
| `mortgage_balance` | Active mortgage outstanding | Offset capacity |
| `student_loan` | Student loan balance | Excluded from debt comparison (income-contingent) |

### 2.6 Risk Profile

| Field | Source | Used By |
|-------|--------|---------|
| `risk_level` | `riskProfile.risk_level` | Cash vs investment decision threshold |
| `risk_tolerance` | `riskProfile.risk_tolerance` | Product selection (cash-only for very low tolerance) |
| `investment_experience` | `riskProfile.investment_experience` | Product complexity suitability |

### 2.7 Goals Profile

| Field | Source | Used By |
|-------|--------|---------|
| `savings_goals` | All goals with `goal_type` in savings-related categories | Goal-account matching |
| `emergency_fund_goal` | Goal where `goal_type = 'emergency_fund'` | Emergency fund assessment |
| `short_term_goals` | Goals with target_date < 2 years | Easy access product recommendation |
| `medium_term_goals` | Goals with target_date 2-5 years | Notice/fixed product recommendation |
| `first_home_goal` | Goal where `goal_type = 'property_purchase'` | LISA eligibility check |

### 2.8 Spouse Profile (only if married/civil_partnership AND spouse exists)

| Field | Source | Used By |
|-------|--------|---------|
| `spouse.name` | `spouse.name` | Message personalisation |
| `spouse.gross_annual_income` | Calculated from spouse income sources | Spouse tax band |
| `spouse.tax_band` | Derived from spouse income | PSA optimisation, account ownership |
| `spouse.savings_income` | Interest from spouse's savings | Spouse PSA usage |
| `spouse.psa_amount` | PSA based on spouse tax band | PSA comparison |
| `spouse.psa_remaining` | `psa_amount - savings_income` | PSA headroom |
| `spouse.isa_remaining` | Spouse's ISA allowance remaining | ISA coordination |
| `spouse.total_savings` | Sum of spouse's savings | FSCS coordination |

---

## 3. Phase 1: Data Readiness Gate

### Decision Tree

```
Phase 1 Prerequisites (any BLOCK = engine stops)
|
+-- date_of_birth is null?
|   YES -> BLOCK [SR1]: "Your date of birth is needed to assess
|                        ISA eligibility and savings product suitability."
|
+-- gross_annual_income <= 0?
|   YES -> BLOCK [SR2]: "Your income details are needed to calculate
|                        your tax band and Personal Savings Allowance."
|
|   ANY BLOCK above? -> can_proceed = false, STOP
|
Phase 2 Financial Data
|
+-- monthly_expenditure is null or <= 0?
|   YES -> BLOCK [SR3]: "Your monthly expenditure is needed to calculate
|                        emergency fund requirements and savings targets."
|
+-- employment_status is null?
|   YES -> WARN [SR4]: "Adding your employment status helps us set an
|                       appropriate emergency fund target."
|
Phase 3 Savings Data
|
+-- No savings accounts at all?
|   YES -> INFO [SR5]: "Add your existing savings accounts to receive
|                       rate comparisons and tax efficiency recommendations."
|
+-- Has savings but no interest rates entered?
|   YES -> WARN [SR6]: "Adding interest rates to your savings accounts
|                       enables rate comparison and tax analysis."
|
Phase 4 Tax Data
|
+-- Income sources incomplete (only gross_annual set, no breakdown)?
|   YES -> WARN [SR7]: "Breaking down your income by type (employment,
|                       savings interest, dividends) improves tax analysis."
|
Phase 5 Spouse Data
|
+-- Married/civil_partnership but no spouse linked?
    YES -> INFO [SR8]: "Link your partner's account to unlock household
                        savings optimisation strategies."
```

### Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| SR1 | `date_of_birth` is null | `block` | `savings_readiness.block.date_of_birth` | "Your date of birth is needed to assess ISA eligibility and savings product suitability." |
| SR2 | `gross_annual_income <= 0` | `block` | `savings_readiness.block.income` | "Your income details are needed to calculate your tax band and Personal Savings Allowance." |
| SR3 | `monthly_expenditure` null or <= 0 | `block` | `savings_readiness.block.expenditure` | "Your monthly expenditure is needed to calculate emergency fund requirements and savings targets." |
| SR4 | `employment_status` null | `warn` | `savings_readiness.warn.employment_status` | "Adding your employment status helps us set an appropriate emergency fund target." |
| SR5 | No savings accounts | `info` | `savings_readiness.info.accounts` | "Add your existing savings accounts to receive rate comparisons and tax efficiency recommendations." |
| SR6 | Accounts without rates | `warn` | `savings_readiness.warn.interest_rates` | "Adding interest rates to your savings accounts enables rate comparison and tax analysis." |
| SR7 | Income not broken down | `warn` | `savings_readiness.warn.income_breakdown` | "Breaking down your income by type (employment, savings interest, dividends) improves tax analysis." |
| SR8 | Married, no spouse linked | `info` | `savings_readiness.info.spouse` | "Link your partner's account to unlock household savings optimisation strategies." |

---

## 4. Phase 2: Emergency Fund Assessment

**Existing services:** `EmergencyFundCalculator`, `LiquidityAnalyzer`

This is the highest-priority savings decision. Every user needs an adequate emergency fund before any other savings optimisation applies.

### 4.1 Emergency Fund Target Calculation

```
Determine target months based on employment status:
|
+-- employment_status = "employed" (full-time, permanent)?
|   TARGET = 3-6 months (use 6 as default target)
|
+-- employment_status = "self_employed"?
|   TARGET = 6-12 months (use 9 as default target)
|   Note: Income less predictable, no employer sick pay/redundancy
|
+-- employment_status = "unemployed"?
|   TARGET = 6+ months (use 6 as default target)
|   Note: Critical priority, reduce non-essential spending
|
+-- employment_status = "retired"?
|   TARGET = 3 months (use 3 as default target)
|   Note: Regular pension/state pension income, lower volatility
|
+-- employment_status = "contract" or "freelance"?
|   TARGET = 6-9 months (use 9 as default target)
|   Note: Contract gaps, irregular income
|
+-- employment_status = "part_time"?
|   TARGET = 6 months
|   Note: Less financial cushion than full-time
|
+-- employment_status is null or unknown?
    TARGET = 6 months (conservative default)
```

**Target adjustments:**

```
Base target from employment status
    |
    +-- Has dependents?
    |   YES -> Add 1 month to target
    |
    +-- Single income household (married, spouse not working)?
    |   YES -> Add 2 months to target
    |
    +-- Has mortgage?
    |   YES -> Add 1 month to target (mortgage payments are non-negotiable)
    |
    +-- Has chronic health condition or disability?
    |   YES -> Add 1 month to target
    |
    = ADJUSTED TARGET (capped at 12 months)
```

### 4.2 Emergency Fund Adequacy Assessment

```
Calculate runway:
    runway = total_emergency_fund / monthly_expenditure

Categorise:
|
+-- runway >= adjusted_target?
|   CATEGORY = "Fully Funded" [EF1]
|   STATUS = "excellent"
|   Message: "Your emergency fund covers {runway} months of expenses,
|            meeting your {target}-month target."
|   Action: Consider whether excess could be better deployed
|
+-- runway >= (adjusted_target * 0.75)?
|   CATEGORY = "Nearly There" [EF2]
|   STATUS = "good"
|   shortfall = (adjusted_target * monthly_expenditure) - total_emergency_fund
|   Message: "Your emergency fund covers {runway} months. You need an
|            additional {shortfall} to reach your {target}-month target."
|
+-- runway >= (adjusted_target * 0.5)?
|   CATEGORY = "Needs Attention" [EF3]
|   STATUS = "fair"
|   Message: "Your emergency fund covers {runway} months -- about half your
|            {target}-month target. Consider increasing regular savings."
|
+-- runway >= 1?
|   CATEGORY = "Insufficient" [EF4]
|   STATUS = "warning"
|   Message: "Your emergency fund covers only {runway} months. As {employment_label},
|            you should aim for {target} months. Prioritise building this up."
|
+-- runway < 1?
    CATEGORY = "Critical" [EF5]
    STATUS = "critical"
    Message: "Your emergency fund covers less than 1 month of expenses.
             This is your top financial priority. All surplus should go here first."
    Action: Block all non-essential savings recommendations until >= 1 month
```

### 4.3 Emergency Fund Location Assessment

```
Where is the emergency fund held?
|
+-- All in easy access accounts?
|   GOOD: "Your emergency fund is appropriately held in easy access accounts."
|
+-- Some in notice accounts (notice_period_days > 0)?
|   WARNING [EF6]: "Part of your emergency fund ({amount}) requires
|   {days}-day notice to access. In a genuine emergency, you may not
|   be able to access these funds quickly enough."
|   RECOMMENDATION: "Move emergency fund to instant access accounts."
|
+-- Some in fixed-term accounts?
|   WARNING [EF7]: "Part of your emergency fund ({amount}) is locked until
|   {maturity_date}. Fixed-term accounts are not suitable for emergency reserves."
|   RECOMMENDATION: "When this matures, move to easy access."
|
+-- Emergency fund across multiple institutions?
|   +-- All within FSCS limit per institution?
|   |   GOOD: "Your emergency fund is well-spread across institutions."
|   +-- Any institution over FSCS limit?
|       WARNING [EF8]: Covered in Phase 6 (FSCS Assessment)
```

### 4.4 Emergency Fund Top-Up Plan

```
If shortfall > 0:
|
+-- Calculate monthly top-up to reach target in 12 months:
|   monthly_top_up = shortfall / 12
|
+-- Is monthly_top_up > disposable_income?
|   YES -> Extend timeline:
|          months_to_target = shortfall / (disposable_income * 0.5)
|          Message [EF9]: "Saving {half_disposable} per month, you can
|                         reach your emergency fund target in {months} months."
|
+-- Is monthly_top_up <= disposable_income * 0.25?
|   YES -> Comfortable:
|          Message [EF10]: "Saving {monthly_top_up} per month (about {percent}%
|                          of your disposable income), you can build your emergency
|                          fund in 12 months."
|
+-- Otherwise:
    Message [EF11]: "Saving {monthly_top_up} per month will reach your target
                    in 12 months. If this is too much, saving {reduced_amount}
                    per month will take {extended_months} months."
```

### Emergency Fund Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| EF1 | runway >= target | `success` | `emergency_fund.fully_funded` | "Your emergency fund covers {runway} months of expenses, meeting your {target}-month target." |
| EF2 | runway >= 75% of target | `info` | `emergency_fund.nearly_there` | "Your emergency fund covers {runway} months. You need an additional {shortfall} to reach your {target}-month target." |
| EF3 | runway >= 50% of target | `warn` | `emergency_fund.needs_attention` | "Your emergency fund covers {runway} months -- about half your {target}-month target." |
| EF4 | runway >= 1 month | `warn` | `emergency_fund.insufficient` | "Your emergency fund covers only {runway} months. As {employment_label}, you should aim for {target} months." |
| EF5 | runway < 1 month | `critical` | `emergency_fund.critical` | "Your emergency fund covers less than 1 month of expenses. This is your top financial priority." |
| EF6 | Emergency fund in notice account | `warn` | `emergency_fund.notice_account` | "Part of your emergency fund requires {days}-day notice to access." |
| EF7 | Emergency fund in fixed account | `warn` | `emergency_fund.fixed_account` | "Part of your emergency fund is locked until {maturity_date}." |
| EF9 | Top-up exceeds disposable | `info` | `emergency_fund.extended_plan` | "Saving {amount} per month, you can reach your target in {months} months." |
| EF10 | Top-up comfortable | `info` | `emergency_fund.comfortable_plan` | "Saving {amount} per month will build your emergency fund in 12 months." |
| EF11 | Top-up moderate | `info` | `emergency_fund.moderate_plan` | "Saving {amount} per month will reach your target in 12 months." |

---

## 5. Phase 3: Tax Efficiency Analysis

### 5.1 Personal Savings Allowance (PSA) Assessment

The PSA is the amount of savings interest a person can earn tax-free each year. It depends on their income tax band.

**CRITICAL: No Tax Recalculation.** The user's tax band is already calculated and stored in the database by the Income module. The savings engine MUST fetch `tax_band` and `psa_amount` from the database — it must NOT recalculate these values. The Income section is the single source of truth for all tax banding. All PSA amounts are sourced from `TaxConfigService` — never hardcoded.

**PSA Amounts (2025/26) — via `TaxConfigService::getPersonalSavingsAllowance()`:**

| Tax Band | Income Range | PSA Amount | Source |
|----------|-------------|------------|--------|
| Non-taxpayer | Below £12,570 | N/A (starting rate applies instead) | `TaxConfigService` |
| Basic rate | £12,571 - £50,270 | £1,000 | `TaxConfigService` |
| Higher rate | £50,271 - £125,140 | £500 | `TaxConfigService` |
| Additional rate | Above £125,140 | £0 | `TaxConfigService` |

```
Step 1: Fetch tax band from database (DO NOT recalculate)
|
+-- tax_band = user.tax_band (pre-calculated by Income module, stored in DB)
+-- psa_amount = TaxConfigService::getPersonalSavingsAllowance(tax_band)
|
|   The Income module calculates and stores the user's tax band based on
|   all income sources. The Savings engine reads this value — it never
|   derives tax bands independently. This prevents divergence between
|   modules and ensures consistency across the application.

Step 2: Calculate annual interest earned across all taxable savings
    annual_interest = SUM(account.balance * account.interest_rate)
                      WHERE is_isa = false
                      (ISA interest is tax-free, does not count)

Step 3: Assess PSA usage
|
+-- TAX_BAND = "additional"?
|   Message [TE1]: "As an additional rate taxpayer, you have no Personal
|   Savings Allowance. All savings interest is taxable. Prioritise
|   tax-free wrappers (Cash ISA, Premium Bonds)."
|   PRIORITY: critical
|
+-- annual_interest > PSA?
|   breach = annual_interest - PSA
|   tax_cost = breach * marginal_rate
|   Message [TE2]: "Your savings interest of {annual_interest} exceeds your
|   {PSA} Personal Savings Allowance by {breach}. This costs approximately
|   {tax_cost} in tax. Consider moving savings to a Cash ISA."
|   PRIORITY: high
|
+-- annual_interest > PSA * 0.75?
|   headroom = PSA - annual_interest
|   Message [TE3]: "Your savings interest of {annual_interest} uses {percent}%
|   of your {PSA} Personal Savings Allowance. You have {headroom} of headroom
|   before interest becomes taxable."
|   PRIORITY: medium
|
+-- annual_interest <= PSA?
    Message [TE4]: "Your savings interest of {annual_interest} is within your
    {PSA} Personal Savings Allowance. No tax is due on this interest."
    PRIORITY: info
    Note: "If you are a basic rate taxpayer with interest well within your PSA,
           a Cash ISA may not be the most efficient use of your ISA allowance.
           Consider a Stocks & Shares ISA instead."
```

### 5.2 Starting Rate for Savings

The starting rate for savings allows up to £5,000 of savings income to be taxed at 0%, but it is reduced by every £1 of non-savings income above the personal allowance.

```
Starting Rate Assessment:
|
+-- non_savings_income > personal_allowance + 5,000 (i.e., > 17,570)?
|   STARTING_RATE_BAND = 0
|   Message: N/A (not eligible)
|
+-- non_savings_income <= personal_allowance (i.e., <= 12,570)?
|   STARTING_RATE_BAND = 5,000
|   Message [TE5]: "You qualify for the full £5,000 starting rate for savings.
|   Combined with your Personal Savings Allowance, you can earn up to
|   {5000 + PSA} in interest tax-free."
|
+-- non_savings_income between personal_allowance and personal_allowance + 5,000?
    STARTING_RATE_BAND = 5,000 - (non_savings_income - personal_allowance)
    Message [TE6]: "You qualify for a reduced starting rate for savings of
    {starting_rate_band}. Combined with your PSA, you can earn up to
    {starting_rate_band + PSA} in interest tax-free."
```

**Key rule:** The starting rate is separate from the PSA. A person can benefit from both. For example, a basic rate taxpayer earning £14,000 has:
- Starting rate band: £5,000 - (£14,000 - £12,570) = £3,570 at 0%
- PSA: £1,000 at 0%
- Total tax-free interest: £4,570

### 5.3 Cash ISA vs Taxable Savings Decision

```
Should the user open/fund a Cash ISA?
|
+-- TAX_BAND = "additional" (PSA = 0)?
|   YES -> STRONGLY RECOMMEND Cash ISA [TE7]
|   "You have no Personal Savings Allowance. Every pound of interest earned
|    outside an ISA is taxed at 45%. A Cash ISA is essential."
|   PRIORITY: critical
|
+-- TAX_BAND = "higher" AND annual_interest > 500 (PSA breached)?
|   YES -> RECOMMEND Cash ISA [TE8]
|   "Your interest exceeds your £500 Personal Savings Allowance.
|    Moving savings to a Cash ISA prevents further tax on interest."
|   PRIORITY: high
|
+-- TAX_BAND = "basic" AND annual_interest > 1,000 (PSA breached)?
|   YES -> RECOMMEND Cash ISA [TE9]
|   "Your interest exceeds your £1,000 Personal Savings Allowance.
|    A Cash ISA would shelter the excess from tax."
|   PRIORITY: high
|
+-- TAX_BAND = "basic" AND annual_interest < 500?
|   NO ISA NEEDED for tax purposes [TE10]
|   "Your savings interest is well within your Personal Savings Allowance.
|    Consider using your ISA allowance for a Stocks & Shares ISA instead,
|    which shelters investment growth and dividends from tax."
|   PRIORITY: info
|
+-- Approaching PSA breach (interest > PSA * 0.75)?
    CONSIDER Cash ISA [TE11]
    "Your interest is approaching your Personal Savings Allowance limit.
     Consider opening a Cash ISA now to prevent future tax charges."
    PRIORITY: medium
```

### 5.4 ISA Allowance Usage Strategy

```
How should the £20,000 ISA allowance be split?
|
+-- Emergency fund not fully funded AND no Cash ISA?
|   RECOMMEND: Cash ISA for emergency fund portion [TE12]
|
+-- PSA breached or near breach?
|   RECOMMEND: Cash ISA to shelter taxable interest [TE13]
|   Amount: enough to bring interest below PSA
|
+-- Short-term goals (< 3 years)?
|   RECOMMEND: Cash ISA or fixed-rate Cash ISA for goal amount [TE14]
|
+-- Long-term goals (> 5 years) or excess savings?
|   RECOMMEND: Stocks & Shares ISA for remainder [TE15]
|   "For savings you won't need for 5+ years, a Stocks & Shares ISA
|    typically offers better long-term returns than cash."
|
+-- First-time buyer, age 18-39, property < £450,000?
    RECOMMEND: LISA first (£4,000), then split remainder [TE16]
    "The 25% government bonus on a Lifetime ISA is the highest guaranteed
     return available. Prioritise this before other ISA types."
```

### 5.5 Cash ISA Transfer Rules

```
ISA Transfer Assessment:
|
+-- Has Cash ISA from previous years with low rate?
|   +-- Rate below market benchmark?
|       YES -> RECOMMEND transfer to better Cash ISA [TE17]
|       "Your Cash ISA from {year} earns {rate}%. The best available
|        Cash ISA rate is {best_rate}%. You can transfer without
|        affecting your current year allowance."
|
+-- Has large Cash ISA balance AND long-term horizon AND medium+ risk?
|   YES -> RECOMMEND transfer to Stocks & Shares ISA [TE18]
|   "Your Cash ISA balance of {balance} could generate better long-term
|    returns in a Stocks & Shares ISA. ISA-to-ISA transfers preserve
|    your tax-free status."

Transfer rules to communicate:
- Previous year ISA balances: Can be partially or fully transferred
- Current year subscriptions: Must be transferred IN FULL or not at all
- Transfers do NOT use up current year allowance (they are transfers, not new subscriptions)
- Flexible ISA: if you withdraw AND replace within same tax year, no allowance impact
- Non-flexible ISA: any withdrawal uses up that amount of allowance permanently
```

### Tax Efficiency Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| TE1 | Additional rate, PSA = 0 | `critical` | `tax.psa.additional_rate` | "As an additional rate taxpayer, you have no Personal Savings Allowance. All savings interest is taxable." |
| TE2 | PSA breached | `high` | `tax.psa.breached` | "Your savings interest of {interest} exceeds your {psa} Personal Savings Allowance by {breach}." |
| TE3 | PSA nearly breached (>75%) | `medium` | `tax.psa.approaching` | "Your savings interest uses {percent}% of your Personal Savings Allowance." |
| TE4 | PSA within limit | `info` | `tax.psa.within_limit` | "Your savings interest is within your Personal Savings Allowance. No tax is due." |
| TE5 | Full starting rate available | `info` | `tax.starting_rate.full` | "You qualify for the full £5,000 starting rate for savings." |
| TE6 | Partial starting rate | `info` | `tax.starting_rate.partial` | "You qualify for a reduced starting rate for savings of {amount}." |
| TE7 | Additional rate, recommend ISA | `critical` | `tax.isa.essential` | "You have no Personal Savings Allowance. A Cash ISA is essential." |
| TE8 | Higher rate, PSA breached | `high` | `tax.isa.recommended_higher` | "Your interest exceeds your £500 Personal Savings Allowance." |
| TE9 | Basic rate, PSA breached | `high` | `tax.isa.recommended_basic` | "Your interest exceeds your £1,000 Personal Savings Allowance." |
| TE10 | Basic rate, PSA comfortable | `info` | `tax.isa.not_needed` | "Consider using your ISA allowance for a Stocks & Shares ISA instead." |
| TE11 | Approaching PSA limit | `medium` | `tax.isa.consider` | "Your interest is approaching your Personal Savings Allowance limit." |
| TE17 | Old ISA poor rate | `medium` | `tax.isa_transfer.better_rate` | "Your Cash ISA from {year} earns {rate}%. Transfer to a better rate." |
| TE18 | Cash ISA to S&S ISA | `medium` | `tax.isa_transfer.to_investment` | "Your Cash ISA balance could generate better long-term returns in a Stocks & Shares ISA." |

---

## 6. Phase 4: Rate Optimisation

**Existing service:** `RateComparator`

### 6.1 Rate Comparison Engine

```
For each savings account:
|
+-- Get appropriate market benchmark:
|   +-- access_type = "immediate" AND is_isa = false -> benchmark: easy_access
|   +-- access_type = "immediate" AND is_isa = true  -> benchmark: easy_access_isa
|   +-- access_type = "notice" AND is_isa = false     -> benchmark: notice
|   +-- access_type = "notice" AND is_isa = true      -> benchmark: notice_isa
|   +-- access_type = "fixed" -> Match by remaining term:
|       +-- >= 3 years -> fixed_3_year / fixed_3_year_isa
|       +-- >= 2 years -> fixed_2_year / fixed_2_year_isa
|       +-- < 2 years  -> fixed_1_year / fixed_1_year_isa
|
+-- Compare account rate to benchmark:
|   difference = account_rate - market_rate
|
+-- Categorise:
    +-- difference >= +1.0% -> "Excellent" (above market)
    +-- difference >= 0%    -> "Good" (at or above market)
    +-- difference >= -0.5% -> "Competitive" (close to market)
    +-- difference >= -1.0% -> "Fair" (below market)
    +-- difference < -1.0%  -> "Poor" (significantly below market)
```

### 6.2 Rate Improvement Recommendations

```
For each account with category "Fair" or "Poor":
|
+-- Calculate annual interest difference:
|   potential_gain = balance * (market_rate - account_rate)
|
+-- potential_gain > £50?
|   YES -> RECOMMEND [RO1]: "Switch to better rate"
|   "Your {institution} {account_type} earns {account_rate}%. The best
|    comparable rate is {market_rate}%. Switching would earn an extra
|    {potential_gain} per year on your {balance} balance."
|
+-- Is this a fixed-rate account?
|   YES -> Check if early withdrawal penalty applies:
|   "This is a fixed-rate account maturing on {maturity_date}. Early
|    withdrawal may incur a penalty of {penalty_days} days' interest.
|    Wait until maturity to switch."
|
+-- Is this a Cash ISA?
    YES -> "You can transfer your Cash ISA to a new provider without
            losing your tax-free status. Use an ISA transfer -- do NOT
            withdraw and reopen, or you will lose your ISA allowance."
```

### 6.3 Maturity & Rate Expiry Alerts

**Notification channels:** All maturity and rate expiry alerts must be delivered via BOTH:
1. **Dashboard notification** — displayed in the "What Drives This" view and savings overview
2. **Email notification** — sent to the user's registered email address

Email notifications should be triggered at:
- **90 days before** maturity/expiry: first email alert
- **30 days before** maturity/expiry: urgent follow-up email
- **7 days before** maturity/expiry: final reminder email

Dashboard notifications persist until the user takes action or the account matures/expires.

```
Check all accounts for upcoming events:
|
+-- Fixed-rate account maturing within 90 days?
|   ALERT [RO2]: "Your {institution} fixed-rate account matures on
|   {maturity_date}. Start comparing rates now -- many accounts
|   default to a poor easy-access rate after maturity."
|   PRIORITY: high (within 30 days), medium (30-90 days)
|   NOTIFY: dashboard + email
|
+-- Fixed-rate account maturing within 30 days?
|   URGENT [RO3]: "Your {institution} fixed-rate account matures in
|   {days} days. Arrange your next account now to avoid losing interest."
|   NOTIFY: dashboard + email (urgent)
|
+-- Fixed-rate account maturing within 7 days?
|   CRITICAL [RO3a]: "Your {institution} fixed-rate account matures in
|   {days} days. Act now to avoid defaulting to a low rate."
|   NOTIFY: dashboard + email (final reminder)
|
+-- Promotional/introductory rate expiring?
|   ALERT [RO4]: "The promotional rate on your {institution} account
|   expires on {date}. The rate will drop from {promo_rate}% to
|   {standard_rate}%. Consider switching."
|   NOTIFY: dashboard + email
|
+-- Bank of England base rate changed in last 3 months?
    INFO [RO5]: "The Bank of England base rate changed on {date}.
    Variable-rate accounts may adjust. Check your rates are still competitive."
    NOTIFY: dashboard + email
```

### 6.4 Regular Saver Opportunities

```
Does the user have disposable income for regular saving?
|
+-- disposable_income > 0 AND no regular saver account?
|   +-- Any best-buy regular saver rate > best easy access rate + 1%?
|       YES -> RECOMMEND [RO6]: "Regular saver accounts currently offer
|       up to {rate}% on monthly deposits of up to {max_monthly}.
|       These rates are often significantly higher than easy access."
|
+-- Has regular saver with introductory period ending?
    ALERT [RO7]: "Your regular saver introductory rate of {rate}% ends
    on {date}. Review options for when the term completes."
```

### Rate Optimisation Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| RO1 | Rate below market | `medium` | `rates.switch_recommended` | "Switching would earn an extra {gain} per year." |
| RO2 | Fixed maturing 30-90 days | `medium` | `rates.maturity_warning` | "Your fixed-rate account matures on {date}. Start comparing rates." |
| RO3 | Fixed maturing < 30 days | `high` | `rates.maturity_urgent` | "Your fixed-rate account matures in {days} days." |
| RO4 | Promo rate expiring | `high` | `rates.promo_expiring` | "The promotional rate expires on {date}." |
| RO5 | Base rate changed | `info` | `rates.base_rate_change` | "The Bank of England base rate changed. Check your rates." |
| RO6 | Regular saver opportunity | `medium` | `rates.regular_saver` | "Regular saver accounts currently offer up to {rate}%." |
| RO7 | Regular saver term ending | `medium` | `rates.regular_saver_ending` | "Your regular saver rate ends on {date}." |

---

## 7. Phase 5: Product Suitability Engine

For each savings product type, determine suitability based on user profile.

### 7.1 Product Decision Trees

#### Easy Access Savings Account

```
SUITABILITY CHECK:
|
+-- ALWAYS suitable for emergency fund portion
+-- ALWAYS suitable for short-term goals (< 1 year)
+-- ALWAYS suitable as "sweep" account for excess current account balance

RECOMMEND when:
+-- Emergency fund not fully funded -> PRIORITY: critical
+-- Has short-term goal without linked account -> PRIORITY: medium
+-- Current account balance > 3x monthly expenditure -> PRIORITY: low
    "Your current account balance of {balance} exceeds 3 months of
     expenditure. Move the excess to a savings account earning interest."

NOT SUITABLE when:
+-- All emergency fund needs met AND all short-term goals funded
    AND no excess current account balance
    "You have sufficient easy access savings. Consider notice accounts
     or fixed rates for better returns on longer-term savings."
```

#### Notice Savings Account (30/60/90/120 day notice)

```
SUITABILITY CHECK:
|
+-- Emergency fund fully funded?
|   NO -> SKIP: "Build your emergency fund in easy access first."
|
+-- Has savings earmarked for known future expense in 3-12 months?
|   YES -> Match notice period to need:
|   +-- Expense in 4+ months -> 90-day notice suitable
|   +-- Expense in 2-4 months -> 30/60-day notice suitable
|
+-- Has excess easy access savings beyond emergency fund?
|   YES -> RECOMMEND [PS1]: "A notice account typically pays
|   {rate_difference}% more than easy access. For savings you
|   won't need immediately, consider a {days}-day notice account."
|
+-- Rate premium over easy access > 0.25%?
    YES -> Worth recommending
    NO -> "Notice account rates are currently similar to easy access.
           The access restriction may not be worth the small rate premium."
```

#### Fixed Rate Bond (1-5 years)

```
SUITABILITY CHECK:
|
+-- Emergency fund fully funded?
|   NO -> SKIP: "Build your emergency fund first."
|
+-- Has medium-term goal (2-5 years) without need for access?
|   YES -> Match term to goal:
|   +-- Goal in 1-2 years -> 1-year fixed
|   +-- Goal in 2-3 years -> 2-year fixed
|   +-- Goal in 3-5 years -> 3 or 5-year fixed
|   RECOMMEND [PS2]: "A {term}-year fixed rate bond at {rate}%
|    matches your {goal_name} target date of {date}."
|
+-- Has excess savings beyond emergency fund and short-term needs?
|   YES -> Consider interest rate outlook:
|   +-- Rates expected to fall (base rate cuts expected)?
|       RECOMMEND longer fix: "Lock in current rates before they fall."
|   +-- Rates expected to rise?
|       RECOMMEND shorter fix or hold in easy access: "Shorter fixes
|        or easy access may be better if rates are expected to rise."
|   +-- Rates stable?
|       RECOMMEND based on liquidity needs
|
+-- FSCS protection check:
    Amount per institution must stay within £85,000
```

#### Cash ISA (Easy Access)

```
SUITABILITY CHECK:
|
+-- UK resident?
|   NO -> SKIP: "Cash ISAs are only available to UK residents."
|
+-- Age >= 18? (or 16 for Cash ISA specifically)
|   NO -> SKIP: "You must be at least 16 to open a Cash ISA."
|
+-- ISA allowance remaining > 0?
|   NO -> SKIP: "Your ISA allowance for this tax year is fully used."
|
+-- PSA breached or near breach?
|   YES -> STRONGLY RECOMMEND [PS3a]: "Move taxable savings into a
|   Cash ISA to shelter interest from tax."
|   PRIORITY: high
|
+-- Additional rate taxpayer (PSA = 0)?
|   YES -> STRONGLY RECOMMEND [PS3b]
|   PRIORITY: critical
|
+-- Basic rate taxpayer with PSA headroom?
|   Consider S&S ISA instead [PS3c]: "Your savings interest is
|   within your Personal Savings Allowance. A Stocks & Shares ISA
|   may be a better use of your ISA allowance for long-term savings."
|   PRIORITY: info
```

#### Fixed Rate Cash ISA

```
SUITABILITY CHECK:
|
+-- All Cash ISA checks from above pass?
+-- Has medium-term savings (2-5 years) that could be ISA-wrapped?
|   YES -> RECOMMEND [PS4]: "A fixed-rate Cash ISA offers tax-free
|   interest at {rate}% -- higher than easy access Cash ISA rates."
|
+-- Note: Current year ISA subscription rules apply:
    "You can only subscribe to one Cash ISA per tax year (though you
     can transfer between providers). If you've already subscribed to
     an easy access Cash ISA this year, you cannot open a fixed Cash ISA
     with a different provider."

    NOTE (2024/25 onwards): This single-provider rule was ABOLISHED
    from April 2024. You can now subscribe to multiple Cash ISAs
    in the same tax year, as long as total does not exceed £20,000.
```

#### Cash Lifetime ISA (LISA)

```
SUITABILITY CHECK:
|
+-- Age 18-39 (to open) or under 50 (to contribute)?
|   NO -> SKIP: "You must be aged 18-39 to open a Lifetime ISA,
|          and can contribute until age 50."
|
+-- UK resident?
|   NO -> SKIP
|
+-- First-time buyer saving for property under £450,000?
|   YES -> STRONGLY RECOMMEND [PS5a]: "The Lifetime ISA gives you a
|   25% government bonus (up to £1,000 per year) on your first home
|   deposit. This is the best guaranteed return available."
|   PRIORITY: high
|
+-- Planning for retirement AND age under 40?
|   YES -> CONSIDER [PS5b]: "A Lifetime ISA can also be used for
|   retirement savings (accessible from age 60). The 25% bonus
|   is equivalent to basic rate pension tax relief."
|
+-- Already a homeowner?
|   +-- Under 50?
|       YES -> Retirement use only [PS5c]: "As a homeowner, you can
|       only use a Lifetime ISA for retirement (age 60+). The 25%
|       bonus still applies."
|
+-- CRITICAL WARNING for non-qualifying withdrawals:
    "Withdrawing from a Lifetime ISA for any reason other than buying
     a first home (under £450,000) or after age 60 incurs a 25%
     government penalty. This effectively means you lose 6.25% of
     your original contribution."

    Penalty calculation:
    - Contribute £4,000 -> government adds £1,000 -> total £5,000
    - Withdraw: 25% penalty on £5,000 = £1,250
    - You receive £3,750 (lost £250 of your own money = 6.25% loss)
```

#### Junior ISA (Cash)

**Note:** All child references use `{child_name}` variable, populated from `familyMembers` where `relationship = 'child'`. This is resolved per child — recommendations are generated for EACH child individually using their name.

```
SUITABILITY CHECK (run for EACH child in familyMembers):
|
+-- child_name = familyMember.name (e.g., "Oliver", "Sophie")
|
+-- Has child under 18?
|   NO -> SKIP
|
+-- Child is UK resident?
|   NO -> SKIP: "Junior ISAs are only available to UK-resident children."
|
+-- Child does not have a Child Trust Fund?
|   +-- Has CTF? -> Can transfer CTF to Junior ISA
|
+-- Annual limit: £9,000 per child (2025/26) — via TaxConfigService
|
+-- RECOMMEND [PS6]: "A Junior Cash ISA offers tax-free interest on
|    savings for {child_name}. The £9,000 annual allowance is separate
|    from your own ISA allowance."
|
+-- Cash vs Stocks & Shares Junior ISA decision:
    +-- {child_name}'s age < 10 (8+ years to maturity)?
        RECOMMEND S&S Junior ISA: "With {years} years until {child_name}
        turns 18, a Stocks & Shares Junior ISA is likely to generate
        better returns than cash over this period."
    +-- {child_name}'s age 10-15 (3-8 years)?
        CONSIDER mix: "A mix of cash and stocks & shares may be
        appropriate for the remaining {years} years for {child_name}."
    +-- {child_name}'s age 16-17 (< 2 years)?
        RECOMMEND Cash Junior ISA: "With only {years} years until
        {child_name} turns 18, cash is the safer option."
```

#### NS&I Products (Including Premium Bonds)

**Important:** Premium Bonds ARE an NS&I product. All NS&I products are issued by National Savings & Investments, backed 100% by HM Treasury. They are grouped together here because the government backing applies to ALL NS&I products equally — there is no FSCS limit because they are Treasury-backed, not bank deposits.

**Key NS&I advantage (applies to ALL products below):**
"NS&I savings are 100% backed by HM Treasury. Unlike banks, there is no £85,000 FSCS limit — your entire balance is government-guaranteed."

```
NS&I PRODUCT ASSESSMENT:
|
+-- PREMIUM BONDS (NS&I):
|   |
|   +-- Age >= 16? (or any age if purchased by parent/guardian)
|   |   Parent/guardian can buy for under-16s
|   |
|   +-- UK resident?
|   |   NO -> SKIP
|   |
|   +-- Current holding < £50,000 maximum?
|   |   NO -> SKIP [PS7a]: "You hold the maximum £50,000 in Premium Bonds."
|   |
|   +-- Higher or additional rate taxpayer?
|   |   YES -> STRONGLY RECOMMEND [PS7b]: "Premium Bond prizes are
|   |   completely tax-free. As a {tax_band}-rate taxpayer, the effective
|   |   prize rate of approximately {effective_rate}% is equivalent to a
|   |   gross rate of {gross_equivalent}% on a taxable account."
|   |
|   |   Gross equivalent calculation:
|   |   gross_equivalent = effective_prize_rate / (1 - marginal_tax_rate)
|   |   Example (higher rate, 4.4% effective):
|   |   4.4% / (1 - 0.40) = 7.33% gross equivalent
|   |
|   +-- Basic rate taxpayer with PSA not breached?
|   |   LESS COMPELLING [PS7c]: "Premium Bonds offer tax-free prizes but the
|   |   effective rate of {rate}% may be lower than the best easy access
|   |   savings accounts. Since your interest is within your Personal Savings
|   |   Allowance, a regular savings account may earn more."
|   |
|   +-- PSA breached (any tax band)?
|       RECOMMEND [PS7d]: "Since your savings interest exceeds your Personal
|       Savings Allowance, Premium Bonds provide tax-free returns without
|       using your ISA allowance."
|
|   Key facts for Premium Bonds:
|   - Minimum purchase: £25
|   - Maximum holding: £50,000 per person
|   - Prize fund rate: approximately 4.40% (check current NS&I rate)
|   - Prizes: £25 to £1,000,000, drawn monthly
|   - Median return: lower than prize fund rate (prizes are skewed)
|   - 100% backed by HM Treasury (no FSCS limit needed)
|   - Can be cashed in at any time (usually 3-5 working days)
|   - No capital risk (always get back what you put in)
|
+-- NS&I INCOME BONDS:
|   +-- Wants regular income from savings?
|   +-- Variable rate, paid monthly
|   +-- Interest is TAXABLE (paid gross, declared via Self Assessment)
|   +-- Min £500, Max £1,000,000
|   RECOMMEND when: retired users wanting income, OR risk-averse with
|   large cash holdings exceeding FSCS limits elsewhere
|
+-- NS&I DIRECT SAVER:
|   +-- Easy access, variable rate
|   +-- Interest is TAXABLE
|   +-- Min £1, no maximum
|   +-- Usually lower than best-buy easy access rates
|   RECOMMEND when: FSCS limit exceeded at other institutions
|
+-- NS&I GREEN SAVINGS BONDS:
|   +-- Fixed term (typically 3 years)
|   +-- Interest is TAXABLE
|   +-- ESG-aligned
|   RECOMMEND when: user has ESG preference AND wants fixed-term
|   cash AND has FSCS concerns
|   [PS8]: "NS&I Green Savings Bonds align with your environmental
|    preferences while offering government-backed security."
|
+-- NS&I GUARANTEED GROWTH / INCOME BONDS:
    +-- Fixed term (1 year typically)
    +-- Growth: interest added at maturity
    +-- Income: interest paid monthly
    +-- Availability varies (NS&I opens/closes these periodically)

RECOMMEND NS&I when: user has > £85,000 with any single bank institution
```

#### Current Account Interest

```
ASSESSMENT:
|
+-- Does user's current account pay interest?
|   YES -> Include in interest income calculations for PSA
|   Note: Some current accounts pay competitive interest on
|         balances up to a certain limit (e.g., £25,000)
|
+-- Is current account interest > easy access savings rate?
|   YES -> INFO [PS9]: "Your current account pays {rate}% on balances
|   up to {limit}. This is competitive with easy access savings.
|   Ensure you are not holding excess cash above the interest-bearing
|   limit."
```

#### Offset Mortgage Savings

```
SUITABILITY CHECK:
|
+-- Has mortgage?
|   NO -> SKIP
|
+-- Has offset mortgage or eligible for one?
|   +-- Currently has offset mortgage:
|       Calculate savings benefit:
|       offset_saving = savings_balance * mortgage_rate
|       (This is effectively tax-free -- you are avoiding interest
|        rather than earning it, so no tax to pay)
|
|       Compare to best savings rate:
|       +-- After-tax savings rate = best_rate * (1 - marginal_rate)
|       +-- offset_saving > after_tax_savings_rate * balance?
|           YES -> RECOMMEND offset [PS10a]: "Offsetting {balance}
|           against your mortgage saves {saving}/year in mortgage
|           interest -- equivalent to earning {gross_equiv}% gross
|           before tax. This beats the best savings account."
|           NO -> INFO [PS10b]: "Your mortgage rate of {rate}% is
|           lower than available after-tax savings rates. You earn
|           more by keeping savings separate."
|
+-- Does NOT have offset mortgage but has significant savings + mortgage:
    INFO [PS10c]: "You have {savings} in savings and a mortgage at
    {rate}%. An offset mortgage could save you {annual_saving}/year
    in mortgage interest, tax-free. Consider switching at your next
    remortgage."
```

#### Help to Save

```
ELIGIBILITY CHECK (Note: Scheme closed to new applicants September 2025):
|
+-- Was the user already enrolled before closure?
|   YES -> Continue tracking:
|   - Saves £1-£50 per month for 4 years
|   - Government bonus: 50% of highest balance at Year 2 and Year 4
|   - Maximum bonus: £1,200 over 4 years (£600 at Year 2 + £600 at Year 4)
|
+-- Not enrolled:
    SKIP: "The Help to Save scheme closed to new applicants in
    September 2025. It is no longer available."

Original eligibility (for reference/existing accounts):
- Receiving Working Tax Credit, OR
- Receiving Universal Credit with earnings > £722.45/month (single)
  or > £1,139.43/month (couple)
```

#### Credit Union Savings

```
ASSESSMENT:
|
+-- User is member of a credit union?
|   YES -> Track as savings account
|   +-- Credit unions pay dividends, not interest
|   +-- Dividend rate typically 1-3% (varies by credit union)
|   +-- FSCS protection applies (£85,000 per credit union)
|   +-- Some credit unions require minimum savings before lending
|
+-- Not a member:
    INFO [PS11]: "Credit unions are community-based financial
    cooperatives. They may offer savings accounts and affordable
    loans. Some have minimum savings requirements."
    PRIORITY: low (only if user asks about alternatives)
```

### 7.2 Product Suitability Matrix

| Product | Emergency Fund | Short-term (<2yr) | Medium-term (2-5yr) | Long-term (5yr+) | Tax-free | FSCS Protected | Access |
|---------|:-:|:-:|:-:|:-:|:-:|:-:|--------|
| Easy Access | YES | YES | Partial | NO | NO | YES (£85k) | Instant |
| Notice Account | NO | Partial | YES | Partial | NO | YES (£85k) | 30-120 days |
| Fixed Rate Bond | NO | NO | YES | Partial | NO | YES (£85k) | Locked |
| Regular Saver | NO | YES | NO | NO | NO | YES (£85k) | Restricted |
| Cash ISA (Easy Access) | YES | YES | Partial | NO | YES | YES (£85k) | Instant |
| Cash ISA (Fixed) | NO | NO | YES | Partial | YES | YES (£85k) | Locked |
| Cash LISA | NO | NO | YES (home) | YES (60+) | YES + 25% bonus | YES (£85k) | Restricted |
| Junior ISA (Cash) | NO | NO | NO | YES (until 18) | YES | YES (£85k) | Locked until 18 |
| Premium Bonds | Partial | YES | YES | YES | YES (prizes) | N/A (HM Treasury) | 3-5 days |
| NS&I Products | Partial | Varies | Varies | Varies | Some | N/A (HM Treasury) | Varies |
| Offset Mortgage | NO | NO | YES | YES | Effectively | N/A | Instant (usually) |
| Help to Save | NO | NO | YES | NO | Bonus tax-free | YES | Monthly limit |
| Credit Union | Partial | YES | Partial | NO | NO | YES (£85k) | Varies |

---

## 8. Phase 6: FSCS Protection Assessment

### 8.1 FSCS Rules

The Financial Services Compensation Scheme (FSCS) protects deposits up to £85,000 per person per authorised institution. Key rules:

- **Per person:** Each individual is protected up to £85,000
- **Per institution:** NOT per account or per brand. Many brands share a single banking licence
- **Joint accounts:** Each person protected for their share (joint account with £170,000 = both protected)
- **Temporary High Balances:** Up to £1,000,000 protected for 6 months for specific events:
  - Property sale proceeds
  - Insurance payouts
  - Personal injury compensation
  - Redundancy payments
  - Inheritance
  - Pension lump sums
  - Divorce/dissolution settlements

### 8.2 Shared Banking Licence Groups (UK, 2025/26)

These brands share a banking licence -- combined deposits count as ONE institution for FSCS:

| Group | Brands |
|-------|--------|
| Lloyds Banking Group | Lloyds Bank, Halifax, Bank of Scotland, Scottish Widows Bank |
| NatWest Group | NatWest, Royal Bank of Scotland, Ulster Bank |
| HSBC Group | HSBC, First Direct, M&S Bank |
| Barclays | Barclays, Barclaycard |
| Santander | Santander UK |
| Nationwide | Nationwide Building Society |
| Virgin Money | Virgin Money, Clydesdale Bank, Yorkshire Bank |
| Co-operative Bank | Co-operative Bank, Smile |
| TSB | TSB Bank |

Note: This list changes. The engine should store licence groups and update them periodically.

### 8.3 FSCS Assessment Decision Tree

```
For each institution (grouped by banking licence):
|
+-- Calculate total deposits per person per licence group:
|   total = SUM(account.balance) for accounts with same licence
|
+-- total > £85,000?
|   YES -> FSCS BREACH [FSCS1]:
|   "You have {total} deposited across {brands} which share a banking
|    licence. Only £85,000 is protected by the FSCS. Consider spreading
|    {excess} to other institutions."
|   PRIORITY: high
|
+-- total > £75,000 (approaching limit)?
|   YES -> FSCS WARNING [FSCS2]:
|   "You have {total} across {brands}. You are approaching the £85,000
|    FSCS protection limit. Plan ahead for when balances grow."
|   PRIORITY: medium
|
+-- total <= £85,000?
    GOOD [FSCS3]: "Your deposits at {institution} are within FSCS
    protection limits."

Special cases:
|
+-- Joint account: Each person's share counted separately
|   joint_balance / 2 per person (or per ownership_percentage)
|
+-- NS&I accounts:
    SKIP FSCS check: "NS&I savings are 100% backed by HM Treasury.
    There is no FSCS limit -- your entire balance is government-guaranteed."
    [FSCS4]
```

### 8.4 FSCS Spreading Recommendations

```
If any institution exceeds FSCS limit:
|
+-- Calculate excess per institution:
|   excess = total - 85,000
|
+-- Recommend spreading strategy:
|   [FSCS5]: "Move {excess} from {over_limit_institution} to another
|    institution. Consider:"
|   +-- NS&I (unlimited government backing)
|   +-- Another bank NOT in same licence group
|   +-- Premium Bonds (up to £50,000, HM Treasury backed)
|
+-- Temporary High Balance detected?
    [FSCS6]: "You appear to have received a large sum recently
    (e.g., property sale, inheritance). Temporary high balances
    up to £1,000,000 are protected for 6 months from the date of
    deposit. Plan where to deploy these funds before protection expires."
```

### FSCS Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| FSCS1 | Over £85,000 at one institution | `high` | `fscs.breach` | "You have {total} at {institution}. Only £85,000 is protected." |
| FSCS2 | Over £75,000 (approaching) | `medium` | `fscs.approaching` | "You are approaching the £85,000 FSCS limit at {institution}." |
| FSCS3 | Within limit | `success` | `fscs.within_limit` | "Your deposits at {institution} are within FSCS limits." |
| FSCS4 | NS&I exempt | `info` | `fscs.nsi_exempt` | "NS&I savings are government-backed with no FSCS limit." |
| FSCS5 | Spreading recommendation | `high` | `fscs.spread` | "Move {excess} to another institution for full protection." |
| FSCS6 | Temporary high balance | `info` | `fscs.temporary_high` | "Temporary high balance protection expires 6 months from deposit." |

---

## 9. Phase 7: Savings vs Debt Analysis

### 9.1 Interest Rate Comparison

```
For each non-mortgage, non-student-loan debt:
|
+-- debt.interest_rate > 15%?
|   CRITICAL [SD1]: "Your {debt_type} charges {rate}% interest -- far
|   more than any savings account can earn. Clear this debt before
|   building savings beyond a minimal emergency fund."
|   Action: Cap emergency fund at 1 month, direct all surplus to debt
|   PRIORITY: critical
|
+-- debt.interest_rate > best_available_savings_rate?
|   HIGH [SD2]: "Your {debt_type} at {rate}% costs more than savings
|   earn ({savings_rate}%). Splitting your surplus between debt
|   repayment and savings is recommended."
|   Action: 50% to debt, 50% to savings (after emergency fund minimum)
|   PRIORITY: high
|
+-- debt.interest_rate <= best_available_savings_rate?
|   LOW [SD3]: "Your {debt_type} at {rate}% costs less than your
|   savings earn ({savings_rate}%). Continue saving while making
|   minimum debt payments."
|   PRIORITY: info
|
+-- debt has promotional rate expiring within 6 months?
    WARNING [SD4]: "Your {debt_type} promotional rate of {rate}%
    expires on {date}. The rate will increase to {standard_rate}%.
    Plan to clear or refinance before the rate rises."
    PRIORITY: high
```

### 9.2 Emergency Fund vs Debt Priority

```
Should the user save for emergency fund or pay off debt?
|
+-- Has high-interest debt (> 15%) AND no emergency fund?
|   COMPROMISE [SD5]: "Build a minimal emergency fund of 1 month's
|   expenses ({one_month_amount}) in easy access savings, then
|   direct all remaining surplus to clearing your {debt_type}."
|
+-- Has moderate-interest debt (> savings rate) AND partial emergency fund?
|   SPLIT [SD6]: "Continue building your emergency fund alongside
|   debt repayment. Suggested split: {ef_percent}% to emergency
|   fund, {debt_percent}% to debt."
|
+-- Has low-interest debt AND no emergency fund?
    SAVE FIRST [SD7]: "Your debt interest rate is low. Focus on
    building your emergency fund first. The cost of not having
    emergency reserves (potentially using more expensive credit)
    outweighs the interest saving from faster debt repayment."
```

### 9.3 Offset Mortgage Comparison

```
If user has mortgage:
|
+-- Compare mortgage rate to after-tax savings rate:
|   after_tax_savings = best_rate * (1 - marginal_rate)
|
+-- mortgage_rate > after_tax_savings?
|   YES -> RECOMMEND offset/overpayment [SD8]:
|   "Your mortgage rate of {mortgage_rate}% exceeds the after-tax
|    return on savings of {after_tax}%. Each £1,000 offset against
|    your mortgage saves {annual_saving}/year in interest -- tax-free."
|
+-- mortgage_rate <= after_tax_savings?
|   NO -> KEEP SAVINGS SEPARATE [SD9]:
|   "Your after-tax savings return of {after_tax}% exceeds your
|    mortgage rate of {mortgage_rate}%. Keep savings separate."
|
+-- mortgage has overpayment allowance?
    INFO [SD10]: "Most mortgages allow 10% overpayment per year
    without penalty. Your allowance this year is approximately
    {overpayment_allowance}."
```

### Savings vs Debt Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| SD1 | Debt > 15% | `critical` | `debt.critical_rate` | "Clear high-interest debt before building savings." |
| SD2 | Debt > savings rate | `high` | `debt.exceeds_savings` | "Debt costs more than savings earn. Split surplus." |
| SD3 | Debt < savings rate | `info` | `debt.below_savings` | "Debt costs less than savings earn. Continue saving." |
| SD4 | Promo rate expiring | `high` | `debt.promo_expiring` | "Promotional rate expires on {date}. Plan ahead." |
| SD5 | High debt, no emergency fund | `critical` | `debt.minimal_emergency` | "Build 1 month emergency fund, then clear debt." |
| SD8 | Mortgage > after-tax savings | `medium` | `debt.offset_recommended` | "Offsetting saves more than earning interest." |
| SD9 | Savings > mortgage | `info` | `debt.savings_preferred` | "Savings earn more than mortgage costs. Keep separate." |

---

## 10. Phase 8: Savings vs Investment Decision

### 10.1 Cash Drag Analysis

```
Calculate excess cash (cash beyond emergency fund + short-term needs):
    excess_cash = total_savings - emergency_fund_target - short_term_goal_total

Is excess_cash > 0?
|
+-- NO -> No cash drag. SKIP.
|
+-- YES -> Calculate cash drag:
|   |
|   +-- real_return = weighted_savings_rate - inflation_rate
|   |   (if inflation > savings rate, real return is negative)
|   |
|   +-- opportunity_cost = excess_cash * (expected_investment_return - weighted_savings_rate)
|   |   (using 5% expected investment return as default)
|   |
|   +-- real_return < 0?
|   |   WARNING [CI1]: "Your excess savings of {excess} are losing
|   |   purchasing power. After inflation of {inflation}%, your real
|   |   return is {real_return}%. Over 5 years, this erodes {erosion}
|   |   in today's money."
|   |   PRIORITY: high
|   |
|   +-- opportunity_cost > £500/year?
|       INFO [CI2]: "Your excess cash of {excess} could earn an
|       estimated {opportunity_cost}/year more if invested. Over
|       10 years, this compounds to approximately {compound_diff}."
|       PRIORITY: medium
```

### 10.2 When Cash is the Right Choice

```
Cash is appropriate when:
|
+-- Time horizon < 2 years?
|   YES -> CASH [CI3]: "For money you need within 2 years, cash
|   is the right choice. Markets can fall significantly over short
|   periods, and you cannot afford to wait for recovery."
|
+-- Time horizon 2-3 years?
|   MIXED [CI4]: "For a 2-3 year horizon, consider splitting between
|   cash (for security) and cautious investments (for growth).
|   A typical split might be 50/50 or 70/30 cash/investments."
|
+-- Time horizon 3-5 years?
|   CONSIDER INVESTING [CI5]: "Over 3-5 years, investments have
|   historically outperformed cash in most periods. Consider moving
|   some excess cash into a Stocks & Shares ISA."
|
+-- Time horizon > 5 years?
|   INVEST [CI6]: "For money you won't need for 5+ years, investments
|   almost always outperform cash over this period. Holding excess
|   cash long-term costs you real growth."
|
+-- Risk tolerance = "very_low" or "low"?
|   CASH ACCEPTABLE [CI7]: "Given your risk tolerance, holding more in
|   cash is reasonable. However, be aware that inflation erodes the
|   purchasing power of cash over time."
|
+-- Specific known expense coming?
    CASH [CI8]: "Keep savings for known upcoming expenses in cash.
    Timing risk (markets falling when you need the money) outweighs
    potential growth."
```

### 10.3 Investment Trigger Points

```
Recommend moving excess cash to investments when ALL of:
|
+-- Emergency fund fully funded (>= target months)
+-- No high-interest debt (> savings rate)
+-- Excess cash > £1,000 (meaningful amount to invest)
+-- No known cash need within 2 years for the excess amount
+-- Risk profile completed and risk_level >= "low-medium"

Then cascade in the following strict priority order:
|
+-- STEP 1: Has ISA allowance remaining?
|   YES -> "Move excess cash to a Stocks & Shares ISA" [CI9]
|   PRIORITY: high
|   (Allocate up to remaining ISA allowance, pass remainder to Step 2)
|
+-- STEP 2: ISA allowance exhausted — has pension Annual Allowance remaining?
|   YES -> "Consider increasing your pension contributions" [CI10a]
|   PRIORITY: high
|   "Your ISA allowance is fully used. Pension contributions offer
|    {tax_relief_rate}% tax relief, making them the next most
|    tax-efficient home for your excess cash. You have {pension_aa_remaining}
|    of Annual Allowance remaining."
|   (Allocate up to remaining AA, pass remainder to Step 3)
|
+-- STEP 3: Pension AA also exhausted — consider onshore bond wrapper?
|   +-- Tax band = higher or additional?
|   |   AND investment_experience >= intermediate?
|   |   AND remaining >= £5,000 (onshore bond minimum)?
|   |   YES -> "Consider an onshore investment bond" [CI10b]
|   |   PRIORITY: medium
|   |   "With your ISA and pension allowances fully used, an onshore
|   |    bond offers tax-deferred growth. As a {tax_band}-rate taxpayer,
|   |    the 20% internal tax credit and top-slicing relief may reduce
|   |    the effective tax rate on encashment."
|   |   (Allocate suitable amount, pass remainder to Step 4)
|   |
|   +-- Not eligible for onshore bond -> proceed to Step 4
|
+-- STEP 4: GIA as last resort
    "Consider a General Investment Account for excess cash" [CI10c]
    PRIORITY: low
    "After filling your ISA, pension, and bond allowances, a GIA
     provides flexible access with no contribution limits. Use
     tax-efficient strategies (accumulation funds, CGT annual
     exemption) to minimise tax drag."
```

---

## 11. Phase 9: Goal-Linked Savings Optimisation

**Existing service:** `GoalProgressCalculator`

### 9.0 Goal-Account Relationship Model

**Critical design rules:**
- Each goal MUST be linked to at least one account
- A goal MAY be linked to MULTIPLE accounts (e.g., a house deposit split across a LISA + Cash ISA + easy access)
- An account MAY be linked to MULTIPLE goals (e.g., a single savings account funding both a holiday and a car)
- When an account serves multiple goals, the engine optimises allocation in order of goal importance (set by the user via `goal.priority_rank`)
- Goal progress is calculated as the SUM of all linked account balances allocated to that goal

```
Goal-Account Linking:
|
+-- goals_accounts (pivot table):
|   | goal_id | account_id | allocated_amount | is_primary |
|   |---------|------------|------------------|------------|
|   | 1       | 5          | 15,000           | true       |  <- house deposit in LISA
|   | 1       | 8          | 10,000           | false      |  <- house deposit in Cash ISA
|   | 2       | 8          | 5,000            | true       |  <- holiday fund also in same Cash ISA
|
+-- When multiple goals share an account:
|   Sort by user-defined importance (goal.priority_rank, 1 = most important)
|   Allocate in order: highest priority goal gets funded first
|   Remaining balance allocated to next goal, and so on
|   [GL0]: "Your {account_name} is linked to {count} goals. We allocate
|   funds in order of your priority: 1. {goal_1}, 2. {goal_2}."
|
+-- When a goal has multiple accounts:
    Sum all allocated_amounts across linked accounts
    progress = SUM(allocated_amounts) / target_amount
    [GL0a]: "Your {goal_name} is funded across {count} accounts
    totalling {total_saved} of your {target_amount} target."
```

### 9.1 Goal-Account Matching

```
For each savings goal:
|
+-- goal_type = "emergency_fund"?
|   REQUIRED ACCOUNT TYPE: Easy access savings or easy access Cash ISA
|   BLOCKED: Fixed rate, notice, LISA, investments
|   MAY span multiple accounts (e.g., split across 2 easy access for FSCS)
|   [GL1]: "Emergency fund must be held in instant access accounts."
|
+-- goal_type = "property_purchase" (first home)?
|   +-- Eligible for LISA? (age 18-39, property < £450,000)
|       YES -> RECOMMEND LISA as PRIMARY + Cash ISA/easy access as SECONDARY [GL2]:
|       "Save your deposit across a Lifetime ISA (for the 25% government bonus,
|        up to £4,000/year) and a Cash ISA or easy access account for the remainder."
|   +-- Not LISA eligible -> Easy access or fixed Cash ISA [GL3]
|
+-- goal timeline < 1 year?
|   RECOMMEND: Easy access savings [GL4]
|   "For a goal within 12 months, keep funds in easy access."
|
+-- goal timeline 1-2 years?
|   RECOMMEND: Notice account (30-90 day) or short fixed [GL5]
|   "A notice account offers better rates for goals 1-2 years away."
|
+-- goal timeline 2-5 years?
|   RECOMMEND: Fixed rate bond or fixed Cash ISA [GL6]
|   "A {term}-year fixed rate matches your goal timeline."
|
+-- goal timeline > 5 years?
    RECOMMEND: Consider investments [GL7]
    "For goals more than 5 years away, investments typically
     outperform cash. Consider a Stocks & Shares ISA."
```

### 9.2 Goal Progress Assessment

```
For each goal with linked account:
|
+-- Calculate progress:
|   progress_percent = (current_saved / target_amount) * 100
|   months_remaining = months until target_date
|   shortfall = target_amount - current_saved
|   required_monthly = shortfall / months_remaining
|
+-- On track?
|   +-- Has auto_transfer AND auto_transfer >= required_monthly?
|       YES -> ON TRACK [GL8]: "You are on track to meet your
|       {goal_name} target of {target} by {date}."
|
|   +-- required_monthly > disposable_income?
|       UNACHIEVABLE [GL9]: "Meeting your {goal_name} target requires
|       saving {required_monthly}/month, which exceeds your disposable
|       income. Consider extending your target date or reducing the
|       target amount."
|
|   +-- required_monthly <= disposable_income but no auto_transfer?
|       AT RISK [GL10]: "You need to save {required_monthly}/month to
|       reach your {goal_name} target. Set up a regular transfer to
|       stay on track."
|
+-- Goal nearly achieved (> 90%)?
    CELEBRATE [GL11]: "You have saved {percent}% of your {goal_name}
    target. You are almost there!"
```

### 9.3 Goal Prioritisation

```
When multiple goals compete for limited surplus:
|
Priority order:
1. Emergency fund (always first)
2. Debt repayment (if debt rate > savings rate)
3. Goals with employer matching (e.g., workplace save-as-you-earn)
4. LISA-eligible goals (25% government bonus)
5. Time-critical goals (shortest deadline first)
6. High-priority user-set goals
7. Medium-priority goals
8. Low-priority goals
9. General savings / excess cash deployment
```

---

## 12. Phase 10: Children's Savings

### 10.1 Junior ISA Assessment

**Note:** As with Phase 5 (section 7.1), all references use `{child_name}` variable populated from `familyMembers`. Recommendations are generated per child.

```
For each dependent child under 18 (child_name = familyMember.name):
|
+-- Has Junior ISA?
|   +-- YES -> Track usage against £9,000 annual limit
|       +-- Contributions this year < £9,000?
|           RECOMMEND top-up [CS1]: "{child_name}'s Junior ISA has
|           {remaining} of allowance remaining this tax year."
|       +-- At maximum?
|           INFO: "{child_name}'s Junior ISA allowance is fully used for this tax year."
|
|   +-- NO -> RECOMMEND opening [CS2]:
|       "A Junior ISA allows tax-free savings of up to £9,000
|        per year for {child_name}. Funds are locked until they turn 18."
|
+-- Cash or Stocks & Shares decision:
    See Phase 5 (PS6) for age-based recommendation per child
```

### 10.2 Parental Settlement Rules (Section 629 ITTOIA)

```
CRITICAL TAX RULE for parents saving for children:
|
+-- Is the saver a PARENT (not grandparent, uncle, etc.)?
|   YES -> Parental settlement rules apply:
|
|   +-- Total interest/income from parent's gifts > £100/year?
|       YES -> ALL the income (not just the excess) is taxed on
|       the PARENT, not the child [CS3]:
|       "Interest from savings you have gifted to your child exceeds
|        £100 per year. Under parental settlement rules, this entire
|        interest amount ({interest}) is taxable on you, not your child."
|
|       KEY: This is per parent. Both parents have a £100 threshold.
|       So mother and father can each gift enough to generate up to
|       £100 interest without it being taxed on them.
|
|   +-- Total interest <= £100/year?
|       FINE [CS4]: "Interest from your gifts to your child is within
|       the £100 parental settlement threshold. No tax implications."
|
+-- Is the saver a GRANDPARENT or other relative?
    NO PARENTAL SETTLEMENT: "Savings gifted by grandparents or other
    relatives are not subject to parental settlement rules. Interest
    is the child's income (usually covered by their personal allowance)."

EXCEPTION: Junior ISA and Child Trust Fund interest is ALWAYS tax-free,
regardless of who made the contribution. Parental settlement rules do
NOT apply to ISA interest.
[CS5]: "Contributions from parents into a Junior ISA are not affected
 by parental settlement rules. All interest is tax-free."
```

### 10.3 Child Trust Fund (CTF) Legacy

```
+-- Child born between 1 September 2002 and 2 January 2011?
|   May have a Child Trust Fund
|   +-- CTF can be transferred to a Junior ISA
|       RECOMMEND [CS6]: "If {child_name} has a Child Trust Fund,
|       consider transferring it to a Junior ISA. Junior ISAs
|       typically offer better rates and more provider choice."
|
+-- Child turning 18 with CTF or Junior ISA?
    ALERT [CS7]: "{child_name}'s {account_type} will mature when they
    turn 18 on {date}. The funds become theirs to access.
    A Junior ISA automatically converts to an adult ISA."
```

### Children's Savings Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| CS1 | JISA allowance remaining | `info` | `children.jisa_remaining` | "{child_name}'s Junior ISA has {remaining} of allowance remaining." |
| CS2 | No JISA, child under 18 | `medium` | `children.recommend_jisa` | "Consider opening a Junior ISA for {child_name} for tax-free savings." |
| CS3 | Parental settlement breach | `warn` | `children.parental_settlement` | "Interest from your gifts exceeds £100. Taxable on you." |
| CS4 | Within parental threshold | `info` | `children.within_threshold` | "Interest from your gifts is within the £100 threshold." |
| CS5 | JISA exempt from rules | `info` | `children.jisa_exempt` | "Junior ISA contributions not affected by parental settlement." |
| CS6 | CTF transfer opportunity | `medium` | `children.ctf_transfer` | "Consider transferring {child_name}'s Child Trust Fund to a Junior ISA." |
| CS7 | Child turning 18 | `info` | `children.turning_18` | "{child_name}'s account matures when they turn 18 on {date}." |

---

## 13. Phase 11: Spouse Optimisation

### 11.1 PSA Optimisation Between Spouses

```
Gate: married/civil_partnership AND spouse linked?
    NO -> SKIP

Compare PSA positions:
|
+-- Partners in different tax bands?
|   YES -> Calculate PSA differential:
|   |
|   +-- User = higher rate (PSA £500), Spouse = basic rate (PSA £1,000)?
|       RECOMMEND [SO1]: "Your spouse has a £1,000 Personal Savings
|       Allowance compared to your £500. Holding interest-bearing
|       savings in your spouse's name shelters up to £500 more
|       interest from tax."
|
|   +-- User = additional rate (PSA £0), Spouse = basic/higher?
|       STRONGLY RECOMMEND [SO2]: "You have no Personal Savings
|       Allowance. Your spouse has {spouse_psa}. Consider holding
|       all non-ISA savings in their name."
|
|   +-- Quantify benefit:
|       tax_saving = min(annual_interest_on_transferred_savings, psa_difference) * marginal_rate
|       "This could save approximately {tax_saving} per year in tax."
|
+-- IHT note for large transfers:
    [SO3]: "Transfers between spouses are IHT-exempt during lifetime.
    However, concentrating assets in one spouse's name may increase
    their estate's IHT exposure if they pass away first."
```

### 11.2 ISA Allowance Coordination

```
+-- One partner's ISA allowance used, other has remaining?
|   RECOMMEND [SO4]: "Your household has {household_remaining} of
|   ISA allowance remaining ({user_remaining} yours, {spouse_remaining}
|   your spouse's). Consider gifting money for your spouse to
|   contribute to their ISA."
|   NOTE: "You cannot contribute directly to your spouse's ISA.
|   Gift the money and they contribute themselves."
|
+-- Both partners have remaining ISA allowance?
    INFO [SO5]: "Combined household ISA capacity: £40,000 per year.
    Coordinate contributions to maximise tax-free savings."
```

### 11.3 Account Ownership Strategy

```
When savings exceed PSA for both partners:
|
+-- One partner is additional rate, other is not?
|   RECOMMEND [SO6]: "Hold all non-ISA savings in {lower_rate_partner}'s
|   name. This maximises the household PSA."
|
+-- Both partners breach PSA?
    RECOMMEND [SO7]: "Both partners' savings interest exceeds their
    Personal Savings Allowance. Prioritise Cash ISA contributions
    for both. Consider Premium Bonds for the additional rate partner."
```

### Spouse Optimisation Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| SO1 | PSA differential (higher/basic) | `medium` | `spouse.psa_shift` | "Hold savings in lower-rate spouse's name." |
| SO2 | Additional rate partner | `high` | `spouse.psa_critical` | "You have no PSA. Hold savings in spouse's name." |
| SO3 | IHT caveat | `info` | `spouse.iht_note` | "Concentrating assets may increase IHT exposure." |
| SO4 | ISA coordination | `medium` | `spouse.isa_coordination` | "Gift money for spouse to use their ISA allowance." |
| SO5 | Both have ISA capacity | `info` | `spouse.household_isa` | "Combined ISA capacity: £40,000 per year." |

---

## 14. Phase 12: Life Event Impact Assessment

### 12.1 Life Event Decision Tree

```
For each active life event:
|
+-- type = "new_baby"
|   |-- Action: TRIGGER
|   |-- Adjustments:
|   |   +-- Increase emergency fund target by 1 month
|   |   +-- Open Junior ISA recommendation
|   |   +-- Reduce disposable income estimate (baby costs ~£500-800/month)
|   |-- Sub-actions:
|   |   +-- Open Junior ISA [LE_S1]
|   |   +-- Review parental settlement rules [LE_S2]
|   |   +-- Increase emergency fund [LE_S3]
|   |   +-- Check Child Benefit / HICBC [LE_S4]
|   |-- Message: "A new baby increases your financial commitments.
|   |   Build your emergency fund and consider a Junior ISA for
|   |   long-term savings."
|
+-- type = "redundancy"
|   |-- Action: BLOCK non-essential savings
|   |-- Adjustments:
|   |   +-- Increase emergency fund target to 9-12 months
|   |   +-- Freeze fixed-term savings recommendations
|   |   +-- Prioritise liquidity
|   |-- Sub-actions:
|   |   +-- Park severance in easy access [LE_S5]
|   |   +-- Review emergency fund adequacy [LE_S6]
|   |-- Message: "Focus on liquidity and emergency reserves until
|   |   your income stabilises."
|
+-- type = "buying_a_home"
|   |-- Action: TRIGGER
|   |-- Adjustments:
|   |   +-- LISA recommendation if eligible
|   |   +-- Deposit in easy access only
|   |   +-- Block fixed-term recommendations for deposit amount
|   |-- Sub-actions:
|   |   +-- LISA eligibility check [LE_S7]
|   |   +-- Deposit account review [LE_S8]
|   |-- Message: "Keep your deposit in accessible accounts. Consider
|   |   a Lifetime ISA for the 25% government bonus."
|
+-- type = "wedding"
|   |-- Action: TRIGGER (if within 2 years)
|   |-- Adjustments:
|   |   +-- Create short-term savings goal for wedding fund
|   |   +-- Block fixed-term longer than time to wedding
|   |-- Message: "Your wedding in {months} months needs {amount}
|   |   in accessible savings."
|
+-- type = "divorce"
|   |-- Action: BLOCK
|   |-- Adjustments:
|   |   +-- Review all joint accounts
|   |   +-- Rebuild individual emergency fund
|   |   +-- Review FSCS exposure after account splitting
|   |-- Sub-actions:
|   |   +-- Separate joint accounts [LE_S9]
|   |   +-- Rebuild emergency fund [LE_S10]
|   |-- Message: "Review all joint savings accounts. Rebuild your
|   |   individual emergency fund as a priority."
|
+-- type = "inheritance"
|   |-- Action: TRIGGER
|   |-- Adjustments:
|   |   +-- FSCS spreading check for large amount
|   |   +-- PSA impact assessment (more savings = more interest)
|   |   +-- Cash vs investment decision for excess
|   |   +-- ISA allowance usage opportunity
|   |-- Sub-actions:
|   |   +-- FSCS spreading [LE_S11]
|   |   +-- PSA review [LE_S12]
|   |   +-- ISA contribution [LE_S13]
|   |-- Message: "An inheritance of {amount} needs careful placement.
|   |   Spread across institutions for FSCS protection, maximise
|   |   ISA contributions, and consider investing excess for growth."
|
+-- type = "career_change"
|   |-- Action: TRIGGER
|   |-- Adjustments:
|   |   +-- Increase emergency fund target to 6-9 months
|   |   +-- Freeze fixed-term recommendations during transition
|   |-- Message: "Ensure 6-9 months emergency fund during your
|   |   career transition."
|
+-- type = "retirement"
|   |-- Action: TRIGGER
|   |-- Adjustments:
|   |   +-- Reduce emergency fund target to 3 months
|   |   +-- Review savings as income source
|   |   +-- Decumulation planning (spending down savings)
|   |-- Message: "In retirement, your emergency fund target reduces.
|   |   Consider how your savings supplement pension income."
|
+-- type = "serious_illness"
|   |-- Action: BLOCK
|   |-- Adjustments:
|   |   +-- Maximise liquidity
|   |   +-- Block all fixed-term recommendations
|   |   +-- Increase emergency fund target
|   |-- Message: "Focus on keeping savings accessible during this period."
|
+-- type = "property_sale"
    |-- Action: TRIGGER
    |-- Adjustments:
    |   +-- Temporary high balance FSCS protection (6 months, up to £1m)
    |   +-- FSCS spreading for proceeds
    |   +-- Investment vs savings decision for proceeds
    |-- Message: "Property sale proceeds qualify for enhanced FSCS
    |   protection of up to £1,000,000 for 6 months. Plan where to
    |   deploy funds before this expires."
```

---

## 15. Phase 13: Savings Adequacy Summary

### 13.1 Key Metrics

| Metric | Calculation | Healthy Range |
|--------|-------------|---------------|
| Emergency Fund Ratio | `emergency_fund / (monthly_expenditure * target_months)` | >= 1.0 (100%) |
| Savings Rate | `monthly_savings / net_monthly_income * 100` | >= 10% (ideally 15-20%) |
| Liquidity Ratio | `(easy_access + notice) / total_savings * 100` | 30-60% (depends on profile) |
| Cash Drag | `excess_cash * (investment_return - savings_rate)` | < £500/year |
| FSCS Exposure | `max(0, institution_balance - 85,000)` per institution | £0 |
| PSA Utilisation | `annual_interest / PSA * 100` | < 75% (headroom) |
| ISA Utilisation | `isa_subscriptions / 20,000 * 100` | Depends on PSA position |
| Weighted Average Rate | `SUM(balance * rate) / SUM(balance)` | >= market benchmark |
| Real Return | `weighted_rate - inflation_rate` | > 0% |
| Goal Funding Ratio | `SUM(current_saved) / SUM(target_amount) * 100` | On track per timeline |

### 13.2 Overall Health Assessment

```
Aggregate all phase findings into overall status:
|
+-- Any CRITICAL findings?
|   YES -> STATUS = "Needs Immediate Attention"
|   Possible criticals: no emergency fund, high-interest debt,
|   FSCS breach, PSA breach for additional rate
|
+-- Any HIGH findings?
|   YES -> STATUS = "Action Recommended"
|   Possible highs: partial emergency fund, PSA breach,
|   approaching FSCS limit, poor rates, debt > savings rate
|
+-- Only MEDIUM or lower?
|   YES -> STATUS = "On Track"
|   Possible mediums: ISA opportunity, rate improvements,
|   approaching PSA, goal adjustments needed
|
+-- All findings are INFO or SUCCESS?
    YES -> STATUS = "Excellent"
```

---

## 16. Phase 14: Conflict Resolution

### 16.1 Surplus Allocation Priority

When total savings recommendations exceed disposable income:

```
Priority order for surplus allocation:
|
1.  Emergency fund (1 month minimum)          -> CRITICAL
2.  High-interest debt repayment (>15%)       -> CRITICAL
3.  Emergency fund (full target)              -> HIGH
4.  Medium-interest debt repayment (>savings) -> HIGH
5.  LISA contribution (25% bonus)             -> HIGH (if eligible)
6.  Cash ISA (if PSA breached)                -> HIGH
7.  Goal-linked savings (time-critical)       -> MEDIUM
8.  Notice/fixed accounts (rate improvement)  -> MEDIUM
9.  Premium Bonds (if higher/additional rate) -> MEDIUM
10. General savings / excess deployment       -> LOW
11. Investment (via investment module)        -> LOW (cross-module)

Partially funded recommendations:
+-- Amount reduced? -> Note: "Partially funded due to surplus constraints.
    Increase your monthly savings or extend timeline."

Fully deferred recommendations:
+-- Not funded at all? -> Note: "Insufficient surplus after higher-priority
    allocations. Review when your financial position changes."
```

### 16.2 ISA Allowance Competition

```
When Cash ISA, S&S ISA, and LISA all recommended:
|
Priority:
1. LISA (if eligible) -- up to £4,000 (25% bonus is unbeatable)
2. Cash ISA -- enough to bring interest below PSA
3. Stocks & Shares ISA -- remainder of allowance
   (Cross-module: defer to investment engine)

Conflict note: "Your ISA allowance of {remaining} has been split:
{lisa_amount} to Lifetime ISA, {cash_isa_amount} to Cash ISA, and
{ss_isa_amount} to Stocks & Shares ISA."
```

### 16.3 Cross-Module Conflicts

```
Savings module may conflict with:
|
+-- Investment module: Excess cash recommendation vs investment allocation
    Resolution: Savings module sets the floor (emergency fund + short-term),
    investment module handles the ceiling (long-term deployment)
|
+-- Retirement module: Pension contribution vs savings
    Resolution: Pension gets priority for higher/additional rate taxpayers
    (tax relief > savings interest). Savings for basic rate where PSA
    not breached.
|
+-- Protection module: Insurance premiums reduce savings capacity
    Resolution: Protection takes priority over optional savings goals.
    Emergency fund is non-negotiable.
```

---

## 17. Output Formatting & Priority

### Priority Levels

| Priority | Usage | Colour (Design System) |
|----------|-------|----------------------|
| `critical` | Immediate action required (no emergency fund, high-interest debt, FSCS breach) | `raspberry-500` |
| `high` | Important action (PSA breach, poor rates, approaching FSCS limit) | `violet-500` |
| `medium` | Recommended action (ISA opportunity, rate improvement, goal adjustment) | `horizon-500` |
| `low` | Optional optimisation (NS&I, regular saver, credit union) | `neutral-500` |
| `info` | Informational (PSA within limit, FSCS within limit, base rate info) | `horizon-300` |
| `success` | Positive confirmation (emergency fund fully funded, rates competitive) | `spring-500` |

### Recommendation Output Shape

```json
{
  "category": "emergency_fund | tax_efficiency | rate_optimisation | product_suitability | fscs_protection | debt_comparison | cash_vs_investment | goal_savings | children_savings | spouse_optimisation | life_event",
  "priority": "critical | high | medium | low | info | success",
  "title": "User-facing headline (no acronyms)",
  "description": "Detailed explanation with personalised values",
  "personal_context": "Specific to user's situation with their numbers",
  "action": "Clear next step the user can take",
  "notes": ["Additional context or caveats"],
  "config_key": "savings.category.sub_key",
  "affected_accounts": [{"id": 1, "institution": "...", "balance": 0}],
  "estimated_benefit": 0.00,
  "estimated_benefit_type": "annual_interest | tax_saving | bonus | avoided_cost"
}
```

---

## 18. Savings Product Reference

### 18.1 Complete Product Taxonomy

| Product Type | `account_type` Value | `access_type` | `is_isa` | Tax Treatment | FSCS | Max Balance |
|-------------|---------------------|--------------|---------|---------------|------|-------------|
| Easy Access Savings | `easy_access` | `immediate` | `false` | Taxable (PSA applies) | £85,000 | None |
| Notice Account (30d) | `notice_30` | `notice` | `false` | Taxable (PSA applies) | £85,000 | None |
| Notice Account (60d) | `notice_60` | `notice` | `false` | Taxable (PSA applies) | £85,000 | None |
| Notice Account (90d) | `notice_90` | `notice` | `false` | Taxable (PSA applies) | £85,000 | None |
| Notice Account (120d) | `notice_120` | `notice` | `false` | Taxable (PSA applies) | £85,000 | None |
| Fixed Rate Bond (1yr) | `fixed_1_year` | `fixed` | `false` | Taxable (PSA applies) | £85,000 | None |
| Fixed Rate Bond (2yr) | `fixed_2_year` | `fixed` | `false` | Taxable (PSA applies) | £85,000 | None |
| Fixed Rate Bond (3yr) | `fixed_3_year` | `fixed` | `false` | Taxable (PSA applies) | £85,000 | None |
| Fixed Rate Bond (5yr) | `fixed_5_year` | `fixed` | `false` | Taxable (PSA applies) | £85,000 | None |
| Regular Saver | `regular_saver` | `immediate` | `false` | Taxable (PSA applies) | £85,000 | £25-500/month |
| Cash ISA (Easy Access) | `cash_isa` | `immediate` | `true` | Tax-free | £85,000 | £20,000/yr |
| Cash ISA (Fixed) | `cash_isa_fixed` | `fixed` | `true` | Tax-free | £85,000 | £20,000/yr |
| Cash ISA (Notice) | `cash_isa_notice` | `notice` | `true` | Tax-free | £85,000 | £20,000/yr |
| Cash LISA | `lisa` | `immediate` | `true` | Tax-free + 25% bonus | £85,000 | £4,000/yr |
| Junior ISA (Cash) | `junior_isa` | `fixed` | `true` | Tax-free | £85,000 | £9,000/yr |
| Premium Bonds | `premium_bonds` | `immediate` | `false` | Tax-free (prizes) | HM Treasury | £50,000 |
| NS&I Direct Saver | `nsi_direct_saver` | `immediate` | `false` | Taxable (paid gross) | HM Treasury | None |
| NS&I Income Bonds | `nsi_income_bonds` | `immediate` | `false` | Taxable (paid gross) | HM Treasury | £1,000,000 |
| NS&I Green Savings | `nsi_green` | `fixed` | `false` | Taxable | HM Treasury | Varies |
| Offset Mortgage | `offset` | `immediate` | `false` | Effectively tax-free | N/A | Mortgage balance |
| Help to Save | `help_to_save` | `immediate` | `false` | Bonus tax-free | £85,000 | £50/month |
| Credit Union | `credit_union` | `immediate` | `false` | Taxable (dividends) | £85,000 | Varies |
| Current Account | `current_account` | `immediate` | `false` | Taxable (PSA applies) | £85,000 | Varies |

### 18.2 Market Benchmark Rates (2025/26)

**CRITICAL:** Market benchmark rates MUST be centralised within `TaxConfigService` alongside all other rates, allowances, and thresholds. The `SavingsMarketRatesSeeder` seeds the initial values into the database, but at runtime ALL rate lookups must go through `TaxConfigService::getSavingsMarketRates()`. This ensures a single source of truth — rates are never hardcoded in services, controllers, or frontend code.

Accessed via: `TaxConfigService::getSavingsMarketRates()`

| Rate Key | Label | Rate | Source |
|----------|-------|------|--------|
| `easy_access` | Easy Access | 4.50% | `TaxConfigService::getSavingsMarketRates('easy_access')` |
| `easy_access_isa` | Easy Access ISA | 4.75% | `TaxConfigService::getSavingsMarketRates('easy_access_isa')` |
| `notice` | Notice Account | 5.00% | `TaxConfigService::getSavingsMarketRates('notice')` |
| `notice_isa` | Notice ISA | 5.25% | `TaxConfigService::getSavingsMarketRates('notice_isa')` |
| `fixed_1_year` | 1 Year Fixed | 5.25% | `TaxConfigService::getSavingsMarketRates('fixed_1_year')` |
| `fixed_1_year_isa` | 1 Year Fixed ISA | 5.50% | `TaxConfigService::getSavingsMarketRates('fixed_1_year_isa')` |
| `fixed_2_year` | 2 Year Fixed | 5.00% | `TaxConfigService::getSavingsMarketRates('fixed_2_year')` |
| `fixed_2_year_isa` | 2 Year Fixed ISA | 5.25% | `TaxConfigService::getSavingsMarketRates('fixed_2_year_isa')` |
| `fixed_3_year` | 3 Year Fixed | 4.75% | `TaxConfigService::getSavingsMarketRates('fixed_3_year')` |
| `fixed_3_year_isa` | 3 Year Fixed ISA | 5.00% | `TaxConfigService::getSavingsMarketRates('fixed_3_year_isa')` |

Note: These rates are seeded via `SavingsMarketRatesSeeder` and stored in the database. Rates invert (shorter terms > longer terms) when markets expect rate cuts. All services must fetch rates via `TaxConfigService` — never from seeders or hardcoded values.

### 18.3 Premium Bonds Prize Details

| Prize Value | Odds (per £1 bond) | Monthly Draw |
|------------|-------------------|-------------|
| £1,000,000 | ~1 in 59,482,822,820 | 2 prizes |
| £100,000 | ~1 in 5,948,282,282 | 10 prizes |
| £50,000 | ~1 in 2,974,141,141 | 20 prizes |
| £25,000 | ~1 in 1,487,070,570 | 40 prizes |
| £10,000 | ~1 in 594,828,228 | 101 prizes |
| £5,000 | ~1 in 297,414,114 | 201 prizes |
| £1,000 | ~1 in 53,115,377 | 1,127 prizes |
| £500 | ~1 in 17,705,126 | 3,382 prizes |
| £100 | ~1 in 1,263,938 | 47,415 prizes |
| £50 | ~1 in 631,969 | 94,830 prizes |
| £25 | ~1 in 21,998 | 2,723,091 prizes |

**Effective prize fund rate:** ~4.40% (2025/26 -- verify with NS&I)
**Median return:** Lower than 4.40% due to prize distribution skew. Typical median for £1,000 holding is ~3.0-3.5%.

---

## 19. Thresholds & Constants Reference

**CRITICAL: `TaxConfigService` is the single source of truth for ALL rates, taxes, allowances, thresholds, and benchmark rates used in this engine.** No values should be hardcoded in services, controllers, components, or calculators. Every constant listed below must be fetched from `TaxConfigService` at runtime. This ensures:
1. Values are updated in ONE place (the database via seeders) and propagate everywhere
2. No divergence between modules (Income, Savings, Investment, etc. all read the same source)
3. Tax year changes require updating only `TaxConfigService` / `TaxConfigurationSeeder`

If a value is not yet in `TaxConfigService`, it must be ADDED there before being used — never hardcoded as a fallback.

### 19.1 Tax Constants (2025/26)

| Constant | Value | Source |
|----------|-------|--------|
| Personal Allowance | £12,570 | `TaxConfigService::get('income_tax.personal_allowance')` |
| PA Taper Threshold | £100,000 | `TaxConfigService::get('income_tax.pa_taper_threshold')` |
| PA Taper Rate | £1 per £2 over threshold | `TaxConfigService::get('income_tax.pa_taper_rate')` |
| Basic Rate Band | £12,571 - £50,270 | `TaxConfigService::get('income_tax.basic_rate_band')` |
| Higher Rate Band | £50,271 - £125,140 | `TaxConfigService::get('income_tax.higher_rate_band')` |
| Additional Rate Band | Above £125,140 | `TaxConfigService::get('income_tax.additional_rate_band')` |
| Basic Rate | 20% | `TaxConfigService::get('income_tax.basic_rate')` |
| Higher Rate | 40% | `TaxConfigService::get('income_tax.higher_rate')` |
| Additional Rate | 45% | `TaxConfigService::get('income_tax.additional_rate')` |
| PSA (Basic) | £1,000 | `TaxConfigService::getPersonalSavingsAllowance('basic')` |
| PSA (Higher) | £500 | `TaxConfigService::getPersonalSavingsAllowance('higher')` |
| PSA (Additional) | £0 | `TaxConfigService::getPersonalSavingsAllowance('additional')` |
| Starting Rate for Savings | £5,000 (reduced by non-savings income above PA) | `TaxConfigService::get('savings.starting_rate_band')` |
| Starting Rate | 0% | `TaxConfigService::get('savings.starting_rate')` |

**NOTE:** PSA values were previously hardcoded in `UKTaxCalculator`. These MUST be migrated to `TaxConfigService` so all modules fetch from the same source.

### 19.2 ISA Constants (2025/26)

| Constant | Value | Source |
|----------|-------|--------|
| Annual ISA Allowance | £20,000 | `TaxConfigService::getISAAllowances('annual_limit')` |
| LISA Annual Limit | £4,000 (within ISA allowance) | `TaxConfigService::getISAAllowances('lisa_limit')` |
| LISA Government Bonus | 25% | `TaxConfigService::getISAAllowances('lisa_bonus_rate')` |
| LISA Max Bonus per Year | £1,000 | Derived: `lisa_limit * lisa_bonus_rate` |
| LISA Withdrawal Penalty | 25% (on total including bonus) | `TaxConfigService::getISAAllowances('lisa_withdrawal_penalty')` |
| LISA Effective Loss | 6.25% of original contribution | Derived from penalty calculation |
| LISA Age to Open | 18-39 | `TaxConfigService::getISAAllowances('lisa_open_age_min/max')` |
| LISA Age to Contribute | Up to 50 | `TaxConfigService::getISAAllowances('lisa_contribute_age_max')` |
| LISA Property Price Limit | £450,000 | `TaxConfigService::getISAAllowances('lisa_property_limit')` |
| LISA Retirement Access Age | 60 | `TaxConfigService::getISAAllowances('lisa_retirement_age')` |
| Junior ISA Annual Limit | £9,000 | `TaxConfigService::getISAAllowances('junior_isa_limit')` |
| Junior ISA Max Age | 17 (matures at 18) | `TaxConfigService::getISAAllowances('junior_isa_max_age')` |
| Cash ISA Min Age | 16 | `TaxConfigService::getISAAllowances('cash_isa_min_age')` |
| S&S ISA / LISA Min Age | 18 | `TaxConfigService::getISAAllowances('ss_isa_min_age')` |
| ISAs per type per year | Unlimited (since April 2024) | `TaxConfigService::getISAAllowances('multiple_isa_allowed')` |

### 19.3 FSCS Constants

| Constant | Value | Source |
|----------|-------|--------|
| FSCS Deposit Protection | £85,000 per person per institution | `TaxConfigService::get('fscs.deposit_protection_limit')` |
| FSCS Joint Account | £170,000 (£85,000 per person) | `TaxConfigService::get('fscs.joint_account_limit')` |
| Temporary High Balance | Up to £1,000,000 | `TaxConfigService::get('fscs.temporary_high_balance')` |
| Temporary High Balance Duration | 6 months from deposit | `TaxConfigService::get('fscs.temporary_high_balance_months')` |
| NS&I Protection | 100% (HM Treasury backed, no limit) | `TaxConfigService::get('fscs.nsi_protection_percent')` |

### 19.4 NS&I Constants (Including Premium Bonds)

| Constant | Value | Source |
|----------|-------|--------|
| Premium Bonds Min Purchase | £25 | `TaxConfigService::get('nsi.premium_bonds.min_purchase')` |
| Premium Bonds Max Holding | £50,000 per person | `TaxConfigService::get('nsi.premium_bonds.max_holding')` |
| Premium Bonds Min Age (self) | 16 | `TaxConfigService::get('nsi.premium_bonds.min_age_self')` |
| Premium Bonds Min Age (parent) | Any age | `TaxConfigService::get('nsi.premium_bonds.min_age_parent')` |
| Premium Bonds Prize Fund Rate | ~4.40% (variable) | `TaxConfigService::get('nsi.premium_bonds.prize_fund_rate')` |
| Premium Bonds Min Prize | £25 | `TaxConfigService::get('nsi.premium_bonds.min_prize')` |
| Premium Bonds Max Prize | £1,000,000 | `TaxConfigService::get('nsi.premium_bonds.max_prize')` |
| Premium Bonds Redemption Time | 3-8 working days | `TaxConfigService::get('nsi.premium_bonds.redemption_days')` |

### 19.5 Emergency Fund Constants

| Constant | Value | Source |
|----------|-------|--------|
| Employed Target | 6 months | Industry standard |
| Self-Employed Target | 9 months | Industry standard |
| Unemployed Target | 6 months | Industry standard |
| Retired Target | 3 months | Industry standard |
| Contract/Freelance Target | 9 months | Industry standard |
| Part-Time Target | 6 months | Industry standard |
| Dependent Adjustment | +1 month per household | Fynla policy |
| Single Income Adjustment | +2 months | Fynla policy |
| Mortgage Adjustment | +1 month | Fynla policy |
| Maximum Target Cap | 12 months | Fynla policy |

### 19.6 Engine Decision Thresholds

| Threshold | Value | Used In |
|-----------|-------|---------|
| Rate Improvement Min Gain | £50/year | Rate optimisation (RO1) |
| Rate "Competitive" Tolerance | 0.50% below market | Rate categorisation |
| Rate "Poor" Threshold | 1.00% below market | Rate categorisation |
| FSCS Warning Threshold | £75,000 (approaching limit) | FSCS assessment |
| PSA Warning Threshold | 75% utilised | PSA assessment |
| High Interest Debt | > 15% | Debt comparison |
| Medium Interest Debt | > best savings rate | Debt comparison |
| Cash Drag Min Amount | £1,000 excess | Cash vs investment |
| Cash Drag Min Cost | £500/year opportunity cost | Cash vs investment |
| Default Expected Investment Return | 5% | Cash drag calculation |
| Short-term Goal Horizon | < 2 years | Product selection |
| Medium-term Goal Horizon | 2-5 years | Product selection |
| Long-term Goal Horizon | > 5 years | Product selection |
| Parental Settlement Threshold | £100/year per parent | Children's savings |
| Fixed Rate Maturity Alert | 90 days before | Rate alerts |
| Fixed Rate Maturity Urgent | 30 days before | Rate alerts |
| Promo Rate Expiry Alert | 90 days before | Rate alerts |
| Minimum Regular Saver Premium | 1.00% above easy access | Regular saver recommendation |
| Current Account Excess | 3x monthly expenditure | Sweep recommendation |
| Savings Account Excess | Emergency target + 3 months | Cash drag trigger |

### 19.7 Inflation & Base Rate (External Data)

| Data Point | Current Value | Update Frequency |
|-----------|---------------|-----------------|
| Bank of England Base Rate | 4.50% (verify current) | After each MPC meeting (8/year) |
| CPI Inflation Rate | ~3.0% (verify current) | Monthly (ONS) |
| RPI Inflation Rate | ~3.5% (verify current) | Monthly (ONS) |

---

## 20. Config Message Key Reference

### 20.1 Full Message Key Map

| Config Key | Phase | Message Summary |
|------------|-------|-----------------|
| `savings_readiness.block.date_of_birth` | 1 | Date of birth required |
| `savings_readiness.block.income` | 1 | Income required |
| `savings_readiness.block.expenditure` | 1 | Expenditure required |
| `savings_readiness.warn.employment_status` | 1 | Employment status helps targeting |
| `savings_readiness.info.accounts` | 1 | Add savings accounts |
| `savings_readiness.warn.interest_rates` | 1 | Add interest rates |
| `savings_readiness.warn.income_breakdown` | 1 | Break down income types |
| `savings_readiness.info.spouse` | 1 | Link spouse account |
| `emergency_fund.fully_funded` | 2 | Emergency fund meets target |
| `emergency_fund.nearly_there` | 2 | 75%+ of target |
| `emergency_fund.needs_attention` | 2 | 50-75% of target |
| `emergency_fund.insufficient` | 2 | 1-50% of target |
| `emergency_fund.critical` | 2 | Less than 1 month cover |
| `emergency_fund.notice_account` | 2 | Emergency fund in notice account |
| `emergency_fund.fixed_account` | 2 | Emergency fund in fixed account |
| `emergency_fund.extended_plan` | 2 | Extended top-up timeline |
| `emergency_fund.comfortable_plan` | 2 | Comfortable 12-month plan |
| `emergency_fund.moderate_plan` | 2 | Moderate 12-month plan |
| `tax.psa.additional_rate` | 3 | No PSA for additional rate |
| `tax.psa.breached` | 3 | PSA exceeded |
| `tax.psa.approaching` | 3 | PSA nearly exceeded |
| `tax.psa.within_limit` | 3 | PSA within limit |
| `tax.starting_rate.full` | 3 | Full starting rate available |
| `tax.starting_rate.partial` | 3 | Partial starting rate |
| `tax.isa.essential` | 3 | Cash ISA essential (additional rate) |
| `tax.isa.recommended_higher` | 3 | Cash ISA recommended (higher rate) |
| `tax.isa.recommended_basic` | 3 | Cash ISA recommended (basic rate) |
| `tax.isa.not_needed` | 3 | Cash ISA not needed for tax |
| `tax.isa.consider` | 3 | Consider Cash ISA (approaching PSA) |
| `tax.isa_transfer.better_rate` | 3 | Transfer ISA for better rate |
| `tax.isa_transfer.to_investment` | 3 | Transfer Cash ISA to S&S ISA |
| `rates.switch_recommended` | 4 | Better rate available |
| `rates.maturity_warning` | 4 | Fixed rate maturing 30-90 days |
| `rates.maturity_urgent` | 4 | Fixed rate maturing < 30 days |
| `rates.promo_expiring` | 4 | Promotional rate expiring |
| `rates.base_rate_change` | 4 | Base rate changed |
| `rates.regular_saver` | 4 | Regular saver opportunity |
| `rates.regular_saver_ending` | 4 | Regular saver term ending |
| `products.notice_account` | 5 | Notice account recommended |
| `products.fixed_rate` | 5 | Fixed rate bond recommended |
| `products.cash_isa` | 5 | Cash ISA recommended |
| `products.cash_isa_fixed` | 5 | Fixed Cash ISA recommended |
| `products.lisa` | 5 | LISA recommended |
| `products.lisa_warning` | 5 | LISA withdrawal penalty warning |
| `products.junior_isa` | 5 | Junior ISA recommended |
| `products.premium_bonds` | 5 | Premium Bonds recommended |
| `products.nsi` | 5 | NS&I product recommended |
| `products.nsi_green` | 5 | NS&I Green Bonds (ESG) |
| `products.offset_mortgage` | 5 | Offset mortgage recommended |
| `fscs.breach` | 6 | Over £85,000 at one institution |
| `fscs.approaching` | 6 | Approaching £85,000 limit |
| `fscs.within_limit` | 6 | Within FSCS limit |
| `fscs.nsi_exempt` | 6 | NS&I government-backed |
| `fscs.spread` | 6 | Spread savings recommendation |
| `fscs.temporary_high` | 6 | Temporary high balance info |
| `debt.critical_rate` | 7 | Debt > 15% |
| `debt.exceeds_savings` | 7 | Debt > savings rate |
| `debt.below_savings` | 7 | Debt < savings rate |
| `debt.promo_expiring` | 7 | Debt promo rate expiring |
| `debt.minimal_emergency` | 7 | Build 1 month then clear debt |
| `debt.offset_recommended` | 7 | Offset saves more than earning |
| `debt.savings_preferred` | 7 | Savings earn more than mortgage |
| `cash_vs_invest.losing_value` | 8 | Real return negative |
| `cash_vs_invest.opportunity_cost` | 8 | Cash drag cost |
| `cash_vs_invest.short_term` | 8 | Cash correct for short term |
| `cash_vs_invest.medium_term` | 8 | Consider mix for medium term |
| `cash_vs_invest.long_term` | 8 | Invest for long term |
| `goals.emergency_fund` | 9 | Emergency fund account type |
| `goals.lisa_eligible` | 9 | LISA for property deposit |
| `goals.on_track` | 9 | Goal on track |
| `goals.at_risk` | 9 | Goal at risk |
| `goals.unachievable` | 9 | Goal exceeds capacity |
| `children.jisa_remaining` | 10 | JISA allowance remaining |
| `children.recommend_jisa` | 10 | Recommend opening JISA |
| `children.parental_settlement` | 10 | Parental settlement breach |
| `children.within_threshold` | 10 | Within £100 threshold |
| `children.jisa_exempt` | 10 | JISA exempt from settlement |
| `children.ctf_transfer` | 10 | Transfer CTF to JISA |
| `children.turning_18` | 10 | Child account maturing |
| `spouse.psa_shift` | 11 | Hold savings in lower-rate name |
| `spouse.psa_critical` | 11 | Additional rate, no PSA |
| `spouse.iht_note` | 11 | IHT concentration caveat |
| `spouse.isa_coordination` | 11 | Gift for spouse ISA |
| `spouse.household_isa` | 11 | Household ISA capacity |

---

## Appendix A: Existing Codebase References

### Services (app/Services/Savings/)

| File | Purpose | Status |
|------|---------|--------|
| `EmergencyFundCalculator.php` | Runway, adequacy, top-up calculations | Exists -- extend for employment-based targets |
| `GoalProgressCalculator.php` | Goal progress, projections, prioritisation | Exists -- extend for account type matching |
| `ISATracker.php` | ISA allowance tracking across Cash/S&S/LISA | Exists -- extend for transfer recommendations |
| `LiquidityAnalyzer.php` | Liquidity categorisation, ladder, risk | Exists -- extend for emergency fund location check |
| `RateComparator.php` | Rate comparison to market benchmarks | Exists -- extend for maturity/promo alerts |

### Models

| File | Key Fields |
|------|-----------|
| `SavingsAccount.php` | `account_type`, `access_type`, `interest_rate`, `is_isa`, `isa_type`, `is_emergency_fund`, `maturity_date`, `notice_period_days`, `institution`, `current_balance`, `ownership_type`, `beneficiary_id` |
| `SavingsGoal.php` | `goal_name`, `target_amount`, `current_saved`, `target_date`, `priority`, `linked_account_id` (deprecated -- use Goal model) |
| `SavingsMarketRate.php` | `rate_key`, `label`, `rate`, `tax_year`, `effective_from` |
| `ISAAllowanceTracking.php` | `user_id`, `tax_year`, `cash_isa_used`, `stocks_shares_isa_used`, `lisa_used`, `total_used`, `total_allowance` |

### Agent

| File | Methods |
|------|---------|
| `SavingsAgent.php` | `analyze()`, `generateRecommendations()`, `buildScenarios()` |

### New Services Required

| Service | Purpose |
|---------|---------|
| `TaxEfficiencyAnalyzer` | PSA assessment, starting rate, ISA strategy |
| `FSCSProtectionService` | Institution exposure, licence group checking |
| `DebtSavingsComparator` | Savings rate vs debt rate analysis |
| `CashDragCalculator` | Excess cash identification, opportunity cost |
| `ProductSuitabilityEngine` | Match user profile to product recommendations |
| `ChildrensSavingsService` | Junior ISA, parental settlement, CTF |
| `SpouseSavingsOptimiser` | PSA shifting, ISA coordination |
| `SavingsLifeEventService` | Life event modifiers for savings |
| `SavingsConflictResolver` | Priority-based surplus allocation |
| `SavingsAdequacyService` | Overall health metrics and scoring |
| `BankingLicenceService` | FSCS licence group data management |

---

## Appendix B: Data Sources for External Rates

| Data Point | Source | Update Method |
|-----------|--------|--------------|
| Best-buy savings rates | Moneyfacts / SavingsChampion | Manual update via `SavingsMarketRatesSeeder` |
| Bank of England Base Rate | bankofengland.co.uk | Manual update (8 times/year) |
| CPI Inflation | ONS | Manual update (monthly) |
| Premium Bonds Prize Fund Rate | nsandi.com | Manual update (variable) |
| FSCS Banking Licence Groups | FSCS / PRA register | Manual update (infrequent changes) |
| NS&I Product Rates | nsandi.com | Manual update (variable) |
| Help to Save Status | gov.uk | Closed to new applicants Sept 2025 |

---

## Appendix C: Fynla Savings Account Type Enum Values

The `account_type` field on `SavingsAccount` should support all of:

```
easy_access
notice_30
notice_60
notice_90
notice_120
fixed_1_year
fixed_2_year
fixed_3_year
fixed_5_year
regular_saver
cash_isa
cash_isa_fixed
cash_isa_notice
lisa
junior_isa
premium_bonds
nsi_direct_saver
nsi_income_bonds
nsi_green
offset
help_to_save
credit_union
current_account
other
```

The `access_type` field:
```
immediate    -- instant withdrawal
notice       -- requires notice period (notice_period_days field)
fixed        -- locked until maturity_date
```

The `isa_type` field (when `is_isa = true`):
```
cash         -- Cash ISA (easy access, fixed, or notice)
LISA         -- Lifetime ISA
junior       -- Junior ISA
```
