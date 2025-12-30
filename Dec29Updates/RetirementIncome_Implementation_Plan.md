# Retirement Income Feature - Implementation Plan

**Date**: 29 December 2025
**Branch**: `retirementincome`
**Status**: Planning Complete

---

## Overview

Add a "Retirement Income" tab to the Retirement section in Net Worth module. This feature enables users to model tax-optimized drawdown strategies from retirement age (default 68), with interactive sliders and visualizations showing fund depletion over time.

---

## User Requirements

| Requirement | Implementation |
|-------------|----------------|
| **Income Target** | Default to profile's `target_retirement_income`, allow custom override |
| **Spouse Assets** | Toggle between individual and combined household view |
| **Drawdown Order** | PCLS (0%) -> Personal Allowance (0%) -> ISA (0%) -> Taxable pension (20%+) |
| **Account Types** | Configurable selection (pensions, ISAs, GIAs, bonds, etc.) |
| **Interactive Sliders** | Each source has slider with immediate tax feedback |
| **Visualization** | Chart showing income vs capital value, fund depletion, tax implications |

---

## Tax-Optimized Drawdown Order

The system applies income in this order to minimise tax liability:

1. **PCLS (Pension Commencement Lump Sum)** - 25% of DC pension pot, 0% tax
2. **Personal Allowance** - Pension income up to £12,570, 0% tax
3. **ISA Withdrawals** - Unlimited, 0% tax (preserves tax-free growth as long as possible)
4. **Taxable Pension/GIA Income** - Marginal rate (20%/40%/45%)

---

## Files to Create

### Backend

| File | Purpose |
|------|---------|
| `app/Services/Retirement/RetirementIncomeService.php` | Core service for income optimisation, tax calculation, fund projections |
| `app/Http/Requests/Retirement/CalculateIncomeRequest.php` | Request validation for income calculation |

### Frontend

| File | Purpose |
|------|---------|
| `resources/js/components/Retirement/RetirementIncomeTab.vue` | Main tab component orchestrating all sub-components |
| `resources/js/components/Retirement/IncomeTargetCard.vue` | Display/override target income |
| `resources/js/components/Retirement/IncomeSourceSlider.vue` | Individual slider with tax badge |
| `resources/js/components/Retirement/TaxBreakdownCard.vue` | Real-time tax calculation display |
| `resources/js/components/Retirement/FundDepletionChart.vue` | ApexCharts visualisation |

---

## Files to Modify

| File | Changes |
|------|---------|
| `app/Http/Controllers/Api/RetirementController.php` | Add 3 methods: `getRetirementIncome()`, `calculateRetirementIncome()`, `getIncomeAccounts()` |
| `routes/api.php` | Add routes: `GET /retirement/income`, `POST /retirement/income/calculate`, `GET /retirement/income/accounts` |
| `resources/js/components/NetWorth/PensionList.vue` | Add 4th tab: `{ id: 'income', label: 'Retirement Income' }` |
| `resources/js/store/modules/retirement.js` | Add state, mutations, actions for retirement income |
| `resources/js/services/retirementService.js` | Add API methods for income endpoints |

---

## API Endpoints

### GET /api/retirement/income
Returns initial retirement income configuration with default allocations.

### POST /api/retirement/income/calculate
Calculates tax breakdown and projections based on user's slider allocations.

**Request Body**:
```json
{
  "income_allocations": [
    { "source_type": "dc_pension_pcls", "source_id": 1, "annual_amount": 5000 },
    { "source_type": "dc_pension_drawdown", "source_id": 1, "annual_amount": 12570 },
    { "source_type": "isa", "source_id": 2, "annual_amount": 10000 }
  ],
  "include_spouse": false,
  "custom_target_income": 35000
}
```

### GET /api/retirement/income/accounts
Returns all accounts eligible for income selection.

---

## API Response Structure

```json
{
  "target_income": 35000,
  "retirement_age": 68,
  "available_accounts": [
    { "id": 1, "type": "dc_pension", "name": "SIPP", "value": 250000, "pcls_available": 62500 },
    { "id": 2, "type": "isa", "name": "Stocks & Shares ISA", "value": 45000 }
  ],
  "default_allocations": [
    { "source_type": "dc_pension_pcls", "source_id": 1, "amount": 5000, "tax_rate": 0, "tax_treatment": "tax_free" },
    { "source_type": "dc_pension_drawdown", "source_id": 1, "amount": 12570, "tax_rate": 0, "tax_treatment": "personal_allowance" }
  ],
  "tax_breakdown": {
    "gross_income": 35000,
    "tax_free_income": 17570,
    "taxable_income": 17430,
    "total_tax": 3486,
    "net_income": 31514,
    "effective_rate": 0.0996,
    "band_usage": {
      "personal_allowance": { "used": 12570, "remaining": 0 },
      "basic_rate": { "used": 17430, "remaining": 25230 }
    }
  },
  "fund_projections": [
    { "age": 68, "dc_pension": 245000, "isa": 45000, "total_income": 35000 },
    { "age": 69, "dc_pension": 238000, "isa": 42000, "total_income": 35000 }
  ],
  "depletion_ages": {
    "isa": 85,
    "dc_pension": 92
  }
}
```

---

## Backend Service: RetirementIncomeService.php

### Key Methods

```php
class RetirementIncomeService
{
    public function __construct(
        private TaxConfigService $taxConfig,
        private DecumulationPlanner $decumulationPlanner,
    ) {}

    /**
     * Get initial configuration with default allocations
     */
    public function getRetirementIncomeConfig(int $userId, bool $includeSpouse = false): array

    /**
     * Calculate tax breakdown based on slider allocations
     */
    public function calculateIncomeScenario(int $userId, array $incomeAllocations): array

    /**
     * Get all accounts eligible for income (pensions, ISAs, GIAs)
     */
    public function getAvailableAccounts(int $userId, bool $includeSpouse = false): array

    /**
     * Project fund values from retirement age to 100
     */
    public function projectFundDepletion(int $userId, array $incomeAllocations): array
}
```

### Tax Calculation Logic

Uses existing `TaxBandTracker` to track band consumption as income sources are stacked:

```php
$tracker = new TaxBandTracker($this->taxConfig->getIncomeTax());

// 1. PCLS - tax-free, doesn't consume bands
$breakdown['pcls'] = ['amount' => $pclsAmount, 'tax' => 0, 'rate' => 0];

// 2. Pension income - uses personal allowance first
$paUsed = min($pensionIncome, $tracker->getRemainingPersonalAllowance());
$breakdown['personal_allowance'] = ['amount' => $paUsed, 'tax' => 0];

// 3. ISA - tax-free, doesn't affect bands
$breakdown['isa'] = ['amount' => $isaWithdrawal, 'tax' => 0, 'rate' => 0];

// 4. Remaining taxable income
$taxAllocation = $tracker->allocateIncome($remainingTaxable);
```

---

## Frontend Components

### RetirementIncomeTab.vue

Main orchestrating component:
- Loads data on mount via Vuex action
- Manages all slider state
- Debounces API calls (300ms)
- Coordinates child components

### IncomeSourceSlider.vue

Interactive slider for each income source:
- Range slider (min=0, max=available amount)
- Tax treatment badge (colour-coded: green=0%, yellow=20%, etc.)
- Live display of annual amount
- Emits change events for recalculation

### TaxBreakdownCard.vue

Real-time tax display:
- Personal Allowance usage bar
- Basic/Higher/Additional rate breakdown
- Total tax paid
- Net income after tax
- Effective tax rate percentage

### FundDepletionChart.vue

ApexCharts area chart:
- X-axis: Age (retirement age to 100)
- Y-axis: Fund value (£)
- Multiple series per fund (DC Pension, ISA, GIA)
- Vertical annotation at State Pension Age (67)
- Colour gradient showing fund depletion
- Tooltip showing tax impact when sources deplete

---

## Vuex Store Additions

### State
```javascript
retirementIncome: null,
retirementIncomeLoading: false,
incomeAccounts: [],
incomeAllocations: [],
includeSpouseAssets: false,
customTargetIncome: null,
```

### Actions
```javascript
async fetchRetirementIncome({ commit, state })
async calculateRetirementIncome({ commit, state }, allocations)
async fetchIncomeAccounts({ commit, state }, includeSpouse)
updateIncomeAllocation({ commit, dispatch }, { sourceId, amount })
toggleSpouseAssets({ commit, dispatch }, include)
setCustomTargetIncome({ commit }, amount)
```

---

## Implementation Steps

### Step 1: Backend Service
1. Create `RetirementIncomeService.php`
2. Implement `getAvailableAccounts()` - aggregate pensions, ISAs, GIAs
3. Implement `calculateIncomeScenario()` - tax calculation with TaxBandTracker
4. Implement `projectFundDepletion()` - year-by-year projection

### Step 2: API Endpoints
1. Create `CalculateIncomeRequest.php` validation
2. Add controller methods to `RetirementController.php`
3. Add routes to `routes/api.php`

### Step 3: Frontend - Tab Registration
1. Add tab to `PensionList.vue` tabs array
2. Import and register `RetirementIncomeTab` component
3. Add Vuex state/mutations/actions
4. Add API methods to `retirementService.js`

### Step 4: Frontend - Core Components
1. Create `RetirementIncomeTab.vue` shell
2. Create `IncomeTargetCard.vue`
3. Create `IncomeSourceSlider.vue` (follow StrategyCard.vue pattern)
4. Wire up slider -> API calculation with 300ms debounce

### Step 5: Frontend - Visualisation
1. Create `TaxBreakdownCard.vue`
2. Create `FundDepletionChart.vue` with ApexCharts
3. Add spouse toggle functionality
4. Style and responsive design

### Step 6: Testing
1. Backend: Pest tests for RetirementIncomeService
2. Frontend: Component tests
3. Integration test with preview personas

---

## Key Technical Decisions

| Decision | Rationale |
|----------|-----------|
| **Slider Debouncing (300ms)** | Balance between responsiveness and API load |
| **ApexCharts** | Consistent with existing charts in the app |
| **Spouse Data via joint_owner_id** | Reuse existing reciprocal records pattern |
| **Default Retirement Age 68** | If not set in profile, use 68 as sensible UK default |
| **Server-side tax calculation** | Accuracy; instant badge updates client-side for UX |

---

## Dependencies

- **Existing Services**: `TaxConfigService`, `TaxBandTracker`, `DecumulationPlanner`
- **Frontend**: ApexCharts (already installed)
- **No new packages required**

---

## UK Tax Context (2025/26)

| Tax Band | Range | Rate |
|----------|-------|------|
| Personal Allowance | £0 - £12,570 | 0% |
| Basic Rate | £12,571 - £50,270 | 20% |
| Higher Rate | £50,271 - £125,140 | 40% |
| Additional Rate | £125,140+ | 45% |

- PCLS: 25% of DC pension pot, tax-free
- ISA withdrawals: Tax-free
- Personal Allowance tapers at £100,000+ income
