# Deploy All — March 7-13, 2026

**Comprehensive production deployment guide for all changes from March 7 to March 13.**

This covers 140+ commits across 12 PRs and 3 feature branches. The deployment is split into phases so you can deploy incrementally and verify at each step.

---

## Deployment Status: COMPLETE (Deployed 13 March 2026)

All phases deployed and verified on production.

## Pre-Deployment Checklist

- [x] All branches merged to `main`
- [x] Local `php artisan db:seed` runs clean
- [x] All tests pass: `./vendor/bin/pest`
- [x] Production database backed up
- [x] SSH access confirmed

---

## Phase 1: Database Migrations

Run on production server via SSH. These are additive — no destructive changes.

```bash
cd ~/www/fynla.org/public_html
php artisan migrate
```

**5 new migrations:**

| Migration | Purpose |
|-----------|---------|
| `2026_03_07_200001_add_journey_fields_to_users_table` | Adds `journey_states`, `journey_selections`, `dismissed_prompts` JSON columns to users table |
| `2026_03_10_200001_create_device_tokens_table` | Mobile push notification device registration |
| `2026_03_10_200002_create_notification_preferences_table` | User notification preference toggles |
| `2026_03_10_200003_add_device_id_to_user_sessions_table` | Track which device a session belongs to |
| `2026_03_10_200004_add_mortgage_rate_alerts_to_notification_preferences` | Mortgage rate alert notification preferences |

After migrations, reseed:
```bash
php artisan db:seed
```

---

## Phase 2: Environment Variables

Add to production `.env` via SiteGround File Manager:

```env
# Cerebras AI Chat (replaces OpenAI for chat)
CEREBRAS_API_KEY=csk-jtjv6mkxyyhttf96mwfck5epyc3rnwyv65whrtxhdxx3y583
CEREBRAS_CHAT_MODEL=gpt-oss-120b

# Analytics (Plausible — privacy-first, no cookies)
ANALYTICS_ENABLED=true
PLAUSIBLE_DOMAIN=fynla.org
VITE_PLAUSIBLE_DOMAIN=fynla.org

# Push Notifications (FCM — configure when ready)
# FCM_PROJECT_ID=
# FCM_PRIVATE_KEY=
# FCM_CLIENT_EMAIL=
```

**Note:** The `OPENAI_API_KEY` can remain — it's still used by the Anthropic document extraction service. The chat has moved to Cerebras.

---

## Phase 3: Upload PHP Files

Upload these files via SiteGround File Manager to `~/www/fynla.org/public_html/`.

### 3a. Journey Onboarding (March 7)

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/JourneyController.php` | **NEW** — Journey API (selections, steps, preview, dashboard prompts) |
| `app/Services/Onboarding/JourneyStateService.php` | **NEW** — Journey state persistence and progress tracking |
| `app/Services/Onboarding/JourneyFieldResolver.php` | **NEW** — Step structure and field definitions for 8 journey types |
| `app/Services/Onboarding/DashboardPromptService.php` | **NEW** — Post-journey dashboard notifications |
| `app/Http/Requests/Onboarding/StoreJourneySelectionsRequest.php` | **NEW** — Journey selections validation |
| `routes/api.php` | Updated — journey routes under `/api/journeys/*` |

### 3b. AI Chat (Cerebras Migration)

| File | Change |
|------|--------|
| `app/Services/AI/AiChatService.php` | Switched from OpenAI to Cerebras API (`gpt-oss-120b`), full tool calling |
| `app/Services/AI/AiContextBuilder.php` | System prompt with tool-aware "Available Actions" section |
| `app/Services/AI/AiModelResolver.php` | Cerebras `gpt-oss-120b` model (120B params, 3000 tok/s) |
| `app/Services/AI/AiToolDefinitions.php` | Tool schemas (17 tools, docblock update) |
| `config/services.php` | Added `cerebras` config block |

### 3c. Mobile API Infrastructure

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php` | **NEW** — Mobile dashboard endpoint with ETag caching |
| `app/Http/Controllers/Api/V1/Mobile/ModuleSummaryController.php` | **NEW** — Per-module summary endpoint |
| `app/Http/Controllers/Api/V1/Mobile/InsightsController.php` | **NEW** — Daily insights endpoint |
| `app/Http/Controllers/Api/V1/Mobile/DeviceController.php` | **NEW** — Push notification device registration |
| `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php` | **NEW** — Notification preference toggles |
| `app/Http/Controllers/Api/V1/Mobile/ShareController.php` | **NEW** — PII-safe social share content |
| `app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php` | **NEW** — Mobile token refresh |
| `app/Http/Requests/V1/RegisterDeviceRequest.php` | **NEW** — Device registration validation |
| `app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php` | **NEW** — Notification prefs validation |

### 3d. Mobile Services

| File | Change |
|------|--------|
| `app/Services/Mobile/MobileDashboardAggregator.php` | **NEW** — Dashboard data aggregation (net worth + modules) |
| `app/Services/Mobile/PushNotificationService.php` | **NEW** — FCM push notification sender |
| `app/Services/Mobile/ShareContentGenerator.php` | **NEW** — PII-safe share text generation |

### 3e. Models

| File | Change |
|------|--------|
| `app/Models/User.php` | Updated — journey field casts (`journey_states`, `journey_selections` as array) |
| `app/Models/DeviceToken.php` | **NEW** — Push notification device tokens |
| `app/Models/NotificationPreference.php` | **NEW** — User notification preferences |
| `app/Models/UserSession.php` | Updated — added `device_id` column |

### 3f. Middleware

| File | Change |
|------|--------|
| `app/Http/Middleware/ETagResponse.php` | **NEW** — Conditional HTTP caching for mobile |
| `app/Http/Middleware/IdentifyMobileClient.php` | **NEW** — X-Fynla-Platform header detection |
| `app/Http/Middleware/SecurityHeaders.php` | Updated — Capacitor origins in CSP |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Updated — new route exclusions |
| `app/Http/Kernel.php` | Updated — registered new middleware |

### 3g. Notifications and Commands

| File | Change |
|------|--------|
| `app/Console/Commands/SendDailyInsightNotifications.php` | **NEW** — Daily insight push notifications |
| `app/Console/Commands/SendPolicyRenewalReminders.php` | **NEW** — Protection policy renewal reminders |
| `app/Console/Commands/SendMortgageRateAlerts.php` | **NEW** — 90/60/30 day mortgage rate warnings |
| `app/Console/Kernel.php` | Updated — scheduled commands registered |
| `app/Notifications/DailyInsightNotification.php` | **NEW** |
| `app/Notifications/GoalMilestoneNotification.php` | **NEW** |
| `app/Notifications/PolicyRenewalNotification.php` | **NEW** |
| `app/Notifications/MortgageRateAlertNotification.php` | **NEW** |
| `app/Notifications/SecurityAlertNotification.php` | **NEW** |
| `app/Notifications/SubscriptionExpiringNotification.php` | **NEW** |
| `app/Notifications/ContributionReminderNotification.php` | **NEW** |

### 3h. Routes and Config

| File | Change |
|------|--------|
| `routes/api_v1.php` | **NEW** — Mobile API v1 route group (`/api/v1/mobile/*`) |
| `app/Providers/RouteServiceProvider.php` | Updated — loads `api_v1.php` |
| `config/services.php` | Updated — added `cerebras` config |
| `config/analytics.php` | **NEW** — Plausible analytics config |
| `config/cors.php` | Updated — Capacitor origins allowed |

### 3i. Other Backend

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/MortgageController.php` | Updated — ownership-adjusted mortgage balance |
| `database/seeders/PreviewUserSeeder.php` | Updated — preview persona fixes |
| `resources/views/app.blade.php` | Updated — Plausible analytics script |

### 3j. Deep Linking (Universal Links)

| File | Change |
|------|--------|
| `public/.well-known/apple-app-site-association` | **NEW** — iOS Universal Links |
| `public/.well-known/assetlinks.json` | **NEW** — Android App Links |

---

## Phase 4: Build and Upload Frontend

Build locally (server lacks memory for npm):

```bash
cd /Users/CSJ/Desktop/fynla
./deploy/fynla-org/build.sh
```

Upload the entire `public/build/` directory to production via SiteGround File Manager:
`~/www/fynla.org/public_html/public/build/`

This includes all 107 changed Vue/JS files:

**Key frontend changes:**

| Area | What Changed |
|------|-------------|
| **Mobile app** (47 new components) | Full Capacitor iOS app — dashboard, Fyn chat, goals, Learn Hub, settings, voice input, Face ID |
| **Landing page** | Redesigned to match Adobe XD wireframe with new colour scheme |
| **Sidebar** | Collapsible sections (Cash Management, Finances, Family/Admin, Planning, Account, Support) |
| **Dashboard** | Journeys and What If Scenarios submenu, pie charts above wealth table |
| **AI Chat panel** | Responsive mobile full-screen overlay |
| **Auth** | Async token storage, biometric login support, mobile logout |
| **PWA** | Service worker, offline banner, Workbox caching |
| **Analytics** | Plausible custom events for mobile |
| **Router** | Mobile routes under `/m/` prefix with lazy loading |
| **Vuex stores** | `mobileDashboard`, `mobileNotifications` — 2 new stores |

---

## Phase 5: Clear Caches

SSH to production and clear all caches:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Phase 6: Post-Deployment Verification

### Web App

- [ ] Landing page loads with new design (no blank page)
- [ ] Login works with verification code
- [ ] Dashboard loads — pie charts above wealth table, Journeys/What If in sidebar
- [ ] Sidebar collapsible sections work
- [ ] Fyn chat responds (Cerebras, not OpenAI) — test: "Hello, what can you help me with?"
- [ ] Preview personas load (test via landing page persona selector)
- [ ] Protection card shows policy count (not "£0")
- [ ] Property shows ownership-adjusted mortgage balance for tenants in common

### Mobile API

- [ ] `GET /api/v1/mobile/dashboard` returns data (with auth token)
- [ ] `GET /api/v1/mobile/module-summary/protection` returns data
- [ ] ETag caching works (second request returns 304)

### Deep Links

- [ ] `https://fynla.org/.well-known/apple-app-site-association` returns JSON
- [ ] `https://fynla.org/.well-known/assetlinks.json` returns JSON

---

## Phase 7: iOS TestFlight (Separate Process)

The iOS app is built separately and deployed via TestFlight. See `deployToTestFlight.md` for the full 8-phase process.

Quick path:
```bash
cd /Users/CSJ/Desktop/fynla
php artisan db:seed
./deploy/mobile/build-ios.sh
open ios/App/App.xcworkspace
# Increment Build number → Clean Build → Archive → Upload
```

---

## Summary of All Changes (March 7-13)

### March 7 — Journey Onboarding

| Feature | Description |
|---------|-------------|
| Journey API | 8 endpoints for journey selections, steps, preview, progress, dashboard prompts |
| Journey services | JourneyStateService (state persistence), JourneyFieldResolver (step definitions), DashboardPromptService (notifications) |
| Journey migration | 3 JSON columns on users table (`journey_states`, `journey_selections`, `dismissed_prompts`) |
| Frontend | JourneyCard, JourneyPreview, JourneyCompletionStep components, journeyService, journeys Vuex store |

### March 9 — Marketplace and Vault

| PR | Description |
|----|-------------|
| #113 | Fynla Marketplace plugin system |
| #114, #115 | Marketplace fixes (repo name, source schema) |
| #116, #117 | Obsidian vault structure, session notes |
| #118 | March 9 session 2 notes |

### March 10 — Mobile App (Phases 0-2b)

| Feature | Commits |
|---------|---------|
| PWA foundation (manifest, service worker, icons, offline banner) | P1-01 to P1-04 |
| Mobile API (dashboard, module summary, insights, rate limiters) | P1-06 to P1-09b |
| AI chat responsive overlay, quick reply chips | P1-10, P1-11 |
| Plausible analytics (privacy-first, no cookies) | P1-15, P1-16 |
| Device tokens, notification preferences, FCM | 2a-01 to 2a-07 |
| Deep linking (Universal Links, App Links) | 2a-08 |
| CORS/CSP for Capacitor origins | 2a-09 |
| Vuex persistence, platform detection, mobileDashboard store | 2a-10 |
| Social share backend | 2a-11 |
| Capacitor 6.x initialisation, iOS platform, native plugins | Phase 2b |
| 47 new mobile Vue components | Phase 2b |
| Mobile auth (login, verification, biometric) | Phase 2b |
| Mobile dashboard, Fyn chat, goals, Learn Hub, settings | Phase 2b |
| Voice input with speech recognition | Phase 2b |
| Mortgage rate alerts (90/60/30 day) | Phase 2b |
| Notification settings screen | Phase 2b |

### March 11 — iOS Blank Screen Fix

| Fix | Description |
|-----|-------------|
| Static imports in vite.config.js | Removed `external` from rollupOptions (caused MIME type error in WKWebView) |
| PWA conditionally disabled for iOS | `VITE_DISABLE_PWA=true` for mobile builds |
| SPA navigation fix | Hash-based routing for Capacitor |

### March 12 — UI Redesigns and Mobile Fixes (PRs #119, #120)

| Feature | Description |
|---------|-------------|
| Landing page redesign | Adobe XD wireframe colour scheme |
| Sidebar restructure | Collapsible sections, property as direct nav item |
| Dashboard reorganisation | Pie charts above table, Journeys/What If submenu |
| 7 module detail pages | Protection, Savings, Investment, Retirement, Estate, Goals, Coordination |
| Expandable account cards | Tap to reveal details (interest rate, access type, ISA, ownership) |
| Face ID biometric login | Persist across logout, auto-login on app startup |
| iOS blank screen fixes | Splash screen, WKWebView MIME type, navigation |
| Property mortgage fix | Ownership-adjusted balance for tenants in common |
| Preview timeout fix | Disabled inactivity timeout for preview personas |

### March 13 — Polish and AI Migration (PR #121 + aiUpdate)

| Feature | Description |
|---------|-------------|
| App icon | Eggshell background, scaled-down logo |
| Mobile header redesign | Centred Fynla logo + user avatar initials |
| Emoji icon removal | Removed from all 13 card components |
| Protection card fix | Show policy count instead of £0 |
| Voice input rewrite | Continuous listening mode |
| Chat error display | Dismissible error box in MobileFynChat |
| Viewport zoom fix | Prevent iOS auto-zoom on input focus |
| More menu cleanup | Removed modules grid (accessible from dashboard) |
| **Cerebras AI migration** | Switched chat from OpenAI to Cerebras `gpt-oss-120b` with full tool calling (17 tools) |

---

## Rollback Plan

If something breaks:

1. **Frontend issues:** Re-upload previous `public/build/` from local backup
2. **API issues:** Revert specific PHP files via SiteGround File Manager
3. **Migration issues:** Migrations are additive — no rollback needed (new tables won't conflict)
4. **Cerebras issues:** Change `CEREBRAS_API_KEY` back to OpenAI key and revert `AiChatService.php`, `AiModelResolver.php`, `AiContextBuilder.php` to previous versions
5. **Cache issues:** `php artisan cache:clear && php artisan config:clear && php artisan optimize`

---

## File Count Summary

| Category | New | Modified | Total |
|----------|-----|----------|-------|
| PHP (Controllers, Services, Models) | 29 | 15 | 44 |
| PHP (Notifications, Commands) | 8 | 1 | 9 |
| PHP (Middleware, Routes, Config) | 5 | 6 | 11 |
| Migrations | 5 | 0 | 5 |
| Vue/JS (Mobile) | 47 | 0 | 47 |
| Vue/JS (Web) | 6 | 17 | 23 |
| Vue/JS (Services, Stores, Utils) | 7 | 10 | 17 |
| Config/Deploy | 3 | 3 | 6 |
| Deep Links | 2 | 0 | 2 |
| **Total** | **112** | **52** | **164** |

*Note: `ShareContentRequest.php` was removed — `ShareController` uses base `Request` class.*
