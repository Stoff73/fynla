---
id: W-0272
title: A linked spouse is assessed as childless, and told she can take more investment risk because of it
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0024-cycle4-risk-engine-reach-and-fraction.md
owner: build-lead
status: gated
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T21:00:00Z
claimed: 2026-08-22T21:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0186, F-0019, W-0115]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Raised as D-08 by `peak-earners-c4` in cycle 4.

### The defect

Sarah Jones's `/risk-profile`:

> **Dependants · 0 · Upper-Med** — *"No dependants means you can afford to take more
> investment risk."*

She is the mother of the household's two dependent children. `family_members` 21
(William) and 22 (Charlotte) are both `is_dependent = 1` and both `user_id = 16` —
recorded on David. David's own profile reads **Dependants · 2 · Lower-Med** —
*"Multiple dependants means financial stability is a priority."*

`app/Services/Risk/AutoRiskCalculator.php:277-279` was `where('user_id', …)`-only, in a
household whose `spouse_id` is reciprocal and whose `SpousePermission` is accepted in
both directions.

**The two parents got opposite investment guidance from the same two children**, and
the factor pushed Sarah's risk level **up** on a false premise.

`family_members.user_id` records **who typed the row**, not **whose children they
are**. That is F-0019's reach failure, third mechanism.

## Acceptance

1. Sarah's dependants read **2**, and her narrative stops telling her she can take
   more risk because she has no children.
2. The count changes when the household link changes — a genuinely childless user
   still reads 0.

## Working notes

**DONE.** Prior art outcome **none**: no mechanism answered "who depends on this
user". `LifeCoverReach` (W-0186) answers the analogous question for a joint-life
policy and is the shape copied, but it is about policies and cannot be reused for
people. Built one home: **`app/Services/Shared/DependantsReach.php`**.

Three rules a naive union gets wrong, each implemented and each tested:

1. **The viewer is not their own dependant.** A non-earning spouse flagged
   `is_dependent` on their partner's account is a real dependant *of that partner*;
   reached from the other side, a plain union counts the reader as depending on
   themselves. Rows reached **through** the spouse describing the spouse relationship
   are dropped; the user's own record of their partner is kept.
2. **A child both parents entered is one child.** Identity is `linked_user_id`, else
   name + date of birth — the key `UserProfileService::buildFamilyMembers()` already
   de-duplicates spouse children on. A row with neither keeps its own id, so an
   unidentifiable row is never silently merged.
3. **The link must be LIVE.** `liveSpouseId()`, not raw `spouse_id`, which survives
   the partner deleting their account (CSJ decision D1/D2, 2026-08-19).

**No spouse-permission gate, deliberately.** `hasAcceptedSpousePermission()` governs
disclosing a partner's *financial* data. A household's children are not that, and
`ProfileCompletenessChecker::hasDependants()` already reads the spouse's children to
decide whether the **user's own** profile is complete. Gating here would make this the
one place where a linked parent's children stop being theirs.

Measured after: Sarah **2, Lower-Med**, David unchanged at 2.

**Eight other consumers still ask this question with the old query** — protection
plans, the AI memory retriever, savings, estate, the Fyn prompt builder. Routing them
is **W-0275**; they were left alone for scope discipline.

Tests: 5 feature tests including reach, link-removal, deleted-partner, the
self-dependant case and the duplicate-child case. Mutation-tested: restoring the
`user_id`-only reach turns 3 of the 5 red; removing the de-duplication turns the
duplicate-child case red.

**Browser-verified** on Sarah's own login, through the MFA gate: `/risk-profile`
reads **Dependants 2 · Lower-Med**, *"Multiple dependants means financial stability is
a priority"*, and `/risk-profile/factor/dependants` names **William** and **Charlotte**
— her husband's records, reaching her account. Screenshots
`W-0272-web-sarah-17-risk-profile-after.png`,
`W-0272-web-sarah-17-dependants-william-charlotte.png`.
