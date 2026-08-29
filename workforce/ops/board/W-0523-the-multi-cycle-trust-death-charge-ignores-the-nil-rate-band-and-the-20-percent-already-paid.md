---
id: W-0523
title: The multi-cycle trust death charge taxes the gross transfer, ignoring both the nil rate band and the 20% already paid
mission: null
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: high
surfaces: [web, m, ios]
created: 2026-08-29T13:25:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: [W-0522, W-0463]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found fixing W-0522, confirmed against CSJ's 2026-08-29 ruling on chargeable lifetime transfers
---

## Intent

`PersonalizedTrustStrategyService` answers "what does this trust transfer cost on death?"
in **two places, two different ways**, and only one of them is right.

**`buildImmediateCLTStrategy()` — correct.** CSJ, 2026-08-29: *"anything over 325k has an
immediate 20% iht charge."* That is what it does:

```php
$excessOverNRB   = max(0.0, $amountToTrust - $availableNRB);
$lifetimeCharge  = $excessOverNRB * $cltLifetimeRate;            // 20%, from config
$potentialDeathCharge = ($excessOverNRB * $ihtRate) - $lifetimeCharge;   // 40% less the 20% paid
```

Band applied, lifetime charge credited.

**`calculateMultiCycleDeathCharge()` — wrong, in both halves:**

```php
$totalCharge += $cycle['amount'] * $this->taxConfig->getGiftTaxRate($yearsFromTransfer, 'clt');
```

1. **No nil rate band.** It charges the **gross** `amount`, where the sibling charges only
   the excess over the available band. The multi-cycle strategy exists precisely to make
   transfers that sit *within* the band each seven-year cycle — so the figure it publishes
   taxes money that would carry no charge at all.
2. **No credit for the 20% already paid.** Where a cycle's transfer *does* exceed the band,
   the lifetime charge was paid on the way in and s7(4) charges only the balance. This
   charges the full tapered death rate on top.

Both overstate. They compound, and the figure is shown to the user as the cost of a
strategy the app is recommending.

## Acceptance

1. One mechanism answers the question for both paths (Rule 20). The single-transfer path is
   the one that is right, so the multi-cycle path reads it rather than a third opinion
   being written.
2. The band is applied per cycle, and the lifetime charge already paid is credited.
3. **The decision this needs and does not have: how the nil rate band cumulates across
   seven-year cycles.** IHTA 1984 s7(1) cumulates chargeable transfers in the seven years
   before each transfer, so a cycle's available band depends on the cycles before it. That
   is a modelling decision, not a refactor — it is why this was filed rather than fixed
   inside W-0522.
4. Before/after on the published multi-cycle death charge for a worked household. It moves
   DOWN; state by how much.
5. `tax-compliance-reviewer` — it moves a figure the user is shown when choosing a strategy.
