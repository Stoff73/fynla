# Property Form Algorithm — Complete Field-by-Field Map

**Date:** 24 March 2026
**Source:** `resources/js/components/NetWorth/Property/PropertyForm.vue`

## Form Structure

The PropertyForm is a **multi-step wizard**. Steps are DYNAMIC — they change based on selections:

### Step Configuration

| Condition | Steps Shown |
|-----------|-------------|
| No mortgage, not BTL | Basic Info → Ownership → Costs (3 steps) |
| Has mortgage, not BTL | Basic Info → Ownership → Mortgage → Costs (4 steps) |
| No mortgage, BTL | Basic Info → Ownership → Costs → BTL Details (4 steps) |
| Has mortgage, BTL | Basic Info → Ownership → Mortgage → Costs → BTL Details (5 steps) |

### Step Navigation

- "Next" button advances to next step
- "Previous" button goes back
- Step indicators are clickable (can jump to any step)
- "Save" button appears on the LAST step only
- `handleSubmit()` is called on form submit (last step Save button)

---

## Validation Rules (validateForm)

The form WILL NOT SUBMIT unless ALL of these pass:

### Required fields (Step 1 — Basic Info):
- `form.property_type` — MUST be set (not empty string)
- `form.address_line_1` — MUST be set
- `form.city` — MUST be set
- `form.postcode` — MUST be set
- `form.current_value` — MUST be > 0

### Required fields (Step 2 — Ownership):
- `form.ownership_type` — MUST be set (not empty/null)
- `form.ownership_percentage` — MUST not be null/undefined

### Required fields (Step 3 — Mortgage, only if hasMortgage):
- `mortgageForm.outstanding_balance` — MUST be > 0
- `mortgageForm.monthly_payment` — MUST be > 0

### NOT validated by frontend (optional):
- Purchase price, purchase date, valuation date
- All monthly cost fields
- All BTL fields
- Mortgage lender, type, rate, dates

---

## Step 1: Basic Information

### Fields

| Field | v-model | Type | HTML Element | Required | Notes |
|-------|---------|------|-------------|----------|-------|
| Property Type | `form.property_type` | string | `<select>` dropdown | YES | Values: `main_residence`, `secondary_residence`, `buy_to_let`. Default: empty string `""` |
| Address Line 1 | `form.address_line_1` | string | `<input type="text">` | YES | |
| Address Line 2 | `form.address_line_2` | string | `<input type="text">` | no | |
| City | `form.city` | string | `<input type="text">` | YES | |
| County | `form.county` | string | `<input type="text">` | no | |
| Postcode | `form.postcode` | string | `<input type="text">` | YES | Pattern: UK postcode |
| Country | `form.country` | string | CountrySelector component | no | Defaults to "United Kingdom" |
| Purchase Date | `form.purchase_date` | date | `<input type="date">` | no | YYYY-MM-DD |
| Purchase Price | `form.purchase_price` | number | `<input type="number">` | no | Uses `v-model.number` |
| Current Value | `form.current_value` | number | `<input type="number">` | YES | Uses `v-model.number`, must be > 0 |
| Valuation Date | `form.valuation_date` | date | `<input type="date">` | no | YYYY-MM-DD |
| Has Mortgage | `hasMortgage` | boolean | `<input type="checkbox">` | no | LOCAL data property (not in `form`). Toggles mortgage step visibility. |

### CRITICAL NOTES — Step 1:
- `property_type` is a `<select>` with `v-model="form.property_type"`. Setting `this.form.property_type = 'main_residence'` via Vue data SHOULD update the select. If it doesn't visually update, it's a reactivity issue.
- `hasMortgage` is a SEPARATE local data property, NOT inside `this.form`. It controls whether the Mortgage step appears. Setting `this.form.has_mortgage` does NOTHING — must set `this.hasMortgage`.
- Changing `property_type` to `buy_to_let` adds the BTL Details step dynamically.

---

## Step 2: Ownership

### Tenure Type (always shown)

| Field | v-model | Type | HTML Element | Notes |
|-------|---------|------|-------------|-------|
| Tenure Type | `form.tenure_type` | string | Radio buttons | Values: `freehold`, `leasehold`. Default: `freehold` |

### Leasehold Details (conditional: `form.tenure_type === 'leasehold'`)

| Field | v-model | Type | HTML Element | Notes |
|-------|---------|------|-------------|-------|
| Lease Expiry Date | `form.lease_expiry_date` | date | `<input type="date">` | Calculates remaining years automatically |

### Ownership Type (always shown)

| Field | v-model | Type | HTML Element | Notes |
|-------|---------|------|-------------|-------|
| Ownership Type | `form.ownership_type` | string | Radio buttons | Values: `individual`, `joint`, `tenants_in_common`, `trust`. Default: `individual` |

### Watchers on ownership_type:
- `individual` → sets `ownership_percentage = 100`
- `joint` → sets `ownership_percentage = 50`
- `tenants_in_common` → sets `ownership_percentage = 50` (if not already set)
- `trust` → sets `ownership_percentage = 0`

### Joint Tenancy Details (conditional: `form.ownership_type === 'joint'`)
- Shows fixed 50/50 split display
- Joint Owner dropdown (`jointOwnerSelection`): spouse (if exists) or "Other (Enter Name)"
- If "Other": shows `form.joint_owner_name` text input

### Tenants in Common Details (conditional: `form.ownership_type === 'tenants_in_common'`)
- Shows ownership percentage input (`form.ownership_percentage`, 1-99)
- Shows split display (your % / co-owner %)
- Co-Owner dropdown (`jointOwnerSelection`): spouse or "Other"
- If "Other": shows `form.joint_owner_name` text input

### Trust Details (conditional: `form.ownership_type === 'trust'`)
- Shows 0%/100% split (all in trust)
- Trust dropdown (`trustSelection`): "Other (Enter Trust Name)"
- If "Other": shows `form.trust_name` text input

---

## Step 3: Mortgage (conditional: `hasMortgage === true`)

### Fields

| Field | v-model | Type | HTML Element | Required | Notes |
|-------|---------|------|-------------|----------|-------|
| Lender Name | `mortgageForm.lender_name` | string | text input | no | |
| Account Number | `mortgageForm.mortgage_account_number` | string | text input | no | |
| Mortgage Type | `mortgageForm.mortgage_type` | string | `<select>` dropdown | no | Values: `repayment`, `interest_only`, `mixed` |
| Outstanding Balance | `mortgageForm.outstanding_balance` | number | number input | YES (if mortgage) | Must be > 0 |
| Interest Rate | `mortgageForm.interest_rate` | number | number input | no | Hidden if rate_type is "mixed" |
| Rate Type | `mortgageForm.rate_type` | string | `<select>` dropdown | no | Values: `fixed`, `variable`, `tracker`, `discount`, `mixed` |
| Monthly Payment | `mortgageForm.monthly_payment` | number | number input | YES (if mortgage) | Must be > 0 |
| Start Date | `mortgageForm.start_date` | date | date input | no | |
| Maturity Date | `mortgageForm.maturity_date` | date | date input | no | |
| Mortgage Ownership | `mortgageForm.ownership_type` | string | `<select>` dropdown | no | Values: `individual`, `joint` |

### Conditional mortgage sub-fields:
- **If mortgage_type = "mixed"**: Shows repayment_percentage + interest_only_percentage (must total 100%)
- **If rate_type = "fixed"**: Shows rate_fix_end_date
- **If rate_type = "mixed"**: Shows fixed_rate_percentage + variable_rate_percentage + fixed_interest_rate + variable_interest_rate
- **If BTL + repayment/mixed mortgage**: Shows monthly_interest_portion

---

## Step 4: Costs (always shown)

### Fields (all optional, all `v-model.number`, all `<input type="number">`)

| Field | v-model |
|-------|---------|
| Council Tax | `form.monthly_council_tax` |
| Gas | `form.monthly_gas` |
| Electricity | `form.monthly_electricity` |
| Water | `form.monthly_water` |
| Building Insurance | `form.monthly_building_insurance` |
| Contents Insurance | `form.monthly_contents_insurance` |
| Service Charge | `form.monthly_service_charge` |
| Maintenance Reserve | `form.monthly_maintenance_reserve` |
| Other Costs | `form.other_monthly_costs` |

Shows total monthly costs summary at bottom.

---

## Step 5: BTL Details (conditional: `form.property_type === 'buy_to_let'`)

### Fields

| Field | v-model | Type | HTML Element | Notes |
|-------|---------|------|-------------|-------|
| Monthly Rental Income | `form.monthly_rental_income` | number | number input | |
| Tenant Name | `form.tenant_name` | string | text input | |
| Tenant Email | `form.tenant_email` | email | email input | |
| Lease Start Date | `form.lease_start_date` | date | date input | Tenancy lease, not property lease |
| Lease End Date | `form.lease_end_date` | date | date input | |
| Managing Agent Name | `form.managing_agent_name` | string | text input | Optional section |
| Managing Agent Company | `form.managing_agent_company` | string | text input | |
| Managing Agent Email | `form.managing_agent_email` | email | email input | |
| Managing Agent Phone | `form.managing_agent_phone` | string | text input | |

---

## AI Form Fill Flow (current implementation)

### pendingFill watcher (line 1663):
1. Receives `fill` with `entityType: 'property'` and `fields` object
2. **IMMEDIATELY** sets `this.hasMortgage = true` if `fill.fields.has_mortgage` is truthy — this renders the mortgage step BEFORE field sequence starts
3. Builds `fieldOrder` from non-null, non-empty fields
4. Dispatches `aiFormFill/beginFieldSequence` with the field order

### highlightedField watcher (line 1679):
For each field in sequence (250ms apart):
- `has_mortgage` → `this.hasMortgage = !!value`
- `mortgage_outstanding_balance` → `this.mortgageForm.outstanding_balance = value`
- `mortgage_interest_rate` → `this.mortgageForm.interest_rate = value`
- `mortgage_lender_name` → `this.mortgageForm.lender_name = value`
- `mortgage_type` → `this.mortgageForm.mortgage_type = value`
- `mortgage_rate_type` → `this.mortgageForm.rate_type = value`
- `mortgage_monthly_payment` → `this.mortgageForm.monthly_payment = value`
- `mortgage_start_date` → `this.mortgageForm.start_date = value`
- `mortgage_maturity_date` → `this.mortgageForm.maturity_date = value`
- **Everything else** → `this.form[fieldKey] = value` (catch-all)

### filling watcher (line 1711):
When `filling` becomes `false` (all fields highlighted):
- Calls `this.handleSubmit()` after 250ms delay

### handleSubmit:
1. Calls `validateForm()` — checks required fields
2. If valid: cleans data, emits `save` event with `{property, mortgage}`
3. Parent component handles API call

---

## KNOWN ISSUE — property_type dropdown

The catch-all `this.form[fieldKey] = value` sets `this.form.property_type = 'main_residence'`. This SHOULD update the `<select v-model="form.property_type">` reactively. But the `<select>` shows "Select property type" as selected even though the data is correct.

**Root cause hypothesis:** The `<select>` option with `value=""` is the default. When `form.property_type` is set programmatically via the catch-all watcher, Vue's reactivity should update the select. If it doesn't, the select might need an explicit `$nextTick` or `$forceUpdate` after setting.

**Fix needed:** Either force the select to re-render, or add `property_type` as an explicit case in the highlightedField watcher (like the mortgage fields).

---

## REQUIRED FIELDS for successful form submission (minimum viable)

For the AI to successfully create a property, the backend `handleCreateProperty` must provide AT MINIMUM:
1. `property_type` — main_residence, secondary_residence, or buy_to_let
2. `current_value` — number > 0
3. `address_line_1` — non-empty string
4. `city` — non-empty string
5. `postcode` — non-empty string (UK format)
6. `ownership_type` — individual, joint, tenants_in_common, or trust
7. `ownership_percentage` — number (auto-set by watcher based on ownership_type)

If mortgage:
8. `has_mortgage` — true (must set `this.hasMortgage`, not `this.form.has_mortgage`)
9. `mortgage_outstanding_balance` → `mortgageForm.outstanding_balance` — number > 0
10. `mortgage_monthly_payment` → `mortgageForm.monthly_payment` — number > 0

---

## Test Scenarios

### Scenario A: Main Residence, Individual, No Mortgage, Freehold
Steps: Basic Info → Ownership → Costs (3 steps)
Required: property_type=main_residence, address, city, postcode, current_value, ownership_type=individual

### Scenario B: Main Residence, Joint, Repayment Mortgage, Freehold
Steps: Basic Info → Ownership → Mortgage → Costs (4 steps)
Required: all of A + has_mortgage=true, outstanding_balance, monthly_payment, ownership_type=joint

### Scenario C: Secondary Residence, Tenants in Common (70/30), Interest-Only Mortgage
Steps: Basic Info → Ownership → Mortgage → Costs (4 steps)
Required: all of A + ownership_type=tenants_in_common, ownership_percentage=70, has_mortgage=true, outstanding_balance, monthly_payment

### Scenario D: Buy-to-Let, Joint, Mixed Mortgage, Tenant Details
Steps: Basic Info → Ownership → Mortgage → Costs → BTL Details (5 steps)
Required: property_type=buy_to_let + all mortgage + monthly_rental_income

### Scenario E: Leasehold Flat, Individual, No Mortgage, Service Charges
Steps: Basic Info → Ownership → Costs (3 steps)
Required: all of A + tenure_type=leasehold, lease_expiry_date
