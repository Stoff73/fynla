# Mobile-First Iframe Scaffold (SP3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the scaffolding for a separate, redesigned mobile frontend behind a same-origin iframe seam — build pipeline, `/m` host + iframe, device routing, frame headers, auth-proof placeholder screens, Capacitor repoint, deploy wiring, legacy mobile retirement — without changing the backend or the desktop web app.

**Architecture:** A second, isolated Vite build (`resources/mobile/` → `public/m-build/`, zero shared components) served by Laravel at `GET /m` (Blade host with a same-origin `<iframe src="/m/app">`) and `GET /m/app/{any?}` (boots the new SPA). Phone user-agents are 302-redirected to `/m` by a new middleware (desktop/tablet untouched). The new SPA does its own Bearer-token login against the existing `/api/auth/*` endpoints and renders a placeholder dashboard from `GET /api/v1/mobile/dashboard`. Legacy `resources/js/mobile/` + `/m/*` Vue routes are removed; Capacitor is repointed at the new build.

**Tech Stack:** Laravel 10 (Blade, middleware, Pest), Vue 3 + Vite (new isolated build), Sanctum Bearer-token auth (existing, unchanged), Capacitor iOS.

**Spec:** `docs/superpowers/specs/2026-05-19-sub-project-3-mobile-iframe-scaffold-design.md`

**Branch:** `iFrames` (already created off `origin/dev`).

> **Spec correction baked into this plan:** Spec §5.3 describes auth as a "Sanctum session cookie set same-origin". The codebase reality (verified in `app/Http/Controllers/Api/AuthController.php` + `app/Http/CLAUDE.md`) is **Bearer-token** auth: `POST /api/auth/login` → `POST /api/auth/verify-code` → `data.access_token`. This plan implements the token flow. Task 9 corrects the spec wording.

---

## Exact backend contracts (verified, do not re-derive)

**Login:** `POST /api/auth/login` body `{ "email": string, "password": string }`
→ `200 { "success": true, "requires_verification": true, "data": { "challenge_token": "<64-char>", "email": "<masked>" } }` and emails a 6-digit code. **NOTE:** `requires_verification` is TOP-LEVEL (sibling of `data`); only `challenge_token`/`email` are nested under `data`. (Verified against `AuthController::login()` lines 276–284 — corrected post-review of Task 5.)
(Preview users / MFA branches exist but are out of scope for the scaffold.)

**Verify:** `POST /api/auth/verify-code` body `{ "code": "<6 digits>", "type": "login", "challenge_token": "<from login>" }`
→ `200 { "success": true, "data": { "user": {...}, "access_token": "<token>", "token_type": "Bearer" } }`.

**Authenticated requests:** header `Authorization: Bearer <access_token>`.

**Dashboard placeholder data:** `GET /api/v1/mobile/dashboard` (route name `api.v1.mobile.dashboard`, `auth:sanctum`, returns `{ success, data: {...aggregated module summary...} }`).

**Local-dev verification code retrieval (for browser tests):**
```bash
php artisan tinker --execute="\$u=\App\Models\User::where('email','john@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id',\$u->id)->latest()->first()->code ?? 'none';"
```

---

## File structure

**Create:**
- `vite.mobile.config.js` — isolated Vite config for the mobile build (no laravel-vite-plugin, no hot-file coupling).
- `resources/mobile/main.js` — mobile SPA entry.
- `resources/mobile/App.vue` — root component (router-view only).
- `resources/mobile/router.js` — mobile router (Login/Verify/Dashboard).
- `resources/mobile/store.js` — minimal reactive auth store (token in `localStorage`).
- `resources/mobile/api.js` — fetch wrapper (base URL + Bearer header).
- `resources/mobile/views/Login.vue`, `resources/mobile/views/Verify.vue`, `resources/mobile/views/Dashboard.vue`.
- `resources/mobile/style.css` — minimal placeholder styling (explicitly disposable).
- `resources/mobile/README.md` — seam/auth/deferred docs for the future redesign team.
- `resources/views/mobile-host.blade.php` — `/m` device-frame host with the iframe.
- `resources/views/mobile-app.blade.php` — `/m/app` shell that boots the mobile SPA from the m-build manifest.
- `app/Http/Middleware/RedirectPhoneToMobile.php` — phone UA → `/m` redirect.
- `tests/Feature/Mobile/MobileScaffoldTest.php` — Pest feature tests for routes/headers/redirect.

**Modify:**
- `package.json` — add `build:mobile` script.
- `routes/web.php` — add `/m` + `/m/app/{any?}` routes BEFORE the catch-all (line 81).
- `app/Http/Middleware/SecurityHeaders.php` — scoped frame headers for `/m*`.
- `app/Http/Kernel.php` — register `RedirectPhoneToMobile` in the `web` group.
- `capacitor.config.ts` — `webDir: 'public/build'` → `'public/m-build'`.
- `deploy/mobile/build-ios.sh` — build the mobile bundle, m-build paths.
- `deploy/fynla-org/build.sh`, `deploy/csjones-fynla/build.sh` — add mobile build step.
- `resources/js/router/index.js` — remove `/m/*` routes, `platform.isNative()` guard, dead imports.
- The Vuex store registration file — deregister the mobile modules (Task 8 locates it precisely).

**Delete (Task 8 only, after scaffold verified):**
- `resources/js/mobile/` (entire directory).
- `resources/js/store/modules/mobileDashboard.js`, `resources/js/store/modules/mobileNotifications.js`.

---

## Task 1: Isolated mobile Vite build pipeline

**Files:**
- Create: `vite.mobile.config.js`
- Create: `resources/mobile/main.js`
- Create: `resources/mobile/App.vue`
- Create: `resources/mobile/style.css`
- Modify: `package.json` (scripts block)

- [ ] **Step 1: Create the minimal SPA entry so the build has something to compile**

Create `resources/mobile/style.css`:
```css
/* SP3 scaffold styling — DISPOSABLE. The future mobile redesign replaces this wholesale. */
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Inter, system-ui, sans-serif; background: #F7F6F4; color: #1F2A44; }
#m-app { max-width: 420px; margin: 0 auto; min-height: 100vh; padding: 24px; }
.m-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
.m-field { display: block; width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 16px; }
.m-btn { width: 100%; padding: 14px; background: #E83E6D; color: #fff; border: 0; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; }
.m-btn:disabled { opacity: .5; cursor: not-allowed; }
.m-err { color: #C42B54; font-size: 14px; margin: 8px 0; }
.m-h1 { font-size: 22px; font-weight: 900; margin-bottom: 4px; }
.m-sub { color: #6B7280; font-size: 14px; margin-bottom: 16px; }
.m-tag { display: inline-block; background: #1F2A44; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 999px; margin-bottom: 12px; }
```

Create `resources/mobile/App.vue`:
```vue
<template>
  <div id="m-app">
    <span class="m-tag">SP3 scaffold — placeholder, redesign pending</span>
    <router-view />
  </div>
</template>

<script>
export default { name: 'MobileScaffoldApp' };
</script>
```

Create `resources/mobile/main.js`:
```js
import { createApp } from 'vue';
import App from './App.vue';
import router from './router.js';
import './style.css';

createApp(App).use(router).mount('#m-app');
```

> `router.js` is created in Task 5. This step intentionally references it; the build is not run until Task 5. Steps 2–4 below configure the pipeline only.

- [ ] **Step 2: Create the isolated Vite config**

Create `vite.mobile.config.js`:
```js
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

// Isolated mobile build. Deliberately NO laravel-vite-plugin: that writes a
// `public/hot` file and resolves assets via the dev server, which would couple
// this build to the main web dev server. The /m/app Blade reads
// public/m-build/manifest.json directly instead (see resources/views/mobile-app.blade.php).
//
// iOS-safety rules inherited verbatim from CLAUDE.md (Capacitor loads this build):
//   - NO `external` for image/asset paths in rollupOptions
//   - transformAssetUrls: false in the vue() plugin
//   - no PWA / service worker in this build
export default defineConfig({
    base: process.env.VITE_MOBILE_BASE_PATH || '/m-build/',
    plugins: [
        vue({
            template: { transformAssetUrls: false },
        }),
    ],
    resolve: {
        alias: { '@m': path.resolve(__dirname, 'resources/mobile') },
    },
    build: {
        sourcemap: false,
        manifest: 'manifest.json',
        outDir: 'public/m-build',
        emptyOutDir: true,
        rollupOptions: {
            input: path.resolve(__dirname, 'resources/mobile/main.js'),
        },
    },
});
```

- [ ] **Step 3: Add the build script**

In `package.json`, add to the `"scripts"` object (alongside the existing `"build"` script — keep `"build"` unchanged):
```json
"build:mobile": "vite build --config vite.mobile.config.js"
```

- [ ] **Step 4: Commit the pipeline (build is exercised in Task 5)**

```bash
git add vite.mobile.config.js resources/mobile/main.js resources/mobile/App.vue resources/mobile/style.css package.json
git commit -m "feat(sp3): isolated mobile Vite build pipeline + scaffold entry

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Laravel host + app routes and Blades

**Files:**
- Create: `resources/views/mobile-host.blade.php`
- Create: `resources/views/mobile-app.blade.php`
- Modify: `routes/web.php` (insert BEFORE line 81 `// Serve Vue.js SPA for all routes (catch-all)`)
- Test: `tests/Feature/Mobile/MobileScaffoldTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mobile/MobileScaffoldTest.php`:
```php
<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('serves the mobile host page with a same-origin iframe', function () {
    $res = get('/m');

    $res->assertOk();
    $res->assertSee('<iframe', false);
    $res->assertSee('src="/m/app"', false);
});

it('serves the mobile app shell at /m/app and nested paths', function () {
    get('/m/app')->assertOk()->assertSee('id="m-app"', false);
    get('/m/app/login')->assertOk()->assertSee('id="m-app"', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileScaffoldTest.php`
Expected: FAIL — `/m` currently falls through to the SPA catch-all (no iframe) / route assertions fail.

- [ ] **Step 3: Create the host Blade**

Create `resources/views/mobile-host.blade.php`:
```blade
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1F2A44">
    <title>Fynla</title>
    <style>
        html, body { margin: 0; padding: 0; height: 100%; background: #1F2A44; }
        .m-frame-wrap { position: fixed; inset: 0; }
        iframe { border: 0; width: 100%; height: 100%; display: block; background: #F7F6F4; }
    </style>
</head>
<body>
    <div class="m-frame-wrap">
        <iframe src="/m/app" title="Fynla" allow="clipboard-read; clipboard-write"></iframe>
    </div>
</body>
</html>
```

> The host is intentionally chrome-only. On phones the iframe is full-bleed; a future desktop "phone-frame" presentation can be layered here without touching the inner SPA.

- [ ] **Step 4: Create the app shell Blade (reads the m-build manifest directly)**

Create `resources/views/mobile-app.blade.php`:
```blade
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F7F6F4">
    <title>Fynla</title>
    @php
        $manifestPath = public_path('m-build/manifest.json');
        $entryJs = null;
        $entryCss = [];
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
            $entry = $manifest['resources/mobile/main.js'] ?? null;
            if ($entry) {
                $entryJs = '/m-build/' . $entry['file'];
                foreach (($entry['css'] ?? []) as $css) {
                    $entryCss[] = '/m-build/' . $css;
                }
            }
        }
    @endphp
    @foreach ($entryCss as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
</head>
<body>
    <div id="m-app"></div>
    @if ($entryJs)
        <script type="module" src="{{ $entryJs }}"></script>
    @else
        <p style="font-family:sans-serif;padding:24px">Mobile build missing. Run <code>npm run build:mobile</code>.</p>
    @endif
</body>
</html>
```

- [ ] **Step 5: Register the routes BEFORE the catch-all**

In `routes/web.php`, insert this block immediately BEFORE the line `// Serve Vue.js SPA for all routes (catch-all)` (currently line 81):
```php
// SP3 — mobile-first iframe scaffold. MUST be declared BEFORE the SPA catch-all
// so phone visitors get the dedicated host + isolated mobile SPA instead of the
// desktop Vue shell. /m = device-frame host; /m/app = the new isolated SPA.
Route::get('/m', function () {
    return view('mobile-host');
});
Route::get('/m/app/{any?}', function () {
    return view('mobile-app');
})->where('any', '.*');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileScaffoldTest.php`
Expected: PASS (both tests). The "Mobile build missing" fallback still contains `id="m-app"`, so the shell assertions pass before Task 5's build.

- [ ] **Step 7: Commit**

```bash
git add resources/views/mobile-host.blade.php resources/views/mobile-app.blade.php routes/web.php tests/Feature/Mobile/MobileScaffoldTest.php
git commit -m "feat(sp3): /m host + /m/app shell routes and Blades

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Scoped same-origin frame headers

The global `SecurityHeaders` middleware sets `X-Frame-Options: DENY` on every response and a CSP with no `frame-ancestors`. `/m/app` must be embeddable by `/m` (same origin). Relax **only** `/m` and `/m/app*`.

**Files:**
- Modify: `app/Http/Middleware/SecurityHeaders.php`
- Test: `tests/Feature/Mobile/MobileScaffoldTest.php` (append)

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Mobile/MobileScaffoldTest.php`:
```php
it('allows same-origin framing on /m and /m/app only', function () {
    $m = get('/m');
    expect($m->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    expect($m->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");

    $app = get('/m/app');
    expect($app->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');

    // Desktop SPA stays locked down.
    $home = get('/');
    expect($home->headers->get('X-Frame-Options'))->toBe('DENY');
    expect($home->headers->get('Content-Security-Policy'))->not->toContain('frame-ancestors');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileScaffoldTest.php --filter="same-origin framing"`
Expected: FAIL — `/m` currently returns `X-Frame-Options: DENY` and no `frame-ancestors`.

- [ ] **Step 3: Implement scoped headers**

In `app/Http/Middleware/SecurityHeaders.php`, replace the line:
```php
        $response->headers->set('X-Frame-Options', 'DENY');
```
with:
```php
        // SP3: /m (host) and /m/app* (isolated mobile SPA) are intentionally
        // same-origin framed (host embeds the app). Every other route stays DENY.
        $isMobileFramed = $request->is('m') || $request->is('m/app') || $request->is('m/app/*');
        $response->headers->set('X-Frame-Options', $isMobileFramed ? 'SAMEORIGIN' : 'DENY');
```

Then, immediately AFTER the line `$response->headers->set('Content-Security-Policy', $csp);`, add:
```php
        // SP3: append frame-ancestors only for the mobile-framed routes so the
        // host iframe is permitted while every other page remains unframeable.
        if ($isMobileFramed) {
            $response->headers->set(
                'Content-Security-Policy',
                $csp."; frame-ancestors 'self'"
            );
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileScaffoldTest.php`
Expected: PASS (all tests in the file).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/SecurityHeaders.php tests/Feature/Mobile/MobileScaffoldTest.php
git commit -m "feat(sp3): scoped SAMEORIGIN frame headers for /m and /m/app

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Phone device-detection redirect middleware

**Files:**
- Create: `app/Http/Middleware/RedirectPhoneToMobile.php`
- Modify: `app/Http/Kernel.php` (append to the `web` group)
- Test: `tests/Feature/Mobile/MobileScaffoldTest.php` (append)

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Mobile/MobileScaffoldTest.php`:
```php
const PHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
const DESKTOP_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

it('redirects a phone user-agent on / to /m', function () {
    get('/', ['User-Agent' => PHONE_UA])->assertRedirect('/m');
});

it('does not redirect desktop user-agents', function () {
    get('/', ['User-Agent' => DESKTOP_UA])->assertOk();
});

it('does not redirect /m or /m/app (no loop)', function () {
    get('/m', ['User-Agent' => PHONE_UA])->assertOk();
    get('/m/app', ['User-Agent' => PHONE_UA])->assertOk();
});

it('does not redirect /api on a phone UA', function () {
    // /api/v1/health is public and returns JSON; must never be device-redirected.
    get('/api/v1/health', ['User-Agent' => PHONE_UA])->assertOk()->assertJson(['success' => true]);
});

it('honours the ?full=1 desktop escape hatch and pins via cookie', function () {
    $res = get('/?full=1', ['User-Agent' => PHONE_UA]);
    $res->assertOk();
    $res->assertCookie('m_full_site', '1');

    // With the pin cookie, a later phone request is not redirected.
    $this->withUnencryptedCookie('m_full_site', '1')
        ->get('/', ['User-Agent' => PHONE_UA])
        ->assertOk();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileScaffoldTest.php --filter="phone user-agent"`
Expected: FAIL — no redirect middleware exists yet.

- [ ] **Step 3: Create the middleware**

Create `app/Http/Middleware/RedirectPhoneToMobile.php`:
```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SP3: route phone web visitors to the dedicated mobile surface (/m).
 *
 * Desktop and tablet are untouched. Native Capacitor loads /m directly via
 * its webDir, so it never hits this middleware. Phones-only by design
 * (tablets stay on the full web app).
 */
class RedirectPhoneToMobile
{
    /**
     * Path prefixes that must never be device-redirected.
     *
     * @var array<int, string>
     */
    private const EXCLUDED_PREFIXES = [
        'm', 'm/*', 'api/*', 'admin/*', 'advisor/*',
        'lifecycle/*', 'feed/*', 'storage/*', 'subscribe/*', 'unsubscribe/*',
        'sanctum/*', 'broadcasting/*', 'livewire/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Escape hatch: ?full=1 pins the visitor to the full web app via cookie.
        if ($request->query('full') === '1') {
            return $next($request)->withCookie(
                cookie('m_full_site', '1', 60 * 24 * 365, null, null, true, false)
            );
        }

        if (! $this->shouldRedirect($request)) {
            return $next($request);
        }

        return redirect('/m');
    }

    private function shouldRedirect(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }
        if ($request->cookie('m_full_site') === '1') {
            return false;
        }
        // Only redirect top-level HTML navigations, never XHR/asset fetches.
        if ($request->ajax() || $request->wantsJson()) {
            return false;
        }
        if (! str_contains((string) $request->header('Accept'), 'text/html')) {
            return false;
        }
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($request->is($prefix)) {
                return false;
            }
        }

        return $this->isPhone((string) $request->header('User-Agent'));
    }

    private function isPhone(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }
        // Phone form factor only. "Mobile" + Android/iPhone; exclude iPad/Tablet.
        if (preg_match('/\b(iPad|Tablet)\b/i', $ua)) {
            return false;
        }

        return (bool) preg_match('/iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|webOS/i', $ua);
    }
}
```

- [ ] **Step 4: Register the middleware in the `web` group**

In `app/Http/Kernel.php`, add the import alongside the other middleware `use` statements (after line 25 `use App\Http\Middleware\RedirectIfAuthenticated;`):
```php
use App\Http\Middleware\RedirectPhoneToMobile;
```

Then in the `'web'` middleware group (the array starting at line 79), append `RedirectPhoneToMobile::class,` as the last entry so it runs after `SubstituteBindings::class`:
```php
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            RedirectPhoneToMobile::class,
        ],
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileScaffoldTest.php`
Expected: PASS (all tests). If the `?full=1` cookie assertion fails on encryption, confirm `m_full_site` is added to `app/Http/Middleware/EncryptCookies.php`'s `$except` array — if `EncryptCookies` has an `$except` property, add `'m_full_site'` to it; otherwise the `withUnencryptedCookie` test helper already accounts for it.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/RedirectPhoneToMobile.php app/Http/Kernel.php tests/Feature/Mobile/MobileScaffoldTest.php
git commit -m "feat(sp3): phone UA -> /m redirect middleware with ?full=1 escape hatch

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Mobile SPA scaffold screens (Login → Verify → Dashboard)

No JS unit-test runner exists in this repo; these screens are verified by the build succeeding (this task) and the end-to-end Playwright test (Task 9).

**Files:**
- Create: `resources/mobile/api.js`
- Create: `resources/mobile/store.js`
- Create: `resources/mobile/router.js`
- Create: `resources/mobile/views/Login.vue`
- Create: `resources/mobile/views/Verify.vue`
- Create: `resources/mobile/views/Dashboard.vue`

- [ ] **Step 1: Create the API client**

Create `resources/mobile/api.js`:
```js
// SP3 scaffold API client — DISPOSABLE. Bearer-token against the existing backend.
// Same-origin: VITE_API_BASE_URL defaults to '' (relative) so /api/* resolves
// against whatever host serves /m. Native Capacitor sets VITE_API_BASE_URL.
const BASE = import.meta.env.VITE_API_BASE_URL || '';

export async function apiPost(path, body, token = null) {
  const res = await fetch(`${BASE}${path}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  return { ok: res.ok, status: res.status, data };
}

export async function apiGet(path, token) {
  const res = await fetch(`${BASE}${path}`, {
    headers: {
      'Accept': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  const data = await res.json().catch(() => ({}));
  return { ok: res.ok, status: res.status, data };
}
```

- [ ] **Step 2: Create the auth store**

Create `resources/mobile/store.js`:
```js
import { reactive } from 'vue';

const KEY = 'm_scaffold_token';

export const store = reactive({
  token: localStorage.getItem(KEY) || null,
  user: null,
  challengeToken: null,
  maskedEmail: null,
  setToken(t) {
    this.token = t;
    if (t) localStorage.setItem(KEY, t);
    else localStorage.removeItem(KEY);
  },
  logout() {
    this.setToken(null);
    this.user = null;
    this.challengeToken = null;
  },
});
```

- [ ] **Step 3: Create the router**

Create `resources/mobile/router.js`:
```js
import { createRouter, createWebHistory } from 'vue-router';
import { store } from './store.js';
import Login from './views/Login.vue';
import Verify from './views/Verify.vue';
import Dashboard from './views/Dashboard.vue';

const router = createRouter({
  // Inner SPA lives under /m/app (the iframe src). Base must match.
  history: createWebHistory('/m/app/'),
  routes: [
    { path: '/', redirect: '/login' },
    { path: '/login', name: 'login', component: Login },
    { path: '/verify', name: 'verify', component: Verify },
    { path: '/dashboard', name: 'dashboard', component: Dashboard, meta: { auth: true } },
  ],
});

router.beforeEach((to) => {
  if (to.meta.auth && !store.token) return { name: 'login' };
  if (to.name === 'login' && store.token) return { name: 'dashboard' };
  return true;
});

export default router;
```

- [ ] **Step 4: Create the Login screen**

Create `resources/mobile/views/Login.vue`:
```vue
<template>
  <div class="m-card">
    <h1 class="m-h1">Sign in</h1>
    <p class="m-sub">Fynla mobile (scaffold)</p>
    <form @submit.prevent="submit">
      <input class="m-field" v-model="email" type="email" placeholder="Email" autocomplete="username" required />
      <input class="m-field" v-model="password" type="password" placeholder="Password" autocomplete="current-password" required />
      <p v-if="error" class="m-err">{{ error }}</p>
      <button class="m-btn" :disabled="loading" type="submit">{{ loading ? 'Signing in…' : 'Continue' }}</button>
    </form>
  </div>
</template>

<script>
import { store } from '../store.js';
import { apiPost } from '../api.js';

export default {
  name: 'MobileLogin',
  data: () => ({ email: '', password: '', error: '', loading: false }),
  methods: {
    async submit() {
      this.error = '';
      this.loading = true;
      const { ok, data } = await apiPost('/api/auth/login', { email: this.email, password: this.password });
      this.loading = false;
      if (ok && data?.requires_verification) {
        store.challengeToken = data.data.challenge_token;
        store.maskedEmail = data.data.email;
        this.$router.push({ name: 'verify' });
        return;
      }
      this.error = data?.message || 'Login failed. Check your details and try again.';
    },
  },
};
</script>
```

- [ ] **Step 5: Create the Verify screen**

Create `resources/mobile/views/Verify.vue`:
```vue
<template>
  <div class="m-card">
    <h1 class="m-h1">Enter code</h1>
    <p class="m-sub">We sent a 6-digit code to {{ maskedEmail || 'your email' }}.</p>
    <form @submit.prevent="submit">
      <input class="m-field" v-model="code" inputmode="numeric" maxlength="6" placeholder="000000" required />
      <p v-if="error" class="m-err">{{ error }}</p>
      <button class="m-btn" :disabled="loading" type="submit">{{ loading ? 'Verifying…' : 'Verify' }}</button>
    </form>
  </div>
</template>

<script>
import { store } from '../store.js';
import { apiPost } from '../api.js';

export default {
  name: 'MobileVerify',
  data: () => ({ code: '', error: '', loading: false }),
  computed: { maskedEmail: () => store.maskedEmail },
  created() {
    if (!store.challengeToken) this.$router.replace({ name: 'login' });
  },
  methods: {
    async submit() {
      this.error = '';
      this.loading = true;
      const { ok, data } = await apiPost('/api/auth/verify-code', {
        code: this.code,
        type: 'login',
        challenge_token: store.challengeToken,
      });
      this.loading = false;
      if (ok && data?.data?.access_token) {
        store.setToken(data.data.access_token);
        store.user = data.data.user || null;
        this.$router.push({ name: 'dashboard' });
        return;
      }
      this.error = data?.message || 'Invalid or expired code.';
    },
  },
};
</script>
```

- [ ] **Step 6: Create the Dashboard placeholder**

Create `resources/mobile/views/Dashboard.vue`:
```vue
<template>
  <div>
    <div class="m-card">
      <h1 class="m-h1">Signed in</h1>
      <p class="m-sub">{{ greeting }}</p>
      <button class="m-btn" style="background:#1F2A44" @click="logout">Sign out</button>
    </div>
    <div class="m-card">
      <h1 class="m-h1">Dashboard (placeholder)</h1>
      <p class="m-sub">Live data from the existing backend — presentation is disposable.</p>
      <p v-if="loading" class="m-sub">Loading…</p>
      <p v-else-if="error" class="m-err">{{ error }}</p>
      <pre v-else style="white-space:pre-wrap;font-size:12px;color:#374151">{{ summary }}</pre>
    </div>
  </div>
</template>

<script>
import { store } from '../store.js';
import { apiGet } from '../api.js';

export default {
  name: 'MobileDashboard',
  data: () => ({ loading: true, error: '', summary: '' }),
  computed: {
    greeting() {
      const u = store.user;
      const name = u?.first_name || u?.name || u?.email || 'there';
      return `Welcome, ${name}.`;
    },
  },
  async created() {
    const { ok, data } = await apiGet('/api/v1/mobile/dashboard', store.token);
    this.loading = false;
    if (ok) this.summary = JSON.stringify(data?.data ?? data, null, 2);
    else this.error = data?.message || `Failed to load dashboard (${'$'}{this.status || ''}).`;
  },
  methods: {
    logout() {
      store.logout();
      this.$router.push({ name: 'login' });
    },
  },
};
</script>
```

> Note: the `error` interpolation above must read `Failed to load dashboard.` — replace the `created()` failure line with exactly:
> ```js
> else this.error = data?.message || 'Failed to load dashboard.';
> ```
> (Use this simpler form; do not include the status string — it avoids template-literal escaping mistakes.)

- [ ] **Step 7: Build the mobile bundle and verify output**

Run: `npm run build:mobile`
Expected: build succeeds; `public/m-build/manifest.json` exists and contains a `resources/mobile/main.js` entry.

Run: `test -f public/m-build/manifest.json && node -e "const m=require('./public/m-build/manifest.json'); if(!m['resources/mobile/main.js']) process.exit(1); console.log('entry:', m['resources/mobile/main.js'].file)"`
Expected: prints `entry: assets/main-XXXX.js` (exit 0).

- [ ] **Step 8: Verify no image-import iOS hazard in the built JS**

Run: `grep -r 'import("/images' public/m-build/assets/ 2>/dev/null && echo "HAZARD FOUND" || echo "clean"`
Expected: `clean` (no output from grep). If `HAZARD FOUND`, the scaffold imported an image — remove the image reference (scaffold uses no images).

- [ ] **Step 9: Re-run the feature test (shell now serves real assets)**

Run: `./vendor/bin/pest tests/Feature/Mobile/MobileScaffoldTest.php`
Expected: PASS — `/m/app` now emits the real `<script type="module" src="/m-build/...">`.

- [ ] **Step 10: Commit (build output is gitignored — only source is committed)**

```bash
git add resources/mobile/api.js resources/mobile/store.js resources/mobile/router.js resources/mobile/views/
git commit -m "feat(sp3): scaffold Login/Verify/Dashboard screens (disposable placeholder)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

> If `public/m-build/` is NOT gitignored, add `/public/m-build` to `.gitignore` in this commit (mirrors `/public/build`). Verify with: `git check-ignore public/m-build/manifest.json && echo ignored || echo NOT-ignored`.

---

## Task 6: Capacitor repoint

**Files:**
- Modify: `capacitor.config.ts`
- Modify: `deploy/mobile/build-ios.sh`

- [ ] **Step 1: Repoint the Capacitor webDir**

In `capacitor.config.ts`, change:
```ts
  webDir: 'public/build',
```
to:
```ts
  webDir: 'public/m-build',
```

- [ ] **Step 2: Rewrite the iOS build script for the mobile bundle**

Replace the entire contents of `deploy/mobile/build-ios.sh` with:
```bash
#!/bin/bash
set -e

echo "=== Fynla iOS Build (SP3 mobile scaffold) ==="
echo ""

# Environment for production iOS build of the isolated mobile frontend.
export VITE_MOBILE_BASE_PATH=/
export VITE_API_BASE_URL=https://fynla.org
export VITE_PLATFORM=ios

echo "1. Building isolated mobile assets..."
npm run build:mobile

if [ ! -f "public/m-build/manifest.json" ]; then
    echo "ERROR: Build failed - public/m-build/manifest.json not found"
    exit 1
fi

echo "2. Generating index.html for Capacitor..."
APP_JS=$(python3 -c "
import json
with open('public/m-build/manifest.json') as f:
    m = json.load(f)
print(m['resources/mobile/main.js']['file'])
")
APP_CSS=$(python3 -c "
import json
with open('public/m-build/manifest.json') as f:
    m = json.load(f)
e = m['resources/mobile/main.js']
print((e.get('css') or [''])[0])
")

cat > public/m-build/index.html << HTMLEOF
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#1F2A44">
    <title>Fynla</title>
    $( [ -n "$APP_CSS" ] && echo "<link rel=\"stylesheet\" href=\"/${APP_CSS}\">" )
</head>
<body>
    <div id="m-app"></div>
    <script type="module" src="/${APP_JS}"></script>
</body>
</html>
HTMLEOF

echo "3. Copying public assets for Capacitor..."
cp -R public/images public/m-build/images 2>/dev/null || true
cp -R public/icons public/m-build/icons 2>/dev/null || true

echo "4. Syncing to iOS project..."
npx cap sync ios

echo ""
echo "=== Build complete ==="
echo "iOS now loads the SP3 mobile scaffold. Native auth (token/biometric) is"
echo "deferred to the future redesign (documented in resources/mobile/README.md)."
echo ""
```

- [ ] **Step 3: Verify the script runs (build + index.html generation)**

Run: `bash deploy/mobile/build-ios.sh` up to the `npx cap sync ios` step. If `npx cap sync ios` fails because no iOS toolchain is present in this environment, that is acceptable — confirm steps 1–3 succeeded:
Run: `test -f public/m-build/index.html && grep -q 'id="m-app"' public/m-build/index.html && echo "index.html OK"`
Expected: `index.html OK`.

- [ ] **Step 4: Commit**

```bash
git add capacitor.config.ts deploy/mobile/build-ios.sh
git commit -m "feat(sp3): repoint Capacitor iOS at the mobile scaffold build

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Deploy-script wiring (both environments)

The two env build scripts must also produce `public/m-build/` with the correct base path.

**Files:**
- Modify: `deploy/fynla-org/build.sh`
- Modify: `deploy/csjones-fynla/build.sh`

- [ ] **Step 1: Wire fynla.org (root) build**

In `deploy/fynla-org/build.sh`, immediately AFTER the block:
```bash
if [ ! -f "public/build/manifest.json" ]; then
    echo "ERROR: Build failed - manifest.json not found"
    exit 1
fi
```
insert:
```bash
# SP3 — isolated mobile build (root deployment: /m-build/).
echo "Building isolated mobile assets (SP3)..."
export VITE_MOBILE_BASE_PATH=/m-build/
npm run build:mobile
if [ ! -f "public/m-build/manifest.json" ]; then
    echo "ERROR: Mobile build failed - public/m-build/manifest.json not found"
    exit 1
fi
```
Then in the "Manual Upload" echo block, after the line that echoes `"   ~/www/fynla.org/public_html/public/build/"`, add:
```bash
echo ""
echo "1b. Upload public/m-build/ directory to:"
echo "   ~/www/fynla.org/public_html/public/m-build/"
```

- [ ] **Step 2: Wire csjones.co/fynla (subdirectory) build**

In `deploy/csjones-fynla/build.sh`, immediately AFTER the block:
```bash
if [ ! -f "public/build/manifest.json" ]; then
    echo "ERROR: Build failed - manifest.json not found"
    exit 1
fi
```
insert:
```bash
# SP3 — isolated mobile build (subdirectory deployment: /fynla/m-build/).
echo "Building isolated mobile assets (SP3)..."
export VITE_MOBILE_BASE_PATH=/fynla/m-build/
npm run build:mobile
if [ ! -f "public/m-build/manifest.json" ]; then
    echo "ERROR: Mobile build failed - public/m-build/manifest.json not found"
    exit 1
fi
```
Then in the "Manual Upload" echo block, after the line that echoes `"   ~/www/csjones.co/fynla-app/public/build/"`, add:
```bash
echo ""
echo "1b. Upload public/m-build/ directory to:"
echo "   ~/www/csjones.co/fynla-app/public/m-build/"
```

> Subdirectory note: on csjones the mobile asset base is `/fynla/m-build/`, but the Blade in Task 2 emits hard-coded `/m-build/...` paths. Update `resources/views/mobile-app.blade.php` to prefix with the app's base. Replace `'/m-build/' . $entry['file']` and `'/m-build/' . $css` with `asset('m-build/' . $entry['file'])` and `asset('m-build/' . $css)` respectively — `asset()` already respects `APP_URL` for subdirectory deployments (same pattern as `app.blade.php` favicon, lines 38–42). Make this edit now and re-run `./vendor/bin/pest tests/Feature/Mobile/MobileScaffoldTest.php` (expected: PASS — `asset()` resolves to `/m-build/...` locally).

- [ ] **Step 3: Verify both scripts parse**

Run: `bash -n deploy/fynla-org/build.sh && bash -n deploy/csjones-fynla/build.sh && echo "syntax OK"`
Expected: `syntax OK`.

- [ ] **Step 4: Commit**

```bash
git add deploy/fynla-org/build.sh deploy/csjones-fynla/build.sh resources/views/mobile-app.blade.php
git commit -m "feat(sp3): wire mobile build into both env deploy scripts

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Legacy mobile retirement

Highest-risk task — done only after the scaffold is verified green. Remove the old mobile frontend so the new one is the single mobile surface.

**Files:**
- Modify: `resources/js/router/index.js`
- Delete: `resources/js/mobile/` (entire directory)
- Delete: `resources/js/store/modules/mobileDashboard.js`, `resources/js/store/modules/mobileNotifications.js`
- Modify: the Vuex store registration file (located in Step 2)

- [ ] **Step 1: Inventory every reference into the legacy mobile code**

Run:
```bash
grep -rn "js/mobile\|/mobile/\|MobileLayout\|MobileDashboard\|MobileFynChat\|MobileLoginScreen\|VerificationCodeScreen\|BiometricPrompt\|MobileGoalsList\|MobileGoalDetail\|MoreMenu\|ProtectionDetail\|platform.isNative\|mobileDashboard\|mobileNotifications" resources/js --include="*.js" --include="*.vue" | grep -v "resources/js/mobile/" | sort
```
Expected: references are confined to `resources/js/router/index.js` (route imports + `/m/*` route blocks + `platform.isNative()` guard) and the Vuex store registration file. Record the exact lines. If any OTHER web component imports from `resources/js/mobile/`, STOP and report — the spec assumes the tree is isolated; an unexpected cross-import needs a decision.

- [ ] **Step 2: Locate the Vuex module registration**

Run:
```bash
grep -rn "mobileDashboard\|mobileNotifications" resources/js/store/
```
Expected: import + `modules: { ... }` entries in `resources/js/store/index.js` (or equivalent). Record exact lines.

- [ ] **Step 3: Remove the `/m/*` routes and native guard from the router**

In `resources/js/router/index.js`:
1. Delete the `// Mobile auth routes (no layout)` block (the `/m/login`, `/m/verify`, `/m/biometric-setup` route objects) and the `// Mobile app routes (with MobileLayout)` block (the `/m` route object with all its `children`). Keep the trailing `/:pathMatch(.*)*` NotFound route.
2. Delete the entire `// Redirect native app users to mobile routes` block inside `router.beforeEach` (the `if (platform.isNative() && !to.path.startsWith('/m/')) { ... }` block).
3. In the `requiresAuth && !isAuthenticated && !isPreviewMode` branch, replace:
```js
    if (platform.isNative()) {
      next({ name: 'MobileLogin' });
    } else {
      next({ name: 'Login' });
    }
```
with:
```js
    next({ name: 'Login' });
```
4. Remove now-unused imports at the top of the file: every component imported only for the deleted `/m/*` routes (`MobileLayout`, `MobileDashboard`, `MobileFynChat`, `LearnHub`, `LearnTopicDetail`, `MobileGoalsList`, `MobileGoalDetail`, `MoreMenu`, `NotificationSettings`, `ProtectionDetail`, `SavingsDetail`, `InvestmentDetail`, `RetirementDetail`, `EstateDetail`, `GoalsDetail`, `CoordinationDetail`, `MobileLoginScreen`, `VerificationCodeScreen`, `BiometricPrompt`) and the `platform` import if it is now unused anywhere else in the file (grep `platform\.` within the file first; remove the import only if zero remaining uses).

- [ ] **Step 4: Deregister and delete the Vuex mobile modules**

In the store registration file from Step 2, remove the `mobileDashboard` and `mobileNotifications` imports and their entries in the `modules` object. Then:
```bash
git rm resources/js/store/modules/mobileDashboard.js resources/js/store/modules/mobileNotifications.js
git rm -r resources/js/mobile
```

- [ ] **Step 5: Verify the web build still compiles with zero orphan imports**

Run: `npm run build`
Expected: build succeeds, no "failed to resolve import" errors. If any unresolved import points at a deleted file, remove that import (it was missed in Step 3/Step 4).

- [ ] **Step 6: Run the full Pest suite (no backend regression)**

Run: `./vendor/bin/pest`
Expected: green at or below the documented clean baseline (the pre-existing AI/chat baseline failures are unrelated to SP3; no NEW failures introduced). The SP3 feature test file passes.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "refactor(sp3): retire legacy resources/js/mobile + /m Vue routes + isNative guard

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: End-to-end browser test, README, spec correction

Per the Fynla browser-testing law: interact (click, fill, submit), verify the result. Do NOT mark complete on a code read.

**Files:**
- Create: `resources/mobile/README.md`
- Modify: `docs/superpowers/specs/2026-05-19-sub-project-3-mobile-iframe-scaffold-design.md` (auth-mechanism correction)

- [ ] **Step 1: Ensure dev server + fresh mobile build**

Run: `npm run build:mobile` then confirm the dev server is up (`lsof -i :8000`). If not: `./dev.sh` in the background.

- [ ] **Step 2: Browser test — desktop unchanged**

Using Playwright (desktop user-agent), navigate to `http://localhost:8000/`. Verify the existing web app loads (login/landing renders, not `/m`). Record pass/fail.

- [ ] **Step 3: Browser test — phone routed to /m and the iframe renders**

Using Playwright with an iPhone user-agent + mobile viewport, navigate to `http://localhost:8000/`. Verify a redirect to `/m` and that an `<iframe>` is present with `src="/m/app"`. Enter the iframe frame and confirm the Login screen renders (`Sign in`).

- [ ] **Step 4: Browser test — full auth journey inside the iframe**

In the iframe: fill the Login form with `john@example.com` / `password`, submit. Verify it advances to the Verify screen. Fetch the code:
```bash
php artisan tinker --execute="\$u=\App\Models\User::where('email','john@example.com')->first(); echo \App\Models\EmailVerificationCode::where('user_id',\$u->id)->latest()->first()->code ?? 'none';"
```
Enter the 6-digit code, submit. Verify the Dashboard placeholder renders, shows `Welcome, ...`, and displays real JSON from `/api/v1/mobile/dashboard` (not the error state). If any step is RED, follow CLAUDE.md Rule #15 (diagnose with file:line evidence → fix → re-verify) until GREEN.

- [ ] **Step 5: Browser test — escape hatch**

Navigate (phone UA) to `http://localhost:8000/?full=1`. Verify the full web app loads (no `/m` redirect) and a subsequent phone-UA visit to `/` stays on the full site (cookie pin).

- [ ] **Step 6: Write the README**

Create `resources/mobile/README.md`:
```markdown
# Fynla Mobile (SP3 scaffold)

This is the **isolated mobile frontend**, separate from the desktop web app
(`resources/js/`). It has its own Vite build (`vite.mobile.config.js` →
`public/m-build/`) and shares **zero components** with the web app.

## How it is served

- `GET /m` → `resources/views/mobile-host.blade.php` — device-frame host,
  embeds `<iframe src="/m/app">` (same origin).
- `GET /m/app/{any?}` → `resources/views/mobile-app.blade.php` — boots this SPA.
- Phone user-agents are 302-redirected to `/m` by
  `App\Http\Middleware\RedirectPhoneToMobile`. `?full=1` pins to the full web app.

## Auth

Bearer-token against the **existing** backend, unchanged:
`POST /api/auth/login` → `POST /api/auth/verify-code` → `data.access_token`,
stored in `localStorage` and sent as `Authorization: Bearer`.

## Status: SCAFFOLD ONLY

The screens (`views/Login.vue`, `Verify.vue`, `Dashboard.vue`) are **disposable
placeholders** that prove the seam works (real login, real data). The redesign
replaces them wholesale inside this directory.

## Deferred (NOT in SP3)

- Redesigned UI / mobile design system / component library.
- Native Capacitor auth: in native the origin is `capacitor://localhost`, so
  cookie/session continuity does not apply; the scaffold's working auth proof is
  **mobile web**. Native token/biometric auth is future redesign work. iOS is
  not a live production concern (CSJ decision, 2026-05-19).
- Feature parity with the retired `resources/js/mobile/` app.
- Deep-link / browser-back postMessage bridging across the iframe.

## Build

- Local: `npm run build:mobile`
- Per-env: handled by `deploy/fynla-org/build.sh` and
  `deploy/csjones-fynla/build.sh` (sets `VITE_MOBILE_BASE_PATH`).
- iOS: `deploy/mobile/build-ios.sh`.
```

- [ ] **Step 7: Correct the spec auth wording**

In `docs/superpowers/specs/2026-05-19-sub-project-3-mobile-iframe-scaffold-design.md`, in §5.3 replace the cookie-based description with the Bearer-token reality. Change the §5.3 steps to:
```markdown
### 5.3 Auth flow (mobile web)

1. New SPA loads in the iframe; if no stored token, shows its own Login screen.
2. Login POST → `POST /api/auth/login` → `data.challenge_token` + emailed code.
3. `POST /api/auth/verify-code` (`code`, `type: "login"`, `challenge_token`) → `data.access_token`.
4. Token stored in `localStorage`; sent as `Authorization: Bearer` on `/api/*` (e.g. `/api/v1/mobile/dashboard`) → dashboard placeholder renders real data.

Auth is Bearer-token (existing backend behaviour), not a session cookie. Same
origin still matters: CSP `connect-src 'self'` covers the in-iframe `/api/*`
calls with no CORS. No new auth routes, so `PreviewWriteInterceptor`
`EXCLUDED_ROUTES` (CLAUDE.md Rule #8) needs no change.
```
Also update §5.4 / §2.2 references to "session cookie" if any remain (grep the spec for "cookie" and reconcile each hit to the token model).

- [ ] **Step 8: Commit**

```bash
git add resources/mobile/README.md docs/superpowers/specs/2026-05-19-sub-project-3-mobile-iframe-scaffold-design.md
git commit -m "docs(sp3): mobile README + spec auth-mechanism correction (cookie -> Bearer)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 9: Push and open PR to dev**

```bash
git push -u origin iFrames
gh pr create --base dev --title "SP3: mobile-first iframe scaffold" --body "Implements docs/superpowers/specs/2026-05-19-sub-project-3-mobile-iframe-scaffold-design.md. Scaffolding only — disposable placeholder screens; redesign is future work. Browser-tested locally; csjones smoke required before dev→main per Fynla deploy flow.

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

---

## Self-Review (completed by plan author)

**1. Spec coverage:**
- §3.1.1 isolated Vite frontend → Task 1, 5. §3.1.2 `/m` host + iframe → Task 2. §3.1.3 `/m/app` → Task 2. §3.1.4 Login/Verify/Dashboard on `/api/*` → Task 5, 9. §3.1.5 device detection + `?full=1` → Task 4. §3.1.6 legacy retirement → Task 8. §3.1.7 Capacitor repoint → Task 6. §3.1.8 deploy wiring → Task 7. §3.1.9 README → Task 9. §5.4 frame headers (the confirmed-real HIGH risk) → Task 3. §9 testing → Task 9. §10 acceptance → covered across Tasks 2–9. All spec sections map to a task.
- Spec §5.3 cookie-vs-token discrepancy → explicitly corrected in Task 9 Step 7 and flagged at the top of this plan.

**2. Placeholder scan:** No "TBD/TODO/handle edge cases". Every code step shows complete code. The one prose caveat (Task 5 Step 6 error line) gives the exact replacement string.

**3. Type/contract consistency:** Backend contracts (`/api/auth/login` → `data.challenge_token`; `/api/auth/verify-code` {code,type,challenge_token} → `data.access_token`; `/api/v1/mobile/dashboard`) are verified against `AuthController.php` and `routes/api_v1.php` and used identically in `api.js`/`Login.vue`/`Verify.vue`/`Dashboard.vue`. Router base `/m/app/` matches the `/m/app/{any?}` route and the iframe `src="/m/app"`. `m_full_site` cookie name consistent across middleware and tests. `resources/mobile/main.js` manifest key consistent across `vite.mobile.config.js`, `mobile-app.blade.php`, and `build-ios.sh`.

---

*End of implementation plan.*
