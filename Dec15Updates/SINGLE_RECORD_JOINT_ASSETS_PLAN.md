# Single-Record Joint Asset Architecture

## Overview

Change joint asset storage from **reciprocal records** (2 records per joint asset) to **single record** (1 record with full value, linked to both owners).

**Scope:** Properties, Mortgages, Savings Accounts, Investment Accounts

**Migration:** Fresh dev database start (no data migration needed)

---

## Current vs New Architecture

| Aspect | Current (Reciprocal) | New (Single Record) |
|--------|---------------------|---------------------|
| Records | 2 per joint asset | 1 per joint asset |
| `current_value` | User's share (e.g., £160k) | Full value (e.g., £320k) |
| Linking | Address + postcode matching | `joint_owner_id` foreign key |
| Query | `where('user_id', $id)` | `where('user_id', $id)->orWhere('joint_owner_id', $id)` |
| Display | Divide by % to get full | Multiply by % to get share |

---

## Implementation Phases

### Phase 1: Create Ownership Share Trait

**File:** `app/Traits/CalculatesOwnershipShare.php` (new)

```php
trait CalculatesOwnershipShare
{
    protected function calculateUserShare(object $asset, int $userId): float
    {
        $fullValue = (float) ($asset->current_value ?? $asset->current_balance ?? 0);
        $ownershipType = $asset->ownership_type ?? 'individual';

        if ($ownershipType === 'individual') {
            return $asset->user_id === $userId ? $fullValue : 0.0;
        }

        // Joint/tenants_in_common ownership
        if ($asset->user_id === $userId) {
            return $fullValue * (($asset->ownership_percentage ?? 50) / 100);
        }

        if (($asset->joint_owner_id ?? null) === $userId) {
            return $fullValue * ((100 - ($asset->ownership_percentage ?? 50)) / 100);
        }

        return 0.0;
    }
}
```

---

### Phase 2: Update Controllers

#### 2.1 PropertyController.php

**Remove:**
- `createJointProperty()` method (lines 591-675)
- Reciprocal record creation in `store()` (line 201)
- Reciprocal record deletion in `destroy()` (lines 490-501)
- `handleJointPropertyUpdate()` reciprocal logic (lines 308-358)
- Value splitting in `store()` (lines 112-152)

**Update:**
- `store()`: Store FULL value directly (no splitting)
- `index()`: Add `->orWhere('joint_owner_id', $user->id)` to query
- `show()`: Include `is_primary_owner` and `user_share` in response
- `update()`: Update single record only
- `destroy()`: Delete single record only

#### 2.2 MortgageController.php

**Remove:**
- `createJointMortgage()` method (lines 390-445)
- Reciprocal record logic in `store()`, `update()`, `destroy()`
- Value splitting

**Update:**
- Store FULL outstanding balance
- Query pattern: `->orWhere('joint_owner_id', $user->id)`

#### 2.3 SavingsController.php

**Remove:**
- `createJointSavingsAccount()` method (lines 509-539)
- Reciprocal record logic in all account methods

**Update:**
- `index()`: Add `->orWhere('joint_owner_id', $user->id)`
- Store FULL balance

#### 2.4 InvestmentController.php

**Remove:**
- `createJointInvestmentAccount()` method (lines 681-721)
- Reciprocal record logic in all account methods

**Update:**
- `storeAccount()`: Store FULL value
- Query pattern: `->orWhere('joint_owner_id', $user->id)`

---

### Phase 3: Update Services

#### 3.1 CrossModuleAssetAggregator.php

**Update all methods** to use new query pattern and share calculation:

```php
public function calculatePropertyTotal(int $userId): float
{
    return Property::where('user_id', $userId)
        ->orWhere('joint_owner_id', $userId)
        ->get()
        ->sum(fn ($p) => $this->calculateUserShare($p, $userId));
}

public function getPropertyAssets(int $userId): Collection
{
    return Property::where('user_id', $userId)
        ->orWhere('joint_owner_id', $userId)
        ->get()
        ->map(function ($property) use ($userId) {
            return (object) [
                'asset_type' => 'property',
                'asset_name' => $property->address_line_1 ?: 'Property',
                'current_value' => $this->calculateUserShare($property, $userId),
                'full_value' => (float) $property->current_value,
                'ownership_percentage' => $property->ownership_percentage,
                'is_primary_owner' => $property->user_id === $userId,
                // ... other fields
            ];
        });
}
```

Apply same pattern to:
- `calculateInvestmentTotal()`
- `calculateCashTotal()`
- `calculateMortgageTotal()`
- `getInvestmentAssets()`
- `getSavingsAssets()`
- `getMortgages()`
- `getAssetBreakdown()`

#### 3.2 PropertyService.php

Update `calculateEquity()` to apply ownership percentage:
```php
public function calculateEquity(Property $property, int $userId): float
{
    $userPropertyShare = $this->calculateUserShare($property, $userId);
    $userMortgageShare = // calculate from mortgage
    return max(0, $userPropertyShare - $userMortgageShare);
}
```

#### 3.3 EstateAssetAggregatorService.php

Update `gatherUserAssets()` with same OR query pattern.

Add joint tenancy vs tenants in common handling for IHT:
- Joint tenancy: Passes to survivor (excluded from first death estate)
- Tenants in common: User's share included in estate

---

### Phase 4: Update Frontend Components

#### 4.1 PropertyCard.vue

**Before (divides to get full):**
```javascript
fullPropertyValue() {
  if (this.isSharedOwnership && this.property.ownership_percentage) {
    return this.property.current_value / (this.property.ownership_percentage / 100);
  }
  return this.property.current_value || 0;
}
```

**After (DB stores full, multiply for share):**
```javascript
fullPropertyValue() {
  return this.property.current_value || 0;
},
userShare() {
  if (this.isSharedOwnership && this.property.ownership_percentage) {
    return this.property.current_value * (this.property.ownership_percentage / 100);
  }
  return this.property.current_value || 0;
}
```

#### 4.2 PropertyDetail.vue & PropertyDetailInline.vue

Same inversion:
- `calculateFullPropertyValue()` → just return `current_value`
- Add `calculateUserShare()` → multiply by percentage
- Update mortgage methods similarly

#### 4.3 PropertyForm.vue

**Remove** conversion in `populateForm()` (lines 1592-1608):
```javascript
// REMOVE this block - DB now stores full value
if (isLinkedJointProperty) {
  const ownershipDecimal = this.property.ownership_percentage / 100;
  this.form.current_value = this.property.current_value / ownershipDecimal;
}
```

**Simplify to:**
```javascript
this.form.current_value = this.property.current_value || null;
```

#### 4.4 Savings Components

**CurrentSituation.vue:**
- `getFullBalance()` → return `current_balance` directly
- Add `getUserShare()` → multiply by percentage

#### 4.5 Investment Components

**AccountCard.vue:**
- Add joint ownership badge display
- Add `userShare` computed property

---

### Phase 5: Update Seeders

#### PreviewUserSeeder.php

**Update joint asset creation** to create single record:

```php
// Before: Created 2 records with 50% each
$userValue = $isJoint ? $totalValue * 0.5 : $totalValue;
Property::create(['current_value' => $userValue, ...]);
// Then created spouse record...

// After: Create 1 record with full value
Property::create([
    'user_id' => $user->id,
    'joint_owner_id' => $spouse->id,
    'current_value' => $totalValue,  // Full value
    'ownership_percentage' => 50,
    'ownership_type' => 'joint',
    // ...
]);
// No spouse record creation
```

Apply to: `createProperties()`, `createMortgages()`, `createSavingsAccounts()`, `createInvestmentAccounts()`

---

## API Response Changes

**Current:**
```json
{
  "id": 1,
  "current_value": 160000,
  "ownership_percentage": 50,
  "joint_owner_share": { "current_value": 160000 }
}
```

**New:**
```json
{
  "id": 1,
  "current_value": 320000,
  "ownership_percentage": 50,
  "user_share": 160000,
  "is_primary_owner": true
}
```

---

## Edit Permissions

- **Primary owner** (`user_id`): Full edit/delete rights
- **Joint owner** (`joint_owner_id`): View-only (or limited edit if needed later)

The `JointAccountLog` audit trail remains for tracking changes.

---

## Files to Modify

### Backend (Laravel)
1. `app/Traits/CalculatesOwnershipShare.php` (new)
2. `app/Http/Controllers/Api/PropertyController.php`
3. `app/Http/Controllers/Api/MortgageController.php`
4. `app/Http/Controllers/Api/SavingsController.php`
5. `app/Http/Controllers/Api/InvestmentController.php`
6. `app/Services/Shared/CrossModuleAssetAggregator.php`
7. `app/Services/Property/PropertyService.php`
8. `app/Services/Estate/EstateAssetAggregatorService.php`
9. `database/seeders/PreviewUserSeeder.php`

### Frontend (Vue.js)
1. `resources/js/components/NetWorth/PropertyCard.vue`
2. `resources/js/components/NetWorth/Property/PropertyDetail.vue`
3. `resources/js/components/NetWorth/Property/PropertyDetailInline.vue`
4. `resources/js/components/NetWorth/Property/PropertyForm.vue`
5. `resources/js/components/Savings/CurrentSituation.vue`
6. `resources/js/components/Investment/AccountCard.vue`

### Optional: Utility
7. `resources/js/utils/ownershipCalculations.js` (new helper)

---

## Testing Checklist

- [ ] Net worth shows correct user share (no double-counting)
- [ ] Joint assets appear in both users' dashboards
- [ ] Primary owner can edit; changes reflect for both users
- [ ] Delete removes asset from both dashboards
- [ ] IHT calculations use correct share amounts
- [ ] ISA accounts remain individual-only (validation)
- [ ] Forms display full value, save full value
- [ ] Property cards show "Full Value" and "Your Share"

---

## Execution Order

1. Create `CalculatesOwnershipShare` trait
2. Update `CrossModuleAssetAggregator` (central calculation service)
3. Update `PropertyController` (most complex, do first)
4. Update `MortgageController`
5. Update `SavingsController`
6. Update `InvestmentController`
7. Update `PropertyService` and `EstateAssetAggregatorService`
8. Update frontend components (PropertyCard, PropertyDetail, PropertyForm)
9. Update savings and investment frontend components
10. Update `PreviewUserSeeder`
11. Reset database and test with fresh data
