---
id: W-0344
title: A spouse link claimed from one side only discloses the other account's financial records
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T23:55:00Z
claimed: 2026-08-22T23:55:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0186, W-0278, W-0272]
prior_art_outcome: route
constitution_refs: [05-perimeter, 03-hard-nos, 07-quality-bar]
---

## Intent

Split out of W-0278 at team-lead's direction so it is findable by someone auditing
**authorisation**, not buried in an estate item. Found by reading `LifeCoverReach`'s four
spouse-link states adversarially rather than fixing the two that were reported.

### What an attacker needed

**An account, and a link naming the target.** No deletion, no elevated role, nothing on
the target's side. `users.spouse_id` is a column written *about* the account holder, and
every reader that trusted it treated "I say N is my spouse" as "N's records are mine to
read".

W-0278 needed a **deleted** partner. This needs nothing but the link, which is why it is
the more serious of the two and why it is filed separately.

### What it exposed in the fixed reader

`LifeCoverReach::policiesCovering()` returned the named account's joint-life policies.
Rendered through `LifeInsurancePolicyResource:28-48` that is **sum assured, provider,
policy number, premium amount and frequency, in-trust status, and the free-text
`beneficiaries` field** — commonly the couple's children.

### Fixed here, for this reader only

`LifeCoverReach::coveringSpouse()` requires `User::hasReciprocalSpouseLink()`, declared
in the model as *"THE single authorization rule"* for an attached id granting the linked
account visibility of a record. Measured on the dev database before changing it: **12
live reciprocal links, 0 one-sided, 0 dead** — no existing user loses cover.

## Acceptance

1. `LifeCoverReach` requires a live **reciprocal** link. — DONE.
2. A test asserts a one-sided link discloses nothing, for the per-life reader and the
   household total separately. — DONE, both cases, mutation-confirmed.
3. **The census.** — Delivered under **W-0350**: 53 consumer sites, of which only three
   in the whole application use the reciprocity rule.
4. Browser-verified. — PENDING.

## Working notes

(append-only)

- 2026-08-22 — **The precondition is EASIER than stated above, and the fix is therefore
  a speed bump rather than closure.** `SpouseLinkingService::linkExistingSpouse()` writes
  **both** rows and forges `accepted` permissions on both sides from one party's request.
  So an attacker does not need to defeat reciprocity — the server establishes it for
  them. **W-0347**, critical. Reciprocity remains the right rule; it is the write path
  underneath it that is broken.
- 2026-08-22 — Also fixed: `otherLifeAssured()`'s non-owner branch was an **ungated**
  `User::find($policy->user_id)`, safe only because the one path delivering a non-owned
  policy is itself gated. An implicit invariant is not an enforced one; it is enforced now.
- 2026-08-23 — Browser cannot verify the blocked states: the persona has no deleted,
  one-sided or unreciprocated link, so there is no screen to look at. Covered by tests and
  mutations M2/M3. Moving to `handoff` with that stated.

- 2026-08-30 build-lead: **CLOSED — fixed, and re-verified 2026-08-29 while doing W-0350.**
  `LifeCoverReach` requires `User::hasReciprocalSpouseLink()`, now through the promoted
  `reciprocalLiveSpouse()`, and the write path underneath it was closed by W-0347 so the
  reciprocity it tests can no longer be forged. Covered by
  `tests/Feature/Api/SpouseLinkConsentTest.php` and the two suites added under W-0350.

  **This item is also the proof that "no commit names it" is a weak signal.** It appeared
  in the 2026-08-30 evidence audit as untouched, having been fixed twice.