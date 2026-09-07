---
id: W-0324
title: holdings.*.dividend_yield has no rule in any nested holdings array, so a yield entered through the account or pension form is silently discarded
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T22:50:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0262, W-0026, W-0261]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

**A W-0262-class defect on the presence axis, found while working the range
axis** — which is the point worth keeping: a sweep for one kind of
rule-versus-schema disagreement says nothing about the others.

`holdings.dividend_yield` is validated in the standalone requests
(`Investment\StoreHoldingRequest`, `UpdateHoldingRequest`) but is **absent from
every nested `holdings.*` rule set**:

| Request | `ocf_percent` | `dividend_yield` |
|---|---|---|
| `StoreInvestmentAccountRequest` | yes, `max:100` | **missing** |
| `UpdateInvestmentAccountRequest` | yes, `max:100` | **missing** |
| `Retirement\StoreDCPensionRequest` | yes, `max:100` | **missing** |

`validated()` passes exactly the keys with rules and drops the rest, so a dividend
yield supplied against a holding created through the account form or the pension
form never reaches the database. The save reports success.

`InvestmentController` confirms the shape at the write: it reads
`$holdingData['ocf_percent'] ?? 0` and never mentions `dividend_yield` at all.

**Whether the inline editors should offer the field is the open question.**
`InlineHoldingsEditor` does not currently bind it — the field is reachable only
through the "Details" link, which opens `HoldingForm` and posts to the standalone
endpoint where the rule exists. So today the gap may be unreachable rather than
live. It becomes live the moment anything adds a yield input to an inline editor,
or a client (Fyn capture, native) sends one in a nested array — and there is
nothing to stop either.

## Acceptance

1. Decide whether nested holdings accept `dividend_yield`.
2. If yes: add the rule to all three requests **and** the write in both
   controllers, with the same `max:100` the standalone path now carries after
   W-0263 widened the column to `decimal(7,4)`. Four paths write this column;
   they must agree by construction (Rule 20).
3. If no: say so at the rule set, so the next reader does not take the absence
   for an oversight — which is exactly how W-0262 happened.

## Working notes

- 2026-08-22 build-lead: `tests/Unit/Database/ValidationMaxFitsColumnPrecisionTest.php`
  already pins every path that DOES bound this column, so adding the rule will not
  be able to drift from the others unnoticed.

- 2026-08-22 build-lead, F-0025: **a second, related parity gap belongs with this
  item.** Fyn's capture path does not use the form requests at all — it writes
  through the Stores (`CoordinatingAgent` → `InvestmentAccountStore::create`,
  `MortgageStore`). `MortgageStore:306` bounds `interest_rate` at `max:100` but
  says nothing about `fixed_interest_rate` or `variable_interest_rate`, and
  `InvestmentAccountStore` sets no bound on `platform_fee_percent`.

  Before F-0025 widened the columns those were 500s. They are now stored values,
  which is the right direction — but it means **Fyn accepts a 12% platform fee
  where the form returns a 422.** A difference in bound, not a crash, and worth
  settling alongside the `dividend_yield` question rather than separately.

  This also matters for Rule 19 in a way "shared backend" obscures:
  `resources/mobile/api.js` has **no post, put or patch helper anywhere**, so Fyn
  is not merely one of `/m`'s write paths — it is the only one.

- 2026-08-23 build-lead (`fix-cycle4-retirement`): **A fourth path belongs in the
  table, and this item's "may be unreachable rather than live" hedge nearly stopped
  being true today.**

  `DCPensionHoldingsController::store()` and `::update()` are **standalone** endpoints,
  not nested rule sets, so they fall outside the three requests tabulated above — and
  they have **no `dividend_yield` rule either**. A yield sent to them is dropped by
  `validated()` exactly as it is on the nested paths.

  That matters now because **W-0441 wired `HoldingForm` to those endpoints**, and
  `HoldingForm` is precisely the form this item names as "the Details link, which
  opens `HoldingForm` and posts to the standalone endpoint where the rule exists".
  For a pension owner it now posts to an endpoint where the rule does **not** exist.

  **The input is hidden in pension context rather than offered.** This item says in
  terms that the gap *"becomes live the moment anything adds a yield input"*, and
  deciding whether nested and pension holdings should accept the column is
  acceptance 1 here, not something to settle inside a form fix. So the field is not
  rendered when `HoldingForm` has a pension owner
  (`resources/js/components/Investment/HoldingForm.vue`, the `owner` prop), and the
  key is deleted from the payload.

  **Whoever settles acceptance 1 should settle it for four paths, not three** — the
  two pension holdings methods belong in the table with the other three.

  **Separately, and already done:** `sub_type` was the same disease on the same two
  methods — validated by neither, REQUIRED by the form for a Fund, and silently
  dropped. Fixed under W-0441 rather than left, because W-0441 made it reachable.
  The vocabulary moved to `app/Constants/HoldingSubTypes.php` so the fix did not
  become a third copy. It is **accepted, not `required_if`**: written as `required_if`
  it turned an existing green 201 into a 422, because other callers legitimately omit
  it — the "column wider than the rule" direction in `app/Http/CLAUDE.md`.

---

## Closed 2026-09-01 — both halves, because the rule alone would not have fixed it

**The rule was only half the defect.** Adding `holdings.*.dividend_yield` to the three
nested sets would have left the value validated and **still discarded**:
`InvestmentController` read `$holdingData['ocf_percent']` and never mentioned this
column at either write site. A fix that stopped at the request class would have looked
right, passed a 201-shaped test, and changed nothing in the database.

| Half | Where |
|---|---|
| The rule | `StoreInvestmentAccountRequest:113-120`, `UpdateInvestmentAccountRequest:128-130`, `Retirement/StoreDCPensionRequest:109-111` |
| The write | `InvestmentController:436` (create) and `:587` (update) |

Range copied from the standalone `Investment\StoreHoldingRequest:55` rather than chosen
here, so the two paths cannot disagree about what the column accepts.

### On "the gap may be unreachable rather than live"

The item was right that `InlineHoldingsEditor` does not bind the field today, so no web
form reached it. That is **not** a reason to leave it: the nested arrays are the shape
Fyn's capture tools and any API client send, and nothing stopped either. A validated
value silently dropped is the W-0011 shape — the save reports success and the user has
no way to learn their figure was lost.

### Tests

`tests/Feature/Investment/NestedHoldingDividendYieldTest.php` — 3:

- the yield **reaches the database** through the nested array (asserted with
  `assertDatabaseHas`, not on a 201 — a status-only test passes on the broken code);
- an out-of-range yield is refused;
- **all three** request classes carry the rule, so fixing the one that was tested and
  leaving the other two is caught. That third test is the guard: the defect is the
  *absence* of a rule, which no behavioural test of a single endpoint can see.

**Regression:** 335 tests across the investment features and services.

**Rule 19:** the write is server-side on the shared path, so web, `/m` and Fyn all reach
it. No frontend currently sends the field in a nested array — that remains the open
product question the item raised, and it is now safe either way.
