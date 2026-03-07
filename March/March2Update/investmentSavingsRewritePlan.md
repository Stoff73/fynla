# Investment & Savings Plan Rewrite — DB-Driven Action System

## Context

The Investment & Savings plan currently uses hardcoded PHP logic across `InvestmentAgent::generateRecommendations()` (10+ rec types), `InvestmentPlanService::buildSavingsRecommendations()`, and `InvestmentPlanService::buildSurplusWaterfall()` to generate recommendations. All thresholds (fee > 1%, diversification < 70, emergency fund < 3 months, etc.) are embedded in code.

The retirement plan (on `retirementPlanFix` branch, to be merged first) has been refactored to a DB-driven architecture: `RetirementActionDefinitionService` evaluates triggers from `retirement_action_definitions` table, supports admin CRUD, cascading per-action charts, funding source selection, and a personal information section.

**Goal:** Rewrite the investment plan to match the retirement plan's architecture exactly, achieving full feature parity.

---

## Phase 1: Database Layer (5 files)

### 1.1 Migration — `investment_action_definitions` table

**Create:** `database/migrations/2026_03_05_000001_create_investment_action_definitions_table.php`

Same schema as `retirement_action_definitions`: key, source, title_template, description_template, action_template, category, priority (enum), scope (enum), what_if_impact_type, trigger_config (JSON), is_enabled, sort_order, notes, timestamps. Indexes on source, is_enabled, sort_order.

### 1.2 Model — `InvestmentActionDefinition`

**Create:** `app/Models/InvestmentActionDefinition.php`

Mirror of `RetirementActionDefinition` — same casts, scopes (`enabled`, `bySource`), static helpers (`findByKey`, `getEnabled`, `getEnabledBySource`), template rendering (`renderTitle`, `renderDescription`, `renderAction`).

### 1.3 Seeder — 21 action definitions

**Create:** `database/seeders/InvestmentActionDefinitionSeeder.php`

| # | Key | Source | Category | Priority | Scope | what_if_impact_type | Trigger |
|---|-----|--------|----------|----------|-------|---------------------|---------|
| 1 | `risk_profile_missing` | agent | Risk Profile | high | portfolio | default | `risk_profile_not_set` |
| 2 | `no_holdings` | agent | Portfolio Setup | medium | portfolio | default | `accounts_exist_but_no_holdings` |
| 3 | `low_diversification` | agent | Diversification | medium | portfolio | default | `diversification_score_below` (threshold: 70) |
| 4 | `high_total_fees` | agent | Fees | high | account | fee_reduction | `total_fee_percent_above` (threshold: 1.0) |
| 5 | `high_fund_fees` | agent | High Fees | medium | account | fee_reduction | `weighted_ocf_above` (threshold: 0.5) |
| 6 | `high_platform_fees` | agent | Platform Fees | medium | account | fee_reduction | `platform_fee_percent_above` (threshold: 0.8) |
| 7 | `rebalance_portfolio` | agent | Asset Allocation | medium | portfolio | default | `allocation_needs_rebalancing` |
| 8 | `tax_loss_harvesting` | agent | Tax Planning | medium | portfolio | tax_optimisation | `has_harvesting_opportunities` |
| 9 | `open_isa` | agent | Tax Efficiency | high | portfolio | tax_optimisation | `has_gia_no_isa` |
| 10 | `use_isa_allowance` | agent | Tax Efficiency | medium | portfolio | tax_optimisation | `has_isa_remaining_and_gia` |
| 11 | `consider_bonds` | agent | Tax Efficiency | low | portfolio | tax_optimisation | `gia_value_above_and_no_bonds` (threshold: 50000) |
| 12 | `emergency_fund_critical` | agent | Emergency Fund | critical | portfolio | savings_increase | `emergency_runway_below` (threshold: 3) |
| 13 | `emergency_fund_grow` | agent | Emergency Fund | high | portfolio | savings_increase | `emergency_runway_between` (low: 3, high: 6) |
| 14 | `switch_savings_rate` | agent | Interest Rate | medium | portfolio | savings_increase | `has_poor_rate_accounts` |
| 15 | `isa_allowance_remaining` | agent | ISA Allowance | medium | portfolio | contribution | `isa_remaining_and_runway_above` (threshold: 6) |
| 16 | `surplus_to_isa` | agent | Emergency Fund Surplus | medium | portfolio | contribution | `surplus_exists_and_isa_remaining` |
| 17 | `surplus_to_pension` | agent | Emergency Fund Surplus | low | portfolio | contribution | `surplus_exceeds_isa` |
| 18 | `surplus_to_bond` | agent | Emergency Fund Surplus | low | portfolio | default | `surplus_exceeds_pension` |
| 19 | `goal_no_contribution` | goal | Goal | high | portfolio | contribution | `linked_goal_no_monthly_contribution` |
| 20 | `goal_behind_schedule` | goal | Goal | high | portfolio | contribution | `linked_goal_off_track` |
| 21 | `goal_deadline_approaching` | goal | Goal | medium | portfolio | contribution | `goal_months_remaining_below_and_progress_below` (months: 6, progress: 75) |

Uses `updateOrCreate` on `key` for idempotency.

### 1.4 Factory

**Create:** `database/factories/InvestmentActionDefinitionFactory.php`

States: `disabled()`, `goalSourced()`. Same pattern as retirement factory.

### 1.5 Register in DatabaseSeeder

**Modify:** `database/seeders/DatabaseSeeder.php` — add `InvestmentActionDefinitionSeeder` after `RetirementActionDefinitionSeeder`.

---

## Phase 2: Backend Service

### 2.1 InvestmentActionDefinitionService

**Create:** `app/Services/Investment/InvestmentActionDefinitionService.php`

Follows `RetirementActionDefinitionService` (612 lines on retirementPlanFix) exactly.

**Constructor:** `FeeAnalyzer`, `TaxConfigService`, `PlanConfigService`

**Public methods:**

| Method | Purpose |
|--------|---------|
| `evaluateAgentActions($investmentAnalysis, $savingsAnalysis, $investmentAccounts, $savingsAccounts, $userId, $accountFeeAnalyses)` | Load enabled agent definitions, dispatch to trigger evaluators, return recommendations |
| `evaluateGoalActions($linkedGoals)` | Load enabled goal definitions, evaluate per goal, return recommendations |
| `getWhatIfImpactType($category)` | DB lookup replacing str_contains chain |

**Private trigger evaluators (18 methods, one per condition):**

Investment triggers:
- `evaluateRiskProfileMissing` — `!isset($analysis['allocation_deviation'])`
- `evaluateNoHoldings` — accounts exist but holdings_count === 0
- `evaluateLowDiversification` — score < threshold (default 70)
- `evaluateHighTotalFees` — per-account: total_fee > threshold (default 1.0%)
- `evaluateHighFundFees` — per-account: OCF > threshold (default 0.5%)
- `evaluateHighPlatformFees` — per-account: platform > threshold (default 0.8%)
- `evaluateRebalancePortfolio` — allocation_deviation.needs_rebalancing
- `evaluateTaxLossHarvesting` — opportunities_count > 0

Tax efficiency triggers:
- `evaluateOpenIsa` — has GIA, no ISA
- `evaluateUseIsaAllowance` — has ISA with remaining + has GIA
- `evaluateConsiderBonds` — GIA > threshold + no bonds

Savings triggers:
- `evaluateEmergencyFundCritical` — runway < 3 months
- `evaluateEmergencyFundGrow` — runway 3-6 months
- `evaluateSwitchSavingsRate` — poor-rated accounts with gain > £100
- `evaluateIsaAllowanceRemaining` — ISA remaining + runway >= 6

Surplus waterfall triggers:
- `evaluateSurplusToIsa` — surplus exists + ISA remaining
- `evaluateSurplusToPension` — surplus > ISA capacity
- `evaluateSurplusToBond` — surplus > pension capacity

Goal triggers (same as retirement):
- `evaluateGoalNoContribution` — monthly_contribution <= 0
- `evaluateGoalOffTrack` — is_on_track === false
- `evaluateGoalDeadline` — months_remaining < 6 && progress < 75%

**Conflict resolution:** If both `emergency_fund_critical` and `emergency_fund_grow` fire, keep only critical.

### 2.2 Integrate with InvestmentAgent

**Modify:** `app/Agents/InvestmentAgent.php`

- Add `InvestmentActionDefinitionService` to constructor
- Simplify `generateRecommendations()` to delegate to service (keeps method for external callers like CoordinatingAgent)

### 2.3 Integrate with InvestmentPlanService

**Modify:** `app/Services/Plans/InvestmentPlanService.php` (major changes)

Changes to `generatePlan()` pipeline (matching retirement's 18-step flow):

1. Load user, check completeness, run agent analyses (unchanged)
2. **Replace** `getRecommendations()` body — call `actionDefinitionService->evaluateAgentActions()` directly
3. **Replace** `buildGoalRecommendations()` call — use `actionDefinitionService->evaluateGoalActions()`
4. Merge goal recs first, then agent recs (goals-first ordering)
5. `prepareActions()` (existing BasePlanService method)
6. **New:** `enrichActionsWithCascadeParams()` — adds `cascade_params.additional_monthly` per action
7. **New:** `enrichActionsWithFundingSource()` — adds funding source for contribution actions
8. Re-derive enabledActions after enrichment
9. Build projections, what-if, conclusion (existing, with what-if fix)
10. **New:** `buildPersonalInformation()` — user personal/financial data section
11. **New:** `buildExecutiveSummary()` refactored to structured format (opening, greeting, goals_summary, actions_summary, closing)

**Delete these methods** (logic moves to service):
- `buildSavingsRecommendations()`
- `buildSurplusWaterfall()`

**Fix `buildWhatIfData()`:**
Replace `str_contains` chain with `actionDefinitionService->getWhatIfImpactType()` → match on `fee_reduction`, `savings_increase`, `contribution`, `tax_optimisation`, `default`.

**New `enrichActionsWithCascadeParams()`:**
- `fee_reduction` → `(currentFee - benchmarkFee) / 100 * accountValue / 12`
- `savings_increase` → `DistributionAccount.allocate(monthlyDisposable * 0.2)`
- `contribution` → `DistributionAccount.allocate(monthlyDisposable * 0.3)`
- `tax_optimisation` → `totalInvestmentValue * taxOptimisationGain / 12`
- `default` → `totalInvestmentValue * defaultActionGain / 12`

**New `enrichActionsWithFundingSource()`:**
Same pattern as retirement — for contribution-category actions (ISA Allowance, Emergency Fund Surplus, Goal), fetch eligible cash/GIA accounts, check emergency threshold, persist via `PlanActionFundingSelection`.

**New `buildPersonalInformation()`:**
Copy retirement's method — returns full_name, dob, age, marital_status, spouse, children, income, risk_level.

---

## Phase 3: Admin Panel

### 3.1 Backend

**Create:** `app/Http/Controllers/Api/InvestmentActionDefinitionController.php` — CRUD mirror of retirement controller
**Create:** `app/Http/Requests/StoreInvestmentActionDefinitionRequest.php` — validation with expanded what_if_impact_type values
**Modify:** `routes/api.php` — add admin/investment-actions routes

### 3.2 Frontend

**Create:** `resources/js/components/Admin/AdminInvestmentActions.vue` — table with CRUD, mirrors `AdminRetirementActions.vue`
**Create:** `resources/js/components/Admin/InvestmentActionModal.vue` — create/edit modal with expanded what_if_impact_type dropdown
**Modify:** `resources/js/views/AdminPanel.vue` — add "Investment Actions" tab
**Modify:** `resources/js/services/adminService.js` — add 5 CRUD methods

---

## Phase 4: Frontend Plan Components

### 4.1 New: InvestmentPersonalInformation.vue

**Create:** `resources/js/components/Plans/Investment/InvestmentPersonalInformation.vue`

Copy of `RetirementPersonalInformation.vue` — two-column grid with Personal Details, Family, Financial Overview, Risk Profile.

### 4.2 New: InvestmentExecutiveSummary.vue

**Create:** `resources/js/components/Plans/Investment/InvestmentExecutiveSummary.vue`

Structured format matching retirement: opening, greeting, introduction, goals_summary table, actions_summary table, total_actions, closing, on_track.

### 4.3 Rewrite: InvestmentGroupedActions.vue

**Modify:** `resources/js/components/Plans/Investment/InvestmentGroupedActions.vue`

Major changes:
- Import `CascadingActionChart` from `Plans/Retirement/CascadingActionChart.vue` (reuse — it's generic)
- Add `cascadedActions` computed (same logic as retirement):
  - Use `frontend_calc_params.current_value`, `growth_rate`, `years`
  - Each action's `cascade_params.additional_monthly` drives after series
  - Cumulative cascading: action N's "before" = action N-1's "after"
- Add funding source display + `@update-funding-source` emit
- Keep `AccountFeeProjectionChart` for per-account fee charts (coexists with cascade charts)
- Layout: per-action cascade chart below each PlanActionCard, then per-account fee chart at group level

### 4.4 Update: InvestmentPlanContent.vue

**Modify:** `resources/js/components/Plans/Investment/InvestmentPlanContent.vue`

New section order:
1. PlanMissingDataPrompt
2. InvestmentExecutiveSummary (was PlanExecutiveSummary)
3. **InvestmentPersonalInformation** (NEW)
4. PlanGoalSection
5. InvestmentCurrentSituation
6. InvestmentGroupedActions (MODIFIED — cascading + funding)
7. PlanConclusion

### 4.5 Update: InvestmentPlan.vue (view)

**Modify:** `resources/js/views/Plans/InvestmentPlan.vue`

Add `handleUpdateFundingSource()` method — calls funding source API, clears cache, re-fetches plan (same pattern as retirement view).

### 4.6 Update: InvestmentWhatIfControls.vue

**Modify:** `resources/js/components/Plans/Investment/InvestmentWhatIfControls.vue`

Add `additional_monthly_savings` metric row to projected scenario (matches retirement's `additional_monthly_contribution`).

---

## Phase 5: Tests

### 5.1 Unit Tests

**Create:** `tests/Unit/Services/Investment/InvestmentActionDefinitionServiceTest.php`
- Test each trigger evaluator (18 conditions)
- Test threshold values from DB
- Test disabled definitions skipped
- Test goal evaluation (3 conditions)
- Test what-if impact type lookup
- Test template rendering
- Test conflict resolution (emergency fund critical vs grow)

### 5.2 Feature Tests

**Create:** `tests/Feature/Api/InvestmentActionDefinitionTest.php`
- Admin CRUD endpoints
- Non-admin 403 rejection
- Validation errors

### 5.3 Existing tests

Run `./vendor/bin/pest tests/Unit/Services/Plans/` — all existing tests must still pass (seed new table in beforeEach).

---

## Files Summary

### Create (12 files)

| File | Purpose |
|------|---------|
| `database/migrations/2026_03_05_000001_create_investment_action_definitions_table.php` | Table schema |
| `database/seeders/InvestmentActionDefinitionSeeder.php` | Seed 21 actions |
| `database/factories/InvestmentActionDefinitionFactory.php` | Test factory |
| `app/Models/InvestmentActionDefinition.php` | Eloquent model with template rendering |
| `app/Services/Investment/InvestmentActionDefinitionService.php` | Core trigger evaluation service |
| `app/Http/Controllers/Api/InvestmentActionDefinitionController.php` | Admin CRUD |
| `app/Http/Requests/StoreInvestmentActionDefinitionRequest.php` | Validation |
| `resources/js/components/Admin/AdminInvestmentActions.vue` | Admin table |
| `resources/js/components/Admin/InvestmentActionModal.vue` | Admin modal |
| `resources/js/components/Plans/Investment/InvestmentPersonalInformation.vue` | Personal info section |
| `resources/js/components/Plans/Investment/InvestmentExecutiveSummary.vue` | Structured executive summary |
| `tests/Unit/Services/Investment/InvestmentActionDefinitionServiceTest.php` | Service unit tests |

### Modify (11 files)

| File | Change |
|------|--------|
| `database/seeders/DatabaseSeeder.php` | Add InvestmentActionDefinitionSeeder |
| `app/Agents/InvestmentAgent.php` | Inject service, delegate generateRecommendations |
| `app/Services/Plans/InvestmentPlanService.php` | Major rewrite: cascade params, funding source, personal info, what-if fix, delegate to service |
| `routes/api.php` | Add admin/investment-actions routes |
| `resources/js/components/Plans/Investment/InvestmentGroupedActions.vue` | Major rewrite: cascading charts, funding sources |
| `resources/js/components/Plans/Investment/InvestmentPlanContent.vue` | Add personal info + executive summary, wire funding events |
| `resources/js/views/Plans/InvestmentPlan.vue` | Add funding source handler |
| `resources/js/components/Plans/Investment/InvestmentWhatIfControls.vue` | Add savings metric |
| `resources/js/views/AdminPanel.vue` | Add Investment Actions tab |
| `resources/js/services/adminService.js` | Add 5 CRUD methods |
| `tests/Feature/Api/InvestmentActionDefinitionTest.php` | Admin feature tests |

---

## What-If Impact Mapping

| Action Key | what_if_impact_type | Metric Affected | Calculation |
|---|---|---|---|
| `risk_profile_missing` | default | projected_value (small) | Default action gain |
| `no_holdings` | default | projected_value (small) | Default action gain |
| `low_diversification` | default | projected_value (small) | Default action gain |
| `high_total_fees` | fee_reduction | annual_fees, projected_value | (current - benchmark) * value |
| `high_fund_fees` | fee_reduction | annual_fees, projected_value | OCF reduction savings |
| `high_platform_fees` | fee_reduction | annual_fees, projected_value | Platform fee reduction |
| `rebalance_portfolio` | default | projected_value (small) | Default action gain |
| `tax_loss_harvesting` | tax_optimisation | projected_value | Tax saving reinvested |
| `open_isa` | tax_optimisation | projected_value | Tax optimisation gain |
| `use_isa_allowance` | tax_optimisation | projected_value | Tax optimisation gain |
| `consider_bonds` | tax_optimisation | projected_value | Tax optimisation gain |
| `emergency_fund_critical` | savings_increase | emergency_fund_months, total_wealth | Monthly allocation from disposable |
| `emergency_fund_grow` | savings_increase | emergency_fund_months, total_wealth | Monthly allocation from disposable |
| `switch_savings_rate` | savings_increase | total_wealth | Interest rate gain per year |
| `isa_allowance_remaining` | contribution | projected_value | Monthly allocation into ISA |
| `surplus_to_isa` | contribution | projected_value | Lump sum + growth |
| `surplus_to_pension` | contribution | projected_value | Lump sum + tax relief + growth |
| `surplus_to_bond` | default | projected_value (small) | Tax-deferred growth |
| `goal_no_contribution` | contribution | projected_value | Monthly allocation |
| `goal_behind_schedule` | contribution | projected_value | Monthly allocation |
| `goal_deadline_approaching` | contribution | projected_value | Monthly allocation |

---

## Execution Order

1. **Merge retirementPlanFix branch first** (prerequisite)
2. Phase 1: Migration + Model + Factory + Seeder → run migrate + seed
3. Phase 2: InvestmentActionDefinitionService (new, no callers yet) + InvestmentAgent update
4. Phase 3: Admin backend (controller, request, routes)
5. Phase 4.1-4.2: InvestmentPersonalInformation + InvestmentExecutiveSummary (new components)
6. **Phase 2.3 + 4.3-4.6 together**: InvestmentPlanService rewrite + frontend component updates (API format changes, must deploy together)
7. Phase 3.2: Admin frontend (tab, table, modal)
8. Phase 5: Tests throughout, final integration after step 6

---

## Key Reference Files (on retirementPlanFix branch)

| File | Role |
|------|------|
| `app/Services/Retirement/RetirementActionDefinitionService.php` | PRIMARY PATTERN — follow exactly |
| `app/Models/RetirementActionDefinition.php` | Model pattern |
| `database/seeders/RetirementActionDefinitionSeeder.php` | Seeder pattern |
| `app/Services/Plans/RetirementPlanService.php` | Plan service integration pattern |
| `resources/js/components/Plans/Retirement/CascadingActionChart.vue` | Reuse directly (generic) |
| `resources/js/components/Plans/Retirement/RetirementGroupedActions.vue` | Frontend cascade pattern |
| `resources/js/components/Plans/Retirement/RetirementPersonalInformation.vue` | Personal info pattern |
| `resources/js/components/Admin/AdminRetirementActions.vue` | Admin table pattern |
| `resources/js/components/Admin/RetirementActionModal.vue` | Admin modal pattern |

---

## Verification

1. `php artisan migrate && php artisan db:seed` — 21 rows in investment_action_definitions
2. `./vendor/bin/pest tests/Unit/Services/Plans/` — all existing tests pass
3. `./vendor/bin/pest tests/Unit/Services/Investment/` — new service tests pass
4. Browser: login as peak_earners → Plans → Investment Plan
   - Personal information section visible below summary
   - Cascading per-action charts with before/after series
   - Toggle an action → subsequent charts cascade (shift baselines)
   - What-if metrics update correctly
   - Fee projection charts still visible for accounts with fee actions
5. Browser: admin panel → Investment Actions tab → 21 definitions visible
   - Toggle an action off → investment plan no longer shows it
   - Edit a threshold → plan reflects change
6. Browser: all preview personas load investment plan without errors
7. `./vendor/bin/pest` — full test suite passes
8. No amber/orange colours (Rule 9), currencyMixin (Rule 6), no acronyms (Rule 10), British spelling
