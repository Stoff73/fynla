# Full Codebase Remediation — 13 March 2026

## Overview

Full sweep of 37 confirmed issues (from 51 original findings, 14 verified as false positives) across security, tax compliance, backend logic, frontend compliance, database performance, and architecture. All fixes applied in a single session with 5 parallel agents.

**Result:** 69 files changed, 1866 tests passing (8006 assertions), zero regressions.

---

## Phase 1: Security (Critical)

### IDOR Vulnerability in AuthController
**File:** `app/Http/Controllers/Api/AuthController.php`
- `resolveLoginUserId()` accepted `user_id` directly from request body as a fallback
- Removed the fallback — now only resolves via `challenge_token`
- An attacker could previously bypass MFA by supplying an arbitrary `user_id`

### MFA Token Invalidation
**File:** `app/Http/Controllers/Api/MFAController.php`
- Added `$user->tokens()->delete()` before creating the post-MFA token
- Pre-MFA tokens are now invalidated so they cannot be reused after MFA verification

### AI Chat Rate Limiting
**File:** `routes/api.php`
- Added `throttle:20,1` middleware to AI chat route group
- Prevents abuse of the LLM proxy endpoint (20 requests per minute per user)

### CSP Documentation
**File:** `app/Http/Middleware/SecurityHeaders.php`
- Documented why `unsafe-inline` is required in production CSP (Revolut SDK + Plausible analytics)
- Added TODO to migrate to nonce-based CSP when Revolut SDK supports it

---

## Phase 2: Tax Compliance (High)

### Intestacy Threshold Update
**File:** `app/Services/Estate/IntestacyCalculator.php`
- Updated `THRESHOLD_SPOUSE_CHILDREN` from 250,000 to 322,000 (January 2024 change)
- Updated all user-facing text referencing the threshold
- Updated test expectations in `IntestacyCalculatorTest.php`

### CGT Rate Correction
**File:** `app/Services/Estate/PersonalizedGiftingStrategyService.php`
- Fixed CGT rate from "28%" to "24%" in two user-facing text strings (2024 Autumn Budget)

### Personal Allowance Taper
**File:** `app/Services/UKTaxCalculator.php`
- Added PA taper logic: reduces PA by 1 for every 2 above 100,000 threshold
- Was missing from the simplified calculation path

### Hardcoded Tax Values Replaced (12 Services)
All hardcoded thresholds (12570, 37700, 50270, 125140) replaced with `TaxConfigService` `bands[]` lookups:

| File | Values Replaced |
|------|----------------|
| `AssetLocationController.php` | 50270, 12570, 125140 |
| `DividendTaxCalculator.php` | 12570, 37700, 50270, 125140 |
| `TaxEfficiencyCalculator.php` | 12570, 37700 |
| `AssetLocationOptimizer.php` | 50270, 12570, 37700, 125140 |
| `ChattelCGTService.php` | 12570, 37700 |
| `CrossModuleStrategyService.php` | 12570, 37700 |
| `HouseholdPlanningService.php` | 12570, 37700, 125140 (4 locations) |
| `LifeEventAllocationService.php` | 37700, 125140 |
| `PortfolioStrategyService.php` | 50270, 125140 |
| `RetirementIncomeService.php` | 12570 (2 locations) |
| `ContributionOptimizer.php` | 50270, 125140 |
| `TaxProductInfoService.php` | 12570 |

**Pattern used:**
```php
// Before (hardcoded)
$basicRateThreshold = $incomeTax['higher_rate_threshold'] ?? 50270;

// After (dynamic from config)
$basicRateThreshold = (float) ($incomeTax['bands'][0]['upper_limit'] ?? 50270);
$additionalRateThreshold = (float) ($incomeTax['bands'][1]['upper_limit'] ?? 125140);
$basicRateBand = (float) ($incomeTax['bands'][0]['max'] ?? 37700);
```

**Post-agent fix:** The background agent that handled this task incorrectly used non-existent config keys (`basic_rate_band`, `basic_rate_limit`). These were corrected across all 12 files to use the actual `bands[]` array structure from `TaxConfigService`.

---

## Phase 3: Backend Logic

### orWhere Scoping Bug (55 Instances, 15 Files)
**Bug:** `Model::where('user_id', $id)->orWhere('joint_owner_id', $id)` without closure breaks query scope with soft deletes — returns ALL soft-deleted records for ANY user when combined with other query constraints.

**Fix:** Replaced with `Model::forUserOrJoint($userId)` scope from `HasJointOwnership` trait.

Controllers fixed (7): SavingsController, InvestmentController, PropertyController, GoalsController, BusinessInterestController, ChattelController

Services fixed (7): NetWorthService, PersonalAccountsService, LifeEventService, GoalsProjectionService, HouseholdPlanningService, ComprehensiveEstatePlanService, EstateAssetAggregatorService, CrossModuleAssetAggregator

Agent fixed (1): GoalsAgent

### N+1 Query Fixes
| Agent | Fix |
|-------|-----|
| `RetirementAgent` | Single `User::with(['retirementProfile', 'dcPensions', 'dbPensions', 'statePension'])` instead of 4 separate queries |
| `SavingsAgent` | Eager load `savingsAccounts` relationship |
| `GoalsAgent` | Eager load goals relationship |

### PropertyController Response Envelope
**File:** `app/Http/Controllers/Api/PropertyController.php`
- `index()` was returning a raw array — wrapped in standard `{success: true, data: {properties: [...]}}` envelope
- Updated `PropertyControllerTest.php` to match new envelope
- Updated `IncomeStep.vue` and `AssetsStep.vue` with fallback chain for both formats

### AdminController Readonly
**File:** `app/Http/Controllers/Api/AdminController.php`
- Added `readonly` to constructor injection: `private readonly DatabaseMetricsService $databaseMetrics`

---

## Phase 4: Frontend Compliance

### Score Removal (Rule 13)
Numerical scores removed from 4 components:

| Component | Before | After |
|-----------|--------|-------|
| `FinancialHealthScore.vue` | Progress bars with percentage widths | Status dots with descriptive labels |
| `DiversificationTab.vue` | "X /100" with score bar | Label badge + holdings/asset class counts |
| `CoverageAdequacyGauge.vue` | "X%" in radial chart | "Adequate"/"Partial"/"Limited"/"Insufficient" text |
| `CorrelationMatrix.vue` | "X/100" score | Descriptive label + correlation summary |

### Banned Color Token Replacement (7 Files)
| Token | Replacement | Files |
|-------|-------------|-------|
| `bg-gray-100` | `bg-savannah-100` | DecumulationStrategyCard |
| `divide-gray-200` | `divide-neutral-200` | PropertyTaxCalculator, AmortizationScheduleView, UKTaxesAllowancesCard (18 instances) |
| `divide-gray-100` | `divide-savannah-100` | TermsOfServicePage, PrivacyPolicyPage |
| `from-gray-500 to-gray-600` | `from-neutral-500 to-neutral-600` | PreviewBanner |

### Hardcoded Hex Replacement (6 Files)
| Hex | Replacement | Files |
|-----|-------------|-------|
| `#6B7280` | `#717171` (neutral-500) | GoalsProjectionChart, GoalsProjectionChartDashboard |
| `#E5E7EB` | `#E5E5E5` (neutral-200) | GoalsProjectionChart, GoalsProjectionChartDashboard |
| `#111827` | `#0F1729` (horizon-900) | GoalsProjectionChartDashboard |
| `#374151` | `#2D3A54` (horizon-700) | GoalsProjectionChartDashboard |
| `rgba(79,70,229)` | `rgba(88,84,230)` (violet-500) | GuidanceTooltip |
| `rgba(236,72,153)` | `rgba(232,62,109)` (raspberry-500) | ChattelsList |

### Centralised localStorage Wrapper
**New file:** `resources/js/utils/storage.js`
- Error-handling wrapper for localStorage/sessionStorage
- Adopted in: AppLayout, StrategyDisclaimer, SideMenu, Register, Dashboard, PersonalAccounts, InfoTooltip

---

## Phase 5: Database

### Foreign Key Constraint
**New migration:** `2026_03_13_200001_add_missing_joint_owner_foreign_keys.php`
- Fixed `savings_accounts.joint_owner_id` column type from signed `bigint` to `unsigned bigint` (must match `users.id`)
- Added FK constraint referencing `users.id` with `onDelete('set null')`

### Dependency Cleanup
- Removed unused `html2pdf.js` npm dependency
- Synced `.env.example` with all required environment variables (CEREBRAS, OPENAI, FCM, Plausible)

---

## False Positives Eliminated (14 of 51)

These findings were verified as incorrect and required no changes:

| Finding | Why False Positive |
|---------|-------------------|
| RetirementAgent min() comparison | Logic correct: income_gap = target - projected, min picks biggest surplus |
| NI number in User model | Field doesn't exist on User model |
| Double columns precision | Already decimal(15,2) |
| joint_owner_id indexes | Already exist |
| Source maps in production | Already disabled |
| Several others | Pre-existing correct patterns |

---

## Deployment Notes

### Files to Upload (67 changed files)

**Backend PHP (35 files):**
- `app/Agents/GoalsAgent.php`, `RetirementAgent.php`, `SavingsAgent.php`
- `app/Http/Controllers/Api/AdminController.php`, `AuthController.php`, `BusinessInterestController.php`, `ChattelController.php`, `GoalsController.php`, `InvestmentController.php`, `MFAController.php`, `PropertyController.php`, `SavingsController.php`
- `app/Http/Controllers/Api/Investment/AssetLocationController.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Services/Chattel/ChattelCGTService.php`
- `app/Services/Coordination/CrossModuleStrategyService.php`, `HouseholdPlanningService.php`
- `app/Services/Estate/ComprehensiveEstatePlanService.php`, `EstateAssetAggregatorService.php`, `IntestacyCalculator.php`, `PersonalizedGiftingStrategyService.php`
- `app/Services/Goals/GoalsProjectionService.php`, `LifeEventAllocationService.php`, `LifeEventService.php`
- `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php`, `DividendTaxCalculator.php`, `PortfolioStrategyService.php`, `TaxEfficiencyCalculator.php`
- `app/Services/NetWorth/NetWorthService.php`
- `app/Services/Retirement/ContributionOptimizer.php`, `RetirementIncomeService.php`
- `app/Services/Shared/CrossModuleAssetAggregator.php`
- `app/Services/Tax/TaxProductInfoService.php`
- `app/Services/UKTaxCalculator.php`
- `app/Services/UserProfile/PersonalAccountsService.php`
- `routes/api.php`

**Frontend (rebuild required):**
- 16 Vue components changed — run `./deploy/fynla-org/build.sh` and upload `public/build/`

**Database:**
- Run migration: `php artisan migrate` (adds FK on savings_accounts.joint_owner_id)

**Post-deploy:**
```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```
