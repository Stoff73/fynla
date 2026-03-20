# Deployment Guide — 20 March 2026

Session: Test fixes, ExpenditureForm review, Estate hardcoded rates, budget constants, @keyframes cleanup, budget override persistence, spouse joint save.

## Rebuild Required?

**Yes** — frontend files changed (`app.css`, 9 Vue components). Run:

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory.

## Database Migration (1 pending)

Adds `retired_budget_overrides` and `widowed_budget_overrides` JSON nullable columns to the `users` table. Safe — no data loss.

## PHP Files to Upload (11)

```
app/Agents/EstateAgent.php
app/Http/Controllers/Api/AdvisorController.php
app/Http/Controllers/Api/UserProfileController.php
app/Models/User.php
app/Services/Coordination/RecommendationPersonaliser.php
app/Services/Estate/GiftingStrategy.php
app/Services/Estate/IHTCalculationService.php
app/Services/Estate/IHTFormattingService.php
app/Services/Estate/IHTStrategyGeneratorService.php
app/Services/Estate/WillAnalysisService.php
app/Services/Retirement/DecumulationPlanner.php
```

## Migration File to Upload (1)

```
database/migrations/2026_03_20_074942_add_budget_overrides_to_users_table.php
```

## Frontend (via build)

These are compiled into `public/build/` — upload the build directory, not individual files:

```
resources/css/app.css
resources/js/components/Investment/PortfolioOptimization.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/NetWorth/PropertyList.vue
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/views/Public/CalculatorsPage.vue
resources/js/views/Public/LearningCentre.vue
resources/js/views/Public/SecurityPage.vue
resources/js/views/Savings/SavingsAccountDetailInline.vue
```

## Test Files (do NOT upload to production)

```
tests/Feature/Api/FamilyMembersControllerTest.php
tests/Feature/CompletenessEndpointTest.php
tests/Feature/Estate/WillBuilderApiTest.php
tests/Feature/InvestmentModuleTest.php
```

## Upload Order

1. Upload 11 PHP files to matching paths on server
2. Upload migration file to `database/migrations/`
3. Run `./deploy/fynla-org/build.sh` locally
4. Upload `public/build/` directory
5. SSH and run migration + clear caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## What Changed

| Category | Files | Summary |
|----------|-------|---------|
| Test fixes | 4 PHP + 4 test files | Fixed 8 Pest failures (strict type comparison, SoftDeletes assertion, ModelNotFoundException handling, factory middle_name, risk profile duplicate) |
| ExpenditureForm | 1 Vue file | Fixed section header totals bug, isSectionExpanded fallback, removed dead code, replaced all off-palette color tokens (blue/green/success to violet/spring/raspberry) |
| Estate rates | 4 PHP files | Replaced 30 hardcoded "40%"/"36%" narrative strings with TaxConfigService values |
| Budget constants | 3 PHP files | Replaced magic numbers (0.85, 0.70, 0.50) with named class constants |
| @keyframes cleanup | 8 Vue files + app.css | Replaced local @keyframes with global CSS classes, added `.animate-slide-in-right` |
| Budget overrides | 2 PHP + 1 Vue + 1 migration | Retired/widowed budget overrides now persist to DB via JSON columns and restore on load |
| Spouse joint save | 1 Vue file | Joint mode now saves expenditure for both user and spouse |
