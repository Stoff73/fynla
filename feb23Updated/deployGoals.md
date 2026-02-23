# Goals & Life Events Cross-Module Integration - Deployment Notes

**Date:** 23 February 2026
**Branch:** goals
**Status:** PENDING UPLOAD
**Rebuild Required:** YES (Vue/JS changes)

---

## Summary of Changes

Goals & Life Events cross-module integration: goal dependencies, linked accounts (savings/investment), life event impact annotations across all modules, goal strategy summaries, Monte Carlo projections, observer-based auto-contributions, and risk analysis.

Bug fix: GoalStrategyService lazy loading violation was crashing savings/investment/retirement API endpoints entirely. Fixed with eager loading + try-catch fault tolerance in controllers.

Bug fix: Mitchell persona "Early Retirement Fund" had goal_type "retirement" but assigned_module "investment", causing "Retirement" heading to appear in the investments tab. Changed to "wealth_accumulation".

Bug fix: ModuleGoalStrategies streak display was rendering raw JSON object instead of the formatted label.

Fix: `mysql-schema.sql` was not regenerated after migrations. Schema dump now includes `goals`, `goal_contributions`, `life_events`, and `goal_dependencies` tables.

---

## Rebuild

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` directory after rebuild.

---

## New Files (create on server)

### Backend - Services
- `app/Services/Goals/GoalStrategyService.php`
- `app/Services/Goals/LifeEventIntegrationService.php`
- `app/Services/Goals/FinancialForecastService.php`
- `app/Services/Shared/MonteCarloEngine.php`

### Backend - Observers
- `app/Observers/InvestmentAccountGoalObserver.php`
- `app/Observers/SavingsAccountGoalObserver.php`

### Backend - Migrations
- `database/migrations/2026_02_23_120001_create_goal_dependencies_table.php`
- `database/migrations/2026_02_23_120002_add_linked_investment_account_to_goals.php`

### Frontend - New Components (included in rebuild)
- `resources/js/components/Estate/EstateLifeEventsImpact.vue`
- `resources/js/components/Shared/ModuleGoalStrategies.vue`
- `resources/js/components/Shared/ModuleLifeEvents.vue`

---

## Modified Files (upload to server)

### Backend - Controllers
- `app/Http/Controllers/Api/GoalsController.php`
- `app/Http/Controllers/Api/SavingsController.php`
- `app/Http/Controllers/Api/InvestmentController.php`
- `app/Http/Controllers/Api/RetirementController.php`
- `app/Http/Controllers/Api/EstateController.php`
- `app/Http/Controllers/Api/ProtectionController.php`

### Backend - Models & Resources
- `app/Models/Goal.php`
- `app/Http/Resources/GoalResource.php`

### Backend - Services (modified)
- `app/Services/Goals/GoalAffordabilityService.php`
- `app/Services/Goals/GoalRiskService.php`
- `app/Services/Goals/GoalsProjectionService.php`
- `app/Services/Estate/ComprehensiveEstatePlanService.php`
- `app/Services/Estate/GiftingStrategyOptimizer.php`
- `app/Services/Estate/IHTCalculationService.php`

### Backend - Other
- `app/Providers/EventServiceProvider.php`
- `routes/api.php`
- `database/seeders/PreviewUserSeeder.php`
- `database/schema/mysql-schema.sql`

### Frontend - Stores
- `resources/js/store/modules/goals.js`
- `resources/js/store/modules/savings.js`
- `resources/js/store/modules/investment.js`
- `resources/js/store/modules/retirement.js`
- `resources/js/store/modules/protection.js`
- `resources/js/store/modules/estate.js`

### Frontend - Services
- `resources/js/services/goalsService.js`
- `resources/js/services/investmentService.js`
- `resources/js/services/savingsService.js`

### Frontend - Components (modified)
- `resources/js/components/Goals/GoalCard.vue`
- `resources/js/components/Goals/GoalFormModal.vue`
- `resources/js/components/Goals/GoalsList.vue`
- `resources/js/components/NetWorth/InvestmentList.vue`
- `resources/js/components/NetWorth/PensionList.vue`
- `resources/js/components/Estate/IHTPlanning.vue`
- `resources/js/components/UserProfile/IncomeStatementTab.vue`

### Frontend - Views (modified)
- `resources/js/views/Estate/EstateDashboard.vue`
- `resources/js/views/NetWorth/CashOverview.vue`
- `resources/js/views/Protection/ProtectionDashboard.vue`
- `resources/js/views/Savings/SavingsDashboard.vue`

### Frontend - Data (modified)
- `resources/js/data/personas/peak_earners.json`

---

## Post-Upload Commands (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migrations (creates goal_dependencies table, adds linked_investment_account_id to goals)
php artisan migrate

# Seed database
php artisan db:seed

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## What Changed (Detail)

### Goal Dependencies System
- Goals can now depend on other goals (blocks, funds, prerequisite types)
- `goal_dependencies` pivot table with cycle detection (BFS)
- API: GET/POST/DELETE `/api/goals/{id}/dependencies`
- GoalCard shows blocked badge and dependency count
- GoalFormModal allows adding/removing dependencies in edit mode

### Linked Account Tracking
- Goals can link to savings accounts (`linked_savings_account_id` - existing column)
- Goals can link to investment accounts (`linked_investment_account_id` - new column)
- Observers auto-create contributions when linked account balances increase
- Paused/completed goals are excluded from auto-contributions

### Life Event Integration
- All 5 module controllers now return `life_events` and `life_event_impact` data
- Events mapped to modules via LifeEventIntegrationService
- Module-specific context messages explain each event's relevance

### Goal Strategies per Module
- Savings, Investment, Retirement controllers return `goal_strategies` and `goals_summary`
- Includes progress tracking, affordability analysis, contribution streaks
- Investment/retirement goals include Monte Carlo probability projections

### Bug Fix - Lazy Loading Crash
- GoalStrategyService was crashing all module API endpoints due to lazy loading violation
- Fixed with eager loading of linkedSavingsAccount and linkedInvestmentAccount
- Added try-catch fault tolerance so new features never crash existing endpoints

### Bug Fix - Retirement Goal in Investment Tab
- Mitchell persona "Early Retirement Fund" had goal_type "retirement" but assigned_module "investment"
- Changed goal_type to "wealth_accumulation" in peak_earners.json (it's a bridge fund, not a pension)
- Now displays as "Wealth Building" instead of "Retirement" in the investments tab

### Bug Fix - Streak Raw JSON Display
- ModuleGoalStrategies.vue was rendering the full streak object as `[object Object]`
- Changed to display `streak.streak_label` (e.g. "36 months") and only when streak > 0

### Fix - Schema Dump Regenerated
- `mysql-schema.sql` was stale and missing `goals`, `goal_contributions`, `life_events`, and `goal_dependencies` tables
- Regenerated via `php artisan schema:dump` to include all current tables
