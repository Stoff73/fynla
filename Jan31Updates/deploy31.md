# Deployment Notes - January 31, 2026

**Deployment Status:** NOT DEPLOYED

---

## Risk Level Color System - Documentation Update

**Branch:** main

**Status:** Ready for deployment

### Description

Updated the design system documentation (`designStyle.md`) to reflect the new risk level color system. The risk level badges now use a distinct color palette that avoids semantic color conflicts.

### New Risk Level Colors

| Level | Background | Text | Tailwind Classes |
|-------|------------|------|------------------|
| Low | `bg-yellow-100` | `text-yellow-800` | `bg-yellow-100 text-yellow-800` |
| Lower-Medium | `bg-pink-100` | `text-pink-800` | `bg-pink-100 text-pink-800` |
| Medium | `bg-green-100` | `text-green-800` | `bg-green-100 text-green-800` |
| Upper-Medium | `bg-teal-100` | `text-teal-800` | `bg-teal-100 text-teal-800` |
| High | `bg-blue-100` | `text-blue-800` | `bg-blue-100 text-blue-800` |

### Previous Risk Level Colors (Replaced)

| Level | Old Background | Old Text |
|-------|----------------|----------|
| Low | `bg-green-100` | `text-green-800` |
| Lower-Medium | `bg-teal-100` | `text-teal-800` |
| Medium | `bg-blue-100` | `text-blue-800` |
| Upper-Medium | `bg-orange-100` | `text-orange-800` |
| High | `bg-red-100` | `text-red-800` |

### Investment Asset Type Colors Added

New table added to `designStyle.md` mapping asset types to their risk-appropriate colors:

| Asset Type | Risk Level | Color |
|------------|------------|-------|
| Cash & Cash Equivalents | Low | Yellow |
| Bonds (Fixed Income) | Lower-Medium | Pink |
| Commercial Property | Medium | Green |
| Equities (Shares) | Medium-High | Teal |
| Alternative Investments | High | Blue |

### Files Changed (1 file)

**Documentation:**
```text
designStyle.md
```

---

## Orange Color Ban - Design System Update

**Branch:** main

**Status:** Ready for deployment

### Description

Extended the color ban to include orange alongside amber. Updated all warning/caution color references throughout the design system to use blue instead.

### FORBIDDEN Colors Updated

| Color | Status | Replacement |
|-------|--------|-------------|
| Amber (`amber-*`) | BANNED | Use `blue-*` |
| Orange (`orange-*`) | BANNED | Use `blue-*` |

### Warning Semantic Colors Changed

| Element | Before | After |
|---------|--------|-------|
| Warning 50 | `#FFF7ED` (orange-50) | `#EFF6FF` (blue-50) |
| Warning 100 | `#FFEDD5` (orange-100) | `#DBEAFE` (blue-100) |
| Warning 500 | `#F97316` (orange-500) | `#3B82F6` (blue-500) |
| Warning 600 | `#EA580C` (orange-600) | `#2563EB` (blue-600) |
| Warning 700 | `#C2410C` (orange-700) | `#1D4ED8` (blue-700) |

### Other Orange References Updated

| Section | Change |
|---------|--------|
| Card Variants - Warning | `border-orange-200 bg-orange-50` → `border-blue-200 bg-blue-50` |
| Warning box example | `bg-orange-50 text-orange-700` → `bg-blue-50 text-blue-700` |
| Status Badges - Pending | `bg-orange-500` → `bg-blue-500` |
| Alert Severity - Important | `bg-orange-50 border-orange-300` → `bg-blue-50 border-blue-300` |
| Progress Bar - Warning | `bg-orange-500` → `bg-blue-500` |
| Icon Colors - Warning | `text-orange-600` → `text-blue-600` |
| Chart Color 4 | `#EA580C` (Orange) → `#60A5FA` (Blue) |
| Chart Color 7 | `#C2410C` (Orange) → `#3B82F6` (Blue) |
| Gauge Chart Warning | `#F97316` (Orange) → `#3B82F6` (Blue) |
| Color Meaning - Warning | Orange → Blue (light) |

### Files Changed (1 file)

**Documentation:**
```text
designStyle.md
```

---

## Design System Compliance Rule - CLAUDE.md

**Branch:** main

**Status:** Ready for deployment

### Description

Added a new mandatory rule (Rule 10) to CLAUDE.md requiring consultation of the design system before any UI work. This ensures visual consistency across all development.

### New Rule Added

**Rule 10: Design System Compliance**

> **CRITICAL:** Before changing, updating, or implementing anything related to the UI, you MUST read and follow `designStyle.md`. This includes:
> - Colors (especially risk level colors, semantic colors, and forbidden colors)
> - Typography and spacing
> - Component patterns (buttons, cards, forms, modals)
> - Badges and status indicators
> - Charts and data visualisation
>
> The design system is the single source of truth for all visual decisions. Never introduce new colors, spacing values, or component patterns without checking `designStyle.md` first.

### Rule 9 Updated

| Before | After |
|--------|-------|
| "No Amber Color" | "No Amber or Orange Color" |
| Use `blue-*` for warnings | Use `blue-*` for warnings |
| Only amber banned | Both amber and orange banned |

### Files Changed (1 file)

**Documentation:**
```text
CLAUDE.md
```

---

## CLAUDE.md Cleanup - Fixes and Updates

**Branch:** main

**Status:** Ready for deployment

### Description

Fixed several issues in CLAUDE.md including incorrect comments, missing personas, and path format inconsistencies.

### Issues Fixed

| Issue | Location | Fix |
|-------|----------|-----|
| Incorrect command comment | Line 34 | Changed "DESTROYS all data" to "runs pending migrations" - `migrate` alone doesn't destroy data |
| Missing personas | Preview Mode table | Added `young_saver` (John Morgan) and `retired_couple` (Robert & Patricia Williams) |
| Path format | Rule 10 | Changed `/designStyle.md` to `designStyle.md` (removed unnecessary leading slash) |

### Preview Mode Table Updated

| Persona | Users | Focus |
|---------|-------|-------|
| young_family | James & Emily Carter | Mortgage, workplace pensions |
| peak_earners | David & Sarah Mitchell | Multiple properties, SIPP + NHS pension |
| widow | Margaret Thompson | Estate planning |
| entrepreneur | Alex Chen | SIPP, business interests |
| young_saver | John Morgan | Emergency fund, first-time savings |
| retired_couple | Robert & Patricia Williams | Decumulation, estate planning |

### Files Changed (1 file)

**Documentation:**
```text
CLAUDE.md
```

---

## designSystem.js - Risk Level Color Fix

**Branch:** main

**Status:** Ready for deployment

### Description

Fixed the `RISK_TAILWIND_CLASSES` constant in `designSystem.js` which had the `low` risk level using banned orange colors. Updated to use yellow to match the design system documentation.

### Change Made

```javascript
// BEFORE (incorrect - used banned orange)
low: {
  bg: 'bg-orange-100',
  text: 'text-orange-800',
  border: 'border-orange-200',
  combined: 'bg-orange-100 text-orange-800',
}

// AFTER (correct - uses yellow)
low: {
  bg: 'bg-yellow-100',
  text: 'text-yellow-800',
  border: 'border-yellow-200',
  combined: 'bg-yellow-100 text-yellow-800',
}
```

### Files Changed (1 file)

**Frontend (Included in Build):**
```text
resources/js/constants/designSystem.js
```

---

## designSystem.js - Warning Colors Fix

**Branch:** main

**Status:** Ready for deployment

### Description

Fixed the `WARNING_COLORS` constant in `designSystem.js` which used banned orange hex values. Updated to use blue hex values to match the design system documentation.

### Change Made

```javascript
// BEFORE (incorrect - used banned orange)
export const WARNING_COLORS = {
  50: '#FFF7ED',      // orange-50
  100: '#FFEDD5',     // orange-100
  500: '#F97316',     // orange-500
  600: '#EA580C',     // orange-600
  700: '#C2410C',     // orange-700
};

// AFTER (correct - uses blue)
export const WARNING_COLORS = {
  50: '#EFF6FF',      // blue-50
  100: '#DBEAFE',     // blue-100
  500: '#3B82F6',     // blue-500
  600: '#2563EB',     // blue-600
  700: '#1D4ED8',     // blue-700
};
```

### Files Changed (1 file)

**Frontend (Included in Build):**
```text
resources/js/constants/designSystem.js
```

---

## designSystem.js - Complete Orange Ban (Charts, Assets, Spending)

**Branch:** main

**Status:** Ready for deployment

### Description

Removed all remaining orange hex values from `CHART_COLORS`, `ASSET_COLORS`, and `SPENDING_COLORS` constants to complete the orange color ban.

### CHART_COLORS Changes

| Index | Before | After |
|-------|--------|-------|
| Chart 4 | `#EA580C` (Orange) | `#60A5FA` (Blue-400) |
| Chart 7 | `#F97316` (Orange light) | `#3B82F6` (Blue-500) |

### ASSET_COLORS Changes

| Asset Type | Before | After |
|------------|--------|-------|
| cash | `#EA580C` (Orange) | `#60A5FA` (Blue-400) |
| chattels | `#F97316` (Orange light) | `#93C5FD` (Blue-300) |

### SPENDING_COLORS Changes

| Index | Category | Before | After |
|-------|----------|--------|-------|
| 5 | Transport | `#ea580c` (Orange) | `#0284c7` (Sky-600) |
| 9 | Entertainment | `#f97316` (Orange) | `#0ea5e9` (Sky-500) |

### Files Changed (1 file)

**Frontend (Included in Build):**
```text
resources/js/constants/designSystem.js
```

---

## FutureValueTab.vue - Orange Color Removal

**Branch:** main

**Status:** Ready for deployment

### Description

Removed banned orange colors from FutureValueTab.vue styles. The "Target Retirement Income" card now uses teal, and warning/depletion badges use red instead of banned orange.

### Changes Made

| Element | Before | After |
|---------|--------|-------|
| Target Income card | `summary-card.orange` with `from-orange-50 to-orange-100` | `summary-card.teal` with `from-teal-50 to-teal-100` |
| Depletion badge | `bg-orange-100 text-orange-700` | `bg-red-100 text-red-700` |
| Info warning | `bg-orange-100 text-orange-800` | `bg-red-100 text-red-800` |

### Files Changed (1 file)

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/FutureValueTab.vue
```

---

## RetirementIncomeTab.vue - Orange Color Fix

**Branch:** main

**Status:** Ready for deployment

### Description

Fixed `netIncomeClass` computed property which returned banned 'orange' class. Changed to 'yellow' and added missing CSS class definitions for income status color coding.

### Changes Made

| Change | Before | After |
|--------|--------|-------|
| `netIncomeClass()` return value | `'orange'` | `'yellow'` |
| CSS classes | Missing green/yellow/red variants | Added `.summary-card.green`, `.summary-card.yellow`, `.summary-card.red` |

### Color Coding Logic

| Income Status | Color |
|--------------|-------|
| At or above target | Green |
| 90-100% of target | Yellow (was orange) |
| Below 90% of target | Red |

### Files Changed (1 file)

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/RetirementIncomeTab.vue
```

---

## Unified Retirement Income Planner

**Branch:** main

**Status:** Ready for deployment

### Description

Merged the Pension Pot Projection detail view (RequiredCapitalDetail.vue) functionality into the Retirement Income Planner (RetirementIncomeTab.vue). This creates a single unified view for retirement planning with no duplicated information.

### Key Changes

1. **Extended Summary Cards** (6 cards instead of 3)
   - Target Income (editable)
   - Projected Net Income (after tax)
   - Annual Tax
   - Pension Capital at Retirement (80% confidence)
   - Other Assets at Retirement
   - Gap/Surplus (colour-coded)

2. **Other Assets Section** (NEW)
   - Investment accounts with include/exclude toggles
   - Savings/Cash accounts with include/exclude toggles
   - Assets default to NOT included (must be explicitly added)

3. **Progress Section** (from RequiredCapitalDetail)
   - Current Progress bar
   - Forecasted at Retirement Progress bar

4. **Assumptions Panel** (from RequiredCapitalDetail)
   - Return rate, fees, inflation, compounding
   - Link to settings page

5. **Two Tables Preserved**
   - Fund Depletion Table (from RetirementIncomeTab)
   - Year-by-Year Projection Table (from RequiredCapitalDetail)

### Cash Account Bug Fixed

**Problem:** All savings accounts were automatically included in retirement income calculations regardless of user preference.

**Fix:** Added `include_in_retirement` column to `savings_accounts` table with default `false`. Savings accounts now require explicit inclusion like investment accounts.

### Database Migration

New migration: `2026_01_31_120000_add_include_in_retirement_to_savings_accounts.php`

```sql
ALTER TABLE savings_accounts ADD COLUMN include_in_retirement BOOLEAN DEFAULT FALSE;
```

### New API Endpoint

`PATCH /api/savings/accounts/{id}/toggle-retirement` - Toggle include_in_retirement for savings accounts

### Navigation Changes

- Pension Pot Projection chart now navigates to Income tab (was: required-capital)
- Future Value "Required Capital" card now navigates to Income tab
- RequiredCapitalDetail removed from navigation

### Files Changed (10 files)

**Database:**
```text
database/migrations/2026_01_31_120000_add_include_in_retirement_to_savings_accounts.php (NEW)
```

**Backend:**
```text
app/Models/SavingsAccount.php
app/Http/Controllers/Api/SavingsController.php
app/Services/Retirement/RetirementIncomeService.php
routes/api.php
```

**Frontend:**
```text
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/FutureValueTab.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/store/modules/retirement.js
resources/js/services/savingsService.js
```

---

## Rebuild Required: YES

Frontend JavaScript constants changed. Full rebuild required:

```bash
./deploy/fynla-org/build.sh
```

---

## Upload Checklist

### Step 1: Run Build

```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

### Step 2: Upload Built Assets

Upload the entire `public/build/` directory to:

```text
~/www/fynla.org/public_html/public/build/
```

### Step 3: Upload PHP Files

Upload the following PHP files:

```text
app/Models/SavingsAccount.php
app/Http/Controllers/Api/SavingsController.php
app/Http/Controllers/Api/BugReportController.php     (NEW: Bug report feature)
app/Mail/BugReportMail.php                           (NEW: Bug report email)
app/Services/Retirement/RetirementIncomeService.php  (updated: Priority-based withdrawal)
app/Http/Middleware/PreviewWriteInterceptor.php      (updated: Toggle persistence fix)
app/Providers/RouteServiceProvider.php               (updated: Bug report rate limiter)
routes/api.php
```

### Step 3b: Upload Blade Template

```text
resources/views/emails/bug-report.blade.php          (NEW: Bug report email template)
```

### Step 4: Run Migration

```bash
php artisan migrate --force
```

### Step 5: Clear Cache (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

---

## Files Changed Summary

### Documentation (2 files - No upload needed)

```text
CLAUDE.md
designStyle.md
```

### Database (1 file - Migration)

```text
database/migrations/2026_01_31_120000_add_include_in_retirement_to_savings_accounts.php
```

### Backend (8 files - Upload required)

```text
app/Models/SavingsAccount.php
app/Http/Controllers/Api/SavingsController.php
app/Http/Controllers/Api/BugReportController.php     (NEW)
app/Mail/BugReportMail.php                           (NEW)
app/Services/Retirement/RetirementIncomeService.php
app/Http/Middleware/PreviewWriteInterceptor.php
app/Providers/RouteServiceProvider.php               (updated)
routes/api.php
```

### Blade Templates (1 file - Upload required)

```text
resources/views/emails/bug-report.blade.php          (NEW)
```

### Frontend (10 files - Included in Build)

```text
resources/js/constants/designSystem.js
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/FutureValueTab.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/Navbar.vue                   (updated: Bug Report button)
resources/js/components/BugReportModal.vue           (NEW)
resources/js/store/modules/retirement.js
resources/js/services/savingsService.js
resources/js/services/consoleCapture.js              (NEW)
resources/js/services/bugReportService.js            (NEW)
resources/js/app.js                                  (updated: console capture init)
```

---

## Verification

After deployment, verify:

1. **Risk Badges Display Correctly**
   - Navigate to any investment account with risk level set
   - Verify Low risk shows yellow badge
   - Verify Lower-Medium risk shows pink badge
   - Verify Medium risk shows green badge
   - Verify Upper-Medium risk shows teal badge
   - Verify High risk shows blue badge

2. **No Orange Colors Visible**
   - Navigate through the application
   - Verify no orange colors appear in warnings, badges, or alerts
   - All warning/caution states should use blue

3. **Unified Retirement Income Planner**
   - Navigate to Retirement > Income tab
   - Verify 6 summary cards display (Target, Net, Tax, Pension Capital, Other Assets, Gap/Surplus)
   - Verify Other Assets section shows investments and cash with toggles
   - Verify toggling assets updates calculations
   - Verify Progress bars display (Current and Forecasted)
   - Verify Assumptions panel displays with edit link
   - Verify Fund Depletion chart and table display
   - Verify Year-by-Year Projection table displays

4. **Cash Account Default Behaviour**
   - Create a new savings account
   - Verify it does NOT appear in Income Planner by default
   - Toggle it to "Include" in Other Assets section
   - Verify it now appears in calculations

5. **Navigation Updates**
   - Click Pension Pot Projection chart - should go to Income tab
   - Click Required Capital card in Future Value - should go to Income tab

6. **Bug Report Feature**
   - Click Bug Report button in navbar - modal should open
   - Enter description and click Send Report
   - Verify success message appears
   - Verify email arrives at chris@fynla.org with console logs and user info
   - Test on mobile menu as well

---

## Rollback

If issues occur:

1. Restore previous `public/build/` directory from backup
2. Clear cache:
   ```bash
   php artisan cache:clear && php artisan config:clear && php artisan view:clear
   ```

---

## Account Card Styling - Retirement Income Planner

**Branch:** main

**Status:** Ready for deployment

### Description

Updated account card styling in the Retirement Income Planner to match the pension and investment dashboard patterns. Added consistent toggle styling with colored tracks.

### Account Card Pattern

Cards in Income Sources and Other Assets now follow the dashboard card pattern:

| Element | Style |
|---------|-------|
| Background | White |
| Border | `border-gray-200` |
| Border radius | 8px |
| Padding | 16px |
| Type badge | Colored pill at top-left |

### Type Badge Colors

| Account Type | Badge Color | Tailwind Classes |
|--------------|-------------|------------------|
| ISA (Stocks & Shares) | Green | `bg-green-100 text-green-800` |
| Cash ISA | Emerald | `bg-emerald-100 text-emerald-800` |
| SIPP | Blue | `bg-blue-100 text-blue-800` |
| Onshore Bond | Green | `bg-green-100 text-green-800` |
| Offshore Bond | Green | `bg-green-100 text-green-800` |
| GIA | Gray | `bg-gray-100 text-gray-700` |
| Savings | Gray | `bg-gray-100 text-gray-700` |

### Toggle Styling

| State | Track Color | Slider Color | Slider Position |
|-------|-------------|--------------|-----------------|
| Excluded | Blue (`bg-blue-500`) | White | Left |
| Included | Green (`bg-green-500`) | White | Right |

Toggle button has no background color (transparent).

### Other Assets Display

| Section | Shows |
|---------|-------|
| Income Sources (Included) | Value + Annual draw |
| Other Assets (Excluded) | Projected value only (no annual draw) |

### Files Changed (2 files)

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/RetirementIncomeTab.vue
```

**Documentation:**
```text
designStyle.md
```

---

## Toggle Functionality Fix - Retirement Income Planner

**Branch:** main

**Status:** Ready for deployment

### Description

Fixed critical bug where toggling assets to include/exclude in the Retirement Income Planner did not update the page calculations. When users toggled investments or savings accounts, the depletion chart, projected net income, tax breakdown, and summary cards were not recalculating.

### Root Cause

The `toggleAsset()` and `toggleAllocation()` methods were calling `debouncedCalculate()` which internally calls `calculateRetirementIncome()`. This action sends the **existing** allocations to the backend for recalculation - but the toggled asset's `include_in_retirement` status wasn't reflected in those allocations.

### Solution

Changed both methods to call `fetchRetirementIncome()` instead. This action:
1. Sends a fresh request to `/api/retirement/income`
2. Backend queries the database with updated `include_in_retirement` flags
3. Returns recalculated allocations including/excluding the toggled asset
4. Updates all derived calculations (tax, depletion, progress)

### Code Changes

```javascript
// BEFORE (broken)
async toggleAsset(type, id) {
  if (type === 'investment') {
    await this.toggleIncludedInvestment(id);
  } else if (type === 'cash') {
    await this.toggleIncludedCash(id);
  }
  this.debouncedCalculate();  // Sends stale allocations
}

// AFTER (fixed)
async toggleAsset(type, id) {
  if (type === 'investment') {
    await this.toggleIncludedInvestment(id);
  } else if (type === 'cash') {
    await this.toggleIncludedCash(id);
  }
  await this.fetchRetirementIncome();  // Fetches fresh allocations
}
```

Same fix applied to `toggleAllocation()` method.

### Verification

Tested with peak_earners persona:
- Toggle Royal London bond to "Excluded" → Other Assets changed from £844,492 to £521,730
- Gap to Target updated from £265,317 to £588,079
- Bond moved from Income Sources to Other Assets section
- Toggle back to "Included" → Values restored correctly

### Files Changed (1 file)

**Frontend (Included in Build):**
```text
resources/js/components/Retirement/RetirementIncomeTab.vue
```

---

## Fund Depletion Projection Fix - Priority-Based Withdrawal

**Branch:** retireDecim

**Status:** Ready for deployment

### Description

Fixed the Fund Depletion year-by-year projection to use **priority-based tax-efficient withdrawal**. Tax-free sources (PCLS, ISA) are now used FIRST before ANY taxable sources (Pension Drawdown). This achieves **ZERO TAX** while tax-free money exists.

### Previous Bug (Allocation-Based Without Fallback)

The year-by-year projection used allocation-based withdrawal without fallback:
```php
// OLD: Withdraw according to calculated allocations
foreach ($allocationWithdrawals as $fundKey => $annualAmount) {
    $withdrawFromFundKey($actualFundKey, $scaledAmount);
}
// NO FALLBACK: income DROPS when allocated funds deplete
```

**Problem:** When ISA depleted, income dropped dramatically while £1.5M in Pension Drawdown sat unused. Users paid TAX while £300k+ tax-free remained at age 100.

### Fix Applied - Priority-Based Withdrawal

Implemented tax-efficient withdrawal order per documentation (retireIncomePriority.md lines 711-736):

**File:** `app/Services/Retirement/RetirementIncomeService.php`
**Lines:** 1111-1182

```php
// PRIORITY-BASED WITHDRAWAL: Tax-efficient order per documentation
// Order: Bond 5% (mandatory) → PCLS → ISA → Drawdown → GIA → Savings
// GOAL: Use ALL tax-free sources FIRST before ANY taxable sources
// Result: ZERO TAX while tax-free money exists

$remainingTarget = $yearTargetIncome;

// 1. BOND 5% (MANDATORY) - Tax-deferred
$bondWithdrawn = $withdrawFromFundType('bond', $bondBalance * 0.05);
$remainingTarget -= $bondWithdrawn;

// 2. PCLS (TAX-FREE) - Fill the gap first
if ($remainingTarget > 0) {
    $pclsWithdrawn = $withdrawFromFundType('pcls', $remainingTarget);
    $remainingTarget -= $pclsWithdrawn;
}

// 3. ISA (TAX-FREE) - Fill remaining gap
if ($remainingTarget > 0) {
    $isaWithdrawn = $withdrawFromFundType('isa', $remainingTarget);
    $remainingTarget -= $isaWithdrawn;
}

// 4. PENSION DRAWDOWN (TAXABLE) - ONLY if tax-free insufficient
if ($remainingTarget > 0) {
    $drawdownWithdrawn = $withdrawFromFundType('drawdown', $remainingTarget);
    $remainingTarget -= $drawdownWithdrawn;
}

// 5. GIA, 6. Savings - if still needed
```

### Documented Behavior (from retireIncomePriority.md)

Per **Lines 1026-1031**:
> "GOAL: Use tax-free sources FIRST so that:
> - Tax-free depletes BEFORE taxable
> - Later years have ZERO TAX (only taxable remains within PA)
> - Pension Drawdown is LAST RESORT"

### Results (Verified with David Mitchell - Peak Earners)

| Ages | Source Used | Tax Paid | Analysis |
|------|-------------|----------|----------|
| 60-63 | PCLS withdrawn | £0 | PCLS used FIRST |
| 64 | PCLS depletes, ISA starts | £0 | ISA takes over |
| 65-71 | ISA withdrawn, Drawdown untouched | £0 | ISA used BEFORE Drawdown |
| 72+ | ISA depletes, Drawdown now used | £8,111+ | Tax starts only when tax-free gone |

### Key Behavior Changes

| Aspect | Previous (Wrong) | Now (Correct) |
|--------|------------------|---------------|
| Withdrawal order | Allocation-based (PMT amounts) | Priority-based (tax-efficient) |
| When tax-free depletes | Income drops, taxable unused | Fallback to taxable sources |
| Tax efficiency | Paid tax while tax-free existed | ZERO TAX while tax-free exists |
| Drawdown usage | Per allocation | ONLY when tax-free exhausted |

### Files Changed (1 file)

**Backend:**
```text
app/Services/Retirement/RetirementIncomeService.php
```

### Testing Performed

1. ✅ PCLS used FIRST (ages 60-63)
2. ✅ ISA used SECOND after PCLS depletes (ages 64-71)
3. ✅ Drawdown used LAST (age 72+) - ONLY when tax-free exhausted
4. ✅ Tax Paid = £0 while tax-free sources have balance
5. ✅ All automated tests pass (9 controller, 12 projection)

---

## Toggle Persistence Fix - PreviewWriteInterceptor

**Branch:** retireDecim

**Status:** Ready for deployment

### Description

Fixed critical bug where toggling assets to include/exclude in retirement planning did not persist to the database for preview users. The toggle appeared to work in the UI but the change was never saved.

### Root Cause

The `PreviewWriteInterceptor` middleware intercepts all write operations (POST, PUT, PATCH, DELETE) for preview users and returns a fake success response without actually saving to the database. The `/toggle-retirement` endpoint was not excluded from this interception.

### Fix Applied

Added `/toggle-retirement` to the excluded patterns so the toggle persists for preview users:

**File:** `app/Http/Middleware/PreviewWriteInterceptor.php`

```php
private const EXCLUDED_PATTERNS = [
    '/calculate',           // All calculation endpoints
    '/calculate-',          // Hyphenated calculation endpoints
    '/projections',         // Projection/simulation endpoints
    '/recalculate',         // Risk profile recalculation
    '/reprocess',           // Document re-extraction
    '/analyze',             // Analysis endpoints
    '/toggle-retirement',   // Retirement inclusion toggle (NEW)
];
```

### Testing Performed

1. Reset ISA `include_in_retirement = false` in database
2. Logged in as David Mitchell (preview user)
3. Clicked ISA toggle to "Include"
4. Verified database updated: `include_in_retirement = TRUE`
5. Verified UI shows ISA in Income Sources with correct calculations

### Files Changed (1 file)

**Backend:**
```text
app/Http/Middleware/PreviewWriteInterceptor.php
```

---

## TODO: Remove Orange Colors from Goals and Onboarding

**Status:** Not Started

### Description

Orange colors remain in the Goals and Onboarding modules and need to be replaced to complete the orange color ban.

### Files to Update

**Goals Module (4 files):**
```text
resources/js/components/Goals/GoalsByModule.vue
resources/js/components/Goals/GoalsAnalysis.vue
resources/js/components/Goals/GoalContributionStreak.vue
resources/js/components/Goals/GoalCountdown.vue
```

**Onboarding Module (2 files):**
```text
resources/js/components/Onboarding/SkipConfirmationModal.vue
resources/js/components/Onboarding/steps/ExpenditureStep.vue
```

### Replacement Guidelines

| Context | Replace Orange With |
|---------|---------------------|
| Warning/caution states | Blue (`blue-*`) |
| Progress indicators | Blue or Teal |
| Priority badges (high priority) | Keep Red for critical, use Blue for high |
| Streak/achievement indicators | Teal or Purple |
| Countdown urgency | Red or Blue |

### Notes

- Review each usage to determine appropriate replacement color
- Maintain visual hierarchy and meaning
- Test that color changes don't reduce accessibility

---

## Bug Report Feature

**Branch:** retireDecim

**Status:** Ready for deployment

### Description

Added a Bug Report button to the navbar that captures user context, console logs, and user description, then emails to chris@fynla.org. Works for both authenticated and guest users.

### Features

- **Console Capture Service** - Intercepts console.log/error/warn/info, maintains circular buffer of last 100 entries, captures unhandled errors and promise rejections
- **Bug Report Modal** - Description (required), expected behaviour (optional), info notice about technical data, loading/success states
- **Rate Limiting** - 5 reports per hour per user/IP
- **Email includes:**
  - User info (ID, name, email, preview status)
  - Bug description and expected behaviour
  - Console logs (dark code block)
  - Technical context (URL, browser, screen size, viewport, IP, timestamps)

### Email Subject Format

```
Bug Report - User {id}
Bug Report - User {id} [PREVIEW]   (for preview users)
Bug Report - User Guest            (for unauthenticated users)
```

### New Files Created (6 files)

**Backend:**
```text
app/Http/Controllers/Api/BugReportController.php
app/Mail/BugReportMail.php
resources/views/emails/bug-report.blade.php
```

**Frontend:**
```text
resources/js/services/consoleCapture.js
resources/js/services/bugReportService.js
resources/js/components/BugReportModal.vue
```

### Files Modified (4 files)

**Backend:**
```text
app/Providers/RouteServiceProvider.php   (added bug-reports rate limiter)
routes/api.php                           (added POST /api/bug-report route)
```

**Frontend:**
```text
resources/js/app.js                      (initialize console capture)
resources/js/components/Navbar.vue       (added Bug Report button + modal, kept Feedback link)
```

### Button Styling

Both Feedback and Bug Report buttons use blue colors (design system compliant):

```html
class="inline-flex items-center px-4 py-2 border-2 border-blue-300
       text-body-sm font-medium rounded-button text-blue-600 bg-white
       hover:text-blue-800 hover:border-blue-400 transition-colors"
```

### API Endpoint

```
POST /api/bug-report
Rate limit: 5 per hour per user/IP

Request body:
{
  "description": "string (required, max 5000)",
  "expected_behaviour": "string (optional, max 2000)",
  "console_logs": "string (optional, max 50000)",
  "page_url": "string (optional)",
  "user_agent": "string (optional)",
  "screen_size": "string (optional)",
  "viewport_size": "string (optional)",
  "client_timestamp": "string (optional)"
}

Response:
{ "success": true, "message": "Bug report submitted successfully..." }
```

### Testing Performed

1. ✅ Bug Report button visible in navbar (desktop and mobile)
2. ✅ Feedback button still present
3. ✅ Modal opens with correct fields
4. ✅ Form validation works (description required)
5. ✅ Submission shows loading state then success
6. ✅ Email received at chris@fynla.org with all data
7. ✅ Console logs included in email
8. ✅ Preview user badge appears in subject for preview users

---
