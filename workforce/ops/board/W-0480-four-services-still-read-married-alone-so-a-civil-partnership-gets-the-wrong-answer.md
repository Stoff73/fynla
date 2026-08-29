---
id: W-0480
title: Four Estate and Tax services still read ['married'] alone, so a civil partnership gets the wrong answer on adjacent screens
mission: persona-run-peak_earners-2026-08-20
branch: fix/w-0480-civil-partnership-parity
owner: null
reviewers: [tax-compliance-reviewer]
status: done
closed: 2026-08-29
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-24T15:40:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-24
prior_art_found: [W-0474]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer gate report on W-0474, finding F5, 2026-08-24
---

## Intent

W-0474 fixed `IHTCalculationService`, which read `['married']` alone while nine
siblings read `['married', 'civil_partnership']`. **The reviewer checked the siblings
and found the count was wrong in the other direction: four of them read `['married']`
alone too, and carry the same defect.**

- `app/Services/Estate/LifeCoverCalculator.php:56` and `:452`
- `app/Services/Estate/ComprehensiveEstatePlanService.php:71`
- `app/Services/Tax/TaxOptimisationService.php:384`
- `app/Services/Tax/TaxActionDefinitionService.php:170`

Two are Estate services a civil partnership reaches on the same screens as the figure
W-0474 corrected, so a household can now see a correct Inheritance Tax number beside
life-cover and planning output still computed as though they were single.

**W-0474's own commit message claimed a constant gave the list "one home". It did
not** — the constant was `private`, which cannot be read by anything else. That is
fixed as part of this item's prior art: `App\Support\HouseholdPooling` is public and
holds the list and the predicate, so these four have something to read.

## Acceptance

1. Each of the four either reads `HouseholdPooling::POOLING_MARITAL_STATUSES` /
   `hasSpousalStatus()`, or states at the line why its own list is deliberately
   narrower.
2. Before/after for a civil partnership on each figure that moves — these are
   different services and the direction is not assumed to be the same in each.
3. A guard that fails when a new consumer branches on `marital_status` with its own
   literal list. Grep-based is acceptable here; the failure mode is a hand-written
   copy, and only a sweep sees a copy.
4. `tax-compliance-reviewer` on the change — `TaxOptimisationService` moves tax.

## Working notes

- 2026-08-24 — Filed from the W-0474 gate report (F5), which the reviewer marked
  informational and explicitly out of scope for that commit. Recorded here rather than
  left in a handoff.
- 2026-08-24 — Check `LifeCoverCalculator` first: it has two sites, and life cover is
  the figure most likely to be read as a protection recommendation rather than a tax
  one, so a wrong answer there reaches a different kind of decision.

## Resolution — 2026-08-28

**Acceptance 1 — done.** All four read `HouseholdPooling::hasSpousalStatus()`. None
needed a narrower list; each was asking the pooling question and answering it with half
the statuses.

- `LifeCoverCalculator.php:59` (`$isJointPolicy`) and `:455` (`$isMarried`)
- `ComprehensiveEstatePlanService.php:72` (the spouse lookup)
- `TaxOptimisationService.php:385` (`buildSpousalStrategy`)
- `TaxActionDefinitionService.php:171` (`evaluateSpousalTransfer`)

**Acceptance 2 — done**, `tests/Unit/Services/CivilPartnershipHouseholdParityTest.php`.
Each household is built twice, `married` and `civil_partnership`, and required to give
the same answer, then `single` is required to still give the other one. **Verified by
mutation:** with the four service edits stashed, all five tests fail and produce exactly
the `single` answer; restored, all five pass. The figures that move, per service:

- Life cover — `is_joint_policy` flips, and the annual premium falls: a joint life second
  death policy carries a 25% discount (`LifeCoverCalculator.php:328`) and is priced on the
  average of two ages, so the test asserts the civil partnership's premium equals the
  marriage's and is strictly less than the single person's.
- Life cover, existing policies — the `single_life_married` warning now raised.
- Estate plan — `user_profile.spouse` is a block rather than `null`.
- `TaxOptimisationService` — `spousal_optimisation` strategy present, same
  `estimated_annual_saving` as the marriage.
- `TaxActionDefinitionService` — `spousal_transfer_beneficial` fires.

**Acceptance 3 — done**, `tests/Architecture/MaritalStatusLiteralsArchitectureTest.php`.
Grep-based, as the item allowed. It has both directions: a NEW literal list reddens it,
and so does a baselined site whose line has changed — including one that has been fixed,
so the entry gets pruned instead of rotting. **Both were mutation-tested** (a probe class
under `app/Support/`, and fixing `LifeStageService` and running without pruning). Its
blind spot is stated in the docblock: it reads one line at a time, so an `in_array` split
across lines slips past.

**The sweep found fourteen more sites — filed as W-0508**, not fixed here. The Estate API
(`WillController`, `LifePolicyController`, `GiftingController` x2, `TrustController` x2,
`EstateController`), three services (`LifeStageService`, `CoverageGapAnalyzer`,
`ProtectionDataReadinessService`) and four agents. **This item's premise — that four
siblings carried the defect — was itself an undercount**, which is the argument for the
sweep rather than another review.

**Acceptance 4 — `tax-compliance-reviewer` dispatched on the change.** See the working
note below for its verdict.

### Verification

- `tests/Unit/Services/Tax` + `tests/Unit/Services/Estate` + the new parity test —
  **587 passed, 1,812 assertions.**
- `tests/Feature/Tax` + `LifeCoverReachSpouseLinkStatesTest` +
  `DeletedSpouseVisibilityTest` + `RecommendationsAggregatorServiceTest` +
  `EstateAgentGoalsTest` + the new sweep guard — **59 passed, 193 assertions.**
- Pint clean.
- **NOT verified in a browser.** These are service-layer branches with no template change;
  the user-visible movement is asserted at the figures above rather than on screen.

## Gate — tax-compliance-reviewer, 2026-08-28

**All four sites confirmed correct HMRC-wise, with the provisions cited**, and no
hardcoded tax value introduced by the change. The predicate is right at each; none needs
a narrower or wider list. Beyond the Inheritance Tax citations already in
`HouseholdPooling`, the reviewer established the income-tax and CGT side, which this
change also widens to civil partnerships:

- Marriage Allowance — **ITA 2007 s55A(1)(a)**, "married to, **or in a civil partnership
  with**, the other individual", in the primary Act, no SI needed.
- Transferring income-producing assets — **ITTOIA 2005 s626(1)** (outright-gift exemption
  from the s624 settlements charge) and **ITA 2007 s836/s837**, both expressed as
  "civil partners of each other".
- The CGT rationale behind that transfer — **TCGA 1992 s58**, headed "Spouses and civil
  partners" since SI 2005/3229.

`widowed` is correctly excluded from all four: there is no second life for a joint
policy, and Marriage Allowance requires a living partner. The two tax guards are not
redundant with `liveSpouseId()` — a widow's partner's `users` row is retained, so
`liveSpouseId()` alone would let her through.

**The gate did not pass clean. Two findings were fixed in this change rather than
deferred:**

- **F1/F5 — `LifeCoverCalculator::calculateLifeCoverRecommendations()` has NO production
  caller.** Verified: the only references are its own definition and the new test. The
  live life-cover path is `LifePolicyController:45`, which selects the second-death
  Inheritance Tax basis and whether the partner's age and gender reach the premium
  calculation. **Fixing the service alone moved no user's figure**, so this item's own
  working note — "check `LifeCoverCalculator` first, life cover is the figure most likely
  to be read as a protection recommendation" — was not satisfied by it. Now fixed at the
  controller.
- **F2 — `TrustController:201`** held the byte-identical line to the one fixed in
  `ComprehensiveEstatePlanService`, feeding the same `calculate()` and returning
  `iht_liability` to the client. Deferring it would have left exactly the
  correct-figure-beside-incorrect-figure this item exists to close.

Both are covered by `tests/Feature/Estate/CivilPartnershipReachesTheEstateApiTest.php`,
which drives the HTTP endpoints — **a unit test on either service would have passed while
the controller above it still handed down `null`**. Verified by mutation: both fail with
the two controllers reverted.

**Two more were fixed because they are in a file this change touches and the change
widens who reaches them:**

- **F10 (Rule 2)** — the Marriage Allowance saving was `$personalAllowance * 0.10 * 0.20`,
  with the basic rate as a literal.
- **F12** — and 10% of the personal allowance is the wrong amount: **ITA 2007 s55C(2)**
  rounds the transferable sum UP to the nearest £10, so it is £1,260 rather than £1,257.
  Both now come from `income_tax.marriage_allowance.amount` and `income_tax.bands.0.rate`
  — the same configured figure the public allowances page already publishes. Covered by a
  test that derives the expectation from config rather than pinning a year's number.

**Deferred, with the reasoning recorded on the items rather than lost:**

- **F3** — `WillController:80` persists `spouse_bequest_percentage = 0` for a civil
  partnership, so closing it needs a **data remediation**, not only a code change. On
  W-0508.
- **F4** — the three `nrb_transferred_from_spouse` defaults. **Not to be fixed by blind
  parity**: the `married` branch is itself wrong law (s8A brought-forward band requires
  the first partner to have died), so copying it to civil partnerships would propagate an
  over-claim. On W-0508 with that stated.
- **F6** — a civil partnership **cannot be persisted to `iht_profiles` at all**: the
  column enum and the `IHTController` validation rule both predate the status. Filed as
  **W-0509, critical**. No grep guard finds this class of defect, which is the point.
- **F7/F8** — the sweep's blind spots (frontend, `in:` rules, DB enums, and the fact that
  it blesses 22 hand-written correct-today copies). Recorded on W-0508.
- **F13** — "living together" (ITA 2007 s1011, TCGA 1992 s288(3)) is not modellable:
  `users.marital_status` has no `separated` value, so a separated couple is still offered
  both strategies. **Not a regression** — identical under `=== 'married'` — and IHT has no
  living-together condition. A data-model question, not this change's.
- **F11, F14, F15** — inline threshold fallbacks bypassing `TaxDefaults`;
  `assessExistingPolicies` firing without a partner; the two spousal evaluators reading
  different definitions of income. All pre-existing and direction-neutral here.

**F16 — the reviewer reported the new tests failing locally with
`Unknown table 'laravel_testing.liabilities'` and 81 failures in an untouched suite. That
was contention, not breakage:** it ran its suites concurrently with this session's, and
the two share `laravel_testing`. Every suite was re-run alone afterwards and is green —
the figures under Verification above are those runs.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`review`.

- **Delivered by:** Stoff73
- **Evidence:** merged in #739; commit `db6753419` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
