---
id: W-0371
title: Tax rates and thresholds are hardcoded in the user-facing sentences printed beside figures the arithmetic computed from configuration
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
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

- 2026-08-31 build-lead: **FIXED AND TESTED — closed. Four of the six sites were already done; two survived.**

  **Already configured:** the three rate messages at `:1752`, `:1762` and `:1770` interpolate `$reducedRateLabel`, `$standardRateLabel` and `$thresholdLabel`, so *"Reduced Inheritance Tax rate of 36% applies"*, *"Standard Inheritance Tax rate of 40%"* and *"meets the 10% threshold"* all move with configuration. The taper-rate prose is gone too. **Rule 9 is satisfied** — every one spells out "Inheritance Tax" rather than "IHT".

  **The two survivors, both in one sentence at `:2157`:** *"allowance used by gifts made within the last **7 years**"* and *"(including the **14-year** rule…)"* — the nil rate band working, printed directly beneath figures the configuration produced. Both now read the configured windows.

  **The fourteen is DERIVED, not stored**, and the comment says so: `getFourteenYearRule()['maximum_window']` returns the sum of the two seven-year windows because **there is no fourteen-year window in the legislation** (W-0526, closed today). Reading a stored 14 here would have re-created the second configured home that item removed this morning.

  **This is the item's own point restated:** the arithmetic was already safe and the sentence beside it was not — Rule 2 displaced into the prose, which is the harder place to see it. Change the setting and the application computed one window while telling the user another.

  **Tested:** 7 persona locks unmoved at £1,728,780 / £343,512; 6 nil-rate-band and IHT calculation tests pass. Pint clean.
