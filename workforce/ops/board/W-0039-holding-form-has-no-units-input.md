---
id: W-0039
title: The holding form has no quantity/units input — every holding's unit count is unenterable
mission: M-0002-persona-fidelity
owner: build-lead
status: done
claimed: 2026-08-21T12:15:00Z
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
severity: high
surfaces: [web, m, ios]
source: found by fix-batch-A while fixing W-0009, 2026-08-21; explicitly out of that scope
prior_art_checked: 2026-08-21
prior_art_outcome: extend
---

## Intent

W-0009 fixed the store action that silently discarded holding edits, so ticker, ISIN,
sub_type, prices, OCF and dividend yield now persist. **Units still cannot be entered
at all** — the Details form exposes no quantity/units field.

## Why it blocks the persona

`tests/Persona/peak_earners.md` carries **ten holdings, every one with a unit count**:
351 Fundsmith, 2,500 Scottish Mortgage, 318 Vanguard FTSE All-World, 333 Vanguard
LifeStrategy 80, 625 iShares Core MSCI World, 1,316 Vanguard UK Govt Bond, 84 iShares
Physical Gold, 4,211 Vanguard Global Equity, 800 BlackRock Corporate Bond, 50,000 L&G
UK Property. None of them has a home in the UI.

Units are not decorative: value, allocation percentage and any per-unit reconciliation
derive from units × price. Without them a holding's value is a typed-in number that
cannot be checked against anything.

## Acceptance

1. A units/quantity input exists on the holding form — add and edit paths both.
2. It reaches web, `/m` and Fyn's holding catalogue (Rule 20 — one mechanism).
3. The persona's ten holdings can be entered with their unit counts and read back
   correctly, with value and allocation consistent with units × price.
4. Decide and record whether value is derived from units × price or entered
   independently. If both are enterable they can disagree, and something must own the
   contradiction.

## Working notes

Blocks a faithful Pass A. Sequence after W-0009 (already fixed by fix-batch-A), since
the edit path had to work before units could be saved through it.

- 2026-08-21 build-lead: **FIXED. The decision (acceptance 4) is: units are the fact, value is derived.**

  **The decision, and why.** A holding is N units of a security at price P; its
  value is N x P and nothing else. So `current_value` and `quantity` can never
  disagree, because only one is authoritative at a time and **the server owns
  which**. There are no two writable fields that can silently diverge.

  This is an **inversion of the existing rule, not a second field.**
  `InvestmentController::storeHolding:718-726` and `::updateHolding:796-802` each
  carried their own copy of `quantity = current_value / current_price`. The chain
  ran allocation % -> value -> quantity, which put the user's actual fact at the
  END of a derivation and is precisely why units were unenterable. Both copies
  are deleted; both paths now call **`App\Support\HoldingValuation::reconcile()`**
  — one home, `:722` and `:793`.

  The rule, in precedence order:
  1. units + current price known -> `current_value = quantity x current_price`
  2. no units, but value + price known -> `quantity = current_value / current_price`
     (**the legacy direction, kept as a fallback**)
  3. units + purchase price known -> `cost_basis = quantity x purchase_price`

  Rule 2 means this is a strict superset of today's behaviour: **nothing that
  works now stops working**, units simply win when supplied. Only keys actually
  derived are written back, so a partial update stays partial — editing just the
  price revalues against the stored units instead of dropping them.

  **Rule 20 — where the one mechanism lives.** The derivation is **server-side**,
  so all three surfaces share it. `/m` has no holding-entry screen and does not
  display quantity (checked `CanonicalPortfolio.vue`,
  `InvestmentAccountDetail.vue`); Fyn has **no holding write tool at all** — it
  creates investment *accounts* (`handleCreateInvestmentAccount`), and reads
  holdings via `AdvicePromptBuilder` / `FynContextAssembler`. Both are read-only
  consumers and inherit the corrected values with no work. The units input is
  therefore web-only **by architecture, not by omission**.
  `HoldingForm.calculatedHoldingValue` mirrors the same precedence as a preview
  and is labelled as such — the server recomputes on save, exactly as
  `ownership.js` mirrors `CalculatesOwnershipShare`.

  **The pattern DOES repeat in this form — and I fixed the second instance too.**
  Asked to check rather than fix only the named field: `dividend_yield` is the
  same disease as Target Retirement Income. It is **validated by both holding
  requests**, stored, cast and consumed — and `grep -rn "dividend_yield"
  resources/js --include="*.vue"` returns **nothing**. No input has ever existed
  anywhere in the SPA. Since the validation rule was already there, adding the
  input was a one-line extension of the same fix rather than separate work; it is
  in the same row as Units. `cost_basis` is the third model-held field with no
  input, but that one is **correct** — it is derived, and should not be typed.

  **Files:** `app/Support/HoldingValuation.php` (NEW) ·
  `app/Http/Controllers/Api/InvestmentController.php:722,793` ·
  `app/Http/Requests/Investment/{Store,Update}HoldingRequest.php:43` (quantity
  rule — it was validated by neither) ·
  `resources/js/components/Investment/HoldingForm.vue:152-186` (Units + Dividend
  Yield inputs), `:405-418` (preview + help text), form state and `resetForm()`.

  **Tests — 26 passing:**
  - `tests/Unit/Support/HoldingValuationTest.php` — 9 cases covering both
    directions, precedence, partial updates, explicit nulls, divide-by-zero, and
    units kept when no price exists to value them.
  - `tests/Feature/Stores/InvestmentAccountHttpIntegrationTest.php` — 14 passing,
    two new: 333 LifeStrategy 80 at £255 stores `quantity 333`,
    `current_value 84915.00`, `cost_basis 74925.00`, `dividend_yield 2.10`; and
    editing only the price on 351 Fundsmith revalues £2,604.42 -> £2,808.00 with
    units untouched.
  - `tests/frontend/components/holdingFormUnits.test.js` — 3 cases: the fields
    exist in form state, units override the allocation fallback in the preview,
    and the fallback still applies without units.

  **NOT verified by me, deliberately:** no browser run — per the standing
  instruction that a fix agent does not close its own Rule 14 loop. Acceptance 3
  (the persona's ten holdings entered and read back) is the tester's to close;
  the HTTP path is proven with two of those ten holdings at their real figures.

  **Raised, not fixed:** the `InlineHoldingsEditor` in `AccountForm` still creates
  holdings with allocation only, so units are entered via the Details form. If
  the tester finds that flow awkward for ten holdings, that is a UX item, not a
  data one.

- 2026-08-31 build-lead: **CLOSED — verified against `dev`.** `App\Support\HoldingValuation::reconcile()`
  (app/Support/HoldingValuation.php:109) is the single home; `InvestmentController` calls it on six
  sites, and neither `storeHolding` nor `updateHolding` carries its own
  `quantity = current_value / current_price` copy any more. Units are the fact, value derived, per
  the recorded decision on acceptance 4.
