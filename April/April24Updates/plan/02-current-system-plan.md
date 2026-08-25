# Fyn v2 — Canonical Two-Fyn Contract

> **BRANCH: `feature/fyn-persona-split`.** All implementation builds on this branch.

This statement is the source of truth for every doc, spec, plan, PRD, and task list in this workstream. It appears verbatim at the top of every artefact.

---

**FYN HAS TWO STATES.**

**ONBOARDING FYN** takes a user through the onboarding flow using bubbles for the user to choose the path, and guides them through the flow they choose. It accepts multi-line information and **SAVES AND WRITES** it to the database so user information is persisted. It has memory: any additional information already entered is not asked about again, but is resurfaced to the user at the right time to give a view of intelligence. If a user leaves at any point in the conversation, the next time they log in Onboarding Fyn picks up from where they left off (example only, not the whole scope: *"Good afternoon CSJ — last time we were busy entering your family details, you told me about X. Do you want to continue from where we left off?"* Yes / No bubble). Journeys are mapped according to what the user wants and where they enter onboarding from. Onboarding Fyn also receives handovers from Advice Fyn for any outstanding information needed to produce guidance. **Onboarding Fyn is the ONLY state that enters or edits information.**

**ADVICE FYN** takes a user request, fetches the user's information, and answers that request using the recommendation engine, the risk module, and every other module or system in the app as needed. Examples only, not the whole scope:

- *"Where's my invoice?"* → Advice Fyn checks subscription status and navigates to the subscription page, confirming the subscription.
- *"Should I contribute more to my ISA?"* → Advice Fyn uses the recommendation engine to surface the guidance the engine produces and navigates to the portfolio page.

Advice Fyn covers tax optimisation (income tax, asset splitting between spouses, etc.), and all other guidance across every module as per the financial planning remit, classification system, recommendation engine, and all the investment, retirement, protection, estate engines and modules. **The ONLY thing Advice Fyn does NOT do is enter or edit information** — that is Onboarding Fyn's job.

**THE USER NEVER SEES THE HANDOFF, OR FEELS THE SWITCH**, between the two states.

---

## What this means for code

- One dispatch decision in `AiChatController::sendMessage`: onboarding or advice, based on `users.onboarding_completed`.
- Onboarding Fyn = the existing `OnboardingChatDirector` (promoted) with a new `handleInlineCapture` entry point for post-onboarding captures.
- Advice Fyn = a new `AdviceFyn` class wrapping the advice-side prompt + chat loop + read-only tool list.
- No `FynPersonaOrchestrator`, no `FynPersonaInvoker`, no `FynPersonaRegistry`, no `DataCapturePromptBuilder`.
- `HandoffContract` constants and `CaptureContext` VO are kept.
- Zero SSE events visible to the frontend that distinguish the two states. No `persona_state_change` event. No capturing pill. Input placeholder invariant.

## What this means for the user

- Onboarding feels like a friendly guided flow with clickable choices and open-text questions.
- Advice feels like a conversational assistant that knows their situation, answers with real data + engine-generated guidance, and navigates them to the right module page.
- When Advice Fyn needs more information to answer something, the request for that information arrives as a natural continuation of the conversation — no "switching to capture mode" preamble, no sudden bubbles.
- Resuming on a new device / session / after a disconnect picks up exactly where the user left off.

## What this means for evaluation

- `01-invariants.md` breaks this contract into ~35 falsifiable invariants. Each invariant has a specific test.
- `fyn-rubrics.md §B` contains 75 golden conversations that exercise the contract end-to-end.
- Scenario category `09-canonical-behaviour` (10 scenarios) is the core canonical-contract test set. Any regression in that category blocks merge.

---

*Source of truth. Do not paraphrase when copying into other docs — paste verbatim.*

---

# Plan — `02-current-system.md` (Branch state / "what is true today")

> **Canonical contract:** [`../spec/00-canonical.md`](../spec/00-canonical.md).
> **Branch:** all implementation commits on `feature/fyn-persona-split`. The branch is 68 commits ahead of / 179 behind `origin/main` at spec time (Sprint 0 Task 0.1 rebases first).
> **Sources:**
> - Source spec: [`../spec/02-current-system.md`](../spec/02-current-system.md)
> - Audit evidence: [`../audit-evidence.md`](../audit-evidence.md)
> - Audit synthesis: [`../audit-synthesis.md`](../audit-synthesis.md)
> - Rubrics: [`../fyn-rubrics.md`](../fyn-rubrics.md)

`02-current-system.md` is the descriptive "what is true" side of the spec. It is not itself a task list — every invariant and sprint plan builds on its facts. This plan captures the obligations that follow: keep the document accurate as code changes, hold on to the things that should be preserved, and remove the things that must go. Each slice names one area of the current system, what part is kept vs deleted, and the acceptance check that proves the state after Sprint 0.

---

### CUR-01 — Two code paths today → collapse to two Fyns

- **Objective:** Collapse the current three-persona architecture (Onboarding path + Persona-split path + Legacy `CoordinatingAgent::chat` path) into exactly two dispatch targets: `OnboardingChatDirector::handleUserMessage` and `AdviceFyn::handle`, keeping the director intact and dissolving the persona-split machinery.
- **Spec reference:** `spec/02-current-system.md §1` lines 9-17 (current three-path reality) vs canonical §0 (two-Fyn target); enforced by INV-2.1.1; executed by `spec/10-sprint-0-plan.md` Task 0.3.
- **Files affected:**
  - KEEP: `app/Services/Onboarding/OnboardingChatDirector.php:1-1985` + state machine (`OnboardingStateMachine.php:1-713`) + value interpreter (`OnboardingValueInterpreter.php:1-324`) + fact extractor (`OnboardingFactExtractor.php:1-286`) + asset-capture extractor (`AssetCaptureEntityExtractor.php:1-665`) + spouse linking (`SpouseLinkingService.php:1-367`) + household provisioner (`HouseholdProvisioner.php:1-61`).
  - DELETE (Sprint 0 Task 0.3 Step 10): `app/Services/AI/FynPersonaOrchestrator.php:1-415`, `FynPersonaInvoker.php:1-518`, `FynPersonaRegistry.php:1-104`, `Prompts/DataCapturePromptBuilder.php:1-110`, `config/fyn_personas.php`.
  - KEEP as constants/VO only: `app/Services/AI/HandoffContract.php`, `app/ValueObjects/CaptureContext.php`, `app/Services/AI/Prompts/EmptyDataGuard.php`.
  - CREATE: `app/Services/AI/AdviceFyn.php` (Sprint 0 Task 0.3 Step 6), `app/Services/AI/HandoffPayloadValidator.php` (Step 7).
  - MODIFY: `app/Http/Controllers/Api/AiChatController.php` (Step 9); `app/Providers/AppServiceProvider.php` (Step 11).
  - MIGRATION: `database/migrations/2026_04_25_000001_clear_stale_persona_state.php` (Step 12) sets `ai_conversations.persona_state = null`.
- **Acceptance test:** `tests/Architecture/PersonaMachineryAbsentTest.php` green — grep across `app/`, `config/`, `tests/` finds zero references to `FynPersonaOrchestrator|FynPersonaInvoker|FynPersonaRegistry|DataCapturePromptBuilder`. `tests/Feature/Fyn/DispatchRoutingTest.php` asserts `sendMessage` body contains exactly one `OnboardingChatDirector` and one `AdviceFyn` reference.
- **Out of scope:** Changing onboarding state logic beyond adding `handleInlineCapture`. Touching the legacy `CoordinatingAgent::chat` entrypoint — it remains the provider-agnostic chat loop; `AdviceFyn::handle` delegates to `chatWithPromptOverride`.

---

### CUR-02 — Dead OpenAI config + Python sidecar removal

- **Objective:** Remove the stale OpenAI config block, the unused Python agent sidecar, the internal-agent controller, and the `agent.token` middleware — none of which have callers per CSJ decision 4 in `../audit-synthesis.md §8`.
- **Spec reference:** `spec/02-current-system.md` implied by the "deleted in Sprint 0" list + `spec/10-sprint-0-plan.md` Task 0.2.
- **Files affected:**
  - MODIFY: `config/services.php` — remove lines 34-38 (OpenAI block).
  - DELETE: `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`, `app/Http/Controllers/Api/AgentInternalController.php`, `app/Http/Middleware/AgentTokenAuth.php`.
  - MODIFY: `routes/api.php` — remove `/api/internal/agent/*` block at lines 1193-1199.
  - MODIFY: `app/Http/Kernel.php` — remove `agent.token` middleware at line 81.
  - MODIFY: `.env.example` — remove `AGENT_INTERNAL_TOKEN`, `OPENAI_*`.
- **Acceptance test:** `tests/Architecture/NoStaleReferencesTest.php` (Sprint 0 Task 0.2 Step 7) green — grep over `app/`, `config/`, `routes/` finds no occurrence of `AgentInternalController|AgentTokenAuth|AGENT_INTERNAL_TOKEN|OPENAI_CHAT_MODEL`. `./vendor/bin/pest` green post-deletion.
- **Out of scope:** Touching the actual Anthropic + xAI chat path. Removing any other config entries (every other env var remains).

---

### CUR-03 — Provider tool-catalogue asymmetry

- **Objective:** Bring xAI's 33-tool catalogue to parity with Anthropic's 37-tool catalogue plus the 3 new billing tools, landing both at 40 strict tools; add 14 batch-capture tools in Sprint 2 for a final 54/54.
- **Spec reference:** `spec/02-current-system.md §3.1` (counts); enforced by INV-2.7.1, INV-2.7.2, INV-2.7.4; executed by Sprint 0 Tasks 0.6, 0.7 and Sprint 2 Tasks 2.1-2.14.
- **Files affected:**
  - `app/Services/AI/AiToolDefinitions.php::getTools` — 37 tools today; add `billingTools()` method (Sprint 0 Task 0.6 Step 2); replace `update_record` with `oneOf` schema (Sprint 0 Task 0.7 Step 4); add 14 batch tools (Sprint 2).
  - `app/Services/AI/XaiToolDefinitions.php::getTools` — 33 tools today; add the 4 missing (`capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `create_holding`), then the 3 billing tools, then 14 batch tools (Sprint 2); every xAI tool wrapped with `strict: true` where Anthropic's schema uses `additionalProperties: false`.
  - `app/Agents/CoordinatingAgent.php::executeTool` — new handlers `handleGetSubscriptionStatus`, `handleListInvoices`, `handleGetCurrentPlan` (Sprint 0 Task 0.6 Step 4); each batch tool handler (Sprint 2).
- **Acceptance test:** `tests/Architecture/ToolCatalogueParityTest.php` green — sorted tool-name arrays equal between providers (40 after Sprint 0, 54 after Sprint 2). `tests/Feature/AI/BillingToolsTest.php` + per-batch-tool tests green on both providers. Preview parity: `tests/Architecture/PreviewModeToolCatalogueTest.php` (INV-2.7.4) asserts `getTools(true)` sets are identical and contain zero write tools.
- **Out of scope:** Removing tools from either provider. Changing existing tool-schema semantics beyond `update_record` (which gains a per-entity allowlist).

---

### CUR-04 — 17 `fill_form` handlers → direct-write

- **Objective:** Convert 16 of the 17 current `fill_form`-returning handlers in `CoordinatingAgent.php` to direct-write handlers that persist inside a `DB::transaction` via the matching FormRequest + model; `create_what_if_scenario` remains the single `fill_form` analytics exception per INV-2.5.6.
- **Spec reference:** `spec/02-current-system.md §3.2` (17 sites listed with line numbers) + INV-2.5.1, INV-2.5.2, INV-2.5.5, INV-2.5.6; executed by Sprint 0 Task 0.5 sub-tasks 0.5.a-0.5.p + 0.5.q.
- **Files affected:**
  - `app/Agents/CoordinatingAgent.php` — rewrite handlers at lines: 1510 (`handleCreateGoal`), 1549 (`handleCreateLifeEvent`), 1595 (`handleCreateSavingsAccount`; task table also cites ~1557), 1742 (`handleCreateInvestmentAccount`; 1614), 1809 (`handleCreateHolding`; 1750), 1887 (`handleCreatePension`; 1817), 2018 (`handleCreateProperty`; 1895), 2065 (`handleCreateMortgage`; 2026), 2132 (`handleCreateProtectionPolicy`; 2073), 2165 (`handleCreateEstateAsset`; 2140), 2205 (`handleCreateEstateLiability`; 2173), 2244 (`handleCreateEstateGift`; 2213), 2861 (`handleCreateFamilyMember`; 2770), 2923 (`handleCreateTrust`; 2869), 2978 (`handleCreateBusinessInterest`; 2931), 3021 (`handleCreateChattel`; 2986), 3142 (`handleUpdateRecord` fill_form path).
  - Handlers use existing FormRequests — `StoreSavingsAccountRequest`, `StoreInvestmentAccountRequest`, `StoreHoldingRequest`, `StoreDCPensionRequest`/`StoreDBPensionRequest`, `StorePropertyRequest`, `StoreMortgageRequest`, per-type protection requests (via `PolicyCRUDTrait`), `StoreEstateAssetRequest`, `StoreEstateLiabilityRequest`, `StoreEstateGiftRequest`, `StoreFamilyMemberRequest`, `StoreTrustRequest`, `StoreBusinessInterestRequest`, `StoreChattelRequest`, `StoreGoalRequest`, `StoreLifeEventRequest`.
  - Observers fire within the transaction (keep): `UserRiskObserver`, `InvestmentAccountRiskObserver`, `PropertyRiskObserver`, `SavingsAccountGoalObserver`, `InvestmentAccountGoalObserver` (via `TracksGoalContributions` trait), `NetWorthCacheObserver`, `RecommendationCacheObserver`, `LifeEventMonteCarloObserver`, `TrustObserver`.
- **Acceptance test:**
  - `tests/Feature/AI/DirectWriteCoverageTest.php` asserts `grep -c "'action' => 'fill_form'" app/Agents/CoordinatingAgent.php === 1` (only `handleCreateWhatIfScenario` retains).
  - Per-handler `tests/Feature/AI/DirectWrite/Create{Entity}Test.php` assert success path + DB row + validation-failure path.
  - `tests/Feature/AI/DirectWriteObserverFireTest.php` asserts expected observer dispatch per handler.
  - `tests/Feature/AI/DirectWriteTransactionRollbackTest.php` asserts mid-write exception leaves zero rows.
  - Browser `BS-14` asserts `create_savings_account` end-to-end with DOM + DB verification.
- **Out of scope:** Changing `create_what_if_scenario` behaviour. Touching the 13 handlers that already write directly (`capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `capture_work_details`, `handleCreateWill`, `handleUpdateWill`, `handleCreatePowerOfAttorney`, `handleUpdatePowerOfAttorney`, `handleUpdateRecord` non-fill_form path, `handleDeleteRecord`, `handleSetExpenditure`, `update_profile`). Modifying observers.

---

### CUR-05 — Recommendation engine stays canonical

- **Objective:** Preserve `CoordinatingAgent::orchestrateAnalysis` at `app/Agents/CoordinatingAgent.php:158-219` + the 8-stage pipeline + `HolisticPlanner::createHolisticPlan` + `RecommendationPersonaliser` as the sole source of truth for interpretive text; `AdviceFyn` invokes it, does not replace it.
- **Spec reference:** `spec/02-current-system.md §4` (pipeline order, return shape, investment deep-dive); enforced by INV-2.3.2, INV-2.3.6; CSJ decision in `spec/README.md` line 61 (*"Existing `orchestrateAnalysis` pipeline — reused, not replaced"*).
- **Files affected (KEEP untouched during Fyn v2):**
  - `app/Agents/CoordinatingAgent.php:158-219` — `orchestrateAnalysis`.
  - `app/Services/Coordination/HolisticPlanner.php` — `createHolisticPlan`.
  - `app/Services/Coordination/RecommendationPersonaliser.php`.
  - `app/Services/Coordination/CashFlowCoordinator.php`, `ConflictResolver.php`, `PriorityRanker.php`, `CrossModuleStrategyService.php`.
  - Per-module agents: `ProtectionAgent`, `SavingsAgent`, `InvestmentAgent`, `RetirementAgent`, `EstateAgent`, `GoalsAgent`, `TaxOptimisationAgent`.
  - Investment waterfall services: `UserContextBuilder`, `DataReadinessService`, `SafetyCheckService`, `ContributionWaterfallService`, `LifeEventAssessmentService`, `GoalAssessmentService`, `ConflictResolutionService`, `TransferRecommendationService`, `SpouseOptimisationService`, `RecommendationOutputFormatter`.
  - `App\Models\RecommendationTracking` + `HolisticPlanningController::plan` persistence.
  - Per-module `ActionDefinition` models/services (Estate, Investment, Protection, Retirement, Savings, Tax).
- **Acceptance test:**
  - `AssertionHelpers::assertInterpretiveTextMapsToEngineSource` in Rubric B Mode-2 runs — 100% of interpretive sentences in `07-regulatory/*` scenarios trace to an engine field.
  - `tests/Feature/AI/GetRecommendationsCompletenessTest.php` (INV-2.6.2) asserts handler returns full `ranked_recommendations` with `priority_score`, `timeline`, `category`, `impact`, `recommendation_text`, `rationale`, `personalised_context`.
  - `tests/Unit/Services/AI/AdviceFynEngineCallLevelTest.php` asserts holistic queries call `orchestrateAnalysis`, module queries call single-agent `analyze`, factual queries bypass the engine.
- **Out of scope:** Any edit to the pipeline order, agent `analyze` return shapes, or `ActionDefinition` rows. Replacing `PriorityRanker` weights (`urgency × 0.4 + impact × 0.3 + ease × 0.2 + user_priority × 0.1`).

---

### CUR-06 — Memory surfaces gap: add the index

- **Objective:** Add the fourth memory layer (`ai_conversations` index columns + summariser job) so `MemoryRetrieverService` has a fallback when the three existing stores (authoritative DB, parked facts, current message history) cannot answer a query.
- **Spec reference:** `spec/02-current-system.md §5` (three stores exist, index absent); INV-2.11.1, INV-2.11.2, INV-2.11.3; executed by Sprint 1 Tasks 1.3, 1.4, 1.5.
- **Files affected:**
  - `ai_conversations` table — existing columns per `spec/02-current-system.md §5`: `id, user_id, title, status, model_used, metadata, persona_state, onboarding_parked_facts, message_count, last_message_at, timestamps, soft-delete`. Migration `database/migrations/2026_04_22_000003_add_onboarding_parked_facts_to_ai_conversations.php` already on branch.
  - NEW migration `database/migrations/2026_05_02_000001_add_conversation_index_columns.php` — adds `summary TEXT`, `topics JSON`, `entities_mentioned JSON`, `intents_stated JSON`, `summarised_at TIMESTAMP` with an index.
  - `app/Models/AiConversation.php` — update fillable + casts.
  - `app/Jobs/ConversationSummariserJob.php` — new.
  - `app/Services/AI/ConversationSummariser.php` — new; cheapest configured model via structured-output prompt.
  - `app/Services/AI/MemoryRetrieverService.php` — new; retrieval fall-through DB → parked → current → index.
  - `app/Services/Onboarding/OnboardingChatDirector.php` — dispatch `ConversationSummariserJob::dispatch($conversation->id)` on `STATE_DONE`.
  - `app/Console/Kernel.php` — schedule summariser scan (`last_message_at > 30 min ago AND (summarised_at IS NULL OR summarised_at < last_message_at)`).
  - `app/Services/AI/AiToolDefinitions.php` + `XaiToolDefinitions.php` — new tool `search_conversation_index`.
  - `app/Agents/CoordinatingAgent.php::executeTool` — new handler `handleSearchConversationIndex` (Sprint 1 Task 1.5 Step 2).
- **Acceptance test:**
  - `tests/Feature/AI/ConversationIndexPopulationTest.php` — closed conversation has non-empty `summary`, `topics`, `entities_mentioned`.
  - `tests/Unit/Services/AI/MemoryRetrieverServiceTest.php` — parameterised fall-through; layer N+1 only queried when layer N silent.
  - `tests/Feature/AI/SearchConversationIndexTest.php` — topic + entity_type filtering returns correct conversations.
  - Rubric B scenarios `09-09 index-populated-on-close` and `09-10 cross-conversation-surface`.
- **Out of scope:** Rewriting the three existing stores. Back-filling historical conversations. Expanding `search_conversation_index` into a vector store.

---

### CUR-07 — Visible-handoff leak removal

- **Objective:** Remove the `persona_state_change` SSE emission + its Vuex consumer + the capturing pill + placeholder swap so the user never sees evidence of a Fyn-state switch.
- **Spec reference:** `spec/02-current-system.md §6` (the leak, with exact line numbers) + INV-2.4.1; executed by Sprint 0 Task 0.4.
- **Files affected:**
  - `app/Services/AI/FynPersonaOrchestrator.php:382-388` — `personaStateChangeEvent` emitter; deleted with the whole file (CUR-01).
  - `resources/js/store/modules/aiChat.js:511-516` — `case 'persona_state_change':` block; delete.
  - `resources/js/store/modules/aiChat.js` — `personaMode` state, getter, mutation (`SET_PERSONA_MODE`); delete.
  - `resources/js/components/Shared/AiChatPanel.vue` — `<div v-if="personaMode === 'capturing'">` pill block; delete. Conditional placeholder `:placeholder="personaMode === 'capturing' ? 'Capturing...' : 'How can I help?'"` → constant `placeholder="How can I help?"`.
- **Acceptance test:** `tests/Feature/Fyn/HandoffInvisibilityTest.php` asserts zero `persona_state_change` events in the SSE stream across an advice → inline-capture → advice turn. Browser `BS-11` asserts DOM snapshot before/after has identical input placeholder + no pill element. `./dev.sh` incognito smoke confirms the same manually (Sprint 0 Task 0.4 Step 4).
- **Out of scope:** Removing `capture_complete` emission (kept; styling must match regular bubbles per INV-2.4.3). Removing `quick_replies` entirely (kept for onboarding; only suppressed during inline-capture sub-turn per INV-2.4.2).

---

### CUR-08 — Reliability bundle

- **Objective:** Close the six reliability gaps listed in `spec/02-current-system.md §7`: add SSE abort detection, fix the token-budget race, version-lock provider during a chat loop, DB-dedup gap-fill, sanitise `generateTitle`, and equalise Anthropic/xAI timeouts.
- **Spec reference:** `spec/02-current-system.md §7` + INV-2.9.1, INV-2.9.2, INV-2.9.3, INV-2.9.4, INV-2.9.5, INV-2.9.6; executed by Sprint 0 Task 0.11 and Sprint 4 Task 4.2.
- **Files affected:**
  - `app/Traits/HasAiGuardrails.php:221` — replace `Cache::remember($cacheKey, 300, …)` with `DB::transaction` + `SELECT ... FOR UPDATE` against new `ai_daily_usage` table.
  - `app/Traits/HasAiGuardrails.php` — new `getAiProviderForLoop()` that captures the provider once per chat call with a versioned cache key.
  - `app/Traits/HasAiChat.php` — SSE abort poll via `connection_aborted()`; writes kept; insert `ai_abort_events` row (INV-2.9.2 per CSJ decision 24 April).
  - `app/Traits/HasAiChat.php:704` — `generateTitle` → `strip_tags` + `mb_substr(..., 0, 100)`.
  - `app/Traits/HasAiChat.php:749` — `summariseToolResult` preserves `entity_id` + `entity_type`.
  - `app/Traits/HasAiChat.php:287-305` — pass explicit 120s timeout on Anthropic call (Sprint 4 Task 4.2 parity with `XaiClient.php:64`).
  - `app/Http/Middleware/IdempotencyKeyMiddleware.php` — new (INV-2.9.3); attached to `POST /api/ai-chat/conversations/{id}/messages` in `routes/api.php`.
  - `app/Http/Controllers/Api/AdminController.php` — versioned `ai_provider` cache key (`Cache::forever('ai_provider:v{n}', ...)`); reader uses the captured version (INV-2.9.4).
  - `app/Services/Onboarding/AssetCaptureEntityExtractor.php::findMissing` — DB lookup `(user_id, entity_fingerprint, created_at > now() - 24h)` before emission (INV-2.9.5).
  - New migrations: `create_ai_daily_usage_table`, `create_ai_request_idempotency_table`, `create_ai_abort_events_table` (timestamps 2026_04_25_00000{3,4,5}).
  - New models: `App\Models\AiDailyUsage`, `AiRequestIdempotency`, `AiAbortEvent`.
  - New job: `app/Jobs/AiIdempotencyCleanupJob.php` scheduled daily in `app/Console/Kernel.php`.
- **Acceptance test:**
  - `tests/Feature/AI/TokenBudgetConcurrencyTest.php` — two parallel requests at budget boundary; second returns `token_limit` without double-consuming.
  - `tests/Feature/AI/SseAbortKeepWritesTest.php` — induced abort mid-create leaves record + `ai_abort_events` row.
  - `tests/Feature/AI/IdempotencyKeyTest.php` — duplicate `Idempotency-Key` within 24h returns cached response.
  - `tests/Feature/AI/ProviderSwapLockTest.php` — admin toggle mid-loop does not leak Anthropic cache markers into xAI request.
  - `tests/Feature/AI/GapFillDedupTest.php` — retry of same user message emits zero gap-fill events.
  - `tests/Unit/Traits/GenerateTitleSanitisationTest.php` — `<script>`, `">`, long inputs all stripped; ≤ 100 chars.
  - Browser `BS-18`, `BS-19`, `BS-20`.
- **Out of scope:** Switching the chat transport away from SSE. Introducing streaming-WS. Moving token-budget tracking out of Laravel into a shared cache layer.

---

### CUR-09 — Audit trail replacement (hash chain)

- **Objective:** Replace the file-only `[AI-AUDIT]` log + partial DB coverage with a single hash-chained, HMAC-signed `ai_audit_events` table covering every tool dispatch/persist/fail/strip event.
- **Spec reference:** `spec/02-current-system.md §8` (audit gaps) + INV-2.5.4, INV-2.10.2; executed by Sprint 0 Task 0.12.
- **Files affected:**
  - `app/Agents/CoordinatingAgent.php:770` — remove `Log::channel('single')->info('[AI-AUDIT] Tool executed', …)`; gating condition at 768 disappears.
  - NEW migration: `database/migrations/2026_04_25_000006_create_ai_audit_events_table.php` per schema in `spec/01-invariants.md` INV-2.10.2.
  - NEW model: `app/Models/AiAuditEvent.php`.
  - NEW service: `app/Services/AI/AuditChainService.php` with `append()` + `verifyChain()`.
  - NEW command: `app/Console/Commands/AiAuditVerifyChainCommand.php` — signature `ai:audit:verify-chain`.
  - NEW job: `app/Jobs/AiAuditRetentionJob.php` — 7-year for write/recommendation rows, 2-year otherwise; pseudonymise in separate export view per Task 0.12 Step 5 docblock note.
  - `app/Agents/CoordinatingAgent.php::executeTool` — call `AuditChainService::append` at dispatch, on success, on exception.
  - `resources/js/components/Admin/AiAudit.vue` — add "Chain view" sub-tab.
- **Acceptance test:**
  - `tests/Feature/Audit/HashChainTest.php` — append 100 events, `verifyChain` → `chain_valid: true, tip_hash, row_count: 100`.
  - `tests/Feature/Audit/HmacSigningTest.php` — sign/verify per row.
  - `tests/Feature/Audit/ChainTamperDetectionTest.php` — mutating a row's `input_summary` makes `verifyChain` return `chain_valid: false, broken_at: <id>`.
  - `tests/Feature/Audit/RetentionPseudonymisationTest.php` — pseudonymisation path preserves chain verification.
  - `php artisan ai:audit:verify-chain` returns `{chain_valid: true, tip_hash: ..., row_count: N}` in production post-deploy.
  - Browser `BS-15` admin chain-view matches artisan output.
- **Out of scope:** Changing `ai_messages` or `ai_advice_logs` schemas (kept; the new table supplements them). Moving audit to a separate service. Encrypting row contents (pseudonymisation is only required for retention).

---

### CUR-10 — Compliance floor (CoreIdentity, consent, update_record, privacy policy deferred)

- **Objective:** Close the four compliance regressions named in `spec/02-current-system.md §9`: rewrite `CoreIdentity` to guidance-only framing, enforce `ConsentService::hasConsent('ai_chat')` at chat entry, tighten `update_record` with per-entity allowlist + strict schema, and defer Privacy Policy rewrite to Sprint 4.
- **Spec reference:** `spec/02-current-system.md §9` + INV-2.10.1, INV-2.10.3, INV-2.7.3, INV-2.10.4; Sprint 0 Tasks 0.7, 0.9, 0.10, 0.13 + Sprint 4 Track A.5.
- **Files affected:**
  - `app/Services/AI/Prompts/CoreIdentity.php` — remove *"qualified financial planner"* language; use the guidance-only block in Sprint 0 Task 0.13 Step 2.
  - `app/Http/Controllers/Api/AiChatController.php::sendMessage` + `startOnboarding` — call `ConsentService::hasConsent($user, 'ai_chat')`; 403 JSON on false (Task 0.9 Step 4).
  - `app/Services/GDPR/ConsentService.php` — add `TYPE_AI_CHAT` constant if absent.
  - NEW migration: `database/migrations/2026_04_25_000002_add_ai_chat_consent_types.php` widens `user_consents.type` enum.
  - `resources/js/store/modules/aiChat.js` — new `case 'consent_required':` handler dispatches consent-modal open.
  - NEW: `app/Constants/UpdateRecordAllowlist.php` with per-entity allowed fields (Task 0.7 Step 1).
  - `app/Agents/CoordinatingAgent.php::handleUpdateRecord` at ~3134 — replace 2-field blocklist (`user_id`, `id`) with allowlist guard (Task 0.7 Step 3).
  - `app/Services/AI/AiToolDefinitions.php` — replace `update_record.fields` with `oneOf` per entity type, `additionalProperties: false` (Task 0.7 Step 4).
  - `app/Services/AI/XaiToolDefinitions.php` — wrap with `strict: true`.
  - NEW: `app/Services/AI/Prompts/UserContentSanitiser.php` — strip `[A-Za-z0-9\s'.,\-]` + `<user_provided>` wrap (Task 0.10 Step 1).
  - `app/Services/AI/AdvicePromptBuilder.php`, `app/Services/Onboarding/OnboardingPromptBuilder.php` — wrap every user-controlled interpolation via `UserContentSanitiser::wrap` (Task 0.10 Step 3).
  - `resources/js/views/Public/PrivacyPolicyPage.vue:111,124,132` — **Sprint 4 Track A.5 only**; either disclose Meta Pixel / AWIN / Plausible / Anthropic (chat) / xAI (chat), or remove the trackers.
- **Acceptance test:**
  - `tests/Architecture/CoreIdentityFramingTest.php` — file contents do not contain banned phrases.
  - `tests/Feature/AI/ConsentRuntimeCheckTest.php` — 403 on missing consent; SSE `consent_required` on mid-stream withdrawal.
  - `tests/Unit/Constants/UpdateRecordAllowlistTest.php` — `Trust.settlor`, `Mortgage.start_date`, `FamilyMember.relationship` forbidden.
  - `tests/Feature/AI/UpdateRecordSecurityTest.php` — attempt forbidden → `fields_not_allowed`; allowed succeeds.
  - `tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php` + 10 Rubric B `06-prompt-injection` scenarios.
  - Browser `BS-21`, `BS-22`, `BS-23`.
- **Out of scope:** Privacy Policy edits before Sprint 4 (calendar-bound by external legal — see Task 4 Track A.5). Changing `ConsentService` storage model. Widening `update_record` to expose more fields (allowlist is strictly narrowing).

---

### CUR-11 — Frontend SSE surface drift

- **Objective:** Take the 19-entry SSE event catalogue listed in `spec/02-current-system.md §10` down to 18 (remove `persona_state_change`) for Sprint 0, then back up to 20 after Sprint 1 adds `advice_response` + `consent_required`, holding stable through Sprint 2+.
- **Spec reference:** `spec/02-current-system.md §10` + INV-2.3.5 (`advice_response`), INV-2.4.1 (no `persona_state_change`), INV-2.10.3 (`consent_required`); Sprint 0 Task 0.4 + Sprint 1 Task 1.6 + Sprint 0 Task 0.9.
- **Files affected:**
  - `resources/js/store/modules/aiChat.js` — handlers for: `content, title, navigation, fill_form, entity_created, quick_replies, onboarding_advance, onboarding_layout_change, capture_complete, handoff, skip_link, preview_cta, onboarding_complete, token_limit, error, done, conversation_created, resume`. REMOVE `persona_state_change` (Sprint 0 Task 0.4 Step 1). ADD `advice_response` (Sprint 1 Task 1.6 Step 4). ADD `consent_required` (Sprint 0 Task 0.9 Step 5).
  - `resources/js/components/Shared/AiChatPanel.vue` — render branch for `msg.role === 'advice_response'` (Sprint 1 Task 1.6 Step 5).
  - `resources/js/components/Shared/AdviceResponsePanel.vue` — new (Sprint 1).
- **Acceptance test:** `tests/Feature/Fyn/AdviceResponseSseShapeTest.php` validates JSON shape. Browser `BS-09` asserts `AdviceResponsePanel` renders. Browser `BS-11` asserts no `persona_state_change`. Manual DevTools inspection post-deploy confirms 20-event catalogue.
- **Out of scope:** Adding any additional SSE event types not named in the spec. Changing the semantics of any kept event. Moving to typed-events schema (YAGNI for Fyn v2).

---

### CUR-12 — Rubric-A baseline → trajectory

- **Objective:** Hold the baseline 4-5/40 🔴 Pre-launch at spec time as the "from" position and publish per-sprint Rubric-A deltas per `spec/01-invariants.md §verification` (13-15 post-0, 17-18 post-1, ~22 post-2, ~24 post-3, 28-30 post-4).
- **Spec reference:** `spec/02-current-system.md §11` (dimension-by-dimension baseline table) + `spec/01-invariants.md §verification` (per-sprint target); Rubric A itself at `fyn-rubrics.md §A`.
- **Files affected:**
  - `docs/sprint-0-verification/rubric-a-score.md`, `docs/sprint-1-verification/rubric-a-score.md`, …, `docs/sprint-4-verification/rubric-a-score.md` — one per sprint per Task 0.16, 1.9, 2.19, 3.3, 4.7 verification sections.
  - No code files.
- **Acceptance test:** Each sprint's `rubric-a-score.md` exists, walks each of D1-D10 per `fyn-rubrics.md §A`, and publishes both the prior and new scores. No sprint merges until its re-score meets the sprint's target (e.g., Sprint 3 requires ≥22/40).
- **Out of scope:** Changing Rubric A itself. Reweighting dimensions. Claiming a score higher than evidence supports.

---

### CUR-13 — Branch-state pre-flight check

- **Objective:** Confirm the expected branch tip + ahead/behind counts + Pest baseline before any Sprint 0 work begins.
- **Spec reference:** `spec/02-current-system.md §12` (branch-state at spec time) + `spec/10-sprint-0-plan.md` Pre-flight.
- **Files affected:** none (read-only check).
- **Acceptance test:**
  - `git rev-parse --abbrev-ref HEAD` → `feature/fyn-persona-split` (or `feature/csj/sprint0-<subtask>` off it).
  - `git fetch origin && git rev-list --count origin/feature/fyn-persona-split..origin/main` → 179 (or the current drift if time has moved on).
  - `git status` → clean working tree.
  - `./vendor/bin/pest` → 2,448 passing + 1 known flake (`AutoRiskCalculatorTest`) per `CSJTODO`.
- **Out of scope:** Resolving the known `AutoRiskCalculatorTest` flake (separate work per CSJTODO). Rebasing before Sprint 0 Task 0.1 (Task 0.1 is the rebase).

---

*End of plan for `02-current-system.md`. Every subsequent sprint plan consumes this document's facts — if any of them drift during implementation, update `02-current-system.md` before continuing.*
