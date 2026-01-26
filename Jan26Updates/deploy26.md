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
| `resources/js/store/modules/userProfile.js` | Modified |
| `resources/js/store/modules/auth.js` | Modified |
| `resources/js/app.js` | Modified |
| `resources/js/components/Navbar.vue` | Modified (logout modal + dropdown close) |
| `resources/js/components/Auth/LogoutSuccessModal.vue` | **NEW** |
| `resources/js/views/Login.vue` | Modified (inactivity msg + removed "Remember me") |
| `resources/js/components/UserProfile/FamilyMembers.vue` | Modified (spouse modal fix) |

### Backend (PHP) - Upload Directly

| File | Change Type |
|------|-------------|
| `app/Http/Controllers/Api/AuthController.php` | Modified |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Modified |
| `app/Services/GDPR/DataErasureService.php` | Modified (spouse cleanup) |
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

# ⏳ NOT DEPLOYED

*Items below this line have not yet been deployed to production.*

---

## Bug Fix: Spouse Success Modal Not Appearing in User Profile

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
