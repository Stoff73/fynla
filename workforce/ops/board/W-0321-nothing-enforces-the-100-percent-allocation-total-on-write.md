---
id: W-0321
title: Nothing enforces the 100% holdings allocation total on write, so any account can be pushed past 100% and into the state W-0257 could not escape
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T22:50:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0257, StoreHoldingRequest, InvestmentController]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

W-0257's acceptance offered a choice: *"Either the allocation total is enforced on
write, or the form copes with exceeding it — not neither."* **F-0025 delivered the
second.** The form now copes: it can be opened, corrected and saved, and a blocked
submit says which number to change and by how much.

**The first half is still open, and it is the half that stops the state arising.**

### The gap

`holdings.*.allocation_percent` is validated `max:100` **per holding**. Nothing
validates the **sum**. So `POST /api/investment/holdings` will happily add a
fourth holding to an account already at 100%, taking it to 105% — which is
precisely how W-0257 was discovered.

The paths that can create the state:

| Path | Total checked? |
|---|---|
| `POST /api/investment/holdings` (standalone add) | **No** — no visibility of the account's other holdings |
| `PUT /api/investment/accounts/{id}` (nested array) | **No** server-side; the form now blocks client-side |
| `POST /api/retirement/pensions` (nested array) | **No** server-side; the form now blocks client-side |
| Fyn capture | **No** — same requests, same gap |

**A client-side guard is not enforcement.** `/m` and native post to the same
endpoints and have no such guard, and Fyn's capture path has none either.

### Why this was not decided inside a form fix

Two genuine product questions, neither answerable by inference:

1. **Reject, or absorb?** A 422 on the standalone add path is defensible, but so
   is reducing the auto-created Cash holding to make room — which is what the
   controller already does implicitly in the other direction (`$totalAllocated <
   100` creates Cash for the remainder). One of those is the intended model.
2. **What about the Cash row itself?** It is auto-created and counts toward the
   total, so "the user's holdings" and "the stored allocation total" are not the
   same set. A naive `sum <= 100` rule would have to decide whether Cash is inside
   or outside the constraint.

Enforcing a cross-record total also needs a DB read inside validation, which is a
different shape from every other rule in `app/Http/Requests/`.

**Related and separate: W-0322**, where the controller's handling of an empty
holdings array is the mirror-image contract question.

## Acceptance

1. A decision, written down, on whether an over-100% total is rejected or absorbed.
2. Whichever is chosen, it holds on **every** write path — standalone add, both
   nested arrays, and Fyn capture — not only where a form happens to guard it.
3. The auto-created Cash holding's place in the constraint is stated explicitly.

## Working notes

- 2026-08-22 build-lead: raised from F-0025. The client-side guard shipped in
  `resources/js/utils/holdingsAllocation.js` is the single home for the frontend
  answer; if the server adopts a tolerance it should match the 0.01 used there,
  since `68.18 + 31.76 + 0.06` is `100.00000000000001` in IEEE 754 and a naive
  `> 100` would reject correct accounts.
