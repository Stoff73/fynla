---
id: W-0364
title: The 2027 pension scenario adds the pension pots to the estate but reuses the smaller estate's allowances and tax rate
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
closed: 2026-08-29
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: route
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333. **This is W-0136's defect in the one place
W-0136 did not reach.**

`IHTCalculationService:1881-1889`:

```php
$postAmendmentNetEstate = $currentNetEstate + $totalPensionValue;
$totalAllowances = $baseCalc['total_allowances'] ?? 0;
$ihtRate         = $baseCalc['iht_rate'] ?? ...;
$postAmendmentTaxableEstate = max(0, $postAmendmentNetEstate - $totalAllowances - $charitableDeduction);
```

Adding the pension pots **enlarges the estate**, and both tests that depend on estate
size are skipped:

- **Residence nil rate band taper (IHTA 1984 s8D(5)).** An estate at £1.7m with a
  £600k pension crosses £2,000,000 and loses the residence band at £1 per £2 — this
  household would lose the whole £350,000. Reusing the smaller estate's
  `total_allowances` **understates the post-2027 bill by up to £140,000**.
- **The 10% charitable rate test (Sch 1A).** The baseline grows with the estate while a
  fixed cash legacy does not, so a household on 36% can fall to 40%. Reusing
  `$baseCalc['iht_rate']` carries the old rate across.

**The mechanism already exists in this file.** `assessTaxPosition($netEstate,
$residenceNetValue, $assessment)` does exactly this re-assessment, and the comment at
`:503-527` explains at length why the projection needs it. This block never got the
same treatment — it hand-rolls three lines instead.

## Acceptance

1. The post-amendment scenario calls `assessTaxPosition()` against its own estate.
2. A fixture crossing the £2,000,000 taper only once the pension is added — the taper
   must fire in the amended scenario and not in the base.
3. A fixture where a fixed charitable legacy passes 10% of the base baseline and fails
   it once the pension is added; the rate must move 36% → 40%.
4. **`tax-compliance-reviewer` on the fix.**

## Resolution — 2026-08-24

One call to `assessTaxPosition()` with the enlarged estate, which answers both skipped
tests at once — the s8D(5) taper and the Sch 1A 10% test — instead of reusing the
smaller estate's `total_allowances`, `iht_rate` and `charitable_deduction`. The taper
base is enlarged by the pension too, because s8D(5)(d) strikes it on the estate before
reliefs. Exactly W-0136's fix, applied to the one place W-0136 did not reach.

Guard: `Pension2027ScenarioReassessesTest` — a household at £1.9m of home plus £600k of
pension crosses £2,000,000 only once the pension is added, so the band must taper away
and the increase must exceed a flat 40% of the pot; and a household far below the
threshold gains no invented bill. **Mutation-checked**: restoring the old arithmetic
reds the taper case. Estate + agents 595 green.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`gated`.

- **Delivered by:** Stoff73
- **Evidence:** merged in #714; commit `2e9d490d0` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
