# Full Codebase Remediation — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all verified security, tax compliance, frontend, database, and architecture issues identified in the full codebase review.

**Architecture:** 6 phases executed in dependency order. Phases 1-2 sequential (overlapping files). Phases 3-4 parallel (no overlap). Phase 5 after 1-3. Phase 6 last.

**Tech Stack:** Laravel 10, Vue.js 3, MySQL 8, Tailwind CSS, Pest testing framework

**Revised Scope:** Research agents verified 51 original findings. 14 were false positives (already compliant). **37 real issues remain across 80+ files.**

**False Positives Eliminated:**
- Fix #3 RetirementAgent comparison: Logic is correct (picks most negative gap = biggest surplus)
- Fix #8 NI encryption: Field doesn't exist on User model
- Fix #14 double columns: Already decimal(15,2)
- Fix #16 Duplicate PortfolioAnalyzer: Only one exists
- Fix #19 Router guards: Already using `to.matched.some()` for requiresAuth
- Fix #31 Indexes on joint_owner_id: Already indexed (migration exists)
- Fix #35 Local currencyMixin: All components use the mixin correctly
- Fix #36 Vuex error handling: All actions have try/catch
- Fix #37 Source maps: Already disabled in vite.config.js
- Fix #44 Webhook CSRF: Already HMAC signature-verified
- Fix #49 $refs: All usage is appropriate DOM operations
- Fix #33 Constructor readonly: Only 1 controller affected (AdminController), not 33+

---

## Chunk 1: Phase 1 — Security & Data Safety

### Task 1: Fix orWhere Joint Ownership Scoping (87 instances, 27 files)

**Problem:** Inline `->orWhere('joint_owner_id', $userId)` without closure wrapper breaks query scoping with soft deletes and additional conditions.

**Solution:** All 10 models with `joint_owner_id` already use the `HasJointOwnership` trait which provides `scopeForUserOrJoint($userId)`. Replace all inline patterns with the scope method.

**Models with HasJointOwnership trait:** Property, Mortgage, SavingsAccount, InvestmentAccount, Goal, LifeEvent, BusinessInterest, Chattel, CashAccount, Liability

**Pattern:**
```php
// BEFORE (broken — orWhere escapes scope)
$items = Model::where('user_id', $userId)
    ->orWhere('joint_owner_id', $userId)
    ->get();

// AFTER (fixed — uses trait scope)
$items = Model::forUserOrJoint($userId)->get();

// OR if additional conditions exist:
$items = Model::forUserOrJoint($userId)
    ->where('status', 'active')
    ->get();
```

**Files to fix (grouped by directory):**

**Controllers (11 files, 31 instances):**
- `app/Http/Controllers/Api/SavingsController.php` — lines 61, 294, 454
- `app/Http/Controllers/Api/InvestmentController.php` — lines 76, 869, 920
- `app/Http/Controllers/Api/PropertyController.php` — lines 56, 176, 354, 384, 443
- `app/Http/Controllers/Api/MortgageController.php` — lines 51, 181, 318
- `app/Http/Controllers/Api/GoalsController.php` — lines 53, 180, 333, 386, 443, 498, 561
- `app/Http/Controllers/Api/BusinessInterestController.php` — lines 52, 137, 318, 350
- `app/Http/Controllers/Api/ChattelController.php` — lines 52, 113, 230
- `app/Http/Controllers/Api/LifeEventController.php` — line 98
- `app/Http/Controllers/Api/LifeEventAllocationController.php` — line 142
- `app/Http/Controllers/Api/JointAccountLogController.php` — line 32

**Services (14 files, 54 instances):**
- `app/Services/NetWorth/NetWorthService.php` — lines 102, 124, 281, 286, 384, 403
- `app/Services/UserProfile/UserProfileService.php` — lines 182, 690, 808, 866, 1023
- `app/Services/UserProfile/PersonalAccountsService.php` — lines 235, 252, 269, 290, 307, 337, 366
- `app/Services/Plans/BasePlanService.php` — line 42
- `app/Services/Plans/GoalPlanService.php` — lines 36, 109
- `app/Services/Goals/LifeEventService.php` — line 26
- `app/Services/Goals/GoalStrategyService.php` — lines 37, 72
- `app/Services/Goals/GoalsProjectionService.php` — line 485
- `app/Services/Coordination/HouseholdPlanningService.php` — lines 388, 394, 400, 410, 416, 422, 454, 460, 862
- `app/Services/Estate/ComprehensiveEstatePlanService.php` — lines 526, 548
- `app/Services/Estate/EstateAssetAggregatorService.php` — lines 50, 68, 87, 105, 142, 214, 226, 241, 253
- `app/Services/Estate/IHTFormattingService.php` — lines 199, 227
- `app/Services/Estate/LetterEstateValidationService.php` — lines 158, 183
- `app/Services/Shared/CrossModuleAssetAggregator.php` — lines 77, 108, 140, 184, 198, 212, 225, 238, 253, 259, 265, 271

**Agents (1 file, 2 instances):**
- `app/Agents/GoalsAgent.php` — lines 36, 276

**Steps:**

- [ ] **Step 1: Fix controllers (11 files)**
  Replace all `->where('user_id', $userId)->orWhere('joint_owner_id', $userId)` with `->forUserOrJoint($userId)` in each controller. Where the orWhere appears inside an existing closure (like PropertyController show() at line 174), verify it's already correct and skip.

  Run: `./vendor/bin/pest tests/Feature/` after each file group

- [ ] **Step 2: Fix services (14 files)**
  Same pattern replacement in all service files. For CrossModuleAssetAggregator (12 instances) and HouseholdPlanningService (9 instances), be especially careful — these have the most instances.

  For services that query models NOT in the model list (e.g., querying raw DB), use closure wrapper:
  ```php
  ->where(function ($q) use ($userId) {
      $q->where('user_id', $userId)
        ->orWhere('joint_owner_id', $userId);
  })
  ```

- [ ] **Step 3: Fix agents (1 file)**
  GoalsAgent lines 36 and 276.

- [ ] **Step 4: Run full test suite**
  Run: `./vendor/bin/pest`
  Expected: All tests pass (no regressions from scoping changes)

- [ ] **Step 5: Commit**
  ```bash
  git add app/Http/Controllers/Api/ app/Services/ app/Agents/GoalsAgent.php
  git commit -m "fix: wrap orWhere joint_owner_id in closure scope across 27 files

  Prevents query scope leakage with soft deletes and compound conditions.
  Uses HasJointOwnership::forUserOrJoint() scope on all 10 joint-ownable models."
  ```

---

### Task 2: Fix IDOR in AuthController

**File:** `app/Http/Controllers/Api/AuthController.php`

- [ ] **Step 1: Read the method**
  Read lines 750-765 of AuthController.php

- [ ] **Step 2: Remove the user_id fallback**
  Replace lines 762-763:
  ```php
  // BEFORE
  // Fall back to direct user_id for backwards compatibility
  return $request->user_id ? (int) $request->user_id : null;

  // AFTER
  return null;
  ```

- [ ] **Step 3: Run auth tests**
  Run: `./vendor/bin/pest tests/Feature/ --filter=Auth`

- [ ] **Step 4: Commit**
  ```bash
  git add app/Http/Controllers/Api/AuthController.php
  git commit -m "fix(security): remove IDOR user_id fallback in resolveLoginUserId

  Client-supplied user_id allowed attackers to target arbitrary accounts.
  Now only challenge_token-based resolution is accepted."
  ```

---

### Task 3: Tighten CSP — Remove unsafe-inline

**File:** `app/Http/Middleware/SecurityHeaders.php`

**Note:** `unsafe-inline` for scripts is needed by Vue.js in development (Vite injects inline scripts). For production, we can use `unsafe-inline` for styles only (required by Tailwind/inline styles) but should add `'unsafe-eval'` removal check. Actually, removing `unsafe-inline` from script-src in production would break Revolut's embedded checkout and Plausible analytics which inject inline scripts. **This fix should only tighten, not break functionality.** The practical fix is to add `'strict-dynamic'` alongside `'unsafe-inline'` for production, which tells modern browsers to ignore `unsafe-inline` when `strict-dynamic` is present.

- [ ] **Step 1: Update production CSP**
  In SecurityHeaders.php line 46, for the production CSP string, keep `unsafe-inline` (needed for Revolut/Plausible compatibility) but add nonce support preparation comment and tighten style-src:
  ```php
  // Production CSP — unsafe-inline kept for Revolut SDK and Plausible compatibility
  // TODO: Migrate to nonce-based CSP when Revolut SDK supports it
  ```

  This is a documentation-only change acknowledging the limitation. Removing unsafe-inline would break payments.

- [ ] **Step 2: Commit**

---

### Task 4: Add Rate Limiter to AI Chat Routes

**File:** `routes/api.php`

- [ ] **Step 1: Add throttle middleware**
  At line 1063, add throttle to the AI chat route group:
  ```php
  // BEFORE
  Route::middleware('auth:sanctum')->prefix('ai-chat')->group(function () {

  // AFTER
  Route::middleware(['auth:sanctum', 'throttle:20,1'])->prefix('ai-chat')->group(function () {
  ```

- [ ] **Step 2: Commit**
  ```bash
  git add routes/api.php
  git commit -m "fix(security): add rate limit to AI chat routes (20/min)"
  ```

---

### Task 5: Invalidate Pre-MFA Tokens After Verification

**File:** `app/Http/Controllers/Api/MFAController.php`

- [ ] **Step 1: Add token cleanup before creating new token**
  At line 200 (after "MFA verified" comment, before creating token), add:
  ```php
  // Invalidate any existing tokens (pre-MFA tokens should not remain valid)
  $user->tokens()->delete();
  ```

- [ ] **Step 2: Run MFA tests**
  Run: `./vendor/bin/pest --filter=MFA`

- [ ] **Step 3: Commit**
  ```bash
  git add app/Http/Controllers/Api/MFAController.php
  git commit -m "fix(security): invalidate pre-MFA tokens after verification

  Existing tokens are now revoked before issuing the post-MFA token,
  preventing pre-MFA tokens from being reused."
  ```

---

## Chunk 2: Phase 2 — Tax Compliance

### Task 6: Replace Hardcoded Tax Values in PHP Services (12 files)

**Problem:** 35+ instances of hardcoded tax thresholds across 12 PHP services. Values like `12570` (PA), `37700` (basic rate band), `50270` (higher rate threshold), `125140` (additional rate threshold) should come from `TaxConfigService`.

**Pattern for each service:**

1. Inject `TaxConfigService` via constructor if not already injected
2. Replace hardcoded values with lookups:
   ```php
   // Tax value lookups
   $incomeTax = $this->taxConfig->getIncomeTax();
   $personalAllowance = $incomeTax['personal_allowance'];      // replaces 12570
   $basicRateBand = $incomeTax['basic_rate_band'];              // replaces 37700
   $higherRateThreshold = $personalAllowance + $basicRateBand;  // replaces 50270
   $additionalRateThreshold = $incomeTax['additional_rate_threshold']; // replaces 125140
   ```

**Files and specific changes:**

- [ ] **Step 1: Fix AssetLocationController.php** (4 values: lines 280, 300, 302, 304)
  `app/Http/Controllers/Api/Investment/AssetLocationController.php`
  Inject TaxConfigService, replace 50270, 12570, 50270, 125140.

- [ ] **Step 2: Fix DividendTaxCalculator.php** (4 values: lines 34-37)
  `app/Services/Investment/DividendTaxCalculator.php`
  Replace 12570, 37700, 50270, 125140 with TaxConfigService lookups.

- [ ] **Step 3: Fix TaxEfficiencyCalculator.php** (2 values: lines 78-79)
  `app/Services/Investment/TaxEfficiencyCalculator.php`
  Replace 12570, 37700.

- [ ] **Step 4: Fix AssetLocationOptimizer.php** (4 values: lines 97, 134-136)
  `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php`
  Replace 50270, 12570, 37700, 125140.

- [ ] **Step 5: Fix ChattelCGTService.php** (2 values: lines 186-187)
  `app/Services/Chattel/ChattelCGTService.php`
  Replace 12570, 37700.

- [ ] **Step 6: Fix CrossModuleStrategyService.php** (2 values: line 147)
  `app/Services/Coordination/CrossModuleStrategyService.php`
  Replace 12570, 37700.

- [ ] **Step 7: Fix HouseholdPlanningService.php** (6 values: lines 172, 497, 500, 649, 686, 689)
  `app/Services/Coordination/HouseholdPlanningService.php`
  Replace 12570, 37700 (×4), 125140.

- [ ] **Step 8: Fix LifeEventAllocationService.php** (2 values: lines 608, 619)
  `app/Services/Goals/LifeEventAllocationService.php`
  Replace 37700, 125140.

- [ ] **Step 9: Fix PortfolioStrategyService.php** (2 values: lines 547-548)
  `app/Services/Investment/PortfolioStrategyService.php`
  Replace 50270, 125140.

- [ ] **Step 10: Fix RetirementIncomeService.php** (2 values: lines 962, 1447)
  `app/Services/Retirement/RetirementIncomeService.php`
  Replace 12570 (×2).

- [ ] **Step 11: Fix ContributionOptimizer.php** (3 values: lines 243-244, 273)
  `app/Services/Retirement/ContributionOptimizer.php`
  Replace 50270 (×2), 125140.

- [ ] **Step 12: Fix TaxProductInfoService.php** (1 value: line 152)
  `app/Services/Tax/TaxProductInfoService.php`
  Replace 12570.

- [ ] **Step 13: Run tests**
  Run: `./vendor/bin/pest`
  Expected: All pass (TaxConfigService returns same values, just from config instead of hardcoded)

- [ ] **Step 14: Commit**
  ```bash
  git add app/Http/Controllers/Api/Investment/AssetLocationController.php \
    app/Services/Investment/DividendTaxCalculator.php \
    app/Services/Investment/TaxEfficiencyCalculator.php \
    app/Services/Investment/AssetLocation/AssetLocationOptimizer.php \
    app/Services/Chattel/ChattelCGTService.php \
    app/Services/Coordination/CrossModuleStrategyService.php \
    app/Services/Coordination/HouseholdPlanningService.php \
    app/Services/Goals/LifeEventAllocationService.php \
    app/Services/Investment/PortfolioStrategyService.php \
    app/Services/Retirement/RetirementIncomeService.php \
    app/Services/Retirement/ContributionOptimizer.php \
    app/Services/Tax/TaxProductInfoService.php
  git commit -m "fix(tax): replace 35 hardcoded tax thresholds with TaxConfigService

  Replaces hardcoded PA (12570), basic rate band (37700), higher rate
  threshold (50270), and additional rate threshold (125140) across 12
  services with TaxConfigService lookups."
  ```

---

### Task 7: Verify and Fix CGT Rate and AEA in TaxConfigurationSeeder

**Files:**
- `database/seeders/TaxConfigurationSeeder.php`
- Services referencing CGT rates

- [ ] **Step 1: Check TaxConfigurationSeeder for CGT values**
  Verify the 2025/26 tax year has:
  - `capital_gains_tax.higher_rate` = 24 (not 28)
  - `capital_gains_tax.annual_exempt_amount` = 3000 (not 6000)

  If incorrect, update the seeder.

- [ ] **Step 2: Search for hardcoded CGT rates**
  Search for `0.28`, `0.24`, `0.10`, `0.18`, `0.20` near CGT context and replace with TaxConfigService lookups.

- [ ] **Step 3: Run seeder and tests**
  ```bash
  php artisan db:seed --class=TaxConfigurationSeeder --force
  ./vendor/bin/pest tests/Unit/Services/Estate/ tests/Unit/Services/Investment/
  ```

- [ ] **Step 4: Commit**

---

### Task 8: Add PA Taper to UKTaxCalculator Simplified Path

**File:** `app/Services/UKTaxCalculator.php`

- [ ] **Step 1: Read the simplified calculation method**
  Find the simplified tax calculation path that doesn't apply PA taper for incomes >£100,000.

- [ ] **Step 2: Add PA taper logic**
  ```php
  $pa = $this->taxConfig->get('income_tax.personal_allowance');
  $taperThreshold = $this->taxConfig->get('income_tax.personal_allowance_taper_threshold') ?? 100000;

  if ($grossIncome > $taperThreshold) {
      $excess = $grossIncome - $taperThreshold;
      $reduction = floor($excess / 2);
      $pa = max(0, $pa - $reduction);
  }
  ```

- [ ] **Step 3: Write test for PA taper**
  ```php
  it('applies personal allowance taper for income above £100,000', function () {
      // Income of £120,000: PA reduced by (120000-100000)/2 = £10,000
      // Remaining PA: £12,570 - £10,000 = £2,570
      $result = $this->calculator->calculateIncomeTax(120000);
      // Verify PA applied is £2,570, not full £12,570
  });
  ```

- [ ] **Step 4: Run tests**
- [ ] **Step 5: Commit**

---

### Task 9: Fix Frontend Tax Constants

**File:** `resources/js/constants/taxConfig.js`

- [ ] **Step 1: Add clear documentation that these are fallback values**
  ```javascript
  /**
   * UK Tax Constants — FALLBACK VALUES ONLY
   *
   * These values are used as client-side fallbacks when the API
   * tax configuration endpoint is unavailable. The backend
   * TaxConfigService is the source of truth.
   *
   * Tax Year: 2025/26 (April 2025 - April 2026)
   */
  ```

- [ ] **Step 2: Verify CGT_ANNUAL_ALLOWANCE is 3000** (line 49)
  If it says 6000, change to 3000.

- [ ] **Step 3: Commit**

---

### Task 10: Update IHT Hardcoded Values in Vue Components

**Files:**
- `resources/js/components/Estate/NRBRNRBTracker.vue`
- `resources/js/components/Estate/IHTPlanning.vue`

- [ ] **Step 1: Replace hardcoded NRB/RNRB with prop-based or API-fetched values**
  These components hardcode `325000`, `175000`, `2000000`. They should receive these from the backend via props or Vuex, with the hardcoded values as documented fallbacks.

- [ ] **Step 2: Commit**

---

### Task 11: Update Tax Band Data in Dashboard Components

**Files:**
- `resources/js/views/UKTaxes/UKTaxesDashboard.vue` (lines 559-563)
- `resources/js/components/Dashboard/UKTaxesAllowancesCard.vue` (lines 799-803)

- [ ] **Step 1: Replace hardcoded tax band data**
  Replace hardcoded `37700`, `125140` with values from the tax config API response or clearly marked fallback constants from `taxConfig.js`.

- [ ] **Step 2: Commit**

---

## Chunk 3: Phase 3 — Backend Logic

### Task 12: Fix PropertyController index() Response Envelope

**File:** `app/Http/Controllers/Api/PropertyController.php`

- [ ] **Step 1: Wrap index() response in standard envelope**
  Line 89 currently returns:
  ```php
  return response()->json($properties);
  ```

  Change to:
  ```php
  return response()->json([
      'success' => true,
      'data' => $properties,
  ]);
  ```

- [ ] **Step 2: Check if frontend expects the raw array**
  Search for PropertyController/properties API calls in Vue files to verify the frontend destructures `response.data` vs `response.data.data`.

- [ ] **Step 3: Update frontend if needed**
  If frontend expects `response.data` as the array, update to `response.data.data`.

- [ ] **Step 4: Run tests**
  Run: `./vendor/bin/pest --filter=Property`

- [ ] **Step 5: Commit**
  ```bash
  git add app/Http/Controllers/Api/PropertyController.php
  git commit -m "fix: wrap PropertyController index() in standard response envelope"
  ```

---

### Task 13: Add readonly to AdminController Constructor

**File:** `app/Http/Controllers/Api/AdminController.php`

- [ ] **Step 1: Add readonly keyword**
  Line 24: Change `private DatabaseMetricsService` to `private readonly DatabaseMetricsService`

- [ ] **Step 2: Commit**

---

## Chunk 4: Phase 4 — Frontend Compliance

### Task 14: Remove Score Displays (Rule 13)

**Files:**
- `resources/js/components/Dashboard/FinancialHealthScore.vue` — composite score 0-100
- `resources/js/components/Protection/CoverageAdequacyGauge.vue` — adequacy score /100
- `resources/js/components/Investment/DiversificationTab.vue` — diversification score /100
- `resources/js/components/Investment/CorrelationMatrix.vue` — diversification score /100

- [ ] **Step 1: Read each component to understand what score data is available**

- [ ] **Step 2: Replace FinancialHealthScore.vue**
  Replace numerical score with descriptive summary text. Instead of "75/100 Good Financial Health", show:
  - Key metrics: total assets, total protection coverage, retirement progress
  - Descriptive text: "Your finances are well-structured with strong protection coverage and growing retirement savings"

- [ ] **Step 3: Replace CoverageAdequacyGauge.vue**
  Replace "/100 adequacy score" with descriptive coverage status:
  - "Adequate coverage" / "Coverage gap identified" with specific currency amounts

- [ ] **Step 4: Replace DiversificationTab.vue and CorrelationMatrix.vue**
  Replace "Diversification Score /100" with:
  - Portfolio allocation breakdown (% domestic, international, bonds, etc.)
  - Descriptive label only (no number): "Well Diversified" / "Concentrated"

- [ ] **Step 5: Check for parent components that pass score data**
  Verify no other components depend on the score number.

- [ ] **Step 6: Run dev server and verify visually**
- [ ] **Step 7: Commit**

---

### Task 15: Fix Hardcoded Hex Colors in Style Blocks

**Files (6):**
- `resources/js/components/Goals/GoalsProjectionChart.vue` — #6B7280, #E5E7EB in tooltip styles
- `resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue` — #111827, #6B7280, #374151, #E5E7EB
- `resources/js/components/Investment/AllocationComparison.vue` — #888, #555 scrollbar
- `resources/js/components/Guidance/GuidanceTooltip.vue` — rgba violet colors
- `resources/js/components/NetWorth/ChattelsList.vue` — rgba pink
- `resources/js/components/Plans/Shared/PlanGoalSection.vue` — rgba indigo border

**Mapping:**
- `#6B7280` / `#888` → `neutral-500`
- `#111827` → `horizon-900`
- `#374151` / `#555` → `horizon-700`
- `#E5E7EB` → `light-gray` or `neutral-200`
- `rgba(79, 70, 229, ...)` → `violet-500` with opacity
- `rgba(236, 72, 153, ...)` → `raspberry-400` with opacity
- `rgba(88, 84, 230, ...)` → `violet-500` with opacity

- [ ] **Step 1: Fix chart tooltip components**
  For GoalsProjectionChart and GoalsProjectionChartDashboard, replace inline style hex with Tailwind class strings or CSS custom properties from the palette.

- [ ] **Step 2: Fix remaining 4 components**
  Replace hex/rgba values with `@apply` directives using palette tokens.

- [ ] **Step 3: Commit**

---

### Task 16: Replace Banned Tailwind Tokens

**Files (10):**
- `resources/js/components/UserProfile/FamilyMembers.vue`
- `resources/js/components/Retirement/DecumulationStrategyCard.vue`
- `resources/js/components/NetWorth/Property/PropertyTaxCalculator.vue`
- `resources/js/components/Estate/EstateOverviewCard.vue`
- `resources/js/views/Public/TermsOfServicePage.vue`
- `resources/js/views/Public/PrivacyPolicyPage.vue`
- `resources/js/components/Trusts/TrustsOverviewCard.vue`
- `resources/js/components/Preview/PreviewBanner.vue`
- `resources/js/components/NetWorth/Property/AmortizationScheduleView.vue`
- `resources/js/components/Dashboard/UKTaxesAllowancesCard.vue`

**Token mapping:**
- `gray-50` → `eggshell-500` or `savannah-50`
- `gray-100` → `savannah-100`
- `gray-200` → `neutral-200` or `light-gray`
- `gray-300` → `neutral-300`
- `gray-400` → `neutral-400`
- `gray-500` → `neutral-500`
- `gray-600` → `horizon-600` or `neutral-600`
- `gray-700` → `horizon-700`
- `gray-800` → `horizon-800`
- `gray-900` → `horizon-900`
- `amber-*` → `violet-*`

- [ ] **Step 1: Read each file and replace banned tokens**
  Replace all `gray-*` tokens with the appropriate palette equivalent per the mapping above.

- [ ] **Step 2: Handle TermsOfServicePage and PrivacyPolicyPage**
  These public pages likely have many gray tokens. Replace systematically.

- [ ] **Step 3: Run dev server and verify no visual regressions**
- [ ] **Step 4: Commit**

---

### Task 17: Create localStorage Wrapper Utility

**New file:** `resources/js/utils/storage.js`
**Files to update (7):** AppLayout.vue, StrategyDisclaimer.vue, Register.vue, Dashboard.vue, PersonalAccounts.vue, SideMenu.vue, InfoTooltip.vue

- [ ] **Step 1: Create storage utility**
  ```javascript
  // resources/js/utils/storage.js
  const storage = {
    get(key, fallback = null) {
      try {
        const value = localStorage.getItem(key);
        return value !== null ? value : fallback;
      } catch {
        return fallback;
      }
    },
    set(key, value) {
      try {
        localStorage.setItem(key, value);
      } catch {
        // Storage full or unavailable
      }
    },
    remove(key) {
      try {
        localStorage.removeItem(key);
      } catch {
        // Ignore
      }
    },
    session: {
      get(key, fallback = null) {
        try {
          const value = sessionStorage.getItem(key);
          return value !== null ? value : fallback;
        } catch {
          return fallback;
        }
      },
      set(key, value) {
        try {
          sessionStorage.setItem(key, value);
        } catch {
          // Ignore
        }
      },
    },
  };
  export default storage;
  ```

- [ ] **Step 2: Update 7 consuming components**
  Import `storage` from `@/utils/storage` and replace direct `localStorage`/`sessionStorage` calls.

- [ ] **Step 3: Commit**

---

## Chunk 5: Phase 5 — Database & Performance

### Task 18: Add Missing FK Constraints

**New migration file:** `database/migrations/2026_03_13_200001_add_missing_joint_owner_foreign_keys.php`

- [ ] **Step 1: Create migration**
  ```php
  public function up(): void
  {
      Schema::table('life_events', function (Blueprint $table) {
          $table->foreign('joint_owner_id')
              ->references('id')->on('users')
              ->onDelete('set null');
      });

      Schema::table('savings_accounts', function (Blueprint $table) {
          // Only add if not already constrained
          $table->foreign('joint_owner_id')
              ->references('id')->on('users')
              ->onDelete('set null');
      });
  }

  public function down(): void
  {
      Schema::table('life_events', function (Blueprint $table) {
          $table->dropForeign(['joint_owner_id']);
      });
      Schema::table('savings_accounts', function (Blueprint $table) {
          $table->dropForeign(['joint_owner_id']);
      });
  }
  ```

- [ ] **Step 2: Run migration locally**
  ```bash
  php artisan migrate
  php artisan db:seed
  ```

- [ ] **Step 3: Run tests**
- [ ] **Step 4: Commit**

---

### Task 19: Fix N+1 Queries in Agents

**Files:**
- `app/Agents/RetirementAgent.php` (lines 68-71) — 4 separate queries
- `app/Agents/SavingsAgent.php` (lines 44-45) — missing eager loads
- `app/Agents/GoalsAgent.php` (lines 35-37) — no eager loads

- [ ] **Step 1: Fix RetirementAgent analyze()**
  Replace 4 separate queries with single eager-loaded query:
  ```php
  $user = User::with(['retirementProfile', 'dcPensions', 'dbPensions', 'statePension'])
      ->findOrFail($userId);
  $profile = $user->retirementProfile;
  $dcPensions = $user->dcPensions;
  // etc.
  ```

- [ ] **Step 2: Fix SavingsAgent analyze()**
  Add `->with(['goals'])` or relevant relationships to SavingsAccount query.

- [ ] **Step 3: Fix GoalsAgent analyze()**
  Add `->with(['linkedAccounts', 'milestones'])` (or whatever relations exist) to Goal query.

- [ ] **Step 4: Run tests**
- [ ] **Step 5: Commit**

---

## Chunk 6: Phase 6 — Architecture & Housekeeping

### Task 20: Sync .env.example

**File:** `.env.example`

- [ ] **Step 1: Add missing environment variables**
  Append after the Analytics section:
  ```env
  VITE_PLAUSIBLE_DOMAIN=

  # Cerebras AI Chat
  CEREBRAS_API_KEY=
  CEREBRAS_CHAT_MODEL=gpt-oss-120b

  # Push Notifications (Firebase Cloud Messaging)
  FCM_PROJECT_ID=
  FCM_PRIVATE_KEY=
  FCM_CLIENT_EMAIL=

  # OpenAI (document extraction service)
  OPENAI_API_KEY=
  ```

- [ ] **Step 2: Commit**

---

### Task 21: Remove Unused html2pdf.js Dependency

- [ ] **Step 1: Remove from package.json**
  ```bash
  npm uninstall html2pdf.js
  ```

- [ ] **Step 2: Verify no imports exist**
  Search for `html2pdf` in all JS/Vue files.

- [ ] **Step 3: Commit**

---

### Task 22: Add Tests for Critical Untested Services

**New test files:**
- `tests/Unit/Services/Estate/IHTCalculationServiceTest.php`
- `tests/Unit/Services/UKTaxCalculatorTest.php`
- `tests/Unit/Services/Retirement/RetirementIncomeServiceTest.php`

- [ ] **Step 1: Write IHT calculation tests**
  Cover: basic NRB calculation, RNRB addition, RNRB taper, spouse exemption, nil-rate band transfer, charity reduction.

- [ ] **Step 2: Write UKTaxCalculator tests**
  Cover: basic rate, higher rate, additional rate, PA taper, dividend tax, CGT with AEA.

- [ ] **Step 3: Write RetirementIncomeService tests**
  Cover: DC pension projection, DB pension valuation, state pension inclusion, total income calculation.

- [ ] **Step 4: Run new tests**
  ```bash
  ./vendor/bin/pest tests/Unit/Services/Estate/IHTCalculationServiceTest.php
  ./vendor/bin/pest tests/Unit/Services/UKTaxCalculatorTest.php
  ./vendor/bin/pest tests/Unit/Services/Retirement/RetirementIncomeServiceTest.php
  ```

- [ ] **Step 5: Commit**

---

### Task 23: Final Verification

- [ ] **Step 1: Full test suite**
  ```bash
  ./vendor/bin/pest
  ```
  Expected: All tests pass

- [ ] **Step 2: Database seed**
  ```bash
  php artisan db:seed
  ```

- [ ] **Step 3: Build web app**
  ```bash
  ./deploy/fynla-org/build.sh
  ```

- [ ] **Step 4: Build mobile app**
  ```bash
  ./deploy/mobile/build-ios.sh
  ```

---

## Execution Strategy

| Phase | Tasks | Agent Assignment | Parallel? |
|-------|-------|-----------------|-----------|
| Phase 1 | Tasks 1-5 | security-focused agents | Sequential (shared files) |
| Phase 2 | Tasks 6-11 | tax-compliance agents | Task 6 steps can be parallel per service |
| Phase 3 | Tasks 12-13 | backend agent | Sequential (small) |
| Phase 4 | Tasks 14-17 | frontend agents | Tasks 14, 15, 16, 17 can be parallel |
| Phase 5 | Tasks 18-19 | database agent | Sequential |
| Phase 6 | Tasks 20-22 | general agents | Tasks 20, 21, 22 can be parallel |
| Final | Task 23 | main session | Sequential |

**Total estimated file changes:** ~80 files
**Total tasks:** 23
**Critical path:** Phase 1 (Task 1 is largest — 27 files)
