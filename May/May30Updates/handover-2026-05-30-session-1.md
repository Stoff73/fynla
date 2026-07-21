---
type: handover
mode: end-of-day
date: 2026-05-30
session: 1
branch: feat/coala-ground-gate
previous_session: 2026-05-29 session 5 (context-clear)
---

# Handover — 2026-05-30, Session 1

## Where we left off

Spent the day building the first two CoALA Phase 5 PRs on top of the green
test-stabilisation gate. **PR 1 (prompt-cache hit/miss + GBP cost telemetry)**
and **PR 2 (the `ground` mechanical write-safety gate)** are both built, fully
TDD'd, committed, and **pushed to origin** (no PRs opened). A separate
**test-stabilisation** branch fixes two pre-existing gate issues. Nothing is
merged to `coala`, nothing deployed. `coala` itself is untouched (== origin/coala).

## What shipped today

(All on local→pushed feature branches off `coala`; none merged.)

- **`feat/coala-cost-telemetry` (`d390f67`)** — Phase 5 PR 1. Captures Anthropic
  `cache_creation_input_tokens` (MISS) alongside the existing cache-read (HIT);
  persists distinct `cache_hit_tokens` / `cache_miss_tokens` + per-turn `gbp_cost`
  in `ai_messages.metadata` (JSON, no migration). New `config/ai_pricing.php`
  rate table + `App\Services\AI\Cost\AiCostCalculator` (priced flag so unknown
  models never silently cost £0). New `HasAiChat::costTelemetryMetadata()`
  normalises Anthropic vs xAI token semantics. 12 new unit tests.
- **`fix/coala-test-stabilisation` (`7384aea`)** — de-flaked `SavingsAgentGoalsTest`
  (pinned `start_date`; root cause was GoalFactory's random start_date flipping
  the time-based `is_on_track` ~10% of runs — NOT order-dependent bleed) +
  added `declare(strict_types=1)` to the 8 content-pipeline files that were
  failing the Architecture suite (HeyGen/ImageRenderer/FFmpeg/ArticleScraper
  services + Approval/Article/PipelineAsset/PipelineRun models).
- **`feat/coala-ground-gate` (`383796c`, stacks on stabilisation)** — Phase 5
  PR 2. New `App\Services\AI\Ground\GroundGate` mechanically rejects any
  `AdviceFyn::WRITE_TOOLS` surface in the read-only 'advice' persona at the
  `HasAiChat` dispatch boundary (defence-in-depth behind the catalogue strip);
  audited `status:stripped`; `rejectGroundSurface()` returns a non-executing
  observation. `WRITE_TOOLS` promoted private→public (single source of truth).
  10 new tests incl. drift guards (orphan check + write-shaped-tool guard =
  the plan's "paired test per tool").

## What's in flight (NOT done)

- **No PRs opened.** The three branches are pushed but not PR'd. CSJ confirmed
  CoALA PRs target **`coala`** (feature → coala), not dev.
- **Phase 5 remaining items (3–8):** typed `Action` enum + dispatcher refactor,
  `FynLoop` service, planner LLM call, concurrent-turn queue, resumption check,
  per-action cost-attribution table + admin dashboard. (See
  `fynla-coala-implementation-plan.md` §8 internal sequencing.)
- **Two-Fyn collapse A/B decision** (plan §"Two-Fyn collapse") is still
  **deferred to CSJ** — plan recommends Option B (shared loop + thin shells).
  The ground gate was built additively (Option-B compatible) so this decision
  is NOT yet forced.

## Deploy status

**Nothing to deploy.** All work is on unmerged local/pushed feature branches.
`coala` unchanged; dev + prod (fynla.org, healthy on #436) unaffected.

The `2026_05_29_180000_widen_loan_to_value_pct_on_properties` migration is
still Pending on dev/csjones/prod (ran in test DB only) — harmless additive
ALTER, ships whenever coala→dev→prod eventually lands.

## Tech debt found this session

- **`config/ai_pricing.php` rates are derived placeholders** (USD list × 0.79
  FX, documented in-file). MUST be verified against live Anthropic/xAI pricing
  pages before the `gbp_cost` figures are relied on for FCA/board reporting.
- **No stream-mock test harness for the AI chat loop.** Both PR 1 (metadata
  persistence) and PR 2 (gate execution inside the loop) lack full end-to-end
  coverage because the suite has no harness mocking `messages->createStream` /
  `XaiClient`. Unit coverage (calculator, telemetry assembly, gate predicate,
  reject path) is solid; e2e is observable via the admin AI-Audit view. Building
  a stream-mock harness is its own future task.
- **xAI cache-creation:** OpenAI-compatible usage exposes cached-read only, no
  cache-creation tier — recorded as 0 for xAI (documented). Re-check if xAI adds
  the field.

## Known issues / blockers

- **NEW flake: `tests/Feature/Admin/ActuarialLifeTableAdminTest`** — fails
  intermittently in the full suite, **passes in isolation** (order-dependent,
  unrelated to today's work). This is a DIFFERENT flake from the SavingsAgent
  one fixed today. The suite now has ≥2 known order-dependent flakes →
  suggests a systemic test-isolation issue worth a dedicated stabilisation
  sweep (scoped queries / shared-state / Carbon leaks). Full suite this session:
  4081 passed / 1 failed (the Actuarial flake).
- Architecture suite: 117 passed / 0 failed (the strict_types fixes closed it).

## Rules reinforced this session

- **CoALA PRs target `coala`, not dev** (CSJ decision this session).
- **Pint strips unused imports on PostToolUse** — add the `use` import and its
  usage in the same logical sequence (usage first, then import), or Pint removes
  the import before the usage lands. Bit me twice today.
- New project memory written: `project_coala_phase5_progress.md` (see MEMORY.md).

## Next session should

1. **Decide the immediate path:** either (a) open the three feature→coala PRs
   for review/merge, or (b) continue building Phase 5 item 3 (typed `Action`
   enum + dispatcher refactor) on a new branch off `coala` (or off
   `feat/coala-ground-gate` if stacking).
2. **Optionally** detour to fix the `ActuarialLifeTableAdminTest` flake — likely
   another unscoped query / shared-state issue. Reproduce by running the full
   `Unit,Feature` suite (it's intermittent ~once per full run); diagnose with
   the systematic-debugging skill (root cause, not a retry band-aid).
3. If continuing Phase 5: read `fynla-coala-implementation-plan.md` lines
   293–327 (the typed Action enum + plan→execute loop) and §8 sequencing.
   Item 3 wraps the existing flat tool catalogue in `reason|retrieve|learn|ground`.
4. The Two-Fyn A/B collapse decision is still open — surface it before the
   FynLoop work (item 4) actually needs it; Option B is the plan's recommendation.

## Context hints

- Active branch type: mixed (CoALA feature work)
- `coala` behind origin: 0 (== origin/coala); main/dev unaffected
- Uncommitted: none, working tree clean (only untracked `docs/mobile/designer-brief.pdf` — CSJ's file, leave it)
- Last commit: `383796c` feat(coala): ground surface gate — mechanical write-safety boundary (Phase 5 PR 2)
- Branch tips: coala=`0124749` · cost-telemetry=`d390f67` · stabilisation=`7384aea` · ground-gate=`383796c` (all three pushed to origin)
