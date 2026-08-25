---
id: W-0021
title: Trust card shows the bare acronym "RPT" — Rule 9 violation, and the same page spells it out correctly elsewhere
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0003-batch-b-estate-wills.md
owner: build-lead
status: done
surfaces: [web, m, ios]
created: 2026-08-21T08:55:00Z
claimed: 2026-08-21T09:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: []
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
reviewers: [design-lead]
---

## Intent

Found by: persona run `peak_earners`, **premium sweep** (local `localhost:8000`),
account **David Jones (primary)**, user id 16.

**Surface:** desktop web, `/trusts`, trust card badge.

Severity: **low**, but it is an unambiguous violation of a CSJ-owned rule with the
correct wording already present ten lines away in a sibling component.

### Expected

`CLAUDE.md` Rule 9 — *No Acronyms in User-Facing Text*: "All acronyms must be spelled
out in user-facing text... The only exception is **ISA**."

The badge on a relevant property trust should read **"Relevant Property Trust"**.

Persona reference: `tests/Persona/peak_earners.md:465` — the Jones Children's
Education Trust is flagged `Relevant Property Trust: Yes`.

### Actual

The trust card renders the bare acronym:

```
Jones Children's Education Trust
£185,000
Discretionary Trust   [RPT]   [Active]
```

`resources/js/components/Trusts/TrustCard.vue:27`:

```vue
<span v-if="trust.is_relevant_property_trust" class="badge rpt">RPT</span>
```

### The same page already gets it right

`resources/js/views/Trusts/TrustsDashboard.vue:110-112`, in the UK Trust Types Guide
directly below the card:

```vue
<span v-if="trustType.isRPT" class="rpt-badge">
  Relevant Property Trust
</span>
```

So one page shows a user both "RPT" and "Relevant Property Trust" for the same
concept. That is a consistency defect on top of the rule violation, and it makes the
fix unambiguous — the approved wording already exists.

### Evidence

Screenshot: `tests/Persona/20-08-2026_run/pass-a-web/12-web-david-trust-185000-RPT-acronym-W-0021.jpg`
— the card showing the `RPT` badge, with the "UK Trust Types Guide" heading visible
below it.

Report: `reports/R-05-premium-sweep.md`.

### Repro

1. Premium account → `/trusts` → Create Trust → any **Discretionary** trust.
2. The saved card shows a badge reading `RPT`.
3. Expand "UK Trust Types Guide" on the same page — the equivalent badge there reads
   "Relevant Property Trust".

## Acceptance

- [ ] `TrustCard.vue:27` spells out "Relevant Property Trust", matching
      `TrustsDashboard.vue:110-112`.
- [ ] If the full phrase does not fit the badge, the design change is agreed rather
      than the acronym retained — Rule 9 has no length exemption.
- [ ] Sweep the trusts module for other acronyms in user-facing text.
- [ ] `/m` and iOS trust surfaces checked (Rule 19).
- [ ] Re-verified live in the browser by the persona run.

## Working notes

(append-only)

- 2026-08-21 persona-tester: found during the premium sweep. Routed to design-lead per
  the copy-defect routing rule. Not fixed by me.
- Worth recording what is **correct** here, because it is good behaviour: the trust
  itself saved accurately (`trust_type` discretionary, initial £150,000, current
  £185,000, created 2020-09-01, settlor, trustees and beneficiaries all matching the
  persona), and `is_relevant_property_trust` was **derived automatically** as `true`
  from the discretionary type rather than asked of the user — which is right, since
  discretionary trusts are relevant property trusts by law. The form has no
  relevant-property checkbox and does not need one.

- 2026-08-21 build-lead: **FIXED**, browser-verified. Handing to quality-lead.
  Picked up as part of Batch B rather than routed separately.

  **Two violations, not one.** The item named `TrustCard.vue:27`; the sweep it
  asked for found a second: `resources/js/views/Trusts/TrustDetailView.vue:37`
  rendered `RPT` too — fourteen lines above `:134`, which already spells out
  "Relevant Property Trust - Tax Implications" on the same screen. Both now read
  **"Relevant Property Trust"**, matching `TrustsDashboard.vue:110-112`.

  **design-lead ruled the badges needed layout changes, not colour changes**, and
  both were applied:
  - `TrustCard.vue` `.item-name` was `flex-shrink: 0` with no wrap, sharing a row
    with the trust-type text and the Active badge — the longer phrase would have
    overflowed on a narrow card. Added `flex-wrap: wrap` and `min-width: 0`,
    removed `flex-shrink: 0`, and added `white-space: nowrap` to `.badge` so the
    phrase wraps as a unit rather than breaking mid-word.
  - `TrustDetailView.vue` `.header-badges` had no `flex-wrap`. Added.
  No font-size, padding or colour changes.

  **Acronym sweep of the trusts module (acceptance 3): clean.** Grepped
  `resources/js/components/Trusts/` and `resources/js/views/Trusts/` for
  IHT/CGT/NRB/RNRB/PET/CLT/LPA/IIP/BPR/APR/HMRC/DGT/GWR/POAT. Two hits, both in
  an HTML comment and a CSS comment (`TrustsDashboard.vue:116` and `:767`) — not
  user-facing. Nothing else to change.

  **Browser evidence (localhost:8000, Playwright, real login + MFA).**
  Discretionary trust created (`is_relevant_property_trust` derived `true`, as
  the item notes it correctly is):
  - `/trusts` card badge renders **"Relevant Property Trust"**.
  - `/trusts/4` header badge renders **"Relevant Property Trust"**.
  Throwaway user and trust deleted afterwards; **David's £185,000 trust (16) was
  not modified.**

  **`/m` and iOS: nothing to change, verified rather than assumed.** Neither has
  a trust card. `/m` shows a trusts COUNT row only
  (`resources/mobile/views/modules/Estate.vue:56-57`) with no drill-down, and
  `ios-native/Fynla/Features/Estate/EstateView.swift:168` likewise. No `RPT`
  string exists anywhere in `resources/mobile/` or `ios-native/`.

  **Reported, NOT fixed — design-lead flagged and told me not to bundle it:** all
  three relevant-property surfaces use non-palette Tailwind defaults, a live Rule
  11 breach — `.badge.rpt` `bg-blue-100 text-blue-700`, `.rpt-badge`
  `bg-blue-50 text-blue-700`, `.rpt-info-card` `bg-blue-50 border-blue-300`, and
  `.status-badge.active` `bg-green-100 text-green-800` where `spring-*` is the
  token. `blue-*` is not in the palette. Recolouring a badge while fixing its
  text is exactly the "tidy up while editing nearby" Rule 15 forbids, so it wants
  its own item with CSJ's sign-off. (`TrustDetailView.vue:131-133` also has an
  icon on a banned detail surface — grandfathered, left alone.)

- 2026-08-21 build-lead: Rule 22 handover for this batch is
  `workforce/branches/fixes/F-0003-batch-b-estate-wills.md` — it carries the dispatch
  verbatim, the full `tax-compliance-reviewer` verdict on W-0020 (§3), the approved
  `compliance-lead` + `design-lead` refusal copy for W-0019 verbatim (§3a), decisions
  taken, dead ends ruled out, and environment state. **Rule 14's loop is NOT closed by
  me on this item** — see §8; the browser evidence recorded above is my own, gathered
  before the no-self-verification policy landed, and needs independent re-verification.
