# Full Codebase Tech Debt Report

**Date:** 6 March 2026
**Codebase:** Fynla v0.8.3
**Files scanned:** 699 (174 services, 70 controllers, 77 models, 378 Vue components)
**Total issues:** 87
**First full audit** (no previous report to compare)

---

## Executive Summary

| Severity | Count |
|----------|-------|
| Critical | 5 |
| Warning | 28 |
| Suggestion | 54 |

| Category | Count |
|----------|-------|
| Complexity / God Files | 12 |
| Convention Violations | 22 |
| Dead Code | 4 |
| Duplicate Code | 8 |
| Coverage Gaps | 18 |
| Design System | 15 |
| Inconsistency | 8 |

### Quick Wins (trivial effort, high impact)

1. **Delete orphaned `SET_MONTE_CARLO_RESULT` mutation** in `investment.js` (10 lines, dead code)
2. **Add `Mockery::close()` to 8 test files** missing cleanup (40 mins)
3. **Add `declare(strict_types=1)` to 19 test files** (15 mins)
4. **Replace 1 raw `DB::table()` call** in `RebalancingCalculationController.php:375` (5 mins)
5. **Remove 6 custom `@keyframes` definitions** that duplicate global classes (30 mins)

### High Priority (any effort, critical severity)

1. **RetirementIncomeService.php** — 2,293 lines, methods up to 388 lines. Split into 4-5 focused services.
2. **RetirementStrategyService.php** — 2,134 lines. Needs decomposition.
3. **planPrintMixin.js** — 3,199 lines. Extract into sub-mixins.
4. **Vuex mutation naming inconsistency** — 21 modules split between camelCase and SCREAMING_SNAKE_CASE.
5. **28 banned colour tokens** (yellow/amber/orange) across 28 Vue components.

---

## Detailed Findings by Module

### Backend Services

**Files scanned:** 174 | **Issues:** 10 | **Overall health:** Good

The service layer is the strongest part of the codebase. All services use `declare(strict_types=1)`, constructor injection with `private readonly`, and proper type hints.

| # | Severity | Issue | File | Lines | Effort |
|---|----------|-------|------|-------|--------|
| S1 | Critical | God file (2,293 lines, 20+ methods) | `Services/Retirement/RetirementIncomeService.php` | All | Large |
| S2 | Critical | God file (2,134 lines) | `Services/Retirement/RetirementStrategyService.php` | All | Large |
| S3 | Warning | Method 388 lines (`calculateDefaultAllocations`) | `Services/Retirement/RetirementIncomeService.php` | 1443-1830 | Medium |
| S4 | Warning | Method 358 lines (`projectFundDepletion`) | `Services/Retirement/RetirementIncomeService.php` | 898-1255 | Medium |
| S5 | Warning | 3 duplicate asset aggregation services | `Estate/EstateAssetAggregatorService`, `Shared/CrossModuleAssetAggregator`, `Trust/TrustAssetAggregatorService` | N/A | Medium |
| S6 | Warning | Hardcoded tax values (40000, 20000) | `Retirement/ContributionOptimizer.php` | 280-283 | Trivial |
| S7 | Warning | Magic numbers without constants | `Coordination/ConflictResolver.php` | 73-78, 287-337 | Trivial |
| S8 | Suggestion | Large file (1,366 lines) | `Estate/IHTCalculationService.php` | All | Medium |
| S9 | Suggestion | Large file (1,344 lines) | `Onboarding/OnboardingService.php` | All | Medium |
| S10 | Suggestion | Large file (1,091 lines) | `UserProfile/UserProfileService.php` | All | Medium |

**What's clean:** Strict types 100%, type hints 100%, TaxConfigService usage consistent, no dead services detected.

---

### Controllers & HTTP

**Files scanned:** 70 controllers, 73 requests, 16 resources | **Issues:** 12 | **Overall health:** Good

| # | Severity | Issue | File(s) | Effort |
|---|----------|-------|---------|--------|
| C1 | Warning | 26 controllers missing `SanitizedErrorResponse` trait | See list below | Small |
| C2 | Warning | 17 controllers use inline `Validator::make()` instead of FormRequest | Investment subcontrollers, Admin, Onboarding | Medium |
| C3 | Warning | God controller (980 lines) | `InvestmentController.php` | Medium |
| C4 | Warning | Large controller (786 lines) | `GoalsController.php` | Medium |
| C5 | Warning | Large controller (673 lines) | `RetirementController.php` | Medium |
| C6 | Suggestion | 1 raw `DB::table()` query | `RebalancingCalculationController.php:375` | Trivial |

**Controllers missing SanitizedErrorResponse (priority):** `WebhookController`, `PasswordResetController`, `AuthController`, `NetWorthController`, `DashboardController`, `UKTaxesController`, `HolisticPlanningController`, `ChattelController`, `LetterToSpouseController`, `PersonalAccountsController`, `PlanController`, + 15 others.

**What's clean:** Strict types 100%, type hints 100%, return types 100%, no dead requests/resources, auth middleware properly applied, API response format consistent.

---

### Models & Database

**Files scanned:** 77 models, 108 migrations, 44 factories | **Issues:** 15 | **Overall health:** Very Good

| # | Severity | Issue | File(s) | Effort |
|---|----------|-------|---------|--------|
| M1 | Warning | 8 financial models missing `Auditable` trait | `RiskProfile`, `InvestmentScenario`, `InvestmentGoal`, `ExpenditureProfile`, `RetirementProfile`, `ProtectionProfile`, `UserAssumption`, `ISAAllowanceTracking` | Small |
| M2 | Suggestion | ~30 older migrations missing safety checks | Dec 2025 - Jan 2026 migrations | Medium |
| M3 | Suggestion | 4 older migrations missing `declare(strict_types=1)` | Dec 2025 migrations | Trivial |
| M4 | Suggestion | 31 factories use `$this->faker` instead of `fake()` | Estate, Investment, Protection, Retirement factories | Small |
| M5 | Suggestion | Missing factory state methods | `PropertyFactory`, `InvestmentAccountFactory`, `SavingsAccountFactory`, `MortgageFactory` | Small |

**What's clean:** Canonical enums 100% correct, decimal precision 100% correct, joint_owner_id indexes all present, seeders all idempotent (`updateOrCreate`), foreign keys properly constrained.

---

### Vue Components

**Files scanned:** 378 | **Issues:** 25 | **Overall health:** Moderate

| # | Severity | Issue | File(s) | Count | Effort |
|---|----------|-------|---------|-------|--------|
| V1 | Critical | Banned colour tokens (yellow/amber/orange) | 28 components (Investment, Goals, Retirement, Risk, etc.) | 28 files | Medium |
| V2 | Warning | Hardcoded hex colours in style blocks | `planPrintMixin.js`, `LetterToSpouse.vue`, `CountrySelector.vue`, chart components | 6 files | Medium |
| V3 | Warning | Custom `@keyframes` duplicating global classes | `PlanGoalSection`, `GoalContributionStreak`, `GuidanceTooltip`, `NetWorthOverviewCard`, `InvestmentList`, `PropertyList`, `PensionList`, `PortfolioOptimization` | 8 files | Low |
| V4 | Warning | Acronyms in user-facing text (AA, DB, DC, S&S) | 39+ components across Retirement, Investment, Savings | 39 files | Medium |
| V5 | Warning | 17 components use formatCurrency without currencyMixin import | UserProfile, Onboarding components | 17 files | Small |
| V6 | Suggestion | Score displays visible in UI | `FinancialHealthScore`, `CoverageAdequacyGauge`, + others | 6+ files | Medium |
| V7 | Suggestion | Duplicate scrollbar CSS | `CountrySelector.vue` | 1 file | Trivial |

**God components (1000+ lines):**

| File | Lines |
|------|-------|
| `planPrintMixin.js` | 3,199 |
| `ExpenditureForm.vue` | 2,403 |
| `RetirementIncomeTab.vue` | 2,034 |
| `PropertyForm.vue` | 1,943 |
| `LetterToSpouse.vue` | 1,776 |
| `PensionList.vue` | 1,773 |
| `TaxSettings.vue` | 1,689 |
| `IHTPlanning.vue` | 1,667 |
| `InvestmentList.vue` | 1,352 |
| `CapitalAdequacyTab.vue` | 1,341 |
| `AssetsStep.vue` | 1,267 |
| `RequiredCapitalDetail.vue` | 1,253 |
| `StrategiesTab.vue` | 1,048 |
| `AccountForm.vue` | 1,031 |
| `SaveAccountModal.vue` | 1,005 |

**What's clean:** No `v-if` + `v-for` anti-pattern, `:key` always used with `v-for`, form modals emit `save` not `submit`, multi-word component names enforced, no `primary-*`/`secondary-*` deprecated tokens.

---

### Vuex Stores & JS Services

**Files scanned:** 21 stores, 33 services, 5 utils, 2 mixins | **Issues:** 12 | **Overall health:** Moderate

| # | Severity | Issue | File(s) | Effort |
|---|----------|-------|---------|--------|
| X1 | Critical | Mutation naming split: camelCase vs SCREAMING_SNAKE_CASE across 21 modules | All store modules | High |
| X2 | Warning | Orphaned mutation `SET_MONTE_CARLO_RESULT` | `investment.js:780-789` | Trivial |
| X3 | Warning | 3 actions skip loading pattern ("causes infinite loop") | `retirement.js:239-262, 386-410, 537-565` | Medium |
| X4 | Warning | Console.log left in production code | `router/index.js`, `LetterToSpouse.vue`, `PrivacySettings.vue` | Trivial |
| X5 | Warning | American/British spelling mix in service methods | 34 services use `analyze` while stores use `analyse` | High |
| X6 | Warning | 5 store modules exceed 500 lines | `investment.js` (896), `retirement.js` (809), `estate.js` (763), `goals.js` (766), `netWorth.js` (768) | High |
| X7 | Suggestion | Unused state properties | `protection.js` — `lifeEvents`, `lifeEventImpact` set but no getter | Trivial |
| X8 | Suggestion | ISA tracking logic in both investment.js and savings.js | Dual tracking, intentional but worth documenting | Trivial |

---

### Tests

**Files scanned:** 115 test files | **Issues:** 13 | **Overall health:** Moderate

| # | Severity | Issue | File(s) | Effort |
|---|----------|-------|---------|--------|
| T1 | Critical | 10 service modules have zero test coverage | Admin, AI, Benefits, Business, Chattel, Dashboard, Documents, Onboarding, Payment, Settings | Very High |
| T2 | Warning | 30+ controllers have no feature tests | See controllers report | Very High |
| T3 | Warning | 19 test files missing `declare(strict_types=1)` | Various (see tests report) | Trivial |
| T4 | Warning | 8 files use Mockery without `Mockery::close()` in afterEach | ISATrackerTest, PensionProjectorTest, TaxEfficiencyCalculatorTest, + 5 others | Trivial |
| T5 | Warning | 31 test files use `test()` instead of Pest `it()` syntax | Various feature and unit tests | Medium |
| T6 | Warning | 8 test files use PHPUnit class-based syntax instead of Pest closures | TaxConfigServiceTest, PropertyControllerTest, MortgageControllerTest, + 5 others | Medium |
| T7 | Suggestion | Missing architecture test for strict types in tests | `Architecture/` | Trivial |

**Test coverage by module:**

| Module | Unit Tests | Feature Tests | Status |
|--------|-----------|--------------|--------|
| Estate | 14 files | 2 files | Good |
| Investment | 12 files | 1 file | Moderate |
| Retirement | 8 files | 3 files | Moderate |
| Protection | 5 files | 4 files | Good |
| Savings | 4 files | 2 files | Moderate |
| Coordination | 2 files | 0 files | Low |
| Goals | 3 files | 0 files | Low |
| Auth | 0 files | 7 files | Good (feature-only) |
| AI/Documents/Payment | 0 files | 0 files | None |

---

## Cross-Cutting Issues

### Duplicate Code

1. **3 asset aggregation services** with overlapping logic (`EstateAssetAggregatorService`, `CrossModuleAssetAggregator`, `TrustAssetAggregatorService`)
2. **ISA tracking** in both `investment.js` and `savings.js` stores (intentional but undocumented)
3. **Format functions** in `currencyMixin` partially overlap with backend `FormatsCurrency` trait
4. **Custom @keyframes** in 8 components duplicate global `app.css` animations

### Dead Code

1. Orphaned `SET_MONTE_CARLO_RESULT` mutation in `investment.js`
2. Unused state properties `lifeEvents`/`lifeEventImpact` in `protection.js`
3. Console.log statements in 3 files

### Convention Drift

1. **Mutation naming**: 21 Vuex modules inconsistently use camelCase vs SCREAMING_SNAKE_CASE
2. **British/American spelling**: Stores use `analyse`, services use `analyze`
3. **Test syntax**: Mix of Pest `it()`, Pest `test()`, and PHPUnit class-based styles
4. **Error handling**: 26/70 controllers missing `SanitizedErrorResponse` trait

### Architectural Issues

1. **God files**: 2 services over 2,000 lines, 15 Vue components over 1,000 lines, 1 mixin at 3,199 lines
2. **Test coverage**: 10 service modules with zero tests, 30+ controllers untested
3. **28 components** using banned colour tokens that predate the design system migration

---

## Recommended Action Plan

### Immediate (this week)

| # | Action | Files | Effort | Impact |
|---|--------|-------|--------|--------|
| 1 | Add `Mockery::close()` to 8 test files | 8 | 40 mins | Prevents test pollution |
| 2 | Add `declare(strict_types=1)` to 19 test files | 19 | 15 mins | Standards compliance |
| 3 | Delete orphaned `SET_MONTE_CARLO_RESULT` | 1 | 5 mins | Dead code removal |
| 4 | Remove console.log from 3 files | 3 | 10 mins | Production cleanliness |
| 5 | Replace `DB::table()` with Eloquent in RebalancingCalculationController | 1 | 5 mins | Standards compliance |
| 6 | Remove 6 custom `@keyframes`, use global classes | 8 | 30 mins | CSS deduplication |
| 7 | Replace hardcoded tax values in ContributionOptimizer | 1 | 15 mins | Tax compliance |
| 8 | Add constants for magic numbers in ConflictResolver | 1 | 15 mins | Readability |

**Total: ~2.5 hours**

### Short-term (this month)

| # | Action | Files | Effort | Impact |
|---|--------|-------|--------|--------|
| 9 | Add `Auditable` trait to 8 financial models | 8 | 2-3 hrs | Audit compliance |
| 10 | Migrate 28 components from banned colour tokens | 28 | 4-6 hrs | Design system compliance |
| 11 | Add `SanitizedErrorResponse` to 26 controllers | 26 | 2-3 hrs | Error handling consistency |
| 12 | Extract FormRequest classes from 17 inline validators | 17 | 3-4 hrs | Code organisation |
| 13 | Modernise 31 factories (`$this->faker` to `fake()`) | 31 | 3-4 hrs | Code modernisation |
| 14 | Convert 31 test files from `test()` to `it()` | 31 | 2 hrs | Convention compliance |
| 15 | Audit and fix acronyms in 39 Vue components | 39 | 4-6 hrs | UX compliance |

**Total: ~22-30 hours**

### Backlog (next quarter)

| # | Action | Files | Effort | Impact |
|---|--------|-------|--------|--------|
| 16 | Refactor RetirementIncomeService (2,293 lines) into 4-5 services | 5+ | 5-7 days | Maintainability |
| 17 | Refactor RetirementStrategyService (2,134 lines) | 3+ | 3-5 days | Maintainability |
| 18 | Standardise Vuex mutation naming across 21 modules | 21 | 2-3 days | Consistency |
| 19 | Split large controllers (Investment, Goals, Retirement) | 3 | 2-3 days | Maintainability |
| 20 | Refactor 15 Vue components over 1,000 lines | 15 | 2-3 weeks | Maintainability |
| 21 | Write tests for 10 untested service modules | 10+ | 2-3 weeks | Coverage |
| 22 | Write feature tests for 30+ untested controllers | 30+ | 3-4 weeks | Coverage |
| 23 | Consolidate 3 asset aggregation services | 3 | 2-3 days | Deduplication |
| 24 | Standardise British/American spelling in services | 34 | 1-2 days | Consistency |

---

## Overall Health Assessment

Fynla's codebase is in **good shape for a v0.8.3 application** with 699 source files. The backend layer (services, models, database) is the strongest — 100% strict types, proper type hints, consistent patterns, and idempotent seeders. The main debt concentration is in **complexity** (a few very large files in Retirement and Plans modules) and **design system migration** (28 components still using pre-v1.2.0 colour tokens). Test coverage has meaningful gaps in 10 service modules and 30+ controllers, but existing tests follow good patterns. The most impactful quick wins are the 8 immediate items (~2.5 hours) which clear dead code, fix test hygiene, and remove duplicated CSS. The costliest debt is the Retirement module god files (RetirementIncomeService at 2,293 lines) which should be decomposed before further feature work in that area.

---

*Generated by tech-debt-full skill on 6 March 2026*
