# Awin Affiliate Integration — Spec & Plan

**Branch:** `awinIntegrate` (off `main`)
**Merchant ID:** `126105`
**Network:** Awin
**Scope:** Add Awin MasterTag to every authorised page, capture affiliate click cookies, and fire both browser-side and server-to-server conversion events when a Fynla subscription is purchased.
**Author:** 15 April 2026
**Status:** All decisions locked (see §9). Ready to execute Phase 1.

---

## 1. Goals

1. **Attribution** — credit Awin publishers for subscriptions purchased by users who landed on Fynla via an Awin affiliate link.
2. **Reliability** — dual-path tracking (browser pixel + S2S from the Revolut webhook) so that ad blockers, cookie rejections, or page refreshes do not break attribution.
3. **Compliance** — only fire Awin tags for users who have accepted marketing cookies; exclude preview personas and staff.
4. **Zero interference** — no impact on existing Plausible, Meta Pixel, or GA4 tracking. No impact on the Revolut checkout page.

---

## 2. Source Material

Awin's merchant onboarding supplied four snippets (see `awin/integration.md`):

1. **MasterTag** — `<script src="https://www.dwin1.com/126105.js" defer></script>` placed just before `</body>` on every page *except* pages that display or process sensitive payment information.
2. **Fallback conversion pixel** — `<img>` tag fired on the order confirmation page with transaction parameters.
3. **Conversion JS tag** — populates `AWIN.Tracking.Sale` object on the confirmation page.
4. **`awc` click-cookie capture** — PHP snippet that reads `?awc=` from the query string on landing and writes a 1-year cookie.
5. **Server-to-server call** — `https://www.awin1.com/sread.php?tt=ss&tv=2&merchant=126105&amount=…&ref=…&cks={awc}` for back-end tracking.

**Transaction parameters required by Awin:**

| Awin param | Fynla source |
|---|---|
| `order_subtotal` | `Payment.amount` (pence → decimal, e.g. `10.99`) |
| `currency_code` | `'GBP'` (Fynla is UK-only) |
| `order_ref` | `Payment.id` or `Subscription.id` prefixed (`FYN-PAY-{id}`) — must be unique |
| `commission_group` | `'SUB'` (all Fynla subscriptions in one group for v1) |
| `sale_amount` | Same as `order_subtotal` for single-group sales |
| `voucher_code` | `Payment.discountCode->code` or empty string |
| `customer_acquisition` | `'new'` if user has no prior completed payments, `'existing'` otherwise |
| `cks` (S2S only) | Captured `awc` cookie value at time of click |
| `ch` | `'aw'` (channel identifier, always) |

---

## 3. Architectural Decisions

### 3.1 Dual tracking — browser pixel + S2S

- **Browser pixel** fires from `CheckoutPage.vue` immediately after `handlePaymentSuccess()` returns. Provides real-time conversion.
- **Server-to-server** fires from the Revolut webhook handler in `WebhookController::handleOrderCompleted()` once the subscription transitions to `active`. Provides the authoritative ping and catches cases where the browser never reaches the success modal (e.g. user closed tab, ad blocker killed the pixel).
- **Awin deduplicates by `order_ref`** — both tracks use the same reference so duplicates collapse cleanly.

### 3.2 Click-cookie capture — Laravel middleware, not client JS

- A global middleware `CaptureAwcCookie` runs on every web request, reads `?awc=` from the query string if present, and sets a 365-day cookie with `HttpOnly`, `Secure`, `SameSite=Lax`, domain-scoped to `fynla.org` (prod) or `csjones.co` (dev).
- Server-side capture survives SPA navigation and is automatically available to the webhook handler via `$request->cookie('awc')` at payment time.

### 3.3 Cookie consent respect

- Fynla has an existing `cookieConsent` utility at `resources/js/utils/cookieConsent.js` storing accept/decline in `localStorage['cookie_consent']`.
- **MasterTag loads only when consent === `'accepted'`.** We keep it out of the blade template and load it dynamically from `app.js` on boot (same pattern as Plausible/GA4 today).
- **The `awc` capture middleware runs regardless of consent** — we are capturing a URL parameter, not a cookie at that moment; the cookie we subsequently set is functional (attribution-only, no PII) and aligns with Awin's LI assessment.
- **S2S conversion fires regardless of consent** — it's a server-side event with no client cookie, so the GDPR basis is the contract performance of the purchase, not a tracking cookie.

### 3.4 Preview-mode exclusion

- `is_preview_user === true` users never fire Awin events (browser pixel skipped, S2S skipped, MasterTag not loaded).
- Staff accounts (admin users) also skipped — use the existing admin role check.

### 3.5 Exclusions — routes that must NOT carry the MasterTag

- `/auth/checkout` (CheckoutPage.vue — Revolut embedded widget)
- Any admin routes under `/settings/billing` or equivalent if they show card details
- Implementation: the `loadAwinMasterTag()` function in `app.js` checks `router.currentRoute` before injecting and re-checks on route change.

---

## 4. File-level Plan

### 4.1 Backend (PHP / Laravel)

| # | File | Purpose |
|---|---|---|
| 1 | `config/awin.php` *(new)* | Holds merchant ID, enabled flag, base URLs, commission group default. Follows the `config/analytics.php` pattern. |
| 2 | `.env` templates (`deploy/csjones-fynla/.env.production`, `deploy/fynla-org/.env.production`) | `AWIN_ENABLED`, `AWIN_MERCHANT_ID=126105`, `AWIN_MASTER_TAG_URL`, `AWIN_S2S_BASE_URL`, `AWIN_COOKIE_DOMAIN` |
| 3 | `app/Http/Middleware/CaptureAwcCookie.php` *(new)* | Reads `?awc=` from request, writes cookie with 1-year TTL. Registered in `Kernel.php::$middleware` (global) so it runs on both web and api routes. |
| 4 | `app/Http/Kernel.php` *(edit)* | Append `\App\Http\Middleware\CaptureAwcCookie::class` to `$middleware`. Add `awc` to `EncryptCookies::$except` so Awin receives the raw value. |
| 5 | `app/Http/Middleware/SecurityHeaders.php` *(edit)* | Extend `script-src` CSP directive to whitelist `https://www.dwin1.com` and `https://www.awin1.com`. Extend `img-src` to allow the fallback pixel from `https://www.awin1.com`. |
| 6 | `app/Services/Marketing/AwinTrackingService.php` *(new)* | Pure service with methods: `buildSaleParams(Payment $p, User $u): array`, `isCustomerAcquisition(User $u, int $excludePaymentId): bool`, `commissionGroupFor(string $planSlug): string`, `fireServerToServer(array $params): bool`. Uses Laravel `Http` facade (not raw cURL — see §6a for the reference implementation and the bugs in Awin's own example). 3-second total + connect timeout, non-blocking try/catch, failures logged via `StructuredLogging` but never rethrown. |
| 7 | `app/Http/Controllers/Api/WebhookController.php` *(edit, ~L143–L153)* | After subscription transitions to `active`, dispatch `FireAwinConversionJob` (queued, same DB connection as webhook). |
| 8 | `app/Http/Controllers/Api/PaymentController.php` *(edit, ~L320–L346)* | Same dispatch as the webhook — whichever path activates the subscription fires the job. The job idempotency key is `order_ref`, so dual dispatch is safe. |
| 9 | `app/Jobs/FireAwinConversionJob.php` *(new)* | Implements `ShouldQueue`. Loads the payment, builds the sale params via `AwinTrackingService`, calls `fireServerToServer()`. Retries 3× with exponential backoff (30 s / 5 min / 30 min). On final failure logs to `structured_logs` and surfaces in the admin panel. |
| 10 | `database/migrations/*_add_awin_tracking_to_payments_table.php` *(new)* | Adds `awin_order_ref` (string, nullable, indexed), `awin_cks` (string, nullable — captured `awc` at time of payment), `awin_fired_at` (timestamp, nullable), `awin_customer_acquisition` (enum: `new`, `existing`, nullable) to the `payments` table. This gives us an audit trail and lets the job be idempotent. |
| 11 | `app/Models/Payment.php` *(edit)* | Add the four new fields to `$fillable` and `$casts`. |

### 4.2 Frontend (Vue / Vuex)

| # | File | Purpose |
|---|---|---|
| 12 | `resources/js/utils/awinTracking.js` *(new)* | Exports `loadMasterTag()`, `fireConversion(params)`, `shouldLoadAwin()` (checks route name and preview mode). Mirrors the pattern in `analyticsService.js`. |
| 13 | `resources/js/app.js` *(edit)* | On boot, if `cookieConsent.hasConsent()` and not on a sensitive route, call `awinTracking.loadMasterTag()`. Add a `$router.afterEach` hook that re-checks and unloads/loads on route change. |
| 14 | `resources/js/utils/cookieConsent.js` *(edit)* | In `acceptCookies()`, also call `awinTracking.loadMasterTag()`. In `declineCookies()`, remove the existing MasterTag `<script>` element if present. |
| 15 | `resources/js/views/Auth/CheckoutPage.vue` *(edit, ~L467)* | After the existing GA4 `purchase` event, call `awinTracking.fireConversion({...})` with the same payment payload (order ref, amount, currency, voucher code, customer acquisition flag — the backend returns the flag in the `/payment/confirm` response). |
| 16 | `resources/js/services/subscriptionService.js` *(edit)* | If the confirm endpoint response now carries an `awin` object (`{ order_ref, customer_acquisition }`), thread it through to the success handler. |

### 4.3 Tests

| # | File | Purpose |
|---|---|---|
| 17 | `tests/Unit/Services/Marketing/AwinTrackingServiceTest.php` | `buildSaleParams` produces correct payload; `isCustomerAcquisition` returns `true` for first-time buyers, `false` for repeat; `commissionGroupFor` maps plan slugs; S2S URL is correctly encoded. |
| 18 | `tests/Unit/Middleware/CaptureAwcCookieTest.php` | Cookie is set when `?awc=` present; cookie is NOT set when absent; existing cookie is not overwritten if newer value arrives (configurable — Awin default is "last click wins", so we *do* overwrite). |
| 19 | `tests/Feature/Payment/AwinConversionFlowTest.php` | End-to-end: simulate a Revolut webhook → assert `FireAwinConversionJob` dispatched → fake HTTP → assert Awin receives correct params → assert `payments.awin_fired_at` is set → assert idempotency (re-dispatch does nothing). |
| 20 | `tests/Feature/Payment/AwinExclusionsTest.php` | Preview user payment does not dispatch the job; admin user payment does not dispatch; failed payment does not dispatch. |

### 4.4 Documentation

| # | File | Purpose |
|---|---|---|
| 21 | `deploy/awin/README.md` *(new)* | Deployment runbook: env vars to set, the Awin dashboard URLs to watch, how to place a test order, troubleshooting common issues (pixel blocked, S2S 500 from Awin, etc.). |
| 22 | `fynlaBrain/currentState/AwinIntegration.md` *(new)* | Vault doc: architecture overview, models, data flow, compliance notes. Lives in the `currentState/` folder per the vault convention. |

---

## 5. Data Flow

### 5.1 Click → cookie → purchase → attribution

```
Publisher link:    https://fynla.org/?awc=12345-1234567-1234567
                                      ↓
           CaptureAwcCookie middleware (global, runs on every request)
                                      ↓
       Cookie set: awc=12345-1234567-1234567 (365d, HttpOnly, Secure, SameSite=Lax)
                                      ↓
User browses, registers, trials, eventually hits /auth/checkout
                                      ↓
User completes Revolut payment → Revolut fires ORDER_COMPLETED webhook
                                      ↓
       WebhookController::handleOrderCompleted() (L143)
                                      ↓
             Subscription status → 'active'
                                      ↓
         Payment record updated: awin_cks = $request->cookie('awc')
                                      ↓
           FireAwinConversionJob dispatched (queued)
                                      ↓
       Job: AwinTrackingService::fireServerToServer()
                                      ↓
         HTTP GET https://www.awin1.com/sread.php?tt=ss&tv=2&merchant=126105&...
                                      ↓
                    payments.awin_fired_at = now()
```

### 5.2 Browser-side pixel (parallel track)

```
CheckoutPage.vue: handlePaymentSuccess() returns successfully
                                ↓
        Existing: gtag('event', 'purchase', …)    ← unchanged
                                ↓
      New: awinTracking.fireConversion({…})
                                ↓
    Injects <img> fallback pixel + sets AWIN.Tracking.Sale object
                                ↓
    MasterTag (already loaded) picks up the Sale object and fires
```

---

## 6. Config Schema

**`config/awin.php`:**
```php
<?php
declare(strict_types=1);

return [
    'enabled' => env('AWIN_ENABLED', false),
    'merchant_id' => env('AWIN_MERCHANT_ID', '126105'),
    'master_tag_url' => env('AWIN_MASTER_TAG_URL', 'https://www.dwin1.com/126105.js'),
    's2s_base_url' => env('AWIN_S2S_BASE_URL', 'https://www.awin1.com/sread.php'),
    'fallback_pixel_base' => env('AWIN_FALLBACK_PIXEL_BASE', 'https://www.awin1.com/sread.img'),
    'default_commission_group' => env('AWIN_DEFAULT_COMMISSION_GROUP', 'SUB'),
    'cookie_domain' => env('AWIN_COOKIE_DOMAIN', 'fynla.org'),
    'cookie_lifetime_days' => 365,
    'http_timeout_seconds' => 3,
    'excluded_routes' => [
        'checkout',
        'auth.checkout',
        'payment.confirm',
    ],
];
```

**`.env.production` additions (fynla-org):**
```
AWIN_ENABLED=true
AWIN_MERCHANT_ID=126105
AWIN_COOKIE_DOMAIN=fynla.org
```

**`.env.production` additions (csjones-fynla / staging):**
```
AWIN_ENABLED=false    # off in staging by default — enable per-test
AWIN_MERCHANT_ID=126105
AWIN_COOKIE_DOMAIN=csjones.co
```

---

## 6a. Server-to-Server Reference Implementation

Awin supplied this PHP cURL example for the S2S call (condensed):

```php
<?php
$url = "https://www.awin1.com/sread.php?tt=ss&tv=2&merchant=126105";
$url .= "&amount=" . $orderSubtotal;
$url .= "&ch=aw";
$url .= "&cr=" . $currencyCode;
$url .= "&ref=" . $orderRef;
$url .= "&parts=" . $commissionGroup . "&ref=" . $saleAmount;  // BUG — see note 1
$url .= "&vc=" . $voucherCode;
$url .= "&customeracquisition=" . $customerAcquisition;
if (isset($awc)) {
    $url .= "&cks=" . $awc;
}
// curl_init / CURLOPT_CONNECTTIMEOUT=10 / curl_exec / curl_close
```

### Issues with Awin's example (do NOT copy verbatim)

1. **Typo in `parts` construction.** The snippet writes `&parts=" . $commissionGroup . "&ref=" . $saleAmount` — that produces a URL with two `&ref=` params and overwrites the real order reference with the sale amount. The correct `parts` format, per Awin's own integration doc earlier in this repo (`awin/integration.md` line 14), is a colon-separated pair: `parts={commission_group}:{sale_amount}`. Our implementation uses the colon form.
2. **No URL encoding.** Voucher codes, order references, and the `awc` cookie value can legitimately contain `&`, `+`, `=`, or spaces which would corrupt the query string. Every value must be URL-encoded (or built via a proper query builder). We use Laravel's HTTP client which encodes automatically.
3. **Response ignored.** The cURL return value is discarded — we can't distinguish 200 from 500 from network error. We use Laravel's `Http` facade so we get a `Response` object with status, body, and structured error handling.
4. **No total timeout.** Only `CURLOPT_CONNECTTIMEOUT = 10s` is set. A slow Awin response could block the queue worker indefinitely. We set both connect and total timeouts (3s each).

### Fynla implementation — `AwinTrackingService::fireServerToServer()`

```php
public function fireServerToServer(array $params): bool
{
    $query = [
        'tt' => 'ss',
        'tv' => '2',
        'merchant' => config('awin.merchant_id'),
        'amount' => number_format($params['order_subtotal'], 2, '.', ''),
        'ch' => 'aw',
        'cr' => $params['currency_code'],
        'ref' => $params['order_ref'],
        'parts' => "{$params['commission_group']}:{$params['sale_amount']}",
        'vc' => $params['voucher_code'] ?? '',
        'customeracquisition' => $params['customer_acquisition'],
    ];

    if (!empty($params['awc'])) {
        $query['cks'] = $params['awc'];
    }

    try {
        $response = Http::timeout(config('awin.http_timeout_seconds', 3))
            ->connectTimeout(config('awin.http_timeout_seconds', 3))
            ->retry(0)  // retries handled by the queued job, not here
            ->get(config('awin.s2s_base_url'), $query);

        if ($response->successful()) {
            $this->logInfo('[awin] s2s fired', [
                'order_ref' => $params['order_ref'],
                'status' => $response->status(),
            ]);
            return true;
        }

        $this->logError('[awin] s2s non-2xx', [
            'order_ref' => $params['order_ref'],
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        return false;
    } catch (\Throwable $e) {
        $this->logError('[awin] s2s exception', [
            'order_ref' => $params['order_ref'],
            'message' => $e->getMessage(),
        ]);
        return false;
    }
}
```

Notes:

- Uses `Http` facade — automatic URL encoding, timeout enforcement, and testable via `Http::fake()` in the feature test.
- Returns `bool` — the caller (`FireAwinConversionJob`) uses the return value to decide whether to mark `payments.awin_fired_at` or leave it null for retry.
- Exceptions are swallowed and logged — a tracking outage MUST NOT break a payment confirmation flow.
- Retries are the job's responsibility (exponential backoff), NOT the HTTP client's — otherwise the queue worker blocks during retries.
- `tt=ss&tv=2` (server-side, tracking version 2) and `ch=aw` (channel = Awin) are constants per Awin docs.


---

## 7. Security / Compliance Checklist

- [ ] `awc` cookie is `HttpOnly`, `Secure`, `SameSite=Lax`, domain-scoped, 365-day TTL
- [ ] `awc` added to `EncryptCookies::$except` so Awin receives the raw value (Awin cannot decrypt Laravel's encryption)
- [ ] CSP `script-src` whitelists only `https://www.dwin1.com` and `https://www.awin1.com`
- [ ] CSP `img-src` whitelists `https://www.awin1.com` for the fallback pixel
- [ ] MasterTag blocked on `/auth/checkout` (Revolut embedded widget — Awin's own docs require this)
- [ ] MasterTag blocked for preview personas and admin impersonation sessions
- [ ] Conversion events blocked when cookie consent = `'declined'` on the browser side
- [ ] S2S tracking fires regardless of client consent (contract-performance basis)
- [ ] Order reference is unique and deterministic per payment (idempotent dedup)
- [ ] Sale payload never contains PII (email, name, card details)
- [ ] Privacy policy updated to mention Awin in the third-party processors list
- [ ] Cookie policy updated with the `awc` cookie purpose + retention
- [ ] DSR export includes the `awc` cookie value where present

---

## 8. Rollout Plan

### Phase 1 — Scaffold (Day 1, ~4h)
1. Create branch `awinIntegrate` off `main` ✅ (done)
2. Add `config/awin.php` and env templates
3. Create `CaptureAwcCookie` middleware + register
4. Add `awc` to `EncryptCookies::$except`
5. Extend CSP header for Awin domains
6. Migration for payments table Awin columns

### Phase 2 — Service layer + job (Day 1, ~4h)
7. Build `AwinTrackingService` with full unit tests
8. Build `FireAwinConversionJob` with dispatch + retry + idempotency
9. Wire the job into `WebhookController::handleOrderCompleted()` and `PaymentController::confirmPayment()`
10. Write `AwinConversionFlowTest` feature test with HTTP fake

### Phase 3 — Frontend (Day 2, ~3h)
11. Build `utils/awinTracking.js` (loadMasterTag, fireConversion, shouldLoadAwin)
12. Wire into `app.js` boot + `$router.afterEach`
13. Hook `cookieConsent.acceptCookies`/`declineCookies`
14. Fire browser pixel in `CheckoutPage.vue` after GA4 purchase event
15. Update `subscriptionService.js` to thread `awin` response object through

### Phase 4 — Dev deploy + test (Day 2, ~2h)
16. Build + deploy to `csjones.co/fynla` (with `AWIN_ENABLED=true` just for the test window)
17. Use Awin's publisher test link to visit `csjones.co/fynla?awc=TEST-CLICK-REF`
18. Verify `awc` cookie set (browser devtools)
19. Register a test user, subscribe via Revolut sandbox
20. Verify browser pixel fires (network tab, `sread.img`)
21. Verify S2S fires (`payments.awin_fired_at` populated, storage log shows successful POST)
22. Verify conversion appears in Awin dashboard

### Phase 5 — Production (Day 3, ~1h + monitoring)
23. Merge `awinIntegrate` → `dev` (PR)
24. After dev smoke test, merge `dev` → `main` (PR)
25. Build + deploy to `fynla.org` with `AWIN_ENABLED=true`
26. Monitor `storage/logs/laravel.log` for the first 24h for `[awin]` warnings
27. Monitor Awin dashboard daily for first week to verify conversions match Revolut revenue

---

## 9. Decisions (confirmed 15 Apr 2026)

All six open questions resolved with the default positions. The plan is locked and ready to execute.

1. **`awc` cookie capture is NOT gated by consent.** — The click cookie is written on arrival regardless of cookie-banner state. It is treated as functional/attribution-only, 365-day TTL, httpOnly/Secure/SameSite=Lax. Only the client-side MasterTag and browser pixel are consent-gated.
2. **Single commission group for v1: `SUB`.** — All Fynla subscription tiers (Student, Standard, Family, Pro) are mapped to the same group. `AwinTrackingService::commissionGroupFor()` returns `'SUB'` unconditionally for now but is structured so per-tier groups can be added later by editing a single `match` block.
3. **Voucher code = `$payment->discountCode?->code ?? ''`.** — The existing `revolutLive` discount codes are passed straight through to Awin as the `voucher_code` param. No transformation, no prefix.
4. **Customer acquisition = "no prior `payments.status = completed`".** — `isCustomerAcquisition(User $user, int $excludePaymentId): bool` returns `true` iff the user has zero completed payments other than the one currently being fired. First purchase returns `new`; any subsequent purchase (including a re-subscription after cancel) returns `existing`.
5. **Refunds are out of scope for v1.** — Revolut refunds do NOT fire an Awin refund event. Documented as a known limitation. A `FireAwinRefundJob` will be added in v2 when we have the Revolut refund webhook wired up.
6. **Mobile app uses S2S only.** — Capacitor iOS does not load the MasterTag or browser pixel. All mobile subscription purchases still route through the Revolut webhook, which dispatches `FireAwinConversionJob`, so attribution is preserved. No mobile-specific code needed.

---

## 10. Success Criteria

- [ ] Test transaction placed via Awin publisher test link shows up in Awin dashboard within 2 hours
- [ ] Both browser pixel and S2S fire for the same test transaction, deduplicated by Awin to a single attributed sale
- [ ] `AWIN_ENABLED=false` cleanly disables all tracking (no MasterTag, no pixel, no S2S, no cookie write)
- [ ] Preview personas purchasing via preview-mode stubs do NOT fire any Awin events
- [ ] CheckoutPage.vue does NOT carry the MasterTag
- [ ] Laravel log shows zero `[awin]` errors after 24h in production
- [ ] Zero regression in existing Plausible, Meta Pixel, or GA4 tracking

---

## 11. References

- Raw integration snippets: `/Users/CSJ/Desktop/fynla/awin/integration.md`
- Awin onboarding screenshots: `/Users/CSJ/Desktop/fynla/awin/img-*`
- Research report (internal): generated by Explore subagent 15 Apr 2026
- Revolut payment flow: `fynlaBrain/currentState/PaymentSubscription.md`
- Existing analytics patterns: `resources/js/services/analyticsService.js`, `resources/views/app.blade.php` (Meta Pixel lines 73-89)

