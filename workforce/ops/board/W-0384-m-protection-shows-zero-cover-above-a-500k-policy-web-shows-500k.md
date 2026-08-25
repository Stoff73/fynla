---
id: W-0384
title: /m protection shows £0 cover directly above the £500,000 policy it is counting, and web shows £500,000 for the same user at the same moment
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0028-cycle4-m-protection-gap-reach.md
owner: build-lead (fix-cycle4-mprotection)
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: 2026-08-23T03:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0186, W-0341, W-0342, W-0350]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

**Found in the browser**, on `/m/app/protection` signed in as Sarah Jones (17), during the
verification pass for W-0341/W-0342. Not predicted by any code reading.

### What is on screen

> **Total lump-sum cover**
> **£0**
> Across **1** policy.
>
> COVERAGE GAPS
> Debt protection **HIGH** — £122,500 short — £0 of £122,500
> Final expenses **HIGH** — £7,500 short — £0 of £7,500
> Income protection **HIGH** — £72,000 a year short — £0 of £72,000 p.a.
>
> POLICIES
> Vitality · Life Insurance · Joint life with David Jones — recorded on their account
> **£500,000**

**A £0 total, an accurate count of 1, and the £500,000 policy it is counting, on one
card.** Three high-severity gaps are then computed against that £0.

### The same user, the same moment, on web

`/protection` desktop, Sarah, signed in through the MFA gate:

> Total Life Insurance **£500,000** · 1. Allocated to cover debts £122,500 ·
> 2. Excess for David Jones's income £377,500
> **Debt Protection: none — Shortfall £0**

**`/m` says she is £122,500 short on debt protection. Web says she is not short at all.**
Same backend, same instant, opposite answers.

### Why — two mechanisms behind one card

`resources/mobile/views/modules/Protection.vue`:

```js
totalPolicyCount() { return this.policies.length; },            // :182  reach-aware list
totalLumpSumCover() {                                            // :184
  return Number(this.coverageGaps?.totals?.cover || 0);          // a DIFFERENT mechanism
},
```

The count comes from the reach-aware policy list that W-0186 fixed. The total comes from
`coverage_gaps.totals.cover`, which W-0186 never reached:

`app/Services/Protection/ProtectionGapPresentationService.php:32-40`

```php
$coverage = $this->gapAnalyzer->calculateTotalCoverage(
    $user->lifeInsurancePolicies,     // ← the plain user_id hasMany
    ...
);
```

**`$user->lifeInsurancePolicies` is the exact relation `LifeCoverReach`'s own docblock
names as the one every consumer used and which stopped at the account that typed the
policy in.**

**Measured, not inferred:**

```
relation $user->lifeInsurancePolicies count = 0
reach    policiesCovering(Sarah)      count = 1
total_coverage AS SHIPPED = £0
total_coverage WITH REACH = £500,000
```

**This is W-0186's own defect shape** — a total and its count coming from different places
and disagreeing on one card — reproduced in a second location, and this time the total is
the wrong half.

### Why nobody saw it

**It is invisible from David's account**: he owns the policy, so the `user_id` relation
finds it and `/m` shows him £700,000, correctly. The defect only exists on the account
that does not hold the contract — and that account is the one nobody tests from.

## Acceptance

1. `ProtectionGapPresentationService:32-40` passes `LifeCoverReach::policiesCovering($user)`
   in place of `$user->lifeInsurancePolicies`. **Critical illness must stay the plain
   relation** — `critical_illness_policies` has no `joint_life` column, so there is
   nothing to reach with (verified against the schema in W-0341).
2. `/m` and web agree for Sarah: £500,000 of lump-sum cover, and the debt-protection gap
   closes on both.
3. **A test with an asymmetric fixture**, because £500,000 is both the correct answer and
   the answer David already gets — the two hypotheses must land on different numbers, or
   the test cannot fail (`tests/CLAUDE.md` §4, Collision).
4. Verified from **the non-owner's account**, on web and `/m`. From the owner's it passes
   either way.

---

## Outcome — 2026-08-23, build-lead (`fix-cycle4-mprotection`)

**FIXED and browser-verified from the non-owning account on both surfaces.**

`ProtectionGapPresentationService` routed to `LifeCoverReach::policiesCovering()`. **One
read now feeds BOTH the total and `relevant_policies`** — criterion 1 named only the total,
but `:42` passed the same broken relation to `lifePolicyReferences()`, so every category
listed **zero** policies for Sarah on `/m` and iOS. Fixing only the total would have shipped
"£500,000 of cover" above a list of no policies.

| | Sarah (17) before | after | David (16) before | after |
|---|---|---|---|---|
| `totals.cover` | £0 | **£500,000** | £700,000 | £700,000 |
| `totals.shortfall` | £130,000 | **£0** | £0 | £0 |
| debt protection | HIGH £122,500 | **covered** | covered | covered |
| final expenses | HIGH £7,500 | **covered** | covered | covered |
| `relevant_policies` | 0 | **1** | 1 | 1 |

**Criterion 2 — met.** `/m` reads £500,000 and web reads £500,000 for Sarah, verified in the
browser with identity from `GET /api/auth/user` on each surface's own token (they are
separate: desktop 115, `/m` 116).

**Criterion 3 — met.** Asymmetric fixture, non-owner holds £120,000 of her own; the four
hypotheses land on £620,000 / £120,000 / £820,000 / £820,000. Mutation-tested three ways;
**the owner control stayed green under every mutation.**

**Criterion 4 — met.** Verified from Sarah's account on web AND `/m`; David checked on both
as the control.

**Correction to this item's own framing: two of the three HIGH gaps were false, not three.**
The income-protection £72,000 is **genuine** — she holds no income-protection policy and that
table has nothing to reach with. It correctly survives, and a test pins it.

**iOS decodes the same payload and the key shape is unchanged, but was not built or looked
at — not claimed.**

Branch doc: `workforce/branches/fixes/F-0028-cycle4-m-protection-gap-reach.md`
Handoff: `workforce/ops/handoffs/W-0384/build-lead-to-quality-lead-2026-08-23.md`
