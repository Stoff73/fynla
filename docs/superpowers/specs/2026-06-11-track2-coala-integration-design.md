# Track 2 — CoALA Integration of the Reconciled Recommendation Catalogue

**Date:** 2026-06-11
**Status:** Approved by CSJ (brainstorm 2026-06-11, session 3)
**Parent spec:** `docs/superpowers/specs/2026-06-10-recommendation-insight-quality-design.md` §7 (Track 2 scope) — this doc binds §7's five items to execution decisions; it does not re-open Track 1.
**Branch:** all Track 2 work lands on `coala` (standing rule: CoALA PRs target `coala`, not dev). The coala → dev landing is a separate, later programme.

## 1. Context (verified 2026-06-11)

- Track 1 is delivered on dev (PR #528): reconciled catalogue with metadata columns (`claim_tier`, `required_data`, `sequencing`), 20 live `source='strategy'` tax rows, strategy classes as single evaluators, `StrategyPlanComposer` + `ComposedTaxPlanService`, `HouseholdFinancialContext`, rewritten `get_recommendations` description in `AiToolDefinitions`/`XaiToolDefinitions`, knowledge layer restored to the unified prompt.
- coala has: the semantic substrate (PR #439) with an **empty** `fyn-memory/semantic/house_view/` scaffold; the Phase-4 pointer registry incl. the `Recommendation` fetch handler (returns `orchestrateAnalysis`'s raw `ranked_recommendations`); the dual-provider corpus-driven tool catalogue (`fyn-memory/procedural/tool_schema/analysis/get_recommendations.md` + `.xai.md`) with byte-identity golden masters; FynLoop + planner (Phase 5 items 1–8, merged); procedural categories `pointers`/`tool_schema`/`workflow` (no `overlay`).
- coala is 168 ahead / 293 behind dev. `git merge-tree` reports **zero textual conflicts** in either direction — Track 1's 1f was built additively in coala's injection style by design.
- v0.5 pointer model is canonical: the corpus holds **no figures**; rates/allowances/thresholds are procedural pointers to live sources. The Phase-1 `fyn:semantic:reindex` no-£ guard enforces this mechanically.
- `FynSystemPrompt::text()` byte-invariance is canonical — no Track 2 change touches it.

## 2. Locked decisions (CSJ, 2026-06-11)

1. **Branch strategy: dev → coala first.** One merge commit brings Track 1 (and everything since) into coala; all five §7 items then build on coala with both halves present. The eventual coala → dev upload ships FynLoop + Track 2 together after its own verification programme.
2. **Corpus scope: the tax strategy set first** (~20 files, one per live `source='strategy'` row). Module-by-module growth is later ongoing content work. `fca` exemplar authoring stays paused.
3. **Compliance review: at the PR.** CSJ's review of the corpus PR is the compliance review; merge wires the files into retrieval. coala is deployed nowhere, so go-live-on-merge has no user exposure.
4. **Planner: heuristics only, feature flag unchanged.** Tests exercise the heuristics flag-on. Flipping the planner default is a landing-programme decision. **Overlays: corpus + loader category only** — no capture-turn consumption wiring (that remains the finish-Phase-4 overlay-externalisation item).

## 3. Sequencing — the merge is step 0, and one ordering is forced

**Step 0: `dev → coala` merge** (single merge commit, no conflicts expected).

**Forced ordering:** coala's tool-catalogue golden masters assert byte-identity between the `tool_schema` corpus and the PHP catalogue. Track 1 changed the `get_recommendations` description in the PHP classes on dev, so **the golden masters fail the moment the merge lands** until the corpus pair (`get_recommendations.md` + `.xai.md`) is updated to match. §7's "mirror the description into the tool_schema corpus" is therefore the merge-green requirement, sequenced immediately after the merge inside the same PR.

**Post-merge verification gate (before any other Track 2 work):**
- Full coala suite green, including regenerated tool-schema golden masters and the `FynSystemPrompt` byte-invariance snapshot (file untouched).
- FynLoop proof tests green (text turn + advice-mode write-strip e2e).
- **Azlan golden eval scenario replayed on coala** — post-merge, coala has both the eval harness and the golden scenarios; passing proves FynLoop and Track 1's assembler layers compose at runtime, not just at merge time. Fixture-mode is the cheap pre-check; **one live grok-4.3 run is the required gate** (fixtures omit tool inputs — the known Track 1 caveat — so only a live run polices the must-surface assertions), matching how Track 1 ran its golden scenarios.

## 4. Workstreams

### 4a. house_view corpus (~20 files)

One file per live tax strategy id, named by strategy id, following `fyn-memory/semantic/_TEMPLATE.md` frontmatter. Content per file:
- **Narrative** — what the strategy is, when it applies, who it serves.
- **Methodology rationale** — why Fynla quantifies it the way it does (the strategy class's approach, in prose).
- **Sequencing reasoning** — the `do_before` / `conflicts_with` logic explained (e.g. own-ISA wrap before gifting savings to spouse).
- **Claim tier + voicing guidance** — mechanical vs judgement, per the tiered-boldness rule.

Constraints: **zero figures** (pointer model; the no-£ reindex guard polices it); source material is dev's catalogue rows, the strategy classes, and `FinancialPlanningKnowledge`; British English; Rules 9/12/15 apply to any copy that can surface to users.

Acceptance: validator green; a retrieval test proves a recommendation-intent query serves house_view hits in the `<knowledge>` block.

### 4b. RecommendationHandler returns the composed plan

The Phase-4 `Recommendation` fetch handler swaps its payload from raw `ranked_recommendations` to the `StrategyPlanComposer` structured plan (top strategies with working, sequencing, claim tiers, locked-strategy count) — the same substance as dev's post-Track-1 `get_recommendations`. Provenance recording and fail-degrade semantics unchanged. (The plan re-verifies the handler's current return path before changing it — the description above is from a 9-day-old memory.)

Acceptance: handler unit tests against the composed shape; a **shape-parity test** pinning handler output against `get_recommendations` so pointer and tool cannot drift.

### 4c. Planner heuristics

Routing guidance added to the planner prompt: recommendation intent → fetch the composed plan (the pointer); strategy locked by missing data → ask the unlock question. Flag unchanged.

Acceptance: flag-on planner tests assert the routed `Action` for each intent.

### 4d. Capture-turn overlays

New `fyn-memory/procedural/overlay/` category. Two files: A1 answer-the-user-first and A2 ack-hygiene, mirroring Track 1's behavioural rules as overlay `.md`. Loader, validator, and reindex recognise the category. **No consumption wiring.**

Acceptance: validator/loader tests for the new category; overlay content matches Track 1's A1/A2 rules (reviewed in the PR).

### 4e. tool_schema mirror (inside the step-0 PR)

`get_recommendations.md` + `get_recommendations.xai.md` updated to Track 1's rewritten description; golden masters regenerated; dual-provider parity tests green.

## 5. Out of scope

Planner default flip; overlay consumption wiring; Option-A shell deletion; the coala → dev landing; `fca` corpus authoring; non-tax corpus growth; any change to `FynSystemPrompt::text()`; any change to the write-safety contract.

## 6. Success criteria

1. dev → coala merged; coala suite, golden masters, and FynLoop proof tests green; Azlan golden scenario green on coala.
2. ~20 house_view files pass the no-figures validator and retrieval provably serves them on recommendation-intent queries.
3. RecommendationHandler shape-parity with `get_recommendations` green.
4. Planner heuristic routing tests green (flag-on).
5. Overlay category validated; A1/A2 content matches Track 1's rules.
