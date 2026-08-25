---
id: W-0326
title: A mixed-rate mortgage cannot be saved at all — MortgageStore rejects `mixed`, which the column and all three form requests allow, and accepts `capped`/`offset`, which the column cannot store
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T23:55:00Z
claimed: 2026-08-23T00:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0263, W-0324]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

**W-0263's defect class, in a layer W-0263 could not see** — and it blocks
W-0263's own headline verification.

### The disagreement, in one line

`app/Services/Stores/MortgageStore.php:307`:

```php
'rate_type' => 'sometimes|nullable|in:fixed,variable,tracker,discount,capped,offset',
```

| Layer | `rate_type` accepts |
|---|---|
| `mortgages.rate_type` column | `enum('fixed','variable','tracker','discount','mixed')` |
| `StoreMortgageRequest:53` · `UpdateMortgageRequest:53` · `StorePropertyRequest:90` | fixed, variable, tracker, discount, **mixed** |
| **`MortgageStore:307`** | fixed, variable, tracker, discount, **capped, offset** |

**Both directions of the same disagreement in a single rule:**

1. It **omits `mixed`**, which the column stores and all three requests permit.
   The property form's rate-type select offers "Mixed" to the user.
2. It **adds `capped` and `offset`**, which the enum cannot hold at all — they
   would be a truncation or an error at the column, the W-0263 shape exactly.

### What the user experiences

Reproduced in the browser as David (16) on Unit 12 Victoria Mill:

- select rate type **Mixed** → the form reveals Fixed Interest Rate and Variable
  Interest Rate, exactly as designed
- enter 12% and 14.75%, press Save Property
- `PUT /api/properties/20` → **200**, `PUT /api/mortgages/16` → **422**
  `{"message":"Validation failed","errors":{"rate_type":["The selected rate type is invalid."]}}`
- **the modal closes as though everything saved** (see W-0327), and the mortgage
  is unchanged in the database

So a mixed-rate mortgage — part fixed, part variable, an entirely ordinary
product — **cannot be recorded through the interface at all**, and the user is
not told.

### Why this blocks W-0263

`fixed_interest_rate` and `variable_interest_rate` — the two columns W-0263
widened, and the item's headline — **only render when `rate_type` is `mixed`**.
So the browser proof of "a 12% mortgage rate saves" is unreachable until this is
resolved. The API-level proof exists and is green
(`tests/Feature/Validation/ValidatedRangeReachesTheColumnTest.php`), and the 422
above named only `rate_type` — never `fixed_interest_rate` — so the 12 passed
validation; it simply never reached the column.

### The decision this needs

Not a guess: **is `mixed` a supported product, and are `capped`/`offset` wanted?**
If they are, the column enum needs them too. If they are not, the Store's list is
simply wrong. Either way the three layers must be brought into agreement, and
which way is a product call.

**This also proves the Store-versus-request parity surface is real, not
theoretical** — Fyn's capture path, and therefore the whole of `/m`, writes
through the Stores rather than the form requests (`resources/mobile/api.js` has
no post/put/patch helper anywhere). See W-0324.

## Acceptance

1. One list of rate types, agreed, holding across the column enum, all three form
   requests, and `MortgageStore`.
2. A mixed-rate mortgage saves end to end through the property form.
3. A drift guard in the shape of `tests/Unit/Database/ValidationMaxFitsColumnPrecisionTest.php`
   would catch this class: an `in:` rule whose set differs from its column's enum.

## Working notes

- 2026-08-22 build-lead (`fix-cycle4-columns`): `app/Services/Stores/` is outside
  this batch's scope, and the correct list is a product decision, so this was
  filed rather than guessed.

## Working notes — FIXED 2026-08-23, authorised by team-lead

`MortgageStore:307` now reads `in:fixed,variable,tracker,discount,mixed` — exactly
the column enum. Scope for this one file was extended explicitly; nobody else was
in the Stores.

**Why this was not the product call it looked like:**

- `capped` and `offset` **cannot be stored at all** — the enum has no room for
  them, so a Store accepting them could only ever produce a failing write.
  Removing them changed nothing any user could do. Whether the app *should*
  support them is a genuine question and is **W-0328**, raised separately so the
  removal is not misread as a decision against them.
- `mixed` **can** be stored, all three requests already allow it, and the property
  form offers it. The Store was the only one of four layers dissenting; aligning it
  restored consistency rather than choosing a policy.

**The swallowed failure, observed live rather than filed from reading.** On
property 20 the modal **closed as though it had saved** while underneath
`PUT /api/properties/20` returned 200 and `PUT /api/mortgages/16` returned **422**.
The database confirmed mortgage 16 unchanged. That is
`PropertyDetailInline.vue:701` catching and logging — a closed modal is not
evidence of a save, and this run is the proof.

**A second mismatch in the same rule set was deliberately NOT changed.**
`ownership_type` refuses `tenants_in_common` and `trust`, which the column stores —
but `MortgageNormaliser:79-81` coerces tenants_in_common to joint before the Store
sees it and documents that mortgages do not support it. Mechanically aligning it
would have broken a documented decision and disturbed the CSJ ruling at W-0228.
**A column wider than its rule is only a defect when a user is refused something
they can legitimately do.**

**Drift guard:** `tests/Unit/Database/StoreEnumRulesMatchColumnsTest.php`, 2 tests,
checking both directions and carrying an explicit exception list for sanctioned
narrowings. **Proven to fail:** restoring the old rule turned both red, naming
`capped, offset` as unstorable and `mixed` as wrongly refused.

**Still open:** the browser proof of the W-0263 12% headline, which this unblocks.
