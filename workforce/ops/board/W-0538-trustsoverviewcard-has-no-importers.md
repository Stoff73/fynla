---
id: W-0538
title: TrustsOverviewCard.vue is rendered by nothing — the dashboard trusts card three items have now "fixed" reaches no screen
mission: board-verification-31-august
owner: build-lead
reviewers: [design-lead]
status: done
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

## Outcome — done, 2026-09-04

**CSJ's decision:** *"this does need to be wired into the dashboard but for the
webservice only, not for the /m route, for /m route the user clicks the trusts
nav in sidebar and sees the trusts overview."*

Wired into `resources/js/views/GamifiedDashboard.vue` in **both** of its layout
blocks — the file carries a narrow and a wide block, both in the DOM and swapped
by media query at `gamified-dashboard.css:32-36`, so a card in only one of them
is invisible at the other width. Both are the web SPA; `/m` is
`resources/mobile/` and is untouched.

**Gated on `auth/hasCapability('estate')`.** `/api/estate/trusts` sits behind the
`estate.full` middleware, so without the gate every user without the capability
takes a 403 on each dashboard load and is shown an empty card for a module they
cannot open. That getter exists for exactly this — its docblock says screens gate
on it so they never offer an entry form the API will refuse.

**Rule 15.** The card's info banner carried an SVG icon. A dashboard card is a
banned surface, and the card had never rendered, so the icon lands new the moment
it is wired in — forward-only means it complies now. Icon and its dead CSS rule
removed; the banner keeps its text and its `light-blue-500` border.

Tests: `tests/frontend/views/TrustsCardIsOnTheDashboard.test.js` — 5, reading the
files rather than mounting, because the defect was the ABSENCE of a render path
and a mounted-component test would have passed throughout. They pin the import
and registration, both render sites, the capability gate, the absent icon, and
that `/m` is deliberately excluded. Full frontend suite: **811 passed, 71 files**.

## Still open — the general guard

Acceptance 4 asked that *something fails when a component loses its last
importer*. The guard added here is specific to this card. A repo-wide orphan
check is the right shape but needs a decision first: it would almost certainly
find pre-existing orphans and so need an allowlist, which is a separate call.
Recorded rather than half-built.

## Adjacent — `/m` has no trusts surface at all

CSJ's instruction describes `/m` as a place where "the user clicks the trusts nav
in sidebar and sees the trusts overview". **That does not exist today:**
`resources/mobile/router.js` has no trusts route, the drawer in `MobileChrome.vue`
has no trusts link, and `resources/mobile/views/modules/Estate.vue:85` shows only
a "Trusts — N trusts" count row inside the estate module. W-0045 recorded the
same in August: *"`/m` checked; no trust surface exists there."*

So either the instruction was a statement of intended future behaviour, in which
case a `/m` trusts route, nav entry and overview need building, or it described
what CSJ believed already existed. Flagged to CSJ rather than guessed at — Rule 19
says a plan silent on `/m` still has `/m` in scope, and this plan is not silent,
it is mistaken about what is there.
