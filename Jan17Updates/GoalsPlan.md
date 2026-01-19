# Goals-Based Planning Module Implementation Plan

## Overview

Implement a centralized Goals-Based Planning Module that allows users to set financial goals in one place, with automatic assignment and display within the appropriate modules (Savings, Investment, Property, Retirement).

**Key Research Insight**: People with structured financial plans (defined goals + timelines) are 78% likely to feel on track vs 45% without plans. Top UK motivators: family security (44%), financial independence (43%), security (31%).

---

## Design Decisions (Confirmed)

1. **Data Model**: Replace existing `SavingsGoal` and `InvestmentGoal` models with unified `Goal` model. Migrate existing data.

2. **Joint Goals**: Support joint goals between household members, following existing joint ownership patterns (reciprocal records).

3. **Goal Types**: Standard types (emergency fund, property/home deposit, education, retirement, wealth, wedding, holiday, car, debt repayment) PLUS custom types that users can define.

4. **Gamification**: Basic level - streaks, milestones (25/50/75/100%), progress bars, countdowns. No achievements/badges/confetti.

---

## Architecture Summary

### Core Concept: Unified Goal Model

Create a single `Goal` model that consolidates the existing `SavingsGoal` and `InvestmentGoal` models. Each goal is automatically assigned to a module based on:

| Criteria | Assigned Module |
|----------|-----------------|
| Time horizon ≤ 3 years | Savings (cash) |
| Time horizon > 3 years AND amount ≥ £5,000 | Investment |
| Goal type = property_purchase / home_deposit | Property |
| Goal type = retirement | Retirement |
| Goal type = emergency_fund | Savings (always) |

### Module Assignment Logic

```
New Goal Created
      ↓
┌─────────────────────────────┐
│ GoalAssignmentService       │
│ ─────────────────────────   │
│ 1. Check goal_type first    │
│ 2. Check time horizon       │
│ 3. Check target amount      │
│ 4. Allow manual override    │
└─────────────────────────────┘
      ↓
Assigned to: savings | investment | property | retirement
```

---

## Database Schema

### Goals Table

```
goals table:
- id, user_id
- goal_name, goal_type (enum + 'custom'), custom_goal_type_name (varchar, nullable)
- description
- target_amount, current_amount, target_date, start_date
- assigned_module (enum: savings/investment/property/retirement), module_override (bool)
- priority (critical/high/medium/low), is_essential
- monthly_contribution, contribution_frequency
- contribution_streak, longest_streak, last_contribution_date
- linked_account_ids (JSON), linked_savings_account_id (FK)
- risk_preference, use_global_risk_profile

Joint ownership fields:
- ownership_type (enum: individual/joint), default 'individual'
- joint_owner_id (FK to users, nullable)
- ownership_percentage (decimal 5,2), default 100

Property-specific fields:
- property_location, property_type (enum)
- is_first_time_buyer, estimated_property_price
- deposit_percentage, stamp_duty_estimate, additional_costs_estimate

Tracking fields:
- status (active/paused/completed/abandoned)
- milestones (JSON), projection_data (JSON)
- completed_at, completion_notes
- timestamps, soft_deletes
```

### Goal Contributions Table

```
goal_contributions table:
- id
- goal_id (FK)
- user_id (FK)
- amount (decimal 12,2)
- type (enum: manual/auto_transfer/interest/dividend/adjustment)
- contribution_date
- notes
- source_account_type, source_account_id
- timestamps
```

---

## Joint Goals Implementation

Following the existing **Reciprocal Records Pattern** for joint ownership:

```php
// Creating a joint goal (shared between household members)
// Record 1: Primary user
Goal::create([
    'user_id' => $user->id,
    'joint_owner_id' => $spouse->id,
    'ownership_percentage' => 50,
    'ownership_type' => 'joint',
    // ... other fields
]);

// Record 2: Spouse (reciprocal)
Goal::create([
    'user_id' => $spouse->id,
    'joint_owner_id' => $user->id,
    'ownership_percentage' => 50,
    'ownership_type' => 'joint',
    // ... same goal data
]);
```

---

## Custom Goal Types Implementation

```php
// In goals table
'goal_type' => enum + 'custom' option
'custom_goal_type_name' => varchar(100), nullable
```

**System Goal Types:**
- emergency_fund → savings
- property_purchase, home_deposit → property
- education → investment (typically long-term)
- retirement → retirement
- wealth_accumulation → investment
- wedding, holiday, car_purchase → savings (typically short-term)
- debt_repayment → savings
- other → based on time horizon

**Custom Types:**
- Users can create custom types with a name
- Default to time-based module assignment

---

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/goals` | List goals (filter by module, status) |
| GET | `/api/goals/analysis` | Full analysis |
| GET | `/api/goals/dashboard-overview` | Summary for dashboard card |
| POST | `/api/goals` | Create goal |
| PUT | `/api/goals/{id}` | Update goal |
| DELETE | `/api/goals/{id}` | Delete goal |
| POST | `/api/goals/{id}/contribution` | Record contribution |
| GET | `/api/goals/{id}/projections` | Get projections |
| POST | `/api/goals/calculate-property-costs` | Property cost calculator |

---

## File Structure

```
Backend:
├── app/Models/Goal.php
├── app/Agents/GoalsAgent.php
├── app/Services/Goals/
│   ├── GoalAssignmentService.php
│   ├── GoalAffordabilityService.php
│   ├── GoalProgressService.php
│   └── GoalRiskService.php
├── app/Http/Controllers/Api/GoalsController.php
├── app/Http/Requests/Goals/
│   ├── StoreGoalRequest.php
│   └── UpdateGoalRequest.php
└── database/migrations/
    ├── 2026_01_18_000001_create_goals_table.php
    ├── 2026_01_18_000002_create_goal_contributions_table.php
    └── 2026_01_18_000003_migrate_existing_goals.php

Frontend:
├── resources/js/store/modules/goals.js
├── resources/js/services/goalsService.js
├── resources/js/components/Goals/
│   ├── GoalFormModal.vue
│   ├── GoalCard.vue
│   ├── GoalProgressBar.vue
│   ├── GoalCountdown.vue
│   ├── GoalContributionStreak.vue
│   ├── GoalMilestoneTracker.vue
│   └── PropertyGoalCalculator.vue
├── resources/js/components/Dashboard/
│   └── GoalsOverviewCard.vue
└── resources/js/views/Goals/
    ├── GoalsDashboard.vue
    └── GoalDetail.vue
```

---

## Implementation Summary

| Phase | Description | Key Deliverables |
|-------|-------------|------------------|
| 1 | Database & Core Backend | Goal model, migrations, data migration script |
| 2 | Service Layer | Assignment, Affordability, Progress, Risk services |
| 3 | GoalsAgent & API | Agent orchestrator, REST endpoints |
| 4 | Frontend Core | Vuex store, API service, core components |
| 5 | Dashboard Integration | GoalsOverviewCard on main dashboard |
| 6 | Module Integration | Goals in Savings/Investment views, Goals Dashboard |
| 7 | Visual Features | Countdowns, streaks, milestones |

**Key Patterns to Follow:**
- Agent-based orchestration (like InvestmentAgent)
- Reciprocal records for joint goals (like joint properties)
- Dashboard card pattern with loading/error states
- Existing GoalCard component patterns for consistency
- currencyMixin for all currency formatting

**UK Financial Context:**
- Stamp Duty Land Tax calculations via TaxConfigService
- ISA allowance integration for savings goals
- Pension annual allowance for retirement goals
- Property purchase costs (legal fees ~3%, surveys, etc.)
