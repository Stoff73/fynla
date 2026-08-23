---
id: W-0382
title: The trust warning told the other life assured to phone an insurer she has no contract with
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0027-cycle4-life-cover-reach.md
owner: build-lead
status: handoff
severity: medium
surfaces: [web, m, ios]
created: 2026-08-23T00:40:00Z
claimed: 2026-08-23T00:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-23
prior_art_found: [W-0186, W-0341, W-0342]
prior_art_outcome: extend
constitution_refs: [07-quality-bar, 04-voice]
---

## Intent

**A hole opened by its own fix.** Routing `EstateAgent:109` to `LifeCoverReach` (W-0342)
made `LifeCoverCalculator::assessExistingPolicies()` reachable, for the first time, with
a policy the reader does not hold. Its `not_in_trust` branch then said:

> *"Vitality (£500,000) is not written in trust. Without trust placement, the policy
> proceeds will form part of **your** taxable estate and may be subject to Inheritance
> Tax. **Contact your provider to place this policy in trust.**"*

Both bolded parts are wrong for the other life assured. The proceeds fall into the
**policyholder's** estate, not hers; and she cannot place his policy in trust — the write
path is scoped to `user_id`, so the action she is told to take does not exist for her.
Same class W-0186 named: *"no surface offers an edit that cannot work"*.

### The principle, because the next person editing this branch needs it

**What is TRUE about her cover, and what she can ACT ON, are two different things, and
this warning conflated them.** She should be told the cover on her life is not in trust
and that it is a term policy that expires — genuinely hers to know. She should not be
told to phone his insurer. Every warning this method emits divides on that line.

### How it was found, since nothing on screen could have shown it

The persona's only joint-life policy **is** in trust, so the branch never fires and no
amount of browser testing would surface it. Found by asking what the newly-reachable code
does, not by checking the persona still looked right. Demonstrated in a rolled-back
transaction with `in_trust` verified back at 1 afterwards. **Fixture variant
(`tests/CLAUDE.md` §4): joint-life-and-not-in-trust is an entirely ordinary combination
in real data and absent from `peak_earners`. Unreachable is not absent.**

## Acceptance

1. Fixed at its one home in `LifeCoverCalculator`, ownership-aware via
   `LifeCoverReach::isOwnedBy()` — **not** by post-filtering warnings in `EstateAgent`,
   which would put a second mechanism in charge of what the warning says. — DONE.
2. The owner's message is unchanged. — DONE, asserted separately.
3. The other two warning types are unaffected: `not_whole_of_life` is informational and
   true for her; `single_life_married` cannot reach a non-owner at all, because the reach
   only pulls `joint_life` policies. — DONE, verified.
4. Mutation-tested: restoring the single message reddens the non-owner case and leaves
   the owner case green. — DONE (M7).
- 2026-08-23 — Not browser-verifiable on the persona: its only joint-life policy is in
  trust, so the branch never fires on any screen. Covered by two tests and mutation M7.
  Moving to `handoff` with that limitation stated.
