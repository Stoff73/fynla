# Awin Installation Check — Guide vs. Implementation

*Date: 16 April 2026*
*Branch: `awinPlusDev`*
*Transaction with error: FYN-PAY-33*

---

## Error Analysis

The Awin Tracking Diagnosis page reports **1 warning** on transaction `FYN-PAY-33`:

> **"Parameter Values not matching across Tracking Tags"**
> The following parameters contain values that do not match: `AWIN.Tracking.Sale.voucher` or `&vc=`

What this means: the voucher code (`vc`) value in the **Conversion Tag** (MasterTag JavaScript) does not match the `vc` value in the **Server-to-Server** call and **Fall-back Conversion Pixel**.

- **Conversion Tag** (MasterTag): `vc=` (empty)
- **Server-to-Server**: `vc=` has a value
- **Fall-back Pixel**: `vc=` has a value

The MasterTag is reading `AWIN.Tracking.Sale.voucher` and finding it undefined, so it sends empty. Our S2S and pixel correctly send the value.

---

## Root Cause

**BUG: Wrong property name on `AWIN.Tracking.Sale` object.**

In `resources/js/utils/awinTracking.js` line 135, we set:

```javascript
window.AWIN.Tracking.Sale = {
  ...
  voucherCode: params.voucher_code || '',   // <-- WRONG
  ...
};
```

The Awin MasterTag expects the property `voucher`, NOT `voucherCode`.

From the Awin installation guide (page 3 — Conversion Tag):

```javascript
AWIN.Tracking.Sale.voucher = "{{voucher_code}}";
```

The error message itself confirms this: it says `AWIN.Tracking.Sale.voucher` is the expected property name.

**Fix:** Change `voucherCode` to `voucher` on line 135 of `awinTracking.js`.

---

## Full Guide vs. Implementation Comparison

### Step 2 — MasterTag (All Pages)

| Requirement | Guide | Our Implementation | Status |
|---|---|---|---|
| Script source | `https://www.dwin1.com/126105.js` | `https://www.dwin1.com/126105.js` (via `VITE_AWIN_MERCHANT_ID` or default `126105`) | PASS |
| Script attributes | `type="text/javascript" defer="defer"` | `defer=true` AND `async=true` | ISSUE |
| Script placement | Just above closing `</body>` tag | Appended to `<head>` | ISSUE |
| All pages except payment | Yes, all pages | Yes, via `router.afterEach()` with `shouldLoadAwin()` check | PASS |
| Excluded from payment pages | Pages that display/process sensitive payment information | `EXCLUDED_ROUTE_NAMES`: Checkout, checkout, auth.checkout, payment.confirm | PASS |
| Conditional on cookie consent | Not mentioned in guide | Yes, via `hasConsent()` check in `cookieConsent.js` | PASS (extra safety) |

**Issues found:**

1. **`async` attribute should not be present** — The guide specifies only `defer="defer"`. Adding `async` changes execution timing: with both attributes, `async` takes precedence and the script executes as soon as it downloads rather than waiting for DOM parsing. This can cause the MasterTag to execute before `AWIN.Tracking.Sale` is populated on the confirmation page.

2. **Script in `<head>` instead of before `</body>`** — The guide says "as late as possible, for example by placing the HTML script element just above the closing body tag". Our code appends to `document.head`. In an SPA this is less critical since the DOM is already parsed, but it deviates from the documented requirement.

### Step 2 — Order Confirmation Page (Checkout Page)

| Requirement | Guide | Our Implementation | Status |
|---|---|---|---|
| Fall-back Conversion Pixel | `<img>` tag with sread.img URL + all parameters | Injected via `fireConversion()` in `awinTracking.js` lines 151-173 | PASS |
| Pixel parameters match S2S | Same values for amount, ref, parts, vc, cr, ch, customeracquisition | Same `params` object feeds both pixel and S2S | PASS |
| Conversion Tag (Sale object) | `AWIN.Tracking.Sale.voucher = "{{voucher_code}}"` | `voucherCode: params.voucher_code` | **FAIL** |
| Sale object property: amount | `AWIN.Tracking.Sale.amount` | `amount: params.amount` | PASS |
| Sale object property: orderRef | `AWIN.Tracking.Sale.orderRef` | `orderRef: params.order_ref` | PASS |
| Sale object property: parts | `AWIN.Tracking.Sale.parts` | `parts: \`${...}:${...}\`` | PASS |
| Sale object property: currency | `AWIN.Tracking.Sale.currency` | `currency: params.currency` | PASS |
| Sale object property: voucher | `AWIN.Tracking.Sale.voucher` | `voucherCode: params.voucher_code` | **FAIL** |
| Sale object property: test | `AWIN.Tracking.Sale.test` | `test: '0'` | PASS |
| Sale object property: channel | `AWIN.Tracking.Sale.channel` | `channel: 'aw'` | PASS |
| MasterTag present on confirmation | Must be on the page to read Sale object | `loadMasterTag()` called after setting Sale object | PASS (with timing concern) |

**Issues found:**

1. **CRITICAL: `voucherCode` must be `voucher`** — This is the direct cause of the Awin warning. The MasterTag reads `AWIN.Tracking.Sale.voucher` but we set `AWIN.Tracking.Sale.voucherCode`. The MasterTag sees `voucher` as undefined and sends `vc=` (empty) in the conversion tag, while our S2S and pixel correctly populate `vc` from the backend.

2. **MasterTag re-injection timing** — The MasterTag is excluded from checkout routes, then `fireConversion()` calls `loadMasterTag()` to re-inject it after payment success. The MasterTag script must download and execute before it can read the Sale object. With the `async` attribute, execution timing is unpredictable. However, this is a secondary concern — the MasterTag DID fire (it appears in the tracking diagnosis), it just read the wrong property name.

### Step 3 Part 1 — Server-side Cookie (AWC Capture)

| Requirement | Guide | Our Implementation | Status |
|---|---|---|---|
| Capture `awc` from URL query param | `$_GET['awc']` | `$request->query('awc')` in `CaptureAwcCookie` middleware | PASS |
| Set as cookie | `setcookie("awc", ...)` | `Cookie::create(name: 'awc', ...)` via Laravel | PASS |
| HTTPOnly flag | Required | `httpOnly: true` | PASS |
| Secure flag | Required | `secure: true` | PASS |
| Domain | Site domain | `config('awin.cookie_domain')` = `fynla.org` | PASS |
| Lifetime | 365 days (`time() + 60*60*24*365`) | `86400 * config('awin.cookie_lifetime_days', 365)` | PASS |
| Cookie path | `/` | `path: '/'` | PASS |
| SameSite | Not specified in guide | `sameSite: 'lax'` | PASS (extra safety) |
| Excluded from encryption | Required (raw value needed by Awin) | Added to `EncryptCookies::$except` | PASS |
| Every page | "Add to every page of your website" | Middleware registered in HTTP kernel | PASS |

**No issues found.** Cookie implementation matches the guide exactly plus sensible extras (SameSite, unencrypted).

### Step 3 Part 2 — Server-to-Server (Send Transaction Data)

| Requirement | Guide | Our Implementation | Status |
|---|---|---|---|
| URL | `https://www.awin1.com/sread.php` | `config('awin.s2s_base_url')` = `https://www.awin1.com/sread.php` | PASS |
| `tt=ss` | Tracking type | `'tt' => 'ss'` | PASS |
| `tv=2` | Tracking version | `'tv' => '2'` | PASS |
| `merchant=126105` | Merchant ID | `config('awin.merchant_id')` = `126105` | PASS |
| `amount={{order_subtotal}}` | Total transaction, 2dp | `number_format(((int) $payment->amount) / 100, 2, '.', '')` | PASS |
| `ch=aw` | Channel | `'ch' => 'aw'` | PASS |
| `parts={{group}}:{{amount}}` | Commission group:sale amount | `"{$params['commission_group']}:{$params['sale_amount']}"` | PASS |
| `vc={{voucher_code}}` | Voucher/discount code | `$payment->discountCode?->code ?? ''` | PASS |
| `cr={{currency_code}}` | ISO currency (GBP) | `$payment->currency ?: 'GBP'` | PASS |
| `ref={{order_ref}}` | Order reference | `"FYN-PAY-{$payment->id}"` | PASS |
| `cks={{awc}}` | AWC cookie value | `$payment->awin_cks` (captured at createOrder time) | PASS |
| `customeracquisition={{value}}` | new or existing | Based on prior completed payments | PASS |
| Idempotent | Should not double-fire | `awin_fired_at` guard in `FireAwinConversionJob` | PASS |
| Retry on failure | Recommended | 3 attempts: 30s / 5min / 30min backoff | PASS |

**No issues found.** S2S implementation matches the guide exactly.

### Additional Checks

| Check | Status | Notes |
|---|---|---|
| CSP headers whitelist Awin domains | PASS | `dwin1.com` and `awin1.com` in script-src/img-src/connect-src (conditional on `awin.enabled`) |
| Admin/preview users excluded | PASS | `! $user->is_admin` check in PaymentController; preview users cannot reach payment flow |
| AWC cookie persisted on Payment model | PASS | `awin_cks` column populated at `createOrder` time (only point where browser cookie is accessible) |
| Frontend and S2S use same source data | PASS | Both read from same Payment model (discountCode relationship, amount, order_ref) |

---

## Summary of Issues

| # | Severity | Component | Issue | Fix |
|---|----------|-----------|-------|-----|
| 1 | **CRITICAL** | `awinTracking.js:135` | Sale object property `voucherCode` should be `voucher` | Rename property |
| 2 | Medium | `awinTracking.js:75` | MasterTag loaded with `async=true` — guide says `defer` only | Remove `script.async = true` |
| 3 | Low | `awinTracking.js:77` | MasterTag appended to `<head>` — guide says before `</body>` | Change to `document.body.appendChild(script)` |

**Issue #1 is the direct cause of the Awin tracking diagnosis warning.** The MasterTag reads `AWIN.Tracking.Sale.voucher` but we populate `AWIN.Tracking.Sale.voucherCode`. The MasterTag sends `vc=` (empty) while our S2S and pixel send the correct value, causing the mismatch.

Issues #2 and #3 are deviations from the guide that could cause timing problems but are not the cause of the current error. They should still be fixed for compliance.

---

## Recommended Fixes

**Fix 1 — Property name (CRITICAL):**
```javascript
// awinTracking.js line 135
// Before:
voucherCode: params.voucher_code || '',
// After:
voucher: params.voucher_code || '',
```

**Fix 2 — Remove async attribute:**
```javascript
// awinTracking.js line 75
// Before:
script.defer = true;
script.async = true;
// After:
script.defer = true;
// Remove: script.async = true;
```

**Fix 3 — Append to body instead of head:**
```javascript
// awinTracking.js line 77
// Before:
document.head.appendChild(script);
// After:
document.body.appendChild(script);
```

After applying these fixes, rebuild with `./deploy/fynla-org/build.sh`, upload `public/build/` to production, and re-run the Awin tracking test to verify all three tracking methods report matching `vc` values.
