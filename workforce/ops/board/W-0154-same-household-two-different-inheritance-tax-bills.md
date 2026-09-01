---
id: W-0154
title: The same married household is shown two different inheritance tax bills depending on which spouse logs in, and the allowance components do not sum to the totals
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0012-batch-g-iht-household.md
owner: build-lead
reviewers: [tax-compliance-reviewer, compliance-lead]
status: done
claimed_by: null
severity: critical
surfaces: [web, m, ios]
created: 2026-08-21T19:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: ["W-0131 the Inheritance Tax cache is never written", "W-0132 three mechanisms answer whether the reduced rate applies", "W-0020 charitable total tests an enum value the column cannot hold", "F-0003-batch-b-estate-wills"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found by CSJ, 2026-08-21, who asked why the allowances were not being checked or explained. They were not being checked because the coordinator scoped the tester to a rate LABEL rather than to the figures. That scoping error is recorded here deliberately.
---

## Intent

Computed through the **real endpoint path** — `$user->liveSpouse()`,
`$user->hasAcceptedSpousePermission()`, exactly as `IHTController::calculateIHT` does
(`app/Http/Controllers/Api/Estate/IHTController.php:49-55`). Not a tinker artefact; an
earlier run that omitted the spouse argument produced a different and misleading picture,
and that near-miss is recorded in the working notes below.

**Persona household, David Jones (16) and Sarah Jones (17), married, reciprocal
`spouse_id`, spouse permission accepted, data sharing true on both sides.**

| Field | David (16) | Sarah (17) |
|---|---|---|
| `total_net_estate` | 1,234,280 | 1,234,280 |
| `nrb_available` | **500,000** | **650,000** |
| `nrb_individual` / `nrb_transferred` | 325,000 / **0** | 325,000 / **0** |
| `rnrb_available` | 350,000 | 350,000 |
| `rnrb_individual` / `rnrb_transferred` | 175,000 / **0** | 175,000 / **0** |
| `total_allowances` | 850,000 | 1,000,000 |
| `taxable_estate` | 374,280 | 224,280 |
| **`iht_liability`** | **149,712** | **89,712** |

### Three distinct defects, all visible in that table

1. **The same household produces two different liabilities — a £60,000 difference —
   depending on which spouse is logged in.** The combined net estate is identical
   (1,234,280 both sides). Both cannot be right.
2. **The allowance components do not sum to the totals they are reported alongside.**
   `nrb_individual` 325,000 `+ nrb_transferred` 0 is reported as `nrb_available`
   **500,000** for David and **650,000** for Sarah. `rnrb_individual` 175,000
   `+ rnrb_transferred` 0 is reported as `rnrb_available` **350,000**. A user reading the
   breakdown cannot reconcile it with the total, because it does not reconcile.
   **`nrb_transferred = 0` on a married couple is itself suspect** — a doubled band is
   being produced somewhere without being attributed to a transfer.
3. **A reduced charitable rate is applied to a nil liability, off a baseline computed
   from a different allowance set.** Priya Raman (20): `total_allowances` 1,000,000,
   `taxable_estate` **0**, `iht_liability` **0** — and `iht_rate_percent` **36**. The
   £6,500 charitable threshold is 10% of a £65,000 baseline, and £65,000 is
   715,000 − 650,000, i.e. derived from the nil rate band alone rather than from the
   allowance set actually applied. **The 10% test is being run against a baseline that
   does not correspond to the computation it qualifies.**

### Also observed in the same runs, to be triaged not assumed

- **`nrb_deduction` is an array where a scalar is expected** — "Array to string
  conversion" when rendered. David's `nrb_message` reads *"Reduced by £150,000 due to
  gifts made within the last 7 years"*, so a gift deduction exists and its payload shape
  is wrong.
- **A negative projected net estate.** Sarah returned `projected_net_estate`
  **−185,535.84** in one configuration. A negative estate is not a small error.
- **A deprecation on every call** — `Using null as an array offset` at
  `Illuminate/Database/Eloquent/Relations/BelongsTo.php:187`, reached through
  `getCompleteProfile()`. Recorded by `fix-batch-C` in `F-0001` as "noticed, not fixed,
  worth its own item". It is fired by the estate path too.

## Acceptance (SUPERSEDED — see the second Acceptance block below)

**2026-08-29 build-lead: this block is the ORIGINAL statement of the criteria and is kept
for the record. It was never ticked off because the second block below replaced it, and
counting the two together is what made the 2026-08-29 gated triage read this item as
"7 of 16 ticked". The live checklist is the second one.**

- [ ] One household produces **one** answer. Whichever spouse is logged in, the
      combined-estate liability is the same number, or the difference is explained on
      screen in terms a user can follow.
- [ ] **Every allowance component reconciles to its total**, on screen and in the
      payload: individual + transferred (+ any deduction, signed) = available. A user
      must be able to add up what they are shown.
- [ ] The transferable nil rate band and transferable residence nil rate band are
      attributed as transfers when they are applied, rather than appearing inside a
      total with `transferred: 0`.
- [ ] The charitable 10% test runs against the baseline that corresponds to the
      computation it qualifies, and **no reduced rate is reported where the liability is
      nil** — reporting "36%" on a £0 bill is meaningless at best and misleading at worst.
- [ ] The gift deduction payload is a scalar, and the seven-year taper is verified
      against `TaxConfigService` rather than assumed (Rule 2 — no hardcoded tax values).
- [ ] A negative projected estate is impossible by construction.
- [ ] **Every figure is verified against hand-computed expected values from
      `tests/Persona/peak_earners.md`**, which is the only persona source (CSJ ruling,
      2026-08-21 — the PDF is out of scope).
- [ ] `/m` and iOS are checked for the same arithmetic, not assumed to inherit it
      (Rule 19), and any second implementation is consolidated rather than aligned
      (Rule 20).

## Working notes

(append-only)

- 2026-08-21 team-lead: **raised by CSJ, and the reason it was not found sooner is a
  scoping error of mine, recorded so it is not repeated.** The tester was set a check
  that the *displayed rate label moves* from 40% to 36% when a bequest changes. That is a
  headline. It cannot detect a wrong nil rate band, an unattributed transfer, components
  that do not sum, or a reduced rate on a nil liability — **all of which were present the
  whole time.** For a financial planning application the check must be figure-by-figure
  against hand-computed expected values, not a label.
- 2026-08-21 team-lead: **near-miss worth recording.** The first run of this evidence
  called `IHTCalculationService::calculate($user)` without the spouse argument.
  `$isMarried` is `marital_status === 'married' && $spouse !== null`
  (`IHTCalculationService.php:99`), so the couple read as **single**, producing
  `is_married: false`, `nrb_transferred: 0`, `spouse_net_estate: 0` and a message saying
  *"available for single person"*. **That looked exactly like a critical married-couple
  bug and was an artefact of the caller.** The real endpoint resolves the spouse
  correctly. Anyone reproducing this must use `liveSpouse()` +
  `hasAcceptedSpousePermission()` as `IHTController` does — otherwise they will report a
  defect that does not exist and miss the three that do.

- 2026-08-21 tax-compliance-reviewer: **audit complete —
  `workforce/ops/reports/2026-08-21-iht-calculation-audit.md`.** Eleven findings, ranked
  by what a user is told wrongly. Report-only; no code changed, no database writes. Source
  register rows **A15, C4, C5, C6** added (A14/C3 were claimed concurrently by another agent; mine were renumbered). Figures below are **hand-computed from
  read-only queries** — four `vendor/bin/pest` runs were live and the personas were being
  driven in a browser, so I did not execute `calculate()`. They reconcile to the penny
  with the measured evidence above.

- 2026-08-21 tax-compliance-reviewer: **Q1, the £60,000 household gap — answered, single
  root cause.** **Every per-person input is read from the logged-in user only, while every
  asset and liability is pooled.** David has one CLT, **£150,000 on 2020-09-01**, inside
  the seven-year window. `calculateNRBDeductionForGifts()`
  (`IHTCalculationService.php:148` → `:1699`) queries `Gift::where('user_id', $user->id)`,
  so it reduces the pooled band by £150,000 in David's view and by nothing in Sarah's.
  **£150,000 × 40% = £60,000 — the entire gap.** Same class at `:104` and `:1318`
  (`IHTProfile` user-only) and `:1338` → `WillAnalysisService.php:131` (bequests
  user-only) — the household's **two** £10,000 charitable bequests are deducted as
  £10,000, never £20,000, either way round. **The stated compensating mechanism does not
  exist:** the comment at `:147` says spouse NRB "is handled separately by
  `SpouseNRBTrackerService`" — that class has **zero callers in the repository**. The
  model's own pooled logic implies **£145,712 for both spouses**.

- 2026-08-21 tax-compliance-reviewer: **Q2, which half is wrong — the TOTAL is the
  modelled half; the components are the honest half.** `× 2` at `:134` (NRB) and `:1233`
  (RNRB), gated on `$isMarried` alone. **There is no transferable nil rate band while both
  spouses are alive** — IHTA 1984 s8A creates it on the survivor's death as a percentage
  of the band unused on the first. So `transferred: 0` is legally correct today and the
  doubling is an unstated **second-death** assumption. **Do not fix this by writing
  325,000 into `nrb_transferred`** — label the doubled band as the modelled transfer it
  is. The £175,000 a user cannot account for is **two** unlabelled effects netting out:
  +£325,000 modelled transfer, −£150,000 gift deduction. **And the deduction has no field
  to live in:** `iht_summary.current` (`IHTController.php:110-116`) has no deduction key,
  and `nrb_deduction` has **no consumer anywhere in the repo**.

- 2026-08-21 tax-compliance-reviewer: **Q3, the charitable test — the £65,000 baseline is
  RIGHT IN LAW. Do not "fix" it.** IHTA 1984 **Sch 1A para 5** (legislation.gov.uk, Latest
  available (Revised), *"up to date with all changes known to be in force on or before
  21 August 2026"*, read 2026-08-21): Step 2 deducts *"the appropriate proportion of the
  available nil-rate band"*, defined by reference to *"the nil-rate band maximum
  (increased, where applicable, in accordance with **section 8A**)"* — **s8A only; ss.8D–8M
  (residence nil rate band) are not referred to.** HMRC IHTM45008 says it directly: do not
  deduct the residence nil rate band, and an adviser who does will compute a donation too
  small to qualify. `:1324` is correct. **Step 3's add-back is satisfied only by
  ordering** — `$totalNetEstate` (`:128`) still contains the charitable gift, which is
  deducted later at `:175`; **any refactor moving that deduction earlier silently breaks
  Sch 1A Step 3.** Needs a comment and a test.
  **What IS wrong, three things.** (a) `$charitablePercent` is a percentage of the **net
  estate** (`:1327`, `:1341`) printed beside a **baseline** threshold (`:1350`), so Priya
  is told *"your charitable giving of 1.4% ... meets the 10% threshold"* — self-
  contradictory on its face; the comparable figure is **15.4%** (10,000 ÷ 65,000). (b)
  **No nil-liability guard** — `determineIHTRate()` runs at `:161`, before the taxable
  estate exists at `:175`. (c) **Where a user is actually told 36% on a £0 bill:**
  `EstatePlanService.php:478-481`, unguarded, and its wording *"10% or more of **the net
  estate**"* is **wrong in law** — the test is 10% of the baseline. **Checked and NOT a
  defect:** `EstateAgent.php:1455-1458` **is** correctly guarded on `iht_liability > 0`
  and says "No Inheritance Tax is due" for Priya. Leave it alone.
  **UNMAPPED REGIME:** Sch 1A tests **three components** (survivorship / settled /
  general) independently with an election to merge. Fynla runs one estate-wide test, and
  both collapsed components are populated in real data (joint property, trusts module).
  **I could not establish how Fynla should map onto the components — needs product-lead
  and a CSJ ruling before anyone codes it.**

- 2026-08-21 tax-compliance-reviewer: **Q4, allowances vs `TaxConfigService` — the core
  values are sourced correctly; the periphery is not.** Correct and config-read: NRB
  325,000 · RNRB 175,000 · taper threshold 2,000,000 · taper rate 0.5 · 0.40 · 0.36 ·
  0.10; `TaxDefaults` matches and is fallback-only. **Hardcoded defects:** `subYears(7)`
  ×3 and `subYears(14)` at `:1700,1706,1714,1715` (three seeded keys unread); "36%"/"40%"
  **in the message prose** at `:1350,1361,1372`; `rnrbTaperThreshold: 2000000` at
  `IHTPlanning.vue:1406`; `?? 650000` ×2 at `ComprehensiveEstatePlanService.php:891,895`;
  `/ 0.40` at `GiftingController.php:185`; `"10%"` at `EstatePlanService.php:480`;
  `$baseline * 0.04` at `WillAnalysisService.php:74` (**also arithmetically wrong** — the
  saving is (standard − reduced) × the chargeable estate after the charitable deduction);
  the whole taper schedule as literals at `PersonalizedTrustStrategyService.php:378-397`.
  **Configured but read by nothing:** `getTaperRelief()` / `getGiftTaxRate()` have **zero
  consumers** — and separately, **seven-year taper relief is not applied anywhere in the
  IHT calculation at all** (F7); `getFourteenYearRule()`; and
  `business_relief.allowance_cap` **£2,500,000** with effective date **2026-04-06, four
  months past** — `EstateAssetAggregatorService.php:114-137` applies BPR as binary 100%
  `is_iht_exempt` **with no cap**. The £2.5m figure itself is **correct** (gov.uk, pub.
  26 Nov 2025 / upd. 3 Mar 2026, read 2026-08-21 — raised from £1m on 23 Dec 2025).
  **Two config homes for one 10%:** `TaxConfigService::getCharitableThresholdPercent()`
  (0.10 fraction) vs `PlanConfigService::getCharitableGivingThreshold()` (10.0 percent).

- 2026-08-21 tax-compliance-reviewer: **Q5, the three secondary observations — two of the
  three are NOT what they appear.**
  (a) **`nrb_deduction` array → the payload shape is not the user-facing defect.** It has
  **no consumer**: not in `iht_summary`, no Vue component, not `/m`, not iOS, no test.
  **I could not reproduce the "Array to string conversion" and cannot say where it fired**
  — most likely the evidence harness interpolating `calculation`. The real defect
  underneath it is the missing scalar deduction field (Q2).
  (b) **Negative projected estate — confirmed, and it is two bugs.**
  `projectCashWithInflation()` (`:486-531`) has **no floor** and returns negative cash by
  design, commented *"so line items sum to total"* — **and that rationale is then defeated
  by `IHTController.php:88-92`**, which recomputes `projected_net_estate` from
  `IHTFormattingService`'s liabilities while deliberately leaving
  `projected_taxable_estate` computed inside the service from a **different** liability
  figure. The two displayed numbers cannot reconcile by construction.
  (c) **The deprecation — I could not place it on the estate path.**
  `getCompleteProfile()` has exactly five callers (`UserProfileController:78`,
  `AdviserExportPackService:47`, `DisposableIncomeAccessor:32`,
  `RequiredCapitalCalculator:158`, `RetirementStrategyService:423`) and **none is on the
  IHT path**. `laravel.log` (25MB, written 19:33 today) has **no** "array offset" entry,
  but that proves nothing: `config/logging.php:35` discards deprecations by default
  (`LOG_DEPRECATIONS_CHANNEL` → `null`). PHP 8.5.2. **Recommend it stays with F-0001**;
  set `LOG_DEPRECATIONS_CHANNEL` before attempting it.

- 2026-08-21 tax-compliance-reviewer: **not in the brief, found on the way — three more.**
  (1) **Married with data sharing OFF gets £1,000,000 of allowances against their own
  assets alone.** The doubling gates on `$isMarried` (`:134`, `:1233`); assets and
  liabilities gate on `$isMarried && $dataSharingEnabled` (`:107`, `:118`). And
  `calculateRNRB()` is passed `$spouse` **unconditionally** (`:158`), so `hasMainResidence`
  (`:1405`), `hasDirectDescendants` (`:1437`) and `getMainResidenceNetValue` (`:1459`) all
  consult a spouse whose assets are excluded — RNRB can be granted, and its residence cap
  raised, by a property that is not in the estate. Limited today by the auto-true in
  `User::hasAcceptedSpousePermission()` (`:782-801`) for reciprocal couples.
  (2) **`IHTPlanning.vue:1309-1319` reads SEVEN keys the backend has never emitted**
  (`nrb_from_spouse`, `rnrb_from_spouse`, `rnrb_tapered`, `rnrb_taper_amount`,
  `rnrb_eligible`, `gross_estate_value`, `net_estate_value` — repo-wide grep, zero hits in
  `app/`), so it falls to a **hardcoded £650,000** nil rate band and a taper that can never
  fire. **Dead today** — `secondDeathData` is the `IHTController` response, which has no
  `second_death_analysis` key, so married users fall through to `standardTableProps` at
  `:336`, which is exactly why the measured symptom is 325,000 + 0 vs 500,000. But
  `ComprehensiveEstatePlanService.php:94-100` **already builds a `$secondDeathAnalysis`
  under that name** — one wiring change from showing every married user £650,000.
  (3) **`Bequest::isCharitable()` (`:87-112`) is a name-substring match that moves the tax
  rate** — 'foundation', 'cancer', 'heart'. **It is the live path on all four persona
  bequests** (ids 50, 51, 56, 57 are `beneficiary_type = 'individual'`, qualifying only on
  the name). A company named "Foundation Holdings Ltd" flips 40% → 36%. Cross-refs W-0020.

- 2026-08-21 tax-compliance-reviewer: **two landmines for whoever fixes W-0131**, since the
  cache is dead only while `persist` is never true. (1) `getCachedCalculation()` filters
  `is_married` on `$spouse !== null` (`:1496`) while `saveCalculation()` writes
  `marital_status === 'married' && $spouse !== null` (`:1625`/`:99`) — for a linked user
  whose `marital_status` is not `'married'` the cache can never hit. (2)
  `charitableBequestFingerprint($user)` (`:1535`) is **user-only**, so once the household
  charitable deduction is fixed per Q1, a spouse's will change will not invalidate the
  cache and a stale rate will be served.

- 2026-08-21 tax-compliance-reviewer: **stated plainly, per the brief — what I could not
  determine.** (1) The estate-path route to the `BelongsTo:187` deprecation. (2) Where the
  "Array to string conversion" fired. (3) Whether the single-component charitable test is
  a deliberate simplification — **needs a product decision, not an engineering one.**
  (4) Whether the absence of seven-year taper relief is deliberate scope. (5) The tax year
  the NRB freeze now runs to — gov.uk (pub. 30 Oct 2024) says 2029-30, a later gov.uk page
  says 2030-31, the seeder comments "April 2031"; **changes no figure today**, recorded as
  unresolved at register row C4. (6) I did not execute `calculate()` — see the first note.
  Separately: my own agent-definition reference table says the nil rate band is *"frozen
  until April 2028"*. **Per that document's own instruction, it is stale** — nothing in
  this audit relies on it.

- 2026-08-21 tax-compliance-reviewer: **`/m` and iOS are clean on the breakdown, not on the
  liability.** `resources/mobile/views/modules/Estate.vue` and
  `ios-native/.../Estate/EstateView.swift` render **no** nil-rate-band, allowance or rate
  fields — the wrong breakdown is web-only today. **But all three surfaces read the same
  service**, so the £60,000 household asymmetry travels wherever `iht_liability` is shown.
  **Rule 20 — four resolutions of "who is the spouse, and may we use their data":**
  `IHTController:51-53` and `EstatePlanService:384-386` use `liveSpouse()` +
  `hasAcceptedSpousePermission()`; `ComprehensiveEstatePlanService:71-72` and
  `TrustController:201-202` use `spouse_id` + the permission check; **`EstateAgent:127-128`
  and `:1556-1557` use `$user->spouse` and set `$dataSharingEnabled = $spouse !== null`,
  skipping the consent check entirely** — so Fyn can report a different liability from the
  Estate screen for the same user.

- 2026-08-21 team-lead: **CORRECTION — my framing above was wrong in an important way, and
  the correct reading is the opposite of what the raw numbers suggest.** Found by
  `persona-passA3` in the live browser (`reports/R-17-persona-estate-figures.md`).

  **`gifts.id 10` — a chargeable lifetime transfer of £150,000, dated 2020-09-01, note
  "Auto-recorded", derived from the persona's trust settlement.** On the run date it is
  **5 years 11 months** old, so it is inside the seven-year window and **legitimately
  reduces the nil rate band.**

  **So David's £500,000 is the CORRECT household nil rate band and Sarah's £650,000 is
  the wrong one.** The Intent above led with "£149,712 against £89,712, both cannot be
  right" as though married-couple handling were broken. **One of them can be right, and
  it is the smaller one.** The real defect is narrower and more specific: **the gift
  deduction reaches one spouse's calculation and not the other's.**

  **The hand-computed household answer is £145,712** — net estate £1,234,280, allowances
  £850,000, household charitable legacies **£20,000** (David's Cancer Research UK plus
  Sarah's British Heart Foundation), taxable £364,280. **The application shows £149,712
  and £89,712, so both are wrong**, and the £4,000 gap on David's side is the household's
  second legacy never being recognised.

  **The components-do-not-sum half of the Intent stands and is now explained.** David's
  four allowance rows sum to **£1,000,000** with a printed subtotal of **£850,000** beneath
  them, and **the £150,000 appears in no row.** The deduction is real, correct and
  invisible — which is exactly why 325,000 + 0 could not be reconciled to 500,000.
  **The missing £150,000 IS the reconciliation.** What remains defective is that it has no
  row, and that it does not reach the other spouse.

  **Lesson, recorded because it is the second time today the same trap fired.** The
  earlier near-miss was a caller-side artefact making a married couple read as single.
  This one is the mirror image: **a figure that is genuinely absent from the presentation
  looked like a figure that was genuinely wrong.** Both times the error was in the
  coordinator's inference from a payload, and both times the correction came from someone
  who went and looked at the underlying data. **Do not diagnose an arithmetic defect from
  totals alone — find the row that is missing before concluding the total is wrong.**

  **Also note the £150,000 is not in `tests/Persona/peak_earners.md`.** It is derived from
  the persona's trust settlement and auto-recorded. `PASS-PLAYBOOK.md` §2.5 omitted it and
  has been corrected by the tester — allowances £1,000,000 → £850,000, tax £291,512 →
  £351,512, charitable threshold £107,878 → £122,878 — **with a note that it reverts on
  2027-09-01, so every run must recompute against its own date rather than reuse the
  table.** A time-dependent expected value in a static playbook is its own trap.

  Forwarded to `iht-audit` immediately so it stops working the married-couple resolution
  and confirms the gift-deduction asymmetry instead. **W-0136 (the residence nil rate
  band taper never applied to projections) is routed to it as well**, since a statutory
  allowance being mis-stated is its remit rather than the tester's.

- 2026-08-21 tax-compliance-reviewer: **agreeing with passA3's inversion, and confirming the
  audit already reflects it.** David's **£500,000 is the correct household nil rate band**;
  Sarah's £650,000 is the wrong one. `gifts.id 10` is a settlement into
  **"Jones Children's Education Trust"** (`trusts.id 3`, discretionary, £185,000, settlor
  David) — 5y 11m old, inside the window, and it legitimately reduces the band. The audit
  report reached **£145,712** for the household and identified David's £4,000 error as the
  household's second £10,000 legacy going unrecognised. **No correction to the record is
  needed; the smaller figure being the right one is what F1 says.**

- 2026-08-21 tax-compliance-reviewer: **CHECKED AND CORRECT — do not "fix" this.** The
  obvious-looking bug here is a double count: the £150,000 reduces the nil rate band *and*
  the £185,000 of settled property might also sit in the death estate.
  **`EstateAssetAggregatorService` contains zero trust references** — the settled property
  is **not** aggregated into the estate. That is the right treatment: relevant property in
  a discretionary trust is outside the settlor's death estate. **Leave it alone.**
  *Unverified and outside this brief:* whether the ten-year periodic and exit charges on
  that relevant property are modelled anywhere (`TrustService` / `TrustValuationService`).
  Separately, per F7, the CLT's own death re-charge and taper relief are **not** computed —
  at 5y 11m this gift sits in the 5–6 year band, and none of that reaches any figure.

- 2026-08-21 tax-compliance-reviewer: **STATUTORY RULING on the married-household
  charitable question — the intuitive fix is WRONG, so read this before building it.**
  **There is no household baseline in law.** IHTA 1984 Sch 1A tests **the estate of one
  deceased person** — their chargeable transfer, their components, their available nil rate
  band. "The household's £20,000" is not a quantity the statute recognises.
  Mapping that onto Fynla's own second-death model gives a **split** answer, and the two
  halves must not be conflated:
  - **For the s23(1) exemption (the deduction from the estate): £20,000 is correct.** Both
    legacies are paid and both leave the combined estate. Deducting only £10,000 understates
    the exemption on both accounts. This half of passA3's reading is right.
  - **For the 10% test (the rate): only the SURVIVOR's legacy counts — £10,000.** The
    second-death estate *is* the survivor's estate, so the will that operates on it is the
    survivor's. The first-to-die's legacy was tested on the **first** death, against an
    estate that under full spouse exemption is **nil** — so no rate question arises there at
    all, and their £10,000 cannot be added to the survivor's for the survivor's test.
  **Summing both wills for the 10% test would over-qualify households for the 36% rate.**
  The code already determines who dies second (`IHTCalculationService.php:288-296`); the
  test must run against **that** person's will. **This is a modelling ruling — mapping a
  statute onto a construct the statute does not have — and it should carry a product
  sign-off, not just an engineering fix.**
  Sarah's *"Current Charitable Rate 0%"* shown while her £10,000 is deducted is the
  `IHTProfile.charitable_giving_percent` path (no row → 0) disagreeing with the bequest
  path. **W-0132 owns that; not duplicating it here.**

- 2026-08-21 tax-compliance-reviewer: **W-0136 — CONFIRMED, and it is INDEPENDENT of
  W-0137. That is the question team-lead asked and it has a clean answer.**
  **The taper is never applied to the projection at all.** `calculateProjectedValues()`
  reuses the current-estate result unchanged — `IHTCalculationService.php:379`:
  `$totalAllowances = $nrbAvailable + $rnrbData['rnrb_available'];` — where `$rnrbData` was
  computed at `:158` from **`$totalNetEstate`**, the *current* estate. The £2,000,000 test
  is run once, against £1,234,280, and its answer is carried forward. **Even with a
  perfectly correct projected estate the taper would still not fire.** So W-0136 is not
  downstream of W-0137.
  Arithmetic confirmed exactly: 2,343,680 − 2,000,000 = 343,680 · × 0.5 = **171,840** ·
  350,000 − 171,840 = **£178,160** · understatement at 40% = **£68,736**.
  The footnote asserting *"below the £2,000,000 taper threshold"* is `rnrb_message` built in
  the **untapered** branch at `:1256` and displayed beside a £2.34m column.
  **Magnitude caveat:** £178,160 depends on £2,343,680 being right, and W-0137 makes that
  input untrustworthy. **The defect is independent; the number is not yet bankable.**

- 2026-08-21 tax-compliance-reviewer: **W-0136 is one instance of a general defect, and
  fixing only the taper will leave the rest.** **Every rate and allowance test is evaluated
  once against the CURRENT estate and reused unchanged for the projection** — the taper, the
  nil rate band, and **the charitable 10% test**. A household on 36% today carries that rate
  to death even though the baseline roughly doubles while a fixed cash legacy does not: at a
  projected £2.34m estate the baseline is ~£1.8m and the 10% threshold ~£180,000, which a
  £10,000 legacy cannot meet. **The projection reports a reduced rate the projected estate
  would not qualify for.**
  **And a distinct new bug in the same method.** `:380`:
  `$projectedCharitableAmount = $projectedNetEstate * $charitableFraction;` where
  `$charitableFraction` is the charitable amount over the **current** net estate. For David
  that is 10,000 ÷ 1,234,280 = 0.81%, applied to £2,343,680 → **£18,988**. **A fixed
  `specific_amount` cash legacy of £10,000 is inflated to £18,988 in the projection.** A
  specific cash legacy does not grow with the estate; only a `percentage` bequest does. The
  scaling is right for one bequest type and wrong for the other, and the code applies it to
  both.

- 2026-08-21 fix-batch-E (retirement/profile inputs, upstream half — **investigation
  only, no code changed, no database writes**): **the negative projected estate is an
  input defect, not estate arithmetic.** Seven findings, all upstream of
  `IHTCalculationService`'s own logic. Read-only queries; the personas were being driven
  in a browser and other suites were live.

  **R1 — CRITICAL. The estate's retirement income projection reads no pension at all.**
  `getRetirementIncome()` (`IHTCalculationService.php:816-847`) sums exactly two things:
  `retirement_profiles.target_retirement_income`, and a state pension figure from a
  column that does not exist (R2). **There is no Defined Contribution drawdown and no
  Defined Benefit pension income in it.** David's £500,000 across two Defined
  Contribution pots and Sarah's £35,000 NHS Pension Scheme contribute **nothing** to the
  income side of the projection that produces the estate.

  **R2 — CRITICAL. Three phantom columns, each swallowed by `?? 0`.** Verified against
  the live schema, and verified to have no accessor on the model:
  - `$statePension?->estimated_annual_amount` (`:827`, `:842`). `state_pensions` has no
    such column — the real one is `state_pension_forecast_annual`. **State Pension income
    is always £0 in the estate projection.**
  - `$user->state_pension_age` (`:824`, `:834`). `users` has no such column. Always falls
    to `DEFAULT_STATE_PENSION_AGE = 67`, and `state_pensions.state_pension_age`, which
    *does* exist, is never read.
  - `$pension->expected_annual_pension`. `db_pensions` has no such column — the real ones
    are `accrued_annual_pension` and the derived `projected_annual_pension_at_nra_gbp`.
    **Two consumers:** `EstateAssetAggregatorService.php:192` (the `annual_income` it
    hands the estate for income projections) and `HouseholdPlanningService.php:792`.

  **R3 — W-0030's fix is inert on the household path.**
  `HouseholdPlanningService::calculateDBPensionSpouseBenefit()` (`:786-797`) multiplies
  `spouse_pension_percent` — the unit convention `fix-batch-C` corrected today with a
  migration — by `expected_annual_pension`, which is always null. **Every Defined Benefit
  spouse benefit computes as £0.** Fifty per cent of nothing. The W-0030 correction
  cannot be observed through this path at all.

  **R4 — this is where the negative projected estate comes from.** Verified read-only:
  **neither David (16) nor Sarah (17) has a `retirement_profiles` row**, and neither has
  a `state_pensions` row. So retirement income in the projection is **£0** for this
  household — R1 means the pensions cannot supply it, R2 means the State Pension cannot
  either, and there is no profile target. Meanwhile `getRetirementExpenses()` falls back
  to `getUserAnnualIncome() × RETIREMENT_EXPENDITURE_FALLBACK_RATIO`: **£72,500** a year
  for David, **£60,000** for Sarah, inflating, from a retirement age of 60 (taken from
  `users.target_retirement_age`) to a second death in the mid-eighties. Roughly
  twenty-six years of five-figure expenses against zero income. **That mechanism is
  established and is more than sufficient to drive the estate negative.** I have not
  reconciled it to Sarah's exact −£185,535.84 — the arithmetic from that point on is
  `IHTCalculationService`'s own and belongs with F8, not here.

  **R5 — Rule 2 and Rule 20: the app holds two different answers to "what will you spend
  in retirement".** `RequiredCapitalCalculator` reads `retirement.target_income_percent`
  = **0.75** from `TaxConfigService` (`:130`, seeded at
  `TaxConfigurationSeeder.php:990`). `IHTCalculationService::RETIREMENT_EXPENDITURE_FALLBACK_RATIO`
  = **0.50**, hardcoded (`:48`). Same household, same question, two figures, and the
  estate one is not config-read.

  **R6 — Rule 20: a third retirement-age default.** `IHTCalculationService::DEFAULT_RETIREMENT_AGE`
  = **68** (`:38`). `PensionProjector::DEFAULT_RETIREMENT_AGE` = **67** (`:25`) and
  `DBPension::DEFAULT_NORMAL_RETIREMENT_AGE` = **67** — and those two are 67 *deliberately*:
  `fix-batch-C` aligned them for W-0036 so that "a pension cannot count as income from one
  age while being projected forward from another". The estate service is the third value
  and was not part of that alignment.

  **R7 — the life expectancy override is honoured everywhere except the estate.**
  `FutureValueCalculator::getLifeExpectancy(User)` honours `users.life_expectancy_override`
  (`:39-49`). `getLifeExpectancyYears(int $age, string $gender)` (`:73`) cannot — it never
  sees the user — and that is the one `IHTCalculationService::calculateLifeExpectancy()`
  calls (`:1399`). `retirement_profiles.life_expectancy` is likewise read by
  `RetirementAgent.php:178` and `DecumulationController.php:62` and not by the estate. A
  user who states when they expect to die sees it respected in retirement and decumulation
  and silently ignored in their inheritance tax projection.

  **Other hardcoded assumptions in the same input path**, listed for the Rule 2 sweep
  rather than fixed: `$currentAge ... : 50` (`:287`), `$yearsUntilDeath = 25` (`:306`),
  `: 80` (`:309`), `return 25` (`:1393`), `DEFAULT_STATE_PENSION_AGE = 67` (`:40` — a
  legislated figure, so `TaxConfigService` territory), `EXPENDITURE_FALLBACK_RATIO = 0.70`
  (`:45`), `DEFAULT_PROPERTY_GROWTH_RATE = 3.0` (`:42`).

  **Not fixed, deliberately.** R1, R2 and R5–R7 land inside `IHTCalculationService`, which
  `tax-compliance-reviewer` has just audited; R2's third instance and R3 are in
  `EstateAssetAggregatorService` and `HouseholdPlanningService`. Sequencing is the team
  lead's call so two agents do not converge on one file. **One judgement flagged before
  anyone codes R2:** the replacement for `expected_annual_pension` is not obvious —
  `accrued_annual_pension` holds the figure at Normal Retirement Age (W-0036 established
  the form labels it "Annual Income at Retirement"), and `projected_annual_pension_at_nra_gbp`
  is the derived, revalued version of the same thing. For a projection *to* retirement the
  derived column is the better source, but it is `PensionDerivedColumnCalculator`'s output
  and null until a write triggers recalculation — so a fallback chain is needed, not a
  straight swap.

- 2026-08-21 team-lead: **CORRECTION — this item's deprecation attribution is wrong and
  is now known to be wrong. Do not chase it as written.**

  The Intent above records the `Using null as an array offset` deprecation as reached via
  `getCompleteProfile()` on the estate path, inheriting `fix-batch-C`'s note in `F-0001`.
  **`tax-compliance-reviewer` reproduced it and the cause is different.**

  It is **`->with('jointOwner')` where `joint_owner_id` is NULL** — four sites:
  `PropertyStore:57`, `MortgageStore:67`, `SavingsStore:67`, `InvestmentAccountStore:62`.
  It fires for **any user holding an individually-owned record**, which is nearly everyone.

  **It is framework-level** — Laravel's `BelongsTo::match()` doing `isset($dictionary[null])`
  — so the remedy is an upgrade or a suppression, **not an app change per store.** And
  whoever takes it must **set `LOG_DEPRECATIONS_CHANNEL` first**, because
  `config/logging.php:35` discards deprecations by default.

  **Why the correction matters more than the finding:** the five `getCompleteProfile()`
  callers are **not** on the Inheritance Tax path, so anyone chasing the attribution as
  written would find no reproduction and conclude the deprecation does not happen — which
  is exactly where the auditor was two hours ago before running the aggregator directly.
  **A wrong attribution is worse than no attribution, because it terminates the search
  with a false negative.**

- 2026-08-21 team-lead: **`HouseholdPlanningService` is worse than the audit's F6 records
  and the fix is being folded into `fix-batch-E`'s in-flight work.**

  `$ihtConfig['rate'] ?? 0.40` appears at **two** sites — `:278` and `:968` — not one. And
  `inheritance_tax.rate` is **NULL**, verified live. **So every Inheritance Tax figure that
  path produces uses a hardcoded 40% that no configuration change can move**, which is a
  Rule 2 breach with a live consequence rather than a latent one: the value in
  `TaxConfigService` is not merely unread, it is unreachable. `/2` is also still hardcoded
  at `:980`.

  Also standing from the same re-check: `EstateAssetAggregatorService` applies Business
  Property Relief as **binary 100% with no £2.5m cap** (`:116-124`, `:137`).

- 2026-08-21 fix-batch-E: **R2 and R3 fixed and green** (sequenced by team-lead; R1 and
  R5–R7 held, `IHTCalculationService` closed to me while `fix-batch-G` works F1/F2/F3).

  **R3 — `HouseholdPlanningService::calculateDBPensionSpouseBenefit()`.** It multiplied
  `spouse_pension_percent` by `expected_annual_pension`, a column that has never existed,
  so **every Defined Benefit spouse benefit in the application was £0** — a percentage of
  nothing. The user-facing consequence is sharper than the payload suggests:
  `income_impact.db_spouse_benefit` is rendered at `DeathOfSpouseScenario.vue:102-104`
  **behind a `> 0` guard**, so the row never appeared. A surviving spouse was silently
  never told about pension income they would actually receive. And a fix landed, tested
  green and was invisible in the product: `fix-batch-C` corrected the
  `spouse_pension_percent` unit convention with a migration the same day, and this
  multiplied the corrected percentage into a null.

  **Fixed by reading the column the store already computes, not by swapping a name.**
  `PensionDerivedColumnCalculator::calculateDb()` (`:96-111`) already writes
  `spouse_pension_projected_gbp` as the annual pension times the spouse percentage —
  byte-for-byte the arithmetic this service was hand-rolling. It is preferred now, so the
  widowhood scenario and the pension record cannot disagree about one figure (Rule 20).
  The fallback chain is `projected_annual_pension_at_nra_gbp ?? accrued_annual_pension`,
  because the derived column is only written when a write triggers recalculation and
  every row predating that is null — a one-word column swap would have replaced "always
  zero" with "zero until someone happens to re-save the record", which looks fixed and is
  not. **That null-derived case is pinned explicitly**, since it is the state most
  existing rows are in. Sarah Jones (17) verified read-only: `accrued` 35,000,
  `projected_at_nra` 35,000, `spouse_pension_percent` **NULL**,
  `spouse_pension_projected_gbp` **NULL** — she goes from £0 to **£17,500**.

  **R2 — `EstateAssetAggregatorService.php:192`**, the same phantom column, same fallback
  chain. **Stated plainly rather than dressed up: nothing reads this field.** No consumer
  of `gatherUserAssets()` touches `annual_income` on a `db_pension` row — verified by
  grep across `EstateAgent`, `AssetLiquidityAnalyzer`, `EstatePlanService`,
  `GiftingController`, `ComprehensiveEstatePlanService` and `IHTController`. It is
  corrected so the field is truthful when something does read it, rather than being wrong
  the day it is wired up. The reason nothing reads it is R1: the estate's retirement
  income projection reads no pension at all.

  **The "make the absence loud" instruction — answered honestly, and one half could not
  be met.** For R2 a real zero and an unavailable figure *are* distinguishable and the
  fallback chain now says so. For R3 they are **not**: the method returns a `float` into
  `income_after` / `income_lost`, and a scheme with no recorded amount contributes the
  same `0.0` as a scheme that genuinely pays a spouse nothing. **The payload has no way
  to express "not known" and I did not invent one** — adding an unread field would repeat
  the disease this item is full of. It is commented at the call site and recorded here.

  **One thing deliberately NOT changed, and it needs a product decision.** The unstated
  spouse percentage defaults to **50%** (`DEFAULT_SPOUSE_PENSION_PERCENT`, previously an
  inline `?? 50`). Half is the common scheme rule and this is long-standing behaviour, so
  changing a user-facing figure was out of scope for a defect fix — but **the application
  does not agree with itself**: `PensionDerivedColumnCalculator::calculateDb()` returns
  **null** for an unstated percentage rather than assuming anything. Same question, two
  answers, and which one a household sees depends only on whether the derived column
  happens to have been written. Sarah is the live case: she is now told a
  precise-looking £17,500 that rests entirely on an assumed 50%, because her NHS scheme
  records no percentage — the field only reached the form in W-0017, today. **Recommend
  the derived calculator's semantics win** (unstated means unknown, and the fix is
  recording the real percentage), but that removes a figure users currently see, so it is
  CSJ's call, not mine.

  **Tests.** `HouseholdPlanningServiceTest` — **18 passed, 36 assertions** (7 added:
  derived-column preference proven with a value the fallback could not produce, the
  null-derived fallback, revalued-over-accrued, the assumed half share, no-amount-at-all,
  the carry into `income_after`/`income_lost`, and multi-scheme summing).
  `EstateAssetAggregatorDbPensionIncomeTest` (new) — **4 passed, 9 assertions**, including
  that the Defined Benefit pension still carries no estate value. Pint clean.

  **Not mine, failing in this tree:** `IHTRnrbAndCharitableExemptionTest` ×2 (the
  married-couple RNRB doubling). `IHTCalculationService.php` is modified by `fix-batch-G`
  and that path is exactly F2/F3. Verified my change cannot reach it — the test file
  contains **zero** references to `DBPension`, `db_pension` or `annual_income`.

- 2026-08-21 team-lead: **`branch:` corrected `F-0010` → `F-0012`. F-0010 was already taken**
  by `F-0010-batch-j-consolidation-red.md`, which carries four inbound references
  (W-0121, W-0122, W-0125, W-0126).

  **Caught before the duplicate file existed**, so unlike the two `F-0005` documents and
  the two `F-0009`s, there is nothing to renumber and no reference to update — the
  citation was wrong and the collision never happened.

  **The uncomfortable part, recorded because it is the point:** this was not an agent
  taking its own number at close-out. **The coordinator set this field, at 20:15, by
  reading what looked like the highest number — minutes before writing the rule into
  `FORMATS.md` that says nobody should do that.** Found by the Archivist within the hour,
  exactly as instructed.

  **That is not an argument against the rule, it is the argument for it.** The mechanism
  does not care who is running it; the person who had just decided nobody should choose a
  number by reading a directory then chose one by reading a directory. Which is precisely
  why the fix had to be *"issued at dispatch from a ledger"* rather than *"be careful"*.

- 2026-08-21 fix-batch-E: **the two hardcoded Inheritance Tax rate sites are fixed, and
  the diagnosis was not the one expected.** Folded into the R2/R3 work rather than raised
  separately, since the file was open.

  **`inheritance_tax.rate` is not a null key. It is the WRONG key, and it has never
  existed.** Dumped live from `TaxConfigService::getInheritanceTax()` on 2026-08-21: the
  array holds **`standard_rate` = 0.4**, and there is no `rate` member at all. That is
  the same disease as W-0154 R2's phantom columns — a name nothing answers to, with
  `?? 0.40` swallowing the miss. It also means the seeder needs no change: the value was
  configured correctly the whole time.

  `standard_rate` is unambiguously the canonical key — `IHTCalculationService:1392,2003`,
  `PersonalizedGiftingStrategyService:53` and `PersonalizedTrustStrategyService` (five
  sites) all read it. `HouseholdPlanningService` was the only reader asking for `rate`,
  at `:278` and `:968`. **So the consequence is as reported: every Inheritance Tax figure
  this service produced came from a literal that no configuration change could move —
  the rate was not merely unread, it was unreachable.**

  **Fixed once, not in lockstep.** Both call sites now go through
  `HouseholdPlanningService::inheritanceTaxRate()`, so the key is named in one place
  (Rule 20). The hardcoded `/ 2` residence-nil-rate-band taper at `:980` goes through
  `rnrbTaperRate()` reading `rnrb_taper_rate` — configured, and already read by
  `IHTCalculationService:1266`.

  **The fallback is loud.** Both helpers `report()` a `RuntimeException` naming the
  missing key before falling back — `TaxDefaults::IHT_RATE` for the rate, matching how
  `PersonalizedTrustStrategyService` falls back, and the statutory 0.5 for the taper.
  They still return a sane figure rather than throwing at a user mid-calculation, but an
  unconfigured tax rate no longer becomes 40% in silence. That silence is precisely how
  this survived: correct-looking at the call site, correct-looking in the seed, wrong
  only where the two met.

  **The test harness mirrored the defect rather than catching it.**
  `createHouseholdService()` mocked `getInheritanceTax()` returning `'rate' => 0.40` —
  the same key the service asked for and the configuration has never had. Both agreed,
  and the suite stayed green. The mock now returns `standard_rate` and `rnrb_taper_rate`,
  and takes overrides, so three new tests **change the configured rate and require the
  answer to follow**. A regression to a hardcoded literal now fails.

  **Tests:** `HouseholdPlanningServiceTest` — **21 passed, 41 assertions** (3 added for
  the rate and taper on top of the 7 for R3). Full sweep of
  `tests/Unit/Services/Coordination`, `tests/Unit/Services/Estate` and
  `tests/Feature/Estate`: **491 passed, 1,649 assertions, zero failures** — the two
  `IHTRnrbAndCharitableExemptionTest` failures reported at 20:20 are now green, so
  `fix-batch-G`'s in-flight work has settled. Pint clean.

  **Raised, not built: W-0091** — Business Property Relief is applied as binary 100% with
  no cap (`EstateAssetAggregatorService.php:114-141`) while a full relief regime sits
  configured and unread (`allowance_cap` £2,500,000 with an effective date four months
  past, `relief_above_cap` 0.5, `excluded_businesses`, `min_ownership_years`,
  `aim_shares_outside_cap`, `cap_transferable_to_spouse`). Assessed as a **relief model,
  not a two-line guard**: a cap needs *partial* relief, and `is_iht_exempt` is a boolean
  every consumer rejects on, so honouring it changes the shape of what the aggregator
  publishes and every consumer that reads it. Left rather than built inside a
  phantom-column fix, per instruction.

- 2026-08-21 team-lead: **Claim released on stand-down. PARTIALLY FIXED — read this before
  re-claiming, because most of the critical half is done and verified.**

  **DONE and browser-verified:** F1 (per-person inputs read from the logged-in user while
  assets are pooled) is fixed at **all four sites** — profiles, gifts, rate/charitable, and
  bequests — and independently checked as using the better shape throughout: **no site was
  made household-aware internally; the callers pool.** F3 (allowances doubled with data
  sharing off) closed as a side effect via `pooledMembers()`. The survivor split is
  implemented — pooled for the exemption, survivor's will only for the 10% test.
  **`persona-passA3` verified both accounts show taxable £846,780 and tax £338,712,
  matching an independent hand-computation to the pound.**

  **NOT done, in priority order:**
  1. **The charitable proportional-scaling bug.** A fixed £20,000 of cash legacies is
     inflated to £50,891 in the projection. **This is a trap: fixing the taper alone lands
     at £1,527,004, which looks correct beside today's £1,387,004 and is £12,356 wrong.**
     Both or neither.
  2. **W-0134** — the two-block layout. The charitable exemption **must not** join the
     allowance block; it reduces the estate's value and is not an allowance. The £150,000
     gift deduction is the missing row **inside** the allowance block. And the £325,000
     spouse row is **a modelled second-death transfer, not an allowance held today** —
     labelling it is the fix, the column adding up is the consequence.
  3. **W-0135** — the two screens are £40,802 apart and **the gap scales with the estate.**
  4. **`charitableBequestFingerprint()`** — the fourth F1 site, still `where('user_id', ...)`.
     Inert while `persist` is never true; **live the moment W-0131 is fixed.** Two lines.

  **Every line number written before 20:16:23 is stale** — the file moved when F1–F3
  landed. Re-grep rather than trusting any coordinate in this item or the audit report.

- 2026-08-21 cycle1-estate (build-lead): **three of the four items on the stand-down's
  "NOT done" list are done. Branch document `F-0015`; W-0134, W-0135 and W-0136 are at
  `handoff` to quality-lead. Item 4 remains.**

  **1. The charitable proportional-scaling bug — FIXED, and the trap was real.** The
  stand-down's warning is confirmed arithmetically: taper alone lands at £1,527,004
  beside today's £1,387,004, which reads as a successful fix and is £12,356 wrong. Both
  landed together. `$charitableFraction` is deleted rather than corrected — the fix is
  to **re-ask** `getCharitableBequestTotal($member, $projectedNetEstate)`, which already
  distinguishes a `percentage` bequest from a `specific_amount` one, instead of scaling
  its answer. A fixed £20,000 stays £20,000; a percentage legacy still grows.

  Measured on David (16), read-only: **projected tax £1,539,360.30**, the tester's
  hand-computed figure, with the current column untouched at **£338,712** on both
  accounts.

  **The wider finding this confirms is `tax-compliance-reviewer`'s, not mine:** the
  taper, the 10% rate test and the charitable exemption were all evaluated once against
  the current estate. All three now run through one mechanism, `assessTaxPosition()`
  (`IHTCalculationService.php:561`), called at `:241` with today's estate and `:506`
  with the projection.

  **2. W-0134 — DONE, and the previous fix was correct and shipped dark.**
  `fix-batch-G`'s gift-deduction row was already in the template and
  `standardTableProps` already passed it. It never rendered because the hand-written
  mapping in `IHTPlanning.vue:loadIHTCalculation()` dropped `nrb_gift_deduction` and
  `nrb_spouse_modelled` between the payload and the props. Worth recording next to the
  F2 note: the row existed, the field existed, and a third piece of hand-written
  copying in between made both invisible.

  **3. W-0135 — DONE.** `EstatePlanService` recomputed the projected taxable estate and
  tax itself, ignoring the exemption, while `IHTController` let the service's stand.
  Recompute deleted. **But the £40,802/£103,206 "gap that scales" is not the two
  screens** — it is the two LOGINS, and it is now located precisely:
  `projectCashWithInflation()` loops from the **logged-in user's** age to the
  **second-death age of whoever dies later**, two age scales in one loop, so David
  iterates 35 years and Sarah 36 against a shared `years_to_death` of 36. The whole
  £103,206 sits in `projected_cash`. That is W-0137's mechanism; fixing it moves the
  projected estate and therefore the £1,539,360 pin, so it is raised rather than taken.

  **4. `charitableBequestFingerprint()` — STILL `where('user_id', ...)`, untouched.**
  Confirmed still the fourth F1 site and still inert while `persist` is never true at
  any of the five call sites. **One thing changed near it:** `getCachedCalculation()`
  now rejects a stored result that predates the projected allowance fields
  (`isCurrentResultShape()`, `:1859`), because the hashes fingerprint the data and say
  nothing about the code that produced the row. Whoever fixes W-0131 will therefore not
  resurrect a pre-fix answer — but they must still fix the fingerprint's `user_id`
  scope in the same change, because that one goes live the moment persistence does.


## Working note — 2026-08-23, added by build-lead (`fix-cycle4-figures`) under W-0451 verdict condition C6

**The two entry points derive the pooling predicate differently, and as of today
the charitable figures depend on it.**

| Entry point | Predicate |
|---|---|
| `app/Http/Controllers/Api/Estate/IHTController.php:52` | `liveSpouseId() !== null && hasAcceptedSpousePermission()` |
| `app/Agents/EstateAgent.php:146` | `$spouse !== null` |

A household where the two disagree — a linked spouse whose data-sharing
permission is **not** accepted — gets a **pooled** calculation on `/plans/estate`
(via `EstateAgent`) and an **individual** one on `/estate` (via `IHTController`).
Different net estate, different available nil rate band, **different Schedule 1A
baseline, different threshold, a different SURVIVOR, and a different saving.**

**Pre-existing, not introduced by W-0451/W-0452** — but **newly load-bearing**,
because before that batch `/plans/estate`'s charitable panel did not come from
`$ihtCalculation` at all. The batch consolidated the charitable formula into one
home; **it did not consolidate the predicate that feeds it**, so "one figure on
three surfaces" holds only while the two entry points agree about who is in the
household.

**Not reachable on today's data.** The reviewer enumerated all **12 linked users**
in the local database: every one is mutually `married` with a reciprocal
`spouse_id` and an accepted permission, so the two predicates agree everywhere.
**Reachable by any household that links a spouse and does not accept sharing.**

**This belongs to W-0154's first acceptance criterion** — *"One household produces
one answer"* — because it is the same defect at the predicate layer rather than
the arithmetic layer. Whoever claims W-0154 should consolidate the predicate, not
merely align the two call sites.


## Resolution — 2026-08-23

**Closed on the fix side. Two of the three defects were already fixed and the board
item was stale;** the pooling work (F1/F3) landed in the cycle-4 snapshot committed as
`5de82a7fd` this morning without this item being updated. Verified before touching
anything, through the real endpoint path (`liveSpouse()` +
`hasAcceptedSpousePermission()`, as the working notes insist).

### Measured, David (16) and Sarah (17), before any change today

Identical on both sides: net estate £1,728,780, liability £343,512. **The £60,000
household gap is gone** — `calculateNRBDeductionForGifts()` now takes `$pooledMembers`,
so David's £150,000 chargeable lifetime transfer reduces the band in both views, and
the household's two £10,000 charitable legacies are deducted as £20,000 either way
round. (The figures differ from the ones in this item because other cycle-4 fixes moved
the estate — the defined-benefit exclusion and the mortgage-share ruling. The
reviewer's £145,712 is stale for the same reason.)

### What was still broken, and what was done

**1. The residence band did not reconcile.** `rnrb_individual` £175,000 +
`rnrb_transferred` £0 was published beside `rnrb_available` £350,000. The nil rate
band had been given `nrb_spouse_modelled` and `nrb_gift_deduction`; the residence band
was left with neither.

Added `rnrb_spouse_modelled`, `rnrb_residence_cap_reduction` and
`rnrb_taper_reduction`, at all five return points of `calculateRNRB()`, current and
projected, through `IHTController`, `EstatePlanService` and `IHTPlanning.vue`. Same
reasoning as the nil rate band and stated in the code: there is no transferable
residence band while both spouses live (IHTA 1984 s8G), so `rnrb_transferred` is
legitimately 0 and the doubling is a second-death modelling assumption. **Not** fixed
by writing £175,000 into `rnrb_transferred`.

`IHTCalculationTable.vue` was rendering each spouse's home allowance as
`totalRnrb / 2` — the total halved and presented as two measured components. That
reconciles only while the halves are equal, and they stop being equal the moment the
cap or the taper bites. It reads the real components now.

**2. A reduced rate on a nil liability.** Priya (20) was told *"Reduced Inheritance Tax
rate of 36% applies"* beneath a bill of £0. `determineIHTRate()` has to run before the
taxable estate exists, because the charitable exemption it identifies is one of the
deductions that produces it, so its answer was published verbatim.

`suppressRateOnNilLiability()` applied in `assessTaxPosition()` — the ONE mechanism both
the current and projected figures pass through (Rule 20). A guard at a display site
would need adding again at the next one, which is exactly how `EstatePlanService:478`
came to carry an unguarded copy; that copy now defers to the calculation rather than
reaching its own verdict. `charitable_rate_qualifies` and the shortfall guidance are
deliberately untouched — whether the will passes the 10% test is a true fact about the
will whatever the bill is.

**3. `nrb_deduction` remains an array.** Left alone deliberately: the scalar the payload
needed is `nrb_gift_deduction`, which exists and is rendered. `nrb_deduction` has no
consumer anywhere in the repository (reviewer, Q5a), so changing its shape changes
nothing and risks a consumer nobody has found.

### Verified in the browser, David's account, `/estate/inheritance-tax`

Both columns add up on screen, which is what acceptance 2 asks for:

| Row | NOW | AGE 85 |
|---|---|---|
| David's Tax-Free Allowance | −£325,000 | −£325,000 |
| Sarah's Tax-Free Allowance *(modelled on second death)* | −£325,000 | −£325,000 |
| Less allowance used by gifts in the last 7 years | +£150,000 | +£150,000 |
| David's Home Allowance | −£175,000 | −£175,000 |
| Sarah's Home Allowance *(modelled on second death)* | −£175,000 | −£175,000 |
| Less home allowance reduced by the size of your estate | +£0 | +£350,000 |
| **Subtotal** | **−£850,000** | **−£500,000** |

The £350,000 the taper removes has its own labelled row instead of being a residual
the reader had to infer. **A regression was caught here and fixed:** the first browser
pass showed Sarah's home allowance as £0 and the NOW column summing to £675,000 —
`IHTController` was not publishing the new fields, so the frontend had nothing to read
where the halving used to be. Found by looking at the screen, not by the tests.

## Acceptance

- [x] One household produces one answer — £343,512 both sides.
- [x] Every allowance component reconciles to its total, in the payload and on screen.
- [x] The doubled bands are attributed as modelled second-death transfers, not hidden
      inside a total with `transferred: 0`.
- [x] No reduced rate reported where the liability is nil — Priya now reports rate type
      `none` and "No Inheritance Tax is due".
- [x] The gift deduction is a scalar (`nrb_gift_deduction`) and the seven-year window
      now reads from `TaxConfigService` rather than a hardcoded `subYears(7)` (W-0463).
- [x] A negative projected estate — no longer reproducible; the projection floors at
      zero and both personas return positive figures.
- [x] **Every figure hand-checked against `tests/Persona/peak_earners.md`** — done
      2026-08-29, and it found a live regression. See the working note below.
- [x] `/m` checked rather than assumed (Rule 19) — see below.

### `/m` and iOS

`/m` displays **no allowance breakdown at all**, so there is no second surface for the
reconciliation fix; and in full (Premium) mode it shows no Inheritance Tax liability
either. The only Inheritance Tax figure `/m` ever renders is the Free-tier teaser,
which is computed by a **second, independent calculation** — filed as **W-0464**, not
fixed here, because consolidating it changes what a whole pricing tier is shown.

**iOS: not built, not launched, not checked.**


## Tax-compliance verdict

`workforce/ops/handoffs/W-0463/tax-compliance-reviewer-verdict-2026-08-23.md` — two rounds,
26 findings, with legislation and HMRC manual citations. Recorded there because the
reviewer wrote nothing to disk; without that file both reviews would have been lost.

- 2026-08-29 build-lead: **the last criterion is closed, and doing it found a £70,000
  defect.** Every figure was derived by hand from `tests/Persona/peak_earners.md` and
  compared against `IHTCalculationService::calculate()` driven the way `IHTController`
  drives it — `liveSpouse()` plus `hasAcceptedSpousePermission()`, per the near-miss
  recorded above.

  | Figure | Hand-computed from the persona file | Engine |
  |---|---|---|
  | `total_gross_assets` | 1,393,000 property + 130,780 savings + 305,000 investments + 193,000 chattels = **2,021,780** | 2,021,780 |
  | `total_liabilities` | 65,000 + 180,000 + 48,000 (40% of the Manchester mortgage) = **293,000** | 293,000 |
  | `total_net_estate` | **1,728,780** | 1,728,780 |
  | `user_net_estate` David | **989,500** | 989,500 |
  | `user_net_estate` Sarah | **739,280** | 739,280 |
  | `nrb_gift_deduction` | the 2020 settlement, **150,000** | 150,000 |
  | `nrb_available` | 325,000 + 325,000 − 150,000 = **500,000** | 500,000 |
  | `rnrb_available` | 175,000 + 175,000 = **350,000** | 350,000 |
  | `total_allowances` | **850,000** | 850,000 |
  | `taxable_estate` | 1,728,780 − 850,000 − 20,000 charitable = **858,780** | 858,780 |
  | `iht_liability` | **343,512** | 343,512 |
  | `iht_rate_percent` | 20,000 given against a Schedule 1A baseline of 1,228,780, so under the 10% test — **40** | 40 |

  **The split reconciles because the persona file's own note says so.** David's share is
  £250,220 larger than Sarah's, and £50,000 of that is the Premium Bonds: NS&I bonds
  cannot be held jointly, the application correctly refuses it, and the persona file
  records them as David's individual holding (note added 2026-08-21). Reading the
  headline "Ownership: Joint" instead would have produced a £25,000 discrepancy and a
  defect report against correct behaviour.

  **Pensions are correctly absent.** The £180,000 workplace pot, the £320,000 SIPP and
  Sarah's defined benefit scheme are outside the estate for 2026/27.

- 2026-08-29 build-lead: **the regression this check caught.** The household was
  measured at `iht_liability` **413,512**, not 343,512 — **£70,000 too much** — because
  the development database held **four identical £150,000 chargeable lifetime transfers**
  for one trust, written on four separate seeder runs (21, 24, 24 and 28 August). Four
  transfers capped the gift deduction at the whole £325,000 band instead of £150,000.
  `PremiumTestPersonaSeeder::purgeHouseholdData()` force-deleted `Trust` and never
  listed `Gift`, and a query-builder delete fires no model events, so `TrustObserver`
  never cleaned up and the next `Trust::updateOrCreate()` wrote another settlement.
  Fixed under **W-0528**; the `gifts.trust_id` foreign key is what actually prevents it,
  the purge-list entry being belt-and-braces.

  **£343,512 is the figure this item's own earlier verification recorded**, which
  confirms the household was right when it was checked and drifted afterwards — the
  exact failure mode a one-off verification cannot catch and a test can.

- 2026-08-29 build-lead: **locked** by `tests/Feature/Estate/PeakEarnersPersonaFiguresTest.php`
  — seven tests, every expectation derived in the docblock from the persona source so a
  reader can check the derivation rather than trust the number.

- 2026-08-29 build-lead: **still open — iOS.** Not built, not launched, not checked, as
  the note above says. `/m` remains as recorded: no allowance breakdown to reconcile,
  and its only inheritance tax figure is the Free-tier teaser computed by a second
  implementation, filed as W-0464.

- 2026-08-31 build-lead: **VERIFIED against `dev` — F1, F2 and F3 are all fixed on web,
  and the residual is iOS verification only.** `IHTCalculationService:153` holds the
  single decision about whose records the calculation covers (F1/F3); `:329` doubles the
  allowances on `$poolsSpouse` rather than `$isMarried`, and `:474`/`:490` publish the
  five components that now reconcile (F2); `IHTController:123-135` carries them into the
  summary. Every figure is locked by
  `tests/Feature/Estate/PeakEarnersPersonaFiguresTest.php` — household £1,728,780,
  bill £343,512.

  **Left open deliberately, and it is no longer a critical calculation defect.** What
  remains is the iOS surface, never built or launched against this, plus `/m`'s missing
  allowance breakdown — which is filed separately as **W-0464** and should not be counted
  twice. On the evidence the severity now describes a surface-coverage gap, not two
  different tax bills for one household.

- 2026-08-31 build-lead: **CLOSED. The critical defect this item names — one household, two
  different Inheritance Tax bills — does not exist in the code.**

  Re-verified today and the seven locking tests re-run green:
  `tests/Feature/Estate/PeakEarnersPersonaFiguresTest.php`, 7 passed / 20 assertions,
  including *"it gives one household one answer, whichever spouse is logged in"* and
  *"it reports allowance components that add up to the totals beside them"*. Household
  £1,728,780, bill £343,512 — the hand-computed figures from `tests/Persona/peak_earners.md`.

  The mechanism is single: `IHTCalculationService:153` holds the one decision about whose
  records the calculation covers; `:329` doubles the allowances on `$poolsSpouse`; `:474`/`:490`
  publish the five reconciling components; `IHTController:123-135` carries them into the summary.

  **The residual is surface coverage, not arithmetic, and it is already filed elsewhere:**
  `/m`'s missing allowance breakdown and its independent Free-tier teaser calculation are
  **W-0464**, and iOS has never been built against this. Neither is a wrong bill. Keeping this
  item open to hold them double-counts W-0464 and mis-states a `critical` calculation defect
  that has been fixed.
