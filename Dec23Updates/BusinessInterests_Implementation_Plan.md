# Business Interests Implementation Plan

**Date**: 23 December 2025
**Status**: In Progress
**Branch**: `business`

## Overview
Implement full business interests feature allowing users to enter business ownership, view tax deadlines, track value, calculate exit/sale implications with BADR relief, and integrate with Net Worth, Estate/IHT, and user profile.

## What Already Exists
- [x] **Database table**: `business_interests` with core fields
- [x] **Model**: `app/Models/BusinessInterest.php`
- [x] **Vue Components**: `BusinessInterestsList.vue` (Coming Soon), `BusinessInterestCard.vue`
- [x] **Documentation**: `Dec23Updates/business.md` (UK business tax guide)

---

## Implementation Checklist

### Phase 1: Database Migration
- [ ] Create migration `add_tax_fields_to_business_interests.php`
- [ ] Update `BusinessInterest.php` model with new fillable fields

**New fields to add:**
| Field | Type | Description |
|-------|------|-------------|
| vat_registered | boolean | Is business VAT registered |
| vat_number | string | VAT registration number |
| utr_number | string | Unique Tax Reference |
| tax_year_end | date | Company year-end (for Ltd) |
| employee_count | integer | Number of employees |
| paye_reference | string | PAYE scheme reference |
| trading_status | enum | trading/dormant/pre_trading |
| acquisition_date | date | When business was acquired (BADR) |
| acquisition_cost | decimal | Original investment/cost basis |
| bpr_eligible | boolean | Business Property Relief eligible |
| industry_sector | string | Business industry |

---

### Phase 2: Backend API
- [ ] Create `StoreBusinessInterestRequest.php`
- [ ] Create `UpdateBusinessInterestRequest.php`
- [ ] Create `BusinessInterestService.php`
- [ ] Create `BusinessInterestController.php`
- [ ] Add routes to `api.php`

**API Endpoints:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/business-interests | List all businesses |
| GET | /api/business-interests/{id} | Show single business |
| POST | /api/business-interests | Create business |
| PUT | /api/business-interests/{id} | Update business |
| DELETE | /api/business-interests/{id} | Delete business |
| GET | /api/business-interests/{id}/tax-deadlines | Get tax deadlines |
| GET | /api/business-interests/{id}/exit-calculation | Get exit CGT calculation |

---

### Phase 3: Tax Deadlines Logic
- [ ] Implement `getTaxDeadlines()` in service

**Deadlines by Business Type:**

| Business Type | Deadline | Date |
|--------------|----------|------|
| Sole Trader/Partnership | Self Assessment (online) | 31 January |
| Sole Trader/Partnership | Self Assessment (paper) | 31 October |
| Sole Trader/Partnership | Payments on Account | 31 Jan & 31 July |
| Limited Company | Corporation Tax | 9 months + 1 day after year-end |
| Limited Company | Company Accounts | 9 months after year-end |
| Limited Company | CT600 Return | 12 months after year-end |
| Limited Company | Confirmation Statement | Annually |
| All (if VAT) | VAT Returns | Quarterly, 1 month + 7 days after |
| All (if employing) | PAYE/NIC | 22nd of following month |

---

### Phase 4: Exit/Sale CGT Calculator
- [ ] Implement `calculateExitScenario()` in service

**Calculation Logic:**
```
Capital Gain = (Sale Price * Ownership%) - (Acquisition Cost * Ownership%)

If BADR eligible (held 2+ years, trading business):
  CGT Rate = 10% (up to £1m lifetime limit)
Else:
  CGT Rate = 10%/20% (based on user's tax band)

CGT Due = Capital Gain * CGT Rate
Post-Tax Proceeds = (Sale Price * Ownership%) - CGT Due
```

---

### Phase 5: Frontend Components
- [ ] Create `businessInterestService.js`
- [ ] Create `businessInterests.js` Vuex store module
- [ ] Create `BusinessInterestForm.vue`
- [ ] Create `BusinessInterestDetailInline.vue`
- [ ] Update `BusinessInterestsList.vue` (remove Coming Soon)
- [ ] Update `BusinessInterestCard.vue` (add actions)
- [ ] Register store module in `store/index.js`

**Form Sections:**
1. Basic Info: name, type, company_number, industry
2. Ownership: ownership_type, ownership_percentage, joint_owner_id
3. Valuation: current_valuation, valuation_date, valuation_method
4. Financials: annual_revenue, annual_profit, annual_dividend_income
5. Tax & Compliance: vat_registered, vat_number, utr_number, tax_year_end, employee_count
6. Exit Planning: acquisition_date, acquisition_cost, bpr_eligible, trading_status

**Detail View Tabs:**
1. Overview - Business details, valuation, ownership
2. Tax Deadlines - List of relevant deadlines with dates
3. Exit Planning - CGT calculator with BADR
4. Notes - Description and notes

---

### Phase 6: Integrations
- [ ] Update `NetWorthDashboard.vue` - include in totals
- [ ] Update `IHTCalculationService.php` - add BPR integration
- [ ] Link to user profile for self-employed income (optional)

---

## Files to Create
| # | File | Status |
|---|------|--------|
| 1 | `database/migrations/XXXX_add_tax_fields_to_business_interests.php` | [ ] |
| 2 | `app/Http/Requests/BusinessInterest/StoreBusinessInterestRequest.php` | [ ] |
| 3 | `app/Http/Requests/BusinessInterest/UpdateBusinessInterestRequest.php` | [ ] |
| 4 | `app/Services/Business/BusinessInterestService.php` | [ ] |
| 5 | `app/Http/Controllers/Api/BusinessInterestController.php` | [ ] |
| 6 | `resources/js/services/businessInterestService.js` | [ ] |
| 7 | `resources/js/store/modules/businessInterests.js` | [ ] |
| 8 | `resources/js/components/NetWorth/BusinessInterestForm.vue` | [ ] |
| 9 | `resources/js/components/NetWorth/BusinessInterestDetailInline.vue` | [ ] |

## Files to Modify
| # | File | Status |
|---|------|--------|
| 1 | `app/Models/BusinessInterest.php` | [ ] |
| 2 | `routes/api.php` | [ ] |
| 3 | `resources/js/components/NetWorth/BusinessInterestsList.vue` | [ ] |
| 4 | `resources/js/components/NetWorth/BusinessInterestCard.vue` | [ ] |
| 5 | `resources/js/store/index.js` | [ ] |
| 6 | `app/Services/Estate/IHTCalculationService.php` | [ ] |
| 7 | `resources/js/views/NetWorth/NetWorthDashboard.vue` | [ ] |

---

## Key Patterns to Follow
- Use `@save` event on forms (not `@submit`)
- Use `CalculatesOwnershipShare` trait in controller
- Store FULL values, calculate user share in API response
- Card styling: purple (#a21caf) for business, badge colors per type
- Grid: `minmax(320px, 1fr)` for cards
- Follow PropertyController pattern for CRUD

## Testing Requirements
- [ ] Pest tests for BusinessInterestService calculations
- [ ] Test BADR eligibility logic (2-year rule)
- [ ] Test CGT calculation with different tax bands
- [ ] Test BPR integration with IHT
