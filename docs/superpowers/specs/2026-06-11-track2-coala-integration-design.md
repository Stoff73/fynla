# Track 2 — CoALA Integration of the Reconciled Recommendation Catalogue

**Date:** 2026-06-11 (v4 — rebuilt on the canonical agent flow as given by CSJ; supersedes v1–v3 entirely)
**Status:** Awaiting CSJ review
**Parent spec:** `docs/superpowers/specs/2026-06-10-recommendation-insight-quality-design.md` §7 — this doc binds §7 to the CoALA architecture and execution decisions; it does not re-open Track 1.
**Canonical architecture:** the agent flow in §1 (CSJ, 2026-06-11) + `fynla-coala-implementation-plan.md` **v0.5** (the copy on `coala`; dev's root copy is a stale v0.4 — the step-0 merge resolves to v0.5) + the CoALA paper (Sumers et al., arXiv:2309.02427).
**Branch:** all Track 2 work lands on `coala`. The coala → dev landing is a separate, later programme.

## 1. The agent flow with memory (canonical — CSJ, 2026-06-11)

1. **Session start.** The user enters the system at an entry point (onboarding, savetax campaign, dashboard chat, `/m`, resume). The **lean system prompt** loads together with the **semantic indexes** — Fyn knows the shape of the user's world (what data exists), the knowledge available, and the skills it can call, with none of the heavy data in the prompt. Depending on the entry point, Fyn asks the opening question.
2. **Each turn.** The user answers. **Semantic memory is checked and the relevant information is loaded**, or **the relevant skill, method, process, or workflow is kicked off from procedural memory** (a data fetch, an engine run, a gated capture, an onboarding state transition). What is loaded is **not static**: the indexes and loaded content change as the conversation grows and different skills are invoked.
3. **Interrupts hold to the same flow.** Conversation interrupts and stream interrupts (the concurrent-turn queue, cancellation, resumption) route through the same memory flow — they never bypass it.
4. **Session end.** Fyn **writes the finished session to episodic memory** — the learning step, so the agent gets better and evolves alongside the user. Per-turn rows are raw substrate; the **session is the episode**, finalised at close (the inactivity-pause/summariser moment).

**The lean-prompt law:** the prompt is always kept as lean as possible to get the job done — without the agent losing context or its place in the conversation, and while still able to give holistic advice. Holism comes from the **index** (Fyn always sees the full shape); depth comes **on demand** through procedural skills. Data with a live owner is never frozen into a prompt or a corpus (v0.5 pointer model).

## 2. The four memory systems — what each holds, how built, updated, used

| Memory | Role in the flow | What of Fynla's lives here | Constructed | Updated | Used |
|---|---|---|---|---|---|
| **Working** (the live context window) | The turn itself — everything Fyn currently sees | Lean system prompt; the loaded semantic indexes; conversation so far; whatever information/skill results have been loaded *this session*; session state (mode, queue, resumption) | **Exists** — chat surface, SSE streaming, API, per-turn assembly | Evolves turn-by-turn as the flow loads information and invokes skills; ephemeral — the session's record goes to episodic, not kept here | Every LLM call; where the decision cycle reads and writes |
| **Semantic** (knowledge about the world and the user's world) | Checked each turn; serves the **indexes** loaded at startup and refreshed as the conversation grows | **The strategy catalogue** (action-definition rows: what strategies exist, copy, claim tier, required data, sequencing — domain knowledge, admin-managed); **reference knowledge sources** (TaxConfigService figures, product reference, actuarial, market rates — live owners, reached by skill, never copied); **the user's data in the DB** (the user's world-state — the *index* of it loads into the prompt; the data itself stays in the DB until fetched); **the `.md` narrative corpus** (`house_view` rationale + FCA narrative — the source-less slice that lives as reviewed files) | Catalogue + reference data: seeders/admin. Corpus: human-authored `.md`, fail-closed validation (incl. no-£ guard), CSJ compliance review at PR | **Via indexes** — re-indexing is the update interface (`fyn:semantic:reindex`); corpus changes land by PR; catalogue by admin/seeder; the agent never writes semantic memory at runtime | Indexes load with the lean prompt at startup; per-turn checks load the *relevant* narrative/knowledge into working memory; skills read the heavy sources on demand |
| **Procedural** (the skills — how Fyn acts) | Invoked each turn when the answer calls for action rather than knowledge | **The retrieve AND write skills over the user's DB data** (the pointer-registry fetch-skills; the gated capture tools — writes only ever through these, gated by session mode); **methods and processes** (strategy evaluator classes, `StrategyPlanComposer`, aggregator, engines); **workflows** (onboarding/savetax state machines); the tool schemas (dual-provider `.md`, golden-mastered), overlays, planner prompt + heuristics, FynLoop/gates code; implicit: model weights | Code by PR; `.md` corpus by PR with fail-closed loaders; golden masters pin corpus ↔ catalogue byte-identity | PR-only, versioned, `procedural_version` stamped on episodes; never autonomous | The agent calls them: fetch data into working memory, run quantifications, execute workflows, perform gated writes; a skill invocation never widens write permission |
| **Episodic** (Fyn's experience with this user) | Written at **session end** — the learning step | Finished sessions: per-turn raw rows (`ai_messages` + `.md` blobs + hash chain) as substrate, the session summary/finalisation, advice events (`ai_advice_logs`), **which strategies were surfaced when** (fetch provenance — Track 2 adds strategy-id granularity), the user's actions on them (`RecommendationTracking`) | **Exists** (Phase 2) — atomic blob writer, provenance collector, summariser at session close | Append-only; cold-archive; GDPR erase; never edited | The substrate for Fyn evolving alongside the user: session summaries feed resumption + conversation context today; consolidation/recall deepen in Phase 6 |

**One recommendation turn, placed end-to-end:** the planner (procedural, decision cycle) recognises recommendation intent → invokes the Recommendation **fetch-skill** (procedural) → the skill runs the **composer** (procedural) over the **catalogue + tax figures + the user's records** (semantic sources) → the composed plan lands in **working memory** → Fyn voices it (grounding) → at session end the episode records what was surfaced (**episodic**). Next session, the index + episodic record are why Fyn builds on prior advice instead of blindly re-pitching.

**For the user:** this is what makes Fyn a financial companion rather than a stateless chatbot — recommendations quantified from their own live numbers, explained with a consistent house view, remembered across sessions, FCA-traceable end to end.

## 3. Build state on `coala` (verified 2026-06-11) and Track 2's touch

| Flow element | State on coala | Track 2 touch |
|---|---|---|
| Lean prompt + index load at startup | Exists (static prompt byte-invariant; assembler layers; records summary as the user-data index; tool catalogue as the skills index) | Improve the **index entries** for recommendations: the rewritten `get_recommendations` description (§4f) and strategy-headline signal; full lean-context rework (B3) stays with the landing programme |
| Per-turn semantic check / load | Phase 1 built (sparse retriever → `<knowledge>`); `house_view/` **empty** | Author the house_view corpus (§4a) so recommendation-intent turns load rationale |
| Per-turn procedural skill invocation | Phase 4 built (pointer registry, tool_schema corpus); Phase 5 built (FynLoop, planner behind flag) | Recommendation fetch-skill returns the composed plan (§4b); planner routing heuristics (§4c); overlay category (§4c) |
| Interrupt handling | Phase 5 items 6–7 built (queue, cancel, resumption) | None — verify it survives the merge (step-0 gate) |
| Session-end episodic write | Phase 2 built (rows, blobs, provenance, summariser) | Strategy-id granularity in provenance; session-scoped re-pitch awareness (§4e) |
| Learning/consolidation beyond the episodic write | Phase 6 **not built** | Out of scope — deferred (§5) |

## 4. Locked decisions (CSJ, 2026-06-11)

1. **The agent flow in §1 is canonical.** All Track 2 work conforms to it; anything that would fatten the prompt against the lean-prompt law is wrong by definition.
2. **Branch strategy: dev → coala first.** One merge commit brings Track 1 in; zero textual conflicts measured. The coala → dev landing ships later, as its own verified programme.
3. **Corpus scope: the tax strategy set first** (~20 files). Module growth is later content work; `fca` exemplar authoring stays paused.
4. **Compliance review: at the PR.** CSJ's review of the corpus PR is the compliance review; coala is deployed nowhere.
5. **Planner: heuristics only, feature flag unchanged. Overlays: corpus + loader category only** (consumption wiring stays with finish-Phase-4).

## 5. Sequencing — the merge is step 0, and one ordering is forced

**Step 0: `dev → coala` merge** (single merge commit). Forced ordering: coala's tool-catalogue **golden masters assert byte-identity between the `tool_schema` corpus and the PHP catalogue**; Track 1 changed `get_recommendations`' description in PHP, so the golden masters fail on merge until the corpus pair is updated — §4f ships inside the step-0 PR.

**Post-merge gate (before other Track 2 work):** full coala suite green incl. regenerated golden masters + the `FynSystemPrompt` byte-invariance snapshot (static prompt untouched by everything here); FynLoop proof tests + GroundGate/SurfaceAllowlist parity green (no write surface widens); **Azlan golden scenario replayed on coala — fixture-mode pre-check, one live grok-4.3 run as the required gate** (fixtures omit tool inputs); interrupt machinery (queue/cancel/resume) exercised once post-merge; root plan file resolves to v0.5.

## 6. Workstreams

### 6a. Semantic — house_view corpus (~20 files)

One file per live `source='strategy'` catalogue row, named by strategy id, following `fyn-memory/semantic/_TEMPLATE.md`: **narrative** (what/when/who), **methodology rationale** (why Fynla quantifies it this way), **sequencing reasoning** (`do_before`/`conflicts_with` in prose), **claim tier + voicing**. Zero figures (no-£ guard); sources: catalogue rows, strategy classes, `FinancialPlanningKnowledge`; British English; Rules 9/12/15 apply to user-surfaceable copy.

Acceptance: validator green; a retrieval test proves a recommendation-intent turn loads house_view content into `<knowledge>` — and an unrelated turn does not (lean-prompt law).

### 6b. Procedural — the Recommendation fetch-skill returns the composed plan

The Phase-4 `Recommendation` pointer handler swaps its payload from raw `ranked_recommendations` to the `StrategyPlanComposer` structured plan (top strategies with working, sequencing, claim tiers, locked-strategy count) — the same substance as `get_recommendations`, so skill and tool cannot disagree. Fetch-on-demand only (lean-prompt law: the plan is never always-on context). Fail-degrade unchanged; no write-permission change. (Plan re-verifies the handler's current return path first — description from a 9-day-old memory.)

Acceptance: handler unit tests against the composed shape; a **shape-parity test** pinning skill output against `get_recommendations`.

### 6c. Procedural + decision cycle — overlays and planner heuristics

- **Overlay category:** new `fyn-memory/procedural/overlay/` (only `pointers`/`tool_schema`/`workflow` exist). Two files — A1 answer-the-user-first, A2 ack-hygiene — mirroring Track 1's behavioural rules. Loader/validator/reindex recognise the category; **no consumption wiring**.
- **Planner heuristics:** routing guidance in the planner prompt implementing §1 step 2 for recommendation turns — recommendation intent → invoke the Recommendation fetch-skill; strategy locked by missing data → ask the unlock question rather than a blind action. Flag unchanged; flag-on tests assert the routed `Action` per intent.

### 6d. Working memory — lean-prompt conformance tests (no construction)

Working memory exists; Track 2 builds nothing in it and owes it conformance guarantees:

- **On-demand arrival:** the composed plan enters the context window only on recommendation-intent turns (via the fetch-skill or tool result); a test asserts presence there and absence on unrelated turns.
- **A4 synthesis persistence under FynLoop:** the savetax synthesis Fyn voices is persisted so `/tax-strategy` shows exactly what was said — verified to still hold under the shared loop post-merge (test + the Azlan replay).
- No change to context assembly plumbing, `FynTurnContext`, or `persona_state` (Phase 3 consolidation is not this work; B3's full lean-context rework belongs to the landing programme).

### 6e. Episodic — the session records what was advised

- **Strategy-id granularity in fetch provenance:** when the Recommendation skill (or `get_recommendations`) returns a composed plan, provenance records the surfaced strategy ids + composed-plan version/hash — every finished session answers "which strategies did Fyn put in front of this user, when" (the FCA suitability trace and the substrate for Fyn evolving with the user). (Verify-first: the Phase-2 collector may already capture this granularity — then it's a test, not code.)
- **Re-pitch awareness, session-scoped:** the tool description + planner heuristic instruct Fyn to check the conversation for strategies already surfaced this session and acknowledge prior discussion when re-surfacing. Prompt-level, cheap.
- **Cross-session recall deferred:** dense similar-case retrieval is Phase 6; `RecommendationTracking` remains the cross-session done-state the composer/aggregator already consult. Boundary named so it isn't forgotten.

Acceptance: a feature test asserting the episode record for a recommendation turn carries surfaced strategy ids; tool/planner text reviewed at PR.

### 6f. Procedural — tool_schema mirror (inside the step-0 PR)

`get_recommendations.md` + `.xai.md` updated to Track 1's rewritten description plus the §6e re-pitch guidance; golden masters regenerated; dual-provider parity green.

## 7. Out of scope

Phase 3 (assembly-code consolidation; `persona_state` migration); Phase 6 (learning actions — episodic→semantic promotion, dense recall); B3's full lean-context rework (landing programme); planner default flip; overlay consumption wiring; Option-A shell deletion; the coala → dev landing; `fca` corpus authoring; non-tax corpus growth; any change to `FynSystemPrompt::text()`; any change to the write-safety contract or gated write skills.

## 8. Success criteria

1. dev → coala merged; suite, golden masters, FynLoop + gate parity tests green; **live-grok Azlan scenario green on coala**; interrupts exercised; plan file at v0.5.
2. ~20 house_view files pass the no-figures validator; recommendation-intent turns provably load them; unrelated turns provably don't.
3. Recommendation fetch-skill shape-parity with `get_recommendations` green.
4. Planner heuristic routing tests green (flag-on).
5. Overlay category validated; A1/A2 content matches Track 1's rules.
6. Composed plan arrives on demand only; A4 synthesis persistence holds under FynLoop.
7. Session episodes record surfaced strategy ids; re-pitch guidance present in tool description + planner prompt.
