# Investment Recommendation Engine: Complete Decision Tree & Reference

> **Engine version:** v2.0 | **Tax year:** 2025/26 | **Last updated:** 2026-03-14

---

## Table of Contents

1. [Engine Pipeline Overview](#1-engine-pipeline-overview)
2. [User Context: Data Inputs](#2-user-context-data-inputs)
3. [Phase 1: Data Readiness Gate](#3-phase-1-data-readiness-gate)
4. [Phase 2a: Life Event Assessment](#4-phase-2a-life-event-assessment)
5. [Phase 2b: Goal Assessment](#5-phase-2b-goal-assessment)
6. [Phase 3: Safety Checks](#6-phase-3-safety-checks)
7. Phase 4: Contribution Waterfall
8. Phase 5: Portfolio Health Check
9. Phase 6: Transfer & Optimisation Scans
10. Phase 7: Spouse Optimisation
11. Phase 8: Compliance & Suitability
12. Phase 9: Conflict Resolution
13. Phase 10: Output Formatting & Priority
14. Tax Rules Reference (CGT, Dividends, ISA, Pension, Bonds, VCT/EIS/SEIS)
15. Thresholds & Constants Reference
16. Upcoming Tax Changes
17. Config Message Key Reference

---

## 1. Engine Pipeline Overview

```
User Request
    |
    v
[Phase 1] DataReadinessGate ──── can_proceed = false? ──> STOP
    |
    v
[Phase 2a] LifeEventAssessment ──> modifiers (blocks, triggers, wrappers)
[Phase 2b] GoalAssessment ──> goal modifiers (blocked/suitable wrappers)
    |
    v
[Phase 3] SafetyChecks ──> safety blocks + surplus adjustments
    |
    v
[Phase 4] ContributionWaterfall ──> 11-step wrapper priority allocation
    |
    v
[Phase 5] PortfolioHealthCheck ──> diversification, rebalancing, fee analysis
    |
    v
[Phase 6] TransferScans ──> Bed & ISA, tax loss harvesting, PSA/dividend breach, consolidation
    |
    v
[Phase 7] SpouseOptimisation ──> 7 household strategies
    |
    v
[Phase 8] ComplianceSuitability ──> Consumer Duty, suitability alignment, review triggers
    |
    v
[Phase 9] ConflictResolution ──> merged, deduplicated, conflict-resolved
    |
    v
[Phase 10] OutputFormatter ──> sorted, formatted API response
```

**Key principle:** Each phase feeds forward. Safety blocks reduce surplus. Life events block/prioritise wrappers. Portfolio health runs post-waterfall. Every phase can produce user-facing messages with a config key, severity, and personalised context.

### v2.0 Changes from v0.8.0

| Area | v0.8.0 | v2.0 |
|------|--------|------|
| Waterfall steps | 9 | 11 (added Employer Share Schemes, Cash ISA) |
| Phases | 7 + output | 10 + output |
| Portfolio analysis | None | Phase 5 (diversification, rebalancing, fee drag) |
| Compliance | None | Phase 8 (Consumer Duty, suitability, review triggers) |
| Safety checks | 4 (debt, emergency, protection, employer match) | 7 (added student loan, mortgage reset, employer schemes) |
| Life events | 17 types | 20 types (added starting_business, caring_responsibilities, pension_access_approaching) |
| User inputs | 7 profiles | 9 profiles (added Portfolio, Employer Benefits) |
| Transfer scans | 7 | Expanded with tax loss harvesting, platform consolidation |
| Spouse strategies | 6 | 7 (added Dividend Allowance sharing) |

---

## 2. User Context: Data Inputs

**Service:** `UserContextBuilder` | **File:** `app/Services/Investment/Recommendation/UserContextBuilder.php`

UserContextBuilder produces no user-facing messages. It assembles the data context consumed by every downstream service. Understanding what data feeds each decision is essential for tracing why a particular output appears.

### 2.1 Personal Profile

| Field | Source | Type | Used By |
|-------|--------|------|---------|
| `age` | Calculated from `user.date_of_birth` | int | LISA eligibility, pension age gate, under-18 path, retirement proximity, pension access approaching |
| `date_of_birth` | `user.date_of_birth` | date | Phase 1 readiness gate |
| `gender` | `user.gender` | string | Actuarial calculations |
| `marital_status` | `user.marital_status` | enum | Spouse optimisation gate |
| `employment_status` | `user.employment_status` (normalised: part_time/other -> employed) | string | Emergency fund target, pension recommendations, employer scheme eligibility |
| `retirement_age` | `user.retirement_age` or default 67 | int | Years to retirement calculation |
| `years_to_retirement` | `retirement_age - age` | int | Approaching retirement detection, glide path, pension access approaching |
| `is_homeowner` | Boolean from properties | bool | Goal assessment (property purchase) |
| `has_dependents` | `familyMembers->count() > 0` | bool | Protection safety check |
| `number_of_dependents` | Count of family members | int | Protection message |
| `youngest_dependent_age` | Min age of family members | int | Protection personal context |
| `uk_resident` | `user.uk_resident` | bool | LISA eligibility |

### 2.2 Financial Profile

| Field | Source | Type | Used By |
|-------|--------|------|---------|
| `gross_annual_income` | Sum of 7 income sources: employment, self-employment, rental, dividend, interest, other, trust | decimal | Phase 1 gate, tax band derivation |
| `net_monthly_income` | `gross * 0.7 / 12` (fallback estimate) | decimal | Disposable income calculation |
| `monthly_expenditure` | `user.monthly_expenditure` | decimal | Emergency fund, affordability |
| `disposable_income` | `net_monthly_income - monthly_expenditure` | decimal | Surplus for waterfall |
| `disposable_percent` | `(disposable_income / net_monthly_income) * 100` | decimal | Pension affordability tier, VCT gate |
| `tax_band` | Derived from income: basic (<50,270), higher (<125,140), additional (>=125,140) | string | Tax relief rates, PSA, dividend allowance |
| `personal_allowance` | 12,570 (tapered above 100k: reduces by 1 for every 2 over 100k) | decimal | Marriage Allowance |
| `pension_annual_allowance` | 60,000 (tapered if threshold income >200k AND adjusted income >260k, floor 10k) | decimal | Pension contribution cap |
| `relevant_uk_earnings` | Employment + self-employment income only | decimal | Pension contribution limit |
| `student_loan_plan` | `user.student_loan_plan` (Plan 1, 2, 4, 5, PG, or null) | string/null | Safety check 5 (student loan impact), surplus adjustment |
| `student_loan_balance` | `user.student_loan_balance` | decimal/null | Student loan payoff proximity, early repayment assessment |
| `student_loan_monthly_deduction` | Calculated from plan thresholds and income | decimal | Surplus reduction in safety checks |

### 2.3 Risk Profile

| Field | Source | Type | Used By |
|-------|--------|------|---------|
| `risk_level` | `riskProfile.risk_level` | string | Phase 1 gate, Cash ISA transfer gate, asset allocation targets |
| `risk_tolerance` | `riskProfile.risk_tolerance` | string | Risk assessment, portfolio health |
| `risk_capacity` | Mapped from `capacity_for_loss_percent`: <=20%=low, <=50%=medium, >50%=high | string | VCT eligibility, IFISA eligibility |
| `investment_experience` | `riskProfile.investment_experience` | string | Bond eligibility, VCT eligibility |
| `comfortable_with_illiquidity` | `riskProfile.comfortable_with_illiquidity` | bool | VCT gate |
| `comfortable_with_capital_loss` | `riskProfile.comfortable_with_capital_loss` | bool | VCT gate |
| `esg_preference` | `riskProfile.esg_preference` | bool | NS&I green bond note, ESG fund recommendations |

### 2.4 Debt Profile

| Field | Source | Type | Used By |
|-------|--------|------|---------|
| `debts` | All debts excluding mortgage and student_loan | collection | Safety check (debt assessment) |
| `high_interest_debts` | Debts with `interest_rate > 15%` | collection | Critical debt block |
| `medium_interest_debts` | Debts with `interest_rate 5-15%` | collection | High debt warning |
| `promotional_rate_expiry` | From debt records | date/null | Promotional rate alert |
| `mortgage_fixed_rate_end_date` | From mortgage record `fixed_rate_end_date` | date/null | Safety check 6 (mortgage rate reset) |
| `mortgage_current_rate` | From mortgage record `interest_rate` | decimal/null | Mortgage rate reset surplus impact |

### 2.5 Emergency Fund Profile

| Field | Source | Type | Used By |
|-------|--------|------|---------|
| `total` | Sum of `is_emergency_fund` savings + cash accounts | decimal | Emergency fund tier assessment |
| `runway` | `total / monthly_expenditure` | decimal | Emergency fund tier (months of cover) |
| `target` | Employment-based: self_employed=9, unemployed=6, retired=3, employed=6 months | int | Emergency fund gap calculation |
| `shortfall_amount` | `(target * monthly_expenditure) - total` | decimal | Emergency fund recommendation amount |

### 2.6 Allowances Profile

| Field | Source | Type | Used By |
|-------|--------|------|---------|
| `isa_remaining` | 20,000 - current year ISA subscriptions | decimal | ISA waterfall step, Bed & ISA |
| `lisa_remaining` | 4,000 - current year LISA subscriptions | decimal | LISA waterfall step |
| `pension_aa_remaining` | Via AnnualAllowanceChecker | decimal | Pension waterfall step |
| `carry_forward_available` | From pension carry forward records | decimal | Carry forward step |
| `is_tapered` | Boolean (pension AA tapered) | bool | Pension personal context |
| `mpaa_triggered` | Boolean | bool | ISA priority boost, pension cap |
| `cgt_allowance_remaining` | 3,000 - realised gains | decimal | CGT sharing, Bed & ISA |
| `psa_remaining` | PSA by band: basic=1,000, higher=500, additional=0 | decimal | PSA breach scan |
| `dividend_allowance_remaining` | 500 - dividends received | decimal | Dividend breach scan |
| `seis_remaining` | 200,000 - current year SEIS investments | decimal | SEIS waterfall allocation cap |
| `eis_remaining` | 1,000,000 - current year EIS investments | decimal | EIS waterfall allocation cap |
| `vct_remaining` | 200,000 - current year VCT investments | decimal | VCT waterfall allocation cap |

### 2.7 Spouse Profile

Only populated if `marital_status` is `married` or `civil_partnership` AND a spouse user record exists.

| Field | Source | Type | Used By |
|-------|--------|------|---------|
| `spouse.name` | `spouse.name` | string | Spouse strategy messages |
| `spouse.gross_annual_income` | Calculated from spouse income sources | decimal | Spouse tax band |
| `spouse.tax_band` | Derived from spouse income | string | PSA optimisation, pension coordination, dividend sharing |
| `spouse.isa_remaining` | Spouse's ISA allowance remaining | decimal | ISA coordination |
| `spouse.pension_aa_remaining` | Spouse's pension AA | decimal | Pension coordination |
| `spouse.carry_forward_available` | Spouse's carry forward | decimal | Carry forward strategy |
| `spouse.personal_allowance_used` | Whether spouse uses full PA | bool | Marriage Allowance |

### 2.8 Portfolio Profile (NEW)

Aggregated from all investment accounts and holdings. Used by the new Phase 5 (Portfolio Health Check) and enhanced transfer scans.

| Field | Source | Type | Used By |
|-------|--------|------|---------|
| `total_portfolio_value` | Sum of all investment account values | decimal | VCT allocation cap, diversification analysis |
| `asset_allocation` | Aggregated by asset class (equities, fixed_income, cash, alternatives, property, commodities) | object | Diversification check, rebalancing |
| `geographic_allocation` | Aggregated by region (uk, us, europe, asia_pacific, emerging_markets, global) | object | Geographic concentration check |
| `holdings` | Individual holdings with `cost_basis`, `current_value`, `ocf`, `fund_type`, `sector` | collection | Fee analysis, tax loss harvesting, concentration risk |
| `accounts` | Investment accounts with `platform`, `wrapper_type`, `value`, `annual_platform_fee` | collection | Platform consolidation scan |
| `last_rebalance_date` | Most recent rebalance across accounts | date/null | Rebalancing trigger |
| `weighted_average_ocf` | Portfolio-weighted OCF | decimal | Fee drag analysis |

### 2.9 Employer Benefits (NEW)

Populated from employer/pension records. Used by safety checks (employer match, share schemes) and the contribution waterfall.

| Field | Source | Type | Used By |
|-------|--------|------|---------|
| `employer_pension_match_percent` | From DC pension employer contribution | decimal | Safety check 4, pension waterfall step |
| `employer_pension_match_cap` | Maximum employer contribution amount | decimal | Pension waterfall step |
| `saye_scheme` | Boolean: employer offers Save As You Earn | bool | Safety check 7 (employer share schemes) |
| `saye_monthly_max` | SAYE monthly contribution limit (up to 500) | decimal | Waterfall step allocation |
| `saye_discount_percent` | SAYE share discount (up to 20%) | decimal | Personal context |
| `saye_maturity_date` | SAYE scheme maturity date | date/null | Transfer scan (ISA transfer window) |
| `sip_scheme` | Boolean: employer offers Share Incentive Plan | bool | Safety check 7 |
| `sip_free_shares` | Annual free shares value (up to 3,600) | decimal | Personal context |
| `sip_partnership_shares_max` | Max partnership share deduction (up to 1,800/yr or 10% salary) | decimal | Waterfall allocation |
| `sip_matching_ratio` | Employer matching ratio (up to 2:1) | string | Personal context |
| `csop_scheme` | Boolean: Company Share Option Plan | bool | Info note only |
| `emi_scheme` | Boolean: Enterprise Management Incentive | bool | Info note only |

---

## 3. Phase 1: Data Readiness Gate

**Service:** `DataReadinessService` | **File:** `app/Services/Investment/Recommendation/DataReadinessService.php`

The readiness gate runs six sequential phases. If any Phase 1 check returns a `block`, `can_proceed = false` and all subsequent engine phases are skipped. Warnings and info messages are collected but do not prevent the engine from continuing.

### Decision Tree

```
Phase 1 Prerequisites (any BLOCK = engine stops)
|
+-- date_of_birth is null?
|   YES -> BLOCK [R1]: "Your date of birth is needed..."
|          config_key: readiness.block.date_of_birth
|
+-- gross_annual_income <= 0?
|   YES -> BLOCK [R2]: "Your income details are needed..."
|          config_key: readiness.block.gross_annual_income
|
+-- No risk profile or risk_level is null?
|   YES -> BLOCK [R3]: "Complete your risk profile..."
|          config_key: readiness.block.risk_level
|
|   ANY BLOCK above? -> can_proceed = false, STOP
|   Engine returns readiness blocks only. No further phases execute.
|
Phase 2 Financial Data
|
+-- monthly_expenditure is null or <= 0?
|   YES -> BLOCK [R4]: "Your monthly expenditure is needed..."
|          config_key: readiness.block.monthly_expenditure
|
+-- employment_status is null?
|   YES -> WARN [R5]: "Adding your employment status helps..."
|          config_key: readiness.warn.employment_status
|
Phase 3 Safety Data
|
+-- Has family members BUT no protection profile?
|   YES -> WARN [R6]: "You have dependents but no protection profile..."
|          config_key: readiness.warn.protection_profile
|
Phase 4 Contribution Waterfall Data
|
+-- No DC pensions?
|   YES -> WARN [R7]: "Adding your workplace pension details..."
|          config_key: readiness.warn.dc_pensions
|
+-- No investment accounts AND no savings accounts?
|   YES -> INFO [R8]: "Add your existing savings and investment accounts..."
|          config_key: readiness.info.accounts
|
Phase 5 Transfer Scan Data
|
+-- No investment accounts?
|   YES -> WARN [R9]: "Add your investment accounts..."
|          config_key: readiness.warn.investment_accounts
|
Phase 6 Spouse & Life Event Data
|
+-- Married/civil_partnership but no spouse linked?
|   YES -> INFO [R10]: "Link your partner's account..."
|          config_key: readiness.info.spouse_link
|
+-- Always:
|   INFO [R11]: "Add any upcoming life events..."
|   config_key: readiness.info.life_events
|
+-- student_loan_plan is null AND age < 65 AND employment_status is employed?
    YES -> INFO [R12]: "Adding your student loan details helps..."
           config_key: readiness.info.student_loan
```

### Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| R1 | `date_of_birth` is null | `block` | `readiness.block.date_of_birth` | "Your date of birth is needed to assess age-related investment options like LISA eligibility and pension access." |
| R2 | `gross_annual_income <= 0` | `block` | `readiness.block.gross_annual_income` | "Your income details are needed to calculate tax bands, pension allowances, and affordable contribution levels." |
| R3 | No risk profile or `risk_level` null | `block` | `readiness.block.risk_level` | "Complete your risk profile so we can recommend investments suited to your comfort level." |
| R4 | `monthly_expenditure` null or <= 0 | `block` | `readiness.block.monthly_expenditure` | "Your monthly expenditure is needed to calculate emergency fund requirements and affordable investment amounts." |
| R5 | `employment_status` is null | `warn` | `readiness.warn.employment_status` | "Adding your employment status helps us tailor emergency fund targets and pension recommendations." |
| R6 | Has dependents, no protection profile | `warn` | `readiness.warn.protection_profile` | "You have dependents but no protection profile. Add your insurance details for better protection gap analysis." |
| R7 | No DC pensions | `warn` | `readiness.warn.dc_pensions` | "Adding your workplace pension details allows us to check employer matching and optimise pension contributions." |
| R8 | No investment or savings accounts | `info` | `readiness.info.accounts` | "Add your existing savings and investment accounts to receive transfer and optimisation recommendations." |
| R9 | No investment accounts | `warn` | `readiness.warn.investment_accounts` | "Add your investment accounts so we can identify tax-efficient transfer opportunities like Bed & ISA." |
| R10 | Married but no spouse linked | `info` | `readiness.info.spouse_link` | "Link your partner's account to unlock household tax optimisation strategies like CGT sharing and ISA coordination." |
| R11 | Always | `info` | `readiness.info.life_events` | "Add any upcoming life events (property purchase, retirement, new baby) to receive tailored investment advice." |
| R12 | Student loan plan missing, age < 65, employed | `info` | `readiness.info.student_loan` | "Adding your student loan details helps us account for repayment deductions when calculating your investable surplus." |

---

## 4. Phase 2a: Life Event Assessment

**Service:** `LifeEventAssessmentService` | **File:** `app/Services/Investment/Recommendation/LifeEventAssessmentService.php`

Life events produce **modifiers** that affect downstream phases: blocking wrappers, prioritising wrappers, requiring liquidity, overriding affordability, and generating sub-action recommendations. Each active life event is evaluated and its modifiers are collected into a single modifier set passed to subsequent phases.

### 4.1 Derived Events (Auto-Detected)

These are not stored in the database -- they are inferred from user context on every engine run.

```
years_to_retirement <= 5 AND > 0?
    YES -> derive "approaching_retirement" event
    |-- years_to_retirement value passed as event metadata

If life event is a windfall source (inheritance, gift_received, bonus,
lottery_windfall, property_sale, business_sale, pension_lump_sum) with amount > 0?
    YES -> derive "windfall" event
    |-- total windfall amount aggregated across all qualifying events

age within 2 years of pension access age (55, or 57 from April 2028)
AND age < retirement_age?
    YES -> derive "pension_access_approaching" event
    |-- pension_access_age passed as metadata (55 if born before 6 April 1971, 57 otherwise)
```

### 4.2 Stored Life Event Decision Tree

```
For each active life event:
|
+-- type = "redundancy"
|   |-- action: BLOCK
|   |-- blocked_wrappers: offshore_bond, onshore_bond, vct, eis, seis
|   |-- liquidity_priority: true
|   |-- affordability_override: true
|   |-- Sub-actions:
|   |   +-- review_emergency_fund
|   |   +-- review_protection
|   |-- Message: [LE1]
|   |-- config_key: life_events.redundancy.block
|
+-- type = "wedding"
|   |-- years_until <= 2?
|   |   YES -> action: TRIGGER, liquidity_priority: true
|   |   NO  -> action: INFO
|   |-- years_until <= 1? -> Sub-action: marriage tax planning
|   |-- Message: [LE2] (if <= 2 years)
|
+-- type = "inheritance"
|   |-- action: TRIGGER
|   |-- prioritised_wrappers: pension, stocks_shares_isa
|   |-- Sub-action: check_iht_position
|   |-- Message: [LE3]
|
+-- type = "property_sale" or "business_sale"
|   |-- action: TRIGGER
|   |-- cgt_check_required: true
|   |-- Sub-action: cgt_assessment
|   |-- Message: [LE4]
|
+-- type = "large_purchase"
|   |-- years_until <= 2?
|   |   YES -> action: BLOCK, blocked_wrappers: offshore_bond, onshore_bond, vct, eis, seis
|   |   NO  -> action: TRIGGER
|   |-- Message: [LE5]
|
+-- type = "education_fees"
|   |-- years_until <= 3?
|   |   YES -> action: TRIGGER, liquidity_priority: true
|   |   NO  -> action: INFO
|   |-- Message: [LE6]
|
+-- type = "gift_received" / "bonus" / "lottery_windfall"
|   |-- action: TRIGGER
|   |-- prioritised_wrappers: pension, stocks_shares_isa
|   |-- Message: [LE7]
|
+-- type = "pension_lump_sum"
|   |-- age >= 55?
|   |   YES -> action: TRIGGER
|   |   |-- prioritised_wrappers: stocks_shares_isa, cash_isa
|   |   |-- NOTE: Do NOT prioritise pension (money just left pension)
|   |   |-- Sub-action: consider_cash_reserve (hold 2-3 years drawdown in cash)
|   |   |-- Sub-action: review_mpaa_implications
|   |   |-- Message: [LE7b]
|   |-- age < 55?
|   |   YES -> action: TRIGGER
|   |   |-- prioritised_wrappers: pension, stocks_shares_isa
|   |   |-- Message: [LE7] (standard windfall path)
|
+-- type = "home_improvement" / "medical_expense"
|   |-- years_until <= 1?
|   |   YES -> action: BLOCK, liquidity_priority: true
|   |   NO  -> action: INFO
|   |-- Message: [LE8]
|
+-- type = "new_baby"
|   |-- action: TRIGGER
|   |-- prioritised_wrappers: junior_isa
|   |-- liquidity_priority: true
|   |-- Sub-actions: open_junior_isa, review_life_cover, check_child_benefit, review_will
|   |-- Message: [LE9]
|
+-- type = "marriage" / "civil_partnership"
|   |-- action: TRIGGER
|   |-- unlocks_spouse_optimisation: true
|   |-- Sub-actions: link_spouse_account, review_beneficiaries, check_marriage_allowance
|   |-- Message: [LE10]
|
+-- type = "divorce"
|   |-- action: BLOCK
|   |-- blocks_spouse_optimisation: true
|   |-- cgt_exemption_tax_year: true
|   |-- liquidity_priority: true
|   |-- Sub-actions: cgt_exemption_review, review_all_beneficiaries, review_estate_plan
|   |-- Message: [LE11]
|
+-- type = "separation"
|   |-- action: BLOCK
|   |-- blocks_spouse_optimisation: true
|   |-- liquidity_priority: true
|   |-- Sub-action: review_shared_accounts
|
+-- type = "career_change"
|   |-- action: TRIGGER
|   |-- liquidity_priority: true
|   |-- affordability_override: true
|   |-- Sub-actions: review_emergency_fund, review_pension_transfer
|
+-- type = "serious_illness"
|   |-- action: BLOCK
|   |-- blocked_wrappers: offshore_bond, onshore_bond, vct, eis, seis
|   |-- liquidity_priority: true
|   |-- Sub-actions: check_critical_illness_claim, review_income_protection, review_estate_plan
|   |-- Message: [LE12]
|
+-- type = "death_of_partner"
|   |-- action: BLOCK
|   |-- blocks_spouse_optimisation: true
|   |-- blocked_wrappers: offshore_bond, onshore_bond, vct, eis, seis
|   |-- liquidity_priority: true
|   |-- Sub-actions: review_inherited_assets, claim_bereavement_support,
|   |               review_all_beneficiaries, transfer_isa_allowance
|   |-- Message: [LE13]
|
+-- type = "child_turning_18"
|   |-- action: TRIGGER
|   |-- Sub-actions: convert_junior_isa, review_dependent_status
|   |-- Message: [LE14]
|
+-- type = "buying_a_home"
|   |-- action: BLOCK
|   |-- blocked_wrappers: offshore_bond, onshore_bond, vct, eis, seis
|   |-- prioritised_wrappers: lifetime_isa, cash_isa
|   |-- liquidity_priority: true
|   |-- Sub-actions: review_deposit_savings, (if age 18-39) consider_lisa
|   |-- Message: [LE15]
|
+-- type = "starting_business" (NEW)
|   |-- action: TRIGGER
|   |-- employment_status_override: self_employed
|   |   (raises emergency fund target to 9 months)
|   |-- liquidity_priority: true
|   |-- affordability_override: true
|   |-- blocked_wrappers: offshore_bond, onshore_bond, vct, eis, seis
|   |-- Sub-actions:
|   |   +-- review_emergency_fund
|   |   +-- review_pension_loss (loss of employer contributions)
|   |   +-- consider_sipp (self-employed pension planning)
|   |-- Message: [LE18]
|   |-- config_key: life_events.starting_business.trigger
|
+-- type = "caring_responsibilities" (NEW)
|   |-- action: TRIGGER
|   |-- liquidity_priority: true
|   |-- Sub-actions:
|   |   +-- review_emergency_fund
|   |   +-- review_carer_benefits (Carer's Allowance, Carer's Credit for NI)
|   |   +-- review_pension_impact (reduced earnings impact on pension)
|   |-- Message: [LE19]
|   |-- config_key: life_events.caring_responsibilities.trigger
|
+-- type = "pension_access_approaching" (DERIVED — NEW)
    |-- Condition: age within 2 years of pension access age (55, or 57 from April 2028)
    |             AND age < retirement_age
    |-- action: INFO
    |-- Sub-actions:
    |   +-- review_mpaa_implications
    |   +-- review_crystallisation_strategy
    |-- Message: [LE20]
    |-- config_key: life_events.pension_access_approaching.info
```

### 4.3 Derived Event Decision Tree

```
"approaching_retirement" (years_to_retirement <= 5):
|-- action: TRIGGER
|-- glide_path: true
|-- prioritised_wrappers: pension, stocks_shares_isa
|-- risk_reduction: true
|-- Sub-actions: review_pension_access_options, review_risk_profile
|-- Message: [LE16]
|-- config_key: life_events.approaching_retirement.trigger

"windfall" (lump sum from qualifying source):
|-- action: TRIGGER
|-- prioritised_wrappers: pension, stocks_shares_isa (+ premium_bonds if >= 50,000)
|-- contribution_type: lump_sum
|-- Sub-action (if property_sale/business_sale): CGT phasing note
|-- Message: [LE17]
|-- config_key: life_events.windfall.trigger

"pension_access_approaching" (age within 2 years of pension access age):
|-- action: INFO
|-- Sub-actions: review_mpaa_implications, review_crystallisation_strategy
|-- Message: [LE20]
|-- config_key: life_events.pension_access_approaching.info
```

### 4.4 Life Event Conflict Rules

```
RULE 1: BLOCKs always beat TRIGGERs
|
+-- If any modifier blocks a wrapper, trigger modifiers that
|   prioritise that wrapper have it removed from their
|   prioritised_wrappers list.
|
+-- Example: divorce blocks spouse optimisation -> marriage
|   event's unlocks_spouse_optimisation is overridden.

RULE 2: Spouse optimisation gate
|
+-- If any modifier has blocks_spouse_optimisation = true,
|   ALL spouse strategies in Phase 7 are skipped entirely.
|   Any modifier with unlocks_spouse_optimisation = true
|   is overridden.

RULE 3: Liquidity aggregation
|
+-- If ANY event sets liquidity_priority = true, the waterfall
|   reduces allocation to illiquid wrappers (bonds, VCT/EIS/SEIS)
|   even if those wrappers are not explicitly blocked.

RULE 4: Affordability override stacking
|
+-- Multiple affordability_override events do NOT stack.
|   A single override reduces the surplus by 25%.
|   This is applied once, regardless of how many events
|   have affordability_override = true.

RULE 5: Employment status override precedence
|
+-- If starting_business sets employment_status_override
|   to self_employed, this takes precedence over the stored
|   employment_status for emergency fund target calculation
|   (raises target from 6 to 9 months).
```

### 4.5 Life Event Message Reference

| # | Event Type | Config Key | Message |
|---|-----------|------------|---------|
| LE1 | redundancy | `life_events.redundancy.block` | "Following redundancy, focus on building liquid reserves. Avoid illiquid investments until your income stabilises." |
| LE2 | wedding (<=2yr) | *(inline)* | "Your wedding in {years} {year/years} means keeping {amount} accessible in cash." |
| LE3 | inheritance | *(inline)* | "An inheritance of {amount} may push your estate closer to the Inheritance Tax threshold. Review your nil-rate band position and consider tax-efficient wrappers for the proceeds." |
| LE4 | property_sale / business_sale | *(inline)* | "{Type} proceeds of {amount} may trigger a capital gains tax liability. Review your annual Capital Gains Tax exemption before reinvesting." |
| LE5 | large_purchase (<=2yr) | *(inline)* | "Large purchase of {amount} within {years} {year/years} requires accessible funds." |
| LE6 | education_fees | *(inline)* | "Education fees require predictable, accessible savings." |
| LE7 | income events (gift/bonus/lottery) | *(inline)* | "{Type} of {amount} could be directed to tax-efficient wrappers." |
| LE7b | pension_lump_sum (age >= 55) | `life_events.pension_lump_sum.post_access` | "Your pension lump sum of {amount} should be directed to ISA and cash reserves rather than back into a pension. Consider holding 2-3 years of planned drawdown income in cash. Be aware that accessing pension flexibly triggers the Money Purchase Annual Allowance." |
| LE8 | expense events (<=1yr) | *(inline)* | "{Type} of {amount} expected within {years} {year/years}." |
| LE9 | new_baby | `life_events.new_baby.trigger` | "Consider opening a Junior ISA ({limit}/year allowance) and reviewing your life cover." |
| LE9a | new_baby (income >50k) | `life_events.new_baby.child_benefit` | "Income over 50,000 triggers High Income Child Benefit Charge." |
| LE10 | marriage | `life_events.marriage.trigger` | "Marriage opens up valuable tax planning opportunities. Link your partner's account to unlock household optimisation." |
| LE11 | divorce | `life_events.divorce.trigger` | "During divorce, interspousal asset transfers are Capital Gains Tax-exempt in the tax year of separation. Review beneficiaries across all accounts." |
| LE12 | serious_illness | `life_events.serious_illness.block` | "Focus on liquidity and protection claim eligibility. Avoid illiquid investments during this period." |
| LE13 | death_of_partner | `life_events.death_of_partner.block` | "Your inherited ISA allowance and bereavement support eligibility should be reviewed. Avoid major financial decisions during this period." |
| LE14 | child_turning_18 | `life_events.child_turning_18.trigger` | "When your child turns 18, their Junior ISA converts to an adult ISA. Review your dependent count and protection needs." |
| LE15 | buying_a_home | `life_events.buying_a_home.trigger` | "Keep your deposit funds in accessible accounts. Illiquid investments should wait until after completion." |
| LE16 | approaching_retirement | `life_events.approaching_retirement.trigger` | "You are {years} {year_word} from retirement. Your investment strategy should gradually shift towards lower-risk assets." |
| LE17 | windfall | `life_events.windfall.trigger` | "A windfall of {amount} gives you an opportunity to boost your tax-efficient investments." |
| LE18 | starting_business | `life_events.starting_business.trigger` | "Starting a business means prioritising liquidity and building a larger emergency fund (9 months as self-employed). Review the impact of losing employer pension contributions and consider a Self-Invested Personal Pension for tax-efficient retirement saving." |
| LE19 | caring_responsibilities | `life_events.caring_responsibilities.trigger` | "Caring responsibilities may reduce your earning capacity. Review your emergency fund, check eligibility for Carer's Allowance and Carer's Credit (which protects your State Pension record), and consider the impact on your pension contributions." |
| LE20 | pension_access_approaching | `life_events.pension_access_approaching.info` | "You are approaching the minimum pension access age ({access_age}). Before accessing any pension flexibly, understand the Money Purchase Annual Allowance implications -- flexible access reduces your annual pension contribution limit from {current_aa} to {mpaa_limit}." |

### 4.6 Life Event Sub-Action Messages

| Event | Sub-Action | Config Key | Message |
|-------|-----------|------------|---------|
| redundancy | review_emergency_fund (amount > 0) | `life_events.redundancy.sub.emergency_fund_payment` | "Your redundancy payment of {amount} should be prioritised for emergency reserves until re-employment is secured." |
| redundancy | review_emergency_fund (amount = 0) | `life_events.redundancy.sub.emergency_fund_build` | "Build emergency reserves to 9-12 months of expenditure during the transition period." |
| redundancy | review_protection | `life_events.redundancy.sub.protection` | "Review whether existing income protection and critical illness cover remains in force without employer sponsorship." |
| approaching_retirement | review_pension_access_options | `life_events.approaching_retirement.sub.access_options` | "With {years} {year/years} to retirement, now is the time to compare drawdown flexibility against annuity guaranteed income." |
| approaching_retirement | review_risk_profile | `life_events.approaching_retirement.sub.risk_profile` | "With {years} {year/years} to retirement, consider a glide path to gradually reduce equity exposure and protect accumulated gains." |
| new_baby | open_junior_isa | `life_events.new_baby.sub.junior_isa` | "Junior ISA allowance is {limit} per year." |
| new_baby | review_life_cover | `life_events.new_baby.sub.life_cover` | "Additional dependent increases life cover requirements." |
| new_baby | check_child_benefit | `life_events.new_baby.sub.child_benefit` | "Income over 50,000 triggers High Income Child Benefit Charge." |
| new_baby | review_will | `life_events.new_baby.sub.will` | "Update beneficiaries to include new child." |
| marriage | link_spouse_account | `life_events.marriage.sub.link_spouse` | "Link partner accounts to enable household tax optimisation." |
| marriage | review_beneficiaries | `life_events.marriage.sub.beneficiaries` | "Update beneficiaries on all policies and pensions." |
| marriage | check_marriage_allowance | `life_events.marriage.sub.marriage_allowance` | "Marriage Allowance can save up to 252 per year for eligible couples." |
| divorce | cgt_exemption_review | `life_events.divorce.sub.cgt_exemption` | "Interspousal transfers are Capital Gains Tax-exempt in the tax year of separation. Use this window for tax-efficient asset division." |
| divorce | review_all_beneficiaries | `life_events.divorce.sub.beneficiaries` | "Update beneficiaries on all policies, pensions, and accounts." |
| divorce | review_estate_plan | `life_events.divorce.sub.estate_plan` | "Review and update will and estate planning." |
| serious_illness | check_critical_illness_claim | `life_events.serious_illness.sub.critical_illness` | "Check critical illness policy eligibility." |
| serious_illness | review_income_protection | `life_events.serious_illness.sub.income_protection` | "Check income protection policy provisions." |
| serious_illness | review_estate_plan | `life_events.serious_illness.sub.estate_plan` | "Review estate planning as a priority." |
| death_of_partner | review_inherited_assets | `life_events.death_of_partner.sub.inherited_assets` | "Assess inherited assets and any Inheritance Tax liability." |
| death_of_partner | claim_bereavement_support | `life_events.death_of_partner.sub.bereavement` | "Check eligibility for Bereavement Support Payment." |
| death_of_partner | review_all_beneficiaries | `life_events.death_of_partner.sub.beneficiaries` | "Update beneficiaries on all policies and pensions." |
| death_of_partner | transfer_isa_allowance | `life_events.death_of_partner.sub.isa_aps` | "Inherited ISA allowance (Additional Permitted Subscription) may be available." |
| child_turning_18 | convert_junior_isa | `life_events.child_turning_18.sub.convert_jisa` | "Junior ISA automatically converts to adult ISA at 18." |
| child_turning_18 | review_dependent_status | `life_events.child_turning_18.sub.dependent_status` | "Reassess dependent status for protection calculations." |
| buying_a_home | review_deposit_savings | `life_events.buying_a_home.sub.deposit` | "Ensure deposit is in accessible accounts." |
| buying_a_home | consider_lisa (age 18-39) | `life_events.buying_a_home.sub.lisa` | "Lifetime ISA offers 25% government bonus on first home purchases up to 450,000." |
| career_change | review_emergency_fund | `life_events.career_change.sub.emergency_fund` | "Ensure 6-9 months emergency fund during transition." |
| career_change | review_pension_transfer | `life_events.career_change.sub.pension_transfer` | "Consider consolidating previous employer pension." |
| windfall (property/business sale) | cgt_phasing | `life_events.windfall.sub.cgt_phasing` | "Your {source} of {amount} may trigger Capital Gains Tax. Check your annual exemption and consider phasing disposals across tax years if possible." |
| starting_business | review_emergency_fund | `life_events.starting_business.sub.emergency_fund` | "As self-employed, build emergency reserves to at least 9 months of expenditure before committing to long-term investments." |
| starting_business | review_pension_loss | `life_events.starting_business.sub.pension_loss` | "Leaving employment means losing employer pension contributions. Calculate the value of lost employer matching and consider whether to increase personal contributions to compensate." |
| starting_business | consider_sipp | `life_events.starting_business.sub.sipp` | "A Self-Invested Personal Pension offers flexibility for self-employed investors. Contributions receive tax relief at your marginal rate and can be timed around business income fluctuations." |
| caring_responsibilities | review_emergency_fund | `life_events.caring_responsibilities.sub.emergency_fund` | "Caring may reduce your income. Review your emergency fund to ensure it covers the adjusted household expenditure." |
| caring_responsibilities | review_carer_benefits | `life_events.caring_responsibilities.sub.carer_benefits` | "Carer's Allowance is {amount}/week if you care for someone at least 35 hours/week and earn under {earnings_limit}/week. Carer's Credit can protect your State Pension entitlement." |
| caring_responsibilities | review_pension_impact | `life_events.caring_responsibilities.sub.pension_impact` | "Reduced earnings may affect pension contributions and annual allowance usage. Review whether to adjust contribution levels or rely on Carer's Credit for State Pension protection." |
| pension_lump_sum (age >= 55) | consider_cash_reserve | `life_events.pension_lump_sum.sub.cash_reserve` | "Hold 2-3 years of planned retirement income in cash to avoid selling investments during market downturns (sequencing risk)." |
| pension_lump_sum (age >= 55) | review_mpaa_implications | `life_events.pension_lump_sum.sub.mpaa` | "Accessing your pension flexibly triggers the Money Purchase Annual Allowance, reducing future pension contribution limits from {current_aa} to {mpaa_limit}." |
| pension_access_approaching | review_mpaa_implications | `life_events.pension_access_approaching.sub.mpaa` | "Before accessing any pension flexibly, understand that the Money Purchase Annual Allowance will limit future contributions to {mpaa_limit}." |
| pension_access_approaching | review_crystallisation_strategy | `life_events.pension_access_approaching.sub.crystallisation` | "Consider whether phased crystallisation, full crystallisation, or annuity purchase best suits your retirement income needs. Each has different tax and flexibility implications." |

---

## 5. Phase 2b: Goal Assessment

**Service:** `GoalAssessmentService` | **File:** `app/Services/Investment/Recommendation/GoalAssessmentService.php`

Goals produce **wrapper modifiers**: lists of suitable wrappers, blocked wrappers, and advisory notes that feed into the contribution waterfall. Each goal type has a specific decision path, and timeline-based blocking applies across all goal types.

### Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `TIMELINE_SHORT` | < 2 years | Cash-only wrappers |
| `TIMELINE_MEDIUM` | 2-5 years | ISA wrappers appropriate |
| `TIMELINE_LONG` | 5-10 years | Equity wrappers appropriate |
| `TIMELINE_VERY_LONG` | > 10 years | Full wrapper range |
| `DEFAULT_INVESTMENT_RETURN` | 5% | Benchmark for debt comparison |

### Decision Tree

```
For each active goal:
|
+-- type = "property_purchase"
|   |
|   +-- age 18-39 AND price < 450,000 AND first_time_buyer?
|   |   YES -> suitable_wrappers: lifetime_isa, cash_isa, savings_account
|   |   |-- note: "Eligible for Lifetime ISA 25% government bonus on first
|   |   |          home purchase."
|   |   |-- config_key: goals.property.lisa_eligible
|   |   NO (price >= 450,000) ->
|   |       note: "Property price of {price} exceeds the Lifetime ISA limit
|   |              of 450,000."
|   |       config_key: goals.property.lisa_price_exceeded
|   |   NO (age >= 40, no existing LISA) ->
|   |       note: "Over 39 -- cannot open a new Lifetime ISA."
|   |       config_key: goals.property.lisa_age_exceeded
|   |
|   +-- years_to_goal < 3?
|       YES -> note: "Property purchase within 3 years -- keep deposit in
|              cash or near-cash."
|              blocked_wrappers: stocks_shares_isa, pension, offshore_bond,
|                                onshore_bond, vct, eis, seis
|              config_key: goals.property.short_term_block
|
+-- type = "retirement"
|   |
|   +-- years_to_retirement <= 10?
|   |   YES -> note: "With {years} years to retirement, consider a glide
|   |           path reducing equity exposure."
|   |           config_key: goals.retirement.glide_path
|   |
|   +-- years_to_retirement <= 5?
|       YES -> note: "Within 5 years of retirement -- review pension access
|              strategy (drawdown vs annuity)."
|              config_key: goals.retirement.access_strategy
|
+-- type = "debt_repayment"
|   |
|   +-- debt_interest_rate > 5% (DEFAULT_INVESTMENT_RETURN)?
|       YES -> note: "Debt interest at {rate}% exceeds expected investment
|              returns of 5.0%. Prioritise debt repayment."
|              priority elevated to HIGH
|              config_key: goals.debt.high_rate
|
+-- type = "emergency_fund"
|   |-- note: "Emergency fund must remain in instant-access cash accounts."
|   |-- suitable_wrappers: savings_account, cash_isa
|   |-- blocked_wrappers: everything else
|   |-- config_key: goals.emergency_fund.cash_only
|
+-- type = "education"
|   |
|   +-- timeline SHORT (< 2 years)?
|   |   YES -> note: "Education fees due within 2 years -- keep in cash."
|   |           suitable_wrappers: savings_account, cash_isa
|   |           config_key: goals.education.short_term
|   |   NO  -> note: "Longer-term education savings can benefit from
|   |           equity growth."
|   |           suitable_wrappers: stocks_shares_isa, pension, junior_isa
|   |           config_key: goals.education.long_term
|
+-- type = (generic/other)
    |
    +-- timeline SHORT  -> suitable: savings_account, cash_isa
    +-- timeline MEDIUM -> suitable: cash_isa, stocks_shares_isa
    +-- timeline LONG   -> suitable: stocks_shares_isa, pension
    +-- timeline VERY_LONG -> suitable: stocks_shares_isa, pension
```

### Timeline-Based Wrapper Blocking (All Goal Types)

These blocks apply regardless of goal type. They represent wrapper unsuitability based on time horizon.

| Timeline | Blocked Wrappers | Reason |
|----------|-----------------|--------|
| SHORT (< 2 years) | offshore_bond, onshore_bond, vct, eis, seis, pension | Too illiquid for short-term goals |
| MEDIUM (2-5 years) | offshore_bond, onshore_bond, vct, eis, seis | Too illiquid for medium-term goals |
| LONG (5-10 years) | *(none blocked)* | All wrappers suitable |
| VERY_LONG (> 10 years) | *(none blocked)* | All wrappers suitable |

### Implicit Emergency Fund Goal

```
Has NO explicit "emergency_fund" goal?
AND emergency fund shortfall > 0 (shortfall_amount > 0)?
    YES -> Auto-create implicit emergency fund goal:
           |-- suitable_wrappers: savings_account, cash_isa
           |-- blocked_wrappers: all other wrappers
           |-- priority: HIGH
           |-- amount: shortfall_amount
           |-- note: "Implicit emergency fund goal created from shortfall."
```

### Goal Modifier Output

Each assessed goal produces a modifier object passed to the waterfall:

```
{
    goal_id: int,
    goal_type: string,
    goal_name: string,
    timeline: string (SHORT|MEDIUM|LONG|VERY_LONG),
    years_to_goal: int,
    target_amount: decimal,
    current_amount: decimal,
    shortfall: decimal,
    suitable_wrappers: string[],
    blocked_wrappers: string[],
    priority: string (critical|high|medium|low|info),
    notes: string[]
}
```

---

## 6. Phase 3: Safety Checks

**Service:** `SafetyCheckService` | **File:** `app/Services/Investment/Recommendation/SafetyCheckService.php`

Safety checks run before the contribution waterfall and can **reduce the surplus available** to Phase 4. Critical blocks can reduce surplus to zero, preventing any investment recommendations from being generated. Safety checks always generate user-facing messages regardless of their surplus impact.

### Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `DEFAULT_EXPECTED_RETURN` | 0.05 (5%) | Benchmark for debt cost comparison |
| `MORTGAGE_EXCEPTION_RATE` | 3.0 | Mortgages below this rate excluded from debt checks |
| `STUDENT_LOAN_PLAN_1_THRESHOLD` | 26,065 | Plan 1 repayment threshold |
| `STUDENT_LOAN_PLAN_2_THRESHOLD` | 28,470 | Plan 2 repayment threshold |
| `STUDENT_LOAN_PLAN_4_THRESHOLD` | 32,745 | Plan 4 (Scotland) repayment threshold |
| `STUDENT_LOAN_PLAN_5_THRESHOLD` | 25,000 | Plan 5 repayment threshold |
| `STUDENT_LOAN_PG_THRESHOLD` | 21,000 | Postgraduate loan repayment threshold |
| `STUDENT_LOAN_RATE` | 0.09 (9%) | Plans 1/2/4/5 repayment rate |
| `STUDENT_LOAN_PG_RATE` | 0.06 (6%) | Postgraduate loan repayment rate |
| `SAYE_MAX_MONTHLY` | 500 | Maximum SAYE monthly saving |
| `SAYE_MAX_DISCOUNT` | 0.20 (20%) | Maximum SAYE share price discount |
| `SIP_FREE_SHARES_MAX` | 3,600 | Maximum annual free shares |
| `SIP_PARTNERSHIP_MAX` | 1,800 | Maximum annual partnership shares |

### Decision Tree

```
Guard: net_monthly_income <= 0?
    YES -> disposable_income = 0, surplus = 0
           No further safety checks run.
    NO  -> continue

=====================================
CHECK 1: HIGH-INTEREST DEBT
=====================================
|
For each debt (excluding mortgage, student_loan):
|
+-- interest_rate > 15%?
|   YES -> CRITICAL [S1]
|   |-- action: redirect_100_percent_to_debt
|   |-- surplus effect: remaining = 0
|   |-- config_key: safety.debt.critical
|   |-- personal_context: "Your {type} balance of {balance} at {rate}%
|   |                      costs {annualCost}/year in interest -- more
|   |                      than typical investment returns of 5%. Clear
|   |                      this before investing."
|
+-- interest_rate 5-15% AND annualDebtCost > expectedReturn?
|   YES -> HIGH [S2]
|   |-- action: reduce_surplus_50_percent
|   |-- surplus effect: remaining -= 50%
|   |-- config_key: safety.debt.high
|   |-- personal_context: "Your {type} of {balance} at {rate}% costs
|   |                      {annualDebtCost}/year vs estimated
|   |                      {expectedReturn} investment return. Split your
|   |                      {surplus} surplus between debt repayment and
|   |                      investing."
|
+-- promotional_rate_expiry within 6 months?
    YES -> MEDIUM [S3]
    |-- action: warn_rate_increase
    |-- surplus effect: none
    |-- config_key: safety.debt.promotional
    |-- personal_context: "Your 0% rate on {type} expires on {date}.
                           Plan for the rate increase to avoid the
                           balance becoming high-interest debt."

=====================================
CHECK 2: EMERGENCY FUND
=====================================
|
+-- monthly_expenditure <= 0?
|   YES -> INFO [S4] "Monthly expenditure data is missing..."
|   |-- config_key: safety.emergency_fund.no_expenditure
|   STOP emergency fund check (cannot calculate tiers)
|
+-- runway < 1 month?
|   YES -> CRITICAL [S5]
|   |-- action: block_non_essential_investment
|   |-- surplus effect: capped = 0
|   |-- config_key: safety.emergency_fund.critical
|   |-- personal_context: "Your liquid reserves of {totalSavings} cover
|   |                      less than 1 month against {monthlyExp}/month
|   |                      expenditure. As {employmentLabel}, you need
|   |                      {target} months of cover ({targetAmount}), a
|   |                      shortfall of {shortfall}."
|
+-- runway 1-3 months?
|   YES -> HIGH [S6]
|   |-- action: cap_investment_50_percent
|   |-- surplus effect: capped = surplus * 0.5
|   |-- config_key: safety.emergency_fund.high
|   |-- personal_context: "Your {totalSavings} in reserves covers {runway}
|   |                      months of your {monthlyExp}/month expenditure.
|   |                      Build to {target} months ({targetAmount}) before
|   |                      committing fully to investments."
|
+-- runway 3 months to target?
|   YES -> MEDIUM [S7]
|   |-- action: parallel_recommendation
|   |-- surplus effect: none (invest alongside building)
|   |-- config_key: safety.emergency_fund.medium
|   |-- personal_context: "Your {totalSavings} covers {runway} months. You
|   |                      can invest alongside building your fund to the
|   |                      {target}-month target of {targetAmount}."
|
+-- runway > target + 3 months?
    YES -> INFO [S8]
    |-- action: transfer_excess
    |-- config_key: safety.emergency_fund.excess
    |-- personal_context: "Your {totalSavings} exceeds the {target}-month
                           target by {excessMonths} months. Consider
                           investing the excess {excessAmount} for better
                           long-term returns."

=====================================
CHECK 3: PROTECTION GAPS (ENHANCED)
=====================================
|
+-- has_dependents = true?
|   |
|   +-- Single-earner household (spouse income <= 0 or no spouse)?
|   |   AND life_cover_total < (gross_annual_income * 5)?
|   |   YES -> HIGH [S9a]
|   |   |-- action: review_protection_urgent
|   |   |-- config_key: safety.protection.single_earner
|   |   |-- personal_context: "As the sole earner with {dependentCount}
|   |   |                      {dependentWord}, your life cover of
|   |   |                      {lifeCover} is less than 5 times your
|   |   |                      {income} income. Consider increasing
|   |   |                      cover before committing to investments."
|   |
|   +-- Otherwise ->
|       MEDIUM [S9]
|       |-- action: review_protection
|       |-- config_key: safety.protection.dependents
|       |-- personal_context: "With {dependentCount} {dependentWord}
|                               {ageNote} and {income} household income,
|                               ensure life cover and income protection
|                               are adequate before prioritising
|                               investments."
|
+-- has_dependents = false?
    SKIP (no protection check)

=====================================
CHECK 4: EMPLOYER MATCH (always surfaced)
=====================================
|
+-- Any DC pension with employer_contribution_percent > 0?
    YES -> [S10] (always shown, regardless of other safety checks)
    |-- config_key: safety.employer_match.always
    |-- personal_context: "Your employer matches pension contributions
    |                      up to {percent}%. This is a {matchValue}
    |                      guaranteed return on your money. Contribute
    |                      at least enough to get the full match."

=====================================
CHECK 5: STUDENT LOAN IMPACT (NEW)
=====================================
|
+-- student_loan_plan is not null?
|   |
|   +-- Calculate monthly deduction based on plan:
|   |   Plan 1: 9% above 26,065/yr (2,172/mo threshold)
|   |   Plan 2: 9% above 28,470/yr (2,373/mo threshold)
|   |   Plan 4: 9% above 32,745/yr (2,729/mo threshold)
|   |   Plan 5: 9% above 25,000/yr (2,083/mo threshold)
|   |   PG:     6% above 21,000/yr (1,750/mo threshold)
|   |
|   |   Formula (Plans 1/2/4/5):
|   |     monthly_deduction = max(0, (gross_annual_income - threshold) * 0.09 / 12)
|   |   Formula (PG):
|   |     monthly_deduction = max(0, (gross_annual_income - 21,000) * 0.06 / 12)
|   |
|   +-- Has BOTH a plan loan AND a postgraduate loan?
|   |   YES -> Both deductions calculated and applied simultaneously
|   |   |-- total_student_loan_deduction = plan_deduction + pg_deduction
|   |
|   +-- monthly_deduction > 0?
|       YES -> INFO [S11]
|       |-- action: reduce_surplus_by_deduction
|       |-- surplus effect: surplus -= monthly_deduction
|       |-- config_key: safety.student_loan.impact
|       |-- personal_context: "Your student loan (Plan {plan}) deducts
|       |                      {deduction}/month from your income above
|       |                      the {threshold} threshold. This has been
|       |                      factored into your investable surplus."
|       |
|       +-- Has dual loans?
|           YES -> append note: "You have both a Plan {plan} and
|                  Postgraduate loan, with combined deductions of
|                  {total}/month."
|                  config_key: safety.student_loan.dual

=====================================
CHECK 6: MORTGAGE RATE RESET (NEW)
=====================================
|
+-- mortgage_fixed_rate_end_date is not null?
    AND mortgage_fixed_rate_end_date within 12 months?
    |
    YES -> WARN [S12]
    |-- action: warn_rate_reset
    |-- Calculate estimated_increase:
    |   current_payment = mortgage balance * current_rate / 12
    |   estimated_new_payment = mortgage balance * (current_rate + 2%) / 12
    |   monthly_increase = estimated_new_payment - current_payment
    |-- surplus effect: surplus -= monthly_increase
    |-- config_key: safety.mortgage.rate_reset
    |-- personal_context: "Your fixed mortgage rate of {currentRate}%
    |                      ends on {endDate}. If your rate increases by
    |                      approximately 2 percentage points, your
    |                      monthly payment could rise by {increase}.
    |                      This has been factored into your surplus."
    |
    +-- mortgage_fixed_rate_end_date within 3 months?
        YES -> append note: "Your fixed rate ends very soon. Contact
               your lender about remortgage options now to avoid
               reverting to the Standard Variable Rate."
               config_key: safety.mortgage.imminent_reset

=====================================
CHECK 7: EMPLOYER SHARE SCHEMES (NEW)
=====================================
|
Pre-waterfall check, alongside employer match.
|
+-- saye_scheme = true AND not currently participating?
|   YES -> INFO [S13a]
|   |-- config_key: safety.employer_schemes.saye
|   |-- personal_context: "Your employer offers a Save As You Earn scheme.
|   |                      You can save up to {max}/month towards
|   |                      discounted shares (up to {discount}% discount).
|   |                      Gains are tax-free on exercise. Shares can be
|   |                      transferred to an ISA within 90 days of exercise
|   |                      to shelter future growth."
|   |-- note: "SAYE shares can be transferred to an ISA within 90 days
|              of exercise, preserving tax-free status."
|
+-- sip_scheme = true AND not currently participating?
|   YES -> INFO [S13b]
|   |-- config_key: safety.employer_schemes.sip
|   |-- personal_context: "Your employer offers a Share Incentive Plan.
|   |                      Free shares up to {free_max}/year, partnership
|   |                      shares up to {partnership_max}/year from pre-tax
|   |                      salary, with employer matching up to {match_ratio}.
|   |                      Hold for 5 years and pay no Income Tax, National
|   |                      Insurance, or Capital Gains Tax on the shares."
|   |-- note: "SIP shares held for 5 years are completely free of Income
|              Tax, National Insurance, and Capital Gains Tax. Shares can
|              also be transferred to an ISA on leaving the plan."
|
+-- csop_scheme = true?
|   YES -> INFO [S13c] (note only)
|   |-- config_key: safety.employer_schemes.csop
|   |-- note: "Your employer offers a Company Share Option Plan. Exercise
|              gains are subject to Capital Gains Tax (not Income Tax) if
|              options are held for 3 years."
|
+-- emi_scheme = true?
    YES -> INFO [S13d] (note only)
    |-- config_key: safety.employer_schemes.emi
    |-- note: "Your employer offers Enterprise Management Incentive options.
               Capital Gains Tax at 10% (Business Asset Disposal Relief) on
               gains up to the lifetime limit if held for 2 years."
```

### Emergency Fund Target by Employment Status

| Employment Status | Target (months) | Rationale |
|-------------------|-----------------|-----------|
| self_employed | 9 | Income volatility, no employer sick pay |
| unemployed | 6 | Active job search period |
| retired | 3 | Stable pension/drawdown income |
| employed (default) | 6 | Standard buffer |
| starting_business (life event override) | 9 | Treated as self_employed |

### Student Loan Repayment Thresholds (2025/26)

| Plan | Threshold (Annual) | Threshold (Monthly) | Rate | Typical Borrower |
|------|-------------------|---------------------|------|-----------------|
| Plan 1 | 26,065 | 2,172 | 9% | Pre-2012 England/Wales, or NI |
| Plan 2 | 28,470 | 2,373 | 9% | Post-2012 England/Wales |
| Plan 4 | 32,745 | 2,729 | 9% | Scotland |
| Plan 5 | 25,000 | 2,083 | 9% | Post-2023 England |
| Postgraduate | 21,000 | 1,750 | 6% | Postgraduate loan (any nation) |

**Dual loan example:** A user earning 45,000 with Plan 2 + Postgraduate loan:
- Plan 2: (45,000 - 28,470) * 0.09 / 12 = 124.03/month
- PG: (45,000 - 21,000) * 0.06 / 12 = 120.00/month
- Total: 244.03/month deducted from surplus

### Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| S1 | Debt rate > 15% | `critical` | `safety.debt.critical` | "You have {type} debt at {rate}% interest. Repaying this should come before investing." |
| S2 | Debt rate 5-15%, cost > return | `high` | `safety.debt.high` | "Your {type} debt at {rate}% costs more than typical investment returns. Consider splitting surplus between debt repayment and investing." |
| S3 | Promotional rate expiry < 6mo | `medium` | `safety.debt.promotional` | "Your 0% promotional rate on {type} expires on {date}. Plan for the rate increase." |
| S4 | No expenditure data | `info` | `safety.emergency_fund.no_expenditure` | "Monthly expenditure data is missing. Please update your expenditure profile for accurate emergency fund assessment." |
| S5 | Emergency fund < 1 month | `critical` | `safety.emergency_fund.critical` | "Your emergency fund covers less than 1 month of expenses. Build this to at least {target} months before investing." |
| S6 | Emergency fund 1-3 months | `high` | `safety.emergency_fund.high` | "Your emergency fund covers {runway} months. We recommend {target} months. Investment limited to 50% of surplus." |
| S7 | Emergency fund 3mo to target | `medium` | `safety.emergency_fund.medium` | "Your emergency fund covers {runway} months. Consider building to {target} months alongside investing." |
| S8 | Emergency fund > target + 3mo | `info` | `safety.emergency_fund.excess` | "Your emergency fund exceeds the target by {months} months. Consider investing the excess of {amount}." |
| S9 | Has dependents (dual earner or adequate cover) | `medium` | `safety.protection.dependents` | "You have {count} {dependent_word}. Ensure adequate life cover and income protection before prioritising investments." |
| S9a | Single earner, life cover < 5x income | `high` | `safety.protection.single_earner` | "As the sole earner with {count} {dependent_word}, your life cover of {cover} is less than 5 times your {income} income. Increasing cover should be a priority." |
| S10 | Employer match available | *(always)* | `safety.employer_match.always` | "Your employer offers {percent}% pension matching. Contribute at least enough to get the full match, even if other safety checks apply." |
| S11 | Student loan deduction > 0 | `info` | `safety.student_loan.impact` | "Your student loan (Plan {plan}) deducts {deduction}/month from your income. This has been factored into your investable surplus." |
| S11a | Dual student loans | `info` | `safety.student_loan.dual` | "You have both a Plan {plan} and Postgraduate loan, with combined deductions of {total}/month." |
| S12 | Mortgage fixed rate ending within 12mo | `warn` | `safety.mortgage.rate_reset` | "Your fixed mortgage rate of {rate}% ends on {date}. Potential payment increase of {increase}/month has been factored into your surplus." |
| S12a | Mortgage fixed rate ending within 3mo | `warn` | `safety.mortgage.imminent_reset` | "Your fixed rate ends very soon. Contact your lender about remortgage options now to avoid reverting to the Standard Variable Rate." |
| S13a | SAYE scheme available | `info` | `safety.employer_schemes.saye` | "Your employer offers Save As You Earn -- up to {max}/month towards shares at up to {discount}% discount, tax-free on exercise." |
| S13b | SIP scheme available | `info` | `safety.employer_schemes.sip` | "Your employer offers a Share Incentive Plan -- free shares, partnership shares, and employer matching, all tax-free if held for 5 years." |
| S13c | CSOP scheme available | `info` | `safety.employer_schemes.csop` | "Your employer offers Company Share Option Plan options. Exercise gains are subject to Capital Gains Tax (not Income Tax) after 3 years." |
| S13d | EMI scheme available | `info` | `safety.employer_schemes.emi` | "Your employer offers Enterprise Management Incentive options with potential Business Asset Disposal Relief at 10% Capital Gains Tax." |

### Surplus Impact Summary

| Safety Check | Surplus Effect | Condition |
|-------------|---------------|-----------|
| Critical debt (>15%) | Surplus = 0 | Any debt with rate > 15% |
| High debt (5-15%) | Surplus reduced by 50% | Debt cost exceeds expected return |
| Emergency fund critical (<1mo) | Surplus capped at 0 | Runway < 1 month |
| Emergency fund low (1-3mo) | Surplus capped at 50% | Runway 1-3 months |
| Student loan deduction | Surplus -= monthly deduction | Active student loan |
| Mortgage rate reset | Surplus -= estimated monthly increase | Fixed rate ending within 12 months |
| Promotional rate expiry | No surplus reduction | Warning only |
| Protection gaps | No surplus reduction | Warning only |
| Employer match | No surplus reduction | Always shown if available |
| Employer share schemes | No surplus reduction | Informational only |

**Surplus reduction ordering:** Debt and emergency fund blocks apply first (they can set surplus to zero). If surplus survives, student loan deduction and mortgage rate reset are subtracted from the remaining amount. The result is the surplus passed to Phase 4.

### Output Headline Mapping (via RecommendationOutputFormatter)

| Check Code | Headline Shown in UI |
|-----------|---------------------|
| `high_interest_debt` | "Clear high-interest debt first" |
| `medium_interest_debt` | "Consider splitting surplus with debt repayment" |
| `promotional_rate_expiry` | "Promotional rate expiring soon" |
| `emergency_fund_critical` | "Build emergency fund" |
| `emergency_fund_low` | "Strengthen emergency fund" |
| `emergency_fund_building` | "Continue building emergency fund" |
| `emergency_fund_excess` | "Excess emergency fund -- consider investing" |
| `emergency_fund_no_expenditure` | "Update expenditure data" |
| `protection_with_dependents` | "Review protection cover" |
| `protection_single_earner` | "Increase life cover urgently" |
| `student_loan_impact` | "Student loan deductions applied" |
| `mortgage_rate_reset` | "Mortgage rate reset approaching" |
| `employer_saye` | "Consider employer Save As You Earn" |
| `employer_sip` | "Consider employer Share Incentive Plan" |

---

*End of Part 1 (Sections 1-6). Sections 7-17 will follow in Part 2.*
## 7. Phase 4: Contribution Waterfall

**Service:** `ContributionWaterfallService` | **File:** `app/Services/Investment/Recommendation/ContributionWaterfallService.php`

The waterfall allocates the **remaining surplus** (after safety check deductions) across wrappers in strict priority order. Each step consumes as much surplus as it can (up to wrapper limits), then passes the remainder to the next step. The v1.0 waterfall expands from 9 to 12 steps, separating SEIS/EIS/VCT into individual steps and adding new regulatory notes.

### Pre-Waterfall Guards

```
GUARD 1: SURPLUS CHECK
|
+-- surplus <= 0?
    YES -> return empty + note: [W0] "No surplus available for investment."
    NO  -> continue

GUARD 2: AGE CHECK
|
+-- age < 18?
    YES -> run Under-18 path (see below)
    NO  -> continue

GUARD 3: OWNERSHIP CHECK
|
+-- ownership_type = "trust"?
    YES -> run Trust path (see below)
    NO  -> continue to Standard Waterfall
```

### Under-18 Path

Only two wrappers are available for under-18s: Junior ISA and GIA.

```
UNDER-18 WATERFALL
==================

[Step A] Junior ISA
|
+-- junior_isa_remaining > 0?
|   YES -> allocate = MIN(surplus, junior_isa_remaining)
|          RECOMMEND:
|            headline: "Junior ISA"
|            explanation: "Tax-free savings for under-18s with a {juniorIsaLimit}
|                         annual limit."
|            note: "Converts to adult ISA at 18."
|            priority: high
|
|   Reduce surplus by allocate
|
+-- NO -> Skip: "Junior ISA allowance fully used."

[Step B] GIA (Minor)
|
+-- surplus > 0?
    YES -> allocate = surplus
           RECOMMEND:
             headline: "General Investment Account (minor)"
             explanation: "Remaining funds invested via GIA in the
                          parent/guardian name."
             note: "Income taxed at parent's marginal rate if >100/year
                    from parental gift."
             priority: low

Decision note: "Under 18: Junior ISA and GIA only." [W0_UNDER18]
```

### Trust Path

Trusts have restricted wrapper access: offshore bond (for tax deferral) and GIA.

```
TRUST WATERFALL
===============

[Determine trust type]
|
+-- bare_trust?
|   Decision note: "Bare trust -- using beneficiary's marginal rate."
|   trust_income_rate = beneficiary's marginal rate
|   trust_cgt_rate = beneficiary's CGT rate
|
+-- discretionary_trust?
    Decision note: "Trust account -- income taxed at 45%, gains at 20%."
    trust_income_rate = 45%
    trust_cgt_rate = 20%

[Step A] Offshore Bond (Trust)
|
+-- trust_income_rate >= 40%?
|   AND surplus >= 10,000?
|   YES -> allocate = MIN(surplus, suitable_amount)
|          RECOMMEND:
|            headline: "Offshore bond for trust"
|            explanation: "Offshore bonds grow tax-deferred within the trust,
|                         deferring income and gains until encashment."
|            note: "Trust income rate is {trust_income_rate}%.
|                   Offshore bond defers this."
|            priority: medium
|
+-- NO -> Skip (rate too low or insufficient amount)

[Step B] GIA (Trust)
|
+-- surplus > 0?
    YES -> allocate = surplus
           RECOMMEND:
             headline: "General Investment Account (trust)"
             explanation: "Remaining trust funds in GIA."
             note (bare): "Bare trust uses beneficiary's rate."
             note (discretionary): "Trust rates apply. Consider
                                    accumulation funds to minimise
                                    annual distributions."
             priority: low
```

### Standard Waterfall (12 Steps)

Each step follows this pattern:
1. Check skip conditions (age, allowance, life event blocks, etc.)
2. If not skipped, calculate allocation amount
3. Generate recommendation with headline, explanation, personal_context, priority, notes
4. Reduce remaining surplus by allocation amount
5. Pass remainder to next step

---

#### Step 1: Lifetime ISA (LISA)

```
SKIP CONDITIONS (any = skip this step):
|
+-- "lifetime_isa" in blockedWrappers?
|   YES -> skip: "Blocked by life event."
|
+-- age < 18?
|   YES -> skip: "Under 18 -- not eligible."
|
+-- age > 49?
|   YES -> skip: [W1a] "Over 50 -- cannot contribute to LISA."
|
+-- uk_resident = false?
|   YES -> skip: "UK residency required for LISA."
|
+-- No property_purchase goal AND age >= 40?
|   YES -> skip: [W1b] "No qualifying property goal and over 39 --
|                        cannot open new LISA."
|
+-- lisa_remaining <= 0?
    YES -> skip: "LISA allowance fully used."

ALLOCATION:
  allocate = MIN(surplus, lisa_remaining)
  government_bonus = allocate * 0.25

RECOMMEND: [W1]
  headline: "Contribute to Lifetime ISA"
  explanation: "The 25% government bonus makes LISA the most effective
               wrapper for eligible first-time buyers or retirement savings."
  personal_context: "At age {age}, a {amount} LISA contribution earns a
                    {taxRelief} government bonus (25%). You have
                    {lisaRemaining} of your {lisaLimit} LISA allowance
                    remaining this tax year."
  priority: high
  amount: allocate
  tax_relief: government_bonus

  note (always): "LISA withdrawals for first home require 12 months
                  from first contribution."
  note (if age = 49): "Last year to contribute to LISA before age 50
                       cutoff."

  Reduce surplus by allocate
  Reduce isa_remaining by allocate (LISA counts towards ISA limit)
```

| # | Field | Value |
|---|-------|-------|
| W1 | headline | "Contribute to Lifetime ISA" |
| W1 | explanation | "The 25% government bonus makes LISA the most effective wrapper for eligible first-time buyers or retirement savings." |
| W1 | personal_context | "At age {age}, a {amount} LISA contribution earns a {taxRelief} government bonus (25%). You have {lisaRemaining} of your {lisaLimit} LISA allowance remaining this tax year." |
| W1 | priority | `high` |
| W1 | note (always) | "LISA withdrawals for first home require 12 months from first contribution." |
| W1 | note (if age 49) | "Last year to contribute to LISA before age 50 cutoff." |
| W1a | skip reason | "Over 50 -- cannot contribute to LISA." |
| W1b | skip reason | "No qualifying property goal and over 39 -- cannot open new LISA." |

---

#### Step 2: Stocks & Shares ISA

```
SKIP CONDITIONS:
|
+-- "stocks_shares_isa" in blockedWrappers?
|   YES -> skip: "Blocked by life event."
|
+-- age < 18?
|   YES -> skip: "Under 18."
|
+-- isa_remaining <= 0?
    YES -> skip: "ISA allowance fully used."

MPAA VARIANT:
  mpaa_triggered = true?
    YES -> headline = "Maximise ISA (pension limited by Money Purchase Annual Allowance)"
           explanation = "With MPAA triggered, your pension annual allowance
                         is reduced to {mpaaLimit}. ISA becomes your primary
                         tax-efficient wrapper."
           priority = critical
    NO  -> headline = "Contribute to Stocks & Shares ISA"
           explanation = "ISA shelters investments from income tax and capital
                         gains tax with no lifetime limit."
           priority = high

YEAR-END URGENCY:
  months_to_april_5 < 3 AND isa_remaining > 5,000?
    YES -> note: "Only {months} {month_word} left in the tax year with
                  {remaining} ISA allowance remaining."

ALLOCATION:
  allocate = MIN(surplus, isa_remaining)
  tax_saving_rate = additional ? 45% : higher ? 40% : 20%

RECOMMEND: [W2]
  personal_context: "You have {isaRemaining} of your {isaLimit} ISA
                    allowance remaining. As a {taxBand}-rate taxpayer,
                    sheltering investments in an ISA saves you
                    {taxSaving} on dividends and capital gains."
  amount: allocate

  Reduce surplus by allocate
  Reduce isa_remaining by allocate
```

| # | Field | Value |
|---|-------|-------|
| W2 | headline (normal) | "Contribute to Stocks & Shares ISA" |
| W2 | headline (MPAA) | "Maximise ISA (pension limited by Money Purchase Annual Allowance)" |
| W2 | explanation (normal) | "ISA shelters investments from income tax and capital gains tax with no lifetime limit." |
| W2 | explanation (MPAA) | "With MPAA triggered, your pension annual allowance is reduced to {mpaaLimit}. ISA becomes your primary tax-efficient wrapper." |
| W2 | personal_context | "You have {isaRemaining} of your {isaLimit} ISA allowance remaining. As a {taxBand}-rate taxpayer, sheltering investments in an ISA saves you {taxSaving} on dividends and capital gains." |
| W2 | priority (MPAA) | `critical` |
| W2 | priority (normal) | `high` |
| W2 | note (year-end) | "Only {months} {month_word} left in the tax year with {remaining} ISA allowance remaining." |

---

#### Step 3: Pension (Current Year)

```
SKIP CONDITIONS:
|
+-- "pension" in blockedWrappers?
|   YES -> skip: "Blocked by life event."
|
+-- age >= 75?
|   YES -> skip: "Over 75 -- pension contributions not allowed."
|
+-- pension_aa_remaining <= 0?
    YES -> skip: "Pension annual allowance fully used."

AFFORDABILITY TIERS:
|
+-- disposable_percent < 5%?
|   tier = RESTRICTED
|   cap = remaining * 0.25
|   note: [W3_RESTRICTED] "Contribution limited due to low disposable
|          income (below 5%)."
|
+-- disposable_percent < 10%?
|   tier = MODERATE
|   cap = remaining * 0.50
|
+-- disposable_percent >= 10%?
    tier = COMFORTABLE
    cap = remaining (no cap)

TAX RELIEF CALCULATION:
|
+-- basic rate?
|   tax_relief_rate = 20%
|   explanation = "Pension contributions receive 20% tax relief."
|   priority = medium
|
+-- higher rate?
|   tax_relief_rate = 40%
|   explanation = "Pension contributions receive 40% tax relief. Your
|                  net cost is {net} for a {gross} gross contribution."
|   priority = high
|
+-- additional rate?
    tax_relief_rate = 45%
    explanation = "Pension contributions receive 45% tax relief. Your
                   net cost is {net} for a {gross} gross contribution."
    priority = critical

MPAA NOTE:
  mpaa_triggered = true?
    YES -> append to explanation: "MPAA limits your money purchase
           annual allowance to {mpaaLimit}."

IHT CHANGE NOTE (NEW):
  estate_value > (nrb + rnrb) * (married ? 2 : 1)?
    YES -> note: "From April 2027, pensions will be brought within the
                  scope of Inheritance Tax. If your estate already exceeds
                  the nil-rate band, maximising pension contributions for
                  IHT purposes may become less advantageous."

SALARY SACRIFICE NOTE (NEW):
  employment_status = employed AND employer_offers_salary_sacrifice?
    YES -> note: "Salary sacrifice saves National Insurance contributions
                  (currently {employee_nic_rate}%) in addition to income
                  tax relief. Your employer also saves {employer_nic_rate}%
                  -- some employers share this saving."

  salary_sacrifice_amount > 2,000?
    YES -> note: "From April 2029, the National Insurance advantage on
                  salary sacrifice will be capped at the first 2,000 of
                  contributions."

ALLOCATION:
  allocate = MIN(cap, pension_aa_remaining, relevant_uk_earnings)
  net_cost = allocate * (1 - tax_relief_rate)
  tax_relief = allocate * tax_relief_rate

RECOMMEND: [W3]
  headline: "Pension contribution ({taxReliefRate}% tax relief)"
  personal_context: "At age {age}{retirementNote}, a {amount} pension
                    contribution costs you just {netCost} after
                    {rate}% tax relief. You have {pensionRemaining}
                    of annual allowance remaining."
  amount: allocate
  tax_relief: tax_relief
  effective_cost: net_cost

  Reduce surplus by net_cost (user pays net, not gross)
  Reduce pension_aa_remaining by allocate (gross counts towards AA)
```

| # | Field | Value |
|---|-------|-------|
| W3 | headline | "Pension contribution ({taxReliefRate}% tax relief)" |
| W3 | explanation (basic) | "Pension contributions receive 20% tax relief." |
| W3 | explanation (higher) | "Pension contributions receive 40% tax relief. Your net cost is {net} for a {gross} gross contribution." |
| W3 | explanation (additional) | "Pension contributions receive 45% tax relief. Your net cost is {net} for a {gross} gross contribution." |
| W3 | explanation (MPAA appended) | "MPAA limits your money purchase annual allowance to {limit}." |
| W3 | personal_context | "At age {age}{retirementNote}, a {amount} pension contribution costs you just {netCost} after {rate}% tax relief. You have {pensionRemaining} of annual allowance remaining." |
| W3 | priority (additional) | `critical` |
| W3 | priority (higher) | `high` |
| W3 | priority (basic) | `medium` |
| W3 | note (restricted) | "Contribution limited due to low disposable income (below 5%)." |
| W3 | note (IHT change) | "From April 2027, pensions will be brought within the scope of Inheritance Tax..." |
| W3 | note (salary sacrifice) | "Salary sacrifice saves National Insurance contributions in addition to income tax relief..." |
| W3 | note (NIC cap) | "From April 2029, the National Insurance advantage on salary sacrifice will be capped at the first 2,000 of contributions." |

---

#### Step 4: Premium Bonds

```
SKIP CONDITIONS:
|
+-- "premium_bonds" in blockedWrappers?
|   YES -> skip: "Blocked by life event."
|
+-- contributionType = "regular"?
|   YES -> skip: "Premium Bonds best suited for lump sum contributions."
|
+-- age < 16?
|   YES -> skip: "Under 16."
|
+-- tax_band = basic AND psa_remaining > 0?
|   YES -> skip: "Personal Savings Allowance not exceeded and basic
|                  rate -- other savings may be more effective."
|
+-- current_premium_bonds >= 50,000?
    YES -> skip: [W4a] "Premium Bonds maximum holding of {max}
                        already reached."

ALLOCATION:
  max_available = 50,000 - current_premium_bonds
  allocate = MIN(surplus, max_available)

RECOMMEND: [W4]
  headline: "Premium Bonds"
  explanation: "Premium Bond prizes are tax-free. Effective for
               higher/additional rate taxpayers who have exceeded
               their Personal Savings Allowance."
  personal_context: "As a {taxBand}-rate taxpayer, Premium Bond prizes
                    are tax-free -- equivalent to a higher gross rate
                    on a taxable account. You hold {currentHolding}
                    of the {maxHolding} maximum."
  priority: medium
  amount: allocate

  Reduce surplus by allocate
```

| # | Field | Value |
|---|-------|-------|
| W4 | headline | "Premium Bonds" |
| W4 | explanation | "Premium Bond prizes are tax-free. Effective for higher/additional rate taxpayers who have exceeded their Personal Savings Allowance." |
| W4 | personal_context | "As a {taxBand}-rate taxpayer, Premium Bond prizes are tax-free -- equivalent to a higher gross rate on a taxable account. You hold {currentHolding} of the {maxHolding} maximum." |
| W4 | priority | `medium` |
| W4a | skip reason | "Premium Bonds maximum holding of {max} already reached." |

---

#### Step 5: NS&I Products

```
SKIP CONDITIONS:
|
+-- "nsi_savings" in blockedWrappers?
|   YES -> skip: "Blocked by life event."
|
+-- Allocation (10% of remaining surplus) < 25?
    YES -> skip: "Insufficient surplus for NS&I allocation."

ALLOCATION:
  allocate = MIN(surplus * 0.10, surplus)
  (NS&I receives a conservative 10% of remaining surplus)

ESG NOTE:
  esg_preference = true?
    YES -> note: "Consider NS&I Green Savings Bonds to align with
                  your ESG preferences."

RECOMMEND: [W5]
  headline: "NS&I Savings"
  explanation: "NS&I products are backed by HM Treasury, offering
               security for conservative allocations."
  personal_context: "Allocating {amount} to NS&I -- 100%
                    government-backed with no deposit limit.
                    A defensive holding within your portfolio."
  priority: low
  amount: allocate

  Reduce surplus by allocate
```

| # | Field | Value |
|---|-------|-------|
| W5 | headline | "NS&I Savings" |
| W5 | explanation | "NS&I products are backed by HM Treasury, offering security for conservative allocations." |
| W5 | personal_context | "Allocating {amount} to NS&I -- 100% government-backed with no deposit limit. A defensive holding within your portfolio." |
| W5 | priority | `low` |
| W5 | note (ESG pref) | "Consider NS&I Green Savings Bonds to align with your ESG preferences." |

---

#### Step 6: Offshore Bond

```
SKIP CONDITIONS:
|
+-- "offshore_bond" in blockedWrappers?
|   YES -> skip: "Blocked by life event."
|
+-- contributionType = "regular"?
|   YES -> skip: "Bonds require minimum lump sum investment."
|
+-- tax_band NOT in [higher, additional]?
|   YES -> skip: "Offshore bonds most beneficial for higher/additional
|                  rate taxpayers."
|
+-- investment_experience in [none, beginner]?
|   YES -> skip: "Offshore bonds require intermediate or higher
|                  investment experience."
|
+-- surplus < 10,000?
    YES -> skip: "Minimum investment of {minInvestment} not met."

SEGMENT STRATEGY NOTE (NEW):
  allocate > 25,000?
    YES -> note: "Consider splitting into multiple segments (e.g. 20
                  segments of {segment_value} each). This allows partial
                  encashment of individual segments, preserving the
                  5% tax-deferred withdrawal allowance on remaining
                  segments."

ALLOCATION:
  allocate = MIN(surplus, suitable_amount)

RECOMMEND: [W6]
  headline: "Offshore Investment Bond"
  explanation: "Offshore bonds grow free of UK tax internally.
               Beneficial if you expect to be a basic rate taxpayer
               at encashment."
  personal_context: "As a {taxBand}-rate taxpayer earning {income},
                    an offshore bond grows tax-deferred. If your
                    tax band drops in retirement, you pay less
                    on encashment."
  priority: low
  amount: allocate

  note (age 55-70): "At your age, ensure a clear plan for bond
                     encashment aligned with retirement income needs."

  Reduce surplus by allocate
```

| # | Field | Value |
|---|-------|-------|
| W6 | headline | "Offshore Investment Bond" |
| W6 | explanation | "Offshore bonds grow free of UK tax internally. Beneficial if you expect to be a basic rate taxpayer at encashment." |
| W6 | personal_context | "As a {taxBand}-rate taxpayer earning {income}, an offshore bond grows tax-deferred. If your tax band drops in retirement, you pay less on encashment." |
| W6 | priority | `low` |
| W6 | note (age 55-70) | "At your age, ensure a clear plan for bond encashment aligned with retirement income needs." |
| W6 | note (segment strategy) | "Consider splitting into multiple segments. This allows partial encashment of individual segments, preserving the 5% tax-deferred withdrawal allowance on remaining segments." |

---

#### Step 7: Onshore Bond

```
SKIP CONDITIONS:
|
+-- "onshore_bond" in blockedWrappers?
|   YES -> skip: "Blocked by life event."
|
+-- contributionType = "regular"?
|   YES -> skip: "Bonds require minimum lump sum investment."
|
+-- tax_band NOT in [higher, additional]?
|   YES -> skip: "Onshore bonds most beneficial for higher/additional
|                  rate taxpayers."
|
+-- investment_experience in [none, beginner]?
|   YES -> skip: "Onshore bonds require intermediate or higher
|                  investment experience."
|
+-- surplus < 5,000?
    YES -> skip: "Minimum investment of {minInvestment} not met."

ALLOCATION:
  allocate = MIN(surplus, suitable_amount)

RECOMMEND: [W7]
  headline: "Onshore Investment Bond"
  explanation: "Onshore bonds benefit from top-slicing relief,
               spreading gains across years held to reduce the
               tax band impact."
  personal_context: "As a {taxBand}-rate taxpayer, the onshore bond's
                    20% internal tax credit means you only pay the
                    difference on encashment. Top-slicing relief may
                    further reduce the effective rate."
  priority: low
  amount: allocate
  note: "Top-slicing relief may reduce the effective tax rate
         on encashment."

  Reduce surplus by allocate
```

| # | Field | Value |
|---|-------|-------|
| W7 | headline | "Onshore Investment Bond" |
| W7 | explanation | "Onshore bonds benefit from top-slicing relief, spreading gains across years held to reduce the tax band impact." |
| W7 | personal_context | "As a {taxBand}-rate taxpayer, the onshore bond's 20% internal tax credit means you only pay the difference on encashment. Top-slicing relief may further reduce the effective rate." |
| W7 | priority | `low` |
| W7 | note | "Top-slicing relief may reduce the effective tax rate on encashment." |

---

#### Step 8: Pension Carry Forward

```
SKIP CONDITIONS:
|
+-- "pension" in blockedWrappers?
|   YES -> skip: "Pension blocked by life event."
|
+-- contributionType = "regular"?
|   YES -> skip: [W8a] "Pension carry forward is for lump sum
|                        contributions only."
|
+-- mpaa_triggered = true?
|   YES -> skip: "MPAA triggered -- carry forward not available for
|                  money purchase pensions."
|
+-- carry_forward_available <= 0?
    YES -> skip: "No carry forward available."

CARRY FORWARD CALCULATION:
  Available years (oldest first):
    year_minus_3: unused_aa_3_years_ago (if member of scheme)
    year_minus_2: unused_aa_2_years_ago (if member of scheme)
    year_minus_1: unused_aa_last_year (if member of scheme)

  total_carry_forward = SUM of available years
  allocate = MIN(surplus, total_carry_forward, relevant_uk_earnings - current_year_contributions)

TAX RELIEF:
  tax_relief = allocate * tax_relief_rate
  net_cost = allocate - tax_relief

RECOMMEND: [W8]
  headline: "Pension Carry Forward"
  explanation: "You have {amount} of unused pension allowance from
               previous years. Use oldest year first as it expires first."
  personal_context: "You have {carryForward} of unused pension allowance
                    from previous years. A {amount} carry forward
                    contribution at {rate}% tax relief costs just
                    {netCost} after relief."
  priority (additional): high
  priority (other): medium
  amount: allocate
  tax_relief: tax_relief
  note: "Use oldest carry forward year first (expires after 3 years)."

  Reduce surplus by net_cost
```

| # | Field | Value |
|---|-------|-------|
| W8 | headline | "Pension Carry Forward" |
| W8 | explanation | "You have {amount} of unused pension allowance from previous years. Use oldest year first as it expires first." |
| W8 | personal_context | "You have {carryForward} of unused pension allowance from previous years. A {amount} carry forward contribution at {rate}% tax relief costs just {netCost} after relief." |
| W8 | priority (additional) | `high` |
| W8 | priority (other) | `medium` |
| W8 | note | "Use oldest carry forward year first (expires after 3 years)." |
| W8a | skip reason | "Pension carry forward is for lump sum contributions only." |

---

#### Step 9: SEIS (Seed Enterprise Investment Scheme) — NEW (separated from VCT/EIS)

```
SKIP CONDITIONS:
|
+-- ALL of [vct, eis, seis] in blockedWrappers?
|   YES -> skip: "All venture capital schemes blocked by life event."
|
+-- contributionType = "regular"?
|   YES -> skip: "SEIS primarily suited for lump sum contributions."
|
+-- investment_experience NOT in [advanced, expert]?
|   YES -> skip: [W9a_exp] "SEIS requires advanced investment
|                            experience (your level: {level})."
|
+-- comfortable_with_capital_loss = false
|   OR comfortable_with_illiquidity = false?
|   YES -> skip: [W9a_comfort] "SEIS requires comfort with capital
|                                loss and illiquidity."
|
+-- disposable_percent < 10%?
    YES -> skip: [W9a_disposable] "Disposable income below 10% --
                                   insufficient buffer for high-risk
                                   investments."

SEIS LIMITS:
  annual_limit = 200,000
  seis_used_this_year = current year SEIS subscriptions
  seis_remaining = 200,000 - seis_used_this_year
  portfolio_cap = total_portfolio_value * 0.10 (max 10% in venture schemes)

ALLOCATION:
  max_venture = MIN(portfolio_cap - existing_venture_holdings, surplus)
  allocate = MIN(max_venture, seis_remaining)

  IF allocate < 1,000 -> skip: "Allocation too small for SEIS."

TAX RELIEF:
  income_tax_relief = allocate * 0.50 (50% income tax relief)
  cgt_reinvestment_relief (if applicable):
    gains reinvested into SEIS = 50% of gain exempt from CGT

RECOMMEND: [W9a]
  headline: "Seed Enterprise Investment Scheme"
  explanation: "SEIS offers 50% income tax relief on investments up to
               200,000 per year. Investments must be held for a minimum
               of 3 years to retain the relief."
  personal_context: "With a portfolio of {portfolioValue}, a {amount}
                    SEIS allocation represents {percent}% of your
                    portfolio. The 50% income tax relief of {taxRelief}
                    directly reduces your tax bill."
  priority: low
  amount: allocate
  tax_relief: income_tax_relief

  note 1: "Minimum 3-year holding period for tax relief retention."
  note 2: "SEIS companies must have fewer than 25 employees and
           under 350,000 in gross assets."
  note 3 (if has_realised_gains): "Capital gains reinvested into
           SEIS qualify for 50% CGT exemption (reinvestment relief)."
  note 4: "SEIS investments carry high risk of total capital loss.
           FSCS protection does not apply."

  Reduce surplus by allocate
  Reduce portfolio_cap_remaining by allocate
```

| # | Field | Value |
|---|-------|-------|
| W9a | headline | "Seed Enterprise Investment Scheme" |
| W9a | explanation | "SEIS offers 50% income tax relief on investments up to 200,000 per year. Investments must be held for a minimum of 3 years to retain the relief." |
| W9a | priority | `low` |
| W9a | note (CGT reinvestment) | "Capital gains reinvested into SEIS qualify for 50% CGT exemption (reinvestment relief)." |

---

#### Step 10: EIS (Enterprise Investment Scheme)

```
SKIP CONDITIONS:
  (Same as SEIS -- see Step 9 skip conditions)

EIS LIMITS:
  standard_annual_limit = 1,000,000
  knowledge_intensive_limit = 2,000,000 (for qualifying KIC companies)
  eis_used_this_year = current year EIS subscriptions
  eis_remaining = standard_annual_limit - eis_used_this_year

ALLOCATION:
  max_venture = portfolio_cap_remaining (after SEIS allocation)
  allocate = MIN(max_venture, eis_remaining, surplus)

  IF allocate < 1,000 -> skip: "Allocation too small for EIS."

TAX RELIEF:
  income_tax_relief = allocate * 0.30 (30% income tax relief)
  cgt_deferral: unlimited gains can be deferred by investing into EIS
  loss_relief: if EIS company fails, loss (net of income tax relief)
               can be offset against income or CGT

IHT BENEFIT:
  Shares held for 2+ years qualify for 100% Business Relief
  -> Shares are outside the estate for IHT purposes

RECOMMEND: [W9b]
  headline: "Enterprise Investment Scheme"
  explanation: "EIS offers 30% income tax relief on investments up to
               1,000,000 per year (2,000,000 for knowledge-intensive
               companies). Investments must be held for a minimum of
               3 years."
  personal_context: "A {amount} EIS investment provides {taxRelief}
                    income tax relief. After 2 years, EIS shares
                    qualify for 100% IHT Business Relief."
  priority: low
  amount: allocate
  tax_relief: income_tax_relief

  note 1: "Minimum 3-year holding period for income tax relief."
  note 2: "CGT deferral relief: defer unlimited capital gains by
           reinvesting into EIS."
  note 3: "Loss relief available if the company fails -- losses
           (net of 30% relief) can offset income or CGT."
  note 4: "100% IHT Business Relief after 2 years of holding."
  note 5: "EIS investments carry high risk of total capital loss."

  Reduce surplus by allocate
  Reduce portfolio_cap_remaining by allocate
```

| # | Field | Value |
|---|-------|-------|
| W9b | headline | "Enterprise Investment Scheme" |
| W9b | explanation | "EIS offers 30% income tax relief on investments up to 1,000,000 per year (2,000,000 for knowledge-intensive companies). Investments must be held for a minimum of 3 years." |
| W9b | priority | `low` |
| W9b | note (CGT deferral) | "CGT deferral relief: defer unlimited capital gains by reinvesting into EIS." |
| W9b | note (IHT) | "100% IHT Business Relief after 2 years of holding." |

---

#### Step 11: VCT (Venture Capital Trust)

```
SKIP CONDITIONS:
  (Same as SEIS -- see Step 9 skip conditions)

VCT LIMITS:
  annual_limit = 200,000
  vct_used_this_year = current year VCT subscriptions
  vct_remaining = 200,000 - vct_used_this_year

ALLOCATION:
  max_venture = portfolio_cap_remaining (after SEIS + EIS)
  allocate = MIN(max_venture, vct_remaining, surplus)

  IF allocate < 1,000 -> skip: "Allocation too small for VCT."

TAX RELIEF:
  Current (2025/26): income_tax_relief = allocate * 0.30
  From April 2026:   income_tax_relief = allocate * 0.20

TAX-FREE BENEFITS:
  Dividends from VCT shares are tax-free
  No CGT on disposal of VCT shares

IHT NOTE:
  VCTs are listed shares -> do NOT qualify for Business Relief
  (Unlike EIS, VCT shares remain in the estate for IHT)

RECOMMEND: [W9c]
  headline: "Venture Capital Trust"
  explanation: "VCT offers {vctReliefRate}% income tax relief on
               investments up to 200,000 per year. Tax-free dividends
               and no CGT on disposal. Minimum 5-year hold."
  personal_context: "A {amount} VCT investment provides {taxRelief}
                    income tax relief. Dividends are tax-free, and
                    there is no CGT on disposal."
  priority: low
  amount: allocate
  tax_relief: income_tax_relief

  note 1: "Minimum 5-year holding period for income tax relief
           retention."
  note 2: "Tax-free dividends -- no income tax on VCT distributions."
  note 3: "No CGT on disposal of VCT shares."
  note 4 (current year): "VCT income tax relief reduces from 30% to
           20% from April 2026."
  note 5: "VCTs do NOT qualify for IHT Business Relief (listed shares).
           Shares remain within the estate."
  note 6: "VCT investments carry high risk. Liquidity is limited --
           secondary market discounts are common."

  Reduce surplus by allocate
```

| # | Field | Value |
|---|-------|-------|
| W9c | headline | "Venture Capital Trust" |
| W9c | explanation | "VCT offers {vctReliefRate}% income tax relief on investments up to 200,000 per year. Tax-free dividends and no CGT on disposal. Minimum 5-year hold." |
| W9c | priority | `low` |
| W9c | note (relief change) | "VCT income tax relief reduces from 30% to 20% from April 2026." |
| W9c | note (no IHT BR) | "VCTs do NOT qualify for IHT Business Relief (listed shares). Shares remain within the estate." |

---

#### Step 12: GIA (Catch-All)

```
SKIP CONDITIONS:
|
+-- surplus <= 0?
    YES -> skip: "No remaining surplus."

GIA TAX STRATEGY NOTES:
|
+-- tax_band in [higher, additional]?
|   note 1: "Consider accumulation funds to minimise annual income
|            distributions."
|   note 2: "Use CGT annual exemption through phased disposals."
|
+-- Always:
    note 3: "No contribution limits or restrictions on GIA."

DIRECT GILTS NOTE (NEW):
  tax_band in [higher, additional] AND fixed_income_allocation_needed?
    note: "Consider holding direct gilts (UK government bonds) in GIA.
           Capital gains on gilts are CGT-exempt, making them
           tax-efficient for higher-rate taxpayers seeking fixed income.
           Only the coupon income is taxable."

ALLOCATION:
  allocate = surplus (everything remaining)

RECOMMEND: [W10]
  headline: "General Investment Account"
  explanation: "GIA has no contribution limits or restrictions. Use
               tax-efficient strategies to minimise annual tax drag."
  personal_context: "After filling tax-efficient wrappers, your
                    remaining {remaining} is invested in a GIA. As
                    a {taxBand}-rate taxpayer, use accumulation funds
                    and annual CGT exemptions to minimise tax."
  priority: low
  amount: allocate

  Reduce surplus to 0
```

| # | Field | Value |
|---|-------|-------|
| W10 | headline | "General Investment Account" |
| W10 | explanation | "GIA has no contribution limits or restrictions. Use tax-efficient strategies to minimise annual tax drag." |
| W10 | personal_context | "After filling tax-efficient wrappers, your remaining {remaining} is invested in a GIA. As a {taxBand}-rate taxpayer, use accumulation funds and annual CGT exemptions to minimise tax." |
| W10 | priority | `low` |
| W10 | note (accumulation) | "Consider accumulation funds to minimise annual income distributions." |
| W10 | note (CGT harvesting) | "Use CGT annual exemption through phased disposals." |
| W10 | note (direct gilts) | "Consider holding direct gilts in GIA. Capital gains on gilts are CGT-exempt, making them tax-efficient for higher-rate taxpayers seeking fixed income." |

### Life Event Priority Boost

Any waterfall recommendation matching a `prioritised_wrapper` from life event modifiers gets:
- Priority boosted one level: low -> medium, medium -> high, high -> critical (critical stays critical)
- Note added: "Priority raised due to an upcoming life event." [W_PRIORITY_BOOST]

```
For each recommendation in waterfall_output:
|
+-- recommendation.wrapper in life_event_modifiers.prioritised_wrappers?
    YES -> recommendation.priority = boost_one_level(recommendation.priority)
           recommendation.notes.push("Priority raised due to an upcoming life event.")
```

### SEIS vs EIS vs VCT Comparison

| Feature | SEIS | EIS | VCT |
|---------|------|-----|-----|
| Income tax relief | 50% | 30% | 30% (20% from April 2026) |
| Annual limit | 200,000 | 1,000,000 (2m KIC) | 200,000 |
| Minimum hold | 3 years | 3 years | 5 years |
| CGT on disposal | Exempt (if held 3+ years) | Exempt (if held 3+ years) | Exempt (always) |
| CGT deferral | 50% reinvestment relief | Unlimited deferral | None |
| Loss relief | Yes (net of 50% relief) | Yes (net of 30% relief) | No |
| Dividends | Taxable | Taxable | Tax-free |
| IHT Business Relief | After 2 years (100%) | After 2 years (100%) | No (listed shares) |
| Company size | <25 employees, <350k assets | <250 employees, <15m assets | Various (fund of companies) |
| Liquidity | Very low | Very low | Low (listed but thin market) |

---

## 8. Phase 5: Portfolio Health Check (NEW PHASE)

**Purpose:** Post-waterfall analysis of the user's EXISTING portfolio. The waterfall (Phase 4) tells you WHERE to put new money; this phase reviews what you ALREADY hold and identifies structural risks, inefficiencies, and drift.

**When it runs:** After Phase 4 (Contribution Waterfall) and before Phase 6 (Transfer & Optimisation Scans). It produces informational and warning recommendations that do not affect surplus calculations.

**Data required:** At least one investment account with holdings data. If no holdings data exists, the phase is skipped with an informational note.

```
PORTFOLIO HEALTH CHECK GATE
============================

+-- No investment accounts?
|   YES -> skip entire phase
|
+-- Investment accounts exist but no holdings data?
    YES -> INFO: [PH0] "Add your investment holdings to receive
                        portfolio health analysis including
                        concentration, drift, and fee analysis."
    SKIP remaining checks
```

### 8.1 Asset Class Concentration

```
ASSET CLASS CONCENTRATION
==========================

For each asset_class in portfolio:
|
+-- class_allocation > 60% of total portfolio?
    YES -> WARN: [PH1]
           headline: "Portfolio concentrated in {class}"
           explanation: "Your portfolio has {percent}% allocated to
                        {class}. Concentration above 60% in a single
                        asset class increases vulnerability to
                        sector-specific downturns."
           personal_context: "Your {class} holdings total {value},
                            representing {percent}% of your
                            {total_portfolio} portfolio."
           priority: medium
           note: "Consider diversifying across equities, bonds,
                  property, and alternatives."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| PH1 | Any asset class > 60% | `medium` | "Your portfolio has {percent}% allocated to {class}. Concentration above 60% in a single asset class increases vulnerability to sector-specific downturns." |

### 8.2 Geographic Concentration

```
GEOGRAPHIC CONCENTRATION
==========================

+-- uk_equity_allocation > 70% of total_equity_holdings?
    AND total_equity_holdings > 0?
    YES -> INFO: [PH2]
           headline: "Equity allocation heavily UK-weighted"
           explanation: "UK equities represent {percent}% of your
                        equity holdings. The UK is approximately 4%
                        of global market capitalisation. International
                        diversification can reduce country-specific risk."
           personal_context: "Your UK equity holdings total {uk_value}
                            out of {total_equity} total equities.
                            Consider global index funds to broaden
                            geographic exposure."
           priority: low
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| PH2 | UK > 70% of equity | `low` | "UK equities represent {percent}% of your equity holdings. The UK is approximately 4% of global market capitalisation. International diversification can reduce country-specific risk." |

### 8.3 Single Stock Concentration

```
SINGLE STOCK CONCENTRATION
============================

For each holding in portfolio:
|
+-- holding_value > 15% of total_portfolio?
    YES -> WARN: [PH3]
           headline: "Single holding concentration: {name}"
           explanation: "{name} represents {percent}% of your total
                        portfolio. Single stock risk means a significant
                        portion of your wealth depends on one company's
                        performance."
           personal_context: "Your holding of {name} is worth {value},
                            which is {percent}% of your {total}
                            portfolio. Consider whether this level of
                            concentration aligns with your risk profile."
           priority: medium
           note: "Diversified funds spread risk across hundreds
                  of companies."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| PH3 | Any single holding > 15% | `medium` | "{name} represents {percent}% of your total portfolio. Single stock risk means a significant portion of your wealth depends on one company's performance." |

### 8.4 Sector Concentration

```
SECTOR CONCENTRATION
=====================

For each sector in equity_holdings:
|
+-- sector_allocation > 30% of total_equity?
    YES -> INFO: [PH4]
           headline: "Sector concentration: {sector}"
           explanation: "{sector} represents {percent}% of your equity
                        holdings. High sector concentration amplifies
                        sector-specific risk."
           personal_context: "Your {sector} holdings total {value}
                            across {count} holdings, representing
                            {percent}% of equities."
           priority: low
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| PH4 | Any sector > 30% of equity | `low` | "{sector} represents {percent}% of your equity holdings. High sector concentration amplifies sector-specific risk." |

### 8.5 Rebalancing Check

```
REBALANCING CHECK
==================

[Check 1] Drift from target allocation
|
+-- Has target allocation been set?
|   NO -> Skip drift check (no target to compare against)
|   YES -> Calculate total_absolute_drift:
|          For each asset_class:
|            drift += ABS(actual_percent - target_percent)
|
|   +-- total_absolute_drift > 10%?
|   |   YES -> HIGH: [PH5a]
|   |          headline: "Significant portfolio drift from target"
|   |          explanation: "Your portfolio has drifted significantly
|   |                       from your target allocation. {class} is
|   |                       {actual}% vs {target}% target."
|   |          priority: high
|   |          note: "Risk profile may no longer match portfolio.
|   |                 Review and rebalance."
|   |
|   +-- total_absolute_drift > 5%?
|       YES -> MEDIUM: [PH5b]
|              headline: "Portfolio drifted from target allocation"
|              explanation: "Your portfolio has drifted from target.
|                           {class} is {actual}% vs {target}%."
|              priority: medium
|              note: "Consider rebalancing to maintain your
|                     target risk profile."

[Check 2] Last rebalance date
|
+-- last_rebalance_date > 12 months ago OR never rebalanced?
    YES -> INFO: [PH5c]
           headline: "Portfolio not rebalanced recently"
           explanation: "Your portfolio has not been rebalanced in
                        over 12 months. Regular rebalancing maintains
                        your target risk profile and can improve
                        long-term returns."
           priority: low
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| PH5a | Total drift > 10% | `high` | "Your portfolio has drifted significantly from your target allocation. Risk profile may no longer match portfolio." |
| PH5b | Total drift > 5% | `medium` | "Your portfolio has drifted from target. Consider rebalancing to maintain your target risk profile." |
| PH5c | Last rebalance > 12 months | `low` | "Your portfolio has not been rebalanced in over 12 months." |

### 8.6 Fee Drag Analysis

```
FEE DRAG ANALYSIS
==================

For each holding in portfolio:
|
+-- holding.ocf > 0.75%?
|   AND passive_equivalent_exists for same asset_class?
|   |
|   +-- ocf_difference > 0.50% (holding.ocf - passive_equivalent.ocf)?
|   |   YES -> MEDIUM: [PH6a]
|   |          headline: "High charges on {name}"
|   |          explanation: "Your holding {name} charges {ocf}% per
|   |                       year. A passive alternative in the same
|   |                       asset class charges approximately
|   |                       {passive_ocf}%."
|   |          personal_context: "Over {years} years, the {diff}%
|   |                           annual difference on {value} costs
|   |                           an estimated {impact} in lost returns."
|   |          priority: medium
|   |
|   +-- ocf_difference <= 0.50%?
|       YES -> LOW: [PH6b]
|              headline: "Charges note: {name}"
|              explanation: "Your holding {name} charges {ocf}%.
|                           A passive equivalent charges {passive_ocf}%.
|                           The difference is modest."
|              priority: info

FEE IMPACT CALCULATION:
  impact = value * ((1 + growth - holding_ocf)^years - (1 + growth - passive_ocf)^years)
  Where growth = assumed portfolio return (e.g. 5%)
  Where years = years_to_retirement or 20 (whichever is shorter)
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| PH6a | OCF > 0.75% AND passive alternative saves > 0.50% | `medium` | "Your holding {name} charges {ocf}%. A passive alternative charges approximately {passive_ocf}%. Over {years} years, the difference costs an estimated {impact}." |
| PH6b | OCF > 0.75% AND passive alternative saves <= 0.50% | `info` | "Charges note: your holding {name} charges {ocf}%. A passive equivalent charges {passive_ocf}%." |

### 8.7 Fund Type Analysis (ETF vs Active)

```
FUND TYPE ANALYSIS
===================

+-- portfolio_weighted_average_ocf > 0.50%?
    YES -> INFO: [PH7]
           headline: "Portfolio average charges above index tracker levels"
           explanation: "Your portfolio's weighted average ongoing charge
                        is {avg_ocf}%. Index tracker funds typically
                        charge 0.10-0.25%. Switching some holdings to
                        passive alternatives could reduce costs."
           personal_context: "On your {total_portfolio} portfolio,
                            reducing average charges from {avg_ocf}%
                            to {target_ocf}% would save approximately
                            {annual_saving} per year."
           priority: low
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| PH7 | Portfolio average OCF > 0.50% | `low` | "Your portfolio's weighted average ongoing charge is {avg_ocf}%. Index tracker funds typically charge 0.10-0.25%." |

### 8.8 AIM Share IHT Review

```
AIM SHARE IHT REVIEW
======================

+-- User holds AIM-listed shares?
|   AND aim_holdings are held for IHT planning (or estate > NRB)?
    |
    +-- Current tax year (2025/26)?
    |   YES -> WARN: [PH8]
    |          headline: "AIM share Business Relief changing"
    |          explanation: "AIM shares currently qualify for 100%
    |                       Business Relief, making them IHT-exempt.
    |                       From April 2026, Business Relief on AIM
    |                       shares reduces to 50%, meaning an
    |                       effective IHT rate of 20% will apply."
    |          personal_context: "Your AIM holdings of {aim_value}
    |                           are currently 100% IHT-exempt. From
    |                           April 2026, 50% ({half_value}) will
    |                           be subject to IHT at 40%, costing
    |                           {iht_cost} in additional IHT."
    |          priority: high
    |          note: "Consider EIS as an alternative -- EIS retains
    |                 100% Business Relief with no reduction planned."
    |          note: "Review whether AIM shares remain appropriate
    |                 for your IHT strategy given the reduced relief."
    |
    +-- From April 2026 onwards?
        YES -> (Same message but in present tense)
               "AIM shares qualify for 50% Business Relief, meaning
                an effective IHT rate of 20% applies."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| PH8 | AIM shares held AND estate > NRB | `high` (pre-April 2026), `medium` (after) | "AIM shares Business Relief reduces from 100% to 50% from April 2026. Effective IHT rate becomes 20%." |

---

## 9. Phase 6: Transfer & Optimisation Scans

**Service:** `TransferRecommendationService` | **File:** `app/Services/Investment/Recommendation/TransferRecommendationService.php`

**Supporting:** `CashAccountAnalyzer` | **File:** `app/Services/Investment/Recommendation/CashAccountAnalyzer.php`

Transfer scans examine existing holdings and identify optimisation opportunities. These run independently of the contribution waterfall. The v1.0 engine expands from 7 scans to 13 scans.

### Scan 1: Bed & ISA (GIA to ISA Shelter)

```
BED & ISA SCAN
===============

GATE:
+-- Any GIA account with balance > 0?
|   NO -> skip scan
|
+-- isa_remaining > 0?
    NO -> skip scan: "No ISA allowance remaining for Bed & ISA."

CALCULATION:
  For each GIA account:
    transfer_amount = MIN(account.balance, isa_remaining)
    unrealised_gains = account.current_value - account.cost_basis

CGT ANALYSIS:
|
+-- unrealised_gains <= 0 (net loss)?
|   CGT note: [T1a] "No CGT liability as the holding is at a loss.
|                     Losses can be carried forward to offset future
|                     gains."
|   CGT cost: 0
|   Priority: high (no tax friction)
|
+-- unrealised_gains > 0 AND <= cgt_allowance_remaining?
|   CGT note: [T1b] "Gains of {gains} are within your remaining
|                     {cgt_remaining} annual exemption -- no CGT
|                     payable."
|   CGT cost: 0
|   Priority: high (no tax friction)
|
+-- unrealised_gains > cgt_allowance_remaining?
    taxable_gain = unrealised_gains - cgt_allowance_remaining
    cgt_rate = basic_rate ? 18% : 24%
    cgt_cost = taxable_gain * cgt_rate
    CGT note: [T1c] "Gains of {gains} exceed the annual exemption
                      by {excess}. Estimated CGT: {cgt_cost}."
    Priority: medium (has tax friction)

    Net benefit check:
      future_tax_saved = transfer_amount * assumed_growth * years * tax_rate
      IF future_tax_saved > cgt_cost -> "Net benefit positive over {years} years."
      ELSE -> "Consider phasing the transfer over multiple tax years
               to use annual CGT exemptions."

BED & ISA PROCESS STEPS:
  1. Sell holdings in GIA
  2. Subscribe to ISA (uses current year allowance)
  3. Buy same or similar holdings within ISA
  Note: "The 30-day rule (Bed & Breakfast) does NOT apply when
         repurchasing within a different wrapper (ISA). You can buy
         the same fund immediately."

RECOMMEND: [T1]
  headline: "Bed & ISA -- shelter GIA holdings"
  explanation: "Transfer {amount} from GIA to ISA to shelter future
               growth from tax. {CGT note}"
  personal_context: "Your GIA holds {balance} with {gains} unrealised
                    gains. {CGT context}. You have {isaRemaining} of
                    ISA allowance to shelter this transfer."
  priority: (see CGT analysis above)
  amount: transfer_amount
```

| # | Scan | Headline | Priority |
|---|------|----------|----------|
| T1 | Bed & ISA | "Bed & ISA -- shelter GIA holdings" | No CGT: `high`; Has CGT: `medium` |
| T1a | Bed & ISA (loss) | *(appended)* | "No CGT liability as the holding is at a loss. Losses can be carried forward." |
| T1b | Bed & ISA (within) | *(appended)* | "Gains within the annual exemption -- no CGT payable." |
| T1c | Bed & ISA (exceeds) | *(appended)* | "Gains exceed the annual exemption. Estimated CGT: {cgt}." |

### Scan 2: Tax Loss Harvesting (NEW)

```
TAX LOSS HARVESTING
====================

GATE:
+-- Any GIA holding with unrealised_loss > 500?
    NO -> skip scan

For each qualifying holding:
|
+-- unrealised_loss > 500?
|   AND (realised_gains_ytd > 0 OR tax_band in [higher, additional])?
    |
    +-- YES -> Calculate:
    |          loss_value = ABS(unrealised_loss)
    |          tax_saved = MIN(loss_value, realised_gains_ytd) * cgt_rate
    |
    |          IF remaining losses after offsetting gains:
    |            carry_forward = loss_value - realised_gains_ytd
    |            note: "Remaining losses of {carry_forward} can be
    |                   carried forward indefinitely."
    |
    +-- TIMING CHECK:
        months_to_april_5 <= 3?
          YES -> priority: high
                 note: "Tax year ends 5 April. Crystallise losses
                        before year end to offset this year's gains."
          NO  -> priority: medium

30-DAY RULE WARNING:
  note: "Do not repurchase the same shares or securities within
         30 days (Bed & Breakfast rule). You may:
         (a) Buy the same holding within an ISA immediately
         (b) Buy a similar-but-different fund in the GIA
             (e.g. switch from one FTSE 100 tracker to another
              provider's FTSE 100 tracker)"

RECOMMEND: [T2]
  headline: "Tax loss harvesting opportunity"
  explanation: "Your GIA holding {name} has an unrealised loss of
               {loss}. Crystallising this loss can offset capital
               gains and reduce your tax bill."
  personal_context: "Selling {name} crystallises a {loss} loss,
                    which offsets {gains_offset} of realised gains
                    this year, saving approximately {tax_saved}
                    in CGT."
  priority: (see timing check above)
  amount: loss_value
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T2 | GIA loss > 500 AND gains/higher rate | `high` (near year end), `medium` (otherwise) | "Your GIA holding {name} has an unrealised loss of {loss}. Crystallising this loss can offset capital gains." |

### Scan 3: PSA Breach

```
PSA BREACH SCAN
================

CALCULATION:
  effective_psa = personal_savings_allowance + starting_rate_for_savings

  Personal Savings Allowance:
    basic rate: 1,000
    higher rate: 500
    additional rate: 0

  Starting Rate for Savings (if non-savings income < 17,570):
    band = MAX(0, 5,000 - MAX(0, non_savings_income - 12,570))

+-- annual_interest > effective_psa?
    YES -> breach = annual_interest - effective_psa
           tax_cost = breach * marginal_savings_rate

RECOMMEND: [T3]
  headline: "Interest income exceeds tax-free allowance"
  explanation: "Your annual interest of {annualInterest} exceeds your
               tax-free allowance of {effectivePSA} by {breach}.
               Consider moving savings to tax-free wrappers."
  personal_context: "Your savings earn {annualInterest}/year in
                    interest against a {effectivePSA} personal
                    savings allowance ({taxBand}-rate). The {breach}
                    excess is taxed at your marginal rate, costing
                    approximately {taxCost}."
  priority: high
  note: "ISA and Premium Bonds shelter interest from tax."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T3 | Interest > effective PSA | `high` | "Your annual interest exceeds your tax-free allowance by {breach}. Consider moving savings to tax-free wrappers." |

### Scan 4: Dividend Allowance Breach

```
DIVIDEND ALLOWANCE BREACH
===========================

+-- annual_dividends > dividend_allowance (500)?
    YES -> breach = annual_dividends - 500
           tax_rate = basic ? 8.75% : higher ? 33.75% : 39.35%
           tax_cost = breach * tax_rate

RECOMMEND: [T4]
  headline: "Dividend income exceeds tax-free allowance"
  explanation: "Annual dividends of {annualDividends} exceed the
               {allowance} allowance by {breach}. Prioritise moving
               highest-yielding GIA holdings into ISA."
  personal_context: "Your dividend income of {annualDividends} exceeds
                    the {dividendAllowance} allowance by {breach},
                    costing approximately {taxCost} in dividend tax
                    at the {taxBand} rate."
  priority: medium
  note: "Prioritise transferring the highest-yielding holdings first."
  note: "Accumulation funds reinvest dividends but are still taxable
         in a GIA -- the tax liability is on the notional distribution."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T4 | Dividends > 500 | `medium` | "Annual dividends exceed the {allowance} allowance. Prioritise moving highest-yielding GIA holdings into ISA." |

### Scan 5: Bed & Pension (NEW)

```
BED & PENSION SCAN
====================

GATE:
+-- pension_aa_remaining > 0?
|   AND gia_total_value > 0?
|   AND tax_band in [higher, additional]?
|   AND isa_remaining < 5,000 (ISA near-fully used)?
    NO -> skip scan

CALCULATION:
  sell_amount = MIN(gia_total_value, pension_aa_remaining, relevant_uk_earnings_remaining)

  unrealised_gains on GIA holdings to sell:
    cgt_cost = taxable_gain * cgt_rate (after applying annual exemption)

  tax_relief = sell_amount * tax_relief_rate
  net_benefit = tax_relief - cgt_cost

+-- net_benefit > 0?
    YES -> RECOMMEND: [T5]
           headline: "Bed & Pension -- sell GIA, contribute to pension"
           explanation: "Sell GIA holdings and contribute the proceeds
                        to your pension. The {rate}% tax relief of
                        {tax_relief} outweighs the CGT cost of
                        {cgt_cost}, giving a net benefit of
                        {net_benefit}."
           personal_context: "With your ISA nearly full, your pension
                            offers the next best tax shelter. Selling
                            {sell_amount} from your GIA and contributing
                            to your pension saves {net_benefit} net."
           priority: medium
           note: "Pension contributions are locked until age
                  {pension_access_age}."
           note: "Consider whether you need the GIA funds before
                  pension access age."
    NO  -> Skip: "CGT cost exceeds pension tax relief benefit."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T5 | GIA value > 0, pension AA remaining, higher/additional rate, ISA near-full, net benefit > 0 | `medium` | "Sell GIA holdings and contribute the proceeds to your pension. Tax relief outweighs CGT cost." |

### Scan 6: GIA to Pension via Carry Forward (NEW)

```
GIA TO PENSION VIA CARRY FORWARD
==================================

GATE:
+-- carry_forward_available > 0?
|   AND gia_total_value > 5,000?
|   AND tax_band in [higher, additional]?
    NO -> skip scan

CALCULATION:
  sell_amount = MIN(gia_total_value, carry_forward_available, relevant_uk_earnings)
  tax_relief = sell_amount * tax_relief_rate
  cgt_cost = estimated CGT on GIA disposal (apply annual exemption first)
  net_benefit = tax_relief - cgt_cost

STRATEGY:
  Use CGT annual exemption to sell GIA tax-free where possible
  Use oldest carry forward year first (FIFO)

+-- net_benefit > 0?
    YES -> RECOMMEND: [T6]
           headline: "Use pension carry forward with GIA proceeds"
           explanation: "You have {carry_forward} of unused pension
                        allowance from previous years. Selling GIA
                        holdings within your CGT exemption and
                        contributing to your pension provides
                        {rate}% tax relief."
           personal_context: "Selling {sell_amount} from your GIA
                            (within the {cgt_exemption} annual CGT
                            exemption) and contributing to your
                            pension via carry forward saves
                            {net_benefit}."
           priority: high
           note: "Use oldest carry forward year first -- it expires
                  first."
           note: "Carry forward requires membership of a pension
                  scheme in the relevant years."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T6 | Carry forward available, GIA > 5k, higher/additional rate | `high` | "Use pension carry forward with GIA proceeds. Selling GIA holdings within CGT exemption and contributing to pension." |

### Scan 7: ISA Consolidation (NEW)

```
ISA CONSOLIDATION SCAN
========================

+-- Count of ISA accounts > 2?
|   AND ISA accounts spread across > 1 provider?
    YES -> RECOMMEND: [T7]
           headline: "Consolidate ISA accounts"
           explanation: "You have {count} ISA accounts across
                        {provider_count} providers. Consolidating
                        via ISA-to-ISA transfer simplifies management
                        and may reduce platform fees."
           personal_context: "Your ISAs are held with {providers}.
                            Transferring to a single provider via
                            ISA-to-ISA transfer preserves your
                            tax-free status."
           priority: info
           note: "Previous year ISA subscriptions can be partially
                  transferred."
           note: "Current year subscriptions must be transferred
                  in full or not at all."
           note: "Check exit fees before transferring."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T7 | > 2 ISAs across > 1 provider | `info` | "Consolidate ISA accounts via ISA-to-ISA transfer to simplify management and reduce fees." |

### Scan 8: Investment Account Consolidation (NEW)

```
INVESTMENT ACCOUNT CONSOLIDATION
==================================

[Check A] Multiple non-workplace pensions
|
+-- Count of non-workplace pensions (SIPP, personal pension) > 2?
    YES -> INFO: [T8a]
           headline: "Consolidate pension accounts"
           explanation: "You have {count} non-workplace pensions.
                        Consider consolidating into a single SIPP
                        to simplify management, reduce fees, and
                        improve oversight."
           priority: info
           note: "Check for exit fees, guaranteed annuity rates,
                  or protected tax-free cash before transferring."
           note: "Do NOT transfer final salary (defined benefit)
                  pensions without independent financial advice."

[Check B] Small balance accounts
|
+-- Any investment account with balance < 1,000
|   AND account is NOT goal-linked?
    YES -> INFO: [T8b]
           headline: "Small balance account: {name}"
           explanation: "Your account {name} has a balance of
                        {balance}. Small balances may be eroded
                        by platform fees and charges."
           priority: info
           note: "Consider consolidating into a larger account
                  or closing if no longer needed."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T8a | > 2 non-workplace pensions | `info` | "Consider consolidating into a single SIPP to simplify management and reduce fees." |
| T8b | Account balance < 1,000 and not goal-linked | `info` | "Small balance may be eroded by platform fees." |

### Scan 9: Platform Consolidation (NEW)

```
PLATFORM CONSOLIDATION
========================

[Check A] Platform count
|
+-- Total investment platforms > 3?
    YES -> INFO: [T9a]
           headline: "Multiple investment platforms"
           explanation: "You hold investments across {count} platforms.
                        Consolidating reduces complexity, simplifies
                        tax reporting, and may reduce total fees."
           priority: info

[Check B] Platform fee analysis
|
+-- Any platform charging > 0.45% on portfolio > 50,000?
    YES -> MEDIUM: [T9b]
           flat_fee_equivalent = 50,000 * 0.0045 = 225 (example)
           potential_saving = (platform_fee_rate * portfolio_value) - flat_fee_annual_cost

           headline: "Platform fee review: {platform}"
           explanation: "Your {platform} charges {rate}% on
                        {portfolio_value}. A flat-fee platform
                        could save approximately {saving}/year
                        on this portfolio size."
           personal_context: "On your {portfolio_value} portfolio
                            with {platform}, you pay approximately
                            {annual_fee}. A flat-fee platform
                            typically charges {flat_fee}, saving
                            {saving} per year."
           priority: medium
           note: "Flat-fee platforms (e.g. interactive investor,
                  AJ Bell) are typically more cost-effective for
                  portfolios above 50,000."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T9a | > 3 platforms | `info` | "Consolidating reduces complexity, simplifies tax reporting, and may reduce total fees." |
| T9b | Platform > 0.45% on > 50,000 | `medium` | "A flat-fee platform could save approximately {saving}/year." |

### Scan 10: Death Benefit Nominations (NEW)

```
DEATH BENEFIT NOMINATIONS
===========================

[Check A] Missing or outdated nominations
|
+-- Any pension account?
|   AND (no nomination_date recorded OR nomination_date > 2 years ago)?
    YES -> INFO: [T10a]
           headline: "Review pension death benefit nominations"
           explanation: "Pension death benefit nominations are
                        typically non-binding expressions of wish.
                        Trustees have discretion but usually follow
                        nominations. Regular review ensures your
                        wishes are current."
           personal_context: "Your pension {name} {has no recorded
                            nomination / was last nominated on
                            {date}, over 2 years ago}."
           priority: info (default)

[Check B] Life event urgency
|
+-- divorce OR death_of_partner in active life events?
    YES -> priority elevated to HIGH: [T10b]
           headline: "Review pension nominations urgently"
           explanation: "Following your recent {event}, your pension
                        death benefit nominations should be reviewed
                        urgently to ensure they reflect your
                        current wishes."
           priority: high
           note: "Former spouses are not automatically removed
                  from pension nominations after divorce."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T10a | Pension with no/old nomination | `info` | "Pension death benefit nominations should be reviewed regularly." |
| T10b | Divorce/death + pension | `high` | "Review pension death benefit nominations urgently following {event}." |

### Scan 11: REIT Wrapper Check (NEW)

```
REIT WRAPPER CHECK
====================

+-- Any REIT holdings outside ISA/SIPP?
|   (identified by holding.asset_class = 'property' AND
|    holding.type = 'reit' AND wrapper NOT IN [isa, sipp])
    YES -> INFO: [T11]
           headline: "REIT holdings in taxable account"
           explanation: "Property Income Distributions from REITs
                        are taxed as property income (not as
                        dividends). The dividend allowance does NOT
                        apply to REIT distributions. Holding REITs
                        within an ISA or SIPP eliminates this tax."
           personal_context: "Your REIT holdings of {value} in your
                            GIA generate approximately {pid_income}
                            in Property Income Distributions, taxed
                            at your {rate}% marginal rate."
           priority: info
           note: "ISA-to-ISA transfer or Bed & ISA can move
                  existing REIT holdings into tax-free wrapper."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T11 | REIT holdings outside ISA/SIPP | `info` | "REIT Property Income Distributions taxed as property income. Dividend allowance does not apply. Prioritise holding REITs within ISA or SIPP." |

### Scan 12: Interest Rate Review

```
INTEREST RATE REVIEW
=====================

For each savings/cash account (excluding emergency fund, fixed-term,
goal-linked):
|
+-- bestAvailableRate - currentRate >= 0.5%?
    YES -> [T12]

RATE EXPIRED VARIANT:
+-- promotional_rate_end_date in the past?
    YES -> priority: high
           headline: "Promotional rate expired"
    NO  -> priority: medium
           headline: "Better rate available"

RECOMMEND: [T12]
  explanation: "Your {account} earns {current}%. A comparable account
               offers {best}% -- switching could earn an extra
               {gain} per year."
  personal_context: "Your {accountName} earns {currentRate}% on
                    {balance}. Switching to the best available rate
                    of {bestRate}% would earn an additional
                    {annualGain} per year."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T12 | Rate difference >= 0.5% | `high` (expired) / `medium` (other) | "Switching could earn an extra {gain} per year." |

### Scan 13: Goal-Linked Optimisation

```
GOAL-LINKED OPTIMISATION
==========================

For each active goal with a linked account:
|
+-- account.wrapper NOT in goal.suitable_wrappers?
    YES -> RECOMMEND: [T13]
           headline: "Better wrapper for \"{goalName}\" goal"
           explanation: "Your goal \"{goal}\" is linked to a
                        {current} wrapper. Consider moving to a
                        {better} for better tax efficiency."
           personal_context: "Your \"{goalName}\" goal is held in a
                            {currentWrapper}, but a {betterWrapper}
                            would be more tax-efficient for this
                            timeline and purpose."
           priority: low
           note: "Ensure goal linkage is preserved when transferring."
```

| # | Condition | Severity | Message |
|---|-----------|----------|---------|
| T13 | Goal linked to suboptimal wrapper | `low` | "Consider moving to a {better} wrapper for better tax efficiency." |

### Transfer Pre-Transfer Checks & Notes Summary

| Scan | Checks / Notes |
|------|---------------|
| T1 (Bed & ISA) | "Confirm ISA subscription status with provider." / "Bed & Breakfast 30-day rule does NOT apply to Bed & ISA." |
| T2 (Tax Loss) | "Do not repurchase same shares within 30 days." / "Can buy via ISA immediately." |
| T3 (PSA) | "ISA and Premium Bonds shelter interest from tax." |
| T4 (Dividend) | "Prioritise transferring highest-yielding holdings first." / "Accumulation funds still taxable in GIA." |
| T5 (Bed & Pension) | "Pension contributions locked until access age." / "Consider whether GIA funds needed before pension." |
| T6 (Carry Forward) | "Use oldest year first." / "Requires scheme membership in relevant years." |
| T7 (ISA Consolidation) | "Previous years: partial transfer OK." / "Current year: full or nothing." / "Check exit fees." |
| T8 (Account Consolidation) | "Check for GAR, protected TFC." / "Never transfer DB pensions without advice." |
| T9 (Platform) | "Flat-fee platforms better above 50,000." |
| T10 (Nominations) | "Former spouses not auto-removed after divorce." |
| T11 (REIT) | "Use Bed & ISA to move REIT holdings." |
| T12 (Rate Review) | "Confirm emergency fund remains at target." / "Check notice period." |
| T13 (Goal Wrapper) | "Ensure goal linkage preserved." |

---

## 10. Phase 7: Spouse Optimisation

**Service:** `SpouseOptimisationService` | **File:** `app/Services/Investment/Recommendation/SpouseOptimisationService.php`

Spouse optimisation only runs if the user is married/civil_partnership AND has a linked spouse account. The v1.0 engine expands from 6 to 8 independent strategies.

### Gate Conditions

```
SPOUSE OPTIMISATION GATE
==========================

+-- No spouse linked?
|   YES -> GATE: [SP0a] "No linked spouse -- spouse optimisation
|                        not available."
|   STOP
|
+-- marital_status NOT IN [married, civil_partnership]?
|   YES -> GATE: [SP0b] "Not married or in civil partnership --
|                        spouse optimisation not available."
|   STOP
|
+-- Any life event modifier has blocks_spouse_optimisation = true?
    YES -> All strategies skipped.
           note: "Spouse optimisation suspended due to {event_type}."
    STOP
```

### Strategy 1: CGT Allowance Sharing

```
CGT ALLOWANCE SHARING
======================

+-- user_total_gia_unrealised_gains > cgt_annual_exemption (3,000)?
    NO -> skip strategy
    YES ->
         |
         +-- spouse in different tax band?
         |   YES -> note: "Your spouse is a {band} rate taxpayer.
         |                  Transferring gains could reduce the CGT rate
         |                  from {user_rate}% to {spouse_rate}%."
         |
         +-- Always:
             note: "Interspousal transfers are CGT-exempt. Spouse then
                    crystallises at their own rates."

CALCULATION:
  transferable_gains = user_unrealised_gains - cgt_annual_exemption
  spouse_exemption_available = spouse_cgt_allowance_remaining
  optimal_transfer = MIN(transferable_gains, spouse_exemption_available)
  tax_saved = optimal_transfer * user_cgt_rate

  IF spouse in lower band:
    additional_saving = optimal_transfer * (user_cgt_rate - spouse_cgt_rate)
    note: "Transferring to your spouse saves an additional
           {additional_saving} due to their lower CGT rate."

RECOMMEND: [SP1]
  headline: "Share CGT allowance with spouse"
  explanation: "Transfer holdings with gains to your spouse to use
               their {allowance} annual CGT exemption."
  personal_context: "Your GIA holdings have {gains} in unrealised
                    gains, exceeding your {exemption} annual exemption.
                    Transferring {optimal_transfer} to {spouseName}
                    saves approximately {tax_saved} in CGT."
  priority: medium
  amount: optimal_transfer
  estimated_annual_benefit: tax_saved
```

| # | Strategy | Headline | Priority |
|---|----------|----------|----------|
| SP1 | CGT sharing | "Share CGT allowance with spouse" | `medium` |

### Strategy 2: ISA Coordination

```
ISA COORDINATION
=================

+-- One partner's ISA allowance exhausted AND other has remaining?
    NO -> skip strategy
    YES ->
         combined_remaining = user_isa_remaining + spouse_isa_remaining
         shortfall_partner = partner whose ISA is exhausted
         available_partner = partner with remaining allowance

RECOMMEND: [SP2]
  headline: "Maximise household ISA allowance"
  explanation: "Your household has {remaining} of ISA allowance
               remaining. Gift money to your spouse to contribute
               to their ISA."
  personal_context: "{shortfall_partner} has used their full ISA
                    allowance. {available_partner} has {remaining}
                    remaining. Gifting money for ISA contribution
                    shelters an additional {remaining} from tax."
  priority: medium
  note: "You cannot contribute directly to your spouse's ISA --
         gift the money for them to contribute."
  note: "Combined household ISA capacity: {householdCapacity}
         per year."
```

| # | Strategy | Headline | Priority |
|---|----------|----------|----------|
| SP2 | ISA coordination | "Maximise household ISA allowance" | `medium` |

### Strategy 3: PSA Optimisation

```
PSA OPTIMISATION
=================

+-- Partners in different tax bands?
    NO -> skip strategy
    YES ->
         lower_rate_partner = partner with lower tax band
         higher_rate_partner = partner with higher tax band
         lower_psa = PSA for lower_rate_partner
         higher_psa = PSA for higher_rate_partner
         psa_difference = lower_psa - higher_psa

RECOMMEND: [SP3]
  headline: "Shift savings to lower-rate spouse"
  explanation: "{spouseName} has a {spousePSA} Personal Savings
               Allowance vs your {userPSA}. Consider holding
               interest-bearing accounts in the name of the
               lower-rate partner."
  personal_context: "You earn {userIncome} ({userTaxBand} rate,
                    {userPSA} Personal Savings Allowance) and
                    {spouseName} earns {spouseIncome} ({spouseTaxBand}
                    rate, {spousePSA} Personal Savings Allowance).
                    Holding savings in {partner's} name shelters up
                    to {difference} more interest from tax."
  priority: medium
  note: "Transferring savings between spouses may have IHT
         implications if estate exceeds the nil-rate band."
```

| # | Strategy | Headline | Priority |
|---|----------|----------|----------|
| SP3 | PSA optimisation | "Shift savings to lower-rate spouse" | `medium` |

### Strategy 4: Pension Coordination

```
PENSION COORDINATION
=====================

[Strategy 4a] Higher-Rate Pension Prioritisation
|
+-- Partners in different tax bands?
    YES ->
      higher_rate_partner = partner with higher marginal rate
      higher_relief = higher marginal rate

      RECOMMEND: [SP4a]
        headline: "Prioritise pension for higher-rate partner"
        explanation: "Pension contributions for {partner} ({band}
                     rate) receive higher tax relief. Prioritise
                     maximising their pension allowance first."
        personal_context (user higher): "You earn {income} ({band}
                     rate) and receive {rate} tax relief on pension
                     contributions. Maximise your pension allowance
                     first for the highest relief."
        personal_context (spouse higher): "{spouseName} earns
                     {income} ({band} rate) and receives {rate}
                     tax relief on pension contributions. Maximise
                     their pension allowance first for the highest
                     relief."
        priority: high
        note: "Pensions are outside the estate for IHT, adding
               a further benefit."
        note (if April 2027+ aware): "Note: pensions will be
               brought into IHT from April 2027."

[Strategy 4b] Non-Earning Spouse Pension
|
+-- spouse_gross_annual_income <= 0?
    YES ->
      RECOMMEND: [SP4b]
        headline: "Non-earning spouse pension contribution"
        explanation: "Your spouse can receive up to 3,600 gross
                     pension contributions per year even with no
                     earnings. The government adds 20% basic rate
                     relief (720)."
        personal_context: "{spouseName} has no earnings but can
                         still receive 3,600 gross in pension
                         contributions. You contribute 2,880 net
                         and the government adds 720."
        priority: medium

[Strategy 4c] Spouse Carry Forward
|
+-- spouse_carry_forward_available > 0?
    YES ->
      RECOMMEND: [SP4c]
        headline: "Use spouse's pension carry forward"
        explanation: "Your spouse has {amount} in unused pension
                     carry forward from previous years."
        personal_context: "Your spouse has {carryForward} of unused
                         pension allowance. Use oldest year first --
                         carry forward expires after 3 years."
        priority: medium
        note: "Use oldest year first -- carry forward expires
               after 3 years."
```

| # | Strategy | Headline | Priority |
|---|----------|----------|----------|
| SP4a | Pension (higher rate) | "Prioritise pension for higher-rate partner" | `high` |
| SP4b | Pension (non-earner) | "Non-earning spouse pension contribution" | `medium` |
| SP4c | Pension (carry forward) | "Use spouse's pension carry forward" | `medium` |

### Strategy 5: Marriage Allowance

```
MARRIAGE ALLOWANCE
===================

ELIGIBILITY CHECK:
|
+-- Both partners earn above personal_allowance (12,570)?
|   YES -> skip: "Both partners earn above the personal allowance."
|
+-- Higher earner is higher/additional rate (income > 50,270)?
|   YES -> skip: [SP5a] "Higher earner is above basic rate --
|                         Marriage Allowance not available."
|
+-- One partner earns below personal_allowance
|   AND the other is basic rate?
    YES -> eligible

CALCULATION:
  transfer_amount = 1,257 (10% of personal allowance)
  tax_saving = transfer_amount * 0.20 = 252 (approx)

RECOMMEND: [SP5]
  headline: "Claim Marriage Allowance"
  explanation: "Transfer {amount} of unused personal allowance to
               your basic rate partner, saving up to {saving}
               per year."
  personal_context: "{lowerEarner} earns below the personal allowance
                    and can transfer {transfer_amount} to {higherEarner},
                    reducing their tax bill by up to {saving} per year."
  priority: medium
  note: "Apply through HMRC. Can be backdated up to 4 years."
  note: "Claim is worth up to {backdated_total} if backdated
         for 4 years."
```

| # | Strategy | Headline | Priority |
|---|----------|----------|----------|
| SP5 | Marriage Allowance | "Claim Marriage Allowance" | `medium` |
| SP5a | Marriage Allowance (skip) | -- | "Higher earner is above basic rate -- Marriage Allowance not available." |

### Strategy 6: IHT Planning

```
IHT PLANNING
==============

+-- combined_estate > (NRB + RNRB) * 2?
    (i.e. > (325,000 + 175,000) * 2 = 1,000,000)
    NO -> skip strategy
    YES ->
      excess = combined_estate - ((nrb + rnrb) * 2)
      potential_iht = excess * 0.40

RECOMMEND: [SP6]
  headline: "Combined estate exceeds IHT threshold"
  explanation: "Your combined estate of {combined_estate} exceeds the
               {threshold} threshold by {excess}. Consider estate
               planning strategies."
  personal_context: "Your combined estate exceeds the double nil-rate
                    band by {excess}, potentially exposing {potential_iht}
                    to Inheritance Tax at 40%."
  priority: info
  note: "Pensions are normally outside the estate for IHT."
  note: "Consider the estate planning module for detailed analysis."
  note (if April 2027 aware): "From April 2027, pensions will be
         brought into IHT scope, increasing effective estate values."
```

| # | Strategy | Headline | Priority |
|---|----------|----------|----------|
| SP6 | IHT planning | "Combined estate exceeds IHT threshold" | `info` |

### Strategy 7: Death Benefit Nomination Review (NEW)

```
DEATH BENEFIT NOMINATION REVIEW
=================================

+-- Either partner has pension accounts?
|   AND (no nomination recorded OR nomination > 2 years old)?
    YES ->
      RECOMMEND: [SP7]
        headline: "Review pension death benefit nominations"
        explanation: "Both partners should review pension death benefit
                     nominations regularly. Nominations are typically
                     non-binding expressions of wish -- keeping them
                     current ensures trustees understand your intentions."
        personal_context: "{partner} pension {name} {has no recorded
                         nomination / was last nominated {date}}.
                         Review and update to ensure current wishes
                         are reflected."
        priority: info (default)
        priority: high (if divorce or death_of_partner event active)
        note: "Former spouses are not automatically removed from
               pension nominations after divorce."
        note: "Review whenever circumstances change: marriage,
               divorce, new children, death of beneficiary."
```

| # | Strategy | Headline | Priority |
|---|----------|----------|----------|
| SP7 | Death benefit nominations | "Review pension death benefit nominations" | `info` (default), `high` (post-divorce/bereavement) |

### Strategy 8: Beneficial Ownership Declaration — Form 17 (NEW)

```
BENEFICIAL OWNERSHIP DECLARATION (FORM 17)
============================================

+-- Joint savings or investment accounts exist?
|   AND partners in different tax bands?
|   AND ownership_type = "joint" (50:50 default)?
    YES ->
      lower_rate_partner = partner with lower marginal rate
      interest_or_income = estimated annual income from joint accounts

      RECOMMEND: [SP8]
        headline: "Beneficial ownership declaration (Form 17)"
        explanation: "Joint accounts are automatically split 50:50
                     for tax purposes between married couples. If
                     the actual beneficial ownership differs, you can
                     submit Form 17 to HMRC to have income taxed
                     according to actual ownership shares."
        personal_context: "Your joint accounts earn approximately
                         {income} per year. If {lower_rate_partner}
                         beneficially owns a larger share, submitting
                         Form 17 could reduce the household tax bill
                         by taxing more income at the lower rate."
        priority: low
        note: "Form 17 must reflect the ACTUAL beneficial ownership --
               you cannot simply declare any split for tax purposes."
        note: "Both partners must sign the declaration."
        note: "The declaration remains in force until ownership
               changes or it is withdrawn."
        note: "This applies to property income too -- if you
               jointly own rental property."
```

| # | Strategy | Headline | Priority |
|---|----------|----------|----------|
| SP8 | Beneficial ownership (Form 17) | "Beneficial ownership declaration (Form 17)" | `low` |

### Spouse Message Reference (Complete)

| # | Strategy | Config Key | Headline | Priority |
|---|----------|------------|----------|----------|
| SP0a | Gate | `spouse.gate.no_spouse` | -- | -- |
| SP0b | Gate | `spouse.gate.not_married` | -- | -- |
| SP1 | CGT sharing | `spouse.cgt_sharing.trigger` | "Share CGT allowance with spouse" | `medium` |
| SP2 | ISA coordination | `spouse.isa_coordination.trigger` | "Maximise household ISA allowance" | `medium` |
| SP3 | PSA optimisation | `spouse.psa_optimisation.trigger` | "Shift savings to lower-rate spouse" | `medium` |
| SP4a | Pension (higher rate) | `spouse.pension_coordination.higher_rate` | "Prioritise pension for higher-rate partner" | `high` |
| SP4b | Pension (non-earner) | `spouse.pension_coordination.non_earner` | "Non-earning spouse pension contribution" | `medium` |
| SP4c | Pension (carry forward) | `spouse.pension_coordination.carry_forward` | "Use spouse's pension carry forward" | `medium` |
| SP5 | Marriage Allowance | `spouse.marriage_allowance.eligible` | "Claim Marriage Allowance" | `medium` |
| SP5a | Marriage Allowance (skip) | `spouse.marriage_allowance.not_available` | -- | -- |
| SP6 | IHT planning | `spouse.iht_planning.trigger` | "Combined estate exceeds IHT threshold" | `info` |
| SP7 | Death benefit nominations | `spouse.death_benefit_nominations.review` | "Review pension death benefit nominations" | `info` / `high` |
| SP8 | Beneficial ownership | `spouse.beneficial_ownership.form17` | "Beneficial ownership declaration (Form 17)" | `low` |

---

## 11. Phase 8: Compliance & Suitability (NEW PHASE)

**Purpose:** Post-recommendation layer that appends compliance warnings, suitability alignment checks, and review triggers. This phase does not generate new recommendations -- it appends notes and warnings to existing recommendations from Phases 4-7.

**When it runs:** After all recommendation-generating phases (Waterfall, Portfolio Health, Transfers, Spouse) and before Conflict Resolution. It reviews the full set of pending recommendations.

### 8.1 FCA Consumer Duty Layer

The Financial Conduct Authority's Consumer Duty (effective July 2023) requires firms to act in customers' best interests. While Fynla is not an FCA-authorised firm, the engine should produce outputs consistent with Consumer Duty principles: fair value, consumer understanding, and appropriate outcomes.

```
CONSUMER DUTY CHECKS
======================

[Check 1] Complex product warning
|
+-- investment_experience = 'none'?
|   AND recommendation involves bonds, VCT, EIS, or SEIS?
    YES -> Append to recommendation:
           note: [CO1a] "This involves a complex financial product.
                         Consider seeking independent financial advice
                         before proceeding."
           (This should not typically occur because experience gates
            in the waterfall already block these, but serves as a
            safety net if experience data changes mid-session)

[Check 2] Vulnerability indicators
|
+-- Any of the following conditions detected?
|   - death_of_partner life event (within 12 months)
|   - serious_illness life event
|   - redundancy life event (within 6 months)
|   - age >= 80
    YES -> Append to ALL recommendations:
           note: [CO1b] "Given your circumstances, you may benefit
                         from speaking to an independent financial
                         adviser who can provide personalised advice."

[Check 3] High-risk allocation for inexperienced investors
|
+-- investment_experience in [none, beginner]?
|   AND total venture scheme (SEIS/EIS/VCT) recommendations > 0?
    YES -> Append to venture recommendations:
           note: [CO1c] "Venture capital schemes involve a high risk
                         of losing your entire investment. These are
                         not suitable for all investors."
```

| # | Condition | Config Key | Note |
|---|-----------|------------|------|
| CO1a | Complex product + no experience | `compliance.consumer_duty.complex_product` | "This involves a complex financial product. Consider seeking independent financial advice." |
| CO1b | Vulnerability indicators | `compliance.consumer_duty.vulnerability` | "Given your circumstances, you may benefit from speaking to an independent financial adviser." |
| CO1c | High-risk + inexperienced | `compliance.consumer_duty.high_risk` | "Venture capital schemes involve a high risk of losing your entire investment." |

### 8.2 Suitability Alignment Check

```
SUITABILITY ALIGNMENT
======================

[Check 1] Low risk profile with high equity recommendations
|
+-- risk_level in [low, very_low]?
|   AND total equity-oriented recommendations > 50% of surplus?
    YES -> Append to equity recommendations:
           note: [CO2a] "Based on your low risk profile, consider
                         holding cash or bond funds within these
                         wrappers rather than equities. The wrapper
                         (ISA, pension) provides tax benefits
                         regardless of the underlying asset."

[Check 2] High risk with significant cash
|
+-- risk_level in [high, very_high]?
|   AND cash/savings recommendations (excluding emergency fund) > 30% of surplus?
    YES -> Append to cash recommendations:
           note: [CO2b] "Based on your higher risk tolerance, you
                         may wish to minimise cash holdings beyond
                         your emergency fund and prioritise
                         growth-oriented investments."

[Check 3] Approaching retirement with high equity
|
+-- years_to_retirement <= 5?
|   AND total equity allocation > 70%?
    YES -> Append to equity recommendations:
           note: [CO2c] "With {years} years to retirement, your
                         high equity allocation carries sequence-of-
                         returns risk. Consider a glide path to
                         gradually reduce equity exposure."
```

| # | Condition | Config Key | Note |
|---|-----------|------------|------|
| CO2a | Low risk + high equity | `compliance.suitability.low_risk_equity` | "Based on your low risk profile, consider cash or bond funds within these wrappers." |
| CO2b | High risk + significant cash | `compliance.suitability.high_risk_cash` | "Based on your higher risk tolerance, minimise cash beyond emergency fund." |
| CO2c | Near retirement + high equity | `compliance.suitability.retirement_equity` | "With {years} years to retirement, high equity allocation carries sequence-of-returns risk." |

### 8.3 Ongoing Review Triggers

```
REVIEW TRIGGERS
================

[Check 1] Tax year end approaching
|
+-- Current date between 6 January and 5 April?
    YES -> Append to ALL allowance-related recommendations:
           note: [CO3a] "Tax year ends 5 April. Review your ISA,
                         pension, and CGT position before the deadline.
                         Unused allowances cannot be carried forward
                         (except pension carry forward)."

[Check 2] Stale review
|
+-- last_review_date > 12 months ago OR never reviewed?
    YES -> Generate standalone recommendation:
           [CO3b]
           headline: "Annual investment review due"
           explanation: "Over 12 months since your last review.
                        Your circumstances, tax position, and
                        market conditions may have changed."
           priority: info
           note: "Review risk profile, asset allocation, and
                  goal progress."

[Check 3] Post-life-event review
|
+-- Significant life event recorded in last 6 months?
|   AND no review since event date?
    YES -> Generate standalone recommendation:
           [CO3c]
           headline: "Review following {event}"
           explanation: "Your recent {event_description} may have
                        changed your financial priorities. Review
                        your investment strategy."
           priority: medium
           note: "Life events often change risk tolerance,
                  time horizons, and financial goals."
```

| # | Condition | Config Key | Note / Message |
|---|-----------|------------|----------------|
| CO3a | Jan-Apr (tax year end) | `compliance.review.tax_year_end` | "Tax year ends 5 April. Review ISA, pension, and CGT position." |
| CO3b | No review > 12 months | `compliance.review.annual_due` | "Over 12 months since your last review." |
| CO3c | Post-life-event | `compliance.review.post_event` | "Your recent {event} may have changed your financial priorities." |

### 8.4 Pound Cost Averaging vs Lump Sum

```
POUND COST AVERAGING ASSESSMENT
=================================

+-- contribution_type = "lump_sum"?
|   AND amount > 10,000?
    YES ->
         |
         +-- risk_level in [low, very_low]?
         |   YES -> note: [CO4a] "With a lump sum of {amount},
         |                        consider investing in stages over
         |                        6-12 months (pound cost averaging)
         |                        to smooth out entry price volatility.
         |                        This approach reduces the risk of
         |                        investing everything at a market peak."
         |
         +-- risk_level in [medium, high, very_high]?
             YES -> note: [CO4b] "Academic evidence suggests lump sum
                                  investing outperforms pound cost
                                  averaging in approximately two-thirds
                                  of historical periods. However, if
                                  the potential for short-term losses on
                                  a {amount} investment would cause you
                                  to change strategy, phased investment
                                  may be more appropriate."
```

| # | Condition | Config Key | Note |
|---|-----------|------------|------|
| CO4a | Lump sum > 10k + low risk | `compliance.pca.low_risk` | "Consider investing in stages over 6-12 months (pound cost averaging)." |
| CO4b | Lump sum > 10k + medium/high risk | `compliance.pca.evidence` | "Evidence suggests lump sum investing outperforms in most historical periods." |

---

## 12. Phase 9: Conflict Resolution

**Service:** `ConflictResolutionService` | **File:** `app/Services/Investment/Recommendation/ConflictResolutionService.php`

Conflict resolution merges all recommendations from Phases 4-8 and resolves competing claims on shared resources (surplus, ISA allowance, pension allowance). It produces the final ordered list of recommendations.

### Conflict 1: Surplus Income Priority

```
SURPLUS INCOME PRIORITY
=========================

+-- Total demand across all recommendations > disposable_income?
    YES -> Sort by 12-step priority order:

    PRIORITY ORDER (highest to lowest):
    1.  employer_match        (free money)
    2.  high_interest_debt    (> 15% -- more than any return)
    3.  emergency_fund        (financial safety net)
    4.  protection            (dependents at risk)
    5.  lifetime_isa          (25% bonus)
    6.  stocks_shares_isa     (tax-free growth, no limits)
    7.  pension               (tax relief at marginal rate)
    8.  nsi_savings           (government-backed)
    9.  pension_carry_forward (older allowance expiring)
    10. bonds                 (offshore/onshore)
    11. vct_eis_seis          (high risk, high relief)
    12. gia                   (catch-all, no tax benefit)

    Allocate surplus in priority order until exhausted.

    +-- Partially funded?
    |   YES -> note: [C1a] "Partially funded due to surplus
    |                       constraints."
    |   Status: recommendation.amount = reduced_amount
    |   Status: recommendation.status = 'active'
    |   Status: recommendation.is_deferred = false
    |
    +-- Fully deferred (no surplus remaining)?
        YES -> note: [C1b] "Insufficient surplus after higher-priority
                            allocations."
        Status: recommendation.status = 'deferred'
        Status: recommendation.is_deferred = true
        Status: recommendation.deferred_reason = "Insufficient surplus"
```

| # | Conflict | Config Key | Message |
|---|---------|------------|---------|
| C1a | Surplus (partial) | `conflicts.surplus.partial` | "Partially funded due to surplus constraints." |
| C1b | Surplus (deferred) | `conflicts.surplus.deferred` | "Insufficient surplus after higher-priority allocations." |

### Conflict 2: ISA Allowance Competition

```
ISA ALLOWANCE COMPETITION
===========================

+-- Total ISA demand (LISA + S&S ISA + Cash ISA) > isa_remaining?
    YES -> Allocate in order:
           1. LISA first (25% government bonus is highest return)
           2. S&S ISA (tax-free growth)
           3. Cash ISA (tax-free interest -- lowest priority)

    +-- Partially funded?
    |   YES -> note: [C2a] "ISA allowance partially allocated.
    |                       {remaining} of {total} used for
    |                       higher-priority ISA recommendation."
    |
    +-- Fully deferred?
        YES -> note: [C2b] "ISA allowance exhausted by higher-priority
                            ISA recommendations."
```

| # | Conflict | Config Key | Message |
|---|---------|------------|---------|
| C2a | ISA (partial) | `conflicts.isa.partial` | "ISA allowance partially allocated." |
| C2b | ISA (deferred) | `conflicts.isa.deferred` | "ISA allowance exhausted by higher-priority ISA recommendations." |

### Conflict 3: Pension Allowance Competition

```
PENSION ALLOWANCE COMPETITION
===============================

+-- Total pension demand (current year + carry forward + spouse)
|   > pension_aa_remaining + carry_forward_available?
    YES -> Allocate by highest tax relief first:
           1. Additional rate partner's current year AA
           2. Higher rate partner's current year AA
           3. Basic rate current year AA
           4. Carry forward (oldest year first)

    +-- Fully deferred?
        YES -> note: [C3] "Pension annual allowance exhausted."
```

| # | Conflict | Config Key | Message |
|---|---------|------------|---------|
| C3 | Pension (deferred) | `conflicts.pension.deferred` | "Pension annual allowance exhausted." |

### Conflict 4: Goal Competition

```
GOAL COMPETITION
=================

+-- Multiple goals compete for same wrapper allocation?
    YES -> Sort by:
           1. Priority: high > medium > low
           2. Urgency: shortest timeline first
           3. Amount: smaller goals first (quicker wins)

    Internal reordering only -- no user-facing messages.
    Each goal's linked recommendation has its amount adjusted
    proportionally.
```

### Conflict 5: Life Event Overrides

```
LIFE EVENT OVERRIDES
=====================

+-- Any life event modifier blocks a wrapper?
    YES -> ALL recommendations for that wrapper get:
           status: 'blocked'
           is_blocked: true
           blocked_reason: "Blocked by {event_type} life event."
           note: [C5] "Blocked by life event."

    BLOCKs always beat TRIGGERs:
    If any modifier blocks a wrapper, trigger modifiers that
    prioritise that wrapper have it removed from their
    prioritised_wrappers list.
```

| # | Conflict | Config Key | Message |
|---|---------|------------|---------|
| C5 | Life event block | `conflicts.life_event.blocked` | "Blocked by life event." |

### Conflict 6: Protection vs Investment

```
PROTECTION VS INVESTMENT
==========================

Delegates to existing ConflictResolver service.
No new messages generated by this conflict type.

Logic:
+-- Protection gap exists AND surplus is limited?
    YES -> Protection recommendations take priority over
           investment contributions (but not over emergency fund
           or debt repayment).
```

### General Conflict Note

| # | Config Key | Message |
|---|------------|---------|
| -- | `conflicts.general.waterfall_vs_conflict` | "Waterfall order (tax efficiency) differs from conflict order (tax relief) by design." |

---

## 13. Phase 10: Output Formatting & Priority

**Service:** `RecommendationOutputFormatter` | **File:** `app/Services/Investment/Recommendation/RecommendationOutputFormatter.php`

### Priority Sorting

All recommendations are sorted by numeric priority before returning to the API:

| Label | Numeric | Typical Sources |
|-------|---------|----------------|
| `critical` | 1 | Critical debt, emergency fund <1mo, MPAA-triggered ISA, additional-rate pension |
| `high` | 2 | Medium debt, emergency fund 1-3mo, ISA, higher-rate pension, Bed & ISA (no CGT), PSA breach, carry forward (high rate), portfolio drift >10%, AIM BR change |
| `medium` | 3 | Emergency fund building, protection, LISA, Premium Bonds, spouse strategies, Bed & Pension, platform fees, portfolio drift >5%, tax loss harvesting |
| `low` | 4 | NS&I, bonds, carry forward, VCT/EIS/SEIS, GIA, goal wrapper optimisation, Form 17, REIT wrapper, fund charges |
| `info` | 5 | Emergency fund excess, life event sub-actions, IHT planning, ISA consolidation, account consolidation, nominations, annual review, fund type analysis |

### Output Fields Per Recommendation

| Field | Description |
|-------|-------------|
| `uuid` | Unique identifier (generated via `Str::uuid()`) |
| `module` | Always "investment" |
| `category` | One of: `contribution`, `transfer`, `rebalance`, `debt`, `emergency_fund`, `protection`, `spouse`, `life_event`, `portfolio_health`, `compliance` |
| `wrapper` | Disambiguated wrapper name (never bare "isa") |
| `headline` | Short action-oriented title (max 80 characters) |
| `explanation` | Why this recommendation exists (1-3 sentences) |
| `personal_context` | Personalised explanation using the user's actual numbers |
| `amount` | Recommended amount (numeric) |
| `frequency` | `"one_off"` or `"monthly"` |
| `tax_relief` | Tax relief amount (numeric, 0 if none) |
| `estimated_annual_benefit` | Calculated annual benefit (numeric) |
| `effective_cost` | `amount - tax_relief` |
| `timeline` | Relevant timeline (e.g. "current_tax_year", "3_years") |
| `priority_label` | `critical` / `high` / `medium` / `low` / `info` |
| `priority_numeric` | 1-5 (for sorting) |
| `status` | `active`, `blocked`, `deferred` |
| `is_blocked` | Boolean |
| `blocked_reason` | Why it is blocked (string or null) |
| `is_deferred` | Boolean |
| `deferred_reason` | Why it is deferred (string or null) |
| `linked_goal_id` | Related goal ID (nullable) |
| `linked_account_id` | Related account ID (nullable) |
| `linked_life_event_id` | Related life event ID (nullable) |
| `notes` | Array of advisory notes (strings) |
| `decision_path` | Array of decision trail entries for transparency |

### Status Mapping

| Source | Status | is_blocked | is_deferred |
|--------|--------|------------|-------------|
| Safety block (critical/high severity) | `blocked` | true | false |
| Safety block (medium/info severity) | `active` | false | false |
| Conflict resolution deferred | `deferred` | false | true |
| Normal recommendation | `active` | false | false |
| Life event block | `blocked` | true | false |

### Deduplication

Key = `{headline}:{wrapper}` -- if the same recommendation appears from contribution, transfer, and spouse sources, only the first occurrence is kept. Later duplicates are merged: their notes are appended and the highest priority is retained.

```
DEDUPLICATION LOGIC
====================

For each recommendation in merged_list:
  key = normalise(headline) + ":" + wrapper
  |
  +-- key already exists in output?
      YES -> Merge:
             - Keep the earlier (higher priority) occurrence
             - Append any unique notes from the duplicate
             - Use the higher priority_numeric (lower number)
             - Merge decision_path entries
      NO  -> Add to output
```

### Estimated Annual Benefit Formulae

**CRITICAL: No hardcoded rates.** All rates must come from `TaxConfigService` or the user's risk profile return matrix. Dividend yields and growth rates come from the user's actual portfolio or risk-based assumptions via `AssumptionsService`/`RiskPreferenceService` — never hardcoded 2% or 5%.

| Wrapper | Formula | Variable Sources |
|---------|---------|-----------------|
| ISA types | `(dividend_yield × amount × dividend_tax_rate) + (growth × amount × cgt_rate)` | `dividend_yield` = user's portfolio weighted dividend yield (from holdings data). `growth` = user's risk-based return from `RiskPreferenceService::getReturnParameters()`. `dividend_tax_rate` and `cgt_rate` from `TaxConfigService::getDividendTax()` and `TaxConfigService::getCapitalGainsTax()` by user's tax band. |
| Pension | `amount × marginal_income_tax_rate` | `marginal_income_tax_rate` from user's tax band (fetched from DB, calculated by Income module). Tax rates from `TaxConfigService::getIncomeTax()`. |
| Premium Bonds | `amount × (prize_rate / 100) × marginal_rate` | `prize_rate` from `TaxConfigService::get('nsi.premium_bonds.prize_fund_rate')`. `marginal_rate` from user's tax band. |
| GIA | 0 | No tax benefit |
| Debt repayment | `amount × debt_interest_rate` | `debt_interest_rate` from actual user debt record — never a default. |
| SEIS | `amount × seis_relief_rate` | `seis_relief_rate` from `TaxConfigService::get('venture_capital.seis.income_tax_relief')` (currently 50%). |
| EIS | `amount × eis_relief_rate` | `eis_relief_rate` from `TaxConfigService::get('venture_capital.eis.income_tax_relief')` (currently 30%). |
| VCT | `amount × vct_relief_rate` | `vct_relief_rate` from `TaxConfigService::get('venture_capital.vct.income_tax_relief')` (30%, or 20% from April 2026). |
| Bed & ISA | `transfer_amount × growth × tax_rate` | `growth` from user's risk-based return. `tax_rate` from user's tax band via `TaxConfigService`. |
| Tax loss harvesting | `loss × cgt_rate` | `cgt_rate` from `TaxConfigService::getCapitalGainsTax()` by user's tax band. |

### Wrapper Disambiguation Map

| Input | Output |
|-------|--------|
| `isa` | `stocks_shares_isa` |
| `cash_isa` | `cash_isa` |
| `stocks_shares_isa` | `stocks_shares_isa` |
| `lifetime_isa` | `lifetime_isa` |
| `junior_isa` | `junior_isa` |
| `pension` | `pension` |
| `gia` | `gia` |
| `offshore_bond` | `offshore_bond` |
| `onshore_bond` | `onshore_bond` |
| `seis` | `seis` |
| `eis` | `eis` |
| `vct` | `vct` |
| `vct_eis_seis` | `vct_eis_seis` (legacy — split into separate wrappers in v1.0) |
| `premium_bonds` | `premium_bonds` |
| `nsi_savings` | `nsi_savings` |
| `savings_account` | `savings_account` |

---

## 14. Tax Rules Reference

**CRITICAL: `TaxConfigService` is the single source of truth for ALL tax rates, allowances, thresholds, and relief rates in this section.** No values should be hardcoded in services, controllers, or components. Where values relate to the user (tax band, income, risk level, portfolio yields), they must always come from actual user data — never defaults. All rates below are reference values for the current tax year; at runtime, always fetch from `TaxConfigService`.

### 14.1 Capital Gains Tax

```
CAPITAL GAINS TAX (CGT)
=========================
Source: TaxConfigService::getCapitalGainsTax()

Annual Exempt Amount: 3,000 (2024/25 onwards; was 6,000 in 2023/24)

Rates (from 30 October 2024):
  Basic rate:      18%
  Higher rate:     24%
  Additional rate: 24%

  (Previous rates pre-30 Oct 2024: 10% basic, 20% higher)
  (Residential property: 18% basic, 24% higher -- unchanged)

SHARE IDENTIFICATION RULES (FIFO for matching disposals to acquisitions):
  1. Same-day acquisitions
     Shares bought and sold on the same day are matched first.

  2. 30-day rule (Bed & Breakfast)
     Shares sold are matched against shares bought within
     the FOLLOWING 30 days. Prevents crystallising a gain/loss
     and immediately repurchasing.

  3. Section 104 pool
     All remaining shares are pooled into a single holding
     with a weighted average cost basis. Disposals are
     matched against this pool.

SECTION 104 POOL MECHANICS:
  pool_quantity += purchased_quantity
  pool_cost += purchase_cost (including dealing costs)

  On disposal:
    proportion = disposed_quantity / pool_quantity
    cost_of_disposal = pool_cost * proportion
    gain_or_loss = proceeds - cost_of_disposal

    pool_quantity -= disposed_quantity
    pool_cost -= cost_of_disposal

BED & ISA:
  The 30-day rule does NOT apply when the repurchase is in
  a different wrapper (ISA vs GIA). You can:
  1. Sell shares in GIA
  2. Subscribe to ISA (uses ISA allowance)
  3. Buy the SAME shares in the ISA on the same day
  This is legitimate tax planning, not avoidance.

BED & BREAKFAST (same wrapper):
  If you sell shares in a GIA and repurchase the SAME shares
  in the GIA within 30 days, the 30-day rule matches the
  new purchase to the disposal -- no gain or loss is crystallised.

  Workarounds:
  - Wait 31 days before repurchasing
  - Buy a similar-but-different fund (e.g. different provider's
    index tracker for the same market)
  - Buy within an ISA (cross-wrapper exempt)

LOSS RELIEF:
  Current year losses:
    MUST be offset against gains in the same tax year.
    Cannot be selective -- all current year losses are applied.

  Carried-forward losses:
    Applied selectively -- only enough to reduce gains to the
    annual exempt amount. No time limit on carry-forward.

  Loss cannot create or increase a loss for offset against income.
  Exception: losses on EIS/SEIS shares CAN be set against income.

BUSINESS ASSET DISPOSAL RELIEF (BADR):
  Qualifying disposals of business assets:
    2025/26: 14% (on first 1,000,000 lifetime limit)
    From April 2026: 18%
    Lifetime limit: 1,000,000

AIM SHARES BUSINESS RELIEF:
  2025/26: 100% Business Relief (fully IHT-exempt)
  From April 2026: 50% Business Relief (effective IHT rate: 20%)
  Note: This is IHT relief, not CGT relief.

DIRECT GILTS:
  Capital gains on UK government bonds (gilts) are EXEMPT from CGT.
  Only the coupon income is taxable as savings income.
  This makes direct gilts (not gilt funds) tax-efficient for
  higher-rate taxpayers seeking fixed income in a GIA.
```

### 14.2 Dividend Tax

```
DIVIDEND TAX
==============

Dividend Allowance: 500 (2024/25 onwards; was 1,000 in 2023/24)

Dividend Tax Rates:
  Basic rate:      8.75%
  Higher rate:     33.75%
  Additional rate: 39.35%

ORDERING:
  Dividends are the TOP SLICE of income. This means:
  1. Calculate non-dividend income (employment, savings, etc.)
  2. Apply personal allowance to non-dividend income
  3. Stack dividends ON TOP of remaining non-dividend income
  4. Dividends that fall within the basic rate band = 8.75%
  5. Dividends that fall within the higher rate band = 33.75%
  6. Dividends above the additional rate threshold = 39.35%

  The dividend allowance is a 0% rate band, not a deduction.
  It does NOT reduce taxable income -- dividends within the
  allowance still count towards determining the tax band.

ACCUMULATION VS INCOME FUNDS IN GIA:
  Both are taxable on dividends in a GIA.
  Accumulation funds reinvest dividends internally, but HMRC
  treats the reinvested amount as a "notional distribution" --
  the investor is liable for dividend tax on the distribution
  even though no cash was received.

  Within ISA or pension: no dividend tax on either type.
```

### 14.3 ISA Rules

```
ISA RULES (2025/26)
=====================

ANNUAL LIMIT:
  Total across all ISA types: 20,000
  This is shared across: Cash ISA, S&S ISA, IFISA, and LISA
  LISA sub-limit: 4,000 (counts towards the 20,000)
  Junior ISA: 9,000 (separate from adult ISA)

MULTIPLE ISAs (from 6 April 2024):
  You CAN open multiple ISAs of the same type in the same
  tax year. Previously, only one of each type per year.

FLEXIBLE ISA:
  If provider offers flexible ISA feature:
  - Withdraw and re-contribute in the same tax year without
    using additional allowance
  - Re-contribution must be in the SAME tax year as withdrawal
  - Not all providers offer flexible ISA feature

TRANSFER RULES:
  Current year subscriptions:
    Must be transferred IN FULL. Cannot partially transfer
    current year amounts.

  Previous year subscriptions:
    Can be partially transferred. No restriction on amount.

  ISA-to-ISA transfer:
    Preserves ISA status. Does NOT use current year allowance.
    Must go through the transfer process (not withdraw and
    re-subscribe).

INHERITED ISA (Additional Permitted Subscription -- APS):
  When an ISA holder dies, their spouse/civil partner receives
  an Additional Permitted Subscription equal to the value of
  the ISA at the date of death (or when the ISA ceases to be
  a continuing account).

  Deadline: Within 3 years of death OR 180 days after
  administration of the estate is completed (whichever is later).

  APS is IN ADDITION to the spouse's own ISA allowance.
  Can be used with any provider, not just the deceased's.

UPCOMING CHANGE (April 2027):
  Cash ISA annual limit proposed to reduce from 20,000 to
  12,000 for under-65s. S&S ISA limit would absorb the
  difference. Over-65s retain the full 20,000 Cash ISA limit.
```

### 14.4 Pension Rules

```
PENSION RULES (2025/26)
=========================

ANNUAL ALLOWANCE (AA): 60,000
  Applies to total contributions (employer + employee + third party)
  Tax relief is available on contributions up to relevant UK earnings
  or 60,000, whichever is lower.

TAPERED ANNUAL ALLOWANCE:
  Applies if BOTH conditions met:
    1. Threshold income > 200,000
    2. Adjusted income > 260,000

  Threshold income = total income BEFORE pension contributions
  Adjusted income = threshold income + employer contributions

  Taper: AA reduces by 1 for every 2 of adjusted income above 260,000
  Floor: AA cannot be reduced below 10,000
  Fully tapered at: adjusted income = 360,000

MONEY PURCHASE ANNUAL ALLOWANCE (MPAA): 10,000
  Triggered by flexibly accessing a defined contribution pension:
    - Taking income drawdown
    - Taking an uncrystallised funds pension lump sum (UFPLS)
    - Taking a flexible annuity

  NOT triggered by:
    - Taking tax-free cash only (25% PCLS)
    - Taking a conventional annuity
    - Taking a defined benefit pension

  Effect:
    Money purchase contributions limited to 10,000
    Remaining AA available for defined benefit accrual (if any)
    Carry forward NOT available for the MPAA portion

CARRY FORWARD:
  Use unused AA from previous 3 tax years
  Requirements:
    1. Must have been a member of a registered pension scheme
       in the year being carried forward
    2. Current year AA must be used first
    3. Oldest year used first (FIFO)

  MPAA + carry forward:
    If MPAA triggered, carry forward is NOT available for money
    purchase contributions. Carry forward only applies to the
    alternative AA (DB schemes).

TAX RELIEF:
  Basic rate: 20% (added by HMRC to net contribution)
  Higher rate: additional 20% claimed via self-assessment
  Additional rate: additional 25% claimed via self-assessment
  Scottish rates: different bands, same total relief mechanism

  Net contribution = gross × (1 - basic_rate)
  Example: 10,000 gross = 8,000 net + 2,000 basic relief
  Higher rate reclaims additional 2,000 via self-assessment
  Additional rate reclaims additional 2,500 via self-assessment

EMPLOYER CONTRIBUTIONS:
  Count towards AA but not earnings test
  No income tax or employee NIC on employer contributions
  Corporation tax deductible for employer
  Employer NIC savings: employer saves NIC on salary sacrificed

SALARY SACRIFICE:
  Employee gives up salary; employer pays into pension instead.
  Benefits:
    - Employee saves income tax AND employee NIC
    - Employer saves employer NIC (currently 15%)
    - Some employers share the NIC saving

  From April 2029:
    NIC advantage on salary sacrifice capped at first 2,000
    of contributions. Contributions above 2,000 will be treated
    as if they were salary for NIC purposes.

ACCESS AGE:
  Current (2025/26): 55
  From April 2028: 57
  Protected pension ages: some schemes have protected lower
  access ages (must have been in scheme before 4 November 2021)

DEATH BENEFITS:
  Before age 75: tax-free lump sum or tax-free income drawdown
  After age 75: taxed at beneficiary's marginal income tax rate

IHT TREATMENT:
  Current (2025/26): Pensions OUTSIDE the estate for IHT
  From April 2027: Pensions brought INTO IHT scope
    - Fundamental change for estate planning
    - Pension death benefits will form part of the estate
    - Impact on estate planning strategy for pension drawdown
      vs decumulation timing
```

### 14.5 Bond Rules

```
INVESTMENT BOND RULES
=======================

ONSHORE BONDS:
  Internal taxation: 20% (corporation tax within the fund)
  On encashment: full gain taxable but 20% tax credit applied
  Effective additional tax:
    Basic rate: 0% (20% credit covers 20% liability)
    Higher rate: 20% (40% - 20% credit)
    Additional rate: 25% (45% - 20% credit)

OFFSHORE BONDS:
  Internal taxation: 0% (gross roll-up in offshore jurisdiction)
  On encashment: full gain taxable at marginal rate
  No tax credit available
  Effective tax:
    Basic rate: 20%
    Higher rate: 40%
    Additional rate: 45%

  Offshore bonds are beneficial when:
    - Investor is higher/additional rate NOW
    - Expects to be basic rate at encashment (retirement)
    - Effective saving: difference between current and future rate

5% TAX-DEFERRED WITHDRAWAL:
  Both onshore and offshore bonds allow withdrawal of up to
  5% of original investment per year WITHOUT triggering a
  chargeable event.

  Cumulative allowance: 5% per year × 20 years = 100%
  If unused in a year, it carries forward.
  Example: no withdrawals for 5 years = 25% can be taken.

  Excess withdrawals: trigger a chargeable event on the excess.

TOP-SLICING RELIEF (onshore bonds):
  Purpose: prevents the total gain being taxed in a single year
  at a higher rate than would apply if spread across years.

  Calculation:
    1. Total gain = surrender value - total premiums paid
    2. Sliced gain = total gain / number of complete years held
    3. Calculate tax on sliced gain at marginal rate
    4. Multiply by number of years = top-sliced tax
    5. Compare with tax on full gain in one year
    6. Use the LOWER amount

CHARGEABLE EVENTS:
  1. Death of the last life assured
  2. Maturity of the bond
  3. Full surrender
  4. Partial surrender exceeding 5% cumulative allowance
  5. Assignment for value (sale)

  NOT a chargeable event:
  - Assignment by way of gift (no consideration)
  - Assignment on death (but death itself is chargeable)

SEGMENTS:
  Bonds can be divided into segments (e.g. 20 segments).
  Each segment is a separate policy.
  Benefits:
  - Surrender individual segments without triggering a
    chargeable event on the whole bond
  - Each segment retains its own 5% withdrawal allowance
  - Allows partial encashment strategy

  Recommendation: split into at least 20 segments for
  maximum flexibility.
```

### 14.6 VCT/EIS/SEIS Rules

```
VENTURE CAPITAL SCHEME RULES
==============================
```

| Feature | SEIS | EIS | VCT |
|---------|------|-----|-----|
| **Income tax relief** | 50% | 30% | 30% (20% from April 2026) |
| **Annual investment limit** | 200,000 | 1,000,000 (2,000,000 KIC) | 200,000 |
| **Minimum holding period** | 3 years | 3 years | 5 years |
| **CGT on disposal** | Exempt (if held 3+ years) | Exempt (if held 3+ years) | Exempt (always) |
| **CGT deferral** | 50% reinvestment relief | Unlimited deferral | None |
| **Loss relief** | Yes (net of 50% relief) | Yes (net of 30% relief) | No |
| **Dividends** | Taxable | Taxable | Tax-free |
| **IHT Business Relief** | 100% after 2 years | 100% after 2 years | No (listed shares) |
| **FSCS protection** | No | No | No |
| **Company size limit** | <25 employees, <350k assets | <250 employees, <15m assets | Fund of qualifying companies |
| **Company age limit** | <3 years old | <7 years (10 for KIC) | Various |
| **Carry-back** | 1 year | 1 year | No |
| **Liquidity** | Very low (private companies) | Very low (private companies) | Low (listed but thin market) |

```
SEIS SPECIFICS:
  - 50% income tax relief on investments up to 200,000/year
  - Designed for very early-stage companies (<3 years old)
  - CGT reinvestment relief: 50% of gain reinvested into SEIS
    is exempt from CGT (not deferred -- permanently exempt)
  - Loss relief: losses (net of 50% income tax relief) can be
    offset against income or CGT
  - Carry-back: can elect to treat investment as made in
    the previous tax year

EIS SPECIFICS:
  - 30% income tax relief on investments up to 1,000,000/year
    (2,000,000 if investing in knowledge-intensive companies)
  - CGT deferral: unlimited capital gains can be deferred by
    investing the gain into EIS shares. Deferred gain becomes
    chargeable when EIS shares are disposed of.
  - Loss relief: losses (net of 30% income tax relief) can be
    offset against income or CGT
  - IHT Business Relief: 100% after 2 years
  - Carry-back: can elect to treat investment as made in
    the previous tax year

VCT SPECIFICS:
  - 30% income tax relief (20% from April 2026) on investments
    up to 200,000/year
  - Must be new ordinary shares (not secondary market purchases
    for income tax relief -- secondary market shares still get
    CGT exemption and tax-free dividends)
  - Tax-free dividends: all VCT dividends are exempt from
    income tax (regardless of amount)
  - CGT exemption: no CGT on disposal of VCT shares
  - No CGT deferral (unlike EIS)
  - No loss relief (unlike EIS/SEIS)
  - No IHT Business Relief (VCTs are listed shares)
  - 5-year minimum hold for income tax relief retention
  - If sold within 5 years: income tax relief clawed back
```

---

## 15. Thresholds & Constants Reference

| Threshold | Value | Service | Purpose |
|-----------|-------|---------|---------|
| **Debt Thresholds** | | | |
| Critical debt rate | > 15% | SafetyCheckService | Blocks all investment (surplus = 0) |
| Medium debt rate | 5-15% | SafetyCheckService | 50% surplus reduction |
| Mortgage exception rate | 3.0% | SafetyCheckService | Mortgages below this excluded from debt checks |
| Expected investment return | 5% | SafetyCheckService | Comparison against debt cost |
| Promotional rate warning | Within 6 months | SafetyCheckService | Debt promotional rate alert |
| **Emergency Fund Targets** | | | |
| Emergency fund target (employed) | 6 months | UserContextBuilder | Emergency fund assessment |
| Emergency fund target (self-employed) | 9 months | UserContextBuilder | Emergency fund assessment |
| Emergency fund target (retired) | 3 months | UserContextBuilder | Emergency fund assessment |
| Emergency fund target (unemployed) | 6 months | UserContextBuilder | Emergency fund assessment |
| Emergency Tier 1 (critical) | < 1 month runway | SafetyCheckService | Surplus = 0 |
| Emergency Tier 2 (high) | 1-3 months runway | SafetyCheckService | Surplus capped at 50% |
| Emergency Tier 3 (medium) | 3 months to target | SafetyCheckService | No cap, parallel building |
| Emergency Tier 4 (excess) | > target + 3 months | SafetyCheckService | Suggests investing excess |
| **ISA Limits** | | | |
| ISA annual limit | 20,000 | TaxConfigService | ISA allowance cap (shared across types) |
| LISA annual limit | 4,000 | TaxConfigService | LISA sub-limit (counts towards ISA) |
| LISA government bonus | 25% | TaxConfigService | LISA bonus rate |
| LISA age range | 18-49 | ContributionWaterfallService | LISA eligibility |
| LISA new account age limit | < 40 (without property goal) | ContributionWaterfallService | Cannot open new LISA |
| LISA property price limit | 450,000 | GoalAssessmentService | LISA first home eligibility |
| Junior ISA annual limit | 9,000 | TaxConfigService | Junior ISA allowance cap |
| Cash ISA limit (from April 2027, under-65) | 12,000 | TaxConfigService | Reduced Cash ISA allowance |
| ISA year-end urgency | < 3 months AND > 5,000 remaining | ContributionWaterfallService | Urgency note trigger |
| Cash ISA transfer minimum | 1,000 excess | TransferRecommendationService | Minimum for transfer recommendation |
| **Pension Limits** | | | |
| Pension annual allowance | 60,000 | TaxConfigService | Pension contribution cap |
| MPAA limit | 10,000 | TaxConfigService | MPAA pension cap |
| Pension AA taper (threshold income) | 200,000 | UserContextBuilder | AA taper trigger (condition 1) |
| Pension AA taper (adjusted income) | 260,000 | UserContextBuilder | AA taper trigger (condition 2) |
| Pension AA taper floor | 10,000 | UserContextBuilder | Minimum tapered AA |
| Pension AA fully tapered at | 360,000 adjusted | UserContextBuilder | AA = 10,000 |
| Non-earner pension gross limit | 3,600 | SpouseOptimisationService | Spouse pension cap |
| Pension access age (current) | 55 | TaxConfigService | Pension access gate |
| Pension access age (from April 2028) | 57 | TaxConfigService | Future pension access gate |
| Pension age limit for contributions | < 75 | ContributionWaterfallService | Pension contribution gate |
| **Tax Bands & Allowances** | | | |
| Personal allowance | 12,570 | TaxConfigService | Income tax and Marriage Allowance |
| PA taper threshold | 100,000 | UserContextBuilder | PA reduction trigger |
| PA taper rate | 1 for every 2 over 100k | UserContextBuilder | PA reduction formula |
| Basic rate band | < 50,270 | UserContextBuilder | Tax band derivation |
| Higher rate band | < 125,140 | UserContextBuilder | Tax band derivation |
| Additional rate band | >= 125,140 | UserContextBuilder | Tax band derivation |
| Basic rate | 20% | TaxConfigService | Income tax |
| Higher rate | 40% | TaxConfigService | Income tax |
| Additional rate | 45% | TaxConfigService | Income tax |
| **CGT** | | | |
| CGT annual exemption | 3,000 | TaxConfigService | CGT exemption |
| CGT rate (basic) | 18% | TaxConfigService | CGT on assets |
| CGT rate (higher/additional) | 24% | TaxConfigService | CGT on assets |
| BADR rate (2025/26) | 14% | TaxConfigService | Business Asset Disposal Relief |
| BADR rate (from April 2026) | 18% | TaxConfigService | Business Asset Disposal Relief |
| BADR lifetime limit | 1,000,000 | TaxConfigService | BADR lifetime cap |
| **Savings & Dividends** | | | |
| PSA (basic rate) | 1,000 | TaxConfigService | PSA breach detection |
| PSA (higher rate) | 500 | TaxConfigService | PSA breach detection |
| PSA (additional rate) | 0 | TaxConfigService | PSA breach detection |
| Starting rate for savings band | 5,000 | TaxConfigService | Extended savings nil rate |
| Dividend allowance | 500 | TaxConfigService | Dividend breach detection |
| Dividend rate (basic) | 8.75% | TaxConfigService | Dividend tax |
| Dividend rate (higher) | 33.75% | TaxConfigService | Dividend tax |
| Dividend rate (additional) | 39.35% | TaxConfigService | Dividend tax |
| **Premium Bonds & NS&I** | | | |
| Premium Bonds max holding | 50,000 | ContributionWaterfallService | Premium Bonds cap |
| Premium Bonds min age | 16 | ContributionWaterfallService | Premium Bonds eligibility |
| NS&I default allocation | 10% of remaining | ContributionWaterfallService | Conservative allocation |
| NS&I minimum | 25 | ContributionWaterfallService | Skip if below |
| **Bonds** | | | |
| Offshore bond minimum | 10,000 | ContributionWaterfallService | Minimum lump sum |
| Onshore bond minimum | 5,000 | ContributionWaterfallService | Minimum lump sum |
| Bond 5% tax-deferred withdrawal | 5% per year (cumulative) | TaxConfigService | Annual withdrawal allowance |
| **Venture Capital Schemes** | | | |
| SEIS annual limit | 200,000 | ContributionWaterfallService | SEIS investment cap |
| SEIS income tax relief | 50% | TaxConfigService | SEIS relief rate |
| SEIS CGT reinvestment relief | 50% of gain exempt | TaxConfigService | SEIS CGT benefit |
| SEIS minimum hold | 3 years | ContributionWaterfallService | SEIS holding period |
| EIS annual limit | 1,000,000 | ContributionWaterfallService | EIS investment cap |
| EIS KIC annual limit | 2,000,000 | ContributionWaterfallService | EIS knowledge-intensive cap |
| EIS income tax relief | 30% | TaxConfigService | EIS relief rate |
| EIS minimum hold | 3 years | ContributionWaterfallService | EIS holding period |
| VCT annual limit | 200,000 | ContributionWaterfallService | VCT investment cap |
| VCT income tax relief (current) | 30% | TaxConfigService | VCT relief rate 2025/26 |
| VCT income tax relief (from April 2026) | 20% | TaxConfigService | Reduced VCT relief rate |
| VCT minimum hold | 5 years | ContributionWaterfallService | VCT holding period |
| VCT/EIS/SEIS max portfolio % | 10% | ContributionWaterfallService | Max venture allocation |
| VCT/EIS/SEIS min allocation | 1,000 | ContributionWaterfallService | Minimum viable allocation |
| VCT/EIS/SEIS disposable gate | < 10% | ContributionWaterfallService | Insufficient buffer |
| **IHT** | | | |
| IHT nil-rate band | 325,000 | TaxConfigService | IHT planning trigger |
| IHT residence nil-rate band | 175,000 | TaxConfigService | IHT planning trigger |
| IHT rate | 40% | TaxConfigService | IHT calculation |
| IHT reduced rate (10%+ to charity) | 36% | TaxConfigService | Charitable giving |
| AIM share BR (current) | 100% | TaxConfigService | AIM IHT relief |
| AIM share BR (from April 2026) | 50% | TaxConfigService | Reduced AIM IHT relief |
| **NIC** | | | |
| Employee NIC rate | 8% | TaxConfigService | Salary sacrifice saving |
| Employer NIC rate | 15% | TaxConfigService | Employer contribution saving |
| Salary sacrifice NIC cap (from April 2029) | 2,000 | TaxConfigService | NIC advantage cap |
| **Marriage Allowance** | | | |
| Marriage Allowance transfer | 1,257 (10% of PA) | SpouseOptimisationService | MA calculation |
| Marriage Allowance saving | Up to 252 | SpouseOptimisationService | MA benefit |
| **Student Loan Thresholds** | | | |
| Plan 1 threshold | 24,990 | TaxConfigService | Student loan repayment |
| Plan 2 threshold | 27,295 | TaxConfigService | Student loan repayment |
| Plan 4 (Scotland) threshold | 31,395 | TaxConfigService | Student loan repayment |
| Plan 5 threshold | 25,000 | TaxConfigService | Student loan repayment |
| Postgraduate loan threshold | 21,000 | TaxConfigService | Postgraduate repayment |
| **Transfer Scan Thresholds** | | | |
| Cash excess threshold | Emergency target + 3 months | TransferRecommendationService | Excess cash trigger |
| Current account excess | 3x monthly expenditure | CashAccountAnalyzer | Current account alert |
| Interest rate switch | >= 0.5% difference | TransferRecommendationService | Rate review trigger |
| Rate expiry warning | Within 3 months | CashAccountAnalyzer | Expiry alert |
| Tax loss harvesting minimum | 500 unrealised loss | TransferRecommendationService | Loss crystallisation trigger |
| Bed & Pension net benefit | > 0 | TransferRecommendationService | Net benefit threshold |
| GIA to pension carry forward minimum | 5,000 GIA value | TransferRecommendationService | Minimum for carry forward scan |
| ISA consolidation trigger | > 2 ISAs across > 1 provider | TransferRecommendationService | Consolidation recommendation |
| Small balance threshold | < 1,000 | TransferRecommendationService | Small balance alert |
| Platform count consolidation | > 3 platforms | TransferRecommendationService | Platform consolidation trigger |
| Platform fee threshold | > 0.45% on > 50,000 | TransferRecommendationService | Flat-fee platform recommendation |
| Nomination staleness | > 2 years | TransferRecommendationService | Nomination review trigger |
| **Portfolio Health Thresholds** | | | |
| Asset class concentration | > 60% | PortfolioHealthService | Concentration warning |
| Geographic concentration (UK equity) | > 70% | PortfolioHealthService | UK bias warning |
| Single stock concentration | > 15% | PortfolioHealthService | Single stock warning |
| Sector concentration | > 30% of equity | PortfolioHealthService | Sector bias warning |
| Portfolio drift (medium) | > 5% absolute | PortfolioHealthService | Rebalancing trigger |
| Portfolio drift (high) | > 10% absolute | PortfolioHealthService | Urgent rebalancing trigger |
| Rebalance staleness | > 12 months | PortfolioHealthService | Rebalancing prompt |
| High OCF threshold | > 0.75% | PortfolioHealthService | Fee drag alert |
| OCF savings threshold | > 0.50% difference | PortfolioHealthService | Meaningful fee reduction |
| Portfolio average OCF threshold | > 0.50% | PortfolioHealthService | Overall fee assessment |
| **Compliance Thresholds** | | | |
| Lump sum PCA threshold | > 10,000 | ComplianceService | Pound cost averaging note |
| Annual review trigger | > 12 months | ComplianceService | Review prompt |
| Tax year end window | Jan 6 - Apr 5 | ComplianceService | Year-end urgency |
| **Life Event Thresholds** | | | |
| Windfall premium bonds threshold | >= 50,000 | LifeEventAssessmentService | Adds premium_bonds to wrappers |
| Approaching retirement | <= 5 years | LifeEventAssessmentService | Triggers glide path |
| Property purchase short-term block | < 3 years | GoalAssessmentService | Blocks equities |
| **Goal Timeline Thresholds** | | | |
| Goal timeline SHORT | < 2 years | GoalAssessmentService | Cash-only wrappers |
| Goal timeline MEDIUM | 2-5 years | GoalAssessmentService | ISA wrappers |
| Goal timeline LONG | 5-10 years | GoalAssessmentService | Equity wrappers |
| Goal timeline VERY_LONG | > 10 years | GoalAssessmentService | Full wrapper range |

---

## 16. Upcoming Tax Changes

| Change | Effective Date | Impact on Investment Engine | Engine Adjustment |
|--------|---------------|---------------------------|-------------------|
| AIM share BR: 100% to 50% | April 2026 | AIM shares become less attractive for IHT planning. Effective IHT rate on AIM shares becomes 20%. | Portfolio Health Check (PH8) warns AIM holders. Waterfall notes on EIS as alternative (retains 100% BR). |
| VCT income tax relief: 30% to 20% | April 2026 | VCT becomes less attractive vs EIS (30% retained). Reduced tax relief on new VCT subscriptions. | Step 11 (VCT) tax_relief calculation updated. Note appended to VCT recommendations in 2025/26. |
| BADR rate: 14% to 18% | April 2026 | Higher CGT on qualifying business disposals. Affects business sale life events. | Life event CGT assessment updated. Threshold constant updated. |
| Cash ISA limit: 20,000 to 12,000 (under-65s) | April 2027 | Reduced Cash ISA subscription limit. More surplus directed to S&S ISA. Over-65s retain 20,000. | ISA allowance split logic updated. Age-based Cash ISA limit. S&S ISA priority may increase for cash savers. |
| Pensions brought into IHT | April 2027 | Fundamental change to estate planning. Pension death benefits form part of the estate for IHT. Pension contributions for IHT mitigation become less effective. | Step 3 (Pension) IHT note appended. Spouse strategy 6 (IHT) updated. Estate planning cross-referral updated. |
| Pension access age: 55 to 57 | April 2028 | Delayed pension access. Affects retirement proximity calculations. Protected pension ages for existing members. | pension_access_age constant updated. Retirement proximity calculation adjusted. Note for users aged 55-57. |
| Salary sacrifice NIC cap: 2,000 | April 2029 | NIC advantage on salary sacrifice capped at first 2,000 of contributions. Reduced incentive for large salary sacrifice arrangements. | Step 3 (Pension) salary sacrifice note updated. Effective cost calculation adjusted for contributions above 2,000. |
| State pension age: 66 to 67 | By 2028 | Affects retirement planning calculations. Gap between private pension access and state pension widens. | Retirement proximity calculation adjusted. State pension deferral analysis updated. |

---

## 17. Config Message Key Reference

Complete index of all config message keys used across the v1.0 investment engine, grouped by phase.

### Readiness Messages (`readiness.*`)

| Key | Severity | Phase | Message |
|-----|----------|-------|---------|
| `readiness.block.date_of_birth` | block | Phase 1 | "Your date of birth is needed to assess age-related investment options like LISA eligibility and pension access." |
| `readiness.block.gross_annual_income` | block | Phase 1 | "Your income details are needed to calculate tax bands, pension allowances, and affordable contribution levels." |
| `readiness.block.risk_level` | block | Phase 1 | "Complete your risk profile so we can recommend investments suited to your comfort level." |
| `readiness.block.monthly_expenditure` | block | Phase 1 | "Your monthly expenditure is needed to calculate emergency fund requirements and affordable investment amounts." |
| `readiness.warn.employment_status` | warn | Phase 1 | "Adding your employment status helps us tailor emergency fund targets and pension recommendations." |
| `readiness.warn.protection_profile` | warn | Phase 1 | "You have dependents but no protection profile. Add your insurance details for better protection gap analysis." |
| `readiness.warn.dc_pensions` | warn | Phase 1 | "Adding your workplace pension details allows us to check employer matching and optimise pension contributions." |
| `readiness.warn.investment_accounts` | warn | Phase 1 | "Add your investment accounts so we can identify tax-efficient transfer opportunities like Bed & ISA." |
| `readiness.info.accounts` | info | Phase 1 | "Add your existing savings and investment accounts to receive transfer and optimisation recommendations." |
| `readiness.info.spouse_link` | info | Phase 1 | "Link your partner's account to unlock household tax optimisation strategies like CGT sharing and ISA coordination." |
| `readiness.info.life_events` | info | Phase 1 | "Add any upcoming life events (property purchase, retirement, new baby) to receive tailored investment advice." |
| `readiness.info.holdings` | info | Phase 1 | "Add your investment holdings to receive portfolio health analysis including concentration, drift, and fee analysis." |

### Life Event Messages (`life_events.*`)

| Key | Phase | Message |
|-----|-------|---------|
| `life_events.redundancy.block` | Phase 2a | "Following redundancy, focus on building liquid reserves. Avoid illiquid investments until your income stabilises." |
| `life_events.wedding.trigger` | Phase 2a | "Your wedding in {years} {year/years} means keeping {amount} accessible in cash." |
| `life_events.inheritance.trigger` | Phase 2a | "An inheritance of {amount} may push your estate closer to the IHT threshold. Review your nil-rate band position and consider tax-efficient wrappers for the proceeds." |
| `life_events.property_sale.trigger` | Phase 2a | "Property sale proceeds of {amount} may trigger a capital gains tax liability. Review your annual CGT exemption before reinvesting." |
| `life_events.business_sale.trigger` | Phase 2a | "Business sale proceeds of {amount} may trigger a capital gains tax liability. Review your annual CGT exemption before reinvesting." |
| `life_events.large_purchase.block` | Phase 2a | "Large purchase of {amount} within {years} {year/years} requires accessible funds." |
| `life_events.education_fees.trigger` | Phase 2a | "Education fees require predictable, accessible savings." |
| `life_events.windfall.trigger` | Phase 2a | "A windfall of {amount} gives you an opportunity to boost your tax-efficient investments." |
| `life_events.expense_events.trigger` | Phase 2a | "{Type} of {amount} expected within {years} {year/years}." |
| `life_events.new_baby.trigger` | Phase 2a | "Consider opening a Junior ISA ({limit}/year allowance) and reviewing your life cover." |
| `life_events.new_baby.child_benefit` | Phase 2a | "Income over 50,000 triggers High Income Child Benefit Charge." |
| `life_events.marriage.trigger` | Phase 2a | "Marriage opens up valuable tax planning opportunities. Link your partner's account to unlock household optimisation." |
| `life_events.divorce.trigger` | Phase 2a | "During divorce, interspousal asset transfers are CGT-exempt in the tax year of separation. Review beneficiaries across all accounts." |
| `life_events.serious_illness.block` | Phase 2a | "Focus on liquidity and protection claim eligibility. Avoid illiquid investments during this period." |
| `life_events.death_of_partner.block` | Phase 2a | "Your inherited ISA allowance and bereavement support eligibility should be reviewed. Avoid major financial decisions during this period." |
| `life_events.child_turning_18.trigger` | Phase 2a | "When your child turns 18, their Junior ISA converts to an adult ISA. Review your dependent count and protection needs." |
| `life_events.buying_a_home.trigger` | Phase 2a | "Keep your deposit funds in accessible accounts. Illiquid investments should wait until after completion." |
| `life_events.approaching_retirement.trigger` | Phase 2a | "You are {years} {year_word} from retirement. Your investment strategy should gradually shift towards lower-risk assets." |
| `life_events.career_change.trigger` | Phase 2a | "Career change -- ensure 6-9 months emergency fund during transition." |
| `life_events.separation.block` | Phase 2a | "During separation, review shared accounts and avoid major financial restructuring." |

### Safety Messages (`safety.*`)

| Key | Phase | Message |
|-----|-------|---------|
| `safety.debt.critical` | Phase 3 | "You have {type} debt at {rate}% interest. Repaying this should come before investing." |
| `safety.debt.high` | Phase 3 | "Your {type} debt at {rate}% costs more than typical investment returns. Consider splitting surplus between debt repayment and investing." |
| `safety.debt.promotional` | Phase 3 | "Your 0% promotional rate on {type} expires on {date}. Plan for the rate increase." |
| `safety.emergency_fund.critical` | Phase 3 | "Your emergency fund covers less than 1 month of expenses. Build this to at least {target} months before investing." |
| `safety.emergency_fund.high` | Phase 3 | "Your emergency fund covers {runway} months. We recommend {target} months. Investment limited to 50% of surplus." |
| `safety.emergency_fund.medium` | Phase 3 | "Your emergency fund covers {runway} months. Consider building to {target} months alongside investing." |
| `safety.emergency_fund.excess` | Phase 3 | "Your emergency fund exceeds the target by {months} months. Consider investing the excess of {amount}." |
| `safety.emergency_fund.no_expenditure` | Phase 3 | "Monthly expenditure data is missing. Please update your expenditure profile for accurate emergency fund assessment." |
| `safety.protection.dependents` | Phase 3 | "You have {count} {dependent_word}. Ensure adequate life cover and income protection before prioritising investments." |
| `safety.employer_match.always` | Phase 3 | "Your employer offers {percent}% pension matching. Contribute at least enough to get the full match, even if other safety checks apply." |
| `safety.debt.student_loan` | Phase 3 | "Student loan repayments are not traditional debt -- they are income-contingent and written off after {years} years. Do not prioritise repayment over investing." |
| `safety.income.negative_disposable` | Phase 3 | "Your expenditure exceeds your income. Review spending before committing to investments." |
| `safety.income.zero_net` | Phase 3 | "Net monthly income is zero or negative. Investment contributions cannot be calculated." |

### Waterfall Messages (`waterfall.*`)

| Key | Phase | Message |
|-----|-------|---------|
| `waterfall.no_surplus.note` | Phase 4 | "No surplus available for investment." |
| `waterfall.under_18.path` | Phase 4 | "Under 18: Junior ISA and GIA only." |
| `waterfall.trust.path` | Phase 4 | "Trust account: restricted to offshore bond and GIA." |
| `waterfall.lisa.recommend` | Phase 4 (Step 1) | "The 25% government bonus makes LISA the most effective wrapper for eligible first-time buyers or retirement savings." |
| `waterfall.lisa.age_cutoff` | Phase 4 (Step 1) | "Last year to contribute to LISA before age 50 cutoff." |
| `waterfall.lisa.maturity` | Phase 4 (Step 1) | "LISA withdrawals for first home require 12 months from first contribution." |
| `waterfall.lisa.over_50` | Phase 4 (Step 1) | "Over 50 -- cannot contribute to LISA." |
| `waterfall.lisa.no_property_goal` | Phase 4 (Step 1) | "No qualifying property goal and over 39 -- cannot open new LISA." |
| `waterfall.isa.recommend` | Phase 4 (Step 2) | "ISA shelters investments from income tax and capital gains tax with no lifetime limit." |
| `waterfall.isa.mpaa_primary` | Phase 4 (Step 2) | "With MPAA triggered, your pension annual allowance is reduced to {limit}. ISA becomes your primary tax-efficient wrapper." |
| `waterfall.isa.year_end_urgency` | Phase 4 (Step 2) | "Only {months} {month_word} left in the tax year with {remaining} ISA allowance remaining." |
| `waterfall.pension.basic_rate` | Phase 4 (Step 3) | "Pension contributions receive 20% tax relief." |
| `waterfall.pension.higher_rate` | Phase 4 (Step 3) | "Pension contributions receive 40% tax relief. Your net cost is {net} for a {gross} gross contribution." |
| `waterfall.pension.additional_rate` | Phase 4 (Step 3) | "Pension contributions receive 45% tax relief. Your net cost is {net} for a {gross} gross contribution." |
| `waterfall.pension.mpaa` | Phase 4 (Step 3) | "MPAA limits your money purchase annual allowance to {limit}." |
| `waterfall.pension.restricted` | Phase 4 (Step 3) | "Contribution limited due to low disposable income (below 5%)." |
| `waterfall.pension.non_earner` | Phase 4 (Step 3) | "Even without earnings, you can contribute up to 3,600 gross to a pension and receive 720 in tax relief." |
| `waterfall.pension.iht_change` | Phase 4 (Step 3) | "From April 2027, pensions will be brought within the scope of Inheritance Tax." |
| `waterfall.pension.salary_sacrifice` | Phase 4 (Step 3) | "Salary sacrifice saves National Insurance contributions in addition to income tax relief." |
| `waterfall.pension.nic_cap` | Phase 4 (Step 3) | "From April 2029, the National Insurance advantage on salary sacrifice will be capped at the first 2,000 of contributions." |
| `waterfall.premium_bonds.recommend` | Phase 4 (Step 4) | "Premium Bond prizes are tax-free. Effective for higher/additional rate taxpayers who have exceeded their Personal Savings Allowance." |
| `waterfall.premium_bonds.max_reached` | Phase 4 (Step 4) | "Premium Bonds maximum holding of {max} already reached." |
| `waterfall.nsi.recommend` | Phase 4 (Step 5) | "NS&I products are backed by HM Treasury, offering security for conservative allocations." |
| `waterfall.nsi.green` | Phase 4 (Step 5) | "Consider NS&I Green Savings Bonds to align with your ESG preferences." |
| `waterfall.offshore_bond.recommend` | Phase 4 (Step 6) | "Offshore bonds grow free of UK tax internally. Beneficial if you expect to be a basic rate taxpayer at encashment." |
| `waterfall.offshore_bond.clear_plan` | Phase 4 (Step 6) | "At your age, ensure a clear plan for bond encashment aligned with retirement income needs." |
| `waterfall.offshore_bond.segments` | Phase 4 (Step 6) | "Consider splitting into multiple segments for partial encashment flexibility." |
| `waterfall.onshore_bond.recommend` | Phase 4 (Step 7) | "Onshore bonds benefit from top-slicing relief, spreading gains across years held to reduce the tax band impact." |
| `waterfall.onshore_bond.top_slicing` | Phase 4 (Step 7) | "Top-slicing relief may reduce the effective tax rate on encashment." |
| `waterfall.carry_forward.recommend` | Phase 4 (Step 8) | "You have {amount} of unused pension allowance from previous years. Use oldest year first as it expires first." |
| `waterfall.carry_forward.regular_skip` | Phase 4 (Step 8) | "Pension carry forward is for lump sum contributions only." |
| `waterfall.seis.recommend` | Phase 4 (Step 9) | "SEIS offers 50% income tax relief on investments up to 200,000 per year. Minimum 3-year hold." |
| `waterfall.seis.cgt_reinvestment` | Phase 4 (Step 9) | "Capital gains reinvested into SEIS qualify for 50% CGT exemption (reinvestment relief)." |
| `waterfall.seis.risk_warning` | Phase 4 (Step 9) | "SEIS investments carry high risk of total capital loss. FSCS protection does not apply." |
| `waterfall.eis.recommend` | Phase 4 (Step 10) | "EIS offers 30% income tax relief on investments up to 1,000,000 per year. Minimum 3-year hold." |
| `waterfall.eis.cgt_deferral` | Phase 4 (Step 10) | "CGT deferral relief: defer unlimited capital gains by reinvesting into EIS." |
| `waterfall.eis.loss_relief` | Phase 4 (Step 10) | "Loss relief available if the company fails -- losses (net of 30% relief) can offset income or CGT." |
| `waterfall.eis.iht_br` | Phase 4 (Step 10) | "100% IHT Business Relief after 2 years of holding." |
| `waterfall.vct.recommend` | Phase 4 (Step 11) | "VCT offers {rate}% income tax relief on investments up to 200,000 per year. Tax-free dividends and no CGT on disposal." |
| `waterfall.vct.relief_change` | Phase 4 (Step 11) | "VCT income tax relief reduces from 30% to 20% from April 2026." |
| `waterfall.vct.no_iht_br` | Phase 4 (Step 11) | "VCTs do NOT qualify for IHT Business Relief (listed shares). Shares remain within the estate." |
| `waterfall.vct.risk_warning` | Phase 4 (Step 11) | "VCT investments carry high risk. Liquidity is limited -- secondary market discounts are common." |
| `waterfall.vct_eis_seis.experience_block` | Phase 4 (Steps 9-11) | "Venture capital schemes require advanced investment experience (your level: {level})." |
| `waterfall.vct_eis_seis.comfort_block` | Phase 4 (Steps 9-11) | "Venture capital schemes require comfort with capital loss and illiquidity." |
| `waterfall.vct_eis_seis.disposable_block` | Phase 4 (Steps 9-11) | "Disposable income below 10% -- insufficient buffer for high-risk investments." |
| `waterfall.gia.recommend` | Phase 4 (Step 12) | "GIA has no contribution limits or restrictions. Use tax-efficient strategies to minimise annual tax drag." |
| `waterfall.gia.accumulation` | Phase 4 (Step 12) | "Consider accumulation funds to minimise annual income distributions." |
| `waterfall.gia.cgt_harvesting` | Phase 4 (Step 12) | "Use CGT annual exemption through phased disposals." |
| `waterfall.gia.direct_gilts` | Phase 4 (Step 12) | "Consider holding direct gilts in GIA. Capital gains on gilts are CGT-exempt." |
| `waterfall.life_event_priority_boost` | Phase 4 | "Priority raised due to an upcoming life event." |

### Portfolio Health Messages (`portfolio.*`) — NEW

| Key | Phase | Message |
|-----|-------|---------|
| `portfolio.no_holdings` | Phase 5 | "Add your investment holdings to receive portfolio health analysis." |
| `portfolio.asset_concentration` | Phase 5 | "Your portfolio has {percent}% allocated to {class}. Concentration above 60% increases vulnerability." |
| `portfolio.geographic_concentration` | Phase 5 | "UK equities represent {percent}% of your equity holdings. The UK is approximately 4% of global market capitalisation." |
| `portfolio.single_stock` | Phase 5 | "{name} represents {percent}% of your total portfolio. Single stock risk." |
| `portfolio.sector_concentration` | Phase 5 | "{sector} represents {percent}% of your equity holdings." |
| `portfolio.drift_high` | Phase 5 | "Your portfolio has drifted significantly from your target allocation. Risk profile may no longer match." |
| `portfolio.drift_medium` | Phase 5 | "Your portfolio has drifted from target. Consider rebalancing." |
| `portfolio.not_rebalanced` | Phase 5 | "Portfolio not rebalanced in over 12 months." |
| `portfolio.high_ocf` | Phase 5 | "Your holding {name} charges {ocf}%. A passive alternative charges approximately {passive_ocf}%." |
| `portfolio.ocf_note` | Phase 5 | "Charges note: modest difference from passive alternative." |
| `portfolio.average_ocf` | Phase 5 | "Portfolio weighted average ongoing charge is {avg_ocf}%." |
| `portfolio.aim_br_change` | Phase 5 | "AIM shares Business Relief reduces from 100% to 50% from April 2026." |

### Transfer Messages (`transfers.*`)

| Key | Phase | Message |
|-----|-------|---------|
| `transfers.bed_and_isa.trigger` | Phase 6 | "Transfer {amount} from GIA to ISA to shelter future growth from tax." |
| `transfers.bed_and_isa.within_allowance` | Phase 6 | "Gains within the annual exemption -- no CGT payable." |
| `transfers.bed_and_isa.exceeds_allowance` | Phase 6 | "Gains exceed the annual exemption. Estimated CGT: {cgt}." |
| `transfers.bed_and_isa.net_loss` | Phase 6 | "No CGT liability as the holding is at a loss. Losses can be carried forward." |
| `transfers.tax_loss_harvesting.trigger` | Phase 6 | "Your GIA holding {name} has an unrealised loss of {loss}. Crystallising this loss can offset capital gains." |
| `transfers.tax_loss_harvesting.year_end` | Phase 6 | "Tax year ends 5 April. Crystallise losses before year end." |
| `transfers.tax_loss_harvesting.thirty_day_rule` | Phase 6 | "Do not repurchase the same shares within 30 days." |
| `transfers.psa_breach.trigger` | Phase 6 | "Your annual interest exceeds your tax-free allowance by {amount}. Consider moving savings to tax-free wrappers." |
| `transfers.dividend_breach.trigger` | Phase 6 | "Annual dividends exceed the {allowance} allowance. Prioritise moving highest-yielding GIA holdings into ISA." |
| `transfers.dividend_breach.accumulation_note` | Phase 6 | "Accumulation funds reinvest dividends but are still taxable in a GIA." |
| `transfers.bed_and_pension.trigger` | Phase 6 | "Sell GIA holdings and contribute proceeds to pension. Tax relief outweighs CGT cost." |
| `transfers.bed_and_pension.locked` | Phase 6 | "Pension contributions locked until age {access_age}." |
| `transfers.carry_forward_gia.trigger` | Phase 6 | "Use pension carry forward with GIA proceeds." |
| `transfers.carry_forward_gia.fifo` | Phase 6 | "Use oldest carry forward year first." |
| `transfers.isa_consolidation.trigger` | Phase 6 | "Consolidate ISA accounts via ISA-to-ISA transfer." |
| `transfers.isa_consolidation.current_year` | Phase 6 | "Current year subscriptions must be transferred in full." |
| `transfers.isa_consolidation.previous_year` | Phase 6 | "Previous year subscriptions can be partially transferred." |
| `transfers.account_consolidation.pensions` | Phase 6 | "Consider consolidating non-workplace pensions into a single SIPP." |
| `transfers.account_consolidation.small_balance` | Phase 6 | "Small balance may be eroded by platform fees." |
| `transfers.account_consolidation.db_warning` | Phase 6 | "Do NOT transfer defined benefit pensions without independent financial advice." |
| `transfers.platform_consolidation.count` | Phase 6 | "Investments across {count} platforms. Consolidating reduces complexity." |
| `transfers.platform_consolidation.fee` | Phase 6 | "A flat-fee platform could save approximately {saving}/year." |
| `transfers.death_benefit_nominations.review` | Phase 6 | "Review pension death benefit nominations." |
| `transfers.death_benefit_nominations.urgent` | Phase 6 | "Review pension nominations urgently following {event}." |
| `transfers.death_benefit_nominations.divorce` | Phase 6 | "Former spouses are not automatically removed from pension nominations." |
| `transfers.reit_wrapper.trigger` | Phase 6 | "REIT Property Income Distributions taxed as property income. Dividend allowance does not apply." |
| `transfers.interest_rate.switch` | Phase 6 | "Your {account} earns {current}%. A comparable account offers {best}%." |
| `transfers.interest_rate.expired` | Phase 6 | "Promotional rate expired on {account}." |
| `transfers.goal_optimisation.trigger` | Phase 6 | "Your goal \"{goal}\" is linked to a {current} wrapper. Consider moving to a {better}." |
| `transfers.excess_cash.trigger` | Phase 6 | "Your cash reserves exceed your emergency target by {amount}." |

### Spouse Messages (`spouse.*`)

| Key | Phase | Message |
|-----|-------|---------|
| `spouse.gate.no_spouse` | Phase 7 | "No linked spouse -- spouse optimisation not available." |
| `spouse.gate.not_married` | Phase 7 | "Not married or in civil partnership -- spouse optimisation not available." |
| `spouse.cgt_sharing.trigger` | Phase 7 | "Transfer holdings with gains to your spouse to use their {allowance} annual CGT exemption." |
| `spouse.cgt_sharing.lower_rate` | Phase 7 | "Transferring to your spouse saves an additional {saving} due to their lower CGT rate." |
| `spouse.cgt_sharing.exempt` | Phase 7 | "Interspousal transfers are CGT-exempt. Spouse then crystallises at their own rates." |
| `spouse.isa_coordination.trigger` | Phase 7 | "Your household has {remaining} of ISA allowance remaining. Gift money to your spouse to contribute to their ISA." |
| `spouse.isa_coordination.note` | Phase 7 | "You cannot contribute directly to your spouse's ISA -- gift the money for them to contribute." |
| `spouse.isa_coordination.capacity` | Phase 7 | "Combined household ISA capacity: {capacity} per year." |
| `spouse.psa_optimisation.trigger` | Phase 7 | "{spouseName} has a {spousePSA} Personal Savings Allowance vs your {userPSA}. Consider holding interest-bearing accounts in the name of the lower-rate partner." |
| `spouse.psa_optimisation.iht_note` | Phase 7 | "Transferring savings between spouses may have IHT implications if estate exceeds the nil-rate band." |
| `spouse.pension_coordination.higher_rate` | Phase 7 | "Pension contributions for {partner} ({band} rate) receive higher tax relief. Prioritise maximising their pension allowance first." |
| `spouse.pension_coordination.non_earner` | Phase 7 | "Your spouse can receive up to 3,600 gross pension contributions per year even with no earnings." |
| `spouse.pension_coordination.carry_forward` | Phase 7 | "Your spouse has {amount} in unused pension carry forward." |
| `spouse.pension_coordination.iht_note` | Phase 7 | "Pensions are outside the estate for IHT, adding a further benefit." |
| `spouse.pension_coordination.iht_change` | Phase 7 | "Note: pensions will be brought into IHT from April 2027." |
| `spouse.marriage_allowance.eligible` | Phase 7 | "Transfer {amount} of unused personal allowance to your basic rate partner, saving up to {saving} per year." |
| `spouse.marriage_allowance.not_available` | Phase 7 | "Higher earner is above basic rate -- Marriage Allowance not available." |
| `spouse.marriage_allowance.backdate` | Phase 7 | "Apply through HMRC. Can be backdated up to 4 years." |
| `spouse.iht_planning.trigger` | Phase 7 | "Your combined estate exceeds the {threshold} threshold by {excess}. Consider estate planning strategies." |
| `spouse.iht_planning.pension_note` | Phase 7 | "Pensions are normally outside the estate for IHT." |
| `spouse.iht_planning.cross_referral` | Phase 7 | "Consider the estate planning module for detailed analysis." |
| `spouse.death_benefit_nominations.review` | Phase 7 | "Both partners should review pension death benefit nominations regularly." |
| `spouse.death_benefit_nominations.divorce` | Phase 7 | "Former spouses are not automatically removed from pension nominations after divorce." |
| `spouse.beneficial_ownership.form17` | Phase 7 | "Joint accounts are automatically split 50:50 for tax. Form 17 allows actual ownership split." |
| `spouse.beneficial_ownership.requirement` | Phase 7 | "Form 17 must reflect actual beneficial ownership." |

### Compliance Messages (`compliance.*`) — NEW

| Key | Phase | Message |
|-----|-------|---------|
| `compliance.consumer_duty.complex_product` | Phase 8 | "This involves a complex financial product. Consider seeking independent financial advice." |
| `compliance.consumer_duty.vulnerability` | Phase 8 | "Given your circumstances, you may benefit from speaking to an independent financial adviser." |
| `compliance.consumer_duty.high_risk` | Phase 8 | "Venture capital schemes involve a high risk of losing your entire investment." |
| `compliance.suitability.low_risk_equity` | Phase 8 | "Based on your low risk profile, consider cash or bond funds within these wrappers." |
| `compliance.suitability.high_risk_cash` | Phase 8 | "Based on your higher risk tolerance, minimise cash beyond emergency fund." |
| `compliance.suitability.retirement_equity` | Phase 8 | "With {years} years to retirement, high equity allocation carries sequence-of-returns risk." |
| `compliance.review.tax_year_end` | Phase 8 | "Tax year ends 5 April. Review ISA, pension, and CGT position." |
| `compliance.review.annual_due` | Phase 8 | "Over 12 months since your last review. Circumstances may have changed." |
| `compliance.review.post_event` | Phase 8 | "Your recent {event} may have changed your financial priorities." |
| `compliance.pca.low_risk` | Phase 8 | "Consider investing in stages over 6-12 months (pound cost averaging)." |
| `compliance.pca.evidence` | Phase 8 | "Evidence suggests lump sum investing outperforms in most historical periods." |

### Conflict Messages (`conflicts.*`)

| Key | Phase | Message |
|-----|-------|---------|
| `conflicts.surplus.partial` | Phase 9 | "Partially funded due to surplus constraints." |
| `conflicts.surplus.deferred` | Phase 9 | "Insufficient surplus after higher-priority allocations." |
| `conflicts.isa.partial` | Phase 9 | "ISA allowance partially allocated." |
| `conflicts.isa.deferred` | Phase 9 | "ISA allowance exhausted by higher-priority ISA recommendations." |
| `conflicts.pension.deferred` | Phase 9 | "Pension annual allowance exhausted." |
| `conflicts.life_event.blocked` | Phase 9 | "Blocked by life event." |
| `conflicts.general.waterfall_vs_conflict` | Phase 9 | "Waterfall order (tax efficiency) differs from conflict order (tax relief) by design." |

### Review Messages (`review.*`) — NEW

| Key | Phase | Message |
|-----|-------|---------|
| `review.annual.headline` | Phase 8 | "Annual investment review due" |
| `review.annual.explanation` | Phase 8 | "Over 12 months since your last review. Your circumstances, tax position, and market conditions may have changed." |
| `review.post_event.headline` | Phase 8 | "Review following {event}" |
| `review.post_event.explanation` | Phase 8 | "Your recent {event_description} may have changed your financial priorities. Review your investment strategy." |
| `review.tax_year_end.headline` | Phase 8 | "Tax year end approaching" |
| `review.tax_year_end.explanation` | Phase 8 | "Tax year ends 5 April. Unused ISA and CGT allowances cannot be carried forward." |

---

*End of Part 2 — Sections 7-17*
*Part 1 covers Sections 1-6: Engine Pipeline Overview, User Context Data Inputs, Phase 1 Data Readiness Gate, Phase 2a Life Event Assessment, Phase 2b Goal Assessment, Phase 3 Safety Checks.*
