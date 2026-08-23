---
id: W-0252
title: Changing an account's risk level moved the label and the caption and did not change the projected value by a single pound
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0024-cycle4-investment-projection.md
owner: build-lead
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T20:00:00Z
claimed: 2026-08-22T20:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0251]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found as D-21 by `peak-earners-c4` (R-23), who was explicit that the behaviour was proven
and the cause was a lead, not a diagnosis.

Before and after saving an ISA's risk as High: label Medium → Upper-Medium, caption 5.41%
→ 7.07%, projected value **£217,451 → £217,451**. Over ten years that gap is worth roughly
£50,000. The panel was otherwise live — changing the horizon did move the number.

**The cache-key hypothesis proved out exactly.** The risk preference reaches the simulation
only as expected return and volatility, and neither was in the key, so no risk change could
dislodge a cached result. Same mechanism as W-0251; no separate fix was needed.

## Acceptance

1. Changing a risk preference moves the projected value, not just the caption. ✓
2. Verified through the real edit form in the browser. ✓

## Working notes

- **Closed by W-0251.** The fingerprint covers expected return and volatility.
- Browser-verified on Sarah (17), ISA 13, Medium → High saved through the account edit form
  (`risk_preference=high`, `updated_at 21:57:07`), page reloaded:
  caption 5.54% → **7.46%**, badge Medium → **High**, projected **£158,918 → £146,328**.
- It moved. It previously did not move at all.
- **The direction is downward at the 20th percentile and that is correct** — higher
  volatility widens the downside faster than higher expected return lifts it. The median
  rose £213,535 → £234,041 and p90 rose £350,399 → £482,838. See W-0259.
- **Could not be verified on David:** account 26's holdings were pushed to 105% allocation
  by another agent mid-session, which silently disables the form — raised as W-0257.
- Persona state restored: Sarah's ISA is back to `medium`.
