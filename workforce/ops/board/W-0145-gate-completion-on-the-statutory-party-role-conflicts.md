---
id: W-0145
title: Completion is not blocked when a Lasting Power of Attorney names a certificate provider the statute disqualifies — the will builder blocks its equivalent
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [compliance-lead, product-lead]
status: queued
severity: medium
surfaces: [web]
created: 2026-08-21T20:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0103 (the check this would gate on), W-0102, W-0151, W-0024 (WillDocumentService's severity:error precedent)]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
source: raised by fix-batch-G on team-lead's direction while closing W-0103, 2026-08-21
---

## Intent

W-0103 built `LpaComplianceService::checkPartyRoles()`, which **reports** party-role
conflicts. It does not **block** anything. The will builder's equivalent does:
`WillDocumentService` raises the executor-is-testator conflict at `severity: error` and
refuses completion until it is corrected (W-0024).

So the two instruments diverge on what happens when a party is named in a role they
cannot hold: the will refuses, the Lasting Power of Attorney records the conflict and
saves anyway.

**Gate only the two statutory limbs** — certificate provider named as an attorney on this
instrument (MCA 2005 Sch 1 para 2(6); SI 2007/1253 reg 8(3)(b)) and on another power of
attorney by the same donor (reg 8(3)(c)).

**Do not gate the other three.** Compliance searched for an express prohibition on a
donor naming themselves as their own attorney or certificate provider and **did not find
one**. Refusing to save an arrangement nobody has established is prohibited would be
Fynla asserting a rule that may not exist — the W-0100 overclaim pointing the other way.
Those three stay warnings.

## Acceptance

1. The two statutory conflicts block completion; the other three do not.
2. **Decide what happens to instruments already saved in that state**, and say how many
   there are before deciding. A gate that silently makes an existing record uneditable is
   a trap, not a guard — the user cannot fix what they cannot open.
3. The refusal wording is compliance's, not build-lead's, and composes from
   `LpaCheckPolicy` rather than a second copy.
4. Verified both ways, as W-0024's was: refused while the conflict exists, and saving
   again once corrected.

## Working notes

- 2026-08-21 fix-batch-G: raised on team-lead's direction after W-0103 shipped without a
  gate. **Deliberately left queued** — the inheritance tax work (W-0154) outranks it, and
  team-lead confirmed not blocking completion on the non-statutory conflicts was correct.
