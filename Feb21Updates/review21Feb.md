# Fynla Codebase Review - 21 February 2026

**Version:** v0.7.0 | **Branch:** main | **Reviewer:** Claude Code
**Scope:** Full codebase (68 controllers, 144 services, 8 agents, 49 models, 313 Vue components, 21 Vuex stores, 103 test files)

---

## Executive Summary

The Fynla codebase is **well-architected** with strong separation of concerns, consistent PSR-12 compliance, 100% `declare(strict_types=1)` adoption, and professional-quality Vue patterns. However, this review identified **12 critical issues**, **23 high-severity issues**, and numerous medium/low items across six review areas. The most pressing concerns are: an authorization bypass risk on user profile routes, a middleware route mismatch breaking preview password resets, monetary values stored as integers, and only 34% service-level test coverage.

### Scorecard

| Area | Score | Key Concern |
|------|-------|-------------|
| Architecture | 9/10 | Clean Agent/Service/Controller layering |
| Security | 6/10 | Authorization gaps on user data routes |
| Performance | 7/10 | N+1 queries in estate calculations |
| Testing | 5/10 | 95+ services without unit tests |
| Frontend | 8/10 | Missing Vue 3 emit declarations |
| Data Integrity | 6/10 | Missing soft deletes, FK constraints |
| Code Quality | 8/10 | Some SRP violations in large services |

---

## Table of Contents

1. [Critical Issues (Fix Immediately)](#1-critical-issues)
2. [High Severity Issues](#2-high-severity-issues)
3. [Medium Severity Issues](#3-medium-severity-issues)
4. [Low Severity Issues](#4-low-severity-issues)
5. [Positive Findings](#5-positive-findings)
6. [Recommended Action Plan](#6-recommended-action-plan)

---

## 1. Critical Issues

### C1. Authorization Bypass on User Profile Routes
**Area:** Security | **File:** `routes/api.php` lines 236-238

Routes `/api/users/{userId}` and `/api/users/{userId}/expenditure` allow any authenticated user to access ANY user's profile by passing a `userId` parameter. No authorization check ensures the requesting user owns the data or is the spouse.

**Impact:** Users can retrieve sensitive financial information (income, expenditure, properties, investments) of any other user by enumerating user IDs.

**Fix:** Add policy check: `$this->authorize('view', User::find($userId));` or verify `$userId === auth()->id() || $userId === $user->spouse_id`.

---

### C2. PreviewWriteInterceptor Route Mismatch
**Area:** Middleware | **File:** `app/Http/Middleware/PreviewWriteInterceptor.php` lines 43-44

The excluded routes list references `api/auth/forgot-password` and `api/auth/reset-password`, but actual password reset routes use `api/auth/password-reset/*` prefix (defined at `routes/api.php` line 91).

**Impact:** Password reset requests from preview users are intercepted and receive fake-success responses, silently breaking password resets in preview mode.

**Fix:** Update lines 43-44 to match actual route paths:
```
'api/auth/password-reset/request',
'api/auth/password-reset/verify-email',
'api/auth/password-reset/resend-code',
'api/auth/password-reset/verify-mfa',
'api/auth/password-reset/mfa-recovery',
'api/auth/password-reset/reset',
```

---

### C3. Monetary Values Stored as Integer
**Area:** Database | **Files:**
- `database/migrations/2026_02_12_100002_create_payments_table.php` line 18
- `database/migrations/2026_02_12_100001_create_subscriptions_table.php` line 24
- `app/Models/Payment.php` line 18
- `app/Models/Subscription.php` line 25

Payment and Subscription tables use `integer` for `amount` columns, causing loss of pence precision.

**Impact:** GBP amounts lose decimal places. Calculations become unreliable.

**Fix:** Migration to change `integer` to `decimal(10,2)`. Update model casts to `'amount' => 'decimal:2'`.

---

### C4. Missing Soft Deletes on Financial Models
**Area:** Data Integrity | **Models affected (18):**

| Category | Models Missing SoftDeletes |
|----------|---------------------------|
| Assets | Property, CashAccount, InvestmentAccount, Holding |
| Liabilities | Mortgage, Estate\Liability |
| Pensions | DCPension, DBPension |
| Insurance | LifeInsurancePolicy, CriticalIllnessPolicy, DisabilityPolicy, IncomeProtectionPolicy, SicknessIllnessPolicy |
| Estate | Will, Bequest, Asset, Gift, IHTCalculation |
| Profiles | RetirementProfile, ExpenditureProfile |

**Models WITH SoftDeletes (correct):** BusinessInterest, Chattel, SavingsAccount, Goal, Document, SavingsGoal, LifeEvent, Subscription

**Impact:** Permanent deletion of financial records destroys audit trails. No recovery possible.

**Fix:** Add `SoftDeletes` trait and migration for `deleted_at` columns to all financial models.

---

### C5. Missing Foreign Key Constraints on joint_owner_id
**Area:** Data Integrity | **Models affected (6):**
- Property, Mortgage, CashAccount, Goal, InvestmentAccount, Estate\Liability

**Impact:** If a joint owner account is deleted, orphaned records remain, breaking calculations and audit trails. Only BusinessInterest and Chattel have FK constraints (added in today's migration).

**Fix:** Migration to add `->foreign('joint_owner_id')->references('id')->on('users')->onDelete('set null')` to all affected tables.

---

### C6. Missing Admin Controller Authorization
**Area:** Security | **File:** `app/Http/Controllers/Api/AdminController.php` lines 79-283

Admin methods (`getUsers`, `createUser`, `updateUser`, `deleteUser`, `getRoles`) lack explicit authorization checks. While routes use `permission:admin.access` middleware, the controller itself has no guard clause.

**Impact:** If route middleware is accidentally removed or misconfigured, admin operations become accessible to any authenticated user.

**Fix:** Add `if (!$request->user()->is_admin) abort(403);` or use policies.

---

### C7. Three SRP-Violating Services (1200+ Lines Each)
**Area:** Code Quality | **Files:**

| Service | Lines | Responsibilities |
|---------|-------|-----------------|
| `Services/Estate/IHTCalculationService.php` | 1315 | IHT calc, projections, life expectancy, caching, messaging |
| `Services/Estate/ComprehensiveEstatePlanService.php` | 1210 | Orchestration, balance sheets, breakdowns, messaging, formatting |
| `Services/Coordination/HolisticPlanner.php` | 619 | Plans, projections, risk, snapshots, summaries |

**Impact:** Hard to test, maintain, and debug. Changes to one area risk breaking another.

**Fix:** Split IHTCalculationService into IHTCalculator, EstateProjector, LifeExpectancyService, IHTCache. Split ComprehensiveEstatePlanService into EstatePlanBuilder, EstateBalanceSheetBuilder, EstateBreakdownBuilder.

---

### C8. Only 34% Service Test Coverage
**Area:** Testing | **Stats:** 49 services tested out of 144

**Services completely untested (95+):**
- Admin, Benefits, Business, Chattel, Dashboard, Documents
- Investment: Analytics, AssetLocation, Fees, Goals, ModelPortfolio, Rebalancing
- Payments, Risk

**Missing feature tests for controllers:**
- BusinessInterestController, ChattelController, GoalsController, LifeEventController
- DocumentController, TaxSettingsController, AdminController, PaymentController
- PostcodeLookupController, SpousePermissionController, OnboardingController

**Impact:** Critical business logic (tax calculations, portfolio analysis, estate planning) lacks integration tests.

---

### C9. Division by Zero Risk
**Area:** Calculations | **File:** `Services/Estate/FutureValueCalculator.php` line 165

```php
$monthlyReduction = $currentBalance / $remainingTermMonths; // No zero check
```

**Additional location:** `Services/Estate/IHTCalculationService.php` line 101 - `$taperRate` could be 0.

**Fix:** Add guard: `if ($remainingTermMonths <= 0) return max(0, $currentBalance);`

---

### C10. Missing Environment Variable Fallbacks
**Area:** Configuration | **File:** `config/services.php`

| Variable | Line | Fallback |
|----------|------|----------|
| MAILGUN_DOMAIN | 18 | None |
| MAILGUN_SECRET | 19 | None |
| POSTMARK_TOKEN | 25 | None |
| AWS_ACCESS_KEY_ID | 29 | None |
| AWS_SECRET_ACCESS_KEY | 30 | None |
| ANTHROPIC_API_KEY | 35 | None |
| GETADDRESS_API_KEY | 39 | None |

**Impact:** Mail and document processing crash if environment variables are undefined.

**Fix:** Add `env('VAR', '')` fallbacks or validate on boot in AppServiceProvider.

---

### C11. Hardcoded Values in MortgageController
**Area:** Code Quality | **File:** `Http/Controllers/Api/MortgageController.php` lines 96-122

- Line 98: `'lender_name' => 'To be completed'`
- Line 100: `'interest_rate' => 0.0000`
- Line 101: `'rate_type' => 'fixed'`
- Line 106: Default 25-year term hardcoded
- Line 122: `300` months (25 years) hardcoded

**Fix:** Move defaults to `config/mortgage.php`.

---

### C12. Inconsistent Decimal Precision in Model Casts
**Area:** Data Integrity | **Pattern mismatch:**

| Pattern | Models Using |
|---------|-------------|
| `float` for currency | Estate\Liability, Estate\IHTProfile, Estate\Asset |
| `decimal:2` for currency | CashAccount, Mortgage, Property |
| `decimal:4` for rates | CashAccount, Mortgage |
| `integer` for currency | Payment, Subscription |

**Impact:** Float casting loses precision on financial calculations. Inconsistent handling across modules.

**Fix:** Standardise to `decimal:2` for all currency, `decimal:4` for rates/percentages.

---

## 2. High Severity Issues

### H1. N+1 Query in Estate Calculations
**File:** `Services/Estate/IHTCalculationService.php` lines 501-523

Iterates over `investmentAccounts`, `mortgages`, and `liabilities` relationships without eager loading. A user with 10 investment accounts triggers 10+ extra queries per IHT calculation.

**Fix:** Eager load in controller: `$user->load('investmentAccounts', 'mortgages', 'liabilities')`.

---

### H2. Inconsistent Error Handling Patterns Across Controllers
Multiple patterns used: `firstOrFail()` exceptions, explicit try-catch with `ModelNotFoundException`, `SanitizedErrorResponse` trait.

**Fix:** Standardise on `SanitizedErrorResponse` trait for all controllers.

---

### H3. Code Duplication: Future Value Calculations (3 Implementations)
1. `FutureValueCalculator.calculateFutureValue()` line 181
2. `IHTCalculationService` embedded at line 786
3. `RetirementProjectionService` inline in projections

**Fix:** Consolidate to single `FutureValueCalculator` service.

---

### H4. Code Duplication: Life Expectancy Lookups (2 Implementations)
1. `FutureValueCalculator.lookupLifeExpectancy()` lines 55-108
2. `IHTCalculationService.calculateLifeExpectancy()` lines 1155-1177

Both query `actuarial_life_tables`.

**Fix:** Create `ActuarialService` to consolidate.

---

### H5. Missing Cache Invalidation Methods
| Service | Has invalidateCache()? |
|---------|----------------------|
| IHTCalculationService | Yes |
| MonteCarloSimulator | Yes |
| ComprehensiveEstatePlanService | **No** |
| RetirementProjectionService | **No** |

**Fix:** Add `invalidateCache()` to both missing services.

---

### H6. Inconsistent Cache Strategies
- Agents use `Cache` facade with tags (via BaseAgent)
- IHTCalculationService uses database deletion
- MonteCarloSimulator uses `DB::table('monte_carlo_cache')`

No coordination between the three strategies.

**Fix:** Standardise on Cache facade throughout. Document strategy in CLAUDE.md.

---

### H7. Long Controller Methods (>50 Lines Business Logic)
**File:** `Http/Controllers/Api/InvestmentController.php` lines 955-1008

`calculateAccountAnnualisedReturn()` contains 50+ lines of financial calculation logic that belongs in a service.

**Fix:** Extract to `InvestmentCalculationService`.

---

### H8. Hardcoded Dates in Tests (Will Break April 2026)
| File | Lines | Hardcoded Values |
|------|-------|-----------------|
| `tests/Integration/ProtectionWorkflowTest.php` | 54, 69, 85, 164 | `2023-01-01`, `2024-01-01` |
| `tests/Feature/TaxConfigurationTest.php` | 67-73, 104-105, etc. | `2025/26`, `2025-04-06` |
| `tests/Unit/Services/TaxConfigServiceTest.php` | 169, 184, 196-200 | `2025/26` |

**Fix:** Use `Carbon::now()` relative dates or test constants.

---

### H9. Missing Indexes on Frequently Queried Columns
Columns lacking indexes: `trust_id`, `household_id`, `beneficiary_id` on financial models. Also missing composite indexes for `(user_id, status)` query patterns.

**Fix:** Migration to add indexes.

---

### H10. Incomplete File Upload Validation
**File:** `Http/Requests/Documents/UploadDocumentRequest.php` lines 26-31

Missing: filename sanitisation, file content inspection (magic bytes), virus scanning.

**Fix:** Add filename sanitisation and magic byte validation.

---

### H11. Missing Vue 3 Emit Declarations (~190 Components)
282 components use `$emit` but only 92 declare `emits:` arrays. Reduces type safety and DevTools debugging capability.

**Fix:** Add `emits:` declarations to all components that use `$emit`.

---

### H12. Six Large Vue Components (>1500 Lines)
| Component | Lines |
|-----------|-------|
| UserProfile/ExpenditureForm.vue | 2411 |
| NetWorth/PensionList.vue | 2137 |
| Retirement/RetirementIncomeTab.vue | 2095 |
| UserProfile/LetterToSpouse.vue | 1776 |
| Admin/TaxSettings.vue | 1689 |
| Estate/IHTPlanning.vue | 1634 |

**Fix:** Decompose into focused sub-components.

---

### H13. Information Leakage via findOrFail Pattern
**File:** `Http/Controllers/Api/RetirementController.php` lines 294, 370

```php
$pension = DCPension::findOrFail($id);  // Reveals existence
if ($pension->user_id !== $user->id) { abort(403); }
```

**Fix:** Scope query to user: `DCPension::where('user_id', $user->id)->findOrFail($id);`

---

### H14. Weak Test Assertions
**File:** `tests/Unit/Services/TaxConfigServiceTest.php` lines 43, 215, 234, 252

Uses `assertIsArray()` instead of `assertArrayHasKey()` for specific structure validation.

**Fix:** Replace with specific structural assertions.

---

### H15. Missing Model Factories (42 Models Without)
Only 28 of 70 models have factory classes. Missing factories for: Role, Permission, all Estate models, all Investment sub-models, CashAccount, Document, AuditLog, and 30+ others.

**Fix:** Create factories for critical models, starting with Estate and Investment.

---

### H16. Missing Pagination on List Endpoints
**File:** `Http/Controllers/Api/PropertyController.php` line 58

Uses `->get()` without pagination. A user with many properties loads all records with all relationships.

**Fix:** Change to `->paginate(20)` on all list endpoints.

---

### H17. Rate Limiting Gaps
- Password reset: `throttle:5,1` (too lenient, should be `3,1`)
- Monte Carlo simulation endpoints: no rate limit on expensive computation
- Portfolio optimisation: no rate limit

**Fix:** Tighten password reset to `throttle:3,1`. Add `throttle:10,1` to expensive endpoints.

---

### H18. Webhook Signature Bypass in Local
**File:** `Http/Controllers/Api/PaymentWebhookController.php` lines 106-109

```php
if (app()->environment('local', 'testing')) {
    return true;  // Skips verification
}
```

**Fix:** Use test webhook secret instead of skipping verification entirely.

---

### H19. Missing Joint Account Cache Invalidation
**File:** `Http/Controllers/Api/SavingsController.php` lines 336-343

Joint account updates clear savings cache but not related net worth or dashboard caches.

**Fix:** Invalidate related caches: `Cache::forget("net_worth_{$account->joint_owner_id}")`.

---

### H20. Missing Query Scopes on Frequently Filtered Models
Models without scopes for common filters:
- InvestmentAccount: no scope for `is_isa`, `account_type`, `status`
- Estate\Liability: no scope for `liability_type`, `is_priority_debt`
- Property: no scope for `property_type`, `ownership_type`
- Mortgage: no scope for `mortgage_type`

**Fix:** Add query scopes to reduce repetitive `where()` chains.

---

### H21. Business Logic in Models
| Model | Lines | Logic |
|-------|-------|-------|
| Goal.php | 132-244 | Progress calculations, on-track status, milestones |
| Estate\Trust.php | 84-127 | IHT value calculations |
| Property.php | 167-224 | Equity and lease expiry calculations |
| Investment\InvestmentAccount.php | 338-497 | Tax advantages, intrinsic/unvested values |

**Fix:** Extract to dedicated service classes.

---

### H22. Payment Model Uses $guarded Instead of $fillable
**File:** `app/Models/Payment.php` line 15

Uses `protected $guarded = ['id']` while all other models use `$fillable`. Inconsistent and less explicit about mass-assignment protection.

**Fix:** Switch to explicit `$fillable` array.

---

### H23. Missing Type Hints on Controller Parameters
**File:** `Http/Controllers/Api/MortgageController.php` lines 203-208

```php
public function update(UpdateMortgageRequest $request, ?int $propertyId = null, ?int $mortgageId = null)
```

If both are null, `$id = $mortgageId ?? $propertyId` becomes null, causing cryptic errors downstream.

**Fix:** Add validation guard: `if (!$mortgageId && !$propertyId) abort(400);`

---

## 3. Medium Severity Issues

### M1. Deprecated Methods in SavingsController
**File:** `Http/Controllers/Api/SavingsController.php` lines 468-580

5 deprecated methods retained for backwards compatibility with no removal deadline.

### M2. Inconsistent Response Format Structure
Different nesting levels: `{'data': {'property': ...}}` vs `{'data': {...}}` across controllers.

### M3. Direct API Call in PropertyForm.vue
`resources/js/components/NetWorth/PropertyForm.vue` uses axios directly instead of the service layer.

### M4. Insufficient CORS Validation
`config/cors.php` has no validation that `ALLOWED_ORIGINS` doesn't contain wildcards in production.

### M5. Missing Audit Field Exclusions
Models with `Auditable` trait (InvestmentAccount, Holding, DCPension) don't specify `$auditExcludeFields`, potentially logging sensitive tax data.

### M6. Inconsistent Relationship Naming
Some models use full namespace `\App\Models\Estate\Liability::class` while others use imports.

### M7. Nullable/Non-nullable Inconsistencies
`joint_owner_name` in Property, Chattel, and Mortgage models may not be properly nullable in migrations.

### M8. Missing Security Event Logging
No logging for: authorization check failures, invalid API request formats, document upload rejections, spouse data access attempts.

### M9. No Cache Key Versioning
If calculation logic changes, stale cache data could be served until TTL expires.

### M10. Acronym Compliance Needs Full Audit
68 components in Savings/Retirement modules need verification. Spot checks show compliance but full audit incomplete.

### M11. CalculatesOwnershipShare Trait Placement
87 lines of calculation logic in a trait used by controllers/services (not models). Better suited as a service.

### M12. Missing Custom Exceptions
Financial operations throw generic `\Exception` instead of domain-specific exceptions.

---

## 4. Low Severity Issues

### L1. Inconsistent Date Handling
`PersonalAccountsController.php` uses `Carbon::parse()` with `startOfYear()`/`endOfYear()` without timezone consideration.

### L2. Missing PHPDoc on Public Controller Methods
PaymentController methods lack documentation.

### L3. Redundant Relationship Loading
`ProtectionController.php` loads relationships then immediately accesses them in the same method.

### L4. Overly Generic Exception Handling
`EstateController.php` catches all `\Exception` without distinguishing types.

### L5. Legacy CSP Headers
SecurityHeaders middleware includes `X-XSS-Protection` which modern browsers ignore.

### L6. Only 2 of 313 Components Use Script Setup
The codebase uses Options API consistently, but new components could benefit from `<script setup>` syntax.

### L7. Missing Relationship Type Hints
Some model relationships lack return type hints (Role, AuditLog).

---

## 5. Positive Findings

### Architecture
- Clean Agent > Service > Controller layering with clear separation of concerns
- BaseAgent provides excellent foundation for consistent agent patterns
- All 8 agents extend BaseAgent with standardised cache key generation

### Backend Quality
- 100% `declare(strict_types=1)` compliance across all 144 services
- Consistent PSR-12 formatting throughout
- TaxConfigService properly used for all UK tax values (no hardcoded tax rates)
- Type hints consistently applied in method signatures
- Well-documented methods with thorough DocBlocks

### Frontend Quality
- 146/146 components using currencyMixin correctly (zero violations)
- All form modals emit `save` not `submit` (correct pattern)
- Zero amber/orange colour violations (design system compliant)
- Zero `v-if` with `v-for` on same element violations
- All `v-for` loops have `:key` attributes
- Vuex stores have excellent error handling with try-catch and loading states
- API service layer has professional retry logic with exponential backoff

### Security
- Strong MFA system with TOTP and recovery codes
- Proper password hashing with bcrypt via Laravel's hashed cast
- User model correctly hides password, MFA secret, recovery codes
- Rate limiting on most auth endpoints
- Preview user isolation with `is_preview_user` flag
- Security headers: X-Frame-Options, X-Content-Type-Options, HSTS
- Webhook signature verification for Revolut payments
- Input sanitisation middleware strips HTML from user input
- Session security: http_only, secure, same_site=lax configured

### Testing
- 103 test files covering core modules
- Architecture tests enforce structural rules
- Integration tests for protection workflow
- Feature tests cover main auth flows

---

## 6. Recommended Action Plan

### Phase 1: Immediate (This Week)
| # | Issue | Priority | Effort |
|---|-------|----------|--------|
| 1 | C1: Fix authorization on user profile routes | CRITICAL | 1 hour |
| 2 | C2: Fix PreviewWriteInterceptor route mismatch | CRITICAL | 30 min |
| 3 | C9: Fix division by zero in FutureValueCalculator | CRITICAL | 30 min |
| 4 | H13: Fix findOrFail information leakage | HIGH | 2 hours |
| 5 | H18: Fix webhook bypass in local environment | HIGH | 1 hour |

### Phase 2: Short Term (1-2 Weeks)
| # | Issue | Priority | Effort |
|---|-------|----------|--------|
| 6 | C3: Migrate payment/subscription to decimal | CRITICAL | 2 hours |
| 7 | C5: Add FK constraints to all joint_owner_id columns | CRITICAL | 2 hours |
| 8 | C10: Add env variable fallbacks/validation | CRITICAL | 1 hour |
| 9 | C12: Standardise model casts to decimal:2 | CRITICAL | 3 hours |
| 10 | H1: Fix N+1 queries in estate calculations | HIGH | 2 hours |
| 11 | H5: Add missing cache invalidation methods | HIGH | 2 hours |
| 12 | H16: Add pagination to list endpoints | HIGH | 3 hours |
| 13 | H17: Tighten rate limiting | HIGH | 1 hour |

### Phase 3: Medium Term (2-4 Weeks)
| # | Issue | Priority | Effort |
|---|-------|----------|--------|
| 14 | C4: Add SoftDeletes to 18 financial models | CRITICAL | 4 hours |
| 15 | C11: Move hardcoded values to config | HIGH | 2 hours |
| 16 | H2: Standardise error handling across controllers | HIGH | 4 hours |
| 17 | H3+H4: Consolidate duplicated calculations | HIGH | 4 hours |
| 18 | H6: Standardise cache strategies | HIGH | 4 hours |
| 19 | H8: Fix hardcoded dates in tests | HIGH | 3 hours |
| 20 | H9: Add missing database indexes | HIGH | 2 hours |
| 21 | H11: Add Vue 3 emit declarations | HIGH | 6 hours |

### Phase 4: Long Term (1-2 Months)
| # | Issue | Priority | Effort |
|---|-------|----------|--------|
| 22 | C7: Refactor 3 large services (SRP) | CRITICAL | 2 weeks |
| 23 | C8: Increase test coverage to 70%+ | HIGH | 3 weeks |
| 24 | H7+H21: Extract business logic to services | HIGH | 1 week |
| 25 | H12: Decompose 6 large Vue components | MEDIUM | 1 week |
| 26 | H15: Create missing model factories | MEDIUM | 1 week |
| 27 | H20: Add query scopes to models | MEDIUM | 3 days |

---

## Appendix: Files Requiring Immediate Attention

### Critical Files
| File | Issues |
|------|--------|
| `routes/api.php` | C1 (auth bypass), route naming |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | C2 (route mismatch) |
| `database/migrations/*_create_payments_table.php` | C3 (integer currency) |
| `app/Services/Estate/FutureValueCalculator.php` | C9 (div by zero) |
| `config/services.php` | C10 (missing fallbacks) |
| `app/Http/Controllers/Api/MortgageController.php` | C11 (hardcoded values) |

### High Priority Files
| File | Issues |
|------|--------|
| `app/Services/Estate/IHTCalculationService.php` | C7 (SRP), H1 (N+1), H3/H4 (duplication) |
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | C7 (SRP), H5 (no cache invalidation) |
| `app/Http/Controllers/Api/RetirementController.php` | H13 (info leak) |
| `app/Http/Controllers/Api/PropertyController.php` | H16 (no pagination) |
| `app/Http/Controllers/Api/PaymentWebhookController.php` | H18 (verification bypass) |
| `app/Http/Controllers/Api/InvestmentController.php` | H7 (logic in controller) |

---

*Report generated: 21 February 2026*
*Total issues identified: 12 Critical, 23 High, 12 Medium, 7 Low*
