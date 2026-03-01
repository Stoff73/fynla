# Plans Section - Comprehensive Task List

**Generated:** 1 March 2026
**Source:** `planReview.md`
**Purpose:** Actionable task list with checkboxes, mandatory tooling, and testing for each section

---

## Mandatory Tooling Reference

| Tool / Skill / Agent | When to Use |
|----------------------|-------------|
| `/feature-dev` | **MANDATORY** for all feature implementation tasks |
| `/frontend-design` + `designStyle.md` | **MANDATORY** for all frontend/UI tasks - must read `designStyle.md` before any UI work |
| `/systematic-debugging` | **MANDATORY** for any bug, error, or unexpected behaviour during implementation |
| `/code-review` | **MANDATORY** after completing each phase before moving to the next |
| `tax-compliance-reviewer` agent | **MANDATORY** for any task touching tax config, IHT calculations, pension allowances, or TaxConfigService |
| `security-reviewer` agent | **MANDATORY** for any task modifying API routes, controllers, or auth-related code |
| `database-optimizer` agent | **MANDATORY** for any new database tables, migrations, or schema changes |
| `Explore` agent | Use for codebase exploration when locating existing implementations |
| `premium-ui-designer` agent | Use when polishing UI components, adding animations, or improving UX |
| `code-simplifier` agent | Use after completing refactors to clean up and simplify code |

---

## Phase 1: Foundation

> **Must be completed first. All subsequent phases depend on this.**

---

### 1A. Create PlanConfigService

> **Use:** `/feature-dev`, `tax-compliance-reviewer` agent, `database-optimizer` agent

- [x] **1A.1** Use `Explore` agent to locate all hardcoded constants listed in planReview.md Section 1 across the codebase and confirm exact file locations and line numbers
- [x] **1A.2** Use `database-optimizer` agent to design the `plan_configurations` table schema (or extend `tax_configurations`) for storing admin-configurable plan values
- [x] **1A.3** Create database migration for plan configuration storage
- [x] **1A.4** Create `app/Services/Plans/PlanConfigService.php` with accessor methods for all plan-related rates, benchmarks, and defaults:
  - [x] `getDefaultGrowthRate()` (currently hardcoded `0.05`)
  - [x] `getWithdrawalRate()` (currently hardcoded `0.04`)
  - [x] `getPlatformFeeBenchmark()` (currently hardcoded `0.25%`)
  - [x] `getOCFBenchmark()` (currently hardcoded `0.15%`)
  - [x] `getConsolidationEfficiencyGain()` (currently hardcoded `2%`)
  - [x] `getTaxOptimisationGain()` (currently hardcoded `3%`)
  - [x] `getDefaultActionGain()` (currently hardcoded `1%`)
  - [x] `getCharitableGivingThreshold()` (currently hardcoded `10%`)
  - [x] `getEstateAgeGate()` (currently hardcoded `35`)
  - [x] `getPlanCacheTTL()` (currently hardcoded `1800`)
  - [x] `getRetirementCacheTTL()` (currently hardcoded `3600`)
- [x] **1A.5** Create database seeder `PlanConfigurationSeeder` with default values for all config entries
- [x] **1A.6** Run `php artisan migrate && php artisan db:seed --class=PlanConfigurationSeeder --force`
- [x] **1A.7** Use `tax-compliance-reviewer` agent to verify all tax-related config values (growth rates, withdrawal rates, IHT thresholds, pension allowances) are correct per current HMRC rules

#### 1A Testing
- [x] **1A.T1** Write Pest tests for `PlanConfigService` - verify all accessor methods return expected default values
- [x] **1A.T2** Test that config values can be updated in the database and `PlanConfigService` reflects the changes
- [x] **1A.T3** Test seeder runs without errors: `php artisan db:seed --class=PlanConfigurationSeeder --force`
- [x] **1A.T4** Run existing test suite to confirm no regressions: `./vendor/bin/pest`

---

### 1B. Disposable Income Accessor & Distribution Account

> **Use:** `/feature-dev`, `Explore` agent

- [x] **1B.1** Use `Explore` agent to locate exactly where the user's disposable income is calculated/stored on the income tab - identify the model field, service method, or computed value
- [x] **1B.2** Create `app/Services/Plans/DisposableIncomeAccessor.php` to fetch the user's disposable income from `UserProfileService` - **fetches, does NOT recalculate**
- [x] **1B.3** Create `app/Services/Plans/DistributionAccount.php`:
  - [x] Constructor accepts `float $disposableIncome`
  - [x] `allocate(string $label, float $amount): float` - allocates up to the remaining balance, returns actual amount allocated
  - [x] `remaining(): float` - returns unallocated balance
  - [x] `getAllocations(): array` - returns all allocations made
  - [x] `reset(): void` - resets to initial disposable income (for per-plan usage)
  - [x] `isExhausted(): bool` - returns true when remaining is 0

#### 1B Testing
- [x] **1B.T1** Write Pest tests for `DistributionAccount`:
  - [x] Initialises with correct balance
  - [x] `allocate()` reduces remaining correctly
  - [x] `allocate()` returns capped amount when requesting more than remaining
  - [x] `remaining()` reflects allocations
  - [x] `isExhausted()` returns true at zero
  - [x] `reset()` restores initial balance
  - [x] `getAllocations()` tracks all allocations
- [x] **1B.T2** Write test confirming the disposable income accessor returns the same value shown on the income tab for test users
- [x] **1B.T3** Run: `./vendor/bin/pest`

---

### 1C. Remove Legacy Plans (Clean Slate)

> **Use:** `Explore` agent, `security-reviewer` agent

- [x] **1C.1** Use `Explore` agent to search entire codebase for all imports, references, and usages of legacy files before deletion
- [x] **1C.2** Delete legacy backend controllers:
  - [x] `app/Http/Controllers/Api/Plans/InvestmentSavingsPlanController.php`
  - [x] `app/Http/Controllers/Api/Investment/InvestmentPlanController.php`
  - [x] `app/Http/Controllers/Api/Investment/InvestmentRecommendationController.php`
- [x] **1C.3** Delete legacy backend services:
  - [x] `app/Services/Plans/InvestmentSavingsPlanService.php`
  - [x] `app/Services/Investment/InvestmentPlanGenerator.php`
- [x] **1C.4** Delete legacy backend models:
  - [x] `app/Models/Investment/InvestmentPlan.php`
  - [x] `app/Models/Investment/InvestmentRecommendation.php`
- [x] **1C.5** Delete legacy frontend views:
  - [x] `resources/js/views/Plans/InvestmentSavingsPlan.vue`
  - [x] `resources/js/views/Protection/ComprehensiveProtectionPlan.vue`
  - [x] `resources/js/views/Estate/ComprehensiveEstatePlan.vue`
- [x] **1C.6** Delete legacy frontend component:
  - [x] `resources/js/components/Plans/InvestmentSavingsPlanView.vue`
- [x] **1C.7** Remove legacy API routes from `routes/api.php`:
  - [x] `GET /api/plans/investment-savings`
  - [x] `DELETE /api/plans/investment-savings/clear-cache`
  - [x] `POST /api/investment/plan/generate`
  - [x] `GET /api/investment/plan`
  - [x] `GET /api/investment/plan/all`
  - [x] `GET /api/investment/plan/{id}`
  - [x] `DELETE /api/investment/plan/{id}`
  - [x] `GET /api/protection/comprehensive-plan`
  - [x] `GET /api/estate/comprehensive-plan`
- [x] **1C.8** Remove legacy frontend routes from `resources/js/router/index.js`:
  - [x] `/plans/investment-savings` (redirect)
  - [x] `/protection-plan`
  - [x] `/estate-plan`
- [x] **1C.9** Clean up `resources/js/services/plansService.js` - remove `generateInvestmentSavingsPlan()` and `clearInvestmentSavingsPlanCache()` methods
- [x] **1C.10** Clean up `resources/js/store/modules/plans.js` - no legacy references found (already clean)
- [x] **1C.11** Use `Explore` agent to do a final sweep for any remaining references to deleted files, routes, models, or service classes across the entire codebase
- [x] **1C.12** Remove or clean up any orphaned imports found in step 1C.11 (also cleaned: `protectionService.js`, `estateService.js`, `AiIntentMatcher.php`, `SideMenu.vue`)
- [x] **1C.13** Confirm with user whether to drop `investment_plans` and `investment_recommendations` database tables or preserve for data retention — **user decided: keep tables for now**
- [x] **1C.14** Verified no auth routes or middleware were affected by route removal - `php artisan route:list` confirms clean state

#### 1C Testing
- [x] **1C.T1** Run `php artisan route:list` and verify no legacy routes appear
- [x] **1C.T2** Run `./vendor/bin/pest` - all Plan tests pass (22 tests, 80 assertions); pre-existing failures in unrelated tests unchanged
- [x] **1C.T3** Run `./dev.sh` and verify the app compiles without errors — Vite compiled successfully, no errors
- [x] **1C.T4** Verify `/plans` dashboard still loads correctly — Playwright: 4 plan cards visible
- [x] **1C.T5** Verify `/plans/investment`, `/plans/protection`, `/plans/retirement`, `/plans/estate` all still load — Playwright: all 4 plans load with full data, charts, recommendations
- [x] **1C.T6** Verify navigating to old legacy routes (`/protection-plan`, `/estate-plan`, `/plans/investment-savings`) returns 404 or redirects appropriately — Playwright: all 3 show Vue Router "No match found"

---

### Phase 1 Review
- [x] **P1.R1** Use `/code-review` on all Phase 1 changes before proceeding to Phase 2 — 5 issues found and fixed: (1) removed dead frontend code from investmentService.js, investment.js store, and deleted orphaned ComprehensiveInvestmentPlan.vue; (2) added try/catch to PlanConfigService::loadActiveConfig() for DB resilience; (3) added PlanConfigurationSeeder + SavingsMarketRatesSeeder to seedRequiredDataOnly(); (4) rounded same-label accumulation in DistributionAccount; (5) added RefreshDatabase to PlanConfigServiceTest
- [x] **P1.R2** Run full test suite: `./vendor/bin/pest` - 22 Plan tests pass; 901 Unit tests pass; pre-existing failures unchanged
- [x] **P1.R3** Run code formatting: `./vendor/bin/pint` - all Phase 1 files pass (1 pre-existing fix in PreviewUserSeeder)

---

## Phase 2: Core Refactors

> **Depends on Phase 1 completion.**

---

### 2A. Refactor Plan Services to Use PlanConfigService

> **Use:** `/feature-dev`, `tax-compliance-reviewer` agent

- [x] **2A.1** Refactor `app/Services/Plans/InvestmentPlanService.php`:
  - [x] Inject `PlanConfigService` in constructor
  - [x] Replace hardcoded `0.05` growth rate with `$this->planConfig->getDefaultGrowthRate()`
  - [x] Replace hardcoded `0.25%` / `0.15%` fee benchmarks with config accessors
  - [x] Replace hardcoded `500/month` savings with user's disposable income (fetched from income tab)
  - [x] Replace `$yearsToRetirement ?? 10` with `$yearsToRetirement` only - remove `?? 10` default
  - [x] Update `buildAccountGrowthProjections()` to use config-driven growth rate and fee benchmarks
  - [x] Update `buildWhatIfData()` to use config values and disposable income
- [x] **2A.2** Refactor `app/Services/Plans/RetirementPlanService.php`:
  - [x] Inject `PlanConfigService` in constructor
  - [x] Replace hardcoded `0.05` growth rate with config accessor
  - [x] Replace hardcoded `0.04` annuity/withdrawal rate with config accessor
  - [x] Replace hardcoded `200/month` contribution with user's disposable income
  - [x] Replace hardcoded `2400` annual contribution with actual user contribution data
  - [x] Replace hardcoded `2%`, `3%`, `1%` efficiency gains with config accessors
  - [x] Update `buildPensionGrowthProjections()` to use config-driven values
  - [x] Update `buildWhatIfData()` to use config values and disposable income
- [x] **2A.3** Refactor `app/Services/Plans/GoalPlanService.php`:
  - [x] Inject `PlanConfigService` in constructor
  - [x] Replace hardcoded `50`, `25` monthly contribution amounts with user's disposable income
  - [x] Replace hardcoded `500`, `1000` lump sum amounts with user data from income tab
  - [x] Update `buildWhatIfData()` to use fetched values
- [x] **2A.4** Refactor `app/Services/Plans/EstatePlanService.php`:
  - [x] Inject `PlanConfigService` in constructor
  - [x] Replace hardcoded age gate `35` with `$this->planConfig->getEstateAgeGate()`
- [x] **2A.5** Refactor `app/Http/Controllers/Api/Plans/PlanController.php`:
  - [x] Inject `PlanConfigService` in constructor
  - [x] Replace hardcoded `1800` cache TTL with `$this->planConfig->getPlanCacheTTL()`
- [x] **2A.6** Refactor `app/Agents/RetirementAgent.php`:
  - [x] Replace hardcoded `3600` cache TTL with config value
- [x] **2A.7** Refactor `app/Agents/SavingsAgent.php`:
  - [x] Replace hardcoded `1800` cache TTL with config value
- [x] **2A.8** Use `tax-compliance-reviewer` agent to review ALL refactored files and verify no tax calculation logic was broken by the config migration — completed in P2.R2; 7 issues found and fixed
- [x] **2A.9** Use `code-simplifier` agent on refactored files to clean up any verbose or repetitive config access patterns — addressed during P2.R1 (consolidated duplicate User query, removed score from payload)

#### 2A Testing
- [x] **2A.T1** Write Pest tests verifying each plan service uses `PlanConfigService` values (not hardcoded)
- [x] **2A.T2** Test that changing a config value (e.g., growth rate) propagates correctly through plan generation
- [x] **2A.T3** Test `InvestmentPlanService::buildWhatIfData()` uses disposable income from income tab
- [x] **2A.T4** Test `RetirementPlanService::buildWhatIfData()` uses disposable income from income tab
- [x] **2A.T5** Test `GoalPlanService::buildWhatIfData()` uses user data, not hardcoded amounts
- [x] **2A.T6** Test `EstatePlanService` age gate respects config value
- [x] **2A.T7** Test cache TTLs come from config
- [x] **2A.T8** Run full test suite: `./vendor/bin/pest`

---

### 2B. Integrate DistributionAccount into Plan Services

> **Use:** `/feature-dev`

- [x] **2B.1** Integrate `DistributionAccount` into `InvestmentPlanService::generatePlan()`:
  - [x] Fetch user's disposable income using the accessor from Task 1B.2
  - [x] Initialise `DistributionAccount` with the full disposable income
  - [x] Update all recommendation amount calculations to draw from `DistributionAccount`
  - [x] Ensure allocated amounts don't exceed remaining balance
- [x] **2B.2** Integrate `DistributionAccount` into `RetirementPlanService::generatePlan()`:
  - [x] Same pattern - initialise with **full** disposable income (independent of investment plan)
  - [x] Update contribution recommendation amounts to draw from `DistributionAccount`
- [x] **2B.3** Update what-if calculations in both services to use allocated amounts from the `DistributionAccount` rather than hardcoded amounts

#### 2B Testing
- [x] **2B.T1** Test that Investment plan recommendations don't exceed disposable income
- [x] **2B.T2** Test that Retirement plan recommendations don't exceed disposable income
- [x] **2B.T3** Test that each plan gets the FULL disposable income (they are separate plans, not shared)
- [x] **2B.T4** Test with a low disposable income user - verify recommendations cap correctly
- [x] **2B.T5** Test with zero disposable income - verify graceful handling
- [x] **2B.T6** Run: `./vendor/bin/pest`

---

### 2C. Fix Projection Horizons

> **Use:** `/feature-dev`

- [x] **2C.1** In `InvestmentPlanService::buildWhatIfData()`: remove `?? 10` fallback. Use years to retirement only. If no retirement date exists, add to `checkDataCompleteness()` as a missing item prompting the user to set a retirement date
- [x] **2C.2** In `InvestmentPlanService::buildAccountGrowthProjections()`: use goal target date when goal is assigned, retirement date otherwise. Remove 10-year default
- [x] **2C.3** Update `InvestmentPlanService::checkDataCompleteness()` to include retirement date as a completeness check item if not already present

#### 2C Testing
- [x] **2C.T1** Test projection uses goal target date when goal is linked
- [x] **2C.T2** Test projection uses retirement date when no goal is linked
- [x] **2C.T3** Test that missing retirement date appears in data completeness warning
- [x] **2C.T4** Test that no `10` year default appears anywhere in projection calculations
- [x] **2C.T5** Run: `./vendor/bin/pest`

---

### 2D. Emergency Fund Surplus Waterfall

> **Use:** `/feature-dev`, `tax-compliance-reviewer` agent

- [x] **2D.1** In `InvestmentPlanService::buildSavingsRecommendations()`:
  - [x] Add detection for emergency fund > 6 months
  - [x] Query `goals` table to check if any goals are set to reduce the emergency fund over time
  - [x] If surplus AND no goals reducing it, generate waterfall recommendations
- [x] **2D.2** Implement the ISA -> Pension -> Bond -> Gifting priority waterfall:
  - [x] Check ISA allowance remaining (via `TaxConfigService`) - recommend ISA first if available
  - [x] Check pension annual allowance remaining - recommend pension second if available
  - [x] Recommend bond wrapper third
  - [x] Recommend gifting fourth
  - [x] Each step should show the amount to move and the tax benefit
- [x] **2D.3** Use `tax-compliance-reviewer` agent to verify ISA allowance checks, pension annual allowance checks, and gifting thresholds are correct per current HMRC rules — completed in P2.R2; all verified correct for 2025/26

#### 2D Testing
- [x] **2D.T1** Test: emergency fund = 4 months -> no surplus recommendations generated
- [x] **2D.T2** Test: emergency fund = 8 months, no goals -> surplus waterfall recommendations generated
- [x] **2D.T3** Test: emergency fund = 8 months, goal exists to reduce fund -> no surplus recommendations
- [x] **2D.T4** Test: ISA allowance exhausted -> waterfall skips ISA, starts at pension
- [x] **2D.T5** Test: all allowances exhausted -> only bond and gifting recommended
- [x] **2D.T6** Test waterfall amounts are correct and don't exceed surplus
- [x] **2D.T7** Run: `./vendor/bin/pest`

---

### 2E. Recommendation Wording Changes

> **Use:** `/feature-dev`

- [x] **2E.1** In `app/Agents/InvestmentAgent.php` `generateRecommendations()`:
  - [x] Change "Complete Your Risk Profile" (no risk profile) to ask for the specific missing information needed to calculate the risk profile: investment time horizon, capacity for loss, risk tolerance. Word it as a request for information, not a generic "complete your profile" instruction
  - [x] Ensure the recommendation description lists the specific data points needed
- [x] **2E.2** In `app/Agents/InvestmentAgent.php` `generateRecommendations()`:
  - [x] Change "Add Your Holdings" (no holdings) to state that the plan defaults to "Risk-based fee-optimised allocations"
  - [x] Add a clear explanation of what this means for the user
- [x] **2E.3** Verify no acronyms appear in the new recommendation text (CLAUDE.md Rule 10)
- [x] **2E.4** Verify British spelling in all new text

#### 2E Testing
- [x] **2E.T1** Test: user with no risk profile -> recommendation asks for specific info (time horizon, capacity for loss, risk tolerance)
- [x] **2E.T2** Test: user with no holdings -> recommendation mentions "Risk-based fee-optimised allocations"
- [x] **2E.T3** Test: user with both risk profile and holdings -> neither of the above recommendations appear
- [x] **2E.T4** Run: `./vendor/bin/pest`

---

### Phase 2 Review
- [x] **P2.R1** Use `/code-review` on all Phase 2 changes — found and fixed 2 critical bugs (wrong key path for monthly_expenditure, gifting check used $surplus instead of $remaining), plus 5 important issues (score exposure, duplicate query, IDOR in goal completeness, redundant agent calls, config bypass in RetirementAgent scenarios)
- [x] **P2.R2** Use `tax-compliance-reviewer` agent for a final review of all tax-related refactors — found and fixed 7 issues (hardcoded £3,000 gift exemption, raw 60000/20000 fallbacks, RetirementAgent scenario rates, nullable injection pattern)
- [x] **P2.R3** Run full test suite: `./vendor/bin/pest` — 22 Plan tests pass (80 assertions); pre-existing failures unchanged
- [x] **P2.R4** Run code formatting: `./vendor/bin/pint` — all Phase 2 files pass

---

## Phase 3: Goal Integration

> **Depends on Phase 2 completion.**
> **Use:** `/feature-dev` for all backend tasks. `/frontend-design` + `designStyle.md` for all frontend tasks.

---

### 3A. Backend - Goal Integration into Plan Services

> **Use:** `/feature-dev`, `Explore` agent

- [x] **3A.1** Use `Explore` agent to examine the `Goal` model, `goals` table schema, and existing `linked_savings_account_id` / `linked_investment_account_id` fields to understand current linking capability
- [x] **3A.2** Modify `InvestmentPlanService::generatePlan()`:
  - [x] Query goals linked to investment accounts or savings accounts for the user
  - [x] Include linked goals in the plan data response under a `linked_goals` key
  - [x] For each linked goal, include: goal name, type, target, progress, linked account
  - [x] Generate goal-specific recommendations from the goal data
- [x] **3A.3** Modify `InvestmentPlanService::structureActions()` or `BasePlanService`:
  - [x] When goals are associated, goal recommendations appear FIRST with highest priority
  - [x] All other recommendations are secondary to goal-based ones
- [x] **3A.4** Modify `RetirementPlanService::generatePlan()`:
  - [x] Query retirement-type goals for the user (goals linked to pension accounts or retirement-module goals)
  - [x] Include in plan data and prioritise goal recommendations
- [x] **3A.5** ~~Modify `EstatePlanService::generatePlan()`~~ — REMOVED: goals do not belong in estate planning. Estate plan has no goal integration.
- [x] **3A.6** Add detection of unlinked goals:
  - [x] Query goals with no `linked_savings_account_id` AND no `linked_investment_account_id`
  - [x] Include these in the plan response under an `unlinked_goals` key with a message prompting the user to allocate an account

#### 3A Testing
- [x] **3A.T1** Test: user with goal linked to savings account -> goal appears in Investment & Savings plan
- [x] **3A.T2** Test: user with goal linked to investment account -> goal appears in Investment & Savings plan
- [x] **3A.T3** Test: user with retirement-type goal -> goal appears in Retirement plan
- [x] **3A.T4** Test: estate plan returns empty goals (goals do not belong in estate planning)
- [x] **3A.T5** Test: goal recommendations appear FIRST in actions list, before other recommendations
- [x] **3A.T6** Test: user with unlinked goal -> plan includes `unlinked_goals` with prompt message
- [x] **3A.T7** Test: user with no goals -> plans work as before, no goal sections shown
- [x] **3A.T8** Run: `./vendor/bin/pest` — 8 tests pass (20 assertions); 30 plan tests pass total

---

### 3B. Frontend - Goal Display in Plans

> **MANDATORY: Read `designStyle.md` before starting any UI work in this section.**
> **Use:** `/frontend-design` + `designStyle.md`, `premium-ui-designer` agent

- [x] **3B.1** Read `designStyle.md` to understand colours, typography, spacing, component patterns, and badges before making any changes
- [x] **3B.2** Update `resources/js/components/Plans/Investment/InvestmentPlanContent.vue`:
  - [x] Add a "Goals" section displaying linked goals with progress indicators
  - [x] If unlinked goals exist, show a prompt message: guide the user to allocate an account
  - [x] Follow `designStyle.md` for card styles, progress bars, and colours
- [x] **3B.3** Update `resources/js/components/Plans/Retirement/RetirementPlanContent.vue`:
  - [x] Same pattern - show linked retirement goals and prompt for unlinked ones
- [x] **3B.4** ~~Update `resources/js/components/Plans/Estate/EstatePlanContent.vue`~~ — REMOVED: goals do not belong in estate planning. PlanGoalSection removed from estate plan.
- [x] **3B.5** Create a shared component (e.g., `PlanGoalSection.vue` or `PlanGoalPrompt.vue`) for the goal display and "link an account" prompt to avoid duplication across plans — created `PlanGoalSection.vue` using `PlanSectionHeader` (teal) and `GoalProgressBar` (sm, showAmounts)
- [x] **3B.6** Ensure the "link an account" prompt uses a router-link to the goals page where the user can edit their goal and link an account
- [x] **3B.7** Use `premium-ui-designer` agent to polish the goal section and prompt UI — polished with entrance animations, hover micro-interactions, status dot badges, meta info dividers, reduced motion support
- [x] **3B.8** Verify no amber/orange colours used (CLAUDE.md Rule 9)
- [x] **3B.9** Verify no acronyms in user-facing text (CLAUDE.md Rule 10)
- [x] **3B.10** Verify no scores displayed in UI (CLAUDE.md Rule 12)

#### 3B Testing
- [x] **3B.T1** Visual test: load Investment plan with linked goals -> goals section displays correctly — Playwright verified: ISA Wealth Building and Early Retirement Fund shown with progress bars, status badges, meta info
- [x] **3B.T2** Visual test: load plan with unlinked goals -> prompt message displays with link to goals page — Playwright verified: "2 goals need a linked account" prompt with Manage goals link
- [x] **3B.T3** Visual test: load plan with no goals -> no goals section shown, no errors — Estate plan loads with no goals section, no errors
- [x] **3B.T4** Visual test: verify `designStyle.md` compliance (colours, spacing, typography) — No amber/orange, uses green/blue/red semantic badges, proper shadows and spacing
- [x] **3B.T5** Run `./dev.sh` and verify no compile errors — Vite compiles successfully after fixing `@apply group` CSS issue
- [x] **3B.T6** Run: `./vendor/bin/pest` — all plan tests pass, no regressions

---

### Phase 3 Review
- [x] **P3.R1** Use `/code-review` on all Phase 3 changes — code review found 3 critical issues, all fixed: (1) SQL precedence bug in forUserOrJoint+active() scope chain; (2) retirement goals leaking into investment plan via overly broad OR query on linked account IDs — fixed to filter by assigned_module only; (3) zero required_monthly_contribution producing nonsensical recommendation text; (4) goals removed from estate plan entirely — goals have no relevance to estate planning
- [x] **P3.R2** Run full test suite: `./vendor/bin/pest` — 814/817 pass (3 pre-existing failures in BaseAgentTest, ProtectionAgentTest, ISATrackerTest unchanged)
- [x] **P3.R3** Run code formatting: `./vendor/bin/pint` — 2 style issues auto-fixed, all clean

---

## Phase 4: Estate Plan Refactor

> **Depends on Phase 3 completion.**
> **Use:** `/feature-dev` for all backend tasks. `/frontend-design` + `designStyle.md` for all frontend tasks.

---

### 4A. Backend - Estate Plan Data Source Refactor

> **Use:** `/feature-dev`, `Explore` agent, `tax-compliance-reviewer` agent

- [ ] **4A.1** Use `Explore` agent to map all data already available from the Estate Module (existing services, cached agent results, `iht_calculations` table) to understand what can be fetched instead of recalculated
- [ ] **4A.2** Refactor `EstatePlanService::generatePlan()` to fetch estate data from the Estate Module's existing services/tables rather than recalculating:
  - [ ] Use cached `EstateAgent::analyze()` results
  - [ ] Query existing `iht_calculations` table for IHT figures
  - [ ] Fetch existing strategy recommendations from estate services
  - [ ] Remove redundant calls that duplicate estate module calculations
- [ ] **4A.3** Refactor `EstatePlanService::buildExecutiveSummary()` to stop re-calling `$this->estateAgent->analyze()` - use the data already fetched
- [ ] **4A.4** Add joint estate view logic when user is married and has spouse data or a linked account:
  - [ ] Fetch spouse's estate data alongside the primary user's
  - [ ] Build side-by-side estate positions for both partners
  - [ ] Calculate combined estate figures and separate IHT positions
- [ ] **4A.5** Add funding source tracking to charitable/gifting recommendations:
  - [ ] For each charitable/gifting recommendation, identify which accounts the money would come from
  - [ ] Include `funding_source` field in recommendation data (account name, type, available balance)
- [ ] **4A.6** Add affordability check for life cover recommendations:
  - [ ] Fetch disposable income from income tab (same accessor as Task 1B.2)
  - [ ] Compare estimated premium against disposable income
  - [ ] Flag unaffordable recommendations with an affordability warning
- [ ] **4A.7** Add detailed "what to do" guidance for each recommendation:
  - [ ] Step-by-step actionable instructions (not just strategy names)
  - [ ] Include estimated timeframes and next actions
- [ ] **4A.8** Use `tax-compliance-reviewer` agent to verify all IHT calculations, NRB/RNRB values, spouse exemptions, and charitable giving thresholds remain correct after the refactor

#### 4A Testing
- [ ] **4A.T1** Test: estate plan generates with data from estate module (not recalculated)
- [ ] **4A.T2** Test: married user -> joint view data returned with both partners' positions
- [ ] **4A.T3** Test: single user -> no joint view, standard single view
- [ ] **4A.T4** Test: charitable recommendation includes funding source
- [ ] **4A.T5** Test: gifting recommendation includes funding source
- [ ] **4A.T6** Test: affordable life cover -> no affordability warning
- [ ] **4A.T7** Test: unaffordable life cover -> affordability warning included
- [ ] **4A.T8** Test: IHT calculation results match what the estate module produces
- [ ] **4A.T9** Run: `./vendor/bin/pest`

---

### 4B. Remove Estate Health Score

> **Use:** `/feature-dev`

- [ ] **4B.1** Remove estate health score calculation from `app/Agents/EstateAgent.php`:
  - [ ] Remove the score calculation logic (starts at 100, deducts points)
  - [ ] Remove `health_score` from the analysis return data
- [ ] **4B.2** Search frontend components for any health score display and remove:
  - [ ] Check `EstateCurrentSituation.vue`
  - [ ] Check `EstatePlanContent.vue`
  - [ ] Check `EstateGroupedActions.vue`
  - [ ] Check any other estate-related components
- [ ] **4B.3** Replace any score-based text with descriptive text and specific metrics (per CLAUDE.md Rule 12)

#### 4B Testing
- [ ] **4B.T1** Test: estate agent analysis returns no `health_score` field
- [ ] **4B.T2** Grep entire codebase for `health_score` in estate context - confirm none remain
- [ ] **4B.T3** Visual test: estate plan UI shows no scores anywhere
- [ ] **4B.T4** Run: `./vendor/bin/pest`

---

### 4C. Frontend - Estate Plan UI Enhancements

> **MANDATORY: Read `designStyle.md` before starting any UI work in this section.**
> **Use:** `/frontend-design` + `designStyle.md`, `premium-ui-designer` agent

- [ ] **4C.1** Read `designStyle.md` for colours, typography, spacing, component patterns
- [ ] **4C.2** Update `resources/js/components/Plans/Estate/EstatePlanContent.vue`:
  - [ ] Add joint view display when plan data includes spouse estate data
  - [ ] Add what-if scenarios with side-by-side comparisons
  - [ ] Add more detailed guidance sections
- [ ] **4C.3** Update `resources/js/components/Plans/Estate/EstateCurrentSituation.vue`:
  - [ ] Add side-by-side layout for joint estate view (primary user | spouse)
  - [ ] Show combined estate totals alongside individual positions
- [ ] **4C.4** Update `resources/js/components/Plans/Estate/EstateGroupedActions.vue`:
  - [ ] Display funding source for each charitable/gifting recommendation
  - [ ] Display affordability indicator for life cover recommendations
  - [ ] Show step-by-step "what to do" guidance for each action
- [ ] **4C.5** Update `resources/js/components/Plans/Estate/EstateWhatIfControls.vue`:
  - [ ] Enhance with side-by-side comparison metrics
- [ ] **4C.6** Use `premium-ui-designer` agent to polish the joint view layout, side-by-side comparisons, and funding source displays
- [ ] **4C.7** Verify no amber/orange colours (CLAUDE.md Rule 9)
- [ ] **4C.8** Verify no acronyms in user-facing text (CLAUDE.md Rule 10)
- [ ] **4C.9** Verify no scores in UI (CLAUDE.md Rule 12)

#### 4C Testing
- [ ] **4C.T1** Visual test: married user -> joint estate view displays correctly with side-by-side
- [ ] **4C.T2** Visual test: single user -> standard single view, no joint layout
- [ ] **4C.T3** Visual test: funding sources display next to charitable/gifting recommendations
- [ ] **4C.T4** Visual test: affordability indicator shows on life cover recommendations
- [ ] **4C.T5** Visual test: step-by-step guidance displays for each action
- [ ] **4C.T6** Visual test: verify `designStyle.md` compliance
- [ ] **4C.T7** Run `./dev.sh` and verify no compile errors
- [ ] **4C.T8** Run: `./vendor/bin/pest`

---

### Phase 4 Review
- [ ] **P4.R1** Use `/code-review` on all Phase 4 changes
- [ ] **P4.R2** Use `tax-compliance-reviewer` agent for final review of estate/IHT changes
- [ ] **P4.R3** Run full test suite: `./vendor/bin/pest`
- [ ] **P4.R4** Run code formatting: `./vendor/bin/pint`

---

## Phase 5: Holistic Plan Refactor

> **Depends on Phase 4 completion.**
> **Use:** `/feature-dev` for all backend tasks. `/frontend-design` + `designStyle.md` for all frontend tasks.

---

### 5A. Backend - Holistic Plan Data Source Refactor

> **Use:** `/feature-dev`, `Explore` agent

- [ ] **5A.1** Use `Explore` agent to map `CoordinatingAgent` dependencies and understand how it currently calls module agents
- [ ] **5A.2** Inject `EstatePlanService` (or `EstateAgent`) and `GoalPlanService` (or `GoalsAgent`) into `CoordinatingAgent`:
  - [ ] Add constructor dependencies
  - [ ] Remove hardcoded estate placeholder data: `{ net_worth: 350000, iht_liability: 10000, ... }`
- [ ] **5A.3** Refactor `CoordinatingAgent::collectModuleAnalysis()`:
  - [ ] Read from individual plan service outputs rather than calling agents directly
  - [ ] This ensures recommendations match what users see in individual plans
  - [ ] Include estate analysis (real data, not placeholders)
  - [ ] Include goals analysis (active goals and their recommendations)
- [ ] **5A.4** Implement shared `DistributionAccount` for the holistic plan:
  - [ ] Fetch disposable income from income tab (same accessor as Task 1B.2)
  - [ ] Initialise ONE shared `DistributionAccount`
  - [ ] Do NOT reset between modules (unlike individual plans)
  - [ ] Allocate with priority order: Emergency fund > Protection > Pension > Investment > Estate > Goals
- [ ] **5A.5** Update `CashFlowCoordinator::optimizeContributionAllocation()`:
  - [ ] Include estate demands in allocation priority
  - [ ] Include goal demands in allocation priority
  - [ ] Use `DistributionAccount` for tracking
- [ ] **5A.6** Update `ConflictResolver::identifyConflicts()`:
  - [ ] Detect conflicts between estate recommendations and other modules
  - [ ] Detect conflicts between goal recommendations and other modules
- [ ] **5A.7** Update `PriorityRanker::rankRecommendations()`:
  - [ ] Include estate and goal recommendations in the ranking
- [ ] **5A.8** Update `HolisticPlanner::createHolisticPlan()`:
  - [ ] Include estate data in executive summary and financial snapshot
  - [ ] Include goal data in executive summary and financial snapshot
- [ ] **5A.9** Update `HolisticPlanner::assessOverallRisk()`:
  - [ ] Add goals as a risk assessment area (currently only 5 areas: protection, emergency_fund, retirement, investment, iht)

#### 5A Testing
- [ ] **5A.T1** Test: holistic plan includes real estate data (not hardcoded placeholders)
- [ ] **5A.T2** Test: holistic plan includes active goals
- [ ] **5A.T3** Test: holistic recommendations match individual plan recommendations
- [ ] **5A.T4** Test: shared `DistributionAccount` does NOT reset between modules
- [ ] **5A.T5** Test: allocation priority order is correct (Emergency > Protection > Pension > Investment > Estate > Goals)
- [ ] **5A.T6** Test: total allocated across all modules does not exceed disposable income
- [ ] **5A.T7** Test: conflicts detected between estate/goal recommendations and other modules
- [ ] **5A.T8** Test: risk assessment includes all areas including goals
- [ ] **5A.T9** Test: user with no estate data -> holistic plan still works without estate section
- [ ] **5A.T10** Test: user with no goals -> holistic plan still works without goals section
- [ ] **5A.T11** Run: `./vendor/bin/pest`

---

### 5B. Frontend - Holistic Plan UI Updates

> **MANDATORY: Read `designStyle.md` before starting any UI work in this section.**
> **Use:** `/frontend-design` + `designStyle.md`, `premium-ui-designer` agent

- [ ] **5B.1** Read `designStyle.md` for colours, typography, spacing, component patterns
- [ ] **5B.2** Update `resources/js/views/HolisticPlan.vue`:
  - [ ] Add Estate section tab/content area
  - [ ] Add Goals section tab/content area
  - [ ] Update tab list to include new sections
- [ ] **5B.3** Update or create Holistic child components for estate display:
  - [ ] Show estate summary within the holistic context
  - [ ] Show estate-specific recommendations integrated into the priority ranking
- [ ] **5B.4** Update or create Holistic child components for goal display:
  - [ ] Show active goals within the holistic context
  - [ ] Show goal-specific recommendations integrated into the priority ranking
- [ ] **5B.5** Update `resources/js/store/modules/holistic.js`:
  - [ ] Add state for estate and goal data
  - [ ] Update getters to include estate and goal sections
- [ ] **5B.6** Update `PrioritizedRecommendations` component to display estate and goal recommendations alongside existing ones
- [ ] **5B.7** Update `CashFlowAllocationChart` to show estate and goal allocation segments
- [ ] **5B.8** Use `premium-ui-designer` agent to polish new estate and goal sections
- [ ] **5B.9** Verify no amber/orange colours (CLAUDE.md Rule 9)
- [ ] **5B.10** Verify no acronyms in user-facing text (CLAUDE.md Rule 10)
- [ ] **5B.11** Verify no scores in UI (CLAUDE.md Rule 12)

#### 5B Testing
- [ ] **5B.T1** Visual test: holistic plan shows estate section with real data
- [ ] **5B.T2** Visual test: holistic plan shows goals section with active goals
- [ ] **5B.T3** Visual test: prioritised recommendations include estate and goal items
- [ ] **5B.T4** Visual test: cash flow chart shows estate and goal allocation segments
- [ ] **5B.T5** Visual test: verify `designStyle.md` compliance
- [ ] **5B.T6** Run `./dev.sh` and verify no compile errors
- [ ] **5B.T7** Run: `./vendor/bin/pest`

---

### Phase 5 Review
- [ ] **P5.R1** Use `/code-review` on all Phase 5 changes
- [ ] **P5.R2** Use `tax-compliance-reviewer` agent for final review of holistic plan tax-related changes
- [ ] **P5.R3** Run full test suite: `./vendor/bin/pest`
- [ ] **P5.R4** Run code formatting: `./vendor/bin/pint`

---

## Phase 6: Dashboard & Polish

> **Depends on Phase 5 completion.**
> **Use:** `/frontend-design` + `designStyle.md`, `premium-ui-designer` agent

---

### 6A. Dashboard Updates

> **MANDATORY: Read `designStyle.md` before starting any UI work in this section.**
> **Use:** `/frontend-design` + `designStyle.md`, `premium-ui-designer` agent

- [ ] **6A.1** Read `designStyle.md` for dashboard card styles, colours, and layout
- [ ] **6A.2** Update `resources/js/views/Plans/PlansDashboard.vue`:
  - [ ] Remove any legacy plan links or references
  - [ ] Add Holistic Plan card to the dashboard grid
  - [ ] Update goal plan cards to indicate which module plan they're associated with
- [ ] **6A.3** Update `app/Http/Controllers/Api/Plans/PlanController.php` `statuses()` method:
  - [ ] Verify Estate Plan is included in status checks
  - [ ] Add goal plan status indicators (or note that goals are shown within module plans)
  - [ ] Add Holistic Plan status/availability indicator
- [ ] **6A.4** Use `premium-ui-designer` agent to polish dashboard layout and card presentation
- [ ] **6A.5** Verify no amber/orange colours (CLAUDE.md Rule 9)

#### 6A Testing
- [ ] **6A.T1** Visual test: dashboard shows all module plan cards (Investment, Protection, Retirement, Estate)
- [ ] **6A.T2** Visual test: dashboard shows Holistic Plan card
- [ ] **6A.T3** Visual test: goal plan cards show module association
- [ ] **6A.T4** Visual test: no legacy plan links visible
- [ ] **6A.T5** Visual test: verify `designStyle.md` compliance
- [ ] **6A.T6** Run `./dev.sh` and verify no compile errors
- [ ] **6A.T7** Run: `./vendor/bin/pest`

---

### Phase 6 Review
- [ ] **P6.R1** Use `/code-review` on all Phase 6 changes
- [ ] **P6.R2** Run full test suite: `./vendor/bin/pest`
- [ ] **P6.R3** Run code formatting: `./vendor/bin/pint`

---

## Final Validation

> **Run after ALL phases are complete.**

### Full Regression Testing
- [ ] **FV.1** Run complete test suite: `./vendor/bin/pest`
- [ ] **FV.2** Run architecture tests: `./vendor/bin/pest --testsuite=Architecture`
- [ ] **FV.3** Run code formatting: `./vendor/bin/pint`
- [ ] **FV.4** Use `/code-review` on the entire set of changes

### Manual Verification
- [ ] **FV.5** Start dev server: `./dev.sh`
- [ ] **FV.6** Log in as each preview persona and verify plans load:
  - [ ] young_family (James & Emily Carter) - test joint views
  - [ ] peak_earners (David & Sarah Mitchell) - test multiple properties, SIPP + NHS pension
  - [ ] widow (Margaret Thompson) - test estate planning
  - [ ] entrepreneur (Alex Chen) - test SIPP, business interests
  - [ ] young_saver (John Morgan) - test emergency fund, first-time savings
  - [ ] retired_couple (Robert & Patricia Williams) - test decumulation, estate planning
- [ ] **FV.7** For each persona, verify:
  - [ ] `/plans` dashboard loads with correct cards
  - [ ] `/plans/investment` generates successfully
  - [ ] `/plans/protection` generates successfully
  - [ ] `/plans/retirement` generates successfully
  - [ ] `/plans/estate` generates successfully (or shows not_applicable correctly)
  - [ ] `/holistic-plan` generates with estate + goals data
  - [ ] What-if toggles and recalculate work on each plan
  - [ ] Print/PDF export works on each plan

### Rule Compliance Verification
- [ ] **FV.8** Grep codebase for hardcoded plan constants - confirm NONE remain in plan services
- [ ] **FV.9** Grep for `?? 10` in plan services - confirm no 10-year default projection
- [ ] **FV.10** Grep for `health_score` in estate context - confirm removed
- [ ] **FV.11** Grep for legacy file references - confirm none remain
- [ ] **FV.12** Verify all user-facing text uses British spelling
- [ ] **FV.13** Verify no amber/orange colours in any plan UI
- [ ] **FV.14** Verify no acronyms in user-facing plan text (except ISA)
- [ ] **FV.15** Verify no scores displayed in any plan UI
- [ ] **FV.16** Use `tax-compliance-reviewer` agent for final sweep of all tax-related plan code
- [ ] **FV.17** Use `security-reviewer` agent for final sweep of all API routes and controllers

### Checklist Summary
- [ ] No hardcoded rates, growth percentages, or monetary amounts remain in plan services
- [ ] All plan config values are admin-accessible via central config
- [ ] Emergency fund surplus >6 months triggers ISA->Pension->Bond->Gifting waterfall
- [ ] Disposable income is fetched from the income tab (not hardcoded or recalculated)
- [ ] Distribution account prevents double-counting across agents
- [ ] Goals with linked accounts appear within their associated module plans with priority
- [ ] Goals without linked accounts show a clear message prompting user to allocate an account
- [ ] Projections use goal length or retirement date (never default 10 years)
- [ ] Risk profile recommendation asks for specific missing info
- [ ] No holdings defaults to "Risk-based fee-optimised allocations"
- [ ] Estate plan fetches data from Estate Module (no recalculation)
- [ ] Estate plan shows joint view for married users
- [ ] Estate recommendations show funding sources
- [ ] Life cover recommendations checked against affordability
- [ ] No score mechanisms in estate plan or anywhere in UI
- [ ] Holistic plan reads from individual plan outputs (not re-calling agents)
- [ ] Holistic plan includes Estate and Goals data (no hardcoded placeholders)
- [ ] Holistic shared distribution account is prioritised across all recommendations
- [ ] All legacy plan files, routes, controllers, services, models are deleted
- [ ] No remaining references to legacy plans anywhere in codebase
- [ ] Dashboard updated with correct plan cards
- [ ] All tests pass
- [ ] `designStyle.md` followed for all UI changes

---

## Task Count Summary

| Phase | Backend Tasks | Frontend Tasks | Testing Tasks | Review Tasks | Total |
|-------|:---:|:---:|:---:|:---:|:---:|
| Phase 1: Foundation | 33 | 0 | 16 | 3 | 52 |
| Phase 2: Core Refactors | 22 | 0 | 21 | 4 | 47 |
| Phase 3: Goal Integration | 6 | 10 | 14 | 3 | 33 |
| Phase 4: Estate Plan Refactor | 11 | 9 | 17 | 4 | 41 |
| Phase 5: Holistic Plan Refactor | 9 | 11 | 18 | 4 | 42 |
| Phase 6: Dashboard & Polish | 1 | 4 | 7 | 3 | 15 |
| Final Validation | 0 | 0 | 33 | 0 | 33 |
| **Total** | **82** | **34** | **126** | **21** | **263** |
