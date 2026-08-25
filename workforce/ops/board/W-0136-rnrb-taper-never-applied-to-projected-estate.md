---
id: W-0136
title: The residence nil rate band taper is never applied to the projected estate, and the footnote asserts the estate is below the taper threshold directly beneath a column showing £2.34m
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0015-cycle1-estate-tax-figures.md
owner: build-lead
status: gated
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T20:20:00Z
claimed: 2026-08-21T19:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [W-0154, W-0135]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, local, both persona accounts.

**Surface:** `/estate/inheritance-tax` and `/plans/estate`, the "AGE 84 / Life
expectancy" column. Routed to `tax-compliance-reviewer` because it is a statutory
allowance being mis-stated, not only a display fault.

### Expected

The residence nil rate band tapers by £1 for every £2 of estate above £2,000,000
(`TaxConfigService` — taper threshold £2,000,000, rate £1 per £2, confirmed live
2026-08-21). Against David's projected estate:

```
projected net estate                       2,343,680
excess over 2,000,000                        343,680
taper at £1 per £2                           171,840
residence nil rate band 350,000 − 171,840    178,160
expected projected allowances 500,000 + 178,160   678,160
```

For Sarah, projected net estate £2,431,937 → excess £431,937 → taper £215,969 →
residence nil rate band £134,031.

### Actual

Both accounts show the **current-year allowance, untapered, in the projected column**:

| | Age 84 net estate | Allowances shown, age 84 | Expected |
|---|---|---|---|
| David | £2,343,680 | **−£850,000** | −£678,160 |
| Sarah | £2,431,937 | **−£1,000,000** | −£784,031 |

And the footnote beneath, unchanged between the two columns:

> "Full Residence Nil Rate Band of £350,000 available (£175,000 each). **Your combined
> estate is below the £2,000,000 taper threshold.**"

That sentence is true of the "NOW" column (£1,234,280) and false of the column beside
it. `rnrb_status` is `"full"` and `rnrb_available` £350,000 in the response — the
projection reuses the current-year determination rather than re-testing it against the
projected estate.

### Impact

The taper is the mechanism that removes the residence nil rate band from exactly the
households this persona represents, and the projection is where a user would see it
coming. Under-stating tax by £68,736 (David: £171,840 of lost allowance at 40%) on the
figure a user plans a decade of gifting and life cover around is a material
understatement, and the footnote actively reassures them it does not apply.

This compounds W-0135: the projection's figures already do not reconcile, and the taper
is one of the adjustments that should be moving them.

### Repro

1. `david.jones@example.com`, premium, married. `/estate/inheritance-tax`, wait ~12s,
   **Expand All**.
2. Read "Net Estate" in the age-84 column: £2,343,680.
3. Read "Less: Tax-Free Allowances" in the same column: −£850,000, identical to the NOW
   column.
4. Read the Home Allowance footnote: asserts the estate is below £2,000,000.
5. Repeat as `sarah.jones@example.com`: £2,431,937 against −£1,000,000.

### Acceptance

1. The residence nil rate band is re-tested against the **projected** estate and tapered
   where it exceeds the threshold, with the threshold and rate read from
   `TaxConfigService` — no hardcoded £2,000,000 (Rule 2).
2. The projected column's allowance figure differs from the current column's whenever
   the taper bites, and the taper appears as its own row or note.
3. The footnote is scoped to the column it describes, or states both positions.
4. `tax-compliance-reviewer` confirms the taper arithmetic and that a fully tapered
   residence nil rate band (estate ≥ £2,700,000) reaches zero rather than going negative.
5. Verified in a browser on both persona accounts, hand-checked.

## Working notes

- 2026-08-21 tax-compliance-reviewer: **claimed and reviewed. Report-only; no code changed,
  no database writes.** `IHTCalculationService.php` last modified **13:15:42**, before I
  started — **it did not move under me.** Line numbers below are pinned as at that state.

- 2026-08-21 tax-compliance-reviewer: **ARITHMETIC CONFIRMED — all four figures in the
  Expected block are right.** David: 2,343,680 − 2,000,000 = 343,680 · ×0.5 = **171,840** ·
  350,000 − 171,840 = **£178,160** · allowances 500,000 + 178,160 = **£678,160** ·
  understatement at 40% = **£68,736**. Sarah: excess 431,937 · taper **£215,968.50** ·
  residence band **£134,031.50**. The threshold and rate are read from config
  (`rnrb_taper_threshold` 2,000,000, `rnrb_taper_rate` 0.5) — no hardcoding in the service.

- 2026-08-21 tax-compliance-reviewer: **acceptance criterion 4 answered — the zero floor
  already exists and must be preserved, not added.** `IHTCalculationService.php:1275`:
  `$rnrbAvailable = max(0, $fullRNRB - $reduction);`, with an explicit fully-tapered branch
  below it returning `rnrb_available => 0`. **The band reaches zero at an estate of
  £2,700,000** (reduction 350,000 requires excess 700,000). It cannot go negative today.
  **The risk in this fix is removing that floor, not lacking it.**

- 2026-08-21 tax-compliance-reviewer: **ROOT CAUSE — the taper is INDEPENDENTLY ABSENT from
  the projection. It is not W-0137 fallout. That was the question asked and this is the
  answer.** `calculateRNRB()` is called **exactly once**, at `IHTCalculationService.php:161`,
  with **`$totalNetEstate`** — the *current* estate. Its result is passed into
  `calculateProjectedValues()` at `:185` and reused verbatim at **`:383`**:
  `$totalAllowances = $nrbAvailable + $rnrbData['rnrb_available'];`
  The `if ($totalNetEstate <= $taperThreshold)` test at `:1251` therefore runs once, against
  £1,234,280, and its answer is carried into a £2.34m column. **Even with a perfectly
  correct projected estate the taper would still not fire.** `IHTFormattingService` contains
  no taper logic, so nothing downstream re-tests it either. **The two items need separate
  fixes and only this one is mine.**

- 2026-08-21 tax-compliance-reviewer: **W-0137 makes this WORSE, not better — £68,736 is a
  floor, not a ceiling.** W-0137's negative cash *reduces* the projected estate, so the true
  projected estate is **higher** than £2,343,680, and a higher estate tapers **more**.
  Directionally, with household cash of −£1.8m floored at zero the projected estate lands
  near £4.1m, excess ~£2.1m, taper ~£1.07m — **which exceeds £350,000, so the residence band
  would taper away entirely.** That is roughly **£350,000 of lost allowance and £140,000 of
  tax, not £68,736.** **Stated as direction and order of magnitude, not as a measured
  figure** — it is contingent on W-0137's numbers, which are themselves the defect. **Do not
  quote £68,736 as the impact once W-0137 lands; re-measure it.**

- 2026-08-21 tax-compliance-reviewer: **⚠️ CORRECTION TO THE EXPECTED BLOCK — Sarah's
  £784,031 embeds a different bug and must not be used to verify this fix.** That figure is
  650,000 + 134,031.50, and **Sarah's £650,000 nil rate band is itself wrong** (W-0154 F1:
  David's £150,000 chargeable lifetime transfer never reaches her calculation; her correct
  band is £500,000). **Sarah's correct projected allowance once F1 is fixed is
  500,000 + 134,031.50 = £634,031.50.** Hand-checking acceptance criterion 5 against
  £784,031 would sign off W-0154's defect as correct. **David's £678,160 is safe** — his
  £500,000 is already the right band.

- 2026-08-21 tax-compliance-reviewer: **NEW — the projected ESTATE is itself asymmetric, so
  fixing the allowances alone will not make one household give one answer.** David's
  projection is £2,343,680 and Sarah's is £2,431,937 — **£88,257 apart on an identical
  household**, and the nil rate band cannot explain it because allowances do not change the
  estate. The cause is the same family as W-0154 F1: **the projection is anchored on the
  logged-in user's age.** `$currentAge` comes from `$user->date_of_birth`, and
  `projectCashWithInflation()` loops `for ($age = $currentAge; $age < $deathAge; $age++)`.
  David is 49 (born 1976-11-08), Sarah 48 (born 1978-04-22), and the death age is shared
  (the longer life expectancy), so **Sarah's loop runs one more year of inflated surplus
  than David's on the same household.** `getRetirementAge($user)` is also user-only, so the
  pre-/post-retirement income switch happens at the logged-in user's retirement age for the
  whole household. **This belongs with `fix-batch-G`'s F1 mechanism, not with the taper.**

- 2026-08-21 tax-compliance-reviewer: **the taper is one instance of a general defect —
  fixing only the residence band leaves two more.** **Every rate and allowance test is
  evaluated once against the current estate and reused for the projection.**
  (a) **The charitable 10% test.** `determineIHTRate()` runs at `:164` against
  `$totalNetEstate`, and `$ihtRate` is passed into the projection at `:189` unchanged. At a
  projected £2.34m estate the baseline is roughly £1.8m and the 10% threshold roughly
  £180,000, which a £10,000 legacy cannot meet — **so the projection can report a reduced
  36% rate the projected estate would not qualify for.**
  (b) **The 2027 pension amendment scenario**, `:1791`:
  `$totalAllowances = $baseCalc['total_allowances'] ?? 0;` — adding unused defined
  contribution pots to the estate never re-tests the £2,000,000 taper, **and pension
  inclusion is precisely what pushes estates over that threshold.** *Checked on this
  household and it does not bite here:* combined defined contribution pots are £500,000
  (David £500,000, Sarah £0), giving 1,234,280 + 500,000 = **£1,734,280, still under £2m.**
  It bites any household whose current estate plus pots exceeds £2,000,000.

- 2026-08-21 tax-compliance-reviewer: **the residence band taper has ZERO test coverage.**
  `tests/Unit/Services/Estate/IHTRnrbAndCharitableExemptionTest.php` has eight tests —
  eligibility, the residence-value cap, and the charitable exemption — and **none tests the
  taper or the projection.** The only `'tapered'` assertion in the whole suite is in
  `tests/Feature/RetirementIntegrationTest.php:301`, which is the pension annual allowance
  taper and unrelated. **This fix needs new tests, not amended ones**, and they should cover
  the £2,700,000 full-taper boundary and the zero floor.

- 2026-08-21 tax-compliance-reviewer: **a SECOND independent taper implementation exists,
  with two Rule 2 defects of its own (Rule 20).**
  `app/Services/Coordination/HouseholdPlanningService.php:921-924` re-implements the taper:
  `$taperReduction = ($netEstate - $taperThreshold) / 2;`
  (a) **The divisor `/ 2` is hardcoded** rather than reading `rnrb_taper_rate` (0.5).
  (b) Worse, `:911` reads `$ihtRate = (float) ($ihtConfig['rate'] ?? 0.40);` — **there is no
  `inheritance_tax.rate` key.** Verified live: `TaxConfigService->get('inheritance_tax.rate')`
  returns **NULL** while `standard_rate` returns `0.4`. **So this path always uses the
  hardcoded 0.40 and a configured rate change can never reach it** — including anything an
  administrator sets in Tax Settings. **Consolidate the taper into one home as part of this
  fix rather than aligning two copies (Rule 20).**

- 2026-08-21 tax-compliance-reviewer: **F7 SETTLED — it is an omission, not deliberate
  scope. No CSJ decision needed; closing the open question.** The architecture doc
  `fynlaBrain/Architecture/v083/08-FINANCIAL-CALCULATIONS.md:607-615` **specifies** PET taper
  relief with the full schedule (3–4y 32%, 4–5y 24%, 5–6y 16%, 6–7y 8%, 7+ 0%), and `:101`
  documents `getGiftTaxRate($yearsSurvived, $type)` as the accessor for it. **The accessor
  exists, is documented, and has zero callers.** A deliberate scope decision would not ship
  a documented accessor nothing calls.
  **But the impact is narrower than F7's "medium" implied, and this bounds it.** Taper
  relief reduces the tax **on the failed transfer itself**, and only where that transfer
  **exceeds the available nil rate band**. David's £150,000 chargeable lifetime transfer sits
  within his £325,000 band, so there is **no tax on it to taper — the omission costs this
  household nothing today.** It bites only where a single donor's seven-year transfers
  exceed £325,000. **Recommend re-scoping F7 from "medium, needs a product decision" to
  "low, bounded, fix when the gift engine is next opened."**

- 2026-08-21 tax-compliance-reviewer: **note for the record — two paths in the dispatch do
  not exist.** `fynlaBrain/v083/08-FINANCIAL-CALCS.md` and `fynlaBrain/UKTaxes.md` are both
  absent. The live files are `fynlaBrain/Architecture/v083/08-FINANCIAL-CALCULATIONS.md` and
  `fynlaBrain/Current State/UKTaxes.md`. CLAUDE.md's vault reference table points at the
  stale paths.

- 2026-08-21 tax-compliance-reviewer: **the second question answered — YES, the projected
  estate still crosses £2,000,000 once the income model is fixed, and by roughly double.
  The missing income SUPPRESSES the estate; it does not inflate it.**
  W-0137's −£1.8m of cash is **subtracted** from the projected estate, so £2,343,680 is an
  understatement of the true projection, not an artefact inflating it.
  Grounded in composition (read-only, 2026-08-21 ~20:09 — **the tester is writing to users
  16/17, so these drift and the current total does not match the £1,234,280 in W-0154**):

  | | Gross | Liabilities | Property |
  |---|---|---|---|
  | David (16) | 985,000 | 182,500 | 755,500 |
  | Sarah (17) | 861,780 | 122,500 | 637,500 |
  | **Household** | **1,846,780** | **305,000** | **1,393,000 (75% of gross)** |

  Property compounds at 3% (`IHTCalculationService::DEFAULT_PROPERTY_GROWTH_RATE`) over
  ~35 years (age 49 → 84) = **×2.814** → **£3.92m of property alone**, against liabilities
  that amortise toward zero by retirement. **The projection crosses £2,000,000 on property
  growth alone, before any cash, investment or chattel value.** With cash repaired the
  estate lands near **£4.3m — past £2,700,000, where the residence band tapers to ZERO.**
  **Consequence for severity: this is a live defect on real households and should go UP,
  not down. £68,736 is a floor; realistic exposure once W-0137 lands is the full £350,000
  of allowance, about £140,000 of tax.** Re-measure rather than re-quoting £68,736.

- 2026-08-21 tax-compliance-reviewer: **files moved under me — recorded per instruction.**
  `IHTCalculationService.php` **has NOT moved** (13:15:42, before this audit began), so
  `fix-batch-G`'s edits were not in the file at the time of these findings and all line
  numbers above are valid against that state.
  `HouseholdPlanningService.php` (20:08:19) and `EstateAssetAggregatorService.php`
  (20:08:43) **did** move, seconds before I re-checked. **Both of my findings in them
  survive that edit**, and one is worse than I first reported: `$ihtConfig['rate'] ?? 0.40`
  appears at **two** sites, `HouseholdPlanningService.php:278` **and** `:968` — and
  `inheritance_tax.rate` is **NULL** (verified live against `TaxConfigService`, while
  `standard_rate` returns `0.4`), so **both always use the hardcoded 0.40 and no configured
  rate — including anything set in admin Tax Settings — can reach either.** `/2` remains
  hardcoded at `:980`. `EstateAssetAggregatorService:116-124,137` still applies Business
  Property Relief as binary 100% `is_iht_exempt` with **no £2,500,000 cap**.

- 2026-08-21 tax-compliance-reviewer: **F11 from the audit is now SETTLED, and W-0154's
  attribution of it is WRONG — this closes my open question 1.**
  Running `EstateAssetAggregatorService::gatherUserAssets()` read-only **reproduced it
  live**: `Using null as an array offset is deprecated, use an empty string instead in
  vendor/laravel/framework/.../BelongsTo.php on line 187`.
  **It is not `getCompleteProfile()` on the estate path.** The cause is
  **`->with('jointOwner')` where `joint_owner_id` is NULL**, at four sites:
  `PropertyStore.php:57`, `MortgageStore.php:67`, `SavingsStore.php:67`,
  `InvestmentAccountStore.php:62`. `BelongsTo::match()` evaluates
  `isset($dictionary[null])`, which PHP 8.5 deprecates. It fires for **any user holding an
  individually-owned record** — i.e. nearly every user, on every estate call.
  **This is framework-level, so the fix is a Laravel upgrade or suppression, NOT an app
  change per store.** Whoever picks it up must set `LOG_DEPRECATIONS_CHANNEL` first —
  `config/logging.php:35` defaults it to `null` and discards deprecations, which is why the
  25MB `laravel.log` showed nothing.

---

## ⚠️ FIGURES SUPERSEDED — 2026-08-21, tax-compliance-reviewer

**The Expected/Actual blocks above were measured on a household that was only about
one-third entered.** `persona-passA3` has since completed the data entry and re-measured.
**Use the figures in this section. The £68,736 above is stale and must not be quoted.**

| | Current | Projected |
|---|---|---|
| Net estate | £1,716,780 | £4,368,401 |
| Allowances shown | −£850,000 | **−£850,000 (unchanged — the defect)** |
| Taxable estate | £846,780 | £3,467,510 |
| Tax shown | £338,712 | **£1,387,004** |

**Correct projected position.** Excess over the threshold 4,368,401 − 2,000,000 =
**2,368,401** · taper at £1 per £2 = **£1,184,200** · **that exceeds the entire £350,000
residence nil rate band, so the band is EXTINGUISHED, not reduced.** Correct projected
allowances = nil rate band £500,000 + residence band **£0** = **£500,000**. Correct
taxable = 4,368,401 − 500,000 − 20,000 = 3,848,401 · **correct tax £1,539,360**.

### **Understatement: £152,356** (not £68,736)

**All of passA3's arithmetic checks — I have verified every line of it.** And the
footnote beneath a £4,368,401 column still reads *"Your combined estate is below the
£2,000,000 taper threshold."*

**This is now the largest single error on the page.**

- 2026-08-21 tax-compliance-reviewer: **passA3's measurement independently CONFIRMS the
  charitable-scaling bug I had only inferred, and it is now measured rather than
  predicted.** The observed projected taxable estate of **£3,467,510** is not
  4,368,401 − 850,000 = 3,518,401. It is **£50,891 lower**, and £50,891 is exactly
  `20,000 ÷ 1,716,780 × 4,368,401` — the household's **fixed £20,000 of cash legacies
  inflated to £50,891** by the proportional scaling in `calculateProjectedValues()`
  (`$projectedCharitableAmount = $projectedNetEstate * $charitableFraction`). A
  `specific_amount` legacy does not grow with the estate; only a `percentage` one does.

- 2026-08-21 tax-compliance-reviewer: **⚠️ FIXING THE TAPER ALONE LANDS £12,356 SHORT —
  this is the single most useful number in this item for whoever builds it.**
  passA3's correct figure of £1,539,360 quietly uses the **true £20,000** charitable
  deduction, not the £50,891 the code actually produces. So it assumes the scaling bug is
  fixed too. If only the taper is fixed:

  | Fix applied | Projected taxable | Projected tax | vs correct |
  |---|---|---|---|
  | Neither (today) | 3,467,510 | £1,387,004 | −£152,356 |
  | **Taper only** | 3,817,510 | **£1,527,004** | **−£12,356** |
  | Taper + charitable scaling | 3,848,401 | **£1,539,360** | **correct** |

  **Both fixes are needed to reach £1,539,360.** A build that stops at the taper will
  browser-verify against £1,527,004, look right next to £1,387,004, and still be wrong.

- 2026-08-21 tax-compliance-reviewer: **checked, and NOT a defect on this household — the
  frozen charitable rate does not bite here.** Current baseline 1,716,780 − 500,000 =
  1,216,780 → 10% threshold £121,678. Projected baseline 4,368,401 − 500,000 = 3,868,401 →
  threshold £386,840. The household's £20,000 is below both, so **40% is correct in both
  columns** and the frozen-rate defect I raised has no effect here. It remains live for a
  household that qualifies today and would not at death.

- 2026-08-21 tax-compliance-reviewer: **file movement — `IHTCalculationService.php` HAS now
  moved, at 20:16:23.** `fix-batch-G` landed the F1–F3 fix. **Every line number I cited in
  the notes above was pinned to the 13:15:42 state and is now stale** — re-grep before
  using them. The findings themselves are unaffected; only the coordinates are.
  *Noted for the record:* the `Undefined variable $isMarried` and halved residence band
  seen at 20:14:31 were a half-applied edit caught mid-write, not a defect — team-lead
  confirmed, and it matches this mtime.

---

## ⚠️ FRAMING CORRECTED — the taper is NOT missing. The second call is.

**2026-08-21, tax-compliance-reviewer, correcting my own earlier wording.** I labelled this
*"the taper is INDEPENDENTLY ABSENT"*. That was me answering a binary — absent from the
projection, versus fed a fiction by W-0137 — and it reads as *"the arithmetic does not
exist"*, which sends a builder to write taper code that is already written. **The mechanism
description underneath it was right; the headline overrode it. The label was wrong.**

**The taper arithmetic exists and is correct.** Verified at the post-`fix-batch-G` state
(file moved 20:16:23 — these coordinates are fresh, the ones earlier in this item are not):

| | Line |
|---|---|
| `calculateRNRB()` | `:1255` |
| Taper test — `if ($totalNetEstate <= $taperThreshold)` | `:1326` |
| Reduction — `$reduction = $excess * $taperRate;` | `:1349` |
| `tapered` branch | `:1357` |
| "fully tapered away" branch | `:1367` |
| **Called — exactly once, with the CURRENT estate** | **`:214`** |
| Result reused, current column | `:236` |
| **Result reused verbatim, PROJECTED column** | **`:453`** |

### The fix is one extra call, not new arithmetic

**Call `calculateRNRB()` a second time with the projected net estate at/near `:453`.**
That is the whole change. Everything else — the £1-per-£2 rate from config, the zero floor
at `max(0, ...)`, the fully-tapered branch, the message text — already exists and works.

**⚠️ One wrinkle for that second call, so it is not found later.** `calculateRNRB()` also
applies the IHTA 1984 s8E(2) residence cap, `min($fullRNRB, $residenceNetValue)`, computed
from **current** property values. On a projected call the residence has grown by roughly the
same ~2.8× as the rest of the estate, so **the cap must be fed the projected residence value
or the projection will under-cap the band.** *It does not bite this household* — the joint
main residence is £850,000, far above the £350,000 maximum — but a household with a modest
home would get a wrong projected band out of an otherwise-correct fix.

- 2026-08-21 cycle1-estate (build-lead): **FIXED, measured read-only against the live
  household, handed to quality-lead. Branch document `F-0015`. Not browser-verified —
  that is Quality's loop, not mine.**

  **Confirmed as framed: the arithmetic existed and was never asked the question.**
  `calculateProjectedValues()` received `$rnrbData` — an answer already computed from
  the CURRENT estate — and reused it verbatim. The fix is a second call, not new tax
  logic, exactly as `persona-passA3` predicted.

  **But fixing only the taper lands on a plausible wrong answer**, so three tests that
  were all evaluated once against today's estate are now all evaluated against the
  estate they apply to, through one mechanism: `assessTaxPosition(float $netEstate,
  float $residenceNetValue, array $ctx)` at `IHTCalculationService.php:561`, called at
  `:241` with today's estate and `:506` with the projection. The others were the 10%
  charitable rate test and the charitable exemption itself (see the scaling note on
  W-0154).

  **The residence-cap trap fired as warned.** `calculateRNRB()` derived
  `$residenceNetValue` internally from current property records, so a naive second call
  would cap a projected band at today's house price. It is now a **required argument**
  (`:1448-1457`) supplied by `projectMainResidenceNetValue()` (`:614`), which grows the
  ownership share through `FutureValueCalculator` and amortises the mortgage share
  through the same `projectSingleLiability()` the rest of the projection uses. It does
  not bite `peak_earners` (residence £850,000, well above the band); it bites every
  household with a modest home. Pinned.

  **Measured, David (16), read-only through `liveSpouse()` / `hasAcceptedSpousePermission()`:**
  projected net estate £4,368,400.76 · nil rate band £500,000 · **residence band £0.00
  (was £350,000)** · charitable £20,000 · taxable £3,848,400.76 · **tax £1,539,360.30**.
  That is the tester's hand-computed £1,539,360. The current column is untouched at
  £338,712 on both accounts.

  The message now reads *"Residence Nil Rate Band fully tapered away. Your estate of
  £4,368,401 exceeds the taper threshold of £2,000,000 by £2,368,401, eliminating all
  RNRB of £350,000"*, and both surfaces render it beside the projected column instead
  of repeating the current one.

  **Tests are written so they cannot pass against a literal**, per the instruction and
  per the `'rate' => 0.40` mock that stayed green over an unreachable tax rate: lifting
  the configured `rnrb_taper_threshold` above the projected estate must restore the
  full band and lower the tax; softening `rnrb_taper_rate` must leave more band
  standing. 17 tests in `tests/Unit/Services/Estate/IHTProjectedAssessmentTest.php`.

  **⚠️ Clear the cache before re-measuring.** `EstateAgent::analyze()` caches its whole
  result, and the first `/plans/estate` read after this change served the PRE-FIX
  figures. `php artisan cache:clear`. Anyone who skips this will read £1,387,004 and
  conclude the fix failed.
