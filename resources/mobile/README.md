# Fynla Mobile (SP3 scaffold)

This is the **isolated mobile frontend**, separate from the desktop web app
(`resources/js/`). It has its own Vite build (`vite.mobile.config.js` →
`public/m-build/`) and shares **zero components** with the web app.

## How it is served

- `GET /m` → `resources/views/mobile-host.blade.php` — device-frame host,
  embeds `<iframe src="/m/app">` (same origin).
- `GET /m/app/{any?}` → `resources/views/mobile-app.blade.php` — boots this SPA.
- Phone user-agents are 302-redirected to `/m` by
  `App\Http\Middleware\RedirectPhoneToMobile`. `?full=1` pins to the full web
  app via the `m_full_site` cookie (1 year).

## Auth

Bearer-token against the **existing** backend, unchanged:
`POST /api/auth/login` → `POST /api/auth/verify-code` → `data.access_token`,
stored in `localStorage` (`m_scaffold_token`) and sent as `Authorization: Bearer`.

> **Scaffold-only risk note:** the token sits in `localStorage` inside the
> same-origin iframe. Acceptable for a disposable placeholder, **not** for
> production. The redesign should move to a more robust storage mechanism
> (httpOnly cookie or in-memory + refresh) before this surface ships to live
> customers.

## Status: SCAFFOLD ONLY

The screens (`views/Login.vue`, `Verify.vue`, `Dashboard.vue`) are **disposable
placeholders** that prove the seam works (real login, real data). The redesign
replaces them wholesale inside this directory.

## Deferred (NOT in SP3)

- Redesigned UI / mobile design system / component library.
- Native Capacitor auth: in native the origin is `capacitor://localhost`, so
  cookie/session continuity does not apply; the scaffold's working auth proof
  is **mobile web**. Native token/biometric auth is future redesign work. iOS
  is not a live production concern (CSJ decision, 2026-05-19).
- Feature parity with the retired `resources/js/mobile/` app.
- Deep-link / browser-back postMessage bridging across the iframe.

## Build

- Local: `npm run build:mobile`
- Per-env: handled by `deploy/fynla-org/build.sh` and
  `deploy/csjones-fynla/build.sh` (sets `VITE_MOBILE_BASE_PATH`).
- iOS: `deploy/mobile/build-ios.sh`.

## Frame headers

`/m`, `/m/app`, `/m/app/`, and `/m/app/*` all set `X-Frame-Options: SAMEORIGIN`
+ `Content-Security-Policy: frame-ancestors 'self'` (see
`app/Http/Middleware/SecurityHeaders.php`). Every other route on the host stays
locked at `DENY` with no `frame-ancestors`.
