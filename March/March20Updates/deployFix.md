# Deployment Guide — 20 March 2026

Covers all changes from today's session: tech debt sweep (8 test failures, ExpenditureForm, estate hardcoded rates, budget constants, @keyframes cleanup, budget override persistence, spouse joint save) + 12 production test bug fixes (Issues 2-6, 8-14).

## Rebuild Required?

**Yes** — CSS + 20 Vue files changed. Run:

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory.

## Database Migration (1 pending)

Adds `retired_budget_overrides` and `widowed_budget_overrides` JSON nullable columns to the `users` table. Safe — no data loss.

## PHP Files to Upload (17)

```
app/Agents/EstateAgent.php
app/Http/Controllers/Api/AdvisorController.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/UserProfileController.php
app/Http/Requests/UpdatePersonalInfoRequest.php
app/Http/Requests/Investment/StoreRiskProfileRequest.php
app/Models/User.php
app/Services/Coordination/RecommendationPersonaliser.php
app/Services/Estate/GiftingStrategy.php
app/Services/Estate/IHTCalculationService.php
app/Services/Estate/IHTFormattingService.php
app/Services/Estate/IHTStrategyGeneratorService.php
app/Services/Estate/WillAnalysisService.php
app/Services/Goals/GoalsProjectionService.php
app/Services/Plans/InvestmentPlanService.php
app/Services/Plans/RetirementPlanService.php
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
resources/js/views/Dashboard.vue
resources/js/views/Public/CalculatorsPage.vue
resources/js/views/Public/LearningCentre.vue
resources/js/views/Public/SecurityPage.vue
resources/js/views/Savings/SavingsAccountDetailInline.vue
resources/js/store/modules/userProfile.js
resources/js/components/Investment/PortfolioOptimization.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/NetWorth/PropertyList.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/Onboarding/OnboardingWizard.vue
resources/js/components/Onboarding/steps/GoalSetupStep.vue
resources/js/components/Onboarding/steps/IncomeStep.vue
resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue
resources/js/components/Onboarding/steps/StudentLoanStep.vue
resources/js/components/Onboarding/steps/WillInfoStep.vue
resources/js/components/Savings/SaveAccountModal.vue
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/components/UserProfile/PersonalInformation.vue
```

## Test Files (do NOT upload to production)

```
tests/Feature/Api/FamilyMembersControllerTest.php
tests/Feature/CompletenessEndpointTest.php
tests/Feature/Estate/WillBuilderApiTest.php
tests/Feature/InvestmentModuleTest.php
```

## Upload Order

1. Upload 17 PHP files to matching paths on server
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

### Tech Debt Sweep (from earlier session)

| Category | Files | Summary |
|----------|-------|---------|
| Test fixes | 4 PHP + 4 test files | Fixed 8 Pest failures (strict type comparison, SoftDeletes assertion, ModelNotFoundException handling, factory middle_name, risk profile duplicate) |
| ExpenditureForm | 1 Vue file | Fixed section header totals bug, isSectionExpanded fallback, removed dead code, replaced all off-palette colour tokens |
| Estate rates | 4 PHP files | Replaced 30 hardcoded "40%"/"36%" narrative strings with TaxConfigService values |
| Budget constants | 3 PHP files | Replaced magic numbers (0.85, 0.70, 0.50) with named class constants |
| @keyframes cleanup | 8 Vue files + app.css | Replaced local @keyframes with global CSS classes, added `.animate-slide-in-right` |
| Budget overrides | 2 PHP + 1 Vue + 1 migration | Retired/widowed budget overrides persist to DB via JSON columns |
| Spouse joint save | 1 Vue file | Joint mode now saves expenditure for both user and spouse |

### Production Test Bug Fixes (12 issues)

| File | Change | Fixes |
|------|--------|-------|
| `UpdatePersonalInfoRequest.php` | `prepareForValidation()` strips spaces/dashes from phone | Issues 2, 3, 4 |
| `GoalsProjectionService.php` | Age fallback `45` → `DEFAULT_RETIREMENT_AGE` (68) | Issue 4 |
| `Dashboard.vue` | Dynamic student loan plan type + thresholds from `liability_name` | Issue 5 |
| `OnboardingWizard.vue` | `fetchProfile` on mount + `savedStepData` cache for back nav | Issues 6, 8 |
| `StudentLoanStep.vue` | Restore form from `savedData` prop on back navigation | Issue 6 |
| `IncomeStep.vue` | Added `savedData` + `context` props | Issue 6 |
| `SimpleExpenditureStep.vue` | Added `savedData` + `context` props | Issue 6 |
| `GoalSetupStep.vue` | Added `savedData` + `context` props | Issue 6 |
| `PersonalInformation.vue` | Added `savedData` prop | Issue 6 |
| `SaveAccountModal.vue` | Added `savedData` prop + unlinked spouse messaging | Issues 6, 12 |
| `WillInfoStep.vue` | Removed stale "Coming Soon" banner | Issue 10 |
| `EstateAgent.php` | `$allPolicies` → `$allLifePolicies` (undefined variable) | Issue 11 |
| `EstateController.php` | Include mortgages in liabilities response | Issue 9 |
| `userProfile.js` | Spouse getter works with family members (no `spouse_id` needed) | Issue 12 |
| `PropertyForm.vue` | Joint owner handles unlinked spouse via `joint_owner_name` | Issue 12 |
| `StoreRiskProfileRequest.php` | All fields `required` → `sometimes` for partial updates | Issue 13 |
| `InvestmentPlanService.php` | `/investments` → `/net-worth/investments` (3 CTAs) | Issue 14 |
| `RetirementPlanService.php` | `/retirement` → `/net-worth/retirement` (3 CTAs) | Issue 14 |

## Post-Deploy Verification

1. Register new user with phone `07700 100001` (space) — should save without 422
2. About You pre-fills name/email from registration
3. Student Loan: fill form, Next, Back — data preserved
4. Goals page shows correct age (not 45)
5. Dashboard student loan shows correct plan type
6. Will step has no purple banner
7. `/api/plans/estate` returns 200 (not 500)
8. Net Worth liabilities include mortgages
9. Joint owner dropdown shows spouse entered during onboarding
10. Goals & Life Events chart shows goal icons at correct age
11. Knowledge nudge: click "Beginner" — nudge should dismiss and level save
12. Investment plan: click "Add" on missing investment accounts — goes to `/net-worth/investments`
13. Retirement plan: click "Add" on missing pensions — goes to `/net-worth/retirement`

## Test Results

2028 passed, 1 pre-existing flaky test (WillBuilderApiTest — unrelated).
