# W-0238 — build-lead → quality-lead

Branch document: `workforce/branches/fixes/F-0022-cycle4-dashboard-module-totals-and-cache.md`

## Done

Every figure in the tester's table reads the Correct column on both accounts, and
`modules.*` agrees with `net_worth.*` inside one response.

| | David (16) | Sarah (17) |
|---|---|---|
| `modules.savings.total_savings` | 99,750 (was 102,000) | 31,030 (was 28,780) |
| `net_worth…assets.savings` | 99,750 | 31,030 |
| `modules.investment.portfolio_value` | 172,500 (was 220,000) | 132,500 (was 85,000) |
| `net_worth…assets.investments` | 172,500 | 132,500 |
| `modules.retirement` | pot 500,000 | guaranteed_income **35,000** (was £0 "Plan your retirement") |

Browser-verified on desktop web at `localhost:8000`, both logins driven through the
MFA gate. Screenshots `W-0238-web-david-16-after.png` / `W-0238-web-sarah-17-after.png`.
`/m` verified for David from the shared endpoint at `localhost:8000/m/app/dashboard`.

Route, not build: `CrossModuleAssetAggregator::calculateCashTotal()` /
`calculateInvestmentTotal()` already answered both questions correctly.
`PortfolioAnalyzer::calculateTotalValue()` is deleted, so the implementation count
went down.

## Not done, and why

- **Sarah's `/m` retirement card still reads £0.** `/m` serves `public/m-build/`
  (2026-08-21 13:45) and never Vite, so new frontend code cannot appear until the
  bundle is rebuilt. **Build artefacts are the coordinator's — requested, not run.**
  Do not log this as a regression before the rebuild. The code path is covered by
  `tests/frontend/mobile/Dashboard.test.js`.
- **Native iOS** still reads £0 for a defined-benefit-only user. Additive JSON key,
  so nothing breaks; filed as **W-0243** with the exact Swift change.
- **The other four dashboard cards are still duplicated** across the two SPAs. Only
  the retirement headline rule was consolidated. Filed as **W-0245**, with why the
  rest is a refactor and not a fix.

## What you need that isn't obvious from the artefacts

1. **Clear the per-user keys before every reading**, or you are reading yesterday.
   `mobile_dashboard_{id}`, `{module}_analysis_{id}`, `mobile_module_{module}_{id}`,
   `net_worth:user_{id}:date_{today}`. W-0239 makes writes invalidate; it does not
   retroactively clear a blob written before the fix landed.
2. **The share view hands the analyzers cloned models carrying a half-balance.**
   They must never be saved. Every current consumer is a pure reader; this is
   documented, not type-enforced. See F-0022 §6.1.
3. **David's `guaranteed_income` is £11,502.40, not £0.** A State Pension row
   (id 15) was created on his account at 19:43 today by another agent. His card is
   unaffected — he has a pot, and the pot leads.
4. **Row 29 in `savings_accounts` is soft-deleted.** A raw SQL query sees two joint
   £4,500 Nationwide rows; only row 53 is live. £99,750 and £31,030 are right;
   £102,000 and £33,280 are what you get by ignoring `deleted_at`.
5. **Three tests were changed, not fixed.** Each pinned a behaviour this work
   deliberately reversed — most notably one asserting `not_configured` beside a pot
   of £47,500. Reasons are in the test files. F-0022 §9.

## Assumptions I made

- A defined benefit scheme's `accrued_annual_pension` is a **retirement** figure,
  not income today (W-0036, `DBPension::isInPayment`). The card says "Guaranteed
  retirement income" and nothing counts it as current income.
- Sarah's £35,000 is the projector's `db_annual_income`. It equals her raw column
  only because her scheme has no inflation protection; a revalued scheme would
  differ from what her retirement page's client-side computed shows.
- A user with pension records and no target has **active** retirement provision.
- Holdings scale by their account's share, with `cost_basis` and `current_value`
  scaled together so the gain stays proportional; `quantity` is not scaled.

## Surfaces covered / not covered

- **web** — covered, browser-verified, both accounts.
- **`/m`** — backend covered and verified for David; Sarah's retirement headline
  blocked on the bundle rebuild. Code path unit-covered.
- **iOS** — not covered. W-0243.
