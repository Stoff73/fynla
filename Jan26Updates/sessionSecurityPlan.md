# Security Enhancement Plan: Session Management & Data Protection

## Status: IMPLEMENTED

**Implementation Date:** 26 January 2026

## Summary

Implement strict session security for the Fynla financial planning application to ensure users must authenticate on every visit, with no persistent local data.

---

## Implementation Notes

All planned features have been implemented plus the following refinements:

- **Dropdown menu closes on logout** — User dropdown and mobile menu close immediately when logout is clicked, before the modal appears
- **"Remember me" removed** — Checkbox and form field removed from login screen (incompatible with session-only auth)
- **PreviewWriteInterceptor updated** — Added `logout-beacon` route to excluded routes list
- **Removed pagehide/beforeunload handlers** — These events fire on page refresh, causing unwanted logouts. sessionStorage automatically clears on browser/tab close, so no explicit handler needed

### Session Behaviour (Final)

| Action | Session Result |
|--------|----------------|
| Page refresh (F5) | **Persists** |
| Tab switching | **Persists** |
| Window switching | **Persists** |
| Browser close | **Ends** (sessionStorage auto-clears) |
| Tab close | **Ends** (sessionStorage auto-clears) |
| 15 min inactivity | **Ends** (timeout handler) |
| Manual logout | **Ends** (explicit action) |

---

## Current Security Issues (RESOLVED)

| Issue | Current State | Risk |
|-------|---------------|------|
| Token persists | `localStorage.auth_token` survives browser close | Auto-login on shared computers |
| Financial data cached | P&L, cashflow, balance sheet in localStorage | Sensitive data exposure |
| No tab close handling | No `pagehide`/`beforeunload` events | Sessions never terminate |
| No logout confirmation | Silent logout | User uncertainty |

---

## Implementation Plan

### 1. Migrate Token Storage (localStorage → sessionStorage)

**File:** `resources/js/services/authService.js`

```javascript
// Change from:
localStorage.setItem('auth_token', token);
localStorage.getItem('auth_token');
localStorage.removeItem('auth_token');

// To:
sessionStorage.setItem('auth_token', token);
sessionStorage.getItem('auth_token');
sessionStorage.removeItem('auth_token');
```

**Also update:** `resources/js/services/api.js` - axios interceptor reads from sessionStorage

**Effect:** Token automatically cleared when browser closes. Each tab gets independent session.

---

### 2. Remove Financial Data from localStorage

**File:** `resources/js/store/modules/userProfile.js`

**Changes:**
- Remove `getInitialPersonalAccounts()` localStorage restoration (lines 9-25)
- Remove `getInitialSpouseAccounts()` localStorage restoration (lines 28-39)
- Remove localStorage persistence in `setPersonalAccounts` mutation (lines 545-562)
- Keep `resetState` cleanup but remove localStorage references

**Result:** Financial data only exists in memory during active session, fetched fresh from API each login.

---

### 3. Add Session Lifecycle Service

**New file:** `resources/js/services/sessionLifecycleService.js`

```javascript
const INACTIVITY_TIMEOUT = 15 * 60 * 1000; // 15 minutes
let inactivityTimer = null;

export function initSessionLifecycle(store, router) {
  // pagehide - handles tab/browser close (works on mobile)
  window.addEventListener('pagehide', (event) => {
    if (!event.persisted) {
      performLogout(store);
    }
  });

  // Inactivity timer - reset on user activity
  const resetInactivityTimer = () => {
    if (inactivityTimer) clearTimeout(inactivityTimer);

    const token = sessionStorage.getItem('auth_token');
    if (token) {
      inactivityTimer = setTimeout(() => {
        store.dispatch('auth/logout');
        router.push('/login?reason=inactivity');
      }, INACTIVITY_TIMEOUT);
    }
  };

  // Activity events to reset timer
  ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(event => {
    document.addEventListener(event, resetInactivityTimer, { passive: true });
  });

  // Start timer on init
  resetInactivityTimer();
}

function performLogout(store) {
  const token = sessionStorage.getItem('auth_token');
  if (token) {
    navigator.sendBeacon('/api/auth/logout-beacon', JSON.stringify({ token }));
  }
  sessionStorage.clear();
}

export function stopInactivityTimer() {
  if (inactivityTimer) {
    clearTimeout(inactivityTimer);
    inactivityTimer = null;
  }
}
```

**File:** `resources/js/app.js` - Initialize service on mount

**File:** `resources/js/views/Login.vue` - Show "Session expired due to inactivity" message when `?reason=inactivity`

---

### 4. Backend Beacon Logout Endpoint

**File:** `app/Http/Controllers/Api/AuthController.php`

```php
public function logoutBeacon(Request $request)
{
    // Accept JSON body from sendBeacon
    $data = json_decode($request->getContent(), true);
    $token = $data['token'] ?? null;

    if ($token) {
        // Find and revoke the token
        $accessToken = PersonalAccessToken::findToken($token);
        if ($accessToken) {
            UserSession::where('token_id', $accessToken->id)->delete();
            $accessToken->delete();
        }
    }

    return response()->json(['success' => true]);
}
```

**File:** `routes/api.php` - Add route (no auth middleware, accepts token in body)

---

### 5. Logout Success Modal

**New file:** `resources/js/components/Auth/LogoutSuccessModal.vue`

```vue
<template>
  <BaseModal :show="show" size="sm" @close="handleClose">
    <div class="text-center py-4">
      <div class="text-green-500 mb-4">
        <svg class="w-16 h-16 mx-auto"><!-- checkmark --></svg>
      </div>
      <h3 class="text-xl font-semibold mb-2">Logged Out Successfully</h3>
      <p class="text-gray-600 mb-4">
        Your session has been securely terminated and all local data cleared.
      </p>
      <BaseButton @click="handleClose">Return to Login</BaseButton>
    </div>
  </BaseModal>
</template>
```

**File:** `resources/js/store/modules/auth.js` - Add `showLogoutModal` state and integrate

**File:** `resources/js/components/Navbar.vue` - Show modal after logout completes

---

### 6. Comprehensive Cleanup on Logout

**File:** `resources/js/services/authService.js`

```javascript
clearAuth() {
  // Clear sessionStorage
  sessionStorage.removeItem('auth_token');

  // Clear any legacy localStorage
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');

  // Clear ALL user-specific localStorage keys
  const keysToRemove = [];
  for (let i = 0; i < localStorage.length; i++) {
    const key = localStorage.key(i);
    if (key && (key.includes('_user_') || key.includes('personalAccounts') || key.includes('spouseAccounts'))) {
      keysToRemove.push(key);
    }
  }
  keysToRemove.forEach(key => localStorage.removeItem(key));
}
```

---

## Files to Modify

| File | Changes |
|------|---------|
| `resources/js/services/authService.js` | sessionStorage migration, enhanced clearAuth |
| `resources/js/services/api.js` | Read token from sessionStorage |
| `resources/js/store/modules/userProfile.js` | Remove localStorage persistence |
| `resources/js/store/modules/auth.js` | Add logout modal state, enhanced logout action |
| `resources/js/app.js` | Initialize sessionLifecycleService with store + router |
| `resources/js/components/Navbar.vue` | Integrate logout modal |
| `resources/js/views/Login.vue` | Show inactivity message when `?reason=inactivity` |
| `app/Http/Controllers/Api/AuthController.php` | Add logoutBeacon method |
| `routes/api.php` | Add beacon logout route |

## New Files

| File | Purpose |
|------|---------|
| `resources/js/services/sessionLifecycleService.js` | Tab/browser close handling |
| `resources/js/components/Auth/LogoutSuccessModal.vue` | Logout confirmation modal |

---

## Verification

1. **Test logout flow:**
   - Click logout → modal appears → redirected to login
   - Check sessionStorage is empty
   - Check localStorage has no user data

2. **Test browser close:**
   - Login, then close browser completely
   - Reopen browser, navigate to app → should see login page, not dashboard

3. **Test tab close:**
   - Login in one tab, close that tab
   - Open new tab to app → should see login page

4. **Test mobile (iOS Safari, Android Chrome):**
   - Login, swipe away app
   - Reopen → should see login page

5. **Test page refresh:**
   - Login, press F5/refresh → should stay logged in (sessionStorage survives refresh)

6. **Test inactivity timeout:**
   - Login, leave app idle for 15+ minutes
   - Should auto-logout and redirect to login with "Session expired" message
   - Verify timer resets on mouse/keyboard/scroll activity

7. **Check no data leakage:**
   - Login as User A, view financial data
   - Logout, login as User B
   - Verify no User A data visible

---

## Security Notes

- **sessionStorage** automatically clears on browser close - no code needed
- **pagehide** event is most reliable across all browsers including mobile
- **sendBeacon** guarantees server receives logout even during page unload
- Financial data should NEVER be stored client-side for a finance app
- Disclaimer flags (`disclaimer_dismissed_*`) are non-sensitive UI state - can remain in localStorage
