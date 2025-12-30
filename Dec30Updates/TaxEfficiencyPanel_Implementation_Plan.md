# Tax Efficiency Panel Implementation Plan

## Overview
Implement the Tax Efficiency tab in the Portfolio Analysis section of the Investment module, replacing the current "Coming Soon" placeholder with a full tax dashboard featuring actionable recommendations.

## Current State
- **Backend**: Fully built - `TaxOptimizationAnalyzer.php` provides complete tax analysis
- **API Service**: All methods exist in `investmentService.js` (lines 220-316)
- **Frontend**: Tab exists but shows "Coming Soon" banner (InvestmentList.vue:199-210)

---

## Implementation Steps

### 1. Create TaxEfficiencyPanel.vue Component
**File**: `resources/js/components/Investment/TaxEfficiencyPanel.vue`

**Sections to include**:

#### Section A: Overview Cards (4-column grid)
- Tax Efficiency Score (0-100 with grade A-F)
- ISA Allowance Usage (progress bar, £X of £20,000)
- CGT Position (unrealised gains vs £3,000 allowance)
- Potential Annual Savings

#### Section B: ISA Allowance Tracker
- Progress bar showing usage
- Days remaining in tax year (urgency indicator)
- Action button: "Transfer to ISA" (opens modal with transfer guidance)

#### Section C: CGT Position Dashboard
- Unrealised gains total (from GIA accounts only)
- Unrealised losses total
- Net position vs CGT allowance
- Top 5 holdings with largest gains/losses

#### Section D: Tax-Loss Harvesting Table
- Table of holdings with unrealised losses
- Columns: Security, Loss Amount, Loss %, Tax Saving, Priority
- Action button: "Harvest Loss" (opens modal with 30-day rule warning)

#### Section E: Bed & ISA Suggestions
- Cards for each transfer opportunity
- Show: security, GIA value, gain on sale, annual tax saving
- Action button: "View Execution Plan" (opens step-by-step guide modal)

#### Section F: All Recommendations Summary
- Prioritised list from API `recommendations` array
- Priority badges (high/medium/low)
- Action buttons for each recommendation type

---

### 2. Update InvestmentList.vue
**File**: `resources/js/components/NetWorth/InvestmentList.vue`

**Changes**:
- Import TaxEfficiencyPanel component
- Replace lines 199-210 (Coming Soon wrapper) with:
```vue
<TaxEfficiencyPanel v-else-if="activePortfolioTab === 'taxefficiency'" />
```

---

### 3. Create Action Modals (3 modals)

#### ISATransferModal.vue
- Show remaining ISA allowance
- List eligible GIA holdings for transfer
- Step-by-step execution guide (not automated - user executes via broker)
- "Download Checklist" button

#### HarvestLossModal.vue
- Show selected holding details
- 30-day repurchase rule warning (bed-and-breakfasting)
- Calculate tax saving
- Suggest similar replacement securities
- "Download Execution Plan" button

#### BedAndISAWizardModal.vue
- Multi-step wizard:
  1. Review holding details
  2. Confirm ISA destination
  3. Review CGT implications
  4. Download execution checklist

---

### 4. Store Updates (Optional - may use local state)
**File**: `resources/js/store/modules/investment.js`

If needed, add:
- State: `taxOptimizationData`, `taxEfficiencyLoading`
- Action: `fetchTaxOptimizationData()`
- Getters: convenience getters for template binding

---

## API Methods to Use (already exist in investmentService.js)

```javascript
// Main analysis endpoint - returns everything
investmentService.analyzeTaxPosition()

// Individual endpoints if needed
investmentService.getISAStrategy()
investmentService.getCGTHarvestingOpportunities()
investmentService.getBedAndISAOpportunities()
investmentService.getTaxEfficiencyScore()
investmentService.getTaxRecommendations()
```

---

## Backend Response Structure (TaxOptimizationAnalyzer.php)

```javascript
{
  success: true,
  tax_year: "2024/25",
  current_position: {
    isa_allowance: 20000,
    isa_used: 5000,
    isa_remaining: 15000,
    isa_utilization: 25,
    cgt_allowance: 3000,
    unrealized_gains: 8500,
    unrealized_losses: 1200,
    net_unrealized_gains: 7300,
    dividend_allowance: 500,
    annual_dividend_income: 1800,
    dividend_excess: 1300
  },
  opportunities: [
    {
      type: "isa_underutilization",
      priority: "high",
      title: "ISA Allowance Available",
      description: "...",
      potential_saving: 450,
      action: "Transfer or contribute to ISA",
      details: { remaining_allowance: 15000, utilization: 25 }
    },
    // ... more opportunities
  ],
  recommendations: [
    {
      rank: 1,
      type: "isa_underutilization",
      priority: "high",
      title: "ISA Allowance Available",
      action: "Transfer or contribute to ISA",
      potential_saving: 450
    }
  ],
  potential_savings: {
    total_potential_savings: 1250,
    savings_by_type: { ... },
    timeframe: "annual"
  },
  efficiency_score: {
    score: 72,
    grade: "C",
    deductions: [...],
    interpretation: "Moderate tax efficiency..."
  }
}
```

---

## Key Files

### Files to Create
1. `resources/js/components/Investment/TaxEfficiencyPanel.vue`
2. `resources/js/components/Investment/ISATransferModal.vue`
3. `resources/js/components/Investment/HarvestLossModal.vue`
4. `resources/js/components/Investment/BedAndISAWizardModal.vue`

### Files to Modify
1. `resources/js/components/NetWorth/InvestmentList.vue` - Lines 199-210

### Reference Files (existing patterns)
- `resources/js/components/Investment/FeeBreakdown.vue` - Panel styling pattern
- `resources/js/components/Common/TaxStatusPanel.vue` - Tax display patterns
- `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php` - Backend response structure

---

## UK Tax Context (2024/25)

| Tax | Allowance | Source |
|-----|-----------|--------|
| ISA | £20,000 | `TaxConfigService::getISAAllowances()` |
| CGT | £3,000 | `TaxConfigService::getCapitalGainsTax()` |
| Dividend | £500 | `TaxConfigService::getDividendTax()` |

- Tax year: April 6 - April 5
- **Never hardcode** - always get from `TaxConfigService`

---

## UI/UX Guidelines

1. Use `currencyMixin` for all currency formatting
2. Follow existing card styling patterns from FeeBreakdown.vue
3. Responsive grid: 4 cols desktop, 2 cols tablet, 1 col mobile
4. Loading states with skeleton loaders
5. Error handling with retry buttons
6. Action modals are guidance-only (no automated trades)
7. Show tax year prominently with days remaining indicator

---

## Implementation Order

1. Create TaxEfficiencyPanel.vue with loading/error states
2. Wire up to analyzeTaxPosition() API
3. Build overview cards section
4. Build ISA tracker section
5. Build CGT position section
6. Build tax-loss harvesting table
7. Build Bed & ISA suggestions
8. Build recommendations summary
9. Create ISATransferModal
10. Create HarvestLossModal
11. Create BedAndISAWizardModal
12. Update InvestmentList.vue to use new component
13. Test with preview personas
