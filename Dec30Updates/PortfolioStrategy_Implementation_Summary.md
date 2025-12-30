# Portfolio Strategy Implementation Summary

## Overview
Replaced the "Coming Soon" Strategy tab in InvestmentList.vue with a fully functional portfolio strategy panel that aggregates recommendations from tax, fee, and rebalancing services with actionable execution buttons.

## Files Created

### Backend

**`app/Services/Investment/PortfolioStrategyService.php`**
- Main aggregation service combining recommendations from:
  - `TaxOptimizationAnalyzer` - ISA allowance, CGT, Bed & ISA, tax-loss harvesting
  - `FeeAnalyzer` - High-fee holdings, portfolio fee assessment
  - `DriftAnalyzer` - Rebalancing recommendations
- New `analyzeBondWrapperOpportunities()` method for GIA > Bond wrapper recommendations
- Prioritization logic with constants:
  - PRIORITY_TAX = 1
  - PRIORITY_WRAPPER = 2
  - PRIORITY_FEES = 3
  - PRIORITY_REBALANCING = 4
- Bond wrapper criteria:
  - GIA balance >= £50,000
  - User is higher/additional rate taxpayer
  - Recommends Onshore Bond (default) or Offshore Bond (>£100k + additional rate)

**`app/Http/Controllers/Api/Investment/PortfolioStrategyController.php`**
- `index()` - Portfolio-level strategy aggregation
- `forAccount($accountId)` - Account-specific strategy

### Frontend

**`resources/js/views/Investment/PortfolioStrategyPanel.vue`**
- Main component with:
  - Summary stats (total savings, recommendation count, high priority count, tax efficiency score/grade)
  - View toggle (Portfolio / Per-Account)
  - Grouped recommendation sections by category (Tax, Wrapper, Fees, Rebalancing)
  - Loading and error states
- Handles modal interactions for:
  - ISATransferModal
  - BedAndISAWizardModal
  - HarvestLossModal
  - BondWrapperInfoModal

**`resources/js/components/Investment/StrategyRecommendationCard.vue`**
- Reusable card component with:
  - Priority badge (high/medium/low with colors)
  - Category badge (tax/wrapper/fees/rebalancing)
  - Potential savings display
  - Action buttons based on recommendation type:
    - `isa_transfer` → "Transfer Now"
    - `bed_and_isa` → "View Plan"
    - `harvest_loss` → "Harvest Losses"
    - `navigate` → "View Details"
    - `info` → "Learn More"

**`resources/js/components/Investment/BondWrapperInfoModal.vue`**
- Educational modal explaining:
  - Onshore vs Offshore Bond wrappers
  - Tax deferral benefits
  - Top-slicing relief explanation
  - Suitability criteria
  - Next steps guidance

## Files Modified

**`routes/api.php`**
```php
use App\Http\Controllers\Api\Investment\PortfolioStrategyController;

Route::get('/portfolio-strategy', [PortfolioStrategyController::class, 'index']);
Route::get('/portfolio-strategy/account/{accountId}', [PortfolioStrategyController::class, 'forAccount']);
```

**`resources/js/services/investmentService.js`**
```javascript
async getPortfolioStrategy() {
    const response = await api.get('/investment/portfolio-strategy');
    return response.data;
},
async getAccountStrategy(accountId) {
    const response = await api.get(`/investment/portfolio-strategy/account/${accountId}`);
    return response.data;
},
```

**`resources/js/components/NetWorth/InvestmentList.vue`**
- Removed "Coming Soon" wrapper from Strategy tab
- Replaced `<Recommendations />` with `<PortfolioStrategyPanel />`
- Added `handleStrategyNavigate()` method for tab navigation
- Updated imports and component registration

## API Response Structure

```json
{
  "success": true,
  "summary": {
    "total_potential_savings": 2638.53,
    "recommendation_count": 8,
    "high_priority_count": 7,
    "tax_efficiency_score": 68.7,
    "tax_efficiency_grade": "D"
  },
  "recommendations": [
    {
      "id": "isa_underutilization_xxx",
      "category": "tax",
      "type": "isa_underutilization",
      "priority": 1,
      "title": "ISA Allowance Available",
      "description": "You have £20,000 of unused ISA allowance...",
      "potential_saving": 275,
      "urgency": "high",
      "action_type": "isa_transfer",
      "action_data": { "modal": "ISATransferModal", ... }
    }
  ],
  "by_account": [...],
  "tax_analysis": {...}
}
```

## Recommendation Types

| Type | Category | Priority | Action |
|------|----------|----------|--------|
| `isa_underutilization` | tax | 1 | ISATransferModal |
| `bed_and_isa` | tax | 1 | BedAndISAWizardModal |
| `tax_loss_harvesting` | tax | 1 | HarvestLossModal |
| `cgt_excess_gains` | tax | 1 | Info only |
| `bond_wrapper` | wrapper | 2 | BondWrapperInfoModal |
| `high_total_fees` | fees | 3 | Navigate to Fees tab |
| `high_fee_holding` | fees | 3 | Info only |
| `drift_threshold_exceeded` | rebalancing | 4 | Navigate to Rebalancing tab |

## Test Results

**peak_earners persona:**
- 8 recommendations
- ISA allowance, tax-loss harvesting, CGT excess gains
- Bond wrapper for AJ Bell GIA (£94,996 balance, additional rate taxpayer)
- Portfolio fees above average, high-fee fund
- Rebalancing for 2 accounts (Hargreaves Lansdown ISA, AJ Bell GIA)

**young_family persona:**
- 3 recommendations
- ISA allowance available
- Portfolio fees above average
- Rebalancing for Vanguard ISA

## UI Layout

```
+------------------------------------------------------------------+
| Investment Strategy                                    [Refresh]  |
+------------------------------------------------------------------+
| Summary Cards:                                                    |
| [Total Savings £2,639] [8 Recommendations] [7 High Priority] [D]  |
+------------------------------------------------------------------+
| View: [Portfolio] [Per Account]                                   |
+------------------------------------------------------------------+
| TAX ACTIONS                                                       |
|   [Card] ISA Allowance Available           [Transfer Now]         |
|   [Card] Tax-Loss Harvesting               [Harvest Losses]       |
+------------------------------------------------------------------+
| WRAPPER OPTIMISATION                                              |
|   [Card] Consider Bond Wrapper for GIA     [Learn More]           |
+------------------------------------------------------------------+
| FEE REDUCTION                                                     |
|   [Card] Portfolio Fees Above Average      [View Details]         |
|   [Card] High-Fee Fund: Fundsmith          [Learn More]           |
+------------------------------------------------------------------+
| REBALANCING                                                       |
|   [Card] Rebalancing Recommended: ISA      [View Details]         |
+------------------------------------------------------------------+
```

## Key Implementation Notes

1. **Account Name Fallback**: Uses `provider + account_type` if `account_name` is null
2. **Tax Band Calculation**: Uses income fields directly from User model (no userProfile relationship)
3. **Bond Wrapper Thresholds**:
   - Minimum balance: £50,000
   - Offshore bond: £100,000+ and additional rate taxpayer
4. **Priority Sorting**: Recommendations sorted by priority, then urgency
5. **Modal Integration**: Existing modals reused via event emission to parent component
