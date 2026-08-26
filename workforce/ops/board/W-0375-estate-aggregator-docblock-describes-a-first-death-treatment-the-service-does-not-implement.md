---
id: W-0375
title: The estate aggregator's docblock describes a first-death survivorship treatment the service does not implement anywhere
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: 2026-08-26
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333. **Documentation, but with a large blast
radius if acted on.**

`EstateAssetAggregatorService:34-35`:

```php
 * - Joint tenancy: Passes to survivor (may exclude from first death estate)
 * - Tenants in common: User's share included in estate
```

That describes a **first-death** treatment **the service does not implement**. No
consumer branches on survivorship, and `TaxConfigService::hasSurvivorshipRights()` is
never consulted on this path. The service produces **only** second-death figures — both
of `calculate()`'s columns are "both gone" estates.

**The risk is a future maintainer "fixing" the projection to match the comment** by
excluding joint-tenancy property. That would be **wrong** for a second-death model and
would **delete roughly half the household estate.**

The comment's second line is fine and is what W-0333 relied on.

## Acceptance

1. The docblock states what the service computes: a second-death estate, both members
   pooled, each record once at each member's share.
2. If a first-death figure is ever wanted, it is a **new** figure with its own name —
   not a reinterpretation of this one.

---

## Fixed 2026-08-26

The three claims were checked before the docblock was rewritten, because a comment
asserting something untrue is the whole defect and replacing it with another
unverified assertion would repeat it:

- **No survivorship branching in the service.** `grep` for `survivor`,
  `joint_tenancy` and `joint_ownership_type` in `EstateAssetAggregatorService`
  matches the docblock line and nothing else.
- **`hasSurvivorshipRights()` is never consulted.** It exists at
  `TaxConfigService:733` and has zero callers anywhere.
- **Both columns are second-death estates**, corroborated by
  `IHTProjectionOwnershipTest`'s own docblock describing "the estate projected to
  the second death".

The docblock now states what the service computes, why survivorship is correctly
absent rather than missing, and what the wrong fix would cost — roughly half the
household estate, understating Inheritance Tax by the same.

**It also names the trap, which turned out to be better stocked than the item knew.**
`hasSurvivorshipRights()`, `allowsWillOverride()` and `getPropertyOwnership()` all
exist, all read live configuration (`joint_tenancy.survivorship = true`), and all
have zero callers. A maintainer acting on the old sentence would have found
ready-made wiring and concluded it was simply unconnected. Raised separately as
**W-0498** — classing that cluster is its own decision and must not be settled by
someone reaching for it from the estate path.

Acceptance 2 is stated in the docblock: a first-death figure, if ever wanted, is a
NEW figure with its own name.
