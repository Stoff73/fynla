# Monte Carlo Simulation Consolidation

**Date:** 19 March 2026
**Branch:** `main`
**Commit:** `4920f8a` (merged via `66c7d96`)

## Summary

Consolidated two separate Monte Carlo simulation implementations into a single inheritance hierarchy. `MonteCarloSimulator` now extends `MonteCarloEngine`, eliminating ~100 lines of duplicated code.

## Before

Two independent implementations with duplicated core math:
- `app/Services/Shared/MonteCarloEngine.php` — simpler shared primitive (used by GoalRiskService, RetirementProjectionService)
- `app/Services/Investment/MonteCarloSimulator.php` — more complex standalone class with caching + scheduled injections (used by InvestmentProjectionService, RunMonteCarloSimulation job)

## After

- `MonteCarloEngine` (base) — canonical implementation with core simulation math
- `MonteCarloSimulator extends MonteCarloEngine` — adds caching, scheduled injections, multi-asset simulation

### Architecture

```
MonteCarloEngine (base)
├── simulate() — public API
├── runCoreSimulation() — protected, accepts optional scheduledInjections
├── calculateGoalProbability() — shared
├── calculatePercentiles() — shared
├── generateNormal() — Box-Muller RNG
└── applyScheduledInjection() — protected, shared

MonteCarloSimulator extends MonteCarloEngine
├── simulate() — delegates to runCoreSimulation(), reshapes output
├── runMultiAssetSimulation() — multi-asset with correlation matrix
├── getCachedResult() / cacheResult() / clearCache() — DB caching
└── reshapeToInvestmentFormat() — format conversion
```

## Eliminated Duplication

- Box-Muller random number generation (`generateNormalDistribution` → alias to inherited `generateNormal`)
- Percentile calculation (fully inherited)
- Goal probability calculation (fully inherited)
- Single-asset simulation loop (delegated to `runCoreSimulation`)
- `aggregateResults` / `applyScheduledInjection` (moved to base)

## Consumer Compatibility

All 7 consumers required zero changes:
- `GoalRiskService` — uses `MonteCarloEngine::simulate()` (6 params)
- `InvestmentProjectionService` — uses `MonteCarloSimulator::simulate()` (8 params)
- `RetirementProjectionService` — same pattern
- `RunMonteCarloSimulation` — calls `simulate()`, accesses `final_value` from percentiles
- `RetirementAgent`, `InvestmentAgent` — inject `MonteCarloSimulator`
- `LifeEventMonteCarloObserver` — calls `clearUserCache()`

## Files Changed

- `app/Services/Shared/MonteCarloEngine.php` — extracted `runCoreSimulation()`, `applyScheduledInjection()` as protected
- `app/Services/Investment/MonteCarloSimulator.php` — now extends `MonteCarloEngine`, removed duplicated methods
- `tests/Unit/Services/Shared/MonteCarloEngineTest.php` — added format test
- `tests/Unit/Services/Investment/MonteCarloSimulatorTest.php` — added inheritance + format tests

## Testing

- 38/38 Monte Carlo tests pass (16 engine + 22 simulator)
