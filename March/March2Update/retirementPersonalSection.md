# Retirement Plan — Personal Information Section

## Context

The retirement plan executive summary currently jumps straight from the greeting into goals and actions. The user wants a "Personal Information" section added after the executive summary that summarises the user's key personal and financial data, formatted identically to the existing User Profile view (label-value rows in a two-column grid, not cards).

## Backend — `RetirementPlanService.php`

Add `buildPersonalInformation(User $user)` method. Fetches and returns:

```
personal_information:
  full_name        — $user->first_name . ' ' . $user->surname
  date_of_birth    — $user->date_of_birth (Carbon date)
  age              — calculated from date_of_birth
  marital_status   — $user->marital_status
  spouse_name      — $user->spouse->first_name . ' ' . $user->spouse->surname (if married + spouse exists)
  children         — array of {name} from $user->familyMembers()->where('relationship', 'child')
  gross_income     — sum of all annual_*_income fields on User
  net_income       — from DisposableIncomeAccessor (already injected)
  annual_expenditure — from DisposableIncomeAccessor
  disposable_income — from DisposableIncomeAccessor (annual)
  monthly_disposable — from DisposableIncomeAccessor (monthly)
  risk_level       — from Investment\RiskProfile::where('user_id', $userId)->first()->risk_level
```

Add `'personal_information' => $this->buildPersonalInformation($user)` to the `generatePlan()` return array (and empty state return).

## Frontend — New Component

**File:** `resources/js/components/Plans/Retirement/RetirementPersonalInformation.vue`

Uses the exact same CSS pattern as `resources/js/components/UserProfile/PersonalInformation.vue`:

- Container: `bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6`
- Section title: `text-h4 font-semibold text-gray-900` + subtitle `text-body-sm text-gray-600`
- Two-column grid: `grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-8`
- Sub-section header: `text-body-base font-semibold text-gray-900 mb-4`
- Rows: `flex justify-between` inside `space-y-3`
- Label: `text-body-sm text-gray-600`
- Value: `text-body-sm text-gray-900 text-right`
- Empty values: `'—'` em dash

**Layout (two grid rows):**

Row 1:
- **Personal Details** — Full Name, Date of Birth, Age, Marital Status
- **Family** — Spouse, Children (comma-separated list or "None")

Row 2:
- **Financial Overview** — Gross Income, Net Income, Annual Expenditure, Disposable Income (annual), Disposable Income (monthly)
- **Risk Profile** — Risk Level (capitalised)

Uses `currencyMixin` for income/expenditure formatting. Date formatted as `DD Month YYYY`.

## Frontend — Wire into RetirementPlanContent.vue

Add `<RetirementPersonalInformation>` between `RetirementExecutiveSummary` and `PlanGoalSection`. Pass `plan.personal_information` as prop.

## Files to Create (1)

| File | Purpose |
|------|---------|
| `resources/js/components/Plans/Retirement/RetirementPersonalInformation.vue` | Personal info section |

## Files to Modify (2)

| File | Change |
|------|--------|
| `app/Services/Plans/RetirementPlanService.php` | Add `buildPersonalInformation()`, add to both return arrays |
| `resources/js/components/Plans/Retirement/RetirementPlanContent.vue` | Import + render new component |

## Verification

1. `php artisan db:seed`
2. Login as peak_earners (David Mitchell) — personal info shows: married, spouse Sarah, children, income/expenditure, risk level
3. Login as young_saver (John Morgan) — single, no spouse, no children, shows "—" for empty fields
4. Login as widow (Margaret Thompson) — widowed status, no spouse
5. Existing tests still pass: `./vendor/bin/pest tests/Unit/Services/Plans/`
