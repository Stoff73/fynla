# W-0271 — build-lead → quality-lead

## Done

`AutoRiskCalculator::calculateEmergencyCashFactor()` now derives nothing itself. Cash
from `CrossModuleAssetAggregator::calculateCashTotal()`, expenditure from
`ResolvesExpenditure`, the division from `EmergencyFundCalculator::calculateRunway()`
— the three homes `SavingsAgent` already reads.

Measured through the real services: David (16) **79.8 months** (£99,750 ÷ £1,250),
Sarah (17) **25.3** (£31,030 ÷ £1,225). Both were **0 months** before.

`resources/js/views/Risk/RiskFactorDetailPage.vue`: the "Source: Savings accounts
marked as emergency fund" line and the "emergency fund" numerator label were corrected
— the fix made both statements false.

6 new feature tests (`tests/Feature/Risk/RiskFactorsReachTheHouseholdTest.php`),
including one that drives the real `SavingsAgent` and asserts the two runways are
**identical**, and one at a 75/25 joint split.

## Not done, and why

**Nothing in this item.** Browser verification is complete on both accounts and both
surfaces — figures and screenshots in F-0024 §7.

**W-0274** (a third answer to the same figure, in `SavingsActionDefinitionService`) and
**W-0276** (runway counts illiquid cash) were raised, not fixed — both outside this
item's scope.

## What you need that isn't obvious from the artefacts

1. **Check the three existing unit tests' fixtures, not just their result.** They were
   flipped from `is_emergency_fund => true` to `false`. Flagged, the old rule and the
   new rule return the **same number**, so all three passed identically before and
   after the fix and proved nothing. Unflagged, they read 0 months under the old rule.
   If a later change flips them back, the suite goes blind again silently.
2. **The denominator was routed too, and I overstated why — corrected here.** I first
   said the two sources "disagreed". Measured, they agree per user today (David
   £1,250 column and £1,250 profile; Sarah £1,225 column, no profile row), so routing
   it moved no number on this persona. The £1,250/£1,225 gap is **between the
   spouses** — that is **D-26**, not this. The routing is still correct structurally:
   the chain prefers an `expenditure_profiles` row over the column, those two can
   diverge, and did historically.
   **What this means for your verification:** Sarah's 25.3 months is computed from a
   denominator D-26 will change to £1,250. Expect **≈24.8 months** afterwards, moving
   on `/risk-profile`, the dashboard and `/m` together. That is D-26 landing, not a
   regression in this work — if the three surfaces move together, the property this
   item guarantees is intact.
3. **"Not calculated" is a deliberate value**, not a null leaking to the UI. It appears
   only when no expenditure is recorded anywhere in the chain.
4. **`/savings` → Emergency Fund still reads 0.0 months / £0** — a fourth, client-side
   implementation, raised as **W-0274** at HIGH and deliberately not fixed here. If you
   verify this item by opening the savings page you will see the old number; the
   surfaces this item covers are `/risk-profile`, `/dashboard` and `/m`.

## Assumptions I made

- **That the savings module's definition is the one to converge on**, because it is
  what the dashboard, `/net-worth/cash` and `/m` all show. I did not re-derive it from
  first principles; its known weakness is W-0276.
- **That `is_emergency_fund` should survive as a designation.** Deleting the column
  was never considered — five other consumers use it for "has the user nominated an
  account", which is a real question.
- **That level `medium` is right for the unknown-expenditure state.** It follows this
  class's own precedent for an unknown age. It is a judgement, not a measurement.

## Surfaces covered / not covered

- **Web** — code complete, service-level measured, **browser-verified on both accounts** (Sarah through the MFA gate, code taken from the database).
- **`/m`** — no code change needed or made; `/m` has no risk screen (W-0279) and its
  savings runway reads the shared endpoint. **Browser-verified on the current
  bundle** (`main-DTjymbsC.js`, retaken after a mid-batch rebuild): Sarah's `/m`
  dashboard reads 25.3 / 6 months, £31,030 — the same figures as her web risk page.
  Identity confirmed from `fynla-state.auth.user` (id 17), not from the figures.
- **iOS** — same backend; no native risk screen exists. **Not verified** — say so.
