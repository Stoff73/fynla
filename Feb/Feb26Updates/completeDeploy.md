# Complete Deployment Guide — Feb 6 → Feb 26 Updates

**Baseline:** `2f07948` (Feb 6 deployment)
**HEAD:** `c09b69a` (Feb 26 refactor merge)
**Total changed PHP files:** 313 (excluding tests)

---

## RECOMMENDED: Upload Entire Directories

With 313 changed files across the entire `app/` tree, uploading individual files is impractical. Upload these **whole directories** via SiteGround File Manager to `~/www/fynla.org/public_html/`:

| Local Directory | Upload To | Why |
|---|---|---|
| `app/` | `public_html/app/` | 230+ changed files across all subdirectories |
| `config/` | `public_html/config/` | 9 changed config files |
| `database/migrations/` | `public_html/database/migrations/` | 37 new migrations |
| `database/seeders/` | `public_html/database/seeders/` | 8 changed seeders |
| `database/factories/` | `public_html/database/factories/` | 15 new/changed factories |
| `resources/views/` | `public_html/resources/views/` | 7 changed blade templates |
| `routes/api.php` | `public_html/routes/api.php` | Route changes |
| `public/build/` | `public_html/public/build/` | Frontend build |

---

## Files to DELETE from Server

These files were removed in the refactoring and will cause errors if left on server:

```
app/Casts/EncryptedDecimal.php
app/Casts/EncryptedString.php
app/Http/Controllers/Traits/HandleApiExceptions.php
app/Models/Estate/NetWorthStatement.php
app/Services/Estate/IHTCalculator.php
database/seeders/ComprehensiveDemoDataSeeder.php
database/seeders/DemoUserSeeder.php
```

---

## Environment Variables

Add to server `.env` (if not already present):

```
REVOLUT_API_KEY=sk_...
REVOLUT_PUBLIC_KEY=pk_...
REVOLUT_WEBHOOK_SECRET=...
REVOLUT_SANDBOX=false
PAYMENT_ENABLED=true
VITE_REVOLUT_PUBLIC_KEY=${REVOLUT_PUBLIC_KEY}
VITE_REVOLUT_SANDBOX=${REVOLUT_SANDBOX}
```

---

## Post-Upload SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migrations (already done — skip if migrations ran earlier today)
php artisan migrate

# Seed subscription plans (already done — skip if ran earlier today)
php artisan db:seed --class=SubscriptionPlanSeeder --force

# Full reseed (restores preview personas with new Goal dependencies)
php artisan db:seed --force

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Detailed File Lists (for reference)

### New PHP files (109 files)

```
# Console commands (7)
app/Console/Commands/CleanupOrphanedSessions.php
app/Console/Commands/CleanupPendingRegistrations.php
app/Console/Commands/ExpireTrials.php
app/Console/Commands/PurgeExpiredUserData.php
app/Console/Commands/SendDataRetentionWarnings.php
app/Console/Commands/SendRenewalReminderEmails.php
app/Console/Commands/SendTrialReminderEmails.php

# Controllers (3)
app/Http/Controllers/Api/LifeEventAllocationController.php
app/Http/Controllers/Api/PaymentController.php
app/Http/Controllers/Api/WebhookController.php

# Middleware (2)
app/Http/Middleware/CheckSubscription.php
app/Http/Middleware/SecurityHeaders.php

# Form requests (6)
app/Http/Requests/Estate/StoreAssetRequest.php
app/Http/Requests/Estate/StoreGiftRequest.php
app/Http/Requests/Estate/StoreLiabilityRequest.php
app/Http/Requests/Estate/UpdateAssetRequest.php
app/Http/Requests/Estate/UpdateGiftRequest.php
app/Http/Requests/Estate/UpdateLiabilityRequest.php

# Resources (6)
app/Http/Resources/Protection/CriticalIllnessPolicyResource.php
app/Http/Resources/Protection/DisabilityPolicyResource.php
app/Http/Resources/Protection/IncomeProtectionPolicyResource.php
app/Http/Resources/Protection/LifeInsurancePolicyResource.php
app/Http/Resources/Protection/ProtectionProfileResource.php
app/Http/Resources/Protection/SicknessIllnessPolicyResource.php

# Mail classes (6)
app/Mail/DataDeletionConfirmation.php
app/Mail/DataRetentionWarning.php
app/Mail/PaymentConfirmation.php
app/Mail/SubscriptionCancellation.php
app/Mail/SubscriptionRenewalReminder.php
app/Mail/TrialExpirationReminder.php

# Models (5)
app/Models/LifeEventAllocation.php
app/Models/Payment.php
app/Models/SavingsMarketRate.php
app/Models/Subscription.php
app/Models/SubscriptionPlan.php

# Observers (3)
app/Observers/InvestmentAccountGoalObserver.php
app/Observers/LifeEventMonteCarloObserver.php
app/Observers/SavingsAccountGoalObserver.php

# Services (14)
app/Services/Estate/TrustValuationService.php
app/Services/Goals/FinancialForecastService.php
app/Services/Goals/GoalCalculationService.php
app/Services/Goals/GoalStrategyService.php
app/Services/Goals/LifeEventAllocationService.php
app/Services/Goals/LifeEventCashFlowService.php
app/Services/Goals/LifeEventIntegrationService.php
app/Services/Investment/DividendTaxCalculator.php
app/Services/Investment/EmployeeSchemeCalculationService.php
app/Services/Investment/ReturnCalculationService.php
app/Services/Payment/DataPurgeService.php
app/Services/Payment/RevolutService.php
app/Services/Payment/TrialService.php
app/Services/Property/PropertyCalculationService.php
app/Services/Shared/MonteCarloEngine.php

# Traits (3)
app/Traits/ResolvesExpenditure.php
app/Traits/ResolvesIncome.php
app/Traits/TracksGoalContributions.php

# Config (2)
config/investment_platforms.php
config/mortgage.php

# Factories (15)
database/factories/Estate/AssetFactory.php
database/factories/Estate/BequestFactory.php
database/factories/Estate/GiftFactory.php
database/factories/Estate/IHTCalculationFactory.php
database/factories/Estate/IHTProfileFactory.php
database/factories/Estate/WillFactory.php
database/factories/ExpenditureProfileFactory.php
database/factories/GoalContributionFactory.php
database/factories/GoalFactory.php
database/factories/Investment/InvestmentPlanFactory.php
database/factories/Investment/RebalancingActionFactory.php
database/factories/LifeEventFactory.php
database/factories/PaymentFactory.php
database/factories/RetirementProfileFactory.php
database/factories/SubscriptionFactory.php

# Migrations (37)
database/migrations/2026_02_12_100001_create_subscriptions_table.php
database/migrations/2026_02_12_100002_create_payments_table.php
database/migrations/2026_02_12_100003_add_plan_fields_to_users_table.php
database/migrations/2026_02_12_100004_create_trial_reminder_log_table.php
database/migrations/2026_02_12_100005_add_plan_fields_to_pending_registrations_table.php
database/migrations/2026_02_17_120040_add_account_name_to_savings_accounts_table.php
database/migrations/2026_02_19_120000_add_joint_owner_id_to_cash_accounts_table.php
database/migrations/2026_02_19_120001_add_linked_user_id_to_family_members_table.php
database/migrations/2026_02_20_000001_add_expires_at_to_pending_registrations_table.php
database/migrations/2026_02_20_120000_assign_roles_to_existing_users.php
database/migrations/2026_02_20_130000_drop_legacy_role_column_from_users.php
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
database/migrations/2026_02_21_200005_add_verification_attempt_counters.php
database/migrations/2026_02_22_130000_widen_encrypted_columns_to_text.php
database/migrations/2026_02_23_120001_create_goal_dependencies_table.php
database/migrations/2026_02_23_120002_add_linked_investment_account_to_goals.php
database/migrations/2026_02_24_100001_create_subscription_plans_table.php
database/migrations/2026_02_24_100002_add_revolut_ids_to_users_and_subscriptions.php
database/migrations/2026_02_24_100003_add_cancelled_at_to_subscriptions_table.php
database/migrations/2026_02_24_100004_add_cancellation_reason_to_subscriptions_table.php
database/migrations/2026_02_24_100005_add_data_retention_starts_at_to_subscriptions_table.php
database/migrations/2026_02_24_100006_create_renewal_reminder_log_table.php
database/migrations/2026_02_24_100007_add_description_to_payments_table.php
database/migrations/2026_02_24_100008_create_data_retention_email_log_table.php
database/migrations/2026_02_24_100009_add_soft_deletes_to_users_table.php
database/migrations/2026_02_24_120001_create_life_event_allocations_table.php
database/migrations/2026_02_24_120002_update_life_event_allocations_columns.php
database/migrations/2026_02_25_100001_add_columns_to_payments_table.php

# Seeders (2)
database/seeders/SavingsMarketRatesSeeder.php
database/seeders/SubscriptionPlanSeeder.php

# Blade templates (6)
resources/views/emails/data-deletion-confirmation.blade.php
resources/views/emails/data-retention-warning.blade.php
resources/views/emails/payment-confirmation.blade.php
resources/views/emails/subscription-cancellation.blade.php
resources/views/emails/subscription-renewal-reminder.blade.php
resources/views/emails/trial-expiration-reminder.blade.php
```

### Modified PHP files (197 files)

```
# Agents (7)
app/Agents/BaseAgent.php
app/Agents/CoordinatingAgent.php
app/Agents/EstateAgent.php
app/Agents/GoalsAgent.php
app/Agents/ProtectionAgent.php
app/Agents/RetirementAgent.php
app/Agents/SavingsAgent.php

# Console (2)
app/Console/Commands/EncryptExistingData.php
app/Console/Kernel.php

# Exceptions (1)
app/Exceptions/FinancialCalculationException.php

# Controllers (31)
app/Http/Controllers/Api/AdminController.php
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/BugReportController.php
app/Http/Controllers/Api/BusinessInterestController.php
app/Http/Controllers/Api/ChattelController.php
app/Http/Controllers/Api/DashboardController.php
app/Http/Controllers/Api/Estate/GiftingController.php
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Controllers/Api/Estate/LifePolicyController.php
app/Http/Controllers/Api/Estate/WillController.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/FamilyMembersController.php
app/Http/Controllers/Api/GoalsController.php
app/Http/Controllers/Api/Investment/AssetLocationController.php
app/Http/Controllers/Api/Investment/EfficientFrontierController.php
app/Http/Controllers/Api/Investment/FeeImpactController.php
app/Http/Controllers/Api/Investment/GoalProgressController.php
app/Http/Controllers/Api/Investment/InvestmentPlanController.php
app/Http/Controllers/Api/Investment/InvestmentScenarioController.php
app/Http/Controllers/Api/Investment/ModelPortfolioController.php
app/Http/Controllers/Api/Investment/PerformanceAttributionController.php
app/Http/Controllers/Api/Investment/RebalancingActionsController.php
app/Http/Controllers/Api/Investment/RebalancingCalculationController.php
app/Http/Controllers/Api/Investment/RebalancingStrategiesController.php
app/Http/Controllers/Api/Investment/TaxOptimizationController.php
app/Http/Controllers/Api/InvestmentController.php
app/Http/Controllers/Api/LetterToSpouseController.php
app/Http/Controllers/Api/LifeEventController.php
app/Http/Controllers/Api/MFAController.php
app/Http/Controllers/Api/MortgageController.php
app/Http/Controllers/Api/NetWorthController.php
app/Http/Controllers/Api/OnboardingController.php
app/Http/Controllers/Api/PasswordResetController.php
app/Http/Controllers/Api/PersonalAccountsController.php
app/Http/Controllers/Api/Plans/InvestmentSavingsPlanController.php
app/Http/Controllers/Api/PortfolioOptimizationController.php
app/Http/Controllers/Api/PreviewController.php
app/Http/Controllers/Api/PropertyController.php
app/Http/Controllers/Api/ProtectionController.php
app/Http/Controllers/Api/RecommendationsController.php
app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php
app/Http/Controllers/Api/RetirementController.php
app/Http/Controllers/Api/RiskPreferenceController.php
app/Http/Controllers/Api/SavingsController.php
app/Http/Controllers/Api/SessionController.php
app/Http/Controllers/Api/Settings/AssumptionsController.php
app/Http/Controllers/Api/UserProfileController.php

# Kernel & Middleware (6)
app/Http/Kernel.php
app/Http/Middleware/EnsureMFAVerified.php
app/Http/Middleware/HasPermission.php
app/Http/Middleware/HasRole.php
app/Http/Middleware/IsAdmin.php
app/Http/Middleware/PreviewWriteInterceptor.php
app/Http/Middleware/SanitizeInput.php

# Form requests (12)
app/Http/Requests/Documents/UploadDocumentRequest.php
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
app/Http/Requests/RegisterRequest.php
app/Http/Requests/Retirement/StoreDCPensionRequest.php
app/Http/Requests/Savings/StoreSavingsAccountRequest.php
app/Http/Requests/Savings/UpdateSavingsAccountRequest.php
app/Http/Requests/StoreFamilyMemberRequest.php
app/Http/Requests/StoreTaxConfigurationRequest.php
app/Http/Requests/UpdateFamilyMemberRequest.php

# Resources (3)
app/Http/Resources/ChattelResource.php
app/Http/Resources/GoalResource.php
app/Http/Resources/MortgageResource.php
app/Http/Resources/SavingsAccountResource.php

# Models (24)
app/Models/ActuarialLifeTable.php
app/Models/BusinessInterest.php
app/Models/CashAccount.php
app/Models/Chattel.php
app/Models/CriticalIllnessPolicy.php
app/Models/DBPension.php
app/Models/DCPension.php
app/Models/DisabilityPolicy.php
app/Models/EmailVerificationCode.php
app/Models/Estate/Asset.php
app/Models/Estate/Bequest.php
app/Models/Estate/Gift.php
app/Models/Estate/IHTCalculation.php
app/Models/Estate/Liability.php
app/Models/Estate/Trust.php
app/Models/Estate/Will.php
app/Models/ExpenditureProfile.php
app/Models/FamilyMember.php
app/Models/Goal.php
app/Models/IncomeProtectionPolicy.php
app/Models/Investment/Holding.php
app/Models/Investment/InvestmentAccount.php
app/Models/LetterToSpouse.php
app/Models/LifeEvent.php
app/Models/LifeInsurancePolicy.php
app/Models/LoginAttempt.php
app/Models/Mortgage.php
app/Models/PasswordResetSession.php
app/Models/PendingRegistration.php
app/Models/Permission.php
app/Models/Property.php
app/Models/RetirementProfile.php
app/Models/SavingsAccount.php
app/Models/SavingsGoal.php
app/Models/SicknessIllnessPolicy.php
app/Models/User.php

# Observers (2)
app/Observers/PropertyRiskObserver.php
app/Observers/UserRiskObserver.php

# Providers (1)
app/Providers/EventServiceProvider.php

# Services (33)
app/Services/Auth/PermissionService.php
app/Services/Auth/SessionService.php
app/Services/Business/BusinessInterestService.php
app/Services/Coordination/CashFlowCoordinator.php
app/Services/Documents/DocumentUploadService.php
app/Services/Estate/ComprehensiveEstatePlanService.php
app/Services/Estate/FutureValueCalculator.php
app/Services/Estate/GiftingStrategyOptimizer.php
app/Services/Estate/IHTCalculationService.php
app/Services/Estate/LifeCoverCalculator.php
app/Services/Estate/NetWorthAnalyzer.php
app/Services/Estate/TrustService.php
app/Services/Goals/GoalAffordabilityService.php
app/Services/Goals/GoalAssignmentService.php
app/Services/Goals/GoalProgressService.php
app/Services/Goals/GoalRiskService.php
app/Services/Goals/GoalsProjectionService.php
app/Services/Goals/LifeEventService.php
app/Services/Investment/ContributionOptimizer.php
app/Services/Investment/FeeAnalyzer.php
app/Services/Investment/InvestmentProjectionService.php
app/Services/Investment/MonteCarloSimulator.php
app/Services/Investment/PortfolioAnalyzer.php
app/Services/Investment/Tax/ISAAllowanceOptimizer.php
app/Services/Investment/Tax/TaxOptimizationAnalyzer.php
app/Services/Investment/TaxEfficiencyCalculator.php
app/Services/Investment/Utilities/MatrixOperations.php
app/Services/NetWorth/NetWorthService.php
app/Services/Onboarding/OnboardingService.php
app/Services/Protection/AdequacyScorer.php
app/Services/Protection/CoverageGapAnalyzer.php
app/Services/Protection/RecommendationEngine.php
app/Services/Retirement/AnnualAllowanceChecker.php
app/Services/Retirement/ContributionOptimizer.php
app/Services/Retirement/PensionProjector.php
app/Services/Retirement/RequiredCapitalCalculator.php
app/Services/Retirement/RetirementIncomeService.php
app/Services/Retirement/RetirementProjectionService.php
app/Services/Retirement/RetirementStrategyService.php
app/Services/Risk/AutoRiskCalculator.php
app/Services/Risk/RiskPreferenceService.php
app/Services/Savings/ISATracker.php
app/Services/Savings/RateComparator.php
app/Services/UserProfile/ModuleDataRequirementsService.php
app/Services/UserProfile/UserProfileService.php

# Traits (2)
app/Traits/Auditable.php
app/Traits/PolicyCRUDTrait.php

# Config (7)
config/app.php
config/auth.php
config/cors.php
config/database.php
config/sanctum.php
config/services.php
config/session.php

# Seeders (6)
database/seeders/AdminUserSeeder.php
database/seeders/DatabaseSeeder.php
database/seeders/PreviewUserSeeder.php
database/seeders/TestUsersSeeder.php

# Blade (1)
resources/views/app.blade.php

# Routes (1)
routes/api.php
```

---

## Verification Checklist

### Core modules (retirement 500 errors were caused by missing PHP files)
- [ ] Dashboard loads for all preview personas
- [ ] Retirement module: `/api/retirement` returns 200
- [ ] Retirement projections load without errors
- [ ] Annual allowance calculation works
- [ ] Estate module loads correctly
- [ ] Savings module loads correctly
- [ ] Investment module loads correctly
- [ ] Protection module loads correctly
- [ ] Goals module loads correctly
- [ ] Net Worth module loads correctly
- [ ] No 500 errors in browser console

### Payment flow
- [ ] Trial banner shows with "Upgrade Now" button
- [ ] Plan selection modal opens with 3 plans
- [ ] Checkout page loads with Revolut widget
- [ ] Subscription management tab shows correct amounts

### Auth flow
- [ ] Login works correctly
- [ ] Register with new email — verification flow works, trial starts
- [ ] Preview personas accessible from landing page

---

## Change Summary

| Category | New | Modified | Deleted |
|----------|-----|----------|---------|
| Agents | 0 | 7 | 0 |
| Console commands | 7 | 2 | 0 |
| Controllers | 3 | 44 | 0 |
| Middleware | 2 | 6 | 0 |
| Form requests | 6 | 12 | 0 |
| Resources | 6 | 4 | 0 |
| Mail classes | 6 | 0 | 0 |
| Models | 5 | 36 | 1 |
| Observers | 3 | 2 | 0 |
| Services | 14 | 33 | 1 |
| Traits | 3 | 2 | 0 |
| Config | 2 | 7 | 0 |
| Migrations | 37 | 0 | 0 |
| Seeders | 2 | 4 | 2 |
| Factories | 15 | 0 | 0 |
| Blade templates | 6 | 1 | 0 |
| Routes | 0 | 1 | 0 |
| Casts | 0 | 0 | 2 |
| Other | 0 | 2 | 1 |
| **Total** | **117** | **163** | **7** |
| Frontend build | `public/build/` (rebuilt) | | |
