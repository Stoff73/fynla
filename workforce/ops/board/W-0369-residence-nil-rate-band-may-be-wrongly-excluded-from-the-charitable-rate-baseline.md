---
id: W-0369
title: The residence nil rate band may be wrongly excluded from the 10% charitable-rate baseline — flagged for verification, not asserted
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

From the tax-compliance review of W-0333. **The reviewer flagged this at MEDIUM
confidence and explicitly declined to call it a defect. It is filed as a question to
settle, not a bug to fix.**

`IHTCalculationService:1249`, documented at `:1195`:

```php
$baseline = max(0, $netEstate - $nrbAvailable);   // RNRB deliberately excluded
```

The rest of the Sch 1A implementation is **right**: the charitable legacy is not
subtracted before the baseline is struck (it is deducted later in `assessTaxPosition`
at `:629`), which correctly reproduces the "add back the donated amount" step.

The open question is the residence band. The reviewer's reading is that Sch 1A para 5
was amended consequentially by F(No.2)A 2015 when ss8D–8M were inserted, and that
HMRC's guidance (IHTM45008) deducts the available residence nil-rate band at the same
step as the nil rate band for deaths on or after 6 April 2017 — **but was not certain
enough to assert it.**

Direction of error if that reading is right: excluding the residence band makes the
baseline **larger**, the 10% threshold **higher**, and qualification for the 36% rate
**harder** — conservative, but it would deny the reduced rate to households entitled to
it. **Changes nothing for this persona**, whose projected residence band is fully
tapered to £0.

## Acceptance

1. Settled against **Sch 1A para 5 and IHTM45008** by `tax-compliance-reviewer`, with
   the reasoning recorded — **whichever way it goes.**
2. If the current behaviour is correct, the comment at `:1195` says so with the
   citation, so this is not re-opened a third time.
