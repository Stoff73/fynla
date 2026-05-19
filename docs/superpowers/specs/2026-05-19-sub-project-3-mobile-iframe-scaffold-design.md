---
title: Mobile-First Iframe Scaffold (Sub-Project 3)
date: 2026-05-19
sub_project: 3 of 6 (Fynla major-overhaul series)
status: APPROVED — design approved by CSJ 2026-05-19; ready for implementation-plan pass
author: Claude (Opus 4.7) + CSJ
related_specs: 2026-05-14-module-canonical-store-design (SP1), 2026-05-16-sub-project-2-freemium-tier-model-design (SP2)
branch: iFrames
---

# Mobile-First Iframe Scaffold (Sub-Project 3)

## 0. Where this sits in the bigger picture

Fynla is undergoing a major overhaul covering six independent sub-projects:

| # | Sub-project | Status |
|---|-------------|--------|
| 1 | Module canonical store-and-retrieve contract | shipped (pass 1) |
| 2 | Freemium tier model + count caps + Fyn agent metering | in progress |
| **3** | **Mobile-first surface via iframe-framed `/m/*` route** *(this doc)* | drafting |
| 4 | Campaign engine | not started |
| 5 | Track-lightweight onboarding | not started |
| 6 | Gamification | not started |

The canonical-store design doc (SP1) described item 3 in a single line — *"Mobile-first surface via iframe-framed `/m/*` route"* — and §17 of that doc deferred it entirely (*"Mobile-first `/m/*` shell or phone-frame iframe (sub-project 3)"*). This document is the full design for sub-project 3, produced from scratch via a brainstorming pass with CSJ on 2026-05-19.

This document is the design for sub-project 3 only.

---

## 1. Context and motivation

### 1.1 The decision

Fynla's mobile experience will be **rebuilt as a separate, redesigned frontend** — a different UI from the web app, not a responsive reskin of it, and not the existing `resources/js/mobile/` Capacitor code. That redesigned frontend talks to the **same Laravel backend** (same `/api/*`, same Sanctum auth) as everything else.

Sub-project 3 does **not** build that redesigned UI. It builds the **scaffolding**: the iframe seam, the routing, the build pipeline, and the backend wiring, so that the redesign work can happen later, in isolation, without entangling with or destabilising the existing web app.

### 1.2 Why an iframe

The iframe is deliberate and is the load-bearing architectural decision. It is the **decoupling boundary**: a genuinely separate frontend codebase lives behind `/m/*`, runs in its own document, and is developed in isolation from the existing Vue web app, while still reaching the same backend on the same origin. The iframe is for codebase/runtime isolation, **not** for a visual phone mockup.

### 1.3 What desktop sees

Nothing changes for desktop. Desktop and tablet visitors get the **existing web app, byte-for-byte unchanged**. The new mobile surface is shown **only on phones** (mobile web *and* native).

### 1.4 The trigger to act now

SP1 (canonical store) is shipped for its first pass and SP2 (freemium) is in progress. The canonical-store doc §1.3 named the mobile-first overhaul as a converging pressure and §19 committed that *"sub-project 3 consumes the same stores as desktop"* — i.e. no backend fork. Standing up the scaffold now lets the redesign proceed as an isolated future workstream on a clean seam.

---

## 2. Goals and non-goals

### 2.1 Goals

- A **new, isolated frontend codebase** at `resources/mobile/` with its own Vite build, its own entry, its own (future) design system, and **zero shared components** with the existing web app.
- A **same-origin iframe seam**: a `/m` host page that embeds the new SPA via `<iframe>`, so the new frontend runs in its own document but shares the origin (and therefore the Sanctum session).
- **Phone-only device routing**: phones (web + native) → new mobile surface; desktop/tablet → existing web app, unchanged.
- **Provable backend connectivity**: the scaffold ships functional-but-disposable placeholder screens — login, email verification, and a dashboard placeholder rendering real logged-in user data — so the seam is demonstrably real.
- **Retire the legacy mobile code** (`resources/js/mobile/`, legacy `/m/*` Vue routes, `platform.isNative()` guard) and **repoint Capacitor** at the new frontend.
- **Deploy wiring** for both environments and the Capacitor build.

### 2.2 Non-goals

- The redesigned mobile UI, mobile design system, or component library. The scaffold's screens are explicitly throwaway.
- Any backend or `/api/*` change. SP3 consumes the existing endpoints and existing Sanctum auth unchanged.
- Feature parity with the old mobile app.
- Native (Capacitor) token/biometric auth. SP3's working auth proof is mobile web; native auth is a documented, accepted limitation deferred to the future redesign.
- Deep-link / browser-back postMessage bridging inside the iframe (added later only if a concrete need appears).
- Tablet support of the new surface (tablets stay on the web app).

### 2.3 Definition of done — sub-project level

SP3 is complete when all acceptance criteria in §10 hold and the work is browser-tested end-to-end on csjones per the Fynla browser-testing law.

---

## 3. Scope

### 3.1 In scope — SP3 delivers

1. New isolated Vue 3 + Vite frontend at `resources/mobile/` (own `main.js`, own `vite.mobile.config.js`, own minimal store/router, own bootstrap).
2. Laravel `GET /m` host route → thin Blade (`resources/views/mobile-host.blade.php`) rendering the device-frame chrome + a same-origin `<iframe src="/m/app">`.
3. Laravel `GET /m/app/{any?}` route → Blade that boots the new mobile SPA from `public/m-build/`.
4. Scaffold screens inside the iframe: **Login → Verify (email code) → Dashboard placeholder**, all calling existing `/api/*`, authenticating via the existing Sanctum session cookie.
5. Server-side phone device detection (Laravel middleware) + client-side backstop; `?full=1` override cookie.
6. Retirement of legacy `resources/js/mobile/`, legacy `/m/*` Vue routes, and the `platform.isNative()` guard.
7. Capacitor repoint: `deploy/mobile/build-ios.sh` and Capacitor `webDir`/entry build and load the new frontend.
8. Second Vite build wired into `deploy/fynla-org/build.sh` and `deploy/csjones-fynla/build.sh` with correct per-env base paths.
9. `resources/mobile/README.md` documenting the seam, the auth model, the deferred items, and what the future redesign team owns.

### 3.2 Out of scope — deferred to the future redesign

- Redesigned screens, mobile design system, component library.
- Backend/API changes.
- Native token/biometric auth.
- Feature parity.
- Deep-link / back-button iframe bridging.

### 3.3 Stack decision

The new mobile frontend uses **Vue 3 + Vite** — the same toolchain as the rest of Fynla (reuses known axios/Sanctum/build/CI patterns) but a **brand-new component tree**. Not React or another framework: a second toolchain buys nothing when the backend is unchanged. (Confirmed by CSJ 2026-05-19.)

---

## 4. Architectural principles

1. **One origin, one backend, one Sanctum session.** The iframe is same-origin; auth, cookies, and CSRF behave normally inside it. No token handoff, no CORS, no postMessage for auth.
2. **Hard frontend isolation.** The new frontend shares zero components, stores, or build config with the existing web app. Enforced by directory boundary and a build/arch check.
3. **Backend is frozen.** SP3 changes no `/api/*`, no controller, no model, no Sanctum config. Consistent with canonical-store doc §19.
4. **Desktop is frozen.** The existing web app is byte-for-byte unchanged for desktop/tablet. SP3 is additive plus the explicit legacy-mobile retirement.
5. **Scaffold is disposable.** The placeholder screens exist to prove the seam, not to be kept. The future redesign replaces them wholesale inside `resources/mobile/`.

If a later decision contradicts one of these, the principle wins.

---

## 5. Architecture and data flow

### 5.1 Request routing

```
Visitor → Laravel
  ├─ Desktop / tablet UA ............→ existing web SPA  (UNCHANGED)
  └─ Phone UA (web) / native ........→ GET /m  (host)
         /m  → Blade host: device-frame chrome + <iframe src="/m/app">
                 /m/app/{any?} → Blade boots NEW mobile SPA (public/m-build/)
                     Login → Verify → Dashboard placeholder
                       └─ all call existing /api/*
                       └─ auth = existing Sanctum session cookie (same origin)
```

### 5.2 The iframe seam

- `/m` host page is a thin Blade with effectively no JS — it renders the frame chrome and the `<iframe>`.
- `/m/app/{any?}` serves the new SPA shell (assets from `public/m-build/`). The SPA owns its own internal routing under the `/m/app/` base.
- The iframe is same-origin, so the Sanctum session cookie set by the login POST is automatically valid inside it.

### 5.3 Auth flow (mobile web)

1. New SPA loads in the iframe; if unauthenticated, shows its own Login screen.
2. SPA fetches the Sanctum CSRF cookie (`/sanctum/csrf-cookie`).
3. Login POST → existing auth endpoint → email-verification code → existing verify endpoint.
4. Sanctum session cookie set **same-origin** → valid in the iframe → SPA fetches `/api/*` (e.g. `/api/user`, existing dashboard endpoint) and renders the dashboard placeholder with real data.

No new auth routes are introduced, so `PreviewWriteInterceptor` `EXCLUDED_ROUTES` (CLAUDE.md Rule #8) needs no change.

### 5.4 Framing headers

The `/m/app` response must permit same-origin framing: `X-Frame-Options: SAMEORIGIN` (or CSP `frame-ancestors 'self'`), scoped to `/m*` only. SP3 audits the current global header config in its first PR and scopes the framing allowance narrowly.

### 5.5 Dashboard placeholder data source

The placeholder dashboard calls an **existing** dashboard endpoint (reusing `MobileDashboardAggregator` or the web dashboard API — implementation choice, no API change). Only its presentation is throwaway; the data is real.

---

## 6. Build and deployment

### 6.1 Build outputs

- Existing web build → `public/build/` (unchanged).
- New mobile build → **`public/m-build/`** (new; gitignored; manual upload per CLAUDE.md Rule #1, exactly like `public/build/`).
- Config: `vite.mobile.config.js`, entry `resources/mobile/main.js`, isolated `outDir` and `base`.

### 6.2 Per-environment wiring

| Setting | fynla.org (main) | csjones.co/fynla (dev) |
|---|---|---|
| Mobile asset base | `/m-build/` | `/fynla/m-build/` |
| Mobile router base | `/m/app/` | `/fynla/m/app/` |
| API base | `https://fynla.org` | `https://csjones.co/fynla` |

- `deploy/fynla-org/build.sh` and `deploy/csjones-fynla/build.sh` each gain a mobile-build step (after the web build; failure isolated, non-blocking to the web build) with the right env vars.
- `vite.mobile.config.js` **inherits the CLAUDE.md iOS-safety rules verbatim**: no `external` for image/asset paths in `rollupOptions`; `transformAssetUrls: false` in the `vue()` plugin; PWA conditionally disabled. A build check greps the built JS for image imports to catch the documented iOS blank-screen / MIME failure.

### 6.3 Capacitor repoint

- `deploy/mobile/build-ios.sh` builds the **new** frontend (`vite.mobile.config.js`) and runs `npx cap sync ios` against it.
- Capacitor `webDir`/served entry points at the new build; native loads `/m` → the same iframe app as mobile web.
- Native auth is **not** asserted (see §7) — accepted because iOS is not a live production concern.

### 6.4 Laravel routes

- `GET /m` and `GET /m/app/{any?}` registered in `routes/web.php` **above** the existing SPA catch-all.
- Legacy Vue `/m/*` routes are removed in the same change, so there is no client/server `/m` ambiguity.

---

## 7. Device detection and legacy retirement

### 7.1 Device detection

- **Server-side Laravel middleware** on web entry points: phone UA → 302 to `/m`, before the heavy SPA loads (no desktop-SPA flash).
- **Phones only.** Tablets and desktop → existing web app. Detection is UA-based (phone form factor), not viewport.
- **`?full=1` override:** sets a cookie pinning the visitor to the desktop web app (escape hatch for a phone user who wants the full site); reversible via `/m`.
- **Excluded from redirect:** `/api/*`, admin, advisor, auth callbacks, webhooks.
- **Client-side backstop** in the existing app handles edge cases the server check misses.

### 7.2 Legacy retirement (SP3 performs this)

- **Delete** `resources/js/mobile/` (old mobile Vue tree) and its Vuex modules (`mobileDashboard.js`, `mobileNotifications.js`).
- **Remove** legacy `/m/login`, `/m/verify`, `/m/biometric-setup`, `/m`, `/m/home` routes and the `platform.isNative()` guard block from `resources/js/router/index.js`.
- **Keep** all backend untouched — `MobileDashboardAggregator` and dashboard APIs stay.
- Before deletion: grep for cross-imports from the web app into `resources/js/mobile/`; run full Pest + web build to confirm no orphaned imports.

### 7.3 Known limitation — native auth (documented, not solved in SP3)

In native Capacitor the origin is `capacitor://localhost`, so the same-origin Sanctum cookie does not cross into native. SP3's *working* auth proof is **mobile web**. Native token/biometric auth is deferred to the future redesign — consistent with CSJ's decision that iOS is not a live concern and the scaffold is acceptable for native. The Capacitor repoint in SP3 is the build/`webDir` switch so native loads the new scaffold UI; full native auth is future work.

---

## 8. Risks and mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Server-side UA detection misroutes | Medium | Medium | `?full=1` override; client backstop; excluded-path allowlist; log misroutes |
| Deleting `resources/js/mobile/` breaks an unnoticed web import | Low | High | Grep cross-imports before delete; full Pest + build run; tree is namespaced/isolated |
| Global `X-Frame-Options: DENY` blocks same-origin framing | Medium | High | Audit header config in PR 1; scope `SAMEORIGIN`/`frame-ancestors 'self'` to `/m*` only |
| Second Vite build slows/breaks deploy scripts | Low | Medium | Independent `outDir`; mobile step after web build; isolated failure |
| iOS blank screen from mobile Vite config | Medium | High | Inherit CLAUDE.md iOS rules verbatim; build-time grep of built JS for image imports |
| Native Capacitor scaffold has no working auth | High (expected) | Low | Explicitly accepted — iOS not live; documented as deferred, not a regression |

---

## 9. Testing

Per the Fynla browser-testing law — Playwright, on csjones, before merge to main:

1. Desktop UA → existing web app loads unchanged (visual + a smoke route).
2. Phone UA → redirected to `/m`; device frame + iframe render.
3. Inside the iframe: fill login → fetch verification code from DB → enter → land on dashboard placeholder showing **real** logged-in user data from `/api/*`.
4. `?full=1` on a phone UA → pinned to desktop web app.
5. Excluded paths (`/api/*`, admin) not redirected under phone UA.
6. `public/m-build/` produced by both env build scripts with correct base paths.
7. Capacitor build (`build-ios.sh`) compiles the new frontend and `cap sync` succeeds (loads scaffold; native auth not asserted — documented limitation).
8. Pest suite green; no orphaned imports after `resources/js/mobile/` deletion.

---

## 10. Acceptance criteria

SP3 is done when:

1. Desktop/tablet experience is byte-for-byte unchanged (no regression).
2. A phone web visitor is routed to `/m`, sees the framed new SPA, and can **log in and see live dashboard data** through the new isolated frontend on the existing backend.
3. The new frontend lives entirely in `resources/mobile/` with its own Vite build → `public/m-build/`, **zero shared components** with the web app; an arch/build check confirms isolation.
4. Legacy `resources/js/mobile/` + legacy `/m/*` Vue routes + `platform.isNative()` guard removed; no backend/API changes.
5. Both env build scripts + the Capacitor build produce the new bundle correctly.
6. Browser-tested end-to-end on csjones per §9.
7. Spec doc + `resources/mobile/README.md` (seam, auth model, deferred items, future-team ownership) shipped.

---

## 11. Out of scope (explicit)

- Redesigned mobile UI / design system / component library (future redesign).
- Any backend or `/api/*` change.
- Native token/biometric auth.
- Feature parity with the old mobile app.
- Deep-link / browser-back postMessage bridging inside the iframe.
- Tablet support of the new surface.
- Anything owned by SP1, SP2, SP4–6.

---

## 12. Dependencies on other sub-projects

| Relationship | Detail |
|---|---|
| SP1 (canonical store) | None blocking. SP3 reads existing `/api/*` which sits on SP1's stores; no coupling to SP1 internals. |
| SP2 (freemium) | None blocking. Tier gating is backend-side; the new frontend inherits it through the same APIs. |
| Future mobile redesign | SP3 produces the seam this work plugs into. The redesign replaces the scaffold screens inside `resources/mobile/`. |

---

## 13. Sign-off

Design approved by CSJ on 2026-05-19 via brainstorming pass (visual companion). Core decisions: Approach 1 (same-origin, same-repo, separate Vite build, iframe seam); new frontend replaces legacy mobile code; Capacitor repointed in SP3 (iOS not live, scaffold acceptable); scaffold ships functional placeholder login/verify/dashboard screens; Vue 3 + Vite stack with a brand-new component tree.

The next step is to invoke the `superpowers:writing-plans` skill to produce the implementation plan for SP3.

---

*End of design document.*
