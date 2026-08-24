---
id: W-0045
title: All three relevant-property trust surfaces use non-palette blue-* and green-* — a live Rule 11 breach
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0005-design-lead-palette-and-copy.md
owner: design-lead
status: gated
severity: low
surfaces: [web, m]
source: flagged by design-lead during W-0021 review, 2026-08-21; deliberately not bundled into that fix
claimed: 2026-08-21
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

The relevant-property trust surfaces use `blue-*` and `green-*` Tailwind classes.
Neither is in the Fynla palette. Rule 11 permits only palette tokens —
`raspberry` / `horizon` / `spring` / `violet` / `savannah` / `eggshell` / `neutral` /
`light`.

design-lead found this while reviewing W-0021's acronym fix and **explicitly said not
to bundle it**, which was right: an acronym correction and a palette migration are
different changes with different risk, and mixing them makes both harder to review.

## Not a sign-off question — corrected 2026-08-21

An earlier revision of this item said CSJ had to decide "fix or grandfather", reading
Rule 15's forward-only grandfather clause across to Rule 11. That was wrong and is
retracted. Rule 15's clause is about **icons** and does not generalise. CSJ's position,
stated 2026-08-21: *"all surfaces need to adhere to the design rules, colour palettes
and design briefs."* Rule 11 has no grandfather clause because it does not have one —
not because one is implied.

The forward-only principle still holds in the narrow sense it was written for: do not
rip out **icon** violations while editing nearby. The icon at `TrustDetailView.vue:131`
was therefore left in place; only its colour token was migrated.

## Token mapping

Taken from `fynlaDesignGuide.md` §Color System / §Badges & Tags, reconciled against
`tailwind.config.js` (the enforcing declaration) and existing in-codebase precedent.

| Sense | Was | Now | Source |
|---|---|---|---|
| Informational / classification (relevant-property badges, tax panels) | `blue-50` / `blue-100` bg | `bg-light-blue-100` (#DDE2EF) | Guide §Badges "Info: `bg-light-blue-100 …`" |
| — body + heading text on that ground | `blue-700` / `blue-800` | `text-horizon-500` (#1F2A44) | Guide's designated text colour |
| — accent glyphs (list bullets, info icon, borders) | `blue-500` / `blue-600` | `light-blue-500` (#6C83BC) | Palette "Light Blue — subtle accents" |
| Success / active status | `bg-green-100 text-green-800` | `bg-spring-100 text-spring-700` | Guide §Badges "Success" variant |
| Positive financial value | `text-green-600` | `text-spring-600` | Matches `NetWorthOverviewCard`, `CashFlowProjectionChart` |
| Negative financial value | `text-red-600` | `text-raspberry-600` | Same precedent pair |
| Error banner | `bg-red-50 border-red-200 text-red-800` | `bg-raspberry-50 border-raspberry-200 text-raspberry-700` | `.card-error` in `app.css`; sibling `Estate/TrustForm.vue:268` |
| Error button + hover | `bg-red-800` / `bg-red-900` | `bg-raspberry-600` / `bg-raspberry-700` | Rule 8 — errors → raspberry |
| Hardcoded `rgba(59,130,246,0.2)` divider | — | `border-light-blue-500/20` | Rule 11 — no hardcoded hex/rgba |

**Why not `text-light-blue-700` as the guide's Info variant literally states:** that token
does not exist. `tailwind.config.js` defines `light-blue` at 100 and 500 only, so
`@apply text-light-blue-700` is a build error. The nearest defined token, `light-blue-500`
on `light-blue-100`, is 2.9:1 — below the WCAG 2.1 AA floor the guide itself sets.
`horizon-500` on `light-blue-100` is 10.9:1 and is the guide's body-text colour.

**Why not `.badge-info` (`bg-horizon-100 text-horizon-700`) from `app.css`:** `horizon-100`
is #F1F5F9, effectively a pale grey. The relevant-property badge sits directly beside the
`.badge.inactive` chip (`savannah-100` / `neutral-500`); making it grey too would collapse a
distinction the card relies on. `light-blue` keeps the informational reading, which makes
this a token migration rather than a redesign.

Every combination above was compiled against `tailwind.config.js` and confirmed to
resolve to a palette hex.

## What changed

Five files, presentation only. No Estate service or controller code touched. **39
occurrences; the whole Trusts module is now clean.**

- `resources/js/components/Trusts/TrustCard.vue` — `.badge.rpt`, `.badge.active`
- `resources/js/views/Trusts/TrustsDashboard.vue` — `.rpt-badge`, `.iht-charges-info`
  and its title, overdue-charge text (×2), `.error-state`
- `resources/js/views/Trusts/TrustDetailView.vue` — `.rpt-badge`, `.rpt-info-card` and
  its icon/title/list/bullets/divider, `.status-badge.active`, `.metric-value`
  positive+negative, `.error-state`, `.retry-btn` and its hover
- `resources/js/components/Trusts/TrustsOverviewCard.vue` — `.rpt-badge`,
  `.info-banner`, `.info-icon`, `.info-text` **(a fourth surface the item did not name)**
- `resources/js/components/Trusts/TrustFormModal.vue` — error banner

**W-0021 is undisturbed.** Both badges still read "Relevant Property Trust"; the
`flex-wrap` / `min-width: 0` / `white-space: nowrap` layout changes build-lead made so
the longer phrase fits are untouched — the diff is colour tokens only.

## `/m` (Rule 19)

**Nothing to change, verified not assumed.** `/m` has no trust card, no relevant-property
badge and no trust detail view — `resources/mobile/views/modules/Estate.vue:56-57` shows a
trusts **count row** only. `/m` also has zero non-palette Tailwind colour classes anywhere:
it does not use Tailwind, it uses `:root` custom properties in `resources/mobile/style.css`.

`/m` does carry a **separate** Rule 11 breach — hardcoded non-palette hex in
`resources/mobile/style.css` — recorded as a finding below, not fixed here.

## Acceptance

- [x] Palette tokens only, no hardcoded hex, applied to all relevant-property surfaces
      together — four files, not three.
- [x] Every token compiled against `tailwind.config.js` and confirmed to resolve.
- [x] W-0021's spelled-out wording and layout changes verified intact.
- [x] `/m` checked; no trust surface exists there.
- [x] Sweep for the same breach run across `resources/js/` and `resources/mobile/`.
- [ ] **Visual confirmation outstanding** — the tester owns the browser. Four screens
      need a look, listed below.

## Needs visual confirmation

Not browser-verified by me by policy — the browser is allocated to the tester.

1. `/trusts` — trust card badge row: "Relevant Property Trust" (pale blue) beside
   "Active" (green→spring). Confirm the two chips are still distinguishable from the
   grey "Inactive" chip and that the row still wraps rather than overflowing.
2. `/trusts` — "UK Trust Types Guide" panel and the "Inheritance Tax Charges" block,
   both now on `light-blue-100`. Confirm the heading is legible on that ground.
3. `/trusts/{id}` — header badges, and the "Relevant Property Trust - Tax Implications"
   card: bordered panel, bullet list, and the divider above "Next 10-year anniversary".
4. `/dashboard` — trusts overview card: white badge and info banner now outlined in
   `light-blue-500` rather than Tailwind blue.

David (16) and Sarah (17) were not touched.

## Working notes

(append-only)

- 2026-08-21 design-lead: fixed. Mapping above. The item named three surfaces; there
  are four — `TrustsOverviewCard.vue` (the dashboard overview card) carries a
  `.rpt-badge` and an `.info-banner` in the same non-palette blue. Fixed with the rest.
- 2026-08-21 design-lead: **root cause of why this survived.** `tailwind.config.js:9-12`
  safelists `blue-*`, `green-*`, `teal-*` and `red-*` explicitly, commented "Risk level
  colors - must always be included (used dynamically)". The safelist keeps non-palette
  utilities compiling forever, so nothing fails and nothing warns. That is the mechanism,
  and it is why the sweep still finds 807 occurrences across 75 files in `resources/js/`,
  plus 103 in `resources/views/welcome.blade.php` and 6 in `app.css`. Full
  ledger returned to the team lead with this item.

- 2026-08-21 team-lead: **CSJ DECISION on the design-guide defect found alongside this
  item — PARKED, low priority.** CSJ: *"again, this is parked for now, low priority,
  getting the system working is key."*
  Recorded so it is not rediscovered: **`fynlaDesignGuide.md` contains a clause that
  cannot be built.** Lines 789 and 814 specify the Info badge as
  `bg-light-blue-100 text-light-blue-700`, but `tailwind.config.js:106-109` defines
  `light-blue` at **100 and 500 only** — so `@apply text-light-blue-700` is a build
  error, not a lint warning. The nearest valid pair (`light-blue-100` on
  `light-blue-500`) measures **2.9:1**, below the AA floor the guide itself mandates.
  So the guide asks for a class that does not compile, and its closest buildable
  substitute fails the guide's own contrast rule. Fixing it means either adding a
  `light-blue-700` token or restating the Info badge against an existing pair — a
  design-system amendment, which is Azlan's, not a code fix.
  Unpark with W-0048.
