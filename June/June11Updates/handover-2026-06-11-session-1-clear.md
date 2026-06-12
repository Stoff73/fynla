---
type: handover
mode: context-clear
date: 2026-06-11
session: 1
branch: dev
---

# Context Clear Handover — 2026-06-11, Session 1

## Immediate state
Track 1 of the recommendation/insight-quality programme is **fully delivered, merged ([PR #528](https://github.com/Stoff73/fynla/pull/528) → dev @ `4e211aa`), and deployed + verified on csjones** (deploy note: `June/June11Updates/deploy-2026-06-11.md`). Session ended cleanly at the wrap — nothing in flight.

## The thread
- CSJ's brief: the recommendation engine + Fyn weren't surfacing real, personalised insights (the 2026-06-09 Azlan transcript was the evidence). Brainstormed → spec (`docs/superpowers/specs/2026-06-10-recommendation-insight-quality-design.md`) → 25-task plan (`docs/superpowers/plans/2026-06-10-recommendation-insight-quality-track1.md`) → executed subagent-driven with two-stage review per task.
- Key discoveries en route: THREE pre-existing catalogue layers (March DB action-definitions — tax one orphaned; June strategy classes; FinancialPlanningKnowledge prompt layer — dropped from the unified prompt at the 2026-05-17 cutover, now restored).
- Live Playwright replay of the full Azlan journey verified every fix and found six more defects, fixed in-loop (decimal dedupe, ISA subscription capture field, pension total concentration, "gia holdings" label leak, saving-suffix duplication, page-vs-chat total mismatch).
- Golden eval scenarios (Azlan + 4 personas) GREEN on live grok-4.3; the harness gained multi-turn assertions, a mid-campaign Azlan persona (`eval:setup-azlan`), and a per-provider reset fix.

## Files touched
All committed and pushed — dev @ `4e211aa` (48 commits), working tree clean. The two untracked files (`docs/mobile/designer-brief.pdf`, `docs/security/security-review-2026-06-09.md`) pre-date the session and were deliberately left alone.

## What the next Claude needs to know
- **Two task chips are waiting when CSJ wants them: the 24 pre-existing dev test failures (mortgage fixtures, gamification seeder boundary breaches, etc.) and the broken `GiftFactory` enums. And whenever ready, Track 2 planning picks up from the spec — the coala `house_view` corpus now has the catalogue as its authoring source** (spec §7 lists the full Track 2 scope: house_view authored from catalogue ids, `RecommendationHandler` pointer returns the composed plan, planner heuristics, capture overlays, tool_schema `.md` mirroring of the new `get_recommendations` description).
- Honest caveat carried from the delivery: live-LLM supply of the optional `isa_subscription_amount` field is unit-proven through the whole pipeline but not yet observed in a recorded fixture (the SSE fixture format omits tool inputs) — the Azlan golden scenario's assertions police it going forward.
- Deferred polish (reviewed, accepted, not built): `ComposedTaxPlanService` double-invocation on GET /api/tax-strategy (~100ms, refactor candidate); conflict notes deliberately omitted from per-section advice (synthesis-only); live-stream renders answer+retry as one merged bubble vs two rows on reload; `{success, message}` envelope missing on the tax-strategy endpoint (pre-existing).
- The full suite shows 24 failures, ALL verified pre-existing on dev (files byte-identical to origin/dev) — that's the first chip. ±1-2 run-to-run flakes also pre-existing (random-factory class).
- gh CLI merge endpoint still 401s on this machine (CSJTODO outstanding item — `gh auth refresh -h github.com -s repo`); PR create/read/api work; workaround used again this session: local merge commit + push.

## Pick up from here
1. CSJ re-tests the Azlan journey on https://csjones.co/fynla (homepage → Save tax now → register fresh) — expect: salary-sacrifice question answered, stated-content acks, total-vs-per-year clarification, numbered synthesis with realisable total, matching figure on /tax-strategy.
2. If happy: dev → main release PR is CSJ's call (NOT recommended proactively per standing rule).
3. Start either chip, or Track 2 planning (brainstorm from spec §7 — most of the design is already settled there).
