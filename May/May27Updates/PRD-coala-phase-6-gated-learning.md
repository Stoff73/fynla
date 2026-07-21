# PRD — CoALA Phase 6: Learning Actions (Gated)

**Project:** Fyn brain rewire — Phase 6 (Learn)
**Owner:** CSJ
**Status:** Draft — codebase audit completed during plan revisions v0.1 → v0.4
**Date:** 27 May 2026
**Spec & Plan:** `/Users/CSJ/Desktop/fynla/fynla-coala-implementation-plan.md` (v0.4)
**Canonical contract:** `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/00-canonical.md`
**Codebase audit:** Performed during plan v0.1 → v0.4 revision cycle

---

## 1. Context & Why

### Problem

CoALA's framework includes a fourth action type alongside `reason`, `retrieve`, and `ground`: **`learn`** — writing back to long-term memory. Section 4.5 of the paper details three forms of learning:

- **Updating episodic memory with experience.** Phase 2 already writes verbatim per-turn records — this is automatic episodic learning. No Phase 6 action needed.
- **Updating semantic memory with knowledge.** A session can surface a new fact, a clarification, an exception. CoALA prescribes writing this to semantic memory so future sessions benefit.
- **Updating procedural memory (agent code).** A session reveals a workflow failure or a missing tool surface. CoALA permits writing back to procedural memory — *and* explicitly flags this as significantly riskier than the other two.

For Fynla specifically, the framing tightens:

- **Semantic memory writes** in Phase 6 propose `SemanticFact` additions or amendments derived from a completed session. CoALA's "Reflexion" pattern. **Never auto-merged for regulatory content** (per the v0.4 plan's resolved decisions and CLAUDE.md Rule #3). Goes to a human-review queue.
- **Procedural memory writes** in Phase 6 propose procedure amendments when a session encounters a workflow failure. **Never auto-applied for any procedural content** (per the v0.4 plan). Goes to an engineering-review queue.
- **Dense similarity retrieval** over `reasoning_trace + observation` — Phase 1's embeddings infrastructure made this technically possible; Phase 6 wires it into the planner so similar past cases surface during retrieval actions.

The unifying constraint: **no autonomous edits ship**. Every memory-write proposal is queued, reviewed, and either approved (becomes a PR against the corpus) or rejected. Approval discipline is a regulatory requirement, not a UX preference.

### Business case

- **Continuous improvement loop.** Today, Fyn's knowledge is whatever was last shipped. Sessions that surface novel patterns (a new regulatory clarification, a missing product type, a workflow dead-end) generate value but the value evaporates after the session ends. Phase 6 captures it.
- **Audit-trail completeness.** Compliance benefits from a record of "Fyn proposed an amendment because session X surfaced Y — reviewer Z decided W". A learning audit log is itself a regulatory artefact.
- **Engineering signal on workflow failures.** Procedural-learning proposals surface real engineering bugs and design gaps — workflow steps that misbehave, tools that are missing, prompts that confuse the LLM. Today these are anecdotal. Phase 6 makes them a structured backlog.
- **Defence against staleness.** A semantic corpus that only receives top-down content updates drifts from the operational reality. Bottom-up signals from real sessions keep the corpus connected to what users actually ask.

### Strategic fit

The final phase. Depends on every preceding phase: semantic corpus (Phase 1) to write into; episodic memory with `reasoning_trace` and embeddings infrastructure (Phase 2 + Phase 1); typed `Action` enum (Phase 5) to dispatch `learn` actions through. Touches the regulatory layer — every approved amendment becomes a versioned content change.

No end-user UX change. Two new internal surfaces: a learning-proposal review queue for semantic amendments, and an engineering-review queue for procedural amendments.

---

## 2. Target Persona

**Infrastructure with internal-tooling surface.** No end-user UX.

**Primary internal beneficiary:** Compliance / content authors — receive a queue of session-derived proposed amendments to review, approve, or reject. Each approval becomes a PR against the semantic corpus.

**Secondary internal beneficiary:** Engineers — receive a queue of procedural amendment proposals (workflow failures, missing tool surfaces) to review.

**Tertiary:** Future advisors using Fyn — benefit from a corpus that stays connected to real session patterns.

---

## 3. Success Metrics (KPIs)

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| Semantic memory amendments from sessions queued for review | 0 (no path exists) | 100% of LLM-proposed semantic writes routed to review queue, 0% auto-merged | Audit log of learn actions |
| Procedural memory amendments from sessions queued for review | 0 | 100% of LLM-proposed procedural writes routed to engineering queue, 0% auto-applied | Audit log of learn actions |
| Approved-to-proposed ratio for semantic amendments | n/a | Measured (sets expectations; no a-priori target — depends on proposal quality) | Review queue analytics |
| Approved-to-proposed ratio for procedural amendments | n/a | Measured | Engineering queue analytics |
| Median time-to-review for proposed amendments | n/a | < 7 days for semantic, < 14 days for procedural | Queue analytics |
| Dense similarity recall p95 latency | n/a (only keyword retrieval exists today via `MemoryRetrieverService`) | < 100ms | Telemetry from Phase 5 cost-attribution table |
| Episodes embedded for similarity recall | 0 | 100% post-cutover (for new turns); historical episodes embedded by backfill artisan command | Embedding index size + episode count diff |
| Cross-session pattern detection — e.g. "5+ sessions in the last 30 days encountered FCA rule X with no canonical guidance" | n/a | Surfaced in review queue as priority signal | Queue prioritisation rules |

---

## 4. User Stories & Scenarios

### User stories

- As a **content author**, I want a queue of session-derived proposed amendments to the semantic corpus so that the FCA / product narrative stays connected to real questions users ask.
- As an **engineering reviewer**, I want a queue of procedural amendment proposals from workflow failures so that I can prioritise real workflow gaps over speculative refactors.
- As **Fyn** (the agent), I want to retrieve similar past cases by dense embedding similarity so that I can answer new questions informed by patterns from earlier sessions of similar shape.
- As **Compliance**, I want every approved semantic amendment to ship via PR against the corpus (Phase 1) so that the audit trail spans the proposal → review → merge → deploy cycle.

### Key scenarios

**Scenario 1 — Semantic amendment proposal from a completed session:**

1. Session ends. Post-session summariser (extension of `ConversationSummariserJob`) runs over the conversation transcript + episodic blobs.
2. Summariser identifies a candidate amendment — e.g. "User asked about X; semantic memory returned no result; Fyn answered from general reasoning; reviewer should consider whether a canonical fact exists for X."
3. Summariser writes a `learning_proposal` row with: `proposal_type: 'semantic_amendment'`, `category: 'fca' | 'product' | 'house_view'`, `proposed_fact_id`, `proposed_content`, `source_session_id`, `source_episodes`, `justification`, `status: 'pending_review'`.
4. Proposal surfaces in the review queue (admin UI).
5. Reviewer (CSJ or designated content author) reads the proposal + the source session episodes. Decides:
   - **Approve:** scaffold a PR against `app/Resources/Memory/Semantic/{category}/` with the proposed content. Reviewer edits as needed, merges via the standard Phase 1 PR flow.
   - **Reject:** mark `status: 'rejected'` with a reason. Proposal closed.
   - **Defer:** mark `status: 'deferred'` with a follow-up date. Reappears in queue at that date.
6. Audit log entry written for the decision.

**Scenario 2 — Procedural amendment proposal from a workflow failure:**

1. Session encounters a workflow failure — e.g. onboarding state machine emitted an action that the user explicitly rejected, or a planner repeatedly emitted `no_action` for the same workflow step across N sessions.
2. Phase 5's per-action telemetry flags the pattern.
3. Phase 6's procedural-learning detector runs nightly over recent telemetry. Identifies recurring failure modes.
4. Writes a `learning_proposal` row with: `proposal_type: 'procedural_amendment'`, `procedure_id`, `failure_pattern`, `proposed_change`, `source_session_ids`, `justification`, `status: 'pending_review'`.
5. Engineering reviewer triages. Approves → manual PR against the procedural corpus (Phase 4). Rejects → status: rejected. Defers → status: deferred.
6. **No automatic procedural amendments are EVER applied.** The reviewer must explicitly author the PR.

**Scenario 3 — Dense similarity recall during a planning turn:**

1. User asks a question. Planner emits a `retrieve` action against episodic memory.
2. New retrieval mode: dense similarity over `reasoning_trace + observation` embeddings (computed at episode-write time, indexed alongside semantic memory embeddings).
3. Top-N similar past sessions returned, scored by cosine similarity + recency.
4. Planner uses them to inform the answer ("In similar past situations, Fyn answered Y…").
5. Episode's audit trail records which past episodes informed this turn (similar to how `semantic_snapshot_id` records semantic facts).

**Scenario 4 — A regulatory amendment that the LLM proposes:**

1. Session involves a tax rule change Fyn doesn't yet know about (user mentions a 2027 budget announcement that hasn't reached the corpus).
2. Summariser identifies a candidate `tax` or `fca` amendment.
3. **Special handling for regulatory categories:** proposals in `tax` / `fca` categories are flagged red in the queue, marked "regulatory — extra scrutiny required". Approval requires CSJ explicitly (configurable reviewer allowlist for these categories).
4. Approved → standard corpus PR with extra reviewer (TBD: legal / compliance counsel if engaged).
5. Until approved, Fyn continues to answer from existing corpus + general reasoning. Never silently adopts the proposed change.

**Unhappy path — proposal queue grows unbounded:**

1. Many sessions surface similar candidate amendments.
2. Detector deduplicates by candidate `fact_id` / `procedure_id`. One open proposal per `(category, fact_id)` pair.
3. Repeated session evidence for the same proposal increments a `corroboration_count` field. Higher count → higher queue priority.
4. After 90 days unreviewed, proposals are auto-aged to `status: 'stale'` and removed from active queue (still in the audit log for retrospective review).

---

## 5. Functional Requirements

### Must-have

- **FR-M1:** `learning_proposals` table migration. Columns: `id`, `proposal_type ENUM('semantic_amendment', 'procedural_amendment')`, `category (semantic) or procedure_id (procedural)`, `proposed_content TEXT`, `source_session_id`, `source_episode_ids JSON`, `justification TEXT`, `status ENUM('pending_review', 'approved', 'rejected', 'deferred', 'stale')`, `corroboration_count INT DEFAULT 1`, `reviewer_id`, `reviewer_decision_reason TEXT`, `created_at`, `decided_at`. _Touches: new migration._
- **FR-M2:** Post-session learning summariser. Extension of `ConversationSummariserJob`. Runs over the conversation's episodes after session end (after the Phase 5 inactivity-timer consolidation). Identifies candidate amendments via a structured LLM call with a constrained output schema. Emits `learning_proposals` rows. _Touches: extension of `app/Console/Commands/SummariseStaleConversationsCommand.php` + new `app/Services/AI/Memory/Learning/PostSessionLearner.php`._
- **FR-M3:** Procedural-learning detector. Runs nightly over Phase 5 per-action telemetry. Identifies recurring workflow-failure patterns (cycle cap exceedances on the same procedure, repeated `no_action` exits for the same `procedure_id`, `ground` gate `stripped` patterns indicating a missing tool surface). Emits `learning_proposals` rows of `proposal_type: 'procedural_amendment'`. _Touches: new artisan command + detector service._
- **FR-M4:** Review queue admin UI. Lists pending proposals with category filter (semantic by category / procedural by procedure_id) and priority sort (by `corroboration_count` desc, then `created_at` asc). Detail view shows proposed content, source session(s), justification, and source episode `.md` blob anchors for context. Wraps in `AppLayout`. _Touches: new `resources/js/views/Admin/LearningQueueViewer.vue` + supporting controller._
- **FR-M5:** Approval workflow. Reviewer clicks "Approve" → scaffolds a PR against the corpus (semantic → Phase 1 corpus, procedural → Phase 4 corpus). PR includes the proposal's content as a starting point; reviewer edits in their text editor as normal. Closing the proposal updates `learning_proposals.status: 'approved'` with `decided_at` and `reviewer_id`. _Touches: controller action that opens a GitHub PR draft (via `gh` CLI or API) OR manual link to the corpus path with copy-paste body — TBD: CSJ preference._
- **FR-M6:** Rejection workflow. Reviewer marks "Reject" with a reason. `status: 'rejected'`, `reviewer_decision_reason` populated. Audit log entry. _Touches: controller action._
- **FR-M7:** Deferral workflow. Reviewer marks "Defer" with a follow-up date. `status: 'deferred'`, reappears in queue at the follow-up date. _Touches: controller action + scheduled job to re-surface deferred proposals._
- **FR-M8:** Regulatory category special handling. Proposals in `tax` / `fca` categories require CSJ-only approval (configurable allowlist). Visual red flag in the queue. _Touches: middleware or controller-level guard._
- **FR-M9:** Dense similarity retrieval over `reasoning_trace + observation`. Episodes' `reasoning_trace` and `observation` content from the `.md` blobs (Phase 2) embedded via the same provider used in Phase 1 (or different — TBD: CSJ). Embeddings stored in `storage/app/memory/episodic/embeddings.json` keyed by `episode_id`. `php artisan fyn:episodic:reindex` to backfill historical episodes. New episodes embedded automatically as part of the write pipeline. _Touches: new artisan command; extension of `EpisodeBlobWriter`; new `EpisodicRetriever::similarTo($query, $client_id, $limit, $effectiveDate)`._
- **FR-M10:** Wire dense episodic retrieval into the planner. New `retrieve` action variant: `store: 'episodic_similar'`, `query`, `client_id`, `limit`. Planner can choose to retrieve similar past cases as part of the cycle. _Touches: planner; Phase 5 `Action` enum extension._
- **FR-M11:** Deduplication and corroboration. Detector checks `learning_proposals` for an existing open proposal matching `(category, proposed_fact_id)` or `(procedure_id, failure_pattern)`. If exists, increment `corroboration_count` and append to `source_session_ids` JSON. Otherwise create new. _Touches: detector logic._
- **FR-M12:** Audit log entries for every proposal lifecycle event (created, viewed by reviewer, decided). Reuses `ai_audit_events` chain or new sibling table — TBD: CSJ. Recommendation: separate `learning_audit_log` table because the action type is different. _Touches: new table + service._
- **FR-M13:** Auto-aging. Proposals unreviewed for 90 days auto-transition to `status: 'stale'`. Stays in the audit log. _Touches: scheduled job._

### Should-have

- **FR-S1:** Email or admin-channel notification to reviewers when high-corroboration proposals land. Threshold configurable. _Touches: notification integration._
- **FR-S2:** Diff view for amendments to existing facts. When a proposal amends an existing `SemanticFact` (vs adds a new one), show diff between current and proposed body. _Touches: admin view rendering._
- **FR-S3:** Anonymisation pass for proposal content. Source episodes may contain PII; proposed amendments must not. Strip names, account numbers, etc. from the proposed `content` field. _Touches: pre-write sanitiser._

### Nice-to-have

- **FR-N1:** Cross-client pattern detection. If 5+ sessions across different clients encounter the same gap (anonymised), surface as a priority flag. CoALA's "cross-client episodic retrieval" is explicitly out of scope (GDPR/FCA spec needed), but cross-client *pattern detection at the proposal level* might be safe — TBD: CSJ + legal review. _Touches: future._
- **FR-N2:** Auto-suggest amendment to similar existing facts when a new proposal arrives. _Touches: future._
- **FR-N3:** Reviewer dashboard with throughput / quality metrics. _Touches: future._

---

## 6. User Flow & UX/Design

### Learning summariser flow (engineering)

```
Session ends (Phase 5 inactivity timer fires, or explicit close)
  └─ ConversationSummariserJob runs (Phase 2 / Phase 5 — extended)
       ├─ Standard summary update (existing)
       └─ NEW: PostSessionLearner::analyse(conversation)
            ├─ Identify candidate semantic amendments via constrained LLM call
            ├─ Identify candidate procedural amendments via telemetry patterns
            ├─ For each candidate: check learning_proposals for existing match
            │    ├─ Match → increment corroboration_count, append source_session
            │    └─ No match → INSERT new proposal with status: pending_review
            └─ Audit log entries written

Nightly
  └─ Procedural-learning detector runs over Phase 5 telemetry
       └─ Same dedup logic, same proposal creation

Review queue
  └─ Reviewer opens admin UI, sees prioritised proposals
       ├─ Approve → scaffold corpus PR, status: approved
       ├─ Reject → status: rejected with reason
       └─ Defer → status: deferred until follow-up date

Stale sweep (daily)
  └─ Proposals with status: pending_review, created_at > 90d ago
       └─ status: stale

Per Fyn turn (when planner emits retrieve store: episodic_similar)
  └─ EpisodicRetriever::similarTo(query, client_id, limit, effective_date)
       └─ Cosine over reasoning_trace + observation embeddings
       └─ Filter by client_id (no cross-client without Phase N spec)
       └─ Return ranked episodes for working memory inclusion
```

### Review queue admin UI (FR-M4)

- **Layout:** Standard `AppLayout`. Filter sidebar (category, status, corroboration threshold) + main pane with proposal cards.
- **Card content:** Proposed `fact_id` or `procedure_id`, category badge (regulatory categories get a `raspberry-*` "regulatory" label per Rule #9 — never amber/orange), corroboration count badge, source session count, "Review" CTA.
- **Detail view:** Proposed content in markdown, source session episodes (lazy-loaded `.md` blobs from Phase 2), justification, approve / reject / defer actions.
- **No icons** (Rule #16 — list / detail / form admin UI, no decorative icons).
- **Design system:** `fynlaDesignGuide.md` v1.3.0. Standard card variants. Standard `FormModal.vue` for decision capture. Standard `AppLayout` chrome.

---

## 7. Out of Scope

- **Autonomous amendments.** No proposed change ships without human review. Period.
- **Cross-client episodic retrieval.** Mentioned in CoALA but explicitly out of scope (v0.4 plan, MEMORY.md). Requires its own GDPR/FCA spec.
- **LLM weight fine-tuning.** CoALA mentions this as a procedural-memory learning option. Out of scope until corpus is large and clean enough — 12+ months out per the v0.4 plan.
- **A reviewer Fyn agent.** A second agent that critiques draft amendments before human review. Mentioned in the v0.4 plan as deferrable post-Phase-6.
- **Real-time learning during a session.** All learning is post-session. A session never modifies long-term memory mid-turn.
- **Approval automation.** No "auto-approve low-risk amendments" pathway. Every approval is human + audit-logged.
- **Reviewer rotation / load balancing.** CSJ as primary reviewer with optional content-author allowlist. Throughput tuning out of scope until queue volume justifies it.
- **Confidence-scored proposals.** Proposals may include a justification but not a numeric confidence score — per CLAUDE.md Rule #13 "No scores in user-facing UI". Reviewer UI shows justification text, not a score badge.

---

## 8. Risks & Dependencies

### Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Proposal queue grows faster than reviewer capacity | High | Medium | Dedup + corroboration counting collapses repeated signals into single proposals. Auto-age to stale after 90d. Throughput tuning if needed (add allowlisted reviewers). |
| LLM-generated proposals are low-quality / noisy | Medium | Medium | Constrained-output prompting + JSON schema validation on the proposal LLM call. Approve/reject rate becomes a quality signal — if approve rate < 10%, retune the prompt. |
| Regulatory proposals approved by mistake (e.g. amending `fca` content without sufficient review) | Low | Critical | Special handling: `tax` / `fca` categories require CSJ-only approval. Visual red flag. Approval is a deliberate two-step (read proposal + manual PR edit + standard PR merge). |
| PII leakage through proposal content | Medium | High | Anonymisation pass on proposed `content` before write (FR-S3 should-have, possibly elevated to must-have during implementation). Independent of source session retention rules. |
| Cross-client pattern detection accidentally exposes individual user signal | Low | High | Pattern detection is aggregated; raw source sessions are NOT shown to non-owning reviewers. Per-client sessions visible only when the reviewer is also the client (admin viewing own data) or a designated cross-client researcher (currently nobody). |
| Auto-aging removes signal that would have been useful later | Low | Low | Stale proposals stay in audit log indefinitely. Re-emerge if corroborated again by future sessions (new proposal, links to old stale one). |
| Dense similarity recall surfaces stale episodes from before a corpus update | Medium | Medium | Effective-date filter on similarity retrieval — `effective_date` of the active corpus version informs which episodes are still "current". Stale episodes scored lower. |
| Embedding storage grows unbounded as episodes accumulate | Medium | Medium | Cold-archived episodes (Phase 2's 12-month threshold) have their embeddings removed from the hot index at archive time. Similarity recall against cold episodes available via a slower path if explicitly requested. |

### Technical dependencies

- **Strong dependency on Phase 1:** semantic corpus + embeddings infrastructure. Phase 6's similarity recall and proposed amendments target Phase 1's corpus.
- **Strong dependency on Phase 2:** episodes with `reasoning_trace` and `observation` in `.md` blobs are the source material for both learning summariser and similarity recall.
- **Strong dependency on Phase 4:** procedural amendments target Phase 4's corpus. Without Phase 4, procedural amendments can only be PRs against PHP code, which is what Phase 6 is supposed to evolve past.
- **Strong dependency on Phase 5:** `Action` enum with `learn` variant; per-action telemetry for procedural-failure detection.
- `ConversationSummariserJob` — extended with PostSessionLearner integration.
- LLM provider for proposal generation (constrained output). Could reuse existing Anthropic / xAI choice.
- Embedding provider (same TBD as Phase 1).

### Sequencing dependencies

- **Blocked by:** Phases 1, 2, 3, 4, 5. Phase 6 is the final phase by design.
- **Blocks:** Future "reviewer Fyn" agent and cross-client retrieval work (both explicitly out of scope, but they slot in after Phase 6).
- **Re-review point.** Per stakeholder brief: end of Phase 5 is the re-review gate. Phase 6 is funded only if Phase 5 telemetry shows the loop and gate are stable.

### Residual concerns from codebase audit

- **PR scaffolding integration.** FR-M5 says "scaffold a PR via gh CLI or API". CSJ preference unconfirmed. Recommendation: start with a simpler "open in editor with proposed content as a draft .md file" integration; add GitHub PR automation later if approval throughput justifies it.
- **Conflict with `MemoryRetrieverService` 4-layer fall-through.** The existing retriever has its own layered logic (authoritative DB → parked facts → re-extract → conversation index). Dense similarity recall is a new 5th source. Decide whether it slots into the existing retriever or runs separately under the typed `retrieve` action. Recommendation: separate — keep `MemoryRetrieverService` for the existing gap-fill behaviour, surface similarity recall under the typed `retrieve` action only.
- **Embeddings provider choice.** Same TBD as Phase 1. Recommendation: use the same provider for semantic and episodic embeddings to keep operational complexity down.
- **Approval audit log granularity.** New `learning_audit_log` table vs extending `ai_audit_events`. Recommendation: new table. The hash chain in `ai_audit_events` is for AI dispatch tamper-evidence; learning lifecycle is different in shape and not on the same hot path.
- **The CoALA paper's caution carries forward.** Per Section 4.5: *"learning new actions by writing to procedural memory is significantly riskier than writing to episodic or semantic memory."* Phase 6 honours this by routing procedural amendments through engineering review and never auto-applying. Confirm in PR review that this discipline holds.

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| 27 May 2026 | Initial draft from CoALA v0.4 plan Phase 6 | prd-writer skill |
