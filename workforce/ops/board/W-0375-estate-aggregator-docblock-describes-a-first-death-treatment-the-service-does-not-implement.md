---
id: W-0375
title: The estate aggregator's docblock describes a first-death survivorship treatment the service does not implement anywhere
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: null
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
