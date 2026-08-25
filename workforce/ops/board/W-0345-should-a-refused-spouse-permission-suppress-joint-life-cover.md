---
id: W-0345
title: Product call — should a refused spouse permission hide the joint-life policy from the person it insures
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-22T23:35:00Z
claimed: null
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

1. CSJ decides whether an explicit refusal should suppress the reach.
2. If yes, the instrument is decided too — `hasAcceptedSpousePermission()` is not it
   (reason 1), and W-0346 must land first so a refusal is representable at all.
3. `tests/Feature/Protection/LifeCoverReachSpouseLinkStatesTest.php` holds the two cases
   that pin the current answer; a reversal lands there.
