---
id: W-0259
title: The single figure on the projection card is the 20th percentile — the one band where taking more risk makes the number go down
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
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
