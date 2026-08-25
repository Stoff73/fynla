---
id: W-0146
title: SpouseNRBTrackerService has never had a caller, and a comment pointing at it is why the household gift defect went unexamined
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [tax-compliance-reviewer]
status: handoff
severity: low
surfaces: [web, m, ios]
created: 2026-08-21T20:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: quality-lead
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

1. [x] **Decide: wire it up, or delete it.** — **DELETED.** Reasoning in the
   2026-08-25 note below.
2. [x] **Before deleting, establish what it was built to do**, and confirm W-0154's
   per-member gift capping does not leave a gap it was meant to cover. — done; no gap.
3. [n/a] If it is wired up, its output must reconcile with `nrb_gift_deduction`. — not
   wired up; the inability to reconcile is precisely why it was deleted.
4. [x] If it is deleted, check for tests, factories or documentation that reference it.
   — no tests, no factories, no live docs. Dated archives left as the historical record.

## Working notes

- 2026-08-21 fix-batch-G: raised while fixing W-0154 F1. **Severity is low because
  nothing calls it and nothing therefore breaks** — but the item exists because a
  comment describing work that nothing does is more dangerous than the dead code
  itself, and that lesson is the transferable half.

- 2026-08-25 (Brett, working alone per CSJ's 2026-08-24 standing instruction):
  **DELETED.** `app/Services/Estate/SpouseNRBTrackerService.php` is gone, and the two
  comments in `IHTCalculationService` that pointed at it now record why.

  **Zero callers re-verified independently**, not taken on the item's word. Repo-wide
  grep across `app/ config/ bootstrap/ routes/ database/ tests/ resources/`, including
  string references that would catch a container binding or a dynamic resolve. The only
  hits were the class itself, its two internal self-calls (`:114`, `:144`), and the two
  W-0154 comments. No service-provider binding. No test file — the planned
  `SpouseNRBTrackerServiceTest.php` in the July blindspot plan was never written.

  **Acceptance 2 — what it was built to do.** Four public methods, all about the
  transferable nil rate band on death: `calculateSpouseNRBUsage()`,
  `calculateSurvivorTotalNRB()`, `isFullNRBTransferable()`, `calculateRNRBTransfer()`.

  **Why it could not be wired up — it contradicts the live model in law.**
  `calculateSpouseNRBUsage()` measures a spouse's gifts against `Carbon::now()`, i.e.
  it computes what a **living** spouse's transferable band would be as if they had died
  today. That is exactly the model `IHTCalculationService` rejects: **IHTA 1984 s8A
  creates the claim on the survivor's death and not before** (see `:346` and `:2441`,
  both written under W-0154 F2). The live service instead takes
  `nrb_transferred_from_spouse` as user input for genuinely widowed users, caps it per
  s8A(3)-(5) at 100% of the maximum, and reports `nrb_spouse_modelled` separately as a
  stated second-death assumption. Wiring the dead class in would have produced a
  **second, contradictory answer to a question the live service already answers** —
  which is what acceptance 3 forbids under Rule 20.

  It also carried three known defects, none of which were worth importing:
  - the 14-year rule applied to CLTs only, not to failed PETs affecting later CLTs —
    flagged in `May/May12Updates/review-tax-compliance.md:264-266`;
  - `calculateRNRBTransfer()` says *"For simplicity, we'll assume if they owned a home,
    RNRB was used"*, which is not the residence-band rule;
  - no s8A(3)-(5) cap anywhere.

  **Acceptance 2 — no gap left behind.** W-0154's `calculateNRBDeductionForGifts()`
  iterates `pooledMembers()`, which returns `[$user, $spouse]` whenever the household
  pools, and delegates each member to `failedGiftTax->forMember($member, $nrbSingle,
  $deathDate)` — **every pooled member's gifts, each capped at their own band.** The
  household-gift subject the two overlapped on is therefore covered, and covered
  correctly. The one thing the dead class did that the live code does not is *derive*
  the transferable band from a living spouse's gift history — and that is a deliberate,
  legally-correct difference, not a gap.

  **The comments.** Both were rewritten rather than deleted. The item's own point is
  that a comment describing work nothing does is more dangerous than the dead code, so
  the quoted claim is kept as history and the record now states that the class was
  deleted and that this deduction is the only answer to the question. A future reader
  who greps the name lands on an explanation, not a void.

  **Verification.** Estate 495 tests / 1,644 assertions green; Architecture 177 / 4,296
  green (this suite would catch a broken binding or an unresolvable dependency).
  `composer dump-autoload` re-run. Pint clean.

  **Note for whoever certifies:** the item declares `reviewers:
  [tax-compliance-reviewer]`, but **no tax calculation changed** — this deletes code
  nothing executed and rewrites two comments. No figure moves for any user. Whether the
  statutory gate is still required is a call for the certifier, not for me.

  **Not done:** the dated archive folders (`July/July7Updates/`,
  `May/May12Updates/`, `codex/plans/source-corpus/`, `docs/superpowers/plans/`) still
  name the class. Left deliberately — they are the historical record of audits and
  plans as written at the time, and rewriting them would destroy the provenance this
  item depends on.
