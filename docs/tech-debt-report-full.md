# Full Codebase Tech Debt Report

**Date:** 18 April 2026
**Codebase:** Fynla v1.0
**Files scanned:** 489 Vue components, 138 Views, 239 PHP services, 98 controllers, 97 models, 35 Vuex stores, 45 frontend services, 211 tests, 173 migrations, 64 factories
**Total issues:** 101
**Previous report:** 9 April 2026 (v0.9.4, 68 issues)

## Executive Summary

| Severity | Count | Previous (Apr 9) |
|----------|-------|------------------|
| Critical | 7 | 12 |
| Warning | 58 | 42 |
| Suggestion | 36 | 14 |
| **Total** | **101** | **68** |

| Category | Count |
|----------|-------|
| God Files / Complexity | 41 (28 Vue + 13 backend) |
| Dead Code / Bloat | 33 |
| Convention Drift | 16 |
| Test Coverage | 8 |
| Inconsistency | 9 |
| Duplicate Code | 5 |
| Security / Vulnerabilities | 1 |

**Trend:** Issue count increased from 68 → 101, but the critical count DROPPED from 12 → 7. The increase is driven by deeper scanning of Vue components (index-based `:key` attributes, inline styles) and a fuller enumeration of orphaned Vuex actions. Core financial-correctness issues have been significantly reduced.

### Progress since April 9

**Resolved (verified):**
- `$toast` global property registered in `app.js:63-74` — no more silent notification failures
- `PSACalculator::determineTaxBand` now returns `'non_taxpayer'` for below-PA earners (line 88-89) — the semantic bug is fixed
- `PythonAgentBridge.php` removed
- `taxOptimisationService.js` removed
- All 12+ hardcoded tax values in backend services removed — backend now clean
- Banned color tokens (`amber-*`, `orange-*`, `primary-*`, `secondary-*`) — zero hits across `resources/js/` (design system compliance restored)
- All 97 models have `declare(strict_types=1)` (was ~90%)
- All 51 domain models correctly use `Auditable` trait
- No `'sole'` enum violations anywhere

**Regressed or new:**
- Float casts on monetary columns: 65 → **70** (worsened — 5 new float casts added on `Estate/IHTCalculation` and `Investment/RebalancingAction`)
- NPM vulnerabilities: 14 → **16** (11 high, 5 moderate)
- `SavingsActionDefinitionService` grew to 3,686 lines (was under 3k)

---

### Quick Wins (trivial effort, high impact)

1. **Fix 19 missing `declare(strict_types=1)` in migrations + 19 in factories** — add one line each, purely mechanical (15 min total)
2. **Replace 6 generic `throw new \Exception(...)` in services with `FinancialCalculationException` factories** — IntestacyCalculator, ScenarioService, ComprehensiveProtectionPlanService, OnboardingService (10 min)
3. **Remove 17 dead API service methods** — estate (8), goals (6), investment (2), protection (1). Pure deletion (15 min)
4. **Rename 7 single-word Vue components** (`Navbar.vue → AppNavbar.vue`, `Footer.vue → AppFooter.vue`, etc.) + update imports (30 min)
5. **Add `afterEach(Mockery::close())` to 2 test files** (BaseAgentTest, ProfileCompletenessTest) (5 min)
6. **Delete stale `DatabaseBackup.vue`** if confirmed unused (5 min)

### Critical Priority

1. **Monetary precision — 70 float casts on currency columns across 12 models.** Root cause of rounding-drift bugs. Biggest offenders: `Estate/IHTCalculation` (23 float casts), `Investment/RebalancingAction` (13), `Investment/Holding` (9), `ExpenditureProfile` (8), `ProtectionProfile` (7). Must be `decimal:2`.
2. **NPM vulnerabilities — 16 total (11 high).** Up from 14. Run `npm audit fix` and gate next PR on it.
3. **Orphaned Vuex actions — 60+ never dispatched** across estate (20), investment (11), retirement (14), goals (16). Dead code paths create confusion about which actions are real.
4. **God files — 41 over 800 lines.** `SavingsActionDefinitionService.php` at **3,686 lines** is the single worst file. `TaxSettings.vue` at 3,068 lines, `ExpenditureForm.vue` at 2,574 lines.
5. **Missing tests on critical financial services.** 134 of 239 services have zero unit tests. Highest-risk gaps: `IHTCalculationService`, `MarkowitzOptimizer`, `RequiredCapitalCalculator`, `SalarySacrificeAnalyzer`, plus 5 of 9 agents.
6. **54 controllers use inline `$request->validate()`** instead of Form Request classes (56% of controllers). Violates CLAUDE.md convention.
7. **8 controllers directly use `DB::` facade** instead of going through services.

---

## SECTION 1: SECURITY & DEPENDENCIES (1 issue)

### CRITICAL

#### VULN-01: NPM vulnerabilities (16 total, 11 high + 5 moderate)

- **Count trend:** 9 → 14 (Apr 9) → 16 (Apr 18). Growing.
- **composer audit:** Clean — no PHP advisories.
- **Fix:** `npm audit fix --force` (breaking — test PWA + mobile + Vite build after). Consider incremental upgrades of the worst offenders (tar, @capacitor/cli, vite, happy-dom).
- **Effort:** Medium (requires regression test of the full build pipeline)

---

## SECTION 2: BACKEND SERVICES (24 issues)

**Summary from scan:** 239 services across 35 modules. All files have `declare(strict_types=1)`. No hardcoded tax values remain in services (fixed since April 9). 13 files over 800 lines. Three monolithic "ActionDefinitionService" classes in Savings / Retirement / Protection each exceed 2,000 lines. Income tax band allocation logic is duplicated across three services. Four services call `request()->ip()` directly instead of receiving it via parameter.

### CRITICAL — Complexity

#### SVC-01: SavingsActionDefinitionService is 3,686 lines
- **File:** `app/Services/Savings/SavingsActionDefinitionService.php`
- **Problem:** 50+ recommendation evaluation methods in a single class, no horizontal decomposition.
- **Fix:** Decompose into `ISARecommendationEvaluator`, `PSARecommendationEvaluator`, `FSCSRecommendationEvaluator`, etc. — same pattern the agents use.
- **Effort:** Large

#### SVC-02: RetirementActionDefinitionService is 2,701 lines
- **File:** `app/Services/Retirement/RetirementActionDefinitionService.php`
- **Fix:** Split into pension-consolidation / contribution-optimisation / decumulation / tax-relief / annuity evaluators.
- **Effort:** Large

#### SVC-03: ProtectionActionDefinitionService is 2,349 lines
- **File:** `app/Services/Protection/ProtectionActionDefinitionService.php`
- **Fix:** Decompose by protection type (LifeCover, CriticalIllness, IncomeProtection, ProtectionGap evaluators).
- **Effort:** Large

#### SVC-04: RetirementIncomeService is 2,292 lines
- **File:** `app/Services/Retirement/RetirementIncomeService.php`
- **Fix:** Extract `StatePensionCalculator`, `AnnuityEvaluator`, `DrawdownPlanner`, `TaxFreeLumpSumCalculator`.
- **Effort:** Large

#### SVC-05: IHTCalculationService is 1,641 lines
- **File:** `app/Services/Estate/IHTCalculationService.php`
- **Fix:** Extract `NilRateBandCalculator`, `RNRBCalculator`, `TrustIHTCalculator`, `ReliefCalculator`. IHTCalculationService becomes orchestrator.
- **Effort:** Large

### WARNING — Duplicate Code

#### SVC-06: Income tax band allocation duplicated across 3 services
- **Files:** `Investment/DividendTaxCalculator.php:25-92`, `UKTaxCalculator.php:32-200`, `Savings/PSACalculator.php:79-100`
- **Problem:** Personal allowance tapering, band boundary calculation, and income positioning logic exist in 3 places.
- **Fix:** Consolidate into a single `TaxBandAllocator` service; use existing `TaxBandTracker` consistently.
- **Effort:** Medium

#### SVC-07: CGT liability calculation duplicated across 3 services
- **Files:** `Investment/TaxEfficiencyCalculator.php:62-90`, `Property/PropertyTaxService.php:~80-120`, `Investment/Rebalancing/TaxAwareRebalancer.php:34-100`
- **Fix:** Create `CGTCalculator` with `calculateTaxableGains()` / `determineCGTRate()` / `calculateLiability()`.
- **Effort:** Medium

#### SVC-08: Income resolution logic duplicated across profile services
- **Files:** `UserProfile/UserProfileService.php:~400-500`, `UserProfile/PersonalAccountsService.php:~200-300`, `Tax/IncomeDefinitionsService.php:~150-300`
- **Fix:** `ResolvesIncome` trait already exists — adopt consistently instead of re-implementing.
- **Effort:** Small

### WARNING — Convention

#### SVC-09: LoginLockoutService uses `request()->ip()` directly
- **File:** `app/Services/Auth/LoginLockoutService.php:57-62`
- **Fix:** Inject IP as parameter from controller layer. Services shouldn't access globals.
- **Effort:** Small

#### SVC-10: PasswordResetService uses `request()->ip()` in two places
- **File:** `app/Services/Auth/PasswordResetService.php`
- **Effort:** Small

#### SVC-11: SessionService calls `request()->user()` for token retrieval
- **File:** `app/Services/Auth/SessionService.php`
- **Fix:** Accept `User $user` as constructor/method parameter.
- **Effort:** Small

#### SVC-12: 6 instances of generic `\Exception` throws instead of `FinancialCalculationException`
- **Files:**
  - `Estate/IntestacyCalculator.php:22` — 'User not found'
  - `Investment/ScenarioService.php:186` — 'At least 2 completed scenarios required'
  - `Protection/ComprehensiveProtectionPlanService.php:41, 48`
  - `Onboarding/OnboardingService.php:97, 942, 982` — duplicate 'Life stage not set'
- **Fix:** Use domain exception factory methods (`FinancialCalculationException::missingData`, `::invalidInput`, `::insufficientData`).
- **Effort:** Trivial per instance

### SUGGESTION — Complexity (additional god files)

| File | Lines | Suggestion |
|------|-------|------------|
| `Investment/InvestmentActionDefinitionService.php` | 1,486 | Same decomposition pattern as Savings/Retirement |
| `Investment/Recommendation/ContributionWaterfallService.php` | 1,032 | Extract `WrapperEvaluator` per wrapper type |
| `Coordination/HouseholdPlanningService.php` | 1,004 | Extract `SpousalOptimizationService`, `DeathScenarioModeler` |
| `AI/SystemPromptBuilder.php` | 988 | Fine as-is for now (prompt construction is inherently long) |
| `AI/AiToolDefinitions.php` + `XaiToolDefinitions.php` | 974 + 888 | Create `ToolRegistry` interface to eliminate duplication |
| `UKTaxCalculator.php` | 844 | Extract `NICalculator` (Class 1, 2, 4) |
| `CoordinatingAgent.php` | 2,635 | Would benefit from extracting into focused sub-coordinators per domain |

### SUGGESTION — Pattern

#### SVC-13: `MonteCarloSimulator` uses `DB::table()` for caching
- **File:** `app/Services/Investment/MonteCarloSimulator.php:143-196`
- **Fix:** Use `Cache::remember()` with database store. Cleaner abstraction, framework-aligned.
- **Effort:** Small

---

## SECTION 3: CONTROLLERS & HTTP (20 issues)

**Summary from scan:** 98 controllers total. 6 exceed 300 lines. 54 controllers (56%) use inline `$request->validate()` instead of Form Request classes. 8 controllers directly call `DB::`. No single-path preview interceptor gaps detected. Route conventions are inconsistent (mixed PUT/PATCH, mixed `{id}` / named parameters).

### CRITICAL — Fat Controllers

| Controller | Lines | Top Problem |
|------------|-------|-------------|
| `InvestmentController.php` | 1,070 | `storeAccount` is 137 lines, `updateAccount` is 129 lines |
| `PaymentController.php` | 956 | Revolut + discount + invoice logic inline |
| `AdminController.php` | 873 | 8 inline `$request->validate()` calls |
| `GoalsController.php` | 792 | Goal analysis + strategy in controller |
| `RetirementController.php` | 789 | 2 direct `DB::` calls |
| `AuthController.php` | 777 | Even with AuthenticationService, still heavyweight |

**Fix pattern:** Extract to service layer. Controller methods should be 20-30 lines max.

### WARNING — Inline Validation

#### HTTP-01: 54 controllers use `$request->validate()` instead of Form Requests
- **Scope:** AdminController (8 methods), PaymentController, AiChatController, MFAController, ReferralController, UserProfileController, TaxSettingsController, RiskPreferenceController, RetirementController, PropertyController, PasswordResetController, OnboardingController, MortgageController, LifeStageController, LetterToSpouseController, InvestmentProjectionController, RecommendationsController, ProtectionController, PortfolioOptimizationController, HolisticPlanningController, DocumentController, PreviewController, SessionController, GDPRController, WhatIfScenarioController, GoalsController, ChattelController, JourneyController, BugReportController, LifeEventController, and 24 more.
- **Fix:** Systematic refactor — one Form Request per inline validation. CLAUDE.md says validation MUST go through Form Requests.
- **Effort:** 60-80h total, ~1-2h per controller. Prioritise high-volume controllers (Admin, Payment, Retirement).

### WARNING — DB Facade in Controllers

| Controller | Occurrences |
|------------|-------------|
| `TaxSettingsController.php` | 4 |
| `Retirement/DCPensionHoldingsController.php` | 3 |
| `RetirementController.php` | 2 |
| `PaymentController.php` | 2 |
| `FamilyMembersController.php` | 2 |
| `WebhookController.php`, `PreviewController.php`, `InvestmentController.php` | 1 each |

**Fix:** Move DB operations into services. Controller should inject and call service methods only.

### WARNING — Response Consistency

#### HTTP-02: 15+ controllers return raw `response()->json($model)` without API Resource wrapping
- **Example:** `Estate/TrustController.php:40-43` returns `response()->json($trusts)` — no `TrustResource`.
- **Fix:** Create missing Resource classes; transform all model responses via Resources.

#### HTTP-03: Inconsistent route parameter naming and verbs
- **File:** `routes/api.php:296-325, 366`
- **Problem:** Mixed `{id}` / `{propertyId}`, mixed PUT vs PATCH for updates.
- **Fix:** Standardise — `{id}` for single resource, `{parentId}/{id}` for nested. PATCH for partial, PUT for full replacement. Document in `app/Http/CLAUDE.md`.

### SUGGESTION — Documentation

#### HTTP-04: Preview interceptor gap process is manual
- **File:** `app/Http/Middleware/PreviewWriteInterceptor.php:47-72`
- **Observation:** EXCLUDED_ROUTES is comprehensive but new auth routes have to be added manually. Already documented in root CLAUDE.md (Rule 8), so this is adequate.

---

## SECTION 4: MODELS & DATABASE (11 issues)

**Summary from scan:** 97 models (all have `strict_types`), 173 migrations (19 missing `strict_types`), 64 factories (19 missing `strict_types`). All domain models correctly use `Auditable` trait. Foreign keys properly constrained. Canonical enums respected — no `'sole'` violations. One god model (`User.php` at 713 lines).

### CRITICAL — Monetary Precision

#### DB-01: 70 float casts on monetary columns across 12 models — REGRESSED FROM APR 9
- **Was 65, now 70.** Float precision is unsafe for currency calculations (rounding errors in penny-level arithmetic).
- **Files:**
  - `ExpenditureProfile.php` — 8 float casts on monthly_* columns + total_monthly_expenditure
  - `ProtectionProfile.php` — 7 float casts (annual_income, monthly_expenditure, mortgage_balance, other_debts, death_in_service_multiple, group_ip_benefit_percent, group_ci_amount)
  - `Estate/Asset.php` — 1 cast (current_value)
  - `Estate/Gift.php` — 1 cast (gift_value)
  - `Estate/IHTCalculation.php` — **23 casts** (user_gross_assets, spouse_gross_assets, total_gross_assets, nrb_available, rnrb_available, taxable_estate, iht_liability, effective_rate, projected_* variants)
  - `Estate/IHTProfile.php` — 4 casts
  - `Estate/Liability.php` — 3 casts (current_balance, monthly_payment, interest_rate)
  - `Investment/Holding.php` — 9 casts
  - `Investment/RebalancingAction.php` — 13 casts
  - `Investment/InvestmentGoal.php` — 1 cast (target_amount)
  - `Investment/RiskProfile.php` — 1 cast (capacity_for_loss_percent)
  - `RecommendationTracking.php` — 2 casts (priority_score, recommended_amount)
- **Fix:** Replace every `'float'` with `'decimal:2'` on currency columns (and appropriate precision for percentages). Ensure DB columns are `DECIMAL(15,2)` or tighter. Add architecture test to prevent regression.
- **Effort:** Medium (each model is trivial; combined ~4h + regression testing)

### WARNING — Convention

#### DB-02: 19 migrations missing `declare(strict_types=1)`
- **Files:** 2025_12_30_103416, 2025_12_30_110842, 2025_12_30_160326, 2026_01_08_091458, 2026_01_10_131616, 2026_01_12_115104, 2026_01_17_092200, 2026_01_24_091552, 2026_01_28_163920, 2026_01_29_082107, 2026_01_29_140000, 2026_01_31_135615, 2026_01_31_154201, 2026_02_17_120040, 2026_02_21_104352, 2026_02_21_104355, 2026_03_18_100000, 2026_03_18_100001, 2026_03_18_100002
- **Effort:** Trivial (sed one-liner)

#### DB-03: 19 factories missing `declare(strict_types=1)`
- **Files:** UserFactory, HouseholdFactory, FamilyMemberFactory, PersonalAccountFactory, CashAccountFactory, SavingsAccountFactory, MortgageFactory, PropertyFactory, ChattelFactory, BusinessInterestFactory, DCPensionFactory, DBPensionFactory, StatePensionFactory, RetirementProfileFactory, LifeInsurancePolicyFactory, CriticalIllnessPolicyFactory, IncomeProtectionPolicyFactory, SicknessIllnessPolicyFactory, DisabilityPolicyFactory
- **Effort:** Trivial

### WARNING — Schema Churn

#### DB-04: 9 migrations use `->change()` to alter columns
- **Indicates:** Schema indecision during the feature cycle. Some are legitimate fixes (FK corrections, widening encrypted columns); the rest suggest the initial migration design could be tightened.
- **No action required** unless pattern continues.

### SUGGESTION — Dead Code

#### DB-05: 56 local scopes defined — spot check shows dead scopes
- **Examples:** `AdvisorClient::scopeActive()`, `AiConversation::scopeActive()`, `Goal::scopeActive()` — no `->active()` calls in the codebase.
- **Action:** Full sweep needed. Remove or document why retained.

### SUGGESTION — Size

#### DB-06: `User.php` is 713 lines
- **File:** `app/Models/User.php`
- **Observation:** Approaching god-model territory. Consider extracting trait groups (`HasHousehold`, `HasAssets`, `HasAccounts`).
- **Effort:** Medium (but not urgent)

---

## SECTION 5: VUE COMPONENTS (30 issues)

**Summary from scan:** 489 components. Design system compliance is strong — zero banned color tokens, zero hardcoded hex in style blocks, all `formatCurrency` delegated to `currencyMixin`, no acronym leaks to user-facing text, no score displays. Primary issues: 28 god components (>800 lines), 7 single-word component names, 35+ v-for loops using `index` as `:key` (anti-pattern), 1 likely duplicate component.

### CRITICAL — God Components (28 total, top 12 listed)

| File | Lines |
|------|-------|
| `Admin/TaxSettings.vue` | 3,068 |
| `UserProfile/ExpenditureForm.vue` | 2,574 |
| `views/Public/CalculatorsPage.vue` | 2,471 |
| `views/Dashboard.vue` | 2,231 |
| `Retirement/RetirementIncomeTab.vue` | 2,107 |
| `NetWorth/PensionList.vue` | 1,891 |
| `NetWorth/Property/PropertyForm.vue` | 1,889 |
| `views/Version.vue` | 1,841 |
| `UserProfile/LetterToSpouse.vue` | 1,789 |
| `Estate/IHTPlanning.vue` | 1,713 |
| `Onboarding/OnboardingWizard.vue` | 1,620 |
| Plus 17 more >800 lines | |

**Also god-file:** `resources/js/components/Plans/Shared/planPrintMixin.js` at **3,199 lines** (not a component but lives in components/)

**Fix:** Each needs decomposition. Prioritise `TaxSettings.vue` (admin surface) and `ExpenditureForm.vue` (user-facing form complexity is a risk for UX bugs).

### WARNING — Single-Word Component Names (7)

- `components/Navbar.vue` → `AppNavbar.vue`
- `components/Footer.vue` → `AppFooter.vue`
- `components/Savings/Recommendations.vue` → `SavingsRecommendations.vue`
- `components/Investment/Goals.vue` → `InvestmentGoals.vue`
- `components/Investment/Performance.vue` → `InvestmentPerformance.vue`
- `components/Investment/Holdings.vue` → `InvestmentHoldings.vue`
- `components/UserProfile/Settings.vue` → `ProfileSettings.vue`

### WARNING — `v-for` Index Keys (35+ instances)

Anti-pattern: using array index as `:key` breaks DOM-diff guarantees on reordering / add / remove.

Examples:
- `Insights/blocks/ListBlock.vue:7`
- `Insights/blocks/KeyTakeawaysBlock.vue:7`
- `Estate/TrustPlanningStrategy.vue:90`
- `Estate/LpaWizardSteps/NotificationPersonsStep.vue:1`
- `Estate/LpaWizardSteps/AttorneysStep.vue:1`

**Fix:** Use stable unique ID from the item (e.g. `:key="person.id"`).

### WARNING — Missing `:key` on v-for (estimated 150+)

Sampled hits:
- `Insights/blocks/KeyTakeawaysBlock.vue:6`
- `Insights/ArticleBlockRenderer.vue:1`
- `Settings/SettingsTabBar.vue:1`
- `Trusts/TrustsOverviewCard.vue:1`

**Fix:** Run `eslint --fix` with the vue/require-v-for-key rule enabled.

### SUGGESTION — Excessive Inline Styles

| Component | Inline Styles |
|-----------|---------------|
| `Goals/GoalsProjectionChart.vue` | 46 |
| `Dashboard/GoalsProjectionChartDashboard.vue` | 19 |
| `NetWorth/WealthSummary.vue` | 18 |
| `UserProfile/BalanceSheetTab.vue` | 15 |

**Fix:** Extract to `<style scoped>` with `@apply`, or compute style objects using `designSystem.js` constants.

### SUGGESTION — Duplicate

- `components/Admin/DatabaseBackup.vue` — name suggests legacy. Verify usage, delete if dead.

---

## SECTION 6: STORES & FRONTEND SERVICES (17 issues)

**Summary from scan:** 35 Vuex modules, 45 API service files. `$toast` properly registered (fixed since Apr 9). No hardcoded tax values found in stores/services/utils. No direct axios calls from components (API layer properly abstracted). Issues concentrate on orphaned actions (60+), dead API methods (17), duplicate family-members endpoint, and 46 `console.*` calls in production code.

### CRITICAL — Orphaned Vuex Actions

#### STORE-01: Estate module — 20 of 24 actions never dispatched
- **File:** `resources/js/store/modules/estate.js`
- **Dead actions:** analyseEstate, calculateIHT, fetchNetWorth, fetchCashFlow, createAsset, updateAsset, deleteAsset, createLiability, updateLiability, deleteLiability, createGift, updateGift, deleteGift, createTrust, updateTrust, deleteTrust, createLpa, updateLpa, removeLpa, removeTrust

#### STORE-02: Investment module — 11 of 17 orphaned
- **File:** `resources/js/store/modules/investment.js`
- **Dead:** createAccount, createHolding, deleteAccount, deleteHolding, fetchPortfolioProjections, fetchRecommendations, pollMonteCarloResults, runScenario, saveRiskProfile, startMonteCarlo, analyzeAssetLocation

#### STORE-03: Retirement module — 14 of 20 orphaned
- **File:** `resources/js/store/modules/retirement.js`
- **Dead:** createDBPension, createDCPension, fetchIncomeAccounts, fetchProjections, fetchRetirementIncome, fetchStrategies, toggleIncludedCash, toggleIncludedInvestment, calculateRetirementIncome, calculateStrategyImpact, fetchDecumulationAnalysis, fetchPortfolioAnalysis, fetchRequiredCapital, runScenario

#### STORE-04: Goals module — 16 of 21 orphaned
- **File:** `resources/js/store/modules/goals.js`
- **Dead:** createGoal, createLifeEvent, deleteGoal, deleteLifeEvent, fetchAllocations, fetchDashboardOverview, fetchDependencies, fetchEventTypes, fetchGoal, fetchGoalTypes, fetchProjection, recordContribution, regenerateAllocations, setViewMode, updateAllocation

**Fix across STORE-01–04:** Delete unused actions AND the matching `api{Service}.{method}` callers AND mutations. Confirm nothing dispatches them with `rg "dispatch.*{actionName}"` before removing. **Warning:** some of these may be called via the AI form-fill flow (which dispatches dynamically) — verify before deletion.

### WARNING — Dead API Service Methods (17)

- `estateService.js` — 8 dead: analyzeTrust, calculateDiscountedGiftDiscount, deleteWillDocument, getRecommendations, getWillDocument, markLpaRegistered, runScenario, storeOrUpdateProfile
- `goalsService.js` — 6 dead: getAnalysis, getHouseholdSummary, getLifeEvent, getProjections, getRiskLevels, getScenarios
- `investmentService.js` — 2 dead: analyzeAssetLocation, getRecommendations
- `protectionService.js` — 1 dead: runScenario

**Fix:** Delete. If the backend controller is also dead, delete that + its route.

### WARNING — Duplicate API Client

#### STORE-05: `/user/family-members` defined in two services
- **Files:** `userProfileService.js` AND `familyMembersService.js` — line-for-line duplicate GET and POST
- **Fix:** Pick one (familyMembersService is more conventional). Update all callers; delete duplicate.

### WARNING — God Stores

- `estate.js` (876 lines), `retirement.js` (824), `investment.js` (770), `netWorth.js` (734)
- **Fix:** Split into sub-modules. The orphaned-actions fix will naturally reduce these sizes.

### WARNING — Mixed State Naming Conventions (12 modules)

- `aiChat.js`, `aiFormFill.js`, `estate.js`, `goals.js`, `insights.js`, `investment.js`, `mobileDashboard.js`, `mobileNotifications.js`, `plans.js`, `preview.js`, `protection.js`, `userProfile.js` mix camelCase and snake_case state keys.
- **Pattern:** `mobileDashboard.js` is the inverse extreme (5 camelCase, 28 snake_case) — because it mirrors backend response field names. **Decision needed:** adopt one convention project-wide OR document the exception for mobile/API-mirror stores.

### SUGGESTION — Console Logging in Production

46 `console.log/debug/info` calls in `services/`, `store/`, `utils/`. Breakdown:
- `services/consoleCapture.js`: 18 (legitimate — that's its purpose)
- `services/dashboardService.js`: 4
- `services/api.js`: 3
- `services/investmentService.js`: 2
- `utils/poller.js`, `utils/logger.js`: 14
- Various stores: 3

**Fix:** Route through `logger` util, which can gate by environment.

### SUGGESTION — State Bloat

- `onboarding.js`: `totalSteps`, `currentStepName`, `currentSkipReason` committed but never read externally.
- `recommendations.js`: `topRecommendations` accessed via `$store.state.recommendations.topRecommendations` in `CoordinationDetail.vue` instead of via a getter.

---

## SECTION 7: TESTS (11 issues)

**Summary from scan:** 211 test files (123 unit, 77 feature, 8 architecture, 3 integration). Only 105 of 239 services have unit tests — **44% coverage**. 5 of 9 agents have no tests. 47 of 98 controllers have no feature tests. 2 Mockery tests missing cleanup. 8 feature tests use `Carbon::now()` without freeze. 2 placeholder `expect(true)->toBeTrue()` assertions.

### CRITICAL — Service Test Coverage

#### TEST-01: Estate module — 15 of 27 services have no unit tests
- **Highest risk:** `IHTCalculationService`, `TrustValuationService`, `WillAnalysisService`, `ComprehensiveEstatePlanService`, `IHTStrategyGeneratorService`, `LifeCoverCalculator`, `LifePolicyStrategyService`, `LpaDocumentService`, `PersonalizedGiftingStrategyService`, `SpouseNRBTrackerService`, `EstateAssetAggregatorService`, `EstateDataReadinessService`, `GiftingStrategyOptimizer`, `IHTFormattingService`, `TrustService`

#### TEST-02: Investment module — 33 of 54 services untested
- **Critical gaps:** `MarkowitzOptimizer`, `ModelPortfolioBuilder`, `CorrelationMatrixCalculator`, `CovarianceMatrixCalculator`, `EfficientFrontierCalculator`, `AccountTypeRecommender`, `AssetLocationOptimizer`, `TaxDragCalculator`, `OCFImpactCalculator`, `PlatformComparator`, `GoalProbabilityCalculator`, `ShortfallAnalyzer`, `FundSelector`, `AlphaBetaCalculator`, `BenchmarkComparator`, `PerformanceAttributionAnalyzer`, `DriftAnalyzer`, `RebalancingCalculator`, `TaxAwareRebalancer`

#### TEST-03: Retirement module — 9 of 12 untested
- **Critical gaps:** `PensionContributionOptimizer`, `PensionPortfolioAnalyzer`, `RequiredCapitalCalculator`, `RetirementDataReadinessService`, `RetirementIncomeService`, `RetirementStrategyService`, `SalarySacrificeAnalyzer`

### WARNING — Agent Test Gaps

#### TEST-04: 5 of 9 agents have no dedicated test file
- Missing: `CoordinatingAgent`, `EstateAgent`, `InvestmentAgent`, `RetirementAgent`, `TaxOptimisationAgent`
- **Present:** `BaseAgent`, `ProtectionAgent`, `SavingsAgent`, `GoalsAgent`
- **Fix:** Follow `ProtectionAgentTest` pattern — mock services + cache, assert response shape, cache key generation.

### WARNING — Controller Feature Test Gaps

#### TEST-05: 47 of 98 API controllers have no feature test
- **Top-risk examples:** `Estate/IHTController`, `Estate/WillController`, `Estate/GiftingController`, `Estate/TrustController`, `Estate/LifePolicyController`, `GoalsController`, `BusinessInterestController`, `TaxYearController`, `LetterToSpouseController`, `Mobile/MobileDashboardController`, `ReferralController`

### WARNING — Mockery Cleanup

#### TEST-06: 2 tests use Mockery without `afterEach(Mockery::close())`
- `tests/Unit/Agents/BaseAgentTest.php:83-105`
- `tests/Feature/Api/ProfileCompletenessTest.php:194`
- **Fix:** Add `afterEach(fn() => Mockery::close());` after the `beforeEach` block.

### WARNING — Flaky Time-Based Tests

#### TEST-07: 8 tests use `Carbon::now()` without `setTestNow()`
- `tests/Feature/Api/DomicileInfoTest.php:44, 69, 86, 108, 125, 224, 239`
- `tests/Feature/Api/TrustsTest.php:66, 99`
- `tests/Feature/Estate/EstateApiTest.php:31`
- **Risk:** Year-boundary failures.
- **Fix:** `beforeEach(fn() => Carbon::setTestNow('2025-06-15'));`

### WARNING — Placeholder Assertions

#### TEST-08: 2 tests with `expect(true)->toBeTrue()` placeholders
- `tests/Feature/RetirementIntegrationTest.php:403`
- `tests/Unit/Services/Coordination/CrossModuleStrategyServiceTest.php:289`
- **Fix:** Replace with real assertions on actual cached data / ordering.

### SUGGESTION — Stale Fixtures

#### TEST-09: 3 tests use `'isa_subscription_year' => '2024-25'`
- `tests/Feature/Savings/SavingsIntegrationTest.php:172`
- `tests/Feature/TaxConfigurationTest.php:78, 99, 220`
- **Fix:** Use dynamic helper: `activeTaxYear()` derived from `now()` per the UK tax year rules.

### SUGGESTION — Architecture Test Gaps

#### TEST-10: Architecture suite doesn't cover
- Vue component naming (PascalCase, multi-word)
- Tax hardcoding in helpers/
- Middleware/Job-layer hardcoding
- Vuex state naming convention
- Float casts on monetary columns (would have caught DB-01 regression)
- **Fix:** Extend `tests/Architecture/` with new rules. Fast feedback loop prevents future regressions.

---

## CROSS-CUTTING ISSUES

### Duplicate Code (5 findings)
- Income tax band allocation (3 services) — SVC-06
- CGT liability (3 services) — SVC-07
- Income resolution (3 services — trait underused) — SVC-08
- AI tool definitions (`AiToolDefinitions` vs `XaiToolDefinitions`)
- `/user/family-members` in two services — STORE-05

### Dead Code (33 findings)
- 60+ orphaned Vuex actions across 4 stores
- 17 dead API service methods
- Estimated 56 local scopes — spot check found dead ones; full sweep recommended
- Likely dead: `Admin/DatabaseBackup.vue`

### Convention Drift (16 findings)
- 19 migrations + 19 factories missing strict_types
- 54 controllers with inline validation
- 8 controllers with `DB::` facade
- 6 services with generic `\Exception`
- 7 single-word Vue component names
- 12 stores with mixed state naming
- 2 Mockery tests without close
- 8 flaky Carbon tests

### Complexity (41 findings)
- 13 backend services > 800 lines
- 28 Vue components > 800 lines
- Plus `planPrintMixin.js` at 3,199 lines

---

## RECOMMENDED ACTION PLAN

### Immediate (this week)
1. **Fix 70 float casts on monetary columns** — single PR, test regression, add architecture rule to prevent recurrence (6–8h)
2. **NPM audit fix** — `npm audit fix`, test build + PWA + iOS, ship (2–4h)
3. **Delete 17 dead API service methods + 60+ orphaned Vuex actions** — pure subtraction (4h). Run AI form-fill regression test first to confirm dynamic dispatch isn't used.
4. **Add `strict_types=1` to 38 files** (migrations + factories) — mechanical (30 min)
5. **Add `afterEach(Mockery::close())` to 2 tests + freeze `Carbon` in 8 tests** (1h)
6. **Replace 6 generic Exception throws with `FinancialCalculationException` factories** (30 min)

**Estimated: 1.5–2 days of focused work, closes all 7 critical items.**

### Short-term (this month)
1. Split the 3 largest `ActionDefinitionService` classes (Savings/Retirement/Protection) — biggest readability win (1 week)
2. Add unit tests for top-10 highest-risk untested services: `IHTCalculationService`, `MarkowitzOptimizer`, `RequiredCapitalCalculator`, `SalarySacrificeAnalyzer`, `TrustValuationService`, `RebalancingCalculator`, `TaxAwareRebalancer`, `AssetLocationOptimizer`, `PensionContributionOptimizer`, `WillAnalysisService` (1–2 weeks)
3. Systematic Form Request conversion for top-10 controllers with inline validation (1 week)
4. Extract services out of the 6 fat controllers (1 week)
5. Add agent tests for the 5 missing agents (3 days)

### Backlog
1. Full sweep of 56 local model scopes — remove dead ones (1–2 days)
2. Rename 7 single-word Vue components + update imports (1 day)
3. Decompose 28 Vue god components — prioritise `TaxSettings.vue` and `ExpenditureForm.vue` (multi-week)
4. Standardise Vuex state naming across 12 mixed modules (3–5 days)
5. Extend architecture test suite with new rules (2 days)
6. Split `User.php` into trait groups if it grows beyond 800 lines

---

## Overall Assessment

The codebase is in healthier shape than on April 9 on the dimensions that matter most for correctness: the $toast silent-failures bug is fixed, the PSACalculator semantic bug is fixed, backend hardcoded tax values are gone, dead services removed, and banned design-system tokens have been purged. Test count grew from 197 to 211. Architecture test coverage for hardcoded tax values via `HardcodedValuesArchitectureTest.php` is now in place.

The dimensions that have not improved — and in two cases regressed — are monetary precision (70 float casts on currency, up from 65), NPM supply-chain vulnerabilities (16, up from 14), and file-size creep in the action-definition services. None of these are new architectural problems — they're all pre-existing debt that hasn't been paid down.

Test coverage remains the single largest risk: 56% of services are untested, including several that touch IHT, portfolio optimisation, and pension calculations where mathematical correctness is material. Every large feature added without tests makes this worse.

Recommendation: spend one focused week on the "immediate" action plan to knock out all 7 critical items. After that, the codebase will be in genuinely good shape for the next quarter's feature work.

---
*Generated by tech-debt-full skill on 18 April 2026*
