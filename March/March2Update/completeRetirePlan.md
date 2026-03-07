# Retirement Plan Module: Complete Implementation Map

## Table of Contents

1. Architecture Overview
2. API Routes
3. Backend Flow: Plan Generation Pipeline
4. RetirementAgent: Analysis and Recommendations
5. Action System: Definitions, Evaluation, and Structure
6. Goal Integration
7. Funding Source Logic
8. Cascade Parameters and the DistributionAccount
9. What-If Calculations
10. Pension Growth Projections
11. Plan Response JSON: Every Field Documented
12. Plan Caching and Cache Invalidation
13. PlanActionFundingSelection: Persistence Model
14. Frontend Flow
15. Vuex Store: plans.js
16. Component Tree and Data Flow
17. Data Models
18. Key Files Reference

---

## 1. Architecture Overview

The Retirement Plan follows the canonical Fynla architecture:

```
Browser (Vue 3 SPA)
  └── RetirementPlan.vue (route view)
        └── Vuex plans store (fetchPlan action)
              └── plansService.js (GET /api/plans/retirement)
                    └── PlanController.generate()
                          └── Cache::remember("plan_retirement_{userId}", 1800s)
                                └── RetirementPlanService.generatePlan()
                                      ├── RetirementAgent.analyze()        ← cached 3600s separately
                                      │     ├── PensionProjector
                                      │     ├── AnnualAllowanceChecker
                                      │     └── ContributionOptimizer
                                      ├── RetirementActionDefinitionService.evaluateAgentActions()
                                      ├── RetirementActionDefinitionService.evaluateGoalActions()
                                      ├── BasePlanService.structureActions()
                                      ├── enrichActionsWithCascadeParams()
                                      ├── enrichActionsWithFundingSource()
                                      ├── buildPensionGrowthProjections()
                                      ├── buildWhatIfData()
                                      └── generateDynamicConclusion()
```

---

## 2. API Routes

All routes defined in `routes/api.php` (lines 850-864), all under `auth:sanctum` middleware:

| Method | URI | Handler | Purpose |
|--------|-----|---------|---------|
| GET | `/api/plans/retirement` | `PlanController@generate` | Generate (or serve cached) full retirement plan |
| POST | `/api/plans/retirement/recalculate` | `PlanController@recalculate` | Backend what-if recalculation with selected action IDs |
| DELETE | `/api/plans/retirement/clear-cache` | `PlanController@clearCache` | Invalidate the plan cache for the current user |
| PUT | `/api/plans/retirement/funding-source` | `PlanController@updateFundingSource` | Persist a funding source selection for an action |
| GET | `/api/plans/statuses` | `PlanController@statuses` | Dashboard readiness check (calls `checkDataCompleteness` for all plan types) |

The `{type}` parameter is route-constrained to `investment|protection|retirement|estate`.

---

## 3. Backend Flow: Plan Generation Pipeline

File: `app/Http/Controllers/Api/Plans/PlanController.php`
File: `app/Services/Plans/RetirementPlanService.php`

### Step 1: Controller Entry (PlanController::generate, line 40)

```
GET /api/plans/retirement
  → $userId = $request->user()->id
  → $cacheKey = "plan_retirement_{$userId}"
  → Cache::remember($cacheKey, 1800, fn() => RetirementPlanService->generatePlan($userId))
  → return JSON { success: true, data: $plan }
```

The plan is cached for 30 minutes (1800 seconds, from `PlanConfigService::getPlanCacheTTL()`). The cache key is flat (not tagged), so it is invalidated using `Cache::forget($cacheKey)` in `clearCache()` and `updateFundingSource()`.

### Step 2: RetirementPlanService::generatePlan (line 33)

The method orchestrates the entire pipeline in a fixed order:

1. **Load user**: `User::findOrFail($userId)`
2. **Check completeness**: `$this->checkDataCompleteness($userId)` — evaluates four data requirements
3. **Run agent analysis**: `$this->retirementAgent->analyze($userId)` — the core calculation engine, itself cached for 1 hour
4. **Early return if failed**: If `$analysis['success']` is false (no RetirementProfile), returns a skeleton plan with empty sections and an error message
5. **Build current situation**: `buildCurrentSituation($data, $userId)` — maps raw agent data to DC/DB/State pension display structures
6. **Get recommendations**: `getRecommendations($userId, $data)` — calls `RetirementAgent::generateRecommendations()` which delegates to `RetirementActionDefinitionService::evaluateAgentActions()`
7. **Load DC pensions**: `DCPension::where('user_id', $userId)->get()` — needed for both goal evaluations and projections
8. **Get goals**: `getGoalsForPlan($userId, 'retirement')` — fetches goals with `assigned_module = 'retirement'` OR `goal_type = 'retirement'`, splits linked/unlinked
9. **Evaluate goal actions**: `actionDefinitionService->evaluateGoalActions($goals['linked'], $dcPensions)`
10. **Merge and structure actions**: `array_merge($goalRecommendations, $recommendations)` then `prepareActions($allRecs, 'retirement', $options)` — this applies the action filter if `enabled_action_ids` option is present (from a recalculate call)
11. **Extract years to retirement**: `$data['summary']['years_to_retirement']` — computed from live `date_of_birth` in the agent, not stale stored age
12. **Enrich cascade params**: `enrichActionsWithCascadeParams()` — adds `cascade_params.additional_monthly` to each action for per-action charts
13. **Enrich funding sources**: `enrichActionsWithFundingSource()` — adds `funding_source` to contribution-type actions
14. **Re-derive enabled actions**: After enrichment, `enabledActions` is recalculated from the enriched actions array
15. **Build pension projections**: `buildPensionGrowthProjections()` — per-DC-pension growth series arrays
16. **Build what-if data**: `buildWhatIfData()` — current vs projected scenario comparison
17. **Build conclusion**: `generateDynamicConclusion()`
18. **Return full plan array** — 12 top-level keys

### Step 3: Data Completeness Check (line 115)

Four required data items (each item is worth 25% of completeness):

| Field | Check | Missing Label | Link |
|-------|-------|---------------|------|
| `retirement_profile` | `RetirementProfile::exists()` | "Retirement profile" | `/retirement` |
| `pensions` | `DCPension::exists() OR DBPension::exists()` | "Pension details" | `/retirement` |
| `income` | `user.annual_employment_income > 0 OR annual_self_employment_income > 0` | "Income details" | `/profile` |
| `target_income` | `RetirementProfile.target_retirement_income > 0` | "Target retirement income" | `/retirement` |

Returns `{ percentage: int, missing: array, complete: bool }`.

---

## 4. RetirementAgent: Analysis and Recommendations

File: `app/Agents/RetirementAgent.php`

### Injected Services

```
PensionProjector          - DC/DB/State pension value and income projections
AnnualAllowanceChecker    - Current year pension allowance usage and carry forward
ContributionOptimizer     - Tax relief optimisation calculations
DecumulationPlanner       - Drawdown sequencing (not used in plan generation path)
PensionPortfolioAnalyzer  - Portfolio analytics (only for DC pension portfolio analysis endpoint)
TaxConfigService          - Tax year, pension allowances, state pension amount
RetirementActionDefinitionService - Action definition evaluation
PortfolioAnalyzer, MonteCarloSimulator, AssetAllocationOptimizer, FeeAnalyzer, TaxEfficiencyCalculator (shared with Investment module)
PlanConfigService (optional) - Controls cache TTL
```

### analyze() Method (line 60)

Cache key: `retirement_analysis_{userId}`, tags: `['retirement', 'user_{userId}']`, TTL: 3600s (1 hour).

Execution within the cached closure:

1. Load `RetirementProfile`, all `DCPension`, all `DBPension`, `StatePension`
2. If no profile, return `response(false, 'No retirement profile found', [])`
3. Call `PensionProjector::projectTotalRetirementIncome($userId)` — returns `dc_total_value`, `dc_annual_income`, `db_annual_income`, `state_pension_income`, `total_projected_income`
4. Compute `incomeAtRetirement`: DC income + DB income; conditionally add state pension only if `retirementAge >= statePensionAge`
5. Compute `incomeGap = max(0, targetIncome - incomeAtRetirement)`
6. Compute `incomeAfterSPA` and `incomeGapAfterSPA` for pre-SPA retirees
7. Call `AnnualAllowanceChecker::checkAnnualAllowance($userId, $taxYear)` — checks contributions against £60,000 AA and carry forward
8. Compute `yearsToRetirement = max(0, retirementAge - profile.current_age)`
9. Build summary and breakdown arrays
10. Return `response(true, 'Retirement analysis completed', { summary, income_projection, breakdown, annual_allowance, profile })`

### generateRecommendations() Method (line 143)

Delegates entirely to `RetirementActionDefinitionService::evaluateAgentActions($analysisData)`.

Returns `{ recommendations: array, total_count: int, high_priority_count: int }`.

### PensionProjector: Income Projection Logic

File: `app/Services/Retirement/PensionProjector.php`

**DC Pensions** — Future Value formula:
```
FV = PV × (1+r)^n + PMT × [((1+r)^n - 1) / r]
```
Where:
- `PV` = `current_fund_value`
- `r` = net growth rate (`riskLevel growth rate - platform_fee_percent / 100`)
- `n` = `yearsToRetirement` (per-pension: uses `pension.retirement_age` not profile's `target_retirement_age`)
- `PMT` = annual contribution (fixed monthly × 12, or `annual_salary × (employee% + employer%) / 100`)

Growth rate priority per pension:
1. Pension's own `risk_preference` if `has_custom_risk = true`
2. User's `RiskProfile.risk_level` from the Risk module
3. Default 5%

DC Annual Income = `dc_total_value × 0.04` (hardcoded 4% withdrawal, not from PlanConfigService)

**DB Pensions** — Compound revaluation:
```
projected = accrued_annual_pension × (1 + revaluationRate)^yearsToRetirement
```
Revaluation rate by `inflation_protection`: `cpi→2.5%`, `rpi→3%`, `fixed→parsed from string`, `none→0%`, default `2%`.

**State Pension** — Uses `state_pension_forecast_annual` directly if set; otherwise calculates proportionally: `(ni_years_completed / ni_years_required) × full_new_state_pension` (pulled from TaxConfigService).

---

## 5. Action System: Definitions, Evaluation, and Structure

### Database Table: retirement_action_definitions

File: `database/migrations/2026_03_03_000001_create_retirement_action_definitions_table.php`

| Column | Type | Purpose |
|--------|------|---------|
| `key` | `string(50) UNIQUE` | Machine key, e.g. `employer_match` |
| `source` | `string(20)` | Either `agent` or `goal` |
| `title_template` | `string` | Display title with `{var}` placeholders |
| `description_template` | `text` | User-facing description with placeholders |
| `action_template` | `string nullable` | Short action sentence (not currently shown in UI) |
| `category` | `string(50)` | Groups actions for filtering and what-if impact routing |
| `priority` | `enum(critical, high, medium, low)` | Default display priority |
| `scope` | `enum(account, portfolio)` | `account` = per-DC-pension; `portfolio` = user-wide |
| `what_if_impact_type` | `string(30)` | `contribution`, `tax_optimisation`, `consolidation`, `default` |
| `trigger_config` | `json` | Condition and numeric thresholds for evaluation |
| `is_enabled` | `boolean` | Feature flag per definition |
| `sort_order` | `smallint` | Ordering for evaluation |

### The 10 Action Definitions (seeded by RetirementActionDefinitionSeeder)

File: `database/seeders/RetirementActionDefinitionSeeder.php`

**7 Agent-sourced actions:**

| Key | Category | Priority | Scope | What-If Type | Trigger Condition |
|-----|----------|----------|-------|--------------|-------------------|
| `employer_match` | `Employer_match` | high | account | contribution | `employee_contribution_percent_below` (threshold: 5%) on workplace pensions |
| `start_contributions` | `Start_contributions` | high | account | contribution | `zero_contribution_with_fund_value` (fund value > 0 but no contributions) |
| `contribution_increase` | `Contribution_increase` | medium | portfolio | contribution | `income_gap_positive_and_additional_contribution_required` (gap > 0 AND allowance headroom > 0) |
| `tax_relief` | `Tax Planning` | medium | portfolio | tax_optimisation | `higher_rate_taxpayer_below_allowance` (ContributionOptimizer finds a tax_relief recommendation) |
| `annual_allowance_exceeded` | `Tax Planning` | critical | portfolio | tax_optimisation | `annual_allowance_has_excess` (always priority 1 when triggered) |
| `ni_gaps` | `State Pension` | high | portfolio | default | `ni_years_wont_reach_required_by_spa` (NI years + years until SPA < required years) |
| `adjust_retirement_age` | `Retirement Planning` | medium | portfolio | default | `income_gap_exceeds_percentage_of_target` (gap > 10% of target; suggests retiring 3 years later, up to age 70) |

**3 Goal-sourced actions:**

| Key | Category | Priority | Scope | What-If Type | Trigger Condition |
|-----|----------|----------|-------|--------------|-------------------|
| `goal_no_contribution` | `Goal` | high | portfolio | contribution | `linked_goal_no_monthly_contribution` (no contribution on goal AND no pension contributions) |
| `goal_behind_schedule` | `Goal` | high | portfolio | contribution | `linked_goal_off_track` (effective contribution < required, accounting for pension contributions) |
| `goal_deadline_approaching` | `Goal` | medium | portfolio | contribution | `goal_months_remaining_below_and_progress_below` (< 6 months remaining AND progress < 75%) |

### Template Variable Substitution

The `RetirementActionDefinition::renderTitle/Description/Action()` methods replace `{placeholder}` tokens in templates. Variables are populated at evaluation time by each `evaluate*` method in `RetirementActionDefinitionService`.

Example substitutions:
- `employer_match`: `{additional_percent}`, `{scheme_name}`
- `contribution_increase`: `{monthly_amount}` (= `availableHeadroom / 12`)
- `tax_relief`: `{tax_saving}`, `{additional_contribution}`
- `ni_gaps`: `{years_short}`, `{years_until_spa}`
- `adjust_retirement_age`: `{suggested_age}`, `{current_age}`

### Conflict Resolution

`resolveContributionConflicts()` ensures `Start_contributions` and `Contribution_increase` are mutually exclusive:
- If user has any pension with `calculateAnnualContribution > 0` → remove `Start_contributions` recommendations
- Otherwise → remove `Contribution_increase` recommendations

This prevents contradictory advice appearing simultaneously.

### structureActions() — BasePlanService (line 260)

File: `app/Services/Plans/BasePlanService.php`

Transforms raw recommendation arrays into standardised action cards:

**Output fields per action:**

| Field | Source | Notes |
|-------|--------|-------|
| `id` | Generated: `retirement_action_{n}` | Re-indexed after sort |
| `title` | `rec.title` | From rendered template |
| `description` | `rec.description` | From rendered template |
| `category` | `rec.category` | Used for what-if routing and funding source display |
| `priority` | `normalisePriority(rec.priority)` | Normalised to critical/high/medium/low |
| `enabled` | `true` | Default on; toggled by user in frontend |
| `estimated_impact` | `rec.estimated_impact` | Optional monetary impact |
| `impact_parameters` | `rec.impact_parameters` | Optional details |
| `action_detail` | `rec.action` | Short action text |
| `scope` | `rec.scope` | `account` or `portfolio` |
| `account_id` | `rec.account_id` | DCPension ID for account-scoped actions |
| `account_name` | `rec.account_name` | DCPension scheme name |
| `source` | `rec.source` | `goal` or `module` |
| `goal_id` | `rec.goal_id` | Goal ID for goal-sourced actions |
| `funding_source` | `null` initially | Enriched later by `enrichActionsWithFundingSource()` |
| `affordability` | `rec.affordability` | Optional |
| `affordability_warning` | `rec.affordability_warning` | Optional |
| `guidance` | `rec.guidance` | Optional |

**Sorting**: Goal-sourced actions first (`source === 'goal'`), then by priority within each group (critical → high → medium → low). IDs are assigned after sort so they reflect display order.

---

## 6. Goal Integration

### Goal Fetching (BasePlanService::getGoalsForPlan, line 39)

```php
Goal::query()->active()
    ->where(fn($q) => $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId))
    ->where(fn($q) => $q
        ->where('assigned_module', 'retirement')
        ->orWhere('goal_type', 'retirement')
    )
```

Goals are split by whether they have `linked_savings_account_id` OR `linked_investment_account_id` set:
- **Linked**: Have a concrete account; progress can be tracked
- **Unlinked**: No account link; shown as a prompt to link in the UI

### Goal Formatting (formatGoalForPlan, line 73)

Each goal is mapped to a plan-friendly array containing all progress fields, plus `funding_source` computed by `resolveFundingSource()` (the goal-level funding source, distinct from the action-level one).

### Goal-Level Funding Source (BasePlanService::resolveFundingSource, line 119)

Resolves the best account to cover a goal's lump-sum shortfall (`target_amount - current_amount`):

1. Try liquid cash accounts (non-ISA, types: `current_account`, `instant_access`, `business_current`, `business_savings`), ordered by balance descending. Check if `balance - lumpSumNeeded >= monthlyExpenditure × 6` (6-month emergency threshold)
2. Fall back to GIA with warning: "Selling investments may trigger a Capital Gains Tax event..."
3. Return `{ name: null, warning: null }` if nothing suitable

### Pension Contribution Awareness in Goal Actions

`evaluateGoalActions()` calculates `monthlyPensionContribution` by summing all DC pension annual contributions and dividing by 12. This is passed to `evaluateGoalNoContribution()` and `evaluateGoalOffTrack()` as `monthlyPensionContribution`. The logic then uses:

```
effectiveContribution = goal.monthly_contribution + monthlyPensionContribution
```

This prevents false positives where a user with large pension contributions is told to "start contributing" to a retirement goal, when in reality their pension contributions are already addressing that goal.

### Goal Recommendations in Executive Summary

All goals (linked + unlinked) appear in `executive_summary.goals_summary` as `{ name, target, progress, on_track }`. The summary shows up to 5 top-priority actions in `actions_summary`.

---

## 7. Funding Source Logic

There are two distinct funding source mechanisms, often confused:

### 7a. Goal-Level Funding Source (BasePlanService::resolveFundingSource)

- **Purpose**: Tells the user which account to draw a lump sum from to complete a goal
- **When used**: For every linked goal in `formatGoalForPlan()`
- **Output**: `goal.funding_source = { name: string|null, warning: string|null }`
- **Display**: In `PlanGoalSection.vue` as "Recommended source: [account name]"
- **Not persisted**: Computed fresh every time, not user-selectable

### 7b. Action-Level Funding Source (RetirementPlanService::enrichActionsWithFundingSource)

- **Purpose**: Tells the user which account to fund an ongoing contribution action from
- **When used**: Only for actions with categories: `Employer_match`, `Start_contributions`, `Contribution_increase`
- **Output**: `action.funding_source = { selected_id, selected_type, selected_name, warning, eligible_accounts: [] }`
- **Persisted**: User's choice is saved in `plan_action_funding_selections` table
- **Display**: In `PlanActionCard.vue` as a dropdown "Fund from [account]"

#### Action-Level Logic in Detail

1. Check if any contribution-category actions exist; if not, skip enrichment
2. Load persisted selections from `PlanActionFundingSelection::getForUser($userId, 'retirement')` — keyed by `"{category}_{targetAccountId}"`
3. Build eligible accounts list once for the user:
   - Cash accounts: `SavingsAccount` where `is_isa = false` AND `account_type IN (current_account, instant_access, business_current, business_savings)`, ordered by balance descending. Each account gets a `warning` if withdrawing would breach 6-month emergency threshold
   - GIA accounts: `InvestmentAccount` where `account_type = 'gia'`, always with warning "Using this account may cause a tax event"
4. For each contribution action:
   - Compute `selectionKey = "{category}_{account_id}"` (where `account_id` is the DC pension ID for account-scoped, or 0 for portfolio-scoped)
   - Check for a persisted selection; verify the account still exists in eligible list
   - If no valid persisted selection, call `autoRecommendFundingAccount()`:
     - First: cash without warning
     - Second: cash with warning
     - Third: GIA
5. Attach `funding_source` object to each matching action

---

## 8. Cascade Parameters and the DistributionAccount

### DistributionAccount

File: `app/Services/Plans/DistributionAccount.php`

An in-memory allocation tracker. Created once per plan generation with `$monthlyDisposable` as the initial balance. Each action requests an allocation via `allocate($label, $amount)`:
- Returns `min($amount, $remaining)` — capped at remaining balance
- Tracks allocations by label
- Prevents over-allocation (total allocations never exceed `$initialBalance`)

This ensures that if there are multiple contribution actions, they don't all independently claim the user's full disposable income.

### enrichActionsWithCascadeParams (line 441)

For each action, computes `additional_monthly` based on `what_if_impact_type`:

| Impact Type | Additional Monthly Calculation |
|-------------|-------------------------------|
| `contribution` | `budget->allocate(action.id, monthlyDisposable × 0.3)` — requests 30% of disposable income, capped at remaining budget |
| `tax_optimisation` | `projectedIncome × taxOptimisationGain / 12` (default: projected income × 0.03 / 12) |
| `consolidation` | `projectedIncome × consolidationEfficiencyGain / 12` (default: projected income × 0.02 / 12) |
| `default` | `projectedIncome × defaultActionGain / 12` (default: projected income × 0.01 / 12) |

The output is appended as `action.cascade_params = { additional_monthly: float }`.

A **fresh** `DistributionAccount` is created in `buildWhatIfData()` for the portfolio-level what-if calculation, and again in `buildPensionGrowthProjections()` for per-pension projection. The account in `enrichActionsWithCascadeParams()` is separate. This means the same disposable income pool is used three times independently — this is consistent but means the three calculations are not truly coordinated.

---

## 9. What-If Calculations

### buildWhatIfData (line 345)

Called with `$enabledActions` (actions where `enabled = true` at time of call).

**Inputs**:
- `projectedIncome`: from `$summary['projected_retirement_income']` (agent output)
- `targetIncome`: from `$summary['target_retirement_income']`
- `currentDcValue`: current total DC fund value today
- `projectedDcValueAtRetirement`: projected total DC value at retirement (from agent's income projection)
- `yearsToRetirement`: from summary or passed in
- `monthlyDisposable`: from `DisposableIncomeAccessor::getMonthlyForUser()`

**DistributionAccount**: Fresh instance seeded with `monthlyDisposable`.

**For each enabled action**, the impact type routes to:
- `contribution`: allocate up to `monthlyDisposable × 0.3` from budget, add to `additionalContribution`, compute `incomeImprovement += estimateIncomeFromContribution(allocated, years)`
- `consolidation`: `incomeImprovement += projectedIncome × 0.02`
- `tax_optimisation`: `incomeImprovement += projectedIncome × 0.03`
- `default`: `incomeImprovement += projectedIncome × 0.01`

**estimateIncomeFromContribution**:
```
additionalFundAtRetirement = FV(0, monthlyContribution × 12, years, growthRate)
additionalAnnualIncome = additionalFundAtRetirement × withdrawalRate
```
Uses `BasePlanService::projectFutureValue()` (standard FV formula with monthly compounding).

**Output**:
```json
{
  "current_scenario": {
    "projected_annual_income": float,
    "income_gap": float,
    "total_dc_value": float,        // current DC value (today)
    "dc_value_at_retirement": float  // projected without actions
  },
  "projected_scenario": {
    "projected_annual_income": float, // with all enabled actions
    "income_gap": float,
    "total_dc_value": float,          // current DC value (today)
    "dc_value_at_retirement": float,  // projected with additional contributions
    "additional_monthly_contribution": float
  },
  "is_approximate": true,
  "frontend_calc_params": {
    "current_dc_value": float,
    "current_annual_contribution": float,
    "growth_rate": float,            // from PlanConfigService.getDefaultGrowthRate()
    "years": int,
    "annuity_rate": float            // from PlanConfigService.getWithdrawalRate()
  }
}
```

### WhatIfCalculator (Precise Backend Recalculation)

File: `app/Services/Plans/WhatIfCalculator.php`

When the user clicks "Recalculate" in the frontend (triggering `POST /api/plans/retirement/recalculate`):

1. Receives `enabled_action_ids` array from request
2. Calls `RetirementPlanService::generatePlan($userId, ['enabled_action_ids' => $enabledActionIds])`
3. In `generatePlan()`, `prepareActions()` calls `applyActionFilter()` which sets `enabled = true/false` based on the provided IDs
4. The full plan pipeline re-runs with the filtered enabled actions
5. `what_if.is_approximate` is set to `false` on the result

This is a full plan regeneration, not a cached call. The result is NOT stored in the plan cache — it returns a fresh plan object directly to the caller.

### Frontend What-If Calculation (Approximate, Client-Side)

When actions are toggled in the UI, the frontend does NOT call the recalculate endpoint automatically. Instead:
- The `CascadingActionChart` in `RetirementGroupedActions.vue` computes before/after series client-side using `projectSeries(startValue, baseAnnualContrib, additionalMonthly, growthRate, years)`
- `RetirementWhatIfControls.vue` displays `current_scenario` and `projected_scenario` from the last-fetched plan data (i.e., the backend values computed at plan generation time)
- The backend what-if data only updates when the user explicitly triggers "Recalculate"

---

## 10. Pension Growth Projections

### buildPensionGrowthProjections (line 633)

Produces a per-DC-pension growth series for the `PensionGrowthProjectionChart.vue`.

For each DC pension:
1. `netGrowthRate = PlanConfigService.getDefaultGrowthRate() - (platform_fee_percent / 100)`
2. Compute `annualContribution` (fixed monthly × 12, or salary × (employee% + employer%))
3. Find enabled account-scoped actions for this pension: `filter(a => a.scope === 'account' && a.account_id == pension.id && a.enabled)`
4. If any such actions exist, request an additional monthly from the DistributionAccount (`monthlyDisposable × 0.3`)
5. Build two year-by-year series from year 0 to `yearsToRetirement`:
   - `currentSeries`: `(prev + annualContribution) × (1 + netGrowthRate)`
   - `withActionsSeries`: `(prev + annualContribution + additionalAnnual) × (1 + netGrowthRate)`

**Output per pension**:
```json
{
  "pension_id": int,
  "pension_name": string,
  "pension_type": string,
  "current_value": float,
  "annual_contribution": float,
  "growth_rate": float,
  "years": int,
  "projection_label": "to retirement",
  "current_series": [int, ...],         // year 0 to year n
  "with_actions_series": [int, ...],    // year 0 to year n
  "projection_difference": int          // end value difference
}
```

Note: This data is passed to the frontend but `PensionGrowthProjectionChart.vue` is imported but not rendered in `RetirementGroupedActions.vue` in the current template. The chart component exists and is ready, but `CascadingActionChart` is used instead for per-action charts.

---

## 11. Plan Response JSON: Every Field Documented

The full response from `GET /api/plans/retirement` is `{ success: true, data: { ...plan } }`.

### Top-Level Keys

```json
{
  "metadata": { ... },
  "completeness_warning": null | { ... },
  "executive_summary": { ... },
  "personal_information": { ... },
  "current_situation": { ... },
  "actions": [ ... ],
  "pension_projections": [ ... ],
  "what_if": { ... },
  "conclusion": { ... },
  "linked_goals": [ ... ],
  "unlinked_goals": [ ... ]
}
```

### metadata
```json
{
  "plan_type": "retirement",
  "generated_at": "2026-03-02T10:00:00+00:00",
  "user_name": "James Carter",
  "user_id": 42,
  "data_completeness": {
    "percentage": 75,
    "missing": [{ "field": "target_income", "label": "...", "description": "...", "link": "/retirement" }],
    "complete": false
  },
  "has_warnings": true
}
```

### completeness_warning
`null` if data is complete, otherwise:
```json
{
  "level": "minor" | "significant",
  "message": "Some data is missing which may affect the accuracy of this plan.",
  "missing_items": [{ "field": "...", "label": "...", "description": "...", "link": "..." }],
  "completeness_percentage": 75
}
```
`level` is "significant" if more than 2 items are missing, else "minor".

### executive_summary
```json
{
  "opening": "Thank you for using Fynla. Here is your personalised Retirement Plan based on your pensions and retirement goals.",
  "greeting": "Dear James,",
  "introduction": "This plan aims to show you how you can achieve retirement at age 65 with £3,000 per month after tax, so you can enjoy your retirement.",
  "goals_summary": [
    { "name": "Retire Comfortably", "target": 500000.00, "progress": 45.0, "on_track": false }
  ],
  "actions_summary": [
    { "title": "Maximise Employer Pension Match", "priority": "high" }
  ],
  "total_actions": 4,
  "closing": "The solutions and recommendations outlined below are achievable steps that can bring you closer to your desired retirement income.",
  "on_track": false
}
```
`on_track` is `true` when `income_gap <= 0`. `closing` text differs based on whether `on_track`. `goals_summary` includes both linked and unlinked goals. `actions_summary` shows max 5 actions.

### personal_information
```json
{
  "full_name": "James Carter",
  "date_of_birth": "1980-03-15",
  "age": 45,
  "marital_status": "married",
  "spouse_name": "Emily Carter",
  "children": ["Sophie Carter", "Oliver Carter"],
  "gross_income": 85000.00,
  "net_income": 57240.00,
  "annual_expenditure": 36000.00,
  "disposable_income": 21240.00,
  "monthly_disposable": 1770.00,
  "risk_level": "medium"
}
```
Income from all sources summed. `net_income`, `annual_expenditure`, `disposable_income`, `monthly_disposable` from `DisposableIncomeAccessor` (which calls `UserProfileService::getCompleteProfile()`). Risk level from `RiskProfile` table.

### current_situation
```json
{
  "summary": {
    "years_to_retirement": 20,
    "target_retirement_age": 65,
    "projected_retirement_income": 18000.00,
    "target_retirement_income": 36000.00,
    "income_gap": 18000.00,
    "retires_before_spa": true,
    "state_pension_age": 67,
    "state_pension_income": 11500.00,
    "income_after_spa": 29500.00,
    "income_gap_after_spa": 6500.00,
    "current_dc_value": 145000.00,
    "total_dc_value": 385000.00,
    "total_pensions_count": 3
  },
  "dc_pensions": [
    {
      "id": 1,
      "scheme_name": "Acme Workplace Pension",
      "provider": "Aviva",
      "current_value": 120000.00,
      "monthly_contribution": 416.67,
      "employer_contribution": 208.33,
      "pension_type": "workplace"
    }
  ],
  "db_pensions": [
    {
      "id": 2,
      "scheme_name": "NHS Pension",
      "projected_annual_pension": 8200.00,
      "normal_retirement_age": 67
    }
  ],
  "state_pension": {
    "weekly_amount": 221.15,
    "annual_amount": 11500.00,
    "ni_years": 18,
    "state_pension_age": 67
  },
  "income_projection": { ... },    // raw PensionProjector output
  "annual_allowance": { ... },     // AnnualAllowanceChecker output
  "breakdown": {
    "dc_pensions": [ ... ],        // formatted DC pension summaries
    "db_pensions": [ ... ],        // formatted DB pension summaries
    "state_pension": { ... }       // formatted state pension summary
  }
}
```

`income_after_spa` and `income_gap_after_spa` are only non-null when `retires_before_spa = true`. `total_dc_value` in the summary is the projected value at retirement, not current. `income_projection` is the raw return from `PensionProjector::projectTotalRetirementIncome()`.

### actions (array)

Each action:
```json
{
  "id": "retirement_action_1",
  "title": "Maximise Employer Pension Match",
  "description": "Increase your contribution by 2.0% to maximise employer match on Acme Workplace Pension. This is free money!",
  "category": "Employer_match",
  "priority": "high",
  "enabled": true,
  "estimated_impact": null,
  "impact_parameters": [],
  "action_detail": "Review your workplace pension contribution level.",
  "scope": "account",
  "account_id": 1,
  "account_name": "Acme Workplace Pension",
  "source": "module",
  "goal_id": null,
  "funding_source": {
    "selected_id": 5,
    "selected_type": "savings",
    "selected_name": "Barclays Current Account",
    "warning": null,
    "eligible_accounts": [
      { "id": 5, "type": "savings", "name": "Barclays Current Account", "balance": 15000.00, "warning": null },
      { "id": 8, "type": "investment", "name": "Hargreaves Lansdown GIA", "balance": 22000.00, "warning": "Using this account may cause a tax event." }
    ]
  },
  "affordability": null,
  "affordability_warning": null,
  "guidance": null,
  "cascade_params": {
    "additional_monthly": 531.00
  }
}
```
`funding_source` is only present on actions with category in `[Employer_match, Start_contributions, Contribution_increase]`. `cascade_params` is always present on every action after enrichment.

### pension_projections (array)

```json
[
  {
    "pension_id": 1,
    "pension_name": "Acme Workplace Pension",
    "pension_type": "workplace",
    "current_value": 120000.00,
    "annual_contribution": 7500.00,
    "growth_rate": 0.0475,
    "years": 20,
    "projection_label": "to retirement",
    "current_series": [120000, 133500, 147997, ...],
    "with_actions_series": [120000, 143000, 167850, ...],
    "projection_difference": 85000
  }
]
```

### what_if

Full structure documented in Section 9 above.

### conclusion
```json
{
  "summary_text": "There are 2 actions that are essential to reaching your retirement goal. A further 2 actions are optional but would strengthen your position.",
  "total_actions": 4,
  "critical_actions": 0,
  "high_priority_actions": 2,
  "essential_actions": [
    { "title": "Maximise Employer Pension Match", "priority": "high" },
    { "title": "National Insurance Gaps", "priority": "high" }
  ],
  "optional_actions": [
    { "title": "Increase Pension Contributions", "priority": "medium" },
    { "title": "Consider Adjusting Retirement Age", "priority": "medium" }
  ],
  "detailed_breakdown": [
    { "category": "Employer_match", "action_count": 1, "actions": ["Maximise Employer Pension Match"] }
  ]
}
```

### linked_goals / unlinked_goals (arrays)

Each goal:
```json
{
  "id": 12,
  "name": "Retire Comfortably",
  "type": "retirement",
  "display_type": "Retirement",
  "assigned_module": "retirement",
  "priority": "high",
  "target_amount": 500000.00,
  "current_amount": 225000.00,
  "progress_percentage": 45.0,
  "is_on_track": false,
  "target_date": "2045-03-15",
  "months_remaining": 228,
  "monthly_contribution": 1200.00,
  "required_monthly_contribution": 2100.00,
  "linked_savings_account_id": 7,
  "linked_investment_account_id": null,
  "description": null,
  "is_essential": true,
  "funding_source": { "name": "Barclays Current Account", "warning": null }
}
```

---

## 12. Plan Caching and Cache Invalidation

### Two Cache Layers

**Layer 1 — Agent Analysis Cache** (inner)
- Key: `retirement_analysis_{userId}`
- Tags: `['retirement', 'user_{userId}']`
- TTL: 3600 seconds (1 hour), from `PlanConfigService::getRetirementCacheTTL()`
- Mechanism: `BaseAgent::remember()` with automatic tag support detection
- Stores: The full agent analysis output (summary, income_projection, breakdown, annual_allowance, profile)
- Invalidated by: `RetirementAgent::invalidateUserCache($userId)` — not called in the plan pipeline itself; would be called by observers on model changes (DCPension, RetirementProfile, StatePension)

**Layer 2 — Plan Output Cache** (outer)
- Key: `plan_retirement_{userId}`
- Tags: None (flat cache key)
- TTL: 1800 seconds (30 minutes), from `PlanConfigService::getPlanCacheTTL()`
- Mechanism: `Cache::remember()` in `PlanController::generate()`
- Stores: The complete plan array (all 12 top-level keys)
- Invalidated by:
  - `PlanController::clearCache()` — explicit `DELETE /api/plans/retirement/clear-cache`
  - `PlanController::updateFundingSource()` — automatically after persisting a funding selection

The recalculate endpoint bypasses the plan cache entirely — it always generates fresh and does not store its result.

---

## 13. PlanActionFundingSelection Model

File: `app/Models/PlanActionFundingSelection.php`
Migration: `database/migrations/2026_03_04_000001_create_plan_action_funding_selections_table.php`

### Table Schema

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `user_id` | FK → users, cascade delete | |
| `plan_type` | string(20) | e.g. `retirement` |
| `action_category` | string(50) | e.g. `Employer_match` |
| `target_account_id` | unsignedBigInt default 0 | DC pension ID for account-scoped; 0 for portfolio-scoped |
| `funding_source_type` | string(30) | `savings` or `investment` |
| `funding_source_id` | unsignedBigInt | SavingsAccount or InvestmentAccount ID |
| `created_at`, `updated_at` | timestamps | |

Unique constraint: `(user_id, plan_type, action_category, target_account_id)` — one selection per action type per target account per plan type per user.

### Key Methods

- `getForUser($userId, $planType)`: Returns a Collection keyed by `"{category}_{targetAccountId}"` for O(1) lookup
- `upsertSelection(...)`: Uses `updateOrCreate` on the composite key

### Update Flow

```
User selects different account in PlanActionCard dropdown
  → onFundingSourceChange() emits 'update-funding-source' with { actionId, actionCategory, targetAccountId, fundingSourceType, fundingSourceId }
  → RetirementPlan.vue handleUpdateFundingSource() → Vuex updateActionFundingSource action
  → Optimistic local update: setActionFundingSource mutation (updates Vuex state immediately)
  → API call: PUT /api/plans/retirement/funding-source
      → PlanController.updateFundingSource()
          → Validates account ownership
          → PlanActionFundingSelection::upsertSelection()
          → Cache::forget("plan_retirement_{userId}")
  → Non-critical: if API fails, local state already updated
```

Preview users' selections are blocked by `PreviewWriteInterceptor` middleware (returns a fake success). The interceptor does not block `/plans/{type}/recalculate` or `/plans/{type}/funding-source` calculation patterns, but `PUT /api/plans/retirement/funding-source` is a write operation and will be intercepted.

---

## 14. Frontend Flow

### Route

Defined in `resources/js/router/index.js` — the path `/plans/retirement` maps to a lazy-loaded `RetirementPlan.vue` view with `meta: { requiresAuth: true }`.

### View: RetirementPlan.vue

File: `resources/js/views/Plans/RetirementPlan.vue`

The view is minimal:
1. Wraps everything in `PlanPageLayout` (handles loading/error states, print button, back navigation)
2. On `mounted()`: calls `fetchPlan('retirement')` Vuex action
3. Renders `RetirementPlanContent` when `plan` is truthy
4. Forwards two events upward from the content tree:
   - `toggle-action` → `toggleAction({ planKey: 'retirement', actionId })`
   - `update-funding-source` → `updateActionFundingSource({ planKey: 'retirement', ...payload })`
5. Uses `planPrintMixin` for print/PDF functionality

---

## 15. Vuex Store: plans.js

File: `resources/js/store/modules/plans.js`

### State Shape

```javascript
{
  plans: {},         // { 'retirement': { ...planData }, 'investment': {...} }
  goalPlans: {},     // { 1: { ...planData } }
  actionStates: {},  // { 'retirement': { 'retirement_action_1': true/false } }
  planStatuses: null,
  loading: false,
  recalculating: false,
  error: null
}
```

### Getters

| Getter | Description |
|--------|-------------|
| `getPlan(type)` | Returns plan object or null |
| `getGoalPlan(goalId)` | Returns goal plan or null |
| `enabledActions(type)` | Actions filtered by `actionStates` overrides, then `a.enabled` default |
| `disabledActions(type)` | Inverse of above |
| `enabledGoalActions(goalId)` | Same pattern for goal plans |
| `isLoading` | Global loading flag |
| `isRecalculating` | Recalculate in progress |
| `planStatuses` | Dashboard readiness data |

### Key Mutations

**`toggleAction({ planKey, actionId })`**: The toggle logic is:
```javascript
currentState = actionStates[planKey][actionId]  // undefined first time, then true/false
newState = currentState !== undefined ? !currentState : false  // First toggle always disables
```
This means all actions start enabled (from backend), and the first toggle disables them. The mutation also updates the plan object itself in `state.plans` for Vue reactivity (not just the `actionStates` overlay).

**`setActionFundingSource({ planKey, actionId, fundingSourceId, fundingSourceType })`**: Finds the matching account in `eligible_accounts` and updates `selected_id`, `selected_type`, `selected_name`, and `warning` in the action's `funding_source` object. Triggers full plan object replacement for Vue reactivity.

### Key Actions

**`fetchPlan(type)`**: Sets loading, calls `plansService.generatePlan(type)`, commits `setPlan`. The backend response's `data` field is stored directly.

**`updateActionFundingSource({ planKey, actionId, ... })`**: Optimistic update first (commits `setActionFundingSource`), then async API call. Failure is silently swallowed — local state remains updated.

**`recalculateScenario({ type })`**: Collects current enabled action IDs from state (respecting `actionStates` overrides), calls `plansService.recalculateScenario(type, enabledIds)`, replaces the plan in state with the full new plan.

---

## 16. Component Tree and Data Flow

```
RetirementPlan.vue (view)
  ├── PlanPageLayout.vue
  │     ├── AppLayout.vue (authenticated shell)
  │     ├── PrintHeader.vue
  │     ├── PlanLoadingState.vue
  │     └── PlanErrorState.vue
  └── RetirementPlanContent.vue  [prop: plan]
        ├── PlanMissingDataPrompt.vue  [prop: plan.completeness_warning]
        ├── RetirementExecutiveSummary.vue  [prop: plan.executive_summary]
        │     (greeting, opening, introduction, goals table, actions table, closing)
        ├── RetirementPersonalInformation.vue  [prop: plan.personal_information]
        │     (personal details, family, financial overview, risk profile)
        ├── RetirementCurrentSituation.vue  [prop: plan.current_situation]
        │     (DC pension list, DB pension list, state pension card, key metric cards)
        ├── PlanGoalSection.vue  [props: plan.linked_goals, plan.unlinked_goals]
        │     (linked goal cards with progress bars, funding source, unlinked prompt)
        ├── RetirementGroupedActions.vue
        │     [props: plan.actions, plan.pension_projections, plan.what_if]
        │     [emits: toggle, update-funding-source]
        │     │
        │     ├── Single pension mode (isSinglePension = pensionProjections.length <= 1):
        │     │     For each action in cascadedActions:
        │     │       ├── PlanActionCard.vue  [prop: item.action]
        │     │       └── CascadingActionChart.vue  [props: beforeSeries, afterSeries, years, differenceAmount]
        │     │     └── What-If metrics block:
        │     │           ├── RetirementWhatIfControls.vue  [prop: whatIf.current_scenario]
        │     │           └── RetirementWhatIfControls.vue  [prop: whatIf.projected_scenario]
        │     │
        │     └── Multi-pension mode:
        │           For each pensionGroup (account-scoped actions):
        │             For each action in group:
        │               ├── PlanActionCard.vue
        │               └── CascadingActionChart.vue (from cascadedActionMap)
        │           For each portfolio action:
        │             ├── PlanActionCard.vue
        │             └── CascadingActionChart.vue (from cascadedActionMap)
        │           └── What-If metrics block (same as single pension mode)
        │
        └── PlanConclusion.vue  [prop: plan.conclusion]
              (summary text, essential actions numbered list, optional actions list)
```

### CascadingActionChart: Client-Side Series Computation

The `cascadedActions` computed property in `RetirementGroupedActions.vue` is the key client-side calculation:

```javascript
// Iterate actions sorted by priority
let cumulativeAdditionalMonthly = 0;

for each action:
  beforeMonthly = cumulativeAdditionalMonthly
  beforeSeries = projectSeries(baseValue, baseAnnualContrib, beforeMonthly, growthRate, years)
  
  actionMonthly = action.cascade_params.additional_monthly
  afterMonthly = action.enabled ? (beforeMonthly + actionMonthly) : beforeMonthly
  afterSeries = projectSeries(baseValue, baseAnnualContrib, afterMonthly, growthRate, years)
  
  if action.enabled:
    cumulativeAdditionalMonthly += actionMonthly

  differenceAmount = afterSeries[last] - beforeSeries[last]
```

`projectSeries(startValue, baseAnnualContrib, additionalMonthly, growthRate, years)`:
```javascript
totalAnnual = baseAnnualContrib + (additionalMonthly * 12)
value = startValue
for y = 0 to years:
  series.push(Math.round(value))
  value = (value + totalAnnual) * (1 + growthRate)
```

Parameters come from `whatIf.frontend_calc_params`:
- `current_dc_value` → `baseValue`
- `current_annual_contribution` → `baseAnnualContrib`
- `growth_rate` → `growthRate` (PlanConfigService default)
- `years` → projection horizon

The "before" series for each action is the cumulative result of all prior enabled actions. This creates a true cascade: action 2's "before" line is action 1's "after" line, showing marginal improvement per action.

### PlanActionCard: Toggle and Funding Source Events

**Toggle**: Button click emits `'toggle', action.id` → bubbles up through `RetirementGroupedActions` → `RetirementPlanContent` → `RetirementPlan` → `handleToggle(actionId)` → Vuex `toggleAction`. No API call; pure client-side state toggle.

**Funding Source Change**: `select` element `@change` → `onFundingSourceChange()`:
1. Parses `event.target.value` as `"{type}_{id}"`
2. Emits `'update-funding-source'` with `{ actionId, actionCategory, targetAccountId, fundingSourceType, fundingSourceId }`
3. Bubbles to `RetirementPlan.vue` → `handleUpdateFundingSource(payload)` → Vuex `updateActionFundingSource`
4. Vuex: optimistic local update + async API `PUT /api/plans/retirement/funding-source`

---

## 17. Data Models

| Model | Table | Key Fields | Notes |
|-------|-------|-----------|-------|
| `User` | `users` | `annual_employment_income`, `annual_self_employment_income`, `date_of_birth`, `marital_status`, `monthly_expenditure`, `annual_expenditure` | Many income fields; disposable income computed via UserProfileService |
| `RetirementProfile` | `retirement_profiles` | `user_id`, `current_age`, `target_retirement_age`, `target_retirement_income`, `life_expectancy`, `prior_year_unused_allowance (json)` | SoftDeletes; `current_age` may be stale (agent uses `date_of_birth`) |
| `DCPension` | `dc_pensions` | `user_id`, `scheme_name`, `scheme_type (workplace/sipp/personal)`, `provider`, `current_fund_value`, `annual_salary`, `employee_contribution_percent`, `employer_contribution_percent`, `employer_matching_limit`, `monthly_contribution_amount`, `platform_fee_percent`, `retirement_age`, `has_custom_risk`, `risk_preference`, `has_flexibly_accessed` | Auditable, SoftDeletes; polymorphic `Holdings` relationship |
| `DBPension` | `db_pensions` | `user_id`, `scheme_name`, `accrued_annual_pension`, `normal_retirement_age`, `inflation_protection`, `revaluation_method`, `spouse_pension_percent` | Auditable, SoftDeletes |
| `StatePension` | `state_pensions` | `user_id`, `ni_years_completed`, `ni_years_required`, `state_pension_forecast_annual`, `state_pension_age`, `ni_gaps (json)` | Auditable; one record per user |
| `RiskProfile` | `risk_profiles` | `user_id`, `risk_level` | Used in personal_information section |
| `Goal` | `goals` | `user_id`, `joint_owner_id`, `goal_name`, `goal_type`, `assigned_module`, `target_amount`, `current_amount`, `target_date`, `linked_savings_account_id`, `linked_investment_account_id`, `monthly_contribution`, `priority` | Scoped query: `active()`, joint ownership aware |
| `SavingsAccount` | `savings_accounts` | `user_id`, `account_name`, `institution`, `account_type`, `current_balance`, `is_isa`, `additional_monthly_savings` | Queried for eligible funding accounts |
| `InvestmentAccount` | `investment_accounts` | `user_id`, `account_name`, `provider`, `account_type (gia/isa/...)`, `current_value` | Queried for GIA funding accounts |
| `RetirementActionDefinition` | `retirement_action_definitions` | `key`, `source`, `title_template`, `category`, `trigger_config`, `what_if_impact_type`, `is_enabled`, `sort_order` | 10 records seeded; admin-configurable |
| `PlanActionFundingSelection` | `plan_action_funding_selections` | `user_id`, `plan_type`, `action_category`, `target_account_id`, `funding_source_type`, `funding_source_id` | Unique on (user, plan_type, category, target_account_id) |
| `ExpenditureProfile` | `expenditure_profiles` | `user_id`, `total_monthly_expenditure` | Priority 1 source for `resolveMonthlyExpenditure()` |
| `PlanConfiguration` | `plan_configurations` | `is_active`, `config_data (json)` | Admin-configurable rates and TTLs |

### Key Query Patterns

- DC/DB Pensions: always `WHERE user_id = ?` (no joint ownership)
- StatePension: `WHERE user_id = ?` first()
- Goals: `WHERE (user_id = ? OR joint_owner_id = ?) AND (assigned_module = 'retirement' OR goal_type = 'retirement')`
- SavingsAccount for funding: `WHERE user_id = ? AND is_isa = false AND account_type IN (...)`
- InvestmentAccount for GIA: `WHERE user_id = ? AND account_type = 'gia'`
- RetirementActionDefinition: `WHERE is_enabled = true AND source = 'agent'/'goal' ORDER BY sort_order`
- PlanActionFundingSelection: `WHERE user_id = ? AND plan_type = 'retirement'`

---

## 18. Key Files Reference

| File | Role |
|------|------|
| `routes/api.php` (lines 850-864) | All plan API routes including retirement |
| `app/Http/Controllers/Api/Plans/PlanController.php` | Entry point controller: generate, recalculate, clearCache, updateFundingSource, statuses |
| `app/Services/Plans/RetirementPlanService.php` | Core plan orchestration: all build* and enrich* methods |
| `app/Services/Plans/BasePlanService.php` | Abstract base: structureActions, getGoalsForPlan, formatGoalForPlan, resolveFundingSource, generateDynamicConclusion, projectFutureValue |
| `app/Agents/RetirementAgent.php` | Agent: analyze() (cached), generateRecommendations(), buildScenarios() |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | Evaluates all 10 action definitions against user data |
| `app/Services/Retirement/PensionProjector.php` | DC/DB/State pension FV projections; risk-aware growth rate selection |
| `app/Services/Plans/PlanConfigService.php` | Admin-configurable rates (growth rate, withdrawal rate, gains, TTLs); falls back to TaxDefaults |
| `app/Services/Plans/DisposableIncomeAccessor.php` | Fetches pre-computed disposable income from UserProfileService |
| `app/Services/Plans/DistributionAccount.php` | In-memory disposable income allocation tracker (prevents over-allocation) |
| `app/Services/Plans/WhatIfCalculator.php` | Orchestrates precise backend recalculation for recalculate endpoint |
| `app/Models/RetirementActionDefinition.php` | Model with template rendering methods (renderTitle, renderDescription, renderAction) |
| `app/Models/PlanActionFundingSelection.php` | Persists user's funding source choices (getForUser, upsertSelection) |
| `app/Models/RetirementProfile.php` | User's retirement planning goals (target age, target income, life expectancy) |
| `app/Models/DCPension.php` | Defined Contribution pension (workplace, SIPP, personal); has Holdings |
| `app/Models/DBPension.php` | Defined Benefit pension (final salary, career average, public sector) |
| `app/Models/StatePension.php` | State Pension record (NI years, forecast, SPA) |
| `app/Traits/ResolvesExpenditure.php` | Priority chain for resolving monthly expenditure (ExpenditureProfile → user.monthly → user.annual/12) |
| `database/migrations/2026_03_03_000001_create_retirement_action_definitions_table.php` | Schema for action definitions table |
| `database/migrations/2026_03_04_000001_create_plan_action_funding_selections_table.php` | Schema for funding selections table |
| `database/seeders/RetirementActionDefinitionSeeder.php` | Seeds all 10 action definitions (7 agent + 3 goal) |
| `resources/js/views/Plans/RetirementPlan.vue` | Route view; mounts plan fetch, owns event handlers for toggle and funding source |
| `resources/js/components/Plans/Retirement/RetirementPlanContent.vue` | Content shell; renders all plan sections in order |
| `resources/js/components/Plans/Retirement/RetirementExecutiveSummary.vue` | Greeting, introduction, goals table, actions table, closing |
| `resources/js/components/Plans/Retirement/RetirementPersonalInformation.vue` | Personal details, family, financial overview, risk profile |
| `resources/js/components/Plans/Retirement/RetirementCurrentSituation.vue` | DC/DB/State pension cards, key metric cards, SPA-aware income gap display |
| `resources/js/components/Plans/Retirement/RetirementGroupedActions.vue` | Single vs multi-pension routing; cascading chart computation; what-if metrics |
| `resources/js/components/Plans/Retirement/CascadingActionChart.vue` | Before/after ApexCharts line chart for per-action impact visualisation |
| `resources/js/components/Plans/Retirement/PensionGrowthProjectionChart.vue` | Per-pension growth projection chart (imported but not rendered in current template) |
| `resources/js/components/Plans/Retirement/RetirementWhatIfControls.vue` | Displays scenario metrics rows (projected income, gap, DC value, at-retirement value) |
| `resources/js/components/Plans/Shared/PlanActionCard.vue` | Action card with toggle switch and funding source dropdown |
| `resources/js/components/Plans/Shared/PlanGoalSection.vue` | Linked goal cards with progress bars and action blocks; unlinked goals prompt |
| `resources/js/components/Plans/Shared/PlanConclusion.vue` | Summary text, essential actions numbered list, optional actions list |
| `resources/js/components/Plans/Shared/PlanMissingDataPrompt.vue` | Blue info banner with missing items list and progress bar |
| `resources/js/components/Plans/Shared/PlanPageLayout.vue` | Page shell with loading/error states, print button, back navigation |
| `resources/js/components/Plans/Shared/PlanSectionHeader.vue` | Colour-coded section header banner (blue, green, teal, gray, purple) |
| `resources/js/store/modules/plans.js` | Vuex module: plans state, toggle logic, funding source optimistic updates, recalculate action |
| `resources/js/services/plansService.js` | API wrapper: generatePlan, recalculateScenario, updateFundingSource, getDashboardStatuses |