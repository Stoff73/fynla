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

## Q-03 measured, 2026-08-26 — and a correction to the ruling's central premise

Compliance-lead asked for the delta to be **assigned rather than estimated**, and
said the remedy decision depends on it. Measured below entirely from Fynla's own
configured values. **No external actuarial data was introduced and none is needed.**

### The correction — Fynla does use the answer. Two modules of three.

The ruling states: *"Neither service reads smoker status or health status anywhere"*
and generalises it to *"Fynla collecting an answer and not using it."*

**The first half is right and the generalisation is wrong.** Verified independently:

| Module | Reads `smoker_status`? | What it does |
|---|---|---|
| **Protection** — `RecommendationEngine:182-186` | **Yes** | `$basePremium *= $smokerLoading` |
| **Retirement** — `DecumulationPlanner:203-204` | **Yes** | `$factor *= 1.20` — enhanced annuity rates |
| **Estate** — `LifeCoverCalculator:300`, `LifePolicyStrategyService:22` | **No** | comment only; rate applied unconditionally |

`LifePolicyStrategyService::PREMIUM_TABLE` is indexed `[age][genderIndex]` — it has
a gender dimension and **no smoker dimension at all**.

**So this is not "Fynla knows something and its output does not". It is Fynla
answering the same question two ways and a third module not asking it.** A user who
declares they smoke gets a loaded premium in Protection, an enhanced annuity in
Retirement, and an unloaded whole-of-life estimate in Estate — three modules, one
declared fact, two different treatments and one omission.

That is a smaller and more tractable defect than the ruling describes, because
**the missing factor already exists and is already configured.**

### One consequence for the ruling's permitted wording

The sentence compliance marked as *"not optional and must not be softened"*:

> *It does not use what you have told us about your own health or whether you smoke.*

**That is true of the Estate figure and false of the Protection recommendation.**
Applied to a Protection premium it would misdescribe Fynla in the opposite
direction — telling a user their answer was ignored when it was applied. The wording
needs scoping to the Estate surface, or rewording per surface. Flagged for
compliance-lead rather than amended here.

### The delta

`protection.premium_factors.smoker_loading` = **1.5**, read live from
`TaxConfigService`. **Configured, not a fallback** — the full block is
`{"ip_rate":0.02,"ci_ratio":2.5,"base_rate":0.5,"smoker_loading":1.5}`.

So Fynla's own recorded position is that a smoker pays **1.5×** a non-smoker.
Applying that to `LifeCoverCalculator::estimateWholeOfLifePremium()`, which applies
no smoker factor:

| Age | Cover | Shown / yr | At Fynla's own ×1.5 | Understated by |
|---|---|---|---|---|
| 35 | £250,000 | £3,600 | £5,400 | £1,800 |
| 45 | £250,000 | £4,500 | £6,750 | £2,250 |
| 55 | £250,000 | £6,750 | £10,125 | £3,375 |
| 65 | £250,000 | £11,250 | £16,875 | £5,625 |
| 75 | £250,000 | £18,000 | £27,000 | £9,000 |
| 45 | £500,000 | £9,000 | £13,500 | £4,500 |
| 65 | £500,000 | £22,500 | £33,750 | £11,250 |
| 75 | £500,000 | £36,000 | £54,000 | £18,000 |

**The relative error is constant: the figure understates a smoker's premium by
33.3%.** It scales linearly with cover and with the age loading, so the money
understatement grows with both — £1,800/yr at the cheap end, £18,000/yr at the
expensive end.

Against compliance's own test — *small delta → disclosure is proportionate; large
delta → the figure should perhaps not be shown at all* — **a third of the premium,
always in the direction that makes cover look more affordable than Fynla itself
believes it to be, is not a rounding error.**

### The schema half is unchanged and still as the item describes

Live, 2026-08-26:

    protection_profiles.smoker_status   tinyint(1)    NOT NULL  DEFAULT '0'
    protection_profiles.health_status   varchar(255)  NOT NULL  DEFAULT 'good'

### A stale comment that would tell the next reader this is solved

`ComprehensiveProtectionPlanService:215-218` now renders `'Not provided'` for a null
— the display fix compliance credited. But its comment says:

> *`smoker_status` is a nullable boolean and `health_status` is nullable*

**Both are NOT NULL.** So the `default => 'Not provided'` arm is unreachable, exactly
as the item's Intent says, and the comment asserts the opposite of the schema. Anyone
reading it concludes the unknown case is handled. It is written, and dead.

**Not corrected here** — the fix is the migration in acceptance 2, and the comment
becomes true the moment that lands. Noted so it is not mistaken for the fix.

## Remedy chosen 2026-08-26 — Azlan: "only recommend the cover, not the premium. There is a rule on this."

**The rule is canonical and it is not one I proposed.**
`app/Services/AI/Prompts/ComplianceRules.php`:

- **Rule 3 — Signpost regulated advice.** Names **"protection underwriting"**
  explicitly among the areas where Fynla must acknowledge its limits and point to a
  regulated adviser.
- **Rule 7 — never state financial product details from memory.**
- **Rule 2 — no product recommendations.**

And `05-perimeter.md` §3, ratified 2026-08-13: **the seven rules bind all outbound
content, not only Fyn's chat.**

**A premium is underwriting output.** It is set by an insurer after assessing an
individual, and Fynla cannot know it. So the question this item has been circling —
*is the non-smoker assumption defensible, and by how much is it wrong* — **dissolves.
The figure should not be stated at any accuracy.** Q-03's 33.3% understatement
measures the error in a number that should not be there.

**This supersedes the disclosure wording in the compliance ruling above.** That
wording was drafted on the premise that the figure would be retained.

### The distinction that scopes the work

**Estimated premiums** — Fynla's guess at what a user *would* pay. Underwriting.
Must go.

**Recorded premiums** — what the user *told* Fynla they pay on a policy they hold.
Their own data, on their own record. **Stays.** `ProtectionCurrentSituation.vue:220`
and `planPrintMixin.js:1356` show `total_monthly_premiums` from stored policies and
are not in scope.

Conflating the two would delete legitimate data display, so it is stated before any
edit.

### Full scope — every site that states an ESTIMATED premium

**Backend**

| Site | What it does |
|---|---|
| `EstateAgent:1162` | `$remainingLiability * 0.02` |
| `EstateAgent:1170` | prose: *"premiums are still affordable (estimated £X/year at approximately 2% of cover)"* — **a premium quote AND an affordability judgement** |
| `EstateAgent:1197` | trace line: *"Estimated annual premium: £X"* |
| `EstateAgent:1201` | `'estimated_premium' => …` |
| `LifeCoverCalculator:128,143,189,202` | `annual_premium` on both scenarios |
| `LifeCoverCalculator:293-331` | `estimateWholeOfLifePremium()` — the method itself |
| `LifePolicyStrategyService:27-60` | `PREMIUM_TABLE`, `[age][genderIndex]` |
| `RecommendationEngine:177` | `estimateLifePremium()` |
| `RecommendationEngine:40,209,220,249` | `estimatedCost` on life, decreasing term, critical illness and income protection |

**Frontend**

| Site | What it does |
|---|---|
| `HolisticPlanContent.vue:387-395` | reads `monthly_premium_estimate` |
| `planPrintMixin.js:2344` | renders it as a badge — *"(£X/month)"* |
| `planPrintMixin.js:3201-3203` | same, print path |

`/m` and iOS not yet surveyed for equivalents.

### Why this is not being executed in the same breath

This removes displayed financial figures from **four modules across at least two
surfaces**, and `estimatedCost` is a documented field other code reads. That is a
different size of change from the one this item was raised as, and stripping
figures users may currently rely on is not something to do on inference from a
one-line instruction. **Scope recorded; execution put to Azlan.**

## Slice 1 done 2026-08-26 — the Estate life-cover recommendation

Scope answered by Azlan: *"only recommend the cover and word appropriately around
that"*, applied to every estimated-premium site. This is the first slice, complete
and tested on its own. The rest is scoped below, not done.

### Changed

**`EstateAgent`** — the `new_life_cover` recommendation no longer computes or states
a premium.

| Was | Now |
|---|---|
| `$estimatedAnnualPremium = $remainingLiability * 0.02` | removed |
| *"At age 62, premiums are still affordable (estimated £4,000/year at approximately 2% of cover)"* | *"What such a policy costs depends on underwriting, so an insurer or a regulated adviser is the right place to get a figure."* |
| action: *"Estimated annual premium: £4,000 (approximately 2% of cover at age 62)"* | action: *"The cost depends on underwriting — get quotes from multiple providers, or speak to a regulated adviser"* |
| `'estimated_premium' => …` | removed |

`cover_amount` is untouched. **The recommendation still says how much cover; it no
longer says what it costs.** The block already carried *"Get quotes from multiple
providers"* — the correct signpost, sitting beside an estimate that contradicted it.

**`EstatePlanService::enrichRecommendations`** — the whole life-cover `affordability`
block removed: `monthly_premium_estimate`, `is_affordable`, `affordability_ratio`,
and the `affordability_warning` prose (*"The estimated monthly premium of £X
represents Y% of your disposable income"*).

**Leaving it in place would have been worse than removing it.** With the premium
gone every figure would read £0, every plan would be declared affordable, and the
warning would never fire — a false reassurance where there had at least been a
number. The now-dead `$monthlyDisposable` local went with it; the accessor is still
used at `:723` so the dependency stays.

### Tested

`Life Cover Affordability` in `EstatePlanRefactorTest` pinned the removed behaviour,
so it was replaced by **`Life Cover Cost Removal`** — modelled on the
`Health Score Removal` block below it, which pins a removal rather than a value. Two
cases: the recommendation survives with no cost attached, and the plan layer does
not rebuild a judgement even when a caller still passes the old `estimated_premium`
key.

**One correction worth recording.** The first version asserted the keys were
*absent*. They are not: `BasePlanService:290-291` passes `affordability` and
`affordability_warning` through with `?? null` for every action type, several of
which legitimately populate them. The true assertion is that the **values are null**
for life cover. Found by a probe printing the real action keys rather than by
reasoning about the shape — the removal had worked, the assertion was wrong.

`EstatePlanRefactorTest` **19 passed**; `ProtectionRecommendationAdapterTest` and
`GetRecommendationsCompletenessTest` **20 passed**; `php -l` and `pint` clean.

### Not done — the remaining scope, unchanged from the map above

Estate scenarios (`LifeCoverCalculator::estimateWholeOfLifePremium`,
`annual_premium`), `LifePolicyStrategyService::PREMIUM_TABLE`, Protection
(`RecommendationEngine::estimateLifePremium` feeding `estimatedCost` on four
recommendation types), `ProtectionActionDefinitionService:270`,
`RecommendationsAggregatorService`'s `total_estimated_cost`, and five frontend files
including `LifeCoverRecommendations.vue`, which shows **"Annual Premium" as a
headline stat and a comparison-table column**.

**One nuance for whoever takes it:** those components render
`annual_premium || annual_investment`. The self-insurance scenario's
`annual_investment` is **not** a premium — it is what the user would invest instead,
which Fynla can legitimately state. The two share a display slot, so this is a UI
restructure rather than a deletion, and deleting the slot would take a legitimate
figure with it.
