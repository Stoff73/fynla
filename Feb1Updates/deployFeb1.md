# Deployment Notes - February 1, 2026

**Deployment Status:** ✅ DEPLOYED - February 1, 2026

---

## Retirement Income Planner - UI Cleanup & Bug Fixes

**Branch:** retireIncome

**Status:** ✅ Deployed

### Description

Cleaned up the Retirement Income Planner UI by removing redundant cards and reorganizing the layout for a cleaner, more focused view. Also fixed a critical bug where savings/cash accounts were not loading.

### UI Changes

#### Summary Cards (Top Section)

| Change | Before | After |
|--------|--------|-------|
| Card count | 6 cards (3 columns) | 4 cards (4 columns) |
| Removed | Annual Tax card | - |
| Removed | Gap to Target card | - |
| Remaining | Target Income, Net Income, Pension Capital, Other Assets | Same 4 cards inline |

#### Removed Sections

| Section | Reason |
|---------|--------|
| Progress Towards Target | Redundant - values shown in summary cards |
| Calculation Assumptions | Moved to settings - accessible via edit link elsewhere |

#### Income Sources & Other Assets

| Change | Before | After |
|--------|--------|-------|
| Layout | Stacked vertically | Side by side (2 columns) |
| Other Assets grid | Single column | 2 columns when multiple assets |

### Bug Fixes

#### 1. Savings Accounts Not Loading

**Problem:** Cash/savings accounts were not appearing in the "Other Assets" section.

**Root Cause:** Component was calling `fetchAccounts` action which doesn't exist in the savings Vuex store. The correct action is `fetchSavingsData`.

**Fix:** Changed mapping from `fetchAccounts` to `fetchSavingsData`:

```javascript
// BEFORE (broken)
...mapActions('savings', { fetchSavingsAccounts: 'fetchAccounts' }),

// AFTER (fixed)
...mapActions('savings', { fetchSavingsAccounts: 'fetchSavingsData' }),
```

#### 2. Controller Missing Projected Pension Pot

**Problem:** The `getIncomeAccounts()` endpoint was not passing the projected pension pot to `getAvailableAccounts()`, causing the pension pot to show as £0 when called directly.

**Fix:** Added pension pot calculation before calling the service:

```php
// Get the projected pension pot value (80% Monte Carlo confidence)
$potProjection = $this->projectionService->projectPensionPot($user);
$projectedPensionPot = (float) ($potProjection['percentile_20_at_retirement'] ?? 0);

// Calculate years to retirement
$profile = RetirementProfile::where('user_id', $user->id)->first();
$currentAge = $user->date_of_birth ? $user->date_of_birth->age : null;
$retirementAge = $profile?->target_retirement_age ?? 68;
$yearsToRetirement = max(0, $retirementAge - ($currentAge ?? 45));

// Pass to service
$accounts = $this->retirementIncomeService->getAvailableAccounts(
    $user->id,
    $includeSpouse,
    $projectedPensionPot,
    $yearsToRetirement
);
```

---

## Files Changed (3 files)

### Backend (1 file - Upload required)

```text
app/Http/Controllers/Api/RetirementController.php
```

### Frontend (1 file - Included in Build)

```text
resources/js/components/Retirement/RetirementIncomeTab.vue
```

### Documentation (1 file - No upload needed)

```text
Feb1Updates/RetirementIncomePlannerConsolidated.md
```

---

## Rebuild Required: YES

Frontend Vue component changed. Full rebuild required:

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

```text
app/Http/Controllers/Api/RetirementController.php
```

### Step 4: Clear Cache (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

---

## Verification

After deployment, verify:

1. **Summary Cards Display**
   - Navigate to Retirement > Income tab
   - Verify 4 summary cards display inline (Target Income, Net Income, Pension Capital, Other Assets)
   - Verify Annual Tax and Gap to Target cards are NOT present

2. **Income Sources & Other Assets Layout**
   - Verify Income Sources and Other Assets sections are side by side
   - Verify Other Assets shows in 2-column grid when multiple assets exist

3. **Savings Accounts Loading**
   - Verify all savings accounts appear in Other Assets section
   - Should show: ISA, HSBC, Nationwide, Cash ISA, NS&I for peak_earners persona

4. **Progress/Assumptions Removed**
   - Verify "Progress Towards Target" section is NOT present
   - Verify "Calculation Assumptions" section is NOT present

5. **Toggles Still Work**
   - Toggle an asset to Include/Exclude
   - Verify calculations update correctly

---

## Rollback

If issues occur:

1. Restore previous `public/build/` directory from backup
2. Restore previous `RetirementController.php`
3. Clear cache:
   ```bash
   php artisan cache:clear && php artisan config:clear && php artisan view:clear
   ```

---
