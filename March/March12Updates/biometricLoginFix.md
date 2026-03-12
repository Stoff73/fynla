# Biometric (Face ID) Login — Complete Implementation

**Date:** 12 March 2026
**Branch:** `mobileImprovement`
**Commits:** `ef8fb79`, `7e594a7`, `65c6c34`, `eeb8f41`

---

## What Was Built

Full Face ID / Touch ID biometric login for the iOS Capacitor app:

1. **First login:** email + password + verification code
2. **Biometric setup:** dashboard shows bottom-sheet modal offering Face ID setup
3. **Subsequent launches:** automatic Face ID login (no email/password needed)
4. **Logout + reopen:** Face ID prompt → auto-login (token preserved in Keychain)
5. **Settings toggle:** enable/disable Face ID from Settings

## Bug Fixes Applied

### 1. Biometric functions never called (ef8fb79)
`attemptBiometricLogin()` and `initAppLifecycle()` were exported from `appLifecycle.js` but never imported in `app.js`. Wired them up.

### 2. Face ID breaks after logout (7e594a7)
`auth/logout` action called `POST /auth/logout` which revokes the server token — making the Keychain-stored token invalid. Added `auth/mobileLogout` action that clears local state only (Vuex + Preferences) without revoking the server token.

### 3. Face ID prompt on first launch before login (eeb8f41)
`attemptBiometricLogin()` called `NativeBiometric.verifyIdentity()` (shows native Face ID prompt) BEFORE checking if any credentials were stored. On first ever app launch with no stored credentials, this triggered an unwanted Face ID prompt. Fixed to check `getCredentials()` first — if no credentials exist, returns silently.

## Files Changed

| File | Change |
|------|--------|
| `resources/js/app.js` | Import and call `attemptBiometricLogin()` + `initAppLifecycle()` on startup |
| `resources/js/mobile/appLifecycle.js` | Check credentials before verifyIdentity(); use store.dispatch for fetchUser |
| `resources/js/mobile/BiometricPrompt.vue` | Refactored to bottom-sheet modal (emits `close`) |
| `resources/js/mobile/SettingsList.vue` | Added Face ID toggle at top of settings list |
| `resources/js/mobile/views/MobileLoginScreen.vue` | Added "Sign in with Face ID" button |
| `resources/js/mobile/views/MobileDashboard.vue` | Shows BiometricPrompt modal when `?biometricSetup=1` |
| `resources/js/mobile/views/VerificationCodeScreen.vue` | Navigate to `/m/home?biometricSetup=1` |
| `resources/js/mobile/views/MoreMenu.vue` | Uses `auth/mobileLogout` instead of `auth/logout` |
| `resources/js/store/modules/auth.js` | Added `mobileLogout` action |

## Architecture

### Credential Storage
- **iOS Keychain** via `@capgo/capacitor-native-biometric` — stores auth token (as `password` field) + email (as `username`)
- **Capacitor Preferences** — separate token storage for normal session management
- Both are independent — biometric credentials survive `mobileLogout`

### Login Flow
```
First launch (no credentials):
  Login screen → email + password → verification code → dashboard → BiometricPrompt modal → user enables → token saved to Keychain

Subsequent launches (credentials exist):
  app.js → attemptBiometricLogin() → getCredentials() → verifyIdentity() (Face ID) → setToken() → fetchUser → /m/home

After logout (credentials still in Keychain):
  mobileLogout clears Vuex + Preferences only → app.js → attemptBiometricLogin() → Face ID → auto-login

Token expired:
  attemptBiometricLogin() catches 401 → clearAuth → login screen
  MobileLoginScreen catches 401 → deletes stale Keychain credentials → "session expired" message
```

### Critical Rules
- **NEVER use `auth/logout` on mobile** — revokes server token, breaks biometric
- **ALWAYS use `auth/mobileLogout`** — clears local state only
- **Check credentials BEFORE verifyIdentity()** — avoids unwanted Face ID prompts
- `MoreMenu.vue` dispatches `auth/mobileLogout`, NOT `auth/logout`

## Rebuild & Verify

```bash
./deploy/mobile/build-ios.sh
php artisan db:seed
# Open Xcode → Clean Build (Cmd+Shift+K) → Run (Cmd+R)
```

### Test Checklist
1. Fresh install: login screen only, no Face ID prompt
2. Login with email + password + verification code
3. Dashboard shows biometric setup modal
4. Enable Face ID → close modal
5. Logout (More → Sign Out)
6. App relaunches → Face ID prompt → auto-login to dashboard
7. Settings → Face ID toggle off → logout → login screen (no Face ID button)
8. Settings → Face ID toggle on → logout → Face ID prompt → auto-login
