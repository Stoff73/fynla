# Consolidated Upload List - All Module Fixes

**Date:** 21 February 2026
**Source:** 7 deploy files (deployEstate, deployInvest, deployNetWorth, deployProtection, deployRetire, deployRisk, deploySaving)

---

## Build Required: YES

Frontend changes exist in 5 modules (Estate, Net Worth, Protection, Risk, Savings). Run once:

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory.

---

## Migrations Required: YES

4 modules have migrations (Estate, Net Worth, Retirement, Savings) plus 4 cross-module migrations. 13 total. Run on server:

```bash
php artisan migrate --force
```

---

## Seeders Required: YES

```bash
php artisan db:seed --force
```

Or targeted:

```bash
php artisan db:seed --class=SavingsMarketRatesSeeder --force
php artisan db:seed --class=PreviewUserSeeder --force
php artisan db:seed --class=TaxConfigurationSeeder --force
```

---

## Files to Delete on Server

```
app/Models/Estate/NetWorthStatement.php
```

(Note: `resources/js/components/NetWorth/NetWorthTrendChart.vue` is also deleted but covered by the build)

---

## All PHP Files to Upload (deduplicated, 98 files)

### Agents (3 files)

```
app/Agents/EstateAgent.php
app/Agents/ProtectionAgent.php
app/Agents/SavingsAgent.php
```

### Config (1 file)

```
config/investment_platforms.php
```

### Controllers (10 files)

```
app/Http/Controllers/Api/BusinessInterestController.php
app/Http/Controllers/Api/ChattelController.php
app/Http/Controllers/Api/Estate/GiftingController.php
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Controllers/Api/Estate/LifePolicyController.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/InvestmentController.php
app/Http/Controllers/Api/NetWorthController.php
app/Http/Controllers/Api/ProtectionController.php
app/Http/Controllers/Api/SavingsController.php
```

### Migrations (13 files)

```
database/migrations/2026_02_21_104352_add_soft_deletes_to_business_interests_and_chattels.php
database/migrations/2026_02_21_104355_add_joint_owner_foreign_keys_to_business_interests_and_chattels.php
database/migrations/2026_02_21_120000_add_soft_deletes_to_savings_tables.php
database/migrations/2026_02_21_120001_create_savings_market_rates_table.php
database/migrations/2026_02_21_130000_add_mpaa_fields_to_dc_pensions.php
database/migrations/2026_02_21_130000_add_projection_columns_to_iht_calculations.php
database/migrations/2026_02_21_130001_add_carry_forward_fields_to_retirement_profiles.php
database/migrations/2026_02_21_130002_remove_risk_tolerance_from_retirement_profiles.php
database/migrations/2026_02_21_140000_add_result_json_to_iht_calculations.php
database/migrations/2026_02_21_200001_fix_payment_subscription_amount_to_decimal.php
database/migrations/2026_02_21_200002_add_soft_deletes_to_financial_models.php
database/migrations/2026_02_21_200003_add_joint_owner_foreign_keys_to_remaining_tables.php
database/migrations/2026_02_21_200004_add_missing_indexes_to_financial_tables.php
```

### Models (10 files)

```
app/Models/BusinessInterest.php
app/Models/Chattel.php
app/Models/DCPension.php
app/Models/DisabilityPolicy.php
app/Models/Estate/IHTCalculation.php
app/Models/RetirementProfile.php
app/Models/SavingsAccount.php
app/Models/SavingsGoal.php
app/Models/SavingsMarketRate.php
app/Models/SicknessIllnessPolicy.php
```

### Observers (2 files)

```
app/Observers/PropertyRiskObserver.php
app/Observers/UserRiskObserver.php
```

### Request Validation (19 files)

```
app/Http/Requests/Estate/StoreAssetRequest.php
app/Http/Requests/Estate/StoreLiabilityRequest.php
app/Http/Requests/Estate/StoreGiftRequest.php
app/Http/Requests/Estate/UpdateAssetRequest.php
app/Http/Requests/Estate/UpdateLiabilityRequest.php
app/Http/Requests/Estate/UpdateGiftRequest.php
app/Http/Requests/Protection/StoreCriticalIllnessPolicyRequest.php
app/Http/Requests/Protection/StoreDisabilityPolicyRequest.php
app/Http/Requests/Protection/StoreIncomeProtectionPolicyRequest.php
app/Http/Requests/Protection/StoreLifePolicyRequest.php
app/Http/Requests/Protection/StoreSicknessIllnessPolicyRequest.php
app/Http/Requests/Protection/UpdateCriticalIllnessPolicyRequest.php
app/Http/Requests/Protection/UpdateDisabilityPolicyRequest.php
app/Http/Requests/Protection/UpdateIncomeProtectionPolicyRequest.php
app/Http/Requests/Protection/UpdateLifePolicyRequest.php
app/Http/Requests/Protection/UpdateSicknessIllnessPolicyRequest.php
app/Http/Requests/Retirement/StoreDCPensionRequest.php
app/Http/Requests/Savings/StoreSavingsAccountRequest.php
app/Http/Requests/Savings/UpdateSavingsAccountRequest.php
```

### Resources (7 files)

```
app/Http/Resources/ChattelResource.php
app/Http/Resources/Protection/CriticalIllnessPolicyResource.php
app/Http/Resources/Protection/DisabilityPolicyResource.php
app/Http/Resources/Protection/IncomeProtectionPolicyResource.php
app/Http/Resources/Protection/LifeInsurancePolicyResource.php
app/Http/Resources/Protection/ProtectionProfileResource.php
app/Http/Resources/Protection/SicknessIllnessPolicyResource.php
```

### Routes (1 file)

```
routes/api.php
```

### Seeders (3 files)

```
database/seeders/DatabaseSeeder.php
database/seeders/PreviewUserSeeder.php
database/seeders/SavingsMarketRatesSeeder.php
```

### Services (27 files)

```
app/Services/Coordination/CashFlowCoordinator.php
app/Services/Estate/ComprehensiveEstatePlanService.php
app/Services/Estate/IHTCalculationService.php
app/Services/Estate/LifeCoverCalculator.php
app/Services/Estate/NetWorthAnalyzer.php
app/Services/Estate/TrustService.php
app/Services/Goals/GoalRiskService.php
app/Services/Investment/DividendTaxCalculator.php
app/Services/Investment/FeeAnalyzer.php
app/Services/Investment/MonteCarloSimulator.php
app/Services/Investment/PortfolioAnalyzer.php
app/Services/Investment/TaxEfficiencyCalculator.php
app/Services/Investment/Utilities/MatrixOperations.php
app/Services/NetWorth/NetWorthService.php
app/Services/Protection/AdequacyScorer.php
app/Services/Protection/CoverageGapAnalyzer.php
app/Services/Protection/RecommendationEngine.php
app/Services/Retirement/AnnualAllowanceChecker.php
app/Services/Retirement/ContributionOptimizer.php
app/Services/Retirement/PensionProjector.php
app/Services/Retirement/RequiredCapitalCalculator.php
app/Services/Retirement/RetirementIncomeService.php
app/Services/Retirement/RetirementProjectionService.php
app/Services/Risk/AutoRiskCalculator.php
app/Services/Risk/RiskPreferenceService.php
app/Services/Savings/ISATracker.php
app/Services/Savings/RateComparator.php
```

### Traits (2 files)

```
app/Traits/PolicyCRUDTrait.php
app/Traits/ResolvesExpenditure.php
```

---

## Complete Deployment Steps (in order)

```bash
# 1. LOCAL: Build frontend
./deploy/fynla-org/build.sh

# 2. Upload public/build/ directory to server
# 3. Upload all 98 PHP files listed above to their respective paths
# 4. Delete: app/Models/Estate/NetWorthStatement.php

# 5. SSH to server:
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# 6. Run migrations
php artisan migrate --force

# 7. Run seeders
php artisan db:seed --force

# 8. Clear caches
php artisan cache:clear && php artisan route:clear && php artisan config:clear && php artisan view:clear && php artisan optimize
```

---

## Summary by Module

| Module | PHP Files | Frontend Build | Migrations | Seeders |
|--------|-----------|---------------|------------|---------|
| Estate | 19 | YES | 2 | NO |
| Investment | 8 | NO | 0 | NO |
| Net Worth | 12 | YES | 2 | YES |
| Protection | 26 | YES | 0 | YES |
| Retirement | 12 | NO | 3 | YES |
| Risk | 6 | YES | 0 | NO |
| Savings | 15 | YES | 2 | YES |
| Cross-module | 0 | NO | 4 | NO |
| **Total** | **98** (deduplicated) | **YES** | **13** | **YES** |
