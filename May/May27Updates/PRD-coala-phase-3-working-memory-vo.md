# PRD — CoALA Phase 3: Consolidated Working Memory VO

**Project:** Fyn brain rewire — Phase 3 (Consolidate)
**Owner:** CSJ
**Status:** Draft — codebase audit completed during plan revisions v0.1 → v0.4
**Date:** 27 May 2026
**Spec & Plan:** `/Users/CSJ/Desktop/fynla/fynla-coala-implementation-plan.md` (v0.4)
**Canonical contract:** `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/00-canonical.md`
**Codebase audit:** Performed during plan v0.1 → v0.4 revision cycle

---

## 1. Context & Why

### Problem

What CoALA calls "working memory" — the structured per-turn state Fyn carries while reasoning — is currently spread across three incompatible sources:

1. **`FynTurnContext` VO** (`app/Services/AI/Fyn/FynTurnContext.php:15-55`) — immutable value object covering bucket / mode / preview flags. The cleanest of the three.
2. **`ai_conversations.persona_state` JSON column** (`2026_04_22_000002_add_persona_state_to_ai_conversations.php`) — populated for the now-removed `FynPersonaOrchestrator`. **Orphan/legacy.** Per CLAUDE.md "No FynPersonaOrchestrator, no invoker, no registry, no DataCapturePromptBuilder." The column persists from the pre-unification era.
3. **`ai_conversations.onboarding_parked_facts` JSON column** (`2026_04_22_000003`) — buffer for `OnboardingFactExtractor` output. Mixes parked-fact state with everything else.

Plus implicit working-memory-shaped fields scattered through method parameters and request-scoped service state (current route, current module, KYC status, prerequisite gate state, tax year).

CoALA Section 4.1 makes the point sharply: *"the working memory (a data structure) should not be confused with the LLM context (a string)."* Today we conflate them — the `<context>` block assembled by `FynContextAssembler` is the closest thing to working memory, but it's a string template not a typed structure. State that should be on a typed VO with named fields is parsed back out of strings or pulled from disparate sources every turn.

### Business case

- **Engineering velocity.** Phase 5's decision loop needs a single typed shape to pin `procedural_version`, `tax_year`, `cycle_count`, `session_mode`, `pending_questions`, and proposed-action data. Three sources of working-memory-shaped state means three places to update, three places to read, three places where state can drift.
- **Type safety.** The current persona_state JSON has no schema. Future engineers (including future-CSJ) can't see what fields exist without grepping every writer. A PHP readonly VO with named properties is self-documenting and IDE-discoverable.
- **CoALA conformance.** This is the smallest of the six phases because most of the templating infrastructure already exists (`FynContextAssembler` is genuinely good). What's missing is the single typed state object the assembler templates *from*.
- **Risk reduction.** `persona_state` is orphan code. Orphan code accumulates bugs because nobody owns it. Phase 3 either repurposes or removes it cleanly.

### Strategic fit

Smallest of the six phases. Unlocks Phase 5 (decision loop needs the VO to track cycle state). Touches the coordination module (AI chat). Zero end-user UX change. Touches only files that already exist — no new surfaces.

---

## 2. Target Persona

**Infrastructure — indirectly benefits all personas.** No end-user UX change.

**Primary internal beneficiary:** Engineers building Phase 5 — get a single typed shape to plumb cycle state through.

**Secondary internal beneficiary:** Engineers debugging Fyn turns — get IDE-navigable state instead of grepping JSON columns.

---

## 3. Success Metrics (KPIs)

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| Number of working-memory-shaped sources | 3 (`FynTurnContext` + `persona_state` JSON + `onboarding_parked_facts` JSON) | 1 (`WorkingMemory` VO) | Grep audit + architectural review |
| `persona_state` column usage | Backfilled on every conversation; no live consumer post-FynPersonaOrchestrator removal | Deprecated and dropped (after consumer audit) | Migration history + `grep -r persona_state app/` returns zero hits |
| `onboarding_parked_facts` integration | Standalone JSON column | Folded into `WorkingMemory.parked_facts` typed channel | Read path uses VO accessor, not direct column read |
| Type coverage of working memory | Partial (`FynTurnContext` only) | Full (every field on the VO is typed) | Static analysis via Psalm/PHPStan if available, else PR review |
| `FynSystemPrompt::text()` byte-identical | Confirmed today | Maintained (Phase 3 must not touch the static prompt) | CI hash check on the file |

---

## 4. User Stories & Scenarios

### User stories

- As an **engineer building Phase 5**, I want a single `WorkingMemory` VO with named typed properties so that I can plumb `cycle_count`, `procedural_version`, `pending_questions`, `proposed_action`, `tax_year` through the loop without inventing new state channels.
- As an **engineer debugging a turn**, I want to dump `WorkingMemory::toArray()` for a given turn and see every field that contributed so that I can reproduce the turn from working-memory state alone.
- As a **future engineer touching `ai_conversations`**, I want orphan/legacy columns removed or formally repurposed so that I don't extend dead code.
- As a **Fyn turn**, I want my tax year pinned at session start so that mid-session the rules don't change underneath me.

### Key scenarios

**Scenario 1 — A turn picks up an existing `WorkingMemory`:**

1. Fyn turn begins. `FynLoop` (Phase 5 — but Phase 3 prepares the substrate) constructs `WorkingMemory` from:
   - `session_id` and `client_id` from the request.
   - `session_mode` from the dispatch predicate (advice / onboarding).
   - `tax_year` pinned from `TaxConfigService::getActive()->tax_year` on the first turn of a session; reused on subsequent turns.
   - `procedural_version` pinned at session start from the procedural corpus (Phase 4) or a fallback constant pre-Phase-4.
   - `client_summary` from `MemoryRetrieverService` 4-layer fall-through.
   - `cycle_count` = 0.
   - `pending_questions` from prior turn's `pending_resumption` if any.
   - `parked_facts` from `ai_conversations.onboarding_parked_facts` (read via VO, not direct column).
2. Assembler templates the LLM prompt from VO fields by name.
3. LLM output parses back into VO updates: `proposed_action`, `current_draft`, `pending_questions`, `cycle_count++`.
4. Turn ends. VO state serialised to whatever persistence layer survives the turn — initially still `ai_conversations` JSON columns (typed shape now), eventually a dedicated table if growth justifies it.

**Scenario 2 — Migrating `persona_state` away:**

1. Audit: `grep -r "persona_state" app/` to find every reader and writer.
2. Producer-side: identify what `persona_state` actually contains today (likely: `current_persona`, `pending_advice_question`, `capture_context`, `turns_in_capture` per the codebase research notes in plan v0.4).
3. Consumer-side: identify who reads it. Per CLAUDE.md, `FynPersonaOrchestrator` is gone. If readers are also gone, the column is fully orphan.
4. If fully orphan: migration drops the column. Done.
5. If readers exist: map their reads to the new VO accessors. Drop the column after readers cut over.

**Scenario 3 — `tax_year` pinning edge case:**

1. Session opens at 23:59 on 5 April. `TaxConfigService::getActive()->tax_year` returns `2026-27`.
2. `WorkingMemory.tax_year = "2026-27"`, pinned for the session.
3. Conversation continues past midnight. `TaxConfigService` may now flip to `2027-28` on its next refresh. The session's `WorkingMemory.tax_year` does NOT update.
4. Next session (new `WorkingMemory` instantiation) pins to whatever is active at that time.
5. Documented edge case: a single conversation may use a tax year that became inactive mid-conversation. This is correct behaviour — advisor consistency within a conversation matters more than mid-conversation rule swaps.

**Unhappy path — `WorkingMemory` field set to invalid value:**

1. Engineer instantiates `WorkingMemory` with `cycle_count = -1` (impossible).
2. VO constructor throws an `InvalidArgumentException`.
3. Caller surfaces canonical error response. Audit log entry written.
4. No silent acceptance of impossible state.

---

## 5. Functional Requirements

### Must-have

- **FR-M1:** Define `WorkingMemory` VO as a PHP `final readonly class` with named typed properties: `sessionId: string`, `clientId: int`, `sessionMode: SessionMode (enum: advice | onboarding)`, `currentModule: ?ModuleEnum`, `clientSummary: string`, `retrievedFacts: SemanticFact[]`, `retrievedEpisodes: Episode[]`, `currentDraft: ?string`, `pendingQuestions: string[]`, `proposedAction: ?Action`, `observations: Observation[]`, `taxYear: string`, `proceduralVersion: string`, `cycleCount: int (default 0, max 8)`, `parkedFacts: array (typed via FactBag VO if it doesn't exist yet)`. Constructor validates ranges. _Touches: new `app/Services/AI/Memory/Working/WorkingMemory.php` + `SessionMode.php` enum._
- **FR-M2:** `WorkingMemoryBuilder` service that constructs a `WorkingMemory` for a given turn from the existing sources: `FynTurnContext`, `ai_conversations.persona_state` (during the deprecation window), `ai_conversations.onboarding_parked_facts`, `MemoryRetrieverService`, `TaxConfigService`. Pure — no side effects. _Touches: new service._
- **FR-M3:** Refactor `FynContextAssembler::build()` to accept a `WorkingMemory` VO as its primary input. Existing assembly code becomes a templating layer over named VO fields. Internal logic unchanged; signature changes. _Touches: `app/Services/AI/Fyn/FynContextAssembler.php`._
- **FR-M4:** Audit and migrate `ai_conversations.persona_state`. Step 1: `grep -r "persona_state" app/ config/ database/` to enumerate readers and writers. Step 2: confirm no live consumer (per CLAUDE.md, `FynPersonaOrchestrator` was removed). Step 3: if confirmed orphan, write deprecation migration setting all rows to NULL and dropping the column in a follow-up. Step 4: if live consumers exist, map them to `WorkingMemory` VO accessors and drop after cutover. _Touches: migration; depends on audit outcome._
- **FR-M5:** Fold `ai_conversations.onboarding_parked_facts` into the VO via a typed `FactBag` accessor. The column stays as the persistence backing for now; the VO is the access path. Direct column reads from PHP code disallowed post-Phase-3 (CI lint). _Touches: VO accessor; deprecation guidance for direct reads._
- **FR-M6:** `WorkingMemory.serialise(): array` method for diagnostic dumps and (eventually) episode persistence. Round-trip safe: `WorkingMemory::fromArray($vo->serialise()) == $vo`. _Touches: VO methods + corresponding test._
- **FR-M7:** Pin `tax_year` and `procedural_version` at session start. `WorkingMemoryBuilder` reads them once at session start (first turn of a (re)opened conversation), stores them on the working memory's persistent slice, and reuses across subsequent turns of that session. Documented edge case: session opened pre-rollover keeps the old year for that session's life. _Touches: builder logic; persistence of pinned values (column or JSON field on `ai_conversations`)._
- **FR-M8:** `FynSystemPrompt::text()` remains byte-identical. CI hash check fails the PR if the file changes outside an explicit rev sub-task. _Touches: CI workflow._
- **FR-M9:** Remove direct `chat_history → prompt` string-concat paths (per plan v0.4 §"Working memory" current equivalents — assembler is already templated, but verify no residual concatenation paths exist for advice/onboarding edge cases). _Touches: audit of `app/Services/AI/HasAiChat.php` and `app/Services/AI/AdvicePromptBuilder.php` (legacy)._

### Should-have

- **FR-S1:** Static analysis coverage: if Psalm or PHPStan is configured in the repo, add `WorkingMemory` to the strict-types scope. _Touches: psalm config._
- **FR-S2:** Diagnostic admin route for displaying current `WorkingMemory` state of an in-progress conversation (admin-only, no end-user surface). Read-only, no editing. Wraps in `AppLayout`. _Touches: new admin view + controller._

### Nice-to-have

- **FR-N1:** Compile-time generation of `WorkingMemory` from a YAML schema spec. Makes adding fields safer. Out of scope unless engineering finds the manual maintenance burden material. _Touches: future._
- **FR-N2:** Snapshot diffing — given two `WorkingMemory` instances from consecutive turns, emit a structured diff for debugging. _Touches: future._

---

## 6. User Flow & UX/Design

### Engineering flow (no end-user UX)

```
Turn N entry
  └─ WorkingMemoryBuilder::build(user, conversation, request)
       ├─ Carry forward from turn N-1's persisted slice:
       │    tax_year, procedural_version, parked_facts, pending_questions
       ├─ Compute fresh per-turn:
       │    session_mode (from dispatch predicate)
       │    current_module (from current_route)
       │    cycle_count = 0
       │    client_summary (from MemoryRetrieverService)
       │    retrieved_facts (from SemanticRetriever — Phase 1)
       │    retrieved_episodes (from MemoryRetrieverService 4-layer)
       └─ Return WorkingMemory instance (immutable per turn step)

Throughout the turn
  └─ Each loop cycle (Phase 5) instantiates a NEW WorkingMemory with
     updated fields. Immutable: append-only semantics within the turn.

Turn N exit
  └─ Persist slice: tax_year, procedural_version, parked_facts (still),
     pending_questions, last semantic_snapshot_id.
  └─ Append to episodic memory (Phase 2 path).
```

### Admin diagnostic view (Should-have)

- **Design system:** Standard `AppLayout`, monospace block for the serialised VO. No icons. No emoji. Standard table for nested arrays.
- **Reuse:** existing audit-log viewer patterns.
- **Access:** admin-only middleware. No end-user route.

---

## 7. Out of Scope

- Persisting full `WorkingMemory` per turn to a new dedicated table. Phase 3 piggybacks on existing `ai_conversations` JSON columns plus the per-turn `ai_messages` row. A dedicated `working_memory_snapshots` table is deferrable — measure footprint first.
- Cross-conversation working-memory carry-over. Each session pins its own `WorkingMemory`. No "Fyn remembers what you said last week" via WM (that's episodic memory's job via `MemoryRetrieverService`).
- Changing `FynSystemPrompt::text()`. Byte-identical for prefix-cache.
- Removing `FynTurnContext` immediately. Keep as an internal helper if useful; treat the VO consolidation as additive in Phase 3 and remove redundant scaffolding once Phase 5 confirms the new shape works.
- Frontend changes. Working memory is server-side only.
- A new ORM/repository for VO persistence. Use the existing model layer.

---

## 8. Risks & Dependencies

### Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| `persona_state` has a hidden live consumer not surfaced by initial grep | Medium | Medium | Two-pass audit: grep first, then add deprecation `Log::warning` on column reads, run on dev for one week to catch dynamic accessors. Drop only after silence. |
| `WorkingMemory` constructor validation rejects edge cases that the existing system silently accepted | Medium | Low | Constructor validation is opt-in initially; ship in "warn don't throw" mode for one release; tighten after. |
| Mid-session tax-year flip surprises a user | Low | Low | Documented edge case. Acceptable per CLAUDE.md "Database - Reseed" and `TaxConfigService` semantics. |
| Refactoring `FynContextAssembler::build()` signature breaks legacy `FYN_PROMPT_ARCH=legacy` path | Medium | Medium | Legacy path has its own assembler. Verify legacy still builds context the legacy way. The signature change applies to the unified path only. Per MEMORY.md `reference_legacy_refuses_advice_capture_journey.md`, the legacy path already has issues — Phase 3 must not worsen them. |
| Tests targeting `FynTurnContext` directly need rework | High (any change to a VO ripples to tests) | Low | Update tests as part of the refactor. Test surface is internal — no external contract change. |

### Technical dependencies

- `FynTurnContext` — partial overlap with the new VO. Decision: extend or replace. Recommendation: replace, then keep the old class temporarily as a deprecation shim if anything outside the assembler depended on it.
- `FynContextAssembler` — integration point.
- `MemoryRetrieverService` — provides several VO fields. No change to its interface.
- `TaxConfigService` — provides `tax_year`. No change to its interface.
- `ai_conversations.persona_state` and `ai_conversations.onboarding_parked_facts` columns — legacy state. Phase 3 owns the deprecation/migration.

### Sequencing dependencies

- **Blocks:** Phase 5 (decision loop) builds on the typed VO.
- **Blocked by:** Nothing strictly. Can ship in parallel with Phase 2 or Phase 4. Recommended sequencing keeps it after Phase 2 (Phase 2 adds `procedural_version` and `semantic_snapshot_id` columns the VO carries through).

### Residual concerns from codebase audit

- **`FynTurnContext`'s exact field list.** Plan v0.4 references "mode / preview flags" — confirm exact fields during implementation and decide whether to retire it or evolve it.
- **`onboarding_parked_facts` semantics post-Phase-3.** The column stays; the access path becomes the VO. But the existing `OnboardingFactExtractor` writes to it directly. Decide whether the extractor writes via the VO or keeps direct column access (recommendation: extractor writes through VO; one access path enforced by lint).
- **Persistence of `cycle_count` across cancellation.** A cancelled turn (queue policy from Phase 5) — does its `cycle_count` advance the persistent slice or not? Recommendation: cancellation resets `cycle_count` for the next turn; the cancelled one's count is recorded in the episode but doesn't bleed forward. Confirm in Phase 5 implementation.

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| 27 May 2026 | Initial draft from CoALA v0.4 plan Phase 3 | prd-writer skill |
