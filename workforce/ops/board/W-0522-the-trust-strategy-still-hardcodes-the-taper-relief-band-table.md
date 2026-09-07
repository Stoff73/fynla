---
id: W-0522
title: The trust strategy still hardcodes the taper relief band table, the last copy in the estate services
mission: null
branch: fix/w-0522-hardcoded-taper-band-table
owner: null
reviewers: [tax-compliance-reviewer]
status: done
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

- 2026-08-30 build-lead: **merged to `dev` as PR #750** (taper ladder read from
  configuration, transfer typed as a chargeable lifetime transfer). Stamp corrected from
  `in_progress`, which it had kept for a day after the code landed. `gated`, not `done`:
  the `tax-compliance-reviewer` gate on it has not run.

- 2026-08-31 build-lead: **VERIFIED ALREADY FIXED AND TESTED — closed.**

  **No taper band table survives in `PersonalizedTrustStrategyService`.** Both remaining occurrences of the word are comments: the class docblock at `:23` and the working note at `:393`. The last copy is gone and the schedule is read from `TaxConfigService:438` — `inheritance_tax.chargeable_lifetime_transfers.taper_relief` — with `GiftingStrategy` and `GiftingStrategyOptimizer` reading the same home.

  **The note at `:388-400` is worth keeping, because the taper duplication was the least of what was wrong there.** The seven-year death charge in that service had three faults at once:

  1. It charged the **GROSS** amount with no nil rate band, while `buildImmediateCLTStrategy()` four hundred lines above charged only the excess. **Two paths, one question, two answers.**
  2. No credit for the 20% already paid on the way in.
  3. It was worked at **projected life expectancy**. With death twenty years out, every cycle had aged past seven years, the tapered rate came back nil, and the user was shown a **£0 risk — for a transfer they are being told to make today, that they could fail by dying tomorrow.**

  **CSJ's ruling, 2026-08-29: the excess, and death NOW.** The band a transfer consumes is not charged here — it is charged in the ESTATE, whose nil rate band is withheld for seven years by `FailedGiftTaxCalculator`. Charging it in both places would bill one band twice.

  **Tested:** 114 trust-strategy and taper tests pass, 349 assertions.
