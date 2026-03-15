# Retirement Decision Engine: Implementation Plan

> Gap analysis between `research-retirement.md` and the current codebase, with phased implementation.
>
> **Date:** 2026-03-14 | **Based on:** 2 codebase audits (backend + frontend/config)

---

## Executive Summary

The Retirement module is **the most feature-rich module** — 10 services, Monte Carlo simulations, decumulation planning, DB-driven action definitions (10 seeded triggers), and 21 Vue components. The gaps are primarily about **consistency and centralisation**, not missing features:

1. **Withdrawal rate inconsistency** — 4.7% in some services, 4.0% in others — 16+ locations across 7 files — must centralise
2. **Hardcoded constants** — accumulation/decumulation trigger (10 years), growth rates (5%), inflation (2.0% vs 2.5%), retirement age (67 vs 68), employer match threshold (5%)
3. **Data readiness gate** — returns error if profile missing, no granular checks
4. **Missing features** — salary sacrifice analysis with NMW/NLW floor check, auto-enrolment threshold checks
5. **Incomplete integration** — care costs fields exist but not wired, smoker/health on User not used for annuity enhancement

### What Exists vs What's Needed

| Area | Current State | Target State | Gap Size |
|------|--------------|-------------|----------|
| **Action definitions** | 10 seeded (RetirementActionDefinition) | Expand with salary sacrifice, auto-enrolment triggers | Enhancement |
| **Withdrawal rates** | 4.7% in 4 services (incl. 9 inline in RetirementStrategyService), 4.0% in 3 services | Single configurable source in TaxConfigService | Config migration |
| **Accumulation/decumulation trigger** | Hardcoded 10 years in RetirementAgent line 137 | Configurable via TaxConfigService | Config migration |
| **Growth rates** | RiskPreferenceService in some, hardcoded 5% in others | Always from user's risk profile via RiskPreferenceService | Fix inconsistency |
| **Inflation** | 2.0% in 3 services, 2.5% in 2 services | Single value from TaxConfigService | Config migration |
| **Retirement age default** | 67 in 4 services, 68 in AssumptionsService | Always from user data, SPA from TaxConfigService as fallback | Fix inconsistency |
| **Data readiness gate** | Returns error string if profile missing | 11 granular checks, no assumptions | Moderate rewrite |
| **Salary sacrifice** | Not implemented | Full analysis with NMW/NLW proxy floor check, NI savings, impact warnings | New feature |
| **Auto-enrolment** | Not implemented | Threshold checking from TaxConfigService | New feature |
| **Smoker/health for annuity** | Not used | Fetch from `$user->protectionProfile?->smoker_status/health_status` (captured in onboarding on ProtectionProfile) | Enhancement |
| **Care costs** | Fields exist on RetirementProfile but not wired into DecumulationPlanner from analysis | Wire care_cost_annual and care_start_age into decumulation | Integration |
| **Annuity rates** | Hardcoded 4-7.5% by age | Move to TaxConfigService as configurable estimates | Config migration |

---

## Architecture: Extend Existing Pattern

The Retirement module **already uses the correct architecture** — DB-driven action definitions, agent orchestration, service delegation. No rebuild needed:

```
RetirementActionDefinition (DB model) ← EXISTS — expand triggers
        |
RetirementActionDefinitionService     ← EXISTS — add evaluator methods
        |
RetirementAgent::analyze()            ← EXISTS — enhance with readiness gate
        |
RetirementStrategyService             ← EXISTS — enhance with new strategies
        |
CoordinatingAgent                     ← EXISTS — already integrated
```

---

## Implementation Phases

### Phase 0: Centralise Constants in TaxConfigService (Pre-requisite)
**Priority: CRITICAL — blocks everything else**
**Files modified: 8 | Files created: 0**

#### 0.1 Add Retirement Config to TaxConfigService Seeder

**File:** `database/seeders/TaxConfigurationSeeder.php`

Add new `retirement` section:
```php
'retirement' => [
    'accumulation_to_decumulation_years' => 10,
    'withdrawal_rates' => [
        'sustainable' => 0.047,  // Centralised — replaces 16+ hardcoded locations across 7 files
        'safe' => 0.04,
        'gia' => 0.04,
    ],
    'target_income_percent' => 0.75,
    'projection_end_age' => 100,
    'monte_carlo_iterations' => 1000,
    'compounding_periods' => 4,
    'employer_match_typical_threshold' => 0.05,
    'annuity_rate_estimates' => [
        55 => ['single' => 0.040, 'joint' => 0.034],
        60 => ['single' => 0.045, 'joint' => 0.038],
        65 => ['single' => 0.055, 'joint' => 0.047],
        70 => ['single' => 0.065, 'joint' => 0.055],
        75 => ['single' => 0.075, 'joint' => 0.064],
    ],
    'annuity_spouse_discount' => 0.15,
],
'pension' => [
    // Add to existing pension section:
    'state_pension' => [
        // existing keys (full_new_state_pension, qualifying_years, etc.) plus:
        'current_spa' => 66,  // Rising to 67 (2026-2028), then 68 (2044-2046)
        'future_spa' => 67,
    ],
    'salary_sacrifice' => [
        'nlw_hourly_21_plus' => 12.21,
        'nmw_hourly_18_20' => 10.00,
        'nmw_hourly_under_18' => 7.55,
        'standard_weekly_hours' => 37.5,
        'conservative_proxy_floor' => 10000, // Auto-enrolment earnings trigger as proxy
    ],
    'auto_enrolment' => [
        'earnings_trigger' => 10000,
        'lower_qe' => 6240,
        'upper_qe' => 50270,
        'min_employee_percent' => 0.05,
        'min_employer_percent' => 0.03,
        'min_total_percent' => 0.08,
        'age_min' => 22,
    ],
],
```

#### 0.2 Resolve Withdrawal Rate Inconsistency

**Decision:** Centralise to `TaxConfigService::get('retirement.withdrawal_rates.sustainable')` (4.7%) as the primary rate. Document that 4.0% (`safe`) is used for conservative scenarios only.

**Scope: 16+ locations across 7 files** (not 6 — see devil's advocate review).

| File | Current | Replace With |
|------|---------|-------------|
| `RetirementProjectionService.php` | `SUSTAINABLE_WITHDRAWAL_RATE = 0.047` | `$this->taxConfig->get('retirement.withdrawal_rates.sustainable')` |
| `RequiredCapitalCalculator.php` | `DEFAULT_WITHDRAWAL_RATE = 0.047` | `$this->taxConfig->get('retirement.withdrawal_rates.sustainable')` |
| `RetirementIncomeService.php` | `ISA_WITHDRAWAL_RATE = 0.047` | `$this->taxConfig->get('retirement.withdrawal_rates.sustainable')` |
| `RetirementIncomeService.php` | `GIA_WITHDRAWAL_RATE = 0.04` | `$this->taxConfig->get('retirement.withdrawal_rates.gia')` |
| `RetirementStrategyService.php` | 9 additional inline `0.047` literals at lines 855, 880, 901, 1588, 1669, 1833, 1895, 1900, 2007. This is the largest single source of withdrawal rate hardcoding. | `$this->taxConfig->get('retirement.withdrawal_rates.sustainable')` |
| `PensionProjector.php` line 195 | Inline `0.04` in `projectTotalRetirementIncome()` (NOT a named constant — different from RetirementProjectionService's `SUSTAINABLE_WITHDRAWAL_RATE`) | `$this->taxConfig->get('retirement.withdrawal_rates.sustainable')` |
| `DecumulationPlanner.php` line 139 | `0.04` in `calculatePCLSStrategy()` | `$this->taxConfig->get('retirement.withdrawal_rates.safe')` |
| `ContributionOptimizer.php` line 143 | Inline `0.04` | `$this->taxConfig->get('retirement.withdrawal_rates.safe')` |
| `DecumulationPlanner.php` | Tests 3%, 4%, 5% | Keep as scenario comparison but use TaxConfigService sustainable rate as "recommended" |

**Post-migration verification:** Run `grep -rn '0\.047\|0\.04[^0-9]' app/Services/Retirement/` and create an architecture test asserting zero matches for hardcoded withdrawal rates in the retirement service directory.

#### 0.3 Resolve Inflation Rate Inconsistency

**Decision:** Centralise to `TaxConfigService::getAssumptions()['inflation']` (2.5% — seeded value).

| File | Current | Replace With |
|------|---------|-------------|
| `RetirementProjectionService.php` | `INFLATION_RATE = 0.02` | `$this->taxConfig->getAssumptions()['inflation']` |
| `RequiredCapitalCalculator.php` | `DEFAULT_INFLATION_RATE = 0.025` | `$this->taxConfig->getAssumptions()['inflation']` |
| `RetirementIncomeService.php` | `DEFAULT_INFLATION_RATE = 0.02` | `$this->taxConfig->getAssumptions()['inflation']` |
| `AssumptionsService.php` | `DEFAULT_INFLATION_RATE = 2.0` | `$this->taxConfig->getAssumptions()['inflation'] * 100` (or adjust format) |

#### 0.4 Resolve Retirement Age Inconsistency

**PREREQUISITE:** The `current_spa` key must be added to the seeder (Phase 0.1) and the seeder must be run BEFORE any service references this key. Without it, all 5 age fallbacks return null, producing silent wrong projections.

**Decision:** ALWAYS use user data. Fallback priority: (1) `StatePension` model per user (`$statePension->state_pension_age`), (2) `TaxConfigService` seeded SPA (`pension.state_pension.current_spa`), (3) never hardcode.

| File | Current | Replace With |
|------|---------|-------------|
| `RetirementProjectionService.php` | `DEFAULT_RETIREMENT_AGE = 67` | `$user->retirement_age ?? $this->taxConfig->getPensionAllowances()['state_pension']['current_spa']` |
| `RequiredCapitalCalculator.php` | `DEFAULT_RETIREMENT_AGE = 67` | Same pattern |
| `RetirementIncomeService.php` | `DEFAULT_RETIREMENT_AGE = 67` (3 usages at lines 578, 581, 590 controlling state pension income phasing) | Same pattern |
| `PensionProjector.php` | `DEFAULT_RETIREMENT_AGE = 67` | Same pattern |
| `AssumptionsService.php` | `DEFAULT_RETIREMENT_AGE = 68` | Same pattern — resolves the 67/68 conflict |

> **Important:** All code accessing the user's risk level must use `RiskPreferenceService::getMainRiskLevel($userId)`. Growth rates come from `RiskPreferenceService::getReturnParameters($riskLevel)['expected_return_typical']` — not hardcoded 5%.

#### 0.5 Fix Growth Rate Hardcoding

> **Note:** `ContributionOptimizer` already has `TaxConfigService` injected. Only `RiskPreferenceService` needs to be added as a new constructor dependency.

| File | Current | Replace With |
|------|---------|-------------|
| `ContributionOptimizer.php` line 124 | `DEFAULT_GROWTH_RATE = 0.05` | `RiskPreferenceService::getReturnParameters($riskLevel)['expected_return_typical']` |
| `RetirementAgent.php` lines 262, 305 | `TaxDefaults::DEFAULT_GROWTH_RATE` (5%) for scenarios | User's actual risk-based rate from RiskPreferenceService |
| `RetirementIncomeService.php` line 35 | `DEFAULT_GROWTH_RATE = 0.04` — used for ISA/GIA/bond projections | User's risk-based rate from `RiskPreferenceService::getReturnParameters($riskLevel)['expected_return_typical']` |
| `PensionProjector.php` | `DEFAULT_GROWTH_RATE = 0.05` — last-resort fallback when RiskPreferenceService returns null | Replace with TaxConfigService lookup or keep as documented fallback with explicit comment |

#### 0.6 Move Accumulation/Decumulation Trigger

**File:** `app/Agents/RetirementAgent.php` line 137

```php
// BEFORE (hardcoded):
if ($yearsToRetirement <= 10 && $currentDcValue > 0)

// AFTER (configurable):
$decumulationThreshold = $this->taxConfig->get('retirement.accumulation_to_decumulation_years', 10);
if ($yearsToRetirement <= $decumulationThreshold && $currentDcValue > 0)
```

#### 0.7 Move Annuity Rate Estimates

**File:** `app/Services/Retirement/DecumulationPlanner.php`

Replace hardcoded `getAnnuityRate()` method with TaxConfigService lookup:
```php
$rates = $this->taxConfig->get('retirement.annuity_rate_estimates');
$spouseDiscount = $this->taxConfig->get('retirement.annuity_spouse_discount');
```

#### 0.8 Move Other Hardcoded Values

| File | Current | Replace With |
|------|---------|-------------|
| `ContributionOptimizer.php` | Employer match threshold 5% | `$this->taxConfig->get('retirement.employer_match_typical_threshold')` |
| `RetirementProjectionService.php` | `TARGET_INCOME_PERCENT = 0.75` | `$this->taxConfig->get('retirement.target_income_percent')` |
| `RetirementProjectionService.php` | `END_AGE = 100` | `$this->taxConfig->get('retirement.projection_end_age')` |
| `RetirementProjectionService.php` | `MONTE_CARLO_ITERATIONS = 1000` | `$this->taxConfig->get('retirement.monte_carlo_iterations')` |
| `RequiredCapitalCalculator.php` | `DEFAULT_COMPOUND_PERIODS = 4` | `$this->taxConfig->get('retirement.compounding_periods')` |

**Test updates required:** All retirement service tests that instantiate services directly will need TaxConfigService mocks added.

---

### Phase 1: Data Readiness Gate
**Priority: HIGH — ensures no assumptions**
**Files modified: 2 | Files created: 1**

#### 1.1 Create RetirementDataReadinessService

**File:** `app/Services/Retirement/RetirementDataReadinessService.php`

Implements the 11-check gate from the amended research document (Section 3.1):

**Blocking (3):** date_of_birth, marital_status, income data
**Warning (4):** pensions, target retirement age, target income, expenditure
**Info (4):** employment status, risk profile, State Pension forecast, spouse link

Returns all expected keys as null when `can_proceed = false` (same pattern as Estate/Protection).

#### 1.2 Integrate into RetirementAgent

**File:** `app/Agents/RetirementAgent.php`

Replace the simple "No retirement profile found" error at line 77 with the readiness gate. Return structured response within normal envelope.

**Vuex score edge case:** When readiness gate blocks analysis, `state.analysis` is null. The Vuex getter `retirementReadinessScore` computes `100 - (incomeGap / 500)` — with null data, gap = 0, score = 100. First-time users get a false 'perfect' retirement score. Add a guard: if `state.analysis?.can_proceed === false`, return `null` instead of computing the score. Update `FinancialHealthScore.vue` to handle null retirement score (exclude from composite or show 'incomplete').

---

### Phase 2: Salary Sacrifice & Auto-Enrolment
**Priority: HIGH — significant feature gap**
**Files created: 1 | Files modified: 2**

#### 2.1 Create SalarySacrificeAnalyzer

**File:** `app/Services/Retirement/SalarySacrificeAnalyzer.php`

```php
class SalarySacrificeAnalyzer
{
    public function analyze(User $user, DCPension $pension): array
    {
        $salary = $user->annual_employment_income;
        $contribution = $pension->employee_contribution_percent * $salary / 100;

        // NI savings
        $employeeNiRate = 0.08; // From TaxConfigService
        $employerNiRate = 0.138;
        $employeeNiSaving = $contribution * $employeeNiRate;
        $employerNiSaving = $contribution * $employerNiRate;

        // Impact checks
        $salaryAfterSacrifice = $salary - $contribution;
        $warnings = [];

        // Cannot compute exact NMW floor without contracted hours
        // Use auto-enrolment earnings trigger (£10,000) as conservative proxy
        $proxyFloor = $this->taxConfig->get('pension.salary_sacrifice.conservative_proxy_floor');
        if ($salaryAfterSacrifice < $proxyFloor) {
            $warnings[] = ['type' => 'warn', 'message' =>
                'Salary sacrifice would reduce your pay below £10,000. This may breach
                 National Minimum Wage requirements depending on your contracted hours.
                 Seek advice before proceeding.'];
        }

        // Additional warning for significant sacrifice
        if ($contribution / $salary > 0.20) {
            $warnings[] = ['type' => 'info', 'message' =>
                'Sacrificing more than 20% of salary. Check impact on mortgage
                 applications, statutory pay, and death-in-service benefits.'];
        }

        if ($salaryAfterSacrifice < $this->taxConfig->get('income_tax.personal_allowance')) {
            $warnings[] = ['type' => 'warn', 'message' => 'Below personal allowance'];
        }
        // ... NI LEL, mortgage, statutory pay, death in service checks

        return [
            'available' => $pension->scheme_type === 'workplace',
            'employee_ni_saving' => $employeeNiSaving,
            'employer_ni_saving' => $employerNiSaving,
            'total_saving' => $employeeNiSaving + $employerNiSaving,
            'salary_after_sacrifice' => $salaryAfterSacrifice,
            'warnings' => $warnings,
        ];
    }
}
```

#### 2.2 Auto-Enrolment Threshold Checking

**File:** `app/Services/Retirement/ContributionOptimizer.php`

Add auto-enrolment validation:
```php
$aeConfig = $this->taxConfig->get('pension.auto_enrolment');
$earningsTrigger = $aeConfig['earnings_trigger']; // £10,000
$lowerQE = $aeConfig['lower_qe']; // £6,240
$upperQE = $aeConfig['upper_qe']; // £50,270
$minTotal = $aeConfig['min_total_percent']; // 8%

// Check: is user eligible for auto-enrolment?
// Check: are contributions meeting minimum 8% of qualifying earnings?
```

---

### Phase 3: Enhanced Annuity & Integration
**Priority: MEDIUM**
**Files modified: 3**

#### 3.1 Smoker/Health Status for Enhanced Annuity

**File:** `app/Services/Retirement/DecumulationPlanner.php`

Fetch from ProtectionProfile (captured during onboarding):
```php
// smoker_status and health_status are on ProtectionProfile, not User
$smokerStatus = $user->protectionProfile?->smoker_status;
$healthStatus = $user->protectionProfile?->health_status;

// Enhanced annuity rates for smokers/health conditions
if ($smokerStatus) {
    $annuityRate *= 1.20; // 15-25% increase for smokers
}
if ($healthStatus === 'poor' || $healthStatus === 'fair') {
    $annuityRate *= 1.15; // Enhanced rate for health conditions
}
```

> **Note:** `smoker_status` and `health_status` are stored on `ProtectionProfile` (captured during onboarding), NOT on the User model. Access via `$user->protectionProfile?->smoker_status`. The retirement engine fetches these — it does NOT ask the user again.

> **Implementation note:** Eager-load `protectionProfile` in RetirementAgent where DecumulationPlanner is invoked. Add a unit test asserting enhanced annuity rates apply when smoker_status is true.

#### 3.2 Wire Care Costs into Decumulation

**File:** `app/Agents/RetirementAgent.php`

When calling DecumulationPlanner, pass care costs from RetirementProfile:
```php
$careCostAnnual = $profile->care_cost_annual ?? 0;
$careStartAge = $profile->care_start_age ?? 0;
$careStartsAfterYear = max(0, $careStartAge - $retirementAge);

$withdrawalAnalysis = $this->decumulationPlanner->calculateSustainableWithdrawalRate(
    $totalDcValue, $yearsInRetirement, $growthRate, $inflationRate,
    $careCostAnnual, $careStartsAfterYear
);
```

#### 3.3 Expand Action Definitions

**File:** `database/seeders/RetirementActionDefinitionSeeder.php`

Add triggers:

| Key | Trigger | Priority |
|-----|---------|----------|
| `salary_sacrifice_available` | Workplace pension, no sacrifice detected | high |
| `salary_sacrifice_floor_warning` | Sacrifice would reduce pay below NMW proxy floor (£10,000) | critical |
| `auto_enrolment_below_minimum` | Contributions below 8% of QE | high |
| `enhanced_annuity_eligible` | Smoker or health condition — enhanced rates | medium |
| `care_costs_not_modelled` | No care costs entered, age > 50 | info |
| `state_pension_no_forecast` | No SP forecast entered | medium |
| `approaching_decumulation` | Within configurable years of retirement | high |
| `pension_consolidation_opportunity` | 3+ DC pensions | medium |

Add corresponding evaluator methods to `RetirementActionDefinitionService`.

---

### Phase 4: Frontend Enhancements
**Priority: LOW**
**Files modified: 2**

#### 4.1 Salary Sacrifice UI

Add salary sacrifice analysis display to the strategies tab showing NI savings, warnings, and net cost comparison.

#### 4.2 Auto-Enrolment Display

Show auto-enrolment compliance status — whether contributions meet the minimum 8% of qualifying earnings.

---

## Dependency Graph

```
Phase 0 ──────────────────────────────────────────┐
(TaxConfigService centralisation — ALL constants)  |
    |                                               |
    v                                               |
Phase 1 ──────────────────────────────────────────┤
(Data readiness gate)                              |
    |                                               |
    v                                               |
Phase 2 ────────── Phase 3 ────────────────────────┤
(Salary sacrifice   (Annuity/care/action defs)     |
 + auto-enrolment)                                  |
    |                   |                           |
    v                   v                           |
Phase 4 ──────────────────────────────────────────┘
(Frontend)
```

Phases 2 and 3 can run in parallel.

---

## Files Created (New)

| File | Purpose |
|------|---------|
| `app/Services/Retirement/RetirementDataReadinessService.php` | 11-check data readiness gate |
| `app/Services/Retirement/SalarySacrificeAnalyzer.php` | Salary sacrifice analysis with NMW/NLW proxy floor check |

## Files Modified (Existing)

| File | Change |
|------|--------|
| `database/seeders/TaxConfigurationSeeder.php` | Add `retirement.*`, `pension.state_pension.current_spa/future_spa`, `pension.salary_sacrifice.*` (NMW/NLW rates + proxy floor), `pension.auto_enrolment.*` config |
| `app/Services/Retirement/RetirementProjectionService.php` | Remove 5 hardcoded constants, fetch from TaxConfigService |
| `app/Services/Retirement/PensionProjector.php` | Remove hardcoded withdrawal rate (4.0% → configurable), retirement age default |
| `app/Services/Retirement/DecumulationPlanner.php` | Move annuity rates to TaxConfigService, add smoker/health enhancement, add TaxConfigService DI |
| `app/Services/Retirement/RetirementIncomeService.php` | Remove 4 hardcoded constants, fetch from TaxConfigService |
| `app/Services/Retirement/RequiredCapitalCalculator.php` | Remove 4 hardcoded constants, fetch from TaxConfigService |
| `app/Services/Retirement/ContributionOptimizer.php` | Remove hardcoded growth rate and employer match, add auto-enrolment checking, add `RiskPreferenceService` DI (note: `TaxConfigService` is already injected) |
| `app/Services/Retirement/RetirementStrategyService.php` | Remove 9 inline `0.047` withdrawal rate literals, fetch from TaxConfigService |
| `app/Services/Settings/AssumptionsService.php` | Fix DEFAULT_RETIREMENT_AGE from 68 to SPA from TaxConfigService |
| `app/Agents/RetirementAgent.php` | Add readiness gate, configurable decumulation trigger, wire care costs, fix growth rate in scenarios |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | Add evaluator methods for new triggers |
| `database/seeders/RetirementActionDefinitionSeeder.php` | Add ~8 new trigger definitions |

## What This Plan Does NOT Include

1. **Real-time annuity provider API** — Annuity rates remain estimated. Live provider integration is a separate feature.
2. **State Pension auto-fetch from DWP** — User must enter their SP forecast manually.
3. **DB pension transfer analysis** — FCA-regulated advice; out of scope for a planning tool.
4. **Pension tracing service** — Link to gov.uk only, no API integration.
5. **Spouse/joint retirement planning** — Fields exist but comprehensive couple analysis is deferred.

## Testing Strategy

| Phase | Tests |
|-------|-------|
| Phase 0 | Run full test suite after each constant migration. Architecture test: grep for removed constant names. |
| Phase 1 | Unit tests for 11 readiness checks. |
| Phase 2 | Unit tests for SalarySacrificeAnalyzer (NMW proxy floor, NI savings, 20%+ sacrifice warning). Unit tests for auto-enrolment threshold checking. |
| Phase 3 | Unit tests for enhanced annuity rates. Integration test for care costs in decumulation. |
| Phase 4 | Manual browser testing. |
