# Investment Portfolio Summary - YTD Return Fix

**Date:** 2025-12-31
**Status:** Implemented

## Problem

The Investment Portfolio Summary card in `InvestmentList.vue` shows YTD Return as 0% because the Vuex getter reads from `state.analysis?.returns?.ytd_return` which is not populated by the backend.

## Solution

Calculate portfolio-wide weighted returns directly in the component, using the same calculation logic already working in `InvestmentDetailInline.vue` for individual accounts. Display both gross and net of fees returns.

## Implementation

### File: `resources/js/components/NetWorth/InvestmentList.vue`

#### 1. Add computed properties for portfolio return calculation

```javascript
// After the existing diversificationLabel computed property, add:

// Calculate weighted portfolio gross return
portfolioGrossReturn() {
  if (!this.accounts.length) return null;

  let totalWeightedReturn = 0;
  let totalValue = 0;

  for (const account of this.accounts) {
    const accountValue = parseFloat(account.current_value) || 0;
    if (accountValue <= 0) continue;

    // Calculate account's gross return using same logic as InvestmentDetailInline
    const accountReturn = this.calculateAccountGrossReturn(account);
    if (accountReturn !== null) {
      totalWeightedReturn += accountReturn * accountValue;
      totalValue += accountValue;
    }
  }

  return totalValue > 0 ? totalWeightedReturn / totalValue : null;
},

// Calculate weighted portfolio net return (after fees)
portfolioNetReturn() {
  if (!this.accounts.length) return null;

  let totalWeightedReturn = 0;
  let totalValue = 0;

  for (const account of this.accounts) {
    const accountValue = parseFloat(account.current_value) || 0;
    if (accountValue <= 0) continue;

    const grossReturn = this.calculateAccountGrossReturn(account);
    const fees = this.calculateAccountFees(account);

    if (grossReturn !== null) {
      const netReturn = grossReturn - fees;
      totalWeightedReturn += netReturn * accountValue;
      totalValue += accountValue;
    }
  }

  return totalValue > 0 ? totalWeightedReturn / totalValue : null;
},
```

#### 2. Add helper methods

```javascript
// In methods section:

calculateAccountGrossReturn(account) {
  // Get holdings with cost basis and current value
  const holdings = account.holdings || [];
  if (!holdings.length) return null;

  let totalCostBasis = 0;
  let totalCurrentValue = 0;
  let weightedYears = 0;
  let totalValueForWeighting = 0;

  for (const holding of holdings) {
    const costBasis = parseFloat(holding.cost_basis) || 0;
    const currentValue = parseFloat(holding.current_value) || 0;

    if (costBasis > 0) {
      totalCostBasis += costBasis;
      totalCurrentValue += currentValue;

      // Calculate holding period
      let years = 3; // Default 3-year assumption
      if (holding.purchase_date) {
        const purchaseDate = new Date(holding.purchase_date);
        const now = new Date();
        years = (now - purchaseDate) / (365.25 * 24 * 60 * 60 * 1000);
        if (years < 0.01) years = 0.01; // Minimum to avoid division issues
      }

      weightedYears += years * currentValue;
      totalValueForWeighting += currentValue;
    }
  }

  if (totalCostBasis <= 0) return null;

  const avgYears = totalValueForWeighting > 0 ? weightedYears / totalValueForWeighting : 3;
  const totalReturn = (totalCurrentValue - totalCostBasis) / totalCostBasis;

  // Annualize: linear for <3 months, compound for longer
  if (avgYears < 0.25) {
    return (totalReturn / avgYears) * 100;
  } else {
    return (Math.pow(1 + totalReturn, 1 / avgYears) - 1) * 100;
  }
},

calculateAccountFees(account) {
  const platformFee = parseFloat(account.platform_fee_percent) || 0;
  const advisorFee = parseFloat(account.advisor_fee_percent) || 0;

  // Weighted average OCF from holdings
  let weightedOCF = 0;
  const holdings = account.holdings || [];
  const totalValue = holdings.reduce((sum, h) => sum + (parseFloat(h.current_value) || 0), 0);

  if (totalValue > 0) {
    for (const holding of holdings) {
      const value = parseFloat(holding.current_value) || 0;
      const ocf = parseFloat(holding.ocf_percent) || 0;
      weightedOCF += (value / totalValue) * ocf;
    }
  }

  return platformFee + advisorFee + weightedOCF;
},
```

#### 3. Update template - Replace single return with dual display

**Before (lines 139-143):**
```vue
<div class="summary-item returns" :class="ytdReturn >= 0 ? 'positive' : 'negative'">
  <p class="summary-label">YTD Return</p>
  <p class="summary-value" :class="ytdReturn >= 0 ? 'text-green-600' : 'text-red-600'">{{ formatReturn(ytdReturn) }}</p>
  <p class="summary-count">{{ holdingsCount }} holding{{ holdingsCount !== 1 ? 's' : '' }}</p>
</div>
```

**After:**
```vue
<div class="summary-item returns" :class="(portfolioGrossReturn || 0) >= 0 ? 'positive' : 'negative'">
  <p class="summary-label">Annualised Return</p>
  <div class="return-values">
    <div class="return-row">
      <span class="return-label">Gross</span>
      <span class="return-value" :class="(portfolioGrossReturn || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
        {{ portfolioGrossReturn !== null ? formatReturn(portfolioGrossReturn) : 'N/A' }}
      </span>
    </div>
    <div class="return-row">
      <span class="return-label">Net of fees</span>
      <span class="return-value" :class="(portfolioNetReturn || 0) >= 0 ? 'text-green-600' : 'text-red-600'">
        {{ portfolioNetReturn !== null ? formatReturn(portfolioNetReturn) : 'N/A' }}
      </span>
    </div>
  </div>
  <p class="summary-count">{{ holdingsCount }} holding{{ holdingsCount !== 1 ? 's' : '' }}</p>
</div>
```

#### 4. Add CSS for dual return display

```css
.return-values {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.return-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.return-label {
  font-size: 13px;
  color: #6b7280;
}

.return-value {
  font-size: 18px;
  font-weight: 700;
}
```

## Calculation Logic

The return calculation follows the same pattern as `InvestmentDetailInline.vue`:

1. **Per-account gross return:**
   - Sum cost basis and current values across all holdings
   - Calculate weighted average holding period (default 3 years if no purchase dates)
   - Compute total return: `(currentValue - costBasis) / costBasis`
   - Annualize: linear extrapolation for <3 months, compound for longer

2. **Per-account fees:**
   - Platform fee + Advisor fee + Weighted average OCF from holdings

3. **Portfolio-wide returns:**
   - Value-weighted average of per-account returns
   - Gross: weighted average of gross returns
   - Net: weighted average of (gross - fees) per account

## Files to Modify

| File | Changes |
|------|---------|
| `resources/js/components/NetWorth/InvestmentList.vue` | Add computed properties, helper methods, update template and CSS |

## Testing

Test with preview personas (peak_earners, entrepreneur) to verify:
1. Gross return displays correctly (positive or negative with appropriate colour)
2. Net return is lower than gross by fee amount
3. Handles accounts with no holdings gracefully (shows N/A)
4. Handles accounts with no cost basis data
5. Default 3-year holding period is used when purchase dates unavailable

## Implementation Summary

All changes were made to `resources/js/components/NetWorth/InvestmentList.vue`:

| Change | Lines |
|--------|-------|
| Added `portfolioGrossReturn` computed property | 320-338 |
| Added `portfolioNetReturn` computed property | 341-363 |
| Added `calculateAccountGrossReturn()` method | 582-623 |
| Added `calculateAccountFees()` method | 626-643 |
| Updated template with dual return display | 139-156 |
| Added CSS for `.return-values`, `.return-row`, `.return-label`, `.return-value` | 1015-1035 |
| Removed unused `ytdReturn` from mapGetters | 318-323 |
