---
id: W-0478
title: Two insight mechanisms exist and the one with tests has no client — /m renders the other
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [quality-lead]
status: queued
claimed_by: null
severity: medium
surfaces: [m, native]
created: 2026-08-24T12:45:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0473]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found while fixing W-0473, 2026-08-24 — searching for the surface that would show the revived insights turned up no caller at all
---

## Intent

Fynla produces a daily insight **twice, from two unrelated readers**, and the two
have never been reconciled:

| Mechanism | Reads | Consumed by |
|---|---|---|
| `InsightsController::extractInsights()` (`GET /api/v1/mobile/insights/daily`) | `CoordinatingAgent::analyze()` — six modules, figures and all | **Nothing.** No caller in `resources/`, `resources/mobile/`, or `ios-native/` |
| `MobileDashboardAggregator::generateFynInsight()` (`fyn_insight` in the dashboard payload) | its own `aggregateModules()` shape | `resources/mobile/views/Dashboard.vue:163` — this is what `/m` shows |

W-0473 revived the first: it now yields five figure-bearing insights for user 14
where it produced none. **Nobody can see any of them.** The endpoint sits on
`routes/api_v1.php` (the native surface) and no native screen calls it either.

Meanwhile the mechanism that *is* on screen carries no figures and none of the
qualification work: it says *"Your estate may have an inheritance tax liability"*
where the other names £58,500 **and appends `unmodelled_relief_caveat`**.

This is Rule 20 in its plainest form — one behaviour, two homes, and every past fix
landing in whichever one the fixer happened to open. The W-0466 caveat work went into
the branch nobody reads.

## Acceptance

1. **A CSJ decision, not a code decision:** either a client is wired to the endpoint
   (`/m` and native both), or the endpoint is deleted and the aggregator becomes the
   one home. Do not implement both.
2. Whichever survives, exactly ONE mechanism composes the daily insight afterwards,
   and the other is gone — not left dormant behind a flag.
3. If the aggregator survives, the figure-bearing insights and the
   `unmodelled_relief_caveat` handling must move to it — the caveat cannot be lost in
   the consolidation (see W-0466, W-0473 acceptance 3).
4. `tests/Feature/Mobile/InsightsTest.php` follows whichever mechanism survives.

## Working notes

- 2026-08-24 — Filed from W-0473. Not fixed there: deleting a live endpoint or
  wiring two clients to it is a scope and product call, and W-0473's business was the
  reader level.
- 2026-08-24 — Worth knowing before choosing: the endpoint's reader is the richer of
  the two (six modules, real figures, caveat-aware, now covered by mutation-proved
  tests). The aggregator's is prose-only but is the one users actually see.
