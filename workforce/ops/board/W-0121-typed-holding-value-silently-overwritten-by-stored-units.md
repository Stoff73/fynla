---
id: W-0121
title: A typed holding value is silently overwritten by the unit count already on record
mission: M-0002-persona-fidelity
owner: build-lead
status: gated
claimed: 2026-08-21T18:05:00Z
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
branch: workforce/branches/fixes/F-0010-batch-j-consolidation-red.md
severity: high
surfaces: [web, m, ios]
source: found by fix-batch-J in the consolidation suite run, 2026-08-21 — InvestmentModuleTest > Holdings Management > it can update a holding
prior_art_checked: 2026-08-21
prior_art_found: [app/Support/HoldingValuation.php (W-0039), app/Http/Controllers/Api/InvestmentController.php:722+793, resources/js/components/Investment/HoldingForm.vue:398, tests/Unit/Support/HoldingValuationTest.php]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

`PUT /api/investment/holdings/{id}` accepted `current_price: 450` and
`current_value: 45000`, validated them, returned **200**, and stored **£8,980.07**.

`HoldingValuation::reconcile()` resolved `quantity` against the **stored holding**
before choosing a branch, so the inherited unit count (the factory's `19.955704`)
satisfied the units-win branch and revalued the row: 19.955704 × 450 = 8,980.07. The
caller never mentioned a unit count; it was inherited, and it beat a figure the user
had just typed.

This is the same class of defect as **W-0026** (`policy_end_date` accepted and
discarded). In a financial application, a silently discarded figure the user watched
succeed is worse than the derivation bug W-0039 replaced: the user has no signal that
the number they entered is not the number held.

**W-0039's direction is not in question and is not reversed.** Units remain the fact
and the value remains derived. What was missing was the distinction between a unit
count **supplied in this payload** and one **inherited from the existing record**.

## Acceptance

1. A payload that states a `current_value` and no usable `quantity` keeps that value
   exactly as entered; the unit count is back-calculated from it.
2. A payload that states a `quantity` still derives the value from units × price —
   including a price-only edit, which revalues the stored units at the new price.
3. A payload that states both keeps units authoritative. Deliberate, documented in the
   class, and pinned by a test rather than left to fall out of branch order.
4. With no usable price, units and value are each stored as given — nothing can relate
   them, so neither is invented.
5. The rule lives only in `app/Support/HoldingValuation.php`. **No branch of it
   anywhere** — not in a controller, a request, a component, **or an agent tool
   handler**. **W-0122 is a prerequisite for signing this off**, because
   `CoordinatingAgent` currently carries a second copy (Rule 20).

## Working notes

**Fixed 2026-08-21 by fix-batch-J.**

- `app/Support/HoldingValuation.php` — added `supplied()` (payload states a usable
  figure) alongside the existing `resolve()` (payload value, else stored). The typed
  value now takes the first branch. The docblock records the decision, including why
  units still win when one payload supplies both.
- `tests/Unit/Support/HoldingValuationTest.php` — three cases added: the regression
  itself (stored 19.955704 units must not eat a typed £45,000 — asserts the value
  stands, quantity back-calculates to 100.0, cost basis follows at £35,052.30), units
  winning over a value supplied in the SAME payload on an update, and a typed value
  left untouched when no price can relate it to the stored units. **No existing case
  was weakened or removed** — all nine still assert exactly what they asserted before.

**Evidence:** `tests/Unit/Support/HoldingValuationTest.php` 12 passed (20 assertions);
`tests/Feature/InvestmentModuleTest.php` 22 passed (86 assertions) — the failing case
`Holdings Management → it can update a holding` now returns `current_value` 45000;
investment families `tests/Feature/Api/InvestmentControllerTest.php`,
`tests/Feature/Stores/InvestmentAccountHttpIntegrationTest.php`,
`tests/Feature/Stores/InvestmentReadCluster5bTest.php`,
`tests/Feature/PortfolioOptimizationTest.php`, `tests/Unit/Services/Investment`,
`tests/Unit/Support` — 345 passed (1068 assertions).

**Not browser-verified by the fixing agent** — by policy a fix agent does not close
Rule 14's loop on its own work. The holding edit path needs a tester pass: type a
value with no units and confirm the figure that comes back is the figure typed.

**Adjacent, not fixed here:** Fyn's own holding-creation path carries a second copy of
this relationship — see **W-0122**.

- 2026-08-21 team-lead: **Acceptance criterion 5 reworded — it could have produced a
  wrong sign-off.** Flagged by the Archivist's delta sweep, and it was a real trap: the
  criterion **enumerated** "a controller, a request, or a component" while **citing**
  Rule 20, which enumerates nothing and admits no exception. `CoordinatingAgent` is an
  agent — none of the three listed — so **as worded the criterion was already met, while
  as cited it was not**, with W-0122 sitting on the board as the proof. `quality-lead`
  would have reached opposite conclusions depending on which half of the sentence it
  weighed.

  Resolved in the direction of the rule, not the list: no branch anywhere, agent tool
  handlers explicitly included, and **W-0122 is now a prerequisite for signing this off**.
  The alternative — dropping the Rule 20 citation and letting the criterion mean only
  what it lists — would have let the shared class ship while a second copy survived
  unrecorded, which is the disease rather than the cure.

  **General lesson, worth more than this item:** an acceptance criterion that both
  enumerates and cites a rule will be read as whichever half suits the reader. Cite the
  rule or list the places, never both.

- 2026-08-21 fix-batch-J: **The W-0122 prerequisite is met.**
  `CoordinatingAgent::handleCreateHolding` now reads `HoldingValuation::reconcile()`
  rather than computing its own valuation, so criterion 5 holds against the rule as the
  team-lead reworded it — no branch anywhere, agent tool handlers included. W-0122 is at
  `handoff` with its own evidence.

  **Criterion 5 is still not fully satisfied, and quality-lead should know before
  signing.** Enumerating every holding write site turned up **five more** consumers that
  do not read the shared class, one of them a line-for-line copy of its cost-basis
  branch (`DCPensionHoldingsController.php:96-98`). Raised as **W-0126**. Whether that
  blocks this item is quality-lead's call: the Fyn path was the one named as the
  prerequisite and it is closed, but "no branch anywhere" is literally not yet true.
