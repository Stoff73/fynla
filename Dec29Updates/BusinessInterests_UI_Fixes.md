# Business Interests UI Fixes - December 29, 2025

## Summary

Fixed various issues with the Business Interests module including data flow problems, acronym usage, and UI simplification.

## Changes Made

### 1. Bug Fixes

#### Trust Model Import Error
- **File:** `app/Models/BusinessInterest.php`
- **Issue:** `Class "App\Models\Trust" not found` error when viewing business detail
- **Fix:** Added missing import for `App\Models\Estate\Trust`

#### Tax Deadlines & Exit Calculation Not Displaying
- **File:** `resources/js/store/modules/businessInterests.js`
- **Issue:** Vuex store was not correctly extracting nested data from API responses
- **Fix:** Updated `fetchTaxDeadlines` and `fetchExitCalculation` actions to properly extract:
  - `response.data.deadlines` for tax deadlines array
  - `response.data.exit_calculation` for exit calculation object

#### Exit Calculation Field Mappings
- **File:** `resources/js/components/NetWorth/BusinessInterestDetailInline.vue`
- **Issue:** Frontend was using incorrect field names (`sale_value` instead of `user_sale_proceeds`)
- **Fix:** Updated all field references to match backend response:
  - `sale_value` → `user_sale_proceeds`
  - `acquisition_cost` → `user_cost_basis`

### 2. UI Simplification

#### Removed Sort/Filter Dropdowns
- **File:** `resources/js/components/NetWorth/BusinessInterestsList.vue`
- **Changes:**
  - Removed business type filter dropdown
  - Removed sort dropdown (was: Value High to Low, Value Low to High, Business Name)
  - Simplified to just show "Add Business" button
  - Removed associated data properties and computed logic
  - Cleaned up unused CSS

### 3. Acronym Removal (User-Friendly Labels)

All acronyms replaced with full terms for clarity:

| Location | Before | After |
|----------|--------|-------|
| Business Card | BPR Eligible | Business Relief Eligible |
| Detail View | Business Property Relief (BPR) | Business Relief |
| Detail View | CGT Due | Capital Gains Tax Due |
| Detail View | CGT Calculation Breakdown | Capital Gains Tax Calculation |
| Detail View | CGT Rate Applied | Tax Rate Applied |
| Detail View | Business Asset Disposal Relief (BADR) | Business Asset Disposal Relief |
| Detail View | 10% CGT rate applies | 10% Capital Gains Tax rate applies |
| Detail View | UTR Number | Unique Taxpayer Reference |
| Detail View | PAYE Reference | Employer PAYE Reference |
| Detail View | VAT Number | VAT Registration Number |
| Backend Warnings | BADR limit | Business Asset Disposal Relief limit |
| Backend Warnings | 100% IHT relief | 100% Inheritance Tax relief |

### 4. New UI Sections Added

#### Exit Planning Tab Enhancements
- **Business Asset Disposal Relief Assessment** - Shows eligibility reasons (e.g., "Held for 7.0 years (2+ years required)", "Business is actively trading")
- **Business Relief Note** - Shows inheritance tax relief eligibility when applicable

## Files Modified

| File | Changes |
|------|---------|
| `app/Models/BusinessInterest.php` | Added Trust model import |
| `app/Services/Business/BusinessInterestService.php` | Removed acronyms from warning messages |
| `resources/js/components/NetWorth/BusinessInterestCard.vue` | Updated BPR badge text and tooltip |
| `resources/js/components/NetWorth/BusinessInterestDetailInline.vue` | Fixed field mappings, expanded acronyms, added new sections |
| `resources/js/components/NetWorth/BusinessInterestsList.vue` | Removed sort/filter dropdowns, simplified UI |
| `resources/js/store/modules/businessInterests.js` | Fixed API response data extraction |

## Git

- **Branch:** `business`
- **Commit:** `b380b62`
- **Message:** "fix: Business interests UI improvements and data fixes"
- **Merged to:** `main`

## Testing

The following API endpoints were verified working:
- `GET /api/business-interests` - List all businesses
- `GET /api/business-interests/{id}` - Get single business
- `GET /api/business-interests/{id}/tax-deadlines` - Returns deadlines based on business type
- `GET /api/business-interests/{id}/exit-calculation` - Returns CGT calculation with BADR eligibility
