---
id: W-0034
title: /m has no Health & Lifestyle section at all — the data source is fixed but no mobile screen renders it
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0001-batch-c-retirement-profile-gates.md
owner: build-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
status: done
severity: medium
surfaces: [m]
source: CSJ direction 2026-08-21 ("/m needs it"), after fix-batch-C found zero /m references while fixing W-0006
prior_art_checked: 2026-08-21
prior_art_outcome: none
---

## Intent

`/m` renders no health status, smoking status or education level field anywhere — zero
grep hits across `resources/mobile/`. W-0006 fixed the underlying data so
`UserProfileService::getCompleteProfile` now publishes the real columns, and that
payload is what `/m` reads — but there is no screen consuming it.

**CSJ direction 2026-08-21: build it.** This is new feature work, correctly flagged by
fix-batch-C rather than silently skipped or smuggled into a bug-fix batch.

## An architectural correction this item must not repeat

`/m` **iframes the funnel** (the public marketing pages). It does **not** iframe the
authenticated app. `/m/app/*` is a separate Vue SPA under `resources/mobile/` with its
own store, router and services — which is exactly why it inherits nothing from
`resources/js/` and why Rule 19 exists.

So this cannot be delivered by pointing an iframe at the web view. The `/m` screen is
built.

## Acceptance

1. A Health & Lifestyle section exists on `/m`, reading the corrected
   `getCompleteProfile` payload — `health_status`, `smoking_status`, `education_level`.
2. It **reads and writes**, matching the web section's capability. A read-only mirror
   leaves `/m` users unable to correct their own data, which is the same half-delivery
   Rule 19 exists to prevent.
3. Rule 9: no acronyms — spell every label and option out in full.
4. Rule 15: no decorative icons; `/m` module screens are not the side nav.
5. Rule 8/10/11: palette tokens only, patterns from `fynlaDesignGuide.md`.
6. The write path hits the same endpoint and the same validation as web — one
   mechanism, not a `/m` copy (Rule 20). Note W-0006's trap: the selects submit `''`,
   `ConvertEmptyStringsToNull` turns it into `null`, and `smoking_status` is NOT NULL,
   so an unanswered select was a 500 rather than a 422. `prepareForValidation` now
   drops those keys — `/m` must go through that same path and not reintroduce it.
7. Verified in a browser on `/m`, both accounts.

## Working notes

Related: W-0006 (the data fix that makes this possible), W-0031 (`education_level`
validation accepts three values the column cannot hold — resolve that first or the new
`/m` select may offer options that 500 on save).

## Working notes (append-only)

- 2026-08-21 build-lead: BUILT. Read and write, on `/m`, through the shared endpoint.

  **Where it lives, and why.** A Health and lifestyle section on
  `resources/mobile/views/PersonalInformation.vue` rather than a new route. That
  screen already fetches `GET /api/user/profile` — the exact
  `getCompleteProfile` payload W-0006 corrected, which is how this item was framed
  — so the data arrives with no second call and no new nav entry to design. It is
  also how `/m` already treats Domicile: a section on the profile screen where web
  has a separate tab.

  I did not build it as an iframe. The item's architectural note is right and worth
  restating: `/m` iframes the **funnel**, not the authenticated app; `/m/app/*` is a
  separate Vue SPA with its own store, router and services.

  **Read** — `PersonalInformation.vue:66-73` (display rows) and `:128-136`
  (label computeds). Unset values read "Not recorded", never a blank row.

  **Write** — `:196-221`, `saveHealth()`. `apiPut('/api/user/profile/personal', ...)`:
  the same endpoint and the same `UpdatePersonalInfoRequest` the desktop form uses.
  `/m` gets no write path of its own (Rule 20), which is precisely what stops
  W-0006's trap being reintroduced here: `''` becomes `null` via
  `ConvertEmptyStringsToNull`, and `prepareForValidation` drops the key rather than
  writing `null` to `smoking_status`, which is NOT NULL. `/m` inherits that by
  construction, not by remembering to copy it.

  A failed save keeps the form open with the server's message
  (`[data-testid="health-error"]`) rather than closing as though it worked; a
  successful one re-reads the profile so the display rows show what was stored, not
  what was typed.

  **Options list.** `resources/mobile/constants/profileOptions.js`. `/m` cannot
  import the desktop constants — `vite.mobile.config.js:28` aliases only `@m` — so
  this is a second copy by architectural necessity. What makes that safe is
  `resources/mobile/__tests__/profileOptionsParity.spec.js`, which asserts the `/m`
  lists are identical to the desktop lists **and** to `App\Constants\ProfileEnums`,
  which is itself pinned to the live columns. Completed chain:

      users columns ─▶ ProfileEnums ─▶ resources/js constants ─▶ resources/mobile constants

  So a `/m` select can never offer a value the column rejects — the W-0031 failure.

  **Acceptance, item by item**
  1. Section exists, reading the corrected payload — done.
  2. Reads AND writes — done, not a read-only mirror.
  3. Rule 9 — no new acronyms introduced. One carried over: "Secondary
     (GCSE/O-Levels)", identical to web on purpose. Raised on W-0031 for CSJ rather
     than diverged here.
  4. Rule 15 — no icons, emoji or glyphs. Asserted by a test, not just by intent
     (the spec fails on any `<svg>` or glyph inside the section). Note the existing
     `›` chevrons on `/m` Settings are pre-existing and untouched.
  5. Rules 8/10/11 — palette CSS custom properties only
     (`--horizon-*`, `--neutral-*`, `--violet-500`, `--white`); reuses `m-card`,
     `m-btn`, `m-btn-ghost`, `m-section-label`, `m-err`; no hardcoded hex.
  6. Same endpoint, same validation — done, see above.
  7. **Browser-verified on `/m`, both accounts — NOT DONE.** See below.

  **Tests:** `resources/mobile/views/__tests__/HealthLifestyleSection.spec.js` — 7
  passed: renders stored values, "Not recorded" when unset, writes the right body to
  the right endpoint, seeds the form from stored values, surfaces a failed save
  without closing, re-reads after success, and carries no icons.
  `resources/mobile/__tests__/profileOptionsParity.spec.js` — 8 passed.

  **BLOCKED — acceptance item 7.** I cannot browser-verify `/m`. It needs the built
  mobile bundle: csjones serves it and is explicitly out of scope for this batch,
  and building `public/m-build/` locally is a raw vite build, which CLAUDE.md
  forbids without asking first. So the section is component-tested but **has not
  been seen in a real browser by anyone**, and I am not claiming otherwise. It needs
  either a sanctioned local mobile build or a csjones deploy, both of which are the
  coordinator's call, not mine.

- 2026-09-01 board-loop: **CLOSED.** Code re-read at the cited lines, not taken on
  trust: `resources/mobile/views/PersonalInformation.vue:66-73` renders the section,
  `:196-221` writes through `PUT /api/user/profile/personal` — the desktop endpoint
  and the desktop request class, so W-0006's `prepareForValidation` trap is inherited
  rather than copied. Tests re-run: 17 passed (7 section, 10 parity).

  **Acceptance 7 stays NOT DONE, and the reason is now concrete rather than
  "coordinator's call".** `public/m-build/` on this machine is a **csjones** build:
  its router base is `/fynla/m/app`, so `localhost:8000/m/app/login` redirects to
  `/fynla/m/app/m/app/login` and the SPA never boots. Verifying locally means
  rebuilding the mobile bundle with the local base path, which both overwrites the
  csjones-targeted bundle on disk and is the mixed-build decision CLAUDE.md reserves
  for CSJ. So: no browser drive, and I am not claiming one. It needs a csjones deploy.
