---
id: W-0081
title: The /m stylesheet hardcodes non-palette hex and invents two neutral shades
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0005-design-lead-palette-and-copy.md
owner: design-lead
status: blocked
severity: low
surfaces: [m]
source: found by design-lead during the W-0045 palette sweep, 2026-08-21
prior_art_checked: 2026-08-21
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Rule 11 permits palette tokens only and forbids hardcoded hex. `/m` does not use
Tailwind — it uses `:root` custom properties in `resources/mobile/style.css` — so
the W-0045 sweep for `blue-*` / `green-*` classes found nothing there. Looking at
the actual hex values does.

**Nine non-palette colours, all in `resources/mobile/style.css`:**

| Hex | Where | Note |
|---|---|---|
| `#6B7280` | lines 84, 94, 109, 168, 219 | Tailwind gray-500. The `/m` secondary-text colour. |
| `#E5E7EB` | lines 77, 78, 81, 166, 167 | Tailwind gray-200. Card borders, field borders, sheet handle. |
| `#C8CFDC` | 91, 180 | Not in the palette. Hero/net-worth sub-label. |
| `#E8ECF3` | 93, 182 | Not in the palette. Hero insight text. |
| `#F1F3F6` | 162, 184 | Not in the palette. Detail-row divider. |
| `#FBF8F4` | 88 | Not in the palette. Welcome gradient stop. |
| `#C42B54` | 82 | Not in the palette. `.m-err` — should be raspberry. |
| `#9CA3AF` | 42 | Declared as `--neutral-400`. Tailwind gray-400. |
| `#4B5563` | 44 | Declared as `--neutral-600`. Tailwind gray-600. |

The last two are the worst of it: they **invent shades of a palette token**. The
Fynla palette defines `neutral-500` (`#717171`) and nothing else in that ramp, so
`--neutral-400` and `--neutral-600` look like palette tokens at every call site
while being Tailwind greys wearing a Fynla name.

Everything else in the file is correct — the raspberry, horizon, spring, violet,
savannah and light-pink ramps are all byte-accurate palette values, and
`resources/mobile/tokens.js` mirrors five of them correctly for the confetti
generators.

## Why it was not fixed with W-0045

It is global `/m` chrome. `.m-card`, `.m-sub`, `.m-detail-key` and
`.m-section-label` are on essentially every mobile screen, so this moves the whole
`/m` surface at once — a different change with different risk from recolouring
four trust components, which is exactly the reasoning that kept W-0045 out of
W-0021.

## Acceptance

1. The seven literal hex values mapped to palette tokens, `.m-err` to raspberry.
2. `--neutral-400` and `--neutral-600` resolved — either removed in favour of
   `neutral-500`, or the palette formally gains those shades. **The second is a
   design decision and belongs to Azlan, not to an agent.**
3. `resources/mobile/tokens.js` re-checked against `style.css` afterwards — its
   docblock requires the two stay byte-identical.
4. Verified on `/m` per `verify-m`, across at least a card screen, a detail screen
   and the dashboard hero.

- 2026-08-21 team-lead: **PARKED under CSJ's palette decision on W-0048** — no colour work
  of any kind until the functional defect board is clear. The finding stands as written
  (nine hardcoded non-palette hex values in the `/m` stylesheet, two of which —
  `--neutral-400` / `--neutral-600` — **invent shades of a palette token**, so they read
  as Fynla tokens at every call site while actually being Tailwind greys). Status moved
  `queued` → `blocked` so it is not picked up by a sweep. Unpark with W-0048.
