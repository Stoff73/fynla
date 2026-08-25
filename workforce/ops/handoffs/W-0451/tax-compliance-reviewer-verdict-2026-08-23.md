# W-0451 / W-0452 — tax-compliance-reviewer statutory gate

**Reviewer:** tax-compliance-reviewer · **Date:** 2026-08-23 · **Requested by:** team-lead
**Batch:** `workforce/branches/fixes/F-0033-cycle4-the-charitable-saving-and-the-percentage-denominator.md`
**Predecessor verdicts (precedent, not background):**
`workforce/ops/handoffs/W-0399/tax-compliance-reviewer-verdict-2026-08-23.md` ·
`workforce/ops/handoffs/W-0432/tax-compliance-reviewer-verdict-2026-08-23.md`
(the second verdict's Q2 is this batch's specification)

**Files in scope:** `app/Services/Estate/IHTCalculationService.php` (the
`assessTaxPosition()` / `determineIHTRate()` hunks only),
`app/Services/Estate/WillAnalysisService.php`, `app/Agents/EstateAgent.php`
(the charitable hunks only), `app/Services/Plans/EstatePlanService.php`,
`app/Services/Estate/GiftingStrategy.php`, `app/Services/TaxConfigService.php`,
`resources/js/components/Estate/IHTPlanning.vue`.

---

## VERDICT: CLEARED WITH CONDITIONS

**Q1 is answered in the batch's favour.** The formula it built is the lawful
answer to the question its sentence asks, the load-bearing statutory claim is
**confirmed**, and the arithmetic reconciles exactly on live data. The 43%
self-contradiction is gone and the three surfaces now quote one figure.

**But this batch introduced one new user-visible false statement, and it is on
the primary persona today.** By moving the numerator to the survivor's will
without moving the label, the decision trace and the plan now attribute the
**survivor's** charitable position to **whoever is logged in**, and instruct that
person to add a legacy to **their own** will — an action which, under the very
2026-08-21 ruling this code implements, **cannot change the rate test at all**.
That is C1, it is blocking for the items, and it is the direct consequence of the
Q3 deviation.

Everything else is a follow-up, a Rule 2 miss, or a correction to a claim.

---

## Authority used

Per the standing warning, I read the live configuration rather than my own
reference table, which is headed 2025/26 and is **not** the authority.

`TaxConfiguration` active row is **2026/27**:

```
standard_rate 0.40 · reduced_rate_charity 0.36 · charity_threshold_percent 0.10 (a FRACTION)
nil_rate_band 325000 · residence_nil_rate_band 175000 · rnrb_taper_threshold 2000000
TaxDefaults::IHT_RATE 0.4 · IHT_CHARITABLE_RATE 0.36 · IHT_CHARITY_THRESHOLD 0.1
TaxConfigService::getCharitableReducedRate() 0.36 · getCharitableThresholdPercent() 0.1
```

These agree with IHTA 1984 s7(1A) and Schedule 1A. **The configuration is what I
relied on**; my table is quoted nowhere in this verdict.

## Verification I ran myself

Isolated database `laravel_testing_taxgate3`, created so as not to collide with
the other agents' runs. **No code changed. Browser not taken** — an agent is
reading `/estate` and `/plans/estate`.

| Suite | Result |
|---|---|
| `CharitableRateSavingReconcilesTest` + `WillAnalysisCharitableBequestTest` + `RateLiteralsComeFromConfigurationTest` + `CharitableExemptionVersusRateTestTest` | **51 passed**, 162 assertions |
| `Unit/Services/Estate` + `Feature/Estate` + `Unit/Services/Plans` + `Feature/Plans` + `Unit/Services/Investment` + `Unit/Agents` | **882 passed**, **2921** assertions |
| Vitest `resources/js/components/__tests__/Estate` + `tests/frontend/components/Estate` | **169 passed**, 12 files |
| `pint --test` on the seven touched files | passed |

882 and 169 match the branch doc exactly. **2921 assertions, not the 2925 the
branch doc records** — a four-assertion difference, almost certainly shared-tree
drift from a concurrent agent rather than anything in this batch. Recorded
because both predecessors matched exactly and a reader comparing will notice.

**Live calculation**, run against the local database for user 16 (David
Mitchell, `peak_earners`), through `IHTCalculationService::calculate()`. Every
figure below is from that run:

```
total_net_estate                         1,728,780.00
nrb_available 500,000  rnrb_available 350,000  total_allowances 850,000
charitable_deduction (pooled s23(1))        20,000.00
charitable_rate_test_amount (survivor)      10,000.00
charitable_baseline                      1,228,780.00
charitable_threshold                       122,878.00
charitable_giving_percent                     0.8138 %
charitable_shortfall                       112,878.00   qualifies false
taxable_estate                             858,780.00
charitable_taxable_estate_if_qualifying    745,902.00
charitable_tax_at_standard_rate            343,512.00   (= iht_liability, the ACTUAL bill)
charitable_tax_at_reduced_rate             268,524.72   (the counterfactual)
charitable_rate_saving                      74,987.28
```

Reconciliations, checked rather than assumed:

- `taxable_estate − shortfall = 745,902.00` = published `..._if_qualifying`. **Exact.**
- `343,512.00 − 268,524.72 = 74,987.28` = published saving. **Exact.**
- `taxable_estate × iht_rate = 343,512.00` = `iht_liability`. **Exact.**

I also confirmed by reflection that the survivor for this household is **Sarah**
(life expectancy 36.00 against David's 32.00) — which matters for C1.

---

## Q1 — which of the three candidates is the lawful answer to *"what does reaching the Schedule 1A threshold save this estate?"*

### The answer is the third: the actual reduction in the Inheritance Tax bill. The batch chose correctly.

**The statute.** IHTA 1984 **s7(1A)** charges the reduced rate where Schedule 1A
is satisfied. **Sch 1A para 2** sets the charitable giving condition: the
*donated amount* for a component must be at least 10% of the *baseline amount*
for that component. **Para 4** defines the donated amount as the value
transferred attributable to property in the component that is exempt under
**s23**. **Para 5** builds the baseline in three steps — Step 1, the amount of
the value transferred attributable to the component; Step 2, deduct the
appropriate proportion of the *available nil-rate band amount*; Step 3, **add
the donated amount back**. HMRC IHTM45002–45008.

Judge each candidate against that:

| Candidate | On the F-0033 fixture | Verdict |
|---|---|---|
| differential × **baseline** | £45,800.00 | **Not a tax quantity at all.** Neither 40% nor 36% is ever applied to the baseline. The baseline is a *test* quantity under para 5, not a charge base. This was the published figure. |
| differential × **chargeable estate** | £30,332.80 | **A counterfactual that cannot occur** for a non-qualifying estate: you cannot reach the reduced rate without making the gift, and the gift leaves the estate. It is the printed working of the old sentence, and it is right *only* in the already-qualifying case. |
| **the actual tax reduction** | £60,122.80 | **Correct**, and it is what the sentence promises. |

**And the third subsumes the second.** For an estate that already qualifies the
shortfall is zero, so `E − shortfall = E`, both bills sit on the same chargeable
estate and the difference collapses to the rate differential on it — which is
what "the reduced rate is worth" means to someone who already has it. **One
formula, both branches**, and in both of them one of the two bills is the actual
`iht_liability` and the other the counterfactual. That property is what makes the
sentence checkable, and it is the property the old sentence did not have.

### The load-bearing statutory claim: CONFIRMED

> *Sch 1A para 5 adds the donated amount back, so `baseline = netEstate − availableNRB` does not move as the gift grows.*

**Confirmed, and it is confirmed by the mechanism of para 5 rather than by
coincidence.** The value transferred at Step 1 is already net of the s23(1)
exemption, so Step 1 yields (component value − gift); Step 3 adds the gift back.
The gift cancels:

```
baseline = (component value − gift) − available NRB + gift = component value − available NRB
```

`IHTCalculationService:1391` computes exactly that (`max(0, $netEstate −
$nrbAvailable)`), and `determineIHTRate()` is called with `$netEstate` **before**
the charitable deduction, so nothing is deducted twice. HMRC IHTM45004 states the
same result: the baseline is the estate after all reliefs, exemptions **other
than the charitable exemption**, and the available nil-rate band.

**This is what makes ONE subtraction the whole answer.** If the threshold moved
with the gift, "give another £S to reach £T" would be chasing a receding target
and no single saving figure could be right. The batch pinned it with its own case
(`it('does not move the baseline when the gift moves …')`), which is the correct
place to pin it.

Two consequences I checked rather than assumed, both sound:

- **The residence nil-rate band is still excluded from the baseline** and is
  still deducted from the chargeable estate. ss.8D–8M are a separate mechanism
  ("residential enhancement") and FA 2015 did not amend para 5. `:1391` is
  untouched by this batch — **W-0369 stays open exactly as it was**, as the
  branch doc says.
- **Increasing a charitable legacy does not move the allowances.** The s8D(5)
  taper is measured on the value of the estate immediately before death, before
  exemptions, so the RNRB does not move with the gift either. `$totalAllowances`
  is therefore correctly held constant across the two bills.

### Arithmetic edge cases, checked

- `max(0, $taxableEstate − $charitableShortfall)` (`:727`) clamps correctly when
  the shortfall exceeds the chargeable estate; tax is zero either way and the
  saving is the whole standard-rate bill. No error introduced by the double clamp
  (`:678` is already clamped).
- `max(0, standard − reduced)` (`:743`) cannot fire under any lawful
  configuration; it only masks an inverted configuration. Not a tax defect.
- The projection runs `assessTaxPosition()` a second time and computes a
  projected saving that is published nowhere. Harmless.

### CONDITION — the figure is right; the RECOMMENDATION built on it is materially incomplete

This is not a challenge to the formula. It is a challenge to the word **saving**
standing alone in a sentence that recommends an action.

Reaching the threshold does two things, and the batch is right that the old
sentence modelled only one. But it costs a third thing the new sentence still
does not state: **the shortfall leaves the family.** Writing `r_s`, `r_r` for the
two rates, `E` for the chargeable estate and `S` for the shortfall, the change in
what the non-charity beneficiaries receive is

```
Δresidue = (r_s − r_r)·E  −  S·(1 − r_r)         at 40/36:  0.04·E − 0.64·S
```

so the beneficiaries are better off only while **S < E·(r_s − r_r)/(1 − r_r)** —
at 40/36, **S < E/16, i.e. a shortfall under 6.25% of the chargeable estate.**

**On user 16 today, following the recommendation makes the family £37,890.72
worse off**, and I verified this end to end rather than deriving it:

```
as the will stands:  1,728,780 − 20,000 gift − 343,512.00 tax = 1,365,268.00 to beneficiaries
with the larger gift:1,728,780 − 132,878 gift − 268,524.72 tax = 1,327,377.28 to beneficiaries
                                                          Δ = −37,890.72
```

The screen says **"Saving £74,987"**. Both statements are true — the estate does
pay £74,987 less tax — and only one of them is on the page.

**This is a pre-existing framing, not something the batch invented** (the old
sentence said "saving £19,580" for the same action). But the batch **multiplied
the published figure by 3.8×**, which makes the omission materially more
consequential, and it **added** the clause *"and the additional £112,878 leaves
the estate as an exempt gift"* — a disclosure that the gift leaves the estate,
framed as part of the benefit rather than as the cost. The `/estate` card carries
a partial mitigation (*"A scenario only — nothing above changes until the gift is
in your will"*); the decision trace and `/plans/estate` carry none.

**Not a reason to withhold clearance** — the tax figure is correct and is a large
improvement on what it replaced. **It is a reason to file the disclosure as its
own item and route it to compliance-lead**, because "save £74,987" attached to an
action that costs the family £37,891 is a Consumer Duty question before it is a
tax question. See **C2**.

---

## Q2 — is the survivor the right numerator on the plans surface as well as the Inheritance Tax surface?

### YES on the statute. The panel is about the Schedule 1A rate test, and the rate test is the survivor's.

`/plans/estate`'s charitable panel is explicitly a rate-test panel — its own
labels are *"Threshold for 36% Rate"*, *"Shortfall to Qualify"*. Under the
2026-08-21 ruling (re-confirmed in the W-0399 verdict's Q2 and untouched here),
the estate being modelled is the survivor's on the second death, so the donated
amount that decides the rate is the survivor's will alone. Summing both wills
would over-qualify households for 36%.

So the pair the plan now publishes — **survivor's donated amount ÷ household
Schedule 1A baseline** — is Sch 1A para 5's own numerator over its own
denominator. `EstatePlanService:604` reads it; `EstatePlanService::charitablePercentage()`
is deleted; `IHTCalculationService:1466` is now the only division producing this
figure in the application. **W-0452 acceptance 5 is met on the arithmetic.**

I confirmed both surfaces resolve to one figure:

| Surface | Key | Source |
|---|---|---|
| `/estate` card | `charitable_rate_saving` | `calculation` (whole array published at `IHTController:98`) |
| `/plans/estate` + printed plan | `charitable_giving.potential_saving` | `charitable_analysis['potential_saving']` = the same key |
| decision trace (`/actions/:id`) | `potential_saving` / `current_saving` | the same key |

**And the removed browser-side mechanism was not right about anything the
survivor is wrong about.** `IHTPlanning.vue:962` computed `taxableEstate ×
(standard − reduced)` = £34,351.20 for user 16 — candidate 2, the counterfactual
that cannot occur — under a sentence proposing exactly the action that makes it
not occur. Its only virtue was that it read `taxable_estate`, a key that is
always present, where the replacement reads a key that arrives through a
hand-written allowlist and defaults to `?? 0`, hiding the card behind its own
`v-if` if it ever goes missing. The batch names that risk and covers the allowlist
line with a real-lifecycle spec case. **Acceptable.**

### BUT the numerator moved and the label did not — see C1

This is the finding that decides the conditions. It is set out under **C1**
because it is a defect, not an answer.

---

## Q3 — `survivingMember()` not exposed, though W-0452 acceptance 2 names it

### The substitution satisfies the acceptance for the FIGURES, and it is exactly what left the ATTRIBUTION wrong.

**The reasoning is sound as far as it goes.** Exposing the helper would force
`EstateAgent` to re-derive `isMarried && spouse !== null && dataSharingEnabled`
in order to call it — a second copy of the decision that selects the survivor,
which is the defect one level up. Publishing the answer instead of the mechanism
is a legitimate reading of *"(or an equivalent)"*, and it is the better
engineering choice. I would have made the same call.

**But the premise it rests on — *"no second caller ever needs to know who the
survivor is"* — is false, and the code proves it.** `EstateAgent` needs the
survivor's **identity**, because it writes sentences with a name in them. What
was published was amounts and booleans; the identity was not. So the
sentence-writer had nothing to name and defaulted to `$ctx['first_name']`, which
is the logged-in user (`EstateAgent:313`).

**The fix keeps the deviation and closes the gap:** publish the identity from the
same one resolution — a `charitable_rate_test_member_id` (or first name) beside
`charitable_rate_test_amount`, from `determineIHTRate()` where `$survivor` is
already in hand. No second caller re-derives the pooling condition, the helper
stays private, and the sentence can name the right person. `$ctx` already carries
`spouse_first_name`, so the consuming change is small.

**Judgement: the deviation is accepted, on condition that the identity is
published too.** That is C1.

---

## C1 — BLOCKING for W-0451 / W-0452. The survivor's position is attributed to whoever is logged in, and the action prescribed cannot work

**What the batch changed.** Before, `analyzeCharitableBequests($user, …)` totalled
the **logged-in user's own** bequests, so *"Add £S to David's will"* was coherent
with the numerator it came from — the threshold was a mongrel, but the person and
the will matched. Now the numerator is the **survivor's** and the name is still
the logged-in user's.

**What it renders.** On the Bennett fixture the batch itself built (survivor
Patricia £31,750, first-to-die Harold £4,930, baseline £1,145,000, threshold
£114,500, shortfall £82,750), Harold's session produces:

- `EstateAgent:736` — *"Do **Harold's** charitable bequests reach the 10%
  threshold…?"* with `data_value` *"2.8% (£31,750 of £1,145,000 baseline)"*.
  **Harold's will leaves £4,930 — 0.43%.**
- `EstateAgent:775` — *"If **Harold** increases charitable bequests by £82,750…
  Saving £74,123."*
- `EstateAgent:790` — the action: *"Add £82,750 in charitable bequests to
  **Harold's** will."*

**Following that instruction does not produce the promised outcome, and the
application would repeat it forever.** Adding £82,750 to Harold's will raises
`$bequestTotal` (the pooled s23(1) exemption) but not `$survivorBequestTotal`
(`IHTCalculationService:1423`), which is Patricia's. So `$qualifies` stays false
and the rate stays at 40%. The chargeable estate falls by £82,750, so tax falls by
£33,100 — the family £49,650 poorer, still on the standard rate. `$baseline` is
unchanged (a bequest is a distribution, not a reduction in assets), so the
threshold and the shortfall are unchanged and **the same instruction is issued
again**.

**Reachability is not hypothetical.** On the seeded `peak_earners` household the
survivor is **Sarah**, so **David's** session — user 16, the primary persona —
already reports Sarah's will under David's name. It is invisible there only
because both spouses happen to leave £10,000, which is the exact coincidence the
W-0399 verdict and the batch's own §8.4 both warned makes this axis
undemonstrable on that persona.

**The batch's own test asserts the half of this that is right and cannot see the
half that is wrong.** `it('gives the same percentage from either spouse's
session')` asserts the two sessions agree on the figures — correct, they are
household figures — and asserts nothing about the name attached to them.

**To close:** publish the survivor's identity (Q3), and make the trace, the
`actions` array, the recommendation `description` and `/plans/estate`'s panel
either name the survivor or say plainly that the test looks at the will operating
on the second death — the disclosure `IHTPlanning.vue:246` already carries on
`/estate`. **One fix, all three surfaces (Rule 20).** Add a case that renders the
sentence from the **first-to-die's** session and asserts the name.

---

## What did NOT move — the batch's list, verified

I checked each claim against the diff rather than accepting it.

| Claim | Verified |
|---|---|
| The baseline formula untouched; **W-0369 stays open exactly as it was** | **TRUE.** `:1391` and `:1394` appear nowhere in the diff. |
| The pooled s23(1) exemption untouched — `charitable_amount` → `charitable_deduction` → `$taxableEstate` by the same path | **TRUE.** `:1422`, `:676`, `:678` are all absent from the diff. |
| `$rateTestAmount` still the survivor's alone | **TRUE.** `:1423` absent from the diff. |
| W-0399's Q2 coupling undisturbed — nothing settles the first death | **TRUE.** No change to asset or liability pooling; the combined estate still contains the first-to-die's assets in full, which is the arithmetic the coupling depends on. **The coupling remains in force.** |
| C2 (the profile-percentage branch) verified and closed | **TRUE, and it is this batch that closed it.** `:1466` computes the percentage once, after both branches, from `$rateTestAmount ÷ $baseline`. W-0432's C2 can be closed. |
| Every published rate and threshold from `TaxConfigService` | **Substantially true, with four misses** — see below. `GiftingStrategy`'s `< 10` gate and its sentence are now configuration-driven; `EstateAgent`'s five threshold literals are gone; `WillAnalysisService`'s `const` is now a method. |
| Rule 9 observed in every sentence rewritten | **TRUE.** No acronym survives in any rewritten sentence. `EstatePlanService`'s qualifying branch still never names the tax — the copy nit W-0432 recorded, unchanged, not a Rule 9 breach. |
| `recommendOptimalGiftingStrategy` has zero production callers, so `GiftingStrategy:227`'s fifth formula reaches no user | **TRUE.** `grep` across `app/`, `routes/`, `resources/` finds the definition and four test call sites, nothing else. |
| Rule 19 — no `/m` or native consumer | **TRUE, re-run independently.** The only hit across `resources/mobile/` and `ios-native/` is `Expenditure.vue:50`, a `charitable_donations` expenditure label. |

---

## What these changes make newly reachable

Checked as instructed, not only line-by-line.

**1. The attribution defect — C1.** The largest one, and the batch's own doing.

**2. A user who types a charitable percentage now sees a different number back.**
With C2 closed, `charitable_giving_percent` is `rateTestAmount ÷ baseline`, so a
user who enters 5% on their Inheritance Tax profile (a percentage of the net
estate by construction, `:1397`) is shown `5 × netEstate ÷ baseline` — 7.0% on
user 16's household. **That is statutorily correct** (Sch 1A measures against the
baseline) and it is the whole point of C2 — but the input and the output now
carry the same words and different values. No fixture reaches it: I queried
`iht_profiles` and there are **0 rows** with `charitable_giving_percent > 0` (of
2). Any user can reach it. **Advisory: label the output so it is not read as an
echo of the input.**

**3. The two entry points still derive pooling differently, and the charitable
figures now depend on it.** `IHTController:52` uses
`liveSpouseId() !== null && hasAcceptedSpousePermission()`; `EstateAgent:146`
uses `$spouse !== null`. A household where the two disagree gets a **pooled**
calculation on `/plans/estate` and an **individual** one on `/estate` — different
baseline, different threshold, **different survivor**, different saving. The
batch consolidated the formula into one home; it did not consolidate the
predicate that feeds it, so "one figure on three surfaces" holds only while the
two entry points agree.

**Not reachable today:** I enumerated all 12 linked users in the local database
and every one is mutually `married` with a reciprocal `spouse_id`, so the two
predicates agree everywhere. **Pre-existing, not introduced here** — but it is
newly load-bearing, because before this batch `/plans/estate`'s charitable panel
did not come from `$ihtCalculation` at all. W-0154 family; record it there.

**4. Skipping the charitable analysis when the calculation failed changes
nothing user-visible.** `generateRecommendations()` only reaches step 1 when
`$ihtLiability > 0`, which is impossible when the calculation failed. The choice
is right; the behaviour is unchanged.

**5. `WillAnalysisService`'s `message` key reaches no surface.** `grep` finds no
consumer of `charitable_analysis['message']` or `has_unvalued_charitable_gifts`
outside the two services. The Rule 2 corrections to
`unvaluedCharitableGiftsMessage()` and `getCharitableStatusMessage()` are
therefore correct and currently dormant. Worth knowing before someone concludes
the unvalued-gift suppression is live on a screen — **it is not**, and the
suppression that *is* live is `charitable_has_unvalued_gifts`, consumed only by
`WillAnalysisService` itself. This looks like a genuine gap in the delivery of
the unvalued-gift disclosure to the user, and it is not this batch's doing.

---

## Rule 2 instances this batch missed

**Four user-facing tax-value literals remain in the charitable family, two of
them in files this batch edited, and two of them adjacent to lines it fixed.**
Listed worst first.

**1. `resources/js/components/Estate/IHTPlanning.vue:246` — the sharpest, because
the getter is in the same component.**

> *"The **10%** test that decides the reduced rate looks only at the will
> operating on the second death…"*

Added by F-0031 (W-0399), and this batch introduced `charitableThresholdLabel`
(`:1027`) three cards below to remove the identical literal from `:609`. **One
component, one quantity, interpolated on one line and hardcoded two cards above.**
Under a 12% configuration this card reads "10%" here and "12%" there. Exactly the
half-fixed shape W-0432's §2 warns about, in the file that documents the warning.

**2. `resources/js/components/Estate/IHTPlanning.vue:609` — the residence nil-rate
band, in the `<li>` immediately after the one this batch fixed.**

> *"Consider leaving your main residence to direct descendants to claim the Home
> Allowance (**up to £175,000**)"*

This is W-0399's C4 item 4 — *"`:596` reads 'up to £175,000'"* — **still open**.
It was named by one verdict, carried into W-0432, and the batch edited the line
directly above it. `residence_nil_rate_band` is in configuration and the RNRB is
frozen only until April 2028.

**3. `resources/js/components/Plans/Estate/EstateCurrentSituation.vue:83` and
`resources/js/components/Plans/Shared/planPrintMixin.js:2290` — the reduced rate,
on the surface this batch corrected and in the printed plan.**

> *"Threshold for **36%** Rate"*

Rendered directly above `situation.charitable_giving.threshold`, which **is**
configuration-driven. So the panel already reads its threshold from configuration
and hardcodes the rate that threshold unlocks, in the same card. Both files are
in the batch's own §3 list of the three consumers of the changed payload.

**4. `app/Http/Controllers/Api/TaxSettingsController.php:330` — named by the
batch's own new docblock and left standing.**

```php
'reduced_rate' => sprintf('%g%% (if 10%%+ to charity)', ((float) ($iht['reduced_rate'] ?? 0.36)) * 100),
```

`TaxConfigService::getCharitableReducedRate()`'s rewritten note explicitly says
the grep *"then found **four** — … and `TaxSettingsController:330`, the last
inside the admin screen that displays the tax settings themselves, hardcoding
'10%+' in the same sentence."* Three of the four were fixed; this one was named
and not fixed, and the note does not say so. **A completion note is load-bearing
— that is the batch's own lesson, applied to its own note.** Admin-facing, so
lower severity than 1–3, but it is the Tax Settings screen: the one place a
hardcoded threshold contradicts the very configuration being displayed.

### Confirmed NOT violations — do not sweep these

- `EstatePlanService:508` — `$ihtConfig['reduced_rate_charity'] ?? TaxDefaults::IHT_CHARITABLE_RATE`. Reads configuration, falls back to a `TaxDefaults` constant. **The sanctioned convention.** W-0432's C5 said the same; it must not be "fixed".
- `IHTPlanning.vue:1031` — `charitableThresholdLabel`'s `return '10%'` fallback. Unreachable in any state that renders a threshold figure, and degrading to the statutory default beats `NaN%`. W-0432 already cleared this.
- `TaxConfigSnapshotService:90`, `StoreTaxConfigurationRequest:70` — configuration plumbing, not values.

### Rule 9

**Satisfied in every sentence this batch rewrote.** "IHT" is spelled out in all
three `getCharitableStatusMessage()` branches, in `GiftingStrategy`'s
recommendation, in all three `determineIHTRate()` messages and throughout
`step1CharitableBequestCheck()`. `TaxSettingsController`'s block carries "IHT",
"NRB", "RNRB", "CGT" — admin-facing, pre-existing, untouched, out of scope, noted
only so it is not mistaken for something this review missed.

---

## Conditions

**C1 — BLOCKING for W-0451 and W-0452.** The survivor's charitable position is
attributed to the logged-in user, and the prescribed action cannot change the
rate test. Publish the survivor's identity from `determineIHTRate()` (which
preserves the Q3 deviation) and correct the trace, the `actions` array, the
recommendation `description` and `/plans/estate`'s panel — one fix, three
surfaces. Add a case rendering the sentence from the **first-to-die's** session.
**This is the one thing the batch introduced rather than inherited.**

**C2 — File as its own item, route to compliance-lead, severity HIGH.** The word
"saving" stands alone in a sentence recommending an action that, on user 16
today, leaves the beneficiaries **£37,890.72** worse off. The figure is correct;
the disclosure is missing. State the net effect, or state the cost beside the
saving. Break-even is **S < E·(r_s − r_r)/(1 − r_r)**, i.e. a shortfall under
6.25% of the chargeable estate at 40/36 — worth encoding, because on households
below that line the recommendation is genuinely good advice and the sentence
should be able to say which side of the line the user is on.

**C3 — Add the four Rule 2 instances above.** Mark items 1–3 user-facing and
live; item 2 (`£175,000`) is W-0399's C4 item 4 **re-opened, not new**, and has
now survived two verdicts and three batches.

**C4 — Correct `TaxConfigService::getCharitableReducedRate()`'s note once more.**
It names four survivors found by the grep and does not record that one of the four
(`TaxSettingsController:330`) was left standing. The note's own thesis is that a
completion claim is what stops the next reader looking.

**C5 — W-0432's C2 can be closed.** `IHTCalculationService:1466` computes the
percentage once, after both branches. Verified.

**C6 — Record the pooling-predicate divergence against W-0154**
(`IHTController:52` vs `EstateAgent:146`). Pre-existing, unreachable on today's
data (all 12 linked users checked), newly load-bearing for the charitable
figures.

**C7 — Advisory, no item required.** The published `charitable_giving_percent` is
no longer the number a user typed on the profile branch. Correct in law; label it
so it is not read as an echo of the input.

**C8 — For team-lead's note on W-0139:** its acceptance 1 does ask for the pooled
household total to feed the rate test, which the 2026-08-21 ruling forbids. The
branch doc is right and the item needs re-wording. Its acceptance 4 is delivered
by this batch.

---

## What I did not review

- **The rendered pages.** The browser was held by another agent throughout; I did
  not take it. **This clearance is on the statute, the code, a live calculation
  and the test suites — not on the screen.** The batch still owes the
  rendered-page verification its own §8 records as outstanding, and C1 is a
  defect a screenshot from the *survivor's* session would not show.
- The F-0026 projection hunks and the W-0342 / W-0397 life-cover and asset-summary
  hunks co-resident in the same working tree. `IHTCalculationService.php` and
  `EstateAgent.php` both carry other batches' edits; only the charitable hunks are
  cleared here.
- `effective_tax_rate` and `iht_liability` (W-0154 / W-0188, untouched).
- Whether the disclosure C2 asks for needs product sign-off — not a tax question.
- The s18(2) restriction on spouse exemption where the transferee is not a
  long-term UK resident. Standing observation from the W-0399 verdict, still
  unmodelled, still unrelated to this diff.
