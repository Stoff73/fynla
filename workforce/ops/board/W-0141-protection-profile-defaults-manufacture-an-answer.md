---
id: W-0141
title: A user who was never asked whether they smoke is recorded as a non-smoker in good health, and told so on their protection plan
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [compliance-lead, product-lead]
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-21T19:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0033, W-0006, W-0100 (same shape — the product stating what nobody told it)]
prior_art_outcome: none
constitution_refs: [05-perimeter, 07-quality-bar]
source: found by fix-batch-G while closing W-0033, 2026-08-21
---

## Intent

**The schema cannot tell "no" from "never asked", and the difference decides how much
life cover Fynla recommends.**

Verified against the live local schema, 2026-08-21:

```
protection_profiles.smoker_status   tinyint(1)   NOT NULL   DEFAULT '0'
protection_profiles.health_status   varchar(255) NOT NULL   DEFAULT 'good'
```

So the moment a protection profile row exists, the database has asserted **"does not
smoke, in good health"** on the user's behalf. `ComprehensiveProtectionPlanService`
then prints that on the plan as "Non-smoker" and "Good", and
`RecommendationEngine.php:185,232` branches on `smoker_status` to decide what to
recommend.

**The request layer already disagrees with the column.**
`StoreProtectionProfileRequest.php:37-38` validates both as `nullable` — so the API
accepts "I have not answered", and the column silently converts it to an answer. That
mismatch is the defect: one layer models the unknown and the next erases it.

W-0033 removed two dead reads immediately above this code and left
`ComprehensiveProtectionPlanService` able to say **"Not provided"** — that branch is
correct and currently unreachable, because nothing can store the null it responds to.

`tests/Unit/Services/Protection/ComprehensiveProtectionPlanProfileSourceTest.php`
carries a characterisation test pinning the two column definitions. **It will fail when
this is fixed. That is the signal.**

## Acceptance

1. **Decide first, and it is not obviously "make them nullable".** Smoking and health
   status drive protection advice; a null means the engine has to say what it does
   without them rather than assuming the favourable answer. Making the columns nullable
   without deciding what `RecommendationEngine` does with a null moves the problem
   rather than fixing it. **compliance-lead should rule on whether assuming
   "non-smoker, good health" is defensible at all** — it is the favourable assumption
   on the field that most affects the price and adequacy of life cover.
2. If the columns become nullable: migration by `--path=`, and state how many existing
   rows hold the default and therefore cannot be distinguished from real answers.
   **Existing rows cannot be retro-classified** — a stored `0` today might be a real
   "no" or a never-asked. Say so rather than guessing.
3. `RecommendationEngine` and `ProtectionDataReadinessService` must both handle the
   unknown explicitly. The readiness gate (`:199, :396`) already tests
   `smoker_status !== null`, which today can never be false.
4. Update the characterisation test in the file named above; do not widen it.

## Working notes

- 2026-08-21 fix-batch-G: found while closing W-0033, not fixed. Making a NOT NULL
  column nullable changes advice for every existing user, which is a decision, not a
  tidy-up — the same reasoning W-0033 itself was raised under.

- 2026-08-21 compliance-lead: **RULING — provisional. It is not the pattern expected, and what
  it actually is, is worse.** **Not an approval** (`05-perimeter.md` §7.3). **Provisional** —
  data protection is only **partially mapped** (§1.1) and this sits in the uncovered part.

  **Nothing below says whether the assumption is actuarially sound or whether any figure is
  right.** Correctly not asked, and neither is mine.

  ### The shape expected was "an unanswered field defaulting to the favourable value". It is not that.

  **There is no field being read at all.**

  `LifeCoverCalculator::estimateWholeOfLifePremium()` (`:298`) and
  `LifePolicyStrategyService::PREMIUM_TABLE` (`:22`) both carry a comment saying the rates are
  *"for non-smokers in good health"*. **Grepped both files for `smok` and `health`: the only
  matches are those two comments.** Neither service reads smoker status or health status
  anywhere.

  **So the favourable rate is not a default that fills a silence. It is applied unconditionally
  — including to a user who told Fynla they smoke.**

  That is a different and sharper defect than an unanswered election becoming an answer. **An
  unanswered question becoming an answer is Fynla filling a gap. This is Fynla collecting an
  answer and not using it.** The user did their part.

  ### The pattern it does belong to — and this is the second instance

  **W-0106's shape: the schema records a distinction the enforcing code never reads.** There,
  `certificate_provider_professional_details` existed on the model while
  `checkCertificateProviderKnownYears()` ignored it entirely. Here, `protection_profiles`
  records `smoker_status` and `health_status` — **read by at least four other consumers**, per
  the notes already in `ComprehensiveProtectionPlanService` — while the premium estimate ignores
  both.

  **Two instances in one day is a pattern worth naming**, and it is the inverse of the one
  everyone has been looking for: not *"Fynla asserts something it does not know"* but **"Fynla
  knows something and its output does not"**.

  ### Already fixed, and not re-raised

  **The display layer has been corrected by someone else and I am not reopening it.**
  `ComprehensiveProtectionPlanService.php:215-224` now renders `'Not provided'` for a null
  `smoker_status` or `health_status`, with a comment recording that both *"previously rendered a
  missing answer as a definite one — 'Non-smoker' and 'Good'"*. **That was the assertion half
  and it is gone.** Credit noted; the live defect is the calculation half.

  ### The competence answer — and it turns on a distinction worth stating

  **The question as posed is "is Fynla entitled to assert good health about someone who has not
  said so". On the facts, Fynla does not assert it.** The assumption lives in a **code comment**,
  which no user reads. What the user receives is **a premium figure with an undisclosed basis.**

  **So the act-not-object test is not the one that bites here. Trunk §4 is:** *"Where Fynla knows
  its picture is incomplete, it says so at the point the affected figure is shown — not in a
  footer, not in a blanket disclaimer."*

  ⚠️ **And this is a NEW CLASS of §4 instance.** §4's live example is **unmodellable** data —
  crypto, which Fynla cannot represent. **This is data Fynla HAS and the figure does not use.**

  **That is worse than the unmodellable case, and the reason is the user.** With crypto, nobody
  told Fynla anything. Here the user answered a question **Fynla asked**, and the figure ignores
  the answer. §4's own rationale — *"an incomplete figure presented without qualification is
  worse than no figure"* — reaches it a fortiori.

  ### What the output may state if the assumption is retained

  **Permitted — these describe Fynla's calculation, which is a fact about Fynla:**

  > `This estimate uses rates for a non-smoker in good health. It does not use what you have told us about your own health or whether you smoke.`

  **The second sentence is not optional and must not be softened to "may not".** It is
  unconditionally true today, and a hedge would misdescribe it.

  **Forbidden:**
  - Anything stating or implying the user **is** a non-smoker or in good health.
  - Anything presenting the figure as personal to them, or as reflecting their circumstances.
  - Anything stating what their actual premium would be, or how much their own status would
    change it. **That is the accuracy question and it is not Fynla's to answer here.**

  ### Do I need the delta measurement? No — and here is who does

  **Not to rule.** Whether Fynla may present a figure whose basis it does not disclose does not
  turn on the size of the error, and the sharper half — a collected answer being ignored — does
  not turn on it either.

  **But whoever decides the remedy needs it**, and that is a real dependency:
  - **Small delta** → the disclosure above is proportionate and sufficient.
  - **Large delta** → showing a smoker a non-smoker's premium may be misleading enough that the
    figure should not be shown to them at all, disclosure or not.

  **That is `Q-03`'s measurement, it is an agent task rather than a legal one, and it should be
  assigned rather than estimated.** I have not estimated it.

  ### Checked and cleared — recorded so nobody re-flags it

  `LifePolicyStrategyService.php:380` — *"You have health conditions (lock in rates now)"* —
  **is not an assertion about the user.** It is one line of a `decision_framework` list under
  *"Choose Insurance if:"*, i.e. a criterion, not a claim. **No defect.**

  ### Noticed — adjacent, routed, not ruled

  That same `decision_framework` block tells a user how to choose **between insurance and
  self-insurance**, generically. **Not a W-0141 finding and I am not raising it as one** — but
  it is exactly the shape the `Q-02` question set's categories B and C are built to probe, and
  whoever runs that corpus should know this static framework exists alongside whatever Fyn says.
