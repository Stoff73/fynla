---
id: W-0371
title: Tax rates and thresholds are hardcoded in the user-facing sentences printed beside figures the arithmetic computed from configuration
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333. **Rule 2, displaced from the arithmetic into
the prose — which is the harder place to see it.**

The computation correctly reads `$ihtConfig['standard_rate']`,
`getCharitableReducedRate()`, `getCharitableThresholdPercent()` and
`$ihtConfig['rnrb_taper_rate']`. Then the sentence printed beside the number states the
rate as a literal:

| Line | Text |
|---|---|
| `:1297` | *"Reduced IHT rate of **36%** applies…"* |
| `:1313` | *"Standard IHT rate of **40%** applies…"* |
| `:1325` | *"meets the **10%** threshold"* / *"Leave **10%+** of your baseline estate"* |
| `:1178` | *"(**£1 reduction per £2** over threshold)"* |
| `:1666` | *"gifts made within the last **7 years**"* |

**Change the configuration and the application computes one rate while telling the user
another.** This is exactly the failure Rule 2 exists to prevent — the arithmetic is
already safe, and the sentence beside it is not.

Note also **Rule 9**: these strings should spell out Inheritance Tax rather than "IHT".

## Acceptance

1. Every rate, threshold and period in user-facing copy is interpolated from the same
   variable the arithmetic uses.
2. Acronyms spelled out (Rule 9) while the strings are being touched.
3. A test that moves a configured rate and asserts the **sentence** follows, not only
   the figure.
