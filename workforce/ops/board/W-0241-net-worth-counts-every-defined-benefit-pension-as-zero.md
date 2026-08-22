---
id: W-0241
title: Net worth counts every defined benefit pension as £0, by summing a column db_pensions does not have
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T20:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0226, W-0238]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Found while fixing **W-0238**, outside its scope and **not fixed** — scope discipline.

### The defect

`app/Services/Mobile/MobileDashboardAggregator.php:427`:

```php
$dbPensionValue = (float) $user->dbPensions->sum('transfer_value');
```

`db_pensions` has no `transfer_value` column. Verified against the live schema: the
value columns are `accrued_annual_pension`, `projected_annual_pension_at_nra_gbp`
and `lump_sum_entitlement`.

Because this is a **collection** sum over models, a missing attribute reads as null
and the sum is `0.0` — silently, for every user, forever. `net_worth.breakdown.
assets.pensions` therefore contains the defined contribution pots only.

Measured on the persona: Sarah Jones (17) holds an NHS final salary scheme worth
£35,000 a year with a £105,000 lump sum entitlement, and her net worth reports
`pensions: 0`.

### Why it is not simply a typo

**There is no obviously right replacement, which is why this is an item and not a
line change.** A defined benefit scheme has no market value; a Cash Equivalent
Transfer Value is a quotation the user may never have obtained, and capitalising an
income at some multiple would be inventing a figure. Three options, all product
calls:

1. Add a `transfer_value` column and ask for it — honest, and usually empty.
2. Capitalise `accrued_annual_pension` at a stated multiple — needs a disclosed
   basis and would move every affected user's headline net worth.
3. Exclude defined benefit schemes from net worth and say so on screen — the
   current behaviour, except that nothing says so.

**The application currently does (3) while its code reads as (1).** That is the
part that must not survive whichever is chosen.

### Related, separately filed

`LifeStageService::hasPensionValueAbove()` reads the same phantom column through
the **query builder**, which throws instead of returning zero — **W-0242**.

## Acceptance

1. A decision from CSJ on which of the three the product does.
2. No reader of `transfer_value` remains unless the column exists.
3. If defined benefit schemes stay out of net worth, the surfaces say so where the
   figure is shown.
4. Web, `/m` and native named individually.
