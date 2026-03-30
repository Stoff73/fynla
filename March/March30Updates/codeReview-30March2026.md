# Fynla Full Codebase Review -- 30 March 2026

**Scope:** 582 Vue components, 214 PHP services, 89 controllers, 89 models, 31 Vuex stores
**Reviewers:** 4 parallel agents (Backend, Frontend, Database/Security, Architecture)
**Date:** 30 March 2026
**Previous review:** 28 March 2026 (43 issues)
**Changes since last review:** PR #170 (content branch -- 70 files, +2,957/-2,024)

---

## Executive Summary

| Severity | Count | Previous (28 Mar) | Trend |
|----------|-------|--------------------|-------|
| CRITICAL | 5 | 12 | Improved |
| HIGH | 18 | 12 | Increased |
| MEDIUM | 22 | 11 | Increased |
| LOW | 16 | 8 | Increased |
| **Total** | **61** | **43** | +18 new |

**Previous issues fixed:** 3 of 43 (7%)
**Previous issues partially fixed:** 4 of 43
**Previous issues still present:** 36 of 43
**New issues found:** 24

**Top concerns:**
1. **Financial precision** -- 60+ model fields cast as `float` instead of `decimal:2` (NEW, CRITICAL). Payment/Subscription `amount` cast as `integer` despite decimal DB columns (STILL PRESENT, CRITICAL)
2. **Tax compliance** -- RNRB applied without qualification check (understates IHT by up to GBP 70,000). Hardcoded tax thresholds still present in 6+ files
3. **Security** -- NI number still exposed in API. MFA middleware still not applied to any routes. Mass-assignable sensitive fields on User, Subscription, Payment
4. **Design system** -- PR #170 introduces new off-palette colors (`slate-*`, `emerald-*`, `red-*`). Banned amber `#EF9F27` still in 3 files. 19 files bypass currencyMixin
5. **Architecture** -- Dashboard.vue now 2,124 lines (NEW god component). CalculatorsPage.vue 2,432 lines. Backend god classes continue growing (SavingsActionDef now 3,675 lines)
6. **New code** -- PricingPage FAQ says "Stripe" when system uses Revolut. Login/Register `px-32` breaks mobile. Duplicate "our our" text

---

## SECTION 1: CRITICAL ISSUES (5)

#### CRIT-01: Payment and Subscription `amount` cast as `integer` -- data corruption
- **Files:** `app/Models/Payment.php:29`, `app/Models/Subscription.php:42`
- **Category:** Database / Models
- **Status:** STILL PRESENT (was DB-03)
- **Detail:** The migration correctly changed both columns to `decimal(10,2)`, but the models still cast `amount` to `'integer'`. Every amount read from the database is truncated -- a payment of GBP 10.99 becomes GBP 10.
- **Fix:** Change both to `'amount' => 'decimal:2'`.

#### CRIT-02: 60+ financial model fields cast as `float` -- precision loss
- **Files:** `app/Models/Investment/Holding.php:41-49` (8 fields), `app/Models/Estate/IHTCalculation.php:59-81` (23 fields), `app/Models/Estate/IHTProfile.php:33-36`, `app/Models/Estate/Asset.php:34`, `app/Models/Estate/Gift.php:30`, `app/Models/Estate/Liability.php:38-40`, `app/Models/ExpenditureProfile.php:30-37` (8 fields), `app/Models/ProtectionProfile.php:50-53`, `app/Models/RecommendationTracking.php:32-33`, `app/Models/Investment/InvestmentGoal.php:29`, `app/Models/Investment/RiskProfile.php:32`, `app/Models/Investment/RebalancingAction.php:43-55` (11 fields)
- **Category:** Database / Models
- **Status:** NEW
- **Detail:** IEEE 754 float arithmetic introduces rounding errors (`0.1 + 0.2 !== 0.3`). For IHT calculations involving large sums, float rounding can push estates above or below the nil-rate band threshold -- a 40% tax consequence. The IHTCalculation model alone has 23 financial fields all cast as float.
- **Fix:** Systematic migration: currency fields to `'decimal:2'`, percentage/rate fields to `'decimal:4'`, quantity fields to `'decimal:4'`.

#### CRIT-03: Hardcoded tax thresholds in RetirementActionDefinitionService
- **File:** `app/Services/Retirement/RetirementActionDefinitionService.php:525,527,661,663,802,804`
- **Category:** Tax Compliance
- **Status:** STILL PRESENT (was TAX-01)
- **Detail:** `$grossIncome > 125140` and `$grossIncome > 50270` as raw literals in three methods. No TaxConfigService lookup.
- **Fix:** Retrieve from `$this->taxConfig->get('income_tax.bands')` with `TaxDefaults::` fallbacks.

#### CRIT-04: RNRB applied without qualification check -- understates IHT by up to GBP 70,000
- **File:** `app/Services/Coordination/HouseholdPlanningService.php:269,914`
- **Category:** Tax Compliance
- **Status:** STILL PRESENT (was TAX-07)
- **Detail:** RNRB unconditionally added to allowances without checking: (1) estate includes a main residence, (2) residence passes to direct descendants, (3) net estate below GBP 2M taper threshold. Can understate IHT liability by 175,000 * 40% = GBP 70,000.
- **Fix:** Add qualification checks for property ownership, descendant beneficiaries, and taper.

#### CRIT-05: PricingPage FAQ says "Stripe" -- system uses Revolut
- **File:** `resources/js/views/Public/PricingPage.vue:367`
- **Category:** New Code / Correctness
- **Status:** NEW
- **Detail:** FAQ answer states payments are processed through "Stripe". The actual payment integration is Revolut. Factually incorrect user-facing content.
- **Fix:** Replace "Stripe" with "Revolut" or generic "our secure payment processor".

---

## SECTION 2: SECURITY (16 issues)

### HIGH

#### SEC-01: National Insurance Number exposed in API response
- **File:** `app/Services/UserProfile/UserProfileService.php:58,607`
- **Status:** STILL PRESENT
- **Detail:** `getCompleteProfile()` returns `national_insurance_number` in the profile payload. NI numbers are UK Personal Data under GDPR.
- **Fix:** Mask in response (`'***' . substr($ni, -4)`). Expose full value only through a dedicated, rate-limited endpoint.

#### SEC-03: MFA middleware registered but never applied to any route
- **File:** `routes/api.php`, `app/Http/Kernel.php:75`
- **Status:** STILL PRESENT
- **Detail:** `mfa.verified` alias exists but zero routes use it. No route-level backstop for financial write operations.
- **Fix:** Apply to authenticated route groups for financial write routes.

#### SEC-04: User model missing guarded fields
- **File:** `app/Models/User.php:28-38`
- **Status:** PARTIALLY FIXED (added is_admin, is_advisor, is_preview_user; still missing role_id, spouse_id, household_id)
- **Fix:** Add `'role_id', 'spouse_id', 'household_id'` to `$guarded`.

#### SEC-05: Subscription/Payment $fillable includes status fields
- **Files:** `app/Models/Subscription.php:18-33`, `app/Models/Payment.php:15-26`
- **Status:** STILL PRESENT
- **Fix:** Remove `status`, `revolut_order_id`, `revolut_subscription_id` from `$fillable`. Set explicitly in service code.

#### SEC-06: EstateController raw $e->getMessage() in 6 catch blocks
- **File:** `app/Http/Controllers/Api/EstateController.php:160,183,207,238,333,428`
- **Status:** STILL PRESENT
- **Fix:** Use `$this->errorResponse($e, 'context')` consistently.

#### SEC-07: PolicyCRUDTrait raw $e->getMessage() in 3 catch blocks
- **File:** `app/Traits/PolicyCRUDTrait.php:52,98,138`
- **Status:** STILL PRESENT
- **Fix:** Use `SanitizedErrorResponse` trait methods.

#### SEC-S02: AdminUserSeeder is_preview_user=true + weak password
- **File:** `database/seeders/AdminUserSeeder.php:27-34`
- **Status:** STILL PRESENT (was SEC-02)
- **Fix:** Remove `is_preview_user => true`. Use env-based password. Set `must_change_password => true`.

#### SEC-S05: MFA verify returns raw user data, not UserResource
- **File:** `app/Http/Controllers/Api/MFAController.php:211-220`
- **Status:** NEW
- **Detail:** MFA `verify()` and `useRecoveryCode()` return raw arrays instead of `UserResource`. Inconsistent with `AuthController::buildAuthSuccessResponse()`.
- **Fix:** Use `new UserResource($user)` in MFA responses.

#### SEC-S06: Payment model missing SoftDeletes
- **File:** `app/Models/Payment.php`
- **Status:** STILL PRESENT (was DB-02)
- **Detail:** Hard deletion destroys financial audit trail. UK FCA requires transaction record retention.
- **Fix:** Add `SoftDeletes` trait + migration for `deleted_at`.

### MEDIUM

#### SEC-09: MFA token has no token-level claim
- **File:** `app/Http/Middleware/EnsureMFAVerified.php:29`
- **Status:** STILL PRESENT
- **Fix:** Add `mfa_verified` ability to tokens and check in middleware.

#### SEC-10: AdminController exposes full filesystem paths
- **File:** `app/Http/Controllers/Api/AdminController.php:381,416`
- **Status:** STILL PRESENT
- **Fix:** Return filename only.

#### SEC-11: SanitizeInput missing exemptions for security fields
- **File:** `app/Http/Middleware/SanitizeInput.php:36-40`
- **Status:** STILL PRESENT
- **Fix:** Add `'code'`, `'challenge_token'`, `'mfa_secret'`, `'mfa_recovery_codes'` to exempt list.

#### SEC-12: v-html in AiMessageContent.vue (mitigated)
- **File:** `resources/js/components/Shared/AiMessageContent.vue:30`
- **Status:** STILL PRESENT (mitigated by escapeHtml)
- **Fix:** Add `sanitizeHtml()` as second layer for defense-in-depth.

#### SEC-13: Timer leaks in admin action components
- **Files:** `AdminInvestmentActions.vue:309`, `AdminProtectionActions.vue:309`, `AiSettings.vue:135,139`
- **Status:** STILL PRESENT
- **Fix:** Store timeout ID, clear in `beforeUnmount()`.

### LOW

#### SEC-08: Dead user_id parameter in verifyCode validation
- **File:** `app/Http/Controllers/Api/AuthController.php:416`
- **Status:** STILL PRESENT (vestigial but harmless)
- **Fix:** Remove `user_id` from validation rules.

#### SEC-S04: SecurityHeaders missing modern isolation headers
- **File:** `app/Http/Middleware/SecurityHeaders.php`
- **Status:** NEW
- **Fix:** Add `X-Permitted-Cross-Domain-Policies: none`, consider `Cross-Origin-Opener-Policy: same-origin`.

---

## SECTION 3: TAX COMPLIANCE (8 issues)

### CRITICAL
CRIT-03 and CRIT-04 (see above)

### HIGH

#### TAX-03: Hardcoded 60000 Annual Allowance in 8+ frontend files
- **Files:** `Dashboard.vue:1634,1662`, `CapitalAdequacyTab.vue:349`, `AnnualAllowanceTracker.vue:179,182,281,288`, `WrapperOptimizer.vue:133,390`, `TaxOptimisationCard.vue:115`, `RetirementDetail.vue:76,80,82`
- **Status:** STILL PRESENT
- **Fix:** Create `TAX_ANNUAL_ALLOWANCE` constant in `constants/taxConfig.js` or fetch from backend.

### MEDIUM

#### TAX-02: Hardcoded max:20000 in AssetLocationController
- **File:** `app/Http/Controllers/Api/Investment/AssetLocationController.php:43`
- **Status:** STILL PRESENT
- **Fix:** Use `'max:' . TaxDefaults::ISA_ALLOWANCE`.

#### TAX-04: Bare literal fallbacks (?? 325000, ?? 175000)
- **Files:** `HouseholdPlanningService.php:251-252,909-910`, `EstateAgent.php:1546`
- **Status:** STILL PRESENT
- **Fix:** Use `TaxDefaults::NRB` and `TaxDefaults::RNRB`.

#### TAX-06: ISA fallback as bare integer
- **Files:** `ConflictResolver.php:321`, `CrossModuleStrategyService.php:86`
- **Status:** STILL PRESENT
- **Fix:** Use `TaxDefaults::ISA_ALLOWANCE`.

---

## SECTION 4: DESIGN SYSTEM (14 issues)

### HIGH

#### DS-05: Banned amber `#EF9F27` still in use
- **Files:** `PublicLayout.vue:438`, `CalculatorsPage.vue:1633`, `OnePlatformPage.vue:150`
- **Status:** STILL PRESENT
- **Fix:** Replace with palette color (savannah-500 or raspberry-500).

#### DS-08: Score gauge in ContributionPlanner.vue
- **File:** `resources/js/components/Investment/ContributionPlanner.vue:129-145`
- **Status:** STILL PRESENT
- **Fix:** Replace radialBar with descriptive text and specific metrics.

### MEDIUM

#### DS-01: Local Intl.NumberFormat bypassing currencyMixin (19 files)
- **Files:** EstateOverviewCard, NetWorthWaterfallChart, IHTPlanning, GiftingStrategy, CoverageGapChart, PremiumBreakdownChart, ScenarioBuilder, InvestmentOverviewCard, Goals, RebalancingActions, HoldingsTable, TaxBreakdownCard, IncomeStatementTab, PrivateInvestmentDetail, EmployeeShareSchemeDetail, netWorth.js, portfolioOptimizationService.js, planPrintMixin.js, willDocumentRenderer.js
- **Status:** STILL PRESENT (expanded from 9 to 19 files)
- **Fix:** Replace with currencyMixin or direct import from `utils/currency.js`.

#### DS-03: Off-palette spinner/tab colors (9+ files)
- **Files:** RiskFactorDetailPage, RiskLevelsExplainedPage, RiskProfilePage, RiskProfileSummary, PensionDetail, CashOverview, BusinessInterestDetailInline, BusinessInterestsList, TrustsOverviewCard, TrustCard, ProtectionOverviewCard
- **Status:** STILL PRESENT
- **Fix:** Use `border-t-raspberry-500 border-horizon-200` for spinners; `border-violet-500` for active tabs.

#### DS-04: Hardcoded hex in SVG/style blocks (expanded with PR #170)
- **Files:** JourneyMap (15+ hex), FocusAreaSelection (10+), HowItWorksPage (3 inline gradients), FeaturesPage (#37B679, #F06595), PlansDashboard, NetWorthOverviewCard, GoalsProjectionChartDashboard, LetterToSpouse (20+ in print styles), GiftCard, WillBuilderReviewStep
- **Status:** STILL PRESENT (expanded)
- **Fix:** Import from `designSystem.js` for JS/SVG. Use `@apply` for style blocks.

#### DS-06: Off-palette chart colors
- **File:** `Dashboard.vue:1168-1171,1182,1212-1215`, `AssetBreakdownBar.vue:67-70,97-98`
- **Status:** PARTIALLY FIXED (asset colors now from designSystem.js; liability colors still hardcoded)
- **Fix:** Import `CHART_COLORS` and define `LIABILITY_COLORS` in `designSystem.js`.

#### DS-07: Acronyms in user-facing text
- **Files:** `Projections.vue:108,110`, `AssumptionsSettings.vue:38`, `IncomeOccupation.vue:311`, `HowItWorksPage.vue:43` (SIPP -- NEW), `ProtectingAndGrowingPage.vue:134` (ICE -- NEW), `EnjoyingYourWealthPage.vue:139,193` (ICE -- NEW)
- **Status:** STILL PRESENT (expanded with PR #170)
- **Fix:** Spell out DC/DB/SIPP/ICE in user-facing text.

#### DS-NEW-01: Non-palette `slate-*` throughout CalculatorsPage.vue
- **File:** `resources/js/views/Public/CalculatorsPage.vue` (extensive)
- **Status:** NEW (PR #170)
- **Detail:** Widespread `slate-700`, `slate-400`, `slate-300`, `slate-500`, `slate-900`, `slate-50`, `slate-200`.
- **Fix:** Replace with `horizon-*` (text), `neutral-*` (muted), `light-gray` (borders), `eggshell-*` (backgrounds).

#### DS-NEW-02: Non-palette `emerald-*`/`slate-*` in PricingPage.vue
- **File:** `resources/js/views/Public/PricingPage.vue:243-267`
- **Status:** NEW (PR #170)
- **Detail:** Uses `emerald-100`, `emerald-600`, `slate-600`.
- **Fix:** Replace with `spring-*` and `neutral-*`/`horizon-*`.

#### DS-NEW-03: `text-red-800` in Login.vue
- **File:** `resources/js/views/Login.vue:80,89`
- **Status:** NEW (PR #170)
- **Fix:** Replace with `text-raspberry-700`.

### LOW

#### DS-02: bg-[#EEEEEE] in 14 files
- **Status:** STILL PRESENT
- **Fix:** Replace with `bg-light-gray`.

#### DS-09: Dead score computed properties in Performance.vue
- **Status:** STILL PRESENT
- **Fix:** Remove unused code.

#### DS-10: Duplicate scoped CSS (1 file remaining)
- **File:** `DBPensionForm.vue:357-370`
- **Status:** PARTIALLY FIXED
- **Fix:** Use global `scrollbar-thin` class.

---

## SECTION 5: ARCHITECTURE (10 issues)

### HIGH

#### ARCH-04: Dashboard.vue -- new god component (2,124 lines)
- **File:** `resources/js/views/Dashboard.vue`
- **Status:** NEW
- **Detail:** Largest Vue file. Contains all card rendering, 21+ API dispatches, extensive computed properties. The `loadAllData()` fires all requests in parallel.
- **Fix:** Extract each card into its own component (`NetWorthCard`, `ProtectionCard`, `AllowancesCard`, etc.).

#### ARCH-01: SavingsActionDefinitionService -- 3,675 lines (grew +570)
- **Status:** STILL PRESENT
- **Fix:** Extract into focused sub-services with shared base class.

#### ARCH-02: CoordinatingAgent -- 2,536 lines (grew +335)
- **Status:** STILL PRESENT
- **Fix:** Extract AI chat handling and coordination strategies.

### MEDIUM

#### ARCH-03: EstateController bypasses EstateAgent
- **Status:** STILL PRESENT
- **Fix:** Route operations through EstateAgent.

#### ARCH-05: CalculatorsPage.vue -- god component (2,432 lines)
- **File:** `resources/js/views/Public/CalculatorsPage.vue`
- **Status:** NEW
- **Detail:** Multiple independent calculators in a single file. Each has its own data, methods, template.
- **Fix:** Extract each calculator into its own component with dynamic imports.

#### ARCH-06: Backend god classes continue growing
- **Detail:** 10 services/traits exceed 1,000 lines: RetirementActionDef (2,691), ProtectionActionDef (2,349), RetirementIncome (2,292), RetirementStrategy (2,141), IHTCalculation (1,574), InvestmentActionDef (1,486), OnboardingService (1,389), HasAiChat trait (1,331), ComprehensiveEstatePlan (1,308), InvestmentController (1,067)
- **Fix:** Extract common ActionDefinition patterns into a base class.

#### ARCH-07: Missing tests for critical services
- **Status:** STILL PRESENT
- **Detail:** SavingsActionDefinitionService (3,675 lines), RetirementStrategyService (2,141), RetirementIncomeService (2,292), OnboardingService (1,389), MobileDashboardAggregator, ComprehensiveEstatePlanService lack test coverage.

### LOW

#### ARCH-09: \Log facade without import in AuthController and FamilyMembersController
- **Files:** `AuthController.php` (12 instances), `FamilyMembersController.php` (6 instances)
- **Status:** STILL PRESENT / NEW
- **Fix:** Add `use Illuminate\Support\Facades\Log;`.

#### CONV-01: Missing readonly on constructor properties
- **File:** `PortfolioStrategyController.php:14-18` + 10 other controllers
- **Status:** STILL PRESENT
- **Fix:** Convert to PHP 8.1 promoted readonly properties.

---

## SECTION 6: NEW CODE ISSUES (PR #170)

#### NC-07: Duplicate text in PricingPage.vue
- **File:** `resources/js/views/Public/PricingPage.vue:272`
- **Severity:** LOW
- **Detail:** "take a look at our our features" -- double "our".
- **Fix:** Remove duplicate word.

#### NC-09: Login/Register `px-32` breaks mobile
- **Files:** `Login.vue:36`, `Register.vue:14`
- **Severity:** HIGH
- **Detail:** 128px horizontal padding on form container. On small screens, content width is severely compressed.
- **Fix:** Use responsive padding: `px-6 sm:px-12 md:px-32`.

#### NC-10: SIPP acronym not spelled out
- **File:** `HowItWorksPage.vue:43`
- **Severity:** LOW
- **Fix:** "Self-Invested Personal Pension" or just "personal pensions".

#### NC-11: ICE acronym not spelled out
- **Files:** `ProtectingAndGrowingPage.vue:134`, `EnjoyingYourWealthPage.vue:139,193`
- **Severity:** LOW
- **Fix:** "In Case of Emergency (ICE) letter" on first use.

#### NC-12: PublicLayout stage colours as hardcoded hex
- **File:** `PublicLayout.vue:434-438`
- **Severity:** LOW
- **Detail:** Journey stage dot indicators use 5 hardcoded hex values including banned `#EF9F27`.
- **Fix:** Define as constants in `designSystem.js`. Replace amber with palette color.

---

## SECTION 7: DATABASE (5 issues)

#### DB-M09: User model does not guard role_id
- **File:** `app/Models/User.php:28-38`
- **Severity:** HIGH
- **Status:** PARTIALLY FIXED (was SEC-04)
- **Fix:** Add `'role_id'`, `'plan'` to `$guarded`.

#### DB-M10: Policy models missing $hidden for policy_number
- **Files:** LifeInsurancePolicy, CriticalIllnessPolicy, IncomeProtectionPolicy, DisabilityPolicy, SicknessIllnessPolicy, DCPension (member_number)
- **Severity:** MEDIUM
- **Status:** NEW
- **Fix:** Add to `$hidden`. Consider encryption consistent with SavingsAccount/CashAccount pattern.

#### DB-M11: User model does not hide national_insurance_number
- **File:** `app/Models/User.php`
- **Severity:** MEDIUM
- **Status:** NEW
- **Fix:** Add to `$hidden` and add encryption accessor.

#### DB-S01: Missing declare(strict_types=1) in 3 seeders
- **Files:** `DatabaseSeeder.php`, `TestUsersSeeder.php`, `HouseholdSeeder.php`
- **Severity:** LOW
- **Status:** STILL PRESENT

#### DB-S04: AdvisorClientSeeder runs unconditionally in production
- **File:** `database/seeders/DatabaseSeeder.php:79`
- **Severity:** MEDIUM
- **Status:** STILL PRESENT
- **Fix:** Move inside environment gate.

---

## SECTION 8: PREVIOUS ISSUE STATUS TRACKER

| ID | Issue | 28 Mar | 30 Mar Status |
|----|-------|--------|---------------|
| SEC-01 | NI number in API response | CRITICAL | STILL PRESENT |
| SEC-02 | Admin seeder preview + weak pw | CRITICAL | STILL PRESENT |
| SEC-03 | MFA middleware never applied | HIGH | STILL PRESENT |
| SEC-04 | User model missing guarded fields | HIGH | PARTIALLY FIXED |
| SEC-05 | Subscription/Payment fillable status | HIGH | STILL PRESENT |
| SEC-06 | EstateController raw exception msgs | HIGH | STILL PRESENT |
| SEC-07 | PolicyCRUDTrait raw exception msgs | HIGH | STILL PRESENT |
| SEC-08 | Dead user_id in verifyCode | HIGH | STILL PRESENT (low risk) |
| SEC-09 | MFA token no claim | HIGH | STILL PRESENT |
| SEC-10 | AdminController filesystem paths | HIGH | STILL PRESENT |
| SEC-11 | SanitizeInput missing exemptions | MEDIUM | STILL PRESENT |
| SEC-12 | v-html in AiMessageContent | MEDIUM | STILL PRESENT (mitigated) |
| SEC-13 | Timer leaks in admin components | MEDIUM | STILL PRESENT |
| TAX-01 | Hardcoded 125140/50270 | CRITICAL | STILL PRESENT |
| TAX-02 | Hardcoded max:20000 | CRITICAL | STILL PRESENT |
| TAX-03 | Hardcoded 60000 AA frontend | CRITICAL | STILL PRESENT |
| TAX-04 | Bare literal NRB/RNRB fallbacks | HIGH | STILL PRESENT |
| TAX-05 | ISA compile-time constant | HIGH | STILL PRESENT |
| TAX-06 | ISA bare integer fallback | HIGH | STILL PRESENT |
| TAX-07 | RNRB no qualification check | HIGH | STILL PRESENT |
| DS-01 | Local currency formatters | CRITICAL | STILL PRESENT (19 files) |
| DS-02 | bg-[#EEEEEE] 14 files | CRITICAL | STILL PRESENT |
| DS-03 | Off-palette spinner colors | CRITICAL | STILL PRESENT |
| DS-04 | Hardcoded SVG hex | HIGH | STILL PRESENT (expanded) |
| DS-05 | Banned amber #EF9F27 | HIGH | STILL PRESENT |
| DS-06 | Off-palette chart colors | HIGH | PARTIALLY FIXED |
| DS-07 | Acronyms in user text | MEDIUM | STILL PRESENT (expanded) |
| DS-08 | Score gauge ContributionPlanner | MEDIUM | STILL PRESENT |
| DS-09 | Dead score props Performance.vue | MEDIUM | STILL PRESENT |
| DS-10 | Duplicate scoped CSS | MEDIUM | PARTIALLY FIXED |
| ARCH-01 | SavingsActionDef god class | CRITICAL | STILL PRESENT (3,675 lines) |
| ARCH-02 | CoordinatingAgent god class | CRITICAL | STILL PRESENT (2,536 lines) |
| ARCH-03 | EstateController bypasses agent | CRITICAL | STILL PRESENT |
| ARCH-04 | console.log in production | HIGH | STILL PRESENT |
| ARCH-05 | FamilyMembersController \Log | HIGH | STILL PRESENT |
| ARCH-06 | Hardcoded platform fee data | MEDIUM | STILL PRESENT |
| ARCH-07 | Missing tests for critical services | MEDIUM | STILL PRESENT |
| DB-01 | Duplicate goals migrations | HIGH | PARTIALLY FIXED |
| DB-02 | Payment missing SoftDeletes | HIGH | STILL PRESENT |
| DB-03 | Subscription.amount cast integer | MEDIUM | STILL PRESENT |
| DB-04 | AdvisorClientSeeder unconditional | MEDIUM | STILL PRESENT |
| DB-05 | Missing strict_types in seeders | MEDIUM | STILL PRESENT |
| CONV-01 | Missing readonly constructors | HIGH | STILL PRESENT |
| CONV-02 | PortfolioStrategy legacy constructor | MEDIUM | STILL PRESENT |

**Fixed (3):** SEC-08 (functionally fixed, vestigial rule remains), DB-01 (safety guard added), DS-10 (4 of 5 files fixed)

---

## Priority Recommendations

### Immediate (before next deploy)

1. **CRIT-01** -- Fix Payment/Subscription `amount` casts to `decimal:2` (data corruption)
2. **CRIT-05** -- Fix "Stripe" to "Revolut" in PricingPage FAQ (user-facing error)
3. **NC-09** -- Fix `px-32` on Login/Register for mobile responsiveness

### This sprint

4. **CRIT-02** -- Systematic float-to-decimal migration across 60+ model fields
5. **CRIT-03** -- Replace hardcoded tax thresholds in RetirementActionDefinitionService
6. **CRIT-04** -- Add RNRB qualification checks
7. **SEC-01** -- Mask NI number in API response
8. **SEC-03** -- Apply MFA middleware to financial write routes
9. **SEC-04** -- Add role_id, spouse_id, household_id to User $guarded
10. **SEC-05** -- Remove status from Subscription/Payment $fillable
11. **DS-05** -- Replace banned amber color in 3 files
12. **DS-NEW-01/02/03** -- Fix non-palette colors in PR #170 pages (slate, emerald, red)

### Next sprint

13. **ARCH-04** -- Extract Dashboard.vue card components (2,124 lines)
14. **ARCH-05** -- Extract CalculatorsPage.vue calculators (2,432 lines)
15. **SEC-06/07** -- Replace raw exception messages with SanitizedErrorResponse
16. **DS-01** -- Replace local currency formatters in 19 files
17. **DS-03** -- Standardise spinners to palette colors in 9+ files
18. **DS-02** -- Replace bg-[#EEEEEE] with bg-light-gray in 14 files
19. **TAX-03** -- Create frontend tax constant for Annual Allowance
20. **ARCH-01/02** -- Begin decomposing god classes

### Deferred

21. **DB-02** -- Add SoftDeletes to Payment model
22. **ARCH-07** -- Add tests for critical untested services
23. **DS-07** -- Spell out acronyms (DC, DB, SIPP, ICE)
24. **DS-08** -- Remove score gauge from ContributionPlanner
25. **CONV-01** -- Add readonly to constructor properties

---

## Confirmed Compliant Areas

- **SQL injection:** All queries use Eloquent parameterised bindings
- **CORS:** No wildcard origins in production
- **Security headers:** HSTS, X-Frame-Options, X-Content-Type-Options, CSP all set
- **Password reset:** Separate session table with expiry and MFA step
- **Login lockout:** Progressive, IP-based
- **Encryption at rest:** Account numbers, NI numbers in FamilyMember, SavingsAccount, CashAccount, Mortgage, InvestmentAccount
- **Webhook security:** HMAC signature verification with idempotency
- **Joint ownership:** Consistent query pattern with indexes
- **N+1 queries:** Key controllers use eager loading
- **Vuex stores:** Consistent British spelling for action names
- **PreviewWriteInterceptor:** All auth routes correctly excluded
- **AgentTokenAuth:** Timing-safe comparison
- **Observer cache invalidation:** Correct for both owners
- **declare(strict_types=1):** All 88 controllers compliant
- **v-for :key:** Zero missing bindings across 582 components
- **v-if/v-for:** Zero same-element violations
- **Chart standardisation:** 30+ chart components now use CHART_DEFAULTS from designSystem.js (PR #170 improvement)
- **Scrollbar classes:** Global classes used consistently across 25+ files
- **No custom @keyframes spin:** All use global animate-spin
