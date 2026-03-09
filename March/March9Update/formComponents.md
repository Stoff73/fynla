# Onboarding Form Components — Complete Input Map

**Date:** 9 March 2026
**Path:** `resources/js/components/Onboarding/`
**Total files:** 21 Vue components
**Total distinct input fields:** ~60+

---

## Component Architecture

```
OnboardingWizard.vue (router/container)
├── FocusAreaSelection.vue (welcome screen)
│   ├── FocusAreaGrid.vue (journey toggle grid)
│   └── JourneyPreview.vue (step/time estimate)
├── SkipToDashboardModal.vue (confirmation modal)
├── OnboardingStep.vue (wrapper for all steps)
└── steps/
    ├── PersonalInfoStep.vue
    ├── FamilyInfoStep.vue → FamilyMemberFormModal, SpouseSuccessModal
    ├── DomicileInformationStep.vue → CountrySelector
    ├── IncomeStep.vue → OccupationAutocomplete
    ├── ExpenditureStep.vue → ExpenditureForm
    ├── AssetsStep.vue → PropertyCard, PensionFormModal, InvestmentFormModal, SavingsFormModal
    ├── QuickAssetsStep.vue (quick mode only)
    ├── LiabilitiesStep.vue → LiabilityForm
    ├── ProtectionPoliciesStep.vue → PolicyFormModal, DocumentUploadModal
    ├── WillInfoStep.vue
    ├── TrustInfoStep.vue
    ├── GoalSetupStep.vue
    ├── BudgetingSteps.vue
    ├── CompletionStep.vue
    └── JourneyCompletionStep.vue
```

---

## Step Flow & Modes

### Full Mode (13 steps)

| # | Step | Component | Conditional |
|---|------|-----------|-------------|
| 1 | Focus Area Selection | FocusAreaSelection.vue | No |
| 2 | Personal Info | PersonalInfoStep.vue | No |
| 3 | Family Info | FamilyInfoStep.vue | No |
| 4 | Domicile | DomicileInformationStep.vue | No |
| 5 | Income | IncomeStep.vue | No |
| 6 | Expenditure | ExpenditureStep.vue | No |
| 7 | Assets | AssetsStep.vue (4 tabs) | No |
| 8 | Liabilities | LiabilitiesStep.vue | No |
| 9 | Protection | ProtectionPoliciesStep.vue | No |
| 10 | Will | WillInfoStep.vue | No |
| 11 | Trusts | TrustInfoStep.vue | No |
| 12 | Goals | GoalSetupStep.vue | No |
| 13 | Completion | CompletionStep.vue | No |

### Quick Mode (3 steps + redirect)

| # | Step | Component |
|---|------|-----------|
| 1 | Personal Info | PersonalInfoStep.vue |
| 2 | Income | IncomeStep.vue |
| 3 | Quick Assets | QuickAssetsStep.vue |
| — | Dashboard redirect | — |

### Journey Mode (variable steps)

Steps determined by backend journey definition. Resolves step components dynamically via `resolveJourneyComponent`. Ends with JourneyCompletionStep.vue.

---

## Input Map by Component

### 1. FocusAreaGrid.vue

**Type:** Visual toggle grid (not a traditional form)
**Separate component:** Yes — used inside FocusAreaSelection.vue

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | budgeting | toggle button | Budgeting | No | Journey area selector |
| 2 | protection | toggle button | Protection | No | Journey area selector |
| 3 | investment | toggle button | Investment | No | Journey area selector |
| 4 | retirement | toggle button | Retirement | No | Journey area selector |
| 5 | estate | toggle button | Estate | No | Journey area selector |
| 6 | family | toggle button | Family | No | Journey area selector |
| 7 | business | toggle button | Business | No | Journey area selector |
| 8 | goals | toggle button | Goals | No | Journey area selector |

---

### 2. PersonalInfoStep.vue

**Type:** Step form (inline)
**Separate component:** No — fields defined directly in template

| # | Field | Input Type | Label | Required | Validation | Notes |
|---|-------|------------|-------|----------|------------|-------|
| 1 | date_of_birth | date | Date of Birth | Yes | 18-105 years old | HTML5 min/max |
| 2 | gender | select | Gender | Yes | — | male, female, other |
| 3 | marital_status | select | Marital Status | Yes | — | single, married, divorced, widowed |
| 4 | address_line_1 | text | Address Line 1 | Yes | — | Placeholder: "123 Test Street" |
| 5 | address_line_2 | text | Address Line 2 | No | — | Placeholder: "Apartment, suite, etc." |
| 6 | city | text | City | Yes | — | Placeholder: "London" |
| 7 | county | text | County | No | — | Placeholder: "Greater London" |
| 8 | postcode | text | Postcode | Yes | maxlength: 8 | Uppercase formatted |
| 9 | phone | tel | Phone Number | No | — | Placeholder: "07700 900000" |
| 10 | health_status | select | Are you in good health? | No | — | yes, yes_previous, no_previous, no_existing, no_both |
| 11 | smoking_status | select | Do you smoke? | No | — | never, quit_recent, quit_long_ago, yes |
| 12 | education_level | select | Highest Education Level | No | — | secondary, a_level, undergraduate, postgraduate, professional, other |

**Total: 12 fields (5 required)**

---

### 3. FamilyInfoStep.vue

**Type:** Step form with modal sub-form
**Separate component:** Yes — uses FamilyMemberFormModal (separate component)

Main step has no direct inputs. Family members are added via modal:

**FamilyMemberFormModal fields:**

| # | Field | Input Type | Label | Required | Validation | Notes |
|---|-------|------------|-------|----------|------------|-------|
| 1 | name | text | Name | Yes | — | Member's name |
| 2 | relationship | select | Relationship | Yes | — | spouse, child, step_child, parent, other_dependent |
| 3 | date_of_birth | date | Date of Birth | Yes | — | — |
| 4 | is_dependent | checkbox | Is Dependent | No | — | Toggle |
| 5 | email | email | Email | Conditional | Spouse only | For spouse account linking |

**Also uses:** SpouseSuccessModal (no inputs — confirmation only)

**Total: 5 fields per family member**

---

### 4. DomicileInformationStep.vue

**Type:** Step form
**Separate component:** Uses CountrySelector (separate component)

| # | Field | Input Type | Label | Required | Validation | Notes |
|---|-------|------------|-------|----------|------------|-------|
| 1 | country_of_birth | select (CountrySelector) | Where were you born? | No | — | Auto-determines domicile |
| 2 | uk_arrival_date | date | Date Moved to UK | Conditional | Non-UK born only | Max: today |
| 3 | years_uk_resident | calculated | — | — | Auto-calculated | From uk_arrival_date |
| 4 | deemed_domicile_date | calculated | — | — | Auto-calculated | If 15+ years resident |
| 5 | domicile_status | calculated | — | — | Auto-determined | uk_domiciled, non_uk_domiciled |

**Total: 2 user inputs + 3 auto-calculated**

---

### 5. IncomeStep.vue

**Type:** Step form
**Separate component:** Uses OccupationAutocomplete (separate component)

| # | Field | Input Type | Label | Required | Validation | Notes |
|---|-------|------------|-------|----------|------------|-------|
| 1 | employment_status | select | Employment Status | No | — | employed, part_time, self_employed, unemployed, retired, other |
| 2 | occupation | autocomplete | Occupation | Conditional | Employed/self-employed/retired | OccupationAutocomplete component |
| 3 | employer | text | Employer | Conditional | Employed/self-employed/retired | Placeholder: "Tech Company Ltd" |
| 4 | industry | text | Industry | Conditional | Employed/self-employed/retired | Placeholder: "Technology" |
| 5 | target_retirement_age | number | Retirement Age | Conditional | Non-retired, min: 30, max: 75 | Warning if < 55 |
| 6 | annual_employment_income | number | Annual Employment Income | Conditional | Employed/part_time | Min: 0, step: 1000, currency |
| 7 | annual_self_employment_income | number | Annual Self-Employment Income | Conditional | Self-employed | Min: 0, step: 1000, currency |
| 8 | annual_benefit_income | number | Annual Benefit Income | Conditional | Unemployed | Min: 0, step: 100, currency |
| 9 | annual_dividend_income | number | Annual Dividend Income | No | — | Min: 0, step: 100, currency |
| 10 | annual_interest_income | number | Annual Interest Income | No | — | Min: 0, step: 100, currency |
| 11 | annual_other_income | number | Annual Other Income | No | — | Min: 0, step: 1000, currency |
| 12 | annual_rental_income | number (read-only) | Annual Rental Income | — | — | Calculated from properties, read-only |

**Total: 12 fields (0 always required, most conditional)**
**Computed:** Total annual income auto-calculated and displayed

---

### 6. ExpenditureStep.vue

**Type:** Step form
**Separate component:** Yes — uses ExpenditureForm (separate shared component from budgeting module)

| # | Field | Input Type | Label | Required | Validation | Notes |
|---|-------|------------|-------|----------|------------|-------|
| 1 | monthly_expenditure | number | Monthly Expenditure | No | Currency | Via ExpenditureForm |
| 2 | annual_expenditure | number | Annual Expenditure | No | Currency | Via ExpenditureForm |

**Validation:** Requires at least one spouse to have entered data if married

**Total: 2 fields (via child component)**

---

### 7. AssetsStep.vue

**Type:** Tabbed step form with 4 tabs and modal sub-forms
**Separate components:** PropertyCard, PensionFormModal, InvestmentFormModal, SavingsFormModal, DocumentUploadModal (all separate)

#### Tab 1: Retirement (Pensions)

**PensionFormModal — Defined Contribution (Money Purchase):**

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | provider | text | Provider | Yes | e.g. "Aviva" |
| 2 | pension_type | select | Pension Type | Yes | workplace, personal, sipp |
| 3 | current_value | number | Current Value | Yes | Currency |
| 4 | monthly_contribution | number | Monthly Contribution | No | Currency |
| 5 | employer_contribution | number | Employer Contribution | No | Currency |
| 6 | retirement_age | number | Target Retirement Age | No | — |

**PensionFormModal — Defined Benefit (Final Salary):**

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | provider | text | Scheme Name | Yes | e.g. "NHS Pension" |
| 2 | annual_pension | number | Annual Pension at Retirement | Yes | Currency |
| 3 | retirement_age | number | Normal Retirement Age | Yes | — |
| 4 | lump_sum | number | Tax-Free Lump Sum | No | Currency |

**PensionFormModal — State Pension:**

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | qualifying_years | number | National Insurance Qualifying Years | No | Min: 0, max: 35 |
| 2 | state_pension_age | number | State Pension Age | No | Auto-determined by DOB |

#### Tab 2: Properties

**PropertyFormModal fields:**

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | property_type | select | Property Type | Yes | main_residence, secondary_residence, buy_to_let |
| 2 | address | text | Address | Yes | — |
| 3 | current_value | number | Current Value | Yes | Currency |
| 4 | mortgage_balance | number | Mortgage Balance | No | Currency |
| 5 | monthly_mortgage_payment | number | Monthly Payment | No | Currency |
| 6 | rental_income | number | Monthly Rental Income | Conditional | BTL only, currency |
| 7 | ownership_type | select | Ownership | Yes | individual, joint, tenants_in_common |
| 8 | ownership_percentage | number | Ownership % | Conditional | Joint/TIC only |

#### Tab 3: Investments

**InvestmentFormModal fields:**

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | account_type | select | Account Type | Yes | isa, general, stocks_shares_isa, junior_isa |
| 2 | provider | text | Provider | Yes | — |
| 3 | current_value | number | Current Value | Yes | Currency |
| 4 | monthly_contribution | number | Monthly Contribution | No | Currency |
| 5 | ownership_type | select | Ownership | Yes | individual, joint |

#### Tab 4: Savings

**SavingsFormModal fields:**

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | account_type | select | Account Type | Yes | easy_access, fixed_rate, notice, cash_isa, regular_saver |
| 2 | provider | text | Provider | Yes | — |
| 3 | current_balance | number | Current Balance | Yes | Currency |
| 4 | interest_rate | number | Interest Rate (%) | No | — |
| 5 | monthly_contribution | number | Monthly Contribution | No | Currency |

**Total across all tabs: ~30 fields (variable, per-item repeatable)**

---

### 8. QuickAssetsStep.vue (Quick Mode Only)

**Type:** Step form
**Separate component:** No — fields inline

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | properties | toggle | Properties | No | Asset area flag |
| 2 | savings | toggle | Savings | No | Asset area flag |
| 3 | investments | toggle | Investments | No | Asset area flag |
| 4 | pensions | toggle | Pensions | No | Asset area flag |
| 5 | protection | toggle | Life Insurance & Protection | No | Asset area flag |

**Total: 5 toggles**

---

### 9. LiabilitiesStep.vue

**Type:** Step form with modal
**Separate component:** Yes — uses LiabilityForm (separate component)

**LiabilityForm fields:**

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | liability_type | select | Liability Type | Yes | personal_loan, car_finance, credit_card, overdraft, other |
| 2 | liability_name | text | Liability Name | Yes | e.g. "Barclays Credit Card" |
| 3 | current_balance | number | Current Balance | Yes | Currency |
| 4 | interest_rate | number | Interest Rate (%) | No | — |
| 5 | monthly_payment | number | Monthly Payment | No | Currency |
| 6 | repayment_date | date | Expected Repayment Date | No | — |

**Total: 6 fields per liability (repeatable)**

---

### 10. ProtectionPoliciesStep.vue

**Type:** Step form with modal
**Separate component:** Yes — uses PolicyFormModal, DocumentUploadModal (both separate)

**Main step field:**

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | has_no_policies | checkbox | I have no protection policies | No | Hides add buttons if checked |

**PolicyFormModal fields:**

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 2 | policy_type | select | Policy Type | Yes | life, criticalIllness, incomeProtection, disability, sicknessIllness |
| 3 | provider | text | Provider | Yes | e.g. "Aviva", "Legal & General" |
| 4 | life_policy_type | select | Life Policy Type | Conditional | Life only: term, whole_of_life, decreasing_term |
| 5 | sum_assured | number | Coverage Amount | Yes | Currency |
| 6 | premium_amount | number | Premium Amount | Yes | Currency |
| 7 | premium_frequency | select | Premium Frequency | Yes | monthly, annual |
| 8 | policy_number | text | Policy Number | No | — |

**Total: 8 fields per policy (repeatable)**

---

### 11. WillInfoStep.vue

**Type:** Step form
**Separate component:** No — fields inline

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | has_will | radio | Do you currently have a valid will? | Yes | true/false |
| 2 | will_last_updated | date | When was your will last updated? | Conditional | has_will = true |
| 3 | executor_name | text | Who is your executor? | Conditional | has_will = true |

**Total: 3 fields (1 required, 2 conditional)**

---

### 12. TrustInfoStep.vue

**Type:** Step form
**Separate component:** No — fields inline

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | has_trusts | radio | Have you created or benefit from any trusts? | Yes | true/false |
| 2 | trust_count | number | Number of Trusts | Conditional | has_trusts = true, min: 0 |

**Total: 2 fields**

---

### 13. GoalSetupStep.vue

**Type:** Step form
**Separate component:** No — fields inline

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | goal_type | select | What are you saving for? | Yes | emergency_fund, house_deposit, holiday, education, wedding, car, home_improvement, other |
| 2 | name | text | Goal Name | Conditional | goal_type = "other" only |
| 3 | target_amount | number | Target Amount | Conditional | Min: 0, step: 100, currency |
| 4 | target_date | date | Target Date | Conditional | Min: today |

**Computed:** months_remaining, monthly_contribution (both auto-calculated)

**Total: 4 fields**

---

### 14. BudgetingSteps.vue

**Type:** Step form
**Separate component:** No — fields inline

| # | Field | Input Type | Label | Required | Notes |
|---|-------|------------|-------|----------|-------|
| 1 | monthly_income | number | Total Monthly Income (after tax) | No | Min: 0, step: 100, currency, placeholder: "3000" |
| 2 | monthly_expenditure | number | Total Monthly Spending | No | Min: 0, step: 100, currency, placeholder: "2000" |

**Computed:** monthlySurplus (auto-calculated, warning if negative)

**Total: 2 fields**

---

### 15. CompletionStep.vue

**Type:** Summary/completion (no inputs)
**Separate component:** No
- Displays checklist of completed steps
- Clickable sections to review/edit
- Navigate to dashboard button

---

### 16. JourneyCompletionStep.vue

**Type:** Summary/completion (no inputs)
**Separate component:** No
- Checkmark animation
- Lists completed steps
- "What happens next" guidance
- Navigate to module, next journey, or dashboard

---

## Summary

### Components Without Inputs (wrappers/display only)

| Component | Role |
|-----------|------|
| OnboardingWizard.vue | Main router/container |
| OnboardingStep.vue | Step wrapper (title, buttons, slot) |
| FocusAreaSelection.vue | Welcome screen container |
| JourneyPreview.vue | Step/time estimate display |
| SkipToDashboardModal.vue | Confirmation modal |
| CompletionStep.vue | Summary display |
| JourneyCompletionStep.vue | Journey summary display |

### Components With Inline Inputs (not separate)

| Component | Fields |
|-----------|--------|
| FocusAreaGrid.vue | 8 toggles |
| PersonalInfoStep.vue | 12 fields |
| QuickAssetsStep.vue | 5 toggles |
| WillInfoStep.vue | 3 fields |
| TrustInfoStep.vue | 2 fields |
| GoalSetupStep.vue | 4 fields |
| BudgetingSteps.vue | 2 fields |

### Components Using Separate Sub-Form Components

| Step Component | Sub-Form Component | Fields per instance |
|----------------|--------------------|---------------------|
| FamilyInfoStep.vue | FamilyMemberFormModal | 5 |
| DomicileInformationStep.vue | CountrySelector | 1 (select) |
| IncomeStep.vue | OccupationAutocomplete | 1 (autocomplete) |
| ExpenditureStep.vue | ExpenditureForm | 2 |
| AssetsStep.vue | PensionFormModal (DC) | 6 |
| AssetsStep.vue | PensionFormModal (DB) | 4 |
| AssetsStep.vue | PensionFormModal (State) | 2 |
| AssetsStep.vue | PropertyFormModal | 8 |
| AssetsStep.vue | InvestmentFormModal | 5 |
| AssetsStep.vue | SavingsFormModal | 5 |
| LiabilitiesStep.vue | LiabilityForm | 6 |
| ProtectionPoliciesStep.vue | PolicyFormModal | 8 |
| ProtectionPoliciesStep.vue | DocumentUploadModal | file upload |

### Total Field Count

| Category | Fields |
|----------|--------|
| Focus area toggles | 8 |
| Personal info | 12 |
| Family (per member) | 5 |
| Domicile | 2 + 3 auto |
| Income | 12 |
| Expenditure | 2 |
| Assets — DC Pension (per item) | 6 |
| Assets — DB Pension (per item) | 4 |
| Assets — State Pension | 2 |
| Assets — Property (per item) | 8 |
| Assets — Investment (per item) | 5 |
| Assets — Savings (per item) | 5 |
| Quick assets (quick mode) | 5 |
| Liabilities (per item) | 6 |
| Protection (per policy) | 8 |
| Will | 3 |
| Trusts | 2 |
| Goals | 4 |
| Budgeting | 2 |
| **Fixed fields** | **~47** |
| **Repeatable fields** | **~47 per item** |
