# Decision Engine Upgrade: Task List

> **Date:** 2026-03-14 | **Total Tasks:** 119 | **Gates:** 26 | **Acceptance Criteria:** 10 | **Sprints:** 5
> **Source:** MASTER-IMPLEMENTATION-PLAN.md + 5 module implementation plans + 5 devil's advocate reviews
>
> Mark tasks with `[x]` as they are completed.

---

## SPRINT 1: Cross-Cutting Foundation (TaxConfigService)

**Goal:** Centralise ALL hardcoded values into TaxConfigService. Zero business logic changes.

### 1.1 TaxConfigurationSeeder — Master Update
> Add ALL module configs in ONE seeder update.

- [ ] **1.1.1** Add `income_tax.personal_savings_allowance` (basic: 1000, higher: 500, additional: 0) and `income_tax.starting_rate_for_savings` (band: 5000)
  - **Agent:** `feature-dev` | **Skill:** `/feature-dev`
  - **File:** `database/seeders/TaxConfigurationSeeder.php`

- [ ] **1.1.2** Add `savings.*` section (fscs_deposit_protection: 85000, fscs_joint_protection: 170000, premium_bonds_max: 50000, parental_settlement_threshold: 100, etc.)
  - **Agent:** `feature-dev`
  - **File:** `database/seeders/TaxConfigurationSeeder.php`

- [ ] **1.1.3** Add `estate.onboarding_estimates` (property: 300000, investment: 50000, savings: 25000, business: 100000) and `estate.insurance_premium_estimates` (per_thousand_monthly by age/gender)
  - **Agent:** `feature-dev`
  - **File:** `database/seeders/TaxConfigurationSeeder.php`

- [ ] **1.1.4** Add `inheritance_tax.pension_iht_inclusion` (effective_date: 2027-04-06)
  - **Agent:** `feature-dev`
  - **File:** `database/seeders/TaxConfigurationSeeder.php`

- [ ] **1.1.5** Add `assumptions.growth_by_risk` mapping (very_low: 0.02 through high: 0.07)
  - **Agent:** `feature-dev`
  - **File:** `database/seeders/TaxConfigurationSeeder.php`

- [ ] **1.1.6** Add `investment.*` section (asset_class_yields, fee_benchmarks, portfolio_thresholds, waterfall limits, venture_capital, safety thresholds, transfer scan thresholds)
  - **Agent:** `feature-dev`
  - **File:** `database/seeders/TaxConfigurationSeeder.php`

- [ ] **1.1.7** Add `protection.*` section (income_multipliers, ip_max_benefit_percent: 0.60, ci_multiplier: 3, education_cost: 9000, final_expenses: 7500, affordability thresholds, premium_factors, withdrawal_rates, ipt rates)
  - **Agent:** `feature-dev`
  - **File:** `database/seeders/TaxConfigurationSeeder.php`

- [ ] **1.1.8** Add `benefits.*` section (ssp: weekly_rate 116.75/max_weeks 28, esa, universal_credit, pip, bereavement_support)
  - **Agent:** `feature-dev`
  - **File:** `database/seeders/TaxConfigurationSeeder.php`

- [ ] **1.1.9** Add `retirement.*` section (accumulation_to_decumulation_years: 10, withdrawal_rates: sustainable/safe/gia, target_income_percent: 0.75, projection_end_age: 100, monte_carlo_iterations: 1000, compounding_periods: 4, annuity_rate_estimates by age)
  - **Agent:** `feature-dev`
  - **File:** `database/seeders/TaxConfigurationSeeder.php`

- [ ] **1.1.10** Add `pension.state_pension.current_spa` (66) and `future_spa` (67), `pension.salary_sacrifice.*` (nlw_hourly, nmw_hourly, conservative_proxy_floor: 10000), `pension.auto_enrolment.*` (earnings_trigger: 10000, lower_qe: 6240, upper_qe: 50270, min percentages)
  - **Agent:** `feature-dev`
  - **File:** `database/seeders/TaxConfigurationSeeder.php`

- [ ] **1.1.11** Run seeder and verify all new keys load correctly
  - **Command:** `php artisan db:seed --class=TaxConfigurationSeeder --force`
  - **Review:** `tax-compliance-reviewer`

### 1.2 TaxConfigService Helper Methods

- [ ] **1.2.1** Add `getPersonalSavingsAllowance(?string $taxBand = null): int|array` method
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`
  - **File:** `app/Services/TaxConfigService.php`

- [ ] **1.2.2** Add `getSavingsConfig(?string $key = null): mixed` method
  - **Agent:** `feature-dev`
  - **File:** `app/Services/TaxConfigService.php`

### 1.3 PSA Hardcode Removal (6 files)

- [ ] **1.3.1** Replace hardcoded PSA in `UKTaxCalculator.php` (lines 673-677)
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- [ ] **1.3.2** Replace hardcoded PSA in `Tax/TaxOptimisationService.php` (lines 217-220)
  - **Agent:** `feature-dev`

- [ ] **1.3.3** Replace hardcoded PSA in `Tax/TaxProductInfoService.php` (lines 150-151)
  - **Agent:** `feature-dev`

- [ ] **1.3.4** Replace hardcoded PSA in `Investment/Tax/TaxOptimizationAnalyzer.php`
  - **Agent:** `feature-dev`

- [ ] **1.3.5** Replace hardcoded PSA in `Investment/AssetLocation/AssetLocationOptimizer.php`
  - **Agent:** `feature-dev`

- [ ] **1.3.6** Replace hardcoded PSA in `Investment/AssetLocation/TaxDragCalculator.php` (includes rate-to-band conversion)
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

### 1.4 Growth Rate Removal — Investment Module (15+ locations, 12 files)

- [ ] **1.4.1** Replace all hardcoded `0.06`/`0.065` with `RiskPreferenceService::getReturnParameters()` across 12 files (PortfolioStrategyService, OCFImpactCalculator, ShortfallAnalyzer, GoalProgressAnalyzer, ISAAllowanceOptimizer, BedAndISACalculator, TaxOptimizationAnalyzer, FeeAnalyzer, ContributionOptimizer, HoldingsDataExtractor, PortfolioStatisticsCalculator, TaxDragCalculator)
  - **Agent:** `feature-dev` with `superpowers:dispatching-parallel-agents`
  - **Review:** `tax-compliance-reviewer`

- [ ] **1.4.2** Remove income fallback `?? 50000` in `AssetLocationOptimizer.php` line 93, use `ResolvesIncome` trait
  - **Agent:** `feature-dev`

- [ ] **1.4.3** Replace hardcoded dividend yields in `TaxDragCalculator.php` (lines 269-274) with `TaxConfigService::get('investment.asset_class_yields')`
  - **Agent:** `feature-dev`

- [ ] **1.4.4** Replace hardcoded 0.15% OCF threshold in `FeeAnalyzer.php` with `TaxConfigService::get('investment.fee_benchmarks.low_cost_ocf')`
  - **Agent:** `feature-dev`

- [ ] **1.4.5** Verify: `grep -rn '0\.06[^0-9]' app/Services/Investment/` returns zero matches
  - **Command:** `grep -rn '0\.06[^0-9]' app/Services/Investment/`

### 1.5 Growth Rate Removal — Estate Module (1 true hardcode)

- [ ] **1.5.1** Fix `LifePolicyStrategyService.php`: add `AssumptionsService` constructor injection, replace `INVESTMENT_RETURN_RATE = 0.047`, move hardcoded premium table to TaxConfigService
  - **Agent:** `feature-dev`

- [ ] **1.5.2** Verify `IHTCalculationService` and `LifeCoverCalculator` already defer to `AssumptionsService` (no change needed)
  - **Agent:** `Explore`

- [ ] **1.5.3** Fix `FutureValueCalculator.php`: verify property growth key exists in seeder, use `$this->taxConfig->getAssumptions()['property_growth']` instead of hardcoded 3%
  - **Agent:** `feature-dev`

### 1.6 Withdrawal Rate Centralisation — Retirement (16+ locations, 7 files)

- [ ] **1.6.1** Replace `SUSTAINABLE_WITHDRAWAL_RATE = 0.047` in `RetirementProjectionService.php`
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- [ ] **1.6.2** Replace `DEFAULT_WITHDRAWAL_RATE = 0.047` in `RequiredCapitalCalculator.php`
  - **Agent:** `feature-dev`

- [ ] **1.6.3** Replace `ISA_WITHDRAWAL_RATE = 0.047` and `GIA_WITHDRAWAL_RATE = 0.04` in `RetirementIncomeService.php`
  - **Agent:** `feature-dev`

- [ ] **1.6.4** Replace 9 inline `0.047` literals in `RetirementStrategyService.php` (lines 855, 880, 901, 1588, 1669, 1833, 1895, 1900, 2007)
  - **Agent:** `feature-dev`

- [ ] **1.6.5** Replace inline `0.04` in `PensionProjector.php` line 195
  - **Agent:** `feature-dev`

- [ ] **1.6.6** Replace `0.04` in `DecumulationPlanner.php` line 139 (PCLS calc)
  - **Agent:** `feature-dev`

- [ ] **1.6.7** Replace inline `0.04` in `ContributionOptimizer.php` line 143
  - **Agent:** `feature-dev`

- [ ] **1.6.8** Resolve inflation rate conflict (2.0% vs 2.5%) across `RetirementProjectionService`, `RequiredCapitalCalculator`, `RetirementIncomeService`, `AssumptionsService`
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- [ ] **1.6.9** Verify: `grep -rn '0\.047\|0\.04[^0-9]' app/Services/Retirement/` returns zero matches
  - **Command:** `grep -rn '0\.047\|0\.04[^0-9]' app/Services/Retirement/`

### 1.7 Hardcode Removal — Protection (10+ locations, 4 files)

- [ ] **1.7.1** Add `TaxConfigService` constructor injection to `CoverageGapAnalyzer`, `RecommendationEngine`, `ScenarioBuilder`, `AdequacyScorer` and replace all hardcoded values (withdrawal rate 0.047, final expenses 7500, education cost 9000, IP max 0.60, CI multiplier 3, premium factors, etc.)
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- [ ] **1.7.2** Update Protection test files (`CoverageGapAnalyzerTest`, `RecommendationEngineTest`, `ScenarioBuilderTest`) to mock new TaxConfigService dependency
  - **Agent:** `feature-dev`

### 1.8 EstateDefaults Cleanup

- [ ] **1.8.1** Remove estimated value constants from `EstateDefaults.php` (ESTIMATED_PROPERTY_VALUE, ESTIMATED_INVESTMENT_VALUE, ESTIMATED_SAVINGS_VALUE, ESTIMATED_BUSINESS_VALUE, DEFAULT_LIFE_EXPECTANCY, DEFAULT_CURRENT_AGE)
  - **Agent:** `feature-dev`

- [ ] **1.8.2** Retain but source RNRB_TAPER_THRESHOLD, TRUST_SUGGESTION_THRESHOLD, COMBINED_NRB_THRESHOLD, COMBINED_RNRB_THRESHOLD from TaxConfigService
  - **Agent:** `feature-dev`

- [ ] **1.8.3** Update `EstateOnboardingFlow.php` to use `TaxConfigService::get('onboarding_estimates')`
  - **Agent:** `feature-dev`

### 1.9 Income Trait Standardisation

- [ ] **1.9.1** Add `ResolvesIncome` + `ResolvesExpenditure` traits to `Estate/GiftingStrategyOptimizer.php` and `Estate/PersonalizedGiftingStrategyService.php`
  - **Agent:** `feature-dev`

- [ ] **1.9.2** Add `ResolvesIncome` + `ResolvesExpenditure` traits to `Protection/CoverageGapAnalyzer.php` and `Protection/RecommendationEngine.php`
  - **Agent:** `feature-dev`

- [ ] **1.9.3** Add `ResolvesIncome` trait to `Investment/AssetLocation/AssetLocationOptimizer.php`
  - **Agent:** `feature-dev`

### 1.10 Retirement Miscellaneous Constants

- [ ] **1.10.1** Move accumulation/decumulation trigger from `RetirementAgent.php` line 137 to TaxConfigService
  - **Agent:** `feature-dev`

- [ ] **1.10.2** Move annuity rate estimates from `DecumulationPlanner.php` to TaxConfigService
  - **Agent:** `feature-dev`

- [ ] **1.10.3** Fix `AssumptionsService` DEFAULT_RETIREMENT_AGE from 68 to SPA from TaxConfigService
  - **Agent:** `feature-dev`

- [ ] **1.10.4** Fix `RetirementIncomeService` DEFAULT_STATE_PENSION_AGE (3 usages at lines 578, 581, 590)
  - **Agent:** `feature-dev`

- [ ] **1.10.5** Fix `PensionProjector` DEFAULT_GROWTH_RATE and inline 0.04
  - **Agent:** `feature-dev`

- [ ] **1.10.6** Replace hardcoded employer match threshold 5%, TARGET_INCOME_PERCENT 0.75, END_AGE 100, MONTE_CARLO_ITERATIONS 1000, DEFAULT_COMPOUND_PERIODS 4 in retirement services
  - **Agent:** `feature-dev`

- [ ] **1.10.7** Replace `RetirementIncomeService` `DEFAULT_GROWTH_RATE = 0.04` (line 35) with user's risk-based rate from `RiskPreferenceService` (DA-Retirement I6 — missed from growth rate table)
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- [ ] **1.10.8** Replace `RetirementAgent.php` growth rate `TaxDefaults::DEFAULT_GROWTH_RATE` (5%) at lines 262, 305 with user's risk-based rate from `RiskPreferenceService`
  - **Agent:** `feature-dev`

- [ ] **1.10.9** Update ALL retirement service tests that instantiate services directly to mock new `TaxConfigService`/`RiskPreferenceService` constructor dependencies (mirrors Protection task 1.7.2)
  - **Agent:** `feature-dev`

### Sprint 1 Gate

- [ ] **GATE-1** Run full test suite: `./vendor/bin/pest`
  - **Command:** `./vendor/bin/pest`
- [ ] **GATE-2** Run seeder: `php artisan db:seed`
  - **Command:** `php artisan db:seed`
- [ ] **GATE-3** Format code: `./vendor/bin/pint`
  - **Command:** `./vendor/bin/pint`
- [ ] **GATE-4** Architecture grep checks pass (no hardcoded values in target directories)
  - **Skill:** `superpowers:verification-before-completion`
- [ ] **GATE-5** Code review Sprint 1
  - **Agent:** `superpowers:code-reviewer`

---

## SPRINT 2: Database Migrations & Data Readiness Gates

**Goal:** Create migrations, data readiness services for all 5 modules, fix score violations.

### 2.1 Database Migrations

- [ ] **2.1.1** Create migration: `create_savings_action_definitions_table` (mirrors investment_action_definitions)
  - **Agent:** `feature-dev` | **Review:** `database-optimizer`

- [ ] **2.1.2** Create migration: `create_goal_savings_account_table` (many-to-many pivot for goal-account linking)
  - **Agent:** `feature-dev` | **Review:** `database-optimizer`

- [ ] **2.1.3** Create migration: `add_employer_benefits_to_protection_profiles` (7 new columns: death_in_service_multiple, group_ip_benefit_percent, group_ip_benefit_months, group_ip_definition, group_ci_amount, has_employer_pmi, employer_name)
  - **Agent:** `feature-dev` | **Review:** `database-optimizer`

- [ ] **2.1.4** Create migration: `add_joint_life_to_life_insurance_policies` (boolean, default false)
  - **Agent:** `feature-dev`

- [ ] **2.1.5** Data migration: Copy `linked_savings_account_id` values into pivot table. Update `SavingsAccountGoalObserver` and `TracksGoalContributions` trait to query pivot table BEFORE deprecating FK
  - **Agent:** `feature-dev`
  - **CRITICAL:** Observer update must happen before FK deprecation

- [ ] **2.1.6** Update `ProtectionProfile` model: add employer benefit fields to `$fillable` and `$casts`
  - **Agent:** `feature-dev`

- [ ] **2.1.7** Update `LifeInsurancePolicy` model: add `joint_life` to `$fillable` and `$casts`
  - **Agent:** `feature-dev`

- [ ] **2.1.8** Update `Goal` model: add `savingsAccounts()` BelongsToMany relationship. Update `SavingsAccount` model: add `goals()` BelongsToMany relationship (both via `goal_savings_account` pivot with `allocated_amount`, `is_primary`, `priority_rank`)
  - **Agent:** `feature-dev`

- [ ] **2.1.9** Audit `linked_account_ids` JSON array on Goal model — document whether it is investment-goal-specific or dead code. Do NOT leave three linking mechanisms in production simultaneously (DA-Cash M4)
  - **Agent:** `Explore`

- [ ] **2.1.10** Run migrations and seed: `php artisan migrate && php artisan db:seed`
  - **Command:** `php artisan migrate && php artisan db:seed`

### 2.2 Data Readiness Services (5 modules — parallelisable)

> These 5 services are independent and can be built in parallel using `superpowers:dispatching-parallel-agents`.

- [ ] **2.2.1** Create `Services/Savings/SavingsDataReadinessService.php` (checks: DOB, income, expenditure — included in SavingsActionDefinitionService triggers)
  - **Agent:** `feature-dev` (parallel subagent 1)

- [ ] **2.2.2** Create `Services/Estate/EstateDataReadinessService.php` (12 checks: blocking = DOB, marital status, at least one asset; warnings = residency, property, liabilities, family, gifts, will; info = income, policies, PoA)
  - **Agent:** `feature-dev` (parallel subagent 2)

- [ ] **2.2.3** Create `Services/Investment/Recommendation/DataReadinessService.php` (12 checks: blocking = DOB, income, risk profile, expenditure; warnings = employment, protection, pensions, accounts; info = spouse, life events, savings)
  - **Agent:** `feature-dev` (parallel subagent 3)

- [ ] **2.2.4** Create `Services/Protection/ProtectionDataReadinessService.php` (14 checks: blocking = DOB, income, marital status; warnings = expenditure, employment, dependant ages, policies, employer benefits, debts; info = occupation, smoker, health, spouse, life events)
  - **Agent:** `feature-dev` (parallel subagent 4)

- [ ] **2.2.5** Create `Services/Retirement/RetirementDataReadinessService.php` (11 checks: blocking = DOB, marital status, income; warnings = pensions, target retirement age, target income, expenditure; info = employment, risk profile, SP forecast, spouse)
  - **Agent:** `feature-dev` (parallel subagent 5)

- [ ] **2.2.6** Integrate readiness gates into all 5 agents (`SavingsAgent`, `EstateAgent`, `InvestmentAgent`, `ProtectionAgent`, `RetirementAgent`) — each returns full response envelope with null keys when `can_proceed = false`
  - **Agent:** `feature-dev`

- [ ] **2.2.7** Remove auto-creation of `ProtectionProfile` in `ProtectionController::index()` (lines 61-72), replace with readiness gate check. Update `ProtectionProfileResource` to safely handle null profile (DA-Protection M4)
  - **Agent:** `feature-dev`

- [ ] **2.2.8** Apply readiness gate to `EstateAgent::generateRecommendations()` (not just `analyze()`). Replace `EstateDefaults::DEFAULT_LIFE_EXPECTANCY` fallback at line 238 and remove default parameter values in `step4AnnualGiftingStrategy()` / `step6PETGiftingStrategy()` that use it (DA-Estate H3)
  - **Agent:** `feature-dev`

- [ ] **2.2.9** Enhance `ProtectionAgent.php` analysis data: include employer benefits and state benefit offsets in analysis response so downstream services have complete data
  - **Agent:** `feature-dev`

### 2.3 Score Removal — Protection (Rule #13 Fix)

- [ ] **2.3.1** Remove numeric score keys from `ComprehensiveProtectionPlanService::generateExecutiveSummary()`
  - **Agent:** `feature-dev`

- [ ] **2.3.2** Refactor `getRecommendedAction()` to use category strings ('Excellent'/'Good'/'Fair'/'Critical')
  - **Agent:** `feature-dev`

- [ ] **2.3.3** Update Vuex `adequacyScore` getter to return rating string, not numeric value
  - **Agent:** `feature-dev`
  - **Review:** `superpowers:code-reviewer`

### 2.4 Vuex Store Guards (all modules)

- [ ] **2.4.1** Add `can_proceed` / `success: false` handling to `store/modules/savings.js`
  - **Agent:** `feature-dev`

- [ ] **2.4.2** Add `can_proceed` handling to `store/modules/estate.js` (via `analyseEstate` action)
  - **Agent:** `feature-dev`

- [ ] **2.4.3** Add `can_proceed` handling to `store/modules/investment.js`
  - **Agent:** `feature-dev`

- [ ] **2.4.4** Add `can_proceed` handling to `store/modules/protection.js` (guard against `success: false`)
  - **Agent:** `feature-dev`

- [ ] **2.4.5** Add `can_proceed` handling to `store/modules/retirement.js` (guard against `retirementReadinessScore = 100` false positive when analysis is null)
  - **Agent:** `feature-dev`

- [ ] **2.4.6** Update `FinancialHealthScore.vue` to handle null retirement score — exclude from composite or show 'incomplete' when `state.analysis?.can_proceed === false` (DA-Retirement I9)
  - **Agent:** `feature-dev`

### Sprint 2 Gate

- [ ] **GATE-6** Run full test suite: `./vendor/bin/pest`
- [ ] **GATE-7** Run seeder: `php artisan db:seed`
- [ ] **GATE-8** Test each module's dashboard with a user missing required data — should see readiness gates, not errors
  - **Skill:** `superpowers:verification-before-completion`
- [ ] **GATE-9** Code review Sprint 2
  - **Agent:** `superpowers:code-reviewer`

---

## SPRINT 3: Module Engines

**Goal:** Build/expand recommendation engines for all 5 modules.

### 3A: Cash/Savings Engine (Sequential)

- [ ] **3A.1** Create `app/Models/SavingsActionDefinition.php` mirroring `InvestmentActionDefinition` (renderTitle, renderDescription, renderAction, scopes)
  - **Agent:** `feature-dev` | **Skill:** `/feature-dev`

- [ ] **3A.2** Create `database/seeders/SavingsActionDefinitionSeeder.php` with ~40 action definitions across categories (data readiness, emergency fund, tax efficiency/PSA/ISA, rate optimisation, FSCS, debt vs savings, cash vs investment, goal-linked, children's savings, spouse optimisation) + LAUNCH GATE: disable 7 Investment engine savings triggers
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`
  - **CRITICAL:** Launch gate — disable 7 Investment triggers in same seeder run

- [ ] **3A.3** Create `Services/Savings/SavingsActionDefinitionService.php` (~800 lines, ~40 evaluator methods)
  - **Agent:** `feature-dev`

- [ ] **3A.4** Create `Services/Savings/PSACalculator.php` (PSA breach detection, annual interest calculation)
  - **Agent:** `feature-dev`

- [ ] **3A.5** Create `Services/Savings/FSCSAssessor.php` (institution exposure grouping, breach detection)
  - **Agent:** `feature-dev`

- [ ] **3A.6** Create `config/banking_licence_groups.php` (FSCS institution groupings)
  - **Agent:** `feature-dev`

- [ ] **3A.7** Enhance `EmergencyFundCalculator` — add `getTargetMonths(string $employmentStatus): int` (employed: 6, self_employed: 9, retired: 3, contractor: 9)
  - **Agent:** `feature-dev`

- [ ] **3A.8** Enhance `RateComparator` — add `getInstitutionExposure()` for FSCS grouping
  - **Agent:** `feature-dev`

- [ ] **3A.9** Create `Services/Plans/SavingsPlanService.php` mirroring `InvestmentPlanService`
  - **Agent:** `feature-dev`

- [ ] **3A.10** Refactor `SavingsAgent::generateRecommendations()` to delegate to `SavingsActionDefinitionService`
  - **Agent:** `feature-dev`

- [ ] **3A.11** Update `SavingsController::recommendations()` to use `SavingsPlanService`
  - **Agent:** `feature-dev`

- [ ] **3A.12** Create 4 notification classes: `SavingsMaturityAlertNotification`, `SavingsRateExpiryNotification`, `ISAAllowanceWarningNotification`, `EmergencyFundAlertNotification` (database channel only)
  - **Agent:** `feature-dev`

- [ ] **3A.13** Create `SendSavingsAlerts` command (daily schedule) and register in Kernel
  - **Agent:** `feature-dev`

- [ ] **3A.14** Enhance `SavingsAgent::analyze()` to include PSA position (via PSACalculator), FSCS exposure (via FSCSAssessor), employment-based emergency fund target, and per-child Junior ISA status in analysis response
  - **Agent:** `feature-dev`

- [ ] **3A.15** Enhance `SavingsController::index()` response to include PSA position, FSCS exposure summary, employment-based emergency fund target, and per-child savings status with `{child_name}` variables
  - **Agent:** `feature-dev`

- [ ] **3A.16** Update `NotificationPreference` model: add migration for savings alert preference columns (`savings_maturity_alerts`, `savings_rate_alerts`, `isa_allowance_warnings`)
  - **Agent:** `feature-dev`

- [ ] **3A.17** Enhance `LiquidityAnalyzer` — add FSCS overlay to liquidity summary (Cash/Savings Plan Phase 2.2)
  - **Agent:** `feature-dev`

- [ ] **3A.18** Document employment-based emergency fund divergence: Investment uses 6-month universal baseline (`PlanConfigService::getEmergencyFundTargetMonths()`), Savings uses employment-specific targets. This is intentional — add code comment in both services (DA-Cash H1)
  - **Agent:** `feature-dev`

- [ ] **3A.19** Fix `BasePlanService` abstract signature mismatch: `getRecommendations(int $userId): array` vs `InvestmentPlanService`'s extra `?array $preComputedData` parameter. Ensure `SavingsPlanService` follows the corrected pattern (DA-Cash M2)
  - **Agent:** `feature-dev`

### 3B: Estate Planning Enhancements (Partially Parallelisable)

- [ ] **3B.1** IHT calc: PET/CLT NRB deduction (primary user only — NOT spouse) in `IHTCalculationService`
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- [ ] **3B.2** Extend `SpouseNRBTrackerService` to include CLTs (change PET-only filter to `whereIn('gift_type', ['pet', 'clt'])`)
  - **Agent:** `feature-dev`

- [ ] **3B.3** 14-year rule enforcement (Direction B: historical CLTs 7-14 years reduce NRB for PETs in final 7 years) in `IHTCalculationService` and `SpouseNRBTrackerService`
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- [ ] **3B.4** RNRB direct descendant clarification — explicitly exclude nieces/nephews/cousins/siblings in message
  - **Agent:** `feature-dev`

- [ ] **3B.5** Liquidity reclassification in `AssetLiquidityAnalyzer`: investments -> semi-liquid, pensions -> illiquid
  - **Agent:** `feature-dev`

- [ ] **3B.6** Trust NRB avoidance forward projection in `PersonalizedTrustStrategyService` (add AssumptionsService + RiskPreferenceService DI, year-by-year trajectory)
  - **Agent:** `feature-dev`

- [ ] **3B.7** Enhanced life insurance checks in `LifeCoverCalculator` (trust status, joint life, whole of life)
  - **Agent:** `feature-dev`

- [ ] **3B.8** 2027 pension IHT dual-scenario projection in `IHTCalculationService` + include in `EstateAgent` analysis response
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- [ ] **3B.9** Create estate notification classes: `GiftExemptionNotification` (7-year), `TrustAnniversaryNotification` (10-year)
  - **Agent:** `feature-dev`

- [ ] **3B.10** Create `SendEstateAlerts` command (daily schedule, `today()` not `now()`, chunk 100) and register in Kernel. Include annual IHT recalculation prompt (once per tax year) in addition to gift exemption and trust anniversary checks
  - **Agent:** `feature-dev`

- [ ] **3B.11** Update `NotificationPreference` model: add estate alert preference columns
  - **Agent:** `feature-dev`

### 3C: Investment Pipeline (Sequential — largest piece)

- [ ] **3C.1** Create `Services/Investment/Recommendation/UserContextBuilder.php` (TWO methods: `build(User)` from scratch + `buildFromExisting()` from InvestmentPlanService data; ISA allowance must account for ALL ISA types)
  - **Agent:** `feature-dev` | **Skill:** `/feature-dev`

- [ ] **3C.2** Create `Services/Investment/Recommendation/LifeEventAssessmentService.php` (20+ event types producing modifiers: blocked_wrappers, prioritised_wrappers, liquidity_priority)
  - **Agent:** `feature-dev`

- [ ] **3C.3** Create `Services/Investment/Recommendation/GoalAssessmentService.php` (goal-to-wrapper mapping by timeline: SHORT/MEDIUM/LONG/VERY_LONG)
  - **Agent:** `feature-dev`

- [ ] **3C.4** Create `Services/Investment/Recommendation/SafetyCheckService.php` (7 safety checks reducing surplus; CRITICAL: must NOT emit standalone emergency fund recommendations — Savings engine owns those)
  - **Agent:** `feature-dev`

- [ ] **3C.5** Create `Services/Investment/Recommendation/ContributionWaterfallService.php` — CORE ENGINE (11 sequential steps: LISA -> S&S ISA -> Pension -> Premium Bonds -> NS&I -> Offshore Bond -> Onshore Bond -> Pension Carry Forward -> VCT/EIS/SEIS -> GIA; all limits from TaxConfigService)
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- [ ] **3C.6** Create `Services/Investment/Recommendation/TransferRecommendationService.php` (13 independent scans; delegates to existing BedAndISACalculator + CGTHarvestingCalculator)
  - **Agent:** `feature-dev`

- [ ] **3C.7** Create `Services/Investment/Recommendation/SpouseOptimisationService.php` (7 strategies: CGT sharing, ISA coordination, PSA optimisation, pension coordination, non-earning spouse pension, Marriage Allowance, IHT planning)
  - **Agent:** `feature-dev`

- [ ] **3C.8** Create `Services/Investment/Recommendation/ConflictResolutionService.php` (merge waterfall + triggers + transfers + spouse, deduplicate, prioritise)
  - **Agent:** `feature-dev`

- [ ] **3C.9** Create `Services/Investment/Recommendation/RecommendationOutputFormatter.php` (structured API response with sections)
  - **Agent:** `feature-dev`

- [ ] **3C.10** Integrate pipeline into `InvestmentPlanService::getRecommendations()` (NOT `InvestmentAgent::analyze()` which remains UNCHANGED) + disable 6 overlapping InvestmentActionDefinition triggers
  - **Agent:** `feature-dev` | **Review:** `superpowers:code-reviewer`
  - **CRITICAL:** Launch gate — disable 6 triggers in same deployment

- [ ] **3C.11** Update `InvestmentController::recommendations()` for structured pipeline output
  - **Agent:** `feature-dev`

- [ ] **3C.12** Address `ContributionOptimizer::generateRecommendations()` conflict: its independent hard-coded recommendations (`isa_allowance`, `pension_tax_relief`, `lump_sum_strategy`, `auto_increase`) overlap with waterfall steps 2 and 3. Disable or reroute to avoid duplicates. Also check `calculateContributionEfficiency()` for Rule #13 score violation (DA-Investment M8)
  - **Agent:** `feature-dev`

### 3D: Protection Engine Expansion

- [ ] **3D.1** Integrate employer benefits into `CoverageGapAnalyzer` gap analysis (death-in-service uses `User.annual_employment_income` directly, add employer reliance warning >50%, SSP offset: `$sspWeekly * $maxWeeks` NOT annualised, self-employed get no SSP)
  - **Agent:** `feature-dev`

- [ ] **3D.2** Add ~17 new triggers to `ProtectionActionDefinitionSeeder` (employer benefits, state benefits, life insurance, IP, CI, children, spouse categories)
  - **Agent:** `feature-dev`

- [ ] **3D.3** Add ~17 new private evaluator methods + match branches to `ProtectionActionDefinitionService` (triggers depending on Phase 2 columns must handle null gracefully)
  - **Agent:** `feature-dev`

- [ ] **3D.4** Frontend cleanup: remove gap calculations from `GapAnalysis.vue` and `ProtectionOverviewCard.vue`, use backend data from Vuex store
  - **Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- [ ] **3D.5** Add joint_life checkbox + employer benefits section to `PolicyFormModal.vue`
  - **Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- [ ] **3D.6** Standardise multipliers (frontend 75%/2x -> backend 60%/3x from TaxConfigService)
  - **Agent:** `feature-dev`

- [ ] **3D.7** Protection notification system: verify `PolicyRenewalNotification` covers policies expiring within 24/12/3 months. Create or update `SendProtectionAlerts` command for expired policies and annual protection review prompt. Register in Kernel (database channel only)
  - **Agent:** `feature-dev`

### 3E: Retirement Engine Expansion

- [ ] **3E.1** Create `Services/Retirement/SalarySacrificeAnalyzer.php` (NI savings calculation, NMW/NLW proxy floor check using auto-enrolment £10,000 threshold, warnings for >20% sacrifice, below personal allowance, NI LEL)
  - **Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- [ ] **3E.2** Add auto-enrolment threshold checking to `ContributionOptimizer` (earnings trigger, lower/upper QE, minimum 8%)
  - **Agent:** `feature-dev`

- [ ] **3E.3** Wire smoker/health from `$user->protectionProfile?->smoker_status` into `DecumulationPlanner` for enhanced annuity rates (NOT `$user->smoker_status`)
  - **Agent:** `feature-dev`

- [ ] **3E.4** Wire care costs from `RetirementProfile` into `DecumulationPlanner` via `RetirementAgent`
  - **Agent:** `feature-dev`

- [ ] **3E.5** Add ~8 new triggers to `RetirementActionDefinitionSeeder` (salary_sacrifice_available, salary_sacrifice_floor_warning, auto_enrolment_below_minimum, enhanced_annuity_eligible, care_costs_not_modelled, state_pension_no_forecast, approaching_decumulation, pension_consolidation_opportunity) + corresponding evaluator methods to `RetirementActionDefinitionService`
  - **Agent:** `feature-dev`

### Sprint 3 Gate

- [ ] **GATE-10** Run full test suite: `./vendor/bin/pest`
- [ ] **GATE-11** Run seeder: `php artisan db:seed`
- [ ] **GATE-12** Clear cache after agent/service refactors: `php artisan cache:clear` (30-minute stale cache risk — DA-Cash M5)
  - **Command:** `php artisan cache:clear`
- [ ] **GATE-13** Test each module's recommendation output with preview personas
  - **Skill:** `superpowers:verification-before-completion`
- [ ] **GATE-14** Verify no duplicate recommendations across Savings/Investment engines
- [ ] **GATE-15** Code review Sprint 3
  - **Agent:** `superpowers:code-reviewer`
  - **Skill:** `/code-review`

---

## SPRINT 4: Frontend & Cross-Module Integration

**Goal:** Build frontend components and verify cross-module coordination.

### 4.1 Cash/Savings Frontend

- [ ] **4.1.1** Enhance `Recommendations.vue` with structured recommendation cards (priority badge, headline, description, decision path, action links)
  - **Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- [ ] **4.1.2** Create `SavingsDecisionPath.vue` (collapsible decision trail display)
  - **Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- [ ] **4.1.3** Create `MissingDataCard.vue` (clickable missing data -> input form navigation)
  - **Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- [ ] **4.1.4** Update Vuex `savings.js` store (add psaPosition, fscsExposure, decisionPaths, missingData state + fetch actions)
  - **Agent:** `feature-dev`

### 4.2 Estate Planning Frontend

- [ ] **4.2.1** Substantial rewrite of `MissingDataAlert.vue` (12 checks, grouped by severity blocking/warning/info, specific form links — not just /profile)
  - **Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- [ ] **4.2.2** Update `EstateDashboard.vue` (`can_proceed` computed property, conditional render of MissingDataAlert vs analysis tabs)
  - **Agent:** `feature-dev`

- [ ] **4.2.3** 2027 pension amendment notification banner (when `amendment_warning = true`)
  - **Agent:** `feature-dev` | **Review:** `premium-ui-designer`

### 4.3 Investment Frontend

- [ ] **4.3.1** Create readiness gate component (replace "No recommendations available" empty state with actionable readiness display)
  - **Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- [ ] **4.3.2** Handle new structured pipeline output in `RecommendationsSection.vue`
  - **Agent:** `feature-dev`

### 4.4 Retirement Frontend

- [ ] **4.4.1** Salary sacrifice analysis display (NI savings, warnings, net cost comparison)
  - **Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- [ ] **4.4.2** Auto-enrolment compliance status display
  - **Agent:** `feature-dev`

### 4.5 CoordinatingAgent Integration

- [ ] **4.5.1** Verify `PriorityRanker::rankRecommendations()` handles new recommendation shapes from all modules
  - **Agent:** `feature-dev`

- [ ] **4.5.2** Add field mapping in `CoordinatingAgent::mapSavingsAnalysis()` if needed for new Savings engine output
  - **Agent:** `feature-dev`

- [ ] **4.5.3** Verify conflict resolution includes Savings recommendations (emergency fund = highest priority)
  - **Agent:** `feature-dev`

- [ ] **4.5.4** Verify surplus allocation ordering across all modules is correct
  - **Agent:** `feature-dev`

### Sprint 4 Gate

- [ ] **GATE-16** Full manual browser testing of all modules with all 6 preview personas
  - **Command:** `php artisan db:seed` (before EVERY persona test)
  - **Skill:** `superpowers:verification-before-completion`
- [ ] **GATE-17** No new `amber-*`, `orange-*`, or non-palette colors
- [ ] **GATE-18** No acronyms in user-facing text
- [ ] **GATE-19** No scores in user-facing UI (Rule #13)
- [ ] **GATE-20** Code review Sprint 4
  - **Agent:** `superpowers:code-reviewer`

---

## SPRINT 5: Testing, Verification & Cleanup

**Goal:** Comprehensive testing, architecture validation, cleanup.

### 5.1 Architecture Tests

- [ ] **5.1.1** Create architecture test: No hardcoded PSA values in any service file
  - **Agent:** `feature-dev` | **Skill:** `superpowers:test-driven-development`

- [ ] **5.1.2** Create architecture test: No hardcoded `0.06` in Investment services
  - **Agent:** `feature-dev`

- [ ] **5.1.3** Create architecture test: No hardcoded `0.047` or `0.04` in Retirement services
  - **Agent:** `feature-dev`

- [ ] **5.1.4** Create architecture test: No hardcoded growth rates in Estate services
  - **Agent:** `feature-dev`

- [ ] **5.1.5** Create architecture test: No hardcoded protection values (withdrawal rates, final expenses, education costs, premium factors, income multipliers) in Protection services
  - **Agent:** `feature-dev`

- [ ] **5.1.6** Create architecture test: No `EstateDefaults::ESTIMATED_*` references anywhere
  - **Agent:** `feature-dev`

### 5.2 Unit Tests per Module

> Use parallel subagents for independent module test suites.

- [ ] **5.2.1** Savings tests: ~40 evaluator method tests, PSACalculator, FSCSAssessor, pipeline integration, migration/seeder idempotency tests, goal-account pivot data migration test, feature tests for `/api/savings/recommendations` endpoint, notification class unit tests, scheduled command feature test
  - **Agent:** `feature-dev` | **Skill:** `superpowers:test-driven-development`

- [ ] **5.2.2** Estate tests: 12 readiness checks, PET NRB deduction, 14-year rule, trust NRB avoidance projection, surplus income fetch integration test, policy assessment warnings, 2027 pension amendment dual-scenario feature test, notification class tests, scheduled command test
  - **Agent:** `feature-dev` | **Skill:** `superpowers:test-driven-development`

- [ ] **5.2.3** Investment tests: UserContextBuilder assembly, 12 readiness checks, 20+ life event types, 6 goal types x 4 timelines, 7 safety checks (surplus reduction), 11 waterfall steps integration tests with various user profiles, 13 transfer scans, 7 spouse strategies, conflict resolution (priority ordering + deduplication), full pipeline end-to-end integration test
  - **Agent:** `feature-dev` | **Skill:** `superpowers:test-driven-development`

- [ ] **5.2.4** Protection tests: 14 readiness checks, ~17 evaluator methods, employer benefit integration in gap analysis, state benefit offset (SSP formula), migration tests, seeder idempotency, notification scheduling feature test
  - **Agent:** `feature-dev` | **Skill:** `superpowers:test-driven-development`

- [ ] **5.2.5** Retirement tests: 11 readiness checks, salary sacrifice (NMW proxy floor, NI savings, 20%+ sacrifice warning), auto-enrolment threshold checking, enhanced annuity rates (smoker/health), care costs in decumulation integration test
  - **Agent:** `feature-dev` | **Skill:** `superpowers:test-driven-development`

### 5.3 Cross-Module Integration Tests

- [ ] **5.3.1** Test: Emergency fund owned by Savings engine; Investment SafetyCheckService only gates surplus
  - **Agent:** `feature-dev`

- [ ] **5.3.2** Test: ISA allowance shared £20,000 across Cash ISA (Savings) and S&S ISA (Investment)
  - **Agent:** `feature-dev`

- [ ] **5.3.3** Test: CoordinatingAgent priority ordering correct across all modules
  - **Agent:** `feature-dev`

- [ ] **5.3.4** Test: No duplicate recommendations across modules
  - **Agent:** `feature-dev`

### 5.4 Preview Persona End-to-End Testing

- [ ] **5.4.1** Test Young Family (James & Emily Carter): mortgage, workplace pensions, children's savings
  - **Command:** `php artisan db:seed` then manual browser test

- [ ] **5.4.2** Test Peak Earners (David & Sarah Mitchell): multiple properties, SIPP + NHS pension, spouse optimisation
  - **Command:** `php artisan db:seed` then manual browser test

- [ ] **5.4.3** Test Widow (Margaret Thompson): estate planning, 2027 pension amendment
  - **Command:** `php artisan db:seed` then manual browser test

- [ ] **5.4.4** Test Entrepreneur (Alex Chen): SIPP, self-employed protection, salary sacrifice N/A
  - **Command:** `php artisan db:seed` then manual browser test

- [ ] **5.4.5** Test Young Saver (John Morgan): emergency fund, first-time savings, LISA eligibility
  - **Command:** `php artisan db:seed` then manual browser test

- [ ] **5.4.6** Test Retired Couple (Robert & Patricia Williams): decumulation, care costs, enhanced annuity
  - **Command:** `php artisan db:seed` then manual browser test

### 5.5 Cleanup

- [ ] **5.5.1** Add docblock to CashAccount model clarifying it's NOT part of savings engine; verify excluded from savings recommendation analysis
  - **Agent:** `feature-dev`

- [ ] **5.5.2** Verify legacy SavingsGoal deprecated endpoints still work; verify migration banner in SavingsGoals.vue points to Goals module
  - **Agent:** `feature-dev`

### Sprint 5 / Final Gate

- [ ] **GATE-21** Full test suite passes: `./vendor/bin/pest`
- [ ] **GATE-22** All architecture tests pass
- [ ] **GATE-23** All 6 personas produce correct, non-duplicated recommendations
- [ ] **GATE-24** Final seed: `php artisan db:seed`
- [ ] **GATE-25** Format: `./vendor/bin/pint`
- [ ] **GATE-26** Final code review
  - **Agent:** `superpowers:code-reviewer`
  - **Skill:** `/code-review`

---

## Final Acceptance Criteria

- [ ] ALL 5 modules produce structured recommendations with decision paths
- [ ] ALL modules have data readiness gates (no assumptions, no auto-creation)
- [ ] ALL hardcoded tax/financial values centralised in TaxConfigService
- [ ] ALL notification systems active (database channel, daily schedule)
- [ ] ALL 6 preview personas produce correct, non-duplicated recommendations
- [ ] ALL architecture tests pass (grep checks + Pest architecture suite)
- [ ] CoordinatingAgent correctly prioritises across all modules
- [ ] No duplicate recommendations across Savings/Investment engines
- [ ] Emergency fund owned exclusively by Savings engine; Investment only gates surplus
- [ ] ISA allowance correctly shared between Cash ISA and S&S ISA recommendations

---

## Quick Reference: Agent & Skill Assignments

| Agent / Skill | Primary Use | Sprints |
|---|---|---|
| `feature-dev` / `/feature-dev` | ALL implementation tasks (primary) | 1-5 |
| `tax-compliance-reviewer` | After ANY TaxConfigService/tax calc change | 1, 3 |
| `superpowers:code-reviewer` / `/code-review` | After each sprint completion | 1-5 |
| `database-optimizer` | Migrations, schema design | 2 |
| `premium-ui-designer` | Frontend components (decision paths, cards) | 4 |
| `security-reviewer` | New API endpoints, controller changes | 3, 4 |
| `Explore` | Understanding existing patterns before implementing | 1, 3 |
| `superpowers:test-driven-development` | Sprint 5 unit test creation | 5 |
| `superpowers:verification-before-completion` | Before claiming any sprint done | 1-5 |
| `superpowers:dispatching-parallel-agents` | Sprint 2 readiness gates (5 modules) | 2 |
| `/systematic-debugging` | When tests fail after refactoring | Any |
| `superpowers:brainstorming` | Design questions during implementation | Any |

## Quick Reference: Critical Commands

| Command | When |
|---|---|
| `php artisan db:seed` | After EVERY backend change, before EVERY test |
| `./vendor/bin/pest` | After every sprint gate |
| `./vendor/bin/pint` | Before any commit |
| `php artisan cache:clear` | After any agent/service refactor |
| `grep -rn '0\.06[^0-9]' app/Services/Investment/` | After Sprint 1 Task 1.4 |
| `grep -rn '0\.047\|0\.04[^0-9]' app/Services/Retirement/` | After Sprint 1 Task 1.6 |
| `php artisan route:list --path=savings` | After Sprint 3A controller changes |

## Summary Stats

| Sprint | Tasks | New Files | Modified Files | Migrations |
|---|---|---|---|---|
| Sprint 1 | 46 | 0 | ~38 | 0 |
| Sprint 2 | 23 | 5 (readiness) | ~18 | 4 |
| Sprint 3 | 41 | ~23 (engines) | ~25 | 1 (notification prefs) |
| Sprint 4 | 14 | 3 (Vue) | ~10 | 0 |
| Sprint 5 | 17 | ~10 (tests) | ~2 | 0 |
| **Total** | **119 tasks + 26 gates** | **~41** | **~93** | **5** |

## Devil's Advocate Review Audit Trail

> Review performed 2026-03-14 against all 5 DA review files. 23 gaps found and patched:
>
> | Gap | Added As | Source |
> |---|---|---|
> | FeeAnalyzer OCF threshold | 1.4.4 | investment plan Phase 0.1 |
> | FutureValueCalculator property growth | 1.5.3 | estate plan Phase 0.4 |
> | RetirementIncomeService DEFAULT_GROWTH_RATE | 1.10.7 | DA-Retirement I6 |
> | RetirementAgent growth rate lines 262, 305 | 1.10.8 | retirement plan Phase 0.5 |
> | Retirement test mock updates | 1.10.9 | retirement plan Phase 0.8 |
> | Goal/SavingsAccount model relationships | 2.1.8 | cash plan Phase 1.2 |
> | linked_account_ids audit | 2.1.9 | DA-Cash M4 |
> | ProtectionController auto-create removal | 2.2.7 | protection plan Phase 1.2 |
> | EstateAgent generateRecommendations() gate | 2.2.8 | DA-Estate H3 |
> | ProtectionAgent analysis enhancement | 2.2.9 | protection plan Phase 2 |
> | FinancialHealthScore.vue null handling | 2.4.6 | DA-Retirement I9 |
> | SavingsAgent analyze() enhancement | 3A.14 | cash plan Phase 3.1 |
> | SavingsController index() response | 3A.15 | cash plan Phase 3.3 |
> | NotificationPreference savings migration | 3A.16 | cash plan Phase 4.2 |
> | LiquidityAnalyzer FSCS overlay | 3A.17 | cash plan Phase 2.2 |
> | Emergency fund divergence documentation | 3A.18 | DA-Cash H1 |
> | BasePlanService signature fix | 3A.19 | DA-Cash M2 |
> | Estate NotificationPreference update | 3B.11 | estate plan Phase 5 |
> | Annual IHT recalculation prompt | 3B.10 (expanded) | estate plan Phase 5.3 |
> | ContributionOptimizer conflict + Rule #13 | 3C.12 | DA-Investment M8 |
> | Protection notifications | 3D.7 | protection plan Phase 4 |
> | Cache clear in Sprint 3 gate | GATE-12 | DA-Cash M5 |
> | Expanded Sprint 5 test coverage | 5.2.1-5.2.5 (expanded) | all 5 plan testing sections |
