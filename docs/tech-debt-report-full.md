# Full Codebase Tech Debt Report

**Date:** 30 March 2026
**Codebase:** Fynla v0.9.3.2
**Files scanned:** 640 Vue, 214 Services, 89 Controllers, 89 Models, 83 Form Requests, 32 Stores
**Total issues:** 47
**Previous report:** 20 March 2026 (v0.9.3, 58 issues)

## Executive Summary

| Severity | Count | Previous |
|----------|-------|----------|
| Critical | 6 | 8 |
| High | 12 | 15 |
| Medium | 17 | 20 |
| Low | 12 | 15 |
| **Total** | **47** | **58** |

| Category | Count |
|----------|-------|
| God Files / Complexity | 15 |
| Security / Vulnerabilities | 5 |
| Design System / Convention | 10 |
| Dead Code / Bloat | 6 |
| Test Coverage | 2 |
| Architecture | 5 |
| Duplicate Code | 4 |

**Improvement since last audit:** 11 fewer issues. Security hardening (54 code review fixes applied this session), strict_types now 100% compliant, hardcoded tax values eliminated, design system palette largely enforced.

### Quick Wins (trivial effort, high impact)

1. Add `$hidden` to 4 models with encrypted account numbers (10 min)
2. Remove 2-3 unused Form Requests (5 min)
3. Fix 2 timer leaks in DBPensionForm + AccountForm (15 min)
4. Remove `status` from Goal/InvestmentScenario `$fillable` (10 min)
5. Update `league/commonmark` to fix 2 CVEs (5 min)

### Critical Priority

1. **NPM vulnerabilities** — 9 high-severity CVEs across transitive dependencies
2. **PHP vulnerability** — league/commonmark 2 CVEs
3. **God classes** — 5 backend services over 2,000 lines (SavingsActionDef 3,675)
4. **God controllers** — InvestmentController 1,067 lines
5. **God components** — Dashboard.vue 2,124 lines, CalculatorsPage.vue 2,432 lines
6. **Test coverage** — 19% (41 of 214 services tested)

---

## SECTION 1: SECURITY & VULNERABILITIES (5 issues)

### CRITICAL

#### VULN-01: NPM high-severity vulnerabilities (9 packages)
- **Packages:** flatted, happy-dom, picomatch, serialize-javascript, tar
- **CVEs:** GHSA-25h7, GHSA-rf6f, GHSA-6q6h, GHSA-w4gp, GHSA-3v7f, GHSA-c2c7, GHSA-5c6j, GHSA-qj8w, GHSA-34x7 + 5 more
- **Impact:** RCE via serialize-javascript, code execution via happy-dom, file extraction via tar
- **Fix:** `npm audit fix --force` (test PWA + mobile after)
- **Effort:** Medium (8 hours with testing)

#### VULN-02: PHP league/commonmark vulnerabilities (2 CVEs)
- **Package:** league/commonmark v2.3.0-2.8.1
- **CVEs:** CVE-2026-33347 (embed bypass), CVE-2026-30838 (DisallowedRawHtml bypass)
- **Fix:** `composer require league/commonmark:^2.9`
- **Effort:** Trivial (update + test)

### HIGH

#### SEC-01: Encrypted account numbers not in $hidden (4 models)
- **Files:** InvestmentAccount.php, SavingsAccount.php, CashAccount.php, Mortgage.php
- **Detail:** These models encrypt account_number/sort_code via Attribute accessors but don't add them to `$hidden`. If serialised directly (bypassing Resource classes), encrypted fields appear in JSON.
- **Fix:** Add `protected $hidden = ['account_number']` (and `sort_code` for CashAccount, `mortgage_account_number` for Mortgage)
- **Effort:** Trivial (10 min)

#### SEC-02: Status fields in $fillable (3 models)
- **Files:** Goal.php, InvestmentScenario.php, AdvisorClient.php
- **Detail:** `status` in `$fillable` allows mass assignment of lifecycle fields
- **Fix:** Remove from `$fillable`, use explicit setter methods
- **Effort:** Small (30 min)

#### SEC-03: Biometric auth module vulnerability
- **Package:** @capgo/capacitor-native-biometric (moderate)
- **CVE:** GHSA-vx5f-vmr6-32wf — authentication bypass
- **Fix:** Update to patched version when available
- **Effort:** Small (test Face ID after update)

---

## SECTION 2: GOD FILES / COMPLEXITY (15 issues)

### CRITICAL — Backend Services (>2,000 lines)

| File | Lines | Effort |
|------|-------|--------|
| `app/Services/Savings/SavingsActionDefinitionService.php` | 3,675 | Large |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | 2,701 | Large |
| `app/Services/Protection/ProtectionActionDefinitionService.php` | 2,349 | Large |
| `app/Services/Retirement/RetirementIncomeService.php` | 2,292 | Large |
| `app/Services/Retirement/RetirementStrategyService.php` | 2,141 | Large |

**Root cause:** ActionDefinitionServices have N+1 private evaluator methods for each trigger condition. Common pattern should be extracted to a base class with strategy registration.

### HIGH — Backend Services (1,000-2,000 lines)

| File | Lines |
|------|-------|
| `app/Services/Estate/IHTCalculationService.php` | 1,574 |
| `app/Services/Investment/InvestmentActionDefinitionService.php` | 1,486 |
| `app/Services/Onboarding/OnboardingService.php` | 1,389 |
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | 1,308 |
| `app/Services/UserProfile/UserProfileService.php` | 1,099 |
| `app/Services/Investment/Recommendation/ContributionWaterfallService.php` | 1,024 |

### HIGH — Controllers (>500 lines)

| File | Lines |
|------|-------|
| `app/Http/Controllers/Api/InvestmentController.php` | 1,067 |
| `app/Http/Controllers/Api/RetirementController.php` | 788 |
| `app/Http/Controllers/Api/PreviewController.php` | 672 |
| `app/Http/Controllers/Api/FamilyMembersController.php` | 669 |

### HIGH — Vue Components (>2,000 lines)

| File | Lines |
|------|-------|
| `resources/js/views/Public/CalculatorsPage.vue` | 2,432 |
| `resources/js/views/Dashboard.vue` | 2,124 |

---

## SECTION 3: DESIGN SYSTEM & CONVENTION (10 issues)

### HIGH

#### DS-01: Local Intl.NumberFormat (15 files)
- **Files:** EstateOverviewCard, NetWorthWaterfallChart, IHTPlanning, GiftingStrategy, CoverageGapChart, PremiumBreakdownChart, ScenarioBuilder, InvestmentOverviewCard, Goals, RebalancingActions, HoldingsTable, TaxBreakdownCard, IncomeStatementTab, PrivateInvestmentDetail, EmployeeShareSchemeDetail
- **Fix:** Replace with currencyMixin
- **Effort:** Medium (2-3 hours)

#### DS-02: Score gauges in user-facing UI (2 files)
- **Files:** `EmergencyFundGauge.vue` (runway months radial), `IHTLiabilityGauge.vue` (IHT percentage radial)
- **Fix:** Replace with descriptive text (Low/Moderate/High)
- **Effort:** Medium (4-6 hours)

### MEDIUM

#### DS-03: Off-palette Tailwind colours (30+ files)
- **Focus areas:** Risk module (red-*, blue-*, teal-*, green-*), Trust components (purple-*), Cash components (red-*/green-*)
- **Fix:** Map to palette tokens (raspberry, spring, violet, horizon)
- **Effort:** Medium (3-4 hours)

#### DS-04: Hardcoded hex in SVG/styles (40+ instances)
- **Focus areas:** JourneyMap, FocusAreaSelection, LetterToSpouse (print), chart components, WillBuilderReviewStep
- **Fix:** Import from designSystem.js or use CSS custom properties
- **Effort:** High (6-8 hours)

#### DS-05: console.error in production (50+ instances)
- **Focus areas:** Estate components (TrustPlanning, AssetsLiabilities, IntestacyRules), Onboarding, Navbar, SideMenu
- **Fix:** Replace with `logger.error()` from utils/logger
- **Effort:** Medium (2-3 hours)

#### DS-06: Float casts on financial model fields (12 models, 60+ fields)
- **Status:** Known issue from code review. Requires API Resource layer updates before changing casts.
- **Fix:** Change to `decimal:2`/`decimal:4` + update Resource classes + update test assertions
- **Effort:** Large (full sprint)

---

## SECTION 4: DEAD CODE & BLOAT (6 issues)

### MEDIUM

#### DEAD-01: Unused Form Requests (2-3 files)
- `CalculateEfficientFrontierRequest.php`, `OptimizePortfolioRequest.php`
- **Effort:** Trivial

#### DEAD-02: Duplicate migrations (2 tables)
- goals table: v1 + v2 (v2 has guard). goal_contributions: same.
- **Fix:** Delete v1 files
- **Effort:** Trivial

#### DEAD-03: Duplicate class names (2 pairs)
- `AssetAllocationOptimizer` in Investment/ and Investment/ModelPortfolio/
- `ContributionOptimizer` in Investment/ and Retirement/
- **Fix:** Rename one in each pair
- **Effort:** Small

### LOW

#### DEAD-04: Orphaned Vuex modules (investigate)
- `spousePermission.js` — dispatches not found in codebase
- `whatIf.js` — usage unclear
- **Effort:** Small (verify + remove)

#### DEAD-05: State bloat in investment.js (28 props) and netWorth.js (36 props)
- Monte Carlo state (3 objects) should be extracted
- UI state mixed with data state
- **Effort:** Medium

#### DEAD-06: TODO/unfinished features
- `PortfolioOptimization.vue:197` — "TODO: Implement rebalancing plan creation"
- **Effort:** Variable

---

## SECTION 5: ARCHITECTURE (5 issues)

### HIGH

#### ARCH-01: DB facade in 8 controllers
- **Files:** FamilyMembersController, RetirementController, InvestmentController, PaymentController, WebhookController, DCPensionHoldingsController, TaxSettingsController, PreviewController
- **Fix:** Extract transactions to service layer
- **Effort:** Medium (4-6 hours)

#### ARCH-02: EstateController bypasses EstateAgent
- **Fix:** Route through agent like other modules
- **Effort:** Medium

### MEDIUM

#### ARCH-03: Response format inconsistency (4 controllers)
- **Files:** WebhookController, PaymentController, AgentInternalController, PostcodeLookupController
- **Detail:** Return `{'error': '...'}` instead of standard `{'success': false, 'message': '...'}`
- **Effort:** Small (2-3 hours)

#### ARCH-04: api.js imports store (coupling)
- **Fix:** Use dependency injection or auth context module
- **Effort:** Small

#### ARCH-05: Timer leaks (2 components)
- **Files:** DBPensionForm.vue:298, AccountForm.vue:831
- **Fix:** Store timeout ID, clear in beforeUnmount
- **Effort:** Trivial (15 min)

---

## SECTION 6: TEST COVERAGE (2 issues)

### CRITICAL

#### TEST-01: 19% service test coverage
- **Status:** 41 of 214 services have test files
- **Priority services needing tests:** HolisticPlanner, NetWorthAnalyzer, MobileDashboardAggregator, RevolutService, DataPurgeService, all Coordination services
- **Target:** 40% coverage (add 44 test files)
- **Effort:** Large (20+ hours)

### MEDIUM

#### TEST-02: Outdated test mocks (3 files)
- AnnualAllowanceCheckerTest, RecommendationEngineTest — verify mocks match current TaxConfig schema
- **Effort:** Small (2 hours)

---

## Confirmed Clean Areas

- **strict_types:** 100% compliant across all PHP files (services, controllers, seeders, models)
- **Constructor injection:** All services use `private readonly` — zero violations
- **Hardcoded tax values:** Eliminated — all use TaxConfigService with TaxDefaults fallbacks
- **Banned colour tokens:** No amber-*, orange-*, primary-*, secondary-* found in templates
- **v-for :key:** Zero missing bindings across 640 components
- **v-if/v-for same element:** Zero violations
- **SoftDeletes:** Applied appropriately to all 40+ financial models
- **Foreign key indexes:** Proactively addressed via dedicated migrations
- **Seeder environment gates:** Test data properly gated to local/development/staging

---

## Recommended Action Plan

### Immediate (this week)
1. VULN-01/02: Fix npm + composer vulnerabilities
2. SEC-01: Add $hidden to 4 models (10 min)
3. SEC-02: Remove status from 3 model $fillable (10 min)
4. ARCH-05: Fix 2 timer leaks (15 min)
5. DEAD-01/02: Remove unused Form Requests + duplicate migrations (10 min)

### Short-term (this month)
6. DS-01: Replace Intl.NumberFormat in 15 files (3 hours)
7. DS-02: Remove 2 score gauges (4-6 hours)
8. DS-05: Replace console.error with logger (2-3 hours)
9. ARCH-03: Standardise API responses in 4 controllers (3 hours)
10. DEAD-03: Rename duplicate class pairs (1 hour)

### Backlog (next sprint)
11. God class decomposition (ActionDefinitionServices, InvestmentController, Dashboard.vue)
12. DS-03/04: Off-palette colours + hardcoded hex (10+ hours)
13. DS-06: Float→decimal migration with Resource layer updates
14. TEST-01: Increase test coverage to 40%
15. ARCH-01: Extract DB transactions from controllers

---

## Comparison to Previous Report (20 March 2026)

| Area | 20 March | 30 March | Trend |
|------|----------|----------|-------|
| Total issues | 58 | 47 | -11 improved |
| Security | 12 critical | 6 critical | Improved (code review fixes) |
| Hardcoded tax values | 6+ files | 0 files | Resolved |
| strict_types compliance | 99% | 100% | Resolved |
| Design system violations | 25+ files | 15 files | Improved |
| God files >2,000 lines | 5 | 7 | Worsened (content branch additions) |
| Test coverage | ~18% | 19% | Stable |
| NPM vulnerabilities | Not checked | 11 | New finding |

---

*Generated by tech-debt-full skill — 30 March 2026*
