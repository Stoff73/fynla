# Code Review Remediation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all Critical (1), High (4), and Medium (7) findings from the full codebase code review.

**Architecture:** Each fix is isolated to specific files with no cross-dependencies, enabling parallel execution. Frontend fixes use existing `designSystem.js` and `taxConfig.js` constants. Backend fixes follow established patterns.

**Tech Stack:** Laravel 10, Vue.js 3, Pest (testing), Tailwind CSS

---

## Task 1: C2 — Replace hardcoded IHT tax values with taxConfig imports

**Files:**
- Modify: `resources/js/components/Estate/LifePolicyStrategy.vue:194-201`
- Modify: `resources/js/components/Estate/IHTCalculationTable.vue:160-170,338-341`
- Modify: `resources/js/mobile/views/EstateDetail.vue:137,144`
- Reference: `resources/js/constants/taxConfig.js` (has `IHT_NIL_RATE_BAND`, `IHT_RESIDENCE_NIL_RATE_BAND`, `IHT_STANDARD_RATE`)

- [ ] Import `IHT_NIL_RATE_BAND`, `IHT_RESIDENCE_NIL_RATE_BAND`, `IHT_STANDARD_RATE` from `@/constants/taxConfig` in each file
- [ ] Replace all `325000` literals with `IHT_NIL_RATE_BAND`
- [ ] Replace all `175000` literals with `IHT_RESIDENCE_NIL_RATE_BAND`
- [ ] Replace all `0.40` / `0.4` IHT rate literals with `IHT_STANDARD_RATE`
- [ ] Remove the dead `computePreviewStrategy()` method from LifePolicyStrategy.vue (M2 combined here)
- [ ] Verify with `./dev.sh` that estate components still render

## Task 2: H1 — Remove scores from user-facing UI

**Files:**
- Modify: `resources/js/components/Dashboard/FinancialHealthScore.vue`
- Modify: `resources/js/components/NetWorth/InvestmentProjections.vue:78-80,654-676`
- Modify: `resources/js/components/Protection/CoverageAdequacyGauge.vue`

- [ ] In `FinancialHealthScore.vue`: Replace composite score display with descriptive text and specific metrics (currency values, coverage gaps, shortfalls). Remove numerical score computation and score-based labels.
- [ ] In `InvestmentProjections.vue`: Replace `taxEfficiencyScore` percentage badge with descriptive text showing tax-sheltered amount vs total. Remove `score-badge`, `score-excellent`, `score-good`, `score-fair`, `score-poor` CSS classes.
- [ ] In `CoverageAdequacyGauge.vue`: Replace score-based radial chart with descriptive coverage summary. Use coverage gap amounts and specific metrics instead of 0-100 score.

## Task 3: H2 — Replace hardcoded hex colors with designSystem.js imports

**Files:**
- Modify: `resources/js/views/Investment/AccountPerformancePanel.vue:845-862`
- Modify: `resources/js/views/Investment/AccountHoldingsPanel.vue:330-331`
- Modify: `resources/js/views/Trusts/TrustsDashboard.vue:486`
- Modify: `resources/js/mobile/components/MobileProjectionChart.vue:66-88`
- Modify: `resources/js/mobile/goals/MilestoneOverlay.vue:113`
- Reference: `resources/js/constants/designSystem.js` (CHART_COLORS, ASSET_COLORS, TEXT_COLORS, BORDER_COLORS)

- [ ] Import ASSET_COLORS from designSystem.js in AccountPerformancePanel.vue and replace all 14 hardcoded hex values
- [ ] Import CHART_COLORS in AccountHoldingsPanel.vue, replace `#ec4899` and `#94a3b8` with palette values
- [ ] Replace `#eff6ff` in TrustsDashboard.vue with Tailwind `@apply` directive
- [ ] Import TEXT_COLORS and BORDER_COLORS in MobileProjectionChart.vue, replace `#717171` and `#EEEEEE`
- [ ] Import PRIMARY_COLORS etc from designSystem.js in MilestoneOverlay.vue, replace confetti color array

## Task 4: H3 — Filter sensitive fields in PreviewWriteInterceptor

**Files:**
- Modify: `app/Http/Middleware/PreviewWriteInterceptor.php:160-169`

- [ ] Add `SENSITIVE_FIELDS` constant array: `['password', 'password_confirmation', 'current_password', 'mfa_secret', 'mfa_recovery_codes', 'token', 'api_key']`
- [ ] Filter these fields from `$request->all()` before echoing in `fakeSuccessResponse()`

## Task 5: H4 — Replace hardcoded ISA allowance with TaxConfigService

**Files:**
- Modify: `app/Services/Retirement/RetirementStrategyService.php:1314`

- [ ] Remove default value `20000` from method signature
- [ ] Use `$this->taxConfig->getISAAllowances()['annual_allowance']` as default within method body

## Task 6: M3 — Extract duplicate keyframes to app.css

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/js/components/Onboarding/steps/BudgetingCompletionStep.vue`
- Modify: `resources/js/components/Onboarding/steps/JourneyCompletionStep.vue`

- [ ] Add `@keyframes checkmark-scale` and `@keyframes checkmark-draw` to app.css as global animations
- [ ] Remove duplicate `@keyframes` from both scoped style blocks
- [ ] Add global utility classes `.animate-checkmark-scale` and `.animate-checkmark-draw`

## Task 7: M4 — Add architecture tests for Models and Controllers

**Files:**
- Modify: `tests/Architecture/ApplicationArchitectureTest.php`

- [ ] Add `arch('all models use strict types')->expect('App\Models')->toUseStrictTypes()`
- [ ] Add `arch('all controllers use strict types')->expect('App\Http\Controllers')->toUseStrictTypes()`
- [ ] Run `./vendor/bin/pest --testsuite=Architecture`

## Task 8: M5 — DebugEnv route already guarded (verify only)

**Files:**
- Read: `resources/js/router/index.js:720-739`

- [ ] Verify the `beforeEnter` guard blocks production access (it does — the `devOnly` meta flag isn't checked in beforeEach but the per-route `beforeEnter` covers it)
- [ ] No code change needed — the route already has a production block

## Task 9: M6 — Fix PropertyController.store() response format

**Files:**
- Modify: `app/Http/Controllers/Api/PropertyController.php`

- [ ] Wrap `store()` return in standard `{ success: true, message, data }` format

## Task 10: M7 — Remove score references from Help and Version pages

**Files:**
- Modify: `resources/js/views/Version.vue:1255`
- Modify: `resources/js/views/Help.vue:314,328`

- [ ] Replace "adequacy scores (0-100)" with "coverage analysis"
- [ ] Replace "Diversification scoring" with "Diversification analysis"
- [ ] Replace "Score out of 100" with descriptive text about retirement readiness assessment

## Task 11: M8 — Add query limits to index endpoints

**Files:**
- Modify: `app/Http/Controllers/Api/EstateController.php`
- Modify: `app/Http/Controllers/Api/GoalsController.php`
- Modify: `app/Http/Controllers/Api/SavingsController.php`

- [ ] Add `.limit(100)` to all unbounded `->get()` calls (matching PropertyController pattern)
