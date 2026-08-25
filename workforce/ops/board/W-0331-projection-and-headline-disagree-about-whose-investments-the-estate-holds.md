---
id: W-0331
title: The projected estate and the current estate applied different ownership rules to the same investments
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T23:05:00Z
claimed: 2026-08-22T23:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [F-0019, F-0024, W-0280, CrossModuleAssetAggregator, SharedOwnership, CalculatesOwnershipShare]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Raised by team-lead from **F-0024 §10 / W-0280 §1** as an inheritance tax double
count. **It is not one — see the correction below, which matters because W-0280
governs a 59-site sweep.**

`IHTCalculationService::calculate()` returns the current estate and the projected
estate in one response. The headline reads `gatherUserAssets()` — `forUserOrJoint`
reach at `calculateUserShare` fraction. The projection read
`InvestmentAccount::where('user_id', …)->sum('current_value')` at 100%, at five
sites (`:725`, `:728`, `:758`, `:763`, `:778`, `:782`). Two ownership rules, one
response.

### Correction to W-0280 §1

A row carries exactly ONE `user_id`, so `where('user_id', $user->id)` and
`where('user_id', $spouse->id)` are **disjoint** and no row can be counted twice by
that pair. Measured: household investments are **£305,000** under both the original
code and the reach-and-share reader. **The five named sites move this household's
tax by £0.**

### What was actually wrong

1. **A third party's share carried in.** A shared record whose co-owner has no
   account (`joint_owner_id` NULL) was taken whole.
2. **Data sharing off.** David £220,000 against a headline of £172,500 — £47,500 of
   Sarah's money in his estate. Sarah £85,000 against £132,500 — her share of the
   joint account missing entirely.
3. **Between the code paths.** The simulation is share-aware, the fallback was not,
   so a run where one member simulated and the other fell back counted a joint
   record at more than its value. A real double count, in the branch nobody named.

## Acceptance

1. No joint record contributes twice to any Inheritance Tax figure. ✅ proven by
   `tests/Unit/Services/Estate/IHTProjectionOwnershipTest.php`, mutation-tested both
   ways.
2. The projection reads the same ownership rule as the headline beside it. ✅ routed
   to `CrossModuleAssetAggregator::calculateInvestmentTotal`.
3. No hardcoded tax value; no threshold adjusted to compensate (Rule 2). ✅ nothing
   in `TaxConfigService` touched.
4. Before/after stated. ✅ F-0026 §6 — **no user's liability fell**, and why.

## Working notes

- Four try/catch fallback copies collapsed into one `projectMemberInvestments()`.
- `projectInvestments()` / `getCurrentInvestmentValue()` are unreachable → W-0334.
- `projectProperties` carries a live £177,000 third-party share → W-0333, flagged to
  team-lead, **not landed**.
- Tests run on `laravel_testing_e`; `laravel_testing_a` was held by another batch.
