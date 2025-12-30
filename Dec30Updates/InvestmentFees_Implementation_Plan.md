# Investment Fees Tab Implementation Plan

> **Note**: Copy this file to `Dec30Updates/InvestmentFees_Implementation_Plan.md` after approval.

## Summary
Implement a comprehensive fees breakdown for the Investment detail view, showing per-holding fees (OCF), platform fees, and advisor fees with annual cost calculations.

## Fee Structure

| Fee Type | Source | Calculation |
|----------|--------|-------------|
| Platform Fee | `investment_accounts.platform_fee_percent` | account_value × rate |
| Holding Fees (OCF) | `holdings.ocf_percent` | holding_value × rate |
| Advisor Fee | `investment_accounts.advisor_fee_percent` (NEW) | account_value × rate |
| **Total** | Sum of above | All fees combined |

## Files to Modify

### 1. Database Migration (NEW)
**File:** `database/migrations/2025_12_30_XXXXXX_add_advisor_fee_to_investment_accounts.php`

```php
Schema::table('investment_accounts', function (Blueprint $table) {
    $table->decimal('advisor_fee_percent', 5, 4)->default(0)->after('platform_fee_percent');
});
```

### 2. InvestmentAccount Model
**File:** `app/Models/Investment/InvestmentAccount.php`

- Add `advisor_fee_percent` to `$fillable`
- Add to `$casts` as float

### 3. Persona JSON Files (4 files)
Add `advisor_fee_percent` to investment accounts:
- `resources/js/data/personas/young_family.json` - 0% (self-directed)
- `resources/js/data/personas/peak_earners.json` - 0.75% (advised)
- `resources/js/data/personas/widow.json` - 1% (advised)
- `resources/js/data/personas/entrepreneur.json` - 0.5% (advised)

### 4. PreviewUserSeeder.php
**File:** `database/seeders/PreviewUserSeeder.php`

Map `advisor_fee_percent` from JSON to database in createInvestmentAccounts()

### 5. AccountFeesPanel.vue (MAIN CHANGES)
**File:** `resources/js/views/Investment/AccountFeesPanel.vue`

Remove "Coming Soon" banner and implement:

**Summary Cards (top):**
- Platform Fee: `X.XX%`
- Average Fund Fee (OCF): weighted average of holdings
- Advisor Fee: `X.XX%`
- Total Cost: sum of all fees

**Annual Cost Breakdown Section:**
| Fee | Rate | Annual Cost |
|-----|------|-------------|
| Platform Fee | 0.45% | £427 |
| Fund Fees (OCF) | 0.51% | £485 |
| Advisor Fee | 0.75% | £712 |
| **Total** | **1.71%** | **£1,624** |

**Per-Holding Fee Breakdown Section:**
| Holding | Value | OCF | Annual Cost |
|---------|-------|-----|-------------|
| Fundsmith Equity | £35,000 | 0.95% | £332.50 |
| Scottish Mortgage IT | £25,000 | 0.34% | £85.00 |
| Vanguard FTSE All-World | £35,000 | 0.22% | £77.00 |
| **Total Fund Fees** | £95,000 | 0.51% avg | £494.50 |

**10-Year Fee Impact Section:**
Show projected cumulative fees over 10 years assuming current value and 5% growth.

### 6. InvestmentDetailInline.vue
**File:** `resources/js/components/NetWorth/InvestmentDetailInline.vue`

Remove Coming Soon wrapper from Fees tab (similar to Performance tab fix).

## Computed Properties for AccountFeesPanel.vue

```javascript
computed: {
  // Platform fee as decimal (0.45% = 0.0045)
  platformFeeRate() {
    return parseFloat(this.account.platform_fee_percent) / 100 || 0;
  },

  // Advisor fee as decimal
  advisorFeeRate() {
    return parseFloat(this.account.advisor_fee_percent) / 100 || 0;
  },

  // Weighted average OCF across holdings
  weightedAverageOCF() {
    const holdings = this.account.holdings || [];
    if (holdings.length === 0) return 0;
    const totalValue = holdings.reduce((sum, h) => sum + (h.current_value || 0), 0);
    if (totalValue === 0) return 0;
    return holdings.reduce((sum, h) => {
      const weight = (h.current_value || 0) / totalValue;
      return sum + weight * (h.ocf_percent || 0);
    }, 0);
  },

  // Annual costs
  annualPlatformFee() {
    return this.account.current_value * this.platformFeeRate;
  },

  annualAdvisorFee() {
    return this.account.current_value * this.advisorFeeRate;
  },

  annualFundFees() {
    return (this.account.holdings || []).reduce((sum, h) => {
      return sum + (h.current_value || 0) * ((h.ocf_percent || 0) / 100);
    }, 0);
  },

  totalAnnualFees() {
    return this.annualPlatformFee + this.annualFundFees + this.annualAdvisorFee;
  },

  totalFeePercentage() {
    return (this.platformFeeRate + this.weightedAverageOCF / 100 + this.advisorFeeRate) * 100;
  }
}
```

## Implementation Steps

1. **Create migration** - Add `advisor_fee_percent` to investment_accounts
2. **Update model** - Add field to InvestmentAccount fillable/casts
3. **Update persona JSONs** - Add advisor fees to investment accounts
4. **Update seeder** - Map advisor_fee_percent from JSON
5. **Update AccountFeesPanel.vue** - Implement full fees breakdown UI
6. **Update InvestmentDetailInline.vue** - Remove Coming Soon wrapper
7. **Run migration and reseed** - Apply changes
8. **Test** - Verify fees display correctly for all personas

## Sample Data (peak_earners - David's ISA £95,000)

| Fee Type | Rate | Annual Cost |
|----------|------|-------------|
| Platform (HL) | 0.45% | £427.50 |
| Fund Fees | 0.51% weighted | £485.33 |
| Advisor | 0.75% | £712.50 |
| **Total** | **1.71%** | **£1,625.33** |

**Per Holding:**
| Holding | Value | OCF | Cost |
|---------|-------|-----|------|
| Fundsmith Equity | £35,051 | 0.95% | £332.98 |
| Scottish Mortgage IT | £25,000 | 0.34% | £85.00 |
| Vanguard FTSE All-World | £34,977 | 0.22% | £76.95 |
