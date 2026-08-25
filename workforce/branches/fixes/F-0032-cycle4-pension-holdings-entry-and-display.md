---
id: F-0032
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web]
consistency_checked: 2026-08-23T04:20:00Z
status: active
---

# F-0032 — Cycle 4: a pension's holdings had no way in, and the table hid what it stored

**Agent:** build-lead (`fix-cycle4-retirement`) · **Branch:** `wip/persona-cycle4-snapshot`
**Board items:** W-0441 (D-17), W-0442 (D-12), W-0444 (the 500) · **ID block:** W-0441 – W-0450
**Number:** F-0032, announced to the team-lead before first write. F-0031 was the
highest in use.

**D-18 has no item of its own by instruction** — it is a working note on **W-0196**,
which owns the retirement-age question.

---

## 1. What the prior-art sweep changed

**D-17 read as "build pension holdings". It was almost entirely already built.**

| Layer | State before this batch |
|---|---|
| Endpoints | `DCPensionHoldingsController` — `index/store/update/destroy/bulkUpdate`, routed at `/api/retirement/pensions/dc/{id}/holdings` (`routes/api.php:1042-1046`) |
| Valuation | All five methods already read `App\Support\HoldingValuation` (W-0126) |
| Client service | `resources/js/services/dcPensionHoldingsService.js` — `createHolding`, `updateHolding`, `deleteHolding` written, **zero consumers**. The write half was dead code waiting for a caller. |
| The form | `HoldingForm.vue` already carries Units, Purchase Price, Current Price, Purchase Date and Ongoing Charge Figure (W-0039) |
| The tab | `PensionDetailInline.vue` already had a Holdings tab |

**The entire defect was one gate.** The tab was conditional on `hasHoldings`, so a
pension with no holdings had no tab, and with no tab there was no way to add one.
A closed loop that looked from the outside like a missing feature.

`prior_art_outcome: route` for the endpoints and the service, `extend` for the two
components. Nothing new was built that already existed.

### Queued items I did not build through

| Item | How it constrained this batch |
|---|---|
| **W-0321** (queued) — allocation total unenforced on write | No competing server-side allocation guard was added. |
| **W-0324** (queued) — `dividend_yield` has no nested rule | It says *"it becomes live the moment anything adds a yield input"*. The pension endpoint has no rule for it either, so the Dividend Yield input is **hidden** in pension context rather than offered and silently discarded. |
| **W-0322** (handoff, acceptance 3 and 4 open) — what an empty holdings array means | **Deliberately unchanged.** An empty array still names nothing and still clears everything. A test pins it so whoever settles that question changes it on purpose. |
| **W-0196** (queued) — the retirement-age chain | Only the component's display was fixed. The seven-constant consolidation remains W-0196's work. |

---

## 2. The three defects, and a fourth found on the way

### W-0441 — a pension's holdings could not be entered

`PensionDetailInline.vue`: the Holdings tab is now unconditional for defined
contribution pensions, with an empty state, an Add Holding control, and per-row
Edit and Delete. It writes through the existing service to the existing endpoints
— **per holding, no arrays**, so the `holdings: []` hazard that W-0322 raised
cannot arise on this path at all.

`HoldingForm.vue` takes an optional `owner` prop and serves both an investment
account and a pension. **One holding form, not a pension-shaped copy of one**
(Rule 20) — units, prices, purchase date and ongoing charge have one input each.

### The hazard this batch created, and closed

Making those fields enterable exposed a guaranteed loss. `DCPensionForm.vue:1021-1027`
maps stored holdings into the form as **five keys** — name, type, allocation,
ongoing charge, cost basis — and `RetirementController` then deleted every row and
rebuilt from that payload. Units, purchase price, current price, purchase date,
ticker and ISIN were dropped on the way in and annihilated on the way out. And
`hasAdditionalInfoData()` **auto-expands** that section whenever holdings exist, so
once a pension had holdings the destructive path was the **default** one: open it,
change a fee, press Update, units gone.

`seedHoldingsForDcPension` is now `syncHoldingsForDcPension`: rows the incoming set
still names are **updated in place**, rows it no longer names are deleted. Ids
survive, which also ends the churn W-0322 recorded (rows 62/63/64 soft-deleted with
65/66/67 created in the same second).

**One subtlety worth stating, because it is where the fix would otherwise undo
itself.** `current_value` is restated **only when the allocation percentage moved**.
Allocation is the one value-bearing fact this form can express; a row whose
allocation is unchanged has had nothing said about its value, and a form that cannot
see units must not revalue a row that has them. Restating it unconditionally put the
value back through `HoldingValuation::reconcile()`, which by W-0121 keeps the stated
figure and back-calculates the units — turning 4,211 units at £38.00 into 4,210.53
on a save that touched neither.

**A second behaviour change, named rather than buried:** the old `holdings()->delete()`
was a builder delete and raised no model events, so `UserDataCacheObserver` never
fired for a removed pension holding and its cache was never invalidated. The sync
raises them per row, so it now does.

### W-0442 — the holdings table hid what it stored

Two mechanisms, and they had different diseases.

**`Investment/HoldingsTable.vue` was dead code.** `:52` and `:135` opened two
consecutive `v-else-if="filteredHoldings.length > 0"` in one chain, so the second
could never be true. With holdings you got the donut and the legend; the table below
it had **never rendered** — nor its Purchase Price and Current Price columns, the
Purchase Date and ISIN in its expanded row, the cost basis, the return, or the
per-row Edit and Delete buttons. Untouched since 30 March, so not a recent
regression. Now one branch renders both.

**The pension table showed five columns and stored nine.** Units Held, Purchase
Price, Current Price and Purchase Date are added.

**Two display faults found while adding them:**

1. **Unit prices were rounded to whole pounds.** `formatCurrency` renders £80.50 as
   "£81"; the persona's L&G UK Property is £1.35 in and £1.28 now, and both rendered
   as **"£1"** — two different facts as one number. Both tables now use
   `formatCurrencyWithPence` for prices. Holding *values* stay whole-pound.
2. **The pension table recomputed the value from the allocation** instead of showing
   the stored one, so a holding storing £160,018 (4,211 units at £38.00) displayed
   £160,000, being 50% of the pot. The stored value now wins; the allocation stays
   as the fallback for rows that have none, and for the unallocated-cash footer.

**And the other half of D-17's symptom, which is not about holdings at all.** The
Overview Fees block read *"Platform Fee 0.00% · Total Annual Cost 0.00% · Annual Fee
Impact £0/year"* because `platform_fee_percent` is **NULL** on David's SIPP and every
reader coerces it with `|| 0`. That is not an absence, it is a claim that the platform
charges nothing — the `?? null` versus `|| 0` distinction in `app/Http/CLAUDE.md`. It
now says "Not recorded", and the totals appear only once something is on record.

### W-0444 — every not-found path on the holdings endpoints returned 500

`DCPensionHoldingsController:43` threw `ModelNotFoundException` **without importing
it**, so it resolved to `App\Http\Controllers\Api\Retirement\ModelNotFoundException`,
which does not exist. All five endpoints raised a fatal `Error` instead of a 404.

**Why nothing caught it: the Fixture variant.** `DCPensionHoldingValuationTest` gives
every case a pension the acting user owns, so the branch was never entered, and
nothing in the file says *"and no unowned pension is ever requested here"*. Fixed,
and six cases now enter it.

---

## 3. What I extended beyond the named scope, and why

Two things, both declared rather than quiet.

**`sub_type` on the pension holdings endpoint.** `HoldingForm` makes the user choose
a fund type whenever the asset type is Fund, and the endpoint validated no rule for
it — so `validated()` dropped it and the choice was reported as saved and never
stored. Wiring the form to the endpoint made that reachable, so it is part of this
fix rather than adjacent to it.

Adding the rule would have made a **third** copy of the sub-type vocabulary, which
Rule 20 names as the violation rather than the fix — the identical list was a private
`getSubTypes()` on both `StoreHoldingRequest` and `UpdateHoldingRequest`. It is now
`app/Constants/HoldingSubTypes.php` and all three read it.

**Accepted, NOT `required_if:asset_type,fund`.** I wrote it as `required_if` first,
matching the investment requests, and it turned an existing green 201 into a 422:
`DCPensionHoldingValuationTest` creates a fund holding with no sub-type. Those
requests serve one form that always sends it; this endpoint has other callers.
Refusing what a live path legitimately produces is the "column wider than the rule"
direction in `app/Http/CLAUDE.md`, where the answer depends on whether anything
offers the excluded value. Something does.

---

## 4. Rule 19 — the `/m` position, stated per defect

| Defect | `/m` |
|---|---|
| **W-0441** entry | **No counterpart exists, by architecture.** `resources/mobile/api.js` has no post, put, patch or delete helper anywhere — `/m` cannot write. Fyn is its only write path (as W-0324 records), and `CoordinatingAgent::handleCreateHolding` "carries no units parameter" (`:3284`). Nothing to build here without building a `/m` write path, which is not this batch. |
| **W-0441** sync | **Reaches `/m` for free** — one shared endpoint, server-side. |
| **W-0444** | **Reaches `/m` for free** — same. |
| **D-18** retirement age | **Already correct on `/m`.** `RetirementPensionDetail.vue` reads `pension.retirement_age` and shows an em dash when absent. Web was the outlier; it now says the same thing. |
| **W-0442** display | **NOT done on `/m`. Flagged, not skipped — see below.** |

### The one thing I did not do, and the reason

`/m` shows holdings through `CanonicalPortfolio.vue`, which renders name, value,
ongoing charge and estimated annual cost. Units, purchase price, current price and
purchase date are absent — **and cannot be added in the component**, because
`PortfolioPresentationService:100-131` never publishes them. That is the axis-7
publication gap in `app/Http/CLAUDE.md`: the component is right about what it was
given.

Fixing it means adding fields to a payload that carries a **`contract_version`**, and
that contract is read by `/m` and by native. **Extending a versioned contract is a
version decision, not a routine addition**, and both the service and the component sit
outside the exclusive scope I was given while another agent is live. Raised for the
team-lead rather than taken.

**No build artefacts produced.** `public/m-build/` and `public/build/` are untouched —
they are the team-lead's, and the `/m`-reaching half of this batch is backend-only in
any case.

---

## 5. Verification

**Backend — 367 passed** across `tests/Feature/Retirement`, `Api/RetirementControllerTest`,
`RetirementModuleTest`, `Unit/Support`, `Feature/Investment`, `Feature/Stores`, on
`DB_DATABASE=laravel_testing_u`. **Architecture 149 passed**, `Unit/Database` 30 passed.

**Frontend — 257 passed** across `components/NetWorth`, `components/Investment`,
`components/Retirement`, `holdingFormUnits`, `store/investmentHoldings`.

**New cover:** `DCPensionHoldingsSyncTest` (6), `DCPensionHoldingsOwnershipTest` (6),
`DCPensionHoldingFieldsTest` (2), `PensionDetailInline.test.js` (12), and 6 cases
added to `HoldingsTable.test.js`.

### Mutation testing — every fix, both directions

| Mutation | Reddened | Correct? |
|---|---|---|
| Restore `holdings()->delete()` before the sync | 4 of 6 sync cases | Yes. The two that stayed green are the no-holdings-key case (the sync is skipped, so the mutation is unreachable) and the empty-array case (identical under both — it is the reserved contract). |
| Force `$allocationMoved = true` | 2 cases | Yes — the two that assert a value the mutation moves. |
| Remove the `ModelNotFoundException` import | all 5 not-found cases | Yes. The owner case stayed green, which is what stops "return 404 unconditionally" satisfying the suite. |
| Remove the `sub_type` rule | 1 case | Yes — the only one asserting it. |
| Restore the duplicated `v-else-if` | 5 new table cases | Yes. **All 21 pre-existing `HoldingsTable` cases stayed green**, which is the measurement of how the defect survived: not one of them asserted a rendered row. |
| Restore `user.target_retirement_age \|\| 67` | 2 retirement-age cases | Yes. |
| Re-gate the Holdings tab on `hasHoldings` | 1 case | Yes. |
| Drop the stored-value preference | 2 cases | Yes. |

### Two non-discriminating assertions mutation testing found — in my own tests

1. **`Model::fresh()` bypasses global scopes**, so it returns a soft-deleted row as
   happily as a live one and its id is unchanged either way. An assertion on
   `$this->equity->fresh()->id` passed whether the row survived or was annihilated
   and rebuilt. Replaced with a query for the live row.
2. **`toBeCloseTo(0.305, 3)` on the weighted fund charge passed under both
   hypotheses** — weighting the allocation-derived £160,000 instead of the stored
   £160,018 gives 0.305 exactly, and the two agree to three places. Tightened to five,
   which is where they part.

Both are the Collision variant, and neither was visible by reading the test file.

### Figures are mutually distinct by construction

The fund is £320,000 with allocations of 50% and 30%, so the values an allocation
would derive are £160,000 and £96,000 while the rows store **£160,018** and
**£96,360**. With round figures every survival case would have passed against the
pre-fix code.

**Two of the persona's own values coincide** — 30% of £320,000 is £96,000 and 20% is
£64,000, which are exactly what SLXX and LGUKP hold. Those two value assertions are
**labelled in the file as proving nothing**; that case rests on the unit counts and
prices, which no allocation can produce.

**D-18's figures collide on live data.** David's `users.target_retirement_age` and his
SIPP's `dc_pensions.retirement_age` are **both 60**, so the right source and the wrong
source give the same number. The test uses three mutually distinct values — pension
62, user 58, and the old literal 67.

## 6. The browser run

Account established on the token rather than on a relay: both stores empty at the
start, then `GET /api/auth/user` on the token in use returned **id 16,
david.jones@example.com**, and later **id 17, sarah.jones@example.com**. The `/m`
store stayed empty throughout both.

**W-0441 — verified end to end on David.** Holdings tab present on a pension with no
holdings; all three persona holdings entered through the interface and stored with
units, prices, purchase date, ongoing charge and `sub_type`; fee figures moved from
"0.00% / 0.00% / £0 a year" to **0.31% / 0.31% / £976 a year**; Edit and Delete
exercised on a throwaway row so persona data was not disturbed.

**The destruction test passed in the browser.** Opening Edit auto-expanded
"Additional information" with all three rows shown allocation-only — the default path,
confirmed live. After changing the provider and pressing Update: same ids 73/74/75,
`max(holdings.id)` still 75, zero soft-deleted rows, units and prices intact, and
`current_value` still **160018.00** rather than the £160,000 an allocation-derived
revaluation gives.

**W-0442 — the pension table verified**, all ten columns, with L&G's £1.35 and £1.28
rendering distinctly (both were "£1" before) and £160,018 rather than £160,000.

### One thing the browser could not settle, and why it matters

**`HoldingsTable` has no user-reachable path that renders it with data on this build.**
Two reasons beyond the dead branch, both found in the browser:

1. `InvestmentList.vue:178-179` — the entire `portfolio-features` block is
   **commented out**, with a note saying it was hidden from the dashboard on purpose.
   That block holds the only mount of `InvestmentHoldings` where the store is already
   populated.
2. `/net-worth/holdings-detail` renders `InvestmentHoldings`, which reads `allHoldings`
   from the store and **has no `mounted` hook and dispatches no fetch**. Reached
   directly it shows "No holdings found" — confirmed against a user with four holdings
   on record.

So the table was dead three times over. **The branch fix is proven by test and
mutation only. I could not test it in a live browser and do not claim to have.**
Un-commenting a deliberately hidden dashboard section is a product decision, not a bug
fix, and is not in this batch.

### What the browser could not settle

- **The retirement-age SOURCE.** The panel showing "60" proves the hardcoded 67 is
  gone. It does **not** prove the source changed, because David's user target and his
  SIPP's own age are both 60. Only `PensionDetailInline.test.js` (62 / 58 / 67)
  separates them.
- **Sarah has no defined contribution pension** — one defined benefit scheme. There
  was nothing on her account for W-0441 or the pension half of W-0442 to exercise.
  Her retirement page renders correctly and nothing here touches it. Her defined
  benefit card did not open a detail panel when clicked; that is outside this batch
  and untouched by it.
- **The investment `HoldingsTable`** — see above.
- **`/m` display parity for W-0442** — not done, contract decision, raised.
- **The persona's 0.25% platform fee** is still not on record for pension 10. The
  panel says "Not recorded" rather than "0.00%", which is honest, but entering it is
  a data step and I did not invent it.

### Environment note

`GET /api/auth/user` **does not return `target_retirement_age`**. The Vuex `auth.user`
therefore never carried it, and the old expression fell through to its hardcoded `|| 67`
for every user — which is exactly the reported symptom, and a stronger explanation than
"the store was stale".

### Test data left behind

Holdings **73, 74, 75** on pension 10 are the persona's real figures and did not exist
before. Pension 10's provider was changed to "AJ Bell Youinvest" during the destruction
test and **restored to "AJ Bell"**. A throwaway holding (76) was created, edited and
deleted; it is soft-deleted.
