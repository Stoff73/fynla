# Track 2 — CoALA Integration of the Reconciled Recommendation Catalogue

**Date:** 2026-06-11 (v3 — corrected working-memory definition; full memory-systems table per CSJ)
**Status:** Awaiting CSJ review
**Parent spec:** `docs/superpowers/specs/2026-06-10-recommendation-insight-quality-design.md` §7 — this doc binds §7 to execution decisions and to the CoALA architecture; it does not re-open Track 1.
**Canonical architecture:** `fynla-coala-implementation-plan.md` **v0.5** (the copy on `coala`; dev's root copy is a stale v0.4 — the step-0 merge resolves to v0.5 automatically, no conflict) + the CoALA paper (Sumers et al., arXiv:2309.02427).
**Branch:** all Track 2 work lands on `coala`. The coala → dev landing is a separate, later programme.

## 1. The four memory systems — what Fyn employs, why, and how

Per the paper: an agent stores information in **short-term working memory** plus three long-term memories (**episodic, semantic, procedural**), acts through internal actions (**retrieval** reads long-term → working; **reasoning** updates working; **learning** writes working → long-term) and external **grounding**, and chooses actions through a **decision cycle** (plan: propose→evaluate→select; then execute).

**Working memory is the agent's live context window — the information actually in front of the LLM for the current turn.** In Fyn this **already exists in full**: the chat surface, SSE streaming, the API integration, and the per-turn context assembly are working memory in operation. It is not something Track 2 (or any phase) needs to build. The canonical plan's Phase 3 is a *code-tidiness consolidation* of how that context is assembled (one typed VO replacing `FynTurnContext` + `persona_state` + `onboarding_parked_facts`) — a refactor of the assembly plumbing, **not** a build of the memory itself.

### The memory table (the full picture, for shared understanding)

| Memory | Why Fyn employs it | What it holds | How it is constructed | How it is updated | How it is used |
|---|---|---|---|---|---|
| **Working** (short-term — the live context window) | It is what Fyn actually sees and acts on each turn: answer quality, compliance of any single response, and token/prefix-cache economy all live here | The static system prompt (byte-invariant); the assembler's per-turn layers — live `<financial_context>`/`<existing_records>` (pointer-fetched, never frozen), retrieved `<knowledge>` (semantic hits), `<live_data>` (pointer pre-fetches), `<remembered>`, conversation summary; recent message history; in-flight tool calls/results; session state (mode, queue, resumption) | **Already built.** Fresh each turn: `FynSystemPrompt::text()` + `FynContextAssembler::build()` synthesise it from live sources + retrievals; chat UI + SSE stream it | Within a turn by the tool loop (results appended); across turns by message history + the conversation summariser; it is ephemeral — its forensic copy is written to episodic memory, not kept | Every LLM call; the hub connecting retrieval (long-term → here), reasoning (LLM ↔ here), and grounding (tool results → here) |
| **Episodic** (long-term — experience) | FCA/MIFID auditability: any recommendation must be reconstructable from the inputs, knowledge versions, and workflow that produced it; also the substrate for future recall ("what did we advise, when") | Every turn verbatim: SQL rows (`ai_messages`, hash-chained `ai_audit_events`, `ai_advice_logs`) + date-sharded `.md` blobs (system prompt, assembled context, tool calls/results); fetch provenance (what was fetched, source@version); tokens/model/GBP cost | **Already built** (Phase 2). `EpisodeBlobWriter` atomic protocol + request-scoped provenance collector, flushed automatically at turn persist | Append-only; cold-archive after 12 months; GDPR erasure (`fyn:user:erase`); never edited | Compliance/advisor UIs today; summariser input; **Track 2 adds strategy-id granularity** (§4e) so episodes record which strategies were surfaced; Phase 6 (later) adds dense similar-case recall |
| **Semantic** (long-term — knowledge) | Source-less durable knowledge (house view, FCA narrative) needs versioning, PR review, and effective-dating — and must reach the prompt only when relevant (retrieval-scoped, not always-on token cost). v0.5 rule: **only** source-less content lives here; anything with a live owner is a pointer, never a copy | Today: empty scaffolds. **Track 2 fills `house_view/`**: per-strategy narrative, methodology rationale, sequencing reasoning, claim-tier voicing — zero figures | Authored by humans, one `.md` per strategy id with frontmatter; validated fail-closed by `fyn:semantic:reindex` (incl. the no-£ guard); CSJ compliance-reviews at the PR | PR-only; effective-dating via frontmatter `valid_from`/`valid_to`; never written by the agent (Phase 6 would only *propose*, human-gated) | Sparse retrieval per turn into working memory's `<knowledge>` block when the query is relevant (e.g. recommendation-intent turns pull the strategy's house-view rationale) |
| **Procedural** (long-term — how to act) | How-to-act knowledge that changes faster than code should ship without deploys but with fail-closed validation; the paper marks this the riskiest memory to write — so human-only changes, versioned, stamped onto episodes. The **pointer registry is its v0.5 heart**: typed fetch-skills routing to live sources so figures are never frozen anywhere | Pointer registry (fetch-skills incl. the Recommendation pointer); dual-provider tool schemas (`get_recommendations.md` + `.xai.md`, golden-mastered); workflow definitions; **Track 2 adds the `overlay/` category** (A1 answer-first, A2 ack-hygiene). Implicit half: LLM weights + the PHP loop/state-machine code (stays in code) | `.md` corpus authored via PR; loaders fail-closed at boot (duplicate/malformed = fatal); golden masters pin corpus ↔ PHP byte-identity | PR-only, SemVer-versioned, `procedural_version` stamped on every episode; never autonomous | Assembled into the tool catalogue and per-turn instructions; the planner prompt (decision procedure) consumes the routing heuristics (§4c); pointers fetch live data into working memory — **a pointer never widens write permission** |

**The decision cycle** (not a memory, but where it all meets): FynLoop (Phase 5, built) runs each turn; the planner — behind its feature flag — proposes typed Actions (`reason`/`retrieve`/`learn`/`ground`); GroundGate/SurfaceAllowlist mechanically enforce write-safety on grounding. Track 2 touches only the planner's *prompt* (routing heuristics), never the gates.

**For the user**, this architecture is what makes Fyn a financial companion rather than a stateless chatbot: recommendations quantified with their own live numbers (pointers), explained with a consistent house view (semantic), remembered rather than blindly re-pitched (episodic + working memory), and every claim traceable for FCA suitability (episodic provenance + procedural versioning).

### Build-state on `coala` (verified 2026-06-11) and Track 2's touch per system

| System | Canonical plan phase | State on coala | Track 2 touch |
|---|---|---|---|
| Working memory | — (exists; Phase 3 is a later assembly-code consolidation) | **Live** — chat, streaming, API, assembler | None to build. Tests only: the composed plan provably *arrives* in the context window on recommendation turns; A4 synthesis persistence survives FynLoop (§4d) |
| Episodic | Phase 2 | Built | Strategy-id granularity in fetch provenance; session-scoped re-pitch awareness (§4e) |
| Semantic | Phase 1 | Built; `house_view/` empty | ~20 house_view files authored from the catalogue (§4a) |
| Procedural | Phase 4 (4b–4f shipped) | Built; no `overlay/` category | RecommendationHandler returns the composed plan (§4b); tool_schema mirror (§4f); overlay category + A1/A2 files (§4c) |
| Decision cycle | Phase 5 | Built; planner behind flag | Planner routing heuristics only (§4c) |
| Learning actions | Phase 6 | Not built | **Out of scope** — deferred (§5) |

## 2. Locked decisions (CSJ, 2026-06-11)

1. **Branch strategy: dev → coala first.** One merge commit brings Track 1 (and everything since) into coala; `git merge-tree` reports zero textual conflicts either direction. The eventual coala → dev upload ships FynLoop + Track 2 together after its own verification programme.
2. **Corpus scope: the tax strategy set first** (~20 files, one per live `source='strategy'` row). Module growth is later content work; `fca` exemplar authoring stays paused.
3. **Compliance review: at the PR.** CSJ's review of the corpus PR is the compliance review; merge wires files into retrieval. coala is deployed nowhere.
4. **Planner: heuristics only, feature flag unchanged.** **Overlays: corpus + loader category only** — capture-turn consumption stays with the finish-Phase-4 item.

## 3. Sequencing — the merge is step 0, and one ordering is forced

**Step 0: `dev → coala` merge** (single merge commit). Forced ordering: coala's tool-catalogue **golden masters assert byte-identity between the `tool_schema` corpus and the PHP catalogue**; Track 1 changed `get_recommendations`' description in the PHP classes, so the golden masters fail the moment the merge lands until the corpus pair is updated. §4f is therefore part of the step-0 PR, not a follow-up.

**Post-merge verification gate (before any other Track 2 work):**
- Full coala suite green, including regenerated golden masters and the `FynSystemPrompt` byte-invariance snapshot (the static prompt is untouched by everything in this spec — canonical prefix-cache constraint).
- FynLoop proof tests green (text turn + advice-mode write-strip e2e); GroundGate/SurfaceAllowlist parity tests green (Track 2 must not widen any write surface).
- **Azlan golden eval scenario replayed on coala.** Fixture-mode as the cheap pre-check; **one live grok-4.3 run is the required gate** (fixtures omit tool inputs — the known Track 1 caveat). Passing proves FynLoop and Track 1's assembler layers compose at runtime.
- Root `fynla-coala-implementation-plan.md` resolves to v0.5.

## 4. Workstreams

### 4a. Semantic — house_view corpus (~20 files)

One file per live tax strategy id, named by strategy id, following `fyn-memory/semantic/_TEMPLATE.md`. Content per file: **narrative** (what/when/who), **methodology rationale** (why Fynla quantifies it this way), **sequencing reasoning** (the `do_before`/`conflicts_with` logic in prose), **claim tier + voicing guidance** (mechanical vs judgement, per tiered boldness). Constraints: zero figures (pointer model; no-£ guard); source material is dev's catalogue rows, the strategy classes, and `FinancialPlanningKnowledge`; British English; Rules 9/12/15 apply to user-surfaceable copy.

Acceptance: validator green; a retrieval test proves a recommendation-intent query serves house_view hits in the `<knowledge>` block.

### 4b. Procedural — RecommendationHandler returns the composed plan

The Phase-4 `Recommendation` fetch handler swaps its payload from raw `ranked_recommendations` to the `StrategyPlanComposer` structured plan (top strategies with working, sequencing, claim tiers, locked-strategy count) — the same substance as dev's post-Track-1 `get_recommendations`, so the pointer and the tool cannot disagree. Fail-degrade semantics unchanged. A pointer fetches data; it never widens write permission. (Plan re-verifies the handler's current return path before changing it; the description here is from a 9-day-old memory.)

Acceptance: handler unit tests against the composed shape; a **shape-parity test** pinning handler output against `get_recommendations`.

### 4c. Procedural + decision cycle — overlays and planner heuristics

- **Overlay category:** new `fyn-memory/procedural/overlay/` (only `pointers`/`tool_schema`/`workflow` exist). Two files — A1 answer-the-user-first, A2 ack-hygiene — mirroring Track 1's behavioural rules. Loader, validator, and reindex recognise the category; **no consumption wiring** (finish-Phase-4 item).
- **Planner heuristics:** routing guidance in the planner prompt — recommendation intent → `retrieve` via the Recommendation pointer (the composed plan); strategy locked by missing data → ask the unlock question (`reason`, not a blind `ground`). Feature flag unchanged; tests run flag-on and assert the routed `Action` per intent.

### 4d. Working memory — arrival guarantees (tests, not construction)

Working memory exists and needs no building (§1). Track 2 owes it two **guarantees** that the new substance actually reaches the context window:

- **Composed-plan arrival:** on recommendation-intent advice turns the composed plan enters the context window through the pointer pre-fetch (`<live_data>`) or the `get_recommendations` tool result — one substance, two retrieval routes into the same turn context. A test asserts presence on a recommendation-intent turn and absence on an unrelated one (B3's module-scoping economy; strategy *headlines* stay holistic per the parent spec).
- **A4 synthesis persistence under FynLoop:** Track 1 persists the savetax synthesis so `/tax-strategy` shows exactly what Fyn said. Post-merge this flows through FynLoop — a test (and the Azlan replay) verifies the persisted synthesis still matches the chat output under the shared loop.

No change to `FynTurnContext`, `persona_state`, or any assembly plumbing — Phase 3's consolidation is explicitly not this work.

### 4e. Episodic — record what was advised, and don't blindly re-pitch

Episodic memory already records every turn with fetch provenance. Track 2 adds **strategy-id granularity**:

- When the Recommendation pointer (or `get_recommendations`) returns a composed plan, fetch provenance records the **surfaced strategy ids** (and the composed-plan version/hash), so every episode answers "which strategies did Fyn put in front of this user, when" — the FCA suitability trace for recommendations and the substrate for future recall. (Verify-first: the Phase-2 collector may already capture tool results at this granularity — if so, this is a test, not code.)
- **Re-pitch awareness at the context-window level:** the `get_recommendations` tool description and the planner heuristic instruct Fyn to check the current conversation for strategies already surfaced this session before re-pitching, and to acknowledge prior discussion when re-surfacing ("as we discussed…"). Prompt-level, cheap, session-scoped.
- **Cross-session recall is deferred:** dense similar-case retrieval over episodes is Phase 6 by the canonical plan; the SQL `RecommendationTracking` done-state (which the aggregator/composer already consult to exclude completed recommendations) remains the cross-session mechanism for "done". Named here so the boundary is explicit.

Acceptance: a feature test asserting an episode row/blob for a recommendation turn carries the surfaced strategy ids; tool-description/planner text reviewed in the PR.

### 4f. Procedural — tool_schema mirror (inside the step-0 PR)

`get_recommendations.md` + `get_recommendations.xai.md` updated to Track 1's rewritten description (plus the re-pitch guidance from §4e); golden masters regenerated; dual-provider parity tests green.

## 5. Out of scope

Phase 3 (assembly-code consolidation into a WorkingMemory VO; `persona_state` migration); Phase 6 (learning actions — episodic→semantic promotion, dense similar-case recall); planner default flip; overlay consumption wiring; Option-A shell deletion; the coala → dev landing; `fca` corpus authoring; non-tax corpus growth; any change to `FynSystemPrompt::text()`; any change to the write-safety contract or ground-gate surfaces.

## 6. Success criteria

1. dev → coala merged; coala suite, golden masters, FynLoop proof + ground-gate parity tests green; **live-grok Azlan golden scenario green on coala**; root plan file at v0.5.
2. ~20 house_view files pass the no-figures validator; retrieval provably serves them on recommendation-intent queries.
3. RecommendationHandler shape-parity with `get_recommendations` green.
4. Planner heuristic routing tests green (flag-on).
5. Overlay category validated; A1/A2 content matches Track 1's rules.
6. Composed plan provably arrives in the context window on recommendation-intent turns; A4 synthesis persistence verified under FynLoop.
7. Episodes record surfaced strategy ids; re-pitch guidance present in the tool description and planner prompt.
