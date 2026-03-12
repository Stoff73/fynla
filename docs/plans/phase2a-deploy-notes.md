# Phase 2a Deploy Notes — Backend Infrastructure for Mobile App

**Date:** 10 March 2026
**Branch:** `feature/mobile-app-phase0`
**Version:** v0.8.5-phase2a

---

## Summary

Phase 2a adds all backend infrastructure needed for the Capacitor mobile app — without requiring Xcode or Android Studio. Everything is testable in the current Laravel + Vue environment.

**Key additions:**
- Database tables: `device_tokens`, `notification_preferences` + `device_id` column on `user_sessions`
- Auth token refresh endpoint (`POST /api/v1/auth/refresh-token`)
- Device registration API (CRUD for push notification tokens)
- Notification preferences API (GET/PUT for 7 preference types)
- Push notification service (FCM HTTP, stale token cleanup)
- 6 notification classes (PolicyRenewal, GoalMilestone, ContributionReminder, SecurityAlert, SubscriptionExpiring, DailyInsight)
- 2 scheduled commands (daily insights at 08:00, policy renewals at 09:00)
- Deep link configuration (iOS Universal Links + Android App Links)
- CORS + CSP updates for Capacitor origins
- Vuex persistence plugin + mobileDashboard store + platform detection utility
- Social share backend with PII-safe content generation

## Pre-Deploy: Dependencies

### PHP (Composer)
No new Composer packages required. FCM uses Laravel's built-in HTTP client.

### JavaScript (npm)
New package: `vuex-persistedstate` (v4.1.0) — included in the build output, no server-side npm install needed.

### Environment Variables (Production)
Add to `.env`:
```
FCM_SERVER_KEY=your_firebase_server_key
FCM_PROJECT_ID=your_firebase_project_id
```

These are optional until push notifications are activated in Phase 2b. Without them, the push service gracefully skips sending.

## Files to Upload

### PHP Files (upload to `~/www/fynla.org/public_html/`)

**NEW files — create directories as needed:**

| File | Purpose |
|------|---------|
| `database/migrations/2026_03_10_200001_create_device_tokens_table.php` | Device token storage |
| `database/migrations/2026_03_10_200002_create_notification_preferences_table.php` | Notification preferences |
| `database/migrations/2026_03_10_200003_add_device_id_to_user_sessions_table.php` | Session device tracking |
| `app/Models/DeviceToken.php` | Device token model |
| `app/Models/NotificationPreference.php` | Notification preference model |
| `app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php` | Token refresh |
| `app/Http/Controllers/Api/V1/Mobile/DeviceController.php` | Device registration |
| `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php` | Notification preferences |
| `app/Http/Controllers/Api/V1/Mobile/ShareController.php` | Social share payloads |
| `app/Http/Requests/V1/RegisterDeviceRequest.php` | Device validation |
| `app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php` | Preferences validation |
| `app/Http/Requests/V1/ShareContentRequest.php` | Share validation |
| `app/Services/Mobile/PushNotificationService.php` | FCM push delivery |
| `app/Services/Mobile/ShareContentGenerator.php` | Share text generation |
| `app/Notifications/PolicyRenewalNotification.php` | Push notification |
| `app/Notifications/GoalMilestoneNotification.php` | Push notification |
| `app/Notifications/ContributionReminderNotification.php` | Push notification |
| `app/Notifications/SecurityAlertNotification.php` | Push notification |
| `app/Notifications/SubscriptionExpiringNotification.php` | Push notification |
| `app/Notifications/DailyInsightNotification.php` | Push notification |
| `app/Console/Commands/SendDailyInsightNotifications.php` | Daily cron |
| `app/Console/Commands/SendPolicyRenewalReminders.php` | Daily cron |
| `public/.well-known/apple-app-site-association` | iOS deep links |
| `public/.well-known/assetlinks.json` | Android deep links |

**MODIFIED files — replace existing:**

| File | What changed |
|------|-------------|
| `app/Models/UserSession.php` | Added `device_id` to fillable |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Added excluded routes for token refresh + device registration |
| `app/Http/Middleware/SecurityHeaders.php` | Added Capacitor origins to CSP connect-src |
| `app/Console/Kernel.php` | Registered 2 scheduled notification commands |
| `routes/api_v1.php` | Added all new mobile routes |
| `config/cors.php` | Added Capacitor origins |
| `config/services.php` | Added FCM config |

### Frontend Build (rebuild required)

Run locally:
```bash
./deploy/fynla-org/build.sh
```

Upload entire `public/build/` directory — replaces existing build. This includes:
- `vuex-persistedstate` persistence plugin
- `mobileDashboard.js` Vuex store module
- `platform.js` utility
- Updated `store/index.js` with persistence config

### Files NOT to Upload

- `package.json` / `package-lock.json` — build dependency only
- `database/factories/` — dev only
- `tests/` — dev only
- `docs/plans/` — dev only
- `.claude/worktrees/` — dev artifacts

## Post-Deploy: SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migrations (creates new tables + column)
php artisan migrate

# Clear caches and optimise
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize

# Reseed (adds data to new tables if needed)
php artisan db:seed
```

## Verification Checklist

### Database
- [ ] `device_tokens` table exists with correct columns
- [ ] `notification_preferences` table exists with correct columns
- [ ] `user_sessions` table has `device_id` column

### Auth Token Refresh
- [ ] `POST /api/v1/auth/refresh-token` (authed) returns new token
- [ ] Old token is revoked after refresh
- [ ] Unauthenticated request returns 401

### Device Registration
- [ ] `POST /api/v1/mobile/devices` registers a new device
- [ ] Posting same device_id updates existing token (upsert)
- [ ] `GET /api/v1/mobile/devices` lists user's devices
- [ ] `DELETE /api/v1/mobile/devices/{deviceId}` revokes device
- [ ] Cannot access other users' devices

### Notification Preferences
- [ ] `GET /api/v1/mobile/notifications/preferences` returns defaults for new user
- [ ] `PUT /api/v1/mobile/notifications/preferences` updates specific fields
- [ ] Unchanged fields remain untouched

### Social Share
- [ ] `GET /api/v1/mobile/share/goal_milestone/1` returns share content
- [ ] `GET /api/v1/mobile/share/app_referral` returns share content
- [ ] Share text contains NO monetary values, names, or PII
- [ ] Invalid share type returns 422

### Deep Links
- [ ] `GET /.well-known/apple-app-site-association` returns valid JSON
- [ ] `GET /.well-known/assetlinks.json` returns valid JSON

### Scheduled Commands
- [ ] `php artisan notifications:daily-insight` runs without error
- [ ] `php artisan notifications:policy-renewals` runs without error

### Existing Features (regression check)
- [ ] Login/logout works normally
- [ ] Dashboard loads with correct data
- [ ] Preview personas still work via landing page
- [ ] AI chat works normally
- [ ] Mobile dashboard API still returns data
- [ ] PWA still installs correctly

## Rollback

If issues arise:
1. Restore previous versions of modified files (routes, middleware, config)
2. New controller/service/notification files are inert without routes — can be left or removed
3. Migration rollback: `php artisan migrate:rollback --step=3` (drops new tables + column)
4. Clear caches: `php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize`

## Test Results

- **Total tests:** 1861 passed (8043 assertions)
- **Mobile-specific tests:** 82 passed (312 assertions)
- **Duration:** 116s

## Phase 2a Metrics Snapshot

| Metric | Phase 1 | Phase 2a | Delta |
|--------|---------|----------|-------|
| PHP Files (new) | +13 | +37 | +24 |
| Vue/JS Files (new) | +5 | +8 | +3 |
| Test Files | +7 | +19 | +12 |
| Test Count | 1820 | 1861 | +41 |
| API Endpoints | +4 | +12 | +8 |
| DB Tables | 0 | +2 | +2 |
| Scheduled Commands | 0 | +2 | +2 |
