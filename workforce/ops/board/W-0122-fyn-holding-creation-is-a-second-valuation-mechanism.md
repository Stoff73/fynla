---
id: W-0122
title: Fyn's holding creation carries a second copy of the units/price/value rule and never writes a unit count
mission: M-0002-persona-fidelity
owner: build-lead
status: gated
claimed: 2026-08-21T19:25:00Z
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
branch: workforce/branches/fixes/F-0010-batch-j-consolidation-red.md
severity: high
surfaces: [web, m, ios]
source: found by fix-batch-J while fixing W-0121, 2026-08-21 — explicitly out of that scope
prior_art_checked: 2026-08-21
prior_art_found: [app/Support/HoldingValuation.php, app/Agents/CoordinatingAgent.php:3171-3207, app/Http/Controllers/Api/InvestmentController.php:722+793]
prior_art_outcome: extend
constitution_refs: [00-precedence, 07-quality-bar]
---

## Intent

W-0039 made `app/Support/HoldingValuation.php` the one home for the relationship
between a holding's units, price and value, and routed both controller paths through
it. **Fyn's `create_holding` tool does not read it.**

`CoordinatingAgent::handleCreateHolding` computes its own valuation inline
(`CoordinatingAgent.php:3171-3174`):

- `current_value` is derived from an allocation percentage against the account's value,
  or set to `0.0` when no allocation is given.
- `quantity` is never set and never derived, even when the payload carries a
  `current_price` that would back-calculate it.

So a holding created by Fyn stores no unit count at all, and a holding created through
the API for the same security stores one. Two mechanisms for one relationship is the
condition Rule 20 exists to prevent, and the class docblock's claim that "Fyn has no
holding-entry surface" was simply wrong — it has one, it just does not use the shared
rule. That claim has been corrected in the docblock and now points here.

## Why it matters beyond tidiness

Units are the fact W-0039 established. A Fyn-created holding starts life without one,
so it cannot be reconciled against a price, cannot be revalued by a price-only edit,
and shows a unit count of nothing on a form that now has a units input. A household
that entered half its portfolio by talking to Fyn and half through the form ends up
with two shapes of the same record.

## Acceptance

1. `handleCreateHolding` builds its payload and passes it through
   `HoldingValuation::reconcile()` before `Holding::create()`. The inline
   allocation-derived valuation stays as the source of `current_value` where no units
   or price are given — it is not a competing rule, it is an input to the shared one.
2. A Fyn-created holding with a `current_price` and a value carries a back-calculated
   `quantity`, exactly as the API path does.
3. No change to the `create_holding` tool schema or its descriptions. Tool schema
   descriptions govern LLM parameter defaults and are golden-mastered — changing one
   is its own item with its own re-recording.
4. Regression cover for the Fyn path asserting the stored row, not the tool result.

## Working notes

**Fixed 2026-08-21 by fix-batch-J**, on the team-lead's instruction after the
consolidation red was cleared, and to the amended acceptance: the handler **reads**
`HoldingValuation`, it does not compute the same answer.

- `app/Agents/CoordinatingAgent.php` — `handleCreateHolding` no longer computes a
  valuation inline. The allocation percentage now sets `current_value` only when an
  allocation was actually given, the payload passes through
  `HoldingValuation::reconcile()`, and the NOT NULL column filler
  (`$payload['current_value'] ??= 0.0`) sits **after** the reconciliation so it stays a
  storage constraint rather than a valuation rule.
- The unconditional `current_value => 0.0` the handler used to seed the payload with
  had to go first: `reconcile()` reads a stated `0.0` as a stated valuation and would
  have back-calculated a unit count of zero — asserting "this holding has no units",
  which is a fabricated fact rather than an absent one. Where nothing values the
  holding, `quantity` is now left NULL and the column takes zero.
- `tests/Feature/AI/DirectWrite/CreateHoldingTest.php` — two cases added, both
  asserting the **stored row**, because the tool result looks identical either way:
  50% of a £20,000 account at £100 a unit stores £10,000, **100 units** and £8,000 cost
  basis; and a holding with no allocation and no price stores a NULL unit count rather
  than a fabricated zero. The five pre-existing cases are untouched and still pass.

**Acceptance criterion 3 verified, not assumed:** the tool schema is unchanged.
`create_holding` has no `quantity` parameter
(`fyn-memory/procedural/tool_schema/savings/create_holding.xai.md`), so Fyn still
cannot state units and this change only back-calculates them — giving Fyn a way to
state units at all means changing a golden-mastered schema and is its own work.
Both golden masters pass byte-identical: 14 passed (27 assertions).

**Evidence:** `tests/Feature/AI/DirectWrite/CreateHoldingTest.php` 7 passed
(24 assertions). Wider regression across `tests/Feature/AI/DirectWrite`,
`DirectWriteCoverageTest`, `DirectWriteObserverFireTest`,
`tests/Feature/InvestmentModuleTest.php`, `tests/Feature/Api/InvestmentControllerTest.php`
and `tests/Unit/Support` — **222 passed (797 assertions)**. Pint clean.

**Not browser-verified by the fixing agent** — a fix agent does not close Rule 14's
loop on its own work.

**This was not the only second copy.** Enumerating every holding write site to check
the claim turned up **five more** that still do not read the shared class, including a
line-for-line duplicate of its cost-basis branch in
`DCPensionHoldingsController.php:96-98`. Raised as **W-0126**.

- 2026-08-21 team-lead: **Reclassified — `severity` medium → high, `constitution_refs`
  extended to include `00-precedence`.** Raised by the Archivist during the consistency
  sweep and it is right.

  **A second copy of one rule inside Fyn is not a quality-bar matter, it is CLAUDE.md
  Rule 20** — the GOLDEN RULE, which `00-precedence.md` §1 ranks above everything else in
  the workforce constitution. That changes what "fixed" means for this item: under the
  quality bar, making Fyn's copy agree with `HoldingValuation` would be a pass. **Under
  Rule 20 it is not** — *"if more than one mechanism implements the behaviour,
  consolidating them into ONE source that all consumers read is PART of the fix"*, and
  editing copies in lockstep is explicitly named as a violation rather than a fix. So the
  acceptance is that `CoordinatingAgent::create_holding` **reads `HoldingValuation`**, not
  that it computes the same answer.

  Severity follows: W-0121 was rated on a user's typed value being silently discarded on
  the web path, and this is the same discard on the Fyn path, on the surface with the
  fewest ways for a user to notice. It is not a lesser instance of W-0121 — **it is the
  reason W-0121's fix does not reach every write path**, which is the more serious half.

  Note this is the third same-shape pair on the board today: W-0048/W-0082 (folded),
  W-0102/W-0103 (folding), and W-0121/W-0122. **This pair is NOT folded** — `fix-batch-J`
  judged W-0122 explicitly out of W-0121's scope and declined to slip a Fyn tool change
  into a red-suite repair at the final gate. That was the right call and the separation
  stands.
