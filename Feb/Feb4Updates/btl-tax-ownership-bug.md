# Bug Report - Feb 4, 2025

## BTL Property Tax: Ownership Percentage Not Applied for Joint Owners

### Status: ✅ FIXED

### Description

For jointly-owned Buy-to-Let properties, the rental income and Section 24 tax credit are not being split correctly between owners. The full amount is assigned to the primary owner, and joint owners receive nothing.

### Example

**Property Setup:**
- BTL Property owned jointly by David (60%) and Sarah (40%)
- Annual taxable rental income: £12,000
- Section 24 credit: £2,400

**Expected Behaviour:**
- David's tax calculation: £7,200 rental income, £1,440 Section 24 credit
- Sarah's tax calculation: £4,800 rental income, £960 Section 24 credit

**Actual Behaviour:**
- David's tax calculation: £7,200 rental income, £1,440 Section 24 credit ✅
- Sarah's tax calculation: £0 rental income, £0 Section 24 credit ❌

### Root Cause

**File:** `app/Services/UserProfile/UserProfileService.php`
**Method:** `calculateAnnualRentalIncome()`

```php
foreach ($user->properties as $property) {
    // Only gets properties where user_id = $user->id
    // Joint owners (joint_owner_id) are NEVER included
}
```

The `$user->properties` relationship only returns properties where `user_id = $user->id`. Properties where the user is listed as `joint_owner_id` are not included.

**File:** `app/Services/Property/PropertyService.php`
**Method:** `calculateTaxPosition()`

```php
$ownershipMultiplier = 1.0;
if ($property->ownership_type === 'joint' || $property->ownership_type === 'tenants_in_common') {
    $ownershipMultiplier = ((float) ($property->ownership_percentage ?? 50)) / 100;
}
```

This method always uses `ownership_percentage` (primary owner's share). It doesn't know which user is requesting the calculation, so it can't calculate the joint owner's share (`100 - ownership_percentage`).

### Files Affected

| File | Issue |
|------|-------|
| `app/Services/UserProfile/UserProfileService.php` | Doesn't query joint-owned properties |
| `app/Services/Property/PropertyService.php` | `calculateTaxPosition()` doesn't accept user context |

### Fix Required

1. **Modify `PropertyService::calculateTaxPosition()`** to accept optional `$userId` parameter
2. **Calculate correct ownership share** based on whether user is primary (`user_id`) or joint (`joint_owner_id`) owner
3. **Modify `UserProfileService::calculateAnnualRentalIncome()`** to also query properties where `joint_owner_id = $user->id`
4. **Pass user ID** to `calculateTaxPosition()` for correct share calculation

### Test Cases

1. Individual BTL ownership - should work as before
2. Joint BTL (50/50) - each owner gets half
3. Tenants in Common BTL (60/40) - each owner gets their percentage
4. Multiple joint BTL properties - all correctly split

---

## Resolution

### Changes Made

**1. `app/Services/Property/PropertyService.php`**

Modified `calculateTaxPosition()` to accept optional `$userId` parameter:

```php
public function calculateTaxPosition(Property $property, ?int $userId = null): array
{
    // ...
    if ($property->ownership_type === 'joint' || $property->ownership_type === 'tenants_in_common') {
        $primaryOwnerPercentage = (float) ($property->ownership_percentage ?? 50);

        if ($userId !== null) {
            if ($property->user_id === $userId) {
                // User is primary owner - use their percentage
                $ownershipMultiplier = $primaryOwnerPercentage / 100;
            } elseif ($property->joint_owner_id === $userId) {
                // User is joint owner - use remaining percentage
                $ownershipMultiplier = (100 - $primaryOwnerPercentage) / 100;
            }
        }
    }
    // ...
}
```

**2. `app/Services/UserProfile/UserProfileService.php`**

Modified `calculateAnnualRentalIncome()` to:
- Query properties where user is either `user_id` OR `joint_owner_id`
- Pass user ID to `calculateTaxPosition()` for correct share calculation

```php
$btlProperties = Property::where('property_type', 'buy_to_let')
    ->where(function ($query) use ($user) {
        $query->where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id);
    })
    ->with('mortgages')
    ->get();

foreach ($btlProperties as $property) {
    $taxPosition = $propertyService->calculateTaxPosition($property, $user->id);
    // ...
}
```

### Files to Deploy

| File | Change Type |
|------|-------------|
| `app/Services/Property/PropertyService.php` | Modified |
| `app/Services/UserProfile/UserProfileService.php` | Modified |

### Date Fixed

Feb 4, 2025
