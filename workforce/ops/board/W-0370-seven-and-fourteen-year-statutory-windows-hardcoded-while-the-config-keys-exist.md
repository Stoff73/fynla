---
id: W-0370
title: The 7- and 14-year statutory gift windows are hardcoded while TaxConfigService already carries them
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
prior_art_outcome: route
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333. **Rule 2 breach.**

`IHTCalculationService:1751`, `:1757`, `:1765-1766`:

```php
->where('gift_date', '>', today()->subYears(7))
->where('gift_date', '>', today()->subYears(14))->where('gift_date', '<=', today()->subYears(7))
```

These are IHTA 1984 s3A(4) / s7(1) periods — **tax rules, not assumptions** — and they
are **already in `TaxConfigService`**:

- `inheritance_tax.potentially_exempt_transfers.cumulation_period = 7`
- `…potentially_exempt_transfers.years_to_exemption = 7`
- `inheritance_tax.fourteen_year_rule.maximum_window = 14`
- `…fourteen_year_rule.lookback_for_clts = 7`

`getPETRules()` and `getFourteenYearRule()` exist and are **unused by this method**.

## Acceptance

1. Both windows read from `TaxConfigService`.
2. A test that **changes the configured window and requires the answer to follow** —
   a test pinning `7` passes just as happily against the hardcoded literal.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.**

  **The accessor already existed and was already being read — by one line out of six.** `GiftingStrategyOptimizer::petWindowYears()` (`:44`) has read `getPETRules()['years_to_exemption']` since the class was written, and `:312` used it. Five other lines in the same file, plus one in `GiftingStrategy`, still said **7**.

  **That shape is worse than a fully hardcoded file**, and it is the same trap W-0432 recorded: a reader checking *"does this class read configuration?"* answers **yes**, sees the helper, and stops. The question that finds it is *"does EVERY window in this file read it?"*

  **`GiftingStrategyOptimizer:130` was the sharpest**, because it is a COMPARISON and not prose:

  ```php
  if ($remainingIHTLiability > 0 && $yearsUntilDeath >= 7) {
  ```

  Under a moved window this branch would have **decided on one number while the sentence it produces quoted another** — the strategy offered or withheld on 7 while the text beside it said something else. The four prose sites (`:294`, `:308`, `:315`, `:316`) and `GiftingStrategy:261` now read the same source.

  `GiftingStrategy` reads it locally rather than reusing `analyzePETs()`'s `$petWindow`, which is a different method — noted at the line so the next reader does not take it for a missed consolidation.

  **Tested:** 28 gifting tests pass, 71 assertions. Pint clean.

  Same family as **W-0526**, closed earlier today, which gave the fourteen-year search bound one configured home and removed the duplicate block that carried `maximum_window: 14`.
