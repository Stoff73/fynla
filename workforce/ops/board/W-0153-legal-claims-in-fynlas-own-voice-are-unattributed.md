---
id: W-0153
title: A legal rule stated in Fynla's own unattributed voice on a will sits beside an attributed one on a power of attorney, and nothing makes the difference visible
mission: M-0002-persona-fidelity
branch: null
owner: build-lead
reviewers: [compliance-lead, design-lead]
status: queued
claimed_by: null
severity: medium
surfaces: [web]
created: 2026-08-21T19:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: ["W-0024 executor-is-testator gate — the approved copy this concerns", "W-0101 will renderer, acceptance 3 — the willDocumentRenderer.js:297 disclaimer goes THERE not here", "W-0102/W-0103 the attributed equivalent", "workforce/ops/reports/2026-08-21-lpa-claims-rulings.md", "F-0003-batch-b-estate-wills"]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 04-voice]
source: found by compliance-lead while scanning for the W-0024 divergence after ruling the seven Lasting Power of Attorney items, 2026-08-21; routed to team-lead because compliance holds no ID block
---

## Intent

`WillDocumentService::EXECUTOR_IS_TESTATOR_MESSAGE` opens **"A will cannot appoint its own
testator as executor"** — a rule with no source, in Fynla's own voice. The Lasting Power
of Attorney equivalent ruled the same day states the same **class** of thing
**attributed and paragraph-referenced**, so a reader can check it. Both are user-facing;
only one is checkable.

**The divergence is not the defect. The absence of anything that would have surfaced it
is.** Nothing in the codebase or the trunk requires a legal statement in user-facing copy
to carry its source, so the two instruments diverged silently — and the difference became
visible only because the same agent happened to write wording for both in one day.

## The honest complication — carry it, do not hide it

`EXECUTOR_IS_TESTATOR_MESSAGE` was **approved verbatim under W-0024, before the
act-not-object test existed.** Reopening approved copy has its own cost. So there are two
defensible answers and the item must not pretend otherwise:

1. **Attribute it and re-approve** — the copy carries its source like the powers-of-attorney
   equivalent.
2. **Record the divergence as accepted, with a reason** — and say what the reason is.

**Both are defensible. The current state — divergent and invisible — is not.**

## Scanned, not assumed

Two other candidates were checked and cleared:

- `WillTypePolicy.php:51` — correctly framed as a **product** statement ("outside what this
  tool is designed to do"), not a legal claim. That is the approved W-0019 copy and it
  passes.
- `willDocumentRenderer.js:297` — the will's "only legally valid once properly signed and
  witnessed" disclaimer. **This goes to W-0101 acceptance 3, NOT here.** Raising it
  separately would create the parallel mechanism this family exists to remove.

## Explicitly out of scope

**A lint rule is not proposed and should not be built.** A regex for legal-sounding copy
would fire on the correct cases too. **The failure here was judgement, not detection** —
compliance's own words — and a detector that cries wolf on attributed statements would
train reviewers to dismiss it.

## Acceptance

- [ ] CSJ or the reviewers pick answer 1 or answer 2 for `EXECUTOR_IS_TESTATOR_MESSAGE`.
- [ ] Whichever is chosen, the **reason is written down where the next person writing
      legal-sounding copy will hit it** — not only in this item.
- [ ] If answer 1: the copy is re-approved, not silently edited. It was approved verbatim
      once already.
- [ ] No lint rule.

## Working notes

(append-only)

- 2026-08-21 team-lead: filed on compliance-lead's draft, in its framing, from the
  coordinator block. Compliance **deliberately did not touch `F-0003`'s files or copy
  mid-run** — correct: `fix-batch-B` is retired but its work is uncommitted and a tester
  is verifying that surface live.
- 2026-08-21 team-lead: **this item is the argument for the §7.3 amendment now with CSJ**
  (extend the no-approval rule to bind the product, not only the agents). The reason the
  two instruments diverged is that the rule constraining what may be asserted was scoped
  to agents, while the application asserts things in its own voice with nothing checking
  it. If CSJ approves that amendment, answer 1 follows almost automatically and this item
  gets easier. **Do not block on it** — the divergence stands either way.
