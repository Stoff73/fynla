---
id: W-0325
title: Every joint property update 500s — PropertyController is missing `use App\Models\User`, so a type hint resolves to a class that does not exist
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T23:55:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0263]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

**Reproduced in the browser**, while attempting the W-0263 headline verification.
It is live on `dev` right now and it blocks an entire journey.

### The defect

`PUT /api/properties/9` as David (16) returns **500**:

```
PropertyController::logJointPropertyUpdate(): Argument #1 ($user) must be of type
App\Http\Controllers\Api\User, App\Models\User given, called in
app/Http/Controllers/Api/PropertyController.php on line 284
```

`app/Http/Controllers/Api/PropertyController.php:448` declares:

```php
private function logJointPropertyUpdate(User $user, Property $property, array $validated): void
```

**`use App\Models\User;` is absent from the file's imports.** The class imports
`App\Models\JointAccountLog` and `App\Models\Property` but not `User`, so `User`
resolves against the current namespace as `App\Http\Controllers\Api\User` — a
class that does not exist. PHP only discovers this when the argument is bound, so
the file parses, `php -l` passes, and nothing fails until a user saves.

### Reach

Line 283 gates the call:

```php
if ($this->isSharedOwnership($existingProperty) && $existingProperty->joint_owner_id) {
```

So **every property that is joint AND has a joint owner 500s on update.** On this
persona that is 15 Chestnut Lane and Flat 42 Riverside — two of David's three
properties. The third, Unit 12 Victoria Mill, is `tenants_in_common` with a NULL
`joint_owner_id`, so it skips the branch and saves normally; that is the only
reason any property verification was possible at all.

### Provenance — already committed, not in flight

`git status` reports no working-tree modification: the defect is committed in
**`d5fe9f9f7`** ("wip: working-tree snapshot at 2026-08-22 20:22"), so it is live
for every agent and every browser session, not a transient edit.

**This is the formatter trap in `tests/CLAUDE.md` §2, verbatim.** Pint and the
PostToolUse formatter hook delete an import that is unreferenced *at the moment
they run* — add `use App\Models\User;` in one edit and the `User` type hint in the
next, and the import is gone before the second edit lands. The documented
countermeasure is to add the import and its first reference in the SAME edit, and
to `grep -n '^use ' path/to/File.php` after any formatter run.

## Acceptance

1. `use App\Models\User;` added to `PropertyController`.
2. A joint property update saves — verified in the browser, not by unit test
   alone, since the failure is a runtime type-binding error that a test using a
   real `User` model would also have caught but none did.
3. Worth a sweep: any other file type-hinting `User` without importing it has the
   same latent 500. The same grep that finds this finds those.

## Working notes

- 2026-08-22 build-lead (`fix-cycle4-columns`): found while verifying W-0263, not
  by inference. `app/Http/Controllers/` is outside this batch's scope so it was
  reported rather than fixed; the fix itself is one line.

- 2026-08-23 — **Already fixed in code; the board item was simply never moved.**
  `use App\Models\User;` is present at `PropertyController.php:15`. It landed in
  `5de82a7fd` ("wip: persona peak_earners cycle 4"), which is an ancestor of HEAD and is
  on dev. Acceptance 1 met.

- 2026-08-23 — **Acceptance 2 met, browser-verified on the exact reproduction case.**
  Signed in as `david.jones@example.com` (user 16, the account in the original report),
  opened property 9 — the joint 50% property in the report — changed Full Property Value
  from £850,000 to £862,500 and saved. **200, not 500.** `properties.current_value` read
  back `862500.00`, and `joint_account_logs` gained row 3,
  `App\Models\Property #9 | user 16 | update`, at the save timestamp — which proves
  `logJointPropertyUpdate()` ran and **bound its `User` argument**, the exact call that
  used to throw. Test data restored afterwards: value back to £850,000, the two log rows
  removed.

- 2026-08-23 — **Acceptance 3 met — the sweep is clean.** Scanned every `.php` under
  `app/` outside `App\Models` for a bare `User` used as a type hint, nullable type,
  return type or static, with no `App\Models\User` import and no aliased `User` import:
  **zero files.** No other file carries this latent 500.

- 2026-08-23 — Arrived at `handoff` AFTER the quality-lead cycle-4 certification pass had
  begun, so it is outside that agent's original scope. Certify it separately.
