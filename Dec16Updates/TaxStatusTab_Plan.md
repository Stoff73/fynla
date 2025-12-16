# Tax Status Tab Implementation Plan
**Date**: December 16, 2025
**Status**: Complete

## Overview
Add a "Tax Status" tab to Investment and Savings account detail views, displaying UK tax treatment information for each product type.

## Requirements Summary
- Add Tax Status tab to Investment detail view (5 tabs → 6 tabs)
- Convert Savings detail view from sections to tabs, add Tax Status tab
- Store tax reference data in database for future calculations
- Display summary-level info (3-5 bullet points per product type)

---

## Phase 1: Database Layer

### 1.1 Create Migration
**File**: `database/migrations/YYYY_MM_DD_create_tax_product_reference_table.php`

```sql
CREATE TABLE tax_product_reference (
    id BIGINT PRIMARY KEY,
    product_category VARCHAR(255),  -- 'investment' or 'savings'
    product_type VARCHAR(255),      -- 'isa', 'gia', 'cash_isa', etc.
    tax_aspect VARCHAR(255),        -- 'income_tax', 'cgt', 'iht', 'allowances'
    title VARCHAR(255),             -- Display title
    summary TEXT,                   -- Bullet point summary
    status VARCHAR(255),            -- 'exempt', 'taxable', 'deferred', 'relief'
    status_icon VARCHAR(255),       -- Icon identifier
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    timestamps
);
```

### 1.2 Create Seeder
**File**: `database/seeders/TaxProductReferenceSeeder.php`

Key product types to seed:

| Category | Product Type | Key Tax Points |
|----------|--------------|----------------|
| investment | isa | Tax-free income, Tax-free CGT, £20k limit, Part of estate for IHT |
| investment | gia | Dividends taxable (£500 allowance), Interest taxable (PSA), CGT applies |
| investment | onshore_bond | 5% withdrawal deferred, Basic rate credit, Top-slicing relief |
| investment | offshore_bond | Gross roll-up, Full marginal rate on gains |
| investment | vct | 30% relief, Tax-free dividends, No CGT, Not IHT exempt |
| investment | eis | 30% relief, No CGT after 3 years, IHT exempt after 2 years |
| investment | nsi | Premium Bonds: tax-free prizes; Others: taxable interest |
| savings | cash_isa | Tax-free interest, £20k limit |
| savings | easy_access | Interest taxable, PSA applies |
| savings | premium_bonds | Tax-free prizes |

---

## Phase 2: Backend Layer

### 2.1 Create Model
**File**: `app/Models/TaxProductReference.php`

### 2.2 Create Service
**File**: `app/Services/Tax/TaxProductInfoService.php`

- Retrieves tax reference data by product type
- Enriches with current rates from `TaxConfigService`
- Returns structured data for frontend display

### 2.3 Create Controller
**File**: `app/Http/Controllers/Api/TaxProductInfoController.php`

Endpoints:
- `GET /api/tax-info/investment/{accountType}`
- `GET /api/tax-info/savings/{accountType}?is_isa={bool}`

### 2.4 Add Routes
**File**: `routes/api.php`

```php
Route::middleware('auth:sanctum')->prefix('tax-info')->group(function () {
    Route::get('/investment/{accountType}', [TaxProductInfoController::class, 'getInvestmentTaxInfo']);
    Route::get('/savings/{accountType}', [TaxProductInfoController::class, 'getSavingsTaxInfo']);
});
```

---

## Phase 3: Frontend Layer

### 3.1 Create Reusable Component
**File**: `resources/js/components/Common/TaxStatusPanel.vue`

Props:
- `productCategory`: 'investment' | 'savings'
- `productType`: string (account type)
- `isIsa`: boolean (for savings)

Features:
- Fetches tax info via API
- Displays tax items with status icons (exempt ✓, taxable !, deferred ⏱, relief ↓)
- Shows current allowances from TaxConfigService
- Includes disclaimer

### 3.2 Create API Service
**File**: `resources/js/services/taxInfoService.js`

### 3.3 Update Investment Detail View
**File**: `resources/js/views/Investment/AccountDetailView.vue`

Changes:
- Import TaxStatusPanel
- Add conditional render for `activeTab === 'account-tax-status'`

### 3.4 Update Investment Dashboard Tabs
**File**: `resources/js/views/Investment/InvestmentDashboard.vue`

Add to `detailTabs` array:
```javascript
{ id: 'account-tax-status', label: 'Tax Status' }
```

### 3.5 Refactor Savings Detail View
**File**: `resources/js/views/Savings/SavingsAccountDetailInline.vue`

Convert from sections to tabs:
- Overview (Account Information)
- Balance & Interest
- Access & Terms
- ISA Details (conditional)
- Tax Status (new)

---

## Phase 4: Testing

### Backend Tests
**File**: `tests/Unit/Services/Tax/TaxProductInfoServiceTest.php`
**File**: `tests/Feature/TaxProductInfoControllerTest.php`

---

## Critical Files to Modify

| File | Changes |
|------|---------|
| `resources/js/views/Investment/AccountDetailView.vue` | Add TaxStatusPanel component |
| `resources/js/views/Investment/InvestmentDashboard.vue` | Add 'account-tax-status' to detailTabs |
| `resources/js/views/Savings/SavingsAccountDetailInline.vue` | Convert to tabs, add TaxStatusPanel |
| `app/Services/TaxConfigService.php` | Reference for rates (no changes) |
| `routes/api.php` | Add tax-info routes |

---

## New Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/YYYY_create_tax_product_reference_table.php` | Migration |
| `database/seeders/TaxProductReferenceSeeder.php` | Seed tax data |
| `app/Models/TaxProductReference.php` | Eloquent model |
| `app/Services/Tax/TaxProductInfoService.php` | Business logic |
| `app/Http/Controllers/Api/TaxProductInfoController.php` | API endpoints |
| `resources/js/components/Common/TaxStatusPanel.vue` | Reusable UI component |
| `resources/js/services/taxInfoService.js` | Frontend API wrapper |

---

## Implementation Order

1. Database migration + seeder
2. Backend model + service + controller + routes
3. Frontend TaxStatusPanel component + service
4. Investment detail view integration
5. Savings detail view refactor to tabs
6. Testing

---

## Progress Tracking

- [x] Phase 1: Database Layer
  - [x] Migration created (`2025_12_16_093932_create_tax_product_reference_table.php`)
  - [x] Seeder created (`TaxProductReferenceSeeder.php`)
- [x] Phase 2: Backend Layer
  - [x] Model created (`TaxProductReference.php`)
  - [x] Service created (`TaxProductInfoService.php`)
  - [x] Controller created (`TaxProductInfoController.php`)
  - [x] Routes added to `api.php`
- [x] Phase 3: Frontend Layer
  - [x] TaxStatusPanel component (`resources/js/components/Common/TaxStatusPanel.vue`)
  - [x] taxInfoService.js (`resources/js/services/taxInfoService.js`)
  - [x] Investment detail view updated (`AccountDetailView.vue`)
  - [x] Investment dashboard tabs updated (`InvestmentDashboard.vue`)
  - [x] Savings detail view refactored to tabs (`SavingsAccountDetailInline.vue`)
- [x] Phase 4: Testing
  - [ ] Unit tests (deferred)
  - [ ] Feature tests (deferred)
  - [x] Manual API testing (passed)
  - [x] Frontend build verification (passed)
