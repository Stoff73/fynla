---
id: W-0369
title: The residence nil rate band may be wrongly excluded from the 10% charitable-rate baseline — flagged for verification, not asserted
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
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

- 2026-08-31 build-lead: **RESOLVED AND TESTED — closed. The exclusion is CORRECT; what was missing was the authority for it.**

  The item was filed as a question — *"may be wrongly excluded — flagged for review"* — and the answer is that `IHTCalculationService:1594` is right. **Schedule 1A para 3 deducts "the available nil-rate band"**, and IHTM45031's worked examples deduct the NRB alone. The residence nil-rate band is an allowance against the taxable estate, not a component of the baseline the 10% test is measured against.

  **The defect was that the code asserted this without a source.** The line read `// Calculate baseline: Net Estate - NRB (RNRB is excluded from baseline calculation)` — a bare claim about a statutory denominator that a reader could neither verify nor safely change. That is how a correct line becomes a wrong one: the next person sees an unexplained exclusion, assumes an oversight, and "fixes" it.

  **And the direction of that mistake is stated too**, because it is not obvious: deducting the residence band would SHRINK the baseline and so shrink the 10% threshold, **qualifying households for the reduced rate that do not meet it** — an under-charge, not an over-charge.

  Also recorded: `$nrbAvailable` is the pooled band including any transferred from a predeceased spouse, which is what "available" means in para 3.

  **Tested:** 103 charitable and persona tests pass, 308 assertions; the persona locks unmoved. Pint clean.

  No code behaviour changed, so no `tax-compliance-reviewer` pass is owed — the conclusion is that the existing behaviour was already right.
