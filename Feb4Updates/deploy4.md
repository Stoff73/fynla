# Deploy Notes - Feb 4, 2025

---

## Goals Life Events Bug Fix ✅ DEPLOYED

### Summary

Life events were not displaying on production because the `life_events` table was empty. The PreviewUserSeeder had been updated to seed life events, but hadn't been run after the migrations.

### Fix Applied

```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

### Files Changed

None - data seeding issue only.

---

## BTL Tax Ownership Percentage Fix ✅ DEPLOYED

### Summary

For jointly-owned Buy-to-Let properties, the rental income and Section 24 tax credit were not being split correctly between owners. The full amount was assigned to the primary owner only.

**Example:**
- BTL Property owned jointly by David (60%) and Sarah (40%)
- Annual taxable rental income: £12,000
- Section 24 credit: £2,400

**Before Fix:**
- David: £7,200 income, £1,440 credit ✅
- Sarah: £0 income, £0 credit ❌

**After Fix:**
- David: £7,200 income, £1,440 credit ✅
- Sarah: £4,800 income, £960 credit ✅

### Root Cause

1. `UserProfileService::calculateAnnualRentalIncome()` only queried properties where `user_id = $user->id`
2. Joint owners (where `joint_owner_id = $user->id`) were never included
3. `PropertyService::calculateTaxPosition()` always used primary owner's percentage

### Files Changed

| File | Change Type |
|------|-------------|
| `app/Services/Property/PropertyService.php` | Modified |
| `app/Services/UserProfile/UserProfileService.php` | Modified |

### Files to Upload

- `app/Services/Property/PropertyService.php`
- `app/Services/UserProfile/UserProfileService.php`

### Post-Upload Commands

```bash
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

---

## UI Improvements ✅ DEPLOYED

### Summary

Minor UI improvements to Expenditure and Estate Planning pages.

### Changes

1. **Expenditure Form** - Changed "Widowed" tab label to "Budget if Widowed" for clarity
2. **IHT Calculation Table** - Added "Expand All / Collapse All" button at top right to toggle all concertina sections

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/UserProfile/ExpenditureForm.vue` | Modified |
| `resources/js/components/Estate/IHTCalculationTable.vue` | Modified |

### Deployment

**Frontend Rebuild Required:** ✅ YES

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory to production.

---

## Complete Upload Checklist

### Goals Life Events Fix ✅

- [x] `php artisan db:seed --class=PreviewUserSeeder --force` (run on server)

### BTL Tax Ownership Fix

**Frontend Rebuild Required:** ❌ NO (backend-only fix)

**Files to Upload:**

- [x] `app/Services/Property/PropertyService.php`
- [x] `app/Services/UserProfile/UserProfileService.php`

### UI Improvements ✅

**Frontend Rebuild Required:** ✅ YES

- [x] Run `./deploy/fynla-org/build.sh`
- [x] Upload `public/build/` directory

### Post-Upload Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Clear caches
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

---

## Verification

After deployment, verify the fix by checking the UK Tax dashboard for peak_earners persona:

- David should show £780 Section 24 credit (50% of £1,560)
- Sarah should show £780 Section 24 credit (50% of £1,560)

Both should have their rental income correctly split by ownership percentage.
