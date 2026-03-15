# Protection Recommendation Engine: Decision Tree & Message Reference

> Complete mapping of every decision path, user-facing message, and the context data that drives each output.
>
> **Engine version:** v0.1.0 | **Last updated:** 2026-03-14 | **Module:** Protection

---

## Table of Contents

1. [Engine Pipeline Overview](#1-engine-pipeline-overview)
2. [User Context: Data Inputs](#2-user-context-data-inputs)
3. [Phase 1: Data Readiness Gate](#3-phase-1-data-readiness-gate)
4. [Phase 2: Life Stage Assessment](#4-phase-2-life-stage-assessment)
5. [Phase 3: Life Cover Gap Analysis](#5-phase-3-life-cover-gap-analysis)
6. [Phase 4: Critical Illness Assessment](#6-phase-4-critical-illness-assessment)
7. [Phase 5: Income Protection Assessment](#7-phase-5-income-protection-assessment)
8. [Phase 6: Family Income Benefit Assessment](#8-phase-6-family-income-benefit-assessment)
9. [Phase 7: Mortgage Protection Assessment](#9-phase-7-mortgage-protection-assessment)
10. [Phase 8: Private Medical Insurance Assessment](#10-phase-8-private-medical-insurance-assessment)
11. [Phase 9: Business Protection Assessment](#11-phase-9-business-protection-assessment)
12. [Phase 10: Employer Benefits Assessment](#12-phase-10-employer-benefits-assessment)
13. [Phase 11: Whole of Life & IHT Planning](#13-phase-11-whole-of-life--iht-planning)
14. [Phase 12: Policy Optimisation](#14-phase-12-policy-optimisation)
15. [Phase 13: Trust & Beneficiary Review](#15-phase-13-trust--beneficiary-review)
16. [Phase 14: Spouse/Partner Protection Coordination](#16-phase-14-spousepartner-protection-coordination)
17. [Phase 15: Life Event Impact Assessment](#17-phase-15-life-event-impact-assessment)
18. [Phase 16: Priority Ordering & Conflict Resolution](#18-phase-16-priority-ordering--conflict-resolution)
19. [Phase 17: Output Formatting](#19-phase-17-output-formatting)
20. [Product Type Reference](#20-product-type-reference)
21. [UK-Specific Rules & Thresholds](#21-uk-specific-rules--thresholds)
22. [Thresholds & Constants Reference](#22-thresholds--constants-reference)
23. [Config Message Key Reference](#23-config-message-key-reference)

---

## 1. Engine Pipeline Overview

```
User Request
    |
    v
[Phase 1] DataReadinessGate ──── can_proceed = false? ──> STOP (return readiness blocks only)
    |
    | can_proceed = true
    v
[Phase 2] LifeStageAssessmentService ──> life stage modifiers, priority adjustments
    |
    v
[Phase 3] LifeCoverGapAnalysisService ──> life cover need, gap, recommendations
    |
    v
[Phase 4] CriticalIllnessAssessmentService ──> CI need, gap, recommendations
    |
    v
[Phase 5] IncomeProtectionAssessmentService ──> IP need, gap, recommendations
    |
    v
[Phase 6] FamilyIncomeBenefitAssessmentService ──> FIB suitability, recommendations
    |
    v
[Phase 7] MortgageProtectionAssessmentService ──> mortgage cover gap, recommendations
    |
    v
[Phase 8] PrivateMedicalInsuranceAssessmentService ──> PMI suitability, recommendations
    |
    v
[Phase 9] BusinessProtectionAssessmentService ──> business cover needs (if applicable)
    |
    v
[Phase 10] EmployerBenefitsAssessmentService ──> employer cover integration
    |
    v
[Phase 11] WholeOfLifeIHTService ──> WoL/IHT planning needs
    |
    v
[Phase 12] PolicyOptimisationService ──> existing policy review
    |
    v
[Phase 13] TrustBeneficiaryReviewService ──> trust placement, beneficiary review
    |
    v
[Phase 14] SpouseProtectionCoordinationService ──> joint/coordinated cover
    |
    v
[Phase 15] LifeEventImpactService ──> life event modifiers
    |
    v
[Phase 16] PriorityOrderingService ──> merged, deduplicated, conflict-resolved recommendations
    |
    v
[Phase 17] ProtectionOutputFormatter ──> sorted, formatted API response
```

**Key principle:** Protection needs are assessed independently for each product type, then merged and prioritised. Employer benefits reduce personal cover needs. Life events can elevate or reduce priority of specific cover types.

---

## 2. User Context: Data Inputs

**Service:** `ProtectionContextBuilder`

ProtectionContextBuilder produces no user-facing messages. It assembles the data context consumed by every other service.

### 2.1 Personal Profile

| Field | Source | Used By |
|-------|--------|---------|
| `age` | Calculated from `user.date_of_birth` | Premium estimates, eligibility, life stage |
| `date_of_birth` | `user.date_of_birth` | Phase 1 readiness gate |
| `gender` | `user.gender` | Actuarial life expectancy, premium factors |
| `marital_status` | `user.marital_status` | Spouse coordination, joint policies |
| `smoker_status` | `user.smoker` or `protectionProfile.smoker_status` | Premium loading (+50-100%), eligibility |
| `health_status` | `user.good_health` or `protectionProfile.health_status` | Underwriting, exclusions, premium loading |
| `occupation` | `user.occupation` or `protectionProfile.occupation` | IP occupation class, premium factors |
| `employment_status` | `user.employment_status` | IP eligibility, employer benefits, state benefit interaction |
| `education_level` | `user.education_level` | Occupation class inference |
| `retirement_age` | `protectionProfile.retirement_age` or `user.retirement_age` or default 67 | Cover term calculation |
| `years_to_retirement` | `retirement_age - age` | IP term, life cover term |
| `uk_resident` | `user.uk_resident` | State benefit eligibility |

### 2.2 Dependants Profile

| Field | Source | Used By |
|-------|--------|---------|
| `number_of_dependents` | `protectionProfile.number_of_dependents` or `FamilyMember` count | Life cover need, FIB, priority elevation |
| `dependents_ages` | `protectionProfile.dependents_ages` or `FamilyMember` records | Education funding calc, cover term |
| `youngest_dependent_age` | Min of `dependents_ages` | Years of cover needed |
| `oldest_dependent_age` | Max of `dependents_ages` | When cover can reduce |
| `has_children_under_18` | Any dependent age < 18 | Life cover priority, FIB trigger |
| `has_children_under_5` | Any dependent age < 5 | Elevated cover need |
| `years_until_youngest_independent` | `21 - youngest_dependent_age` | Minimum cover term |
| `dependent_relationships` | `FamilyMember.relationship` | Distinguish children vs elderly parents vs other |

### 2.3 Financial Profile

| Field | Source | Used By |
|-------|--------|---------|
| `gross_annual_income` | Sum of employment + self-employment + rental + dividend + other | Life cover multiplier, IP calc |
| `net_annual_income` | Via `UKTaxCalculator` | IP benefit cap, expenditure comparison |
| `employment_income` | `user.annual_employment_income` | Earned income that stops on death |
| `self_employment_income` | `user.annual_self_employment_income` | Earned income that stops on death |
| `rental_income` | `user.annual_rental_income` | Continuing income (survives death) |
| `dividend_income` | `user.annual_dividend_income` | Continuing income (survives death) |
| `other_income` | `user.annual_other_income` | May or may not continue |
| `monthly_expenditure` | `user.monthly_expenditure` or `protectionProfile.monthly_expenditure` | Needs-based calc, affordability |
| `annual_expenditure` | `user.annual_expenditure` | Needs-based calc |
| `income_that_stops` | Employment + self-employment net income | Human capital calculation |
| `income_that_continues` | Rental + dividend + spouse income | Reduces protection need |
| `net_income_difference` | `income_that_stops - income_that_continues` | Actual family income loss |
| `tax_band` | Derived from gross income | IP tax treatment, relevant life policy |

### 2.4 Debt Profile

| Field | Source | Used By |
|-------|--------|---------|
| `mortgage_balance` | `protectionProfile.mortgage_balance` or `mortgages` table | Debt protection need, decreasing term |
| `mortgage_type` | `mortgages.type` (repayment, interest_only, mixed) | Decreasing vs level term |
| `mortgage_term_remaining` | From `mortgages` table | Cover term matching |
| `mortgage_rate` | `mortgages.interest_rate` | Decreasing rate calculation |
| `other_debts` | `protectionProfile.other_debts` or `liabilities` table | Total debt protection need |
| `total_debt` | `mortgage_balance + other_debts` | Debt coverage gap |

### 2.5 Existing Cover Profile

| Field | Source | Used By |
|-------|--------|---------|
| `life_policies` | `user.lifeInsurancePolicies` | Life cover gap calc |
| `total_life_cover` | Sum of `life_policies.sum_assured` | Gap analysis |
| `ci_policies` | `user.criticalIllnessPolicies` | CI gap calc |
| `total_ci_cover` | Sum of `ci_policies.sum_assured` | Gap analysis |
| `ip_policies` | `user.incomeProtectionPolicies` | IP gap calc |
| `total_ip_annual_benefit` | Annualised sum of IP benefits | Gap analysis |
| `disability_policies` | `user.disabilityPolicies` | Supplementary IP |
| `sickness_policies` | `user.sicknessIllnessPolicies` | Supplementary CI/IP |
| `policies_in_trust` | Count where `in_trust = true` | Trust review |
| `policies_not_in_trust` | Count where `in_trust = false` | Trust recommendation |
| `has_mortgage_protection` | Any policy with `is_mortgage_protection = true` | Mortgage cover check |
| `total_annual_premiums` | Sum of all policy premiums (annualised) | Affordability check |
| `premium_as_percent_of_income` | `total_annual_premiums / gross_annual_income * 100` | Affordability alert |

### 2.6 Employer Benefits Profile

| Field | Source | Used By |
|-------|--------|---------|
| `death_in_service_multiple` | User input or employer benefits record | Reduces personal life cover need |
| `death_in_service_amount` | `employment_income * death_in_service_multiple` | Specific cover amount |
| `employer_ip_benefit` | Employer group IP amount | Reduces personal IP need |
| `employer_ip_definition` | Own occupation / suited occupation / any occupation | IP quality assessment |
| `employer_ip_deferred_weeks` | Employer group IP waiting period | Gap in cover assessment |
| `employer_ci_cover` | Employer group CI amount | Reduces personal CI need |
| `employer_pmi` | Boolean: employer provides PMI | PMI recommendation |
| `employer_pmi_taxable` | P11D benefit value | Tax impact of employer PMI |

### 2.7 Spouse/Partner Profile

| Field | Source | Used By |
|-------|--------|---------|
| `spouse_exists` | `user.spouse_id` is not null | Spouse coordination |
| `spouse_income` | Spouse's employment + self-employment income | Reduces protection need |
| `spouse_net_income` | Via `UKTaxCalculator` | Net income offset |
| `spouse_continuing_income` | Spouse rental + dividend income | Continuing income after death |
| `spouse_has_own_cover` | Spouse's own protection policies | Coordination |
| `spouse_permission_granted` | `user.hasAcceptedSpousePermission()` | Data access gate |
| `joint_mortgage` | Mortgage with `joint_owner_id` | Joint life cover |

### 2.8 Property Profile

| Field | Source | Used By |
|-------|--------|---------|
| `is_homeowner` | Has properties | Mortgage protection trigger |
| `properties` | `user.properties` | Estate value for IHT |
| `main_residence_value` | Main residence property value | RNRB eligibility |
| `total_property_value` | Sum of all property values | Estate calculation |

### 2.9 Estate Profile (for Whole of Life assessment)

| Field | Source | Used By |
|-------|--------|---------|
| `estimated_estate_value` | Properties + investments + savings - debts | IHT exposure |
| `iht_nil_rate_band` | Via `TaxConfigService` (325,000) | IHT threshold |
| `iht_residence_nil_rate_band` | Via `TaxConfigService` (175,000) | IHT threshold |
| `potential_iht_liability` | 40% of estate above thresholds | WoL cover need |

### 2.10 Business Profile (if self-employed or director)

| Field | Source | Used By |
|-------|--------|---------|
| `is_self_employed` | `employment_status = 'self_employed'` | Business protection trigger |
| `is_company_director` | User input | Relevant life policy, key person |
| `business_value` | User input | Shareholder protection |
| `ownership_percentage` | User input | Shareholder protection amount |
| `has_business_partners` | User input | Partnership protection |
| `number_of_employees` | User input | Key person insurance |

---

## 3. Phase 1: Data Readiness Gate

**Service:** `ProtectionDataReadinessService`

The readiness gate runs sequential checks. If any check returns a `block`, `can_proceed = false` and all subsequent phases are skipped.

### Decision Tree

**CRITICAL: No protection profile abstraction.** The readiness gate checks for ACTUAL user data, not a bundled "protection profile" entity. We check each data point individually so the user knows exactly what is missing and why it matters.

```
START — Check each data point individually (no protection profile abstraction)
  |
  v
[Check 1] BLOCKING: Does user have date_of_birth?
  |
  +-- NO --> BLOCK: [PR1] "Your date of birth is needed to calculate
  |          appropriate cover terms and premium estimates."
  |          can_proceed = false
  |
  +-- YES → continue
       |
       v
[Check 2] BLOCKING: Does user have income data (gross_annual_income > 0)?
  |
  +-- NO --> BLOCK: [PR2] "Your income details are needed to calculate
  |          life cover requirements and income protection benefits."
  |          can_proceed = false
  |
  +-- YES → continue
       |
       v
[Check 3] BLOCKING: Does user have marital_status?
  |
  +-- NO --> BLOCK: [PR3] "Your marital status is needed to assess
  |          joint cover options and spouse protection needs."
  |          can_proceed = false
  |
  +-- YES → continue
       |
       v
[Check 4] Does user have monthly_expenditure?
  |
  +-- NO --> WARN: [PR4] "Your monthly expenditure is needed to
  |          calculate how much cover your family would need to
  |          maintain their lifestyle."
  |
  +-- YES → continue
       |
       v
[Check 5] Does user have employment_status?
  |
  +-- NO --> WARN: [PR5] "Your employment status determines employer
  |          benefits (death in service, group income protection),
  |          occupation class for income protection premiums, and
  |          state benefit entitlements."
  |
  +-- YES → continue
       |
       v
[Check 6] Does user have dependants recorded?
  |
  +-- NO --> INFO: [PR6] "Add your dependants to assess life cover
  |          needs. Without dependants, income protection becomes
  |          the priority over life cover."
  |
  +-- YES → Are dependants' ages recorded?
       |    +-- NO → WARN: [PR6a] "Adding your dependants' ages helps
       |             calculate education funding needs, the number of
       |             years income replacement is needed, and the
       |             appropriate cover term."
       |    +-- YES → continue
       |
       v
[Check 7] Does user have a mortgage or significant debts?
  |
  +-- Check properties for mortgage balance, check debts
  +-- NO mortgage/debts → INFO: [PR7] "If you have a mortgage or
  |   debts, add them to assess whether your cover protects against
  |   these liabilities."
  |
  +-- YES → continue (used in life cover gap calculation)
       |
       v
[Check 8] Does user have existing insurance policies recorded?
  |
  +-- NO policies AND has_no_policies flag NOT set → WARN: [PR8]
  |   "Add your existing insurance policies (life, critical illness,
  |    income protection) so we can identify gaps rather than
  |    recommending duplicate cover. Include employer-provided
  |    death in service and group cover."
  |
  +-- NO policies AND has_no_policies = true → INFO: [PR8a]
  |   "You've confirmed you have no existing policies.
  |    Recommendations will be based on your full protection need."
  |
  +-- YES → continue
       |
       v
[Check 9] Does user have employer benefits recorded?
  |
  +-- employment_status = employed AND no employer benefits?
  |   WARN: [PR9] "Check if your employer provides death in service,
  |   group income protection, or group critical illness cover.
  |   These are often included in your employment package and
  |   reduce the cover you need to buy privately."
  |
  +-- YES or not employed → continue
       |
       v
[Check 10] Does user have occupation recorded?
  |
  +-- NO --> INFO: [PR10] "Your occupation determines your income
  |          protection occupation class. Some occupations (e.g.,
  |          manual work, hazardous roles) attract higher premiums
  |          or limited cover definitions."
  |
  +-- YES → continue
       |
       v
[Check 11] Does user have smoker_status recorded?
  |
  +-- NO --> INFO: [PR11] "Your smoking status significantly affects
  |          premium estimates. Non-smokers typically pay 40-50%
  |          less for life and critical illness cover."
  |
  +-- YES → continue
       |
       v
[Check 12] Does user have health_status recorded?
  |
  +-- NO --> INFO: [PR12] "Your health status helps assess
  |          underwriting likelihood and potential policy exclusions."
  |
  +-- YES → continue
       |
       v
[Check 13] Is user married but no spouse linked?
  |
  +-- YES --> INFO: [PR13] "Link your partner's account to coordinate
  |           protection cover, assess joint life policies, and
  |           avoid gaps or duplication."
  |
  +-- NO → continue
       |
       v
[Check 14] Always:
    INFO: [PR14] "Add any upcoming life events (new baby, marriage,
    career change, redundancy) to receive tailored protection advice."
       |
       v
[PROCEED to Phase 2]
```

### Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| PR1 | `date_of_birth` is null | `block` | `readiness.block.date_of_birth` | "Your date of birth is needed to calculate appropriate cover terms and premium estimates." |
| PR2 | No income data | `block` | `readiness.block.income` | "Your income details are needed to calculate life cover requirements and income protection benefits." |
| PR3 | No marital status | `block` | `readiness.block.marital_status` | "Your marital status is needed to assess joint cover options and spouse protection needs." |
| PR4 | No expenditure data | `warn` | `readiness.warn.expenditure` | "Your monthly expenditure is needed to calculate how much cover your family would need to maintain their lifestyle." |
| PR5 | No employment status | `warn` | `readiness.warn.employment_status` | "Your employment status determines employer benefits, occupation class, and state benefit entitlements." |
| PR6 | No dependants recorded | `info` | `readiness.info.dependants` | "Add your dependants to assess life cover needs. Without dependants, income protection becomes the priority." |
| PR6a | Dependants without ages | `warn` | `readiness.warn.dependant_ages` | "Adding your dependants' ages helps calculate education funding needs and appropriate cover terms." |
| PR7 | No mortgage/debts recorded | `info` | `readiness.info.debts` | "If you have a mortgage or debts, add them to assess whether your cover protects against these liabilities." |
| PR8 | No policies, not confirmed | `warn` | `readiness.warn.missing_policies` | "Add your existing insurance policies so we can identify gaps rather than recommending duplicate cover." |
| PR8a | Confirmed no policies | `info` | `readiness.info.no_policies_confirmed` | "You've confirmed you have no existing policies. Recommendations will be based on your full protection need." |
| PR9 | Employed, no employer benefits | `warn` | `readiness.warn.employer_benefits` | "Check if your employer provides death in service, group income protection, or group critical illness cover." |
| PR10 | No occupation | `info` | `readiness.info.occupation` | "Your occupation determines your income protection occupation class and premium estimates." |
| PR11 | No smoker status | `info` | `readiness.info.smoker_status` | "Your smoking status significantly affects premium estimates. Non-smokers typically pay 40-50% less." |
| PR12 | No health status | `info` | `readiness.info.health_status` | "Your health status helps assess underwriting likelihood and potential policy exclusions." |
| PR13 | Married, no spouse linked | `info` | `readiness.info.spouse_link` | "Link your partner's account to coordinate protection cover and avoid gaps or duplication." |
| PR14 | Always | `info` | `readiness.info.life_events` | "Add any upcoming life events (new baby, marriage, career change) to receive tailored protection advice." |

---

## 4. Phase 2: Life Stage Assessment

**Service:** `LifeStageAssessmentService`

Life stage determines baseline priority weightings and which product types are most relevant.

### Life Stage Decision Tree

```
age < 18?
    -> life_stage = "minor"
    -> BLOCK: "Protection analysis is designed for adults aged 18+."

age 18-25 AND single AND no dependants?
    -> life_stage = "young_single"
    -> priority_modifiers: IP = high, life = low, CI = medium, FIB = skip

age 18-35 AND (married OR has_dependants)?
    -> life_stage = "young_family"
    -> priority_modifiers: life = critical, IP = critical, CI = high, FIB = high

age 26-35 AND single AND no dependants?
    -> life_stage = "established_single"
    -> priority_modifiers: IP = high, CI = medium, life = low

age 36-50 AND has_dependants?
    -> life_stage = "peak_family"
    -> priority_modifiers: life = critical, IP = critical, CI = high, FIB = high

age 36-50 AND no dependants?
    -> life_stage = "peak_no_dependants"
    -> priority_modifiers: IP = high, CI = high, life = medium

age 51-60?
    -> life_stage = "pre_retirement"
    -> priority_modifiers: IP = high (if still working), CI = high, life = medium, WoL = medium

age 61-67 (or retirement_age)?
    -> life_stage = "approaching_retirement"
    -> priority_modifiers: IP = medium (short term remaining), WoL = high, life = low (unless dependants)

age > retirement_age?
    -> life_stage = "retired"
    -> priority_modifiers: IP = skip, WoL = high, life = low (unless IHT planning)
```

### Life Stage Message Reference

| # | Life Stage | Config Key | Message |
|---|-----------|------------|---------|
| LS1 | young_single | `life_stage.young_single` | "As a young adult without dependants, income protection is your priority. Your ability to earn is your greatest asset." |
| LS2 | young_family | `life_stage.young_family` | "With a young family, comprehensive protection is essential. Life cover and income protection should be your immediate priorities." |
| LS3 | established_single | `life_stage.established_single` | "Without dependants, focus on protecting your income. Consider critical illness cover to protect your lifestyle and mortgage." |
| LS4 | peak_family | `life_stage.peak_family` | "At peak earning years with dependants, ensure your protection keeps pace with your financial commitments." |
| LS5 | peak_no_dependants | `life_stage.peak_no_dependants` | "Without dependants, focus on income protection and critical illness cover to protect your lifestyle and commitments." |
| LS6 | pre_retirement | `life_stage.pre_retirement` | "As retirement approaches, review whether existing term policies will cover you long enough. Consider whole of life for estate planning." |
| LS7 | approaching_retirement | `life_stage.approaching_retirement` | "With retirement approaching, most term policies will end soon. Focus on estate planning protection needs." |
| LS8 | retired | `life_stage.retired` | "In retirement, protection focus shifts to estate planning. Whole of life cover can help with inheritance tax planning." |

---

## 5. Phase 3: Life Cover Gap Analysis

**Service:** `LifeCoverGapAnalysisService`

Existing implementation: `CoverageGapAnalyzer.php`

### 5.1 Life Cover Need Calculation Methods

Two methods are used. The engine calculates both and uses the higher of the two for the recommendation, but shows both to the user.

#### Method A: Income Replacement (Multiplier Method)

```
Simple multiplier:
    life_cover_need_A = gross_annual_income * INCOME_MULTIPLIER

INCOME_MULTIPLIER varies by life stage:
    young_family (under 40 with dependants): 15x
    peak_family (40-50 with dependants):     12x
    no dependants:                           5x (debt cover only)
    pre_retirement:                          8x
    approaching_retirement:                  5x
    retired:                                 0x (no earned income to replace)
```

#### Method B: Needs-Based (Current Implementation)

```
Total Need = Human Capital + Debt Protection + Education Funding + Final Expenses

Human Capital:
    annual_income_need = net_income_that_stops - income_that_continues
    IF annual_income_need > 0:
        human_capital = annual_income_need / 0.047  (sustainable withdrawal rate)
    ELSE:
        human_capital = 0  (spouse/continuing income covers the gap)

Debt Protection:
    debt_protection = mortgage_balance + other_debts

Education Funding:
    FOR each dependent:
        years_remaining = max(0, 21 - dependent_age)
        education_cost += 9,000 * years_remaining

Final Expenses:
    final_expenses = 7,500  (funeral and administration costs)
```

#### Method Selection

```
recommended_cover = max(method_A, method_B)

IF method_A > method_B * 1.5:
    note: "The income multiplier method suggests higher cover. Consider whether
           the needs-based figure fully captures your family's long-term requirements."

IF method_B > method_A * 1.5:
    note: "The needs-based method suggests higher cover due to significant debt
           obligations or education funding requirements."
```

### 5.2 Existing Cover Assessment

```
total_life_cover = SUM(life_policies.sum_assured)
    + death_in_service_amount  (employer benefit)

NOTE: Death in service is employer-dependent. If user changes job, this cover is lost.
    -> WARN if death_in_service > 50% of total cover: [LC-W1]

For decreasing term policies:
    current_value = start_value * (1 - decreasing_rate) ^ years_elapsed
    Use current_value, not original sum_assured

For policies approaching expiry (within 2 years of policy_end_date):
    -> WARN: [LC-W2] "Policy with {provider} expires in {months} months..."

For policies already expired:
    -> Exclude from total
    -> WARN: [LC-W3] "Policy with {provider} expired on {date}..."
```

### 5.3 Gap Analysis Decision Tree

```
life_cover_gap = recommended_cover - total_life_cover

life_cover_gap <= 0?
    YES -> life_cover_status = "adequate"
    |   -> [LC1] "Your life cover is adequate..."
    |
    |   life_cover > recommended_cover * 1.5?
    |       YES -> [LC2] "Your life cover significantly exceeds..."
    |              (May indicate over-insurance or changed circumstances)

life_cover_gap > 0 AND life_cover_gap < recommended_cover * 0.1?
    YES -> life_cover_status = "minor_gap"
    |   -> [LC3] "Your life cover is close to adequate..."

life_cover_gap > 0 AND life_cover_gap < recommended_cover * 0.5?
    YES -> life_cover_status = "moderate_gap"
    |   -> [LC4] "Your life cover has a moderate shortfall..."

life_cover_gap >= recommended_cover * 0.5 AND total_life_cover > 0?
    YES -> life_cover_status = "significant_gap"
    |   -> [LC5] "Your life cover has a significant shortfall..."

total_life_cover = 0 AND has_dependants?
    YES -> life_cover_status = "critical_no_cover"
    |   -> [LC6] "You have no life cover and {count} dependants..."

total_life_cover = 0 AND NOT has_dependants AND total_debt > 0?
    YES -> life_cover_status = "no_cover_with_debt"
    |   -> [LC7] "You have no life cover. Outstanding debts of {debt}..."

total_life_cover = 0 AND NOT has_dependants AND total_debt = 0?
    YES -> life_cover_status = "no_cover_no_need"
    |   -> [LC8] "You have no life cover but no dependants or debts..."
```

### 5.4 Cover Type Recommendations

```
IF life_cover_gap > 0:
|
+-- Has mortgage AND no mortgage-specific cover?
|   YES -> Recommend decreasing term for mortgage: [LC-R1]
|          amount = mortgage_balance
|          term = mortgage_term_remaining
|          type = decreasing_term (for repayment mortgage)
|                 level_term (for interest-only mortgage)
|
+-- Has dependants?
|   YES -> Recommend level term or FIB for income replacement: [LC-R2]
|          amount = life_cover_gap - mortgage_cover_recommended
|          term = years_until_youngest_independent OR years_to_retirement (whichever longer)
|
+-- Has debt but no dependants?
|   YES -> Recommend level term for debt clearance: [LC-R3]
|          amount = total_debt + final_expenses
|          term = max debt repayment term OR 10 years (whichever shorter)
|
+-- Existing policies not in trust?
    YES -> Recommend trust placement: [LC-R4]
```

### 5.5 Life Cover Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| LC1 | Cover adequate | `info` | `life_cover.adequate` | "Your life cover of {cover} meets or exceeds the recommended {need}. Review annually to ensure it keeps pace with your circumstances." |
| LC2 | Significantly over-insured | `info` | `life_cover.over_insured` | "Your life cover of {cover} significantly exceeds the recommended {need}. Review whether all policies are still needed -- you may be able to reduce premiums." |
| LC3 | Minor gap (<10%) | `medium` | `life_cover.minor_gap` | "Your life cover of {cover} is close to the recommended {need}. A small top-up of {gap} would close this gap." |
| LC4 | Moderate gap (10-50%) | `high` | `life_cover.moderate_gap` | "Your life cover of {cover} falls short of the recommended {need} by {gap}. Your family would face a significant shortfall." |
| LC5 | Significant gap (>50%) | `critical` | `life_cover.significant_gap` | "Your life cover of {cover} covers less than half the recommended {need}. Shortfall of {gap} leaves your family financially vulnerable." |
| LC6 | No cover, has dependants | `critical` | `life_cover.no_cover_dependants` | "You have no life cover and {count} dependant(s). Your family would have no financial safety net. Immediate action recommended." |
| LC7 | No cover, has debt | `high` | `life_cover.no_cover_debt` | "You have no life cover. Outstanding debts of {debt} would pass to your estate, potentially burdening your family." |
| LC8 | No cover, no need | `info` | `life_cover.no_cover_no_need` | "You have no life cover, but with no dependants or significant debts, your immediate need is lower. Consider cover if your circumstances change." |
| LC-W1 | Death in service >50% of cover | `warn` | `life_cover.dis_reliance` | "Over half your life cover ({dis_amount}) comes from your employer's death in service benefit. This cover is lost if you leave employment." |
| LC-W2 | Policy expiring within 2 years | `warn` | `life_cover.expiring_policy` | "Your {provider} policy expires in {months} months. Plan replacement cover before it lapses to avoid a gap." |
| LC-W3 | Policy expired | `warn` | `life_cover.expired_policy` | "Your {provider} policy expired on {date}. You have less cover than you may think." |
| LC-R1 | Mortgage cover recommendation | `high` | `life_cover.recommend.mortgage` | "Add {type} cover of {amount} over {term} years to protect your mortgage. {Decreasing term is cheaper and matches a repayment mortgage. / Level term is needed for an interest-only mortgage.}" |
| LC-R2 | Family cover recommendation | `critical` | `life_cover.recommend.family` | "Add level term cover of {amount} over {term} years to replace your income for your family until your youngest dependant is independent." |
| LC-R3 | Debt cover recommendation | `high` | `life_cover.recommend.debt` | "Add level term cover of {amount} to clear outstanding debts and cover final expenses if you were to die." |
| LC-R4 | Trust recommendation | `medium` | `life_cover.recommend.trust` | "Place your life policies in trust. Without trust placement, payouts form part of your estate -- subject to inheritance tax and probate delays of 6-12 months." |

---

## 6. Phase 4: Critical Illness Assessment

**Service:** `CriticalIllnessAssessmentService`

### 6.1 Critical Illness Need Calculation

```
CI need is typically calculated as:
    ci_need = gross_annual_income * CI_MULTIPLIER

CI_MULTIPLIER:
    With mortgage:    3x annual income (or mortgage balance, whichever higher)
    Without mortgage: 3x annual income
    Self-employed:    3x annual income (higher recommended, business disruption)

Alternative needs-based approach:
    ci_need = mortgage_balance
            + 12_months_expenditure (adjustment period)
            + medical_treatment_fund (10,000 default)
            + home_adaptation_fund (20,000 if homeowner)
            - existing_ci_cover
            - existing_savings (partial offset, max 50%)
```

### 6.2 CI Gap Analysis Decision Tree

```
ci_gap = ci_need - total_ci_cover

total_ci_cover = SUM(ci_policies.sum_assured)
               + employer_ci_cover
               + combined_life_ci_policies (CI element only)

ci_gap <= 0?
    YES -> [CI1] "Your critical illness cover is adequate..."

ci_gap > 0 AND total_ci_cover > 0?
    YES -> [CI2] "Your critical illness cover of {cover} falls short..."

total_ci_cover = 0?
    YES ->
    |
    +-- has_dependants OR has_mortgage?
    |   YES -> [CI3] "You have no critical illness cover..."
    |
    +-- no_dependants AND no_mortgage AND age < 50?
    |   YES -> [CI4] "Consider critical illness cover..."
    |
    +-- age >= 50?
        YES -> [CI5] "Critical illness cover becomes more expensive..."
```

### 6.3 Standalone vs Combined Decision

```
User has life cover need AND CI need?
|
+-- Budget constrained (total_premiums > 7% of income)?
|   YES -> Recommend combined life + CI: [CI-R1]
|          note: "Combined cover is cheaper but pays out only once."
|
+-- Budget available?
    YES -> Recommend standalone CI: [CI-R2]
           note: "Standalone CI pays out on diagnosis AND life cover
                  remains in force. More comprehensive but costs more."

User has CI need but adequate life cover?
    -> Recommend standalone CI only: [CI-R3]
```

### 6.4 CI Conditions Context

```
Common conditions covered by UK CI policies:
    - Cancer (excluding less advanced cases)
    - Heart attack (of specified severity)
    - Stroke (with permanent symptoms)
    - Multiple sclerosis
    - Kidney failure
    - Major organ transplant
    - Coronary artery bypass
    - Alzheimer's disease / dementia
    - Parkinson's disease
    - Motor neurone disease
    - Loss of limbs
    - Blindness / deafness
    - Paralysis
    - Third-degree burns
    - Traumatic brain injury
    - Benign brain tumour

Children's CI (usually included free):
    Covers children until age 18-21 at reduced benefit (typically 25,000-50,000)

Enhanced CI policies also cover:
    - Less advanced conditions (partial payout, typically 25%)
    - Children's critical illness
    - Total permanent disability
```

### 6.5 CI Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| CI1 | Cover adequate | `info` | `ci.adequate` | "Your critical illness cover of {cover} meets the recommended {need}. Review conditions covered to ensure they match current medical definitions." |
| CI2 | Gap exists | `high` | `ci.gap` | "Your critical illness cover of {cover} falls short of the recommended {need} by {gap}. A serious diagnosis could leave you unable to meet financial commitments." |
| CI3 | No cover, has commitments | `high` | `ci.no_cover_commitments` | "You have no critical illness cover. A serious illness could prevent you working while financial commitments continue. One in two people are diagnosed with a serious illness in their lifetime." |
| CI4 | No cover, younger, no commitments | `medium` | `ci.no_cover_consider` | "Consider critical illness cover to protect your lifestyle and future plans. Premiums are significantly lower when you are younger and healthier." |
| CI5 | No cover, older | `medium` | `ci.no_cover_older` | "Critical illness cover becomes more expensive with age and pre-existing conditions may limit availability. Consider whether your savings and investments provide adequate self-insurance." |
| CI-R1 | Recommend combined | `high` | `ci.recommend.combined` | "Combined life and critical illness cover of {amount} over {term} years. More affordable but pays out only once -- if you claim for critical illness, life cover ends." |
| CI-R2 | Recommend standalone | `high` | `ci.recommend.standalone` | "Standalone critical illness cover of {amount} over {term} years. Pays a lump sum on diagnosis while your life cover remains in force." |
| CI-R3 | Standalone (life adequate) | `medium` | `ci.recommend.standalone_only` | "Your life cover is adequate. Add standalone critical illness cover of {amount} to protect against serious illness without affecting your life cover." |

---

## 7. Phase 5: Income Protection Assessment

**Service:** `IncomeProtectionAssessmentService`

### 7.1 Income Protection Need Calculation

```
Maximum IP benefit (insurer limit):
    max_ip_benefit = gross_annual_income * 0.60  (60% of gross, typical insurer max)

    Some insurers allow up to 70% of gross minus state benefits.
    Conservative calculation: 60% of gross earned income.

IP need (monthly):
    ip_monthly_need = (gross_annual_income * 0.60) / 12

Adjust for state benefits (if employment_status != self_employed):
    esa_new_style = 90.50/week = 4,706/year (2025/26 rate, work-related activity group)
    esa_support_group = 138.20/week = 7,186/year (2025/26 rate, support group)

    Insurers may offset state benefits:
    ip_net_need = ip_monthly_need - (esa_annual / 12)

For self-employed:
    No employer sick pay, no SSP
    ip_need elevated: recommend 6+ month deferred period with savings bridge
    OR shorter deferred (4-8 weeks) if no emergency fund
```

### 7.2 Deferred Period Decision Tree

```
Deferred period (waiting period before IP pays out):

employer_sick_pay_weeks > 0?
    YES -> deferred_period = employer_sick_pay_weeks
           (Match deferred period to employer sick pay duration)
           Common: 4 weeks, 8 weeks, 13 weeks, 26 weeks

employer_sick_pay_weeks = 0 OR self_employed?
    |
    +-- emergency_fund >= 6 months expenditure?
    |   YES -> deferred_period = 26 weeks (cheaper premiums)
    |          [IP-D1] "With 6 months of emergency savings, a 26-week deferred
    |                   period reduces premiums while your savings bridge the gap."
    |
    +-- emergency_fund >= 3 months?
    |   YES -> deferred_period = 13 weeks
    |          [IP-D2] "With 3 months of savings, a 13-week deferred period balances
    |                   premium cost against the gap before benefits start."
    |
    +-- emergency_fund < 3 months?
        YES -> deferred_period = 4 weeks
               [IP-D3] "With limited savings, a 4-week deferred period ensures
                        income replacement starts quickly. Premiums will be higher."
```

### 7.3 Occupation Class Assessment

```
Occupation classes (insurer definitions):
    Class 1: Professional/office-based (accountant, solicitor, IT)
             -> Lowest premiums, broadest definitions
    Class 2: Skilled non-manual (manager, teacher, nurse)
             -> Standard premiums
    Class 3: Skilled manual (electrician, plumber, chef)
             -> Higher premiums, may have exclusions
    Class 4: Manual/hazardous (builder, farmer, driver)
             -> Highest premiums, limited cover options

Occupation definition types:
    "own_occupation": Cannot perform YOUR specific job
        -> BEST for policyholder, most expensive
        -> Recommended for Class 1-2
    "suited_occupation": Cannot perform any job suited to your experience/education
        -> MIDDLE ground
    "any_occupation": Cannot perform ANY job
        -> WORST for policyholder, cheapest
        -> Avoid if possible

IF existing_ip.occupation_class = "any_occupation":
    -> WARN: [IP-W1] "Your income protection uses an 'any occupation' definition..."
```

### 7.4 IP Benefit Period Decision

```
Benefit period options:
    Short-term IP: 1 year or 2 years
        -> Cheaper, covers temporary illness
        -> WARNING: Most claims last longer than 2 years

    Long-term IP: To retirement age (SRA or chosen retirement age)
        -> More expensive, comprehensive protection
        -> RECOMMENDED for primary earner

    5-year benefit period:
        -> Compromise option

IF existing_ip.benefit_period_months < 24:
    -> WARN: [IP-W2] "Your income protection benefit period is only {months} months..."

RECOMMENDED:
    benefit_period = "to_retirement_age"
    benefit_end_age = min(retirement_age, 70)  (most insurers cap at 70)
```

### 7.5 IP Gap Analysis Decision Tree

```
total_ip_annual_cover = SUM(ip_policies annualised benefit)
                      + SUM(disability_policies annualised benefit)
                      + employer_ip_benefit

ip_annual_need = gross_annual_income * 0.60
ip_annual_gap = ip_annual_need - total_ip_annual_cover

ip_annual_gap <= 0?
    YES -> [IP1] "Your income protection is adequate..."

ip_annual_gap > 0 AND total_ip_annual_cover > 0?
    YES -> [IP2] "Your income protection covers {percent}% of your income..."

total_ip_annual_cover = 0 AND employment_status != "retired"?
    YES ->
    |
    +-- is_self_employed?
    |   YES -> [IP3] "As self-employed, you have no employer sick pay or SSP..."
    |
    +-- has_dependants?
    |   YES -> [IP4] "You have no income protection and {count} dependant(s)..."
    |
    +-- has_mortgage?
    |   YES -> [IP5] "You have no income protection and a mortgage of {amount}..."
    |
    +-- default:
        YES -> [IP6] "You have no income protection..."

employment_status = "retired"?
    -> [IP7] "Income protection is not applicable in retirement..."
    -> SKIP further IP analysis
```

### 7.6 Employer IP Assessment

```
IF employer_ip_benefit > 0:
|
+-- employer_ip_definition = "any_occupation"?
|   YES -> [IP-E1] "Your employer's group income protection uses an 'any occupation'
|                    definition. This only pays if you cannot do ANY job, not just your
|                    current role. Consider personal 'own occupation' cover to fill this gap."
|
+-- employer_ip_benefit < ip_annual_need * 0.5?
|   YES -> [IP-E2] "Your employer's income protection covers {percent}% of income.
|                    Consider topping up with a personal policy."
|
+-- employer_ip_deferred_weeks > 26?
    YES -> [IP-E3] "Your employer's income protection has a {weeks}-week waiting period.
                    Consider a short-term personal policy to bridge this gap."
```

### 7.7 State Benefits Interaction

```
State benefits available during incapacity:

Statutory Sick Pay (SSP):
    - Paid by employer for up to 28 weeks
    - Rate: 116.75/week (2025/26)
    - Not available to self-employed
    - Requires minimum earnings (123/week)

Employment and Support Allowance (ESA) - New Style:
    - Available after SSP ends (or immediately if no SSP)
    - Contribution-based (requires sufficient NI contributions)
    - Work-related activity group: 90.50/week
    - Support group: 138.20/week
    - Limited to 365 days for work-related activity group
    - Self-employed may qualify if Class 2 NI contributions paid

Universal Credit (UC):
    - Means-tested (income and savings below thresholds)
    - Standard allowance: varies by age and circumstances
    - Limited Capability for Work element: 416.19/month
    - Limited Capability for Work and Work-Related Activity: 416.19/month
    - Savings > 16,000 disqualify; 6,000-16,000 reduce entitlement

Personal Independence Payment (PIP):
    - Not means-tested, not income-tested
    - Based on functional ability
    - Daily living: 72.65 (standard) / 108.55 (enhanced) per week
    - Mobility: 28.70 (standard) / 75.75 (enhanced) per week
    - Not affected by IP payouts

NOTE for engine: State benefits provide minimal replacement. IP recommendations
should NOT assume state benefits bridge the gap, but should mention them as
supplementary support.
```

### 7.8 IP Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| IP1 | Cover adequate | `info` | `ip.adequate` | "Your income protection covers {percent}% of your income ({cover}/year). This meets the recommended 60% threshold." |
| IP2 | Partial cover | `high` | `ip.partial_cover` | "Your income protection covers {percent}% of your income. Recommended cover is 60% ({need}/year), leaving a gap of {gap}/year ({monthly_gap}/month)." |
| IP3 | No cover, self-employed | `critical` | `ip.no_cover_self_employed` | "As self-employed, you have no employer sick pay, no Statutory Sick Pay, and no income protection. If illness or injury stops you working, your income stops immediately." |
| IP4 | No cover, has dependants | `critical` | `ip.no_cover_dependants` | "You have no income protection and {count} dependant(s) relying on your income. If you could not work, your family would have no replacement income beyond state benefits of approximately {esa_weekly}/week." |
| IP5 | No cover, has mortgage | `high` | `ip.no_cover_mortgage` | "You have no income protection and a mortgage of {amount}. If illness prevented you working, you would need to meet {monthly_payment}/month mortgage payments from savings alone." |
| IP6 | No cover, default | `high` | `ip.no_cover_default` | "You have no income protection. If illness or injury prevented you working, you would rely solely on state benefits and savings." |
| IP7 | Retired | `info` | `ip.retired` | "Income protection is not applicable in retirement. Your retirement income sources are assessed in the Retirement module." |
| IP-W1 | Any occupation definition | `warn` | `ip.warn.any_occupation` | "Your income protection uses an 'any occupation' definition. This only pays if you cannot perform ANY job -- not just your current role. Consider upgrading to 'own occupation' cover." |
| IP-W2 | Short benefit period | `warn` | `ip.warn.short_benefit` | "Your income protection benefit period is only {months} months. Most long-term illness claims last significantly longer. Consider extending to your retirement age." |
| IP-D1 | 26-week deferred | `info` | `ip.deferred.26_week` | "With your emergency savings covering 6+ months, a 26-week deferred period reduces premiums significantly while your savings bridge the waiting period." |
| IP-D2 | 13-week deferred | `info` | `ip.deferred.13_week` | "A 13-week deferred period balances affordable premiums with a manageable gap. Your savings can cover the waiting period." |
| IP-D3 | 4-week deferred | `info` | `ip.deferred.4_week` | "A 4-week deferred period ensures income replacement starts quickly. Premiums are higher but you have limited savings to bridge a longer gap." |
| IP-E1 | Employer any occupation | `warn` | `ip.employer.any_occupation` | "Your employer's group income protection uses an 'any occupation' definition. Consider personal 'own occupation' cover for stronger protection." |
| IP-E2 | Employer low cover | `medium` | `ip.employer.low_cover` | "Your employer's income protection covers {percent}% of your income. Consider a personal top-up policy to reach the recommended 60%." |
| IP-E3 | Employer long deferred | `medium` | `ip.employer.long_deferred` | "Your employer's income protection has a {weeks}-week waiting period. Consider a short-term personal policy to bridge this gap." |

---

## 8. Phase 6: Family Income Benefit Assessment

**Service:** `FamilyIncomeBenefitAssessmentService`

### 8.1 FIB Suitability Decision Tree

```
FIB is suitable when:
    has_dependants AND youngest_dependent_age < 18?
    |
    YES ->
    |   +-- Budget constrained (can't afford level term for full need)?
    |   |   YES -> FIB is more cost-effective: [FIB1]
    |   |          FIB typically costs 30-50% less than equivalent level term
    |   |
    |   +-- Prefers regular income over lump sum?
    |   |   YES -> FIB better suited: [FIB2]
    |   |
    |   +-- Already has level term for debts, needs income replacement?
    |       YES -> FIB as complement: [FIB3]
    |
    NO -> FIB not recommended: [FIB4]

FIB Amount Calculation:
    fib_annual_benefit = net_income_that_stops - income_that_continues
    fib_monthly_benefit = fib_annual_benefit / 12
    fib_term = years_until_youngest_independent  (typically until youngest is 21)
             OR years_to_retirement (whichever is shorter)

Tax Treatment:
    FIB payouts are TAX-FREE if:
        - Policy is not in trust AND pays to legal personal representatives
        - OR policy IS in trust AND pays to named beneficiaries
    FIB payouts are TAXABLE as income if:
        - Paid to someone other than the policyholder's estate
        - AND policy is NOT written in trust
    RECOMMENDATION: Always write FIB in trust
```

### 8.2 FIB vs Level Term Comparison

```
Level Term:
    + Lump sum flexibility
    + Can invest/manage the capital
    + One-off decision
    - More expensive for equivalent income
    - Risk of poor investment decisions by beneficiary
    - Lump sum may be spent quickly

Family Income Benefit:
    + Cheaper (decreasing overall liability)
    + Regular income mimics lost salary
    + Cannot be overspent
    + Tax-free in trust
    - Less flexibility
    - Payments stop at end of term regardless
    - Cannot be accelerated (e.g., for deposit)
```

### 8.3 FIB Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| FIB1 | Budget-friendly alternative | `high` | `fib.budget_alternative` | "Family income benefit provides {monthly}/month tax-free for {term} years if you die. It costs approximately 30-50% less than equivalent level term cover." |
| FIB2 | Regular income preference | `medium` | `fib.regular_income` | "Family income benefit pays a regular tax-free income rather than a lump sum, providing steady financial support for your family." |
| FIB3 | Complement to level term | `medium` | `fib.complement` | "Add family income benefit of {monthly}/month alongside your existing life cover. The life cover clears debts; family income benefit replaces your ongoing income." |
| FIB4 | Not recommended | `info` | `fib.not_recommended` | "Family income benefit is most valuable when you have dependent children. Review if your circumstances change." |

---

## 9. Phase 7: Mortgage Protection Assessment

**Service:** `MortgageProtectionAssessmentService`

### 9.1 Mortgage Cover Decision Tree

```
has_mortgage = false?
    -> SKIP: [MP-S1] "No mortgage -- mortgage protection not applicable."

has_mortgage = true?
|
+-- has_mortgage_protection = true (existing mortgage-specific policy)?
|   |
|   +-- mortgage_cover >= mortgage_balance?
|   |   YES -> [MP1] "Your mortgage is adequately covered..."
|   |
|   +-- mortgage_cover < mortgage_balance?
|       YES -> [MP2] "Your mortgage cover of {cover} is below your
|                      outstanding balance of {balance}..."
|
+-- has_mortgage_protection = false?
    |
    +-- total_life_cover >= mortgage_balance + other_needs?
    |   YES -> [MP3] "Your general life cover includes sufficient capacity
    |                  to clear your mortgage..."
    |   NOTE: Recommend separating mortgage cover for clarity and efficiency
    |
    +-- total_life_cover < mortgage_balance + other_needs?
        YES -> [MP4] "You have no dedicated mortgage protection..."

Mortgage Type Determines Cover Type:
|
+-- mortgage_type = "repayment"?
|   -> Recommend DECREASING TERM: [MP-R1]
|      Cover decreases roughly in line with outstanding balance
|      Cheaper than level term for same initial sum assured
|
+-- mortgage_type = "interest_only"?
|   -> Recommend LEVEL TERM: [MP-R2]
|      Balance stays constant throughout term
|      Must cover full balance at any point
|
+-- mortgage_type = "mixed"?
    -> Recommend COMBINATION: [MP-R3]
       Decreasing element for repayment portion
       Level element for interest-only portion

Joint Mortgage Considerations:
|
+-- Joint mortgage AND single policy?
|   -> [MP-W1] "A single joint life policy pays out on the first death only..."
|      Recommendation: Consider separate policies for flexibility
|
+-- Joint mortgage AND no joint cover?
    -> [MP-W2] "Your joint mortgage of {amount} has no joint cover..."
```

### 9.2 Mortgage Protection Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| MP-S1 | No mortgage | `info` | `mortgage.not_applicable` | "No mortgage to protect. Review if you take on a mortgage in future." |
| MP1 | Adequately covered | `info` | `mortgage.adequate` | "Your mortgage of {balance} is covered by dedicated mortgage protection of {cover}." |
| MP2 | Under-covered | `high` | `mortgage.under_covered` | "Your mortgage cover of {cover} is below your outstanding balance of {balance}. Shortfall of {gap}." |
| MP3 | Covered by general life | `medium` | `mortgage.covered_by_general` | "Your general life cover includes capacity to clear your mortgage. Consider a separate, cheaper decreasing term policy to ring-fence mortgage protection." |
| MP4 | No mortgage cover | `high` | `mortgage.no_cover` | "You have no dedicated mortgage protection. Your mortgage of {balance} would need to be repaid from other assets or by your family." |
| MP-R1 | Decreasing term rec | `high` | `mortgage.recommend.decreasing` | "Decreasing term cover of {amount} over {term} years matches your repayment mortgage. Premiums are lower than level term as the cover reduces over time." |
| MP-R2 | Level term for IO | `high` | `mortgage.recommend.level_io` | "Level term cover of {amount} over {term} years is needed for your interest-only mortgage. The balance remains constant, so cover must too." |
| MP-R3 | Mixed recommendation | `high` | `mortgage.recommend.mixed` | "Your mixed mortgage needs both decreasing term (for the repayment element of {repayment_amount}) and level term (for the interest-only element of {io_amount})." |
| MP-W1 | Joint life single policy | `warn` | `mortgage.warn.joint_single` | "A single joint life policy pays out on the first death only. The surviving partner then has no cover. Consider separate policies -- often similar cost with better protection." |
| MP-W2 | Joint mortgage no cover | `high` | `mortgage.warn.joint_no_cover` | "Your joint mortgage of {amount} has no joint cover. Both partners should have mortgage protection." |

---

## 10. Phase 8: Private Medical Insurance Assessment

**Service:** `PrivateMedicalInsuranceAssessmentService`

### 10.1 PMI Decision Tree

```
PMI is optional and lower priority than life/CI/IP. Assess suitability:

employer_provides_pmi = true?
|
+-- YES -> [PMI1] "Your employer provides private medical insurance..."
|          Note: P11D taxable benefit
|          Note: Cover lost if employment ends
|
+-- NO ->
    |
    +-- gross_annual_income > 50,000 AND age < 65?
    |   YES -> [PMI2] "Consider private medical insurance for faster access..."
    |          Priority: low
    |
    +-- has_dependants AND gross_annual_income > 40,000?
    |   YES -> [PMI3] "Family private medical insurance provides faster access..."
    |          Priority: low
    |
    +-- age >= 65?
    |   YES -> [PMI4] "Private medical insurance premiums increase significantly
    |                   over 65. Consider whether self-insurance from savings
    |                   is more cost-effective."
    |
    +-- default:
        -> [PMI5] "Private medical insurance is not a priority at your current
                   income level. Focus on core protection (life, CI, IP) first."

Tax implications of employer PMI:
    Employer-paid PMI is a P11D benefit in kind
    Tax payable = premium * marginal_tax_rate
    Example: 1,500 premium, higher-rate taxpayer = 600 tax
    Still beneficial: employee pays 600 for 1,500 of cover
```

### 10.2 PMI Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| PMI1 | Employer provides | `info` | `pmi.employer_provided` | "Your employer provides private medical insurance. This is a taxable benefit (reported on your P11D) but still valuable. Cover is lost if you leave employment." |
| PMI2 | Higher earner | `low` | `pmi.higher_earner` | "Consider private medical insurance for faster access to specialists and treatment. Average individual cover costs {estimate}/month." |
| PMI3 | Family cover | `low` | `pmi.family` | "Family private medical insurance provides faster healthcare access for you and your dependants. Average family cover costs {estimate}/month." |
| PMI4 | Over 65 | `info` | `pmi.over_65` | "Private medical insurance premiums increase significantly over 65, and pre-existing conditions may be excluded. Consider whether self-insurance from savings is more cost-effective." |
| PMI5 | Lower priority | `info` | `pmi.lower_priority` | "Private medical insurance is not a priority at your current income level. Focus on core protection (life cover, critical illness, income protection) first." |

---

## 11. Phase 9: Business Protection Assessment

**Service:** `BusinessProtectionAssessmentService`

### 11.1 Business Protection Decision Tree

```
is_self_employed OR is_company_director?
|
+-- NO -> SKIP: Business protection not applicable

+-- YES ->
    |
    +-- is_company_director?
    |   |
    |   +-- Relevant Life Policy assessment: [BP1]
    |   |   Corporation tax deductible (not a P11D benefit)
    |   |   More tax-efficient than personal life cover for directors
    |   |   Cover amount: typically same as personal life need
    |   |
    |   +-- has_business_partners OR has_shareholders?
    |       |
    |       +-- YES -> Shareholder/Partnership Protection: [BP2]
    |       |   Cover amount = business_value * ownership_percentage
    |       |   Funded by cross-option agreement
    |       |   Ensures surviving partners can buy deceased's share
    |       |
    |       +-- number_of_employees > 0?
    |           YES -> Key Person Insurance: [BP3]
    |           Cover amount = typically 2-5x annual salary + recruitment costs
    |           Corporation tax deductible if purpose is to cover profit loss
    |
    +-- is_self_employed (sole trader)?
        |
        +-- Income protection is CRITICAL: [BP4]
        |   No SSP, no employer sick pay
        |   Recommend shorter deferred period
        |
        +-- Business overhead cover: [BP5]
            Covers fixed business costs during incapacity
            Rent, utilities, employee wages, insurance
            Separate from personal income protection
```

### 11.2 Relevant Life Policy Detail

```
Relevant Life Policy (RLP):
    WHO: Employees and directors of limited companies
    WHAT: Life cover paid for by the company
    TAX TREATMENT:
        - Premiums are corporation tax deductible expense
        - NOT a P11D benefit in kind (unlike other employer benefits)
        - Payout is tax-free (written in trust)
        - No lifetime allowance test (unlike group life)
    WHEN TO USE:
        - Director/employee of limited company
        - Particularly beneficial for higher/additional rate taxpayers
        - Alternative to personal life cover for company directors
    COMPARISON vs personal life cover:
        Personal: Paid from net income (after income tax + NI)
        RLP: Paid from gross company profit (before corporation tax)
        Saving: For higher-rate taxpayer, effective cost is ~50% less

    IF is_company_director AND has_personal_life_cover:
        -> [BP-R1] "Consider replacing personal life cover with a Relevant Life Policy..."
```

### 11.3 Business Protection Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| BP1 | Relevant Life Policy | `high` | `business.relevant_life` | "As a company director, a Relevant Life Policy provides life cover paid by your company. Premiums are corporation tax deductible and not a P11D benefit -- significantly more tax-efficient than personal cover." |
| BP2 | Shareholder protection | `high` | `business.shareholder` | "Shareholder protection insurance of {amount} ensures your business partners can buy your shares from your estate. Without it, your family may be left with shares they cannot sell, or the business may need to be sold." |
| BP3 | Key person insurance | `medium` | `business.key_person` | "Key person insurance covers the financial impact on your business if you or a key employee dies or is critically ill. Cover of {amount} protects against lost profits and recruitment costs." |
| BP4 | Self-employed IP critical | `critical` | `business.self_employed_ip` | "As a sole trader, you have no employer sick pay and no Statutory Sick Pay. Income protection is your single most important protection product." |
| BP5 | Business overhead cover | `medium` | `business.overhead` | "Business overhead protection covers your fixed business costs (rent, utilities, staff wages) during incapacity, preventing business failure while you recover." |
| BP-R1 | Replace with RLP | `high` | `business.replace_with_rlp` | "Consider replacing your personal life cover with a Relevant Life Policy through your company. As a {tax_band}-rate taxpayer, the effective cost saving is approximately {saving}% compared to paying premiums personally." |

---

## 12. Phase 10: Employer Benefits Assessment

**Service:** `EmployerBenefitsAssessmentService`

### 12.1 Death in Service Assessment

```
death_in_service_multiple > 0?
|
+-- YES ->
|   |   death_in_service_amount = employment_income * death_in_service_multiple
|   |
|   +-- death_in_service_multiple >= 4?
|   |   -> [EB1] "Your employer provides {multiple}x salary death in service ({amount})..."
|   |      Note: Generous benefit, reduces personal cover need
|   |
|   +-- death_in_service_multiple = 2 or 3?
|   |   -> [EB2] "Your employer provides {multiple}x salary death in service ({amount})..."
|   |      Note: Standard benefit, likely need supplementing
|   |
|   +-- death_in_service_multiple = 1?
|       -> [EB3] "Your employer provides 1x salary death in service ({amount})..."
|          Note: Minimal benefit, significant personal cover still needed
|   |
|   ALWAYS:
|   -> [EB-W1] "Death in service cover is lost if you change employer or are made redundant."
|   -> [EB-W2] "Death in service is usually written under a group life scheme which
|               may have a free cover limit. Cover above this limit requires underwriting."
|   -> Note: Death in service may count towards pension lifetime allowance
|            (but LTA was abolished April 2024, so this is no longer relevant)
|
+-- NO ->
    -> [EB4] "Your employer does not provide death in service benefit..."
       Note: Full personal cover needed
```

### 12.2 Employer Benefits Integration

```
Total effective cover = personal_cover + employer_cover

For life cover:
    effective_life_cover = total_life_cover + death_in_service_amount
    life_gap = recommended_life_cover - effective_life_cover

For income protection:
    effective_ip = personal_ip_annual + employer_ip_annual
    ip_gap = ip_annual_need - effective_ip

For critical illness:
    effective_ci = personal_ci_cover + employer_ci_cover
    ci_gap = ci_need - effective_ci

IMPORTANT: Display employer benefits separately with warnings about portability:
    "Employer benefits are included in your total cover calculation but are shown
     separately because they are not portable -- if you leave your employer, these
     benefits end immediately."
```

### 12.3 Employer Benefits Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| EB1 | Death in service 4x+ | `info` | `employer.dis_generous` | "Your employer provides {multiple}x salary death in service benefit ({amount}). This is a generous benefit that significantly reduces your personal life cover need." |
| EB2 | Death in service 2-3x | `info` | `employer.dis_standard` | "Your employer provides {multiple}x salary death in service benefit ({amount}). This helps but likely needs supplementing with personal cover to meet your full protection need." |
| EB3 | Death in service 1x | `warn` | `employer.dis_minimal` | "Your employer provides 1x salary death in service benefit ({amount}). This is minimal -- personal life cover is essential to fill the gap." |
| EB4 | No death in service | `warn` | `employer.no_dis` | "Your employer does not provide death in service benefit. Your full life cover need must be met through personal policies." |
| EB-W1 | Portability warning | `warn` | `employer.portability` | "Employer benefits are not portable. If you change jobs, are made redundant, or retire, these benefits end immediately. Ensure you have personal cover that does not depend on employment." |
| EB-W2 | Free cover limit | `info` | `employer.free_cover_limit` | "Group life schemes have a free cover limit (typically 3-10x salary). Cover above this limit requires medical underwriting, which may result in exclusions." |

---

## 13. Phase 11: Whole of Life & IHT Planning

**Service:** `WholeOfLifeIHTService`

### 13.1 Whole of Life Decision Tree

```
age >= 50 OR estimated_estate_value > iht_threshold?
|
+-- YES ->
|   |
|   +-- estimated_estate_value > (NRB + RNRB) * 2?  (married: 1,000,000)
|   |   YES -> potential_iht = (estate - threshold) * 0.40
|   |   |
|   |   +-- Has whole of life policy covering IHT?
|   |   |   YES -> [WOL1] "Your whole of life cover of {cover} offsets
|   |   |                   your estimated IHT liability of {iht}..."
|   |   |
|   |   +-- No whole of life cover?
|   |       YES -> [WOL2] "Your estimated estate of {estate} exceeds the IHT
|   |                       threshold. A whole of life policy of {iht} would
|   |                       cover the potential tax liability."
|   |
|   +-- estimated_estate_value > NRB * 2?  (650,000 married) but below RNRB threshold?
|   |   YES -> [WOL3] "Your estate may be close to the IHT threshold..."
|   |          Priority: info (plan ahead, not urgent)
|   |
|   +-- estate well below thresholds?
|       YES -> [WOL4] "Your estate is below the IHT threshold. Whole of life
|                       cover is not currently needed for IHT planning."

IHT Threshold Calculation:
    Single person:
        threshold = NRB (325,000)
        + RNRB (175,000) if main residence passes to direct descendants
        = 500,000 maximum

    Married couple (with transferable allowances):
        threshold = NRB * 2 (650,000)
        + RNRB * 2 (350,000) if main residence passes to direct descendants
        = 1,000,000 maximum

    RNRB taper: If estate > 2,000,000, RNRB reduces by 1 for every 2 above
    (tapers to zero at 2,350,000 for single, 2,700,000 for married)
```

### 13.2 Over 50s Plans

```
age >= 50 AND (health_status = "poor" OR cannot get standard cover)?
|
+-- Consider Over 50s plan: [WOL5]
    Guaranteed acceptance (no medical questions)
    Fixed premiums for life
    Cover amount limited (typically 2,000-25,000)
    Premiums may exceed payout if policyholder lives long
    12-month moratorium period (no payout in first year except accidental death)

    WARN: [WOL-W1] "Over 50s plans offer guaranteed acceptance but cover is
                     limited and premiums can exceed the payout over time.
                     Standard cover is better value if you qualify."
```

### 13.3 Whole of Life Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| WOL1 | WoL covers IHT | `info` | `wol.adequate` | "Your whole of life cover of {cover} helps offset your estimated inheritance tax liability of {iht}. Ensure the policy is written in trust." |
| WOL2 | IHT liability, no WoL | `medium` | `wol.iht_gap` | "Your estimated estate of {estate} exceeds the inheritance tax threshold of {threshold}. A whole of life policy of {iht} written in trust would cover the potential 40% tax liability, ensuring your beneficiaries receive the full estate value." |
| WOL3 | Near threshold | `info` | `wol.near_threshold` | "Your estate is approaching the inheritance tax threshold. Consider planning ahead with a whole of life policy, particularly if your estate is expected to grow." |
| WOL4 | Below threshold | `info` | `wol.below_threshold` | "Your estate is below the inheritance tax threshold. Whole of life cover is not currently needed for inheritance tax planning." |
| WOL5 | Over 50s plan | `low` | `wol.over_50s` | "An over 50s plan provides guaranteed acceptance with no medical questions. Cover is limited (typically up to 25,000) and designed for funeral costs and small legacies." |
| WOL-W1 | Over 50s warning | `warn` | `wol.over_50s_warning` | "Over 50s plans can result in total premiums exceeding the payout if you live beyond your life expectancy. Standard underwritten cover offers better value if your health allows." |

---

## 14. Phase 12: Policy Optimisation

**Service:** `PolicyOptimisationService`

### 14.1 Policy Review Decision Tree

```
total_annual_premiums > gross_annual_income * 0.10?
    YES -> [PO1] "Protection premiums exceed 10% of income..."
           CRITICAL affordability concern

total_annual_premiums > gross_annual_income * 0.07?
    YES -> [PO2] "Protection premiums are {percent}% of income..."
           WARNING: approaching affordability limit

total_annual_premiums > gross_annual_income * 0.05?
    YES -> [PO3] "Protection premiums are {percent}% of income..."
           INFO: within typical range but worth reviewing

Duplicate Cover Check:
|
+-- Multiple policies covering same risk with overlapping terms?
|   YES -> [PO4] "You have {count} policies covering similar risks..."
|          Recommendation: consolidate for potential savings
|
+-- Life and CI combined policies alongside standalone CI?
    YES -> [PO5] "You have both combined and standalone critical illness cover..."
           May be paying more than needed

Policy Age Check:
|
+-- Any policy older than 10 years AND renewable?
    YES -> [PO6] "Your {provider} policy is {years} years old..."
           Older policies may have outdated terms/definitions
           BUT: do NOT cancel without replacement (pre-existing conditions)

Indexation Check:
|
+-- Policies WITHOUT indexation AND inflation > 3%?
    YES -> [PO7] "Your {provider} policy has no indexation..."
           Cover erodes in real terms over time

Premium Type Check:
|
+-- Reviewable premiums (not guaranteed)?
    YES -> [PO8] "Your {provider} policy has reviewable premiums..."
           Premiums can increase at insurer's discretion
```

### 14.2 Policy Optimisation Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| PO1 | Premiums > 10% income | `critical` | `optimisation.affordability_critical` | "Your protection premiums of {premiums}/year exceed 10% of your income. Review policies to identify potential savings without compromising essential cover." |
| PO2 | Premiums 7-10% income | `warn` | `optimisation.affordability_warning` | "Your protection premiums of {premiums}/year are {percent}% of income, approaching the affordability limit. Consider whether all policies are necessary." |
| PO3 | Premiums 5-7% income | `info` | `optimisation.affordability_info` | "Your protection premiums of {premiums}/year are {percent}% of income. This is within typical range but review periodically." |
| PO4 | Duplicate cover | `medium` | `optimisation.duplicate` | "You have {count} policies covering similar risks. Consolidation may reduce total premiums without reducing cover." |
| PO5 | Combined + standalone CI | `info` | `optimisation.combined_standalone` | "You have both combined life/critical illness and standalone critical illness cover. Review whether both are needed." |
| PO6 | Old policy | `info` | `optimisation.old_policy` | "Your {provider} policy is {years} years old. Newer policies may have updated condition definitions and better terms. Never cancel existing cover before replacement is in force." |
| PO7 | No indexation | `warn` | `optimisation.no_indexation` | "Your {provider} policy has no indexation. At current inflation, the real value of your {cover} cover will reduce significantly over the remaining {years}-year term." |
| PO8 | Reviewable premiums | `warn` | `optimisation.reviewable_premiums` | "Your {provider} policy has reviewable premiums. The insurer can increase premiums at review dates. Consider switching to a guaranteed premium policy." |

---

## 15. Phase 13: Trust & Beneficiary Review

**Service:** `TrustBeneficiaryReviewService`

### 15.1 Trust Assessment Decision Tree

```
policies_not_in_trust > 0?
|
+-- YES ->
|   |
|   +-- total_estate > NRB (325,000)?
|   |   YES -> [TR1] "You have {count} policies not written in trust.
|   |                  Without trust placement, payouts form part of your
|   |                  estate and may be subject to 40% inheritance tax."
|   |          Priority: HIGH
|   |
|   +-- total_estate <= NRB?
|       YES -> [TR2] "Consider placing policies in trust even though your
|                      estate is below the IHT threshold. Trust placement
|                      avoids probate delays (6-12 months) and ensures
|                      swift payout to beneficiaries."
|              Priority: MEDIUM
|
+-- NO (all in trust) ->
    -> [TR3] "All your policies are written in trust."
       Priority: INFO

Trust Types:
    Absolute/Bare Trust:
        - Beneficiaries have immediate right to proceeds
        - Cannot be changed once set up
        - Simple and appropriate for most families
        - Suitable for life policies and CI

    Flexible/Discretionary Trust:
        - Trustees decide how to distribute proceeds
        - Can change beneficiaries
        - More control, more complex
        - Suitable for larger estates or complex family situations

    Split Trust (for combined Life + CI):
        - Life element in trust for beneficiaries
        - CI element paid to policyholder (for their medical needs)
        - ESSENTIAL for combined policies

Beneficiary Review:
|
+-- Any policy with no named beneficiary?
|   YES -> [TR4] "Your {provider} policy has no named beneficiary..."
|
+-- Marital status changed since policy inception?
|   YES -> [TR5] "Your marital status has changed since your {provider}
|                  policy was set up. Review your beneficiaries..."
|
+-- Has dependants not listed as beneficiaries?
    YES -> [TR6] "Ensure all dependants are included as beneficiaries..."
```

### 15.2 Trust & Beneficiary Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| TR1 | Not in trust, IHT risk | `high` | `trust.not_in_trust_iht` | "You have {count} policy/policies not written in trust. Payouts could be subject to 40% inheritance tax and probate delays of 6-12 months. Trust placement is free with most insurers." |
| TR2 | Not in trust, no IHT risk | `medium` | `trust.not_in_trust_no_iht` | "Consider placing your policies in trust. Even without inheritance tax concerns, trust placement ensures your family receives payouts quickly, bypassing probate." |
| TR3 | All in trust | `info` | `trust.all_in_trust` | "All your policies are written in trust. Payouts will go directly to your named beneficiaries, bypassing probate." |
| TR4 | No beneficiary | `high` | `trust.no_beneficiary` | "Your {provider} policy has no named beneficiary. Without a named beneficiary, the payout goes to your estate and may be delayed by probate." |
| TR5 | Marital change | `medium` | `trust.marital_change` | "Your marital status has changed since your {provider} policy was set up. Review and update your beneficiaries to reflect your current wishes." |
| TR6 | Missing dependants | `medium` | `trust.missing_dependants` | "Ensure all your dependants are included as beneficiaries on your policies, especially any children born after the policy was written." |

---

## 16. Phase 14: Spouse/Partner Protection Coordination

**Service:** `SpouseProtectionCoordinationService`

### 16.1 Spouse Coordination Decision Tree

```
spouse_exists AND spouse_permission_granted?
|
+-- NO -> GATE: [SPC0] "Link your partner's account to coordinate protection cover."
|
+-- YES ->
    |
    STRATEGY 1: INCOME DEPENDENCY ANALYSIS
    |
    +-- Both partners earn income?
    |   YES ->
    |   |   user_income > spouse_income * 2?
    |   |       YES -> [SPC1] "Your income is significantly higher. Your partner's
    |   |                       cover should focus on replacing your income."
    |   |   spouse_income > user_income * 2?
    |   |       YES -> [SPC2] "Your partner's income is significantly higher.
    |   |                       Ensure THEIR cover is adequate."
    |   |   Similar incomes?
    |   |       YES -> [SPC3] "Both incomes are important. Each partner needs
    |   |                       independent cover."
    |
    +-- One partner does not earn?
        YES -> [SPC4] "Even without income, your partner provides valuable
                        childcare/household services. Consider cover for care costs."
        Estimated childcare replacement cost: 15,000 - 30,000/year

    STRATEGY 2: JOINT vs SEPARATE POLICIES
    |
    +-- Has joint life policy?
    |   YES -> [SPC5] "Joint life policies pay on first death only. After payout,
    |                   the surviving partner has no cover. Separate policies often
    |                   cost only 10-20% more but provide double the protection."
    |
    +-- No joint policy, both need cover?
        YES -> [SPC6] "Consider separate policies for each partner rather than
                        joint cover, for maximum flexibility."

    STRATEGY 3: CROSS-COVER GAPS
    |
    +-- User has cover, spouse has none?
    |   YES -> [SPC7] "Your partner has no protection cover. If they died or
    |                   became ill, the financial impact could be significant."
    |
    +-- Spouse has cover, user has none?
        YES -> [SPC8] "You have no protection cover but your partner does.
                        Ensure both partners are protected."
```

### 16.2 Spouse Coordination Message Reference

| # | Condition | Severity | Config Key | Message |
|---|-----------|----------|------------|---------|
| SPC0 | No spouse linked | `info` | `spouse.gate` | "Link your partner's account to coordinate protection cover and avoid gaps or duplication." |
| SPC1 | User higher earner | `high` | `spouse.user_higher_earner` | "Your income of {user_income} is significantly higher than your partner's {spouse_income}. Ensuring adequate cover on your life is the priority -- your partner's financial stability depends on it." |
| SPC2 | Spouse higher earner | `high` | `spouse.spouse_higher_earner` | "Your partner's income of {spouse_income} is significantly higher than yours. Ensure their protection cover is adequate, as your household depends more on their income." |
| SPC3 | Similar incomes | `medium` | `spouse.similar_incomes` | "Both incomes contribute significantly to household finances. Each partner should have independent life cover and income protection." |
| SPC4 | Non-earning spouse | `medium` | `spouse.non_earning` | "Even without income, your partner provides valuable childcare and household services worth an estimated {replacement_cost}/year. Consider life cover to fund replacement care." |
| SPC5 | Joint policy warning | `warn` | `spouse.joint_policy` | "Your joint life policy pays out on the first death only. The surviving partner is then left without cover and may be older/less healthy, making new cover expensive or unavailable." |
| SPC6 | Separate policies rec | `medium` | `spouse.separate_policies` | "Consider separate life policies for each partner. Often only 10-20% more expensive than a joint policy but provides cover for both deaths." |
| SPC7 | Spouse no cover | `high` | `spouse.no_cover` | "Your partner has no protection cover. If they died or became seriously ill, you would need to fund childcare, household costs, and emotional recovery without financial support." |
| SPC8 | User no cover | `high` | `spouse.user_no_cover` | "You have no protection cover but your partner does. Both partners should have appropriate cover to protect the household." |

---

## 17. Phase 15: Life Event Impact Assessment

**Service:** `LifeEventProtectionImpactService`

### 17.1 Life Event Decision Tree

```
For each active life event:
|
+-- type = "new_baby"
|   |-- action: ELEVATE life cover, CI, IP priorities
|   |-- Sub-actions:
|   |   +-- increase_life_cover (new dependant)
|   |   +-- review_ci_cover (child CI usually free with parent policy)
|   |   +-- review_ip (higher expenditure)
|   |   +-- consider_fib (new reason for regular income on death)
|   |   +-- update_beneficiaries (include new child)
|   |   +-- review_will
|   |-- Message: [LE-P1]
|
+-- type = "marriage" / "civil_partnership"
|   |-- action: TRIGGER beneficiary review, spouse coordination
|   |-- Sub-actions:
|   |   +-- update_beneficiaries
|   |   +-- review_joint_vs_separate
|   |   +-- check_spouse_cover
|   |   +-- consider_relevant_life (if company director)
|   |-- Message: [LE-P2]
|
+-- type = "divorce"
|   |-- action: TRIGGER policy review
|   |-- Sub-actions:
|   |   +-- update_beneficiaries (URGENT: remove ex-spouse if needed)
|   |   +-- review_joint_policies (may need splitting)
|   |   +-- reassess_cover_needs (single income now)
|   |   +-- update_trusts
|   |-- Message: [LE-P3]
|
+-- type = "buying_a_home" / "mortgage"
|   |-- action: TRIGGER mortgage protection assessment
|   |-- Sub-actions:
|   |   +-- add_mortgage_protection
|   |   +-- review_life_cover_total
|   |   +-- consider_ci_for_mortgage
|   |-- Message: [LE-P4]
|
+-- type = "redundancy"
|   |-- action: CRITICAL review
|   |-- Sub-actions:
|   |   +-- check_employer_benefits_lost (death in service, group IP, PMI)
|   |   +-- review_personal_cover_adequacy
|   |   +-- check_ip_still_in_force
|   |-- Message: [LE-P5]
|
+-- type = "career_change"
|   |-- action: TRIGGER occupation class review
|   |-- Sub-actions:
|   |   +-- reassess_ip_occupation_class
|   |   +-- review_employer_benefits (new employer may differ)
|   |   +-- check_death_in_service (new employer)
|   |-- Message: [LE-P6]
|
+-- type = "serious_illness"
|   |-- action: TRIGGER claims review
|   |-- Sub-actions:
|   |   +-- check_ci_claim_eligibility
|   |   +-- check_ip_claim_eligibility
|   |   +-- review_future_insurability
|   |   +-- check_waiver_of_premium
|   |-- Message: [LE-P7]
|
+-- type = "death_of_partner"
|   |-- action: CRITICAL reassessment
|   |-- Sub-actions:
|   |   +-- claim_life_policies (partner's policies)
|   |   +-- reassess_own_cover_needs (single parent?)
|   |   +-- claim_bereavement_support_payment
|   |   +-- review_joint_policies
|   |-- Message: [LE-P8]
|
+-- type = "child_turning_18"
|   |-- action: TRIGGER dependency review
|   |-- Sub-actions:
|   |   +-- reassess_dependent_count
|   |   +-- review_cover_amount (may reduce)
|   |   +-- review_fib_term
|   |-- Message: [LE-P9]
|
+-- type = "retirement"
|   |-- action: TRIGGER protection wind-down
|   |-- Sub-actions:
|   |   +-- review_term_policies_ending
|   |   +-- consider_whole_of_life_for_iht
|   |   +-- cancel_ip (no longer needed)
|   |   +-- review_pmi_continuation
|   |-- Message: [LE-P10]
```

### 17.2 Life Event Protection Message Reference

| # | Event | Config Key | Message |
|---|-------|------------|---------|
| LE-P1 | new_baby | `life_event.new_baby` | "A new baby means an additional dependant relying on your income. Review your life cover (increase by at least {education_cost} for education funding), and ensure income protection is adequate for higher household costs." |
| LE-P2 | marriage | `life_event.marriage` | "Marriage means your partner may now depend on your income. Update beneficiaries on all policies, review whether joint or separate cover is more appropriate, and consider spouse coordination." |
| LE-P3 | divorce | `life_event.divorce` | "Following divorce, urgently review all policy beneficiaries. Joint policies may need splitting. Your protection needs change significantly as a single-income household." |
| LE-P4 | mortgage | `life_event.mortgage` | "A new mortgage of {amount} creates a significant debt protection need. Add mortgage protection cover to ensure the mortgage can be repaid if you die or become critically ill." |
| LE-P5 | redundancy | `life_event.redundancy` | "Redundancy means losing employer benefits immediately: death in service, group income protection, and private medical insurance. Review your personal cover urgently to fill these gaps." |
| LE-P6 | career_change | `life_event.career_change` | "A career change may affect your income protection occupation class and employer benefits. Review your new employer's benefits package and update your personal cover accordingly." |
| LE-P7 | serious_illness | `life_event.serious_illness` | "Check whether your conditions qualify for a critical illness or income protection claim. Contact your policy providers. Note: making a claim does not affect other policies." |
| LE-P8 | death_of_partner | `life_event.death_of_partner` | "Claim on your partner's life policies. Reassess your own cover needs as a single parent or sole earner. You may be eligible for Bereavement Support Payment of up to {amount}." |
| LE-P9 | child_turning_18 | `life_event.child_turning_18` | "Your child turning 18 may reduce your dependant count. Review whether your current cover level is still needed -- you may be able to reduce premiums." |
| LE-P10 | retirement | `life_event.retirement` | "In retirement, term life cover and income protection are typically no longer needed. Consider whole of life cover for inheritance tax planning if your estate exceeds {threshold}." |

---

## 18. Phase 16: Priority Ordering & Conflict Resolution

**Service:** `ProtectionPriorityService`

### 18.1 Recommended Priority Order

Protection products should be prioritised in this order (highest first):

```
PRIORITY 1 (Critical): Income Protection
    Rationale: Your ability to earn is your greatest financial asset.
    Without income, all other financial plans collapse.
    Exception: Skip if retired or no earned income.

PRIORITY 2 (Critical): Life Insurance (if dependants or significant debt)
    Rationale: Dependants need financial security if you die.
    Exception: Lower priority if no dependants and minimal debt.

PRIORITY 3 (High): Critical Illness Cover
    Rationale: 1 in 2 people diagnosed with serious illness in lifetime.
    A diagnosis can devastate finances even if you survive.

PRIORITY 4 (High): Mortgage Protection (if homeowner with mortgage)
    Rationale: Protects the family home specifically.
    May be covered by general life cover -- check for gaps.

PRIORITY 5 (Medium): Family Income Benefit (if dependants)
    Rationale: Cost-effective alternative or complement to level term.

PRIORITY 6 (Medium): Business Protection (if applicable)
    Rationale: Protects the business and business partners.
    Relevant Life Policy can be highly tax-efficient.

PRIORITY 7 (Low): Private Medical Insurance
    Rationale: NHS provides free healthcare; PMI is for convenience.
    Only recommended after core cover is in place.

PRIORITY 8 (Low): Whole of Life / IHT Planning
    Rationale: Long-term estate planning, not urgent protection.
    Exception: Elevated if estate significantly exceeds IHT threshold.
```

### 18.2 Protection vs Investment/Savings Priority

```
Protection should take priority over investment when:
|
+-- has_dependants AND total_life_cover < recommended * 0.5?
|   YES -> Protection BEFORE investment
|   "Adequate protection is a prerequisite for investment."
|
+-- no_ip_cover AND employment_income > 0 AND NOT retired?
|   YES -> IP BEFORE investment
|   "Without income protection, a period of illness could
|    force you to liquidate investments at the worst time."
|
+-- has_mortgage AND no_mortgage_cover?
    YES -> Mortgage protection BEFORE investment
    "Protecting the family home takes priority over growing wealth."

Protection can run alongside investment when:
|
+-- Core cover (life + IP) is at least 75% of recommended?
    YES -> Both protection and investment recommendations active
    "Your core protection is mostly in place. Continue building
     cover while also investing for the future."
```

### 18.3 Conflict Resolution

```
CONFLICT 1: AFFORDABILITY
|
+-- Total recommended premiums > 10% of gross income?
    YES -> Triage by priority order
    |-- Fund Priority 1-3 first
    |-- Defer lower priorities with note: [CR1]
    |-- Consider cheaper alternatives (FIB vs level term, longer deferred periods)

CONFLICT 2: COMBINED vs STANDALONE
|
+-- Both life cover gap AND CI gap?
    YES -> If affordable: recommend standalone
           If constrained: recommend combined with note: [CR2]

CONFLICT 3: EMPLOYER COVER RELIANCE
|
+-- Personal recommendations offset by employer cover?
    YES -> Show effective cover but warn about portability: [CR3]

CONFLICT 4: OVER-INSURANCE
|
+-- Total cover across all policies > 150% of calculated need?
    YES -> Flag potential over-insurance: [CR4]
```

### 18.4 Conflict Resolution Message Reference

| # | Conflict | Config Key | Message |
|---|---------|------------|---------|
| CR1 | Affordability constraint | `conflict.affordability` | "Recommended premiums exceed affordable levels. Prioritised essential cover first. Lower-priority cover can be added as budget allows." |
| CR2 | Combined vs standalone | `conflict.combined_vs_standalone` | "Budget constraints favour combined life and critical illness cover. This pays out once only -- if you claim for critical illness, life cover ends." |
| CR3 | Employer reliance | `conflict.employer_reliance` | "Your protection plan includes employer benefits. Remember these are not portable -- review personal cover if you change employment." |
| CR4 | Over-insurance | `conflict.over_insurance` | "Your total cover exceeds the calculated need by {percent}%. Review whether all policies are still necessary -- you may be paying unnecessary premiums." |

---

## 19. Phase 17: Output Formatting

**Service:** `ProtectionOutputFormatter`

### Priority Sorting

| Label | Numeric | Typical Sources |
|-------|---------|----------------|
| `critical` | 1 | No IP (employed), no life cover (with dependants), self-employed no IP |
| `high` | 2 | Significant life gap, CI gap with commitments, mortgage unprotected |
| `medium` | 3 | Moderate gaps, FIB, trust placement, business protection |
| `low` | 4 | PMI, policy optimisation, over 50s plans |
| `info` | 5 | Adequate cover confirmation, life event notes, employer benefit notes |

### Output Fields Per Recommendation

| Field | Description |
|-------|-------------|
| `uuid` | Unique identifier |
| `module` | Always "protection" |
| `category` | life_cover, critical_illness, income_protection, fib, mortgage, pmi, business, wol, trust, optimisation, employer, spouse |
| `product_type` | level_term, decreasing_term, whole_of_life, ci_standalone, ci_combined, ip, fib, pmi, rlp, key_person, shareholder, over_50s |
| `headline` | Short action title |
| `explanation` | Why this recommendation exists |
| `personal_context` | Personalised with user's numbers |
| `cover_amount` | Recommended sum assured or annual benefit |
| `cover_term` | Recommended policy term in years |
| `estimated_monthly_premium` | Indicative premium |
| `priority_label` | critical / high / medium / low / info |
| `priority_numeric` | 1-5 |
| `status` | active, blocked, deferred |
| `is_blocked` | Boolean |
| `blocked_reason` | Why blocked |
| `notes` | Array of advisory notes |
| `linked_life_event_id` | Related life event |
| `employer_offset` | Amount offset by employer benefits |
| `employer_warning` | Portability warning if employer benefits used |

---

## 20. Product Type Reference

### 20.1 Life Insurance Products

| Product | Cover Type | Payout | Term | Who Needs It | Tax Treatment |
|---------|-----------|--------|------|-------------|---------------|
| Level Term | Fixed sum | Lump sum on death | Fixed (10-40 years) | Anyone with dependants or debts | Payout tax-free (if in trust or to spouse) |
| Decreasing Term | Reducing sum | Lump sum on death | Fixed (matches mortgage) | Mortgage holders (repayment) | Payout tax-free |
| Whole of Life | Fixed sum (or with-profits) | Lump sum on death | Lifetime | IHT planning, guaranteed legacy | Payout tax-free if in trust |
| Family Income Benefit | Regular income | Monthly/annual payments | Fixed (until end of term) | Families needing income replacement | Tax-free if in trust |
| Over 50s | Fixed sum (small) | Lump sum on death | Lifetime | Over 50s who cannot get standard cover | Payout tax-free |
| Relevant Life | Fixed sum | Lump sum on death | Fixed or whole of life | Company directors/employees | Premiums: corp tax deductible, not P11D. Payout: tax-free |
| Joint Life (First Death) | Fixed sum | Lump sum on first death | Fixed | Couples | Only pays once |
| Joint Life (Second Death) | Fixed sum | Lump sum on second death | Lifetime | IHT planning | Pays when both have died |

### 20.2 Health, Income & Disability Protection Products

| Product | Cover Type | Payout | Term | Who Needs It | Tax Treatment |
|---------|-----------|--------|------|-------------|---------------|
| Critical Illness (Standalone) | Lump sum on diagnosis | One-off lump sum | Fixed | Anyone with financial commitments | Payout tax-free |
| Critical Illness (Combined) | Life + CI combined | One-off (pays on death OR CI, not both) | Fixed | Budget-conscious with both needs | Payout tax-free |
| Income Protection (Long-term) | Income replacement | Monthly payments | To retirement age | Anyone with earned income | Individual: tax-free. Employer-paid: taxable as income |
| Income Protection (Short-term) | Income replacement | Monthly payments | 1-2 years | Supplement to employer sick pay | Same as long-term |
| Disability Income Insurance | Income replacement for permanent disability | Monthly payments | To retirement age or lifetime | Those wanting cover specifically for total permanent disability | Individual: tax-free. Same rules as income protection |
| Total Permanent Disability (TPD) | Lump sum on permanent disability | One-off lump sum | Fixed | Anyone at risk of disabling injury/illness (often added to life cover) | Payout tax-free |
| Private Medical Insurance | Healthcare access | Pays medical bills | Annual (renewable) | Those wanting private healthcare | Individual: no tax relief. Employer: P11D benefit |
| Personal Accident | Accident cover only | Lump sum or income | Fixed | Higher-risk occupations | Payout tax-free |

**Disability Cover Notes:**
- **Disability Income Insurance** is functionally similar to long-term income protection but may use different incapacity definitions (typically "unable to perform ANY occupation" rather than "own occupation")
- **Total Permanent Disability (TPD)** is often sold as an add-on to life insurance policies rather than standalone. It pays a lump sum if the insured is permanently unable to work due to illness or injury
- TPD definitions vary: some require inability to perform "any occupation suited to education and experience", others require inability to perform "any occupation at all"
- UK market: TPD is less common as a standalone product than in Australia/US markets. Most UK needs are better served by Income Protection (own-occupation definition) or Critical Illness Cover
- **Group Disability Insurance**: Employer-provided disability cover, often part of a group income protection scheme with "activities of daily living" or "own occupation" definitions

### 20.3 Business Protection Products

| Product | Cover Type | Payout | Term | Who Needs It | Tax Treatment |
|---------|-----------|--------|------|-------------|---------------|
| Key Person (Life) | Covers business loss | Lump sum to company | Fixed | Businesses with key employees | Premiums: corp tax deductible (if for profit loss). Payout: taxable as trading receipt |
| Key Person (CI) | Covers business disruption | Lump sum to company | Fixed | Businesses with key employees | Same as Key Person Life |
| Shareholder Protection | Funds share buyback | Lump sum to surviving shareholders | Fixed or WoL | Companies with multiple shareholders | Premiums: NOT corp tax deductible. Payout: tax-free (own policy on co-shareholder) |
| Partnership Protection | Funds partnership buyout | Lump sum to surviving partners | Fixed | Partnerships | Similar to shareholder protection |
| Business Overhead | Covers fixed costs | Monthly payments | 1-2 years typically | Self-employed, small businesses | Premiums: tax deductible. Benefit: taxable as income |

---

## 21. UK-Specific Rules & Thresholds

### 21.1 Trust Arrangements

```
Why place policies in trust:
    1. Avoids IHT on policy payout (not part of estate)
    2. Avoids probate delays (6-12 months typically)
    3. Ensures swift payout to named beneficiaries
    4. Most insurers offer trust forms FREE of charge

Types:
    Absolute/Bare Trust: Irrevocable, beneficiaries fixed
    Flexible Trust: Beneficiaries can be changed
    Discretionary Trust: Trustees choose distribution
    Split Trust: For combined life/CI policies (CI to policyholder, life to beneficiaries)

When NOT to use a trust:
    - Single person with no dependants (estate goes to estate anyway)
    - When flexibility to change beneficiaries is critical (use flexible trust)
```

### 21.2 Income Protection Benefit Limits

```
Maximum insurable benefit:
    Individual IP: Typically 50-70% of pre-incapacity gross earnings
    Most insurers: 60% of gross earned income (employment + self-employment)
    Some insurers: 70% of first 50,000 + 50% of balance

Offsetting:
    Insurers may offset state benefits (ESA, PIP)
    Some policies pay without offset ("non-offsetting")
    Non-offsetting policies cost more but provide certainty

Benefit escalation:
    Level benefit: Fixed throughout claim
    RPI-linked: Increases with inflation during claim (costs more)
    Fixed escalation (e.g., 3%/year): Predictable increases
```

### 21.3 State Benefit Rates (2025/26)

| Benefit | Rate | Conditions |
|---------|------|-----------|
| Statutory Sick Pay (SSP) | 116.75/week (28 weeks max) | Employed, earning >= 123/week |
| ESA (Work-related activity) | 90.50/week | NI contributions, limited to 365 days |
| ESA (Support group) | 138.20/week | NI contributions, no time limit |
| Universal Credit (standard) | 311.68-393.45/month | Means-tested, savings < 16,000 |
| UC (LCWRA element) | 416.19/month | Limited capability assessment |
| PIP (Daily living standard) | 72.65/week | Functional ability, not means-tested |
| PIP (Daily living enhanced) | 108.55/week | Functional ability, not means-tested |
| PIP (Mobility standard) | 28.70/week | Functional ability |
| PIP (Mobility enhanced) | 75.75/week | Functional ability |
| Bereavement Support Payment (higher) | 3,500 + 350/month (18 months) | Spouse/CP under SPA, deceased had NI |
| Bereavement Support Payment (lower) | 2,500 + 100/month (18 months) | Spouse/CP over SPA or no children |

### 21.4 Insurance Premium Tax

```
Standard rate: 12% (applied to all general insurance premiums)
Higher rate: 20% (travel insurance, mechanical breakdown)

Life insurance: EXEMPT from IPT
Critical illness: EXEMPT from IPT
Income protection: EXEMPT from IPT
Private medical insurance: 12% IPT applies
```

### 21.5 Non-Disclosure & Misrepresentation

```
Consumer Insurance (Disclosure and Representations) Act 2012:
    - Consumers must take "reasonable care" not to misrepresent
    - Insurer cannot avoid claim for innocent non-disclosure
    - Deliberate/reckless misrepresentation: insurer can void policy
    - Careless misrepresentation: insurer can apply proportionate remedy
    - "Proportionate remedy": What would insurer have done if told truth?
      (higher premium -> reduce payout proportionally; would have declined -> void)

Practical impact:
    - Always answer health questions honestly
    - Declare ALL pre-existing conditions
    - Include conditions you consider "minor"
    - Failure to disclose = risk of claim being denied
```

### 21.6 Relevant Life Policy Rules

```
Eligibility:
    - Must be an employee or director of a limited company
    - Cannot be for sole traders or partners in a partnership
    - Policy must be set up and paid for by the employer (company)

Tax treatment:
    Premiums: Corporation tax deductible (revenue expense)
    NOT a P11D benefit in kind (unlike other employer-paid benefits)
    Payout: Tax-free to beneficiaries (written in trust)
    No pension lifetime allowance implications

Limits:
    Cover amount: Must be "reasonable" (HMRC benchmark: up to 20x salary)
    No fixed HMRC limit but excessive cover may be challenged
    Must be death benefit only (not CI or IP)

vs Personal Life Cover (comparison for higher-rate taxpayer earning 80,000):
    Personal premium of 50/month:
        Paid from net income after 40% tax + 2% NI = 50 costs 83.33 gross
    RLP premium of 50/month:
        Paid by company, corp tax relief at 25% = effective cost 37.50
    Saving: 45.83/month (55%)
```

---

## 22. Thresholds & Constants Reference

**CRITICAL: `TaxConfigService` is the single source of truth for ALL rates, thresholds, and constants used in this engine.** No values should be hardcoded in services, controllers, or components. State benefit rates, premium estimation factors, and IHT values must all be fetched from `TaxConfigService` at runtime. Where values relate to the user (e.g., income, age, occupation), they must always come from actual user data — never defaults.

### 22.1 Cover Calculation Constants

| Threshold | Value | Source | Purpose |
|-----------|-------|--------|---------|
| Income replacement (IP max) | 60% of gross | `TaxConfigService::get('protection.ip_max_benefit_percent')` | IP benefit cap |
| Income multiplier (young family) | 15x | `TaxConfigService::get('protection.income_multipliers.young_family')` | Multiplier method |
| Income multiplier (peak family) | 12x | `TaxConfigService::get('protection.income_multipliers.peak_family')` | Multiplier method |
| Income multiplier (no dependants) | 5x | `TaxConfigService::get('protection.income_multipliers.no_dependants')` | Multiplier method |
| Income multiplier (pre-retirement) | 8x | `TaxConfigService::get('protection.income_multipliers.pre_retirement')` | Multiplier method |
| Income multiplier (approaching) | 5x | `TaxConfigService::get('protection.income_multipliers.approaching_retirement')` | Multiplier method |
| CI multiplier | 3x annual income | `TaxConfigService::get('protection.ci_multiplier')` | CI need calculation |
| Education cost per child/year | 9,000 | `TaxConfigService::get('protection.education_cost_per_year')` | Education funding |
| Education end age | 21 | `TaxConfigService::get('protection.education_end_age')` | Education funding term |
| Final expenses | 7,500 | `TaxConfigService::get('protection.final_expenses')` | Funeral/admin costs |

### 22.2 Affordability & Policy Thresholds

| Threshold | Value | Source | Purpose |
|-----------|-------|--------|---------|
| Affordability critical | > 10% of gross income | `TaxConfigService::get('protection.affordability.critical_percent')` | Premium affordability |
| Affordability warning | > 7% of gross income | `TaxConfigService::get('protection.affordability.warning_percent')` | Premium affordability |
| Affordability info | > 5% of gross income | `TaxConfigService::get('protection.affordability.info_percent')` | Premium affordability |
| Over-insurance threshold | > 150% of calculated need | `TaxConfigService::get('protection.over_insurance_percent')` | Over-insurance flag |
| Policy expiry warning | Within 24 months | `TaxConfigService::get('protection.policy_expiry_warning_months')` | Expiring policy alert |
| Death in service reliance | > 50% of total cover | `TaxConfigService::get('protection.dis_reliance_percent')` | Employer reliance warning |

### 22.3 Premium Estimation Factors

| Factor | Value | Source | Purpose |
|--------|-------|--------|---------|
| Smoker loading (life) | +50% | `TaxConfigService::get('protection.premium_factors.smoker_life')` | Smoker adjustment |
| Smoker loading (IP) | +30% | `TaxConfigService::get('protection.premium_factors.smoker_ip')` | Smoker adjustment |
| Age 40+ loading | +20% | `TaxConfigService::get('protection.premium_factors.age_40_plus')` | Age adjustment |
| Age 50+ loading | +50% | `TaxConfigService::get('protection.premium_factors.age_50_plus')` | Age adjustment |
| Decreasing term discount | 20% cheaper than level | `TaxConfigService::get('protection.premium_factors.decreasing_term_discount')` | Decreasing term pricing |
| FIB discount vs level term | 30-50% cheaper | `TaxConfigService::get('protection.premium_factors.fib_discount_range')` | FIB cost comparison |
| CI standalone vs life ratio | 2.5x more expensive | `TaxConfigService::get('protection.premium_factors.ci_to_life_ratio')` | CI premium estimate |

### 22.4 State Benefits (2025/26)

All state benefit rates served from `TaxConfigService::get('benefits')`. These change annually and must be updated via the seeder each tax year.

| Benefit | Value | Source | Purpose |
|---------|-------|--------|---------|
| SSP weekly rate | £116.75 | `TaxConfigService::get('benefits.ssp.weekly_rate')` | State benefit offset |
| SSP max duration | 28 weeks | `TaxConfigService::get('benefits.ssp.max_weeks')` | SSP duration |
| SSP earnings threshold | £123/week | `TaxConfigService::get('benefits.ssp.earnings_threshold')` | SSP eligibility |
| ESA (WRAG) | £90.50/week | `TaxConfigService::get('benefits.esa.wrag_rate')` | State benefit offset |
| ESA (Support group) | £138.20/week | `TaxConfigService::get('benefits.esa.support_rate')` | State benefit offset |
| UC standard (single 25+) | £393.45/month | `TaxConfigService::get('benefits.universal_credit.single_25_plus')` | UC reference |
| UC LCWRA element | £416.19/month | `TaxConfigService::get('benefits.universal_credit.lcwra')` | Disability element |
| PIP daily living (standard) | £72.65/week | `TaxConfigService::get('benefits.pip.daily_living_standard')` | Disability benefit |
| PIP daily living (enhanced) | £108.55/week | `TaxConfigService::get('benefits.pip.daily_living_enhanced')` | Disability benefit |
| PIP mobility (standard) | £28.70/week | `TaxConfigService::get('benefits.pip.mobility_standard')` | Disability benefit |
| PIP mobility (enhanced) | £75.75/week | `TaxConfigService::get('benefits.pip.mobility_enhanced')` | Disability benefit |
| Bereavement Support (higher) | £3,500 + £350/month x 18 | `TaxConfigService::get('benefits.bereavement_support.higher')` | State bereavement |
| Bereavement Support (lower) | £2,500 + £100/month x 18 | `TaxConfigService::get('benefits.bereavement_support.lower')` | State bereavement |

### 22.5 Tax & IHT Constants (from existing TaxConfigService)

| Constant | Value | Source | Purpose |
|----------|-------|--------|---------|
| IHT rate | 40% | `TaxConfigService::getInheritanceTax()['standard_rate']` | IHT liability calculation |
| NRB | £325,000 | `TaxConfigService::getInheritanceTax()['nil_rate_band']` | IHT threshold |
| RNRB | £175,000 | `TaxConfigService::getInheritanceTax()['residence_nil_rate_band']` | IHT threshold (residence) |
| RNRB taper start | £2,000,000 | `TaxConfigService::getInheritanceTax()['rnrb_taper_threshold']` | RNRB tapering |
| IPT standard rate | 12% | `TaxConfigService::get('protection.ipt.standard_rate')` | PMI tax |
| IPT life/CI/IP | 0% (exempt) | `TaxConfigService::get('protection.ipt.life_exempt')` | Protection products exempt |

### 22.6 Business Protection Constants

| Constant | Value | Source | Purpose |
|----------|-------|--------|---------|
| RLP max cover (HMRC benchmark) | 20x salary | `TaxConfigService::get('protection.rlp_max_multiple')` | RLP reasonableness |
| Non-earning spouse care cost | £15,000-£30,000/year | `TaxConfigService::get('protection.replacement_costs.spouse_care')` | Replacement care |
| Childcare replacement cost | £15,000-£30,000/year | `TaxConfigService::get('protection.replacement_costs.childcare')` | Childcare costs |

### 22.7 Growth Rate for Human Capital Calculation

| Data Point | Source | Rule |
|------------|--------|------|
| Growth rate for human capital / self-insurance | User's risk profile via `AssumptionsService` | ALWAYS use user's risk level — never a hardcoded default like 4.7%. `AssumptionsService` resolves growth rate from the user's risk profile. |
| Salary growth | `TaxConfigService::getAssumptions()['salary_growth']` | For income projection over cover term |
| Inflation | `TaxConfigService::getAssumptions()['inflation']` | For real-value calculations |

---

## 23. Config Message Key Reference

Complete index of all protection message keys.

### Readiness Messages (`readiness.*`)

| Key | Severity | Message |
|-----|----------|---------|
| `readiness.block.date_of_birth` | block | "Your date of birth is needed to calculate appropriate cover terms and premium estimates." |
| `readiness.block.income` | block | "Your income details are needed to calculate life cover requirements and income protection benefits." |
| `readiness.block.marital_status` | block | "Your marital status is needed to assess joint cover options and spouse protection needs." |
| `readiness.warn.expenditure` | warn | "Your monthly expenditure is needed to calculate how much cover your family would need to maintain their lifestyle." |
| `readiness.warn.employment_status` | warn | "Your employment status determines employer benefits, occupation class, and state benefit entitlements." |
| `readiness.info.dependants` | info | "Add your dependants to assess life cover needs. Without dependants, income protection becomes the priority." |
| `readiness.warn.dependant_ages` | warn | "Adding your dependants' ages helps calculate education funding needs and appropriate cover terms." |
| `readiness.info.debts` | info | "If you have a mortgage or debts, add them to assess whether your cover protects against these liabilities." |
| `readiness.warn.missing_policies` | warn | "Add your existing insurance policies so we can identify gaps rather than recommending duplicate cover." |
| `readiness.info.no_policies_confirmed` | info | "You've confirmed you have no existing policies. Recommendations will be based on your full protection need." |
| `readiness.warn.employer_benefits` | warn | "Check if your employer provides death in service, group income protection, or group critical illness cover." |
| `readiness.info.occupation` | info | "Your occupation determines your income protection occupation class and premium estimates." |
| `readiness.info.smoker_status` | info | "Your smoking status significantly affects premium estimates. Non-smokers typically pay 40-50% less." |
| `readiness.info.health_status` | info | "Your health status helps assess underwriting likelihood and potential policy exclusions." |
| `readiness.info.spouse_link` | info | "Link your partner's account to coordinate protection cover and avoid gaps or duplication." |
| `readiness.info.life_events` | info | "Add any upcoming life events (new baby, marriage, career change) to receive tailored protection advice." |

### Life Stage Messages (`life_stage.*`)

| Key | Message |
|-----|---------|
| `life_stage.young_single` | "As a young adult without dependants, income protection is your priority. Your ability to earn is your greatest asset." |
| `life_stage.young_family` | "With a young family, comprehensive protection is essential. Life cover and income protection should be your immediate priorities." |
| `life_stage.established_single` | "Without dependants, focus on protecting your income. Consider critical illness cover to protect your lifestyle and mortgage." |
| `life_stage.peak_family` | "At peak earning years with dependants, ensure your protection keeps pace with your financial commitments." |
| `life_stage.peak_no_dependants` | "Without dependants, focus on income protection and critical illness cover to protect your lifestyle and commitments." |
| `life_stage.pre_retirement` | "As retirement approaches, review whether existing term policies will cover you long enough. Consider whole of life for estate planning." |
| `life_stage.approaching_retirement` | "With retirement approaching, most term policies will end soon. Focus on estate planning protection needs." |
| `life_stage.retired` | "In retirement, protection focus shifts to estate planning. Whole of life cover can help with inheritance tax planning." |

### Life Cover Messages (`life_cover.*`)

| Key | Message |
|-----|---------|
| `life_cover.adequate` | "Your life cover of {cover} meets or exceeds the recommended {need}. Review annually to ensure it keeps pace with your circumstances." |
| `life_cover.over_insured` | "Your life cover of {cover} significantly exceeds the recommended {need}. Review whether all policies are still needed." |
| `life_cover.minor_gap` | "Your life cover of {cover} is close to the recommended {need}. A small top-up of {gap} would close this gap." |
| `life_cover.moderate_gap` | "Your life cover of {cover} falls short of the recommended {need} by {gap}. Your family would face a significant shortfall." |
| `life_cover.significant_gap` | "Your life cover of {cover} covers less than half the recommended {need}. Shortfall of {gap} leaves your family financially vulnerable." |
| `life_cover.no_cover_dependants` | "You have no life cover and {count} dependant(s). Your family would have no financial safety net. Immediate action recommended." |
| `life_cover.no_cover_debt` | "You have no life cover. Outstanding debts of {debt} would pass to your estate, potentially burdening your family." |
| `life_cover.no_cover_no_need` | "You have no life cover, but with no dependants or significant debts, your immediate need is lower." |
| `life_cover.dis_reliance` | "Over half your life cover comes from your employer's death in service benefit. This cover is lost if you leave employment." |
| `life_cover.expiring_policy` | "Your {provider} policy expires in {months} months. Plan replacement cover before it lapses." |
| `life_cover.expired_policy` | "Your {provider} policy expired on {date}. You have less cover than you may think." |
| `life_cover.recommend.mortgage` | "Add {type} cover of {amount} over {term} years to protect your mortgage." |
| `life_cover.recommend.family` | "Add level term cover of {amount} over {term} years to replace your income for your family." |
| `life_cover.recommend.debt` | "Add level term cover of {amount} to clear outstanding debts and cover final expenses." |
| `life_cover.recommend.trust` | "Place your life policies in trust to avoid inheritance tax and probate delays." |

### Critical Illness Messages (`ci.*`)

| Key | Message |
|-----|---------|
| `ci.adequate` | "Your critical illness cover of {cover} meets the recommended {need}." |
| `ci.gap` | "Your critical illness cover of {cover} falls short of the recommended {need} by {gap}." |
| `ci.no_cover_commitments` | "You have no critical illness cover. One in two people are diagnosed with a serious illness in their lifetime." |
| `ci.no_cover_consider` | "Consider critical illness cover. Premiums are significantly lower when younger and healthier." |
| `ci.no_cover_older` | "Critical illness cover becomes more expensive with age. Consider self-insurance from savings." |
| `ci.recommend.combined` | "Combined life and critical illness cover of {amount}. More affordable but pays out only once." |
| `ci.recommend.standalone` | "Standalone critical illness cover of {amount}. Pays on diagnosis while life cover remains in force." |
| `ci.recommend.standalone_only` | "Add standalone critical illness cover of {amount} alongside your adequate life cover." |

### Income Protection Messages (`ip.*`)

| Key | Message |
|-----|---------|
| `ip.adequate` | "Your income protection covers {percent}% of your income. This meets the recommended 60% threshold." |
| `ip.partial_cover` | "Your income protection covers {percent}% of your income. Gap of {gap}/year." |
| `ip.no_cover_self_employed` | "As self-employed, you have no employer sick pay, no SSP, and no income protection." |
| `ip.no_cover_dependants` | "You have no income protection and {count} dependant(s) relying on your income." |
| `ip.no_cover_mortgage` | "You have no income protection and a mortgage of {amount}." |
| `ip.no_cover_default` | "You have no income protection. You would rely solely on state benefits and savings." |
| `ip.retired` | "Income protection is not applicable in retirement." |
| `ip.warn.any_occupation` | "Your income protection uses an 'any occupation' definition. Consider 'own occupation' cover." |
| `ip.warn.short_benefit` | "Your income protection benefit period is only {months} months. Consider extending to retirement age." |
| `ip.deferred.26_week` | "26-week deferred period reduces premiums. Your savings bridge the gap." |
| `ip.deferred.13_week` | "13-week deferred period balances cost with manageable gap." |
| `ip.deferred.4_week` | "4-week deferred period ensures quick income replacement. Higher premiums." |
| `ip.employer.any_occupation` | "Employer's group IP uses 'any occupation' definition. Consider personal 'own occupation' cover." |
| `ip.employer.low_cover` | "Employer's IP covers {percent}% of income. Consider personal top-up." |
| `ip.employer.long_deferred` | "Employer's IP has a {weeks}-week waiting period. Consider bridging policy." |

### Remaining Message Categories

| Category | Keys |
|----------|------|
| Family Income Benefit | `fib.budget_alternative`, `fib.regular_income`, `fib.complement`, `fib.not_recommended` |
| Mortgage Protection | `mortgage.not_applicable`, `mortgage.adequate`, `mortgage.under_covered`, `mortgage.covered_by_general`, `mortgage.no_cover`, `mortgage.recommend.decreasing`, `mortgage.recommend.level_io`, `mortgage.recommend.mixed`, `mortgage.warn.joint_single`, `mortgage.warn.joint_no_cover` |
| Private Medical Insurance | `pmi.employer_provided`, `pmi.higher_earner`, `pmi.family`, `pmi.over_65`, `pmi.lower_priority` |
| Business Protection | `business.relevant_life`, `business.shareholder`, `business.key_person`, `business.self_employed_ip`, `business.overhead`, `business.replace_with_rlp` |
| Employer Benefits | `employer.dis_generous`, `employer.dis_standard`, `employer.dis_minimal`, `employer.no_dis`, `employer.portability`, `employer.free_cover_limit` |
| Whole of Life | `wol.adequate`, `wol.iht_gap`, `wol.near_threshold`, `wol.below_threshold`, `wol.over_50s`, `wol.over_50s_warning` |
| Policy Optimisation | `optimisation.affordability_critical`, `optimisation.affordability_warning`, `optimisation.affordability_info`, `optimisation.duplicate`, `optimisation.combined_standalone`, `optimisation.old_policy`, `optimisation.no_indexation`, `optimisation.reviewable_premiums` |
| Trust & Beneficiary | `trust.not_in_trust_iht`, `trust.not_in_trust_no_iht`, `trust.all_in_trust`, `trust.no_beneficiary`, `trust.marital_change`, `trust.missing_dependants` |
| Spouse Coordination | `spouse.gate`, `spouse.user_higher_earner`, `spouse.spouse_higher_earner`, `spouse.similar_incomes`, `spouse.non_earning`, `spouse.joint_policy`, `spouse.separate_policies`, `spouse.no_cover`, `spouse.user_no_cover` |
| Life Events | `life_event.new_baby`, `life_event.marriage`, `life_event.divorce`, `life_event.mortgage`, `life_event.redundancy`, `life_event.career_change`, `life_event.serious_illness`, `life_event.death_of_partner`, `life_event.child_turning_18`, `life_event.retirement` |
| Conflict Resolution | `conflict.affordability`, `conflict.combined_vs_standalone`, `conflict.employer_reliance`, `conflict.over_insurance` |
