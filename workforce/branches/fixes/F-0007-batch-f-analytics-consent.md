---
id: F-0007
type: fix
parent: core/constitution/05-perimeter.md
applies: [core/constitution/07-quality-bar.md, core/constitution/08-process.md]
surfaces: [web, m]
consistency_checked: 2026-08-21T18:40:00Z
status: active
---

# F-0007 — Batch F: analytics and affiliate consent

**Agent:** build-lead (`fix-batch-F`) · **Written:** 2026-08-21
**Branch:** `dev` (shared working tree — nothing committed, no PR, by instruction)
**Items:** W-0047 (high), W-0049 (high) · both `status: handoff`, `handoff_to: quality-lead`

---

## 1. The dispatch, verbatim

> You are `fix-batch-F`. Two live production data-integrity defects on the analytics and affiliate-tracking surface.
>
> **Scope boundary — read this first so you do not solve the wrong problem**
>
> There is a third, related item, **W-0050 (the cookie wall: you cannot register without consenting to analytics and affiliate tracking)**. **CSJ has PARKED W-0050 today.** Their ruling, recorded in that board item: clicking Accept gives consent and registration proceeds, so it is not a functional blocker, and the GDPR Article 7(4) "freely given" question waits until the functional defect board is clear.
>
> **Your two items are NOT parked**, because they are functional defects independent of how the consent question is eventually answered:
> - W-0047 sends real analytics data to the live production property from developer machines and test runs.
> - W-0049 means "Decline" does not actually decline — the app tracks the user anyway, and cannot stop.
>
> **Do not redesign the consent banner, do not change what registration requires, do not remove the wall.** Fix what is broken behind it.
>
> **Module and patterns**
> - Vault: `/Users/CSJ/Desktop/fynlaBrain/v083/03-AUTH-SECURITY.md`, `/Users/CSJ/Desktop/fynlaBrain/v083/11-CONFIG-DEPLOY.md`, `/Users/CSJ/Desktop/fynlaBrain/Auth.md`
> - Conventions: `app/Http/CLAUDE.md`, `resources/js/CLAUDE.md`, `tests/CLAUDE.md`
> - Known code: `resources/js/utils/cookieConsent.js`, `app/Http/Middleware/CaptureAwcCookie.php` (registered in the **global** stack at `app/Http/Kernel.php:106`), `app/Models/UserConsent.php` (five consent types), `app/Http/Controllers/Api/GDPRController.php` (`getConsentHistory` already exists), `config/services.php`.
> - Rule 19: this touches web AND the `/m` pathway (`resources/mobile/`) — `/m` has its own isolated bundle with its own utilities. A fix in `resources/js/` does **not** reach `/m`. Check both.
>
> **Item 1 — W-0047 (high)** `workforce/ops/board/W-0047-ga-falls-back-to-the-production-measurement-id.md`
>
> Google Analytics falls back to the **hardcoded production measurement ID** when `VITE_GA_ID` is unset, so local development and test runs report into the live property. Confirmed live during testing: clicking Accept on `localhost` fired `googletagmanager.com/gtag/js?id=G-3Y8DL3QB09`, the production ID, from a developer machine.
>
> This is pollution of the business's own metrics — `intelligence-lead` reads Plausible and this property for the north-star numbers. A fallback to production is the wrong default in both directions: it should fail closed (no analytics) when unconfigured, never fall back to the live property.
>
> **Item 2 — W-0049 (high)** `workforce/ops/board/W-0049-consent-is-not-enforced-or-recorded-server-side.md`
>
> Two distinct failures in one item:
>
> **(a) `awc` is set regardless of consent.** `AWIN_ENABLED` is **true on production**. `CaptureAwcCookie` sits in the global middleware stack with **no consent check**, setting a 365-day **HttpOnly** `awc` cookie on **every visitor**. Because it is HttpOnly, the client-side `declineCookies()` is *structurally incapable* of clearing it — this is not a bug in `declineCookies()`, it is a layering error. The fix has to be server-side.
>
> **(b) Consent cannot be demonstrated.** Consent exists only as a `localStorage` string with no server record, and has done since 2026-04-07. **Historic consent is not repairable retrospectively** — do not attempt a backfill and do not fabricate records for existing users. What you can fix is going forward: record consent server-side when it is given, and make the middleware honour it.
>
> Note `UserConsent` with five consent types and `GDPRController::getConsentHistory` already exist — **Rule 20 applies: find every mechanism that records or reads consent and converge on ONE, rather than adding a second parallel one.** If you add a new consent store beside `UserConsent`, that is a violation, not a fix.
>
> An anonymous visitor has no user row, so think carefully about where pre-registration consent lives and say plainly in your notes what you chose and why.
>
> **Rules that bind you**
> - **Rule 15:** no decorative icons anywhere you touch.
> - **Rule 19:** web AND `/m`, explicitly verified per surface.
> - **Rule 20:** one behaviour, one home, all surfaces and all paths.
> - **British spelling** in user-facing text.
> - **Never edit `.env`** to work around anything. Reading it for a key is fine and expected.
> - Validate only at system boundaries; do not add defensive code to paths you did not change.
>
> **Environment**
> - Your test database is **`laravel_testing_b`**. Every run: `DB_DATABASE=laravel_testing_b ./vendor/bin/pest <paths>`. Without it you get deadlocks and 0-assertion failures that look like real failures.
> - **`pgrep -f "vendor/bin/pest"` before running.** I have a full suite on `_a`; other agents are on `_c`, `_d`, `_e`. Vitest timeouts at 5000ms under parallel load are contention — re-run in isolation before believing them, never raise the timeout.
> - Migrations: `php artisan migrate --path=database/migrations/<file>.php` only. **Never bare `migrate`, never `migrate:fresh`/`migrate:refresh`.** Five untracked 2026_08_21_* migrations already exist — pick a distinct timestamp.
> - Laravel `:8000`, Vite `:5173` are up. **Do not rebuild the `/m` bundle** — that is mine, ask me.
> - **Production is untouched and stays untouched.** Do not run anything against it. If you need a production fact, ask me and I will run a read-only query.
> - **Do not create, delete or provision users.** Users 16, 17, 20, 30, 31 are live reproduction data for other agents.
> - The formatter deletes an unreferenced `use` at the moment it runs — **add an import and its first usage in the same edit.**
>
> **What you do NOT do**
> - **Do not commit, do not open a PR, do not deploy.** 554 uncommitted paths from five other agents share this tree.
> - **Do not browser-verify your own work** — a persona-tester closes Rule 14's loop independently.
> - **No colour or palette changes** — CSJ parked that workstream today.
>
> **Deliverables**
> 1. Both items fixed, targeted test families green under `DB_DATABASE=laravel_testing_b`, `./vendor/bin/pint` clean on files you touched.
> 2. Both board items → `status: handoff`, `handoff_to: quality-lead`, append-only working notes with file:line evidence, decisions and reasoning, and what you deliberately did not fix.
> 3. Branch document **`workforce/branches/fixes/F-0007-batch-f-analytics-consent.md`** per `workforce/ops/FORMATS.md` — a complete seed for a replacement: dispatch verbatim, DONE with evidence, IN FLIGHT and its exact state, NOT STARTED, decisions taken, dead ends, environment state.
> 4. A concise report to me: what you changed, test output, what you could not reach, and anything needing a CSJ decision (stated with a recommendation).
>
> **Report the moment you are blocked — do not sit idle.** I unblock tooling, environments and test data. Rule 22: at ~900k context, stop, write the handover into your branch document, return it to me.

**Amendments received since dispatch:** none.

---

## 2. Status

| Item | Status | Board file |
|---|---|---|
| W-0047 GA falls back to the production measurement id | **DONE** | `status: handoff`, `handoff_to: quality-lead`, working notes appended |
| W-0049 consent not enforced or recorded server-side | **DONE** | same |

**Nothing is mid-edit. No file is half-written.** Every change on disk is complete
and self-consistent. Handoff notes exist at
`workforce/ops/handoffs/W-0047/build-to-quality-2026-08-21.md` and
`workforce/ops/handoffs/W-0049/build-to-quality-2026-08-21.md`.

---

## 3. DONE, with evidence

### W-0047

| Change | Evidence |
|---|---|
| Fallback removed | `resources/js/utils/cookieConsent.js:22` — `import.meta.env.VITE_GA_ID \|\| ''`; the early return on a falsy id was already there at `:145` |
| Production id moved to the production build | `deploy/fynla-org/build.sh:40` |
| Documented for production | `deploy/fynla-org/.env.production.example:127` |
| Staging deliberately unset, recorded so nobody "fixes" it | `deploy/csjones-fynla/build.sh:36-41` (comment only, no export) |
| Local default empty, with the reason | `.env.example:116` |

`.env` untouched.

### W-0049

**New files**
- `app/Services/Consent/CookieConsentService.php` — the one home.
- `app/Http/Controllers/Api/CookieConsentController.php` — the one write path.
- `app/Support/AwinClickCookie.php` — the one declaration of the `awc` cookie.
- `database/migrations/2026_08_21_140000_add_anonymous_subject_to_user_consents_table.php`
- `tests/Feature/Consent/CookieConsentTest.php` (15)
- `tests/Feature/Middleware/CaptureAwcCookieTest.php` (6)
- `resources/js/__tests__/cookieConsent.spec.js` (8)

**Modified — mine only. The tree also holds five other agents' batches; do not attribute.**
- `app/Http/Middleware/CaptureAwcCookie.php` — rewritten: consent gate at `:51`,
  expire-on-refusal at `:53`, capture at `:60`
- `app/Jobs/FireAwinConversionJob.php:91` — no conversion without `awin_cks`
- `app/Models/UserConsent.php:32,41,46,102,137` — `TYPE_COOKIES`, version entry,
  `subject_token` fillable, `recordAnonymousConsent()`, `claimAnonymousConsents()`
- `app/Http/Controllers/Api/AuthController.php:27,662` — import + claim at registration
- `app/Http/Controllers/Api/GDPRController.php:71` — `cookies` filtered out of the PUT
- `app/Http/Middleware/EncryptCookies.php` — both consent cookies excepted
- `app/Http/Middleware/PreviewWriteInterceptor.php:54` — endpoint excluded
- `app/Providers/RouteServiceProvider.php:80` — named `cookie-consent` limiter
- `routes/api.php:35,145-146` — import + route
- `resources/js/utils/cookieConsent.js` — cookie-based, posts to the endpoint,
  `localStorage` gone, accept/decline now async
- `resources/js/components/Shared/CookieBanner.vue:88-96` — awaits both
- `resources/js/views/Register.vue` — `handleAcceptCookiesForRegistration` awaits
- `public/pages/js/cookie-consent.js` — same cookie, same endpoint, `localStorage` gone
- `tests/Feature/Payment/FireAwinConversionJobTest.php` — three existing payments
  given an `awin_cks` so their original skip reason still holds; one new test
- board files, handoff notes, this document

**Migration applied to the local dev database only:**
`php artisan migrate --path=database/migrations/2026_08_21_140000_...php` →
`user_id` now `YES` nullable, `subject_token char(64) YES` (verified by `DESCRIBE`).
No bare `migrate`, no `migrate:fresh`.

### Test output

```
DB_DATABASE=laravel_testing_b ./vendor/bin/pest \
  tests/Feature/Consent/ tests/Feature/Middleware/CaptureAwcCookieTest.php \
  tests/Feature/Payment/FireAwinConversionJobTest.php \
  tests/Feature/Payment/AwinConversionFlowTest.php \
  tests/Feature/Auth/GDPRApiTest.php tests/Unit/Services/GDPR/ tests/Unit/Services/Marketing/
→ Tests: 151 passed (93863 assertions)

DB_DATABASE=laravel_testing_b ./vendor/bin/pest \
  tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/CampaignRegistrationHandoffTest.php \
  tests/Feature/Auth/PensioncheckRegistrationPayloadTest.php \
  tests/Feature/Auth/SignupSourceCaptureTest.php tests/Feature/Auth/FunnelAnswersCaptureTest.php
→ Tests: 64 passed (264 assertions)

npx vitest run resources/js/__tests__/   → 12 passed (2 files)
./vendor/bin/pint --test <15 touched files> → passed
```

`--testsuite=Architecture` → 148 passed, **1 failed, and the failure is not mine**:
`app/Http/Requests/Retirement/StoreDBPensionRequest.php:7`, a `use App\Models\DBPension;`
added by another agent's in-flight W-0032 work (`git diff HEAD` on that file confirms).

---

## 4. IN FLIGHT

Nothing. Both items are complete on disk.

---

## 5. NOT STARTED — and deliberately so

- **`security-reviewer` sign-off on the pre-auth write path** (W-0049 acceptance 5).
  A reviewer's call, not the builder's. The design and its one contestable input
  are written out in the board item and the handoff note.
- **`/m` bundle rebuild.** The coordinator owns it. The funnel banner change is not
  live on `/m` until it happens.
- **Browser verification.** Reserved for a persona-tester by dispatch.
- **Historic consent backfill.** Explicitly forbidden, and correctly so.
- **W-0050, the wall itself.** Parked by CSJ.
- **The Awin MasterTag merchant-id fallback.** Checked, does not have the W-0047
  defect, changing it risks silently disabling production affiliate tracking.
  Flagged in the W-0047 working notes.
- **A consent check in `PaymentController.php:340/506`.** One-request theoretical
  window, named in the W-0049 notes rather than papered over with a third check.

---

## 6. Decisions taken — do not re-litigate these

1. **Anonymous subject on `user_consents`, not a second table.** The item said a
   second consent store would be a violation. `user_id` nullable + `subject_token`
   keeps one store, one versioning model, one history, one withdrawal path.
2. **A new `cookies` consent type, not `TYPE_MARKETING`.** Cookie/tracking consent
   and email-marketing consent are different lawful bases; one button must not
   stand for both. Reversible as a rename if compliance disagrees.
3. **Record in the database, gate on the cookie.** The middleware is global — it
   runs before the session starts, before any guard resolves a user, on every
   request. A per-request database read there is not viable and `$request->user()`
   is not reliably resolvable at that point. One service writes both, always
   together, so they cannot drift.
4. **Claim at registration rather than re-record.** Preserves the moment the
   consent was actually given. An anonymous row is left unclaimed rather than
   deleted when the user already holds the same type+version — no evidence is
   ever destroyed.
5. **The endpoint accepts an optional `awc`.** Without it, consent-gating silently
   ends affiliate attribution for every visitor, because the click reference only
   exists on the landing request and the banner is answered after it. Nothing is
   stored before consent. It grants no capability a visitor did not already have
   via `/?awc=<value>`.
6. **Cookie written client-side first, then posted.** A slow or failed request must
   never leave a visitor who clicked Accept still behind the wall.
7. **Both consent cookies are host-only (no domain).** So the browser-written and
   server-written cookies are the same cookie, not two that shadow each other.
8. **`cookies` is not writable through `PUT /api/auth/gdpr/consents`.** A record
   written there alone would be a preference the middleware never sees — the exact
   defect being fixed.
9. **The endpoint is excluded from `PreviewWriteInterceptor`.** A refusal the
   interceptor fakes a success for is a refusal the middleware ignores.
10. **`FireAwinConversionJob` gates on `awin_cks`, not on a fresh consent check.**
    One consent decision, read in one place; everything downstream keys off whether
    the cookie survived.

---

## 7. Dead ends — already walked, do not re-walk

- **Reading consent from the database inside `CaptureAwcCookie`.** It is global
  middleware: the session has not started and no guard has resolved a user. Calling
  `$request->user()` there is early, unreliable for the session guard, and a
  per-request query on every request besides.
- **Letting `PUT /api/auth/gdpr/consents` write `cookies` and attaching the cookie
  to its response.** Adds a second write path for one decision. Filtering the type
  out is smaller and stronger.
- **A shared client module for all three banner surfaces.** The public funnel
  script is unbundled vanilla JS in `public/`; it cannot import from `resources/`
  without a build step, and `/m` is a separate bundle with a different alias set.
  The convergence that was available — and taken — is server-side: one endpoint,
  one service, one record, one cookie contract. What remains duplicated on the
  client is a banner UI and one `fetch`, not a second policy.
- **Holding the `awc` value server-side pending consent.** That is processing for
  marketing purposes before consent; the item explicitly says to say so rather
  than work around it.
- **`->change()` on `user_consents.user_id`.** Laravel 10 routes it through
  doctrine/dbal, which round-trips the foreign key and drops it on some MySQL 8
  builds. Raw `ALTER TABLE ... MODIFY` keeps the FK.
- **Asserting a cleared cookie's value is `''`.** Symfony's `clearCookie` produces
  a cookie whose `getValue()` is `null`.
- **`withUnencryptedCookie()` on a `postJson`/`getJson` without `withCredentials()`.**
  Laravel's `prepareCookiesForJsonRequest` returns an empty array unless
  `withCredentials()` was called, so the cookie is silently dropped and the test
  passes for the wrong reason. This cost one cycle.

---

## 8. Environment state a replacement depends on

- **Working tree `dev`, shared, 554+ uncommitted paths from six agents.** Nothing
  committed by me. No branch, no PR, no deploy — by instruction.
- **Test database `laravel_testing_b`.** Every run needs
  `DB_DATABASE=laravel_testing_b`; `phpunit.xml`'s `<env>` has no `force="true"`,
  so the shell wins. Check `pgrep -f "vendor/bin/pest"` first — three to five other
  runs were live throughout.
- **Local dev database has the new migration applied.** A replacement does not need
  to re-run it. `php artisan db:seed` is unaffected — the column is additive and
  nullable.
- **`/m` bundle NOT rebuilt.** Coordinator owns it.
- **No production access used, none needed.** Every production fact in these notes
  came from repository files.
- **No users created, deleted or provisioned.** Users 16/17/20/30/31 untouched.
- Laravel `:8000` and Vite `:5173` were up throughout and were not restarted.

---

## 9. Rule compliance

- **Rule 15 (icons):** no icon added anywhere. The two existing banner SVGs
  (`CookieBanner.vue`, `public/pages/js/cookie-consent.js` `ICON_COOKIE`/`ICON_WARN`)
  were left exactly as found — grandfathered, and not mine to strip.
- **Rule 19 (`/m`):** covered and stated per surface, in §3 and in the W-0049
  handoff note. Not parity-by-absence — enforcement reaches `/m` by architecture,
  and the asking happens on the funnel `/m` iframes.
- **Rule 20 (one home):** three mechanisms found (server consent model, SPA
  `localStorage`, funnel `localStorage`); converged onto one service, one endpoint,
  one record, one cookie contract. The second client copy was not named in the item
  and was found by the prior-art check.
- **British spelling** in every string touched. No user-facing copy was changed —
  the banner wording is W-0050's, and W-0050 is parked.
- **No `.env` edit.** Read for keys only.
