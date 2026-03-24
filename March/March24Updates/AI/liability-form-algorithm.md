# Liability Form Algorithm — Complete Field-by-Field Map

**Date:** 24 March 2026
**Source:** `resources/js/components/Estate/LiabilityForm.vue`

## Form Structure

Single-step modal form. Opens when "Add Liability" is clicked on `/net-worth/liabilities`. No multi-step wizard.

`LiabilitiesList.vue` watches `pendingFill` for entity_type `estate_liability` and auto-opens the modal.

## Liability Types

| Value | Label | Priority Debt? |
|-------|-------|---------------|
| `secured_loan` | Secured Loan | Auto-set |
| `personal_loan` | Personal Loan | No |
| `credit_card` | Credit Card | No |
| `overdraft` | Bank Overdraft | No |
| `hire_purchase` | Hire Purchase / Car Finance | No |
| `student_loan` | Student Loan | No |
| `business_loan` | Business Loan | No |
| `other` | Other | No |

Note: `mortgage` type exists in the form but mortgages are typically added via the Property form. AI tool maps `loan` → `personal_loan`.

## Form Fields

| Field | v-model | Type | Required | ai-fill-highlight | Notes |
|-------|---------|------|----------|------------------|-------|
| Liability Type | `formData.liability_type` | `<select>` | YES (backend) | YES | See types above |
| Liability Name | `formData.liability_name` | text | YES (backend) | YES | |
| Current Balance | `formData.current_balance` | number | YES (backend) | YES | Amount owed |
| Monthly Payment | `formData.monthly_payment` | number | No | YES | |
| Interest Rate | `formData.interest_rate` | number (%) | No | YES | |
| Maturity Date | `formData.maturity_date` | date | No | No | Expected payoff date |
| Secured Against | `formData.secured_against` | text | No | No | Asset name |
| Priority Debt | `formData.is_priority_debt` | checkbox | No | No | Auto-set for mortgage/secured |
| Notes | `formData.notes` | textarea | No | No | |

### Conditional: Mortgage (liability_type === 'mortgage')
| Field | v-model | Type |
|-------|---------|------|
| Mortgage Type | `formData.mortgage_type` | `<select>` (repayment/interest_only/fixed_rate/variable_rate/tracker) |
| Fixed Until | `formData.fixed_until` | date |

## Validation (validateForm)

All fields are optional — `validateForm()` always returns true. Backend validates `liability_type`, `liability_name`, `current_balance`.

## AI Fill Flow (already implemented)

### pendingFill watcher (line 397):
1. Pre-sets `liability_type`, `liability_name`, `current_balance` before sequence
2. Builds field order, dispatches `beginFieldSequence`

### highlightedField watcher (line 417):
Catch-all: `this.formData[fieldKey] = value`

### filling watcher (line 426):
Auto-submits via `handleSubmit()` after 250ms

## Backend: handleCreateEstateLiability

1. Validates `liability_name`, `liability_type`, `current_balance`, `monthly_payment`, `interest_rate`
2. Maps `loan` → `personal_loan`
3. Returns `fill_form` with entity_type `estate_liability`, route `/net-worth/liabilities`

## Test Scenarios

### Scenario 1: Credit Card
"I have a Barclays credit card with £3,500 owing, 19.9% interest, I pay £150 a month"
Expected: liability_type=credit_card, name="Barclays Credit Card", balance=3500, rate=19.9, payment=150

### Scenario 2: Personal Loan
"I have a personal loan with Halifax for £8,000, paying £250 a month at 6.5%"
Expected: liability_type=personal_loan, name="Halifax Personal Loan", balance=8000, rate=6.5, payment=250

### Scenario 3: Student Loan
"I have a Plan 2 student loan with £28,000 outstanding"
Expected: liability_type=student_loan, name="Plan 2 Student Loan", balance=28000

### Scenario 4: Car Finance (Hire Purchase)
"I have car finance on my BMW, £12,000 remaining, £350 a month at 4.9%"
Expected: liability_type=hire_purchase, name="BMW Car Finance", balance=12000, rate=4.9, payment=350
