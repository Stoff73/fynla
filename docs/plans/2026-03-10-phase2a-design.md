# Phase 2a Design: Backend Infrastructure for Mobile App

**Date:** 10 March 2026
**Status:** Approved
**Branch:** `feature/mobile-app-phase0` (continuing from Phase 0+1)
**Depends on:** Phase 0 + Phase 1 (complete)

---

## Overview

Phase 2a builds all backend infrastructure, API endpoints, state management, and configuration needed for the Capacitor mobile app — without requiring Xcode or Android Studio. Everything here is testable in the current Laravel + Vue environment.

Phase 2b (Capacitor + native UI) builds on top of 2a and is specced separately.

---

## 2a-01: Database Migrations

### device_tokens table

```sql
CREATE TABLE device_tokens (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    device_token VARCHAR(500) NOT NULL,
    device_id VARCHAR(255) NOT NULL,
    platform ENUM('ios', 'android') NOT NULL,
    device_name VARCHAR(255) NULL,
    app_version VARCHAR(20) NULL,
    os_version VARCHAR(50) NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id, device_id),
    INDEX(device_token)
);
```

### notification_preferences table

```sql
CREATE TABLE notification_preferences (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    policy_renewals BOOLEAN DEFAULT TRUE,
    goal_milestones BOOLEAN DEFAULT TRUE,
    contribution_reminders BOOLEAN DEFAULT TRUE,
    market_updates BOOLEAN DEFAULT FALSE,
    fyn_daily_insight BOOLEAN DEFAULT TRUE,
    security_alerts BOOLEAN DEFAULT TRUE,
    payment_alerts BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id)
);
```

### user_sessions modification

Add `device_id VARCHAR(255) NULL` column to existing `user_sessions` table.

---

## 2a-02: Models + Factories

### DeviceToken model

- Belongs to User (`user_id`)
- Fillable: device_token, device_id, platform, device_name, app_version, os_version, last_used_at
- Scopes: `scopeForUser($userId)`, `scopeForPlatform($platform)`
- Cast: `last_used_at` as datetime

### NotificationPreference model

- Belongs to User (`user_id`)
- Fillable: all 7 preference booleans
- Casts: all booleans
- Static `getOrCreateForUser($userId)` — returns existing or creates with defaults

### Factories

- `DeviceTokenFactory` — with `ios()` and `android()` state methods
- `NotificationPreferenceFactory` — with `allEnabled()` and `allDisabled()` states

---

## 2a-03: Auth Token Refresh Endpoint

**Route:** `POST /api/v1/auth/refresh-token`

**Middleware:** `auth:sanctum` (must have valid current token)

**Logic:**
1. Validate current token is valid
2. Check token age — if <25 days, return current token info (no refresh needed)
3. Create new Sanctum token with 30-day expiry
4. Revoke old token
5. Return new token + expiry

**Response:**
```json
{
    "success": true,
    "data": {
        "token": "new_bearer_token",
        "expires_at": "2026-04-09T15:00:00Z",
        "token_age_days": 0
    }
}
```

**Controller:** `app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php`

**Add to PreviewWriteInterceptor EXCLUDED_ROUTES** — refresh must work for preview users.

---

## 2a-04: Device Registration API

**Routes** (inside `auth:sanctum` group):
- `POST /api/v1/mobile/devices` — register
- `DELETE /api/v1/mobile/devices/{deviceId}` — revoke by device_id
- `GET /api/v1/mobile/devices` — list user's registered devices

**Controller:** `app/Http/Controllers/Api/V1/Mobile/DeviceController.php`

**Register request validation:**
- `device_token`: required, string, max:500
- `device_id`: required, string, max:255
- `platform`: required, in:ios,android
- `device_name`: nullable, string, max:255
- `app_version`: nullable, string, max:20
- `os_version`: nullable, string, max:50

**Rate limiting:** `throttle:device-registration` (5/min, already exists from Phase 1)

**Upsert logic:** If device_id already exists for user, update token + metadata. Otherwise create.

---

## 2a-05: Notification Preferences API

**Routes** (inside `auth:sanctum` group):
- `GET /api/v1/mobile/notifications/preferences`
- `PUT /api/v1/mobile/notifications/preferences`

**Controller:** `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php`

**Update validation:** All 7 fields nullable boolean. Only provided fields are updated.

**Auto-create:** GET creates default preferences if none exist for user.

---

## 2a-06: Push Notification Service

**Package:** `laravel-notification-channels/fcm` (adds Firebase Cloud Messaging)

**Config:** `config/fcm.php` with `FCM_SERVER_KEY`, `FCM_PROJECT_ID` env vars

**Service:** `app/Services/Mobile/PushNotificationService.php`
- `sendToUser(int $userId, Notification $notification): void`
- `sendToDevice(DeviceToken $device, Notification $notification): void`
- Checks user's notification preferences before sending
- Handles stale tokens (FCM returns invalid → soft-delete device token)

**6 Notification classes** (all extend `Notification`, implement `via()` returning `['fcm']`):

| Class | Trigger | Preference Key |
|-------|---------|---------------|
| `PolicyRenewalNotification` | Daily cron, 30-day lookahead | `policy_renewals` |
| `GoalMilestoneNotification` | GoalContributionObserver at 25/50/75/100% | `goal_milestones` |
| `ContributionReminderNotification` | Weekly cron | `contribution_reminders` |
| `SecurityAlertNotification` | New device login | `security_alerts` |
| `SubscriptionExpiringNotification` | TrialExpirationJob at 7d, 1d | `payment_alerts` |
| `DailyInsightNotification` | Daily cron | `fyn_daily_insight` |

---

## 2a-07: Scheduled Notification Commands

**`app/Console/Commands/SendDailyInsightNotifications.php`**
- Runs daily at 08:00
- Queries users with `fyn_daily_insight = true` and at least one device token
- Generates insight using CoordinatingAgent (batched, not per-user)
- Sends via PushNotificationService

**`app/Console/Commands/SendPolicyRenewalReminders.php`**
- Runs daily at 09:00
- Queries protection policies expiring in 30 days
- Sends reminder to policy owner via PushNotificationService

**Register in `app/Console/Kernel.php`:**
```php
$schedule->command('notifications:daily-insight')->dailyAt('08:00');
$schedule->command('notifications:policy-renewals')->dailyAt('09:00');
```

---

## 2a-08: Deep Link Configuration

**`public/.well-known/apple-app-site-association`:**
```json
{
    "applinks": {
        "apps": [],
        "details": [{
            "appID": "TEAMID.org.fynla.app",
            "paths": ["/protection", "/savings", "/investments", "/retirement", "/estate", "/goals", "/net-worth", "/tax", "/dashboard"]
        }]
    }
}
```

**`public/.well-known/assetlinks.json`:**
```json
[{
    "relation": ["delegate_permission/common.handle_all_urls"],
    "target": {
        "namespace": "android_app",
        "package_name": "org.fynla.app",
        "sha256_cert_fingerprints": ["PLACEHOLDER_SHA256"]
    }
}]
```

Placeholders filled in Phase 2b when native projects are generated.

---

## 2a-09: CORS + Middleware Updates

**`config/cors.php`** — add to allowed_origins:
- `capacitor://localhost` (iOS Capacitor)
- `http://localhost` (Android Capacitor)

**`app/Http/Middleware/SecurityHeaders.php`** — add Capacitor origins to CSP `connect-src`

**`app/Http/Middleware/PreviewWriteInterceptor.php`** — add to EXCLUDED_ROUTES:
- `api/v1/auth/refresh-token`
- `api/v1/mobile/devices`

---

## 2a-10: Vuex Persistence + Platform Detection

**Install:** `vuex-persistedstate` npm package

**Configure in `resources/js/store/index.js`:**
```javascript
import createPersistedState from 'vuex-persistedstate';

const store = createStore({
    modules: { ... },
    plugins: [
        createPersistedState({
            key: 'fynla-state',
            paths: [
                'auth.user',
                'auth.isAuthenticated',
                'dashboard',
                'aiChat.conversations',
                'goals.goals',
                'mobileDashboard',
            ],
            storage: window.localStorage,
        }),
    ],
});
```

**New Vuex module:** `store/modules/mobileDashboard.js`
- State: `summary`, `netWorth`, `modules`, `alerts`, `insight`, `loading`, `error`, `lastFetched`
- Actions: `fetchDashboard`, `refreshDashboard`, `clearCache`
- Consumes `/api/v1/mobile/dashboard` endpoint

**Platform utility:** `resources/js/utils/platform.js`
```javascript
export const platform = {
    isNative: () => typeof window.Capacitor !== 'undefined' && window.Capacitor.isNativePlatform(),
    isIOS: () => platform.isNative() && window.Capacitor.getPlatform() === 'ios',
    isAndroid: () => platform.isNative() && window.Capacitor.getPlatform() === 'android',
    isWeb: () => !platform.isNative(),
    isMobileViewport: () => window.innerWidth < 768,
};
```

---

## 2a-11: Social Share Backend

**Controller:** `app/Http/Controllers/Api/V1/Mobile/ShareController.php`
- `GET /api/v1/mobile/share/{type}/{id}` — returns sanitised share payload

**Service:** `app/Services/Mobile/ShareContentGenerator.php`
- Generates share text per type (goal_milestone, net_worth_milestone, fyn_insight, app_referral)
- **Never includes monetary values, balances, or portfolio figures**
- **Never includes names of joint owners or beneficiaries**
- Returns `{ title, text, url }` structure

**Validation:** `app/Http/Requests/V1/ShareContentRequest.php`
- `type`: required, in: goal_milestone, net_worth_milestone, fyn_insight, app_referral
- `id`: required when type is goal_milestone or fyn_insight

---

## 2a-12: Integration Testing + Code Review

- Run full test suite (1820+ tests)
- Run mobile-specific tests
- Code review via subagent
- Generate deploy notes
- Seed database

---

## File Inventory

### New Files (~28)

| File | Purpose |
|------|---------|
| `database/migrations/YYYY_create_device_tokens_table.php` | Device token storage |
| `database/migrations/YYYY_create_notification_preferences_table.php` | Notification prefs |
| `database/migrations/YYYY_add_device_id_to_user_sessions_table.php` | Session device tracking |
| `app/Models/DeviceToken.php` | Device token model |
| `app/Models/NotificationPreference.php` | Notification preference model |
| `database/factories/DeviceTokenFactory.php` | Test factory |
| `database/factories/NotificationPreferenceFactory.php` | Test factory |
| `app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php` | Token refresh |
| `app/Http/Controllers/Api/V1/Mobile/DeviceController.php` | Device registration |
| `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php` | Notification prefs |
| `app/Http/Controllers/Api/V1/Mobile/ShareController.php` | Social share payloads |
| `app/Http/Requests/V1/RegisterDeviceRequest.php` | Device validation |
| `app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php` | Prefs validation |
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
| `resources/js/store/modules/mobileDashboard.js` | Vuex store |
| `resources/js/utils/platform.js` | Platform detection |

### Modified Files (~8)

| File | Change |
|------|--------|
| `config/cors.php` | Add Capacitor origins |
| `app/Http/Middleware/SecurityHeaders.php` | Capacitor in CSP |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Add excluded routes |
| `app/Console/Kernel.php` | Schedule notification commands |
| `routes/api_v1.php` | Add all new mobile routes |
| `resources/js/store/index.js` | Add persistence plugin + mobileDashboard module |
| `package.json` | Add vuex-persistedstate |
| `composer.json` | Add laravel-notification-channels/fcm |

### Test Files (~12)

| File | Tests |
|------|-------|
| `tests/Unit/Models/DeviceTokenTest.php` | Model tests |
| `tests/Unit/Models/NotificationPreferenceTest.php` | Model tests |
| `tests/Feature/Mobile/TokenRefreshTest.php` | Auth refresh API |
| `tests/Feature/Mobile/DeviceRegistrationTest.php` | Device CRUD API |
| `tests/Feature/Mobile/NotificationPreferenceTest.php` | Prefs API |
| `tests/Feature/Mobile/ShareContentTest.php` | Share API |
| `tests/Unit/Services/Mobile/PushNotificationServiceTest.php` | Push service |
| `tests/Unit/Services/Mobile/ShareContentGeneratorTest.php` | Share text |
| `tests/Unit/Notifications/PolicyRenewalNotificationTest.php` | Notification |
| `tests/Unit/Notifications/GoalMilestoneNotificationTest.php` | Notification |
| `tests/Unit/Commands/SendDailyInsightNotificationsTest.php` | Command |
| `tests/Unit/Commands/SendPolicyRenewalRemindersTest.php` | Command |
