---
id: W-0522
title: The trust strategy still hardcodes the taper relief band table, the last copy in the estate services
mission: null
branch: fix/w-0522-hardcoded-taper-band-table
owner: null
reviewers: [tax-compliance-reviewer]
status: in_progress
claimed_by: null
severity: medium
surfaces: [web, m, ios]
created: 2026-08-29T13:00:00Z
claimed: 2026-08-29T13:00:00Z
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: [W-0463, W-0091, W-0465]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found verifying W-0463 during the 2026-08-29 gated-item pass
---

## Intent

`PersonalizedTrustStrategyService::calculateMultiCycleDeathCharge()` computed a death
charge as `amount × standard_rate × taperRate ÷ 100`, where `taperRate` came from a
**private method holding the band table as literals**:

```php
private function getTaperReliefRate(int $years): int
{
    if ($years < 3) { return 100; }
    if ($years < 4) { return 80; }
    if ($years < 5) { return 60; }
    if ($years < 6) { return 40; }
    if ($years < 7) { return 20; }
    return 0;
}
```

`inheritance_tax.taper_relief` carries that same graduated schedule, configured. This is
the exact shape **W-0463** exists to remove — *"the taxconfig should be the source, nothing
writes any values that come from the tax config"* — and grep says it was **the last copy of
the taper ladder left in `app/Services/Estate/`**. `GiftingStrategy` and
`FailedGiftTaxCalculator` were both moved onto `getGiftTaxRate()` under W-0463; this one
was missed because it is a *trust* strategy rather than a *gifting* one.

## Resolution — 2026-08-29

The ladder is deleted and the charge reads `getGiftTaxRate($yearsFromTransfer, 'pet')`,
which returns the **effective** rate with the death rate already applied — so there is
nothing left to multiply, and the `< 7` / `>= 3` branches go with the table because the
accessor answers both ends itself.

**Behaviour-preserving, verified band for band** against the live configuration:

| years survived | old ladder | `getGiftTaxRate(y, 'pet')` |
|---|---|---|
| 0 | 0.4000 | 0.4000 |
| 3 | 0.3200 | 0.3200 |
| 4 | 0.2400 | 0.2400 |
| 5 | 0.1600 | 0.1600 |
| 6 | 0.0800 | 0.0800 |
| 7 | 0.0000 | 0.0000 |

**Verification.** 723 passed across `tests/Unit/Services/Estate`, `tests/Feature/Estate`
and `tests/Architecture`.

## Open question for CSJ — NOT decided here

**A transfer into a trust is a chargeable lifetime transfer, and this code rates it as a
potentially exempt transfer.** `getGiftTaxRate()` takes a type, and `'clt'` reads a
different schedule — `tax_percent` of a `chargeable_lifetime_transfers.death_rate` — where
`'pet'` reads `tax_rate` off the standard rate.

`'pet'` is used here **because it reproduces the existing behaviour exactly**, and changing
the type would move a published figure. Whether it *should* be `'clt'` is a tax question,
not a refactoring one, so it is asked rather than answered (Rule 16). If the answer is
`'clt'`, that is a one-word change and a new before/after.
