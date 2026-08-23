---
id: W-0463
title: TaxConfigService is the source or it is nothing — 20 configured rules have zero consumers, and every guard built to catch this is structurally incapable of seeing it
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer, compliance-lead, quality-lead]
status: handoff
claimed_by: null
severity: critical
surfaces: [web, m, ios]
created: 2026-08-23T12:55:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: quality-lead
prior_art_checked: 2026-08-23
prior_art_found: [W-0091, W-0362, W-0431, W-0432, W-0451, W-0461, W-0154, RateLiteralsComeFromConfigurationTest]
prior_art_outcome: extend
constitution_refs: [07-quality-bar, 05-perimeter]
source: CSJ, 2026-08-23, on being shown the Business Property Relief defect — "the taxconfig should be the source, nothing writes any values that come from the tax config, all of the estate and tax services must call, use, display and implement the tax config as specced"
---

## Intent

**CSJ's instruction, verbatim and as the acceptance standard:**

> the taxconfig should be the source, nothing writes any values that come from the
> tax config, all of the estate and tax services must call, use, display and
> implement the tax config as specced

**And the question this item exists to stop being asked again:** *"why this is not
being done is beyond me. it has been specced, planned, agreed and I got confirmation
that it was all implemented many times."*

That confirmation was not dishonest. It was **true of what was measured and false of
what was asked**, and the gap between those two is the whole of this item.

## What is actually true, measured 2026-08-23

**Twenty of `TaxConfigService`'s public accessors have zero callers anywhere in
`app/`.** Not "used indirectly" — zero, and for the substantive ones the underlying
config KEY has zero hits too, so they are not being read through a parent getter
either.

Traced in full:

| Rule | Configured | Implemented |
|---|---|---|
| **Business Property Relief** | `allowance_cap: 2500000`, `cap_in_effect: true`, `relief_above_cap: 0.5`, `aim_shares_outside_cap: true`, `cap_transferable_to_spouse: true`, effective `2026-04-06` | **A boolean.** `EstateAssetAggregatorService:116-127` emits `is_iht_exempt` true/false. 100% or nothing. (W-0091) |
| **Agricultural Property Relief** | same capped structure, `cap_shared_with_bpr: true` | **Nothing at all.** No caller in the estate path. Agricultural property gets no relief. (W-0091) |
| **Taper relief** | a full graduated band table — `tax_rate` 0.32/0.24/0.16/0.08/0 by years survived | **A boolean.** `GiftingStrategy:71` → `'taper_relief_applicable' => $yearsAgo >= 3`, with 3 and 7 hardcoded; `GiftingStrategyOptimizer:268` hardcodes `taper_relief_from_year => 3` again. **The percentages are applied to nothing.** The only other `'taper_relief'` reference in `app/` is a help string at `TaxSettingsController:332` reading "see active config for exact percentages" — which nothing does. |
| **Normal Expenditure Out of Income** | full rule set | **A label.** Appears only as `'strategy_name' => 'Normal Expenditure Out of Income'` in two gifting services. Never computed. |
| **14-year rule** | configured | Zero mentions in `app/Services/Estate/` or `app/Services/Tax/`. |
| **Quick succession relief** | configured | Zero mentions. |
| **Chargeable lifetime transfers** | `getCLTRules()`, `getCLTLifetimeRate()` | `getCLTRules()` zero callers; the key has zero hits. |

Also zero-caller, not individually traced and **not to be assumed dead**:
`getPETRules`, `getGiftTaxRate`, `getEstateConfig`, `getInvestmentConfig`,
`getPropertyOwnership`, `getJointOwnershipType`, `hasSurvivorshipRights`,
`getLeaseholdReform`, `getLeaseholdValuationWarnings`, `getEarlyYearsFunding`,
`getTaxFreeChildcare`, `getAll`, `isInCurrentTaxYear`. Some are genuinely redundant
helpers. **Deciding which is part of this work, not a precondition for starting it.**

## Why every previous sweep was honestly green

This is the part that matters, and it is not "someone was careless".

`RateLiteralsComeFromConfigurationTest` — the guard W-0451 left behind, and a good
one — moves four rates to values nothing else in the codebase uses and asserts the
**service output changes**. Its own docblock records the lesson that produced it:
*"A guard that moves two of three inputs silently certifies the third."*

**It is structurally incapable of detecting this defect.** A move-the-value guard
proves a consumer reads the right value. It cannot prove a consumer exists:

- Move `business_relief.allowance_cap` from £2.5m to £2.9m → **nothing changes**,
  because nothing reads it. Green.
- Move every taper band → **nothing changes**, because the code emits a boolean.
  Green.
- Move `quick_succession_relief` → **nothing changes**, because there is no output
  to move. Green.

So "the Rule 2 sweep is complete" has been true, twice, of *literals in the files
that were read*. W-0461 already records the scope half of this ("both sweeps covered
`app/` and exactly one Vue file"). **This item is the other half: a rule with no
consumer emits no literal to find and no output to move, so it is invisible to
every mechanism built so far.** Absence of a wrong value was repeatedly reported as
presence of the right one.

**No guard in this codebase asserts that a configured rule is implemented at all.**
That is the thing to build.

## Acceptance

1. **A coverage guard, and it is the acceptance that matters.** Every rule in the
   active tax configuration either has a consumer that demonstrably reads it, or is
   listed in an explicit, dated, reviewed exclusions register with a reason. The
   guard fails on an unregistered orphan. Without this, item 2 is fixed once and
   rots exactly as the last two sweeps did.
2. **No tax value is hardcoded where the configuration holds one** — including as a
   decimal in arithmetic, a literal in a comparison, a threshold in a `const`, a
   boolean standing in for a graduated relief, and a value in a Vue template or a
   `/m` view (W-0461's scope, folded in here).
3. **Graduated reliefs are computed, not flagged.** Business Property Relief,
   Agricultural Property Relief and taper relief each return an amount from the
   configured bands. `is_iht_exempt` as a boolean is deleted, not adjusted — every
   consumer doing `->reject(fn ($a) => $a->is_iht_exempt)` changes with it.
4. **Effective dates decide.** `allowance_cap_effective_date` and the tax-year
   fields govern whether a rule applies, so the answer follows configuration rather
   than the calendar in someone's head.
5. **The shared cap is modelled once.** `cap_shared_with_bpr: true` — one £2.5m
   allowance across both reliefs. A fix applying it to Business Property Relief
   alone over-relieves any estate holding both.
6. The unimplemented rules — 14-year, quick succession, normal expenditure out of
   income, chargeable lifetime transfers, potentially exempt transfers — are each
   implemented **or** explicitly registered as out of scope with CSJ's agreement.
   Silently leaving them is what produced this item.
7. `tax-compliance-reviewer` gate on the whole batch. `compliance-lead` on anything
   changing a figure a user has already been shown.
8. **Before/after figures on a real household for every rule changed.** Note the
   persona holds **no business interests**, and the largest on the dev database is
   £750,000 — below the cap — so the Business Property Relief path cannot be
   exercised by the personas and needs a purpose-built fixture.

## Sequencing note

**Taper relief is live for ordinary users; the business cap is latent.** No account
on the dev database holds a business above £2.5m, so nothing is currently wrong from
W-0091 on this data. Gifts are common. If this is split, taper relief goes first.

## Working notes

(append-only)

- 2026-08-23 — Raised at CSJ's direction while answering a question about W-0091.
  The 20 zero-caller accessors were measured, not estimated; Business/Agricultural
  Property Relief and taper relief were traced to their consumers line by line, the
  remainder established by absence of any mention of the config key across
  `app/Services/Estate/` and `app/Services/Tax/`. **That last method is strong but
  not exhaustive** — a rule implemented under a different name would not show up,
  so item 6 says "implemented or registered", not "implement all of these".


## Progress — 2026-08-23

**Acceptance 1, the coverage guard, is done — and it is the part that matters.**

`tests/Feature/Tax/ConfiguredRulesHaveConsumersTest.php`. Every second-level rule group
under `inheritance_tax` in the ACTIVE configuration must be referenced in `app/`, by its
config key or by the `TaxConfigService` accessor that reads it, or be named in an
exclusions register carrying a reason, a board item and a date. A second test keeps the
register honest: an exclusion for a rule that no longer exists, or one missing its item
or date, fails.

**It found the orphans independently**, before being told what to look for:
`business_relief`, `agricultural_relief`, `potentially_exempt_transfers` — the same
three found by hand.

**Mutation-checked.** Removing the `getBusinessRelief()` call turns it red. It is not
green by construction.

**One thing it taught me, worth keeping.** The first version derived accessor names from
key names — `potentially_exempt_transfers` → `getPotentiallyExemptTransfers(` — and
reported a false orphan, because the accessor is `getPETRules()`. It now parses
`TaxConfigService` for `$this->get('<path>')` and maps method to path. A naming
heuristic cannot survive the abbreviations a real codebase uses, and a guard that cries
wolf gets switched off.

### Fixed

- **Business Property Relief is now a capped, graduated relief** (W-0091).
  `EstateAssetAggregatorService::applyBusinessPropertyRelief()` allocates ONE shared cap
  across the estate — 100% on the first £2,500,000, `relief_above_cap` (50%) above,
  largest holdings first because that is the allocation that relieves most. Gated on
  `allowance_cap_effective_date`, so a date before 6 April 2026 still gets the old
  uncapped 100%. Relief is published as `business_relief_deduction` rather than netted
  into the asset value, so the estate still reconciles on screen. `min_ownership_years`
  now comes from configuration rather than a literal `2`.
  Eight tests in `tests/Feature/Estate/BusinessPropertyReliefCapTest.php`, including the
  board's £6m worked example (£4.25m relieved, £1.75m chargeable), one cap shared across
  two businesses, and two Rule 2 tests that move the cap and the effective date.
  **The fixture is purpose-built because no persona can reach it** — the largest business
  interest on the dev database is £750,000, which is why a persona run could never have
  found this.
- **The seven- and fourteen-year gift windows read from configuration.**
  `subYears(7)` ×3 and `subYears(14)` are gone; the windows come from
  `years_to_exemption`, `lookback_period` and `cumulation_period`, with the fourteen-year
  reach expressed as a sum of two configured periods so they cannot drift apart.

### Registered, not fixed — each with its real reason

- **Agricultural Property Relief: NOT IMPLEMENTABLE AS THE SCHEMA STANDS.** There is no
  agricultural asset type or flag anywhere in the data model, so there is nothing to
  relieve. Needs a product decision before code. The cap is configured as shared
  (`cap_shared_with_bpr`), so when agricultural property becomes expressible it must join
  the existing allocation, **not** get a second cap.
- **AIM shares at 50% outside the cap** — `business_interests` has no column identifying
  AIM holdings, so they cannot be told apart from any other qualifying business.
- Quick succession relief, the fourteen-year rule, chargeable lifetime transfers and the
  April 2027 pension inclusion — no implementation, each recorded with its reason.

### NOT done

- **Taper relief on failed potentially exempt transfers is still not applied.** The
  graduated table is now formally "consumed" because the PET rule group is read for its
  window, which is a real weakness of a reference-based guard and is stated in the test's
  own docblock. Applying the percentages requires modelling tax at gift level — a failed
  PET uses the donor's nil rate band first and taper bites only above it — which is a
  larger change needing the tax gate and a product decision. **Filed separately rather
  than half-built.**
- **Scope is `inheritance_tax` only.** `income_tax`, `capital_gains_tax`, `pension` and
  the rest are not guarded. Extending means auditing each area's consumers and writing a
  register somebody has actually reviewed; doing it speculatively would produce exactly
  the unreviewed list this item exists to prevent.
- `tax-compliance-reviewer` has **not** run on any of this.
