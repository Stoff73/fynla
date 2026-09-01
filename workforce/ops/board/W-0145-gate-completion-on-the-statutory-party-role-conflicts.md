---
id: W-0145
title: Completion is not blocked when a Lasting Power of Attorney names a certificate provider the statute disqualifies — the will builder blocks its equivalent
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [compliance-lead, product-lead]
status: done
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

---

## Closed 2026-08-31 — the two statutory limbs now refuse

**Root cause.** The classification was never the problem — `LpaComplianceService`
has raised both limbs at `fail` since W-0102 and W-0151. Nothing consulted them.
`LpaService::createLpa()` and `updateLpa()` set `completed_at` from whatever status
the request asked for (`:53`, `:84` before this change), so the checks reported a
statutory disqualification into a payload while the same call saved the instrument.

**Acceptance 1 — the two block, the other three do not.**
`app/Services/Estate/LpaCheckPolicy.php:120-140` names the two blocking keys and
carries the reason the other three stay warnings; `LpaService.php:108-140`
(`refuseDisqualifiedCompletion`) is called on both write paths, inside the
transaction, so a refusal rolls the write back.

**Acceptance 2 — measured before deciding.** 4 completed/registered instruments
exist in the local database and **0** carry either conflict, so no existing record
is trapped. The gate is written so it could not trap one anyway: it refuses only the
transition INTO `completed`/`registered`, and the instrument stays saveable as a
draft. Proven by `it('lets the user keep working on a draft that carries the
conflict')`.

**Acceptance 3 — the wording composes from `LpaCheckPolicy`.**
`LpaCheckPolicy::completionRefusal()` builds the refusal from the failing check's own
title and description plus the existing `REFERRAL` constant. No second copy of the
statutory sentences exists.

**Acceptance 4 — verified both ways**, as W-0024's was.
`tests/Feature/Estate/LpaControllerTest.php` — 5 new tests: refused while the conflict
stands (422 on `status`, nothing written), saved once the name is corrected, draft
still editable, a donor-as-own-attorney warning NOT refused, and the same refusal on
the update path that completes an existing draft.

**Regression:** 450 passed across the LPA controller, both estate unit suites and the
Fyn `create_power_of_attorney` tool.

## Not done, and deliberate

`markAsRegistered()` is NOT gated (`LpaService.php:158-170` carries the reason at the
line). It records that the Office of the Public Guardian *has* registered the
instrument — a fact about the world, not a claim Fynla is making — and refusing it
would leave a user unable to record what has already happened.

**Surfaces:** the gate is server-side on the shared write path, so web, `/m` and Fyn
all hit it. Only web has an LPA form today (see W-0110), so there is no second
frontend to mirror it into.
