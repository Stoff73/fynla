# Tax Settings Admin Panel Fix

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix NaN values, incorrect rate formatting, and data structure mismatches in the admin panel Tax Settings tab so all TaxConfig values display correctly and are editable.

**Architecture:** All fixes are in one file — `TaxSettings.vue`. The component reads `config_data` from the API but references wrong keys for PET taper relief and trust charges. Rate display formatting is inconsistent (some multiply by 100, some don't). The seeder data is correct; only the frontend needs fixing.

**Tech Stack:** Vue.js 3 (Options API), TaxConfigService (PHP), TaxConfigurationSeeder

---

## Data Structure Reference

The `config_data` JSON in the `tax_configurations` table stores rates as **decimals** (0.2 = 20%). The admin UI must multiply by 100 for display and divide by 100 for saves.

### Key mismatches found:

| Component expects | Actual DB key | Fix |
|---|---|---|
| `band.rate` displayed raw | `band.rate` = 0.2 | Multiply by 100: `(band.rate * 100)` |
| `band.upper_limit` = 0 | `band.upper_limit` = null | Show "No limit" for null |
| `relief.years` | `relief.min_years` / `relief.max_years` | Map to correct keys |
| `relief.rate` | `relief.tax_rate` | Map to correct key |
| `inheritance_tax.trust_entry_charge` | `inheritance_tax.trust_charges.entry.rate` | Read nested path |
| `inheritance_tax.trust_periodic_charge_max` | `inheritance_tax.trust_charges.periodic.max_rate` | Read nested path |
| `inheritance_tax.trust_exit_charge_max` | `inheritance_tax.trust_charges.exit.max_rate` | Read nested path |
| `inheritance_tax.trust_no_exit_charge_period` | `inheritance_tax.trust_charges.exit.no_charge_periods.first_quarter` | Read nested path |
| `inheritance_tax.trust_will_no_exit_charge_period` | `inheritance_tax.trust_charges.exit.no_charge_periods.will_trust_months` | Read nested path |
| CGT `basic_rate` displayed raw | `basic_rate` = 0.1 | Multiply by 100: `(rate * 100)` |
| Dividend `basic_rate` displayed raw | `basic_rate` = 0.0875 | Multiply by 100: `(rate * 100)` |

---

## File Structure

**Single file modified:**
- Modify: `resources/js/components/Admin/TaxSettings.vue`

No new files needed. All fixes are display/binding corrections in the existing component.

---

### Task 1: Fix income tax band rate display

**Files:**
- Modify: `resources/js/components/Admin/TaxSettings.vue:205`

The income tax bands display `{{ band.rate }}%` which shows "0.2%" instead of "20%". Need to multiply by 100.

- [ ] **Step 1: Fix the display format**

Change line 205 from:
```html
<p v-else class="text-sm font-semibold text-raspberry-600">{{ band.rate }}%</p>
```
To:
```html
<p v-else class="text-sm font-semibold text-raspberry-600">{{ (band.rate * 100).toFixed(0) }}%</p>
```

- [ ] **Step 2: Fix null upper_limit display**

Find line 192 (`formatCurrency(band.upper_limit)`) and change from:
```html
<p v-else class="text-sm">{{ formatCurrency(band.upper_limit) }}</p>
```
To:
```html
<p v-else class="text-sm">{{ band.upper_limit ? formatCurrency(band.upper_limit) : 'No limit' }}</p>
```

- [ ] **Step 3: Verify in browser**

Navigate to Admin → Tax Settings → Income Tax & National Insurance tab.
- Basic Rate should show "20%" not "0.2%"
- Higher Rate should show "40%" not "0.4%"
- Additional Rate should show "45%" not "0.45%"
- Additional Rate upper limit should show "No limit" not "£0"

---

### Task 2: Fix CGT rate display

**Files:**
- Modify: `resources/js/components/Admin/TaxSettings.vue:458,471,486,497`

CGT rates are stored as decimals (0.1 = 10%) but displayed raw.

- [ ] **Step 1: Fix all 4 CGT individual rate displays**

Line 458 — change `{{ currentConfig.capital_gains_tax.basic_rate }}%` to:
```html
{{ (currentConfig.capital_gains_tax.basic_rate * 100).toFixed(0) }}%
```

Line 471 — change `{{ currentConfig.capital_gains_tax.higher_rate }}%` to:
```html
{{ (currentConfig.capital_gains_tax.higher_rate * 100).toFixed(0) }}%
```

Line ~486 — change `residential_property_basic_rate` display similarly:
```html
{{ (currentConfig.capital_gains_tax.residential_property_basic_rate * 100).toFixed(0) }}%
```

Line ~497 — change `residential_property_higher_rate` display similarly:
```html
{{ (currentConfig.capital_gains_tax.residential_property_higher_rate * 100).toFixed(0) }}%
```

- [ ] **Step 2: Verify in browser**

Navigate to Admin → Tax Settings → Savings & Investments tab.
- CGT Basic Rate should show "10%" not "0.1%"
- CGT Higher Rate should show "20%" not "0.2%"
- Residential Property Basic should show "18%" not "0.18%"
- Residential Property Higher should show "24%" not "0.24%"

---

### Task 3: Fix dividend rate display

**Files:**
- Modify: `resources/js/components/Admin/TaxSettings.vue` — dividend section (around lines 560-610)

Dividend rates are stored as decimals (0.0875 = 8.75%) but displayed raw.

- [ ] **Step 1: Fix all dividend rate displays**

Find all dividend rate displays in the format `{{ currentConfig.dividend_tax.XXX_rate }}%` and change to `{{ (currentConfig.dividend_tax.XXX_rate * 100).toFixed(2) }}%`.

This applies to:
- `basic_rate` (0.0875 → 8.75%)
- `higher_rate` (0.3375 → 33.75%)
- `additional_rate` (0.3935 → 39.35%)
- `trust_dividend_rate` (0.3935 → 39.35%)
- `trust_other_income_rate` (0.45 → 45.00%)
- `trust_management_expenses_dividend_rate` (0.0875 → 8.75%)
- `trust_management_expenses_other_rate` (0.2 → 20.00%)

- [ ] **Step 2: Verify in browser**

Navigate to Admin → Tax Settings → Savings & Investments tab.
- Dividend Basic Rate should show "8.75%" not "0.0875%"
- Dividend Higher Rate should show "33.75%" not "0.3375%"
- Trust rates should display correctly

---

### Task 4: Fix PET taper relief NaN

**Files:**
- Modify: `resources/js/components/Admin/TaxSettings.vue:893-921`

The seeder stores PET taper relief as `{ min_years, max_years, tax_rate }` but the component reads `{ years, rate }`.

- [ ] **Step 1: Fix the v-for display bindings**

Replace the taper relief display section (lines ~893-923). The key changes:

Line 907 — change `{{ relief.years }} years` to:
```html
{{ relief.min_years }}-{{ relief.max_years ?? '∞' }} years
```

Line 920 — change `{{ (relief.rate * 100).toFixed(0) }}%` to:
```html
{{ (relief.tax_rate * 100).toFixed(0) }}%
```

- [ ] **Step 2: Fix the v-model edit bindings**

Line 901 — change `v-model.number="...taper_relief[index].years"` to:
```
v-model.number="...taper_relief[index].min_years"
```

Add a second input for `max_years` next to it.

Line 913 — change `v-model.number="...taper_relief[index].rate"` to:
```
v-model.number="...taper_relief[index].tax_rate"
```

- [ ] **Step 3: Verify in browser**

Navigate to Admin → Tax Settings → Inheritance Tax tab.
- PET Taper Relief should show "0-3 years" with "40%", "3-4 years" with "32%", etc.
- No NaN values

---

### Task 5: Fix trust charges NaN

**Files:**
- Modify: `resources/js/components/Admin/TaxSettings.vue:926-995`

Trust charges are nested under `inheritance_tax.trust_charges.{entry,periodic,exit}` but the component reads flat keys like `inheritance_tax.trust_entry_charge`.

- [ ] **Step 1: Fix display bindings for trust charge rates**

Line 941 — change:
```html
{{ (currentConfig.inheritance_tax.trust_entry_charge * 100).toFixed(0) }}%
```
To:
```html
{{ (currentConfig.inheritance_tax.trust_charges?.entry?.rate * 100).toFixed(0) }}%
```

Line 954 — change `trust_periodic_charge_max` to:
```html
{{ (currentConfig.inheritance_tax.trust_charges?.periodic?.max_rate * 100).toFixed(1) }}%
```

Line 967 — change `trust_exit_charge_max` to:
```html
{{ (currentConfig.inheritance_tax.trust_charges?.exit?.max_rate * 100).toFixed(1) }}%
```

- [ ] **Step 2: Fix display bindings for trust charge periods**

Line ~983 — change `trust_no_exit_charge_period` to:
```html
{{ currentConfig.inheritance_tax.trust_charges?.exit?.no_charge_periods?.first_quarter }} months
```

Line ~993 — change `trust_will_no_exit_charge_period` to:
```html
{{ currentConfig.inheritance_tax.trust_charges?.exit?.no_charge_periods?.will_trust_months }} months
```

- [ ] **Step 3: Fix edit mode v-model bindings**

Update all corresponding `v-model` bindings for edit mode to use the nested paths:
- `editableConfig.inheritance_tax.trust_charges.entry.rate`
- `editableConfig.inheritance_tax.trust_charges.periodic.max_rate`
- `editableConfig.inheritance_tax.trust_charges.exit.max_rate`
- `editableConfig.inheritance_tax.trust_charges.exit.no_charge_periods.first_quarter`
- `editableConfig.inheritance_tax.trust_charges.exit.no_charge_periods.will_trust_months`

- [ ] **Step 4: Verify in browser**

Navigate to Admin → Tax Settings → Inheritance Tax tab.
- Entry Charge should show "20%" not "NaN%"
- Periodic Charge Max should show "6.0%" not "NaN%"
- Exit Charge Max should show "6.0%" not "NaN%"
- No Exit Charge Period should show "3 months" not blank
- Will Trust period should show "24 months" not blank

---

### Task 6: Fix edit mode rate inputs for income tax and CGT

**Files:**
- Modify: `resources/js/components/Admin/TaxSettings.vue`

The edit inputs for income tax bands use `max="100"` suggesting percentage entry, but the DB stores decimals. The NI inputs correctly use `max="1"`. Need consistency: since the DB stores decimals, inputs should accept decimals with `max="1"`.

- [ ] **Step 1: Fix income tax band rate input**

Line 200-202 — change:
```html
step="0.01" min="0" max="100"
```
To:
```html
step="0.01" min="0" max="1"
```

- [ ] **Step 2: Fix CGT rate inputs**

Find all CGT rate inputs with `max="100"` and change to `max="1"` to match the decimal storage format.

- [ ] **Step 3: Fix validation function**

Line 1409 — the validation checks `band.rate < 0 || band.rate > 100` for income tax. Change to `band.rate > 1` since rates are stored as decimals.

---

### Task 7: Verify edit and save works

- [ ] **Step 1: Click "Edit Configuration" in browser**

Navigate to Admin → Tax Settings → click "Edit Configuration".

- [ ] **Step 2: Verify edit mode shows correct values in inputs**

Check that:
- Income tax band rate inputs show decimal values (0.2, 0.4, 0.45)
- NI rate inputs show decimal values (0.08, 0.02)
- PET taper relief inputs show correct min_years and tax_rate values
- Trust charge inputs show correct nested values

- [ ] **Step 3: Make a test edit and save**

Change Personal Allowance from 12570 to 12571, save, verify it persists. Then change it back.

- [ ] **Step 4: Verify all tabs render correctly after save**

Check all 5 tabs display correctly with no NaN and correct formatting.

---

## Browser Test Checkpoints

### Checkpoint 1: After Tasks 1-3 (rate formatting)
- [ ] Navigate to Admin → Tax Settings
- [ ] Income Tax tab: rates show 20%, 40%, 45% (not 0.2%, 0.4%, 0.45%)
- [ ] Income Tax tab: Additional Rate upper limit shows "No limit" (not "£0")
- [ ] Savings tab: CGT rates show 10%, 20%, 18%, 24%
- [ ] Savings tab: Dividend rates show 8.75%, 33.75%, 39.35%
- [ ] No NaN values on any tab

### Checkpoint 2: After Tasks 4-5 (IHT fixes)
- [ ] IHT tab: PET Taper Relief shows year ranges and percentages (no NaN)
- [ ] IHT tab: Trust charges show 20%, 6.0%, 6.0% (no NaN)
- [ ] IHT tab: Trust periods show "3 months" and "24 months" (not blank)

### Checkpoint 3: After Tasks 6-7 (edit mode)
- [ ] Click "Edit Configuration" — inputs show correct decimal values
- [ ] Edit Personal Allowance → save → verify change persists
- [ ] Cancel edit → verify values revert
- [ ] All tabs correct after save
