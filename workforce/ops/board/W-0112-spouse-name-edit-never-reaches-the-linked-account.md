---
id: W-0112
title: Editing a linked spouse's name never reaches their account — `users.name` is an appended accessor with no column, so the sync is silently discarded
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0009-batch-i-onboarding-spouse.md
owner: build-lead
claimed_by: fix-batch-I
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-21T18:45:00Z
claimed: 2026-08-21T19:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [FamilyMembersController::update, User::getNameAttribute, FamilyMember::booted name derivation]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found while writing the W-0051 regression tests. The assertion "editing the
linked spouse record renames their account" failed, and the cause turned out to
be pre-existing and unrelated to the link predicate.

### Actual

`FamilyMembersController::update()` syncs five fields from the family-member
record to the linked spouse's user account. Four arrive; the name does not.

```php
if (isset($data['name'])) {
    $spouseUpdates['name'] = $data['name'];
}
...
$spouseUser->update($spouseUpdates);
```

`users` has **no `name` column**. `name` is an appended accessor derived from
`first_name` / `middle_name` / `surname` (`User.php:107-114` declares it in
`$appends`; `:343-359` computes it). It is not in `$fillable`, so `fill()`
discards it, `save()` never sees it, and no error is raised.

Verified empirically today on `laravel_testing_d`: a `PUT` to a linked spouse
record with `first_name`, `last_name`, `date_of_birth`, `gender` and
`annual_income` returned 200 and synced date of birth, gender and income onto the
spouse's account. The spouse's `first_name` and `surname` were untouched, so
their displayed name stayed as it was.

### Impact

A household corrects a misspelled spouse name — or records a change of surname
after marriage — from `/settings/family`. The family-member card updates. The
spouse's own account keeps the old name, and so does everything that reads the
spouse from the user record: `/api/user/profile` → `spouse.name`, the `/m`
Personal Information screen, the native iOS profile screen, donut titles,
beneficiary dropdowns and joint-ownership labels. The two halves of the household
disagree about the person's name, permanently, with nothing to indicate it.

### Evidence

- `app/Http/Controllers/Api/FamilyMembersController.php` — the `name` branch of
  the spouse sync in `update()`
- `app/Models/User.php:107-114` — `$appends = ['name']`
- `app/Models/User.php:343-359` — `getNameAttribute()` derives from the parts
- `app/Models/User.php:$fillable` — no `name`
- `Schema::getColumnListing('users')` — `first_name`, `middle_name`, `surname`;
  no `name`, on both the development and the test database
- `tests/Feature/Api/SpouseFamilyLinkTest.php` — the "still syncs the spouse
  account" test asserts date of birth, gender and income and carries a comment
  naming this item as the reason it does not assert the name

## Acceptance

- [ ] The sync writes the name **parts** the column actually holds
      (`first_name`, `middle_name`, `surname`), not the derived `name`.
- [ ] One home: whatever derives a user's name parts from a family member's is
      written once and shared, never copied beside the four existing hand-rolled
      name splits (`FamilyMembersController`, `SpouseLinkingService::createReciprocalFamilyMember`,
      `OnboardingService::handleSpouseLinking`, `FamilyMember::booted()`) — Rule 20.
- [ ] A test pins that renaming a linked spouse changes what their own account
      reports, on the profile payload every surface reads.
- [ ] Consider whether `User::$fillable` should stop accepting `name` at all,
      since nothing can store it — a separate call, flag it rather than assume.

## Working notes

Raised by `fix-batch-I` from the W-0051 ID block (W-0111–W-0120). Pre-existing;
not introduced by the W-0051 fix and not made worse by it — the same `name` line
ran before, on the same records, with the same result.


---

## Working notes — fix-batch-I, 2026-08-21 (append-only)

### Confirmed by probe, not by reading

`User::isFillable('name')` is **false** and `Schema::hasColumn('users', 'name')`
is **false**, yet `$user->update(['name' => 'Changed Name', 'gender' => 'male'])`
returns without error, writes `gender`, and leaves the name derived from the
parts. So the discard is silent in the fullest sense: no exception, no failed
save, no warning, and the rest of the same update succeeds — which is exactly why
it survived.

### Not the same edge as W-0113

The team lead asked. W-0113 is the **write** side of creating a spouse, in the
tool layer. This is the **sync** side of editing one, in the HTTP layer. Both are
Rule 20 findings and neither collapses the other.

### What changed

One declared correspondence, on `SpouseLinkingService` — the service that already
owns the user ↔ family-member relationship:

```php
public const FAMILY_MEMBER_TO_USER_COLUMNS = [
    'first_name'  => 'first_name',
    'middle_name' => 'middle_name',
    'last_name'   => 'surname',          // the one that differs
    'date_of_birth' => 'date_of_birth',
    'gender' => 'gender',
    'annual_income' => 'annual_employment_income',
    'national_insurance_number' => 'national_insurance_number',
];
```

`last_name → surname` is the whole reason it needs a declared home: the one field
whose name differs is the one a hand-written sync gets wrong, and getting it
wrong fails silently.

- `FamilyMembersController::update()` — five hand-written `if (isset(...))` lines
  replaced by `userAttributesFrom($data)`. `name`, `relationship`, `notes`,
  `is_dependent` and `education_status` are dropped by the map rather than being
  offered to the user record at all.
- `SpouseLinkingService::createAndLinkNewSpouse()` — composes the person's own
  columns from the same map, and no longer passes a phantom `'name'` key.
- `createReciprocalFamilyMember()` — reads `first_name` / `middle_name` /
  `surname` instead of `explode(' ', $currentUser->name)`.

### The drift guard earned its place on its first run

I added a test pinning the declared map against what creation actually writes.
It failed immediately, on `middle_name`: a spouse created with a middle name got
it on their family-member card and **not** on their own account, because the
creation list was written out by hand and had never included it. Fixed.

Following that thread found a second one: `createReciprocalFamilyMember()` built
the spouse's view of their partner by splitting the **derived** display name on
spaces — throwing the middle name away and mis-splitting any double-barrelled or
multi-word surname, for the one record the spouse sees of their partner. `name`
is derived *from* those three columns, so splitting it back apart was lossy by
construction. Now reads the columns.

Neither was reported by anyone. Both were found by the guard, which is the
argument for writing that kind of test rather than only testing the fix.

### Tests

`tests/Feature/Api/SpouseFamilyLinkTest.php` — **17 passed**, including:
- *renames the linked spouse account, not just their card* — asserts
  `first_name`, `middle_name`, `surname` AND the derived `name` the profile
  payload, `/m` and native iOS all render;
- *does not push family-member-only fields onto the spouse account*;
- *keeps the declared field map in agreement with what the linking service
  writes* — the drift guard.

### Deliberately not fixed

`FamilyMembersController::update()` derives `$data['name']` from the parts only
when a part is supplied. A caller sending **only** `name` updates the legacy
column while the parts go stale, so the two diverge. Different defect, out of
this item's scope, and no current client does it — the form always sends parts.
Left for a sweep of the legacy `name` column rather than patched here.

### Open, for CSJ

`name` is not a column and is not fillable, yet `User::$guarded` does not exclude
it and callers keep passing it. Worth deciding whether `users.name` should be
rejected loudly rather than absorbed. Noted in the acceptance above; not taken.

- 2026-08-31 build-lead: **VERIFIED ALREADY FIXED AND TESTED — closed.**

  `FamilyMembersController:381` now routes the spouse update through `SpouseLinkingService::userAttributesFrom($data)`, which maps `family_members` columns onto `users` columns from **one declared correspondence** (`FAMILY_MEMBER_TO_USER_COLUMNS`) rather than five hand-written assignments.

  **The comment states the cause exactly, and it is the interesting part:** those five lines *"had to guess which column each field lands in. The name line guessed `name`, which `users` does not have, so every rename was discarded in silence."*

  `users.name` is an **appended accessor** (`User::getNameAttribute()`, `:342`) composed from the name parts, with a legacy-column fallback. Writing to it therefore **fails silently** — Eloquent has no column to set and no error to raise. That is why the defect was invisible: the update returned 200, the family-member row changed, and the account did not.

  **A declared map is what makes that class of fault impossible**, rather than a fix to one line: any field whose destination is unknown simply is not in the map, so it cannot be written to a column that does not exist.

  **Tested:** 76 spouse-linking and family-member tests pass, 228 assertions.
