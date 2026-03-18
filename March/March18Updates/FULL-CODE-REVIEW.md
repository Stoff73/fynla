# Fynla Full Codebase Review

**Date:** 18 March 2026
**Version:** v0.9.2
**Scope:** Complete codebase — 403 Vue components, 183 PHP services, 75 controllers, 78 models, 97 database tables
**Reviewers:** Security, Tax Compliance, Backend Architecture, Frontend Quality, Database Optimisation

---

## Executive Summary

The Fynla codebase demonstrates a well-architected financial planning application with strong foundational patterns — centralised tax configuration, consistent Agent/Service/Controller layering, robust authentication with MFA, and comprehensive audit logging. However, this review identified **96 findings** across five domains that need attention before the v1.0 release.

### Findings by Severity

| Domain | Critical | High | Medium | Low | Total |
|--------|----------|------|--------|-----|-------|
| Security | 0 | 2 | 8 | 6 | 16 |
| Tax Compliance | 2 | 8 | 11 | 6 | 27 |
| Backend Architecture | 6 | 10 | — | — | 16 |
| Frontend Quality | 6 | 10 | 5 | — | 21 |
| Database | 3 | 5 | 5 | 3 | 16 |
| **Total** | **17** | **35** | **29** | **15** | **96** |

### Top 10 Priority Fixes

1. **Float precision on financial data** — User model and InvestmentAccount model cast decimal DB columns as `float`, introducing rounding errors across all financial calculations (Database CRIT-1, CRIT-2)
2. **Outdated additional rate threshold** — SavingsActionDefinitionService uses £150,000 instead of £125,140, misclassifying users earning £125k–150k (Tax CRIT-2)
3. **Monte Carlo IDOR** — Results accessible without user-scoped authorisation; any authenticated user can retrieve another user's simulation data (Security HIGH-1)
4. **Silent IHT failure** — EstateAgent swallows IHT calculation exceptions with no logging, returning £0 liability (Backend CRIT-1)
5. **Scores in user-facing UI** — PortfolioOverview, TaxFees, and AssetLocationOptimizer display numerical scores violating Rule 13 (Frontend CRIT-1)
6. **8 services bypass TaxConfigService** — Using TaxDefaults constants or hardcoded values instead of the injected service (Tax HIGH-3 through HIGH-8)
7. **N+1 queries in IHT calculations** — IHTCalculationService lazy-loads 6 relationships per calculation (Database CRIT-3)
8. **Full User model in auth responses** — 100+ attributes including financial data exposed in every login/auth response (Security MED-3)
9. **292 raw console.log calls** — Including auth credentials in MobileLoginScreen, bypassing the logger utility (Frontend IMP-1)
10. **Hardcoded hex values** — AssumptionsSettings and PrivacySettings have 70+ non-palette hex values in scoped styles (Frontend CRIT-3)

---

## 1. Security Review

### Positive Security Observations

The application has a strong security foundation:
- **Rate limiting** on all auth endpoints (login: 5/min, register: 5/min, password reset: 3/min)
- **CSRF protection** via Sanctum stateful authentication
- **Input sanitisation** — global `SanitizeInput` middleware strips HTML tags
- **MFA encryption** — secrets encrypted at rest with `Crypt::encryptString()`, recovery codes individually hashed with bcrypt
- **Ownership checks** — controllers consistently verify `user_id` before CRUD operations
- **Security headers** — X-Frame-Options: DENY, X-Content-Type-Options: nosniff, HSTS, Referrer-Policy, CSP
- **Webhook HMAC** — Revolut webhooks use HMAC-SHA256 with timing-safe comparison and 5-minute replay protection
- **Account lockout** — progressive lockout with per-account and per-IP tracking
- **GDPR compliance** — data export, erasure, and consent management implemented
- **File upload validation** — restricted MIME types (pdf, jpeg, jpg, png, webp), 20MB limit, filename sanitisation
- **Path traversal protection** — admin backup operations use `basename()`, `realpath()`, and path containment checks

### SEC-1: Monte Carlo Results IDOR (HIGH)

**File:** `app/Http/Controllers/Api/InvestmentController.php:233`

The `getMonteCarloResults(string $jobId)` method retrieves cached simulation results using only the UUID. No check that the authenticated user initiated the job. Any authenticated user who knows a valid UUID can access another user's financial projections.

**Fix:** Store `user_id` alongside the job status in cache and verify ownership before returning results:
```php
$cached = Cache::get("monte_carlo_status_{$jobId}");
if (!$cached || $cached['user_id'] !== $request->user()->id) {
    return response()->json(['success' => false, 'message' => 'Job not found'], 404);
}
```

### SEC-2: Agent Token Timing Attack (HIGH)

**File:** `app/Http/Middleware/AgentTokenAuth.php:24`

Token comparison uses `!==` (not timing-safe). An attacker could determine the correct token character-by-character via response timing analysis.

**Fix:** Replace with `hash_equals($expectedToken, $token)`.

### SEC-3: Full User Model in Auth Responses (MEDIUM)

**File:** `app/Http/Controllers/Api/AuthController.php:345-354, 729-741`

Auth responses include the full User model (100+ attributes including income, expenditure, NI number, addresses, dates of birth). Only `password`, `mfa_secret`, and `mfa_recovery_codes` are hidden.

**Fix:** Create a `UserResource` API Resource class that explicitly whitelists fields returned in auth responses.

### SEC-4: Admin Controller Exposes Full User Data (MEDIUM)

**File:** `app/Http/Controllers/Api/AdminController.php:109-131`

Admin user listing returns full User models for every user in the system including financial profiles. Violates least privilege and GDPR data minimisation.

**Fix:** Use a dedicated `AdminUserResource` returning only management fields (id, name, email, role, subscription status).

### SEC-5: Preview Login Without Credentials (MEDIUM)

**File:** `app/Http/Controllers/Api/PreviewController.php:113-162`

`POST /api/preview/login/{personaId}` creates valid Sanctum tokens without authentication. Rate limited to 10/minute (600 tokens/hour). Tokens grant access to computation-heavy endpoints excluded from PreviewWriteInterceptor.

**Fix:** Tighten rate limiting to 3/minute per IP. Consider adding CAPTCHA.

### SEC-6: Unauthenticated Bug Report Endpoint (MEDIUM)

**File:** `routes/api.php:1162`

`POST /api/bug-report` accepts unauthenticated submissions and sends emails directly. Could be used to exhaust email sending quotas.

**Fix:** Verify rate limiting is sufficiently restrictive. Consider requiring a honeypot field.

### SEC-7: PreviewWriteInterceptor Overly Broad Pattern Matching (MEDIUM)

**File:** `app/Http/Middleware/PreviewWriteInterceptor.php:76-86`

`EXCLUDED_PATTERNS` uses `str_contains()`, meaning `/calculate` matches any URL containing "calculate". Future write endpoints with that substring would bypass the interceptor.

**Fix:** Use regex patterns anchored to path segments instead of `str_contains()`.

### SEC-8: MFA Setup Secret Not User-Bound (MEDIUM)

**File:** `app/Http/Controllers/Api/MFAController.php:47, 70`

MFA setup secret stored in session under generic key `mfa_setup_secret`, not tied to user ID.

**Fix:** Use `Cache::put("mfa_setup_secret:{$user->id}", $secret, 300)`.

### SEC-9: Spouse Data Over-Exposure (MEDIUM)

**File:** `app/Http/Controllers/Api/AuthController.php:332-336`

Loading `$user->load('spouse')` serialises the spouse's full financial profile in every auth response.

**Fix:** Use `->only('id', 'first_name', 'surname', 'email')` when serialising spouse data.

### SEC-10: Advisor Impersonation Cache Trust (MEDIUM)

**File:** `app/Http/Middleware/AdvisorImpersonationMiddleware.php:32-38`

The middleware trusts the cache entry without validating the advisor-client relationship itself. If cache integrity is compromised, any user could be impersonated.

**Fix:** Add secondary advisor-client relationship check in middleware.

### SEC-11: DB::raw with Variable Column Name (LOW)

**File:** `app/Agents/CoordinatingAgent.php:1284`

`whereRaw('LOWER('.$nameField.') = ?', ...)` interpolates a column name variable. If `$nameField` is ever user-controlled, this is SQL injection.

**Fix:** Validate `$nameField` against a whitelist of allowed column names.

### SEC-12: v-html Usage in 3 Components (LOW)

**Files:** `AiMessageContent.vue:30`, `LpaDetailView.vue:43`, `WillBuilderReviewStep.vue:44`

Currently safe (HTML is escaped or server-generated from structured data), but consider adding DOMPurify as defence-in-depth.

### SEC-13: Postcode Not URL-Encoded (LOW)

**File:** `app/Http/Controllers/Api/PostcodeLookupController.php:77`

Postcode is validated by regex but not URL-encoded when interpolated into the API URL.

**Fix:** Use `urlencode($postcode)`.

### SEC-14: CSP Allows unsafe-inline (LOW)

**File:** `app/Http/Middleware/SecurityHeaders.php:44-48`

Required for Revolut checkout SDK. Accepted risk with compensating controls (SanitizeInput middleware, Vue template compilation).

### SEC-15: 8-Hour Token Expiration (LOW)

**File:** `config/sanctum.php:49`

Long session window for a financial application. Consider reducing to 2-4 hours for web.

### SEC-16: Legacy localStorage Token References (LOW)

**File:** `resources/js/store/modules/auth.js:44, 81`

`localStorage.removeItem('auth_token')` cleanup references suggest tokens were previously stored in localStorage. Current storage is correctly sessionStorage/Capacitor Preferences.

---

## 2. Tax Compliance Review

### Tax Configuration Architecture

The TaxConfigService architecture is well-designed:
- **TaxDefaults** constants are correct for 2025/26 tax year
- **TaxConfigService** loads from database with request-scoped singleton caching
- **Frontend taxConfig.js** mirrors values correctly with clear fallback documentation
- **Core calculators** (UKTaxCalculator, AnnualAllowanceChecker, PropertyTaxService, IHTCalculationService) consistently use TaxConfigService

### Correct Implementations

- Income tax bands, rates, and PA taper
- National Insurance Class 1 and Class 4
- IHT with NRB, RNRB, taper, 14-year CLT rule, charitable 36% rate
- Pension AA with taper, MPAA, carry forward
- SDLT with first-time buyer relief and additional property surcharge
- ISA tracking with correct April 6–5 tax year boundaries
- Section 24 mortgage interest credit correctly applied as tax reducer

### TAX-1: Wrong Additional Rate Threshold — £150,000 Instead of £125,140 (CRITICAL)

**File:** `app/Services/Savings/SavingsActionDefinitionService.php:2188-2192`

```php
if ($grossIncome > 150000) {    // WRONG — should be 125,140
    $marginalRate = 0.45;
}
```

This has been incorrect since April 2023 when the threshold changed. Users earning £125k–150k are classified as higher rate (40%) when they should be additional rate (45%), underestimating pension tax relief by 5 percentage points.

**Fix:** Source from `$this->taxConfig->getIncomeTax()` bands.

### TAX-2: Incorrect Dividend Additional Rate Fallback (CRITICAL)

**File:** `app/Services/Investment/DividendTaxCalculator.php:41`

Fallback value is `0.3938` (39.38%) — correct rate is `0.3935` (39.35%).

**Fix:** Change fallback to `0.3935`.

### TAX-3: TaxOptimisationService Uses TaxDefaults Instead of TaxConfigService (HIGH)

**File:** `app/Services/Tax/TaxOptimisationService.php:467-484, 239, 324`

`determineTaxBand()`, `buildISAStrategy()`, and `buildCGTStrategy()` use `TaxDefaults` constants directly despite having TaxConfigService injected.

**Fix:** Replace all TaxDefaults references with TaxConfigService calls.

### TAX-4: TaxActionDefinitionService Uses TaxDefaults (HIGH)

**File:** `app/Services/Tax/TaxActionDefinitionService.php:340-354, 362-377`

Same pattern as TAX-3 — `determineMarginalRate()` and `determineTaxBand()` use constants.

### TAX-5: Hardcoded Dividend Tax Rates (HIGH)

**File:** `app/Services/Tax/TaxOptimisationService.php:240`

`$dividendRate = $taxBand === 'basic' ? 0.0875 : 0.3375` — should source from TaxConfigService.

### TAX-6: Hardcoded CGT Rates (HIGH)

**File:** `app/Services/Tax/TaxOptimisationService.php:239, 324`

CGT rates from `TaxDefaults::CGT_BASIC_RATE` instead of TaxConfigService.

### TAX-7: PSACalculator Uses TaxDefaults (HIGH)

**File:** `app/Services/Savings/PSACalculator.php:69-81`

`determineTaxBand()` uses TaxDefaults despite having TaxConfigService injected.

### TAX-8: Hardcoded Rates in TaxOptimizationAnalyzer (HIGH)

**File:** `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php:309, 325, 331, 418-419, 441-442`

Multiple hardcoded rates: `$excessGains * 0.20`, `$dividendExcess * 0.0875`. All assume basic rate taxpayer status.

### TAX-9: Hardcoded IHT Rate in EstatePlanService (HIGH)

**File:** `app/Services/Plans/EstatePlanService.php:425`

`$ihtCalc['projected_taxable_estate'] * 0.40` — doesn't account for reduced 36% charitable rate.

### TAX-10: Hardcoded IHT/CLT Rates in PersonalizedTrustStrategyService (HIGH)

**File:** `app/Services/Estate/PersonalizedTrustStrategyService.php:172, 176, 209, 263, 294, 344, 397, 467, 478-479`

Extensive hardcoded `0.40` and `0.20` rates throughout trust strategy calculations despite having TaxConfigService injected.

### TAX-11 through TAX-20: Medium Severity Hardcoded Values

| # | File | Issue |
|---|------|-------|
| 11 | `Trust/IHTPeriodicChargeCalculator.php:15-21` | Trust charge rates as PHP constants |
| 12 | `Estate/TrustService.php:156-157, 166-167` | Trust income rates hardcoded with fallbacks |
| 13 | `UKTaxCalculator.php:318-322` | PSA values hardcoded (£1,000 / £500 / £0) |
| 14 | `Investment/DividendTaxCalculator.php:45-46` | PA taper threshold hardcoded as 100,000 |
| 15 | `Traits/HasAiChat.php:871-887` | Fragile TaxConfigService parsing with hardcoded fallbacks |
| 16 | `Estate/ComprehensiveEstatePlanService.php:958, 972` | Annual gift exemption and IHT rate hardcoded |
| 17 | `Estate/GiftingStrategyOptimizer.php:294, 305` | CLT rate hardcoded as 0.20 |
| 18 | `Investment/Rebalancing/TaxAwareRebalancer.php:34-35` | CGT allowance/rate defaults with wrong comment |
| 19 | `Investment/Tax/BedAndISACalculator.php:60, 315, 319, 360` | Multiple hardcoded CGT/dividend rates |
| 20 | `Coordination/HouseholdPlanningService.php:253, 510-513` | IHT and marginal rate hardcoded |

### TAX-21 through TAX-27: Low Severity

| # | File | Issue |
|---|------|-------|
| 21 | `Estate/IHTCalculationService.php:1133` | Charitable rate 0.36 as fallback (acceptable) |
| 22 | `Estate/WillAnalysisService.php:43` | 10% charitable threshold (statutory, unlikely to change) |
| 23 | `Retirement/ContributionOptimizer.php:245-250` | Correct 2025/26 fallback values |
| 24 | `Retirement/RetirementIncomeService.php:954` | Basic rate 0.20 fallback (acceptable) |
| 25 | `Investment/AssetLocation/AssetLocationOptimizer.php:104` | CGT rates correct but should use TaxConfigService |
| 26 | `Investment/AssetLocation/TaxDragCalculator.php:141-178` | Multiple correct rates hardcoded |
| 27 | `Investment/Rebalancing/TaxAwareRebalancer.php:34` | Comment says "12,300 for 2024/25" — was actually 3,000 |

### Missing Tax Coverage

- **No Scottish Income Tax support** — if any users are Scottish taxpayers, rUK rates are applied incorrectly (Scottish starter, intermediate, and top rates differ)
- **Strategy services assume basic rate** — ISA tax savings, Bed & ISA potential, etc. consistently assume basic rate taxpayer, underestimating savings for higher/additional rate taxpayers

---

## 3. Backend Architecture Review

### Architecture Strengths

- Agent/Service/Controller/Model layering consistently applied
- TaxConfigService correctly used in core calculators
- Joint asset single-record pattern correctly implemented
- `CalculatesOwnershipShare` trait used consistently
- Strong middleware stack (auth, rate limiting, CORS, security headers)

### BE-1: Silent Exception Swallowing in EstateAgent (CRITICAL)

**File:** `app/Agents/EstateAgent.php:125-193`

Six consecutive `catch (\Exception $e)` blocks with no logging. IHT calculation failure (line 131) silently returns `$ihtLiability = 0`, producing incorrect estate planning recommendations.

**Fix:** Add `report($e)` or `Log::warning()` in every catch block. Consider re-throwing `FinancialCalculationException` for IHT failures.

### BE-2: AnnualAllowanceChecker Repeated TaxConfig Calls (CRITICAL)

**File:** `app/Services/Retirement/AnnualAllowanceChecker.php:26-71`

Six separate calls to `getPensionAllowances()` within a single `checkAnnualAllowance()` invocation.

**Fix:** Cache result in private property at start of method.

### BE-3: Duplicate Monte Carlo Implementations (CRITICAL)

**Files:** `app/Services/Investment/MonteCarloSimulator.php`, `app/Services/Shared/MonteCarloEngine.php`

Two diverging implementations. `MonteCarloSimulator` uses a custom DB table instead of Laravel Cache, bypassing `php artisan cache:clear`. `MonteCarloEngine` is the cleaner implementation.

**Fix:** Consolidate into single engine. Migrate to `Cache::remember()`.

### BE-4: AuthController Non-Readonly Injection (CRITICAL)

**File:** `app/Http/Controllers/Api/AuthController.php:36-42`

Five injected services declared `private` without `readonly`, inconsistent with project convention.

**Fix:** Add `readonly` to all constructor-injected dependencies.

### BE-5: Dead Code — `min(20, 30)` Always Returns 20 (CRITICAL)

**File:** `app/Services/Estate/IHTStrategyGeneratorService.php:40`

```php
$yearsToProject = min(20, 30); // Always 20, ignores user's age
```

All users get identical gifting strategy projections regardless of age.

**Fix:** Calculate from user's age: `$yearsToProject = min(20, $yearsUntilDeath)`.

### BE-6: DashboardAggregator Returns Financial Health Scores (CRITICAL)

**File:** `app/Services/Dashboard/DashboardAggregator.php:47-107`

`calculateFinancialHealthScore()` produces `composite_score` (0-100) values. If exposed via API, violates Rule 13 (No Scores in UI).

**Fix:** Verify whether exposed via API endpoint. If so, replace with qualitative labels.

### BE-7: Simplified Income Calculation for AA Taper (IMPORTANT)

**File:** `app/Services/Retirement/AnnualAllowanceChecker.php:85-88`

`$thresholdIncome` only uses `current_annual_salary`, missing self-employment, rental, dividend, and interest income. Produces incorrect taper calculations for users with multiple income sources.

**Fix:** Use `ResolvesIncome` trait for total gross income.

### BE-8: EstateController Returns Raw Models (IMPORTANT)

**File:** `app/Http/Controllers/Api/EstateController.php:48-96`

Assets, liabilities, gifts, trusts, and IHT profile returned as raw Eloquent models, exposing all fillable columns. Inconsistent with other controllers that use Resource classes.

**Fix:** Create and use `AssetResource`, `LiabilityResource`, `GiftResource`, `TrustResource`.

### BE-9: ISATracker Writes on Every Read (IMPORTANT)

**File:** `app/Services/Savings/ISATracker.php:75-81`

`getISAAllowanceStatus()` always calls `$tracking->update()` even when values haven't changed. Every GET request triggers an unnecessary write.

**Fix:** Guard update with `$tracking->fill([...])->isDirty()`.

### BE-10: RetirementController Silent Error Handling (IMPORTANT)

**File:** `app/Http/Controllers/Api/RetirementController.php:62-91`

Four service calls wrapped in single try-catch. If any fails, all four values are lost. No `report($e)` call.

**Fix:** Add logging and wrap each service call individually.

### BE-11: PaymentController Order Probe Risk (IMPORTANT)

**File:** `app/Http/Controllers/Api/PaymentController.php:166-174`

Revolut API `getOrder($orderId)` called before verifying the order belongs to the authenticated user. Any user with a valid token can probe arbitrary order UUIDs.

**Fix:** Check `Payment::where('revolut_order_id', $orderId)->where('user_id', $user->id)` before calling Revolut API.

### BE-12: AdminController Redundant Name Field (IMPORTANT)

**File:** `app/Http/Controllers/Api/AdminController.php:170`

Sets `$user->name` directly, bypassing the `getNameAttribute()` accessor. Inconsistent with registration flow.

**Fix:** Remove the `$user->name = ...` line.

### BE-13: MySQL-Specific Raw Queries (IMPORTANT)

**File:** `app/Console/Commands/SendProtectionAlerts.php:81-92`

`DATE_ADD(..., INTERVAL ... YEAR)` is MySQL-specific. Will fail on SQLite in tests.

**Fix:** Use Carbon date math in PHP instead.

### BE-14: Missing strict_types in Test Files (IMPORTANT)

**File:** `tests/Unit/Services/Estate/GiftingStrategyTest.php:1` (and potentially others)

Missing `declare(strict_types=1)` violates project convention.

### BE-15: Non-Readonly Dependencies in Services (IMPORTANT)

**File:** `app/Services/Investment/Analytics/MarkowitzOptimizer.php:16-20`

Three constructor-injected dependencies missing `readonly`. Same pattern in other analytics services.

### BE-16: Testing Coverage Gaps (IMPORTANT)

Missing test coverage for:
- `AnnualAllowanceChecker` tapering with multi-source income
- `EstateAgent.analyze()` silent exception paths
- `WebhookController.handleOrderCompleted()` idempotency
- `PreviewWriteInterceptor` route blocking/exclusion logic

---

## 4. Frontend Quality Review

### FE-1: Scores Displayed in User-Facing UI (CRITICAL)

**Files:**
- `resources/js/components/Investment/PortfolioOverview.vue:138, 174` — "Efficiency Score" and "Diversification Score" with `X/100` values
- `resources/js/components/Investment/TaxFees.vue:28` — "Tax Efficiency Score" `X/100`
- `resources/js/components/Investment/AssetLocationOptimizer.vue:36` — `optimization_score` `X/100`

Directly violates Rule 13 (No Scores in User-Facing UI).

**Fix:** Replace with descriptive text ("Well Diversified", "Concentrated") or concrete metrics.

### FE-2: FinancialHealthScore Component (CRITICAL)

**File:** `resources/js/components/Dashboard/FinancialHealthScore.vue`

Score-driven component using `compositeScore` (0-100). May be dead code (not imported in Dashboard.vue).

**Fix:** Verify usage. Remove if orphaned, refactor if active.

### FE-3: 70+ Hardcoded Hex Values in Style Blocks (CRITICAL)

**Files:**
- `resources/js/views/Settings/AssumptionsSettings.vue` — 30+ hex values including non-palette colours (`#3b82f6`, `#2563eb`)
- `resources/js/views/Settings/PrivacySettings.vue` — 40+ hex values including banned amber (`#fef3c7`, `#f59e0b`)
- `resources/js/components/Goals/GoalsProjectionChart.vue:663-743` — chart tooltip hex values
- `resources/js/components/Retirement/RetirementIncomeTab.vue:1761` — `accent-color: #E83E6D`
- `resources/js/components/Retirement/DBPensionForm.vue:330-335` — `background: #888`
- `resources/js/views/Trusts/TrustsDashboard.vue:828-829` — `#fef2f2`, `#fecaca`
- `resources/js/components/Estate/LpaDetailView.vue:167, 252` — `#fafafa`, `#555`

Violates Rule 12 (No hardcoded hex in style blocks).

**Fix:** Replace with `@apply` directives using palette tokens, or `theme('colors.token.shade')`.

### FE-4: Duplicated Badge CSS (CRITICAL)

**Files:**
- `resources/js/components/NetWorth/InvestmentList.vue:957-984`
- `resources/js/components/NetWorth/InvestmentDetailInline.vue:715-741`

Both define local `.badge-isa`, `.badge-gia`, `.badge-sipp`, `.badge-nsi`, `.badge-bond`, `.badge-vct` duplicating global `app.css` definitions.

**Fix:** Remove scoped definitions. Add `.badge-nsi` and `.badge-vct` to global `app.css` if not present.

### FE-5: Duplicated Scrollbar CSS in 4 Modals (CRITICAL)

**Files:** `PolicyFormModal.vue:849-870`, `SaveAccountModal.vue:1027-1049`, `SaveGoalModal.vue:320-341`, `AllocationComparison.vue:274-291`

All define identical `::-webkit-scrollbar` rules.

**Fix:** Remove scoped CSS. Use global `scrollbar-thin` class.

### FE-6: American Spelling "Optimization" in 6 Components (CRITICAL)

**Files:** `ISAOptimizationStrategy.vue:3`, `AssetLocationOptimizer.vue:152, 290`, `TaxOptimizationRecommendations.vue:3`, `TaxStrategySection.vue:3, 94`, `PortfolioOptimizer.vue:24`, `InvestmentRecommendationsTracker.vue:445`

User-facing headings and tab labels use "Optimization" instead of "Optimisation".

**Fix:** Replace all user-facing instances with British spelling.

### FE-7: 292 Raw console.log Calls (IMPORTANT)

146 Vue files and 12 Vuex store modules use raw `console.*` instead of the `logger` utility. Most critical: `MobileLoginScreen.vue` (8 calls logging auth credentials state) and `netWorth.js` (logging full API error responses containing financial data).

**Fix:** Replace with `logger.info`/`logger.warn`/`logger.error`.

### FE-8: Non-Palette purple-* in General UI (IMPORTANT)

**File:** `resources/js/components/Cash/AccountGroupList.vue:191, 223-284`

Uses `purple-*` for hover states, joint badges, and add-button styling — not an approved context.

**Fix:** Use `violet-*` for hover states, global `.badge-joint` class for badges.

### FE-9: TODO Stubs Presenting False Functionality (IMPORTANT)

| File | Issue |
|------|-------|
| `Retirement/AnnualAllowanceTracker.vue:255-259` | `getHistoricalContributions()` always returns 0, showing £0 for all carry-forward years |
| `Investment/PortfolioOptimization.vue:195-208` | "Create Rebalancing Plan" button shows success toast but does nothing |
| `mobile/layouts/MobileLayout.vue:86` | `milestoneCount()` returns 0, Goals tab always shows zero badge |

**Fix:** Implement API calls or remove misleading UI elements.

### FE-10: error-* Token Used in 43 Files (IMPORTANT)

**File:** `resources/js/components/Common/ConfirmDialog.vue:194` and 43 others

`error-*` is not in the canonical palette token list. While it resolves to the same hex as `raspberry-*`, mixing token names erodes consistency.

**Fix:** Standardise on `raspberry-*` as the canonical danger/error token.

### FE-11: 23 Components Make Direct API Calls (IMPORTANT)

Bypassing the service layer (architecture rule: Component → Service → Controller):
- `LetterToSpouse.vue` — 10 direct `api.*` calls
- `OnboardingWizard.vue` — 11 direct calls
- `PrivacySettings.vue` — 9 direct calls
- `ContributionPlanner.vue`, `AssumptionsSettings.vue` — 2+ each

**Fix:** Consolidate into appropriate service modules.

### FE-12: Banned Amber Colours via Hex (IMPORTANT)

**File:** `resources/js/views/Settings/PrivacySettings.vue:835, 1124-1125`

Uses `#f59e0b` (amber-500) and `#fef3c7` (amber-100) for status indicators.

**Fix:** Replace with `violet-*` tokens per Rule 9.

### FE-13: Index Used as :key in 84 Components (IMPORTANT)

84 components use `:key="index"` in `v-for` loops. Most concerning in editable forms:
- `TaxSettings.vue` — 5 occurrences in editable rate tables
- `ContributionPlanner.vue` — 6 occurrences in editable rows
- `CGTHarvestingOpportunities.vue` — 4 occurrences

**Fix:** Use stable unique identifiers (item `id` or composite key) for all editable lists.

### FE-14: Acronyms in User-Facing Text (IMPORTANT)

- `SaveAccountModal.vue:437` — "S&S ISA" in tooltip title attributes
- `AssetsStep.vue:62` — `formatDCPensionType()` badge output needs verification

**Fix:** Verify all tooltips and badges use spelled-out forms.

### FE-15: Vuex Store Logging (IMPORTANT)

12 store modules use raw `console.error`/`console.warn`. `netWorth.js` logs full API error responses containing financial data. `preview.js` has 11 `console.log` calls serialising full error objects.

---

## 5. Database Review

### Database Strengths

- Well-structured seeder architecture with idempotent `updateOrCreate()`
- All 140 migrations have both `up()` and `down()` methods
- Comprehensive eager loading in `MobileDashboardAggregator`
- Strong indexing on most foreign keys and query patterns
- Joint owner indexes on all relevant tables
- Polymorphic indexes in both orderings on holdings table

### DB-1: Float Precision on Financial Columns (CRITICAL)

**File:** `app/Models/User.php:88-119`

20 expenditure columns stored as `double` in the database. Additionally, the User model casts 6 income columns (which ARE correctly `decimal(15,2)` in DB) as `'float'` in PHP, silently converting precise values to imprecise floats.

**Fix:** Migrate all financial `double` columns to `decimal(15,2)`. Change model casts from `'float'` to `'decimal:2'`.

### DB-2: InvestmentAccount Float Casts (CRITICAL)

**File:** `app/Models/Investment/InvestmentAccount.php:192-290`

30+ financial columns correctly stored as `decimal` in the database but cast as `'float'` in the model. Every Eloquent hydration converts precise values to imprecise floats. This is the most impactful precision issue because investment calculations chain through these values.

**Fix:** Change all monetary casts to `'decimal:2'` (or `'decimal:4'` for rates). Follow `SavingsAccount` model as reference.

### DB-3: N+1 in IHTCalculationService (CRITICAL)

**File:** `app/Services/Estate/IHTCalculationService.php:533, 545, 935, 947, 960, 971`

Six relationship accesses without eager loading for both user and spouse (investmentAccounts, mortgages, liabilities). Generates 6+ additional queries per IHT calculation.

**Fix:** Add `$user->loadMissing(['investmentAccounts', 'mortgages', 'liabilities', 'savingsAccounts', 'properties'])` at calculation entry point.

### DB-4: N+1 in Protection RecommendationEngine (HIGH)

**File:** `app/Services/Protection/RecommendationEngine.php:259-282`

Accesses three policy relationships without verifying eager loading.

**Fix:** Add `loadMissing()` for all policy relationships.

### DB-5: EstateAgent Triple-Queries LifeInsurancePolicy (HIGH)

**File:** `app/Agents/EstateAgent.php:99-108, 210`

Three separate queries for the same user's life insurance policies.

**Fix:** Load once and filter in-memory.

### DB-6: Missing SoftDeletes on Key Models (HIGH)

| Model | Table | Risk |
|-------|-------|------|
| `Trust` | `trusts` | IHT calculation data permanently lost |
| `IHTProfile` | `iht_profiles` | One-per-user config permanently lost |
| `FamilyMember` | `family_members` | Breaks beneficiary FK references |
| `ProtectionProfile` | `protection_profiles` | Needs assessment lost |
| `StatePension` | `state_pensions` | Forecast data lost |

**Fix:** Add `SoftDeletes` trait and migration for each table.

### DB-7: Missing Unique Constraints on HasOne Tables (HIGH)

| Table | Column |
|-------|--------|
| `iht_profiles` | `user_id` |
| `retirement_profiles` | `user_id` |
| `risk_profiles` | `user_id` |
| `state_pensions` | `user_id` |
| `letters_to_spouse` | `user_id` |
| `expenditure_profiles` | `user_id` |

Without unique constraints, application bugs could silently create duplicate records.

**Fix:** Add unique indexes via migration.

### DB-8: Missing FK on bequests.asset_id (HIGH)

No foreign key constraint on `bequests.asset_id → assets.id`. Risk of orphaned records.

**Fix:** `ALTER TABLE bequests ADD CONSTRAINT bequests_asset_id_foreign FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL`

### DB-9: Duplicate Indexes (MODERATE)

| Table | Redundant Index | Equivalent |
|-------|----------------|------------|
| `monte_carlo_cache` | `cache_key_index` | `cache_key_unique` |
| `occupation_codes` | `title_index` | `title_fulltext` |
| `protection_profiles` | `user_id_index` | `user_id_unique` |
| `spouse_permissions` | Duplicate unique index | |
| `tax_configurations` | `tax_year_index` | `tax_year_unique` |

**Fix:** Drop the 5 redundant indexes.

### DB-10: Missing Indexes on Queried _id Columns (MODERATE)

- `bequests.asset_id`
- `life_event_allocations.account_id`
- `plan_action_funding_selections.funding_source_id`

### DB-11: Users Table Extremely Wide (~80+ Columns) (MODERATE)

20 expenditure columns should be in `expenditure_profiles` table (which already exists).

### DB-12: RetirementAgent Double-Loads DCPensions (MODERATE)

**File:** `app/Agents/RetirementAgent.php:223, 334`

`DCPension::where('user_id', $userId)->get()` called twice in separate methods.

### DB-13: Low-Cardinality Single-Column Indexes (LOW)

Boolean/enum columns with single-column indexes (e.g., `is_isa`, `ownership_type`) have insufficient selectivity. Harmless at current scale.

### DB-14: Subscription Plan Integer Pricing (LOW)

`monthly_price` and `yearly_price` stored as `int`. Should be documented whether this is pence or pounds.

### DB-15: Data Migration in Schema Migration (LOW)

`2026_02_20_120000_assign_roles_to_existing_users.php` performs data migration in a schema migration.

---

## Remediation Priority Matrix

### Sprint 1 — Critical Fixes (Estimated: 3-5 days)

| # | Finding | Effort |
|---|---------|--------|
| 1 | DB-1, DB-2: Fix float casts to decimal:2 on User + InvestmentAccount models | 2 hours |
| 2 | TAX-1: Fix £150k → £125,140 threshold in SavingsActionDefinitionService | 30 min |
| 3 | TAX-2: Fix dividend additional rate 0.3938 → 0.3935 | 15 min |
| 4 | SEC-1: Add user_id check to Monte Carlo results | 1 hour |
| 5 | SEC-2: Replace `!==` with `hash_equals()` in AgentTokenAuth | 15 min |
| 6 | BE-1: Add logging to EstateAgent catch blocks | 1 hour |
| 7 | BE-5: Fix `min(20, 30)` dead code in IHTStrategyGeneratorService | 30 min |
| 8 | FE-1: Remove scores from PortfolioOverview, TaxFees, AssetLocationOptimizer | 2 hours |
| 9 | DB-3: Add eager loading to IHTCalculationService | 1 hour |

### Sprint 2 — High Priority (Estimated: 5-8 days)

| # | Finding | Effort |
|---|---------|--------|
| 10 | TAX-3 through TAX-10: Replace TaxDefaults/hardcoded values with TaxConfigService | 1 day |
| 11 | SEC-3, SEC-4, SEC-9: Create UserResource, AdminUserResource for auth responses | 1 day |
| 12 | FE-3: Replace hardcoded hex in AssumptionsSettings + PrivacySettings | 1 day |
| 13 | FE-4, FE-5: Remove duplicated badge/scrollbar CSS | 2 hours |
| 14 | FE-6: Fix American → British spelling | 1 hour |
| 15 | FE-7: Replace console.log with logger (priority: MobileLoginScreen, netWorth.js) | 4 hours |
| 16 | DB-6: Add SoftDeletes to 5 models | 2 hours |
| 17 | DB-7: Add unique constraints on HasOne tables | 1 hour |
| 18 | BE-3: Consolidate Monte Carlo implementations | 4 hours |
| 19 | BE-11: Fix PaymentController order probe | 30 min |
| 20 | DB-4, DB-5: Fix N+1 in Protection + Estate agents | 2 hours |

### Sprint 3 — Medium Priority (Estimated: 3-5 days)

| # | Finding | Effort |
|---|---------|--------|
| 21 | TAX-11 through TAX-20: Replace remaining hardcoded fallbacks | 1 day |
| 22 | SEC-5 through SEC-10: Tighten preview rate limits, fix MFA binding, etc. | 1 day |
| 23 | FE-9: Fix false-positive success notifications | 2 hours |
| 24 | FE-10: Standardise error-* → raspberry-* | 2 hours |
| 25 | FE-11: Move direct API calls to service layer | 4 hours |
| 26 | FE-13: Fix index-as-key in editable forms (highest-risk 10 files) | 2 hours |
| 27 | BE-7: Implement multi-source income for AA taper | 4 hours |
| 28 | BE-8: Create Estate module Resource classes | 2 hours |
| 29 | DB-9, DB-10: Drop duplicate indexes, add missing indexes | 1 hour |

### Backlog — Low Priority

| # | Finding | Effort |
|---|---------|--------|
| 30 | TAX-21 through TAX-27: Acceptable fallbacks | As encountered |
| 31 | SEC-11 through SEC-16: Minor security hardening | 1 day |
| 32 | DB-11: Users table refactoring | Major migration |
| 33 | FE-12, FE-14, FE-15: Palette and spelling cleanup | 2 hours |
| 34 | BE-9, BE-10, BE-13, BE-14: ISA write guard, error handling, MySQL compat | 1 day |
| 35 | Scottish Income Tax support | Feature work |

---

## Appendix: Files Referenced

### Most-Touched Files (Appearing in 3+ Findings)

| File | Findings |
|------|----------|
| `app/Services/Tax/TaxOptimisationService.php` | TAX-3, TAX-5, TAX-6 |
| `app/Services/Estate/PersonalizedTrustStrategyService.php` | TAX-10 (9 locations) |
| `app/Http/Controllers/Api/AuthController.php` | SEC-3, SEC-9, BE-4 |
| `app/Agents/EstateAgent.php` | BE-1, DB-5 |
| `app/Services/Retirement/AnnualAllowanceChecker.php` | BE-2, BE-7 |
| `app/Models/Investment/InvestmentAccount.php` | DB-2 (30+ casts) |
| `resources/js/views/Settings/PrivacySettings.vue` | FE-3, FE-12 |
| `resources/js/views/Settings/AssumptionsSettings.vue` | FE-3 |

---

*Report generated by Claude Opus 4.6 on 18 March 2026. This review covers the codebase at commit HEAD on main branch.*
