# Investment AccountForm.vue Refactor - Extract Account Type Sections

## Overview

Refactor `AccountForm.vue` (2,643 lines) by extracting the three major account-type-specific sections into separate components. This follows the same pattern used successfully for IHTPlanning.vue.

**Current Problem:**
- One component handles 14 different account types
- 56 conditional rendering statements (`v-if`/`v-show`)
- Three distinct form "modes" with completely different fields:
  - Standard investments (ISA, GIA, Bonds, VCT, EIS)
  - Private investments (Private Company, Crowdfunding) - 634 lines
  - Employee share schemes (SAYE, CSOP, EMI, RSUs) - 586 lines

**Impact of Current State:**
- Adding a new account type requires understanding the entire 2,643-line file
- Hard to navigate and maintain
- High risk of breaking other account types when making changes

---

## Component Architecture

```
AccountForm.vue (Parent - refactored from ~2,643 to ~1,200 lines)
  │
  ├── PrivateInvestmentFields.vue (NEW - ~650 lines)
  │     └── Company Details, Investment Details, Ownership, Tax Relief, Exit
  │
  ├── EmployeeShareSchemeFields.vue (NEW - ~600 lines)
  │     └── Employer Details, Grant Details, SAYE/CSOP-specific, Vesting, Exercise
  │
  └── StandardInvestmentFields.vue (NEW - ~400 lines)
        └── Provider, Platform, Current Value, Contributions, Platform Fee, Risk Level
```

---

## Files to Create

### 1. PrivateInvestmentFields.vue
**Location:** `resources/js/components/Investment/PrivateInvestmentFields.vue`

**Purpose:** Form fields for Private Company and Crowdfunding account types

**Extracted from:** Lines 77-710 of AccountForm.vue

**Props:**
```javascript
{
  modelValue: Object,        // v-model for form data (two-way binding)
  errors: Object,            // Validation errors
  isCrowdfunding: Boolean,   // Show crowdfunding-specific fields
}
```

**Sections included:**
- Company Details (legal name, trading name, registration, sector, website)
- Crowdfunding Platform (conditional)
- Investment Details (date, amount, funding round, instrument type, valuation)
- Ownership & Legal (share class, holding structure, rights)
- Debt Instrument fields (conditional)
- UK Tax Relief (EIS/SEIS/SITR/VCT tracking)
- Status & Valuation
- Exit Details (conditional on company_status === 'exited')
- Loss Relief fields (conditional)

---

### 2. EmployeeShareSchemeFields.vue
**Location:** `resources/js/components/Investment/EmployeeShareSchemeFields.vue`

**Purpose:** Form fields for SAYE, CSOP, EMI, Unapproved Options, and RSUs

**Extracted from:** Lines 712-1298 of AccountForm.vue

**Props:**
```javascript
{
  modelValue: Object,        // v-model for form data
  errors: Object,            // Validation errors
  schemeType: String,        // 'saye', 'csop', 'emi', 'unapproved_options', 'rsu'
}
```

**Computed helpers (internal):**
- `isOptionsScheme` - true for saye, csop, emi, unapproved_options (not rsu)
- `isSAYEScheme` - true for saye only
- `isCSOPScheme` - true for csop only
- `isRSUScheme` - true for rsu only

**Sections included:**
- Employer Details (name, registration, ticker, listed status)
- Grant Details (date, reference, units, exercise price)
- SAYE Savings Details (conditional)
- CSOP Info (conditional)
- Vesting Schedule (type, cliff, performance conditions)
- Current Status (units vested/unvested/exercised, share price)
- Exercise & Expiry (conditional - options only, not RSUs)
- Tax Treatment
- Leaver Terms

---

### 3. StandardInvestmentFields.vue
**Location:** `resources/js/components/Investment/StandardInvestmentFields.vue`

**Purpose:** Form fields for standard investment types (ISA, GIA, Bonds, VCT, EIS, etc.)

**Extracted from:** Lines 1300-1750 of AccountForm.vue

**Props:**
```javascript
{
  modelValue: Object,        // v-model for form data
  errors: Object,            // Validation errors
  accountType: String,       // Current account type
  isOnboarding: Boolean,     // Hide risk level during onboarding
  riskLevels: Array,         // Risk level options
}
```

**Sections included:**
- Provider
- Country Selector
- Platform/Product Name
- Current Value
- Regular Contributions
- Platform Fee Section
- Risk Level Section
- ISA-specific fields (subscription, allowance tracker)
- Joint Ownership Section

---

## File to Modify

### AccountForm.vue
**Location:** `resources/js/components/Investment/AccountForm.vue`

**Changes:**

1. **Import new components:**
```javascript
import PrivateInvestmentFields from './PrivateInvestmentFields.vue';
import EmployeeShareSchemeFields from './EmployeeShareSchemeFields.vue';
import StandardInvestmentFields from './StandardInvestmentFields.vue';
```

2. **Replace Private Investment section (lines 77-710) with:**
```vue
<PrivateInvestmentFields
  v-if="isPrivateInvestmentType"
  v-model="formData"
  :errors="errors"
  :is-crowdfunding="isCrowdfundingType"
/>
```

3. **Replace Employee Share Scheme section (lines 712-1298) with:**
```vue
<EmployeeShareSchemeFields
  v-if="isEmployeeShareScheme"
  v-model="formData"
  :errors="errors"
  :scheme-type="formData.account_type"
/>
```

4. **Replace Standard fields section (lines 1300-1750) with:**
```vue
<StandardInvestmentFields
  v-if="!isPrivateInvestmentType && !isEmployeeShareScheme"
  v-model="formData"
  :errors="errors"
  :account-type="formData.account_type"
  :is-onboarding="isOnboarding"
  :risk-levels="riskLevels"
/>
```

5. **Keep in parent:**
   - Modal structure (header, footer)
   - Account type dropdown
   - Form submission logic
   - Validation orchestration
   - All computed properties for account type detection
   - Watch handlers for account type changes
   - API calls and error handling

---

## Implementation Order

### Phase 1: Create PrivateInvestmentFields
1. Create `PrivateInvestmentFields.vue` with template from lines 77-710
2. Set up v-model pattern with `modelValue` prop and `update:modelValue` emit
3. Move internal computed properties (requiresTaxReliefTracking, isDebtInstrument, showExitFields)
4. Test with Private Company and Crowdfunding account types

### Phase 2: Create EmployeeShareSchemeFields
5. Create `EmployeeShareSchemeFields.vue` with template from lines 712-1298
6. Set up v-model pattern
7. Move internal computed properties (isOptionsScheme, isSAYEScheme, etc.)
8. Test with SAYE, CSOP, EMI, Unapproved Options, and RSU types

### Phase 3: Create StandardInvestmentFields
9. Create `StandardInvestmentFields.vue` with template from lines 1300-1750
10. Set up v-model pattern
11. Move ISA-specific computed properties
12. Test with ISA, GIA, Bonds, VCT, EIS types

### Phase 4: Refactor Parent
13. Import all three components
14. Replace template sections with component usage
15. Remove extracted template code
16. Remove moved computed properties (keep account type detection in parent)
17. Test all 14 account types

---

## v-model Pattern for Child Components

Each child component uses the v-model pattern for two-way binding:

```vue
<!-- Parent -->
<PrivateInvestmentFields v-model="formData" :errors="errors" />

<!-- Child -->
<script>
export default {
  props: {
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) }
  },
  emits: ['update:modelValue'],
  computed: {
    localData: {
      get() { return this.modelValue; },
      set(value) { this.$emit('update:modelValue', value); }
    }
  }
}
</script>

<!-- Child template uses localData.field_name -->
<input v-model="localData.company_legal_name" />
```

---

## Verification

### Manual Test Checklist

**Standard Investment Types:**
- [ ] ISA - All fields display, ISA allowance tracker works
- [ ] GIA - Provider, platform, value, contributions display
- [ ] Onshore Bond - Fields display correctly
- [ ] Offshore Bond - Fields display correctly
- [ ] VCT - Fields display correctly
- [ ] EIS - Fields display correctly

**Private Investment Types:**
- [ ] Private Company - All sections display (company, investment, ownership, tax relief)
- [ ] Crowdfunding - Same as above plus platform dropdown
- [ ] Exit fields appear when company_status = 'exited'
- [ ] Tax relief fields appear when EIS/SEIS selected

**Employee Share Scheme Types:**
- [ ] SAYE - SAYE savings section displays, exercise fields display
- [ ] CSOP - CSOP info displays, exercise fields display
- [ ] EMI - Exercise fields display
- [ ] Unapproved Options - Exercise fields display
- [ ] RSU - Exercise fields hidden (RSUs don't have exercise price)

**General:**
- [ ] Switching account types clears/shows appropriate fields
- [ ] Form validation works for all types
- [ ] Save/update works for all types
- [ ] Edit mode loads existing data correctly

---

## Expected Outcome

| Metric | Before | After |
|--------|--------|-------|
| AccountForm.vue lines | ~2,643 | ~1,200 |
| New components | 0 | 3 |
| Conditional statements in parent | 56 | ~20 |
| Total lines (all files) | ~2,643 | ~2,850 |
| Maintainability | Poor | Good |

*Note: Total lines increase slightly due to component boilerplate, but each file is now focused and manageable.*
