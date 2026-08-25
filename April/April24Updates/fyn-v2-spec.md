# Fyn v2 Spec — What is true and what must become true

> **For agentic workers:** This document is the spec AND a Sprint 0 + Sprint 1 task plan. Sprint 2–4 are scoped at the backlog level and get their own plans later. Each invariant carries a falsifiability test + acceptance criterion. Each Sprint 0 / Sprint 1 task is TDD-structured with complete code + commands. Execute via `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans`.

**Goal:** Collapse three-persona code into the canonical two-Fyn architecture, convert every fill_form handler into a direct-write service call, close the reliability / compliance / audit gaps in the three audit docs, and ship a scenario-gated eval harness that blocks regressions. End state: Rubric-A 17-18/40 🟠 Limited beta, ready for `csjones.co/fynla` deploy.

**Architecture:** Two Fyns — Onboarding Fyn (state machine, bubble-driven, direct-writes, memory-aware, resume-aware) and Advice Fyn (conversational, read-only, recommendation-engine-backed, emits structured `advice_response` SSE). Invisible handoff. Memory = 3 DB-backed stores + 1 conversation index. Engine pipeline is the existing `CoordinatingAgent::orchestrateAnalysis` + `HolisticPlanner` + 7 module agents + 10 Investment/Recommendation/* services — reused, not replaced.

**Tech Stack:** Laravel 10 + PHP 8.2 + Pest 3 + Vue 3 + Vuex + Tailwind + Playwright. LLM providers: Anthropic (`claude-haiku-4-5-20251001`) + xAI (`grok-4-1-fast-reasoning`) with runtime toggle. Stream transport: SSE. Branch: `feature/fyn-persona-split` (68 ahead / 179 behind `main` at plan time).

---

## §0 — Canonical two-Fyn contract (source of truth)

**FYN HAS TWO STATES.**

**ONBOARDING FYN** takes a user through the onboarding flow using bubbles for the user to choose the path, and guides them through the flow they choose. It accepts multi-line information and **SAVES AND WRITES** it to the database so user information is persisted. It has memory: any additional information already entered is not asked about again, but is resurfaced to the user at the right time to give a view of intelligence. If a user leaves at any point in the conversation, the next time they log in Onboarding Fyn picks up from where they left off (example only, not the whole scope: *"Good afternoon CSJ — last time we were busy entering your family details, you told me about X. Do you want to continue from where we left off?"* Yes / No bubble). Journeys are mapped according to what the user wants and where they enter onboarding from. Onboarding Fyn also receives handovers from Advice Fyn for any outstanding information needed to produce guidance. **Onboarding Fyn is the ONLY state that enters or edits information.**

**ADVICE FYN** takes a user request, fetches the user's information, and answers that request using the recommendation engine, the risk module, and every other module or system in the app as needed. Examples only, not the whole scope:
- *"Where's my invoice?"* → Advice Fyn checks subscription status and navigates to the subscription page, confirming the subscription.
- *"Should I contribute more to my ISA?"* → Advice Fyn uses the recommendation engine to surface the guidance the engine produces and navigates to the portfolio page.

Advice Fyn covers tax optimisation (income tax, asset splitting between spouses, etc.), and all other guidance across every module as per the financial planning remit, classification system, recommendation engine, and all the investment, retirement, protection, estate engines and modules. **The ONLY thing Advice Fyn does NOT do is enter or edit information** — that is Onboarding Fyn's job.

**THE USER NEVER SEES THE HANDOFF, OR FEELS THE SWITCH**, between the two states.

---

## §1 — Current system (grounded in code)

Condensed view of what's actually on `feature/fyn-persona-split` today. Full anchors in `audit-evidence.md`.

### 1.1 Two code paths (not two Fyns)
- `OnboardingChatDirector` (`app/Services/Onboarding/OnboardingChatDirector.php:1-1985`) — state-machine driven, bubble-emitting, direct-writes user/family state via services. Active when `users.onboarding_completed = false`.
- `FynPersonaOrchestrator` (`app/Services/AI/FynPersonaOrchestrator.php:1-415`) + `FynPersonaInvoker` (`app/Services/AI/FynPersonaInvoker.php:1-518`) + `FynPersonaRegistry` (`app/Services/AI/FynPersonaRegistry.php:1-104`) + `DataCapturePromptBuilder` (`app/Services/AI/Prompts/DataCapturePromptBuilder.php:1-110`) — three-persona post-onboarding machinery. Behind feature flag `FYN_PERSONA_SPLIT` (default off).
- Default: `CoordinatingAgent::chat` (via `HasAiChat` trait) — single-prompt legacy flow.

### 1.2 Tool surface
- Anthropic (`app/Services/AI/AiToolDefinitions.php::getTools`): 37 tools.
- xAI (`app/Services/AI/XaiToolDefinitions.php::getTools`): 33 tools.
- xAI is missing `capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `create_holding` — runtime provider flip silently loses 4 tools.
- 17 handlers return `['action' => 'fill_form', ...]` (`app/Agents/CoordinatingAgent.php:1510, 1549, 1595, 1742, 1809, 1887, 2018, 2065, 2132, 2165, 2205, 2244, 2861, 2923, 2978, 3021, 3142`) — pre-fill forms rather than writing directly.
- 13 handlers write directly today (`capture_*`, `update_record`, `delete_record`, `update_profile`, `set_expenditure`, `create_will`, `update_will`, `create_power_of_attorney`, `update_power_of_attorney`).

### 1.3 Recommendation engine (the real one)
- Entry: `CoordinatingAgent::orchestrateAnalysis(int $userId)` (`app/Agents/CoordinatingAgent.php:158-219`).
- Pipeline: 7 module agents' `analyze()` → `CashFlowCoordinator::calculateAvailableSurplus` → `extractRecommendations` → `ConflictResolver::identifyConflicts` → `resolveConflicts` → `PriorityRanker::rankRecommendations` (urgency×0.4 + impact×0.3 + ease×0.2 + user_priority×0.1) → `optimizeContributionAllocation` → `CrossModuleStrategyService::generateCrossModuleStrategies`.
- Return: `{user_id, analysis_date, module_analysis, available_surplus, conflicts, ranked_recommendations, cashflow_allocation, shortfall_analysis, cross_module_strategies, summary}`.
- `HolisticPlanner::createHolisticPlan` (`app/Services/Coordination/HolisticPlanner.php`) adds `executive_summary`, `net_worth_projection` (baseline vs optimised), `risk_assessment`, `action_plan` (timeline-grouped).
- Investment waterfall: `ContributionWaterfallService` (`app/Services/Investment/Recommendation/ContributionWaterfallService.php`) — 9 steps: LISA → S&S ISA → Pension → Premium Bonds → NS&I → Offshore Bond → Onshore Bond → Carry Forward → VCT/EIS → GIA.
- `RecommendationTracking` model (`app/Models/RecommendationTracking.php`) persists ranked recommendations with `pending → in_progress → completed/dismissed` lifecycle.
- `ActionDefinition` tables per module (`EstateActionDefinition`, `InvestmentActionDefinition`, `ProtectionActionDefinition`, `RetirementActionDefinition`, `SavingsActionDefinition`, `TaxActionDefinition`) hold the recommendation template library.

### 1.4 Memory surfaces (three exist; index absent)
- Authoritative DB state: `users.*` + `family_members` + linked module tables. Exists.
- Current-turn parked facts: `ai_conversations.onboarding_parked_facts` (`database/migrations/2026_04_22_000003_add_onboarding_parked_facts_to_ai_conversations.php`). Exists.
- Current-conversation history: `ai_messages` rows via `HasAiChat::buildMessageHistory` (`app/Traits/HasAiChat.php:679`). Exists.
- Conversation index: **absent**. No `summary` / `topics` / `entities_mentioned` / `intents_stated` on `ai_conversations`. No observer, no job, no tool exposes it.

### 1.5 Visible-handoff leak
- `persona_state_change` SSE event emitted from `app/Services/AI/FynPersonaOrchestrator.php::personaStateChangeEvent` (lines 382-388).
- Vuex consumes at `resources/js/store/modules/aiChat.js:511-516`.
- Swaps input placeholder + surfaces capturing pill in `resources/js/components/Shared/AiChatPanel.vue`.
- Directly violates §0 "user never sees the handoff".

### 1.6 Reliability gaps
- No `connection_aborted()` / `ignore_user_abort(true)` anywhere in `app/` — SSE abort undetected.
- Token budget: `Cache::remember($cacheKey, 300, ...)` at `app/Traits/HasAiGuardrails.php:221` — 5-minute TTL race.
- Provider swap: `Cache::forever('ai_provider', ...)` in `app/Http/Controllers/Api/AdminController.php` — mid-turn switch risk.
- Gap-fill: `AssetCaptureEntityExtractor::findMissing` has no DB dedup before synthesising fill-in events.
- `AIExtractionService` (`app/Services/Documents/AIExtractionService.php:1-965`) is synchronous, no wrapping Job, no retry. Text-PDF path has no size cap (15 MB cap at line 31 applies only to scanned PDFs).
- `generateTitle` at `app/Traits/HasAiChat.php:704` sends raw user text to LLM; only `mb_substr` truncation. No `strip_tags`.

### 1.7 Audit trail
- File-only tool-execution log: `Log::channel('single')->info('[AI-AUDIT]')` at `app/Agents/CoordinatingAgent.php:770`. Gated to create/update/delete/profile at line 768.
- DB surfaces: `ai_messages` (system_prompt snapshot, cache metrics at `HasAiChat.php:569-572`) + `ai_advice_logs` (`tools_called` at `HasAiChat.php:612`, `user_data_snapshot`, classification, KYC). Row-mutable, no chain.
- Truthfulness gap: `[AI-AUDIT]` and `tools_called` both fire BEFORE frontend form submit; 17 fill_form handlers can log "tool executed" when no record persisted.

### 1.8 Compliance
- `CoreIdentity.php` contains *"you think like a qualified financial planner"* — misaligned with guidance-only posture.
- `ConsentService::hasConsent` exists at `app/Services/GDPR/ConsentService.php` but is NOT called from chat flow (zero matches in `AiChatController` / `HasAiChat`).
- `update_record` blocklist is 2 fields (`user_id`, `id`) at `app/Agents/CoordinatingAgent.php:3134`; `fields` schema is `additionalProperties: true` / xAI `strict: false`. LLM can mutate `Trust.settlor`, `Mortgage.start_date`, `FamilyMember.relationship`.
- Privacy Policy (`resources/js/views/Public/PrivacyPolicyPage.vue`) §5 / §7 contradict Meta Pixel (unconditional, `app.blade.php:80-89`), AWIN (env-gated), Plausible (config-gated), xAI (undisclosed chat provider).

### 1.9 Rubric-A baseline
**4-5/40 — 🔴 Pre-launch.** D1=1, D2=0, D3=1, D4=0-1 (scoring choice), D5=0, D6=0, D7=0, D8=1, D9=0, D10=1. Full evidence table in `fyn-rubrics.md §A "Current Fyn score"`.

---

## §2 — Target invariants (falsifiable, observable)

Every invariant states a property, a falsifiability test, and an acceptance criterion. A runtime that fails any test does not score above the rubric level it implicates.

### 2.1 Two-Fyn architecture

**INV-2.1.1 Single dispatch path.**
- Property: `app/Http/Controllers/Api/AiChatController.php::sendMessage` routes to exactly one of two code paths — `OnboardingChatDirector::handleUserMessage` when `user.onboarding_completed = false`, `AdviceFyn::handle` otherwise. No `FynPersonaOrchestrator`, no `FynPersonaInvoker`, no `FynPersonaRegistry`. Third branch for system-level early-returns (token limit, consent required, preview short-circuit) is exempt.
- Test: `grep -rn "FynPersonaOrchestrator\|FynPersonaInvoker\|FynPersonaRegistry\|DataCapturePromptBuilder" app/ config/ tests/` → zero matches.
- Accept: files deleted, controller branch count is 2 + system-early-returns, `./vendor/bin/pest` passes.

**INV-2.1.2 AdviceFyn tool list is disjoint from write tools.**
- Property: `AdviceFyn::buildToolList(User $user)` returns a set whose names do not intersect `['create_savings_account','create_investment_account','create_holding','create_pension','create_property','create_mortgage','create_protection_policy','create_asset','create_liability','create_estate_gift','create_chattel','create_business_interest','create_trust','create_family_member','create_will','update_will','create_power_of_attorney','update_power_of_attorney','update_record','delete_record','update_profile','set_expenditure','capture_personal_details','capture_spouse_details','capture_dependants','capture_work_details']`. Allowed: all `list_*`, `get_*`, `navigate_to_page`, `create_what_if_scenario` (analytics artefact), `search_conversation_index` (new).
- Test: `tests/Feature/Fyn/AdviceFynToolListTest.php` — `expect($fyn->buildToolList($user))->toContainNone([...write list...])`.
- Accept: test passes on both providers (`ai_provider` = anthropic AND xai).

### 2.2 Onboarding Fyn

**INV-2.2.1 State machine drives every non-inline onboarding turn.**
- Property: when `user.onboarding_completed = false`, every chat turn goes through `OnboardingChatDirector::handleUserMessage`. The director reads `users.onboarding_fyn_step` + `ai_conversations.onboarding_parked_facts` and emits SSE per the state record (`bubbles` / `grouped_extract` / `free_text` / `delegated` / `terminal`).
- Test: `tests/Feature/Onboarding/StateMachineWalkthroughTest.php` (exists — extend with path_choice → journey_selection → base_personal → base_spouse → base_dependants_detail → base_employment → base_work → base_retirement_date → base_expenditure → asset_capture → add_more → done for the retirement journey).
- Accept: all intermediate states emit the expected SSE event types; `users.onboarding_completed` flips true exactly once at `STATE_DONE`.

**INV-2.2.2 Multi-line grouped-extract turns write direct.**
- Property: a grouped-extract state (e.g. `base_spouse`) receiving "Angela, DOB 12 Jan 1976, email aslater@gmail.com" writes all three fields in one transaction via `SpouseLinkingService::linkOrCreateSpouse` — creating User + FamilyMember + SpousePermission + sending `SpouseAccountCreated` mail. No fill_form returned.
- Test: `tests/Feature/Onboarding/BaseSpouseDirectWriteTest.php` (new) — Playwright-free feature test asserting DB rows + queued mail.
- Accept: `User::where('email','aslater@gmail.com')->exists()` true; `FamilyMember` row exists with correct relationship + DOB; `Mail::assertSent(SpouseAccountCreated::class)`.

**INV-2.2.3 No re-ask: `<known_facts>` block injected.**
- Property: `OnboardingPromptBuilder::buildAssetCapturePrompt` and `OnboardingPromptBuilder::buildGroupedExtractPrompt` inject a `<known_facts>` block listing every populated field from (a) `users.*`, (b) `family_members`, (c) linked module tables for the current focus, (d) `ai_conversations.onboarding_parked_facts`. The block ends with the instruction: *"Do not ask the user for any field above."*
- Test: `tests/Unit/Services/Onboarding/KnownFactsBlockTest.php` (new) — seed a user with every onboarding field populated; assert the block contains every field name; feed the assembled prompt to a mock LLM; assert no re-ask question is emitted.
- Accept: unit assertion passes; scenario `09-03 memory-no-repeat-ask` in Rubric B is green.

**INV-2.2.4 Resume greeting + Yes/No bubble.**
- Property: for a user with `onboarding_completed = false` AND `onboarding_fyn_step != null` AND last `ai_messages.created_at` > 5 minutes ago, `AiChatController::startOnboarding` emits a `quick_replies` SSE whose `prompt_text` contains `OnboardingChatDirector::resumeSummary($stateId)` and two bubbles `{id: 'resume', label: 'Yes, continue'}`, `{id: 'restart', label: 'Start over'}`.
- Existing function: `app/Services/Onboarding/OnboardingChatDirector.php::resumeSummary` (line 394-406) — reuse, don't add a new column.
- Test: `tests/Feature/Onboarding/ResumeAfterDisconnectTest.php` (new) — Playwright scenario per Rubric B `09-02`.
- Accept: Scenario green.

**INV-2.2.5 Journey mapping is config-driven and extensible.**
- Property: entry source → journey selection lives in `config/onboarding.php` under `journey_map`. Lookup in `AiChatController::startOnboarding` when `request->from` is set. Unknown `from` falls through to `STATE_PATH_CHOICE`.
- Initial map: `['budgeting' => 'budgeting', 'goals' => 'goals', 'protection' => 'protection', 'retirement' => 'retirement']`. Future sources plug in without code changes.
- Test: `tests/Feature/Onboarding/EntrySourceJourneyMapTest.php` (new) — assert each mapping picks the correct journey; assert unknown source starts at path_choice.

### 2.3 Advice Fyn

**INV-2.3.1 Two response modes: factual vs recommendation.**
- Property: `AdviceFyn::classifyResponseMode(string $queryType): string` returns `'factual'` for `['net_worth_query', 'list_*', 'tax_factual', 'billing_subscription', 'billing_invoice', 'billing_plan', 'document_reference']` and `'recommendation'` for `['holistic_plan', 'cross_module_*', 'module_recommendation', 'affordability', 'tax_optimisation', 'protection_cover', ...]`. Factual mode bypasses `orchestrateAnalysis`; recommendation mode calls it (holistic / cross-module) OR a single-agent `analyze()` (module-scoped).
- Test: `tests/Unit/Services/AI/AdviceFynResponseModeTest.php` — parameterised over the full 22+3+1 query type list.
- Accept: map is exhaustive across `QuerySchemas` constants; no query type is unmapped.

**INV-2.3.2 Engine is the sole source of interpretive text.**
- Property: every sentence in `advice_response` containing interpretive markers (`suggest|consider|recommend|should|could|worth|would benefit|might want`) must be attributable to a field in the engine's output (`ranked_recommendations[*].recommendation_text` / `.rationale`, `executive_summary.overview`, `personalised_context[*]`, `ActionDefinition.description_template`, etc.).
- Test: Rubric B Mode-2 eval regex check — for each scenario in `07-regulatory/`, extract Advice Fyn's text output; regex every interpretive sentence; assert it substring-matches an engine-output field.
- Accept: scenario pass rate = 100% on hard-fail; Mode-1 mocked runs assert same invariant per-scenario.

**INV-2.3.3 FCA signposting on every recommendation-mode response.**
- Property: every Advice Fyn response whose mode is `recommendation` ends with the exact signposting: *"For regulated advice personal to your circumstances, speak to a qualified financial adviser."*
- Test: `tests/Feature/Fyn/FcaSignpostingTest.php` — parameterised over a sample scenario per recommendation-type query.
- Accept: exact string match at end of response; factual-mode responses do NOT append signposting.

**INV-2.3.4 Out-of-remit responses use canonical refusal.**
- Property: for query classifications outside the IN-remit list, Advice Fyn emits exactly: *"I'm able to help you with your finances. {context} is out of scope."* where `{context}` is the classifier's detected topic. No contact details.
- Test: `tests/Feature/Fyn/OutOfRemitTest.php` — scenarios for medical / legal / emotional / general-knowledge queries.
- Accept: exact string shape; no signposting appended; no tool call emitted.

**INV-2.3.5 Structured `advice_response` SSE event.**
- Property: Advice Fyn emits exactly one `advice_response` SSE event per recommendation-mode turn with payload:
  ```json
  {
    "type": "advice_response",
    "headline": "string",
    "key_figures": [{"label": "string", "value": "string", "unit": "gbp|percent|years|count|none"}],
    "breakdowns": [{"title": "string", "rows": [{"label": "string", "value": "string"}]}],
    "recommendations": [{"id": "string", "text": "string", "priority": "critical|high|medium|low", "timeline": "immediate|short_term|medium_term|long_term", "source": "action_definition|engine|holistic"}],
    "next_steps": [{"label": "string", "route": "string"}],
    "signposting": "string"
  }
  ```
- Rendering: new `resources/js/components/Shared/AdviceResponsePanel.vue` consumes this event.
- Test: `tests/Feature/Fyn/AdviceResponseSseShapeTest.php` — JSON-schema validation against the payload on each scenario run.
- Accept: every recommendation-mode scenario emits exactly one `advice_response`; schema validates; factual-mode scenarios emit `content` + optional `navigation` only.

### 2.4 Handoff invisibility

**INV-2.4.1 Zero `persona_state_change` SSE events reach the frontend.**
- Property: no code path emits `persona_state_change`. The event type is removed from the Vuex handler. No capturing pill, no input-placeholder swap.
- Test: `tests/Feature/Fyn/HandoffInvisibilityTest.php` — scripted advice → capture → advice handoff; assertions:
  - `events.filter(e => e.type === 'persona_state_change').length === 0`
  - `events.filter(e => e.type === 'quick_replies').length === 0` during the capture sub-turn
  - DOM snapshot before / after handoff: no new pill, input placeholder unchanged.
- Accept: Playwright + SSE capture green.

**INV-2.4.2 Inline capture emits conversational prompts only.**
- Property: `OnboardingChatDirector::handleInlineCapture(User $user, AiConversation $conversation, string $message, CaptureContext $context)` emits `content` SSE events (conversational register, matching Advice Fyn) and direct-write tool-use events. It does NOT emit `quick_replies`, `onboarding_layout_change`, or any event that changes the chat-panel layout.
- Test: `tests/Feature/Onboarding/InlineCaptureSilenceTest.php` — per scenario `09-07`.

**INV-2.4.3 System-level messages are exempt.**
- Property: token-limit, consent-required, preview-short-circuit, maintenance messages MAY emit their own SSE events (`token_limit`, `preview_cta`, etc.) even mid-handoff. These are system emissions, not Fyn turns.
- Test: assertion in the handoff test suite — system-event count is allowed to be non-zero.

### 2.5 Direct-write tool semantics

**INV-2.5.1 Every `create_*` / `update_*` / `capture_*` handler writes to DB synchronously.**
- Property: each of the 17 currently-fill_form handlers in `CoordinatingAgent.php` returns `['success' => bool, 'entity_type' => string, 'entity_id' => int|null, 'persisted_fields' => array, 'validation_errors' => array]`. No handler returns `['action' => 'fill_form', ...]` after Sprint 0.
- Test: `tests/Feature/AI/DirectWriteCoverageTest.php` — iterate the 17 handler names, call each with a valid payload, assert return shape + DB row existence.
- Accept: `grep -c "'action' => 'fill_form'" app/Agents/CoordinatingAgent.php` returns 1 (the single remaining `create_what_if_scenario` analytics artefact, confirmed in INV-2.5.6).

**INV-2.5.2 Observer chain fires on every direct-write.**
- Property: for each write, the corresponding Laravel observer chain fires: risk recalc (`UserRiskObserver` / `InvestmentAccountRiskObserver` / ...), goal tracking (`TracksGoalContributions` trait), cache invalidation (`NetWorthCacheObserver` etc.), Monte Carlo triggers (`LifeEventMonteCarloObserver`), trust-gift creation (`TrustObserver`).
- Test: `tests/Feature/AI/DirectWriteObserverFireTest.php` — Pest mock observer spies per handler.

**INV-2.5.3 Model sees real `entity_id` across turns.**
- Property: the tool-call context assembled in `HasAiChat::buildMessageHistory` preserves the `entity_id` and `entity_type` from each prior tool result within the conversation. `summariseToolResult` is loosened to retain these two fields.
- Test: `tests/Unit/Traits/HasAiChatSummarisationTest.php` — two-turn conversation where turn 1 creates a record + turn 2 references it; assert turn 2's prompt contains `entity_id`.

**INV-2.5.4 Audit trail matches reality.**
- Property: `ai_audit_events` table row written on every tool execution with `status IN ('dispatched','persisted','failed')`. For direct-write tools, `dispatched` fires at `executeTool` entry, `persisted` fires after the write returns with `entity_id`, `failed` fires on exception. The file-channel `[AI-AUDIT]` is removed.
- Schema: see §2.10.2.
- Test: `tests/Feature/AI/AuditTruthfulnessTest.php` — call each direct-write handler; assert two rows (`dispatched` + `persisted`); call with invalid payload; assert `dispatched` + `failed`.

**INV-2.5.5 Direct-write transaction boundary per handler.**
- Property: each handler wraps its writes in `DB::transaction`. Observers fire within the transaction; cache invalidation and email queuing fire after commit.
- Test: `tests/Feature/AI/DirectWriteTransactionRollbackTest.php` — induce a mid-write exception; assert zero rows persisted.

**INV-2.5.6 `create_what_if_scenario` is the only retained fill_form-style artefact write callable by Advice Fyn.**
- Property: this tool writes a scenario row for analytics but does not mutate user-facing financial data. Allowed on `AdviceFyn::buildToolList`.
- Test: named check in `tests/Feature/Fyn/AdviceFynToolListTest.php` (INV-2.1.2).

### 2.6 Read completeness

**INV-2.6.1 `list_*` + `get_*` tools return complete data.**
- Property: handlers do not truncate, paginate below 100 items, or summarise lists returned to the model. `handleModuleAnalysis` returns the raw `analyze()` output, not a summary.
- Test: `tests/Feature/AI/ReadCompletenessTest.php` — seed 50+ records per list tool; assert handler return length equals DB count.

**INV-2.6.2 `get_recommendations` returns complete `orchestrateAnalysis` output.**
- Property: `handleRecommendations` returns the full `ranked_recommendations` array with all metadata (priority_score, timeline, category, impact, rationale, personalised_context) — no summarisation.
- Test: `tests/Feature/AI/GetRecommendationsCompletenessTest.php`.

### 2.7 Provider parity

**INV-2.7.1 Identical tool catalogue on both providers.**
- Property: `AiToolDefinitions::getTools` and `XaiToolDefinitions::getTools` return the same tool-name set. Count: 40 (37 existing + 3 billing). Both with strict schemas where applicable.
- Test: `tests/Architecture/ToolCatalogueParityTest.php` (Pest Architecture suite) — `expect($anthropicNames)->toEqualCanonicalizing($xaiNames)`.

**INV-2.7.2 Billing tools present.**
- Property: 3 new tools registered on both providers: `get_subscription_status`, `list_invoices`, `get_current_plan`.
  - `get_subscription_status`: `{status: string, plan_name: string, trial_ends_at: ?date, current_period_end: date, next_charge_amount: float, is_cancelled: bool}`.
  - `list_invoices`: `[{invoice_id: int, issued_at: date, amount: float, status: string, pdf_url: string}]`.
  - `get_current_plan`: `{plan_name: string, tier: string, price_gbp: float, features: string[]}`.
- Source data: existing `subscriptions` + `invoices` tables.
- Test: `tests/Feature/AI/BillingToolsTest.php`.

**INV-2.7.3 `update_record` schema is strict + per-entity allowlist.**
- Property: `update_record.fields` schema is `additionalProperties: false` on both providers; xAI wraps with `strict: true`. Per-entity allowlist enforced in `handleUpdateRecord`. Dynamic-key shape is replaced by a `oneOf` schema keyed on `entity_type` — one sub-schema per entity type listing its exact updateable fields.
- Allowlist source: `app/Constants/UpdateRecordAllowlist.php` (new).
- Forbidden-to-update fields (always excluded): `id`, `user_id`, `joint_owner_id`, `ownership_percentage`, `Trust.settlor`, `Mortgage.start_date`, `Mortgage.mortgage_type`, `FamilyMember.relationship`, `Will.testator_id`, `LastingPowerOfAttorney.donor_id`, any `*_at` timestamp, any foreign-key column pointing to identity.
- Test: `tests/Unit/Constants/UpdateRecordAllowlistTest.php` + feature tests per entity type.

### 2.8 Multi-entity coverage

**INV-2.8.1 Sprint 0 target: 4-focus gap-fill running on both paths.**
- Property: `AssetCaptureEntityExtractor` (existing) runs inside `handleInlineCapture` + inside the director's `handleAssetCapture`. Covers protection / savings / retirement / investment with known providers. Gap-fill dedup against DB `(user_id, entity_fingerprint, created_at > now() - 24h)`.
- Test: `tests/Feature/AI/MultiEntityGapFillTest.php` — "Aviva £300k and Vitality £100k" → 2 protection policies persisted; retry same message → no doubles.

**INV-2.8.2 Sprint 2 target: batch-shaped tools for all 18+ entity types.**
- Property: new tools `capture_protection_policies(policies: [...])`, `capture_savings_accounts`, `capture_pensions`, `capture_investment_accounts`, `capture_properties_mortgages`, `capture_trusts`, `capture_family_members`, `capture_goals`, `capture_life_events`, `capture_chattels`, `capture_business_interests`, `capture_liabilities`, `capture_estate_gifts`, `capture_holdings`. Strict JSON schema per tool. Each tool persists all items or none (transaction).
- Retires `AssetCaptureEntityExtractor` when gap-fill fire rate < 2% sustained over a 2-week eval window.
- Test: scenarios `03-multi-entity/*.yaml` in Rubric B.

### 2.9 Reliability

**INV-2.9.1 Atomic token budget.**
- Property: new `ai_daily_usage` table with unique `(user_id, usage_date)`. `HasAiGuardrails::consume` uses `DB::transaction` + `SELECT ... FOR UPDATE`. `Cache::remember` call at `HasAiGuardrails.php:221` removed.
- Test: `tests/Feature/AI/TokenBudgetConcurrencyTest.php` — two parallel requests at boundary; second returns `token_limit` without consuming double.

**INV-2.9.2 SSE-abort policy: keep partial writes, instrument + monitor.**
- Property: when `connection_aborted()` is detected mid-generator, in-flight writes are committed (keep). An `ai_abort_events` row is inserted with `conversation_id`, `last_tool_call`, `partial_write_count`. The `aiChat.js` client resumes on reconnect and reconciles via normal DB reads.
- Decision: **keep** writes — matches the current form-POST path (successful POST, dropped SSE → record exists, user sees on next open). Spec flags this for instrumentation; re-evaluate if logs show user-visible inconsistency.
- Test: `tests/Feature/AI/SseAbortKeepWritesTest.php` — induce abort mid-create; assert record persisted + `ai_abort_events` row.

**INV-2.9.3 Idempotency key on `POST /conversations/{id}/messages`.**
- Property: middleware reads `Idempotency-Key` header; duplicate keys within 24h return the cached response. New `ai_request_idempotency` table: `(idempotency_key, user_id, response_hash, created_at)` with TTL cleanup.
- Test: `tests/Feature/AI/IdempotencyKeyTest.php`.

**INV-2.9.4 Provider-swap write lock.**
- Property: each chat-loop iteration reads `ai_provider` cache once at entry; the captured value holds for the whole `while (true)` loop even if admin toggles mid-conversation.
- Test: `tests/Feature/AI/ProviderSwapLockTest.php`.

**INV-2.9.5 Gap-fill dedup against DB.**
- Property: before `AssetCaptureEntityExtractor` emits a synthesised fill-in for a detected entity, it queries the target table for `(user_id, provider, policy_type_group|account_type, created_at > now() - 24h)` and skips matches.
- Test: `tests/Feature/AI/GapFillDedupTest.php`.

**INV-2.9.6 `generateTitle` sanitised.**
- Property: `HasAiChat::generateTitle` runs `strip_tags($message)` then `mb_substr(..., 0, 100)` before sending to LLM AND before writing to `ai_conversations.title`.
- Test: `tests/Unit/Traits/GenerateTitleSanitisationTest.php`.

### 2.10 Compliance

**INV-2.10.1 CoreIdentity rewrite.**
- Property: `app/Services/AI/Prompts/CoreIdentity.php` no longer contains the string *"qualified financial planner"* or any equivalent professional-role framing. It frames Fyn as a guidance tool.
- Test: `tests/Architecture/CoreIdentityFramingTest.php` — `expect(file_get_contents('.../CoreIdentity.php'))->not->toContain('qualified financial planner')`.

**INV-2.10.2 Hash-chain audit.**
- Schema for `ai_audit_events`:
  ```sql
  id BIGINT PK, user_id BIGINT FK, conversation_id BIGINT FK NULL,
  tool_name VARCHAR(64), operation ENUM('read','write','handoff','classify'),
  status ENUM('dispatched','persisted','failed','stripped'),
  input_summary JSON, result_summary JSON, entity_type VARCHAR(32) NULL, entity_id BIGINT NULL,
  prev_hash CHAR(64), row_hash CHAR(64), signed_at TIMESTAMP, signature CHAR(64),
  created_at TIMESTAMP
  ```
- `row_hash = sha256(prev_hash || serialised(fields_except_hashes) || signed_at)`.
- `signature = hmac_sha256(row_hash, env('AI_AUDIT_HMAC_KEY'))`.
- `php artisan ai:audit:verify-chain` walks the table and asserts chain validity.
- Retention: 7 years for rows where `operation='write'` or `tool_name LIKE 'get_recommendations'`; 2 years for others.
- Test: `tests/Feature/Audit/HashChainTest.php` + `tests/Feature/Audit/HmacSigningTest.php` + `tests/Feature/Audit/ChainTamperDetectionTest.php`.

**INV-2.10.3 Runtime consent check.**
- Property: `AiChatController::sendMessage` and `AiChatController::startOnboarding` call `ConsentService::hasConsent($user, 'ai_chat')` at entry. On false, returns HTTP 403 `{error: 'consent_required', required: 'ai_chat'}`. Consent withdrawn mid-conversation triggers `consent_required` SSE event that closes the stream.
- Migration: `user_consents.type` enum gains `ai_chat` + `ai_chat_health_data` values.
- Test: `tests/Feature/AI/ConsentRuntimeCheckTest.php`.

**INV-2.10.4 User-controlled prompt field sanitisation + structural separation.**
- Property: every user-controlled field (first_name, surname, employer, occupation, goal_name, family_member names, etc.) passed into prompt builders is stripped to `[A-Za-z0-9\s'.-]` before interpolation. User-content within prompts is wrapped in `<user_provided>...</user_provided>` markers. System instructions never mix with user-provided strings.
- Test: `tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php` + injection-scenario tests `06-prompt-injection/*.yaml`.

### 2.11 Memory model

**INV-2.11.1 Three stores + one index, queried in order.**
- Property: prompt builders and Advice Fyn tool-call paths query memory in order: (1) authoritative DB state via module-service reads, (2) `ai_conversations.onboarding_parked_facts`, (3) current conversation's `ai_messages`, (4) conversation index. Each layer falls through only when empty.
- Retrieval is driven by `MemoryRetrieverService` (new, `app/Services/AI/MemoryRetrieverService.php`). Prompt builders inject the output as a `<known_facts>` block.
- Test: `tests/Unit/Services/AI/MemoryRetrieverServiceTest.php` — parameterised over each store; assert fall-through order.

**INV-2.11.2 Conversation index schema.**
- Option A chosen: new JSON columns on `ai_conversations`:
  - `summary TEXT NULL` — one-paragraph human-readable summary.
  - `topics JSON NULL` — array of canonical topic strings.
  - `entities_mentioned JSON NULL` — `[{type, id}]` records the conversation touched.
  - `intents_stated JSON NULL` — stated preferences that don't map to a DB column.
- Populated by `ConversationSummariserJob` dispatched on `STATE_DONE` transition (onboarding conversations) and on conversation-inactivity > 30 minutes (advice conversations).
- Summariser uses the cheapest configured model (default `grok-4-1-fast-non-reasoning`) with a structured-output prompt.
- Test: `tests/Feature/AI/ConversationIndexPopulationTest.php`.

**INV-2.11.3 `search_conversation_index` tool.**
- Property: new tool exposed to Advice Fyn. Input `{topic_keywords: string[], entity_types: string[]}`. Output: `[{conversation_id, summary, topics, entities_mentioned, intents_stated, last_message_at}]` matching any keyword or entity.
- Invocation rule: Advice Fyn calls this tool only when the `<known_facts>` block does not contain the needed fact.
- Test: `tests/Feature/AI/SearchConversationIndexTest.php`.

### 2.12 System-level message invariants

Covered in INV-2.4.3. No handoff-invisibility violation for system emissions.

### 2.13 Eval harness (Rubric B)

**INV-2.13.1 Scenario floor.**
- Property: `tests/Feature/Fyn/Eval/` contains at minimum 75 scenarios organised into 9 categories per `fyn-rubrics.md §B`. Mode 1 (mocked, 100% required on every PR) runs in CI. Mode 2 (real providers, ≥97% required) runs weekly.
- Test: `tests/Architecture/EvalScenarioCountTest.php` — count YAML files per category; assert category minima.

**INV-2.13.2 Hard-fail floors.**
- Property: per Rubric B §Validity/accuracy/recall: 100% entity validity, 100% value accuracy, 100% cross-entity consistency, 0% fabrication. PR blocked on any violation regardless of other scores.
- Test: `EvalRunner::enforceHardFailFloors` runs after every scenario; raises on violation.

---

## §3 — Delta register (Sprint 0 task-level detail; Sprint 1–4 summary)

### Sprint 0 — the shippable unit (3-4 weeks)

Two-Fyn collapse + direct-write conversions + reliability + audit chain + CoreIdentity + billing tools + consent + `generateTitle` sanitation. End-state: can deploy to `csjones.co/fynla` behind the ONBOARDING_FYN_FLOW_ENABLED flag.

#### Task 0.1 — Rebase `feature/fyn-persona-split` onto `main`

**Files:**
- Entire branch — 179-commit drift against `main`.

- [ ] **Step 1**: Snapshot current working tree.
  ```bash
  git -C /Users/CSJ/Desktop/fynla status
  git -C /Users/CSJ/Desktop/fynla rev-list --count origin/feature/fyn-persona-split..origin/main
  ```
  Expected: clean tree; count = 179.

- [ ] **Step 2**: Create rebase integration branch so main branch is preserved.
  ```bash
  git -C /Users/CSJ/Desktop/fynla checkout feature/fyn-persona-split
  git -C /Users/CSJ/Desktop/fynla checkout -b feature/fyn-persona-split-rebase
  git -C /Users/CSJ/Desktop/fynla fetch origin
  git -C /Users/CSJ/Desktop/fynla rebase origin/main
  ```

- [ ] **Step 3**: Resolve conflicts. Expected hotspots (per `audit-evidence.md §5`): `resources/js/layouts/AppLayout.vue`, `app/Agents/CoordinatingAgent.php`, `routes/api.php`, `routes/web.php`, `app/Traits/HasAiChat.php`, `app/Services/AI/AdvicePromptBuilder.php`, `app/Services/AI/AiToolDefinitions.php`, `app/Services/AI/XaiToolDefinitions.php`, `app/Services/AI/Prompts/ComplianceRules.php`, `app/Services/AI/Prompts/FcaProcessInstructions.php`, `app/Services/AI/StructuredResponseValidator.php`, `app/Http/Controllers/Api/AiChatController.php`, `app/Http/Controllers/Api/AdminController.php`, `resources/js/router/index.js`, `resources/js/store/modules/aiChat.js`, `resources/js/components/Shared/AiChatPanel.vue`. Plus phantom conflicts from Insights/Lifecycle deletions if `main` added dependents.

- [ ] **Step 4**: Full Pest run post-rebase.
  ```bash
  cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
  ```
  Expected: 2,448 passing + 1 flake. Triage failures.

- [ ] **Step 5**: Commit.
  ```bash
  git -C /Users/CSJ/Desktop/fynla commit -am "rebase: feature/fyn-persona-split onto main (179-commit drift)"
  ```

#### Task 0.2 — Delete stale OpenAI config block + Python sidecar

**Files:**
- Modify: `config/services.php:34-38`
- Delete: `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`
- Delete: `app/Http/Controllers/Api/AgentInternalController.php`, `app/Http/Middleware/AgentTokenAuth.php`
- Modify: `routes/api.php` (remove `/api/internal/agent/*` block at lines 1193-1199)
- Modify: `app/Http/Kernel.php` (remove `agent.token` middleware registration at line 81)
- Modify: `.env.example` (remove `AGENT_INTERNAL_TOKEN`)

- [ ] **Step 1**: Confirm zero external callers. Ask CSJ explicitly.

- [ ] **Step 2**: Remove OpenAI config block from `config/services.php:34-38`. Delete the `'openai' => [...]` entry.

- [ ] **Step 3**: Delete Python sidecar files.
  ```bash
  git -C /Users/CSJ/Desktop/fynla rm -r scripts/fynla_agent scripts/run_agent.py scripts/requirements.txt
  git -C /Users/CSJ/Desktop/fynla rm app/Http/Controllers/Api/AgentInternalController.php app/Http/Middleware/AgentTokenAuth.php
  ```

- [ ] **Step 4**: Remove route block + middleware registration.

- [ ] **Step 5**: Full Pest run.

- [ ] **Step 6**: Commit.
  ```bash
  git commit -m "chore: remove stale OpenAI config + dead Python sidecar"
  ```

#### Task 0.3 — Two-Fyn collapse (architecture)

**Files:**
- Create: `app/Services/AI/AdviceFyn.php`
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (add `handleInlineCapture`)
- Modify: `app/Http/Controllers/Api/AiChatController.php` (two-way dispatch)
- Delete: `app/Services/AI/FynPersonaOrchestrator.php`
- Delete: `app/Services/AI/FynPersonaInvoker.php`
- Delete: `app/Services/AI/FynPersonaRegistry.php`
- Delete: `app/Services/AI/Prompts/DataCapturePromptBuilder.php`
- Delete: `config/fyn_personas.php`
- Modify: `app/Providers/AppServiceProvider.php` (remove bindings)
- Create migration: `database/migrations/2026_04_25_000001_clear_stale_persona_state_for_completed_onboardings.php`
- Delete tests: `tests/Feature/AI/PersonaSplit/{Cancel,Timeout,ClassifierFastPath,Preview,KycGate}Test.php`, `tests/Unit/Services/AI/{FynPersonaInvoker,FynPersonaOrchestrator,FynPersonaRegistry}Test.php`, `tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php`, `tests/Unit/Services/AI/AdvicePromptBuilderPersonaSplitTest.php`
- Keep / port: `tests/Feature/AI/PersonaSplit/{CreateWill,CreatePOA,InlineCaptureFlow}Test.php` → move to `tests/Feature/Fyn/`

- [ ] **Step 1 — TDD red**: Write the failing invariant tests.
  - `tests/Feature/Fyn/DispatchRoutingTest.php` — asserts `AiChatController::sendMessage` has exactly two dispatch branches.
  - `tests/Feature/Fyn/AdviceFynToolListTest.php` — asserts `AdviceFyn::buildToolList` is disjoint from write-tool set.
  - `tests/Feature/Fyn/HandoffInvisibilityTest.php` — asserts zero `persona_state_change` events, zero `quick_replies` during capture.

  ```php
  // tests/Feature/Fyn/DispatchRoutingTest.php
  <?php
  declare(strict_types=1);

  use App\Http\Controllers\Api\AiChatController;
  use Illuminate\Support\Facades\File;

  it('AiChatController::sendMessage has exactly two Fyn dispatch branches', function () {
      $source = File::get(base_path('app/Http/Controllers/Api/AiChatController.php'));
      $method = preg_match('/public function sendMessage.*?\n    \}/s', $source, $m);
      expect($m[0])->toContain('OnboardingChatDirector');
      expect($m[0])->toContain('AdviceFyn');
      expect($m[0])->not->toContain('FynPersonaOrchestrator');
      expect($m[0])->not->toContain('FynPersonaInvoker');
  });
  ```

- [ ] **Step 2 — Verify tests fail**.
  ```bash
  ./vendor/bin/pest tests/Feature/Fyn/DispatchRoutingTest.php -v
  ```
  Expected: FAIL — `AdviceFyn` class does not exist.

- [ ] **Step 3 — Implement `AdviceFyn`**.
  ```php
  // app/Services/AI/AdviceFyn.php
  <?php
  declare(strict_types=1);

  namespace App\Services\AI;

  use App\Agents\CoordinatingAgent;
  use App\Models\AiConversation;
  use App\Models\User;
  use App\Services\AI\Prompts\AdvicePromptBuilder;

  final class AdviceFyn
  {
      public function __construct(
          private readonly CoordinatingAgent $coordinatingAgent,
          private readonly AdvicePromptBuilder $promptBuilder,
          private readonly AiToolDefinitions $toolDefinitions,
          private readonly XaiToolDefinitions $xaiToolDefinitions,
      ) {}

      public function handle(User $user, AiConversation $conversation, string $message, ?string $currentRoute = null): \Generator
      {
          $allowedTools = $this->buildToolList($user);
          return $this->coordinatingAgent->chatWithPromptOverride(
              user: $user,
              conversation: $conversation,
              message: $message,
              currentRoute: $currentRoute,
              systemPromptOverride: null,  // AdvicePromptBuilder runs as default
              allowedTools: $allowedTools,
              persistUserMessage: true,
              toolsListOverride: null,
              personaOverride: 'advice',
          );
      }

      /** @return list<string> */
      public function buildToolList(User $user): array
      {
          $writeTools = [
              'create_savings_account', 'create_investment_account', 'create_holding',
              'create_pension', 'create_property', 'create_mortgage',
              'create_protection_policy', 'create_asset', 'create_liability',
              'create_estate_gift', 'create_chattel', 'create_business_interest',
              'create_trust', 'create_family_member', 'create_will', 'update_will',
              'create_power_of_attorney', 'update_power_of_attorney',
              'update_record', 'delete_record', 'update_profile', 'set_expenditure',
              'capture_personal_details', 'capture_spouse_details',
              'capture_dependants', 'capture_work_details',
          ];
          $provider = cache('ai_provider', config('services.ai_provider', 'anthropic'));
          $definitions = $provider === 'xai' ? $this->xaiToolDefinitions : $this->toolDefinitions;
          $allTools = $definitions->getTools($user->is_preview_user);
          $toolNames = array_map(
              fn ($t) => $t['name'] ?? ($t['function']['name'] ?? null),
              $allTools
          );
          return array_values(array_diff(
              array_filter($toolNames),
              $writeTools,
          ));
      }
  }
  ```

- [ ] **Step 4 — Implement `handleInlineCapture` on the director**.
  Add method below `handleUserMessage`:
  ```php
  // app/Services/Onboarding/OnboardingChatDirector.php (append)
  public function handleInlineCapture(
      User $user,
      AiConversation $conversation,
      string $message,
      CaptureContext $context,
      ?string $currentRoute = null,
  ): \Generator {
      // Runs the capture prompt + tools + entity-extractor gap-fill
      // WITHOUT emitting quick_replies, onboarding_layout_change, or
      // persona_state_change events. Uses the advice-register system prompt
      // plus a capture-instruction suffix derived from CaptureContext.
      $allowedTools = $this->captureToolSet($context);  // the 26 write tools
      $promptSuffix = $this->promptBuilder->buildInlineCaptureSuffix($context);

      $generator = $this->coordinatingAgent->chatWithPromptOverride(
          user: $user,
          conversation: $conversation,
          message: $message,
          currentRoute: $currentRoute,
          systemPromptOverride: null,  // keep advice prompt
          allowedTools: $allowedTools,
          persistUserMessage: true,
          toolsListOverride: null,
          personaOverride: 'onboarding_inline',
      );

      // Run gap-fill afterward (extractor covers 4 focuses).
      yield from $this->emitGapFillFromCaptureContext($user, $conversation, $context, $message);
      yield from $generator;
  }
  ```

- [ ] **Step 5 — Rewrite `AiChatController::sendMessage` dispatch**.
  ```php
  // Replace existing sendMessage routing block
  $inOnboarding = $user->onboarding_completed === false
      && (bool) config('onboarding.fyn_flow_enabled', true);

  return new StreamedResponse(function () use (...) {
      try {
          $generator = $inOnboarding
              ? $this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute)
              : $this->adviceFyn->handle($user, $conversation, $message, $currentRoute);
          // ... stream loop ...
      }
  });
  ```

- [ ] **Step 6 — Delete orchestrator + invoker + registry + data_capture prompt builder + config**.
  ```bash
  git rm app/Services/AI/FynPersonaOrchestrator.php \
          app/Services/AI/FynPersonaInvoker.php \
          app/Services/AI/FynPersonaRegistry.php \
          app/Services/AI/Prompts/DataCapturePromptBuilder.php \
          config/fyn_personas.php
  ```

- [ ] **Step 7 — Migration to clear stale `persona_state`**.
  ```php
  // database/migrations/2026_04_25_000001_clear_stale_persona_state_for_completed_onboardings.php
  <?php
  declare(strict_types=1);

  use Illuminate\Database\Migrations\Migration;
  use Illuminate\Support\Facades\DB;

  return new class extends Migration {
      public function up(): void
      {
          DB::table('ai_conversations')
              ->update(['persona_state' => null]);
      }
      public function down(): void {}
  };
  ```

- [ ] **Step 8 — Delete + port tests** per Files list above.

- [ ] **Step 9 — Verify dispatch tests pass**.
  ```bash
  ./vendor/bin/pest tests/Feature/Fyn/
  ```
  Expected: DispatchRoutingTest green; AdviceFynToolListTest green; HandoffInvisibilityTest green.

- [ ] **Step 10 — Full Pest**.
  ```bash
  ./vendor/bin/pest
  ```
  Expected: all pass.

- [ ] **Step 11 — Commit**.
  ```bash
  git commit -am "feat(fyn): two-Fyn collapse — AdviceFyn + handleInlineCapture + delete orchestrator stack"
  ```

#### Task 0.4 — Remove visible-handoff UI

**Files:**
- Modify: `resources/js/store/modules/aiChat.js` (remove `persona_state_change` case)
- Modify: `resources/js/components/Shared/AiChatPanel.vue` (remove capturing-pill render + placeholder swap)
- Modify: `resources/js/store/modules/aiChat.js` state (remove `personaMode`)

- [ ] **Step 1 — Write failing test** (in `HandoffInvisibilityTest` — extend step 1 of Task 0.3): assert `aiChat.js` does not export `personaMode` getter.

- [ ] **Step 2 — Remove the handler block** in `aiChat.js`:
  Delete lines 511-516 (the `case 'persona_state_change':` block).

- [ ] **Step 3 — Remove `personaMode` state**:
  - Remove from `state` initial values.
  - Remove from `getters`.
  - Remove from `mutations.SET_PERSONA_MODE`.

- [ ] **Step 4 — Remove capturing-pill + placeholder swap** in `AiChatPanel.vue`:
  - Delete the conditional template that renders the pill.
  - Replace conditional input placeholder with single static string.

- [ ] **Step 5 — `npm run dev`, test in browser**.

- [ ] **Step 6 — Commit**.
  ```bash
  git commit -am "feat(fyn): remove visible-handoff UI — persona_state_change + capturing pill"
  ```

#### Task 0.5 — Convert 17 fill_form handlers to direct-write

One sub-task per handler. Pattern repeats. Example for `handleCreateSavingsAccount`:

**Files per sub-task:**
- Modify: `app/Agents/CoordinatingAgent.php::handle{Tool}` handler
- Verify: `app/Http/Requests/{Module}/Store{Entity}Request.php` (existing)
- Verify: corresponding Observer is registered

- [ ] **Step 1 — Write failing direct-write test for the handler**:
  ```php
  // tests/Feature/AI/DirectWriteHandlerTest.php (add case per handler)
  it('create_savings_account persists a SavingsAccount row directly', function () {
      $user = User::factory()->create();
      $agent = app(CoordinatingAgent::class);
      $result = $agent->executeTool('create_savings_account', [
          'account_name' => 'Test ISA',
          'provider' => 'Aviva',
          'account_type' => 'cash_isa',
          'balance' => 5000,
          'interest_rate' => 4.5,
      ], $user);

      expect($result['success'])->toBeTrue();
      expect($result['entity_type'])->toBe('savings_account');
      expect($result['entity_id'])->toBeInt();
      expect(SavingsAccount::find($result['entity_id']))
          ->not->toBeNull()
          ->balance->toBe('5000.00');
  });
  ```

- [ ] **Step 2 — Rewrite the handler**:
  ```php
  private function handleCreateSavingsAccount(array $input, User $user, bool $isPreview): array
  {
      if ($isPreview) {
          return ['blocked' => true, 'reason' => 'preview_mode'];
      }
      $validator = validator($input, (new StoreSavingsAccountRequest())->rules());
      if ($validator->fails()) {
          return ['error' => 'validation_failed', 'errors' => $validator->errors()->toArray()];
      }
      return DB::transaction(function () use ($user, $input) {
          $account = SavingsAccount::create([
              'user_id' => $user->id,
              ...$validator->validated(),
          ]);
          return [
              'success' => true,
              'entity_type' => 'savings_account',
              'entity_id' => $account->id,
              'persisted_fields' => $account->only(array_keys($validator->validated())),
          ];
      });
  }
  ```

- [ ] **Step 3 — Run handler test**. Expected: PASS.

- [ ] **Step 4 — Verify observer fires** via `tests/Feature/AI/DirectWriteObserverFireTest.php`.

- [ ] **Step 5 — Commit per handler**:
  ```bash
  git commit -am "feat(fyn): direct-write create_savings_account"
  ```

Repeat for:
- `create_investment_account`, `create_holding`, `create_pension`, `create_property`, `create_mortgage`, `create_protection_policy`, `create_asset`, `create_liability`, `create_estate_gift`, `create_chattel`, `create_business_interest`, `create_trust`, `create_family_member`, `create_will`, `update_will`, `create_power_of_attorney`, `update_power_of_attorney`.

Each sub-task identical shape: failing test → direct-write handler → observer-fire test → commit.

#### Task 0.6 — Billing/subscription tools

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php` (add 3 tool schemas)
- Modify: `app/Services/AI/XaiToolDefinitions.php` (add 3 tool schemas)
- Modify: `app/Agents/CoordinatingAgent.php` (add 3 handlers)

- [ ] **Step 1 — Failing tests**:
  ```php
  it('get_subscription_status returns current subscription', function () {
      $user = User::factory()->has(Subscription::factory()->active())->create();
      $result = app(CoordinatingAgent::class)
          ->executeTool('get_subscription_status', [], $user);
      expect($result)->toHaveKeys([
          'status', 'plan_name', 'trial_ends_at', 'current_period_end',
          'next_charge_amount', 'is_cancelled',
      ]);
  });
  ```

- [ ] **Step 2 — Register tools in both providers**.

- [ ] **Step 3 — Implement handlers**.

- [ ] **Step 4 — Parity test**:
  ```php
  // tests/Architecture/ToolCatalogueParityTest.php
  it('Anthropic and xAI tool catalogues match exactly', function () {
      $anthropic = collect(app(AiToolDefinitions::class)->getTools(false))
          ->pluck('name')->sort()->values();
      $xai = collect(app(XaiToolDefinitions::class)->getTools(false))
          ->map(fn ($t) => $t['function']['name'])->sort()->values();
      expect($anthropic->toArray())->toEqual($xai->toArray());
  });
  ```

- [ ] **Step 5 — Commit**.

#### Task 0.7 — `update_record` per-entity allowlist + strict schema

**Files:**
- Create: `app/Constants/UpdateRecordAllowlist.php`
- Modify: `app/Agents/CoordinatingAgent.php::handleUpdateRecord`
- Modify: `app/Services/AI/AiToolDefinitions.php` (oneOf schema)
- Modify: `app/Services/AI/XaiToolDefinitions.php` (strict mode)

- [ ] **Step 1 — Write allowlist**:
  ```php
  // app/Constants/UpdateRecordAllowlist.php
  final class UpdateRecordAllowlist
  {
      public const MAP = [
          'savings_account' => ['account_name', 'provider', 'balance', 'interest_rate', 'account_type'],
          'investment_account' => ['account_name', 'provider', 'total_value', 'account_type'],
          'protection_policy' => ['provider', 'sum_assured', 'monthly_premium', 'end_date'],
          'trust' => ['trust_name', 'trustees', 'beneficiaries'],  // NOTE: settlor excluded — IHT impact
          'mortgage' => ['outstanding_balance', 'interest_rate', 'term_years'],  // NOTE: start_date excluded — re-amortises
          'family_member' => ['first_name', 'surname', 'date_of_birth', 'annual_income'],  // NOTE: relationship excluded — spouse-linking side effect
          // ... 15+ more ...
      ];
  }
  ```

- [ ] **Step 2 — Enforcement in handler**:
  ```php
  private function handleUpdateRecord(array $input, User $user, bool $isPreview): array
  {
      if ($isPreview) return ['blocked' => true, 'reason' => 'preview_mode'];
      $entityType = $input['entity_type'];
      $allowedFields = UpdateRecordAllowlist::MAP[$entityType] ?? [];
      $fields = array_intersect_key($input['fields'] ?? [], array_flip($allowedFields));
      if (count($fields) !== count($input['fields'] ?? [])) {
          $disallowed = array_diff_key($input['fields'], array_flip($allowedFields));
          return [
              'error' => 'fields_not_allowed',
              'disallowed_fields' => array_keys($disallowed),
              'entity_type' => $entityType,
          ];
      }
      // ... dispatch to model::update ...
  }
  ```

- [ ] **Step 3 — oneOf schema in Anthropic tool**:
  Replace `'fields' => ['type' => 'object', 'additionalProperties' => true]` with `'oneOf' => [...]` enumerating each entity's allowed-fields object.

- [ ] **Step 4 — xAI strict-mode wrap**.

- [ ] **Step 5 — Feature tests** per entity type.

- [ ] **Step 6 — Commit**.

#### Task 0.8 — Delete confirmation pattern

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php::handleDeleteRecord`

- [ ] **Step 1 — Failing test**: `delete_record` called once returns `{requires_confirmation: true, confirmation_token: ...}`; called again with matching token persists.

- [ ] **Step 2 — Two-phase delete**:
  ```php
  private function handleDeleteRecord(array $input, User $user, bool $isPreview): array
  {
      if ($isPreview) return ['blocked' => true];
      $confirmationToken = hash('sha256', $user->id . $input['entity_type'] . $input['entity_id'] . now()->format('Y-m-d'));
      if (($input['confirmation_token'] ?? '') !== $confirmationToken) {
          return [
              'requires_confirmation' => true,
              'confirmation_token' => $confirmationToken,
              'entity_type' => $input['entity_type'],
              'entity_id' => $input['entity_id'],
              'preview_message' => "This will delete {$input['entity_type']} #{$input['entity_id']}. Confirm?",
          ];
      }
      // ... proceed ...
  }
  ```

- [ ] **Step 3 — Test + commit**.

#### Task 0.9 — Consent runtime check

**Files:**
- Modify: `app/Http/Controllers/Api/AiChatController.php::sendMessage` (+ `startOnboarding`)
- Modify: `app/Services/GDPR/ConsentService.php` (new constants `TYPE_AI_CHAT`, `TYPE_AI_CHAT_HEALTH_DATA` if not present)
- Create migration: `database/migrations/2026_04_25_000002_add_ai_chat_consent_types.php`

- [ ] **Step 1 — Failing test**: user with withdrawn `ai_chat` consent posts to `/api/ai-chat/...`; expect 403 with `{error: 'consent_required', required: 'ai_chat'}`.

- [ ] **Step 2 — Add migration + entry to consent type enum** (or migrate to string).

- [ ] **Step 3 — Add early-return in controller**.

- [ ] **Step 4 — Frontend consent-required handler** in `aiChat.js` — route to consent modal.

- [ ] **Step 5 — Test + commit**.

#### Task 0.10 — User-prompt-field sanitisation + structural separation

**Files:**
- Modify: `app/Services/AI/AdvicePromptBuilder.php`
- Modify: `app/Services/Onboarding/OnboardingPromptBuilder.php`
- Create: `app/Services/AI/Prompts/UserContentSanitiser.php`

- [ ] **Step 1 — Failing injection tests** from `06-prompt-injection/*.yaml`.

- [ ] **Step 2 — Sanitiser**:
  ```php
  final class UserContentSanitiser
  {
      public static function clean(string $value): string
      {
          return preg_replace("/[^A-Za-z0-9\s'.,\-]/", '', $value) ?? '';
      }
      public static function wrap(string $value): string
      {
          return '<user_provided>' . self::clean($value) . '</user_provided>';
      }
  }
  ```

- [ ] **Step 3 — Apply to every user-controlled field in prompt builders**.

- [ ] **Step 4 — Tests + commit**.

#### Task 0.11 — SSE abort detection + atomic token budget + idempotency + provider-swap lock + gap-fill dedup

Five reliability invariants; each is a sub-task with TDD cycle. Abbreviated here — see §2.9 for the exact invariants.

- [ ] **Step 1 — Abort detection**: add `connection_aborted()` checks between yields in `HasAiChat::chat`; insert `ai_abort_events` row on detection; do NOT roll back writes (keep policy).
- [ ] **Step 2 — Atomic token budget**: new `ai_daily_usage` table; rewrite `HasAiGuardrails::consume` with `DB::transaction` + `FOR UPDATE`; backfill from today's `ai_messages`.
- [ ] **Step 3 — Idempotency**: new `ai_request_idempotency` table + middleware; 24h TTL cleanup job.
- [ ] **Step 4 — Provider-swap lock**: capture `ai_provider` once at loop entry, hold for loop duration.
- [ ] **Step 5 — Gap-fill dedup**: add `(user_id, entity_fingerprint, created_at > now()-24h)` query before extractor emit.
- [ ] **Step 6 — `generateTitle` sanitation**: `strip_tags` + length clamp at `HasAiChat.php:704`.
- [ ] **Step 7 — Full Pest + commit**.

#### Task 0.12 — Hash-chain audit migration

**Files:**
- Create migration: `database/migrations/2026_04_25_000003_create_ai_audit_events_table.php`
- Create model: `app/Models/AiAuditEvent.php`
- Create service: `app/Services/AI/AuditChainService.php`
- Create command: `app/Console/Commands/AiAuditVerifyChainCommand.php`
- Modify: `app/Agents/CoordinatingAgent.php::executeTool` — replace `Log::channel('single')` with `AuditChainService::append`
- Modify: `resources/js/components/Admin/AiAudit.vue` — add chain-view tab
- Create: `app/Jobs/AiAuditRetentionJob.php`
- Modify: `app/Console/Kernel.php` — schedule retention + integrity-check

- [ ] **Step 1 — Migration** per schema in INV-2.10.2.

- [ ] **Step 2 — `AuditChainService::append($event)`**:
  ```php
  public function append(array $event): AiAuditEvent
  {
      return DB::transaction(function () use ($event) {
          $prev = AiAuditEvent::lockForUpdate()->latest()->first();
          $prevHash = $prev?->row_hash ?? str_repeat('0', 64);
          $signedAt = now();
          $rowHash = hash('sha256', $prevHash . json_encode($event) . $signedAt);
          $signature = hash_hmac('sha256', $rowHash, config('app.ai_audit_hmac_key'));
          return AiAuditEvent::create([
              ...$event,
              'prev_hash' => $prevHash,
              'row_hash' => $rowHash,
              'signed_at' => $signedAt,
              'signature' => $signature,
          ]);
      });
  }
  ```

- [ ] **Step 3 — `verifyChain()` walks the table**.

- [ ] **Step 4 — `php artisan ai:audit:verify-chain` command** returns JSON `{chain_valid, tip_hash, row_count}`.

- [ ] **Step 5 — Replace `[AI-AUDIT]` calls with `AuditChainService::append`**. Both dispatch + persisted + failed events (INV-2.5.4).

- [ ] **Step 6 — `AiAuditRetentionJob`** runs weekly; pseudonymises rows older than retention limit via hash-preserving token swap.

- [ ] **Step 7 — Tests**: chain validity, HMAC, tamper detection, retention pseudonymisation preserves chain.

- [ ] **Step 8 — Commit**.

#### Task 0.13 — CoreIdentity rewrite + FCA signposting

**Files:**
- Modify: `app/Services/AI/Prompts/CoreIdentity.php`
- Modify: `app/Services/AI/AdvicePromptBuilder.php` (append signposting to recommendation-mode responses)

- [ ] **Step 1 — Architecture test**: `CoreIdentity` source does not contain "qualified financial planner" or "financial adviser" (inside Fyn's self-description; the signposting string is fine).

- [ ] **Step 2 — Rewrite `CoreIdentity.php`**:
  ```php
  public static function get(string $firstName): string
  {
      return <<<PROMPT
  You are Fyn, a UK personal-finance guidance tool inside the Fynla app. You help {$firstName} understand their finances, explore options, and surface the outputs of Fynla's financial-planning engines. You do NOT give personalised regulated financial advice — for that, {$firstName} must consult a qualified financial adviser.

  Tone: clear, plain-English, calm. Never patronising. Never alarmist. British spelling. Currency in £. Always signpost regulated advice when the query asks "what should I do?".
  PROMPT;
  }
  ```

- [ ] **Step 3 — Signposting in AdvicePromptBuilder**: add suffix to recommendation-mode prompt: *"End your response with: 'For regulated advice personal to your circumstances, speak to a qualified financial adviser.'"*.

- [ ] **Step 4 — Test signposting presence on recommendation-mode; absence on factual**.

- [ ] **Step 5 — Commit**.

#### Task 0.14 — Out-of-remit response

**Files:**
- Modify: `app/Services/AI/AdviceFyn.php` (out-of-remit early return)
- Modify: `app/Services/AI/Prompts/AdvicePromptBuilder.php` (add refusal instruction)

- [ ] **Step 1 — Failing test**: medical query → exact output *"I'm able to help you with your finances. medical queries are out of scope."* No contact.

- [ ] **Step 2 — Classifier → AdviceFyn early-return** when classification returns `out_of_remit`.

- [ ] **Step 3 — Commit**.

---

### Sprint 1 — Eval harness + memory + CoreIdentity ratchet (1-2 weeks)

Summary: build `tests/Feature/Fyn/Eval/` scaffold with `EvalRunner` + `MockedProviderClient` + first 30 scenarios. Implement conversation index schema + `ConversationSummariserJob` + `search_conversation_index` tool. Implement `MemoryRetrieverService` + `<known_facts>` injection in every prompt builder. Per-sprint task plan gets its own doc at Sprint-0 end.

- Sprint-1 planning artefact: `/Users/CSJ/Desktop/fynla/April/April24Updates/fyn-v2-sprint-1-plan.md` (to be written after Sprint 0 lands).
- Invariants covered: 2.11.1, 2.11.2, 2.11.3, 2.13.1, 2.13.2, 2.3.5 (rendering), 2.10.1 (ratchet from Sprint 0 text).

### Sprint 2 — Batch-shaped tools + 18 entity coverage (2-3 weeks)

Summary: introduce the 14 `capture_*_batch` tools per INV-2.8.2. Retire `AssetCaptureEntityExtractor` when gap-fill fire rate sustained <2%. Ratchet Rubric-B `recall_floor` per-tool upward (mortgage → 100%, protection/savings → 98% per CSJ decision 3 in `audit-synthesis.md §8`). Per-sprint plan to follow.

### Sprint 3 — Dev deploy, local-first gate (1 week)

Summary: enforce local-first gate (CSJTODO §8.5). All Pest + Rubric-B Mode-1 green locally before dev deploy; browser-test matrix complete; deploy to `csjones.co/fynla`.

### Sprint 4 — Production hardening (4-8 weeks calendar)

Summary: external legal opinion on guidance posture; DPIA drafting; Privacy Policy rewrite to disclose Anthropic (chat) + xAI (chat) + Meta Pixel / AWIN / Plausible accurately (OR remove trackers to match current policy text — commercial decision); Article 28 DPA verification; UK IDTA + Transfer Risk Assessment; provider failover; Sentry integration.

---

## §4 — Out of scope for this spec

- Cross-conversation full-history replay (replaced by conversation index).
- Non-financial query categories (medical, legal, emotional-support, general-knowledge).
- Mobile-specific features beyond what the existing Capacitor iOS build uses.
- Admin-side analytics dashboards beyond `AiAudit.vue` + chain-view tab.
- Pricing / subscription onboarding UI — only the read-only query tools are in scope.
- FCA targeted-support (PS25/22, live 6 April 2026) — CSJ decision: guidance-only posture, not targeted support.
- Multi-tenant isolation beyond existing `user_id` scoping.

---

## §5 — Open decisions (all resolved at spec time)

Preserved for audit-trail:

| Decision | Answer | Source |
|---|---|---|
| Tool semantics | Direct-write everywhere (Q1=a) | CSJ 24 April |
| Provider parity | Reach parity, 40/36 catalogue (Q2=a) | CSJ 24 April |
| Recommendation engine | `orchestrateAnalysis` + module-agent `analyze()` + `NetWorthService` for factual | CSJ "biggest piece of code... integrates into the system" |
| Advice response shape | New `advice_response` SSE event + new `AdviceResponsePanel.vue` | CSJ 24 April |
| SSE abort | Keep partial writes, instrument + monitor | CSJ 24 April |
| Document extraction | UI-only CTA flow, not an Advice Fyn tool | CSJ 24 April |
| Entry-source mapping | Config-driven, extensible (4 initial + default `path_choice`) | CSJ 24 April |
| FCA signposting | "For regulated advice personal to your circumstances, speak to a qualified financial adviser." | CSJ 24 April |
| Out-of-remit copy | "I'm able to help you with your finances. {context} is out of scope." | CSJ 24 April |
| Memory model | 3 stores + 1 index; retrieval order DB → parked → current → index | CSJ 24 April |
| FCA posture | Guidance-only | CSJ decision 1 in audit-synthesis §8 |
| Persona count | Two Fyns, no Orchestrator class | CSJ decision 2 in audit-synthesis §8 |
| Multi-entity thresholds | 95% baseline recall/precision; 100% hard-fail floors | CSJ decision 3 in audit-synthesis §8 |
| Python sidecar | Delete | CSJ decision 4 in audit-synthesis §8 |
| Deploy gate | Local-first unambiguous | CSJ decision 5 in audit-synthesis §8 |
| Rubrics | Build both | CSJ decision 7 in audit-synthesis §8 |

---

## §6 — Rubric-B scenario catalogue (reference)

75 scenarios across 9 categories. Full breakdown in `fyn-rubrics.md §B`. Categories:

| # | Category | Count | Directory |
|---|---|---|---|
| 01 | Query types | 22 | `tests/Feature/Fyn/Eval/scenarios/01-query-types/` |
| 02 | Preview personas | 6 | `02-preview-personas/` |
| 03 | Multi-entity | 10 | `03-multi-entity/` |
| 04 | Handoff round-trips | 5 | `04-handoffs/` |
| 05 | Cancel / timeout | 3 | `05-cancel-timeout/` |
| 06 | Prompt injection | 10 | `06-prompt-injection/` |
| 07 | Regulatory | 5 | `07-regulatory/` |
| 08 | Provider parity | 4 | `08-provider-parity/` |
| 09 | Canonical §0 behaviour | 10 | `09-canonical-behaviour/` |
| **Total** | | **75** | |

Scenario files in `09-canonical-behaviour/`:
- `path-choice-to-done`
- `resume-after-disconnect`
- `memory-no-repeat-ask`
- `advice-factual-net-worth`
- `advice-recommendation-route`
- `advice-invoice-subscription`
- `advice-handoff-invisible-capture`
- `advice-read-only-tool-list`
- `index-populated-on-close`
- `cross-conversation-surface`

---

## §7 — Verification (how to run the spec against a build)

After Sprint 0 lands:

1. **Pest full suite**: `./vendor/bin/pest` — all pass (2,400+ scenarios including new Fyn tests).
2. **Rubric-B Mode 1**: `./vendor/bin/pest tests/Feature/Fyn/Eval/ --testsuite=Eval` — 100%.
3. **Architecture tests**: `./vendor/bin/pest --testsuite=Architecture` — all pass (tool parity, CoreIdentity framing, file-size caps).
4. **Rubric-A re-score**: walk each dimension's test per `fyn-rubrics.md §A`. Publish delta; expect 4-5/40 → 13-15/40 (Sprint 0 lands D4, D5, D6 level-2; D1 level-2; D10 level-2 partial).
5. **Browser smoke test**: Playwright-scripted `09-canonical-behaviour` scenarios run in preview-persona context.
6. **Audit-chain verification**: `php artisan ai:audit:verify-chain` — `{chain_valid: true, tip_hash: ..., row_count: N}`.

After Sprint 1: Rubric-A → 17-18/40 🟠 Limited beta. Dev deploy permitted per CSJ gate.

---

## §8 — File structure (new / modified / deleted)

### New files
```
app/Services/AI/AdviceFyn.php
app/Services/AI/AuditChainService.php
app/Services/AI/MemoryRetrieverService.php
app/Services/AI/Prompts/UserContentSanitiser.php
app/Constants/UpdateRecordAllowlist.php
app/Models/AiAuditEvent.php
app/Models/AiDailyUsage.php
app/Models/AiRequestIdempotency.php
app/Models/AiAbortEvent.php
app/Console/Commands/AiAuditVerifyChainCommand.php
app/Jobs/AiAuditRetentionJob.php
app/Jobs/AiIdempotencyCleanupJob.php
app/Jobs/ConversationSummariserJob.php
app/Http/Middleware/IdempotencyKeyMiddleware.php
resources/js/components/Shared/AdviceResponsePanel.vue
database/migrations/2026_04_25_000001_clear_stale_persona_state_for_completed_onboardings.php
database/migrations/2026_04_25_000002_add_ai_chat_consent_types.php
database/migrations/2026_04_25_000003_create_ai_audit_events_table.php
database/migrations/2026_04_25_000004_create_ai_daily_usage_table.php
database/migrations/2026_04_25_000005_create_ai_request_idempotency_table.php
database/migrations/2026_04_25_000006_create_ai_abort_events_table.php
database/migrations/2026_04_25_000007_add_conversation_index_columns.php
tests/Feature/Fyn/DispatchRoutingTest.php
tests/Feature/Fyn/AdviceFynToolListTest.php
tests/Feature/Fyn/HandoffInvisibilityTest.php
tests/Feature/Fyn/OutOfRemitTest.php
tests/Feature/Fyn/FcaSignpostingTest.php
tests/Feature/Fyn/AdviceResponseSseShapeTest.php
tests/Feature/AI/DirectWriteCoverageTest.php
tests/Feature/AI/DirectWriteObserverFireTest.php
tests/Feature/AI/DirectWriteTransactionRollbackTest.php
tests/Feature/AI/BillingToolsTest.php
tests/Feature/AI/MultiEntityGapFillTest.php
tests/Feature/AI/TokenBudgetConcurrencyTest.php
tests/Feature/AI/IdempotencyKeyTest.php
tests/Feature/AI/ProviderSwapLockTest.php
tests/Feature/AI/GapFillDedupTest.php
tests/Feature/AI/ReadCompletenessTest.php
tests/Feature/AI/GetRecommendationsCompletenessTest.php
tests/Feature/AI/SseAbortKeepWritesTest.php
tests/Feature/AI/ConsentRuntimeCheckTest.php
tests/Feature/AI/ConversationIndexPopulationTest.php
tests/Feature/AI/SearchConversationIndexTest.php
tests/Feature/Audit/HashChainTest.php
tests/Feature/Audit/HmacSigningTest.php
tests/Feature/Audit/ChainTamperDetectionTest.php
tests/Feature/Onboarding/BaseSpouseDirectWriteTest.php
tests/Feature/Onboarding/ResumeAfterDisconnectTest.php
tests/Feature/Onboarding/InlineCaptureSilenceTest.php
tests/Feature/Onboarding/EntrySourceJourneyMapTest.php
tests/Architecture/ToolCatalogueParityTest.php
tests/Architecture/CoreIdentityFramingTest.php
tests/Architecture/EvalScenarioCountTest.php
tests/Unit/Constants/UpdateRecordAllowlistTest.php
tests/Unit/Services/Onboarding/KnownFactsBlockTest.php
tests/Unit/Services/AI/AdviceFynResponseModeTest.php
tests/Unit/Services/AI/MemoryRetrieverServiceTest.php
tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php
tests/Unit/Traits/HasAiChatSummarisationTest.php
tests/Unit/Traits/GenerateTitleSanitisationTest.php
tests/Feature/Fyn/Eval/EvalRunner.php
tests/Feature/Fyn/Eval/MockedProviderClient.php
tests/Feature/Fyn/Eval/AssertionHelpers.php
tests/Feature/Fyn/Eval/EvalReport.php
tests/Feature/Fyn/Eval/scenarios/01-query-types/*.yaml (22)
tests/Feature/Fyn/Eval/scenarios/02-preview-personas/*.yaml (6)
tests/Feature/Fyn/Eval/scenarios/03-multi-entity/*.yaml (10)
tests/Feature/Fyn/Eval/scenarios/04-handoffs/*.yaml (5)
tests/Feature/Fyn/Eval/scenarios/05-cancel-timeout/*.yaml (3)
tests/Feature/Fyn/Eval/scenarios/06-prompt-injection/*.yaml (10)
tests/Feature/Fyn/Eval/scenarios/07-regulatory/*.yaml (5)
tests/Feature/Fyn/Eval/scenarios/08-provider-parity/*.yaml (4)
tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/*.yaml (10)
config/fyn_eval.php
```

### Modified files
```
app/Agents/CoordinatingAgent.php (17 handlers rewritten + handleDeleteRecord two-phase + handleUpdateRecord allowlist + audit-chain call site)
app/Traits/HasAiChat.php (generateTitle sanitisation + summariseToolResult preserve entity_id + connection_aborted polling + persist-message clean-up)
app/Traits/HasAiGuardrails.php (atomic token budget replaces Cache::remember)
app/Services/AI/AdvicePromptBuilder.php (known_facts block + signposting + user-content wrapping)
app/Services/AI/AiToolDefinitions.php (update_record oneOf + 3 billing tools)
app/Services/AI/XaiToolDefinitions.php (parity: add 4 missing tools + strict mode on update_record + 3 billing tools)
app/Services/AI/Prompts/CoreIdentity.php (remove "qualified financial planner" framing)
app/Services/Onboarding/OnboardingChatDirector.php (add handleInlineCapture; existing resumeSummary reused)
app/Services/Onboarding/OnboardingPromptBuilder.php (known_facts block)
app/Http/Controllers/Api/AiChatController.php (two-way dispatch; consent check; idempotency middleware)
app/Http/Controllers/Api/AdminController.php (remove Cache::forever('ai_provider')—replace with versioned)
app/Models/AiConversation.php (new fillable + casts for index columns)
app/Providers/AppServiceProvider.php (remove FynPersonaOrchestrator bindings; add AdviceFyn binding)
app/Providers/EventServiceProvider.php (observers for direct-writes — verify existing)
app/Http/Kernel.php (register IdempotencyKeyMiddleware; remove agent.token)
app/Console/Kernel.php (schedule AiAuditRetentionJob, AiIdempotencyCleanupJob, ConversationSummariserJob weekly integrity-check)
config/onboarding.php (add journey_map)
config/services.php (remove openai block)
resources/js/store/modules/aiChat.js (remove persona_state_change + personaMode + placeholder swap; add advice_response handler; consent_required handler)
resources/js/components/Shared/AiChatPanel.vue (remove capturing pill; render advice_response via AdviceResponsePanel; static input placeholder)
resources/js/components/Shared/AiMessageContent.vue (optional: render advice_response in message stream)
resources/js/components/Admin/AiAudit.vue (new chain-view tab)
routes/api.php (remove /api/internal/agent/*; add idempotency middleware to /messages route)
.env.example (add AI_AUDIT_HMAC_KEY; remove AGENT_INTERNAL_TOKEN, OPENAI_*)
```

### Deleted files
```
app/Services/AI/FynPersonaOrchestrator.php
app/Services/AI/FynPersonaInvoker.php
app/Services/AI/FynPersonaRegistry.php
app/Services/AI/Prompts/DataCapturePromptBuilder.php
config/fyn_personas.php
app/Http/Controllers/Api/AgentInternalController.php
app/Http/Middleware/AgentTokenAuth.php
scripts/fynla_agent/
scripts/run_agent.py
scripts/requirements.txt
tests/Unit/Services/AI/FynPersonaInvokerTest.php
tests/Unit/Services/AI/FynPersonaOrchestratorTest.php
tests/Unit/Services/AI/FynPersonaRegistryTest.php
tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php
tests/Unit/Services/AI/AdvicePromptBuilderPersonaSplitTest.php
tests/Feature/AI/PersonaSplit/CancelMidCaptureTest.php
tests/Feature/AI/PersonaSplit/CaptureTimeoutTest.php
tests/Feature/AI/PersonaSplit/ClassifierFastPathTest.php
tests/Feature/AI/PersonaSplit/PreviewModeTest.php
tests/Feature/AI/PersonaSplit/KycGateFlowTest.php
```

### Ported (moved + renamed)
```
tests/Feature/AI/PersonaSplit/CreateWillToolTest.php → tests/Feature/Fyn/CreateWillToolTest.php
tests/Feature/AI/PersonaSplit/CreatePowerOfAttorneyToolTest.php → tests/Feature/Fyn/CreatePowerOfAttorneyToolTest.php
tests/Feature/AI/PersonaSplit/InlineCaptureFlowTest.php → tests/Feature/Fyn/InlineCaptureFlowTest.php
tests/Unit/ValueObjects/CaptureContextTest.php (unchanged location; VO is kept)
```

---

*Spec prepared 24 April 2026. Source of truth: canonical §0. Implementation-plan decomposition in §3 Sprint 0 is TDD-structured with exact file paths and code; Sprint 1–4 are scoped at the task-headline level and get their own plans when Sprint 0 lands. Every invariant in §2 is falsifiable via the named test; every claim about the current system in §1 is anchored to a file:line in the three audit docs. Mirror to vault on approval.*
