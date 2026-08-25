# Account Linking Fixes - Sections 16 & 17 of AccLink.md

## Context
AccLink.md documents vulnerabilities and improvement recommendations for the spouse/account linking system. Several issues are already resolved (auth checks on spouse routes, LifeEvent already uses HasJointOwnership). This plan addresses the remaining fixable code issues. Feature enhancements (agent spouse awareness, configurable expenditure splits, household dashboard, divorce handling, notifications) are out of scope for this PR.

## Already Resolved (no changes needed)
- **16.2 / 17.1.1**: Auth checks already exist in `UserProfileController::getUserById()` and `updateSpouseExpenditure()` (both check `$currentUser->spouse_id !== $userId` -> 403)
- **16.7 / 17.2.5**: `LifeEvent` already uses `HasJointOwnership` trait (line 25)
- **16.5**: `IHTController` properly passes spouse data to `IHTCalculationService::calculate($user, $spouse, $dataSharingEnabled)`

## Fixes

### Fix 1: SpousePermission cleanup on unlink (16.1 / 17.1.2)
**File**: `app/Http/Controllers/Api/FamilyMembersController.php` - `destroy()` (line 572)
- Add `SpousePermission` deletion inside the spouse-unlinking block (after line 587, before clearing spouse_id)
- Also add cache clearing for both users (currently missing from destroy)
- Add imports: `use App\Models\SpousePermission;`, `use Illuminate\Support\Facades\DB;`, `use Illuminate\Support\Facades\Cache;`

### Fix 2: SpousePermission creation in new-account branch (discovered during exploration)
**File**: `app/Http/Controllers/Api/FamilyMembersController.php` - `handleSpouseCreation()` (line 398)
- After `$currentUser->save()` on line 398, add bidirectional `SpousePermission::updateOrCreate()` calls matching the existing-account pattern at lines 286-306

### Fix 3: Race condition protection (16.6 / 17.1.3)
**Files**:
- `app/Http/Controllers/Api/FamilyMembersController.php` - wrap existing-account branch and new-account branch in `DB::transaction()`. Inside the existing-account transaction, re-fetch the spouse user with `lockForUpdate()` and re-check `spouse_id` to prevent concurrent linking. If the check fails (spouse already linked by another user), the transaction returns null and the controller returns 422. Email sends stay outside transactions.
- `app/Services/Onboarding/OnboardingService.php` - wrap existing-account branch in `DB::transaction()`. Inside the transaction, re-fetch the spouse account with `User::lockForUpdate()` and re-check `spouse_id` before proceeding. If the spouse was already linked by another user, the transaction returns early with no changes.

### Fix 4: CashAccount joint_owner_id (16.4 / 17.2.4)
**New migration**: `database/migrations/2026_02_19_120000_add_joint_owner_id_to_cash_accounts_table.php`
- Add `unsignedBigInteger('joint_owner_id')->nullable()->after('ownership_percentage')` + index
- Pattern: matches existing asset tables (no FK constraint)

**File**: `app/Models/CashAccount.php`
- Add `use App\Traits\HasJointOwnership;` import
- Add `HasJointOwnership` trait usage
- Add `'joint_owner_id'` to `$fillable`
- Add `jointOwner()` BelongsTo relationship

### Fix 5: FamilyMember linked_user_id (16.8 / 17.2.7)
**New migration**: `database/migrations/2026_02_19_120001_add_linked_user_id_to_family_members_table.php`
- Add `unsignedBigInteger('linked_user_id')->nullable()->after('household_id')` with FK to users(id) onDelete set null + index

**File**: `app/Models/FamilyMember.php`
- Add `'linked_user_id'` to `$fillable`
- Add `linkedUser()` BelongsTo relationship

**File**: `app/Http/Controllers/Api/FamilyMembersController.php`
- Add `'linked_user_id' => $spouseUser->id` to all 5 FamilyMember::create() calls for spouse records (lines 232, 310, 331, 405, 426)

**File**: `app/Services/Onboarding/OnboardingService.php`
- Add `'linked_user_id' => $spouseAccount->id` to FamilyMember at line 346 and `'linked_user_id' => $user->id` at line 359

## Implementation Order
1. Create both migrations (Fix 4a, 5a)
2. Update models: CashAccount (Fix 4b), FamilyMember (Fix 5b)
3. Update FamilyMembersController: all of Fix 1, 2, 3a, 5c combined
4. Update OnboardingService: Fix 3b, 5d combined
5. Run migrations: `php artisan migrate`
6. Reseed: `php artisan db:seed`

## Files Changed
- `app/Http/Controllers/Api/FamilyMembersController.php`
- `app/Services/Onboarding/OnboardingService.php`
- `app/Models/CashAccount.php`
- `app/Models/FamilyMember.php`
- `database/migrations/2026_02_19_120000_add_joint_owner_id_to_cash_accounts_table.php` (new)
- `database/migrations/2026_02_19_120001_add_linked_user_id_to_family_members_table.php` (new)

## Verification
- Run `./dev.sh` and check for compile errors
- Test spouse linking flow via preview personas (young_family has active spouse)
- Verify `spouse_permissions` table has records for all linked preview users
- Verify `family_members` table has `linked_user_id` populated for spouse records
- Check `cash_accounts` table has `joint_owner_id` column
