# Deploy Notes — 13 March 2026

**Branch:** `uiImprovements`
**Type:** iOS Capacitor App Only — No server upload needed

## Summary

Two changes: app icon redesign (eggshell background, scaled logo) and complete Face ID flow rework (no premature prompts, auto-login when enabled).

## Build & Deploy

```bash
# Already built — just open Xcode and archive
open ios/App/App.xcworkspace

# If rebuilding needed:
./deploy/mobile/build-ios.sh
```

**Important:** Users should delete the app from their device before installing to clear the icon cache and any stale Keychain credentials from the old Face ID implementation.

## Changes Included

### App Icon
- Eggshell (#F7F6F4) background replacing dark/black
- Logo scaled to 60% with centred padding (no more clipping by iOS rounded corners)
- All 14 icon size variants regenerated

### Face ID Rework
- Removed premature iOS permission dialog on first app launch
- Dashboard setup banner moved from bottom-sheet modal to inline card at top
- Face ID scan now fires when user taps "Set up" (visible confirmation)
- Auto Face ID login on app launch and login screen mount when enabled
- `biometric_enabled` Preferences flag gates all native biometric API calls
- Settings toggle sets/clears the flag

## Files Changed

| File | Type |
|------|------|
| `ios/App/App/Assets.xcassets/AppIcon.appiconset/*` | 14 icon PNGs |
| `resources/js/app.js` | JS |
| `resources/js/mobile/BiometricPrompt.vue` | Vue |
| `resources/js/mobile/views/MobileDashboard.vue` | Vue |
| `resources/js/mobile/views/MobileLoginScreen.vue` | Vue |
| `resources/js/mobile/views/VerificationCodeScreen.vue` | Vue |
| `resources/js/mobile/SettingsList.vue` | Vue |

## Post-Deploy

Clear server cache after deploying:
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear
```
