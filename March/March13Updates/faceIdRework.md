# Face ID Flow Rework — Complete Overhaul

**Date:** 13 March 2026
**Branch:** `uiImprovements`

---

## Problem

The previous Face ID implementation had several issues:
1. iOS system permission dialog ("Do you want to allow Fynla to use Face ID?") fired on first app launch before the user had even logged in
2. The Face ID setup prompt was a full-screen bottom-sheet modal anchored to the bottom of the dashboard
3. No visible Face ID scan when user tapped "Set up" — credentials stored silently
4. Face ID did not auto-trigger on subsequent logins — user had to manually tap "Sign in with Face ID"

## What Changed

### 1. No biometric API calls until user explicitly sets up Face ID
Added a `biometric_enabled` flag in Capacitor Preferences (persists across app restarts). All native biometric API calls (`NativeBiometric.isAvailable()`, `getCredentials()`) are gated behind this flag. On fresh install, no biometric APIs are touched — no iOS permission dialog.

### 2. Dashboard banner replaces bottom-sheet modal
`BiometricPrompt.vue` changed from a full-screen overlay modal to a compact inline card at the top of the dashboard. Shows on every dashboard load until the user enables Face ID.

### 3. Face ID scan on setup
When the user taps "Set up", the flow is: `isAvailable()` → iOS permission dialog (first time) → `verifyIdentity()` (Face ID scan) → `setCredentials()` + flag set → banner disappears. User sees the Face ID animation confirming it works.

### 4. Auto Face ID login when enabled
Face ID auto-triggers in two places:
- **`app.js` startup** — for fresh app launches when `biometric_enabled` flag is set
- **`MobileLoginScreen` mount** — for in-app logouts; automatically calls `handleBiometricLogin()` if credentials exist

If Face ID fails or the user cancels, they see the login screen with the manual "Sign in with Face ID" button as fallback.

## Flow Summary

| Scenario | Behaviour |
|----------|-----------|
| First ever app launch | Login screen only. No Face ID prompts. |
| First dashboard after login (Face ID not set up) | "Enable Face ID" banner at top of dashboard |
| User taps "Set up" | iOS permission → Face ID scan → credentials stored → banner gone |
| User dismisses banner | Banner disappears for this session, reappears next login |
| App launch (Face ID set up) | Auto Face ID scan → straight to dashboard |
| Face ID fails / cancelled | Falls through to login screen with manual Face ID button |
| User disables Face ID in settings | Keychain credentials + Preferences flag cleared |

## Files Changed

| File | Change |
|------|--------|
| `resources/js/app.js` | Gate auto-biometric behind `biometric_enabled` Preferences flag |
| `resources/js/mobile/BiometricPrompt.vue` | Converted to inline top-anchored banner; added `verifyIdentity()` to setup flow; set Preferences flag on enable |
| `resources/js/mobile/views/MobileDashboard.vue` | Moved banner to top; check Preferences flag instead of query param; removed `?biometricSetup=1` dependency |
| `resources/js/mobile/views/MobileLoginScreen.vue` | Gate biometric checks behind Preferences flag; auto-trigger Face ID on mount when enabled |
| `resources/js/mobile/views/VerificationCodeScreen.vue` | Removed `?biometricSetup=1` query param |
| `resources/js/mobile/SettingsList.vue` | Set/clear `biometric_enabled` Preferences flag on toggle |
| `resources/js/mobile/appLifecycle.js` | No changes — `attemptBiometricLogin()` still used by app.js |

## Key Implementation Detail

The `biometric_enabled` flag in Capacitor Preferences prevents ALL native biometric API calls until the user explicitly enables Face ID. This is critical because:
- `NativeBiometric.isAvailable()` triggers the iOS system permission dialog on first call
- `NativeBiometric.getCredentials()` can also trigger it
- iOS Keychain persists across app deletions (Preferences do not), so the flag acts as the gate
