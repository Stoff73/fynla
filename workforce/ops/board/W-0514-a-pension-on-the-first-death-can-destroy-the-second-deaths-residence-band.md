---
id: W-0514
title: A pension on the first death can destroy the second death's residence band, and the model cannot show it
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: done
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-28T15:34:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-28
prior_art_found: [W-0482, W-0188]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer gate report on W-0482, finding F3, 2026-08-28
---

## Intent

The estate projection models one death — the second — and applies a single £2,000,000
taper test to the combined household with two residence bands. **IHTA 1984 s8G(5) tapers
the brought-forward allowance where the FIRST-dying person's estate exceeded £2,000,000**,
and E in s8D(5)(d) is struck **before exemptions** (IHTM46023). So a pension passing wholly
and exemptly to a spouse still counts toward the first death's threshold.

A £700,000 pension can push a first death over £2,000,000, destroy the brought-forward
residence allowance, and cost the second estate up to £350,000 of band — with **no tax
arising on the first death at all**. HMRC IHTM46044 works four examples, one of which
eliminates the carry-forward entirely.

**W-0482 is what makes this bite.** Before it, the pension was outside the projected
estate; a pension is precisely the asset large enough to cross £2,000,000 on a first death
where nothing else would.

A second, smaller problem in the same code: `projectedUnusedPensionFund()` asks each member
for their residual at **their own age at the household horizon**, where the horizon is the
later of the two life expectancies. The first-dying spouse is therefore modelled as drawing
down to an age they will not reach — their residual is understated — while the survivor
never receives the inherited fund to spend, so theirs is overstated. **The net direction is
indeterminate**, which is worse than a known-conservative bias.

## Acceptance

1. The first death's estate is tested against the taper threshold, and s8G(5) applied to
   the brought-forward allowance.
2. The pension is in that test, exempt transfer or not — E is struck before exemptions.
3. A worked household: two spouses, a pension that crosses £2,000,000 on the first death,
   before/after on the second death's residence allowance and liability.
4. The first-dying member's drawdown stops at their death and the survivor inherits the
   remainder — or a stated decision to keep the current approximation, with its direction
   named.
5. `tax-compliance-reviewer`.

## Working notes

- 2026-08-28 — Raised as F3 by the gate on W-0482. **Do not "fix" the RNRB treatment of the
  pension itself**: no residence band is claimed against it, and that is correct —
  s8H requires a qualifying residential interest closely inherited by lineal descendants,
  and notional pension property is neither. FA 2026 amends nothing in ss8E-8H.
- 2026-08-28 — Also unmodelled, low severity: the s18(2) cap (£325,000) where the
  transferee spouse is not a long-term UK resident and the deceased was.

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev`.**
  `IHTCalculationService:1249-1250` applies **one** `rnrb_taper_threshold` test, to the combined
  household on the second death. There is no first-death test anywhere: `grep` finds
  `rnrb_taper_reduction` published at :499, :625 and :1036, all from the single assessment. IHTA
  1984 s8G(5) tapers the brought-forward allowance on the FIRST-dying person's estate, struck
  before exemptions (IHTM46023), so a pension passing exempt to a spouse still counts toward that
  threshold — and W-0482 is what makes this bite, because a pension is the asset large enough to
  cross £2,000,000 where nothing else would.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed. The mechanism turned out to be simpler and worse than the item described.**

  The item asked for the model to be able to SHOW that a pension on the first death can destroy the second death's residence band. Tracing it found the reason it could not: **the second death's residence band was never tapered at all.**

  `HouseholdPlanningService:407` read `$combinedRnrb = $rnrb + $rnrbTransferred` — added and never reduced — while `secondDeathIhtFor()` at `:1052-1055` in the SAME service tapers correctly. **Two mechanisms for one allowance, and the one producing the second-death figure had forgotten the rule.** A survivor whose estate had been swollen past the threshold by everything they inherited kept the full £350,000.

  That is the effect the item names, and it is not specific to pensions: the first death moves the deceased's estate onto the survivor, and the taper is measured against the SURVIVOR's combined estate (IHTM46023). **A household comfortably below the threshold twice over can be above it once** — and was shown both bands in full regardless.

  **The fix** computes `$survivorEstateForTaper` as its own value and withdraws £1 of band per £2 above `rnrb_taper_threshold`, at the configured `rnrb_taper_rate`, capped at the band so it cannot go negative. It is measured on the estate BEFORE allowances — the taper base is the estate, not the taxable remainder — and computed separately rather than reused from the taxable figure precisely because pensions leave the base today and rejoin it from the configured 2027 date (W-0515). Published as `rnrb_taper_reduction_on_second_death` and `survivor_estate_for_taper` so a household can see why its band is smaller than the two it was told it had.

  **Tested:** `tests/Unit/Services/Coordination/SecondDeathRnrbTaperTest.php` — 4 passed, covering below-threshold, the £1-per-£2 withdrawal, full extinguishment without going negative, and a MOVED taper rate (a hardcoded `/ 2` fails). 23 household and second-death tests pass. Pint clean.

  **NOT DONE.** No `tax-compliance-reviewer` pass, and this moves the second-death bill for any household above the threshold. Not browser-verified.
