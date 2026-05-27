# Fynla Mobile (SP3 iFrame Scaffold) — Designer Brief

**Audience:** designers building the redesigned mobile screens / flows.
**Status of code:** disposable placeholder scaffold. The Vue screens and the new
HTML landing page are intentionally replaceable wholesale. Backend, routing,
auth, and the mobile API surface are real and stable — design against them.
**Last updated:** 2026-05-27.

---

## 1. The shape of mobile in one picture

```
                            ┌─────────────────────────────────────┐
   GET /m  ────────────────►│  resources/views/mobile-host.blade  │
   (phone-UA visitors are   │  Outer chrome: full-screen iframe   │
    auto-redirected here)   │  iframe src = /m/landing            │
                            └────────────────┬────────────────────┘
                                             │
                                             ▼
                            ┌─────────────────────────────────────┐
   GET /m/landing  ────────►│  resources/views/mobile-landing     │
   (static HTML — designer  │  Static HTML, no Vue, no API call.  │
    placeholder)            │  CTA "Get started" → /m/app         │
                            └────────────────┬────────────────────┘
                                             │
                                             ▼
                            ┌─────────────────────────────────────┐
   GET /m/app/{any}  ──────►│  resources/views/mobile-app.blade   │
                            │  Boots isolated Vue SPA from        │
                            │  public/m-build/manifest.json       │
                            │                                     │
                            │  Inner Vue router:                  │
                            │    /          → redirect /login     │
                            │    /login     → views/Login.vue     │
                            │    /verify    → views/Verify.vue    │
                            │    /dashboard → views/Dashboard.vue │
                            │    /module/:slug → ModuleDetail.vue │
                            └─────────────────────────────────────┘
```

Two things to internalise:

1. **The "iFrames" are real iframes.** `/m` is a Blade page whose only job is
   to render a same-origin `<iframe>`. The iframe is what holds the mobile
   surface. Everything the user sees on mobile is the contents of that iframe.
2. **The mobile frontend is fully isolated from the desktop SPA.** Different
   source root (`resources/mobile/`), different Vite build
   (`vite.mobile.config.js`), different output (`public/m-build/`), zero shared
   components. Designers can replace its HTML / CSS / Vue without touching the
   desktop app and vice versa.

---

## 2. Routes (server-side)

Declared in `routes/web.php`, in this order, **before** the SPA catch-all:

| Method + Path        | Returns                                | Purpose                                       |
|----------------------|----------------------------------------|-----------------------------------------------|
| `GET /m`             | `resources/views/mobile-host.blade.php`| Outer iframe shell (phone-frame host)         |
| `GET /m/landing`     | `resources/views/mobile-landing.blade.php` | **HTML landing placeholder** (replace me)|
| `GET /m/app/{any?}`  | `resources/views/mobile-app.blade.php` | Mobile Vue SPA + manifest loader              |

**Phone-UA redirect:** `App\Http\Middleware\RedirectPhoneToMobile` 302s phone
visitors hitting `/` (and every other top-level HTML route) to `/m`. The escape
hatch is `?full=1` which sets the `m_full_site` cookie for a year. Tablets and
desktop are untouched.

**Frame headers:** `App\Http\Middleware\SecurityHeaders` sets
`X-Frame-Options: SAMEORIGIN` + `frame-ancestors 'self'` **only** on
`/m`, `/m/landing`, `/m/app`, `/m/app/`, `/m/app/*`. Every other route stays
`DENY`. If you add a new framed route, update the `$isMobileFramed` check.

---

## 3. File map — what to touch, what to leave alone

```
fynla/
├── resources/
│   ├── views/
│   │   ├── mobile-host.blade.php        ◄── outer iframe shell (rarely touched)
│   │   ├── mobile-landing.blade.php     ◄── REPLACE WHOLESALE with new HTML
│   │   └── mobile-app.blade.php         ◄── Vue SPA boot (rarely touched)
│   │
│   └── mobile/                          ◄── isolated mobile frontend
│       ├── main.js                      ◄── SPA entry
│       ├── App.vue                      ◄── root component
│       ├── router.js                    ◄── inner Vue router (5 routes)
│       ├── store.js                     ◄── auth/token state
│       ├── api.js                       ◄── apiGet / apiPost helpers
│       ├── style.css                    ◄── REPLACE WHOLESALE with mobile DS
│       ├── README.md                    ◄── scaffold scope + caveats
│       └── views/
│           ├── Login.vue                ◄── REDESIGN
│           ├── Verify.vue               ◄── REDESIGN
│           ├── Dashboard.vue            ◄── REDESIGN
│           └── ModuleDetail.vue         ◄── REDESIGN
│
├── routes/
│   └── web.php                          (mobile routes lines 81-92)
│
├── app/Http/Middleware/
│   ├── RedirectPhoneToMobile.php        (UA detection + /m redirect)
│   └── SecurityHeaders.php              (frame-ancestors allowlist)
│
├── public/
│   └── m-build/                         ◄── Vite build output (gitignored)
│       ├── manifest.json
│       └── assets/...
│
├── vite.mobile.config.js                ◄── isolated mobile build pipeline
├── package.json                         (script: `npm run build:mobile`)
└── docs/mobile/
    └── designer-brief.md                (this file)
```

**Designer-owned files:**

- `resources/views/mobile-landing.blade.php` — drop the new HTML in here.
- `resources/mobile/style.css` — replace with whatever the mobile design system
  becomes. The current CSS is throwaway.
- `resources/mobile/views/*.vue` — markup + scoped styles. Keep the script
  block's API calls intact (or move equivalent calls to a composable).

**Engineering-owned files** (don't change without a PR conversation):

- `routes/web.php`, both middleware files, `vite.mobile.config.js`,
  `mobile-host.blade.php`, `mobile-app.blade.php`, `main.js`, `router.js`,
  `store.js`, `api.js`.

---

## 4. The HTML landing placeholder

`resources/views/mobile-landing.blade.php` is a single-file static HTML page
with **no Vite dependency** (inline `<style>`, inline content). It's
deliberately self-contained so designers can replace it wholesale with the
finalised HTML when it's ready, without touching the build pipeline.

**Today's placeholder contents:**

- Fynla wordmark
- Headline: "Your financial life, in one place."
- Lede: short value-prop sentence
- Primary CTA: "Get started" → `/m/app` (boots the Vue SPA, which currently
  redirects to `/login`)
- Secondary link: "I already have an account" → `/m/app/login`
- Placeholder strip at the bottom

**Design system values inlined** (from `fynlaDesignGuide.md` v1.2.0):

| Token         | Value     |
|---------------|-----------|
| Eggshell (bg) | `#F7F6F4` |
| Horizon 500   | `#1F2A44` |
| Horizon 300   | `#4F5B75` |
| Raspberry 500 | `#C84268` |
| Raspberry 600 | `#A93257` |
| Savannah 200  | `#E8E3D8` |

Fonts: `Segoe UI, 'Inter', -apple-system, sans-serif` (matches the rest of
Fynla). Safe-area insets respected via `env(safe-area-inset-top/bottom)`.

**To replace:** just overwrite the file. The CTA must continue to navigate the
iframe to `/m/app` (or, if you want to skip directly to login, `/m/app/login`).
Anchor `href`s navigate the iframe contents, which is exactly the behaviour we
want.

---

## 5. Mobile SPA screens (current state)

All four Vue screens live in `resources/mobile/views/`. Treat them as
"behavioural spec, not visual spec" — the markup and CSS are placeholders.

### 5.1 `Login.vue`  →  route `/m/app/login`
Behaviour: 2-field form (email, password) → `POST /api/auth/login`. If the API
responds `requires_verification: true`, store `challenge_token` + masked email
and route to `/verify`.

### 5.2 `Verify.vue`  →  route `/m/app/verify`
Behaviour: 6-digit numeric input → `POST /api/auth/verify-code` with the
challenge token. On success, store the `access_token` in `localStorage`
(`m_scaffold_token`) and route to `/dashboard`. Guard: routes back to `/login`
if there's no challenge token in state.

### 5.3 `Dashboard.vue`  →  route `/m/app/dashboard`  *(auth required)*
Behaviour: on mount, calls `GET /api/v1/mobile/dashboard`. Renders:

- **Welcome card** — greeting + today's date + sign-out button.
- **Net worth card** — hero metric + optional `fyn_insight` string.
- **Module list** — six router-link cards for `protection`, `savings`,
  `investment`, `retirement`, `estate`, `goals`. Each shows a short label,
  status text, and a single metric.

### 5.4 `ModuleDetail.vue`  →  route `/m/app/module/:slug`  *(auth required)*
Behaviour: calls `GET /api/v1/mobile/modules/{slug}`. Renders:

- **Header card** — back button + module title + subtitle.
- **Hero card** — module-specific hero metric (e.g. Protection shows total
  cover, Savings shows emergency-fund runway, Estate shows IHT liability).
- **Detail rows** — curated list of summary fields, formatted as
  currency / percent / months / count based on the key name.
- **Placeholder strip** — "Scaffold — full redesign pending" tag.

**Rule #16 reminder:** **no icons on dashboard cards or detail views.** The
side-nav is the only surface where icons are functional. See `CLAUDE.md` Rule
#16 — applies in full to the redesigned mobile screens.

---

## 6. Auth flow (real, don't change without a PR conversation)

```
[Login.vue]
    │  email + password
    ▼
POST /api/auth/login
    │  { success: true, requires_verification: true,
    │    data: { challenge_token: "...", email: "j***@example.com" } }
    ▼
[Verify.vue]
    │  6-digit code + challenge_token
    ▼
POST /api/auth/verify-code   { code, type: 'login', challenge_token }
    │  { success: true,
    │    data: { access_token: "sanctum-token", user: {...} } }
    ▼
[store.setToken(...)]   ──►  localStorage['m_scaffold_token']
    │
    ▼
[Dashboard.vue]
    GET /api/v1/mobile/dashboard  with `Authorization: Bearer <token>`
```

**Scaffold-only risk:** token sits in `localStorage` inside a same-origin
iframe. Acceptable for the placeholder. **Not acceptable for production** —
the redesign should move to httpOnly cookie or in-memory + refresh before
shipping to live customers.

---

## 7. Mobile API surface (design against this)

All authenticated routes require `Authorization: Bearer {access_token}`. All
responses follow the project envelope `{ success, message, data }`. Listed in
`routes/api_v1.php`.

| Method | Path                                            | Purpose                            |
|--------|-------------------------------------------------|------------------------------------|
| POST   | `/api/auth/login`                               | Email + password → MFA challenge   |
| POST   | `/api/auth/verify-code`                         | 6-digit code → access token        |
| GET    | `/api/v1/mobile/dashboard`                      | Home overview (net worth + modules)|
| GET    | `/api/v1/mobile/modules/{module}`               | Per-module summary drill-down      |
| GET    | `/api/v1/mobile/insights/daily`                 | Fyn's daily insight string         |
| GET    | `/api/v1/mobile/devices`                        | List registered push devices       |
| POST   | `/api/v1/mobile/devices`                        | Register a push device             |
| DELETE | `/api/v1/mobile/devices/{deviceId}`             | De-register a push device          |
| GET    | `/api/v1/mobile/notifications/preferences`      | Read push notification preferences |
| PUT    | `/api/v1/mobile/notifications/preferences`      | Update push notification preferences|
| GET    | `/api/v1/mobile/share/{type}/{id?}`             | Shareable artefact (deeplink)      |

Controllers live in `app/Http/Controllers/Api/V1/Mobile/`.

### 7.1 `GET /api/v1/mobile/dashboard` — response shape

```json
{
  "success": true,
  "data": {
    "net_worth": { "total": 482300 },
    "fyn_insight": "You're £12k ahead of your 3-month emergency-fund target.",
    "modules": {
      "protection":  { "status": "ok", "total_coverage": 350000, "critical_gaps": 0 },
      "savings":     { "status": "ok", "emergency_fund_months": 4.2, "emergency_fund_status": "on_track" },
      "investment":  { "status": "ok", "portfolio_value": 142500 },
      "retirement":  { "status": "ok", "income_gap": 4200 },
      "estate":      { "status": "ok", "iht_liability": 28500 },
      "goals":       { "status": "ok", "total_goals": 5, "completed_goals": 2 }
    }
  }
}
```

A module can return `"status": "unavailable"` if the user has no data for it
(e.g. estate module before they've added any assets). The UI must handle that
gracefully — see `Dashboard.vue:60` for the current "Not available" fallback.

### 7.2 `GET /api/v1/mobile/modules/{module}` — response shape

`{module}` ∈ `{ protection, savings, investment, retirement, estate, goals }`.

```json
{
  "success": true,
  "data": {
    "summary": {
      // per-module fields — see Dashboard.vue MODULE_META + ModuleDetail.vue MODULE_CONFIG
      "total_coverage": 350000,
      "total_life_cover": 250000,
      "total_critical_illness_cover": 100000,
      "total_income_protection": 0,
      "policy_count": 3,
      "critical_gaps": 0,
      "status": "ok"
    }
  }
}
```

The exact field set varies per module. The current `ModuleDetail.vue`
documents the curated field list for each module in its `MODULE_CONFIG`
constant — that's the authoritative list of what the API surfaces today.

**Rate limiting:** `etag` + `throttle:mobile-dashboard` middleware on both
dashboard endpoints. Responses are ETag-cached, so a 304 is normal on
repeated polls.

---

## 8. Build, dev, and how to preview your work

### 8.1 Local
```bash
# from repo root
./dev.sh                   # Laravel + main desktop Vite — keep running
npm run build:mobile       # Builds public/m-build/ once
```
Then visit **http://localhost:8000/m** in a mobile-shaped browser viewport
(Chrome DevTools → Toggle device toolbar → iPhone 14 Pro is a good default).

The mobile build is **not** served by the dev Vite server — it's read from
`public/m-build/manifest.json` directly by `mobile-app.blade.php`. So after any
change to `resources/mobile/*` or `resources/views/mobile-*.blade.php`, you
re-run `npm run build:mobile` (a couple of seconds).

### 8.2 Static landing-only previews
If you're only iterating on `mobile-landing.blade.php`, you don't need to
rebuild the SPA — just refresh `/m` in the browser.

### 8.3 Per-env builds
Engineering ships these via `./deploy/fynla-org/build.sh` (prod) and
`./deploy/csjones-fynla/build.sh` (dev / staging). They set `VITE_MOBILE_BASE_PATH`
and `VITE_API_BASE_URL` correctly for each environment. Designers don't need
to run these.

### 8.4 Capacitor iOS
`./deploy/mobile/build-ios.sh` builds + `npx cap sync ios`. The iOS app
loads `/m` directly via its `webDir`. Hard rules carried over verbatim from
the root `CLAUDE.md` (already enforced in `vite.mobile.config.js`):

- **No `external` for image/asset paths** in `rollupOptions` (WKWebView MIME).
- `transformAssetUrls: false` in the `vue()` plugin.
- No PWA / service worker in this build.

---

## 9. What's wired vs what's deferred

| Capability                                       | Status                             |
|--------------------------------------------------|------------------------------------|
| Phone-UA redirect to `/m`                        | **Wired** (production)             |
| Iframe shell @ `/m`                              | **Wired**                          |
| HTML landing placeholder @ `/m/landing`          | **Wired** (this brief adds it)     |
| Login + MFA verify against real backend          | **Wired**                          |
| Dashboard reading `/api/v1/mobile/dashboard`     | **Wired**                          |
| Module drill-down reading `/api/v1/mobile/modules/{slug}` | **Wired**                  |
| Mobile design system / component library         | **Deferred** — design output       |
| Redesigned screens                               | **Deferred** — design output       |
| Native Capacitor auth (cookie / biometric)       | **Deferred** — post-redesign work  |
| Deep-link / browser-back postMessage bridging    | **Deferred** — post-redesign work  |
| Feature parity with retired `resources/js/mobile/` | **Deferred** — post-redesign work|
| Production-grade token storage (not localStorage)| **Deferred** — must land before live customers |

---

## 10. Design rules that bind the mobile redesign

These come from the root `CLAUDE.md` and `fynlaDesignGuide.md` v1.2.0.

1. **Design system v1.2.0 is the only source of truth.** Use `raspberry-*`,
   `horizon-*`, `spring-*`, `violet-*`, `savannah-*`, `eggshell-*`. No amber,
   no orange, no hex outside the palette.
2. **Icons are functional only (Rule #16).** Side-nav icons are allowed
   because they identify nav items when collapsed. **Dashboard cards, module
   cards, detail views, Fyn chat — all banned.** No emoji, no Unicode glyphs,
   no icon fonts on these surfaces. Rule is forward-only: don't strip existing
   violations during a redesign, but new work must comply strictly.
3. **No scores in user-facing UI (Rule #13).** No "75/100", no portfolio
   health score. Use descriptive text and specific metrics.
4. **No acronyms in user-facing text (Rule #10).** Spell out "Annual
   Allowance", "Stocks & Shares", "Defined Benefit". Only ISA may remain
   abbreviated.
5. **British spelling in user-facing copy** (Optimisation, Customise).
   American spelling in code identifiers (`optimize`, `center`).
6. **Currency: `Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 })`.** Used in both `Dashboard.vue` and `ModuleDetail.vue`.

---

## 11. Where this brief lives

- **Repo:** `docs/mobile/designer-brief.md` (this file)
- **Vault:** `fynlaBrain/Design/MobileScaffold-DesignerBrief.md` (mirror,
  Obsidian-friendly with wikilinks)

When the redesign lands, update both. The Obsidian copy is the discovery
surface for designers using the vault; the repo copy is the source of truth
for engineering.

---

## 12. Useful links

- `resources/mobile/README.md` — scaffold scope, auth caveats, deferred items
- `fynlaBrain/Design/fynlaDesignGuide.md` — design system v1.2.0
- `CLAUDE.md` Rule #11 (design system), Rule #13 (no scores), Rule #16 (icons)
- Commits worth reading for context:
  - `3783043 docs(sp3): mobile-first iframe scaffold design spec`
  - `f9626d5 docs(sp3): mobile-first iframe scaffold implementation plan`
  - `8fbe67f feat(sp3): scaffold Login/Verify/Dashboard screens`
  - `5f4464d feat(sp3): wire mobile build into both env deploy scripts`
  - `738715c feat(mobile): real placeholder dashboard + wired module drill-downs`
