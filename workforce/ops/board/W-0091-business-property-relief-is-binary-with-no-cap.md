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
gate: null
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
