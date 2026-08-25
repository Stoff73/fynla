---
id: W-0251
title: The Monte Carlo cache key names the user and the horizon but not one input that determines the answer, so a £172,500 portfolio was shown as £4,650
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0024-cycle4-investment-projection.md
owner: build-lead
status: done
severity: critical
surfaces: [web, m, ios]
created: 2026-08-22T20:00:00Z
claimed: 2026-08-22T20:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [RetirementProjectionService-inputHash, CacheInvalidationService, MonteCarloSimulator]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found as D-20 by `peak-earners-c4` (R-23). David's £172,500 portfolio, no contributions,
captioned "Upper-Medium risk profile (7.07% expected return)", was shown at the 80%
probability band as **£4,650 at five years — a 97.3% loss** — with the chart y-axis
collapsed to £50.0K.

The tester proved the simulator correct and the band arithmetic correct, and localised the
fault to the hand-off between them. It was the cache.

`InvestmentProjectionService.php:160-162` built `user_{id}_portfolio_{years}y_e{eventHash}`.
User, horizon, life-event hash — **not capital, contributions, expected return, volatility
or iterations.** A simulation of £47,500 at 6.5% written at 20:11 on 21 August was served
22 hours later against £172,500 at 7.07%.

All six of the tester's reported figures reproduce byte-exactly from cached rows whose
`start_value` is £47,500 or £95,000. The diagnostic tell: four horizons implying −58%,
+2.34%, +5.76% and +5.10% a year. One portfolio cannot produce four inconsistent rates;
four cache entries written at four moments can.

`RetirementProjectionService.php:109` already hashed its inputs, with the reasoning in its
comment. That is why the pension projection was well-behaved and this one was not.

## Acceptance

1. A cached simulation is served only while every input that determines it still matches. ✓
2. Five-year figure in the region of £86,944, not £4,650. ✓
3. Chart y-axis scales to the data at every horizon. ✓ (£180K/£350K/£800K/£800K)
4. Web and /m named individually. ✓ (§9 of F-0024 — /m has no investment projection surface)

## Working notes

- Fixed in `MonteCarloSimulator::fingerprintKey()` — one home; the two hand-rolled
  `$inputHash` lines in `RetirementProjectionService` are deleted.
- **No cache invalidation added, deliberately.** A key naming its inputs makes a stale
  entry unreachable rather than wrong. `CacheInvalidationService` (F-0022) untouched.
- Guarded by `tests/Unit/Services/Investment/MonteCarloCacheIdentityTest.php` (10 tests),
  written as movement assertions under one shared key prefix.
- Before/after at all four horizons, both accounts, in F-0024 §6.
