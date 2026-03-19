# Full Codebase Review -- Fynla v0.9.2

**Date:** 19 March 2026
**Reviewer:** Senior Code Reviewer (Claude Opus 4.6)
**Branch:** `logicFix`
**Scope:** Complete codebase review covering security, tax compliance, code quality, frontend, database, API, and testing

---

## Executive Summary

Fynla is a well-architected UK financial planning application with strong security foundations. The codebase demonstrates consistent patterns, proper separation of concerns, and thoughtful authentication/authorisation mechanisms. The TaxConfigService centralisation is well-implemented, and the middleware stack provides good defence-in-depth.

However, this review identified several issues requiring attention. The most critical are: (1) a user data exposure via the `getUserById` endpoint returning a raw Eloquent model instead of a UserResource, (2) several instances of hardcoded tax rates in services that should use TaxConfigService, (3) the advisor `enterClient` endpoint missing client assignment verification before `findOrFail`, and (4) frontend score displays in the Investment module that violate the project's no-scores-in-UI rule.

**Summary of findings:**
- **Critical:** 3 issues (must fix immediately)
- **High Priority:** 7 issues (should fix soon)
- **Medium Priority:** 10 issues (should fix in upcoming sprints)
- **Low Priority / Tech Debt:** 8 items (nice to have)

---

## Critical Issues (Must Fix)

### C1. User Data Exposure in `getUserById` Endpoint

**File:** `/app/Http/Controllers/Api/UserProfileController.php` (line 255-262)

The `getUserById` method returns the raw Eloquent `$user` model directly in the JSON response instead of using `UserResource`. While the User model has `$hidden` attributes set (password, remember_token, mfa_secret, mfa_recovery_codes, etc.), this pattern is fragile. Any future column added to the users table would be automatically exposed. All other auth endpoints correctly use `new UserResource($user)`.

The same issue exists at line 182: `'user' => $user->fresh()` -- also returns a raw model after updating expenditure.

**Risk:** Sensitive data leakage if new columns are added without updating `$hidden`. The `$hidden` property currently protects critical fields, but this relies on developers remembering to add every sensitive column -- a fragile contract.

**Recommendation:** Replace both occurrences with `new UserResource($user)` to match the pattern used in `AuthController::buildAuthSuccessResponse()` and `AuthController::user()`.

---

### C2. Advisor Impersonation -- Missing Client Assignment Check in `enterClient`

**File:** `/app/Http/Controllers/Api/AdvisorController.php` (line 118-130)

The `enterClient` method calls `User::findOrFail($id)` before the `AdvisorImpersonationService` validates that the client is assigned to the advisor. While the service does perform this check via `abort_unless`, the controller reveals whether a user ID exists (404 vs 403) before authorisation is verified. This is an information disclosure vulnerability -- an advisor could enumerate valid user IDs.

```php
public function enterClient(Request $request, int $id): JsonResponse
{
    $client = User::findOrFail($id);  // Reveals existence before auth check
    $result = $this->impersonationService->enterClientProfile($request->user(), $client);
```

**Risk:** User ID enumeration by advisor-role users. The `clientModuleStatus` method at line 99-116 correctly checks client assignment FIRST.

**Recommendation:** Check client assignment before `findOrFail`, consistent with `clientModuleStatus`:
```php
$advisor = $request->user();
$advisor->advisorClients()->where('client_id', $id)->where('status', 'active')->firstOrFail();
$client = User::findOrFail($id);
```

---

### C3. Hardcoded Tax Rates in AssetLocationController Defaults

**File:** `/app/Http/Controllers/Api/Investment/AssetLocationController.php` (lines 280-294)

The `buildDefaultContext` method hardcodes multiple tax-sensitive values without any TaxConfigService fallback:

```php
'isa_allowance_remaining' => 20000,         // Hardcoded ISA allowance
'expected_return' => 0.06,                   // Hardcoded growth rate
'years_to_retirement' => max(0, 67 - $age), // Hardcoded retirement age
'expected_withdrawal_tax_rate' => 0.20,      // Hardcoded basic rate
```

The `isa_allowance_remaining` is also hardcoded to 20000 on line 286, and the ISA validation rule is hardcoded to `max:20000` on line 43. These should reference `TaxConfigService::getISAAllowances()`.

**Risk:** If ISA allowances change (or any of these values), this controller will use stale figures for asset location optimisation, producing incorrect recommendations.

**Recommendation:** Use TaxConfigService for all tax-dependent defaults. The controller already injects `$this->taxConfig` -- use it consistently.

---

## High Priority Issues (Should Fix)

### H1. Hardcoded Income Tax Rates Across Multiple Services

Several services hardcode income tax rate mappings instead of deriving them from TaxConfigService. While these match current 2025/26 rates, they will silently become incorrect if rates change.

**Affected files and locations:**
- `/app/Services/Tax/TaxOptimisationService.php` (line 282-283): `'basic' => 0.20, 'higher' => 0.40`
- `/app/Services/Investment/Recommendation/ContributionWaterfallService.php` (lines 319-320, 327-328, 733)
- `/app/Services/UKTaxCalculator.php` (lines 519-520): `'basic' => 0.20, 'higher' => 0.40`
- `/app/Services/Estate/TrustService.php` (line 167): `'standard_rate' => 0.20`
- `/app/Services/Investment/AssetLocation/AssetLocationOptimizer.php` (line 129): `0.20` default

While `TaxDefaults.php` provides centralised fallback constants (which is good), some of these services use inline literals instead of TaxDefaults constants. All should use TaxConfigService as primary, TaxDefaults as fallback.

**Recommendation:** Refactor to pattern:
```php
$reliefRate = match ($taxBand) {
    'basic' => (float) ($this->taxConfig->get('income_tax.bands.0.rate', TaxDefaults::BASIC_RATE)),
    'higher' => (float) ($this->taxConfig->get('income_tax.bands.1.rate', TaxDefaults::HIGHER_RATE)),
    ...
};
```

---

### H2. Hardcoded IHT Text in Frontend (UKTaxesAllowancesCard)

**File:** `/resources/js/components/Dashboard/UKTaxesAllowancesCard.vue` (lines 261, 568, 577, 616-617, 627)

Multiple hardcoded tax rate strings appear in the UI:
- Line 261: `40%` in an HTML table cell
- Line 568: `40% (Higher Rate)` income tax text
- Lines 616-627: IHT calculation examples with hardcoded `40%` and `36%`

While this is an informational/educational component (not a calculation), the hardcoded text will become inaccurate if rates change. The component already has access to `taxConfig` data for the dynamic values above.

**Recommendation:** Use the `taxConfig` object already available in the component for all rate displays, or extract to a computed property.

---

### H3. Custom @keyframes in Scoped Styles (CSS Governance Violation)

**Files:** 16 Vue components define custom `@keyframes` animations in `<style scoped>` blocks. Per CLAUDE.md rule 12, global animation classes should be used from `app.css`.

**Affected files:**
- `GuidanceTooltip.vue` (guidance-pulse)
- `TypingIndicator.vue` (typing-bounce)
- `NetWorthOverviewCard.vue` (loading)
- `LearningCentre.vue` (contentFadeIn)
- `MilestoneOverlay.vue` (confetti-fall)
- `SecurityPage.vue` (sectionFadeIn)
- `VoiceInputButton.vue` (voice-pulse, voice-wave)
- `CalculatorsPage.vue` (calcFadeIn)
- `PensionList.vue`, `PropertyList.vue`, `InvestmentList.vue` (slideIn)
- `SavingsAccountDetailInline.vue` (fadeIn)
- `PortfolioOptimization.vue` (slideIn)
- `OnboardingWizard.vue` (nodePulse)
- `JourneyMap.vue` (nodeGlow)

**Recommendation:** Consolidate common animations (fadeIn, slideIn) into `app.css` global classes. Unique animations (confetti-fall, voice-pulse) can remain scoped.

---

### H4. Scores Displayed in Investment UI

**Files:**
- `/resources/js/views/Investment/PortfolioStrategyPanel.vue` (lines 66-71, 401-405): Displays `tax_efficiency_score` with colour-coded thresholds
- `/resources/js/views/Investment/AccountPerformancePanel.vue` (lines 64-68, 774): Displays `drift_score`

Per CLAUDE.md rule 13: "Scores must never appear in user-facing UI. This includes score badges, score metric cards, score-formatted values."

**Recommendation:** Replace score displays with descriptive text. For tax efficiency, use text like "Well-optimised" / "Room for improvement" / "Review recommended". For drift, show the actual percentage deviation with contextual guidance.

---

### H5. console.log Statements in Production Components

50 occurrences of `console.log()` across 30 Vue component files. While `logger.js` utility exists for development-only structured logging, many components use raw `console.log`.

**High-priority files** (multiple occurrences):
- `AssetsLiabilities.vue` (4 occurrences)
- `CashOverview.vue` (4 occurrences)
- `GiftingStrategy.vue` (4 occurrences)
- `SavingsGoals.vue` (3 occurrences)
- `LifeEventAllocationTab.vue` (3 occurrences)

**Recommendation:** Replace all `console.log` with `logger.debug()` from `@/utils/logger` which is automatically silenced in production. Or remove debug logs entirely where they were added during development.

---

### H6. EstateAgent Hardcoded Default Trust Value

**File:** `/app/Agents/EstateAgent.php` (line 1523)

```php
$trustValue = $parameters['trust_value'] ?? 325000;
```

The fallback value of 325,000 happens to match the current NRB but is not derived from TaxConfigService. This method is `buildTrustScenario()` which is used for what-if scenarios. If the NRB changes, the default trust scenario value would be stale.

**Recommendation:** Use `$this->taxConfig->getInheritanceTax()['nil_rate_band']` as the fallback.

---

### H7. Missing Rate Limiting on Several Admin Write Endpoints

**File:** `/routes/api.php` (lines 1046-1053)

The `admin/retirement-actions` group lacks throttle middleware, unlike the equivalent `admin/investment-actions` (line 1056) and `admin/protection-actions` (line 1066) which have `throttle:30,1`. This is inconsistent.

Additionally, the `admin/action-definitions/{module}` group at line 1076 correctly has throttle, but the `admin/decision-matrix/{module}` GET at line 1088 does not.

**Recommendation:** Add `throttle:30,1` to the retirement-actions group for consistency. Evaluate whether the decision-matrix endpoint needs throttling (it is read-only, so lower priority).

---

## Medium Priority Issues (Should Fix)

### M1. WhereRaw with Column Name Interpolation (SQL Injection Vector)

**File:** `/app/Agents/CoordinatingAgent.php` (line 1320)

```php
->whereRaw('LOWER('.$nameField.') = ?', [strtolower($nameValue)])
```

While the `$nameField` variable is validated against an allowlist of column names (`$allowedColumns` on line 1314-1316), this pattern of interpolating column names into raw SQL is a potential maintenance risk. If the validation is ever loosened or bypassed, this becomes an SQL injection vector.

**Recommendation:** Use `whereRaw('LOWER(?) = ?')` is not valid for column names, but consider using `->where(DB::raw("LOWER({$nameField})"), strtolower($nameValue))` or a safer abstraction. The current allowlist is adequate protection, but the pattern should be flagged for caution.

---

### M2. Unbounded Queries in EstateController Index

**File:** `/app/Http/Controllers/Api/EstateController.php` (lines 52-59)

```php
$assets = Asset::where('user_id', $user->id)->limit(100)->get();
$liabilities = Liability::where('user_id', $user->id)->limit(100)->get();
$gifts = Gift::where('user_id', $user->id)->limit(100)->get();
$trusts = Trust::where('user_id', $user->id)->limit(100)->get();
$investmentAccounts = InvestmentAccount::where('user_id', $user->id)->limit(100)->get();
```

Five sequential queries for a single index endpoint. While each has a limit(100) (good), this is an N+1 opportunity. These queries could be parallelised or combined into fewer round-trips.

Additionally, the `IHTProfile` query on line 56 does not have `limit()` (it uses `first()` which is fine for HasOne, but there's no unique constraint in code to guarantee only one exists).

**Recommendation:** Consider eager loading these through User model relationships: `$user->load(['estateAssets', 'estateLiabilities', ...])` in a single query. Or use a dedicated repository/service.

---

### M3. Trust IHTPeriodicChargeCalculator Uses Hardcoded Constants

**File:** `/app/Services/Trust/IHTPeriodicChargeCalculator.php` (lines 14-18)

```php
private const IHT_RATE = 0.40;
private const PERIODIC_CHARGE_RATE = 0.06;
private const ENTRY_CHARGE_MAX = 0.20;
private const EXIT_CHARGE_MAX = 0.06;
```

These are documented as "fallback defaults if TaxConfigService unavailable" and the `getTrustChargeRates()` method correctly uses TaxConfigService first. However, these fallback values are duplicated from TaxDefaults. They should reference `TaxDefaults::IHT_RATE` etc. for single-source-of-truth.

**Recommendation:** Replace class constants with `TaxDefaults` references.

---

### M4. GoalsController Index Lacks Input Validation on Filter Parameters

**File:** `/app/Http/Controllers/Api/GoalsController.php` (lines 55-67)

```php
if ($request->has('module')) {
    $query->where('assigned_module', $request->input('module'));
}
if ($request->has('status')) {
    $query->where('status', $request->input('status'));
}
if ($request->has('priority')) {
    $query->where('priority', $request->input('priority'));
}
```

These filter values are taken directly from the request without validation. While Eloquent parameterises the values (preventing SQL injection), there is no validation that the values are valid enum values. An attacker could pass arbitrary strings, and while the query would simply return no results, the lack of validation is inconsistent with the project's pattern of strict input validation.

**Recommendation:** Add validation: `$request->validate(['module' => 'nullable|in:savings,investment,...', 'status' => 'nullable|in:active,completed,...', 'priority' => 'nullable|in:critical,high,medium,low'])`.

---

### M5. Inconsistent `v-html` Usage -- AiMessageContent Escapes Properly, Others Use Sanitiser

Three components use `v-html`:
1. `AiMessageContent.vue` -- Properly escapes HTML first via `escapeHtml()` then applies markdown formatting. **Good.**
2. `LpaDetailView.vue` -- Uses `sanitizeHtml()` from `@/utils/sanitizeHtml`. **Good.**
3. `WillBuilderReviewStep.vue` -- Uses same `sanitizeHtml()`. **Good.**

All three handle XSS correctly. However, `AiMessageContent.vue` uses a custom `escapeHtml` method while the other two use a shared utility. Consistency would be improved by using the same sanitisation utility everywhere.

**Recommendation:** No security action needed -- all three are safe. For consistency, consider migrating `AiMessageContent.vue` to use the shared `sanitizeHtml` utility.

---

### M6. Duplicate ISA Allowance Hardcoded in Validation Rules

**File:** `/app/Http/Requests/StoreInvestmentAccountRequest.php` (line 50)
**File:** `/app/Http/Requests/UpdateInvestmentAccountRequest.php` (line 56)

```php
'isa_subscription_current_year' => 'nullable|numeric|min:0|max:20000',
```

The `max:20000` is hardcoded. `ValidationLimits` class has a method that retrieves this from TaxConfigService (line 91: `return (float) ($isaConfig['annual_allowance'] ?? 20000)`), but these Form Request classes use the raw number.

**Recommendation:** Use `ValidationLimits::isaAllowanceMax()` or similar method instead of hardcoded `max:20000`.

---

### M7. Equity Release Scenario Default Value Not From Config

**File:** `/app/Agents/EstateAgent.php` (line 1503)

```php
$equityRelease = $parameters['equity_release'] ?? 200000;
```

The default equity release value of 200,000 is a planning assumption, not a tax value. However, it should still be configurable rather than hardcoded, ideally from UserAssumptions or a TaxConfigService planning section.

**Recommendation:** Move to `EstateDefaults` constant or user assumptions.

---

### M8. DashboardAggregator ISA Allowance Fallback

**File:** `/app/Services/Dashboard/DashboardAggregator.php` (line 267)

```php
$isaAllowance = (float) ($data['isa_allowance']['total_allowance'] ?? 20000);
```

This is a fallback when the data does not contain the ISA allowance. The primary source correctly comes from TaxConfigService. The fallback should reference `TaxDefaults::ISA_ALLOWANCE`.

**Recommendation:** Use `TaxDefaults::ISA_ALLOWANCE` instead of bare `20000`.

---

### M9. CheckSubscription Middleware Path Matching Could Be Tighter

**File:** `/app/Http/Middleware/CheckSubscription.php` (lines 16-35)

The excluded paths use `str_starts_with` prefix matching, which means `api/payment/anything-at-all` would match. While all these prefixes lead to known route groups, this is a looser pattern than exact route matching.

**Recommendation:** This is acceptable given the route structure, but document the rationale. If new routes are added under these prefixes that should NOT be excluded (unlikely but possible), the prefix matching could bypass subscription checks.

---

### M10. Missing Index on `is_preview_user` Column

The PreviewWriteInterceptor middleware resolves users from tokens and checks `is_preview_user`. If the users table grows large, queries filtering by `is_preview_user` may benefit from an index. Currently this is looked up after token resolution (which is indexed), so the actual risk is low.

**Recommendation:** Low urgency. Consider adding an index if the users table exceeds 10,000 rows.

---

## Low Priority / Tech Debt

### L1. TaxDefaults Constants Not Comprehensive

`/app/Constants/TaxDefaults.php` provides fallback constants for IHT, ISA, pension, income tax, and CGT. However, it does not include:
- Savings-specific values (FSCS limit, Premium Bond limit)
- Benefits values (SSP rates, Child Benefit amounts)
- Stamp Duty thresholds

Services that need these values fall back to inline literals (e.g., `?? 20000`). Expanding TaxDefaults to cover all categories would improve consistency.

---

### L2. Inconsistent Error Handling in Mail Sending

In `AuthController`, failed email sends are caught and logged but execution continues silently:
```php
} catch (\Exception $e) {
    \Log::error('Failed to send verification email', [...]);
}
```

This is acceptable for login (user can resend), but for registration (lines 92-98), the user gets a "check your email" response even though the email may not have been sent. The user has no way to know the email failed.

**Recommendation:** Consider returning a warning flag (e.g., `email_sent: false`) so the frontend can prompt the user to resend immediately.

---

### L3. EstateAgent Hardcoded IHT Rate Strings in Narratives

**File:** `/app/Agents/EstateAgent.php` (lines 546, 594-596, 610, 869, 878, 1010, 1067, 1085, 1089, 1101)

Multiple narrative strings contain hardcoded "40%", "36%", "20%" IHT rate references. While these are in user-facing explanation text rather than calculations, they would become misleading if rates changed. The calculations themselves correctly use TaxConfigService.

**Recommendation:** Generate rate strings dynamically from the same TaxConfigService values used in calculations.

---

### L4. DB::raw Usage in Console Commands

Several console commands use `DB::raw()` for COALESCE and DATE_ADD operations:
- `SendProtectionAlerts.php` (lines 46, 85, 140, 177)
- `SendPolicyRenewalReminders.php` (lines 30, 56, 61-62)
- `VerifyDataMigration.php` (lines 271, 284)

These use parameterised values and are safe from injection. However, they bypass Eloquent scoping, meaning user isolation must be manually ensured. The actual queries do filter by `user_id`.

**Recommendation:** No action needed -- these are internal commands. Document the raw SQL justification.

---

### L5. Missing Feature Tests for Payment Endpoints

There are no feature tests in `/tests/Feature/` for the PaymentController or WebhookController. These handle real monetary transactions via Revolut.

**Untested paths:**
- `POST /api/payment/create-order`
- `POST /api/payment/confirm`
- `POST /api/payment/cancel-subscription`
- `POST /api/payment/delete-all-data`
- `POST /api/webhooks/revolut`

**Recommendation:** Create feature tests with mocked RevolutService to verify order creation, confirmation flow, webhook signature verification, and subscription state transitions.

---

### L6. Missing Feature Tests for AI Chat Endpoints

The `AiChatController` has no feature tests. While the AI integration is complex, basic CRUD tests (create conversation, list conversations, delete conversation) and authorisation tests (user can only access their own conversations) should exist.

---

### L7. Test Coverage Summary

**Current test counts:**
- Unit tests: 95 test files
- Feature tests: 63 test files
- Architecture tests: referenced in Pest config

**Well-tested areas:** Authentication (login, logout, registration, MFA), GDPR (consent, export, erasure), Estate planning (IHT, trusts, LPA, wills), Investment (portfolio optimisation, rebalancing, scenarios), Coordination (household planning, recommendations), Admin (RBAC, backup)

**Under-tested areas:**
- Payment/billing (0 tests)
- AI chat (0 tests)
- Document upload/extraction (0 tests)
- Savings controller (1 feature test)
- Mobile dashboard (4 tests, but missing offline/edge cases)

---

### L8. GoalsController `index` Passes Unvalidated `module` Filter to ORM

Addressed in M4 above, but the broader pattern applies to several controllers that accept query parameters for filtering. A project-wide audit of unvalidated filter parameters would be beneficial.

---

## Files Examined

### Backend (PHP)
- `/app/Http/Controllers/Api/AuthController.php` -- Authentication flow
- `/app/Http/Controllers/Api/UserProfileController.php` -- User data endpoints
- `/app/Http/Controllers/Api/AdminController.php` -- Admin panel
- `/app/Http/Controllers/Api/AdvisorController.php` -- Advisor impersonation
- `/app/Http/Controllers/Api/GoalsController.php` -- Goals CRUD
- `/app/Http/Controllers/Api/PropertyController.php` -- Property management
- `/app/Http/Controllers/Api/EstateController.php` -- Estate planning
- `/app/Http/Controllers/Api/PaymentController.php` -- Payment processing
- `/app/Http/Controllers/Api/WebhookController.php` -- Revolut webhooks
- `/app/Http/Controllers/Api/GDPRController.php` -- Data privacy
- `/app/Http/Controllers/Api/MFAController.php` -- Multi-factor auth
- `/app/Http/Controllers/Api/Investment/AssetLocationController.php` -- Asset location
- `/app/Http/Controllers/Api/Estate/TrustController.php` -- Trust management
- `/app/Http/Controllers/Api/Estate/GiftingController.php` -- Gifting strategies
- `/app/Http/Middleware/SanitizeInput.php` -- XSS protection
- `/app/Http/Middleware/SecurityHeaders.php` -- CSP and headers
- `/app/Http/Middleware/PreviewWriteInterceptor.php` -- Preview mode
- `/app/Http/Middleware/EnsureMFAVerified.php` -- MFA enforcement
- `/app/Http/Middleware/CheckSubscription.php` -- Subscription gating
- `/app/Http/Middleware/AgentTokenAuth.php` -- Internal API auth
- `/app/Http/Middleware/AdvisorImpersonationMiddleware.php` -- Advisor context
- `/app/Http/Resources/UserResource.php` -- User serialisation
- `/app/Services/TaxConfigService.php` -- Centralised tax config
- `/app/Services/UKTaxCalculator.php` -- Tax calculation engine
- `/app/Services/Tax/TaxOptimisationService.php` -- Tax strategies
- `/app/Services/Trust/IHTPeriodicChargeCalculator.php` -- Trust charges
- `/app/Services/Investment/Recommendation/ContributionWaterfallService.php` -- Investment waterfall
- `/app/Services/Advisor/AdvisorImpersonationService.php` -- Impersonation logic
- `/app/Services/Dashboard/DashboardAggregator.php` -- Dashboard data
- `/app/Agents/EstateAgent.php` -- Estate planning agent
- `/app/Agents/CoordinatingAgent.php` -- Cross-module coordination
- `/app/Constants/TaxDefaults.php` -- Tax fallback constants
- `/app/Constants/EstateDefaults.php` -- Estate planning constants
- `/app/Models/User.php` -- User model (mass assignment)
- `/app/Models/Goal.php` -- Goal model
- `/routes/api.php` -- All API routes

### Frontend (Vue.js)
- Component files searched for design system violations
- Console.log usage audited across all .vue files
- `v-html` usage audited (3 files, all safe)
- NaN handling reviewed across 30+ components
- Custom keyframes reviewed (16 components)
- Score displays identified (2 components)

### Tests
- 95 unit test files reviewed
- 63 feature test files reviewed
- Coverage gaps identified for payment, AI chat, and document modules

---

## Recommendations

### Immediate Actions (this sprint)
1. Fix C1: Replace raw User model returns with UserResource in `UserProfileController`
2. Fix C2: Add client assignment check before `findOrFail` in `AdvisorController::enterClient`
3. Fix C3: Replace hardcoded ISA/tax values in `AssetLocationController` with TaxConfigService

### Short-Term (next 2 sprints)
4. Fix H1: Refactor hardcoded income tax rates across 5 services to use TaxConfigService
5. Fix H4: Replace score displays in Investment PortfolioStrategyPanel and AccountPerformancePanel
6. Fix H5: Replace all `console.log` with `logger.debug()` or remove
7. Fix H7: Add missing throttle middleware to retirement-actions admin routes
8. Write feature tests for Payment and Webhook controllers (L5)

### Medium-Term (next quarter)
9. Fix M1-M10: Address medium-priority items systematically
10. Expand TaxDefaults constants (L1) for comprehensive fallback coverage
11. Consolidate duplicate `@keyframes` animations into global CSS (H3)
12. Write feature tests for AI Chat and Document Upload endpoints (L6)

### Ongoing Practices
- Run `./vendor/bin/pint` before every commit (enforced by CI)
- Review any new `DB::raw()` or `whereRaw()` usage for injection safety
- Ensure all new API endpoints returning User data use UserResource
- Verify new admin routes include appropriate throttle middleware
- Check TaxConfigService usage when adding any tax-related calculation

---

## Positive Observations

The codebase has many strong qualities worth acknowledging:

1. **Authentication security is excellent.** The login flow with email verification, MFA support, account lockout with escalating delays, IP-based rate limiting, and challenge tokens is well-implemented and thorough.

2. **The PreviewWriteInterceptor is thoughtfully designed.** It handles edge cases (calculation endpoints, auth routes, wildcard patterns) and properly filters sensitive fields from fake responses.

3. **Consistent API response format.** Every controller returns `{success, message, data}` with appropriate HTTP status codes.

4. **TaxConfigService is well-architected.** Request-scoped singleton with comprehensive accessor methods for every tax category. The `TaxDefaults` constants provide genuine resilience.

5. **Security headers are comprehensive.** CSP policy covers Revolut SDK, Plausible analytics, Capacitor mobile, and Vite HMR without being overly permissive. The TODO for nonce-based CSP shows forward planning.

6. **GDPR compliance is thorough.** Data export, erasure, consent management with versioning, and audit logging are all implemented.

7. **Joint ownership pattern is consistent.** `HasJointOwnership` trait with `forUserOrJoint()` scope is used correctly across models.

8. **Design system compliance is strong.** No amber/orange colours found. No `primary-*`/`secondary-*` legacy tokens. No hardcoded hex in Vue components. No `gray-*` tokens. The previous code review's recommendations were thoroughly implemented.

9. **SQL injection protection is strong.** All raw SQL uses parameterised queries or validated allowlists. No unparameterised user input in queries.

10. **Webhook signature verification is properly implemented.** The Revolut webhook handler verifies HMAC signatures before processing, and the endpoint is rate-limited.

---

*End of review.*
