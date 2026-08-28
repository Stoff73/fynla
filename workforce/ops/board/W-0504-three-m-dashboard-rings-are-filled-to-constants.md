---
id: W-0504
title: Three of the /m dashboard's donut rings are filled to hardcoded constants, so the arc means nothing
mission: persona-run-peak_earners-2026-08-20
owner: design-lead
reviewers: [build-lead]
status: queued
severity: low
surfaces: [m]
source: found while consolidating the card derivation, W-0245, 2026-08-26
prior_art_checked: 2026-08-26
prior_art_found: [W-0245, W-0238]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

`resources/mobile/views/Dashboard.vue` fills three of its five donut rings with
constants rather than derived figures:

| Card | `/m` ring | web ring |
|---|---|---|
| Net worth | `progress: 72` | `equityPct` — share of assets owned outright |
| Protection | `progress: covered ? 85 : 0` | `covered ? 100 : 0` |
| Investment | `progress: value > 0 ? 72 : 0` | `investmentPct` — share of total assets |

Only the savings and retirement cards use bars driven by real data.

They arrive from `7eaa085cb feat(mobile+auth): mobile dashboard redesign` and carry
no comment. **They read as placeholder values from a visual redesign that were never
wired to data**, which is also why they went unnoticed: an arc at 72% looks
deliberate.

Verified live on 2026-08-26 for the `peak_earners` persona: the net worth ring
renders at 72% while the number printed inside it is `+0%`, and the investment ring
renders at 72% while investments are **11%** of that household's assets. The arc and
the caption beside it are telling the user different things.

## Why it was not fixed with W-0245

W-0245 consolidated the card **derivation** and states that labels, routes and
visualisation stay per-surface. A constant is not a derivation, so removing it was
out of that item's scope — and choosing what each ring should show is a design
decision rather than a mechanical substitution.

**The derived figures are now available at each site**, so the change is small once
the decision is taken: `f.netWorth.equityPct` and `f.investment.sharePct` are already
in scope on those lines, and the `ponytail:` comments there point here.

## The decision, which is Azlan's

The net worth card is the awkward one. `/m` labels its ring **"Trend"** and prints
the trend percentage inside it, where web labels the same ring **"Equity"**. A trend
can be negative and does not map onto a 0–100 arc, which may be exactly why it was
left at a constant.

So the choice per card is: show the figure the ring is already labelled with, adopt
web's metric, or drop the ring for a shape that suits a signed number.

## Acceptance

1. Each of the three rings either renders a derived figure or stops being a ring.
2. Whatever it renders agrees with the number printed inside it — an arc at 72% next
   to `+0%` is the defect, independent of which metric wins.
3. Verified on `/m` against a persona whose figures are not near the constants, so a
   ring that failed to change is visible. `peak_earners` at 11% investments is a good
   case; anything near 72% is not.
