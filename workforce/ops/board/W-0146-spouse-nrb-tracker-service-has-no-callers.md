---
id: W-0146
title: SpouseNRBTrackerService has never had a caller, and a comment pointing at it is why the household gift defect went unexamined
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [tax-compliance-reviewer]
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-21T20:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0154 F1 (the defect the comment concealed), 2026-08-21-iht-calculation-audit.md]
prior_art_outcome: none
source: found by tax-compliance-reviewer during the W-0154 audit; verified independently by fix-batch-G while fixing F1, 2026-08-21
---

## Intent

`app/Services/Estate/SpouseNRBTrackerService.php` is **never instantiated, injected or
invoked anywhere in the repository.** Verified twice by repo-wide grep — by the audit,
and again by `fix-batch-G` before removing the comments that referenced it. After
W-0154 the only remaining mentions are in that file itself.

**The reason this is on the board is not the dead class. It is what the class was
cited for.** Two comments in `IHTCalculationService` said the primary user's gifts were
deducted from their own band because *"Spouse NRB is handled separately by
SpouseNRBTrackerService"*. That sentence described a compensating mechanism, so a
reader checking whether the household case was handled would conclude it was and stop.
It was the stated reason the asymmetry was safe, and **the asymmetry was W-0154 F1** —
the £60,000 gap between the two spouses' bills.

Both comments were removed under W-0154. **The class was left in place**, because
deleting a service is a decision and this item is where it gets made.

## Acceptance

1. **Decide: wire it up, or delete it.** Do not leave a third option where it stays
   present and unused — that is the state that produced the misleading comment.
2. **Before deleting, establish what it was built to do**, and confirm W-0154's
   per-member gift capping does not leave a gap it was meant to cover. The two overlap
   in subject matter; whether they overlap in function has not been checked.
3. If it is wired up, its output must reconcile with `nrb_gift_deduction` rather than
   becoming a second answer to the same question (Rule 20).
4. If it is deleted, check for tests, factories or documentation that reference it.

## Working notes

- 2026-08-21 fix-batch-G: raised while fixing W-0154 F1. **Severity is low because
  nothing calls it and nothing therefore breaks** — but the item exists because a
  comment describing work that nothing does is more dangerous than the dead code
  itself, and that lesson is the transferable half.
