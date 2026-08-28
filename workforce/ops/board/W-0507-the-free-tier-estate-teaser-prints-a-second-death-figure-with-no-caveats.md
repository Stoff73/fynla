---
id: W-0507
title: The free-tier estate teaser prints a second-death Inheritance Tax figure with none of the caveats the full table carries
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [compliance-lead]
status: queued
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-24T19:05:00Z
claimed: null
blocked_by: []
gate: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0466, W-0363, W-0467]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
source: observed on csjones while verifying the 24 August deploy — the peak_earners demo estate page
---

## Intent

Observed live on `csjones.co/fynla/estate` as the `peak_earners` demo, 2026-08-24:

> "Your household could face up to **£343,512** in Inheritance Tax on the second death."
> "Estimated Inheritance Tax exposure: **£343,512**"

**"On the second death" is the projected model.** Two caveats now attach to that figure
on the full table and neither reaches the teaser:

- `unmodelled_relief_caveat` (W-0466) — Agricultural Property Relief and the
  Alternative Investment Market are not modelled.
- `projected_pension_exclusion_caveat` (W-0363) — unused defined contribution pensions
  are excluded from the projected estate, and form part of it from April 2027.

Both live in `IHTCalculationTable.vue`, which the teaser does not render — the teaser is
its own component behind the upgrade gate. **This is the W-0466 F3 shape exactly**: the
caveat was put where the figure was thought to live, and a second surface prints the
same figure without it.

**Who sees it:** every free-tier user, which is every user who has not upgraded — and
the demo personas, which is the first thing a prospective customer sees.

## Acceptance

1. The teaser carries the same caveats as the full table when they apply, from the same
   engine-published strings (Rule 20 — no second copy of either sentence).
2. `/m`'s estate teaser too (Rule 19).
3. A guard that a surface printing an Inheritance Tax figure also renders whatever
   caveats the engine published with it — the third instance of this, so it wants a
   test rather than a third manual fix.
4. `compliance-lead` on the copy.

## Working notes

- 2026-08-24 — Found while browser-verifying the deploy, not by a test. Worth noting how:
  the estate page for a preview persona is the free-tier teaser, so anyone verifying the
  caveat work on a demo account sees a page where it legitimately does not appear — and
  could easily read that as "verified".

## Provenance — renumbered on salvage, 2026-08-28

Raised as **W-0483 on `main`** (commit `526327655`, 2026-08-24) and never merged to
`dev`, because that work went to `main` directly. `dev` had meanwhile assigned W-0483 to
an unrelated item — "a co-owner who borrowed alone cannot be shown as owing alone" — so
this takes the next free id rather than displacing it.

Nothing else in the file was touched: the observation, the two named caveats, the
acceptance criteria and the `compliance-lead` reviewer are as originally written. The
cited prior art (W-0466, W-0363, W-0467) all exist on `dev` under those ids, so the
`extend` outcome still reads correctly.

This is the second item to survive the `main` reconciliation; the first was
`PremiumTestPersonaSeeder` in PR #734. Everything else `main` held was either stale,
deliberately deleted here, or superseded.
