# Decision Engine Upgrade: Master Implementation Plan

> **Date:** 2026-03-14 | **Version:** 1.0
> **Scope:** 5 modules (Cash/Savings, Estate Planning, Investment, Protection, Retirement)
> **Source:** 5 research documents, 5 implementation plans, 5 devil's advocate reviews (all amendments incorporated)

---

## Table of Contents

1. [Strategic Overview](#strategic-overview)
2. [Execution Order & Dependencies](#execution-order--dependencies)
3. [Sprint Structure](#sprint-structure)
4. [Detailed Task List](#detailed-task-list)
5. [Agent & Skill Assignments](#agent--skill-assignments)
6. [Risk Register](#risk-register)
7. [Verification Gates](#verification-gates)

---

## 1. Strategic Overview

### What We're Building

A comprehensive decision engine upgrade across all 5 Fynla financial planning modules. Each module gets:

1. **Hardcoded value centralisation** - All tax/financial constants moved to TaxConfigService
2. **Data readiness gates** - Granular user data checks replacing assumptions/auto-creation
3. **Expanded recommendation engines** - Database-driven action definitions with full decision paths
4. **"What Drives This" UI** - Missing data cards linking to input forms, decision path display
5. **Notification systems** - Maturity alerts, expiry warnings, review prompts

### Scale of Work

| Module | New Files | Modified Files | New DB Triggers | Complexity |
|--------|-----------|---------------|-----------------|------------|
| Cash/Savings | 16 | 19 | ~40 | **High** (engine rebuild) |
| Estate Planning | 5 | 17 | 0 (future phase) | **Medium** (targeted enhancements) |
| Investment | 10 | 16 | 0 (pipeline replaces triggers) | **Very High** (new pipeline from scratch) |
| Protection | 3 | 16 | ~17 new | **Medium** (extend existing pattern) |
| Retirement | 2 | 12 | ~8 new | **Medium** (centralise + new features) |
| **Total** | **36** | **~80** | **~65** | |

### Architecture Patterns

```
MODULE TYPE A — DB-Driven Triggers (Savings, Protection, Retirement)
====================================================================
ActionDefinition (DB model) → ActionDefinitionService (evaluators) → Agent → PlanService → CoordinatingAgent

MODULE TYPE B — Sequential Pipeline + Triggers (Investment)
============================================================
UserContextBuilder → DataReadiness → LifeEvents → Goals → Safety
  → ContributionWaterfall → Transfers → Spouse → Conflicts → Output
  + InvestmentActionDefinitionService (secondary, portfolio health triggers)

MODULE TYPE C — Targeted Enhancement (Estate Planning)
=======================================================
Existing 22 services → Fix hardcodes → Add readiness gate → Enhance IHT calc
  → Add gifting/trust features → Add 2027 pension amendment
```

---

## 2. Execution Order & Dependencies

### Critical Path

```
SPRINT 1: CROSS-CUTTING FOUNDATION
====================================
All modules share TaxConfigService. The seeder must be updated ONCE with ALL
module configs before any service refactoring begins.

    TaxConfigSeeder (ALL modules)
         |
    PSA Migration (Cash + Investment)
         |
    Growth Rate Removal (All modules)
         |
    Income Trait Standardisation (Estate, Protection, Investment)

SPRINT 2: DATA READINESS GATES (all 5 modules — parallelisable)
================================================================
Each module gets its own DataReadinessService. These are independent.

    Cash Readiness ──── Estate Readiness ──── Investment Readiness
                   ──── Protection Readiness ──── Retirement Readiness

SPRINT 3: MODULE ENGINES (partially parallelisable)
====================================================
    Cash: SavingsActionDefinitionService (40 triggers)
    Estate: IHT calc fixes + gifting/trust enhancements
    Investment: Pipeline phases 2-5 (sequential)
    Protection: Evaluator methods (17 new)
    Retirement: Salary sacrifice + auto-enrolment + annuity

SPRINT 4: INTEGRATION & FRONTEND
==================================
    Agent/controller refactors
    Launch gates (disable overlapping triggers)
    Frontend: What Drives This, decision paths, missing data cards
    Notifications

SPRINT 5: TESTING & CLEANUP
==============================
    Full test suite
    Architecture tests (no hardcoded values)
    Cross-module conflict resolution verification
    Preview persona testing
```

### Hard Dependencies (MUST respect)

| Dependency | Reason |
|------------|--------|
| Seeder before ANY service change | Services read from TaxConfigService; missing keys = null errors |
| `current_spa` key before retirement age migration | Without it, all 5 age fallbacks return null |
| PSA in TaxConfigService before PSA migration | 6 files reference `getPersonalSavingsAllowance()` |
| Goal-account observer update before pivot table FK deprecation | Silent contribution tracking failure |
| Investment launch gate before pipeline goes live | Duplicate recommendations from 6 overlapping triggers |
| Savings launch gate before engine goes live | Duplicate recommendations from 7 overlapping triggers |
| Employer benefits migration before employer trigger evaluators | Columns must exist for evaluators to query |
| `joint_life` migration before life insurance checks | `$policy->joint_life` returns null without column |

---

## 3. Sprint Structure

### Sprint 1: Cross-Cutting Foundation (TaxConfigService)

**Goal:** Centralise ALL hardcoded values across ALL modules into TaxConfigService. Zero code changes to business logic yet — just wiring.

#### 1.1 TaxConfigurationSeeder — Master Update
**Agent:** `feature-dev` | **Skill:** `/feature-dev` | **Command:** `php artisan db:seed --class=TaxConfigurationSeeder --force`

Add ALL of the following sections in ONE seeder update:

```
- income_tax.personal_savings_allowance (basic: 1000, higher: 500, additional: 0)
- income_tax.starting_rate_for_savings (band: 5000, rate: 0)
- savings.fscs_deposit_protection (85000), fscs_joint_protection (170000), etc.
- savings.premium_bonds_max_holding (50000), parental_settlement_threshold (100)
- estate.onboarding_estimates (property: 300000, investment: 50000, etc.)
- estate.insurance_premium_estimates (per_thousand_monthly by age/gender)
- inheritance_tax.pension_iht_inclusion (effective_date: 2027-04-06)
- assumptions.growth_by_risk (very_low: 0.02 through high: 0.07)
- investment.asset_class_yields, fee_benchmarks, portfolio_thresholds
- investment.waterfall (premium_bonds_max, offshore_bond_minimum, etc.)
- investment.venture_capital (SEIS, EIS, VCT limits)
- investment.safety (critical_debt_rate, emergency fund gates)
- investment.transfers (cash_excess_buffer, consolidation triggers)
- protection.income_multipliers, ip_max_benefit_percent, ci_multiplier
- protection.education_cost_per_year (9000), final_expenses (7500)
- protection.affordability thresholds, premium_factors, withdrawal_rates
- protection.ipt (standard_rate: 0.12, life/CI/IP exempt)
- benefits.ssp (weekly_rate: 116.75, max_weeks: 28), esa, universal_credit, pip
- benefits.bereavement_support (higher/lower lump sums + monthly)
- retirement.accumulation_to_decumulation_years (10)
- retirement.withdrawal_rates (sustainable: 0.047, safe: 0.04, gia: 0.04)
- retirement.target_income_percent (0.75), projection_end_age (100)
- retirement.monte_carlo_iterations (1000), compounding_periods (4)
- retirement.annuity_rate_estimates (by age, single/joint)
- pension.state_pension.current_spa (66), future_spa (67)
- pension.salary_sacrifice (nlw_hourly, nmw_hourly, conservative_proxy_floor: 10000)
- pension.auto_enrolment (earnings_trigger: 10000, lower_qe: 6240, etc.)
```

#### 1.2 TaxConfigService Helper Methods
**Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

Add methods:
- `getPersonalSavingsAllowance(?string $taxBand = null): int|array`
- `getSavingsConfig(?string $key = null): mixed`

#### 1.3 PSA Hardcode Removal (6 files)
**Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

Replace hardcoded PSA values in:
1. `UKTaxCalculator.php` (lines 673-677)
2. `Tax/TaxOptimisationService.php` (lines 217-220)
3. `Tax/TaxProductInfoService.php` (lines 150-151)
4. `Investment/Tax/TaxOptimizationAnalyzer.php`
5. `Investment/AssetLocation/AssetLocationOptimizer.php`
6. `Investment/AssetLocation/TaxDragCalculator.php` (rate-to-band conversion needed)

#### 1.4 Growth Rate Removal — Investment Module (15+ locations, 12 files)
**Agent:** `feature-dev` with parallel subagents | **Review:** `tax-compliance-reviewer`

Replace ALL hardcoded `0.06` / `0.065` with `RiskPreferenceService::getReturnParameters($riskLevel)['expected_return_typical']`:
- `PortfolioStrategyService.php` (1)
- `Fees/OCFImpactCalculator.php` (4)
- `Goals/ShortfallAnalyzer.php` (4)
- `Goals/GoalProgressAnalyzer.php` (4)
- `Tax/ISAAllowanceOptimizer.php` (1)
- `Tax/BedAndISACalculator.php` (1)
- `Tax/TaxOptimizationAnalyzer.php` (2)
- `FeeAnalyzer.php` (2)
- `ContributionOptimizer.php` (2 at lines 515, 518)
- `Analytics/HoldingsDataExtractor.php` (4 at lines 226, 230, 250, 253)
- `Analytics/PortfolioStatisticsCalculator.php` (2 at lines 285, 302)
- `AssetLocation/TaxDragCalculator.php` (2 additional)

Also remove income fallback in `AssetLocationOptimizer.php` line 93 (`?? 50000`), use `ResolvesIncome` trait.

**Post-migration check:** `grep -rn '0\.06[^0-9]' app/Services/Investment/` must return zero matches.

#### 1.5 Growth Rate Removal — Estate Module (1 true hardcode)
**Agent:** `feature-dev`

Fix `LifePolicyStrategyService.php`:
- Add `AssumptionsService` constructor injection
- Replace `INVESTMENT_RETURN_RATE = 0.047` constant
- Move hardcoded premium table to TaxConfigService

Verify `IHTCalculationService` and `LifeCoverCalculator` already defer to `AssumptionsService` (no change needed).

#### 1.6 Withdrawal Rate Centralisation — Retirement (16+ locations, 7 files)
**Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

Replace ALL hardcoded withdrawal rates:
- `RetirementProjectionService.php` — `SUSTAINABLE_WITHDRAWAL_RATE = 0.047`
- `RequiredCapitalCalculator.php` — `DEFAULT_WITHDRAWAL_RATE = 0.047`
- `RetirementIncomeService.php` — `ISA_WITHDRAWAL_RATE = 0.047`, `GIA_WITHDRAWAL_RATE = 0.04`
- `RetirementStrategyService.php` — **9 inline 0.047 literals** (lines 855, 880, 901, 1588, 1669, 1833, 1895, 1900, 2007)
- `PensionProjector.php` line 195 — inline `0.04`
- `DecumulationPlanner.php` line 139 — `0.04` in PCLS calc
- `ContributionOptimizer.php` line 143 — inline `0.04`

Also fix: inflation (2.0% vs 2.5% conflict), retirement age (67 vs 68 conflict), growth rate (5% hardcoded), employer match threshold (5%), annuity rates, Monte Carlo iterations, compounding periods.

**Post-migration check:** `grep -rn '0\.047\|0\.04[^0-9]' app/Services/Retirement/` — zero matches.

#### 1.7 Hardcode Removal — Protection (10 locations, 3 files)
**Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

Replace in `CoverageGapAnalyzer.php`:
- `/ 0.047` withdrawal rate → `TaxConfigService::get('protection.withdrawal_rates.human_capital')`
- `7500` final expenses, `9000` education cost, `0.60` IP max

Replace in `RecommendationEngine.php`:
- `0.50` premium base, `1.5` smoker loading, `2.5` CI ratio, `0.02` IP rate

Replace in `ScenarioBuilder.php`:
- `0.03` withdrawal rate → `TaxConfigService::get('protection.withdrawal_rates.scenario')`

Replace in `AdequacyScorer.php`:
- `3` CI multiplier

**CRITICAL:** Add `TaxConfigService` constructor injection to all 3 services. Update corresponding tests (they instantiate directly with `new`).

#### 1.8 EstateDefaults Cleanup
**Agent:** `feature-dev`

Remove estimated value constants from `EstateDefaults.php`:
- `ESTIMATED_PROPERTY_VALUE`, `ESTIMATED_INVESTMENT_VALUE`, `ESTIMATED_SAVINGS_VALUE`, `ESTIMATED_BUSINESS_VALUE`
- `DEFAULT_LIFE_EXPECTANCY`, `DEFAULT_CURRENT_AGE`

Retain but source from TaxConfigService:
- `RNRB_TAPER_THRESHOLD`, `TRUST_SUGGESTION_THRESHOLD`, `COMBINED_NRB_THRESHOLD`, `COMBINED_RNRB_THRESHOLD`

Update `EstateOnboardingFlow.php` to use `TaxConfigService::get('onboarding_estimates')`.

#### 1.9 Income Trait Standardisation
**Agent:** `feature-dev`

Add `ResolvesIncome` + `ResolvesExpenditure` traits to:
- `Estate/GiftingStrategyOptimizer.php`
- `Estate/PersonalizedGiftingStrategyService.php`
- `Protection/CoverageGapAnalyzer.php`
- `Protection/RecommendationEngine.php`
- `Investment/AssetLocation/AssetLocationOptimizer.php`

#### 1.10 Retirement Miscellaneous Constants
**Agent:** `feature-dev`

- Move accumulation/decumulation trigger from `RetirementAgent.php` line 137
- Move annuity rate estimates from `DecumulationPlanner.php`
- Fix `AssumptionsService` DEFAULT_RETIREMENT_AGE from 68 to SPA from TaxConfigService
- Fix `RetirementIncomeService` DEFAULT_STATE_PENSION_AGE (3 usages at lines 578, 581, 590)
- Fix `PensionProjector` DEFAULT_GROWTH_RATE and inline 0.04

**SPRINT 1 GATE:** Run `./vendor/bin/pest` — ALL existing tests must pass. Then `php artisan db:seed`.

---

### Sprint 2: Database Migrations & Data Readiness Gates

#### 2.1 Database Migrations
**Agent:** `feature-dev` | **Review:** `database-optimizer`

| Migration | Module | Purpose |
|-----------|--------|---------|
| `create_savings_action_definitions_table` | Savings | Mirror investment_action_definitions |
| `create_goal_savings_account_table` | Savings | Many-to-many pivot for goal-account linking |
| `add_employer_benefits_to_protection_profiles` | Protection | 7 new columns (death_in_service_multiple, group_ip, etc.) |
| `add_joint_life_to_life_insurance_policies` | Protection/Estate | Boolean column for joint life status |

**Data migration:** Copy `linked_savings_account_id` values into pivot table. Update `SavingsAccountGoalObserver` and `TracksGoalContributions` trait to query pivot table BEFORE deprecating FK.

**Command:** `php artisan migrate && php artisan db:seed`

#### 2.2 Data Readiness Services (5 modules — parallelisable)
**Agent:** `superpowers:dispatching-parallel-agents` with 5 `feature-dev` subagents

Each creates a `{Module}DataReadinessService.php`:

| Module | File | Checks | Blocking |
|--------|------|--------|----------|
| Savings | `Services/Savings/SavingsDataReadinessService.php` | Included in SavingsActionDefinitionService triggers | Missing DOB, income, expenditure |
| Estate | `Services/Estate/EstateDataReadinessService.php` | 12 checks | DOB, marital status, at least one asset |
| Investment | `Services/Investment/Recommendation/DataReadinessService.php` | 12 checks | DOB, income, risk profile, expenditure |
| Protection | `Services/Protection/ProtectionDataReadinessService.php` | 14 checks | DOB, income, marital status |
| Retirement | `Services/Retirement/RetirementDataReadinessService.php` | 11 checks | DOB, marital status, income |

**Integration into agents:** Each agent's `analyze()` method calls the readiness service first. Returns full response envelope with all expected keys as null when `can_proceed = false`.

**Frontend:** Each module's dashboard needs `can_proceed` handling + `MissingDataAlert` component. These must be updated BEFORE the readiness gate is deployed.

#### 2.3 Score Removal — Protection (Rule #13 Fix)
**Agent:** `feature-dev` | **Review:** `superpowers:code-reviewer`

- Remove numeric score keys from `ComprehensiveProtectionPlanService::generateExecutiveSummary()`
- Refactor `getRecommendedAction()` to use category strings
- Update Vuex `adequacyScore` getter to return rating string

#### 2.4 Vuex Store Guards (all modules)
**Agent:** `feature-dev`

Add `can_proceed` / `success: false` handling to:
- `store/modules/savings.js`
- `store/modules/estate.js` (via `analyseEstate` action)
- `store/modules/investment.js`
- `store/modules/protection.js`
- `store/modules/retirement.js`

Guard against `retirementReadinessScore = 100` false positive when analysis is null.

**SPRINT 2 GATE:** Run `./vendor/bin/pest`. Run `php artisan db:seed`. Test each module's dashboard with a user missing required data — should see readiness gates, not errors.

---

### Sprint 3: Module Engines

#### 3A: Cash/Savings Engine (Sequential)

##### 3A.1 SavingsActionDefinition Model
**Agent:** `feature-dev`

- Create `app/Models/SavingsActionDefinition.php` mirroring `InvestmentActionDefinition`
- Methods: `renderTitle()`, `renderDescription()`, `renderAction()`, scopes

##### 3A.2 Seed ~40 Action Definitions
**Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- Create `database/seeders/SavingsActionDefinitionSeeder.php`
- Categories: data readiness, emergency fund, tax efficiency (PSA/ISA), rate optimisation, FSCS protection, debt vs savings, cash vs investment, goal-linked, children's savings, spouse optimisation
- **LAUNCH GATE included:** Disable 7 Investment engine savings triggers in same seeder run

##### 3A.3 Core Services
**Agent:** `feature-dev`

Create:
- `Services/Savings/SavingsActionDefinitionService.php` (~800 lines, ~40 evaluator methods)
- `Services/Savings/PSACalculator.php` — PSA breach detection
- `Services/Savings/FSCSAssessor.php` — FSCS institution exposure
- `config/banking_licence_groups.php` — FSCS institution groupings

Enhance:
- `EmergencyFundCalculator` — employment-based targets (3/6/9 months)
- `RateComparator` — institution grouping for FSCS
- Document divergence: Investment uses 6-month universal baseline; Savings uses employment-specific

##### 3A.4 SavingsPlanService + Agent Refactor
**Agent:** `feature-dev`

- Create `Services/Plans/SavingsPlanService.php` mirroring `InvestmentPlanService`
- Refactor `SavingsAgent::generateRecommendations()` to delegate to service
- Update `SavingsController::recommendations()` to use `SavingsPlanService`

##### 3A.5 Savings Notifications
**Agent:** `feature-dev`

- Create 4 notification classes (database channel only)
- Create `SendSavingsAlerts` command (daily schedule)
- Add savings alert preferences to `NotificationPreference` model
- Register in Kernel

#### 3B: Estate Planning Enhancements (Partially Parallelisable)

##### 3B.1 IHT Calculation Fixes
**Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- PET/CLT NRB deduction (primary user only — NOT spouse)
- Extend `SpouseNRBTrackerService` to include CLTs
- 14-year rule enforcement (Direction B: historical CLTs reduce NRB for PETs)
- RNRB direct descendant clarification (exclude nieces/nephews/cousins/siblings)
- Liquidity reclassification (investments → semi-liquid, pensions → illiquid)

##### 3B.2 Gifting & Trust Enhancements
**Agent:** `feature-dev`

- Trust NRB avoidance forward projection in `PersonalizedTrustStrategyService`
- Growth rate from `AssumptionsService` + `RiskPreferenceService` (NOT TaxConfigService)

##### 3B.3 Life Insurance & 2027 Pension Amendment
**Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- Enhanced life insurance checks in `LifeCoverCalculator` (trust status, joint life, whole of life)
- 2027 pension IHT dual-scenario projection in `IHTCalculationService`
- Frontend notification banner when `amendment_warning = true`

##### 3B.4 Estate Notifications
**Agent:** `feature-dev`

- Gift 7-year exemption reminders
- Trust 10-year anniversary alerts
- `SendEstateAlerts` command (daily schedule, `today()` not `now()`, chunk 100)

#### 3C: Investment Pipeline (Sequential — this is the largest piece)

##### 3C.1 UserContextBuilder
**Agent:** `feature-dev`

- `Services/Investment/Recommendation/UserContextBuilder.php`
- TWO methods: `build(User)` from scratch + `buildFromExisting(...)` from InvestmentPlanService data
- ISA allowance must account for ALL ISA types (savings + investment)

##### 3C.2 LifeEventAssessmentService
**Agent:** `feature-dev`

- `Services/Investment/Recommendation/LifeEventAssessmentService.php`
- 20+ event types producing modifiers: blocked_wrappers, prioritised_wrappers, liquidity_priority

##### 3C.3 GoalAssessmentService
**Agent:** `feature-dev`

- `Services/Investment/Recommendation/GoalAssessmentService.php`
- Maps goals to suitable wrappers by timeline (SHORT/MEDIUM/LONG/VERY_LONG)

##### 3C.4 SafetyCheckService
**Agent:** `feature-dev`

- `Services/Investment/Recommendation/SafetyCheckService.php`
- 7 safety checks reducing surplus
- **CRITICAL:** Must NOT emit standalone emergency fund recommendations (Savings engine owns those)
- Only reduces surplus figure + adds context notes + surfaces employer match

##### 3C.5 ContributionWaterfallService (CORE ENGINE)
**Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- `Services/Investment/Recommendation/ContributionWaterfallService.php`
- 11 sequential steps: LISA → S&S ISA → Pension → Premium Bonds → NS&I → Offshore Bond → Onshore Bond → Pension Carry Forward → VCT/EIS/SEIS → GIA
- Each step: check skip conditions → calculate allocation → generate recommendation → reduce surplus → pass remainder
- All limits from TaxConfigService

##### 3C.6 TransferRecommendationService
**Agent:** `feature-dev`

- `Services/Investment/Recommendation/TransferRecommendationService.php`
- 13 independent scans
- Delegates to existing `BedAndISACalculator` and `CGTHarvestingCalculator`

##### 3C.7 SpouseOptimisationService
**Agent:** `feature-dev`

- `Services/Investment/Recommendation/SpouseOptimisationService.php`
- 7 strategies: CGT sharing, ISA coordination, PSA optimisation, pension coordination, non-earning spouse pension, Marriage Allowance, IHT planning

##### 3C.8 ConflictResolution + OutputFormatter
**Agent:** `feature-dev`

- `Services/Investment/Recommendation/ConflictResolutionService.php`
- `Services/Investment/Recommendation/RecommendationOutputFormatter.php`
- Merge waterfall + triggers + transfers + spouse → deduplicate → prioritise

##### 3C.9 Pipeline Integration + Launch Gate
**Agent:** `feature-dev` | **Review:** `superpowers:code-reviewer`

- Integrate pipeline into `InvestmentPlanService::getRecommendations()` (NOT the agent)
- `InvestmentAgent::analyze()` remains UNCHANGED
- Disable 6 overlapping triggers in seeder (surplus + transfer triggers)
- Update `InvestmentController::recommendations()` for structured output
- Add frontend readiness gate component

#### 3D: Protection Engine Expansion

##### 3D.1 Employer Benefits Integration
**Agent:** `feature-dev`

- Integrate employer benefits into `CoverageGapAnalyzer` gap analysis
- Death-in-service: use `User.annual_employment_income` directly (not profile income)
- Add employer reliance warning (>50% from death-in-service)
- State benefits: SSP offset using `$sspWeekly * $maxWeeks` (NOT annualised)
- Employment status check (self-employed get no SSP)

##### 3D.2 Expand Action Definitions + Evaluators
**Agent:** `feature-dev`

- Add ~17 new triggers to `ProtectionActionDefinitionSeeder`
- Add ~17 new private evaluator methods + match branches to `ProtectionActionDefinitionService`
- Categories: employer benefits, state benefits, life insurance, IP, CI, children, spouse
- Triggers depending on Phase 2 columns must handle null gracefully

##### 3D.3 Frontend Cleanup
**Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- Remove frontend gap calculations from `GapAnalysis.vue` and `ProtectionOverviewCard.vue`
- Use backend data from Vuex store
- Add joint_life checkbox + employer benefits section to `PolicyFormModal.vue`
- Standardise multipliers (frontend 75%/2x → backend 60%/3x from TaxConfigService)

#### 3E: Retirement Engine Expansion

##### 3E.1 SalarySacrificeAnalyzer
**Agent:** `feature-dev` | **Review:** `tax-compliance-reviewer`

- Create `Services/Retirement/SalarySacrificeAnalyzer.php`
- NI savings calculation (employee + employer)
- NMW/NLW proxy floor check (use auto-enrolment £10,000 threshold — NOT £2,000)
- Additional warnings: >20% sacrifice, below personal allowance, NI LEL

##### 3E.2 Auto-Enrolment + Enhanced Annuity
**Agent:** `feature-dev`

- Add auto-enrolment threshold checking to `ContributionOptimizer`
- Wire smoker/health from `$user->protectionProfile?->smoker_status` into `DecumulationPlanner` (NOT `$user->smoker_status`)
- Wire care costs into decumulation from `RetirementProfile`

##### 3E.3 Expand Retirement Action Definitions
**Agent:** `feature-dev`

- Add ~8 new triggers to `RetirementActionDefinitionSeeder`
- Add corresponding evaluator methods to `RetirementActionDefinitionService`
- Categories: salary sacrifice, auto-enrolment, enhanced annuity, care costs, SP forecast, consolidation

**SPRINT 3 GATE:** Run `./vendor/bin/pest`. Run `php artisan db:seed`. Test each module's recommendation output with preview personas.

---

### Sprint 4: Frontend & Cross-Module Integration

#### 4.1 Cash/Savings Frontend
**Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- Enhance `Recommendations.vue` with structured recommendation cards
- Create `SavingsDecisionPath.vue` (decision trail display)
- Create `MissingDataCard.vue` (clickable missing data → input form links)
- Update Vuex `savings.js` store (psaPosition, fscsExposure, decisionPaths, missingData)

#### 4.2 Estate Planning Frontend
**Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- Substantial rewrite of `MissingDataAlert.vue` (12 checks, grouped by severity, specific form links)
- Update `EstateDashboard.vue` (`can_proceed` computed property, conditional render)
- 2027 pension amendment notification banner

#### 4.3 Investment Frontend
**Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- Readiness gate component (replacing "No recommendations available" empty state)
- Handle new structured pipeline output in `RecommendationsSection.vue`

#### 4.4 Retirement Frontend
**Agent:** `feature-dev` | **Review:** `premium-ui-designer`

- Salary sacrifice analysis display (NI savings, warnings, net cost comparison)
- Auto-enrolment compliance status display

#### 4.5 CoordinatingAgent Integration
**Agent:** `feature-dev`

- Verify `PriorityRanker::rankRecommendations()` handles new recommendation shapes
- Add field mapping in `CoordinatingAgent::mapSavingsAnalysis()` if needed
- Verify conflict resolution includes Savings recommendations (emergency fund highest priority)
- Verify surplus allocation ordering across all modules

**SPRINT 4 GATE:** Full manual browser testing of all modules with all 6 preview personas. `php artisan db:seed` before testing.

---

### Sprint 5: Testing, Verification & Cleanup

#### 5.1 Architecture Tests
**Agent:** `feature-dev` with `superpowers:verification-before-completion`

Create architecture tests asserting:
- No hardcoded PSA values in any service file
- No hardcoded `0.06` in Investment services
- No hardcoded `0.047` or `0.04` in Retirement services
- No hardcoded growth rates in Estate services
- No hardcoded premium factors in Protection services
- No `EstateDefaults::ESTIMATED_*` references anywhere

#### 5.2 Unit Tests per Module
**Agent:** `superpowers:test-driven-development` with parallel subagents

| Module | Tests Needed |
|--------|-------------|
| Savings | ~40 evaluator method tests, PSACalculator, FSCSAssessor, pipeline integration |
| Estate | 12 readiness checks, PET NRB deduction, 14-year rule, trust projection, 2027 pension |
| Investment | UserContextBuilder, 12 readiness checks, 20+ life events, 11 waterfall steps, 13 transfers, 7 spouse strategies, conflict resolution |
| Protection | 14 readiness checks, ~17 evaluator methods, employer benefit integration, state benefit offset |
| Retirement | 11 readiness checks, salary sacrifice (NMW proxy, NI savings), auto-enrolment, enhanced annuity |

#### 5.3 Cross-Module Integration Tests
**Agent:** `feature-dev`

- Emergency fund: Savings engine owns action cards, Investment SafetyCheckService only gates surplus
- ISA allowance: shared £20,000 across Cash ISA (Savings) and S&S ISA (Investment)
- Surplus allocation: CoordinatingAgent priority ordering correct
- No duplicate recommendations across modules

#### 5.4 Preview Persona End-to-End Testing
**Agent:** `feature-dev` with browser automation

Test all 6 personas:
- Young Family (James & Emily Carter): Mortgage, workplace pensions, children's savings
- Peak Earners (David & Sarah Mitchell): Multiple properties, SIPP + NHS pension, spouse optimisation
- Widow (Margaret Thompson): Estate planning, 2027 pension amendment
- Entrepreneur (Alex Chen): SIPP, self-employed protection, salary sacrifice N/A
- Young Saver (John Morgan): Emergency fund, first-time savings, LISA eligibility
- Retired Couple (Robert & Patricia Williams): Decumulation, care costs, enhanced annuity

**Command:** `php artisan db:seed` before EVERY persona test.

#### 5.5 CashAccount Model Documentation
**Agent:** `feature-dev`

- Add docblock to CashAccount model clarifying it's NOT part of savings engine
- Verify CashAccount excluded from savings recommendation analysis

#### 5.6 Legacy SavingsGoal Deprecation Banner
**Agent:** `feature-dev`

- Keep deprecated endpoints working
- Verify migration banner in SavingsGoals.vue pointing to Goals module

**SPRINT 5 GATE:** Full test suite passes. All architecture tests pass. All 6 personas produce correct recommendations. `php artisan db:seed` final run.

---

## 4. Agent & Skill Assignments

### Per-Task Agent Map

| Agent | When to Use | Sprint |
|-------|-------------|--------|
| `feature-dev` | ALL implementation tasks (primary agent) | 1-5 |
| `tax-compliance-reviewer` | After ANY change to TaxConfigService, tax calculations, financial projections | 1, 3 |
| `superpowers:code-reviewer` | After completing each sprint, and before integration | 2, 3, 5 |
| `database-optimizer` | Migrations, schema design, query performance for new services | 2 |
| `premium-ui-designer` | Frontend components (decision paths, missing data cards, recommendation displays) | 4 |
| `security-reviewer` | New API endpoints, controller changes, auth-related routes | 3, 4 |
| `Explore` | Understanding existing service patterns before implementing new ones | 1, 3 |

### Per-Task Skill Map

| Skill | When to Use |
|-------|-------------|
| `/feature-dev` | Starting any new feature implementation |
| `/systematic-debugging` | When tests fail after refactoring |
| `/code-review` | After completing each module's engine work |
| `superpowers:test-driven-development` | Sprint 5 unit test creation |
| `superpowers:verification-before-completion` | Before claiming any sprint is complete |
| `superpowers:dispatching-parallel-agents` | Sprint 2 readiness gates (5 independent modules) |
| `superpowers:brainstorming` | If design questions arise during implementation |

### Commands (Critical Checkpoints)

| Command | When |
|---------|------|
| `php artisan db:seed` | After EVERY backend change, before EVERY test |
| `./vendor/bin/pest` | After every sprint gate |
| `./vendor/bin/pint` | Before any commit (PSR-12 formatting) |
| `php artisan cache:clear` | After any agent/service refactor (30-min stale cache) |
| `grep -rn '0\.06[^0-9]' app/Services/Investment/` | After Sprint 1 Task 1.4 |
| `grep -rn '0\.047\|0\.04[^0-9]' app/Services/Retirement/` | After Sprint 1 Task 1.6 |
| `php artisan route:list --path=savings` | After Sprint 3A controller changes |

---

## 5. Risk Register

| Risk | Impact | Mitigation | Owner |
|------|--------|-----------|-------|
| PSA migration breaks existing tax calculations | High | Run full test suite after Phase 0; values unchanged, only source changes | Sprint 1 |
| Goal-account pivot migration loses data | High | Copy FK values to pivot BEFORE deprecating; update observer FIRST | Sprint 2 |
| 40+ savings triggers overwhelm users | Medium | Start with core triggers enabled; mark advanced as `is_enabled = false` | Sprint 3A |
| Cross-module circular dependencies | High | Services MUST NOT call each other; CoordinatingAgent handles cross-module | Sprint 4 |
| Frontend breaks on new response shapes | High | Update Vuex stores BEFORE deploying readiness gates | Sprint 2 |
| Investment pipeline breaks InvestmentPlanService | Critical | Pipeline goes into `getRecommendations()`, NOT `analyze()` | Sprint 3C |
| Duplicate recommendations across engines | Critical | Launch gates: disable overlapping triggers atomically with new engine | Sprint 3A, 3C |
| Employment-based emergency fund divergence | Medium | Documented: Investment uses 6-month universal, Savings uses employment-specific | Sprint 3A |
| `current_spa` null arithmetic | Critical | Add key to seeder BEFORE any retirement service references it | Sprint 1 |
| Tests break from constructor injection changes | High | Update test files alongside service changes (Protection tests especially) | Sprint 1 |

---

## 6. Verification Gates

### Sprint Gate Checklist

Every sprint must pass ALL of these before proceeding:

- [ ] `./vendor/bin/pest` — full test suite passes
- [ ] `php artisan db:seed` — seeder runs without errors
- [ ] `./vendor/bin/pint` — code formatted
- [ ] Architecture grep checks pass (no hardcoded values in target directories)
- [ ] Preview personas load without errors (manual check, at least 2 personas)
- [ ] No new `amber-*`, `orange-*`, or non-palette colors introduced
- [ ] No new hardcoded tax/financial values introduced
- [ ] No acronyms in user-facing text
- [ ] No scores in user-facing UI (Rule #13)
- [ ] Mobile dashboard cache cleared if backend changed

### Final Acceptance Criteria

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

## 7. Estimated File Counts by Sprint

| Sprint | New Files | Modified Files | Migrations | Seeders |
|--------|-----------|---------------|------------|---------|
| Sprint 1 | 0 | ~35 | 0 | 1 (TaxConfigSeeder) |
| Sprint 2 | 5 (readiness services) | ~15 (agents, controllers, stores) | 4 | 0 |
| Sprint 3A | 8 (savings engine) | 8 | 0 | 1 (SavingsActionDefs) |
| Sprint 3B | 4 (estate notifications) | 12 | 0 | 0 |
| Sprint 3C | 10 (investment pipeline) | 3 | 0 | 0 |
| Sprint 3D | 0 | 6 (protection expansion) | 0 | 1 (ProtectionActionDefs) |
| Sprint 3E | 1 (salary sacrifice) | 5 | 0 | 1 (RetirementActionDefs) |
| Sprint 4 | 3 (Vue components) | ~10 (Vue + stores) | 0 | 0 |
| Sprint 5 | ~10 (test files) | 1 (architecture test) | 0 | 0 |
| **Total** | **~41** | **~95** | **4** | **4** |
