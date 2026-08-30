---
id: W-0523
title: The multi-cycle trust death charge taxes the gross transfer, ignoring both the nil rate band and the 20% already paid
mission: null
branch: fix/w-0523-multi-cycle-clt-death-charge
owner: build-lead
reviewers: [tax-compliance-reviewer]
status: gated
claimed_by: null
severity: high
surfaces: [web]
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

## Working notes

(append-only)

- 2026-08-29 build-lead: **fixed on the excess basis, after two CSJ rulings that between
  them settle where the cost of a consumed nil rate band belongs.**

  **Ruling 1 — how the band cumulates across cycles (CSJ, 2026-08-29).** Rolling seven-year
  cumulation, IHTA 1984 s7(1): a cycle's band is the nil rate band less the chargeable
  transfers in the seven years ending with it, chosen over "a fresh band every cycle" and
  over "one band for the whole schedule". On the seven-year cadence every earlier cycle has
  just aged out, so each cycle gets a full band — which is what the old flat value happened
  to produce, but as an assumption rather than a calculation. A schedule spaced any tighter
  was priced as though the band replenished anyway; now it is not.

  **Ruling 2 — what the death charge reports (CSJ, 2026-08-29).** *"It would cost the
  excess, don't double count the nrb."* I had put the opposite to CSJ — charge the whole
  amount, because the band consumed by the transfer is a real cost to the death estate — and
  it is wrong, because **the estate already withholds that band**. `TrustObserver` writes a
  `clt` `Gift` against the settlor on trust creation
  (`app/Observers/TrustObserver.php:39-47`), `FailedGiftTaxCalculator` withholds the
  transferor's band for seven years, and charging it again in the strategy bills one band
  twice. **The rule CSJ asked for — a trust transfer uses the transferor's nil rate band and
  it is not available to the estate until the seven-year reset — already exists and is
  wired.** Verified: observer registered at `EventServiceProvider.php:133`, covered by
  `tests/Unit/Observers/TrustObserverTest.php`.

  **And the taper question, raised and settled.** I flagged that "until the taper and 7 year
  reset" bundles two mechanics, and asked whether the band itself tapers back. CSJ first
  ruled that it does, then reversed: *"You are right, it does not give back band relieves
  the tax. Keep it that way please."* So `FailedGiftTaxCalculator` is **unchanged** — the
  band is withheld in full for seven years and returns in full at seven, a cliff, while
  taper reduces only the rate on the failed transfer's excess. This is the statutory
  position and no code moved for it.

  **What actually changed**, all in `PersonalizedTrustStrategyService`:
  1. `buildCLTCycleSchedule()` computes each cycle's band by s7(1) cumulation, and reports
     `nrb_available`, `chargeable_amount` and `immediate_charge` per cycle instead of a flat
     band and a hardcoded nil charge.
  2. `calculateMultiCycleDeathCharge()` charges the **chargeable slice**, credits the 20%
     lifetime charge, and is floored at nil — s7 repays nothing.
  3. It is worked on a **death-now** basis from the calculation date, not from projected
     life expectancy. That was the third error and the one that hid the other two: with
     death twenty years out every cycle had aged past seven years, the tapered rate came
     back nil, and the user was shown a £0 risk on a transfer they are told to make today.
  4. `lifetime_tax_charge` is summed from the schedule rather than asserted as nil.

  `buildImmediateCLTStrategy()` is **unchanged** — it was already on the excess basis, and
  the two paths agreeing is the point of this item.

- 2026-08-29 build-lead: **verification.** 592 passed across `tests/Unit/Services/Estate`,
  `tests/Unit/Services/Trust`, `tests/Feature/Estate` and `tests/Unit/Observers`.
  **Mutation-verified** before the basis changed: all four altered expectations fail against
  the pre-fix service. Two tests added, two existing expectations corrected — both of them
  had encoded the double count (`potential_death_charge` asserted `> 0` for a transfer
  sitting entirely inside the band).

- 2026-08-29 build-lead: **Rule 19 — no `/m` or iOS counterpart exists.** Only
  `resources/js/components/Estate/TrustPlanningStrategy.vue` renders these figures; nothing
  in `resources/mobile/` or `ios-native/` consumes the trust strategy payload. `surfaces`
  narrowed to `[web]` on that evidence rather than left as an assumption.

- 2026-08-29 build-lead: **not fixed, reported.** `TrustObserver` handles `created` only.
  Editing a trust's `initial_value` afterwards does not move the CLT gift, and deleting a
  trust leaves the gift behind — so the withheld band can drift from the trust that caused
  it. Adjacent to this item rather than in it; needs its own board item and a decision on
  whether a user-edited gift should be overwritten.

- 2026-08-30 build-lead: **merged to `dev` as PR #751** — the excess basis and the lifetime credit on the trust death charge. Left `gated` rather than
  `done` because the reviewer gate named above has not run; `done` here would mean the
  change is on `dev`, which is true, and would hide that nobody has certified it.
