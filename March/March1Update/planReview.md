# Plans Section Review & Task List

**Generated:** 1 March 2026
**Source:** `plansDetail.md` (current state) + `updatePlans.md` (amendments)
**Purpose:** Consolidated review identifying all changes required, with implementation tasks

---

## Summary of Changes

| Area | Change Type | Impact |
|------|------------|--------|
| Hardcoded Constants (Section 2.10) | **Breaking Rule Violation** | All plans affected |
| Goal Integration into Plans | **New Feature + UX Prompt** | Investment, Savings, Retirement, Estate, Holistic |
| Disposable Income Distribution | **Fetch from Income Tab** | Investment & Savings, Retirement, Holistic |
| Emergency Fund Surplus Logic | **New Business Rule** | Investment & Savings Plan |
| Estate Plan Refactor | **Major Refactor** | Estate Plan, Estate Module |
| Holistic Plan Refactor | **Major Refactor** | Holistic Plan, all plan services |
| Score Mechanism Removal | **Rule Enforcement** | Estate Plan |
| Legacy Plans Removal | **Deletion** | Section 10 files, routes, controllers |
| Recommendation Wording | **UX Change** | Investment Plan |
| Projection Horizon Fix | **Bug Fix** | Investment & Savings, Goals |

---

## 1. Hardcoded Constants & Rates (CRITICAL - Rule Violation)

### Problem
Section 2.10 of `plansDetail.md` documents 16+ hardcoded values scattered across plan services. This **violates the application rule**: all values, rates, returns, risks must come from `TaxConfigService` or user data.

### Current Hardcoded Values to Eliminate

| Value | Current Location | Required Source |
|-------|-----------------|-----------------|
| `0.05` (5% growth) | `InvestmentPlanService`, `RetirementPlanService` | TaxConfigService or user risk profile |
| `0.04` (4% withdrawal/annuity) | `RetirementPlanService` | TaxConfigService |
| `0.25%` platform fee benchmark | `InvestmentPlanService` | TaxConfigService |
| `0.15%` OCF benchmark | `InvestmentPlanService` | TaxConfigService |
| `200` fee reduction / monthly contribution | `InvestmentPlanService`, `RetirementPlanService` | User disposable income (fetched from income tab) |
| `500` monthly savings / lump sum | `InvestmentPlanService`, `GoalPlanService` | User disposable income (fetched from income tab) |
| `2400` annual pension contribution | `RetirementPlanService` | Derived from user monthly figure |
| `2%` consolidation efficiency | `RetirementPlanService` | TaxConfigService |
| `3%` tax optimisation gain | `RetirementPlanService` | TaxConfigService |
| `1%` default other action gain | `RetirementPlanService` | TaxConfigService |
| `50` monthly goal contribution | `GoalPlanService` | User disposable income (fetched from income tab) |
| `25` default goal contribution | `GoalPlanService` | User disposable income (fetched from income tab) |
| `1000` lump sum scenario | `GoalPlanService` | User data (fetched from income tab) |
| `10` charitable giving threshold % | `EstateAgent` | TaxConfigService (already partially done) |
| `35` estate age gate | `EstatePlanService` | Admin-configurable via central config |
| `1800` cache TTL | `PlanController` | Central config file |
| `3600` retirement cache TTL | `RetirementAgent` | Central config file |

### Tasks

- **Task 1.1:** Create a `PlanConfigService` (or extend `TaxConfigService`) with all plan-related rates, benchmarks, and defaults. These must be stored in the `tax_configurations` table or a new `plan_configurations` table accessible to admin.
- **Task 1.2:** Add admin-configurable values for: default growth rate, withdrawal rate, fee benchmarks, efficiency gain percentages, estate age gate (currently 35), and cache TTLs.
- **Task 1.3:** Refactor `InvestmentPlanService::buildWhatIfData()` to replace hardcoded `0.05` growth rate and `500/month` savings with values from config service and user disposable income.
- **Task 1.4:** Refactor `RetirementPlanService::buildWhatIfData()` to replace hardcoded `0.05` growth, `0.04` annuity, `200/month` contribution, `2%`/`3%`/`1%` efficiency gains with config values.
- **Task 1.5:** Refactor `GoalPlanService::buildWhatIfData()` to replace `50`, `25`, `500`, `1000` hardcoded values with user affordability data.
- **Task 1.6:** Refactor `EstatePlanService::generatePlan()` to pull the age gate value (currently 35) from the config service instead of hardcoding.
- **Task 1.7:** Refactor `InvestmentPlanService::buildAccountGrowthProjections()` to use config-driven growth rate and fee benchmarks.
- **Task 1.8:** Refactor `RetirementPlanService::buildPensionGrowthProjections()` to use config-driven growth rate and `2400` derived from actual user contribution data.
- **Task 1.9:** Move all cache TTL values (`1800`, `3600`) to a central config file accessible to admin.

### Files Affected
- `app/Services/Plans/InvestmentPlanService.php` (growth rate, fee benchmarks, savings estimates)
- `app/Services/Plans/RetirementPlanService.php` (growth rate, annuity rate, contribution amounts, efficiency gains)
- `app/Services/Plans/GoalPlanService.php` (contribution amounts, lump sums)
- `app/Services/Plans/EstatePlanService.php` (age gate)
- `app/Http/Controllers/Api/Plans/PlanController.php` (cache TTL)
- `app/Agents/RetirementAgent.php` (cache TTL)
- `app/Agents/SavingsAgent.php` (cache TTL)
- New: `app/Services/Plans/PlanConfigService.php` or config table/seeder

---

## 2. Goal Integration into Plans

### Problem
Goals currently exist as standalone plans (`/plans/goal/:goalId`) but are not integrated into the module-level plans. Each goal needs an account allocated to it so it can appear in the appropriate plan. The user must be able to allocate an account themselves - the plan should prompt them with a message if no account is linked.

### Required Architecture

1. Each goal needs an account allocated to determine which plan it belongs to
2. **If a goal has no linked account**, the plan must display a clear message prompting the user to allocate an account to the goal (e.g., "This goal is not linked to an account. Please allocate a savings or investment account to include it in your plan.")
3. **The user allocates the account** - this is a user action, not automatic assignment
4. The account type determines which plan the goal appears in:
   - Savings account linked goal → Investment & Savings Plan
   - Investment account linked goal → Investment & Savings Plan
   - Retirement-type goal → Retirement Plan
   - Estate-related goal → Estate Plan
5. If a goal is associated with a plan, the goal becomes the **priority** with all other recommendations secondary

### Current State
- `Goal` model already has `linked_savings_account_id` and `linked_investment_account_id` fields
- `GoalPlanService` already handles per-goal plans
- Goals are NOT currently included in module plans (`InvestmentPlanService`, `RetirementPlanService`, `EstatePlanService`)
- `CoordinatingAgent` does NOT include `GoalsAgent`
- No UI prompt exists to tell users they need to link an account to a goal for plan integration

### Tasks

- **Task 2.1:** Add a user-facing message/prompt in plan views for goals that have no linked account. The message should guide the user to allocate an account to the goal so it can be included in the relevant plan. This should appear in the goal's current UI and/or when the plan detects unlinked goals.
- **Task 2.2:** Modify `InvestmentPlanService::generatePlan()` to query goals linked to investment/savings accounts for the user, include them in the plan data, and prioritise goal-related recommendations.
- **Task 2.3:** Modify `RetirementPlanService::generatePlan()` to query retirement-type goals and include them with priority.
- **Task 2.4:** Modify `EstatePlanService::generatePlan()` to query estate-related goals and include them with priority.
- **Task 2.5:** Update frontend plan content components to display associated goals and their progress within each plan view. Include a prompt message for any unlinked goals the user has.
- **Task 2.6:** Ensure goal recommendations appear FIRST (highest priority) in the actions list when a goal is associated with a plan, with all other recommendations secondary.

### Files Affected
- `app/Services/Plans/InvestmentPlanService.php`
- `app/Services/Plans/RetirementPlanService.php`
- `app/Services/Plans/EstatePlanService.php`
- `resources/js/components/Plans/Investment/InvestmentPlanContent.vue`
- `resources/js/components/Plans/Retirement/RetirementPlanContent.vue`
- `resources/js/components/Plans/Estate/EstatePlanContent.vue`
- Goal-related frontend components (for the "link an account" prompt)

---

## 3. Disposable Income Distribution Account

### Problem
Currently, hardcoded amounts (e.g., `200/month`, `500/month`) are used for recommendations. Instead, the user's actual disposable income must be used and allocated through a temporary distribution account so agents don't double-count or exceed affordability.

### Key Principle: Fetch, Don't Recalculate
The user's disposable income is **already calculated and available** on the user's income tab. Do NOT recalculate it. Fetch the existing value and use it directly.

### Required Architecture

1. **Fetch** the user's disposable income from the existing income data (already calculated on the income tab)
2. Create a temporary "distribution account" (in-memory, per plan generation) initialised with the fetched disposable income
3. Each agent draws from this account when making recommendations with monetary amounts
4. Once an amount is allocated to an account, the distribution balance reduces
5. This prevents double-counting and ensures recommendations don't exceed affordability
6. **Investment & Savings Plan and Retirement Plan each use the FULL distribution account** (these are separate plans)
7. **Holistic Plan shares ONE distribution account across ALL plans** - amounts must be prioritised and don't reset

### Current State
- The user's disposable income figure is already calculated and stored/available via the income tab
- `CashFlowCoordinator::calculateAvailableSurplus()` exists in the Holistic plan but recalculates unnecessarily
- Individual plan services do NOT use disposable income - they use hardcoded amounts
- No distribution/allocation tracking exists within individual plans

### Tasks

- **Task 3.1:** Identify where the user's disposable income is stored/fetched from the income tab and create a simple accessor method to retrieve it for plan services. No recalculation needed - just fetch the existing value.
- **Task 3.2:** Create a `DistributionAccount` class (in-memory allocation tracker) that is initialised with the fetched disposable income and allows agents to `allocate()` and check `remaining()`.
- **Task 3.3:** Integrate `DistributionAccount` into `InvestmentPlanService` - all monetary recommendation amounts must draw from this account using the real disposable income figure.
- **Task 3.4:** Integrate `DistributionAccount` into `RetirementPlanService` - same pattern, using the full disposable income (independent of investment plan).
- **Task 3.5:** Refactor `CoordinatingAgent::orchestrateAnalysis()` to use a shared `DistributionAccount` across all plans, with priority-based allocation (Emergency fund > Protection > Pension > Investment > Estate). Fetch the disposable income from the income tab, don't recalculate.
- **Task 3.6:** Update what-if calculations in all plan services to use allocated amounts rather than hardcoded amounts.

### Files Affected
- New: `app/Services/Plans/DistributionAccount.php` (in-memory allocation tracker only)
- `app/Services/Plans/InvestmentPlanService.php`
- `app/Services/Plans/RetirementPlanService.php`
- `app/Agents/CoordinatingAgent.php`
- `app/Services/Coordination/CashFlowCoordinator.php`

---

## 4. Emergency Fund Surplus Logic (Investment & Savings Plan)

### Problem
If the emergency fund exceeds 6 months of expenditure AND the surplus cash is not allocated to any goals to reduce over time, the plan must recommend moving excess cash into tax-efficient wrappers in priority order.

### Required Waterfall
1. ISA (if allowance available)
2. Pension (if annual allowance available)
3. Bond wrapper
4. Gifting

### Current State
- `InvestmentPlanService::buildSavingsRecommendations()` currently only:
  - Recommends building emergency fund if <3 or <6 months
  - Recommends better rates for poor-rated accounts
  - Recommends using ISA allowance if remaining >0 AND emergency >=6 months
- Does NOT check for excess emergency fund (>6 months)
- Does NOT check if cash is allocated to goals
- Does NOT recommend pension, bond, or gifting as surplus destinations

### Tasks

- **Task 4.1:** Add excess emergency fund detection in `InvestmentPlanService::buildSavingsRecommendations()`: if emergency fund >6 months AND excess cash not allocated to goals, generate waterfall recommendations.
- **Task 4.2:** Implement ISA → Pension → Bond → Gifting priority waterfall in the recommendation generation, checking allowance availability at each step.
- **Task 4.3:** Query `goals` table to check if any goals are set to reduce the emergency fund over time before generating surplus recommendations.

### Files Affected
- `app/Services/Plans/InvestmentPlanService.php` (`buildSavingsRecommendations()`)
- `app/Agents/SavingsAgent.php` (may need additional analysis data)

---

## 5. Projection Horizon Fix

### Problem
Projections currently default to 10 years when no other horizon is available. They should default to retirement date, not an arbitrary 10 years. Only use goal length if a goal is assigned.

### Current State
- `InvestmentPlanService::buildWhatIfData()`: `$yearsToRetirement ?? 10`
- `InvestmentPlanService::buildAccountGrowthProjections()`: uses goal target date, or retirement, or default 10 years
- `GoalPlanService`: uses goal-specific horizon

### Tasks

- **Task 5.1:** Remove the `?? 10` default from `InvestmentPlanService::buildWhatIfData()` and `buildAccountGrowthProjections()`. Use years to retirement as the fallback. If no retirement date exists, prompt user to set one (add to data completeness check).
- **Task 5.2:** Ensure goal-linked projections use goal target date when a goal is assigned, and retirement date otherwise.

### Files Affected
- `app/Services/Plans/InvestmentPlanService.php`

---

## 6. Recommendation Wording Changes (Investment Plan)

### Problem
Two specific wording issues in `InvestmentAgent::generateRecommendations()`:

1. When risk profile is missing, it says "Complete Your Risk Profile" - instead should ask for the specific information needed to calculate the risk profile
2. When no holdings exist, should state "Defaulting to risk-based fee-optimised allocations" rather than just "Add Your Holdings"

### Current State
- `InvestmentAgent::generateRecommendations()` line: No risk profile → "Complete Your Risk Profile" (priority 1)
- No holdings → "Add Your Holdings" (priority 1)

### Tasks

- **Task 6.1:** Change the "no risk profile" recommendation to ask for the specific missing information needed to calculate the risk profile (e.g., investment time horizon, capacity for loss, risk tolerance), not a generic "complete your risk profile".
- **Task 6.2:** Change the "no holdings" recommendation to state that the plan is defaulting to "Risk-based fee-optimised allocations" and explain what this means.

### Files Affected
- `app/Agents/InvestmentAgent.php` (`generateRecommendations()`)

---

## 7. Estate Plan Refactor (MAJOR)

### Problem
Multiple issues with the current Estate Plan:

1. **Duplicated work**: The Estate Plan recalculates everything instead of fetching from the Estate Module which already has a full table, strategies, recommendations, and calculations
2. **No joint view**: Married users with spouse data should see a joint estate view
3. **Insufficient detail**: Needs what-if scenarios, side-by-side comparisons, and actionable guidance
4. **No funding source shown**: Charitable/gifting recommendations don't show where money comes from
5. **Affordability not checked**: Life cover recommendations aren't checked against affordability
6. **Score mechanism present**: Estate health score (Section 6.6) violates the "No Scores in UI" rule

### Current State

#### Duplicated Calculations
- `EstatePlanService::generatePlan()` calls `$this->estateAgent->analyze($userId)` which runs:
  - `EstateAssetAggregatorService::gatherUserAssets()`
  - `IHTCalculationService::calculate()`
  - `GiftingStrategyOptimizer::calculateOptimalGiftingStrategy()`
  - `PersonalizedTrustStrategyService::generatePersonalizedTrustStrategy()`
  - `WillAnalysisService` methods
- All of this data is ALREADY available from the Estate Module's existing services and tables

#### Estate Health Score (Rule Violation)
- `EstateAgent::analyze()` calculates a health score (starts at 100, deducts points)
- This violates CLAUDE.md Rule 12: "No Scores in User-Facing UI"

#### No Joint View
- `EstatePlanService` only queries `User::with(['spouse'])` but doesn't build a joint estate view
- No side-by-side comparison of individual vs joint estate positions

### Tasks

- **Task 7.1:** Refactor `EstatePlanService::generatePlan()` to fetch estate data from the Estate Module's existing services/tables rather than recalculating. Use `EstateAgent::analyze()` results that are already cached, or query the existing `iht_calculations` table.
- **Task 7.2:** Add joint estate view when user is married and has spouse data or a linked account. Show side-by-side estate positions for both partners.
- **Task 7.3:** Add detailed "what to do" guidance for each recommendation - not just the strategy name but step-by-step actionable instructions.
- **Task 7.4:** Add what-if scenarios and side-by-side comparisons (currently only has basic toggle-based projection).
- **Task 7.5:** For charitable/gifting recommendations, show the funding source - which accounts the money would come from.
- **Task 7.6:** Check affordability of life cover recommendations against the user's disposable income (link to Task 3.1).
- **Task 7.7:** **Remove estate health score** from `EstateAgent::analyze()` and all references in frontend components. Replace with descriptive text and specific metrics per CLAUDE.md Rule 12.
- **Task 7.8:** Update `EstateCurrentSituation.vue` and `EstatePlanContent.vue` to support joint view display and enhanced detail.
- **Task 7.9:** Update `EstateGroupedActions.vue` to show funding sources and affordability indicators.

### Files Affected
- `app/Services/Plans/EstatePlanService.php` (major refactor)
- `app/Agents/EstateAgent.php` (remove score, reduce duplication)
- `resources/js/components/Plans/Estate/EstatePlanContent.vue`
- `resources/js/components/Plans/Estate/EstateCurrentSituation.vue`
- `resources/js/components/Plans/Estate/EstateGroupedActions.vue`
- `resources/js/components/Plans/Estate/EstateWhatIfControls.vue`

---

## 8. Holistic Plan Refactor (MAJOR)

### Problem
Multiple issues with the current Holistic Plan:

1. **Duplicates work**: Calls all 4 module agents independently instead of reading from existing plan data
2. **Missing modules**: Estate uses hardcoded placeholder values; Goals not included at all
3. **No goal integration**: Goals should now be included since they're part of plans
4. **Disposable income handling**: The shared distribution account must be prioritised across all recommendations (not reset per plan)

### Current State
- `CoordinatingAgent` injects 4 module agents but NOT `EstateAgent` or `GoalsAgent`
- Estate analysis is hardcoded: `{ net_worth: 350000, iht_liability: 10000, ... }`
- Each module agent is called with `analyze()` independently, duplicating work already done
- `CashFlowCoordinator` exists but only allocates across 4 modules

### Required Architecture
Instead of calling module agents directly, the Holistic Plan should:
1. Read from the individual plan outputs (Investment, Protection, Retirement, Estate, Goal plans)
2. This ensures consistency - recommendations match what users see in individual plans
3. Include Estate and Goals data (no more hardcoded placeholders)
4. Use a shared `DistributionAccount` for disposable income across all recommendations
5. Identify conflicts between plan recommendations
6. Rank all recommendations by priority
7. Produce an integrated action plan

### Tasks

- **Task 8.1:** Inject `EstatePlanService` and `GoalPlanService` (or `EstateAgent` and `GoalsAgent`) into `CoordinatingAgent`. Remove hardcoded estate placeholder data.
- **Task 8.2:** Refactor `CoordinatingAgent::collectModuleAnalysis()` to read from plan service outputs rather than calling agents directly. This prevents duplicated work and ensures consistency.
- **Task 8.3:** Include goals in the holistic plan - query active goals and their associated plan recommendations.
- **Task 8.4:** Implement shared `DistributionAccount` for the holistic plan where disposable income is allocated once and shared across all recommendations with prioritisation (not reset per module).
- **Task 8.5:** Update `HolisticPlanner::createHolisticPlan()` to include estate and goal data in the executive summary, financial snapshot, and risk assessment.
- **Task 8.6:** Update `CashFlowCoordinator::optimizeContributionAllocation()` to include estate and goal demands in the priority order.
- **Task 8.7:** Update the frontend `HolisticPlan.vue` and its child components to display estate and goal sections.
- **Task 8.8:** Update `HolisticPlanner::assessOverallRisk()` to include estate and goals in risk assessment (currently only 5 areas, needs to add goals).

### Files Affected
- `app/Agents/CoordinatingAgent.php` (major refactor)
- `app/Services/Coordination/HolisticPlanner.php`
- `app/Services/Coordination/CashFlowCoordinator.php`
- `app/Services/Coordination/PriorityRanker.php`
- `app/Services/Coordination/ConflictResolver.php`
- `resources/js/views/HolisticPlan.vue`
- `resources/js/components/Holistic/` (all child components)
- `resources/js/store/modules/holistic.js`

---

## 9. Remove Legacy Plans (Section 10) - COMPLETE DELETION

### Problem
Legacy plans (v1) are no longer needed. All references, code, files, routes, controllers, services, and models must be removed.

### Files to Delete

#### Backend Controllers
- `app/Http/Controllers/Api/Plans/InvestmentSavingsPlanController.php`
- `app/Http/Controllers/Api/Investment/InvestmentPlanController.php`
- `app/Http/Controllers/Api/Investment/InvestmentRecommendationController.php`

#### Backend Services
- `app/Services/Plans/InvestmentSavingsPlanService.php`
- `app/Services/Investment/InvestmentPlanGenerator.php`

#### Backend Models
- `app/Models/Investment/InvestmentPlan.php`
- `app/Models/Investment/InvestmentRecommendation.php`

#### Frontend Views
- `resources/js/views/Plans/InvestmentSavingsPlan.vue`
- `resources/js/views/Protection/ComprehensiveProtectionPlan.vue`
- `resources/js/views/Estate/ComprehensiveEstatePlan.vue`

#### Frontend Components
- `resources/js/components/Plans/InvestmentSavingsPlanView.vue`

### Routes to Remove

#### Backend API Routes (`routes/api.php`)
- `GET /api/plans/investment-savings`
- `DELETE /api/plans/investment-savings/clear-cache`
- `POST /api/investment/plan/generate`
- `GET /api/investment/plan`
- `GET /api/investment/plan/all`
- `GET /api/investment/plan/{id}`
- `DELETE /api/investment/plan/{id}`
- `GET /api/protection/comprehensive-plan`
- `GET /api/estate/comprehensive-plan`

#### Frontend Routes (`resources/js/router/index.js`)
- `/plans/investment-savings` (redirect)
- `/protection-plan`
- `/estate-plan`

### Other Cleanup

- `resources/js/services/plansService.js`: Remove `generateInvestmentSavingsPlan()` and `clearInvestmentSavingsPlanCache()` methods
- `resources/js/store/modules/plans.js`: Remove any legacy plan references
- Check for any imports/references to deleted files across the codebase

### Tasks

- **Task 9.1:** Delete all legacy backend files (controllers, services, models) listed above.
- **Task 9.2:** Remove all legacy API routes from `routes/api.php`.
- **Task 9.3:** Delete all legacy frontend files (views, components) listed above.
- **Task 9.4:** Remove legacy frontend routes from `resources/js/router/index.js`.
- **Task 9.5:** Clean up `plansService.js` - remove legacy methods.
- **Task 9.6:** Search entire codebase for any remaining references to deleted files/routes and remove them.
- **Task 9.7:** Consider whether to drop `investment_plans` and `investment_recommendations` database tables (or leave for data preservation - confirm with user).

### Database Tables Potentially Affected
- `investment_plans` - legacy persisted plans
- `investment_recommendations` - legacy persisted recommendations

---

## 10. Plans Dashboard Updates

### Required Changes
With legacy plans removed and goals integrated into module plans, the dashboard needs updating:

### Tasks

- **Task 10.1:** Remove any legacy plan links from the dashboard.
- **Task 10.2:** Add Holistic Plan card to the dashboard (currently not shown on `/plans`).
- **Task 10.3:** Update goal plan cards to indicate which module plan they're associated with.
- **Task 10.4:** Include Estate Plan in the `statuses` endpoint (currently only Investment, Protection, Retirement, Estate are checked - verify goals are represented).

### Files Affected
- `resources/js/views/Plans/PlansDashboard.vue`
- `app/Http/Controllers/Api/Plans/PlanController.php` (`statuses()` method)

---

## Implementation Priority Order

### Phase 1: Foundation (must be done first)
1. **Task 1.1-1.2**: Create `PlanConfigService` with admin-configurable values
2. **Task 3.1-3.2**: Identify disposable income source from income tab and create `DistributionAccount`
3. **Task 9.1-9.7**: Remove all legacy plans (clean slate)

### Phase 2: Core Refactors
4. **Task 1.3-1.9**: Refactor all plan services to use config service (depends on Phase 1)
5. **Task 3.3-3.4**: Integrate disposable income into Investment & Retirement plans
6. **Task 5.1-5.2**: Fix projection horizons
7. **Task 4.1-4.3**: Emergency fund surplus waterfall logic
8. **Task 6.1-6.2**: Recommendation wording changes

### Phase 3: Goal Integration
9. **Task 2.1-2.6**: Integrate goals into module plans

### Phase 4: Estate Plan Refactor
10. **Task 7.1-7.9**: Estate plan refactor (fetch from module, joint view, scores removal, detail)

### Phase 5: Holistic Plan Refactor
11. **Task 8.1-8.8**: Holistic plan refactor (read from plans, include estate + goals, shared distribution)

### Phase 6: Dashboard & Polish
12. **Task 10.1-10.4**: Dashboard updates

---

## Validation Checklist

After implementation, verify:

- [ ] No hardcoded rates, growth percentages, or monetary amounts remain in plan services
- [ ] All plan config values are admin-accessible via central config
- [ ] Emergency fund surplus >6 months triggers ISA→Pension→Bond→Gifting waterfall
- [ ] Disposable income is fetched from the income tab for all monetary recommendations (not hardcoded or recalculated)
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
- [ ] All tests pass after changes
- [ ] `designStyle.md` followed for all UI changes
- [ ] No amber/orange colours used
- [ ] No acronyms in user-facing text
- [ ] British spelling in user-facing text

---

## Complete File Impact Summary

### Files to CREATE
| File | Purpose |
|------|---------|
| `app/Services/Plans/PlanConfigService.php` | Centralised plan configuration |
| `app/Services/Plans/DistributionAccount.php` | In-memory disposable income allocation tracker (fetches disposable income from income tab) |
| Database migration for `plan_configurations` table | Admin-configurable plan values |
| Database seeder for plan config defaults | Seed default values |

### Files to MODIFY (Backend - 15+)
| File | Changes |
|------|---------|
| `app/Services/Plans/InvestmentPlanService.php` | Config values, fetch disposable income from income tab, goal integration, projection horizon, emergency fund surplus |
| `app/Services/Plans/RetirementPlanService.php` | Config values, fetch disposable income from income tab, goal integration |
| `app/Services/Plans/EstatePlanService.php` | Fetch from module, joint view, age gate from config, goal integration, affordability |
| `app/Services/Plans/GoalPlanService.php` | Config values, user affordability |
| `app/Services/Plans/BasePlanService.php` | Possible updates for goal priority logic |
| `app/Agents/InvestmentAgent.php` | Recommendation wording |
| `app/Agents/EstateAgent.php` | Remove health score, reduce duplication |
| `app/Agents/CoordinatingAgent.php` | Read from plans, include estate + goals, shared distribution |
| `app/Services/Coordination/HolisticPlanner.php` | Include estate + goals |
| `app/Services/Coordination/CashFlowCoordinator.php` | Include estate + goals in allocation |
| `app/Services/Coordination/PriorityRanker.php` | Include estate + goals |
| `app/Services/Coordination/ConflictResolver.php` | Include estate + goals |
| `app/Http/Controllers/Api/Plans/PlanController.php` | Config-driven cache TTL, dashboard statuses |
| `routes/api.php` | Remove legacy routes |

### Files to MODIFY (Frontend - 10+)
| File | Changes |
|------|---------|
| `resources/js/views/Plans/PlansDashboard.vue` | Remove legacy, add holistic card |
| `resources/js/components/Plans/Investment/InvestmentPlanContent.vue` | Goal display |
| `resources/js/components/Plans/Retirement/RetirementPlanContent.vue` | Goal display |
| `resources/js/components/Plans/Estate/EstatePlanContent.vue` | Joint view, enhanced detail |
| `resources/js/components/Plans/Estate/EstateCurrentSituation.vue` | Joint view |
| `resources/js/components/Plans/Estate/EstateGroupedActions.vue` | Funding sources, affordability |
| `resources/js/views/HolisticPlan.vue` | Estate + goals sections |
| `resources/js/components/Holistic/*` | Estate + goals display |
| `resources/js/services/plansService.js` | Remove legacy methods |
| `resources/js/store/modules/holistic.js` | Estate + goals state |
| `resources/js/router/index.js` | Remove legacy routes |

### Files to DELETE (11 files)
| File | Reason |
|------|--------|
| `app/Http/Controllers/Api/Plans/InvestmentSavingsPlanController.php` | Legacy |
| `app/Http/Controllers/Api/Investment/InvestmentPlanController.php` | Legacy |
| `app/Http/Controllers/Api/Investment/InvestmentRecommendationController.php` | Legacy |
| `app/Services/Plans/InvestmentSavingsPlanService.php` | Legacy |
| `app/Services/Investment/InvestmentPlanGenerator.php` | Legacy |
| `app/Models/Investment/InvestmentPlan.php` | Legacy |
| `app/Models/Investment/InvestmentRecommendation.php` | Legacy |
| `resources/js/views/Plans/InvestmentSavingsPlan.vue` | Legacy |
| `resources/js/views/Protection/ComprehensiveProtectionPlan.vue` | Legacy |
| `resources/js/views/Estate/ComprehensiveEstatePlan.vue` | Legacy |
| `resources/js/components/Plans/InvestmentSavingsPlanView.vue` | Legacy |
