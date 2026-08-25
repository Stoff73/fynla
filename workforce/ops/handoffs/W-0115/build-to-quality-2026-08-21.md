# W-0115 — build-lead (`fix-batch-I`) → quality-lead

**Branch document:** `workforce/branches/fixes/F-0009-batch-i-onboarding-spouse.md` §29–35

## Done

**All four relationship formatters converged.** `grep -rn "formatRelationship"
resources/js resources/mobile` returns nothing. `FamilyMembers.vue`,
`FamilyInfoStep.vue`, `SaveAccountModal.vue` and `RiskFactorDetailPage.vue` all read
`familyMemberRelationshipLabel()` / `familyMemberRelationshipTitle()`.

**The Risk half needed the service, not the component.**
`AutoRiskCalculator::calculateDependantsFactor()` hand-builds its rows, so the fix was
to add `stated_relationship` to the select (because `display_relationship` is computed
from it — a partial select would have fallen back to the enum regardless of what the
component did) and to add `display_relationship` alongside `relationship` in the map.
Converging the component alone would have looked like a fix and not been one.

**British spelling, once, on the server.** `FamilyMember::RELATIONSHIP_WORDS` maps
`other_dependent` → `other dependant`, applied in the display accessor so web, `/m` and
native inherit one spelling. The dropdown label in `FamilyMemberFormModal.vue:36` went
with it.

**Evidence:** `FamilyMemberRelationshipAliasTest` 18 passed; Risk regression (Feature/Risk,
Unit/Services/Risk, AutoRiskCalculatorEnhancementTest + family suites) 110 passed, 0
failures; frontend 129 passed across 11 files. `pint` clean.

## Not done, and why

- **No browser verification** — persona-tester closes Rule 14's loop.
- **`persona-passA3` was not told.** The team lead is handling it deliberately: a label
  changing under a tester mid-entry reads as a defect.
- **Screenshot-bearing reports already filed were not amended.** They use the old
  spelling and were accurate when taken.
- Nothing committed, no PR, no deploy.

## What you need that isn't obvious from the artefacts

**Of the four formatters, the one that was RIGHT was the minority.** `SaveAccountModal`
said "Dependant" — the correct British noun — while the family cards, the onboarding step
and the Risk page all said "Dependent". Consolidating on what most call sites did would
have propagated the error into the only place that was correct, and it would have looked
like a tidy-up while doing it. **Majority is a headcount, not a source of truth.** This is
recorded on the model constant as well as in F-0009 §32, because the next person
consolidating something will read it there rather than here.

**The front end deliberately does not know the words.** `familyMemberRelationshipLabel()`
passes the server value through and falls back to the column's own words. There is a test
pinning the fallback returning "other dependent" — **that is not a bug, it is the
contract**: adding a wording map to the client would recreate the copy-in-lockstep failure
this item removed. Please do not "fix" it in review; it is documented in the util for the
same reason.

**The Risk payload sends both values.** `relationship` stays raw for anything that needs
to branch; `display_relationship` is what the client renders. Nothing else consumed
`components.dependants` — checked before changing the shape.

## Assumptions I made

*Stated as assumptions, never as facts.*

- **That "dependant" is a rule compliance fix rather than a copy preference.** The team
  lead ruled it so and CLAUDE.md is explicit, but it does change user-facing text on live
  surfaces and `design-lead` did not see it.
- **That adding `display_relationship` to the risk payload harms no consumer.** I grepped
  and found only the one Vue page. A consumer reading that structure dynamically would not
  show up in a grep.
- **That the client fallback never needs the British form.** True while every real payload
  carries the server value. If a future payload hand-builds an `other_dependent` row
  without it, that row will read "Other Dependent" — and the fix is to make that payload
  carry `display_relationship`, not to teach the client the words.

## Surfaces covered / not covered

- **Web** — covered: family cards, onboarding step, savings beneficiary picker, risk
  factor detail, and the form dropdown.
- **`/m` and iOS** — no relationship-rendering surface on either, checked. They inherit
  the corrected wording automatically if one is ever built, which is the point of putting
  it in the accessor rather than in a client helper.
