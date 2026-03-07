# Retirement Actions — Complete System Map

**Generated:** 2 March 2026
**Scope:** Full trace of every action in the retirement plan (`/plans/retirement`)

---

## Architecture Flow

```
User → /plans/retirement
  → PlanController::generate()
    → RetirementPlanService::generatePlan()
      ├── RetirementAgent::analyze()                    ← raw data (pensions, projections, gaps)
      ├── RetirementAgent::generateRecommendations()    ← agent-sourced actions
      │     └── ContributionOptimizer::optimizeContributions()  ← 4 of 7 action types
      ├── BasePlanService::getGoalsForPlan()             ← retirement goals
      ├── BasePlanService::buildGoalRecommendations()    ← goal-sourced actions
      ├── array_merge(goalRecs, agentRecs)               ← goals go FIRST
      ├── BasePlanService::prepareActions()               ← structure + sort + assign IDs
      ├── buildPensionGrowthProjections()                 ← per-pension chart data
      └── buildWhatIfData()                               ← what-if scenario impact
```

---

## Every Possible Action (10 types)

### Agent-Sourced (7)

| # | Title | Category | Priority | Scope | Trigger |
|---|---|---|---|---|---|
| 1 | Maximise Employer Pension Match | `Employer_match` | high | `account` | Workplace DC pension with employee contribution < 5% |
| 2 | Start Pension Contributions | `Start_contributions` | high | `account` | DC pension with fund value > 0 but no ongoing contributions |
| 3 | Increase Pension Contributions | `Contribution_increase` | medium | `portfolio` | Income gap exists and additional contributions needed |
| 4 | Optimise Pension Tax Relief | `Tax_relief` | medium | `portfolio` | Higher-rate taxpayer with contributions < £40k |
| 5 | Annual Allowance Exceeded | `Tax Planning` | critical | `portfolio` | Total contributions exceed allowance + carry forward |
| 6 | National Insurance Gaps | `State Pension` | high | `portfolio` | NI years won't reach required by State Pension Age |
| 7 | Consider Adjusting Retirement Age | `Retirement Planning` | high | `portfolio` | Income gap > 10% of target income |

### Goal-Sourced (3)

| # | Title | Category | Priority | Scope | Trigger |
|---|---|---|---|---|---|
| 8 | Start contributing to {goal} | `Goal` | high | default | Linked retirement goal with no monthly contribution |
| 9 | {goal} is behind schedule | `Goal` | high | default | Linked retirement goal off-track |
| 10 | {goal} target date is approaching | `Goal` | medium | default | Goal < 6 months remaining and < 75% complete |

---

## Action Generation Detail

### Step 1: RetirementAgent::analyze()

**File:** `app/Agents/RetirementAgent.php` (lines 58-137)
**Cache:** `retirement_analysis_{userId}` for 3600 seconds

Loads `RetirementProfile`, `DCPension` (collection), `DBPension` (collection), `StatePension`. Calls:
- `PensionProjector::projectTotalRetirementIncome($userId)` — income breakdown
- `AnnualAllowanceChecker::checkAnnualAllowance($userId, $taxYear)` — AA excess flag

Returns:
```php
[
    'summary' => [
        'years_to_retirement', 'target_retirement_age',
        'projected_retirement_income', 'target_retirement_income',
        'income_gap', 'retires_before_spa', 'state_pension_age',
        'state_pension_income', 'income_after_spa', 'income_gap_after_spa',
        'current_dc_value', 'total_dc_value'
    ],
    'income_projection' => [...],
    'breakdown' => ['dc_pensions' => [...], 'db_pensions' => [...], 'state_pension' => [...]],
    'annual_allowance' => ['has_excess' => bool, ...],
    'profile' => [...]
]
```

### Step 2: RetirementAgent::generateRecommendations()

**File:** `app/Agents/RetirementAgent.php` (lines 142-255)
**Not cached** — runs fresh every plan generation.

**Block A — ContributionOptimizer** (`app/Services/Retirement/ContributionOptimizer.php`):

Iterates over all DC pensions and produces up to 4 types:

1. **Employer Match** — Workplace pension where `employee_contribution_percent < 5.0`. Category: `employer_match`. Scope: `account` (linked to specific pension by `scheme_name`/`account_id`).
2. **Start Contributions** — DC pension with `current_fund_value > 0` but calculated `annualContrib <= 0`. Category: `start_contributions`. Scope: `account`.
3. **Contribution Increase** — `targetIncome > 0` AND `yearsToRetirement > 0` AND `requiredAdditionalContribution > 0`. Category: `contribution_increase`. Scope: `portfolio`.
4. **Tax Relief** — User is higher-rate taxpayer (income > £50,270) AND total contributions < £40,000/year. Category: `tax_relief`. Scope: `portfolio`.

Account-level recs (`employer_match`, `start_contributions`) are matched back to their `DCPension` model in `RetirementAgent::generateRecommendations()` (lines 174-185) to attach `account_id` and `account_name`.

**Block B — Annual Allowance** (lines 191-204):
- Title: "Annual Allowance Exceeded"
- Trigger: `annual_allowance['has_excess'] === true`
- Priority: integer `1` (normalises to `critical`)

**Block C — NI Gaps / State Pension** (lines 207-228):
- Title: "National Insurance Gaps"
- Trigger: `ni_years_completed < ni_years_required` AND `(ni_years_completed + yearsUntilSPA) < ni_years_required` (won't reach naturally)
- Priority: sequential integer (normalises to `high`)

**Block D — Retirement Age Adjustment** (lines 230-248):
- Title: "Consider Adjusting Retirement Age"
- Trigger: `incomeGap > (targetIncome * 0.10)` AND `retirementAge > 0`
- Suggests `min(retirementAge + 3, 70)` as alternative
- Priority: sequential integer (normalises to `high`)

### Step 3: Goal-Sourced Actions

**Files:** `app/Services/Plans/BasePlanService.php` (lines 35-63, 94-156)

Goals fetched:
```php
Goal::active()->where('assigned_module', 'retirement')
    ->orWhere('goal_type', 'retirement')
```

Only **linked** goals (with `linked_savings_account_id` or `linked_investment_account_id`) generate action cards. Unlinked goals appear in the plan's `unlinked_goals` section but produce no actions.

Three possible goal actions:

1. **Start contributing** — `monthly_contribution <= 0` AND `required_monthly_contribution > 0` AND `progress < 100`
2. **Behind schedule** — `is_on_track === false`
3. **Target date approaching** — `months_remaining <= 6` AND `progress < 75`

All have `source: 'goal'` and `goal_id` set.

### Step 4: Action Structuring

**File:** `app/Services/Plans/BasePlanService.php` (lines 171-232)

`prepareActions()` → `structureActions()` → `applyActionFilter()`

Each raw recommendation becomes an action card:
```php
[
    'title'                 => string,
    'description'           => string,
    'category'              => string,
    'priority'              => 'critical' | 'high' | 'medium' | 'low',
    'enabled'               => true,  // all start enabled
    'estimated_impact'      => string | null,
    'impact_parameters'     => array,
    'action_detail'         => string | null,
    'scope'                 => 'account' | 'portfolio',
    'account_id'            => int | null,
    'account_name'          => string | null,
    'source'                => 'module' | 'goal',
    'goal_id'               => int | null,
    'funding_source'        => string | null,
    'affordability'         => string | null,
    'affordability_warning' => string | null,
    'guidance'              => string | null,
]
```

**Sorting:** Goal-sourced first (`source === 'goal'` = group 0, others = group 1), then by priority within each group (`critical=0, high=1, medium=2, low=3`).

**ID assignment:** Sequential after sorting — `retirement_action_1`, `retirement_action_2`, etc. IDs change if recommendation count/order changes.

**Priority normalisation** (`normalisePriority()`):
- Integer input: `<= 1` → critical, `<= 3` → high, `<= 5` → medium, else low
- String input: direct match (`critical/urgent` → critical, `high` → high, `medium/moderate` → medium, `low` → low)

---

## Toggle Flow

Toggle is **frontend-only** — no API call on toggle.

```
PlanActionCard click
  → emit('toggle', actionId)
    → RetirementGroupedActions → emit('toggle', $event)
      → RetirementPlanContent → emit('toggle-action', $event)
        → RetirementPlan.vue::handleToggle()
          → Vuex store: toggleAction({ planKey: 'retirement', actionId })
```

**Vuex mutation** (`resources/js/store/modules/plans.js`, lines 59-85):
- First toggle of any action always **disables** it (default state is enabled)
- Stores override in `actionStates[planKey][actionId]` (boolean)
- Also updates `plan.actions` directly for Vue reactivity
- Persists in Vuex for the session only

**Recalculate** (separate, requires explicit API call):
```
Vuex::recalculateScenario()
  → POST /api/plans/retirement/recalculate
    body: { enabled_action_ids: ['retirement_action_1', ...] }
  → PlanController::recalculate()
    → WhatIfCalculator::recalculate()
      → RetirementPlanService::generatePlan(options: { enabled_action_ids: [...] })
        → BasePlanService::applyActionFilter() overrides enabled flags
```

Sets `what_if.is_approximate = false` on the response.

---

## What-If Impact

**File:** `app/Services/Plans/RetirementPlanService.php` (`buildWhatIfData()`, lines 278-342)

Budget pool: `DisposableIncomeAccessor` → `DistributionAccount` (prevents over-allocation).

Each enabled action's impact is computed by category match:

| Category match | What-if impact |
|---|---|
| Contains `contribution` or `start_contribution` | Allocates up to 30% of disposable income → projects future value → estimates income |
| Contains `consolid` | +2% of projected income (`PlanConfigService::getConsolidationEfficiencyGain()`) |
| Contains `tax` or `allowance` | +3% of projected income (`PlanConfigService::getTaxOptimisationGain()`) |
| Everything else | +1% of projected income (`PlanConfigService::getDefaultActionGain()`) |

**How each action type maps:**

| Action | Category value | Branch hit | Impact |
|---|---|---|---|
| Maximise Employer Pension Match | `Employer_match` | **default** (1% gain) | Note: does not contain "contribution" — see known issue below |
| Start Pension Contributions | `Start_contributions` | contribution branch | 30% of disposable income allocated |
| Increase Pension Contributions | `Contribution_increase` | contribution branch | 30% of disposable income allocated |
| Optimise Pension Tax Relief | `Tax_relief` | tax branch | +3% of projected income |
| Annual Allowance Exceeded | `Tax Planning` | tax branch | +3% of projected income |
| National Insurance Gaps | `State Pension` | default | +1% of projected income |
| Consider Adjusting Retirement Age | `Retirement Planning` | default | +1% of projected income |
| Goal actions | `Goal` | default | +1% of projected income |

**Output:**
```php
[
    'current_scenario' => [
        'projected_annual_income', 'income_gap',
        'total_dc_value', 'dc_value_at_retirement'
    ],
    'projected_scenario' => [
        'projected_annual_income', 'income_gap',
        'total_dc_value', 'dc_value_at_retirement',
        'additional_monthly_contribution'
    ],
    'is_approximate' => true,
    'frontend_calc_params' => [
        'current_dc_value', 'growth_rate', 'years', 'annuity_rate'
    ]
]
```

---

## Pension Growth Projections

**File:** `app/Services/Plans/RetirementPlanService.php` (`buildPensionGrowthProjections()`, lines 347-418)

One projection per DC pension:
- `netGrowthRate = PlanConfigService::getDefaultGrowthRate() - (platform_fee_percent / 100)`
- Annual contribution: prefers `monthly_contribution_amount * 12`, falls back to `annual_salary * (employee% + employer%) / 100`
- Identifies enabled account-level actions for this pension (`scope === 'account'` AND `account_id == pension.id`)
- Allocates up to 30% of disposable income per pension via `DistributionAccount`
- Builds two year-by-year series (year 0 to `yearsToRetirement`):
  - **Current:** `(prevValue + annualContribution) * (1 + netGrowthRate)`
  - **With actions:** `(prevValue + annualContribution + additionalAnnual) * (1 + netGrowthRate)`

**Frontend partial toggle** (`PensionGrowthProjectionChart.vue`):
- All account actions enabled → uses backend `with_actions_series` directly
- No account actions enabled → uses `current_series`
- Partial → linear interpolation: `current + (withActions - current) * (enabledCount / totalCount)`

**Portfolio chart** (`RetirementGroupedActions.vue`, computed frontend-only):
- Current: `startValue * (1 + growthRate)^year` (no contributions)
- Projected: `(value + additionalMonthly * 12) * (1 + growthRate)` per year

---

## Frontend Rendering

### RetirementGroupedActions.vue

**Single pension** (`pensionProjections.length <= 1`): Flat list of all action cards, one chart, what-if metrics.

**Multiple pensions**: Groups `scope === 'account'` actions by `account_id`, matches projections to groups by `pension_id`. Portfolio actions shown separately. Portfolio chart built client-side using `what_if.frontend_calc_params`.

### Component Hierarchy

```
RetirementPlan.vue (page view, toggle handler)
  └── RetirementPlanContent.vue (wires data to sub-components)
        ├── RetirementExecutiveSummary.vue (greeting, intro, goals table, actions table, closing)
        ├── PlanGoalSection.vue (linked/unlinked goals)
        ├── RetirementCurrentSituation.vue (pension details, metrics)
        ├── RetirementGroupedActions.vue (actions + charts + what-if)
        │     ├── PlanActionCard.vue (individual toggle card)
        │     ├── PensionGrowthProjectionChart.vue (per-pension chart)
        │     └── RetirementWhatIfControls.vue (scenario metrics)
        └── PlanConclusion.vue (closing narrative)
```

---

## All Files Involved (18)

| File | Role |
|---|---|
| `app/Http/Controllers/Api/Plans/PlanController.php` | HTTP entry point, cache wrapper |
| `app/Agents/RetirementAgent.php` | Analysis + recommendation generation |
| `app/Services/Retirement/ContributionOptimizer.php` | Produces 4 recommendation types |
| `app/Services/Retirement/AnnualAllowanceChecker.php` | Produces AA exceeded flag |
| `app/Services/Retirement/PensionProjector.php` | Projects total retirement income |
| `app/Services/Plans/RetirementPlanService.php` | Plan assembly, what-if, projections |
| `app/Services/Plans/BasePlanService.php` | Action structuring, goal recs, priority normalisation |
| `app/Services/Plans/PlanConfigService.php` | Configurable rates (growth 5%, withdrawal 4%, gains 1-3%) |
| `app/Services/Plans/DisposableIncomeAccessor.php` | Monthly disposable income for budgeting |
| `app/Services/Plans/DistributionAccount.php` | Budget ledger preventing over-allocation |
| `app/Services/Plans/WhatIfCalculator.php` | Recalculate API path |
| `resources/js/views/Plans/RetirementPlan.vue` | Page view, Vuex dispatches, toggle handler |
| `resources/js/components/Plans/Retirement/RetirementPlanContent.vue` | Wires plan data to sub-components |
| `resources/js/components/Plans/Retirement/RetirementGroupedActions.vue` | Actions renderer (single/multi-pension layout) |
| `resources/js/components/Plans/Shared/PlanActionCard.vue` | Individual toggle card |
| `resources/js/components/Plans/Retirement/PensionGrowthProjectionChart.vue` | Per-pension chart with toggle interpolation |
| `resources/js/components/Plans/Retirement/RetirementWhatIfControls.vue` | Scenario metrics display |
| `resources/js/store/modules/plans.js` | Vuex: toggle mutation, recalculate action |
| `resources/js/services/plansService.js` | API wrapper |

---

## Known Issue

**`Employer_match` category does not hit the contribution branch** in `buildWhatIfData()`. The category value `"Employer_match"` does not contain the substring `"contribution"`, so it falls to the default 1% gain instead of being treated as a contribution action. This underweights the what-if impact of employer match actions.
