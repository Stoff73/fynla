# Fynla — `/m` Mobile-Web Pathway Connection & Delta Map

**Generated:** 2026-06-05
**Companion to:** `surfaces-and-api-map.md` (the full web surface/API inventory). This report superimposes **`/m` connection status** onto every surface and API in that map, to show where the mobile-web pathway is, what the delta is, and what remains to be built.
**Scope:** Web `/m` pathway only (responsive mobile-web served through the browser). The iOS/Capacitor native build is out of scope, though it shares the same SPA bundle and `/api/v1/mobile/*` contract.

---

## What `/m` actually is (current state)

The `/m` pathway is an **isolated, separate Vue SPA** at `resources/mobile/` — its own `main.js`, `App.vue`, `router.js`, `store.js`, `api.js`. Per its own README it is **"SCAFFOLD ONLY"**: disposable placeholder screens that prove the seam works (real login, real data), to be replaced wholesale by the redesign. It **shares zero components** with the desktop app (`resources/js/`).

**Serving chain:**
- `GET /m` → `mobile-host.blade.php` (device-frame host, iframes `/m/app`)
- `GET /m/app/{any?}` → `mobile-app.blade.php` (boots the isolated SPA)
- Phone user-agents 302-redirected to `/m` by `RedirectPhoneToMobile` (`?full=1` pins to full site via `m_full_site` cookie)
- Auth: bearer token in `localStorage('m_scaffold_token')`, `credentials: 'omit'` (bearer-only, never cookies)

**Critical characteristic — READ-ONLY.** Across the entire mobile SPA: **19 `apiGet` calls, 5 `apiPost` calls**. The POSTs are *only*: auth login, verify-code, AI-chat conversation create, AI-chat message (SSE), onboarding/start, and read-style `analyze`. **There are zero CRUD write surfaces** (no add/edit/delete forms for any module). The only data-entry pathway is **Fyn chat** (`delegate_to_capture` handoff) embedded in the dashboard.

---

## Status legend

| Tag | Meaning |
|-----|---------|
| **CONNECTED** | A dedicated `/m` surface exists and is wired to a live API. |
| **PARTIAL** | Backend reachable from `/m` (e.g. via the generic `/api/v1/mobile/modules/{module}` placeholder drill-down) but **no dedicated rich mobile surface**, or only a read-only subset of the web surface. |
| **NOT CONNECTED** | No `/m` surface and not consumed by the `/m` SPA. |
| **API-only (reverse delta)** | Endpoint built server-side for mobile but **not yet consumed** by the `/m` web SPA. |

---

# 0. The `/m` inventory (what is actually built)

## 0.1 Mobile routes (15, in `resources/mobile/router.js`)

| `/m/app` route | Component | API(s) called |
|----------------|-----------|---------------|
| `/login` | `Login.vue` | `POST /api/auth/login` |
| `/verify` | `Verify.vue` | `POST /api/auth/verify-code` |
| `/dashboard` | `Dashboard.vue` | `GET /api/v1/mobile/dashboard`, `GET /api/auth/user`, `GET/POST /api/ai-chat/conversations`, `POST /api/ai-chat/onboarding/start`, `POST /api/ai-chat/conversations/{id}/messages` (SSE) |
| `/module/:slug` | `ModuleDetail.vue` | `GET /api/v1/mobile/modules/{module}` (generic placeholder drill-down) |
| `/tax-strategy` | `TaxStrategy.vue` | `GET /api/tax-strategy` |
| `/net-worth` | `modules/NetWorth.vue` | `GET /api/net-worth/overview`, `GET /api/net-worth/assets-summary-detailed` |
| `/net-worth/:category` | `modules/NetWorthCategory.vue` | `GET /api/net-worth/overview`, `GET /api/net-worth/assets-summary-detailed` |
| `/protection` | `modules/Protection.vue` | `GET /api/protection` |
| `/protection/policy/:policyType/:id` | `modules/ProtectionPolicy.vue` | `GET /api/protection` |
| `/savings` | `modules/Savings.vue` | `GET /api/savings` |
| `/savings/account/:id` | `modules/SavingsAccount.vue` | `GET /api/savings/accounts/{id}` |
| `/retirement` | `modules/Retirement.vue` | `GET /api/retirement`, `POST /api/retirement/analyze`, `GET /api/retirement/projections` |
| `/retirement/pension/:type/:id` | `modules/RetirementPensionDetail.vue` | `GET /api/retirement`, `GET /api/retirement/dc-pensions/{id}/projections` |
| `/investment` | `modules/Investment.vue` | `GET /api/investment` |
| `/investment/account/:id` | `modules/InvestmentAccountDetail.vue` | `GET /api/investment` |

## 0.2 APIs consumed by `/m` (the complete connected set — ~18 endpoints)

```
AUTH       POST /api/auth/login
           POST /api/auth/verify-code
           GET  /api/auth/user
MOBILE     GET  /api/v1/mobile/dashboard
           GET  /api/v1/mobile/modules/{module}    (protection|savings|investment|retirement|estate|goals|tax)
AI CHAT    GET/POST /api/ai-chat/conversations
           POST /api/ai-chat/conversations/{id}/messages   (SSE)
           POST /api/ai-chat/onboarding/start
NET WORTH  GET  /api/net-worth/overview
           GET  /api/net-worth/assets-summary-detailed
PROTECTION GET  /api/protection
SAVINGS    GET  /api/savings
           GET  /api/savings/accounts/{id}
RETIREMENT GET  /api/retirement
           POST /api/retirement/analyze
           GET  /api/retirement/projections
           GET  /api/retirement/dc-pensions/{id}/projections
INVESTMENT GET  /api/investment
TAX        GET  /api/tax-strategy
```

## 0.3 Mobile backend built but NOT consumed by the `/m` web SPA (reverse delta)

| Endpoint | Controller | Note |
|----------|-----------|------|
| `GET /api/v1/health` | (closure) | health probe |
| `POST /api/v1/auth/refresh-token` | `TokenRefreshController` | native token rotation — web SPA uses long-lived bearer |
| `POST/GET/DELETE /api/v1/mobile/devices` | `DeviceController` | push-notification device registration (native) |
| `GET /api/v1/mobile/insights/daily` | `InsightsController` | daily insight feed — built, not surfaced in `/m` |
| `GET/PUT /api/v1/mobile/notifications/preferences` | `NotificationPreferenceController` | not surfaced in `/m` |
| `GET /api/v1/mobile/share/{type}/{id?}` | `ShareController` | share-card generation — built, not surfaced in `/m` |

---

# 1. Surfaces — connection status superimposed

## 1.1 Public marketing & SEO surfaces — **ALL NOT CONNECTED**

None of the ~85 public surfaces (Home, Calculators, Security, About, Pricing, Features, FAQ, How-it-works, Advisors, Contact, Sitemap, QuickStart, Privacy/Terms/Editorial, 5 campaign landings, 5 life-stage explainers, 7 feature deep-dives, 4 Why-Fynla, 6 compare, ~24 Learn, 8+ Insights, News, 404) have a `/m/app` equivalent.

> The **public marketing funnel** for mobile is handled *outside* the SPA — by the responsive `/savetax*` Blade funnel + `/m` host iframe (see companion report §3). The `/m/app` SPA begins at login. So "public surface parity" is intentionally **not** the SPA's job. Status: **NOT CONNECTED to the SPA (by design — funnel is the responsive web pages, not the SPA).**
>
> **Update (2026-06-05, PR #472 — savetax now genuinely reachable on mobile):** campaign deep-links are no longer collapsed to the generic homepage. A phone hitting `/savetax` (and the other campaign prefixes — `biggerpension`, `paymortgage`, `managedebt`, `wealth`) is redirected to `/m?to=/savetax` (utm/attribution query preserved), and the `/m` host frames that validated campaign path so the ad lands on the campaign funnel inside `/m`. The `?to=` value is open-redirect-guarded (`RedirectPhoneToMobile::isFramableTo()` — same-origin campaign/public paths only). So while these surfaces are still **not part of the SPA**, they ARE reachable on mobile via the responsive funnel inside `/m`. The "Save tax" CTA on the `/m` landing also links straight to the real server-rendered funnel (`<a href>`, not a SPA route — PRs #471/#473).

## 1.2 Authentication & onboarding

| Web surface | `/m` status | Detail |
|-------------|-------------|--------|
| `/login` | **CONNECTED** | `/m/app/login` → `Login.vue` |
| MFA verify code | **CONNECTED** | `/m/app/verify` → `Verify.vue` |
| `/register` | **NOT CONNECTED** | no mobile register surface (registration via responsive funnel) |
| `/checkout` | **NOT CONNECTED** | no mobile checkout |
| `/onboarding/*` (web wizard) | **PARTIAL** | no web onboarding wizard in `/m`; onboarding runs **conversationally via Fyn** on the mobile dashboard (`POST /api/ai-chat/onboarding/start`) |

## 1.3 Authenticated application surfaces

### Dashboard & global
| Web surface | `/m` status | Detail |
|-------------|-------------|--------|
| `/dashboard` | **CONNECTED** | `/m/app/dashboard` → `Dashboard.vue` (aggregated via `/api/v1/mobile/dashboard`) + embedded Fyn chat |
| `/tax-strategy` | **CONNECTED** | `/m/app/tax-strategy` → `TaxStrategy.vue` |
| `/actions`, `/actions/:..` | **NOT CONNECTED** | — |
| `/holistic-plan` | **NOT CONNECTED** | — |
| `/valuable-info` | **NOT CONNECTED** | — |
| `/teaser` | **NOT CONNECTED** | — |
| `/help`, `/version`, `/debug-env` | **NOT CONNECTED** | — |

### Settings & profile — **ALL NOT CONNECTED**
`/settings` and all 9 sub-pages (`security`, `privacy`, `assumptions`, `notifications`, `personal`, `health`, `family`, `subscription`), `/profile`, `/profile/notifications`, `/invoice/:id` — **none** have a `/m` surface.

### Net Worth
| Web surface | `/m` status | Detail |
|-------------|-------------|--------|
| `/net-worth/wealth-summary` | **CONNECTED** | `/m/app/net-worth` → `NetWorth.vue` |
| `/net-worth/:category` (property, retirement, investments, cash, business, chattels, liabilities) | **PARTIAL** | `/m/app/net-worth/:category` → `NetWorthCategory.vue` — generic category drill-down off the same overview/summary data; **read-only**, not the rich per-category web views |
| `/net-worth/{investment-detail, tax-efficiency, holdings-detail, fees-detail, strategy-detail}` | **NOT CONNECTED** | no mobile equivalent of the detailed analysis sub-views |
| `/net-worth/joint-history` | **NOT CONNECTED** | — |

### Retirement
| Web surface | `/m` status | Detail |
|-------------|-------------|--------|
| `/net-worth/retirement` (pension list) | **CONNECTED** | `/m/app/retirement` → `Retirement.vue` (read-only) |
| `/pension/:type/:id` | **CONNECTED (read-only)** | `/m/app/retirement/pension/:type/:id` → `RetirementPensionDetail.vue` |

### Protection
| Web surface | `/m` status | Detail |
|-------------|-------------|--------|
| `/protection` | **CONNECTED (read-only)** | `/m/app/protection` → `Protection.vue` |
| `/protection/policy/:type/:id` | **CONNECTED (read-only)** | `/m/app/protection/policy/:policyType/:id` → `ProtectionPolicy.vue` |

### Savings
| Web surface | `/m` status | Detail |
|-------------|-------------|--------|
| `/savings` | **CONNECTED (read-only)** | `/m/app/savings` → `Savings.vue` |
| `/savings/account/:id` | **CONNECTED (read-only)** | `/m/app/savings/account/:id` → `SavingsAccount.vue` |

### Investment
| Web surface | `/m` status | Detail |
|-------------|-------------|--------|
| `/net-worth/investments` | **CONNECTED (read-only)** | `/m/app/investment` → `Investment.vue` |
| `/risk-profile`, `/risk-profile/levels`, `/risk-profile/factor/:f` | **NOT CONNECTED** | no mobile risk surface |
| Investment analysis sub-views (Monte Carlo, fees, efficient frontier, rebalancing, tax-optimisation, performance, model-portfolio, scenarios) | **NOT CONNECTED** | the 113-route investment analytics suite is **not** on mobile |
| `/net-worth/investments` account detail | **CONNECTED (read-only)** | `/m/app/investment/account/:id` → `InvestmentAccountDetail.vue` |

### Estate & Trusts
| Web surface | `/m` status | Detail |
|-------------|-------------|--------|
| `/estate` | **PARTIAL** | reachable only via generic `/module/estate` placeholder (`/api/v1/mobile/modules/estate`); **no dedicated mobile estate surface** |
| `/estate/inheritance-tax`, `/estate/power-of-attorney`, `/estate/lpa/*`, `/estate/will-builder` | **NOT CONNECTED** | — |
| `/trusts`, `/trusts/:id` | **NOT CONNECTED** | — |

### Goals & Life Events
| Web surface | `/m` status | Detail |
|-------------|-------------|--------|
| `/goals` | **PARTIAL** | reachable only via generic `/module/goals` placeholder (`/api/v1/mobile/modules/goals`); **no dedicated mobile goals surface** |
| Life events / life stage surfaces | **NOT CONNECTED** | — |

### Planning, What-If, Plans, Holistic
| Web surface | `/m` status |
|-------------|-------------|
| `/planning/journeys`, `/planning/what-if*` | **NOT CONNECTED** |
| `/plans`, `/plans/{investment,protection,retirement,estate}`, `/plans/goal/:id` | **NOT CONNECTED** |
| `/holistic-plan` | **NOT CONNECTED** |

## 1.4 Admin surfaces — **ALL NOT CONNECTED** (8 surfaces)
## 1.5 Advisor surfaces — **ALL NOT CONNECTED** (6 surfaces)
## 1.6 Preview surfaces — **N/A** (preview personas are a desktop-demo concept; not part of `/m`)

---

# 2. APIs — connection status superimposed

Each domain from the companion report, annotated with what `/m` consumes.

| API domain (routes) | `/m` status | Connected endpoints |
|---------------------|-------------|---------------------|
| **Auth `/api/auth/*` (41)** | **PARTIAL** | `login`, `verify-code`, `user` only. MFA, password-reset, sessions, GDPR, restore, change-password — **not** consumed by `/m`. |
| **AI Chat `/api/ai-chat/*` (9)** | **PARTIAL (core wired)** | `conversations` (GET/POST), `conversations/{id}/messages` (SSE), `onboarding/start`. Not used: `conversations/{id}` show/delete, `conversations/{id}/action`, `onboarding/status`, `token-usage`. |
| **Mobile `/api/v1/mobile/*` (11)** | **PARTIAL** | `dashboard`, `modules/{module}` wired. `devices`, `insights/daily`, `notifications/preferences`, `share/*`, `auth/refresh-token`, `health` — **API-only (reverse delta)**. |
| **Net Worth `/api/net-worth/*` (6)** | **PARTIAL** | `overview`, `assets-summary-detailed`. Not used: `breakdown`, `assets-summary`, `joint-assets`, `refresh`. |
| **Protection `/api/protection/*` (21)** | **PARTIAL (read-only)** | `GET /api/protection` only. All policy CRUD (life/CI/IP/disability/sickness), `analyze`, `profile`, `recommendations`, `scenarios` — **not** on `/m`. |
| **Savings `/api/savings/*` (10)** | **PARTIAL (read-only)** | `index`, `accounts/{id}` (show). All write CRUD, `analyze`, `isa-allowance`, `recommendations`, `scenarios` — **not** on `/m`. |
| **Retirement `/api/retirement/*` (29)** | **PARTIAL (read-only)** | `index`, `analyze`, `projections`, `dc-pensions/{id}/projections`. The other 25 (DB/DC CRUD, holdings, income, decumulation, strategies, state-pension, etc.) — **not** on `/m`. |
| **Investment `/api/investment/*` (113)** | **PARTIAL (read-only)** | `GET /api/investment` only. The entire 112-route analytics/optimisation/rebalancing/fees/scenarios suite — **not** on `/m`. |
| **Tax `/api/tax*` (~21)** | **PARTIAL** | `GET /api/tax-strategy` only. `tax-strategy/calculate`, `tax-info/*`, `tax-settings/*`, `tax/*`, `tax-year/current`, public tax config — **not** on `/m`. |
| **Estate `/api/estate/*` (54)** | **NOT CONNECTED** | reachable only indirectly via `/api/v1/mobile/modules/estate` (agent analyze summary), no direct estate endpoints on `/m`. |
| **Goals `/api/goals/*` (20) + life-events/life-stage (12)** | **NOT CONNECTED** | reachable only indirectly via `/api/v1/mobile/modules/goals`. |
| **Dashboard `/api/dashboard/*` (4)** | **NOT CONNECTED** | mobile uses `/api/v1/mobile/dashboard` instead of the web `/api/dashboard`. |
| **Household `/api/household/*` (3)** | **NOT CONNECTED** | — |
| **Plans `/api/plans/*` (7)** | **NOT CONNECTED** | — |
| **Holistic `/api/holistic/*` (9)** | **NOT CONNECTED** | — |
| **Recommendations `/api/recommendations/*` (8)** | **NOT CONNECTED** | — |
| **What-If `/api/what-if-scenarios/*` (6)** | **NOT CONNECTED** | — |
| **Journeys `/api/journeys/*` (8)** | **NOT CONNECTED** | — |
| **Onboarding `/api/onboarding/*` (11)** | **NOT CONNECTED** | mobile onboarding goes via AI-chat `onboarding/start`, not the web onboarding API. |
| **User profile `/api/user/*` (26) + users + spouse-permission (5)** | **NOT CONNECTED** | — |
| **Settings/info-guide/notifications (7)** | **NOT CONNECTED** | — |
| **Payments `/api/payment/*` (11) + pricing + webhook** | **NOT CONNECTED** | — |
| **Documents `/api/documents/*` (10)** | **NOT CONNECTED** | — |
| **Properties/Mortgages/Business/Chattels/Liabilities (~31)** | **NOT CONNECTED** | net-worth category view reads the aggregated overview, not these direct endpoints. |
| **Referral `/api/referral/*` (3)** | **NOT CONNECTED** | — |
| **Public APIs (insights/news/contact/bug-report/postcode/occupations/public-tax)** | **NOT CONNECTED** | — |
| **Admin `/api/admin/*` (100)** | **NOT CONNECTED** | — |
| **Advisor `/api/advisor/*` (11)** | **NOT CONNECTED** | — |
| **Preview `/api/preview/*` + eval (6)** | **N/A** | — |

---

# 3. Delta summary — where we are, what's missing, what's next

## 3.1 What is connected today (the "done")

**Modules with a dedicated, working (read-only) mobile surface:**
- Authentication (login + MFA verify)
- Dashboard (aggregated, with embedded Fyn chat + conversational onboarding)
- Net Worth (summary + generic category drill-down)
- Protection (list + policy detail)
- Savings (list + account detail)
- Retirement (list + pension detail, with projections)
- Investment (list + account detail)
- Tax Strategy (dedicated view)

**Cross-cutting that works on `/m`:** Fyn AI chat (read + write-via-handoff), conversational onboarding.

## 3.2 The delta (the "to do")

**A. Modules reachable but with NO dedicated surface (PARTIAL → needs a real screen):**
- **Estate** — only the generic `/module/estate` placeholder + `/api/v1/mobile/modules/estate` summary. Needs: estate dashboard, IHT detail, LPA, will builder.
- **Goals** — only the generic `/module/goals` placeholder + summary. Needs: goals dashboard, goal detail, contributions.

**B. Entire modules with NO mobile presence at all (NOT CONNECTED → needs build):**
- Risk profile, Trusts, Planning/What-If, Journeys, Plans (all 5), Holistic plan, Actions/Recommendations, Valuable Info, Life events.
- The full Investment analytics suite (Monte Carlo, efficient frontier, rebalancing, fees, tax-optimisation, performance, model portfolio, scenarios).

**C. Account & lifecycle surfaces missing (NOT CONNECTED):**
- Register, Checkout, all 9 Settings pages, Profile, Invoices, Subscription/Payments, Documents, Referral, GDPR (export/erasure/consents), MFA management, Session management, Spouse permissions.

**D. Write capability gap (the biggest structural delta):**
- `/m` has **zero CRUD write surfaces**. Every connected module is read-only display. The only write path is Fyn chat. To reach feature parity, each module needs add/edit/delete forms **or** an explicit product decision that all mobile data-entry is Fyn-conversational.

**E. Reverse delta — backend ahead of surface (API-only, wire these up):**
- `/api/v1/mobile/insights/daily` (daily insight feed) — built, not surfaced.
- `/api/v1/mobile/share/{type}/{id}` (share cards) — built, not surfaced.
- `/api/v1/mobile/devices` + `/api/v1/mobile/notifications/preferences` — push/notification infra, native-oriented, not surfaced on web `/m`.
- `/api/v1/auth/refresh-token` — token rotation, not used by web SPA.

**F. Quality/architecture debt flagged in the scaffold's own README:**
- Token in `localStorage` inside the iframe is **scaffold-only**, explicitly "not for production" — needs httpOnly cookie or in-memory+refresh before live.
- All current screens are "disposable placeholders" pending the redesigned mobile design system / component library.

## 3.3 Coverage at a glance

| Layer | Total (web) | Connected to `/m` | Coverage |
|-------|-------------|-------------------|----------|
| API endpoints | 627 | ~18 directly + 7 via generic module endpoint | **~3% direct** |
| Authenticated app modules | ~16 | 7 dedicated + 2 partial (placeholder) | **~44% with some presence; ~9 modules absent** |
| Authenticated app surfaces | ~70 | ~13 mobile surfaces | **~19%** |
| CRUD write surfaces | many | 0 (Fyn-chat handoff only) | **0% form-based** |
| Settings / account / payments | ~15 | 0 | **0%** |
| Admin / Advisor | 14 | 0 | **0%** (likely out of mobile scope) |

---

# 4. Reading the delta as a build backlog (suggested priority)

1. **Hardening (pre-anything-live):** move auth off `localStorage`; replace scaffold screens per the redesign design system.
2. **Estate + Goals real surfaces** — closes the two PARTIAL modules (backend already reachable).
3. **Write pathway decision** — confirm whether mobile data entry is Fyn-only or needs per-module forms; this gates parity for every connected module.
4. **Account/lifecycle surfaces** — Settings, Subscription/Payments, Profile, GDPR, MFA, Sessions (needed for a self-service mobile product).
5. **Wire reverse-delta APIs** — daily insights feed + share cards are cheap wins (backend exists).
6. **Remaining modules** — Risk, Trusts, Planning/What-If, Plans, Holistic, Actions, Investment analytics (largest effort, lowest mobile urgency).

---

*Sources: `resources/mobile/` (router, views, api.js, README), `app/Http/Controllers/Api/V1/Mobile/*`, `app/Services/Mobile/*`, and `php artisan route:list` (707 routes). Companion: `surfaces-and-api-map.md`. Branch `dev` @ 2026-06-05.*
