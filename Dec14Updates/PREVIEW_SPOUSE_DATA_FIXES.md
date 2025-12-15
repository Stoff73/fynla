# Preview Mode Spouse Data Fixes (December 15, 2025)

## Summary

Fixed preview mode personas to correctly display spouse-specific data across all modules. Implemented the reciprocal records pattern for joint assets and added owner detection logic for pensions and accounts.

## Problem Statement

Preview mode personas (young_family, peak_earners, widow, entrepreneur) were not correctly displaying spouse data:

1. Joint assets were not properly split between spouses
2. Pensions were all assigned to the primary user instead of the correct spouse
3. Individual savings/investment accounts named for the spouse were going to the primary user
4. Sarah Mitchell's NHS DB pension showed £0 annual pension instead of £35,000

## Root Causes

1. **No reciprocal records**: Joint assets were creating only one database record instead of two
2. **Missing owner detection**: No logic to determine which spouse owns a pension or account
3. **DB pension field mismatch**: Seeder used `current_annual_pension` but JSON uses `accrued_annual_pension`
4. **Estate module preview bypass**: `IHTPlanning.vue` and `WillPlanning.vue` were skipping API calls in preview mode, using client-side calculations without spouse data

## Solution: Reciprocal Records Pattern

### Concept

For joint assets, create TWO database records - one for each owner:

```php
// Joint property worth £320,000 total (50/50 split)

// Record 1: Primary user's share
Property::create([
    'user_id' => $primaryUser->id,
    'joint_owner_id' => $spouse->id,
    'current_value' => 160000,  // 50% share
    'ownership_percentage' => 50,
    'ownership_type' => 'joint',
]);

// Record 2: Spouse's share (reciprocal)
Property::create([
    'user_id' => $spouse->id,
    'joint_owner_id' => $primaryUser->id,
    'current_value' => 160000,  // 50% share
    'ownership_percentage' => 50,
    'ownership_type' => 'joint',
]);
```

### Benefits

1. **Simple queries**: Services only need to query by `user_id`
2. **Correct totals**: Each user sees only their share
3. **No duplicate counting**: No risk of double-counting joint assets
4. **Consistent pattern**: Same approach across all asset types

### Applied To

- Properties (`createProperties()`)
- Mortgages (`createMortgages()`)
- Savings Accounts (`createSavingsAccounts()`)
- Investment Accounts (`createInvestmentAccounts()`)

## Solution: Owner Detection

### For Pensions (`determinePensionOwner()`)

```php
private function determinePensionOwner(array $pension, User $user, ?User $spouse): User
{
    if (!$spouse) return $user;

    // 1. Explicit owner flag
    if (isset($pension['owner']) && $pension['owner'] === 'spouse') {
        return $spouse;
    }

    // 2. Notes contain spouse's first name
    $notes = strtolower($pension['notes'] ?? '');
    $spouseFirstName = strtolower(explode(' ', $spouse->name)[0] ?? '');
    if ($spouseFirstName && str_contains($notes, $spouseFirstName)) {
        return $spouse;
    }

    // 3. Scheme name contains spouse's employer
    $schemeName = strtolower($pension['scheme_name'] ?? '');
    $spouseEmployer = strtolower($spouse->employer ?? '');
    if ($spouseEmployer && str_contains($schemeName, $spouseEmployer)) {
        return $spouse;
    }

    // 4. Annual salary matches spouse's income (within 1%)
    $pensionSalary = $pension['annual_salary'] ?? 0;
    $spouseIncome = $spouse->annual_employment_income ?? 0;
    if ($pensionSalary > 0 && $spouseIncome > 0) {
        $difference = abs($pensionSalary - $spouseIncome) / $spouseIncome;
        if ($difference < 0.01) {
            return $spouse;
        }
    }

    return $user;
}
```

### For Savings/Investment Accounts (`determineAccountOwner()`)

```php
private function determineAccountOwner(array $account, User $user, ?User $spouse): User
{
    if (!$spouse) return $user;

    // 1. Explicit owner flag
    if (isset($account['owner']) && $account['owner'] === 'spouse') {
        return $spouse;
    }

    // 2. Account name contains spouse's first name
    $accountName = strtolower($account['account_name'] ?? '');
    $spouseFirstName = strtolower(explode(' ', $spouse->name)[0] ?? '');
    if ($spouseFirstName && str_contains($accountName, $spouseFirstName)) {
        return $spouse;
    }

    // 3. Notes contain spouse's first name
    $notes = strtolower($account['notes'] ?? '');
    if ($spouseFirstName && str_contains($notes, $spouseFirstName)) {
        return $spouse;
    }

    return $user;
}
```

## DB Pension Field Mapping Fix

### Before (incorrect)
```php
DBPension::create([
    'accrued_annual_pension' => $pension['current_annual_pension'] ?? 0,
    'lump_sum_entitlement' => $pension['lump_sum_option'] ?? null,
]);
```

### After (correct)
```php
DBPension::create([
    'accrued_annual_pension' => $pension['accrued_annual_pension'] ?? $pension['current_annual_pension'] ?? 0,
    'lump_sum_entitlement' => $pension['lump_sum_entitlement'] ?? $pension['lump_sum_option'] ?? null,
]);
```

## Solution: Estate Module Frontend Fix

### Problem

The `IHTPlanning.vue` component was bypassing API calls in preview mode:

```javascript
// OLD CODE - bypassed API in preview mode
async loadIHTCalculation() {
    const isPreviewMode = this.$store.getters['preview/isPreviewMode'];
    if (isPreviewMode) {
        this.computePreviewIHTData();  // Client-side calc without spouse data
        return;
    }
    // ... API call only for non-preview users
}
```

### Fix

Remove the preview mode bypass so all users (including preview) use the API:

```javascript
// NEW CODE - all users use API
async loadIHTCalculation() {
    const isPreviewMode = this.$store.getters['preview/isPreviewMode'];
    if (isPreviewMode) {
        console.log('[IHTPlanning] Preview mode - using API with real database users');
    }
    // API call proceeds for all users...
}
```

### Benefits

- Preview users now see full spouse asset breakdown
- Combined NRB (£650,000) displayed for married couples
- Spouse exemption notices appear correctly
- All IHT calculations use real database data

## Files Modified

### Backend

1. **database/seeders/PreviewUserSeeder.php**
   - Added `determinePensionOwner()` method
   - Added `determineAccountOwner()` method
   - Updated `createProperties()` to create reciprocal records
   - Updated `createMortgages()` to create reciprocal records
   - Updated `createSavingsAccounts()` to create reciprocal records with owner detection
   - Updated `createInvestmentAccounts()` to create reciprocal records with owner detection
   - Updated `createDCPensions()` to use owner detection
   - Updated `createDBPensions()` to use owner detection and fix field mapping
   - Fixed expenditure category null constraint issues

2. **app/Services/Shared/CrossModuleAssetAggregator.php**
   - Simplified to use only `user_id` queries (no joint_owner_id logic needed)
   - Added documentation about reciprocal records pattern

3. **app/Services/NetWorth/NetWorthService.php**
   - Updated `calculateLiabilitiesBreakdown()` to use simple `user_id` query pattern

### Frontend

4. **resources/js/components/Estate/IHTPlanning.vue**
   - Removed preview mode bypass that was calling `computePreviewIHTData()` instead of API
   - Now uses real API endpoint which returns full spouse data breakdown
   - Estate IHT Planning tab now shows combined NRB (£650,000) for married couples
   - Both spouses' assets and liabilities display correctly in breakdown tables

5. **resources/js/components/Estate/WillPlanning.vue**
   - Removed `if (!this.isPreviewMode)` check that skipped data loading
   - Now loads will data via API for preview users
   - Spouse-related will planning features now work correctly

### Already Updated (no changes needed)
- `resources/js/components/Estate/GiftingStrategy.vue` - Already uses API
- `resources/js/components/Estate/TrustPlanning.vue` - Already uses API
- `resources/js/components/Estate/TrustPlanningStrategy.vue` - Already uses API
- `resources/js/components/Estate/IntestacyRules.vue` - Already uses API
- `resources/js/components/Estate/LifePolicyStrategy.vue` - Already uses API

## Verification Results

### young_family (James & Emily Carter)
- **James**: £93,950 net worth
  - Property: £160,000 (50% of joint)
  - Pension: £45,000 (TechCorp)
  - Cash: £8,450
  - Investment: £15,000 (Vanguard ISA)
  - Mortgage: £122,500 (50% of joint)
  - Loans: £12,000

- **Emily**: £63,750 net worth
  - Property: £160,000 (50% of joint)
  - Pension: £22,000 (MediaGroup)
  - Cash: £4,250 (50% of joint Marcus)
  - Mortgage: £122,500 (50% of joint)

### peak_earners (David & Sarah Mitchell)
- **David**: £902,500 net worth
  - Property: £637,500
  - Pension: £320,000 (SIPP)
  - Cash: £47,500
  - Investment: £172,500
  - Mortgage: £250,000
  - Loans: £25,000

- **Sarah**: £1,372,500 net worth
  - Property: £637,500
  - Pension: £805,000 (NHS DB: £35k × 20 + £105k lump sum)
  - Cash: £47,500
  - Investment: £132,500
  - Mortgage: £250,000

### widow (Margaret Thompson)
- **Margaret**: £2,239,000 net worth (individual ownership)
- **Robert**: £0 (deceased - assets transferred)

### entrepreneur (Alex Chen)
- **Alex**: £545,000 net worth (no spouse)

## Solution: Preview Modal Flow Fix

### Problem

The PersonaIntroModal was appearing briefly then disappearing when:
1. Clicking "Try Demo" on the landing page
2. Selecting a different persona from the dropdown

### Root Cause

**PersonaSelector.vue** was calling `switchPersona()` immediately after emitting the `persona-selected` event. This triggered a page reload before the user could interact with the modal.

**LandingPage.vue** was directly calling `loadPersona()` and navigating without showing an intro modal.

### Fix

1. **PersonaSelector.vue** - Removed the immediate `switchPersona()` call from `doSwitch()`:

```javascript
// BEFORE (broken) - called switchPersona immediately
async doSwitch(persona) {
    this.isOpen = false;
    this.$emit('persona-selected', persona);
    await this.switchPersona(persona.id);  // Page reloads here!
}

// AFTER (fixed) - only emit event, let PreviewBanner handle the switch
doSwitch(persona) {
    this.isOpen = false;
    this.$emit('persona-selected', persona);
    // PreviewBanner.confirmPersonaSwitch() handles the actual switch
}
```

2. **LandingPage.vue** - Added PersonaIntroModal with proper flow:

```javascript
// Show modal when user clicks "Try Demo"
enterPreviewMode() {
    this.selectedPersona = DEFAULT_PERSONA;
    this.showIntroModal = true;
},

// User clicks "Explore Dashboard" in modal
async confirmPreview() {
    this.showIntroModal = false;
    await this.$store.dispatch('preview/loadPersona', 'young_family');
    await nextTick();  // Wait for Vue reactivity
    this.$router.push('/dashboard');
}
```

### Files Modified

- **resources/js/components/Preview/PersonaSelector.vue** - Removed `switchPersona()` from `doSwitch()`
- **resources/js/views/Public/LandingPage.vue** - Added PersonaIntroModal with `confirmPreview()` handler

## Testing Commands

```bash
# Delete and reseed preview users
php artisan tinker --execute="
\$previewUsers = App\Models\User::where('is_preview_user', true)->get();
foreach (\$previewUsers as \$user) {
    App\Models\Property::where('user_id', \$user->id)->delete();
    App\Models\Mortgage::where('user_id', \$user->id)->delete();
    App\Models\SavingsAccount::where('user_id', \$user->id)->delete();
    App\Models\Investment\InvestmentAccount::where('user_id', \$user->id)->delete();
    App\Models\DCPension::where('user_id', \$user->id)->delete();
    App\Models\DBPension::where('user_id', \$user->id)->delete();
    \$user->tokens()->delete();
    \$user->delete();
}
"

php artisan db:seed --class=PreviewUserSeeder --force
```

## Related Documentation

- **CLAUDE.md**: Updated with reciprocal records pattern and preview mode documentation
- **README.md**: Added changelog entry for v0.2.19

## Version

**v0.2.20** - December 15, 2025
