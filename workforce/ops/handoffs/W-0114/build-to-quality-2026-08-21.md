# W-0114 — build-lead (`fix-batch-I`) → quality-lead

**Branch document:** `workforce/branches/fixes/F-0009-batch-i-onboarding-spouse.md` §12–21

## Done

`family_members.relationship` is `enum('spouse','child','parent','other_dependent')`.
The family form offers six options. Under `strict => true` the two extras do not degrade
— they raise, so **`partner` and `step_child` both returned HTTP 500** with
`SQLSTATE[01000] 1265 Data truncated` and the raw SQL in the response. Verified through
the real endpoint, not inferred.

That form is the same component on `/settings/family` **and** onboarding step 2, so a
step-parent adding their step-child got a database error on their first run through the
product, from an option the product offered them.

The mapping already existed — inline in `CoordinatingAgent::handleCreateFamilyMember`
alone — so Fyn could add a step-child and the form could not. It now lives on
`FamilyMember` (`RELATIONSHIP_ALIASES`, `resolveRelationship()`,
`composeRelationshipNotes()`), because the model owns the column, which makes it the one
place a new writer cannot miss. `grep -rn "'step_child' =>" app/` returns exactly one
line.

**10 tests pass**, including one asserting both paths land an identical row, and one
pinning the enum itself so widening the column fails loudly instead of silently
invalidating the translation.

## Not done, and why

- **No browser verification** — persona-tester.
- **I did NOT add `partner` and `step_child` to the enum**, and this is the decision to
  scrutinise. See below.
- Nothing committed, no PR, no deploy. No migration written.

## What you need that isn't obvious from the artefacts

**Why no migration.** `step_child` is stored as `child` **today**, on every row Fyn has
ever written. Estate, protection, the plan services, `IntestacyCalculator`,
`getFamilyMembersWithSharing`'s shared-children logic and `ProfileCompletenessChecker`
all query `where('relationship', 'child')`. Give the column a native `step_child` and
every one of them silently stops counting step-children — a regression far larger than
the 500 this fixes, across modules other batches own, with no test failing to announce
it. That is a migration plus a consumer sweep, not something to slip into a bug-fix
batch.

**The consequence, stated plainly rather than sold as a clean win:** a partner now
displays as **Other Dependent** and a step child as **Child**, each with an explanatory
note. Consistent across both paths, and not a 500. But it is not what the user picked,
and CSJ may prefer the enum change. It is on the item as the open question.

**Also noted, not fixed:** on EDIT, a step child re-opens the form with **Child**
selected, because `child` is what is stored. Pre-existing on the Fyn path and not
fixable without the enum decision.

## Assumptions I made

- **That coercing `partner` to `other_dependent` is acceptable.** It is what the Fyn
  path has shipped for months, so it is not new — but "Other Dependent" is a poor label
  for someone's partner, and I am assuming consistency-plus-a-note beats a 500 until CSJ
  rules on the enum.
- **That the note is a sufficient record of what the user chose.** Nothing reads it
  programmatically; it is display text. If anything ever needs to *know* a row is a
  step-child, the note is not a data structure and the enum change becomes necessary.
- **That no consumer breaks on a partner arriving as `other_dependent`.** It could not
  arrive at all before, so this is a new value reaching those code paths from the HTTP
  side. `is_dependent` is a separate column and is not forced true, which is the one I
  checked.

## Surfaces covered / not covered

- **Web** — covered; one modal, both surfaces, plus the update path.
- **`/m` and iOS** — no family-member form on either. Both consume `family_members` only
  through the dependants filter (`is_dependent`), which is unchanged. The fix is
  server-side, so if a form is ever built there it inherits it.
