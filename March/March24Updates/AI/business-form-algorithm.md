# Business Interest Form Algorithm — Complete Field-by-Field Map

**Date:** 24 March 2026 (after manual browser testing)
**Source:** `resources/js/components/NetWorth/BusinessInterestForm.vue`
**Parent:** `resources/js/components/NetWorth/BusinessInterestsList.vue`
**Route:** `/net-worth/business`
**Entity type:** `business_interest`

## Form Structure

**6-step multi-step wizard** with steps: Basic Info, Ownership, Valuation, Financials, Tax, Exit Planning.

AI fill watchers already implemented — uses `beginFieldSequence` (single sequence, not per-step). The form auto-submits after all fields are set via the `filling` watcher (250ms delay).

**Save button** only appears on step 6 (last step) or in edit mode. The AI auto-submit bypasses step navigation — it sets all fields then calls `handleSubmit()` directly.

## Validation (validateForm — BLOCKING)

These fields MUST be set or the form will NOT save:

1. `business_name` — required, non-empty
2. `business_type` — required, non-empty
3. `current_valuation` — required, > 0
4. `valuation_date` — required (defaults to today in `data()`, so OK if AI doesn't send it)

## formData Shape

```javascript
form: {
  // Step 1: Basic Info
  business_name: '',           // text — REQUIRED
  business_type: '',           // select — REQUIRED: sole_trader, partnership, limited_company, llp, other
  company_number: '',          // text
  industry_sector: '',         // text
  country: 'United Kingdom',   // text (default)
  trading_status: 'trading',   // select: trading, dormant, pre_trading
  description: '',             // textarea

  // Step 2: Ownership
  ownership_type: 'individual', // radio: individual, joint, trust
  ownership_percentage: 100,    // number (1-99 for joint, 100 for individual, 0 for trust)
  joint_owner_id: null,         // number (linked spouse id)

  // Step 3: Valuation
  current_valuation: null,      // number — REQUIRED, > 0
  valuation_date: '2026-03-24', // date — REQUIRED (defaults to today)
  valuation_method: '',         // select: self_assessed, professional_valuation, earnings_multiple, asset_based, discounted_cash_flow

  // Step 4: Financials
  annual_revenue: null,         // number
  annual_profit: null,          // number (can be negative)
  annual_dividend_income: null, // number (limited company only)
  employee_count: 0,            // number

  // Step 5: Tax
  vat_registered: false,        // checkbox
  vat_number: '',               // text (shows when vat_registered)
  utr_number: '',               // text
  tax_year_end: '',             // date (shows for limited_company/llp)
  paye_reference: '',           // text (shows when employee_count > 0)

  // Step 6: Exit Planning
  acquisition_date: '',         // date
  acquisition_cost: null,       // number
  bpr_eligible: false,          // checkbox
  notes: '',                    // textarea
}
```

## Select Option Values

### business_type
| `<option value>` | Label |
|-------------------|-------|
| `sole_trader` | Sole Trader |
| `partnership` | Partnership |
| `limited_company` | Limited Company |
| `llp` | LLP (Limited Liability Partnership) |
| `other` | Other |

### trading_status
| `<option value>` | Label |
|-------------------|-------|
| `trading` | Trading |
| `dormant` | Dormant |
| `pre_trading` | Pre-Trading |

### ownership_type (radio buttons)
| Value | Label |
|-------|-------|
| `individual` | Individual Owner (100%) |
| `joint` | Joint Ownership (defaults to 50%) |
| `trust` | Trust (0%) |

### valuation_method
| `<option value>` | Label |
|-------------------|-------|
| `self_assessed` | Self Assessed |
| `professional_valuation` | Professional Valuation |
| `earnings_multiple` | Earnings Multiple |
| `asset_based` | Asset Based |
| `discounted_cash_flow` | Discounted Cash Flow |

## Pre-Set Requirements

The `pendingFill` watcher does NOT pre-set any selects before `beginFieldSequence`. This means `business_type` must be in the field sequence and will be set via the `highlightedField` watcher. Since it's a `<select>` with `v-model`, setting the value directly should work.

**Potential issue:** The `business_type` select doesn't have `ai-fill-highlight` class on it, but the `highlightedField` watcher does a catch-all `this.form[fieldKey] = value` which should still set it.

## AI Tool → Handler → Form Field Map

### Current tool params (XaiToolDefinitions)
| AI param | Handler maps to | formData key |
|----------|----------------|-------------|
| `business_name` | `business_name` | `business_name` |
| `business_type` | `business_type` | `business_type` |
| `ownership_percentage` | `ownership_percentage` | `ownership_percentage` |
| `estimated_value` | `current_valuation` | `current_valuation` |
| `annual_profit` | `annual_profit` | `annual_profit` |

### Additional params to add
| AI param | formData key | Why |
|----------|-------------|-----|
| `industry_sector` | `industry_sector` | Common info users mention |
| `annual_revenue` | `annual_revenue` | Users often mention turnover |
| `annual_dividend_income` | `annual_dividend_income` | Ltd company owners mention dividends |
| `employee_count` | `employee_count` | Common info |

## Parent Save Flow

`BusinessInterestsList.vue` → `handleSave(formData)`:
- New: `this.createBusiness(formData)` (Vuex action)
- Edit: `this.updateBusiness({ id, data: formData })`
- Then: `completeFill()` + `closeFormModal()`

## Test Scenarios

### Scenario 1: Sole Trader
"I have a sole trader business called Smith Consulting in the consulting sector, worth about £150,000, making £60,000 profit a year"

### Scenario 2: Limited Company
"I own a limited company called Acme Technologies Ltd, it's a tech company worth £500,000 with revenue of £250,000 and profit of £80,000, I take £40,000 in dividends"

### Scenario 3: Partnership
"I have a 50% share in a partnership called Jones & Partners, it's a law firm valued at £300,000 with annual revenue of £400,000"

### Scenario 4: LLP
"I'm a member of an LLP called Digital Solutions LLP, it's an IT consultancy worth £200,000, my share is 33%, profit is £90,000 a year"

## Manual Browser Test Results (Step 4+5)

| # | Type | Filled | Saved to DB | Dashboard Card Correct | Result |
|---|------|--------|-------------|----------------------|--------|
| 1 | Limited Company | All 6 steps | YES | Ltd Co / Trading / Acme Technologies Ltd / £500k / £250k rev / £80k profit / BPR Eligible | PASS |
