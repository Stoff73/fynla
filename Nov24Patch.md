# Fynla v0.2.13 Patch - 24 November 2025

**Date**: 24 November 2025
**Branch**: main
**Version**: v0.2.13
**Type**: Mixed Deployment (3 Database Migrations + Code Updates)
**Production URL**: https://csjones.co/tengo
**Application Root**: ~/tengo-app/

---

## ⚠️ CRITICAL WARNING FOR ALL CLAUDE INSTANCES ⚠️

**NEVER INCLUDE `public/build/` IN TAR FILE CREATION WITHOUT EXPLICIT USER PERMISSION**

When creating deployment tarballs:
- ❌ **DO NOT** automatically include `public/build/` directory
- ❌ **DO NOT** assume frontend assets should be in deployment packages
- ✅ **ALWAYS ASK** the user before including compiled frontend assets
- ✅ **ONLY INCLUDE** source files (Vue components, PHP files, migrations) unless explicitly told otherwise

**Why**: Including `public/build/` causes:
1. Wrong asset paths if Vite base configuration changed
2. Cache issues with old compiled assets
3. Deployment confusion between source code fixes and compiled output
4. Massive tarball sizes (build/ can be 10-50MB+)

**Correct deployment workflow**:
1. Upload source files ONLY (Vue components, PHP controllers, etc.)
2. **ALWAYS build on LOCAL machine**: `NODE_ENV=production npm run build`
3. Upload the `public/build/` directory separately IF needed
4. **NEVER** instruct user to run `npm run build` on production server

**Why build locally, not on server**:
- Production servers often lack Node.js or have wrong versions
- Build process is resource-intensive and can crash shared hosting
- Local builds use correct environment configuration
- Faster deployment without waiting for server builds

**This deployment failed initially because**: Claude created `build-assets-fixed.tar.gz` with ONLY `public/build/` instead of the actual Vue component fixes from the patch, causing hours of debugging.

---

## Patch Overview

This patch addresses critical bugs in:
1. DC pension creation (scheme_type NULL constraint, stakeholder mapping)
2. DB pension creation (field name mismatches)
3. Pension display in onboarding (missing data, no pensions showing)
4. Protection policy creation (optional dates, missing database columns, validation)
5. Financial commitments missing Disability and Sickness/Illness premiums
6. Property form mortgage lender name missing required indicator

**Total Sections**: 12
**Total Files Modified**: 20
- Backend: 12 files (controllers, validation requests, services, 4 database migrations)
- Frontend: 8 files (Vue components)

**Deployment Method**: File upload + 4 database migrations + frontend rebuild + cache clear
**Estimated Time**: 20-25 minutes
**Downtime**: Minimal (< 2 minutes during migrations)

---

## Section 1: DC Pension scheme_type NULL Constraint Fix (CRITICAL)

**Issue**: Users received 500 Internal Server Error when trying to save DC pensions during onboarding.

**Root Cause**:
- Database column `scheme_type` had NOT NULL constraint: `enum('workplace','sipp','personal') NOT NULL`
- Backend validation allowed `scheme_type` to be nullable: `'scheme_type' => ['nullable', ...]`
- When form submitted with empty or null `scheme_type`, validation passed but database rejected with NULL constraint violation

**Console Error**:
```
POST http://localhost:8000/api/retirement/pensions/dc 500 (Internal Server Error)
SQLSTATE[23000]: Integrity constraint violation: ... Column 'scheme_type' cannot be null
```

### Changes Made

#### File: `database/migrations/2025_11_24_144502_make_scheme_type_nullable_on_dc_pensions_table.php` (NEW)

```php
public function up(): void
{
    Schema::table('dc_pensions', function (Blueprint $table) {
        $table->enum('scheme_type', ['workplace', 'sipp', 'personal'])->nullable()->change();
    });
}
```

**Impact**:
- ✅ DC pensions can now be created even if `scheme_type` is not set
- ✅ Aligns database constraint with validation rules
- ✅ Prevents 500 errors during pension creation

---

## Section 2: Stakeholder Pension Mapping Fix

**Issue**: Stakeholder pensions created successfully but `handlePensionTypeChange` method had incorrect mapping logic.

**Root Cause**: Original mapping set `scheme_type = pension_type` directly, which would set `scheme_type = 'stakeholder'` but database only accepts `'workplace'`, `'sipp'`, `'personal'`.

### Changes Made

#### File: `resources/js/components/Retirement/DCPensionForm.vue`

**Location**: Lines 398-407 (handlePensionTypeChange method)

**Before**:
```javascript
if (this.formData.pension_type === 'occupational') {
  this.formData.scheme_type = 'workplace';
} else {
  this.formData.scheme_type = this.formData.pension_type; // ❌ BROKEN for stakeholder
}
```

**After**:
```javascript
if (this.formData.pension_type === 'occupational') {
  this.formData.scheme_type = 'workplace';
} else if (this.formData.pension_type === 'stakeholder') {
  this.formData.scheme_type = 'personal'; // ✅ FIXED - stakeholder maps to personal
} else {
  this.formData.scheme_type = this.formData.pension_type; // sipp, personal map directly
}
```

**Impact**:
- ✅ Stakeholder pensions correctly map to 'personal' scheme_type
- ✅ Maintains UK pension taxonomy (stakeholder is a type of personal pension)

---

## Section 3: Onboarding Pension Display Fix (CRITICAL UX BUG)

**Issue**: During onboarding Assets & Wealth step, no DC pensions were showing even though they existed in the database.

**Root Cause**:
- `retirementService.getRetirementData()` returns `response.data` which contains: `{ success, message, data: { dc_pensions, db_pensions, state_pension } }`
- Frontend code tried to access `response.data.dc_pensions` which is actually `response.data.data.dc_pensions`
- This resulted in empty arrays, so no pensions displayed

**Console Log**:
```
Retirement API response: {success: true, message: '...', data: {dc_pensions: [...], ...}}
Loaded pensions: {dc: [], db: [], state: null}  // ❌ Empty despite data existing
```

### Changes Made

#### File: `resources/js/components/Onboarding/steps/AssetsStep.vue`

**Location**: Lines 511-524 (loadPensions function)

**Before**:
```javascript
async function loadPensions() {
  try {
    const response = await retirementService.getRetirementData();
    pensions.value = {
      dc: response.data?.dc_pensions || [],  // ❌ WRONG - double nested
      db: response.data?.db_pensions || [],
      state: response.data?.state_pension || null,
    };
  } catch (err) {
    console.error('Failed to load pensions', err);
  }
}
```

**After**:
```javascript
async function loadPensions() {
  try {
    const response = await retirementService.getRetirementData();
    // retirementService returns response.data which has structure: { success, message, data: { dc_pensions, db_pensions, state_pension } }
    const retirementData = response.data || response;
    pensions.value = {
      dc: retirementData.dc_pensions || [],  // ✅ FIXED - correct path
      db: retirementData.db_pensions || [],
      state: retirementData.state_pension || null,
    };
  } catch (err) {
    console.error('Failed to load pensions', err);
  }
}
```

**Impact**:
- ✅ DC pensions now display correctly in onboarding
- ✅ DB pensions now display correctly in onboarding
- ✅ State pension displays correctly

---

## Section 4: DB Pension Field Name Mapping Fix (CRITICAL)

**Issue**: Users received 422 validation errors when trying to save DB pensions during onboarding.

**Root Cause**: Frontend form sends different field names than API expects:
- Form sends: `employer_name`, `annual_income`, `service_years`
- API expects: `scheme_name`, `accrued_annual_pension`, `pensionable_service_years`

**Console Error**:
```
POST http://localhost:8000/api/retirement/pensions/db 422 (Unprocessable Content)
[API] 422 Validation: The given data was invalid.
errors: {scheme_name: ['required'], accrued_annual_pension: ['required']}
```

### Changes Made

#### File: `resources/js/components/Retirement/DBPensionForm.vue`

**Location**: Lines 320-335 (handleSubmit method)

**Before**:
```javascript
this.$emit('save', this.formData);  // ❌ Sends wrong field names
```

**After**:
```javascript
// Map form fields to API field names
const apiData = {
  scheme_name: this.formData.employer_name,
  scheme_type: this.formData.scheme_type,
  accrued_annual_pension: this.formData.annual_income,
  pensionable_service_years: this.formData.service_years,
  pensionable_salary: this.formData.final_salary,
  normal_retirement_age: this.formData.normal_retirement_age,
  revaluation_method: this.formData.revaluation_rate ? `${this.formData.revaluation_rate}%` : null,
  lump_sum_entitlement: this.formData.pcls_available,
};

this.$emit('save', apiData);  // ✅ Sends correct field names
```

**Impact**:
- ✅ DB pensions can now be created during onboarding
- ✅ Field names match API expectations
- ✅ Validation passes successfully

---

## Section 5: Protection Policy Dates - Database Schema Fix (CRITICAL)

**Issue**: All protection policy types failed with 500 or 422 errors when saving without dates.

**Root Causes**:
1. Database columns `policy_start_date`, `policy_end_date`, and `policy_term_years` were NOT NULL
2. Validation allowed these fields to be nullable
3. Frontend sent null values, validation passed, database rejected → 500 error
4. Whole of Life insurance policies don't have end dates (coverage continues until death)

### Changes Made

#### File: `database/migrations/2025_11_24_124735_make_policy_end_date_nullable_on_life_insurance_policies_table.php` (NEW)

```php
public function up(): void
{
    Schema::table('life_insurance_policies', function (Blueprint $table) {
        // Make policy_end_date nullable
        // Whole of life policies don't have an end date - coverage continues until death
        $table->date('policy_end_date')->nullable()->change();
    });
}
```

#### File: `database/migrations/2025_11_24_151629_make_protection_policy_dates_nullable.php` (NEW)

```php
public function up(): void
{
    // Critical Illness Policies
    Schema::table('critical_illness_policies', function (Blueprint $table) {
        $table->date('policy_start_date')->nullable()->change();
        $table->integer('policy_term_years')->nullable()->change();
    });

    // Income Protection Policies
    Schema::table('income_protection_policies', function (Blueprint $table) {
        $table->date('policy_start_date')->nullable()->change();
        $table->integer('deferred_period_weeks')->nullable()->change();
    });

    // Disability Policies
    Schema::table('disability_policies', function (Blueprint $table) {
        $table->date('policy_start_date')->nullable()->change();
        $table->integer('deferred_period_weeks')->nullable()->change();
    });

    // Sickness/Illness Policies
    Schema::table('sickness_illness_policies', function (Blueprint $table) {
        $table->date('policy_start_date')->nullable()->change();
    });
}
```

**Impact**:
- ✅ Life insurance policies (including Whole of Life) can be saved without end dates
- ✅ All other protection policies can be saved without start dates or term years
- ✅ Database constraints align with business logic
- ✅ Prevents 500 errors

---

## Section 6: Protection Policy Dates - Backend Validation Fix

**Issue**: After making dates nullable in database, policies still failed validation because backend required dates.

**Root Cause**:
- `ProtectionController.php` had `'policy_start_date' => 'required|...'` for all policy types
- Form Request classes also had `'policy_start_date' => ['required', ...]`
- Frontend sends nullable dates but backend rejected them

### Changes Made

#### File: `app/Http/Controllers/Api/ProtectionController.php`

**Location 1**: Line 326 (storeCriticalIllnessPolicy)
- Changed `'policy_start_date' => 'required|...'` to `'policy_start_date' => 'nullable|...'`
- Changed `'policy_term_years' => 'required|...'` to `'policy_term_years' => 'nullable|...'`

**Location 2**: Lines 450-452 (storeIncomeProtectionPolicy)
- Changed `'policy_start_date' => 'required|...'` to `'policy_start_date' => 'nullable|...'`
- Changed `'deferred_period_weeks' => 'required|...'` to `'deferred_period_weeks' => 'nullable|...'`
- Added `'policy_term_years' => 'nullable|...'`

#### File: `app/Http/Requests/Protection/StoreDisabilityPolicyRequest.php`

**Location**: Lines 32, 37
- Changed `'deferred_period_weeks' => ['required', ...]` to `['nullable', ...]`
- Changed `'policy_start_date' => ['required', ...]` to `['nullable', ...]`

#### File: `app/Http/Requests/Protection/StoreSicknessIllnessPolicyRequest.php`

**Location**: Line 36
- Changed `'policy_start_date' => ['required', ...]` to `['nullable', ...]`

**Impact**:
- ✅ Critical Illness policies save without dates
- ✅ Income Protection policies save without dates
- ✅ Disability policies save without dates
- ✅ Sickness/Illness policies save without dates

---

## Section 7: Protection Policy Frontend - Remove Required Dates

**Issue**: Frontend form still made dates required even after backend accepted nullable.

### Changes Made

#### File: `resources/js/components/Protection/PolicyFormModal.vue`

**Location 1**: Lines 790-792 (criticalIllness data preparation)

**Before**:
```javascript
data.policy_start_date = this.formData.start_date || null;  // ❌ Forces null
data.policy_term_years = this.formData.term_years || null;
```

**After**:
```javascript
data.policy_start_date = this.formData.start_date;  // ✅ Sends actual value or empty
data.policy_end_date = this.formData.end_date || null;
data.policy_term_years = this.formData.term_years;
```

**Location 2**: Lines 798-800 (other policy types data preparation)

**Before**:
```javascript
data.policy_start_date = this.formData.start_date || null;
data.policy_end_date = this.formData.end_date;
```

**After**:
```javascript
data.policy_start_date = this.formData.start_date;  // ✅ Sends actual value
data.policy_end_date = this.formData.end_date || null;
data.policy_term_years = this.formData.term_years || null;
```

**Impact**:
- ✅ Form sends actual date values when entered
- ✅ Form sends empty/undefined when not entered (instead of forcing null)
- ✅ Backend handles both cases correctly

---

## Section 8: Protection Policy Display - Benefit Amount Fix

**Issue**: Income Protection, Disability, and Sickness/Illness policy cards showed blank for coverage amount.

**Root Cause**: Onboarding step only checked for `coverage_amount` and `sum_assured` fields, but these policy types use `benefit_amount`.

### Changes Made

#### File: `resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue`

**Location 1**: Lines 67-69 (coverage display)

**Before**:
```vue
<div>
  <p class="text-body-sm text-gray-500">Sum Assured</p>
  <p class="text-body font-medium text-gray-900">£{{ policy.coverage_amount?.toLocaleString() || policy.sum_assured?.toLocaleString() }}</p>
</div>
```

**After**:
```vue
<div>
  <p class="text-body-sm text-gray-500">{{ getCoverageLabel(policy.policyType || policy.policy_type) }}</p>
  <p class="text-body font-medium text-gray-900">£{{ (policy.coverage_amount || policy.sum_assured || policy.benefit_amount || 0).toLocaleString() }}</p>
</div>
```

**Location 2**: Lines 182-187 (added getCoverageLabel function)

```javascript
const getCoverageLabel = (type) => {
  if (type === 'life' || type === 'criticalIllness') {
    return 'Sum Assured';
  }
  return 'Benefit Amount';
};
```

**Location 3**: Line 399 (return statement)
- Added `getCoverageLabel` to returned properties

**Impact**:
- ✅ Income Protection shows "Benefit Amount: £45,000"
- ✅ Disability shows "Benefit Amount: £X"
- ✅ Sickness/Illness shows "Benefit Amount: £X"
- ✅ Life and Critical Illness still show "Sum Assured"

---

## Section 9: Pension Card Type Display Enhancement

**Issue**: Pension cards showed generic "Defined Contribution" label instead of specific pension type.

### Changes Made

#### File: `resources/js/components/Retirement/PensionCard.vue`

**Location**: Lines 168-191 (typeLabel computed property)

**Before**:
```javascript
typeLabel() {
  return this.type === 'dc' ? 'Defined Contribution' : 'Defined Benefit';
},
```

**After**:
```javascript
typeLabel() {
  if (this.type === 'db') {
    return 'Defined Benefit';
  }

  // For DC pensions, show the specific pension type
  if (this.pension.pension_type) {
    const typeMap = {
      'occupational': 'Occupational',
      'sipp': 'SIPP',
      'personal': 'Personal',
      'stakeholder': 'Stakeholder'
    };
    return typeMap[this.pension.pension_type] || 'Defined Contribution';
  }

  // Fallback to scheme_type if pension_type not available
  const schemeTypeMap = {
    'workplace': 'Occupational',
    'sipp': 'SIPP',
    'personal': 'Personal'
  };
  return schemeTypeMap[this.pension.scheme_type] || 'Defined Contribution';
},
```

**Impact**:
- ✅ Cards show "Occupational", "SIPP", "Personal", or "Stakeholder"
- ✅ Clear identification of pension type at a glance
- ✅ Consistent with onboarding display

---

## Section 10: Onboarding Pension Card Component Unification

**Issue**: Onboarding used custom HTML for pension cards instead of unified PensionCard component.

### Changes Made

#### File: `resources/js/components/Onboarding/steps/AssetsStep.vue`

**Location 1**: Import and registration (lines 434, 454)

**Added**:
```javascript
import PensionCard from '@/components/Retirement/PensionCard.vue';

components: {
  // ...
  PensionCard,
},
```

**Location 2**: Lines 64-73 (DC Pension display)

**Replaced ~60 lines of custom HTML with**:
```vue
<div class="grid grid-cols-1 gap-4">
  <PensionCard
    v-for="pension in pensions.dc"
    :key="pension.id"
    :pension="pension"
    type="dc"
    @edit="openPensionForm('dc', pension)"
    @delete="deletePension('dc', pension.id)"
  />
</div>
```

**Location 3**: Lines 82-89 (DB Pension display)

**Same pattern for DB pensions**

**Impact**:
- ✅ Consistent pension display throughout app
- ✅ Single source of truth for pension cards
- ✅ Reduced code duplication (~80 lines removed)
- ✅ Future improvements automatically apply to onboarding

---

## Section 11: Financial Commitments - Missing Disability and Sickness/Illness Premiums (CRITICAL)

**Issue**: Disability and Sickness/Illness policy premiums were not appearing in the Expenditure form's financial commitments section.

**Root Cause**: The `getFinancialCommitments` method in `UserProfileService` only fetched Life Insurance, Critical Illness, and Income Protection premiums. Disability and Sickness/Illness policies were completely missing from the financial commitments API response.

**Console/User Report**: "I do not see the premiums for disability and sickness, every thing else looks good."

**Database Verification**:
```sql
-- User had policies with premiums
SELECT id, user_id, provider, premium_amount, premium_frequency FROM disability_policies WHERE user_id = 5;
-- Result: id=1, premium_amount=250.00, premium_frequency=monthly

SELECT id, user_id, provider, premium_amount, premium_frequency FROM sickness_illness_policies WHERE user_id = 5;
-- Result: id=1, premium_amount=200.00, premium_frequency=monthly
```

### Changes Made

#### File: `app/Services/UserProfile/UserProfileService.php`

**Location 1**: Lines 566-572 (Fixed Income Protection frequency calculation)

**Before**:
```php
$incomeProtectionPolicies = \App\Models\IncomeProtectionPolicy::where('user_id', $user->id)->get();
foreach ($incomeProtectionPolicies as $policy) {
    // Income Protection premiums are stored as premium_amount (assumed monthly)
    if ($policy->premium_amount > 0) {
        $commitments['protection'][] = [
            // ...
            'monthly_amount' => $policy->premium_amount,  // ❌ Doesn't handle quarterly/annually
        ];
    }
}
```

**After**:
```php
$incomeProtectionPolicies = \App\Models\IncomeProtectionPolicy::where('user_id', $user->id)->get();
foreach ($incomeProtectionPolicies as $policy) {
    // Calculate monthly premium based on frequency
    $monthlyPremium = $policy->premium_amount;
    if ($policy->premium_frequency === 'quarterly') {
        $monthlyPremium = $policy->premium_amount / 3;
    } elseif ($policy->premium_frequency === 'annually') {
        $monthlyPremium = $policy->premium_amount / 12;
    }

    if ($monthlyPremium > 0) {
        $commitments['protection'][] = [
            // ...
            'monthly_amount' => $monthlyPremium,  // ✅ Correctly converts to monthly
        ];
    }
}
```

**Location 2**: Lines 586-607 (Added Disability policies)

**Added**:
```php
// Disability
$disabilityPolicies = \App\Models\DisabilityPolicy::where('user_id', $user->id)->get();
foreach ($disabilityPolicies as $policy) {
    // Calculate monthly premium based on frequency
    $monthlyPremium = $policy->premium_amount;
    if ($policy->premium_frequency === 'quarterly') {
        $monthlyPremium = $policy->premium_amount / 3;
    } elseif ($policy->premium_frequency === 'annually') {
        $monthlyPremium = $policy->premium_amount / 12;
    }

    if ($monthlyPremium > 0) {
        $commitments['protection'][] = [
            'id' => $policy->id,
            'name' => $policy->policy_name ?? 'Disability',
            'type' => 'disability',
            'monthly_amount' => $monthlyPremium,
            'is_joint' => false,
            'ownership_type' => 'individual',
        ];
    }
}
```

**Location 3**: Lines 609-630 (Added Sickness/Illness policies)

**Added**:
```php
// Sickness/Illness
$sicknessIllnessPolicies = \App\Models\SicknessIllnessPolicy::where('user_id', $user->id)->get();
foreach ($sicknessIllnessPolicies as $policy) {
    // Calculate monthly premium based on frequency
    $monthlyPremium = $policy->premium_amount;
    if ($policy->premium_frequency === 'quarterly') {
        $monthlyPremium = $policy->premium_amount / 3;
    } elseif ($policy->premium_frequency === 'annually') {
        $monthlyPremium = $policy->premium_amount / 12;
    }

    if ($monthlyPremium > 0) {
        $commitments['protection'][] = [
            'id' => $policy->id,
            'name' => $policy->policy_name ?? 'Sickness/Illness',
            'type' => 'sickness_illness',
            'monthly_amount' => $monthlyPremium,
            'is_joint' => false,
            'ownership_type' => 'individual',
        ];
    }
}
```

**Impact**:
- ✅ Disability policy premiums now appear in financial commitments (£250/month for user)
- ✅ Sickness/Illness policy premiums now appear in financial commitments (£200/month for user)
- ✅ Income Protection premiums correctly converted to monthly (quarterly/annually supported)
- ✅ Complete protection coverage tracking in expenditure form
- ✅ Accurate monthly expenditure totals

---

## Section 12: Property Form - Mortgage Lender Name Required Indicator

**Issue**: The mortgage lender name field in the Property Form did not have a red asterisk (*) to indicate it's a required field.

**Root Cause**: The label for "Lender Name" in the PropertyForm component was missing the visual required indicator, which is inconsistent with other required fields throughout the application.

**User Request**: "please add a red asterix(denoting required information) to the Lender name label of the mortgage details when adding a property."

### Changes Made

#### File: `resources/js/components/NetWorth/Property/PropertyForm.vue`

**Location**: Lines 596-598

**Before**:
```vue
<label for="lender_name" class="block text-sm font-medium text-gray-700 mb-1">Lender Name</label>
```

**After**:
```vue
<label for="lender_name" class="block text-sm font-medium text-gray-700 mb-1">
  Lender Name <span class="text-red-500">*</span>
</label>
```

**Impact**:
- ✅ Visual consistency with other required fields
- ✅ Clear indication that lender name is mandatory
- ✅ Improved user experience during property/mortgage entry

---

## Complete Files Changed

### Backend (12 files)

1. **`database/migrations/2025_11_24_124735_make_policy_end_date_nullable_on_life_insurance_policies_table.php`** (NEW)
   - Makes `policy_end_date` nullable on `life_insurance_policies` table for Whole of Life policies

2. **`database/migrations/2025_11_24_144502_make_scheme_type_nullable_on_dc_pensions_table.php`** (NEW)
   - Makes `scheme_type` nullable on `dc_pensions` table

3. **`database/migrations/2025_11_24_151629_make_protection_policy_dates_nullable.php`** (NEW)
   - Makes `policy_start_date`, `policy_term_years`, `deferred_period_weeks` nullable on all protection policy tables

4. **`database/migrations/2025_11_24_141304_add_policy_end_date_to_protection_policies.php`** (EXISTING)
   - Previously added `policy_end_date` columns

5. **`app/Http/Controllers/Api/ProtectionController.php`**
   - Lines 326, 328: Made `policy_start_date` and `policy_term_years` nullable for Critical Illness
   - Lines 450-452: Made `policy_start_date` nullable, `deferred_period_weeks` nullable, added `policy_term_years` for Income Protection

6. **`app/Http/Requests/Protection/StoreDisabilityPolicyRequest.php`**
   - Lines 32, 37: Made `deferred_period_weeks` and `policy_start_date` nullable

7. **`app/Http/Requests/Protection/UpdateDisabilityPolicyRequest.php`**
   - Line 32: Made `deferred_period_weeks` nullable

8. **`app/Http/Requests/Protection/StoreSicknessIllnessPolicyRequest.php`**
   - Line 36: Made `policy_start_date` nullable

9. **`app/Http/Requests/Protection/UpdateSicknessIllnessPolicyRequest.php`**
   - Line 32: Made `deferred_period_weeks` nullable

10. **`app/Http/Requests/Retirement/StoreDCPensionRequest.php`** (Reference only - validation already nullable)

11. **`app/Http/Requests/Retirement/StoreDBPensionRequest.php`** (Reference only - unchanged)

12. **`app/Services/UserProfile/UserProfileService.php`**
   - Lines 566-572: Fixed Income Protection premium frequency calculation
   - Lines 586-607: Added Disability policy premium tracking
   - Lines 609-630: Added Sickness/Illness policy premium tracking

### Frontend (8 files)

1. **`resources/js/components/Retirement/DCPensionForm.vue`**
   - Lines 398-407: Fixed stakeholder pension `scheme_type` mapping

2. **`resources/js/components/Retirement/DBPensionForm.vue`**
   - Lines 320-335: Added field name mapping from form fields to API fields

3. **`resources/js/components/Retirement/PensionCard.vue`**
   - Lines 168-191: Enhanced `typeLabel` to show specific pension types

4. **`resources/js/components/Onboarding/steps/AssetsStep.vue`**
   - Lines 511-524: Fixed pension data loading (response.data.data path)
   - Lines 64-73, 82-89: Replaced custom HTML with PensionCard component
   - Lines 434, 454: Added PensionCard import and registration

5. **`resources/js/components/Protection/PolicyFormModal.vue`**
   - Lines 790-792: Fixed Critical Illness date field submission
   - Lines 798-800: Fixed other policy types date field submission

6. **`resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue`**
   - Lines 67-69: Added `benefit_amount` to coverage display
   - Lines 182-187: Added `getCoverageLabel` function
   - Line 399: Added `getCoverageLabel` to return statement

7. **`resources/js/components/NetWorth/Property/PropertyForm.vue`**
   - Lines 596-598: Added red asterisk to Lender Name label

8. **`resources/js/components/UserProfile/FamilyMemberFormModal.vue`** (Reference only - previously modified)

---

## Deployment Instructions

### Step 1: Backup
```bash
cd ~/tengo-app
cp -r database/migrations database/migrations.backup-$(date +%Y%m%d)
cp -r resources/js/components resources/js/components.backup-$(date +%Y%m%d)
cp -r app/Http app/Http.backup-$(date +%Y%m%d)
```

### Step 2: Upload Modified Files

**Database Migrations (3 new files)**:
```
database/migrations/2025_11_24_124735_make_policy_end_date_nullable_on_life_insurance_policies_table.php
database/migrations/2025_11_24_144502_make_scheme_type_nullable_on_dc_pensions_table.php
database/migrations/2025_11_24_151629_make_protection_policy_dates_nullable.php
```

**Backend (7 files)**:
```
app/Http/Controllers/Api/ProtectionController.php
app/Http/Requests/Protection/StoreDisabilityPolicyRequest.php
app/Http/Requests/Protection/UpdateDisabilityPolicyRequest.php
app/Http/Requests/Protection/StoreSicknessIllnessPolicyRequest.php
app/Http/Requests/Protection/UpdateSicknessIllnessPolicyRequest.php
app/Services/UserProfile/UserProfileService.php
```

**Frontend (8 files)**:
```
resources/js/components/Retirement/DCPensionForm.vue
resources/js/components/Retirement/DBPensionForm.vue
resources/js/components/Retirement/PensionCard.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/Onboarding/steps/AssetsStep.vue
resources/js/components/Protection/PolicyFormModal.vue
resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue
```

### Step 3: Run Database Migrations

**CRITICAL - Run in order**:

```bash
cd ~/tengo-app
php artisan migrate --step
```

**Expected output**:
```
INFO  Running migrations.

2025_11_24_124735_make_policy_end_date_nullable_on_life_insurance_policies_table ... DONE
2025_11_24_144502_make_scheme_type_nullable_on_dc_pensions_table ................... DONE
2025_11_24_151629_make_protection_policy_dates_nullable ............................ DONE
```

### Step 4: Rebuild Frontend
```bash
export NODE_ENV=production
npm run build
```

### Step 5: Clear Caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### Step 6: Test Deployment
- Test DC pension creation (all types including stakeholder)
- Test DB pension creation
- Verify pensions display in onboarding
- Test all protection policy types
- Verify Disability and Sickness/Illness premiums appear in expenditure
- Check Property Form shows red asterisk on Lender Name field

### Step 7: Tag Release
```bash
git add -A
git commit -m "fix: v0.2.13 - Critical pension and protection policy fixes

DC PENSION FIXES:
- Fixed scheme_type NULL constraint causing 500 errors
- Fixed stakeholder pension mapping to personal scheme_type
- Fixed pension display in onboarding (response data path)
- Enhanced pension cards to show specific types

DB PENSION FIXES:
- Fixed field name mapping (employer_name → scheme_name, etc.)
- DB pensions now save successfully

PROTECTION POLICY FIXES:
- Made policy_end_date nullable on life_insurance_policies (Whole of Life support)
- Made policy_start_date, policy_term_years, deferred_period_weeks nullable in database
- Updated all backend validation to accept nullable dates
- Fixed frontend to send proper date values
- Fixed benefit_amount display for IP/Disability/Sickness policies

FINANCIAL COMMITMENTS FIXES:
- Added Disability policy premiums to expenditure tracking
- Added Sickness/Illness policy premiums to expenditure tracking
- Fixed Income Protection premium frequency conversion

UI IMPROVEMENTS:
- Added red asterisk to Property Form Lender Name field

MIGRATIONS:
- 2025_11_24_124735: Make policy_end_date nullable on life_insurance_policies
- 2025_11_24_144502: Make scheme_type nullable on dc_pensions
- 2025_11_24_151629: Make protection policy dates nullable

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"

git tag v0.2.13
git push origin main --tags
```

---

## Testing Checklist

### DC Pensions
- [ ] Create Occupational pension - verify saves successfully
- [ ] Create SIPP pension - verify saves successfully
- [ ] Create Personal pension - verify saves successfully
- [ ] Create Stakeholder pension - verify saves successfully
- [ ] Verify all pensions display with correct type labels in cards
- [ ] Verify pensions show in onboarding Assets & Wealth step

### DB Pensions
- [ ] Create DB pension in onboarding
- [ ] Verify: Fields map correctly (scheme_name appears in database)
- [ ] Verify: Pension displays in card list

### Protection Policies
- [ ] Create Critical Illness without dates - verify saves
- [ ] Create Income Protection without dates - verify saves
- [ ] Create Disability without dates - verify saves
- [ ] Create Sickness/Illness without dates - verify saves
- [ ] Verify benefit amounts display correctly for all policy types

### Financial Commitments (Expenditure)
- [ ] Navigate to User Profile → Expenditure
- [ ] Verify Disability premium appears (£250/month)
- [ ] Verify Sickness/Illness premium appears (£200/month)
- [ ] Verify Income Protection premium displays correctly
- [ ] Verify all protection premiums sum correctly in totals

### Property Form
- [ ] Open Property Form to add a property with mortgage
- [ ] Verify "Lender Name" field has red asterisk (*)

---

## Rollback Plan

```bash
cd ~/tengo-app

# Rollback migrations
php artisan migrate:rollback --step=3

# Restore from backup
cp -r resources/js/components.backup-YYYYMMDD/* resources/js/components/
cp -r app/Http.backup-YYYYMMDD/* app/Http/

# Rebuild
npm run build
php artisan cache:clear
```

---

**Deployment Date**: 24 November 2025
**Deployed By**: Chris Jones
**Status**: ✅ SUCCESSFULLY DEPLOYED AND TESTED

### Deployment Notes

**Deployed**: 24 November 2025 at 19:45 GMT
**Production URL**: https://csjones.co/tengo

**Migration Status**: ✅ All migrations applied successfully via manual SQL execution
- `2025_11_24_124735_make_policy_end_date_nullable_on_life_insurance_policies_table` - DONE
- `2025_11_24_144502_make_scheme_type_nullable_on_dc_pensions_table` - DONE (with stakeholder enum value)
- `2025_11_24_151629_make_protection_policy_dates_nullable` - DONE

**Additional Database Fixes Required**:
- ✅ `dc_pensions.provider` made nullable (Column 'provider' cannot be null error)

**Manual SQL Commands Executed**:
```sql
ALTER TABLE life_insurance_policies MODIFY COLUMN policy_end_date DATE NULL;
ALTER TABLE dc_pensions MODIFY COLUMN scheme_type ENUM("workplace", "sipp", "personal", "stakeholder") NULL DEFAULT NULL;
ALTER TABLE dc_pensions MODIFY COLUMN provider VARCHAR(255) NULL;
ALTER TABLE critical_illness_policies MODIFY COLUMN policy_start_date DATE NULL, MODIFY COLUMN policy_term_years INT NULL;
ALTER TABLE income_protection_policies MODIFY COLUMN policy_start_date DATE NULL, MODIFY COLUMN deferred_period_weeks INT NULL;
ALTER TABLE disability_policies MODIFY COLUMN policy_start_date DATE NULL, MODIFY COLUMN deferred_period_weeks INT NULL;
ALTER TABLE sickness_illness_policies MODIFY COLUMN policy_start_date DATE NULL;
```

**Deployment Issues Encountered & Resolved**:
1. ✅ Laravel migrations showed as "Ran" but didn't execute - Fixed by running raw SQL via tinker
2. ✅ Missing `provider` column nullable constraint - Fixed with ALTER TABLE
3. ✅ Vite asset path configuration issues during deployment (unrelated to patch)
4. ✅ All Vue component fixes uploaded and frontend rebuilt successfully
5. ✅ All caches cleared and config rebuilt

**Testing Results**: ✅ ALL TESTS PASSED
- ✅ DC Pension creation (all types including stakeholder) - WORKING
- ✅ DB Pension creation - WORKING
- ✅ Pensions display correctly in onboarding - WORKING
- ✅ Life Insurance policy creation - WORKING
- ✅ Critical Illness policy creation - WORKING
- ✅ Income Protection policy creation - WORKING
- ✅ Disability policy creation - WORKING
- ✅ Sickness/Illness policy creation - WORKING

---

🤖 **Generated with [Claude Code](https://claude.com/claude-code)**
