# Deploy Notes — 13 March 2026

**Branch:** `uiImprovements`
**Type:** iOS Capacitor App + 2 PHP files to upload

## Summary

Nine changes: app icon redesign, Face ID flow rework, mobile header redesign (centred logo + user avatar), emoji icon removal from all cards, protection dashboard card fix (show policy count), continuous voice input, chat error display, viewport zoom fix, and More menu modules grid removal.

## Build & Deploy

```bash
# Already built — just open Xcode and archive
open ios/App/App.xcworkspace

# If rebuilding needed:
./deploy/mobile/build-ios.sh
```

### PHP File Upload (Required)

Upload via SiteGround File Manager:

| Local | Remote |
|-------|--------|
| `app/Services/Mobile/MobileDashboardAggregator.php` | `~/www/fynla.org/public_html/app/Services/Mobile/MobileDashboardAggregator.php` |
| `app/Services/AI/AiChatService.php` | `~/www/fynla.org/public_html/app/Services/AI/AiChatService.php` |

**Important:** Users should delete the app from their device before installing to clear the icon cache and any stale Keychain credentials from the old Face ID implementation.

**IMPORTANT — Fyn Chat Broken on Production:** Chat returns SSE error events. API key and model work fine locally. After uploading `AiChatService.php`, SSH in and check logs:
```bash
grep "AiChatService" storage/logs/laravel.log | tail -20
grep OPENAI_API_KEY .env
```

## Changes Included

### App Icon ([[appIconUpdate]])
- Eggshell (#F7F6F4) background replacing dark/black
- Logo scaled to 60% with centred padding (no more clipping by iOS rounded corners)
- All 14 icon size variants regenerated

### Face ID Rework ([[faceIdRework]])
- Removed premature iOS permission dialog on first app launch
- Dashboard setup banner moved from bottom-sheet modal to inline card at top
- Face ID scan now fires when user taps "Set up" (visible confirmation)
- Auto Face ID login on app launch and login screen mount when enabled
- `biometric_enabled` Preferences flag gates all native biometric API calls
- Settings toggle sets/clears the flag

### Mobile Header Redesign ([[headerRedesign]])
- Centred Fynla logo (`LogoHiResFynlaDark.png`) replaces text title
- User initials avatar (top left) links to settings on root pages
- Back button replaces avatar on sub-pages
- Login screen: full logo replaces favicon + heading

### Icon Removal ([[iconRemoval]])
- Removed all emoji icons from dashboard module cards
- Removed emoji icons from detail view hero cards, accordion sections, empty states
- Removed emoji icons from policy cards, estate asset cards
- Removed emoji icons from More menu module grid
- 13 Vue files cleaned

### Protection Card Fix ([[protectionCardFix]])
- Dashboard card now shows "X policies" instead of £0
- Backend: added `policy_count` to `MobileDashboardAggregator`
- Frontend: changed metric type from currency to text

### More Menu Cleanup ([[moreMenuCleanup]])
- Removed Modules grid from More/Settings screen (accessible from dashboard)

### Voice Input & Chat Fix ([[voiceInputAndChatFix]])
- VoiceInputButton.vue rewritten for continuous listening mode
- Uses native Capacitor `listeningState` listener for safe auto-restart
- Avoids Plugin.swift race condition (stop/start causes fatal nil unwrap)
- Chat errors now visible in MobileFynChat (dismissible error box)
- AiChatService.php logs actual OpenAI error details
- Viewport meta prevents iOS auto-zoom (`maximum-scale=1.0, user-scalable=no`)

## Files Changed

| File | Type |
|------|------|
| `ios/App/App/Assets.xcassets/AppIcon.appiconset/*` | 14 icon PNGs |
| `resources/js/app.js` | JS |
| `resources/js/mobile/BiometricPrompt.vue` | Vue |
| `resources/js/mobile/MobileHeader.vue` | Vue |
| `resources/js/mobile/ModuleSummaryCard.vue` | Vue |
| `resources/js/mobile/components/MobileAccordionSection.vue` | Vue |
| `resources/js/mobile/components/MobileEmptyState.vue` | Vue |
| `resources/js/mobile/components/MobileEstateAssetCard.vue` | Vue |
| `resources/js/mobile/components/MobileHeroCard.vue` | Vue |
| `resources/js/mobile/components/MobilePolicyCard.vue` | Vue |
| `resources/js/mobile/layouts/MobileLayout.vue` | Vue |
| `resources/js/mobile/views/CoordinationDetail.vue` | Vue |
| `resources/js/mobile/views/EstateDetail.vue` | Vue |
| `resources/js/mobile/views/GoalsDetail.vue` | Vue |
| `resources/js/mobile/views/InvestmentDetail.vue` | Vue |
| `resources/js/mobile/views/MobileDashboard.vue` | Vue |
| `resources/js/mobile/views/MobileLoginScreen.vue` | Vue |
| `resources/js/mobile/views/MoreMenu.vue` | Vue |
| `resources/js/mobile/views/ProtectionDetail.vue` | Vue |
| `resources/js/mobile/views/RetirementDetail.vue` | Vue |
| `resources/js/mobile/views/SavingsDetail.vue` | Vue |
| `resources/js/mobile/views/VerificationCodeScreen.vue` | Vue |
| `resources/js/mobile/SettingsList.vue` | Vue |
| `resources/js/store/modules/mobileDashboard.js` | JS |
| `app/Services/Mobile/MobileDashboardAggregator.php` | PHP |
| `app/Services/AI/AiChatService.php` | PHP |
| `resources/js/mobile/VoiceInputButton.vue` | Vue |
| `resources/js/mobile/views/MobileFynChat.vue` | Vue |
| `resources/js/mobile/views/MoreMenu.vue` | Vue |
| `deploy/mobile/build-ios.sh` | Shell |

## Post-Deploy

Clear server cache after deploying:
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear
```
