# PRD — CoALA Phase 5: Decision Loop, Concurrent Turns, and Cost Telemetry

**Project:** Fyn brain rewire — Phase 5 (Orchestrate)
**Owner:** CSJ
**Status:** Draft — codebase audit completed during plan revisions v0.1 → v0.4
**Date:** 27 May 2026
**Spec & Plan:** `/Users/CSJ/Desktop/fynla/fynla-coala-implementation-plan.md` (v0.4)
**Canonical contract:** `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/00-canonical.md`
**Codebase audit:** Performed during plan v0.1 → v0.4 revision cycle

---

## 1. Context & Why

### Problem

Fyn today is **one LLM call per turn with a tool-use loop**. No explicit plan/execute split. `QueryClassifier` routes the response mode pre-LLM but classifies, doesn't decompose. The LLM emits tool calls in its stream, `CoordinatingAgent::executeTool` dispatches them via a single `match` statement, results feed back, the loop re-enters. This works — but it's missing four things CoALA's framework prescribes and Fynla's roadmap needs:

1. **A typed `Action` enum.** Tools today are flat provider-shaped function arrays (`AiToolDefinitions::php` for Anthropic, `XaiToolDefinitions.php` for xAI). There is no `reason | retrieve | learn | ground` typing on the LLM's output. Phase 5 wraps the existing flat catalogue in the CoALA typed action space.
2. **A mechanically enforced `ground` surface gate.** Write safety is today the dispatch-level `array_diff` against `AdviceFyn::WRITE_TOOLS`. After Phase 5, the safety boundary is *also* (or *instead* in Option A) the typed action's surface allowlist keyed on `session_mode`. Closed-set switch, fail-closed.
3. **Per-action cost telemetry.** Today: per-message tokens (`ai_messages.input_tokens / output_tokens`), per-conversation rollup, per-user-day atomic counter (`ai_daily_usage`). Not tracked: per-tool / per-action / per-procedure / per-mode cost, GBP cost, prompt-cache hit/miss.
4. **Concurrent-turn policy and resumption.** Today: a second user message during an in-flight turn races naturally over SSE — undefined behaviour. Half-finished sessions (pending questions, failed write intents, fatal errors mid-stream) have no resumption surface.

The v0.4 plan also lifts a CSJ directive: this should be the **same flow for advice and onboarding**, with the safety boundary moving to the `ground` gate (Option A: full collapse) or running at both dispatch + gate (Option B: shared loop with thin shells — recommended). Phase 5 ships the shared `FynLoop` service that both options ride on.

### Business case

- **Regulatory.** Per-action cost attribution and prompt-cache hit-rate telemetry are prerequisites for explaining AI cost to the FCA and the board. "We spent X on advice last month" is the answer today; after Phase 5 it's "We spent X on advice, of which Y was reasoning and Z was retrieval and W was procedure-version A".
- **Cost discipline.** The unified prompt is *designed* for Anthropic prefix-cache. Today we don't measure whether the cache is actually hit. Phase 5 makes the cache rate observable — and then optimisable.
- **Safety boundary clarity.** Whichever collapse option ships (the canonical contract's preserved Option B or the eventual Option A), the `ground` gate is the new mechanical write-safety layer. Phase 5 ships it as the first PR, with its own dedicated test suite, before any planner work.
- **Concurrent-turn UX.** Today's race is undefined; tomorrow's defined queue (depth cap 3, three visual states, cancel, transcript-honest) removes a real source of confusion.
- **Resumption.** A user whose conversation cut out mid-question is currently silent on next login. Phase 5 surfaces the resumption opportunity explicitly.

### Strategic fit

The orchestration layer. Wires together Phases 1–4 into one observable, attributable, gated, resumable flow. Touches every Fyn turn. Has **two end-user visible changes**: the concurrent-turn queue visual states (greyed / processing / answered / cancelled) and the resumption prompt on session start.

---

## 2. Target Persona

**Primary:** All chat users. The concurrent-turn queue and resumption check are visible UX changes. Personas affected:

- `young_family`, `peak_earners`, `entrepreneur`, `young_saver`, `retired_couple`, `student` — every persona uses Fyn chat.
- Indirect: advisors and admins through the cost-attribution telemetry.

**Secondary internal beneficiary:** Engineering — typed action enum + planning budget + cycle cap removes whole classes of "Fyn looped forever" failure modes.

**Tertiary:** Compliance — gets per-action audit attribution.

---

## 3. Success Metrics (KPIs)

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| Per-turn cost attributable to `action_type` | 0% (only per-message tokens tracked) | 100% of LLM calls tagged with `(session_id, cycle_id, stage, action_type, procedural_version)` | New `ai_cost_attribution` table or `ai_messages.metadata` extension |
| Prompt-cache hit/miss telemetry coverage | 0% | 100% of LLM calls record `cache_hit_tokens` and `cache_miss_tokens` from provider | Field on the per-call telemetry record |
| GBP cost computation per turn | 0% (tokens only) | 100% — `gbp_cost` field populated using current model price table | Per-call telemetry record |
| Pre-planner bypass rate (turns skipping the LLM planner) | n/a (no planner exists) | Measured + reported; expect 30–50% based on existing deterministic short-circuits | Telemetry; rate computed nightly |
| Hard cycle cap exceedance rate | n/a | < 1% (alerts above threshold) | Telemetry; alert via existing ops channel |
| `ground` gate strip rate in advice mode | n/a | Matches today's WRITE_TOOLS denial pattern (zero false-positives, zero false-negatives) | Audit chain entries with `status: stripped` |
| Concurrent-turn queue depth distribution | n/a | p95 depth ≤ 1; depth-3 reject rate < 0.1% | Telemetry on `ai_messages.status` transitions |
| Concurrent-turn cancel rate | n/a | Measured | Telemetry on `status: cancelled` |
| Resumption-prompt surface rate per session | n/a | Measured + acceptable (target TBD: CSJ — guidance: 5–15% feels right) | Telemetry on `pending_resumption` reads at session start |
| End-to-end turn latency increase from planner | n/a | < 1.5x baseline single-LLM-call latency for non-bypass turns | A/B vs single-LLM-call control during rollout |

---

## 4. User Stories & Scenarios

### User stories

- As a **Fyn user**, I want my second message to be acknowledged with a greyed bubble while my first is still being answered, so I know it was received and will be picked up.
- As a **Fyn user**, I want the option to cancel a message I sent in haste, so I don't have to wait for an unwanted answer.
- As a **Fyn user**, I want to be reminded of an unfinished conversation when I come back, so I can pick up where I left off rather than re-explain.
- As **Compliance**, I want every Fyn turn's spend to be attributable to a specific action type and procedure version, so I can demonstrate cost discipline and procedural provenance.
- As an **engineer**, I want the `ground` surface gate to be a closed-set switch keyed on `session_mode`, so write safety is mechanical, not a planner promise.
- As **Fyn** (the agent), I want to skip the LLM planner when the onboarding state machine has a deterministic next action, so I don't burn tokens on routing decisions a rule can make.

### Key scenarios

**Scenario 1 — Normal turn through the full loop:**

1. User sends message. Existing dispatch + consent gate fires.
2. Resumption check: no `pending_resumption` for this user. Continue.
3. Pre-planner deterministic bypasses run in order (out_of_remit → duplicate ack → write_intent direct capture → state-machine direct dispatch). None fire.
4. Working memory hydrated: buckets selected, semantic retrieval (Phase 1), episodic retrieval (Phase 2), KYC/prerequisite state resolved into typed VO fields, `tax_year` and `procedural_version` pinned.
5. Planner LLM call. Returns one typed action.
6. If `reason` / `retrieve`: cycle count incremented, loop back to step 4 with updated working memory. Cycle cap 8.
7. If `learn`: write to episodic (verbatim per Phase 2) or semantic (Phase 6 — always human-gated, never auto-applied here).
8. If `ground`: surface checked against `session_mode` allowlist. Allowed → dispatch via `CoordinatingAgent::executeTool` (reused) wrapped in the gate. Denied → audit chain entry `status: stripped`, canonical refusal.
9. Stream tokens to user from the most recent `reason` action's output. `thinking` SSE event emitted during steps 4–7 before any reason output exists.
10. End-of-turn: verbatim persistence via Phase 2's atomic write protocol, audit chain entry per `ground`/`learn`, delta summary appended to `ai_conversations.summary`, `ai_daily_usage` counter incremented.
11. If turn ended with `pending_questions` populated, OR write_intent had no successful tool dispatch, OR fatal error: write `pending_resumption` to `ai_conversations`.
12. Pop the concurrent-turn queue if non-empty.

**Scenario 2 — Onboarding state machine bypass:**

1. User in onboarding flow. `users.onboarding_fyn_step = "salary_capture"`.
2. Dispatch routes through the shared `FynLoop` (Option B shell) or directly (Option A).
3. Pre-planner step 1d: `OnboardingStateMachine` has a deterministic next action — `capture_salary_sacrifice`. Emit that ground action directly.
4. NO planner LLM call. Audit chain records `status: dispatched` via deterministic path.
5. Tool executes, persists, audit chain appended. User sees the next onboarding step.

**Scenario 3 — Concurrent-turn queue:**

1. User sends turn A. SSE stream opens, planner runs.
2. While turn A is still streaming, user sends turn B.
3. Server persists turn B as `ai_messages` row with `status: 'queued'` immediately.
4. UI receives a `turn_queued` SSE event for the original stream. Renders turn B in the chat history with greyed bubble + cancel affordance.
5. Turn A completes, writes its persistent record, transitions `status: 'queued' → 'processing'` on turn B, and the loop begins for turn B.
6. UI updates turn B from greyed → italic/pulsing while processing.
7. Turn B completes, transitions to `status: 'answered'`, normal bubble.

**Scenario 4 — User cancels a queued message:**

1. Turn A still running. Turn B queued.
2. User clicks cancel on turn B's greyed bubble. Confirms.
3. Frontend issues DELETE/PATCH on the queued message endpoint. Server transitions to `status: 'cancelled'`.
4. UI shows turn B struck through.
5. After turn A completes, queue pops turn B's status; it's cancelled, so the loop skips it and moves to whatever's next (likely empty queue, returns to idle).

**Scenario 5 — Resumption check on session start:**

1. Previous session ended with `pending_questions: ["What's your annual income?"]` populated and `pending_resumption` written.
2. User returns to Fyn the next day.
3. Resumption check at step 0 fires. UI prompts: "Last time I asked about your annual income but we didn't finish — want to continue, or start fresh?"
4. User chooses "Continue". Working memory reconstructs with `pending_questions` pre-populated. Planner sees them and resumes.
5. User chooses "Start fresh". `pending_resumption` cleared. New turn starts clean.

**Scenario 6 — `pending_questions` edge case:**

1. Turn A's planner emits `reason` action that asks the user a clarifying question. `pending_questions: ["What does X mean to you?"]` populated.
2. Before turn A's `done` event arrives, user sends turn B asking something completely different.
3. Turn B is queued with `status: 'queued'`. Its timestamp is captured.
4. Turn A streams its question, completes. Writes `pending_questions` to working memory's persistent slice and `pending_resumption` to the conversation row.
5. Queue pops turn B. Planner detects: `working_memory.pending_questions` non-empty AND turn B's user-message timestamp predates turn A's `done` event timestamp.
6. Planner recognises turn B is NOT an answer to the clarifying question. Responds: "I was going to ask about X, but to answer your follow-up about Y first…".
7. Turn B completes. Resolution: clarifying question may be re-asked at end of turn B if still relevant; or dropped if turn B's answer subsumes it. Decision left to the planner.

**Scenario 7 — Cycle cap exceeded:**

1. Planner emits `reason → retrieve → reason → retrieve → reason → retrieve → reason → retrieve` (8 actions).
2. On the 9th planning step, dispatcher refuses: cycle cap hit. Emits `no_action` with canonical "I need more time on this — let me come back to you" response.
3. Writes `pending_resumption` with current working-memory state.
4. User sees the canonical response, can pick up the question next turn or next session.
5. Telemetry records cycle cap exceedance. Above threshold (default < 1%), alert fires.

**Scenario 8 — Inactivity timer summary consolidation:**

1. Turn completes. No new turn arrives.
2. After 3 minutes of inactivity, scheduler triggers conversation consolidation.
3. Per-turn delta summaries (appended in step 6 of the loop) are consolidated into a single final summary in `ai_conversations.summary`.
4. Conversation `status: 'paused'`. Not closed.
5. Next message from user (any time later) reopens. Resumption check fires per Scenario 5.

**Unhappy path — `ground` action surface denied:**

1. Planner in advice mode emits `{ action: 'ground', surface: 'create_savings_account', args: { ... } }`.
2. Gate checks `session_mode === 'advice'` allowlist for `create_savings_account`. Not present.
3. Gate refuses. Audit chain entry: `status: stripped`, `tool_name: create_savings_account`.
4. Canonical refusal emitted to user: "I can't create accounts in advice mode — would you like to go to the savings module to add one?".
5. NEVER falls back to "try anyway". Closed-set, fail-closed.

---

## 5. Functional Requirements

### Must-have

- **FR-M1:** Typed `Action` enum: `reason | retrieve | learn | ground | no_action`. Each variant with typed fields (`reason` has `prompt_template_id`, `working_memory_fields[]`; `retrieve` has `store`, `query`, `filters`; `learn` has `store`, `payload`; `ground` has `surface`, `args`). _Touches: new `app/Services/AI/Actions/Action.php` + variants._
- **FR-M2:** `FynLoop` service implementing the full per-message flow (steps 0–8 from the v0.4 plan and Scenarios 1–8 above). Single class running the cycle. _Touches: new service `app/Services/AI/Loop/FynLoop.php`._
- **FR-M3:** **Recommended Option B (shared loop with shells):** `AdviceFyn::handle` and `OnboardingChatDirector::handleUserMessage` both delegate to `FynLoop`. Existing dispatch predicate in `AiChatController::sendMessage` unchanged. _Touches: `app/Services/AI/AdviceFyn.php`, `app/Services/Onboarding/OnboardingChatDirector.php`._ (If Option A approved by CSJ, replace this FR with: "Delete `AdviceFyn` and `OnboardingChatDirector` classes; dispatch goes direct to `FynLoop` with `session_mode` derived from the existing predicate.")
- **FR-M4:** **`ground` surface gate.** Closed-set switch keyed on `session_mode`. Hardcoded allowlists per mode in the gate (not in the procedural corpus — the safety boundary stays in code). Failing the gate emits an audit chain entry `status: stripped` and returns a canonical refusal. **Ship this in its own dedicated PR with its own dedicated test suite, MERGED BEFORE any planner work lands.** _Touches: new service `app/Services/AI/Actions/GroundGate.php` + test suite._
- **FR-M5:** Deterministic pre-planner bypasses (preserved from existing logic, restructured into the loop's step 1):
  - Out_of_remit short-circuit (existing `QueryClassifier` path).
  - Write-intent + duplicate check (existing `WriteIntentClassifier` + `RecordDuplicateChecker`).
  - Write-intent direct capture handoff (existing `handleInlineCapture` path, now invoked as a typed `ground` action with capture surface).
  - Onboarding state-machine direct dispatch (NEW — pre-planner step 1d).
  _Touches: refactor of `AdviceFyn::handle` short-circuit logic into the loop's step 1 module._
- **FR-M6:** Planner LLM call (step 3). Single call. Returns one typed action. Cycle cap 8 enforced by the loop. Max 2 `reason` actions and max 3 `retrieve` actions per turn (planning budget). _Touches: new service `app/Services/AI/Loop/Planner.php`._
- **FR-M7:** Concurrent-turn queue. New column `ai_messages.status` ENUM(`queued`, `processing`, `answered`, `cancelled`, `expired`). Depth cap 3. TTL 10 min (configurable). Three frontend visual states (greyed / italic-pulsing / normal). Cancel affordance on `queued` rows. _Touches: migration; backend queue popper logic in `FynLoop`; frontend `ChatMessageBubble.vue` + `ChatComposer.vue` state handling; new endpoint to cancel a queued message._
- **FR-M8:** Cancelled rows remain in the transcript (struck-through / marked). Never deleted. _Touches: frontend rendering of `status: cancelled`._
- **FR-M9:** Resumption check at session start. New column `ai_conversations.pending_resumption` JSON. Written at end-of-turn when predicates fire (`pending_questions` populated, write_intent failed, fatal mid-stream error in `ai_abort_events`, queued message past session end). Surfaced on session start with "resume / discard / start fresh" choice. _Touches: migration; `FynLoop` step 7 logic; frontend resumption prompt component._
- **FR-M10:** Inactivity timer (3 minutes). Consolidates per-turn delta summaries into a final summary in `ai_conversations.summary`. Marks conversation `status: 'paused'`. Reuses `ConversationSummariserJob` infrastructure with a new inactivity-triggered scheduling path. _Touches: scheduler; extension of `ConversationSummariserJob`._
- **FR-M11:** Per-action cost telemetry. Tag every LLM call with `(session_id, cycle_id, stage, action_type, procedural_version, prompt_cache_hit_tokens, prompt_cache_miss_tokens, gbp_cost)`. Sibling table `ai_cost_attribution` or extension of `ai_messages.metadata` — TBD by storage volume assessment. _Touches: new migration; `Planner` and `FynLoop` instrumentation._
- **FR-M12:** Prompt-cache hit/miss telemetry. Records the cache hit/miss tokens reported by the provider's response metadata (Anthropic surfaces `usage.cache_creation_input_tokens` / `usage.cache_read_input_tokens`; xAI surfaces equivalent). Maintained on every LLM call. **Ship this on its own, BEFORE Phase 1's assembler rewrites, to establish baseline.** _Touches: instrumentation in `HasAiChat::apiChat`._
- **FR-M13:** GBP cost computation alongside token counts. Maintains a model price table (config-driven, easy to update). For each LLM call, computes `gbp_cost = tokens × price_per_1k / 1000`. _Touches: new config `config/ai_pricing.php` + utility class._
- **FR-M14:** `thinking` SSE event. Emitted during steps 2–4 (before any reason output exists) so the UI shows a "Fyn is thinking…" indicator. _Touches: new SSE event type; frontend `ChatStream.vue` rendering._
- **FR-M15:** Per-action telemetry surfaced in admin UI. Cost-attribution dashboard showing spend by `action_type`, `procedural_version`, `session_mode` over time. Wraps in `AppLayout`. _Touches: new `resources/js/views/Admin/AiCostDashboard.vue` + controller. Charts use `designSystem.js` constants per CLAUDE.md Rule #11._

### Should-have

- **FR-S1:** Adaptive planning depth. If telemetry shows turns of a given query type consistently use only one action, the planner can short-circuit to direct response without intermediate `reason` actions. Out of scope for v1 of Phase 5 but flagged in the rollout plan. _Touches: future enhancement._
- **FR-S2:** Cycle cap as configurable per `session_mode` (currently global 8). _Touches: config._
- **FR-S3:** Queue depth and TTL as configurable. _Touches: config._

### Nice-to-have

- **FR-N1:** Per-turn cost preview in chat (e.g. small unobtrusive cost indicator for admin/test users). Useful for engineering but disabled for end users. _Touches: feature-flagged UI._
- **FR-N2:** Resumption surface deferred when user has recently dismissed it. Respect a "snooze" preference. _Touches: user preference._

---

## 6. User Flow & UX/Design

### Per-turn flow (see Architecture section diagrams in the v0.4 plan)

Steps 0–8 as detailed in Scenarios above.

### Concurrent-turn UI states

| State | Visual treatment | Behaviour |
|-------|------------------|-----------|
| `queued` | Greyed bubble with cancel affordance | User can cancel before processing begins |
| `processing` | Italic / subtle pulsing animation | Planner is running for this message |
| `answered` | Normal bubble | Response shown below; conversation continues |
| `cancelled` | Struck-through, muted | Preserved in transcript for audit; never deleted |
| `expired` | Faded with "expired — re-send if needed" caption | TTL exceeded while user was offline |

**Design system:** `fynlaDesignGuide.md` v1.3.0. Greyed uses `neutral-*` background; processing pulse uses `horizon-*` opacity animation; cancelled uses standard strikethrough + `neutral-*` opacity. No icons (Rule #16 — text labels and visual state speak for themselves; cancel is a text "cancel" link, not an icon).

### Resumption prompt UI

- Surfaced on session start (when `pending_resumption` exists).
- Modal or inline card at top of chat. **Modal preferred** to force the resume/discard/start-fresh choice before the user's new message lands.
- Three CTAs: "Continue where we left off", "Discard and start fresh", "Remind me later" (= snooze 7 days).
- Wraps in `AppLayout`. Standard `FormModal.vue` patterns reused.

### Cost dashboard (admin)

- Standard `AppLayout` chrome.
- Time-series charts of cost by `action_type`, `procedural_version`, `session_mode`. ApexCharts via `designSystem.js` constants per CLAUDE.md Rule #11 + the `ui-graph` skill.
- Table of top-cost turns for drilldown.
- No icons (Rule #16 — data-driven admin dashboard).

### `thinking` indicator

- Subtle "Fyn is thinking…" copy in standard bubble shape, muted text.
- Replaced by actual response tokens as soon as the planner emits its first `reason` action's stream.

---

## 7. Out of Scope

- Full collapse of `AdviceFyn` and `OnboardingChatDirector` classes (Option A). Phase 5 ships Option B (shared loop with thin shells). Revisit Option A after Phase 6.
- Multi-LLM planning. Single planner LLM call per cycle. Tree-of-thoughts / Monte-Carlo planning out of scope.
- Cross-conversation planner memory. The planner sees working memory + episodic retrieval results; it doesn't have its own state across turns.
- Adaptive planning-depth heuristics in v1. Static planning budget; tune later from telemetry.
- New analytics infrastructure beyond the cost-attribution table + admin dashboard.
- Mobile-specific concurrent-turn UI changes. Same SSE protocol, same state transitions. Mobile reads the same `ai_messages.status` field.
- Replacement of `QueryClassifier`. Stays as the pre-planner short-circuit per CSJ directive.
- Replacement of `WriteIntentClassifier` and `RecordDuplicateChecker`. Same — stay as deterministic bypasses.

---

## 8. Risks & Dependencies

### Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Planning-stage latency makes Fyn feel slower | High | Medium | Mitigations: (1) pre-planner bypasses skip the planner for ~30–50% of turns; (2) `thinking` SSE event covers perceived latency; (3) measure pre/post in shadow mode before cutover. |
| Double LLM cost for non-bypass turns | High (by design — planner + reasoner is two calls) | Medium | Anthropic prefix-cache mitigates most of it (static prompt identical across both calls). Phase 5 ships cache hit/miss telemetry to verify the mitigation works as designed. |
| `ground` gate bug breaks write-safety boundary | Low (with rigorous testing) | Critical | Mitigation: gate ships in its own PR, with its own dedicated test suite, **before** any planner work. Every new tool addition requires a paired test for "is this tool in the correct mode's allowlist?". PR checklist enforces. |
| Concurrent-turn queue grows unbounded if worker dies | Low | Medium | Depth cap 3 + TTL 10 min. Expired rows surfaced via resumption check. |
| Resumption surfacing UX friction (every new conversation opens with "resume?") | Medium | Low | Only fire when `pending_resumption` was written within 7 days (configurable). One-click "discard" available. Snooze via FR-N2 if needed. |
| Inactivity-timer races with new turn arrival | Low | Low | Optimistic-lock on `ai_conversations.updated_at`. If the conversation moved on, summary write discarded and re-triggered after new turn completes. |
| Eval re-baseline required regardless of collapse option | Certain | Medium | `01-invariants.md` 35 invariants + `09-canonical-behaviour` scenarios + 75 golden conversations all written against the existing two-class shape. Even Option B (preserves class shells) needs the invariants updated to reference the loop's behaviour. Budget time for this before Phase 5 merges to `main`. |
| Mobile-app SSE rendering of queued/processing/cancelled states | Medium | Medium | Mobile renders the same `ai_messages.status` field. iOS Capacitor's WKWebView quirks (per MEMORY.md `mobile_capacitor_patterns.md`) need verification of the new SSE event types. Test before merge. |
| `pending_questions` carry-forward across cancel | Medium | Low | Cancellation resets `cycle_count`; if turn A asked a clarifying question and was then cancelled (rare — usually the user cancels turn B, not turn A), the clarifying question remains in `pending_resumption` for next turn. Recommended: cancellation of turn-with-pending-question should ALSO clear that pending question, to avoid resurfacing on a re-engaged conversation. Confirm during implementation. |

### Technical dependencies

- **Strict prerequisite:** Phase 5 ships the **cache hit/miss telemetry first as its own PR**, before any other Phase 5 work. This establishes the baseline before Phase 1's assembler rewrites land.
- **Strong dependency:** Phase 2 (episode columns `procedural_version`, `semantic_snapshot_id`, blob path) populated by FynLoop's persistence step.
- **Strong dependency:** Phase 3 (typed `WorkingMemory` VO) — the loop's state object.
- **Strong dependency:** Phase 4 (`procedural_version` is meaningful only if procedures live in the corpus and are pinned by version).
- **Strong dependency:** Phase 1 (semantic retrieval is one of the `retrieve` action targets).
- `CoordinatingAgent::executeTool` — reused as the action dispatcher under the new typed action surface.
- `HasAiChat::apiChat` streaming — extended with `thinking` event and per-action telemetry tagging.
- `ConversationSummariserJob` — extended for inactivity-triggered consolidation.
- Anthropic + xAI provider response shapes — both must surface cache hit/miss tokens. Verify both during implementation.

### Sequencing dependencies

- **Blocked by:** Phases 1, 2, 3, 4 should all ship before Phase 5's full programme. Phase 5's first PR (cache hit/miss telemetry) ships first standalone.
- **Blocks:** Phase 6 (learning from experience) depends on Phase 5's typed action enum and per-action telemetry.
- **Internal sequencing within Phase 5:**
  1. Cache hit/miss telemetry — standalone PR. Pre-Phase 1.
  2. `ground` gate + test suite — standalone PR. Pre-planner.
  3. Typed `Action` enum + dispatcher refactor (no LLM planner yet — existing tool-use loop wraps in typed actions).
  4. `FynLoop` service + Option B shell delegation.
  5. Planner LLM call (with budget).
  6. Concurrent-turn queue (`ai_messages.status` migration + queue logic + frontend states).
  7. Resumption check + inactivity timer.
  8. Per-action cost attribution table + admin dashboard.

### Residual concerns from codebase audit

- **`ai_advice_logs` table overlap with new per-action telemetry.** Today this table captures per-advice-turn `query_type`, `classification`, `kyc_status`, `recommendations`, `tools_called`, `user_data_snapshot`. The new `ai_cost_attribution` shape overlaps in places. Recommendation: keep both, with `ai_advice_logs` as the legacy structured advice-turn record and `ai_cost_attribution` as the typed-action cost ledger. Revisit consolidation in a later cleanup.
- **`KycGateChecker` + `PrerequisiteGateService`** today emit `prompt_text` (a string block). Phase 5 (per FR-M5 in Phase 3) resolves these into typed working-memory fields. Confirm the migration path.
- **Mobile (iOS) SSE rendering** of `thinking`, `turn_queued`, `pending_resumption_surfaced` events needs explicit verification per MEMORY.md `mobile_capacitor_patterns.md`. WKWebView occasionally drops SSE events.
- **Frontend Vuex store** changes for `ai_messages.status` transitions. Specifically `resources/js/store/modules/ai*.js` (confirm exact module name in implementation). Cancellation endpoint, status updates, queue depth indicator.
- **CSJ stated directive:** "everything goes through the loop EXCEPT the deterministic bypasses we explicitly keep, and the safety gate is mechanical not promised." Phase 5 must visibly demonstrate this in the PR review.

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| 27 May 2026 | Initial draft from CoALA v0.4 plan Phase 5 | prd-writer skill |
