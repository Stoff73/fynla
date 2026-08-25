# W-0050 — compliance-lead → design-lead

**Acceptance item 3:** the "Cookies Required … keep you securely signed in" copy is removed
or rewritten to describe what is actually being consented to. **`compliance-lead` owns the
wording; `design-lead` reviews it as copy.** I have drafted; the voice call is yours.

Full draft and reasoning: `workforce/ops/reports/2026-08-21-W-0050-consent-validity-ruling.md` §6.
Ruling and evidence: the same report, §0–§5.

## Done

- Drafted three pieces: **A** the cookie banner request state (replaces `CookieBanner.vue:15-18`),
  **B** a recommendation to delete the decline confirmation (`CookieBanner.vue:38-63`),
  **C** a recommendation to delete the registration block outright with no replacement
  (`Register.vue:69-86`, `:90`, `:254-259`).
- Checked against Rule 9 / voice §4 (no acronyms — cross-site-request-forgery is never named
  to the user), Rule 15 (no icons added; draft C removes one), and voice C1–C7.
- Flagged the three public claims in the draft and what each depends on.

## Not done, and why

- **The voice pass is yours.** I wrote for the `Functional` register — terse, no personality.
  If it reads flat or stilted, that is the part I am least able to judge.
- **Granularity left binary.** As drafted, one choice covers two unlike purposes (measurement
  and commercial attribution). Separate controls would be better and Art 7(2) leans that way,
  but it is a build decision. If build-lead makes it granular, the two bold lines become two
  toggles and the copy does not need rewriting.
- **The cookie-wall question is not resolved and must not be resolved in copy.** Whether
  registration can be conditioned on consent is CSJ's. I drafted for the unconditioned state
  and set out separately what changes if the wall stays.

## What you need that isn't obvious from the artefacts

1. **The copy is BLOCKED on a code change and must not ship before it.** Draft A tells the
   user that declining stops analytics and affiliate tracking. That is **false for affiliate
   tracking today**: `CaptureAwcCookie` (`app/Http/Kernel.php:106`, global middleware, live on
   production — `AWIN_ENABLED` is `true` on fynla.org, confirmed) sets a 365-day `HttpOnly`
   `awc` cookie with **no consent check**, and `HttpOnly` means no browser-side code can clear
   it. Shipping this copy against the current code would swap one false justification for a
   different one. **Order: gate the Awin paths server-side → remove the wall → then the copy.**
2. **What copy can and cannot fix.** It fixes the *informed* defect. It does **not** fix
   *freely given* (only removing the condition does) and it does **not** fix *demonstrable*
   (Art 7(1) — consent exists only as a `localStorage` string with no server record; copy
   cannot create a record). Please don't let "the copy is fixed" be read as "W-0050 is done" —
   that is the likeliest misreading and it is wrong on two of three counts.
3. **Two things in Draft A are load-bearing for compliance, not style.** Both third parties
   must be **named** — Sch A1 para 1 conditions consent on "clear and comprehensive
   information", and a user cannot be informed about a processor whose name they have never
   seen. And affiliate must be described **commercially** ("credited", "pay them"), not as a
   technical detail. **Tighten the prose around these; please don't compress them away.**
   The current banner says only "analyse how you use our site" and never mentions affiliate
   tracking to anyone — that silence is one of the live findings.
4. **The strictly-necessary sentence must keep saying we are NOT asking.** Acceptance item 2
   requires the distinction, and it is also what removes the ground the old false
   justification stood on.
5. **Shape issue in your territory, flagged not ruled:** the decline confirmation exists only
   on the decline path, so accepting is one click and declining two, under a heading reading
   "Limited Functionality". Asymmetric friction on the privacy-preserving option, with the
   heading stating the choice's cost rather than the choice. If a confirmation is kept at all,
   it should be on both paths or neither.
6. **Rule 15:** `Register.vue:70-72` holds an inline SVG icon inside the block draft C
   deletes. Grandfathered, and it goes with the block — do not carry it into anything new.

## Assumptions I made

(stated as assumptions, not facts)

- That the wall is removed rather than kept. Draft A reads as it does because of that; if CSJ
  keeps the wall, §6's alternative applies and Draft A's closing line becomes false.
- That nothing beyond `Register.vue` and `router/index.js:1864` reads the consent flag. True
  when I checked; it is what makes "Declining doesn't change anything you can do on Fynla"
  sayable, so it needs re-checking before publication.
- That the Awin arrangement works commercially the way the code implies. **I have not seen the
  contract.** Someone who has should check the "credited … and we can pay them" line.

## Surfaces covered / not covered

- **web** — covered. All three drafts target web components.
- **`/m`** — no register view and no cookie-consent code at all (`grep` over
  `resources/mobile/` returns nothing), so there is no `/m` counterpart to write. Parity by
  absence, not exclusion. **If `/m` ever gains registration, Draft A is the copy it uses —
  from one home, not a second copy.**
- **iOS** — native has its own server-backed versioned consent system
  (`ios-native/Fynla/Features/Privacy/ConsentModels.swift`) and does not read this flag.
  Out of scope for the copy; in scope for the Rule 20 observation in the report (three
  surfaces, three mechanisms).

**This is not an approval.** I clear within competence or flag; publication still needs the
human button.
