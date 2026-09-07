---
id: W-0259
title: The single figure on the projection card is the 20th percentile — the one band where taking more risk makes the number go down
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T21:55:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0217, RiskPreferenceService, InvestmentPerformance.vue]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

**W-0217 acceptance 2 asks for a property a correct Monte Carlo does not have**, and this
item exists to record that rather than let a future agent try to build it.

It asks that a higher risk preference produce a higher projected return *at every
percentile reported*. Measured after the W-0251 fix, £100,000, no contributions:

| Risk | 10y p20 | 10y p50 | 30y p20 | 30y p50 |
|---|---:|---:|---:|---:|
| Low (2%, 3%) | £112,065 | £121,556 | £155,506 | £176,553 |
| Medium (5%, 10%) | **£121,508** | £159,020 | £232,865 | £366,033 |
| Upper-Medium (6.5%, 15%) | £115,682 | £169,415 | **£258,336** | £481,183 |
| High (8%, 20%) | **£104,829** | **£177,379** | £216,768 | **£581,670** |

**The median and the upside rise monotonically with risk. The 20th percentile is
hump-shaped**, peaking further up the risk scale the longer the horizon. Added volatility
widens the downside faster than added expected return lifts it — that is what a
conservative percentile is. Forcing monotonicity would mean breaking the model.

**The product consequence is the reason this is worth a decision.** The one number on the
card is "Projected Value (80%)". Observed live: Sarah raised her ISA from Medium to High
and her headline fell **£158,918 → £146,328**, while her median rose £213,535 → £234,041
and her 90th percentile rose £350,399 → £482,838. Everything shown was true; what the user
was shown was that taking more risk made them poorer.

## Acceptance

CSJ to decide what the card leads with. Options, not recommendations:
1. Lead with the median and keep the 80% band on the chart only;
2. Show median and 80% band side by side, so the widening is visible;
3. Keep the 80% headline and accept the inversion as intended conservatism.

Whatever is chosen, W-0217 acceptance 2 should be amended to the property the model
actually guarantees: **the median and the spread rise with risk.**

## Working notes

- The tests in F-0024 assert median-rises and band-widens, and assert only that p20
  *moves* — deliberately not its direction.
- Rule 12 is not engaged: these are currency projections, not scores.

---

## Closed 2026-09-01 — option 2 taken, and W-0217 amended

**Option 2: show the median and the conservative band side by side.** Reasoning, so it
can be overturned cheaply:

- **Option 3 (keep the 80% headline) leaves the observed harm live.** Sarah raised her
  ISA from Medium to High and the one number on her card fell £158,918 → £146,328 while
  her median rose £213,535 → £234,041. Everything shown was true and what she was shown
  was that taking more risk made her poorer. Calling that intended conservatism does not
  stop it happening.
- **Option 1 (median only, band on the chart) removes information.** The downside is the
  half a cautious user most needs, and the chart is below the fold.
- Option 2 adds a figure and removes none. Both numbers were already computed.

### What changed

| Card | Was | Now |
|---|---|---|
| `InvestmentPerformance.vue:87-101` | "Projected Value (80%)" — p20 alone | median, then "Lower outcome (4 in 5 do better)" |
| `InvestmentProjections.vue:193-201` | p20 alone | median (`formatProjectedMedian`), then the band |
| `PensionList.vue:352-360` | p20 alone | `percentile_50_at_retirement`, then the band |

**The label was also wrong on its own terms.** "Projected Value (80%)" reads as "80% of
the value" or "80% likely"; it is neither. "Lower outcome (4 in 5 do better)" says what
the number is without a percentile or an acronym (Rule 9).

### W-0217 acceptance 2 — amended, as this item required

`W-0217`'s acceptance 2 asked that a higher risk preference produce a higher return **at
every percentile**. That is a property a correct Monte Carlo does not have, and a future
agent trying to satisfy it would have had to break the model. It is struck through in
place, with the measurements, and replaced by the property the model does guarantee:
**the median rises with risk and the spread widens with it.**

**Regression:** 736 frontend tests across the component suites.

**Rule 19:** `grep` finds no percentile headline in `resources/mobile` — `/m` does not
print this figure — so there is no counterpart to change. iOS is out of scope.

**If CSJ prefers option 1 or 3**, each card is a two-line revert.
