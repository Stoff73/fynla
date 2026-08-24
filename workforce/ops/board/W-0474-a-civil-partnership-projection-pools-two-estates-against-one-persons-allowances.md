---
id: W-0474
title: A civil partnership's projected estate pools both partners' assets against one person's allowances, because one predicate omits the status nine siblings include
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-24T07:40:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0154, W-0465]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer round four, finding F1, 2026-08-24
---

## Intent

`IHTCalculationService.php:125`:

```php
$isMarried = in_array($user->marital_status, ['married']) && $spouse !== null;
```

`civil_partnership` is a live enum value, accepted by `UpdatePersonalInfoRequest:63`
and captured by Fyn onboarding. **The migration that added it states in its own
docblock that `IHTCalculationService` branches on `['married','civil_partnership']`.
It does not.** Nine siblings do — `EstatePlanService:687`, `IntestacyCalculator:27`,
`WillTypePolicy:37`, `ProfileCompletenessChecker:29`, `UserContextBuilder:443`,
`TaxStrategyCalculator:163`. This service is the outlier.

**The damage is asymmetric, because the two columns use different predicates:**

| | predicate |
|---|---|
| Current assets, liabilities, allowances | `$isMarried && $dataSharingEnabled` |
| Projected assets, relief, liabilities, properties, investments | `$dataSharingEnabled && $spouse` |

`$dataSharingEnabled` is `$hasLinkedSpouse && hasAcceptedSpousePermission()`, and
that method applies **no marital-status test**. So for a civil partnership
`$dataSharingEnabled` is true, `$spouse` is not null, and `$isMarried` is false.

The projected column therefore takes **both partners' assets, liabilities,
properties, investments, cash, business value and business relief** and assesses
them against **one person's £325,000 + £175,000**. The taper base is struck on the
same doubled estate, so it crosses £2,000,000 roughly twice as fast and strips the
residence band.

**Direction: OVERSTATES projected tax.** This is the W-0154 F3 shape the engine was
already fixed for once, still live on one marital status.

**Statute:** IHTA 1984 s18 (spouse exemption, extended to civil partners by Civil
Partnership Act 2004 s.246 and SI 2005/3229), s8A, s8G.

## Acceptance

1. `['married', 'civil_partnership']` at `:125`, and `$isMarried &&` added to the five
   projection predicates so both columns pool on the same rule.
2. Before/after for a civil-partnership household with data sharing on, showing the
   projected allowances doubling and the taper base halving.
3. A test that a civil partnership and a marriage with identical holdings produce
   identical figures — the cheapest guard against a tenth consumer drifting.
4. `tax-compliance-reviewer` on the change: it moves tax.

## Working notes

- 2026-08-24 — Pre-existing; the predicate predates W-0465. Raised now because
  `8f09eaddc` **rewrote those exact lines** and newly routed the business relief and
  the projected taper base through them, so it is load-bearing on two tax-moving
  figures that were not there before. Filed separately rather than folded in, because
  it moves tax on its own and needs its own before/after.
