# W-0451 — build-lead (`fix-cycle4-figures`) → quality-lead

**Branch doc:** `workforce/branches/fixes/F-0033-cycle4-the-charitable-saving-and-the-percentage-denominator.md`
**Statutory gate:** CLEARED WITH CONDITIONS — `workforce/ops/handoffs/W-0451/tax-compliance-reviewer-verdict-2026-08-23.md`. **C1 was blocking and is discharged.**

## Done

**One definition of what the reduced charitable rate saves**, in
`IHTCalculationService::assessTaxPosition()`:

```
saving = standard × E − reduced × (E − shortfall)      E = chargeable estate
```

The reviewer confirmed this is the lawful answer of the three candidates, and
confirmed the statutory property it rests on (Sch 1A para 5 adds the donated
amount back, so the baseline does not move as the gift grows).

**Four mechanisms retired to one.** `WillAnalysisService` computes nothing now —
it expresses the settled position. `EstatePlanService::charitablePercentage()` is
deleted. `IHTPlanning.vue:962` computed the saving in the browser and now reads
the published figure.

**C1 discharged:** `determineIHTRate()` publishes the survivor's identity beside
the amount, and the trace, the `actions` array, the recommendation `description`
and `/plans/estate`'s panel all name the right person. The panel disclosure is one
server-composed `basis` key printed verbatim by both renderers.

**Verified:** 883 Pest / 2927 assertions across six suite families; 179 Vitest;
Pint clean. Ten mutations, all killed. Both pages read on both accounts —
evidence `162-` to `170-` in `tests/Persona/20-08-2026_run/pass-a-web/`.

## Not done, and why

- **C2 (the disclosure) is FILED, not fixed** — `W-0462`, HIGH, gate
  `compliance-lead`. The reviewer asked for it to travel separately: its gate is
  not the tax reviewer, and "save £74,987" attached to an action costing the
  family £37,891 is a Consumer Duty question first.
- **`GiftingStrategy:227`** is a fifth mechanism computing this quantity, in a
  method with **zero production callers**. Left alone; recorded so nobody finds it
  and concludes the consolidation was incomplete.
- **The `actions` array is not rendered on any page I could find.** Covered by
  test only.

## What you need that is not obvious

- **My browser evidence from the first pass (`163-`) contains the C1 defect.** It
  photographs "If David increases charitable bequests…" over Sarah's figures.
  `168-` to `170-` are the post-fix readings. Do not read `162-` to `167-` as
  evidence of the final state.
- **The clearance does not cover the screen**, and covers **only the charitable
  hunks** of `IHTCalculationService.php` and `EstateAgent.php` — both files carry
  other batches' edits in this shared tree.
- **Four branches the persona cannot exercise** are named individually in F-0033
  §8. A green screenshot of a page that never entered the branch would be worse
  than none.

## Assumptions I made

- **That naming the survivor is better than saying "the will operating on the
  second death" without a name.** The reviewer allowed either. I chose the name
  because the *action* must identify a specific will or it is not actionable.
- **That the disclosure should fire only when the reader is not the survivor.** A
  note that always appears is one a reader learns to skip. Asserted both ways.
- **That the third-person convention should stay** — the sentences already read
  "David's charitable giving…", so "Sarah's will" is consistent rather than new.
  It reads slightly oddly from Sarah's own session; it is not a regression, and I
  did not change a convention the batch was not asked to change.

## Surfaces covered / not covered

**Covered:** desktop web — `/estate`, `/plans/estate`, `/actions/estate/*`, both
spouses' sessions.
**Not applicable:** `/m` and native. Re-run independently: zero consumers of
`charitable_analysis`, `charitable_giving`, `potential_saving` or any charitable
key across `resources/mobile/` and `ios-native/`. Matches `surfaces: [web]`.
