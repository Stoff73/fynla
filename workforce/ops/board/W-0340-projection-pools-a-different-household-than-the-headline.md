---
id: W-0340
title: The projection pools a different household than the headline, so an unmarried linked couple gets two estates taxed against one person's allowances
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T00:15:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0154, W-0331, W-0333]
prior_art_outcome: extend
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

Raised by the tax-compliance review of W-0333. **W-0154's F3 defect, still alive in
the projection path.**

W-0331/W-0333 made the headline and the projection agree about **whose share** of
each record they take. They still disagree about **which people** are in the
household:

| path | predicate |
|---|---|
| headline `pooledMembers()` | `$isMarried && $spouse !== null && $dataSharingEnabled` |
| every projection branch | `$dataSharingEnabled && $spouse` |

And the caller does not gate on marriage either — `IHTController:52`:

```php
$hasLinkedSpouse    = $user->liveSpouseId() !== null;
$dataSharingEnabled = $hasLinkedSpouse && $user->hasAcceptedSpousePermission();
```

**Neither `liveSpouse()` nor `hasAcceptedSpousePermission()` consults
`marital_status`.** So an unmarried cohabiting couple with linked accounts and
accepted sharing gets `$isMarried = false`, `$dataSharingEnabled = true`, `$spouse`
non-null — a **headline taxing one estate** beside a **projection pooling two**.

On tax grounds this is the worst combination available. Unmarried partners get **no
IHTA 1984 s18 spouse exemption and no s8A transferable nil rate band**, so pooling
their estates while applying single-person allowances materially **overstates** the
projected liability — and the two columns of one response describe two different
households.

## Acceptance

1. One predicate decides who is in the household, read by both halves.
2. Whichever way it is settled, an unmarried linked couple gets a coherent pair of
   columns — not one estate beside two.
3. Before/after stated for an unmarried linked household; the peak_earners persona is
   married and **cannot** exercise this.

## Working notes

**Team-lead escalated this to CSJ, 2026-08-23: do NOT fix.** What the product should
model for an unmarried linked couple is a product question, not a consolidation — and
the two available answers are not equivalent. Not fixed inside F-0026 for that reason:
it moves a real tax figure for a class of user. **The `IHTCalculationService` class docblock
names this as open**, so the docblock cannot be read as claiming the gap closed —
which it would otherwise have done.


## Note — 2026-08-30, build-lead: the block was stale, and most of this was already fixed

**Unblocked, and no CSJ decision is needed.** The item was escalated on 2026-08-23 as a
product question — what the app should model for an unmarried linked couple — and it read
that way because the two paths disagreed. They no longer do.

**Checked against the code rather than the item.** Every calculating branch now asks
`HouseholdPooling::poolsSpouse()`, which requires married or civil-partnership status:
`EstateProjectionService:276,314,419,460` and `IHTCalculationService:186,281,779,804,895,2140`.
The `$dataSharingEnabled && $spouse` predicate the item quotes is gone from all of them,
closed by W-0474 and the W-0519 split, and **the item was never updated** — so it sat
`blocked` on a decision that had stopped being needed.

**There was no product question to answer.** Cohabitants get no spouse exemption (IHTA
1984 s18), no transferable nil rate band (s8A) and no transferable residence nil rate band
(s8G). An estate that pools them is wrong in law, not a modelling preference, so the two
answers the escalation described were never equivalent.

**What WAS still loose, and is fixed here:** `IHTCalculationService::generateHashes()` —
the **cache key** — kept the old predicate after every calculating branch moved off it. For
a linked unmarried couple the key varied with the partner's assets while the cached figure
did not depend on them. Latent (zero such households exist on the development database),
wrong, and cheap.

**Reported honestly, because I got it wrong first:** I read
`ComprehensiveEstatePlanService:81` as the same defect on another surface and said so. It
is not. `$spouse` is resolved twelve lines above already gated on `hasSpousalStatus()`, so
it is null for an unmarried couple and the flag is false. The predicate there is now
explicit anyway, described as belt-and-braces rather than as a fix.

**Locked** by `tests/Feature/Estate/UnmarriedLinkedCoupleIsNotPooledTest.php` — unmarried
£300,000 against married £420,000, two distinct figures so the test can tell the behaviours
apart, plus civil partnership pooling identically (W-0474). 565 passed across Feature
Estate and Unit Estate.

- 2026-08-31 build-lead: **CLOSED — merged as PR #758, verified against `dev`.**
  `IHTCalculationService::generateHashes()` now builds both the asset and the liability
  hash through `poolsSpouse()`, so the cache key pools exactly as the calculation pools;
  `ComprehensiveEstatePlanService` gathers spouse assets through
  `HouseholdPooling::poolsSpouse()`. Pinned by
  `tests/Feature/Estate/UnmarriedLinkedCoupleIsNotPooledTest.php`.
