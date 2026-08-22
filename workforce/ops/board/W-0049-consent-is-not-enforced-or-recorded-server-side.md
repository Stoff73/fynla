---
id: W-0049
title: Consent is neither enforced nor recorded server-side — affiliate tracking runs regardless of it, and no consent can be evidenced for any user
mission: M-0002-persona-fidelity
owner: build-lead
claimed_by: fix-batch-F
reviewers: [compliance-lead, security-reviewer]
status: handoff
handoff_to: quality-lead
branch: branches/fixes/F-0007-batch-f-analytics-consent.md
severity: high
surfaces: [web, m]
source: compliance-perimeter ruling on W-0050, 2026-08-21; production config verified by coordinator
prior_art_checked: 2026-08-21
prior_art_outcome: extend
prior_art_found: [app/Models/UserConsent.php, app/Services/GDPR/ConsentService.php, app/Http/Controllers/Api/GDPRController.php, app/Http/Middleware/CaptureAwcCookie.php, resources/js/utils/cookieConsent.js, public/pages/js/cookie-consent.js]
---

## Intent

Two defects with one root: **consent exists only in the browser.** It is not enforced
server-side and it is not recorded anywhere.

**1. Affiliate tracking ignores consent entirely.** `CaptureAwcCookie` is in the
**global** middleware stack (`app/Http/Kernel.php:106`, also on `origin/main`), gated
only on `config('awin.enabled')` with **no consent check**, setting a **365-day
HttpOnly `awc` cookie**. `FireAwinConversionJob` fires server-to-server conversions,
also unchecked.

**`config('awin.enabled')` is `true` on production — verified directly on the
production server, 2026-08-21.** So this is live, not latent, and it applies to **every
visitor**, including the majority who never reach a registration form and have
therefore never been asked anything at all.

Because the cookie is **HttpOnly**, `declineCookies()` is *structurally incapable* of
clearing it. **No banner change can ever fix this** — withdrawal has to be server-side.

**2. Consent cannot be demonstrated.** Consent exists only as a `localStorage` string;
`grep` for `cookie_consent` across `app/`, `database/migrations/` and `routes/` returns
nothing. Even where consent is freely given and perfectly informed, it cannot be
evidenced for one user on any day since 2026-04-07. **Defects of disclosure are fixable
going forward; this one has no record to reconstruct from.**

## Why these are one item

Both are "the browser is the only place consent lives". Fixing the record without the
enforcement leaves tracking running against recorded refusals; fixing the enforcement
without the record leaves it unprovable. Splitting them produces two mechanisms for one
job.

## Acceptance

1. **Awin capture and conversion firing are consent-gated server-side.** If consent is
   absent or refused, no `awc` cookie is set and no conversion fires.
2. **Withdrawal clears the HttpOnly cookie server-side.** Declining after the fact must
   actually stop it, not merely record a preference the middleware ignores.
3. **Consent is recorded server-side, versioned, with history and withdrawal.**
   `UserConsent` already provides exactly this shape and has `TYPE_MARKETING`, a
   versioning model, an API and a native client. **It is keyed to `user_id`, and cookie
   consent must be captured before an account exists — so it is NOT a drop-in.** Solve
   the pre-account identity problem explicitly rather than bolting a second consent
   system alongside it (Rule 20).
4. **One mechanism across three surfaces.** Today web writes to `localStorage`, `/m`
   has **no consent code at all**, and native has a full server-backed versioned
   system. Converging on native's model is the obvious direction; whatever is chosen,
   `/m` must not be left as parity-by-absence.
5. `security-reviewer` on the pre-account identity design — a pre-auth consent token is
   an unauthenticated write path.

## Sequencing — this gates the copy

`compliance-perimeter` has drafted replacement banner copy
(`workforce/ops/handoffs/W-0050/compliance-to-design-2026-08-21.md`). **That copy must
not ship before acceptance 1 and 2.** It tells the user that declining stops analytics
and affiliate tracking, which is **false for affiliate today** — publishing it against
current code would swap one false justification for a worse one, false about precisely
the thing the first was concealing.

Order: **gate Awin server-side → remove the wall (CSJ decision) → then the copy.**

If the server-side `awc` capture genuinely cannot be consent-gated, say so rather than
working around it — compliance will redraft to describe what actually happens.

## Not fixed by this item

**"Freely given" (Art 7(4)) is untouched by anything here.** Only removing the
registration condition addresses it, and that is CSJ's decision. An honestly described
wall is still a wall — the provision addresses conditionality, not disclosure of it.
**Better copy must not be read as a fix.**


## Working notes

### 2026-08-21 — build-lead (fix-batch-F) — FIXED, handed to quality-lead

**Prior art — three mechanisms found where the item expected two.**

1. `UserConsent` + `ConsentService` + `GDPRController` — versioned, server-side,
   with history and withdrawal, but `user_consents.user_id` was `NOT NULL` with an
   FK, so it could not hold a pre-account decision.
2. `resources/js/utils/cookieConsent.js` — `localStorage['cookie_consent']`, web SPA.
3. **`public/pages/js/cookie-consent.js` — a second, independent copy of the same
   `localStorage` key for the server-rendered funnel pages.** Not named in the item.
   Two client stores of one decision is exactly the Rule 20 shape, so converging
   them was part of the fix, not an extra.

`/m` had none, as the item says.

**The pre-account identity problem — what I chose and why.**

`user_consents` gained an anonymous subject rather than a second table:
`user_id` is now nullable and a new `subject_token` (char(64), unique with
`consent_type` + `version`) identifies the browser. Migration:
`database/migrations/2026_08_21_140000_add_anonymous_subject_to_user_consents_table.php`
(`user_id` altered by raw `ALTER TABLE ... MODIFY` because `->change()` on an
FK-constrained column drops the FK on some MySQL 8 builds; the FK is retained and
MySQL permits NULL in a constrained column). The token is 32 random bytes hex,
issued by the server, carried in an HttpOnly cookie, never accepted from a request
body. On registration the anonymous row is **claimed** onto the new `user_id` and
the token cleared (`AuthController.php:662`), so a consent given on the landing
page is demonstrable for that user, dated when they actually gave it — not
re-recorded at sign-up.

`UserConsent::claimAnonymousConsents()` (`app/Models/UserConsent.php:137`) leaves a
row unclaimed rather than delete or overwrite when the user already holds the same
type+version. **No consent evidence is ever destroyed**, and the
`(user_id, consent_type, version)` unique key is respected.

New consent type `TYPE_COOKIES = 'cookies'` (`app/Models/UserConsent.php:32`,
version `v1.0` at `:41`) rather than reusing `TYPE_MARKETING`: conflating
cookie/tracking consent with email-marketing consent would make one button stand
for two different lawful bases.

**One home: `app/Services/Consent/CookieConsentService.php`.** Every write of the
decision goes through it, and it always writes **both** the `user_consents` record
(the evidence) and the `fyn_cookie_consent` cookie (the transport the global
middleware reads). They cannot drift because nothing else writes either. One
endpoint serves all surfaces: `POST /api/cookie-consent`
(`routes/api.php:145-146`, `CookieConsentController`).

**Acceptance 1 — capture and conversion firing are consent-gated server-side. DONE.**
- `app/Http/Middleware/CaptureAwcCookie.php:51` — no `awc` cookie is set unless the
  decision is `accepted`. **Absence of a decision is not consent**, so the majority
  who never reach a form and were never asked are no longer tracked.
- `app/Jobs/FireAwinConversionJob.php:91` — no conversion fires without
  `payments.awin_cks`, and that column can only be non-null if the cookie survived,
  which now requires consent. This also stops the pre-existing behaviour of firing
  unattributed conversions (no `cks`) to Awin, which were never billable anyway.

**Acceptance 2 — withdrawal clears the HttpOnly cookie server-side. DONE.**
- `CookieConsentController.php:70` expires `awc` on the decline response itself.
- `CaptureAwcCookie.php:53` expires it on *any* subsequent request where the
  decision is not `accepted` — which is what clears the 365-day cookies already
  sitting on production visitors who were never asked.
- Both go through one declaration of the cookie's name, domain and flags,
  `app/Support/AwinClickCookie.php`. A clear that does not match the cookie it is
  trying to remove is silent, so name/domain/flags are single-sourced.
- The middleware reads the decision **after** the response
  (`CookieConsentService::resolvedStatus`) so the consent endpoint's own request is
  judged on the decision it just recorded, not the one it is replacing.

**Acceptance 3 — recorded, versioned, with history and withdrawal. DONE.** A refusal
after an acceptance updates the same row: `consented=false`, `withdrawn_at` set —
the existing `UserConsent` semantics, unchanged. It appears in
`GET /api/auth/gdpr/consents` and `/consents/history` for free.

**Cookie consent is NOT writable through `PUT /api/auth/gdpr/consents`**
(`GDPRController.php:71` filters it out). A record written there alone would be a
preference the middleware never sees — the exact defect this item is about. One
write path, deliberately.

**Acceptance 4 — one mechanism across the surfaces. DONE.**
- Web SPA: `resources/js/utils/cookieConsent.js` — reads the cookie, POSTs to the
  endpoint. `localStorage` removed entirely.
- Server-rendered funnel: `public/pages/js/cookie-consent.js` — same cookie, same
  endpoint. Its `localStorage` store is gone.
- `/m`: **reached by architecture, and checked rather than assumed.** `/m`
  (`resources/views/mobile-host.blade.php:33`) iframes the real funnel same-origin,
  so a phone visitor gets the funnel banner and the cookies it writes are readable
  at `/m/app`. The mobile SPA itself loads no analytics and no affiliate tag, and
  every route in `resources/mobile/router.js:46-76` except `/login` is
  `meta: { auth: true }` — there is no anonymous surface there to ask on. The
  server-side gate reaches every `/m` request regardless, because
  `CaptureAwcCookie` is global (`app/Http/Kernel.php:106`). **This is not
  parity-by-absence: the enforcement covers `/m` and the asking happens where the
  visitor actually is.**
- Native is untouched; its own versioned consent system is the model this followed.

**Acceptance 5 — security-reviewer on the pre-auth write path. NOT DONE by me** —
that is a reviewer's call, not the builder's. What a reviewer needs:
- The endpoint takes **no identifiers**. The subject token is server-issued; a
  presented token is honoured only if it matches `/^[a-f0-9]{64}$/`, otherwise a
  fresh one is issued. A visitor supplying their own token can only write consent
  for a subject they control.
- Rate-limited by a **named** limiter, `cookie-consent`, 20/min keyed by
  `path|ip` (`RouteServiceProvider.php:80`). Named because inline throttles share
  one per-IP bucket across every inline-throttled public route.
- `status` is validated to exactly `accepted|declined`.
- Both cookies are added to `EncryptCookies::$except` — required, because the
  global middleware runs before decryption and would otherwise read ciphertext.
  Neither carries anything secret.
- Added to `PreviewWriteInterceptor::EXCLUDED_ROUTES`
  (`PreviewWriteInterceptor.php:54`): a refusal that the interceptor fakes a
  success for is a refusal the middleware ignores.
- The one input worth arguing about is `awc` — see below.

**The `awc` field on the endpoint — a decision that needs a second opinion.**
An affiliate landing is a single request (`/?awc=...`) and the banner is answered
*after* it, so gating capture on consent loses the click reference for **every**
affiliate visitor. Rather than work around the gate server-side (holding the value
pending consent would be processing it for marketing before consent), the banner
sends the reference it can still see in the URL with the accept, and the server
sets the cookie at the moment of consent (`CookieConsentController.php:65`). It
grants no capability that did not already exist — any visitor can set the same
cookie by requesting `/?awc=<value>` — and it is ignored on decline and when
`awin.enabled` is false. **If a reviewer disagrees, deleting the `awc` field is a
three-line change; the consequence is that affiliate attribution stops working for
visitors who accept after landing.**

**Historic consent: not repaired, as instructed.** No backfill, no fabricated rows.
The record starts from the first decision made after this ships.

**Residual gap I am naming rather than hiding.** A legacy `awc` cookie set before
this ships could still be read into `payments.awin_cks` by
`PaymentController.php:340/506` on the *same* request that the middleware expires
it, because the controller runs before middleware post-processing. In practice the
window is nil — the cookie is cleared on the visitor's first request after deploy
and checkout is many requests later — so I did not add a third consent check to
the payment path. Named for the reviewer to disagree with.

**Tests — all green under `DB_DATABASE=laravel_testing_b`.**
- `tests/Feature/Consent/CookieConsentTest.php` — 15 pass, including the claim
  through the real `POST /api/auth/verify-code` registration flow
- `tests/Feature/Middleware/CaptureAwcCookieTest.php` — 6 pass
- `tests/Feature/Payment/FireAwinConversionJobTest.php` — 10 pass (one new: no
  conversion without a click reference; three existing payments given an
  `awin_cks` so their original reason for skipping remains the reason)
- `tests/Feature/Payment/AwinConversionFlowTest.php`, `tests/Feature/Auth/GDPRApiTest.php`,
  `tests/Unit/Services/GDPR/`, `tests/Unit/Services/Marketing/` — 151 pass together
- `resources/js/__tests__/cookieConsent.spec.js` — 8 pass
- `./vendor/bin/pint --test` clean on every PHP file touched

**Not verified by me:** no browser verification, per my dispatch — a persona-tester
closes Rule 14's loop independently. **`/m` needs a bundle rebuild** before the
funnel banner change is live there; the coordinator owns that.

**Sequencing note for compliance:** acceptance 1 and 2 are now met in code, so the
`compliance-perimeter` replacement copy is no longer false about affiliate
tracking — **once this is deployed.** Against the currently-deployed build it is
still false. The wall itself (W-0050) is untouched and remains CSJ's decision.

- 2026-08-21 compliance-lead: **PRIVACY REVIEW — provisional. Two additive findings; neither
  blocks the batch.** Full reasoning and sources:
  `workforce/ops/reports/2026-08-21-F-0007-consent-privacy-review.md`. **Not an approval**
  (`05-perimeter.md` §7.3). **I have not ruled the consent valid or invalid**, and **the
  cookie wall is not reopened** — W-0050 is parked by CSJ and nothing here depends on it.
  **Provisional** — PECR is `Unmapped` (§1.1, §1.3).

  ### Commencement checked first, and PECR failed again — differently

  **PECR reg 6 was SUBSTITUTED in its entirety on 5 February 2026**, not amended, by the
  Data (Use and Access) Act 2025 (c. 18) ss. 112(2), 142(1), commenced by **S.I. 2026/82
  reg 2(w)**. It now has **two paragraphs**, and **every exception — including consent —
  lives in a new Schedule A1 that did not exist before that date.**

  Consequence recorded as standing warning **W4** in `core/registry/sources.md`: the W-0050
  ruling cites **reg 6(4)**, and **reg 6 has no paragraph (4)**. That is not a citation that
  drifted; it is one that **no longer resolves**. Register maintenance, **not** a re-ruling.

  ### Decision 2 — `cookies` not `TYPE_MARKETING`: the reasoning HOLDS, and stops one level short

  **Right, and better founded than the batch had grounds to know.** `TYPE_COOKIES` governs
  storing or accessing information in terminal equipment, which is what reg 6 is about on its
  face; email marketing is not that. One record must not be the evidence for two different
  things.

  **But Schedule A1 does not have one exception — it has several, and they are different in
  kind:** para 2 **consent** · para 5 **statistical purposes** (sole purpose of collecting
  information about how the service is used to enable improvements, with clear information
  and a **simple means to object at no cost** — objection, not consent) · para 6 appearance
  and functionality.

  **`TYPE_COOKIES` covers two materially different activities — Google Analytics measurement
  and Awin affiliate attribution — and the record cannot say which the visitor agreed to**,
  because they were never asked separately. **The batch correctly refused to conflate cookie
  consent with marketing consent, then reproduced that conflation one level down.**

  **What I am NOT saying, and it matters: I am not saying para 5 applies to Fynla's
  analytics, that consent can be dropped for anything, or that the single button is invalid.**
  All three are determinations (§7.3) and the third is W-0050's, which is parked.

  **The finding within competence is about the record, not the law:** a
  `cookies / v1.0 / consented = true` row answers a question that will not survive the two
  being separated. **W-0049's own dispatch says historic consent is not repairable
  retrospectively** — the batch honoured that about the past and is about to recreate the
  condition going forward.

  **Recommendation — proportionate, and it does not touch the parked decision. Do not change
  the button.** Change what the click records: **two consent types (`cookies_analytics`,
  `cookies_affiliate`) written from the one click.** Two rows from one click is **not** a
  second write path — same service, same endpoint, same moment — so Rule 20 is untouched, and
  the two types map one-to-one onto the two behaviours the middleware already gates
  separately. The alternative (keep one type, make `version` carry the scope) works but is
  weaker.

  **This is the one that stops being possible later.** Nothing is committed; granularity now
  costs a row, and cannot be backfilled afterwards without fabricating records.

  ### Decision 5 — the `awc` field: the refusal was CORRECT and approving the alternative was correct

  Of the two options available, the one taken is **the more conservative**. Holding the value
  server-side pending consent would put an affiliate click reference into Fynla's storage
  against a visitor **before** they answered; the chosen design keeps it in the visitor's own
  URL and page until the moment of consent. **The agent identified the right distinction and
  declined to work around it.**

  Two things **recorded rather than fixed**, neither blocking:

  1. **The value reaches the server regardless.** `awc` is in the landing request's query
     string and will be in access logs whatever the application does. Not a defect of this fix
     and no available design changes it — but *"nothing is stored before consent"* is true of
     the application, not of the whole system.
  2. **The consent record does not evidence what was captured under it.** Same theme as the
     finding above; nearly free if the two-type recommendation is taken.

  The *"grants no new capability"* note is correct, and is a **security** argument — it
  answers "can this be abused", not "is the value processed before consent". Both answers are
  satisfactory here; they are different questions.

  ### NEW, and it belongs to this batch — there is no way to withdraw

  **Verified by reading all three surfaces: after accepting, no user-reachable control exists
  to withdraw cookie consent.**

  - The banner is the only control and renders only when there is no decision
    (`CookieBanner.vue:72`).
  - `cookies` is excluded from `PUT /api/auth/gdpr/consents` (`GDPRController.php:71`) —
    **correctly, and I am not asking for that to be reversed.**
  - No cookie-settings control anywhere in `resources/js`, `resources/mobile` or
    `public/pages/js`.
  - `getConsentHistory` (`:338-354`) does **not** filter `cookies`, so a user can **see** the
    decision they cannot **change**. Visible and irreversible is worse than invisible.

  **Why it is this batch's and not W-0050's: before F-0007, Decline did nothing** — `awc` was
  set on every visitor regardless — so the missing route changed no outcome. **F-0007 made
  Decline mechanically meaningful**, so it built a real withdrawal capability and shipped no
  interface that reaches it. The only remaining route is the visitor clearing their own
  cookies, which is working around Fynla rather than Fynla providing withdrawal.

  **Whether that matters legally is a §6 question and I have not answered it.** As a product
  observation it needs no legal premise: **the system has a state no interface can move a user
  into.**

  **Fix stays inside Rule 20:** a privacy-screen control calling `POST /api/cookie-consent` —
  the existing one home. **Do not re-open the GDPR PUT.** **Rule 19:** `/m` has no banner of
  its own and inherits both the mechanism and the gap, so any control must reach `/m` too.

  ### On the record — what the batch got right

  The anonymous subject on `user_consents` rather than a second store; **claiming at
  registration rather than re-recording**, so the row keeps the moment consent was actually
  given; **leaving a row unclaimed rather than deleting it**, so no evidence is destroyed;
  the server-issued subject token never taken from a request body; **absence of a decision
  treated as not-consent**; expire-on-decline honoured server-side because the cookie is
  HttpOnly; and **finding the third funnel copy via the prior-art check and converging it**.
  `AwinClickCookie` as one declaration of the cookie's attributes is the detail that makes
  withdrawal actually work — a second copy would produce a clear that silently fails to match.

  ### §6 questions written, not answered

  Four, numbered 11–14 in the report: whether Sch A1 para 5 reaches Fynla's analytics; whether
  `awc` sits under a different paragraph from analytics; whether withdrawal must be reachable
  in-product; and **when the anonymous `subject_token` row becomes personal data** — which
  arises from the design being *better* than the alternative, not worse.

### 2026-08-21 (later) — fix-batch-F — consent split into two types, on team-lead's instruction

**`TYPE_COOKIES` is now `TYPE_COOKIES_ANALYTICS` + `TYPE_COOKIES_AFFILIATE`**, both
written from the one click. `compliance-lead`'s review
(`workforce/ops/reports/2026-08-21-F-0007-consent-privacy-review.md`) found that a
single `cookies` row covered two materially different activities — measurement and
affiliate attribution — and so could not say which the visitor had agreed to.

**Why it could not wait.** This item established that historic consent is not
repairable retrospectively. Without the split, that same condition was being
recreated going forward: a row written today is free, the same row next week means
fabricating records to separate them.

**Where:**
- `app/Models/UserConsent.php:40,42` — the two constants, plus
  `COOKIE_BANNER_TYPES` (`:51-53`) as the one home for the list, so a consumer
  cannot come to know about one and not the other.
- `app/Services/Consent/CookieConsentService::record()` — loops that list and
  writes both, in one call, at one moment, against one subject.
- `app/Http/Controllers/Api/GDPRController.php:71` — both excluded from the
  general consent PUT, via the same list.

**What deliberately did NOT change:** the button, the endpoint, the moment, the
status cookie, the middleware gate, and the wall (W-0050, parked). **Two rows for
one user action is not two write paths** — the enforcement already treated these
as separate behaviours (analytics by the tag loader, affiliate by
`CaptureAwcCookie`); only the record pretended they were one thing.

**No data migration.** The feature is not deployed, so no real consent exists under
the old `cookies` type. Four rows do exist on the LOCAL dev database
(`user_consents` ids 110-113, all anonymous, created 18:33–19:32 today) — they are
artefacts of my own local requests while building this, not user consent. **I have
left them alone rather than tidying them away**: deleting consent records by hand is
not a habit worth forming, and they will never reach an environment that matters.

**Tests:** `tests/Feature/Consent/CookieConsentTest.php` — 15 pass, now asserting
two rows per click, one subject and one moment shared between them, both claimed at
registration, and both refused through the general endpoint. 85 pass across the
consent-adjacent families (`Feature/Consent`, `CaptureAwcCookieTest`, `GDPRApiTest`,
`Unit/Services/GDPR`, the two AI-chat consent suites). `pint --test` clean.

**Still not done, and not mine:** W-0155 — after accepting there is no user-reachable
way to withdraw, on any surface. Downstream of this fix rather than a flaw in it:
before, Decline did nothing, so a missing withdrawal route changed no outcome; making
Decline mechanically meaningful created a right with no interface. It needs a
privacy-screen control calling the existing endpoint, and it must reach `/m`.
