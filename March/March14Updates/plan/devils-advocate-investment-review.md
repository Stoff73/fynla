# Devil's Advocate Review: Investment Implementation Plan

> **Date:** 2026-03-14
> **Reviewed:** `implementation-plan-investment.md`
> **Method:** Direct file inspection of all referenced services, models, and controllers

---

## CRITICAL (2)

### 1. Phase 6 Breaks InvestmentPlanService — The Plan's Integration Point Is Wrong

The plan proposes changing `InvestmentAgent::analyze()` to run the full pipeline. `InvestmentPlanService` (a fully-built production service) calls `investmentAgent->analyze()` expecting the current analysis shape (`portfolio_summary`, `returns`, `asset_allocation`, `fee_analysis`, `tax_wrappers`). Changing `analyze()` breaks `buildCurrentSituation()`, `buildWhatIfData()`, `buildAccountGrowthProjections()`, `PlanController`, and `InvestmentController::analyze()`.

`InvestmentPlanService` already assembles `$investmentAnalysis`, `$savingsAnalysis`, `$investmentAccounts`, `$savingsAccounts`, `$accountFeeAnalyses` — ~70% of what `UserContextBuilder` would re-fetch from scratch.

**Recommendation:** The new pipeline belongs in `InvestmentPlanService::getRecommendations()`, NOT the agent. Do not modify `InvestmentAgent::analyze()`. Either extend `InvestmentPlanService` or create a new method. `UserContextBuilder` should derive its context from data already assembled by `InvestmentPlanService`, not re-query the database.

### 2. Three Surplus Waterfall Triggers Fire Simultaneously With New Waterfall

`surplus_exists_and_isa_remaining`, `surplus_exceeds_isa`, `surplus_exceeds_pension` in `InvestmentActionDefinitionService` (lines 158-160) produce recommendations that directly overlap with ContributionWaterfallService Steps 2, 3, and 8. Unlike the Cash/Savings plan, there is NO launch gate disabling these. Users will receive duplicate recommendations.

Additionally, three tax triggers (`has_harvesting_opportunities`, `has_isa_remaining_and_gia`, `has_gia_no_isa`) overlap with TransferRecommendationService scans 2 and 3.

**Recommendation:** Add a launch gate: disable these 6 trigger definitions in the seeder when the new pipeline goes live. Same pattern as the Cash/Savings plan.

---

## HIGH (4)

### 3. Growth Rate Audit Misses 4+ Files (8+ Locations)

`Analytics/HoldingsDataExtractor.php` (4 occurrences at lines 226, 230, 250, 253), `Analytics/PortfolioStatisticsCalculator.php` (2 occurrences at lines 285, 302), and `AssetLocation/TaxDragCalculator.php` (2 additional beyond what Phase 0.3 covers) are all missing from the plan's Phase 0.2 table. `HoldingsDataExtractor` feeds the Markowitz/efficient frontier analytics stack — wrong returns there cascade into incorrect optimisation recommendations.

Additionally, the plan's Phase 0.1 seeder only defines yields for income-producing classes. `HoldingsDataExtractor` needs return assumptions for `uk_equity`, `international_equity`, `emerging_markets`, `commodity`, `alternative`, `mixed` — these are not in the seeder.

### 4. SafetyCheckService Must Not Emit User-Facing Emergency Fund Recommendations

The Cash/Savings plan explicitly owns emergency fund recommendations. If `SafetyCheckService` also emits "build your emergency fund" cards, users see duplicates from two engines. Safety checks must only gate the surplus figure internally — no standalone emergency fund action cards.

### 5. Frontend Has Zero `can_proceed` Handling

No component in the Investment frontend handles a readiness gate state. The plan has no frontend task. When the API returns `can_proceed = false`, the frontend shows "No recommendations available" with no explanation of what's missing.

### 6. ISA Allowance Ownership Across Engines Not Reconciled

The Savings engine may recommend Cash ISA usage. The Investment waterfall Step 2 recommends S&S ISA. Both draw from the same £20,000 allowance. `UserContextBuilder` must use the multi-source ISA remaining calculation (which `InvestmentAgent::analyze()` already does at lines 88-94, cross-checking savings and investment ISA subscriptions) — not just `InvestmentAccount` subscriptions.

---

## MEDIUM (2)

### 7. UserContextBuilder Duplicates InvestmentPlanService Assembly

`InvestmentPlanService::generatePlan()` already fetches: user model with income fields, investment analysis, savings analysis, accounts, fees, goals, risk profile. UserContextBuilder re-fetches ~70% of this. Derive context from existing data, don't re-query.

### 8. ContributionOptimizer Produces Independent Hard-Coded Recommendations

`ContributionOptimizer::generateRecommendations()` (lines 432-485) produces `isa_allowance`, `pension_tax_relief`, `lump_sum_strategy`, `auto_increase` recommendations outside the DB-driven framework. These will conflict with waterfall steps 2 and 3. Also `calculateContributionEfficiency()` returns a score (0-100) — possible CLAUDE.md Rule #13 violation.

---

## Summary

| # | Severity | Finding |
|---|----------|---------|
| 1 | Critical | Phase 6 changes break InvestmentPlanService and PlanController |
| 2 | Critical | 6 trigger definitions overlap with new pipeline — no launch gate |
| 3 | High | Growth rate audit misses 4+ files (8+ locations) |
| 4 | High | SafetyCheckService must not emit standalone emergency fund recommendations |
| 5 | High | Frontend has zero readiness gate handling |
| 6 | High | ISA allowance ownership across Savings/Investment engines unreconciled |
| 7 | Medium | UserContextBuilder duplicates InvestmentPlanService data assembly |
| 8 | Medium | ContributionOptimizer independent recommendations conflict with waterfall |
