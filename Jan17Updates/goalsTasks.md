# Goals-Based Planning Module - Implementation Task List

This is a detailed step-by-step task list for implementing the Goals-Based Planning Module. Each task must be completed and tested before moving to the next.

---

## Pre-Implementation Setup

- [ ] **TASK 0.1**: Create and switch to `goals` branch
  ```bash
  git checkout -b goals
  ```

- [ ] **TASK 0.2**: Verify development environment is running
  ```bash
  ./dev.sh
  ```

---

## Phase 1: Database & Core Backend

### 1.1 Create Goals Table Migration

- [ ] **TASK 1.1.1**: Create migration file `2026_01_18_000001_create_goals_table.php`
  - Location: `database/migrations/`
  - Include all fields from schema:
    - Basic: id, user_id, goal_name, goal_type, custom_goal_type_name, description
    - Financial: target_amount, current_amount, target_date, start_date
    - Module: assigned_module, module_override
    - Priority: priority, is_essential
    - Contribution: monthly_contribution, contribution_frequency, contribution_streak, longest_streak, last_contribution_date
    - Links: linked_account_ids (JSON), linked_savings_account_id (FK)
    - Risk: risk_preference, use_global_risk_profile
    - Joint: ownership_type, joint_owner_id, ownership_percentage
    - Property: property_location, property_type, is_first_time_buyer, estimated_property_price, deposit_percentage, stamp_duty_estimate, additional_costs_estimate
    - Status: status, milestones (JSON), projection_data (JSON), completed_at, completion_notes
    - Timestamps and soft deletes
  - Add indexes: user_id, (user_id, status), (user_id, assigned_module), (user_id, target_date)

- [ ] **TASK 1.1.2**: Test migration runs successfully
  ```bash
  php artisan migrate
  ```

- [ ] **TASK 1.1.3**: Test migration rollback works
  ```bash
  php artisan migrate:rollback --step=1
  php artisan migrate
  ```

### 1.2 Create Goal Contributions Table Migration

- [ ] **TASK 1.2.1**: Create migration file `2026_01_18_000002_create_goal_contributions_table.php`
  - Fields: id, goal_id (FK), user_id (FK), amount, type (enum), contribution_date, notes, source_account_type, source_account_id, timestamps
  - Add indexes: (goal_id, contribution_date), (user_id, contribution_date)

- [ ] **TASK 1.2.2**: Test migration runs successfully
  ```bash
  php artisan migrate
  ```

### 1.3 Create Goal Model

- [ ] **TASK 1.3.1**: Create `app/Models/Goal.php`
  - Add `declare(strict_types=1);`
  - Define fillable fields
  - Define casts (decimal, date, boolean, array for JSON fields)
  - Add relationships:
    - `user()` - BelongsTo User
    - `jointOwner()` - BelongsTo User (nullable)
    - `linkedSavingsAccount()` - BelongsTo SavingsAccount (nullable)
    - `contributions()` - HasMany GoalContribution
  - Add scope methods:
    - `scopeActive($query)`
    - `scopeForModule($query, $module)`
    - `scopeForUser($query, $userId)`

- [ ] **TASK 1.3.2**: Create `app/Models/GoalContribution.php`
  - Define fillable fields
  - Define casts
  - Add relationships: `goal()`, `user()`

- [ ] **TASK 1.3.3**: Test models can be instantiated
  ```bash
  php artisan tinker
  # new App\Models\Goal(['goal_name' => 'Test']);
  ```

### 1.4 Data Migration Script

- [ ] **TASK 1.4.1**: Create migration `2026_01_18_000003_migrate_existing_goals.php`
  - Migrate `savings_goals` records to `goals` table with assigned_module = 'savings'
  - Migrate `investment_goals` records to `goals` table with assigned_module = 'investment'
  - Preserve all existing data
  - Map fields correctly (current_saved → current_amount, etc.)

- [ ] **TASK 1.4.2**: Test migration preserves data correctly
  ```bash
  php artisan migrate
  # Verify data in goals table matches source tables
  ```

- [ ] **TASK 1.4.3**: Test existing functionality still works after migration
  - Check Savings module loads goals
  - Check Investment module loads goals

---

## Phase 2: Service Layer

### 2.1 Goal Assignment Service

- [ ] **TASK 2.1.1**: Create `app/Services/Goals/GoalAssignmentService.php`
  - Inject TaxConfigService
  - Implement `determineModule(Goal $goal): string`
    - Check module_override first
    - Check goal_type for property/retirement/emergency_fund
    - Check time horizon (≤3 years → savings, >3 years → investment)
    - Check target amount (≥£5,000 for investment)
  - Implement `calculatePropertyCosts(Goal $goal): array`
    - Calculate deposit amount
    - Calculate stamp duty using TaxConfigService
    - Calculate additional costs (~3%)
  - Implement `getRecommendedAllocation(Goal $goal): array`
    - Return glide path based on time horizon

- [ ] **TASK 2.1.2**: Write unit tests for GoalAssignmentService
  - Location: `tests/Unit/Services/Goals/GoalAssignmentServiceTest.php`
  - Test determineModule with various goal types
  - Test time horizon logic (≤3 years vs >3 years)
  - Test property cost calculations
  - Test first-time buyer stamp duty relief

- [ ] **TASK 2.1.3**: Run tests
  ```bash
  ./vendor/bin/pest tests/Unit/Services/Goals/GoalAssignmentServiceTest.php
  ```

### 2.2 Goal Affordability Service

- [ ] **TASK 2.2.1**: Create `app/Services/Goals/GoalAffordabilityService.php`
  - Implement `analyzeAffordability(Goal $goal, User $user): array`
  - Implement `calculateMonthlySurplus(User $user): float`
  - Implement `calculateRequiredContribution(Goal $goal): array`
    - Return minimum, recommended (×1.15), aggressive
  - Implement `categorizeAffordability(float $ratio): string`
    - comfortable (<15%), moderate (15-30%), challenging (30-50%), stretch (>50%)
  - Implement `generateAffordabilityRecommendations(): array`

- [ ] **TASK 2.2.2**: Write unit tests for GoalAffordabilityService
  - Location: `tests/Unit/Services/Goals/GoalAffordabilityServiceTest.php`
  - Test surplus calculation
  - Test required contribution calculation
  - Test affordability categorization
  - Test recommendations generation

- [ ] **TASK 2.2.3**: Run tests
  ```bash
  ./vendor/bin/pest tests/Unit/Services/Goals/GoalAffordabilityServiceTest.php
  ```

### 2.3 Goal Progress Service

- [ ] **TASK 2.3.1**: Create `app/Services/Goals/GoalProgressService.php`
  - Implement `calculateProgress(Goal $goal): array`
    - Return progress_percent, on_track, status, time_remaining
  - Implement `updateContributionStreak(Goal $goal, Carbon $contributionDate): void`
    - Increment streak if contribution within expected period
    - Reset streak if period missed
    - Update longest_streak if current > longest
  - Implement `checkMilestones(Goal $goal): array`
    - Check 25%, 50%, 75%, 100% milestones
    - Return newly reached milestones

- [ ] **TASK 2.3.2**: Write unit tests for GoalProgressService
  - Location: `tests/Unit/Services/Goals/GoalProgressServiceTest.php`
  - Test progress calculation
  - Test streak increment logic
  - Test streak reset logic
  - Test milestone detection

- [ ] **TASK 2.3.3**: Run tests
  ```bash
  ./vendor/bin/pest tests/Unit/Services/Goals/GoalProgressServiceTest.php
  ```

### 2.4 Goal Risk Service

- [ ] **TASK 2.4.1**: Create `app/Services/Goals/GoalRiskService.php`
  - Inject GoalProbabilityCalculator (existing)
  - Implement `getProjections(Goal $goal, ?RiskProfile $riskProfile): array`
  - Implement `getRiskParameters(Goal $goal, ?RiskProfile $riskProfile): array`
  - Implement `getParametersForPreference(string $preference): array`
    - conservative: 4% return, 8% volatility
    - balanced: 7% return, 12% volatility
    - growth: 9% return, 16% volatility
    - aggressive: 11% return, 20% volatility

- [ ] **TASK 2.4.2**: Write unit tests for GoalRiskService
  - Location: `tests/Unit/Services/Goals/GoalRiskServiceTest.php`
  - Test risk parameter mapping
  - Test projection calculations
  - Test integration with existing GoalProbabilityCalculator

- [ ] **TASK 2.4.3**: Run tests
  ```bash
  ./vendor/bin/pest tests/Unit/Services/Goals/GoalRiskServiceTest.php
  ```

---

## Phase 3: GoalsAgent & API

### 3.1 Goals Agent

- [ ] **TASK 3.1.1**: Create `app/Agents/GoalsAgent.php`
  - Extend BaseAgent
  - Set cacheTtl = 900 (15 minutes)
  - Inject all goal services
  - Implement `analyze(int $userId): array`
    - Return summary, goals analysis, by_module breakdown, streaks, milestones
  - Implement `generateRecommendations(array $analysisData): array`
  - Implement `buildScenarios(int $userId, array $parameters): array`

- [ ] **TASK 3.1.2**: Write unit tests for GoalsAgent
  - Location: `tests/Unit/Agents/GoalsAgentTest.php`
  - Test analyze returns correct structure
  - Test recommendations generation
  - Test caching behavior

- [ ] **TASK 3.1.3**: Run tests
  ```bash
  ./vendor/bin/pest tests/Unit/Agents/GoalsAgentTest.php
  ```

### 3.2 API Request Validation

- [ ] **TASK 3.2.1**: Create `app/Http/Requests/Goals/StoreGoalRequest.php`
  - Validate: goal_name (required, max:255)
  - Validate: goal_type (required, enum or 'custom')
  - Validate: custom_goal_type_name (required_if goal_type is custom)
  - Validate: target_amount (required, numeric, min:1)
  - Validate: target_date (required, date, after:today)
  - Validate: priority (enum)
  - Validate: ownership_type, joint_owner_id (required_if joint)
  - Validate property fields if property goal

- [ ] **TASK 3.2.2**: Create `app/Http/Requests/Goals/UpdateGoalRequest.php`
  - Similar validation but fields are optional
  - Add authorization check (user owns goal)

### 3.3 Goals Controller

- [ ] **TASK 3.3.1**: Create `app/Http/Controllers/Api/GoalsController.php`
  - Inject GoalsAgent, GoalAssignmentService
  - Implement `index(Request $request)` - list goals with filters
  - Implement `store(StoreGoalRequest $request)` - create goal
  - Implement `show(int $id)` - get single goal
  - Implement `update(UpdateGoalRequest $request, int $id)` - update goal
  - Implement `destroy(int $id)` - soft delete goal
  - Implement `analysis(Request $request)` - full analysis
  - Implement `dashboardOverview(Request $request)` - dashboard summary
  - Implement `recordContribution(Request $request, int $id)` - add contribution
  - Implement `getProjections(int $id)` - get Monte Carlo projections
  - Implement `calculatePropertyCosts(Request $request)` - property calculator

- [ ] **TASK 3.3.2**: Add routes to `routes/api.php`
  ```php
  Route::middleware('auth:sanctum')->prefix('goals')->group(function () {
      Route::get('/', [GoalsController::class, 'index']);
      Route::get('/analysis', [GoalsController::class, 'analysis']);
      Route::get('/dashboard-overview', [GoalsController::class, 'dashboardOverview']);
      Route::post('/', [GoalsController::class, 'store']);
      Route::get('/{id}', [GoalsController::class, 'show']);
      Route::put('/{id}', [GoalsController::class, 'update']);
      Route::delete('/{id}', [GoalsController::class, 'destroy']);
      Route::post('/{id}/contribution', [GoalsController::class, 'recordContribution']);
      Route::get('/{id}/projections', [GoalsController::class, 'getProjections']);
      Route::post('/calculate-property-costs', [GoalsController::class, 'calculatePropertyCosts']);
  });
  ```

- [ ] **TASK 3.3.3**: Write feature tests for API endpoints
  - Location: `tests/Feature/Api/GoalsControllerTest.php`
  - Test CRUD operations
  - Test authorization (user can only access own goals)
  - Test joint goal creation
  - Test module assignment
  - Test contribution recording
  - Test dashboard overview

- [ ] **TASK 3.3.4**: Run feature tests
  ```bash
  ./vendor/bin/pest tests/Feature/Api/GoalsControllerTest.php
  ```

- [ ] **TASK 3.3.5**: Test API manually with curl
  ```bash
  # Login as preview user
  curl -X POST "http://localhost:8000/api/preview/login/young_family" -H "Accept: application/json"

  # Create a goal
  curl -X POST "http://localhost:8000/api/goals" \
    -H "Authorization: Bearer TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"goal_name":"Emergency Fund","goal_type":"emergency_fund","target_amount":10000,"target_date":"2027-01-01"}'

  # List goals
  curl "http://localhost:8000/api/goals" -H "Authorization: Bearer TOKEN"
  ```

---

## Phase 4: Frontend Core

### 4.1 Vuex Store Module

- [ ] **TASK 4.1.1**: Create `resources/js/store/modules/goals.js`
  - State: goals, summary, topGoals, byModule, bestStreak, analysis, loading, error
  - Getters: onTrackPercentage, savingsGoals, investmentGoals, propertyGoals, goalsForModule, goalById, activeGoals, criticalGoals
  - Actions: fetchGoals, fetchDashboardOverview, fetchAnalysis, createGoal, updateGoal, deleteGoal, recordContribution
  - Mutations: setGoals, setDashboardOverview, setAnalysis, addGoal, updateGoal, removeGoal, setLoading, setError

- [ ] **TASK 4.1.2**: Register store module in `resources/js/store/index.js`
  ```javascript
  import goals from './modules/goals';
  // Add to modules object
  ```

- [ ] **TASK 4.1.3**: Test store module loads correctly in browser console

### 4.2 API Service

- [ ] **TASK 4.2.1**: Create `resources/js/services/goalsService.js`
  - Implement all API methods matching controller endpoints
  - Use axios with proper error handling

### 4.3 Core Components

- [ ] **TASK 4.3.1**: Create `resources/js/components/Goals/GoalProgressBar.vue`
  - Props: progress (number), showLabel (boolean)
  - Color coding: green (>75%), blue (50-75%), yellow (25-50%), orange (<25%)
  - Animate width changes

- [ ] **TASK 4.3.2**: Create `resources/js/components/Goals/GoalCountdown.vue`
  - Props: targetDate (string/Date)
  - Display: "X days", "X months", "Xy Xm" format based on time remaining
  - Color coding for urgency

- [ ] **TASK 4.3.3**: Create `resources/js/components/Goals/GoalContributionStreak.vue`
  - Props: streak (number), longestStreak (number)
  - Display fire icon with streak count
  - Show "Longest: X" below

- [ ] **TASK 4.3.4**: Create `resources/js/components/Goals/GoalMilestoneTracker.vue`
  - Props: milestones (array), currentProgress (number)
  - Display milestone markers at 25%, 50%, 75%, 100%
  - Highlight reached milestones

- [ ] **TASK 4.3.5**: Create `resources/js/components/Goals/PropertyGoalCalculator.vue`
  - Props: modelValue (object for v-model)
  - Fields: estimated_property_price, deposit_percentage, is_first_time_buyer, property_type
  - Display calculated: deposit amount, stamp duty, additional costs, total needed
  - Call API for stamp duty calculation

- [ ] **TASK 4.3.6**: Create `resources/js/components/Goals/GoalCard.vue`
  - Reuse patterns from existing `Investment/GoalCard.vue`
  - Add streak display
  - Add countdown display
  - Add module badge (savings/investment/property)
  - Include edit/delete actions

- [ ] **TASK 4.3.7**: Create `resources/js/components/Goals/GoalFormModal.vue`
  - Use @save event (not @submit - avoid double submission bug)
  - Dynamic fields based on goal_type
  - Show property calculator when property goal selected
  - Joint goal toggle with owner selection
  - Module override option (advanced)

- [ ] **TASK 4.3.8**: Test all components render correctly
  - Create test page or use Storybook if available

---

## Phase 5: Dashboard Integration

### 5.1 Goals Overview Card

- [ ] **TASK 5.1.1**: Create `resources/js/components/Dashboard/GoalsOverviewCard.vue`
  - Display total goals count with on-track badge
  - Show overall progress (current vs target)
  - List top 5 goals with mini progress bars
  - Show module icons for goal assignment
  - Display best contribution streak
  - Include "Set Your First Goal" CTA if no goals
  - Follow existing dashboard card patterns

- [ ] **TASK 5.1.2**: Add GoalsOverviewCard to Dashboard.vue
  - Import component
  - Add to components object
  - Add loading state for goals
  - Add to grid (between Tax and Estate cards)
  - Load goals data in loadAllData method

- [ ] **TASK 5.1.3**: Test dashboard displays goals card correctly
  - Test with no goals (shows CTA)
  - Test with goals (shows progress)
  - Test loading state
  - Test error handling

---

## Phase 6: Module Integration

### 6.1 Savings Module Integration

- [ ] **TASK 6.1.1**: Update `resources/js/views/Savings/SavingsDashboard.vue`
  - Add goals section that displays goals where assigned_module = 'savings'
  - Use `goals/goalsForModule('savings')` getter
  - Add "View All Goals" link
  - Update to use new unified Goal model

- [ ] **TASK 6.1.2**: Test savings goals display correctly

### 6.2 Investment Module Integration

- [ ] **TASK 6.2.1**: Update `resources/js/views/Investment/AccountPerformancePanel.vue`
  - Add goals section for investment goals
  - Integrate with existing Monte Carlo displays
  - Use `goals/goalsForModule('investment')` getter

- [ ] **TASK 6.2.2**: Test investment goals display correctly with Monte Carlo

### 6.3 Goals Dashboard View

- [ ] **TASK 6.3.1**: Create `resources/js/views/Goals/GoalsDashboard.vue`
  - Summary cards: total goals, on-track, off-track, total target
  - Filter by module, status, priority
  - Grid of GoalCard components
  - Create goal button → opens GoalFormModal
  - Responsive layout

- [ ] **TASK 6.3.2**: Create `resources/js/views/Goals/GoalDetail.vue`
  - Full goal details
  - Progress chart over time
  - Contribution history
  - Projections (for investment goals)
  - Edit/delete actions
  - Record contribution form

- [ ] **TASK 6.3.3**: Add routes to `resources/js/router/index.js`
  ```javascript
  {
    path: '/goals',
    name: 'Goals',
    component: () => import('@/views/Goals/GoalsDashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/goals/:id',
    name: 'GoalDetail',
    component: () => import('@/views/Goals/GoalDetail.vue'),
    meta: { requiresAuth: true }
  }
  ```

- [ ] **TASK 6.3.4**: Add Goals to navigation menu
  - Update AppLayout.vue or navigation component

- [ ] **TASK 6.3.5**: Test full goals flow
  - Navigate to /goals
  - Create a new goal
  - Verify module assignment
  - View goal detail
  - Record contribution
  - Check streak updates
  - Delete goal

---

## Phase 7: Visual Features

### 7.1 Enhanced Countdown

- [ ] **TASK 7.1.1**: Enhance GoalCountdown.vue
  - Add urgency colors (red < 30 days, orange < 90 days)
  - Add pulsing animation for urgent goals

### 7.2 Streak Visualization

- [ ] **TASK 7.2.1**: Enhance GoalContributionStreak.vue
  - Add flame animation
  - Color intensity based on streak length
  - Celebration animation on streak milestone (5, 10, 25, 50, 100)

### 7.3 Milestone Celebrations

- [ ] **TASK 7.3.1**: Implement milestone reach detection
  - Check milestones on contribution
  - Store reached milestones in goal.milestones JSON

- [ ] **TASK 7.3.2**: Add milestone celebration UI
  - Toast notification when milestone reached
  - Badge display for reached milestones

### 7.4 Projection Charts

- [ ] **TASK 7.4.1**: Create `resources/js/components/Goals/GoalProjectionChart.vue`
  - Use ApexCharts (already in project)
  - Show actual progress vs target trajectory
  - For investment goals: show Monte Carlo distribution

---

## Phase 8: Testing & Polish

### 8.1 Unit Tests

- [ ] **TASK 8.1.1**: Run all unit tests
  ```bash
  ./vendor/bin/pest --testsuite=Unit
  ```

- [ ] **TASK 8.1.2**: Fix any failing tests

### 8.2 Feature Tests

- [ ] **TASK 8.2.1**: Run all feature tests
  ```bash
  ./vendor/bin/pest --testsuite=Feature
  ```

- [ ] **TASK 8.2.2**: Fix any failing tests

### 8.3 Manual Testing

- [ ] **TASK 8.3.1**: Test as young_family preview user
  - Create emergency fund goal (→ savings)
  - Create home deposit goal (→ property)
  - Create education fund goal (→ investment, >3 years)
  - Test joint goal with spouse
  - Record contributions
  - Verify streaks work

- [ ] **TASK 8.3.2**: Test as peak_earners preview user
  - Create custom goal type
  - Test module override
  - Test Monte Carlo for investment goals

- [ ] **TASK 8.3.3**: Test dashboard card
  - Verify shows on main dashboard
  - Test empty state CTA
  - Test with multiple goals

### 8.4 Code Quality

- [ ] **TASK 8.4.1**: Run Pint for code formatting
  ```bash
  ./vendor/bin/pint
  ```

- [ ] **TASK 8.4.2**: Review code for security issues
  - Check authorization on all endpoints
  - Verify user can only access own goals
  - Check for SQL injection vulnerabilities

### 8.5 Documentation

- [ ] **TASK 8.5.1**: Add API routes to CLAUDE.md API reference

- [ ] **TASK 8.5.2**: Add goal-related guidance to CLAUDE.md

---

## Phase 9: Preview Data

- [ ] **TASK 9.1.1**: Add goals to PreviewUserSeeder
  - young_family: Emergency fund goal, home deposit goal
  - peak_earners: Investment goals, retirement top-up goal
  - entrepreneur: Business investment goal

- [ ] **TASK 9.1.2**: Reseed preview users
  ```bash
  php artisan db:seed --class=PreviewUserSeeder --force
  ```

---

## Phase 10: Final Verification

- [ ] **TASK 10.1.1**: Full end-to-end test
  - Login as demo user
  - Navigate through all goal features
  - Verify all modules display their goals
  - Test CRUD operations
  - Verify dashboard integration

- [ ] **TASK 10.1.2**: Reseed all required data after tests
  ```bash
  php artisan db:seed --class=TaxConfigurationSeeder --force
  php artisan db:seed --class=TaxProductReferenceSeeder --force
  php artisan db:seed --class=ActuarialLifeTablesSeeder --force
  php artisan db:seed --class=AdminUserSeeder --force
  php artisan db:seed --class=PreviewUserSeeder --force
  ```

- [ ] **TASK 10.1.3**: Commit changes
  ```bash
  git add .
  git commit -m "feat(goals): Implement centralized goals-based planning module"
  ```

---

## Completion Checklist

- [ ] All migrations run successfully
- [ ] All unit tests pass
- [ ] All feature tests pass
- [ ] Goals display on dashboard
- [ ] Goals display in Savings module
- [ ] Goals display in Investment module
- [ ] Goal creation works with module auto-assignment
- [ ] Joint goals work correctly
- [ ] Custom goal types work
- [ ] Contribution recording works
- [ ] Streaks update correctly
- [ ] Milestones detected correctly
- [ ] Property cost calculator works
- [ ] Monte Carlo projections work for investment goals
- [ ] Code formatted with Pint
- [ ] Documentation updated
