# W-0008 / W-0205 — statutory gate, tax-compliance-reviewer, 2026-08-25

Discharges the gate `quality-lead` could not run (`pr716-certification-2026-08-25.md`,
"WHAT I COULD NOT DO" §1). Scope: the two items it blocked, and only those.

Branch `feature/icecube/w0008-w0146-projection-fees-and-dead-service` at `940e749d9`.
Everything below was **executed**, not read. Where the dispatch asserted something, I
reproduced it or contradicted it. Test runs used `DB_DATABASE=laravel_testing_tax`.

**Headline: both items CLEARED WITH CONDITIONS. One condition blocks, and it is three
sentences and a test name — not a line of arithmetic. Two statutory defects found, both
pre-existing, one of which reaches a charge. One assertion in the dispatch was wrong and
one thing I was about to assert myself was wrong; both are recorded below.**

---

## Item 1 — W-0205: Gift Aid moved from net income to adjusted net income

### **CLEARED WITH CONDITIONS** — one condition BLOCKS

### 1. Is the statutory reading correct?

**On Gift Aid: yes, completely.** The change is right and the reasoning is right.

- **ITA 2007 s23 Step 2** — net income is total income less the reliefs **s24** lists.
  The s24 list is closed. It includes, among others, loss reliefs under Part 4, qualifying
  loan interest under s383, and pension relief given under **FA 2004 s193(4) or s194(1)**
  (relief on making a claim). **It does not include Gift Aid.**
- **Gift Aid relief is delivered by band extension, not by deduction.** ITA 2007 Part 8
  Chapter 2: s414 treats the donation as made net of basic rate tax; **s415 increases the
  individual's basic rate limit and higher rate limit by the grossed-up amount**. Higher-
  and additional-rate relief comes from more income falling into a lower band. Nothing is
  deducted at Step 2.
- **ITA 2007 s58** — adjusted net income is net income, less the grossed-up Gift Aid
  donation, less the gross amount of relief-at-source pension contributions, plus any
  s457/s458 relief added back.

The service now does exactly that. `UKTaxCalculator` independently corroborates the
band-extension half — `app/Services/UKTaxCalculator.php:731` applies
`->extendedBy($giftAidGross)` under an s414 comment, and does **not** reduce taxable
income by the donation. The two engines agree on Gift Aid. **Cleared.**

### 1b. FINDING — the statutory claim the fix travels with is wrong, and this PR pins it green

> **The Blind Person's Allowance is not a s58 deduction. It cannot be, by construction.**

ITA 2007 s58 has exactly four steps: take net income; deduct grossed-up Gift Aid; deduct
gross relief-at-source pension contributions; add back s457/s458 relief. **There is no
BPA step.** The BPA is granted by **ITA 2007 s38** and is deducted at **s23 Step 3** —
*after* net income, from net income, to reach taxable income. Adjusted net income starts
from net income at Step 1, so a Step 3 allowance is downstream of it and cannot reduce it.

The claim "Gift Aid belongs at s58, with the Blind Person's Allowance" appears **five
times**, and this PR added or reinforced three of them:

| Where | Status |
|---|---|
| `workforce/ops/board/W-0205-…md:34` — "at **adjusted net income** (ITA 2007 s58), alongside the Blind Person's Allowance" | pre-existing (the premise it all came from) |
| `app/Services/Tax/IncomeDefinitionsService.php:38-39` — "It comes off one definition further down, at s58, with the Blind Person's Allowance" | **added by this PR** |
| `resources/js/components/UserProfile/IncomeDefinitionsPanel.vue` W-0205 comment — "It belongs here, at s58, with the Blind Person's Allowance" | **added by this PR** |
| `tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php` — `it('deducts Gift Aid and the Blind Person\'s Allowance at the same step')`, comment "Both are s58 deductions and neither touches s23 Step 2" | **added by this PR** |
| `app/Services/Benefits/ChildBenefitService.php:207-210` — "computes ANI per HMRC ITA 2007 s58: total income − gross employee pension contributions − grossed-up Gift Aid − **Blind Person's Allowance**" | pre-existing |

**The arithmetic error is pre-existing and this PR does not change it** — I proved that in
§2 below. But the PR writes the wrong statute into the code twice more and adds a **test
that certifies it**, which is the mechanism by which a future correct fix gets rejected as
a regression.

**It is a live money error, measured, not argued.** Probe on a registered-blind user
inside a rolled-back transaction:

```
income=110000 blind=true  net=110000.00  ANI=106750.00  PA=9195.00  tapered=true
income=110000 blind=false net=110000.00  ANI=110000.00  PA=7570.00  tapered=true
income= 63000 blind=true  net= 63000.00  ANI= 59750.00  PA=12570.00 tapered=false
income= 63000 blind=false net= 63000.00  ANI= 63000.00  PA=12570.00 tapered=false
```

- **Personal Allowance taper.** ANI is understated by the BPA (£3,250 for 2026/27), so the
  taper reduction is understated by £1,625 and **the allowance is overstated by £1,625**
  for any registered-blind user in the £100,000–£125,140 band.
- **High Income Child Benefit Charge.** `ChildBenefitService:218` reads this ANI. A
  registered-blind user on £63,000 is shown ANI of £59,750 — **below the £60,000
  threshold**, so the app tells them they owe no charge when they owe roughly 15% of the
  benefit.
- **It contradicts the app's own tax engine.** `UKTaxCalculator:720` computes
  `$adjustedNetIncome = max(0.0, $totalIncomePre - $giftAidGross)` — **no BPA**, which is
  correct. `ChildBenefitService`'s docblock claims the two agree ("the same ANI value used
  by the PA-taper calculation in UKTaxCalculator"). They demonstrably do not, for any
  registered-blind user. **Two contradictory answers to one statutory question in one
  product — Rule 20 shape, in a tax service.**
- **It reaches Fyn.** `CoordinatingAgent:2694` hands the whole payload to the AI as a tool
  result, so the wrong ANI can be stated to a user in conversation.

**Separately and in the opposite direction:** `UKTaxCalculator` never applies the BPA as
an allowance at all — `grep -n 'blind' app/Services/UKTaxCalculator.php` returns nothing —
so the same user's tax is *overstated* by the allowance they are entitled to. One missing
concept, two opposite errors, in two engines.

### **CONDITION 1 (BLOCKING).** Correct the three new BPA assertions this PR adds. Comment text and one test name/comment only — **change no arithmetic**, because changing it would move `adjusted_net_income` and break the item's own acceptance 3. Specifically: `IncomeDefinitionsService.php:38-39`, the `IncomeDefinitionsPanel.vue` W-0205 comment, and the test case `it('deducts Gift Aid and the Blind Person\'s Allowance at the same step')` — which should say what it actually pins (both are deducted after net income *in this service*) rather than that both are s58 deductions.

I am blocking on this and I want to be plain about why, because it changes nobody's
number today. It is a false statement of UK tax law being written into a tax service and
pinned green by a new test, in a diff whose entire purpose is to attach the right statute
to the right line. Minutes to fix. A gate that lets this through has not gated anything.

### **CONDITION 2 (does not block).** Raise the arithmetic as its own board item: remove the BPA from adjusted net income in `IncomeDefinitionsService`, correct `ChildBenefitService`'s docblock, and decide separately whether `UKTaxCalculator` should grant the BPA at s23 Step 3. It moves real figures for registered-blind users (PA taper and HICBC), so it needs its own gate and its own before/after.

---

### 2. Are `adjusted_net_income` and `threshold_income` genuinely unchanged?

**Yes — and I can give you something stronger than the persona measurement.**

**By construction, bit-for-bit.** The two expressions have identical operator association:

```
before:  netIncome = ((T - P) - G)        adjustedNetIncome = (((T - P) - G) - B)
after:   netIncome =  (T - P)             adjustedNetIncome = (((T - P) - G) - B)
```

Both reduce to `((T − P) − G) − B` evaluated left to right, so they are the **same IEEE-754
computation**, not merely the same value. The `max(0.0, …)` clamps are applied *after*
`$adjustedNetIncome` is derived in both versions, so they cannot introduce a divergence at
the floor either. Fuzzed over five input sets including negatives and 1e9:

```
T=159289.6 old_anl=144689.60000000001 new_anl=144689.60000000001 identical=true
T=100000   old_anl=93740              new_anl=93740              identical=true
T=0        old_anl=-9380              new_anl=-9380              identical=true
T=1234.56  old_anl=-2919.0100000000002 new_anl=-2919.0100000000002 identical=true
T=1e9      old_anl=999996869.9289999  new_anl=999996869.9289999   identical=true
```

`threshold_income` and `adjusted_income` branch from `$totalIncome` on lines the diff does
not touch, so they cannot move.

**And by execution, because construction is what I was asked not to trust.** I reverted
both lines on disk, ran the service against the live personas, restored, and confirmed
`git diff --stat` clean:

```
                        PRE-FIX                              POST-FIX (measured)
user 104  net=144689.60 ANI=144689.60 thr=147689.60   net=147689.60 ANI=144689.60 thr=147689.60
user 106  net=230250.00 ANI=230250.00 thr=231000.00   (ANI/thr unchanged)
user 108  net= 28500.00 ANI= 28500.00 thr= 30000.00   (ANI/thr unchanged)
user 105  net=128880.00 ANI=128880.00 thr=128880.00   (ANI/thr unchanged)
```

**Acceptance 3 holds.** ANI £144,689.60 before and after; threshold income £147,689.60
before and after; net income £144,689.60 → £147,689.60. The dispatch's figures reproduce
exactly. `adjusted_income` (£170,889.60) and both tapered allowances also unchanged.

**Consumer sweep re-run independently.** Of the six consumers of
`IncomeDefinitionsService::calculate()`, **none reads `net_income`** — confirmed by
grepping each file, not by reading the table. `UserProfileService:585`'s `net_income` is
`$detailedTax['summary']['net_income']`, the `UKTaxCalculator` take-home figure. The
collision is correctly identified. `tests/Unit/Services/Tax/IncomeDefinitionsServiceTest.php`
and `tests/Feature/Investment/ProjectionIsNetOfFeesTest.php`: **35 passed, 84 assertions.**

### 3. Net income and threshold income are now the same number. Is that right?

**Yes. Verified against the statute rather than from memory — and my memory was wrong on
the way there, so this is worth reading.**

**FA 2004 s228ZA(5)** — threshold income is:

> net income (ITA 2007 s23 Step 2)
> **+** amounts given up under a relevant salary sacrifice or flexible remuneration
> arrangement made on or after 9 July 2015
> **−** the gross amount of member contributions eligible for relief under s192 (relief at source)
> **−** lump sum death benefits taxed under Part 9 ITEPA 2003

For a user with none of those three, **every adjustment is nil and threshold income is
net income identically.** The coincidence is correct, and the code produces it correctly:
net income = `T − employee`, threshold income = `T − employee`.

**Where I nearly filed a false defect.** I had reasoned from policy — threshold income
exists so a high earner cannot contribute their way under the £200,000 gate, therefore
pension contributions must be added back — and was about to report that the app's
threshold income wrongly deducts net-pay contributions, contradicting W-0189. **That is
wrong.** s228ZA(5) adds back only *salary sacrifice*. Net-pay contributions are added back
to **adjusted income** under s228ZA(4), not to threshold income. Checked against
legislation.gov.uk s228ZA and HMRC PTM057100, which agree with each other and against me.
**W-0189's fix is right and the app's `T − employee` is right.** Recorded because the
dispatch asked me to assume it of anything asserted from a definition, and it turned out
to be true of me.

**One imprecision in the comment, not a defect.** `IncomeDefinitionsService.php:68-69`
names only two exceptions ("no salary sacrifice and no relief-at-source contributions").
s228ZA(5) has a third — taxed lump sum death benefits. The application models neither RAS
nor lump sum death benefits, so the omission is harmless, but the comment presents itself
as a statutory reading and is one adjustment short.

**A related observation, no action needed here.** The app has **no relief-at-source flag**,
so every DC pension is treated as net pay. A personal pension or SIPP is always RAS, and a
RAS contribution does **not** reduce net income (relief is at source plus band extension).
So a RAS contributor's `net_income` is understated. Because nothing but the panel reads
`net_income`, and because ANI and threshold income both happen to land right under s58
Step 3 and s228ZA(5), **no calculated outcome is affected**. Worth knowing before anyone
gives `net_income` a consumer.

### 4. The salary-sacrifice add-back — **it is a defect. Severity HIGH. Not this PR's.**

**Your reading of FA 2004 s228ZA is correct.** s228ZA(5) expressly adds back employment
income given up under a relevant salary sacrifice arrangement made on or after 9 July
2015 — the provision exists for precisely the reason you gave.

**Measured end-to-end through the production consumer, not argued.** Rolled-back fixture:
`annual_employment_income` £205,000, DC pension salary £205,000, employee 5%, employer
30%, `salary_sacrifice` toggled:

```
sacrifice=true   arrangement=salary_sacrifice  thr=194750.00  adjinc=266500.00  AA=60000.00  tapered=false
   AnnualAllowanceChecker: tapered=false available=60000
sacrifice=false  arrangement=net_pay           thr=194750.00  adjinc=266500.00  AA=60000.00  tapered=false
   AnnualAllowanceChecker: tapered=false available=60000

coded threshold      = 194750.00   (T - employee)
s228ZA(5) threshold  = 215250.00   (T + sacrifice, if income is entered post-sacrifice)
s228ZA(5) threshold  = 205000.00   (T, if income is entered pre-sacrifice)
adjusted income      = 266500.00   -> £260,000 gate: CROSSED
correct AA           =  56750.00
```

**The two runs are byte-identical.** The flag is published and never acted on, exactly as
you said. The user is told their Annual Allowance is **£60,000** where s228ZA gives
**£56,750**; contributing to the stated figure leaves £3,250 exposed to an annual
allowance charge at their marginal rate (~£1,300 at 40%, ~£1,462 at 45%). The error is
**always in the direction of overstating headroom**, which is the harmful direction.

Reach is wider than the panel: `AnnualAllowanceChecker:115` (reproduced above),
`TaxStrategyMath:407`, and `CoordinatingAgent:2694` (Fyn can say it out loud).

**Why it does not block this PR.** It is pre-existing, untouched by W-0205, and — to the
implementer's credit — **explicitly disclosed** in the `getPensionContributions()` docblock
on `origin/dev`, which names s228ZA(3) and states that the add-back is deliberately not
applied because nothing records whether `annual_employment_income` is the pre- or
post-sacrifice figure. That is an honest data-model limitation, not a shortcut.

**But the defence is narrower than it is stated to be, and the new item should say so.**
In the fixture above the taper is missed on **either** reading — £205,000 and £215,250 are
both over £200,000. Where both interpretations cross the gate the taper can be applied
without guessing anything. "We cannot know which figure it is" justifies not computing a
precise add-back; it does not justify never applying the taper. Recommend the item be
raised with that carve-out named, and with a `pension_arrangement === 'salary_sacrifice'`
caveat surfaced on the panel and to Fyn in the meantime.

---

## Item 2 — W-0008: investment projections net of fees

### **CLEARED WITH CONDITIONS** — none of them block

### 1. Is deducting total fees from the expected return defensible?

**Yes — and for the three ad-valorem components it is not an approximation, it is the
correct model.** Platform-percentage, adviser-percentage and fund OCF are all levied on the
fund value, so they are mathematically a drag on the growth rate. Subtracting them from the
return before compounding is the reduction-in-yield convention that UK retail projection
practice has used for decades. Modelling them as cashflows would give the same answer with
more machinery.

**Including the OCF alongside platform and adviser fees is correct.** The OCF is a real
ongoing cost borne by the investor; a projection net of platform and adviser but gross of
fund charges is not a net projection. `weightedOcfPercent()` value-weights it across
holdings, which is right, and the docblock's reason for reading `ocf_percent` rather than
the `CalculatesOCF` trait's estimate — *"a projection must charge the fee the user actually
recorded, not an estimate they never saw"* — is the correct call.

**Two caveats, neither blocking:**

**(a) Fixed-amount charges are modelled as a rate, and nothing says so.**
`platformFeePercent()` converts a fixed £ charge to a percentage of *today's* value **once**,
then applies that percentage for the whole horizon. A fixed fee is a *shrinking* percentage
as a pot grows and a *growing* one as it shrinks, so the model overstates its drag in a
rising market and understates it in a falling one. This is also the root of the pathological
case quality-lead found (PROBE D: £1,000 monthly fee on a £1,000 account → −1,195% return) —
one cause, two symptoms. The approximation is a reasonable first cut; it should be stated in
the docblock, and it strengthens the case for their unbounded-fee finding. **Confirmed
independently:** `platform_fee_amount` is `'nullable|numeric|min:0'` in both
`StoreInvestmentAccountRequest:69` and `UpdateInvestmentAccountRequest:79` — **no maximum**,
and it now compounds.

**(b) The provenance of the expected returns is unstated, and W-0008 makes that
load-bearing.** `RiskPreferenceService:34-85` hardcodes five return assumptions
(2.0 / 3.5 / 5.0 / 6.5 / 8.0% typical) with **no comment saying whether they are gross or net
of fund costs**, nominal or real, or where they came from. If they were ever set as
achievable *net-of-fund-cost* returns, subtracting the OCF double-charges it. My reading is
that they are asset-allocation/index assumptions and therefore gross — 90% equities → 8.0%
nominal reads as an index assumption — so the deduction is right. **But that is inference,
and before this PR it did not matter.** Now it does.

### **CONDITION 3 (does not block).** State the basis of the five `expected_returns` assumptions in `RiskPreferenceService` — gross or net of fund charges, nominal or real, and their source. One docblock. It is the only thing that could make this change over-charge rather than under-charge.

### **CONDITION 4 (does not block).** Note the fixed-fee-as-constant-rate approximation in `platformFeePercent()`'s docblock, and support quality-lead's ask for a `max:` on `platform_fee_amount` or a floor on the net return.

### 2. Consumer Duty

**The state this PR removes is the indefensible one. This is not close.**

A "Total Fees 1.72%" card sitting directly above a chart compounding the full gross return
is a projection that is not fair, clear and not misleading: it overstates outcomes by
**5.4% at ten years on the persona portfolio**, systematically in the customer's favour, and
it renders the fee disclosure inert — the user is told charges exist and then shown a figure
that behaves as though they do not. Under the consumer understanding outcome, a disclosure
the rest of the screen contradicts does not equip a customer to make an informed decision;
it does the opposite. Net-of-costs performance projection is the settled convention across
the UK retail regime. **The "after" state is defensible; the "before" state was not.**

The caption implementation is honest and I checked it: `InvestmentProjectionChart.vue`
states the gross return and the charge **separately** ("7.59% expected return, less 1.40% in
charges") while the chart compounds the net one, and `InvestmentPerformance.vue:97` passes
`gross_expected_return ?? expected_return` so the risk profile is not blamed for the fee.
The D-21 inversion is genuinely avoided.

**Does the change need disclosure to users whose figures fall? Yes, briefly — and it is not
a tax question, so it is a recommendation, not a condition.** The caption explains the
*composition* of the new figure but not the *change*. A user who noted £404,771 last month
and sees £382,833 today has no way to tell an improved methodology from a market fall or a
bug. One line — in-app note, changelog, or a sentence beside the chart on first view —
closes it. **Product-lead or CSJ's call. I am not competent to rule on the FCA perimeter
question of whether PRIN 2A binds this product at all; that is `compliance-lead`'s.**

### 3. Rule 2

- **`InvestmentProjectionService.php`: CLEAN.** Swept for every threshold, rate and
  allowance in the 2026/27 table. **Zero.** The file contains no tax value, no statutory
  number, and no `TaxConfigService` need — the only constants are
  `DEFAULT_PROJECTION_PERIODS` and `MONTE_CARLO_ITERATIONS`, which are engine parameters,
  not tax. The fee arithmetic reads user-entered account columns throughout. **Nothing to
  raise.**
- **`IncomeDefinitionsService.php`: two pre-existing findings, neither introduced by this
  PR, neither blocking.**
  1. **Inline literal fallbacks instead of `TaxDefaults` constants**, in
     `calculateAdjustedAllowances()` (untouched by the diff): `?? 12570`, `?? 100000`,
     `?? 60000`, `?? 200000`, `?? 260000`, `?? 10000`, `?? 0.5`. Four have constants that
     should be used — `TaxDefaults::PERSONAL_ALLOWANCE`, `PERSONAL_ALLOWANCE_TAPER`,
     `PENSION_ANNUAL_ALLOWANCE`, `PENSION_MINIMUM_ALLOWANCE`. **There is no constant at all
     for the £200,000 threshold income limit**, and `PENSION_TAPER_THRESHOLD` (260000) is
     documented as "threshold for tapered annual allowance" when it is the *adjusted income*
     threshold — a name that invites exactly the wrong use.
  2. **The Gift Aid gross-up factor `1.25` is hardcoded** (`calculateGiftAidGrossUp()`,
     unchanged by the diff). It is the UK basic rate reciprocal (100/80) and is correct, but
     it is a rate literal in a tax service that does not read the configured basic rate.
- **Values verified against the live config, not the reviewer table.** Active tax year is
  **2026/27**: PA 12570, taper 100000, taper rate 0.5, BPA 3250, AA 60000, threshold income
  200000, adjusted income 260000, minimum allowance 10000 — all present in
  `TaxConfigService` and all matching the fallbacks. **Flagging the reviewer reference doc as
  stale**: it is headed 2025/26 while the application's active year is 2026/27. Every value I
  needed is identical across both years except the BPA (3130 → 3250), which is seeded
  correctly per year.

### 4. Does the Monte Carlo seeding undermine the projections? **This is the biggest finding in the dispatch, and the answer is split.**

You were right to make this the priority. I measured it rather than reasoned about it.

**It does NOT undermine the level, and it does NOT cause run-to-run flicker.**

```
E. determinism: identical inputs, ten runs -> unique p20 values = 1
F. simulated p50 vs closed-form lognormal median (no contributions):
   r=7%  vol=15% 10y : sim 184,524.83  closed form 179,948.41  (+2.54%)
   r=8%  vol=18% 10y : sim 187,352.61  closed form 189,269.17  (-1.01%)
   r=6%  vol=12% 20y : sim 280,095.32  closed form 287,484.86  (-2.57%)
```

The seed is a genuine feature: a user whose data has not changed sees the same number on
every surface, across cache expiry, and in the account view and the single-account portfolio
view alike. The level is unbiased — within ±2.6% of closed form — and the volatility drag is
modelled correctly (the median sits well below the arithmetic compound, as it should).

**It DOES break monotonicity, and that is a real product defect.** Because the seed is
derived from the economic inputs, any change to them re-rolls the entire sample, so the map
from inputs to displayed figure is neither continuous nor order-preserving:

```
B. monthly contribution 500 -> 700 in £10 steps:
   10 of 20 increases of a strictly-beneficial input LOWERED p20 (worst -3.85%)
   e.g. £500 -> £510 : p20 187,719.28 -> 180,491.73

C. fee 0.00% -> 1.00% in 0.05pp steps on an 8% gross:
   11 of 20 fee INCREASES RAISED p20
   e.g. fee 0.70% -> 0.75% : p20 187,476.92 -> 191,117.40
   end to end, the 1.00pp fee is worth -6.88%

A. expected return in 0.01pp steps: 6 of 20 strictly-better returns LOWERED p20 (worst -4.56%)

D. pure sampling noise, economics fixed, seed re-rolled 30 times:
   p20 range 7.49% of mean, sd 1.91%   |   p50 range 5.63%, sd 1.33%   |   p5 range 7.56%
```

**Read line C against line C.** The end-to-end effect of a full 1.00pp of fee is 6.88%. The
pure sampling range on p20 is 7.49%. **The signal this PR ships is the same size as the
noise it is drawn through** — which is why the "£8,329 adviser fee" retraction was right,
and why quality-lead's replacement £3,847 would have been just as wrong. Their 3.9%
peak-to-peak over 8 seeds was an under-sample; over 30 it is 7.5%.

The user-facing consequence is worse than the reporting one. A user who **cuts their adviser
fee from 0.75% to 0.70% is shown a projection £3,640 lower**. A user who **increases their
monthly saving by £10 is told they will have less**, half the time. That is exactly the
comparison a planning tool exists to support, and it is exactly the comparison W-0008 exists
to make visible.

**Whose defect is it?** `MonteCarloEngine::seedFromInputs()` (`:110-123`), not the W-0008
diff. It is pre-existing and it affects pensions, goals and retirement equally. **But W-0008
makes it product-facing for the first time**, because it introduces the fee as a projection
input and the product's whole claim about it — "your charges cost you this much" — is a
comparative one.

**The fix is standard, cheap, and would also make the retracted pound figure well-defined:
common random numbers.** Seed from an identity that excludes the economic parameters — user,
account, horizon, iteration count — instead of from `$expectedReturn`, `$monthlyContribution`
and `$startValue`. Two scenarios are then drawn on the same sample paths, the fee difference
is exact and monotone, and the per-user determinism the current seed was written for is
retained in full.

### **CONDITION 5 (does not block, but should be raised today).** Raise common random numbers on `MonteCarloEngine::seedFromInputs()` as a board item in its own right, with the monotonicity measurements above. It is not W-0008's to fix and it should not wait for the next person to rediscover it.

**I also endorse both of quality-lead's open W-0008 items** — the untested
`getAccountProjectedValue80()` (8 production call sites, zero coverage, and it moves
retirement income) and the fee bound. Neither is a tax question, so neither is mine to gate.

---

## VERDICT

| Item | Ruling |
|---|---|
| **W-0205** — Gift Aid at adjusted net income | **CLEARED WITH CONDITIONS** — Condition 1 **BLOCKS**; Condition 2 does not |
| **W-0008** — projections net of fees | **CLEARED WITH CONDITIONS** — Conditions 3, 4, 5 do **not** block |

**Both changes are correct. Neither has a defect in what it computes.** W-0205's arithmetic
is provably identical where it had to be and correctly moved where it had to move. W-0008's
methodology is sound, its Rule 2 posture is clean, and it removes a state that was closer to
a Consumer Duty problem than the fix is.

**Does this PR merge?**

**Yes — after Condition 1, which is three comment sentences and one test name, and no
arithmetic.** Nothing else on either item blocks. Everything else I found is pre-existing,
and two pieces of it are serious enough to go on the board today: **the Blind Person's
Allowance wrongly reducing adjusted net income** (Personal Allowance taper and High Income
Child Benefit Charge, both measured, and the app's two engines disagreeing with each other),
and **the missing salary-sacrifice add-back to threshold income** (Annual Allowance taper,
measured at £60,000 reported against £56,750 correct). Neither is W-0008's or W-0205's to
carry, and neither should be allowed to sit unraised behind a merge.

---

## RAISE AS NEW ITEMS

1. **The Blind Person's Allowance is deducted from adjusted net income and is not a s58
   deduction.** `IncomeDefinitionsService.php:51`. Overstates the Personal Allowance by
   £1,625 for a registered-blind user in the taper band, and understates the High Income
   Child Benefit Charge (`ChildBenefitService:218`). Contradicts `UKTaxCalculator:720`, which
   gets it right. Correct `ChildBenefitService:207-210`'s docblock in the same item.
2. **`UKTaxCalculator` never grants the Blind Person's Allowance at all** (ITA 2007 s38,
   s23 Step 3). Overstates tax for the same users the item above under-tapers. Pair with (1).
3. **Threshold income does not add back post-8-July-2015 salary sacrifice** (FA 2004
   s228ZA(5)). Measured: AA £60,000 reported against £56,750 correct. Note in the item that
   the "we cannot know pre- or post-sacrifice" defence does not hold where both readings
   cross the £200,000 gate.
4. **`MonteCarloEngine` seeds from its economic inputs, so projections are non-monotone in
   them.** Measurements in §4 above. Fix by common random numbers.
5. **`RiskPreferenceService`'s five expected-return assumptions have no stated provenance.**
   W-0008 makes gross-vs-net-of-fund-costs load-bearing for the first time.
6. **Rule 2 tidy in `IncomeDefinitionsService`:** inline literal fallbacks where
   `TaxDefaults` constants exist; no constant for the £200,000 threshold income limit;
   `PENSION_TAPER_THRESHOLD`'s docblock misnames it; hardcoded `1.25` Gift Aid gross-up.
7. **The reviewer reference table is stale** — headed 2025/26 against an active 2026/27
   configuration.

## WHAT I DID NOT DO

- **I did not verify anything in a browser.** My verification is service-level and
  statutory. quality-lead's browser check of the £382,833 figure stands unexamined by me.
- **I did not run the full suite.** I ran the two files this dispatch concerns
  (35 passed, 84 assertions) on `laravel_testing_tax`. quality-lead's full run stands.
- **I did not rule on the FCA perimeter** — whether PRIN 2A binds this product is
  `compliance-lead`'s call, not mine. I ruled on which of the two states is defensible, which
  is answerable either way.
- **I did not verify `/m` or native.** Neither item has a counterpart on either surface, per
  quality-lead's sweep, which I did not re-run.
- **No agents spawned**, per the dispatch.
