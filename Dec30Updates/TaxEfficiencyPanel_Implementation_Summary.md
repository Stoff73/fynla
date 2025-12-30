# Tax Efficiency Panel - Implementation Summary

## Date: 30 December 2024

## Overview
Implemented a full Tax Efficiency dashboard in the Portfolio Analysis section of the Investment module, replacing the "Coming Soon" placeholder.

---

## Files Created

### 1. TaxEfficiencyPanel.vue
**Path**: `resources/js/components/Investment/TaxEfficiencyPanel.vue`

Main panel component with sections:
- **Tax Year Banner** - Shows current tax year with days remaining until year end
- **Overview Cards** - Tax efficiency score, ISA usage, CGT position, potential savings
- **ISA Allowance Tracker** - Progress bar, usage stats, "Transfer to ISA" action
- **CGT Position Dashboard** - Unrealised gains/losses, allowance status
- **Tax-Loss Harvesting Table** - Holdings with losses, harvest actions
- **Bed & ISA Suggestions** - Transfer opportunities with execution plan
- **Recommendations Summary** - Prioritised actionable items

### 2. ISATransferModal.vue
**Path**: `resources/js/components/Investment/ISATransferModal.vue`

Modal for ISA transfer guidance:
- Shows remaining ISA allowance
- Lists eligible GIA holdings for transfer
- Step-by-step execution instructions
- Important notes about Bed & ISA rules

### 3. HarvestLossModal.vue
**Path**: `resources/js/components/Investment/HarvestLossModal.vue`

Modal for tax-loss harvesting:
- Holding details with loss amount
- 30-day rule warning (bed-and-breakfasting)
- Tax saving calculation
- Alternative investment suggestions

### 4. BedAndISAWizardModal.vue
**Path**: `resources/js/components/Investment/BedAndISAWizardModal.vue`

4-step wizard for Bed & ISA execution:
1. Review holdings for transfer
2. Confirm ISA destination
3. CGT implications review
4. Execution checklist

---

## Files Modified

### InvestmentList.vue
**Path**: `resources/js/components/NetWorth/InvestmentList.vue`

Changes:
- Added import for `TaxEfficiencyPanel`
- Added to components registration
- Replaced Coming Soon wrapper (lines 199-210) with `<TaxEfficiencyPanel />`

### TaxOptimizationAnalyzer.php (Bug Fix)
**Path**: `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php`

**Bug**: ISA usage was calculated using `current_value` (account balance) instead of actual contributions.

**Fix**: Changed `calculateISAUsage()` method to use:
- Investment ISAs: `isa_subscription_current_year` field
- Savings ISAs: `isa_subscription_amount` with `isa_subscription_year` check

**Before** (incorrect):
```php
$usage += $account->current_value ?? 0;  // Wrong - uses total balance
```

**After** (correct):
```php
$usage += $account->isa_subscription_current_year ?? 0;  // Correct - uses actual contributions
```

---

## API Endpoint Used

**Endpoint**: `GET /api/investment/tax-optimization/analyze`

**Response Structure**:
```json
{
  "success": true,
  "data": {
    "tax_year": "2025/26",
    "current_position": {
      "isa_allowance": 20000,
      "isa_used": 0,
      "isa_remaining": 20000,
      "isa_utilization": 0,
      "cgt_allowance": 3000,
      "unrealized_gains": 11112,
      "unrealized_losses": 658,
      "net_unrealized_gains": 10454,
      "dividend_allowance": 500,
      "annual_dividend_income": 0,
      "dividend_excess": 0
    },
    "opportunities": [...],
    "recommendations": [...],
    "potential_savings": {
      "total_potential_savings": 1450,
      "savings_by_type": {...}
    },
    "efficiency_score": {
      "score": 70,
      "grade": "C",
      "interpretation": "..."
    }
  }
}
```

---

## Features

### Tax Efficiency Score
- Score 0-100 with grade A-F
- Deductions for: ISA underutilisation, unharvested losses, excess dividends, missed Bed & ISA opportunities
- Colour-coded display (green/amber/red)

### ISA Allowance Tracker
- Progress bar showing usage vs £20,000 allowance
- Days remaining until tax year end (April 5)
- Urgency indicator when approaching year end
- "Transfer to ISA" action button

### CGT Position Dashboard
- Unrealised gains from taxable accounts (GIA)
- Unrealised losses available for harvesting
- Net position vs £3,000 annual allowance
- Warning when gains exceed allowance

### Tax-Loss Harvesting
- Table of holdings with losses
- Priority ranking
- Tax saving calculation (loss × 20% CGT rate)
- "Harvest Loss" action with 30-day rule warning

### Bed & ISA Opportunities
- Identifies holdings with gains within CGT allowance
- Calculates transferable amount
- Shows potential annual tax saving
- Multi-step execution wizard

### Recommendations
- Prioritised list (high/medium/low)
- Potential saving per recommendation
- Actionable guidance

---

## UK Tax Context (2024/25)

| Tax | Allowance | Source |
|-----|-----------|--------|
| ISA | £20,000 | TaxConfigService |
| CGT | £3,000 | TaxConfigService |
| Dividend | £500 | TaxConfigService |

Tax year: April 6 - April 5

---

## Testing

Tested with `peak_earners` persona:
- ISA subscription: £0 (correctly shows remaining allowance)
- Unrealised gains: £11,112 (exceeds CGT allowance)
- 3 opportunities identified: ISA allowance, tax-loss harvesting, CGT excess
