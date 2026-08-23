# W-0432 / W-0433 — tax-compliance-reviewer statutory gate

**Reviewer:** tax-compliance-reviewer · **Date:** 2026-08-23 · **Requested by:** team-lead
**Batch:** `workforce/branches/fixes/F-0032-cycle4-rate-literals-and-the-charitable-denominator.md`
**Predecessor verdict:** `workforce/ops/handoffs/W-0399/tax-compliance-reviewer-verdict-2026-08-23.md`
(its condition C4 is this batch's specification)

**Files in scope:** `app/Services/Plans/EstatePlanService.php`,
`app/Services/Estate/WillAnalysisService.php`,
`app/Services/Plans/PlanConfigService.php`,
`app/Services/Investment/Recommendation/ContributionWaterfallService.php`,
`app/Services/Estate/GiftingStrategy.php`,
`resources/js/components/Estate/IHTPlanning.vue`,
plus the W-0433 hunk in `app/Services/Estate/IHTCalculationService.php`.

> **Path correction to the dispatch:** the two plan-layer files are under
> `app/Services/Plans/`, not `app/Services/Estate/`. Same shape as the
> `ContributionWaterfallService` path error the branch doc already flagged.

---

## VERDICT: CLEARED WITH CONDITIONS

**No condition blocks the batch.** Every change in the diff is a correction, no
figure it moves is wrong, and the 2026-08-21 statutory ruling and my
predecessor's Q2 coupling are both intact and undisturbed.

The conditions are of three kinds: **one wrong figure the batch deliberately did
not touch and correctly flagged (Q2 — and it is worse than the agent knew, see
below); one place where the fix is half-applied; and the batch's own completeness
claims, which are not accurate.**

**The single most important line in this verdict:** the agent's decision to make
the RATE configuration-driven and leave the BASE alone was the right call, and
the base is wrong in a way that is **visible on screen today, under current
configuration, as an arithmetic contradiction inside one sentence.**

---

## Authority used

Per the standing warning about stale reviewer tables, I read the live
configuration rather than my own reference table, which is headed 2025/26.

`TaxConfiguration` active row is **2026/27**:
`standard_rate 0.40`, `reduced_rate_charity 0.36`, `charity_threshold_percent 0.10`
(a **fraction**), `nil_rate_band 325000`, `residence_nil_rate_band 175000`,
`isa.lifetime_isa.government_bonus_rate 0.25`. `TaxDefaults::IHT_CHARITABLE_RATE`
and `IHT_CHARITY_THRESHOLD` agree as fallbacks. These match IHTA 1984 s7(1A) and
Schedule 1A. **The configuration is what I relied on.**

## Verification I ran myself

Isolated database `laravel_testing_taxgate2`, created so as not to collide with
the other agents' runs. **No code changed. Browser not taken** — another agent
holds it.

| Suite | Result |
|---|---|
| `RateLiteralsComeFromConfigurationTest` + `CharitableExemptionVersusRateTestTest` | **15 passed**, 45 assertions |
| `Unit/Services/Estate` + `Feature/Estate` + `Unit/Services/Plans` + `Feature/Plans` + `Unit/Services/Investment` | **770 passed**, 2468 assertions |

770 / 2468 matches the branch doc exactly.

**Live calculation** run against the local database for user 16 (David), through
`IHTCalculationService::calculate()`, `WillAnalysisService::analyzeCharitableBequests()`
and `EstateAgent::analyze()` → `generateRecommendations()`. Every figure quoted
below is from that run, not from the branch doc.

---

## Q1 — Is item 1's new wording a correct statement of Schedule 1A?

> *"Reduced rate of 36% applies: 10% or more of the estate above the nil rate
> band is left to charity."*

### YES. Correct as a statement of the general rule, judged against the statute.

**The statute.** IHTA 1984 s7(1A) charges the reduced rate where Schedule 1A
(inserted by FA 2012 Sch 33) is satisfied. **Sch 1A para 5** defines the
*baseline amount* for a component: take the value transferred attributable to the
component, **deduct** the appropriate proportion of the *available nil-rate band
amount*, then **add back** the donated amount. Because the value transferred is
already net of the s23(1) charitable exemption, adding the donated amount back
yields exactly **(estate − available nil-rate band)**. HMRC IHTM45002–45008.

**So "the estate above the nil rate band" is the baseline, precisely** — not an
approximation of it. It is also exactly what the code computes:
`$baseline = max(0, $netEstate - $nrbAvailable)`, with the donated amount never
deducted before that point. Verified live: baseline £814,500 on a net estate of
£989,500 against an available nil-rate band of £175,000.

**This replaces a sentence that was wrong in law** ("10% or more of the net
estate"), which would have understated the threshold and over-qualified estates.
The correction is sound and it reaches `/plans/estate` and printed plans, which
is where the wrong statement was live.

### Three advisories — none blocking, recorded so they are not re-derived

**1. "the nil rate band" is the *available* nil-rate band.** Sch 1A para 5(6)
means the band as increased by any transferred proportion (s8A–s8C, up to
£650,000 for a survivor) and as reduced by chargeable transfers in the seven
years before death. **The model uses the available figure and is correct**;
the sentence's shorthand reads to a lay user as the flat £325,000. It prints no
amount, so nothing on screen contradicts anything. Under-specified, not wrong.

**2. The residence nil-rate band is *not* deducted in computing the baseline, and
the phrase could be read as though it were.** The RNRB is a separate mechanism at
ss.8D–8M ("residential enhancement"); it is not part of the nil-rate band maximum
for Sch 1A purposes, and FA 2015 did not amend para 5 to include it. **The code
gets this right** — `IHTCalculationService:653` keeps `nrb_available` and
`rnrb_available` separate and only `nrb_available` reaches the baseline. Two
things mitigate the ambiguity in the prose: Fynla's own vocabulary names the RNRB
the **"Home Allowance"**, a distinct term from "nil rate band", and the sentence
prints no figure. **Acceptable as written.**

**3. Sch 1A tests each *component* separately (para 3), with an election to merge
(para 7).** The new sentence states one base for "the estate". True for every
household Fynla can currently model, which models no components and no election
— but it is a statement about the model presented as a statement about the test,
which is **the same shape my predecessor's Q3 advisory flagged and it must not be
disturbed.** No change required now; if the sentence is ever revisited, it should
acknowledge it states the ordinary case.

### Two copy nits, in the same sentence family

- The qualifying branch says *"**Reduced rate** of 36% applies"* while the
  non-qualifying branch says *"**Standard Inheritance Tax rate** of 40% applies"*.
  The reduced branch never names the tax.
- `/estate`'s third-branch message says *"Leave 10%+ of your **baseline estate**
  (£X) to charity"* while `/plans/estate` now says *"the estate above the nil rate
  band"*. **Two plain-English renderings of one statutory base, on two surfaces**
  — Rule 20 in miniature. The new wording is the better of the two; converging
  `/estate` onto it would close it.

---

## Q2 — Is the baseline the right base for `potential_saving`?

### NO. And it is worse than "an approximation that excludes the RNRB".

The agent was **right** to make the rate configuration-driven and leave the base,
and **right** to flag it rather than re-derive a formula inside a Rule 2 pass.
This answer does not criticise that decision — it raises the severity of what was
flagged.

### The identity, confirmed on live data

`potential_saving = baseline × (standard − reduced)`, where
`baseline = netEstate − availableNRB`.

The quantity it purports to describe is the rate differential applied to the
**chargeable estate**, `netEstate − charitable gift − availableNRB − RNRB`. The
baseline over-includes by exactly **(charitable gift + RNRB)**:

> baseline £814,500 − taxable estate £629,500 = **£185,000**
> = charitable gift £10,000 + residence nil-rate band £175,000. **Exactly.**

### The finding that decides this question: the app contradicts itself in one sentence

`EstateAgent::step1CharitableBequestCheck()` prints its own working and then
states a different answer. **Rendered live, verbatim, from
`generateRecommendations()` for user 16:**

> *"If David increases charitable bequests by £38,950 (to reach £48,950), the
> Inheritance Tax rate drops from 40% to 36%. On the taxable estate of £858,780:
> at 40% = £343,512, at 36% = £309,161 — **saving £19,580**."*

£343,512 − £309,161 = **£34,351**. The sentence asserts **£19,580**.
**A £14,771 contradiction — 43% — between two figures printed in the same
sentence and the difference stated immediately after them.**

This is not a modelling subtlety. It is arithmetic a user can check on a
calculator, on a **decision trace whose entire purpose is auditability** — the
subject of this cycle's F-0020. It is live under the current configuration and
needs no configuration change to appear.

**The cause is precisely Q2.** `EstateAgent` computes the differential on the
**taxable estate** (`:722-723`, correct); `WillAnalysisService` computes
`potential_saving` on the **baseline**. Two mechanisms, one quantity,
disagreeing — Rule 20.

### And under the sentence's own promise, neither figure is right

`WillAnalysisService`'s message says:

> *"Increase charitable giving by £71,450 to qualify for the reduced 36% rate and
> **save £32,580 in Inheritance Tax**."*

The saving from **that exact action** is
`0.40 × (A − C) − 0.36 × (A − T)` where A is the estate less all allowances, C
the current gift and T the threshold. On the live figures (net estate £989,500,
allowances £350,000, gift £10,000, threshold £81,450):

- before: 0.40 × £629,500 = **£251,800** (matches the published `iht_liability`)
- after: 0.36 × (£989,500 − £81,450 − £350,000) = 0.36 × £558,050 = **£200,898**
- **actual reduction: £50,902**, against a published **£32,580**.

So there are **three** candidate answers in the codebase and the published one is
none of them:

| Quantity | Value here | Where |
|---|---|---|
| differential × baseline | £32,580 | `WillAnalysisService` — **published** |
| differential × taxable estate | £25,180 | `EstateAgent`'s own working |
| actual tax reduction from making the gift | £50,902 | nowhere |

### Direction of error: it varies by household — it is not a conservative bias

On the `calculate()` path the baseline (£814,500) **exceeds** the taxable estate
(£629,500), so the figure **overstates** the rate saving. On the `analyze()` path
the baseline (£489,500) is **below** the taxable estate (£858,780), so it
**understates** it. **The base is not biased one way; it is simply unrelated to
the quantity the sentence describes.** I cannot answer "in whose favour" with a
single direction, and that is itself the finding: a figure whose error changes
sign with the household cannot be defended as conservative.

### The batch's rate fix amplifies this under any configuration change

The discrepancy is `(taxable estate − baseline) × differential`. Both sides now
scale with the configured differential, so at the guard's own 41%/31%
configuration — a 10-point differential — **the contradiction grows two and a
half times.** Making the rate correct without correcting the base widens the gap
the moment anyone moves the configuration. Not a reason to withhold the rate fix;
a reason not to leave the base for long.

### Verdict on Q2

**The base is incorrect, the error is material, it is user-visible today as a
self-contradicting sentence, and its direction varies.** File as its own item at
**high** severity — higher than W-0433, whose amounts were all correct. The item
must decide *which* of the three quantities the sentences mean, then use one
definition in both `WillAnalysisService` and `EstateAgent` (Rule 20).

**Not a reason to hold this batch.** The batch did not move this figure: at the
current 40/36 configuration `standard − reduced` is exactly `0.04`, so the
published number is byte-identical before and after. The agent's judgement was
sound.

---

## Q3 — Does W-0433's denominator change produce the statutorily correct percentage?

### YES on the bequest path. Confirmed against the statute and on live data.

Sch 1A para 5 compares the **donated amount** with **10% of the baseline
amount**. The new code publishes `rateTestAmount ÷ baseline`, where
`rateTestAmount` is the survivor's donated amount and `baseline` is Sch 1A's own
denominator, computed three lines above. **Numerator and denominator are both the
statute's.** The old `÷ netEstate` invited a comparison between two percentages
of different things.

**Verified live, not taken from the branch doc:**

```
charitable_giving_percent = 1.2277470841006752
charitable_baseline       = 814500.0
charitable_rate_test_amount = 10000.0
```

£10,000 ÷ £814,500 = **1.2277%** exactly. Against the net estate (£989,500) it
would be 1.0106%. The reconciliation the test asserts —
`percent ÷ 100 × baseline == rate_test_amount` — holds on live data.

*(The branch doc's "0.6% → 0.81%" is from the persona's figures at the time it was
written; the local household now computes 1.0% → 1.2%. The change is the same
change; I verified the definition rather than the persona's arithmetic.)*

### The pooled s23(1) exemption is NOT disturbed

The edit is confined inside `if ($bequestTotal > 0)` and assigns only
`$charitablePercent`. `$charitableAmount` (the pooled exemption) and
`$rateTestAmount` are untouched, as is the `$charitableAmount = $bequestTotal`
line above and every line of the exemption's journey to `charitable_deduction` →
`$taxableEstate`. Live run confirms `charitable_deduction` intact.

**My predecessor's Q2 coupling is undisturbed and remains in force:** the pooled
exemption is correct *only because the model never settles the first death*; if
any future change settles it, the pooling must be removed in the same change.
Nothing here moves either half of that pair.

### CONDITION — the profile-percentage path still carries the original defect, and the batch has given one published key two meanings

When `$bequestTotal == 0` the fix does not run. `$charitablePercent` remains the
user's `IHTProfile.charitable_giving_percent`, which is a percentage of the **net
estate** by construction — `$charitableAmount = $netEstate * ($charitablePercent / 100)`
— and the message then compares it with a **baseline**-derived threshold. That is
exactly the W-0433 defect, unfixed.

**And it is now worse than before the batch**, because the published field
`charitable_giving_percent` means *"÷ baseline"* on one branch and
*"÷ net estate"* on the other. One key, two definitions, decided by whether the
user recorded a will bequest.

**Reachability:** no seeded profile has a non-zero value today (I queried:
**0 rows** with `charitable_giving_percent > 0`), so **no fixture exercises this
branch** — but any user can enter a percentage on their Inheritance Tax profile
and reach it. Same shape as the defects this cycle keeps finding: correct at
every layer the tests touch.

**The rate decision itself is unaffected** — it compares amounts
(`$rateTestAmount >= $threshold`), not percentages. Only the published percentage
is wrong. Non-blocking; must be closed before W-0433 is called done.

---

## What these changes make newly reachable, and what now disagrees

Checked as instructed, not only line-by-line.

### The "inert admin control" premise is factually wrong — this affects the escalation

team-lead escalated to CSJ whether retiring the plans-screen threshold control
needs product sign-off. **There is no such control.**

- `grep -rln "PlanConfiguration" app/Http/ resources/js/` → **nothing**.
- No `plan-config` / `planConfig` routes exist in `routes/`.
- `estate.charitable_giving_threshold_percent` appears only in
  `PlanConfigService` (its own defaults array and the new comment),
  `PlanConfigurationSeeder:56` and two tests.

So the key is settable **only** by editing the seeder or the database row.
**Nothing was retired from any screen.** The product question as posed rests on a
premise that does not hold — that is a factual correction, and what CSJ does with
the corrected premise remains CSJ's call.

Related, and it cuts the same way: `charity_threshold_percent` is **display-only**
in the admin Tax Settings screen (`TaxSettings.vue:1380`, no `v-model`), while
`reduced_rate_charity` **is** editable (`:1365`). So the Schedule 1A threshold
cannot be moved from any admin screen today either.

### Tax risk of leaving the key seeded-but-inert: **none**

- **No reader remains.** `getCharitableGivingThreshold()` was its only consumer
  and now delegates; the only other consumer of that method,
  `EstatePlanService:590`, receives the same number.
- **The units contract is preserved, which was the real risk.**
  `charity_threshold_percent` is stored as a **fraction** (0.10 — confirmed both
  in the seeder and by the admin screen rendering it `* 100`), and
  `getCharitableThresholdPercent()` returns that fraction. Returning
  `* 100` = `10.0` keeps the **percent** unit every caller of
  `getCharitableGivingThreshold()` has always received. **Nothing disagrees.**
- Residual is documentation drift only: someone editing the seeded key later and
  expecting an effect. The docblock states this explicitly. Adequate.

### `/plans/estate` and `/estate` now agree on the threshold and still disagree on the percentage

With the delegation in place, both surfaces take the Schedule 1A threshold from
one home. **But W-0433's acceptance box "One definition of 'charitable giving as
a percentage' ... read by both surfaces" is ticked `[x]` and is not met:**

| Surface | Numerator | Denominator |
|---|---|---|
| `/estate` (`IHTCalculationService`) | `rateTestAmount` — the **survivor's** will | baseline |
| `/plans/estate` (`EstatePlanService::charitablePercentage()`) | `charitable_total` — the **logged-in user's own** will | baseline |

**The denominators now match; the numerators do not.** They coincide for a single
person and for any household where only one partner left a legacy, so no fixture
discriminates — the same blindness my predecessor recorded for the persona. This
is a denominator match, not one definition, and the box should say so.

### Newly reachable, checked rather than assumed

- **`IHTPlanning.vue`'s `charitableThresholdLabel`** derives the percentage from
  `charitable_threshold ÷ charitable_baseline`, and falls back to the literal
  `'10%'` when either is zero or absent. The fallback is a hardcoded threshold —
  but it is unreachable in any state that renders a threshold figure, and
  degrading to the statutory default beats rendering `NaN%`. **Acceptable.** The
  branch doc's §7 catch of `charitable_baseline` missing from the hand-written
  mapping was real and is correctly fixed.
- **`GiftingStrategy:214`'s gate** now reads `$charitablePercent < $thresholdPercent`,
  comparing the profile's percentage-of-net-estate against a
  percentage-of-baseline threshold. **Pre-existing, not introduced** — the old
  `< 10` had the identical mismatch — and no behaviour changes today
  (`0.10 × 100 = 10`). Worth recording because it is the same denominator
  confusion W-0433 exists to remove, one file over. The control-flow-literal
  observation the branch doc raises is sound and the fix is right.
- **`ContributionWaterfallService`** now reads `government_bonus_rate` from the
  same `lifetime_isa` array it already read the allowance from, with a
  `?? 0.25` fallback matching the seeded value. The Lifetime ISA bonus is a
  statutory rate (25% under the Savings (Government Contributions) Act 2017) and
  Rule 2 governs it. **Correct.** The 25% *withdrawal penalty* in the same config
  block is untouched — out of scope here, and I found no user-facing literal for
  it in this sweep.

---

## Rule 2 instances this batch missed

**W-0432 records "Fix — all instances closed, 2026-08-23. Nine routings." That
claim is not accurate.** Seven user-facing tax-value literals remain, **three of
them in the two files this batch edited.** Listed worst first.

### Same family — the Schedule 1A threshold, user-facing, live

**1. `app/Services/Estate/WillAnalysisService.php:27` — the sharpest miss.**

```php
public const UNVALUED_CHARITABLE_GIFTS_MESSAGE = '… whether you have reached the 10% needed for the reduced Inheritance Tax rate. …';
```

**Same class, same `message` key, same return array** as the three sentences this
batch just made configuration-driven — it is the value of `message` whenever
`hasUnvaluedCharitableGifts()` is true. Under a 12% configuration this branch
says 10% while the other three say 12%, in one field.

**It survived because it is a `const`** and a constant cannot interpolate. That
is a **third structural blind spot**, alongside the two the batch documented
(decimals in arithmetic; literals in comparisons): *a rate in a compile-time
constant is invisible to a sweep that expects an expression.* Worth having beside
the other two.

**2. `app/Agents/EstateAgent.php:710, 713, 716, 717, 728` — five instances.**
*"reach the 10% threshold"*, *"10% of baseline (£…)"*, *"meets or exceeds the 10%
threshold of £…"*, *"is X% of the £… baseline … The 10% threshold is £…"*,
*"Shortfall to 10% threshold"* — with `$reducedRatePercent` **correctly
interpolated right beside them**. This is precisely the half-fixed shape W-0432's
own §2 warns about, and `EstateAgent` is one of the sites
`TaxConfigService`'s docblock names.

**3. `app/Services/Plans/EstatePlanService.php:215`** —
*"Discuss adding or increasing charitable bequests to reach the 10% threshold."*
In the file this batch edited, on the surface it corrected.

### Second tier — tax values with existing configuration homes, outside the charitable family

Listed for the next sweep, not for this batch.

**4.** `EstatePlanService:261` — *"the immediate 20% charge on amounts exceeding
the Nil Rate Band"*. `TaxConfigService::getCLTLifetimeRate()` exists and is the
home.
**5.** `PersonalizedTrustStrategyService:197, 198, 242, 243` — 20% / 25% / 40%
chargeable-lifetime-transfer entry and death charges. Configuration holds
`lifetime_rate`, `lifetime_rate_grossed_up` and `additional_death_charge`.
**6.** `TrustService:326` — *"Entry charge (20% over NRB)"*.
**7.** `CrossModuleStrategyService:166, 168` — *"40% tax relief"* (income tax
higher rate; `getIncomeTax()` is the home).

### Exclusions confirmed correct — do not sweep these

I independently re-checked the branch doc's exclusion list and agree with all of
it: `LifePolicyStrategyService:271` (60/40 asset allocation),
`PersonalizedGiftingStrategyService:284` and `AssetLiquidityAnalyzer:165`
("10% per year" gifting staging), `CashFlowProjector:391` (0.25 discretionary
suggestion), `NetWorthAnalyzer:190` (30% concentration), and the waterfall's own
allocation heuristics. **None is a tax value.** A guard banning percentage
literals outright would manufacture defects here.

**Pass 2 result:** I ran the rate-shaped-decimal pass across
`app/Services/Estate/`, `app/Services/Plans/` and `EstateAgent.php` myself. Apart
from the `* 0.04` this batch fixed, **it is clean** — every remaining hit is an
allocation or liquidity heuristic. The agent's `* 0.04` really was the notable
one, and the second pass is now worth keeping.

---

## Two completeness claims inside the batch that are themselves incomplete

The batch's own headline lesson — *"a completion note is load-bearing; if a
consolidation leaves a survivor, the note is the thing that hides it"* — applies
to its own correction.

**1. The corrected docblock names one survivor. There were two.**
`TaxConfigService::getCharitableReducedRate()` now reads: *"**One** site still
read the array directly with its own `?? 0.36`:
`WillAnalysisService::getCharitableStatusMessage()`."* The batch fixed **two** —
`GiftingStrategy:213` is the board's own "ninth instance". And
`EstatePlanService:504` still reads
`$ihtConfig['reduced_rate_charity'] ?? TaxDefaults::IHT_CHARITABLE_RATE` today.

*That last one is **not** a Rule 2 violation* — it reads configuration and falls
back to a `TaxDefaults` constant, which is the permitted safety-net convention.
But the note's enumeration is again not accurate, and accuracy is the whole point
of the lesson.

**2. The guard has a hole, and it is in the batch's highest-priority item.**
The statement-of-law assertion at `EstatePlanRefactorTest:472-477` asserts
`toContain('10%')` — **a literal**. `RateLiteralsComeFromConfigurationTest` moves
all four rates but **never touches `EstatePlanService`**.

> **Re-hardcoding "10%" in `EstatePlanService`'s `sprintf` leaves the whole suite
> green.**

So **item 1 — the wrong statement of law, the batch's own top priority, live on
`/plans/estate` and in printed plans — is the one fix not protected by a
moved-configuration guard.** This is my predecessor's C3 recurring inside the
batch that documented the lesson. The other four rates all move; this one does
not. Cheapest close: assert the message under `moveEveryRate()` and expect `12%`.

**What the guard does well, recorded so it is not lost:** it drives the real
services rather than asserting accessors, it moves **all four** rates to values
nothing else uses, and the pair of waterfall mutations (bonus re-hardcoded in
arithmetic; re-hardcoded in prose only) genuinely proves the test sees the figure
and the sentence independently. The §1 lesson — *the config-key assertion would
have passed against all three defects* — is correct and is the most transferable
thing in either branch doc.

---

## Conditions

**None blocks the batch.** All are follow-ups or corrections to claims.

**C1 — File `potential_saving`'s base as its own item, severity HIGH.**
Higher than W-0433, because W-0433's amounts were all correct and this one's are
not. It renders a sentence that contradicts its own printed arithmetic by 43%
today. The item must pick one definition of "the charitable rate saving" and use
it in both `WillAnalysisService` and `EstateAgent`. **Do not fold into this
batch** — same reasoning the agent used for W-0433, and it is correct.

**C2 — Close the profile-percentage path before W-0433 is called done.**
`charitable_giving_percent` currently means "÷ baseline" on the bequest branch
and "÷ net estate" on the profile branch. Non-blocking, unexercised by fixtures,
reachable by any user.

**C3 — Add a moved-configuration assertion for `EstatePlanService`'s statement of
law** before W-0432 is called covered. It is the only one of the batch's rate
fixes a re-hardcoding would survive.

**C4 — Correct W-0432's "all instances closed" claim and add the seven instances
above**, marking items 1–3 as same-family and user-facing. Record the
**constant-literal blind spot** beside the arithmetic and control-flow ones.

**C5 — Correct `TaxConfigService::getCharitableReducedRate()`'s note again:** two
survivors, not one, and `EstatePlanService:504` still reads the array (legitimately).

**C6 — Untick W-0433's "one definition read by both surfaces"** or re-word it to
what was achieved: one denominator, two numerators, undiscriminated by any
fixture.

**C7 — For team-lead's escalation:** no admin control exists for the plan-config
threshold, and `charity_threshold_percent` is display-only in Tax Settings.
**Tax risk of the inert key: none.**

---

## What I did not review

- The rendered pages. `/plans/estate` and `/estate` both carry changed sentences
  and **the browser was held by another agent throughout; I did not take it.**
  This clearance is on the statute, the code, the live calculation and the test
  suites, not on the screen. The batch still owes the rendered-page verification
  its own doc records as outstanding.
- The F-0026 projection hunks co-resident in `IHTCalculationService.php`.
- `effective_tax_rate` and `iht_liability` (W-0154 / W-0188, untouched).
- Whether retiring the plan-config key needs **product** sign-off — not a tax
  question. I have corrected only its factual premise (C7).
