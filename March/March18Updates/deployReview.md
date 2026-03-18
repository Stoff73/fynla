# Code Review Remediation — Deployment Guide

**Date:** 18 March 2026
**Commits:** 14 commits (54249c6 → HEAD)
**Total:** 100 files changed, 1,423 insertions, 821 deletions
**Tasks:** 94/94 complete

---

## Files to Upload

### PHP — Controllers (7 files)
```
app/Http/Controllers/Api/AdminController.php
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/InvestmentController.php
app/Http/Controllers/Api/MFAController.php
app/Http/Controllers/Api/PaymentController.php
app/Http/Controllers/Api/PostcodeLookupController.php
```

### PHP — Middleware (3 files)
```
app/Http/Middleware/AdvisorImpersonationMiddleware.php
app/Http/Middleware/AgentTokenAuth.php
app/Http/Middleware/PreviewWriteInterceptor.php
```

### PHP — Resources (6 files — 5 NEW)
```
app/Http/Resources/AdminUserResource.php          ← NEW
app/Http/Resources/UserResource.php               ← MODIFIED
app/Http/Resources/Estate/AssetResource.php       ← NEW
app/Http/Resources/Estate/GiftResource.php        ← NEW
app/Http/Resources/Estate/LiabilityResource.php   ← NEW
app/Http/Resources/Estate/TrustResource.php       ← NEW
```

### PHP — Jobs (1 file)
```
app/Jobs/RunMonteCarloSimulation.php
```

### PHP — Models (7 files)
```
app/Models/Estate/IHTProfile.php
app/Models/Estate/Trust.php
app/Models/FamilyMember.php
app/Models/Investment/InvestmentAccount.php
app/Models/ProtectionProfile.php
app/Models/StatePension.php
app/Models/User.php
```

### PHP — Agents (3 files)
```
app/Agents/CoordinatingAgent.php
app/Agents/EstateAgent.php
app/Agents/RetirementAgent.php
```

### PHP — Traits (1 file)
```
app/Traits/HasAiChat.php
```

### PHP — Console Commands (1 file)
```
app/Console/Commands/SendProtectionAlerts.php
```

### PHP — Config (1 file)
```
config/sanctum.php
```

### PHP — Services (22 files)
```
app/Services/Coordination/HouseholdPlanningService.php
app/Services/Dashboard/DashboardAggregator.php
app/Services/Estate/ComprehensiveEstatePlanService.php
app/Services/Estate/GiftingStrategyOptimizer.php
app/Services/Estate/IHTCalculationService.php
app/Services/Estate/IHTStrategyGeneratorService.php
app/Services/Estate/PersonalizedTrustStrategyService.php
app/Services/Investment/Analytics/MarkowitzOptimizer.php
app/Services/Investment/DividendTaxCalculator.php
app/Services/Investment/Rebalancing/TaxAwareRebalancer.php
app/Services/Investment/Tax/BedAndISACalculator.php
app/Services/Investment/Tax/ISAAllowanceOptimizer.php
app/Services/Investment/Tax/TaxOptimizationAnalyzer.php
app/Services/Plans/EstatePlanService.php
app/Services/Protection/RecommendationEngine.php
app/Services/Savings/ISATracker.php
app/Services/Savings/PSACalculator.php
app/Services/Savings/SavingsActionDefinitionService.php
app/Services/Tax/TaxActionDefinitionService.php
app/Services/Tax/TaxOptimisationService.php
app/Services/Trust/IHTPeriodicChargeCalculator.php
app/Services/UKTaxCalculator.php
```

### PHP — Routes (1 file)
```
routes/api.php
```

### PHP — Migrations (3 files — NEW)
```
database/migrations/2026_03_18_100000_add_soft_deletes_to_key_models.php          ← NEW
database/migrations/2026_03_18_100001_add_unique_constraints_to_has_one_tables.php ← NEW
database/migrations/2026_03_18_100002_fix_indexes_and_constraints.php              ← NEW
```

### Vue — Components (28 files)
```
resources/js/components/Admin/TaxSettings.vue
resources/js/components/Cash/AccountGroupList.vue
resources/js/components/Dashboard/FinancialHealthScore.vue
resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue
resources/js/components/Estate/LpaDetailView.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderReviewStep.vue
resources/js/components/Goals/GoalsProjectionChart.vue
resources/js/components/Investment/AllocationComparison.vue
resources/js/components/Investment/AssetLocationOptimizer.vue
resources/js/components/Investment/ContributionPlanner.vue
resources/js/components/Investment/ISAOptimizationStrategy.vue
resources/js/components/Investment/InvestmentRecommendationsTracker.vue
resources/js/components/Investment/PlanSections/TaxStrategySection.vue
resources/js/components/Investment/PortfolioOptimization.vue
resources/js/components/Investment/PortfolioOptimizer.vue
resources/js/components/Investment/PortfolioOverview.vue
resources/js/components/Investment/TaxFees.vue
resources/js/components/Investment/TaxOptimizationRecommendations.vue
resources/js/components/NetWorth/InvestmentDetailInline.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/Onboarding/OnboardingWizard.vue
resources/js/components/Protection/PolicyFormModal.vue
resources/js/components/Retirement/AnnualAllowanceTracker.vue
resources/js/components/Retirement/DBPensionForm.vue
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Savings/SaveAccountModal.vue
resources/js/components/Savings/SaveGoalModal.vue
resources/js/components/UserProfile/LetterToSpouse.vue
```

### Vue — Mobile (2 files)
```
resources/js/mobile/layouts/MobileLayout.vue
resources/js/mobile/views/MobileLoginScreen.vue
```

### Vue — Stores (3 files)
```
resources/js/store/modules/auth.js
resources/js/store/modules/netWorth.js
resources/js/store/modules/preview.js
```

### Vue — Services (2 files — NEW)
```
resources/js/services/letterService.js     ← NEW
resources/js/services/privacyService.js    ← NEW
```

### Vue — Utils (1 file — NEW)
```
resources/js/utils/sanitizeHtml.js         ← NEW
```

### Vue — Views (3 files)
```
resources/js/views/Settings/AssumptionsSettings.vue
resources/js/views/Settings/PrivacySettings.vue
resources/js/views/Trusts/TrustsDashboard.vue
```

---

## Summary by Type

| Type | Count |
|------|-------|
| PHP files | 58 |
| Vue/JS files | 42 |
| **Total** | **100** |

---

## Post-Upload Steps

### 1. Build frontend (locally)
```bash
./deploy/fynla-org/build.sh
```
Then upload `public/build/` directory.

### 2. Run migrations (SSH)
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate --force
```

This runs 3 new migrations:
- `2026_03_18_100000` — Adds `deleted_at` to trusts, iht_profiles, family_members, protection_profiles, state_pensions
- `2026_03_18_100001` — Adds unique constraints on user_id for 6 HasOne tables
- `2026_03_18_100002` — Adds FK on bequests.asset_id, adds 3 indexes, drops 5 duplicate indexes

### 3. Clear caches (SSH)
```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### 4. Reseed (SSH)
```bash
php artisan db:seed --force
```

---

## Risk Assessment

| Change | Risk | Notes |
|--------|------|-------|
| Float → decimal model casts | **Medium** | May surface precision differences in existing data. Test financial calculations after deploy |
| UserResource in auth responses | **Medium** | Frontend may expect fields that are no longer returned. Test login flow thoroughly |
| AdminUserResource | **Low** | Admin panel only |
| Monte Carlo IDOR fix | **Low** | Cache format change — existing cached jobs will return 404 (cleared on cache:clear) |
| MFA secret in Cache | **Low** | Any in-progress MFA setups will need to restart |
| Preview rate limit 3/min | **Low** | Only affects preview login spam |
| SoftDeletes migrations | **Low** | Adds nullable column, no data change |
| Unique constraints | **Medium** | Will fail if duplicate records exist. Check for duplicates before migrating |
| Index changes | **Low** | Online DDL on MySQL 8 |
| Tax calculation changes | **Low** | Values sourced from same TaxConfigService, just via dynamic lookup now |

### Pre-deploy check for duplicate records
Run this query before the unique constraints migration:
```sql
SELECT 'iht_profiles' as tbl, user_id, COUNT(*) as cnt FROM iht_profiles GROUP BY user_id HAVING cnt > 1
UNION ALL
SELECT 'retirement_profiles', user_id, COUNT(*) FROM retirement_profiles GROUP BY user_id HAVING cnt > 1
UNION ALL
SELECT 'risk_profiles', user_id, COUNT(*) FROM risk_profiles GROUP BY user_id HAVING cnt > 1
UNION ALL
SELECT 'state_pensions', user_id, COUNT(*) FROM state_pensions GROUP BY user_id HAVING cnt > 1
UNION ALL
SELECT 'letters_to_spouse', user_id, COUNT(*) FROM letters_to_spouse GROUP BY user_id HAVING cnt > 1
UNION ALL
SELECT 'expenditure_profiles', user_id, COUNT(*) FROM expenditure_profiles GROUP BY user_id HAVING cnt > 1;
```
If any duplicates exist, resolve them before running migrations.
