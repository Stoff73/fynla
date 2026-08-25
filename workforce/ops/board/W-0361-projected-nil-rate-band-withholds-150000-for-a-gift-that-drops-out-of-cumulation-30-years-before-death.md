---
id: W-0361
title: The projected nil rate band withholds £150,000 for a chargeable transfer that leaves cumulation thirty years before the modelled death
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: gated
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

**Lead item of the tax-compliance ledger, because it is the one anybody can reproduce
today.** Found by the review of W-0333. Full ledger: **F-0026 §7**.

`IHTCalculationService:563` — `projected_nrb_available` reuses the `nrb_available`
computed at `:208-209`, and `nrbDeductionForOneMember` measures the seven-year window
from **`today()`**.

That is correct for the "current" column, which assumes death today. It is wrong for
the **projected** column, which models a death 36 years away. David's £150,000
chargeable lifetime transfer of September 2020 still consumes £150,000 of the band at
a death in 2062 — **thirty years after IHTA 1984 s7(1) drops it out of cumulation.**

**Live on the persona right now:** `projected_nrb_available` reads **£500,000** where
**£650,000** is correct. **£60,000 of overstated projected Inheritance Tax**, on both
David (16) and Sarah (17).

The docblock at `:592-595` justifies not re-deriving the band in `assessTaxPosition`
on the grounds that it is *"a statutory amount reduced by chargeable transfers already
made, neither of which is a function of the estate's size."* True — **but it IS a
function of the DATE OF DEATH, and the two columns have different ones.** The
reasoning is sound and the conclusion does not follow.

## Acceptance

1. The projected column measures cumulation from the **projected date of death**, not
   from today.
2. `projected_nrb_available` is £650,000 for this household; the current column stays
   £500,000. Both stated before and after.
3. **Routed through `tax-compliance-reviewer` on the fix, not only on the review** —
   this is a statutory window, not an assumption.

## Resolution — 2026-08-24

`FailedGiftTaxCalculator::forMember()` now takes the date of death being modelled,
defaulting to today. The projection passes its own date, so the SAME rules are applied
to the right date instead of a second set being written for the projected column. Both
`today()` calls inside it — the search bound and `yearsSince()` — key off it.

`nrb_gross` is published on the assessment so the projection can re-strike the band
from the gross figure rather than trying to reverse-engineer it out of the net one.
`projected_nrb_gift_deduction` is published beside the band it reduces, so the projected
column reconciles the way the current one does.

Guard: `ProjectedNilRateBandUsesDeathDateTest` — a gift charged in the current column
and NOT in the projected one for a young household, and still charged for a household
whose modelled death falls inside the window. **Mutation-checked**: reverting to the
current column's band reds it. Estate 491, agents/plans/tax/tiers 410 — green.
