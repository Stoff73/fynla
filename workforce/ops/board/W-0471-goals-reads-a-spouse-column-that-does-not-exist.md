---
id: W-0471
title: Three Goals consumers read `users.spouse_user_id`, a column that does not exist, so household goal logic silently never fires for a linked couple
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: null
reviewers: [quality-lead]
status: handoff
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-24T00:15:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0154, W-0350]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found while fixing W-0349, 2026-08-24 — reported rather than folded into that fix
---

## Intent

`users` has **`spouse_id`**. It has no `spouse_user_id`. Three Goals consumers read
the second name:

| File | Line |
|---|---|
| `app/Http/Controllers/Api/GoalsController.php` | 581 |
| `app/Services/Goals/GoalsProjectionService.php` | 553, 555 |
| `app/Services/Goals/LifeEventService.php` | 36 |

Measured, not read off the code:

```
has spouse_id: YES
has spouse_user_id: no
user 16 → spouse_id = 17   spouse_user_id = NULL
```

User 16 IS linked to 17. **Every one of these sites therefore takes the
no-spouse branch for a household that has one**, permanently and silently.

## Why it survived

**This is the silent half of the phantom-column family already recorded in
`tests/CLAUDE.md`**: a missing attribute read off an Eloquent *model* returns
`null` without complaint, while the same name in a *query builder* throws. The
throwing ones were found long ago (`db_pensions.transfer_value`,
`mortgages.end_date`); these three are the silent ones, and they read perfectly
naturally — `if ($household && $user->spouse_user_id)` looks like a guard doing
its job.

`GoalsProjectionService:553` is the clearest case: the whole household branch is
gated on it, so a couple's joint goals projection has never run.

## Acceptance

1. All three read `spouse_id` — or better, the existing `liveSpouseId()` helper,
   which already honours a deleted partner (W-0347's retention decision).
2. Before/after for a linked household, measured, showing a household branch that
   now fires where it did not.
3. A guard that would fail if the name regressed — the sweep below suggests the
   generalisable one is worth more than three assertions.
4. **Sweep for the rest of the family.** These three were found by accident while
   fixing an unrelated item. `users.spouse_user_id` is one name; the same silent
   read can exist for any column on any model. A test that walks model attribute
   reads against the live schema would retire the whole class.

## Working notes

- 2026-08-24 — Found while fixing W-0349, grepping for consumers of a details-array
  key that happened to share the name. Not fixed there: it is a Goals defect with
  its own before/after, and folding it into a spouse-consent commit would bury it.

- 2026-08-24 — **FIXED, all three sites.** `GoalsController:581`,
  `GoalsProjectionService:553,555` and `LifeEventService:36` now read
  `liveSpouseId()`.

- 2026-08-24 — **`liveSpouseId()` rather than `spouse_id`**, per acceptance 1. The raw
  column survives the partner deleting their account (retention, CSJ D1/D2 2026-08-19),
  so a household view built on it would keep reading a closed account's goals. There is a
  test for exactly that.

- 2026-08-24 — **Acceptance 3 and 4 met by
  `tests/Feature/Goals/HouseholdGoalsReadTheRealSpouseColumnTest.php`** (4/4):
  - the premise — `users` has `spouse_id` and no `spouse_user_id`;
  - a **source sweep** across `app/Http/Controllers`, `app/Services` and `app/Agents` for
    `->spouse_user_id`, because a model read of a missing attribute cannot fail at
    runtime and a source sweep is the only thing that can catch this class regressing.
    The arrow distinguishes a column read from a response KEY of the same name, which
    `CoordinatingAgent` publishes deliberately;
  - the behaviour — a spouse's household-visible goal is gathered in household mode and
    NOT in individual mode. **Both asserted**, so a change that simply returns everything
    cannot pass;
  - and the deleted-spouse case.

- 2026-08-24 — 66 tests pass across `tests/Feature/Goals` and `tests/Unit/Services/Goals`.
  Pint clean.

