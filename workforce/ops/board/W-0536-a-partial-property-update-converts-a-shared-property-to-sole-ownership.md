---
id: W-0536
title: Any partial update to a property converts it to sole ownership — the /m spouse answer took a jointly-owned house from a 50% share to 100%
mission: board-verification-31-august
owner: build-lead
reviewers: [tax-compliance-reviewer, quality-lead]
status: done
severity: high
surfaces: [web, m]
created: 2026-09-04
source: found browser-testing W-0500 on csjones, 2026-09-04
prior_art_checked: 2026-09-04
prior_art_found: [W-0500, W-0040, W-0014]
prior_art_outcome: extends — W-0040 fixed the same class for a LINKED joint owner and stopped there
constitution_refs: [07-quality-bar]
---

## Intent

Browser-testing W-0500's acceptance on csjones. Property 8 on `chris@fynla.org`:
`joint`, 50%, co-owner "wife" with no account, `joint_owner_is_spouse` NULL —
exactly the state the question is asked in. Pressing **Yes** recorded the answer
and destroyed the ownership:

| Column | Before | After |
|---|---|---|
| `ownership_type` | `joint` | **`individual`** |
| `ownership_percentage` | `50.00` | **`100.00`** |
| `joint_owner_is_spouse` | NULL | `true` |

The detail card stopped saying "Held with wife" and the user's share went from
£90,000 to £180,000 — carried straight into net worth and the estate.

## Root cause

`/m` sends a one-key PUT (`PropertyDetail.vue:129`), which is correct.

`PropertyController::update():270` resolves the effective type from the stored
record — `$ownershipType = $validated['ownership_type'] ?? $existingProperty->ownership_type`
— and then **never writes it back into `$validated`**. Both branches below it are
skipped: `:273` requires a linked `joint_owner_id`, which an unlinked co-owner
does not have, and `:278` requires the type to already be `individual`.

`PropertyNormaliser::fromForm():49` then injects the key unconditionally, two
lines under a comment stating the opposite intent ("We DO NOT inject the key when
the input omitted it"), and `canonicalOwnershipType(null)` returns `'individual'`
(`:293`). `SharedOwnership::applyTo()` follows that to 100.

So the reach is every partial PUT to `/api/properties/{id}` that omits
`ownership_type`, not just the spouse answer.

## Why no test caught it

`CoOwnerSpouseAnswerComesFromTheUserTest:71` drives **exactly this request** and
asserts only `joint_owner_is_spouse`. It was green over the damage the same
request did — the subject-vs-collateral blind spot in the
`test-failure-forensics` skill.

## Outcome — done, 2026-09-04

`app/Http/Controllers/Api/PropertyController.php:270-284`. The resolved
ownership type is written back into the payload, and the stored split is
preserved for any shared property, not only one whose co-owner holds an account
— an unlinked co-owner's stated 70/30 is just as stated as a linked one's.

Two tests added to `tests/Feature/Estate/CoOwnerSpouseAnswerComesFromTheUserTest.php`,
both red before the change and green after: the spouse answer keeps
`joint`/50, and a partial update keeps a stated 70/30 rather than re-defaulting
to 50.

Suites: 278 passed (804 assertions) across `Api/PropertyControllerTest`,
`Feature/Stores`, `Feature/Property` and `UndividedShareDiscountTest`.

**Not re-verified in the browser** — the fix is local; csjones still runs the
code that produced the damage. Property 8 on csjones was restored to `joint`
/ 50.00 with `saveQuietly()`; the `joint_owner_is_spouse = true` the test wrote
is a genuine answer and was left in place.
