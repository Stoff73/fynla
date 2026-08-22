---
id: F-0017
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-22T20:45:00Z
status: active
---

# F-0017 — Cycle 1: the income tax computation, and the figures around it

**Agent:** build-lead (`cycle1-tax`) · **Written:** 2026-08-22
**Branch:** `dev` (shared working tree — nothing committed, no PR, no deploy, by instruction)
**Number issued by team-lead.**

| Item | Status | Notes |
|---|---|---|
| W-0174 Personal Allowance tapered, basic-rate band not narrowed | **DONE** | `handoff` → quality-lead. £2,514/person under-charge closed |
| W-0175 rental income stated two ways on one page | **DONE** | `handoff` → quality-lead. One residual flagged, not guessed |
| W-0176 linked spouse's income displays as £0 | **DONE** | `handoff` → quality-lead. Item raised here; web only |
| W-0177 "Income needs updating" listed under COMPLETED | **DONE** | `handoff` → quality-lead. Item raised here; web only |
| W-0178 maintenance-reserve deductibility | **RAISED, not claimed** | `queued` → compliance-lead. The residual split out of W-0175 so it is decided, not settled quietly |

Handoff notes: `ops/handoffs/W-0174/` · `W-0175/` · `W-0176/` · `W-0177/`, all
`build-to-quality-2026-08-22.md`.

---

## 1. What was actually wrong, in one line each

**W-0174.** The £37,700 basic-rate band width is the constant and the £50,270 threshold is
derived from it; the code had that inverted in two places, so removing the allowance
widened the 20% band instead of moving the threshold down.

**W-0175.** A correct property-business profit already existed; a second service re-derived
gross rent for the allowance panel, so one page taxed one income and tested the taper
against another.

**W-0176.** A family-member row served its own stale income column while the fallback path
forty lines below already read the linked account.

**W-0177.** A staleness flag was declared as if it were a piece of data, so lowering it
counted as an achievement and printed its own problem statement under COMPLETED.

**All four are the same disease as F-0016's two:** the honest version already existed —
`bands[0].max` seeded and unread, `calculateTaxPosition()` computing the right figure,
the virtual-spouse path reading the right account — and a second mechanism a few lines
away did it again, differently.

---

## 2. Prior-art check

Six sources, `charter.md` §11. Outcome **extend** on all four — in every case the correct
mechanism existed and a duplicate had drifted.

| Source | What it gave |
|---|---|
| `registry/capabilities.md` | "All UK tax values → `TaxConfigService`, never hardcode (Rule 2)". No band-geometry entry — the gap this branch fills |
| Code | Two homes for the band derivation, two for rental, two for family-member income |
| Custom artisan commands | Nothing relevant; `db:seed --class=TaxConfigurationSeeder` is the reset path |
| Open PRs / branches | None touching `UKTaxCalculator`, `TaxBandTracker`, `PropertyService` or `ModuleDataRequirementsService` |
| Vault | `v083/` is absent from the working vault; the seeder's own comments were the authority for band shape |
| `.claude/skills`, `.claude/agents` | `tax-compliance-reviewer` exists as a review agent; no band-resolution machinery |

**One live capability-map gap.** `workforce/core/registry/capabilities.md` records only
"use `TaxConfigService`". It does not record **which key means a width and which means an
absolute threshold** — the exact confusion that produced W-0174. Worth an entry:

> Income-tax band geometry → `App\Services\Tax\IncomeTaxBands`. `bands[0].max` is the
> basic-rate band **width** (£37,700); `bands[0].upper_limit`, `higher_rate_threshold` and
> `additional_rate_threshold` are absolute income thresholds. `bands[1].max` is an
> absolute threshold despite the name — never read it as a width.

---

## 3. What changed

### New

- `app/Services/Tax/IncomeTaxBands.php` — the one home for band geometry: the taper, the
  absolute limits derived from the configured width, and the Gift Aid / pension band
  extension. Pure, config-array in, no container dependency, so both the tracker and the
  calculator use it identically.

### Rewired

| File | Change |
|---|---|
| `app/Services/TaxBandTracker.php:31-56` | Parses no configuration of its own; optional effective-allowance argument |
| `app/Services/UKTaxCalculator.php:44-58` | Stopped mutating the config array — the mutation was the mechanism |
| `app/Services/UKTaxCalculator.php:653-676` | Simple path composes from the same object |
| `app/Services/UKTaxCalculator.php:117-125` | Income components carry a stable `key`; rental relabelled "Rental Profit" |
| `app/Services/Property/PropertyService.php:24-88` | `annualRentalTaxPosition()` — the one rental figure |
| `app/Services/UserProfile/UserProfileService.php:235-242` | Composes from it |
| `app/Services/Tax/IncomeDefinitionsService.php:99-114` | Composes from it; gross re-implementation deleted; takes `PropertyService` not `PropertyStore` |
| `app/Services/UserProfile/UserProfileService.php:756-766, 781-796, 836` | One definition of a linked account's income, both paths |
| `app/Services/UserProfile/ModuleDataRequirementsService.php:602-614, 641-646` | Flag-style requirements omitted when satisfied |
| `resources/js/components/UserProfile/TaxIncomeCard.vue:38` | Matches `component.key`, not a display label |
| `resources/js/components/UserProfile/IncomeDefinitionsPanel.vue:146` | "Rental profit" |
| `resources/js/components/UserProfile/IncomeOccupation.vue:404-408` | Names the deducted expenses |

**Five copies of the Personal Allowance taper formula existed**, each writing `/2` rather
than reading the configured `personal_allowance_taper_rate`. The two inside
`UKTaxCalculator` are consolidated. `IncomeDefinitionsService`, `TaxStrategyMath` and
`SaveTaxEstimateService` still carry their own — **correct, so not defects, and outside
this branch's scope**, but they are the next Rule 20 consolidation in this area and
nothing prevents them drifting apart.

---

## 4. Evidence

| | Before | After | Expected |
|---|---|---|---|
| David income tax | £50,149 | **£52,663.50** | £52,663.50 |
| Sarah income tax | £41,685 | **£44,199** | £44,199 |
| Sarah total income | £128,880 / £130,800 | **£128,880** both panels | one figure |
| Sarah on `/settings/family` | £0 | **£120,000** | £120,000 |
| `profile` readiness panel | 9, contradictory | **8 of 8, 100%** | no contradiction |

Taper sweep, both code paths agreeing: £99,000 → £27,032 · £110,000 → £33,432 ·
£125,140 → £42,516 · £159,290 → £57,883.50.

### Tests

New, all green: `tests/Unit/Services/Tax/IncomeTaxBandsTest.php` (15) ·
`tests/Unit/Services/UKTaxCalculatorTaperedBandTest.php` (12) ·
`tests/Unit/Services/Tax/RentalIncomeOneDefinitionTest.php` (7) ·
`tests/Unit/Services/UserProfile/LinkedSpouseAnnualIncomeTest.php` (5) ·
`tests/Unit/Services/UserProfile/FlagRequirementCompletionTest.php` (6).

Written to the brief: **every pinned number has a sibling that moves the configured input
and requires the output to follow** — band width, taper threshold, taper rate, full
allowance, additional-rate threshold, rates, and, for rental, the property record itself.

Regression families re-run green: the three other `UKTaxCalculator*` suites,
`IncomeDefinitionsServiceTest`, `UserProfileServiceTest`, `FinancialCommitmentsTest`,
`PropertyServiceTest`, `PropertyTaxServiceTest`, `ChildBenefitServiceTest`, all of
`tests/Unit/Services/Retirement/`, all of `tests/Architecture/`, the tax-config feature
suites, `tests/Feature/Income/`, and the `UserProfile` vitest specs (66).
`pint --dirty` clean.

**Two existing test files were changed and both changes need independent review:**

1. **`UKTaxCalculatorAdjustedNetIncomeTest`** — three assertions encoded the defect,
   comments and all ("basic band space = £50,270 − PA"; one said it matched "pre-fix
   behaviour"). Corrected to £37,432 / £33,432 / £42,432, each moving by exactly 20 points
   on the allowance that case withdraws. Hand-derived against HMRC's published bands
   before running anything.
2. **`PropertyServiceTest`** — `new PropertyService` with no arguments no longer
   constructs; resolves from the container instead. No asserted behaviour changed.

---

## 5. Judgement calls, stated as such

**W-0175's diagnosis was one step out, and correcting it changed the fix.** The item reads
the £8,880 as netting "two of four cost fields — not a definition anyone can reconcile"
and proposes keeping both figures with different labels.
`PropertyService::calculateTaxPosition()` deducts **eight** fields — the HMRC allowable
set. The persona had populated two. So the profit was already principled and the gross was
the wrong figure, and the right fix was to collapse to one, not to label two. Under ITA
2007 s23 Step 1 property income enters total income as the profits of the property
business, which is what adjusted net income and threshold income build on. Raised with
team-lead before building.

**Left open deliberately, for a decision rather than a guess: now W-0178.** Whether
`monthly_maintenance_reserve` and `other_monthly_costs` should join the allowable-expense
list. A reserve is not a paid expense and capital improvements are never allowable, so the
exclusion is defensible — but it is a deductibility question, not an engineering one.
Raised as its own item at team-lead's direction rather than left as a note inside a fix.

The line I drew, in case it is the wrong one: **which figure enters total income** is a
question the statute answers, so I settled it; **which expenses are allowable** is a
judgement about incurred cost and intent, so I did not.

**One correction to my own correction.** I first reported that the persona "had populated
only two" of the eight allowable fields. Measured: it populated **four** cost fields, two
of which are on the allowable list (`monthly_building_insurance`, `monthly_service_charge`)
and two of which are not (`monthly_maintenance_reserve`, `other_monthly_costs` — note the
column name, which is not `monthly_other`). So the tester's "two of four" was an accurate
count; only the inference that it was arbitrary was wrong. The W-0175 item now says this
precisely rather than approximately.

**W-0173's cause, found in passing.** `users.annual_rental_income` is written only by
`updateIncomeOccupation`, so surfaces reading the column directly —
`PersonalAccountsService.php:68` and `:196`, `ResolvesIncome`, `CashFlowProjector.php:116`
— can disagree with the computed figure. Sarah's holds `0.00`; David's holds `17280.00`,
the gross share. Recorded on W-0175 so W-0173 is not re-derived from scratch.

---

## 6. Surfaces

Named individually, Rule 19.

| Item | web | `/m` | iOS |
|---|---|---|---|
| W-0174 | server-side | **inherits; no counterpart exists** | **inherits; no counterpart exists** |
| W-0175 | server-side + 3 components | **inherits the figure; no panel or drill-down** | **inherits the figure** |
| W-0176 | the only surface | **no family-members income screen** | **none** |
| W-0177 | the only surface | **no info-guide panel** | **none** |

Each "no counterpart" was established by grep, not assumed: `detailed_tax_breakdown` /
`income_breakdowns` / `basic_rate`, `annual_income`, and `info-guide` / `infoGuide` /
`ModuleStatusBar` across `resources/mobile/` and `ios-native/Fynla/`.

---

## 7. Not done

- **No browser verification on any surface** — by instruction; the tester closes that loop.
- **No commit, no PR, no deploy, no bundle rebuild, no tool-schema capture** — by
  instruction. Nothing here touches the tool catalogue.
- **Nothing written to users 16 or 17.** Every check against that household was read-only;
  the W-0177 verification flipped the flag in memory and never saved.
- **W-0173 not fixed** — same family, different item.
- **The three remaining taper copies not consolidated** — correct today, recorded in §3.
