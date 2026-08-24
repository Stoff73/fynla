---
id: W-0245
title: The web and /m dashboards duplicate the whole card-building computed, so a card can be fixed on one surface and not the other
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: medium
surfaces: [web, m]
created: 2026-08-22T20:50:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0238, W-0015]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found while fixing **W-0238**, partly addressed there and filed for the remainder.

### The defect

`resources/js/views/GamifiedDashboard.vue` and `resources/mobile/views/Dashboard.vue`
each build the same five cards from the same endpoint with their own near-identical
computed. They had already drifted:

| | web | `/m` |
|---|---|---|
| retirement headline fallback | `pot_value` → `income_gap` → `value` | `pot_value` → **`net_worth…assets.pensions`** → `income_gap` → `value` |

The `/m`-only reach into the net worth block is the shape of the disease: one
surface was patched for a symptom and the other was not, and nothing made them
disagree loudly.

### What W-0238 did about it

Extracted **only the retirement headline rule** into
`resources/js/utils/retirementHeadline.js`, imported by both (the `ownership.js`
precedent, where `/m` imports the web util by relative path). Both fallback chains
are deleted.

**The other four cards are still duplicated** — net worth, protection, savings,
investment — including the emergency-fund bar, the equity percentage and the
holdings caption.

### Why the rest was not folded in

The two computeds differ in ways that are **not** drift and must be preserved: the
labels (`Savings` vs `Bank Accounts`), the routes (`/net-worth/cash` vs `/savings`),
and the visualisation parameters. Separating the shared derivation from the genuine
per-surface presentation is a refactor of two large files with its own verification,
and doing it inside a defect fix would have put an untested rewrite behind a
one-line correction.

## Acceptance

1. One home for every card's **derivation**; labels, routes and visualisation stay
   per-surface.
2. `retirementHeadline.js` is the pattern to follow, not a second one to add to.
3. Both dashboards verified against the same payload after the extraction, on web
   AND `/m` — noting `/m` serves `public/m-build/` and never Vite, so it needs a
   bundle rebuild to verify at all.
