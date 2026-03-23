# Onboarding Fix Plan — Remap Journeys to Era 1 Components

**Date:** 20 March 2026
**Goal:** Remap all 5 life stage journeys to use the original Era 1 onboarding components. No new forms. No wrappers. One form for all entry and edit.

---

## Journey Mappings

### Journey 1: Starting Out (university) — 6 steps

| # | Step ID | Component | File | Props | DB Target |
|---|---------|-----------|------|-------|-----------|
| 1 | `personal-info` | PersonalInfoStep | `Onboarding/steps/PersonalInfoStep.vue` | | `users` table |
| 2 | `student-loan` | StudentLoanStep | `Onboarding/steps/StudentLoanStep.vue` | | `student_loans` table |
| 3 | `income` | IncomeStep | `Onboarding/steps/IncomeStep.vue` | | `users` table |
| 4 | `expenditure` | ExpenditureStep | `Onboarding/steps/ExpenditureStep.vue` | | `users` table |
| 5 | `savings` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | `visibleTabs="cash"` | `savings_accounts` table |
| 6 | `goals` | GoalSetupStep | `Onboarding/steps/GoalSetupStep.vue` | | `goals` table |

### Journey 2: Building Foundations (early_career) — 7 steps

| # | Step ID | Component | File | Props | DB Target |
|---|---------|-----------|------|-------|-----------|
| 1 | `personal-info` | PersonalInfoStep | `Onboarding/steps/PersonalInfoStep.vue` | | `users` table |
| 2 | `income-career` | IncomeStep | `Onboarding/steps/IncomeStep.vue` | | `users` table |
| 3 | `savings-emergency` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | `visibleTabs="cash"` | `savings_accounts` table |
| 4 | `first-home-lisa` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | `visibleTabs="cash"` | `savings_accounts` table |
| 5 | `pension-auto-enrolment` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | `visibleTabs="retirement"` | `dc_pensions` table |
| 6 | `investments` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | `visibleTabs="investments"` | `investment_accounts` table |
| 7 | `goals` | GoalSetupStep | `Onboarding/steps/GoalSetupStep.vue` | | `goals` table |

### Journey 3: Protecting What Matters (mid_career) — 8 steps

| # | Step ID | Component | File | Props | DB Target |
|---|---------|-----------|------|-------|-----------|
| 1 | `personal-info` | PersonalInfoStep | `Onboarding/steps/PersonalInfoStep.vue` | | `users` table |
| 2 | `family` | FamilyInfoStep | `Onboarding/steps/FamilyInfoStep.vue` | | `family_members` table |
| 3 | `income` | IncomeStep | `Onboarding/steps/IncomeStep.vue` | | `users` table |
| 4 | `property-mortgage` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | all tabs | `properties`, `mortgages` tables |
| 5 | `protection-insurance` | ProtectionPoliciesStep | `Onboarding/steps/ProtectionPoliciesStep.vue` | | `life_insurance_policies`, `critical_illness_policies`, `income_protection_policies` |
| 6 | `pensions` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | all tabs | `dc_pensions`, `db_pensions` tables |
| 7 | `will-estate` | WillInfoStep | `Onboarding/steps/WillInfoStep.vue` | | `estate_wills` table |
| 8 | `goals` | GoalSetupStep | `Onboarding/steps/GoalSetupStep.vue` | | `goals` table |

### Journey 4: Planning Your Future (peak) — 7 steps

| # | Step ID | Component | File | Props | DB Target |
|---|---------|-----------|------|-------|-----------|
| 1 | `personal-info` | PersonalInfoStep | `Onboarding/steps/PersonalInfoStep.vue` | | `users` table |
| 2 | `income-tax` | IncomeStep | `Onboarding/steps/IncomeStep.vue` | | `users` table |
| 3 | `pension-review` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | all tabs | `dc_pensions`, `db_pensions` tables |
| 4 | `investments-isa` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | all tabs | `investment_accounts` table |
| 5 | `property-portfolio` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | all tabs | `properties`, `mortgages` tables |
| 6 | `estate-iht` | WillInfoStep | `Onboarding/steps/WillInfoStep.vue` | | `estate_wills` table |
| 7 | `goals` | GoalSetupStep | `Onboarding/steps/GoalSetupStep.vue` | | `goals` table |

### Journey 5: Enjoying Your Wealth (retirement) — 6 steps

| # | Step ID | Component | File | Props | DB Target |
|---|---------|-----------|------|-------|-----------|
| 1 | `personal-info` | PersonalInfoStep | `Onboarding/steps/PersonalInfoStep.vue` | | `users` table |
| 2 | `pension-drawdown` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | all tabs | `dc_pensions`, `state_pensions` tables |
| 3 | `state-pension` | AssetsStep | `Onboarding/steps/AssetsStep.vue` | all tabs | `state_pensions` table |
| 4 | `income-tax` | IncomeStep | `Onboarding/steps/IncomeStep.vue` | | `users` table |
| 5 | `estate-legacy` | WillInfoStep | `Onboarding/steps/WillInfoStep.vue` | | `estate_wills` table |
| 6 | `goals` | GoalSetupStep | `Onboarding/steps/GoalSetupStep.vue` | | `goals` table |

---

## STEP_COMPONENTS Mapping (OnboardingWizard.vue)

```javascript
const STEP_COMPONENTS = {
  'personal-info': () => import('@/components/Onboarding/steps/PersonalInfoStep.vue'),
  'student-loan': () => import('@/components/Onboarding/steps/StudentLoanStep.vue'),
  'income': () => import('@/components/Onboarding/steps/IncomeStep.vue'),
  'income-career': () => import('@/components/Onboarding/steps/IncomeStep.vue'),
  'income-tax': () => import('@/components/Onboarding/steps/IncomeStep.vue'),
  'expenditure': () => import('@/components/Onboarding/steps/ExpenditureStep.vue'),
  'savings': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'savings-emergency': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'first-home-lisa': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'property-mortgage': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'property-portfolio': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'protection-insurance': () => import('@/components/Onboarding/steps/ProtectionPoliciesStep.vue'),
  'pensions': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'pension-auto-enrolment': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'pension-review': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'pension-drawdown': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'state-pension': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'investments': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'investments-isa': () => import('@/components/Onboarding/steps/AssetsStep.vue'),
  'family': () => import('@/components/Onboarding/steps/FamilyInfoStep.vue'),
  'will-estate': () => import('@/components/Onboarding/steps/WillInfoStep.vue'),
  'estate-iht': () => import('@/components/Onboarding/steps/WillInfoStep.vue'),
  'estate-legacy': () => import('@/components/Onboarding/steps/WillInfoStep.vue'),
  'goals': () => import('@/components/Onboarding/steps/GoalSetupStep.vue'),
};
```

---

## AssetsStep.vue — `visibleTabs` Prop + Dynamic Title

**Changes to AssetsStep.vue:**

```javascript
props: {
  visibleTabs: {
    type: String,
    default: null,  // null = show all 4 tabs
    // "cash" = Cash tab only
    // "retirement" = Retirement tab only
    // "investments" = Investments tab only
    // "properties" = Properties tab only
  }
}
```

**Logic:**
- `null` → show all tabs, tab bar visible, title "Assets & Wealth" (Journeys 3, 4, 5)
- Single tab value → show only that tab, hide tab bar (Journeys 1, 2)
- `activeTab` defaults to the visible tab
- `assetTabs` computed filters based on `visibleTabs` prop

**Dynamic title per tab:**

| `visibleTabs` | Title | Description |
|---------------|-------|-------------|
| `null` | Assets & Wealth | Add your properties, investments, and savings accounts |
| `cash` | Bank Accounts | Add your bank and savings accounts |
| `retirement` | Pensions | Add your pension schemes so we can project your retirement income |
| `investments` | Investments | Add your investment accounts so we can analyse your portfolio |
| `properties` | Properties | Add your properties and any mortgages |

**How the wizard passes the prop:**
```javascript
// In OnboardingWizard.vue, map step IDs to tab names
const STEP_TAB_MAP = {
  'savings': 'cash',
  'savings-emergency': 'cash',
  'first-home-lisa': 'cash',
  'pension-auto-enrolment': 'retirement',
  'investments': 'investments',
  'investments-isa': 'investments',
  // All others: null (show all tabs)
};
```

Passed to the component via `:visible-tabs="stepTabMap[currentStepId] || null"`

---

## Progress Tracking

**How it works (no change needed):**
- `LifeStageController::completeStep()` marks steps complete in DB
- `LifeStageController::progress()` returns `completed_steps` (explicit) + `data_completed_steps` (data readiness)
- Frontend `lifeStage.js` store merges both into `allCompletedSteps`
- Progress bar shows green ticks for completed steps

**Data readiness checks (per module):**
- Protection: `life_insurance_policies`, `critical_illness_policies`, or `income_protection_policies` exist
- Savings: `savings_accounts` exist
- Retirement: `dc_pensions`, `db_pensions`, or `state_pensions` exist
- Investment: `investment_accounts` exist
- Estate: `properties` or `investment_accounts` exist + `estate_wills` exist
- Goals: `goals` exist
- Tax: `annual_employment_income > 0` AND `employment_status` is set

**All Era 1 steps write to the same tables these checks look at. No gaps.**

---

## Completeness / Advice Gates

**PrerequisiteGateService checks:**
- `canAnalyseProtection()` → needs policies
- `canAnalyseSavings()` → needs savings accounts
- `canAnalyseRetirement()` → needs pensions
- `canAnalyseInvestment()` → needs investment accounts
- `canAnalyseEstate()` → needs properties or accounts
- `canAnalyseGoals()` → needs goals
- `canAnalyseTax()` → needs income + employment status

**All of these are satisfied by data entered through the Era 1 onboarding steps. No mismatch.**

---

## AI Context

AI context is built from completed module data AFTER onboarding, not during. The `AiContextBuilder` reads from the same DB tables the Era 1 steps write to. No onboarding-specific AI context needed.

---

## What Needs to Change

| File | Change | Effort |
|------|--------|--------|
| `OnboardingWizard.vue` | Update `STEP_COMPONENTS` mapping + add `STEP_TAB_MAP` + pass `visibleTabs` prop | Small |
| `AssetsStep.vue` | Add `visibleTabs` prop + filter `assetTabs` + set default `activeTab` | Small |
| `OnboardingWizard.vue` | Remove old `handleLifeStageStepSave` handlers for property/pension/investment (AssetsStep handles its own saves) | Small |
| `OnboardingWizard.vue` | Remove imports of deleted components | Small |

**Total: 2 files changed. Zero new files. Zero new forms.**

---

## Field-Level Completion Tracking

Currently `LifeStageService::getDataCompleteness()` tracks at STEP level (e.g. `$hasPersonalInfo = $user->date_of_birth && $user->gender`). This needs to track every individual input field.

**Approach:** No new tables needed. The `users` table already stores all personal/income/expenditure fields. We check each field directly — `null` or empty = incomplete, any value = complete. For related models (pensions, properties, etc.) we check record existence AND key fields on those records.

### PersonalInfoStep fields

| Field | DB Column | Check |
|-------|-----------|-------|
| `first_name` | `users.first_name` | `!empty($user->first_name)` |
| `surname` | `users.surname` | `!empty($user->surname)` |
| `date_of_birth` | `users.date_of_birth` | `!is_null($user->date_of_birth)` |
| `gender` | `users.gender` | `!empty($user->gender)` |
| `marital_status` | `users.marital_status` | `!empty($user->marital_status)` |
| `phone` | `users.phone` | `!empty($user->phone)` |
| `address_line_1` | `users.address_line_1` | `!empty($user->address_line_1)` |
| `city` | `users.city` | `!empty($user->city)` |
| `postcode` | `users.postcode` | `!empty($user->postcode)` |
| `health_status` | `users.good_health` | `!is_null($user->good_health)` |
| `smoking_status` | `users.smoker` | `!is_null($user->smoker)` |

### IncomeStep fields

| Field | DB Column | Check |
|-------|-----------|-------|
| `employment_status` | `users.employment_status` | `!empty($user->employment_status)` |
| `occupation` | `users.occupation` | `!empty($user->occupation)` |
| `employer` | `users.employer` | `!empty($user->employer)` |
| `industry` | `users.industry` | `!empty($user->industry)` |
| `target_retirement_age` | `users.target_retirement_age` | `!is_null($user->target_retirement_age)` |
| `annual_employment_income` | `users.annual_employment_income` | `$user->annual_employment_income > 0` |
| `annual_self_employment_income` | `users.annual_self_employment_income` | `$user->annual_self_employment_income > 0` |
| `annual_dividend_income` | `users.annual_dividend_income` | `$user->annual_dividend_income > 0` |
| `annual_interest_income` | `users.annual_interest_income` | `$user->annual_interest_income > 0` |
| `annual_other_income` | `users.annual_other_income` | `$user->annual_other_income > 0` |

### ExpenditureStep fields

| Field | DB Column | Check |
|-------|-----------|-------|
| `monthly_expenditure` | `users.monthly_expenditure` | `$user->monthly_expenditure > 0` |
| `food_groceries` | `users.food_groceries` | `$user->food_groceries > 0` |
| `transport_fuel` | `users.transport_fuel` | `$user->transport_fuel > 0` |
| `healthcare_medical` | `users.healthcare_medical` | `$user->healthcare_medical > 0` |
| `mobile_phones` | `users.mobile_phones` | `$user->mobile_phones > 0` |
| `internet_tv` | `users.internet_tv` | `$user->internet_tv > 0` |
| `clothing_personal_care` | `users.clothing_personal_care` | `$user->clothing_personal_care > 0` |
| `entertainment_dining` | `users.entertainment_dining` | `$user->entertainment_dining > 0` |
| `holidays_travel` | `users.holidays_travel` | `$user->holidays_travel > 0` |

### FamilyInfoStep fields

| Field | DB Column | Check |
|-------|-----------|-------|
| `has_spouse` | `family_members` where `relationship = 'spouse'` | `$user->familyMembers()->where('relationship', 'spouse')->exists()` |
| `has_children` | `family_members` where `relationship = 'child'` | `$user->familyMembers()->where('relationship', 'child')->exists()` |
| `charitable_bequest` | `users.charitable_bequest` | `!is_null($user->charitable_bequest)` |

### AssetsStep — Savings fields (per account)

| Field | DB Column | Check |
|-------|-----------|-------|
| `has_savings_account` | `savings_accounts` exists | `$user->savingsAccounts()->exists()` |
| `institution` | `savings_accounts.institution` | Per-record: `!empty($account->institution)` |
| `current_balance` | `savings_accounts.current_balance` | Per-record: `!is_null($account->current_balance)` |
| `interest_rate` | `savings_accounts.interest_rate` | Per-record: `!is_null($account->interest_rate)` |
| `account_type` | `savings_accounts.account_type` | Per-record: `!empty($account->account_type)` |

### AssetsStep — Property fields (per property)

| Field | DB Column | Check |
|-------|-----------|-------|
| `has_property` | `properties` exists | `$user->properties()->exists()` |
| `property_type` | `properties.property_type` | Per-record: `!empty($property->property_type)` |
| `address_line_1` | `properties.address_line_1` | Per-record: `!empty($property->address_line_1)` |
| `current_value` | `properties.current_value` | Per-record: `$property->current_value > 0` |
| `has_mortgage` | `mortgages` linked to property | Per-record: `$property->mortgages()->exists()` |
| `outstanding_balance` | `mortgages.outstanding_balance` | Per-record: `$mortgage->outstanding_balance > 0` |
| `interest_rate` | `mortgages.interest_rate` | Per-record: `!is_null($mortgage->interest_rate)` |
| `monthly_payment` | `mortgages.monthly_payment` | Per-record: `$mortgage->monthly_payment > 0` |

### AssetsStep — Pension fields (per pension)

| Field | DB Column | Check |
|-------|-----------|-------|
| `has_dc_pension` | `dc_pensions` exists | `$user->dcPensions()->exists()` |
| `pension_type` | `dc_pensions.pension_type` | Per-record: `!empty($pension->pension_type)` |
| `scheme_name` | `dc_pensions.scheme_name` | Per-record: `!empty($pension->scheme_name)` |
| `provider` | `dc_pensions.provider` | Per-record: `!empty($pension->provider)` |
| `current_value` | `dc_pensions.current_fund_value` | Per-record: `$pension->current_fund_value > 0` |
| `employee_contribution` | `dc_pensions.employee_contribution_percent` | Per-record: `$pension->employee_contribution_percent > 0` |
| `employer_contribution` | `dc_pensions.employer_contribution_percent` | Per-record: `$pension->employer_contribution_percent > 0` |
| `has_db_pension` | `db_pensions` exists | `$user->dbPensions()->exists()` |
| `has_state_pension` | `state_pensions` exists | `$user->statePension()->exists()` |

### AssetsStep — Investment fields (per account)

| Field | DB Column | Check |
|-------|-----------|-------|
| `has_investment_account` | `investment_accounts` exists | `$user->investmentAccounts()->exists()` |
| `account_type` | `investment_accounts.account_type` | Per-record: `!empty($account->account_type)` |
| `provider` | `investment_accounts.provider` | Per-record: `!empty($account->provider)` |
| `current_value` | `investment_accounts.current_value` | Per-record: `$account->current_value > 0` |

### ProtectionPoliciesStep fields (per policy)

| Field | DB Column | Check |
|-------|-----------|-------|
| `has_life_insurance` | `life_insurance_policies` exists | `$user->lifeInsurancePolicies()->exists()` |
| `has_critical_illness` | `critical_illness_policies` exists | `$user->criticalIllnessPolicies()->exists()` |
| `has_income_protection` | `income_protection_policies` exists | `$user->incomeProtectionPolicies()->exists()` |
| `provider` | `*.provider` | Per-record: `!empty($policy->provider)` |
| `sum_assured` | `*.sum_assured` / `*.coverage_amount` | Per-record: `> 0` |
| `premium_amount` | `*.premium_amount` | Per-record: `> 0` |

### WillInfoStep fields

| Field | DB Column | Check |
|-------|-----------|-------|
| `has_will` | `estate_wills.has_will` | `Will::where('user_id', $user->id)->exists()` |
| `will_last_updated` | `estate_wills.will_last_updated` | Per-record: `!is_null($will->will_last_updated)` |
| `executor_name` | `estate_wills.executor_name` | Per-record: `!empty($will->executor_name)` |

### StudentLoanStep fields

| Field | DB Column | Check |
|-------|-----------|-------|
| `plan_type` | `student_loans.plan_type` | `!empty($loan->plan_type)` |
| `outstanding_balance` | `student_loans.outstanding_balance` | `$loan->outstanding_balance > 0` |
| `interest_rate` | `student_loans.interest_rate` | `!is_null($loan->interest_rate)` |

### GoalSetupStep fields

| Field | DB Column | Check |
|-------|-----------|-------|
| `has_goal` | `goals` exists | `$user->goals()->exists()` |
| `goal_type` | `goals.goal_type` | Per-record: `!empty($goal->goal_type)` |
| `target_amount` | `goals.target_amount` | Per-record: `$goal->target_amount > 0` |
| `target_date` | `goals.target_date` | Per-record: `!is_null($goal->target_date)` |

---

### Implementation

Update `LifeStageService::getDataCompleteness()` to return a nested structure:

```php
return [
    'steps' => $completedSteps,           // Step-level (existing)
    'fields' => [                          // Field-level (new)
        'personal_info' => [
            'first_name' => !empty($user->first_name),
            'surname' => !empty($user->surname),
            'date_of_birth' => !is_null($user->date_of_birth),
            'gender' => !empty($user->gender),
            // ... all fields
        ],
        'income' => [
            'employment_status' => !empty($user->employment_status),
            'annual_employment_income' => $user->annual_employment_income > 0,
            // ... all fields
        ],
        // ... all steps
    ],
    'percentage' => $overallPercentage,    // Calculated from field completion
];
```

This feeds into:
- **Progress bar** — exact % based on filled fields, not just step completion
- **AI context** — knows exactly what data is available
- **Advice gates** — granular checks per field
- **Completeness UI** — shows which specific fields are missing
