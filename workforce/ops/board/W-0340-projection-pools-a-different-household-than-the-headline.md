---
id: W-0340
title: The projection pools a different household than the headline, so an unmarried linked couple gets two estates taxed against one person's allowances
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: blocked
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T00:15:00Z
claimed: null
blocked_by: [csj-decision]
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
