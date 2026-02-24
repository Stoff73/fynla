# Fynla Mobile App Strategy

## Context

Fynla is a UK financial planning web app (Laravel 10 + Vue 3 + MySQL 8) with 315 mobile-responsive Vue components, 200+ REST API endpoints, and all calculations server-side. The goal is a dedicated iOS/Android mobile app with native features (biometrics, push notifications, camera, swipe gestures) ready before v1.0 launch.

**Key facts:**
- **Audience:** Consumers (individuals managing their own finances) -- App Store presence matters for trust
- **Test devices:** Both iPhone and Android available
- **Dev accounts:** Neither Apple nor Google accounts exist yet -- must create both
- **Timeline:** Before v1.0 launch -- mobile app is part of the overall product release
- **Developer:** Solo, manual deployment via SiteGround

---

## Approach Comparison

| Factor | PWA Only | Capacitor (Recommended) | React Native |
|--------|----------|------------------------|--------------|
| **Code reuse** | 100% | 95%+ | ~5% (utilities only) |
| **Timeline to MVP** | 1-2 weeks | 7-9 weeks | 13-14 months |
| **App Store presence** | No (iOS), Yes via TWA (Android) | Yes (both) | Yes (both) |
| **Biometrics** | WebAuthn (good) | Native Face ID/Touch ID (best) | Native (best) |
| **Push notifications** | Web Push (iOS unreliable) | Native APNs + FCM (reliable) | Native (reliable) |
| **Camera/doc scanning** | Basic camera only | Native camera + scanner | Full native |
| **Offline support** | Service worker cache | Service worker + native | Full native |
| **Native feel** | Web-like | 90% native | 100% native |
| **Ongoing maintenance** | Zero extra | Low (~10% overhead) | High (~50% overhead) |
| **App Store rejection risk** | N/A | Low (with native features) | None |

## Recommendation: PWA Foundation + Capacitor Wrap (Phased)

**Why this approach wins for Fynla:**

1. **The app is already 90% ready** -- 315 mobile-responsive components, SPA architecture, clean API layer with 200+ REST endpoints
2. **PWA work is additive** -- everything built in Phase 1 carries forward to Capacitor
3. **Capacitor reuses the entire Vue codebase** -- no rewrite, just a native shell + bridge plugins
4. **React Native would take 13+ months** to rebuild 320 components, 24 charts, 22 forms, and 21 store modules from scratch, then add permanent 40-50% maintenance overhead. The 24 ApexCharts alone (waterfall, efficient frontier, Monte Carlo projections) would take 8-12 weeks to reimplement
5. **Apple App Store requires native features** -- Capacitor + biometrics/push/camera satisfies this (pure web wrappers get rejected)
6. **Consumer trust requires App Store presence** -- competitors (PensionBee, MoneyHub, Plum) are all in the stores. Users expect to "download" a financial app

---

## Pre-requisites (Do Before Phase 1)

### Account Setup

| Account | Cost | Approval Time | URL | Why Needed |
|---------|------|---------------|-----|------------|
| Apple Developer Program | $99/year | 24-48 hours | https://developer.apple.com/programs/ | iOS builds, TestFlight, App Store submission, APNs key for push notifications |
| Google Play Console | $25 one-time | Instant | https://play.google.com/console | Android builds, Play Store submission |
| Firebase Project | Free | Instant | https://console.firebase.google.com/ | Push notifications (FCM handles both iOS + Android) |

**Apple Developer Account is on the critical path** -- it blocks Phase 4 (APNs key for push notifications) and Phase 8 (App Store submission). Create this first.

### Development Tools

| Tool | Purpose | Install |
|------|---------|---------|
| Xcode (latest) | iOS builds, simulator, signing | Mac App Store |
| Android Studio | Android builds, emulator, signing | https://developer.android.com/studio |
| CocoaPods | Capacitor iOS dependency manager | `sudo gem install cocoapods` |
| Java 17 | Android Gradle builds | `brew install openjdk@17` |

### Firebase Project Setup

1. Create project at https://console.firebase.google.com/
2. Add iOS app (bundle ID: `org.fynla.app`) -- download `GoogleService-Info.plist`
3. Add Android app (package: `org.fynla.app`) -- download `google-services.json`
4. Generate APNs key in Apple Developer account -> upload to Firebase Cloud Messaging settings
5. Note the Firebase Server Key for `.env`

---

## Phase 1: PWA Foundation (Week 1-2)

**Goal:** Make the web app installable, fast-loading, and app-like on mobile.

### 1.1 Web App Manifest
- **Create** `public/manifest.webmanifest` with app name, icons, theme colour (#1E40AF), standalone display mode
- **Generate** icon assets from existing `resources/js/assets/logoTransparent.png`:
  - 192x192 PNG (manifest standard)
  - 512x512 PNG (manifest standard)
  - 512x512 maskable PNG (with safe zone padding for adaptive icons)
  - 180x180 PNG (Apple touch icon)
  - 1024x1024 PNG (App Store -- needed later in Phase 8)
- **Place** in new `public/icons/` directory

### 1.2 Update `app.blade.php`

Current file (`resources/views/app.blade.php`) has a minimal 20-line `<head>`. Add:
- `<link rel="manifest" href="/manifest.webmanifest">`
- `<meta name="theme-color" content="#1E40AF">`
- `<meta name="apple-mobile-web-app-capable" content="yes">`
- `<meta name="apple-mobile-web-app-status-bar-style" content="default">`
- `<meta name="apple-mobile-web-app-title" content="Fynla">`
- `<link rel="apple-touch-icon" href="/icons/apple-touch-icon-180.png">`
- Update viewport: add `viewport-fit=cover` for notch/safe area handling

### 1.3 Service Worker (Network-First for Financial Data)
- **Create** `public/sw.js` with network-first strategy
- **Cache:** App shell (HTML, JS bundle, CSS), static assets, icons, fonts
- **NEVER cache:** API responses (`/api/*`) -- financial data must always be fresh
- **Offline fallback:** Show cached app shell with "offline" state when no network
- **Register** in `resources/js/app.js` (production only, skip during Vite HMR dev)
- Caching strategy rationale: Vite-built assets have content hashes in filenames (cache-forever), while the app shell HTML uses network-first (always get latest)

### 1.4 Update `.htaccess`
In `deploy/fynla-org/.htaccess`:
- Service worker `Cache-Control: no-cache, no-store, must-revalidate` headers (browsers must always check for SW updates)
- Manifest `Content-Type: application/manifest+json`
- Add `worker-src 'self'` to Content-Security-Policy
- Change `Permissions-Policy camera=()` to `camera=(self)` for future camera access

### Files to create:
- `public/manifest.webmanifest`
- `public/sw.js`
- `public/icons/` (6 icon sizes)

### Files to modify:
- `resources/views/app.blade.php` -- PWA meta tags
- `deploy/fynla-org/.htaccess` -- CSP and caching headers
- `resources/js/app.js` -- service worker registration

---

## Phase 2: Capacitor Integration (Week 2-3)

**Goal:** Wrap the Vue SPA in native iOS/Android shells.

### 2.1 Install and Configure Capacitor
```bash
npm install @capacitor/core @capacitor/cli
npx cap init "Fynla" "org.fynla.app" --web-dir public/build
npx cap add ios
npx cap add android
```

This creates `ios/` and `android/` directories at the project root containing native Xcode and Android Studio projects.

### 2.2 Capacitor Config (`capacitor.config.ts`)
- `appId: 'org.fynla.app'`
- `appName: 'Fynla'`
- `webDir: 'public/build'` (where Vite outputs the built SPA)
- `server.androidScheme: 'https'` (avoid mixed-content issues)
- `server.iosScheme: 'https'`
- Plugin config for SplashScreen (auto-hide disabled, custom background #F9FAFB)
- Development mode: `server.url: 'http://localhost:8000'` (load from Laravel dev server)

### 2.3 Platform Detection Utility
- **Create** `resources/js/utils/platform.js`
- Exports: `isNative()`, `isIOS()`, `isAndroid()`, `isWeb()`
- Uses `@capacitor/core`'s `Capacitor.isNativePlatform()` and `Capacitor.getPlatform()`
- Used throughout codebase to branch behaviour between web and native

### 2.4 Build Pipeline

The key challenge: Capacitor cannot use Laravel's Blade templates (`app.blade.php` with `@vite()` directives). The native app needs a static `index.html`.

- **Create** `deploy/capacitor/build.sh`:
  - Sets `VITE_BASE_PATH=/` (not `/build/`)
  - Sets `VITE_API_BASE_URL=https://fynla.org` (all API calls go to production)
  - Sets `VITE_ROUTER_BASE=/`
  - Sets `VITE_PLATFORM=capacitor`
  - Runs `npm run build`
  - Runs the index generator
  - Runs `npx cap sync` (copies web assets to native projects)

- **Create** `deploy/capacitor/generate-index.js`:
  - Reads `public/build/.vite/manifest.json` (Vite's build manifest)
  - Generates a static `public/build/index.html` referencing correct CSS and JS asset paths
  - Includes PWA meta tags (from Phase 1) but NOT CSRF token
  - Includes Capacitor-specific viewport: `viewport-fit=cover`

- **Create** `deploy/capacitor/build-release.sh`:
  - Same as build.sh but optimised for release
  - Outputs instructions for opening Xcode/Android Studio to archive

### 2.5 CORS Configuration
- Native apps request from `capacitor://localhost` (iOS) / `http://localhost` (Android)
- These origins need to be in `ALLOWED_ORIGINS` env var on the production server
- Note: native apps don't enforce CORS browser-side, but the server's `config/cors.php` still validates the `Origin` header

### Files to create:
- `capacitor.config.ts`
- `resources/js/utils/platform.js`
- `deploy/capacitor/build.sh`
- `deploy/capacitor/build-release.sh`
- `deploy/capacitor/generate-index.js`

### Files to modify:
- `package.json` -- add Capacitor dependencies
- `.env.production` -- add `capacitor://localhost,http://localhost` to ALLOWED_ORIGINS
- `.gitignore` -- add `ios/`, `android/` platform directories

---

## Phase 3: Auth + Biometrics (Week 3-5)

**Goal:** Secure token storage and Face ID / Touch ID login.

This is the most architecturally significant phase because it touches the core auth flow.

### Plugins Required:
- `@capacitor/preferences` -- persistent key-value storage (replaces sessionStorage)
- `capacitor-native-biometric` -- Face ID / Touch ID / Fingerprint
- `@capacitor/app` -- app lifecycle events

### 3.1 Token Storage Abstraction

Currently, auth tokens are stored synchronously in `sessionStorage` (cleared when browser/tab closes). On native, tokens must persist across app restarts.

- **Create** `resources/js/services/tokenStorage.js`
- On native: uses `@capacitor/preferences` (iOS UserDefaults / Android SharedPreferences, persistent)
- On web: uses `sessionStorage` (current behaviour, completely unchanged)
- Methods: `getToken()`, `setToken()`, `removeToken()` -- all async (returns Promises)
- Future enhancement: upgrade to `capacitor-secure-storage-plugin` for iOS Keychain / Android EncryptedSharedPreferences

### 3.2 Modify Auth Flow (Critical Changes)

**`resources/js/services/authService.js`** (lines 81-91):
- Replace `sessionStorage.setItem/getItem('auth_token')` with `tokenStorage` abstraction
- `setToken()` and `getToken()` become async
- `isAuthenticated()` becomes async

**`resources/js/services/api.js`** (lines 64-68):
- Request interceptor becomes async (Axios supports Promise-returning interceptors)
- `sessionStorage.getItem('auth_token')` -> `await tokenStorage.getToken()`
- Set `withCredentials: false` on native platforms (CSRF cookies don't work from `capacitor://localhost` origin)
- Current `withCredentials: true` (line 60) is for Sanctum SPA cookie auth, which only works with matching domain

**`resources/js/store/modules/auth.js`**:
- Initial state: `token: null` (currently `authService.getToken()` which is now async and can't run in module scope)
- New `initAuth` action that loads token asynchronously on app startup
- App waits for `initAuth` to complete before rendering authenticated content

**`resources/js/services/sessionLifecycleService.js`** (line 51):
- Replace `sessionStorage.getItem('auth_token')` with `tokenStorage.getToken()`
- On native: use `@capacitor/app` `appStateChange` event for background/foreground detection
- Keep 15-minute inactivity timeout (activity events already include `touchstart` -- line 60)
- The `sendBeacon` logout pattern is not available in Capacitor WebView -- use regular API call on `appStateChange` instead

**`resources/js/app.js`**:
- Add async `initAuth` dispatch before `app.mount('#app')` (wait for token load)
- On native: add `App.addListener('backButton')` for Android hardware back button
- Wrap in platform check: `if (isNative()) { ... }`

**`resources/js/bootstrap.js`**:
- `window.axios.defaults.withCredentials` conditional: `!isNative()` (true for web, false for native)

### 3.3 Biometric Authentication Flow

Biometrics are layered ON TOP of existing auth, not a replacement:

1. **First login**: Normal email + password -> email verification code -> MFA (if enabled) -> Bearer token issued
2. **Enable biometrics**: After successful login, prompt: "Enable Face ID / Touch ID for faster login?"
3. **If enabled**: Store email + "biometric enabled" flag in Preferences
4. **Subsequent app opens**: Check if biometric enabled -> present Face ID/Touch ID prompt -> if token still valid (check via `GET /api/auth/user`), resume session -> if expired (server returns 401), fall back to full login
5. **Biometrics DO NOT bypass MFA/email verification** -- they only resume an existing valid session (same security model as banking apps)

- **Create** `resources/js/services/biometricService.js`
  - `isAvailable()` -- checks device capability (Face ID, Touch ID, or fingerprint)
  - `promptBiometric()` -- triggers the native biometric prompt
  - `enableBiometricLogin(email)` -- stores preference
  - `disableBiometricLogin()` -- removes preference
  - `getBiometricLoginEmail()` -- retrieves stored email for display

### 3.4 Native Session Lifecycle

The security model changes on native:
- **Web**: Token in sessionStorage clears on browser/tab close (automatic cleanup)
- **Native**: Token persists in Preferences (no auto-cleanup on app close)

Mitigations:
- Keep 15-minute inactivity timeout (works identically on native with `touchstart` events)
- On app background -> foreground after extended period: require biometric re-authentication
- On app background -> foreground after short period: resume silently
- Server-side token expiration (8 hours per `config/sanctum.php`) is the ultimate safety net
- Explicit logout (via Navbar) clears token from Preferences

### Files to create:
- `resources/js/services/tokenStorage.js`
- `resources/js/services/biometricService.js`

### Files to modify:
- `resources/js/services/authService.js` -- async token storage
- `resources/js/services/api.js` -- async interceptor, conditional withCredentials
- `resources/js/store/modules/auth.js` -- async initAuth action
- `resources/js/services/sessionLifecycleService.js` -- native lifecycle
- `resources/js/app.js` -- async boot sequence, back button
- `resources/js/bootstrap.js` -- conditional withCredentials

---

## Phase 4: Push Notifications (Week 5-6)

**Goal:** Native push notifications for financial reminders and alerts.

**Prerequisite:** Apple Developer Account must be active (for APNs key).

### Plugin: `@capacitor/push-notifications` (FCM + APNs)

### 4.1 Backend Infrastructure

**New migration:** `create_device_tokens_table`
```
id, user_id, token (text), platform (enum: ios/android/web), is_active (boolean), created_at, updated_at
```

**New files:**
- `app/Models/DeviceToken.php` -- Eloquent model with `belongsTo(User)`
- `app/Http/Controllers/Api/DeviceTokenController.php` -- register/update/remove device tokens
- `app/Services/Notifications/PushNotificationService.php` -- sends notifications via Firebase Cloud Messaging
- `database/migrations/xxxx_create_device_tokens_table.php`
- `app/Console/Commands/SendPushNotifications.php` -- scheduled artisan command that checks for notification triggers

**New routes in `routes/api.php`:**
- `POST /api/device-tokens` -- register device token (called after login + permission grant)
- `DELETE /api/device-tokens/{token}` -- unregister device token (called on logout)
- Both behind `auth:sanctum` middleware

**PHP package:** `kreait/firebase-php` (official Firebase Admin SDK for PHP) -- or raw HTTP calls to FCM v1 API

### 4.2 Notification Triggers

| Event | When | Priority | Source Check |
|-------|------|----------|-------------|
| Policy renewal approaching | 30 and 7 days before `expiry_date` | High | `ProtectionProfile` policies |
| Tax year-end reminder | March (30 and 7 days before April 5) | High | Calendar-based |
| ISA allowance approaching limit | After ISA deposit totals >80% of £20,000 | Medium | `ISAAllowanceTracking` |
| Pension Annual Allowance warning | After contribution update exceeds threshold | Medium | `AnnualAllowanceChecker` service |
| Goal milestone reached | After goal progress crosses 25/50/75/100% | Medium | `GoalContribution` updates |
| Action item reminder | Weekly digest for pending recommendations | Low | `InvestmentRecommendation` with pending status |

### 4.3 Frontend Service
- **Create** `resources/js/services/pushNotificationService.js`
- Request notification permission after first successful login (not aggressively on install)
- On permission granted: receive device token from FCM/APNs, send to backend via `POST /api/device-tokens`
- Handle incoming notifications when app is in foreground (show in-app banner)
- Handle notification taps from background: parse `data.route` field, call `router.push(route)`
- On logout: call `DELETE /api/device-tokens/{token}` to unregister

### 4.4 Notification Payload Format
```json
{
  "notification": {
    "title": "Policy Renewal Due",
    "body": "Your life insurance policy expires in 30 days. Review your coverage."
  },
  "data": {
    "route": "/protection/policy/life/42",
    "type": "policy_renewal",
    "entity_id": "42"
  }
}
```

### 4.5 Cron Schedule
- `php artisan push:send` -- runs daily via SiteGround cron job
- Checks all notification triggers, deduplicates (don't send same notification twice), sends via FCM
- Logs sent notifications to avoid duplicates (add `notification_logs` tracking or use a `last_notified_at` column)

### Files to create:
- `app/Models/DeviceToken.php`
- `app/Http/Controllers/Api/DeviceTokenController.php`
- `app/Services/Notifications/PushNotificationService.php`
- `database/migrations/xxxx_create_device_tokens_table.php`
- `app/Console/Commands/SendPushNotifications.php`
- `resources/js/services/pushNotificationService.js`

### Files to modify:
- `routes/api.php` -- device token routes
- `app/Console/Kernel.php` -- schedule `push:send` command daily
- `app/Http/Middleware/PreviewWriteInterceptor.php` -- add device-token routes to EXCLUDED_ROUTES

---

## Phase 5: Native UX Enhancements (Week 6-7)

**Goal:** Make the app feel native, not like a wrapped website.

### Plugins:
- `@capacitor/haptics` -- tactile feedback
- `@capacitor/keyboard` -- keyboard appearance/dismiss handling
- `@capacitor/status-bar` -- status bar colour/style control
- `@capacitor/splash-screen` -- launch screen management
- `@capacitor/network` -- online/offline detection

### 5.1 Safe Areas and Status Bar
- Update viewport meta in generated `index.html`: `viewport-fit=cover`
- Add CSS `env(safe-area-inset-*)` padding to body, Navbar (`resources/js/components/Navbar.vue`), and bottom navigation
- Configure status bar colour to match design system (#1E40AF primary blue)
- Custom splash screen with Fynla logo on #F9FAFB background

### 5.2 Haptic Feedback
- **Create** `resources/js/composables/useHaptics.js`
- Form submissions (success): `ImpactStyle.Medium`
- Validation errors: `NotificationType.Error`
- Navigation taps: `ImpactStyle.Light`
- Delete confirmations: `NotificationType.Warning`
- Only fires on native platforms (no-op wrapper on web via `isNative()` check)

### 5.3 Pull-to-Refresh
- **Create** `resources/js/components/Native/PullToRefresh.vue`
- Wraps child content with touch-based pull-to-refresh behaviour
- Active only on native platforms
- Triggers data refresh via Vuex `dispatch` for the current module
- Integrated into `AppLayout.vue` main content slot

### 5.4 Offline Indicator
- **Create** `resources/js/store/modules/network.js` -- state: `{ isOnline: true, connectionType: 'wifi' }`
- **Create** `resources/js/components/Native/OfflineBanner.vue`
  - Blue banner (not amber/orange -- per design system rules)
  - Text: "You are currently offline. Some features may be unavailable."
  - Shows below Navbar when `isOnline === false`
- Modify `resources/js/services/api.js` to check network state and reject requests immediately when offline with a user-friendly message (instead of waiting for network timeout)

### 5.5 Android Back Button
- In `resources/js/app.js`: listen for `backButton` event from `@capacitor/app`
- Integrate with Vue Router: `canGoBack ? router.back() : App.exitApp()`
- Only register on native Android

### 5.6 Keyboard Handling
- Use `@capacitor/keyboard` to detect keyboard open/close
- Adjust scroll position so form inputs aren't obscured by keyboard
- Add "Done" button above keyboard on iOS for numeric inputs (currency fields)

### Files to create:
- `resources/js/composables/useHaptics.js`
- `resources/js/components/Native/PullToRefresh.vue`
- `resources/js/components/Native/OfflineBanner.vue`
- `resources/js/store/modules/network.js`

### Files to modify:
- `resources/js/layouts/AppLayout.vue` -- add OfflineBanner, PullToRefresh wrapper
- `resources/js/store/index.js` -- register network module
- `resources/js/app.js` -- back button handler, status bar init, keyboard listeners
- `resources/css/app.css` -- safe area padding CSS

---

## Phase 6: Camera + Document Scanning (Week 7)

**Goal:** Use device camera for document uploads (policy PDFs, pension statements).

### Plugin: `@capacitor/camera`

### 6.1 Integration with Existing Upload
- **Modify** `resources/js/components/Shared/DocumentUploadModal.vue`
- Add "Scan Document" button (visible only on native via `isNative()`)
- On tap: opens device camera via `Camera.getPhoto()` with `CameraSource.Camera`
- Returns image as base64 data URL
- Convert to File object and pass to existing upload handler
- Backend unchanged: `DocumentController` already accepts file uploads, `AIExtractionService` (Claude Vision) processes them
- Also offer "Choose from Library" option using `CameraSource.Photos`

### Permissions:
- iOS: Add `NSCameraUsageDescription` to `Info.plist` ("Fynla uses your camera to scan financial documents")
- Android: `CAMERA` permission in `AndroidManifest.xml` (Capacitor handles runtime permission request)

---

## Phase 7: Deep Linking (Week 7-8)

**Goal:** Open specific app screens from notifications, emails, and marketing links.

### Plugin: `@capacitor/app` (already installed from Phase 3)

### 7.1 URL Scheme Registration
- **Custom scheme:** `fynla://` (for notification taps and internal routing)
- **Universal links (iOS):** `https://fynla.org/app/*` -- requires `apple-app-site-association` file on server
- **App Links (Android):** `https://fynla.org/app/*` -- requires `assetlinks.json` file on server

Configuration:
- iOS: `ios/App/App/Info.plist` -- URL types + associated domains entitlement
- Android: `android/app/src/main/AndroidManifest.xml` -- intent filters for both custom scheme and https
- Server: `public/.well-known/apple-app-site-association` and `public/.well-known/assetlinks.json`

### 7.2 Route Mapping
- Deep link paths match Vue Router paths directly: `fynla://protection/policy/life/42` -> `/protection/policy/life/42`
- Listen for `appUrlOpen` events in `resources/js/app.js` -> `router.push(path)`
- Push notification payloads include `data.route` field for direct navigation
- Email links (e.g., "View your protection report") use universal links that open the app if installed, web if not

---

## Phase 8: App Store Submission (Week 8-9)

### Why App Store Presence Matters for Fynla

Consumer users managing sensitive financial data expect to "download an app" from a trusted store:
- **Perceived legitimacy** -- "Apple approved it" matters to consumers
- **Discoverability** -- users search App Store for "financial planning UK"
- **Trust signal** -- competitors (PensionBee, MoneyHub, Plum) are all in the App Store
- **Familiar install path** -- no "Add to Home Screen" friction

### Apple App Store Requirements

| Requirement | Status / Action |
|------------|----------------|
| Apple Developer Account | Create in Pre-requisites ($99/year, 24-48hr approval) |
| App icons (1024x1024) | Generated in Phase 1 from logo |
| Splash/Launch Screen | Configured in Phase 5 via LaunchScreen.storyboard |
| Privacy Policy URL | Exists: https://fynla.org/privacy |
| App Privacy Labels | Financial data, email (linked to identity) -- fill in App Store Connect |
| Test credentials for review | Create a dedicated Apple Review test account (NOT chris@fynla.org -- Apple reviewers should not use a real account) |
| Screenshots | 6.7" (iPhone 15 Pro Max), 6.5" (iPhone 11 Pro Max), 5.5" (iPhone 8 Plus) -- capture from physical iPhone |
| Native feature justification | Biometrics (Phase 3), Push (Phase 4), Haptics (Phase 5), Camera (Phase 6) |
| Export compliance | Uses HTTPS (standard encryption exemption applies -- select "No" for proprietary encryption) |
| App category | Finance |

### Google Play Requirements

| Requirement | Status / Action |
|------------|----------------|
| Google Play Developer Account | Create in Pre-requisites ($25 one-time, instant) |
| Target API Level | Android 14 (API 34) or higher |
| App Bundle format | AAB (not APK) -- `./gradlew bundleRelease` |
| Content Rating | IARC questionnaire (financial app, no gambling/violence) |
| Data Safety Form | Declare: collects email, financial data; encrypted in transit; not shared with third parties |
| Screenshots | Phone + 7" tablet + 10" tablet -- capture from physical Android device |

### Build and Sign for Release

**iOS:**
1. Open `ios/App/App.xcworkspace` in Xcode
2. Set signing team (Apple Developer Account) and bundle identifier (`org.fynla.app`)
3. Select "Any iOS Device" as build target
4. Product -> Archive -> Upload to App Store Connect
5. Create TestFlight build for internal testing first
6. Submit for App Store Review (typical review: 24-48 hours)

**Android:**
1. Generate a release signing keystore: `keytool -genkey -v -keystore fynla-release.keystore -alias fynla -keyalg RSA -keysize 2048 -validity 10000`
2. Store keystore securely (NOT in git) -- back up to a safe location
3. Configure `android/app/build.gradle` with signing config
4. Run `cd android && ./gradlew bundleRelease`
5. Upload AAB to Google Play Console
6. Create internal testing track first, then promote to production

### Apple Rejection Mitigation

Apple rejects apps that are "merely a web view with no native functionality." The native features implemented in Phases 3-6 provide sufficient differentiation:
- Face ID / Touch ID authentication (Phase 3)
- Push notifications (Phase 4)
- Haptic feedback on interactions (Phase 5)
- Native camera for document scanning (Phase 6)

In the App Review notes, explicitly mention: "This app uses native biometric authentication (Face ID/Touch ID), push notifications for financial reminders, haptic feedback, and native camera access for document scanning."

---

## Complete Plugin Summary (12 total)

| Plugin | Phase | Purpose |
|--------|-------|---------|
| `@capacitor/core` | 2 | Core Capacitor runtime |
| `@capacitor/cli` | 2 | Build tooling and CLI commands |
| `@capacitor/preferences` | 3 | Persistent key-value storage (token persistence) |
| `capacitor-native-biometric` | 3 | Face ID / Touch ID / Fingerprint |
| `@capacitor/app` | 3, 5, 7 | App lifecycle, Android back button, deep links |
| `@capacitor/push-notifications` | 4 | FCM (Android) + APNs (iOS) push |
| `@capacitor/haptics` | 5 | Tactile haptic feedback |
| `@capacitor/keyboard` | 5 | Keyboard appearance/dismiss handling |
| `@capacitor/status-bar` | 5 | Status bar colour and style |
| `@capacitor/splash-screen` | 5 | Launch/splash screen management |
| `@capacitor/camera` | 6 | Camera capture and photo library |
| `@capacitor/network` | 5 | Online/offline network detection |

---

## Full File Inventory

### New Files to Create (22)

| File | Phase | Purpose |
|------|-------|---------|
| `public/manifest.webmanifest` | 1 | PWA web app manifest |
| `public/sw.js` | 1 | Service worker for caching and offline |
| `public/icons/` (6 icon sizes) | 1 | App icons for PWA and native |
| `capacitor.config.ts` | 2 | Capacitor project configuration |
| `resources/js/utils/platform.js` | 2 | Platform detection utility |
| `deploy/capacitor/build.sh` | 2 | Development build script for Capacitor |
| `deploy/capacitor/build-release.sh` | 2 | Release build script |
| `deploy/capacitor/generate-index.js` | 2 | Static index.html generator from Vite manifest |
| `resources/js/services/tokenStorage.js` | 3 | Token storage abstraction (web + native) |
| `resources/js/services/biometricService.js` | 3 | Biometric authentication service |
| `resources/js/services/pushNotificationService.js` | 4 | Push notification registration and handling |
| `app/Models/DeviceToken.php` | 4 | Device token Eloquent model |
| `app/Http/Controllers/Api/DeviceTokenController.php` | 4 | Device token API endpoints |
| `app/Services/Notifications/PushNotificationService.php` | 4 | FCM push notification sender |
| `database/migrations/xxxx_create_device_tokens_table.php` | 4 | Device tokens database table |
| `app/Console/Commands/SendPushNotifications.php` | 4 | Scheduled push notification command |
| `resources/js/composables/useHaptics.js` | 5 | Haptic feedback composable |
| `resources/js/components/Native/PullToRefresh.vue` | 5 | Pull-to-refresh wrapper component |
| `resources/js/components/Native/OfflineBanner.vue` | 5 | Offline status banner |
| `resources/js/store/modules/network.js` | 5 | Network connectivity Vuex module |
| `public/.well-known/apple-app-site-association` | 7 | iOS universal links config |
| `public/.well-known/assetlinks.json` | 7 | Android app links config |

### Existing Files to Modify (17)

| File | Phase | Change |
|------|-------|--------|
| `resources/views/app.blade.php` | 1 | Add PWA meta tags, manifest link, viewport-fit |
| `deploy/fynla-org/.htaccess` | 1 | CSP headers, SW caching, camera permission |
| `resources/js/app.js` | 1, 3, 5 | SW registration, async boot, back button, keyboard |
| `package.json` | 2 | Add 12 Capacitor dependencies |
| `.gitignore` | 2 | Exclude ios/, android/ native project directories |
| `.env.production` | 2 | Add Capacitor origins to ALLOWED_ORIGINS |
| `resources/js/services/authService.js` | 3 | Async token storage via tokenStorage abstraction |
| `resources/js/services/api.js` | 3, 5 | Async token interceptor, conditional withCredentials, offline check |
| `resources/js/store/modules/auth.js` | 3 | Async initAuth action, null initial token |
| `resources/js/services/sessionLifecycleService.js` | 3 | Native lifecycle via appStateChange, async token check |
| `resources/js/bootstrap.js` | 3 | Conditional withCredentials based on platform |
| `routes/api.php` | 4 | Add device-token registration/removal routes |
| `app/Console/Kernel.php` | 4 | Schedule daily push:send command |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | 4 | Exclude device-token routes from preview blocking |
| `resources/js/layouts/AppLayout.vue` | 5 | Add OfflineBanner and PullToRefresh wrapper |
| `resources/js/store/index.js` | 5 | Register network Vuex module |
| `resources/js/components/Shared/DocumentUploadModal.vue` | 6 | Add "Scan Document" camera button (native only) |
| `resources/css/app.css` | 5 | Safe area inset padding |

---

## Verification Plan

### Phase 1 (PWA):
- Run Chrome Lighthouse PWA audit on https://fynla.org -- target score 90+
- Test "Add to Home Screen" on physical iPhone (Safari) and Android (Chrome)
- Verify app launches in standalone mode (no browser chrome visible)
- Test offline: enable airplane mode, app shell should load from service worker cache
- Verify API calls always go to network (financial data never cached)
- Verify the web app continues to work identically for non-PWA users

### Phase 2 (Capacitor):
- `npx cap sync` completes without errors
- Open `ios/App/App.xcworkspace` in Xcode -> run on simulator -> app loads, navigation works
- Open `android/` in Android Studio -> run on emulator -> app loads, navigation works
- Verify all API calls reach the production server from the native app
- Test all 7 module dashboards render correctly in the WebView
- Test ApexCharts performance (scroll through Dashboard, Net Worth charts, Estate waterfall)

### Phase 3 (Auth):
- Login flow works identically in native app and web browser
- Close and reopen native app -> token persists, session resumes without re-login
- Enable Face ID/Touch ID -> close app -> reopen -> biometric prompt appears
- Deny biometric -> falls back to full login screen
- After 15 minutes of inactivity, auto-logout still fires
- After 8 hours (server token expiry), app correctly redirects to login
- Web app behaviour completely unchanged (sessionStorage still used)

### Phase 4 (Push):
- Register device token on successful login
- Use Firebase Console to send a test notification -> appears on device
- Trigger a scheduled notification from backend (`php artisan push:send`)
- Tap notification -> app opens to correct screen
- Logout -> device token is removed -> no more notifications received

### Phase 5 (UX):
- Safe areas render correctly on iPhone with notch (no content hidden behind status bar)
- Haptic feedback fires on form submit (feel vibration on physical device)
- Pull-to-refresh on Dashboard triggers data reload (loading spinner appears)
- Toggle airplane mode -> offline banner appears -> toggle off -> banner disappears
- Android back button: navigates back through history, exits app from root screen

### Phase 6 (Camera):
- "Scan Document" button appears ONLY in native app, hidden on web
- Tap -> camera opens -> take photo -> image appears in upload flow
- Image uploads to backend -> AI extraction processes normally
- "Choose from Library" also works

### Phase 7 (Deep Linking):
- `fynla://dashboard` opens the dashboard in the app
- Push notification tap routes to the correct screen
- Universal link (`https://fynla.org/app/protection`) opens app if installed, web if not

### Phase 8 (App Store):
- TestFlight build installs and runs correctly on physical iPhone
- Google Play internal testing track installs and runs on physical Android
- Both apps pass store review without rejection

---

## Estimated Timeline

| Phase | Effort | Running Total | Dependencies |
|-------|--------|---------------|-------------|
| Pre-requisites (accounts, tools) | 1-2 days | Day 1-2 | None |
| 1. PWA Foundation | 3-4 days | Week 1 | None |
| 2. Capacitor Integration | 3-4 days | Week 2 | Phase 1 |
| 3. Auth + Biometrics | 5-7 days | Week 3-4 | Phase 2 |
| 4. Push Notifications | 5-7 days | Week 5-6 | Phase 3 + Apple Dev Account |
| 5. Native UX | 4-5 days | Week 6-7 | Phase 2 |
| 6. Camera | 1-2 days | Week 7 | Phase 2 |
| 7. Deep Linking | 2-3 days | Week 7-8 | Phase 4 (notification routing) |
| 8. App Store Submission | 3-5 days | Week 8-9 | All phases |
| **Total** | **~28-39 working days** | **~7-9 weeks** | |

**Parallelisation:** Phases 5, 6, and 7 can overlap with each other and with Phase 4.

**Critical path:** Pre-requisites -> Phase 1 -> Phase 2 -> Phase 3 -> Phase 4 -> Phase 8.

---

## Key Risks and Mitigations

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Apple rejects app as "web wrapper" | High | Low | Native features (biometrics, push, camera, haptics) differentiate from pure web. Explicitly describe in App Review notes |
| ApexCharts slow in WebView | Medium | Medium | Test early in Phase 2 on physical devices. If issues, switch ApexCharts to Canvas rendering mode (`chart.animations.enabled: false`, `chart.toolbar.show: false`) |
| Token persistence changes break web auth | High | Low | Platform detection ensures web path is completely unchanged. `tokenStorage.js` falls through to `sessionStorage` on web |
| `withCredentials: true` CSRF issues on native | Medium | High | Disable `withCredentials` on native platforms. App already uses Bearer token auth (not cookie sessions), so CSRF is irrelevant for native |
| `sendBeacon` logout unavailable in Capacitor | Low | Certain | Use `@capacitor/app` `appStateChange` event and regular API call instead |
| SiteGround cron limitations for push | Low | Low | SiteGround supports standard cron. Use simple daily schedule. Notifications are reminders, not time-critical |
| Apple Developer Account approval delay | Medium | Low | Apply immediately in Pre-requisites. Typically 24-48 hours but can take longer |

---

## Costs

| Item | Cost | Frequency |
|------|------|-----------|
| Apple Developer Program | $99 | Annual |
| Google Play Console | $25 | One-time |
| Firebase Cloud Messaging | Free | Free tier covers all reasonable usage |
| `kreait/firebase-php` package | Free | Open source |
| **Total Year 1** | **~$124** | |
| **Total Subsequent Years** | **~$99** | |

---

## Future Enhancements (Post v1.0)

Once the app is live and stable, consider:

1. **Secure Enclave storage** -- Upgrade from `@capacitor/preferences` to `capacitor-secure-storage-plugin` for iOS Keychain / Android EncryptedSharedPreferences
2. **Offline data viewing** -- Cache last-viewed dashboard data in IndexedDB, show with "Last updated" timestamp
3. **Widget support** -- iOS WidgetKit / Android Widget for "Net Worth at a glance" (requires native Swift/Kotlin code)
4. **In-app review prompts** -- Use `@capacitor/app` to trigger native review dialogs after positive interactions
5. **App Clips (iOS) / Instant Apps (Android)** -- Lightweight preview experience without full install
6. **Background refresh** -- Periodic data sync when app is backgrounded (native only)
7. **Shared Web Credentials** -- Allow Safari passwords to auto-fill in the native app
