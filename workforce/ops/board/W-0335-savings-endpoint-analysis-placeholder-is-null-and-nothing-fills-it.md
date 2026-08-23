---
id: W-0335
title: /api/savings returns 'analysis' => null as a placeholder, nothing dispatches the analyze action, and the store then reads a key that does not exist
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: handoff
severity: medium
surfaces: [web]
created: 2026-08-22T23:25:00Z
claimed: 2026-08-23T00:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0274, W-0241, SavingsAgent::analyze]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Three dead links in one chain, found while fixing W-0274.

1. `SavingsController::index():184` returns `'analysis' => null, // Placeholder for
   analysis data`. So `state.analysis` is null after every `fetchSavingsData`.
2. **Nothing in `resources/js/` dispatches `analyzeSavings`.** The action, and the
   `POST /savings/analyze` endpoint behind it, are unreached from the SPA.
3. Even if it were called, the store commits `responseData.analysis` where
   `responseData` is already the analysis payload — so it would commit `undefined`.

The W-0241 `transfer_value` shape again: a missing key swallowed silently, wrong
forever, with no error. `SavingsAgent::analyze()` computes exactly the figures
`/savings` needs — `summary.total_savings` from
`CrossModuleAssetAggregator::calculateCashTotal()` and
`emergency_fund.runway_months` from it — and none of them reach the page.

W-0274's acceptance is met without this, because the fund value is the sum of the
API's own per-record `user_share`. But the **runway** only agrees with the dashboard
while `users.monthly_expenditure` happens to equal what the backend's expenditure
resolver picks — and the persona already proves the resolver branches: David
resolves from `expenditure_profile`, Sarah from `user_monthly`.

## Acceptance

1. `/savings` carries the emergency-fund figures the page displays, or the dead
   placeholder and the dead action are removed — not left reading as data that
   arrives.
2. `adequacy.adequacy_score` does **not** reach the client (Rule 12).
3. The store commits a key that exists, proven by a test that fails when it does not.

## Working notes — build-lead, 2026-08-23

**DONE.** Team-lead authorised the controller change.

`/api/savings` now carries `analysis.emergency_fund.runway_months`,
`analysis.summary.total_savings` and `analysis.summary.expenditure_source`, from
`SavingsAgent::analyze()`. The store's `setAnalysis` commit was fed a key that
exists — it read `responseData.analysis` where `responseData` IS the analysis, which
the guard three lines above proves by reading `can_proceed` off it directly.

**Deliberately narrow, and that is the point.** `emergency_fund.adequacy` carries an
`adequacy_score`. Shipping the block wholesale would have put a numerical rating on
the wire (Rule 12). Guarded by a test that asserts against the whole response body,
not one key — because the score would arrive by someone widening the block, not by
someone adding that key by name. Mutation-verified: shipping the whole block turns
that test red.

Verified at the endpoint, both accounts: David 79.8 months / £99,750 from
`expenditure_profile`; Sarah 25.33 / £31,030 from `user_monthly`. **The two branches
of the resolver chain, on one household** — which is why the runway had to be read
rather than divided client-side.

Tests: `tests/Feature/Savings/SavingsEmergencyFundPayloadTest.php` (3).
