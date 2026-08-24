---
id: W-0442
title: The holdings tables hide what they store — and the investment one has never rendered at all, behind a duplicated v-else-if
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0032-cycle4-pension-holdings-entry-and-display.md
owner: build-lead
status: claimed
severity: medium
surfaces: [web, m]
created: 2026-08-23T03:30:00Z
claimed: 2026-08-23T03:30:00Z
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0039, W-0121, W-0126, W-0351, PortfolioPresentationService]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by `peak-earners-c4`, cycle 4, as D-12: Units Held, Purchase Price, Current
Price and Purchase Date are captured, validated and stored, and displayed nowhere.
The display half of W-0039, whose fix made them enterable while nothing made them
visible.

**The sweep found two mechanisms with two different diseases.**

### 1. `Investment/HoldingsTable.vue` is dead code

`:52` and `:135` opened two consecutive `v-else-if="filteredHoldings.length > 0"` in
the same chain. **The second can never be true.** With holdings the user got the
donut and the legend; the table below it had **never rendered** — nor its Purchase
Price and Current Price columns, its Purchase Date and ISIN in the expanded row, its
cost basis, its return, or its per-row Edit and Delete buttons. The empty state was
reachable; the table was not.

Untouched since 30 March, so not a recent regression.

**A twenty-one case suite named `HoldingsTable` stayed green throughout**, because
every case asserted a computed or a method through `vm` and **not one asserted a
rendered row.**

### 2. The pension table showed five columns and stored nine

`PensionDetailInline.vue`: Fund Name, Type, Allocation, Value, Ongoing Charge Figure.

## Acceptance

1. [x] The investment holdings table renders when holdings exist, alongside the
   chart rather than instead of it.
2. [x] Units, purchase price, current price and purchase date are visible where they
   are stored, on both tables.
3. [ ] **`/m` parity.** NOT done — see the working note.
4. [ ] Browser verification. Not done; the browser was requested and held.

## Working notes

- 2026-08-23 build-lead (`fix-cycle4-retirement`): **Fixed on web, not on `/m`, not
  browser-verified.**

  Both branches now render from one `<template v-else-if>`. A Units Held column is
  added to both tables — it is the fact a holding is made of, and no table has ever
  shown one back.

  **Two further display faults found while adding the columns:**

  1. **Unit prices were rounded to whole pounds.** `formatCurrency` renders £80.50 as
     "£81"; the persona's L&G UK Property is £1.35 in and £1.28 now, and **both
     rendered as "£1"** — two different facts as one number. Both tables now use
     `formatCurrencyWithPence` for prices; holding *values* stay whole-pound.
  2. **The pension table recomputed the value from the allocation** rather than
     showing the stored one, so a holding storing £160,018 (4,211 units at £38.00)
     displayed £160,000, being 50% of the pot. The stored value now wins; the
     allocation remains the fallback for rows without one and for the
     unallocated-cash footer.

  **Adjacent and fixed here, because it is the other half of D-17's symptom.** The
  Overview Fees block read "Platform Fee 0.00% · Total Annual Cost 0.00% · Annual Fee
  Impact £0/year" because `platform_fee_percent` is **NULL** on David's SIPP and every
  reader coerces with `|| 0`. That is not an absence, it is a claim that the platform
  charges nothing — the `?? null` versus `|| 0` distinction in `app/Http/CLAUDE.md`.
  It now says "Not recorded", and the totals appear only once something is on record.

  **Evidence:** 6 cases added to `tests/frontend/components/Investment/HoldingsTable.test.js`,
  12 in `tests/frontend/components/NetWorth/PensionDetailInline.test.js`. Frontend 257
  passed. Mutation-tested: restoring the duplicated `v-else-if` reddens the 5 new table
  cases and **leaves all 21 pre-existing cases green**, which measures precisely how the
  defect survived.

- 2026-08-23 build-lead: **`/m` is NOT done, and it is a contract decision rather than
  a component change.**

  `/m` renders holdings through `CanonicalPortfolio.vue`, which shows name, value,
  ongoing charge and estimated annual cost. Units, purchase price, current price and
  purchase date are absent **and cannot be added in the component**:
  `PortfolioPresentationService:100-131` never publishes them. Axis 7 in
  `app/Http/CLAUDE.md` — the component is right about what it was given.

  That payload carries a **`contract_version`**, and it is read by `/m` **and by
  native**. Extending a versioned contract is a version decision, not a routine
  addition, and both the service and the component sit outside the exclusive scope
  this batch was given while a sibling agent is live. **Raised, not taken.**

- 2026-08-23 build-lead (`fix-cycle4-retirement`): **Pension table BROWSER VERIFIED.
  Investment table CANNOT be browser-verified on this build — see below. Holding at
  `claimed`.**

  Signed in as **user 16, david.jones@example.com**, established on the token via
  `GET /api/auth/user`, not on a relay.

  The pension holdings table renders all ten columns:

  `Fund Name · Type · Units Held · Purchase Price · Current Price · Purchase Date ·
  Allocation · Value · Ongoing Charge Figure · Actions`

  and the rows carry what they store:

  | Fund | Units | Purchase | Current | Date | Value | Charge |
  |---|---|---|---|---|---|---|
  | Vanguard Global Equity | 4,211 | £32.50 | £38.00 | 11/03/2019 | **£160,018** | 0.23% |
  | BlackRock Corporate Bond | 800 | £125.00 | £120.00 | — | £96,000 | 0.18% |
  | L&G UK Property | 50,000 | **£1.35** | **£1.28** | — | £64,000 | 0.68% |

  **The two display fixes are visible in that table.** L&G's £1.35 and £1.28 are
  distinct — under `formatCurrency` both rendered as "£1". And the top row shows
  **£160,018**, the stored value, not the £160,000 that 50% of a £320,000 pot gives.

  A row with no purchase price and no purchase date shows **em dashes**, not "£0"
  and not "0" — checked on a throwaway row created for the purpose.

- 2026-08-23 build-lead: **Why the investment table could not be reached, and it is
  a bigger finding than the duplicated `v-else-if`.**

  `HoldingsTable` has **no user-reachable path that renders it with data** on this
  build, for two further reasons on top of the dead branch:

  1. **Its only in-app mount is commented out.** `InvestmentList.vue:178-179` —
     `<!-- Portfolio Features Tabs - Hidden from dashboard, components still
     available for detail views -->` wraps the entire `portfolio-features` block,
     including the `<InvestmentHoldings v-if="activePortfolioTab === 'holdings'">`
     that is the only place the store is already populated when it mounts.
  2. **The standalone route never loads data.** `/net-worth/holdings-detail` renders
     `HoldingsDetail` → `InvestmentHoldings`, which reads `allHoldings` from the
     store and **has no `mounted` hook and dispatches no fetch**. Reached directly
     it always shows "No holdings found" — verified in the browser against a user
     who has four holdings on record (ids 33, 70, 71, 72).

  So the table was dead three times over: unreachable branch, commented-out mount,
  and a route that renders an empty state by construction. **That is how a
  twenty-one case suite named after it stayed green for five months** — nothing
  rendered it and nothing asserted a row.

  **I did not un-comment `InvestmentList.vue`.** Restoring a deliberately hidden
  dashboard section is a product decision with a note attached saying it was hidden
  on purpose, not a bug fix, and it is outside this batch.

  **The branch fix itself is proven by test and mutation**, not by the browser:
  restoring the duplicated `v-else-if` reddens the 5 new rendered-table cases and
  leaves all 21 pre-existing cases green. **I could not test it in a live browser,
  and I am not claiming otherwise.**

  **Acceptance 1 therefore stands unverified in the browser, acceptance 3 (`/m`) is
  not done, and this item stays at `claimed`.**
