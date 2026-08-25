---
id: F-0015
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/05-perimeter.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-21T20:44:00Z
status: active
---

# F-0015 — Cycle 1: the estate tax figures, W-0136 / W-0134 / W-0135

**Owner:** build-lead (agent `cycle1-estate`) · **Branch:** `dev` · **Board items:**
W-0136, W-0134, W-0135 (all `high`), continuing W-0154's "NOT done" list.

**Status: all four defects code-complete and measured. 30 new tests (17 backend,
10 frontend, 3 in the existing persist suite) plus 1,046 regression tests green.
Pint and ESLint clean.** No commit, no PR, no deploy, and not browser-verified —
Quality closes that loop. Nothing in flight.

---

## 1. The single mechanism behind three of the four

W-0136, the charitable scaling defect, and half of W-0135 were **one defect wearing
three faces: every rate and allowance test was evaluated once, against the CURRENT
estate, and its answer was carried into a projection two and a half times larger.**

`calculateProjectedValues()` received `$nrbAvailable`, `$rnrbData`, `$ihtRate` and
`$charitableFraction` — four already-decided answers — and applied them to a
different estate:

- **The £2,000,000 residence-band taper never fired on a projection, however large.**
  `calculateRNRB()` had both a partial-taper branch and a fully-tapered-away branch
  with correct £1-per-£2 arithmetic. It was called **once**, with the current estate.
  The projected column reused the result and printed the footnote from the untapered
  branch — *"Your combined estate is below the £2,000,000 taper threshold"* — beside a
  column showing £4,368,401.
- **The 10% charitable rate test was decided against today's estate**, so a household
  on 36% carried that rate to a death whose baseline it could not qualify against.
- **A fixed cash legacy was inflated in proportion to the estate.** `$charitableFraction`
  was `charitable ÷ current net estate`, re-applied to the projection: the household's
  fixed £20,000 of `specific_amount` bequests became **£50,891**.

**The fix is one assessment, run twice with two estates.** `assessTaxPosition(float
$netEstate, float $residenceNetValue, array $ctx)` — `IHTCalculationService.php:561`
— calls `calculateRNRB()` and `determineIHTRate()` and returns the allowances, the
exemption, the taxable estate and the tax. `calculate()` calls it with today's estate
(`:241`); `calculateProjectedValues()` calls it with the projected one (`:506`).
`$charitableFraction` is deleted outright — it was the scaling defect's only carrier.

**Re-asking beats re-scaling, and that is the whole point.**
`WillAnalysisService::getCharitableBequestTotal($member, $netEstate)` already knows
the difference between a `percentage` bequest and a `specific_amount` one
(`WillAnalysisService.php:154-158`). The projection was approximating an answer the
codebase could give exactly. It now asks.

### The trap inside the second call

`calculateRNRB()` also applies the residence cap of IHTA 1984 s8E(2),
`min($fullRNRB, $residenceNetValue)`, and it derived that value internally from
**current** property records. A second call would therefore have capped the projected
band at a house price decades out of date.

`$residenceNetValue` is now a **required argument** rather than something the method
looks up (`:1448-1457`), so no caller can forget which estate it belongs to.
`projectMainResidenceNetValue()` (`:614`) supplies it, growing the ownership-share
value through `FutureValueCalculator` and amortising the mortgage share through the
same `projectSingleLiability()` the rest of the projection uses — so the residence
cannot be worth one thing here and another in `projectProperties()`.

**It does not bite the `peak_earners` household** (joint main residence £850,000,
well above the £350,000 band) and it bites every household with a modest home. Pinned
by `it caps the projected band at the projected residence value, not today's`.

---

## 2. W-0134 — the column that did not survive addition

Four rows summing to £1,000,000 above a printed subtotal of £850,000, with the
charitable deduction applied and shown nowhere.

**The £150,000 row already existed in the template.** `fix-batch-G` added it
(`IHTCalculationTable.vue`, F2) and `standardTableProps` passed `nrbGiftDeduction`
correctly. It never rendered because the intermediate mapping in
`IHTPlanning.vue:loadIHTCalculation()` copied `iht_summary.current` field by field and
**omitted `nrb_spouse_modelled` and `nrb_gift_deduction`**, so the prop was fed
`undefined || 0` and the `v-if` was false. A row present in the markup, published by
the server, and invisible on the page — because of an unrelated hand-written mapping
in between. Both fields are now carried (`IHTPlanning.vue:1635-1636`).

**Two blocks, not one, because the law has two.** The charitable exemption is NOT an
allowance: IHTA 1984 s23 removes the legacy from the estate's transferable value.
Folding it into the allowance subtotal would make the column add up **while
misstating the law**. It now renders as its own row beneath the allowance block, and
**independently of the `charitableBequest` what-if toggle** it used to be gated
behind. That gate is why a deduction the server had already applied was invisible on
the page that applied it.

**The £325,000 spouse row is labelled as what it is.** There is no transferable nil
rate band while both spouses are alive — the claim arises on the survivor's death
(IHTA 1984 s8A) — so the row carries `Modelled on second death — there is no
transferable allowance while you are both alive.` `nrb_transferred` stays 0. Writing
325,000 into it would make the column add up and the payload wrong.

**Fourteen hand-written rows became one row model.** `nilRateBandRows()` and
`residenceBandRows()` build every itemised row with its own value per column;
`allowanceRows()` concatenates them. Both the default and the what-if branch render
from it, so the what-if view cannot drift again — it had already drifted, reading the
nil rate band from the Vuex tax config rather than the payload and carrying no gift
row at all.

**Two reconciliation holes closed on the way**, both pre-existing and both invisible
while the gift row was:
- The single and widowed branches printed `totalNrb` — **already net of the gift
  deduction** — and then rendered the deduction again beneath it. Every row now shows
  the gross individual band, so `gross + spouse/transferred − gifts = totalNrb` holds
  in all four marital branches.
- The widowed branch printed gross residence components that exceed what is available
  once the cap or taper bites. The difference now gets its own named row rather than
  leaving a reader with a subtotal they cannot reach.

**Per-column allowances were not optional.** Once the projected residence band is
genuinely a different number, a single figure printed beside both columns is wrong in
at least one of them. `allowancesProjected` is a new prop defaulting to `allowances`,
so a caller that has not been updated behaves exactly as before.

---

## 3. W-0135 — two screens, and the £103,206 that is not this defect

**Root cause, `EstatePlanService.php:423` as it stands (the deleted block sat at `:423-426`):** the service **recomputed**
`projected_taxable_estate` and `projected_iht_liability` itself — projected net estate
minus the CURRENT allowances at the CURRENT rate, **with the charitable exemption
omitted entirely**. `IHTController` has always let the service's values stand
(`IHTController.php:95`). One question, two mechanisms, two answers.

The recompute is deleted. Both surfaces now publish the identical projected block,
and both carry the projected allowance components and the exemption.

**A second divergence in the same method, also removed.** `EstatePlanService`
composed its own allowance messages: a married couple was told *"Individual Nil Rate
Band. On second death, up to double may be available."* on `/plans/estate` while
`/estate/inheritance-tax` told them *"Combined Nil Rate Band of £650,000 available …
Reduced by £150,000 due to gifts made within the last 7 years."* Same household, same
instant, two accounts of one allowance, and only one mentioned the deduction the
arithmetic had already applied. Both surfaces now render the calculation's own
strings, plus the new `projected_rnrb_message` beside the projected column.

### The £103,206 in the dispatch belongs to a different defect

**The dispatch attributes £103,206 to the two screens. Measured, it is the gap between
the two LOGINS**, which is R-18 §2.6 item 3, not §2.6 item 2. Both are real; they are
not the same defect, and the number belongs to the second. **Root cause located, not
fixed** — see §6.

---

## 4. Measured, read-only, against the live household

Computed through the real path — `liveSpouse()`, `hasAcceptedSpousePermission()` —
exactly as `IHTController::calculateIHT` does. `persist` defaults false, so nothing
was written to users 16 or 17.

| | David (16) | Sarah (17) | Expected (R-18 §2.5/§2.6) |
|---|---|---|---|
| Net estate | 1,716,780.00 | 1,716,780.00 | 1,716,780 |
| Allowances | 850,000.00 | 850,000.00 | 850,000 |
| Charitable | 20,000.00 | 20,000.00 | 20,000 |
| Taxable | 846,780.00 | 846,780.00 | 846,780 |
| **Inheritance Tax** | **338,712.00** | **338,712.00** | **£338,712 — unchanged** |
| Projected net estate | 4,368,400.76 | 4,471,607.25 | 4,368,401 / 4,471,607 |
| Projected nil rate band | 500,000.00 | 500,000.00 | 500,000 |
| **Projected residence band** | **0.00** | **0.00** | **extinguished** |
| Projected charitable | 20,000.00 | 20,000.00 | 20,000, **not 50,891** |
| Projected taxable | 3,848,400.76 | 3,951,607.25 | 3,848,401 |
| **Projected tax** | **1,539,360.30** | 1,580,642.90 | **£1,539,360** |

Both pins hit: **the current column is untouched at £338,712 and David's projection
lands on £1,539,360**, the figure the tester hand-computed. Sarah's differs only by
the between-logins defect in §6.

`/plans/estate` for user 16, after `cache:clear`: projected net 4,368,400.76,
allowances 500,000.00, residence band 0.00, charitable 20,000.00, taxable
3,848,400.76, **tax 1,539,360.30** — byte-identical to the drill-down.

**The residence-band message now says the right thing:** *"Residence Nil Rate Band
fully tapered away. Your estate of £4,368,401 exceeds the taper threshold of
£2,000,000 by £2,368,401, eliminating all RNRB of £350,000."*

> **⚠️ Read before re-testing.** `EstateAgent::analyze()` caches its whole result
> (`estate_analysis_{userId}`, `BaseAgent::remember`). The first `/plans/estate` read
> after this change returned the **pre-fix** figures from that cache — taxable
> 3,467,510.13, tax 1,387,004.05, allowances 0. `php artisan cache:clear` fixes it.
> Model writes are invalidated by `RecommendationCacheObserver`, so this is
> deploy-time staleness rather than a defect — **but anyone re-measuring without
> clearing will read the old numbers and conclude the fix failed.**

---

## 5. Tests — written so they cannot pass against a literal

The instruction was explicit and it came from a live failure on 2026-08-21: a mock
returned `'rate' => 0.40`, the same key the code wrongly asked for, and the suite
stayed green over a tax rate no configuration change could move.

**So these assert that the answer MOVES when a configured input moves**, using the
seeded `TaxConfiguration` row rather than a mock. `withIhtConfig()` rewrites part of
`inheritance_tax`, clears `TaxConfigService`, and re-resolves the calculation service.

`tests/Unit/Services/Estate/IHTProjectedAssessmentTest.php` — **17 passed, 66
assertions**:

- lifting `rnrb_taper_threshold` above the projected estate restores the full band and
  lowers the projected tax — a hardcoded £2,000,000 cannot follow this
- softening `rnrb_taper_rate` leaves more band standing and less tax
- changing `standard_rate` to 0.45 moves both columns' liabilities
- changing `nil_rate_band` to £400,000 moves both columns' allowances
- the taper reaches the projection while today's band is untouched, and extinguishes
  it entirely once the reduction exceeds it
- **a household below the threshold still gets the full band in both columns**
- the projected cap follows the projected residence value, not today's
- **a fixed cash legacy stays £20,000 in a projection 2.5× larger**, and a percentage
  legacy still grows — the mirror-image defect would be freezing both
- the 10% rate test is re-run against the projected estate
- every allowance component reconciles to its published total in both columns
- `/plans/estate` and the calculation publish identical projected figures and
  identical messages

`tests/frontend/components/Estate/IHTCalculationTable.test.js` — **10 passed**. These
assert the **arithmetic of what is rendered**, not the presence of a label, because a
label can be present and still not add up — which is exactly how this survived a
browser pass. Rendered rows sum to the printed subtotal in **both** columns; the
chargeable transfer has its own row signed as an addition; the spouse band is labelled
as modelled; the exemption renders independently of the toggle; the single and widowed
branches reconcile; an un-updated caller falls back cleanly.

`tests/Unit/Services/Estate/IHTCalculationPersistTest.php` — **+1**. The cache hashes
fingerprint the DATA, never the code, so a result persisted by an older build passes
every hash check and is served whole. `isCurrentResultShape()` (`:1859`) recomputes instead.
Dormant today (`$persist` is false at all five call sites — W-0131); it exists so
fixing W-0131 does not resurrect a pre-fix answer.

**Regression sweep, all green:** `tests/Unit/Services/Estate` (247) ·
`tests/Feature/Estate` + `tests/Unit/Services/Plans` (379 combined) ·
`tests/Unit/Services/Coordination` + `tests/Feature/Agents` + `tests/Feature/Dashboard`
+ `tests/Feature/Mobile` + `tests/Unit/Services/Mobile` (409) ·
`tests/Unit/Agents/EstateAgentGoalsTest` + `tests/Feature/Stores/PropertyReadConsumerParityTest`
(11) · `npx vitest run tests/frontend` (720). Pint clean, ESLint clean.

---

## 6. Found, evidenced, NOT fixed — each needs its own item and its own pin

**Every one of these would move the pinned £1,539,360, which is why none was taken
unilaterally.**

### 6.1 The two logins project two different households — £103,206, deterministic

**This is the number the dispatch attached to W-0135.** It is reproducible to the
penny and it is not stochastic:

```
David  age 49 | years_to_death 36 | age_at_death 84 | projected_cash -2,957,895
Sarah  age 48 | years_to_death 36 | age_at_death 84 | projected_cash -2,854,689
```

Properties, investments and liabilities are identical. **The entire £103,206 is in
`projected_cash`**, and the cause is at
`IHTCalculationService::projectCashWithInflation()`:

```php
for ($age = $currentAge; $age < $deathAge; $age++) {
```

`$currentAge` is the **logged-in user's** age; `$deathAge` is the **second-death age
of whoever dies later**. Two age scales in one loop. David iterates 49→83 = **35**
years, Sarah 48→83 = **36**. `years_to_death` is 36 for both, so David's projection
is a year short of its own stated horizon. `projectLiabilities()` computes
`$yearsToProject = $deathAge - $currentAge` and inherits the same error.

The gap scales with the household's annual surplus, which is why the tester measured
it growing from £88,257 to £103,206 as the household was entered — proportional, not
a fixed offset, exactly as recorded.

**Fixing it means choosing an anchor** (project `years_to_death` years from today for
both logins) and that changes the projected estate, so the £1,539,360 pin moves with
it. This is W-0137's mechanism and belongs to W-0137.

### 6.2 A third mechanism for projected liabilities, with a hardcoded age

`IHTFormattingService::formatUserLiabilities()` (`:422`) projects mortgages as
`($ageAtDeath >= 70) ? 0 : $userShare` — a **hardcoded 70** — while
`IHTCalculationService::projectLiabilities()` amortises to the real end date. Both
`IHTController` (`:93`) and `EstatePlanService` (`:421`) then **overwrite**
`projected_net_estate` with the formatting service's figure while leaving the taxable
estate computed from the service's own.

They agree for `peak_earners` (both project to zero), which is why the drill-down
reconciled. **They need not agree**, and now that the taper is assessed against the
service's projected net estate, a disagreement means the taper is computed off a
number the user cannot see. Rule 20 candidate. Both surfaces apply the identical
override, so the two screens do not disagree with each other today.

### 6.3 Gifts never fall out of the seven-year window in the projection

`projected_nrb_available` carries today's £150,000 gift deduction to a death 36 years
away. The transfer will be long outside the window. **The tester's own expected value
assumes the deduction persists** (R-18 §2.6 expects allowances of £500,000), so
changing it would break the agreed pin — but the projected band is arguably £650,000
and the projected tax £1,479,360. **This needs a ruling, not a code change.**

### 6.4 The free-tier `/m` teaser is a fourth answer to "what do you owe"

`EstateIhtExposureDetector::detect()` (`:31-58`) computes
`(netWorth − (nrb + rnrb)) × rate` from `NetWorthService`, which **includes pensions**
and applies a single person's bands with no doubling, no gift deduction, no charitable
exemption, no residence cap and no taper. Deliberately rough for a teaser, but it is a
fourth mechanism and a free user upgrading sees the figure move for reasons the
product never explains.

### 6.5 `secondDeathTableProps` is unreachable

`IHTPlanning.vue:1292` returns null unless `secondDeathData.second_death_analysis`
exists, and `/estate/calculate-iht` emits no such key. Married users render the
**standard** table. Left in place, minimally updated so it does not silently lose the
residence-band note if it ever becomes reachable.

---

## 7. Rule 19 — surfaces, named individually

| Surface | Defect 1 (taper) | Defect 2 (scaling) | Defect 3 (column) | Defect 4 (two screens) |
|---|---|---|---|---|
| **web** | fixed | fixed | fixed | fixed |
| **`/m`** | **reaches it, nothing renders it** | **reaches it, nothing renders it** | **no counterpart** | **no counterpart** |
| **iOS** | same as `/m` | same as `/m` | no counterpart | no counterpart |

`/m` reads the same `IHTCalculationService` through `EstateAgent`
(`MobileDashboardAggregator::extractEstateSummary`, `:264-281`), so the backend fixes
are already there. **What `/m` renders is the constraint, not the plumbing:**
`resources/mobile/views/modules/Estate.vue` shows net estate, an asset composition, and
counts of gifts, trusts and wills. **The premium view shows no Inheritance Tax figure
at all, no allowance itemisation and no projected column** — that is W-0138, unchanged
by this work. The free teaser shows `estimated_liability_gbp` from the separate
detector in §6.4, which this work does not touch.

**So the honest statement is: nothing on `/m` displays any figure these four defects
changed.** There is no `/m` regression to look for and no `/m` improvement to see. It
becomes relevant the moment W-0138 gives `/m` a tax figure, and W-0138 should read the
service's published fields rather than compose a fifth answer.

---

## 8. Files

| File | What changed |
|---|---|
| `app/Services/Estate/IHTCalculationService.php` | `assessTaxPosition()`, `projectMainResidenceNetValue()`, `isCurrentResultShape()`; `calculateRNRB()` takes the residence value; `$charitableFraction` deleted; ten `projected_*` fields published |
| `app/Http/Controllers/Api/Estate/IHTController.php` | `iht_summary` carries the exemption, the rate, and the projected allowance block |
| `app/Services/Plans/EstatePlanService.php` | recompute deleted; identical summary shape; messages from the calculation |
| `resources/js/components/Estate/IHTCalculationTable.vue` | row model, per-column allowances, exemption row, per-column taper note; Vuex tax-config read removed |
| `resources/js/components/Estate/IHTPlanning.vue` | dropped fields carried; projected allowances and exemption passed; projected residence message rendered |
| `resources/js/components/Plans/Estate/EstateCurrentSituation.vue` | same props as the drill-down; projected residence message |
| `tests/Unit/Services/Estate/IHTProjectedAssessmentTest.php` | new, 17 tests |
| `tests/frontend/components/Estate/IHTCalculationTable.test.js` | new, 10 tests |
| `tests/Unit/Services/Estate/IHTCalculationPersistTest.php` | +1 test |
