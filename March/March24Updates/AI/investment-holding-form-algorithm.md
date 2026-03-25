# Investment Holding Form Algorithm — Complete Field-by-Field Map

**Date:** 25 March 2026 (updated for inline holdings in AccountForm)
**Source:** `resources/js/components/Investment/InlineHoldingsEditor.vue` (inline editor)
**Fallback:** `resources/js/components/Investment/HoldingForm.vue` (full detail modal)
**Parent:** `resources/js/components/Investment/AccountForm.vue` (inline editor embedded here)
**Detail view:** `resources/js/components/NetWorth/InvestmentDetailInline.vue` (always-visible holdings section)
**Route:** `/net-worth/investments`
**Entity type:** `investment_account` (with inline `holdings` array) or `investment_holding` (standalone)

## Form Structure — TWO Paths

### Path 1: Inline Holdings (PREFERRED — new account creation)

When creating a new investment account, holdings are added inline within AccountForm via `InlineHoldingsEditor.vue`. The AI sends `create_investment_account` with a `holdings` array parameter. The backend creates account + holdings in a single DB transaction.

**Eligible account types:** `isa`, `gia`, `onshore_bond`, `offshore_bond`, `vct`, `eis`
**Trigger:** Holdings section appears when `current_value > 0` for eligible types
**Fields per holding (simplified):** security_name, asset_type, allocation_percent, cost_basis

### Path 2: Standalone Holding (FALLBACK — add to existing account)

When adding holdings to an account that already exists, the AI uses `create_holding` tool. This navigates into the account detail view and opens the full `HoldingForm` modal.

**Trigger:** User says "add a holding to my X account"
**Fields:** All detailed fields (security_name, ticker, ISIN, asset_type, sub_type, allocation_percent, purchase_price, purchase_date, current_price, ocf_percent)

## Path 1: Inline Holdings — AI Tool → Form Field Map

### AI Tool: `create_investment_account` (with `holdings` parameter)

The `holdings` parameter is an array of objects, each with:

| AI param | formData key | Type | Required | Notes |
|----------|-------------|------|----------|-------|
| `security_name` | `holdings[].security_name` | string | Yes | e.g. "Vanguard FTSE All-World" |
| `asset_type` | `holdings[].asset_type` | string enum | Yes | See asset types below |
| `allocation_percent` | `holdings[].allocation_percent` | number | Yes | 0-100, total must not exceed 100 |
| `cost_basis` | `holdings[].cost_basis` | number/null | No | Total amount originally invested (£) |

### Navigation Flow for AI Fill (Path 1)

1. CoordinatingAgent `handleCreateInvestmentAccount()` receives input with `holdings` array
2. Maps account fields + passes `holdings` array through to `fields`
3. Returns `fill_form` with `entity_type: 'investment_account'`
4. `InvestmentList.vue` opens AccountForm
5. AccountForm `pendingFill` watcher sets `account_type` first, then field sequence
6. `highlightedField` watcher sets `formData.holdings = [{...}, {...}]`
7. `InlineHoldingsEditor` receives holdings via prop, renders inline rows
8. Auto-submit sends account + holdings to `POST /api/investment/accounts`
9. Backend creates account + holdings in single DB transaction
10. Any unallocated remainder auto-creates a Cash holding

### Backend Transaction (storeAccount)

```
POST /api/investment/accounts
{
  account_type: "isa",
  provider: "Vanguard",
  current_value: 100000,
  holdings: [
    { security_name: "Vanguard FTSE All-World", asset_type: "etf", allocation_percent: 60, cost_basis: 55000 },
    { security_name: "iShares UK Gilts", asset_type: "bond", allocation_percent: 25, cost_basis: 22000 }
  ]
}
→ Creates account + 2 holdings + auto Cash holding (15%)
→ All in DB::transaction (atomic)
```

### Cash Auto-Allocation Rules

- If total allocation < 100% AND user did NOT include a cash holding → auto-create Cash holding for remainder
- If user explicitly includes `asset_type: "cash"` → no auto-cash created (even if < 100%)
- Cash warning shown when effective cash < 5%

## Path 2: Standalone Holding — AI Tool → Form Field Map

### AI Tool: `create_holding`

| AI param | Handler maps to | formData key | Type | Required |
|----------|----------------|-------------|------|----------|
| `account_name` | Looked up → `investment_account_id` | `investment_account_id` | int | Yes |
| `security_name` | `security_name` | `security_name` | string | Yes |
| `ticker` | `ticker` | `ticker` | string | No |
| `asset_type` | `asset_type` | `asset_type` | string enum | Yes |
| `allocation_percent` | `allocation_percent` | `allocation_percent` | number | Yes |
| `purchase_price` | `purchase_price` | `purchase_price` | number | No |
| `current_price` | `current_price` | `current_price` | number | No |
| `ocf_percent` | `ocf_percent` | `ocf_percent` | number | No |

### Navigation Flow for AI Fill (Path 2)

1. CoordinatingAgent `handleCreateHolding()` looks up account by name/provider
2. Returns `fill_form` with `entity_type: 'investment_holding'`
3. `InvestmentList.vue` navigates into account detail view
4. `InvestmentDetailInline.vue` opens HoldingForm modal
5. HoldingForm pre-sets fields, auto-submits
6. `POST /api/investment/holdings` creates holding
7. `adjustCashHolding()` auto-adjusts existing Cash holding

## Asset Type Options (both paths)

| Value | Label | Use for |
|-------|-------|---------|
| `equity` | Equity | Generic equity |
| `uk_equity` | UK Equity | UK shares (LLOY, BARC) |
| `us_equity` | US Equity | US shares (AAPL, MSFT) |
| `international_equity` | International Equity | Non-UK/US shares |
| `fund` | Fund | OEICs, unit trusts |
| `etf` | ETF | Exchange-traded funds (VWRL, VUSA) |
| `bond` | Bond | Fixed income / gilts |
| `cash` | Cash | Cash holdings |
| `alternative` | Alternative | Commodities, crypto, etc. |
| `property` | Property | Property funds |

## Fund Sub-Type (Path 2 only — HoldingForm has this field, inline editor does not)

| Value | Label |
|-------|-------|
| `equity_fund` | Equity Fund |
| `bond_fund` | Bond Fund |
| `mixed_fund` | Mixed Fund |
| `income_fund` | Income Fund |
| `index_fund` | Index Fund |
| `money_market_fund` | Money Market Fund |
| `property_fund` | Property Fund |

## Validation

### Path 1 (Inline — StoreInvestmentAccountRequest)

- `holdings` — optional array
- `holdings.*.security_name` — required, string, max 255
- `holdings.*.asset_type` — required, in valid types
- `holdings.*.allocation_percent` — required, numeric, 0-100
- `holdings.*.cost_basis` — nullable, numeric, min 0
- **Custom:** total allocation_percent across all holdings must not exceed 100%

### Path 2 (Standalone — StoreHoldingRequest)

- `investment_account_id` — required, must exist
- `security_name` — required
- `asset_type` — required, in valid types
- `allocation_percent` — required, 0-100

## When to Use Which Path

| Scenario | Tool | Path |
|----------|------|------|
| "I have a Vanguard ISA worth £100k with VWRL and VUSA" | `create_investment_account` with `holdings` | Path 1 |
| "Add my ISA at Hargreaves Lansdown, £50k" | `create_investment_account` (no holdings) | Path 1 (no holdings) |
| "Add VWRL to my Vanguard ISA" (account exists) | `create_holding` | Path 2 |
| "I also hold iShares UK Gilts in that account" (account exists) | `create_holding` | Path 2 |

**Rule:** If the user mentions holdings AND an account in the same message, and the account doesn't exist yet → use `create_investment_account` with `holdings`. If the account already exists → use `create_holding`.

## Test Scenarios for Grok

### Scenario 1: New ISA with holdings (Path 1)
"I have a Vanguard Stocks and Shares ISA worth £100,000. It holds the Vanguard FTSE All-World ETF (about 60%, cost me £55,000) and iShares Core UK Gilts (25%, cost £22,000)"

**Expected:** `create_investment_account` called with:
- account_type: stocks_shares_isa
- provider: Vanguard
- current_value: 100000
- holdings: [{security_name: "Vanguard FTSE All-World", asset_type: "etf", allocation_percent: 60, cost_basis: 55000}, {security_name: "iShares Core UK Gilts", asset_type: "bond", allocation_percent: 25, cost_basis: 22000}]

**Result:** Account created with 3 holdings (2 user + 1 auto-cash at 15%)

### Scenario 2: New GIA without holdings (Path 1 — no holdings)
"I have a general investment account at Hargreaves Lansdown worth £95,000"

**Expected:** `create_investment_account` called without holdings
**Result:** Account created, no holdings

### Scenario 3: Add holding to existing account (Path 2)
"Add Vanguard FTSE All-World ETF to my Vanguard ISA, it's about 60% of the account, OCF is 0.22%"

**Expected:** `create_holding` called with account_name, security_name, asset_type, allocation_percent, ocf_percent
**Result:** Holding added to existing account via HoldingForm modal

### Scenario 4: New ISA with single holding (Path 1)
"I have a Fidelity ISA worth £40,000 all in their Global Equity Fund"

**Expected:** `create_investment_account` with holdings: [{security_name: "Fidelity Global Equity Fund", asset_type: "fund", allocation_percent: 100}]
**Result:** Account created with 1 holding at 100% (no auto-cash)

### Scenario 5: New bond with holdings (Path 1)
"I have an onshore bond at Prudential worth £200,000, split 70% in their Prudential Dynamic Growth fund and 30% in their Corporate Bond fund"

**Expected:** `create_investment_account` with account_type: onshore_bond, holdings array
**Result:** Account + 2 holdings created

## Files Involved

| File | Role |
|------|------|
| `app/Services/AI/XaiToolDefinitions.php` | Tool definitions — `create_investment_account` (with `holdings`) + `create_holding` |
| `app/Agents/CoordinatingAgent.php` | `handleCreateInvestmentAccount()` passes holdings, `handleCreateHolding()` for standalone |
| `app/Http/Controllers/Api/InvestmentController.php` | `storeAccount()` creates account + holdings in transaction |
| `app/Http/Requests/StoreInvestmentAccountRequest.php` | Validates holdings array |
| `resources/js/components/Investment/AccountForm.vue` | Embeds InlineHoldingsEditor, receives holdings via AI fill |
| `resources/js/components/Investment/InlineHoldingsEditor.vue` | Renders inline holding rows |
| `resources/js/components/Investment/HoldingForm.vue` | Full detail modal (Path 2 / edit mode) |
| `resources/js/components/NetWorth/InvestmentList.vue` | Routes AI fill to AccountForm or account detail |
| `resources/js/components/NetWorth/InvestmentDetailInline.vue` | Always-visible holdings section + HoldingForm modal |
