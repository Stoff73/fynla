# Protection Policy Form Algorithm — Complete Field-by-Field Map

**Date:** 24 March 2026 (updated after manual browser testing)
**Source:** `resources/js/components/Protection/PolicyFormModal.vue`
**Parent:** `resources/js/views/Protection/ProtectionDashboard.vue`
**API Service:** `resources/js/services/protectionService.js`

## Form Structure

Single-step modal form. Opens on `/protection` page when "Add New Policy" clicked. AI fill watchers already implemented (`pendingFill`, `highlightedField`, `filling`).

Entity type: `protection_policy`
Route: `/protection`

---

## Policy Type Hierarchy

The form has TWO levels of type selection. The AI tool must send a single `policy_type` value, and the CoordinatingAgent maps it to both levels.

### Level 1: policyType (main dropdown)

| `<option value>` | Label | Conditional Fields Shown |
|-------------------|-------|--------------------------|
| `life` | Life Insurance | life_policy_type sub-dropdown, in_trust, is_mortgage_protection, beneficiary section |
| `criticalIllness` | Critical Illness | term_years, start_date, end_date |
| `incomeProtection` | Income Protection | benefit_frequency, deferred_period_weeks, benefit_period_months, start_date, end_date |
| `disability` | Disability | benefit_frequency, deferred_period_weeks, benefit_period_months, coverage_type, start_date, end_date |
| `sicknessIllness` | Sickness/Illness | benefit_frequency, benefit_period_months, start_date, end_date |

### Level 2: life_policy_type (only when policyType = 'life')

| `<option value>` | Label | Additional Fields |
|-------------------|-------|-------------------|
| `decreasing_term` | Decreasing Life Policy | start_value, decreasing_rate, start_date, term_years, end_date |
| `level_term` | Level Term Life Policy | start_date, term_years, end_date |
| `whole_of_life` | Whole of Life Policy | NOTHING extra (no start_date, no term_years, no end_date) |

**MISSING from dropdown (needs adding):** `family_income_benefit`, `term`

### AI tool policy_type → form mapping

| AI sends `policy_type` | Handler sets `policyType` | Handler sets `life_policy_type` |
|-------------------------|---------------------------|--------------------------------|
| `level_term` | `life` | `level_term` |
| `term` | `life` | `level_term` (generic term → level term) |
| `whole_of_life` | `life` | `whole_of_life` |
| `decreasing_term` | `life` | `decreasing_term` |
| `family_income_benefit` | `life` | `family_income_benefit` |
| `standalone_ci` | `criticalIllness` | — |
| `accelerated_ci` | `criticalIllness` | — |
| `income_protection` | `incomeProtection` | — |

---

## Form Fields — Complete Map

### formData initial shape (from `data()`)

```javascript
formData: {
  policyType: '',              // select: life, criticalIllness, incomeProtection, disability, sicknessIllness
  life_policy_type: '',        // select: decreasing_term, level_term, whole_of_life
  provider: '',                // text
  policy_number: '',           // text
  coverage_amount: 0,          // number — Sum Assured OR Benefit Amount depending on type
  start_value: 0,              // number — decreasing_term only
  decreasing_rate: 0,          // number — decreasing_term only (percentage, e.g. 5.0)
  premium_amount: 0,           // number
  premium_frequency: 'monthly', // select: monthly, annual
  start_date: '',              // date (YYYY-MM-DD)
  end_date: '',                // date (YYYY-MM-DD)
  term_years: null,            // number
  in_trust: false,             // boolean — life only
  is_mortgage_protection: false, // boolean — life only
  beneficiary_name: '',        // text — life only
  beneficiary_percentage: 100, // number — life only
  additional_beneficiaries: '', // text — life only
  benefit_frequency: 'monthly', // select: monthly, weekly, lump_sum — IP/disability/sickness
  deferred_period_weeks: null, // number — IP/disability only
  benefit_period_months: null, // number — IP/disability/sickness
  coverage_type: 'accident_and_sickness', // select — disability only
  notes: '',                   // text
}
```

### Always Visible Fields

| Field | formData key | Type | AI fills? |
|-------|-------------|------|-----------|
| Policy Type | `policyType` | select | YES — pre-set in pendingFill |
| Provider | `provider` | text | YES |
| Policy Number | `policy_number` | text | No (optional) |
| Coverage Amount | `coverage_amount` | number | YES |
| Premium Amount | `premium_amount` | number | YES |
| Premium Frequency | `premium_frequency` | select (monthly/annual) | No (defaults monthly) |
| Additional Notes | `notes` | text | No |

**Coverage Amount label changes by type:**
- Life / Critical Illness → "Sum Assured"
- Income Protection / Disability / Sickness → "Benefit Amount" (with help text: "This is the monthly amount paid out if you are unable to work.")

### Life Insurance Fields (policyType = 'life')

| Field | formData key | Type | Condition |
|-------|-------------|------|-----------|
| Life Policy Type | `life_policy_type` | select | Always when life |
| Start Date | `start_date` | date | `level_term` or `decreasing_term` (NOT `whole_of_life`) |
| Term Years | `term_years` | number | All EXCEPT `whole_of_life` |
| End Date | `end_date` | date | `level_term` or `decreasing_term` (NOT `whole_of_life`) |
| In Trust | `in_trust` | checkbox | Always when life |
| Mortgage Protection | `is_mortgage_protection` | checkbox | Always when life |
| Beneficiary | `beneficiary_name` | select+text | Always when life |
| Beneficiary % | `beneficiary_percentage` | number | When beneficiary selected |

### Decreasing Term Fields (life_policy_type = 'decreasing_term')

| Field | formData key | Type | Notes |
|-------|-------------|------|-------|
| Start Value | `start_value` | number (£) | Initial coverage at policy start |
| Decreasing Rate | `decreasing_rate` | number (%) | Annual % rate coverage decreases. Form shows as percentage (e.g. 5.0), `preparePolicyData()` divides by 100 before sending to API |

### Critical Illness Fields (policyType = 'criticalIllness')

| Field | formData key | Type |
|-------|-------------|------|
| Start Date | `start_date` | date |
| Term Years | `term_years` | number |
| End Date | `end_date` | date |

### Income Protection Fields (policyType = 'incomeProtection')

| Field | formData key | Type | Notes |
|-------|-------------|------|-------|
| Start Date | `start_date` | date | |
| End Date | `end_date` | date | |
| Benefit Frequency | `benefit_frequency` | select: monthly/weekly/lump_sum | |
| Deferred Period | `deferred_period_weeks` | number (weeks) | |
| Benefit Period | `benefit_period_months` | number (months) | |

### Disability Fields (policyType = 'disability')

| Field | formData key | Type |
|-------|-------------|------|
| Benefit Frequency | `benefit_frequency` | select: monthly/weekly/lump_sum |
| Deferred Period | `deferred_period_weeks` | number (weeks) |
| Benefit Period | `benefit_period_months` | number (months) |
| Coverage Type | `coverage_type` | select: accident_only/accident_and_sickness |

---

## Coverage Amount Logic

The form uses a single `coverage_amount` field for all types. The `preparePolicyData()` method maps it to the correct API field:

- **Life** → `data.sum_assured = formData.coverage_amount`
- **Critical Illness** → `data.sum_assured = formData.coverage_amount`
- **Income Protection / Disability / Sickness** → `data.benefit_amount = formData.coverage_amount`

For AI fill, the handler MUST always set `coverage_amount` (the form field name), not `sum_assured` or `benefit_amount`.

For income-based types (income_protection, family_income_benefit), the AI tool sends `benefit_amount` and the handler maps it to `coverage_amount`.
For lump-sum types (level_term, whole_of_life, decreasing_term, CI), the AI tool sends `sum_assured` and the handler maps it to `coverage_amount`.

---

## Parent Component Save Flow

`ProtectionDashboard.vue` → `handlePolicySaved(policyData)`:

```
const { policyType, ...actualPolicyData } = policyData;
switch (policyType) {
  case 'life':       → protectionService.createLifePolicy(actualPolicyData)
  case 'criticalIllness': → protectionService.createCriticalIllnessPolicy(actualPolicyData)
  case 'incomeProtection': → protectionService.createIncomeProtectionPolicy(actualPolicyData)
  case 'disability': → protectionService.createDisabilityPolicy(actualPolicyData)
  case 'sicknessIllness': → protectionService.createSicknessIllnessPolicy(actualPolicyData)
}
```

Each calls a different API endpoint:
- POST `/protection/policies/life`
- POST `/protection/policies/critical-illness`
- POST `/protection/policies/income-protection`
- POST `/protection/policies/disability`
- POST `/protection/policies/sickness-illness`

The `policyType` field is destructured out — it controls which endpoint is called, NOT sent in the payload.

---

## Pre-Set Requirements (Vue Reactivity)

The `pendingFill` watcher MUST set these BEFORE calling `beginFieldSequence`:

1. `formData.policyType` — controls which conditional sections render
2. `formData.life_policy_type` — controls which life sub-fields render (start_date, term_years, decreasing fields)

Without pre-setting these, conditional fields won't be in the DOM when `highlightedField` tries to set their values.

---

## Validation

No blocking validation. `handleSubmit()` calls `preparePolicyData()` and emits `save`. The parent handles the API call.

---

## Known Issues to Fix

1. **`family_income_benefit` not in life_policy_type dropdown** — AI sends it, form dropdown doesn't have it as an option. Need to add `<option value="family_income_benefit">Family Income Benefit</option>`.
2. **`term` not in life_policy_type dropdown** — AI sends it for generic "term life", handler maps to `level_term` which IS in the dropdown. No form change needed.
3. **FIB coverage_amount mapping** — Already fixed in CoordinatingAgent: FIB uses `benefit_amount` → `coverage_amount` (same path as income_protection).

---

## AI Tool Parameter → Handler → Form Field Map

| AI tool param | Handler field | formData key | Notes |
|---------------|--------------|-------------|-------|
| `policy_type` | Split to `policyType` + `life_policy_type` | `policyType`, `life_policy_type` | See mapping table above |
| `provider` | `provider` | `provider` | Direct pass-through |
| `sum_assured` | `coverage_amount` | `coverage_amount` | For life + CI types |
| `benefit_amount` | `coverage_amount` | `coverage_amount` | For IP + FIB types |
| `premium_amount` | `premium_amount` | `premium_amount` | Direct pass-through |
| `premium_frequency` | `premium_frequency` | `premium_frequency` | monthly/annually |
| `policy_term_years` | `term_years` | `term_years` | Not for whole_of_life |
| `in_trust` | `in_trust` | `in_trust` | Life only, boolean |

---

## Test Scenarios

### Scenario 1: Level Term Life
"I have level term life insurance with Aviva for £500,000, paying £45 a month for 25 years, held in trust"
**Expected:** policyType=life, life_policy_type=level_term, provider=Aviva, coverage_amount=500000, premium_amount=45, term_years=25, in_trust=true

### Scenario 2: Decreasing Term Life
"I have decreasing term life cover with Legal & General for £350,000, £28 a month for 20 years, it's mortgage protection"
**Expected:** policyType=life, life_policy_type=decreasing_term, provider=Legal & General, coverage_amount=350000, premium_amount=28, term_years=20, is_mortgage_protection=true

### Scenario 3: Whole of Life
"I have whole of life insurance with Royal London for £200,000, paying £85 a month, in trust"
**Expected:** policyType=life, life_policy_type=whole_of_life, provider=Royal London, coverage_amount=200000, premium_amount=85, in_trust=true. NO term_years.

### Scenario 4: Family Income Benefit
"I have family income benefit with Zurich, £3,000 a month benefit, paying £55 a month for 18 years"
**Expected:** policyType=life, life_policy_type=family_income_benefit, provider=Zurich, coverage_amount=3000 (benefit, not lump sum), premium_amount=55, term_years=18

### Scenario 5: Standalone Critical Illness
"I have standalone critical illness cover with Vitality for £150,000, paying £62 a month for 20 years"
**Expected:** policyType=criticalIllness, provider=Vitality, coverage_amount=150000, premium_amount=62, term_years=20

### Scenario 6: Accelerated Critical Illness
"I have accelerated critical illness with Scottish Widows for £250,000, paying £78 a month for 25 years"
**Expected:** policyType=criticalIllness, provider=Scottish Widows, coverage_amount=250000, premium_amount=78, term_years=25

### Scenario 7: Income Protection
"I have income protection with LV= paying £2,500 a month benefit, premium £42 a month"
**Expected:** policyType=incomeProtection, provider=LV=, coverage_amount=2500 (monthly benefit), premium_amount=42

### Scenario 8: Term Life (generic)
"I have term life insurance with AIG for £300,000, £35 a month for 15 years"
**Expected:** policyType=life, life_policy_type=level_term (mapped from generic "term"), provider=AIG, coverage_amount=300000, premium_amount=35, term_years=15

---

## Manual Browser Test Results (Step 4+5)

| # | Type | Filled | Saved to DB | Dashboard Card Correct | Result |
|---|------|--------|-------------|----------------------|--------|
| 1 | Level Term Life | All fields | YES | Life Insurance / Level Term / Legal & General / £350,000 / £28/mo | PASS |
| 2 | Whole of Life | No term/dates (correct) | YES | Life Insurance / Whole of Life / Royal London / £200,000 / £85/mo | PASS |
| 3 | Critical Illness | +term_years | YES | Critical Illness / Vitality / £150,000 / £62/mo | PASS |
| 4 | Income Protection | +benefit fields | YES | Income Protection / LV= / Benefit Amount: £2,500 / £42/mo | PASS |
| 5 | Decreasing Term | +start_value, rate | YES | Life Insurance / Decreasing Term / Scottish Widows / £400,000 / £32/mo | PASS |
