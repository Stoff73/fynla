# Investment Recommendation Engine: Decision Tree & Message Reference

> Complete mapping of every decision path, user-facing message, and the context data that drives each output.
>
> **Engine version:** v0.8.0 | **Last updated:** 2026-02-11 | **Branch:** `investmentFix`

---

## Table of Contents

1. [Engine Pipeline Overview](#1-engine-pipeline-overview)
2. [User Context: Data Inputs](#2-user-context-data-inputs)
3. [Phase 1: Data Readiness Gate](#3-phase-1-data-readiness-gate)
4. [Phase 2a: Life Event Assessment](#4-phase-2a-life-event-assessment)
5. [Phase 2b: Goal Assessment](#5-phase-2b-goal-assessment)
6. [Phase 3: Safety Checks](#6-phase-3-safety-checks)
7. [Phase 4: Contribution Waterfall (9 Steps)](#7-phase-4-contribution-waterfall-9-steps)
8. [Phase 5: Transfer Scans](#8-phase-5-transfer-scans)
9. [Phase 6: Spouse Optimisation](#9-phase-6-spouse-optimisation)
10. [Phase 7: Conflict Resolution](#10-phase-7-conflict-resolution)
11. [Output Formatting & Priority](#11-output-formatting--priority)
12. [Thresholds & Constants Reference](#12-thresholds--constants-reference)
13. [Config Message Key Reference](#13-config-message-key-reference)

---

## 1. Engine Pipeline Overview

```
User Request
    |
    v
[Phase 1] DataReadinessService ──── can_proceed = false? ──> STOP (return readiness blocks only)
    |
    | can_proceed = true
    v
[Phase 2a] LifeEventAssessmentService ──> modifiers (blocks, triggers, prioritised wrappers)
[Phase 2b] GoalAssessmentService ──> goal modifiers (blocked wrappers, suitable wrappers)
    |
    v
[Phase 3] SafetyCheckService ──> safety blocks + surplus adjustments
    |
    v
[Phase 4] ContributionWaterfallService ──> contribution recommendations (9-step priority order)
    |
    v
[Phase 5] TransferRecommendationService ──> transfer recommendations (7 scans)
    |
    v
[Phase 6] SpouseOptimisationService ──> spouse recommendations (6 strategies)
    |
    v
[Phase 7] ConflictResolutionService ──> merged, deduplicated, conflict-resolved recommendations
    |
    v
[Output] RecommendationOutputFormatter ──> sorted, formatted API response
```

**Key principle:** Each phase can modify the surplus available to subsequent phases. Safety blocks can reduce surplus to zero, preventing any contribution recommendations from being generated.

---

## 2. User Context: Data Inputs

**Service:** `UserContextBuilder` | **File:** `app/Services/Investment/Recommendation/UserContextBuilder.php`

UserContextBuilder produces no user-facing messages. It assembles the data context consumed by every other service. Understanding what data feeds each decision is essential for tracing why a particular output appears.

### 2.1 Personal Profile

| Field | Source | Used By |
|-------|--------|---------|
| `age` | Calculated from `user.date_of_birth` | LISA eligibility, pension age gate, under-18 path, retirement proximity |
| `date_of_birth` | `user.date_of_birth` | Phase 1 readiness gate |
| `gender` | `user.gender` | Actuarial calculations |
| `marital_status` | `user.marital_status` | Spouse optimisation gate |
| `employment_status` | `user.employment_status` (normalised: part_time/other -> employed) | Emergency fund target, pension recommendations |
| `retirement_age` | `user.retirement_age` or default 67 | Years to retirement calculation |
| `years_to_retirement` | `retirement_age - age` | Approaching retirement detection, glide path |
| `is_homeowner` | Boolean from properties | Goal assessment (property purchase) |
| `has_dependents` | `familyMembers->count() > 0` | Protection safety check |
| `number_of_dependents` | Count of family members | Protection message |
| `youngest_dependent_age` | Min age of family members | Protection personal context |
| `uk_resident` | `user.uk_resident` | LISA eligibility |

### 2.2 Financial Profile

| Field | Source | Used By |
|-------|--------|---------|
| `gross_annual_income` | Sum of 7 income sources: employment, self-employment, rental, dividend, interest, other, trust | Phase 1 gate, tax band derivation |
| `net_monthly_income` | `gross * 0.7 / 12` (fallback estimate) | Disposable income calculation |
| `monthly_expenditure` | `user.monthly_expenditure` | Emergency fund, affordability |
| `disposable_income` | `net_monthly_income - monthly_expenditure` | Surplus for waterfall |
| `disposable_percent` | `(disposable_income / net_monthly_income) * 100` | Pension affordability tier, VCT gate |
| `tax_band` | Derived from income: basic (<50,270), higher (<125,140), additional (>=125,140) | Tax relief rates, PSA, dividend allowance |
| `personal_allowance` | 12,570 (tapered above 100k: reduces by 1 for every 2 over 100k) | Marriage Allowance |
| `pension_annual_allowance` | 60,000 (tapered if threshold income >200k AND adjusted income >260k, floor 10k) | Pension contribution cap |
| `relevant_uk_earnings` | Employment + self-employment income only | Pension contribution limit |

### 2.3 Risk Profile

| Field | Source | Used By |
|-------|--------|---------|
| `risk_level` | `riskProfile.risk_level` | Phase 1 gate, Cash ISA transfer gate |
| `risk_tolerance` | `riskProfile.risk_tolerance` | Risk assessment |
| `risk_capacity` | Mapped from `capacity_for_loss_percent`: <=20%=low, <=50%=medium, >50%=high | VCT eligibility |
| `investment_experience` | `riskProfile.investment_experience` | Bond eligibility, VCT eligibility |
| `comfortable_with_illiquidity` | `riskProfile.comfortable_with_illiquidity` | VCT gate |
| `comfortable_with_capital_loss` | `riskProfile.comfortable_with_capital_loss` | VCT gate |
| `esg_preference` | `riskProfile.esg_preference` | NS&I green bond note |

### 2.4 Debt Profile

| Field | Source | Used By |
|-------|--------|---------|
| `debts` | All debts excluding mortgage and student_loan | Safety check (debt assessment) |
| `high_interest_debts` | Debts with `interest_rate > 15%` | Critical debt block |
| `medium_interest_debts` | Debts with `interest_rate 5-15%` | High debt warning |
| `promotional_rate_expiry` | From debt records | Promotional rate alert |

### 2.5 Emergency Fund Profile

| Field | Source | Used By |
|-------|--------|---------|
| `total` | Sum of `is_emergency_fund` savings + cash accounts | Emergency fund tier assessment |
| `runway` | `total / monthly_expenditure` | Emergency fund tier (months of cover) |
| `target` | Employment-based: self_employed=9, unemployed=6, retired=3, employed=6 months | Emergency fund gap calculation |
| `shortfall_amount` | `(target * monthly_expenditure) - total` | Emergency fund recommendation amount |

### 2.6 Allowances Profile

| Field | Source | Used By |
|-------|--------|---------|
| `isa_remaining` | 20,000 - current year ISA subscriptions | ISA waterfall step, Bed & ISA |
| `lisa_remaining` | 4,000 - current year LISA subscriptions | LISA waterfall step |
| `pension_aa_remaining` | Via AnnualAllowanceChecker | Pension waterfall step |
| `carry_forward_available` | From pension carry forward records | Carry forward step |
| `is_tapered` | Boolean (pension AA tapered) | Pension personal context |
| `mpaa_triggered` | Boolean | ISA priority boost, pension cap |
| `cgt_allowance_remaining` | 3,000 - realised gains | CGT sharing, Bed & ISA |
| `psa_remaining` | PSA by band: basic=1000, higher=500, additional=0 | PSA breach scan |
| `dividend_allowance_remaining` | 500 - dividends received | Dividend breach scan |

### 2.7 Spouse Profile (only if married/civil_partnership AND spouse exists)

| Field | Source | Used By |
|-------|--------|---------|
| `spouse.name` | `spouse.name` | Spouse strategy messages |
| `spouse.gross_annual_income` | Calculated from spouse income sources | Spouse tax band |
| `spouse.tax_band` | Derived from spouse income | PSA optimisation, pension coordination |
| `spouse.isa_remaining` | Spouse's ISA allowance remaining | ISA coordination |
| `spouse.pension_aa_remaining` | Spouse's pension AA | Pension coordination |
| `spouse.carry_forward_available` | Spouse's carry forward | Carry forward strategy |
| `spouse.personal_allowance_used` | Whether spouse uses full PA | Marriage Allowance |

---

## 3. Phase 1: Data Readiness Gate

**Service:** `DataReadinessService` | **File:** `app/Services/Investment/Recommendation/DataReadinessService.php`

The readiness gate runs six sequential phases. If any Phase 1 check returns a `block`, `can_proceed = false` and all subsequent engine phases are skipped.

### Decision Tree

```
Phase 1 Prerequisites (any BLOCK = engine stops)
|
+-- date_of_birth is null?
|   YES -> BLOCK: "Your date of birth is needed..."
|
+-- gross_annual_income <= 0?
|   YES -> BLOCK: "Your income details are needed..."
|
+-- No risk profile or risk_level is null?
|   YES -> BLOCK: "Complete your risk profile..."
|
|   ANY BLOCK above? -> can_proceed = false, STOP
|
Phase 2 Financial Data
|
+-- monthly_expenditure is null or <= 0?
|   YES -> BLOCK: "Your monthly expenditure is needed..."
|
+-- employment_status is null?
|   YES -> WARN: "Adding your employment status helps..."
|
Phase 3 Safety Data
|
+-- Has family members BUT no protection profile?
|   YES -> WARN: "You have dependents but no protection profile..."
|
Phase 4 Contribution Waterfall Data
|
+-- No DC pensions?
|   YES -> WARN: "Adding your workplace pension details..."
|
+-- No investment accounts AND no savings accounts?
|   YES -> INFO: "Add your existing savings and investment accounts..."
|
Phase 5 Transfer Scan Data
|
+-- No investment accounts?
|   YES -> WARN: "Add your investment accounts..."
|
Phase 6 Spouse Data
|
+-- Married/civil_partnership but no spouse linked?
|   YES -> INFO: "Link your partner's account..."
|
+-- Always:
    INFO: "Add any upcoming life events..."
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

---

## 4. Phase 2a: Life Event Assessment

**Service:** `LifeEventAssessmentService` | **File:** `app/Services/Investment/Recommendation/LifeEventAssessmentService.php`

Life events produce **modifiers** that affect downstream phases: blocking wrappers, prioritising wrappers, requiring liquidity, overriding affordability, and generating sub-action recommendations.

### 4.1 Derived Events (Auto-Detected)

These are not stored in the database -- they are inferred from user context.

```
years_to_retirement <= 5 AND > 0?
    YES -> derive "approaching_retirement" event

Any life event is a windfall source (inheritance, gift_received, bonus,
lottery_windfall, property_sale, business_sale, pension_lump_sum) with amount > 0?
    YES -> derive "windfall" event
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
+-- type = "gift_received" / "bonus" / "lottery_windfall" / "pension_lump_sum"
|   |-- action: TRIGGER
|   |-- prioritised_wrappers: pension, stocks_shares_isa
|   |-- Message: [LE7]
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
    |-- action: BLOCK
    |-- blocked_wrappers: offshore_bond, onshore_bond, vct, eis, seis
    |-- prioritised_wrappers: lifetime_isa, cash_isa
    |-- liquidity_priority: true
    |-- Sub-actions: review_deposit_savings, (if age 18-39) consider_lisa
    |-- Message: [LE15]
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

"windfall" (lump sum from qualifying source):
|-- action: TRIGGER
|-- prioritised_wrappers: pension, stocks_shares_isa (+ premium_bonds if >= 50,000)
|-- contribution_type: lump_sum
|-- Sub-action (if property_sale/business_sale): CGT phasing note
|-- Message: [LE17]
```

### 4.4 Life Event Conflict Rules

- **BLOCKs always beat TRIGGERs:** If any modifier blocks a wrapper, trigger modifiers that prioritise that wrapper have it removed.
- **Spouse optimisation:** If any modifier has `blocks_spouse_optimisation = true`, any modifier with `unlocks_spouse_optimisation = true` is overridden.

### 4.5 Life Event Message Reference

| # | Event Type | Config Key | Message |
|---|-----------|------------|---------|
| LE1 | redundancy | `life_events.redundancy.block` | "Following redundancy, focus on building liquid reserves. Avoid illiquid investments until your income stabilises." |
| LE2 | wedding (<=2yr) | *(inline)* | "Your wedding in {years} {year/years} means keeping {amount} accessible in cash." |
| LE3 | inheritance | *(inline)* | "An inheritance of {amount} may push your estate closer to the IHT threshold. Review your nil-rate band position and consider tax-efficient wrappers for the proceeds." |
| LE4 | property_sale / business_sale | *(inline)* | "{Type} proceeds of {amount} may trigger a capital gains tax liability. Review your annual CGT exemption before reinvesting." |
| LE5 | large_purchase (<=2yr) | *(inline)* | "Large purchase of {amount} within {years} {year/years} requires accessible funds." |
| LE6 | education_fees | *(inline)* | "Education fees require predictable, accessible savings." |
| LE7 | income events | *(inline)* | "{Type} of {amount} could be directed to tax-efficient wrappers." |
| LE8 | expense events (<=1yr) | *(inline)* | "{Type} of {amount} expected within {years} {year/years}." |
| LE9 | new_baby | `life_events.new_baby.trigger` | "Consider opening a Junior ISA ({limit}/year allowance) and reviewing your life cover." |
| LE9a | new_baby (income >50k) | `life_events.new_baby.child_benefit` | "Income over 50,000 triggers High Income Child Benefit Charge." |
| LE10 | marriage | `life_events.marriage.trigger` | "Marriage opens up valuable tax planning opportunities. Link your partner's account to unlock household optimisation." |
| LE11 | divorce | `life_events.divorce.trigger` | "During divorce, interspousal asset transfers are CGT-exempt in the tax year of separation. Review beneficiaries across all accounts." |
| LE12 | serious_illness | `life_events.serious_illness.block` | "Focus on liquidity and protection claim eligibility. Avoid illiquid investments during this period." |
| LE13 | death_of_partner | `life_events.death_of_partner.block` | "Your inherited ISA allowance and bereavement support eligibility should be reviewed. Avoid major financial decisions during this period." |
| LE14 | child_turning_18 | `life_events.child_turning_18.trigger` | "When your child turns 18, their Junior ISA converts to an adult ISA. Review your dependent count and protection needs." |
| LE15 | buying_a_home | `life_events.buying_a_home.trigger` | "Keep your deposit funds in accessible accounts. Illiquid investments should wait until after completion." |
| LE16 | approaching_retirement | `life_events.approaching_retirement.trigger` | "You are {years} {year_word} from retirement. Your investment strategy should gradually shift towards lower-risk assets." |
| LE17 | windfall | `life_events.windfall.trigger` | "A windfall of {amount} gives you an opportunity to boost your tax-efficient investments." |

### 4.6 Life Event Sub-Action Messages

| Event | Sub-Action | Message |
|-------|-----------|---------|
| redundancy | review_emergency_fund (amount > 0) | "Your redundancy payment of {amount} should be prioritised for emergency reserves until re-employment is secured." |
| redundancy | review_emergency_fund (amount = 0) | "Build emergency reserves to 9-12 months of expenditure during the transition period." |
| redundancy | review_protection | "Review whether existing income protection and critical illness cover remains in force without employer sponsorship." |
| approaching_retirement | review_pension_access_options | "With {years} {year/years} to retirement, now is the time to compare drawdown flexibility against annuity guaranteed income." |
| approaching_retirement | review_risk_profile | "With {years} {year/years} to retirement, consider a glide path to gradually reduce equity exposure and protect accumulated gains." |
| new_baby | open_junior_isa | "Junior ISA allowance is {limit} per year." |
| new_baby | review_life_cover | "Additional dependent increases life cover requirements." |
| new_baby | check_child_benefit | "Income over 50,000 triggers High Income Child Benefit Charge." |
| new_baby | review_will | "Update beneficiaries to include new child." |
| marriage | link_spouse_account | "Link partner accounts to enable household tax optimisation." |
| marriage | review_beneficiaries | "Update beneficiaries on all policies and pensions." |
| marriage | check_marriage_allowance | "Marriage Allowance can save up to 252 per year for eligible couples." |
| divorce | cgt_exemption_review | "Interspousal transfers are CGT-exempt in the tax year of separation. Use this window for tax-efficient asset division." |
| divorce | review_all_beneficiaries | "Update beneficiaries on all policies, pensions, and accounts." |
| divorce | review_estate_plan | "Review and update will and estate planning." |
| serious_illness | check_critical_illness_claim | "Check critical illness policy eligibility." |
| serious_illness | review_income_protection | "Check income protection policy provisions." |
| serious_illness | review_estate_plan | "Review estate planning as a priority." |
| death_of_partner | review_inherited_assets | "Assess inherited assets and any IHT liability." |
| death_of_partner | claim_bereavement_support | "Check eligibility for Bereavement Support Payment." |
| death_of_partner | review_all_beneficiaries | "Update beneficiaries on all policies and pensions." |
| death_of_partner | transfer_isa_allowance | "Inherited ISA allowance (Additional Permitted Subscription) may be available." |
| child_turning_18 | convert_junior_isa | "Junior ISA automatically converts to adult ISA at 18." |
| child_turning_18 | review_dependent_status | "Reassess dependent status for protection calculations." |
| buying_a_home | review_deposit_savings | "Ensure deposit is in accessible accounts." |
| buying_a_home | consider_lisa (age 18-39) | "LISA offers 25% government bonus on first home purchases up to 450,000." |
| career_change | review_emergency_fund | "Ensure 6-9 months emergency fund during transition." |
| career_change | review_pension_transfer | "Consider consolidating previous employer pension." |
| windfall (property/business sale) | cgt_phasing | "Your {source} of {amount} may trigger CGT. Check your annual exemption and consider phasing disposals across tax years if possible." |

---

## 5. Phase 2b: Goal Assessment

**Service:** `GoalAssessmentService` | **File:** `app/Services/Investment/Recommendation/GoalAssessmentService.php`

Goals produce **wrapper modifiers**: lists of suitable wrappers, blocked wrappers, and notes that feed into the waterfall.

### Constants

| Constant | Value |
|----------|-------|
| `TIMELINE_SHORT` | < 2 years |
| `TIMELINE_MEDIUM` | 2-5 years |
| `TIMELINE_LONG` | 5-10 years |
| `TIMELINE_VERY_LONG` | > 10 years |
| `DEFAULT_INVESTMENT_RETURN` | 5% |

### Decision Tree

```
For each active goal:
|
+-- type = "property_purchase"
|   |
|   +-- age 18-39 AND price < 450,000 AND first_time_buyer?
|   |   YES -> note: "Eligible for LISA 25% government bonus on first home purchase."
|   |   NO (price >= 450,000) -> note: "Property price of {price} exceeds the LISA limit of 450,000."
|   |
|   +-- years_to_goal < 3?
|       YES -> note: "Property purchase within 3 years -- keep deposit in cash or near-cash."
|              blocked_wrappers: stocks_shares_isa, pension, offshore_bond, onshore_bond, vct, eis, seis
|
+-- type = "retirement"
|   |
|   +-- years_to_retirement <= 10?
|   |   YES -> note: "With {years} years to retirement, consider a glide path reducing equity exposure."
|   |
|   +-- years_to_retirement <= 5?
|       YES -> note: "Within 5 years of retirement -- review pension access strategy (drawdown vs annuity)."
|
+-- type = "debt_repayment"
|   |
|   +-- debt_interest_rate > 5% (DEFAULT_INVESTMENT_RETURN)?
|       YES -> note: "Debt interest at {rate}% exceeds expected investment returns of 5.0%. Prioritise debt repayment."
|              priority elevated to HIGH
|
+-- type = "emergency_fund"
|   |-- note: "Emergency fund must remain in instant-access cash accounts."
|   |-- suitable_wrappers: savings_account, cash_isa
|   |-- blocked_wrappers: everything else
|
+-- type = "education"
|   |
|   +-- timeline SHORT (< 2 years)?
|   |   YES -> note: "Education fees due within 2 years -- keep in cash."
|   |   NO  -> note: "Longer-term education savings can benefit from equity growth."
|
+-- type = (generic/other)
    |
    +-- timeline SHORT  -> suitable: savings_account, cash_isa
    +-- timeline MEDIUM -> suitable: cash_isa, stocks_shares_isa
    +-- timeline LONG   -> suitable: stocks_shares_isa, pension
    +-- timeline VERY_LONG -> suitable: stocks_shares_isa, pension
```

### Timeline-Based Wrapper Blocking (All Goal Types)

| Timeline | Blocked Wrappers | Reason |
|----------|-----------------|--------|
| SHORT (< 2 years) | offshore_bond, onshore_bond, vct, eis, seis, pension | Too illiquid for short-term goals |
| MEDIUM (2-5 years) | offshore_bond, onshore_bond, vct, eis, seis | Too illiquid for medium-term goals |
| LONG / VERY_LONG | *(none blocked)* | All wrappers suitable |

### Implicit Emergency Fund Goal

If no explicit `emergency_fund` goal exists AND the user has an emergency fund shortfall, the engine automatically creates an implicit emergency fund goal with:
- Suitable wrappers: savings_account, cash_isa
- All other wrappers blocked
- Priority: HIGH

---

## 6. Phase 3: Safety Checks

**Service:** `SafetyCheckService` | **File:** `app/Services/Investment/Recommendation/SafetyCheckService.php`

Safety checks can **reduce the surplus available to the waterfall** (Phase 4). Critical blocks can reduce it to zero.

### Constants

| Constant | Value |
|----------|-------|
| `DEFAULT_EXPECTED_RETURN` | 0.05 (5%) |
| `MORTGAGE_EXCEPTION_RATE` | 3.0 (mortgages below this excluded) |

### Decision Tree

```
Guard: net_monthly_income <= 0?
    YES -> disposable_income = 0, surplus = 0
    NO  -> continue

CHECK 1: HIGH-INTEREST DEBT
|
For each debt (excluding mortgage, student_loan):
|
+-- interest_rate > 15%?
|   YES -> CRITICAL [S1]
|   |-- action: redirect_100_percent_to_debt
|   |-- surplus effect: remaining = 0
|   |-- personal_context: "Your {type} balance of {balance} at {rate}% costs
|   |                      {annualCost}/year in interest -- more than typical
|   |                      investment returns of 5%. Clear this before investing."
|
+-- interest_rate 5-15% AND annualDebtCost > expectedReturn?
|   YES -> HIGH [S2]
|   |-- action: reduce_surplus_50_percent
|   |-- surplus effect: remaining -= 50%
|   |-- personal_context: "Your {type} of {balance} at {rate}% costs {annualDebtCost}/year
|   |                      vs estimated {expectedReturn} investment return. Split your
|   |                      {surplus} surplus between debt repayment and investing."
|
+-- promotional_rate_expiry within 6 months?
    YES -> MEDIUM [S3]
    |-- action: warn_rate_increase
    |-- surplus effect: none

CHECK 2: EMERGENCY FUND
|
+-- monthly_expenditure <= 0?
|   YES -> INFO [S4] "Monthly expenditure data is missing..."
|   STOP emergency fund check
|
+-- runway < 1 month?
|   YES -> CRITICAL [S5]
|   |-- action: block_non_essential_investment
|   |-- surplus effect: capped = 0
|   |-- personal_context: "Your liquid reserves of {totalSavings} cover less than
|   |                      1 month against {monthlyExp}/month expenditure. As
|   |                      {employmentLabel}, you need {target} months of cover
|   |                      ({targetAmount}), a shortfall of {shortfall}."
|
+-- runway 1-3 months?
|   YES -> HIGH [S6]
|   |-- action: cap_investment_50_percent
|   |-- surplus effect: capped = surplus * 0.5
|   |-- personal_context: "Your {totalSavings} in reserves covers {runway} months
|   |                      of your {monthlyExp}/month expenditure. Build to {target}
|   |                      months ({targetAmount}) before committing fully to investments."
|
+-- runway 3 months to target?
|   YES -> MEDIUM [S7]
|   |-- action: parallel_recommendation
|   |-- surplus effect: none (invest alongside building)
|   |-- personal_context: "Your {totalSavings} covers {runway} months. You can invest
|   |                      alongside building your fund to the {target}-month target
|   |                      of {targetAmount}."
|
+-- runway > target + 3 months?
    YES -> INFO [S8]
    |-- action: transfer_excess
    |-- personal_context: "Your {totalSavings} exceeds the {target}-month target by
                           {excessMonths} months. Consider investing the excess
                           {excessAmount} for better long-term returns."

CHECK 3: PROTECTION GAPS
|
+-- has_dependents = true?
|   YES -> MEDIUM [S9]
|   |-- action: review_protection
|   |-- personal_context: "With {dependentCount} {dependentWord}{ageNote} and {income}
|                           household income, ensure life cover and income protection
|                           are adequate before prioritising investments."
|
+-- has_dependents = false?
    SKIP (no protection check)

EMPLOYER MATCH (always surfaced if applicable):
|
+-- Any DC pension with employer_contribution_percent > 0?
    YES -> [S10] (always shown, regardless of other safety checks)
```

### Emergency Fund Target by Employment Status

| Employment Status | Target (months) |
|-------------------|-----------------|
| self_employed | 9 |
| unemployed | 6 |
| retired | 3 |
| employed (default) | 6 |

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
| S9 | Has dependents | `medium` | `safety.protection.dependents` | "You have {count} {dependent_word}. Ensure adequate life cover and income protection before prioritising investments." |
| S10 | Employer match available | *(always)* | `safety.employer_match.always` | "Your employer offers {percent}% pension matching. Contribute at least enough to get the full match, even if other safety checks apply." |

### Surplus Impact Summary

| Safety Block | Surplus Effect |
|-------------|---------------|
| Critical debt (>15%) | Surplus = 0 |
| High debt (5-15%) | Surplus reduced by 50% |
| Emergency fund critical (<1mo) | Surplus capped at 0 |
| Emergency fund low (1-3mo) | Surplus capped at 50% |
| All others | No surplus reduction |

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

---

## 7. Phase 4: Contribution Waterfall (9 Steps)

**Service:** `ContributionWaterfallService` | **File:** `app/Services/Investment/Recommendation/ContributionWaterfallService.php`

The waterfall allocates the **remaining surplus** (after safety check deductions) across wrappers in strict priority order. Each step consumes as much surplus as it can (up to wrapper limits), then passes the remainder to the next step.

### Pre-Waterfall Guards

```
surplus <= 0?
    YES -> return empty + note: "No surplus available for investment." [W0]

age < 18?
    YES -> run Under-18 path (Junior ISA + GIA only)

ownership_type = "trust"?
    YES -> run Trust path (offshore bond + GIA)
```

### Under-18 Path Messages

| Field | Value |
|-------|-------|
| Decision note | "Under 18: Junior ISA and GIA only." |
| Junior ISA headline | "Junior ISA" |
| Junior ISA explanation | "Tax-free savings for under-18s with a {juniorIsaLimit} annual limit." |
| Junior ISA note | "Converts to adult ISA at 18." |
| GIA headline (if remaining) | "General Investment Account (minor)" |
| GIA explanation | "Remaining funds invested via GIA in the parent/guardian name." |

### Trust Path Messages

| Trust Type | Decision Note |
|-----------|--------------|
| Bare trust | "Bare trust -- using beneficiary's marginal rate." |
| Discretionary | "Trust account -- income taxed at {rate}%, gains at {rate}%." |

| Field | Value |
|-------|-------|
| Offshore bond headline | "Offshore bond for trust" |
| Offshore bond explanation | "Offshore bonds grow tax-deferred within the trust, deferring income and gains until encashment." |
| Offshore bond note | "Trust income rate is 45%. Offshore bond defers this." |
| GIA headline | "General Investment Account (trust)" |
| GIA explanation | "Remaining trust funds in GIA. {Bare trust uses beneficiary's rate / Trust rates apply.}" |
| GIA note | "Consider accumulation funds to minimise annual distributions." |

### Standard Waterfall (9 Steps)

Each step follows this pattern:
1. Check skip conditions (age, allowance, life event blocks, etc.)
2. If not skipped, calculate allocation amount
3. Generate recommendation with headline, explanation, personal_context, priority, notes
4. Reduce remaining surplus by allocation amount
5. Pass remainder to next step

---

#### Step 1: Lifetime ISA (LISA)

```
SKIP conditions:
+-- In blockedWrappers? -> "Blocked by life event."
+-- age < 18? -> "Under 18 -- not eligible."
+-- age > 49? -> [W1a] "Over 50 -- cannot contribute to LISA."
+-- Not UK resident? -> "UK residency required for LISA."
+-- No property goal AND age >= 40? -> [W1b] "No qualifying property goal and over 39."
+-- LISA allowance fully used? -> "LISA allowance fully used."

RECOMMEND: [W1]
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
SKIP conditions:
+-- In blockedWrappers? -> "Blocked by life event."
+-- age < 18? -> "Under 18."
+-- ISA allowance fully used? -> "ISA allowance fully used."

RECOMMEND: [W2]
```

| # | Field | Value |
|---|-------|-------|
| W2 | headline (normal) | "Contribute to Stocks & Shares ISA" |
| W2 | headline (MPAA) | "Maximise ISA (pension limited by MPAA)" |
| W2 | explanation (normal) | "ISA shelters investments from income tax and capital gains tax with no lifetime limit." |
| W2 | explanation (MPAA) | "With MPAA triggered, your pension annual allowance is reduced to 10,000. ISA becomes your primary tax-efficient wrapper." |
| W2 | personal_context | "You have {isaRemaining} of your {isaLimit} ISA allowance remaining. As a {taxBand}-rate taxpayer, sheltering investments in an ISA saves you {taxSaving} on dividends and capital gains." |
| W2 | priority (MPAA) | `critical` |
| W2 | priority (normal) | `high` |
| W2 | note (conditional) | "Only {months} {month_word} left in the tax year with {remaining} ISA allowance remaining." *(shown if < 3 months to April 5 AND > 5,000 remaining)* |

**Tax saving display:** additional=45%, higher=40%, basic=20%

---

#### Step 3: Pension (Current Year)

```
SKIP conditions:
+-- In blockedWrappers? -> "Blocked by life event."
+-- age >= 75? -> "Over 75 -- pension contributions not allowed."
+-- Pension AA fully used? -> "Pension annual allowance fully used."

Affordability tiers:
+-- disposable_percent < 5%? -> RESTRICTED (cap = remaining * 0.25)
+-- disposable_percent < 10%? -> MODERATE (cap = remaining * 0.5)
+-- >= 10%? -> COMFORTABLE (no cap)

RECOMMEND: [W3]
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

---

#### Step 4A: Premium Bonds

```
SKIP conditions:
+-- In blockedWrappers? -> "Blocked by life event."
+-- contributionType = "regular"? -> "Premium Bonds best suited for lump sum contributions."
+-- age < 16? -> "Under 16."
+-- PSA not exceeded AND basic rate? -> "PSA not exceeded and basic rate -- other savings may be more effective."
+-- Maximum holding reached? -> [W4a] "Premium Bonds maximum holding of {max} already reached."

RECOMMEND: [W4]
```

| # | Field | Value |
|---|-------|-------|
| W4 | headline | "Premium Bonds" |
| W4 | explanation | "Premium Bond prizes are tax-free. Effective for higher/additional rate taxpayers who have exceeded their Personal Savings Allowance." |
| W4 | personal_context | "As a {taxBand}-rate taxpayer, Premium Bond prizes are tax-free -- equivalent to a higher gross rate on a taxable account. You hold {currentHolding} of the {maxHolding} maximum." |
| W4 | priority | `medium` |
| W4a | skip reason | "Premium Bonds maximum holding of {max} already reached." |

---

#### Step 4B: NS&I Savings

```
SKIP conditions:
+-- In blockedWrappers? -> "Blocked by life event."
+-- Allocation (10% of remaining) < 25? -> "Insufficient surplus for NS&I allocation."

RECOMMEND: [W5]
```

| # | Field | Value |
|---|-------|-------|
| W5 | headline | "NS&I Savings" |
| W5 | explanation | "NS&I products are backed by HM Treasury, offering security for conservative allocations." |
| W5 | personal_context | "Allocating {amount} to NS&I -- 100% government-backed with no deposit limit. A defensive holding within your portfolio." |
| W5 | priority | `low` |
| W5 | note (ESG pref) | "Consider NS&I Green Savings Bonds to align with your ESG preferences." |

---

#### Step 5: Offshore Bond

```
SKIP conditions:
+-- In blockedWrappers? -> "Blocked by life event."
+-- contributionType = "regular"? -> "Bonds require minimum lump sum investment."
+-- Tax band not higher/additional? -> "Offshore bonds most beneficial for higher/additional rate taxpayers."
+-- Experience none/beginner? -> "Offshore bonds require intermediate or higher investment experience."
+-- remaining < 10,000? -> "Minimum investment of {minInvestment} not met."

RECOMMEND: [W6]
```

| # | Field | Value |
|---|-------|-------|
| W6 | headline | "Offshore Investment Bond" |
| W6 | explanation | "Offshore bonds grow free of UK tax internally. Beneficial if you expect to be a basic rate taxpayer at encashment." |
| W6 | personal_context | "As a {taxBand}-rate taxpayer earning {income}, an offshore bond grows tax-deferred. If your tax band drops in retirement, you pay less on encashment." |
| W6 | priority | `low` |
| W6 | note (age 55-70) | "At your age, ensure a clear plan for bond encashment aligned with retirement income needs." |

---

#### Step 6: Onshore Bond

```
SKIP conditions:
+-- In blockedWrappers? -> "Blocked by life event."
+-- contributionType = "regular"? -> "Bonds require minimum lump sum investment."
+-- Tax band not higher/additional? -> "Onshore bonds most beneficial for higher/additional rate taxpayers."
+-- Experience none/beginner? -> "Onshore bonds require intermediate or higher investment experience."
+-- remaining < 5,000? -> "Minimum investment of {minInvestment} not met."

RECOMMEND: [W7]
```

| # | Field | Value |
|---|-------|-------|
| W7 | headline | "Onshore Investment Bond" |
| W7 | explanation | "Onshore bonds benefit from top-slicing relief, spreading gains across years held to reduce the tax band impact." |
| W7 | personal_context | "As a {taxBand}-rate taxpayer, the onshore bond's 20% internal tax credit means you only pay the difference on encashment. Top-slicing relief may further reduce the effective rate." |
| W7 | priority | `low` |
| W7 | note | "Top-slicing relief may reduce the effective tax rate on encashment." |

---

#### Step 7: Pension Carry Forward

```
SKIP conditions:
+-- Pension in blockedWrappers? -> "Pension blocked by life event."
+-- contributionType = "regular"? -> [W8a] "Pension carry forward is for lump sum contributions only."
+-- MPAA triggered? -> "MPAA triggered -- carry forward not available."
+-- carry_forward_available <= 0? -> "No carry forward available."

RECOMMEND: [W8]
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

#### Step 8: VCT/EIS/SEIS

```
SKIP conditions:
+-- All 3 in blockedWrappers? -> "All VCT/EIS/SEIS blocked by life event."
+-- contributionType = "regular"? -> "VCT/EIS/SEIS primarily suited for lump sum contributions."
+-- Experience none/beginner/intermediate? -> [W9a] "VCT/EIS/SEIS requires experienced investors (your level: {level})."
+-- Not comfortable with capital loss OR illiquidity? -> [W9b] "VCT/EIS/SEIS requires comfort with capital loss and illiquidity."
+-- disposable_percent < 10%? -> [W9c] "Disposable income below 10% -- insufficient buffer for high-risk investments."
+-- Allocation < 1,000? -> "Allocation too small for VCT/EIS/SEIS."

Max allocation: 10% of total portfolio value (or 10% of remaining if no portfolio)

RECOMMEND: [W9]
```

| # | Field | Value |
|---|-------|-------|
| W9 | headline | "Venture Capital Schemes (VCT/EIS/SEIS)" |
| W9 | explanation | "Tax-advantaged venture investments with 30-50% income tax relief. High risk, illiquid, and not suitable for all investors." |
| W9 | personal_context | "With a portfolio of {portfolioValue}, a {amount} VCT/EIS allocation represents {percent}% -- within the recommended 10% maximum. Tax relief of {taxRelief} reduces your income tax bill." |
| W9 | priority | `low` |
| W9 | note 1 | "Allocation order: SEIS first (50% relief), then EIS (30%), then VCT (30%)." |
| W9 | note 2 | "Minimum 3-5 year holding period for tax relief retention." |
| W9a | skip reason | "VCT/EIS/SEIS requires experienced investors (your level: {level})." |
| W9b | skip reason | "VCT/EIS/SEIS requires comfort with capital loss and illiquidity." |
| W9c | skip reason | "Disposable income below 10% -- insufficient buffer for high-risk investments." |

---

#### Step 9: GIA (Catch-All)

```
SKIP conditions:
+-- remaining <= 0? -> "No remaining surplus."

RECOMMEND: [W10]
```

| # | Field | Value |
|---|-------|-------|
| W10 | headline | "General Investment Account" |
| W10 | explanation | "GIA has no contribution limits or restrictions. Use tax-efficient strategies to minimise annual tax drag." |
| W10 | personal_context | "After filling tax-efficient wrappers, your remaining {remaining} is invested in a GIA. As a {taxBand}-rate taxpayer, use accumulation funds and annual CGT exemptions to minimise tax." |
| W10 | priority | `low` |
| W10 | note (higher/additional) | "Consider accumulation funds to minimise annual income distributions." |
| W10 | note (higher/additional) | "Use CGT annual exemption through phased disposals." |

### Life Event Priority Boost

Any waterfall recommendation matching a `prioritised_wrapper` from life event modifiers gets:
- Priority boosted one level: low -> medium, medium -> high, high -> critical (critical stays critical)
- Note added: "Priority raised due to an upcoming life event."

---

## 8. Phase 5: Transfer Scans

**Service:** `TransferRecommendationService` | **File:** `app/Services/Investment/Recommendation/TransferRecommendationService.php`

**Supporting:** `CashAccountAnalyzer` | **File:** `app/Services/Investment/Recommendation/CashAccountAnalyzer.php`

Transfer scans examine existing holdings and identify optimisation opportunities. These run independently of the contribution waterfall.

### Decision Tree

```
SCAN 1: EXCESS CASH
|
+-- totalCash > emergencyTarget + (monthlyExpenditure * 3)?
|   AND account is NOT: fixed-term, goal-linked, emergency fund
|   YES -> [T1]

SCAN 2: GIA SHELTER (BED & ISA)
|
+-- GIA account with balance > 0 AND isa_remaining > 0?
|   YES -> Calculate unrealised gains
|   |
|   +-- gains <= 0? -> CGT note: [T2a] "No CGT liability as holding is at a loss."
|   +-- gains > 0 AND <= cgt_allowance? -> CGT note: [T2b] "Gains within the annual exemption."
|   +-- gains > cgt_allowance? -> CGT note: [T2c] "Gains exceed the annual exemption."
|   |
|   -> [T2]

SCAN 3: PSA BREACH
|
+-- annualInterest > effectivePSA (PSA + starting rate band)?
    YES -> [T3]

SCAN 4: DIVIDEND ALLOWANCE BREACH
|
+-- annualDividends > dividendAllowance (500)?
    YES -> [T4]

SCAN 5: INTEREST RATE REVIEW
|
+-- bestAvailableRate - currentRate >= 0.5%?
    YES -> [T5]

SCAN 6: GOAL-LINKED OPTIMISATION
|
+-- Goal linked to account whose wrapper is NOT in suitable_wrappers?
    YES -> [T6]

SCAN 7: CASH ISA TO S&S ISA TRANSFER
|
+-- risk_level NOT very_low/none?
    AND Cash ISA balance > emergency target?
    AND excess >= 1,000?
    YES -> [T7]
```

### CashAccountAnalyzer Pre-Processing

Before transfer scans run, CashAccountAnalyzer categorises and analyses all cash holdings:

| Account Type | Action |
|-------------|--------|
| Legacy ISA records (`is_isa = true`) | Skipped: "Skipped legacy ISA record: {name}" |
| Trust-linked (`ownership_type = 'trust'`) | Skipped: "Skipped trust-linked account: {name}" |
| Business accounts | Skipped: "Skipped business account: {name}" |
| Emergency reserve accounts | Silently skipped |
| Fixed-term accounts | Silently skipped |
| Goal-linked accounts | Silently skipped |

| Analysis | Condition | Message |
|----------|-----------|---------|
| Current account excess | balance > 3x monthly expenditure | "Balance exceeds 3 months of expenditure by {excess}. Consider moving excess to a higher-rate savings account or investing." |
| Savings account excess | balance > emergency target + 3 months | "Savings balance exceeds emergency target + 3 months buffer by {excess}. Consider investing the excess for better long-term returns." |
| Rate expired | Promotional rate in the past | "Promotional rate on {name} expired on {date}. Review for a better rate." |
| Rate expiring soon | Promotional rate within 3 months | "Rate on {name} expires {date}. Start looking for alternatives." |

### Transfer Message Reference

| # | Scan | Config Key | Headline | Explanation | Priority |
|---|------|------------|----------|-------------|----------|
| T1 | Excess cash | `transfers.excess_cash.trigger` | "Excess cash earning below inflation" | "Your cash reserves exceed your emergency target by {amount}. Consider investing the excess for better long-term returns." | `medium` |
| T2 | Bed & ISA | `transfers.bed_and_isa.trigger` | "Bed & ISA -- shelter GIA holdings" | "Transfer {amount} from GIA to ISA to shelter future growth from tax." + CGT note | No CGT: `high`; Has CGT: `medium` |
| T2a | Bed & ISA (loss) | `transfers.bed_and_isa.net_loss` | *(appended to T2)* | "No CGT liability as the holding is at a loss. Losses can be carried forward." | -- |
| T2b | Bed & ISA (within) | `transfers.bed_and_isa.within_allowance` | *(appended to T2)* | "Gains within the annual exemption -- no CGT payable." | -- |
| T2c | Bed & ISA (exceeds) | `transfers.bed_and_isa.exceeds_allowance` | *(appended to T2)* | "Gains exceed the annual exemption. Estimated CGT: {cgt}." | -- |
| T3 | PSA breach | `transfers.psa_breach.trigger` | "Interest income exceeds tax-free allowance" | "Your annual interest exceeds your tax-free allowance by {amount}. Consider moving savings to tax-free wrappers." | `high` |
| T4 | Dividend breach | `transfers.dividend_breach.trigger` | "Dividend income exceeds tax-free allowance" | "Annual dividends exceed the {allowance} allowance. Prioritise moving highest-yielding GIA holdings into ISA." | `medium` |
| T5 | Rate review | `transfers.interest_rate.switch` | Rate expired: "Promotional rate expired"; Other: "Better rate available" | "Your {account} earns {current}%. A comparable account offers {best}% -- switching could earn an extra {gain} per year." | Rate expired: `high`; Other: `medium` |
| T6 | Goal wrapper | `transfers.goal_optimisation.trigger` | "Better wrapper for \"{goalName}\" goal" | "Your goal \"{goal}\" is linked to a {current} wrapper. Consider moving to a {better} for better tax efficiency." | `low` |
| T7 | Cash ISA to S&S ISA | *(inline)* | "Transfer Cash ISA to Stocks & Shares ISA" | "Your Cash ISA balance of {balance} exceeds your emergency target. Consider transferring {excess} to a Stocks & Shares ISA for better long-term growth." | `medium` |

### Transfer Personal Context Templates

| Scan | Personal Context |
|------|-----------------|
| T1 | "Your cash reserves of {totalCash} exceed your emergency target of {emergencyTarget} by {excessAmount}. The excess is earning {interestRate}% while inflation erodes its value. Consider investing for better long-term returns." |
| T2 | "Your GIA holds {balance} with {gains} unrealised gains. {CGT context}. You have {isaRemaining} of ISA allowance to shelter this transfer." |
| T3 | "Your savings earn {annualInterest}/year in interest against a {effectivePSA} personal savings allowance ({taxBand}-rate). The {breach} excess is taxed at your marginal rate." |
| T4 | "Your dividend income of {annualDividends} exceeds the {dividendAllowance} allowance by {breach}, costing approximately {taxCost} in dividend tax at the {taxBand} rate." |
| T5 | "Your {accountName} earns {currentRate}% on {balance}. Switching to the best available rate of {bestRate}% would earn an additional {annualGain} per year." |
| T6 | "Your \"{goalName}\" goal is held in a {currentWrapper}, but a {betterWrapper} would be more tax-efficient for this timeline and purpose." |
| T7 | "Your Cash ISA holds {balance} but you only need {emergencyTarget} for emergencies. With {a/an} {riskLevel} risk profile, the {excess} excess could generate better long-term returns in a Stocks & Shares ISA. ISA-to-ISA transfers preserve your tax-free status." |

### Transfer Pre-Transfer Checks & Notes

| Scan | Checks / Notes |
|------|---------------|
| T1 | "Confirm emergency fund remains at target level after transfer." / "Check for any notice period on the savings account." |
| T2 | "Confirm ISA subscription status with provider." / "Bed & Breakfast 30-day rule does NOT apply to Bed & ISA." |
| T4 | "Prioritise transferring the highest-yielding holdings first." |
| T6 | "Ensure goal linkage is preserved when transferring." |
| T7 | "ISA-to-ISA transfer preserves ISA status -- no allowance impact." / "Ensure emergency fund remains accessible after transfer." / "Consider your investment time horizon before transferring." / "Current year subscriptions must be transferred in full or not at all. Previous years can be partially transferred." |

---

## 9. Phase 6: Spouse Optimisation

**Service:** `SpouseOptimisationService` | **File:** `app/Services/Investment/Recommendation/SpouseOptimisationService.php`

Spouse optimisation only runs if the user is married/civil_partnership AND has a linked spouse account. It produces six independent strategies.

### Gate Conditions

```
No spouse linked?
    YES -> GATE: "No linked spouse -- spouse optimisation not available." [SP0a]

Not married or civil_partnership?
    YES -> GATE: "Not married or in civil partnership -- spouse optimisation not available." [SP0b]

Life event blocks_spouse_optimisation?
    YES -> All strategies skipped
```

### Decision Tree

```
STRATEGY 1: CGT ALLOWANCE SHARING
|
+-- User's total GIA unrealised gains > annual CGT exemption (3,000)?
    YES -> [SP1]
    |-- If spouse is different tax band:
    |   note: "Your spouse is a {band} rate taxpayer. Transferring gains could reduce CGT rate."
    |-- Always:
        note: "Interspousal transfers are CGT-exempt. Spouse then crystallises at their own rates."

STRATEGY 2: ISA COORDINATION
|
+-- One partner's ISA allowance exhausted, other has remaining?
    YES -> [SP2]

STRATEGY 3: PSA OPTIMISATION
|
+-- Partners in different tax bands?
    YES -> [SP3]

STRATEGY 4: PENSION COORDINATION
|
+-- Partners in different tax bands?
|   YES -> [SP4a] Higher-rate pension prioritisation
|
+-- Spouse income <= 0?
|   YES -> [SP4b] Non-earning spouse pension
|
+-- Spouse carry_forward_available > 0?
    YES -> [SP4c] Spouse carry forward

STRATEGY 5: MARRIAGE ALLOWANCE
|
+-- Both earn above personal allowance?
|   YES -> SKIP: "Both partners earn above the personal allowance."
|
+-- Higher earner is higher/additional rate?
|   YES -> SKIP: [SP5a] "Higher earner is above basic rate -- Marriage Allowance not available."
|
+-- Eligible?
    YES -> [SP5]

STRATEGY 6: IHT PLANNING (INFO)
|
+-- Combined estate > (NRB + RNRB) * 2?
    YES -> [SP6]
```

### Message Reference

| # | Strategy | Config Key | Headline | Explanation | Priority |
|---|----------|------------|----------|-------------|----------|
| SP0a | Gate | `spouse.gate.no_spouse` | -- | "No linked spouse -- spouse optimisation not available." | -- |
| SP0b | Gate | `spouse.gate.not_married` | -- | "Not married or in civil partnership -- spouse optimisation not available." | -- |
| SP1 | CGT sharing | `spouse.cgt_sharing.trigger` | "Share CGT allowance with spouse" | "Transfer holdings with gains to your spouse to use their {allowance} annual CGT exemption." | `medium` |
| SP2 | ISA coordination | `spouse.isa_coordination.trigger` | "Maximise household ISA allowance" | "Your household has {remaining} of ISA allowance remaining. Gift money to your spouse to contribute to their ISA." | `medium` |
| SP2 (note) | ISA coordination | `spouse.isa_coordination.note` | -- | "You cannot contribute directly to your spouse's ISA -- gift the money for them to contribute." | -- |
| SP3 | PSA optimisation | `spouse.psa_optimisation.trigger` | "Shift savings to lower-rate spouse" | "{spouseName} has a {spousePSA} PSA vs your {userPSA}. Consider holding interest-bearing accounts in the name of the lower-rate partner." | `medium` |
| SP3 (note) | PSA optimisation | `spouse.psa_optimisation.iht_note` | -- | "Transferring savings between spouses may have IHT implications if estate exceeds the nil-rate band." | -- |
| SP4a | Pension (higher rate) | `spouse.pension_coordination.higher_rate` | "Prioritise pension for higher-rate partner" | "Pension contributions for {partner} ({band} rate) receive higher tax relief. Prioritise maximising their pension allowance first." | `high` |
| SP4b | Pension (non-earner) | `spouse.pension_coordination.non_earner` | "Non-earning spouse pension contribution" | "Your spouse can receive up to 3,600 gross pension contributions per year even with no earnings." | `medium` |
| SP4c | Pension (carry forward) | `spouse.pension_coordination.carry_forward` | "Use spouse's pension carry forward" | "Your spouse has {amount} in unused pension carry forward." | `medium` |
| SP5 | Marriage Allowance | `spouse.marriage_allowance.eligible` | "Claim Marriage Allowance" | "Transfer {amount} of unused personal allowance to your basic rate partner, saving up to {saving} per year." | `medium` |
| SP5a | Marriage Allowance (skip) | `spouse.marriage_allowance.not_available` | -- | "Higher earner is above basic rate -- Marriage Allowance not available." | -- |
| SP6 | IHT planning | `spouse.iht_planning.trigger` | "Combined estate exceeds IHT threshold" | "Your combined estate exceeds the {threshold} threshold by {excess}. Consider estate planning strategies." | `info` |

### Spouse Personal Context Templates

| Strategy | Personal Context |
|----------|-----------------|
| SP3 | "You earn {userIncome} ({userTaxBand} rate, {userPSA} PSA) and {spouseName} earns {spouseIncome} ({spouseTaxBand} rate, {spousePSA} PSA). Holding savings in {partner's} name shelters up to {difference} more interest from tax." |
| SP4a (user higher) | "You earn {income} ({band} rate) and receive {rate} tax relief on pension contributions. Maximise your pension allowance first for the highest relief." |
| SP4a (spouse higher) | "{spouseName} earns {income} ({band} rate) and receives {rate} tax relief on pension contributions. Maximise their pension allowance first for the highest relief." |
| SP4c | "Your spouse has {carryForward} of unused pension allowance. Use oldest year first -- carry forward expires after 3 years." |

### Spouse Strategy Notes

| Strategy | Notes |
|----------|-------|
| SP1 (different band) | "Your spouse is a {band} rate taxpayer. Transferring gains to them could reduce the CGT rate." |
| SP1 (always) | "Interspousal transfers are CGT-exempt. Spouse then crystallises at their own rates." |
| SP2 | "Combined household ISA capacity: {householdCapacity} per year." |
| SP4a | "Pensions are outside the estate for IHT, adding a further benefit." |
| SP4c | "Use oldest year first -- carry forward expires after 3 years." |
| SP5 | "Apply through HMRC. Can be backdated up to 4 years." |
| SP6 | "Pensions are normally outside the estate for IHT." / "Consider the estate planning module for detailed analysis." |

---

## 10. Phase 7: Conflict Resolution

**Service:** `ConflictResolutionService` | **File:** `app/Services/Investment/Recommendation/ConflictResolutionService.php`

Conflict resolution merges all recommendations from Phases 4-6 and resolves competing claims on shared resources (surplus, ISA allowance, pension allowance).

### Decision Tree

```
CONFLICT 1: SURPLUS INCOME PRIORITY
|
+-- Total demand > disposable income?
    YES -> Sort by 12-step priority: employer_match, high_interest_debt,
           emergency_fund, protection, lifetime_isa, stocks_shares_isa,
           pension, nsi_savings, pension_carry_forward, bonds, vct_eis_seis, gia
    |
    +-- Partially funded? -> note: [C1a] "Partially funded due to surplus constraints."
    +-- Fully deferred?   -> note: [C1b] "Insufficient surplus after higher-priority allocations."

CONFLICT 2: ISA ALLOWANCE COMPETITION
|
+-- Total ISA demand > ISA remaining?
    YES -> Priority: LISA first (25% bonus), then S&S ISA, then Cash ISA
    |
    +-- Partially funded? -> note: [C2a] "ISA allowance partially allocated."
    +-- Fully deferred?   -> note: [C2b] "ISA allowance exhausted by higher-priority ISA recommendations."

CONFLICT 3: PENSION ALLOWANCE COMPETITION
|
+-- Total pension demand > pension AA remaining?
    YES -> Sort by highest tax_relief first
    |
    +-- Fully deferred? -> note: [C3] "Pension annual allowance exhausted."

CONFLICT 4: GOAL COMPETITION
|
+-- Multiple goals compete for same wrapper?
    YES -> Sort by priority (high > medium > low) then urgency (shortest timeline first)
    (No user-facing messages -- internal reordering only)

CONFLICT 5: LIFE EVENT OVERRIDES
|
+-- Any life event modifier blocks a wrapper?
    YES -> note: [C5] "Blocked by life event."

CONFLICT 6: PROTECTION VS INVESTMENT
|
+-- Delegates to existing ConflictResolver service
    (No new messages)
```

### Message Reference

| # | Conflict | Config Key | Message |
|---|---------|------------|---------|
| C1a | Surplus (partial) | `conflicts.surplus.partial` | "Partially funded due to surplus constraints." |
| C1b | Surplus (deferred) | `conflicts.surplus.deferred` | "Insufficient surplus after higher-priority allocations." |
| C2a | ISA (partial) | `conflicts.isa.partial` | "ISA allowance partially allocated." |
| C2b | ISA (deferred) | `conflicts.isa.deferred` | "ISA allowance exhausted by higher-priority ISA recommendations." |
| C3 | Pension (deferred) | `conflicts.pension.deferred` | "Pension annual allowance exhausted." |
| C5 | Life event block | `conflicts.life_event.blocked` | "Blocked by life event." |
| -- | General note | `conflicts.general.waterfall_vs_conflict` | "Waterfall order (tax efficiency) differs from conflict order (tax relief) by design." |

---

## 11. Output Formatting & Priority

**Service:** `RecommendationOutputFormatter` | **File:** `app/Services/Investment/Recommendation/RecommendationOutputFormatter.php`

### Priority Sorting

All recommendations are sorted by numeric priority before returning to the API:

| Label | Numeric | Typical Sources |
|-------|---------|----------------|
| `critical` | 1 | Critical debt, emergency fund <1mo, MPAA-triggered ISA, additional-rate pension |
| `high` | 2 | Medium debt, emergency fund 1-3mo, ISA, higher-rate pension, Bed & ISA (no CGT), PSA breach |
| `medium` | 3 | Emergency fund building, protection, LISA, Premium Bonds, spouse strategies |
| `low` | 4 | NS&I, bonds, carry forward, VCT/EIS/SEIS, GIA, goal wrapper optimisation |
| `info` | 5 | Emergency fund excess, life event sub-actions, IHT planning |

### Output Fields Per Recommendation

| Field | Description |
|-------|-------------|
| `uuid` | Unique identifier (generated) |
| `module` | Always "investment" |
| `category` | contribution, transfer, rebalance, debt, emergency_fund, protection, spouse, life_event |
| `wrapper` | Disambiguated wrapper name (never bare "isa") |
| `headline` | Short action-oriented title |
| `explanation` | Why this recommendation exists |
| `personal_context` | Personalised explanation using the user's actual numbers |
| `amount` | Recommended amount |
| `frequency` | "one_off" or "monthly" |
| `tax_relief` | Tax relief amount |
| `estimated_annual_benefit` | Calculated annual benefit |
| `effective_cost` | Amount - tax_relief |
| `timeline` | Relevant timeline |
| `priority_label` | critical / high / medium / low / info |
| `priority_numeric` | 1-5 (for sorting) |
| `status` | active, blocked, deferred |
| `is_blocked` | Boolean |
| `blocked_reason` | Why it's blocked |
| `is_deferred` | Boolean |
| `deferred_reason` | Why it's deferred |
| `linked_goal_id` | Related goal |
| `linked_account_id` | Related account |
| `linked_life_event_id` | Related life event |
| `notes` | Array of advisory notes |
| `decision_path` | Array of decision trail entries |

### Status Mapping

| Source | Status | is_blocked |
|--------|--------|------------|
| Safety block (critical/high severity) | `blocked` | true |
| Safety block (medium/info severity) | `active` | false |
| Conflict resolution deferred | `deferred` | false |
| Normal recommendation | `active` | false |

### Deduplication

Key = `{headline}:{wrapper}` -- if the same recommendation appears from contribution, transfer, and spouse sources, only the first occurrence is kept.

### Estimated Annual Benefit Formulae

| Wrapper | Formula |
|---------|---------|
| ISA types | `(2% dividend yield * amount * dividend_tax_rate) + (5% growth * amount * CGT_rate)` |
| Pension | `amount * marginal_income_tax_rate` |
| GIA | 0 |
| Premium Bonds | `amount * (prize_rate / 100) * marginal_rate` |
| Debt | `amount * 15%` (average credit card rate) |

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
| `vct_eis_seis` | `vct_eis_seis` |
| `premium_bonds` | `premium_bonds` |
| `nsi_savings` | `nsi_savings` |
| `savings_account` | `savings_account` |

---

## 12. Thresholds & Constants Reference

| Threshold | Value | Service | Purpose |
|-----------|-------|---------|---------|
| Critical debt rate | > 15% | SafetyCheckService | Blocks all investment |
| Medium debt rate | 5-15% | SafetyCheckService | 50% surplus reduction |
| Mortgage exception rate | 3.0% | SafetyCheckService | Mortgages below this excluded from debt checks |
| Expected investment return | 5% | SafetyCheckService, GoalAssessmentService | Comparison against debt cost |
| Emergency fund target (employed) | 6 months | UserContextBuilder | Emergency fund assessment |
| Emergency fund target (self-employed) | 9 months | UserContextBuilder | Emergency fund assessment |
| Emergency fund target (retired) | 3 months | UserContextBuilder | Emergency fund assessment |
| Emergency fund target (unemployed) | 6 months | UserContextBuilder | Emergency fund assessment |
| Emergency Tier 1 (critical) | < 1 month runway | SafetyCheckService | Surplus = 0 |
| Emergency Tier 2 (high) | 1-3 months runway | SafetyCheckService | Surplus capped at 50% |
| Emergency Tier 3 (medium) | 3mo to target | SafetyCheckService | No cap, parallel building |
| Emergency Tier 4 (excess) | > target + 3 months | SafetyCheckService | Suggests investing excess |
| Promotional rate warning | Within 6 months | SafetyCheckService | Debt promotional rate alert |
| LISA age range | 18-49 | ContributionWaterfallService | LISA eligibility |
| LISA new account age limit | < 40 (without property goal) | ContributionWaterfallService | Cannot open new LISA |
| LISA property price limit | 450,000 | GoalAssessmentService | LISA first home eligibility |
| Pension age limit | < 75 | ContributionWaterfallService | Pension contribution gate |
| Pension affordability (restricted) | disposable_percent < 5% | ContributionWaterfallService | Cap at 25% of remaining |
| Pension affordability (moderate) | disposable_percent < 10% | ContributionWaterfallService | Cap at 50% of remaining |
| Premium Bonds min age | 16 | ContributionWaterfallService | Premium Bonds eligibility |
| Premium Bonds max holding | 50,000 | ContributionWaterfallService | Premium Bonds cap |
| Offshore bond minimum | 10,000 | ContributionWaterfallService | Minimum lump sum |
| Onshore bond minimum | 5,000 | ContributionWaterfallService | Minimum lump sum |
| VCT/EIS/SEIS max portfolio % | 10% | ContributionWaterfallService | Max allocation |
| VCT/EIS/SEIS min allocation | 1,000 | ContributionWaterfallService | Minimum viable allocation |
| VCT/EIS/SEIS disposable gate | < 10% | ContributionWaterfallService | Insufficient buffer |
| ISA year-end urgency | < 3 months AND > 5,000 remaining | ContributionWaterfallService | Urgency note trigger |
| NS&I default allocation | 10% of remaining | ContributionWaterfallService | Conservative allocation |
| NS&I minimum | 25 | ContributionWaterfallService | Skip if below |
| Cash excess threshold | Emergency target + 3 months | TransferRecommendationService | Excess cash trigger |
| Current account excess | 3x monthly expenditure | CashAccountAnalyzer | Current account alert |
| Interest rate switch | >= 0.5% difference | TransferRecommendationService | Rate review trigger |
| Rate expiry warning | Within 3 months | CashAccountAnalyzer | Expiry alert |
| Cash ISA transfer minimum | 1,000 excess | TransferRecommendationService | Minimum for transfer rec |
| Windfall premium bonds threshold | >= 50,000 | LifeEventAssessmentService | Adds premium_bonds to wrappers |
| Approaching retirement | <= 5 years | LifeEventAssessmentService | Triggers glide path |
| Property purchase short-term block | < 3 years | GoalAssessmentService | Blocks equities |
| Goal timeline SHORT | < 2 years | GoalAssessmentService | Cash-only wrappers |
| Goal timeline MEDIUM | 2-5 years | GoalAssessmentService | ISA wrappers |
| Goal timeline LONG | 5-10 years | GoalAssessmentService | Equity wrappers |
| Goal timeline VERY_LONG | > 10 years | GoalAssessmentService | Full wrapper range |
| CGT annual exemption | 3,000 | TaxConfigService | CGT sharing trigger |
| PSA (basic rate) | 1,000 | TaxConfigService | PSA breach detection |
| PSA (higher rate) | 500 | TaxConfigService | PSA breach detection |
| PSA (additional rate) | 0 | TaxConfigService | PSA breach detection |
| Dividend allowance | 500 | TaxConfigService | Dividend breach detection |
| ISA annual limit | 20,000 | TaxConfigService | ISA allowance cap |
| LISA annual limit | 4,000 | TaxConfigService | LISA allowance cap |
| Pension annual allowance | 60,000 | TaxConfigService | Pension contribution cap |
| MPAA limit | 10,000 | TaxConfigService | MPAA pension cap |
| Personal allowance | 12,570 | TaxConfigService | Marriage Allowance, tax bands |
| PA taper threshold | 100,000 | UserContextBuilder | PA reduction trigger |
| Pension AA taper (threshold income) | 200,000 | UserContextBuilder | AA taper trigger |
| Pension AA taper (adjusted income) | 260,000 | UserContextBuilder | AA taper trigger |
| IHT nil-rate band | 325,000 | TaxConfigService | IHT planning trigger |
| IHT residence nil-rate band | 175,000 | TaxConfigService | IHT planning trigger |
| Non-earner pension gross limit | 3,600 | SpouseOptimisationService | Spouse pension cap |
| Marriage Allowance transfer | 1,257 (10% of PA) | SpouseOptimisationService | MA calculation |
| Marriage Allowance saving | Up to 252 | SpouseOptimisationService | MA benefit |
| Basic rate band | < 50,270 | UserContextBuilder | Tax band derivation |
| Higher rate band | < 125,140 | UserContextBuilder | Tax band derivation |
| Additional rate band | >= 125,140 | UserContextBuilder | Tax band derivation |
| CGT rate (basic) | 18% | TaxConfigService | CGT calculations |
| CGT rate (higher/additional) | 24% | TaxConfigService | CGT calculations |

---

## 13. Config Message Key Reference

Complete index of all `config/investment_messages.php` keys used across the engine.

### Readiness Messages (`readiness.*`)

| Key | Severity | Message |
|-----|----------|---------|
| `readiness.block.date_of_birth` | block | "Your date of birth is needed to assess age-related investment options like LISA eligibility and pension access." |
| `readiness.block.gross_annual_income` | block | "Your income details are needed to calculate tax bands, pension allowances, and affordable contribution levels." |
| `readiness.block.risk_level` | block | "Complete your risk profile so we can recommend investments suited to your comfort level." |
| `readiness.block.monthly_expenditure` | block | "Your monthly expenditure is needed to calculate emergency fund requirements and affordable investment amounts." |
| `readiness.warn.employment_status` | warn | "Adding your employment status helps us tailor emergency fund targets and pension recommendations." |
| `readiness.warn.protection_profile` | warn | "You have dependents but no protection profile. Add your insurance details for better protection gap analysis." |
| `readiness.warn.dc_pensions` | warn | "Adding your workplace pension details allows us to check employer matching and optimise pension contributions." |
| `readiness.warn.investment_accounts` | warn | "Add your investment accounts so we can identify tax-efficient transfer opportunities like Bed & ISA." |
| `readiness.info.accounts` | info | "Add your existing savings and investment accounts to receive transfer and optimisation recommendations." |
| `readiness.info.spouse_link` | info | "Link your partner's account to unlock household tax optimisation strategies like CGT sharing and ISA coordination." |
| `readiness.info.life_events` | info | "Add any upcoming life events (property purchase, retirement, new baby) to receive tailored investment advice." |

### Life Event Messages (`life_events.*`)

| Key | Message |
|-----|---------|
| `life_events.approaching_retirement.trigger` | "You are :years :year_word from retirement. Your investment strategy should gradually shift towards lower-risk assets." |
| `life_events.windfall.trigger` | "A windfall of :amount gives you an opportunity to boost your tax-efficient investments." |
| `life_events.new_baby.trigger` | "Consider opening a Junior ISA (:limit/year allowance) and reviewing your life cover." |
| `life_events.new_baby.child_benefit` | "Income over 50,000 triggers High Income Child Benefit Charge." |
| `life_events.marriage.trigger` | "Marriage opens up valuable tax planning opportunities. Link your partner's account to unlock household optimisation." |
| `life_events.divorce.trigger` | "During divorce, interspousal asset transfers are CGT-exempt in the tax year of separation. Review beneficiaries across all accounts." |
| `life_events.redundancy.block` | "Following redundancy, focus on building liquid reserves. Avoid illiquid investments until your income stabilises." |
| `life_events.serious_illness.block` | "Focus on liquidity and protection claim eligibility. Avoid illiquid investments during this period." |
| `life_events.death_of_partner.block` | "Your inherited ISA allowance and bereavement support eligibility should be reviewed. Avoid major financial decisions during this period." |
| `life_events.child_turning_18.trigger` | "When your child turns 18, their Junior ISA converts to an adult ISA. Review your dependent count and protection needs." |
| `life_events.buying_a_home.trigger` | "Keep your deposit funds in accessible accounts. Illiquid investments should wait until after completion." |

### Safety Messages (`safety.*`)

| Key | Message |
|-----|---------|
| `safety.debt.critical` | "You have :type debt at :rate% interest. Repaying this should come before investing." |
| `safety.debt.high` | "Your :type debt at :rate% costs more than typical investment returns. Consider splitting surplus between debt repayment and investing." |
| `safety.debt.promotional` | "Your 0% promotional rate on :type expires on :date. Plan for the rate increase." |
| `safety.emergency_fund.critical` | "Your emergency fund covers less than 1 month of expenses. Build this to at least :target months before investing." |
| `safety.emergency_fund.high` | "Your emergency fund covers :runway months. We recommend :target months. Investment limited to 50% of surplus." |
| `safety.emergency_fund.medium` | "Your emergency fund covers :runway months. Consider building to :target months alongside investing." |
| `safety.emergency_fund.excess` | "Your emergency fund exceeds the target by :months months. Consider investing the excess of :amount." |
| `safety.emergency_fund.no_expenditure` | "Monthly expenditure data is missing. Please update your expenditure profile for accurate emergency fund assessment." |
| `safety.protection.dependents` | "You have :count :dependent_word. Ensure adequate life cover and income protection before prioritising investments." |
| `safety.employer_match.always` | "Your employer offers :percent% pension matching. Contribute at least enough to get the full match, even if other safety checks apply." |

### Waterfall Messages (`waterfall.*`)

| Key | Message |
|-----|---------|
| `waterfall.lisa.recommend` | "The 25% government bonus makes LISA the most effective wrapper for eligible first-time buyers or retirement savings." |
| `waterfall.lisa.age_cutoff` | "Last year to contribute to LISA before age 50 cutoff." |
| `waterfall.lisa.maturity` | "LISA withdrawals for first home require 12 months from first contribution." |
| `waterfall.lisa.over_50` | "Over 50 -- cannot contribute to LISA." |
| `waterfall.lisa.no_property_goal` | "No qualifying property goal and over 39 -- cannot open new LISA." |
| `waterfall.isa.recommend` | "ISA shelters investments from income tax and capital gains tax with no lifetime limit." |
| `waterfall.isa.mpaa_primary` | "With MPAA triggered, your pension annual allowance is reduced to 10,000. ISA becomes your primary tax-efficient wrapper." |
| `waterfall.isa.year_end_urgency` | "Only :months :month_word left in the tax year with :remaining ISA allowance remaining." |
| `waterfall.pension.basic_rate` | "Pension contributions receive 20% tax relief." |
| `waterfall.pension.higher_rate` | "Pension contributions receive 40% tax relief. Your net cost is :net for a :gross gross contribution." |
| `waterfall.pension.additional_rate` | "Pension contributions receive 45% tax relief. Your net cost is :net for a :gross gross contribution." |
| `waterfall.pension.mpaa` | "MPAA limits your money purchase annual allowance to :limit." |
| `waterfall.pension.non_earner` | "Even without earnings, you can contribute up to 3,600 gross to a pension and receive 720 in tax relief." |
| `waterfall.pension.restricted` | "Contribution limited due to low disposable income (below 5%)." |
| `waterfall.premium_bonds.recommend` | "Premium Bond prizes are tax-free. Effective for higher/additional rate taxpayers who have exceeded their Personal Savings Allowance." |
| `waterfall.premium_bonds.max_reached` | "Premium Bonds maximum holding of :max already reached." |
| `waterfall.nsi.recommend` | "NS&I products are backed by HM Treasury, offering security for conservative allocations." |
| `waterfall.nsi.green` | "Consider NS&I Green Savings Bonds to align with your ESG preferences." |
| `waterfall.offshore_bond.recommend` | "Offshore bonds grow free of UK tax internally. Beneficial if you expect to be a basic rate taxpayer at encashment." |
| `waterfall.offshore_bond.clear_plan` | "At your age, ensure a clear plan for bond encashment aligned with retirement income needs." |
| `waterfall.onshore_bond.recommend` | "Onshore bonds benefit from top-slicing relief, spreading gains across years held to reduce the tax band impact." |
| `waterfall.carry_forward.recommend` | "You have :amount of unused pension allowance from previous years. Use oldest year first as it expires first." |
| `waterfall.carry_forward.regular_skip` | "Pension carry forward is for lump sum contributions only." |
| `waterfall.vct_eis_seis.recommend` | "Tax-advantaged venture investments with 30-50% income tax relief. High risk, illiquid, and not suitable for all investors." |
| `waterfall.vct_eis_seis.experience_block` | "VCT/EIS/SEIS requires experienced investors (your level: :level)." |
| `waterfall.vct_eis_seis.comfort_block` | "VCT/EIS/SEIS requires comfort with capital loss and illiquidity." |
| `waterfall.vct_eis_seis.disposable_block` | "Disposable income below 10% -- insufficient buffer for high-risk investments." |
| `waterfall.gia.recommend` | "GIA has no contribution limits or restrictions. Use tax-efficient strategies to minimise annual tax drag." |
| `waterfall.gia.accumulation` | "Consider accumulation funds to minimise annual income distributions." |
| `waterfall.gia.cgt_harvesting` | "Use CGT annual exemption through phased disposals." |
| `waterfall.under_18.path` | "Under 18: Junior ISA and GIA only." |
| `waterfall.no_surplus.note` | "No surplus available for investment." |
| `waterfall.life_event_priority_boost` | "Priority raised due to an upcoming life event." |

### Transfer Messages (`transfers.*`)

| Key | Message |
|-----|---------|
| `transfers.excess_cash.trigger` | "Your cash reserves exceed your emergency target by :amount. Consider investing the excess for better long-term returns." |
| `transfers.bed_and_isa.trigger` | "Transfer :amount from GIA to ISA to shelter future growth from tax." |
| `transfers.bed_and_isa.within_allowance` | "Gains within the annual exemption -- no CGT payable." |
| `transfers.bed_and_isa.exceeds_allowance` | "Gains exceed the annual exemption. Estimated CGT: :cgt." |
| `transfers.bed_and_isa.net_loss` | "No CGT liability as the holding is at a loss. Losses can be carried forward." |
| `transfers.psa_breach.trigger` | "Your annual interest exceeds your tax-free allowance by :amount. Consider moving savings to tax-free wrappers." |
| `transfers.dividend_breach.trigger` | "Annual dividends exceed the :allowance allowance. Prioritise moving highest-yielding GIA holdings into ISA." |
| `transfers.interest_rate.switch` | "Your :account earns :current%. A comparable account offers :best% -- switching could earn an extra :gain per year." |
| `transfers.interest_rate.expired` | "Promotional rate expired on :account." |
| `transfers.goal_optimisation.trigger` | "Your goal \":goal\" is linked to a :current wrapper. Consider moving to a :better for better tax efficiency." |

### Spouse Messages (`spouse.*`)

| Key | Message |
|-----|---------|
| `spouse.cgt_sharing.trigger` | "Transfer holdings with gains to your spouse to use their :allowance annual CGT exemption." |
| `spouse.isa_coordination.trigger` | "Your household has :remaining of ISA allowance remaining. Gift money to your spouse to contribute to their ISA." |
| `spouse.isa_coordination.note` | "You cannot contribute directly to your spouse's ISA -- gift the money for them to contribute." |
| `spouse.psa_optimisation.trigger` | "Your spouse has a :spouse_psa PSA vs your :user_psa. Consider holding interest-bearing accounts in the name of the lower-rate partner." |
| `spouse.psa_optimisation.iht_note` | "Transferring savings between spouses may have IHT implications if estate exceeds the nil-rate band." |
| `spouse.pension_coordination.higher_rate` | "Pension contributions for :partner (:band rate) receive higher tax relief. Prioritise maximising their pension allowance first." |
| `spouse.pension_coordination.non_earner` | "Your spouse can receive up to 3,600 gross pension contributions per year even with no earnings." |
| `spouse.pension_coordination.carry_forward` | "Your spouse has :amount in unused pension carry forward." |
| `spouse.marriage_allowance.eligible` | "Transfer :amount of unused personal allowance to your basic rate partner, saving up to :saving per year." |
| `spouse.marriage_allowance.not_available` | "Higher earner is above basic rate -- Marriage Allowance not available." |
| `spouse.iht_planning.trigger` | "Your combined estate exceeds the :threshold threshold by :excess. Consider estate planning strategies." |
| `spouse.gate.no_spouse` | "No linked spouse -- spouse optimisation not available." |
| `spouse.gate.not_married` | "Not married or in civil partnership -- spouse optimisation not available." |

### Conflict Messages (`conflicts.*`)

| Key | Message |
|-----|---------|
| `conflicts.surplus.deferred` | "Insufficient surplus after higher-priority allocations." |
| `conflicts.surplus.partial` | "Partially funded due to surplus constraints." |
| `conflicts.isa.deferred` | "ISA allowance exhausted by higher-priority ISA recommendations." |
| `conflicts.isa.partial` | "ISA allowance partially allocated." |
| `conflicts.pension.deferred` | "Pension annual allowance exhausted." |
| `conflicts.life_event.blocked` | "Blocked by life event." |
| `conflicts.general.waterfall_vs_conflict` | "Waterfall order (tax efficiency) differs from conflict order (tax relief) by design." |
