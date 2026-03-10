# Fynla Mobile App — Technical Implementation Plan

**Date:** 10 March 2026
**Status:** Phase 0 + Phase 1 COMPLETE — Ready for Phase 2
**Based on:** [Mobile App Exploration](2026-03-06-mobile-app-exploration.md) + team review
**Team:** Technical lead, UX/UI designer, integration expert, devil's advocate

---

## Executive Summary

This plan covers the phased implementation of a Fynla mobile app, starting with a PWA and evolving into a Capacitor hybrid app. The plan incorporates findings from 4 specialist reviews and resolves conflicts between optimistic scope and realistic constraints.

**Key decisions from team review:**
1. **AI-enhanced, not AI-dependent** — Home tab is data-driven; Fyn enhances every screen but is not the sole interaction model
2. **Phase 0 first** — Instrument analytics on the web app before building mobile
3. **Rescoped Phase 1** — Achievable in 3-4 weeks (service worker + manifest + responsive chat + aggregated dashboard)
4. **Auth token migration is a prerequisite** — Must abstract `sessionStorage` before any mobile work
5. **Learn Hub deferred** — Phase 1 links to MoneyHelper/HMRC; Phase 2 builds basic hub; Phase 3 adds personalisation
6. **Simple caching, not sync architecture** — ETags + Cache-Control in Phase 1; Vuex persistence in Phase 2; no full sync manager
7. **~54 new/variant components** — 36 new mobile-specific + 18 mobile variants of existing web components
8. **~34 new backend files + ~14 modified** — New mobile controllers, services, middleware, migrations
9. **Total timeline: 18-22 weeks** (including Phase 0 analytics and app store compliance buffer)

---

## Phase 0: Instrument & Measure (Weeks 1-2) — COMPLETE

**Status:** All tasks implemented and deployed. Branch: `feature/mobile-app-phase0`
**Completed:** 10 March 2026
**Deploy notes:** `docs/plans/phase0-deploy-notes.md`

**Rationale (devil's advocate):** Zero user data currently backs any mobile assumption. Before writing mobile code, measure actual mobile web usage.

### Tasks

1. **Add analytics to existing web app**
   - Integrate a lightweight analytics package (Plausible or PostHog self-hosted)
   - Track: device type, viewport size, pages visited, session duration, AI chat usage
   - No PII collection — aggregate metrics only

2. **Instrument key metrics**
   - Mobile browser visits as % of total
   - Which modules are visited on mobile browsers
   - AI chat session count per user per week
   - Time spent in AI chat vs browsing modules
   - Drop-off points in mobile browser experience

3. **User survey (optional)**
   - In-app banner: "Would you use a Fynla mobile app?" — 3 options (Yes/Maybe/No)
   - Collect which features matter most (dashboard, AI chat, goals, notifications)

4. **Auth token abstraction (PREREQUISITE — CRITICAL)**
   - Current: tokens in `sessionStorage` (`authService.js:82`, `api.js:66`, `aiChatService.js:43`)
   - `sessionStorage` clears on browser/tab close — incompatible with mobile
   - Create `resources/js/services/tokenStorage.js` abstracting storage:
     - Web: `sessionStorage` (current behaviour preserved)
     - Mobile/Capacitor: `@capacitor/preferences` (encrypted native storage)
   - Update `api.js`, `authService.js`, `aiChatService.js`, `sessionLifecycleService.js`
   - **This must be completed and tested before Phase 1 begins**

### Files to Create/Modify

| File | Action |
|------|--------|
| `resources/js/services/tokenStorage.js` | CREATE — platform-aware token storage |
| `resources/js/services/api.js` | MODIFY — use tokenStorage instead of direct sessionStorage |
| `resources/js/services/authService.js` | MODIFY — use tokenStorage |
| `resources/js/services/aiChatService.js` | MODIFY — use tokenStorage |
| `resources/js/services/sessionLifecycleService.js` | MODIFY — use tokenStorage |

### Success Gate
- Analytics collecting data for ≥2 weeks
- Token abstraction tested and deployed
- Decision point: proceed to Phase 1 only if mobile browser traffic >5% of total OR strategic decision to proceed regardless

---

## Phase 1: PWA Foundation (Weeks 3-6) — COMPLETE

**Status:** All tasks implemented, tested (1820 tests, 47 mobile-specific), and code reviewed.
**Completed:** 10 March 2026
**Deploy notes:** `docs/plans/phase1-deploy-notes.md`

**Scope (rescoped from original):** Service worker + manifest for installability, responsive AI chat, one aggregated dashboard endpoint. No Learn hub, no push notifications, no offline sync.

### 1.1 Service Worker & Manifest

**Plugin:** `vite-plugin-pwa` (wraps Workbox)

**Caching strategies:**

| Resource | Strategy | TTL |
|----------|----------|-----|
| App shell (JS, CSS from `public/build/`) | Precache (build-time) | Until next deploy |
| Fonts (system = free, Inter = Google) | CacheFirst | 30 days |
| API: dashboard/module summaries | NetworkFirst | 5 min stale |
| API: AI chat conversations | NetworkFirst | No expiry |
| API: Tax config, market rates | CacheFirst | 24 hours |
| Images/SVGs/icons | CacheFirst | 7 days |
| SSE streams (AI chat) | NetworkOnly | N/A |

**Vite config addition:**
```javascript
import { VitePWA } from 'vite-plugin-pwa';

// Add to plugins array in vite.config.js
VitePWA({
  registerType: 'autoUpdate',
  manifest: {
    name: 'Fynla - Financial Planning',
    short_name: 'Fynla',
    start_url: '/dashboard',
    display: 'standalone',
    orientation: 'portrait',
    theme_color: '#1F2A44',      // horizon-500
    background_color: '#F7F6F4', // eggshell-500
    categories: ['finance'],
    icons: [/* 72-512px sizes */],
    shortcuts: [
      { name: 'Ask Fyn', url: '/dashboard?openChat=true' },
      { name: 'Goals', url: '/goals' }
    ]
  },
  workbox: {
    globPatterns: ['**/*.{js,css,html,svg,png,ico,woff2}'],
    runtimeCaching: [/* strategies above */]
  }
})
```

**Build impact:** Zero — `vite-plugin-pwa` hooks into existing Vite pipeline. Existing `deploy/fynla-org/build.sh` unchanged.

### 1.2 Responsive AI Chat

Current `AiChatPanel.vue` (417 lines) is a fixed-position 420px popup with zero responsive classes. Make it full-screen on mobile viewports.

**Approach:** Add a mobile layout mode to the existing `AiChatPanel.vue` rather than creating a separate component (Phase 1 keeps things simple). The full `MobileFynChat.vue` component comes in Phase 2.

**Changes:**
- Detect viewport width (`window.innerWidth < 768`)
- Mobile mode: full-screen fixed overlay, `inset-0`, simplified header
- Add keyboard handling (input stays above keyboard)
- Add quick-reply chips below AI responses
- Voice input deferred to Phase 2 (Capacitor only)

### 1.3 Aggregated Dashboard Endpoint

**New route:** `GET /api/v1/mobile/dashboard`

Returns all module summaries in one request (replaces ~15 separate web API calls).

**Leverages existing:** `DashboardAggregator` service (already aggregates with 5-minute cache).

**Response shape:**
```json
{
  "success": true,
  "data": {
    "net_worth": { "total": 2300000, "change": 12500 },
    "modules": {
      "protection": { "status": "adequate", "policies_count": 3, "coverage_gap": 0 },
      "savings": { "total_balance": 45000, "emergency_fund_months": 4.2 },
      "investment": { "total_value": 180000, "ytd_return_pct": 7.2 },
      "retirement": { "projected_income": 32000, "on_track": true },
      "estate": { "estimated_iht": 85000 },
      "goals": { "active_count": 4, "on_track_count": 3 }
    },
    "alerts": [{ "type": "policy_renewal", "message": "Life insurance renews in 30 days" }]
  },
  "meta": { "cached_at": "2026-03-10T10:00:00Z", "etag": "W/\"abc123\"" }
}
```

**Backend files:**
- `routes/api_v1.php` — NEW parallel route file (does NOT modify existing `api.php`)
- `app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php` — NEW
- `app/Services/Mobile/MobileDashboardAggregator.php` — NEW (extends existing aggregator)
- `app/Providers/RouteServiceProvider.php` — MODIFY (add v1 route group)
- `app/Http/Middleware/ETagResponse.php` — NEW (304 Not Modified for unchanged data)
- `app/Http/Middleware/IdentifyMobileClient.php` — NEW (sets `is_mobile` attribute)
- `app/Http/Kernel.php` — MODIFY (register new middleware aliases)

### 1.4 Offline Indicator

Simple offline banner — not a full offline architecture.

- Detect network status via `navigator.onLine` + `online`/`offline` events
- Show `bg-savannah-200` banner: "Offline — showing last updated data" with timestamp
- Cached dashboard data displayed (from service worker cache)
- No write queuing in Phase 1

### 1.5 PWA Icons

Generate Fyn/Fynla icons at required sizes: 72, 96, 128, 144, 152, 192, 384, 512px. Both regular and maskable variants.

### Phase 1 Files Summary

| File | Action | Purpose |
|------|--------|---------|
| `vite.config.js` | MODIFY | Add vite-plugin-pwa |
| `package.json` | MODIFY | Add vite-plugin-pwa devDependency |
| `public/icons/` | CREATE | PWA icons (8 sizes) |
| `resources/js/components/Shared/AiChatPanel.vue` | MODIFY | Add full-screen mobile mode |
| `resources/js/mobile/OfflineBanner.vue` | CREATE | Offline status indicator |
| `resources/js/mobile/QuickReplyChips.vue` | CREATE | Suggested replies for AI chat |
| `routes/api_v1.php` | CREATE | Mobile API v1 routes |
| `app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php` | CREATE | Aggregated dashboard |
| `app/Services/Mobile/MobileDashboardAggregator.php` | CREATE | Dashboard data aggregation |
| `app/Http/Middleware/ETagResponse.php` | CREATE | HTTP caching |
| `app/Http/Middleware/IdentifyMobileClient.php` | CREATE | Mobile client detection |
| `app/Providers/RouteServiceProvider.php` | MODIFY | Register v1 routes + rate limiters |
| `app/Http/Kernel.php` | MODIFY | Register middleware |

### Phase 1 Security Capabilities (Honest Assessment)

| Feature | PWA Capability |
|---------|---------------|
| TLS 1.3 | Yes |
| Sanctum token auth | Yes |
| Certificate pinning | NO — not possible in PWA |
| Biometric auth | NO — not possible in PWA |
| Keychain/Keystore | NO — uses localStorage/sessionStorage |
| Screenshot prevention | NO |
| Device binding | Very limited |

PWA security relies on TLS + server-side controls. Native security features require Capacitor (Phase 2).

### Phase 1 Success Metrics
- PWA install rate (home screen adds)
- Mobile AI chat sessions per week
- Aggregated dashboard load time (<1 second)
- Service worker cache hit rate
- User retention (daily/weekly active on mobile)

---

## Phase 2: Capacitor + Core Mobile Experience (Weeks 7-14)

**Gate:** Phase 1 metrics show mobile engagement (or strategic decision to proceed).

**Execution strategy:** Split into **Phase 2a** (backend infrastructure, no Capacitor required) and **Phase 2b** (Capacitor + native UI, requires Xcode/Android Studio). Phase 2a is fully testable in the current Laravel + Vue environment. See `docs/plans/2026-03-10-phase2a-design.md` for the detailed 2a specification.

### 2.1 Capacitor Setup

**Version:** Capacitor 6.x

```typescript
// capacitor.config.ts
const config: CapacitorConfig = {
  appId: 'org.fynla.app',
  appName: 'Fynla',
  webDir: 'public',
  server: {
    url: process.env.CAPACITOR_DEV ? 'http://localhost:5173' : undefined,
    androidScheme: 'https',
  },
  plugins: {
    SplashScreen: { backgroundColor: '#F7F6F4', spinnerColor: '#E83E6D' },
    PushNotifications: { presentationOptions: ['badge', 'sound', 'alert'] },
    Keyboard: { resize: 'body', style: 'light' },
  },
};
```

**Project structure:**
```
fynla/
├── resources/js/           # Shared Vue app
├── resources/js/mobile/    # Mobile-specific components
├── capacitor.config.ts     # Capacitor config
├── ios/                    # Xcode project (auto-generated)
├── android/                # Android Studio (auto-generated)
├── deploy/mobile/          # Mobile build scripts
```

### 2.2 Native Plugin Dependencies

| Plugin | Package | Priority |
|--------|---------|----------|
| Biometrics | `@capgo/capacitor-native-biometric` | Critical |
| Push Notifications | `@capacitor/push-notifications` | Critical |
| Keyboard | `@capacitor/keyboard` | Critical |
| App Lifecycle | `@capacitor/app` | Critical |
| Preferences (encrypted storage) | `@capacitor/preferences` | Critical |
| Network Detection | `@capacitor/network` | High |
| Browser (in-app web views) | `@capacitor/browser` | High |
| Status Bar | `@capacitor/status-bar` | Medium |
| Haptics | `@capacitor/haptics` | Medium |
| Splash Screen | `@capacitor/splash-screen` | Medium |
| Device Info | `@capacitor/device` | Medium |
| Local Notifications | `@capacitor/local-notifications` | Medium |
| Share | `@capacitor/share` | Medium |

### 2.3 Platform Detection

```javascript
// resources/js/utils/platform.js
import { Capacitor } from '@capacitor/core';

export const platform = {
  isNative: () => Capacitor.isNativePlatform(),
  isIOS: () => Capacitor.getPlatform() === 'ios',
  isAndroid: () => Capacitor.getPlatform() === 'android',
  isWeb: () => Capacitor.getPlatform() === 'web',
  isMobileViewport: () => window.innerWidth < 768,
  canUseBiometrics: () => Capacitor.isNativePlatform(),
  canUsePushNotifications: () => Capacitor.isNativePlatform(),
  canUseHaptics: () => Capacitor.isNativePlatform(),
};
```

### 2.4 Mobile Auth Flow

**First login:**
1. `POST /api/v1/auth/login` — email + password + `platform: "mobile"` + `device_id`
2. Verification code (email or MFA TOTP)
3. `POST /api/v1/auth/verify-code` — returns 30-day `access_token`
4. Token stored in iOS Keychain / Android EncryptedSharedPreferences via `@capacitor/preferences`
5. Prompt: "Enable Face ID / Touch ID?"
6. Register device for push: `POST /api/v1/mobile/devices`

**Subsequent launches:**
1. Biometric prompt → success → retrieve stored token
2. `GET /api/v1/auth/user` — validate token still valid
3. If 401 → full re-auth

**Token refresh:**
1. Check token age on each launch
2. If >25 days → `POST /api/v1/auth/refresh-token` → new 30-day token, old revoked
3. If >30 days (expired) → full re-auth

**New backend endpoint:** `POST /api/v1/auth/refresh-token`

### 2.5 Mobile Navigation & Layout

**Bottom tab bar (5 tabs):**

| Tab | Label | Icon | Badge |
|-----|-------|------|-------|
| Home | Home | House outline | Red dot (alerts) |
| Fyn | Fyn | Springbok avatar (28x28pt) | Numeric (unread) |
| Learn | Learn | Book open | None |
| Goals | Goals | Flag | Numeric (milestones) |
| More | More | Grid | Red dot (settings) |

**Tab bar styling:**
- `bg-white border-t border-light-gray`, 83pt height (includes safe area)
- Active: `raspberry-500`, Inactive: `neutral-500`
- Shadow: `0 -1px 3px rgba(0,0,0,0.06)`

**Within-tab navigation:** Stack-based (push/pop). Tab state preserved when switching.

**Mobile header:** 44pt, `bg-white border-b border-light-gray`, centred title, back chevron left, action icons right.

### 2.6 Screen Specifications

#### Home / Dashboard
1. Greeting: "Good morning, {firstName}"
2. **Net worth hero card** — `bg-white rounded-xl shadow-sm p-5`, value in `text-3xl font-black text-horizon-500`, change in `spring-500` or `raspberry-500`, 90-day sparkline
3. **Fyn insight card** — `bg-horizon-500 rounded-xl p-4 text-white`, Fyn avatar + 1-sentence insight
4. **Alerts panel** — `bg-violet-50 rounded-lg border border-violet-200`, max 3 shown
5. **Module summary grid** — 2-column, each card: icon + name + one key metric + coloured left border (spring/violet/raspberry for status)
6. **Journey progress** — horizontal scroll of progress ring badges

**Loading:** Skeleton screens (`bg-savannah-100 animate-pulse`)
**Offline:** Grey tint + `bg-savannah-200` banner with last-updated timestamp
**Empty:** Fyn avatar + "Welcome! Let's get started" + onboarding button

#### Fyn Chat (Full-Screen)
- `bg-eggshell-500` message area
- Fyn messages: `bg-white rounded-2xl rounded-bl-sm p-4`, left-aligned, max-width 85%
- User messages: `bg-raspberry-50 rounded-2xl rounded-br-sm p-4`, right-aligned
- Typing indicator: three animated dots in Fyn bubble
- Tool execution: "Fyn is analysing your portfolio..." with spinner
- Quick reply chips: horizontal scroll, `bg-white border border-light-gray rounded-full px-4 py-2`
- Input: `bg-eggshell-500 rounded-full`, mic button left, send button right (`bg-raspberry-500` circle)
- Keyboard-aware: input stays above keyboard
- Voice input button (Capacitor only): speech-to-text via native plugin

#### Learn Hub (Basic — Phase 2)
- Topic grid (2-column): Tax, Pensions, Protection, Investing, Estate, Budgeting, ISAs, Goals
- Each topic card: `bg-white rounded-xl border border-light-gray p-4`, icon in `violet-500`, article count
- Topic detail: curated links to MoneyHelper, HMRC, Pension Wise + "Ask Fyn about this" buttons
- Phase 3 adds: in-app articles, personalised "Your situation" section, AI-generated content

#### Goals
- Filter chips: All/Active/Completed
- Goal cards: `bg-white rounded-xl border border-light-gray p-4`, progress ring (56x56pt), status text
- Goal detail: large progress ring (120x120pt), milestone tracker, contribution log
- Contribution FAB: `bg-raspberry-500 text-white rounded-full` 56x56pt
- Milestone celebrations: full-screen overlay + confetti + Fyn message + haptic feedback

#### More Menu
- User profile card (avatar + name + subscription badge)
- Module links (2-column grid, 7 modules)
- Settings list (account, security, notifications, subscription, data, help, about)
- "Open full web app" link → `fynla.org`
- Logout

#### Module Summaries (7 screens, shared template)
1. Header with back + module name + "View full detail" icon
2. Hero metric card (one key number per module)
3. Fyn's take card (`bg-horizon-500 text-white`)
4. Key metrics (2-3 cards, 2-column grid)
5. Action items (what needs attention)
6. "Learn about {module}" card
7. "View full detail on web" button

### 2.7 Push Notifications

**No existing push infrastructure.** Build from scratch.

**Database migrations:**

```sql
-- device_tokens
CREATE TABLE device_tokens (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
  device_token VARCHAR(500),
  device_id VARCHAR(255),
  platform ENUM('ios', 'android'),
  device_name VARCHAR(255) NULL,
  app_version VARCHAR(20),
  os_version VARCHAR(50) NULL,
  last_used_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  UNIQUE(user_id, device_id),
  INDEX(device_token)
);

-- notification_preferences
CREATE TABLE notification_preferences (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
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

**Notification triggers:**

| Event | Source | Type |
|-------|--------|------|
| Policy renewal (30 days) | Daily cron | `policy_renewals` |
| Goal milestone (25/50/75/100%) | `GoalContributionObserver` | `goal_milestones` |
| Contribution reminder (weekly) | Weekly cron | `contribution_reminders` |
| Subscription expiring (7d, 1d) | `TrialExpirationJob` | `payment_alerts` |
| New device login | `AuthController` | `security_alerts` |
| ISA deadline approaching | March cron | `fyn_daily_insight` |

**Package:** `laravel-notification-channels/fcm` (Firebase Cloud Messaging handles both iOS APNs and Android).

### 2.8 Deep Links

**Server files:**
- `public/.well-known/apple-app-site-association` — Universal Links config
- `public/.well-known/assetlinks.json` — Android App Links config

**Supported paths:** `/protection`, `/savings`, `/investments`, `/retirement`, `/estate`, `/goals`, `/net-worth`, `/tax`

**Excluded:** `/api/*`, `/admin/*`, `/login`, `/register`

**"View full detail" button behaviour:**
- Capacitor: `Browser.open({ url: 'https://fynla.org/{path}' })` → SFSafariViewController / Custom Tab
- PWA: `window.open()` to new tab

### 2.9 CORS & Middleware Updates

**CORS (`config/cors.php`):**
```php
'allowed_origins' => [
  // ... existing origins
  'capacitor://localhost',  // iOS Capacitor
  'http://localhost',        // Android Capacitor
]
```

**Do NOT add Capacitor to Sanctum stateful domains** — this forces stateless token auth, which is what we want for mobile.

**CSP (`SecurityHeaders.php`):** Add Capacitor origins to `connect-src`.

**PreviewWriteInterceptor:** Add `api/v1/mobile/devices` to `EXCLUDED_ROUTES`.

**Rate limiters (new):**
- `mobile-dashboard`: 30/min per user
- `ai-chat`: 20/min per user
- `device-registration`: 5/min per user

### 2.10 State Management

**Vuex persistence for key stores only:**

| Store | Persist | Reason |
|-------|---------|--------|
| `auth` | Full | Token + user info |
| `dashboard` | Full | Cached dashboard for offline |
| `aiChat` | Full | Conversation history offline |
| `goals` | Full | Goal progress offline |
| `userProfile` | Full | Name, preferences, tier |
| `mobileDashboard` | Full | Aggregated summary |
| `journeys` | Full | Journey progress |
| `protection` through `estate` | Summary only | Key metrics cached |

**New Vuex stores (4):**
- `mobileDashboard` — aggregated summary from `/api/v1/mobile/dashboard`
- `mobileSync` — online/offline state, pending queue
- `mobileLearn` — education topics and cached content
- `mobileNotifications` — push state, unread count

**Persistence plugin:** `vuex-persistedstate` with Capacitor Preferences on native, localStorage on web.

**No sync manager.** Server wins for all calculations. Mobile is read-focused. Only simple writes (contributions, notes) can be queued offline.

### 2.11 Build Pipeline

```bash
# deploy/mobile/build-ios.sh
export VITE_BASE_PATH=/
export VITE_API_BASE_URL=https://fynla.org
npm run build && npx cap sync ios

# deploy/mobile/build-android.sh
export VITE_BASE_PATH=/
export VITE_API_BASE_URL=https://fynla.org
npm run build && npx cap sync android
```

Existing `deploy/fynla-org/build.sh` unchanged. Web and mobile builds are separate pipelines.

### 2.12 SSE Streaming on Mobile

- Use `fetch()` with `ReadableStream` (not `EventSource` — POST body + auth header support)
- App backgrounded → abort stream via `AbortController`
- App foregrounded → reload conversation via GET
- No background SSE — push notifications handle background alerts

```javascript
import { App } from '@capacitor/app';
App.addListener('appStateChange', ({ isActive }) => {
  if (!isActive) store.dispatch('aiChat/abortStreaming');
  else store.dispatch('mobileDashboard/refresh');
});
```

### 2.13 Social Share

**Plugin:** `@capacitor/share` (native share sheet on iOS/Android, Web Share API fallback on PWA)

**Shareable Content:**

| Content | Trigger | Share Text | Privacy |
|---------|---------|------------|---------|
| Goal milestone | Milestone celebration overlay | "I just hit {milestone}% of my {goalName} goal with Fynla!" | No amounts — percentage only |
| Net worth milestone | Dashboard (manual tap) | "I've reached a new financial milestone with Fynla!" | No figures — generic message |
| Fyn insight | Insight card share icon | "{sanitised insight text} — via Fynla" | AI strips any personal data before share text is generated |
| Learn article | Article detail share icon | "{article title} — {url}" | Public content, no privacy concern |
| App referral | More menu → "Invite friends" | "I use Fynla to plan my finances. Check it out: https://fynla.org" | No user data |

**Privacy rules (CRITICAL for a financial app):**
- **Never** include monetary values, account balances, or portfolio figures in share text
- **Never** include full names of other joint owners or beneficiaries
- Goal names are user-defined — warn if goal name contains sensitive info (e.g., "Divorce fund")
- All share text is generated server-side via a dedicated endpoint to enforce sanitisation
- User must explicitly tap share — no auto-sharing, no pre-populated social posts

**Frontend component:**

```
resources/js/mobile/ShareButton.vue
```

- Renders a share icon (arrow-up-from-bracket)
- Accepts `shareType` and `entityId` props
- Calls `GET /api/v1/mobile/share/{type}/{id}` to get sanitised share payload
- Invokes native share sheet:

```javascript
import { Share } from '@capacitor/share';

await Share.share({
  title: payload.title,
  text: payload.text,
  url: payload.url,
  dialogTitle: 'Share via',  // Android only
});
```

- Web fallback: `navigator.share()` if available, otherwise copy-to-clipboard with toast

**Backend:**

| File | Action | Purpose |
|------|--------|---------|
| `app/Http/Controllers/Api/V1/Mobile/ShareController.php` | CREATE | Generates sanitised share payloads |
| `app/Services/Mobile/ShareContentGenerator.php` | CREATE | Builds share text per type, strips PII |
| `app/Http/Requests/V1/ShareContentRequest.php` | CREATE | Validates share type + entity ID |

**Share payload response:**
```json
{
  "success": true,
  "data": {
    "title": "Fynla - Financial Planning",
    "text": "I just hit 50% of my House Deposit goal with Fynla!",
    "url": "https://fynla.org?ref=share&type=goal_milestone"
  }
}
```

**Referral tracking (lightweight):**
- Share URLs include `?ref=share&type={shareType}` query params
- Tracked in analytics (Phase 0 instrumentation) — no separate referral system in Phase 2
- Full referral programme (credits, rewards) deferred to Phase 4 if share metrics justify it

### Phase 2 Implementation Order

**Phase 2a — Backend Infrastructure (no Capacitor required):**

| Task | Focus | Deliverable |
|------|-------|-------------|
| 2a-01 | Database migrations | device_tokens, notification_preferences tables, user_sessions device_id |
| 2a-02 | Models + factories | DeviceToken, NotificationPreference with factories |
| 2a-03 | Auth token refresh | POST /api/v1/auth/refresh-token endpoint |
| 2a-04 | Device registration API | CRUD endpoints for device tokens |
| 2a-05 | Notification preferences API | GET/PUT preference endpoints |
| 2a-06 | Push notification service | FCM integration, 6 notification classes |
| 2a-07 | Scheduled commands | Daily insight + policy renewal crons |
| 2a-08 | Deep link configuration | .well-known/apple-app-site-association + assetlinks.json |
| 2a-09 | CORS + middleware updates | Capacitor origins, CSP, PreviewWriteInterceptor |
| 2a-10 | Vuex persistence + platform | vuex-persistedstate, mobileDashboard store, platform.js |
| 2a-11 | Social share backend | ShareController, ShareContentGenerator (no PII) |
| 2a-12 | Integration testing + review | Full test suite, code review, deploy notes |

**Phase 2b — Capacitor + Native UI (requires Xcode/Android Studio):**

| Week | Focus | Deliverable |
|------|-------|-------------|
| 7-8 | Capacitor setup + auth | Config, native projects, biometric auth, token persistence |
| 8-9 | Mobile layout + navigation | MobileLayout, BottomTabBar, MobileHeader |
| 9-10 | Dashboard + module summaries | MobileDashboard, 7 module summary screens, net worth card |
| 10-11 | Full-screen AI chat | MobileFynChat, voice input, SSE lifecycle |
| 11-12 | Push + social share frontend | Native push handling, ShareButton.vue |
| 12-13 | Goals + deep links | Mobile goals list/detail, milestone celebrations |
| 13-14 | Basic Learn hub + More menu | Topic grid with external links, settings, profile |

---

## Phase 3: Polish & App Store (Weeks 15-20)

### 3.1 App Store Preparation

**Budget 4-6 weeks** (not 2-4) for financial app + AI chat review.

**Apple (financial guideline 4.2 + AI guideline 5.6.4):**
- Privacy nutrition labels (declare all data collection)
- AI usage disclosure (Fyn clearly labelled as AI-generated)
- Financial disclaimer ("Not regulated financial advice")
- Data handling documentation
- Expect 1-2 rejection cycles (2-3 weeks each)

**Google Play (financial services policy):**
- Detailed data safety form
- AI features safety review
- Typically faster (1-3 weeks)

**Strategy:** Submit minimal skeleton build for early review feedback BEFORE building full features. Start Apple Developer enrollment ($99/year) immediately.

**Subscription strategy:**
- Route all subscriptions through web (Revolut) — avoid Apple's 30% cut
- Use "reader app" exemption or link-out pattern
- Plan B if Apple rejects exemption: web-only subscription management

### 3.2 Enhanced Learn Hub (if user research supports)

- In-app articles (curated, not AI-generated) reviewed for FCA compliance
- "Your situation" personalised section using user data
- "Ask Fyn about this" button on every article
- Offline caching of articles
- Difficulty badges: Beginner/Intermediate/Advanced

### 3.3 Additional Polish

- Haptic feedback: goal milestones (medium), chat sends (light), saves (success triple)
- Skeleton screen loading states (all screens)
- Error states (network, API 500, AI unavailable, session expired)
- Accessibility: VoiceOver/TalkBack labels, Dynamic Type support, WCAG AA contrast
- Fyn mascot: onboarding illustration, empty states, achievement celebrations

### 3.4 Onboarding

Use existing Quick Mode adapted for mobile (3 steps, validated, reliable):
1. Welcome screen with Fyn
2. Journey selection (focus areas)
3. Quick profile (name, age, employment)

Conversational AI onboarding deferred to Phase 4 — parsing natural language into validated financial fields is error-prone and risks corrupting seed data.

---

## Phase 4: Enhanced Features (Weeks 21+, Optional)

Only if Phase 2-3 metrics justify:

- Home screen widgets (net worth, goal progress)
- Quick Actions (3D Touch: "Ask Fyn", "Check Goals")
- Siri/Google Assistant integration
- Document scanning with OCR
- Proactive AI notifications
- Apple Watch / Wear OS companion
- Conversational onboarding experiment
- Full offline sync (if user research shows demand)
- Advanced voice input for financial queries
- Full referral programme (credits, rewards) — requires share metrics from Phase 2
- **Shared goals (exploration)** — two tiers requiring separate product discovery:
  - *Tier 1 — Goal sharing (lightweight):* Share goal progress with contacts (percentage only, no financial data exposed), receive encouragement/reactions, follow friends' progress. Builds on Phase 2 social share infrastructure. Requires: contacts/friends system, privacy-safe goal visibility toggle, activity feed.
  - *Tier 2 — Collaborative goals (heavyweight):* Multiple users contributing toward a shared goal (group holiday, shared gift). Requires: contribution tracking across separate accounts, goal ownership model, leave/join flows, dispute resolution, GDPR assessment for sharing financial data between users, FCA review of facilitating group financial activity. Consider whether Open Banking integration is needed for accurate cross-user contribution tracking. **Do not build without dedicated product discovery and legal review.**

---

## Complete Component Inventory

### New Mobile-Specific Components (~35)

| Component | Path | Complexity |
|-----------|------|------------|
| `MobileTabBar.vue` | `resources/js/mobile/MobileTabBar.vue` | Medium |
| `MobileHeader.vue` | `resources/js/mobile/MobileHeader.vue` | Low |
| `MobileDashboard.vue` | `resources/js/mobile/MobileDashboard.vue` | Medium |
| `MobileNetWorthCard.vue` | `resources/js/mobile/MobileNetWorthCard.vue` | Low |
| `FynInsightCard.vue` | `resources/js/mobile/FynInsightCard.vue` | Low |
| `MobileAlertsList.vue` | `resources/js/mobile/MobileAlertsList.vue` | Low |
| `ModuleSummaryCard.vue` | `resources/js/mobile/ModuleSummaryCard.vue` | Low |
| `MobileFynChat.vue` | `resources/js/mobile/MobileFynChat.vue` | High |
| `ChatBubble.vue` | `resources/js/mobile/ChatBubble.vue` | Low |
| `QuickReplyChips.vue` | `resources/js/mobile/QuickReplyChips.vue` | Low |
| `SuggestedPrompts.vue` | `resources/js/mobile/SuggestedPrompts.vue` | Low |
| `VoiceInputButton.vue` | `resources/js/mobile/VoiceInputButton.vue` | Medium |
| `TypingIndicator.vue` | `resources/js/mobile/TypingIndicator.vue` | Low |
| `ToolExecutionStatus.vue` | `resources/js/mobile/ToolExecutionStatus.vue` | Low |
| `LearnHub.vue` | `resources/js/mobile/learn/LearnHub.vue` | Medium |
| `LearnTopicDetail.vue` | `resources/js/mobile/learn/LearnTopicDetail.vue` | Medium |
| `LearnArticle.vue` | `resources/js/mobile/learn/LearnArticle.vue` | Low |
| `TopicCard.vue` | `resources/js/mobile/learn/TopicCard.vue` | Low |
| `MobileGoalsList.vue` | `resources/js/mobile/goals/MobileGoalsList.vue` | Medium |
| `MobileGoalCard.vue` | `resources/js/mobile/goals/MobileGoalCard.vue` | Low |
| `MobileGoalDetail.vue` | `resources/js/mobile/goals/MobileGoalDetail.vue` | Medium |
| `ContributionFAB.vue` | `resources/js/mobile/goals/ContributionFAB.vue` | Low |
| `MilestoneOverlay.vue` | `resources/js/mobile/goals/MilestoneOverlay.vue` | Medium |
| `ProgressRing.vue` | `resources/js/mobile/charts/ProgressRing.vue` | Low |
| `NetWorthSparkline.vue` | `resources/js/mobile/charts/NetWorthSparkline.vue` | Low |
| `AllocationDonut.vue` | `resources/js/mobile/charts/AllocationDonut.vue` | Low |
| `MoreMenu.vue` | `resources/js/mobile/MoreMenu.vue` | Medium |
| `SettingsList.vue` | `resources/js/mobile/SettingsList.vue` | Low |
| `ProtectionSummary.vue` | `resources/js/mobile/summaries/ProtectionSummary.vue` | Low |
| `SavingsSummary.vue` | `resources/js/mobile/summaries/SavingsSummary.vue` | Low |
| `InvestmentSummary.vue` | `resources/js/mobile/summaries/InvestmentSummary.vue` | Low |
| `RetirementSummary.vue` | `resources/js/mobile/summaries/RetirementSummary.vue` | Low |
| `EstateSummary.vue` | `resources/js/mobile/summaries/EstateSummary.vue` | Low |
| `GoalsSummary.vue` | `resources/js/mobile/summaries/GoalsSummary.vue` | Low |
| `TaxSummary.vue` | `resources/js/mobile/summaries/TaxSummary.vue` | Low |
| `MobileOnboarding.vue` | `resources/js/mobile/MobileOnboarding.vue` | Medium |
| `BiometricPrompt.vue` | `resources/js/mobile/BiometricPrompt.vue` | Medium |
| `DeepLinkButton.vue` | `resources/js/mobile/DeepLinkButton.vue` | Low |
| `OfflineBanner.vue` | `resources/js/mobile/OfflineBanner.vue` | Low |
| `PullToRefresh.vue` | `resources/js/mobile/PullToRefresh.vue` | Low |
| `ShareButton.vue` | `resources/js/mobile/ShareButton.vue` | Low |

### Mobile Variants of Existing Components (~18)

| Web Component | Mobile Variant | Changes |
|--------------|---------------|---------|
| `AiChatPanel.vue` | `MobileFynChat.vue` | Full-screen, voice, keyboard handling |
| `DashboardCard.vue` | `ModuleSummaryCard.vue` | Smaller, 2-col grid |
| `NetWorthSummary.vue` | `MobileNetWorthCard.vue` | Single metric card |
| `GoalCard.vue` | `MobileGoalCard.vue` | Progress ring, touch-optimised |
| `GoalDetailInline.vue` | `MobileGoalDetail.vue` | Full-screen |
| `ProtectionOverviewCard.vue` | `ProtectionSummary.vue` | Vertical mobile layout |
| `SavingsOverviewCard.vue` | `SavingsSummary.vue` | Simplified |
| `InvestmentsOverviewCard.vue` | `InvestmentSummary.vue` | Simplified donut |
| `EstateOverviewCard.vue` | `EstateSummary.vue` | Key IHT number |
| `GoalsOverviewCard.vue` | `GoalsSummary.vue` | Aggregate ring |
| `UKTaxesOverviewCard.vue` | `TaxSummary.vue` | Effective rate + allowances |
| `Navbar.vue` | `MobileHeader.vue` | Back button, no side menu |
| `SideMenu.vue` | `MoreMenu.vue` | Grid in More tab |
| `AlertsPanel.vue` | `MobileAlertsList.vue` | Compact inline |
| `EmptyDashboard.vue` | Mobile empty state | Fyn-centred |
| `ContributionModal.vue` | `ContributionFAB.vue` | Bottom sheet |
| `OnboardingWizard.vue` | `MobileOnboarding.vue` | Quick 3-step mobile |
| `FocusAreaGrid.vue` | Reuse with padding | Touch target adjustment |

### Reusable As-Is (~80+ existing)

All API services (36), Vuex stores (24), mixins, utils, small UI elements (InfoTooltip, ConfidenceBadge, RiskBadge, ProcessingState, ConfirmDialog, GoalProgressBar, GoalMilestoneTracker, EventIcon, etc.).

---

## Complete Backend File Inventory

### New Files (~30)

**Controllers (7):**
- `app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php`
- `app/Http/Controllers/Api/V1/Mobile/InsightsController.php`
- `app/Http/Controllers/Api/V1/Mobile/DeviceController.php`
- `app/Http/Controllers/Api/V1/Mobile/EducationController.php`
- `app/Http/Controllers/Api/V1/Mobile/ModuleSummaryController.php`
- `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php`
- `app/Http/Controllers/Api/V1/Mobile/ShareController.php`

**Request Validation (4):**
- `app/Http/Requests/V1/RegisterDeviceRequest.php`
- `app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php`
- `app/Http/Requests/V1/MobileLoginRequest.php`
- `app/Http/Requests/V1/ShareContentRequest.php`

**Services (4):**
- `app/Services/Mobile/MobileDashboardAggregator.php`
- `app/Services/Mobile/DailyInsightGenerator.php`
- `app/Services/Mobile/PushNotificationService.php`
- `app/Services/Mobile/ShareContentGenerator.php`

**Middleware (3):**
- `app/Http/Middleware/IdentifyMobileClient.php`
- `app/Http/Middleware/ETagResponse.php`
- `app/Http/Middleware/VerifyMobileSignature.php`

**Models (2):**
- `app/Models/DeviceToken.php`
- `app/Models/NotificationPreference.php`

**Notifications (6):**
- `app/Notifications/PolicyRenewalNotification.php`
- `app/Notifications/GoalMilestoneNotification.php`
- `app/Notifications/ContributionReminderNotification.php`
- `app/Notifications/SecurityAlertNotification.php`
- `app/Notifications/SubscriptionExpiringNotification.php`
- `app/Notifications/DailyInsightNotification.php`

**Migrations (3):**
- `database/migrations/YYYY_create_device_tokens_table.php`
- `database/migrations/YYYY_create_notification_preferences_table.php`
- `database/migrations/YYYY_add_device_id_to_user_sessions_table.php`

**Routes (1):**
- `routes/api_v1.php`

**Deep Link Config (2):**
- `public/.well-known/apple-app-site-association`
- `public/.well-known/assetlinks.json`

**Scheduled Commands (2):**
- `app/Console/Commands/SendDailyInsightNotifications.php`
- `app/Console/Commands/SendPolicyRenewalReminders.php`

**Build Scripts (2):**
- `deploy/mobile/build-ios.sh`
- `deploy/mobile/build-android.sh`

### Modified Files (~14)

| File | Change |
|------|--------|
| `app/Providers/RouteServiceProvider.php` | Add v1 route group + mobile rate limiters |
| `app/Http/Kernel.php` | Register new middleware aliases |
| `config/cors.php` | Add Capacitor origins |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Add device registration to excluded routes |
| `app/Http/Middleware/CheckSubscription.php` | Exclude mobile device routes |
| `app/Http/Middleware/SecurityHeaders.php` | Add Capacitor to CSP connect-src |
| `app/Http/Controllers/Api/AuthController.php` | Add platform/device_id to login, add refresh-token |
| `app/Models/UserSession.php` | Add device_id to fillable |
| `app/Services/AI/AiContextBuilder.php` | Add mobile screen name mappings |
| `app/Console/Kernel.php` | Schedule notification commands |
| `composer.json` | Add laravel-notification-channels/fcm |
| `.env.example` | Add FCM_SERVER_KEY, FCM_PROJECT_ID |
| `vite.config.js` | Add vite-plugin-pwa, mobile entry point |
| `package.json` | Add Capacitor + PWA dependencies |

---

## Cost Model (Corrected)

### Fixed Costs

| Item | Cost |
|------|------|
| Apple Developer Programme | £79/year |
| Google Play Developer | £20 one-time |
| Firebase (free tier) | £0/month (up to 500 devices) |

### Variable Costs at Scale

| Item | 200 users | 500 users | 1000 users |
|------|-----------|-----------|------------|
| AI API (daily insights + chat) | £300-500/month | £800-1500/month | £1500-3000/month |
| Firebase (beyond free tier) | £0-20/month | £20-50/month | £50-100/month |
| OTA updates (Capgo) | £50/month | £50/month | £100/month |
| Total monthly | £350-570 | £870-1600 | £1650-3200 |

### Break-Even Analysis

At £10.99/month (Standard tier):
- **50 mobile subscribers** = £550/month → covers 200-user costs
- **150 mobile subscribers** = £1,650/month → covers 1000-user costs

**Daily insights must be batched** — not one AI call per user per day. Generate for cohorts (by module focus) and personalise at render time.

**Real business case:** Retention, not acquisition. If mobile reduces monthly churn from 8% to 5%, the lifetime value increase across 500 users far exceeds the mobile development and operating costs.

---

## Risk Register

| # | Risk | Severity | Mitigation |
|---|------|----------|-----------|
| 1 | No user research backs mobile demand | CRITICAL | Phase 0 analytics before building |
| 2 | PWA scope creep blows timeline | HIGH | Rescoped Phase 1 (3-4 weeks, no Learn hub) |
| 3 | AI latency on mobile networks | HIGH | Data-driven Home tab works without AI; Fyn enhances |
| 4 | sessionStorage incompatible with mobile | CRITICAL | Phase 0 prerequisite: tokenStorage abstraction |
| 5 | App store rejection (financial + AI) | HIGH | Early skeleton submission; 4-6 week buffer |
| 6 | Apple rejects reader app exemption | MEDIUM | Plan B: web-only subscriptions |
| 7 | AI API costs exceed revenue | HIGH | Batch daily insights; rate limit; monitor costs |
| 8 | Learn Hub content compliance (FCA) | MEDIUM | Phase 2: external links only. Phase 3: curated content |
| 9 | PWA push unreliable on iOS | MEDIUM | Honest expectation-setting; Capacitor for reliable push |
| 10 | Offline sync complexity | MEDIUM | No sync manager. ETags + simple caching only |
| 11 | Bundle size regression | MEDIUM | Separate mobile entry point; CI size checks |
| 12 | Capacitor plugin lag behind OS | LOW | Pin versions; test on major OS releases |

---

## Timeline Summary

| Phase | Weeks | Deliverable |
|-------|-------|-------------|
| **Phase 0** | 1-2 | Analytics instrumentation + auth token abstraction |
| **Phase 1** | 3-6 | PWA (service worker, manifest, responsive chat, dashboard endpoint) |
| **Phase 2** | 7-14 | Capacitor app (full mobile experience, push notifications, biometrics) |
| **Phase 3** | 15-20 | App store submission, polish, basic Learn hub, accessibility |
| **Phase 4** | 21+ | Widgets, Siri/Google Assistant, document scanning (optional) |

**Total to app store: ~20 weeks** (5 months)
**Total to PWA: ~6 weeks** (1.5 months)

---

## Design System Quick Reference (Mobile)

| Token | Usage | Value |
|-------|-------|-------|
| `raspberry-500` | CTAs, active tabs, send button | #E83E6D |
| `horizon-500` | Text, headings, Fyn insight bg | #1F2A44 |
| `spring-500` | Success, on-track, positive change | #20B486 |
| `violet-500` | Warnings, focus rings, education icons | #5854E6 |
| `savannah-100` | Subtle backgrounds, hover states | #FDFAF7 |
| `eggshell-500` | Page/chat background | #F7F6F4 |
| `neutral-500` | Muted text, inactive tabs | #717171 |
| `light-gray` | Borders, dividers | #EEEEEE |

**Typography:** System fonts (SF Pro iOS, Roboto Android). Min body: 16pt. Weights: 900 (display), 700 (headings), 400 (body).

**Touch targets:** Minimum 44x44pt. Buttons min height 44pt. List rows min 48pt.

**Banned:** amber-*, orange-*, scores, hardcoded hex, acronyms in user-facing text.
