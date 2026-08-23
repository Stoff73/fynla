---
id: W-0278
title: LifeCoverReach keeps reading a deleted partner's policies, because it follows spouse_id rather than the live spouse
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T22:10:00Z
claimed: 2026-08-22T23:20:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0186, W-0272]
prior_art_outcome: route
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

Found while building `DependantsReach` (W-0272) by reading `LifeCoverReach` as its
model. Not raised by any tester — found by comparing two implementations of one
pattern.

`app/Services/Protection/LifeCoverReach.php` reads `$user->spouse_id` directly in
`policiesCovering()` and `householdCoverInTrust()`.

**`users.spouse_id` survives the partner deleting their account.** It is retained
deliberately for regulatory purposes, and `User::liveSpouse()` exists precisely so
read paths stop at a closed account (CSJ decision D1/D2, 2026-08-19 — retain the rows,
ignore them at read time; measured on csjones, three survivors).

Consequences while the link is dead:

- A deleted partner's joint-life policies keep appearing in the surviving user's
  protection analysis, as cover they may no longer have.
- `householdCoverInTrust()` keeps adding the deleted partner's in-trust sum assured to
  the household total on the estate plan.

The same class of exposure `UserProfileService` was already corrected for: *"a deleted
spouse's records are kept for regulatory purposes but must stop being visible to their
partner."*

## Acceptance

1. Both methods key on `liveSpouseId()` / `liveSpouse()`.
2. A test deletes the partner account and asserts the cover figure **and the policy
   count** both drop — asserting one alone repeats W-0186's own defect, where a total
   and its count came from different places.

## Working notes

(append-only)

- 2026-08-22 — Severity raised `medium` → `high`. This is a disclosure of another
  person's financial position, not a stale figure: it is the same class as
  `LiabilityCard.vue` earlier this cycle, which showed a co-owner the other party's
  share and was raised on that basis.
- 2026-08-22 — Fixed. Both methods, plus `otherLifeAssured()`, now pass through one
  private gate, `LifeCoverReach::coveringSpouse()`, composed from `User::liveSpouse()`
  and `User::hasReciprocalSpouseLink()`.
- 2026-08-22 — **A third state was found while reading it adversarially, and it is worse
  than the one raised: a link claimed from ONE SIDE ONLY also disclosed.** `spouse_id`
  is a claim its own account holder writes, unilaterally — any user could name another
  account as their spouse and read back that person's joint-life sum assured. It needs
  no deletion, just an unreciprocated write. `hasReciprocalSpouseLink()` is declared in
  the model as *"THE single authorization rule"* for an attached id granting visibility,
  so it is called rather than re-derived. Blocked now.
- 2026-08-22 — The fourth state, a refused or never-accepted `SpousePermission`, still
  reaches. Decided, argued in `F-0027` §4, raised for CSJ as **W-0345**. Related:
  **W-0346** — the status enum has no `revoked` member, so "revoked" was not a state
  that could be built or tested.
- 2026-08-22 — Acceptance criterion 2 (amount AND count drop together) is asserted
  directly, and mutation M3 confirms it reddens when the gate is removed. Also closed a
  lazy-loading hazard: `otherLifeAssured()` reached for `$viewer->spouse` under
  `preventLazyLoading()`. **Not a live crash** — its only call site passes
  `$request->user()` — but it would throw the first time a caller passed a user loaded
  as part of a collection.
- 2026-08-22 — Browser verification PENDING; the browser is queued behind two agents.
- 2026-08-23 — **Browser-verified.** The reach itself is visible on both accounts:
  Sarah's `/m` protection lists the policy as *"Joint life with David Jones — recorded on
  their account"*, David's as *"Joint life with Sarah Jones"*. The three blocked link
  states are covered by tests, not by the browser — **the persona has none of them**, so
  no screen can show them. Moving to `handoff`.
