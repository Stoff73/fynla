---
id: W-0241
title: Net worth counts every defined benefit pension as £0, by summing a column db_pensions does not have
mission: persona-run-peak_earners-2026-08-20
branch: F-0024
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T20:30:00Z
claimed: 2026-08-22T21:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0226, W-0238]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found while fixing **W-0238**, outside its scope and **not fixed** — scope discipline.

### The defect

`app/Services/Mobile/MobileDashboardAggregator.php:427`:

```php
$dbPensionValue = (float) $user->dbPensions->sum('transfer_value');
```

`db_pensions` has no `transfer_value` column. Verified against the live schema: the
value columns are `accrued_annual_pension`, `projected_annual_pension_at_nra_gbp`
and `lump_sum_entitlement`.

Because this is a **collection** sum over models, a missing attribute reads as null
and the sum is `0.0` — silently, for every user, forever. `net_worth.breakdown.
assets.pensions` therefore contains the defined contribution pots only.

Measured on the persona: Sarah Jones (17) holds an NHS final salary scheme worth
£35,000 a year with a £105,000 lump sum entitlement, and her net worth reports
`pensions: 0`.

### Why it is not simply a typo

**There is no obviously right replacement, which is why this is an item and not a
line change.** A defined benefit scheme has no market value; a Cash Equivalent
Transfer Value is a quotation the user may never have obtained, and capitalising an
income at some multiple would be inventing a figure. Three options, all product
calls:

1. Add a `transfer_value` column and ask for it — honest, and usually empty.
2. Capitalise `accrued_annual_pension` at a stated multiple — needs a disclosed
   basis and would move every affected user's headline net worth.
3. Exclude defined benefit schemes from net worth and say so on screen — the
   current behaviour, except that nothing says so.

**The application currently does (3) while its code reads as (1).** That is the
part that must not survive whichever is chosen.

### Related, separately filed

`LifeStageService::hasPensionValueAbove()` reads the same phantom column through
the **query builder**, which throws instead of returning zero — **W-0242**.

## Acceptance

1. A decision from CSJ on which of the three the product does.
2. No reader of `transfer_value` remains unless the column exists.
3. If defined benefit schemes stay out of net worth, the surfaces say so where the
   figure is shown.
4. Web, `/m` and native named individually.

---

## CSJ RULING, 2026-08-22 — OPTION 3: exclude, and say so on screen

**Decided by CSJ. Not open for re-litigation (Rule 18).**

Of the three options in Intent, CSJ chose **(3): exclude defined benefit schemes from
net worth, and state it plainly wherever the figure is shown.**

**Rationale:** it is the only option that neither invents a valuation nor asks the user
for a figure most of them have never obtained. No user's headline number moves. The
defect was never the exclusion — it was that **the application did (3) while its code
read as (1)**, silently.

**In scope:**

1. **Delete every reader of the phantom `transfer_value` column.** Do not add the
   column. `MobileDashboardAggregator.php:427` is the collection reader that silently
   returns `0.0`; `LifeStageService.php:211` is the query-builder reader that throws
   (filed as **W-0242**, assigned to the validation batch — coordinate, do not both edit it).
2. **Disclose the exclusion where the figure appears**, so a user holding an NHS final
   salary scheme is told why their pensions total shows Defined Contribution only.
   Wording is design-lead's; the requirement is that no surface presents the total as
   complete.
3. **Name web, `/m` and native individually** (Rule 19), per the item's own acceptance.

**Explicitly OUT of scope — do not build:**
- A `transfer_value` column, migration or form field.
- Any capitalisation multiple applied to `accrued_annual_pension`.
- Any change to a user's net worth figure. **If a number moves, the change is wrong.**

**Rule 12 applies to the disclosure copy** — descriptive text, no score, no rating.

**Sequencing:** queued behind the W-0228 ownership batch, which is live in
`MobileDashboardAggregator.php`. Do not dispatch into that file concurrently.


---

## HANDOFF → quality-lead, 2026-08-22 (build-lead, `fix-cycle4-pensions`)

**Branch document: `workforce/branches/fixes/F-0024-cycle4-pension-provision-and-valuation.md`.**
**Read §5 of the branch document. This item's ACCEPTANCE WIDENED mid-batch** — see
the section below — and Quality needs to review the larger scope, not the filed one.

### What was done

1. **Every reader of the phantom column deleted.** There was exactly one left:
   `MobileDashboardAggregator.php:427`. `LifeStageService.php:211` had already been
   removed by W-0242's agent (verified, not assumed). The remaining `transfer_value`
   matches in `ISAAllowanceOptimizer`, `BedAndISACalculator` and
   `BedAndISATransfers.vue` are an **unrelated Bed-and-ISA array key**, not the
   pension column — checked, correctly left alone.
2. **Routed to the rule's existing home** rather than fixed in place.
   `NetWorthService::calculatePensionBreakdown()` was already
   Defined-Contribution-only and already returned `has_db` "so the frontend can
   display an appropriate note". It is now public and the aggregator calls it, so
   the dashboard and `/net-worth` cannot answer differently.
3. **The disclosure was already shipped on all three surfaces** —
   `WealthSummary.vue:34`, `NetWorth.vue:20`, `NetWorthView.swift:63`. Prior-art
   outcome **extend, not build**: nothing new was written, and `has_db_pensions` was
   added to the dashboard payload so the flag travels with the figure.
4. **No `transfer_value` column, no migration, no form field, no capitalisation
   multiple** — the ruling implemented, not improved on.

### The acceptance clause that mattered: no number moved

Measured on both accounts, caches flushed, before and after. **Net worth
£1,489,500 / £739,280, pensions £500,000 / £0, both retirement cards
byte-identical.** Nothing moved on any surface.

### ACCEPTANCE WIDENED — read this, the item's scope grew

**Clause 3 now covers the DETAIL views and the DASHBOARD, not just the summary.**
Team-lead authorised the widening explicitly; Quality needs to know the item is
bigger than it was filed.

A **×20 capitalisation** of Defined Benefit schemes — **option 2 in this item's own
Intent, the option CSJ rejected** — was running live on web, `/m` and native at
`NetWorthService.php:302-313`, served by `GET /api/net-worth/assets-summary-detailed`.
It is **deleted**.

Measured for Sarah (17), before and after:

| | Before | After |
|---|---|---|
| Pensions row | **£805,000 (93% of assets)** | **£0 (0%)** |
| Asset list sums to | £1,666,780 | **£861,780** |
| Difference from the stated total | **£805,000** | **£0** |
| Category percentages total | **193%** | **100%** |
| Headline net worth | £739,280 | **£739,280 — unchanged** |

The NHS scheme keeps `annual_pension: 35000`, which every surface already renders as
"£35,000 a year", and the category subtitle no longer says "Accessible pension
capital". **The £805,000 → £0 movement is the ruling taking effect and is recorded as
a non-regression** — do not flag it.

**One home for the wording: `app/Constants/PensionDisclosure.php`.** Three frontends
each held their own copy of the sentence; web and `/m` now render what the backend
sends with the figure. A fourth consumer — the risk-profile capacity-for-loss factor,
owned by another agent — reads the same constants rather than shipping its own.

**Checked that the disclosure actually RENDERS, not just that the string exists.**
The ellipsis rules in `NetWorthOverview.vue` target `.item-name` and `.column-value`,
not the disclosure block; neither `/m` class clamps. A clipped disclosure is not a
disclosure.

**Native:** the figures are correct from the backend alone — native has no
client-side capitalisation and already renders `annual_pension` as "£35,000 a year"
(`NetWorthCategoryView.swift:269-270`). Two Swift-only gaps remain — the category
subtitle still reads "Accessible pension capital" (`NetWorthModels.swift:324`) and
`NetWorthAssetSection` does not decode the new `disclosure`/`subtitle` keys. **Filed
as W-0311 with the exact change written out.** `Codable` ignores unlisted keys, so
nothing breaks meanwhile.

### `LifeStageService.php` — closed, not by me

Its comment block claimed W-0241 was "open with CSJ" and that the Defined Benefit
term "should be added back here" when it lands. Under the ruling it must never be
added back. Raised to team-lead rather than edited, since the file was another
agent's and mid-verification; **that agent has since fixed it, finding the same stale
claim in four places.** Nothing outstanding.

### Evidence

`tests/Feature/Retirement/PensionProvisionAndValuationTest.php`, **18 passing**.
**The W-0241 assertions deliberately avoid the £0** — it reads the same under the
bug and under the correct exclusion (`tests/CLAUDE.md` §4, collision variant). They
assert the **disclosure flag**, a **Defined Contribution figure that moves**, and a
case that adds a Defined Benefit scheme to a populated household and proves the net
worth is unchanged while `has_db_pensions` flips false → true. Both directions.

For the ×20 removal specifically the assertions are **the £35,000 that survives** and
**the arithmetic** — that the asset list sums to the total printed above it — rather
than the £0, which reads the same under the bug and under the fix. One test names
`805000.0` explicitly as the number that must not come back.

**Not browser-verified.** The single Playwright tab was held by another agent and
not released to this batch. Stated as a gap, not signed off.

### BROWSER VERIFIED — 2026-08-22, both accounts, web and `/m`

Full evidence in F-0024 §11. Headlines:

- **Sarah's `/m` asset list now sums to £861,780 against a stated £861,780, with
  percentages totalling 100%.** Before this batch it summed to £1,666,780 with
  percentages totalling 193%.
- `/m` pensions category: **"Accessible pension capital" is gone**, the new subtitle
  and the disclosure both render, and the NHS scheme shows **£0 capital beside
  "£35,000 a year"**.
- **The disclosure is proven to come from the backend**: the sentence has zero
  occurrences in the rebuilt `/m` bundle yet renders on screen.
- **Not clipped** — measured at 390×844: `scrollHeight === clientHeight`, no
  line-clamp, `overflow: visible`.
- **David, the negative case:** `.mnw-note` and `.mnwc-disclosure` element counts are
  **0** — asserted on the element, not the text — and his list sums to £1,660,000 at
  100%.

### CORRECTION to this handoff's earlier claim

It said the ×20 was live on **web, `/m` and native**. **It was live on `/m` and native
only.** The web consumer cited (`NetWorthOverview.vue`) is **dead code** — nothing
imports it, and it is the only web consumer of `assets-summary-detailed`. The measured
£805,000 / 193% figures were computed from the endpoint and are what `/m` rendered, so
the defect and the fix are unchanged; the surface count was overstated and is
withdrawn. Detail and the lesson in F-0024 §6.12.
