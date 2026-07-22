# CoALA Phase 6 — Learning Actions (Gated) — Design

**Date:** 2026-06-15
**Status:** Design — awaiting CSJ spec review (brainstorming → writing-plans)
**Branch target:** off `dev` (CoALA substrate; Phases 1,2,4,5 + Track 2 landed via PR #550)
**Canonical source:** `fynla-coala-implementation-plan.md:804` (Phase 6) + the four-stage agent flow (`feedback_coala_agent_flow_canonical.md`) + v0.5 pointer model.
**Predecessor context:** completes the last unbuilt learning phase so the cross-module plan composer (`2026-06-15-cross-module-plan-composer-design.md`, parked) can lean on episode→semantic promotion + similar-case recall.

---

## 1. Problem & goal

CoALA Phases 1,2,4,5 + Track 2 are live on `dev`, but the **learning loop is open**: Fyn captures per-turn episodes and recalls them by recency, but it never *learns back into shared memory*. Phase 6 closes the loop with three **gated** capabilities (no autonomous edits — human/engineering review on every write-back):

1. **Session → semantic promotion (human-reviewed):** a completed session yields *proposed* durable facts about the user ("retires 2041", "risk-averse"), staged for human approval, then written to the **per-user** semantic layer.
2. **Procedural-amendment proposals (engineering-reviewed):** on a detected workflow failure the planner proposes a procedure fix, staged for engineering review, **never auto-applied**.
3. **Similar-case recall (sparse):** the planner recalls *relevant* past episodes (not just recent ones), wired into the planner system prompt.

**Done when (canonical):** there is a human-reviewed promotion path from session-derived facts into semantic memory; no autonomous procedural edits ship; similar-case recall is wired into the planner.

**Regulatory guard (Rule #3 / `feedback_never_hardcode_tax_values`):** promotion **never** touches the global CSJ-authored regulatory corpus (`house_view`/`fca`/`tax`/`allowance`/`product`). It writes only the per-user layer, and only after human approval. No auto-merge, ever.

---

## 2. Decisions locked in brainstorming (2026-06-15)

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | **Similar-case recall = sparse relevance now; dense deferred.** Upgrade `EpisodeRetriever`/`recall()` from recency-only to sparse token/BM25 relevance over `reasoning_trace` + observation; wire into the planner. | The "done when" says *similar-case recall*, not *dense*. Honours CSJ's documented `SemanticRetriever` deferral ("dense deferred until ~500 concurrent users, 2026-06-01"). xAI has no embeddings API. Dense is a later drop-in behind the same interface. |
| D2 | **Promotion target = per-user semantic layer** (`fyn-memory/semantic/<user_id>/`). | The `semantic/README.md`-reserved layer for distilled user facts. Keeps the global regulatory corpus CSJ-authored and untouched. Safest. |
| D3 | **All three components in one spec/phase.** | Actually closes Phase 6's "done when". |

### Recommended defaults (overridable at spec review)
- **D4 (`observation` for sparse recall):** index over `reasoning_trace` + the episode **summary/outcome** (both exist on the blob) rather than building a new per-turn `observation` synthesiser. A dedicated `observation` field is the dense-phase refinement. *Keeps Phase 6 lean and grounded in existing data.*
- **D5 (`learn` action store enum):** extend `['episodic','semantic']` → add `'procedural'`. `semantic` (currently a no-op stub) routes to the semantic staging table; `procedural` (new) routes to the amendment staging table. Episodic stays live/unchanged.
- **D6 (session-end trigger):** reuse the existing stale-conversation path. `ConversationSummariser` already fires on onboarding `STATE_DONE` and a 30-min stale scan; hook session-end episode consolidation + proposed-fact emission there rather than inventing a new lifecycle. *No new state machine.*
- **D7 (flag default):** `FYN_LEARNING_ENABLED` gates the *LLM proposal-generation* (cost), default per CSJ at deploy. The review surface + staging tables are always present and harmless (nothing applies without approval).

---

## 3. Current state (from codebase investigation, 2026-06-15)

**Exists / ready:**
- `ConversationSummariser` (+ `ConversationSummariserJob`) — produces `summary`/`topics`/`entities_mentioned`/`intents_stated` on `ai_conversations`; fires on onboarding done + 30-min stale scan; xAI grok-4.3. Clean extension point.
- `Action::learn(store, payload)` + Planner schema `store: ['episodic','semantic']`. **Episodic write live** (`FynLoop::recordEpisode` → `FynMemoryStore::writeEpisode`). **Semantic learn = no-op stub.**
- Episode blob (`EpisodeBlobData::toMarkdown`) captures `reasoning_trace` (optional), `assembled_context`, `tool_calls`, `tool_results`, `fetch_provenance`, `procedural_version`, `semantic_snapshot_id`. **No `observation` field.**
- `EpisodeRetriever` / `FynMemoryStore::recall()` — **recency-only**, `latest('id')`, default 5; injected into the planner system prompt via `recallContext()` + `FynLoop::plannerSystemPrompt()`.
- Admin read surfaces: `ProceduralCorpusController` + `ProceduralCorpusViewer.vue`; `EpisodicComplianceLog.vue` + `AiAuditController::episode()`. Pattern to mirror for a review surface.
- `SemanticCorpusLoader`/`SemanticFact` (read-only), `SemanticRetriever` (sparse), `fyn:semantic:reindex` (sparse index, no vectors).

**Does not exist (Phase 6 builds it):**
- Any semantic **write/promotion** path; any staging table; any "proposed/pending" status.
- **Per-user** semantic layer loading (corpus loads global categories only).
- **Workflow-failure detection** — the planner never sees failure context.
- **Session-end** episode consolidation — only per-turn `learn`-action episodes are written.
- Relevance-based recall — recall is recency-only.
- Embeddings of any kind (confirmed deferred; out of scope per D1).

---

## 4. Architecture — three components + prerequisites

### Component A — Session → per-user semantic promotion (human-reviewed)

**A1. Session-end episode consolidation (prerequisite).** Hook the existing stale/`STATE_DONE` summariser path: when a conversation ends/goes stale, after `ConversationSummariser::summarise()`, write a **session episode** (one per session, not per turn) via `FynMemoryStore::writeEpisode()` — the canonical "the session is the episode" (flow stage 4). Reuses existing infra; no new lifecycle.

**A2. Proposed-fact emission.** Extend `ConversationSummariser` (flag-gated, D7) with `emitProposedFacts(AiConversation): array` — a second provider call that synthesises *durable, user-scoped* candidate facts `{category, fact_id, title, body, valid_from, valid_to}` from the session (e.g. "retires 2041", "risk tolerance: cautious"). **No figures with a live owner** (pointer model — a retirement *date* is durable; a £ value is not). Candidates are **staged, never applied**.

**A3. Staging table** `proposed_semantic_facts`:
`id, user_id, derived_from_conversation_id, derived_from_episode_id (nullable), category, fact_id, title, body, valid_from, valid_to, status enum('pending','approved','rejected'), reviewed_by (nullable FK users), created_at, reviewed_at (nullable)`.

**A4. Admin review surface** (net-new, mirrors `ProceduralCorpus*`): `Api/Admin/SemanticFactReviewController` (`GET` list pending, `GET {id}` detail, `PATCH {id}` approve/reject/edit) behind `permission:admin.access`; `resources/js/views/Admin/ProposedSemanticFactsViewer.vue`. Optionally an inline "propose fact" CTA on `EpisodicComplianceLog.vue`.

**A5. Reify on approval.** Approving writes the fact to disk at `fyn-memory/semantic/<user_id>/<fact_id>.md` (per-user layer, D2), sets `status='approved'`, `reviewed_at`, `reviewed_by`. Command `fyn:semantic:promote {id}` (or the controller action) performs the guarded write. Rejection sets `status='rejected'`, writes nothing.

**A6. Per-user recall loop (closes the loop).** `SemanticRetriever` / `SemanticCorpusLoader` must additionally load `fyn-memory/semantic/<user_id>/` for the active user, so approved facts actually reach Fyn's context. The global corpus loading is unchanged.

### Component B — Procedural-amendment proposals (engineering-reviewed, never auto-applied)

**B1. Workflow-failure detection (prerequisite).** In `FynLoop::run()`, assemble a failure-context object across the cycle: tool failure, reasoning-loop cap exhaustion (`FYN_CYCLE_CAP` hit), empty/irrelevant retrieval. Pass it to the planner (system message / history) so the planner can recognise a failure.

**B2. `learn` store='procedural' (D5).** Extend the planner `plan`-tool schema and `Action::learn` to accept `store='procedural'` with payload `{procedure_id, problem_observed, proposed_fix, rationale, failure_type}`. Wire the FynLoop dispatch: `semantic`→`proposed_semantic_facts` (was a stub), `procedural`→`proposed_procedure_amendments`.

**B3. Staging table** `proposed_procedure_amendments`:
`id, procedure_id, problem_observed, proposed_fix, rationale, failure_type, conversation_id (nullable), status enum('pending','approved','rejected'), reviewed_by (nullable), created_at, reviewed_at (nullable)`.

**B4. Engineering review surface.** Extend the same admin area (sibling tab/view to A4, or a `ProposedProcedureAmendmentsViewer.vue`). **Approval does NOT auto-edit the corpus** — it marks the proposal accepted; an engineer applies the `.md` change by hand (or a guarded, explicitly-run command). The "no autonomous procedural edits" invariant is enforced by *never* writing the procedural corpus from the loop or from approval.

### Component C — Sparse-relevance similar-case recall

**C1. Relevance scorer.** Add a `$query` parameter to `FynMemoryStore::recall()` / `EpisodeRetriever`: score the user's episodes by sparse token-overlap/BM25 over `{episode summary, reasoning_trace}` (D4), return top-K by relevance with recency as tiebreak (replacing recency-only). Behind a `RecallScorer` interface so a dense scorer drops in later without touching callers.

**C2. Wire into the planner.** `FynMemoryStore::recallContext($userId, $query)` and `FynLoop::plannerSystemPrompt()` pass the current turn/user message as the query, so the "What I remember about you" block is *relevant*, not just recent.

### Cross-cutting
- **Config** (`config/fyn.php`): `FYN_LEARNING_ENABLED` (D7), per-user semantic path (`semantic/<user_id>`), `FYN_RECALL_TOP_K`. Embeddings keys explicitly **not** added (dense deferred).
- **Migrations:** `proposed_semantic_facts`, `proposed_procedure_amendments` (additive).
- **Pointer model:** staged facts are *proposals* about live-owned reality; only durable, source-less narrative facts are ever promoted — never frozen figures.

---

## 5. Data model & code changes (summary)

| Change | Location |
|--------|----------|
| `proposed_semantic_facts` + `proposed_procedure_amendments` migrations | `database/migrations/` |
| `emitProposedFacts()` | `app/Services/AI/ConversationSummariser.php` |
| Session-end episode consolidation | summariser/stale-scan path + `FynMemoryStore::writeEpisode` |
| `learn` store enum `+procedural`; semantic+procedural dispatch | `Actions/Action.php`, `Loop/Planner.php` (schema), `Loop/FynLoop.php` (dispatch) |
| Failure-context assembly | `Loop/FynLoop.php` |
| `RecallScorer` (sparse) + `$query` on recall | `Memory/Episodic/EpisodeRetriever.php`, `Memory/FynMemoryStore.php` |
| Per-user semantic layer loading | `Memory/SemanticCorpusLoader.php`, `SemanticRetriever.php` |
| Review controllers + Vue views | `Api/Admin/SemanticFactReviewController`, `…/ProcedureAmendmentReviewController`; `views/Admin/Proposed*Viewer.vue` |
| `fyn:semantic:promote {id}` command | `app/Console/Commands/` |
| Config + flag | `config/fyn.php`, `.env.example` |

---

## 6. Build sequence (detailed in the plan)

1. **Migrations + staging models** (`proposed_semantic_facts`, `proposed_procedure_amendments`) + config/flag.
2. **Component C (recall)** — lowest-risk, self-contained: `RecallScorer` sparse + `$query` plumb-through + planner wiring + tests. Ships value immediately.
3. **Component A** — A1 session-end consolidation → A2 proposed-fact emission (flag-gated) → A3 staging → A4 review surface → A5 reify-on-approval → A6 per-user recall load. End-to-end: session → proposed fact → approve → Fyn recalls it.
4. **Component B** — B1 failure-context → B2 `learn` procedural wiring → B3 staging → B4 review surface (no auto-apply).
5. **Tests + browser E2E** (admin review/approve flows; verify approved per-user fact reaches recall; verify no procedural auto-edit).

---

## 7. Testing & risk

- **No-auto-apply invariant** (the security/regulatory crux): dedicated tests proving (a) the loop never writes the procedural corpus, (b) promotion never writes the global semantic corpus, (c) nothing applies without `status='approved'` + a reviewer. Pair with GroundGate-style PR-checklist discipline.
- Unit: proposed-fact emission shape; staging CRUD + status transitions; reify-to-disk path + per-user layer load; sparse `RecallScorer` ranking; `learn` semantic/procedural dispatch; failure-context detection.
- Admin endpoint tests (auth + `permission:admin.access`, IDOR-safe).
- Browser E2E (Rule #14, web + `/m` where applicable — admin is desktop-only per Rule #19's admin carve-out): propose → review → approve → confirm the per-user fact surfaces in a later Fyn turn; confirm a procedural amendment stages but never auto-applies.
- Flag-gated proposal-generation keeps LLM cost controlled; `FynSystemPrompt::text()` stays byte-invariant (no change to the prefix-cached prompt).

---

## 8. Out of scope / deferred

- **Dense embeddings + vector recall** — deferred per CSJ's ~500-concurrent-user threshold (D1); the `RecallScorer` interface leaves it a drop-in.
- **Promotion into the global corpus** — per-user layer only (D2); global stays CSJ-authored.
- **Auto-application** of any proposal — forbidden by design.
- A dedicated per-turn `observation` synthesiser — sparse recall uses existing fields (D4).
- Phases 3 (working-memory VO consolidation), the composer spec itself, Option A shell-deletion, prod deploy.

---

## 9. Success criteria

1. A completed session emits *proposed* user-scoped facts (flag-gated) into `proposed_semantic_facts`; an admin can review/approve/reject; an approved fact is written to `fyn-memory/semantic/<user_id>/` and **surfaces in a later Fyn turn** (loop closed, verified in browser).
2. On a detected workflow failure the planner can stage a `proposed_procedure_amendment`; it is reviewable; **it never auto-applies** to the procedural corpus (test-proven).
3. The planner's "what I remember" recall is **relevance-ranked** (sparse) over `reasoning_trace` + summary, not recency-only — wired and tested.
4. The global regulatory corpus is provably untouched by any learning path; no auto-merge anywhere.
5. Full suite green; browser E2E green; `FynSystemPrompt::text()` byte-invariant; flag-gated cost controlled.
