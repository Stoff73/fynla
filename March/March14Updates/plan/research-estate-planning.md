# Estate Planning Decision Engine: Complete Decision Tree & Reference

> Exhaustive mapping of every decision path, user data input, analysis branch, threshold, message, and UK regulatory rule for the Fynla Estate Planning module.
>
> **Engine version:** v1.0.0 | **Date:** 2026-03-14 | **Tax year:** 2025/26

---

## Table of Contents

1. [Engine Pipeline Overview](#1-engine-pipeline-overview)
2. [User Context: Data Inputs](#2-user-context-data-inputs)
3. [Phase 1: Data Readiness Gate](#3-phase-1-data-readiness-gate)
4. [Phase 2: Estate Valuation Engine](#4-phase-2-estate-valuation-engine)
5. [Phase 3: IHT Liability Calculation](#5-phase-3-iht-liability-calculation)
6. [Phase 4: Allowance & Exemption Engine](#6-phase-4-allowance--exemption-engine)
7. [Phase 5: IHT Mitigation Decision Tree (7 Steps)](#7-phase-5-iht-mitigation-decision-tree-7-steps)
8. [Phase 6: Gifting Strategy Engine](#8-phase-6-gifting-strategy-engine)
9. [Phase 7: Trust Planning Engine](#9-phase-7-trust-planning-engine)
10. [Phase 8: Will & Intestacy Analysis](#10-phase-8-will--intestacy-analysis)
11. [Phase 9: Life Insurance Strategy](#11-phase-9-life-insurance-strategy)
12. [Phase 10: Pension Death Benefits Optimisation](#12-phase-10-pension-death-benefits-optimisation)
13. [Phase 11: Powers of Attorney Assessment](#13-phase-11-powers-of-attorney-assessment)
14. [Phase 12: Business & Agricultural Relief](#14-phase-12-business--agricultural-relief)
15. [Phase 13: Charitable Giving Optimisation](#15-phase-13-charitable-giving-optimisation)
16. [Phase 14: Domicile & Cross-Border Issues](#16-phase-14-domicile--cross-border-issues)
17. [Phase 15: Life Event Impact Engine](#17-phase-15-life-event-impact-engine)
18. [Phase 16: Estate Adequacy Assessment](#18-phase-16-estate-adequacy-assessment)
19. [Phase 17: Projected Estate Trajectory](#19-phase-17-projected-estate-trajectory)
20. [Phase 18: What-If Scenario Builder](#20-phase-18-what-if-scenario-builder)
21. [Thresholds & Constants Reference](#21-thresholds--constants-reference)
22. [Message Key Reference](#22-message-key-reference)
23. [Existing Codebase Mapping](#23-existing-codebase-mapping)

---

## 1. Engine Pipeline Overview

```
User Request
    |
    v
[Phase 1] DataReadinessGate ──── can_proceed = false? ──> STOP (return missing data prompts)
    |
    | can_proceed = true
    v
[Phase 2] EstateValuationEngine ──> gross_estate, net_estate, asset_breakdown, liquidity_analysis
    |
    v
[Phase 3] IHTLiabilityCalculation ──> current IHT, projected IHT at death, effective rate
    |
    v
[Phase 4] AllowanceExemptionEngine ──> NRB, RNRB, spouse exemption, charitable rate, transferable bands
    |
    v
[Phase 5] IHTMitigationDecisionTree ──> 7-step priority-ordered mitigation recommendations
    |
    v
[Phase 6] GiftingStrategyEngine ──> annual exemption, PETs, CLTs, normal expenditure, taper analysis
    |
    v
[Phase 7] TrustPlanningEngine ──> trust type selection, CLT scheduling, 10-year charge projections
    |
    v
[Phase 8] WillIntestacyAnalysis ──> will status, bequest analysis, intestacy distribution, trust triggers
    |
    v
[Phase 9] LifeInsuranceStrategy ──> whole of life, self-insurance, hybrid comparison
    |
    v
[Phase 10] PensionDeathBenefits ──> nomination status, drawdown vs annuity, age-75 planning
    |
    v
[Phase 11] PowersOfAttorney ──> LPA status, registration, named attorneys
    |
    v
[Phase 12] BusinessAgriculturalRelief ──> BR/AR qualification, holding periods, excepted assets
    |
    v
[Phase 13] CharitableGivingOptimisation ──> 36% rate analysis, baseline calculation, shortfall
    |
    v
[Phase 14] DomicileCrossBorder ──> domicile status, deemed domicile, treaty benefits
    |
    v
[Phase 15] LifeEventImpactEngine ──> marriage, divorce, death, birth, property, business triggers
    |
    v
[Phase 16] EstateAdequacyAssessment ──> completeness scoring, gap identification, priority actions
    |
    v
[Phase 17] ProjectedEstateTrajectory ──> asset-specific growth, Monte Carlo, cash flow surplus
    |
    v
[Phase 18] WhatIfScenarioBuilder ──> current, optimised, gifting, downsizing, trust scenarios
    |
    v
[Output] Personalised estate plan with prioritised recommendations
```

**Key principle:** Each phase feeds forward. Phase 5 (IHT Mitigation) is the central decision tree that orchestrates Phases 6-13 as sub-engines. The remaining IHT liability cascades through each step, with later steps only triggered if earlier steps leave residual liability.

---

## 2. User Context: Data Inputs

### 2.1 Personal Profile

| Field | Source | Used By | Required? |
|-------|--------|---------|-----------|
| `date_of_birth` | `user.date_of_birth` | Life expectancy, years to death, premium calculation | YES (Phase 1 gate) |
| `age` | Calculated from DOB | All age-dependent decisions | YES |
| `gender` | `user.gender` | Actuarial life expectancy, insurance premiums | YES |
| `marital_status` | `user.marital_status` | Spouse exemption, NRB transfer, intestacy | YES |
| `domicile_status` | `user.domicile_status` | UK/non-dom IHT scope | NO (default: UK) |
| `uk_resident` | `user.uk_resident` | Deemed domicile rules | NO (default: true) |
| `life_expectancy_override` | `user.life_expectancy_override` | Override actuarial default (85) | NO |
| `retirement_age` | `user.retirement_age` | Pension spending, cash flow projection | NO (default: 68) |

### 2.2 Family & Beneficiaries

| Field | Source | Used By | Required? |
|-------|--------|---------|-----------|
| `spouse` | `user.spouse` (linked User) | Spouse exemption, joint NRB, joint policies | NO |
| `spouse.date_of_birth` | `spouse.date_of_birth` | Second death projection, joint premium | NO |
| `spouse.gender` | `spouse.gender` | Premium calculation | NO |
| `children` | `family_members WHERE relationship = 'child'` | RNRB eligibility, intestacy, guardianship | NO |
| `grandchildren` | `family_members WHERE relationship = 'grandchild'` | RNRB eligibility, gifting recipients | NO |
| `parents` | `family_members WHERE relationship = 'parent'` | Intestacy fallback | NO |
| `siblings` | `family_members WHERE relationship IN ('sibling','brother','sister')` | Intestacy fallback | NO |
| `half_siblings` | `family_members WHERE relationship = 'half-sibling'` | Intestacy fallback | NO |
| `grandparents` | `family_members WHERE relationship = 'grandparent'` | Intestacy fallback | NO |
| `aunts_uncles` | `family_members WHERE relationship IN ('aunt','uncle')` | Intestacy fallback | NO |
| `dependents_count` | Count of child family members | Protection cross-referral | NO |
| `youngest_dependent_age` | Min age of child family members | Guardianship urgency | NO |

### 2.3 Asset Profile (via EstateAssetAggregatorService)

| Asset Type | Source Model | IHT Treatment | Ownership Pattern |
|------------|-------------|---------------|-------------------|
| Properties | `Property` | Taxable; main residence qualifies for RNRB | Joint query (forUserOrJoint) |
| Investments | `InvestmentAccount` | Taxable (including ISAs) | Joint query |
| Savings/Cash | `SavingsAccount` | Taxable (including Cash ISAs) | Joint query |
| DC Pensions | `DCPension` | **EXEMPT** if beneficiary nominated | Individual only |
| DB Pensions | `DBPension` | **EXEMPT** (no transfer value; income dies with member) | Individual only |
| Business Interests | `BusinessInterest` | May qualify for 100% BR | Joint query |
| Chattels | `Chattel` | Taxable (personal possessions, vehicles, jewellery, art) | Joint query |
| Estate Assets | `Estate\Asset` | Taxable (catch-all for manual entries) | Individual |

**Critical IHT exemptions:**
- DC pensions: `is_iht_exempt = true` (if beneficiary nominated)
- DB pensions: `is_iht_exempt = true` (no transfer value)
- Business interests with BPR: `is_iht_exempt = true` (if `bpr_eligible` AND `trading_status = 'trading'` AND owned 2+ years)
- Life insurance in trust: Not part of estate (separate query via `LifeInsurancePolicy WHERE in_trust = true`)

**Joint ownership IHT rules:**
- Joint tenancy: On first death, asset passes to survivor automatically (outside estate for IHT on first death in most cases)
- Tenants in common: User's share included in estate at death
- Ownership share: `current_value * (ownership_percentage / 100)` for primary owner; `current_value * ((100 - ownership_percentage) / 100)` for joint owner

### 2.4 Liability Profile

| Liability Type | Source Model | IHT Treatment |
|----------------|-------------|---------------|
| Mortgages | `Mortgage` | Deducted from gross estate |
| Personal loans | `Estate\Liability` | Deducted from gross estate |
| Credit cards | `Estate\Liability` | Deducted from gross estate |
| Other debts | `Estate\Liability` | Deducted from gross estate |
| Funeral expenses | Estimated or actual | Deducted from gross estate |

### 2.5 Existing Estate Planning Profile

| Field | Source | Used By |
|-------|--------|---------|
| `has_will` | `Estate\Will.has_will` | Will analysis, intestacy check |
| `will_last_updated` | `Estate\Will.will_last_updated` | Staleness check (3-year threshold) |
| `last_reviewed_date` | `Estate\Will.last_reviewed_date` | Staleness check |
| `executor_name` | `Estate\Will.executor_name` | Will completeness |
| `executor_notes` | `Estate\Will.executor_notes` | Trust trigger detection |
| `spouse_primary_beneficiary` | `Estate\Will.spouse_primary_beneficiary` | Spouse exemption applicability |
| `spouse_bequest_percentage` | `Estate\Will.spouse_bequest_percentage` | Second death calculation |
| `bequests` | `Estate\Bequest` (via Will) | Charitable analysis, distribution planning |
| `gifts` | `Estate\Gift` | PET analysis, annual exemption usage, taper relief |
| `trusts` | `Estate\Trust` | Trust valuation, 10-year charges, CLT history |
| `iht_profile` | `Estate\IHTProfile` | NRB transferred, RNRB transferred, charitable % |

### 2.6 IHT Profile Fields

| Field | Type | Purpose |
|-------|------|---------|
| `marital_status` | string | Determines spouse exemption availability |
| `has_spouse` | boolean | Quick check for spouse-related calculations |
| `own_home` | boolean | RNRB eligibility |
| `home_value` | float | RNRB quantum |
| `nrb_transferred_from_spouse` | float | Widowed: transferred NRB from late spouse (0 to 325,000) |
| `rnrb_transferred_from_spouse` | float | Widowed: transferred RNRB from late spouse (0 to 175,000) |
| `charitable_giving_percent` | float | For 36% reduced rate check |

### 2.7 Gift Records

| Field | Type | Purpose |
|-------|------|---------|
| `gift_date` | date | 7-year clock, taper relief calculation |
| `recipient` | string | Small gifts tracking per recipient |
| `gift_type` | string | `pet`, `small_gift`, `annual_exemption`, `wedding_gift`, `normal_expenditure` |
| `gift_value` | float | Value for PET/CLT cumulation |
| `status` | string | Active, expired, etc. |
| `taper_relief_applicable` | boolean | Whether taper relief applies (3-7 years) |
| `notes` | string | Documentation for HMRC evidence |

### 2.8 Trust Records

| Field | Type | Purpose |
|-------|------|---------|
| `trust_type` | string | `bare`, `discretionary`, `interest_in_possession`, `accumulation_maintenance`, `disabled_person`, `loan`, `discounted_gift`, `life_insurance` |
| `trust_creation_date` | date | 10-year anniversary calculation |
| `initial_value` | float | Original settlement value |
| `current_value` | float | Current trust value |
| `discount_amount` | float | For discounted gift trusts |
| `retained_income_annual` | float | For discounted gift trusts |
| `loan_amount` | float | For loan trusts (stays in estate) |
| `sum_assured` | float | For life insurance trusts |
| `annual_premium` | float | For life insurance trusts |
| `is_relevant_property_trust` | boolean | Subject to 10-year charges |
| `last_periodic_charge_date` | date | Next 10-year charge tracking |
| `beneficiaries` | text | Named beneficiaries |
| `trustees` | text | Named trustees |
| `settlor` | text | Who created the trust |

### 2.9 Life Insurance Data

| Field | Source | Used By |
|-------|--------|---------|
| `sum_assured` | `LifeInsurancePolicy.sum_assured` | Cover gap analysis |
| `in_trust` | `LifeInsurancePolicy.in_trust` | Whether proceeds bypass estate — **CRITICAL CHECK** |
| `trust_type` | `LifeInsurancePolicy.trust_type` | Type of trust if in trust (bare, discretionary, split) |
| `policy_type` | `LifeInsurancePolicy.policy_type` | Whole of life vs term |
| `joint_life` | `LifeInsurancePolicy.joint_life` | Whether joint life (first death or second death) |
| `premium_amount` | `LifeInsurancePolicy.premium_amount` | Affordability |
| `premium_type` | `LifeInsurancePolicy.premium_type` | Guaranteed vs reviewable premiums |

**Trust status check is critical for estate planning:**
- `in_trust = true` → proceeds are OUTSIDE the estate (not included in gross estate calculation)
- `in_trust = false` → proceeds form PART of the estate and are subject to IHT
- If `in_trust = false` AND `policy_type = 'whole_of_life'` → **FLAG**: "This whole of life policy is not written in trust. Proceeds of {sum_assured} will form part of your estate and may be subject to IHT. Consider placing this policy into trust to remove it from your estate."
- If `in_trust = false` AND `policy_type = 'term'` → **WARN**: "This term policy is not written in trust. If a claim is made, the proceeds will form part of your estate."

### 2.10 Income & Expenditure (for Gifting from Income)

**CRITICAL: Do NOT recalculate surplus income.** The surplus income figure is already calculated and stored by the Income module. The Estate Planning engine MUST fetch `surplus_income` directly from the database / Income module — it must NOT independently calculate total income minus expenditure. The Income section is the single source of truth for surplus income.

| Field | Source | Used By |
|-------|--------|---------|
| `surplus_income` | Fetched from Income module (pre-calculated) | Normal expenditure from income gifting, affordability |
| `monthly_expenditure` | `ExpenditureProfile` (read-only reference) | Standard of living verification for gifting evidence |

The Income module already computes: `surplus_income = total_income - total_expenditure`. The Estate engine reads this value. It never derives it independently.

---

## 3. Phase 1: Data Readiness Gate

**Purpose:** Determine whether we have enough data to produce a meaningful estate analysis. Unlike investment recommendations (which have strict gates), estate planning can provide partial value even with limited data.

### Decision Tree

**CRITICAL: No assumptions.** The data readiness gate must check for ACTUAL user data, not inferred or default values. We do not auto-create IHT profiles or assume defaults — we tell the user exactly what is missing and why it matters.

```
START — Check each data point individually (no IHT profile abstraction)
  |
  v
[Check 1] BLOCKING: Does user have date_of_birth?
  |
  +-- NO --> BLOCK: "Add your date of birth to calculate life expectancy
  |          and project your estate forward."
  |          can_proceed = false
  |
  +-- YES → continue
       |
       v
[Check 2] BLOCKING: Does user have marital_status?
  |
  +-- NO --> BLOCK: "Your marital status determines whether spouse
  |          exemption and transferable allowances are available."
  |          can_proceed = false
  |
  +-- YES → continue
       |
       v
[Check 3] BLOCKING: Does user have at least one asset (property, savings, investment)?
  |
  +-- NO --> BLOCK: "Add your assets to calculate your estate value."
  |          can_calculate_iht = false
  |
  +-- YES → continue
       |
       v
[Check 4] Does user have UK residency / domicile status recorded?
  |
  +-- NO --> WARN: "Your residency and domicile status affects which
  |          assets are subject to UK IHT."
  |          (non-blocking — assume UK domiciled, flag for confirmation)
  |
  +-- YES → continue
       |
       v
[Check 5] Does user have property data?
  |
  +-- NO --> WARN: "Add your property details to assess Residence Nil
  |          Rate Band eligibility (worth up to £175,000)."
  |
  +-- YES → Is it marked as main_residence or renting?
       |    +-- main_residence → RNRB analysis enabled
       |    +-- renting / no property → RNRB = £0 (inform user why)
       |
       v
[Check 6] Does user have liabilities recorded (mortgage, debts)?
  |
  +-- NO --> INFO: "Add your liabilities for an accurate net estate
  |          calculation. Without them, we assume no debts."
  |
  +-- YES → continue
       |
       v
[Check 7] Does user have dependents / family members recorded?
  |
  +-- NO --> WARN: "Add your family members to assess RNRB eligibility
  |          (requires direct descendants) and intestacy distribution."
  |
  +-- YES → continue
       |
       v
[Check 8] Has user recorded any lifetime gifts?
  |
  +-- NO --> INFO: "Record any gifts you have made. Gifts within the
  |          last 7 years may affect your IHT calculation."
  |
  +-- YES → continue
       |
       v
[Check 9] Does user have a will recorded?
  |
  +-- NO --> WARN: "Consider creating or recording your will. Without
  |          one, your estate will be distributed under intestacy rules."
  |          (non-blocking — analysis continues with intestacy scenario)
  |
  +-- YES → continue
       |
       v
[Check 10] Does user have income & expenditure data?
  |
  +-- NO --> INFO: "Add income and expenditure to assess gifting from
  |          surplus income opportunities (an unlimited IHT exemption)."
  |
  +-- YES → continue
       |
       v
[Check 11] Does user have existing life insurance policies?
  |
  +-- NO --> INFO: "Add any life insurance policies to assess whether
  |          they are in trust and whether they provide IHT cover."
  |
  +-- YES → Check trust status (see Section 2.9)
       |
       v
[Check 12] Does user have Powers of Attorney recorded?
  |
  +-- NO --> INFO: "Consider setting up Lasting Powers of Attorney
  |          for Property & Financial Affairs and Health & Welfare."
  |
  +-- YES → continue
       |
       v
[PROCEED to Phase 2]
```

**Minimum viable data for IHT calculation (BLOCKING):**
- Date of birth
- Marital status
- At least one asset with value > 0

**Data that enhances accuracy (NON-BLOCKING):**
- UK residency / domicile status
- Property details (main residence vs renting)
- Liabilities (mortgage, debts)
- Family members / dependents
- Lifetime gifts
- Will status
- Income & expenditure (for gifting analysis)
- Life insurance policies (for trust status check)
- Powers of Attorney

### Missing Data Prompt Messages

| Missing Data | Message Key | Priority |
|-------------|-------------|----------|
| No DOB | `readiness.missing_dob` | HIGH |
| No assets | `readiness.missing_assets` | HIGH |
| No will | `readiness.missing_will` | MEDIUM |
| No IHT profile | `readiness.missing_iht_profile` | LOW (auto-created) |
| No expenditure data | `readiness.missing_expenditure` | LOW |
| No family members | `readiness.missing_family` | LOW |
| No life insurance data | `readiness.missing_life_cover` | LOW |

---

## 4. Phase 2: Estate Valuation Engine

**Service:** `EstateAssetAggregatorService`
**File:** `app/Services/Estate/EstateAssetAggregatorService.php`

### 4.1 Gross Estate Calculation

```
Gross Estate = SUM of all taxable assets at current market value

  Properties (user's share based on ownership_percentage)
+ Investment Accounts (user's share)
+ Savings Accounts / Cash (user's share)
+ Business Interests (user's share, BEFORE BR applied)
+ Chattels / Personal Possessions (user's share)
+ Estate Assets (manual entries)
+ Life Insurance NOT in trust (sum_assured)
+ Trust assets counted in estate:
    - Interest in Possession trusts (current_value for life tenant)
    - Loan trusts (loan_amount only, not growth)
    - Discounted Gift trusts (discount_amount only)
- EXCLUDE: DC Pensions (IHT exempt if beneficiary nominated)
- EXCLUDE: DB Pensions (no transfer value)
- EXCLUDE: Life Insurance IN trust (bypasses estate)
- EXCLUDE: Bare trusts (beneficiary's estate, not settlor's)
- EXCLUDE: Discretionary trusts (relevant property regime)
- EXCLUDE: BPR-qualifying businesses (100% relief)
```

### 4.2 Net Estate Calculation

```
Net Estate = Gross Estate - Total Liabilities

Total Liabilities:
  Mortgages (user's share)
+ Personal Loans (user's share)
+ Credit Cards
+ Other Debts
+ Estimated Funeral Expenses (if configured)
```

### 4.3 Liquidity Classification

```
Liquid Assets:
  Cash accounts, Savings accounts (easy access, notice)
  → Immediately available to pay IHT / fund gifts
  → Includes: current accounts, savings accounts, Cash ISAs, Premium Bonds, NS&I

Semi-Liquid Assets:
  Investment accounts (ISAs, GIAs, bonds)
  → Can be sold but may take days/weeks
  → May incur CGT on disposal
  → Subject to market conditions at time of death

Illiquid Assets:
  Properties, Business Interests, Pensions
  → Properties may need to be sold to pay IHT (6-month deadline)
  → HMRC instalment option available for property and business assets
  → Pensions: outside estate for IHT but NOT available to pay IHT liability
    (pensions cannot be used to fund IHT payment — they pass to beneficiaries)
```

**Liquidity ratio:** `liquid_assets / iht_liability`
- Ratio < 0.5 → **LIQUIDITY RISK** flagged (Step 2 of mitigation tree)
- Ratio >= 1.0 → Sufficient liquidity

### 4.4 Joint Estate (Married Couples)

For married couples with data sharing enabled:

```
Combined Gross Estate = User Gross Assets + Spouse Gross Assets
Combined Liabilities = User Liabilities + Spouse Liabilities
Combined Net Estate = Combined Gross Estate - Combined Liabilities
```

**First death scenario:** Spouse exemption means no IHT on assets passing to surviving spouse.
**Second death scenario:** Entire combined estate potentially subject to IHT (but with doubled NRB/RNRB).

---

## 5. Phase 3: IHT Liability Calculation

**Service:** `IHTCalculationService`
**File:** `app/Services/Estate/IHTCalculationService.php`

### 5.1 Current IHT Calculation

```
STEP 1: Calculate Net Estate
         net_estate = gross_assets - total_liabilities

STEP 2: Determine NRB Available
         |
         +-- For EACH person (user AND spouse if married), calculate:
         |   base_nrb = £325,000 (from TaxConfigService)
         |   pets_in_7_years = SUM(gifts WHERE gift_type = 'pet'
         |                    AND gift_date > NOW - 7 years)
         |   clts_in_7_years = SUM(gifts WHERE gift_type = 'clt'
         |                    AND gift_date > NOW - 7 years)
         |   nrb_used = MIN(base_nrb, pets_in_7_years + clts_in_7_years)
         |   available_nrb = base_nrb - nrb_used
         |
         +-- Single person:
         |   nrb = available_nrb (user only)
         |
         +-- Married (both alive):
         |   nrb = user_available_nrb + spouse_available_nrb
         |   (Check BOTH accounts — each person's PETs/CLTs reduce THEIR NRB)
         |   (Spouse exemption: transfers between spouses are IHT-free on first death)
         |   Maximum combined: £650,000 (if neither has made chargeable gifts)
         |
         +-- Widowed:
             surviving_nrb = available_nrb (survivor)
             transferred_nrb = nrb_transferred_from_spouse
             (Transferred NRB = unused proportion of late spouse's NRB at THEIR death)
             (Late spouse's PETs/CLTs in 7 years before THEIR death reduce THEIR NRB)
             total_nrb = surviving_nrb + transferred_nrb
             Maximum transfer: £325,000 (100% of spouse's NRB)

STEP 3: Calculate RNRB Available
         |
         +-- Does user own a main residence? NO --> rnrb = £0
         |
         +-- YES: Is main residence left to direct descendants
         |   (children, grandchildren, step-children)?
         |   |
         |   +-- NO --> rnrb = £0
         |   |
         |   +-- YES: Calculate base RNRB
         |       |
         |       +-- Single: rnrb = MIN(£175,000, property_value)
         |       |
         |       +-- Married: rnrb = MIN(£175,000 x 2, combined_property_value)
         |       |
         |       +-- Widowed: rnrb = own_rnrb + rnrb_transferred_from_spouse
         |
         +-- RNRB Taper Check:
             |
             +-- net_estate <= £2,000,000 --> Full RNRB available
             |
             +-- net_estate > £2,000,000 -->
                 taper_reduction = (net_estate - 2,000,000) * 0.5
                 rnrb_after_taper = MAX(0, rnrb - taper_reduction)

STEP 4: Calculate Taxable Estate
         total_allowances = nrb + rnrb
         taxable_estate = MAX(0, net_estate - total_allowances)

STEP 5: Determine IHT Rate
         |
         +-- Check charitable giving:
         |   baseline = net_estate - nrb (RNRB excluded from baseline)
         |   charitable_bequests = total charitable bequests in will
         |   |
         |   +-- charitable_bequests >= baseline * 0.10 -->
         |   |   iht_rate = 0.36 (36% reduced rate)
         |   |
         |   +-- charitable_bequests < baseline * 0.10 -->
         |       iht_rate = 0.40 (40% standard rate)

STEP 6: Calculate IHT Liability
         iht_liability = taxable_estate * iht_rate
         effective_rate = (iht_liability / net_estate) * 100
```

### 5.2 Projected IHT at Death

The service projects estate value forward to estimated death date using asset-specific growth models:

```
Asset Projection Methods:
  Cash/Savings:   Income/expense surplus model
                  cash_at_death = current_cash + (annual_surplus * years_to_death)
                  (where annual_surplus = annual_income - annual_expenditure)

  Investments:    Monte Carlo simulation (80% confidence) or custom growth rate
                  investment_at_death = monte_carlo_projection(current_value, years)
                  OR: current_value * (1 + custom_rate)^years

  Properties:     Configurable growth rate (default 3% p.a.)
                  property_at_death = current_value * (1 + 0.03)^years

  Liabilities:    Amortisation to end date
                  If end_date < death_date: liability = 0 (paid off)
                  Else: remaining balance at death
```

**For married couples:** Projects to SECOND death (whoever lives longer based on actuarial tables).

### 5.3 Calculation Messages

| Scenario | Message Template |
|----------|-----------------|
| Married, both NRBs | "Combined Nil Rate Band of {nrb_combined} available ({nrb_single} each). Transfers between spouses are exempt from IHT on first death." |
| Widowed, transferred NRB | "Combined Nil Rate Band of {nrb_total} available (own {nrb_single} + {nrb_transferred} transferred from late spouse's estate)." |
| Single | "Nil Rate Band of {nrb_single} available for single person." |
| RNRB eligible | "Residence Nil Rate Band of {rnrb} available for main residence left to direct descendants." |
| RNRB tapered | "Residence Nil Rate Band reduced from {rnrb_full} to {rnrb_tapered} because estate exceeds {taper_threshold}." |
| RNRB not eligible (no property) | "No Residence Nil Rate Band available — no main residence identified." |
| RNRB not eligible (no descendants) | "No Residence Nil Rate Band available — main residence must pass to direct descendants (children, grandchildren, step-children). Nieces, nephews, cousins, siblings, and other relatives are NOT direct descendants and do not qualify for RNRB." |
| Charitable rate | "Charitable bequests of {charitable_total} qualify for the reduced 36% IHT rate, saving {saving} in IHT." |
| Near charitable threshold | "Increase charitable giving by {shortfall} to qualify for the reduced 36% rate and save {potential_saving} in IHT." |

---

## 6. Phase 4: Allowance & Exemption Engine

### 6.1 Complete Exemption Map

```
EXEMPTION DECISION TREE
========================

[1] SPOUSE / CIVIL PARTNER EXEMPTION
     |
     +-- Is beneficiary spouse/civil partner? YES --> UNLIMITED exemption
     |   (All assets passing to spouse are 100% IHT-free)
     |   NOTE: Only defers IHT to second death, does not eliminate it
     |
     +-- NO --> Continue to other exemptions

[2] CHARITY EXEMPTION
     |
     +-- Is beneficiary a registered UK charity? YES --> FULLY exempt
     |   PLUS: If charitable giving >= 10% of baseline --> Reduced rate (36%)
     |
     +-- NO --> Continue

[3] NIL RATE BAND (NRB)
     |
     +-- Standard NRB: £325,000 per person
     |
     +-- Transferable NRB (widowed):
     |   unused_percentage = (spouse_nrb - spouse_nrb_used) / spouse_nrb
     |   transferred_nrb = unused_percentage * current_nrb
     |   Maximum: 100% of current NRB (£325,000)
     |
     +-- Maximum combined (married): £650,000

[4] RESIDENCE NIL RATE BAND (RNRB)
     |
     +-- Conditions (ALL must be met):
     |   a) Deceased owned a qualifying residential interest
     |   b) Property is left to direct descendants:
     |      ELIGIBLE: children, grandchildren, great-grandchildren, step-children
     |      NOT ELIGIBLE: nieces, nephews, cousins, siblings, friends, charities
     |   c) Property was deceased's residence at some point
     |
     +-- Base amount: £175,000 per person
     |
     +-- Taper (estates over £2m):
     |   reduction = (net_estate - 2,000,000) / 2
     |   rnrb_available = MAX(0, 175,000 - reduction)
     |   Estate of £2,350,000 = RNRB fully tapered to £0
     |
     +-- Transferable RNRB (widowed): Same principle as NRB transfer
     |
     +-- Downsizing provisions:
     |   If deceased downsized/sold home after 8 July 2015,
     |   RNRB may still be available on assets of equivalent value
     |   left to direct descendants
     |
     +-- Maximum combined (married): £350,000

[5] BUSINESS RELIEF (BR)
     |
     +-- See Phase 12 for full decision tree
     |
     +-- 100% relief: Trading business, partnership interest, unquoted shares
     +-- 50% relief: Controlling quoted shares, business-used property
     +-- Minimum 2-year holding period

[6] AGRICULTURAL RELIEF (AR)
     |
     +-- See Phase 12 for full decision tree
     |
     +-- 100% relief: Owner-occupied farmland, post-1995 tenancies
     +-- 50% relief: Pre-1995 tenancies
     +-- Character appropriate test

[7] GIFTS EXEMPTIONS
     |
     +-- Annual exemption: £3,000/year (carry forward 1 year)
     +-- Small gifts: £250/person/year (unlimited recipients)
     +-- Wedding gifts: £5,000/£2,500/£1,000 by relationship
     +-- Normal expenditure from income: UNLIMITED if conditions met
     +-- PETs: Exempt after 7 years
```

### 6.2 NRB Transfer Calculation (SpouseNRBTrackerService)

```
TRANSFERABLE NRB CALCULATION
=============================

1. Get deceased spouse's original NRB at their death: £325,000 (current)
2. Calculate how much of their NRB was used:
   - PETs within 7 years of their death (that became chargeable)
   - CLTs within 7 years of their death
   - IMPORTANT (14-YEAR RULE): CLTs made up to 14 years before death
     can affect the NRB available. CLTs made 7-14 years before death
     do not incur IHT themselves, but they ARE counted when calculating
     the NRB available for PETs/CLTs made in the final 7 years.
     See Section 8.4 for full 14-year rule detail.
   - nrb_used = MIN(nrb, total_chargeable_transfers_within_cumulation_window)
3. Calculate unused percentage:
   - unused_pct = (nrb - nrb_used) / nrb * 100
4. Apply percentage to CURRENT NRB:
   - transferred_nrb = unused_pct / 100 * current_nrb
5. Survivor's total NRB:
   - total_nrb = own_nrb + transferred_nrb
   - Maximum: £650,000 (2 x NRB)

NOTE: The 14-year rule means we must check CLTs going back 14 years
from death, not just 7 years, when determining how much NRB was
consumed. A CLT made 10 years before death is itself exempt from IHT,
but it reduces the NRB available for any PETs made in the final 7 years.
```

### 6.3 RNRB Detailed Calculation

```
RNRB CALCULATION
================

Input:
  net_estate, main_residence_value, has_direct_descendants,
  is_married, is_widowed, rnrb_transferred

Step 1: Base eligibility
  IF NOT has_direct_descendants --> rnrb = 0, STOP
  IF NOT owns_main_residence --> Check downsizing provisions, else rnrb = 0

Step 2: Calculate base RNRB
  rnrb_individual = MIN(175,000, main_residence_value)

Step 3: Add transferred RNRB (widowed)
  IF is_widowed AND rnrb_transferred > 0:
    rnrb_total = rnrb_individual + rnrb_transferred

Step 4: Double for married couples
  IF is_married:
    rnrb_total = rnrb_individual * 2

Step 5: Apply taper
  IF net_estate > 2,000,000:
    taper = (net_estate - 2,000,000) * 0.5
    rnrb_final = MAX(0, rnrb_total - taper)
  ELSE:
    rnrb_final = rnrb_total

RNRB Status Messages:
  'eligible'     = Full RNRB available
  'tapered'      = Partial RNRB (estate over £2m)
  'eliminated'   = RNRB fully tapered (estate over £2.35m individual / £2.7m couple)
  'not_eligible' = No qualifying property or no direct descendants
```

---

## 7. Phase 5: IHT Mitigation Decision Tree (7 Steps)

**Service:** `EstateAgent.generateRecommendations()`
**File:** `app/Agents/EstateAgent.php`

This is the central decision engine. Steps are executed in priority order. Each step reduces the `remainingLiability`. Later steps only fire if earlier steps leave residual liability.

```
IHT LIABILITY > 0?
  |
  +-- NO --> "No IHT liability projected - no mitigation strategies needed"
  |          STOP
  |
  +-- YES
       |
       v
[STEP 1] CHARITABLE BEQUEST CHECK (Rate Reduction)
  |
  +-- charitable_bequests >= 10% of baseline?
  |   |
  |   +-- YES --> "Reduced 36% rate applied. Saving: {saving}"
  |   |           (Rate already factored into IHT calculation)
  |   |
  |   +-- NO --> Calculate shortfall and potential saving
  |       |
  |       +-- shortfall < potential_saving?
  |           (i.e., is it worth giving away more to charity than you save?)
  |           |
  |           +-- YES --> "Increase charitable giving by {shortfall} to save {saving}"
  |           |           Priority: HIGH
  |           |
  |           +-- NO --> Skip (charitable giving not cost-effective)
  |
  v
[STEP 2] LIQUIDITY & AFFORDABILITY ASSESSMENT
  |
  +-- liquidity_ratio = liquid_assets / iht_liability
  |
  +-- liquidity_ratio < 0.5?
  |   |
  |   +-- YES --> "Liquidity risk: liquid assets {liquid} may not cover IHT {iht}"
  |   |           Actions: Life insurance in trust, downsizing, build liquid savings
  |   |           Priority: HIGH
  |   |
  |   +-- NO --> No liquidity warning needed
  |
  v
[STEP 3] CHECK EXISTING LIFE COVER
  |
  +-- total_cover_in_trust > 0?
  |   |
  |   +-- YES --> usable_cover = cover_in_trust - liabilities
  |   |           remaining_liability -= usable_cover
  |   |           "Existing life cover: {usable_cover} offsets IHT"
  |   |
  |   +-- NO --> No existing cover to apply
  |
  +-- policies_not_in_trust > 0?
  |   |
  |   +-- YES --> "Place {count} policies in trust to bypass estate"
  |   |           Priority: MEDIUM
  |
  v
[STEP 4] ANNUAL GIFTING STRATEGY (First Resort)
  |
  +-- years_to_life_expectancy = life_expectancy - current_age
  +-- annual_gifting_capacity = £3,000 * years_to_life_expectancy
  +-- potential_iht_saving = MIN(annual_gifting_capacity * 0.40, remaining_liability)
  |
  +-- covers_liability?
  |   |
  |   +-- YES --> "Annual gifting of £3,000/year could fully offset IHT
  |   |            over {years} years"
  |   |           Priority: HIGH
  |   |
  |   +-- NO --> "Annual gifting could save {saving} in IHT"
  |              Priority: MEDIUM
  |
  +-- remaining_liability -= potential_iht_saving
  |
  +-- Also recommend:
      - Small gifts (£250/person)
      - Wedding gifts (£5,000/£2,500/£1,000)
      - Normal expenditure from income
  |
  v
[STEP 5] LIFE COVER STRATEGY (Second Resort)
  |
  +-- current_age <= 50?
  |   |
  |   +-- NO --> Skip (premiums prohibitive after 50)
  |   |
  |   +-- YES AND remaining_liability > 0:
  |       |
  |       +-- estimated_premium = remaining_liability * 0.02
  |       +-- "Whole of life cover for {remaining} at ~{premium}/year"
  |       +-- "MUST be written in trust"
  |       +-- remaining_liability -= remaining_liability (fully covered)
  |       +-- Priority: MEDIUM
  |
  v
[STEP 6] PET GIFTING STRATEGY (Third Resort)
  |
  +-- remaining_liability > 0 AND years_to_life_expectancy >= 7?
  |   |
  |   +-- NO --> Skip (not enough time for PETs to become exempt)
  |   |
  |   +-- YES:
  |       seven_year_cycles = FLOOR(years_to_life_expectancy / 7)
  |       pet_capacity = seven_year_cycles * nrb (£325,000)
  |       potential_saving = MIN(pet_capacity * 0.40, remaining_liability)
  |       |
  |       +-- "With {cycles} seven-year cycles, PETs up to {capacity}
  |            could become fully exempt"
  |       +-- remaining_liability -= potential_saving
  |       +-- Priority: MEDIUM
  |
  v
[STEP 7] CLT INTO TRUST (Last Resort ONLY)
  |
  +-- remaining_liability > 0?
  |   |
  |   +-- NO --> All liability covered by Steps 1-6
  |   |
  |   +-- YES:
  |       excess_over_nrb = MAX(0, remaining_liability - nrb)
  |       immediate_charge = excess_over_nrb * 0.20
  |       |
  |       +-- "CLT of {remaining} would incur {charge} immediate charge"
  |       +-- "Additional 20% if death within 7 years"
  |       +-- "Trust subject to periodic charges (max 6% every 10 years)"
  |       +-- Priority: LOW
  |       +-- WARNING: "CLTs are complex - exhaust simpler strategies first"
```

### Step Priority & Effectiveness Summary

| Step | Strategy | Effectiveness | Cost | Complexity | When to Use |
|------|----------|--------------|------|------------|-------------|
| 1 | Charitable bequest (36% rate) | Medium-High | Charitable gift amount | Low | Always check first |
| 2 | Liquidity assessment | N/A (diagnostic) | None | Low | Always |
| 3 | Existing life cover | High | Already paid | Low | Always check |
| 4 | Annual gifting (£3k/year) | Medium | £3k/year from estate | Very Low | Always (first resort) |
| 5 | Whole of life insurance | High | Annual premiums | Low | Age <= 50 |
| 6 | PETs (7-year gifts) | High | Capital given away | Medium | Years to death >= 7 |
| 7 | CLT into trust | Variable | 20% immediate + periodic | High | Last resort only |

---

## 8. Phase 6: Gifting Strategy Engine

**Services:** `GiftingStrategy`, `GiftingStrategyOptimizer`, `PersonalizedGiftingStrategyService`
**Files:** `app/Services/Estate/GiftingStrategy.php`, `GiftingStrategyOptimizer.php`, `PersonalizedGiftingStrategyService.php`

### 8.1 Gifting Exemption Decision Tree

```
GIFTING EXEMPTIONS
==================

[A] ANNUAL EXEMPTION (£3,000/year)
     |
     +-- Current tax year gifts total?
     |   (Tax year: 6 April to 5 April)
     |
     +-- Previous year exemption unused?
     |   carry_forward = MAX(0, £3,000 - previous_year_gifts)
     |   (Can carry forward ONE year only)
     |
     +-- Available this year:
         total_available = £3,000 + carry_forward
         remaining = MAX(0, total_available - current_year_gifts)

[B] SMALL GIFTS (£250/person/year)
     |
     +-- Per recipient per tax year
     +-- UNLIMITED number of recipients
     +-- CANNOT combine with annual exemption for same person
     +-- Group by recipient, check <= £250 per recipient
     |
     +-- If total to recipient > £250 --> "Exceeds £250 limit for {recipient}"

[C] WEDDING / CIVIL PARTNERSHIP GIFTS
     |
     +-- Relationship to recipient?
         |
         +-- Child (son/daughter): £5,000
         +-- Grandchild/Great-grandchild: £2,500
         +-- Other person: £1,000
     |
     +-- Conditions:
         - Must be given before the ceremony
         - Must be conditional on the marriage/civil partnership taking place

[D] NORMAL EXPENDITURE FROM INCOME
     |
     +-- Conditions (ALL three must be met):
     |   1. Gifts are from INCOME, not capital
     |   2. Gifts form a REGULAR pattern (habitual)
     |   3. Donor maintains normal STANDARD OF LIVING after gifts
     |
     +-- UNLIMITED amount if conditions met
     +-- IMMEDIATELY exempt (no 7-year wait)
     |
     +-- Surplus Income:
         surplus_income = FETCH from Income module (pre-calculated, stored in DB)
         DO NOT recalculate — the Income section is the single source of truth
         max_gifting_from_income = surplus_income (if > 0)
     |
     +-- Evidence requirements:
         - Income and expenditure records (3+ years)
         - Pattern of regular giving
         - Proof standard of living maintained
     |
     +-- Examples:
         - Regular pension contributions for family
         - School/university fees
         - Life insurance premiums for family
         - Regular payments to children

[E] POTENTIALLY EXEMPT TRANSFERS (PETs)
     |
     +-- Any gift to an individual (not into trust)
     +-- NO limit on amount
     +-- NO immediate tax charge
     |
     +-- 7-year clock:
     |   gift_date --> 7 years later --> FULLY EXEMPT
     |
     +-- If donor dies within 7 years:
     |   PET becomes CHARGEABLE
     |   Uses donor's NRB
     |   Cumulated with other PETs/CLTs in 7 years before death
     |
     +-- Taper relief (death between 3-7 years):
         | Years since gift | Tax rate (% of 40%) | Effective rate |
         |------------------|---------------------|----------------|
         | 0 - 3            | 100%                | 40%            |
         | 3 - 4            | 80%                 | 32%            |
         | 4 - 5            | 60%                 | 24%            |
         | 5 - 6            | 40%                 | 16%            |
         | 6 - 7            | 20%                 | 8%             |
         | 7+               | 0%                  | 0% (exempt)    |
     |
     +-- NOTE: Taper relief only reduces TAX, not the VALUE of the gift
         It only helps if the gift ITSELF exceeds available NRB

[F] GIFT WITH RESERVATION OF BENEFIT (GROB)
     |
     +-- WARNING: Gift is INEFFECTIVE for IHT if:
     |   - Donor continues to benefit from the gifted asset
     |   - Example: Gift house to child but continue living in it rent-free
     |   - Example: Gift painting but keep it on your wall
     |
     +-- The asset remains in the donor's estate for IHT
     |
     +-- Exception: Donor pays full market rent/consideration
     |
     +-- Pre-Owned Assets Tax (POAT):
         Income tax charge on benefit from assets you previously owned
         Applies even if GROB rules don't catch it
```

### 8.2 Optimal Gifting Strategy (GiftingStrategyOptimizer)

The optimizer prioritises strategies by cost-effectiveness:

```
GIFTING STRATEGY PRIORITY ORDER
================================

1. Annual Exemption (£3,000/year)
   - Zero risk, immediately exempt
   - iht_saved = annual_exemption * years_to_death * 0.40

2. Planned Gift Life Events
   - User's planned future gifts (from Goals module)
   - Already committed to, factor into projections

3. Gifting from Surplus Income
   - Immediately exempt if conditions met
   - surplus = total_income - annual_expenditure
   - iht_saved = surplus * years_to_death * 0.40
   - Only if can_afford = true

4. PET Strategy (7-year cycles)
   - cycles = FLOOR(years_to_death / 7)
   - per_cycle = MIN(nrb, available_liquid_assets / cycles)
   - total_pet = per_cycle * cycles
   - iht_saved = total_pet * 0.40 (if survive all cycles)

5. CLT Strategy (Last resort)
   - Only if remaining_liability > 0 after steps 1-4
   - immediate_charge = excess_over_nrb * 0.20
   - Net benefit = (amount * 0.40) - immediate_charge

SUMMARY OUTPUT:
  total_gifted, total_iht_saved, reduction_percentage,
  implementation_timeframe, strategies[]
```

### 8.3 PET Analysis (Active Gifts Tracking)

```
FOR EACH gift WHERE gift_type = 'pet' AND years_since_gift < 7:

  years_ago = NOW - gift_date (in years)
  years_remaining = 7 - years_ago
  taper_relief_applicable = years_ago >= 3

  STATUS:
    years_ago < 3:   "At risk - full 40% if death occurs"
    years_ago 3-4:   "Taper relief: 32% (reduced from 40%)"
    years_ago 4-5:   "Taper relief: 24%"
    years_ago 5-6:   "Taper relief: 16%"
    years_ago 6-7:   "Taper relief: 8%"
    years_ago >= 7:  "EXEMPT - falls outside 7-year window"
```

### 8.4 14-Year Rule (Extended Cumulation)

```
When calculating CLT tax on death:
  - Failed PETs in 7 years before a CLT affect the NRB available for that CLT
  - This can reach back up to 14 years before death:
    * PET made 10 years before death (becomes failed PET)
    * CLT made 5 years before death
    * The failed PET uses up NRB, increasing tax on CLT
  - applies_to: CLT with prior failed PET
  - look_back: 14 years from death
```

---

## 9. Phase 7: Trust Planning Engine

**Services:** `PersonalizedTrustStrategyService`, `TrustService`, `TrustValuationService`

### 9.1 Trust Type Selection Decision Tree

```
TRUST TYPE SELECTOR
===================

[Q1] What is the primary objective?
  |
  +-- "Remove assets from estate" --> [Q2]
  +-- "Provide income to beneficiary for life" --> Interest in Possession Trust
  +-- "Protect assets from creditors/divorce" --> Discretionary Trust
  +-- "Provide for disabled person" --> Disabled Person's Trust
  +-- "Provide for bereaved minor child" --> Bereaved Minor's Trust
  +-- "Retain access to capital" --> [Q3]
  +-- "Cover IHT with life insurance" --> Life Insurance Trust
  +-- "Business succession planning" --> Business Property Trust
  +-- "Education funding for children" --> Bare Trust

[Q2] How much are you transferring vs NRB?
  |
  +-- <= NRB (£325,000): Discretionary Trust
  |   No immediate charge, 7-year CLT clock starts
  |
  +-- > NRB: Consider phased approach over 7-year cycles
      OR: Accept 20% immediate charge on excess

[Q3] Do you want income or just capital access?
  |
  +-- Income from assets: Discounted Gift Trust
  |   (Retained income rights reduce CLT value)
  |
  +-- Capital access: Loan Trust
      (Loan stays in estate, but growth is IHT-free)
```

### 9.2 Trust IHT Treatment Summary

| Trust Type | IHT on Creation | IHT in Estate | Periodic Charges | Exit Charges |
|------------|----------------|---------------|------------------|-------------|
| Bare/Absolute | PET (to individual) | In beneficiary's estate | None | None |
| Interest in Possession (pre-2006) | N/A | In life tenant's estate | None | None |
| Interest in Possession (post-2006) | CLT | Outside settlor's estate | Yes (relevant property) | Yes |
| Discretionary | CLT | Outside settlor's estate | Yes (max 6% per 10 years) | Yes |
| Accumulation & Maintenance | CLT | Outside settlor's estate | Yes (relevant property) | Yes |
| Disabled Person's | CLT but special rules | In disabled person's estate | **Exempt** | **Exempt** |
| Bereaved Minor's | N/A (testamentary) | Outside estate until 18 | **Exempt** until 18 | **Exempt** until 18 |
| 18-25 Trust | N/A (testamentary) | Outside estate until 25 | Reduced charges | Reduced charges |
| Loan Trust | Not a gift (it's a loan) | Loan amount in estate | On growth only | On growth only |
| Discounted Gift Trust | CLT (discounted value) | Discount in estate | On gift portion | On gift portion |
| Life Insurance Trust | Not a gift (premiums exempt) | Outside estate | None | None |

### 9.3 CLT Tax Calculation

```
CHARGEABLE LIFETIME TRANSFER (CLT) TAX
========================================

Step 1: Cumulate CLTs in rolling 7-year window
  total_clts_7_years = SUM of all CLTs in previous 7 years

Step 2: Calculate available NRB
  nrb_available = £325,000 - total_clts_7_years

Step 3: Calculate immediate charge
  IF transfer_amount <= nrb_available:
    immediate_charge = £0
  ELSE:
    excess = transfer_amount - nrb_available
    IF trust pays tax:
      charge = excess * 0.20 (20%)
    IF settlor pays tax:
      charge = excess * 0.25 (25% grossing up)

Step 4: If death within 7 years
  additional_charge = excess * 0.40 - charge_already_paid
  Apply taper relief if death 3-7 years after CLT
  Credit for lifetime tax already paid
```

### 9.4 Periodic (10-Year) Charge Calculation

```
10-YEAR PERIODIC CHARGE
========================

Applies to: Relevant property trusts (discretionary, A&M, post-2006 IIP)
When: Every 10th anniversary of trust creation

Step 1: Value trust assets at 10-year anniversary

Step 2: Effective rate calculation
  hypothetical_rate = 20% (lifetime rate)
  proportion = value_above_NRB / total_trust_value
  effective_rate = hypothetical_rate * 30% * proportion
  Maximum effective rate: 6% (20% * 30%)

Step 3: Apply rate
  periodic_charge = trust_value * effective_rate

Step 4: Reduced charge for property added within 10 years
  proportionate_reduction for quarters not held

PRACTICAL MAXIMUM:
  On trust of £1,000,000 with full NRB available:
  Taxable portion = £1,000,000 - £325,000 = £675,000
  Rate = 20% * 30% * (675,000/1,000,000) = 4.05%
  Charge = £1,000,000 * 4.05% = £40,500
```

#### 9.4.1 NRB Avoidance Strategy — Forward Value Calculation

**CRITICAL:** To avoid the 10-year periodic charge entirely, the user must ensure the trust value stays BELOW the NRB (currently £325,000) at the 10-year anniversary. If the trust is below the NRB, the periodic charge is £0.

```
NRB AVOIDANCE FORWARD PROJECTION
==================================

Goal: Determine the MAXIMUM initial settlement that will remain
      below £325,000 after 10 years of growth.

Step 1: Get user's growth rate from their RISK PROFILE
        growth_rate = user.risk_profile.expected_return
        (NEVER use a default — always use the user's actual risk level)
        (Risk level maps to growth rate via TaxConfigService)

Step 2: Calculate maximum initial settlement
        Target: trust_value at Year 10 < NRB (£325,000)
        max_initial = NRB / (1 + growth_rate)^10

        Examples (NRB = £325,000):
        | Risk Level | Growth Rate | Max Initial Settlement |
        |------------|-------------|------------------------|
        | Low        | 3.0%        | £241,805               |
        | Low-Medium | 4.0%        | £219,527               |
        | Medium     | 5.0%        | £199,571               |
        | Medium-High| 6.0%        | £181,529               |
        | High       | 7.0%        | £165,251               |

Step 3: Compare to user's planned settlement
        |
        +-- planned_amount <= max_initial?
        |   YES → "Your trust settlement of {amount} is projected to
        |   remain below the Nil Rate Band of {nrb} at the 10-year
        |   anniversary, avoiding the periodic charge entirely."
        |
        +-- planned_amount > max_initial?
            YES → "At your risk profile growth rate of {rate}%, a trust
            settlement of {amount} is projected to grow to {projected_value}
            by the 10-year anniversary, exceeding the Nil Rate Band of
            {nrb}. Consider settling no more than {max_initial} to avoid
            the periodic charge, or plan for a charge of approximately
            {estimated_charge}."

Step 4: Project forward annually (years 1-11) to show trajectory
        Display table of year-by-year projected trust value vs NRB
        Flag the year where trust crosses NRB threshold (if any)
```

### 9.5 Exit Charge Calculation

```
EXIT CHARGE
============

Applies when: Assets leave a relevant property trust between 10-year anniversaries

Calculation:
  Proportionate to time since last periodic charge (or trust creation)
  Based on rate at last 10-year charge (or hypothetical first charge)
  Expressed in complete quarters out of 40 (10 years = 40 quarters)

exit_charge = last_periodic_rate * (quarters_since_last_charge / 40)
```

### 9.6 Five Trust Strategies (PersonalizedTrustStrategyService)

```
STRATEGY 1: Immediate Discretionary Trust (CLT)
  Amount: All liquid assets
  Tax: 20% on excess over NRB (or £0 if within NRB)
  Timeline: 7 years for full effectiveness
  Risk: Medium (if over NRB) / Low (if within NRB)
  Best for: Users with liquid assets within NRB

STRATEGY 2: Multi-Cycle CLT (7-year cycles)
  Amount: NRB per cycle (£325,000 every 7 years)
  Tax: £0 per cycle (stays within NRB)
  Timeline: Multiple 7-year cycles
  Risk: Medium (requires longevity)
  Best for: Large estates, long time horizon

STRATEGY 3: Loan Trust
  Amount: Liquid assets
  Tax: £0 immediate (it's a loan, not a gift)
  Estate impact: Loan stays in estate; GROWTH is IHT-free
  Timeline: Immediate effect on growth
  Risk: Low
  Best for: Those wanting to retain capital access

STRATEGY 4: Discounted Gift Trust
  Amount: Liquid assets (income-producing)
  Tax: CLT on discounted value (reduced by retained income rights)
  Estate impact: Discount value stays in estate
  Timeline: 7 years for CLT value
  Risk: Medium
  Best for: Those wanting income but willing to give up capital

STRATEGY 5: Property Trust Planning
  Options:
    A) Downsizing: Release equity, gift using PET/CLT strategies
    B) Life Interest Trust in Will: Spouse lives in property for life,
       then passes to children on second death
    C) Shared Ownership: Gift percentage to children
       (they MUST pay market rent to avoid GROB)
  WARNING: Cannot gift home and continue living rent-free (GROB)
```

---

## 10. Phase 8: Will & Intestacy Analysis

**Services:** `WillAnalysisService`, `IntestacyCalculator`

### 10.1 Will Status Decision Tree

```
WILL ANALYSIS
=============

[Check 1] Does user have a will?
  |
  +-- NO --> HIGH priority: "You do not have a will. Without one,
  |          your estate will be distributed under intestacy rules."
  |          --> Run IntestacyCalculator to show what would happen
  |
  +-- YES
       |
       v
[Check 2] Is will up to date?
  |
  +-- last_reviewed_date > 3 years ago (or never reviewed)?
  |   |
  |   +-- YES --> MEDIUM priority: "Your will has not been reviewed
  |   |           recently. Review every 3-5 years or after
  |   |           significant life events."
  |   |
  |   +-- NO --> Will is current
  |
  v
[Check 3] Does will include IHT-efficient provisions?
  |
  +-- spouse_primary_beneficiary? (For married users)
  |   |
  |   +-- YES --> Spouse exemption utilised on first death
  |   |           But consider: Does this waste NRB?
  |   |           Better: NRB discretionary trust + rest to spouse
  |   |
  |   +-- NO --> Consider spouse exemption for first death
  |
  +-- Charitable bequests?
  |   --> Analyse against 10% baseline (see Phase 13)
  |
  +-- Trust-triggering wishes detected?
  |   --> Scan bequest notes for trigger patterns (see 10.3)
  |
  +-- Guardianship provisions? (if minor children)
  |   |
  |   +-- NO --> "Consider appointing guardians for minor children"
  |   |
  |   +-- YES --> Noted
  |
  +-- Executors appointed?
  |   |
  |   +-- NO --> "Consider appointing executors and trustees"
  |   |
  |   +-- YES --> Noted
  |
  +-- RNRB qualification?
      +-- Does will leave property to direct descendants?
      |   +-- YES --> RNRB available
      |   +-- NO --> "Consider updating will to qualify for RNRB"
```

### 10.2 Intestacy Rules (UK - England & Wales)

```
INTESTACY DISTRIBUTION TREE
============================

[Q1] Is the deceased married/civil partnered?
  |
  +-- YES
  |   |
  |   +-- [Q2] Are there children?
  |       |
  |       +-- NO --> Spouse gets ENTIRE estate
  |       |
  |       +-- YES --> [Q3] Is estate > £322,000?
  |           |
  |           +-- NO --> Spouse gets ENTIRE estate
  |           |
  |           +-- YES --> Spouse gets:
  |                       - First £322,000
  |                       - Personal possessions
  |                       - Half of remainder
  |                       Children get:
  |                       - Other half of remainder (shared equally)
  |                       - Held in trust until age 18
  |
  +-- NO (not married)
      |
      +-- [Q4] Are there children? YES --> Children share equally
      |
      +-- NO --> [Q5] Living parents? YES --> Parents share equally
      |
      +-- NO --> [Q6] Siblings (whole blood)? YES --> Siblings share equally
      |
      +-- NO --> [Q7] Half-siblings? YES --> Half-siblings share equally
      |
      +-- NO --> [Q8] Grandparents? YES --> Grandparents share equally
      |
      +-- NO --> [Q9] Aunts/uncles (whole blood)? YES --> They share equally
      |
      +-- NO --> [Q10] Aunts/uncles (half blood)? YES --> They share equally
      |
      +-- NO --> Estate passes to THE CROWN (bona vacantia)
```

**Key intestacy points:**
- Unmarried partners get NOTHING under intestacy
- Cohabiting partners must rely on Inheritance (Provision for Family and Dependants) Act 1975 claim
- Children includes adopted children and children born outside marriage
- "Children" means direct children only, not grandchildren (unless their parent predeceased)
- Assets passing under intestacy still subject to IHT

### 10.3 Trust-Triggering Wish Detection

The WillAnalysisService scans bequest notes and executor instructions for patterns suggesting trust structures should be recommended:

| Wish Pattern | Trust Type Recommended | IHT Treatment |
|-------------|----------------------|---------------|
| "education", "school fees", "university" | Bare Trust (Education) | PET, not CLT |
| "income for family/spouse" | Interest in Possession Trust | Pre-2006: not relevant property |
| "income for children/maintenance" | Discretionary Trust | Relevant property (10-year charges) |
| "at age 25", "when they reach" | 18-25 Trust | Reduced exit charges |
| "protect from divorce", "creditor protection" | Asset Protection (Discretionary) Trust | Relevant property |
| "special needs", "disability", "disabled" | Disabled Person's Trust | **Exempt** from periodic/exit charges |
| "business succession", "company shares" | Business Property Trust | May qualify for BPR |
| "property managed", "rental income" | Property Trust | Relevant property |

### 10.4 Testamentary Trust Options

Trusts created by will (on death) have different IHT treatment from lifetime trusts:

```
TESTAMENTARY TRUST DECISION TREE
==================================

[A] NRB Discretionary Trust (Most common for married couples)
    - Will leaves up to NRB value into discretionary trust
    - Rest of estate passes to spouse (spouse exemption)
    - Uses deceased's NRB immediately (no wasted NRB)
    - Trustees can benefit spouse AND children
    - On second death: trust assets OUTSIDE surviving spouse's estate
    - IHT Benefit: Fully uses both NRBs (no reliance on transferable NRB)

[B] Interest in Possession Trust in Will
    - Surviving spouse has right to income (or use of property) for life
    - On second death: trust assets pass to children
    - IHT: Treated as part of life tenant's estate (special rules apply)

[C] Bereaved Minor's Trust
    - For children under 18 of deceased
    - Must become absolutely entitled at 18
    - NO periodic charges, NO exit charges before 18
    - Treated as if child absolutely entitled for IHT

[D] 18-25 Trust
    - Must become absolutely entitled between 18 and 25
    - Reduced exit charges (proportionate to time over 18)
    - No periodic charges before 25

[E] Immediate Post-Death Interest (IPDI)
    - Interest in possession created by will
    - Treated as part of beneficiary's estate
    - Commonly used for spouse to have income
```

### 10.5 Deed of Variation

```
DEED OF VARIATION
=================

What: A legal document that changes the distribution of a deceased's estate
When: Must be made within 2 YEARS of death
Who: All beneficiaries who lose out must consent
Effect: Treated as if the deceased made the new arrangements

IHT Uses:
  1. Redirect assets into NRB discretionary trust (if will didn't)
  2. Redirect to charity to get 36% rate
  3. Redirect to direct descendants to claim RNRB
  4. Skip a generation (give to grandchildren instead of children)

Conditions:
  - Must be in writing
  - Must refer to the relevant sections of IHTA 1984
  - Must be signed by all affected beneficiaries
  - Copy must be sent to HMRC within 6 months of variation
  - Cannot be made for monetary consideration (not a "sale")
```

---

## 11. Phase 9: Life Insurance Strategy

**Services:** `LifePolicyStrategyService`, `LifeCoverCalculator`

### 11.1 Life Insurance Decision Tree

```
LIFE INSURANCE FOR IHT
========================

[Q1] Is there an IHT liability?
  |
  +-- NO --> No life insurance needed for IHT purposes
  |
  +-- YES
       |
       v
[Q2] Is existing life cover (in trust) sufficient?
  |
  +-- existing_cover_in_trust >= iht_liability?
  |   |
  |   +-- YES --> "Existing cover sufficient. Ensure policies
  |   |            remain in trust and review annually."
  |   |
  |   +-- NO --> cover_gap = iht_liability - existing_cover_in_trust
  |              Continue to Q3
  |
  v
[Q3] Are there policies NOT in trust?
  |
  +-- YES --> "Place {count} policies in trust to bypass estate"
  |           (This alone may close the gap)
  |
  v
[Q4] Is the user married?
  |
  +-- YES --> Recommend: JOINT LIFE SECOND DEATH policy
  |           (Cheaper than two single policies; pays on second death
  |            which is when IHT becomes due)
  |
  +-- NO --> Recommend: SINGLE LIFE WHOLE OF LIFE policy
  |
  v
[Q5] Compare three scenarios:

  SCENARIO A: Full Life Cover
    cover_amount = iht_liability
    premium = estimated based on age/gender/health
    cost_benefit = cover_amount / total_premiums_paid

  SCENARIO B: Life Cover Less Gifting
    cover_amount = iht_after_gifting_strategy
    premium = lower (smaller cover amount)
    RECOMMENDED: Most cost-effective combination

  SCENARIO C: Self-Insurance (Invest Premiums)
    annual_investment = equivalent premium amount
    projected_fund = FV of annuity at 4.7% return
    coverage_percentage = projected_fund / target
    |
    +-- >= 110% --> "Self-insurance viable"
    +-- 90-110% --> "Consider hybrid approach"
    +-- < 90%  --> "Self-insurance risky - consider insurance"

[Q6] RECOMMENDATION
  |
  +-- Self-insurance coverage >= 110% AND user comfortable with risk
  |   --> "Self-Insurance" (invest premiums instead)
  |
  +-- Insurance cost_benefit >= 1.5 AND user wants certainty
  |   --> "Whole of Life Insurance"
  |
  +-- Otherwise
      --> "Hybrid Approach" (50-60% insurance + invest remainder)
```

### 11.2 Existing Policy Assessment

Before recommending new cover, check the user's existing life insurance:

```
EXISTING POLICY CHECK
======================

+-- Does user have existing whole of life insurance?
|   |
|   +-- YES → Check:
|   |   |
|   |   +-- Is it JOINT LIFE (second death)?
|   |   |   YES → Correct type for IHT planning (pays on second death)
|   |   |   NO  → WARN: "Your whole of life policy is single life. For
|   |   |          married couples, a joint life second death policy is more
|   |   |          cost-effective for IHT cover, as IHT is typically due
|   |   |          on the second death."
|   |   |
|   |   +-- Is it WHOLE OF LIFE (not term)?
|   |   |   YES → Correct type for IHT planning
|   |   |   NO  → WARN: "Your term life policy expires on {end_date}.
|   |   |          IHT cover requires whole of life insurance to ensure
|   |   |          cover is in place whenever death occurs."
|   |   |
|   |   +-- Is it IN TRUST?
|   |       YES → Correct — proceeds bypass estate
|   |       NO  → WARN: "Your whole of life policy is not in trust.
|   |              Proceeds will form part of your estate and may be
|   |              subject to IHT. Consider placing it into trust."
|   |
|   +-- NO → Proceed to premium estimation for new cover
```

### 11.3 Premium Estimation

**All premium estimation rates MUST be stored in and served from `TaxConfigService`** — never hardcoded in services. This allows rates to be updated centrally when market conditions change.

Premium rates accessed via: `TaxConfigService::get('insurance_premium_estimates')`

```
PREMIUM TABLE (per £1,000 cover per month, non-smoker, good health)
===================================================================
Source: TaxConfigService::get('insurance_premium_estimates')

| Age | Male  | Female |
|-----|-------|--------|
| 30  | £0.95 | £0.80  |
| 35  | £1.10 | £0.95  |
| 40  | £1.40 | £1.20  |
| 45  | £1.85 | £1.55  |
| 50  | £2.55 | £2.10  |
| 55  | £3.60 | £2.95  |
| 60  | £5.20 | £4.15  |
| 65  | £7.80 | £6.20  |
| 70  | £12.50| £9.80  |
| 75  | £19.50| £15.20 |
| 80  | £31.00| £24.00 |

Joint Life Second Death: ~75% of average of two single rates

Monthly premium = (cover_amount / 1,000) * rate_per_thousand * age_factor
Annual premium = monthly * 12
Total cost = annual * years_to_death
Cost-benefit ratio = cover_amount / total_cost
```

### 11.3 Self-Insurance Calculation

```
SELF-INSURANCE (invest premiums instead)
=========================================

Future Value of Annuity: FV = PMT * [(1 + r)^n - 1] / r

Where:
  PMT = annual premium equivalent
  r = assumed return rate (4.7% default, or user's custom rate)
  n = years until expected death

Confidence Levels:
  coverage >= 120%: "Very High"
  coverage >= 110%: "High"
  coverage >= 100%: "Medium-High"
  coverage >= 90%:  "Medium"
  coverage >= 75%:  "Medium-Low"
  coverage < 75%:   "Low"
```

---

## 12. Phase 10: Pension Death Benefits Optimisation

### 12.1 Pension Death Benefits Decision Tree

```
PENSION DEATH BENEFITS
=======================

[KEY RULE] Pensions are OUTSIDE the estate for IHT
           (if beneficiaries are nominated via expression of wish)

[Q1] What type of pension?
  |
  +-- Defined Contribution (DC):
  |   |
  |   +-- Death BEFORE 75:
  |   |   Beneficiaries can receive tax-free:
  |   |   - Lump sum (tax-free)
  |   |   - Drawdown income (tax-free)
  |   |   - Annuity purchase (tax-free)
  |   |   MUST be claimed within 2 years of death
  |   |
  |   +-- Death AFTER 75:
  |       Beneficiaries pay INCOME TAX at their marginal rate:
  |       - Drawdown income (taxed as income)
  |       - Lump sum (taxed as income - can push into higher band)
  |       - Annuity purchase (taxed as income)
  |       STRATEGY: Drawdown usually better than lump sum (spread tax)
  |
  +-- Defined Benefit (DB):
      |
      +-- Death before retirement:
      |   - Lump sum death in service benefit (typically 3-4x salary)
      |   - May be in trust (scheme-dependent)
      |
      +-- Death after retirement:
          - Dependant's pension (typically 50% of member's pension)
          - Some schemes offer guaranteed period (e.g., 5-10 years)
          - No transfer value in estate

[Q2] Are expression of wish / nomination forms up to date?
  |
  +-- NO --> HIGH priority: "Update pension nomination forms"
  |          Without nomination, trustees may include in estate
  |
  +-- YES --> Check nominees match current wishes

[Q3] Estate planning pension optimisation:
  |
  +-- Pension is LAST asset to spend (most IHT-efficient)
  |   - Spend ISAs, savings, investments first
  |   - Let pension grow IHT-free
  |
  +-- Drawdown vs Annuity:
  |   - Drawdown: Remaining fund passes to beneficiaries (IHT-free)
  |   - Annuity: Income dies with annuitant (unless guaranteed period/spouse pension)
  |   - FOR ESTATE PLANNING: Drawdown is superior
  |
  +-- Lump Sum and Death Benefit Allowance (LSDBA):
      - £1,073,100 (formerly Lifetime Allowance)
      - Tax-free lump sum death benefits limited to this amount
      - Excess taxed at beneficiary's marginal rate
```

### 12.2 Pension Spending Order for Estate Planning

```
OPTIMAL SPENDING ORDER (estate planning perspective)
=====================================================

SPEND FIRST (removes from taxable estate):
  1. Cash/Savings (fully taxable on death)
  2. Investment accounts (fully taxable, including ISAs)
  3. Non-pension assets generally

SPEND LAST (IHT-efficient):
  4. Pensions (currently outside estate, pass to beneficiaries)

NEVER SPEND (if possible):
  5. Business Relief qualifying assets (100% IHT relief)
  6. Agricultural Relief qualifying property (100% IHT relief)

SPECIAL CONSIDERATION:
  - Property: RNRB benefit requires ownership at death
    (don't sell main residence if it qualifies for RNRB)
  - Life insurance in trust: Already outside estate
```

### 12.3 2027 Pension IHT Amendment — CRITICAL UPCOMING CHANGE

**From April 2027, unused pension funds will be included in the estate for IHT purposes.**

This is a fundamental change to UK estate planning. Currently, pensions are outside the estate — from April 2027, they will NOT be.

```
CURRENT RULES (until April 2027):
  - DC pension pots are OUTSIDE the estate for IHT
  - Death before 75: beneficiaries receive tax-free
  - Death after 75: beneficiaries pay income tax at their marginal rate
  - Pensions are the LAST asset to spend (most IHT-efficient)

NEW RULES (from April 2027):
  - Unused DC pension pots will be INCLUDED in the taxable estate
  - Pensions will be subject to IHT at 40% (or 36% with charitable rate)
  - This fundamentally changes the spending order strategy
  - The "spend pensions last" advice becomes less clear-cut

IMPACT ON USER RECOMMENDATIONS:
  |
  +-- User's projected death is BEFORE April 2027?
  |   → Current rules apply. Pensions remain outside estate.
  |
  +-- User's projected death is AFTER April 2027?
      → WARN: "From April 2027, unused pension funds will be included
         in your estate for Inheritance Tax purposes. This may increase
         your projected IHT liability by up to {pension_value * 0.40}.
         Consider reviewing your decumulation strategy."
      |
      +-- Recalculate projected IHT including pension values
      +-- Show BOTH scenarios:
          a) Current rules projection (pensions excluded)
          b) Post-2027 rules projection (pensions included)
      +-- Highlight the difference: "The 2027 pension amendment could
          increase your IHT liability by {difference}."
      |
      +-- Strategy adjustments to consider:
          - Drawing down more pension before death (to reduce pot)
          - Using pension to fund lifetime gifting (PETs)
          - Purchasing an annuity (income dies with annuitant)
          - Increasing charitable bequests (36% rate may offset)
          - Reviewing the spending order (pensions no longer "spend last")
```

**NOTE:** This amendment was announced in the Autumn Budget 2024. Users should be made aware regardless of when they access estate planning. All projections for death dates after April 2027 must include both current and post-amendment scenarios.

---

## 13. Phase 11: Powers of Attorney Assessment

### 13.1 LPA Decision Tree

```
POWERS OF ATTORNEY
===================

[TYPE 1] LASTING POWER OF ATTORNEY - PROPERTY & FINANCIAL AFFAIRS
  |
  +-- Allows attorneys to manage: bank accounts, investments,
  |   property, bills, tax affairs
  |
  +-- Can be used: while donor has capacity (if specified) OR
  |   only when donor loses capacity
  |
  +-- [Check] Does user have one?
      |
      +-- NO --> HIGH priority: "Consider creating a Property &
      |          Financial Affairs LPA to protect your finances
      |          if you lose mental capacity"
      |
      +-- YES --> [Check] Is it registered with OPG?
          |
          +-- NO --> "Your LPA must be registered with the Office of
          |          the Public Guardian before it can be used"
          |
          +-- YES --> [Check] Are named attorneys still appropriate?
              |
              +-- Review: attorney names, replacement attorneys,
                  any restrictions or conditions

[TYPE 2] LASTING POWER OF ATTORNEY - HEALTH & WELFARE
  |
  +-- Allows attorneys to manage: medical treatment, care home
  |   decisions, daily routine, life-sustaining treatment
  |
  +-- Can ONLY be used: when donor lacks capacity
  |
  +-- [Check] Does user have one?
      |
      +-- NO --> MEDIUM priority: "Consider creating a Health &
      |          Welfare LPA"
      |
      +-- YES --> Same registration and review checks as above

COST:
  - £82 per LPA to register with OPG (2025/26)
  - Fee exemptions for low income
  - Solicitor fees for drafting: typically £300-£600 per LPA
```

### 13.2 Why LPAs Matter for Estate Planning

```
WITHOUT LPAs:
  - If user loses mental capacity, family must apply to Court of Protection
  - Court of Protection deputy costs: £1,000+ per year
  - Delays: Can take months to get authority
  - Court may appoint someone the user wouldn't have chosen
  - Cannot implement IHT planning (gifting, trusts) without Court approval
  - Court of Protection can refuse to authorise IHT planning

WITH LPAs:
  - Attorneys can act immediately when needed
  - Can continue implementing gifting strategies
  - Can manage investments and property
  - Can make health decisions aligned with user's wishes
  - Much lower cost than Court of Protection
```

---

## 14. Phase 12: Business & Agricultural Relief

### 14.1 Business Relief (BR) Decision Tree

```
BUSINESS RELIEF QUALIFICATION
===============================

[Q1] What type of business interest?
  |
  +-- Trading business (sole trader / partnership interest)
  |   --> Potential 100% BR
  |
  +-- Unquoted shares in trading company
  |   --> Potential 100% BR
  |
  +-- Quoted shares with controlling interest (>50%)
  |   --> Potential 50% BR
  |
  +-- Land/buildings/machinery used by partnership/controlled company
  |   --> Potential 50% BR
  |
  +-- Investment company (holding investments, property lettings)
  |   --> NO BR available
  |
  +-- Mixed trading/investment company
      --> Must be MAINLY trading (>50% trading activities)
      --> Excepted assets reduce relief

[Q2] Has the asset been owned for at least 2 years?
  |
  +-- NO --> BR NOT available yet
  |          "Hold for {months_remaining} more months to qualify"
  |
  +-- YES --> Continue to Q3

[Q3] Is the business actively trading?
  |
  +-- NO (investment company, dormant, etc.) --> NO BR
  |
  +-- YES --> Continue to Q4

[Q4] Are there excepted assets?
  |
  +-- Excepted assets = assets not needed for the business
  |   (e.g., surplus cash, investment property held by trading company)
  |
  +-- excepted_asset_value = identified surplus assets
  |   BR only applies to: business_value - excepted_asset_value
  |
  +-- "Business value of {total} includes {excepted} in excepted
       assets that do not qualify for BR"

BR RELIEF RATES:
  100% relief: Trading business, partnership, unquoted shares
  50% relief:  Controlling quoted shares, business-used land/buildings

IHT CALCULATION WITH BR:
  business_value_for_iht = business_value * (1 - br_rate)
  e.g., £500,000 business with 100% BR = £0 for IHT
```

### 14.2 Agricultural Relief (AR) Decision Tree

```
AGRICULTURAL RELIEF QUALIFICATION
===================================

[Q1] Is the property agricultural?
  |
  +-- Must pass "character appropriate" test:
  |   - Land used for agriculture (crops, livestock, etc.)
  |   - Agricultural buildings (farmhouse, barns, etc.)
  |   - Farmhouse must be of character appropriate to the farming
  |
  +-- NOT agricultural: NO AR

[Q2] What is the tenure?
  |
  +-- Owner-occupied farmland
  |   --> 100% AR (if owned and occupied for 2+ years)
  |
  +-- Tenanted AFTER 1 September 1995
  |   --> 100% AR (if owned for 7+ years)
  |
  +-- Tenanted BEFORE 1 September 1995
      --> 50% AR (if owned for 7+ years)

[Q3] Minimum holding period met?
  |
  +-- Owner-occupied: 2 years ownership AND occupation
  |
  +-- Let to tenant: 7 years ownership
  |
  +-- Replacement property: Previous ownership period counts
  |   (if replaced within specified time)

[Q4] Agricultural value vs market value?
  |
  +-- AR applies to AGRICULTURAL VALUE only
  |   (not development or hope value)
  |   e.g., Farmland worth £500k but agricultural value £300k
  |   AR applies to £300k only; £200k subject to normal IHT

IMPORTANT CHANGES (2024 Autumn Budget):
  From April 2026:
  - Combined BR + AR relief capped at £1m per person
  - Above £1m: 50% relief (effective 20% IHT rate)
  - This is a SIGNIFICANT change for large farming estates
```

### 14.3 AIM Shares as BR-Qualifying Investments

```
AIM SHARES FOR IHT PLANNING
=============================

Strategy: Invest in AIM-listed shares that qualify for Business Relief

Qualification:
  - Must be shares in trading companies listed on AIM
  - NOT all AIM shares qualify (investment companies don't)
  - Must be held for 2+ years at death

Benefits:
  - 100% BR = 100% IHT relief
  - Liquid investment (can buy/sell on stock market)
  - Potential for growth

Risks:
  - AIM shares are HIGHER RISK than main market
  - Companies may lose trading status (losing BR)
  - 2-year holding period required
  - Not suitable for low-risk investors
  - Should not replace diversified portfolio

Implementation:
  - BR-qualifying AIM funds/portfolios available from IFAs
  - Typically higher charges than standard funds
  - Should form part of overall investment strategy, not replace it
```

---

## 15. Phase 13: Charitable Giving Optimisation

**Service:** `WillAnalysisService.analyzeCharitableBequests()`

### 15.1 Charitable 36% Rate Decision Tree

```
CHARITABLE GIVING IHT OPTIMISATION
====================================

Step 1: Calculate BASELINE
  baseline = net_estate - NRB
  (RNRB is EXCLUDED from baseline per HMRC rules)

Step 2: Calculate 10% threshold
  threshold = baseline * 0.10

Step 3: Sum charitable bequests
  total_charitable = SUM of:
    - Percentage bequests to charities (percentage * net_estate)
    - Specific amount bequests to charities

Step 4: Compare
  |
  +-- total_charitable >= threshold?
  |   |
  |   +-- YES --> STATUS: 'at' or 'above'
  |   |           IHT rate: 36% (not 40%)
  |   |           Current saving: baseline * 0.04
  |   |           Message: "Your charitable bequests qualify for
  |   |                     the reduced 36% IHT rate"
  |   |
  |   +-- NO --> STATUS: 'below'
  |              Shortfall: threshold - total_charitable
  |              Potential saving: baseline * 0.04
  |              |
  |              +-- Is shortfall < potential_saving?
  |                  (i.e., does giving more to charity net-save money?)
  |                  |
  |                  +-- YES --> "Give {shortfall} more to charity
  |                               to save {saving} net"
  |                               NET BENEFIT = saving - shortfall
  |                  |
  |                  +-- NO --> "Charitable increase not cost-effective"

WORKED EXAMPLE:
  Net estate: £1,000,000
  NRB: £325,000
  Baseline: £675,000
  Threshold (10%): £67,500
  Current charitable: £20,000

  Shortfall: £47,500 (need to give this much more)
  Potential saving: £675,000 * 0.04 = £27,000

  £47,500 > £27,000 --> NOT COST-EFFECTIVE
  (You'd give away £47,500 to save £27,000)

DIFFERENT EXAMPLE:
  Net estate: £2,000,000
  NRB: £325,000
  Baseline: £1,675,000
  Threshold: £167,500
  Current charitable: £150,000

  Shortfall: £17,500
  Potential saving: £1,675,000 * 0.04 = £67,000

  £17,500 < £67,000 --> COST-EFFECTIVE
  (Give £17,500 more to save £67,000 = NET BENEFIT £49,500)
```

### 15.2 Charitable Bequest Detection

The system detects charitable bequests by:
1. `beneficiary_type = 'charity'` (explicit)
2. `charity_registration_number` present
3. Beneficiary name contains charity indicators: charity, charitable, foundation, cancer, heart, hospice, NSPCC, RSPCA, Oxfam, Red Cross, British Heart, Macmillan, Marie Curie, Shelter, Save the Children, UNICEF

---

## 16. Phase 14: Domicile & Cross-Border Issues

### 16.1 Domicile Decision Tree

```
DOMICILE STATUS FOR IHT
=========================

[Q1] Where is the user domiciled?
  |
  +-- UK Domiciled:
  |   ALL worldwide assets subject to UK IHT
  |   This is the DEFAULT assumption
  |
  +-- Non-UK Domiciled:
  |   ONLY UK-situated assets subject to UK IHT
  |   Foreign assets EXEMPT
  |   |
  |   +-- BUT check deemed domicile rules:
  |       |
  |       +-- Resident in UK for 15 out of 20 tax years?
  |       |   --> DEEMED UK domiciled
  |       |   --> ALL worldwide assets subject to IHT
  |       |
  |       +-- UK-born with UK domicile of origin who left
  |           and returned?
  |           --> Deemed domiciled after 1 year of return
  |
  +-- Formerly Domiciled Resident:
      Born in UK with UK domicile of origin, acquired
      foreign domicile, then returned to UK
      --> Deemed domiciled if resident for 1+ year

[Q2] Are there assets in multiple jurisdictions?
  |
  +-- YES --> Consider:
  |   - Double taxation treaties (UK has treaties with several countries)
  |   - Credit method vs exemption method
  |   - Need separate wills for each jurisdiction?
  |   - Foreign probate requirements
  |
  +-- NO --> Standard UK IHT applies

UK-SITUATED ASSETS (for non-domiciled):
  - UK real estate
  - UK bank accounts
  - UK shares and securities
  - UK-based businesses
  - UK government securities (EXEMPT for non-doms!)
  - UK life insurance policies

NON-UK ASSETS (exempt for non-domiciled):
  - Foreign property
  - Foreign bank accounts
  - Foreign investments
  - Excluded property trusts (set up while non-domiciled)
```

### 16.2 Excluded Property Trusts (Non-Domiciled)

```
EXCLUDED PROPERTY TRUST
=========================

If settlor was NON-UK domiciled when trust was created:
  - Non-UK assets in the trust are EXCLUDED PROPERTY
  - Not subject to IHT even if settlor later becomes deemed domiciled
  - Entry charges, periodic charges, and exit charges do NOT apply
  - This is a significant planning opportunity for non-doms
  - MUST be set up BEFORE becoming deemed domiciled (15/20 year test)
```

---

## 17. Phase 15: Life Event Impact Engine

### 17.1 Life Event Estate Planning Triggers

| Life Event | Estate Planning Impact | Actions Required |
|------------|----------------------|------------------|
| **Marriage** | Spouse exemption now available; old will may be revoked by marriage; RNRB eligibility changes | Update will; consider NRB trust in will; update LPAs; review beneficiary nominations |
| **Divorce** | Will provisions to former spouse may be revoked; spouse exemption lost; NRB transfer lost | New will essential; update all nominations; review all beneficiaries; recalculate IHT |
| **Death of spouse** | Claim transferable NRB; claim transferable RNRB; estate structure changes; may trigger IHT | Claim NRB/RNRB transfer; update own will; review IHT position; consider deed of variation |
| **Birth of child** | RNRB now available (direct descendants); guardianship needed; new beneficiary | Update will (guardianship, bequests); consider trusts for minors; RNRB claim |
| **Birth of grandchild** | RNRB eligibility confirmed; new gifting recipient | Update will; consider education trusts; gifting opportunities |
| **Property purchase** | Estate value increases; RNRB eligibility may change; mortgage deduction | Recalculate IHT; check RNRB status; update will if new property |
| **Property sale** | Estate value changes; RNRB may be lost (downsizing provisions apply); capital available for gifting | Check downsizing RNRB rules; consider gifting released equity; recalculate IHT |
| **Business start** | Potential BR after 2 years; estate value change | Monitor 2-year holding period; ensure trading status; update valuations |
| **Business sale** | BR lost; large capital sum enters estate; IHT exposure increases significantly | Urgent IHT review; consider gifting, trusts; reinvest in BR-qualifying assets? |
| **Inheritance received** | Estate value increases; potential double IHT; may push over RNRB taper | Recalculate IHT; consider disclaiming/deed of variation; gifting strategy |
| **Serious illness** | Urgency of planning increases; 7-year survival less likely; LPAs critical | Prioritise LPAs; accelerate gifting; ensure will is current; consider immediate CLTs |
| **Retirement** | Income changes; pension spending order matters; more time for gifting | Review pension death benefits; optimise spending order; implement gifting strategy |
| **Reaching age 75** | Pension death benefit tax treatment changes (becomes taxable) | Review pension nomination; consider drawdown strategy; update estate plan |
| **Reaching State Pension Age** | Additional income; surplus for gifting from income | Assess gifting from income opportunity; update cash flow projections |

### 17.2 Life Event Priority Matrix

| Event | IHT Review Urgency | Will Update | LPA Review | Nomination Update |
|-------|-------------------|-------------|------------|-------------------|
| Marriage | HIGH | **ESSENTIAL** (may be revoked) | HIGH | HIGH |
| Divorce | HIGH | **ESSENTIAL** | HIGH | HIGH |
| Death of spouse | HIGH | HIGH | MEDIUM | HIGH |
| Birth of child | MEDIUM | HIGH (guardianship) | LOW | MEDIUM |
| Serious illness | **CRITICAL** | **ESSENTIAL** | **ESSENTIAL** | HIGH |
| Business sale | HIGH | MEDIUM | LOW | LOW |
| Large inheritance | HIGH | MEDIUM | LOW | LOW |
| Property purchase | MEDIUM | LOW | LOW | LOW |
| Retirement | MEDIUM | LOW | LOW | MEDIUM |

---

## 18. Phase 16: Estate Adequacy Assessment

### 18.1 Completeness Scoring

```
ESTATE PLANNING ADEQUACY CHECKLIST
====================================

Category 1: DOCUMENTATION (Weight: 30%)
  [ ] Will exists and is current (< 3 years old)
  [ ] Will includes IHT-efficient provisions (NRB trust, charitable bequests)
  [ ] Executors and trustees appointed
  [ ] Guardianship provisions (if minor children)
  [ ] Letter of wishes in place
  [ ] Digital assets provision in will

Category 2: LEGAL PROTECTION (Weight: 20%)
  [ ] Property & Financial Affairs LPA in place and registered
  [ ] Health & Welfare LPA in place and registered
  [ ] Appropriate attorneys named
  [ ] Replacement attorneys named

Category 3: IHT MITIGATION (Weight: 25%)
  [ ] IHT liability quantified
  [ ] Gifting strategy active (annual exemptions being used)
  [ ] Life insurance in trust adequate for IHT
  [ ] Pension nominations up to date
  [ ] Trust structures in place (if beneficial)
  [ ] RNRB optimised (property to direct descendants)
  [ ] Charitable giving optimised (36% rate check)

Category 4: BENEFICIARY MANAGEMENT (Weight: 15%)
  [ ] Pension nomination forms current
  [ ] Life insurance beneficiary nominations current
  [ ] Trust beneficiaries documented
  [ ] Expression of wish forms completed

Category 5: ONGOING MONITORING (Weight: 10%)
  [ ] Estate trajectory monitored (growing/shrinking)
  [ ] Will reviewed within last 3 years
  [ ] IHT calculation updated within last year
  [ ] Life events reflected in plan
```

### 18.2 Gap Identification

```
FOR EACH unchecked item in adequacy assessment:
  |
  +-- Determine priority:
  |   HIGH:   No will, no LPAs, no IHT quantification, serious illness
  |   MEDIUM: Stale will, missing nominations, no gifting strategy
  |   LOW:    Missing letter of wishes, digital assets, monitoring gaps
  |
  +-- Generate action item with:
      - Title (what to do)
      - Description (why it matters)
      - Specific steps (how to do it)
      - Estimated cost (if applicable)
      - Professional help needed? (solicitor, IFA, accountant)
```

---

## 19. Phase 17: Projected Estate Trajectory

### 19.1 Asset-Specific Growth Models

```
ESTATE PROJECTION ENGINE
==========================

[CASH / SAVINGS]
  Method: Income/expense surplus accumulation
  Formula: cash_at_death = current_cash + (annual_surplus * years_to_death)
  Where:  annual_surplus = total_annual_income - total_annual_expenditure
  Notes:  Can go negative if spending exceeds income (drawdown phase)
          Adjusts for state pension income from state pension age
          Pre-retirement: employment + other income
          Post-retirement: pension income + state pension

[INVESTMENTS]
  Method: Monte Carlo simulation (80% confidence) OR custom rate
  Monte Carlo: Uses InvestmentProjectionService with risk profile
  Custom: current_value * (1 + custom_rate/100)^years
  Default rate: From AssumptionsService (typically 4-6%)
  Notes: Tax drag may apply; growth within ISA/pension is tax-free

[PROPERTIES]
  Method: Compound growth at configurable rate
  Default: 3% p.a. (configurable)
  Formula: property_at_death = current_value * (1 + 0.03)^years
  Notes: UK long-term average ~3-4% p.a.
         Regional variation not modelled

[LIABILITIES]
  Method: Amortisation to end date
  If end_date < death_date: liability = £0 (fully repaid)
  If no end_date: amortise to retirement age (default 68)
  Mortgages: Standard amortisation schedule

[BUSINESS INTERESTS]
  Method: Compound growth (if trading)
  Default rate: Configurable (or match investment rate)
  BR status: Projected forward (assume maintained if currently qualifying)

[DC PENSIONS]
  Method: Compound growth (contribution + returns)
  Pre-retirement: contributions + growth
  Post-retirement: drawdown at sustainable rate
  IHT: Always exempt (tracked for spending order optimisation)

PROJECTED NET ESTATE AT DEATH:
  = projected_cash + projected_investments + projected_properties
    + projected_business + projected_chattels
    - projected_liabilities
```

### 19.2 Second Death Projection (Married Couples)

```
SECOND DEATH PROJECTION
=========================

Step 1: Determine who dies first (actuarial)
  Use life expectancy tables by age and gender
  Younger spouse typically survives

Step 2: First death effects
  - Spouse exemption: All assets to survivor (no IHT)
  - Transferable NRB: Unused NRB carries to survivor
  - Transferable RNRB: Unused RNRB carries to survivor
  - Combined estate now in one person's name

Step 3: Projection to second death
  - Start from combined estate at first death
  - Continue projecting with asset-specific models
  - Account for:
    * Survivor's ongoing income and expenditure
    * Pension changes (loss of spouse pension income)
    * Property: survivor may downsize
    * Investment: may shift to income/preservation

Step 4: IHT on second death
  - Combined NRB: up to £650,000
  - Combined RNRB: up to £350,000
  - Maximum combined allowance: £1,000,000
  - IHT on amount above allowances at 40% (or 36%)
```

---

## 20. Phase 18: What-If Scenario Builder

**Service:** `EstateAgent.buildScenarios()`

### 20.1 Available Scenarios

```
SCENARIO 1: CURRENT POSITION
  No changes. Shows current IHT liability and beneficiary inheritance.

SCENARIO 2: OPTIMISED ESTATE PLAN
  Apply all recommended strategies:
  - Gifting savings estimate (~15% of IHT, max £50k)
  - Trust savings estimate (~10% of IHT, max £40k)
  - Shows potential reduction

SCENARIO 3: GIFTING STRATEGY
  Parameters: gifting_years (default 7), annual_gift (default £3,000)
  total_gifted = annual_gift * gifting_years
  iht_saved = total_gifted * 0.40
  Reduced estate and IHT shown

SCENARIO 4: PROPERTY DOWNSIZING
  Parameters: equity_release (default £200,000)
  Reduced estate by equity released
  Released equity can be gifted (PET)
  Show impact on RNRB

SCENARIO 5: TRUST CREATION
  Parameters: trust_value (default £325,000 = NRB)
  Assets moved into discretionary trust
  Within NRB: no immediate charge
  Show 40% saving on trust value
```

### 20.2 Scenario Comparison Output

```
| Metric              | Current   | Gifting  | Downsizing | Trust     | Optimised |
|---------------------|-----------|----------|------------|-----------|-----------|
| Gross Estate        | £X        | £X-gifts | £X-equity  | £X        | £X-all    |
| Net Estate          | £X        | £X-gifts | £X-equity  | £X        | £X-all    |
| IHT Liability       | £Y        | £Y-saved | £Y-saved   | £Y-saved  | £Y-all    |
| To Beneficiaries    | £Z        | £Z+saved | £Z+saved   | £Z+saved  | £Z+all    |
| IHT Saved           | -         | £saved   | £saved     | £saved    | £total    |
```

---

## 21. Thresholds & Constants Reference

**CRITICAL: `TaxConfigService` is the single source of truth for ALL values in this section.** Every constant, rate, threshold, and allowance MUST be fetched from `TaxConfigService` at runtime. No values should be hardcoded in services, controllers, or components. This ensures tax year changes require updating only `TaxConfigService` / `TaxConfigurationSeeder`.

### 21.1 IHT Core Constants

| Constant | Value | Frozen Until | Source |
|----------|-------|-------------|--------|
| Nil Rate Band (NRB) | £325,000 | April 2030 | `TaxConfigService::getInheritanceTax()['nil_rate_band']` |
| Residence Nil Rate Band (RNRB) | £175,000 | April 2030 | `TaxConfigService::getInheritanceTax()['residence_nil_rate_band']` |
| RNRB Taper Threshold | £2,000,000 | - | `TaxConfigService::getInheritanceTax()['rnrb_taper_threshold']` |
| RNRB Taper Rate | £1 lost per £2 over threshold | - | `TaxConfigService::getInheritanceTax()['rnrb_taper_rate']` |
| Standard IHT Rate | 40% | - | `TaxConfigService::getInheritanceTax()['standard_rate']` |
| Reduced Rate (Charity) | 36% | - | `TaxConfigService::getInheritanceTax()['reduced_rate_charity']` |
| Charity Threshold | 10% of baseline | - | `TaxConfigService::getInheritanceTax()['charity_threshold_percent']` |
| CLT Lifetime Rate | 20% (25% if settlor pays) | - | `TaxConfigService::getCLTRules()['lifetime_rate']` |
| CLT Death Rate | 40% | - | `TaxConfigService::getCLTRules()['death_rate']` |
| PET Exemption Period | 7 years | - | `TaxConfigService::getPETRules()['years_to_exemption']` |
| CLT Cumulation Window | 14 years | - | `TaxConfigService::getFourteenYearRule()['cumulation_window']` |
| Transferable NRB | Yes (unused proportion) | - | `TaxConfigService::getInheritanceTax()['transferable_nil_rate_band']` |
| Transferable RNRB | Yes (unused proportion) | - | `TaxConfigService::getInheritanceTax()['transferable_rnrb']` |
| Pension IHT Inclusion Date | April 2027 | - | `TaxConfigService::getInheritanceTax()['pension_iht_inclusion_date']` |

### 21.2 Gifting Exemption Constants

| Exemption | Value | Source |
|-----------|-------|--------|
| Annual Exemption | £3,000/year | `TaxConfigService::getGiftingExemptions()['annual_exemption']` |
| Carry Forward | 1 year | `TaxConfigService::getGiftingExemptions()['carry_forward_years']` |
| Small Gifts | £250/person/year | `TaxConfigService::getGiftingExemptions()['small_gifts_limit']` |
| Wedding: Parent | £5,000 | `TaxConfigService::getGiftingExemptions()['wedding_gifts']['parent_to_child']` |
| Wedding: Grandparent | £2,500 | `TaxConfigService::getGiftingExemptions()['wedding_gifts']['grandparent_to_grandchild']` |
| Wedding: Other | £1,000 | `TaxConfigService::getGiftingExemptions()['wedding_gifts']['other_person']` |
| Normal Expenditure from Income | Unlimited (conditions) | `TaxConfigService::getNormalExpenditureFromIncome()` |

### 21.3 Taper Relief Rates

All taper rates served from: `TaxConfigService::getTaperRelief('pet')` and `TaxConfigService::getTaperRelief('clt')`

| Years Since Gift | % of Full Rate Charged | Effective IHT Rate |
|-----------------|----------------------|-------------------|
| 0 - 3 years | 100% | 40% |
| 3 - 4 years | 80% | 32% |
| 4 - 5 years | 60% | 24% |
| 5 - 6 years | 40% | 16% |
| 6 - 7 years | 20% | 8% |
| 7+ years | 0% | 0% (exempt) |

### 21.4 Trust Charge Constants

| Charge Type | Rate | Source |
|-------------|------|--------|
| Entry (CLT) | 20% on excess over NRB | `TaxConfigService::getTrustCharges()['entry_charge_rate']` |
| Entry (CLT, settlor pays) | 25% (grossing up) | `TaxConfigService::getTrustCharges()['entry_charge_grossed_up']` |
| Periodic (10-year) | Max 6% effective | `TaxConfigService::getTrustCharges()['periodic_charge_max']` |
| Exit | Proportionate to periodic | `TaxConfigService::getTrustCharges()['exit_charge_method']` |
| Trust income tax: Non-dividend | 45% | `TaxConfigService::getTrusts()['income_tax_rate']` |
| Trust income tax: Dividend | 39.35% | `TaxConfigService::getTrusts()['dividend_tax_rate']` |

### 21.5 Growth Rates & Projections — NO DEFAULTS

**CRITICAL: Never use default values for growth rates or projections. Always use the user's actual data.**

| Data Point | Source | Rule |
|------------|--------|------|
| Growth rate for projections | User's risk profile → mapped to growth rate via `TaxConfigService::getAssumptions()` | ALWAYS use user's risk level. Never a hardcoded default. |
| Property growth rate | `TaxConfigService::getAssumptions()['property_growth']` | Centralised assumption, not hardcoded |
| Inflation rate | `TaxConfigService::getAssumptions()['inflation']` | Centralised assumption |
| Life expectancy | Actuarial tables based on user's actual age and gender | ALWAYS use user data. Never a default age. |
| Retirement age | `user.retirement_age` | ALWAYS use user's chosen age |
| State Pension age | `TaxConfigService::getPensionAllowances()['state_pension_age']` | From TaxConfigService |

**Risk Level to Growth Rate Mapping (via TaxConfigService):**

| Risk Level | Expected Return | Source |
|------------|----------------|--------|
| Very Low | 2.0% | `TaxConfigService::getAssumptions()['growth_by_risk']['very_low']` |
| Low | 3.0% | `TaxConfigService::getAssumptions()['growth_by_risk']['low']` |
| Low-Medium | 4.0% | `TaxConfigService::getAssumptions()['growth_by_risk']['low_medium']` |
| Medium | 5.0% | `TaxConfigService::getAssumptions()['growth_by_risk']['medium']` |
| Medium-High | 6.0% | `TaxConfigService::getAssumptions()['growth_by_risk']['medium_high']` |
| High | 7.0% | `TaxConfigService::getAssumptions()['growth_by_risk']['high']` |

**The following EstateDefaults constants are REMOVED — replaced by actual user data or TaxConfigService:**

| Old Constant | Replacement |
|-------------|-------------|
| ~~`ESTIMATED_PROPERTY_VALUE`~~ | Use actual `user.properties` data. If missing, flag as missing data (Phase 1 readiness gate) — do NOT assume a value. |
| ~~`ESTIMATED_INVESTMENT_VALUE`~~ | Use actual `user.investment_accounts` data. If missing, flag. |
| ~~`ESTIMATED_SAVINGS_VALUE`~~ | Use actual `user.savings_accounts` data. If missing, flag. |
| ~~`ESTIMATED_BUSINESS_VALUE`~~ | Use actual `user.business_interests` data. If missing, flag. |
| ~~`DEFAULT_LIFE_EXPECTANCY`~~ | Use actuarial tables with user's actual age and gender. |
| ~~`DEFAULT_CURRENT_AGE`~~ | Use `user.date_of_birth`. If missing, BLOCK (Phase 1 readiness gate). |
| ~~`DEFAULT_RETIREMENT_AGE`~~ | Use `user.retirement_age`. If missing, use State Pension age from TaxConfigService. |
| ~~`DEFAULT_PROPERTY_GROWTH_RATE`~~ | Use `TaxConfigService::getAssumptions()['property_growth']`. |
| ~~`INVESTMENT_RETURN_RATE`~~ | Use user's risk-level-mapped growth rate from TaxConfigService. |

**Retained constants (structural, not assumption-based):**

| Constant | Value | Source |
|----------|-------|--------|
| `RNRB_TAPER_THRESHOLD` | £2,000,000 | `TaxConfigService::getInheritanceTax()['rnrb_taper_threshold']` |
| `TRUST_SUGGESTION_THRESHOLD` | £2,000,000 | `TaxConfigService::get('estate.trust_suggestion_threshold')` |
| `COMBINED_NRB_THRESHOLD` | £650,000 | Derived: `NRB * 2` from TaxConfigService |
| `COMBINED_RNRB_THRESHOLD` | £350,000 | Derived: `RNRB * 2` from TaxConfigService |

### 21.6 Probate Fees

| Estate Value | Probate Fee |
|-------------|-------------|
| Up to £5,000 | £0 (no fee) |
| Over £5,000 | £300 |

### 21.7 IHT Payment Rules

| Rule | Detail |
|------|--------|
| Payment deadline | 6 months after end of month of death |
| Late payment | Interest charged from deadline |
| Instalment option | Available for property, businesses, certain shares |
| Instalment terms | 10 equal annual instalments |
| Instalment interest | Interest charged on outstanding balance |
| Excepted estates | Simplified reporting for small estates / estates passing entirely to spouse |

### 21.8 Intestacy Threshold

| Threshold | Value | Application |
|-----------|-------|-------------|
| Spouse + Children | £322,000 | Spouse gets this amount plus half of remainder |

---

## 22. Message Key Reference

### 22.1 Readiness Messages

| Key | Message |
|-----|---------|
| `readiness.missing_dob` | "Add your date of birth to calculate life expectancy and project your estate forward." |
| `readiness.missing_assets` | "Add your assets (properties, savings, investments) to calculate your estate value." |
| `readiness.missing_will` | "Consider creating or recording your will to ensure your estate is distributed according to your wishes." |
| `readiness.missing_family` | "Add family members to assess intestacy rules and RNRB eligibility." |
| `readiness.missing_expenditure` | "Add income and expenditure data to calculate gifting from surplus income opportunities." |

### 22.2 IHT Calculation Messages

| Key | Message |
|-----|---------|
| `iht.no_liability` | "No Inheritance Tax liability projected - no mitigation strategies needed." |
| `iht.nrb_single` | "Nil Rate Band of {nrb} available for single person." |
| `iht.nrb_married` | "Combined Nil Rate Band of {nrb} available ({nrb_each} each). Transfers between spouses are exempt from IHT on first death." |
| `iht.nrb_widowed` | "Combined Nil Rate Band of {nrb_total} available (own {nrb_own} + {nrb_transferred} transferred from late spouse's estate)." |
| `iht.rnrb_eligible` | "Residence Nil Rate Band of {rnrb} available for main residence left to direct descendants." |
| `iht.rnrb_tapered` | "Residence Nil Rate Band reduced from {rnrb_full} to {rnrb_after} because estate exceeds {threshold}." |
| `iht.rnrb_not_eligible` | "No Residence Nil Rate Band available - {reason}." |
| `iht.charitable_rate` | "Your charitable bequests qualify for the reduced 36% IHT rate, saving {saving} in IHT." |
| `iht.charitable_shortfall` | "Increase charitable giving by {shortfall} to qualify for the reduced 36% rate and save {potential_saving} in IHT." |

### 22.3 Mitigation Strategy Messages

| Key | Message |
|-----|---------|
| `mitigation.charitable_opportunity` | "Increase charitable giving by {shortfall} to qualify for the reduced 36% IHT rate and save {saving}." |
| `mitigation.liquidity_risk` | "Your liquid assets of {liquid} may not cover the IHT liability of {iht}." |
| `mitigation.existing_cover` | "You have {cover} in life cover that can offset IHT." |
| `mitigation.place_in_trust` | "You have {count} life insurance policies totalling {amount} not written in trust." |
| `mitigation.annual_gifting` | "Using your annual gift exemption of {exemption}/year could save {saving} in IHT over {years} years." |
| `mitigation.annual_gifting_covers` | "Annual gifting of {exemption}/year could fully offset your IHT liability over {years} years." |
| `mitigation.life_cover` | "A whole of life policy for {amount} could cover the remaining IHT liability." |
| `mitigation.pet_strategy` | "With {cycles} seven-year cycles available, PETs up to {capacity} could become fully exempt." |
| `mitigation.clt_last_resort` | "A CLT of {amount} would incur {charge} immediate charge (20% on amount over NRB)." |
| `mitigation.clt_warning` | "CLTs are complex and should only be considered after exhausting simpler strategies." |

### 22.4 Will & Estate Planning Messages

| Key | Message |
|-----|---------|
| `will.no_will` | "You do not have a will. Without one, your estate will be distributed under intestacy rules." |
| `will.stale` | "Your will has not been reviewed recently. It is recommended to review your will every 3-5 years or after significant life events." |
| `will.trust_triggers` | "{count} wishes in your will may require trust arrangements." |
| `will.guardianship_needed` | "Consider appointing guardians for your minor children in your will." |
| `lpa.missing_financial` | "Consider creating a Property and Financial Affairs LPA to protect your finances if you lose mental capacity." |
| `lpa.missing_health` | "Consider creating a Health and Welfare LPA." |
| `lpa.not_registered` | "Your LPA must be registered with the Office of the Public Guardian before it can be used." |

### 22.5 Pension Messages

| Key | Message |
|-----|---------|
| `pension.update_nominations` | "Update pension nomination forms to ensure death benefits pass to your chosen beneficiaries outside your estate." |
| `pension.spend_last` | "Pensions are outside your estate for IHT. Consider spending other assets first and preserving pension wealth." |
| `pension.drawdown_preferred` | "Drawdown allows remaining pension to pass to beneficiaries; annuity income typically dies with the annuitant." |

---

## 23. Existing Codebase Mapping

### 23.1 Services (22 files)

| Service | File | Purpose | Phase |
|---------|------|---------|-------|
| `IHTCalculationService` | `app/Services/Estate/IHTCalculationService.php` | Core IHT calculation with projections | 3 |
| `EstateAssetAggregatorService` | `app/Services/Estate/EstateAssetAggregatorService.php` | Gathers all assets across modules | 2 |
| `GiftingStrategy` | `app/Services/Estate/GiftingStrategy.php` | PET analysis, annual exemptions, recommendations | 6 |
| `GiftingStrategyOptimizer` | `app/Services/Estate/GiftingStrategyOptimizer.php` | Optimal gifting with income/PET/CLT priority | 6 |
| `PersonalizedGiftingStrategyService` | `app/Services/Estate/PersonalizedGiftingStrategyService.php` | Personalised gifting recommendations | 6 |
| `GiftingTimelineService` | `app/Services/Estate/GiftingTimelineService.php` | Visual timeline of gifts and 7-year windows | 6 |
| `PersonalizedTrustStrategyService` | `app/Services/Estate/PersonalizedTrustStrategyService.php` | 5 trust strategies with CLT calculations | 7 |
| `TrustService` | `app/Services/Estate/TrustService.php` | Trust CRUD and management | 7 |
| `TrustValuationService` | `app/Services/Estate/TrustValuationService.php` | IHT value by trust type | 7 |
| `WillAnalysisService` | `app/Services/Estate/WillAnalysisService.php` | Charitable analysis, trust trigger detection | 8 |
| `IntestacyCalculator` | `app/Services/Estate/IntestacyCalculator.php` | UK intestacy distribution | 8 |
| `LifePolicyStrategyService` | `app/Services/Estate/LifePolicyStrategyService.php` | Insurance vs self-insurance comparison | 9 |
| `LifeCoverCalculator` | `app/Services/Estate/LifeCoverCalculator.php` | Cover recommendations (3 scenarios) | 9 |
| `IHTStrategyGeneratorService` | `app/Services/Estate/IHTStrategyGeneratorService.php` | 8 prioritised mitigation strategies | 5 |
| `SpouseNRBTrackerService` | `app/Services/Estate/SpouseNRBTrackerService.php` | Transferable NRB/RNRB calculation | 4 |
| `ComprehensiveEstatePlanService` | `app/Services/Estate/ComprehensiveEstatePlanService.php` | Full estate plan combining all services | All |
| `AssetLiquidityAnalyzer` | `app/Services/Estate/AssetLiquidityAnalyzer.php` | Liquid/semi-liquid/illiquid classification | 2 |
| `NetWorthAnalyzer` | `app/Services/Estate/NetWorthAnalyzer.php` | Net worth calculation and breakdown | 2 |
| `CashFlowProjector` | `app/Services/Estate/CashFlowProjector.php` | Cash flow projections to death | 17 |
| `FutureValueCalculator` | `app/Services/Estate/FutureValueCalculator.php` | Compound growth calculations | 17 |
| `IHTFormattingService` | `app/Services/Estate/IHTFormattingService.php` | Currency/percentage formatting for IHT | Output |
| `LetterEstateValidationService` | `app/Services/Estate/LetterEstateValidationService.php` | Letter of wishes validation | 8 |

### 23.2 Models (8 files)

| Model | Table | Key Fields |
|-------|-------|------------|
| `Estate\IHTProfile` | `iht_profiles` | marital_status, has_spouse, own_home, home_value, nrb_transferred, rnrb_transferred, charitable_giving_percent |
| `Estate\Asset` | `estate_assets` | user_id, asset_type, current_value, is_iht_exempt |
| `Estate\Liability` | `estate_liabilities` | user_id, type, current_balance |
| `Estate\Will` | `wills` | user_id, has_will, spouse_primary_beneficiary, executor_name, will_last_updated |
| `Estate\Bequest` | `bequests` | will_id, beneficiary_name, beneficiary_type, bequest_type, percentage_of_estate, specific_amount, charity_registration_number |
| `Estate\Gift` | `gifts` | user_id, gift_date, recipient, gift_type, gift_value, taper_relief_applicable |
| `Estate\Trust` | `trusts` | user_id, trust_type, trust_creation_date, current_value, discount_amount, loan_amount, is_relevant_property_trust |
| `Estate\IHTCalculation` | `iht_calculations` | user_id, calculation results (cached) |

### 23.3 Agent

| Agent | File | Methods |
|-------|------|---------|
| `EstateAgent` | `app/Agents/EstateAgent.php` | `analyze()`, `generateRecommendations()` (7-step tree), `buildScenarios()` (5 scenarios) |

### 23.4 Controllers

| Controller | File | Endpoints |
|------------|------|-----------|
| `IHTController` | `app/Http/Controllers/Api/Estate/IHTController.php` | IHT calculations, projections |
| `GiftingController` | `app/Http/Controllers/Api/Estate/GiftingController.php` | Gift CRUD, gifting strategy |
| `TrustController` | `app/Http/Controllers/Api/Estate/TrustController.php` | Trust CRUD, trust planning |
| `WillController` | `app/Http/Controllers/Api/Estate/WillController.php` | Will CRUD, bequest management |
| `LifePolicyController` | `app/Http/Controllers/Api/Estate/LifePolicyController.php` | Life policy strategy |
| `LetterValidationController` | `app/Http/Controllers/Api/Estate/LetterValidationController.php` | Letter of wishes |

### 23.5 Vue Components (30+ components)

| Component | Purpose |
|-----------|---------|
| `EstateDashboard.vue` | Main estate planning dashboard |
| `EstateOverviewCard.vue` | Summary card with key metrics |
| `IHTLiabilityGauge.vue` | Visual IHT liability indicator |
| `IHTCalculationTable.vue` | Detailed IHT breakdown |
| `IHTLiabilityBreakdown.vue` | Asset-by-asset IHT contribution |
| `IHTAssetBreakdown.vue` | Asset type analysis |
| `IHTMitigationStrategies.vue` | Mitigation recommendations display |
| `NRBRNRBTracker.vue` | NRB and RNRB usage visualisation |
| `SpouseExemptionNotice.vue` | Spouse exemption information |
| `NetWorth.vue` | Net worth summary |
| `NetWorthWaterfallChart.vue` | Waterfall chart of estate composition |
| `AssetsLiabilities.vue` | Asset and liability management |
| `AssetForm.vue` | Asset creation/editing form |
| `LiabilityForm.vue` | Liability creation/editing form |
| `GiftingStrategy.vue` | Gifting strategy display |
| `GiftCard.vue` | Individual gift card |
| `GiftForm.vue` | Gift creation/editing form |
| `GiftingTimelineChart.vue` | 7-year timeline visualisation |
| `DualGiftingTimeline.vue` | His/hers gifting timeline |
| `TrustPlanning.vue` | Trust planning overview |
| `TrustPlanningStrategy.vue` | Trust strategy recommendations |
| `TrustForm.vue` | Trust creation/editing form |
| `CashFlow.vue` | Cash flow projections |
| `CashFlowProjectionChart.vue` | Cash flow chart |
| `IntestacyRules.vue` | Intestacy distribution display |
| `LifeCoverRecommendations.vue` | Life cover scenarios |
| `LifePolicyStrategy.vue` | Insurance vs self-insurance |
| `EstateLifeEventsImpact.vue` | Life event impact analysis |
| `EstateProjectionComparison.vue` | Scenario comparison |
| `MissingDataAlert.vue` | Missing data prompts |

### 23.6 Plan Components

| Component | Purpose |
|-----------|---------|
| `EstatePlanContent.vue` | Comprehensive estate plan |
| `EstateExecutiveSummary.vue` | Executive summary |
| `EstateCurrentSituation.vue` | Current situation analysis |
| `EstatePersonalInformation.vue` | Personal information review |
| `EstateGroupedActions.vue` | Grouped action items |
| `EstateJointView.vue` | Joint (couple) estate view |
| `EstateWhatIfControls.vue` | What-if scenario controls |

---

## Appendix A: IHT Calculation Worked Examples

### Example 1: Single Person, No Property

```
Assets:
  Savings: £50,000
  Investments: £200,000
  Personal possessions: £20,000
Liabilities: £0

Gross estate: £270,000
Net estate: £270,000

NRB: £325,000
RNRB: £0 (no property)
Total allowances: £325,000

Taxable estate: MAX(0, £270,000 - £325,000) = £0
IHT: £0

RESULT: No IHT liability. Estate below NRB.
```

### Example 2: Married Couple, Family Home, Children

```
Assets (combined):
  Main residence: £600,000 (joint)
  Savings: £100,000
  Investments: £300,000
  Pensions: £400,000 (EXEMPT - outside estate)
Liabilities:
  Mortgage: £150,000

Gross estate (excluding pensions): £1,000,000
Net estate: £850,000

NRB (combined): £650,000
RNRB (home to children): £350,000
Total allowances: £1,000,000

Taxable estate: MAX(0, £850,000 - £1,000,000) = £0
IHT: £0

RESULT: No IHT. Combined allowances cover entire estate.
NOTE: Pensions pass to nominated beneficiaries tax-free (death before 75)
      or at beneficiary's marginal rate (death after 75).
```

### Example 3: Widowed Person, Large Estate

```
Assets:
  Main residence: £800,000
  Investment portfolio: £500,000
  Savings: £200,000
  Buy-to-let property: £350,000
  Personal possessions: £50,000
Liabilities:
  BTL mortgage: £100,000

Gross estate: £1,900,000
Net estate: £1,800,000

NRB (own): £325,000
NRB (transferred from late spouse, 100% unused): £325,000
Total NRB: £650,000

RNRB: £175,000 (home to children)
RNRB (transferred from late spouse): £175,000
Total RNRB: £350,000

Total allowances: £1,000,000

Taxable estate: £1,800,000 - £1,000,000 = £800,000
IHT at 40%: £320,000
Effective rate: 17.8%

MITIGATION OPTIONS:
1. Charitable giving: Leave £80,000 (10% of baseline £800,000)
   New rate: 36%. New IHT: £800,000 * 0.36 = £288,000
   Saving: £32,000. Cost: £80,000. NET LOSS: -£48,000. NOT WORTH IT.

2. Annual gifting: £3,000/year for 15 years = £45,000
   IHT saved: £18,000

3. PETs: Gift £325,000 (survive 7 years)
   IHT saved: £130,000

4. Life insurance: £170,000 WoL in trust
   Covers remaining liability after gifting
```

### Example 4: Estate Over RNRB Taper

```
Net estate: £2,400,000

NRB: £325,000
RNRB before taper: £175,000
RNRB taper: (£2,400,000 - £2,000,000) * 0.5 = £200,000
RNRB after taper: MAX(0, £175,000 - £200,000) = £0

Total allowances: £325,000
Taxable estate: £2,075,000
IHT at 40%: £830,000

STRATEGY: Reduce estate below £2m to recover full RNRB
  Gift £400,000 (PET): Estate drops to £2,000,000
  RNRB restored: £175,000
  New taxable: £2,000,000 - £325,000 - £175,000 = £1,500,000
  New IHT: £600,000
  Saving: £230,000 (£400,000 * 40% + £175,000 * 40%)
```

---

## Appendix B: 2024 Autumn Budget Changes (Future Impact)

### Agricultural and Business Relief Reform (from April 2026)

```
CURRENT (2025/26):
  BR: 100% or 50% with no cap
  AR: 100% or 50% with no cap

FROM APRIL 2026:
  Combined BR + AR:
    First £1,000,000: Full relief (100% or 50% as applicable)
    Above £1,000,000: 50% relief only
    Effective IHT rate on excess: 20% (50% of 40%)

IMPACT:
  Farming estates over £1m will face IHT for first time
  AIM share BR portfolios over £1m will face partial IHT
  Business owners with large trading businesses affected

FYNLA ACTION:
  When estate includes BR/AR assets over £1m:
  Flag: "From April 2026, Business and Agricultural Relief will be
        capped at £1,000,000 combined. Above this threshold, only
        50% relief applies (effective 20% IHT rate)."
```

### Pensions in Estate (from April 2027)

```
CURRENT (2025/26):
  DC pensions: Outside estate for IHT

FROM APRIL 2027:
  DC pensions: INCLUDED in estate for IHT
  (Subject to confirmation and implementation details)

IMPACT:
  Massive increase in IHT liability for many estates
  Pension spending order advice changes fundamentally
  Life insurance needs increase significantly

FYNLA ACTION:
  Flag: "From April 2027, pension funds may be included in your
        estate for IHT purposes. This would increase your projected
        IHT liability by approximately {pension_value * 0.40}."
```

---

## Appendix C: Decision Engine Coverage Gaps (To Build)

Areas identified in this research that are NOT yet fully implemented in the codebase:

| Gap | Current State | Priority | Complexity |
|-----|--------------|----------|------------|
| Powers of Attorney tracking | Not modelled | HIGH | LOW |
| Pension nomination tracking | No model for nominations | HIGH | LOW |
| Domicile/non-dom handling | Assumed UK domiciled | LOW | MEDIUM |
| Agricultural Relief calculation | No AR model | MEDIUM | MEDIUM |
| Deed of Variation analysis | Not implemented | LOW | LOW |
| Digital assets in will | Not tracked | LOW | LOW |
| Probate fee calculation | Not implemented | LOW | VERY LOW |
| IHT payment instalment analysis | Not implemented | LOW | LOW |
| 14-year rule full implementation | Config exists, not fully calculated | MEDIUM | HIGH |
| Normal expenditure from income evidence tracking | Calculated but no evidence log | MEDIUM | MEDIUM |
| 2026 BR/AR cap modelling | Not yet implemented | HIGH (time-sensitive) | MEDIUM |
| 2027 pension IHT inclusion | Not yet implemented | HIGH (time-sensitive) | HIGH |
| Excepted estate simplified reporting | Not implemented | LOW | LOW |
| Cross-border will requirements | Not modelled | LOW | MEDIUM |
| Guardianship tracking | Not a separate model | MEDIUM | LOW |
| Expression of wish form tracking | No dedicated model | MEDIUM | LOW |
