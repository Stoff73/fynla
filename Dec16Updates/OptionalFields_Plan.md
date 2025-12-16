# Database Update Plan - Optional Fields & Registration Refactor

**Date**: December 16, 2025
**Status**: Planning
**Branch**: dbupdate

## Overview
Make all form fields optional (except registration), refactor registration to use separate name fields, and fix all division/math operations to handle NULL and zero values safely.

---

## Phase 1: Registration Refactor

### 1.1 Database Migration
**File**: `database/migrations/YYYY_MM_DD_refactor_users_name_fields.php`

```php
// Split single 'name' column into three columns
Schema::table('users', function (Blueprint $table) {
    $table->string('first_name')->after('id');
    $table->string('middle_name')->nullable()->after('first_name');
    $table->string('surname')->after('middle_name');
});

// Data migration: Parse existing 'name' into components
// Then drop 'name' column

Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('name');
});
```

### 1.2 Update User Model
**File**: `app/Models/User.php`

- Change fillable from `['name', ...]` to `['first_name', 'middle_name', 'surname', ...]`
- Add accessor for full name: `getNameAttribute()` returns `"{first_name} {middle_name} {surname}"`

### 1.3 Update RegisterRequest
**File**: `app/Http/Requests/RegisterRequest.php`

```php
public function rules(): array
{
    return [
        'first_name' => ['required', 'string', 'max:255'],
        'middle_name' => ['nullable', 'string', 'max:255'],
        'surname' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ];
}
```

### 1.4 Update AuthController
**File**: `app/Http/Controllers/Api/AuthController.php`

```php
$user = User::create([
    'first_name' => $request->first_name,
    'middle_name' => $request->middle_name,
    'surname' => $request->surname,
    'email' => $request->email,
    'password' => Hash::make($request->password),
]);
```

### 1.5 Update Register.vue
**File**: `resources/js/views/Register.vue`

- Replace single "Full Name" input with three fields:
  - First Name (required)
  - Middle Name (optional)
  - Surname (required)
- Update form data and validation

---

## Phase 2: Make Form Fields Optional (Backend)

### 2.1 Laravel Form Requests to Update

Change `'required'` to `'nullable'` for all non-registration fields:

| File | Fields to Make Nullable |
|------|------------------------|
| `app/Http/Requests/Protection/StoreLifePolicyRequest.php` | All except user_id |
| `app/Http/Requests/Protection/UpdateLifePolicyRequest.php` | All fields |
| `app/Http/Requests/Protection/StoreDisabilityPolicyRequest.php` | All except user_id |
| `app/Http/Requests/Protection/UpdateDisabilityPolicyRequest.php` | All fields |
| `app/Http/Requests/Protection/StoreSicknessIllnessPolicyRequest.php` | All except user_id |
| `app/Http/Requests/Protection/UpdateSicknessIllnessPolicyRequest.php` | All fields |
| `app/Http/Requests/Savings/StoreSavingsAccountRequest.php` | All except user_id |
| `app/Http/Requests/Savings/UpdateSavingsAccountRequest.php` | All fields |
| `app/Http/Requests/Savings/StoreSavingsGoalRequest.php` | All except user_id |
| `app/Http/Requests/Savings/UpdateSavingsGoalRequest.php` | All fields |
| `app/Http/Requests/Retirement/StoreDCPensionRequest.php` | All except user_id |
| `app/Http/Requests/Retirement/UpdateDCPensionRequest.php` | All fields |
| `app/Http/Requests/Retirement/StoreDBPensionRequest.php` | All except user_id |
| `app/Http/Requests/Retirement/UpdateDBPensionRequest.php` | All fields |
| `app/Http/Requests/StorePropertyRequest.php` | All except user_id |
| `app/Http/Requests/UpdatePropertyRequest.php` | All fields |
| `app/Http/Requests/StoreMortgageRequest.php` | All except property_id |
| `app/Http/Requests/UpdateMortgageRequest.php` | All fields |
| `app/Http/Requests/StoreFamilyMemberRequest.php` | All except user_id, relationship |
| `app/Http/Requests/UpdateFamilyMemberRequest.php` | All fields |
| `app/Http/Requests/StoreInvestmentAccountRequest.php` | All except user_id |
| `app/Http/Requests/UpdateInvestmentAccountRequest.php` | All fields |

---

## Phase 3: Make Form Fields Optional (Frontend)

### 3.1 Vue Components to Update

Remove `required` HTML5 attributes from all input fields:

| File | Action |
|------|--------|
| `resources/js/components/Protection/PolicyFormModal.vue` | Remove all `required` attributes |
| `resources/js/components/Savings/SaveAccountModal.vue` | Remove all `required` attributes |
| `resources/js/components/Savings/SavingsGoalForm.vue` | Remove all `required` attributes |
| `resources/js/components/Retirement/DCPensionForm.vue` | Remove all `required` attributes |
| `resources/js/components/Retirement/DBPensionForm.vue` | Remove all `required` attributes |
| `resources/js/components/Retirement/StatePensionForm.vue` | Remove all `required` attributes |
| `resources/js/components/Investment/AccountForm.vue` | Remove all `required` attributes |
| `resources/js/components/Investment/HoldingForm.vue` | Remove all `required` attributes |
| `resources/js/components/NetWorth/Property/PropertyForm.vue` | Remove all `required` attributes |
| `resources/js/components/Estate/LiabilityForm.vue` | Remove all `required` attributes |
| `resources/js/components/Estate/GiftForm.vue` | Remove all `required` attributes |
| `resources/js/components/Estate/TrustForm.vue` | Remove all `required` attributes |
| `resources/js/components/Onboarding/steps/*.vue` | Remove all `required` attributes |

---

## Phase 4: Fix Division and Math Operations

### 4.1 Pattern to Apply

```php
// BEFORE - vulnerable to division by zero
$result = $numerator / $denominator;

// AFTER - safe pattern
$result = $denominator != 0 ? ($numerator / $denominator) : 0;

// For nullable values
$result = ($denominator ?? 0) != 0 ? (($numerator ?? 0) / $denominator) : 0;
```

### 4.2 Files with Vulnerable Division Operations

| File | Line(s) | Fix Required |
|------|---------|--------------|
| `app/Services/Savings/EmergencyFundCalculator.php` | 28 | `$monthlyExpenses != 0 ? ...` |
| `app/Services/Investment/FeeAnalyzer.php` | 77, 97 | Add null/zero checks |
| `app/Services/Onboarding/OnboardingService.php` | 722, 729 | Add null/zero checks |
| `app/Services/Plans/InvestmentSavingsPlanService.php` | 569, 588 | Add null/zero checks |
| `app/Services/Coordination/CashFlowCoordinator.php` | 105 | Add null/zero check |
| `app/Services/Coordination/ConflictResolver.php` | 214-215, 406 | Add null/zero checks |
| `app/Services/Estate/FutureValueCalculator.php` | 371 | Add null/zero check |
| `app/Services/Estate/LifePolicyStrategyService.php` | 480 | Add null/zero check |
| `app/Services/Retirement/PensionProjectionService.php` | Various | Review and fix |
| `app/Services/Protection/AdequacyScorer.php` | Various | Review and fix |
| `app/Services/Investment/PortfolioAnalyzer.php` | Various | Review and fix |
| `app/Agents/InvestmentAgent.php` | Various | Review and fix |

### 4.3 Helper Function (Optional)

Consider adding a helper for safe division:

```php
// app/Helpers/MathHelpers.php
function safeDivide($numerator, $denominator, $default = 0): float
{
    $num = $numerator ?? 0;
    $den = $denominator ?? 0;
    return $den != 0 ? ($num / $den) : $default;
}
```

---

## Phase 5: Database Migration for Nullable Columns

### 5.1 Create Migration for Existing Tables

Review and update columns that should accept NULL:

```php
// Example for protection policies
Schema::table('life_insurance_policies', function (Blueprint $table) {
    $table->decimal('sum_assured', 15, 2)->nullable()->change();
    $table->decimal('monthly_premium', 10, 2)->nullable()->change();
    // ... other columns
});
```

Tables to review:
- `life_insurance_policies`
- `critical_illness_policies`
- `income_protection_policies`
- `disability_policies`
- `sickness_illness_policies`
- `savings_accounts`
- `savings_goals`
- `dc_pensions`
- `db_pensions`
- `state_pensions`
- `investment_accounts`
- `investment_holdings`
- `properties`
- `mortgages`
- `liabilities`

---

## Implementation Order

1. **Phase 1**: Registration refactor (database + backend + frontend)
2. **Phase 5**: Database column nullable migration (must run before Phase 2)
3. **Phase 2**: Backend Form Request updates
4. **Phase 3**: Frontend required attribute removal
5. **Phase 4**: Division/math operation fixes

---

## Progress Tracking

- [ ] Phase 1: Registration Refactor
  - [ ] Database migration for name fields
  - [ ] Update User model
  - [ ] Update RegisterRequest
  - [ ] Update AuthController
  - [ ] Update Register.vue
- [ ] Phase 5: Database Nullable Columns Migration
  - [ ] Create migration for all tables
  - [ ] Run migration
- [ ] Phase 2: Backend Form Requests
  - [ ] Protection module requests
  - [ ] Savings module requests
  - [ ] Retirement module requests
  - [ ] Property/Mortgage requests
  - [ ] Family member requests
  - [ ] Investment requests
- [ ] Phase 3: Frontend Components
  - [ ] Protection forms
  - [ ] Savings forms
  - [ ] Retirement forms
  - [ ] Investment forms
  - [ ] Property/Estate forms
  - [ ] Onboarding forms
- [ ] Phase 4: Math Operations
  - [ ] Emergency fund calculator
  - [ ] Fee analyzer
  - [ ] Onboarding service
  - [ ] Investment/Savings plan service
  - [ ] Cash flow coordinator
  - [ ] Conflict resolver
  - [ ] Future value calculator
  - [ ] Life policy strategy service
  - [ ] Other services review

---

## Testing Checklist

- [ ] Registration with all fields
- [ ] Registration with only required fields
- [ ] Create entities with minimal data (only user_id)
- [ ] Update entities with partial data
- [ ] Verify calculations don't error with NULL values
- [ ] Verify calculations don't error with zero values
- [ ] Check all dashboards load with empty/partial data
- [ ] Run existing test suite
