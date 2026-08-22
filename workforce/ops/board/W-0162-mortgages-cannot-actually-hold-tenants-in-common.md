---
id: W-0162
title: The mortgages enum accepts tenants_in_common but nothing downstream understands it, so the coercion to joint is load-bearing rather than stale
mission: M-0002-persona-fidelity
owner: build-lead
claimed_by: null
status: queued
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

1. Decide whether mortgages should genuinely hold `tenants_in_common`. There is a
   real case — a mortgage over a tenants-in-common property is not a joint tenancy —
   but it is a labelling improvement, not a figures fix, because
   `SharedOwnership::isShared()` already treats both identically for every
   calculation.
2. If yes: widen `MortgageStore::validateCanonical`, convert all seven consumers to
   `SharedOwnership::isShared()` rather than `=== 'joint'`, remove both coercions,
   and give the wizard's Borrower(s) control a way to express it. **One home for the
   predicate** — the `=== 'joint'` comparisons are the Rule 20 shape.
3. If no: reverse the January migration or record on the column why it holds a value
   nothing may write. A column that accepts what the app rejects is a trap for the
   next reader — it is what nearly caught me.

## Working notes

Not urgent and not user-visible. Filed so the contradiction is written down
somewhere other than a comment that already misled once.
