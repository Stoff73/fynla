# Retirement Decision Engine: Complete Decision Tree & Research Reference

> Exhaustive mapping of every retirement planning decision, analysis path, UK-specific rule, threshold, and user-facing output for the Fynla Retirement module.
>
> **Research version:** v1.0.0 | **Date:** 2026-03-14 | **Tax year:** 2025/26 | **Codebase ref:** RetirementAgent + 10 services

---

## Table of Contents

1. [Engine Pipeline Overview](#1-engine-pipeline-overview)
2. [User Context: Data Inputs Required](#2-user-context-data-inputs-required)
3. [Phase 1: Data Readiness Gate](#3-phase-1-data-readiness-gate)
4. [Phase 2: Pension Inventory & Classification](#4-phase-2-pension-inventory--classification)
5. [Phase 3: Accumulation Analysis](#5-phase-3-accumulation-analysis)
6. [Phase 4: State Pension Analysis](#6-phase-4-state-pension-analysis)
7. [Phase 5: Retirement Readiness Assessment](#7-phase-5-retirement-readiness-assessment)
8. [Phase 6: Decumulation Strategy](#8-phase-6-decumulation-strategy)
9. [Phase 7: Tax Optimisation in Retirement](#9-phase-7-tax-optimisation-in-retirement)
10. [Phase 8: Strategy Recommendations](#10-phase-8-strategy-recommendations)
11. [Phase 9: Scenario Modelling](#11-phase-9-scenario-modelling)
12. [Phase 10: Life Event Impacts](#12-phase-10-life-event-impacts)
13. [Phase 11: Spouse/Partner Coordination](#13-phase-11-spousepartner-coordination)
14. [Phase 12: Cross-Module Integration](#14-phase-12-cross-module-integration)
15. [Thresholds & Constants Reference](#15-thresholds--constants-reference)
16. [PLSA Retirement Living Standards](#16-plsa-retirement-living-standards)
17. [Decision Tree: Master Flow](#17-decision-tree-master-flow)
18. [Existing Codebase Mapping](#18-existing-codebase-mapping)
19. [Gaps: What Is Not Yet Built](#19-gaps-what-is-not-yet-built)

---

## 1. Engine Pipeline Overview

```
User Request (Retirement Analysis)
    |
    v
[Phase 1] DataReadinessGate ──── missing critical data? ──> STOP (return readiness blocks)
    |
    | data sufficient
    v
[Phase 2] PensionInventory ──> classify all pension types, aggregate values
    |
    v
[Phase 3] AccumulationAnalysis ──> contributions, AA check, carry forward, employer match, tax relief
    |
    v
[Phase 4] StatePensionAnalysis ──> NI record, forecast, deferral, voluntary contributions
    |
    v
[Phase 5] RetirementReadiness ──> projected income vs target, income gap, probability, on-track status
    |
    v
[Phase 6] DecumulationStrategy ──> drawdown vs annuity, PCLS, withdrawal rate, income layering
    |
    v
[Phase 7] TaxOptimisation ──> withdrawal ordering, PA usage, band management, spouse splitting
    |
    v
[Phase 8] StrategyRecommendations ──> prioritised actions (employer match → contributions → age → income target)
    |
    v
[Phase 9] ScenarioModelling ──> what-if scenarios (increased contributions, later retirement, lower target)
    |
    v
[Phase 10] LifeEventImpacts ──> career change, redundancy, divorce, illness, approaching retirement
    |
    v
[Phase 11] SpouseCoordination ──> combined planning, survivor benefits, pension sharing
    |
    v
[Phase 12] CrossModuleIntegration ──> ISA drawdown, property equity release, protection needs
    |
    v
[Output] Formatted response: summary, breakdown, recommendations, projections, scenarios
```

**Key principle:** The retirement engine must handle two fundamentally different phases:
- **Accumulation** (saving for retirement) — contribution optimisation, tax relief, growth projections
- **Decumulation** (spending in retirement) — income strategy, tax-efficient withdrawal, longevity risk

The trigger to switch emphasis from accumulation to decumulation MUST NOT be hardcoded. It is currently hardcoded at `years_to_retirement <= 10` in RetirementAgent. This must be moved to a configurable variable in `TaxConfigService` — accessed via `TaxConfigService::get('retirement.accumulation_to_decumulation_years')`. Default value: 10 years. This allows the threshold to be adjusted without code changes (e.g., if planning guidance evolves to suggest 15 years for glide path strategies).

---

## 2. User Context: Data Inputs Required

### 2.1 Personal Profile

| Field | Source | Required? | Used By | Current Model |
|-------|--------|-----------|---------|---------------|
| `date_of_birth` | `user.date_of_birth` | **Critical** | Age calc, years to retirement, SPA lookup | `User` |
| `gender` | `user.gender` | Important | Life expectancy, annuity rates, SPA | `User` |
| `marital_status` | `user.marital_status` | Important | Spouse coordination, joint annuity, survivor benefits | `User` |
| `current_age` | Calculated from DOB | **Critical** | All projections | `RetirementProfile.current_age` |
| `target_retirement_age` | User-entered | **Critical** | Years to retirement, projection horizon | `RetirementProfile` |
| `life_expectancy` | User-entered or actuarial | Important | Decumulation horizon, annuity decision | `RetirementProfile` |
| `spouse_life_expectancy` | User-entered | Optional | Joint planning, survivor income | `RetirementProfile` |
| `health_status` | Not currently captured | **GAP** | Enhanced annuity eligibility, early access | -- |
| `smoker_status` | Not currently captured | **GAP** | Enhanced annuity rates | -- |
| `uk_resident` | `user.uk_resident` | Important | Tax relief eligibility, pension access rules | `User` |

### 2.2 Employment & Income

| Field | Source | Required? | Used By | Current Model |
|-------|--------|-----------|---------|---------------|
| `employment_status` | `user.employment_status` | Important | Pension type eligibility, salary sacrifice | `User` |
| `current_annual_salary` | User-entered | **Critical** | Contribution %, AA tapering, tax band | `RetirementProfile` |
| `gross_annual_income` | All income sources | Important | Adjusted income calc, AA tapering | `User` profile |
| `net_income` | Calculated | Important | Target income default (75% of net) | Calculated |
| `employer_name` | Not explicitly captured | Low | Scheme identification | -- |
| `years_of_service` | Not explicitly captured | **GAP for DB** | DB pension projection | -- |
| `is_self_employed` | Derived from employment_status | Important | Pension type recommendations | Derived |
| `monthly_expenditure` | `user.monthly_expenditure` | Important | Retirement spending estimate | `User` |

### 2.3 Retirement Profile

| Field | Source | Required? | Used By | Current Model |
|-------|--------|-----------|---------|---------------|
| `target_retirement_income` | User-entered | **Critical** | Income gap analysis, required capital | `RetirementProfile` |
| `essential_expenditure` | User-entered | Important | Minimum income floor | `RetirementProfile` |
| `lifestyle_expenditure` | User-entered | Important | Discretionary income layer | `RetirementProfile` |
| `care_cost_annual` | User-entered | Optional | Late-life cost modelling | `RetirementProfile` |
| `care_start_age` | User-entered | Optional | Care cost timing | `RetirementProfile` |
| `prior_year_unused_allowance` | User-entered JSON | Important | Carry forward calculation | `RetirementProfile` |

### 2.4 DC Pension Data (per pension)

| Field | Source | Required? | Used By | Current Model |
|-------|--------|-----------|---------|---------------|
| `scheme_name` | User-entered | Display | Identification | `DCPension` |
| `scheme_type` | User-entered | Important | `workplace` / `sipp` / `personal` / `stakeholder` | `DCPension` |
| `provider` | User-entered | Display | Provider identification | `DCPension` |
| `pension_type` | User-entered | Important | Classification | `DCPension` |
| `current_fund_value` | User-entered | **Critical** | Pot projection | `DCPension` |
| `annual_salary` | User-entered | Important | Percentage-based contributions | `DCPension` |
| `employee_contribution_percent` | User-entered | Important | Total contribution calc | `DCPension` |
| `employer_contribution_percent` | User-entered | Important | Employer match analysis | `DCPension` |
| `employer_matching_limit` | User-entered | Important | Match optimisation | `DCPension` |
| `monthly_contribution_amount` | User-entered | Important | Fixed contribution calc | `DCPension` |
| `lump_sum_contribution` | User-entered | Optional | One-off contributions | `DCPension` |
| `investment_strategy` | User-entered | Display | Fund selection | `DCPension` |
| `platform_fee_percent` | User-entered | Important | Net growth rate | `DCPension` |
| `retirement_age` | User-entered | Important | Per-pension retirement age | `DCPension` |
| `risk_preference` | User-entered or inherited | Important | Growth rate assumption | `DCPension` |
| `has_custom_risk` | Boolean | Important | Override user risk profile | `DCPension` |
| `has_flexibly_accessed` | Boolean | **Critical** | MPAA trigger | `DCPension` |
| `flexible_access_date` | Date | Important | MPAA trigger date | `DCPension` |
| `beneficiary_id` / `beneficiary_name` | User-entered | Optional | Death benefits | `DCPension` |

**Holdings (polymorphic via Holding model):**

| Field | Used By |
|-------|---------|
| `security_name` | Portfolio analysis |
| `ticker` | Market data lookup |
| `asset_type` | Asset allocation |
| `current_value` | Valuation |
| `allocation_percent` | Diversification |
| `ocf_percent` | Fee analysis |

### 2.5 DB Pension Data (per pension)

| Field | Source | Required? | Used By | Current Model |
|-------|--------|-----------|---------|---------------|
| `scheme_name` | User-entered | Display | Identification | `DBPension` |
| `scheme_type` | User-entered | Important | `final_salary` / `career_average` / `public_sector` | `DBPension` |
| `accrued_annual_pension` | User-entered | **Critical** | Income projection | `DBPension` |
| `pensionable_service_years` | User-entered | Important | Context | `DBPension` |
| `pensionable_salary` | User-entered | Important | Context | `DBPension` |
| `normal_retirement_age` | User-entered | **Critical** | When pension starts | `DBPension` |
| `revaluation_method` | User-entered | Important | Growth of deferred pension | `DBPension` |
| `spouse_pension_percent` | User-entered | Important | Survivor benefits | `DBPension` |
| `lump_sum_entitlement` | User-entered | Important | PCLS from DB scheme | `DBPension` |
| `inflation_protection` | User-entered | Important | `cpi` / `rpi` / `fixed` / `none` | `DBPension` |

**Not currently captured (GAPS for DB):**

| Field | Purpose |
|-------|---------|
| `accrual_rate` | e.g. 1/60th, 1/80th — for projecting future accrual |
| `cetv_value` | Cash Equivalent Transfer Value — for transfer analysis |
| `guaranteed_minimum_pension` | GMP component (pre-1997 service) |
| `early_retirement_factor` | Reduction for taking pension before NRA |
| `late_retirement_factor` | Enhancement for deferring beyond NRA |
| `commutation_factor` | Rate for exchanging pension for lump sum |
| `scheme_funded_status` | PPF eligibility assessment |
| `has_guaranteed_annuity_rate` | Critical for transfer decision |

### 2.6 State Pension Data

| Field | Source | Required? | Used By | Current Model |
|-------|--------|-----------|---------|---------------|
| `ni_years_completed` | User-entered (from gov.uk) | **Critical** | Forecast calculation | `StatePension` |
| `ni_years_required` | Default 35 | Important | Gap analysis | `StatePension` |
| `state_pension_forecast_annual` | User-entered (from gov.uk) | **Critical** | Income projection | `StatePension` |
| `state_pension_age` | User-entered or calculated | **Critical** | When SP starts, bridge strategy | `StatePension` |
| `already_receiving` | Boolean | Important | Already retired path | `StatePension` |
| `ni_gaps` | JSON array | Important | Gap-fill analysis | `StatePension` |
| `gap_fill_cost` | Calculated | Important | Cost-benefit of voluntary NI | `StatePension` |

**Not currently captured (GAPS):**

| Field | Purpose |
|-------|---------|
| `is_deferring` | Whether user has chosen to defer |
| `deferral_start_date` | When deferral began |
| `additional_state_pension` | SERPS / S2P entitlement (pre-2016) |
| `protected_payment` | If new SP > full rate due to old system |
| `contracted_out_years` | Years contracted out of SERPS/S2P |

### 2.7 Other Assets for Retirement Income

| Asset Type | Source | Tax Treatment | Current Model |
|------------|--------|---------------|---------------|
| Stocks & Shares ISA | `InvestmentAccount` | Tax-free withdrawals | `RetirementIncomeService` |
| Cash ISA | `SavingsAccount` | Tax-free withdrawals | `RetirementIncomeService` |
| Investment Bond | `InvestmentAccount` | 5% cumulative tax-deferred | `RetirementIncomeService` |
| General Investment Account | `InvestmentAccount` | CGT + income tax | `RetirementIncomeService` |
| Savings Accounts | `SavingsAccount` | PSA applies | `RetirementIncomeService` |
| Property (rental income) | `Property` | Income tax | Not yet integrated |
| Property (equity release) | `Property` | Capital, not income | Not yet integrated |

---

## 3. Phase 1: Data Readiness Gate

Before any retirement analysis can proceed, minimum data must exist.

### 3.1 Critical Data Requirements

**CRITICAL: No RetirementProfile abstraction.** The readiness gate checks for ACTUAL user data, not a bundled "retirement profile" entity. We do NOT auto-create profiles with defaults. If data is missing, we tell the user exactly what is needed and why.

```
START — Check each data point individually (no profile abstraction)
  |
  v
[Check 1] BLOCKING: Does user have date_of_birth?
  |
  +-- NO --> BLOCK: "Your date of birth is needed to calculate years to
  |          retirement, State Pension age, and pension access timing."
  |          can_proceed = false
  |
  +-- YES → continue
       |
       v
[Check 2] BLOCKING: Does user have marital_status?
  |
  +-- NO --> BLOCK: "Your marital status is needed to assess spouse
  |          pension benefits, survivor pensions, and joint annuity options."
  |          can_proceed = false
  |
  +-- YES → continue
       |
       v
[Check 3] BLOCKING: Does user have income data (gross_annual_income > 0)?
  |
  +-- NO --> BLOCK: "Your income details are needed to calculate pension
  |          contribution limits, tax relief, and retirement income targets."
  |          can_proceed = false
  |
  +-- YES → continue
       |
       v
[Check 4] Does user have at least one pension (DC, DB, or State)?
  |
  +-- NO --> WARN: "Add your pension details to begin retirement
  |          planning. Include workplace pensions, personal pensions,
  |          SIPPs, and any defined benefit schemes."
  |
  +-- YES → continue
       |
       v
[Check 5] Does user have a target retirement age?
  |
  +-- NO --> WARN: "Set your target retirement age. Without it, we
  |          use your State Pension age ({spa}) as default."
  |          (Use SPA from TaxConfigService, not hardcoded 67)
  |
  +-- YES → Validate against minimum pension age
       |
       v
[Check 6] Does user have target retirement income?
  |
  +-- NO --> WARN: "Set your target retirement income. Without it, we
  |          estimate using 75% of your current net income."
  |          (Proceed with calculated estimate — but flag it as estimated)
  |
  +-- YES → continue
       |
       v
[Check 7] Does user have monthly expenditure?
  |
  +-- NO --> INFO: "Add your monthly expenditure for more accurate
  |          retirement income projections."
  |
  +-- YES → continue
       |
       v
[Check 8] Does user have employment status?
  |
  +-- NO --> INFO: "Your employment status determines auto-enrolment
  |          eligibility, employer pension contributions, and salary
  |          sacrifice opportunities."
  |
  +-- YES → continue
       |
       v
[Check 9] Does user have a risk profile?
  |
  +-- NO --> INFO: "Complete your risk profile for personalised
  |          growth assumptions in retirement projections."
  |
  +-- YES → continue
       |
       v
[Check 10] Does user have State Pension forecast data?
  |
  +-- NO --> INFO: "Check your State Pension forecast at gov.uk for
  |          a more accurate projection."
  |
  +-- YES → continue
       |
       v
[Check 11] Is user married but no spouse linked?
  |
  +-- YES --> INFO: "Link your partner's account for combined
  |           retirement planning, pension sharing analysis, and
  |           survivor benefit assessment."
  |
  +-- NO → continue
       |
       v
[PROCEED to Phase 2]
```

### 3.2 Data Quality Warnings (non-blocking)

| Condition | Warning |
|-----------|---------|
| No State Pension data entered | "Check your State Pension forecast at gov.uk/check-state-pension for a more accurate projection." |
| DC pension with £0 fund value | "Update your pension value for {scheme_name} — it currently shows as £0." |
| No risk profile set | "Using medium risk as default. Complete your risk profile for personalised projections." |
| Retirement age < Minimum Pension Age | "You cannot access your pension before age {MPA}. Your target retirement age has been adjusted." |
| Retirement age > 75 | "Note: different tax rules apply to pension access after age 75." |
| No monthly expenditure set | "Set your monthly spending to help estimate your retirement income needs." |

---

## 4. Phase 2: Pension Inventory & Classification

### 4.1 Pension Type Classification

```
FOR EACH pension the user holds:
    |
    CLASSIFY by type:
    |
    ├── DC (Defined Contribution) ──> scheme_type?
    |   ├── workplace ──> Auto-enrolment eligible, employer contributions
    |   ├── sipp ──> Self-Invested Personal Pension, full investment choice
    |   ├── personal ──> Provider-managed personal pension
    |   ├── stakeholder ──> Charge-capped (1.5% yr1, 1% after)
    |   ├── nest ──> National Employment Savings Trust (auto-enrolment default)
    |   ├── group_personal ──> Employer-facilitated personal pension
    |   ├── executive ──> Enhanced employer contributions
    |   └── ssas ──> Small Self-Administered Scheme (business owners)
    |
    ├── DB (Defined Benefit) ──> scheme_type?
    |   ├── final_salary ──> Pension based on final/best salary × accrual rate × years
    |   ├── career_average (CARE) ──> Pension based on average salary × accrual rate × years
    |   └── public_sector ──> NHS, Teachers, Civil Service, Armed Forces, Police, Fire
    |       (Each has specific rules: accrual rates, NRA, revaluation)
    |
    └── State Pension ──> One per user
        ├── New State Pension (post-6 April 2016)
        └── Basic + Additional (pre-6 April 2016, rarely applicable now)
```

### 4.2 Pension Aggregation

```
AGGREGATE across all pensions:
    |
    ├── Total DC fund value = SUM(dc_pensions.current_fund_value)
    ├── Total monthly contributions = SUM(employee + employer contributions)
    ├── Total annual contributions = monthly × 12 (for AA check)
    ├── Total projected DC value at retirement = SUM(projected values)
    ├── Total DB annual income = SUM(db_pensions.accrued_annual_pension) with revaluation
    ├── State Pension annual income = forecast or calculated from NI years
    └── Total pension count = DC count + DB count + (1 if State Pension)
```

### 4.3 Public Sector DB Scheme Reference

| Scheme | Accrual Rate | NRA | Revaluation | Pension Type |
|--------|-------------|-----|-------------|-------------|
| NHS (2015) | 1/54th | SPA | CPI + 1.5% | CARE |
| Teachers (2015) | 1/57th | SPA | CPI + 1.6% | CARE |
| Civil Service (alpha) | 1/43.1th (2.32%) | SPA | CPI | CARE |
| Armed Forces (2015) | 1/47th | 60 | CPI | CARE |
| Police (2015) | 1/55.3th | 60 | CPI + 1.25% | CARE |
| Fire (2015) | 1/59.7th | 60 | CPI + 1.25% | CARE |
| Local Government (2014) | 1/49th | SPA | CPI | CARE |
| Judges | 1/40th | 65 | CPI | CARE |
| NHS (1995 section) | 1/80th + 3× lump sum | 60 | CPI | Final Salary |
| NHS (2008 section) | 1/60th | 65 | CPI | Final Salary |
| Teachers (pre-2015) | 1/80th or 1/60th | 60/65 | CPI | Final Salary |
| Civil Service (classic) | 1/80th + 3× lump sum | 60 | CPI | Final Salary |

---

## 5. Phase 3: Accumulation Analysis

### 5.1 Annual Allowance Check

**Service:** `AnnualAllowanceChecker` | **File:** `app/Services/Retirement/AnnualAllowanceChecker.php`

```
CALCULATE total annual contributions:
    FOR EACH DC pension:
        IF monthly_contribution_amount > 0:
            annual = monthly × 12
        ELSE:
            annual = annual_salary × (employee% + employer%) / 100
        ENDIF
        total += annual
    ENDFOR

    (NOTE: Both employee AND employer contributions count towards AA)
    |
    v
CHECK Annual Allowance tapering:
    |
    ├── threshold_income <= £200,000 AND adjusted_income <= £260,000?
    |   YES ──> Standard AA = £60,000
    |
    ├── threshold_income > £200,000 AND adjusted_income > £260,000?
    |   YES ──> Calculate tapered AA:
    |           reduction = (adjusted_income - £260,000) / 2
    |           tapered_AA = max(£10,000, £60,000 - reduction)
    |
    └── NOTE on income definitions:
        threshold_income = net income (before pension contributions)
                         = total taxable income - any tax relief on personal contributions
        adjusted_income  = threshold_income + employer pension contributions
                         + employee contributions (gross)
    |
    v
CHECK carry forward:
    Look back 3 previous tax years (e.g. 2022/23, 2023/24, 2024/25 for 2025/26)
    carry_forward = SUM(unused AA from each prior year)
    (Requires user-entered data in RetirementProfile.prior_year_unused_allowance)
    |
    v
CALCULATE remaining allowance:
    available = current_year_AA + carry_forward
    used = total_contributions
    remaining = max(0, available - used)
    excess = max(0, used - available)
    |
    v
IF excess > 0:
    ──> WARNING: "You have exceeded your annual allowance by £{excess}.
         This will be added to your income and taxed at your marginal rate.
         Annual allowance charge applies."
```

#### Annual Allowance Tapering Detail

```
Standard AA: £60,000

Tapering triggers when BOTH conditions met:
  1. Threshold income > £200,000
  2. Adjusted income > £260,000

Reduction: £1 for every £2 of adjusted income above £260,000
Floor: £10,000 (reached at adjusted income of £360,000)

Example calculations:
  Adjusted income £280,000 → reduction = (280k - 260k) / 2 = £10,000 → AA = £50,000
  Adjusted income £300,000 → reduction = (300k - 260k) / 2 = £20,000 → AA = £40,000
  Adjusted income £360,000 → reduction = (360k - 260k) / 2 = £50,000 → AA = £10,000
  Adjusted income £400,000 → reduction = £50,000 (capped) → AA = £10,000
```

### 5.2 Money Purchase Annual Allowance (MPAA)

**Service:** `AnnualAllowanceChecker.checkMPAA()`

```
HAS user flexibly accessed any DC pension?
    (DCPension.has_flexibly_accessed = true)
    |
    NO ──> Standard AA applies (£60,000 or tapered)
    |
    YES ──> MPAA triggered
            |
            ├── Money purchase contributions limited to £10,000/year
            ├── No carry forward available for money purchase contributions
            ├── DB accrual still uses alternative AA (£50,000 minus MP contributions, floor £10,000)
            └── Trigger date recorded for compliance

MPAA triggers:
  - Taking income from flexi-access drawdown (beyond tax-free lump sum)
  - Taking an Uncrystallised Funds Pension Lump Sum (UFPLS)
  - Taking a stand-alone lump sum from a money purchase arrangement

MPAA does NOT trigger:
  - Taking tax-free lump sum only (without drawdown income)
  - Buying an annuity
  - Taking a small pot lump sum (pot under £10,000)
  - Taking a trivial commutation (total pension wealth under £30,000)
  - Capped drawdown (pre-April 2015, if staying within cap)
```

### 5.3 Carry Forward Rules

```
CARRY FORWARD — use unused AA from previous 3 tax years

Rules:
  1. Must have been a member of a registered pension scheme in each year claimed
  2. Use oldest year's unused AA first
  3. Must use current year's AA first before carry forward
  4. Cannot carry forward if MPAA applies to money purchase contributions
  5. Carry forward applies per person, not per scheme

Example (2025/26 tax year, standard AA £60,000):
  ┌──────────┬─────────────┬────────────────┬──────────────┐
  │ Tax Year │ AA Available │ Contributions  │ Unused       │
  ├──────────┼─────────────┼────────────────┼──────────────┤
  │ 2022/23  │ £60,000     │ £20,000        │ £40,000      │
  │ 2023/24  │ £60,000     │ £30,000        │ £30,000      │
  │ 2024/25  │ £60,000     │ £25,000        │ £35,000      │
  │ 2025/26  │ £60,000     │ Current year   │ --           │
  ├──────────┼─────────────┼────────────────┼──────────────┤
  │ TOTAL    │             │                │ CF = £105,000│
  └──────────┴─────────────┴────────────────┴──────────────┘
  Max contribution in 2025/26 = £60,000 + £105,000 = £165,000
  (Subject to having sufficient relevant UK earnings)
```

### 5.4 Employer Match Optimisation

**Service:** `ContributionOptimizer.checkEmployerMatch()`

```
FOR EACH workplace DC pension:
    |
    GET employer_contribution_percent
    GET employee_contribution_percent
    GET employer_matching_limit (if set)
    |
    IS employee contributing enough to maximise employer match?
    |
    ├── YES ──> "You are maximising your employer pension match."
    |
    └── NO ──> Calculate gap:
              additional_needed = matching_threshold - employee_contribution%
              potential_gain_annual = (additional_needed/100) × annual_salary
              |
              ──> RECOMMENDATION (HIGH priority):
                  "Increase your contribution by {additional_needed}% to maximise
                   employer match. This is effectively free money worth £{gain}/year."
```

#### Common Employer Matching Patterns

| Pattern | Employee Pays | Employer Pays | Total |
|---------|--------------|---------------|-------|
| Auto-enrolment minimum | 5% | 3% | 8% |
| Common match | 5% | 5% | 10% |
| Generous match | 5% | 10% | 15% |
| Enhanced match | 3% | 2× (6%) | 9% |
| Tiered: first 3% | 3% | 1:1 (3%) | 6% |
| Tiered: next 2% | +2% | 0.5:1 (+1%) | +3% |
| Cap match | Up to 8% | matches up to 8% | 16% |

### 5.5 Salary Sacrifice Analysis

```
IS employer offering salary sacrifice for pension contributions?
    |
    ├── YES ──> Calculate NI savings:
    |           employee_NI_saving = contribution × 8% (Class 1 employee rate 2025/26)
    |           employer_NI_saving = contribution × 13.8% (Class 1 employer rate)
    |
    |           Some employers pass on their NI saving as additional pension contribution
    |
    |           NET COST to employee = contribution - employee_NI_saving
    |           (Salary sacrifice costs less than personal contribution for same gross amount)
    |
    |   BUT CHECK impacts:
    |   ├── Salary after sacrifice < £2,000 (National Living Wage floor)?
    |   │   BLOCK: "Under current budget rules, salary sacrifice cannot reduce your
    |   │          pay below £2,000 per annum. This is the lower bound limit."
    |   │   (Source: TaxConfigService::get('pension.salary_sacrifice_floor'))
    |   ├── Salary after sacrifice < £12,570 (Personal Allowance)?
    |   │   WARNING: "Salary sacrifice would reduce your pay below the personal allowance.
    |   │            You would lose tax-free income capacity."
    |   ├── Salary after sacrifice < £6,396 (NI Lower Earnings Limit)?
    |   │   WARNING: "This could affect your State Pension qualifying year.
    |   │            Below the Lower Earnings Limit, this year may not count
    |   │            towards your 35 qualifying years."
    |   ├── Salary after sacrifice < employer's contractual minimum?
    |   │   WARNING: "Check your employment contract for minimum salary requirements."
    |   ├── Impact on mortgage applications (lower reported salary)?
    |   │   NOTE: "Salary sacrifice reduces your contractual pay which may affect
    |   │          mortgage affordability assessments."
    |   ├── Impact on statutory pay (maternity, paternity, sick pay)?
    |   │   NOTE: "Statutory pay is based on qualifying earnings. Salary sacrifice
    |   │          may reduce statutory maternity/paternity/sick pay entitlement."
    |   └── Impact on death-in-service benefit (if multiple of salary)?
    |       NOTE: "If death-in-service benefit is a multiple of salary, salary sacrifice
    |              will reduce this benefit."
    |
    └── NO / Net Pay / Relief at Source:
        Calculate tax relief method:
        ├── Net Pay: tax relief applied before income tax deducted (workplace schemes)
        │   No further action needed — relief is automatic
        ├── Relief at Source: contribution from net pay, provider claims 20% back from HMRC
        │   ├── Basic rate taxpayer: fully covered
        │   ├── Higher rate taxpayer: must claim extra 20% via Self Assessment
        │   └── Additional rate taxpayer: must claim extra 25% via Self Assessment
        └── NOTE: Non-taxpayers can contribute up to £2,880 net (£3,600 gross) with RAS
```

### 5.6 Tax Relief on Pension Contributions

**Service:** `ContributionOptimizer.calculateTaxRelief()`

```
CALCULATE tax relief based on marginal rate:

┌──────────────────────┬───────────┬─────────────────────────────────────────┐
│ Tax Band             │ Rate      │ Relief on £1,000 gross contribution     │
├──────────────────────┼───────────┼─────────────────────────────────────────┤
│ Basic rate           │ 20%       │ £200 (automatic via RAS or net pay)     │
│ Higher rate          │ 40%       │ £400 (£200 auto + £200 via SA)          │
│ Additional rate      │ 45%       │ £450 (£200 auto + £250 via SA)          │
│ Scottish starter 19% │ 19%       │ £190                                    │
│ Scottish basic 20%   │ 20%       │ £200                                    │
│ Scottish inter 21%   │ 21%       │ £210                                    │
│ Scottish higher 42%  │ 42%       │ £420                                    │
│ Scottish advanced 45%│ 45%       │ £450                                    │
│ Scottish top 48%     │ 48%       │ £480                                    │
│ Non-taxpayer         │ 0%        │ £200 (20% basic rate relief still given)│
└──────────────────────┴───────────┴─────────────────────────────────────────┘

MAXIMUM contribution with tax relief:
  - Limited to 100% of relevant UK earnings (employment + self-employment income)
  - OR £3,600 gross (for non-earners/low earners) — whichever is higher
  - PLUS carry forward from 3 previous tax years
  - Subject to Annual Allowance (£60,000 or tapered amount)

RELEVANT UK EARNINGS includes:
  - Employment income (salary, wages, bonus, commission)
  - Self-employment profits
  - Patent income
  - Furnished holiday lettings income (until April 2025)

DOES NOT include:
  - Rental income (other than FHL)
  - Dividend income
  - Investment income
  - Pension income
  - Social security benefits
```

### 5.7 Contribution vs Mortgage Overpayment Decision

```
DOES user have a mortgage AND pension contributions below AA?
    |
    YES ──> Compare effective returns:
    |
    ├── Pension contribution effective return:
    │   = investment_growth_rate + tax_relief_rate + employer_match_rate
    │   Example (higher rate, 5% growth, 5% match):
    │     Effective = 5% + 40% + 5% = 50% first-year return
    │     (Ongoing growth at 5% thereafter)
    │
    ├── Mortgage overpayment effective return:
    │   = mortgage_interest_rate (guaranteed, risk-free saving)
    │   Example: 4.5% mortgage rate = 4.5% guaranteed return
    │
    └── DECISION:
        IF employer_match_not_maximised:
            ──> "Maximise employer match first — this provides an immediate,
                 guaranteed return that no mortgage overpayment can match."
        ELIF mortgage_rate > expected_growth_net_of_fees:
            ──> "Your mortgage rate ({rate}%) exceeds expected investment returns.
                 Consider overpaying your mortgage for a guaranteed return."
        ELIF tax_relief = higher_or_additional:
            ──> "As a {band} rate taxpayer, pension contributions provide
                 {relief}% tax relief. This likely outweighs mortgage overpayment."
        ELSE:
            ──> "Consider splitting between pension and mortgage overpayment.
                 Pension for tax relief, mortgage for guaranteed savings."
```

### 5.8 Pension Consolidation Analysis

```
COUNT user's DC pensions:
    |
    ├── 1 pension ──> No consolidation needed
    |
    ├── 2-3 pensions ──> "Consider consolidating for simpler management"
    |
    └── 4+ pensions ──> "Strongly consider consolidating — multiple small pots
                          are harder to manage and may have higher combined fees"
    |
    v
FOR EACH pension, CHECK for consolidation risks:
    |
    ├── Has guaranteed annuity rate (GAR)?
    │   ──> WARNING: "This pension may have a guaranteed annuity rate.
    │        Transferring would lose this valuable benefit. Check with provider."
    │
    ├── Has protected tax-free cash > 25%?
    │   ──> WARNING: "This pension may have protected tax-free cash above 25%.
    │        Transferring could lose this protection."
    │
    ├── Is a DB pension?
    │   ──> WARNING: "Transferring a Defined Benefit pension requires regulated advice
    │        for pots over £30,000. DB pensions provide guaranteed income —
    │        transfer analysis is complex."
    │
    ├── Has employer contributions still being paid?
    │   ──> WARNING: "This is your active workplace pension. Transferring could
    │        stop employer contributions. Only transfer old workplace pensions."
    │
    ├── Has exit penalties or market value reductions?
    │   ──> NOTE: "Check for any exit penalties or MVR before transferring."
    │
    └── NO risks found:
        CALCULATE consolidation benefit:
        ├── Fee comparison: current weighted avg fee vs target pension fee
        ├── Annual saving = (current_fees - target_fees) × total_value
        ├── Investment choice improvement
        └── ──> "Consolidating could save £{saving}/year in fees and simplify
                 your retirement planning."
```

---

## 6. Phase 4: State Pension Analysis

### 6.1 State Pension Age Determination

```
DETERMINE State Pension age based on date of birth:

┌─────────────────────────────────┬──────────────┐
│ Date of Birth                   │ SPA          │
├─────────────────────────────────┼──────────────┤
│ Before 6 March 1961             │ 66           │
│ 6 March 1961 – 5 April 1977    │ 67           │
│   (phased increase 66→67       │              │
│    between 2026–2028)          │              │
│ 6 April 1977 – 5 April 1960   │ 67           │
│ After 5 April 1960             │ 68           │
│   (phased increase 67→68,     │              │
│    timing under review,        │              │
│    currently 2044–2046)        │              │
└─────────────────────────────────┴──────────────┘

Note: SPA is subject to government review. The 2017 Cridland Review recommended
SPA of 68 by 2037-39 but this was not implemented. Current legislation has 68
by 2044-2046. Future reviews may accelerate or delay this.
```

### 6.2 State Pension Forecast Calculation

**Service:** `PensionProjector.projectStatePension()`

```
IF user has entered state_pension_forecast_annual:
    USE that value directly (most accurate — from gov.uk)
ELSE:
    CALCULATE from NI years:

    full_new_state_pension = £11,973/year (2025/26) = £230.25/week
    qualifying_years_for_full = 35
    minimum_qualifying_years = 10

    IF ni_years_completed < 10:
        state_pension = £0 ("You need at least 10 qualifying years")
    ELSE:
        state_pension = (ni_years_completed / 35) × £11,973
        (Pro-rated, capped at full amount)
    ENDIF
```

### 6.3 State Pension Gap Analysis

```
CHECK NI record for gaps:
    |
    years_remaining = SPA - current_age
    projected_total_years = ni_years_completed + years_remaining
    |
    ├── projected_total >= 35?
    │   ──> "You are on track for the full State Pension."
    │
    ├── projected_total >= 10 AND < 35?
    │   ──> gap = 35 - projected_total
    │       "You have {gap} year(s) of NI contributions to fill.
    │        Each qualifying year adds approximately £342/year to your State Pension."
    │   |
    │   CHECK if gaps can be filled:
    │   ├── Are there fillable gaps in the last 6 years? (Normal deadline)
    │   │   ──> Cost per year (Class 3 voluntary NI): £907.80/year (2025/26)
    │   │       = £17.45/week × 52
    │   │
    │   ├── Extended deadline (until 5 April 2025 — now expired for most):
    │   │   Gaps from 2006-07 to 2017-18 could be filled at historic rates
    │   │   (This deadline was extended but has now largely passed)
    │   │
    │   └── COST-BENEFIT analysis:
    │       cost_to_fill = gap_years × £907.80
    │       annual_increase = gap_years × (£11,973 / 35) = gap_years × £342
    │       breakeven_years = cost_to_fill / annual_increase
    │
    │       IF breakeven_years < 5:
    │           ──> "Filling NI gaps is excellent value. You would recoup the cost
    │                in approximately {breakeven} years."
    │       ELIF breakeven_years < 10:
    │           ──> "Filling NI gaps represents good value based on average life expectancy."
    │       ELSE:
    │           ──> "Consider whether filling NI gaps is worthwhile given the longer
    │                payback period. This depends on your health and life expectancy."
    │
    └── projected_total < 10?
        ──> WARNING: "Without at least 10 qualifying years, you will not receive
             any State Pension. Consider making voluntary NI contributions."
```

### 6.4 State Pension Deferral Analysis

```
IS user at or past State Pension age?
    |
    NO ──> Deferral analysis is forward-looking
    |
    YES ──> IS user choosing to defer?
            |
            ├── YES ──> Calculate deferral benefit:
            │
            │   Deferral rate (new State Pension):
            │   = 1% for every 9 weeks deferred
            │   = approximately 5.8% per year
            │   = paid as higher regular pension (no lump sum option under new rules)
            │
            │   Example:
            │   Full SP = £11,973/year
            │   Defer 1 year: increase = £11,973 × 5.8% = £694/year extra (for life)
            │   Defer 2 years: increase = £11,973 × 11.6% = £1,389/year extra
            │
            │   BREAKEVEN analysis:
            │   years_to_breakeven = (years_deferred × annual_SP) / annual_increase
            │   1 year deferral: breakeven ≈ 17.2 years after starting to claim
            │
            │   FACTORS to consider:
            │   ├── Health and life expectancy
            │   │   IF poor health → "Deferral is less likely to pay off"
            │   │   IF good health → "Deferral can provide significantly more income over your lifetime"
            │   ├── Other income during deferral period
            │   │   IF sufficient other income → "You can afford to defer"
            │   │   IF relying on SP → "Deferral may not be practical"
            │   ├── Tax implications
            │   │   Higher SP may push into higher tax bracket
            │   │   "The increased State Pension will be taxable income.
            │   │    Check whether the higher amount pushes you into a higher tax band."
            │   └── Inflation protection
            │       Deferred SP still benefits from triple lock increases
            │
            └── NO ──> "Claim your State Pension at your State Pension age unless
                        you have other sufficient income and are in good health."
```

---

## 7. Phase 5: Retirement Readiness Assessment

### 7.1 Retirement Income Projection

**Services:** `PensionProjector`, `RetirementProjectionService`

```
PROJECT total retirement income:
    |
    ├── DC PENSION INCOME:
    │   FOR EACH DC pension:
    │       growth_rate = risk-based return (from RiskPreferenceService)
    │       net_growth = growth_rate - platform_fee_percent
    │       years = target_retirement_age - current_age
    │
    │       projected_value = FV of current value + FV of future contributions
    │       FV_current = current_value × (1 + net_growth)^years
    │       FV_contributions = annual_contrib × [((1 + net_growth)^years - 1) / net_growth]
    │
    │   total_DC_value = SUM(all projected values)
    │   dc_annual_income = total_DC_value × 4% (safe withdrawal rate)
    │
    ├── DB PENSION INCOME:
    │   FOR EACH DB pension:
    │       revaluation_rate = based on inflation_protection type:
    │           CPI → 2.5%
    │           RPI → 3.0%
    │           Fixed → parsed from revaluation_method
    │           None → 0%
    │           Default → 2.0%
    │
    │       IF years_to_NRA > 0:
    │           projected_income = accrued_pension × (1 + revaluation)^years_to_NRA
    │       ELSE:
    │           projected_income = accrued_pension (already at NRA)
    │
    │   total_DB_income = SUM(all projected DB incomes)
    │
    ├── STATE PENSION INCOME:
    │   = forecast_annual OR (ni_years / 35) × full_SP
    │   NOTE: Only included from State Pension Age onwards
    │
    └── TOTAL at retirement:
        IF retiring_before_SPA:
            income_at_retirement = dc_income + db_income  (no SP yet)
            income_after_SPA = dc_income + db_income + state_pension
        ELSE:
            income_at_retirement = dc_income + db_income + state_pension
```

### 7.2 Monte Carlo Projection (for DC pensions)

**Service:** `RetirementProjectionService.projectPensionPot()`

```
MONTE CARLO simulation:
    iterations = 1,000

    FOR EACH iteration:
        FOR EACH year until retirement:
            annual_return = random from normal distribution(expected_return, volatility)
            pot_value = pot_value × (1 + annual_return) + annual_contributions
            + scheduled_life_event_cash_flows (lump sums in/out)
        ENDFOR
        record final pot value
    ENDFOR

    EXTRACT percentile bands:
    ├── 10th percentile (90% probability of achieving or exceeding)
    ├── 15th percentile
    ├── 20th percentile (80% probability — USED FOR CONSERVATIVE PROJECTIONS)
    ├── 25th percentile
    ├── 50th percentile (median — equal chance of better or worse)
    ├── 75th percentile
    └── 90th percentile (10% probability — optimistic scenario)

    PRIMARY projection uses 20th percentile (80% confidence level)
    This means: "There is an 80% chance your pot will be at least this much"
```

### 7.3 Income Gap Analysis

**Service:** `RetirementAgent.analyze()`

```
CALCULATE income gap:
    |
    target_income = RetirementProfile.target_retirement_income
                    OR (net_income × 75%)
    |
    projected_income = dc_income + db_income + state_pension (if at SPA)
    |
    income_gap = max(0, target_income - projected_income)
    |
    ├── income_gap = 0?
    │   ──> STATUS: "On Track" / "Excellent"
    │       "Your projected retirement income meets or exceeds your target."
    │
    ├── income_gap > 0 AND retiring_before_SPA?
    │   ──> Show TWO gaps:
    │       gap_before_SPA = target - (dc + db)
    │       gap_after_SPA = target - (dc + db + sp)
    │       "Before State Pension: income gap of £{gap1}/year
    │        After State Pension: income gap of £{gap2}/year"
    │
    └── income_gap > 0?
        ──> STATUS: based on probability (see 7.4)
            "Your projected income falls short of your target by £{gap}/year.
             See strategies below to close this gap."
```

### 7.4 On-Track Status Classification

**Service:** `RetirementProjectionService.determineOnTrackStatus()`

```
CALCULATE probability from income ratio and longevity:

income_ratio = projected_income / target_income

┌─────────────────┬──────────────────┬──────────────────┐
│ Income Ratio    │ Base Probability │ Status           │
├─────────────────┼──────────────────┼──────────────────┤
│ >= 1.00         │ 95%              │ Excellent        │
│ >= 0.90         │ 85%              │ On Track         │
│ >= 0.75         │ 65%              │ Needs Attention  │
│ >= 0.50         │ 40%              │ Off Track        │
│ >= 0.25         │ 20%              │ Significantly    │
│                 │                  │ Off Track        │
│ < 0.25          │ 10%              │ Critical         │
└─────────────────┴──────────────────┴──────────────────┘

Longevity bonus:
  years_before_depletion >= 35 → +5%
  years_before_depletion >= 25 → +3%

Final probability = min(100, base + longevity_bonus)
```

### 7.5 Required Capital Calculation

**Service:** `RequiredCapitalCalculator`

```
CALCULATE required capital at retirement:

  required_income = target_income from profile OR 75% of (gross_income - pension_contributions)
  withdrawal_rate = 4.7% (sustainable withdrawal rate)

  required_capital = required_income / withdrawal_rate

  Example:
    Target income = £30,000/year
    Required capital = £30,000 / 0.047 = £638,298

CALCULATE present value (what that means in today's money):
  inflation_rate = 2.5%
  years_to_retirement = 25

  present_value = required_capital / (1 + 0.025)^25
                = £638,298 / 1.854 = £344,284

YEAR-BY-YEAR projection:
  Uses compound interest with contributions:
  FV = PV × (1 + r/m)^(m×n) + PMT × [((1 + r/m)^(m×n) - 1) / (r/m)]

  Where:
    PV = current pension pot value
    r = net return rate (growth - fees)
    m = compounding periods per year (default: 4 = quarterly)
    n = years
    PMT = contribution per compounding period
```

### 7.6 PLSA Retirement Living Standards Comparison

```
COMPARE projected income against PLSA benchmarks:

┌─────────────┬──────────────┬──────────────┬────────────────────────────────────┐
│ Standard    │ Single       │ Couple       │ Includes                           │
├─────────────┼──────────────┼──────────────┼────────────────────────────────────┤
│ Minimum     │ £14,400      │ £22,400      │ Food, shelter, clothing, basic     │
│             │              │              │ transport, basic social activities │
├─────────────┼──────────────┼──────────────┼────────────────────────────────────┤
│ Moderate    │ £31,300      │ £43,100      │ Above + holidays in Europe,       │
│             │              │              │ hobbies, eating out, nicer car     │
├─────────────┼──────────────┼──────────────┼────────────────────────────────────┤
│ Comfortable │ £43,100      │ £59,000      │ Above + long-haul holidays,       │
│             │              │              │ regular beauty treatments, gym,    │
│             │              │              │ new car, charitable giving         │
└─────────────┴──────────────┴──────────────┴────────────────────────────────────┘

(PLSA figures updated 2024 — should be verified annually)

CLASSIFY user's projected income:
  IF income >= comfortable_threshold:
      "Your projected income supports a Comfortable retirement lifestyle."
  ELIF income >= moderate_threshold:
      "Your projected income supports a Moderate retirement lifestyle."
  ELIF income >= minimum_threshold:
      "Your projected income supports a Minimum retirement lifestyle."
  ELSE:
      "Your projected income falls below the Minimum retirement standard.
       Urgent action is needed."
```

### 7.7 Replacement Ratio Analysis

```
CALCULATE income replacement ratio:
    ratio = (projected_retirement_income / current_gross_income) × 100

Common targets:
  ┌─────────────────┬────────────────┬──────────────────────────────────────┐
  │ Income Level     │ Target Ratio  │ Rationale                            │
  ├─────────────────┼────────────────┼──────────────────────────────────────┤
  │ Below £30k      │ 70-80%         │ Lower earners spend more on basics  │
  │ £30k - £50k     │ 60-70%         │ Standard target                     │
  │ £50k - £100k    │ 50-67%         │ Higher earners save/pay more tax    │
  │ Above £100k     │ 50%            │ Lifestyle fully fundable at lower % │
  └─────────────────┴────────────────┴──────────────────────────────────────┘

WHY less than 100%:
  - No pension contributions in retirement
  - No NI contributions in retirement
  - No commuting / work expenses
  - Mortgage may be paid off
  - Lower marginal tax rate likely
  - No childcare costs

  BUT:
  + Healthcare costs may increase
  + Leisure spending may increase initially
  + Inflation erodes purchasing power
  + Care costs in later years
```

---

## 8. Phase 6: Decumulation Strategy

### 8.1 Pension Access Methods Decision Tree

```
USER reaches pension access age (currently 55, rising to 57 from 6 April 2028):
    |
    WHAT are the access options?
    |
    ├── 1. TAX-FREE LUMP SUM (PCLS)
    │   └── 25% of crystallised pension, up to £268,275 (Lump Sum Allowance)
    │
    ├── 2. FLEXI-ACCESS DRAWDOWN
    │   └── Move pot to drawdown fund, take income as needed
    │       (Triggers MPAA for future contributions)
    │
    ├── 3. ANNUITY PURCHASE
    │   └── Exchange pot for guaranteed income for life
    │
    ├── 4. UNCRYSTALLISED FUNDS PENSION LUMP SUM (UFPLS)
    │   └── Ad-hoc lump sums: 25% tax-free, 75% taxed at marginal rate
    │       (Triggers MPAA)
    │
    ├── 5. SMALL POT RULES
    │   └── Pots under £10,000: take as lump sum (25% tax-free, 75% taxed)
    │       Up to 3 small pots from different non-occupational schemes
    │       (Does NOT trigger MPAA)
    │
    ├── 6. TRIVIAL COMMUTATION
    │   └── Total pension wealth under £30,000: take all as lump sum
    │       (25% tax-free, 75% taxed)
    │
    └── 7. PHASED RETIREMENT
        └── Crystallise in stages over time to maximise tax-free lump sums
            and manage tax bands year by year
```

### 8.2 PCLS (Tax-Free Cash) Strategy

**Service:** `DecumulationPlanner.calculatePCLSStrategy()`

```
CALCULATE PCLS options:
    |
    pension_value = total DC pension pot
    pcls_amount = min(pension_value × 25%, £268,275)
    remaining_pot = pension_value - pcls_amount
    |
    OPTIONS:
    |
    ├── A. Take full PCLS upfront
    │   PROS: Immediate tax-free cash, repay debts, invest elsewhere
    │   CONS: Reduces pension pot, less income from drawdown
    │   BEST WHEN: Have specific use (mortgage repayment, debt clearing),
    │              or want to invest tax-efficiently in ISA
    │
    ├── B. Take PCLS in stages (phased crystallisation)
    │   PROS: Spreads tax-free cash over years, manages tax bands
    │   CONS: More complex administration
    │   BEST WHEN: Want to maximise tax efficiency year by year,
    │              gradually transitioning to retirement
    │
    ├── C. Don't take PCLS (leave invested)
    │   PROS: Larger pot for drawdown/annuity, more growth potential
    │   CONS: Miss out on tax-free extraction
    │   BEST WHEN: Don't need the cash, pot is small relative to income needs
    │
    └── D. Take PCLS and invest in ISA (Bed & SIPP)
        PROS: Tax-free growth in ISA, flexible access
        CONS: Uses ISA allowance (£20,000/year limit)
        BEST WHEN: Have ISA allowance remaining, want tax-efficient growth
```

### 8.3 Drawdown vs Annuity Decision

**Service:** `DecumulationPlanner.compareAnnuityVsDrawdown()`

```
DECISION FACTORS:
    |
    ├── AGE at retirement:
    │   < 65: Drawdown strongly favoured (annuity rates poor, long time horizon)
    │   65-74: Either viable, depends on other factors
    │   75+: Annuity becomes more attractive (higher rates, longevity risk)
    │
    ├── HEALTH status:
    │   Good health: Annuity may be poor value (expect to live long, but rates assume average)
    │   Poor health: Enhanced annuity rates significantly better
    │                (smoker, diabetes, heart conditions, cancer history)
    │   NOTE: Enhanced annuities can pay 20-40% more than standard rates
    │
    ├── GUARANTEED INCOME FLOOR:
    │   State Pension + DB pensions + any existing annuities = guaranteed_base
    │   IF guaranteed_base >= essential_expenditure:
    │       Drawdown more appropriate for discretionary income layer
    │   IF guaranteed_base < essential_expenditure:
    │       Annuity to secure essential spending floor
    │
    ├── POT SIZE:
    │   < £50,000: Annuity income too small to be meaningful, drawdown for flexibility
    │   £50,000-£100,000: Marginal — consider partial annuity
    │   £100,000-£250,000: Both viable
    │   > £250,000: Drawdown offers significant flexibility advantage
    │
    ├── RISK TOLERANCE:
    │   Low: Annuity provides certainty
    │   Medium: Hybrid approach
    │   High: Drawdown with growth potential
    │
    ├── DEATH BENEFITS:
    │   Want to leave inheritance: Drawdown (remaining pot passes to beneficiaries,
    │       potentially tax-free if death before 75)
    │   Don't need to leave inheritance: Annuity viable
    │   Have spouse to provide for: Joint-life annuity or drawdown with expression of wish
    │
    └── FLEXIBILITY NEED:
        Need variable income: Drawdown
        Predictable income preferred: Annuity
```

#### Annuity Types & Options

```
ANNUITY OPTIONS:
    |
    ├── LEVEL ANNUITY
    │   Fixed income for life, no inflation protection
    │   Highest initial income, but purchasing power erodes
    │   Example: £100k pot at 65 → ~£5,500/year (approx rate 5.5%)
    │
    ├── ESCALATING ANNUITY (fixed rate)
    │   Income increases by fixed % each year (e.g. 3%)
    │   Lower initial income, but maintains purchasing power
    │   Example: £100k at 65 with 3% escalation → ~£3,800/year initially
    │
    ├── INDEX-LINKED ANNUITY (RPI/CPI)
    │   Income increases with inflation measure
    │   Lowest initial income, best long-term protection
    │   Example: £100k at 65, RPI-linked → ~£3,200/year initially
    │
    ├── SINGLE LIFE
    │   Pays until your death only
    │   Highest rate
    │
    ├── JOINT LIFE
    │   Continues paying (typically 50% or 2/3) to surviving spouse
    │   Lower rate (typically 10-15% less than single life)
    │   Spouse percentage options: 33%, 50%, 66%, 100%
    │
    ├── GUARANTEE PERIOD
    │   Pays for minimum period (5 or 10 years) even if you die
    │   Slightly lower rate
    │
    ├── ENHANCED / IMPAIRED LIFE
    │   Higher rate if health conditions or lifestyle factors exist
    │   Qualifying conditions: smoking, BMI > 30, diabetes, heart disease,
    │   cancer, high blood pressure, high cholesterol, kidney disease
    │   Can increase income by 20-40%
    │
    └── WITH-PROFITS ANNUITY
        Income varies based on fund performance
        Can increase or decrease
        Rare and complex — not commonly recommended
```

#### Current Annuity Rate Estimates (2025/26)

```
APPROXIMATE annuity rates (level, single life, no guarantee):

┌─────┬───────────────┬─────────────────────────┐
│ Age │ Rate (approx) │ £100k pot → annual income│
├─────┼───────────────┼─────────────────────────┤
│ 55  │ 4.0-4.5%      │ £4,000-£4,500           │
│ 60  │ 4.5-5.0%      │ £4,500-£5,000           │
│ 65  │ 5.0-5.8%      │ £5,000-£5,800           │
│ 70  │ 5.8-6.8%      │ £5,800-£6,800           │
│ 75  │ 6.8-8.0%      │ £6,800-£8,000           │
│ 80  │ 8.0-10.0%     │ £8,000-£10,000          │
└─────┴───────────────┴─────────────────────────┘

Adjustments:
  Joint life (50% spouse): reduce by ~10-15%
  5-year guarantee: reduce by ~1-2%
  3% escalation: reduce initial by ~30-35%
  RPI-linked: reduce initial by ~40-45%
  Enhanced (smoker): increase by ~15-25%
  Enhanced (serious condition): increase by ~25-40%
```

### 8.4 Sustainable Withdrawal Rate Analysis

**Service:** `DecumulationPlanner.calculateSustainableWithdrawalRate()`

```
TEST withdrawal rates: 3%, 4%, 5%

FOR EACH rate:
    initial_withdrawal = pot_value × rate

    SIMULATE year by year:
        Year 1: withdraw, apply growth, inflate withdrawal
        Year 2: withdraw (inflated), apply growth, inflate withdrawal
        ...
        Until pot depletes or reaches end of projection

    Record: survives? final_balance? years_survived?

RECOMMENDATION:
    3% → "Very conservative — high likelihood of leaving large legacy"
    4% → "Balanced approach — widely considered sustainable" (the "4% rule")
    5% → "Aggressive — higher risk of portfolio depletion"

NOTES on the 4% rule:
  - Originally from the Trinity Study (US data, 1926-1995)
  - Assumes 50/50 stock/bond portfolio
  - 30-year retirement horizon
  - UK-specific considerations:
    ├── Lower historical equity returns vs US
    ├── Currency risk (if international equities)
    ├── Different inflation patterns
    └── Some UK advisers recommend 3.5-4% for UK context

  - Sequence of returns risk: poor returns early in retirement are much more
    damaging than poor returns later. The 4% rule does not fully account for this.

  - Current service uses 4.7% (higher than standard — noted in code as
    SUSTAINABLE_WITHDRAWAL_RATE = 0.047). This is aggressive.
    RECOMMENDATION: Consider lowering to 4% or making configurable.
```

### 8.5 Income Layering Strategy

**Service:** `DecumulationPlanner.modelIncomePhasing()`

```
INCOME LAYERING APPROACH:

Layer 1: GUARANTEED INCOME FLOOR (covers essential spending)
    ├── State Pension
    ├── DB pension income
    ├── Annuity income (if purchased)
    └── TARGET: >= essential_expenditure

    IF Layer 1 < essential_expenditure:
        ──> "Consider purchasing an annuity with part of your DC pension to
             secure your essential spending needs. Your essential spending is
             £{essential}/year but your guaranteed income is only £{guaranteed}/year."

Layer 2: FLEXIBLE DRAWDOWN (covers lifestyle spending)
    ├── DC pension drawdown (taxable)
    ├── ISA withdrawals (tax-free)
    ├── Investment bond withdrawals (5% tax-deferred)
    └── TARGET: covers lifestyle_expenditure

Layer 3: RESERVE / CONTINGENCY
    ├── Cash savings
    ├── Additional ISA/GIA holdings
    └── PURPOSE: unexpected costs, care needs, helping family, gifts
```

### 8.6 Phased Retirement Income Strategy

```
PHASE 1: Early Retirement (retirement_age to SPA)
    ──> "State Pension Bridge" period
    Income sources: DC drawdown, PCLS, ISA withdrawals
    Strategy: Draw MORE from DC pension to replace State Pension
    Tax efficiency: Use full personal allowance (£12,570)

    CALCULATION:
    annual_bridge_amount = state_pension_annual_forecast
    bridge_years = SPA - retirement_age
    bridge_cost = annual_bridge_amount × bridge_years

    "You will need approximately £{bridge_cost} to bridge the gap between
     your retirement age ({ret_age}) and State Pension age ({SPA})."

PHASE 2: State Pension Age to 75
    Income sources: State Pension + DB pension + reduced DC drawdown + ISA
    Strategy: Reduce DC drawdown, let pot grow
    Tax efficiency: Coordinate all sources to stay in basic rate band

PHASE 3: Age 75+
    Income sources: State Pension + DB pension + annuity/minimal drawdown
    Strategy: Consider converting remaining DC pot to annuity for security
    Tax note: Death benefits taxed differently after 75
        - Before 75: beneficiaries receive remaining pot tax-free (if no LSA breach)
        - After 75: beneficiaries pay income tax on inherited pension

PHASE 4: Later Life (80s+)
    Strategy: Account for care costs
    IF care_cost_annual > 0:
        Annual cost of care (residential 2025/26 estimate):
        ├── Domiciliary care: £15,000-£30,000/year
        ├── Residential care: £35,000-£50,000/year
        ├── Nursing care: £50,000-£70,000/year
        └── Funded by: local authority (below £23,250 savings)
                       self-funded (above £23,250 savings)
                       NOTE: Government's £86,000 cap has been delayed to October 2025
                       (subject to further delay — check current status)
```

---

## 9. Phase 7: Tax Optimisation in Retirement

### 9.1 Tax-Efficient Withdrawal Ordering

**Service:** `RetirementIncomeService`

```
OPTIMAL WITHDRAWAL ORDER (general principles):
    |
    1. TAX-FREE sources first:
    │   ├── PCLS (25% tax-free lump sum from pension)
    │   ├── ISA withdrawals (tax-free)
    │   └── Return of capital from investment bonds (up to 5% cumulative tax-free)
    │
    2. Use PERSONAL ALLOWANCE:
    │   Draw £12,570 from taxable sources (pension drawdown) tax-free
    │
    3. BASIC RATE BAND:
    │   Next £37,700 (£12,571-£50,270) taxed at 20%
    │   ├── Pension drawdown income
    │   ├── Part-time employment (if any)
    │   └── Rental income (if any)
    │
    4. AVOID HIGHER RATE where possible:
    │   IF total taxable income > £50,270:
    │       Consider deferring some drawdown to next tax year
    │       OR switch to ISA/tax-free sources
    │
    5. GIA withdrawals:
    │   ├── Use CGT annual exempt amount (£3,000/year)
    │   ├── Realise gains within basic rate band (10% CGT) if possible
    │   └── Time disposals across tax years

SPECIFIC ACCOUNT ORDERING by tax efficiency:
    ┌────┬──────────────────────┬───────────────────────────────────┐
    │ #  │ Source               │ Tax Treatment                     │
    ├────┼──────────────────────┼───────────────────────────────────┤
    │ 1  │ ISA                  │ Tax-free withdrawal               │
    │ 2  │ Pension PCLS         │ Tax-free (25% of pot)             │
    │ 3  │ Investment Bond      │ 5% cumulative tax-deferred p.a.   │
    │ 4  │ Pension Drawdown     │ Taxable as income                 │
    │ 5  │ State Pension        │ Taxable as income (cannot choose) │
    │ 6  │ DB Pension           │ Taxable as income                 │
    │ 7  │ GIA                  │ Income tax + CGT                  │
    │ 8  │ Savings Account      │ PSA applies (£1k basic/£500 higher│
    └────┴──────────────────────┴───────────────────────────────────┘
```

### 9.2 Personal Allowance Strategy

```
MANAGE personal allowance usage:
    |
    State Pension (2025/26): £11,973/year
    Personal Allowance: £12,570/year
    Remaining PA after State Pension: £12,570 - £11,973 = £597

    STRATEGY:
    IF no State Pension yet (before SPA):
        Full PA available for pension drawdown = £12,570 tax-free
        "Before your State Pension starts, you can draw up to £12,570/year
         from your pension tax-free by using your full personal allowance."

    IF receiving State Pension:
        Only £597 of PA remaining for other income
        "Once your State Pension is in payment, almost all your personal allowance
         is used. Additional pension drawdown will be taxed from the first pound."

    IF total income > £100,000:
        PA tapers: reduced by £1 for every £2 above £100,000
        Fully removed at £125,140
        "Drawing more than £100,000 in a tax year will reduce your personal allowance.
         Between £100,000 and £125,140 the effective marginal rate is 60%."
```

### 9.3 Marriage Allowance in Retirement

```
IF married/civil partnership AND one partner is non-taxpayer:
    |
    Lower earner can transfer £1,260 (10% of PA) to higher earner
    Tax saving = £1,260 × 20% = £252/year

    CONDITIONS:
    ├── Transferor must have income below £12,570 (non-taxpayer)
    ├── Recipient must be basic rate taxpayer (income below £50,270)
    └── Both must be born after 5 April 1935

    IN RETIREMENT context:
    IF one spouse has only State Pension (£11,973 < PA of £12,570):
        "Your spouse's income is below the personal allowance.
         They can transfer £1,260 to you, saving £252/year in tax."
```

### 9.4 Scottish Income Tax Rates in Retirement

```
IF user is Scottish taxpayer (residence in Scotland):
    Different rates apply to non-savings, non-dividend income:

    ┌──────────────┬──────────┬───────────────────┐
    │ Band          │ Rate     │ Taxable income     │
    ├──────────────┼──────────┼───────────────────┤
    │ Starter       │ 19%      │ £12,571-£14,876   │
    │ Basic         │ 20%      │ £14,877-£26,561   │
    │ Intermediate  │ 21%      │ £26,562-£43,662   │
    │ Higher        │ 42%      │ £43,663-£75,000   │
    │ Advanced      │ 45%      │ £75,001-£125,140  │
    │ Top           │ 48%      │ Above £125,140    │
    └──────────────┴──────────┴───────────────────┘

    Impact on retirement:
    - Pension contributions still get relief at marginal rate
    - Drawdown income taxed at Scottish rates
    - Higher rate threshold is lower (£43,663 vs £50,270)
    - Top rate 48% vs UK additional rate 45%

    NOTE: Savings and dividend income use UK-wide rates regardless of residence
```

---

## 10. Phase 8: Strategy Recommendations

### 10.1 Strategy Priority Waterfall

**Service:** `RetirementStrategyService`

```
IF user is ON TRACK (probability >= 95% OR combined assets meet target):
    ──> Return "on track" status, no strategies needed

IF user is NOT on track:
    Apply strategies in priority order until on track:

    PRIORITY 1: EMPLOYER MATCH
        ──> Maximise employer pension match (free money)
        ──> Check affordability before recommending

    PRIORITY 2: INCREASE CONTRIBUTIONS
        ──> Add more to pensions (within AA and affordability)
        ──> Skip if employer match was unaffordable

    PRIORITY 3: DELAY RETIREMENT
        ──> Work longer (more contributions + growth + shorter drawdown)
        ──> Maximum suggestion: age 68

    PRIORITY 4: REDUCE INCOME TARGET
        ──> Lower expectations to achievable level
        ──> Only if Priorities 1-3 cannot close the gap
        ──> Suggest sustainable income at suggested retirement age

    EACH strategy shows:
    ├── Impact on probability
    ├── Additional monthly cost (if applicable)
    ├── Affordability assessment
    ├── Projection comparison (before/after)
    └── Cumulative effect with previous strategies
```

### 10.2 Strategy Detail: Employer Match

```
FOR EACH workplace DC pension:
    IF employee_contribution% < employer_matching_limit:
        |
        additional% = matching_limit - employee_contribution%
        additional_monthly = (additional% / 100) × annual_salary / 12
        employer_additional = additional_monthly (matched)
        total_gain = additional_monthly + employer_additional (double value!)
        |
        CHECK AFFORDABILITY:
        ├── Can user afford additional_monthly from disposable income?
        │   YES ──> Recommend with affordability confirmation
        │   NO ──> Show strategy but flag "may not be affordable on current budget"
        │
        IMPACT:
        additional_annual = total_gain × 12
        additional_pot_at_retirement = FV of annual contributions over years_to_retirement
        additional_income = additional_pot × withdrawal_rate
        new_probability = recalculated
```

### 10.3 Strategy Detail: Increase Contributions

```
CALCULATE optimal contribution increase:
    |
    remaining_AA = annual_allowance - current_total_contributions
    affordable_monthly = disposable_income × 0.5 (don't commit more than 50%)
    affordable_annual = affordable_monthly × 12
    |
    optimal_increase = min(remaining_AA, affordable_annual)
    |
    IF salary_sacrifice_available:
        net_cost = optimal_increase × (1 - employee_NI_rate)
        "Via salary sacrifice, this costs you only £{net_cost}/month
         rather than £{gross_cost}/month."

    IMPACT:
    additional_pot = FV of optimal_increase over years_to_retirement
    additional_income = additional_pot × withdrawal_rate
    new_probability = recalculated

    TAX RELIEF benefit:
    IF higher_rate_taxpayer:
        "You will receive {rate}% tax relief on these additional contributions,
         effectively reducing the cost to £{net}/month."
```

### 10.4 Strategy Detail: Delay Retirement

```
CALCULATE impact of later retirement:
    |
    FOR each additional year (up to age 68):
        additional_contributions = current_monthly × 12
        additional_growth = existing_pot × growth_rate
        fewer_years_in_drawdown = 1 less year of withdrawals

        new_pot = project with extra years
        new_income = new_pot × withdrawal_rate
        new_probability = recalculated

    SHOW best retirement age:
    "Delaying retirement from {current_age} to {new_age} could increase
     your annual retirement income by £{increase}."

    IF cannot achieve target by 68:
        flag cannot_achieve_target_by_68 = true
        calculate sustainable_income_at_68
        trigger Priority 4 (income target reduction)
```

### 10.5 Strategy Detail: Reduce Income Target

```
CALCULATE sustainable income at retirement:
    |
    sustainable_income = projected_total_income at best achievable scenario
    |
    COMPARE with PLSA standards:
    ├── Above Comfortable? "You can still afford a comfortable retirement"
    ├── Above Moderate? "You can afford a moderate retirement lifestyle"
    ├── Above Minimum? "You can afford a minimum retirement lifestyle"
    └── Below Minimum? "Critical — you may struggle to meet basic retirement needs"

    SHOW income target suggestion:
    "Based on your current savings trajectory, a realistic target income is
     £{sustainable}/year. This is £{difference} less than your current target."
```

---

## 11. Phase 9: Scenario Modelling

### 11.1 What-If Scenarios

**Service:** `RetirementAgent.buildScenarios()`

```
SCENARIO 1: Current Trajectory
    Keep everything the same
    Show projected income and gap

SCENARIO 2: Increased Contributions
    Parameter: additional_monthly_contribution
    Calculate: additional pot from extra contributions
    Show: new projected income, reduced gap

SCENARIO 3: Later Retirement
    Parameter: later_retirement_age
    Calculate: more years of contributions + growth, shorter drawdown
    Show: new projected income, reduced gap

SCENARIO 4: Lower Target Income
    Parameter: lower_target_income
    Calculate: income gap with new target
    Show: whether current trajectory meets new target

COMPARISON TABLE:
    ┌──────────────────────┬──────────┬──────────┬──────────┬──────────┐
    │                      │ Current  │ +£200/m  │ Retire 68│ Target   │
    │                      │          │          │          │ £25k     │
    ├──────────────────────┼──────────┼──────────┼──────────┼──────────┤
    │ Retirement Age       │ 65       │ 65       │ 68       │ 65       │
    │ Projected Income     │ £22,000  │ £26,500  │ £28,000  │ £22,000  │
    │ Target Income        │ £30,000  │ £30,000  │ £30,000  │ £25,000  │
    │ Income Gap           │ £8,000   │ £3,500   │ £2,000   │ £3,000   │
    │ On Track?            │ No       │ Closer   │ Closer   │ Closer   │
    └──────────────────────┴──────────┴──────────┴──────────┴──────────┘
```

### 11.2 Interactive Strategy Impact

**Service:** `RetirementStrategyService.calculateStrategyImpact()`

```
USER adjusts a strategy slider (e.g. contribution amount, retirement age):
    |
    RECALCULATE in real-time:
    ├── New probability
    ├── New projected income
    ├── New projection chart data
    └── Cumulative effect with previous strategies

    Strategy types:
    ├── employer_match: slider for employee contribution %
    ├── increase_contribution: slider for additional monthly amount
    ├── retirement_age: slider for age 55-75
    └── income_target: slider for target annual income
```

---

## 12. Phase 10: Life Event Impacts

### 12.1 Life Events Affecting Retirement Planning

```
CAREER CHANGE
    Impact: New employer → new pension scheme
    Actions:
    ├── Transfer old workplace pension? (consolidation analysis)
    ├── New employer match different? (match optimisation)
    ├── Salary change? (contribution recalculation)
    └── Gap in employment? (NI gap, contribution gap)

REDUNDANCY
    Impact: Loss of employer contributions, potential early access
    Actions:
    ├── Continue personal contributions if possible
    ├── Do NOT access pension early just because of redundancy
    ├── Check if redundancy pay can boost pension (lump sum contribution)
    ├── Bridge period: use savings, not pension
    └── Update income and contribution projections

DIVORCE / PENSION SHARING
    Impact: Pension may be split via court order
    Types:
    ├── Pension Sharing Order: % of pension transferred to ex-spouse
    │   Creates separate pension in ex-spouse's name
    │   Pension Sharing Order reduces the member's pension
    ├── Pension Attachment Order: % of pension paid to ex-spouse when member retires
    │   Member retains pension but shares payments
    │   Less clean break than sharing order
    ├── Pension Offsetting: pension kept intact, other assets given to ex-spouse
    │   No direct impact on pension
    └── Actions:
        ├── Update pension values post-sharing
        ├── Recalculate retirement income projection
        ├── May need to increase contributions to recover
        └── Consider CETV for DB pensions in divorce

DEATH OF SPOUSE
    Impact: Changed income needs, survivor benefits
    Actions:
    ├── Claim DB pension survivor benefit (typically 50% of spouse's pension)
    ├── Claim DC pension inheritance (if nominated beneficiary)
    │   Before 75: tax-free
    │   After 75: taxed as income
    ├── Bereavement Support Payment (if below SPA): £3,500 lump sum + 18 monthly payments of £350
    ├── Inherited State Pension (old rules only, pre-April 2016)
    ├── Reassess income needs (single vs couple PLSA standards)
    └── Recalculate retirement plan for single person

SERIOUS ILLNESS
    Impact: May trigger early pension access, enhanced annuity
    Actions:
    ├── ILL-HEALTH EARLY RETIREMENT:
    │   IF cannot work due to illness:
    │       ├── Employer scheme may allow early retirement (enhanced benefits)
    │       ├── No minimum pension age if "serious ill health" (life expectancy < 12 months)
    │       │   Can take entire pension as lump sum:
    │       │   ├── Under 75: tax-free (if within LSA & LSDBA)
    │       │   └── Over 75: taxed at marginal rate
    │       └── Non-serious illness: can access from MPA (55/57) with actuarial reduction
    │
    ├── ENHANCED ANNUITY:
    │   Conditions that qualify: smoking, high BMI, diabetes, heart disease,
    │   cancer, high blood pressure, kidney disease, stroke, MS, Parkinson's
    │   Income increase: 15-40% above standard rates
    │
    └── REASSESS:
        ├── Shorter time horizon → different withdrawal strategy
        ├── Care cost planning
        └── Estate planning priorities (IHT)

APPROACHING RETIREMENT (within 5-10 years)
    Actions:
    ├── DE-RISK investments (glide path to lower equity, more bonds/cash)
    ├── Start planning access strategy (drawdown vs annuity)
    ├── Check State Pension forecast (gov.uk)
    ├── Consider trial retirement budget
    ├── Review pension nominations (expression of wish)
    ├── Consider whether to take PCLS
    └── Assess care cost needs

REACHING PENSION ACCESS AGE (55, rising to 57 from April 2028)
    Actions:
    ├── Crystallisation decision: when to start accessing
    ├── PCLS decision: take some/all/none
    ├── MPAA awareness: accessing triggers MPAA for future contributions
    └── No requirement to access — can wait

REACHING STATE PENSION AGE
    Actions:
    ├── CLAIM State Pension (or actively choose to defer)
    ├── Reduce DC drawdown to account for SP income
    ├── Tax planning: SP uses most of personal allowance
    └── Recalculate withdrawal strategy
```

### 12.2 Life Event Cash Flow Integration

**Service:** `LifeEventCashFlowService`

```
Life events are integrated into Monte Carlo projections:
    |
    ├── Accumulation phase: buildCashFlowMap()
    │   Lump sums added/removed from pension pot during growth simulation
    │   Examples: inheritance received, home purchase, wedding costs
    │
    └── Decumulation phase: buildDrawdownCashFlowMap()
        Lump sums added/removed during income drawdown simulation
        Examples: home sale proceeds, large gifts to children, care costs

    Events are age-indexed and applied in the correct simulation year.
    Event hash used for cache invalidation (change events → new projection).
```

---

## 13. Phase 11: Spouse/Partner Coordination

### 13.1 Combined Retirement Planning

```
IF user is married/has partner:
    |
    INCLUDE SPOUSE:
    ├── Spouse's pensions (DC, DB, State)
    ├── Spouse's ISAs and investments
    ├── Spouse's income and tax band
    ├── Combined household retirement income target
    └── Survivor planning

    COORDINATION STRATEGIES:
    |
    ├── TAX BAND MANAGEMENT:
    │   Draw income to keep both spouses in basic rate band
    │   Combined basic rate allowance = 2 × £50,270 = £100,540
    │   "By splitting income between you and your spouse, you can keep
    │    both of you in the basic rate band, saving significant tax."
    │
    ├── PENSION CONTRIBUTION SPLITTING:
    │   IF one spouse has unused AA and the other is at the limit:
    │       "Your spouse has £{unused_AA} of unused annual allowance.
    │        Consider increasing their pension contributions instead."
    │   NOTE: Cannot directly contribute to spouse's pension from your income
    │         (but can gift money, and they contribute to their own pension)
    │
    ├── STATE PENSION TIMING:
    │   Different SPAs may mean one spouse receives SP before the other
    │   Plan bridge strategy for the gap period
    │
    ├── DRAWDOWN COORDINATION:
    │   Use one spouse's pension first, let other's grow
    │   Or draw equally to stay in same tax band
    │
    └── SURVIVOR PLANNING:
        ├── DB pension survivor benefit (50-66% typically)
        ├── DC pension nomination (expression of wish)
        ├── Consider joint-life annuity
        └── Death benefits: DC pension passed tax-free if death before 75
```

### 13.2 Pension Sharing on Divorce

```
IF divorce/dissolution:
    |
    PENSION TYPES and sharing:
    |
    ├── DC PENSION:
    │   Sharing order: % of fund transferred to ex-spouse
    │   Creates new pension arrangement for ex-spouse
    │   Clean break achieved
    │
    ├── DB PENSION:
    │   Sharing order based on CETV
    │   Can be external transfer (to ex-spouse's pension) or internal (shadow member)
    │   Actuary report usually needed
    │   More complex than DC
    │
    ├── STATE PENSION:
    │   New State Pension: CANNOT be shared on divorce
    │   Old Basic State Pension: derived entitlement may apply
    │   Additional State Pension (SERPS/S2P): can be shared
    │
    └── PENSION OFFSETTING:
        Instead of sharing pension, other assets allocated:
        Example: Spouse keeps pension, partner gets more of the house equity
        Risk: may not be equivalent value long-term
```

---

## 14. Phase 12: Cross-Module Integration

### 14.1 Integration with Investment Module

```
SHARED SERVICES:
    ├── MonteCarloSimulator — used for pension pot projections
    ├── PortfolioAnalyzer — used for DC pension holdings analysis
    ├── AssetAllocationOptimizer — target allocation for pension investments
    ├── FeeAnalyzer — pension platform and fund fees
    ├── TaxEfficiencyCalculator — pension vs ISA vs GIA decision
    └── RiskPreferenceService — growth rate assumptions

PENSION vs ISA vs GIA CONTRIBUTION DECISION:
    Priority: Employer match → Pension (tax relief) → ISA → GIA
    See Investment decision tree Phase 4 (Contribution Waterfall)
```

### 14.2 Integration with Estate Module

```
PENSION DEATH BENEFITS:
    |
    ├── DC Pension:
    │   Death before 75: remaining pot passed to nominees tax-free
    │       (if within Lump Sum and Death Benefit Allowance of £1,073,100)
    │   Death after 75: nominees pay income tax at their marginal rate
    │   Pension is OUTSIDE the estate for IHT purposes
    │
    ├── DB Pension:
    │   Survivor pension: typically 50% of member's pension to spouse
    │   Lump sum death benefit: typically 2-4× pensionable salary
    │   (if death in service)
    │
    └── State Pension:
        No death benefit under new system (post-2016)
        Old system: derived entitlement may continue

ESTATE PLANNING STRATEGY:
    ├── Spend other assets first, preserve pension (IHT-free wrapper)
    ├── Don't take pension income you don't need (tax-free growth)
    ├── Nominate beneficiaries (expression of wish form)
    └── Pension pot is one of the most tax-efficient ways to pass wealth
```

### 14.3 Integration with Protection Module

```
RETIREMENT IMPACT on protection needs:
    |
    ├── Approaching retirement: life insurance less important
    │   (no dependents relying on income, pension pot passes on death)
    │
    ├── Income protection: not needed after retirement
    │
    ├── Critical illness: may be too expensive or unavailable
    │   Consider self-insuring from pension pot
    │
    └── Long-term care: increasingly important
        Consider care fee planning / insurance
```

### 14.4 Integration with Savings Module

```
SAVINGS FOR RETIREMENT SUPPLEMENTATION:
    |
    ├── Cash ISA: supplement pension drawdown tax-free
    ├── Savings accounts: emergency buffer in retirement
    │   (Reduced from working-life requirement: 3 months expenses)
    └── NS&I: guaranteed returns, government-backed
```

### 14.5 Integration with Goals Module

```
RETIREMENT-RELATED GOALS:
    |
    ├── Retirement fund target goal
    ├── Early retirement goal
    ├── Specific retirement purchase (dream holiday, car, etc.)
    ├── Help children/grandchildren goal
    └── Legacy/inheritance goal

    Goals create cash flow events that integrate into retirement projections
    via LifeEventCashFlowService.
```

---

## 15. Thresholds & Constants Reference

**CRITICAL: `TaxConfigService` is the single source of truth for ALL rates, thresholds, and constants in this engine.** No values should be hardcoded in services, controllers, or components. Where values relate to the user (e.g., retirement age, risk level, income), they must always come from actual user data — never defaults. Growth rates come from the user's risk profile via `AssumptionsService`.

### 15.1 Pension Allowances (2025/26)

| Threshold | Value | Source |
|-----------|-------|--------|
| Annual Allowance | £60,000 | `TaxConfigService::getPensionAllowances()['annual_allowance']` |
| Tapered AA — Threshold Income | £200,000 | `TaxConfigService::getPensionAllowances()['tapered_allowance']['threshold_income']` |
| Tapered AA — Adjusted Income | £260,000 | `TaxConfigService::getPensionAllowances()['tapered_allowance']['adjusted_income']` |
| Tapered AA — Minimum | £10,000 | `TaxConfigService::getPensionAllowances()['tapered_allowance']['minimum']` |
| Money Purchase Annual Allowance | £10,000 | `TaxConfigService::getPensionAllowances()['mpaa']` |
| Lump Sum Allowance (LSA) | £268,275 | `TaxConfigService::getPensionAllowances()['lump_sum_allowance']` |
| Lump Sum & Death Benefit Allowance | £1,073,100 | `TaxConfigService::getPensionAllowances()['lump_sum_death_benefit_allowance']` |
| Minimum Pension Age (MPA) | 55 (rising to 57 from April 2028) | `TaxConfigService::getPensionAllowances()['minimum_pension_age']` |
| Max tax relief contribution | 100% of UK earnings | `TaxConfigService::getPensionAllowances()['max_contribution_percent']` |
| Carry forward lookback | 3 years | `TaxConfigService::getPensionAllowances()['carry_forward_years']` |
| Salary sacrifice floor | £2,000 | `TaxConfigService::get('pension.salary_sacrifice_floor')` |
| Accumulation/Decumulation trigger | 10 years to retirement | `TaxConfigService::get('retirement.accumulation_to_decumulation_years')` |

### 15.2 State Pension (2025/26)

| Threshold | Value | Source |
|-----------|-------|--------|
| Full new State Pension | £11,973/year (£230.25/week) | `TaxConfigService::getPensionAllowances()['state_pension']['full_amount']` |
| Qualifying years for full | 35 years | `TaxConfigService::getPensionAllowances()['state_pension']['qualifying_years_full']` |
| Minimum qualifying years | 10 years | `TaxConfigService::getPensionAllowances()['state_pension']['qualifying_years_min']` |
| Voluntary NI (Class 3) | £907.80/year (£17.45/week) | `TaxConfigService::getNationalInsurance()['class_3']` |
| Deferral rate | ~5.8%/year (1% per 9 weeks) | `TaxConfigService::getPensionAllowances()['state_pension']['deferral_rate']` |
| Current SPA | 66 (rising to 67: 2026-2028) | `TaxConfigService::getPensionAllowances()['state_pension']['current_spa']` |
| Future SPA | 67 (rising to 68: 2044-2046) | `TaxConfigService::getPensionAllowances()['state_pension']['future_spa']` |

### 15.3 Income Tax (2025/26)

All income tax rates and bands served from `TaxConfigService::getIncomeTax()`. Not duplicated here — see Cash/Savings Section 19.1 for the canonical reference.

### 15.4 Auto-Enrolment Thresholds (2025/26)

| Threshold | Value | Source |
|-----------|-------|--------|
| Earnings trigger | £10,000/year | `TaxConfigService::get('pension.auto_enrolment.earnings_trigger')` |
| Lower QE limit | £6,240/year | `TaxConfigService::get('pension.auto_enrolment.lower_qe')` |
| Upper QE limit | £50,270/year | `TaxConfigService::get('pension.auto_enrolment.upper_qe')` |
| Min total contribution | 8% of QE (5% employee + 3% employer) | `TaxConfigService::get('pension.auto_enrolment.min_total_percent')` |
| Age range | 22 to SPA | `TaxConfigService::get('pension.auto_enrolment.age_range')` |

### 15.5 Growth & Withdrawal Assumptions

**CRITICAL: No hardcoded defaults.** Growth rates MUST come from the user's risk profile via `AssumptionsService`. Withdrawal rates must be centralised in `TaxConfigService`. The inconsistency documented in Section 19.3 (4.0% vs 4.7% across 6 services) must be resolved by centralising to a single configurable source.

| Assumption | Value | Source | Rule |
|------------|-------|--------|------|
| Growth rate | Varies by risk level | User's risk profile via `AssumptionsService` | ALWAYS use user's actual risk level — never a default |
| Safe withdrawal rate | 4.0% | `TaxConfigService::get('retirement.withdrawal_rates.safe')` | Centralise — currently 4.0% in some services, 4.7% in others |
| Sustainable withdrawal rate | 4.7% | `TaxConfigService::get('retirement.withdrawal_rates.sustainable')` | Centralise from 6 hardcoded locations |
| Inflation | 2.5% | `TaxConfigService::getAssumptions()['inflation']` | From TaxConfigService |
| Target income % of net | 75% | `TaxConfigService::get('retirement.target_income_percent')` | From TaxConfigService |
| Retirement age | User-entered | `user.retirement_age` or SPA from TaxConfigService | ALWAYS use user data — never hardcoded 67 |
| Projection end age | 100 | `TaxConfigService::get('retirement.projection_end_age')` | From TaxConfigService |
| Monte Carlo iterations | 1,000 | `TaxConfigService::get('retirement.monte_carlo_iterations')` | From TaxConfigService |
| Compounding periods | 4 (quarterly) | `TaxConfigService::get('retirement.compounding_periods')` | From TaxConfigService |
| DB revaluation CPI | 2.5% | `TaxConfigService::getAssumptions()['cpi']` | From TaxConfigService |
| DB revaluation RPI | 3.0% | `TaxConfigService::getAssumptions()['rpi']` | From TaxConfigService |

### 15.6 Pension Protection / Transitional Values

| Protection | Value | Notes |
|------------|-------|-------|
| Fixed Protection 2016 | £1,250,000 | LTA frozen at this level |
| Individual Protection 2016 | Varies | LTA = value of rights at 5 April 2016 |
| Fixed Protection 2014 | £1,500,000 | Older protection |
| Individual Protection 2014 | Varies | Older protection |
| Enhanced Protection | Unlimited | Pre-2006, no further contributions |
| Primary Protection | Varies | Pre-2006 |

NOTE: LTA was abolished from 6 April 2024, but the Lump Sum Allowance (£268,275) and
Lump Sum & Death Benefit Allowance (£1,073,100) replaced it. Transitional protections
may give higher lump sum allowances.

---

## 16. PLSA Retirement Living Standards

### 16.1 Full Breakdown (2024 figures, update annually)

#### Single Person

| Category | Minimum | Moderate | Comfortable |
|----------|---------|----------|-------------|
| **Total annual** | **£14,400** | **£31,300** | **£43,100** |
| Housing (council tax, repairs) | £2,000 | £2,400 | £3,000 |
| Food & drink | £2,800 | £4,500 | £5,800 |
| Transport | £1,600 | £3,800 | £5,200 |
| Holidays & leisure | £1,200 | £4,300 | £8,200 |
| Clothing & personal | £900 | £1,400 | £2,200 |
| Helping others | £0 | £600 | £1,200 |
| Insurance & healthcare | £500 | £1,500 | £2,500 |
| Household goods | £400 | £1,100 | £1,700 |
| Other | £5,000 | £11,700 | £13,300 |

#### Couple

| Category | Minimum | Moderate | Comfortable |
|----------|---------|----------|-------------|
| **Total annual** | **£22,400** | **£43,100** | **£59,000** |

### 16.2 What Each Standard Looks Like

```
MINIMUM:
  - UK-based holidays in budget accommodation
  - Public transport, or a second-hand car (small engine)
  - Basic smartphone, limited data plan
  - Eat at home, occasional meal out at budget restaurant
  - Limited leisure activities

MODERATE:
  - Annual holiday in Europe (2 weeks)
  - A modest car (second-hand, replace every 7 years)
  - Moderate eating out (once a fortnight)
  - Hobbies with some spending (e.g. gym membership, golf)
  - Some financial help to family

COMFORTABLE:
  - Annual long-haul holiday plus UK breaks
  - A newer car (replace every 4 years)
  - Regular eating out
  - Regular beauty/grooming
  - Gym membership
  - Charitable giving
  - Financial help to family
```

---

## 17. Decision Tree: Master Flow

### 17.1 Complete Retirement Decision Flow

```
┌─────────────────────────────────────────────────────┐
│              USER ENTERS RETIREMENT MODULE           │
└────────────────────────┬────────────────────────────┘
                         │
                         v
              ┌──────────────────────┐
              │  DATA READINESS      │
              │  GATE                │
              │                      │
              │  Has DOB?            │──NO──> "Enter date of birth"
              │  Has any pension?    │──NO──> "Add pension details"
              │  Has ret. profile?   │──NO──> Auto-create with defaults
              └──────────┬───────────┘
                         │ YES (all)
                         v
              ┌──────────────────────┐
              │  PENSION INVENTORY   │
              │                      │
              │  Classify pensions   │
              │  Aggregate values    │
              │  Check completeness  │
              └──────────┬───────────┘
                         │
                         v
              ┌──────────────────────┐
              │  YEARS TO            │
              │  RETIREMENT?         │
              └──────────┬───────────┘
                         │
               ┌─────────┼──────────┐
               │         │          │
            > 10 yrs   5-10 yrs   <= 5 yrs / retired
               │         │          │
               v         v          v
     ┌─────────────┐ ┌──────────┐ ┌──────────────────┐
     │ ACCUMULATION │ │ BOTH     │ │ DECUMULATION     │
     │ FOCUS        │ │ PHASES   │ │ FOCUS            │
     │              │ │          │ │                  │
     │ - AA check   │ │ - All    │ │ - Drawdown plan  │
     │ - MPAA check │ │   accum. │ │ - Annuity vs DD  │
     │ - Employer   │ │   checks │ │ - PCLS strategy  │
     │   match      │ │ - Start  │ │ - Withdrawal     │
     │ - Tax relief │ │   decum. │ │   rate           │
     │ - Contrib.   │ │   plan   │ │ - Income layering│
     │   optimise   │ │ - Glide  │ │ - Tax optimise   │
     │ - Sal. sac.  │ │   path   │ │ - Fund depletion │
     │ - Consol.    │ │          │ │   projection     │
     └──────┬──────┘ └────┬─────┘ └────────┬─────────┘
            │             │                │
            └─────────────┼────────────────┘
                          │
                          v
              ┌──────────────────────┐
              │  INCOME PROJECTION   │
              │                      │
              │  DC: Monte Carlo     │
              │  DB: revaluation     │
              │  SP: forecast/calc   │
              │                      │
              │  Total projected     │
              │  income at retirement│
              └──────────┬───────────┘
                         │
                         v
              ┌──────────────────────┐
              │  READINESS           │
              │  ASSESSMENT          │
              │                      │
              │  Income gap?         │
              │  Probability?        │
              │  PLSA comparison?    │
              │  Replacement ratio?  │
              └──────────┬───────────┘
                         │
                ┌────────┼────────┐
                │                 │
             ON TRACK          OFF TRACK
                │                 │
                v                 v
     ┌──────────────┐  ┌────────────────────┐
     │ "You are on  │  │ STRATEGY WATERFALL │
     │  track"      │  │                    │
     │              │  │ P1: Employer match │
     │ Show current │  │ P2: Contributions  │
     │ position     │  │ P3: Later retire   │
     │ projections  │  │ P4: Lower target   │
     └──────┬───────┘  └────────┬───────────┘
            │                   │
            └───────────────────┤
                                │
                                v
              ┌──────────────────────┐
              │  SCENARIO MODELLING  │
              │                      │
              │  What-if scenarios   │
              │  Interactive sliders │
              │  Comparison table    │
              └──────────┬───────────┘
                         │
                         v
              ┌──────────────────────┐
              │  LIFE EVENT          │
              │  INTEGRATION         │
              │                      │
              │  Cash flow impacts   │
              │  Event-specific      │
              │  advice              │
              └──────────┬───────────┘
                         │
                         v
              ┌──────────────────────┐
              │  SPOUSE              │
              │  COORDINATION        │
              │  (if applicable)     │
              │                      │
              │  Combined planning   │
              │  Tax optimisation    │
              │  Survivor planning   │
              └──────────┬───────────┘
                         │
                         v
              ┌──────────────────────┐
              │  CROSS-MODULE        │
              │  INTEGRATION         │
              │                      │
              │  Estate (IHT)        │
              │  Investment (ISA)    │
              │  Protection          │
              │  Goals               │
              └──────────┬───────────┘
                         │
                         v
              ┌──────────────────────┐
              │  OUTPUT              │
              │                      │
              │  Summary metrics     │
              │  Breakdown by source │
              │  Recommendations     │
              │  Projections/charts  │
              │  Scenarios           │
              └──────────────────────┘
```

### 17.2 DB Pension Decision Sub-Tree

```
USER has DB pension:
    |
    ├── IS pension in payment (already retired from this scheme)?
    │   YES ──> Record as guaranteed income source
    │           Include in income projection at accrued value
    │
    ├── IS pension deferred (left the scheme, not yet at NRA)?
    │   YES ──> Apply revaluation to project value at NRA
    │           Check early/late retirement factors
    │
    └── IS pension active (still in the scheme)?
        YES ──> Project future accrual
                Check NRA vs target retirement age
                IF retiring before NRA: apply early retirement reduction
                IF retiring after NRA: apply late retirement enhancement

    SHOULD user consider DB TRANSFER? (CETV)
    |
    ──> CRITICAL: Fynla does NOT provide DB transfer advice.
        DB pension note in model: "DB pensions are captured for projection only —
        no DB to DC transfer advice is provided."

    HOWEVER, the decision tree should DOCUMENT the factors:
    |
    ├── CETV offered by scheme
    ├── Actuarial value of guaranteed benefits
    ├── Critical yield: investment return needed to match DB income
    │   IF critical yield > 5-6%: transfer unlikely to be beneficial
    │   IF critical yield < 3-4%: transfer may be worth exploring
    ├── Loss of guaranteed income (longevity risk transfers to member)
    ├── Loss of inflation protection
    ├── Loss of spouse pension
    ├── Death benefits comparison (DB lump sum vs DC pot)
    ├── Health: poor health may favour transfer (lower life expectancy)
    ├── FCA guidance: presumption against transfer for safeguarded benefits > £30,000
    ├── MUST take regulated advice for CETV > £30,000
    └── ──> "We recommend speaking to a regulated financial adviser about
             any DB pension transfer. This is a complex and irreversible decision."
```

### 17.3 State Pension Decision Sub-Tree

```
STATE PENSION analysis:
    |
    ├── HAS user entered State Pension data?
    │   NO ──> "Check your State Pension forecast at gov.uk/check-state-pension"
    │          Use default estimates (full SP if 35+ years projected)
    │
    ├── NI YEARS analysis:
    │   ├── completed >= 35 ──> Full SP projected
    │   ├── completed >= 10 ──> Partial SP: (years/35) × full_amount
    │   └── completed < 10 ──> "You need at least 10 qualifying years for any State Pension"
    │
    ├── NI GAPS:
    │   ├── Has fillable gaps? ──> Cost-benefit of voluntary NI
    │   │   cost = gap_years × £907.80
    │   │   benefit = gap_years × £342/year (for life)
    │   │   breakeven = cost / benefit (typically 2-3 years)
    │   │   ──> Almost always worthwhile
    │   └── Deadline: generally 6 years back, but extended deadline may apply
    │
    ├── DEFERRAL:
    │   ├── Rate: ~5.8% per year deferred
    │   ├── Breakeven: ~17 years
    │   ├── Best if: good health, other income, don't need SP immediately
    │   └── Worst if: poor health, need the income, would push into higher tax band
    │
    └── ALREADY RECEIVING:
        ──> Fixed income source, subject to triple lock annual increases
            Triple lock: highest of CPI, average earnings growth, or 2.5%
```

---

## 18. Existing Codebase Mapping

### 18.1 Services Already Built

| Service | File | Status | Covers |
|---------|------|--------|--------|
| `RetirementAgent` | `app/Agents/RetirementAgent.php` | Built | Orchestration, analysis, scenarios, portfolio |
| `PensionProjector` | `app/Services/Retirement/PensionProjector.php` | Built | DC, DB, State Pension projections |
| `AnnualAllowanceChecker` | `app/Services/Retirement/AnnualAllowanceChecker.php` | Built | AA, tapering, carry forward, MPAA |
| `ContributionOptimizer` | `app/Services/Retirement/ContributionOptimizer.php` | Built | Employer match, tax relief, required contributions |
| `DecumulationPlanner` | `app/Services/Retirement/DecumulationPlanner.php` | Built | Withdrawal rates, annuity vs drawdown, PCLS, phasing |
| `RetirementProjectionService` | `app/Services/Retirement/RetirementProjectionService.php` | Built | Monte Carlo, income drawdown, probability |
| `RetirementIncomeService` | `app/Services/Retirement/RetirementIncomeService.php` | Built | Tax-optimised income, fund depletion, all accounts |
| `RetirementStrategyService` | `app/Services/Retirement/RetirementStrategyService.php` | Built | 4-priority strategy waterfall, interactive impact |
| `RequiredCapitalCalculator` | `app/Services/Retirement/RequiredCapitalCalculator.php` | Built | Required capital, year-by-year table, FV/PV |
| `PensionPortfolioAnalyzer` | `app/Services/Retirement/PensionPortfolioAnalyzer.php` | Built | DC holdings analysis, fees, asset allocation |
| `RetirementActionDefinitionService` | `app/Services/Retirement/RetirementActionDefinitionService.php` | Built | Database-driven recommendations |

### 18.2 Models

| Model | Table | Key Fields |
|-------|-------|------------|
| `RetirementProfile` | `retirement_profiles` | target_retirement_age, target_retirement_income, essential/lifestyle expenditure, life_expectancy, care costs, prior_year_unused_allowance |
| `DCPension` | `dc_pensions` | scheme_name/type, provider, fund_value, contributions (% and £), fees, risk, MPAA fields, beneficiary |
| `DBPension` | `db_pensions` | scheme_name/type, accrued_pension, service_years, salary, NRA, revaluation, spouse %, lump sum |
| `StatePension` | `state_pensions` | NI years completed/required, forecast, SPA, already_receiving, gaps, gap_fill_cost |

---

## 19. Gaps: What Is Not Yet Built

### 19.1 Data Model Gaps

| Gap | Priority | Impact | Notes |
|-----|----------|--------|-------|
| Health/smoker status | Medium | Enhanced annuity eligibility | **NOT a data model gap** — smoker_status and health_status are already captured during user onboarding and stored on the User profile. The retirement engine should fetch these from `user.smoker_status` and `user.health_status` rather than adding new fields. If null, prompt the user to complete their profile (these questions are part of the onboarding flow). |
| DB accrual rate | Medium | Future accrual projection | Currently only captures accrued pension, not ongoing accrual |
| DB CETV value | Low | Transfer analysis (not providing advice) | For information/awareness only |
| DB early/late retirement factors | Medium | Accurate projection if retiring before/after NRA | Actuarial reduction/enhancement |
| DB commutation factor | Low | Lump sum calculation from DB | Scheme-specific |
| DB guaranteed annuity rate flag | High | Consolidation warning | Must warn before any transfer suggestion |
| State Pension deferral tracking | Medium | Deferral analysis | is_deferring, deferral_start_date |
| Contracted-out years | Low | Accurate old-system SP | Mainly legacy |
| Scottish taxpayer flag | Medium | Correct tax rate application | Different retirement tax calculations |
| Salary sacrifice availability | Medium | Salary sacrifice analysis | Per workplace pension |

### 19.2 Analysis Gaps

| Gap | Priority | Description |
|-----|----------|-------------|
| Salary sacrifice analysis | High | Full analysis of NI savings vs impact on benefits |
| Pension consolidation recommendation engine | High | Automated analysis of when to consolidate, with risk warnings |
| State Pension deferral breakeven calculator | Medium | Visual breakeven analysis |
| Voluntary NI cost-benefit calculator | Medium | Automated analysis with deadline awareness |
| DB pension early/late retirement impact | Medium | Actuarial reduction/enhancement modelling |
| Care cost modelling (detailed) | Medium | Integration with care cost phase of life |
| PLSA comparison | Low | Benchmark against Retirement Living Standards |
| Replacement ratio analysis | Low | Compare to income-based targets |
| Pension vs mortgage overpayment tool | Low | Interactive comparison |
| Phased crystallisation planner | Medium | Year-by-year PCLS strategy |
| Enhanced annuity eligibility check | Low | Based on health questionnaire |
| Divorce/pension sharing calculator | Low | Post-sharing projection update |
| Cross-module retirement income aggregation | Medium | Pull in ISA, bond, savings, rental income alongside pensions |

### 19.3 Withdrawal Rate Inconsistency (Code Issue)

The codebase uses **multiple different withdrawal rates** across services:

| Service | Rate | Constant Name |
|---------|------|---------------|
| `TaxDefaults` | 4.0% | `SAFE_WITHDRAWAL_RATE` |
| `RetirementProjectionService` | 4.7% | `SUSTAINABLE_WITHDRAWAL_RATE` |
| `RequiredCapitalCalculator` | 4.7% | `DEFAULT_WITHDRAWAL_RATE` |
| `PensionProjector` | 4.0% | Hardcoded `0.04` in `projectTotalRetirementIncome()` |
| `DecumulationPlanner` | 4.0% | Hardcoded `0.04` in comparison and PCLS methods |
| `RetirementIncomeService` | 4.7% | `ISA_WITHDRAWAL_RATE` (confusingly named for ISAs) |

**Recommendation:** Centralise to a single configurable constant. 4.0% is more commonly accepted for UK context. 4.7% is aggressive and may give users false confidence.

### 19.4 Missing User-Facing Decision Tools

| Tool | Description | Priority |
|------|-------------|----------|
| Retirement age calculator | "When can I afford to retire?" reverse calculator | High |
| Income gap closer | Interactive tool showing how to close the gap step by step | High |
| Pension pot target tracker | Visual progress toward required capital | Medium |
| State Pension checker integration | Pre-fill from gov.uk forecast | Medium |
| Annuity quote comparison | Allow user to compare annuity quotes | Low |
| Retirement budget planner | Detailed retirement expenditure breakdown | Medium |
| Pension finder service link | Trace lost pensions (Pension Tracing Service) | Low |

---

## Appendix A: Key UK Pension Legislation & Regulation

| Act / Regulation | Relevance |
|-----------------|-----------|
| Pensions Act 2004 | PPF, The Pensions Regulator |
| Pensions Act 2008 | Auto-enrolment |
| Pension Schemes Act 2015 | Freedom and Choice reforms |
| Finance Act 2004 (Part 4) | Tax regime for pensions, AA, LTA |
| Finance (No. 2) Act 2023 | LTA abolition |
| The Occupational and Personal Pension Schemes (Automatic Enrolment) Regulations 2010 | AE thresholds |
| FCA COBS 19 | Pension transfer advice rules |
| FCA PS22/6 | Strengthened DB transfer rules |

## Appendix B: Key Dates & Deadlines

| Date | Event |
|------|-------|
| 6 April 2025 | Start of 2025/26 tax year |
| 5 April 2026 | End of 2025/26 tax year |
| 6 April 2028 | Minimum pension age rises from 55 to 57 |
| 2026-2028 | SPA rises from 66 to 67 (phased) |
| 2044-2046 | SPA rises from 67 to 68 (under review, may be earlier) |
| 31 January (annually) | Self Assessment deadline (claim higher/additional rate pension tax relief) |

## Appendix C: Gov.uk Links for Users

| Resource | URL |
|----------|-----|
| Check State Pension | https://www.gov.uk/check-state-pension |
| State Pension age | https://www.gov.uk/state-pension-age |
| Pension tax relief | https://www.gov.uk/tax-on-your-private-pension |
| Annual allowance | https://www.gov.uk/tax-on-your-private-pension/annual-allowance |
| Pension tracing | https://www.gov.uk/find-pension-contact-details |
| Voluntary NI | https://www.gov.uk/voluntary-national-insurance-contributions |
| Pension wise (free guidance) | https://www.moneyhelper.org.uk/en/pensions-and-retirement |
| Benefits of deferring | https://www.gov.uk/deferring-state-pension |

---

*End of Retirement Decision Engine Research Document*
