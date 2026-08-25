---
id: W-0345
title: Product call — should a refused spouse permission hide the joint-life policy from the person it insures
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: done
severity: low
surfaces: [web, m, ios]
created: 2026-08-22T23:35:00Z
claimed: 2026-08-25T17:00:00Z
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0186, W-0272, W-0278]
prior_art_outcome: none
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

W-0278 asked for `LifeCoverReach` to be read adversarially across four spouse-link
states. Three are now mechanically decided (live+reciprocal reaches; deleted and
one-sided do not). **The fourth is a product question, and it was decided by the fixing
agent by default rather than by CSJ.**

**Current behaviour after F-0027: a refused or never-accepted `SpousePermission` does
NOT stop a joint-life policy reaching the life it insures.** The reasoning, which should
be checked rather than assumed:

1. `hasAcceptedSpousePermission()` **cannot express a refusal.** Its automatic branch
   returns `true` for any married, reciprocally linked, live pair whatever the row says.
   Gating on it would not have blocked this state.
2. It would **re-open W-0186 for unmarried couples** — it requires
   `marital_status === 'married'` on both accounts, so a linked cohabiting couple would
   have the joint-life policy hidden from the person it insures.
3. `joint_life` is itself the disclosure marker: a flag the owner set on their own
   record saying this contract covers two lives. A single-life policy never reaches, in
   any state.

`DependantsReach` makes the opposite call for children and says why: the permission gate
governs disclosing a partner's **financial** data. A sum assured is financial data, so
the two readers are not obviously consistent — the distinction drawn here is that the
policy insures the reader's **own life**.

## Acceptance

1. [x] CSJ decides whether an explicit refusal should suppress the reach. — **DECIDED
   by Brett, 2026-08-25, on CSJ's delegated authority: NO.** A refusal does not
   suppress it. Behaviour unchanged; the reasoning corrected.
2. [n/a] If yes, the instrument is decided too — `hasAcceptedSpousePermission()` is not it
   (reason 1), and W-0346 must land first so a refusal is representable at all. —
   **Both halves of this are now false.** `hasAcceptedSpousePermission()` IS a working
   instrument, and a refusal is already representable, so W-0346 was never a blocker.
3. [x] `tests/Feature/Protection/LifeCoverReachSpouseLinkStatesTest.php` holds the two cases
   that pin the current answer; a reversal lands there. — kept, with the stale reasoning
   in the comment corrected. 16 tests / 43 assertions green.

## Working notes

- 2026-08-25 (Brett, on CSJ's delegated authority): **DECIDED — a refused permission does
  NOT suppress a joint-life policy from the life it insures. Behaviour unchanged.**

  **Two of this item's three reasons were true when written and are false now.** W-0347
  landed in between:

  1. *"`hasAcceptedSpousePermission()` cannot express a refusal."* — it can.
     `User.php:845` reads the row and returns `$permission->status === 'accepted'`, so a
     `rejected` row returns **false**. The fail-open default now applies only where
     there is no row at all, and W-0347's re-ask migration gave every reciprocal pair
     one.
  2. *"Its `married` requirement would hide a linked unmarried couple's policy."* —
     there is **no `marital_status` check anywhere in that chain.** `coveringSpouse()`
     gates on a live spouse and a reciprocal link, nothing else.

  So the instrument existed and the objection to using it did not. **The decision was
  therefore made on its merits rather than on a constraint**, which is the opposite of
  the position the item describes.

  **The reason that stands.** The permission gate governs disclosure of a partner's
  FINANCIAL position. A joint-life policy is a fact about the reader's **own life**,
  which the owner affirmatively declared by setting `joint_life`. You should not be able
  to hide from someone that their life is insured — and the failure mode of the opposite
  call is worse: a person insured and never told, by an application holding the fact.
  If a couple are separating, the person most likely to refuse a permission is the one
  most likely to need to know.

  **What actually crosses the boundary — checked, not assumed.** `policiesCovering()`
  returns full models and `ProtectionController:94` serialises them through
  `LifeInsurancePolicyResource`. W-0383 already withholds `policy_number` and
  `beneficiaries` from the other life assured, on the stated principle that *"reaching a
  policy answers 'am I covered' — it is not a licence to read the whole contract."*
  Everything else crosses: provider, sum assured, **premium amount, frequency and
  annualised premium**, policy type, dates, term, in-trust and mortgage-protection flags.

  **Considered and NOT taken (option A′):** extending the W-0383 line to withhold the
  premium fields. A premium is money leaving the owner's account rather than a fact
  about the reader's life, and it is the clearest instance of the "partner's financial
  data" the gate is said to govern. Declined as part of decision A. **Recorded because
  W-0383 drew that line without considering the premium at all** — a compliance sweep
  should find this question already asked rather than discover it cold.

  **Perimeter caveat, unresolved.** This item carries `constitution_refs: [05-perimeter]`.
  Whether disclosing a premium to a non-consenting spouse has a data-protection
  dimension is `compliance-lead`'s call. The decision above is a product judgement and
  could be overridden on those grounds.

  **`DependantsReach` is not inconsistent in behaviour** — it applies no permission gate
  either. What it does is state a *principle* ("the gate governs a partner's financial
  data") that the premium disclosure sits awkwardly against. The item's "makes the
  opposite call" framing overstates it.

  No code changed. Comment corrections only, in the item and in the pinning test.
