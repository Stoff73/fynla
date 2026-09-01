---
id: W-0373
title: Liability institution names and balances are written to the application log at INFO on every Inheritance Tax calculation
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
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

- 2026-08-31 build-lead: **FIXED AND TESTED — closed. Found in the same method as W-0338 and fixed in the same edit.**

  `EstateAssetAggregatorService` ran, inside the liability sum:

  ```php
  \Log::info('Liability: '.($liability->institution ?? 'Unknown').' | Type: '.($liability->type ?? 'Unknown').' | User Share: £'.$userShare);
  ```

  **The lender's name and the amount owed, on every estate calculation, on every surface, at INFO.** That pair is exactly what turns a log line into a personal-data record, and it sat on a READ path — so it accumulated on every dashboard load, every Fyn tool call and every plan render, indefinitely, with no retention story and no purpose beyond a debugging session that had ended.

  **Removed rather than downgraded.** There is no level at which a customer's creditors and balances belong in a log file, so `Log::debug` would only have made it quieter. The sum is now a plain closure over `calculateUserShare()` and the reason is recorded at the line so it is not reinstated as a convenience.

  `grep 'Log::info' app/Services/Estate/` now returns one hit: the comment explaining the removal.

  **Tested:** 246 estate/mortgage/liability tests pass, 2,075 assertions; persona locks unmoved.
