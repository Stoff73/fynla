# W-0399 / W-0431 — tax-compliance-reviewer statutory gate

**Reviewer:** tax-compliance-reviewer · **Date:** 2026-08-23 · **Requested by:** team-lead
**Batch:** `workforce/branches/fixes/F-0031-cycle4-charitable-figures.md`
**Files in scope:** `app/Services/Estate/IHTCalculationService.php`,
`app/Http/Controllers/Api/Estate/IHTController.php`,
`resources/js/components/Estate/IHTPlanning.vue`

## VERDICT: CLEARED WITH CONDITIONS

No condition blocks the batch. No figure in it is wrong, no arithmetic moved, and
the 2026-08-21 statutory ruling is intact and now genuinely pinned. The conditions
are one false code comment that must not survive into the next reader's premises,
and three items to be filed — one of which (C2) is in the very sentence this batch
rewrote and is a real tax-accuracy defect.

---

## Scope boundary — read before quoting this clearance

The shared working tree carries **F-0026's projection edits in the same file**
(W-0331 / W-0333 / W-0336: `CrossModuleAssetAggregator` routing in
`getCurrentInvestmentValue`, `projectMemberInvestments`, `projectProperties`,
`projectMemberLiabilities`, and both `end_date` → `maturity_date` reads). Of the
348 changed lines in `IHTCalculationService.php`, roughly 40 belong to this batch.

**This verdict covers only the charitable-figures and rate-literal hunks.** The
projection hunks were reviewed under F-0026 and are not re-cleared here. One
incidental confirmation, since it touches a tax figure and was cheap: `mortgages`
has a `maturity_date` column and **no `end_date` column** (verified against the
live schema), so the previous read resolved to null. That correction is sound.

## Authority used

Per team-lead's warning about stale reviewer tables, I read the live configuration
rather than my own reference table. `TaxConfiguration` active row is **2026/27**
(not the 2025/26 my standing table is headed), and it holds
`standard_rate 0.40`, `reduced_rate_charity 0.36`, `charity_threshold_percent 0.10`,
`nil_rate_band 325000`. `TaxDefaults::IHT_CHARITABLE_RATE = 0.36` and
`IHT_CHARITY_THRESHOLD = 0.10` agree as fallbacks. These match IHTA 1984 s7(1A) and
Sch 1A, so on this occasion the table and the configuration do not conflict —
but the configuration is what I relied on.

## Verification I ran myself (not taken from the branch doc)

Isolated database `laravel_testing_taxrev`, created so as not to collide with the
other agents' runs.

| Suite | Result |
|---|---|
| `CharitableExemptionVersusRateTestTest` + `CharitableFiguresPublishedTest` | **11 passed**, 35 assertions |
| `tests/Unit/Services/Estate` + `tests/Feature/Estate` | **421 passed**, 1407 assertions |
| Vitest Estate (`resources/js/components/__tests__/Estate` + `tests/frontend/components/Estate`) | **166 passed**, 12 files |

421 matches the branch doc exactly. No code was changed by this review.

---

## Q1 — Is the s23(1) / Schedule 1A distinction correctly preserved, and has publishing the second figure disturbed the first?

**Yes to the first. No to the second.** Confirmed.

**The exemption is untouched.** `charitable_amount` (pooled) → `charitable_deduction`
→ `$taxableEstate = max(0, $netEstate − $totalAllowances − $charitableDeduction)` →
`iht_liability`. Not one of those lines appears in the diff. `determineIHTRate()`'s
two decision branches are unchanged apart from the message strings; the third branch
gained only the `charitable_rate_test_amount` key. The rate is still decided by
`$rateTestAmount` (survivor's will alone), never by the pooled figure.

**The distinction is right in law.**

- **s23(1) IHTA 1984** — a transfer of value attributable to property given to
  charity is exempt. Each spouse's legacy is exempt at the death on which it takes
  effect. Pooling is discussed under Q2.
- **s7(1A) + Sch 1A IHTA 1984** (inserted by FA 2012 Sch 33) — the 36% rate applies
  where the donated amount is at least 10% of the baseline amount, tested on the
  estate of **one deceased person**. On the first death of a spouse pair the estate
  is normally wholly exempt under s18, so no rate question arises and the
  first-to-die's legacy cannot be carried into the survivor's donated amount.
  **Summing both wills would over-qualify households for 36%.** The ruling of
  2026-08-21 is correct and the code implements it.

**Two statutory details the code gets right that are commonly got wrong, recorded so
the next reviewer does not re-derive them:**

1. **The donated amount is added back into the baseline.** `determineIHTRate()` is
   called with `$netEstate` *before* the charitable deduction, so
   `$baseline = max(0, $netEstate − $nrbAvailable)` includes the legacy. That is what
   Sch 1A para 5 requires. Deducting the gift first and then taking 10% of the
   remainder would understate the threshold and over-qualify estates.
2. **The residence nil-rate band is excluded from the baseline**, correctly; only the
   available nil-rate band (including the transferred proportion under s8A–s8C) is
   deducted. `WillAnalysisService:44-56` documents the same rule and takes the
   available figure from the caller rather than re-deriving a single band.

**The pinning is real, not decorative.** `unequalCharitableCouple(30000, 5000)` against
`unequalCharitableCouple(30000, 80000)`: the exemption moves £35,000 → £110,000 while
the rate test holds at £30,000 in both. Substituting the over-qualifying mutation
(`$rateTestAmount = $bequestTotal`) turns three cases red. The fixture-asymmetry claim
holds — £35,000, £30,000, £5,000 and every half of each are distinct values, so no
wrong reading lands on a right-looking number. The peak_earners persona could not have
established any of this (£10,000 + £10,000 makes the pooled figure exactly twice the
tested one), and the file says so.

**Newly reachable, checked rather than assumed:**

- `charitable_rate_test_amount` is always present on `iht_summary.current`.
  `IHTController::calculateIHT` takes a **fresh** `IHTCalculationService::calculate()`
  result — no cache, no persisted row, no partial payload — so there is no undefined-key
  path. Verified at `IHTController.php:55`.
- **New case the card can now reach: a couple where only the first-to-die left a
  legacy.** Pooled > 0, rate test = 0, so the card renders "…across your household"
  plus "…the will operating on the second death, which leaves £0". Both statements are
  true and the disclosure is an improvement. It does sit above the third-branch server
  message "Leave 10%+ of your baseline estate … to charity", which reads oddly to
  someone who *has* left a legacy. Coherent once read in order; copy nit only.
- The `v-if / v-if / v-else-if` chain has one structurally odd branch (`differ && !recorded`)
  which would render the second sentence orphaned. **Unreachable from this backend** —
  the rate-test amount is always ≤ the pooled amount, so it cannot be positive while
  the pooled figure is zero. No action.
- Rounding: `Math.round(exemption) !== Math.round(rateTest)` suppresses sub-£0.50
  divergence. Correct behaviour, not a defect.
- **Rule 19 verified independently.** `grep` across `resources/mobile/` and `ios-native/`
  finds no consumer of `calculate-iht`, `iht_summary` or any charitable figure — the
  only `/m` hit is an expenditure category label. The branch doc's "no `/m` counterpart"
  claim is accurate.
- A second **web** surface does consume the pooled figure — see C4.

## Q2 — Should both spouses' legacies pool into the s23(1) exemption at all? (escalated; answered on the statute)

**Answered on the statute, not on the W-0154 sign-off: the pooling is sound in this
model, and the re-confirmation is granted. Do not reopen it — but record the
condition it depends on, because it is not obvious and it is load-bearing.**

There is no household estate in law. IHTA 1984 s1 and s4(1) charge tax on each
person's death on the value of *their* estate. So "pooling" is a modelling construct
and must be judged on whether it reproduces the correct total tax, not on whether it
resembles the statute.

It does, for a specific arithmetic reason:

> **The model never settles the first death.** The combined second-death estate still
> contains the first-to-die's assets in full — including the money that, in reality,
> would have left the estate as their charitable legacy on the first death. The
> combined estate therefore **over-includes by exactly the amount** that the pooled
> exemption **over-deducts**. `estate − gift` is the same number whether you remove
> the legacy at the first death or deduct it at the second.

Supporting provisions: s18 (unlimited spouse exemption, so the first estate is
normally wholly exempt and no rate question arises there); s8A–s8C (a first estate
that is wholly spouse- and charity-exempt uses none of its nil-rate band, so 100%
transfers — consistent with `nrb_spouse_modelled`); s23(1) (each legacy exempt at its
own death). Nominal cash legacies are correctly *not* inflated in the projected
column, and percentage bequests correctly scale with the projected estate
(`WillAnalysisService::getCharitableBequestTotal`), so the projection does not
mis-time the deduction either.

**The condition this clearance depends on — record it against the item:**

> **If any future change makes the model actually settle the first death** — removing
> the first-to-die's estate and passing only the net to the survivor — **the pooled
> exemption must be removed in the same change, or the first legacy will be relieved
> twice.** The two are a matched pair. Today they are correct only together.

**One asymmetry, conservative, no action.** The baseline is computed on the combined
estate, so it includes the first-to-die's legacy, which on the strict statutory view
never entered the survivor's estate. The baseline is therefore slightly overstated,
the 10% threshold slightly overstated, and the household made marginally **harder** to
qualify for 36%. It errs against the taxpayer and never over-qualifies, which is the
same direction as the ruling's own stated caution. Worth knowing; not worth changing.

**Standing observation, out of scope and not made reachable by this batch:** the
model treats spouse exemption as unlimited without the s18(2) restriction (the
capped exemption where the transferee spouse is not long-term UK resident). That is
the one place "unlimited spouse exemption" is wrong in law. Pre-existing, unrelated
to this diff, mentioned only so it is not mistaken for something this review missed.

## Q3 — Are the corrected label texts accurate as statements of law?

**Substantially yes.** Checked against the statute, not merely against the code.

| Text | Verdict |
|---|---|
| "£X is left to charity across your household, and comes out of the estate before Inheritance Tax is worked out." | **Accurate.** A sound plain-English rendering of the s23(1) exemption, and it correctly declines to attribute the pooled figure to any one will. |
| "The 10% test that decides the reduced rate looks only at the will operating on the second death, which leaves £Y." | **Accurate for this model and for the ordinary case**, with one over-simplification — see below. |
| "Your will records no gifts to charity." | Accurate, unchanged. |
| All three server messages | Accurate; every rate now interpolated from configuration. |

**The over-simplification, advisory only.** Sch 1A tests each **component** of the
estate separately (survivorship, settled property, general), with an election to merge
under para 7. A charitable remainder in an interest-in-possession settlement created
by the first-to-die's will forms part of the survivor's chargeable estate under
s49(1)/s49A and would be tested in its own settled-property component — so the donated
amount on the second death is not, in law, confined to "the will operating on the
second death". Fynla models neither components nor the merger election, and no
household it can currently model reaches the divergent case. If the sentence is ever
revisited, "the estate passing on the second death" is the safer verb. **No change
required now.**

**Rule 9: satisfied, and properly tested.** All three server messages and the card's
own new sentence spell out Inheritance Tax; no acronym survives in any of them,
asserted at both the service layer and the component layer. ISA does not arise here.

**But the same component still violates Rule 2 in user-facing prose — see C4.**
`IHTPlanning.vue:593` reads "Charitable giving (can reduce Inheritance Tax rate from
40% to 36% if ≥10% to charity)" and `:596` reads "up to £175,000", while
`ihtStandardRate` and `ihtReducedRate` are already mapped into this component at
`:758`. Under the batch's own 31%/12% test configuration this one card would
simultaneously read "Reduced Inheritance Tax rate of 31% applies" and "can reduce …
from 40% to 36%". The fix does not cause that contradiction, but it does make it
newly visible on a single screen.

## Q4 — Rule 2 completeness: does anything outside this batch make the in-scope change unsafe to land?

**No.** None of the out-of-scope literals feeds `charitable_deduction`,
`charitable_rate_test_amount`, `taxable_estate` or `iht_liability`. Nothing here is a
reason to hold the batch.

**Four additions to W-0432 that the agent did not list, ordered worst first. Two are
worse than a literal — one is a wrong figure, one is a wrong statement of law.**

1. **`app/Services/Estate/WillAnalysisService.php:74` — a hardcoded rate differential
   in a computed figure, not a label.**
   `$potentialSaving = $baseline * 0.04;` — 4% is 40% minus 36%. It is the only item
   in this family where configuration moving produces a **wrong number** rather than
   a wrong sentence: at a 31% reduced rate the saving is 9% of baseline, and the app
   would understate it by more than half. **The strongest Rule 2 case of the set.**
2. **`app/Services/Plans/EstatePlanService.php:517` — a wrong statement of the
   statutory test, in a second message mechanism.**
   `'Reduced rate of %d%% applies as 10%% or more of the net estate is left to charity.'`
   Sch 1A compares the donated amount with 10% of the **baseline amount** (net estate
   less available nil-rate band, donation added back), not with 10% of the net estate.
   Two defects in one line: a hardcoded threshold, and a materially different base.
   This message is what `/plans/estate` renders (`EstateCurrentSituation.vue:13`) and
   what the printed plan carries (`planPrintMixin.js:2241`) — so the batch's Rule 2
   and Rule 9 corrections reach `/estate` and **not** `/plans/estate`. Rule 20: one
   sentence, two mechanisms, one of them fixed.
3. **`app/Services/Plans/PlanConfigService.php:168` — a second configuration home for
   the Schedule 1A threshold.** `estate.charitable_giving_threshold_percent` (default
   10.0) is independent of `TaxConfigService`'s `inheritance_tax.charity_threshold_percent`.
   Move the admin tax setting to 12% and `/estate` says 12% while `/plans/estate` says
   10%. W-0431 is only half-closed at application level while this stands.
4. **`resources/js/components/Estate/IHTPlanning.vue:593` and `:596`** — as set out
   under Q3, in the file this batch edited, with the getters already in scope.

The agent's own list (`WillAnalysisService:348,351-353`, `GiftingStrategy:219`,
`ContributionWaterfallService:184/193`) is confirmed accurate; `:348`'s surviving
`?? 0.36` does duplicate what `TaxConfigService::getCharitableReducedRate()`'s docblock
records as consolidated.

---

## Conditions

**C1 — BLOCKING for the item, trivial to close. Fix the false comment in
`IHTPlanning.vue:225-232`.** The template comment states: *"On this persona both
spouses left £10,000, so the two coincide and the difference is invisible."* They do
**not** coincide — pooled £20,000 against a rate test of £10,000. The computed
property's own docblock (`:975-985`) and the branch doc's fixture note both say the
opposite and are right. In a batch whose entire subject is a false sentence attached
to a correct figure, a false comment inside the fix is the one thing that must not
survive: it is the next reader's premise. **No code change, comment only.**

**C2 — File as its own item (recommend W-0433), do not fold into this batch.
The percentage in the message is measured against the wrong denominator.**
`determineIHTRate()` computes `$charitablePercent = ($survivorBequestTotal / $netEstate) * 100`,
while `$threshold = $baseline * getCharitableThresholdPercent()`. The sentence then
places them side by side: *"Your charitable giving of 0.6% … is below the 10% threshold
of £122,878."* **0.6% is of the net estate; 10% is of the baseline.** The reader is
invited to compare two percentages with different denominators. The £ amounts are
correct and the instruction ("increase by £112,878") is computed from the correct pair,
so **nobody is told a wrong amount to give** — this is the same severity shape as
W-0399 itself. The statutory percentage is the donated amount over the baseline
(Sch 1A para 5): £10,000 / £1,228,780 = **0.81%**, not 0.6%.
**The app already knows this.** `EstatePlanService::charitablePercentage()` (`:582-596`)
computes it against the baseline, with a docblock saying so — so the two web surfaces
publish two different charitable percentages for the same household. Rule 20 applies.
It changes a published figure (`charitable_giving_percent`), which is why it is its
own item and not an edit here.

**C3 — Close the gap in the Rule 2 test before the batch is called covered.**
`CharitableExemptionVersusRateTestTest` moves `reduced_rate_charity` to 0.31 and
`charity_threshold_percent` to 0.12, but leaves `standard_rate` at 0.40 — so the
assertion set cannot distinguish a config-read standard rate from a re-hardcoded
`"40%"`. The mutation table's five kills are all real, but "standard rate re-hardcoded"
is a sixth mutation that survives. Cheapest close: move `standard_rate` to 0.41 in the
same fixture and assert `41%`. Related, lower value: the moved configuration exercises
only the standard-rate branch — the reduced-rate and no-legacy messages are not
asserted under moved configuration.

**C4 — Add the four items above to W-0432**, and record explicitly that items 1 and 2
are **not** cosmetic: one produces a wrong figure under changed configuration, the
other misstates the statutory test on `/plans/estate` and in printed plans today,
under the current configuration, with no configuration change required.

## What I did not review

- The F-0026 projection hunks co-resident in `IHTCalculationService.php`.
- `effective_tax_rate` and `iht_liability` (W-0154 / W-0188, untouched here).
- The rendered page. **The browser was held by another agent throughout; I did not take
  it.** This clearance is on the statute, the code and the test suites, not on the
  screen. The batch still owes the rendered-page verification its own doc records as
  outstanding.
