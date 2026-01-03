# Spouse Creation Fix - January 2, 2026

## Issue Reported

During onboarding, adding a spouse as a family member failed with the error:
```
Failed to save family member
```

The error occurred when trying to create a spouse with an email address (which triggers spouse account creation/linking).

## Root Cause Analysis

### Phase 1: Investigation

Traced the error from frontend to backend:
1. `FamilyInfoStep.vue` → `FamilyMemberFormModal.vue` → `familyMembersService.js` → `FamilyMembersController.php`

Found the bug in `FamilyMembersController.php` at lines 284-306 where `SpousePermission` records are created.

### The Bug

The code was trying to set columns that **don't exist** in the `spouse_permissions` table:

```php
// BROKEN CODE - these columns don't exist
\App\Models\SpousePermission::updateOrCreate(
    [
        'user_id' => $currentUser->id,
        'spouse_id' => $spouseUser->id,
    ],
    [
        'can_view_data' => true,        // WRONG - column doesn't exist
        'can_edit_data' => false,       // WRONG - column doesn't exist
        'permission_granted_at' => now(), // WRONG - column doesn't exist
    ]
);
```

### Actual Table Schema

The `spouse_permissions` table has these columns:
- `id`
- `user_id`
- `spouse_id`
- `status` (enum: 'pending', 'accepted', 'rejected')
- `requested_at`
- `responded_at`
- `created_at`
- `updated_at`

The `SpousePermission` model's `$fillable` array confirms:
```php
protected $fillable = [
    'user_id',
    'spouse_id',
    'status',
    'requested_at',
    'responded_at',
];
```

## Solution

Updated both files that create `SpousePermission` records to use the correct columns:

```php
// FIXED CODE - uses correct columns
\App\Models\SpousePermission::updateOrCreate(
    [
        'user_id' => $currentUser->id,
        'spouse_id' => $spouseUser->id,
    ],
    [
        'status' => 'accepted',
        'responded_at' => now(),
    ]
);
```

## Files Changed

### 1. `app/Http/Controllers/Api/FamilyMembersController.php`

Lines 284-306 and 308-330 - Fixed both SpousePermission::updateOrCreate calls (one for each direction of the relationship).

### 2. `app/Services/Onboarding/OnboardingService.php`

Lines 307-330 - Same fix applied here (spouse creation during onboarding flow).

### 3. `resources/js/components/Onboarding/steps/FamilyInfoStep.vue`

Improved error handling to show actual API error messages instead of generic message:

```javascript
// BEFORE
} catch (err) {
  console.error('Failed to save family member:', err);
  error.value = 'Failed to save family member. Please try again.';
}

// AFTER
} catch (err) {
  console.error('Failed to save family member:', err);
  const errorMsg = err.response?.data?.message || err.message || 'Unknown error';
  error.value = `Failed to save family member: ${errorMsg}`;
}
```

## Risk Profile Save Investigation

Also investigated reported issue with Risk Profile save button not working.

**Finding**: No code bug found. The risk profile API and controller are correctly implemented:
- `RiskProfilePage.vue` - Correct event handling and API calls
- `riskService.js` - Correct API endpoint (`/api/investment/risk/profile`)
- `RiskPreferenceController.php` - Correct store/update logic

The issue during testing was browser extension connectivity problems, not a code bug.

## Deployment

### Files to Upload

**Backend:**
- `app/Http/Controllers/Api/FamilyMembersController.php`
- `app/Services/Onboarding/OnboardingService.php`

**Frontend:**
- `public/build/` (entire folder - recompiled with `npm run build`)

### No Migration Required

No database changes - the fix only corrects the column names used in the code to match the existing schema.

## Testing

After deployment, test by:
1. Start fresh registration or use preview persona
2. Go to onboarding → Family & Dependents step
3. Click "Add Family Member"
4. Select "Spouse" relationship
5. Enter spouse details including email
6. Save - should succeed without error
7. Spouse account should be created/linked

## Verification Commands

```bash
# Check SpousePermission table has correct records
php artisan tinker
>>> \App\Models\SpousePermission::all()->toArray();

# Should show records with 'status', 'responded_at' columns populated
```
