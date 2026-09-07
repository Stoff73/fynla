---
id: W-0114
title: Adding a Partner or a Step Child returns HTTP 500 — the family form offers six relationships and the column holds four
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0009-batch-i-onboarding-spouse.md
owner: build-lead
claimed_by: fix-batch-I
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T19:20:00Z
claimed: 2026-08-21T19:20:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [CoordinatingAgent::handleCreateFamilyMember relationship mapping, FamilyMember model]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by `fix-batch-I` while investigating W-0111 (a partner's email being
silently discarded). The email turned out to be the smaller half: **adding a
partner at all fails.**

### Actual

`family_members.relationship` is
`enum('spouse','child','parent','other_dependent')` — four values.

`FamilyMemberFormModal.vue` offers **six**: spouse, partner, child, step_child,
parent, other_dependent. `StoreFamilyMemberRequest` and
`UpdateFamilyMemberRequest` both validate the extra two as allowed, and
`FamilyMembersController::store()` writes the value straight into the column.

The MySQL connection runs with `strict => true`, so this does not degrade — it
raises. Verified end to end through the real endpoint on `laravel_testing_d`:

```
POST /api/user/family-members  relationship=partner
  → 500  SQLSTATE[01000]: Warning: 1265 Data truncated for column 'relationship' at row 1

POST /api/user/family-members  relationship=step_child
  → 500  SQLSTATE[01000]: Warning: 1265 Data truncated for column 'relationship' at row 1
```

Nothing is stored, and the raw SQL statement is what comes back in the response
message.

### Impact

`FamilyMemberFormModal` is the **same component** on `/settings/family` and on
onboarding step 2. So a step-parent adding their step-child — an ordinary family
shape, not an edge case — gets a database error on their first run through the
product, from a dropdown option the product itself offered them.

### Root cause — the same shape as W-0051, W-0111, W-0112 and W-0113

The mapping from the offered relationships to the storable ones **already
existed**, in one place only:

```php
// app/Agents/CoordinatingAgent.php, handleCreateFamilyMember()
$dbRelationship = match ($relationship) {
    'step_child' => 'child',
    'partner' => 'other_dependent',
    default => $relationship,
};
```

So Fyn could add a step-child and the family form could not. One mechanism knew
the rule; the parallel mechanism did not; and the one that did not is the one a
new user touches first.

### Evidence

- `SHOW COLUMNS FROM family_members LIKE 'relationship'` →
  `enum('spouse','child','parent','other_dependent')`
- `resources/js/components/UserProfile/FamilyMemberFormModal.vue:30-37` — six options
- `app/Http/Requests/StoreFamilyMemberRequest.php:29` — validates all six
- `app/Http/Requests/UpdateFamilyMemberRequest.php:29` — validates `step_child`
- `app/Agents/CoordinatingAgent.php` — the mapping, previously inline and alone
- `config/database.php` — `strict => true`, so 1265 is fatal rather than coerced
- `tests/Feature/Api/FamilyMemberRelationshipAliasTest.php` — the reproduction,
  now as a regression test

## Acceptance

- [x] Adding a partner returns 201, not 500.
- [x] Adding a step child returns 201, not 500.
- [x] The same on update, which accepted `step_child` and would have failed
      identically.
- [x] ONE home for the translation, read by the form path and the Fyn tool path
      (Rule 20) — `FamilyMember::RELATIONSHIP_ALIASES` /
      `resolveRelationship()` / `composeRelationshipNotes()`, since the model
      owns the column.
- [x] A test asserts both paths land an identical row.
- [x] A test pins the enum itself, so widening or narrowing the column fails
      loudly rather than silently invalidating the translation.
- [x] **The card shows the relationship the user chose, never the alias.** A
      partner reads "partner", a step child reads "step child".
- [x] The stated value is recorded on the Fyn path as well as the form path.
- [ ] Re-verified live in the browser — persona-tester, not self-certified.

## Working notes — fix-batch-I, 2026-08-21

### What changed

`FamilyMember` now owns the translation, because it owns the column and that
makes it the one place a new writer cannot miss — the same argument as the
`booted()` name derivation already in that model.

| File | Change |
|---|---|
| `app/Models/FamilyMember.php` | `RELATIONSHIP_ALIASES`, `resolveRelationship()`, `composeRelationshipNotes()` |
| `app/Http/Controllers/Api/FamilyMembersController.php` | `store()` and `update()` resolve before writing |
| `app/Agents/CoordinatingAgent.php` | its inline `match` pair deleted; reads the model |

`grep -rn "'step_child' =>" app/` now returns exactly one line, in the model.

### Amendment — the alias is stored, never displayed (team lead, 2026-08-21)

My first cut mapped the relationship for storage and let the card render the
stored value, which I described as "consistent but not lovely". The team lead was
right that this understates it: **the application would be telling a user that
their partner is a dependent.** That is a false statement about their household,
made by the software in its own voice — the same class of thing removed from
wills and powers of attorney today. Storing an alias is fine; displaying the
alias as though it were the truth is not.

So the fix now has two halves:

1. **The alias, for the column.** Unchanged from above.
2. **`family_members.stated_relationship`** — a new nullable column holding the
   relationship the user actually chose, and one display home on each side that
   reads it.

**Additive, not semantic**, which is the whole point:

- the enum keeps its four values, so every existing `where('relationship',
  'child')` behaves exactly as before;
- NULL means "as stated equals as stored", so **no backfill** — every existing
  row is already correct;
- nothing branches on the column. It is display only.

Deliberately **not** `notes`: that column is the user's own free text, and a
system fact parked inside it gets edited away by the person it describes. The
mapping note still goes to `notes` for a human reading the record; the machine
fact has its own column.

| File | Change |
|---|---|
| `database/migrations/2026_08_21_200000_add_stated_relationship_to_family_members_table.php` | the column |
| `app/Models/FamilyMember.php` | `resolveRelationship()` returns `stated`; `getDisplayRelationshipAttribute()`; appended as `display_relationship` |
| `app/Http/Controllers/Api/FamilyMembersController.php` | `store()` and `update()` persist it |
| `app/Agents/CoordinatingAgent.php` | the Fyn path persists it too |
| `resources/js/utils/familyMember.js` | `familyMemberRelationshipLabel()` — the one front-end home |
| `FamilyMembers.vue`, `FamilyInfoStep.vue` | read it; **two divergent formatters deleted** |

There were **four** formatters for this one thing. Two are now gone. The other
two live in Risk and Savings and are raised as **W-0115** rather than reached
into — one of them (`RiskFactorDetailPage`) can still print "Other Dependent"
for a partner flagged as a dependant.

### The open question — CSJ's call, deliberately NOT taken here

**Should `partner` and `step_child` become real enum values?** It looks like the
better end state and it is not what I built, for a reason worth recording:

`step_child` is stored as `child` **today**, on every row Fyn has ever written.
Estate, protection, the plan services, `IntestacyCalculator`,
`getFamilyMembersWithSharing`'s shared-children logic and
`ProfileCompletenessChecker` all query `where('relationship', 'child')`. Give the
column a native `step_child` and every one of them silently stops counting
step-children — a regression far larger than the 500 this fixes, across modules
other batches own, with no test failing to announce it.

That migration is its own work item with its own consumer sweep. Until it
happens, the consequence of the fix as built is:

- a partner displays as **Other Dependent**, with the note "Partner (unmarried)";
- a step child displays as **Child**, with the note "Step child".

Consistent across both paths, and not a 500. But it is not lovely, and if CSJ
wants the relationships shown as chosen, the enum change plus the consumer sweep
is the answer.

### Also noted, not fixed

On EDIT, a step child re-opens the form with **Child** selected, because `child`
is what the dropdown is bound to. `stated_relationship` now holds the truth, so
this is fixable without the enum change — bind the select to
`display_relationship` and map back on save. Left out of this item because it
changes form-binding behaviour for every relationship, not just the aliased two,
and wants its own verification pass.

### Consequence of the fix as built

Storage is unchanged for every existing row and every consumer. Display now reads
"partner" and "step child". The old consequence — "a partner displays as Other
Dependent" — no longer applies on the family surfaces, and survives only on the
two formatters raised as W-0115.

- 2026-08-31 build-lead: **CLOSED — verified against `dev`.**
  `2026_08_21_200000_add_stated_relationship_to_family_members_table` adds the column that holds
  what the user actually chose, so `partner` and `step_child` are stored (as `other_dependent` and
  `child` in the narrow enum) *and* rendered back as what the user said. The 500 on the
  truncated enum is gone, and the migration's own docblock records why storing the enum value
  alone would tell someone their partner is a dependent.
