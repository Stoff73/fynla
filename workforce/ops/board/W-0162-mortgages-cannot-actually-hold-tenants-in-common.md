---
id: W-0162
title: The mortgages enum accepts tenants_in_common but nothing downstream understands it, so the coercion to joint is load-bearing rather than stale
mission: M-0002-persona-fidelity
owner: build-lead
claimed_by: brett-2026-08-25
status: done
certification: CERTIFIED 2026-08-25 quality-lead — see ops/handoffs/quality-lead/pr716-certification-2026-08-25.md; merged to dev in 88e9d08ce (PR #716)
severity: low
surfaces: [web, m, ios]
source: found by fix-batch-F while fixing W-0172, 2026-08-21
prior_art_checked: 2026-08-21
prior_art_found: [app/Services/Property/MortgageService.php, app/Services/Stores/Normalisers/MortgageNormaliser.php, app/Services/Stores/MortgageStore.php, database/migrations/2026_01_17_100145_add_tenants_in_common_to_mortgages_ownership_type.php]
prior_art_outcome: none
---

## Intent

`mortgages.ownership_type` is
`enum('individual','joint','tenants_in_common','trust')`, widened on **2026-01-17**
by a migration named for the purpose:
`2026_01_17_100145_add_tenants_in_common_to_mortgages_ownership_type`.

**Nothing downstream can use it.** Two places coerce `tenants_in_common` → `joint`
on the way in, both carrying a comment saying mortgages "do not support" it:

- `app/Services/Property/MortgageService::normalizeMortgageOwnershipType()`
- `app/Services/Stores/Normalisers/MortgageNormaliser` (`:79-87`, and the class
  docblock at `:15`)

And the coercion is **not** stale decoration. Removing it breaks things:

- `MortgageStore::validateCanonical:304` — `'in:individual,joint'`. A TIC mortgage
  is rejected at the store.
- At least seven consumers decide whether a mortgage is shared by testing
  `ownership_type === 'joint'` **exactly**:
  `UserProfileService:931`, `PropertyCard.vue:153`,
  `PropertyDetailInline.vue:382,388,814`, `PropertyFinancials.vue:443`,
  `LetterToSpouse.vue:482`.

A mortgage stored as `tenants_in_common` would therefore read as **individual** to
every one of them and charge the user **100%** of the debt — a worse defect than
W-0172, on more surfaces.

## Why this is filed rather than fixed

I nearly removed the coercion while fixing W-0172, on the strength of the migration
alone. The migration says the database is ready; it says nothing about the
application, and the application is not. **The comment was wrong about the column
and right about the app** — which is the failure mode the "read the enforcing
declaration, not a mention of it" rule exists to catch.

W-0172 is fixed without touching the type: what was wrong there was the **share**
being flattened along with it, and the share is now carried from the parent property.
So there is no user-visible defect left behind this item — only a column that
promises something the code cannot deliver.

## Acceptance

1. [x] Decide whether mortgages should genuinely hold `tenants_in_common`. There is a
   real case — a mortgage over a tenants-in-common property is not a joint tenancy —
   but it is a labelling improvement, not a figures fix, because
   `SharedOwnership::isShared()` already treats both identically for every
   calculation. — **DECIDED: NO.** See the 2026-08-25 note.
2. [n/a] If yes: widen `MortgageStore::validateCanonical`, convert all seven consumers to
   `SharedOwnership::isShared()` rather than `=== 'joint'`, remove both coercions,
   and give the wizard's Borrower(s) control a way to express it. — not taken. Note the
   `=== 'joint'` comparisons are **exhaustive and correct** under the NO decision: a
   mortgage can only ever be `individual` or `joint`. The Rule 20 shape only becomes a
   defect if TIC is made writable, so option 2 would have created the problem it then
   had to fix.
3. [x] If no: reverse the January migration or record on the column why it holds a value
   nothing may write. A column that accepts what the app rejects is a trap for the
   next reader — it is what nearly caught me. — **DONE: recorded on the column.** The
   migration was deliberately NOT reversed; its own `down()` keeps `trust`, so
   reverting would not resolve the mismatch it exists to resolve.

## Working notes

Not urgent and not user-visible. Filed so the contradiction is written down
somewhere other than a comment that already misled once.

- 2026-08-25 (Brett, working alone per CSJ's 2026-08-24 standing instruction):
  **DECIDED NO, and the column now says so itself.**

  **Verified the contradiction first, independently.** TIC is rejected at *three*
  layers, so the coercion is genuinely load-bearing exactly as this item warned:
  `StoreMortgageRequest:72` / `UpdateMortgageRequest:72` (`Rule::in(['individual',
  'joint'])`), `MortgageNormaliser:79-87`, and `MortgageStore::validateCanonical:304`.
  No write path bypasses them. **Live distribution: 6 `joint`, 3 `individual`, zero
  `tenants_in_common`, zero `trust`** — the coercion has been completely effective and
  there is nothing to migrate.

  **Two facts this item did not record, both pointing the same way:**
  - **`trust` has the identical problem.** `normalizeMortgageOwnershipType()` sends it
    to `individual`, because `isShared('trust')` is false. The column offered four
    values; the application could write two.
  - **Reversing the January migration would not have fixed it.** Its `down()` narrows
    to `ENUM('individual','joint','trust')` — it *keeps* `trust`. Acceptance 3's first
    option resolves nothing.

  **Why NO — and the reason is stronger than "it is only a label".** CSJ's **W-0228
  ruling (2026-08-22), "not open for re-litigation"**, is that *a debt is shared exactly
  as the asset securing it is shared.* `CalculatesOwnershipShare:156-164` now **throws**
  if anyone tries to compute a mortgage share from the mortgage row, directing them to
  `calculateUserMortgageShare`, which resolves the property. **So the mortgage's
  `ownership_type` is no longer the source of truth for any share — the property is.**
  Widening the enum would add expressiveness to a column that ruling deliberately
  demoted, and the property side already holds `tenants_in_common` correctly, which is
  where the concept belongs.

  Independently: tenants in common describes how a **title** is held, not how a **debt**
  is held. Co-owners of a tenants-in-common property still borrow jointly and severally;
  a lender does not recognise TIC on the borrowing. The coercion is correct modelling.

  **The `=== 'joint'` comparisons are not a Rule 20 defect under this decision.** They
  are exhaustive: a mortgage can only be `individual` or `joint`. Acceptance 2 would
  have created the very problem it then had to fix across seven files.

  **What was done:**
  - `2026_08_25_100000_say_on_the_column_which_mortgage_ownership_types_are_writable`
    — puts a COMMENT on `mortgages.ownership_type` naming the two writable values, the
    three layers that enforce it, and the W-0228 reason. **The enum is deliberately not
    narrowed** (see above). Rollback verified: down() clears the comment, re-up restores
    it, row counts unchanged at 6/3 throughout.
  - `MortgageNormaliser` class docblock and inline comment — rewritten. They said
    *"Mortgages do NOT support ownership_type=tenants_in_common"*, which reads as a
    shortfall; they now state the modelling decision.
  - `MortgageService::normalizeMortgageOwnershipType()` docblock — records the decision
    instead of describing W-0162 as open, and its stale consumer line references are
    corrected (`UserProfileService` :931 → :967, `LetterToSpouse` :482 → :1101; both
    re-verified).

  **Verification:** Mortgage 199 tests / 2,091 assertions green; Property + Ownership
  385 / 1,102 green. Pint clean. No behaviour changed, no figure moves, no consumer
  touched.

  **Raised separately — W-0483.** Brett asked whether a tenants-in-common property can
  have a mortgage allocated 100% to one owner. In law it can (a co-owner may charge
  their own beneficial share), and **Fynla cannot express it** — W-0228's knowingly
  accepted limitation. It is NOT fixable here: since the share resolves from the
  property, TIC on the mortgage row would be a label no calculation reads, so even
  acceptance 2 would not have delivered it. Only CSJ can reopen W-0228, so the question
  is escalated rather than answered.
