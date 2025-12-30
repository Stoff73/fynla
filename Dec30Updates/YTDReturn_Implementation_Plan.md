# Annualised Return Calculation - Gross and Net of Fees

## Overview
Fix the YTD Return display in InvestmentDetailInline.vue which currently shows "N/A". Calculate the annualised return from holdings data (cost_basis vs current_value) and display both gross return and net-of-fees return.

## Requirements
1. Calculate **annualised** return from actual holdings data (current_value vs cost_basis)
2. Show both **gross return** and **net-of-fees return** (both annualised)
3. Fees must match what's shown in the Fees tab (platform + weighted OCF + advisor)
4. Return calculation must match the holdings data displayed in Holdings tab
5. **Default to 3-year holding period** when purchase dates are not available
6. Show warning when using default period

---

## Data Available

**Holdings data (per holding):**
- `quantity` - units held
- `purchase_price` - initial unit cost
- `current_price` - current unit cost
- `current_value` - total current value
- `cost_basis` - quantity × purchase_price

**Fee data (from account + holdings):**
- `account.platform_fee_percent` - platform fee %
- `account.advisor_fee_percent` - advisor fee %
- `holding.ocf_percent` - fund OCF % per holding

---

## Calculations

### Gross Return (%)
```javascript
totalCostBasis = sum of all holdings' cost_basis
totalCurrentValue = sum of all holdings' current_value
grossReturnPercent = ((totalCurrentValue - totalCostBasis) / totalCostBasis) * 100
```

### Total Fee (%) - matching Fees tab
```javascript
platformFee = account.platform_fee_percent || 0
advisorFee = account.advisor_fee_percent || 0
weightedOCF = sum(holding.current_value * holding.ocf_percent) / totalCurrentValue
totalFeePercent = platformFee + advisorFee + weightedOCF
```

### Net Return (%)
```javascript
netReturnPercent = grossReturnPercent - totalFeePercent
```

---

## File to Modify

### `resources/js/components/NetWorth/InvestmentDetailInline.vue`

**Add computed properties:**
```javascript
// Calculate total cost basis from holdings
totalCostBasis() {
  if (!this.account.holdings?.length) return 0;
  return this.account.holdings.reduce((sum, h) => {
    const costBasis = h.cost_basis || (h.quantity * h.purchase_price) || 0;
    return sum + costBasis;
  }, 0);
},

// Calculate total current value from holdings
totalHoldingsValue() {
  if (!this.account.holdings?.length) return 0;
  return this.account.holdings.reduce((sum, h) => sum + (h.current_value || 0), 0);
},

// Gross return percentage
grossReturnPercent() {
  if (!this.totalCostBasis || this.totalCostBasis === 0) return null;
  return ((this.totalHoldingsValue - this.totalCostBasis) / this.totalCostBasis) * 100;
},

// Total fee percentage (matching Fees tab calculation)
totalFeePercent() {
  const platformFee = parseFloat(this.account.platform_fee_percent) || 0;
  const advisorFee = parseFloat(this.account.advisor_fee_percent) || 0;

  // Weighted average OCF
  let weightedOCF = 0;
  if (this.totalHoldingsValue > 0 && this.account.holdings?.length) {
    const totalWeightedOCF = this.account.holdings.reduce((sum, h) => {
      return sum + ((h.current_value || 0) * (parseFloat(h.ocf_percent) || 0));
    }, 0);
    weightedOCF = totalWeightedOCF / this.totalHoldingsValue;
  }

  return platformFee + advisorFee + weightedOCF;
},

// Net return (gross minus fees)
netReturnPercent() {
  if (this.grossReturnPercent === null) return null;
  return this.grossReturnPercent - this.totalFeePercent;
},
```

**Modify template (lines 59-64):**

Replace single YTD Return card with expanded display showing both gross and net:

```html
<div class="bg-gray-50 rounded-lg p-4">
  <p class="text-sm text-gray-600">Total Return</p>
  <div class="flex items-baseline gap-2">
    <p class="text-2xl font-bold" :class="getReturnColorClass(grossReturnPercent)">
      {{ formatReturn(grossReturnPercent) }}
    </p>
    <span class="text-xs text-gray-500">gross</span>
  </div>
  <div v-if="grossReturnPercent !== null" class="mt-1 flex items-baseline gap-2">
    <p class="text-lg font-semibold" :class="getReturnColorClass(netReturnPercent)">
      {{ formatReturn(netReturnPercent) }}
    </p>
    <span class="text-xs text-gray-500">net of {{ totalFeePercent.toFixed(2) }}% fees</span>
  </div>
</div>
```

---

## UI Result

```
+------------------------+
| Total Return           |
| +12.50% gross          |
| +11.55% net of 0.95%   |
+------------------------+
```

- Gross return: Calculated from holdings (current_value vs cost_basis)
- Net return: Gross minus total fees (platform + OCF + advisor)
- Fee percentage displayed matches the Fees tab exactly

---

## Implementation Notes

1. **No backend changes needed** - all data is already available in the account object
2. **Null handling** - Show "N/A" if no holdings or no cost_basis data
3. **Fee calculation matches AccountFeesPanel.vue** (lines 156-212) exactly
4. **Return calculation matches HoldingsTable.vue** unrealized gain/loss logic
