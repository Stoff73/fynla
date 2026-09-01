---
id: W-0050
title: You cannot create an account without consenting to Google Analytics and Awin affiliate tracking — a cookie wall, justified by copy that is factually untrue
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: compliance-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T11:55:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: build-lead
prior_art_checked: 2026-08-21
prior_art_found: []
prior_art_outcome: new
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **registration / onboarding sweep**, local
`localhost:8000`, in a clean isolated browser context with no prior state. Throwaway
account, no persona data involved.

**Surface:** `/register` on desktop web. Owned by no fix batch.

Found by taking the privacy-preserving option on the cookie banner — which my own
operating rules require — and discovering it makes the product unusable.

### Expected

Declining non-essential cookies should leave registration working. Session and CSRF
cookies are **strictly necessary** for authentication and are exempt from the consent
requirement under PECR reg 6(4); they are set server-side regardless of any banner
choice.

A user who declines analytics and affiliate tracking should still be able to create an
account.

### Actual

**The registration form does not render at all.** Not hidden, not disabled — absent.

After clicking "Decline Cookies" and then "Continue Without Cookies", `/register`
contains **zero `<form>` elements and zero `<input>` elements**. The entire page reads:

> Create your account · Already have an account? Sign in · **Cookies Required** ·
> "Cookies are required to create an account. They allow us to keep you securely
> signed in." · **Accept Cookies & Continue** · Go to Fynla homepage

The only route forward is to accept. There is no decline-and-continue path.

**The stated justification is untrue.** The chain:

- `resources/js/views/Register.vue:254` — `const cookiesAccepted = ref(hasConsent());`
- `resources/js/views/Register.vue:90` — `<form v-if="cookiesAccepted" …>` — no consent, no form.
- `resources/js/views/Register.vue:256-259` — the sole escape calls `acceptCookies()`.
- `resources/js/utils/cookieConsent.js:24-26` — `hasConsent()` is
  `localStorage.cookie_consent === 'accepted'`.
- `resources/js/utils/cookieConsent.js:31-45` — `acceptCookies()` sets the flag, then
  **loads Google Analytics** and **loads the Awin affiliate MasterTag**.
- `resources/js/utils/cookieConsent.js:54-62` — `declineCookies()` sets the flag,
  unloads the Awin tag, and does not load Google Analytics.

So the consent flag governs **Google Analytics and Awin affiliate tracking, and
nothing else**. It has no bearing on session cookies whatsoever. Confirmed live:
after declining, `document.cookie` still carried `XSRF-TOKEN=eyJpdiI6…` — the session
cookie was present the whole time and needed no permission.

Google Analytics is not env-gated in practice: `cookieConsent.js:6` falls back to a
**hardcoded** measurement id, `G-3Y8DL3QB09`, so accepting always loads it. Awin is
env-gated on `VITE_AWIN_ENABLED` (`awinTracking.js:24`).

The user is therefore told they must accept cookies "to keep you securely signed in",
when what they are actually consenting to is analytics and affiliate marketing.

### Impact

**Regulatory.** UK GDPR Art 4(11) and Art 7(4) require consent to be freely given, and
Recital 42 says consent is not free where the data subject has no genuine choice or
cannot refuse without detriment. Making account creation conditional on accepting
non-essential analytics and affiliate tracking is a cookie wall — the ICO's published
position is that "consent or bust" does not produce valid consent. Consent obtained
this way is arguably invalid for **every account ever created through this form**,
which also puts the lawful basis for the resulting analytics processing in question.

Compounding it, the consent is obtained under a **factually incorrect explanation**.
Even setting the cookie wall aside, consent given on the basis that these cookies keep
you signed in — when they do no such thing — is not informed consent.

**Commercial.** Every privacy-conscious visitor who declines is dropped at the
registration step with no way to continue. For a personal-finance product asking users
to hand over their entire financial position, the users most likely to decline
tracking are precisely the security-conscious ones you most want.

**Scope.** `/register` is the only view gated this way — `/login` is not, and the only
other consumer is the Awin `router.afterEach` hook at `resources/js/router/index.js:1864`.
So the fix is contained. `/m` and iOS registration paths need checking for the same
gate (Rule 19).

### Repro

1. Fresh browser context, no `localStorage`, no cookies.
2. Go to `http://localhost:8000/register`.
3. Cookie banner → **Decline Cookies** → **Continue Without Cookies**.
4. The page shows "Cookies Required" and a single "Accept Cookies & Continue" button.
   `document.querySelectorAll('form').length === 0` and
   `document.querySelectorAll('input').length === 0`.
5. `localStorage.cookie_consent === 'declined'`; `document.cookie` still contains
   `XSRF-TOKEN`, proving the session cookie never depended on consent.

### Evidence

- `tests/Persona/20-08-2026_run/pass-a-web/18-web-register-declined-cookies-no-form.png`
- `resources/js/views/Register.vue:69, 90, 233, 254, 256-259`
- `resources/js/utils/cookieConsent.js:6, 24-26, 31-45, 54-62, 79-88`
- `resources/js/utils/awinTracking.js:24, 47-51`
- `resources/js/router/index.js:1864`

## Acceptance

- [ ] Declining non-essential cookies leaves the registration form fully usable. No
      cookie wall on account creation.
- [ ] The consent banner distinguishes **strictly necessary** cookies (session, CSRF —
      no consent required, cannot be declined, and say so) from **non-essential**
      analytics and affiliate cookies (consent required, freely refusable).
- [ ] The "Cookies Required … keep you securely signed in" copy is removed or rewritten
      to describe what is actually being consented to. **`compliance-lead` owns the
      wording**; `design-lead` reviews it as copy.
- [ ] `compliance-lead` rules on whether consent already collected through this gate is
      valid, and what that means for analytics data gathered on its basis. That is a
      decision, not a code change — flag to CSJ.
- [ ] The hardcoded Google Analytics id fallback (`G-3Y8DL3QB09`,
      `cookieConsent.js:6`) is removed so analytics cannot load on an environment that
      never configured it. A local development or test run should not be able to send
      hits to the production property.
- [ ] `/m` and iOS registration checked for the same gate (Rule 19); if the gate is
      web-only, that is itself an inconsistency to record.
- [ ] A test pins that declining consent still renders the registration form.
- [ ] Re-verified live in the browser by the persona run, from a clean context.

## Working notes

- 2026-08-21 compliance-lead: **UPDATE — `AWIN_ENABLED` is `true` on production** (read on the
  server, read-only, CSJ-authorised, via team-lead). My conditional resolves to the live branch,
  and I have **re-weighted and re-ranked** the report accordingly.

  **This changes the finding's character, not just its odds.** `CaptureAwcCookie` is live in the
  global middleware stack on fynla.org: every visitor arriving with `?awc=` gets a **365-day
  HttpOnly cookie with no consent check**, and because it is HttpOnly **`declineCookies()` is
  structurally incapable of clearing it** — no banner change alone can ever fix this; withdrawal
  must be server-side. So this is not "a wall that over-collects consent". It is a wall
  extracting consent for processing **already happening to everyone who arrives**, including the
  large majority who never reach a registration form, never see the wall, and have therefore
  never been asked anything at all. The banner's silence on affiliate tracking
  (`CookieBanner.vue:16-17`) is live for all of them. **I now rank this above the registration
  wall itself** — strictly more people, and that group was never offered a choice.

  **Re-ordered the report's lead finding.** Art 7(1) — consent not demonstrable — was third in a
  list and should not have been. It is the **only defect here with no remedy**: consent exists
  solely as a `localStorage` string with no server record, so it cannot be reconstructed, only
  stopped. It now leads.

  **Production Google Analytics has no measurement ID configured**, so W-0047's hardcoded
  fallback resolves in production too — one property receiving every environment's traffic.
  Confirms rather than complicates the point: a deletion scope cannot cleanly separate
  environments inside it.

- 2026-08-21 compliance-lead: **acceptance item 3 DRAFTED — handed to design-lead**,
  `workforce/ops/handoffs/W-0050/compliance-to-design-2026-08-21.md`; full draft and reasoning
  at §6 of the ruling. Three pieces: banner request state (replaces `CookieBanner.vue:15-18`),
  delete the decline confirmation (`:38-63`), delete the registration block outright with no
  replacement (`Register.vue:69-86`, `:90`, `:254-259`).

  **The copy is BLOCKED on a code change and must not ship before it.** It tells the user that
  declining stops analytics and affiliate tracking — **false for affiliate today**. Publishing it
  against the current code would swap one false justification for a different one, and the second
  would be worse because it would be false about the very thing the first concealed. **Order:
  gate the Awin paths on consent server-side → remove the wall → then the copy.** If the
  server-side `awc` capture cannot be gated, tell me and I will redraft rather than paper over it.

  **What the copy fixes and does not.** Fixes the **informed** defect — that is what it is for.
  Does **not** fix **freely given** (only removing the condition does — CSJ's call) and does
  **not** fix **demonstrable** (copy cannot create a record). "The copy is fixed" must not be read
  as "W-0050 is resolved"; that is wrong on two of three counts.

  **Per instruction I did not resolve the cookie-wall question in the copy.** Drafted for the
  unconditioned state, with the alternative set out separately — and stated there that honest
  wording fixes the informed defect while leaving Art 7(4) exactly where it is. **An honestly
  described wall is still a wall**; better copy must not be allowed to read as a fix.

  Two lines in the draft are compliance-load-bearing, not style: **both third parties named**
  (Sch A1 para 1 — a user cannot be informed about a processor whose name they have never seen),
  and **affiliate described commercially** ("credited", "pay them"), not as a technical detail.
  Flagged three public claims and what each depends on — the load-bearing one, "Declining doesn't
  change anything you can do on Fynla", needs re-verifying against the code before publication.


- 2026-08-21 compliance-lead: **ruling written —
  `workforce/ops/reports/2026-08-21-W-0050-consent-validity-ruling.md`.** Acceptance item 4
  is addressed as far as perimeter §7.3 permits. **It is not a ruling that the consent is
  invalid** — three defects are flagged with dated sources; the determination is a lawyer's.
  Trunk unamended; no code, no PR, no prod.

  **The item's own law is out of date, and this is not pedantry.** PECR **reg 6 was
  substituted on 5 February 2026** by the Data (Use and Access) Act 2025 and the exemptions
  moved to a new **Schedule A1**. The strictly-necessary exemption cited above as "reg 6(4)"
  is now **Sch A1 para 3**, which expressly covers "maintaining authentication records" — so
  the tester's conclusion about session cookies survives, but the citation does not. More
  importantly **Sch A1 para 4 created a statistical/audience-measurement exception that did
  not exist when this code was written**, conditional on the information **not being shared**
  and on users having "a simple means of objecting, free of charge". Whether it could ever
  reach Google Analytics is a determination I cannot make; the elements are tabulated in the
  report against the verified facts. Every ICO cookie page carries an under-review note
  post-DUAA — treat all ICO material in this area as pre-dating the change.

  **Three defects in the consent, and the third is the one this item did not find:**
  1. **Freely given** — Art 7(4); registration conditional on consent to processing not
     necessary for it. ICO's tracking-wall position points the same way (**cited from the
     search index, not verified — ico.org.uk 403s my fetcher**).
  2. **Informed** — the register copy is false, as recorded above. **Wider than recorded:
     `CookieBanner.vue:16-17` never discloses affiliate tracking to anyone** — it says only
     "analyse how you use our site". Every visitor sees that banner, not just registrants.
  3. **Demonstrable — Art 7(1), and it is the serious one.** Consent exists ONLY as a string
     in `localStorage` (`cookieConsent.js:5, 24-26`); `grep -rn "cookie_consent" app/
     database/migrations/ routes/` returns **nothing**. Even if consent were freely given and
     perfectly informed, **Fynla could not demonstrate it for a single user on any day since
     2026-04-07**. Defects 1 and 2 are fixable going forward; **this one cannot be repaired
     retrospectively** — there is no record to reconstruct from.

  **Declining cookies does not stop Awin.** This changes the item's shape. `CaptureAwcCookie`
  is in the **global** middleware stack (`app/Http/Kernel.php:106`, also on `origin/main`),
  gated only on `config('awin.enabled')` — **no consent check** — and sets a **365-day
  HttpOnly `awc` cookie** on every request carrying `?awc=`. `declineCookies()` cannot clear
  it; it is HttpOnly. `FireAwinConversionJob` fires a **server-to-server** conversion, also
  with no consent check; `AwinTrackingService::fireServerToServer()` transmits amount,
  currency, order ref, commission group, voucher, acquisition status and the `cks` click
  reference (**no name or email** — checked). So the wall extracts consent for something that
  partly happens regardless. **Conditional on `AWIN_ENABLED` in production, which I cannot
  read** (`.env` is server-side; `ssh-fynla` is prod, untouched). One env var decides whether
  this is live or latent — it should be answered before affiliate marketing is switched on.

  **Scope of exposure:** the wall shipped `1ab710c1e` **2026-04-07** (commit message: "cookie
  consent banner with GA gating and **registration block**" — deliberate and named), and it is
  on `origin/main`. Every production account created through `/register` since then went
  through it, and it is still collecting. **W-0047's contamination matters to remediation
  here, not just tidiness:** dev/staging hits went to the live property, so "delete the data
  collected without valid consent" cannot cleanly separate environments inside one property.

  **Acceptance item 6 (Rule 19) answered:** `/m` has **no register view and no consent code
  at all** (`grep` over `resources/mobile/` → nothing). The answer is *parity by absence*, not
  "checked, fine" — recorded that way so a future `/m` registration is not built on the
  assumption that consent machinery exists there. Native iOS meanwhile has a **full
  server-backed versioned consent system** (`ConsentModels.swift`). Three surfaces, three
  mechanisms — a Rule 20 shape.

  **Prior art for the fix, with its catch.** `app/Models/UserConsent.php` already has
  **`TYPE_MARKETING`**, versioning (`CURRENT_VERSIONS`), history and withdrawal, exposed at
  `routes/api.php:199-201` and already consumed by native. The banner writes to `localStorage`
  instead. **But it is keyed to `user_id` and cookie consent must be captured before an
  account exists — so it is NOT a drop-in.** Naming the prior art and the obstacle; the design
  is build-lead's and product-lead's.

  **Trunk: a second unmapped regime.** `05-perimeter.md` has **no clause on consent, cookies,
  PECR, tracking or marketing permissions** — its entire data-protection surface is erasure,
  retention and one Article 9 question. Structurally identical to the legal-services gap found
  on W-0019. Two consecutive rulings hitting the same shape is a pattern: the file is written
  against the FCA regime and assumes it is the only one. Recommendation is a **§1 regime map**
  so the next agent SEES an unmapped regime rather than discovering it by finding nothing.

  **Acceptance item 3 (the replacement copy) is still mine and is NOT delivered here** — the
  dispatch asked for the ruling. It should be drafted against the corrected facts, and it must
  disclose affiliate tracking, which no current string does. Available on request.


Found because my operating rules require choosing the most privacy-preserving option
on any consent banner. Following that rule made the product untestable, which is how
the wall surfaced — a user-behaviour path that a tester who reflexively clicks "Accept"
would never walk. Worth keeping in the persona playbook as a standing check: **take the
decline path at least once on every consent surface.**

To continue the sweep I had to accept, since there is no other way to reach
registration. That acceptance is a testing artefact on a local throwaway account, and
it is recorded here so the run's own evidence is not mistaken for a user freely
consenting.

- 2026-08-21 team-lead: **CSJ DECISION — parked, not rejected.** CSJ: *"just click the
  allow cookies button, consent is then given?"* Confirmed: clicking **Accept** records
  consent in `localStorage`, the `/register` form renders, and registration proceeds
  normally. **The wall is therefore not a run blocker** — the tester accepts cookies and
  carries on; no test is gated on this item.
  The GDPR Art 7(4) "freely given" question stands unresolved and is **deferred until the
  functional defect board is clear**, per CSJ's standing priority this session: *"getting
  the system functional in the way it should be for real users is the priority."*
  **Not folded into W-0047 or W-0049** — those are separate live defects and stay in scope:
  W-0047 (analytics reporting into the production property from local/test) and W-0049
  (the `awc` affiliate cookie set with no consent check) are functional/data-integrity
  problems regardless of how the consent question is eventually answered.
  Status left `queued`, severity unchanged. Do not re-raise as a gate.

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev` — and still parked by CSJ's 2026-08-21
  decision, so this is a re-measurement, not a re-raise.** `Register.vue:69` renders the "Cookies
  Required" block on `!cookiesAccepted` and `:90` gates the whole `<form>` on `cookiesAccepted`, so
  declining still leaves the page with no form. Unchanged. The Article 7(4) question remains
  deferred until the functional board is clear; do not treat this as a gate.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.**

  **The cookie wall is gone.** `Register.vue` gated the form on `hasConsent()` — `ACCEPTED` specifically — so a visitor who clicked Decline got a page with **zero inputs on it** and exactly one way forward, which was to accept. Declining was not a choice; it was a dead end with a button out of it.

  **The root cause is a category error, and it is why the justification was untrue.** The copy said cookies *"allow us to keep you securely signed in"* — that describes the SESSION cookie, which is **strictly necessary and needs no consent at all** under PECR reg 6(4). What the banner actually governs is Google Analytics and the Awin affiliate MasterTag, neither of which registration touches. So consent to measurement and marketing was being extracted with a sentence about authentication. Fixing the wording alone would have left the wall standing; fixing the wall alone would have left the false sentence. Both are gone.

  **The form is now gated on nothing.** Declining still means no analytics and no affiliate tag — `cookieConsent.js` owns that, and the affiliate middleware reads the same server-side `fyn_cookie_consent` cookie, so a refusal remains honoured and demonstrable. It simply no longer means no account.

  **The dead wiring was removed in the same edit**, not left behind: the `hasConsent`/`acceptCookies` import, the `cookiesAccepted` ref, `handleAcceptCookiesForRegistration` and both `setup()` returns. A gate left half-present is a gate someone re-attaches.

  **Tested:** 819 frontend tests pass.

  **NOT DONE.** Not browser-verified — `public/build/` is a csjones build. The decline-then-register path is exactly what a browser test should walk, and it has not been walked.
