---
id: W-0509
title: A civil partnership cannot be saved to an Inheritance Tax profile at all — the column enum and the validation rule both predate the status
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer, security-reviewer]
status: queued
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
