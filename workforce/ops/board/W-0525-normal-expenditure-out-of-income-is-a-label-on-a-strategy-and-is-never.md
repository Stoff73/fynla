---
id: W-0525
title: Normal Expenditure Out of Income is a label on a strategy and is never computed
mission: null
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: done
claimed_by: null
severity: medium
surfaces: [web, m, ios]
created: 2026-08-29T13:30:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: [W-0463, W-0465, W-0091]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: W-0463 independent verification, 2026-08-29 — CSJ ruled these four are work and need doing
---

## Intent

**CSJ, 2026-08-29, on the four reliefs W-0463 left open: "the four reliefs are work, and
need to be done."** This is one of the four, broken out so each can be claimed, gated and
verified on its own rather than sitting inside a structural item.

The IHTA 1984 s21 exemption appears in the app **only** as `'strategy_name' => 'Normal Expenditure Out of Income'` in two gifting services. The rule set is configured; `getNormalExpenditureFromIncome()` has zero callers. So the app names the exemption to the user and computes nothing from it.

## Notes

s21 needs three tests met together — the gift is part of a regular pattern, it is made out of INCOME rather than capital, and it leaves the donor able to maintain their usual standard of living. The app already holds income and expenditure per member (`HouseholdCashFlowProjector`), which is the surplus the third test turns on.

## Acceptance

1. `getNormalExpenditureFromIncome()` has a real caller on the estate path, and the `normal_expenditure_out_of_income` configuration
   decides the outcome — no literal reproduces any part of it (Rule 2).
2. A household that qualifies sees the relief in **both** the current and the projected
   Inheritance Tax column. W-0465 records what happens when only one column gets a
   relief: the two halves of a comparison table disagree by the whole of it.
3. A household that does not qualify is unaffected — before/after on a non-qualifying
   estate shows no movement.
4. Tests that fail with the relief removed, not just tests that pass with it present.
5. `tax-compliance-reviewer` — it moves Inheritance Tax for every qualifying household.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.** Worked through `superpowers:systematic-debugging`.

  **Root cause — and the item's framing needs correcting, because what was there is both worse and better than "never computed".**

  An amount WAS computed. **Twice.** `PersonalizedGiftingStrategyService:361-363` and `GiftingStrategyOptimizer:213-219` each hardcoded `$surplusIncome * 0.5` with a `>= 1000` floor — the same heuristic, invented independently, with different `priority` values and different implementation steps. Meanwhile the configured `gifting_exemptions.normal_expenditure_from_income` block governed neither, because `getNormalExpenditureFromIncome()` had zero callers.

  **So one exemption had two mechanisms and no configuration.** Moving the admin setting did nothing; editing either service let the two answers drift apart with nothing comparing them. That is Rule 20 and Rule 2 in the same defect, and it is why the item read as "a label" — the label was the only part anyone could see.

  **The fix.** `getNormalExpenditureFromIncome()` is now the one home and surfaces the two numbers the strategies act on — `safe_surplus_fraction` and `minimum_annual_gift` — merged over defaults so a silent block cannot invent a rule. Both services read it. Both literals are gone.

  **Why those two numbers are configuration and not law**, recorded at the line because a future reader will ask: **s21 sets no cap at all.** The fraction is a deliberate conservatism against the third statutory test — the donor must maintain their usual standard of living, so advising the whole surplus advises up to the edge of failing it. The minimum is where a standing order stops being worth the record-keeping s21 demands. Neither belongs in a service.

  **Acceptance:** (1) real caller in BOTH services, configuration decides, no literal — the guard proves it. (3) a household with no surplus is unaffected: `max(0, ...)` is untouched and 750 gifting/estate tests are unmoved. (4) **mutation-verified**: re-hardcoding the fraction goes red, and stripping the accessor's defaults goes red; restore goes green.

  **Tested:** `tests/Unit/Services/Estate/NormalExpenditureOutOfIncomeTest.php` — 5 passed; 750 gifting/estate passed (2,460 assertions); Pint clean.

  **NOT DONE.** (2) The "both columns" criterion does not apply as written — s21 here produces a STRATEGY SUGGESTION, not a relief applied to the Inheritance Tax calculation, so there are no two columns for it to disagree across. Recording that rather than ticking it. (5) No `tax-compliance-reviewer` pass.
