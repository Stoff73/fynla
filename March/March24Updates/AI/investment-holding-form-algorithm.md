# Investment Holding Form Algorithm — Complete Field-by-Field Map

**Date:** 24 March 2026 (after manual browser testing)
**Source:** `resources/js/components/Investment/HoldingForm.vue`
**Parent:** `resources/js/components/NetWorth/InvestmentDetailInline.vue`
**Route:** `/net-worth/investments` (navigates into account detail, then opens holding form)
**Entity type:** `investment_holding`

## Form Structure

Single-step modal form. Opens from within an account's detail view when "Enter Holdings" or "Add Holding" is clicked. The form requires an existing investment account.

## Navigation Flow for AI Fill

1. CoordinatingAgent looks up account by name/provider
2. Returns `fill_form` with `investment_account_id` in fields
3. `InvestmentList.vue` watches `pendingFill`, finds the account, calls `selectAccount()` to render detail view
4. `InvestmentDetailInline.vue` watches `pendingFill`, opens `HoldingForm` modal
5. `HoldingForm.vue` pre-sets fields and auto-submits

## Validation (validateForm — BLOCKING)

1. `investment_account_id` — required
2. `security_name` — required
3. `asset_type` — required
4. `allocation_percent` — required, 0-100

## formData Shape

```javascript
formData: {
  investment_account_id: '',   // select — REQUIRED (pre-selected from account)
  security_name: '',           // text — REQUIRED
  ticker: '',                  // text
  isin: '',                    // text
  asset_type: '',              // select — REQUIRED
  sub_type: null,              // select — only when asset_type = 'fund'
  allocation_percent: null,    // number — REQUIRED (0-100)
  purchase_price: null,        // number (optional)
  purchase_date: '',           // date (optional)
  current_price: null,         // number (optional)
  ocf_percent: 0,              // number (OCF as percentage, e.g. 0.22)
}
```

## Asset Type Options

| `<option value>` | Label |
|-------------------|-------|
| `uk_equity` | UK Equity |
| `us_equity` | US Equity |
| `international_equity` | International Equity |
| `fund` | Fund |
| `etf` | ETF |
| `bond` | Bond |
| `cash` | Cash |
| `alternative` | Alternative |
| `property` | Property |

## Fund Sub-Type (only when asset_type = 'fund')

| `<option value>` | Label |
|-------------------|-------|
| `equity_fund` | Equity Fund |
| `bond_fund` | Bond Fund |
| `mixed_fund` | Mixed Fund |
| `income_fund` | Income Fund |
| `index_fund` | Index Fund |
| `money_market_fund` | Money Market Fund |
| `property_fund` | Property Fund |

## AI Tool → Handler → Form Field Map

| AI param | Handler maps to | formData key |
|----------|----------------|-------------|
| `account_name` | Looked up → `investment_account_id` | `investment_account_id` |
| `security_name` | `security_name` | `security_name` |
| `ticker` | `ticker` | `ticker` |
| `asset_type` | `asset_type` | `asset_type` |
| `allocation_percent` | `allocation_percent` | `allocation_percent` |
| `purchase_price` | `purchase_price` | `purchase_price` |
| `current_price` | `current_price` | `current_price` |
| `ocf_percent` | `ocf_percent` | `ocf_percent` |

## Manual Browser Test Results (Step 4+5)

| # | Security | Asset Type | Allocation | OCF | Saved | Dashboard Correct | Result |
|---|----------|-----------|------------|-----|-------|-------------------|--------|
| 1 | Vanguard FTSE All-World (VWRL) | ETF | 60% | 0.22% | YES | Diversification: Well Diversified, Fees: 0.37%, Asset Allocation: ETF 100% | PASS |

## Test Scenarios for Grok

### Scenario 1: ETF holding
"I hold the Vanguard FTSE All-World ETF in my Vanguard ISA, ticker VWRL, it's about 60% of the account, OCF is 0.22%"

### Scenario 2: Fund holding
"I also have the iShares Core MSCI World fund in the same ISA, about 40%, ticker SWDA, OCF 0.20%"

### Scenario 3: UK equity
"In my Vanguard ISA I hold Lloyds Banking Group shares, about 10% of the account, ticker LLOY"
