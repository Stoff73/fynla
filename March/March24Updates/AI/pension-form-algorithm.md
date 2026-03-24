# Pension Form Algorithm — Complete Field-by-Field Map

**Date:** 24 March 2026
**Sources:**
- `resources/js/components/Retirement/DCPensionForm.vue`
- `resources/js/components/Retirement/DBPensionForm.vue`
- `resources/js/components/Retirement/UnifiedPensionForm.vue`
- `resources/js/components/NetWorth/PensionList.vue`

## Form Structure

There are TWO separate pension forms, selected by pension category:
- **DC (Defined Contribution)** — `DCPensionForm.vue` — workplace, SIPP, personal, stakeholder
- **DB (Defined Benefit)** — `DBPensionForm.vue` — final salary, career average

The `UnifiedPensionForm.vue` wraps both — user first selects DC/DB/State, then the relevant sub-form renders.

For AI fill: `PensionList.vue` watches `pendingFill` and sets `initialPensionType` to `'dc'` or `'db'`, which auto-selects the right form in UnifiedPensionForm. **No type selection step needed from AI.**

Both forms are **single-step** (no multi-step wizard). One form, fill fields, click Save.

---

## DC Pension Form (Defined Contribution)

### Entity Type: `dc_pension`

### Form Data Shape

```javascript
formData: {
  pension_type: '',          // 'occupational', 'sipp', 'personal', 'stakeholder'
  scheme_type: '',           // Set by handlePensionTypeChange: 'workplace', 'sipp', 'personal'
  scheme_name: '',           // e.g., "Aviva Master Trust"
  provider: '',              // e.g., "Aviva"
  policy_number: '',
  current_fund_value: null,  // number
  annual_salary: null,       // number — workplace pensions only
  employee_contribution_percent: null,  // number — workplace only
  employer_contribution_percent: null,  // number — workplace only
  monthly_contribution_amount: null,    // number — personal/SIPP only
  lump_sum_contribution: null,          // number — personal/SIPP only
  expected_return_percent: 5.0,
  retirement_age: null,      // number — personal/SIPP only
  salary_sacrifice: false,   // boolean — workplace only
  notes: '',
  risk_preference: null,
  beneficiary_id: null,
  beneficiary_name: '',
}
```

### Validation Rules (handleSubmit)

| Check | Required? | Notes |
|-------|-----------|-------|
| `formData.scheme_type` | YES | Set by `handlePensionTypeChange()` from `pension_type`. If not set, validation fails with "Please select a pension type" |
| `formData.scheme_name` | YES | "Please enter a scheme name" |
| `formData.current_fund_value >= 0` | YES | "Please enter a valid current fund value" |
| Contribution % validation | If workplace | Checks employee % and employer % are valid numbers 0-100 |
| `retirement_age >= 55` | If personal/SIPP | Minimum pension access age |

### CRITICAL: pension_type → scheme_type sync

The `<select>` dropdown uses `pension_type` values: `occupational`, `sipp`, `personal`, `stakeholder`.

`handlePensionTypeChange()` syncs `scheme_type`:
- `occupational` → `scheme_type = 'workplace'`
- `stakeholder` → `scheme_type = 'personal'`
- `sipp` → `scheme_type = 'sipp'`
- `personal` → `scheme_type = 'personal'`

**Validation checks `scheme_type`, NOT `pension_type`.** If `pension_type` is set but `handlePensionTypeChange()` isn't called, `scheme_type` stays empty and validation fails.

### Conditional Fields

| Condition | What Shows |
|-----------|-----------|
| `isWorkplacePension` (occupational/stakeholder) | annual_salary, employee_contribution_percent, employer_contribution_percent, salary_sacrifice checkbox |
| `isPersonalPension` (sipp/personal) | monthly_contribution_amount, lump_sum_contribution, retirement_age |
| Always | pension_type, scheme_name, provider, current_fund_value, expected_return, beneficiary, notes |

### AI Fill Flow (existing — already works)

The `pendingFill` watcher:
1. Sets `pension_type` immediately from `fill.fields.pension_type`
2. Calls `handlePensionTypeChange()` to sync `scheme_type`
3. Pre-sets `scheme_name` (falls back to provider if empty)
4. Builds field order and starts sequence

The `highlightedField` watcher uses catch-all: `this.formData[fieldKey] = value`

Auto-submit fires on `filling === false`.

### Fields Backend Must Provide (minimum for save)

| Field | Required | Notes |
|-------|----------|-------|
| `pension_type` | YES | `occupational`, `sipp`, `personal`, or `stakeholder` |
| `scheme_name` | YES | Non-empty |
| `current_fund_value` | YES | Number >= 0 |
| `provider` | Recommended | Falls back to scheme_name |

For workplace pensions:
| `employee_contribution_percent` | Recommended | |
| `employer_contribution_percent` | Recommended | |

---

## DB Pension Form (Defined Benefit)

### Entity Type: `db_pension`

### Form Data Shape

```javascript
formData: {
  employer_name: '',      // e.g., "NHS Pension Scheme"
  scheme_status: '',      // 'Active', 'Deferred', 'In Payment'
  scheme_type: 'final_salary',  // 'final_salary' or 'career_average'
  annual_income: null,    // number — projected annual pension at retirement
  service_years: null,    // number
  final_salary: null,     // number — pensionable salary
  accrual_rate: null,     // number — e.g., 60 for 1/60th
  revaluation_rate: null, // number — for CARE schemes
  pcls_available: null,   // number — tax-free lump sum
  notes: '',
}
```

### Validation Rules (handleSubmit)

| Check | Required? | Error |
|-------|-----------|-------|
| `formData.employer_name` | YES | alert("Please enter an employer/scheme name") |
| `formData.scheme_status` | YES | alert("Please select a scheme status") |
| `formData.annual_income >= 0` | YES | alert("Please enter a valid annual income") |
| `formData.service_years >= 0` | YES | alert("Please enter valid service years") |

### Fields Backend Must Provide (minimum for save)

| Field | Required | Notes |
|-------|----------|-------|
| `employer_name` | YES | = scheme_name from AI |
| `scheme_status` | YES | Must be 'Active', 'Deferred', or 'In Payment'. Default to 'Active' |
| `scheme_type` | Defaults to 'final_salary' | 'final_salary' or 'career_average' |
| `annual_income` | YES | Accrued annual pension amount > 0 |
| `service_years` | YES | Must be > 0 |

### AI Fill Flow (existing)

The `pendingFill` watcher just starts field sequence (no special pre-setting needed — DB form has no conditional rendering that depends on a dropdown).

The `highlightedField` watcher uses catch-all: `this.formData[fieldKey] = value`

Auto-submit fires on `filling === false`.

---

## Backend Tool Handler: handleCreatePension

Current `CoordinatingAgent::handleCreatePension`:

1. Validates `pension_category` (dc/db), `scheme_name`, and optional fields
2. Checks for duplicate scheme names
3. For DC: maps `scheme_type` to form `pension_type` value, builds fields
4. For DB: sets `employer_name`, `annual_income`, `service_years`, `scheme_type`
5. Returns `fill_form` action with entity_type `dc_pension` or `db_pension`

### Current field mapping gaps:

**DC pensions:**
- `scheme_type` → mapped to `pension_type` (correct)
- `provider` → included (correct)
- `current_fund_value` → included (correct)
- `employee_contribution_percent` → included (correct)
- `employer_contribution_percent` → included (correct)
- MISSING: `annual_salary`, `monthly_contribution_amount`, `retirement_age`, `expected_return_percent`

**DB pensions:**
- `employer_name` → set to scheme_name (correct)
- `scheme_type` → defaults to 'final_salary' (correct)
- `annual_income` → from accrued_annual_pension (correct)
- `service_years` → from pensionable_service_years (correct)
- MISSING: `scheme_status` (REQUIRED for validation — must default to 'Active'), `final_salary`, `accrual_rate`, `normal_retirement_age`

---

## Test Scenarios

### Scenario 1: DC Workplace Pension (occupational)
"I have a workplace pension with Aviva worth £85,000. I put in 5% and my employer puts in 3%. My salary is £55,000."
Expected: pension_type=occupational, scheme_name="Aviva Workplace Pension", provider="Aviva", current_fund_value=85000, employee_contribution_percent=5, employer_contribution_percent=3

### Scenario 2: DC SIPP
"I have a SIPP with Hargreaves Lansdown worth £120,000. I contribute £500 a month."
Expected: pension_type=sipp, scheme_name="Hargreaves Lansdown SIPP", provider="Hargreaves Lansdown", current_fund_value=120000, monthly_contribution_amount=500

### Scenario 3: DB Final Salary
"I have an NHS pension. 15 years service, projected annual pension of £12,000. Final salary scheme."
Expected: entity_type=db_pension, employer_name="NHS Pension Scheme", scheme_type=final_salary, annual_income=12000, service_years=15, scheme_status=Active

### Scenario 4: DB Career Average (Deferred)
"I have a deferred Teachers' Pension from when I was teaching. Career average scheme, 8 years service, annual pension of £6,500."
Expected: entity_type=db_pension, employer_name="Teachers' Pension", scheme_type=career_average, annual_income=6500, service_years=8, scheme_status=Deferred
