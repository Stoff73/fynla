# Deployment Guide: Codebase Refactoring

## Phase 1: Dead Code Removal (Complete)

### Rebuild Required: YES (frontend)

The `vuedraggable` dependency was removed and `store/index.js` was modified. A frontend rebuild is required.

```bash
./deploy/fynla-org/build.sh
```

### Files Deleted (remove from server)

**PHP files to delete on server:**

```
app/Services/Estate/IHTCalculator.php
app/Http/Controllers/Traits/HandleApiExceptions.php
app/Casts/EncryptedDecimal.php
app/Casts/EncryptedString.php
database/seeders/ComprehensiveDemoDataSeeder.php
database/seeders/DemoUserSeeder.php
tests/Unit/Services/Estate/IHTCalculatorTest.php
tests/Unit/ExampleTest.php
tests/Feature/ExampleTest.php
```

**JS/Vue files to delete on server (only matters if source files are deployed; otherwise rebuild handles it):**

```
resources/js/store/modules/user.js
resources/js/store/helpers/          (entire directory)
resources/js/utils/asyncAction.js
resources/js/views/Investment/AccountDetailView.vue
resources/js/views/Retirement/ContributionsAllowances.vue
resources/js/views/Retirement/DecumulationPlanning.vue
resources/js/views/Retirement/PortfolioAnalysis.vue
resources/js/views/Retirement/RetirementReadiness.vue
resources/js/components/NetWorth/PropertyForm.vue
resources/js/services/postcodeService.js
resources/js/services/taxInfoService.js
resources/js/components/Protection/PolicyDetails.vue
resources/js/components/Protection/Recommendations.vue
resources/js/components/Savings/WhatIfScenarios.vue
resources/js/components/Investment/ACCOUNT_COMPONENTS_README.md
resources/js/components/Investment/INTEGRATION_EXAMPLE.md
```

### Files Modified (upload to server)

**PHP files to upload:**

| File | Change |
|------|--------|
| `app/Agents/CoordinatingAgent.php` | Removed duplicate PHPDoc block |
| `tests/Feature/TaxConfigurationTest.php` | Replaced IHTCalculator reference with TaxConfigService |
| `tests/Pest.php` | Removed unused `toBeOne` expectation and empty `something()` function |

**Frontend files to upload (or just rebuild):**

| File | Change |
|------|--------|
| `resources/js/store/index.js` | Removed `user` module import and registration |
| `resources/js/CLAUDE.md` | Removed `asyncAction.js` from utilities table |
| `package.json` | Removed `vuedraggable` dependency |
| `package-lock.json` | Updated after uninstall |

### Upload & Deploy

**Option A: Rebuild + upload build (recommended)**

```bash
# 1. Build locally
./deploy/fynla-org/build.sh

# 2. Upload public/build/ directory via SiteGround File Manager
#    to ~/www/fynla.org/public_html/public/build/

# 3. Upload modified PHP files:
#    - app/Agents/CoordinatingAgent.php
#    - tests/Feature/TaxConfigurationTest.php (if tests are on server)
#    - tests/Pest.php (if tests are on server)

# 4. Delete PHP files from server:
#    - app/Services/Estate/IHTCalculator.php
#    - app/Http/Controllers/Traits/HandleApiExceptions.php
#    - app/Casts/EncryptedDecimal.php
#    - app/Casts/EncryptedString.php
#    - database/seeders/ComprehensiveDemoDataSeeder.php
#    - database/seeders/DemoUserSeeder.php

# 5. SSH and clear caches
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Phase 2: Consolidate Duplicates (Complete)

### Rebuild Required: YES (frontend)

```bash
./deploy/fynla-org/build.sh
```

### Files Deleted (remove from server)

```
resources/js/components/Common/ConfirmationModal.vue
resources/js/components/Onboarding/SkipConfirmationModal.vue
resources/js/utils/dates.js
```

### PHP Files Modified (upload to server)

| File | Change |
|------|--------|
| `app/Agents/BaseAgent.php` | Added `use FormatsCurrency` trait; removed `formatCurrency()`, `formatPercentage()`, `getCurrentTaxYear()`, `calculatePercentageChange()`, `calculateCompoundGrowth()`, `calculatePresentValue()`, `validateRequired()`, `calculateAge()` |
| `app/Agents/RetirementAgent.php` | Injected `TaxConfigService`; replaced `$this->getCurrentTaxYear()` with `$this->taxConfig->getTaxYear()` |
| `app/Services/Savings/ISATracker.php` | Replaced local `getCurrentTaxYear()` with `$this->taxConfig->getTaxYear()` |
| `app/Services/Business/BusinessInterestService.php` | Replaced local `getCurrentTaxYear()` and `getNextTaxYear()` with TaxConfigService |
| `app/Services/Retirement/RetirementStrategyService.php` | Replaced local `getCurrentTaxYear()` with `$this->taxConfig->getTaxYear()` |
| `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php` | Replaced local `getCurrentTaxYear()` with `$this->taxConfig->getTaxYear()` |
| `app/Services/Investment/ContributionOptimizer.php` | Replaced local `getCurrentTaxYear()` with `$this->taxConfig->getTaxYear()` |
| `app/Services/Investment/Tax/ISAAllowanceOptimizer.php` | Replaced local `getCurrentTaxYear()` with `$this->taxConfig->getTaxYear()` |
| `app/Services/CLAUDE.md` | Updated BaseAgent helpers list |

### Vue Files Modified (rebuild handles these)

**formatCurrency consolidation (17 files):**

| File | Change |
|------|--------|
| `resources/js/components/Onboarding/steps/IncomeStep.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/Onboarding/steps/CompletionStep.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/Onboarding/steps/LiabilitiesStep.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/Onboarding/steps/AssetsStep.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/FamilyMembers.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/ExpenditureForm.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/BalanceSheetView.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/LiabilitiesOverview.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/BalanceSheetTab.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/TaxIncomeCard.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/ProfitAndLossView.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/CashflowView.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/AssetsOverview.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/IncomeStatementTab.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/TaxSummaryCard.vue` | Import from `@/utils/currency`, removed local definition |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Import from `@/utils/currency`, removed local definition |

**ConfirmationModal merge (11 files):**

| File | Change |
|------|--------|
| `resources/js/components/NetWorth/ChattelDetailInline.vue` | Migrated to ConfirmDialog |
| `resources/js/components/NetWorth/BusinessInterestDetailInline.vue` | Migrated to ConfirmDialog |
| `resources/js/components/NetWorth/BusinessInterestsList.vue` | Migrated to ConfirmDialog |
| `resources/js/components/NetWorth/ChattelsList.vue` | Migrated to ConfirmDialog |
| `resources/js/components/NetWorth/PensionDetailInline.vue` | Migrated to ConfirmDialog |
| `resources/js/components/NetWorth/Property/PropertyDetailInline.vue` | Migrated to ConfirmDialog |
| `resources/js/components/NetWorth/InvestmentDetailInline.vue` | Migrated to ConfirmDialog |
| `resources/js/components/UserProfile/FamilyMembers.vue` | Migrated to ConfirmDialog |
| `resources/js/components/Protection/PolicyDetail.vue` | Migrated to ConfirmDialog |
| `resources/js/views/Savings/SavingsAccountDetail.vue` | Migrated to ConfirmDialog |
| `resources/js/views/Savings/SavingsAccountDetailInline.vue` | Migrated to ConfirmDialog |

**Other merges:**

| File | Change |
|------|--------|
| `resources/js/components/Onboarding/OnboardingWizard.vue` | Replaced SkipConfirmationModal with ConfirmDialog |
| `resources/js/utils/dateFormatter.js` | Added `getTaxYearStart` and `getTaxYearEnd` from dates.js |
| `resources/js/CLAUDE.md` | Consolidated dates utility documentation |

### Upload & Deploy

```bash
# 1. Build locally
./deploy/fynla-org/build.sh

# 2. Upload public/build/ to server

# 3. Upload modified PHP files (9 files):
#    - app/Agents/BaseAgent.php
#    - app/Agents/RetirementAgent.php
#    - app/Services/Savings/ISATracker.php
#    - app/Services/Business/BusinessInterestService.php
#    - app/Services/Retirement/RetirementStrategyService.php
#    - app/Services/Investment/Tax/TaxOptimizationAnalyzer.php
#    - app/Services/Investment/ContributionOptimizer.php
#    - app/Services/Investment/Tax/ISAAllowanceOptimizer.php
#    - app/Services/CLAUDE.md (if docs are on server)

# 4. SSH and clear caches
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Phase 3: Architectural Fixes

### Rebuild Required: NO (PHP only)

### Files to modify

| File | Change |
|------|--------|
| `app/Agents/CoordinatingAgent.php` | Inject EstateAgent + GoalsAgent, replace hardcoded data |
| 10 models with `joint_owner_id` | Add `use HasJointOwnership` trait |
| `app/Agents/BaseAgent.php` | Remove unused methods, standardise cache methods |
| All agent subclasses | Standardise `invalidateCache()` naming |
| `app/Agents/RetirementAgent.php` | Remove 5 redundant Investment service injections |
| `app/Services/Shared/MonteCarloEngine.php` | DELETE (consolidate into MonteCarloSimulator) |
| `app/Services/Investment/ModelPortfolio/AssetAllocationOptimizer.php` | Rename to avoid name clash |

---

## Phase 4: Store & Frontend Consistency

### Rebuild Required: YES (frontend)

### Files to modify

| Scope | Files | Change |
|-------|-------|--------|
| Mutation naming | 12 Vuex store modules | Rename `SET_*` to `set*` |
| Import paths | 4 store modules | Change relative to `@/` imports |
| Service wrappers | 3 new service files | Create thin API wrappers |
| Ownership util | `currencyMixin.js`, `ownership.js` | Consolidate |

---

## Phase 5: Test & Config Cleanup

### Rebuild Required: NO (config/test only)

### Files to modify

| File | Change |
|------|--------|
| New test files (3) | Payment/subscription test coverage |
| 11 test files | Migrate PHPUnit style to Pest |
| `phpunit.xml` | Add Integration test suite |
| `.env.example` | Add missing keys, remove unused entries |
| `.env` | Fix APP_NAME to Fynla |
| 32 factory files | Update `$this->faker` to `fake()` |

---

## Phase 6: Project Root Cleanup

### Rebuild Required: NO

### Actions

Move non-application files to `.admin/` directory. No server deployment needed -- these are development-only files.

---

## Phase 7: Oversized Components

### Rebuild Required: YES (when each component is split)

Split on an as-needed basis when next modifying each component. No batch deployment needed.
