# Retirement Income Feature - Implementation Summary

**Date:** 29 December 2024
**Branch:** `retirementincome` → merged to `main`
**Commit:** `f575cae`

## Overview

Added a new "Retirement Income" tab to the Retirement section, enabling users to model tax-optimized drawdown strategies from their target retirement age with interactive sliders and visualizations.

## Files Created

### Backend

| File | Purpose |
|------|---------|
| `app/Services/Retirement/RetirementIncomeService.php` | Core service (872 lines) - income optimization, tax calculation, fund projections |

### Frontend

| File | Purpose |
|------|---------|
| `resources/js/components/Retirement/RetirementIncomeTab.vue` | Main tab component with income target, account selection, sliders |
| `resources/js/components/Retirement/TaxBreakdownCard.vue` | Detailed tax breakdown with per-source line items and band usage |
| `resources/js/components/Retirement/IncomeSourceSlider.vue` | Interactive slider with tax treatment badges |
| `resources/js/components/Retirement/FundDepletionChart.vue` | ApexCharts visualization of fund values over time |

## Files Modified

| File | Changes |
|------|---------|
| `app/Http/Controllers/Api/RetirementController.php` | Added `getRetirementIncome()` and `calculateRetirementIncome()` methods |
| `routes/api.php` | Added routes: `GET /retirement/income`, `POST /retirement/income/calculate` |
| `resources/js/components/NetWorth/PensionList.vue` | Added 4th tab: "Retirement Income" |
| `resources/js/services/retirementService.js` | Added `getRetirementIncome()` and `calculateRetirementIncome()` API methods |
| `resources/js/store/modules/retirement.js` | Added state, mutations, actions for retirement income feature |

## Key Features Implemented

### 1. Tax-Optimized Default Allocations

Income sources are allocated in tax-efficient order:
1. **Guaranteed Income First** - State Pension, DB Pension (uses Personal Allowance)
2. **Tax-Free Sources** - PCLS (25% pension lump sum), ISA withdrawals
3. **Taxable Drawdown** - DC pension drawdown (fills remaining target)
4. **GIA** - General Investment Account (last, most taxable)

### 2. State Pension Age Logic

- State Pension only included in allocations when `retirement_age >= state_pension_age`
- DB Pension only included when `retirement_age >= normal_retirement_age`
- Prevents showing income that won't be available at chosen retirement age

### 3. Interactive Income Sliders

Each income source has a slider showing:
- Source type badge (PCLS, Pension, ISA, GIA, Savings)
- Available amount / max withdrawal
- Tax treatment badge:
  - **0% Tax-free** (green) - PCLS, ISA
  - **Var Taxable** (indigo) - Pension drawdown at marginal rate
  - **20%/40%/45%** (yellow/orange/red) - Known tax rates

### 4. Detailed Tax Breakdown Card

Shows each income source as a line item with:
- Source badge and name
- Amount and tax status (Tax-free or -£X tax)
- **For taxable income, expanded breakdown:**
  - Personal Allowance (0%): £12,570 → £0
  - Basic Rate (20%): £24,918 → -£4,984
  - Higher Rate (40%): £X → -£X (if applicable)

Summary section shows:
- Gross Income
- Total Tax
- Net Income
- Effective tax rate badge

### 5. Fund Depletion Chart

ApexCharts stacked area chart showing:
- X-axis: Age (retirement age to 100)
- Y-axis: Fund value (£)
- Series: DC Pension, ISA, GIA, Savings (aggregated by type)
- State Pension Age annotation at 67
- Depletion age warnings if funds run out before 90

### 6. Tax Band Usage Visualization

Progress bars showing:
- Personal Allowance: used/remaining
- Basic Rate Band: used/remaining
- Higher Rate Band: used/remaining (if applicable)

## API Response Structure

### GET /api/retirement/income

```json
{
  "target_income": 75000,
  "retirement_age": 60,
  "available_accounts": [...],
  "allocations": [
    {
      "source_type": "dc_pension_pcls",
      "source_id": 11,
      "name": "Global Finance Corp Pension - Tax-Free Cash (PCLS)",
      "annual_amount": 18750,
      "tax_rate": 0,
      "tax_treatment": "tax_free"
    },
    {
      "source_type": "dc_pension_drawdown",
      "source_id": 11,
      "name": "Global Finance Corp Pension - Drawdown",
      "annual_amount": 37487.5,
      "tax_rate": null,
      "tax_treatment": "taxable"
    }
  ],
  "tax_breakdown": {
    "sources": [...],
    "gross_income": 75000,
    "tax_free_income": 37512.5,
    "taxable_income": 37487.5,
    "total_tax": 4984,
    "net_income": 70016,
    "effective_rate": 0.066,
    "band_usage": {
      "personal_allowance": { "used": 12570, "remaining": 0 },
      "basic_rate": { "used": 24918, "remaining": 12783 }
    }
  },
  "fund_projections": [
    {
      "age": 60,
      "dc_pension": 446888,
      "isa": 228248,
      "gia": 98800,
      "savings": 50000,
      "total_funds": 823936
    }
  ],
  "depletion_ages": {}
}
```

## Bug Fixes During Implementation

1. **Income sources disappearing after load** - Fixed by including `available_accounts` in `calculateIncomeScenario` return
2. **State pension shown for early retirees** - Added retirement age check before including state/DB pension
3. **Fund depletion chart blank** - Added aggregation by fund type (`dc_pension`, `isa`, `gia`, `savings`)
4. **Pension drawdown showing "0% Tax-free"** - Added `isTaxable` check and "Var" badge for marginal rate sources
5. **Tax breakdown showing £0** - Added `tax_free_income` and `taxable_income` aliases for frontend

## Testing

Tested with preview personas:
- **young_family** (James Carter) - Retirement age 65, modest DC pension
- **peak_earners** (David Mitchell) - Retirement age 60, multiple pensions, SIPP, ISAs

## Usage

1. Navigate to Net Worth → Retirement → "Retirement Income" tab
2. View default tax-optimized allocations
3. Adjust sliders to change income from each source
4. Watch tax breakdown update in real-time
5. Review fund depletion chart for sustainability
