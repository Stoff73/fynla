---
id: W-0333
title: The projected estate carries £177,000 belonging to a third party, and the same response contradicts itself about it
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T23:25:00Z
claimed: 2026-08-23T00:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [F-0019, W-0175, CrossModuleAssetAggregator::calculatePropertyTotal]
prior_art_outcome: route
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

Found while fixing W-0331. **This is the failure W-0280 §1 was reaching for, and it
is live, larger, and in property rather than investments.**

`IHTCalculationService::projectProperties():807-818` sums primary-owned property at
**100%**, deliberately — a comment from `5278a2457` says so, added to stop a
double count and leaving this one behind. Property 20, Victoria Mill: recorded
`tenants_in_common`, David 40%, `joint_owner_id` NULL. Mike Barrett holds the other
60% and has no account. F-0019 §3's *"£177,000 that must never appear"*.

The same API response disagrees with itself:

| | value |
|---|---|
| headline estate, property component (`gatherUserAssets` × share) | **£1,393,000** |
| projection base (`projectProperties`) | **£1,570,000** |
| difference | **£177,000** — a third party's money |

At 3% over the 36-year second-death horizon: projected property **£4,550,296.97 →
£4,037,301.71**. Projected taxable estate falls £512,995.26. **Projected Inheritance
Tax liability falls £205,198.10** (£2,851,349.69 → £2,646,151.59), identically on
David (16) and Sarah (17).

Note `tenants_in_common` is **property-only** —
`investment_accounts.ownership_type` is `enum('individual','joint','trust')` — so
this shape reaches an estate through property, which is why it is here and not in
W-0331.

## Acceptance

1. The projection base equals the headline's property component, to the penny.
2. A third party's share is credited to **nobody**, never to the spouse.
3. The Manchester third-party constraint from F-0019 §3 still holds on the value
   side: David £118,000, Sarah £0, and £177,000 appears nowhere.
4. Before/after stated for both accounts, with the tax-compliance review recorded.

## Working notes

**Not landed.** It reverses a deliberate 2026 decision and moves a real tax figure by
more than the item that uncovered it, so it is flagged to team-lead and awaiting a
decision plus the tax-compliance review team-lead named. The fix is the same
one-line shape as W-0331 — route to
`CrossModuleAssetAggregator::calculatePropertyTotal($userId)` per pooled member.

## Working notes — build-lead, 2026-08-23

**DONE.** Team-lead authorised; tax-compliance review passed before landing.

`projectProperties()` routed to `CrossModuleAssetAggregator::calculatePropertyTotal`
per pooled member. Measured on the persona, both accounts:

| Figure | Before | After |
|---|---|---|
| projected properties | £4,550,296.97 | **£4,037,301.71** |
| projected Inheritance Tax liability | £2,851,349.69 | **£2,646,151.58** |

**A user's projected tax liability falls by £205,198.11.** Predicted £205,198.10;
the compliance review re-derived both sides and found the **prediction** was the
imprecise figure — `0.4 × 512,995.26405647` rounds to `…11`, `0.4 × 512,995.26`
rounds to `…10`. The measurement is the accurate one. Every other component
reconstructed bit-identical: nothing moved for any other reason.

**This COMPLETES `5278a2457`, it does not reverse it.** That commit stopped a joint
property being counted twice, by taking primary rows at 100% — which is how the third
party got in. The aggregator prevents both, and the commit named that option itself
when it left `EstateAssetAggregatorService` alone *"because that consumer applies
calculateUserShare on each row"*. The right answer was in the commit that introduced
the defect; it was simply never applied here.

**Tax grounds confirmed:** IHTA 1984 s5(1) — the estate is property to which the
deceased was beneficially entitled, and tenants in common hold distinct undivided
shares. The stranger's 60% was never David's, and `calculateUserShare` credits it to
nobody rather than to the spouse.

Guarded by `tests/Unit/Services/Estate/IHTProjectionOwnershipTest.php` and by the
rewritten case in `tests/Feature/Stores/PropertyReadConsumerParityTest.php`.
Mutation-tested against BOTH historical states — the May defect and the pre-May
double count — and each turns the suite red in a different place.

- 2026-08-31 build-lead: **CLOSED — verified against `dev`, and it completes `5278a2457` rather
  than reversing it.** `EstateProjectionService:363-385` sets out the three approaches and the two
  failure modes explicitly — `forUser` on both sides double-counts a joint property, `user_id` at
  100% lets a third party in, **reach + share per member does neither** — and takes the third.
  Mike Barrett's £177,000 is out of the household's projected estate. Pinned by
  `tests/Unit/Services/Estate/IHTProjectionOwnershipTest.php:331` and
  `tests/Feature/Stores/PropertyReadConsumerParityTest.php`.
