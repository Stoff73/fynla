# Goals & Life Events - Updated Implementation Plan

**Created:** 2026-02-23
**Status:** Proposed
**Scope:** Cross-module integration, goal strategy surfacing, life event propagation, known issue fixes

---

## Table of Contents

1. [Plan Overview](#1-plan-overview)
2. [Life Events Integration Into All Modules](#2-life-events-integration-into-all-modules)
3. [Life Events in Estate Plan & Income/Expenditure](#3-life-events-in-estate-plan--incomeexpenditure)
4. [Goal Strategies in Relevant Module Sections](#4-goal-strategies-in-relevant-module-sections)
5. [Known Issue Fixes (Section 17)](#5-known-issue-fixes-section-17)
6. [Database Changes](#6-database-changes)
7. [Implementation Order](#7-implementation-order)

---

## 1. Plan Overview

Four workstreams delivering full cross-module integration for Goals & Life Events:

| # | Workstream | Summary |
|---|---|---|
| 1 | Life Events in All Modules | Surface relevant life events inside Protection, Cash, Investment, Retirement, and Estate sections |
| 2 | Life Events in Estate & I&E | Feed life events into IHT calculations, estate projections, and income/expenditure forecasts |
| 3 | Goal Strategies in Modules | Show goal strategies (contribution plans, glide paths, scenarios) inside the module the goal is assigned to |
| 4 | Known Issue Fixes | Resolve all 8 issues from Section 17 of the current state document |

---

## 2. Life Events Integration Into All Modules

### 2.1 Concept

Every life event has a natural home module based on its type. When a user navigates to any module section, they should see relevant upcoming life events displayed in a consistent banner/card format, with the event's expected impact on that module clearly explained.

### 2.2 Event-to-Module Mapping

| Event Type | Primary Module | Secondary Module(s) | Rationale |
|---|---|---|---|
| `inheritance` | Estate | Cash, Investment | Direct estate impact; incoming funds affect savings/investment |
| `gift_received` | Estate | Cash | PET/CLT implications; incoming cash |
| `bonus` | Cash | Retirement | Cash inflow; potential pension top-up opportunity |
| `redundancy_payment` | Protection | Cash | Triggers protection review; incoming cash |
| `property_sale` | Estate | Cash, Investment | CGT implications; sale proceeds |
| `business_sale` | Estate | Investment | CGT/Business Relief implications; reinvestment |
| `pension_lump_sum` | Retirement | Cash | Tax-free lump sum; cash inflow |
| `lottery_windfall` | Cash | Investment, Estate | Cash inflow; investment/estate planning opportunity |
| `large_purchase` | Cash | - | Cash outflow |
| `home_improvement` | Cash | Estate | Cash outflow; property value increase |
| `wedding` | Cash | - | Cash outflow |
| `education_fees` | Cash | - | Cash outflow |
| `gift_given` | Estate | Cash | PET implications; cash outflow |
| `medical_expense` | Protection | Cash | Protection claim opportunity; cash outflow |
| `custom_income` | Cash | - | Cash inflow |
| `custom_expense` | Cash | - | Cash outflow |

### 2.3 Backend Changes

#### New Service: `LifeEventIntegrationService`

**File:** `app/Services/Goals/LifeEventIntegrationService.php`

**Purpose:** Central service that returns life events relevant to a specific module, with contextual annotations explaining the impact.

```
Methods:
- getEventsForModule(userId, module, includeHousehold): LifeEvent[]
    Returns life events mapped to the given module (primary + secondary),
    sorted by expected_date, with an added 'module_context' annotation
    explaining why this event is relevant to the module.

- getModuleImpactSummary(userId, module): array
    Returns aggregate impact summary for a module:
    { upcoming_income, upcoming_expense, net_impact, event_count, next_event }

- getEventModules(LifeEvent): string[]
    Returns the list of modules an event is relevant to based on the mapping table.
```

#### Controller Changes

Each module controller's `index` or `analyze` method gains an optional `include_life_events` parameter. When true (default), the response includes a `life_events` block:

| Controller | Method | Addition |
|---|---|---|
| `ProtectionController` | `index()` | Add `life_events` key with redundancy/medical events + protection review prompts |
| `SavingsController` | `index()` | Add `life_events` key with all cash-impact events |
| `InvestmentController` | `index()` | Add `life_events` key with investment-relevant events (inheritance proceeds, business sale, windfall) |
| `RetirementController` | `index()` | Add `life_events` key with pension lump sum, bonus (pension top-up opportunity) |
| `EstateController` | `index()` | Add `life_events` key with inheritance, gifts, property/business sale events |

Each controller calls `LifeEventIntegrationService::getEventsForModule()` and `getModuleImpactSummary()`.

### 2.4 Frontend Changes

#### New Shared Component: `ModuleLifeEvents.vue`

**File:** `resources/js/components/shared/ModuleLifeEvents.vue`

A reusable component displayed within each module section showing upcoming life events relevant to that module.

**Display:**
- Collapsible section titled "Upcoming Life Events" with event count badge
- Each event shown as a compact card: icon, name, amount (formatted), expected date, certainty badge, and a one-line context sentence explaining the module relevance
- "View All" link navigates to `/goals` Events tab
- Empty state: hidden entirely (no empty state message needed)

**Props:** `module` (string), `events` (array from API), `impactSummary` (object)

#### Module View Integration Points

| Module View | File | Where to Place `ModuleLifeEvents` |
|---|---|---|
| Cash / Savings | `SavingsDashboard.vue` | Below the accounts list, above any goals section |
| Investment | `InvestmentDashboard.vue` or main investment view | Below accounts/holdings, above analysis |
| Retirement | Retirement main view | Below pension summary, above projections |
| Protection | `ProtectionDashboard.vue` | Below policies overview, as an alert-style banner for redundancy/medical events |
| Estate | `EstateDashboard.vue` | Below assets summary, above IHT calculation — estate events are high priority here |

#### Vuex Store Changes

Each module's Vuex store gains:
- State: `lifeEvents: []`, `lifeEventImpact: null`
- Mutation: `SET_LIFE_EVENTS`, `SET_LIFE_EVENT_IMPACT`
- Action: These are populated from the existing module `fetch` actions (the data comes back in the controller response)
- Getter: `upcomingLifeEvents`, `lifeEventNetImpact`

No new API calls needed — life events piggyback on the module's existing data fetch.

### 2.5 Dashboard Integration

The existing dashboard already shows life events on the Goals projection chart. No additional dashboard changes needed for this workstream, but each module's dashboard card could show a small event count indicator if upcoming events exist.

**Optional enhancement:** Add a subtle event count indicator (e.g., a small numbered badge) on each dashboard module card when that module has upcoming life events. This draws attention without cluttering the dashboard.

---

## 3. Life Events in Estate Plan & Income/Expenditure

### 3.1 Estate Plan Integration

Life events have significant estate planning implications that are currently ignored. This workstream connects life events into the estate calculation engine.

#### 3.1.1 IHT Calculation Service Changes

**File:** `app/Services/Estate/IHTCalculationService.php`

**Current behaviour:** Projects estate value growth using a simple cash surplus model (income - expenses compounding annually). Life events are ignored.

**New behaviour:** Incorporate life events into the year-by-year estate projection:

```
For each projection year (current age to life expectancy):
  1. Calculate base surplus (income - expenses) [existing]
  2. Query life events occurring in this year
  3. For income events: add to that year's cash surplus
     - inheritance: add full amount (already in estate as received asset)
     - gift_received: add amount (potential PET if >£3k annual exemption)
     - property_sale: add net proceeds (subtract CGT if applicable)
     - business_sale: add net proceeds (subtract CGT, consider Business Relief loss)
     - bonus/redundancy/pension_lump_sum/windfall: add to cash
  4. For expense events: subtract from that year's cash
     - large_purchase/home_improvement/wedding/education/medical/gift_given
  5. Apply certainty weighting (confirmed=1.0, likely=0.75, possible=0.5, speculative=0.25)
  6. Update cumulative estate value
```

**New method:** `projectEstateWithLifeEvents(userId, includeHousehold): array`
Returns year-by-year estate projection with life events factored in, alongside the existing projection without events for comparison.

#### 3.1.2 Gifting Strategy Impact

**File:** `app/Services/Estate/GiftingStrategyOptimizer.php`

**Changes:**
- Factor in `gift_given` life events as future planned gifts in the PET/CLT calculations
- Factor in `gift_received` life events as potential future PET exposures if the user re-gifts
- When calculating affordable annual gifting capacity, subtract planned `gift_given` events from available surplus

#### 3.1.3 Comprehensive Estate Plan

**File:** `app/Services/Estate/ComprehensiveEstatePlanService.php`

**Changes:**
Add a new section (Section 14) to the comprehensive plan: "Life Events Impact on Estate"
- Lists all upcoming life events with estate implications
- Shows projected IHT impact of each event
- Flags events that should trigger an estate plan review (e.g., large inheritance, property sale)
- Provides recommendations (e.g., "Your planned inheritance of GBP150,000 in 2028 will increase your taxable estate above the nil-rate band. Consider a gifting strategy to mitigate IHT.")

#### 3.1.4 Estate Frontend Changes

**Component:** New `EstateLifeEventsImpact.vue` within `resources/js/components/Estate/`

**Display:**
- Timeline view of life events with estate impact annotations
- Before/after IHT liability comparison for each significant event
- Integrated into `EstateDashboard.vue` as a dedicated panel/tab

### 3.2 Income & Expenditure Integration

Life events that represent income or expenses should appear in the user's financial forecast so they can plan around them.

#### 3.2.1 Financial Forecast Service

**New Service:** `app/Services/Goals/FinancialForecastService.php`

**Purpose:** Projects income and expenditure forward in time, including one-off life events.

```
Methods:
- getMonthlyForecast(userId, months): array
    Returns month-by-month forecast of regular income, regular expenditure,
    and life event impacts, producing a net cash flow forecast.

- getAnnualForecast(userId, years): array
    Same but annual granularity, suitable for longer-term planning.

- getUpcomingImpacts(userId, months): array
    Returns only the life event impacts in the next N months,
    useful for cash flow warnings.
```

#### 3.2.2 Income & Expenditure View Changes

**File:** `resources/js/components/Profile/IncomeStatementTab.vue`

**Current:** Shows a static income statement (current annual income vs current annual expenditure).

**Changes:**
- Add a "Forecast" toggle/tab that shows future months/years with life events overlaid
- Income events appear as additional one-off income lines in the month they occur
- Expense events appear as additional one-off expense lines
- Net surplus/deficit line adjusts accordingly
- Visual indicator (event icon) next to one-off items to distinguish from regular income/expenditure

#### 3.2.3 Cash Flow Warning System

When a life event (especially a large expense) would push a future month into deficit:
- Show a warning banner in the Cash/Savings section
- Suggest building up savings or adjusting goal contributions before the event
- This ties into the `GoalAffordabilityService` — affordability should account for upcoming life event expenses

**Changes to `GoalAffordabilityService`:**
- New method: `analyzeAffordabilityWithLifeEvents(goal, user)` that factors in upcoming life events within the goal's time horizon
- Large upcoming expenses reduce available surplus for goal contributions
- Large upcoming income events could be flagged as potential lump-sum contribution opportunities

---

## 4. Goal Strategies in Relevant Module Sections

### 4.1 Concept

When a goal is assigned to a module (savings, investment, retirement, or property), the strategy to achieve that goal should be visible within that module's section. Users shouldn't have to navigate to the Goals page to see what they need to do in their savings, investment, or retirement section to achieve their goals.

### 4.2 Goal Strategy Data Structure

Each goal's strategy includes:

```
{
  goal: { name, type, target, current, progress, target_date, priority, status },
  contribution_plan: {
    monthly_amount, frequency, next_due, streak, is_on_track,
    required_monthly_to_stay_on_track
  },
  linked_accounts: [{ id, name, type, current_balance }],
  affordability: { category, ratio, monthly_surplus_after_goal },
  projections: {  // investment/retirement goals only
    expected_completion_date, probability_of_success,
    recommended_allocation: { equities, bonds, cash }
  },
  scenarios: [  // top 2 most impactful
    { name, description, impact }
  ],
  recommendations: [string]  // 1-3 actionable items
}
```

### 4.3 Backend Changes

#### New Service: `GoalStrategyService`

**File:** `app/Services/Goals/GoalStrategyService.php`

**Purpose:** Generates a strategy summary for goals within a specific module, suitable for display within that module's section.

```
Methods:
- getStrategiesForModule(userId, module): array
    Returns all active goals assigned to the given module, each with its
    strategy summary (contribution plan, affordability, projections, recommendations).

- getStrategyForGoal(goalId): array
    Returns the full strategy for a single goal.

- getModuleGoalsSummary(userId, module): array
    Returns aggregate summary: total goals, total target, total current,
    overall progress, total monthly commitment, affordability status.
```

**Dependencies:** GoalProgressService, GoalAffordabilityService, GoalRiskService, GoalAssignmentService

#### Controller Changes

| Controller | Method | Addition |
|---|---|---|
| `SavingsController` | `index()` | Add `goal_strategies` key with savings-assigned goal strategies |
| `InvestmentController` | `index()` | Add `goal_strategies` key with investment-assigned goal strategies |
| `RetirementController` | `index()` | Add `goal_strategies` key with retirement-assigned goal strategies |

Property goals surface within the Estate module since there's no dedicated property module view.

### 4.4 Frontend Changes

#### New Shared Component: `ModuleGoalStrategies.vue`

**File:** `resources/js/components/shared/ModuleGoalStrategies.vue`

**Display:**
- Section titled "Goal Strategies" with goal count
- Each goal shown as an expandable card:
  - **Collapsed:** Goal name, progress bar, monthly contribution, on-track status badge
  - **Expanded:** Full strategy details — contribution schedule, affordability assessment, projections chart (investment goals), glide path allocation, top scenarios, recommendations
- "Record Contribution" button on each goal (opens `ContributionModal`)
- "View Full Details" link navigates to `/goals` with the goal selected
- Empty state: "No goals assigned to this section. [Create a Goal](/goals)" link

**Props:** `module` (string), `strategies` (array from API), `summary` (object)

#### Module View Integration Points

| Module | View File | Placement | Notes |
|---|---|---|---|
| Cash / Savings | `SavingsDashboard.vue` | New tab or section below accounts | Shows savings goals — emergency fund, short-term targets. Replaces legacy savings goals UI. |
| Investment | Investment main view | New tab or section below holdings | Shows investment goals — wealth accumulation, long-term education. Includes glide path and projected returns. |
| Retirement | Retirement main view | New tab or section within retirement planning | Shows retirement goals — target retirement fund. Includes pension contribution strategy. |

#### Vuex Store Changes

Each module's Vuex store gains:
- State: `goalStrategies: []`, `goalsSummary: null`
- Mutation: `SET_GOAL_STRATEGIES`, `SET_GOALS_SUMMARY`
- Action: Populated from existing module `fetch` actions (data comes in controller response)
- Getter: `activeGoalStrategies`, `totalGoalCommitment`, `goalsOnTrack`

### 4.5 Interaction Between Goal Strategies and Module Data

| Module | How Goal Strategy Interacts |
|---|---|
| **Cash** | Show which savings accounts are linked to goals. Highlight the emergency fund goal prominently. Show how goal contributions affect the monthly savings rate. |
| **Investment** | Show how investment goal allocations compare to the user's current portfolio. Flag if the current risk profile doesn't match the goal's glide path recommendation. Show goal projections alongside account projections. |
| **Retirement** | Show how retirement goal contributions compare to pension annual allowance usage. Flag if the user is on track for their retirement income target. Integrate with the retirement readiness assessment. |

---

## 5. Known Issue Fixes (Section 17)

### 5.1 Issue 1: Two Goal Systems

**Problem:** The unified Goals module (`goals` table) coexists with the legacy `savings_goals` table and `investment_goals` table. These are not merged.

**Fix:**

#### Phase 1: Migration Mapping

Create a migration that:
1. Maps existing `savings_goals` records to `goals` table entries with `assigned_module = 'savings'`
2. Maps existing `investment_goals` records to `goals` table entries with `assigned_module = 'investment'`
3. Preserves all data including linked accounts, progress, and contribution history

**Migration mapping:**

| `savings_goals` field | `goals` field |
|---|---|
| `goal_name` | `goal_name` |
| `target_amount` | `target_amount` |
| `current_saved` | `current_amount` |
| `target_date` | `target_date` |
| `priority` | `priority` |
| `linked_account_id` | `linked_savings_account_id` |
| `auto_transfer_amount` | `monthly_contribution` |
| (auto) | `goal_type = 'custom'` |
| (auto) | `assigned_module = 'savings'` |

| `investment_goals` field | `goals` field |
|---|---|
| (mapped similarly) | (mapped similarly) |
| (auto) | `assigned_module = 'investment'` |

#### Phase 2: Deprecate Legacy Endpoints

1. Mark all `savings/goals/*` routes as deprecated with a 6-month sunset
2. Make legacy endpoints proxy to the unified Goals API internally (so existing frontend code doesn't break during transition)
3. Update `SavingsController` goal methods to call `GoalsController` methods under the hood
4. Update `GoalProgressController` (Investment) similarly

#### Phase 3: Frontend Consolidation

1. Remove legacy `SavingsGoalCard`, `SavingsGoalForm` components from the Savings module
2. Remove `InvestmentGoalCard`, `InvestmentGoalForm` from the Investment module
3. Replace with the new `ModuleGoalStrategies.vue` component (from Workstream 4)
4. Update Vuex stores to use `goals` module data instead of module-specific goal state

#### Phase 4: Cleanup

1. Add `@deprecated` annotations to `SavingsGoal` model
2. Do NOT drop legacy tables yet — keep for rollback safety
3. After 2 release cycles with no issues, create a migration to drop `savings_goals` and `investment_goals` tables

### 5.2 Issue 2: Simplified Tax in Affordability

**Problem:** `GoalAffordabilityService` uses hardcoded tax thresholds instead of `TaxConfigService`.

**Fix:**

1. Inject `TaxConfigService` into `GoalAffordabilityService` constructor
2. Replace the hardcoded `estimateNetIncome()` method:

**Current (hardcoded):**
```php
// Hardcoded 20%/40%/45% bands + 8%/2% NI
```

**New (using TaxConfigService):**
```php
private function estimateNetIncome(User $user): float
{
    $taxConfig = $this->taxConfigService->getIncomeTax();
    $niConfig = $this->taxConfigService->getNationalInsurance();

    // Use TaxConfigService bands for income tax calculation
    $grossIncome = $this->calculateGrossIncome($user);
    $incomeTax = $this->calculateIncomeTax($grossIncome, $taxConfig);
    $nationalInsurance = $this->calculateNI($grossIncome, $niConfig);

    return $grossIncome - $incomeTax - $nationalInsurance;
}
```

3. Alternatively, delegate to `UKTaxCalculator` directly (which already uses `TaxConfigService`), consistent with what `GoalsProjectionService` does:

```php
$calculator = app(UKTaxCalculator::class);
$taxResult = $calculator->calculateTax($grossIncome, $taxYear);
$netIncome = $grossIncome - $taxResult['total_tax'] - $taxResult['total_ni'];
```

4. Remove all hardcoded tax thresholds from the service
5. Add test coverage verifying that affordability calculations use current tax year rates

### 5.3 Issue 3: No Contribution Auto-Import

**Problem:** Contributions must be manually recorded. No integration with bank feeds or automatic tracking.

**Fix:**

This is a feature enhancement rather than a bug fix. Implement in two phases:

#### Phase 1: Linked Account Sync

For goals linked to a savings account (`linked_savings_account_id`), automatically track balance changes as contributions:

1. Add an observer on `SavingsAccount` model (`SavingsAccountObserver`)
2. When `current_balance` increases, check if any goals are linked to this account
3. If linked, auto-create a `GoalContribution` record:
   - `contribution_type = 'automatic'`
   - `amount = balance_increase`
   - `notes = 'Auto-tracked from [account_name] balance change'`
   - `streak_qualifying = true` (if amount >= 80% of expected monthly)
4. Update the goal's `current_amount` accordingly
5. Check milestones and streaks

**New file:** `app/Observers/SavingsAccountGoalObserver.php`

#### Phase 2: Investment Account Sync

For investment-assigned goals, optionally link to an investment account:

1. Add `linked_investment_account_id` field to `goals` table (nullable FK)
2. Same observer pattern on `InvestmentAccount` balance changes
3. Track net contributions (deposits) not market value changes

#### Phase 3: Manual Batch Import (Future)

Allow CSV/manual batch import of contributions — out of scope for this plan but noted for future iteration.

### 5.4 Issue 4: Linear Mortgage Reduction

**Problem:** The projection service reduces mortgages linearly to retirement age rather than using actual amortisation schedules.

**Fix:**

**File:** `app/Services/Goals/GoalsProjectionService.php`

1. Replace the linear reduction with a proper amortisation calculation:

```php
private function calculateMortgageBalance(
    float $originalBalance,
    float $annualRate,
    int $remainingYears,
    int $yearsElapsed,
    string $mortgageType  // 'repayment', 'interest_only', 'mixed'
): float {
    if ($mortgageType === 'interest_only') {
        // Balance stays constant until term end
        return $yearsElapsed >= $remainingYears ? 0 : $originalBalance;
    }

    // Standard amortisation: B(t) = P * ((1+r)^n - (1+r)^t) / ((1+r)^n - 1)
    $r = $annualRate;
    $n = $remainingYears;
    $t = min($yearsElapsed, $n);

    if ($r <= 0) {
        return max(0, $originalBalance * (1 - $t / $n));
    }

    $factor_n = pow(1 + $r, $n);
    $factor_t = pow(1 + $r, $t);
    $balance = $originalBalance * ($factor_n - $factor_t) / ($factor_n - 1);

    return max(0, $balance);
}
```

2. Pull mortgage data from the user's actual mortgage records:
   - Interest rate from `properties.mortgage_rate`
   - Remaining term from `properties.mortgage_remaining_years` or calculated from `mortgage_end_date`
   - Mortgage type from `properties.mortgage_type`

3. Handle multiple properties with separate mortgages — each property's mortgage follows its own amortisation schedule

4. For `mixed` mortgages: split into repayment and interest-only portions and calculate each separately

### 5.5 Issue 5: Single Cash Growth Rate

**Problem:** Cash in projections does not grow. Only investments, pensions, and property have growth applied.

**Fix:**

**File:** `app/Services/Goals/GoalsProjectionService.php`

1. Apply a cash growth rate to savings/cash balances in the projection:
   - Use `AssumptionsService` to get a `cash_growth_rate` (or default to Bank of England base rate proxy)
   - If no explicit cash growth assumption exists, default to `inflation_rate - 0.5%` (real return on cash is typically slightly negative)

2. Implementation:
```php
// In the yearly projection loop
$cashGrowthRate = $assumptions['cash_growth_rate']
    ?? max(0, $assumptions['inflation_rate'] - 0.005);

$assets['cash'] *= (1 + $cashGrowthRate);
```

3. Add `cash_growth_rate` to the `AssumptionsService` if not already present, with a sensible default (e.g., 2.0% nominal, matching approximate cash ISA rates)

4. Display the cash growth assumption in the `AssumptionsDisclosure.vue` component

### 5.6 Issue 6: No Goal Dependencies

**Problem:** Goals cannot be marked as dependent on other goals (e.g., "pay off debt before saving for house").

**Fix:**

#### Database Change

Add a `goal_dependencies` table:

```sql
CREATE TABLE goal_dependencies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    goal_id BIGINT UNSIGNED NOT NULL,
    depends_on_goal_id BIGINT UNSIGNED NOT NULL,
    dependency_type ENUM('blocks', 'informs') DEFAULT 'blocks',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
    FOREIGN KEY (depends_on_goal_id) REFERENCES goals(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dependency (goal_id, depends_on_goal_id)
);
```

- `blocks`: The dependent goal should not start until the prerequisite is completed (e.g., emergency fund before home deposit)
- `informs`: The dependent goal's strategy is influenced by the prerequisite but not blocked (e.g., debt repayment informs how much surplus is available for savings)

#### Model Changes

**`Goal.php`:**
```php
public function dependencies(): BelongsToMany
{
    return $this->belongsToMany(Goal::class, 'goal_dependencies', 'goal_id', 'depends_on_goal_id')
        ->withPivot('dependency_type', 'notes')
        ->withTimestamps();
}

public function dependents(): BelongsToMany
{
    return $this->belongsToMany(Goal::class, 'goal_dependencies', 'depends_on_goal_id', 'goal_id')
        ->withPivot('dependency_type', 'notes')
        ->withTimestamps();
}

public function isBlocked(): bool
{
    return $this->dependencies()
        ->wherePivot('dependency_type', 'blocks')
        ->where('status', '!=', 'completed')
        ->exists();
}
```

#### Service Changes

**`GoalAssignmentService`** gains:
- `suggestDependencies(goal)`: Auto-suggest dependencies based on goal type (e.g., emergency fund should precede investment goals)
- Validation: prevent circular dependencies

**`GoalAffordabilityService`** gains:
- When analysing affordability, account for `blocks` dependencies — a blocked goal's contribution should not be counted against surplus until the blocker is completed

#### Frontend Changes

- Add a "Dependencies" section in `GoalFormModal.vue` — dropdown to select prerequisite goals
- Show dependency chain in `GoalCard.vue` — "Blocked by: [Emergency Fund]" badge
- In `ModuleGoalStrategies.vue` — grey out blocked goals with explanation

### 5.7 Issue 7: Probability Approximation

**Problem:** Monte Carlo probability uses an analytical log-normal approximation rather than full simulation. The Investment module's `GoalProbabilityCalculator` has a more sophisticated implementation.

**Fix:**

1. Refactor `GoalRiskService` to use the Investment module's `GoalProbabilityCalculator` for probability calculations:

```php
// Instead of the analytical approximation:
// $probability = $this->calculateLogNormalProbability(...)

// Delegate to the investment module's calculator:
$calculator = app(GoalProbabilityCalculator::class);
$probability = $calculator->calculateProbability(
    currentAmount: $goal->current_amount,
    monthlyContribution: $goal->monthly_contribution,
    targetAmount: $goal->target_amount,
    yearsRemaining: $goal->months_remaining / 12,
    expectedReturn: $riskParams['expected_return'],
    volatility: $riskParams['volatility'],
    simulations: 1000  // configurable
);
```

2. If the `GoalProbabilityCalculator` is too tightly coupled to the Investment module, extract it into a shared service:

**New file:** `app/Services/Shared/MonteCarloSimulator.php`
- Move the simulation logic here
- Both `GoalRiskService` and `GoalProbabilityCalculator` delegate to this shared service
- Configurable simulation count (default 1,000; up to 10,000 for detailed analysis)

3. Add confidence interval output (10th, 25th, 50th, 75th, 90th percentiles) to the goal projections
4. Cache simulation results (they're computationally expensive) with the same 30-minute TTL as projections

### 5.8 Issue 8: Missing Schema in mysql-schema.sql

**Problem:** The `goals`, `goal_contributions`, and `life_events` tables are not in the schema dump.

**Fix:**

1. Regenerate the schema dump:
```bash
php artisan schema:dump
```

2. Verify the dump includes:
   - `goals` table (from migration `2026_01_24_160001`)
   - `goal_contributions` table (from migration `2026_01_24_160002`)
   - `life_events` table (from migration `2026_02_03_120001`)
   - Any new tables from this plan (`goal_dependencies`)

3. Add a CI check (or manual checklist item) to regenerate the schema dump after any new migration is created

---

## 6. Database Changes

### New Tables

| Table | Purpose | Migration |
|---|---|---|
| `goal_dependencies` | Goal prerequisite relationships | New migration |

### Modified Tables

| Table | Change | Migration |
|---|---|---|
| `goals` | Add `linked_investment_account_id` (nullable FK to `investment_accounts`) | New migration |
| `life_events` | Add `modules` JSON column (cached list of relevant modules for fast queries) | New migration |

### Schema Dump

Regenerate `mysql-schema.sql` after all migrations are applied.

---

## 7. Implementation Order

### Phase 1: Foundation (Backend Services)

| Step | Task | Dependencies |
|---|---|---|
| 1.1 | Create `LifeEventIntegrationService` with event-to-module mapping | None |
| 1.2 | Create `GoalStrategyService` | None |
| 1.3 | Fix Issue 2: Replace hardcoded tax in `GoalAffordabilityService` with `TaxConfigService` | None |
| 1.4 | Fix Issue 5: Add cash growth rate to `GoalsProjectionService` | None |
| 1.5 | Fix Issue 4: Replace linear mortgage reduction with amortisation | None |
| 1.6 | Fix Issue 8: Regenerate `mysql-schema.sql` | After all migrations |

### Phase 2: Cross-Module Backend Integration

| Step | Task | Dependencies |
|---|---|---|
| 2.1 | Add `life_events` and `goal_strategies` to `SavingsController` response | 1.1, 1.2 |
| 2.2 | Add `life_events` and `goal_strategies` to `InvestmentController` response | 1.1, 1.2 |
| 2.3 | Add `life_events` and `goal_strategies` to `RetirementController` response | 1.1, 1.2 |
| 2.4 | Add `life_events` to `ProtectionController` response | 1.1 |
| 2.5 | Add `life_events` to `EstateController` response | 1.1 |

### Phase 3: Estate & I/E Integration (Backend)

| Step | Task | Dependencies |
|---|---|---|
| 3.1 | Integrate life events into `IHTCalculationService` projections | 1.1 |
| 3.2 | Update `GiftingStrategyOptimizer` for gift life events | 1.1 |
| 3.3 | Add life events section to `ComprehensiveEstatePlanService` | 3.1 |
| 3.4 | Create `FinancialForecastService` for I&E integration | 1.1 |
| 3.5 | Update `GoalAffordabilityService` to account for upcoming life events | 3.4 |

### Phase 4: Frontend Components

| Step | Task | Dependencies |
|---|---|---|
| 4.1 | Build `ModuleLifeEvents.vue` shared component | 2.1-2.5 |
| 4.2 | Build `ModuleGoalStrategies.vue` shared component | 2.1-2.3 |
| 4.3 | Integrate both components into Cash/Savings view | 4.1, 4.2 |
| 4.4 | Integrate both components into Investment view | 4.1, 4.2 |
| 4.5 | Integrate both components into Retirement view | 4.1, 4.2 |
| 4.6 | Integrate life events into Protection view | 4.1 |
| 4.7 | Integrate life events into Estate view | 4.1 |
| 4.8 | Build `EstateLifeEventsImpact.vue` for estate-specific display | 3.1, 3.3 |
| 4.9 | Add forecast view to `IncomeStatementTab.vue` | 3.4 |

### Phase 5: Goal System Unification

| Step | Task | Dependencies |
|---|---|---|
| 5.1 | Create data migration from `savings_goals` to `goals` | None |
| 5.2 | Create data migration from `investment_goals` to `goals` | None |
| 5.3 | Update legacy endpoints to proxy to unified API | 5.1, 5.2 |
| 5.4 | Remove legacy goal components from Savings and Investment modules | 4.3, 4.4, 5.3 |
| 5.5 | Add deprecation notices to legacy endpoints | 5.3 |

### Phase 6: Advanced Features

| Step | Task | Dependencies |
|---|---|---|
| 6.1 | Create `goal_dependencies` table and model relationships | None |
| 6.2 | Add dependency UI to goal form and cards | 6.1 |
| 6.3 | Implement `SavingsAccountGoalObserver` for auto-contribution tracking | None |
| 6.4 | Extract shared `MonteCarloSimulator` service | None |
| 6.5 | Update `GoalRiskService` to use shared simulator | 6.4 |
| 6.6 | Add `linked_investment_account_id` to goals and observer | 6.3 |

### Phase 7: Testing & Seeder Updates

| Step | Task | Dependencies |
|---|---|---|
| 7.1 | Update `PreviewUserSeeder` with life events across all personas | All phases |
| 7.2 | Add goal dependencies to seeded data | 6.1 |
| 7.3 | Add linked account goals to seeded data | 6.3, 6.6 |
| 7.4 | Write unit tests for all new services | All phases |
| 7.5 | Write feature tests for cross-module API responses | All phases |
| 7.6 | Regenerate `mysql-schema.sql` | All migrations |
