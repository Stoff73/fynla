---
id: W-0322
title: Collapsing "Additional information" and pressing Update replaced every holding on an account with a single 100% Cash row — silent, destructive, and on an ordinary interaction
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: done
severity: high
surfaces: [web]
created: 2026-08-22T22:50:00Z
claimed: 2026-08-22T22:50:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0257, W-0009]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

**Found while fixing W-0257**, in the same method, and it is the more destructive
of the two. The frontend half is fixed here; **the backend half is the real
hazard and is NOT fixed** — see Acceptance.

### The defect

`InvestmentController::update` (`app/Http/Controllers/Api/InvestmentController.php:556`)
reads the holdings payload and syncs conditionally:

```php
$holdings = $validated['holdings'] ?? null;
...
if ($holdings !== null) {
    $account->holdings()->delete();
    foreach ($holdings as $holdingData) { /* ... */ }

    $totalAllocated = collect($holdings)->sum('allocation_percent');
    if ($totalAllocated < 100) {
        // auto-create a "Cash" holding for the remainder
    }
}
```

**An empty array is not null.** So a payload carrying `holdings: []`:

1. deletes every holding on the account,
2. writes nothing back,
3. computes `$totalAllocated = 0`, which is `< 100`,
4. and creates **one "Cash" holding at 100%**.

`AccountForm.submitForm` sent exactly that. When "Additional information" was
collapsed it set `submitData.holdings = []` — and `holdings` **is** in
`allowedFields`, so it reached the server. The section is **collapsed by
default**.

**The user's experience: open an account, change the provider or the value
without ever expanding Additional Information, press Update, get a success
response — and the portfolio is now a single Cash line.** No warning, no
confirmation, no error.

`RetirementController:469-470` has the identical shape for pension holdings, fed
by `DCPensionForm` doing the identical `payload.holdings = []`.

### Why the frontend half was fixed here rather than reported

It is one line in a file already in this batch's scope, and the semantics are not
in question: **"this form is not showing holdings" is a different statement from
"the user removed them all"**, and only the second should clear anything.
`AccountForm` and `DCPensionForm` now `delete` the key instead of setting `[]`, so
`$holdings === null` and the sync is skipped. Clearing holdings deliberately still
works exactly as a user would expect — expand the section, delete the rows, save,
which sends a real empty array from a visible control.

Pinned by `tests/frontend/components/Investment/AccountFormHoldingsPayload.test.js`,
which asserts on **key presence**, not on the value: `undefined` and `[]` are both
falsy wherever this gets read, so the two hypotheses only diverge on whether the
key is there at all. Verified to fail against the pre-fix code.

## Acceptance

- [x] Neither form sends `holdings: []` merely because the section is collapsed.
- [x] Deleting every row from the open section still clears holdings.
- [ ] **The backend decides what an empty holdings array means, explicitly.**
      Today "clear them all" and "say nothing about them" are indistinguishable
      at the controller, and the tie is broken by auto-substituting Cash. A
      client that legitimately posts `[]` still triggers the substitution. That is
      a contract question about every write path — form, Fyn capture, native —
      not something to settle inside a form fix.
- [ ] Whether "auto-create a 100% Cash holding" is right when a user has
      genuinely emptied their holdings, or whether that should leave the account
      with none.

## Working notes

- 2026-08-22 build-lead: DB evidence of the delete-and-recreate path is visible
  in `holdings` — account 26 rows 62/63/64 soft-deleted at 19:52:16 with 65/66/67
  created in the same second, same values. That save carried real holdings, so it
  round-tripped correctly; it is the same code path that destroys them when the
  array is empty.
- The `?? null` / `!== null` pair reads as deliberate — someone intended
  "omitted means leave alone". The bug is that the client never omitted it.

- 2026-08-23 build-lead (`fix-cycle4-columns`): **BROWSER VERIFIED — the frontend
  half holds.**

  Run on **account 13** rather than 14, deliberately: account 14's only holding is
  the auto-created Cash row, which the edit form filters out anyway, so it could
  not discriminate. Account 13 holds a real fund — `Vanguard LifeStrategy 80`,
  holding id **69**.

  Open Edit → collapse "Additional information" (`showAdditionalInfo: false`,
  editor unmounted) → change provider → Update.

  `PUT /api/investment/accounts/13` → **200**, captured request body:

  ```json
  {"account_type":"isa","provider":"Hargreaves Lansdown Vantage","platform":null,
   "current_value":"85000.00",...,"platform_fee_amount":null}
  ```

  **No `holdings` key.** Database after: holding **69 still live, `deleted_at`
  NULL, max holding id still 69** — nothing deleted, no Cash substituted.

  Acceptance 1 and 2 are now browser-verified. **Acceptance 3 and 4 — the
  controller's contract for an empty array — remain open.**

- 2026-08-31 build-lead: **VERIFIED PARTIAL against `dev` — the two frontend criteria are ticked
  and hold; the two open ones are a CONTRACT DECISION, not unstarted work.**
  **Confirmed still true at the controller:** `InvestmentController:568` runs the sync on
  `if ($holdings !== null)`, so "clear them all" (`[]`) and "say nothing about them" (absent) are
  still indistinguishable in effect from the client's side, and `:585-597` still auto-creates a
  100% `Cash` holding for whatever allocation is unaccounted for. A client that legitimately posts
  `[]` gets a full-value Cash row.
  **This is the question, and it is CSJ's:** should emptying an account's holdings leave it with
  none, or with a 100% Cash row? It governs every write path — form, Fyn capture, native — so it
  should not be settled inside a form fix. Recorded rather than decided.

- 2026-08-31 build-lead: **CLOSED — the two open criteria were already answered by a rule CSJ
  considers settled, and I turned it into a question that did not need asking.**

  CSJ, 2026-08-31: *"the account is empty? how can you have a cash row? there is no cash, no
  assets, nothing? the account shows 0, which as per the rule if there are no holdings the account
  will show cash, with a zero, I thought this was clear?"*

  **The rule: unallocated value is Cash.** The code implements it. `InvestmentController:585-597`
  computes `$totalAllocated` and creates a Cash holding for the remainder at
  `($account->current_value * $remainderPercent) / 100`. Empty the holdings and `$totalAllocated`
  is 0, so `$remainderPercent` is 100 and the Cash row carries the whole account value — which is
  zero when the account is zero. Criteria 3 and 4 are therefore satisfied, not open: "what does an
  empty holdings array mean" has an answer, and "is auto-creating Cash right" was decided.
  Criteria 1 and 2 were already ticked and still hold.

  **Adjacent, reported not fixed — the two write paths disagree about a Cash row the user entered
  themselves.** `InvestmentController:439` (create) guards with `&& ! $hasCashHolding`, so it will
  not add an automatic Cash row when the payload already contains one. `:587` (update) has **no
  such guard**. Post 70% equities and 20% Cash through update and the account ends with TWO Cash
  rows — the user's at 20% and an automatic one at 10%. Same rule, two implementations, one of
  them missing a condition. Not folded in here because it is a different defect from the one this
  item reported, and W-0322's own framing was about a destructive delete rather than a duplicate.
