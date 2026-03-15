# Protection Decision Engine: Implementation Plan

> Gap analysis between `research-protection.md` and the current codebase, with phased implementation.
>
> **Date:** 2026-03-14 | **Based on:** 2 codebase audits (backend + frontend)

---

## Executive Summary

The Protection module is **more mature than expected** — it already has the DB-driven action definition pattern (`ProtectionActionDefinition` model + `ProtectionActionDefinitionService` with 8 seeded triggers), 6 services, 5 policy models, a comprehensive plan service, and scenario building. The gaps are primarily about:

1. **Centralising hardcoded values** — withdrawal rate (4.7%), final expenses (£7,500), education costs (£9,000), premium multipliers, and income replacement ratios are all hardcoded in services
2. **Missing data points** — no `joint_life` field, no employer benefits tracking, no state benefit integration in gap analysis
3. **Data readiness** — controller auto-creates ProtectionProfile instead of checking granular user data
4. **Score violation** — `AdequacyScorer` returns numeric scores displayed via `CoverageAdequacyGauge.vue` (violates CLAUDE.md Rule #13)
5. **Frontend duplication** — gap calculations duplicated between `GapAnalysis.vue` and `ProtectionOverviewCard.vue`
6. **Expanding action definitions** — only 8 triggers seeded; research doc identifies ~30+ trigger conditions

### What Exists vs What's Needed

| Area | Current State | Target State | Gap Size |
|------|--------------|-------------|----------|
| **Action definitions** | 8 seeded (ProtectionActionDefinition) | ~30+ covering all research phases | Enhancement |
| **Hardcoded values** | 4.7%, £7,500, £9,000, 60%, 3x all in services | All from TaxConfigService | Config migration |
| **Data readiness gate** | Auto-creates ProtectionProfile in controller | 14 granular checks, no assumptions | Moderate rewrite |
| **Employer benefits** | Not tracked | Death in service, group IP, group CI models | New feature |
| **State benefits** | Not integrated | SSP, ESA, UC, PIP offsets in gap analysis | Enhancement |
| **joint_life field** | Missing from all policy models | Boolean on LifeInsurancePolicy | Migration |
| **Adequacy scores** | Numeric 0-100 displayed in UI | Remove scores, use descriptive text | Fix violation |
| **Premium estimation** | Placeholder formulas (£0.50/£1k) | Configurable via TaxConfigService | Config migration |
| **Income multipliers** | Fixed in code (3x CI, 60% IP) | Configurable via TaxConfigService | Config migration |
| **Surplus income** | Recalculated in services | Use ResolvesIncome/ResolvesExpenditure traits | Small refactor |
| **Frontend duplication** | Gap calc in GapAnalysis.vue + ProtectionOverviewCard.vue | Single source from backend | Cleanup |
| **What Drives This** | Not implemented | Decision path display per recommendation | UI feature |
| **Disability policies** | Model exists, basic coverage | Add TPD, disability income assessment | Enhancement |

---

## Architecture: Extend Existing Pattern

The Protection module **already uses the correct architecture** — no rebuild needed, just expansion:

```
ProtectionActionDefinition (DB model) ← EXISTS — expand from 8 to ~30 triggers
        |
ProtectionActionDefinitionService     ← EXISTS — add new evaluator methods
        |
ProtectionAgent::analyze()            ← EXISTS — enhance analysis data
        |
ProtectionPlanService                 ← EXISTS — enhance with new data
        |
CoordinatingAgent                     ← EXISTS — already integrated
```

---

## Implementation Phases

### Phase 0: TaxConfigService Centralisation (Pre-requisite)
**Priority: CRITICAL — blocks everything else**
**Files modified: 3 | Files created: 0**

#### 0.1 Add Protection Config to TaxConfigService Seeder

**File:** `database/seeders/TaxConfigurationSeeder.php`

Add new `protection` section:
```php
'protection' => [
    'income_multipliers' => [
        'young_family' => 15,
        'peak_family' => 12,
        'no_dependants' => 5,
        'pre_retirement' => 8,
        'approaching_retirement' => 5,
    ],
    'ip_max_benefit_percent' => 0.60,
    'ci_multiplier' => 3,
    'education_cost_per_year' => 9000,
    'education_end_age' => 21,
    'final_expenses' => 7500,
    'affordability' => [
        'critical_percent' => 0.10,
        'warning_percent' => 0.07,
        'info_percent' => 0.05,
    ],
    'over_insurance_percent' => 1.50,
    'policy_expiry_warning_months' => 24,
    'dis_reliance_percent' => 0.50,
    'rlp_max_multiple' => 20,
    'replacement_costs' => [
        'spouse_care_min' => 15000,
        'spouse_care_max' => 30000,
        'childcare_min' => 15000,
        'childcare_max' => 30000,
    ],
    'premium_factors' => [
        'base_rate_per_thousand' => 0.50,
        'smoker_life' => 1.50,
        'smoker_ip' => 1.30,
        'age_40_plus' => 1.20,
        'age_50_plus' => 1.50,
        'decreasing_term_discount' => 0.80,
        'fib_discount' => 0.60,
        'ci_to_life_ratio' => 2.50,
        'ip_rate_percent' => 0.02,
    ],
    'withdrawal_rates' => [
        'human_capital' => 0.047,
        'scenario' => 0.03,
    ],
    'ipt' => [
        'standard_rate' => 0.12,
        'life_exempt' => true,
        'ci_exempt' => true,
        'ip_exempt' => true,
        'pmi_rate' => 0.12,
    ],
],
```

#### 0.2 Add State Benefit Rates to Seeder

Add under `benefits` section (if not already present):
```php
'benefits' => [
    'ssp' => ['weekly_rate' => 116.75, 'max_weeks' => 28, 'earnings_threshold' => 123],
    'esa' => ['wrag_rate' => 90.50, 'support_rate' => 138.20],
    'universal_credit' => ['single_25_plus' => 393.45, 'lcwra' => 416.19],
    'pip' => [
        'daily_living_standard' => 72.65, 'daily_living_enhanced' => 108.55,
        'mobility_standard' => 28.70, 'mobility_enhanced' => 75.75,
    ],
    'bereavement_support' => [
        'higher' => ['lump_sum' => 3500, 'monthly' => 350, 'months' => 18],
        'lower' => ['lump_sum' => 2500, 'monthly' => 100, 'months' => 18],
    ],
],
```

#### 0.3 Remove Hardcoded Values from Services

| File | Current | Replace With |
|------|---------|-------------|
| `CoverageGapAnalyzer.php` line 31 | `/ 0.047` (withdrawal rate) | Move to TaxConfigService as `protection.withdrawal_rates.human_capital` (0.047). This is a planning constant (sustainable withdrawal rate), NOT an investment return rate. Access: `$this->taxConfig->get('protection.withdrawal_rates.human_capital')`. Pass the configured rate into the method at the call site. |
| `CoverageGapAnalyzer.php` line 84 | `7500` (final expenses) | `$this->taxConfig->get('protection.final_expenses')` |
| `CoverageGapAnalyzer.php` line 67 | `9000` (education cost) | `$this->taxConfig->get('protection.education_cost_per_year')` |
| `CoverageGapAnalyzer.php` line 345 | `0.60` (IP max) | `$this->taxConfig->get('protection.ip_max_benefit_percent')` |
| `AdequacyScorer.php` line 66 | `3` (CI multiplier) | `$this->taxConfig->get('protection.ci_multiplier')` |
| `RecommendationEngine.php` line 164 | `0.50` (premium base) | `$this->taxConfig->get('protection.premium_factors.base_rate_per_thousand')` |
| `RecommendationEngine.php` line 170 | `1.5` (smoker loading) | `$this->taxConfig->get('protection.premium_factors.smoker_life')` |
| `RecommendationEngine.php` line 200 | `2.5` (CI ratio) | `$this->taxConfig->get('protection.premium_factors.ci_to_life_ratio')` |
| `RecommendationEngine.php` line 209 | `0.02` (IP rate) | `$this->taxConfig->get('protection.premium_factors.ip_rate_percent')` |
| `ScenarioBuilder.php` line 20 | `0.03` (withdrawal rate) | Move to TaxConfigService as `protection.withdrawal_rates.scenario` (0.03). This is a planning constant (sustainable withdrawal rate), NOT an investment return rate. Access: `$this->taxConfig->get('protection.withdrawal_rates.scenario')`. Pass the configured rate into the method at the call site. |

Add `TaxConfigService` constructor injection to `CoverageGapAnalyzer`, `RecommendationEngine`, and `ScenarioBuilder` (which currently have none or minimal DI). The Protection module should NOT depend on `AssumptionsService` — only `TaxConfigService`.

**Test updates required (BEFORE deployment):**
- `tests/Unit/Services/Protection/CoverageGapAnalyzerTest.php` — update constructor to mock TaxConfigService
- `tests/Unit/Services/Protection/RecommendationEngineTest.php` — update `new RecommendationEngine` to include TaxConfigService mock
- `tests/Unit/Services/Protection/ScenarioBuilderTest.php` — update `new ScenarioBuilder` to include TaxConfigService mock
These tests instantiate services directly. Adding constructor parameters without updating tests will cause immediate test failures.

#### 0.4 Add Income Resolution via Traits

**Files:** `CoverageGapAnalyzer.php`, `RecommendationEngine.php`

Add `ResolvesIncome` and `ResolvesExpenditure` traits. Replace manual User model income field summation with `$this->resolveGrossAnnualIncome($user)` and `$this->resolveMonthlyExpenditure($user)`.

> **Note:** There is no `IncomeService` or `income_profile` in the codebase. The traits are the correct mechanism for standardised income resolution.

---

### Phase 1: Data Readiness Gate & Score Fix
**Priority: HIGH**
**Files modified: 4 | Files created: 1**

#### 1.1 Create ProtectionDataReadinessService

**File:** `app/Services/Protection/ProtectionDataReadinessService.php`

Implements the 14-check gate from the amended research document (Section 3). Returns:
```php
[
    'can_proceed' => bool,
    'blocking' => [...],   // DOB, income, marital status
    'warnings' => [...],   // expenditure, employment, dependant ages, policies, employer benefits
    'info' => [...],       // occupation, smoker, health, spouse, life events, debts
]
```

**Blocking checks (3):** date_of_birth, income data, marital_status
**Warning checks (6):** expenditure, employment_status, dependant ages, policies, employer benefits, debts
**Info checks (5):** occupation, smoker_status, health_status, spouse link, life events

#### 1.2 Replace Auto-Create in Controller

**File:** `app/Http/Controllers/Api/ProtectionController.php`

Remove auto-creation of ProtectionProfile in `index()` (lines 61-72). Replace with readiness gate check. Return readiness data within the normal response envelope (all expected keys present but null when `can_proceed = false`).

**Vuex store update required:** Update `resources/js/store/modules/protection.js` to handle `success: false` from `analyseProtection`. Currently commits the raw response as analysis state — if `success: false`, components reading `state.analysis.data.gaps` get `undefined`. Add a guard:
```javascript
if (response.data?.success === false || response.data?.can_proceed === false) {
    commit('setAnalysis', { can_proceed: false, readiness: response.data.readiness });
    return;
}
```
Update `ProtectionProfileResource` to safely handle null profile.

#### 1.3 Remove Adequacy Scores from API Response

**CLAUDE.md Rule #13 violation:** The gauge component already shows descriptive text ('Adequate'/'Partial'/'Limited'/'Insufficient'), not numbers — no change needed there.

The actual Rule #13 violation is in **`ComprehensiveProtectionPlanService::generateExecutiveSummary()`** which puts `'overall': 75`, `'life': 80` etc. into the API response. Fix:
- Remove numeric score keys from the executive summary response
- Refactor `getRecommendedAction()` to use category strings ('Excellent'/'Good'/'Fair'/'Critical') instead of numeric thresholds
- Update the Vuex `adequacyScore` getter to return the rating string, not the numeric value

The backend `AdequacyScorer` can continue calculating the score internally for categorisation, but the numeric value must NOT be exposed in the API response or displayed in user-facing UI.

#### 1.4 Remove Frontend Gap Calculation Duplication

**Files:** `GapAnalysis.vue`, `ProtectionOverviewCard.vue`

Both components independently calculate gaps using inline JavaScript. The backend already calculates these via `CoverageGapAnalyzer`. Remove the frontend calculations and rely on the backend analysis data from the Vuex store:

```javascript
// BEFORE: computed gap in component
debtGap() { return Math.max(0, this.totalDebt - this.totalLifeCoverage) }

// AFTER: read from backend analysis
debtGap() { return this.analysis?.gaps?.gaps_by_category?.debt_protection_gap ?? 0 }
```

**IMPORTANT: Multiplier standardisation prerequisite.** Frontend uses 75% income replacement / 2x CI. Backend uses 60% income / 3x CI. Before removing frontend calculations, standardise multipliers in TaxConfigService (Phase 0.1 seeder already defines `ip_max_benefit_percent: 0.60` and `ci_multiplier: 3`). Document that switching from frontend values to backend values will change displayed numbers — this is intentional, not a bug.

---

### Phase 2: Employer Benefits & State Benefits
**Priority: HIGH — significant gap in accuracy**
**Files created: 2 | Files modified: 3**

#### 2.1 Create Employer Benefits Tracking

**Migration:** `add_employer_benefits_to_protection_profiles`

Add columns to `protection_profiles`:
```php
$table->decimal('death_in_service_multiple', 3, 1)->nullable(); // e.g., 4.0 = 4x salary
$table->decimal('group_ip_benefit_percent', 5, 2)->nullable(); // e.g., 75 = 75% of salary
$table->integer('group_ip_benefit_months')->nullable(); // duration of employer IP
$table->string('group_ip_definition', 50)->nullable(); // own_occupation, suited, any
$table->decimal('group_ci_amount', 15, 2)->nullable(); // employer CI sum assured
$table->boolean('has_employer_pmi')->default(false);
$table->string('employer_name')->nullable();
```

Update `ProtectionProfile::$fillable` and `$casts`.

#### 2.2 Integrate Employer Benefits into Gap Analysis

**File:** `app/Services/Protection/CoverageGapAnalyzer.php`

In `calculateTotalCoverage()`, include employer-provided cover:
```php
// Death in service = multiple × gross salary
// Use User.annual_employment_income directly — it's the primary source
// ProtectionProfile.annual_income is a fallback that may be stale
$salary = $user->annual_employment_income ?: $profile->annual_income;
$deathInService = $profile->death_in_service_multiple
    ? $profile->death_in_service_multiple * $salary
    : 0;
$lifeCoverage += $deathInService;

// Group IP
$groupIP = $profile->group_ip_benefit_percent
    ? ($profile->annual_income * $profile->group_ip_benefit_percent / 100) / 12
    : 0;
// ... add to IP coverage
```

Add employer reliance warning:
```php
if ($deathInService > 0 && ($deathInService / $totalLifeCover) > 0.50) {
    $warnings[] = 'Over half your life cover comes from death in service. This is lost if you leave employment.';
}
```

The reliance threshold (50%) comes from `TaxConfigService::get('protection.dis_reliance_percent')`.

#### 2.3 Integrate State Benefits into Income Protection Assessment

**File:** `app/Services/Protection/CoverageGapAnalyzer.php`

When calculating income protection gap, offset by state benefits the user would receive:
```php
$sspWeekly = $this->taxConfig->get('benefits.ssp.weekly_rate');
$sspMaxWeeks = $this->taxConfig->get('benefits.ssp.max_weeks');
$esaRate = $this->taxConfig->get('benefits.esa.support_rate');

// SSP is paid for a MAXIMUM of 28 weeks, not indefinitely
$totalSspEntitlement = $sspWeekly * $sspMaxWeeks; // £116.75 × 28 = £3,269

// Only available to EMPLOYED users earning >= threshold
if (!in_array($user->employment_status, ['employed', 'part_time'])) {
    $totalSspEntitlement = 0; // Self-employed, unemployed, retired get no SSP
}

// ESA — note as potential, not guaranteed (requires NI contributions)
// Do NOT deduct ESA as a firm offset — flag as "subject to eligibility"

// After SSP (ESA monthly equivalent for display)
$monthlyStateSupport_esa = ($esaRate * 52) / 12;

// IP need = income shortfall AFTER state benefits
$ipNeed = max(0, ($grossIncome * $ipMaxPercent) - $monthlyStateSupport);
```

**Rate conflict resolution:** The seeder uses £116.75/week (2025/26). Frontend components `GapAnalysis.vue` and `ProtectionOverviewCard.vue` hardcode £118.75. After this change, update frontend to read from the backend analysis response (which uses the TaxConfigService value) rather than hardcoding.

#### 2.4 Update Frontend Policy Form

**File:** `resources/js/components/Protection/PolicyFormModal.vue`

Add employer benefits section to the protection profile form (not the policy form — these are profile-level fields):
- Death in service multiple (number, e.g., 4x)
- Group income protection (percent of salary, duration, definition)
- Group critical illness (sum assured)
- Employer PMI (checkbox)

---

### Phase 3: Schema Enhancements & Evaluator Methods
**Priority: MEDIUM**
**Files created: 1 | Files modified: 3**

#### 3.1 Add joint_life to Life Insurance Policies

**Migration:** `add_joint_life_to_life_insurance_policies`

```php
$table->boolean('joint_life')->default(false)->after('in_trust');
```

Update `LifeInsurancePolicy::$fillable` and `$casts`.

Update `PolicyFormModal.vue` — add joint life checkbox in the life insurance section.

#### 3.2 Add Trigger Definitions for New Analyses

**File:** `database/seeders/ProtectionActionDefinitionSeeder.php`

Expand from 8 to ~25 triggers. Add:

**Employer Benefits:**
| Key | Trigger | Priority |
|-----|---------|----------|
| `dis_reliance_warning` | Death in service > 50% of total cover | high |
| `no_employer_benefits_recorded` | Employed but no employer benefits | medium |
| `group_ip_any_occupation` | Group IP uses 'any occupation' definition | medium |

**State Benefits:**
| Key | Trigger | Priority |
|-----|---------|----------|
| `ip_gap_after_state_benefits` | IP shortfall even after SSP/ESA offset | high |

**Life Insurance:**
| Key | Trigger | Priority |
|-----|---------|----------|
| `policy_not_in_trust` | Life policy with in_trust = false | medium |
| `policy_not_joint_married` | Married + life policy not joint_life | medium |
| `policy_expiring_soon` | Policy end_date within 24 months | high |
| `policy_expired` | Policy end_date in the past | critical |
| `mortgage_no_decreasing_term` | Has mortgage but no decreasing term | high |

**Income Protection:**
| Key | Trigger | Priority |
|-----|---------|----------|
| `self_employed_no_ip` | Self-employed with no IP | critical |
| `ip_any_occupation_definition` | IP uses 'any occupation' — recommend 'own occupation' | medium |
| `ip_short_benefit_period` | Benefit period < 24 months | medium |
| `ip_long_deferred_period` | Deferred period > 26 weeks with no employer sick pay | high |

**Critical Illness:**
| Key | Trigger | Priority |
|-----|---------|----------|
| `no_ci_with_mortgage` | Has mortgage, no CI cover | high |
| `ci_combined_risk` | Combined life+CI policy — only pays once | info |

**Children:**
| Key | Trigger | Priority |
|-----|---------|----------|
| `dependants_no_life_cover` | Has dependants but zero life cover | critical |
| `education_funding_gap` | Education funding shortfall | medium |

**Spouse:**
| Key | Trigger | Priority |
|-----|---------|----------|
| `non_earning_spouse_no_cover` | Spouse doesn't earn but no cover for care/childcare replacement | medium |

#### 3.3 Implement Evaluator Methods

**File:** `app/Services/Protection/ProtectionActionDefinitionService.php`

For EACH new trigger added to the seeder, a corresponding private evaluator method and match branch must be added. Without these, seeded triggers produce silent no-ops (hit `default => null` in the match block).

New match branches required (~17):
```php
match ($condition) {
    // ... existing 6 conditions ...
    'dis_reliance_warning' => $this->evaluateDisReliance($definition, $planData, $config),
    'no_employer_benefits_recorded' => $this->evaluateNoEmployerBenefits(...),
    'group_ip_any_occupation' => $this->evaluateGroupIpDefinition(...),
    'ip_gap_after_state_benefits' => $this->evaluateIpGapAfterState(...),
    'policy_not_in_trust' => $this->evaluatePolicyNotInTrust(...),
    'policy_not_joint_married' => $this->evaluatePolicyNotJoint(...),
    'policy_expiring_soon' => $this->evaluatePolicyExpiring(...),
    'policy_expired' => $this->evaluatePolicyExpired(...),
    'mortgage_no_decreasing_term' => $this->evaluateMortgageNoDecreasing(...),
    'self_employed_no_ip' => $this->evaluateSelfEmployedNoIp(...),
    'ip_any_occupation_definition' => $this->evaluateIpAnyOccupation(...),
    'ip_short_benefit_period' => $this->evaluateIpShortBenefit(...),
    'ip_long_deferred_period' => $this->evaluateIpLongDeferred(...),
    'no_ci_with_mortgage' => $this->evaluateNoCiWithMortgage(...),
    'ci_combined_risk' => $this->evaluateCiCombinedRisk(...),
    'dependants_no_life_cover' => $this->evaluateDependantsNoLifeCover(...),
    'education_funding_gap' => $this->evaluateEducationFundingGap(...),
    'non_earning_spouse_no_cover' => $this->evaluateNonEarningSpouse(...),
};
```

Each evaluator method must be unit tested.

**IMPORTANT:** Triggers that depend on Phase 2 data (employer benefits columns) must gracefully handle the columns not yet existing. Use optional chaining or null checks.

---

### Phase 4: Notification System
**Priority: LOW**
**Files created: 1 | Files modified: 1**

#### 4.1 Policy Expiry Alerts

`PolicyRenewalNotification` already exists. Verify it covers:
- Policies expiring within 24/12/3 months
- Uses `database` channel only (per project pattern)

If not already scheduled, add to existing `SendPolicyRenewalReminders` command or create `SendProtectionAlerts` command that also checks:
- Policy expired (past end_date)
- Annual protection review prompt (once per year)

---

## Dependency Graph

```
Phase 0 ──────────────────────────────────────────┐
(TaxConfigService + hardcode removal + traits)     |
    |                                               |
    v                                               |
Phase 1 ──────────────────────────────────────────┤
(Readiness gate + score fix + frontend cleanup)    |
    |                                               |
    v                                               |
Phase 2 ────────── Phase 3 ────────────────────────┤
(Employer + state)  (Schema + new triggers)        |
    |                   |                           |
    v                   v                           |
Phase 4 ──────────────────────────────────────────┘
(Notifications)
```

Phases 2 and 3 can run partially in parallel: Phase 3 schema work (3.1) and seeder work (3.2) can proceed alongside Phase 2, but Phase 3 evaluators for employer-benefit triggers (`dis_reliance_warning`, `no_employer_benefits_recorded`, `group_ip_any_occupation`) must not be deployed until Phase 2 migration has run.

---

## Files Created (New)

| File | Purpose |
|------|---------|
| `app/Services/Protection/ProtectionDataReadinessService.php` | 14-check data readiness gate |
| `database/migrations/xxx_add_employer_benefits_to_protection_profiles.php` | Employer benefits columns |
| `database/migrations/xxx_add_joint_life_to_life_insurance_policies.php` | joint_life boolean |

## Files Modified (Existing)

| File | Change |
|------|--------|
| `database/seeders/TaxConfigurationSeeder.php` | Add `protection.*` and `benefits.*` config sections |
| `app/Services/Protection/CoverageGapAnalyzer.php` | Remove hardcodes, add TaxConfigService DI, add ResolvesIncome/ResolvesExpenditure traits, integrate employer + state benefits |
| `app/Services/Protection/AdequacyScorer.php` | Remove hardcoded CI/IP multipliers, fetch from TaxConfigService |
| `app/Services/Protection/RecommendationEngine.php` | Remove hardcoded premium factors, add TaxConfigService DI, add ResolvesIncome trait |
| `app/Services/Protection/ScenarioBuilder.php` | Remove hardcoded withdrawal rate, use TaxConfigService |
| `app/Http/Controllers/Api/ProtectionController.php` | Remove auto-create profile, add readiness gate |
| `app/Agents/ProtectionAgent.php` | Add readiness gate call, include employer benefits + state benefits in analysis |
| `app/Models/ProtectionProfile.php` | Add employer benefit fields to $fillable and $casts |
| `app/Models/LifeInsurancePolicy.php` | Add joint_life to $fillable and $casts |
| `database/seeders/ProtectionActionDefinitionSeeder.php` | Expand from 8 to ~25 trigger definitions |
| `app/Services/Protection/ComprehensiveProtectionPlanService.php` | Remove numeric score keys from executive summary response, refactor `getRecommendedAction()` to use category strings (Rule #13 fix) |
| `app/Services/Protection/ProtectionActionDefinitionService.php` | Add ~17 new evaluator methods and match branches for new trigger definitions |
| `resources/js/store/modules/protection.js` | Add guard for `success: false` / `can_proceed: false` readiness gate responses |
| `resources/js/components/Protection/GapAnalysis.vue` | Remove frontend gap calculations, use backend data |
| `resources/js/components/Protection/ProtectionOverviewCard.vue` | Remove duplicated gap calculations, use backend data |
| `resources/js/components/Protection/PolicyFormModal.vue` | Add joint_life checkbox, add employer benefits section |

## What This Plan Does NOT Include

1. **Family Income Benefit (FIB) as separate policy model** — FIB is tracked as a life insurance policy with `life_policy_type = 'family_income_benefit'`. No separate model needed.
2. **Key person / shareholder protection** — Business protection is a separate concern. Only Relevant Life Policy is relevant to personal protection.
3. **Underwriting rules engine** — Condition-specific CI underwriting (pre-existing conditions, exclusions) is too complex for this phase.
4. **Premium comparison with market rates** — We estimate premiums for guidance, not for quotes. Actual quotes come from insurers.

## Testing Strategy

| Phase | Tests |
|-------|-------|
| Phase 0 | Run full test suite. Architecture test: no hardcoded protection values remain in services. |
| Phase 1 | Unit tests for 14 readiness checks. Verify CoverageAdequacyGauge renders text not numbers. |
| Phase 2 | Unit tests for employer benefit integration in gap analysis. Unit tests for state benefit offset. |
| Phase 3 | Migration tests. Seeder idempotency. Unit tests for new action definition evaluators. |
| Phase 4 | Feature test for notification scheduling. |
