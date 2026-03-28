# Fynla Full Codebase Review — 28 March 2026

**Scope:** 582 Vue components, 214 PHP services, 89 controllers, 89 models, 31 Vuex stores
**Reviewers:** 4 parallel agents (Backend, Frontend, Database/Security, Architecture)
**Date:** 28 March 2026

---

## Executive Summary

| Severity | Count |
|----------|-------|
| CRITICAL | 12 |
| HIGH | 12 |
| MEDIUM | 11 |
| LOW | 8 |
| **Total** | **43** |

**Top concerns:**
1. **Security** — NI number exposed in API, admin seeded as preview user in production, MFA middleware never applied to routes, mass-assignable sensitive fields
2. **Tax compliance** — Hardcoded tax thresholds in 6+ files bypassing TaxConfigService
3. **Design system** — 20+ files with hardcoded hex, 9 files with off-palette spinner colors, banned amber/orange color in use
4. **Convention** — Local currency formatters in 9 files bypassing currencyMixin, console.log in production code
5. **Architecture** — God classes (3,105 lines max), EstateController bypasses agent pattern

---

## SECTION 1: SECURITY (14 issues)

### CRITICAL

#### SEC-01: National Insurance Number Exposed in API Response
- **File:** `app/Services/UserProfile/UserProfileService.php:58,607`
- **Category:** Sensitive Data Exposure
- **Detail:** `getCompleteProfile()` returns `national_insurance_number` in the profile payload. The `User` model has no `$hidden` for this field. NI numbers are UK Personal Data under GDPR.
- **Fix:** Remove from profile array. Expose only through a dedicated, rate-limited endpoint if needed.

#### SEC-02: Admin User Seeded as Preview User in Production
- **File:** `database/seeders/AdminUserSeeder.php:32`
- **Category:** Authentication Bypass
- **Detail:** `admin@fps.com` created with `is_preview_user = true` and weak password `admin123`. PreviewWriteInterceptor returns fake success responses for this account. Seeder runs unconditionally in production.
- **Fix:** Remove `is_preview_user = true`. Set `email_verified_at` directly. Use `env('ADMIN_SEED_PASSWORD')` for production.

### HIGH

#### SEC-03: MFA Middleware Registered But Never Applied to Any Route
- **File:** `routes/api.php`, `app/Http/Kernel.php:75`
- **Category:** Missing Middleware
- **Detail:** `mfa.verified` alias exists but zero routes use it. MFA only enforced at token issuance. No route-level backstop for financial write operations.
- **Fix:** Apply `mfa.verified` to authenticated route groups for financial write routes.

#### SEC-04: User Model Mass-Assignable Sensitive Fields
- **File:** `app/Models/User.php:28-38`
- **Category:** Mass Assignment
- **Detail:** `$guarded` does not include `spouse_id`, `national_insurance_number`, `plan`, `role_id`, `household_id`, `locked_until`, `failed_login_count`, `must_change_password`. A controller calling `$user->update($request->validated())` could allow privilege escalation via `spouse_id` or `plan`.
- **Fix:** Add all listed fields to `$guarded`.

#### SEC-05: Subscription/Payment Models Fillable Includes Status Fields
- **Files:** `app/Models/Subscription.php`, `app/Models/Payment.php`
- **Category:** Mass Assignment
- **Detail:** `$fillable` includes `status`, `amount`, `revolut_order_id`. If any controller ever uses `$subscription->update($request->all())`, attacker could set `status = 'active'`.
- **Fix:** Move lifecycle fields to `$guarded` or use explicit assignment only.

#### SEC-06: Exception Message Leakage in EstateController
- **File:** `app/Http/Controllers/Api/EstateController.php:160,183,207,238,333,428`
- **Category:** Information Disclosure
- **Detail:** Six `catch` blocks return raw `$e->getMessage()` to the client, bypassing `SanitizedErrorResponse`.
- **Fix:** Use `$this->errorResponse($e, 'context')` consistently.

#### SEC-07: PolicyCRUDTrait Leaks Raw Exception Messages
- **File:** `app/Traits/PolicyCRUDTrait.php:52,98,138`
- **Category:** Information Disclosure
- **Detail:** Returns `$e->getMessage()` in all catch-all handlers. Affects all five policy types.
- **Fix:** Use `SanitizedErrorResponse` trait or fixed production message.

#### SEC-08: Dead `user_id` Parameter in verifyCode (Latent IDOR)
- **File:** `app/Http/Controllers/Api/AuthController.php:411-418`
- **Category:** Authentication
- **Detail:** `user_id` parameter validated but never used. If a developer adds a fallback, it becomes a direct IDOR for brute-forcing verification codes.
- **Fix:** Remove `user_id` from validation rules entirely.

#### SEC-09: MFA Challenge Token Has No Token-Level Claim
- **File:** `app/Http/Controllers/Api/AuthController.php:212`
- **Category:** Authentication
- **Detail:** `EnsureMFAVerified` blindly passes all Bearer token requests. No claim proves MFA was completed.
- **Fix:** Use `createToken('auth_token', ['mfa_verified'])` and verify `$user->currentAccessToken()->can('mfa_verified')`.

#### SEC-10: AdminController Exposes Full Server Filesystem Paths
- **File:** `app/Http/Controllers/Api/AdminController.php:380,412`
- **Category:** Information Disclosure
- **Detail:** Backup creation and listing return full server path in JSON response.
- **Fix:** Return filename only, not absolute path.

### MEDIUM

#### SEC-11: SanitizeInput Missing Exemptions for Security Fields
- **File:** `app/Http/Middleware/SanitizeInput.php:61-77`
- **Detail:** `mfa_secret`, `token`, `api_key`, `new_password` not in `$exemptFields`. `strip_tags()` runs on them.
- **Fix:** Add to exempt list.

#### SEC-12: AiMessageContent.vue v-html Pattern Fragility
- **File:** `resources/js/components/Shared/AiMessageContent.vue:30`
- **Detail:** Escape-then-inject HTML pattern. Safe today but fragile if regex is added before escapeHtml.
- **Fix:** Add comment documenting invariant. Consider DOMPurify.

#### SEC-13: Timer Leaks in Admin Action Components
- **Files:** `AdminProtectionActions.vue:309`, `AdminInvestmentActions.vue:309`, `AdminRetirementActions.vue:275`, `AiSettings.vue:135,139`
- **Detail:** `setTimeout` with no `beforeUnmount` cleanup. Vue warnings on unmounted state mutation.
- **Fix:** Store timeout ID, clear in `beforeUnmount()`.

---

## SECTION 2: TAX COMPLIANCE (7 issues)

### CRITICAL

#### TAX-01: Hardcoded Tax Thresholds in RetirementActionDefinitionService
- **File:** `app/Services/Retirement/RetirementActionDefinitionService.php:525,527,661,663,802,804`
- **Detail:** `$grossIncome > 125140` and `$grossIncome > 50270` as raw literals. Every other service reads from TaxConfigService.
- **Fix:** Use `$this->taxConfig->getIncomeTax()['bands']` with constant fallbacks.

#### TAX-02: Hardcoded ISA Limit in AssetLocationController
- **File:** `app/Http/Controllers/Api/Investment/AssetLocationController.php:43`
- **Detail:** `max:20000` hardcoded. TaxConfigService already injected.
- **Fix:** `'max:' . $this->taxConfig->getISAAllowances()['annual_allowance']`

#### TAX-03: Hardcoded Annual Allowance (60000) in 4 Frontend Files
- **Files:** `CapitalAdequacyTab.vue:349`, `AnnualAllowanceTracker.vue:182,281,288`, `Dashboard.vue:1202`, `TaxOptimisationCard.vue:115`
- **Detail:** `return 60000` used as fallback when API data unavailable.
- **Fix:** Show loading state when null. Use `taxConfig.js` constant from backend.

### HIGH

#### TAX-04: Tax Fallback Constants as Bare Literals
- **Files:** `HouseholdPlanningService.php:251-252,909-910`, `EstateAgent.php:1546`
- **Detail:** `?? 325000` and `?? 175000` instead of `TaxDefaults::NRB` and `TaxDefaults::RNRB`.
- **Fix:** Use `TaxDefaults` constants.

#### TAX-05: ISA Validation Uses Compile-Time Constant Not TaxConfigService
- **Files:** `StoreInvestmentAccountRequest.php:51`, `UpdateInvestmentAccountRequest.php:56`
- **Detail:** `TaxDefaults::ISA_ALLOWANCE` is compile-time. Won't update from database.
- **Fix:** Use `ValidationLimits::getISALimit(app(TaxConfigService::class))`.

#### TAX-06: ISA Fallback as Bare Integer in Coordination Services
- **Files:** `ConflictResolver.php:321`, `CrossModuleStrategyService.php:86`
- **Detail:** `?? 20000` instead of `TaxDefaults::ISA_ALLOWANCE`.
- **Fix:** Use constant.

#### TAX-07: RNRB Applied Without Qualification Check
- **File:** `HouseholdPlanningService.php:909-915`
- **Detail:** `singlePersonScenario()` adds full RNRB without checking residence ownership. Overstates allowances vs. `IHTCalculationService` which correctly qualifies.
- **Fix:** Delegate to same `calculateRNRB()` logic or add property check.

---

## SECTION 3: DESIGN SYSTEM (10 issues)

### CRITICAL

#### DS-01: Local Currency Formatters Bypassing currencyMixin (9 files)
- **Files:** `EstateOverviewCard.vue`, `NetWorthWaterfallChart.vue`, `CoverageGapChart.vue`, `PremiumBreakdownChart.vue`, `ScenarioBuilder.vue`, `InvestmentOverviewCard.vue`, `Goals.vue`, `TaxBreakdownCard.vue`, `IncomeStatementTab.vue`
- **Rule:** 6
- **Fix:** Import and use `currencyMixin`.

#### DS-02: Hardcoded `bg-[#EEEEEE]` Across 14 Files
- **Files:** `EstateOverviewCard.vue`, `InvestmentOverviewCard.vue`, `SavingsOverviewCard.vue`, `ProtectionOverviewCard.vue`, `PolicyCard.vue`, `GoalCard.vue`, `GoalsByModule.vue`, `GoalsOverview.vue`, `AffordabilityOverviewCard.vue`, `Investment/GoalCard.vue`, `LearningMilestoneSidebar.vue`, `EstateDashboard.vue`, `AppLayout.vue`, `AiChatPanel.vue`
- **Rule:** 12 (`#EEEEEE` = `light-gray` token)
- **Fix:** Replace with `bg-light-gray`.

#### DS-03: Off-Palette Spinner/Tab Colors (9 files)
- **Files:** `RiskFactorDetailPage.vue`, `RiskLevelsExplainedPage.vue`, `RiskProfilePage.vue`, `RiskProfileSummary.vue`, `PensionDetail.vue`, `CashOverview.vue`, `BusinessInterestDetailInline.vue`, `BusinessInterestsList.vue`, `TrustsOverviewCard.vue`
- **Detail:** `border-blue-600` and `border-purple-600` are off-palette.
- **Fix:** Use `border-raspberry-500` or `border-violet-500`.

### HIGH

#### DS-04: Hardcoded SVG Hex Colors in Advisor Views
- **Files:** `AdvisorDashboard.vue`, `AdvisorActivityLog.vue`, `AdvisorClientDetail.vue`, `AdvisorReports.vue`, `AdvisorReviewsDue.vue`, `JourneyMap.vue`, `ModuleStatusBar.vue`, `FocusAreaSelection.vue`
- **Detail:** SVG `stroke="#5854E6"`, `fill="#1F2A44"`, etc. Can't use Tailwind but should use JS constants.
- **Fix:** Import color values from `designSystem.js` and bind dynamically.

#### DS-05: Banned Amber/Orange Color `#EF9F27` in Use
- **Files:** `PublicLayout.vue:299`, `OnePlatformPage.vue:150`, `CalculatorsPage.vue:1631`
- **Rule:** 9
- **Fix:** Replace with palette color (e.g., `violet-500` or `savannah-500`).

#### DS-06: Chart Colors Not from designSystem.js
- **Files:** `Dashboard.vue:781-784,857,867`, `AssetBreakdownBar.vue:67-70,97-98`
- **Detail:** `#B91C1C` (red-700), `#16A34A` (green-600), `#DC2626` (red-600) — off-palette.
- **Fix:** Import from `CHART_COLORS` / `PRIMARY_COLORS` / `SUCCESS_COLORS`.

### MEDIUM

#### DS-07: Acronyms in User-Facing Text (4 files)
- **Files:** `AssumptionsSettings.vue:38`, `Projections.vue:108,110`, `IncomeOccupation.vue:311`
- **Detail:** "DC pensions", "DB pensions" — should be "Defined Contribution", "Defined Benefit".
- **Fix:** Spell out.

#### DS-08: Score Display in ContributionPlanner.vue
- **File:** `resources/js/components/Investment/ContributionPlanner.vue:129-145`
- **Rule:** 13
- **Detail:** Tax Efficiency Score gauge (0-100 radialBar chart) shown to user.
- **Fix:** Remove chart. Show descriptive label + specific allocation amounts.

#### DS-09: Dead Score Computed Properties in Performance.vue
- **File:** `resources/js/components/Investment/Performance.vue:180-213`
- **Detail:** `portfolioHealthScore`, `diversificationScore` — dead code. Would violate Rule 13 if used.
- **Fix:** Remove.

#### DS-10: Duplicate Scoped CSS (Scrollbar, Keyframes)
- **Files:** `DBPensionForm.vue:357-372` (scrollbar), `NetWorthOverviewCard.vue:285-292` (keyframes loading), `OnboardingWizard.vue:1426`, `GuidanceTooltip.vue:381`, `TypingIndicator.vue:35`
- **Rule:** 12
- **Fix:** Use global classes or extract to `app.css`.

---

## SECTION 4: ARCHITECTURE (7 issues)

### CRITICAL

#### ARCH-01: God Class — SavingsActionDefinitionService (3,105 lines)
- **File:** `app/Services/Savings/SavingsActionDefinitionService.php`
- **Detail:** Single largest file. Same evaluation pattern duplicated across Retirement (2,343), Protection (1,959), Investment (1,264).
- **Fix:** Extract `BaseActionDefinitionService` with common framework.

#### ARCH-02: God Class — CoordinatingAgent (2,201 lines)
- **File:** `app/Agents/CoordinatingAgent.php`
- **Detail:** 16 constructor dependencies. Combines orchestration + AI chat (HasAiChat trait, 1,142 lines) + tool routing.
- **Fix:** Extract AI chat handling to dedicated service.

#### ARCH-03: EstateController Bypasses EstateAgent
- **File:** `app/Http/Controllers/Api/EstateController.php`
- **Detail:** Injects services directly instead of delegating through EstateAgent. Every other module follows Controller -> Agent -> Services.
- **Fix:** Inject and delegate to `EstateAgent`.

### HIGH

#### ARCH-04: Production console.log in app.js and AccountForm.vue
- **Files:** `resources/js/app.js:60-97` (8 logs), `resources/js/components/Investment/AccountForm.vue:797-987` (13 logs with `JSON.stringify(formData)`), `resources/js/store/modules/whatIf.js:67`
- **Detail:** Dumps financial data to browser console. Not guarded by `import.meta.env.DEV`.
- **Fix:** Replace with `logger.debug()`.

#### ARCH-05: FamilyMembersController Uses \Log Without Import
- **File:** `app/Http/Controllers/Api/FamilyMembersController.php:34,94,180,190,374,499`
- **Detail:** Uses `\Log::info()` global alias. Excessive logging on every index request.
- **Fix:** Import facade. Reduce production logging.

### MEDIUM

#### ARCH-06: Hardcoded Platform Fee Data Duplicated
- **Files:** `app/Services/Investment/FeeAnalyzer.php:461-483`, `app/Services/Investment/Fees/PlatformComparator.php`
- **Detail:** Vanguard, HL, AJ Bell, II, Fidelity, Charles Stanley fees hardcoded in two places.
- **Fix:** Extract to config file or database table.

#### ARCH-07: Test Coverage Gaps for Critical Services
- **Missing tests for:** `SavingsActionDefinitionService` (3,105 lines), `RetirementStrategyService` (1,852), `RetirementIncomeService` (2,031), `SalarySacrificeAnalyzer` (394), `OnboardingService` (1,193)

---

## SECTION 5: DATABASE (5 issues)

### HIGH

#### DB-01: Duplicate Goals Table Migrations
- **Files:** `2026_01_18_000001_create_goals_table.php`, `2026_01_24_160001_create_goals_table_v2.php`
- **Detail:** Both create `goals` table. v2 has `if (Schema::hasTable('goals'))` guard. Same for `goal_contributions_table`.
- **Fix:** Delete v1 files. Remove guard from v2.

#### DB-02: Payment Model Missing SoftDeletes
- **File:** `app/Models/Payment.php`
- **Detail:** No `SoftDeletes` trait. Hard cascade deletion destroys financial audit trail. UK FCA requires transaction record retention.
- **Fix:** Add `SoftDeletes` + migration for `deleted_at`.

### MEDIUM

#### DB-03: Subscription.amount Cast as Integer — Verify Column Type
- **File:** `app/Models/Subscription.php:43`
- **Detail:** Cast is `'integer'` but migration name implies decimal conversion. CLAUDE.md requires `decimal:2` for financial fields.
- **Fix:** Verify column type. Update cast if column is decimal.

#### DB-04: AdvisorClientSeeder Runs Unconditionally in Production
- **File:** `database/seeders/DatabaseSeeder.php:79`
- **Detail:** Not gated to local/development/staging like HouseholdSeeder and TestUsersSeeder.
- **Fix:** Move inside environment gate if it only seeds preview data.

#### DB-05: Missing declare(strict_types=1) in 2 Seeders
- **Files:** `database/seeders/TestUsersSeeder.php`, `database/seeders/DatabaseSeeder.php`
- **Fix:** Add declaration.

---

## SECTION 6: CONVENTION (5 issues)

### HIGH

#### CONV-01: Missing `readonly` on Constructor Properties (10+ controllers)
- **Files:** `MFAController.php`, `GDPRController.php`, `SessionController.php`, `AssetLocationController.php`, `PerformanceAttributionController.php`, `EfficientFrontierController.php`, `TaxOptimizationController.php`, `RebalancingCalculationController.php`, and others
- **Fix:** Add `readonly` to all promoted constructor properties.

#### CONV-02: PortfolioStrategyController Uses Legacy Constructor Pattern
- **File:** `app/Http/Controllers/Api/Investment/PortfolioStrategyController.php:14-18`
- **Fix:** Convert to PHP 8 promoted readonly property.

### MEDIUM

#### CONV-03: Inconsistent American/British Spelling in Comments
- **File:** `resources/js/store/modules/investment.js:331`
- **Detail:** Comment says "analyze" — should be "analyse" per project convention.

#### CONV-04: Unimplemented TODO in PortfolioOptimization.vue
- **File:** `resources/js/components/Investment/PortfolioOptimization.vue:197`
- **Detail:** `// TODO: Implement rebalancing plan creation`

#### CONV-05: CSP Nonce Migration TODO
- **File:** `app/Http/Middleware/SecurityHeaders.php:47`

---

## Confirmed Compliant Areas

The following were reviewed and found correctly implemented:

- **SQL injection:** All queries use Eloquent parameterised bindings. LIKE wildcards escaped.
- **CORS:** No wildcard origins in production. Well-configured.
- **Security headers:** HSTS, X-Frame-Options, X-Content-Type-Options, CSP all set.
- **Password reset:** Separate session table with expiry and MFA step.
- **Login lockout:** Progressive, IP-based.
- **Encryption at rest:** Account numbers, NI numbers (in FamilyMember, SavingsAccount, CashAccount, Mortgage, InvestmentAccount) encrypted via `Crypt::encryptString()`.
- **Webhook security:** HMAC signature verification before processing. Idempotency handled.
- **Joint ownership:** Consistent `WHERE user_id = ? OR joint_owner_id = ?` pattern with indexes.
- **N+1 queries:** Key controllers use `loadMissing()` / `load()` / eager loading.
- **Vuex stores:** Consistent British spelling for action names across all 7 modules.
- **PreviewWriteInterceptor:** All auth routes correctly excluded.
- **AgentTokenAuth:** Uses `hash_equals()` for timing-safe comparison.
- **Observer cache invalidation:** Correct for both primary and joint owners.

---

## Priority Recommendations

### Immediate (before next deploy)
1. **SEC-01** — Remove NI number from profile API response
2. **SEC-03** — Apply MFA middleware to financial routes
3. **SEC-04** — Guard sensitive User model fields
4. **ARCH-04** — Remove console.log from production code

### This sprint
5. **TAX-01/02/03** — Replace all hardcoded tax values with TaxConfigService
6. **DS-02** — Replace `bg-[#EEEEEE]` with `bg-light-gray` (14 files, quick find/replace)
7. **DS-03** — Fix off-palette spinner colors (9 files)
8. **SEC-02** — Fix admin seeder (remove preview user flag, secure password)
9. **DS-01** — Replace local currency formatters with currencyMixin (9 files)

### Next sprint
10. **ARCH-01/02** — Refactor god classes (extract BaseActionDefinitionService, separate AI chat)
11. **DB-01** — Clean up duplicate migrations
12. **DB-02** — Add SoftDeletes to Payment model
13. **ARCH-07** — Add tests for critical untested services
