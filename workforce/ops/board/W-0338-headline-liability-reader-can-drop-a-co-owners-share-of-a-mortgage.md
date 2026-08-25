---
id: W-0338
title: The headline estate's liability reader can drop a co-owner's share of a mortgage the row does not name, inflating the estate and the tax
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-23T00:15:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0228, W-0333, W-0336, CrossModuleAssetAggregator::getMortgages]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found by the **tax-compliance review** of W-0333/W-0336, not by a tester, and **the
persona cannot exercise it** — all three of its mortgage rows name both spouses.

There is an asymmetry between how a mortgage is **reached** and how its share is
**computed**:

- **Reach** — `MortgageStore::forUser()` → `Mortgage::forUserOrJoint()`, scoped by the
  **mortgage row's** own `user_id` / `joint_owner_id`.
- **Share** — `calculateUserMortgageShare()` → `propertyOwnershipFor()`, resolved from
  the **securing property** (CSJ's W-0228 ruling: a debt is shared exactly as the
  asset securing it is shared).

When those disagree, debt vanishes. A home owned 50/50 by David and Sarah with a
mortgage row naming **only David**: David's share resolves from the property at 50%;
Sarah never sees the mortgage, because the row does not name her. **The household
deducts 50% of the debt and the other 50% is deducted by nobody.** A missing
liability inflates the net estate, so this **overstates** Inheritance Tax.

`EstateAssetAggregatorService::calculateUserLiabilities():245` — the reader behind the
**headline** estate — uses the one-leg version. The projection was routed to the
two-leg `CrossModuleAssetAggregator::getMortgages()` under W-0336, whose second leg
picks up mortgages on the user's properties that the mortgage row does not name.
**So the two halves of the same response now disagree again, in the opposite
direction to the one this cycle has been closing.**

The two-leg reader already exists and documents this exact case in its own docblock
(*"one spouse's mortgage on a jointly-owned home"*). This is a routing job, not a new
mechanism.

## Acceptance

1. `calculateUserLiabilities()` reaches a mortgage secured on the user's property
   whether or not the mortgage row names them.
2. The headline's liability base equals the projection's, to the penny.
3. A fixture with a jointly-owned property whose mortgage row names ONE spouse —
   this persona produces no such shape, so the test must build it.
4. No debt is deducted twice as a result: the two legs must not both count one row.

## Why this item exists at all — the argument for the tax gate, in one sentence

**A mortgage is reached by its own row's ownership but its share resolves from the
securing property, and when those disagree half the debt is deducted by nobody and the
estate comes out too big — and the persona cannot exercise it, because all three of its
mortgage rows name both spouses, so only a review would have found it.**

The sharper half: the OLD 100%-of-the-row read **accidentally recovered the whole debt**
for that shape. So applying the share without widening the reach would have been **a
regression introduced by the fix itself** — in a tax figure, in the direction that
overstates liability. W-0336 caught it before landing only because the tax-compliance
review was run on the change rather than on the finished code.

Six service-level measurements, fifteen feature tests and a full browser pass on both
accounts would every one of them have stayed green.
