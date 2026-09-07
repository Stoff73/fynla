---
id: W-0478
title: Two insight mechanisms exist and the one with tests has no client — /m renders the other
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: main-inference
reviewers: [quality-lead]
status: done
closed: 2026-08-29
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
- 2026-08-24 — **Done. CSJ chose "wire the clients to the endpoint — the richer
  mechanism wins."** Built as one shared composer rather than a second HTTP call from
  the dashboard, because measurement changed the picture: **both `/m` AND native
  already read `fyn_insight` from the dashboard payload**
  (`resources/mobile/views/Dashboard.vue:559`, `ios-native/.../DashboardView.swift:122`),
  so pointing them at the endpoint would have added a request to a screen that already
  had the data. `DailyInsightService` holds the sentences and thresholds; the endpoint
  and the dashboard both read it; `generateFynInsight` is deleted. Same outcome — the
  richer mechanism wins and only one exists — without a second round trip.
- 2026-08-24 — `compose()` takes the module payloads its caller already has instead of
  fetching its own. The aggregator calls all six agents to build its summaries; a
  service that re-fetched would double that on the dashboard hot path.
- 2026-08-24 — **One asymmetry, stated in the class rather than hidden:** the dashboard
  aggregator has no tax agent, so it supplies no `tax` payload and the tax-strategy
  insight is unreachable from that caller. Adding `TaxOptimisationAgent` to the
  dashboard would put a strategy computation on every load.
- 2026-08-24 — Measured on user 14: `/m` and native now receive *"Your estimated
  Inheritance Tax liability is 58,500.00…"* **with the `unmodelled_relief_caveat`**,
  where they previously received prose with no figure.
- 2026-08-24 — **Filed W-0479 from a test fixture correction.** The fixture mocked
  protection gaps as `['life' => ['gap' => …]]`, a shape the agent has never emitted.
  The dashboard's `critical_gaps` counter reads that same invented shape, so it is 0
  for every household on all three surfaces.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`gated`.

- **Delivered by:** Stoff73
- **Evidence:** merged in #714; commit `cd8d5c4aa` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
