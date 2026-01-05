# Jan 5 2026 Updates

## Bug Fixes

### 1. Registration from Preview Mode Not Working

**Issue:** When clicking "Create Account" from the registration page (after clicking "Register to Save Your Data" from a preview persona dashboard), nothing happened. The form appeared to submit but no account was created.

**Root Cause:** The `PreviewWriteInterceptor` middleware was intercepting ALL POST requests from preview users, including `/auth/register`. This caused the registration endpoint to return a fake "preview mode" response instead of actually creating a pending registration.

**Fix:** Added authentication endpoints to the excluded routes list in the middleware.

**File Changed:** `app/Http/Middleware/PreviewWriteInterceptor.php`

```php
// Added these routes to EXCLUDED_ROUTES array:
'api/auth/register',      // Allow preview users to create real accounts
'api/auth/verify-code',   // Required for registration verification
'api/auth/resend-code',   // Required for registration verification
```

---

### 2. "Failed to set up account" After Choosing to Keep/Discard Persona Data

**Issue:** After successful registration and email verification, the KeepDataOrFreshModal appeared correctly, but clicking "Continue" after selecting an option caused a "Failed to set up account" error. The console showed a 422 validation error from `/api/user/seed-persona-data`.

**Root Cause:** After registration completes, the authenticated user changes from the preview user to the newly created real user. Since the new user doesn't have `is_preview_user=true`, the Vuex getter `currentPersona` returns `null`. The modal was receiving this null persona, so `persona_id` was undefined in the API call.

**Fix:** Created a reactive ref `personaForModal` to cache the persona before auth state changes, and pass that to the modal instead of the computed `currentPersona`.

**File Changed:** `resources/js/views/Register.vue`

Changes made:
1. Added `personaForModal` ref (line 222)
2. Set `personaForModal.value = cachedCurrentPersona` when showing modal (line 325)
3. Changed template to use `:persona="personaForModal"` instead of `:persona="currentPersona"` (line 181)
4. Added `personaForModal` to return statement (line 386)

---

### 3. Vuex Error: Unknown Getter "effectivePersonaData"

**Issue:** Console showed `[vuex] unknown getter: preview/effectivePersonaData` which prevented the Continue button from working properly in the KeepDataOrFreshModal.

**Root Cause:** The `KeepDataOrFreshModal.vue` component was referencing a Vuex getter `effectivePersonaData` that didn't exist in the preview store module.

**Fix:** Removed the non-existent getter from mapGetters and updated computed properties to use `this.activePersona` instead.

**File Changed:** `resources/js/components/Preview/KeepDataOrFreshModal.vue`

```javascript
// Changed from:
...mapGetters('preview', ['currentPersona', 'effectivePersonaData']),

// Changed to:
...mapGetters('preview', ['currentPersona']),

// Also updated personaIsMarried, spouseName, and dataSummary computed properties
// to use this.activePersona instead of this.effectivePersonaData
```

---

### 4. User Redirected to Home Page Instead of Dashboard After Registration

**Issue:** After successfully completing registration from preview mode and clicking Continue in the KeepDataOrFreshModal, the user was redirected to the home page (`/`) instead of the dashboard.

**Root Cause:** The `handleKeepDataChoice` function in Register.vue called `store.dispatch('preview/exitPreview')` after seeding persona data. The `exitPreview` action:
1. Clears localStorage auth_token
2. Clears Vuex auth state
3. Does `window.location.href = '/'` which redirects before `router.push({ name: 'Dashboard' })` can execute

This was incorrect because after registration, the user is a REAL authenticated user (not a preview user), and should not have their auth token cleared.

**Fix:** Removed the `exitPreview` call and replaced it with simple localStorage cleanup for preview-related items only.

**File Changed:** `resources/js/views/Register.vue`

```javascript
// Changed from:
await store.dispatch('preview/exitPreview');

// Changed to:
localStorage.removeItem('preview_persona_id');
localStorage.removeItem('preview_mode');
```

---

## Files to Upload to Production

### Backend (PHP)
| File | Path on Server |
|------|----------------|
| `app/Http/Middleware/PreviewWriteInterceptor.php` | `app/Http/Middleware/PreviewWriteInterceptor.php` |

### Frontend (Requires Rebuild)
| File | Notes |
|------|-------|
| `resources/js/views/Register.vue` | Multiple fixes - requires `npm run build` |
| `resources/js/components/Preview/KeepDataOrFreshModal.vue` | Removed invalid getter - requires `npm run build` |

### Deployment Steps

1. **Upload PHP file:**
   ```
   app/Http/Middleware/PreviewWriteInterceptor.php
   ```

2. **Rebuild frontend locally:**
   ```bash
   ./deploy/fynla-org/build.sh
   ```

3. **Upload built assets:**
   - Extract the generated tar.gz package
   - Upload `public/build/` contents to server's `public/build/` directory

4. **Clear Laravel cache on server:**
   ```bash
   php artisan config:clear
   php artisan view:clear
   php artisan route:clear
   ```

---

## Testing Checklist

- [x] Go to localhost:8000 (or fynla.org after deployment)
- [x] Click "Try the Demo"
- [x] Select any persona (e.g., Emily & James Carter)
- [x] Click "Register to Save Your Data" in the dashboard
- [x] Fill in registration form with valid email
- [x] Click "Create Account" - should show verification modal
- [x] Enter verification code from email
- [x] KeepDataOrFreshModal should appear (no console errors)
- [x] Select "Keep data" or "Start fresh"
- [x] Click "Continue" - should redirect to dashboard WITHOUT error
- [x] Dashboard should display seeded persona data (if "Keep data" was selected)
- [x] Click on Net Worth card - should navigate to Net Worth page (not redirect to login)
- [x] Click on Property card - should navigate to Property page (not redirect to login)
- [x] Click on Protection card - should navigate to Protection page (not redirect to login)
- [x] Verify auth_token persists in localStorage after navigation
- [x] Verify preview_mode and preview_persona_id are cleared from localStorage

**All tests passed on Jan 5, 2026**
