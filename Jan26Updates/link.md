# Linked Spouse Account Architecture Analysis

## Overview

This document traces the data flow for linked spouse accounts, identifying bugs in joint ownership handling.

---

## Account Linking Architecture

### Account Creation Flow

**Scenario: Chris creates account, adds Angela as spouse**

1. Chris registers → `users` record created (id=1)
2. Chris adds spouse via Family Members → creates:
   - `family_members` record: `user_id=1, relationship='spouse', first_name='Angela', email='angela@...'`
   - If "Create Login" checked: new `users` record for Angela (id=2)
   - Links: `users.id=1.spouse_id = 2` AND `users.id=2.spouse_id = 1`

**Critical Missing Step:** When Angela's user account is created, NO reciprocal `family_members` record is created for Angela pointing to Chris.

### Database Relationships

```
users (Chris, id=1)
├── spouse_id → 2 (Angela)
└── family_members
    └── relationship='spouse', first_name='Angela'

users (Angela, id=2)
├── spouse_id → 1 (Chris)
└── family_members
    └── [EMPTY - no family_member record for Chris!]
```

---

## Data Flow: Joint Asset Entry

### Scenario: Angela creates joint chattel (50/50 split)

**Database Record Created:**
```
chattels:
  id: 100
  user_id: 2 (Angela - creator/primary owner)
  joint_owner_id: 1 (Chris)
  ownership_type: 'joint'
  ownership_percentage: 50 (Angela's share)
  current_value: 10000 (FULL value)
```

### How Each User's Share is Calculated

The `CalculatesOwnershipShare` trait in `app/Traits/CalculatesOwnershipShare.php`:

```php
// When querying for Angela (user_id=2):
// - Found via: user_id = 2 ✓
// - Angela is primary owner (user_id matches)
// - Returns: 10000 * (50/100) = £5,000

// When querying for Chris (user_id=1):
// - Found via: joint_owner_id = 1 ✓
// - Chris is joint owner (joint_owner_id matches)
// - Returns: 10000 * ((100-50)/100) = £5,000
```

---

## Bug #1: Wealth Summary Shows Spouse as £0

### Symptoms
- When logged in as Angela: Her column shows £5,000, Chris column shows £0
- When logged in as Chris: His column shows £5,000, Angela column shows £0

### Data Flow Analysis

**NetWorthController.getOverview()** (lines 38-53):
```php
if ($user->spouse_id) {
    $spouse = $user->spouse;  // Gets spouse User model
    $spouseNetWorth = $this->netWorthService->getCachedNetWorth($spouse);
    // Returns spouse's breakdown including chattels
}
```

**NetWorthService.calculateChattelValue()** (lines 119-131):
```php
$chattels = Chattel::where('user_id', $userId)
    ->orWhere('joint_owner_id', $userId)
    ->get();  // ✓ Correctly queries both owner types

foreach ($chattels as $chattel) {
    $total += $this->calculateUserShare($chattel, $userId);  // ✓ Correct calculation
}
```

### Root Cause
The query logic appears correct. The issue may be:
1. **Caching** - `getCachedNetWorth()` may have stale data
2. **`spouse_id` not set bidirectionally** - Check if `users.id=2.spouse_id = 1` was set
3. **Permission check failing** - The `$user->spouse` relationship may not be loading

**Verification Steps:**
```sql
-- Check spouse_id is set both ways
SELECT id, first_name, spouse_id FROM users WHERE id IN (1, 2);

-- Check chattel ownership
SELECT id, user_id, joint_owner_id, ownership_percentage, current_value
FROM chattels WHERE user_id IN (1, 2) OR joint_owner_id IN (1, 2);
```

---

## Bug #2: Balance Sheet Shows Both Columns as Same User

### Symptoms
When logged in as Chris: Both column headers show "Chris Chris" instead of "Chris" and "Angela"

### Data Flow Analysis

**BalanceSheetTab.vue** (lines 235-254):
```javascript
const user = computed(() => store.getters['userProfile/user']);
const spouse = computed(() => store.getters['userProfile/spouse']);

const userName = computed(() => {
  // Gets name from auth/user getter
  return `${user.value.first_name} ${user.value.last_name}`.trim();
});

const spouseName = computed(() => {
  // Gets name from userProfile/spouse getter
  return `${spouse.value.first_name} ${spouse.value.last_name}`.trim();
});
```

**userProfile.js spouse getter** (lines 34-70):
```javascript
spouse: (state, getters, rootState, rootGetters) => {
  const currentUser = rootGetters['auth/user'];

  if (!currentUser || !currentUser.spouse_id) {
    return null;
  }

  // Try to find spouse in family members first
  const spouseInFamily = state.familyMembers.find(
    member => member.relationship === 'spouse'
  );

  if (spouseInFamily) {
    return { ...spouseInFamily, id: currentUser.spouse_id };
  }

  // Fallback to user.spouse relationship
  if (currentUser.spouse) {
    return { ... };
  }

  return { id: currentUser.spouse_id, first_name: '', last_name: '' };
}
```

### Root Cause: Missing Reciprocal Family Member Record

When Chris creates Angela as spouse:
- A `family_members` record is created for Chris with `relationship='spouse'`
- Chris → spouse getter finds Angela in family_members ✓

When Angela logs in:
- Angela's `family_members` is EMPTY (no record created for her)
- Angela → spouse getter falls through to `currentUser.spouse` or the fallback
- If `currentUser.spouse` is not eagerly loaded, it may use the fallback with empty names
- OR if the auth store has incorrect data, it may return wrong user

**The fallback path returns:**
```javascript
return {
  id: currentUser.spouse_id,  // Correct ID (1 = Chris)
  first_name: '',             // EMPTY - no data source!
  last_name: '',
};
```

### Fix Required
When creating a spouse account, create a reciprocal `family_members` record:
```php
// In FamilyMembersController or SpouseService
// After creating spouse user account:
FamilyMember::create([
    'user_id' => $spouse->id,        // Angela's user ID
    'first_name' => $originalUser->first_name,  // Chris's first name
    'last_name' => $originalUser->surname,       // Chris's last name
    'relationship' => 'spouse',
    'date_of_birth' => $originalUser->date_of_birth,
]);
```

---

## Bug #3: IHT Calculator Missing Joint Chattels/Business Interests

### Symptoms
- IHT Calculator shows spouse's assets but not joint chattels created by spouse
- Chris's IHT calculation doesn't include joint chattels where he's `joint_owner_id`

### Data Flow Analysis

**EstateAssetAggregatorService.gatherUserAssets()** (lines 104-155):

```php
// Business Interests - BUG: Missing joint_owner_id query!
$businessInterests = BusinessInterest::where('user_id', $user->id)->get();
// Should be:
// $businessInterests = BusinessInterest::where('user_id', $user->id)
//     ->orWhere('joint_owner_id', $user->id)->get();

// Chattels - BUG: Missing joint_owner_id query!
$chattels = Chattel::where('user_id', $user->id)->get();
// Should be:
// $chattels = Chattel::where('user_id', $user->id)
//     ->orWhere('joint_owner_id', $user->id)->get();
```

### Root Cause
`EstateAssetAggregatorService` does NOT use the single-record joint ownership pattern for:
- `BusinessInterest` (line 104)
- `Chattel` (line 139)

It only queries `user_id`, missing assets where the user is `joint_owner_id`.

**Compare with NetWorthService** (which works correctly):
```php
// NetWorthService.calculateChattelValue() - CORRECT
$chattels = Chattel::where('user_id', $userId)
    ->orWhere('joint_owner_id', $userId)  // ✓ Includes joint ownership
    ->get();
```

### Additional Bug: Not Using calculateUserShare Trait

The EstateAssetAggregatorService manually calculates ownership:
```php
$userValue = $chattel->current_value * ($ownershipPercentage / 100);
```

This is incorrect when the user is the joint owner (should use `100 - percentage`).

Should use the trait:
```php
$userValue = $this->calculateUserShare($chattel, $user->id);
```

---

## Bug #4: Asset Reordering on Refresh

### Symptoms
> "when I refresh Chris account, Angela's assets are now on top, it has re-ordered them"

### Root Cause
The `gatherUserAssets()` method concatenates assets without sorting:
```php
return $assets
    ->concat($investmentAssets)
    ->concat($propertyAssets)
    // ... etc
```

When joint assets are added, they're appended. On refresh, if the query order changes (e.g., database auto-increment IDs), assets may appear in different order.

### Fix
Add consistent sorting after aggregation:
```php
return $allAssets->sortBy('asset_name')->values();
```

---

## Summary of Required Fixes

| Bug | Location | Fix | Status |
|-----|----------|-----|--------|
| IHT missing joint chattels | `EstateAssetAggregatorService` line 141-143 | Add `->orWhere('joint_owner_id', $user->id)` + use trait | **FIXED** |
| IHT missing joint business | `EstateAssetAggregatorService` line 104-106 | Add `->orWhere('joint_owner_id', $user->id)` + use trait | **FIXED** |
| Missing reciprocal family_member | `FamilyMembersController` | Already implemented (lines 326-343, 421-438) | **OK** |
| Balance Sheet showing wrong name | Data issue | Verify reciprocal family_member exists in database | **VERIFY DATA** |
| Wealth Summary showing 0 | Data/Cache issue | Clear net worth cache after adding joint assets | **VERIFY DATA** |

## Fixes Applied

### EstateAssetAggregatorService.php

**Before (Broken):**
```php
// Business Interests - Missing joint_owner_id!
$businessInterests = BusinessInterest::where('user_id', $user->id)->get();
$businessAssets = $businessInterests->map(function ($business) use ($user) {
    $ownershipPercentage = $business->ownership_percentage ?? 100;
    $userValue = $business->current_valuation * ($ownershipPercentage / 100);  // Wrong calculation for joint owner!
    // ...
});

// Chattels - Missing joint_owner_id!
$chattels = Chattel::where('user_id', $user->id)->get();
$chattelAssets = $chattels->map(function ($chattel) use ($user) {
    $ownershipPercentage = $chattel->ownership_percentage ?? 100;
    $userValue = $chattel->current_value * ($ownershipPercentage / 100);  // Wrong calculation for joint owner!
    // ...
});
```

**After (Fixed):**
```php
// Business Interests - Single-record pattern with joint ownership support
$businessInterests = BusinessInterest::where('user_id', $user->id)
    ->orWhere('joint_owner_id', $user->id)  // ADDED
    ->get();
$businessAssets = $businessInterests->map(function ($business) use ($user) {
    $userValue = $this->calculateUserShare($business, $user->id);  // USES TRAIT
    // ...
    'is_primary_owner' => $this->isPrimaryOwner($business, $user->id),  // USES TRAIT
});

// Chattels - Single-record pattern with joint ownership support
$chattels = Chattel::where('user_id', $user->id)
    ->orWhere('joint_owner_id', $user->id)  // ADDED
    ->get();
$chattelAssets = $chattels->map(function ($chattel) use ($user) {
    $userValue = $this->calculateUserShare($chattel, $user->id);  // USES TRAIT
    // ...
    'ownership_type' => $chattel->ownership_type ?? 'individual',  // ADDED
    'is_primary_owner' => $this->isPrimaryOwner($chattel, $user->id),  // USES TRAIT
});
```

---

## Files Modified

### Backend
1. **`app/Services/Estate/EstateAssetAggregatorService.php`** - **FIXED**
   - Added `->orWhere('joint_owner_id', $user->id)` for chattels and business interests
   - Changed to use `$this->calculateUserShare()` trait for correct share calculation
   - Added `ownership_type` field to chattel assets
   - Added `is_primary_owner` using trait method

2. `app/Http/Controllers/Api/FamilyMembersController.php` - **ALREADY CORRECT**
   - Reciprocal family_member records ARE being created (verified lines 326-343, 421-438)

---

## Data Verification Steps

If issues persist after deploying the code fix, verify data in database:

### Check Spouse Linkage
```sql
-- Verify spouse_id is set bidirectionally
SELECT id, first_name, surname, email, spouse_id
FROM users
WHERE email IN ('chris@example.com', 'angela@example.com');

-- Both should have spouse_id pointing to each other
```

### Check Family Members
```sql
-- Chris should have Angela as family member with relationship='spouse'
SELECT * FROM family_members WHERE user_id = [CHRIS_ID] AND relationship = 'spouse';

-- Angela should have Chris as family member with relationship='spouse'
SELECT * FROM family_members WHERE user_id = [ANGELA_ID] AND relationship = 'spouse';

-- If missing, create it:
INSERT INTO family_members (user_id, household_id, first_name, last_name, relationship, name, created_at, updated_at)
VALUES ([ANGELA_ID], [ANGELA_HOUSEHOLD_ID], 'Chris', 'LastName', 'spouse', 'Chris LastName', NOW(), NOW());
```

### Check Chattel Ownership
```sql
-- View chattels with ownership details
SELECT id, name, user_id, joint_owner_id, ownership_type, ownership_percentage, current_value
FROM chattels
WHERE user_id IN ([CHRIS_ID], [ANGELA_ID]) OR joint_owner_id IN ([CHRIS_ID], [ANGELA_ID]);
```

### Clear Caches
```bash
# On server after data fixes
php artisan cache:clear

# Or specifically for net worth
php artisan tinker
>>> \Cache::forget("net_worth_{CHRIS_ID}");
>>> \Cache::forget("net_worth_{ANGELA_ID}");
```

---

## Testing Checklist

After fixes, verify:

1. [ ] Angela creates joint chattel → appears in Chris's Wealth Summary with correct share
2. [ ] Chris creates joint chattel → appears in Angela's Wealth Summary with correct share
3. [ ] Angela creates joint chattel → appears in Chris's IHT Calculator with correct share
4. [ ] Balance Sheet headers show correct names for both users
5. [ ] Asset order remains consistent after refresh
6. [ ] Cache invalidation works when joint assets are modified

---

## Deployment Notes

**Files to upload:**
```
app/Services/Estate/EstateAssetAggregatorService.php
```

**Rebuild Required:** NO (PHP file only)

**After Upload:**
```bash
php artisan cache:clear
```
