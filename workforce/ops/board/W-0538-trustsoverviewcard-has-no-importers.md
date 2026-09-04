---
id: W-0538
title: TrustsOverviewCard.vue is rendered by nothing — the dashboard trusts card three items have now "fixed" reaches no screen
mission: board-verification-31-august
owner: null
reviewers: [design-lead]
status: queued
severity: low
surfaces: [web]
created: 2026-09-04
source: found browser-verifying W-0045 on csjones, 2026-09-04
prior_art_checked: 2026-09-04
prior_art_found: [W-0045, W-0376]
prior_art_outcome: extends — W-0376 was the same shape (dead sites carrying their own copy of a rule)
constitution_refs: [07-quality-bar]
---

## Intent

`resources/js/components/Trusts/TrustsOverviewCard.vue` has **no importers**.
Searched across `resources/`, `tests/` and `app/`: the only occurrence of the
string anywhere is the component's own `name: 'TrustsOverviewCard'` at `:59`.
No view imports it, no route renders it, no dynamic import references it.

Confirmed on screen: `/dashboard` as the `peak_earners` household — which holds
a trust and is premium — renders no trusts card.

## Why it matters more than a spare file

W-0045's fourth acceptance criterion is *"`/dashboard` — trusts overview card:
white badge and info banner now outlined in `light-blue-500` rather than
Tailwind blue"*. That fix was made in this file, and the design-lead's working
note calls it the surface "the item named three surfaces; there are four".

So a design fix was written, reviewed and signed off against a component nobody
renders — and the same is true of any future fix that lands here. This is the
W-0376 shape: a dead site that carries its own copy of a rule, so the rule looks
maintained and reaches no user.

The palette work inside it is correct. The question is whether the card was
meant to be on the dashboard and was never wired, or whether it is a leftover.

## Acceptance

1. A decision: **wire it** into the dashboard, or **delete it**.
2. If wired, W-0045's fourth surface gets the visual check it never had.
3. If deleted, the deletion is recorded against W-0045 so its acceptance 4 is
   closed as "surface removed", not left reading as unverified for ever.
4. Either way, something fails when a component under `resources/js/components/`
   loses its last importer — this is the second instance (W-0376 was the first).
