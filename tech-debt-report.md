# Tech Debt Report — Session 15 April 2026

**Files analysed:** 14 files I authored or meaningfully modified this session (Awin integration + related fixes)
**Issues found:** 0
**Severity breakdown:** 0 critical, 0 warnings, 0 suggestions

## Scope

Audited only files I authored or modified with substantive content this session. Files merged in from `origin/dev` (PR #210 insight pages, PR #211 email templates + review carousel + LandingPage tweaks, persona modal mobile fix, onboarding tweaks) were already reviewed in their own branches before the merge and are out of scope for this session audit.

**In-scope files:**

Backend (7):
- `app/Services/Marketing/AwinTrackingService.php` — 161 lines, new
- `app/Jobs/FireAwinConversionJob.php` — 116 lines, new
- `app/Http/Middleware/CaptureAwcCookie.php` — 51 lines, new
- `app/Http/Middleware/SecurityHeaders.php` — modified (Meta Pixel CSP + conditional Awin CSP)
- `app/Http/Middleware/EncryptCookies.php` — modified (`awc` in `$except`)
- `app/Http/Kernel.php` — modified (register CaptureAwcCookie)
- `app/Http/Controllers/Api/PaymentController.php` — modified (Awin capture + dispatch + response)
- `app/Http/Controllers/Api/WebhookController.php` — modified (Awin dispatch)
- `app/Models/Payment.php` — modified (4 awin_* fillables + datetime cast)
- `app/Services/LifeStage/LifeStageService.php` — 1-line typo fix (`current_value` → `current_fund_value`)

Config + migration (2):
- `config/awin.php` — 108 lines, new
- `database/migrations/2026_04_15_153100_add_awin_tracking_to_payments_table.php` — 60 lines, new

Frontend (4):
- `resources/js/utils/awinTracking.js` — 184 lines, new
- `resources/js/utils/cookieConsent.js` — modified (accept/decline/init hooks)
- `resources/js/router/index.js` — modified (afterEach hook)
- `resources/js/views/Auth/CheckoutPage.vue` — modified (fireAwinConversion after GA4)

## Audit Results

### Category 1: Duplicate Code
**✅ No findings.**
- `AwinTrackingService` methods (`buildSaleParams`, `isCustomerAcquisition`, `commissionGroupFor`, `orderRefFor`, `fireServerToServer`) have no name collisions with existing services
- No local `formatCurrency()`, `spinner`, or `scrollbar` reimplementations in the frontend utils
- HTTP call uses the `Http` facade, not a re-invented cURL wrapper

### Category 2: Dead & Redundant Code
**✅ No findings.**
- `grep -n "TODO|FIXME|HACK|XXX"` across all in-scope files: 0 matches
- `grep -n "console.log|dd(|dump("`: 0 matches
- All new imports are used; no unused computed properties or variables
- One catch block in `AwinTrackingService::fireServerToServer` catches `\Throwable` — not empty, logs via `logError` and returns false per the documented "never throw" contract

### Category 3: Convention Violations
**✅ No findings.**
- **strict_types**: All 5 new PHP files start with `declare(strict_types=1);` ✓
- **Hardcoded tax values**: grep for `'2025/26'|'2026/27'|12570|20000|60000|325000|175000`: 0 matches ✓
- **Banned colours**: grep for `amber-|orange-` in `awinTracking.js` + `cookieConsent.js`: 0 matches ✓
- **DB facade in controllers**: `PaymentController` already uses `DB::transaction()` for payment activation (pre-existing, out of scope for this audit) — Awin additions don't introduce new DB facade uses
- **Type hints**: all new methods on `AwinTrackingService` and `FireAwinConversionJob` have full parameter and return type hints
- **`sole` vs `individual`**: no ownership types touched

### Category 4: Complexity & Maintainability
**✅ No findings.**
- File sizes (all well under 500 lines):
  - `AwinTrackingService.php` — 161 lines
  - `FireAwinConversionJob.php` — 116 lines
  - `CaptureAwcCookie.php` — 51 lines
  - `awinTracking.js` — 184 lines
  - `config/awin.php` — 108 lines
- Longest method in new code is `fireServerToServer` at ~50 lines — at the complexity threshold but justified (query building + try/catch + success/error logging paths). No extraction warranted.
- No nesting beyond 2 levels
- All magic values (`tt=ss`, `tv=2`, `ch=aw`) are documented inline and come from the Awin spec — not "magic numbers" in the code-smell sense

### Category 5: Security Concerns
**✅ No findings.**
- `CaptureAwcCookie` validates input: `is_string($awc) && $awc !== '' && strlen($awc) <= 255` prevents cookie bombing and type confusion
- Cookie attributes are correct: `HttpOnly`, `Secure`, `SameSite=Lax`, domain-scoped
- `awc` exempted from Laravel's `EncryptCookies` so Awin receives the raw click reference — intentional, documented in the middleware docblock
- CSP extensions to `SecurityHeaders` are gated on `config('awin.enabled')` for the Awin domains (no unnecessary widening when disabled); Meta Pixel domains are unconditional since `app.blade.php` loads the pixel on every page
- No user input interpolated into SQL
- `fireServerToServer` uses Laravel's `Http` facade which encodes query params correctly (explicitly avoids the Awin sample's 3 bugs: URL encoding, `parts` format, response ignore)
- Payment data (amount, voucher, customer acquisition) is never logged to `laravel.log` as PII; only the `order_ref` identifier and HTTP status are logged

### Category 6: Inconsistency with Existing Patterns
**✅ No findings.**
- `AwinTrackingService` follows the constructor-free pure-service pattern (it uses `StructuredLogging` trait like other services)
- `FireAwinConversionJob` uses `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels` — the same trait stack as `RunMonteCarloSimulation`
- `CaptureAwcCookie` follows the same short-circuit-on-disabled pattern used by other conditional middleware
- Controller wiring uses the same `DB::transaction()` + post-transaction pattern already in place for payment confirmation
- Frontend util mirrors `analyticsService.js` and the GA loader inside `cookieConsent.js`
- All `[awin]` log entries use the structured-logging prefix convention

## Verdict

**Nothing to fix. Commit the one outstanding file (CLAUDE.md metrics update) and move on to session wrap-up.**

Notes for future reference:
- The Awin integration is well-isolated in its own `App\Services\Marketing` namespace — future tracking integrations (Google Ads, TikTok Pixel, etc.) should follow the same pattern
- The dual-track (browser + S2S) with idempotency via `payments.awin_fired_at` is a reusable pattern for any attribution integration — worth extracting into a generic `AttributionJob` base class if a second network gets added

---
*Generated by tech-debt-session skill — 2026-04-15 21:29 BST*
