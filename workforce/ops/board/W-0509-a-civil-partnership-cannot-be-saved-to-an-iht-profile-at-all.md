---
id: W-0509
title: A civil partnership cannot be saved to an Inheritance Tax profile at all — the column enum and the validation rule both predate the status
mission: persona-run-peak_earners-2026-08-20
branch: fix/w-0509-civil-partnership-cannot-be-saved-to-iht-profile
owner: null
reviewers: [tax-compliance-reviewer, security-reviewer]
status: done
closed: 2026-08-29
claimed_by: null
severity: critical
surfaces: [web, m]
created: 2026-08-28T13:10:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-28
prior_art_found: [W-0474, W-0480, W-0508]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer gate report on W-0480, finding F6, 2026-08-28
---

## Intent

**This is not a wrong figure. It is a hard block**, and no grep guard will find it —
neither layer contains a quoted `'married'` literal, which is exactly how it escaped the
sweep filed under W-0480.

Two layers still carry the pre-2026-04-15 list:

- `app/Http/Controllers/Api/Estate/IHTController.php:290` —
  `'marital_status' => ['nullable', 'string', 'in:single,married,widowed,divorced']`.
  A civil partnership submitting their Inheritance Tax profile gets a **422**.
- The column itself: `iht_profiles.marital_status` is
  `enum('single','married','widowed','divorced') NOT NULL`, while
  `users.marital_status` is `enum('single','married','civil_partnership','divorced','widowed')`.
  Verified on the live database, not only in `database/schema/mysql-schema.sql:1491`.

The `users` column was corrected on 2026-04-15 by
`2026_04_15_091500_add_civil_partnership_to_users_marital_status`. **The table one over
was missed.**

`ComprehensiveEstatePlanService:64` builds an in-memory `IHTProfile` carrying
`$user->marital_status`, so it constructs a value the column would reject the moment
anything persisted it.

## Acceptance

1. A migration widens `iht_profiles.marital_status` to match `users.marital_status`.
   An `ALTER` that preserves the existing rows — nothing that drops or rebuilds the table.
2. The `IHTController` rule reads the same list, and does not restate it as a literal —
   `App\Support\HouseholdPooling` or the users column is the source.
3. A test that POSTs an Inheritance Tax profile as a civil partnership and gets a 2xx,
   then reads it back with the status intact. It must fail before the migration.
4. A sweep for the same omission elsewhere: any other table or `in:` rule listing
   marital statuses without `civil_partnership`. The 2026-04-15 migration touched one
   table; this one asks what else it should have touched.
5. Rule 19 — the profile is submitted from web AND `/m`.

## Working notes

- 2026-08-28 — Raised as F6 by the `tax-compliance-reviewer` gate on W-0480, alongside
  the point that **the new sweep guard cannot see this class of defect**: it looks for a
  quoted `'married'` literal or an `in_array`, and a Laravel `in:` rule string and a DB
  enum are neither. Acceptance 4 is the answer to that, not another regex.
- 2026-08-28 — Severity `critical` rather than `high` because it is a submission a
  civil partnership cannot complete, not a number they see wrong.

## Resolution — 2026-08-28

**Both layers, and the guard that holds them together.**

**Acceptance 1 — the column.**
`2026_08_28_211500_add_civil_partnership_to_iht_profiles_marital_status` is an `ALTER
TABLE ... MODIFY`, so no row is dropped or rebuilt. The value is **appended** rather than
slotted into the users column's ordering: the two columns then hold the same SET, which is
what the acceptance asks for, and no existing row has to survive an enum re-ordering.
MySQL stores an enum by ordinal, and a MODIFY that moves values around is the kind of
silent remap nobody finds until the figures are already wrong. The column stays `NOT NULL`
with no default, exactly as it was. `down()` maps any `civil_partnership` row to `married`
first — a civil partnership is a marriage for every Inheritance Tax purpose the app models
(W-0480), so the rollback loses the distinction but not the tax treatment.

**Acceptance 2 — the rule.** `HouseholdPooling::ALL_MARITAL_STATUSES` is the whole
vocabulary, deliberately distinct from `POOLING_MARITAL_STATUSES` (which is only the two
statuses that pool a household). `IHTController` now reads it through `Rule::in()` instead
of restating `in:single,married,widowed,divorced`.

**Acceptance 3 — the test.** `tests/Feature/Estate/CivilPartnershipCanSaveAnIhtProfileTest`
POSTs as a civil partnership, asserts a 2xx, and reads the status back **from the database**
rather than the response — the column is the layer a passing controller would otherwise
hide. HTTP rather than unit, because the validation rule is the outer of the two layers and
a test writing the model directly would have gone green against a controller still
rejecting every real submission. **Mutation-verified:** restoring the old `in:` literal
turns two of the four red.

**Acceptance 4 — the sweep, and what it found.** Exactly two stale sites existed, and both
are now fixed: this `in:` rule and this column. `UpdatePersonalInfoRequest:63` already had
the full list. No other table carries a marital-status enum.

The sweep is kept as a **test, not another regex**: `it holds the two columns to the same
vocabulary` reads both column definitions out of `SHOW COLUMNS` and compares them to each
other and to the shared constant. It fails if either column is widened without the other —
which is precisely the mistake the 2026-04-15 migration made. That answers the working
note: the guard for this defect class is a comparison, because neither layer contains a
literal a grep could match.

**Acceptance 5 — Rule 19.** The fix is the validation rule and the column, both shared by
architecture, so web and `/m` are corrected by the same change. Neither surface renders its
own marital-status option list for this form.

**Adjacent, reported not fixed.** `EstateOverviewCard.vue:116`, `IHTPlanning.vue:1657` and
`WillPlanning.vue:519` still branch on `marital_status === 'married'` alone, so a civil
partnership can now save the profile and still read a single person's framing above it.
Already in scope on [[W-0508-fourteen-more-sites-read-married-alone-so-a-civil-partnership-is-still-treated-as-single]],
whose §1 names `IHTPlanning.vue:1657` explicitly. Left there rather than fixed in passing.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`in_review`.

- **Delivered by:** Stoff73
- **Evidence:** merged in #742; commit `1a6312d6d` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
