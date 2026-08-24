---
id: W-0238
title: The dashboard module cards are a second, wrong answer to a figure printed beside them — and they are wrong in opposite directions for the two spouses
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0022-cycle4-dashboard-module-totals-and-cache.md
owner: build-lead
status: gated
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T18:30:00Z
claimed: 2026-08-22T19:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0015, W-0187, W-0203, W-0226, F-0002, F-0019]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Raised by `peak-earners-c4` in cycle 4, browser-verified on both accounts after a
manual cache clear.

### The defect

`GET /api/v1/mobile/dashboard` returns the module cards and the net worth block in
**one response**, and they disagreed:

| | Shown | Correct | Failure |
|---|---|---|---|
| David (16) SAVINGS | £102,000 | £99,750 | joint counted at 100% |
| David (16) INVESTMENT | £220,000 | £172,500 | joint counted at 100% |
| Sarah (17) SAVINGS | £28,780 | £31,030 | joint invisible |
| Sarah (17) INVESTMENT | £85,000 | £132,500 | joint invisible |
| Sarah (17) RETIREMENT | £0 "Plan your retirement" | £35,000/year | provision with no pot shows nothing |

Two failures in F-0019's vocabulary, on the same records:

- **Fraction** — `PortfolioAnalyzer::calculateTotalValue` and `SavingsAgent`'s
  `$accounts->sum('current_balance')` summed shared accounts whole.
- **Reach** — `InvestmentAgent` read `where('user_id', …)` and `SavingsAgent` read
  the `savingsAccounts` hasMany, so the co-owner never saw a joint record held on
  the other account. Rule 6 requires `user_id = ? OR joint_owner_id = ?`.

The retirement row is a third, different failure: the card could only render a
**pot**, and a defined-benefit-only spouse has an **income**. The aggregator's
fallback read `dbPensions->sum('transfer_value')` — a column `db_pensions` does not
have — so she scored zero from every source at once.

### Impact

The dashboard is the first screen after login on all three surfaces. A household
read two different totals for the same joint account depending on which spouse was
logged in, with the correct figure printed two inches away in the net worth tile.

## Acceptance

1. Every figure above reads the Correct column on **David (16) and Sarah (17)**.
2. `modules.*` agrees with `net_worth.*` **within the same response**.
3. Sarah's retirement card shows £35,000/year, not £0.
4. Verified on web AND `/m` (Rule 19).
5. The share is computed in ONE place that all consumers read — no sixth copy.

## Working notes

**2026-08-22 — DONE, handed to quality-lead.** Branch document:
`workforce/branches/fixes/F-0022-cycle4-dashboard-module-totals-and-cache.md`.

**Prior art outcome: route.** `CrossModuleAssetAggregator::calculateCashTotal()` /
`calculateInvestmentTotal()` already answered both questions reach-completely and at
the user's fraction — they are what `/net-worth` and the wealth summary read. The
agents were not routed to them. Nothing new was built for the totals; one mechanism
was **deleted**.

### What changed

| File | Change |
|---|---|
| `app/Agents/SavingsAgent.php:78-96` | `$accounts` now `SavingsStore::forUser()` (reach) through `atUserShare()` (fraction); `total_savings` from `CrossModuleAssetAggregator::calculateCashTotal()` |
| `app/Agents/InvestmentAgent.php:70-107` | `$accounts` now `InvestmentAccount::forUserOrJoint()` through `atUserShare()`; `total_value` from `calculateInvestmentTotal()`; holdings scaled by the owning account's share |
| `app/Services/Investment/PortfolioAnalyzer.php:15-25` | `calculateTotalValue()` **deleted** — it took a collection, so it could not know whose portfolio it was and could not apply a share even in principle |
| `app/Traits/CalculatesOwnershipShare.php` | `atUserShare()` and `userShareFraction()` added to the existing one home |
| `app/Services/Mobile/MobileDashboardAggregator.php` | `extractRetirementSummary()` emits `guaranteed_income`; the phantom `transfer_value` patch removed |
| `resources/js/utils/retirementHeadline.js` (NEW) | The one home for the retirement card's headline rule, read by web AND `/m` |

### Measured, both accounts, after clearing every per-user key

| | David (16) | Sarah (17) |
|---|---|---|
| `modules.savings.total_savings` | **99,750** | **31,030** |
| `net_worth…assets.savings` | 99,750 | 31,030 |
| `modules.investment.portfolio_value` | **172,500** | **132,500** |
| `net_worth…assets.investments` | 172,500 | 132,500 |
| `modules.retirement` | pot 500,000 | **guaranteed_income 35,000** |

Live browser, `localhost:8000` desktop web: David reads
`Savings £99,750 · Investment £172,500 · Retirement £500,000 "Your pension pot"`;
Sarah reads `Savings £31,030 · Investment £132,500 · Retirement £35,000/year
"Guaranteed retirement income"`. Screenshots `W-0238-web-david-16-after.png`,
`W-0238-web-sarah-17-after.png`.

`/m` on `localhost:8000/m/app/dashboard` reads David's corrected
`£99,750 / £172,500` from the shared endpoint. **`/m` cannot yet render Sarah's
guaranteed-income headline** — `public/m-build/` dates from 2026-08-21 13:45 and
`/m` never uses Vite. The bundle rebuild is the coordinator's; requested, not run.

### Raised while working

W-0241 (net worth counts every defined benefit pension as £0) · W-0242
(`LifeStageService` throws on the same phantom column) · W-0243 (native iOS cannot
render a guaranteed income) · W-0244 (`RetirementAgent` reports "not set up" for a
household with £500,000 of pensions) · W-0245 (the two dashboards still duplicate
the card-building computed).
