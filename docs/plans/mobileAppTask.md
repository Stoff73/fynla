# Fynla Mobile App — Detailed Task List

**Created:** 10 March 2026
**Based on:** [Implementation Plan](2026-03-10-mobile-implementation-plan.md)
**Total Tasks:** 108 | **Phases:** 0-4 | **Estimated Duration:** 20 weeks to app store
**Review:** Approved with revisions — Voice of Reason + Devil's Advocate (10 March 2026)

---

## How to Use This Document

Each task includes:
- **ID** — sequential, prefixed by phase (P0-, P1-, P2-, P3-, P4-)
- **Task** — what to do
- **Tools** — Claude Code agents, skills, commands, plugins, and MCP servers to use
- **Files** — specific files to create or modify
- **Tests** — how to verify completion
- **Sign-off** — acceptance criteria that must be met before moving on
- **Depends on** — which tasks must complete first

### Tool Legend

| Abbreviation | Tool |
|-------------|------|
| `agent:explore` | Explore agent (codebase search) |
| `agent:feature-dev` | Feature development agent |
| `agent:security-reviewer` | Security review agent |
| `agent:tax-compliance` | Tax compliance reviewer |
| `agent:db-optimizer` | Database optimizer agent |
| `agent:premium-ui` | Premium UI designer agent |
| `agent:code-reviewer` | Code review agent (superpowers) |
| `skill:feature-dev` | `/feature-dev` skill |
| `skill:systematic-debugging` | `/systematic-debugging` skill |
| `skill:tech-debt-session` | `/tech-debt-session` skill |
| `skill:code-review` | `/code-review` skill |
| `skill:ship` | `/ship` skill |
| `skill:deploy-notes` | `/deploy-notes` skill |
| `skill:session-end` | `/session-end` skill |
| `cmd:pest` | `./vendor/bin/pest` |
| `cmd:pint` | `./vendor/bin/pint` |
| `cmd:seed` | `php artisan db:seed` |
| `cmd:build` | `./deploy/fynla-org/build.sh` |
| `mcp:playwright` | Playwright MCP for browser testing |
| `mcp:trivy` | Trivy MCP for security scanning |
| `mcp:context7` | Context7 MCP for library docs |

---

## Phase 0: Instrument & Measure (Weeks 1-2)

### P0-01: Analytics Package Selection & Installation

| Field | Detail |
|-------|--------|
| **Task** | Research and install a privacy-first analytics package (Plausible Cloud or PostHog self-hosted). No PII collection. |
| **Tools** | `mcp:context7` (Plausible/PostHog docs), `skill:feature-dev`, `agent:explore` |
| **Files** | `resources/js/app.js` (MODIFY), `resources/views/layouts/app.blade.php` (MODIFY), `.env.example` (MODIFY) |
| **Tests** | Load any page → verify analytics script loads in Network tab. Check analytics dashboard shows page view. |
| **Sign-off** | - [ ] Analytics script loading on all authenticated pages. - [ ] No PII sent (verify in Network tab). - [ ] `.env` variable controls analytics (disabled in testing). |
| **Depends on** | None |

### P0-02: Instrument Mobile-Specific Metrics

| Field | Detail |
|-------|--------|
| **Task** | Add custom event tracking: device type, viewport size, pages visited, AI chat session starts, AI chat messages sent, module visits, session duration. |
| **Tools** | `skill:feature-dev`, `agent:explore` |
| **Files** | `resources/js/services/analyticsService.js` (CREATE), `resources/js/router/index.js` (MODIFY — add route tracking), `resources/js/components/Shared/AiChatPanel.vue` (MODIFY — track chat opens) |
| **Tests** | Navigate 3 pages → verify 3 pageview events in analytics. Open AI chat → verify `chat_opened` event. Send message → verify `chat_message_sent` event. |
| **Sign-off** | - [ ] Route changes tracked. - [ ] AI chat opens/messages tracked. - [ ] Device type captured. - [ ] Viewport dimensions captured. |
| **Depends on** | P0-01 |

### P0-03: Optional User Survey Banner

| Field | Detail |
|-------|--------|
| **Task** | Create in-app banner: "Would you use a Fynla mobile app?" (Yes/Maybe/No). Show once per user. Store preference. |
| **Tools** | `agent:premium-ui` (for polished banner), `skill:feature-dev` |
| **Files** | `resources/js/components/Shared/MobileSurveyBanner.vue` (CREATE), `resources/js/services/analyticsService.js` (MODIFY) |
| **Tests** | Login → see banner. Click "Yes" → banner dismisses, event tracked. Refresh → banner does not reappear. |
| **Sign-off** | - [ ] Banner displays once per user. - [ ] Response tracked in analytics. - [ ] Does not interfere with existing UI. - [ ] Dismissible with X button. |
| **Depends on** | P0-01 |

### P0-04: Auth Token Storage Abstraction (CRITICAL PREREQUISITE)

> **REVIEW NOTE (Devil's Advocate — CRITICAL):** `@capacitor/preferences` stores data in `UserDefaults` (iOS) and `SharedPreferences` (Android) — these are NOT secure storage. Auth tokens for a financial app MUST use iOS Keychain and Android Keystore. Use `@capgo/capacitor-native-biometric` (already in plugin list) for biometric-associated token storage, or `@nicepayments/capacitor-native-settings` for Keychain/Keystore access. The `tokenStorage.js` abstraction must use SECURE native storage on mobile, not Preferences.
>
> **REVIEW NOTE (Devil's Advocate — CRITICAL):** `sessionStorage` is also used directly in `store/modules/auth.js`, `store/modules/preview.js`, and `app.js` — these 3 files are NOT in the original migration list and must be included. Run `grep -rn "sessionStorage" resources/js/` to find ALL references before starting.
>
> **REVIEW NOTE (Devil's Advocate — HIGH):** `@capacitor/preferences` is async (returns Promises) while `sessionStorage` is sync. The `api.js` Axios request interceptor attaches the Bearer token synchronously — it must become async-aware. This is an architectural change, not a simple find-and-replace.

| Field | Detail |
|-------|--------|
| **Task** | Create `tokenStorage.js` abstracting auth token storage. Web uses `sessionStorage` (unchanged behaviour). Mobile/Capacitor uses SECURE native storage (iOS Keychain / Android Keystore via `@capgo/capacitor-native-biometric` or dedicated secure storage plugin — NOT `@capacitor/preferences`). Update ALL 7 files that reference `sessionStorage` for auth tokens directly. Handle async/sync difference: Capacitor storage is async (Promises), sessionStorage is sync — the Axios interceptor in `api.js` must become async-aware. |
| **Tools** | `skill:feature-dev`, `agent:security-reviewer`, `agent:explore` (grep ALL sessionStorage refs across entire codebase) |
| **Files** | `resources/js/services/tokenStorage.js` (CREATE), `resources/js/services/api.js` (MODIFY — async interceptor), `resources/js/services/authService.js` (MODIFY), `resources/js/services/aiChatService.js` (MODIFY), `resources/js/services/sessionLifecycleService.js` (MODIFY), `resources/js/store/modules/auth.js` (MODIFY), `resources/js/store/modules/preview.js` (MODIFY), `resources/js/app.js` (MODIFY) |
| **Tests** | `cmd:pest` — all existing auth tests pass. Manual: login → navigate → refresh → still authenticated. Logout → token cleared from ALL storage locations. AI chat → auth header sent correctly. Verify async token retrieval does not cause race conditions on page load. |
| **Sign-off** | - [ ] `grep -rn "sessionStorage" resources/js/` returns ZERO direct auth token references outside tokenStorage.js. - [ ] All 7 files migrated (api.js, authService.js, aiChatService.js, sessionLifecycleService.js, auth.js store, preview.js store, app.js). - [ ] Axios interceptor handles async token retrieval correctly. - [ ] `agent:security-reviewer` passes. - [ ] All existing tests pass (`cmd:pest`). - [ ] Web login/logout/refresh cycle works identically to before. - [ ] Secure storage plugin evaluated and selected for Phase 2 native integration. |
| **Depends on** | None |

### P0-05: Phase 0 Code Review & Tech Debt Check

| Field | Detail |
|-------|--------|
| **Task** | Review all Phase 0 changes. Run tech debt check. Ensure no regressions. |
| **Tools** | `skill:tech-debt-session`, `skill:code-review`, `agent:code-reviewer`, `cmd:pest`, `cmd:pint` |
| **Files** | All Phase 0 files |
| **Tests** | `cmd:pest` — full suite passes. `cmd:pint` — no formatting issues. `mcp:trivy` — no new vulnerabilities. |
| **Sign-off** | - [ ] All tests pass. - [ ] Code formatted (pint). - [ ] No security issues (trivy). - [ ] Tech debt check clean. - [ ] Code review approved. |
| **Depends on** | P0-01 through P0-04 |

### P0-06: Deploy Phase 0 to Production

| Field | Detail |
|-------|--------|
| **Task** | Deploy analytics + token abstraction to production. Generate deploy notes. |
| **Tools** | `skill:deploy-notes`, `skill:ship`, `cmd:build` |
| **Files** | N/A (deployment task) |
| **Tests** | Visit https://fynla.org → analytics collecting. Login/logout cycle works. AI chat works. |
| **Sign-off** | - [ ] Analytics live and collecting data. - [ ] Auth flow unchanged for web users. - [ ] Deploy notes generated and saved. |
| **Depends on** | P0-05 |

### P0-07: Analytics Data Review & Go/No-Go Decision

| Field | Detail |
|-------|--------|
| **Task** | After 2 weeks of data collection, review analytics. Document findings. Make go/no-go decision for Phase 1. |
| **Tools** | Manual review |
| **Files** | `docs/plans/mobile-analytics-review.md` (CREATE) |
| **Tests** | N/A |
| **Sign-off** | - [ ] Analytics report generated with: mobile %, top modules on mobile, AI chat usage. - [ ] Go/no-go decision documented. - [ ] If go: proceed to Phase 1. If no-go: document reasons and revisit criteria. |
| **Depends on** | P0-06 + 2 weeks data |

---

## Phase 1: PWA Foundation (Weeks 3-6)

### P1-01: Install vite-plugin-pwa

| Field | Detail |
|-------|--------|
| **Task** | Install `vite-plugin-pwa` as devDependency. Configure in `vite.config.js` with manifest and Workbox caching strategies per the implementation plan. |
| **Tools** | `mcp:context7` (vite-plugin-pwa docs), `skill:feature-dev` |
| **Files** | `package.json` (MODIFY), `vite.config.js` (MODIFY) |
| **Tests** | `npm run build` succeeds. `public/build/` contains `sw.js` and `manifest.webmanifest`. |
| **Sign-off** | - [ ] Build succeeds with PWA plugin. - [ ] Service worker generated. - [ ] Manifest generated with correct theme_color (#1F2A44) and background_color (#F7F6F4). - [ ] Existing `deploy/fynla-org/build.sh` still works unchanged. |
| **Depends on** | P0-04 |

### P1-02: Generate PWA Icons

| Field | Detail |
|-------|--------|
| **Task** | Create Fynla/Fyn icons at 72, 96, 128, 144, 152, 192, 384, 512px. Both regular and maskable variants. Use Fyn springbok branding. |
| **Tools** | Manual design or AI generation |
| **Files** | `public/icons/icon-72x72.png` through `icon-512x512.png` (CREATE — 8+ files) |
| **Tests** | All icon files exist at correct dimensions. Manifest references all icons correctly. |
| **Sign-off** | - [ ] 8 icon sizes generated. - [ ] Maskable variant included (safe zone respected). - [ ] Icons use Fynla brand colours (raspberry/horizon). - [ ] Referenced correctly in manifest. |
| **Depends on** | P1-01 |

### P1-03: Service Worker Caching Strategy

| Field | Detail |
|-------|--------|
| **Task** | Configure Workbox runtime caching in vite.config.js: Precache for app shell, NetworkFirst for API dashboard/chat, CacheFirst for fonts/images/tax data, NetworkOnly for SSE streams. |
| **Tools** | `mcp:context7` (Workbox docs), `skill:feature-dev` |
| **Files** | `vite.config.js` (MODIFY — workbox.runtimeCaching array) |
| **Tests** | Build → deploy locally → DevTools Application > Service Workers shows registered SW. Go offline → cached pages still load. API responses cached per strategy. |
| **Sign-off** | - [ ] SW registered and active in Chrome DevTools. - [ ] App shell loads offline. - [ ] Dashboard data cached (NetworkFirst — shows last cached on offline). - [ ] SSE streams not cached (NetworkOnly). - [ ] Fonts/images cached (CacheFirst). |
| **Depends on** | P1-01 |

### P1-04: Web App Manifest Configuration

| Field | Detail |
|-------|--------|
| **Task** | Configure complete manifest: name, short_name, start_url (/dashboard), display (standalone), orientation (portrait), theme_color, background_color, categories, icons, shortcuts (Ask Fyn, Goals). |
| **Tools** | `mcp:playwright` (test install prompt) |
| **Files** | `vite.config.js` (MODIFY — manifest section) |
| **Tests** | Chrome DevTools → Application → Manifest shows valid manifest. Lighthouse PWA audit passes installability checks. |
| **Sign-off** | - [ ] Lighthouse PWA audit: installable. - [ ] Manifest fields correct (name, colours, icons). - [ ] Shortcuts work (Ask Fyn opens chat, Goals opens /goals). - [ ] "Install app" prompt appears on mobile Chrome. |
| **Depends on** | P1-02 |

### P1-05: Create Mobile API v1 Route Group

| Field | Detail |
|-------|--------|
| **Task** | Create `routes/api_v1.php` as parallel route file. Register in `RouteServiceProvider`. Add `IdentifyMobileClient` middleware. Do NOT modify existing `routes/api.php`. |
| **Tools** | `skill:feature-dev`, `agent:explore` (inspect current RouteServiceProvider) |
| **Files** | `routes/api_v1.php` (CREATE), `app/Providers/RouteServiceProvider.php` (MODIFY), `app/Http/Middleware/IdentifyMobileClient.php` (CREATE), `app/Http/Kernel.php` (MODIFY) |
| **Tests** | `php artisan route:list --path=api/v1` shows registered routes. Existing `/api/*` routes unchanged (`php artisan route:list --path=api/` count matches before). |
| **Sign-off** | - [ ] `/api/v1/` prefix registered. - [ ] `IdentifyMobileClient` sets `is_mobile` attribute on request. - [ ] Existing API routes unchanged (count before == count after). - [ ] `cmd:pest` — all existing tests pass. |
| **Depends on** | None |

### P1-06: Create ETag Middleware

| Field | Detail |
|-------|--------|
| **Task** | Create `ETagResponse` middleware. Computes md5 of response body, sets ETag header, returns 304 if `If-None-Match` matches. Register as middleware alias. |
| **Tools** | `skill:feature-dev` |
| **Files** | `app/Http/Middleware/ETagResponse.php` (CREATE), `app/Http/Kernel.php` (MODIFY) |
| **Tests** | Hit endpoint → response has `ETag` header. Second request with `If-None-Match: {etag}` → returns 304. Request with different `If-None-Match` → returns 200. |
| **Sign-off** | - [ ] ETag header present on GET responses. - [ ] 304 returned for matching ETag. - [ ] 200 returned for changed content. - [ ] Unit test written and passing. |
| **Depends on** | P1-05 |

### P1-07: Create Mobile Rate Limiters

| Field | Detail |
|-------|--------|
| **Task** | Add rate limiters in `RouteServiceProvider`: `mobile-dashboard` (30/min/user), `ai-chat` (20/min/user), `device-registration` (5/min/user). |
| **Tools** | `skill:feature-dev` |
| **Files** | `app/Providers/RouteServiceProvider.php` (MODIFY) |
| **Tests** | Hit endpoint 31 times in 1 minute → 429 on 31st. Rate limit header shows remaining count. |
| **Sign-off** | - [ ] Rate limiters registered and functional. - [ ] Response headers include `X-RateLimit-Remaining`. - [ ] 429 returned when exceeded. |
| **Depends on** | P1-05 |

### P1-08: Build MobileDashboardAggregator Service

| Field | Detail |
|-------|--------|
| **Task** | Create service that extends existing `DashboardAggregator` to return all module summaries, net worth, alerts, and Fyn insight in a single response. 5-minute cache per user. |
| **Tools** | `skill:feature-dev`, `agent:explore` (inspect existing DashboardAggregator), `agent:db-optimizer` (verify cache strategy) |
| **Files** | `app/Services/Mobile/MobileDashboardAggregator.php` (CREATE) |
| **Tests** | Unit test: mock dependencies → verify response shape matches plan spec. Integration test: seed data → call aggregator → verify all 6 modules present. Performance: response cached (second call <50ms). |
| **Sign-off** | - [ ] Response matches JSON shape from implementation plan. - [ ] All 6 module summaries included. - [ ] Net worth calculated correctly (including joint assets via `WHERE user_id = ? OR joint_owner_id = ?` with correct ownership percentages). - [ ] Alerts included. - [ ] 5-minute cache working (verified via Cache::has). - [ ] Joint couple personas (young_family, peak_earners, retired_couple) show correct per-user net worth shares. - [ ] Unit tests written and passing. |
| **Depends on** | P1-05 |

### P1-09: Build MobileDashboardController

| Field | Detail |
|-------|--------|
| **Task** | Create controller with `index()` method returning aggregated dashboard data. Apply `etag` and `mobile-dashboard` rate limit middleware. |
| **Tools** | `skill:feature-dev` |
| **Files** | `app/Http/Controllers/Api/V1/Mobile/MobileDashboardController.php` (CREATE), `routes/api_v1.php` (MODIFY) |
| **Tests** | `GET /api/v1/mobile/dashboard` with valid auth → 200 with correct JSON. Without auth → 401. ETag header present. Cacheable. |
| **Sign-off** | - [ ] Endpoint returns correct JSON structure. - [ ] Auth required (401 without token). - [ ] ETag header present. - [ ] Rate limited. - [ ] Feature test written and passing. |
| **Depends on** | P1-06, P1-07, P1-08 |

### P1-09b: Build ModuleSummaryController & InsightsController (REVIEW ADDITION)

> **Added by Voice of Reason + Devil's Advocate:** The implementation plan lists these controllers in the backend file inventory but no task created them. Module summary screens (P2-21) and daily insights (P2-19) need backend endpoints.

| Field | Detail |
|-------|--------|
| **Task** | Create `ModuleSummaryController` with endpoints for individual module summary data (protection, savings, investment, retirement, estate, goals, tax). Create `InsightsController` for daily Fyn insights endpoint. Both serve the mobile frontend screens built in Phase 2. |
| **Tools** | `skill:feature-dev`, `agent:tax-compliance` (for tax endpoint) |
| **Files** | `app/Http/Controllers/Api/V1/Mobile/ModuleSummaryController.php` (CREATE), `app/Http/Controllers/Api/V1/Mobile/InsightsController.php` (CREATE), `routes/api_v1.php` (MODIFY) |
| **Tests** | `GET /api/v1/mobile/modules/{module}` returns correct summary per module. Auth required. ETag caching. Rate limited. All 7 module types return valid data for each preview persona. |
| **Sign-off** | - [ ] 7 module summary endpoints functional. - [ ] Insights endpoint returns daily insight. - [ ] Auth required on all endpoints. - [ ] No hardcoded tax values (`agent:tax-compliance`). - [ ] Feature tests written and passing. - [ ] No scores in responses (rule #13). |
| **Depends on** | P1-05, P1-06, P1-07 |

### P1-10: Make AiChatPanel Responsive (Mobile Full-Screen)

| Field | Detail |
|-------|--------|
| **Task** | Add mobile layout mode to `AiChatPanel.vue`. Detect viewport < 768px. Mobile: full-screen fixed overlay (`inset-0`), simplified header, keyboard-aware input. Keep existing desktop behaviour unchanged. |
| **Tools** | `skill:feature-dev`, `agent:premium-ui`, `mcp:playwright` (test on mobile viewport) |
| **Files** | `resources/js/components/Shared/AiChatPanel.vue` (MODIFY) |
| **Tests** | Desktop (>768px): panel unchanged (420px popup). Mobile (<768px): full-screen overlay. Type message → input stays above keyboard. Send message → response streams correctly. Close button → panel closes. |
| **Sign-off** | - [ ] Desktop layout unchanged. - [ ] Mobile: full-screen, `inset-0`. - [ ] Keyboard pushes input up (not hidden). - [ ] SSE streaming works in both modes. - [ ] Quick reply chips show below responses. - [ ] All 7 preview personas tested. - [ ] `mcp:playwright` screenshot at 375px and 1024px viewports. |
| **Depends on** | P0-04 |

### P1-11: Create QuickReplyChips Component

| Field | Detail |
|-------|--------|
| **Task** | Create horizontal scroll row of suggested reply chips. Rendered after each Fyn response. Tapping sends chip text as user message. Styling per design spec. |
| **Tools** | `agent:premium-ui` |
| **Files** | `resources/js/mobile/QuickReplyChips.vue` (CREATE) |
| **Tests** | Fyn responds → chips appear below response. Tap chip → text sent as user message. Chips disappear after selection. Horizontal scroll works for many chips. |
| **Sign-off** | - [ ] Chips render after AI responses. - [ ] Tap sends message. - [ ] Horizontal scrollable. - [ ] Styling matches design spec (`bg-white border border-light-gray rounded-full`). |
| **Depends on** | P1-10 |

### P1-12: Create OfflineBanner Component

| Field | Detail |
|-------|--------|
| **Task** | Create offline indicator using `navigator.onLine` + event listeners. Shows `bg-savannah-200` banner with "Offline — showing last updated data" + timestamp. |
| **Tools** | `skill:feature-dev` |
| **Files** | `resources/js/mobile/OfflineBanner.vue` (CREATE), `resources/js/layouts/AppLayout.vue` (MODIFY — include banner) |
| **Tests** | Go offline (DevTools) → banner appears. Go online → banner disappears. Timestamp shows last successful fetch. |
| **Sign-off** | - [ ] Banner shows when offline. - [ ] Banner hides when online. - [ ] Timestamp accurate. - [ ] Does not interfere with existing layout. - [ ] Accessible (screen reader announces state change). |
| **Depends on** | None |

### P1-13: Phase 1 Integration Testing

| Field | Detail |
|-------|--------|
| **Task** | End-to-end testing of all Phase 1 features together. Test PWA install, service worker, responsive chat, dashboard endpoint, offline behaviour. |
| **Tools** | `mcp:playwright` (automated browser tests), `cmd:pest`, `cmd:seed` |
| **Files** | `tests/Feature/Mobile/MobileDashboardTest.php` (CREATE), `tests/Feature/Mobile/ETagMiddlewareTest.php` (CREATE) |
| **Tests** | Full test matrix below. |
| **Sign-off** | See Phase 1 Test Matrix |

#### Phase 1 Test Matrix

| # | Test | Tool | Pass Criteria |
|---|------|------|---------------|
| 1 | Lighthouse PWA audit | `mcp:playwright` | Installable, SW registered, manifest valid |
| 2 | Install PWA on iOS Safari | Manual | Add to Home Screen works, standalone mode launches |
| 3 | Install PWA on Android Chrome | Manual | Install prompt appears, standalone mode launches |
| 4 | Dashboard endpoint — all personas | `cmd:pest` | 6 preview personas return correct module data |
| 5 | Dashboard endpoint — ETag caching | `cmd:pest` | 304 on unchanged, 200 on changed |
| 6 | Dashboard endpoint — rate limiting | `cmd:pest` | 429 after 30 requests/min |
| 7 | AI chat — mobile full-screen | `mcp:playwright` (375px viewport) | Full-screen overlay, messages stream |
| 8 | AI chat — desktop unchanged | `mcp:playwright` (1024px viewport) | 420px popup, same as before |
| 9 | AI chat — quick replies | `mcp:playwright` | Chips appear, tap sends message |
| 10 | Offline — dashboard cached | Manual | Disconnect → cached dashboard shows |
| 11 | Offline — banner shows | Manual | "Offline" banner with timestamp |
| 12 | Offline — reconnect | Manual | Banner disappears, data refreshes |
| 13 | Service worker — cache strategies | DevTools | Verify cache names and strategies match plan |
| 14 | Existing web app unchanged | `cmd:pest` (full suite) | All 940+ tests pass |
| 15 | Build succeeds | `cmd:build` | No errors, output includes SW + manifest |
| 16 | Security scan | `mcp:trivy` | No new HIGH/CRITICAL vulnerabilities |

### P1-14: Phase 1 Code Review

| Field | Detail |
|-------|--------|
| **Task** | Full code review of all Phase 1 changes. |
| **Tools** | `skill:code-review`, `agent:code-reviewer`, `skill:tech-debt-session`, `agent:security-reviewer` |
| **Files** | All Phase 1 files |
| **Sign-off** | - [ ] Code review approved (no blocking issues). - [ ] Security review passed. - [ ] Tech debt check clean. - [ ] PSR-12 formatting (`cmd:pint`). |
| **Depends on** | P1-01 through P1-13 |

### P1-15: Deploy Phase 1 & Generate Deploy Notes

| Field | Detail |
|-------|--------|
| **Task** | Deploy PWA to production. Generate deploy notes. Monitor analytics for PWA install events. |
| **Tools** | `skill:deploy-notes`, `skill:ship`, `cmd:build` |
| **Files** | N/A (deployment) |
| **Sign-off** | - [ ] PWA installable on production (fynla.org). - [ ] Service worker active. - [ ] Dashboard endpoint accessible at `/api/v1/mobile/dashboard`. - [ ] AI chat responsive on mobile. - [ ] Deploy notes generated. |
| **Depends on** | P1-14 |

### P1-16: Phase 1 Archive & Metrics Snapshot

| Field | Detail |
|-------|--------|
| **Task** | Archive Phase 1 completion. Record metrics baseline. Document any deviations from plan. |
| **Tools** | Manual |
| **Files** | `docs/plans/phase1-completion.md` (CREATE) |
| **Sign-off** | - [ ] PWA install rate recorded. - [ ] Mobile AI chat usage recorded. - [ ] Dashboard load time recorded. - [ ] Known issues documented. - [ ] Phase 1 → Phase 2 gate decision documented. |
| **Depends on** | P1-15 |

---

## Phase 2: Capacitor + Core Mobile Experience (Weeks 7-14)

### Section A: Capacitor Foundation (Week 7-8)

#### P2-01: Apple Developer Enrollment

| Field | Detail |
|-------|--------|
| **Task** | Enroll in Apple Developer Program ($99/year). Set up App ID `org.fynla.app`. Configure provisioning profiles. |
| **Tools** | Manual (Apple Developer portal) |
| **Files** | N/A (external) |
| **Sign-off** | - [ ] Apple Developer account active. - [ ] App ID `org.fynla.app` registered. - [ ] Development provisioning profile created. - [ ] Distribution provisioning profile created. |
| **Depends on** | None (start ASAP — can run parallel with Phase 1) |

#### P2-02: Google Play Developer Registration

| Field | Detail |
|-------|--------|
| **Task** | Register Google Play Developer account (£20 one-time). Set up app listing placeholder. |
| **Tools** | Manual (Google Play Console) |
| **Files** | N/A (external) |
| **Sign-off** | - [ ] Google Play Developer account active. - [ ] App listing placeholder created. |
| **Depends on** | None |

#### P2-03: Install Capacitor Core

| Field | Detail |
|-------|--------|
| **Task** | Install `@capacitor/core`, `@capacitor/cli`. Create `capacitor.config.ts`. Initialise iOS and Android projects. Update `.gitignore` for `ios/` and `android/`. |
| **Tools** | `mcp:context7` (Capacitor 6 docs), `skill:feature-dev` |
| **Files** | `package.json` (MODIFY), `capacitor.config.ts` (CREATE), `ios/` (AUTO-GENERATED), `android/` (AUTO-GENERATED), `.gitignore` (MODIFY) |
| **Tests** | `npx cap sync ios` succeeds. `npx cap sync android` succeeds. Open in Xcode → builds. Open in Android Studio → builds. |
| **Sign-off** | - [ ] Capacitor config matches plan spec (appId, webDir, plugins). - [ ] iOS project builds in Xcode. - [ ] Android project builds in Android Studio. - [ ] `.gitignore` excludes `ios/App/Pods/` and `android/.gradle/`. |
| **Depends on** | P1-15 |

#### P2-04: Install Capacitor Native Plugins

| Field | Detail |
|-------|--------|
| **Task** | Install all 12 Capacitor plugins from the plan: biometrics, push, keyboard, app, preferences, network, browser, status-bar, haptics, splash-screen, device, local-notifications. |
| **Tools** | `mcp:context7` (Capacitor plugin docs) |
| **Files** | `package.json` (MODIFY), `ios/App/Podfile` (AUTO-UPDATED), `android/app/build.gradle` (AUTO-UPDATED) |
| **Tests** | `npx cap sync` succeeds for both platforms. No plugin conflicts. Build succeeds on both platforms. |
| **Sign-off** | - [ ] All 12 plugins installed. - [ ] iOS build succeeds after pod install. - [ ] Android build succeeds after gradle sync. - [ ] No version conflicts. |
| **Depends on** | P2-03 |

#### P2-05: Create Platform Detection Utility

| Field | Detail |
|-------|--------|
| **Task** | Create `platform.js` utility with `isNative()`, `isIOS()`, `isAndroid()`, `isWeb()`, `isMobileViewport()`, `canUseBiometrics()`, `canUsePushNotifications()`, `canUseHaptics()`. |
| **Tools** | `skill:feature-dev` |
| **Files** | `resources/js/utils/platform.js` (CREATE) |
| **Tests** | Unit tests: mock Capacitor → verify each method returns correct value for web/iOS/Android. |
| **Sign-off** | - [ ] All platform methods work correctly. - [ ] Unit tests passing. - [ ] Web fallbacks safe (no Capacitor errors in browser). |
| **Depends on** | P2-03 |

#### P2-06: Token Storage Capacitor Integration (REVISED — Secure Storage)

> **REVIEW NOTE (Devil's Advocate — CRITICAL):** `@capacitor/preferences` uses `UserDefaults` (iOS) / `SharedPreferences` (Android) — these are NOT encrypted and are readable on jailbroken/rooted devices. For a financial app, auth tokens MUST use iOS Keychain / Android Keystore. Use `@capgo/capacitor-native-biometric` (stores credentials in Secure Enclave via biometric association) or install `@nicepayments/capacitor-native-settings` / `@capacitor-community/secure-storage-plugin` for direct Keychain/Keystore access.

| Field | Detail |
|-------|--------|
| **Task** | Update `tokenStorage.js` (from P0-04) to use SECURE native storage on Capacitor platforms. Evaluate and install a secure storage plugin that uses iOS Keychain and Android Keystore (NOT `@capacitor/preferences`). Options: `@capacitor-community/secure-storage-plugin`, `@nicepayments/capacitor-native-settings`, or use `@capgo/capacitor-native-biometric` credential storage. Token must be encrypted at rest and inaccessible to other apps even on jailbroken devices. |
| **Tools** | `skill:feature-dev`, `agent:security-reviewer`, `mcp:context7` (secure storage plugin docs) |
| **Files** | `resources/js/services/tokenStorage.js` (MODIFY), `package.json` (MODIFY — add secure storage plugin) |
| **Tests** | On iOS Simulator: login → token stored in Keychain (verify via Keychain Access or security tool). Kill app → reopen → token still present. Logout → token cleared from Keychain. On web: behaviour unchanged (sessionStorage). On jailbroken test: token not accessible via file system browsing. |
| **Sign-off** | - [ ] Token stored in iOS Keychain (NOT UserDefaults). - [ ] Token stored in Android Keystore (NOT SharedPreferences). - [ ] Token persists across app kills on both platforms. - [ ] Token cleared on logout from secure storage. - [ ] Web behaviour unchanged. - [ ] `agent:security-reviewer` passes with secure storage verification. |
| **Depends on** | P0-04, P2-04 |

#### P2-07: Mobile Build Scripts

| Field | Detail |
|-------|--------|
| **Task** | Create `deploy/mobile/build-ios.sh` and `deploy/mobile/build-android.sh` with correct env vars (`VITE_BASE_PATH=/`, `VITE_API_BASE_URL`). |
| **Tools** | `skill:feature-dev` |
| **Files** | `deploy/mobile/build-ios.sh` (CREATE), `deploy/mobile/build-android.sh` (CREATE) |
| **Tests** | `./deploy/mobile/build-ios.sh` succeeds → opens Xcode. `./deploy/mobile/build-android.sh` succeeds → opens Android Studio. |
| **Sign-off** | - [ ] iOS build script works end-to-end. - [ ] Android build script works end-to-end. - [ ] Existing web build (`deploy/fynla-org/build.sh`) still works unchanged. - [ ] VITE_BASE_PATH correctly set to `/` (not `/build/`). |
| **Depends on** | P2-03 |

#### P2-08: CORS & Security Headers Update

| Field | Detail |
|-------|--------|
| **Task** | Add Capacitor origins to CORS allowed_origins. Add to CSP connect-src in SecurityHeaders. Do NOT add to Sanctum stateful domains. |
| **Tools** | `skill:feature-dev`, `agent:security-reviewer` |
| **Files** | `config/cors.php` (MODIFY), `app/Http/Middleware/SecurityHeaders.php` (MODIFY) |
| **Tests** | Capacitor app makes API request → no CORS error. Web app → still works (no regression). Request from unknown origin → blocked. |
| **Sign-off** | - [ ] Capacitor origin allowed in CORS. - [ ] CSP allows Capacitor connections. - [ ] NOT in Sanctum stateful domains (stateless token auth). - [ ] `Permissions-Policy` updated to allow microphone for voice input (P2-25). - [ ] `CheckSubscription.php` modified to exclude mobile device registration routes. - [ ] `AiContextBuilder.php` modified to add mobile screen name mappings. - [ ] `agent:security-reviewer` passes. - [ ] Web app unchanged. |
| **Depends on** | P2-03 |

#### P2-08b: API Backward Compatibility & App Version Check (REVIEW ADDITION)

> **Added by Devil's Advocate (HIGH):** Mobile users may stay on old app versions for weeks. A breaking web API change will crash mobile clients. Also, there is no way to force users to update when a critical security patch ships.

| Field | Detail |
|-------|--------|
| **Task** | Add `X-App-Version` and `X-Platform` headers to all mobile API requests. Create `VerifyMobileSignature` middleware to validate mobile client requests. Create `CheckMinimumAppVersion` middleware that returns 426 (Upgrade Required) with a force-update payload when the app version is below the minimum. Add minimum version config to `PlanConfigurationSeeder` or `.env`. |
| **Tools** | `skill:feature-dev`, `agent:security-reviewer` |
| **Files** | `app/Http/Middleware/VerifyMobileSignature.php` (CREATE), `app/Http/Middleware/CheckMinimumAppVersion.php` (CREATE), `app/Http/Kernel.php` (MODIFY), `config/mobile.php` (CREATE — min version, force update config), `.env.example` (MODIFY) |
| **Tests** | Request with valid app version → passes. Request with old version below minimum → 426 response with update URL. Request without version header (web) → passes (web exempt). |
| **Sign-off** | - [ ] Version check middleware functional. - [ ] 426 response includes App Store / Play Store URLs. - [ ] Web requests exempt (no version header required). - [ ] `agent:security-reviewer` passes. - [ ] Feature tests written. |
| **Depends on** | P2-03 |

#### P2-09: Mobile Auth Flow — Backend

| Field | Detail |
|-------|--------|
| **Task** | Add `platform` and `device_id` params to login flow. Create `POST /api/v1/auth/refresh-token` endpoint. Mobile tokens get 30-day expiry (web stays 8 hours). |
| **Tools** | `skill:feature-dev`, `agent:security-reviewer` |
| **Files** | `app/Http/Controllers/Api/AuthController.php` (MODIFY), `app/Http/Requests/V1/MobileLoginRequest.php` (CREATE), `routes/api_v1.php` (MODIFY) |
| **Tests** | Login with `platform: "mobile"` → token with 30-day expiry. Login without platform → token with 8-hour expiry (unchanged). Refresh valid token → new token issued, old revoked. Refresh expired token → 401. |
| **Sign-off** | - [ ] Mobile tokens: 30-day expiry. - [ ] Web tokens: 8-hour expiry (unchanged). - [ ] Refresh endpoint works. - [ ] Feature tests written and passing. - [ ] `agent:security-reviewer` passes. |
| **Depends on** | P1-05 |

#### P2-09b: Mobile MFA/TOTP Challenge Screen (REVIEW ADDITION — CRITICAL)

> **Added by Devil's Advocate (CRITICAL):** The current auth flow supports MFA via TOTP. The mobile auth flow (P2-09) skips from login to verification code without addressing users who have MFA/TOTP enabled. Any user with MFA enabled will be unable to log in on mobile without this.

| Field | Detail |
|-------|--------|
| **Task** | Create mobile MFA challenge screen. After email/password validation, if user has TOTP MFA enabled, display a TOTP code input screen. Handle: authenticator app codes, backup recovery codes, "remember this device for 30 days" option. Integrate with existing MFA verification in `AuthController`. |
| **Tools** | `skill:feature-dev`, `agent:security-reviewer` |
| **Files** | `resources/js/mobile/MfaChallengeScreen.vue` (CREATE), `resources/js/services/authService.js` (MODIFY — add MFA step to mobile flow), `routes/api_v1.php` (MODIFY — MFA verify endpoint if not already accessible) |
| **Tests** | User with MFA: login → MFA prompt → enter TOTP → authenticated. Wrong TOTP → error message, retry. Backup code → works. "Remember device" → next login skips MFA on same device. User without MFA → flow unchanged. |
| **Sign-off** | - [ ] MFA challenge screen renders. - [ ] Authenticator app codes accepted. - [ ] Backup/recovery codes accepted. - [ ] "Remember device" stores device_id server-side. - [ ] Rate limited (5 attempts/min). - [ ] `agent:security-reviewer` passes. |
| **Depends on** | P2-09 |

#### P2-09c: Token Revocation & Remote Session Invalidation (REVIEW ADDITION — CRITICAL)

> **Added by Devil's Advocate (CRITICAL):** 30-day mobile tokens with no server-side revocation means a stolen device/compromised account cannot be locked out. Need a revocation mechanism and "sign out all devices" feature.

| Field | Detail |
|-------|--------|
| **Task** | Create token revocation endpoint (`POST /api/v1/auth/revoke-all-devices`). Add a `token_revoked_at` column or revocation list check. When a user changes their password, revoke all mobile tokens. Add "Sign out all devices" option in mobile settings. On each API request, validate token against revocation list (lightweight Redis/cache check, not DB query per request). |
| **Tools** | `skill:feature-dev`, `agent:security-reviewer`, `agent:db-optimizer` |
| **Files** | `app/Http/Controllers/Api/AuthController.php` (MODIFY — add revoke-all endpoint), `database/migrations/YYYY_add_revoked_at_to_personal_access_tokens.php` (CREATE), `app/Http/Middleware/CheckTokenRevocation.php` (CREATE), `routes/api_v1.php` (MODIFY), `resources/js/mobile/SettingsList.vue` (MODIFY — add "Sign out all devices") |
| **Tests** | Revoke all → existing mobile tokens rejected (401). Password change → all mobile tokens revoked. "Sign out all devices" → confirmation → all sessions ended. Revocation check is cached (not DB per request). |
| **Sign-off** | - [ ] Revoke-all endpoint works. - [ ] Password change triggers revocation. - [ ] Revocation check is performant (<5ms, cached). - [ ] Mobile app handles 401 gracefully (redirect to login). - [ ] "Sign out all devices" UI accessible from settings. - [ ] `agent:security-reviewer` passes. |
| **Depends on** | P2-09 |

#### P2-10: Biometric Auth — Frontend

| Field | Detail |
|-------|--------|
| **Task** | Create `BiometricPrompt.vue`. On first login success, prompt "Enable Face ID / Touch ID?". On subsequent launches, biometric check → retrieve stored token. Fallback to full login on 3 failures. |
| **Tools** | `mcp:context7` (capacitor-native-biometric docs), `skill:feature-dev` |
| **Files** | `resources/js/mobile/BiometricPrompt.vue` (CREATE), `resources/js/services/authService.js` (MODIFY) |
| **Tests** | iOS Simulator: biometric prompt appears on launch. Authenticate → app opens. Cancel 3 times → full login screen. Web: no biometric prompt (graceful degradation). |
| **Sign-off** | - [ ] Biometric enrollment prompt after first login. - [ ] Biometric unlock on subsequent launches. - [ ] Fallback after 3 failures. - [ ] Web: no biometric (degradation). - [ ] Token validated server-side after biometric unlock. |
| **Depends on** | P2-06, P2-09 |

#### P2-11: Section A Integration Test & Sign-off

| Field | Detail |
|-------|--------|
| **Task** | Full test of Capacitor foundation: build on both platforms, auth flow, token persistence, biometrics. |
| **Tools** | `cmd:pest`, `mcp:playwright`, manual device testing |
| **Tests** | iOS: build → install on Simulator → login → biometric → navigate → kill → reopen → biometric → still authed. Android: same flow. Web: all existing tests pass. |
| **Sign-off** | - [ ] iOS Simulator: full auth flow works. - [ ] Android Emulator: full auth flow works. - [ ] Web: zero regressions (`cmd:pest` full suite). - [ ] `agent:security-reviewer` passes on auth changes. |
| **Depends on** | P2-01 through P2-10 |

### Section B: Mobile UI Shell (Week 8-9)

#### P2-12: Create Mobile Entry Point & Router

| Field | Detail |
|-------|--------|
| **Task** | Create `resources/js/mobile.js` as separate Vite entry point. Create mobile-specific router with tab-based navigation. Update `vite.config.js` with multiple entry points. |
| **Tools** | `skill:feature-dev`, `mcp:context7` (Vue Router docs) |
| **Files** | `resources/js/mobile.js` (CREATE), `resources/js/mobile/router.js` (CREATE), `resources/js/mobile/MobileApp.vue` (CREATE), `vite.config.js` (MODIFY) |
| **Tests** | Web entry point unchanged. Mobile entry point loads MobileApp with bottom tabs. Route navigation works between tabs. |
| **Sign-off** | - [ ] Separate mobile bundle generated. - [ ] Mobile router with 5 tab routes. - [ ] Tab state preserved on switch. - [ ] Web bundle unaffected (no size increase). |
| **Depends on** | P2-03 |

#### P2-13: Build MobileTabBar Component

| Field | Detail |
|-------|--------|
| **Task** | Create bottom tab bar with 5 tabs (Home, Fyn, Learn, Goals, More). Active/inactive states. Badge support. Safe area handling. Matches design spec exactly. |
| **Tools** | `agent:premium-ui`, `skill:feature-dev` |
| **Files** | `resources/js/mobile/MobileTabBar.vue` (CREATE) |
| **Tests** | 5 tabs visible. Tap each → navigates to correct route. Active tab: raspberry-500. Inactive: neutral-500. Badge counts update. Safe area respected on iPhone X+. |
| **Sign-off** | - [ ] 5 tabs with correct icons and labels. - [ ] Active state: raspberry-500. - [ ] Badge rendering (dot and numeric). - [ ] 83pt height with safe area. - [ ] Tab state preserved on switch. |
| **Depends on** | P2-12 |

#### P2-14: Build MobileHeader Component

| Field | Detail |
|-------|--------|
| **Task** | Create in-tab header: 44pt, back chevron, centred title, right action icons. Collapses on scroll (optional). |
| **Tools** | `agent:premium-ui` |
| **Files** | `resources/js/mobile/MobileHeader.vue` (CREATE) |
| **Tests** | Title displays centred. Back button navigates back. Right action slot works. 44pt touch targets. |
| **Sign-off** | - [ ] 44pt height. - [ ] Back navigation works. - [ ] Title centred. - [ ] Action slot functional. - [ ] Accessible (VoiceOver reads title). |
| **Depends on** | P2-12 |

#### P2-15: Build MobileLayout Component

| Field | Detail |
|-------|--------|
| **Task** | Create mobile layout wrapper: MobileHeader top, content area (scrollable), MobileTabBar bottom. Handles safe areas, keyboard avoidance, pull-to-refresh slot. |
| **Tools** | `skill:feature-dev`, `agent:premium-ui` |
| **Files** | `resources/js/layouts/MobileLayout.vue` (CREATE) |
| **Tests** | Content scrolls between header and tab bar. Safe areas respected. Keyboard pushes content up. Pull-to-refresh triggers callback. |
| **Sign-off** | - [ ] Header + content + tab bar layout correct. - [ ] Safe areas handled (iPhone notch, home indicator). - [ ] Keyboard avoidance working. - [ ] `PullToRefresh` slot functional. |
| **Depends on** | P2-13, P2-14 |

#### P2-16: Build PullToRefresh Component

| Field | Detail |
|-------|--------|
| **Task** | Create pull-to-refresh wrapper with raspberry-500 spinner. Emits `refresh` event. |
| **Tools** | `agent:premium-ui` |
| **Files** | `resources/js/mobile/PullToRefresh.vue` (CREATE) |
| **Tests** | Pull down → spinner appears in raspberry-500. Release → `refresh` event emitted. Spinner dismisses after callback completes. |
| **Sign-off** | - [ ] Raspberry-500 spinner. - [ ] Emits refresh event. - [ ] Works within MobileLayout. |
| **Depends on** | None |

### Section C: Dashboard & Module Summaries (Week 9-10)

#### P2-17: Build MobileDashboard Screen

| Field | Detail |
|-------|--------|
| **Task** | Create Home tab root screen: greeting, net worth hero card, Fyn insight card, alerts panel, module summary grid (2-col), journey progress. Fetches from `/api/v1/mobile/dashboard`. |
| **Tools** | `skill:feature-dev`, `agent:premium-ui` |
| **Files** | `resources/js/mobile/MobileDashboard.vue` (CREATE) |
| **Tests** | All 6 module summaries render. Net worth displays correctly. Fyn insight shows. Alerts panel conditional. Skeleton loading on fetch. Pull-to-refresh works. |
| **Sign-off** | - [ ] All 6 modules shown. - [ ] Net worth hero card with sparkline. - [ ] Fyn insight card (horizon-500 bg). - [ ] Alerts panel (violet-50 bg). - [ ] 2-column module grid. - [ ] Skeleton loading state. - [ ] Offline: cached data with grey tint. - [ ] Empty state: Fyn avatar + onboarding prompt. - [ ] Tested with all 7 preview personas. |
| **Depends on** | P1-09, P2-15 |

#### P2-18: Build MobileNetWorthCard Component

| Field | Detail |
|-------|--------|
| **Task** | Net worth headline card with value, change indicator (+/- with colour), 90-day sparkline. |
| **Tools** | `agent:premium-ui` |
| **Files** | `resources/js/mobile/MobileNetWorthCard.vue` (CREATE), `resources/js/mobile/charts/NetWorthSparkline.vue` (CREATE) |
| **Tests** | Value formatted with currencyMixin. Positive change: spring-500. Negative: raspberry-500. Sparkline renders 90-day trend. Tap navigates to Net Worth module. |
| **Sign-off** | - [ ] Currency formatting correct (currencyMixin). - [ ] Change colour coding. - [ ] Sparkline renders. - [ ] Tap navigation works. |
| **Depends on** | P2-17 |

#### P2-19: Build FynInsightCard Component

| Field | Detail |
|-------|--------|
| **Task** | Daily AI insight card: horizon-500 background, Fyn avatar, 1-sentence insight, tap → opens chat with context. |
| **Tools** | `agent:premium-ui` |
| **Files** | `resources/js/mobile/FynInsightCard.vue` (CREATE) |
| **Tests** | Insight text displays. Fyn avatar renders. Tap opens AI chat with context. Handles missing insight gracefully. |
| **Sign-off** | - [ ] Horizon-500 background with white text. - [ ] Fyn avatar 32x32pt. - [ ] Tap → chat opens with context. |
| **Depends on** | P2-17 |

#### P2-19b: Build DailyInsightGenerator Service (REVIEW ADDITION)

> **Added by Voice of Reason + Devil's Advocate (HIGH):** The implementation plan lists `DailyInsightGenerator.php` in the backend file inventory but no task created it. The `FynInsightCard` (P2-19) and `SendDailyInsightNotifications` command (P2-32) both need this service. Must batch insights for cohorts (by module focus) to control AI API costs per the cost model.

| Field | Detail |
|-------|--------|
| **Task** | Create `DailyInsightGenerator` service that generates daily Fyn insights. Must batch by user cohort (not one AI call per user per day). Generates insights based on: user's active modules, recent financial changes, upcoming events (renewals, deadlines), goals progress. Caches generated insights for 24 hours. |
| **Tools** | `skill:feature-dev`, `agent:explore` (inspect existing AI service patterns) |
| **Files** | `app/Services/Mobile/DailyInsightGenerator.php` (CREATE) |
| **Tests** | Unit test: generate insight for each persona type → returns meaningful 1-sentence text. Integration: batch generation for 6 preview personas completes in <30 seconds. Cache: second call returns cached insight (no AI call). |
| **Sign-off** | - [ ] Generates personalised insights per user. - [ ] Batched by cohort (not 1 AI call per user). - [ ] 24-hour cache per user. - [ ] Graceful fallback if AI unavailable ("Review your financial plan today"). - [ ] Unit tests passing. - [ ] No hardcoded financial advice in fallbacks. |
| **Depends on** | P1-09b |

#### P2-20: Build ModuleSummaryCard Component

| Field | Detail |
|-------|--------|
| **Task** | Reusable compact card for dashboard grid: icon, module name, key metric, coloured status border (spring/violet/raspberry). |
| **Tools** | `agent:premium-ui` |
| **Files** | `resources/js/mobile/ModuleSummaryCard.vue` (CREATE) |
| **Tests** | Renders for each module type. Status border correct colour. Tap navigates to module summary. |
| **Sign-off** | - [ ] Works for all 7 modules. - [ ] 4px left border with status colour. - [ ] Touch target ≥44pt. - [ ] Accessible labels. |
| **Depends on** | None |

#### P2-21: Build 7 Module Summary Screens

| Field | Detail |
|-------|--------|
| **Task** | Create ProtectionSummary, SavingsSummary, InvestmentSummary, RetirementSummary, EstateSummary, GoalsSummary, TaxSummary. Shared template: hero metric, Fyn's take, key metrics grid, action items, learn card, deep link button. |
| **Tools** | `skill:feature-dev`, `agent:premium-ui`, `agent:tax-compliance` (for tax summary) |
| **Files** | `resources/js/mobile/summaries/ProtectionSummary.vue` (CREATE), `SavingsSummary.vue`, `InvestmentSummary.vue`, `RetirementSummary.vue`, `EstateSummary.vue`, `GoalsSummary.vue`, `TaxSummary.vue` (7 files CREATE) |
| **Tests** | Each screen: data loads, hero metric correct, Fyn's take renders, "View full detail" button works. Test with all 7 preview personas (different data scenarios). |
| **Sign-off** | - [ ] All 7 summaries implemented. - [ ] Hero metrics match module (coverage gap, emergency months, portfolio value, projected income, IHT estimate, goals on track, effective tax rate). - [ ] Fyn's take card (horizon-500 bg). - [ ] "View full detail" deep link works. - [ ] "Learn about {module}" links. - [ ] Tested with all 7 personas. - [ ] No hardcoded tax values (`agent:tax-compliance`). - [ ] No scores in UI (rule #13). |
| **Depends on** | P2-17, P2-20 |

#### P2-21b: Build AllocationDonut Chart Component (REVIEW ADDITION)

> **Added by Voice of Reason:** Listed in the component inventory (`resources/js/mobile/charts/AllocationDonut.vue`) but no task created it. Needed for the Investment Summary (P2-21) to show portfolio allocation.

| Field | Detail |
|-------|--------|
| **Task** | Create donut chart component for portfolio allocation display. Uses `designSystem.js` `CHART_COLORS`. Touch to show segment detail. Accessible labels for each segment. |
| **Tools** | `agent:premium-ui` |
| **Files** | `resources/js/mobile/charts/AllocationDonut.vue` (CREATE) |
| **Tests** | Renders with sample allocation data. Segments use correct design system colours. Touch segment → shows label + percentage. VoiceOver reads segment values. |
| **Sign-off** | - [ ] Uses `CHART_COLORS` from designSystem.js. - [ ] Touch interaction shows segment detail. - [ ] Accessible (screen reader announces each segment). - [ ] Works with 1-8 segments. |
| **Depends on** | None |

#### P2-22: Build DeepLinkButton Component

| Field | Detail |
|-------|--------|
| **Task** | "View full detail" button: opens web URL via Capacitor Browser plugin (SFSafariViewController / Custom Tab). Falls back to `window.open` on web. |
| **Tools** | `skill:feature-dev` |
| **Files** | `resources/js/mobile/DeepLinkButton.vue` (CREATE) |
| **Tests** | Capacitor: tap → in-app browser opens fynla.org/module. Web: tap → new tab. Close browser → back to app. |
| **Sign-off** | - [ ] Opens correct URL per module. - [ ] In-app browser (not external Safari/Chrome). - [ ] Close returns to app. - [ ] Web fallback works. |
| **Depends on** | P2-04 |

#### P2-23: Build MobileAlertsList Component

| Field | Detail |
|-------|--------|
| **Task** | Compact alerts list for dashboard: icon + text + time badge. Max 3 shown with "View all" link. Violet-50 background. |
| **Tools** | `agent:premium-ui` |
| **Files** | `resources/js/mobile/MobileAlertsList.vue` (CREATE) |
| **Tests** | Renders alerts from dashboard data. Max 3 shown. "View all" expands or navigates. Correct icon per alert type. |
| **Sign-off** | - [ ] Violet-50 background with violet-200 border. - [ ] Max 3 alerts shown. - [ ] "View all" link functional. - [ ] Alert icons match types. |
| **Depends on** | P2-17 |

### Section D: Full-Screen AI Chat (Week 10-11)

#### P2-24: Build MobileFynChat Shell & ChatBubble (REVISED — Split per review)

> **REVIEW NOTE (Voice of Reason + Devil's Advocate):** Original task created 5 components (HIGH complexity). Split into P2-24 (core chat shell + bubbles) and P2-24b (supporting components). The existing `AiMessageContent` component may have web-specific styling baked in — extracting a reusable message renderer is a hidden sub-task.

| Field | Detail |
|-------|--------|
| **Task** | Create full-screen dedicated chat screen (separate from modified AiChatPanel). Create ChatBubble component with Fyn avatar on Fyn messages, user messages right-aligned. Custom mobile input area with send button. Keyboard-aware layout. Integrate SSE streaming from existing aiChat store. Reuse `AiMessageContent` for message rendering — extract shared logic if needed. |
| **Tools** | `skill:feature-dev`, `agent:premium-ui`, `mcp:context7` (Capacitor App lifecycle docs) |
| **Files** | `resources/js/mobile/MobileFynChat.vue` (CREATE), `resources/js/mobile/ChatBubble.vue` (CREATE) |
| **Tests** | Send message → SSE streams response token by token. Chat bubbles styled per design spec. Fyn avatar on Fyn messages. Keyboard pushes input up. Quick reply chips after responses (from P1-11). |
| **Sign-off** | - [ ] Messages stream via SSE. - [ ] Chat bubbles styled per design spec (Fyn: `bg-white rounded-2xl rounded-bl-sm`, User: `bg-raspberry-50 rounded-2xl rounded-br-sm`). - [ ] Fyn avatar on Fyn messages. - [ ] Keyboard-aware input. - [ ] Quick reply chips integrated. - [ ] Tested with all 7 personas. |
| **Depends on** | P2-15, P1-10, P1-11 |

#### P2-24b: Build Chat Supporting Components (REVIEW ADDITION — Split from P2-24)

| Field | Detail |
|-------|--------|
| **Task** | Create supporting chat components: SuggestedPrompts (empty state), TypingIndicator (3 animated dots during response), ToolExecutionStatus ("Fyn is analysing your portfolio..." with spinner). Integrate all into MobileFynChat. |
| **Tools** | `agent:premium-ui` |
| **Files** | `resources/js/mobile/SuggestedPrompts.vue` (CREATE), `resources/js/mobile/TypingIndicator.vue` (CREATE), `resources/js/mobile/ToolExecutionStatus.vue` (CREATE) |
| **Tests** | Empty state shows suggested prompts. Tap prompt → sends as message. Typing indicator shows during AI response. Tool execution shows analysing status with correct module context. |
| **Sign-off** | - [ ] Suggested prompts render on empty chat. - [ ] Tap prompt sends message. - [ ] Typing indicator: 3 animated dots in Fyn bubble style. - [ ] Tool execution status with spinner and context-aware text. - [ ] All components integrate cleanly in MobileFynChat. |
| **Depends on** | P2-24 |

#### P2-25: Build VoiceInputButton Component

| Field | Detail |
|-------|--------|
| **Task** | Microphone button for speech-to-text input in Fyn chat. Uses native speech recognition on Capacitor, Web Speech API on web. Recording state animation. |
| **Tools** | `mcp:context7` (speech recognition docs), `skill:feature-dev` |
| **Files** | `resources/js/mobile/VoiceInputButton.vue` (CREATE) |
| **Tests** | Capacitor: tap mic → recording indicator → speak → text appears in input. Web: uses Web Speech API (if available) or hidden. Cancel recording → no text sent. |
| **Sign-off** | - [ ] Recording state animation. - [ ] Text appears in input field after recognition. - [ ] Cancel/error handling. - [ ] Graceful degradation on unsupported platforms. |
| **Depends on** | P2-24 |

#### P2-26: SSE Streaming Lifecycle Management

| Field | Detail |
|-------|--------|
| **Task** | Implement AbortController-based SSE management. App backgrounded → abort active stream. App foregrounded → reload conversation via GET. Handle interrupted responses ("Fyn was interrupted. Tap to continue."). |
| **Tools** | `skill:feature-dev` |
| **Files** | `resources/js/store/modules/aiChat.js` (MODIFY — add abortStreaming action) |
| **Tests** | Start chat → background app → stream aborts (no network activity). Foreground → conversation reloads. Interrupted response shows retry option. |
| **Sign-off** | - [ ] Stream aborts on background. - [ ] Conversation reloads on foreground. - [ ] Interrupted response handling. - [ ] No battery drain from background connections. |
| **Depends on** | P2-24 |

### Section E: Push Notifications (Week 11-12)

#### P2-27: Device Tokens Migration & Model

| Field | Detail |
|-------|--------|
| **Task** | Create `device_tokens` and `notification_preferences` migrations and models. Add `device_id` column to `user_sessions` table. |
| **Tools** | `skill:feature-dev`, `agent:db-optimizer` |
| **Files** | `database/migrations/YYYY_create_device_tokens_table.php` (CREATE), `database/migrations/YYYY_create_notification_preferences_table.php` (CREATE), `database/migrations/YYYY_add_device_id_to_user_sessions.php` (CREATE), `app/Models/DeviceToken.php` (CREATE), `app/Models/NotificationPreference.php` (CREATE), `app/Models/UserSession.php` (MODIFY) |
| **Tests** | `php artisan migrate` succeeds. Models create/read/update/delete correctly. Foreign key cascades work (delete user → delete devices). |
| **Sign-off** | - [ ] Migrations run without error. - [ ] `cmd:seed` works after migration. - [ ] Unique constraint on (user_id, device_id). - [ ] Cascade delete tested. - [ ] `agent:db-optimizer` approves schema. - [ ] Preview persona notification_preferences seeded for testing (add to `PreviewUserSeeder` or create `NotificationPreferenceSeeder`). |
| **Depends on** | None |

#### P2-27b: Firebase Project Setup & APNs Configuration (REVIEW ADDITION)

> **Added by Devil's Advocate (HIGH):** P2-29 installs the FCM package but no task covers creating the Firebase project, enabling Cloud Messaging, downloading `google-services.json` (Android) and `GoogleService-Info.plist` (iOS), or configuring Apple Push Notification certificates in Firebase for iOS push delivery.

| Field | Detail |
|-------|--------|
| **Task** | Create Firebase project for Fynla. Enable Cloud Messaging. Generate and download `google-services.json` (Android) and `GoogleService-Info.plist` (iOS). For iOS push: generate APNs authentication key in Apple Developer portal, upload to Firebase Console. Configure Firebase project settings (bundle ID, SHA fingerprints). |
| **Tools** | Manual (Firebase Console, Apple Developer Portal) |
| **Files** | `android/app/google-services.json` (CREATE — from Firebase), `ios/App/App/GoogleService-Info.plist` (CREATE — from Firebase) |
| **Tests** | Firebase Console shows both iOS and Android apps registered. FCM test message from console → delivered to test device. |
| **Sign-off** | - [ ] Firebase project created. - [ ] iOS app registered with correct bundle ID (`org.fynla.app`). - [ ] Android app registered with correct package name. - [ ] APNs auth key uploaded to Firebase. - [ ] `google-services.json` in Android project. - [ ] `GoogleService-Info.plist` in iOS project. - [ ] Test notification delivered successfully. |
| **Depends on** | P2-01, P2-02, P2-03 |

#### P2-28: Device Registration Endpoints

| Field | Detail |
|-------|--------|
| **Task** | Create `DeviceController` with register (POST) and unregister (DELETE). Create `RegisterDeviceRequest` validation. Add to PreviewWriteInterceptor excluded routes. |
| **Tools** | `skill:feature-dev` |
| **Files** | `app/Http/Controllers/Api/V1/Mobile/DeviceController.php` (CREATE), `app/Http/Requests/V1/RegisterDeviceRequest.php` (CREATE), `routes/api_v1.php` (MODIFY), `app/Http/Middleware/PreviewWriteInterceptor.php` (MODIFY) |
| **Tests** | Register device → 201. Register same device_id → updates token (upsert). Delete device → 200. Unauthed → 401. Preview user can register (excluded route). |
| **Sign-off** | - [ ] Registration with validation. - [ ] Upsert on existing device_id. - [ ] Deletion works. - [ ] Added to PreviewWriteInterceptor EXCLUDED_ROUTES. - [ ] Feature tests written. |
| **Depends on** | P2-27 |

#### P2-29: FCM Integration & PushNotificationService

| Field | Detail |
|-------|--------|
| **Task** | Install `laravel-notification-channels/fcm`. Create `PushNotificationService`. Configure FCM credentials in `.env`. |
| **Tools** | `mcp:context7` (FCM channel docs), `skill:feature-dev` |
| **Files** | `composer.json` (MODIFY), `app/Services/Mobile/PushNotificationService.php` (CREATE), `.env.example` (MODIFY) |
| **Tests** | Service can send test notification to registered device. Respects notification preferences. Handles invalid tokens gracefully. |
| **Sign-off** | - [ ] FCM package installed. - [ ] Service sends to iOS and Android. - [ ] Preferences checked before sending. - [ ] Invalid token handling (remove stale tokens). - [ ] `.env.example` updated. |
| **Depends on** | P2-28 |

#### P2-30: Create 6 Notification Classes

| Field | Detail |
|-------|--------|
| **Task** | Create Laravel Notification classes: PolicyRenewal, GoalMilestone, ContributionReminder, SecurityAlert, SubscriptionExpiring, DailyInsight. Each with `toFcm()` method returning correct payload. |
| **Tools** | `skill:feature-dev` |
| **Files** | `app/Notifications/PolicyRenewalNotification.php` (CREATE), `GoalMilestoneNotification.php`, `ContributionReminderNotification.php`, `SecurityAlertNotification.php`, `SubscriptionExpiringNotification.php`, `DailyInsightNotification.php` (6 files CREATE) |
| **Tests** | Unit test each: verify notification payload includes title, body, data (type, module, deep_link, web_url). |
| **Sign-off** | - [ ] All 6 notifications created. - [ ] Correct payload format per plan spec. - [ ] Deep link URLs included in data. - [ ] Unit tests passing. |
| **Depends on** | P2-29 |

#### P2-31: Notification Preference Endpoints

| Field | Detail |
|-------|--------|
| **Task** | Create endpoints: GET and PUT notification preferences. Default preferences created on first device registration. |
| **Tools** | `skill:feature-dev` |
| **Files** | `app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php` (CREATE), `app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php` (CREATE), `routes/api_v1.php` (MODIFY) |
| **Tests** | GET returns current preferences. PUT updates preferences. Security alerts always true (cannot disable). |
| **Sign-off** | - [ ] GET/PUT endpoints work. - [ ] Default preferences seeded on first registration. - [ ] Security alerts cannot be disabled. - [ ] Feature tests passing. |
| **Depends on** | P2-27 |

#### P2-32: Scheduled Notification Commands

| Field | Detail |
|-------|--------|
| **Task** | Create artisan commands: `SendDailyInsightNotifications` (daily cron) and `SendPolicyRenewalReminders` (daily cron). Register in Kernel.php schedule. |
| **Tools** | `skill:feature-dev` |
| **Files** | `app/Console/Commands/SendDailyInsightNotifications.php` (CREATE), `app/Console/Commands/SendPolicyRenewalReminders.php` (CREATE), `app/Console/Kernel.php` (MODIFY) |
| **Tests** | Run command manually → notifications dispatched to registered devices. Respects preferences. Handles no-device users gracefully. |
| **Sign-off** | - [ ] Commands runnable via artisan. - [ ] Scheduled in Kernel.php. - [ ] Respects notification preferences. - [ ] Daily insights batched (not per-user AI call). - [ ] Logs sent notification counts. |
| **Depends on** | P2-30 |

#### P2-33: Frontend Push Notification Integration

| Field | Detail |
|-------|--------|
| **Task** | Request push notification permission on first Capacitor launch. Handle incoming notifications: navigate to correct screen based on deep_link data. Badge count management. |
| **Tools** | `mcp:context7` (Capacitor push notifications docs), `skill:feature-dev` |
| **Files** | `resources/js/store/modules/mobileNotifications.js` (CREATE), relevant mobile components (MODIFY) |
| **Tests** | iOS: permission prompt appears. Grant → device registered via API. Receive notification → tap → navigates to correct screen. Badge count updates. |
| **Sign-off** | - [ ] Permission request on first launch. - [ ] Device registered on grant. - [ ] Tap notification → correct screen opens (both cold-start and warm-start scenarios). - [ ] Cold-start from notification: app initialises → authenticates (biometric) → navigates to correct screen. - [ ] Badge count management. - [ ] Handles permission denial gracefully. - [ ] Notification payloads do NOT contain sensitive financial data visible on lock screen (use generic text like "You have a new alert" with detail only shown after unlock/app open). |
| **Depends on** | P2-28, P2-04 |

### Section F: Goals & Deep Links (Week 12-13)

#### P2-34: Build Mobile Goals Screen

| Field | Detail |
|-------|--------|
| **Task** | Create goals tab: MobileGoalsList (filter chips, goal cards), MobileGoalCard (progress ring, status), MobileGoalDetail (large ring, milestones, contribution log), ContributionFAB, MilestoneOverlay (celebration). |
| **Tools** | `skill:feature-dev`, `agent:premium-ui` |
| **Files** | `resources/js/mobile/goals/MobileGoalsList.vue` (CREATE), `MobileGoalCard.vue`, `MobileGoalDetail.vue`, `ContributionFAB.vue`, `MilestoneOverlay.vue` (5 files CREATE), `resources/js/mobile/charts/ProgressRing.vue` (CREATE) |
| **Tests** | Goals load from API. Filter chips work. Progress rings accurate. Contribution FAB opens form. Milestone overlay triggers at 25/50/75/100%. Haptic feedback on milestone. |
| **Sign-off** | - [ ] Filter chips: All/Active/Completed. - [ ] Progress rings (56pt cards, 120pt detail). - [ ] Contribution FAB. - [ ] Milestone celebration overlay. - [ ] Haptic feedback on milestones. - [ ] Empty state with Fyn. - [ ] Tested with personas that have goals (young_family, peak_earners). |
| **Depends on** | P2-15 |

#### P2-35: Deep Link Server Configuration

| Field | Detail |
|-------|--------|
| **Task** | Create apple-app-site-association and assetlinks.json. Upload to production `public/.well-known/`. Verify served with correct Content-Type. |
| **Tools** | `skill:feature-dev` |
| **Files** | `public/.well-known/apple-app-site-association` (CREATE), `public/.well-known/assetlinks.json` (CREATE) |
| **Tests** | `curl https://fynla.org/.well-known/apple-app-site-association` returns JSON. App installed → tap web link → opens in app. App not installed → opens in browser normally. |
| **Sign-off** | - [ ] AASA file served with `application/json` content-type. - [ ] assetlinks.json served correctly. - [ ] Excluded paths (/api, /admin, /login) don't trigger app. - [ ] Universal Links work on iOS. - [ ] App Links work on Android. |
| **Depends on** | P2-01, P2-02, P2-03 |

### Section G: Learn Hub & More Menu (Week 13-14)

#### P2-36: Build Basic Learn Hub

| Field | Detail |
|-------|--------|
| **Task** | Create Learn tab: topic grid (8 topics), topic detail with curated external links (MoneyHelper, HMRC, Pension Wise). "Ask Fyn about this" button on each topic. No in-app articles (Phase 3). |
| **Tools** | `skill:feature-dev`, `agent:premium-ui` |
| **Files** | `resources/js/mobile/learn/LearnHub.vue` (CREATE), `LearnTopicDetail.vue`, `TopicCard.vue` (CREATE), `resources/js/store/modules/mobileLearn.js` (CREATE) |
| **Tests** | 8 topics render in grid. Tap topic → external links page. "Ask Fyn" → opens chat with topic context. Search filters topics. |
| **Sign-off** | - [ ] 8 topic cards in 2-column grid. - [ ] Topic detail with curated external links. - [ ] "Ask Fyn about this" button. - [ ] Search/filter functional. - [ ] No FCA compliance issues (external links only). |
| **Depends on** | P2-15 |

#### P2-37: Build More Menu & Settings

| Field | Detail |
|-------|--------|
| **Task** | Create More tab: user profile card, module links grid, settings list (account, security, notifications, subscription, data, help, about), "Open full web app" link, logout. |
| **Tools** | `skill:feature-dev`, `agent:premium-ui` |
| **Files** | `resources/js/mobile/MoreMenu.vue` (CREATE), `resources/js/mobile/SettingsList.vue` (CREATE) |
| **Tests** | Profile card shows user info. Module links navigate to summaries. Settings items navigate to correct screens. Logout clears token and returns to login. "Open web app" opens browser. |
| **Sign-off** | - [ ] Profile card with subscription badge. - [ ] 7 module links. - [ ] Settings menu items all functional. - [ ] Logout clears all local state. - [ ] "Open fynla.org" button works. |
| **Depends on** | P2-15 |

#### P2-37b: Mobile Subscription Status & Payment Routing (REVIEW ADDITION)

> **Added by Voice of Reason + Devil's Advocate (HIGH):** The plan discusses routing subscriptions through web (Revolut) to avoid Apple's 30% cut, using a "reader app" exemption. No task covers: how the mobile app handles expired subscriptions, the flow for purchasing/renewing via web, or the return-to-app experience after payment. The existing `CheckSubscription` middleware could reject mobile requests for expired users.

| Field | Detail |
|-------|--------|
| **Task** | Handle subscription status on mobile: (1) Check subscription status on app launch and show appropriate UI. (2) Expired subscription → show upgrade prompt with "Manage subscription" button that opens fynla.org/subscription in in-app browser (Revolut payment stays on web — avoids Apple 30% cut). (3) After payment on web → return to app → verify updated subscription status via API. (4) Modify `CheckSubscription.php` to return proper 402/403 responses (not redirects) for mobile API requests. (5) Free tier limitations: define which mobile features require active subscription. |
| **Tools** | `skill:feature-dev`, `agent:security-reviewer` |
| **Files** | `app/Http/Middleware/CheckSubscription.php` (MODIFY — mobile-aware responses), `resources/js/mobile/SubscriptionPrompt.vue` (CREATE), `resources/js/mobile/MoreMenu.vue` (MODIFY — subscription management) |
| **Tests** | Active subscription → full access. Expired → upgrade prompt shown. "Manage subscription" → in-app browser to fynla.org. Complete payment on web → return to app → subscription active. API returns 402 (not redirect) for expired mobile users. |
| **Sign-off** | - [ ] Expired users see clear upgrade prompt (not error). - [ ] Payment via web (Revolut) — no in-app purchase. - [ ] Web → app return flow works. - [ ] API returns JSON errors (not HTML redirects) for mobile. - [ ] Free tier features accessible without subscription. - [ ] `agent:security-reviewer` passes. |
| **Depends on** | P2-15, P2-22 |

#### P2-38: Build Mobile Onboarding

| Field | Detail |
|-------|--------|
| **Task** | Adapt existing Quick Mode for mobile: welcome screen (Fyn illustration, gradient bg), journey selection (focus areas), quick profile (3 fields). NOT conversational AI (deferred to Phase 4). |
| **Tools** | `skill:feature-dev`, `agent:premium-ui` |
| **Files** | `resources/js/mobile/MobileOnboarding.vue` (CREATE) |
| **Tests** | New user → welcome screen. Select focus areas → quick profile form. Complete → dashboard with initial data. Skip → empty dashboard with Fyn prompt. |
| **Sign-off** | - [ ] Welcome screen with Fyn + gradient. - [ ] Journey selection (multi-select). - [ ] Quick profile (name, age, employment). - [ ] Skip option available. - [ ] Validated form inputs. |
| **Depends on** | P2-15 |

### Section H: State Management & Offline (Week 13-14)

#### P2-39: Vuex Persistence Plugin

| Field | Detail |
|-------|--------|
| **Task** | Create Vuex persistence plugin. Persist key stores (auth, dashboard, aiChat, goals, userProfile, mobileDashboard, journeys) to Capacitor Preferences on native, localStorage on web. |
| **Tools** | `skill:feature-dev` |
| **Files** | `resources/js/store/plugins/mobilePersistence.js` (CREATE), `resources/js/store/index.js` (MODIFY), `resources/js/store/modules/mobileDashboard.js` (CREATE), `resources/js/store/modules/mobileSync.js` (CREATE) |
| **Tests** | Save data → kill app → reopen → data persisted. Logout → all persisted data cleared. Web: uses localStorage (verify). Native: uses Preferences (verify). |
| **Sign-off** | - [ ] Key stores persist across app kills. - [ ] Logout clears all persisted state. - [ ] Web/native storage strategy correct. - [ ] No sensitive financial data stored raw (summaries only). - [ ] Storage size management: aiChat history pruned to last 50 conversations (Capacitor Preferences has ~2MB per-key limit). - [ ] State migration strategy: include a schema version number so future app updates can migrate persisted state without crashing. |
| **Depends on** | P2-06 |

### Section I: Phase 2 Testing & Sign-off

#### P2-40: Phase 2 Full Integration Testing

| Field | Detail |
|-------|--------|
| **Task** | Complete test matrix across all Phase 2 features on iOS Simulator, Android Emulator, and web browser. |
| **Tools** | `cmd:pest`, `mcp:playwright`, manual device testing, `cmd:seed` |
| **Tests** | Full test matrix below. |

#### Phase 2 Test Matrix

| # | Test | Platform | Pass Criteria |
|---|------|----------|---------------|
| 1 | Build iOS | Xcode | Clean build, no warnings |
| 2 | Build Android | Android Studio | Clean build, no warnings |
| 3 | Full auth flow (first login) | iOS + Android | Login → verify → biometric prompt → dashboard |
| 4 | Biometric re-auth | iOS + Android | Kill → reopen → biometric → dashboard (no login) |
| 5 | Token refresh | iOS + Android | Token >25 days → auto-refresh → no interruption |
| 6 | Dashboard loads all modules | iOS + Android + Web | 6 module cards, net worth, Fyn insight, alerts |
| 7 | All 7 module summaries | iOS + Android | Correct hero metrics per persona |
| 8 | AI chat full-screen | iOS + Android | Messages stream, keyboard handling, quick replies |
| 9 | AI chat SSE lifecycle | iOS | Background → abort. Foreground → reload |
| 10 | Voice input | iOS + Android | Speak → text in input |
| 11 | Push notification receive | iOS + Android | Notification arrives, tap → correct screen |
| 12 | Push notification preferences | iOS + Android | Toggle off → no notification for that type |
| 13 | Goals — filter, progress, detail | iOS + Android | All filters work, rings accurate, contribution FAB |
| 14 | Goal milestone celebration | iOS + Android | Overlay + confetti + haptic at 25/50/75/100% |
| 15 | Deep links | iOS + Android | Web URL → opens in app (when installed) |
| 16 | "View full detail" button | iOS + Android | Opens in-app browser to fynla.org |
| 17 | Learn hub | iOS + Android | 8 topics, external links, "Ask Fyn" |
| 18 | More menu + settings | iOS + Android | All items navigate, logout clears state |
| 19 | Onboarding flow | iOS + Android | Welcome → focus areas → profile → dashboard |
| 20 | Offline dashboard | iOS + Android | Disconnect → cached data shows, banner visible |
| 21 | Offline chat | iOS + Android | Previous conversations viewable, new messages queued |
| 22 | State persistence | iOS + Android | Kill → reopen → dashboard data cached |
| 23 | All 7 preview personas | iOS + Android | Each persona loads with correct data |
| 24 | Web regression | Chrome + Safari | `cmd:pest` full suite (940+ tests pass) |
| 25 | Performance: dashboard load | iOS + Android | <1 second cold start, <200ms cached |
| 26 | Performance: bundle size | Build output | Mobile bundle <3MB |
| 27 | Security scan | `mcp:trivy` | No HIGH/CRITICAL vulnerabilities |
| 28 | Accessibility: VoiceOver | iOS | All screens navigable, financial data readable |
| 29 | Accessibility: TalkBack | Android | All screens navigable, financial data readable |
| 30 | MFA login flow | iOS + Android | TOTP prompt → enter code → authenticated |
| 31 | Token revocation ("Sign out all devices") | iOS + Android | Revoke → other device gets 401 → login redirect |
| 32 | Joint couple net worth | iOS + Android | James sees his share, Emily sees hers (not combined total) |
| 33 | Expired subscription handling | iOS + Android | Expired → upgrade prompt → web payment → return → active |
| 34 | Cold-start from notification | iOS + Android | Tap notification (app killed) → biometric → correct screen |
| 35 | Android back button | Android | Back within tab → pop stack. Back at tab root → confirm exit |
| 36 | App version check | iOS + Android | Old version → force update prompt with store link |

#### P2-41: Phase 2 Code Review & Security Audit

| Field | Detail |
|-------|--------|
| **Task** | Full code review of all Phase 2 changes. Security audit of auth flow, token handling, push notifications, CORS. |
| **Tools** | `skill:code-review`, `agent:code-reviewer`, `agent:security-reviewer`, `skill:tech-debt-session`, `mcp:trivy`, `cmd:pint` |
| **Sign-off** | - [ ] Code review approved. - [ ] Security audit passed. - [ ] Tech debt check clean. - [ ] `cmd:pint` formatting clean. - [ ] `mcp:trivy` — no new vulnerabilities. - [ ] All Fynla coding standards met (CLAUDE.md rules). |
| **Depends on** | P2-40 |

#### P2-42: Phase 2 Archive & Metrics Snapshot

| Field | Detail |
|-------|--------|
| **Task** | Archive Phase 2 completion. Record all metrics. Document deviations. |
| **Tools** | Manual |
| **Files** | `docs/plans/phase2-completion.md` (CREATE) |
| **Sign-off** | - [ ] Component count matches plan (~53). - [ ] Backend file count matches (~30 new, ~14 modified). - [ ] Performance metrics recorded. - [ ] Known issues documented. - [ ] Phase 2 → Phase 3 gate decision documented. |
| **Depends on** | P2-41 |

---

## Phase 3: Polish & App Store (Weeks 15-20)

### P3-01: Apple App Store Connect Setup

| Field | Detail |
|-------|--------|
| **Task** | Create app in App Store Connect. Configure: app name, bundle ID, primary language (English UK), primary category (Finance). Upload screenshots placeholders. Set up TestFlight for beta testing. |
| **Tools** | Manual (App Store Connect) |
| **Files** | N/A (external) |
| **Sign-off** | - [ ] App created in App Store Connect. - [ ] Bundle ID matches `org.fynla.app`. - [ ] TestFlight configured. - [ ] Beta testing group created. |
| **Depends on** | P2-01 |

### P3-02: Google Play Console Setup

| Field | Detail |
|-------|--------|
| **Task** | Create app in Google Play Console. Configure: app name, default language, category (Finance), content rating, data safety form. Set up internal testing track. |
| **Tools** | Manual (Google Play Console) |
| **Files** | N/A (external) |
| **Sign-off** | - [ ] App created in Play Console. - [ ] Data safety form completed. - [ ] Internal testing track configured. - [ ] Content rating questionnaire completed. |
| **Depends on** | P2-02 |

### P3-03: Privacy Nutrition Labels (Apple)

| Field | Detail |
|-------|--------|
| **Task** | Complete Apple's privacy nutrition label questionnaire. Declare: data collected, data linked to identity, tracking. Document all data types used. |
| **Tools** | Manual |
| **Files** | `docs/plans/apple-privacy-labels.md` (CREATE — internal reference) |
| **Sign-off** | - [ ] All data types documented. - [ ] Nutrition labels submitted in App Store Connect. - [ ] No undeclared data collection. |
| **Depends on** | P3-01 |

### P3-04: AI Usage Disclosure

| Field | Detail |
|-------|--------|
| **Task** | Add clear AI disclaimers throughout app: "Fyn provides guidance, not regulated financial advice." Label all AI-generated content. Add to app store descriptions. |
| **Tools** | `skill:feature-dev` |
| **Files** | Various mobile components (MODIFY — add disclaimer text), App Store description |
| **Sign-off** | - [ ] Disclaimer in Fyn chat (visible before first message). - [ ] "AI-generated" label on all Fyn responses. - [ ] "Verify with an adviser" CTA after recommendations. - [ ] App Store description mentions AI clearly. |
| **Depends on** | P2-24 |

### P3-05: Financial Disclaimer & Regulatory Compliance

| Field | Detail |
|-------|--------|
| **Task** | Add financial disclaimers: "Not regulated financial advice." FCA compliance for app store listing. Terms of service and privacy policy links in app. |
| **Tools** | Manual |
| **Files** | `resources/js/mobile/SettingsList.vue` (MODIFY — add legal links) |
| **Sign-off** | - [ ] Financial disclaimer visible in app. - [ ] Terms of service accessible. - [ ] Privacy policy accessible. - [ ] App store descriptions FCA-compliant. |
| **Depends on** | None |

### P3-05b: Install & Configure Crash Reporting — Sentry (REVIEW ADDITION)

> **Added by Voice of Reason + Devil's Advocate (HIGH):** P3-15 sign-off references "Monitoring/crash reporting configured (Sentry)" but no task sets it up. A financial app launching into app stores with zero crash visibility is unacceptable.

| Field | Detail |
|-------|--------|
| **Task** | Install `@sentry/capacitor` and `@sentry/vue`. Create Sentry project for Fynla mobile. Configure source map uploads in build pipeline. Add Vue error boundary integration. Configure alert rules for crash spikes. Add Sentry DSN to `.env`. |
| **Tools** | `skill:feature-dev`, `mcp:context7` (Sentry Capacitor docs) |
| **Files** | `package.json` (MODIFY), `resources/js/mobile.js` (MODIFY — Sentry init), `deploy/mobile/build-ios.sh` (MODIFY — source map upload), `deploy/mobile/build-android.sh` (MODIFY — source map upload), `.env.example` (MODIFY — SENTRY_DSN) |
| **Tests** | Trigger test error → appears in Sentry dashboard within 60 seconds. Source maps resolve to original file/line. Breadcrumbs show navigation trail. User context attached (user ID, subscription tier — no PII). |
| **Sign-off** | - [ ] Sentry capturing errors on iOS and Android. - [ ] Source maps uploaded and resolving correctly. - [ ] Vue error boundary catches component errors. - [ ] Alert rules configured for crash rate spikes. - [ ] No PII in Sentry (user ID only, no email/name). - [ ] Performance monitoring enabled (transaction traces). |
| **Depends on** | P2-03 |

### P3-06: Accessibility Audit

| Field | Detail |
|-------|--------|
| **Task** | Full accessibility audit: VoiceOver (iOS), TalkBack (Android), Dynamic Type support, WCAG AA contrast verification, screen reader labels for all financial data and charts. |
| **Tools** | Manual + Xcode Accessibility Inspector + Android Accessibility Scanner |
| **Files** | Various mobile components (MODIFY — add aria labels, accessibility traits) |
| **Tests** | Navigate all screens with VoiceOver/TalkBack. Verify Dynamic Type at xSmall and xxxLarge. Contrast verification for all colour combinations. |
| **Sign-off** | - [ ] All screens VoiceOver navigable. - [ ] All screens TalkBack navigable. - [ ] Dynamic Type supported (16pt-32pt range). - [ ] WCAG AA contrast met. - [ ] Financial values announced correctly ("X pounds"). - [ ] Charts have text alternatives. |
| **Depends on** | P2-40 |

### P3-07: Skeleton Loading States

| Field | Detail |
|-------|--------|
| **Task** | Add skeleton screen loading states to all screens: dashboard, module summaries, chat, goals, learn, more. `bg-savannah-100 animate-pulse`. |
| **Tools** | `agent:premium-ui` |
| **Files** | Various mobile components (MODIFY) |
| **Sign-off** | - [ ] Skeleton loading on all 12 screens: MobileDashboard, 7 module summaries, MobileFynChat, MobileGoalsList, MobileGoalDetail, LearnHub, MoreMenu. - [ ] Skeletons match content layout shape (cards, text lines, progress rings). - [ ] savannah-100 with animate-pulse. - [ ] Smooth transition to real content (no flash/jump). |
| **Depends on** | P2-17 through P2-38 |

### P3-08: Error State Implementation

| Field | Detail |
|-------|--------|
| **Task** | Implement all error states: network offline, API 500, API 429, AI unavailable, session expired, validation error, sync conflict. Per design spec. |
| **Tools** | `skill:feature-dev`, `agent:premium-ui` |
| **Files** | Various mobile components (MODIFY) |
| **Sign-off** | - [ ] Network offline: banner + cached data. - [ ] API 500: error screen with retry. - [ ] API 429: toast with cooldown. - [ ] AI unavailable: chat shows fallback message. - [ ] Session expired: modal → login redirect. - [ ] Validation: inline field errors. |
| **Depends on** | P2-17 through P2-38 |

### P3-09: Haptic Feedback Integration

| Field | Detail |
|-------|--------|
| **Task** | Add haptic feedback: goal milestones (medium), chat sends (light), successful saves (triple tap), tab switches (light), card taps (light). |
| **Tools** | `skill:feature-dev`, `mcp:context7` (Capacitor haptics docs) |
| **Files** | Various mobile components (MODIFY) |
| **Sign-off** | - [ ] Goal milestone: medium impact. - [ ] Chat send: light tap. - [ ] Save confirmation: success pattern. - [ ] Haptics only on native (no errors on web). |
| **Depends on** | P2-04 |

### P3-09b: Configure Capgo OTA Updates (REVIEW ADDITION)

> **Added by Voice of Reason (MEDIUM):** The cost model lists Capgo at £50-100/month for OTA updates but no task covers setup. OTA updates allow pushing web-layer fixes without app store review (1-7 day delay per fix otherwise).

| Field | Detail |
|-------|--------|
| **Task** | Install and configure Capgo for over-the-air updates. Configure auto-update strategy (background download, apply on next launch). Set up CI pipeline for OTA deployments. Test rollback mechanism. |
| **Tools** | `mcp:context7` (Capgo docs), `skill:feature-dev` |
| **Files** | `package.json` (MODIFY), `capacitor.config.ts` (MODIFY — Capgo plugin config) |
| **Tests** | Push OTA update → app receives on next launch → new version active. Rollback → reverts to previous. No user-visible interruption during update. |
| **Sign-off** | - [ ] Capgo configured and functional. - [ ] Auto-update on background download. - [ ] Rollback tested and working. - [ ] OTA deployment pipeline documented. - [ ] Update size monitoring (delta updates for efficiency). |
| **Depends on** | P2-03 |

### P3-10: Build TestFlight Beta (iOS)

| Field | Detail |
|-------|--------|
| **Task** | Build production iOS binary. Upload to TestFlight. Configure beta testing group. Submit for beta app review. |
| **Tools** | `deploy/mobile/build-ios.sh`, Xcode (Archive + Upload) |
| **Files** | N/A (build artefact) |
| **Tests** | TestFlight install → full app flow works. Biometrics, push notifications, chat, all screens. |
| **Sign-off** | - [ ] TestFlight build uploaded. - [ ] Beta app review passed. - [ ] External testers can install. - [ ] All critical features work on physical device. |
| **Depends on** | P3-01, P3-03, P3-04, P3-05, P3-06 |

### P3-11: Build Internal Testing APK (Android)

| Field | Detail |
|-------|--------|
| **Task** | Build production Android AAB. Upload to internal testing track in Google Play Console. |
| **Tools** | `deploy/mobile/build-android.sh`, Android Studio (Build > Generate Signed Bundle) |
| **Files** | N/A (build artefact) |
| **Tests** | Internal testing install → full app flow works. |
| **Sign-off** | - [ ] AAB uploaded to internal testing. - [ ] Internal testers can install. - [ ] All critical features work on physical device. |
| **Depends on** | P3-02, P3-04, P3-05, P3-06 |

### P3-12: App Store Submission (iOS)

| Field | Detail |
|-------|--------|
| **Task** | Prepare full App Store submission: screenshots (6.5", 5.5" required), app description, keywords, support URL, privacy policy URL. Submit for review. |
| **Tools** | Manual (App Store Connect) |
| **Files** | `docs/plans/app-store-submission.md` (CREATE — tracking document) |
| **Tests** | N/A (Apple review) |
| **Sign-off** | - [ ] Screenshots for all required sizes. - [ ] Description compliant (financial disclaimers, AI disclosure). - [ ] Privacy policy URL active. - [ ] Support URL active. - [ ] Submitted for review. - [ ] Track rejection reasons if any. |
| **Depends on** | P3-10 |

### P3-13: Google Play Submission (Android)

| Field | Detail |
|-------|--------|
| **Task** | Prepare Google Play submission: store listing, screenshots, feature graphic, data safety form verified, submit for review. |
| **Tools** | Manual (Google Play Console) |
| **Files** | `docs/plans/google-play-submission.md` (CREATE — tracking document) |
| **Sign-off** | - [ ] Store listing complete. - [ ] Screenshots uploaded. - [ ] Data safety form accurate. - [ ] Submitted for review. |
| **Depends on** | P3-11 |

### P3-14: App Store Rejection Handling

| Field | Detail |
|-------|--------|
| **Task** | Handle app store rejections (expect 1-2 for financial app + AI). Document each rejection reason. Fix issues. Resubmit. Budget 2-3 weeks per cycle. |
| **Tools** | `skill:feature-dev`, `skill:systematic-debugging` |
| **Files** | `docs/plans/app-store-rejections.md` (CREATE — tracking) |
| **Sign-off** | - [ ] Each rejection documented with reason and fix. - [ ] Resubmission timeline tracked. - [ ] Final approval obtained from both stores. |
| **Depends on** | P3-12, P3-13 |

### P3-15: Phase 3 Final Sign-off & Launch

| Field | Detail |
|-------|--------|
| **Task** | Final validation across all platforms. Production readiness check. Launch decision. |
| **Tools** | `cmd:pest`, `mcp:trivy`, `agent:security-reviewer`, manual testing |
| **Sign-off** | - [ ] iOS app approved in App Store. - [ ] Android app approved in Google Play. - [ ] PWA still functional on web. - [ ] All 940+ web tests pass. - [ ] Security audit clean (`mcp:trivy` + `agent:security-reviewer`). - [ ] Performance benchmarks met: dashboard <1s cold start, <200ms cached; mobile bundle <3MB; API responses <500ms p95. - [ ] Crash reporting configured and verified (Sentry — P3-05b). - [ ] OTA updates configured and tested (Capgo — P3-09b). - [ ] Launch date set. |
| **Depends on** | P3-14, P3-05b, P3-09b |

### P3-16: Phase 3 Archive & Launch Documentation

| Field | Detail |
|-------|--------|
| **Task** | Archive Phase 3. Document launch configuration, store URLs, monitoring dashboards, incident response process. |
| **Tools** | Manual |
| **Files** | `docs/plans/phase3-launch.md` (CREATE), `docs/plans/mobile-incident-response.md` (CREATE) |
| **Sign-off** | - [ ] App Store URL documented. - [ ] Google Play URL documented. - [ ] Monitoring configured. - [ ] Incident response documented. - [ ] OTA update process documented. - [ ] Phase 3 → Phase 4 criteria documented. |
| **Depends on** | P3-15 |

---

## Phase 4: Enhanced Features (Weeks 21+, Optional)

### P4-01: Home Screen Widgets (iOS + Android)

| Field | Detail |
|-------|--------|
| **Task** | Net worth widget + goal progress widget. |
| **Tools** | Native (SwiftUI WidgetKit for iOS, Glance for Android) |
| **Sign-off** | - [ ] Net worth widget shows current value. - [ ] Goal progress widget shows top goal. - [ ] Updates at least hourly. |
| **Depends on** | P3-15 |

### P4-02: Siri / Google Assistant Integration

| Field | Detail |
|-------|--------|
| **Task** | "Hey Siri, ask Fyn what my net worth is." Siri Shortcuts + Google Assistant App Actions. |
| **Sign-off** | - [ ] Siri Shortcut works. - [ ] Google Assistant Action works. |
| **Depends on** | P3-15 |

### P4-03: Enhanced Learn Hub (In-App Content)

| Field | Detail |
|-------|--------|
| **Task** | In-app articles, personalised "Your situation" section, AI-generated content reviewed for accuracy. Education backend endpoint. |
| **Tools** | `skill:feature-dev`, backend `EducationController` |
| **Sign-off** | - [ ] In-app articles load. - [ ] Personalised section uses user data. - [ ] Content reviewed for FCA compliance. - [ ] Offline caching of articles. |
| **Depends on** | P3-15 |

### P4-04: Document Scanning (OCR)

| Field | Detail |
|-------|--------|
| **Task** | Camera capture of pension statements → OCR → data extraction → auto-populate fields. |
| **Sign-off** | - [ ] Camera capture works. - [ ] OCR extracts key fields. - [ ] User confirms extracted data before saving. |
| **Depends on** | P3-15 |

---

## Cross-Phase: Recurring Tasks

### REC-01: Database Reseed After Every Backend Change

| Field | Detail |
|-------|--------|
| **Task** | `cmd:seed` after ANY backend change. No exceptions. |
| **Frequency** | After every task involving PHP files, migrations, or seeders |

### REC-02: Run Full Test Suite Before Each Phase Sign-off

| Field | Detail |
|-------|--------|
| **Task** | `cmd:pest` — all 940+ tests must pass |
| **Frequency** | Before P0-05, P1-14, P2-41, P3-15 |

### REC-03: Security Scan at Phase Boundaries

| Field | Detail |
|-------|--------|
| **Task** | `mcp:trivy` filesystem scan. `agent:security-reviewer` on auth/payment code. |
| **Frequency** | At each phase sign-off |

### REC-04: Code Formatting

| Field | Detail |
|-------|--------|
| **Task** | `cmd:pint` for PHP. Design system compliance for Vue. |
| **Frequency** | Before every commit |

### REC-05: Deploy Notes Generation

| Field | Detail |
|-------|--------|
| **Task** | `skill:deploy-notes` after each deployable batch of work |
| **Frequency** | At each phase deployment |

---

## Task Summary

| Phase | Tasks | Key Deliverable |
|-------|-------|----------------|
| **Phase 0** | P0-01 to P0-07 (7 tasks) | Analytics + auth token abstraction (REVISED: secure storage, 7 files) |
| **Phase 1** | P1-01 to P1-16 + P1-09b (17 tasks) | PWA + backend controllers (ModuleSummary, Insights) |
| **Phase 2** | P2-01 to P2-42 + 9 new tasks (51 tasks) | Capacitor app: full mobile experience, secure auth, MFA, token revocation, push (with Firebase), subscription handling |
| **Phase 3** | P3-01 to P3-16 + P3-05b + P3-09b (18 tasks) | App store submission, crash reporting (Sentry), OTA updates (Capgo), accessibility, polish |
| **Phase 4** | P4-01 to P4-04 (4 tasks) | Widgets, voice assistants, enhanced learn, OCR |
| **Recurring** | REC-01 to REC-05 (5 tasks) | Continuous quality gates |
| **TOTAL** | **108 tasks** (was 90, +18 from review) | |

### New Tasks Added by Review

| Task ID | Added By | Severity | Description |
|---------|----------|----------|-------------|
| P1-09b | Voice of Reason + Devil's Advocate | HIGH | ModuleSummaryController & InsightsController backend endpoints |
| P2-08b | Devil's Advocate | HIGH | API backward compatibility, app version check, VerifyMobileSignature middleware |
| P2-09b | Devil's Advocate | CRITICAL | Mobile MFA/TOTP challenge screen |
| P2-09c | Devil's Advocate | CRITICAL | Token revocation & remote session invalidation |
| P2-19b | Voice of Reason + Devil's Advocate | HIGH | DailyInsightGenerator service (batched by cohort) |
| P2-21b | Voice of Reason | MEDIUM | AllocationDonut chart component |
| P2-24b | Voice of Reason | HIGH | Chat supporting components (split from P2-24) |
| P2-27b | Devil's Advocate | HIGH | Firebase project setup & APNs configuration |
| P2-37b | Voice of Reason + Devil's Advocate | HIGH | Mobile subscription status & payment routing |
| P3-05b | Voice of Reason + Devil's Advocate | HIGH | Sentry crash reporting installation & configuration |
| P3-09b | Voice of Reason | MEDIUM | Capgo OTA update configuration |

### Key Revisions to Existing Tasks

| Task | Change | Reason |
|------|--------|--------|
| P0-04 | Added 3 missing files (auth.js, preview.js, app.js), changed to secure storage, added async awareness | Devil's Advocate: CRITICAL security + completeness |
| P1-08 | Added joint ownership verification to sign-off | Devil's Advocate: joint couples would see wrong net worth |
| P2-06 | Changed from `@capacitor/preferences` to secure storage (Keychain/Keystore) | Devil's Advocate: CRITICAL security |
| P2-08 | Added Permissions-Policy, CheckSubscription, AiContextBuilder modifications | Voice of Reason: missing file modifications |
| P2-24 | Split into P2-24 + P2-24b (was 5 components, now 2 + 3) | Both reviewers: HIGH complexity risk |
| P2-27 | Added notification preference seeder for preview personas | Devil's Advocate: testing would fail |
| P2-33 | Added cold-start notification flow, lock screen privacy for payloads | Devil's Advocate: security + edge cases |
| P2-39 | Added storage size management + state migration strategy | Devil's Advocate: hidden complexity |
| P2-40 | Added 7 new tests to test matrix (MFA, revocation, joint, subscription, cold-start, back button, version check) | Both reviewers: testing blind spots |
| P3-07 | Enumerated all 12 screens needing skeletons | Voice of Reason: vague criteria |
| P3-15 | Added specific performance numbers, linked to Sentry + Capgo tasks | Voice of Reason: unmeasurable criteria |

---

## Sign-off Record

| Phase | Reviewer | Date | Status | Notes |
|-------|----------|------|--------|-------|
| **Task List Review** | Voice of Reason | 10 Mar 2026 | **Approved with notes** | 90% coverage. 7 missing backend files, 3 vague sign-offs. All addressed in revision. |
| **Task List Review** | Devil's Advocate | 10 Mar 2026 | **Approved after revision** | 15 gaps found (3 CRITICAL, 7 HIGH). All CRITICAL gaps addressed: secure storage, MFA, token revocation, missing sessionStorage refs. |
| Phase 0 | | | Pending | |
| Phase 1 | | | Pending | |
| Phase 2 Section A (Capacitor) | | | Pending | |
| Phase 2 Section B (UI Shell) | | | Pending | |
| Phase 2 Section C (Dashboard) | | | Pending | |
| Phase 2 Section D (AI Chat) | | | Pending | |
| Phase 2 Section E (Push) | | | Pending | |
| Phase 2 Section F (Goals/Deep Links) | | | Pending | |
| Phase 2 Section G (Learn/More) | | | Pending | |
| Phase 2 Section H (State/Offline) | | | Pending | |
| Phase 2 Integration | | | Pending | |
| Phase 3 (App Store) | | | Pending | |
| Phase 4 (Enhanced) | | | Pending | |

---

## Review Findings Log

### Voice of Reason — 10 March 2026

**Verdict:** APPROVED WITH NOTES (90% plan coverage)

**Strengths:** Excellent test matrices (16 + 29 tests), correct dependency chains, sensible tool assignments, security reviewer on all auth tasks, recurring quality gates well-designed.

**Fixed in revision:**
- 7 missing backend files now have tasks (P1-09b, P2-08b, P2-19b)
- P2-24 split into 2 tasks (was HIGH complexity, 5 components)
- AllocationDonut chart added (P2-21b)
- Sentry crash reporting task added (P3-05b)
- Capgo OTA updates task added (P3-09b)
- P3-07 sign-off enumerated specific screens
- P3-15 sign-off includes specific performance numbers

### Devil's Advocate — 10 March 2026

**Verdict:** APPROVED AFTER REVISION (15 gaps found, all addressed)

**CRITICAL gaps fixed:**
1. `@capacitor/preferences` → secure storage (Keychain/Keystore) — P0-04 + P2-06 revised
2. MFA flow added — P2-09b created
3. 3 missing sessionStorage files added to P0-04
4. Token revocation mechanism added — P2-09c created
5. Crash reporting task added — P3-05b created

**HIGH gaps fixed:**
6. Firebase project setup — P2-27b created
7. API backward compatibility + version check — P2-08b created
8. Joint ownership in dashboard aggregator — P1-08 sign-off updated
9. Subscription handling on mobile — P2-37b created
10. DailyInsightGenerator service — P2-19b created
11. ModuleSummaryController + InsightsController — P1-09b created
12. Push notification lock screen privacy — P2-33 sign-off updated

**Known accepted risks (not blocking):**
- Android manufacturer battery optimisation may affect push delivery (documented, not fixable in app)
- Phase 4 only covers 4 of 9 plan items (acceptable — Phase 4 is speculative)
- P1-10 (responsive AiChatPanel) creates minor tech debt replaced by MobileFynChat in Phase 2
- Learn Hub content curation is a process task, not a code task
