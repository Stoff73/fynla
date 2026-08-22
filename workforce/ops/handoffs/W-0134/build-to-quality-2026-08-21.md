# W-0134 (with W-0134 / W-0135 / W-0136) — build-lead (`cycle1-estate`) → quality-lead

One handoff for three items because they were one dispatch and one code change.
Branch document: `branches/fixes/F-0015-cycle1-estate-tax-figures.md`.
Duplicated to `handoffs/W-0134/` and `handoffs/W-0135/` so each item resolves.

## Done

**W-0136 — the residence band taper reaches the projection.** One assessment,
`assessTaxPosition()` (`app/Services/Estate/IHTCalculationService.php:561`), run
against today's estate at `:241` and against the projected estate at `:506`. The
taper arithmetic was never wrong; it was never asked about this estate. The residence
cap is now fed a projected residence value from `projectMainResidenceNetValue()`
(`:614`) instead of a current one.

**Bundled with it, because fixing the taper alone lands on a plausible wrong answer:**
the charitable exemption is re-assessed against the projected estate rather than
scaled by projected ÷ current. A fixed £20,000 of cash legacies was being inflated to
£50,891. Taper alone would have produced £1,527,004 beside today's £1,387,004 — an
improvement that looks like a fix and is £12,356 wrong. The 10% charitable rate test
came with them, since it was the third test decided once against today's estate.

**W-0134 — the allowance column adds up.** The gift-deduction row already existed in
the template; the mapping in `IHTPlanning.vue:loadIHTCalculation()` dropped
`nrb_gift_deduction` and `nrb_spouse_modelled` on the way to it. Fourteen hand-written
rows became one row model with per-column values. The charitable exemption renders as
its own row **below** the allowance block and independently of the what-if toggle. The
spouse band is labelled as a modelled second-death transfer.

**W-0135 — one calculation, two surfaces.** `EstatePlanService` recomputed the
projected taxable estate and tax itself, ignoring the exemption; that is deleted. Its
hand-written allowance messages are replaced by the calculation's own.

**Measured read-only against users 16 and 17** (no writes; `persist` defaults false):
current column unchanged at **£338,712** on both accounts; David's projection lands on
**£1,539,360.30** with the residence band extinguished and the charitable deduction
holding at £20,000. `/plans/estate` returns byte-identical projected figures.

**Tests:** 17 backend (`tests/Unit/Services/Estate/IHTProjectedAssessmentTest.php`),
10 frontend (`tests/frontend/components/Estate/IHTCalculationTable.test.js`), 1 added
to `IHTCalculationPersistTest`. Regression sweep 1,046 green across estate, plans,
coordination, agents, dashboard, mobile, stores and the full frontend suite. Pint and
ESLint clean.

## Not done, and why

- **Not browser-verified.** Build does not write its own evidence and does not close
  its own loop (`08-process.md` §2.4). W-0134 acceptance 5, W-0135 acceptance 4 and
  W-0136's verification are yours.
- **Not committed, no PR, no deploy**, per dispatch.
- **The £103,206 between the two logins is NOT fixed.** The dispatch attributed it to
  the two screens; measured, it is the gap between the two logins (R-18 §2.6 item 3).
  Root cause located and evidenced in W-0135's working notes —
  `projectCashWithInflation()` mixes two people's age scales in one loop. Fixing it
  moves the projected estate, and therefore moves the £1,539,360 this cycle was pinned
  to, so it needs its own item and its own pin. It is W-0137's mechanism.
- **Five further findings raised, not built**, in `F-0015` §6: the seven-year gift
  window never expiring in the projection (needs a ruling, not a code change), a third
  projected-liability mechanism with a hardcoded age 70, the free-tier `/m` teaser as a
  fourth answer to "what do you owe", and `secondDeathTableProps` being unreachable.

## What you need that isn't obvious from the artefacts

**⚠️ Clear the cache before you measure anything.** `EstateAgent::analyze()` caches its
entire result (`estate_analysis_{userId}`, `BaseAgent::remember`). My first
`/plans/estate` read after this change returned the **pre-fix** figures — taxable
£3,467,510.13, tax £1,387,004.05, allowances £0 — straight from that cache.
`php artisan cache:clear` fixes it. Model writes are invalidated by
`RecommendationCacheObserver`, so this is deploy-time staleness rather than a defect,
**but if you measure without clearing you will read the old numbers and conclude the
fix failed.**

**The three figures to check by hand, expanded, on both accounts:**
`£325,000 + £325,000 − £150,000 + £350,000 = £850,000` in the Now column, and
`£325,000 + £325,000 − £150,000 + £0 = £500,000` in the Age 84 column. Then
`£1,716,780 − £850,000 − £20,000 = £846,780` and
`£4,368,401 − £500,000 − £20,000 = £3,848,401`.

**Sarah's projection is £1,580,642.90, not £1,539,360.** That is not a defect in this
work — it is the between-logins divergence above. Do not read it as a failure of the
taper fix; her residence band is extinguished too.

**The what-if toggle renders a second charitable row by design.** With "leave 10% to
charity" on, the modelled donation row appears and the actual-exemption row does not.
They are different quantities and must not be shown together.

## Assumptions I made

*(stated as assumptions, never as facts)*

- **I assumed the pinned £1,539,360 fixes the horizon**, and therefore that the
  seven-year gift window should keep reducing the projected band 36 years out. The
  tester's expected allowance of £500,000 implies the same, but nobody has ruled on it.
  If the ruling goes the other way the projected band is £650,000 and the tax
  £1,479,360.
- **I assumed the projected residence value should grow at the property growth rate
  with the mortgage amortised**, matching `projectProperties()` and
  `projectLiabilities()`. It does not change any figure for `peak_earners`; it changes
  every household with a modest home, and no spec states the intended treatment.
- **I assumed the exemption belongs outside the allowance block** on the strength of
  IHTA 1984 s23 and the dispatch's explicit instruction. It is a presentation decision
  with a legal justification, not a legal requirement about layout.
- **I assumed `EstatePlanService` should defer to the calculation** rather than the
  reverse. Its recompute reconciled to its own two printed rows, which is why it looked
  right; the calculation is the one that accounts for the taper and the exemption.
- **I assumed the standard table is what married users actually see.**
  `secondDeathTableProps` requires a `second_death_analysis` key the endpoint does not
  emit, so it returns null. If some other caller does emit it, that branch renders and
  I have only minimally updated it.

## Surfaces covered / not covered

- **web** — all four defects fixed. Needs your browser pass on both accounts.
- **`/m`** — the backend fixes reach it (same `IHTCalculationService` via
  `EstateAgent` → `MobileDashboardAggregator::extractEstateSummary`, `:264-281`), but
  **nothing on `/m` displays any figure these four defects changed.**
  `resources/mobile/views/modules/Estate.vue` shows net estate, asset composition and
  counts of gifts/trusts/wills; the premium view carries **no Inheritance Tax figure,
  no allowance itemisation and no projected column**. That is W-0138 and is unchanged
  here. The free teaser's figure comes from `EstateIhtExposureDetector`, a separate
  mechanism this work does not touch. **There is no `/m` regression to look for and no
  `/m` improvement to see** — I am naming that rather than reporting `/m` as verified.
- **iOS** — same position as `/m`; no counterpart screen. Not exercised.
