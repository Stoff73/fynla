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

## Decided — CSJ, 2026-08-29

> *"transfer to a trust is a clt, it is not a pet, anything over 325k has an immediate 20%
> iht charge."*

Changed to `'clt'`. **It moves no figure today** — `chargeable_lifetime_transfers` carries
no `death_rate` of its own, so `getGiftTaxRate()` falls back to the standard rate and both
schedules return 0.4000 / 0.3200 / 0.2400 / 0.1600 / 0.0800 / 0.0000 identically. It is
still the correct type, and the day that key is configured the `'pet'` reading would have
been silently wrong.

The immediate 20% charge itself **is** modelled, and from configuration:
`buildImmediateCLTStrategy()` reads `getCLTLifetimeRate()` (0.20) and the grossed-up
settlor rate, and charges it on `max(0, amountToTrust − availableNRB)`.

**But the multi-cycle path does not agree with it — raised as W-0523.**
