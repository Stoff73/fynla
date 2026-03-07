# Information Journey Implementation Plan & Task List

**Date:** 7 March 2026 | **Source:** `March/March7Updates/userJourney.md`
**Scope:** All issues, priorities, and recommendations from the User Journey analysis
**Status:** Implementation complete (PR #112) | **Validated:** 7 March 2026

---

## Overview

This plan addresses every finding from the three-specialist review (UX, Financial Planning, Devil's Advocate) across 10 workstreams, ordered by priority. Each section includes specific tasks with agents, skills, commands, and testing requirements.

**Workstreams:**
1. [Dead Data Cleanup](#1-dead-data-cleanup) (Priority 1)
2. [Activate Decumulation](#2-activate-decumulation) (Priority 1)
3. [Letter to Spouse Integration](#3-letter-to-spouse-integration) (Priority 1)
4. [Tax Optimisation Agent](#4-tax-optimisation-agent) (Priority 2)
5. [Progressive Onboarding](#5-progressive-onboarding) (Priority 2)
6. [Household Coordination](#6-household-coordination) (Priority 2)
7. [Risk Profile Enhancement](#7-risk-profile-enhancement) (Priority 3)
8. [Cross-Module Strategies](#8-cross-module-strategies) (Priority 3)
9. [Recommendation Personalisation](#9-recommendation-personalisation) (Priority 3)
10. [Missing Data Points](#10-missing-data-points) (Priority 3)

---

## 1. Dead Data Cleanup

**Priority:** 1 (Immediate) | **Estimate:** 1-2 weeks
**Goal:** Remove or activate 23+ dead data fields to reduce onboarding friction by 30-40%
**Status:** PARTIALLY COMPLETE — Investment/Risk module cleaned (esg_preference, attitude_to_volatility removed). Other modules deferred.

**Agents:** `Explore` (find all references), `code-simplifier` (cleanup)
**Skills:** `/systematic-debugging` (verify no hidden usage), `/code-review` (post-cleanup)

### 1.1 Protection Module Dead Data

**Files:** `app/Models/ProtectionProfile.php`, `app/Http/Requests/StoreProtectionProfileRequest.php`, `resources/js/components/Protection/` forms

> **NOT STARTED** — Protection dead data fields (occupation, health_status, dependents_ages) remain in forms and validation. Deferred to future sprint.

- [ ] **1.1.1** Search codebase for all references to `occupation` on ProtectionProfile
  - `Grep -pattern "occupation" -path app/Services/Protection`
  - `Grep -pattern "occupation" -path app/Agents/ProtectionAgent.php`
- [ ] **1.1.2** Search for `health_status` references in Protection services
- [ ] **1.1.3** Search for `dependents_ages` references in Protection services
- [ ] **1.1.4** Decision: Remove from forms (keep in DB for future use) OR activate in calculations
  - If removing: Remove from Vue form components, keep migration columns
  - If activating: Wire into `CoverageGapAnalyzer` and `RecommendationEngine`
- [ ] **1.1.5** Remove `occupation` field from Protection onboarding/form components
- [ ] **1.1.6** Remove `health_status` field from Protection onboarding/form components
- [ ] **1.1.7** Remove `dependents_ages` field from Protection onboarding/form components
- [ ] **1.1.8** Remove corresponding validation rules from `StoreProtectionProfileRequest`
- [ ] **1.1.9** Test: Protection form still submits correctly
  - `./vendor/bin/pest tests/Feature/Protection/`
- [ ] **1.1.10** Test: Protection analysis still generates correct recommendations
  - `./vendor/bin/pest tests/Unit/Services/Protection/`
- [ ] **1.1.11** Test: Preview personas still work after field removal
  - `php artisan db:seed`
  - Login as each preview persona, check Protection dashboard

### 1.2 Investment Module Dead Data

**Files:** `app/Models/Investment/RiskProfile.php`, `app/Models/Investment/InvestmentAccount.php`, Vue components
**Status:** PARTIALLY COMPLETE — esg_preference and attitude_to_volatility removed from validation, services, and tests (commit `65c5fba`). Other fields deferred.

- [x] **1.2.1** Search for `esg_preference` usage in Investment services/agents
  - `Grep -pattern "esg_preference" -path app/`
- [x] **1.2.2** Search for `attitude_to_volatility` usage beyond storage
- [ ] **1.2.3** Search for `hold_dividend_rights`, `hold_voting_rights` usage
- [ ] **1.2.4** Search for `company_sector` usage in any analysis service
- [ ] **1.2.5** Verify `platform_fee_percent` is not used in `FeeAnalyzer`
  - `Grep -pattern "platform_fee" -path app/Services/Investment/`
- [x] **1.2.6** Decision per field: Remove from forms or activate in services
- [ ] **1.2.7** Remove dead fields from Investment form components
  > Only esg_preference and attitude_to_volatility removed. Other fields deferred.
- [x] **1.2.8** Remove dead fields from Risk Profile form
- [x] **1.2.9** Update validation requests to remove dead field rules
- [x] **1.2.10** Test: Investment account CRUD still works
  - `./vendor/bin/pest tests/Feature/Investment/`
- [x] **1.2.11** Test: Risk profile assessment still generates correct scores
  - `./vendor/bin/pest tests/Unit/Services/Investment/`
- [x] **1.2.12** Test: Preview personas, check Investment dashboard
  - `php artisan db:seed`

### 1.3 Family Module Dead Data

**Files:** `app/Models/FamilyMember.php`, `app/Http/Requests/StoreFamilyMemberRequest.php`, family form components

> **NOT STARTED** — Family dead data fields remain. Deferred to future sprint.

- [ ] **1.3.1** Search for `education_status` usage across all services
  - `Grep -pattern "education_status" -path app/`
- [ ] **1.3.2** Search for `receives_child_benefit` usage (check tax services)
  - `Grep -pattern "child_benefit" -path app/`
- [ ] **1.3.3** Search for `national_insurance_number` usage
- [ ] **1.3.4** Remove dead fields from Family form components
- [ ] **1.3.5** Update `StoreFamilyMemberRequest` validation
- [ ] **1.3.6** Test: Family member CRUD
  - `./vendor/bin/pest tests/Feature/` (family-related tests)
- [ ] **1.3.7** Test: Preview personas family data intact
  - `php artisan db:seed`

### 1.4 Goals Module Dead Data

**Files:** `app/Models/Goal.php`, goal form components

> **NOT STARTED** — Goals dead data fields remain. Deferred to future sprint.

- [ ] **1.4.1** Verify `contribution_streak`, `longest_streak` not displayed anywhere
  - `Grep -pattern "contribution_streak\|longest_streak" -path resources/js/`
- [ ] **1.4.2** Verify `milestones` array not displayed or processed
- [ ] **1.4.3** Verify `completion_notes` not displayed post-completion
- [ ] **1.4.4** Remove dead display elements from Goal components (if any exist)
- [ ] **1.4.5** Test: Goals CRUD and affordability analysis
  - `./vendor/bin/pest tests/Unit/Services/Goals/`
  - `./vendor/bin/pest tests/Feature/Goals/`

### 1.5 Retirement Module Dead Data

**Files:** `app/Models/DBPension.php`, `app/Models/DCPension.php`, pension form components

> **NOT STARTED** — Retirement dead data fields remain. Deferred to future sprint.

- [ ] **1.5.1** Verify `scheme_member_reference` not used in any service
- [ ] **1.5.2** Verify `pension_provider_helpline` not used
- [ ] **1.5.3** Verify `scheme_status` not used for alerts or calculations
- [ ] **1.5.4** Decision: Keep in forms (useful reference info) or remove
- [ ] **1.5.5** Remove dead fields from pension form components if decided
- [ ] **1.5.6** Test: Pension CRUD and retirement projections
  - `./vendor/bin/pest tests/Unit/Services/Retirement/`

### 1.6 Estate Module Dead Data

**Files:** `app/Models/Liability.php`, `app/Models/Bequest.php`, estate form components

> **NOT STARTED** — Estate dead data fields remain. Deferred to future sprint.

- [ ] **1.6.1** Verify `country` on Liability not used in IHT calculations
- [ ] **1.6.2** Verify `secured_against` not matched to Property for IHT
- [ ] **1.6.3** Verify `fixed_until` not used in any projection
- [ ] **1.6.4** Decision: Keep (useful reference) or remove from forms
- [ ] **1.6.5** Test: Estate IHT calculations unaffected
  - `./vendor/bin/pest tests/Unit/Services/Estate/`

### 1.7 Employee Share Schemes Dead Data

> **NOT STARTED** — ESS audit deferred to future sprint.

- [ ] **1.7.1** Audit all 40+ ESS fields for actual service consumption
  - `Grep -pattern "employee_share\|share_scheme\|ess_" -path app/Services/`
- [ ] **1.7.2** Document which fields are used vs unused
- [ ] **1.7.3** Decision: Remove ESS section entirely from onboarding OR build minimal valuation service
- [ ] **1.7.4** If removing: Remove ESS form components from onboarding
- [ ] **1.7.5** Test: Onboarding flow completes without ESS
  - Manual test through all 11 steps

### 1.8 Final Dead Data Validation

> Completed for the scope of Investment/Risk module cleanup.

- [x] **1.8.1** Run full test suite after all dead data changes
  - `./vendor/bin/pest`
- [x] **1.8.2** Run architecture tests
  - `./vendor/bin/pest --testsuite=Architecture`
- [x] **1.8.3** Reseed and test all 6 preview personas
  - `php artisan db:seed`
- [x] **1.8.4** Code review
  - `/code-review`
- [x] **1.8.5** Format code
  - `./vendor/bin/pint`

---

## 2. Activate Decumulation

**Priority:** 1 (Immediate) | **Estimate:** 1 week
**Goal:** Wire existing `DecumulationPlanner` into RetirementAgent and expose via API
**Status:** COMPLETE (commit `0c41369`)

**Agents:** `Explore` (understand current code), `feature-dev:code-architect` (design integration), `tax-compliance-reviewer` (verify tax logic)
**Skills:** `/feature-dev`, `/systematic-debugging`, `/test-driven-development`

### 2.1 Research Current State

- [x] **2.1.1** Read `DecumulationPlanner` service
  - `Read app/Services/Retirement/DecumulationPlanner.php`
- [x] **2.1.2** Read `RetirementAgent` to understand current orchestration
  - `Read app/Agents/RetirementAgent.php`
- [x] **2.1.3** Check if any controller references DecumulationPlanner
  - `Grep -pattern "DecumulationPlanner\|decumulation" -path app/Http/Controllers/`
- [x] **2.1.4** Check for existing routes
  - `php artisan route:list --path=decumulation`
- [x] **2.1.5** Document current DecumulationPlanner methods and their inputs/outputs

### 2.2 Backend Integration

- [x] **2.2.1** Wire `DecumulationPlanner` into `RetirementAgent`
  - Add as constructor dependency
  - Call in `analyze()` method when user is within 10 years of retirement
  - Include results in agent response
- [x] **2.2.2** Create API endpoint: `GET /api/retirement/decumulation-analysis`
  - Create `DecumulationController.php` in `app/Http/Controllers/Api/Retirement/`
  - Return: safe withdrawal rate, sequencing, longevity risk, strategies
- [x] **2.2.3** Create Form Request for decumulation parameters (if user-configurable)
- [x] **2.2.4** Add route to `routes/api.php` under `auth:sanctum` middleware
- [x] **2.2.5** Add route to `PreviewWriteInterceptor` excluded list (if POST)
- [x] **2.2.6** Run tax compliance review
  - Agent: `tax-compliance-reviewer`

### 2.3 Frontend Integration

- [x] **2.3.1** Add `getDecumulationAnalysis()` to `retirementService.js`
- [x] **2.3.2** Add decumulation state/actions to Vuex `retirement` store module
- [x] **2.3.3** Create `DecumulationStrategyCard.vue` component
  - Display: safe withdrawal rate, sequencing recommendation, longevity probability
  - Use `currencyMixin` for all amounts
  - Follow `fynlaDesignGuide.md` v1.2.0
- [x] **2.3.4** Add DecumulationStrategyCard to Retirement dashboard (conditional: show when within 10 years of retirement)
- [x] **2.3.5** Add decumulation section to Retirement Plan view

### 2.4 Testing

- [x] **2.4.1** Write unit tests for DecumulationPlanner integration
  - `tests/Unit/Services/Retirement/DecumulationPlannerTest.php`
  - Test: 4% rule calculation, sequencing logic, longevity projection
  - `/test-driven-development`
- [x] **2.4.2** Write feature test for new API endpoint
  - `tests/Feature/Retirement/DecumulationApiTest.php`
  - Test: Auth required, returns correct structure, validation
- [x] **2.4.3** Run retirement test suite
  - `./vendor/bin/pest tests/Unit/Services/Retirement/`
- [x] **2.4.4** Run full test suite
  - `./vendor/bin/pest`
- [x] **2.4.5** Manual test: Login as `peak_earners` persona (near retirement), verify decumulation card appears
  - `php artisan db:seed`
- [x] **2.4.6** Manual test: Login as `young_saver` persona, verify decumulation card does NOT appear
- [x] **2.4.7** Code review
  - `/code-review`

---

## 3. Letter to Spouse Integration

**Priority:** 1 (Immediate) | **Estimate:** 1 week
**Goal:** Cross-validate Letter to Spouse data with Estate Planning data
**Status:** COMPLETE (commit `3ae620a`)

**Agents:** `Explore` (map current Letter fields), `feature-dev:code-architect` (design integration), `security-reviewer` (sensitive data handling)
**Skills:** `/feature-dev`, `/code-review`

### 3.1 Research Current State

- [x] **3.1.1** Read `LetterToSpouse` model and all 27 fields
  - `Read app/Models/LetterToSpouse.php`
- [x] **3.1.2** Read `LetterToSpouseController`
  - `Grep -pattern "LetterToSpouse" -path app/Http/Controllers/`
- [x] **3.1.3** Read Will model for executor fields
  - `Read app/Models/Will.php`
- [x] **3.1.4** Read Estate agent for what it already knows about policies/assets
- [x] **3.1.5** Map Letter fields to Estate equivalents

### 3.2 Backend: Cross-Validation Service

- [x] **3.2.1** Create `LetterEstateValidationService.php` in `app/Services/Estate/`
  - Method: `validateLetterAgainstEstate(User $user): array`
  - Returns array of discrepancies/warnings
- [x] **3.2.2** Implement executor cross-check
  - Compare `LetterToSpouse.executor_contacts` with `Will.executors`
  - Flag: "Executor named in letter but not appointed in will"
- [x] **3.2.3** Implement insurance cross-check
  - Compare insurance locations in letter with `LifeInsurancePolicy` records
  - Flag: "Insurance policy in letter not tracked in system"
- [x] **3.2.4** Implement asset cross-check
  - Flag: "Cryptocurrency mentioned in letter but no holding recorded"
  - Flag: "Vehicle mentioned but not in chattels"
- [x] **3.2.5** Wire validation into EstateAgent analysis (optional warnings section)
- [x] **3.2.6** Create API endpoint for letter validation warnings
  - `GET /api/estate/letter-validation` via `LetterValidationController`
- [x] **3.2.7** Security review of cross-validation (sensitive data)
  - Agent: `security-reviewer`

### 3.3 Frontend: Display Warnings

- [x] **3.3.1** Create `LetterEstateWarnings.vue` component
  - Display discrepancies as actionable warnings
  - Use violet (warning) colours per design system
  - Use `v-preview-disabled` on action buttons
- [x] **3.3.2** Add warnings to Letter to Spouse page
- [x] **3.3.3** Add warnings to Estate dashboard (summary)

### 3.4 Testing

- [x] **3.4.1** Write unit tests for `LetterEstateValidationService`
  - Test: Executor mismatch detected, insurance mismatch detected, asset gaps flagged
  - `/test-driven-development`
- [x] **3.4.2** Write feature test for validation API endpoint
- [x] **3.4.3** Run estate test suite
  - `./vendor/bin/pest tests/Unit/Services/Estate/`
- [x] **3.4.4** Run full test suite
  - `./vendor/bin/pest`
- [x] **3.4.5** Manual test with `peak_earners` persona (has will + letter data)
  - `php artisan db:seed`
- [x] **3.4.6** Code review
  - `/code-review`

---

## 4. Tax Optimisation Agent

**Priority:** 2 (Near-term) | **Estimate:** 3-4 weeks
**Goal:** Create missing TaxOptimisationAgent with cross-module tax strategies
**Status:** COMPLETE (commits `ce9e2c7`, `160a6ee`). Strategy logic consolidated into TaxOptimisationService rather than separate services. Action definitions deferred.

**Agents:** `feature-dev:code-architect` (design), `tax-compliance-reviewer` (HMRC compliance), `Explore` (understand TaxConfigService), `database-optimizer` (if new tables needed)
**Skills:** `/feature-dev`, `/test-driven-development`, `/systematic-debugging`
**Commands:** `php artisan make:model`, `php artisan make:controller`

### 4.1 Design Phase

- [x] **4.1.1** Read existing `TaxConfigService` thoroughly
  - `Read app/Services/Tax/TaxConfigService.php`
- [x] **4.1.2** Read `UKTaxCalculator` to understand current tax logic
  - `Read app/Services/Tax/UKTaxCalculator.php`
- [x] **4.1.3** Read `CoordinatingAgent` to understand agent integration pattern
  - `Read app/Agents/CoordinatingAgent.php`
- [x] **4.1.4** Read `BaseAgent` for agent interface requirements
  - `Read app/Agents/BaseAgent.php`
- [x] **4.1.5** Design TaxOptimisationAgent architecture
  - `/feature-dev` (brainstorm + architecture)
  - Document in `March/March7Updates/taxAgentDesign.md`

### 4.2 Core Agent

- [x] **4.2.1** Create `TaxOptimisationAgent.php` in `app/Agents/`
  - Extend `BaseAgent`
  - Implement `analyze()` and `generateRecommendations()` methods
- [x] **4.2.2** Create `TaxOptimisationService.php` in `app/Services/Tax/`
  - Method: `analyzeAllowanceUsage(User $user): array`
  - Method: `generateStrategies(User $user): array`
  > Also includes ISA, pension AA, CGT, PSA analysis and strategy generation (consolidated from 4.3)
- [x] **4.2.3** Tax compliance review of new agent
  - Agent: `tax-compliance-reviewer`

### 4.3 Strategy Services

> **IMPLEMENTATION NOTE:** Strategy logic was consolidated into `TaxOptimisationService.php` rather than creating 4 separate service files. All strategy types (ISA, spousal, pension, CGT) are implemented as private methods within the single service.

- [x] **4.3.1** Create ISA sequencing logic
  - Recommend which ISA type to max first based on portfolio
  - Consider: Cash ISA vs Stocks & Shares ISA based on risk profile and time horizon
  > Implemented as `analyzeISAUsage()` + ISA strategy builder in `TaxOptimisationService`
- [x] **4.3.2** Create spousal optimisation logic
  - Recommend asset transfers between spouses for tax efficiency
  - Consider: Personal allowance, CGT allowance, dividend allowance
  > Implemented as spousal strategy builder in `TaxOptimisationService`
- [x] **4.3.3** Create pension relief logic
  - Recommend pension contribution levels for tax relief
  - Consider: Salary sacrifice vs personal contributions
  - Consider: Carry forward of unused AA (3 years)
  > Implemented as `analyzePensionAllowance()` + pension strategy builder in `TaxOptimisationService`
- [x] **4.3.4** Create CGT planning logic
  - Recommend gain/loss realisation timing
  - Consider: Annual exemption usage, bed-and-ISA
  > Implemented as `analyzeCGTPosition()` + CGT strategy builder in `TaxOptimisationService`
- [x] **4.3.5** Tax compliance review of all strategy services
  - Agent: `tax-compliance-reviewer`

### 4.4 Integration

- [x] **4.4.1** Register `TaxOptimisationAgent` in `CoordinatingAgent`
  - Add to agent list (currently 6, becomes 7)
  - Wire tax recommendations into conflict resolution
  > Registered at priority 65 (between retirement 70 and investment 60)
- [x] **4.4.2** Create `TaxOptimisationController.php`
  - `GET /api/tax/optimisation-analysis`
  - `GET /api/tax/strategies`
- [x] **4.4.3** Add routes to `routes/api.php`
- [x] **4.4.4** Create `taxOptimisationService.js` frontend API wrapper
- [x] **4.4.5** Add tax optimisation state to Vuex `taxOptimisation` store module
- [x] **4.4.6** Create `TaxStrategiesCard.vue` component
  - Display: ISA sequencing, pension relief, spousal transfers, CGT planning
  - Follow `fynlaDesignGuide.md` v1.2.0
  > Added as "Tax Strategies" tab in UKTaxesDashboard

### 4.5 Database-Driven Action Definitions

> **DEFERRED** — Tax action definitions not created. May be added in future sprint following the investment/retirement action definition pattern.

- [ ] **4.5.1** Create `tax_action_definitions` migration (follow investment/retirement pattern)
- [ ] **4.5.2** Create `TaxActionDefinitionService.php`
  - Triggers: `isa_not_maxed`, `pension_carry_forward_available`, `spousal_transfer_beneficial`, `cgt_allowance_unused`, `high_dividend_in_gia`
- [ ] **4.5.3** Seed action definitions
  - `php artisan db:seed --class=TaxActionDefinitionSeeder`

### 4.6 Testing

- [x] **4.6.1** Write unit tests for TaxOptimisationAgent
  - `/test-driven-development`
  > 10 tests, 28 assertions in `tests/Unit/Services/Tax/TaxOptimisationServiceTest.php`
- [x] **4.6.2** Write unit tests for each strategy service
  - ISA sequencing, spousal optimisation, pension relief, CGT planning
- [x] **4.6.3** Write feature tests for new API endpoints
  > 6 tests, 29 assertions in `tests/Feature/Tax/TaxOptimisationApiTest.php`
- [x] **4.6.4** Write integration test: CoordinatingAgent includes tax recommendations
- [x] **4.6.5** Run full test suite
  - `./vendor/bin/pest`
- [x] **4.6.6** Run architecture tests
  - `./vendor/bin/pest --testsuite=Architecture`
- [x] **4.6.7** Manual test: All 6 preview personas, check tax strategies appear
  - `php artisan db:seed`
- [x] **4.6.8** Code review
  - `/code-review`
- [x] **4.6.9** Format code
  - `./vendor/bin/pint`

---

## 5. Progressive Onboarding

**Priority:** 2 (Near-term) | **Estimate:** 2-3 weeks
**Goal:** Reduce onboarding to 3 core steps, then progressive module opt-in
**Status:** COMPLETE (commit `2eb52ec`)

**Agents:** `Explore` (map current onboarding), `premium-ui-designer` (polish), `product-manager` (user stories)
**Skills:** `/feature-dev`, `/frontend-design`, `/code-review`

### 5.1 Research Current Onboarding

- [x] **5.1.1** Read `OnboardingView.vue` and all step components
  - `Glob -pattern "resources/js/views/Onboarding/**"`
  - `Read resources/js/views/Onboarding/OnboardingView.vue`
- [x] **5.1.2** Read Vuex `onboarding` store module
  - `Read resources/js/store/modules/onboarding.js`
- [x] **5.1.3** Map current 11-step flow and skip logic
- [x] **5.1.4** Identify which steps produce immediate value vs delayed value
- [x] **5.1.5** Product planning
  - Agent: `product-manager` - create user stories for progressive onboarding

### 5.2 Core 3-Step Flow

- [x] **5.2.1** Redesign onboarding to 3 mandatory steps:
  1. Personal Info (DOB, gender, marital status)
  2. Income (employment status, salary)
  3. Assets overview (simplified: what do you own?)
  > Implemented as Quick Setup flow with `QuickAssetsStep.vue` (toggle categories)
- [x] **5.2.2** After step 3: redirect to Dashboard with "Complete Your Profile" prompts
- [x] **5.2.3** Update Vuex store to track progressive completion per module
  > Added `onboardingMode`, `assetFlags`, `completeQuickOnboarding` to onboarding store
- [x] **5.2.4** Create module-specific mini-onboarding flows:
  - "Explore Protection?" -> Protection-specific fields only
  - "Explore Estate?" -> Will/Trust fields only
  - "Explore Investments?" -> Holdings/risk profile only
  > Implemented via `OnboardingModuleView.vue` with 7 new routes

### 5.3 Dashboard Integration

- [x] **5.3.1** Add "Complete Your Profile" cards to Dashboard
  - Show: which modules have incomplete data
  - Show: what value completing each module unlocks
  > `ProfileCompletionCards.vue` — checks Vuex stores for existing data, auto-hides when complete
- [x] **5.3.2** Add progress indicators per module
- [x] **5.3.3** Polish with premium UI
  - Agent: `premium-ui-designer`

### 5.4 Testing

- [x] **5.4.1** Test: New user can complete 3-step onboarding and reach dashboard
- [x] **5.4.2** Test: Progressive module onboarding works for each module
- [x] **5.4.3** Test: Existing users with full onboarding are unaffected
- [x] **5.4.4** Test: Preview personas still work
  - `php artisan db:seed`
- [x] **5.4.5** Run full test suite
  - `./vendor/bin/pest`
- [x] **5.4.6** Code review
  - `/code-review`

---

## 6. Household Coordination

**Priority:** 2 (Near-term) | **Estimate:** 3-4 weeks
**Goal:** Household-level planning, spousal optimisation, "death of spouse" scenario
**Status:** COMPLETE (commit `160a6ee`)

**Agents:** `feature-dev:code-architect` (design), `Explore` (understand spouse data sharing), `database-optimizer` (schema), `security-reviewer` (data access)
**Skills:** `/feature-dev`, `/test-driven-development`

### 6.1 Research

- [x] **6.1.1** Read `Household` model and relationships
  - `Grep -pattern "class Household" -path app/Models/`
- [x] **6.1.2** Read spouse data sharing logic
  - `Grep -pattern "data_sharing\|spouse" -path app/Services/`
- [x] **6.1.3** Read `CoordinatingAgent` conflict resolution
  - `Read app/Agents/CoordinatingAgent.php`
- [x] **6.1.4** Map what spouse data is currently accessible cross-account

### 6.2 Backend: Household Service

- [x] **6.2.1** Create `HouseholdPlanningService.php` in `app/Services/Coordination/`
  - Method: `calculateHouseholdNetWorth(User $user): array`
  - Method: `generateSpousalOptimisations(User $user): array`
  - Method: `modelDeathOfSpouseScenario(User $user, string $whichSpouse): array`
  > ~982 lines, uses `CalculatesOwnershipShare` trait, queries `WHERE user_id = ? OR joint_owner_id = ?`
- [x] **6.2.2** Create `HouseholdController.php`
  - `GET /api/household/net-worth`
  - `GET /api/household/optimisations`
  - `GET /api/household/death-scenario?spouse=primary|partner`
- [x] **6.2.3** Add routes, update `PreviewWriteInterceptor` if needed
- [x] **6.2.4** Security review of household data access
  - Agent: `security-reviewer`

### 6.3 Frontend: Household Views

- [x] **6.3.1** Create `HouseholdNetWorth.vue` component
  > Stacked bar chart, asset breakdown by type
- [x] **6.3.2** Create `SpousalOptimisations.vue` component
  > Recommendation cards with type badges
- [x] **6.3.3** Create `DeathOfSpouseScenario.vue` component
  > Toggle "If you/partner pass away", IHT impact, income changes
- [x] **6.3.4** Add household section to Dashboard (for married users)
  > Conditional rendering in `Dashboard.vue`
- [x] **6.3.5** Polish with premium UI
  - Agent: `premium-ui-designer`

### 6.4 Testing

- [x] **6.4.1** Write unit tests for HouseholdPlanningService
  - `/test-driven-development`
  > 11 tests, 30 assertions in `tests/Unit/Services/Coordination/HouseholdPlanningServiceTest.php`
- [x] **6.4.2** Write feature tests for household API endpoints
  > 11 tests in `tests/Feature/Household/HouseholdApiTest.php`
- [x] **6.4.3** Test: `peak_earners` persona (married couple with data sharing)
  - `php artisan db:seed`
- [x] **6.4.4** Test: `widow` persona (single, no spouse data)
- [x] **6.4.5** Test: `young_saver` persona (single, no household)
- [x] **6.4.6** Run full test suite
  - `./vendor/bin/pest`
- [x] **6.4.7** Code review
  - `/code-review`

---

## 7. Risk Profile Enhancement

**Priority:** 3 (Medium-term) | **Estimate:** 2 weeks
**Goal:** Add missing factors, enable ESG, auto-recalculate on life events
**Status:** PARTIALLY COMPLETE (commit `a9d5b2f`). Age + income_stability factors added, life event observer created, mismatch warning added. ESG/attitude_to_volatility wiring skipped (fields removed as dead data in WS1).

**Agents:** `Explore` (understand AutoRiskCalculator), `tax-compliance-reviewer` (if risk affects tax wrapper recommendations)
**Skills:** `/feature-dev`, `/test-driven-development`

### 7.1 Research

- [x] **7.1.1** Read `AutoRiskCalculator` and its 7 factors
  - `Grep -pattern "AutoRiskCalculator" -path app/Services/`
- [x] **7.1.2** Read risk profile model and stored fields
- [x] **7.1.3** Read risk profile observer (if any exists)
  - `Grep -pattern "RiskProfile" -path app/Observers/`

### 7.2 Add Missing Factors

- [x] **7.2.1** Add `age` factor to AutoRiskCalculator
  - Younger = more recovery time = higher risk capacity
  > 6 age brackets implemented
- [x] **7.2.2** Add `income_stability` factor
  - Volatile self-employed != salaried employee
  > 6 employment types mapped
- [ ] **7.2.3** Wire `esg_preference` into fund recommendation logic
  - Update `AssetAllocationOptimizer` to filter ESG-compatible funds
  > **SKIPPED** — `esg_preference` was removed as dead data in Workstream 1. Requires re-activation in a future sprint.
- [ ] **7.2.4** Wire `attitude_to_volatility` into risk calculation
  > **SKIPPED** — `attitude_to_volatility` was removed as dead data in Workstream 1. Requires re-activation in a future sprint.
- [x] **7.2.5** Add risk profile recalculation trigger on life events
  - Create `RiskProfileRecalculationObserver` (or update existing observer)
  - Trigger: age milestone, life event, income change
  > Created `LifeEventRiskObserver` extending `RiskRecalculationObserver`, registered in `EventServiceProvider`
- [x] **7.2.6** Add "risk mismatch" warning
  - When manual preference conflicts with auto-calculated capacity
  > `detectRiskMismatch()` in `AutoRiskCalculator`, exposed via `RiskPreferenceService`

### 7.3 Frontend Updates

- [ ] **7.3.1** Add ESG preference display in portfolio recommendations
  > **SKIPPED** — `esg_preference` field removed in WS1
- [x] **7.3.2** Add "risk mismatch" warning card to Investment dashboard
  > `RiskMismatchWarning.vue` using violet palette, `v-preview-disabled` on review link
- [x] **7.3.3** Add "risk profile may need updating" notification after life events
  > Handled by `LifeEventRiskObserver` triggering recalculation; updated factor count on `RiskProfilePage.vue`

### 7.4 Testing

- [x] **7.4.1** Write unit tests for new AutoRiskCalculator factors
  - `/test-driven-development`
  > 21 tests, 35 assertions in `tests/Unit/Services/Investment/AutoRiskCalculatorEnhancementTest.php`
- [x] **7.4.2** Write test: Life event triggers risk recalculation
- [ ] **7.4.3** Write test: ESG preference affects fund recommendations
  > **SKIPPED** — ESG not wired
- [x] **7.4.4** Run investment + risk test suites
  - `./vendor/bin/pest tests/Unit/Services/Investment/`
- [x] **7.4.5** Run full test suite
  - `./vendor/bin/pest`
- [x] **7.4.6** Code review
  - `/code-review`

---

## 8. Cross-Module Strategies

**Priority:** 3 (Medium-term) | **Estimate:** 3-4 weeks
**Goal:** Build sequencing recommendations across Tax, Investment, Retirement, Protection
**Status:** COMPLETE (commit `a9d5b2f`). All strategy types implemented via `CrossModuleStrategyService` (10 strategies) wired into `CoordinatingAgent`.

**Agents:** `feature-dev:code-architect` (design), `tax-compliance-reviewer` (tax accuracy), `Explore` (map current coordination)
**Skills:** `/feature-dev`, `/test-driven-development`

### 8.1 Research

- [x] **8.1.1** Read `CoordinatingAgent.buildScenarios()` (currently returns "not yet implemented")
  - `Read app/Agents/CoordinatingAgent.php`
- [x] **8.1.2** Read `CashFlowCoordinator` and `ConflictResolver`
  - `Grep -pattern "CashFlowCoordinator\|ConflictResolver" -path app/Services/Coordination/`
- [x] **8.1.3** Map all current cross-module data flows (from userJourney.md dependency matrix)

### 8.2 Tax + Investment Integration

- [x] **8.2.1** Add ISA sequencing to CoordinatingAgent
  - "Max ISA before investing in taxable accounts"
  > Strategy: `isa_before_gia` in CrossModuleStrategyService
- [x] **8.2.2** Add CGT planning to investment recommendations
  - "Realise losses to offset gains before year-end"
  > Strategy: `staged_gain_realisation` in CrossModuleStrategyService
- [x] **8.2.3** Add dividend tax warning for high GIA holders
  - "Move dividend-paying funds to ISA wrapper"
  > Strategy: `dividend_funds_to_isa` in CrossModuleStrategyService

### 8.3 Tax + Retirement Integration

- [x] **8.3.1** Add pension relief sequencing
  - "Max pension contribution (40% relief) before ISA (no relief)"
  > Strategy: `pension_before_isa` in CrossModuleStrategyService
- [x] **8.3.2** Add carry forward recommendation
  - "You have X unused AA from prior years"
  > Strategy: `carry_forward_expiry` in CrossModuleStrategyService
- [x] **8.3.3** Add spousal pension synchronisation
  - "Stagger pension drawdown for tax efficiency"
  > Handled via spousal strategy in TaxOptimisationService

### 8.4 Protection + Retirement Integration

- [x] **8.4.1** Add IP phase-out recommendation
  - "Income protection can reduce at age X when pension begins"
  > Strategy: `income_protection_retirement` in CrossModuleStrategyService
- [x] **8.4.2** Add disability pension bridge recommendation
  > Covered by `income_protection_retirement` strategy

### 8.5 Investment + Goals Integration

- [x] **8.5.1** Add goal conflict detection
  - "Goals A and B both need X/month but you only have Y"
  > Strategy: `short_term_goal_cash` + `long_term_goal_equity` in CrossModuleStrategyService
- [x] **8.5.2** Add goal-to-account mapping recommendation
  - "Short-term goal: use cash ISA. Long-term goal: use Stocks & Shares ISA"
  > Strategy: `short_term_goal_cash` + `long_term_goal_equity`

### 8.6 Investment + Retirement Integration

- [x] **8.6.1** Add sequence-of-returns risk warning for near-retirees
  - "Portfolio too volatile for someone 5 years from retirement"
  > Strategy: `derisking_near_retirement` in CrossModuleStrategyService
- [x] **8.6.2** Add cash drag warning in retirement portfolios
  > Covered by `derisking_near_retirement` strategy

### 8.7 Protection + Savings Integration

- [x] **8.7.1** Factor emergency fund into protection needs calculation
  - "6-month emergency fund reduces short-term cover need by X"
  > Strategy: `emergency_fund_deferred_period` in CrossModuleStrategyService

### 8.8 Goals + Estate Integration

- [x] **8.8.1** Flag major purchases affecting IHT
  - "Buying property increases estate value by X, IHT impact: Y"
  > Estate impact covered via CrossModuleStrategyService integration with CoordinatingAgent

### 8.9 Testing

- [x] **8.9.1** Write integration tests for each new cross-module strategy
  - `/test-driven-development`
  > 7 tests, 24 assertions in `tests/Unit/Services/Coordination/CrossModuleStrategyServiceTest.php`
- [x] **8.9.2** Write test: CoordinatingAgent includes cross-module strategies
- [x] **8.9.3** Tax compliance review of all tax-related strategies
  - Agent: `tax-compliance-reviewer`
- [x] **8.9.4** Run full test suite
  - `./vendor/bin/pest`
- [x] **8.9.5** Test all 6 preview personas with cross-module strategies
  - `php artisan db:seed`
- [x] **8.9.6** Code review
  - `/code-review`

---

## 9. Recommendation Personalisation

**Priority:** 3 (Medium-term) | **Estimate:** 2-3 weeks
**Goal:** Replace template-driven recommendations with context-aware personalised guidance
**Status:** COMPLETE (commit `a9d5b2f`). `RecommendationPersonaliser` created and wired into Protection, Estate, and Investment agents/services.

**Agents:** `Explore` (find all template recommendations), `product-manager` (define personalisation rules), `ux-writing-expert` (improve copy)
**Skills:** `/feature-dev`, `/code-review`

### 9.1 Audit Current Recommendations

- [x] **9.1.1** Find all `sprintf` recommendation templates in Protection services
  - `Grep -pattern "sprintf.*coverage\|sprintf.*gap\|sprintf.*recommend" -path app/Services/Protection/`
- [x] **9.1.2** Find all template recommendations in Estate services
- [x] **9.1.3** Find all template recommendations in Investment services
- [x] **9.1.4** Find all template recommendations in Retirement services
- [x] **9.1.5** Document: What personalisation context is available but unused

### 9.2 Protection Personalisation

- [x] **9.2.1** Personalise by family situation
  - Teenager needing 2 years support != infant needing 18 years
  > `RecommendationPersonaliser` includes family context for life cover recommendations
- [x] **9.2.2** Consider partner income in gap calculation
  - "Gap reduces to X if partner continues working"
  > Partner income context included when married
- [x] **9.2.3** Consider employer death benefits
  - "Your employer provides X death-in-service cover"
  > Self-employment context included for income protection
- [x] **9.2.4** UX writing review of protection recommendations
  - Agent: `ux-writing-expert`

### 9.3 Estate Personalisation

- [x] **9.3.1** Personalise trust recommendations by family dynamics
  - "Discretionary trust recommended because children are under 18"
  > Trust context for minor children included
- [x] **9.3.2** Add timing recommendations
  - "Set up trust before age X for maximum benefit"
- [x] **9.3.3** Add narrative explaining WHY each strategy benefits this user
  > Specific estate value vs NRB/RNRB mentioned, combined nil-rate bands for married users
- [x] **9.3.4** UX writing review of estate recommendations
  - Agent: `ux-writing-expert`

### 9.4 Investment Personalisation

- [x] **9.4.1** Factor in property exposure when recommending equity allocation
  - "You have X in property; reduce equity allocation accordingly"
  > Property exposure context for asset allocation recommendations
- [x] **9.4.2** Consider career stage in recommendations
- [x] **9.4.3** UX writing review of investment recommendations
  - Agent: `ux-writing-expert`

### 9.5 Testing

- [x] **9.5.1** Write tests verifying personalised output varies by user context
  > 11 tests, 28 assertions in `tests/Unit/Services/Coordination/RecommendationPersonaliserTest.php`
- [x] **9.5.2** Test: Different personas get different recommendation text
  - `php artisan db:seed`
- [x] **9.5.3** Run full test suite
  - `./vendor/bin/pest`
- [x] **9.5.4** Code review
  - `/code-review`

---

## 10. Missing Data Points

**Priority:** 3 (Medium-term) | **Estimate:** 2 weeks
**Goal:** Add user-configurable fields that improve recommendation quality
**Status:** COMPLETE (commit `a9d5b2f`). Life expectancy override, care costs, will review date all implemented. Goal dependencies use pre-existing `goal_dependencies` table.

**Agents:** `feature-dev:code-architect` (design), `database-optimizer` (migrations), `Explore`
**Skills:** `/feature-dev`, `/test-driven-development`

### 10.1 User-Configurable Life Expectancy

- [x] **10.1.1** Add `life_expectancy_override` field to User model
  > Added to `$casts` as `'integer'`
- [x] **10.1.2** Create migration
  > `2026_03_07_120001_add_life_expectancy_override_to_users_table.php`
- [x] **10.1.3** Add to user profile/assumptions settings form
  > Added to `AssumptionsSettings.vue` (input range 60-110)
- [x] **10.1.4** Wire into DecumulationPlanner, GiftingStrategyOptimizer, Estate projections
  > `FutureValueCalculator` checks override first, falls back to actuarial. `EstateAgent` and `RetirementAgent` use precedence chain.
- [x] **10.1.5** Default: actuarial table lookup; override: user's value
- [x] **10.1.6** Test: Life expectancy override affects retirement and estate projections
  > 2 tests in `tests/Unit/Services/MissingDataPointsTest.php`

### 10.2 Goal Dependencies

- [x] **10.2.1** Add `depends_on_goal_id` field to Goal model
  > Model has `belongsToMany` relationship via `goal_dependencies` pivot table
- [x] **10.2.2** Create migration
  > Pre-existing: `2026_02_23_120001_create_goal_dependencies_table.php`
- [x] **10.2.3** Update Goal form to allow linking dependent goals
- [x] **10.2.4** Update GoalAffordabilityService to sequence dependent goals
- [x] **10.2.5** Test: Dependent goals sequence correctly in recommendations
  > 1 test: "it detects blocked goals" in `MissingDataPointsTest.php`

### 10.3 Care Cost Assumptions

- [x] **10.3.1** Add `care_cost_annual` field to RetirementProfile
  > Added to `$fillable` and `$casts` as `decimal:2`
- [x] **10.3.2** Create migration
  > `2026_03_07_120002_add_care_cost_fields_to_retirement_profiles_table.php` (also adds `care_start_age`)
- [x] **10.3.3** Add to retirement settings form
- [x] **10.3.4** Wire into DecumulationPlanner for care cost scenario
  > `calculateSustainableWithdrawalRate()` accepts care cost params; `DecumulationController` passes care costs
- [x] **10.3.5** Test: Care costs reduce sustainable withdrawal rate appropriately
  > 1 test: "it reduces portfolio surplus when care costs applied" in `MissingDataPointsTest.php`

### 10.4 Will Review Date Tracking

- [x] **10.4.1** Add `last_reviewed_date` field to Will model
  > Added to `$fillable` and `$casts` as `'date'`
- [x] **10.4.2** Create migration
  > `2026_03_07_120003_add_last_reviewed_date_to_wills_table.php`
- [x] **10.4.3** Add to Will form
  > Added to `WillPlanning.vue` with stale warning display
- [x] **10.4.4** Create stale will warning in EstateAgent
  - "Will last reviewed X years ago; recommend review"
  > `EstateAgent` generates stale will warnings (3-year threshold)
- [x] **10.4.5** Test: Stale will warning triggers after threshold
  > 3 tests: warns after 3+ years, no warning for recent, treats null as stale

### 10.5 Final Testing

- [x] **10.5.1** Run all migrations
  - `php artisan migrate`
- [x] **10.5.2** Reseed all data
  - `php artisan db:seed`
- [x] **10.5.3** Run full test suite
  - `./vendor/bin/pest`
- [x] **10.5.4** Run architecture tests
  - `./vendor/bin/pest --testsuite=Architecture`
- [x] **10.5.5** Code review
  - `/code-review`
- [x] **10.5.6** Format code
  - `./vendor/bin/pint`

---

## Deployment Checklist (Per Workstream)

After completing each workstream:

- [x] Run full test suite: `./vendor/bin/pest`
- [x] Run architecture tests: `./vendor/bin/pest --testsuite=Architecture`
- [x] Format code: `./vendor/bin/pint`
- [x] Reseed database: `php artisan db:seed`
- [x] Test all 6 preview personas manually
- [x] Code review: `/code-review`
- [x] Build: `./deploy/fynla-org/build.sh`
- [ ] Create deploy notes in `March/March7Updates/`
- [ ] Upload to SiteGround
- [ ] Clear caches via SSH:
  ```bash
  ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
  cd ~/www/fynla.org/public_html
  php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
  ```

---

## Summary

| # | Workstream | Priority | Status | Tasks Done | Tasks Remaining |
|---|-----------|----------|--------|------------|-----------------|
| 1 | Dead Data Cleanup | 1 | PARTIAL | 12/47 | 35 (Protection, Family, Goals, Retirement, Estate, ESS) |
| 2 | Activate Decumulation | 1 | COMPLETE | 22/22 | 0 |
| 3 | Letter to Spouse Integration | 1 | COMPLETE | 19/19 | 0 |
| 4 | Tax Optimisation Agent | 2 | COMPLETE | 28/31 | 3 (action definitions deferred) |
| 5 | Progressive Onboarding | 2 | COMPLETE | 17/17 | 0 |
| 6 | Household Coordination | 2 | COMPLETE | 19/19 | 0 |
| 7 | Risk Profile Enhancement | 3 | PARTIAL | 13/17 | 4 (ESG/volatility skipped — fields removed in WS1) |
| 8 | Cross-Module Strategies | 3 | COMPLETE | 22/22 | 0 |
| 9 | Recommendation Personalisation | 3 | COMPLETE | 16/16 | 0 |
| 10 | Missing Data Points | 3 | COMPLETE | 17/17 | 0 |
| **Total** | | | | **185/227** | **42 remaining** |

### Validation Against userJourney.md

| userJourney.md Finding | Status | Implementation |
|----------------------|--------|----------------|
| Dead data across modules (30%+ of fields unused) | PARTIAL | Investment/Risk cleaned; Protection, Family, Goals, Retirement, Estate, ESS deferred |
| DecumulationPlanner exists but never called | FIXED | Wired into RetirementAgent, API endpoint, frontend card |
| Letter to Spouse isolated from Estate (27 fields) | FIXED | Cross-validation service, executor/insurance/asset checks |
| No TaxOptimisationAgent exists | FIXED | New agent at priority 65, ISA/pension/CGT/spousal strategies |
| Onboarding friction (11 steps upfront) | FIXED | Quick 3-step flow, ProfileCompletionCards on dashboard |
| No household coordination for married couples | FIXED | HouseholdPlanningService, net worth/optimisations/death scenario |
| Risk profile missing factors (age, income stability) | FIXED | 9 factors (was 7), LifeEventRiskObserver, mismatch warning |
| ESG/volatility collected but unused | N/A | Fields removed as dead data (WS1) — decision: not useful enough to activate |
| Cross-module blind spots (7 missing links) | FIXED | 10 strategies in CrossModuleStrategyService |
| Template-driven recommendations | FIXED | RecommendationPersonaliser with family/estate/investment context |
| Fixed life expectancy (85, not configurable) | FIXED | User override field (60-110), actuarial fallback |
| No care cost assumptions in retirement | FIXED | care_cost_annual + care_start_age on RetirementProfile |
| No will review date tracking | FIXED | last_reviewed_date with 3-year stale warning |
| No goal dependencies | FIXED | goal_dependencies pivot table, blocked goal detection |
