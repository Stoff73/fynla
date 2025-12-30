# Annualised Return Implementation Summary

## Overview
Replaced the "N/A" YTD Return display with a properly calculated annualised return showing both gross and net-of-fees figures.

## Key Design Decision
When purchase dates are not available, the system defaults to a **3-year holding period**. This ensures:
- Annual fees can be properly deducted (you can't subtract annual fees from a total return)
- The maths makes sense (both returns and fees are annualised)
- Users are clearly informed when defaults are being used

---

## Files Modified

### `resources/js/components/NetWorth/InvestmentDetailInline.vue`

**New Computed Properties:**
- `totalCostBasis()` - Sum of all holdings' cost basis
- `totalHoldingsValue()` - Sum of all holdings' current value
- `weightedHoldingPeriodYears()` - Weighted average holding period (defaults to 3 years)
- `usingDefaultHoldingPeriod()` - Boolean flag for when using 3-year default
- `totalReturnPercent()` - Raw total return percentage
- `grossReturnPercent()` - Annualised gross return
- `netReturnPercent()` - Annualised net return (gross - fees)
- `totalFeePercent()` - Platform + weighted OCF + advisor fees

**Annualisation Logic:**
```javascript
// For periods < 3 months: linear extrapolation
annualizedReturn = (totalReturn / years) * 100;

// For periods >= 3 months: compound annualisation
annualizedReturn = (Math.pow(1 + totalReturn, 1 / years) - 1) * 100;
```

**Template Changes:**
- Shows "Annualised Return" header
- Gross return with "p.a. gross" suffix
- Net return with "p.a. net of X.XX% fees" suffix
- Warning note when using 3-year default

### `resources/js/views/Investment/AccountHoldingsPanel.vue`

**New Features:**
- Info banner when holdings lack purchase dates
- Purchase Date column in holdings table
- "3yr default" badge for holdings without dates
- `holdingsWithoutDates` computed property
- `formatDate()` method

---

## UI Result

### Annualised Return Card
```
+--------------------------------+
| Annualised Return              |
| +5.56% p.a. gross              |
| +3.84% p.a. net of 1.72% fees  |
| *Based on 3-year default       |
+--------------------------------+
```

### Holdings Table
```
| Name        | Type   | Units | Purchase Date | Initial Cost | ...
|-------------|--------|-------|---------------|--------------|
| Fundsmith   | Equity | 351   | 3yr default   | £85.50       |
| Scottish MT | Equity | 2500  | 15 Jun 2022   | £8.40        |
```

### Info Banner (when dates missing)
```
⚠️ 3 holdings without purchase date
Annualised returns use a 3-year default holding period.
Add purchase dates for more accurate return calculations.
```

---

## Calculations

### Gross Annualised Return
```
totalReturn = (currentValue - costBasis) / costBasis
years = weightedAverageHoldingPeriod (default: 3)
annualisedReturn = ((1 + totalReturn)^(1/years) - 1) × 100
```

### Total Fee Percentage
```
platformFee = account.platform_fee_percent
advisorFee = account.advisor_fee_percent
weightedOCF = Σ(holding.value × holding.ocf) / totalValue
totalFee = platformFee + advisorFee + weightedOCF
```

### Net Annualised Return
```
netReturn = grossAnnualisedReturn - totalFeePercent
```

---

## Example Calculation

**Portfolio:**
- Cost basis: £80,584.50
- Current value: £95,028.00
- Holding period: 3 years (default)

**Returns:**
- Total return: (95,028 - 80,584.50) / 80,584.50 = 17.92%
- Annualised: ((1.1792)^(1/3) - 1) × 100 = **5.56% p.a.**

**Fees:**
- Platform: 0.45%
- Advisor: 0.75%
- Weighted OCF: 0.52%
- Total: **1.72% p.a.**

**Net Return:**
- 5.56% - 1.72% = **3.84% p.a. net**

---

## Formatting

Uses centralised `currencyMixin`:
- `formatReturnPercent(value)` - Returns "+X.XX%" or "-X.XX%" or "N/A"
- `formatPercentage(value)` - Returns "X.XX%"

Both use the mixin's `formatPercentage()` method for consistent 2 decimal places.
