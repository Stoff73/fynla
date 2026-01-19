# Goals-Based Planning Module Implementation

**Date**: January 17, 2026
**Branch**: `goals`
**Commit**: `f379021`

## Overview

Implemented a centralized Goals-Based Planning Module that allows users to set financial goals with automatic assignment and display within the appropriate modules (Savings, Investment, Property, Retirement).

## Architecture

### Backend Components

| Component | Path | Purpose |
|-----------|------|---------|
| Goal Model | `app/Models/Goal.php` | Unified goal model replacing separate SavingsGoal/InvestmentGoal |
| GoalContribution Model | `app/Models/GoalContribution.php` | Track contribution history for streak calculations |
| GoalsAgent | `app/Agents/GoalsAgent.php` | Business logic orchestrator |
| GoalsController | `app/Http/Controllers/Api/GoalsController.php` | REST API endpoints |
| GoalAssignmentService | `app/Services/Goals/GoalAssignmentService.php` | Auto-assign goals to modules |
| GoalAffordabilityService | `app/Services/Goals/GoalAffordabilityService.php` | Affordability analysis |
| GoalProgressService | `app/Services/Goals/GoalProgressService.php` | Progress & streak tracking |
| GoalRiskService | `app/Services/Goals/GoalRiskService.php` | Risk-based projections |

### Frontend Components

| Component | Path | Purpose |
|-----------|------|---------|
| GoalsOverviewCard | `resources/js/components/Dashboard/GoalsOverviewCard.vue` | Dashboard summary card |
| GoalsDashboard | `resources/js/views/Goals/GoalsDashboard.vue` | Full goals management view |
| GoalFormModal | `resources/js/components/Goals/GoalFormModal.vue` | Create/edit goals |
| GoalCard | `resources/js/components/Goals/GoalCard.vue` | Individual goal display |
| GoalProgressBar | `resources/js/components/Goals/GoalProgressBar.vue` | Visual progress indicator |
| GoalCountdown | `resources/js/components/Goals/GoalCountdown.vue` | Time remaining display |
| GoalContributionStreak | `resources/js/components/Goals/GoalContributionStreak.vue` | Streak meter |
| GoalMilestoneTracker | `resources/js/components/Goals/GoalMilestoneTracker.vue` | 25/50/75/100% milestones |
| goals.js store | `resources/js/store/modules/goals.js` | Vuex state management |
| goalsService.js | `resources/js/services/goalsService.js` | API wrapper |

### Database Migrations

1. `2026_01_18_000001_create_goals_table.php` - Main goals table
2. `2026_01_18_000002_create_goal_contributions_table.php` - Contribution tracking
3. `2026_01_18_000003_migrate_existing_goals_data.php` - Migrate legacy data

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

## Goal Types

**System Types** (with default module assignment):
- `emergency_fund` → savings
- `property_purchase`, `home_deposit` → property
- `education` → investment (long-term)
- `retirement` → retirement
- `wealth_accumulation` → investment
- `wedding`, `holiday`, `car_purchase` → savings (short-term)
- `debt_repayment` → savings
- `custom` → based on time horizon

## Module Assignment Logic

```
Time horizon ≤ 3 years → Savings (cash)
Time horizon > 3 years AND amount ≥ £5,000 → Investment
Goal type = property_purchase/home_deposit → Property
Goal type = retirement → Retirement
Goal type = emergency_fund → Savings (always)
```

## Preview Personas Goals Seeding

All 6 preview personas now have realistic goals:

### young_family (James & Emily Carter) - 4 Goals
| Goal | Type | Target | Current | Module |
|------|------|--------|---------|--------|
| Emergency Fund | emergency_fund | £25,000 | £8,500 | savings |
| Oliver's University Fund | education | £50,000 | £5,000 | investment |
| Sophia's University Fund | education | £50,000 | £2,800 | investment |
| Family Holiday Fund | holiday | £4,000 | £1,200 | savings |

### peak_earners (David & Sarah Mitchell) - 6 Goals
| Goal | Type | Target | Current | Module |
|------|------|--------|---------|--------|
| Max Pension Contributions | retirement | £60,000 | £45,000 | retirement |
| William's House Deposit Help | custom | £40,000 | £15,000 | savings |
| Charlotte's Gap Year Fund | custom | £15,000 | £8,000 | savings |
| ISA Wealth Building | wealth_accumulation | £500,000 | £285,000 | investment |
| Sarah's ISA | wealth_accumulation | £400,000 | £180,000 | investment |
| Early Retirement Fund | retirement | £200,000 | £85,000 | investment |

### widow (Margaret Thompson) - 4 Goals
| Goal | Type | Target | Current | Module |
|------|------|--------|---------|--------|
| Grandchildren Education Fund | education | £60,000 | £18,000 | savings |
| Annual Gifting to Family | custom | £6,000 | £3,000 | savings |
| Home Maintenance Reserve | custom | £25,000 | £15,000 | savings |
| Care Fee Reserve | custom | £150,000 | £95,000 | savings |

### entrepreneur (Alex Chen) - 5 Goals
| Goal | Type | Target | Current | Module |
|------|------|--------|---------|--------|
| Business Exit Fund | custom | £500,000 | £125,000 | investment |
| Early Retirement SIPP | retirement | £60,000 | £42,000 | retirement |
| Property Investment Deposit | property_purchase | £100,000 | £35,000 | property |
| Emergency Business Buffer | emergency_fund | £60,000 | £25,000 | savings |
| Sabbatical Travel Fund | holiday | £25,000 | £8,000 | savings |

### young_saver (Alex Morgan) - 4 Goals
| Goal | Type | Target | Current | Module |
|------|------|--------|---------|--------|
| First Home Deposit | property_purchase | £25,000 | £5,600 | property |
| Emergency Fund | emergency_fund | £5,500 | £1,500 | savings |
| Max LISA This Year | custom | £4,000 | £2,000 | savings |
| Career Development Fund | custom | £3,000 | £800 | savings |

### retired_couple (Patricia & Harold Bennett) - 5 Goals
| Goal | Type | Target | Current | Module |
|------|------|--------|---------|--------|
| Annual Gifting to Grandchildren | custom | £6,000 | £3,000 | savings |
| Grandchildren Education Trust | education | £75,000 | £24,000 | investment |
| Care Fee Reserve | custom | £180,000 | £145,500 | savings |
| IHT Reduction Target | custom | £100,000 | £46,000 | savings |
| Annual Holiday Fund | holiday | £8,000 | £2,500 | savings |

**Total: 28 goals across 6 personas**

## Testing the Module

1. Start dev servers: `./dev.sh`
2. Navigate to http://localhost:8000
3. Click "Try the Demo" and select a persona
4. View Goals card on dashboard
5. Navigate to /goals for full goals dashboard

## Files Changed

### New Files (39 files)
- Backend: 12 files (models, services, controllers, migrations)
- Frontend: 14 files (components, views, store, services)
- Persona JSON updates: 6 files

### Modified Files
- `database/seeders/PreviewUserSeeder.php` - Added createGoals method
- `resources/js/router/index.js` - Added /goals route
- `resources/js/store/index.js` - Added goals module
- `resources/js/views/Dashboard.vue` - Added GoalsOverviewCard
- `routes/api.php` - Added goals API routes

## Next Steps

1. Add goal creation flow in module dashboards (Savings, Investment)
2. Implement goal-specific projections with Monte Carlo
3. Add notification/reminder system for contributions
4. Add goals to module-specific views (show savings goals in Savings dashboard)
