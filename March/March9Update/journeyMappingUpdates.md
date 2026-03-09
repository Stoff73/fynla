# Journey Mapping — Simplified Onboarding Forms

**Date:** 9 March 2026
**Branch:** `onboarding`

This document maps every journey to its simplified onboarding steps, including every input field per step, which component renders it, and how overlapping fields are handled when users select multiple journeys.

---

## Table of Contents

1. [Budgeting Journey](#1-budgeting-journey)
2. [Protection Journey](#2-protection-journey)
3. [Investment Journey](#3-investment-journey-planned)
4. [Retirement Journey](#4-retirement-journey-planned)
5. [Estate Journey](#5-estate-journey-planned)
6. [Multi-Journey Combinations](#6-multi-journey-combinations)
7. [Field Overlap Matrix](#7-field-overlap-matrix)
8. [Technical Implementation](#8-technical-implementation)

---

## 1. Budgeting Journey

**Status:** Implemented
**Steps:** 4 + completion
**Component prefix:** `Simple*`

### Step 1: Personal Information
**Component:** `SimplePersonalInfoStep.vue`

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| First name | text (read-only) | - | Pre-filled from registration, disabled |
| Surname | text (read-only) | - | Pre-filled from registration, disabled |
| Phone | tel | No | For two-factor authentication |

*DOB is NOT shown for budgeting-only. Conditional on journey selections.*

### Step 2: Your Income
**Component:** `SimpleIncomeStep.vue`

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Employment status | select | No | Employed, Part-Time, Self-Employed, Student, Unemployed, Retired, Other |
| Monthly take-home pay | number (£) | No | Shown when employed/part-time/self-employed/student/other. Labelled as after-tax |

*Occupation and retirement age NOT shown for budgeting-only. Conditional on journey selections.*

### Step 3: Your Monthly Outgoings
**Component:** `SimpleExpenditureStep.vue`

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Total monthly spending | number (£) | No | Monthly only. Surplus preview shown if income was entered |

### Step 4: Your Savings Accounts
**Component:** `SimpleSavingsAccountStep.vue`

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Institution | text | No | e.g., Halifax, Barclays |
| Product type | select | No | Savings Account, Current Account, Easy Access, ISA, etc. |
| Current balance | number (£) | No | |
| Interest rate | number (%) | No | |
| Emergency fund | checkbox | No | "This forms part of my emergency fund" |

*User can add multiple accounts. Creates real records via `savingsService.createAccount()`.*

### Completion
**Component:** `BudgetingCompletionStep.vue`
- Congratulations messaging
- Journey benefits: spending habits, financial goals, emergency fund tracking, personalised recommendations
- Links to `/net-worth/cash` dashboard

---

## 2. Protection Journey

**Status:** To implement
**Steps:** 7 + completion
**Approach:** Extend existing simplified components with conditional fields + reuse existing full step components for complex forms

### Step 1: Personal Information
**Component:** `SimplePersonalInfoStep.vue` (extended)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| First name | text (read-only) | - | Pre-filled from registration |
| Surname | text (read-only) | - | Pre-filled from registration |
| Phone | tel | No | For two-factor authentication |
| Date of birth | date | Yes | Shown because protection selected (conditional) |
| Marital status | select | Yes | Single, Married, Divorced, Widowed |
| Health status | select | No | Good health / previous conditions / existing conditions |
| Smoking status | select | No | Never, quit recently, quit long ago, yes |

*DOB, marital status, health, smoking are conditionally shown when protection journey is selected.*

### Step 2: Your Income
**Component:** `SimpleIncomeStep.vue` (extended)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Employment status | select | No | Employed, Part-Time, Self-Employed, Student, Unemployed, Retired, Other |
| Occupation | text (autocomplete) | No | Shown when protection selected. Affects insurance premiums |
| Monthly take-home pay | number (£) | No | Labelled as after-tax |

*Occupation field conditionally shown when protection journey is selected.*

### Step 3: Your Monthly Outgoings
**Component:** `SimpleExpenditureStep.vue` (reused as-is)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Total monthly spending | number (£) | No | Surplus preview if income entered |

### Step 4: Family and Dependants
**Component:** `FamilyInfoStep.vue` (existing full component)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Family member name | text | Yes | Per member |
| Relationship | select | Yes | Child, Step Child, Dependant, Parent, Sibling, Other |
| Date of birth | date | No | Per member |
| Financial dependent | checkbox | No | Per member |

*Uses existing FamilyInfoStep — it already handles add/edit/delete of multiple family members.*

### Step 5: Debts and Loans
**Component:** `LiabilitiesStep.vue` (existing full component)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Liability type | select | Yes | Personal loan, Car finance, Credit card, Student loan, Other |
| Name/description | text | No | Per liability |
| Outstanding balance | number (£) | Yes | Per liability |
| Monthly payment | number (£) | No | Per liability |
| Interest rate | number (%) | No | Per liability |
| End date | date | No | Per liability |

*Uses existing LiabilitiesStep — handles add/edit/delete of multiple liabilities.*

### Step 6: Existing Protection Policies
**Component:** `ProtectionPoliciesStep.vue` (existing full component)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| "I have no policies" | checkbox | No | Skips the form if checked |
| Policy type | select | Yes | Life, Critical Illness, Income Protection, etc. |
| Provider | text | No | Insurance company name |
| Sum assured | number (£) | Yes | Cover amount |
| Monthly premium | number (£) | No | |
| Start date | date | No | |
| End date | date | No | |
| In trust | checkbox | No | |

*Uses existing ProtectionPoliciesStep — handles add/edit/delete of multiple policies. "I have no policies" checkbox skips.*

### Completion
**Component:** `JourneyCompletionStep.vue` (existing generic)
- Links to Protection dashboard
- Shows completed steps summary

---

## 3. Investment Journey (Planned)

**Steps:** 4 + completion (estimated)

### Step 1: Personal Information
**Component:** `SimplePersonalInfoStep.vue`
- Name (read-only), phone, DOB (shown), target retirement age (shown)

### Step 2: Your Income
**Component:** `SimpleIncomeStep.vue`
- Employment status, monthly take-home pay, target retirement age

### Step 3: Investment Accounts
**Component:** Existing `AssetsStep.vue` or new simplified investment step
- Investment wrapper type (ISA, SIPP, GIA), provider, value, contributions

### Completion
- Links to `/net-worth/investments` dashboard

---

## 4. Retirement Journey (Planned)

**Steps:** 5 + completion (estimated)

### Step 1: Personal Information
**Component:** `SimplePersonalInfoStep.vue`
- Name (read-only), phone, DOB (shown)

### Step 2: Your Income
**Component:** `SimpleIncomeStep.vue`
- Employment status, monthly take-home pay, target retirement age (shown)

### Step 3: Your Monthly Outgoings
**Component:** `SimpleExpenditureStep.vue`
- Monthly spending (for retirement income target)

### Step 4: Your Pensions
**Component:** Existing pension steps or new simplified pension step
- Defined Contribution, Defined Benefit, State Pension

### Completion
- Links to `/net-worth/retirement` dashboard

---

## 5. Estate Journey (Planned)

**Steps:** 5 + completion (estimated)

### Step 1: Personal Information
**Component:** `SimplePersonalInfoStep.vue`
- Name (read-only), phone, DOB (shown), marital status (shown), domicile status

### Step 2: Family
**Component:** `FamilyInfoStep.vue`
- Family members, spouse details

### Step 3: Properties
**Component:** Existing property step
- Property details for estate value

### Step 4: Investment Accounts
**Component:** Existing investment step
- Investment accounts for estate value

### Completion
- Links to Estate dashboard

---

## 6. Multi-Journey Combinations

### Budgeting + Protection

**Total steps:** 7 + completion (merged)

When both are selected, the shared simplified components show ALL fields needed by both journeys. No duplicate steps.

| Step | Component | Fields from Budgeting | Fields from Protection | Merged Result |
|------|-----------|----------------------|----------------------|---------------|
| 1. Personal Info | `SimplePersonalInfoStep` | Name, phone | + DOB, marital status, health, smoking | Name, phone, DOB, marital status, health, smoking |
| 2. Income | `SimpleIncomeStep` | Employment status, income | + Occupation | Employment status, occupation, income |
| 3. Expenditure | `SimpleExpenditureStep` | Monthly spending | Monthly spending | Monthly spending (no change) |
| 4. Family | `FamilyInfoStep` | - | Family members | Family members |
| 5. Debts | `LiabilitiesStep` | - | Liabilities | Liabilities |
| 6. Savings | `SimpleSavingsAccountStep` | Savings accounts | - | Savings accounts |
| 7. Protection | `ProtectionPoliciesStep` | - | Protection policies | Protection policies |
| Done | Completion | Budgeting benefits | Protection benefits | Combined benefits |

**Key:** Steps 1-3 are shared (simplified components with conditional fields). Steps 4-7 are journey-specific and only appear if that journey requires them.

### Budgeting + Investment (Future)

| Step | From Budgeting | From Investment | Merged |
|------|---------------|----------------|--------|
| Personal Info | Name, phone | + DOB | Name, phone, DOB |
| Income | Employment, income | + retirement age | Employment, income, retirement age |
| Savings | Savings accounts | - | Savings accounts |
| Investments | - | Investment accounts | Investment accounts |

### Budgeting + Retirement (Future)

| Step | From Budgeting | From Retirement | Merged |
|------|---------------|----------------|--------|
| Personal Info | Name, phone | + DOB | Name, phone, DOB |
| Income | Employment, income | + retirement age | Employment, income, retirement age |
| Expenditure | Monthly spending | Monthly spending | Monthly spending (no change) |
| Savings | Savings accounts | - | Savings accounts |
| Pensions | - | DC, DB, State Pension | DC, DB, State Pension |

### Protection + Retirement (Future)

| Step | From Protection | From Retirement | Merged |
|------|----------------|----------------|--------|
| Personal Info | Name, phone, DOB, marital, health, smoking | DOB (already included) | Name, phone, DOB, marital, health, smoking |
| Income | Employment, occupation, income | + retirement age | Employment, occupation, income, retirement age |
| Expenditure | Monthly spending | Monthly spending | Monthly spending (no change) |
| Family | Family members | - | Family members |
| Debts | Liabilities | - | Liabilities |
| Protection | Protection policies | - | Protection policies |
| Pensions | - | DC, DB, State Pension | DC, DB, State Pension |

### Budgeting + Protection + Retirement (Future — 3-way)

| Step | Merged Fields |
|------|--------------|
| Personal Info | Name, phone, DOB, marital status, health, smoking |
| Income | Employment, occupation, income, retirement age |
| Expenditure | Monthly spending |
| Family | Family members |
| Debts | Liabilities |
| Savings | Savings accounts |
| Protection | Protection policies |
| Pensions | DC, DB, State Pension |

---

## 7. Field Overlap Matrix

Shows which personal fields are needed by each journey. Used to determine which conditional fields to show on shared steps.

| Field | Budgeting | Protection | Investment | Retirement | Estate | Goals |
|-------|-----------|------------|------------|------------|--------|-------|
| Name (read-only) | Yes | Yes | Yes | Yes | Yes | Yes |
| Phone | Yes | Yes | Yes | Yes | Yes | Yes |
| DOB | - | Yes | Yes | Yes | Yes | Yes |
| Marital status | - | Yes | - | - | Yes | - |
| Health status | - | Yes | - | - | - | - |
| Smoking status | - | Yes | - | - | - | - |
| Employment status | Yes | Yes | Yes | Yes | - | Yes |
| Occupation | - | Yes | - | - | - | - |
| Income (after tax) | Yes | Yes | Yes | Yes | - | Yes |
| Retirement age | - | - | Yes | Yes | - | - |
| Monthly expenditure | Yes | Yes | - | Yes | - | Yes |
| Domicile status | - | - | - | - | Yes | - |

### Financial steps by journey (not merged — each appears once)

| Financial Step | Budgeting | Protection | Investment | Retirement | Estate | Goals |
|---------------|-----------|------------|------------|------------|--------|-------|
| Savings accounts | Yes | - | - | - | - | - |
| Family members | - | Yes | - | - | Yes | - |
| Liabilities | - | Yes | - | - | - | - |
| Protection policies | - | Yes | - | - | - | - |
| Mortgages | - | Yes | - | - | - | - |
| Investment accounts | - | - | Yes | - | Yes | - |
| DC Pensions | - | - | - | Yes | - | - |
| DB Pensions | - | - | - | Yes | - | - |
| State Pension | - | - | - | Yes | - | - |
| Properties | - | - | - | - | Yes | - |
| Goals | - | - | - | - | - | Yes |

---

## 8. Technical Implementation

### How conditional fields work

The simplified step components (`SimplePersonalInfoStep`, `SimpleIncomeStep`, `SimpleExpenditureStep`) use journey selections from the Vuex store to conditionally show/hide fields:

```javascript
// In SimplePersonalInfoStep.vue
const showDateOfBirth = computed(() => {
  const selections = store.state.journeys?.selections || [];
  // Show DOB for any journey except budgeting-only
  if (selections.length === 1 && selections[0] === 'budgeting') return false;
  return true;
});

const showMaritalStatus = computed(() => {
  const selections = store.state.journeys?.selections || [];
  return selections.includes('protection') || selections.includes('estate');
});

const showHealthFields = computed(() => {
  const selections = store.state.journeys?.selections || [];
  return selections.includes('protection');
});
```

### How step merging works (JOURNEY_STEP_OVERRIDES)

For single journeys, the backend returns explicit step overrides. For multi-journey combinations, the system:

1. Starts with the shared simplified steps (personal, income, expenditure) — these are always first
2. Adds journey-specific financial steps in order — no duplicates because each financial step is unique to its journey
3. The simplified step components handle field visibility via journey selections

```php
// JourneyFieldResolver.php — single journey
if (isset(self::JOURNEY_STEP_OVERRIDES[$journey])) {
    return self::JOURNEY_STEP_OVERRIDES[$journey];
}

// Multi-journey — merge simplified steps + unique financial steps
// The frontend components handle showing the right fields based on selections
```

### Component reuse strategy

| Component Type | Strategy |
|---------------|----------|
| `SimplePersonalInfoStep` | Single component, conditional fields based on journey selections |
| `SimpleIncomeStep` | Single component, conditional fields (occupation, retirement age) |
| `SimpleExpenditureStep` | Reused as-is across all journeys that need expenditure |
| `SimpleSavingsAccountStep` | Budgeting-specific |
| `FamilyInfoStep` | Reuse existing full component |
| `LiabilitiesStep` | Reuse existing full component |
| `ProtectionPoliciesStep` | Reuse existing full component |
| `BudgetingCompletionStep` | Budgeting-specific completion |
| `JourneyCompletionStep` | Generic completion for all other journeys |
