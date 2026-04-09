# Full Codebase Tech Debt Report

**Date:** 9 April 2026
**Codebase:** Fynla v0.9.4
**Files scanned:** 537 Vue, 234 Services, 93 Controllers, 94 Models, 83 Form Requests, 35 Stores, 197 Tests
**Total issues:** 68
**Previous report:** 30 March 2026 (v0.9.3.2, 47 issues)

## Executive Summary

| Severity | Count | Previous |
|----------|-------|----------|
| Critical | 12 | 6 |
| Warning | 42 | 29 |
| Suggestion | 14 | 12 |
| **Total** | **68** | **47** |

| Category | Count |
|----------|-------|
| God Files / Complexity | 16 |
| Hardcoded Values (Tax/Financial) | 14 |
| Dead Code / Bloat | 12 |
| Convention Drift | 8 |
| Test Coverage | 5 |
| Architecture | 7 |
| Duplicate Code | 4 |
| Security / Vulnerabilities | 2 |

**Trend:** Issue count increased from 47 to 68 due to deeper scanning (stores/services frontend layer scanned for first time, test coverage gaps enumerated in detail). Real regressions are minimal — most new findings are pre-existing issues now surfaced by more thorough analysis.

### Quick Wins (trivial effort, high impact)

1. Register `$toast` global in `app.js` — fixes silent notification failures in Settings + MFA (10 min)
2. Fix PSACalculator bug — below-PA earners get wrong PSA tier (5 min)
3. Add `afterEach(Mockery::close())` to 6 test files (10 min)
4. Fix hardcoded `£12,570` / `£50,270` in DecumulationPlanner user-facing tips (5 min)
5. Remove dead `taxOptimisationService.js` + `PythonAgentBridge.php` (5 min)

### Critical Priority

1. **$toast never registered** — 23 success/failure notifications silently swallowed in Settings pages + MFA setup
2. **PSACalculator semantic bug** — returns `'basic'` for below-PA earners instead of non-taxpayer
3. **Float casts** — 65 monetary columns across 9 models use `'float'` instead of `'decimal:2'`
4. **Hardcoded tax values** — 12+ instances in services, 11+ in Vue components
5. **Duplicate tax band calculation** — 7 separate implementations (one with bug)
6. **NPM vulnerabilities** — 14 (3 moderate, 11 high) — up from 9

---

## SECTION 1: SECURITY & VULNERABILITIES (2 issues)

### CRITICAL

#### VULN-01: NPM vulnerabilities (14 packages, 11 high)
- **Packages:** tar, @capacitor/cli, vite, picomatch, happy-dom, serialize-javascript, flatted
- **Change from last report:** Was 9 high, now 14 total (3 moderate + 11 high). Vite path traversal (GHSA-4w7w-66w2-5vf9) is new.
- **Fix:** `npm audit fix --force` (breaking changes — test PWA + mobile + Vite build after)
- **Effort:** Medium (requires testing)

#### RESOLVED: PHP league/commonmark vulnerabilities
- Previously VULN-02. `composer audit` now reports zero vulnerabilities. Fixed.

### IMPROVED

#### SEC-01: Encrypted account numbers not in $hidden (4 models)
- **Status:** Still present from last report. Not addressed.
- **Files:** InvestmentAccount.php, SavingsAccount.php, CashAccount.php, Mortgage.php
- **Fix:** Add `protected $hidden = ['account_number']`
- **Effort:** Trivial

---

## SECTION 2: BROKEN FEATURES (2 issues — NEW)

### CRITICAL

#### BROKEN-01: `$toast` global never registered — 23 silent notification failures
- **Files:** `views/Settings/SecuritySettings.vue`, `views/Settings/PrivacySettings.vue`, `views/Settings/AssumptionsSettings.vue`, `components/Auth/MFASetupModal.vue`
- **Detail:** All `this.$toast?.success?.()` / `this.$toast?.error?.()` calls are silently swallowed because `$toast` is never registered as a Vue global property. The optional chaining suppresses the error. Users get no feedback on MFA enable/disable, password changes, privacy settings changes, assumption updates.
- **Fix:** Register `$toast` global in `app.js` delegating to the existing `toast` Vuex module, OR replace all 23 usages with direct `this.$store.dispatch('toast/show', ...)` calls.
- **Effort:** Small (10 min for global registration)

### CRITICAL

#### BROKEN-02: PSACalculator returns wrong tier for below-PA earners
- **File:** `app/Services/Savings/PSACalculator.php:73-74`
- **Detail:** When `$grossIncome <= $personalAllowance`, the method returns `'basic'` instead of a non-taxpayer indicator. This assigns a PSA of £1,000 when below-PA earners should get unlimited PSA. Affects savings interest tax calculations for low-income users.
- **Fix:** Return `'non_taxpayer'` or `'nil'` and handle downstream.
- **Effort:** Trivial

---

## SECTION 3: HARDCODED VALUES (14 issues)

### CRITICAL — Backend (user-facing strings)

#### HV-01: DecumulationPlanner hardcodes £12,570 and £50,270
- **File:** `app/Services/Retirement/DecumulationPlanner.php:304-305`
- TaxConfigService is already injected. Effort: Trivial

#### HV-02: RetirementActionDefinitionService hardcodes £50,270 / £125,140
- **File:** `app/Services/Retirement/RetirementActionDefinitionService.php:684`
- Effort: Trivial

#### HV-03: SavingsActionDefinitionService hardcodes 0.20/0.40/0.45 tax rates
- **File:** `app/Services/Savings/SavingsActionDefinitionService.php:774,2192-2199`
- TaxConfigService is already injected. Effort: Trivial

### WARNING — Backend (fallbacks/calculations)

#### HV-04: PersonalizedTrustStrategyService uses `?? 0.40` fallback 5 times
- **File:** `app/Services/Estate/PersonalizedTrustStrategyService.php:165,269,345,406,466`
- Should use `?? TaxDefaults::IHT_RATE`. Effort: Trivial

#### HV-05: CGTHarvestingCalculator hardcodes `?? 0.20`
- **File:** `app/Services/Investment/Tax/CGTHarvestingCalculator.php:44`
- Effort: Trivial

#### HV-06: TrustService hardcodes `0.45` / `0.3935` trust rates
- **File:** `app/Services/Estate/TrustService.php:196`
- Effort: Trivial

#### HV-07: UKTaxCalculator hardcodes `0.45` for unknown trust types
- **File:** `app/Services/UKTaxCalculator.php:472`
- Effort: Trivial

#### HV-08: AssetLocationOptimizer hardcodes CGT rates `0.10` / `0.20`
- **File:** `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php:104`
- Effort: Trivial

#### HV-09: ContributionWaterfallService hardcodes tax relief rates
- **File:** `app/Services/Investment/Recommendation/ContributionWaterfallService.php:317-322,731-735`
- Effort: Trivial

### WARNING — Frontend (user-facing text)

#### HV-10: 11 Vue components hardcode £3,000 CGT / £325,000 NRB / £10,000 MPAA
- **Files:** `GiftForm.vue`, `IHTPlanning.vue`, `WrapperOptimizer.vue`, `TaxEfficiencyPanel.vue`, `AccountRebalancingPanel.vue`, `PropertyTaxCalculator.vue`, `GiftingStrategy.vue`, `TrustsDashboard.vue`, `TrustPlanningStrategy.vue`, `AnnualAllowanceTracker.vue`
- Should import from `@/constants/taxConfig.js`. Effort: Small

#### HV-11: Investment store hardcodes ISA allowance `|| 20000`
- **File:** `resources/js/store/modules/investment.js:201`
- Should use `ISA_ANNUAL_ALLOWANCE` from taxConfig.js. Effort: Trivial

---

## SECTION 4: DUPLICATE CODE (4 issues)

### CRITICAL

#### DUP-01: `determineTaxBand` duplicated 7 times (one with bug)
- **Files:** `TaxActionDefinitionService`, `TaxOptimisationService`, `HouseholdPlanningService`, `LifeEventAllocationService`, `PortfolioStrategyService`, `UserContextBuilder`, `PSACalculator`, `SystemPromptBuilder`
- PSACalculator version has a bug (see BROKEN-02). UserContextBuilder uses `TaxDefaults` constants instead of live config.
- **Fix:** Add `determineTaxBand()` to `UKTaxCalculator` and call from all dependents.
- **Effort:** Medium

#### DUP-02: DC pension annual contribution calculated 5 times
- **Files:** `PensionProjector`, `PensionContributionOptimizer`, `RetirementActionDefinitionService`, `RetirementPlanService`, `AnnualAllowanceChecker`
- **Fix:** Extract to shared trait or method. Effort: Small

### WARNING

#### DUP-03: Pension tax relief duplicated 3 times
- **Files:** `PensionContributionOptimizer`, `ContributionOptimizer`, `SavingsActionDefinitionService`
- SavingsActionDefinitionService version hardcodes rates. Effort: Small

#### DUP-04: `calculateFutureValue` duplicated 5 times
- **Files:** `FutureValueCalculator` (shared service exists), `RequiredCapitalCalculator`, `InvestmentPlanService`, `LifePolicyStrategyService`, `LifeCoverCalculator`
- **Fix:** Inject `FutureValueCalculator` into the other services. Effort: Small

---

## SECTION 5: GOD FILES / COMPLEXITY (16 issues)

### CRITICAL — Backend Services (>2,000 lines)

| File | Lines | Change |
|------|-------|--------|
| `SavingsActionDefinitionService.php` | 3,675 | Same |
| `RetirementActionDefinitionService.php` | 2,701 | Same |
| `ProtectionActionDefinitionService.php` | 2,349 | Same |
| `RetirementIncomeService.php` | 2,292 | Same |
| `RetirementStrategyService.php` | 2,141 | Same |

### CRITICAL — Vue Components (>2,000 lines)

| File | Lines | Change |
|------|-------|--------|
| `Admin/TaxSettings.vue` | 3,068 | NEW (was below threshold before tax year changes) |
| `UserProfile/ExpenditureForm.vue` | 2,574 | Same |
| `Public/CalculatorsPage.vue` | 2,471 | Same |
| `Dashboard.vue` | 2,215 | Grew (was 2,124) |
| `Retirement/RetirementIncomeTab.vue` | 2,107 | NEW |

### WARNING — Controllers (>500 lines)

| File | Lines |
|------|-------|
| `InvestmentController.php` | 1,070 |
| `PaymentController.php` | 871 |
| `AdminController.php` | 794 |
| `GoalsController.php` | 792 |
| `RetirementController.php` | 789 |
| `AuthController.php` | 777 |

Plus 20 more Vue components between 800-1900 lines.

---

## SECTION 6: DEAD CODE & BLOAT (12 issues)

### CRITICAL

#### DEAD-01: `guidance` store module + 2 components entirely unused
- **Files:** `store/modules/guidance.js` (392 lines), `Guidance/GuidanceTooltip.vue`, `Guidance/GuidanceWelcomeModal.vue`
- Components never imported by any parent. Effort: Small

#### DEAD-02: `retirement.js` — 4 DC Pension Holdings CRUD actions never dispatched
- **File:** `store/modules/retirement.js:604-647`
- Effort: Small

### WARNING

#### DEAD-03: `PythonAgentBridge.php` — unreferenced service
- Effort: Trivial

#### DEAD-04: `taxOptimisationService.js` — zero imports
- Effort: Trivial

#### DEAD-05: Dead methods across 5 API services
- `advisorService.getReports()`, `advisorService.getClientModules()`, `netWorthService.getBreakdown()`, `dcPensionHoldingsService.getHoldings()`, `portfolioOptimizationService` (5+ methods)
- Effort: Trivial each

#### DEAD-06: Dead store actions in `recommendations.js` (4 actions + summary state)
- Effort: Small

#### DEAD-07: Dead `completeness` store getters (6 unused)
- Effort: Small

#### DEAD-08: Unused model scopes (8 across 4 models)
- `Goal::scopeOnTrack`, `Holding::scopeForInvestmentAccounts`, `LoginAttempt` (4 scopes), `LastingPowerOfAttorney` (3 scopes)
- Effort: Trivial

#### DEAD-09: `InvestmentScenario` model references non-existent `status` column
- `isCompleted()`, `isRunning()`, `markAsFailed()`, `markAsCompleted()` would generate SQL errors at runtime.
- Effort: Small

---

## SECTION 7: MODELS & DATABASE (5 issues)

### CRITICAL

#### DB-01: Float casts on 65 monetary/percentage columns (9 models)
- **Files:** `ExpenditureProfile`, `ProtectionProfile`, `IHTCalculation`, `IHTProfile`, `Estate/Asset`, `Estate/Gift`, `Estate/Liability`, `Investment/Holding`, `Investment/RebalancingAction`
- PHP `float` causes rounding errors on financial data. Must be `'decimal:2'` (monetary) or `'decimal:4'` (percentages).
- **Status:** Known since March 2026 code review. Requires API Resource layer updates before changing to avoid breaking frontend.
- **Effort:** Medium (systematic, needs Resource class updates)

### WARNING

#### DB-02: Invoice model missing Auditable trait
- Financial transaction records should be audit-trailed. Effort: Trivial

#### DB-03: 4 April 2026 models missing factories
- `Invoice`, `DiscountCode`, `DiscountCodeUsage`, `Referral`
- Effort: Small

#### DB-04: InvestmentAccount has 164-field $fillable
- Symptom of wide-table anti-pattern. Long-term extract to child models.
- Effort: Large

#### DB-05: User model at 713 lines (44 casts, 35+ relationships)
- Extract domicile logic and subscription helpers to services.
- Effort: Medium

---

## SECTION 8: CONVENTION DRIFT (8 issues)

### WARNING

#### CONV-01: 26 controllers use inline `$request->validate()` (100+ occurrences)
- Convention requires Form Request classes. Investment sub-controllers have zero Form Requests.
- Effort: Large (78+ new Form Request classes)

#### CONV-02: 92/93 controllers return raw JSON without API Resources
- Convention requires Resource transformation. 22 Resource classes exist but are rarely used.
- Effort: Large (systematic, incremental)

#### CONV-03: 22 Vue components bypass `currencyMixin` (import directly from utils)
- Plus 5 chart components using raw `'£' + val.toLocaleString()`.
- Effort: Small per file

#### CONV-04: 8 single-word Vue component names + 6 duplicate names
- `Footer`, `Navbar`, `Settings` (x2), `Projections`, `Recommendations`, `Version`, snake_case `protection_policies`
- Effort: Trivial

#### CONV-05: 8 controllers use DB facade directly
- `DCPensionHoldingsController`, `RetirementController`, `InvestmentController`, `PaymentController`, `WebhookController`, `PreviewController`, `FamilyMembersController`, `TaxSettingsController`
- Effort: Medium

#### CONV-06: 8 test files use PHPUnit class-style instead of Pest
- Effort: Small per file

#### CONV-07: Vuex mutation naming inconsistency (SCREAMING_SNAKE vs camelCase)
- 17 modules split between two conventions. CLAUDE.md shows camelCase.
- Effort: Large (if full migration)

#### CONV-08: 3 `localStorage.removeItem` calls bypass `tokenStorage.js` abstraction
- Will fail silently on iOS (should use `Preferences.remove()`).
- **Files:** `api.js:114`, `authService.js:103-119`, `sessionLifecycleService.js:105`
- Effort: Small

---

## SECTION 9: ARCHITECTURE (7 issues)

### WARNING

#### ARCH-01: `recommendations` and `infoGuide` stores call `api.js` directly
- Every other store uses a dedicated service wrapper. Effort: Small

#### ARCH-02: `refreshCompleteness` duplicated in `lifeStage` and `completeness` stores
- Same API endpoint called twice on every dashboard load. Effort: Medium

#### ARCH-03: `MonteCarloSimulator` uses `DB::table()` with no Eloquent model
- Effort: Small

#### ARCH-04: AdequacyScorer exposes score data to API (Rule #13)
- Scores may still reach web API responses despite mobile stripping.
- Effort: Medium

#### ARCH-05: Property equity calculation has conflicting semantics in two services
- `PropertyService.php:27` vs `PropertyCalculationService.php:35` — ownership percentage applied differently.
- Effort: Small

#### ARCH-06: Route closure at api.php:1127 (`tax-year/current`)
- Cannot be cached by `route:cache`. Should be extracted to controller.
- Effort: Trivial

#### ARCH-07: Timer leaks in 2 components
- **Files:** `DBPensionForm.vue:298`, `AccountForm.vue:831`
- From previous report, still present. Effort: Trivial

---

## SECTION 10: TEST COVERAGE (5 issues)

### CRITICAL

#### TEST-01: ~85 services with zero test coverage
- **Priority untested services:** `IHTCalculationService` (1,641 lines, backbone of Estate module), all Investment Analytics/Rebalancing/Performance/Tax/Goals sub-services (~35 services), 6 Retirement calculation services, 10 Goals services, `RevolutService`, `InvoiceService`
- **Current coverage:** ~20% of services have tests (47 of 234)
- **Target:** 40% (add ~47 test files)
- Effort: Large

### WARNING

#### TEST-02: 6 test files missing `Mockery::close()`
- `RecommendationEngineTest`, `AdequacyScorerTest`, `CoverageGapAnalyzerTest`, `ScenarioBuilderTest`, `UserProfileServiceTest`, `LiquidityAnalyzerTest`
- Effort: Trivial

#### TEST-03: Hardcoded tax values in test assertions (not mocks)
- 4 files hardcode `325000`, `175000` in assertion values.
- Effort: Small

#### TEST-04: Missing feature tests for 5 module endpoints
- Goals CRUD, WhatIf, Savings action definitions, Tax optimisation, Life Stage
- Effort: Medium each

#### TEST-05: 14 test files over 500 lines
- Prime candidates for splitting. Effort: Small per file

---

## Confirmed Clean Areas

- **strict_types:** 100% across all PHP files (maintained)
- **Banned colour tokens:** Zero amber-*, orange-*, primary-*, secondary-* (maintained)
- **v-for :key:** Zero missing bindings (maintained)
- **v-if/v-for same element:** Zero violations (maintained)
- **Composer vulnerabilities:** Zero (improved from 2 CVEs)
- **Hardcoded tax year strings:** Zero in user-facing components (maintained)
- **Score displays:** Zero in user-facing UI (maintained)
- **Custom @keyframes duplicating app.css:** Zero (all unique animations)
- **SoftDeletes:** Properly applied to financial models (maintained)
- **Foreign key indexes:** Properly addressed (maintained)
- **Constructor injection:** All services use `private readonly` (maintained)

---

## Recommended Action Plan

### Immediate (this week — trivial/small effort, high impact)

| # | Fix | Effort | Impact |
|---|-----|--------|--------|
| 1 | BROKEN-01: Register `$toast` global in app.js | 10 min | Fixes 23 silent failures |
| 2 | BROKEN-02: Fix PSACalculator `determineTaxBand` return | 5 min | Fixes wrong PSA for low-income |
| 3 | TEST-02: Add Mockery::close() to 6 files | 10 min | Test reliability |
| 4 | HV-01/02: Fix hardcoded thresholds in DecumulationPlanner + RetirementActionDef | 10 min | Tax accuracy |
| 5 | HV-03/04/05/06/07/08: Fix 8 remaining hardcoded rate fallbacks | 30 min | Tax accuracy |
| 6 | DEAD-03/04/05: Remove dead PythonAgentBridge + taxOptimisationService + dead methods | 15 min | Code hygiene |
| 7 | DB-02: Add Auditable to Invoice model | 5 min | Audit trail |
| 8 | ARCH-06: Extract route closure to TaxYearController | 10 min | Route caching |
| 9 | ARCH-07: Fix 2 timer leaks | 15 min | Memory leaks |

### Short-term (this month)

| # | Fix | Effort |
|---|-----|--------|
| 10 | DUP-01: Consolidate determineTaxBand to UKTaxCalculator | 3-4 hours |
| 11 | DUP-02/03: Consolidate pension contribution + tax relief calculations | 2-3 hours |
| 12 | HV-10: Replace 11 hardcoded Vue financial thresholds with taxConfig.js | 2 hours |
| 13 | CONV-03: Migrate 22 components to currencyMixin | 2-3 hours |
| 14 | CONV-08: Replace 3 bare localStorage calls with tokenStorage.js | 1 hour |
| 15 | DEAD-01/02/06/07: Remove dead guidance module + dead store actions/getters | 2 hours |
| 16 | DB-03: Create 4 missing factories (Invoice, DiscountCode, DiscountCodeUsage, Referral) | 2 hours |
| 17 | VULN-01: npm audit fix + test | 4-6 hours |

### Backlog (next sprint+)

| # | Fix | Effort |
|---|-----|--------|
| 18 | God class decomposition (5 backend services + 5 Vue components) | Multi-sprint |
| 19 | DB-01: Float-to-decimal cast sweep (65 columns, 9 models + Resource updates) | Full sprint |
| 20 | CONV-01: Form Request migration (26 controllers, ~78 new classes) | Full sprint |
| 21 | CONV-02: API Resource adoption (incremental per module) | Ongoing |
| 22 | TEST-01: Increase test coverage to 40% (~47 new test files) | Multi-sprint |
| 23 | CONV-05: Extract DB transactions from 8 controllers to services | 4-6 hours |
| 24 | DB-04: InvestmentAccount polymorphic sub-type extraction | Large |

---

## Comparison to Previous Report (30 March 2026)

| Area | 30 March | 9 April | Trend |
|------|----------|---------|-------|
| Total issues | 47 | 68 | +21 (deeper scan) |
| Critical | 6 | 12 | +6 (new categories scanned) |
| Composer CVEs | 2 | 0 | Resolved |
| NPM vulnerabilities | 9 high | 14 (11 high) | Worsened |
| Hardcoded tax values (backend) | 0 | 9 | Regression (new code paths) |
| Hardcoded tax values (frontend) | 0 | 11 | Regression (new components) |
| strict_types | 100% | 100% | Maintained |
| Banned colour tokens | 0 | 0 | Maintained |
| God files >2,000 lines (backend) | 5 | 5 | Stable |
| God files >2,000 lines (frontend) | 2 | 5 | Worsened (+3 grew past threshold) |
| Test coverage | ~19% | ~20% | Stable |
| Dead code (frontend) | 2 modules | 12+ items | More thoroughly scanned |
| Broken features | 0 | 2 | NEW ($toast, PSACalculator) |

### What improved:
- Composer vulnerabilities resolved (2 → 0)
- Design system compliance maintained (zero banned tokens)
- Code hygiene maintained (strict_types, v-for keys, SoftDeletes)

### What regressed:
- NPM vulnerabilities increased (new Vite CVE)
- Hardcoded tax values crept back (new code in April additions)
- 3 Vue components crossed the 2,000-line threshold
- $toast feature was broken (likely always was, but now discovered)

### New findings (not regressions):
- Frontend stores/services scanned for first time — found 12 dead code items
- Test coverage gaps enumerated in detail — ~85 untested services identified
- Duplicate calculation patterns mapped across backend services

---

*Generated by tech-debt-full skill — 9 April 2026*
