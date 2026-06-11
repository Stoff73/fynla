# Track 2 — CoALA Integration of the Reconciled Recommendation Catalogue

**Date:** 2026-06-11 (v2 — reframed across all four CoALA memory modules after CSJ review)
**Status:** Awaiting CSJ review
**Parent spec:** `docs/superpowers/specs/2026-06-10-recommendation-insight-quality-design.md` §7 — this doc binds §7 to execution decisions and to the CoALA architecture; it does not re-open Track 1.
**Canonical architecture:** `fynla-coala-implementation-plan.md` **v0.5** (the copy on `coala`; dev's root copy is a stale v0.4 — the step-0 merge resolves to v0.5 automatically, no conflict) + the CoALA paper (Sumers et al., arXiv:2309.02427).
**Branch:** all Track 2 work lands on `coala`. The coala → dev landing is a separate, later programme.

## 1. CoALA framing — what Track 2 is, in the architecture's own terms

CoALA structures an agent as **four memory modules** (short-term working memory + long-term episodic/semantic/procedural), an **action space** (internal: retrieval / reasoning / learning; external: grounding), and a **decision cycle** (plan: propose→evaluate→select; then execute). Track 1 built the recommendation *substance* (catalogue, strategy classes, composer) as ordinary dev services. **Track 2's job is to make that substance memory-native on coala so Fyn's advice quality compounds**: the rationale lives in semantic memory, the fetch routes live in procedural memory, what was advised lands in episodic memory, and the composed plan flows through working memory on every recommendation turn.

For the user, this is the difference between a chatbot that recomputes advice from scratch and a **financial companion**: Fyn quantifies recommendations with the user's own numbers, explains the house view behind them, remembers what it already advised (and doesn't blindly re-pitch it), and every claim is mechanically traceable for FCA suitability — inputs, knowledge version, and workflow that produced it.

### Fyn ↔ CoALA mapping (state on `coala`, verified 2026-06-11)

| CoALA module | Canonical plan phase | Built on coala? | Track 2 touch |
|---|---|---|---|
| **Working memory** | Phase 3 (consolidated VO) | **Not built** — `FynTurnContext` + `FynContextAssembler` + `persona_state` remain the shape | Composed plan becomes a named per-turn context layer; A4 synthesis persistence verified under FynLoop (§4d) |
| **Episodic** | Phase 2 (SQL+`.md` hybrid) | Built — `ai_messages` + blobs, fetch provenance collector, hash chain | Strategy-id granularity in fetch provenance; re-pitch awareness (§4e) |
| **Semantic** | Phase 1 (corpus + retriever) | Built — sparse retriever, `<knowledge>` block, no-£ guard; `house_view/` **empty** | house_view corpus authored from the catalogue (§4a) |
| **Procedural** | Phase 4 (pointer registry + `.md` corpus) | Built (4b–4f) — pointer registry, dual-provider tool_schema corpus + golden masters, `workflow/`; **no `overlay/` category** | RecommendationHandler returns the composed plan (§4b); tool_schema mirror (§4f); overlay category + A1/A2 files (§4c) |
| **Decision cycle** | Phase 5 (FynLoop + planner) | Built (items 1–8), planner behind feature flag | Planner routing heuristics (§4c) |
| **Learning actions** | Phase 6 | **Not built** | Out of scope — explicitly deferred (§5) |

v0.5 pointer model governs throughout: **memory holds pointers, not copies.** Figures (rates, allowances, thresholds, user data, computed plans) are never frozen into the corpus — they are fetched live through procedural pointers; only source-less narrative (fca / house_view) is semantic content. The `fyn:semantic:reindex` no-£ guard polices this mechanically.

## 2. Locked decisions (CSJ, 2026-06-11)

1. **Branch strategy: dev → coala first.** One merge commit brings Track 1 (and everything since) into coala; `git merge-tree` reports zero textual conflicts either direction. The eventual coala → dev upload ships FynLoop + Track 2 together after its own verification programme.
2. **Corpus scope: the tax strategy set first** (~20 files, one per live `source='strategy'` row). Module growth is later content work; `fca` exemplar authoring stays paused.
3. **Compliance review: at the PR.** CSJ's review of the corpus PR is the compliance review; merge wires files into retrieval. coala is deployed nowhere.
4. **Planner: heuristics only, feature flag unchanged.** **Overlays: corpus + loader category only** — capture-turn consumption stays with the finish-Phase-4 item.

## 3. Sequencing — the merge is step 0, and one ordering is forced

**Step 0: `dev → coala` merge** (single merge commit). Forced ordering: coala's tool-catalogue **golden masters assert byte-identity between the `tool_schema` corpus and the PHP catalogue**; Track 1 changed `get_recommendations`' description in the PHP classes, so the golden masters fail the moment the merge lands until the corpus pair (`get_recommendations.md` + `.xai.md`) is updated. §4f is therefore part of the step-0 PR, not a follow-up.

**Post-merge verification gate (before any other Track 2 work):**
- Full coala suite green, including regenerated golden masters and the `FynSystemPrompt` byte-invariance snapshot (the static prompt is untouched by everything in this spec — canonical prefix-cache constraint).
- FynLoop proof tests green (text turn + advice-mode write-strip e2e); GroundGate/SurfaceAllowlist parity tests green (Track 2 must not widen any write surface — pointers fetch, never write).
- **Azlan golden eval scenario replayed on coala.** Fixture-mode as the cheap pre-check; **one live grok-4.3 run is the required gate** (fixtures omit tool inputs — the known Track 1 caveat), matching how Track 1 ran its golden scenarios. Passing proves FynLoop and Track 1's assembler layers compose at runtime.
- Root `fynla-coala-implementation-plan.md` resolves to v0.5 (coala's copy supersedes dev's stale v0.4).

## 4. Workstreams (organised by memory module)

### 4a. Semantic — house_view corpus (~20 files)

One file per live tax strategy id, named by strategy id, following `fyn-memory/semantic/_TEMPLATE.md`. Content per file: **narrative** (what/when/who), **methodology rationale** (why Fynla quantifies it this way), **sequencing reasoning** (the `do_before`/`conflicts_with` logic in prose), **claim tier + voicing guidance** (mechanical vs judgement, per tiered boldness). Constraints: zero figures (pointer model; no-£ guard); source material is dev's catalogue rows, the strategy classes, and `FinancialPlanningKnowledge`; British English; Rules 9/12/15 apply to user-surfaceable copy.

Acceptance: validator green; a retrieval test proves a recommendation-intent query serves house_view hits in the `<knowledge>` block.

### 4b. Procedural — RecommendationHandler returns the composed plan

The Phase-4 `Recommendation` fetch handler swaps its payload from raw `ranked_recommendations` to the `StrategyPlanComposer` structured plan (top strategies with working, sequencing, claim tiers, locked-strategy count) — the same substance as dev's post-Track-1 `get_recommendations`, so the pointer and the tool cannot disagree. Fail-degrade semantics unchanged. A pointer fetches data; it never widens write permission (v0.5 rule — the ground gate is untouched). (Plan re-verifies the handler's current return path before changing it; the description here is from a 9-day-old memory.)

Acceptance: handler unit tests against the composed shape; a **shape-parity test** pinning handler output against `get_recommendations`.

### 4c. Procedural + decision cycle — overlays and planner heuristics

- **Overlay category:** new `fyn-memory/procedural/overlay/` (only `pointers`/`tool_schema`/`workflow` exist). Two files — A1 answer-the-user-first, A2 ack-hygiene — mirroring Track 1's behavioural rules. Loader, validator, and reindex recognise the category; **no consumption wiring** (finish-Phase-4 item).
- **Planner heuristics:** routing guidance in the planner prompt — recommendation intent → `retrieve` via the Recommendation pointer (the composed plan); strategy locked by missing data → ask the unlock question (`reason`, not a blind `ground`). Feature flag unchanged; tests run flag-on and assert the routed `Action` per intent.

### 4d. Working memory — the composed plan as a named per-turn layer

Phase 3 (the consolidated `WorkingMemory` VO) is **not built and not built here** — Track 2 stays compatible with the current `FynTurnContext` + `FynContextAssembler` shape. Within that shape:

- On recommendation-intent advice turns, the composed plan enters working memory through the pointer pre-fetch (`<live_data>` block) or the `get_recommendations` tool result — one substance, two retrieval routes, both landing in the same per-turn context. A test asserts the composed plan is present in assembled context on a recommendation-intent turn (and absent on an unrelated one — B3's module-scoping economy applies; strategy *headlines* stay holistic per the parent spec).
- **A4 synthesis persistence under FynLoop:** Track 1 persists the savetax synthesis so `/tax-strategy` shows exactly what Fyn said. Post-merge this flows through FynLoop — a test (and the Azlan replay) verifies the persisted synthesis still matches the chat output under the shared loop. No new persistence mechanism; this is a does-it-survive-the-merge guarantee.

Acceptance: the two tests above; no change to `FynTurnContext`'s shape or `persona_state` handling (Phase 3's job, not ours).

### 4e. Episodic — record what was advised, and don't blindly re-pitch

Episodic memory (Phase 2) already records every turn with fetch provenance (what was fetched, source@version). Track 2 adds **strategy-id granularity**:

- When the Recommendation pointer (or `get_recommendations`) returns a composed plan, the fetch provenance records the **surfaced strategy ids** (and the composed-plan version/hash), so every episode answers "which strategies did Fyn put in front of this user, when" — the FCA suitability trace for recommendations, and the substrate for future recall. (Verify-first: the Phase-2 collector may already capture tool results at this granularity — if so, this is a test, not code.)
- **Re-pitch awareness at the working-memory level:** the `get_recommendations` tool description and the planner heuristic instruct Fyn to check the current conversation context for strategies already surfaced this session before re-pitching, and to acknowledge prior discussion when re-surfacing ("as we discussed…"). This is prompt-level, cheap, and session-scoped.
- **Cross-session recall is deferred:** dense similar-case retrieval over episodes is Phase 6 by the canonical plan, and the SQL `RecommendationTracking` done-state (which the aggregator/composer already consult to exclude completed recommendations) remains the cross-session mechanism for "done" state. Named here so the boundary is explicit, not forgotten.

Acceptance: a feature test asserting an episode row/blob for a recommendation turn carries the surfaced strategy ids; tool-description/planner text reviewed in the PR.

### 4f. Procedural — tool_schema mirror (inside the step-0 PR)

`get_recommendations.md` + `get_recommendations.xai.md` updated to Track 1's rewritten description (plus the re-pitch guidance from §4e); golden masters regenerated; dual-provider parity tests green.

## 5. Out of scope

Phase 3 (WorkingMemory VO consolidation, `persona_state` migration); Phase 6 (learning actions — episodic→semantic promotion, dense similar-case recall); planner default flip; overlay consumption wiring; Option-A shell deletion; the coala → dev landing; `fca` corpus authoring; non-tax corpus growth; any change to `FynSystemPrompt::text()`; any change to the write-safety contract or ground-gate surfaces.

## 6. Success criteria

1. dev → coala merged; coala suite, golden masters, FynLoop proof + ground-gate parity tests green; **live-grok Azlan golden scenario green on coala**; root plan file at v0.5.
2. ~20 house_view files pass the no-figures validator; retrieval provably serves them on recommendation-intent queries.
3. RecommendationHandler shape-parity with `get_recommendations` green.
4. Planner heuristic routing tests green (flag-on).
5. Overlay category validated; A1/A2 content matches Track 1's rules.
6. Composed plan provably present in working memory on recommendation-intent turns; A4 synthesis persistence verified under FynLoop.
7. Episodes record surfaced strategy ids; re-pitch guidance present in the tool description and planner prompt.
