# Phase 2b Deploy Notes — Capacitor iOS App

**Date:** 10 March 2026
**Branch:** `feature/mobile-app-phase0`
**Version:** v0.8.6-phase2b

---

## Summary

Phase 2b builds the complete Capacitor iOS app with 44 new Vue components across 5 mobile screens, biometric auth, push notifications, voice input, and mortgage rate alerts.

**Key additions:**
- Capacitor 6.x project with iOS platform (17 native plugins)
- 5-tab mobile navigation: Home, Fyn, Learn, Goals, More
- Mobile auth: login, verification code, Face ID/Touch ID biometric setup
- App lifecycle: background/foreground handling, token refresh, biometric re-auth
- Full-screen Fyn chat with voice input (speech recognition)
- Goals list with progress rings, detail view, contribution FAB, milestone celebrations
- Learn Hub with 8 topics and real external guide links (MoneyHelper, HMRC, Pension Wise)
- More menu with module summaries, profile, settings, notification preferences
- Push notification frontend (permission flow, token registration, in-app toast)
- Mortgage rate alerts backend (90/60/30 day warnings for fixed rate expiry)
- iOS app icons (13 sizes), Info.plist permissions, Xcode project

## Pre-Deploy: Dependencies

### PHP (Composer)
No new Composer packages required.

### JavaScript (npm)
17 new packages — all Capacitor 6.x ecosystem. Included in the build output, no server-side npm install needed.

| Package | Version | Purpose |
|---------|---------|---------|
| `@capacitor/core` | ^6.2.1 | Capacitor runtime |
| `@capacitor/cli` | ^6.2.1 | Build tooling (devDependency) |
| `@capacitor/ios` | ^6.2.1 | iOS platform |
| `@capacitor/app` | ^6.0.3 | App state lifecycle |
| `@capacitor/browser` | ^6.0.6 | In-app browser (SFSafariViewController) |
| `@capacitor/device` | ^6.0.3 | Device info |
| `@capacitor/haptics` | ^6.0.3 | Haptic feedback |
| `@capacitor/keyboard` | ^6.0.4 | Keyboard management |
| `@capacitor/local-notifications` | ^6.1.3 | Local notifications |
| `@capacitor/network` | ^6.0.4 | Network status |
| `@capacitor/preferences` | ^6.0.4 | Native key-value storage |
| `@capacitor/push-notifications` | ^6.0.5 | Push notification handling |
| `@capacitor/share` | ^6.0.4 | Native share sheet |
| `@capacitor/splash-screen` | ^6.0.4 | Splash screen |
| `@capacitor/status-bar` | ^6.0.3 | Status bar styling |
| `@capacitor-community/speech-recognition` | ^6.0.1 | Voice input |
| `@capgo/capacitor-native-biometric` | ^6.0.4 | Face ID / Touch ID |

### Environment Variables
No new environment variables required. FCM keys from Phase 2a are reused.

## Deployment Type: iOS App (Not Web Server)

**Phase 2b is primarily an iOS app build — NOT a web server deployment.** The web server changes are minimal (1 migration + 3 modified PHP files). The bulk of the work is frontend components bundled into the Capacitor iOS app.

### Web Server Upload (minimal)

**NEW PHP files:**

| File | Purpose |
|------|---------|
| `database/migrations/2026_03_10_200004_add_mortgage_rate_alerts_to_notification_preferences.php` | Adds `mortgage_rate_alerts` boolean column |
| `app/Console/Commands/SendMortgageRateAlerts.php` | Artisan command for 90/60/30 day alerts |
| `app/Notifications/MortgageRateAlertNotification.php` | Laravel notification class |

**MODIFIED PHP files:**

| File | What changed |
|------|-------------|
| `app/Models/NotificationPreference.php` | Added `mortgage_rate_alerts` to fillable/casts/defaults |
| `app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php` | Added validation rule |
| `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php` | Added to show response |
| `app/Console/Kernel.php` | Scheduled mortgage rate alerts at 09:30 daily |

**MODIFIED config/build files (web server NOT affected):**

| File | What changed |
|------|-------------|
| `vite.config.js` | Added `/images/` to Rollup externals |
| `resources/js/router/index.js` | Added 13 mobile routes under `/m/` prefix |
| `resources/js/store/index.js` | Added native-aware storage backend, mobileNotifications module |
| `resources/js/store/modules/aiChat.js` | Added prefilledPrompt, abortController |
| `resources/js/services/aiChatService.js` | Added AbortController signal to SSE fetch |
| `resources/js/services/tokenStorage.js` | Activated Capacitor Preferences for native storage |
| `resources/js/utils/platform.js` | Upgraded to @capacitor/core SDK |

### Frontend Build (rebuild required for web)

```bash
./deploy/fynla-org/build.sh
```

Upload entire `public/build/` directory. The new mobile components are lazy-loaded and only fetched when accessed via `/m/` routes, so web performance is unaffected.

### iOS App Build

```bash
./deploy/mobile/build-ios.sh
```

This generates `index.html` from Vite manifest, copies public assets, and runs `npx cap sync ios`. Then open Xcode:

```bash
open ios/App/App.xcworkspace
```

Build, archive, and upload to TestFlight from Xcode.

## New Frontend Files (47 files)

### Mobile Views (11)

| File | Screen |
|------|--------|
| `resources/js/mobile/views/MobileLoginScreen.vue` | Email/password login |
| `resources/js/mobile/views/VerificationCodeScreen.vue` | 6-digit code entry |
| `resources/js/mobile/views/MobileDashboard.vue` | Home tab — net worth, alerts, modules |
| `resources/js/mobile/views/MobileFynChat.vue` | Full-screen Fyn chat |
| `resources/js/mobile/views/MobileGoalsList.vue` | Goals list with filter chips |
| `resources/js/mobile/views/MobileGoalDetail.vue` | Goal detail with progress ring |
| `resources/js/mobile/views/LearnHub.vue` | 8-topic learning grid |
| `resources/js/mobile/views/LearnTopicDetail.vue` | Topic detail with guides |
| `resources/js/mobile/views/MoreMenu.vue` | Profile, modules, settings |
| `resources/js/mobile/views/ModuleSummary.vue` | Hero metric + Fyn one-liner |
| `resources/js/mobile/views/NotificationSettings.vue` | 8 notification toggles |

### Mobile Components (18)

| File | Purpose |
|------|---------|
| `resources/js/mobile/BiometricPrompt.vue` | Face ID / Touch ID setup |
| `resources/js/mobile/ChatBubble.vue` | User/assistant chat bubbles |
| `resources/js/mobile/FynInsightCard.vue` | Fyn AI insight card |
| `resources/js/mobile/InAppNotificationToast.vue` | Top notification banner |
| `resources/js/mobile/MobileAlertsList.vue` | Dashboard alerts |
| `resources/js/mobile/MobileHeader.vue` | Back button + title + action slot |
| `resources/js/mobile/MobileNetWorthCard.vue` | Net worth with sparkline |
| `resources/js/mobile/MobileTabBar.vue` | 5-tab bottom bar |
| `resources/js/mobile/ModuleSummaryCard.vue` | Module metric card |
| `resources/js/mobile/ProfileCard.vue` | Initials avatar + user info |
| `resources/js/mobile/PullToRefresh.vue` | Touch gesture pull-to-refresh |
| `resources/js/mobile/PushPermissionPrompt.vue` | Bottom sheet permission prompt |
| `resources/js/mobile/SettingsList.vue` | Settings rows list |
| `resources/js/mobile/ShareButton.vue` | Native share / clipboard fallback |
| `resources/js/mobile/SuggestedPrompts.vue` | 4 chat prompt cards |
| `resources/js/mobile/ToolExecutionStatus.vue` | "Fyn is analysing..." spinner |
| `resources/js/mobile/TypingIndicator.vue` | Three animated dots |
| `resources/js/mobile/VoiceInputButton.vue` | Mic with speech recognition |

### Subdirectory Components (15)

| File | Purpose |
|------|---------|
| `resources/js/mobile/layouts/MobileLayout.vue` | Shell with header + tab bar |
| `resources/js/mobile/charts/NetWorthSparkline.vue` | ApexCharts sparkline |
| `resources/js/mobile/charts/ProgressRing.vue` | SVG circular progress |
| `resources/js/mobile/goals/ContributionFAB.vue` | Bottom sheet FAB |
| `resources/js/mobile/goals/MilestoneOverlay.vue` | Confetti celebration |
| `resources/js/mobile/goals/MobileGoalCard.vue` | Goal card with ring |
| `resources/js/mobile/icons/TabIconHome.vue` | Home tab icon |
| `resources/js/mobile/icons/TabIconFyn.vue` | Fyn tab icon |
| `resources/js/mobile/icons/TabIconLearn.vue` | Learn tab icon |
| `resources/js/mobile/icons/TabIconGoals.vue` | Goals tab icon |
| `resources/js/mobile/icons/TabIconMore.vue` | More tab icon |
| `resources/js/mobile/learn/LearnGuideLink.vue` | External link via Browser |
| `resources/js/mobile/learn/LearnInfoCard.vue` | Info card with popup |
| `resources/js/mobile/learn/LearnInfoPopup.vue` | Bottom sheet popup |
| `resources/js/mobile/learn/LearnTopicCard.vue` | Topic card |

### JS / Store Files (3)

| File | Purpose |
|------|---------|
| `resources/js/mobile/appLifecycle.js` | Background/foreground, biometric re-auth |
| `resources/js/mobile/learn/learnTopics.js` | 8 topic definitions with external URLs |
| `resources/js/store/modules/mobileNotifications.js` | Push notification state management |

## Post-Deploy: SSH Commands (Web Server)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration (adds mortgage_rate_alerts column)
php artisan migrate

# Clear caches and optimise
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize

# Reseed
php artisan db:seed
```

## Verification Checklist

### Web Server (backend)
- [ ] Migration runs: `notification_preferences` has `mortgage_rate_alerts` column
- [ ] `php artisan notifications:mortgage-rate-alerts` runs without error
- [ ] `GET /api/v1/mobile/notifications/preferences` includes `mortgage_rate_alerts`
- [ ] `PUT /api/v1/mobile/notifications/preferences` accepts `mortgage_rate_alerts`
- [ ] Existing features unaffected (login, dashboard, preview personas, AI chat)

### iOS App (Xcode)
- [ ] Project builds without errors in Xcode
- [ ] App icons appear in asset catalog (13 sizes)
- [ ] Info.plist has permissions: Microphone, Speech Recognition, Face ID
- [ ] App launches with splash screen in Simulator
- [ ] Login screen appears at `/m/login`
- [ ] Can navigate through all 5 tabs
- [ ] Dashboard loads data from API
- [ ] Fyn chat sends messages and receives SSE responses
- [ ] Voice input activates speech recognition
- [ ] Learn Hub shows 8 topics with external links
- [ ] Goals list renders with progress rings
- [ ] Goal detail shows contribution FAB
- [ ] More menu shows profile, module grid, settings
- [ ] Module summary shows hero metric + Fyn one-liner
- [ ] Notification settings shows 8 toggles
- [ ] Biometric prompt appears after verification code

### Frontend Build
- [ ] `npm run build` succeeds (no errors)
- [ ] `./deploy/mobile/build-ios.sh` succeeds (cap sync completes)
- [ ] `./deploy/fynla-org/build.sh` succeeds (web deploy build)

## Rollback

### Web Server
1. Restore previous versions of 4 modified PHP files
2. New PHP files are inert without the migration — can be left or removed
3. Migration rollback: `php artisan migrate:rollback --step=1` (drops column)
4. Clear caches: `php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize`

### iOS App
No rollback needed — not yet published to App Store. TestFlight builds can be expired.

## Test Results

- **Backend tests:** 1866 passed (7888 assertions)
- **Frontend build:** Vite build succeeds (197 precache entries, 408 modules)
- **iOS sync:** 14 Capacitor plugins detected and synced
- **Duration:** 186s (backend), 29s (frontend build)

## Phase 2b Metrics Snapshot

| Metric | Phase 2a | Phase 2b | Delta |
|--------|----------|----------|-------|
| Vue Components | 403 | 447 | +44 |
| JS Files (new) | 0 | +3 | +3 |
| PHP Files (new) | 0 | +3 | +3 |
| Test Files (new) | 0 | +2 | +2 |
| Test Count | 1861 | 1866 | +5 |
| npm Packages | 0 | +17 | +17 |
| Mobile Routes | 0 | +13 | +13 |
| Capacitor Plugins | 0 | 14 | +14 |
| iOS App Icons | 0 | 13 | +13 |
| Scheduled Commands | 2 | 3 | +1 |

## iOS App Configuration

| Setting | Value |
|---------|-------|
| App ID | `org.fynla.app` |
| App Name | Fynla |
| Web Dir | `public/build` |
| Capacitor | 6.x |
| Min iOS | Set by Xcode default |
| Splash BG | `#F7F6F4` (eggshell) |
| Splash Spinner | `#E83E6D` (raspberry) |
| Keyboard Resize | `body` |
| Push Presentation | badge, sound, alert |
