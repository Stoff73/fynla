---
id: F-0024
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-22T00:00:00Z
status: active
---

# F-0024 — Cycle 4: what a household HAS, and what its pensions are WORTH

**Agent:** build-lead (`fix-cycle4-pensions`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0241, W-0244 · **ID block:** W-0311 – W-0320
**Number and ID block issued by team-lead.** Both items carry a **CSJ ruling dated
2026-08-22**; neither was re-litigated (Rule 18).

**Predecessors, read before touching anything here:**
`F-0022-cycle4-dashboard-module-totals-and-cache.md` — the direct predecessor. It
built the card-level workaround this batch **deletes**, created
`resources/js/utils/retirementHeadline.js` (extended here, not duplicated), and
named the third failure precisely: *"Sarah's retirement card is not a share
problem. The card could only render a pot, and her provision is an income."*
`F-0019-cycle2-ownership-applied-one-side-only.md` — the reach / fraction
vocabulary. `F-0023` — the concurrent validation batch, which owns **W-0242** in
`LifeStageService.php`.

---

## 1. The principle

**Two different questions had been collapsed into one answer, in two different
places, and each collapse produced the same symptom from the opposite direction.**

| | The question | The wrong answer | Where |
|---|---|---|---|
| **W-0244** | Does this household have retirement **provision**? | conflated with *"has it stated a target?"* | `RetirementAgent::analyze()` |
| **W-0241** | What are its pensions **worth** as capital? | conflated with *"has it obtained a transfer value?"* | `MobileDashboardAggregator::calculateNetWorth()` |

They are not the same defect and they do not have the same fix, but they meet on
one household. **Sarah Jones (17) holds an NHS final salary scheme paying £35,000 a
year and nothing else.** She therefore has retirement provision **and** a £0
pensions capital line, and the application has to be able to say both at once
without either statement contradicting the other. That is the sentence this batch
was written against, and it is the test the code now has to pass.

### W-0244 — `success: false` was carrying a meaning it cannot carry

```php
if (! $hasProfile) {
    return $this->response(false, 'No retirement profile found', []);
}
```

Not "we cannot project without a target" — **nothing at all**: no pot, no schemes,
no State Pension, not even a count. Both persona accounts were in this state while
holding £500,000 of Defined Contribution pensions, an NHS scheme and a State
Pension forecast.

**The readiness gate was not what blocked them, and that mattered more than it
looked.** `RetirementDataReadinessService::assess()` correctly classified the
missing target as a *warning*. The profile check below it was absolute. So the
system already had a considered opinion about how blocking a missing target is —
and one line further down ignored it.

### W-0241 — the code read as one thing while doing another

```php
$dbPensionValue = (float) $user->dbPensions->sum('transfer_value');
```

`db_pensions` has never had a `transfer_value` column. Over a **Collection** a
missing attribute reads as null, so the sum was `0.0` — silently, for every user,
forever. **The exclusion was never the defect.** CSJ's ruling is explicit: the
defect was that *the application performed the exclusion while its code read as if
it valued the schemes*. A future reader "fixing the typo" would have moved every
affected user's net worth without meaning to.

**The same mistake in `LifeStageService` was a live 500** rather than a silent zero,
because there it went through the **query builder** and reached MySQL as
`select sum(transfer_value)`. One mistake, two consequences, and the invisible one
survived years longer than the fatal one. That is worth stating as a rule:
**a silent wrong answer outlives a loud one.**

---

## 2. Prior art

Checked 2026-08-22 across `registry/capabilities.md`, the code, custom artisan
commands, open PRs and in-flight branches, the vault, and `.claude/skills|agents`.

| Instance | Prior art found | Outcome |
|---|---|---|
| Net worth pension rule | `NetWorthService::calculatePensionBreakdown()` — already Defined-Contribution-only, already returns `has_db` "so the frontend can display an appropriate note" | **route** — made public; the aggregator calls it instead of its own two sums |
| The disclosure itself | **already shipped on all three surfaces**: `WealthSummary.vue:34`, `NetWorth.vue:20`, `NetWorthView.swift:63` | **extend** — nothing new built; the flag was added to the dashboard payload so it travels with the figure |
| Retirement headline rule | `resources/js/utils/retirementHeadline.js` (F-0022) | **extend** — a second function in the same file, not a second file |
| Guaranteed income | `PensionProjector::projectTotalRetirementIncome()` | **route** — hoisted into the agent as `guaranteedAnnualIncome()`; the aggregator's copy deleted |
| Native retirement card | **W-0243, already filed, now unblocked** | **route to the existing item** — not built here, see §6.2 |
| Guaranteed-income basis drift | **W-0245, already filed** | **route to the existing item** — not reconciled here, see §6.3 |

**"Build a parallel one because the existing one is awkward" was available three
times and declined three times.** The most tempting was the third: the web module
page computes `guaranteedIncome` client-side from raw records, which is a third
implementation of a figure the backend now returns. Consolidating it looked free —
**it is not**, because the two differ for any scheme with revaluation, and that
reconciliation is W-0245's, already filed. Taking it here would have moved numbers
under cover of a fix that promised not to.

---

## 3. Constraints honoured

- **The rulings were implemented, not improved on** (Rule 18). No `transfer_value`
  column, no migration, no form field, no capitalisation multiple.
- **Rule 20** — three mechanisms answering "what retirement provision is there"
  became one; two answering "what does a pension contribute to net worth" became
  one. Deleting the workaround included deleting the two constructor dependencies
  it needed. **The count of implementations went down at every step.**
- **Rule 19** — web, `/m` and native named individually throughout; the one native
  gap routed to its existing item rather than assumed or skipped.
- **Rule 12** — the disclosure is descriptive text. No score, no rating, no
  completeness percentage. **Rule 15** — no icons added.
- **Rule 9** — "Defined Benefit", "Defined Contribution" and "State Pension" spelled
  out in every string and comment written here.
- **`LifeStageService.php` was NOT edited** — W-0242's agent had already removed the
  phantom reader, and the file is theirs. Coordinated with team-lead, not assumed.

---

## 4. Status

| Item | Outcome |
|---|---|
| **W-0244** provision | **DONE** — agent, aggregator, plans, module page, Fyn's tool path, dashboards |
| **W-0241** valuation | **DONE** — phantom reader deleted, exclusion disclosed, and the rejected ×20 capitalisation removed from the detail endpoint under team-lead's authorisation. Native's remaining Swift wording filed as **W-0311** |

### The acceptance clause that mattered most: no number moved

Measured on the live local `laravel` database, both accounts, caches flushed,
before and after:

| | David (16) before → after | Sarah (17) before → after |
|---|---|---|
| Net worth (`/net-worth`) | £1,489,500 → **£1,489,500** | £739,280 → **£739,280** |
| Net worth (dashboard endpoint) | £1,489,500 → **£1,489,500** | £739,280 → **£739,280** |
| Pensions capital | £500,000 → **£500,000** | £0 → **£0** |
| Retirement card | pot £500,000, guaranteed £11,502.40 → **identical** | pot £0, guaranteed £35,000 → **identical** |
| `has_db_pensions` on the dashboard | **absent** → `false` / `true` | — |

**The retirement cards being byte-identical after the workaround was deleted is the
evidence that the agent is now supplying them.** That was the point of the deletion
and it is the one measurement that proves it, because a card that changed would
mean the agent had not taken over and a card that broke would mean nothing had.

### Mechanisms answering each question: before and after

| Question | Before | After |
|---|---|---|
| Does this user have retirement provision | 3 (agent refusal, aggregator's own record read, each frontend's fallback) | **1** — `RetirementAgent` |
| What income has this user already secured | 3 (`pensionProvision`, the agent's projector read, the web page's client-side sum) | **2** — the web page's copy is W-0245's, §6.3 |
| What does a pension contribute to net worth | 2, **and they disagreed by a phantom column** | **1** — `NetWorthService::calculatePensionBreakdown` |
| Which figure does the retirement hero lead with | 2 (dashboard card rule, `/m` page's own chain) | **1** — `retirementHeadline.js` |
| What a Defined Benefit scheme is worth as capital | 2, **and they disagreed by 20×** | **1** — always nothing |
| What we SAY about the exclusion | 3 hardcoded frontend copies | **1** — `PensionDisclosure`, served with the figure |

---

## 5. The ×20 — a rejected option that was running live, now deleted

**Found while doing W-0241, not named by it. Escalated with measurements rather than
removed unilaterally, then authorised by team-lead and removed.**

`NetWorthService::getAssetsSummaryWithDetails()` capitalised Defined Benefit schemes
at **20× the accrued pension plus the lump sum** — **option 2 in W-0241's own Intent,
the option CSJ rejected.** Served by `GET /api/net-worth/assets-summary-detailed`.

**CORRECTION, made after browser verification.** This document and two messages to
team-lead originally said the ×20 was live on **web, `/m` and native**. **It was live
on `/m` and native only.** The web consumer cited, `NetWorthOverview.vue`, is **dead
code**: no route imports it, and nothing else in `resources/js/` imports it either —
it is the sole consumer of `assetsSummaryDetailed` on web, and it is unreachable. See
§6.12. The `/m` and native defect was real and is what the measurements below record;
the web half of that claim was wrong and is withdrawn.

**Why it was escalated rather than fixed on sight.** The dispatch's acceptance read
*"if a number moves, your change is wrong"*, and removing this moves £805,000 on a
screen. Team-lead corrected the instruction: it was written about the **headline net
worth total**, which is already Defined-Contribution-only at `NetWorthService.php:48-50`
and does not move — *"it was never a licence to keep a rejected option running."*
**The escalation cost one message and prevented either a rejected option shipping or
a large unauthorised number movement.** Recorded as a non-regression in the queue.

### Measured, Sarah (17), before and after

| | Before | After |
|---|---|---|
| Hero: total assets | £861,780 | £861,780 |
| Pensions row | **£805,000 (93% of assets)** | **£0 (0%)** |
| Asset list sums to | £1,666,780 | **£861,780** |
| Difference from the stated total | **£805,000** | **£0** |
| Category percentages total | **193%** | **100%** |
| Headline net worth | £739,280 | **£739,280** |

**The list now sums to the total printed above it, and the percentages total 100%.**
The NHS scheme keeps `annual_pension: 35000`, which every surface already renders as
"£35,000 a year" — the £0 capital line and the £35,000 income now read as one
coherent statement instead of contradicting each other. David is unaffected in every
respect: no Defined Benefit scheme, list already reconciled.

**What is asserted in tests is the £35,000 and the arithmetic, not the £0** — see §10.

## 6. What the receiver needs, and would not otherwise know

### 6.1 The readiness gate was a SECOND path to the same wrong answer, and deleting the workaround would have re-opened it

**This is the single most important thing in this document.** W-0244 names the
missing-profile branch. It is not the only branch that returned nothing.

`RetirementDataReadinessService` blocks on missing **income**, and that branch
returned `'summary' => null`. F-0022's workaround happened to cover it, because
`$profileMissing` included `can_proceed === false` and `pensionProvision()` supplied
the facts regardless. **Deleting the workaround without covering the readiness path
would have regressed exactly what F-0022 fixed** — and it would have looked like a
clean deletion.

It is not hypothetical: a household with an NHS scheme and no income on file
reproduces it. Both ends are fixed — `provisionSummary()` fills the readiness
branch's `summary` with the same facts, and the aggregator no longer blanks the
summary when `can_proceed` is false — and it has its own test, named for the
branch it exercises.

### 6.2 The disclosure has one home now, and a fourth consumer is already waiting

`app/Constants/PensionDisclosure.php` holds the sentence. **Three frontends each held
their own copy of it**, which is how they drift; web and `/m` now render the text the
backend sends with the figure, so the wording cannot diverge.

Three registers, deliberately, because **a clipped disclosure is not a disclosure**:

- `DEFINED_BENEFIT_EXCLUDED` — the full sentence, for a block with room to wrap.
- `DEFINED_BENEFIT_EXCLUDED_SHORT` — **not a truncation; a shorter sentence that is
  complete on its own**, for a caption or cell that cannot hold the long one.
- `PENSION_CAPITAL_SUBTITLE` — replaces "Accessible pension capital", the phrase that
  made a £0 line read as a lost record rather than a statement.

**The fourth consumer is not this batch's:** the risk-profile capacity-for-loss factor
prints "£0 pensions" as a literal term in its formula, and the risk-engine agent was
about to ship its own `has_defined_benefit_pension` flag and its own wording. Named to
team-lead early, before polishing the copy, precisely to stop that second mechanism
being born. `calculatePensionBreakdown()` is public and already returns
`{dc, has_db}` — that agent needs no new flag.

**Their finding, which applies to every surface:** that factor's `description` is
`line-clamp-2` on the card and **not rendered at all** on the detail page. Checked the
same here — the ellipsis rules on `NetWorthOverview.vue` target `.item-name` and
`.column-value`, not the disclosure block, and neither `/m` class clamps. Verified by
reading the CSS, not assumed.

### 6.12 `NetWorthOverview.vue` is dead code, and it cost me a wrong claim

`resources/js/components/NetWorth/NetWorthOverview.vue` is **not imported by the
router, nor by any component**. Verified two ways: the router's `NetWorth` imports are
`NetWorthWealthSummary`, `PropertyList`, `PensionList`, `InvestmentList`,
`BusinessInterestsList`, `ChattelsList`, `LiabilitiesList`, `JointAccountHistory`,
`BalanceHistory` and `CashOverview` — no `NetWorthOverview`; and a repo-wide grep for
the name returns only its own file and an unrelated `NetWorthOverviewCard`.

**It is also the only consumer of `assetsSummaryDetailed` on web**, which means the
`/m`-and-native endpoint has no live web renderer at all. The web equivalent is
`PensionList.vue` at `/net-worth/retirement`, which was already correct.

**Two consequences, both mine to own:**

1. **My edit to it reaches nobody.** The disclosure and the "£35,000 a year" row I
   added to that card are correct and harmless, and will be right if the component is
   ever revived — but they are **not web coverage** and are not counted as such.
2. **I told team-lead the ×20 was live on three surfaces. It was live on two.** I
   cited `NetWorthOverview.vue:19` as the web consumer without checking whether
   anything mounts it. Grepping for a component's *usages* is not the same as grepping
   for its *contents*, and I did the second.

**The lesson is the one this batch keeps relearning from the other side:** a string
existing in a file proves nothing about whether a user can see it. I applied that to
the `/m` bundle and to the clamp check, and failed to apply it to a Vue component in
the same repository.

Filed as a finding for the cycle rather than fixed here — deleting a dead component is
its own change with its own blast radius.

### 6.3 The native retirement card is W-0243's, and it is now unblocked

`FinancePanelsView.swift:91-93` computes
`retirement.potValue ?? (pensionAssets > 0 ? pensionAssets : retirement.incomeGap)`.
For a Defined-Benefit-only household `pot_value` is **present and zero**, so `??`
never fires and the panel reads £0 — the defect W-0238 fixed on web and `/m`.

**W-0243 already specifies this exactly**, including "read the JavaScript helper
before writing the Swift". It is `blocked_by: [W-0238]`, which is now done, so it is
**unblocked**. The backend it needs is live: `guaranteed_income` is on
`modules.retirement` and now comes from the agent rather than a workaround.

**Not built here** because it is a separate item with a separate owner and was not
in this batch's scope. Flagged to team-lead as ready to claim. `Codable` ignores
unlisted keys, so nothing breaks in the meantime and no coordinated release is
needed.

### 6.4 "Guaranteed income" still has two implementations, and closing that gap moves numbers

The backend computes it from `PensionProjector` (**revalued**). The web module page
computes `dbPensionIncome + statePensionForecast` client-side from **raw** columns.
They agree on this persona **only because Sarah's scheme has
`inflation_protection = 'none'`** — a scheme with revaluation would show different
figures on the module page and the card.

**F-0022 flagged this and assigned it to W-0245.** Consolidating it here would have
been a Rule 20 improvement that silently moved a user-visible number under cover of
a fix whose acceptance was that nothing moves. **Left alone deliberately**, and
recorded here so it does not read as an oversight.

### 6.5 The fix made two fabrications reachable in the retirement plan, and they are now fixed

Before this work the plan returned an empty shell with an error, so its narrative
was unreachable for these users. Once the agent answered, it built — and said:

- *"This plan aims to show you how you can achieve retirement at **age 0** with **£0
  per month** after tax"*
- *"Your current pension arrangements are **projected to meet your retirement income
  target**"*, with `on_track: true`

Both to a household that has never set a target. **A null income gap is an absent
measurement, not a surplus**, and `$incomeGap <= 0` cannot tell them apart. Both now
branch on `has_retirement_target`, and `on_track` is `null` rather than `true` when
there is nothing to be on track for.

**The general lesson for anyone unblocking a previously-dead code path: the code
behind it has never run against this input.** Its defaults were written for the
populated case and `?? 0` will convert every absent figure into a confident zero.

### 6.6 `?? 0` in a controller is how a null becomes a lie

`RetirementController::analyze()` flattened the summary with `?? 0` throughout.
`??` fires on null, so every target-derived null became a **zero** — and `/m` tests
`Number.isFinite()` to decide between printing a figure and printing an em dash.
**Zero is finite.** The page would have reported a projected retirement income of
£0 to a household holding a £35,000-a-year scheme.

Target-derived fields now pass `null` through; record-derived fields keep `?? 0`,
because there a zero is the true answer. **The distinction is the fix, not the
null-safety.**

### 6.7 `has_retirement_target` is the flag to branch on — `success` is not, and never was

Added to `summary` on **every** branch, including the happy path, so consumers have
one shape. Any new consumer asking "has this user set up their retirement?" must
read it. A consumer that branches on `success === false` is now asking "did the
agent fail?", which is a different and much rarer question.

The eval trace gained a matching `no_retirement_target` result path so the two
`success: true` branches stay distinguishable in `EngineCalled`.

### 6.8 `ToolResultContract` needed no special case, and that was checked before relying on it

`assertKeys()` uses `array_key_exists`, not `isset`, so a **null value satisfies a
required key**. The no-target branch therefore returns the full six-key retirement
contract with nulls and validates on the normal happy path. Verified by running the
real validator against the real agent output for both accounts, not by reading it.

`CoordinatingAgent::requiresQuestionScopedFallback()` still matches the string
`'no retirement profile'`. That is **not** dead: `buildScenarios()` still returns it,
legitimately — a what-if scenario genuinely needs a target to be a what-if about.

### 6.9 The hardcoded State Pension age of 67 was mirrored, not fixed

The happy path falls back to a literal `67`. `provisionSummary()` mirrors it exactly,
literal included, with a comment saying why. **Mirroring keeps the branches
identical, which is the point of the method**; fixing it would be a Rule 2 change to
a value neither item mentions, in a batch whose acceptance is that nothing moves.
`TaxDefaults` has no State Pension age constant — checked, it would have to be added.
**Raised, not changed.**

### 6.10 Assumptions made, stated plainly

- **"A null projection" was read as "everything derived from the TARGET is null;
  everything derived from the RECORDS is present."** `income_projection` is therefore
  populated, not nulled — it is entirely record-derived (each Defined Contribution
  scheme carries its own retirement age, and `getUserAge()` falls back to
  `users.date_of_birth`), and nulling it would have discarded the Defined Benefit
  and State Pension income that makes the case work. `profile`, `decumulation`, the
  income gap and years-to-retirement are null; `post_retirement_goals` is empty
  because it needs a retirement date.
- **A readiness-blocked household with a profile has `has_retirement_target: true`.**
  It has stated a target; the analysis is blocked for other reasons. Hardcoding
  `false` there would have been a third way of confusing the two facts.
- **A "not configured" retirement card now means holding no pensions**, not lacking a
  target. Asserted in both directions.

### 6.11 Live persona data was read, never written

No `->touch()`, no row created, updated or deleted on the `laravel` database. Every
test figure comes from factory fixtures in `laravel_testing_b`.

---

## 7. Two defects found in OTHER agents' live work, reported not touched

Both reproduce on the shared tree and both are in files with uncommitted changes, so
someone is mid-edit in each. **Reported to team-lead immediately; neither touched.**

1. **`app/Services/Risk/AutoRiskCalculator.php:318` is fatal.** It references
   `FamilyMember::` and `App\Models\FamilyMember` is **not imported**, so PHP
   resolves `App\Services\Risk\FamilyMember` and throws *"Class not found"*.
   **Anything triggering a risk recalculation currently fatals** and will surface in
   unrelated suites as mystery red. This is the `tests/CLAUDE.md` §2 trap exactly:
   the formatter deletes an import that is unreferenced at the moment it runs.
   `php -l` passes; the file is valid PHP. Fix is to add the import **in the same
   edit as a reference**, then `grep -n '^use '` to confirm it survived.

2. **`tests/frontend/components/NetWorth/Property/PropertyForm.test.js` — 2 failing.**
   `mortgageForm.ownership_percentage` expects `50`, receives `30`.
   `PropertyForm.vue` has 73 uncommitted insertions; this batch touched nothing under
   `resources/js/components/NetWorth/Property/`. Reproduces in isolation, so it is a
   real failure and not parallel-run contention (`tests/CLAUDE.md` §5). Almost
   certainly the W-0228 ownership batch mid-flight.

---

## 8. Files this batch owns

**New:** `app/Constants/PensionDisclosure.php` — **the one home for what the
application says when it shows a figure that excludes Defined Benefit schemes** ·
`tests/Feature/Retirement/PensionProvisionAndValuationTest.php`

**Deleted:** `MobileDashboardAggregator::pensionProvision()` and its two
constructor dependencies (`PensionStore`, `PensionProjector`)

**Modified — backend:** `app/Agents/RetirementAgent.php` ·
`app/Agents/CoordinatingAgent.php` ·
`app/Services/Mobile/MobileDashboardAggregator.php` ·
`app/Services/NetWorth/NetWorthService.php` ·
`app/Services/Plans/RetirementPlanService.php` ·
`app/Http/Controllers/Api/RetirementController.php`

**Modified — shared frontend:** `resources/js/utils/retirementHeadline.js`

**Modified — web:** `resources/js/components/NetWorth/NetWorthOverview.vue`

**Modified — `/m`:** `resources/mobile/views/modules/Retirement.vue` ·
`resources/mobile/views/modules/NetWorth.vue` ·
`resources/mobile/views/modules/NetWorthCategory.vue`

**Modified — tests:** `tests/Unit/Services/Mobile/MobileDashboardAggregatorTest.php` ·
`tests/frontend/mobile/Retirement.test.js` ·
`tests/frontend/utils/retirementHeadline.test.js`

**Filed, not built:** `workforce/ops/board/W-0311-native-net-worth-category-still-calls-pension-capital-accessible.md`

**NOT modified, deliberately:** `app/Services/LifeStage/LifeStageService.php`
(W-0242's) · `resources/js/components/NetWorth/PensionList.vue` (W-0245's) ·
`ios-native/` (W-0243's) · `app/Services/Risk/AutoRiskCalculator.php` (another
agent's, and broken — §7)

---

## 9. Every `success === false` retirement consumer, enumerated

Required as a deliverable by W-0244's acceptance clause 2, **before** the return
shape changed. Eleven call sites across eight files; each was read, and each was
then exercised against the real new shape rather than reasoned about.

| # | Consumer | `file:line` | Branch on failure, before | After |
|---|---|---|---|---|
| 1 | `RetirementController::analyze()` | `RetirementController.php:220` | returns the failure envelope verbatim | happy path; **nulls no longer coerced to zero** (§6.5); `has_retirement_target` + `guaranteed_annual_income` added |
| 2 | `RetirementController::recommendations()` | `RetirementController.php:261` | returns the failure envelope | happy path; generates zero recommendations — **verified, no fabrication** |
| 3 | `RetirementController::scenarios()` | `RetirementController.php:286` | returns the failure envelope | **unchanged** — reads `buildScenarios()`, which still refuses without a target, correctly |
| 4 | `RetirementPlanService::generatePlan()` | `RetirementPlanService.php:40` | empty plan shell + `error` | builds a real plan; **two fabrications fixed** (§6.4) |
| 5 | `RetirementPlanService::getRecommendations()` | `RetirementPlanService.php:101` | `empty($data)` → `[]` | data present; zero recommendations |
| 6 | `DashboardAggregator::getRetirementAnalysis()` | `DashboardAggregator.php:204` | `null` → zeros + no alerts | returns data; **target-derived alerts stay silent** (null gap, null `retires_before_spa`); the factual unused-annual-allowance alert can now fire |
| 7 | `MobileDashboardAggregator::extractRetirementSummary()` | `MobileDashboardAggregator.php:252` | **the W-0238 workaround** | **workaround deleted**; reads the agent only |
| 8 | `CoordinatingAgent::orchestrateAnalysis()` | `CoordinatingAgent.php:549` | recommendations skipped; `mapRetirementAnalysis` on `[]` → zeros | real facts; `guaranteed_annual_income` added to the flat shape |
| 9 | `CoordinatingAgent::requiresQuestionScopedFallback()` | `CoordinatingAgent.php:2385-2397` | matched `'no retirement profile'` → question-scoped fallback | no longer fires for this case; **string still live via `buildScenarios()`** (§6.7) |
| 10 | `ToolResultContract::validate()` Path 0 | `ToolResultContract.php:109-112` | **Fyn's tool result was the refusal message** | validates the full six-key contract; **Fyn now receives the pension facts** |
| 11 | `ModuleSummaryController::getModuleSummary()` | `ModuleSummaryController.php:99` | passed the whole failure envelope to `/m`'s module page | passes the facts |

**Fyn's context layer was never blind.** `AdvicePromptBuilder:810-833` reads
`PensionStore` directly and always had the schemes. What was blind was Fyn's
**tool** result (#10) — the `get_module_analysis` path, which received
*"No retirement profile found"* and nothing else. Worth separating, because
"Fyn's retirement context" names two different mechanisms and only one was broken.

**Every `transfer_value` reader deleted:** one —
`MobileDashboardAggregator.php:427`. `LifeStageService.php:211` was already removed
by W-0242's agent before this batch started (verified, not assumed). The remaining
matches in `ISAAllowanceOptimizer`, `BedAndISACalculator` and
`BedAndISATransfers.vue` are an **unrelated** Bed-and-ISA array key, not the pension
column — checked, correctly left alone.

---

## 10. Test evidence

| Run | Result |
|---|---|
| `tests/Feature/Retirement/PensionProvisionAndValuationTest.php` (new) | **18 passing**, 59 assertions |
| `tests/Unit/Services/Mobile/` | **108 passing** |
| `tests/Unit/Agents`, `tests/Feature/Api`, `tests/Unit/Services/AI` | **994 passing**, 6,319 assertions |
| `tests/Unit/Services/Retirement/`, `tests/Feature/Retirement/` | **144 → 180 passing** after the new suite |
| `tests/Feature/NetWorth` | **33 passing** |
| `tests/Feature/Dashboard/`, `Feature/Plans`, `Feature/Mobile` | **132 passing** |
| Architecture suite (incl. `PensionStoreBoundaryTest`) | **149 passing**, 28 deprecated, 1 skipped |
| `tests/frontend/` full vitest suite | **769 passing**, 63 files, **zero failures** — the two `PropertyForm` failures noted in §7.2 were fixed by their owning agent during this batch |
| `./vendor/bin/pint` on every touched path | passed; imports re-checked after formatting |

**Three tests were changed rather than made to pass, each with the reason recorded
in the file:**

1. `MobileDashboardAggregatorTest` — "reports a real pension pot as active even when
   there is no retirement profile" **mocked the agent returning `success: false`**.
   It was pinning the workaround. It now mocks the shape the agent really returns.
2. `tests/frontend/mobile/Retirement.test.js` — mocked
   `POST /api/retirement/analyze` returning `success: false`, a response the backend
   can no longer produce. Rewritten around the real payload, **with the nulls
   preserved**, because a fixture that softened them to zeros could not catch §6.5.
3. The same file's `projectedIncome` cases were left intact and **new** hero cases
   added beside them, rather than edited — the old rule still holds for a household
   with a pot.

**None was a red test made green.** Each asserted a behaviour a CSJ ruling
deliberately changed.

### The test-design trap, and the fact that it caught me

`tests/CLAUDE.md` §4 names four variants of *a test that shares the code's
misconception cannot fail*. **Two of them applied here and one of them bit.**

- **Collision.** Sarah's Defined Benefit pension reads £0 under the phantom-column
  bug **and** £0 under the correct exclusion. *"Assert it is £0"* proves nothing. So
  the W-0241 assertions are on the **disclosure flag** and on a **Defined
  Contribution figure that moves** — and one test adds a Defined Benefit scheme to a
  populated household and asserts the net worth is **unchanged** while
  `has_db_pensions` flips `false → true`. What changes is what the user is told, not
  what they are worth, and that is what is measured.
- **Fixture — and it caught me.** The first version of the new suite passed 9 of 12
  and the three failures revealed that **several of my own tests were exercising the
  readiness gate, not the missing-profile branch they were named for**, because a
  bare `User::factory()` has no income and `checkIncome` is *blocking*. The fixtures
  now state their income deliberately, with a comment saying why, and the readiness
  branch is covered **once, explicitly, by name**. Left unnoticed, this suite would
  have claimed coverage of a branch it never entered — the exact failure §4 warns
  about, turned on the test author.

**Both directions are asserted throughout**, per the ruling: a household with
pensions is never told it has none, **and** a household with no pensions still is.

---

## 11. Browser evidence — live, both accounts, web and `/m`

Run 2026-08-22 22:00–22:08 on `localhost:8000`, Playwright, after the `/m` bundle
rebuild. Web at 1440×900, `/m` at 390×844.

**Session note, stated rather than glossed:** the tab arrived already authenticated as
Sarah. I verified the identity and that the page was settled before touching anything,
then ran her **read-only** pass on that session rather than re-authenticating. David
was a full deliberate login — form filled, submitted, MFA code taken from the database.

### Sarah Jones (17) — a household whose whole provision is an income

| Surface | Observed | |
|---|---|---|
| web dashboard | Net worth **£739,280** · **£861,780** assets · RETIREMENT **£35,000/year "Guaranteed retirement income"** | ✓ |
| web Wealth Summary | Pensions column reads **"Defined Benefit only"**; Total Assets £861,780; Net Worth £739,280 | ✓ |
| web `/net-worth/retirement` | **"Guaranteed Retirement Income · Total Annual Income £35,000/year"**, NHS Pension Scheme, lump sum £105,000 | ✓ |
| `/m` net worth | £739,280 · £861,780 · **disclosure renders** | ✓ |
| `/m` asset list | property £637,500 (74%) · investments £132,500 (15%) · **pensions £0 (0%)** · cash £31,030 (4%) · valuables £60,750 (7%) — **sums to £861,780, percentages total 100%** | ✓ |
| `/m` pensions category | subtitle **"Defined Contribution pension value. Defined Benefit schemes are listed with the income they pay, not a capital value."** · disclosure · NHS Pension Scheme **£0** + **"£35,000 a year"** · **"Accessible pension capital" absent** | ✓ |
| `/m` retirement | hero **"Guaranteed retirement income £35,000 a year"** (was "Projected retirement income £0"), shortfall £61,660 against her £96,660 derived target | ✓ |

**Before this batch that `/m` list summed to £1,666,780 against a stated £861,780 with
percentages totalling 193%.** It now reconciles to the penny.

### The consolidation is proven, not inferred

The disclosure sentence has **zero occurrences** in `public/m-build/assets/main-DTjymbsC.js`.
It renders on `/m`. **The bundle cannot have supplied it**, so it came from
`PensionDisclosure` via the backend. That is the difference between a screen that looks
right and a screen that proves something.

### Not clipped — measured on the rendered element, at every width

**Reading the CSS is not the check.** The risk-engine agent proved that the same day:
its factor disclosure was clipped at `scrollHeight` **48** against `clientHeight` **32**,
and switching to the short constant did **not** fix it — the length was never the
problem, the disclosure had been **appended to another sentence** inside a
`line-clamp-2` container. The fix was its own field on its own unclamped line.

So every live consumer of these constants was measured, not inspected:

| Element | Surface | Viewport | Result |
|---|---|---|---|
| `.mnwc-disclosure` | `/m` pensions category | 390×844 | `scrollHeight 59 === clientHeight 59` — **not clipped** |
| `/m` subtitle | `/m` pensions category | 390×844 | `scrollHeight 51 === clientHeight 51` — **not clipped** |
| `.mnw-note` | `/m` net worth | 390×844 | renders, wraps, no clamp |
| `.db-pension-note` | web Wealth Summary | 1440 · 1024 · 768 | 227/259 · 123/155 · 98/110 — **never overflows its column**, always inside the viewport |
| `.db-only-message` | web Wealth Summary | 1440 · 1024 · 768 | 107/259 · 107/155 · **107/110** — not clipped, but see below |

All: `webkitLineClamp: none`, `overflow: visible`. The `/m` blocks wrap normally at
13–14px; the web cells are `white-space: nowrap` but the text fits at every width
tested.

**One observation, not a defect:** `.db-only-message` ("Defined Benefit only") measures
**107px inside a 110px column at 768px** — three pixels of headroom, inside a `nowrap`
parent. It is not clipped today at any width tested, but it is the one disclosure on
these surfaces with no margin left, and a longer label or a narrower column would push
it out. Raised for design-lead alongside the 9px note; **not changed** — neither is
W-0241's, and both currently disclose.

### David Jones (16) — the negative case, asserted rather than glanced at

| Surface | Observed | |
|---|---|---|
| web dashboard | Net worth **£1,489,500** · **£1,660,000** assets · RETIREMENT **£500,000 "Your pension pot"** (pot leads, correctly) | ✓ |
| `/m` net worth | £1,489,500 · £1,660,000 · **`document.querySelectorAll('.mnw-note').length === 0`** | ✓ |
| `/m` asset list | property £755,500 (46%) · investments £172,500 (10%) · **pensions £500,000 (30%)** · cash £99,750 (6%) · valuables £132,250 (8%) — **sums to £1,660,000, percentages total 100%** | ✓ |
| `/m` pensions category | **`.mnwc-disclosure` count 0** · £500,000 across two Defined Contribution pensions (£180,000 + £320,000) · no "Accessible pension capital" | ✓ |

**The absence was asserted on the ELEMENT, not on the text**, so a hidden-but-present
or empty-but-rendered node would have failed it. A disclosure that fires for a
household with no Defined Benefit scheme is exactly as wrong as one missing for a
household that has one, and it is the direction nobody checks because nothing looks
broken.

### Not covered by this pass

- **Native.** No simulator run; the two Swift gaps are filed as W-0311 and the figures
  are correct from the backend regardless.
- **`NetWorthOverview.vue`.** Dead code — could not be verified because it cannot be
  reached. See §6.12.
