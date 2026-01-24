# Goals Module - Comprehensive Mapping

## Overview

The Goals module provides a unified goal-planning system across all financial modules (Savings, Investment, Property, Retirement). Users create financial goals with targets, timelines, and contributions. The system automatically assigns goals to the appropriate module, tracks progress via contributions and milestones, calculates projections using Monte Carlo simulations, and provides affordability analysis.

---

## 1. Database Schema

### `goals` Table

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `user_id` | bigint FK → users | Goal owner |
| `goal_name` | varchar(100) | User-readable name |
| `goal_type` | enum | One of 11 types (see Goal Types below) |
| `custom_goal_type_name` | varchar(100) nullable | Name when type='custom' |
| `description` | text nullable | User description |
| `target_amount` | decimal(15,2) | Goal target in £ |
| `current_amount` | decimal(15,2) default 0 | Current progress |
| `target_date` | date | When goal should be achieved |
| `start_date` | date nullable | When tracking began |
| `assigned_module` | enum: savings, investment, property, retirement | Which module manages this goal |
| `module_override` | boolean default false | User manually chose module |
| `priority` | enum: critical, high, medium, low | Goal priority |
| `is_essential` | boolean default false | Essential goal flag |
| `status` | enum: active, paused, completed, abandoned | Current status |
| `monthly_contribution` | decimal(12,2) nullable | Target monthly amount |
| `contribution_frequency` | enum: weekly, monthly, quarterly, annually | How often contributions are made |
| `contribution_streak` | unsigned int default 0 | Current consecutive contributions |
| `longest_streak` | unsigned int default 0 | Best ever streak |
| `last_contribution_date` | date nullable | Most recent contribution |
| `linked_account_ids` | json nullable | Array of linked account IDs |
| `linked_savings_account_id` | FK → savings_accounts nullable | Linked savings account |
| `risk_preference` | tinyint(1-5) nullable | Goal-specific risk level |
| `use_global_risk_profile` | boolean default true | Use global risk or goal-specific |
| `ownership_type` | enum: individual, joint | Ownership |
| `joint_owner_id` | FK → users nullable | Joint owner |
| `ownership_percentage` | decimal(5,2) default 100 | Owner's share |
| `property_location` | varchar(255) nullable | Property goal: location |
| `property_type` | enum nullable | house, flat, bungalow, terraced, semi_detached, detached, other |
| `is_first_time_buyer` | boolean nullable | For SDLT relief |
| `estimated_property_price` | decimal(15,2) nullable | Property price estimate |
| `deposit_percentage` | decimal(5,2) nullable | Deposit % target |
| `stamp_duty_estimate` | decimal(12,2) nullable | Calculated SDLT |
| `additional_costs_estimate` | decimal(12,2) nullable | Legal, survey, moving |
| `milestones` | json nullable | Progress milestones at 25/50/75/100% |
| `projection_data` | json nullable | Cached projection calculations |
| `completed_at` | timestamp nullable | When goal was completed |
| `completion_notes` | text nullable | User notes on completion |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp nullable | Soft delete |

**Indexes:** `(user_id, status)`, `(user_id, assigned_module)`, `(user_id, goal_type)`

### `goal_contributions` Table

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `goal_id` | FK → goals (cascade) | Which goal |
| `user_id` | FK → users (cascade) | Who contributed |
| `amount` | decimal(12,2) | Contribution amount |
| `contribution_date` | date | When contributed |
| `contribution_type` | enum: manual, automatic, lump_sum, interest, adjustment | Type |
| `notes` | text nullable | User notes |
| `goal_balance_after` | decimal(15,2) | Balance after this contribution |
| `streak_qualifying` | boolean default true | Counts toward streak (≥80% of target) |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:** `(goal_id, contribution_date)`, `(user_id, contribution_date)`

---

## 2. Goal Types & Module Assignment

### 11 Goal Types

| Goal Type | Icon | Default Module | Assignment Logic |
|-----------|------|----------------|-----------------|
| `emergency_fund` | shield | savings | Always savings |
| `property_purchase` | home | property | Always property |
| `home_deposit` | key | property | Always property |
| `education` | academic-cap | conditional | Time horizon + amount |
| `retirement` | sun | retirement | Always retirement |
| `wealth_accumulation` | trending-up | investment | Always investment |
| `wedding` | heart | conditional | Time horizon + amount |
| `holiday` | globe | conditional | Time horizon + amount |
| `car_purchase` | truck | conditional | Time horizon + amount |
| `debt_repayment` | credit-card | savings | Always savings |
| `custom` | star | conditional | Time horizon + amount |

### Module Assignment Logic (`GoalAssignmentService::determineModule`)

```
1. Check goal_type for explicit module mapping (above)
2. If conditional:
   a. Time horizon ≤ 3 years → savings
   b. Time horizon > 3 years AND target ≥ £5,000 → investment
   c. Default → savings
```

Users can override with `module_override = true`.

---

## 3. API Endpoints

**Route prefix:** `/api/goals` (auth:sanctum middleware)

| Endpoint | Method | Controller | Purpose |
|----------|--------|-----------|---------|
| `/goals` | GET | `GoalsController::index()` | List goals (filterable by module, status, priority) |
| `/goals/analysis` | GET | `GoalsController::analysis()` | Full analysis with recommendations |
| `/goals/dashboard-overview` | GET | `GoalsController::dashboardOverview()` | Dashboard card data |
| `/goals/types` | GET | `GoalsController::getGoalTypes()` | Available goal types |
| `/goals/risk-levels` | GET | `GoalsController::getRiskLevels()` | Risk levels with returns |
| `/goals/calculate-property-costs` | POST | `GoalsController::calculatePropertyCosts()` | SDLT + costs calculator |
| `/goals` | POST | `GoalsController::store()` | Create goal |
| `/goals/{id}` | GET | `GoalsController::show()` | Get goal detail with progress |
| `/goals/{id}` | PUT | `GoalsController::update()` | Update goal |
| `/goals/{id}` | DELETE | `GoalsController::destroy()` | Soft delete goal |
| `/goals/{id}/contribution` | POST | `GoalsController::recordContribution()` | Record contribution |
| `/goals/{id}/projections` | GET | `GoalsController::getProjections()` | Get projections |
| `/goals/{id}/scenarios` | GET | `GoalsController::getScenarios()` | What-if scenarios |
| `/goals/{id}/contributions` | GET | `GoalsController::getContributionHistory()` | Contribution history |

### Investment Goal Progress Routes

**Route prefix:** `/api/investment/goals`

| Endpoint | Method | Controller | Purpose |
|----------|--------|-----------|---------|
| `/goals/{id}/progress` | GET | `GoalProgressController::analyzeGoalProgress()` | Progress analysis with probability |
| `/goals/progress/all` | GET | `GoalProgressController::analyzeAllGoals()` | All goals progress summary |
| `/goals/{id}/shortfall` | GET | `GoalProgressController::analyzeShortfall()` | Shortfall + mitigation strategies |
| `/goals/{id}/what-if` | POST | `GoalProgressController::generateWhatIfScenarios()` | Custom scenarios |
| `/goals/calculate-probability` | POST | `GoalProgressController::calculateProbability()` | Monte Carlo probability |
| `/goals/required-contribution` | POST | `GoalProgressController::calculateRequiredContribution()` | Required contribution for target probability |
| `/goals/glide-path` | GET | `GoalProgressController::getGlidePath()` | Asset allocation recommendation |

---

## 4. Controllers

### GoalsController

**Path:** `app/Http/Controllers/Api/GoalsController.php`

**Dependencies:**
- `GoalsAgent` — analysis and recommendations
- `GoalAssignmentService` — module assignment and property costs
- `GoalAffordabilityService` — affordability checks
- `GoalProgressService` — contribution tracking and milestones
- `GoalRiskService` — projections and risk parameters

### GoalProgressController

**Path:** `app/Http/Controllers/Api/Investment/GoalProgressController.php`

**Dependencies:**
- `GoalProgressAnalyzer` — progress analysis
- `ShortfallAnalyzer` — shortfall detection
- `GoalProbabilityCalculator` — Monte Carlo simulations

---

## 5. Services

### GoalsAgent (`app/Agents/GoalsAgent.php`)

Orchestrator for the goals module. Extends `BaseAgent` with caching.

| Method | Purpose |
|--------|---------|
| `analyze(userId)` | Full analysis: summary, by-module breakdown, top goals, affordability, streaks |
| `generateRecommendations(analysis)` | Up to 5 prioritised recommendations |
| `buildScenarios(userId, params)` | 4 what-if scenarios per goal |
| `getDashboardOverview(userId)` | Top 5 goals, on-track count, progress, streaks |
| `clearCache(userId)` | Invalidate all cached data |

### GoalProgressService (`app/Services/Goals/GoalProgressService.php`)

Handles contribution tracking, streaks, and milestones.

| Method | Purpose |
|--------|---------|
| `calculateProgress(goal)` | Progress %, expected progress, delta, status |
| `recordContribution(goal, amount, type, notes)` | Create contribution, update balance, streak, milestones |
| `checkMilestones(goal)` | Check/record 25/50/75/100% milestones |
| `getContributionHistory(goal, limit)` | Last N contributions |
| `getMonthlySummary(goal, months)` | Monthly breakdown |
| `completeGoal(goal, notes)` | Mark completed |
| `getStreakDisplay(goal)` | Streak with intensity label |

**Streak Logic:**
- Qualifies if contribution ≥ 80% of monthly_contribution
- Grace periods: weekly (10d), monthly (35d), quarterly (95d), annual (370d)
- Intensity: blazing (≥12), hot (≥6), warm (≥3), starting (≥1), cold (0)

### GoalRiskService (`app/Services/Goals/GoalRiskService.php`)

Handles risk parameters and projections.

| Risk Level | Label | Expected Return | Volatility |
|------------|-------|-----------------|------------|
| 1 | Conservative | 3% | 5% |
| 2 | Cautious | 4.5% | 8% |
| 3 | Balanced | 6% | 12% |
| 4 | Growth | 7.5% | 16% |
| 5 | Aggressive | 9% | 20% |

| Method | Purpose |
|--------|---------|
| `getRiskParameters(goal, globalProfile)` | Get risk level, return, volatility |
| `getProjections(goal, riskProfile)` | Full projections with yearly breakdown |
| `getUserRiskProfile(user)` | Fetch user's RiskProfile |
| `getAvailableRiskLevels()` | All 5 levels with descriptions |

**Risk Selection Logic:**
```
if use_global_risk_profile AND global profile exists:
    use global risk_level (mapped 1-5)
else if goal.risk_preference set:
    use goal.risk_preference
else:
    default to 3 (Balanced)
```

### GoalAffordabilityService (`app/Services/Goals/GoalAffordabilityService.php`)

Calculates whether goals are financially achievable.

| Method | Purpose |
|--------|---------|
| `analyzeAffordability(goal, user)` | Single goal affordability assessment |
| `analyzeAllGoals(user)` | All goals combined affordability |
| `calculateMonthlySurplus(user)` | Net monthly surplus after tax + expenses |

**Affordability Categories:**
| Category | Ratio | Colour | Meaning |
|----------|-------|--------|---------|
| unaffordable | surplus ≤ 0 | red | No surplus to contribute |
| completed | required ≤ 0 | green | Already achieved |
| comfortable | ratio ≤ 0.3 | green | Well within means |
| moderate | ratio ≤ 0.5 | blue | Manageable |
| challenging | ratio ≤ 0.75 | yellow | Tight but possible |
| stretch | ratio ≤ 1.0 | orange | Using all surplus |
| overcommitted | ratio > 1.0 | red | Exceeds available surplus |

### GoalAssignmentService (`app/Services/Goals/GoalAssignmentService.php`)

Module assignment and property cost calculations.

| Method | Purpose |
|--------|---------|
| `determineModule(goalData)` | Auto-assign savings/investment/property/retirement |
| `calculatePropertyCosts(goalData)` | SDLT + legal + survey + moving costs |
| `getRecommendedAllocation(goalData)` | Glide path asset allocation |
| `getGoalTypes()` | All 11 types with icons |

**Glide Path (Asset Allocation by Time Horizon):**
| Years to Goal | Equities | Bonds | Cash | Strategy |
|---------------|----------|-------|------|----------|
| >15 | 80% | 15% | 5% | Aggressive |
| >10 | 70% | 25% | 5% | Growth |
| >5 | 50% | 40% | 10% | Balanced |
| >3 | 30% | 50% | 20% | Cautious |
| >1 | 15% | 45% | 40% | Defensive |
| ≤1 | 0% | 20% | 80% | Cash |

### GoalProbabilityCalculator (`app/Services/Investment/Goals/GoalProbabilityCalculator.php`)

Monte Carlo simulations for goal success probability.

| Method | Purpose |
|--------|---------|
| `calculateGoalProbability(...)` | Run 1000 iterations, return probability + percentiles |
| `calculateRequiredContribution(...)` | Binary search for contribution needed at target probability |
| `calculateGlidePath(years, equity%)` | Recommended allocation + rebalance trigger |

---

## 6. Model

**File:** `app/Models/Goal.php`

**Relationships:**
```
belongsTo(User::class)                          // Owner
belongsTo(User::class, 'joint_owner_id')       // Joint owner
belongsTo(SavingsAccount::class, 'linked_savings_account_id')
hasMany(GoalContribution::class)               // Contribution history
```

**Computed Attributes (appended):**
| Attribute | Calculation |
|-----------|-------------|
| `progress_percentage` | (current_amount / target_amount) × 100, max 100 |
| `days_remaining` | today → target_date, min 0 |
| `months_remaining` | today → target_date in months, rounded up, min 0 |
| `is_on_track` | progress ≥ (expected_progress - 10%) |
| `display_goal_type` | Human-readable type name |
| `amount_remaining` | max(0, target - current) |
| `required_monthly_contribution` | amount_remaining / months_remaining |
| `current_milestone` | 25/50/75/100 or null |
| `next_milestone` | Next milestone or null |

**Scopes:**
- `active()` — WHERE status='active'
- `completed()` — WHERE status='completed'
- `forModule(module)` — WHERE assigned_module=module
- `byPriority(priority)` — WHERE priority=priority
- `onTrack()` — Active goals where is_on_track=true

---

## 7. Frontend

### Vue Components (`resources/js/components/Goals/`)

| Component | Purpose |
|-----------|---------|
| `GoalFormModal.vue` | Create/edit goal form with conditional fields |
| `GoalCard.vue` | Single goal summary card with progress bar |
| `GoalProgressBar.vue` | Visual progress indicator |
| `GoalMilestoneTracker.vue` | 25/50/75/100% milestone display |
| `GoalCountdown.vue` | Days/months remaining display |
| `GoalContributionStreak.vue` | Streak visualisation with intensity |
| `GoalsAnalysis.vue` | Full analysis dashboard |
| `GoalsByModule.vue` | Goals grouped by module |
| `GoalsList.vue` | Filterable list of all goals |
| `GoalsOverview.vue` | Dashboard overview card |
| `ContributionModal.vue` | Record contribution modal |

### Frontend Service (`resources/js/services/goalsService.js`)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `getGoals(filters)` | GET /goals | List with filters |
| `getAnalysis()` | GET /goals/analysis | Full analysis |
| `getDashboardOverview()` | GET /goals/dashboard-overview | Dashboard data |
| `getGoalTypes()` | GET /goals/types | Available types |
| `getRiskLevels()` | GET /goals/risk-levels | Risk levels |
| `createGoal(data)` | POST /goals | Create |
| `getGoal(id)` | GET /goals/{id} | Get detail |
| `updateGoal(id, data)` | PUT /goals/{id} | Update |
| `deleteGoal(id)` | DELETE /goals/{id} | Delete |
| `recordContribution(id, data)` | POST /goals/{id}/contribution | Record contribution |
| `getProjections(id)` | GET /goals/{id}/projections | Projections |
| `getScenarios(id)` | GET /goals/{id}/scenarios | What-if scenarios |
| `getContributionHistory(id, limit)` | GET /goals/{id}/contributions | History |
| `calculatePropertyCosts(data)` | POST /goals/calculate-property-costs | Property costs |

### Vuex Store (`resources/js/store/modules/goals.js`)

**State:**
```javascript
goals: []                    // All goals
summary: {}                  // Aggregate stats
topGoals: []                 // Top 5 by priority/progress
byModule: { savings, investment, property, retirement }
bestStreak: 0                // Current best streak
analysis: null               // Full analysis data
recommendations: []          // Generated recommendations
goalTypes: []                // Available types
riskLevels: []               // Available risk levels
dashboardOverview: null      // Dashboard card data
selectedGoal: null           // Currently viewed goal
loading: false
error: null
```

**Key Getters:**
- `activeGoals` — status='active'
- `goalsForModule(module)` — by assigned_module
- `goalsOnTrack` — active AND is_on_track
- `goalsBehind` — active AND !is_on_track
- `totalTargetAmount` — sum of targets
- `overallProgress` — (totalCurrent / totalTarget) × 100
- `priorityGoals` — critical OR high priority
- `dashboardData` — overview or defaults

---

## 8. Progress Tracking

### On-Track Calculation

```
days_elapsed = start_date → today
total_days = start_date → target_date
expected_progress = (days_elapsed / total_days) × 100
progress_delta = progress_percentage - expected_progress

Status:
  delta ≥ +10%  → "ahead"
  delta ≥ -5%   → "on_track"
  delta ≥ -15%  → "slightly_behind"
  delta < -15%  → "behind"

is_on_track = delta ≥ -10%  (10% tolerance margin)
```

### Contribution Streaks

- **Qualifies** if amount ≥ 80% of monthly_contribution target
- **Grace periods** by frequency:
  - Weekly: 10 days
  - Monthly: 35 days
  - Quarterly: 95 days
  - Annually: 370 days
- **Intensity labels:**
  - ≥12 contributions: "blazing"
  - ≥6: "hot"
  - ≥3: "warm"
  - ≥1: "starting"
  - 0: "cold"

### Milestones

Tracked at 25%, 50%, 75%, 100%. Stored in JSON:
```json
{
  "milestone_25": { "reached": true, "reached_at": "2026-03-15T10:00:00Z", "amount_at_milestone": 2500 },
  "milestone_50": { "reached": true, "reached_at": "2026-06-20T14:30:00Z", "amount_at_milestone": 5100 }
}
```

---

## 9. Projections

### Deterministic (GoalRiskService)

```
monthly_return = annual_return / 12
months = months_remaining

FV_lumpsum = current_amount × (1 + monthly_return)^months
FV_contributions = monthly_contribution × [((1 + monthly_return)^months - 1) / monthly_return]
expected_final_value = FV_lumpsum + FV_contributions
```

### Monte Carlo (GoalProbabilityCalculator)

- **Iterations:** 1000
- **Method:** Box-Muller transform for normal random returns
- **Per iteration, per month:**
  ```
  random_return = N(expected_return/12, volatility/√12)
  value = value × (1 + random_return) + monthly_contribution
  ```
- **Output:** probability (% reaching target), p10, p25, p50, p75, p90

### Confidence Bounds (95%)

```
Lower = expected_value × exp(-1.96 × volatility × √year)
Upper = expected_value × exp(1.96 × volatility × √year)
```

### Probability Interpretation

| Probability | Confidence |
|-------------|-----------|
| ≥90% | Very High |
| ≥75% | High |
| ≥60% | Moderate |
| ≥40% | Low |
| <40% | Very Low |

---

## 10. Cross-Module Integration

### → Investment Module
- Long-term goals (>3 years, ≥£5,000) auto-assigned to investment
- Monte Carlo projections use investment risk parameters
- Glide path recommends allocation based on years-to-goal
- Goals can link to specific investment accounts via `linked_account_ids`

### → Savings Module
- Short-term goals (≤3 years) or <£5,000 auto-assigned to savings
- Goals link to savings accounts via `linked_savings_account_id`
- Contribution tracking for regular savings goals

### → Property Module
- `property_purchase` and `home_deposit` types auto-assigned
- Calculates SDLT (with first-time buyer relief ≤£625k), legal fees, survey, moving costs
- Deposit % and property price tracking

### → Retirement Module
- `retirement` type auto-assigned
- Can track retirement savings across pension accounts

### → Risk Module
- Goals can use global risk profile (`use_global_risk_profile = true`)
- Or override with goal-specific risk (1-5 scale)
- Risk parameters drive projection return/volatility assumptions

### → User Profile (Affordability)
- Monthly surplus = (Gross income / 12) - estimated tax - expenditure
- Expenditure from user fields: mortgage_rent, utilities, council_tax, insurance, transport, food, childcare, entertainment, other, debt_repayments
- Affordability ratio determines if goal is achievable

### → Dashboard
- `getDashboardOverview()` provides top 5 goals, on-track count, overall progress, best streak
- Refreshed on goal CRUD and contribution events

---

## 11. What-If Scenarios

Generated by `GoalsAgent::buildScenarios()`:

| Scenario | Calculation |
|----------|-------------|
| Increase contribution 20% | monthly × 1.2, recalculate timeline |
| Reach goal 6 months earlier | new target_date, required monthly |
| Reduce target 20% | target × 0.8, recalculate progress |
| Add £1,000 lump sum | current + 1000, recalculate timeline |

---

## 12. Cache & Performance

- `GoalsAgent` extends `BaseAgent` with `rememberForUser($userId, $key, $callback)` caching
- `GoalProgressController` caches progress analysis for 30 minutes per goal
- Cache cleared on: goal create, update, delete, contribution recorded
- Dashboard overview fetched independently (lightweight query)

---

## 13. Key Files Summary

| File | Purpose |
|------|---------|
| `app/Models/Goal.php` | Goal model with computed attributes |
| `app/Models/GoalContribution.php` | Contribution tracking model |
| `app/Http/Controllers/Api/GoalsController.php` | Main goals API |
| `app/Http/Controllers/Api/Investment/GoalProgressController.php` | Investment goal progress API |
| `app/Agents/GoalsAgent.php` | Analysis orchestrator |
| `app/Services/Goals/GoalProgressService.php` | Contributions, streaks, milestones |
| `app/Services/Goals/GoalRiskService.php` | Risk parameters and projections |
| `app/Services/Goals/GoalAffordabilityService.php` | Affordability analysis |
| `app/Services/Goals/GoalAssignmentService.php` | Module assignment, property costs |
| `app/Services/Investment/Goals/GoalProgressAnalyzer.php` | Investment goal progress analysis |
| `app/Services/Investment/Goals/GoalProbabilityCalculator.php` | Monte Carlo simulations |
| `app/Services/Investment/Goals/ShortfallAnalyzer.php` | Shortfall detection + mitigation |
| `resources/js/services/goalsService.js` | Frontend API service |
| `resources/js/store/modules/goals.js` | Vuex store |
| `resources/js/components/Goals/GoalFormModal.vue` | Goal form |
| `resources/js/components/Goals/GoalCard.vue` | Goal display card |
| `resources/js/components/Goals/GoalsAnalysis.vue` | Analysis dashboard |
| `resources/js/components/Goals/ContributionModal.vue` | Contribution form |
| `database/migrations/*_create_goals_table.php` | Goals schema |
| `database/migrations/*_create_goal_contributions_table.php` | Contributions schema |

---

## Bug Fixes (24 Jan 2026)

### Modal z-stacking — All goal modals unclickable

**Root Cause:** The backdrop overlay (`fixed inset-0`) created a stacking context above the modal panel (`inline-block`), intercepting all button clicks. The modal appeared functional (fields responded to input, auto-assignment calculated) but "Create Goal", "Record Contribution", and "Delete" buttons never fired their handlers.

**Fix:** Added `relative z-10` to the modal panel `<div>` in:
- `GoalFormModal.vue`
- `ContributionModal.vue`
- `GoalsDashboard.vue` (delete confirmation modal)

Also changed the Create Goal button to `type="submit"` and added inline validation error messages (previously failed silently when required fields were empty).
