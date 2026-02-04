# Deploy Notes - Feb 3, 2025

---

## IHT Calculation & Projection Fixes ✅ DEPLOYED

### Summary
Comprehensive fixes to the IHT calculation system addressing multiple issues:
1. **Retirement age bug** - Cached calculations were missing retirement_age field
2. **Projection totals mismatch** - Breakdown totals didn't match service calculations
3. **Cash projection display** - Cash assets incorrectly showed current value instead of £0
4. **Chattel projections** - Personal valuables were incorrectly projected at 4.7% growth

### Files Changed

| File | Change Type |
|------|-------------|
| `app/Services/Estate/IHTCalculationService.php` | Modified |
| `app/Http/Controllers/Api/Estate/IHTController.php` | Modified |
| `resources/js/components/Estate/IHTPlanning.vue` | Modified |
| `resources/js/components/Estate/IHTAssetBreakdown.vue` | Modified |

### Files to Upload

- `public/build/` directory (full replacement)
- `app/Services/Estate/IHTCalculationService.php`
- `app/Http/Controllers/Api/Estate/IHTController.php`
- `app/Services/Settings/AssumptionsService.php` ← **CRITICAL: Required for IHT calculations**

---

## Integrated Cash-Investment Drawdown Model ✅ DEPLOYED

### Summary
Implemented an integrated cash-investment projection model that accurately handles retirement deficit drawdown. When cash goes negative during retirement (expenses exceed income), the deficit is drawn from investment accounts BEFORE applying growth.

### Files Changed

| File | Change Type |
|------|-------------|
| `app/Services/Estate/IHTCalculationService.php` | Modified |

### Files to Upload

- `app/Services/Estate/IHTCalculationService.php`

---

## Dashboard Estate Card Always Visible ✅ DEPLOYED

### Summary
Changed the Estate Planning card on the dashboard to always be visible, regardless of IHT liability amount.

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/views/Dashboard.vue` | Modified |

### Files to Upload

- `public/build/` directory (full replacement)

---

## Life Insurance Strategy Page Simplification ✅ DEPLOYED

### Summary
Simplified the Life Insurance Strategy page by removing self-insurance options, inaccurate claims, and unnecessary UI elements.

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/Estate/LifePolicyStrategy.vue` | Modified |
| `app/Services/Estate/LifePolicyStrategyService.php` | Modified |

### Files to Upload

- `public/build/` directory (full replacement)
- `app/Services/Estate/LifePolicyStrategyService.php`

---

## Gifting Strategy UI Fix ✅ DEPLOYED

### Summary
Fixed misleading "Immediately Giftable" and "Giftable with Planning" terminology. Replaced with meaningful exemption-based metrics.

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/Estate/GiftingStrategy.vue` | Modified |
| `resources/js/components/Estate/IHTPlanning.vue` | Modified |

### Files to Upload

- `public/build/` directory (full replacement)

---

## IHT Calculation Info Tooltip ✅ DEPLOYED

### Summary
Converted the blue information message box under "Inheritance Tax Calculation (Joint Death Scenario)" heading to an info icon with hover tooltip.

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/Estate/IHTPlanning.vue` | Modified |

### Files to Upload

- `public/build/` directory (full replacement)

---

## Mortgage End Date Retirement Age Messaging ✅ DEPLOYED

### Summary
Added informational messages about mortgage end dates defaulting to retirement age when not specified:
- Form help text: "If no end date specified, chosen retirement date will be used"
- Detail view message: "No end date specified, retirement age of {age} being used"

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/NetWorth/Property/PropertyForm.vue` | Modified |
| `resources/js/components/NetWorth/Property/PropertyDetailInline.vue` | Modified |

### Files to Upload

- `public/build/` directory (full replacement)

---

## Goals Projection Chart Improvements ✅ DEPLOYED

### Summary
Major refactoring of the Goals module projection chart with visual improvements and simplified navigation:

1. **Real SVG icons** - Replaced text abbreviations with proper icons (house, plane, gift, etc.)
2. **Floating icons** - Icons now float above bars with gap, no connector lines
3. **Vertical stacking** - Multiple events at same age stack vertically without overlap
4. **Completed state** - Past events show at 40% opacity
5. **Asset Breakdown tooltip** - Shows Pensions, Property, Investments, Cash with amounts
6. **Cash Flow fix** - Income now displays correctly using annual_employment_income field
7. **Simplified tabs** - Removed "All Goals", "By Module", "Analysis" tabs
8. **Merged Overview** - Projection chart now embedded in Overview tab
9. **Removed summary cards** - Removed Total Goals, On Track, Total Target, Current Progress cards
10. **Removed progress meter** - Removed overall progress bar

### Files Changed

| File | Change Type |
|------|-------------|
| `resources/js/components/Goals/GoalsProjectionChart.vue` | Modified |
| `resources/js/components/Goals/GoalsOverview.vue` | Modified |
| `resources/js/views/Goals/GoalsDashboard.vue` | Modified |
| `resources/js/constants/eventIconSvgs.js` | New |
| `resources/js/components/Goals/EventIcon.vue` | New |
| `app/Services/Goals/GoalsProjectionService.php` | Modified |
| `resources/js/data/personas/peak_earners.json` | Modified |

### Files to Upload

- `public/build/` directory (full replacement)
- `app/Services/Goals/GoalsProjectionService.php`

**Note:** This feature depends on the Life Events feature below.

---

## Life Events Feature (Goals Dependency) ✅ DEPLOYED

### Summary

Backend infrastructure for life events that affect financial projections. Required dependency for Goals Projection Chart.

### Files Changed

| File | Change Type |
|------|-------------|
| `routes/api.php` | Modified |
| `app/Models/LifeEvent.php` | New |
| `app/Models/Goal.php` | Modified |
| `app/Http/Controllers/Api/GoalsController.php` | Modified |
| `app/Http/Controllers/Api/LifeEventController.php` | New |
| `app/Services/Goals/LifeEventService.php` | New |
| `app/Http/Requests/StoreLifeEventRequest.php` | New |
| `app/Http/Requests/UpdateLifeEventRequest.php` | New |
| `database/migrations/2026_02_03_100001_add_charity_fields_to_bequests_table.php` | New |
| `database/migrations/2026_02_03_100002_add_estate_planning_to_user_assumptions_table.php` | New |
| `database/migrations/2026_02_03_120001_create_life_events_table.php` | New |
| `database/migrations/2026_02_03_120002_add_projection_fields_to_goals_table.php` | New |

### Files to Upload (12 files)

**Routes:**

- `routes/api.php`

**Models:**

- `app/Models/LifeEvent.php`
- `app/Models/Goal.php`

**Controllers:**

- `app/Http/Controllers/Api/GoalsController.php`
- `app/Http/Controllers/Api/LifeEventController.php`

**Services:**

- `app/Services/Goals/LifeEventService.php`

**Form Requests:**

- `app/Http/Requests/StoreLifeEventRequest.php`
- `app/Http/Requests/UpdateLifeEventRequest.php`

**Migrations:**

- `database/migrations/2026_02_03_100001_add_charity_fields_to_bequests_table.php`
- `database/migrations/2026_02_03_100002_add_estate_planning_to_user_assumptions_table.php`
- `database/migrations/2026_02_03_120001_create_life_events_table.php`
- `database/migrations/2026_02_03_120002_add_projection_fields_to_goals_table.php`

### Post-Upload

```bash
php artisan migrate --force
```

---

## Complete Upload Checklist

### Previously Deployed ✅

- [x] `public/build/` directory (full replacement)
- [x] `app/Services/Estate/IHTCalculationService.php`
- [x] `app/Http/Controllers/Api/Estate/IHTController.php`
- [x] `app/Services/Estate/LifePolicyStrategyService.php`
- [x] `app/Services/Settings/AssumptionsService.php`
- [x] `app/Services/Goals/GoalsProjectionService.php`

### Goals & Life Events ✅ DEPLOYED

#### Routes

- [x] `routes/api.php`

#### Models

- [x] `app/Models/LifeEvent.php`
- [x] `app/Models/Goal.php`

#### Controllers

- [x] `app/Http/Controllers/Api/GoalsController.php`
- [x] `app/Http/Controllers/Api/LifeEventController.php`

#### Services

- [x] `app/Services/Goals/LifeEventService.php`

#### Form Requests

- [x] `app/Http/Requests/StoreLifeEventRequest.php`
- [x] `app/Http/Requests/UpdateLifeEventRequest.php`

#### Migrations

- [x] `database/migrations/2026_02_03_100001_add_charity_fields_to_bequests_table.php`
- [x] `database/migrations/2026_02_03_100002_add_estate_planning_to_user_assumptions_table.php`
- [x] `database/migrations/2026_02_03_120001_create_life_events_table.php`
- [x] `database/migrations/2026_02_03_120002_add_projection_fields_to_goals_table.php`

### Post-Upload Commands

```bash
cd ~/www/fynla.org/public_html

# 1. Clear all caches first
php artisan cache:clear && php artisan route:clear && php artisan config:clear && php artisan view:clear

# 2. Run migrations
php artisan migrate --force

# 3. Rebuild caches
php artisan config:cache && php artisan route:cache
```
