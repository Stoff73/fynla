---
id: W-0539
title: /m has no trusts surface at all — no route, no nav entry, no overview, only a count row inside the estate module
mission: board-verification-31-august
owner: null
reviewers: [product-lead, design-lead]
status: deferred
severity: low
surfaces: [m]
created: 2026-09-04
source: found wiring W-0538, 2026-09-04
prior_art_checked: 2026-09-04
prior_art_found: [W-0045, W-0538]
prior_art_outcome: extends — W-0045 recorded the same absence in August and moved on
constitution_refs: [07-quality-bar]
---

## Intent

Wiring the trusts card into the web dashboard (W-0538), CSJ said: *"for /m route
the user clicks the trusts nav in sidebar and sees the trusts overview."*

**That does not exist.** Verified 2026-09-04:

- `resources/mobile/router.js` — no trusts route. The file lists every `/m` path
  and trusts is not among them.
- `resources/mobile/components/MobileChrome.vue` — the drawer's nav sections carry
  no trusts link, and there is no trusts entry in the icon map.
- `resources/mobile/views/modules/Estate.vue:85-86` — the only trusts anything on
  the surface: a row reading "Trusts — N trusts", a count with no destination.

W-0045 recorded the same in August — *"`/m` checked; no trust surface exists
there"* — and closed around it.

## Why it is deferred rather than open

**CSJ, 2026-09-04: "lets leave trusts for now, mark this as deferred and we will
get back to it."**

The web side is done and verified (W-0538). This is the `/m` half, and it is a
build — a route, a nav entry and an overview screen — not a gap to be patched.

Note that `/m` reaching trusts is **not** simply Rule 19 parity with the web
dashboard card: CSJ's direction is explicitly that `/m` should NOT get a
dashboard card, and should reach trusts through its nav instead. So this item is
a different design, not a copy of the web one.

## Acceptance, when it is picked up

1. A `/m` route for trusts, a drawer nav entry, and an overview screen.
2. The overview is `/m`'s own design, not a port of `TrustsOverviewCard.vue` —
   the dashboard card is deliberately web-only.
3. Gated the way the endpoint is: `/api/estate/trusts` sits behind `estate.full`,
   so the surface must not offer what the API refuses (the W-0538 lesson).
4. A trust that fails to load must not be reported as "no trusts" — the same
   distinction W-0538 had to fix on the web card.
5. Whatever it shows, verified on csjones per the `verify-m` skill.
