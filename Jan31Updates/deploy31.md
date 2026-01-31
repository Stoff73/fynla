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
app/Services/Retirement/RetirementIncomeService.php
routes/api.php
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

### Backend (4 files - Upload required)

```text
app/Models/SavingsAccount.php
app/Http/Controllers/Api/SavingsController.php
app/Services/Retirement/RetirementIncomeService.php
routes/api.php
```

### Frontend (6 files - Included in Build)

```text
resources/js/constants/designSystem.js
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/FutureValueTab.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/store/modules/retirement.js
resources/js/services/savingsService.js
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

---

## Rollback

If issues occur:

1. Restore previous `public/build/` directory from backup
2. Clear cache:
   ```bash
   php artisan cache:clear && php artisan config:clear && php artisan view:clear
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
