# Integrated Investment Holdings — Design Spec

**Date:** 24 March 2026
**Status:** Approved
**Branch:** To be created from `grokAI`

## Summary

Consolidate the "Add Holding" workflow into the "Add/Edit Investment Account" form so users can enter holdings inline when creating or editing an investment account. Also add an always-visible holdings section to the account detail view.

## Problem

Currently, creating an investment account and adding holdings are completely separate workflows:
1. User creates an investment account via `AccountForm`
2. User must navigate to the account detail view
3. User must find the holdings management (currently hidden behind a broken/invisible tab bar)
4. User adds holdings one-by-one via a separate `HoldingForm` modal

This is friction-heavy, especially during onboarding. Most users know their holdings at the time they're entering the account.

## Eligible Account Types

The inline holdings section only appears for these account types:
- `isa` — Stocks & Shares ISA
- `gia` — General Investment Account
- `onshore_bond` — Onshore Bond
- `offshore_bond` — Offshore Bond
- `vct` — Venture Capital Trust
- `eis` — Enterprise Investment Scheme

**Note:** `seis` is not a separate account type — it is a `tax_relief_type` within private company accounts. `nsi` is not currently a selectable account type in `AccountForm.vue`. Neither is included in the eligible list.

**Excluded:** NSI, private company, crowdfunding, employee share schemes (SAYE, CSOP, EMI, unapproved options, RSU), and "other" — for these, the account itself IS the holding or holdings are not applicable.

## Design

### 1. New Component: `InlineHoldingsEditor.vue`

**Location:** `resources/js/components/Investment/InlineHoldingsEditor.vue`

**Props:**
- `accountValue` (Number, required) — the account's `current_value`, used to calculate holding values
- `holdings` (Array, default `[]`) — existing holdings (for edit mode)
- `accountId` (Number, nullable) — existing account ID (null for create)

**Emits:**
- `update:holdings` — emits the current holdings array to parent

**Visibility trigger:** Only renders when `accountValue > 0`.

**Layout:** Spreadsheet-style inline rows.

| Column | Field | Type | Required | Notes |
|--------|-------|------|----------|-------|
| Security Name | `security_name` | Text input | Yes | e.g. "Vanguard FTSE All-World" |
| Asset Type | `asset_type` | Select dropdown | Yes | equity, uk_equity, us_equity, international_equity, fund, etf, bond, cash, alternative, property (matches `StoreHoldingRequest::getAssetTypes()`) |
| Allocation % | `allocation_percent` | Number input | Yes | 0-100, validated against running total |
| Amount Invested | `cost_basis` | Currency input | No | Total amount originally invested in this holding |

**Additional UI elements:**
- Column headers above rows (subtle, small text)
- "+ Add Holding" button (dashed border, full width) — adds a new empty row
- "x" button on each row — removes the row
- Running total bar: "X% allocated, Y% remaining (£Z)"
- Cash remainder row (read-only, muted styling): shows unallocated % and calculated value
- Warning banner if cash < 5%: "At least 5% cash is advised — return-producing assets may need to be sold to cover fees"
- Hard cap: total allocation cannot exceed 100%. The allocation % input should prevent entry that would exceed 100%.

**Edit mode behaviour:**
- When `accountId` is provided and `holdings` prop has data, existing holdings render as editable inline rows
- Each row has an additional "Details" link/icon → emits `open-holding-details` event with the holding object (or holding ID for persisted holdings). The parent (`AccountForm`) renders `HoldingForm` as a nested modal, pre-populated with the holding data. On save from `HoldingForm`, the parent updates the holding via `PUT /api/investment/holdings/{id}` (for existing) or updates the local holdings array (for unsaved). `AccountForm` must import `HoldingForm` and manage a `showHoldingForm` boolean + `editingHolding` data property.
- Changes to inline fields (allocation %, cost) are tracked locally and emitted to parent

**Cash holding logic:**
- If total allocation < 100%, the cash remainder row shows the difference
- **Cash warning rule (single consolidated rule):** Show warning when the effective cash allocation (explicit cash holdings + auto-remainder) is less than 5%: "At least 5% cash is advised — return-producing assets may need to be sold to cover fees". This covers both cases: 100% allocated with no cash, and remainder < 5%.
- On save, if remainder > 0% AND no user-submitted holding has `asset_type = 'cash'`, auto-create a Cash holding for the remainder. If the user already included a cash-type holding, do NOT create a duplicate — the user's explicit cash holding counts toward the total.
- If user explicitly adds a Cash holding (asset_type = 'cash'), it counts toward the 100%

### 2. Modified: `AccountForm.vue`

**Changes:**
- Import and embed `InlineHoldingsEditor` component
- Show it below existing account fields, conditionally:
  - `account_type` is in eligible types list
  - `current_value` > 0
- Maintain a `holdings` array in form data
- On save emit: include `holdings` array in the payload alongside account data
- **Critical:** Add `'holdings'` to the `allowedFields` array in `submitForm()` — the existing filter strips any key not in this list, so without this change the holdings data will be silently dropped before reaching the backend.
- Works in both `context="standalone"` (modal) and `context="onboarding"` modes. Note: the form's context prop uses `'standalone'` not `'modal'` — use the existing terminology.

**Eligible types constant (shared):**
```javascript
const HOLDABLE_ACCOUNT_TYPES = ['isa', 'gia', 'onshore_bond', 'offshore_bond', 'vct', 'eis'];
```

### 3. Modified: Account Detail View (`InvestmentDetailInline.vue`)

**Changes:**
- Add an always-visible "Holdings" section below the existing performance/projection panels
- Shows for eligible account types only
- Renders a compact read-only list of holdings:
  - Security Name | Asset Type | Allocation % | Current Value
- Each row has a "Details" button → opens `HoldingForm` modal pre-populated for editing
- "Add Holding" button at bottom → opens `HoldingForm` modal with `investment_account_id` pre-selected
- Empty state text: "No holdings — default allocation is 100% cash"
- Holdings data comes from the account's `holdings` relationship (already loaded via API)

### 4. Backend: `InvestmentController::storeAccount()`

**Changes to existing method:**

After creating the account, check for optional `holdings` array in the request. If present:

```php
DB::transaction(function () use ($validated, &$account) {
    // Create account (existing logic)
    $account = InvestmentAccount::create($validated);

    // Create holdings if provided — use $validated not $request to respect validation
    $holdings = $validated['holdings'] ?? [];
    if (!empty($holdings)) {
        $hasCashHolding = false;

        foreach ($holdings as $holdingData) {
            $currentValue = ($account->current_value * $holdingData['allocation_percent']) / 100;

            if (($holdingData['asset_type'] ?? '') === 'cash') {
                $hasCashHolding = true;
            }

            $account->holdings()->create([
                'holdable_type' => InvestmentAccount::class,
                'holdable_id' => $account->id,
                'security_name' => $holdingData['security_name'],
                'asset_type' => $holdingData['asset_type'],
                'allocation_percent' => $holdingData['allocation_percent'],
                'cost_basis' => $holdingData['cost_basis'] ?? null,
                'current_value' => $currentValue,
            ]);
        }

        // Auto-create cash holding for remainder — but only if user didn't already add one
        $totalAllocated = collect($holdings)->sum('allocation_percent');
        if ($totalAllocated < 100 && !$hasCashHolding) {
            $remainderPercent = 100 - $totalAllocated;
            $account->holdings()->create([
                'holdable_type' => InvestmentAccount::class,
                'holdable_id' => $account->id,
                'security_name' => 'Cash',
                'asset_type' => 'cash',
                'allocation_percent' => $remainderPercent,
                'current_value' => ($account->current_value * $remainderPercent) / 100,
            ]);
        }
    }
});
```

**Note:** `current_value` on the Holding model is computed server-side from `account->current_value * allocation_percent / 100`. It is intentionally NOT included in the frontend payload or `StoreInvestmentAccountRequest` validation rules — the controller calculates it.

**No holdings provided:** No change to current behaviour (no holdings created).

### 5. Backend: `StoreInvestmentAccountRequest`

**Additional validation rules:**

```php
'holdings' => 'sometimes|array',
'holdings.*.security_name' => 'required_with:holdings|string|max:255',
'holdings.*.asset_type' => 'required_with:holdings|string|in:equity,bond,fund,etf,alternative,uk_equity,us_equity,international_equity,cash,property',
'holdings.*.allocation_percent' => 'required_with:holdings|numeric|min:0|max:100',
'holdings.*.cost_basis' => 'nullable|numeric|min:0',
```

**Custom validation rule:** Total `allocation_percent` across all holdings must not exceed 100.

```php
public function withValidator($validator)
{
    $validator->after(function ($validator) {
        if ($this->has('holdings') && is_array($this->holdings)) {
            $totalAllocation = collect($this->holdings)->sum('allocation_percent');
            if ($totalAllocation > 100) {
                $validator->errors()->add('holdings', 'Total allocation percentage cannot exceed 100%.');
            }
        }
    });
}
```

### 6. Data Flow

**Create (new account with holdings):**
```
AccountForm
  → collects account fields + holdings array from InlineHoldingsEditor
  → emits 'save' with combined payload
  → Parent calls POST /api/investment/accounts (single request)
  → Controller: DB::transaction creates account + holdings
  → Returns InvestmentAccountResource (with holdings eager-loaded)
```

**Edit (account fields via AccountForm):**
```
AccountForm
  → PUT /api/investment/accounts/{id} (existing endpoint, unchanged)
  → Holdings changes from inline editor: individual PUT/POST/DELETE calls per holding
```

**Edit (holding details via HoldingForm modal):**
```
HoldingForm
  → PUT /api/investment/holdings/{id} (existing endpoint, unchanged)
```

**Add holding from detail view:**
```
HoldingForm
  → POST /api/investment/holdings (existing endpoint, unchanged)
```

### 7. Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `resources/js/components/Investment/InlineHoldingsEditor.vue` | **New** | Spreadsheet-style inline holdings editor component |
| `resources/js/components/Investment/AccountForm.vue` | Modified | Embed InlineHoldingsEditor, include holdings in save payload |
| `resources/js/components/NetWorth/InvestmentDetailInline.vue` | Modified | Add always-visible holdings section with edit/add links |
| `app/Http/Controllers/Api/InvestmentController.php` | Modified | Accept optional holdings array in storeAccount, create in transaction |
| `app/Http/Requests/StoreInvestmentAccountRequest.php` | Modified | Add holdings array validation rules |

### 8. Files Unchanged

| File | Reason |
|------|--------|
| `HoldingForm.vue` | Kept as-is for detailed editing (ticker, ISIN, OCF, etc.) |
| `Holdings.vue` / `HoldingsTable.vue` | Kept — used by other views |
| `AccountHoldingsPanel.vue` | Kept — may still be useful, no changes needed |
| `Holding.php` model | No schema changes needed |
| `InvestmentAccount.php` model | No changes needed |
| Holding API endpoints (CRUD) | All existing endpoints remain unchanged |
| Vuex store (`investment.js`) | No structural changes — existing createHolding/updateHolding actions used for edit mode |

### 9. Constraints & Validation

- Total allocation % cannot exceed 100%
- Each holding requires security_name and asset_type
- Allocation % required per holding (0-100)
- Amount Invested (cost_basis) is optional
- Holdings section only visible when current_value > 0
- Holdings section only visible for eligible account types
- Cash holding auto-created for unallocated remainder
- Warning shown when cash allocation < 5%
- Single DB transaction for account + holdings creation (atomic)

### 10. Out of Scope

- No changes to the `HoldingForm` detailed fields (ticker, ISIN, purchase date, current price, OCF, sub_type)
- No changes to existing holding API endpoints
- No bulk-update endpoint for holdings (edit uses individual calls)
- No holding reordering/drag-and-drop
- No auto-save or draft state
- No holding search/autocomplete (security name is free text)
