---
id: F-0033
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web]
consistency_checked: 2026-08-23T05:50:00Z
status: active
---

**Rule 22 handover for the predecessor agent:** [`HANDOVER-fix-cycle4-wills-2026-08-23.md`](HANDOVER-fix-cycle4-wills-2026-08-23.md)
— read before this document; its §2 lists the four branches this persona cannot
reach and its §6 the dead ends not to re-walk.

# F-0033 — Cycle 4: the charitable saving, and the percentage denominator

**Agent:** build-lead (`fix-cycle4-figures`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0451 (the saving), W-0452 (the percentage) · **ID block:** W-0461 – W-0470
**Branch number:** F-0033 taken because **F-0032 is doubly taken already** —
`F-0032-cycle4-pension-holdings-entry-and-display.md` and
`F-0032-cycle4-rate-literals-and-the-charitable-denominator.md`. Declared to
team-lead before claiming rather than chosen silently.

**Predecessors, read first:** [`F-0031`](F-0031-cycle4-charitable-figures.md) ·
[`F-0032`](F-0032-cycle4-rate-literals-and-the-charitable-denominator.md) ·
the two statutory verdicts at `workforce/ops/handoffs/W-0399/` and
`workforce/ops/handoffs/W-0432/`.

---

## 1. THE HEADLINE LESSON — the two items were one disease, and fixing either alone leaves the page contradicting itself

They arrived as two items: a saving computed on the wrong base, and a percentage
computed on the wrong estate. **They are the same defect seen from two angles,
and the arithmetic proves it.**

The decision trace prints `$ctx['taxable_estate']` — the **household** chargeable
estate from `IHTCalculationService::calculate()` — beside a saving derived from a
baseline `EstateAgent` struck on the **individual's** net estate. So the £14,771
gap in W-0451 is not one error but two compounded:

| | |
|---|---|
| **wrong base** | the differential on the baseline, where the sentence promises the reduction in the bill |
| **wrong estate** | that baseline built on one person's assets and two people's nil rate band |

**Fixing the base alone leaves a sentence that still cannot be checked**, because
the two figures either side of the subtraction come from different households.
**Fixing the estate alone leaves the 43% arithmetic error untouched.** Either fix
shipped alone would have produced a green test suite, a plausible screen, and a
figure still wrong.

> **When two items name the same quantity, check whether one is the other's
> denominator before agreeing to fix them separately.**

The dispatch named W-0452's axis as *"whose estate is the denominator"*. Measured
against the code it has **two** axes, and the reviewer's C6 and the browser
measurement each found a different one:

- **numerator** — the survivor's will versus the logged-in user's. Coincides on
  this persona (£10,000 each) and on every single-person household.
- **denominator** — the household net estate versus the individual's. The 5× the
  browser measured.

Both had to move. Flagged to team-lead before starting rather than discovered in
the gate.

---

## 2. Prior art

Checked 2026-08-23 across all six sources: `registry/capabilities.md`, the code,
custom artisan commands, open PRs and in-flight branches, the vault,
`.claude/skills|agents` — **and `workforce/ops/board/`, swept by subject**, which
the 2026-08-23 addition to `FORMATS.md` makes mandatory.

| Quantity | Prior art found | Outcome |
|---|---|---|
| the charitable baseline and threshold | `IHTCalculationService::determineIHTRate()` — already the one home, already correct, already cleared by two statutory verdicts | **route** — every other reader onto it |
| the survivor resolution | `IHTCalculationService::survivingMember()` | **route** — publish its answer rather than expose the helper (see §4) |
| the charitable percentage | one division in `determineIHTRate()`, one in `EstatePlanService::charitablePercentage()` | **retire** the second |
| the rate saving | three: `WillAnalysisService`, `EstateAgent`, `IHTPlanning.vue` | **none existed that was right** — build one definition, retire three |

**One mechanism was added: the saving definition.** Everything else routes or
retires. The three it replaces gave three different answers and none of them
answered the question its sentence asked.

### Three queued board items on this subject, and why none of them is built past

**A queued item is a decision someone has already taken.** All three were read in
full before any code was written.

- **W-0139** (queued, high) — *"the charitable position stated four different
  ways"*. Its acceptance 4 — *"the percentage on `/plans/estate` and
  `charitable_giving_percent` from the server are the same number"* — **is what
  W-0452 delivers**, so this batch advances a subset of W-0139 rather than
  building past it. **Its acceptance 1 is partly superseded**: it asks for the
  £20,000 household total to feed *the rate test*, which the 2026-08-21 statutory
  ruling forbids (summing both wills over-qualifies households for 36%). Someone
  should re-word it. Not mine to edit, and flagged to team-lead.
- **W-0369** (queued) — whether the residence nil rate band belongs in the
  Schedule 1A baseline. **The W-0432 verdict already answered it** (Q1 advisory 2:
  ss.8D–8M are a separate mechanism and FA 2015 did not amend para 5; the code is
  right). W-0369's own acceptance asks only that the reasoning be recorded at
  `IHTCalculationService:1195`. **This batch does not touch the baseline**, so the
  question stays open exactly as it was.
- **W-0154** (queued, critical) — *"one household, one answer"*. This batch moves
  `/plans/estate`'s charitable panel onto the household calculation, advancing its
  first criterion for that one panel. No claim on the item.

### W-0399's Q2 coupling: undisturbed, and checked rather than assumed

> *If any future change makes the model actually settle the first death, the
> pooled section 23(1) exemption must be removed in the same change, or the first
> legacy is relieved twice.*

Nothing here settles the first death. `$charitableAmount` (pooled) reaches
`charitable_deduction` → `$taxableEstate` by the same untouched path;
`$rateTestAmount` (the survivor's) is still what the 10% test compares. The two
halves of the coupling are both where the verdict left them.

### C2 verified, as team-lead asked

**It holds.** `IHTCalculationService:1400` computes
`$charitablePercent = $rateTestAmount / $baseline` **once, after both branches**,
so `charitable_giving_percent` no longer means "÷ baseline" on the bequest path
and "÷ net estate" on the profile path. Not reopened.

---

## 3. Rule 19 — checked, not assumed

`grep` across `resources/mobile/` and `ios-native/` finds **zero** consumers of
`charitable_analysis`, `charitable_giving`, `potential_saving`,
`charitable_giving_percent`, `charitable_baseline`, `charitable_threshold` or
`charitable_rate_test_amount`. The only consumers are
`resources/js/components/Plans/Estate/EstateCurrentSituation.vue:75-92`,
`resources/js/components/Plans/Shared/planPrintMixin.js:2283` and
`resources/js/components/Estate/IHTPlanning.vue` — all desktop web.

Matches the board's `surfaces: [web]` and the independent check in the W-0399
verdict. **Not inherited from that verdict — re-run, because the payload changed.**

---

## 4. What changed

### The one definition

`IHTCalculationService::assessTaxPosition()`:

```
saving = standard × E  −  reduced × (E − shortfall)
```

where `E` is the chargeable estate the service already computes and `shortfall`
is the distance to the Schedule 1A threshold.

**One formula, both branches.** For an estate already qualifying the shortfall is
zero, both bills sit on the same chargeable estate, and the difference collapses
to the rate differential on it — which is what "the reduced rate is worth" means
for someone who already has it. In both branches **one of the two bills is the
actual `iht_liability` and the other is the counterfactual**, which is the
property that makes it checkable.

**Why the shortfall leaves the estate.** Increasing the gift does two things, and
the old sentence modelled only one: the rate falls **and** the gift itself leaves
the estate under the section 23(1) exemption. A sentence printing ONE base for two
bills could not be made to add up, whatever base it chose.

**Why the threshold does not move with the gift.** Schedule 1A para 5 adds the
donated amount back into the baseline, so `baseline = netEstate − availableNRB` is
independent of what is given. That is what makes one subtraction the whole answer,
and it is pinned by its own case.

### The routings

| | Change |
|---|---|
| **1. `determineIHTRate()`** | Publishes `shortfall`, `qualifies` and `has_unvalued_charitable_gifts` — the last asked of the **survivor**. **The three return branches now share one array**, so a key cannot be added to two of them and forgotten in the third; the third branch carried a comment recording the last time that happened. |
| **2. `assessTaxPosition()`** | The saving definition, plus `charitable_taxable_estate_if_qualifying` — published rather than left to be subtracted downstream, so a sentence saying "36% of £X" can print the £X it multiplied. |
| **3. `WillAnalysisService::analyzeCharitableBequests()`** | **Computes nothing.** New signature `(array $ihtCalculation)`. It struck its own baseline from whatever the caller passed; the caller passed the individual's net estate with the household's band. It needs no `User` because *whose will* is settled before it is called. |
| **4. `EstateAgent`** | Passes `$ihtCalculation`. **Skips the analysis when the calculation failed** — there is no charitable position to describe for an estate we could not compute, and producing one from a second source is the parallel mechanism being removed. |
| **5. `EstateAgent::step1CharitableBequestCheck()`** | The sentence rewritten so the subtraction the reader performs IS the subtraction the application performed. Also routes the `?? 0.36` and the five hardcoded `10%` literals in the lines being rewritten (verdict C4/C5). |
| **6. `EstatePlanService`** | `charitablePercentage()` **deleted**; it reads the published percentage. **There is now exactly one division producing that figure in the application.** |
| **7. `IHTPlanning.vue`** | The fourth mechanism — see below. |

### The sentence, before and after

Before, live for user 16:

> *"On the taxable estate of £858,780: at 40% = £343,512, at 36% = £309,161 —
> saving £19,580."*

After, on the F-0033 fixture:

> *"If Patricia increases charitable bequests by £82,750 (to reach £114,500), the
> Inheritance Tax rate drops from 40% to 36% and the additional £82,750 leaves the
> estate as an exempt gift. As the will stands: 40% of the taxable estate of
> £1,108,320 = £443,328. With the larger gift: 36% of £1,025,570 = £369,205.
> Saving £74,123."*

£443,328 − £369,205 = £74,123. Every figure in the sentence is derivable from the
figures beside it.

### Why `survivingMember()` was NOT exposed, though the acceptance names it

W-0452's acceptance asks for *"`survivingMember()` (or an equivalent) exposed once
and read by both, not re-implemented."*

**Publishing the ANSWER is the better equivalent, and it is why the `(or an
equivalent)` is in the criterion.** Exposing the helper would have required
`EstateAgent` to re-derive the pooling condition (`isMarried && spouse !== null &&
dataSharingEnabled`) in order to call it — a second copy of the decision that
selects the survivor, which is the defect one level up. Instead
`determineIHTRate()` resolves the survivor once and publishes everything derived
from that resolution, so **no second caller ever needs to know who the survivor
is.** The private helper stays private and gains no second consumer.

Stated here because it is a deviation from the literal wording of an acceptance
criterion, and the reviewer should judge the substitution rather than discover it.

---

## 5. THE FOURTH MECHANISM — found in the frontend, and it is why this is a Rule 20 fix

`IHTPlanning.vue:962` computed the saving **in the browser**:

```js
const currentIHT = taxableEstate * this.ihtStandardRate;
const reducedIHT = taxableEstate * this.ihtReducedRate;
return currentIHT - reducedIHT;
```

rendered under *"If you left £X or more, your rate would fall to 36% and your
estate would pay about **£Y less**."*

**Two things were wrong, and neither is visible from that file.**

1. **It is the wrong answer to its own sentence.** Leaving £X does not only change
   the rate — the gift leaves the estate too. Holding the estate still understates
   the saving.
2. **It was a fourth answer**, beside the decision trace's, `/plans/estate`'s and
   `WillAnalysisService`'s. Four mechanisms, one quantity, four numbers.

Under Rule 20 routing it is **part of this fix, not a follow-up**. It now reads
`charitable_rate_saving`.

**And it needed a line in the hand-written mapping** (`:1740`) — the **fifth**
instance of the allowlist shape this cycle. Written in the **same edit** as the
computed that reads it, because the tell is now known: *adding a computed that
reads a key means checking the key arrives.* Without it the card renders "£0 less"
and the whole block vanishes behind its own `v-if` — **the quietest way this
failure can present**, and the one no screenshot would catch, because there is
nothing on the page to be wrong.

---

## 6. Tests

| File | Cases | What it guards |
|---|---|---|
| `tests/Unit/Services/Estate/CharitableRateSavingReconcilesTest.php` (new) | 12 | the whole chain, driven for real: `calculate()` → `EstateAgent::analyze()` → `generateRecommendations()` → `EstatePlanService::generatePlan()`, from **both spouses' sessions** |
| `tests/Unit/Services/Estate/WillAnalysisCharitableBequestTest.php` | 5 rewritten | the same intents, moved onto the layer where the decision now lives |
| `tests/Unit/Services/Estate/RateLiteralsComeFromConfigurationTest.php` | 3 rewritten | the Rule 2 guard, driving the real calculation |
| `resources/js/components/__tests__/Estate/IHTPlanningCharitableCard.spec.js` | +1 | the card reads the published saving — in the **real-lifecycle** block, not `setData` |

### The join is the layer with no tests, and this batch is the proof

`EstatePlanRefactorTest` mocks `EstateAgent`, `IHTCalculationService` and
`TaxConfigService` and hands `charitable_analysis` in as a fixture.
`IHTPlanningCharitableCard.spec.js` injects `ihtData` past the mapping.
**Both ends were tested and neither could see whose estate produced the figure.**
Every new case drives the real chain end to end.

### The axis each case varies, named

**An asymmetric fixture is only asymmetric along the axis you varied.** Three axes
matter and the Bennett fixture varies all three: whose will (£31,750 against
£4,930), whose estate (£412,000 against £903,000), **whose session** (every
published-figure case reads from both accounts).

The predecessor fixture varied the legacies and **always read from the survivor's
session** — which is how W-0433's "one definition read by both surfaces" came to
be ticked while unmet.

### The gap the acceptance asked for

baseline £1,145,000 − chargeable estate £758,320 = **£386,680 = £350,000 residence
band + £36,680 charitable gift** — exactly the reviewer's identity, and not a
round number. On the persona the gap was the residence band plus a round £10,000
legacy, which is guessable.

### The three candidates are distinct, and one case exists to say so

| Quantity | On this fixture |
|---|---|
| differential × baseline | **£45,800.00** — what was published |
| differential × chargeable estate | **£30,332.80** — what was printed as working |
| the actual tax reduction | **£60,122.80** — what the sentence promises |

Smallest pairwise gap **£15,467.20**, against a tolerance of one penny.
`it('discriminates …')` asserts this, and **without it every other case in the
file proves nothing.**

### Eight mutations, and the two that survived the first pass

| Mutation | Reddened |
|---|---|
| M1 both bills on the baseline, printed base unchanged | reconciliation · chargeable-base · arithmetic pass |
| **M2 saving from the baseline differential — THE ORIGINAL DEFECT** | reconciliation · discriminates · qualifying · moved-rate |
| M3 rate test pools both wills | numerator · the three W-0399 pins |
| **M4 survivor is the logged-in user — THE W-0452 AXIS** | both-sessions · plan-percentage · unvalued · a W-0399 pin |
| M5 unvalued gift asked of the logged-in user | the unvalued case — **only after it read from both sessions** |
| M6 differential re-hardcoded at 0.40 / 0.36 | both moved-rate cases |
| M7 percentage over the net estate | numerator · the three W-0433 pins |
| **M8 the WHOLE sentence on the baseline** | discriminates · **chargeable-base, only after the structural assertion was added** |

**M5 and M8 both survived the first pass, and both were faults in my own cases.**

**M5 is the one worth carrying forward.** The unvalued-gifts case read only from
the survivor's session, so a mutation reading the logged-in user's will passed it
— *because from her session the logged-in user IS the survivor.* **That is the
exact blindness the reviewer named on W-0433, reproduced by the person who had
just written it up, one screen below a docblock describing it.** Found by mutation
testing, not by reading the file.

> **Documenting a blind spot does not inoculate you against it.** The
> countermeasure is mechanical — restore the bug and watch — not attentional.

**M8 is the one that keeps a claim honest.** I wrote in the file that the
reconciliation case *cannot* discriminate a consistently-wrong base. M1 reddened
it anyway, so the claim as written was false — M1 leaves the printed base correct,
and the case checks each bill against its own printed base. The claim is true of
**M8**, which moves the bills *and* the bases together. Rather than delete the
sentence I built the mutation that makes it true, measured it, and the file now
says which mutation it means. **A claim about what a test cannot see is itself a
claim, and it needs a measurement.**

### What is asserted, and how

- **The reconciliation is parsed out of the RENDERED SENTENCE**, not read from the
  array that produced it. Reading the array would pass against a sentence printing
  something else — which is precisely what happened.
- **Each bill is checked against its own printed rate and its own printed base**,
  so the sentence's internal working is asserted, not just its conclusion.
- **Tolerance is one penny** on float products and **£1** on the parsed sentence
  (`number_format` to whole pounds can move each figure by 50p). The error being
  caught was £14,771 — the hypotheses are four orders of magnitude apart, so
  neither window can span both. On this household the reconciliation is exact.
- **The percentage is asserted as a reconciliation** (`percent ÷ 100 × baseline ==
  rate_test_amount`) with a companion proving **the wrong denominators give
  different answers here** — from *both* accounts. A reconciliation with no
  discriminating companion stops discriminating silently.

**Suites:** `Unit/Services/Estate` · `Feature/Estate` · `Unit/Services/Plans` ·
`Feature/Plans` · `Unit/Services/Investment` · `Unit/Agents` → **882 passed
(2925 assertions)**. Vitest Estate → **169 passed, 12 files**. Pint clean.
`DB_DATABASE=laravel_testing_v`, single process.

---

## 7. What this batch does NOT fix, named rather than left

- **`GiftingStrategy:227` is a fifth mechanism** —
  `round($taxableEstate * ($ihtRate - $reducedRate), 2)` under a "Charitable
  Giving" recommendation, where `$taxableEstate` is `estateValue − totalNrb`, i.e.
  the baseline. **`recommendOptimalGiftingStrategy` has ZERO production callers**
  — `grep` across `app`, `routes` and `resources` finds its definition and four
  test call sites, nothing else. **No user sees its figure.** Not fixed: editing
  dead code inside a batch cleared for something else is scope creep, and whether
  it is deleted or routed is its own decision. **Recorded so the next reader does
  not find a fourth formula and conclude the consolidation was incomplete.**
- **`RecommendationPersonaliser:147`** computes `netEstate − nrb` from a single
  band. It is a narrative sentence that prints the band beside the figure, so it
  self-reconciles, and it is not a Schedule 1A baseline. W-0154 family.
- **Three percent-label formatters** now exist across `IHTCalculationService`
  (a closure), `WillAnalysisService` (`rtrim(rtrim(number_format(…)))`) and
  `EstateAgent` (`round(× 100)`). A formatting idiom, not a value — but three
  copies is a real Rule 20 candidate, and consolidating it would touch code two
  verdicts have cleared. Observation, not a claim.
- **W-0139's acceptance 1 needs re-wording** — it asks for the pooled household
  total to feed the rate test, which the 2026-08-21 ruling forbids.

---

## 7a. MEASURED ON THE PERSONA, 2026-08-23 05:55 — computed, not rendered

Run against the local database through the real services, with
`estate_analysis_16` / `_17` cleared before and after (W-0381). **This is the
calculation, not the page** — the browser reading is still owed and is a separate
claim. Figures are a snapshot: other agents are live in this tree, so the durable
claims are the **relationships**, not the amounts.

```
total_net_estate                        1,728,780     user 16 (David) net estate    989,500
nrb_available                             500,000     user 17 (Sarah) net estate    739,280
rnrb_available                            350,000     charitable_deduction (pooled)  20,000
charitable_baseline                     1,228,780     rate_test_amount (survivor)    10,000
charitable_threshold                      122,878     charitable_shortfall          112,878
taxable_estate                            858,780     …if_qualifying                745,902
tax_at_standard_rate                      343,512     tax_at_reduced_rate           268,524.72
charitable_rate_saving                     74,987.28  charitable_giving_percent          0.8138…
```

**W-0451 — it reconciles.** £343,512 − £268,524.72 = **£74,987.28**, exactly the
published saving. The board's sentence printed *"at 36% = £309,161 — saving
£19,580"* against the same £343,512.

**Four answers existed on this one household, and the published one was the
furthest from right:**

| | |
|---|---|
| £19,580 | published — the differential on the INDIVIDUAL baseline |
| £34,351 | the sentence's own printed working |
| £49,151.20 | the differential on the household baseline |
| **£74,987.28** | the actual reduction in the bill |

**On this household the application understated what a user would gain by
£55,407** — and the reviewer's finding stands that the direction varies by
household, so it could never have been defended as conservative.

**W-0452 — it agrees, from both accounts.** `/plans/estate`'s
`current_percentage` now reads **0.8** from user 16 **and** from user 17, where
Sarah's account read 4.2% against `/estate`'s 0.8%. The full charitable panel is
byte-identical from both sessions:

```
{"status":"below","current_percentage":0.8,"threshold":10,
 "shortfall":112878,"potential_saving":74987.28}
```

**The rendered sentence, user 16 and user 17, identical:**

> *"If David increases charitable bequests by £112,878 (to reach £122,878), the
> Inheritance Tax rate drops from 40% to 36% and the additional £112,878 leaves
> the estate as an exempt gift. As the will stands: 40% of the taxable estate of
> £858,780 = £343,512. With the larger gift: 36% of £745,902 = £268,525. Saving
> £74,987."*

Every figure is derivable from the figures beside it. **What this does NOT show
is the page** — see §8.

---

## 7b. THE RENDERED PAGE — done 2026-08-23, both accounts

**Identity established per token store before every reading**, via
`GET /api/auth/user` on `sessionStorage.auth_token` — never from
`fynla-state.auth.user`, and never from a relay. Confirmed **user 16 David Jones**
and **user 17 Sarah Jones** at the point of reading, each time.
`estate_analysis_16` / `_17` cleared by hand before each account's pass (W-0381).
Login and verification driven through the real forms, fill-and-click atomic in one
`browser_evaluate` per pass. Vite confirmed serving on 5173 before starting.

### W-0451 — the decision trace, `/actions/estate/estate_action_1`

**Rendered, David's session:**

> *"If David increases charitable bequests by £112,878 (to reach £122,878), the
> Inheritance Tax rate drops from 40% to 36% and the additional £112,878 leaves
> the estate as an exempt gift. As the will stands: 40% of the taxable estate of
> £858,780 = £343,512. With the larger gift: 36% of £745,902 = £268,525. Saving
> £74,987."*

**Checked as a reader checks it — parsed out of the rendered sentence, not read
from the payload:**

| Check | Result |
|---|---|
| 40% of the printed £858,780 = the printed £343,512 | **true** |
| 36% of the printed £745,902 = the printed £268,525 | **true** |
| £343,512 − £268,525 = the printed saving £74,987 | **true, exactly** |
| the second base is the first less the printed shortfall | **true** |

**This is the same sentence, on the same household, at the same £858,780 and
£343,512, that the board reported as *"at 36% = £309,161 — saving £19,580"*.**

**Identical from Sarah's session**, every check true, only the forename differing
— which is correct: the trace names the reader, the figures are the household's.
Screenshots `163-` (David) and `167-` (Sarah).

### W-0452 — the percentage, both accounts

| | David (16) | Sarah (17) |
|---|---|---|
| `/plans/estate` Current Charitable Rate | **0.8%** | **0.8%** (was **4.2%**) |
| Net Estate row on the same page | £1,728,780 | £1,728,780 |
| Shortfall to Qualify | £112,878 | £112,878 |
| Potential Saving | £74,987 | £74,987 |
| `/estate` rate message | *"charitable giving of 0.8% (£10,000)"* | *"charitable giving of 0.8% (£10,000)"* |

**0.8% is now derivable from the figure printed above it:** £10,000 ÷
(£1,728,780 − £500,000) = 0.814%. **The figure does not move between sessions** —
W-0452 acceptance 4. Screenshots `162-`, `165-`, `166-`.

### The fourth mechanism, and the one thing only the browser could settle

`/estate`'s charitable card, both accounts:

> *"If you left £122,878 or more, your rate would fall to 36% and your estate
> would pay about: **£74,987 less**"*

**Before this fix that line computed £858,780 × 0.04 = £34,351 in the browser.**
It now quotes the published figure — **and the block rendered rather than
vanishing.** That was the whole point of reading the page: a key dropped by the
hand-written mapping shows as `£0 less` and the block disappearing behind its own
`v-if`, which no figure check catches and no calculation can prove. Screenshots
`164-` and `166-`.

**Rule 9 verified on both pages, both accounts:** `/\bIHT\b/` is **false** across
the whole of `/estate` and `/plans/estate`.

### What the page showed that the calculation could not

**W-0461 is visible in these very screenshots** — *"Threshold for **36%** Rate"*
sits in the charitable panel of `162-` and `165-`, two rows above the figures this
batch corrected. Filed, not fixed, and the evidence for it is incidental to the
evidence for this one.

### FOUR THINGS THE BROWSER DID NOT EXERCISE — named, not implied

**A green screenshot of a page that never entered the branch is worse than no
screenshot.** All four are covered by tests only:

1. **The already-qualifying branch.** This estate is on the standard rate, so
   *"On the taxable estate of £E: at 40% = £X, at 36% = £Y — saving £Z"* never
   renders on this household.
2. **The unvalued-gifts suppression.** Needs a charitable gift of an asset or a
   residuary share; this household has neither.
3. **The profile-percentage branch.** Needs `charitable_giving_percent > 0` with
   zero recorded bequests. No seeded persona has that combination.
4. **THE NUMERATOR AXIS.** Both spouses leave £10,000, so survivor-or-self gives
   the same figure. **The page proves the percentage no longer moves between
   sessions; it cannot prove which will it came from.** Only the Bennett fixture
   expresses that axis, and only mutation M4 kills it.

**To exercise 2 or 3 you must build a household, not pick one.**

---

## 7c. C1 — THE ONE THING THIS BATCH INTRODUCED, AND THE LESSON IN IT

**The statutory gate cleared the formula and blocked the batch on an attribution
defect the batch itself created.** Recorded first because it is the most useful
thing here.

### What I did

I moved the numerator to the survivor's will and **did not move the label**. Every
sentence still named `$ctx['first_name']` — whoever is logged in. So on any
household where the reader is not the survivor, the application reported the
**survivor's** charitable position under the **reader's** name, and instructed the
reader to add a legacy to their **own** will.

**That instruction cannot produce the outcome it promises.** A legacy in the
first-to-die's will raises the pooled section 23(1) exemption and leaves
`$rateTestAmount` untouched, so `$qualifies` stays false and the rate stays
standard. The chargeable estate falls by the whole gift, the baseline does not
move, and **the identical instruction is issued again on the next run.**

### The lesson, which is not "check the names"

**My browser evidence contains the defect.** Screenshot `163-` is David's session
— David is the first-to-die, Sarah is the survivor (life expectancy 36 against 32)
— and the sentence reads *"If **David** increases charitable bequests by
£112,878…"* over Sarah's figures. **I read that sentence four times, parsed it
with a regex, checked its arithmetic to the penny, and never asked whose will it
was about.**

> **Every check I built asks whether the sentence is internally consistent. Not
> one asks whether it is about the right person.** A reconciliation is blind to
> the subject of the sentence it reconciles.

And the batch's own test asserts the half that is right and cannot see the half
that is wrong: `it('gives the same percentage from either spouse's session')`
asserts the two sessions agree on the **figures** — correct, they are household
figures — **and asserts nothing about the name attached to them.**

**The Q3 deviation is what left the gap, and the reviewer accepted the shape while
rejecting its premise.** Publishing the answer rather than exposing
`survivingMember()` was the right engineering call. But the premise it rested on
— *"no second caller ever needs to know who the survivor is"* — was false, and the
code proved it: **`EstateAgent` writes sentences with a name in them.** What was
published was amounts and booleans. The identity was not, so the sentence-writer
had nothing to name and fell back to the session.

### The fix

`determineIHTRate()` publishes `rate_test_member_id`,
`rate_test_member_first_name` and `rate_test_member_is_requesting_user` beside the
amount — **one resolution, its answer published whole**, the helper still private,
no second caller re-deriving the pooling predicate. The deviation stands; the gap
is closed.

**Four sites corrected, one fix:** the decision trace, the `actions` array, the
recommendation `description`, and `/plans/estate`'s panel.

**The panel disclosure is composed server-side as ONE `basis` key** and printed
verbatim by both `EstateCurrentSituation.vue` and `planPrintMixin.js`. It is
deliberately **not** written in each frontend: that pair already duplicates the
"Threshold for 36% Rate" label the reviewer flagged in C3, and adding a second
duplication in order to fix an attribution bug would be self-defeating.

**The disclosure fires only when the reader is not the survivor.** A note that
always appears is one a reader learns to skip.

### Verified, both sessions

| | David (16) — the first-to-die | Sarah (17) — the survivor |
|---|---|---|
| trace names | **Sarah** | **Sarah** |
| `description` | *"Increase charitable giving in **Sarah's** will…"* | same |
| second-death note | **present** | **absent** (correctly) |
| `/plans/estate` basis | *"These figures are for Sarah's will. The 10% test that decides the reduced rate looks only at the will operating on the second death."* | **absent** (correctly) |
| `"David's will"` anywhere | **false** | **false** |
| arithmetic still reconciles | **true** | **true** |

Screenshots `168-`, `169-`, `170-`.

**Not rendered on either page: the `actions` array.** `/actions/estate/*` prints
the trace and the description, `/plans/estate` prints the description; neither
prints the raw action strings. **Covered by test only** — stated rather than
implied.

### Mutations

| Mutation | Reddened |
|---|---|
| **M9 — C1 restored (`$rateTestName = $ctx['first_name']`)** | **exactly one case, and only that one** |
| M10 — the second-death disclosure suppressed | the same case |
| M4 — survivor is the logged-in user | five cases, now including the name case |

**M9 killing exactly one case is the result worth having:** the new case is
discriminating and nothing else in the suite depends on it.

---

## 8. The gate, and what it left behind

**VERDICT: CLEARED WITH CONDITIONS**, 2026-08-23 —
`workforce/ops/handoffs/W-0451/tax-compliance-reviewer-verdict-2026-08-23.md`.

**Q1 answered in the batch's favour.** *"The answer is the third: the actual
reduction in the Inheritance Tax bill. The batch chose correctly."* Grounded in
s7(1A) and Sch 1A paras 2, 4 and 5.

**The load-bearing statutory claim CONFIRMED, and confirmed by mechanism rather
than coincidence:** para 5 Step 1 yields (component value − gift) because the
value transferred is already net of the s23(1) exemption, and Step 3 adds the gift
back, so the gift cancels and `baseline = component value − available NRB`. **That
is what makes one subtraction the whole answer.**

**Q2 YES** — the survivor is the right numerator on the plans surface.
**Q3 accepted**, on condition the identity is published too. That was C1.

### Conditions, and their disposal

| | Condition | Disposal |
|---|---|---|
| **C1** | BLOCKING — the survivor's position attributed to the logged-in user | **DISCHARGED** — see §7c. Fixed, mutation-tested (M9 kills one case and only that one), verified on both sessions |
| **C2** | The word "saving" beside an action costing the family £37,891 | **FILED as W-0462**, HIGH, gate `compliance-lead`. Not fixed here — its gate is not the tax reviewer, and the reviewer asked for it to travel separately |
| **C3** | Four more Rule 2 instances | **ADDED to W-0461** as an addendum, including the tenth (`TaxSettingsController:330`) which is PHP and outside this item's frontend framing |
| **C4** | Correct `getCharitableReducedRate()`'s note again | **DONE** — see below |
| **C5** | W-0432's C2 can be closed | **RECORDED on W-0433** with the verification line |
| **C6** | Pooling-predicate divergence | **RECORDED on W-0154** — pre-existing, unreachable on all 12 linked users, newly load-bearing |
| **C7** | Advisory: label `charitable_giving_percent` | **RECORDED on W-0433**, no item, per the reviewer |
| **C8** | W-0139 needs re-wording | Already annotated by team-lead |

### C4 — and a check that degraded as it was documented

The note named four survivors and did not record that **`TaxSettingsController:330`
was named and left standing**. Corrected: it now lists which three were routed and
which one was not, and points at W-0461.

**And the note's own prescribed check has decayed.** It says the durable thing is
the command, not the count:

```
grep -rn '?? 0.36' app/
```

**That grep now returns five hits and four of them are the COMMENTS written to
explain the fixes — two of them inside the note itself.** The command is corrected
to exclude comments, and the observation is recorded because it is a fourth way a
completion claim stops a reader looking:

> **A grep-based check degrades as the fix it checks for gets documented.**

One cycle to appear.

### Scope of the clearance — do not let it travel

**Statute, code, a live calculation and the suites — NOT the screen** (the browser
was held throughout the review). And **only the charitable hunks**:
`IHTCalculationService.php` and `EstateAgent.php` both carry other batches' edits
which are explicitly **not** cleared. The rendered-page verification in §7b and
§7c is mine, not the reviewer's.

### Four things the persona could not exercise — unchanged by the fix

1. **The already-qualifying branch** — this estate is on the standard rate.
2. **The unvalued-gifts suppression** — no asset or residuary gift here. *(And the
   reviewer found it reaches no surface at all: `charitable_analysis['message']`
   has no consumer. A genuine gap in delivering that disclosure, not this batch's
   doing.)*
3. **The profile-percentage branch** — 0 of 2 `iht_profiles` rows carry a value.
4. **The numerator axis** — both spouses leave £10,000, so the page proves the
   figures no longer move between sessions and **cannot prove which will they came
   from.** Only the Bennett fixture expresses it; only M4 and M9 kill it.

**To exercise 2 or 3 you must build a household, not pick one.**

## 9. In flight

**Nothing.** Every edit applied, linted, covered and mutation-tested; all backups
restored and verified. No commit, no PR, no git lock.

**Scratch files:** `…/scratchpad/mutate-cycle4-figures/` (the mutation harness and
pristine copies). **Note for other agents:** the scratchpad's shared `backup/`
directory already held another agent's files; I copied four into it before
noticing and then moved to my own directory. Flagged to team-lead.
