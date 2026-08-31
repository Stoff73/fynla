---
id: W-0155
title: Cookie consent can be given and seen but never withdrawn — F-0007 made Decline meaningful and thereby created a right with no interface
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0007-batch-f-analytics-consent.md
owner: build-lead
reviewers: [compliance-lead]
status: closed_invalid
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-21T19:45:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: ["W-0049 consent not enforced or recorded server-side - fixed by fix-batch-F, this is downstream of that fix", "W-0047 GA production measurement id", "W-0050 the cookie wall - PARKED by CSJ, do not reopen", "F-0007-batch-f-analytics-consent", "workforce/ops/reports/2026-08-21-F-0007-consent-privacy-review.md"]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
source: found by compliance-lead reviewing F-0007, 2026-08-21; routed to team-lead because compliance holds no ID block
---

## Intent

**After accepting cookies there is no user-reachable way to withdraw that consent — not on web, not on `/m`, not on the server-rendered funnel.**

Established by reading, not inferred:

- The banner is the only control, and it renders **only when no decision exists**
  (`CookieBanner.vue`, the `v-if` on the stored decision).
- `cookies` is deliberately excluded from the GDPR consent `PUT`
  (`GDPRController`) — **that exclusion is correct and must not be reversed.** One
  write path was the point of the fix.
- There is no cookie-settings control anywhere in `resources/js`, `resources/mobile`
  or `public/pages/js`.
- `getConsentHistory` does **not** filter `cookies`, so a user can **see** the decision
  they cannot **change**.

**This is downstream of F-0007, not a defect in it.** Before that batch, Decline did
nothing at all — the affiliate cookie was set on every visitor regardless — so a missing
withdrawal route changed no outcome. **The batch made Decline mechanically meaningful,
and in doing so created a capability with no interface.** The only remaining route is the
visitor clearing their own browser cookies, which is working around Fynla rather than
Fynla providing withdrawal.

**"Visible and irreversible is worse than invisible"** (compliance-lead) — surfacing a
decision in consent history while offering no way to change it is a worse state than
never showing it.

**No legal premise is required for this to be a defect.** Whether it matters as a matter
of law is a §6 question compliance has explicitly not answered. As a product observation
it stands on its own.

## Acceptance

- [ ] A user who has accepted can withdraw, from a control they can find without being
      told where it is.
- [ ] **Rule 20 — it calls the one existing write path**, `POST /api/cookie-consent`.
      No second endpoint, no second store, no re-implementation per surface.
- [ ] **Rule 19 — it reaches `/m`.** The mobile bundle is separate; a control in
      `resources/js` does not reach it. Note `/m` iframes the funnel same-origin, so
      establish whether the funnel control serves it or whether `/m` needs its own.
- [ ] Withdrawal is **recorded**, not just applied — the history shows consent given
      then withdrawn, with dates. Evidence is never destroyed (F-0007's own principle:
      a row is left unclaimed rather than deleted).
- [ ] Withdrawing expires the affiliate cookie by the same mechanism a decline does, so
      the two paths converge rather than diverging.
- [ ] `getConsentHistory` continuing to show `cookies` is then correct, because the
      decision has become changeable.
- [ ] **Does NOT reopen the cookie wall.** W-0050 is parked by CSJ — clicking Accept
      gives consent and registration proceeds, and the Article 7(4) question waits until
      the functional board is clear. This item is about withdrawal after acceptance and
      touches nothing about what registration requires.

## Working notes

(append-only)

- 2026-08-21 team-lead: filed on compliance-lead's finding, in its framing, from the
  coordinator block. **Raised as its own item rather than folded into W-0049** because it
  is a capability gap created by that fix rather than a flaw in it — folding it in would
  let it close when the batch closes. **"Own item" does not mean "later":** it should
  land before the consent work is deployed, since the state it creates is worse than the
  state it replaced in the one specific respect compliance names.
- 2026-08-21 team-lead: compliance flagged separately that the **two-type consent record**
  (`cookies_analytics` / `cookies_affiliate`, both written from the one click) should land
  **before anything is committed**, because a row written today is free and the same row
  later means fabricating records. That is a different item's work but the same fix
  window — sequence them together.

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev`, and the withdrawal capability now
  provably exists server-side while remaining unreachable.**
  `app/Http/Controllers/Api/CookieConsentController.php` exposes exactly one public method,
  `store()`, and `routes/api.php:145` exposes exactly one route to it. `grep` for a
  cookie-settings or withdrawal control across `resources/js`, `resources/mobile` and
  `public/pages` finds none — the banner remains the only control and still renders only when no
  decision exists.
  **Sharper than when filed, because W-0049 closed in between:** consent is now recorded and
  enforced server-side, so a recorded acceptance is a durable, demonstrable state the user has no
  interface to change. "Visible and irreversible is worse than invisible" applies more strongly
  now, not less.

- 2026-08-31 build-lead: **CLOSED BY CSJ'S RULING 2026-08-31 — not a defect.**

  > *"the user clicks accept and that is that, they do not withdraw, they do not go backwards, they consent through a simple button"*

  The item asked for a withdrawal interface, on the reasoning that F-0007 made Decline meaningful and thereby created a right with no way to exercise it. **CSJ has ruled that consent is a single accept button and there is no withdrawal journey.** That is a product decision, not a gap, and this item was asking for a feature nobody agreed to build.

  `declineCookies()` stays where it is (`cookieConsent.js:133`) — the banner uses it, and it is what makes a refusal honoured server-side through `fyn_cookie_consent`. It is not dead code, it is the Decline path on the banner itself.

  **Do not re-raise this.** The next sweep will find `declineCookies()` with one caller and read it as an unwired capability, exactly as W-0463's accessors read. It is wired; the withdrawal SCREEN is the thing that deliberately does not exist.

  Related and unaffected: **W-0050** removed the cookie WALL on registration in the same session. Declining no longer blocks account creation. That was a dead end, which is a different thing from the absence of a withdrawal screen — one trapped the user, this one simply is not offered.
