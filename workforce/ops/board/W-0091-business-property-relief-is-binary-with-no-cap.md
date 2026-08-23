---
id: W-0091
title: Business Property Relief is applied as binary 100% with no cap, while a full relief regime sits configured and unread
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
reviewers: [tax-compliance-reviewer, compliance-lead, product-lead]
status: queued
claimed_by: null
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T20:40:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: ["W-0154 (F6 — configured but read by nothing)", "2026-08-21-iht-calculation-audit.md F6"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: raised by fix-batch-E, 2026-08-21, at team-lead's direction while fixing W-0154 R2 in the same file — assessed as a relief model rather than a guard, so raised rather than built inside a phantom-column fix
---

## Intent

`EstateAssetAggregatorService.php:114-141` decides whether a business interest is exempt
from Inheritance Tax. It emits a **boolean**:

```php
$ihtExempt = false;
if ($business->bpr_eligible && $business->trading_status === 'trading') {
    if ($business->acquisition_date) {
        $yearsOwned = Carbon::parse($business->acquisition_date)->diffInYears(now());
        $ihtExempt = $yearsOwned >= 2;
    } else {
        // If no acquisition date set but marked BPR eligible, assume eligible
        $ihtExempt = true;
    }
}
```

Every consumer then does `->reject(fn ($a) => $a->is_iht_exempt)`. **A qualifying business
is worth either its full value or nothing to the estate — there is no partial relief.**

### The regime that is configured and unread

`TaxConfigService::getInheritanceTax()['business_relief']` holds, verified live
2026-08-21:

| Key | Value |
|---|---|
| `allowance_cap` | 2,500,000 |
| `cap_in_effect` | true |
| `relief_above_cap` | 0.5 |
| `min_ownership_years` | 2 |
| `excluded_businesses` | dealing_in_securities, dealing_in_stocks_shares, dealing_in_land_or_buildings, making_or_holding_investments |
| `aim_shares_outside_cap` | true |
| `cap_transferable_to_spouse` | true |
| `allowance_cap_effective_date` | 2026-04-06 |

**None of it is read by the aggregator.** The effective date is **four months past**
(today is 2026-08-21). The £2,500,000 figure itself is correct — gov.uk, published
26 Nov 2025, updated 3 Mar 2026, raised from £1m on 23 Dec 2025 — confirmed by
`tax-compliance-reviewer` in the W-0154 audit (F6).

### Consequence

A business interest above the cap is relieved in full when it should be relieved to
£2,500,000 and at 50% above that. For an estate holding a £5,000,000 trading business
the difference is not marginal: roughly £1,250,000 of chargeable value, **£500,000 of
Inheritance Tax at the standard rate**, reported as nil.

Two smaller defects sit in the same block:

- **`min_ownership_years` is configured and the code hardcodes `>= 2`** (Rule 2).
- **"No acquisition date but marked eligible → assume eligible."** The two-year rule is
  a statutory condition; an unrecorded acquisition date means it cannot be established,
  and assuming it in the user's favour invents a fact. Same disease as W-0154 R2's
  `?? 0` and the `current_age = 30` fabrication removed in W-0035.

## Why this was not fixed inside W-0154

Assessed and deliberately left. **It is a relief model, not a guard.** A cap requires
*partial* relief, and the asset row cannot express it: `is_iht_exempt` is a boolean and
every consumer rejects on it. Honouring the cap means changing the shape of what the
aggregator publishes and every consumer that reads it, plus the excluded-business types,
the AIM carve-out, spouse transferability of the cap, and the effective date. The
team-lead's instruction was explicit: if it needs a relief model, raise it and leave it,
rather than building one inside a phantom-column fix.

## Acceptance

- [ ] Relief is **partial**, not binary: value up to `allowance_cap` at 100%, the excess
      at `relief_above_cap`, with the cap and the rate read from `TaxConfigService`
      (Rule 2). No literal £2,500,000 or 0.5 anywhere.
- [ ] `allowance_cap_effective_date` is honoured, so a calculation dated before it does
      not apply a cap that was not yet in force.
- [ ] `excluded_businesses` is applied — a business dealing in securities, stocks and
      shares, land or buildings, or making or holding investments does not qualify,
      whatever `bpr_eligible` says.
- [ ] `aim_shares_outside_cap` and `cap_transferable_to_spouse` are either implemented
      or explicitly recorded as out of scope with a reason. Not silently ignored.
- [ ] `min_ownership_years` comes from configuration rather than a hardcoded `2`.
- [ ] **An unrecorded acquisition date no longer qualifies a business by assumption.**
      Whether that means excluding it or surfacing "we cannot confirm the two-year rule"
      is a product decision — it changes what a user is told about their own business.
- [ ] The asset row can carry a partially relieved value, and **every** consumer of
      `gatherUserAssets()` that does `reject(is_iht_exempt)` is updated — not just the
      Inheritance Tax path (Rule 20: consolidate, do not align).
- [ ] Figures verified against hand-computed expected values, per W-0154's standard.
- [ ] `/m` and iOS checked rather than assumed (Rule 19).

## Working notes

(append-only)

- 2026-08-21 fix-batch-E: raised while fixing W-0154 R2 in this file. **Not started** —
  no code changed here. The `business_relief` config block above was dumped live from
  `TaxConfigService` on 2026-08-21, so the keys and values are current, not from a
  seeder read in isolation.
- 2026-08-21 fix-batch-E: whoever takes this should read the W-0154 audit F6 first
  (`workforce/ops/reports/2026-08-21-iht-calculation-audit.md`) — it records the gov.uk
  provenance of the £2.5m figure and the wider list of configured-but-unread values, of
  which this is the largest.


## Merged from W-0362 — 2026-08-23 (CSJ direction: remove the duplicate)

W-0362 raised the same defect two days later from the tax-compliance review of W-0333
and is now closed as a duplicate of this item. What it added, kept here:

**Agricultural Property Relief is absent entirely — worse than the cap.**
`TaxConfigService::getAgriculturalRelief()` is populated with the same capped structure
(`allowance_cap: 2500000`, `cap_in_effect: true`, `relief_above_cap: 0.5`,
`cap_shared_with_bpr: true`, `allowance_cap_effective_date: "2026-04-06"`) and has **no
caller in the estate path at all**. Agricultural property receives no relief whatsoever,
which errs in the OPPOSITE direction to the Business Property Relief defect: it
overstates tax rather than understating it.

**The cap is shared between the two reliefs**, so they cannot be fixed independently —
`cap_shared_with_bpr: true` means one £2.5m allowance covers both, and a fix that
applies the cap to Business Property Relief alone will over-relieve any estate holding
both.

**Where it reaches the Inheritance Tax figure:** `$userGrossAssets`
(`IHTCalculationService:158`) and `$projectedBusiness` (`:485-496`), both reading
`is_iht_exempt` straight from the boolean.

### Acceptance (extends the list above)

1. Relief computed from `getBusinessRelief()` / `getAgriculturalRelief()`, cap and rates
   included — never a boolean.
2. `allowance_cap_effective_date` decides whether the cap applies, so the answer follows
   the configuration rather than the calendar in someone's head (Rule 2).
3. AIM shares at 50%, outside the cap.
4. The shared cap is modelled once across both reliefs, not twice.
5. **`tax-compliance-reviewer` on the fix** — gate now set on this item.
6. Before/after on a household holding a business above the cap. **The persona holds no
   business interests at all** and cannot exercise any of this; the largest business
   interest on the dev database is £750,000, so nothing on that data is currently wrong.
   The defect is latent, not live — that bears on sequencing, not on whether it is real.


## Rolled under W-0463 — 2026-08-23

This item stays open and is still the Business/Agricultural Property Relief fix. It is
now one instance of **W-0463**, which carries CSJ's standing instruction that
`TaxConfigService` is the source for every estate and tax service, and — more
importantly — the coverage guard without which this defect recurs. Fixing this item
alone leaves 19 other configured rules with no consumer.


## Business Property Relief — done, 2026-08-23

Implemented under W-0463: `EstateAssetAggregatorService::applyBusinessPropertyRelief()`
allocates one shared cap across the estate, 100% to £2,500,000 and 50% above, gated on
`allowance_cap_effective_date`. Eight tests including the £6m worked example from this
item (£4.25m relieved, £1.75m chargeable).

**Agricultural Property Relief is NOT done and is not implementable as the schema
stands** — there is no agricultural asset type or flag in the data model. Registered in
the W-0463 exclusions register with that reason. When it becomes expressible it must
join the existing allocation, not get a second cap (`cap_shared_with_bpr`).

`tax-compliance-reviewer` has not run.
