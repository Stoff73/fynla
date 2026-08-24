---
id: report-2026-08-21-iht-calculation-audit
title: Inheritance Tax calculation audit — IHTCalculationService and everything that feeds it
work_item: W-0154
author: tax-compliance-reviewer
date: 2026-08-21
status: complete
scope: report-only (no code changed, no database writes, no commits)
related: [W-0131, W-0132, W-0020, F-0001, F-0003-batch-b-estate-wills]
sources_register_rows_added: [A15, C4, C5, C6]
---

# Inheritance Tax calculation audit

Ranked by **what a user would be told wrongly**, not by code severity.

## ⚠️ The worked examples below are measured on a household that no longer exists

**Added 2026-08-21.** Every figure in this report was taken when the `peak_earners`
household was about **one-third entered**. `persona-passA3` has since completed the data
entry through the module forms. **The figures were correct when taken and are deliberately
NOT rewritten** — the findings are about mechanisms and are unaffected — but do not quote
them as the current position.

| | As audited (one-third entered) | Current (fully entered) |
|---|---|---|
| Gross assets | 1,846,780 | **2,021,780** |
| Liabilities | 305,000 | 305,000 |
| **Net estate** | **1,234,280** | **1,716,780** |
| Allowances | 850,000 | 850,000 |
| Charitable deduction | 10,000 *(the F1 defect)* | **20,000** *(F1 fixed)* |
| **Taxable estate** | 374,280 (David) / 224,280 (Sarah) | **846,780, both accounts** |
| **Inheritance Tax** | 149,712 / 89,712 | **338,712, both accounts** |

**F1 is verified fixed.** Both accounts now return the same figure, matching an independent
hand-computation to the pound, and the household charitable deduction is £20,000 rather than
£10,000. The £145,712 this report derives was the correct household answer **for the
one-third data**; £338,712 is the correct answer for the current data.

**Also closed since publication:** F7 and F11 (struck through in "What I could not
determine"), and the W-0136 framing — see that item, the residence band taper arithmetic
**exists**; what is missing is a second call to `calculateRNRB()` for the projected estate.

---

## Line-number errata — read this before fixing off the citations below

**Added 2026-08-21 after `fix-batch-G` was dispatched from this report.** Several line
references in the findings drift by 1–4 lines from the file as it actually stands. The
file itself has **not** moved (`IHTCalculationService.php` last modified 13:15:42, before
this audit began) — the drift is mine, from counting within `sed` ranges. Every finding
and every figure is unaffected. **These are the pinned, verified numbers:**

| Cited | Actual | What is there |
|---|---|---|
| `:104` | **`:103`** | `IHTProfile::where('user_id', $user->id)` — transferred bands |
| `:134` | **`:136`** | `$nrbAvailable = $nrbSingle * 2;` |
| `:148` | `:148` | `calculateNRBDeductionForGifts($user, $nrbSingle)` — correct as cited |
| `:158` | **`:161`** | the single `calculateRNRB(...)` call, passed `$totalNetEstate` |
| `:161` | **`:164`** | `determineIHTRate($user, $totalNetEstate, $nrbAvailable, ...)` |
| `:171` | **`:172`** | `$charitableFraction = ... / $totalNetEstate` |
| `:379` / `:380` | **`:383`** / **`:384`** | projected `$totalAllowances` reuse; charitable scaling |
| `:1233` | **`:1234`** | `$fullRNRB = $rnrbSingle * 2;` |
| `:1247` | **`:1248`** | `min($fullRNRB, $residenceNetValue)` |
| `:1275` | `:1275` | `max(0, $fullRNRB - $reduction)` — the zero floor, correct as cited |
| `:1318` | **`:1315`** | `IHTProfile::where(...)` in `determineIHTRate` |
| `:1324` | **`:1319`** | `$baseline = max(0, $netEstate - $nrbAvailable);` |
| `:1338` | `:1338` | `getCharitableBequestTotal($user, $netEstate)` — correct as cited |
| `:1699` | **`:1698`** | `Gift::where('user_id', $user->id)` — the first of three |

---

## How the figures below were obtained

I did **not** execute `IHTCalculationService::calculate()`. Four `vendor/bin/pest`
processes were live (`pgrep -f "vendor/bin/pest"` → 19956, 20249, 23718, 24718) and the
persona users are being driven in a browser. Every figure here is **hand-computed from
read-only queries** against `laravel` (users, gifts, IHT profiles, wills, bequests) and
reconciles **exactly** with the team lead's measured evidence — to the penny on all
three liabilities. Where I could not establish something without running code, I say so
rather than infer it.

The measured inputs, read 2026-08-21:

| | David (16) | Sarah (17) | Priya (20) |
|---|---|---|---|
| `marital_status` / `spouse_id` | married / 17 | married / 16 | married / 30 |
| Gifts in `gifts` | **1 CLT, £150,000, 2020-09-01** | none | none |
| `IHTProfile` row | **none** | **none** | **none** |
| Charitable bequest | £10,000 Cancer Research UK (id 51) | £10,000 British Heart Foundation (id 50) | £10,000 Cancer Research UK (id 57) |

There are **no `IHTProfile` rows at all** for these users, so `charitable_giving_percent`
is 0 throughout and every charitable effect below comes from the recorded will via the
W-0020 path.

---

## F1 — CRITICAL — One household, two bills: every per-person input is read from the logged-in user only, while every asset and liability is pooled

**What the user is told.** David is told he owes **£149,712**. Sarah is told the same
household owes **£89,712**. The combined net estate is identical (£1,234,280) on both
sides. The difference is £60,000 and depends only on who logged in.

**Where it is produced.** `app/Services/Estate/IHTCalculationService.php:148`

```php
$nrbDeduction = $this->calculateNRBDeductionForGifts($user, $nrbSingle);
$nrbAvailable = max(0, $nrbAvailable - $nrbDeduction['total_nrb_used']);
```

`calculateNRBDeductionForGifts()` (`:1695-1737`) queries
`Gift::where('user_id', $user->id)` only. David's £150,000 CLT of 2020-09-01 is inside
the seven-year window (today − 7y = 2019-08-21), so it reduces the pooled band by
£150,000 in his view and by nothing in Sarah's.

**The arithmetic, exactly:**

| | David | Sarah |
|---|---|---|
| Nil rate band | 650,000 − **150,000** = 500,000 | 650,000 − **0** = 650,000 |
| Residence nil rate band | 350,000 | 350,000 |
| Charitable deduction | 10,000 | 10,000 |
| Taxable | 1,234,280 − 850,000 − 10,000 = **374,280** | 1,234,280 − 1,000,000 − 10,000 = **224,280** |
| Liability @40% | **149,712** | **89,712** |

£150,000 × 40% = £60,000. That is the whole of the gap.

**The same defect in three more places** — every per-person input is user-only while
every asset and liability is pooled:

| Input | Line | Reads |
|---|---|---|
| Gifts | `:148` → `:1699` | `Gift::where('user_id', $user->id)` |
| IHT profile (transferred NRB/RNRB) | `:104` | `IHTProfile::where('user_id', $user->id)` |
| Charitable planning percentage | `:1318` | `IHTProfile::where('user_id', $user->id)` |
| Charitable bequests | `:1338` → `WillAnalysisService.php:131` | `Will::where('user_id', $user->id)` |

The bequest one is independently wrong on these personas: David's will leaves £10,000 to
Cancer Research UK and Sarah's leaves £10,000 to British Heart Foundation. The pooled
household estate deducts **£10,000**, never £20,000, whichever spouse is logged in.

**The stated compensating mechanism does not exist.** `:147` reads:

```php
// Spouse NRB is handled separately by SpouseNRBTrackerService
```

`SpouseNRBTrackerService` has **zero callers in the repository.** Verified with a
repo-wide grep: the only three hits are that comment, the identical comment at `:1689`,
and the class declaration at `app/Services/Estate/SpouseNRBTrackerService.php:13`. The
class is never instantiated, injected or invoked. The comment describes work nothing does.

**The number the model's own pooled logic implies** (stated as arithmetic, not as a legal
determination): household gifts £150,000, household charitable bequests £20,000 →
NRB 500,000 + RNRB 350,000 = 850,000; taxable 1,234,280 − 850,000 − 20,000 = 364,280;
**£145,712, identical for both spouses.** The charitable rate stays at 40% (baseline
1,234,280 − 500,000 = 734,280, threshold £73,428, gifts £20,000 — below).

**A second, separate error in the same deduction, at a boundary these personas do not
reach.** The gift total is deducted from the **pooled** band:
`max(0, $nrbAvailable - $total)` where `$nrbAvailable` is £650,000. IHTA 1984 s8A
transfers the **unused percentage** of the first-to-die's band, and that percentage
cannot go below zero — one spouse's gifts can exhaust their own £325,000 but can never
consume the survivor's. A £400,000 chargeable transfer by David would give:

- Code: `max(0, 650,000 − 400,000)` = **£250,000**
- s8A: David's band fully used → 0% transferable → Sarah keeps her own **£325,000**

**Trigger boundary: any single spouse's seven-year chargeable transfers exceeding
£325,000.** The per-person cap that would prevent this already exists one line up —
`min($nrbSingle, ...)` at `:1723` — but it is applied to the CLT subtotal, not to the
household deduction.

---

## F2 — CRITICAL — The doubled bands are produced without attribution, so the breakdown cannot be reconciled by the person reading it

**What the user is told.** `nrb_individual` £325,000, `nrb_transferred` £0,
`nrb_available` £500,000. And `rnrb_individual` £175,000, `rnrb_transferred` £0,
`rnrb_available` £350,000. Neither pair adds up. This is rendered as a three-line
breakdown — `IHTPlanning.vue:1398-1400` builds `nrb` / `nrbFromSpouse` / `totalNrb` from
exactly those three fields — so the user is shown all three numbers together and they do
not sum.

**Where the doubling happens.** `IHTCalculationService.php:134` and `:1233`:

```php
if ($isMarried) { $nrbAvailable = $nrbSingle * 2; }      // :134
if ($isMarried) { $fullRNRB = $rnrbSingle * 2; }         // :1233
```

while the reported components at `:215-216` and `:220-221` are `$nrbSingle` and
`$ihtProfile?->nrb_transferred_from_spouse ?? 0` — zero for a living couple, because no
`IHTProfile` row exists.

**Which half is wrong: the total is the modelled half; the components are the honest
half.** There is **no transferable nil rate band while both spouses are alive.** IHTA
1984 s8A creates the claim on the survivor's death and transfers a percentage of the
band unused on the first death. So `nrb_transferred: 0` is legally correct *today*, and
the `× 2` is a **second-death modelling assumption** — one the service states elsewhere
("For married couples, projects to SECOND DEATH", `:271`) but never surfaces in the
payload. The fix is not to write 325,000 into `nrb_transferred`; it is to label the
doubled band as the modelled second-death transfer it is.

**The £175,000 the user cannot account for is actually two unlabelled effects netting
out:** +£325,000 modelled spouse transfer, −£150,000 gift deduction.

**And the deduction has nowhere to go.** `iht_summary.current`
(`IHTController.php:110-116`) carries `nrb_available`, `nrb_individual`,
`nrb_transferred`, `nrb_message` — **there is no deduction field.** `nrb_deduction`
exists only in the raw `calculation` array (`:249`) and has **no consumer anywhere in the
repository** (see F10). The only way a user learns about the £150,000 is the prose
appended to `nrb_message` at `:152`: *"Reduced by £150,000 due to gifts made within the
last 7 years."*

Same for the residence band: when `fullRNRB` is capped by the residence value at `:1247`
(`min($fullRNRB, $residenceNetValue)`), `rnrb_individual` still reports the uncapped
£175,000 while `rnrb_available` reports the capped figure.

---

## F3 — CRITICAL — Married with data sharing off: both allowances doubled against one person's estate

**What the user would be told.** A married user whose spouse has not accepted sharing is
given **£650,000 + £350,000 = £1,000,000** of allowances against **their own assets
alone**. On a £700,000 individual estate that is a £0 bill where the correct answer is
£700,000 − 500,000 = £200,000 → **£80,000**.

**Where.** The doubling at `:134` and `:1233` gates on `$isMarried` alone —
`marital_status === 'married' && $spouse !== null` (`:99`). The assets and liabilities
gate on `$isMarried && $dataSharingEnabled` (`:107`, `:118`). The two conditions differ.

**The same inconsistency in RNRB eligibility.** `calculateRNRB()` is passed `$spouse`
unconditionally at `:158`, and all three eligibility helpers consult the spouse
regardless of `$dataSharingEnabled`:

- `hasMainResidence()` `:1405-1427` — returns true on the **spouse's** main residence
- `hasDirectDescendants()` `:1437-1449` — returns true on the **spouse's** children
- `getMainResidenceNetValue()` `:1459-1470` — **adds the spouse's residence** to the cap

So the residence nil rate band can be granted, and its cap raised, by a property that is
excluded from the estate being taxed.

**Mitigation, stated so this is not over-ranked.** `User::hasAcceptedSpousePermission()`
(`app/Models/User.php:782-801`) returns **true automatically** for any reciprocally
linked married pair. David/Sarah and Priya/Arjun are reciprocal, so this does not bite
them. It bites a married user whose link is not reciprocal and who has no accepted
`SpousePermission` row.

---

## F4 — HIGH — The charitable 10% test: the baseline is right in law, the percentage shown to the user is not, and nothing guards a nil liability

### The ruling the team lead asked for: excluding the residence nil rate band from the baseline is CORRECT. Do not "fix" it.

**IHTA 1984 Sch 1A para 5**, read on legislation.gov.uk 2026-08-21 (Latest available
(Revised); *"up to date with all changes known to be in force on or before 21 August
2026"*). The three-step method is in **paragraph 5**:

- **Step 1** — *"Determine the part of the value transferred by the chargeable transfer
  that is attributable to property in that component."*
- **Step 2** — deduct *"the appropriate proportion of the available nil-rate band"*,
  where the available nil-rate band is *"the amount (if any) by which (a) the nil-rate
  band maximum (increased, where applicable, in accordance with **section 8A**), exceeds
  (b) the sum of the values transferred by previous chargeable transfers made by D in the
  period of 7 years ending with the date of the relevant transfer."*
- **Step 3** — add back *"so much of the value transferred by the relevant transfer as
  (in total) is attributable to property that... is property in relation to which
  **section 23(1)** applies"* — i.e. the charitable gift itself.

Step 2 refers only to the nil-rate band maximum as increased by **s8A** (the transferable
nil rate band). It makes **no reference to ss.8D–8M**, which are the residence nil rate
band. HMRC's manual states the point directly: when calculating the baseline amount you
deduct the available nil rate band and transferable nil rate band but **must not deduct
the residence nil rate band or transferable residence nil rate band**, and an adviser who
does will underestimate the baseline and compute a charitable donation too small to
qualify.

`IHTCalculationService.php:1324` — `$baseline = max(0, $netEstate - $nrbAvailable)` —
is therefore **correct as to which allowance it deducts**, and the docblock at `:1302`
saying so is accurate. Priya's £65,000 baseline (715,000 − 650,000) is the right
construction; **the £6,500 threshold is not the defect.**

**Step 3 is also satisfied, by construction — and fragilely so.** `$totalNetEstate`
(`:128`) is gross assets less liabilities with the charitable gift **still inside it**;
the gift is subtracted only later, at `:175`. So no explicit add-back is needed. **Any
refactor that moves the charitable deduction earlier will silently break Step 3.** This
belongs in a comment at `:1324` and in a test.

### 4a — The percentage printed next to the threshold is a percentage of a different thing

`:1327` and `:1341` compute `$charitablePercent` as a percentage of the **net estate**.
`:1350` prints it in the same sentence as a **baseline** threshold. Priya is told:

> *"Reduced IHT rate of 36% applies. Your charitable giving of **1.4%** (£10,000) meets
> the **10%** threshold of £6,500 (10% of baseline £65,000)."*

1.4% does not "meet" a 10% threshold, and the sentence says so on its own face. The
comparable figure is 10,000 ÷ 65,000 = **15.4%**. The below-threshold branch (`:1361`)
carries the identical mismatch, where it is worse: a user is quoted a percentage of net
estate against a baseline threshold and cannot act on either.

**Correct:** `$charitablePercent = $baseline > 0 ? ($charitableAmount / $baseline) * 100 : 0`
for the purpose of this message, or drop the percentage and state the two cash figures.

### 4b — A reduced rate reported against a nil liability, and where that actually reaches a user

`determineIHTRate()` (`:1309`) is called at `:161`, **before** the taxable estate is
computed at `:175`. It has no access to, and no guard on, the liability. Priya:
`taxable_estate` 0, `iht_liability` 0, `iht_rate_percent` **36**.

**Where a user is actually told this** — `app/Services/Plans/EstatePlanService.php:478-481`:

```php
$appliedRateType = $qualifies ? 'charitable' : 'standard';
$appliedRateMessage = $qualifies
    ? sprintf('Reduced rate of %d%% applies as 10%% or more of the net estate is left to charity.', ...)
```

Three faults in one sentence, returned as `iht_rate_message` for display below the table:

1. **No liability guard** — it fires on Priya's £0 bill.
2. **"10% or more of the net estate" is wrong in law.** The test is 10% of the **baseline
   amount** (Sch 1A para 5). Priya left 1.4% of her net estate and qualifies; a user
   reading this sentence would conclude they need ten times what they actually need.
3. The **"10%"** is hardcoded in the format string while the rate beside it is read from
   config.

**Checked and NOT a defect, so it is not fixed unnecessarily:**
`app/Agents/EstateAgent.php:1455-1458` **is** correctly guarded on
`$ctx['iht_liability'] > 0`. For Priya it produces *"...is within the combined allowances
of £1,000,000. No Inheritance Tax is due."* Correct. Leave it.

The web estate screen never renders `iht_rate_percent` at all —
`IHTPlanning.vue:1594` maps `iht_rate` from `effective_rate / 100` (12.13% for David,
the liability-over-estate ratio, not a statutory rate). W-0132 already owns the question
of which mechanism the screen should read; I am not duplicating it.

### 4c — UNMAPPED REGIME, stated as a finding rather than resolved

Sch 1A operates on **three separate components** of the estate — survivorship property,
settled property, and the general component — each tested for the 10% condition
**independently**, with an election available to merge them. Fynla runs **one
estate-wide test**. Fynla has joint property (survivorship) and a trusts module (settled
property), so both of the components the code collapses are populated in real user data.
A single merged test can produce the wrong answer **in either direction**: it can deny
the reduced rate to an estate whose general component qualifies alone, and grant it to
an estate where no single component does.

**I could not establish how Fynla should map its data onto the three components without
a product decision.** This needs `product-lead` and a CSJ ruling before anyone codes it.
Flagging, not resolving.

---

## F5 — HIGH — Two client-side allowance builders; one reads seven keys the backend has never emitted

`resources/js/components/Estate/IHTPlanning.vue` contains **two** allowance-breakdown
builders that disagree with each other and with the backend.

**The second-death builder, `:1309-1319`**, reads `nrb_from_spouse`, `rnrb_from_spouse`,
`rnrb_tapered`, `rnrb_taper_amount`, `rnrb_eligible`, `gross_estate_value`,
`net_estate_value`. **None of those seven keys exists anywhere in `app/`** — verified by
repo-wide grep. Every one falls to its default:

```js
nrbFromSpouse: currentCalc.nrb_from_spouse || this.ihtNilRateBand,   // → always £325,000
totalNrb: (currentCalc.nrb || 325000) + (currentCalc.nrb_from_spouse || 325000),  // → always £650,000
rnrbTapered: currentCalc.rnrb_tapered || false,       // → always false
rnrbTaperAmount: currentCalc.rnrb_taper_amount || 0,  // → always £0
```

A hardcoded £650,000 nil rate band for every married user, regardless of gifts, and a
residence-band taper that can never fire.

**Currently dead, and that is exactly why the measured symptom looks the way it does.**
`secondDeathData` is assigned the `IHTController` response (`:1571`), which has no
`second_death_analysis` key. So `v-if="isMarried && secondDeathData?.second_death_analysis"`
(`:63`, `:315`) never passes, and the married user falls through the `v-else-if` at
`:336` to `standardTableProps`. That is the 325,000 + 0 against 500,000 that W-0154
measured — **not** a flat £650,000.

**Why it still needs fixing rather than ignoring.** The moment anything populates that
key, every married user sees the hardcoded £650,000.
`ComprehensiveEstatePlanService.php:94-100` **already builds a `$secondDeathAnalysis`
under that exact name**, with three keys. One wiring change away.

**Two more hardcoded tax values in the live path:** `IHTPlanning.vue:1406` sets
`rnrbTaperThreshold: 2000000` as a literal in the **standard** table too, alongside
`rnrbTapered: false` and `rnrbTaperAmount: 0` **hardcoded regardless of the calculation**
(`:1405`, `:1407`). So for an estate over £2,000,000 — the peak_earners bracket this
persona run targets — the standard breakdown tells the user the taper did not apply and
the taper amount is £0, while the backend has tapered the band away.

---

## F6 — HIGH — Rule 2: hardcoded tax values, and configured values nothing reads

### Values that are correct — verified, no defect

`inheritance_tax` in `TaxConfigurationSeeder.php:320-333`: `nil_rate_band` 325,000 ·
`residence_nil_rate_band` 175,000 · `rnrb_taper_threshold` 2,000,000 ·
`rnrb_taper_rate` 0.5 · `standard_rate` 0.40 · `reduced_rate_charity` 0.36 ·
`charity_threshold_percent` 0.10. `TaxDefaults::NRB` / `RNRB` / `IHT_RATE` /
`IHT_CHARITABLE_RATE` / `IHT_CHARITY_THRESHOLD` (`:31-53`) match, and are used only as
`??` fallbacks — acceptable per Rule 2. gov.uk confirms 325,000 / 175,000 / £2m taper.
`IHTCalculationService` reads all of them through `TaxConfigService` (`:132`, `:1194-1196`,
`:1311-1312`, `:1330`). **The core allowances are sourced correctly.**

### Hardcoded values that are defects in their own right

| File:line | Value | Config key that exists and is not read |
|---|---|---|
| `IHTCalculationService.php:1700,1706` | `subYears(7)` ×2 | `potentially_exempt_transfers.years_to_exemption` (7), `chargeable_lifetime_transfers.cumulation_period` (7) |
| `IHTCalculationService.php:1714,1715` | `subYears(14)` / `subYears(7)` | `fourteen_year_rule.maximum_window` (14) |
| `IHTCalculationService.php:1350,1361,1372` | "36%" / "40%" **in the message prose** while the rate itself is read from config | — |
| `IHTPlanning.vue:1406` | `rnrbTaperThreshold: 2000000` | `rnrb_taper_threshold` |
| `ComprehensiveEstatePlanService.php:891,895` | `?? 650000` ×2 | `nil_rate_band` |
| `GiftingController.php:185` | `$currentIHTLiability / 0.40` | `standard_rate` |
| `EstatePlanService.php:480` | `"10%"` in the format string | `charity_threshold_percent` |
| `WillAnalysisService.php:74` | `$baseline * 0.04` | `standard_rate` − `reduced_rate_charity` |
| `PersonalizedTrustStrategyService.php:378-397` | the entire taper-relief schedule as literals (100/80/60/40/20/0) | `potentially_exempt_transfers.taper_relief` |
| `GiftingStrategyOptimizer.php:268` | `'taper_relief_from_year' => 3` | same |

`WillAnalysisService.php:74` is additionally **arithmetically wrong**, not just
hardcoded: the saving from the reduced rate is (standard − reduced) × the chargeable
estate **after** the charitable deduction, less the cost of the extra gift. It is not 4%
of the baseline. This figure is shown to users as `potential_saving` /
`current_saving` (`:85-86`).

### Configured but read by nothing

- **`TaxConfigService::getTaperRelief()` and `getGiftTaxRate()` (`:360-375`) have zero
  consumers.** The seeded seven-year taper schedule
  (`TaxConfigurationSeeder.php:348-355`) is read by nothing in the application.
- **`TaxConfigService::getFourteenYearRule()`** — no consumer on the IHT path, while
  `IHTCalculationService` reimplements the 14-year window inline at `:1714`.
- **`inheritance_tax.business_relief.allowance_cap` = £2,500,000 with
  `allowance_cap_effective_date` = 2026-04-06 is never applied.**
  `EstateAssetAggregatorService.php:114-137` treats a qualifying trading business as
  binary `is_iht_exempt => true` **with no cap at all**, and the effective date passed
  four months ago. A £10,000,000 qualifying business is treated as wholly outside the
  estate; the correct treatment is 100% on the first £2,500,000 and 50% above.
  **The configured £2.5m figure is correct** — gov.uk *"Changes to agricultural property
  relief and business property relief"* (published 26 Nov 2025, last updated 3 Mar 2026,
  read 2026-08-21) confirms £2.5m from 6 April 2026 with 50% above, raised from the
  originally announced £1m by the announcement of 23 December 2025. The seeder comment at
  `:1350` is accurate. **The defect is that the calculation never reads it.**

### Two config homes for the same 10% (Rule 20)

`TaxConfigService::getCharitableThresholdPercent()` returns `0.10` (a fraction, from
`inheritance_tax.charity_threshold_percent`). `PlanConfigService::getCharitableGivingThreshold()`
(`:166-169`) returns `10.0` (a percent, from `estate.charitable_giving_threshold_percent`)
and is what `EstatePlanService.php:537` publishes to the frontend as `threshold`. **Two
sources, two units, one statutory figure.**

---

## F7 — MEDIUM — Seven-year taper relief is not applied anywhere in the calculation

The board's acceptance line asks that the seven-year taper be "verified against
`TaxConfigService` rather than assumed". The finding is stronger than that: **it is
absent.**

`IHTCalculationService` deducts gifts from the nil rate band (`:148-157`) and stops. It
never computes tax on the failed transfer itself and never applies taper relief. David's
£150,000 CLT of 2020-09-01 is 5.97 years old and would sit in the 5–6 year band
(`tax_percent` 40 per the seeded CLT schedule, `TaxConfigurationSeeder.php:385`). None of
that reaches any figure the user sees.

**I could not establish whether this is deliberate scope** (a decision to model only the
nil-rate-band consequence of gifts) **or an omission.** It needs a product ruling before
it is built.

---

## F8 — MEDIUM — The negative projected estate, and two liability figures that cannot reconcile

Sarah's `projected_net_estate` of **−£185,535.84**. A negative estate is not a
representable quantity — an estate floors at zero, and a spending shortfall is a claim
against other assets, not a negative asset.

**Root cause.** `projectCashWithInflation()` (`:486-531`) accumulates income − expenses
year by year with **no floor**, and returns the balance:

```php
// Cash can go negative — gives honest estate picture so line items sum to total
return $cashBalance;
```

**That stated rationale is then defeated by the caller.** `IHTController.php:88-92`:

```php
$calculation['projected_liabilities'] = $projectedLiabilities;   // from IHTFormattingService
$calculation['projected_net_estate'] = $calculation['projected_gross_assets'] - $projectedLiabilities;
// Let the service's projected_taxable_estate and projected_iht_liability stand
```

The displayed **projected net estate** is computed from `IHTFormattingService`'s
liabilities. The displayed **projected taxable estate** and **projected liability** were
computed inside the service (`:380-383`) from the service's **own, different** liability
figure. The two cannot reconcile by construction — so the comment's justification for
allowing negative cash ("so line items sum to total") no longer holds, and the user is
shown a projected net estate and a projected taxable estate derived from two different
liability numbers.

---

## F9 — MEDIUM — `isCharitable()` is a name-substring match, and it moves the tax rate

`app/Models/Estate/Bequest.php:87-112`. After checking `beneficiary_type === 'charity'`
and a registration number, it falls back to substring-matching `beneficiary_name` against
`['charity', 'charitable', 'foundation', 'cancer', 'heart', 'hospice', 'nspcc', 'rspca',
'oxfam', 'red cross', 'british heart', ...]`.

A bequest to an individual or company whose name contains **"foundation"** — a family
investment company, "Foundation Holdings Ltd" — is classified charitable, deducted from
the taxable estate under s23(1), and can flip the rate from 40% to 36%.

**This is the live path on every persona I read.** All four bequests (ids 50, 51, 56, 57)
have `beneficiary_type = 'individual'`, not `'charity'`, and qualify **solely** on the
name match ("Cancer Research UK", "British Heart Foundation"). Cross-references W-0020.

---

## F10 — LOW / TRIAGE — `nrb_deduction` as an array: the payload shape is not the user-facing defect

`nrb_deduction` (`:249`) is an array, and it has **no consumer**: not in `iht_summary`,
not read by any Vue component, not by `resources/mobile/`, not by `ios-native/`, not by
any test. The only hits in the repository are its own producer and the W-0154 board file.

**I could not reproduce the "Array to string conversion" and cannot say where it fired.**
Given there is no consumer, the most likely origin is the evidence-gathering harness
interpolating the `calculation` array, not a user-facing render. I would not carry it as
a display defect on that evidence.

**The real defect underneath it is F2:** the deduction is applied, is worth £150,000, and
has **no scalar field** in the payload the UI reads. It needs one.

---

## F11 — LOW / TRIAGE — The deprecation: I could not place it on the estate path

`getCompleteProfile()` has exactly **five** callers: `UserProfileController.php:78`,
`AdviserExportPackService.php:47`, `DisposableIncomeAccessor.php:32`,
`RequiredCapitalCalculator.php:158`, `RetirementStrategyService.php:423`. **None is on
the Inheritance Tax path.** `IHTController::calculateIHT` → `IHTCalculationService::calculate`
does not reach it.

`storage/logs/laravel.log` (25MB, last written 19:33 today) contains **no** "array offset"
entry — **but that proves nothing**: `config/logging.php:35` sets
`'deprecations' => ['channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null')]`, so
deprecations are discarded by default. Running PHP 8.5.2.

**I could not establish the estate-path route to this deprecation and did not reproduce
it.** Recommend it stays with `F-0001` rather than being folded into W-0154, and that
whoever picks it up sets `LOG_DEPRECATIONS_CHANNEL` first.

---

## Surfaces (Rules 19 and 20)

**The wrong breakdown is web-only today. The wrong liability is not.**

- **`/m`** — `resources/mobile/views/modules/Estate.vue` renders **no** nil-rate-band,
  allowance, rate or IHT fields (grep for `nrb|allowance|iht|rate` returns nothing).
- **iOS** — `ios-native/Fynla/Features/Estate/EstateView.swift` likewise renders none.
- **But every surface reads the same service**, so wherever `iht_liability` is displayed,
  F1's £60,000 household asymmetry travels with it.

**Four resolutions of "who is the spouse and may we use their data" (Rule 20):**

| Caller | Spouse | Data sharing |
|---|---|---|
| `IHTController.php:51-53` | `liveSpouse()` | `hasAcceptedSpousePermission()` |
| `EstatePlanService.php:384-386` | `liveSpouse()` | `hasAcceptedSpousePermission()` |
| `ComprehensiveEstatePlanService.php:71-72` | `User::find($user->spouse_id)` | `hasAcceptedSpousePermission()` |
| `TrustController.php:201-202` | `$spouse` | `hasAcceptedSpousePermission()` |
| **`EstateAgent.php:127-128` and `:1556-1557`** | **`$user->spouse`** | **`$spouse !== null` — no permission check** |

`EstateAgent` pools a spouse's financial data into the calculation **without the consent
gate every other caller applies**. Consent impact is limited today by the auto-true in
`hasAcceptedSpousePermission()` (F3), but the divergence is real and it means Fyn can
report a different Inheritance Tax liability from the Estate screen for the same user.

---

## Two notes for whoever fixes W-0131, so the fixes do not collide

The calculation cache is dead today (W-0131: `persist` is never passed true). Two latent
defects will activate the moment it is switched on:

1. **The cache can never hit for some users.** `getCachedCalculation()` filters
   `->where('is_married', $spouse !== null)` (`:1496`), while `saveCalculation()` writes
   `'is_married' => $result['is_married']` (`:1625`), which is
   `marital_status === 'married' && $spouse !== null` (`:99`). For any user with a linked
   spouse whose `marital_status` is not `'married'`, the lookup asks for `true` and the
   row holds `false`.
2. **A spouse's will change will not invalidate the cache.**
   `charitableBequestFingerprint($user)` (`:1535-1552`) reads the logged-in user's will
   only — the same asymmetry as F1. With the pooled charitable deduction fixed per F1,
   the fingerprint must become household-wide or the cache will serve a stale rate.

---

## What I could not determine

Stated plainly, as complete findings:

1. ~~The estate-path route to the `BelongsTo.php:187` deprecation.~~ **SETTLED 2026-08-21
   during W-0136 — reproduced live.** It is **not** `getCompleteProfile()`. The cause is
   `->with('jointOwner')` where `joint_owner_id` is NULL (`PropertyStore.php:57`,
   `MortgageStore.php:67`, `SavingsStore.php:67`, `InvestmentAccountStore.php:62`), which
   makes `BelongsTo::match()` evaluate `isset($dictionary[null])`. Framework-level; fix is
   a Laravel upgrade or suppression, not an app change per store. See W-0136 notes. (F11)
2. **Where the "Array to string conversion" fired.** `nrb_deduction` has no consumer, so
   I could not locate a render that would produce it. (F10)
3. **Whether the single-component charitable test is a deliberate simplification.**
   Sch 1A's three-component structure is unmapped in Fynla. Needs a product decision
   before anyone codes it. (F4c)
4. ~~Whether the absence of seven-year taper relief is deliberate scope.~~ **SETTLED
   2026-08-21 during W-0136 — it is an OMISSION, not scope.**
   `fynlaBrain/Architecture/v083/08-FINANCIAL-CALCULATIONS.md:607-615` specifies the full
   schedule and `:101` documents `getGiftTaxRate()` as its accessor; the accessor exists,
   is documented, and has zero callers. **Re-scope F7 from medium to low/bounded:** taper
   relief reduces tax on the failed transfer only where it exceeds the available band, so
   David's £150,000 inside his £325,000 costs this household nothing. Bites only where one
   donor's seven-year transfers exceed £325,000. (F7)
5. **The tax year to which the nil rate band freeze now runs.** The gov.uk page I fetched
   (published 30 Oct 2024) says end of 2029-30; a later gov.uk page refers to 2030-31;
   `TaxConfigurationSeeder.php:324` comments "April 2031". **This changes no figure
   today** — £325,000, £175,000 and the £2,000,000 taper are confirmed correct — so I did
   not pursue it. It is a comment-accuracy item, not a calculation defect.
6. **I did not run `IHTCalculationService::calculate()`.** Four pest runs were live and
   the personas are being driven in a browser. Every figure here is hand-computed from
   read-only queries and reconciles exactly with the measured evidence.

## Note on my own reference table

The `tax-compliance-reviewer` agent definition carries a 2025/26 reference table stating
the nil rate band is *"frozen until April 2028"*. Per that document's own instruction —
trust `TaxConfigService`, flag the doc as stale — **it is stale.** Both `TaxConfigService`
and gov.uk put the freeze well beyond 2028. Nothing in this report relies on that table;
every figure was checked against the seeded config or a dated source.
