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

# Fyn v2 — Current system on `feature/fyn-persona-split`

> **BRANCH: `feature/fyn-persona-split`.** Everything described here is on this branch. None of it is on `main` unless explicitly noted. Commit drift at spec time: branch is **68 ahead of** and **179 behind** `origin/main`. Sprint 0 Task 0.1 rebases before any other work.

This document is the "what is true" side of the spec. Every claim is anchored to a file:line on the branch and traceable to `audit-evidence.md`. The "what must become true" side lives in [`01-invariants.md`](01-invariants.md).

---

## 1. Two code paths (not two Fyns)

The branch implements a **three-persona** architecture that the canonical contract rejects. The spec collapses this to two Fyns in Sprint 0.

- **Onboarding path.** `app/Services/Onboarding/OnboardingChatDirector.php:1-1985` (1,985 LOC). State-machine driven, bubble-emitting, direct-writes user/family state via services (`SpouseLinkingService`, `HouseholdProvisioner`). Activated when `users.onboarding_completed = false`.
- **Post-onboarding persona path.** `app/Services/AI/FynPersonaOrchestrator.php:1-415` (415 LOC) + `app/Services/AI/FynPersonaInvoker.php:1-518` (518 LOC) + `app/Services/AI/FynPersonaRegistry.php:1-104` (104 LOC) + `app/Services/AI/Prompts/DataCapturePromptBuilder.php:1-110` (110 LOC). Three-persona machinery (`advice`, `data_capture`, handoff contract). Behind feature flag `FYN_PERSONA_SPLIT` (default false per `config/fyn.php`).
- **Default legacy path.** `CoordinatingAgent::chat` via `HasAiChat` trait — single-prompt, pre-split behaviour.

Dispatch today: `AiChatController::sendMessage` at `app/Http/Controllers/Api/AiChatController.php` checks `user.onboarding_completed` and `config('fyn.persona_split_enabled')`, routes to one of the three paths.

---

## 2. Files that do not exist on `main` but do on this branch

Everything in this list must be handled by Sprint 0 (deleted, renamed, or kept):

- `app/Services/Onboarding/OnboardingChatDirector.php` — kept, extended with `handleInlineCapture`.
- `app/Services/Onboarding/OnboardingStateMachine.php` — kept (713 LOC).
- `app/Services/Onboarding/OnboardingValueInterpreter.php` — kept (324 LOC).
- `app/Services/Onboarding/OnboardingFactExtractor.php` — kept (286 LOC).
- `app/Services/Onboarding/AssetCaptureEntityExtractor.php` — kept (665 LOC); its emission logic is rewired into `handleInlineCapture` per Sprint 0.3 Step 4.
- `app/Services/Onboarding/SpouseLinkingService.php` — kept (367 LOC), already does exactly what §0 describes for spouse flow.
- `app/Services/Onboarding/HouseholdProvisioner.php` — kept (61 LOC).
- `app/Services/AI/FynPersonaOrchestrator.php` — **deleted** in Sprint 0.3.
- `app/Services/AI/FynPersonaInvoker.php` — **deleted** in Sprint 0.3.
- `app/Services/AI/FynPersonaRegistry.php` — **deleted** in Sprint 0.3.
- `app/Services/AI/HandoffContract.php` — kept (constants only).
- `app/Services/AI/Prompts/DataCapturePromptBuilder.php` — **deleted** in Sprint 0.3.
- `app/Services/AI/Prompts/EmptyDataGuard.php` — kept.
- `app/ValueObjects/CaptureContext.php` — kept (VO for inline-capture context).
- `config/fyn_personas.php` — **deleted** in Sprint 0.3.
- `config/fyn.php` — kept (other Fyn config beyond the split flag).
- `config/onboarding.php` — kept, extended with `journey_map` per INV-2.2.5.

---

## 3. Tool surface

### 3.1 Count by provider

- **Anthropic** (`app/Services/AI/AiToolDefinitions.php::getTools`): **37 tools**.
- **xAI** (`app/Services/AI/XaiToolDefinitions.php::getTools`): **33 tools**.

xAI is missing: `capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `create_holding`. Runtime provider flip silently loses 4 tools — onboarding's capture flow breaks if admin switches to xAI mid-conversation.

Target state (Sprint 0.6 + 0.7): both providers carry an identical 40-tool catalogue (37 existing + 3 new billing tools).

### 3.2 fill_form vs direct-write today

**17 handlers return `['action' => 'fill_form', ...]`** — pre-fill a frontend form, do not write to DB. Lines in `app/Agents/CoordinatingAgent.php`: 1510, 1549, 1595, 1742, 1809, 1887, 2018, 2065, 2132, 2165, 2205, 2244, 2861, 2923, 2978, 3021, 3142. Handler names: `handleCreateGoal`, `handleCreateLifeEvent`, `handleCreateSavingsAccount`, `handleCreateInvestmentAccount`, `handleCreateHolding`, `handleCreatePension`, `handleCreateProperty`, `handleCreateMortgage`, `handleCreateProtectionPolicy`, `handleCreateEstateAsset`, `handleCreateEstateLiability`, `handleCreateEstateGift`, `handleCreateFamilyMember`, `handleCreateTrust`, `handleCreateBusinessInterest`, `handleCreateChattel`, `handleUpdateRecord` (edit-mode fill_form).

**13 handlers write directly today** — `capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `capture_work_details`, `handleCreateWill`, `handleUpdateWill`, `handleCreatePowerOfAttorney`, `handleUpdatePowerOfAttorney`, `handleUpdateRecord` (non-fill_form path), `handleDeleteRecord`, `handleSetExpenditure`, `update_profile`, `create_what_if_scenario`.

Target state (Sprint 0.5): 16 of the 17 fill_form handlers convert to direct-write. `create_what_if_scenario` retains analytics-write behaviour as the single remaining fill_form exception (INV-2.5.6).

---

## 4. Recommendation engine (the canonical one — reused, not replaced)

### 4.1 Entry point

`CoordinatingAgent::orchestrateAnalysis(int $userId)` at `app/Agents/CoordinatingAgent.php:158-219`.

### 4.2 Pipeline order

1. `collectModuleAnalysis()` — parallel calls to all 7 module agents' `analyze()`:
   - `ProtectionAgent`, `SavingsAgent`, `InvestmentAgent`, `RetirementAgent`, `EstateAgent`, `GoalsAgent`, `TaxOptimisationAgent`.
2. `CashFlowCoordinator::calculateAvailableSurplus()`.
3. `extractRecommendations()` — merges module outputs.
4. `ConflictResolver::identifyConflicts()` — cashflow / ISA allowance / protection-vs-savings.
5. `resolveConflicts()` — 60/40 weighting when both critical.
6. `PriorityRanker::rankRecommendations()` — `(urgency × 0.4) + (impact × 0.3) + (ease × 0.2) + (user_priority × 0.1)`.
7. `CashFlowCoordinator::optimizeContributionAllocation()`.
8. `CrossModuleStrategyService::generateCrossModuleStrategies()`.

### 4.3 Return shape

```
{
  user_id, analysis_date,
  module_analysis: {protection, savings, investment, retirement, estate, goals},
  available_surplus,
  conflicts: [{type, demands, severity}, ...],
  ranked_recommendations: [...],   // sorted by priority_score desc
  cashflow_allocation,
  shortfall_analysis,
  cross_module_strategies,
  summary: {total_recommendations, conflicts_identified, ...}
}
```

### 4.4 HolisticPlanner layer

`app/Services/Coordination/HolisticPlanner.php::createHolisticPlan` adds on top:

```
{
  executive_summary: {overview, key_strengths, key_vulnerabilities, top_priorities, health_status},
  financial_snapshot,
  net_worth_projection: {current_net_worth, baseline_projections, optimized_projections, improvement, improvement_percent},
  risk_assessment: {risk_areas: [{area, severity, description}, ...]},
  module_summaries,
  ranked_recommendations,
  action_plan: {immediate, short_term, medium_term, long_term},
  action_plan_summary,
  cashflow_allocation,
  shortfall_analysis,
  conflicts
}
```

### 4.5 Investment deep-dive

`app/Services/Investment/Recommendation/` — 10 services composing a waterfall:

- `UserContextBuilder` — assembles age, tax band, risk tolerance, household.
- `DataReadinessService` — validates data completeness (`can_proceed`, `completion_percent`, `blocking`, `warnings`).
- `SafetyCheckService` — adjusts surplus for employer-match cliffs, life-event reserves, liquidity locks.
- **`ContributionWaterfallService` — CORE orchestrator.** 9-step sequential allocation: LISA → S&S ISA → Pension → Premium Bonds → NS&I → Offshore Bond → Onshore Bond → Pension Carry Forward → VCT/EIS/SEIS → GIA.
- `LifeEventAssessmentService` — blocks/prioritises wrappers based on upcoming life events.
- `GoalAssessmentService` — blocks wrappers creating lock-in when a goal is near-term.
- `ConflictResolutionService` — merges waterfall + goal + life-event modifiers.
- `TransferRecommendationService` — scans holdings for consolidation, fee reduction, tax-loss harvesting.
- `SpouseOptimisationService` — shifts assets to lower-tax-band spouse.
- `RecommendationOutputFormatter` — packages for API response.

### 4.6 Persistence

`RecommendationTracking` model (`app/Models/RecommendationTracking.php`) persists ranked recommendations with lifecycle `pending → in_progress → completed/dismissed`. Written by `HolisticPlanningController::plan` on fresh plan generation (not cache hits).

### 4.7 Template library

Per-module `ActionDefinition` models + services:

- `EstateActionDefinition` + `EstateActionDefinitionService`
- `InvestmentActionDefinition` + `InvestmentActionDefinitionService`
- `ProtectionActionDefinition` + `ProtectionActionDefinitionService`
- `RetirementActionDefinition` + `RetirementActionDefinitionService`
- `SavingsActionDefinition` + `SavingsActionDefinitionService`
- `TaxActionDefinition` + `TaxActionDefinitionService`

Each ActionDefinition row: `key` (unique), `source` (agent|goal), `title_template`, `description_template`, `action_template`, `category`, `priority`, `scope`, `trigger_config` (JSON), `is_enabled`, `sort_order`.

### 4.8 Per-recommendation personalisation

`app/Services/Coordination/RecommendationPersonaliser.php` adds a `personalised_context` bullet list per recommendation (family composition, IHT thresholds, asset allocation drift) without changing the raw recommendation text.

---

## 5. Memory surfaces (3 exist, index absent)

- **Store 1 — authoritative DB user state.** `users.*` (first_name, surname, marital_status, dob, employment, income, `onboarding_*` state), `family_members` (spouse + dependants, bidirectional per `SpouseLinkingService`), linked module tables (`savings_accounts`, `investment_accounts`, `dc_pensions`, `db_pensions`, `protection_*` policies, `properties`, `mortgages`, `trusts`, `wills`, `lasting_powers_of_attorney`, `estate_assets`, `estate_liabilities`, `estate_gifts`, `chattels`, `business_interests`, `goals`, `life_events`).
- **Store 2 — current-turn parked facts.** `ai_conversations.onboarding_parked_facts` JSON column, written by `OnboardingFactExtractor`. Migration: `database/migrations/2026_04_22_000003_add_onboarding_parked_facts_to_ai_conversations.php`.
- **Store 3 — current-conversation message history.** `ai_messages` rows, loaded via `HasAiChat::buildMessageHistory` at `app/Traits/HasAiChat.php:679`.
- **Index — absent.** `ai_conversations` columns today: `id`, `user_id`, `title`, `status`, `model_used`, `metadata`, `persona_state`, `onboarding_parked_facts`, `message_count`, `last_message_at`, timestamps, soft-delete. No `summary`, no `topics`, no `entities_mentioned`, no `intents_stated`. No observer or job populates such columns. No tool exposes an index scan to Fyn.

Sprint 1 introduces the index per INV-2.11.2 (Option A: columns on `ai_conversations`).

---

## 6. Visible-handoff leak (direct canonical violation)

- `persona_state_change` SSE event emitted from `app/Services/AI/FynPersonaOrchestrator.php::personaStateChangeEvent` at lines 382-388.
- Consumed in Vuex at `resources/js/store/modules/aiChat.js:511-516`:
  ```js
  case 'persona_state_change':
      commit('SET_PERSONA_MODE', event.current);
      break;
  ```
- `SET_PERSONA_MODE` swaps `state.personaMode` between `'advice'` and `'capturing'`.
- `resources/js/components/Shared/AiChatPanel.vue` reads `personaMode` to swap the input placeholder and render a capturing pill.

All of the above is removed in Sprint 0.4 per INV-2.4.1.

---

## 7. Reliability gaps

- **No SSE abort detection.** `grep -rn "connection_aborted\|ignore_user_abort" feature/fyn-persona-split -- 'app/'` → zero matches.
- **Token budget race.** `Cache::remember($cacheKey, 300, ...)` at `app/Traits/HasAiGuardrails.php:221`. 5-minute TTL window.
- **Provider-swap coherence.** `Cache::forever('ai_provider', ...)` in `app/Http/Controllers/Api/AdminController.php`. Admin toggle mid-loop mixes Anthropic cache markers into xAI requests.
- **Gap-fill double-insert on retry.** `AssetCaptureEntityExtractor::findMissing` has no DB lookup against existing records before synthesising fill-in events.
- **Synchronous document extraction.** `AIExtractionService` at `app/Services/Documents/AIExtractionService.php:1-965` runs inside the web request. No wrapping Job (`ls app/Jobs/ | grep -i extract` → zero). Text-based PDFs have no size cap (`MAX_SCANNED_PDF_SIZE = 15 MB` at line 31 applies only to scanned PDFs; enforcement at line 783).
- **`generateTitle` sanitation.** `HasAiChat::generateTitle` at line 704 sends raw user text to LLM; only `mb_substr` truncation. No `strip_tags`, no HTML escape.
- **Asymmetric provider timeouts.** `XaiClient.php:64` = 120s; Anthropic path at `HasAiChat.php:287-305` uses SDK defaults.

Sprint 0.11 closes all of the above.

---

## 8. Audit trail today

- **File-only tool-execution log.** `app/Agents/CoordinatingAgent.php:770` — `Log::channel('single')->info('[AI-AUDIT] Tool executed', [...]);`. Gating condition at line 768: `if (str_starts_with($toolName, 'create_') || in_array($toolName, ['update_record','delete_record','update_profile']))`. Reads (list_*, get_*) are NOT logged.
- **DB surfaces (partial audit coverage):**
  - `ai_messages` with `system_prompt` snapshot, `metadata.cached_tokens`, `metadata.cache_hit_rate` persisted at `app/Traits/HasAiChat.php:569-572`.
  - `ai_advice_logs` (`app/Models/AiAdviceLog.php`, migration `database/migrations/2026_04_01_150000_create_ai_advice_log_table.php`) with `tools_called` at `HasAiChat.php:612`, `user_data_snapshot`, `classification`, `kyc_status`.
- **Truthfulness gap** (`audit-evidence.md §18`): `[AI-AUDIT]` and `ai_advice_logs.tools_called` both fire BEFORE the frontend form submit. For the 17 fill_form handlers, both log "tool executed" even when the record never persists.
- **Row-mutable, no chain.** `ai_messages` and `ai_advice_logs` are normal Eloquent tables; post-hoc edits are not detectable.

Sprint 0.12 introduces `ai_audit_events` with hash chain + HMAC signing per INV-2.10.2.

---

## 9. Compliance posture today

- **CoreIdentity misaligned.** `app/Services/AI/Prompts/CoreIdentity.php` contains verbatim: *"You think like a qualified financial planner — you understand UK tax rules, income classifications, investment wrapper implications, pension allowance calculations, estate planning strategies, and protection needs analysis."* Misaligned with CSJ decision 1 (guidance-only posture).
- **ConsentService not runtime-checked.** `app/Services/GDPR/ConsentService.php::hasConsent` exists. `grep -rn hasConsent app/Http/Controllers/Api/AiChatController.php app/Traits/HasAiChat.php` → zero matches.
- **`update_record` over-exposure.** 2-field blocklist (`user_id`, `id`) at `app/Agents/CoordinatingAgent.php:3134`. `fields` schema is `additionalProperties: true` on Anthropic; xAI `strict: false`. LLM can mutate `Trust.settlor`, `Mortgage.start_date`, `FamilyMember.relationship` — any of which have side-effects the user never consents to.
- **Privacy Policy contradictions.** `resources/js/views/Public/PrivacyPolicyPage.vue`:
  - §5 line 111 — *"We do not share health data with any third party."*
  - §7 line 132 — *"We do not use third-party analytics or tracking services."*
  Code reality: Meta Pixel (unconditional, `resources/views/app.blade.php:80-89`, merchant ID `1878962689749080`), AWIN (env-gated, `resources/js/utils/awinTracking.js` + 4 other files), Plausible (config-gated, `app.blade.php:71-73`), xAI undisclosed chat provider, Anthropic under-disclosed (scoped to document extraction only in §7 line 124).

Sprint 0 closes CoreIdentity + ConsentService runtime + update_record allowlist. Sprint 4 handles Privacy Policy rewrite (external-legal calendar).

---

## 10. Frontend render surface

SSE event types the current Vuex store handles (`resources/js/store/modules/aiChat.js` switch block):

1. `content` — streamed text fragment.
2. `title` — conversation title.
3. `navigation` — queued navigation card.
4. `fill_form` — hand to `aiFormFill` store, opens form modal.
5. `entity_created` — success badge.
6. `quick_replies` — render bubble buttons.
7. `onboarding_advance` — debug log only.
8. `onboarding_layout_change` — wide/standard layout swap.
9. `persona_state_change` — **violates §0, removed in Sprint 0.4**.
10. `capture_complete` — record-card bubble.
11. `handoff` — debug log (stripped by invoker, should never reach frontend).
12. `skip_link` — skip affordance.
13. `preview_cta` — signup CTA.
14. `onboarding_complete` — terminal state navigation.
15. `token_limit` — budget reset time.
16. `error` — error display.
17. `done` — finalise assistant message.
18. `conversation_created` — store conversation ID.
19. `resume` — resume mid-flow.

Sprint 0 removes `persona_state_change`. Sprint 1 adds `advice_response` (per INV-2.3.5) and `consent_required`. Sprint 2 keeps the set stable.

---

## 11. Rubric-A baseline (from `fyn-rubrics.md §A`)

**4-5/40 — 🔴 Pre-launch** at spec time. Dimension-by-dimension:

| Dim | Level | Evidence (abbreviated) |
|---|---|---|
| D1 Regulatory | 1 | `CoreIdentity.php` "qualified financial planner" framing; hedging in Layer 2 but no legal opinion. |
| D2 Data protection | 0 | No ROPA/DPIA; Privacy Policy §5/§7 contradicts code. |
| D3 Consent | 1 | `ConsentService::hasConsent` exists but zero chat-flow callers. |
| D4 Audit | 0-1 (scoring choice) | File-only `[AI-AUDIT]` tool log; DB surfaces partial; no hash chain. |
| D5 LLM safety | 0 | 2-field blocklist; `additionalProperties: true`; user-controlled prompt fields raw. |
| D6 Reliability | 0 | No abort detection; 5-min cache race; no dedup. |
| D7 Provider risk | 0 | xAI not in Privacy Policy; no org cap; no failover. |
| D8 Code quality | 1 | Tests exist; `CoordinatingAgent.php` ~3,500 LOC; `OnboardingChatDirector.php` 1,985 LOC. |
| D9 Observability | 0 | No eval harness. File-only audit. |
| D10 Documentation | 1 | System-map accurate for main §1-20; §21 errors; no DPIA/ROPA. |

Full expected trajectory through the sprints in [`01-invariants.md §verification`](01-invariants.md).

---

## 12. Branch state at spec time

- Tip: `f4b0b89 docs(session): end-of-day handover 2026-04-23-session-1` (or whatever the latest at pickup time is — verify with `git log -1 feature/fyn-persona-split`).
- Commits ahead of `main`: 68.
- Commits behind `main`: 179.
- Working tree: verify clean before starting Sprint 0. The audit docs have been extensively edited; no new code in this spec pass.
- Test suite: `./vendor/bin/pest` expected 2,448 passing + 1 known flake (`AutoRiskCalculatorTest`) per CSJTODO. Confirm this post-rebase in Sprint 0 Task 0.2.

---

*This document describes the system as it exists on `feature/fyn-persona-split` right now. Every claim is citable via `audit-evidence.md`. The target state — what must become true — is in [`01-invariants.md`](01-invariants.md). The TDD decomposition that gets us from here to there is in the sprint plans (`10-sprint-0-plan.md` onward).*
