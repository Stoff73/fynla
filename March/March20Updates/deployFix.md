# Deployment Guide — 20 March 2026

Covers all changes from today: tech debt sweep + 12 production bug fixes + onboarding system refactor (Era consolidation, inline forms, contextual sidebar, family step additions, validation removal) + occupation lookup fix.

## Rebuild Required?

**Yes** — CSS + 40+ Vue files changed. Run:

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory.

## Database Migrations (2 pending)

1. `2026_03_20_074942_add_budget_overrides_to_users_table.php` — Adds `retired_budget_overrides` and `widowed_budget_overrides` JSON nullable columns. Safe.
2. `2026_03_20_100000_make_enum_columns_nullable.php` — Makes 15 enum columns nullable (employment_status, marital_status, gender, etc.) to support optional onboarding fields. Safe.

## Database Seeder (1 new — MUST RUN on production)

`OccupationCodeSeeder` — Seeds 406 ONS SOC 2020 occupation codes into `occupation_codes` table. Required for the occupation autocomplete on the Income step. **This table is currently empty on production.**

```bash
php artisan db:seed --class=OccupationCodeSeeder --force
```

## PHP Files to Upload (42)

### Services & Controllers (7)

```
app/Agents/EstateAgent.php
app/Http/Controllers/Api/EstateController.php
app/Services/Goals/GoalsProjectionService.php
app/Services/LifeStage/LifeStageService.php
app/Services/Plans/InvestmentPlanService.php
app/Services/Plans/RetirementPlanService.php
app/Services/Shared/CrossModuleAssetAggregator.php
```

### Form Requests — validation relaxed (31)

All `required` rules changed to `sometimes` for optional onboarding. Upload all:

```
app/Http/Requests/BusinessInterest/UpdateBusinessInterestRequest.php
app/Http/Requests/Documents/ConfirmExtractionRequest.php
app/Http/Requests/Documents/UploadDocumentRequest.php
app/Http/Requests/Estate/SaveWillDocumentRequest.php
app/Http/Requests/Estate/StoreAssetRequest.php
app/Http/Requests/Estate/StoreBequestRequest.php
app/Http/Requests/Estate/StoreGiftRequest.php
app/Http/Requests/Estate/StoreLiabilityRequest.php
app/Http/Requests/Estate/StoreLpaRequest.php
app/Http/Requests/Estate/UpdateLpaRequest.php
app/Http/Requests/Estate/UploadLpaRequest.php
app/Http/Requests/Goals/StoreGoalRequest.php
app/Http/Requests/Investment/StartMonteCarloRequest.php
app/Http/Requests/Investment/StoreHoldingRequest.php
app/Http/Requests/Investment/StoreInvestmentGoalRequest.php
app/Http/Requests/Investment/StoreRiskProfileRequest.php
app/Http/Requests/LoginRequest.php
app/Http/Requests/Onboarding/StoreJourneySelectionsRequest.php
app/Http/Requests/Protection/StoreIncomeProtectionPolicyRequest.php
app/Http/Requests/RegisterRequest.php
app/Http/Requests/StoreActionDefinitionRequest.php
app/Http/Requests/StoreClientActivityRequest.php
app/Http/Requests/StoreFamilyMemberRequest.php
app/Http/Requests/StoreInvestmentAccountRequest.php
app/Http/Requests/StoreInvestmentActionDefinitionRequest.php
app/Http/Requests/StoreLifeEventRequest.php
app/Http/Requests/StorePersonalAccountLineItemRequest.php
app/Http/Requests/StoreProtectionActionDefinitionRequest.php
app/Http/Requests/StoreRetirementActionDefinitionRequest.php
app/Http/Requests/StoreTaxConfigurationRequest.php
app/Http/Requests/UpdateDomicileInfoRequest.php
app/Http/Requests/UpdatePersonalInfoRequest.php
app/Http/Requests/V1/RegisterDeviceRequest.php
```

### Models (1)

```
app/Models/User.php
```

### Seeders (2)

```
database/seeders/DatabaseSeeder.php
database/seeders/OccupationCodeSeeder.php
```

## Migration Files to Upload (2)

```
database/migrations/2026_03_20_074942_add_budget_overrides_to_users_table.php
database/migrations/2026_03_20_100000_make_enum_columns_nullable.php
```

## Frontend Files to Delete on Server (9)

These components were deleted locally. Remove from server to keep clean:

```
resources/js/components/Onboarding/steps/SimplePersonalInfoStep.vue
resources/js/components/Onboarding/steps/SimpleIncomeStep.vue
resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue
resources/js/components/Onboarding/steps/SimpleSavingsAccountStep.vue
resources/js/components/Onboarding/steps/SimplePropertyMortgageStep.vue
resources/js/components/Onboarding/steps/BudgetingCompletionStep.vue
resources/js/components/Onboarding/steps/QuickAssetsStep.vue
resources/js/components/Onboarding/steps/BudgetingSteps.vue
resources/js/components/Onboarding/steps/JourneyCompletionStep.vue
```

Note: These are source files. The compiled build won't include them, but clean up to avoid confusion.

## Frontend (via build)

These are compiled into `public/build/` — upload the build directory, not individual files:

```
resources/js/constants/lifeStageConfig.js
resources/js/store/modules/userProfile.js
resources/js/views/Dashboard.vue
resources/js/components/Onboarding/OnboardingWizard.vue
resources/js/components/Onboarding/LearningMilestoneSidebar.vue
resources/js/components/Onboarding/steps/AssetsStep.vue
resources/js/components/Onboarding/steps/PersonalInfoStep.vue
resources/js/components/Onboarding/steps/IncomeStep.vue
resources/js/components/Onboarding/steps/GoalSetupStep.vue
resources/js/components/Onboarding/steps/StudentLoanStep.vue
resources/js/components/Onboarding/steps/WillInfoStep.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/Savings/SaveAccountModal.vue
resources/js/components/UserProfile/PersonalInformation.vue
resources/js/components/Shared/PostcodeLookup.vue
resources/js/components/BugReportModal.vue
resources/js/components/Advisor/ClientActivityForm.vue
resources/js/components/Estate/TrustForm.vue
resources/js/components/Estate/LpaWizardSteps/AttorneysStep.vue
resources/js/components/Estate/LpaWizardSteps/DecisionTypeStep.vue
resources/js/components/Estate/LpaWizardSteps/DonorDetailsStep.vue
resources/js/components/Estate/LpaWizardSteps/NotificationPersonsStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderExecutorsStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderGiftsStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderGuardiansStep.vue
resources/js/components/Estate/WillBuilder/steps/WillBuilderResiduaryStep.vue
resources/js/components/Investment/EmployeeShareSchemeFields.vue
resources/js/components/Investment/PrivateInvestmentFields.vue
resources/js/components/Investment/WhatIfScenariosBuilder.vue
resources/js/components/NetWorth/BusinessInterestForm.vue
resources/js/components/NetWorth/ChattelFormModal.vue
resources/js/components/Trusts/TrustFormModal.vue
resources/css/app.css
```

## Upload Order

1. Upload all PHP files (including seeders) to matching paths on server
2. Upload 2 migration files to `database/migrations/`
3. Delete 9 obsolete Vue files from server (optional — they're source files, not served)
4. Run `./deploy/fynla-org/build.sh` locally
5. Upload `public/build/` directory
6. SSH and run migrations + seed + clear caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate && php artisan db:seed --class=OccupationCodeSeeder --force && php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## What Changed

### Tech Debt Sweep (earlier session)

| Category | Files | Summary |
|----------|-------|---------|
| Test fixes | 4 PHP + 4 test files | Fixed 8 Pest failures |
| ExpenditureForm | 1 Vue file | Fixed section header totals bug |
| Estate rates | 4 PHP files | Replaced 30 hardcoded "40%"/"36%" with TaxConfigService |
| Budget constants | 3 PHP files | Replaced magic numbers with named constants |
| @keyframes cleanup | 8 Vue files + app.css | Replaced local @keyframes with global CSS classes |
| Budget overrides | 2 PHP + 1 Vue + 1 migration | Retired/widowed budget overrides persist to DB |
| Spouse joint save | 1 Vue file | Joint mode saves expenditure for both user and spouse |

### Production Bug Fixes (12 issues)

| File | Change | Fixes |
|------|--------|-------|
| `UpdatePersonalInfoRequest.php` | `prepareForValidation()` strips spaces/dashes from phone | Issues 2, 3, 4 |
| `GoalsProjectionService.php` | Age fallback `45` → `DEFAULT_RETIREMENT_AGE` (68) | Issue 4 |
| `Dashboard.vue` | Dynamic student loan plan type + thresholds | Issue 5 |
| `OnboardingWizard.vue` | `fetchProfile` on mount + `savedStepData` cache | Issues 6, 8 |
| `StudentLoanStep.vue` | Restore form from `savedData` prop | Issue 6 |
| `WillInfoStep.vue` | Removed stale "Coming Soon" banner | Issue 10 |
| `EstateAgent.php` | `$allPolicies` → `$allLifePolicies` | Issue 11 |
| `EstateController.php` | Include mortgages in liabilities response | Issue 9 |
| `PropertyForm.vue` | Joint owner handles unlinked spouse | Issue 12 |
| `InvestmentPlanService.php` | `/investments` → `/net-worth/investments` | Issue 14 |
| `RetirementPlanService.php` | `/retirement` → `/net-worth/retirement` | Issue 14 |

### Onboarding Refactor

See `onboardingFix.md` for full details. Summary:

| Change | Impact |
|--------|--------|
| Deleted 9 duplicate components (Era 2/3/4) | Cleaner codebase, single source of truth |
| Unified STEP_COMPONENTS (25+ → 13) | All journeys use same components |
| Single `assets` step with tab filtering | No more split step IDs |
| Inline forms (`context="onboarding"`) | Forms render in content area, not modals |
| Contextual sidebar (10 contexts) | Sidebar updates per tab and form state |
| Family step added to journeys 4 + 5 | Estate planning needs family data |
| All validation removed | No required fields during onboarding |
| 15 enum columns made nullable | Supports partial data entry |
| PersonalInfoStep field visibility | Fields hidden per journey via config |
| Returning user mode simplified | Always resumes life stage journey |

### Occupation Lookup Fix

| Change | Impact |
|--------|--------|
| `OccupationCodeSeeder` added to `DatabaseSeeder.php` | 406 ONS SOC 2020 codes now seeded on `db:seed` |
| `occupation_codes` table was empty | Autocomplete on Income step now returns results |

## Post-Deploy Verification

1. Register new user → select each journey → verify step count matches
2. Journey 1: only Cash tab visible on Assets step
3. Journey 2: Cash + Retirement + Investments tabs visible
4. Journeys 3-5: all 4 tabs visible
5. Click "+ Add Account" → form renders inline (not modal)
6. Sidebar updates when switching tabs and opening/closing forms
7. Journey 4 + 5: Family step appears after About You
8. Family sidebar shows tailored content per journey
9. All forms submit without validation errors (no required fields)
10. Dashboard shows correct step count and 100% after completing all steps
11. Returning user sees their journey progress, not welcome screen
12. **Occupation autocomplete**: type "soft" on Income step → should show "Software Developer", "Software Engineer" etc.
13. Will/Estate step renders for journeys 3, 4, and 5 with correct sidebar content
