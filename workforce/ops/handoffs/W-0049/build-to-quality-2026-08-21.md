# W-0049 — build-lead (fix-batch-F) → quality-lead

## Done

Consent now exists on the server, and the affiliate middleware obeys it.

**New — the one home**
- `app/Services/Consent/CookieConsentService.php` — the only thing that writes the
  decision, and it always writes both halves: the `user_consents` record (the
  evidence) and the `fyn_cookie_consent` cookie (the transport the global
  middleware reads). They cannot drift because nothing else writes either.
- `app/Http/Controllers/Api/CookieConsentController.php` + `routes/api.php:145-146`
  — `POST /api/cookie-consent`, the single write path for every surface.
- `app/Support/AwinClickCookie.php` — one declaration of the `awc` cookie's name,
  domain and flags. Two callers need them (capture, and clear-on-withdrawal); a
  clear whose attributes do not match the cookie is silent.
- `database/migrations/2026_08_21_140000_add_anonymous_subject_to_user_consents_table.php`
  — `user_consents.user_id` nullable, new `subject_token` char(64), unique with
  `consent_type` + `version`.

**Changed**
- `app/Http/Middleware/CaptureAwcCookie.php:51,53,60` — sets `awc` only on
  `accepted`; expires it on any request where the decision is not `accepted`.
- `app/Jobs/FireAwinConversionJob.php:91` — no conversion without
  `payments.awin_cks`.
- `app/Models/UserConsent.php:32,41,102,137` — `TYPE_COOKIES`,
  `recordAnonymousConsent()`, `claimAnonymousConsents()`.
- `app/Http/Controllers/Api/AuthController.php:662` — claims the pre-account
  consent onto the new user at registration.
- `app/Http/Controllers/Api/GDPRController.php:71` — `cookies` filtered out of the
  general consent PUT.
- `app/Http/Middleware/EncryptCookies.php` — both consent cookies excepted.
- `app/Http/Middleware/PreviewWriteInterceptor.php:54` — endpoint excluded.
- `app/Providers/RouteServiceProvider.php:80` — named `cookie-consent` limiter.
- `resources/js/utils/cookieConsent.js`, `public/pages/js/cookie-consent.js`,
  `CookieBanner.vue`, `Register.vue` — both client copies now read the cookie and
  POST to the one endpoint; `localStorage` removed from both.

**Tests, all green under `DB_DATABASE=laravel_testing_b`**
- `tests/Feature/Consent/CookieConsentTest.php` — 15
- `tests/Feature/Middleware/CaptureAwcCookieTest.php` — 6
- `tests/Feature/Payment/FireAwinConversionJobTest.php` — 10
- Regression set (`AwinConversionFlowTest`, `GDPRApiTest`, `Unit/Services/GDPR`,
  `Unit/Services/Marketing`) — 151 together
- `resources/js/__tests__/cookieConsent.spec.js` — 8
- `./vendor/bin/pint --test` clean on every PHP file touched

## Not done, and why

- **`security-reviewer` (acceptance 5).** Not mine to sign. The design notes it
  needs are in the board item's working notes; the one contestable input is the
  `awc` field on the endpoint, and I have written out both the argument for it and
  what deleting it would cost.
- **No historic backfill.** As instructed. The record starts at the first decision
  made after this ships.
- **No browser verification.** My dispatch reserves Rule 14's loop for a
  persona-tester.
- **No `/m` bundle rebuild.** The coordinator owns that; the funnel banner change
  is not live on `/m` until it happens.
- **The wall itself (W-0050) is untouched.** Parked by CSJ.
- **I did not add a consent check to `PaymentController.php:340/506`.** It reads
  the `awc` cookie into `awin_cks`, and on the single request where a legacy
  cookie is being expired the controller runs before the middleware clears it. The
  window is one request, and checkout is many requests after a landing, so a third
  consent check would be defensive code in a path I otherwise did not change.
  Named so a reviewer can disagree.

## What you need that isn't obvious from the artefacts

**Affiliate attribution changes shape, and someone should decide whether they
like it.** An affiliate landing is one request (`/?awc=...`) and the banner is
answered after it. Gating capture on consent therefore loses the click reference
for every affiliate visitor — unless it travels with the decision, which is what
the endpoint's optional `awc` field does. This is not a workaround for the gate:
nothing is stored before consent. It is a decision that the moment of capture
moves from the landing request to the accept. If a reviewer would rather not have
a client-supplied value on an unauthenticated endpoint, deleting the field is a
three-line change and the consequence is that affiliate attribution stops working
for visitors who accept after landing.

**The banner writes the cookie before it posts.** Otherwise a slow or failed
request would leave a visitor who has just clicked Accept still behind the wall.
The server's response re-sets the same host-only cookie and adds the subject
token. Both consent cookies are deliberately host-only (no domain) so the
browser-written and server-written cookies are the same cookie rather than two
that shadow each other unpredictably.

**Testing gotcha that cost me a cycle:** Laravel only sends cookies on JSON
requests when `withCredentials()` is set (`prepareCookiesForJsonRequest`). Without
it, `withUnencryptedCookie(...)` on a `postJson`/`getJson` is silently dropped and
the test passes for the wrong reason.

**A cleared cookie's `getValue()` is `null`, not `''`.** Assert on that and on an
expiry in the past.

**The one architecture-suite failure is not mine.** `--testsuite=Architecture`
reports 1 failure at `app/Http/Requests/Retirement/StoreDBPensionRequest.php:7`,
a `use App\Models\DBPension;` added by another agent's in-flight W-0032 work
(confirmed via `git diff HEAD` on that file). Everything else: 148 pass.

## Assumptions I made

- **I assumed a distinct consent type is right, not `TYPE_MARKETING`.** The item
  pointed at `TYPE_MARKETING`; I used a new `cookies` type because conflating
  cookie/tracking consent with email-marketing consent would make one button
  stand for two different lawful bases. If compliance wants them merged, that is
  a rename plus a data decision, not a redesign.
- **I assumed a cookie is an acceptable gate for the middleware**, with the
  database row as the evidence rather than the gate. The middleware is global: it
  runs before the session starts and before any guard resolves a user, on every
  request. A per-request database read there is not viable, and `$request->user()`
  is not reliably resolvable at that point.
- **I assumed a browser-forged `accepted` cookie is not a threat worth designing
  against.** The only person harmed is the forger, who is thereby consenting by
  action. The evidence record is the database row, not the cookie.
- **I assumed adding `cookies` to `UserConsent::CURRENT_VERSIONS` is safe for the
  native privacy screen.** It renders a fixed list of toggles and only reads
  `marketing` (`ios-native/Fynla/Features/Privacy/PrivacySettingsModel.swift:30`),
  so the new type surfaces only in the consent *history* list, which is honest.
  `needs_reconsent` grows by one entry; nothing acts on it
  (`ConsentModels.swift:13` decodes it and no view uses it).
- **I assumed the endpoint should be usable by an authenticated visitor too**, and
  records against the user in that case rather than the anonymous subject.

## Surfaces covered / not covered

- **web** — covered. Both the SPA banner and the registration gate.
- **`/m`** — covered, and checked rather than assumed. `/m`
  (`resources/views/mobile-host.blade.php:33`) iframes the real funnel same-origin,
  so a phone visitor sees the funnel banner and the cookies it writes are readable
  at `/m/app`. The mobile SPA loads no analytics and no affiliate tag, and every
  route in `resources/mobile/router.js:46-76` except `/login` is
  `meta: { auth: true }` — there is no anonymous surface there to ask on. Server-side
  enforcement reaches every `/m` request because `CaptureAwcCookie` is global
  (`app/Http/Kernel.php:106`). **Needs the `/m` bundle rebuilt before it is live.**
- **iOS** — not covered and not in scope. Native has its own server-backed
  versioned consent system; the new type appears in its consent history and
  nowhere else.
