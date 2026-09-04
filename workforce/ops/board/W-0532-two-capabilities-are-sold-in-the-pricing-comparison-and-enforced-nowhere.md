---
id: W-0532
title: family_module and benefits_child are listed in the pricing comparison and gated by nothing — sold to customers, enforced nowhere
mission: board-verification-31-august
owner: null
reviewers: [compliance-lead, quality-lead]
status: queued
severity: medium
surfaces: [web, m]
created: 2026-09-04
source: found while working W-0499, 2026-09-01
prior_art_checked: 2026-09-04
prior_art_found: [W-0499]
prior_art_outcome: extends — W-0499 was the same shape for investments_exotic and was fixed by defining and gating it in the Store
constitution_refs: [06-commercials, 07-quality-bar]
---

## Intent

W-0499's premise, verbatim: **a capability that appears in the customer-facing
pricing comparison and is checked by no code is a promise the application cannot
keep.** `investments_exotic` was that shape and was closed by defining it and
gating it in the Store. Two more remain.

## Evidence, verified 2026-09-04

`app/Services/Payment/TierComparisonService.php:28-29` — both appear in
`FEATURES`, so both are rendered on the pricing comparison the customer reads:

```php
'family_module'  => ['label' => 'Family module'],
'benefits_child' => ['label' => 'Child benefit modelling'],
```

Both are seeded `'full'` on every tier
(`database/seeders/TierConfigurationSeeder.php:49,92`). Outside those two files,
every remaining reference is a **test** —
`ApprovedTwoTierMatrixTest:47-48`, `TierConfigurationSeederTest:24`, and
`EveryCapabilityHasAConsumerTest:33,39-40`, which already names them as the
sharper two and carries them on its allowlist so the suite stays green. There is
**no consumer in `app/` or `resources/`**: no `can()`, no Store gate, no
frontend check.

Because both are `'full'` on every tier today, nothing is currently withheld from
anyone — the defect is that the enforcement point does not exist, so the day the
matrix changes, the comparison keeps promising and the app keeps giving.

## The judgement this needs before code

Unlike `investments_exotic`, it is not obvious **what** these two gate:

- **`family_module`** — plausibly `/settings/family`, family members, spouse
  linking. Spouse linking is load-bearing for the estate module on the free tier,
  so gating the whole area is not safe without a decision on where the line falls.
- **`benefits_child`** — no child benefit modelling exists in the codebase at all.
  This may be a capability for an unbuilt feature, in which case it should be
  **removed from the comparison** rather than gated.

Both branches are CSJ's, not the implementer's.

## Acceptance

1. A decision, recorded here, on each key: **gate it** (naming the surface) or
   **remove it from `FEATURES`** because the feature does not exist.
2. Whichever branch, the key stops being an allowlist entry in
   `EveryCapabilityHasAConsumerTest` — either it has a consumer, or it is gone.
3. If gated: the gate is in **one** place, the Store or the capability map, and
   `/m` reads the same one (Rules 19, 20).
4. `compliance-lead` on the branch that changes what the pricing page claims.
