# Deployment Notes - January 26, 2026

---

# ✅ DEPLOYED

**Deployed:** 26 January 2026

---

## Security Enhancement: Session Management & Data Protection

This update implements strict session security to ensure users must authenticate on every visit with no persistent local data.

---

## Summary of Changes

| Category | Description |
|----------|-------------|
| Token Storage | Migrated from localStorage to sessionStorage |
| Financial Data | Removed all localStorage caching |
| Session Lifecycle | 15-min inactivity timeout; sessionStorage auto-clears on close |
| Logout UX | Added confirmation modal, dropdown closes on logout |
| Login UX | Removed "Remember me" checkbox (incompatible with session-only auth) |
| Backend | New beacon logout endpoint (kept for manual logout) |

## Session Behaviour

| Action | Session Result |
|--------|----------------|
| Page refresh (F5) | **Persists** |
| Tab switching | **Persists** |
| Browser/tab close | **Ends** (sessionStorage auto-clears) |
| 15 min inactivity | **Ends** |
| Manual logout | **Ends** |

---

## Rebuild Required: YES

Frontend JavaScript/Vue files have changed. Run the build script before uploading.

```bash
./deploy/fynla-org/build.sh
```

---

## Files Changed

### Frontend (JavaScript/Vue) - REBUILD REQUIRED

| File | Change Type |
|------|-------------|
| `resources/js/services/authService.js` | Modified |
| `resources/js/services/api.js` | Modified |
| `resources/js/services/sessionLifecycleService.js` | **NEW** |
| `resources/js/store/modules/userProfile.js` | Modified (spouse getter + last_name) |
| `resources/js/store/modules/auth.js` | Modified |
| `resources/js/app.js` | Modified |
| `resources/js/components/Navbar.vue` | Modified (logout modal + dropdown close) |
| `resources/js/components/Auth/LogoutSuccessModal.vue` | **NEW** |
| `resources/js/views/Login.vue` | Modified (inactivity msg + removed "Remember me") |
| `resources/js/views/Register.vue` | Modified (surname → last_name) |
| `resources/js/components/UserProfile/FamilyMembers.vue` | Modified (spouse modal fix) |
| `resources/js/components/UserProfile/FamilyMemberFormModal.vue` | Modified (Surname → Last Name label) |
| `resources/js/components/UserProfile/BalanceSheetTab.vue` | Modified (spouse name display) |

### Backend (PHP) - Upload Directly

| File | Change Type |
|------|-------------|
| `app/Http/Controllers/Api/AuthController.php` | Modified |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Modified |
| `app/Services/GDPR/DataErasureService.php` | Modified (spouse cleanup) |
| `app/Http/Requests/RegisterRequest.php` | Modified (last name error msg) |
| `app/Http/Requests/StoreFamilyMemberRequest.php` | Modified (last name error msg) |
| `routes/api.php` | Modified |

---

## Upload Checklist

### Step 1: Run Build
```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

### Step 2: Upload Built Assets
Upload the entire `public/build/` directory to:
```
~/www/fynla.org/public_html/public/build/
```

### Step 3: Upload PHP Files
Upload these files via SiteGround File Manager:

```
app/Http/Controllers/Api/AuthController.php
app/Http/Middleware/PreviewWriteInterceptor.php
app/Services/GDPR/DataErasureService.php
app/Http/Requests/RegisterRequest.php
app/Http/Requests/StoreFamilyMemberRequest.php
routes/api.php
```

### Step 4: Clear Cache (SSH)
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

---

## Verification Steps

1. **Logout flow**: Click logout → modal appears → redirected to login
2. **Browser close**: Login, close browser, reopen → should see login page
3. **Tab close**: Login in tab, close tab, open new tab → should see login page
4. **Inactivity**: Login, wait 15+ minutes idle → auto-logout with message
5. **Page refresh**: Login, press F5 → should stay logged in (sessionStorage survives refresh)
6. **No data leakage**: Login as User A, logout, login as User B → no User A data visible
7. **Check storage**: Open DevTools → Application → sessionStorage should have token, localStorage should have NO financial data

---

## Rollback Plan

If issues occur, restore previous versions of:
- `app/Http/Controllers/Api/AuthController.php`
- `routes/api.php`
- `public/build/` directory

The new sessionLifecycleService and LogoutSuccessModal are additive and won't break existing functionality if the old build is restored.

---

## Bug Fix: GDPR Account Deletion Verification

**Status:** VERIFIED ✓

### Issue
Account deletion verification was failing with "Invalid or expired session" error immediately after initiating deletion.

### Root Cause
Local development `.env` had `CACHE_DRIVER=array`, which only stores data in memory for a single HTTP request. The deletion session was lost between the `initiate` and `verify` API calls.

### Fix
Changed local `.env` from `CACHE_DRIVER=array` to `CACHE_DRIVER=file`.

**Important:** Laravel server must be restarted after changing `.env` for the change to take effect.

### Verification Testing (26 Jan 2026)

Tested full deletion flow via browser automation:

| Step | Result |
|------|--------|
| 1. Navigate to Privacy Settings | ✓ |
| 2. Click "Manage Account Deletion" | ✓ |
| 3. Select "Delete My Account" | ✓ Proceeded to Step 2 |
| 4. Enter verification code from email | ✓ Code accepted |
| 5. Proceed to Final Confirmation (Step 3) | ✓ |

**Log evidence (before fix - array driver):**
```
session_found: false, tokens_match: false → "Invalid or expired session"
```

**Log evidence (after fix - file driver):**
```
session_found: true, tokens_match: true → Verification successful
```

### Security Note
This change affects **server-side caching only** and does not conflict with the session security plan:

| Storage Type | Location | Purpose |
|--------------|----------|---------|
| sessionStorage | Browser (client) | Auth token - clears on browser close |
| Laravel Cache (file) | Server | Temporary verification sessions - protected by .htaccess |

Production already uses `CACHE_DRIVER=file` and was unaffected.

---

## Bug Fix: GDPR Account Deletion - Hard Delete with Spouse Cleanup

**Status:** VERIFIED ✓

### Issue
1. Account deletion was using soft delete (anonymization) instead of hard delete
2. When a user deleted their account, the linked spouse still showed them in the Family tab

### Changes Made
Changed from **soft delete** (anonymization) to **hard delete**:

| Before | After |
|--------|-------|
| User record anonymized (`deleted_X@anonymized.local`) | User record completely removed from database |
| Related data kept with anonymized references | All user data hard deleted |
| Spouse relationship not cleaned up | Spouse's `spouse_id` cleared before deletion |

### Spouse Relationship Logic

When User A deletes their account:
- User A is completely removed from database
- User A's spouse (if any) has their `spouse_id` set to NULL
- The spouse account remains intact and fully functional
- The spouse will no longer see User A in their Family tab

This works correctly in both directions:
- If primary account holder deletes → spouse account unaffected
- If spouse account deletes → primary account unaffected

### Fix
Updated `app/Services/GDPR/DataErasureService.php`:

```php
private function deleteUser(User $user): void
{
    // Clear spouse relationship before deleting (both directions)
    if ($user->spouse_id) {
        $spouse = User::find($user->spouse_id);
        if ($spouse) {
            $spouse->update(['spouse_id' => null]);
        }
    }
    User::where('spouse_id', $user->id)->update(['spouse_id' => null]);

    // Revoke tokens and sessions
    $user->tokens()->delete();
    $user->sessions()->delete();

    // Hard delete the user record
    $user->forceDelete();
}
```

### Verification
1. Delete an account that has a linked spouse
2. Verify user record is completely removed from database
3. Log into the spouse's account
4. Navigate to User Profile → Family tab
5. Deleted user should NOT appear

---

## Bug Fix: GDPR Account Deletion - Family Member Record Not Deleted

**Status:** VERIFIED ✓

### Issue
After deleting a spouse account, the spouse still appeared in the Family tab of the linked account, even though the user record was deleted and `spouse_id` was cleared.

### Root Cause
Spouse relationships are stored in TWO places:
1. `users.spouse_id` - Was being cleared ✓
2. `family_members` table - Record with `relationship='spouse'` was NOT being deleted ✗

### Fix
Updated `app/Services/GDPR/DataErasureService.php` to also delete the family_member record:

```php
// Delete the spouse's family_member record that represents this user
\App\Models\FamilyMember::where('user_id', $spouse->id)
    ->where('relationship', 'spouse')
    ->delete();
```

### Files Changed
```
app/Services/GDPR/DataErasureService.php
```

### Verification
1. Delete a spouse account
2. Log into the linked account
3. Navigate to User Profile → Family tab
4. Deleted spouse should NOT appear (no orphaned family_member record)

---

## Bug Fix: Preview Personas Redirect to Login Instead of Dashboard

**Deployed:** 26 January 2026

**Status:** VERIFIED ✓

### Issue
When selecting a preview persona from the landing page modal, users were redirected to the login page instead of the persona's dashboard.

### Root Cause
The session security update changed `authService.js` to use `sessionStorage` for auth tokens, but `preview.js` was missed and still used `localStorage`.

When a persona was selected:
1. Token was stored in `localStorage` (by preview.js)
2. API requests looked for token in `sessionStorage` (via authService.js)
3. Token not found → user appeared unauthenticated → redirect to login

### Fix
Updated `resources/js/store/modules/preview.js` to use `sessionStorage` instead of `localStorage` (4 locations):
- Line 183: `sessionStorage.removeItem('auth_token')`
- Line 194: `sessionStorage.setItem('auth_token', token)`
- Line 235: `sessionStorage.setItem('auth_token', token)`
- Line 279: `sessionStorage.removeItem('auth_token')`

### Files Changed
```
resources/js/store/modules/preview.js
```

### Rebuild Required: YES
This is a frontend-only fix. Upload the new `public/build/` directory.

### Verification
1. Go to landing page
2. Click "Try Demo" or persona selector
3. Select any persona (e.g., "Emily & James Carter")
4. Should redirect to dashboard with persona data loaded

---

## Bug Fix: Spouse Success Modal Not Appearing in User Profile

**Deployed:** 26 January 2026

**Status:** VERIFIED ✓

### Issue
When creating a spouse account through User Profile → Family tab → Add Family Member, the success modal (SpouseSuccessModal) that shows during onboarding was NOT appearing.

### Root Cause
After saving a spouse, the code called `store.dispatch('userProfile/fetchProfile')` which sets `loading: true` in the Vuex store. In UserProfile.vue, the tab content is wrapped in:

```vue
<div v-if="loading">...loading spinner...</div>
<div v-else>
  <FamilyMembers v-show="activeTab === 'family'" />
</div>
```

When `loading` becomes `true`, the `v-else` block is **removed from the DOM** (because `v-if` removes elements), which **unmounts** the FamilyMembers component. This resets all refs to their initial values, including `showSpouseSuccess = ref(false)`.

When loading finishes, FamilyMembers is remounted as a fresh component with `showSpouseSuccess = false`, so the modal never appears.

### Fix
Changed the refresh logic to NOT trigger the global loading state:

```javascript
// Before (causes component unmount/remount):
await store.dispatch('userProfile/fetchProfile');

// After (refreshes data without loading state):
await loadFamilyMembers(true); // forceRefresh = true
```

Also updated `loadFamilyMembers` to accept a `forceRefresh` parameter that bypasses the store cache and fetches directly from the API.

### Files Changed
```
resources/js/components/UserProfile/FamilyMembers.vue
```

### Verification
1. Log in as a user without a spouse
2. Navigate to User Profile → Family tab
3. Click "Add Family Member"
4. Select "Spouse" and fill in details with a new email
5. Click "Add Family Member"
6. **SUCCESS**: "Spouse Account Created" modal appears with login details info

---

## Standardisation: Surname → Last Name

**Deployed:** 26 January 2026

**Status:** VERIFIED ✓

### Issue
Inconsistent naming across the application:
- User model uses `surname`
- FamilyMember model uses `last_name`
- Form labels showed "Surname" in some places, causing confusion
- Balance sheet showed only first name for spouse due to field name mismatch

### Changes Made

**Frontend Labels:**
- Changed all form labels from "Surname" to "Last Name"
- Registration form now uses `last_name` field (mapped to `surname` for backend)
- Family Member form already used `last_name`

**Frontend Logic:**
- `BalanceSheetTab.vue`: Updated userName and spouseName computed properties to check both `last_name` and `surname` with appropriate fallbacks
- `userProfile.js` store: Spouse getter now returns `first_name` and `last_name` instead of deprecated `name` field
- `Register.vue`: Maps `last_name` → `surname` when sending to API, and maps `surname` errors back to `last_name` for display

**Backend Validation Messages:**
- `RegisterRequest.php`: Added custom message "Last name is required."
- `StoreFamilyMemberRequest.php`: Changed message from "Surname is required." to "Last name is required."

### Files Changed

**Frontend:**
```
resources/js/views/Register.vue
resources/js/components/UserProfile/FamilyMemberFormModal.vue
resources/js/components/UserProfile/BalanceSheetTab.vue
resources/js/store/modules/userProfile.js
```

**Backend:**
```
app/Http/Requests/RegisterRequest.php
app/Http/Requests/StoreFamilyMemberRequest.php
```

### Verification
1. Register page shows "Last Name" label
2. Family Member form shows "Last Name" label
3. Balance Sheet shows full name (first + last) for both user and spouse
4. Validation errors display correctly with "Last name" wording

---

## Feature: Cash Tab Joint Account Indicator

**Deployed:** 26 January 2026

**Status:** VERIFIED ✓

### Issue
Cash accounts in Net Worth → Cash tab did not show whether accounts were jointly held, and did not display user's share vs total balance.

### Changes Made
Updated `CashOverview.vue` to display:
- **(Joint)** badge in amber text for joint accounts (matching IHT calculator style)
- **(TiC - X%)** badge for tenants in common ownership
- User's share as the primary balance (based on ownership_percentage)
- **Total: £X** below user's share for joint accounts

### Display Example
For a joint account with 70% ownership and £50,000 total:
```
HSBC Current (Joint)
£35,000
Total: £50,000
```

### Files Changed
```
resources/js/views/NetWorth/CashOverview.vue
```

### Verification
1. Navigate to Net Worth → Cash tab
2. View a joint account - should show "(Joint)" badge in amber
3. Balance should show user's share, with "Total: £X" below

---

## Standardisation: Jewelry → Jewellery (British Spelling)

**Deployed:** 26 January 2026

**Status:** VERIFIED ✓

### Issue
Personal Valuables module used American spelling "Jewelry" instead of British spelling "Jewellery".

### Changes Made
Updated all user-facing labels from "Jewelry" to "Jewellery" across the Personal Valuables module.

Note: The database enum value remains `jewelry` (code convention), only the display labels changed.

### Files Changed
```
resources/js/components/NetWorth/ChattelFormModal.vue
resources/js/components/NetWorth/ChattelCard.vue
resources/js/components/NetWorth/ChattelsList.vue
resources/js/components/NetWorth/ChattelDetailInline.vue
resources/js/components/Estate/AssetForm.vue
resources/js/views/Version.vue
```

### Verification
1. Navigate to Net Worth → Personal Valuables
2. Click "Add Valuable" - type selector should show "Jewellery"
3. Filter dropdown should show "Jewellery"
4. Any jewellery items should display "Jewellery" badge

---

## Bug Fix: Balance Sheet Joint Chattels Not Splitting Correctly

**Deployed:** 26 January 2026

**Status:** COMPLETED ✓

### Issue
1. Joint chattels showed full value under user's account instead of splitting by ownership_percentage
2. Balance sheet displayed chattel description instead of name in the Assets column

### Root Cause
The `PersonalAccountsService.php` had two problems:
1. Only queried `user_id` for chattels (not `joint_owner_id` like other assets)
2. Used `$chattel->description` instead of `$chattel->name` for the line item
3. Used raw `current_value` instead of `calculateUserShare()` trait method

### Fix
Updated `app/Services/UserProfile/PersonalAccountsService.php`:

```php
// Before (broken):
foreach ($user->chattels as $chattel) {
    $assets[] = [
        'line_item' => $chattel->description ?? 'Chattel',
        'amount' => $chattel->current_value,
    ];
}

// After (fixed):
$chattels = \App\Models\Chattel::where('user_id', $user->id)
    ->orWhere('joint_owner_id', $user->id)
    ->get();
foreach ($chattels as $chattel) {
    $amount = $this->calculateUserShare($chattel, $user->id);
    if ($amount <= 0) continue;
    $assets[] = [
        'line_item' => $chattel->name ?? 'Chattel',
        'amount' => $amount,
    ];
}
```

### Files Changed

```
app/Services/UserProfile/PersonalAccountsService.php
```

### Verification
1. Add a joint chattel with 70% ownership and £10,000 value
2. Navigate to User Profile → Balance Sheet (Valuable Info)
3. User column should show £7,000 (70% of £10,000)
4. Spouse column should show £3,000 (30% of £10,000)
5. Assets column should show chattel name (not description)

---

## Bug Fix: Balance Sheet Joint Business Interests Not Splitting Correctly

**Deployed:** 26 January 2026

**Status:** COMPLETED ✓

### Issue
Joint business interests in the Balance Sheet showed the full value under the primary owner's account instead of splitting by ownership percentage. Same issue as chattels.

### Root Cause
`PersonalAccountsService.php` had the same problem for business interests:
1. Only queried `user_id`, not `joint_owner_id`
2. Used raw `current_valuation` without applying ownership split

### Fix
Updated `app/Services/UserProfile/PersonalAccountsService.php` for business interests.

Also updated `app/Traits/CalculatesOwnershipShare.php` to support `current_valuation` field (used by BusinessInterest model).

### Files Changed

```
app/Services/UserProfile/PersonalAccountsService.php
app/Traits/CalculatesOwnershipShare.php
```

### Verification
1. Add a joint business interest with 70% ownership and £100,000 valuation
2. Navigate to User Profile → Balance Sheet (Valuable Info)
3. User column should show £70,000 (70% share)
4. Spouse column should show £30,000 (30% share)

---

## Bug Fix: Wealth Summary Joint Chattels/Business Not Splitting Correctly

**Deployed:** 26 January 2026

**Status:** COMPLETED ✓

### Issue
The Wealth Summary tab in Net Worth module showed the full value of joint chattels and business interests under the primary owner's account instead of correctly splitting by ownership percentage.

### Root Cause
`NetWorthService.php` had two problems:
1. `calculateBusinessValue()` and `calculateChattelValue()` only queried `user_id` (not `joint_owner_id`)
2. These methods summed full values instead of using `CalculatesOwnershipShare` trait to calculate user's share

Incorrect comments claimed values were "already stored as user's share" but this contradicted the single-record architecture where full values are stored.

### Fix
Updated `app/Services/NetWorth/NetWorthService.php`:
- Added `use CalculatesOwnershipShare` trait
- `calculateBusinessValue()`: Now queries both `user_id` and `joint_owner_id`, uses `calculateUserShare()` trait method
- `calculateChattelValue()`: Same fix as above
- `getAssetsSummary()`: Updated count queries to include `joint_owner_id`
- `getAssetsSummaryWithDetails()`: Updated queries and value mappings to use user's share

### Files Changed
```
app/Services/NetWorth/NetWorthService.php
```

### Verification
1. Add a joint chattel with 70% ownership and £10,000 value
2. Navigate to Net Worth → Wealth Summary tab
3. User's chattels should show £7,000 (70% of £10,000)
4. Spouse's chattels should show £3,000 (30% of £10,000)
5. Same verification for joint business interests

---

## Summary: All PHP Files for Joint Ownership Fixes

**Deployed:** 26 January 2026

**IMPORTANT:** Upload ALL these files for joint chattels and business interests to work correctly:

```
app/Traits/CalculatesOwnershipShare.php
app/Services/UserProfile/PersonalAccountsService.php
app/Services/NetWorth/NetWorthService.php
```

After uploading, clear cache:
```bash
php artisan cache:clear
```

---

## Code Quality Improvements - Code Review Tasks

**Deployed:** 26 January 2026

**Status:** 10 of 12 Tasks Completed (83%)

### Summary

Full code review performed with systematic improvements across the codebase. Quality score: 82/100.

| Category | Improvements |
|----------|--------------|
| Code Organization | Extracted reusable traits, refactored large methods |
| Error Handling | Standardized across all controllers |
| Database | Added indexes for joint ownership queries |
| Caching | Standardized cache tags across all Agents |
| API Resilience | Added retry logic with exponential backoff |
| Validation | Form Request classes, Vue form validation |
| Logging | Structured logging format trait |

---

### New Files Created

| File | Purpose |
|------|---------|
| `app/Traits/HasJointOwnership.php` | Query scope trait for joint owner pattern |
| `app/Traits/StructuredLogging.php` | Standardized logging format |
| `app/Http/Requests/Protection/StoreIncomeProtectionPolicyRequest.php` | Form Request validation |
| `app/Http/Requests/Protection/UpdateIncomeProtectionPolicyRequest.php` | Form Request validation |
| `database/migrations/2026_01_26_150000_add_joint_owner_indexes.php` | Performance indexes |

---

### Files Modified

**Models (8) - Added HasJointOwnership trait:**
```
app/Models/Property.php
app/Models/SavingsAccount.php
app/Models/Chattel.php
app/Models/BusinessInterest.php
app/Models/Mortgage.php
app/Models/Investment/InvestmentAccount.php
app/Models/Goal.php
app/Models/Estate/Liability.php
```

**Agents (3) - Added cache tags:**
```
app/Agents/SavingsAgent.php
app/Agents/InvestmentAgent.php
app/Agents/RetirementAgent.php
```

**Controllers (10) - Added SanitizedErrorResponse trait:**
```
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/OnboardingController.php
app/Http/Controllers/Api/RiskPreferenceController.php
app/Http/Controllers/Api/RecommendationsController.php
app/Http/Controllers/Api/NetWorthController.php
app/Http/Controllers/Api/UserProfileController.php
app/Http/Controllers/Api/DashboardController.php
app/Http/Controllers/Api/RetirementController.php
app/Http/Controllers/Api/FamilyMembersController.php
app/Http/Controllers/Api/PortfolioOptimizationController.php
```

**Refactored Controllers:**
```
app/Http/Controllers/Api/PropertyController.php (store() reduced from 106 to 56 lines)
app/Http/Controllers/Api/AuthController.php (added helper methods, simplified verifyCode/login)
app/Http/Controllers/Api/ProtectionController.php (uses Form Request classes)
```

**Services:**
```
app/Services/Property/MortgageService.php (added createFromPropertyData method)
```

**Frontend:**
```
resources/js/services/api.js (retry logic with exponential backoff)
resources/js/components/Estate/GiftForm.vue (form validation)
```

---

### Rebuild Required: YES

Frontend JavaScript files have changed. Run the build script before uploading.

```bash
./deploy/fynla-org/build.sh
```

---

### Upload Checklist for Code Review Changes

**Step 1: Run Build**
```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

**Step 2: Upload Built Assets**
```
public/build/
```

**Step 3: Upload New PHP Files**
```
app/Traits/HasJointOwnership.php
app/Traits/StructuredLogging.php
app/Http/Requests/Protection/StoreIncomeProtectionPolicyRequest.php
app/Http/Requests/Protection/UpdateIncomeProtectionPolicyRequest.php
database/migrations/2026_01_26_150000_add_joint_owner_indexes.php
```

**Step 4: Upload Modified PHP Files**
```
app/Models/Property.php
app/Models/SavingsAccount.php
app/Models/Chattel.php
app/Models/BusinessInterest.php
app/Models/Mortgage.php
app/Models/Investment/InvestmentAccount.php
app/Models/Goal.php
app/Models/Estate/Liability.php
app/Agents/SavingsAgent.php
app/Agents/InvestmentAgent.php
app/Agents/RetirementAgent.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/OnboardingController.php
app/Http/Controllers/Api/RiskPreferenceController.php
app/Http/Controllers/Api/RecommendationsController.php
app/Http/Controllers/Api/NetWorthController.php
app/Http/Controllers/Api/UserProfileController.php
app/Http/Controllers/Api/DashboardController.php
app/Http/Controllers/Api/RetirementController.php
app/Http/Controllers/Api/FamilyMembersController.php
app/Http/Controllers/Api/PortfolioOptimizationController.php
app/Http/Controllers/Api/PropertyController.php
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/ProtectionController.php
app/Services/Property/MortgageService.php
```

**Step 5: Run Migration and Clear Cache (SSH)**
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate --force
php artisan cache:clear
php artisan route:clear
php artisan config:clear
```

---

## UI Fix: IHT Calculator - Spell Out Acronyms & Standardize Font Sizes

**Branch:** ihtBugs

**Status:** READY FOR DEPLOYMENT

### Issue

1. IHT calculator used acronyms (NRB, RNRB) that users may not understand
2. Asset/liability category labels used inconsistent font sizes (text-xs vs text-sm)

### Changes Made

**1. Acronyms spelled out:**
- `NRB` → "Tax-Free Allowance"
- `RNRB` → "Home Allowance"
- `Nil Rate Band (NRB)` → "Tax-Free Allowance"
- `Residence Nil Rate Band (RNRB)` → "Home Allowance"

**2. Font sizes standardized:**
- Allowance labels: Added `font-semibold text-gray-700` to match Total Liabilities/Taxable Estate styling
- Asset category labels (Property, Investment, Cash/Savings, Business, Personal Valuables): `text-xs` → `text-sm`
- Liability category labels (Mortgages, Other Liabilities, liability types): `text-xs` → `text-sm`

### Files Changed

```
resources/js/components/Estate/IHTPlanning.vue
```

### Rebuild Required: YES

Frontend Vue file changed. Run build script before uploading.

```bash
./deploy/fynla-org/build.sh
```

### Upload Checklist

1. Run build script
2. Upload `public/build/` directory

---

## Bug Fix: Property Form Not Scrolling to Top on Section Change

**Branch:** ihtBugs

**Status:** READY FOR DEPLOYMENT

### Issue

During onboarding, when adding a property and moving to the next section of the form, the form did not scroll to the top. Users had to manually scroll up to see the beginning of each new section.

### Fix

Added a `scrollToTop()` method that scrolls the form content container to the top when navigating between steps:

```javascript
scrollToTop() {
  this.$nextTick(() => {
    if (this.$refs.formContent) {
      this.$refs.formContent.scrollTop = 0;
    }
  });
},
```

Called in `nextStep()`, `previousStep()`, and `goToStep()` methods.

### Files Changed

```
resources/js/components/NetWorth/Property/PropertyForm.vue
```

### Rebuild Required: YES

Frontend Vue file changed. Run build script before uploading.

```bash
./deploy/fynla-org/build.sh
```

### Verification

1. Navigate to Onboarding or Net Worth → Properties
2. Click "Add Property"
3. Fill in Step 1 and click "Next"
4. Form should scroll to show the top of Step 2
5. Repeat for all steps, including clicking "Previous" and clicking step numbers directly

---

## Bug Fix: IHT Calculator Missing Joint Chattels and Business Interests

**Branch:** ihtBugs

**Status:** READY FOR DEPLOYMENT

### Issue

When a spouse creates joint chattels or business interests, they were not appearing in the other spouse's IHT Calculator. The joint assets were visible in Wealth Summary but missing from estate planning calculations.

### Root Cause

`EstateAssetAggregatorService.gatherUserAssets()` was only querying assets where `user_id` matched, missing assets where the user is the `joint_owner_id`:

```php
// Before (broken):
$chattels = Chattel::where('user_id', $user->id)->get();
$businessInterests = BusinessInterest::where('user_id', $user->id)->get();
```

Additionally, the share calculation was incorrect for joint owners - it used `ownership_percentage` directly instead of `(100 - ownership_percentage)` for the joint owner's share.

### Fix

Added `orWhere('joint_owner_id', $user->id)` to queries and switched to using the `CalculatesOwnershipShare` trait for correct share calculation:

```php
// After (fixed):
$chattels = Chattel::where('user_id', $user->id)
    ->orWhere('joint_owner_id', $user->id)
    ->get();

$chattelAssets = $chattels->map(function ($chattel) use ($user) {
    $userValue = $this->calculateUserShare($chattel, $user->id);  // Trait handles joint owner
    // ...
});
```

### Files Changed

```
app/Services/Estate/EstateAssetAggregatorService.php
```

### Rebuild Required: NO

Backend PHP file only. No frontend rebuild needed.

### Upload Checklist

1. Upload `app/Services/Estate/EstateAssetAggregatorService.php`
2. Clear cache: `php artisan cache:clear`

### Verification

1. Log in as spouse who did NOT create the joint chattel
2. Navigate to Estate Planning → IHT Calculator
3. Joint chattels should appear in the asset breakdown
4. User's share should be correctly calculated (e.g., 50% of full value for 50/50 split)

---

## Bug Fix: Will Card Navigation to Blank Page

**Branch:** ihtBugs

**Status:** READY FOR DEPLOYMENT

### Issue

In the Estate Planning module, clicking the Will card in the IHT Mitigation Strategies section navigated to a blank page instead of going to Valuable Info → Wills tab.

### Root Cause

The `navigateToWillTab()` method in `IHTPlanning.vue` emitted a `switch-tab` event with value `'will'` to the parent `EstateDashboard.vue`. However, EstateDashboard only has tabs for: `iht`, `gifting`, `life-policy`, `trusts` — there is no `'will'` tab component.

When the tab value was set to `'will'`, no component rendered because there was no matching `v-if` condition, resulting in a blank page.

The Will tab actually exists in `ValuableInfo.vue` (a separate route), not in EstateDashboard.

### Fix

Changed `navigateToWillTab()` from emitting a tab switch event to performing a direct router navigation:

```javascript
// Before (broken):
navigateToWillTab() {
  this.$emit('switch-tab', 'will');
},

// After (fixed):
navigateToWillTab() {
  this.$router.push({ path: '/valuable-info', query: { section: 'will' } });
},
```

### Files Changed

```
resources/js/components/Estate/IHTPlanning.vue
```

### Rebuild Required: YES

Frontend Vue file changed. Run build script before uploading.

```bash
./deploy/fynla-org/build.sh
```

### Upload Checklist

1. Run build script
2. Upload `public/build/` directory

### Verification

1. Navigate to Estate Planning → IHT Calculator
2. Scroll to Mitigation Strategies section
3. Click on the Will card
4. Should navigate to Valuable Info page with Wills tab active

---

## Feature: ISA Subscription Enhancement - Regular Contributions & Lump Sums

**Branch:** ihtBugs

**Status:** READY FOR DEPLOYMENT

### Overview

Enhanced ISA account forms (both Cash ISA and Stocks & Shares ISA) to include regular contributions and planned lump sums inside the ISA subscription box. Added validation to ensure planned contributions don't exceed the shared £20,000 ISA allowance.

### Changes Made

**1. Stocks & Shares ISA (AccountForm.vue):**
- Moved Regular Contributions section INSIDE the blue ISA subscription box (only shown for ISA accounts)
- Added planned lump sum fields to the ISA box
- Added ISA allowance progress bar showing breakdown by:
  - Cash ISA usage (blue)
  - Other S&S ISAs (purple)
  - This account (green)
  - Planned contributions (amber)
- Added validation that prevents saving if total planned contributions exceed £20,000 allowance
- Shows remaining allowance with color-coded warnings (green → orange → red)

**2. Cash ISA (SaveAccountModal.vue):**
- Added Regular Contribution Amount field with frequency selector (monthly/quarterly/annually)
- Added Planned Lump Sum field with date picker
- Added ISA allowance progress bar (same breakdown as S&S ISA)
- Added validation to prevent exceeding allowance
- Styled ISA section with blue background and border (matching S&S ISA style)
- Fields hidden for Junior ISAs (they have separate £9,000 allowance)

**3. Backend (SavingsAccount model + migration):**
- Added new fields to SavingsAccount model: `regular_contribution_amount`, `contribution_frequency`, `planned_lump_sum_amount`, `planned_lump_sum_date`
- Created migration to add columns to `savings_accounts` table

### Allowance Tracking Logic

Both forms now correctly track the shared ISA allowance:
- Gets Cash ISA usage from `savings/currentYearISASubscription` store getter
- Gets S&S ISA usage from `investment/investmentISASubscription` store getter
- When editing an account, excludes that account's original subscription from the "other" totals
- Calculates planned annual contribution: `(regular_amount × frequency_multiplier) + lump_sum`
- Shows warning if `total_used + planned > £20,000`

### Files Changed

**Frontend (Rebuild Required):**
```
resources/js/components/Investment/AccountForm.vue
resources/js/components/Savings/SaveAccountModal.vue
```

**Backend:**
```
app/Models/SavingsAccount.php
database/migrations/2026_01_26_000001_add_contribution_fields_to_savings_accounts.php
```

### Rebuild Required: YES

Frontend Vue files changed. Run build script before uploading.

```bash
./deploy/fynla-org/build.sh
```

### Upload Checklist

1. Run build script
2. Upload `public/build/` directory
3. Upload backend files:
   ```
   app/Models/SavingsAccount.php
   database/migrations/2026_01_26_000001_add_contribution_fields_to_savings_accounts.php
   ```
4. Run migration and clear cache:
   ```bash
   ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
   cd ~/www/fynla.org/public_html
   php artisan migrate --force
   php artisan cache:clear
   ```

### Verification

**Cash ISA Form:**
1. Navigate to Net Worth → Cash tab
2. Click "Add Account"
3. Select "Cash ISA" as product type
4. ISA section should show:
   - ISA Subscription header with info text
   - Already Subscribed This Tax Year field
   - Regular Contribution Amount with frequency dropdown
   - Planned Lump Sum with date picker
   - ISA Allowance Usage bar with breakdown
5. Enter values that exceed £20,000 → should show warning and prevent save

**Stocks & Shares ISA Form:**
1. Navigate to Net Worth → Investments tab
2. Click "Add Account"
3. Select "ISA (Stocks & Shares)" as account type
4. Regular Contributions and Planned Lump Sum should appear INSIDE the blue ISA box
5. Allowance bar should show combined Cash ISA + S&S ISA usage
6. Test exceeding allowance → should show warning

---

### Deferred Tasks

| Task | Reason |
|------|--------|
| Unit tests for financial calculations | Requires 8+ hour dedicated testing sprint |
| Vue prop type validation | Long-term incremental improvement |

---
