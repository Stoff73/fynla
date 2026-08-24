---
id: W-0366
title: Chargeable lifetime transfers made 7–14 years before death wrongly reduce the death estate's own nil rate band, and the comment above the line states the correct rule
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: medium
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

From the tax-compliance review of W-0333.

`IHTCalculationService:1770`:

```php
$nrbUsedByCLTs = min($nrbSingle, (float) $cltsIn7Years + (float) $clts7to14Years);
```

**The comment immediately above it (`:1762-1764`) states the rule correctly** —
*"These CLTs don't incur IHT themselves (outside 7-year window), but they DO reduce the
NRB available for PETs in the final 7 years"* — **and then the code does something
else.** `$nrbUsedByCLTs` flows into `total_nrb_used` (`:1783`), which `calculate()`
subtracts from the estate's own band at `:209`.

Under IHTA 1984 **s7(1)** the cumulative total for the **death estate** is chargeable
transfers in the **seven years before death only**. A transfer made ten years before
death is outside it. It belongs in the cumulation used to tax a later **failed PET** —
which is what the comment describes and what `$nrbRemainingForPETs` at `:1773`
correctly implements — but it must not **also** come off the estate's band.

Worked: a £300,000 chargeable transfer ten years ago and nothing else. The code gives
the estate `325,000 − 300,000 = £25,000`. The correct answer is **£325,000**.
**£120,000 of overstated tax.**

Bites only users with recorded transfers in the 7–14 year window. **The persona has
`clts_7_to_14_years: 0`**, which is why it has survived.

## Acceptance

1. The death estate's band is reduced by transfers in the seven years before death only.
2. The 7–14 year cumulation still applies to failed potentially exempt transfers —
   `$nrbRemainingForPETs` must not regress.
3. A fixture with a transfer in the 7–14 year window; the persona produces none.
4. **`tax-compliance-reviewer` on the fix.**
