---
id: W-0277
title: The SavingsStore boundary allowlist names a class that no longer touches the model
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: quality-lead
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-22T22:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0271]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

**W-0271** removed the last `SavingsAccount` reference from
`App\Services\Risk\AutoRiskCalculator` — it now reads cash through
`CrossModuleAssetAggregator`, which reads through `SavingsStore`. The class is
therefore a genuine store consumer and no longer needs a bypass exemption.

Two places still grant it one:

- `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php:146`
- `app/Services/Stores/SavingsStore.md`, "Out-of-sub-project-1-scope read / infra
  references"

Neither is failing — an unused allowlist entry is inert. It is filed because a stale
allowlist is documentation that disagrees with the code, and the next reader checks
the code against it (the W-0226 / W-0239 lesson).

**Deliberately not fixed in W-0271:** both are shared boundary config, and editing
them while parallel batches are running is a collision, not a fix.

## Acceptance

The entry is removed from both, with the boundary suite still green.
