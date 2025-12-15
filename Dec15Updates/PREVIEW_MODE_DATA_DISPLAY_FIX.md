# Preview Mode Data Display Fix

**Date:** 15 December 2024
**Branch:** `persona`
**Issue:** Persona data not displaying in User Profile tabs (family members, addresses, expenses)

---

## Problem Summary

Preview mode personas were showing empty data in the User Profile section despite the API returning correct data. Affected tabs included:
- Personal Information (address not showing)
- Family Members (spouse and children not showing)
- Expenditure (monthly expenses not showing)

## Root Cause

Components were incorrectly **skipping API calls in preview mode**, assuming data was already loaded. However, the preview store documentation clearly states:

> "Preview users are actual database users with `is_preview_user=true`. They use the same code paths as real users - data is loaded via normal APIs."

The preview mode checks were remnants of an older architecture and were causing the Vuex store to remain empty.

---

## Files Modified

### 1. UserProfile.vue
**Path:** `resources/js/views/UserProfile.vue`

**Change:** Removed preview mode skip for `loadProfile()`

```javascript
// BEFORE (broken)
onMounted(() => {
  const isPreviewMode = store.getters['preview/isPreviewMode'];
  if (!isPreviewMode) {
    loadProfile();
  } else {
    console.log('[UserProfile] Preview mode - data already loaded');
  }
});

// AFTER (fixed)
onMounted(() => {
  // Load profile for all users (including preview mode)
  // Preview users are real database users and use the same code paths
  loadProfile();
});
```

### 2. FamilyMembers.vue
**Path:** `resources/js/components/UserProfile/FamilyMembers.vue`

**Changes:**
- Removed preview mode check that was using empty store data
- Added Vue `watch` to sync with store data (which includes spouse)
- Updated CRUD operations to refresh the profile store
- Added preview-specific success messages

```javascript
// Added watcher to sync with store
const storeFamilyMembers = computed(() => store.state.userProfile.familyMembers);
watch(storeFamilyMembers, (newMembers) => {
  if (newMembers && newMembers.length > 0) {
    familyMembers.value = newMembers;
  }
}, { immediate: true });
```

### 3. ExpenditureOverview.vue
**Path:** `resources/js/components/UserProfile/ExpenditureOverview.vue`

**Changes:**
- Removed preview mode skip for spouse data fetch
- Removed preview mode skip in `onMounted`
- Added preview-specific success message

### 4. ExpenditureForm.vue
**Path:** `resources/js/components/UserProfile/ExpenditureForm.vue`

**Change:** Removed preview mode skip for financial commitments fetch

### 5. api.js
**Path:** `resources/js/services/api.js`

**Change:** Added response interceptor to handle `preview_mode` flag

```javascript
// Response interceptor now logs preview mode changes
if (response.data?.preview_mode === true) {
  console.info('[Preview Mode] Changes are session-only and will not be saved.');
  response.data._preview_notice = response.data.preview_notice || 'Changes are session-only...';
}
```

### 6. PersonalInformation.vue
**Path:** `resources/js/components/UserProfile/PersonalInformation.vue`

**Change:** Added preview-specific success message when saving

```javascript
const isPreviewMode = store.getters['preview/isPreviewMode'];
successMessage.value = isPreviewMode
  ? 'Changes saved for this session only (preview mode).'
  : 'Personal information updated successfully!';
```

---

## Preview Mode Data Protection

The `PreviewWriteInterceptor` middleware ensures preview data is never persisted:

**Path:** `app/Http/Middleware/PreviewWriteInterceptor.php`

**How it works:**
1. Intercepts all write operations (POST, PUT, PATCH, DELETE) for preview users
2. Returns fake success response: `{ success: true, preview_mode: true }`
3. Data is **never saved** to the database
4. Frontend shows preview-specific messages

**Excluded routes:**
- `api/preview/exit`
- `api/preview/switch`
- `api/auth/logout`

---

## Data Flow (After Fix)

```
User selects persona on landing page
       ↓
POST /api/preview/login/{personaId}
       ↓
PreviewController finds/creates preview user in database
       ↓
Returns Sanctum token + user metadata
       ↓
User navigates to /profile
       ↓
UserProfile.vue mounts → dispatches userProfile/fetchProfile
       ↓
GET /api/user/profile returns complete data:
  - personal_info (name, DOB, address)
  - family_members (spouse + children)
  - expenditure (monthly expenses)
  - income_occupation
  - assets_summary
  - liabilities_summary
       ↓
Store populated → Components display data
```

---

## API Response Examples

### Profile API (GET /api/user/profile)
```json
{
  "success": true,
  "data": {
    "personal_info": {
      "name": "James Carter",
      "date_of_birth": "1990-05-15",
      "address": {
        "line_1": "42 Oak Avenue",
        "city": "Birmingham",
        "postcode": "B15 2TT"
      }
    },
    "family_members": [
      { "name": "Oliver Carter", "relationship": "child" },
      { "name": "Sophie Carter", "relationship": "child" },
      { "name": "Emily Carter", "relationship": "spouse" }
    ],
    "expenditure": {
      "monthly_expenditure": 4200
    }
  }
}
```

### Write Operation in Preview Mode
```json
{
  "success": true,
  "message": "Preview: Record updated (not saved)",
  "preview_mode": true,
  "preview_notice": "Changes are session-only and will be lost on refresh."
}
```

---

## Testing Verification

1. **Login as preview user:**
   ```bash
   curl -X POST http://localhost:8000/api/preview/login/young_family
   ```

2. **Verify profile data loads:**
   ```bash
   curl -H "Authorization: Bearer {token}" http://localhost:8000/api/user/profile
   ```

3. **Verify write is intercepted:**
   ```bash
   curl -X PUT -H "Authorization: Bearer {token}" \
     -d '{"name":"Test"}' http://localhost:8000/api/user/profile/personal
   # Returns: preview_mode: true
   ```

4. **Verify data unchanged:**
   ```bash
   curl -H "Authorization: Bearer {token}" http://localhost:8000/api/user/profile
   # Name still "James Carter"
   ```

---

## Summary of Changes

| File | Change Type | Description |
|------|-------------|-------------|
| UserProfile.vue | Bug fix | Remove preview mode skip for loadProfile |
| FamilyMembers.vue | Bug fix + Enhancement | Load data correctly, sync with store, preview messages |
| ExpenditureOverview.vue | Bug fix + Enhancement | Remove skips, add preview message |
| ExpenditureForm.vue | Bug fix | Remove preview mode skip |
| PersonalInformation.vue | Enhancement | Add preview-specific success message |
| api.js | Enhancement | Log preview mode responses |

---

## Additional Fixes (Session 2)

### Issue: Expenditure Categories Not Showing

The expenditure form was showing no data because:
1. PreviewWriteInterceptor was blocking POST `/calculate` endpoints
2. UserProfileService wasn't returning expenditure category breakdown
3. PreviewUserSeeder wasn't populating expenditure category fields

### 7. PreviewWriteInterceptor.php
**Path:** `app/Http/Middleware/PreviewWriteInterceptor.php`

**Change:** Added excluded patterns for calculation endpoints (POST endpoints that compute data without modifying it)

```php
private const EXCLUDED_PATTERNS = [
    '/calculate',           // All calculation endpoints (personal-accounts, IHT, SDLT, etc.)
    '/calculate-',          // Hyphenated calculation endpoints (calculate-sdlt, calculate-iht)
];

// In handle() method:
foreach (self::EXCLUDED_PATTERNS as $pattern) {
    if (str_contains($currentPath, $pattern)) {
        return $next($request);
    }
}
```

### 8. UserProfileService.php
**Path:** `app/Services/UserProfile/UserProfileService.php`

**Change:** Added expenditure categories to the profile API response

```php
// BEFORE
'expenditure' => [
    'monthly_expenditure' => $user->monthly_expenditure,
    'annual_expenditure' => $user->annual_expenditure,
],

// AFTER
'expenditure' => [
    'monthly_expenditure' => $user->monthly_expenditure,
    'annual_expenditure' => $user->annual_expenditure,
    'categories' => [
        'food_groceries' => $user->food_groceries,
        'transport_fuel' => $user->transport_fuel,
        'clothing_personal_care' => $user->clothing_personal_care,
        'entertainment_dining' => $user->entertainment_dining,
        'childcare' => $user->childcare,
        'other_expenditure' => $user->other_expenditure,
    ],
],
```

### 9. PreviewUserSeeder.php
**Path:** `database/seeders/PreviewUserSeeder.php`

**Change:** Updated to populate expenditure category fields from persona JSON

```php
// Expenditure categories (from separate expenditure data in persona JSON)
if ($expenditureData && !empty($expenditureData['categories'])) {
    $categories = $expenditureData['categories'];
    $user->monthly_expenditure = $expenditureData['total_monthly'] ?? $userData['monthly_expenditure'] ?? null;
    $user->food_groceries = $categories['food'] ?? null;
    $user->transport_fuel = $categories['transport'] ?? null;
    $user->clothing_personal_care = $categories['clothing'] ?? null;
    $user->entertainment_dining = $categories['entertainment'] ?? null;
    $user->childcare = $categories['childcare'] ?? null;
    $user->other_expenditure = $categories['other'] ?? null;
}
```

---

## Updated API Response Examples

### Profile API (GET /api/user/profile) - With Categories
```json
{
  "success": true,
  "data": {
    "expenditure": {
      "monthly_expenditure": 4200,
      "annual_expenditure": null,
      "categories": {
        "food_groceries": 600,
        "transport_fuel": 450,
        "clothing_personal_care": 100,
        "entertainment_dining": 200,
        "childcare": 800,
        "other_expenditure": 165
      }
    }
  }
}
```

### Auth User API (GET /api/auth/user) - Flat Fields
```json
{
  "success": true,
  "data": {
    "user": {
      "monthly_expenditure": 4200,
      "food_groceries": 600,
      "transport_fuel": 450,
      "clothing_personal_care": 100,
      "entertainment_dining": 200,
      "childcare": 800,
      "other_expenditure": 165
    }
  }
}
```

---

## Updated Summary of Changes

| File | Change Type | Description |
|------|-------------|-------------|
| UserProfile.vue | Bug fix | Remove preview mode skip for loadProfile |
| FamilyMembers.vue | Bug fix + Enhancement | Load data correctly, sync with store, preview messages |
| ExpenditureOverview.vue | Bug fix + Enhancement | Remove skips, add preview message |
| ExpenditureForm.vue | Bug fix | Remove preview mode skip |
| PersonalInformation.vue | Enhancement | Add preview-specific success message |
| api.js | Enhancement | Log preview mode responses |
| PreviewWriteInterceptor.php | Bug fix | Allow calculation endpoints through |
| UserProfileService.php | Enhancement | Add expenditure categories to profile response |
| PreviewUserSeeder.php | Bug fix | Populate expenditure category fields |

---

---

## Additional Fixes (Session 3) - Spouse Financial Data

### Issue: Spouse Data Showing 0 in Financial Statements

The financial statements were showing £0 for spouse expenses because:
1. PersonalAccountsService only included mortgage/property expenses, not living expenses
2. PreviewUserSeeder wasn't populating spouse expenditure fields

### 10. PersonalAccountsService.php
**Path:** `app/Services/UserProfile/PersonalAccountsService.php`

**Change:** Added "Living Expenses" line item to both P&L and Cashflow calculations

```php
// In calculateProfitAndLoss():
$livingExpenses = ($user->monthly_expenditure ?? 0) * 12;

$expenses = [
    // ... existing expenses ...
    [
        'line_item' => 'Living Expenses',
        'category' => 'expense',
        'amount' => $livingExpenses,
    ],
];

// In calculateCashflow():
$livingExpenses = ($user->monthly_expenditure ?? 0) * 12;

$outflows = [
    // ... existing outflows ...
    [
        'line_item' => 'Living Expenses',
        'category' => 'cash_outflow',
        'amount' => $livingExpenses,
    ],
];
```

### 11. PreviewUserSeeder.php (Spouse Expenditure)
**Path:** `database/seeders/PreviewUserSeeder.php`

**Change:** Updated createSpouse() to populate spouse's share of household expenditure (50/50 split)

```php
private function createSpouse(array $spouseData, string $personaId, User $primaryUser, ?array $expenditureData = null): User
{
    // ... existing spouse creation ...

    // Expenditure: Split household expenditure 50/50
    if ($expenditureData && !empty($expenditureData['categories'])) {
        $categories = $expenditureData['categories'];
        $share = 0.5;
        $spouse->monthly_expenditure = round(($expenditureData['total_monthly'] ?? 0) * $share);
        $spouse->food_groceries = round(($categories['food'] ?? 0) * $share);
        // ... other categories ...
    }
}
```

---

## Financial Statements After Fix

### Primary User (James Carter)
```
Income: £62,000
Expenses:
  - Mortgage Payments: £17,820
  - Property Expenses: £0
  - Living Expenses: £50,400
Total Expenses: £68,220
Net P&L: £-6,220
```

### Spouse (Emily Carter)
```
Income: £48,000
Expenses:
  - Mortgage Payments: £0
  - Property Expenses: £0
  - Living Expenses: £25,200
Total Expenses: £25,200
Net P&L: £22,800
```

### Household Summary
```
Combined Income: £110,000
Combined Expenses: £93,420
Combined Net: £16,580
```

---

## Final Summary of All Changes

| File | Change Type | Description |
|------|-------------|-------------|
| UserProfile.vue | Bug fix | Remove preview mode skip for loadProfile |
| FamilyMembers.vue | Bug fix + Enhancement | Load data correctly, sync with store, preview messages |
| ExpenditureOverview.vue | Bug fix + Enhancement | Remove skips, add preview message |
| ExpenditureForm.vue | Bug fix | Remove preview mode skip |
| PersonalInformation.vue | Enhancement | Add preview-specific success message |
| api.js | Enhancement | Log preview mode responses |
| PreviewWriteInterceptor.php | Bug fix | Allow calculation endpoints through |
| UserProfileService.php | Enhancement | Add expenditure categories to profile response |
| PreviewUserSeeder.php | Bug fix | Populate expenditure category fields + spouse expenditure |
| PersonalAccountsService.php | Bug fix | Add Living Expenses to P&L and Cashflow calculations |

---

## Notes

- Preview users are **real database users** with `is_preview_user=true`
- They should use the **same code paths** as regular users
- Write operations are intercepted by middleware, not frontend
- Frontend preview checks are only for **UI messaging**, not data protection
- Calculation endpoints (POST requests that don't modify data) are excluded from interception
- Spouse data sharing is automatic when both users are married with linked spouse_id

---

## Additional Fixes (Session 4) - Balance Sheet Joint Assets

### Issue: Spouse Balance Sheet Showing £0 for Everything

The balance sheet was only querying assets/liabilities by `user_id`, missing joint assets where the spouse is `joint_owner_id`.

### 12. PersonalAccountsService.php - Joint Asset Queries
**Path:** `app/Services/UserProfile/PersonalAccountsService.php`

**Changes:**
1. Updated all asset/liability queries to include `orWhere('joint_owner_id', $user->id)`
2. Added 50/50 split calculation for joint assets/liabilities
3. Added `net_worth` alias to balance sheet response

```php
// Example for savings accounts:
$cashAccounts = SavingsAccount::where('user_id', $user->id)
    ->orWhere('joint_owner_id', $user->id)
    ->get();
foreach ($cashAccounts as $account) {
    $amount = $account->current_balance;
    if ($account->ownership_type === 'joint' && $account->joint_owner_id) {
        $amount = $account->current_balance * 0.5;  // 50% share
    }
    // ...
}
```

**Updated queries:**
- SavingsAccount (cash)
- InvestmentAccount
- Property
- Mortgage
- Liability

---

## Balance Sheet After Fix

### James (Primary User)
```
Total Assets: £250,450
Total Liabilities: £134,500
Net Worth: £115,950
```

### Emily (Spouse)
```
Total Assets: £164,250
  - Cash (50% of Emergency Fund): £4,250
  - Property (50% of Family Home): £160,000
Total Liabilities: £122,500
  - Mortgage (50%): £122,500
Net Worth: £41,750
```

### Household Total
```
Combined Net Worth: £157,700 ✓
(Matches original total: £414,700 assets - £257,000 liabilities)
```

---

## Complete Summary of All Changes

| File | Change Type | Description |
|------|-------------|-------------|
| UserProfile.vue | Bug fix | Remove preview mode skip for loadProfile |
| FamilyMembers.vue | Bug fix | Load data correctly, sync with store |
| ExpenditureOverview.vue | Bug fix | Remove skips, add preview message |
| ExpenditureForm.vue | Bug fix | Remove preview mode skip |
| PersonalInformation.vue | Enhancement | Add preview-specific success message |
| api.js | Enhancement | Log preview mode responses |
| PreviewWriteInterceptor.php | Bug fix | Allow calculation endpoints through |
| UserProfileService.php | Enhancement | Add expenditure categories to profile response |
| PreviewUserSeeder.php | Bug fix | Populate expenditure fields + spouse expenditure |
| PersonalAccountsService.php | Bug fix | Add Living Expenses + Include joint assets for spouse |
