# New-user run — registration surface findings

**Env:** csjones staging (dev), `https://csjones.co/fynla`
**Date:** 2026-09-07
**Surface:** desktop web, unauthenticated
**Method:** live browser interaction (click/fill/submit) + network capture + code trace

## F-01 · Only the first validation error per field is ever shown — HIGH

`POST /api/auth/register` returned **three** password errors:

```json
{"errors":{"password":[
  "The password field must be at least 8 characters.",
  "The password field confirmation does not match.",
  "Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character."
]}}
```

The UI rendered only the first. Verified in-page: `shows_length: true`,
`shows_mismatch: false`, `shows_complexity: false`.

**Root cause:** `resources/js/views/Register.vue:174` renders `{{ errors.password[0] }}`.
Same single-element pattern at lines 116 (first_name), 135 (last_name), 153 (email).

**Why it bites:** there is no client-side validation — a 4-char password with a
mismatched confirmation still round-trips to the server. Each discovery costs one
request, and `/register` is behind `throttle:auth-5` (`routes/api.php:157`).
A user fumbling their password learns the rules one at a time and can plausibly
exhaust the throttle before ever creating an account.

**Fix direction:** render the full array (`v-for` over `errors.password`), and
validate match + complexity client-side before submitting.

## F-02 · Password rules disappear exactly when they are failed — MEDIUM

`Register.vue:170` gates the rules hint on `v-if="!errors.password"`. On the first
failure the hint is replaced by a single partial error. Confirmed in-page:
`hintStillVisible: false`, `bothAtOnce: false`.

Compounds F-01: the user loses the full rules at the moment they need them.

**Fix direction:** keep the hint visible alongside errors.

## F-03 · Cookie-decline warning overstates the consequence — MEDIUM (compliance-adjacent)

The decline step warns: *"Without cookies, some features including registration
will be unavailable."*

Tested while `fyn_cookie_consent=declined`: the registration form renders fully
enabled and `POST /api/auth/register` was accepted and processed normally
(422 validation, not a consent block). Registration is **not** unavailable.

Overstating the cost of refusing cookies is the shape the ICO treats as a
consent dark pattern. Worth a compliance view for a regulated fintech.

**Fix direction:** either make the copy accurate, or enforce what it claims.

## F-04 · No `autocomplete` on any registration field — MEDIUM (accessibility)

All five inputs report `autocomplete: null` and `name: null`. WCAG 2.1 AA §1.3.5
(Identify Input Purpose) expects `given-name`, `family-name`, `email`,
`new-password`, `new-password`. Without them — and with no `name` fallback —
password managers cannot reliably fill or offer to save the signup.

## Verified PASSING

- **Cookie consent, end to end.** Two-step decline (warning before confirming) on
  both the Vue banner and the vanilla server-rendered banner; cookie written
  (`fyn_cookie_consent=declined`); `POST /api/cookie-consent → 200`; persists
  across SPA navigation **and** onto the server-rendered landing page.
- **No duplicate consent store.** `resources/js/utils/cookieConsent.js` and
  `public/pages/js/cookie-consent.js` are two renderers over one server-owned
  record, with matching copy and flow. Rule 20 clean.
- **Form labels** correctly associated via `label[for]` → `id` on all five fields.
- **Palette compliance** on the banner (`raspberry-500`, `eggshell-500`,
  `neutral-500`) — Rules 8 and 11 clean.
- **Native email validation** blocks submit client-side; no throttle consumed.

## Corrections made during testing

Two suspected defects were investigated and **disproved** before reporting:
consent "not persisting" (my check searched for step-1 button text that step 2
does not contain), and "missing form labels" (the accessibility tree was terse;
labels are correctly associated).
