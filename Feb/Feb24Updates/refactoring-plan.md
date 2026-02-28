# Fynla Codebase Refactoring & Cleanup Plan

**Generated:** 25 February 2026
**Codebase Version:** v0.8.1
**Scope:** Full codebase audit -- PHP backend, Vue frontend, tests, database, configuration

---

## Executive Summary

A comprehensive audit of the Fynla codebase identified **75 findings** across the PHP backend (25), Vue frontend (25), and tests/config/database (25). The codebase has accumulated technical debt typical of rapid feature development over ~2 months.

**Key metrics:**
- ~2,500+ lines of dead frontend code (unused views, stores, utilities)
- ~560 lines of dead backend code (orphaned seeders, legacy services)
- 17 components violating the `formatCurrency` convention
- 7 duplicated `getCurrentTaxYear()` implementations
- 99 database migrations ripe for squashing
- ~27MB of non-application files cluttering the project root
- Zero test coverage for the payment/subscription module

---

## Phase 1: Quick Wins -- Dead Code Removal (COMPLETE)

**Status:** All items completed on 25 Feb 2026. See `deployRefactor.md` for deployment instructions.

Zero-risk deletions of code confirmed to have no consumers.

### 1.1 PHP Dead Code

| Action | File(s) | Lines Saved |
|--------|---------|-------------|
| Delete legacy IHT calculator | `app/Services/Estate/IHTCalculator.php` | 208 |
| Delete orphaned demo seeders | `database/seeders/ComprehensiveDemoDataSeeder.php`, `database/seeders/DemoUserSeeder.php` | 559 |
| Delete unused controller trait | `app/Http/Controllers/Traits/HandleApiExceptions.php` | ~100 |
| Delete unused custom casts | `app/Casts/EncryptedDecimal.php`, `app/Casts/EncryptedString.php` | ~80 |
| Delete duplicate PHPDoc | `app/Agents/CoordinatingAgent.php` (line 202-205) | 3 |

### 1.2 Vue Dead Code

| Action | File(s) | Lines Saved |
|--------|---------|-------------|
| Delete dead `user` store module | `resources/js/store/modules/user.js` + remove from `store/index.js` | 52 |
| Delete dead store helpers | `resources/js/store/helpers/storeHelpers.js`, `resources/js/store/helpers/index.js` | 236 |
| Delete dead async utility | `resources/js/utils/asyncAction.js` | 158 |
| Delete dead views | `views/Investment/AccountDetailView.vue`, `views/Retirement/ContributionsAllowances.vue`, `views/Retirement/DecumulationPlanning.vue`, `views/Retirement/PortfolioAnalysis.vue`, `views/Retirement/RetirementReadiness.vue` | ~1,130 |
| Delete orphaned PropertyForm | `resources/js/components/NetWorth/PropertyForm.vue` | 501 |
| Delete dead services | `resources/js/services/postcodeService.js`, `resources/js/services/taxInfoService.js` | ~100 |
| Delete dead component | `resources/js/components/Protection/PolicyDetails.vue` | 291 |
| Delete dead component | `resources/js/components/Protection/Recommendations.vue` | 155 |
| Delete Savings WhatIf stub | `resources/js/components/Savings/WhatIfScenarios.vue` | 36 |
| Delete dead docs in components | `resources/js/components/Investment/ACCOUNT_COMPONENTS_README.md`, `INTEGRATION_EXAMPLE.md` | ~200 |

### 1.3 Test Cleanup

| Action | File(s) | Lines Saved |
|--------|---------|-------------|
| Delete example tests | `tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php` | ~20 |
| Remove unused Pest helpers | `tests/Pest.php` -- remove `toBeOne` expectation and empty `something()` | ~10 |

### 1.4 Remove Unused npm Dependency

```bash
npm uninstall vuedraggable
```

**Phase 1 Total: ~3,850+ lines of dead code removed**

---

## Phase 2: Consolidate Duplicates (COMPLETE)

**Status:** All items completed on 25 Feb 2026. See `deployRefactor.md` for deployment instructions.

Merge duplicate implementations into single sources of truth.

### 2.1 Consolidate `getCurrentTaxYear()` (HIGH)

**Problem:** Identical tax year calculation duplicated in 7 files.

**Files to modify:**
- `app/Agents/BaseAgent.php` -- remove `getCurrentTaxYear()`
- `app/Services/Savings/ISATracker.php` -- inject `TaxConfigService`
- `app/Services/Business/BusinessInterestService.php` -- inject `TaxConfigService`
- `app/Services/Retirement/RetirementStrategyService.php` -- inject `TaxConfigService`
- `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php` -- inject `TaxConfigService`
- `app/Services/Investment/ContributionOptimizer.php` -- inject `TaxConfigService`
- `app/Services/Investment/Tax/ISAAllowanceOptimizer.php` -- inject `TaxConfigService`

**Target:** All use `TaxConfigService::getTaxYear()`.

### 2.2 Fix `formatCurrency` Violations (HIGH)

**Problem:** 17 Vue components define local `formatCurrency` functions, violating CLAUDE.md rules.

**Fix:** Replace all 17 local definitions with:
```javascript
import { formatCurrency } from '@/utils/currency';
```

**Files:** All 17 files listed in Frontend Analysis finding 4.1 (Onboarding steps + UserProfile components).

### 2.3 Consolidate `formatCurrency` in PHP (MEDIUM)

**Problem:** `BaseAgent` defines its own `formatCurrency`/`formatPercentage` with different defaults than the `FormatsCurrency` trait.

**Fix:**
1. Add `use FormatsCurrency;` to `BaseAgent`
2. Remove `formatCurrency()` and `formatPercentage()` methods from `BaseAgent`
3. Ensure `FormatsCurrency` trait is used consistently across services

### 2.4 Merge Confirmation Modal Components (HIGH)

**Problem:** `ConfirmationModal.vue` and `ConfirmDialog.vue` are near-identical.

**Fix:**
1. Keep `ConfirmDialog.vue` (more capable)
2. Migrate consumers of `ConfirmationModal` to use `ConfirmDialog`
3. Delete `ConfirmationModal.vue`

### 2.5 Consolidate Date Utilities (MEDIUM)

**Problem:** `dateFormatter.js` (224 lines) and `dates.js` (151 lines) have overlapping functionality.

**Fix:**
1. Merge unique functions from `dates.js` (tax year helpers) into `dateFormatter.js`
2. Update any imports of `dates.js` to use `dateFormatter.js`
3. Delete `dates.js`

### 2.6 Merge Onboarding Skip Modals (MEDIUM)

**Problem:** `SkipConfirmationModal.vue` and `SkipToDashboardModal.vue` are nearly identical.

**Fix:** Replace both with `ConfirmDialog` using appropriate props.

---

## Phase 3: Architectural Fixes (3-5 days)

Fix structural issues that affect data quality and maintainability.

### 3.1 Wire Up EstateAgent in CoordinatingAgent (HIGH)

**Problem:** `CoordinatingAgent.collectModuleAnalysis()` returns hardcoded fake estate data instead of calling the fully-implemented `EstateAgent`.

**Fix:**
1. Inject `EstateAgent` and `GoalsAgent` into `CoordinatingAgent` constructor
2. Replace hardcoded data (lines 239-247) with actual agent calls
3. Add error handling for partial failures

### 3.2 Adopt `HasJointOwnership` Trait (MEDIUM)

**Problem:** Trait defined but unused by any of the 10 models with `joint_owner_id`.

**Fix:** Add `use HasJointOwnership;` to: `SavingsAccount`, `InvestmentAccount`, `Property`, `Mortgage`, `CashAccount`, `BusinessInterest`, `Chattel`, `Goal`, `LifeEvent`, `Estate\Liability`. Refactor controllers to use `->forUserOrJoint($userId)` scope.

### 3.3 Standardize Agent Cache Invalidation (MEDIUM)

**Problem:** Three different naming conventions and approaches across agents.

**Fix:**
1. Standardize all agents to method name `invalidateCache(int $userId)`
2. All delegate to `$this->invalidateUserCache($userId)` from `BaseAgent`
3. Remove `clearUserCache()` from `BaseAgent` (it's a subset of `invalidateUserCache()`)
4. Fix `ProtectionAgent` to use `BaseAgent` methods instead of raw `Cache` facade

### 3.4 Clean Up BaseAgent (LOW)

**Problem:** Multiple unused utility methods.

**Fix:** Remove these unused methods from `BaseAgent`:
- `calculateAge()` -- never called
- `calculatePercentageChange()` -- never called
- `calculatePresentValue()` -- never called
- `validateRequired()` -- never called
- `getCurrentTaxYear()` -- addressed in 2.1

### 3.5 Reduce RetirementAgent Constructor Dependencies (MEDIUM)

**Problem:** 9 constructor dependencies, 5 of which are Investment module services.

**Fix:** Remove the 5 Investment service injections and rely on `PensionPortfolioAnalyzer` which already wraps them.

### 3.6 Consolidate Monte Carlo Engines (MEDIUM)

**Problem:** `MonteCarloSimulator` and `MonteCarloEngine` are separate implementations.

**Fix:** Move `MonteCarloSimulator` to `Shared/` namespace and have `GoalRiskService` use it instead of `MonteCarloEngine`. Delete `MonteCarloEngine`.

### 3.7 Consolidate AssetAllocationOptimizer (MEDIUM)

**Problem:** Two classes with identical names in different namespaces.

**Fix:** Rename `ModelPortfolio/AssetAllocationOptimizer` to `ModelPortfolioAllocationOptimizer` or merge functionality.

---

## Phase 4: Store & Frontend Consistency (2-3 days)

### 4.1 Standardize Vuex Mutation Naming (MEDIUM)

**Problem:** 12 modules use `SET_UPPERCASE`, 7 use `setCamelCase`, 1 uses both.

**Fix:** Standardize all to `setCamelCase` to match JavaScript conventions and CLAUDE.md.

**Modules to update:** `goals`, `retirement`, `investment`, `netWorth`, `onboarding`, `trusts`, `recommendations`, `preview`, `infoGuide`, `holistic`, `guidance`, `dashboard`.

### 4.2 Standardize Import Paths (LOW)

**Problem:** Some store modules use relative paths (`../../services/`) instead of `@/services/`.

**Fix:** Update `holistic`, `netWorth`, `businessInterests`, `chattels` to use `@/` imports.

### 4.3 Create Service Wrappers for Direct API Calls (MEDIUM)

**Problem:** `recommendations`, `guidance`, and `infoGuide` store modules call `api.get()` directly.

**Fix:** Create thin service files:
- `resources/js/services/recommendationsService.js`
- `resources/js/services/guidanceService.js`
- `resources/js/services/infoGuideService.js`

### 4.4 Consolidate `ownership.js` Usage (MEDIUM)

**Problem:** `ownership.js` utility exists but is never imported; `currencyMixin` duplicates `getOwnershipLabel()`.

**Fix:** Either import `ownership.js` in components that need it, or merge its unique functions into `currencyMixin` and delete `ownership.js`.

---

## Phase 5: Test & Config Cleanup (1-2 days)

### 5.1 Add Payment/Subscription Test Coverage (HIGH)

**Problem:** Zero tests for payment module handling real financial transactions.

**Fix:** Create:
- `tests/Feature/Api/PaymentControllerTest.php`
- `tests/Feature/Api/WebhookControllerTest.php`
- `tests/Unit/Services/RevolutServiceTest.php`
- Test webhook signature verification, subscription state transitions, trial expiry

### 5.2 Migrate PHPUnit-Style Tests to Pest (MEDIUM)

**Problem:** 11 test files use PHPUnit class syntax instead of Pest `it()`.

**Files:** `MortgageServiceTest`, `PropertyServiceTest`, `PropertyTaxServiceTest`, `TaxConfigServiceTest`, `TaxConfigurationTest`, `CrossModuleIntegrationTest`, `UserMassAssignmentTest`, `MortgageControllerTest`, `PropertyControllerTest`, `RecommendationsControllerTest`, `SessionApiTest`.

### 5.3 Fix UserMassAssignmentTest (MEDIUM)

**Problem:** Test asserts `is_admin` is NOT mass assignable, but `User` model has it in `$fillable`.

**Fix:** Verify intent and align test with model.

### 5.4 Squash Database Migrations (MEDIUM)

**Problem:** 99 migrations in 2 months, including duplicates (goals v1/v2, 3x investment contribution fields).

**Fix:** Run `php artisan schema:dump` once all environments are in sync.

### 5.5 Sync `.env.example` (MEDIUM)

**Problem:** Missing keys for `ADMIN_EMAILS`, `ANTHROPIC_API_KEY`, `GETADDRESS_API_KEY`.

**Fix:** Add all used env vars to `.env.example` with empty defaults.

### 5.6 Remove Unused Config (LOW)

Remove from `.env.example`:
- All `VITE_PUSHER_*` entries
- All `AWS_*` entries
- All `REDIS_*` entries
- All `MEMCACHED_*` entries

### 5.7 Fix `APP_NAME` (LOW)

Change `APP_NAME=Laravel` to `APP_NAME=Fynla` in both `.env` and `.env.example`.

### 5.8 Add Integration Test Suite to phpunit.xml (LOW)

```xml
<testsuite name="Integration">
    <directory>tests/Integration</directory>
</testsuite>
```

### 5.9 Update Factory Style (LOW)

Update 32 factories from `$this->faker` to `fake()` per `database/CLAUDE.md`.

---

## Phase 6: Project Root Cleanup (1 day)

### 6.1 Move Non-Application Files to `.admin/`

**Problem:** ~27MB of documentation, images, PDFs, and update logs in the project root.

**Move to `.admin/`:**
- `Feb4Updates/` through `Feb25Updates/` -> `.admin/changelog/`
- `currentState/` -> `.admin/currentState/`
- `revolut/` -> `.admin/revolut/`
- `v07/` -> `.admin/v07/`
- `personaData/` -> `.admin/personaData/`
- `logo/`, `portraits/` -> `.admin/assets/`
- Root images (`.png`, `.pdf`) -> `.admin/assets/`
- Root markdown docs -> `.admin/docs/`

### 6.2 Remove Outdated Deployment Docs

Delete or archive:
- `DEPLOYMENT_FYNLA_ORG.md`
- `DEPLOYMENT_JAN16.md`
- `deploy12Feb.md`
- `deploy/DEPLOYMENT_v0.6.2.md`

Update `deploy/README.md` to remove ZIP file references.

### 6.3 Delete One-Time Migration Commands (LOW)

After confirming all environments have run:
- `app/Console/Commands/MigrateEstateToNetWorth.php`
- `app/Console/Commands/MigrateSavingsToCash.php`
- `app/Console/Commands/VerifyDataMigration.php`
- `app/Console/Commands/EncryptExistingData.php`

---

## Phase 7: Oversized Components (Ongoing)

Split these when next modifying them. Do not refactor speculatively.

| Component | Lines | Priority |
|-----------|-------|----------|
| `UserProfile/ExpenditureForm.vue` | 2,411 | HIGH -- extract category sub-forms |
| `NetWorth/PensionList.vue` | 2,160 | HIGH -- extract pension type cards |
| `Retirement/RetirementIncomeTab.vue` | 2,083 | MEDIUM |
| `NetWorth/Property/PropertyForm.vue` | 1,943 | MEDIUM -- extract mortgage sub-form |
| `UserProfile/LetterToSpouse.vue` | 1,777 | MEDIUM |
| `Estate/IHTPlanning.vue` | 1,714 | MEDIUM |
| `Admin/TaxSettings.vue` | 1,689 | MEDIUM |
| `views/Dashboard.vue` | 1,466 | LOW |
| `NetWorth/InvestmentList.vue` | 1,443 | LOW |
| `Http/Controllers/Api/InvestmentController.php` | 980 | LOW -- extract holding CRUD |

---

## Phase 8: Future Considerations (Not Urgent)

These are noted for awareness but require no immediate action:

1. **Vue Composition API migration** -- 280+ components use Options API, 29 use `setup()`, 2 use `<script setup>`. Continue Options API for consistency; adopt `<script setup>` for new features.
2. **Vuex to Pinia** -- Not worth the effort for a working system. Consider for new modules only.
3. **Route file splitting** -- `routes/api.php` is 1,032 lines. Consider splitting by module when it becomes unwieldy.
4. **Preview route deduplication** -- Generate preview routes programmatically from authenticated routes.
5. **Legacy GDPR erasure endpoints** -- Remove once confirmed no frontend consumers remain.
6. **StructuredLogging trait** -- Either adopt broadly or remove.
7. **Three gifting strategy services** -- Consider consolidating when next touching estate planning.
8. **Silent exception swallowing in EstateAgent.analyze()** -- Add logging at minimum.

---

## Priority Summary

| Priority | Phase | Effort | Impact |
|----------|-------|--------|--------|
| **Critical** | Phase 1: Dead code removal | 1-2 days | -3,850 lines, cleaner codebase |
| **Critical** | Phase 2: Consolidate duplicates | 2-3 days | Eliminate 7x tax year bug risk, fix 17 formatting violations |
| **High** | Phase 3: Architectural fixes | 3-5 days | Fix fabricated estate data, proper trait usage |
| **Medium** | Phase 4: Store consistency | 2-3 days | Unified patterns across 21 Vuex modules |
| **Medium** | Phase 5: Test & config | 1-2 days | Payment test coverage, clean config |
| **Low** | Phase 6: Root cleanup | 1 day | Clean project root, remove 27MB of non-app files |
| **Ongoing** | Phase 7: Split components | As-needed | Maintainability of 10 oversized components |

**Total estimated effort: 10-16 days of focused work**
