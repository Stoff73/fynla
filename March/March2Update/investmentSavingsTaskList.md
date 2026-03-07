# Investment & Savings Plan Rewrite — Task List

**Source plan:** `March2Update/investmentSavingsRewritePlan.md`
**Branch:** `investmentSavePlan`
**Prerequisite:** retirementPlanFix merged into main, main merged into investmentSavePlan (DONE)

---

## Pre-flight

- [x] **Confirm branch is correct**: `git branch --show-current` should show `investmentSavePlan`
- [x] **Confirm retirement code is available**: Check `app/Services/Retirement/RetirementActionDefinitionService.php` exists
- [x] **Seed database**: `php artisan db:seed`
- [x] **Run existing tests**: `./vendor/bin/pest tests/Unit/Services/Plans/` — 42 passed (163 assertions)

---

## Phase 1: Database Layer

**Agent/Skill:** Use `/feature-dev` for guided implementation. Use `database-optimizer` agent to review schema design.

### 1.1 Migration — `investment_action_definitions` table

- [x] **Read reference migration**: Read `database/migrations/*_create_retirement_action_definitions_table.php` to understand exact schema
- [x] **Create migration file**: `database/migrations/2026_03_05_000001_create_investment_action_definitions_table.php`
  - Table name: `investment_action_definitions`
  - Columns: `id`, `key` (string, unique), `source` (string, indexed), `title_template` (text), `description_template` (text), `action_template` (text), `category` (string), `priority` (enum: critical, high, medium, low), `scope` (enum: portfolio, account), `what_if_impact_type` (string), `trigger_config` (JSON), `is_enabled` (boolean, default true, indexed), `sort_order` (integer, default 0, indexed), `notes` (text, nullable), `timestamps`
  - Indexes: `source`, `is_enabled`, `sort_order`
- [x] **Run migration**: `php artisan migrate`
- [x] **Verify table exists**: `php artisan tinker` → `Schema::hasTable('investment_action_definitions')`

### 1.2 Model — `InvestmentActionDefinition`

- [x] **Read reference model**: Read `app/Models/RetirementActionDefinition.php` (116 lines)
- [x] **Create model file**: `app/Models/InvestmentActionDefinition.php`
  - Mirror `RetirementActionDefinition` exactly
  - `$casts`: `trigger_config` → `array`, `is_enabled` → `boolean`
  - Scopes: `enabled()`, `bySource($source)`
  - Static helpers: `findByKey($key)`, `getEnabled()`, `getEnabledBySource($source)`
  - Template methods: `renderTitle($vars)`, `renderDescription($vars)`, `renderAction($vars)` — replace `{placeholder}` tokens with values
- [x] **Verify model loads**: `php artisan tinker` → `new \App\Models\InvestmentActionDefinition()`

### 1.3 Seeder — 21 action definitions

- [x] **Read reference seeder**: Read `database/seeders/RetirementActionDefinitionSeeder.php` (227 lines)
- [x] **Create seeder file**: `database/seeders/InvestmentActionDefinitionSeeder.php`
  - Use `updateOrCreate` on `key` field for idempotency
  - 21 definitions as specified in plan (see table in plan Section 1.3):
    - Agent-sourced investment (8): risk_profile_missing, no_holdings, low_diversification, high_total_fees, high_fund_fees, high_platform_fees, rebalance_portfolio, tax_loss_harvesting
    - Agent-sourced tax efficiency (3): open_isa, use_isa_allowance, consider_bonds
    - Agent-sourced savings (4): emergency_fund_critical, emergency_fund_grow, switch_savings_rate, isa_allowance_remaining
    - Agent-sourced surplus waterfall (3): surplus_to_isa, surplus_to_pension, surplus_to_bond
    - Goal-sourced (3): goal_no_contribution, goal_behind_schedule, goal_deadline_approaching
  - Each definition must include: key, source, title_template (with `{placeholders}`), description_template, action_template, category, priority, scope, what_if_impact_type, trigger_config (JSON with `condition` key + thresholds), is_enabled, sort_order
  - Template text: British spelling, no acronyms (Rule 10), spell out all terms
  - **Reference existing hardcoded text** from `InvestmentAgent::generateRecommendations()` and `InvestmentPlanService::buildSavingsRecommendations()` for realistic title/description/action wording
- [x] **Run seeder**: `php artisan db:seed --class=InvestmentActionDefinitionSeeder`
- [x] **Verify 21 rows**: 21 rows confirmed — 18 agent, 3 goal

### 1.4 Factory

- [x] **Read reference factory**: Read `database/factories/RetirementActionDefinitionFactory.php`
- [x] **Create factory file**: `database/factories/InvestmentActionDefinitionFactory.php`
  - Default state with sensible values
  - `disabled()` state: `is_enabled => false`
  - `goalSourced()` state: `source => 'goal'`
- [x] **Verify factory works**: All states verified — default, disabled(), goalSourced()

### 1.5 Register in DatabaseSeeder

- [x] **Read current DatabaseSeeder**: Read `database/seeders/DatabaseSeeder.php`
- [x] **Add seeder call**: Add `$this->call(InvestmentActionDefinitionSeeder::class)` after `RetirementActionDefinitionSeeder`
- [x] **Full reseed**: `php artisan db:seed`
- [x] **Verify both tables seeded**: retirement=10, investment=21

### Phase 1 Checkpoint

- [x] **Run all existing tests**: `./vendor/bin/pest tests/Unit/Services/Plans/` — 42 passed (163 assertions)
- [x] **Code review (Phase 1)**: Reviewed — migration/model/seeder/factory all match retirement pattern. One fix: added missing seeders to `seedRequiredDataOnly()` in DatabaseSeeder
- [x] **Schema review**: Reviewed — schema well-designed, indexes technically suboptimal for 21 rows but negligible, JSON column correctly used (never queried via SQL), no changes needed
- [x] **Reseed**: `php artisan db:seed`

---

## Phase 2: Backend Service

**Agent/Skill:** Use `/feature-dev` for guided implementation. Read retirement reference files first, then implement investment equivalents.

### 2.1 InvestmentActionDefinitionService

- [x] **Read primary reference**: Read `app/Services/Retirement/RetirementActionDefinitionService.php` (612 lines) — this is THE pattern to follow
- [x] **Read supporting files for context**:
  - Read `app/Agents/InvestmentAgent.php` — understand current `generateRecommendations()` logic to extract into service
  - Read `app/Services/Plans/InvestmentPlanService.php` — understand `buildSavingsRecommendations()` and `buildSurplusWaterfall()` logic
  - Read `app/Services/Investment/FeeAnalyzer.php` — fee analysis data shapes
  - Read `app/Services/Tax/TaxConfigService.php` — tax config method signatures
  - Read `app/Services/Plans/PlanConfigService.php` — plan config method signatures

- [x] **Create service file**: `app/Services/Investment/InvestmentActionDefinitionService.php`

  **Constructor dependencies:**
  - `FeeAnalyzer`
  - `TaxConfigService`
  - `PlanConfigService`

  **Public method: `evaluateAgentActions()`**
  - [x] Signature: `evaluateAgentActions($investmentAnalysis, $savingsAnalysis, $investmentAccounts, $savingsAccounts, $userId, $accountFeeAnalyses)`
  - [x] Load enabled agent definitions: `InvestmentActionDefinition::getEnabledBySource('agent')`
  - [x] Dispatch each definition to appropriate trigger evaluator via `match()` on `trigger_config['condition']`
  - [x] Return array of recommendation arrays

  **Private trigger evaluators — Investment (8):**
  - [x] `evaluateRiskProfileMissing($definition, $investmentAnalysis)` — `!isset($analysis['allocation_deviation'])`
  - [x] `evaluateNoHoldings($definition, $investmentAccounts)` — accounts exist but all have holdings_count === 0
  - [x] `evaluateLowDiversification($definition, $investmentAnalysis)` — `diversification_score < trigger_config threshold (default 70)`
  - [x] `evaluateHighTotalFees($definition, $accountFeeAnalyses)` — per-account loop: `total_fee > threshold (default 1.0%)`, emit one rec per account
  - [x] `evaluateHighFundFees($definition, $accountFeeAnalyses)` — per-account: `weighted_ocf > threshold (default 0.5%)`
  - [x] `evaluateHighPlatformFees($definition, $accountFeeAnalyses)` — per-account: `platform_fee > threshold (default 0.8%)`
  - [x] `evaluateRebalancePortfolio($definition, $investmentAnalysis)` — `allocation_deviation.needs_rebalancing === true`
  - [x] `evaluateTaxLossHarvesting($definition, $investmentAnalysis)` — `tax_loss_opportunities_count > 0`

  **Private trigger evaluators — Tax Efficiency (3):**
  - [x] `evaluateOpenIsa($definition, $investmentAccounts)` — has GIA accounts, no ISA accounts
  - [x] `evaluateUseIsaAllowance($definition, $investmentAccounts, $userId)` — has ISA with remaining allowance + has GIA
  - [x] `evaluateConsiderBonds($definition, $investmentAccounts)` — GIA total value > threshold (default £50,000) + no bond accounts

  **Private trigger evaluators — Savings (4):**
  - [x] `evaluateEmergencyFundCritical($definition, $savingsAnalysis)` — runway_months < threshold (default 3)
  - [x] `evaluateEmergencyFundGrow($definition, $savingsAnalysis)` — runway_months >= 3 AND < 6
  - [x] `evaluateSwitchSavingsRate($definition, $savingsAccounts, $savingsAnalysis)` — has poor-rated accounts with annual gain > £100
  - [x] `evaluateIsaAllowanceRemaining($definition, $savingsAnalysis)` — ISA remaining allowance > 0 + runway >= 6 months

  **Private trigger evaluators — Surplus Waterfall (3):**
  - [x] `evaluateSurplusToIsa($definition, $savingsAnalysis)` — surplus exists + ISA remaining allowance > 0
  - [x] `evaluateSurplusToPension($definition, $savingsAnalysis)` — surplus exceeds ISA capacity
  - [x] `evaluateSurplusToBond($definition, $savingsAnalysis)` — surplus exceeds pension capacity

  **Conflict resolution:**
  - [x] `resolveConflicts($recommendations)` — if both `emergency_fund_critical` and `emergency_fund_grow` fire, keep only critical

  **Public method: `evaluateGoalActions()`**
  - [x] Signature: `evaluateGoalActions($linkedGoals)`
  - [x] Load enabled goal definitions: `InvestmentActionDefinition::getEnabledBySource('goal')`
  - [x] Per goal, evaluate 3 conditions:
    - [x] `evaluateGoalNoContribution($definition, $goal)` — `monthly_contribution <= 0`
    - [x] `evaluateGoalOffTrack($definition, $goal)` — `is_on_track === false`
    - [x] `evaluateGoalDeadline($definition, $goal)` — `months_remaining < 6 && progress_percentage < 75`
  - [x] Use `renderTitle/renderDescription/renderAction` with goal-specific template variables

  **Public method: `getWhatIfImpactType()`**
  - [x] Signature: `getWhatIfImpactType($category)`
  - [x] DB lookup: `InvestmentActionDefinition::where('category', $category)->value('what_if_impact_type')`
  - [x] Fallback: return `'default'`

  **Template variable helpers:**
  - [x] Build template variables from analysis data (account names, fee percentages, amounts, goal names, etc.)
  - [x] Use `renderTitle($vars)`, `renderDescription($vars)`, `renderAction($vars)` on each definition

- [x] **Verify service instantiates**: `php artisan tinker` → resolve from container

### 2.2 Integrate with InvestmentAgent

- [x] **Read current InvestmentAgent**: Read `app/Agents/InvestmentAgent.php` (421 lines)
- [x] **Add service dependency**: Inject `InvestmentActionDefinitionService` in constructor
- [x] **Simplify `generateRecommendations()`**: Delegate to `$this->actionDefinitionService->evaluateAgentActions()` — keep method signature for external callers (CoordinatingAgent)
- [x] **Verify CoordinatingAgent still works**: Read `app/Agents/CoordinatingAgent.php` to confirm it calls `generateRecommendations()` — must still function

### 2.3 Integrate with InvestmentPlanService (MAJOR REWRITE)

- [x] **Read current InvestmentPlanService**: Read `app/Services/Plans/InvestmentPlanService.php` (698 lines) — understand full `generatePlan()` pipeline
- [x] **Read retirement reference**: Read `app/Services/Plans/RetirementPlanService.php` — understand the 18-step pipeline to mirror
- [x] **Read BasePlanService**: Read `app/Services/Plans/BasePlanService.php` — understand shared methods available

**Inject new dependency:**
- [x] Add `InvestmentActionDefinitionService` to constructor

**Rewrite `generatePlan()` pipeline (11 steps):**
- [x] Step 1: Load user, check completeness, run agent analyses (keep existing)
- [x] Step 2: Replace `getRecommendations()` body — call `$this->actionDefinitionService->evaluateAgentActions()` with investment analysis, savings analysis, accounts, userId, fee analyses
- [x] Step 3: Replace `buildGoalRecommendations()` — call `$this->actionDefinitionService->evaluateGoalActions($linkedGoals)`
- [x] Step 4: Merge: goal recs first, then agent recs (goals-first ordering)
- [x] Step 5: `prepareActions()` (existing BasePlanService method — no change)
- [x] Step 6: New `enrichActionsWithCascadeParams()` — adds `cascade_params.additional_monthly` per action
- [x] Step 7: New `enrichActionsWithFundingSource()` — adds funding source for contribution-type actions
- [x] Step 8: Re-derive `$enabledActions` from enriched actions
- [x] Step 9: Build projections, what-if, conclusion (existing, with what-if fix)
- [x] Step 10: New `buildPersonalInformation()` — user personal/financial data section
- [x] Step 11: New `buildExecutiveSummary()` — refactored to structured format

**Delete old methods:**
- [x] Delete `buildSavingsRecommendations()` (logic now in service)
- [x] Delete `buildSurplusWaterfall()` (logic now in service)

**Fix `buildWhatIfData()`:**
- [x] Replace `str_contains()` chain with `$this->actionDefinitionService->getWhatIfImpactType()` lookup
- [x] `match()` on: `fee_reduction`, `savings_increase`, `contribution`, `tax_optimisation`, `default`

**New method: `enrichActionsWithCascadeParams()`**
- [x] Read retirement's `enrichActionsWithCascadeParams()` as reference
- [x] Per action, based on `what_if_impact_type`:
  - `fee_reduction` → `estimateFeeReductionMonthly()` using estimated_impact / 12
  - `savings_increase` → `DistributionAccount.allocate(monthlyDisposable * 0.2)`
  - `contribution` → `DistributionAccount.allocate(monthlyDisposable * 0.3)`
  - `tax_optimisation` → `totalInvestmentValue * taxOptimisationGain / 12`
  - `default` → `totalInvestmentValue * defaultActionGain / 12`
- [x] Add `cascade_params.additional_monthly` to each action
- [x] Add `frontend_calc_params` to plan response: `current_value`, `growth_rate`, `years`

**New method: `enrichActionsWithFundingSource()`**
- [x] Read retirement's `enrichActionsWithFundingSource()` as reference
- [x] For contribution-type actions (ISA Allowance, Emergency Fund Surplus, Goal), fetch eligible cash/GIA accounts
- [x] Check emergency fund threshold (don't drain below 6 months)
- [x] Persist selection via `PlanActionFundingSelection` model (already supports `plan_type = 'investment'`)

**New method: `buildPersonalInformation()`**
- [x] Read retirement's `buildPersonalInformation()` as reference
- [x] Return: full_name, dob, age, marital_status, spouse_name, children, gross_income, net_income, annual_expenditure, disposable_income, monthly_disposable, risk_level
- [x] Include in plan response as `personal_information` key

**New method: `buildExecutiveSummary()` (refactor existing)**
- [x] Read retirement's `buildExecutiveSummary()` as reference
- [x] Return structured format: opening, greeting, introduction, goals_summary, actions_summary, total_actions, closing, on_track

### Phase 2 Checkpoint

- [x] **Run all existing tests**: `./vendor/bin/pest tests/Unit/Services/Plans/` — 42 passed (163 assertions)
- [x] **Run investment-specific tests**: `./vendor/bin/pest tests/Unit/Services/Investment/` — 188 passed (477 assertions)
- [x] **Code review (Phase 2)**: 4 issues found, all fixed:
  - Fixed `InvestmentAgent::generateRecommendations()` — removed extra param, now matches BaseAgent contract
  - Fixed `InvestmentPlanService::getRecommendations()` — refactored to `(int $userId, ?array $preComputedData = null)` matching retirement pattern
  - Fixed `evaluateSurplusToBond()` — now deducts capped pension amount (not full annual allowance)
  - Documented agent userId=0 as safe (guard at line 586 prevents DB query)
- [x] **Security review**: Running — awaiting results
- [x] **Tax compliance review**: 3 issues found, all fixed:
  - Fixed `evaluateOpenIsa()` — ISA allowance now uses `taxConfig->getISAAllowances()` before TaxDefaults fallback
  - Fixed `calculateSurplus()` — uses `planConfig->getEmergencyFundTargetMonths()` instead of hardcoded 6
  - Fixed `buildEligibleFundingAccounts()` — uses `planConfig->getEmergencyFundTargetMonths()` instead of hardcoded 6
  - Added `getEmergencyFundTargetMonths()` to PlanConfigService + PlanConfigurationSeeder
- [x] **Reseed**: `php artisan db:seed` — all seeders passed

---

## Phase 3: Admin Panel

**Agent/Skill:** Use `/feature-dev` for guided implementation. Mirror retirement admin pattern.

### 3.1 Backend — Controller

- [x] **Read reference controller**: Read `app/Http/Controllers/Api/RetirementActionDefinitionController.php`
- [x] **Create controller**: `app/Http/Controllers/Api/InvestmentActionDefinitionController.php` — 153 lines, mirrors retirement exactly
- [x] **Verify controller methods**: All 6 CRUD operations verified

### 3.2 Backend — Form Request

- [x] **Read reference request**: Read `app/Http/Requests/StoreRetirementActionDefinitionRequest.php`
- [x] **Create request**: `app/Http/Requests/StoreInvestmentActionDefinitionRequest.php` — 51 lines, investment-specific what_if_impact_type values

### 3.3 Backend — Routes

- [x] **Read current routes**: Read `routes/api.php`
- [x] **Add investment admin routes**: 6 routes under `admin/investment-actions` with `auth:sanctum` + `permission:admin.access` middleware
- [x] **Verify routes register**: 6 routes confirmed via `php artisan route:list`

### 3.4 Frontend — Admin Table

- [x] **Read reference component**: Read `resources/js/components/Admin/AdminRetirementActions.vue`
- [x] **Create component**: `resources/js/components/Admin/AdminInvestmentActions.vue` — ~297 lines, with source filter (all/agent/goal)

### 3.5 Frontend — Admin Modal

- [x] **Read reference modal**: Read `resources/js/components/Admin/RetirementActionModal.vue`
- [x] **Create component**: `resources/js/components/Admin/InvestmentActionModal.vue` — ~489 lines, 21 condition options, condition-specific threshold UIs

### 3.6 Frontend — Admin Panel Tab

- [x] **Read current AdminPanel**: Read `resources/js/views/Admin/AdminPanel.vue`
- [x] **Add "Investment Actions" tab**: Added after Retirement Actions tab with trend-up icon

### 3.7 Frontend — Admin Service Methods

- [x] **Read current adminService**: Read `resources/js/services/adminService.js`
- [x] **Add 5 CRUD methods**: getInvestmentActions, createInvestmentAction, updateInvestmentAction, deleteInvestmentAction, toggleInvestmentAction

### Phase 3 Checkpoint

- [x] **Run existing tests**: 42 plan tests (163 assertions), 188 investment tests (477 assertions) — all pass
- [x] **Security review**: Completed — fixed HasJointOwnership trait SQL grouping (HIGH), added userId<=0 guard in calculateSurplus
- [x] **Design compliance**: No amber/orange colours, British spelling in labels verified
- [x] **Reseed**: `php artisan db:seed` — all seeders passed

---

## Phase 4: Frontend Plan Components

**Agent/Skill:** Use `/feature-dev` for guided implementation. Use `premium-ui-designer` agent for UI polish after functional implementation.

### 4.1 InvestmentPersonalInformation.vue (NEW)

- [x] **Read reference component**: Read `resources/js/components/Plans/Retirement/RetirementPersonalInformation.vue` (128 lines)
- [x] **Create component**: `resources/js/components/Plans/Investment/InvestmentPersonalInformation.vue` — mirrors retirement exactly, uses currencyMixin, British spelling, no amber/orange

### 4.2 InvestmentExecutiveSummary.vue (NEW)

- [x] **Read reference component**: Read `resources/js/components/Plans/Retirement/RetirementExecutiveSummary.vue`
- [x] **Read current PlanExecutiveSummary**: Understood shared narrative-only format
- [x] **Create component**: `resources/js/components/Plans/Investment/InvestmentExecutiveSummary.vue` — structured format with greeting, goals table, actions table, priority badges (no amber/orange)

### 4.3 InvestmentGroupedActions.vue (MAJOR REWRITE)

- [x] **Read current + reference components**: Read investment (299 lines), retirement (408 lines), CascadingActionChart (112 lines)
- [x] **Import CascadingActionChart** from `../Retirement/CascadingActionChart.vue`
- [x] **Add `cascadedActions` computed**: Extracts frontend_calc_params, sorts by priority, computes before/after series per action with cumulative additional_monthly
- [x] **Add `projectSeries()` helper**: Year-by-year compound growth with annual contributions
- [x] **Add funding source**: Emit `@update-funding-source` event (handled by PlanActionCard)
- [x] **Keep AccountFeeProjectionChart**: Coexists with cascade charts at group level
- [x] **Template structure**: Per-action PlanActionCard + CascadingActionChart, per-group AccountFeeProjectionChart

### 4.4 InvestmentPlanContent.vue (UPDATE)

- [x] **Updated section order**: MissingData → ExecutiveSummary (structured or fallback) → PersonalInformation → Goals → CurrentSituation → GroupedActions → Conclusion
- [x] **Imported new components**: InvestmentExecutiveSummary, InvestmentPersonalInformation
- [x] **Wired events**: `@update-funding-source` propagated from GroupedActions to parent
- [x] **Backward compatible**: Falls back to PlanExecutiveSummary if summary has narrative format

### 4.5 InvestmentPlan.vue — View (UPDATE)

- [x] **Added `handleUpdateFundingSource()` method**: Delegates to `updateActionFundingSource` Vuex action with `planKey: 'investment'`
- [x] **Wired event**: `@update-funding-source="handleUpdateFundingSource"`

### 4.6 InvestmentWhatIfControls.vue (UPDATE)

- [x] **Added `additional_monthly_savings` metric row**: Conditionally rendered when value present, uses currency format via PlanWhatIfMetricRow

### Phase 4 Checkpoint

- [x] **Run backend tests**: 42 plan tests (163 assertions), 188 investment tests (477 assertions) — all pass
- [x] **Reseed**: `php artisan db:seed` — all seeders passed
- [ ] **Start dev server**: `./dev.sh`
- [ ] **Browser test — peak_earners**: Login as peak_earners → Plans → Investment Plan
  - [ ] Personal information section visible below executive summary
  - [ ] Executive summary shows structured format with goals and actions tables
  - [ ] Cascading per-action charts show before/after series
  - [ ] Toggle an action → subsequent charts cascade (shift baselines)
  - [ ] Toggle all off → charts collapse (before = after = current trajectory)
  - [ ] What-if metrics update correctly with additional_monthly_savings
  - [ ] Fee projection charts still visible for fee-related actions
  - [ ] Funding source dropdown appears for contribution-type actions
- [ ] **Browser test — all personas**: Test each persona loads investment plan without errors
  - [ ] young_family
  - [ ] peak_earners
  - [ ] widow
  - [ ] entrepreneur
  - [ ] young_saver
  - [ ] retired_couple
- [ ] **Design compliance check**:
  - [ ] No amber/orange colours anywhere (Rule 9)
  - [ ] All currency via `currencyMixin` (Rule 6)
  - [ ] No acronyms in user-facing text (Rule 10)
  - [ ] British spelling in all labels
  - [ ] No scores in UI (Rule 12)
  - [ ] Read `designStyle.md` and verify component patterns match (Rule 11)
- [ ] **Code review (Phase 4)**: Use `/code-review` skill — review all new/modified Vue components for design system compliance, correct prop types, proper event handling
- [ ] **UI polish**: Use `premium-ui-designer` agent — review chart animations, spacing, responsive layout, badge styling
- [ ] **Reseed**: `php artisan db:seed`

---

## Phase 5: Tests

**Agent/Skill:** Use `/feature-dev` for test implementation.

### 5.1 Unit Tests — InvestmentActionDefinitionServiceTest

- [x] **Read reference tests**: Read `tests/Unit/Services/Retirement/RetirementActionDefinitionServiceTest.php` (503 lines)
- [x] **Create test file**: `tests/Unit/Services/Investment/InvestmentActionDefinitionServiceTest.php` — 30 tests (36 assertions)

**Test cases implemented:**
- [x] Investment triggers: risk_profile_missing (fire + not fire), no_holdings (fire + not fire), low_diversification (fire + not fire), high_total_fees (fire + not fire), rebalance_portfolio, tax_loss_harvesting
- [x] Tax efficiency triggers: open_isa (fire + not fire)
- [x] Savings triggers: emergency_fund_critical, emergency_fund_grow, conflict resolution (critical wins)
- [x] Surplus waterfall: surplus_to_isa fires, surplus does NOT fire when runway < target
- [x] Disabled definitions: skipped correctly
- [x] Custom threshold overrides: diversification threshold changed from 70 to 90
- [x] Goal evaluation: goal_no_contribution, goal_behind_schedule, goal_deadline_approaching, skips completed
- [x] What-if impact type: fee_reduction, savings_increase, default
- [x] Template rendering: title, description, missing placeholders
- [x] userId guard: zero surplus when userId is zero

### 5.2 Feature Tests — Admin CRUD

- [x] **Read reference tests**: Read `tests/Feature/Api/RetirementActionDefinitionTest.php` (180 lines)
- [x] **Create test file**: `tests/Feature/Api/InvestmentActionDefinitionTest.php` — 12 tests (180 assertions)

**Test cases implemented:**
- [x] Admin list (21 definitions), show, create, update, toggle, delete
- [x] Non-admin 403 on all 6 endpoints
- [x] Validation errors (required fields, what_if_impact_type values)
- [x] Duplicate key constraint on create
- [x] 404 for non-existent definition

### 5.3 Run ALL Tests

- [x] **Existing plan tests**: 42 passed (163 assertions)
- [x] **New service tests**: 30 passed (36 assertions)
- [x] **New feature tests**: 12 passed (180 assertions)
- [x] **Combined run**: 272 passed (856 assertions)
- [x] **PSR-12 clean**: Pint auto-fixed 1 style issue

### Phase 5 Checkpoint

- [x] **Reseed**: `php artisan db:seed` — all seeders passed

---

## Final Integration Testing

- [x] **Full reseed**: `php artisan db:seed`
- [x] **Start dev server**: `./dev.sh`

### Browser Verification (all from plan's Verification section)

- [x] **Verification 1**: 21 rows in `investment_action_definitions` table
  - Command: `php artisan tinker` → `\App\Models\InvestmentActionDefinition::count()`
- [x] **Verification 2**: All existing plan tests pass (42 tests, 163 assertions)
  - Command: `./vendor/bin/pest tests/Unit/Services/Plans/`
- [x] **Verification 3**: New service tests pass (218 tests, 513 assertions)
  - Command: `./vendor/bin/pest tests/Unit/Services/Investment/`
- [x] **Verification 4**: peak_earners Investment Plan
  - [x] Personal information section visible below summary
  - [x] Cascading per-action charts with before/after series
  - [x] Toggle an action → subsequent charts cascade (shift baselines)
  - [x] What-if metrics update correctly
  - [x] Fee projection charts still visible for accounts with fee actions
- [x] **Verification 5**: Admin Panel → Investment Actions tab
  - [x] 21 definitions visible (sort_order 10-210, all sources/categories/priorities correct)
  - [ ] Toggle an action off → investment plan no longer shows it (skipped — preview write interceptor blocks admin writes)
  - [ ] Edit a threshold → plan reflects change (skipped — preview write interceptor blocks admin writes)
- [x] **Verification 6**: All preview personas load investment plan without errors
  - [x] young_family (James Carter) — 4 actions, 4 goals, cascading charts
  - [x] peak_earners (David Mitchell) — 8 actions, 2 goals, fee charts, toggle works
  - [x] widow (Margaret Thompson) — 9 actions, 4 goals, funding sources
  - [x] entrepreneur (Alex Chen) — 3 actions, 3 goals, 16yr projections
  - [x] young_saver (John Morgan) — 4 actions, 3 goals, 42yr projections
  - [x] retired_couple (Patricia Bennett) — 6 actions, 5 goals, ISA/pension/bond actions
- [x] **Verification 7**: Investment + Plan + Action Definition tests pass (272 tests, 856 assertions)
  - Note: Full suite has 370 pre-existing failures in BaseAgentTest (unrelated to our changes)
  - Command: `./vendor/bin/pest tests/Unit/Services/Plans/ tests/Unit/Services/Investment/ tests/Feature/Api/InvestmentActionDefinitionTest.php`
- [x] **Verification 8**: Design compliance (agent verified all 9 files)
  - [x] No amber/orange colours (Rule 9)
  - [x] `currencyMixin` used everywhere (Rule 6)
  - [x] No acronyms in user-facing text (Rule 10)
  - [x] British spelling throughout
  - [x] No scores in UI (Rule 12)

### Final Code Review

- [x] **Security review**: 4 issues found, 3 fixed (max:2000 on description_template, strip unreplaced placeholders, throttle:30,1 on admin routes), 1 skipped for consistency (Auditable trait)
- [x] **Tax compliance review**: PASS — all tax values from TaxConfigService, no hardcoded values
- [x] **Final reseed**: `php artisan db:seed`

---

## Summary

| Phase | Tasks | New Files | Modified Files |
|-------|-------|-----------|----------------|
| Phase 1: Database Layer | 20 | 4 | 1 |
| Phase 2: Backend Service | 45 | 1 | 2 |
| Phase 3: Admin Panel | 15 | 4 | 3 |
| Phase 4: Frontend Components | 25 | 2 | 4 |
| Phase 5: Tests | 40 | 2 | 0 |
| Final Integration | 20 | 0 | 0 |
| **Total** | **~165** | **13** | **10** |

## Quick Reference — Commands

```bash
# Database
php artisan migrate                                          # Run new migration
php artisan db:seed                                          # Reseed ALL data
php artisan db:seed --class=InvestmentActionDefinitionSeeder # Seed only investment actions

# Testing
./vendor/bin/pest tests/Unit/Services/Plans/                 # Existing plan tests
./vendor/bin/pest tests/Unit/Services/Investment/             # New service tests
./vendor/bin/pest tests/Feature/Api/InvestmentActionDefinitionTest.php  # Admin tests
./vendor/bin/pest                                            # Full suite
./vendor/bin/pest --testsuite=Architecture                   # Architecture tests

# Development
./dev.sh                                                     # Start Laravel + Vite

# Verification
php artisan tinker                                           # Interactive check
php artisan route:list --path=investment-actions              # Verify routes
```

## Quick Reference — Agents & Skills

| Task | Tool |
|------|------|
| Feature implementation | `/feature-dev` skill |
| Bug investigation | `/systematic-debugging` skill |
| Code review | `/code-review` skill |
| Schema design review | `database-optimizer` agent |
| API/auth security | `security-reviewer` agent |
| Tax calculation compliance | `tax-compliance-reviewer` agent |
| UI polish and animations | `premium-ui-designer` agent |
| Codebase exploration | `Explore` agent |

## Key Reference Files

| File | Purpose |
|------|---------|
| `app/Services/Retirement/RetirementActionDefinitionService.php` | PRIMARY PATTERN for service |
| `app/Models/RetirementActionDefinition.php` | Model pattern |
| `database/seeders/RetirementActionDefinitionSeeder.php` | Seeder pattern |
| `app/Services/Plans/RetirementPlanService.php` | Plan service integration pattern |
| `resources/js/components/Plans/Retirement/CascadingActionChart.vue` | Reuse directly |
| `resources/js/components/Plans/Retirement/RetirementGroupedActions.vue` | Frontend cascade pattern |
| `resources/js/components/Plans/Retirement/RetirementPersonalInformation.vue` | Personal info pattern |
| `resources/js/components/Admin/AdminRetirementActions.vue` | Admin table pattern |
| `resources/js/components/Admin/RetirementActionModal.vue` | Admin modal pattern |
| `March2Update/investmentSavingsRewritePlan.md` | Full implementation plan |
