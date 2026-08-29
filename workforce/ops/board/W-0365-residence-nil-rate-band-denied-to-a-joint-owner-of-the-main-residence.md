---
id: W-0365
title: Residence Nil Rate Band is denied to a user who is only the joint owner of the main residence, against IHTA 1984 s8H(2), while their share is still counted into the cap
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
closed: 2026-08-29
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333.

`IHTCalculationService:1363-1385`:

```php
$userHasMainRes = $this->propertyStore
    ->forUserByType($user, 'main_residence')
    ->where('user_id', $user->id)          // primary owner only
    ->isNotEmpty();
```

The docblock says this is deliberate — *"so the RNRB-eligibility check matches the
pre-PR-5a semantics"* — **but it is wrong on the statute.** IHTA 1984 **s8H(2)**
defines a qualifying residential interest as an interest in a dwelling-house which has
been the person's residence. A beneficial co-owner recorded as `joint_owner_id` **has
such an interest.** Nothing in ss8E–8H requires being the primary named owner of a
database row.

**The file contradicts itself.** `sumMainResidenceNetShare():1436-1447` uses the
**joint-aware** reader and counts that same user's share into the s8E(2) cap. So their
share of the home is in the estate **and** in the cap, and grants no entitlement.

Does not bite a pooled married couple — either spouse being primary passes the check
for both. It bites **single, widowed, and data-sharing-off users who are the secondary
owner**: the band drops to £0, up to **£175,000 × 40% = £70,000 of overstated tax**,
and the user is shown a message telling them they do not own a main residence when
they do.

**A comment claiming a behaviour is deliberate is not evidence that it is correct** —
compare W-0228, where the docblock stated the opposite of the law and the code matched
the docblock, so every reviewer checking one against the other passed it.

## Acceptance

1. Eligibility follows a qualifying residential interest, not primary ownership of the
   row.
2. Eligibility and the s8E(2) cap read the same set of properties — **today one is
   joint-aware and the other is not**.
3. Fixtures for single, widowed and sharing-off secondary owners; a married pooled
   couple **cannot** exercise it.
4. **`tax-compliance-reviewer` on the fix.**

## Resolution — 2026-08-24

The primary-owner filter is gone from `hasMainResidence()`. A beneficial co-owner holds
a qualifying residential interest under IHTA 1984 s8H(2); nothing in ss8E–8H asks who
typed the record in. The contradiction the item names is closed — the eligibility check
and `sumMainResidenceNetShare()` now read the same ownership.

Guard: `RnrbJointOwnerEligibilityTest` — a co-owner gets the same band as the recorder
of the same home, and someone with no residential interest still gets none.
**Mutation-checked**: restoring the filter reds it. Estate unit 353 green.

Note for whoever writes the next RNRB fixture: the band also requires a lineal
descendant (s8E), so a household with no `child` family member scores £0 for a reason
that has nothing to do with the interest — which is how the first version of this test
failed for the wrong reason.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`gated`.

- **Delivered by:** Stoff73
- **Evidence:** merged in #714; commit `f4ecbcea7` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
