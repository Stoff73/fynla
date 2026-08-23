---
id: W-0373
title: Liability institution names and balances are written to the application log at INFO on every Inheritance Tax calculation
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: medium
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

From the tax-compliance review of W-0333. **Security/data-exposure, not a tax defect.**

`EstateAssetAggregatorService:246`:

```php
\Log::info('Liability: '.($liability->institution ?? 'Unknown').' | Type: '...' | User Share: £'.$userShare);
```

Institution name and balance to the application log for **every liability**,
unredacted, with **no user context**, at INFO.

`calculateUserLiabilities()` is called from `calculate()` (`:163`) **and** from
`generateHashes()` (`:1583`) via `getCachedCalculation()` (`:107`), so a single
Inheritance Tax request writes each liability **several times**.

It reads as debugging left in. Raised as coverage because W-0331/W-0333/W-0336 all lean
harder on this class than before.

## Acceptance

1. Removed, or behind a debug flag with the financial values dropped.
2. Route through `security-reviewer` — this is customer financial data in a log file.
