---
id: W-0504
title: Three of the /m dashboard's donut rings are filled to hardcoded constants, so the arc means nothing
mission: persona-run-peak_earners-2026-08-20
owner: design-lead
reviewers: [build-lead]
status: done
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

## 2026-09-01 — CLOSED

**Acceptance 1 — all three rings now render a derived figure**, each from the shared
`dashboardFigures` derivation `/m` already imports:

| Ring | Was | Now |
|---|---|---|
| Net worth | `progress: 72` | `f.netWorth.equityPct` |
| Protection | `covered ? 85 : 0` | `covered ? 100 : 0` |
| Investment | `value > 0 ? 72 : 0` | `f.investment.sharePct` |

**Acceptance 2 — arc and number are one quantity now.** The net-worth ring was the whole
defect in one line: `progress: 72` beside `vizNum: <trend>%`. It now fills, prints and
captions from `equityPct`.

**The decision the item reserved for Azlan, taken here and flagged.** The item asked
whether the net-worth ring should show its labelled metric (Trend), adopt web's (Equity),
or stop being a ring. **Equity**, for three reasons: a trend is signed and a 0-100 arc
cannot render a fall — which is likely why it was left constant in the first place; web
shows Equity from the identical field (`GamifiedDashboard.vue:320`), so the two surfaces
now say the same thing; and it needed no new derivation. The `Trend` caption is gone with
it. **Reversible in one line** if Azlan wants the ring dropped instead.

Protection moved 85 → 100 rather than to some other partial: cover here is binary, and 85
drew a partial arc for a state that has no partial.

`const trend = f.netWorth.trendPct` was left orphaned by the change and removed; ESLint
clean.

**Guard:** three cases appended to `resources/mobile/views/__tests__/Dashboard.spec.js` —
no hardcoded percentage in any ring, every `progress:` expression derived from `f.`, and
the net-worth arc and number reading the same field.

**An instrument error worth recording**, because it is the third of its kind this
session: the guard first failed against **its own comment** — the comment describing the
old `progress: 72` was scanned as if it were code, so the test reported the defect it was
documenting. Comments are now stripped before matching.

Tests: **197 passed, 34 files** across `/m`; ESLint clean on the changed file.

**Acceptance 3 is NOT done.** No `/m` browser verification against a persona away from
the constants. `peak_earners` is the right case — the item measured it live at 11%
investments against a 72% arc, and its investments are £220,000 — but the local
`public/m-build/` is a csjones build whose router base is `/fynla/m/app`, so the `/m` SPA
does not boot on `localhost` (established on W-0034). This needs a csjones deploy to
close. Recorded, not implied.
