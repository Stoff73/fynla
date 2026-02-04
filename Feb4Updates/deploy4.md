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

## Pension Fees in Overview Tab

### Summary

Added a Fees section to the Overview tab for DC pensions in the detailed view. Shows fee breakdown including platform fee, fund fees (OCF), total annual cost, and annual fee impact.

### Features Added

1. **Platform Fee** - Shows the pension platform fee percentage
2. **Avg Fund Fee (OCF)** - Weighted average OCF across holdings (only shown if pension has holdings)
3. **Total Annual Cost** - Combined platform fee + weighted average OCF
4. **Annual Fee Impact** - Total fees as a pound amount per year

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/NetWorth/PensionDetailInline.vue` | Modified |
| `resources/js/data/personas/peak_earners.json` | Modified |
| `resources/js/data/personas/young_family.json` | Modified |
| `resources/js/data/personas/entrepreneur.json` | Modified |
| `resources/js/data/personas/young_saver.json` | Modified |
| `database/seeders/PreviewUserSeeder.php` | Modified |

### Changes Made

1. **Vue Component** - Added Fees section to DC pension Overview tab
2. **Persona Data** - Added `platform_fee_percent` to all DC pensions in persona JSON files
3. **Seeder** - Fixed to map `platform_fee_percent` field + fixed LifeEvent soft-delete FK constraint

### Deployment

**Frontend Rebuild Required:** ✅ YES

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory to production.

**Seeder Required:** ✅ YES (to populate platform fees)

```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

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

### Pension Fees in Overview Tab

**Frontend Rebuild Required:** ✅ YES

- [ ] Run `./deploy/fynla-org/build.sh`
- [ ] Upload `public/build/` directory
- [ ] Upload `database/seeders/PreviewUserSeeder.php`
- [ ] Run `php artisan db:seed --class=PreviewUserSeeder --force` on server

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
