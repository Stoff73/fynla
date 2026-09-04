---
id: W-0540
title: A component can lose its last importer and nothing fails — 79 of 522 already have
mission: board-verification-31-august
owner: null
reviewers: [quality-lead]
status: queued
severity: low
surfaces: [web]
created: 2026-09-04
source: measured while closing W-0538, 2026-09-04
prior_art_checked: 2026-09-04
prior_art_found: [W-0376, W-0538]
prior_art_outcome: extends — both were single instances of this; this is the mechanism behind them
constitution_refs: [07-quality-bar]
---

## Intent

W-0538 was a component with no importers, so a design fix made to it reached no
screen and nobody noticed for a fortnight. W-0376 was the same shape earlier.
Neither was caught by anything; both were found by a person looking.

Measured 2026-09-04: **79 of 522 components under `resources/js/components/` have
no importer anywhere in `resources/js` or `tests/frontend`** — 15%.

Concentrated in `Investment/`: the whole of `PlanSections/` (`FeeAnalysisSection`,
`CurrentSituationSection`, `GoalProgressSection`, `TaxStrategySection`,
`ActionPlanSection`, `RiskAnalysisSection`, `RecommendationsSection`), plus
`PerformanceAttribution`, `AssetLocationOptimizer`, `WrapperOptimizer`,
`FeeSavingsCalculator`, `TaxFees`, `WhatIfScenariosBuilder`, `PortfolioOverview`,
`InvestmentReadinessGate`, `GoalProjection`, `BenchmarkComparison`. Clusters also
in `Estate/` (`PensionAmendmentBanner`, `AssetsLiabilities`,
`EstateProjectionComparison`) and `Savings/` (`InterestRateComparisonChart`,
`MissingDataCard`, `SavingsDecisionPath`).

## The decision this needs first

A guard added today fails on all 79 immediately. So it is one of:

- **an allowlist** freezing the 79 and failing only on the 80th — cheap, but it
  records 15% of the component tree as permanently acceptable; or
- **a deletion sweep** first, then a guard with nothing to allow — correct, but
  79 files is a project, and some of them may be half-built features somebody
  intends to finish rather than dead code.

That is CSJ's call. The measurement is here so the call has a number.

## Acceptance

1. A decision between allowlist and sweep, recorded here.
2. Whichever it is, a check that fails when a component loses its last importer.
3. If a sweep: each deletion checked against git history for a half-built feature
   rather than deleted on the count alone.
