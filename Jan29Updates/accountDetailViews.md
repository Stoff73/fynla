# Account-Type-Specific Detail Views

## Overview

Create separate detail panel components for Employee Share Schemes and Private Investments, replacing the generic tabbed view (Holdings, Performance, Rebalancing, Fees) which doesn't apply to these account types.

## Account Type Routing

| Account Types | Detail Component |
|---------------|------------------|
| `saye`, `csop`, `emi`, `unapproved_options`, `rsu` | EmployeeShareSchemeDetail |
| `private_company`, `crowdfunding` | PrivateInvestmentDetail |
| `isa`, `gia`, `sipp`, `pension`, `nsi`, `vct`, `eis`, bonds | Existing tabbed view |

## Files to Create

### 1. EmployeeShareSchemeDetail.vue
**Location:** `resources/js/views/Investment/EmployeeShareSchemeDetail.vue`

**Header Metrics (4 cards):**
- Exercise Value / Grant Value (RSUs)
- Vested Value (current price × vested units)
- Vesting Progress %
- Days to Exercise Window / Days to Full Vest

**Sections:**
1. **Employer Details** - employer_name, ticker, listed status, ERS reference
2. **Grant Details** - grant_date, units_granted, exercise_price, market_value_at_grant
3. **Vesting Schedule** - vesting_type, progress bar, cliff_date, full_vest_date
4. **Current Status** - scheme_status, units_vested/unvested/exercised, current_share_price
5. **Exercise & Expiry** (options only) - window dates, exercise window status alert
6. **Tax Treatment** - tax-advantaged badge, CSOP 3-year date, PAYE info
7. **SAYE Savings** (SAYE only) - monthly savings, balance, maturity date
8. **Leaver Terms** - leaver_category, post-termination exercise period

### 2. PrivateInvestmentDetail.vue
**Location:** `resources/js/views/Investment/PrivateInvestmentDetail.vue`

**Header Metrics (4 cards):**
- Latest Valuation (or investment amount)
- Return Multiple (MOIC)
- Tax Relief Status
- Disposal Restriction countdown / Company Status

**Sections:**
1. **Company Details** - legal name, trading name, registration, sector, website, platform
2. **Investment Details** - date, amount, funding_round, instrument_type, shares, valuations
3. **Ownership & Legal** - share_class, voting/dividend rights, holding structure
4. **UK Tax Relief** (EIS/SEIS) - relief type, certificate, holding period countdown, clawback warning
5. **Status & Valuation** - company_status, latest_valuation, MOIC, unrealised gain/loss
6. **Exit Details** (if exited) - exit_type, date, proceeds, fees, exit MOIC

## File to Modify

### InvestmentDetailInline.vue
**Location:** `resources/js/components/NetWorth/InvestmentDetailInline.vue`

**Changes:**
1. Add computed property `detailComponentType` to route based on account_type
2. Import new components
3. Conditionally render appropriate detail component
4. Keep header (badges, name, Edit/Delete buttons) the same for all types
5. Replace tab content area with specialized component when applicable

```javascript
detailComponentType() {
  const type = this.account.account_type;
  if (['saye', 'csop', 'emi', 'unapproved_options', 'rsu'].includes(type)) {
    return 'employee-share-scheme';
  }
  if (['private_company', 'crowdfunding'].includes(type)) {
    return 'private-investment';
  }
  return 'standard';
}
```

## Patterns to Follow

From existing `AccountSummaryPanel.vue`:
- Single `account` prop
- Use `currencyMixin` for formatting
- Section structure: `.details-section` > `.section-title` > `.details-grid` > `.detail-item`
- Grid: `repeat(auto-fill, minmax(250px, 1fr))`
- Conditional rendering with `v-if` for optional fields

## Implementation Order

1. Create `EmployeeShareSchemeDetail.vue`
2. Create `PrivateInvestmentDetail.vue`
3. Update `InvestmentDetailInline.vue` with routing logic
4. Test each account type

## Verification

1. Navigate to Investments, click on a SAYE/CSOP/EMI account
   - Should show employer details, grant info, vesting progress bar
   - Should NOT show Holdings/Performance/Fees tabs

2. Click on a Private Company/Crowdfunding account
   - Should show company details, investment info, tax relief section
   - Should show Exit Details if company_status = 'exited'

3. Click on standard ISA/GIA account
   - Should show existing tabbed view (Holdings, Performance, Fees, etc.)
