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

# Plan — `11-sprint-1-plan.md` (Sprint 1: eval harness + memory model + `<known_facts>`)

> **Canonical contract:** [`../spec/00-canonical.md`](../spec/00-canonical.md).
> **Branch:** all implementation commits on `feature/fyn-persona-split` (or `feature/csj/sprint1-<subtask>` off it). Sprint 1 starts only after Sprint 0 merged.
> **Sources:**
> - Source spec: [`../spec/11-sprint-1-plan.md`](../spec/11-sprint-1-plan.md)
> - Audit evidence: [`../audit-evidence.md`](../audit-evidence.md)
> - Audit synthesis: [`../audit-synthesis.md`](../audit-synthesis.md)
> - Rubrics: [`../fyn-rubrics.md`](../fyn-rubrics.md)
> - **Eval expectations rewrite (2026-04-27, session 102):** [`../../April27Updates/eval-expectations-rewrite.md`](../../April27Updates/eval-expectations-rewrite.md) — line-by-line rewrite of every scenario YAML against the actual app behaviour, plus 48 new YAMLs (canonical-behaviour, state-machine, handoff, resume). **Drives S1.2.l + the expanded S1.7 below.** Read end-to-end before touching any YAML.
> - Companion April27 audits: [`../../April27Updates/eval-system-vs-live-flow-audit.md`](../../April27Updates/eval-system-vs-live-flow-audit.md), [`../../April27Updates/fixEvalTask.md`](../../April27Updates/fixEvalTask.md), [`../../April27Updates/system-prompt-audit.md`](../../April27Updates/system-prompt-audit.md), [`../../April27Updates/CSJTODO.md`](../../April27Updates/CSJTODO.md).

**Goal (per source spec):** Ship Rubric B Mode 1 eval harness + first 30 scenarios + memory model (3 stores + 1 index + `<known_facts>` + `search_conversation_index`) + `advice_response` SSE + `AdviceResponsePanel.vue`. End state Rubric-A 17-18/40 🟠 Limited beta.

**Pre-flight gate:** Sprint 0 merged; `./vendor/bin/pest` green; Rubric-A ≥13/40. If any fails, stop.

---

## Status (updated 2026-04-27)

Tick each entry as the commit lands on `feature/fyn-persona-split`. Commit SHA + short subject for traceability. One line per task — no narrative here; delivery notes belong in the task section itself.

- [x] **S1.1** — Eval harness scaffold + 9 scenario category dirs + architecture meta-tests · `30ca5fa` (`feat(eval): Sprint 1 S1.1 — harness scaffold + scenario category directories`)
- [ ] **S1.2** — First 10 scenarios (6 query-type + 4 multi-entity) — YAMLs AUTHORED + tooling SHIPPED + 1 of 10 fixtures captured; remaining 9 deferred (S1.2.k) until S1.6 lands the output-shape contract
  - [x] S1.2.a — Author 6 query-type YAMLs · `0bb878c` (`feat(eval): Sprint 1 S1.2 — 10 scenario YAMLs (fixtures pending)`)
  - [x] S1.2.b — Author 4 multi-entity YAMLs · `0bb878c` (same commit)
  - [x] S1.2.c — Model-aware fixture paths · `7fe4a8c` (`feat(eval): model-aware fixture paths + 3 handoff scenarios`)
  - [x] S1.2.d — `eval:record` artisan command · `2bd99f2` (`feat(eval): eval:record artisan command for fixture capture`)
  - [x] S1.2.e — Both-providers-per-scenario + side-by-side compare · `4ff2826` (`feat(eval): record both providers per scenario + side-by-side comparison`)
  - [x] S1.2.f — Single shared eval user across providers (savepoint isolation) · `c133dde` (`feat(eval): single shared user across both providers (savepoint isolation)`)
  - [x] S1.2.g — Forensic recording store (eval_users + sessions + provider_runs) · `6c703df`, FK fix `a09853c`
  - [x] S1.2.h — Admin viewer + delta report + tool-name capture fix (extra scope on top of plan) · `6649b1a` (`feat(eval): admin viewer + delta report + tool name capture fix`)
  - [x] S1.2.i — Eval/live prompt-divergence fix (Tasks 1, 2, 3, 3b: delete `EmptyDataGuard` branch + classification-gate `<billing_guidance>` + `EvalRecordCommand::seedUser` schema validator + universal KYC seed fields on all six query-type YAMLs) · `279bd9b` (`fix(ai): eval/live prompt divergence — Tasks 1, 2, 3, 3b`)
  - [x] S1.2.j — First fixture recorded live, both providers GREEN-on-tools (eval session #18, `advice_protection_cover`, 2026-04-27) — captured prompt verified: `<financial_context>` + `<existing_records>` + `<data_completeness>` PRESENT, `<new_user_state>` + `<billing_guidance>` ABSENT, both providers call `get_module_analysis`. Recording exposed the output-shape contract gap below; no further fixtures recorded this session.
  - [ ] S1.2.k — Re-record remaining 9 fixtures (`advice_savings_emergency`, `advice_investment_isa`, `advice_retirement_contribution`, `advice_estate_iht`, `advice_goals_affordability`, `protection_2x_known_providers`, `protection_2x_unknown_providers`, `savings_3x_mixed`, `pensions_2x_schemes`) — **DEFERRED again** (session 102, 2026-04-27). Session #20 recording of `advice_protection_cover` against the per-agent contract (S1.6.b) surfaced that the YAML EXPECTATIONS themselves are wrong against the actual app behaviour: tool lists contradict `QuerySchemas::REQUIRED_TOOLS`; `expected_advice_response` references the SSE event removed in S1.6.a; `timing_budget_ms` is path-blind; classification is asserted single-string but classifier is multi-label; readiness gates and per-agent profile gates (ProtectionAgent line 72, RetirementAgent line 101) are unsaid. Full diagnosis + line-by-line rewrite in [`../../April27Updates/eval-expectations-rewrite.md`](../../April27Updates/eval-expectations-rewrite.md). **Re-recording is now blocked on S1.2.l (rewrite the 10 YAMLs) and S1.7.a-S1.7.f (asserter + dashboard + meta-tests) per the rewrite report Section 9.**
  - [ ] S1.2.l — **NEW (session 102)**: Rewrite the 10 existing YAMLs against the rewrite report Section 4 + Section 5. Per-scenario: replace `expected_classifications: [single]` with `expected_classification_shape: {primary, related, modules}`; add `expected_response_mode` + `expected_engine_call_level` + `expected_kyc_state` (+ `expected_kyc_missing` when blocked); replace flat `expected_tool_calls` with the merged `QuerySchemas::getRequiredToolsForClassification` list including `IMPLICIT_RELATED`, with per-tool `result_path` enum (`success_false` / `readiness_blocked` / `empty_state` / `happy`); delete `expected_advice_response`; add `expected_assistant_text` (must_contain INV-2.3.3 signposting on recommendation mode, must_not_contain forbidden phrases); restructure `expected_sse_events` to `must_contain_types` + `must_emit_exactly_once` + `must_not_emit` + tool_use count bounds; replace flat `timing_budget_ms` with per-provider per-path map. Verify each rewritten YAML against `php artisan tinker --execute="dump(app(QueryClassifier::class)->classify('<message>'));"` AND `KycGateChecker::check` for the seeded user before commit. Acceptance: all 6 advice + 4 multi-entity YAMLs parse; rewrite-report Section 8 architecture meta-tests (S1.7.b) green.
- [x] **S1.3** — Conversation index schema + summariser job — migration adds 5 columns (`summary`, `topics`, `entities_mentioned`, `intents_stated`, `summarised_at` + index) · `ConversationSummariser` service (xAI grok-4-1-fast-non-reasoning, structured `response_format: json_object`) · `ConversationSummariserJob` dispatched on `STATE_DONE` from `OnboardingChatDirector::emitDoneTurn` and via `ai:conversations:summarise-stale` artisan command scheduled every 30 minutes · resume-contract carve-out in the scheduler: skips conversations whose owner has `onboarding_completed = false` AND `metadata.source = 'fyn_onboarding'` so an idle mid-flow onboarding never gets summarised before the user resumes (canonical Two-Fyn §0 "picks up from where they left off") · 8/8 Pest tests in `tests/Feature/AI/ConversationIndexPopulationTest.php` (populates index, job wiring, no-op without API key, absorbs non-JSON responses, emitDoneTurn dispatch via reflection, scheduler picks stale + skips in-flight + skips already-summarised, scheduler carve-out for in-flight onboarding, resume payload preserved even with stale summary written to the row)
- [x] **S1.4** — `MemoryRetrieverService` + `<known_facts>` block — service with strict gap-fill fall-through across 4 layers (authoritative DB → parked facts → current conversation extractor re-run → conversation index from prior summarised conversations) · `renderKnownFactsBlock` produces canonical `<known_facts>\n- key: json_value\n…\n</known_facts>\n\nDo not ask the user for any field above.` · injected into `OnboardingPromptBuilder::buildAssetCapturePrompt` (asset_capture turns), `OnboardingChatDirector::buildGroupedExtractPrompt` (grouped_extract turns), `AdvicePromptBuilder::build` Layer 3d (advice turns) · 11/11 Pest in `tests/Unit/Services/AI/MemoryRetrieverServiceTest.php` (each layer + fall-through priority + active-conversation exclusion + render shape) · 4/4 Pest in `tests/Unit/Services/Onboarding/KnownFactsBlockTest.php` (asset_capture + advice both inject every field + base_spouse no-repeat-ask pin) · eval scenario `tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/memory-no-repeat-ask.yaml` authored (recording deferred per S1.2.k)
- [x] **S1.5** — `search_conversation_index` tool — added to `AiToolDefinitions` (Anthropic, `additionalProperties: false`) + `XaiToolDefinitions` (xAI, `strict: true`) with `topic_keywords[]` + `entity_types[]` parameters · `CoordinatingAgent::executeTool` dispatches to new `handleSearchConversationIndex($input, $user, $activeConversationId)` — queries `AiConversation::forUser → whereNotNull('summary') → whereJsonContains('topics', …) | whereJsonContains('entities_mentioned', ['type' => …])` ordered by `last_message_at` desc, capped at 10, excludes active conversation · NOT in `AdviceFyn::WRITE_TOOLS` so naturally permitted in advice mode (read-only) · 10/10 Pest in `tests/Feature/AI/SearchConversationIndexTest.php` (topic + entity match, per-user ACL, skip un-summarised, exclude active, cap at 10, no-filter return-all, write-tools absence pin, both provider definitions registered) · eval scenario `tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/cross-conversation-surface.yaml` authored (recording deferred per S1.2.k)
- [~] **S1.6** — per-agent tool-result contract SHIPPED · `advice_response` SSE + `AdviceResponsePanel.vue` REMOVED in session 101
  - [x] **S1.6.a — REMOVED (session 101).** Browser verification surfaced that the panel duplicated information already present in the LLM text bubble (same figures, same recommendations, same signposting). The "every component must justify its existence" review found the panel could only justify itself by replacing the LLM prose entirely (saving tokens + latency), which would require a non-trivial AdvicePromptBuilder change and a fresh eval-recording cycle. CSJ chose removal over justification. Deleted: `AdviceResponseComposer.php`, `AdviceResponseSchema.php`, `AdviceResponseSchemaException.php`, `resources/js/components/Shared/AdviceResponsePanel.vue`, `tests/Feature/Fyn/AdviceResponseSseShapeTest.php`, the `wrapStream` `advice_response` emit block, the `case 'advice_response':` handler in `aiChat.js`, the panel branch + import in `AiChatPanel.vue`, and the `shouldEmitAdviceResponse` method on `AdviceFyn`. The eval YAMLs retain `expected_advice_response` keys but the controller already handles the absence gracefully (defaults to null) — they will be cleaned up when fixtures are re-recorded. The structured-output contract for tool results (S1.6.b) is the durable part of the work and remains in place.
  - [x] S1.6.b — Per-agent output contract (Phase 2): `app/Services/AI/ToolResultContract.php` enforces per-module required-keys against the unwrapped agent payload (happy-path / readiness-gate / agent-error / module-specific empty-state branches all explicitly handled) · `ToolResultContractException` carries `context`, `missingKeys`, `presentKeys` for traceable drift logging · `CoordinatingAgent::summariseToolAnalysis` rewritten to delegate to the contract and return verbatim — drift yields a structured `module_analysis_contract_violation` error tool result rather than a malformed shape · `extractKeyMetrics` 15-key whitelist DELETED · each module agent (`ProtectionAgent`, `SavingsAgent`, `InvestmentAgent`, `RetirementAgent`, `EstateAgent`, `GoalsAgent`) emits first-class `missing_for_quality_advice: [{field, why, severity}]` on the happy path with per-module gap rules (e.g. Protection blocks on `monthly_expenditure ≤ 0`, Retirement blocks on no pension records, Estate flags missing spouse-link or Will) · `StructuredResponseValidator` left in place — it scrubs LLM text output (a different layer from tool results); session 101 surfaced this as a CSJ-decision item rather than auto-removing it · 21/21 Pest in `tests/Unit/Services/AI/ToolResultContractTest.php` (15) + `tests/Feature/AI/ToolResultContractIntegrationTest.php` (6) covering schema acceptance/rejection, agent-error pass-through, verbatim payload preservation (no key-stripping), reflection round-trip through `summariseToolAnalysis`, and live agent → contract integration with seeded users
- [ ] **S1.7** — **EXPANDED (session 102)** — Eval scenario rewrite + bank expansion (target: 58 deliverable YAMLs). Source: [`../../April27Updates/eval-expectations-rewrite.md`](../../April27Updates/eval-expectations-rewrite.md) Sections 4, 6, 8, 9, 10, 11, 12. Originally "20 more scenarios → 30 total"; now 48 NEW + 10 rewrites + asserter + dashboard delta + meta-tests.
  - [ ] S1.7.a — Extend `tests/Feature/Fyn/Eval/AssertionHelpers.php` with new keys per rewrite report §3 (`expected_response_mode`, `expected_engine_call_level`, `expected_classification_shape`, `expected_kyc_state` + `expected_kyc_missing`, per-tool `result_path`, `expected_tool_calls_absent`, `expected_assistant_text` sub-block, `expected_orchestrate_analysis_called`, `expected_per_turn`, `expected_state_transition`, `expected_parked_facts`, `expected_quick_replies`, `expected_handoff_path`, `expected_db_writes`, `inherits` fragment-inheritance, `linked_browser_scenario`). Reject legacy `expected_advice_response` + single-int `timing_budget_ms` with deprecation message. Per-helper Pest unit tests. **Blocks every other S1.7 sub-task.**
  - [ ] S1.7.b — Add 6 architecture meta-tests per rewrite report §8: `EvalScenarioToolListMatchesQuerySchemasTest`, `EvalScenarioResponseModeConsistencyTest`, `EvalScenarioForbiddenToolsContainsAdviceWriteToolsTest`, `EvalScenarioKycBlockedHasAbsentToolsTest`, `EvalScenarioSignpostingMatchesResponseModeTest`, `EvalScenarioTimingBudgetIsPathAwareTest`. All under `tests/Architecture/`. Acceptance: all 6 green against the rewritten YAMLs.
  - [ ] S1.7.c — Author 4 new canonical-behaviour YAMLs per rewrite report §6: `advice_kyc_blocked_no_dob.yaml`, `advice_protection_profile_setup_handoff.yaml` (3 turns), `advice_holistic_health.yaml`, `advice_out_of_remit_medical.yaml`. All under `tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/`.
  - [ ] S1.7.d — Update `EvalProviderRun` model + `EvalRecordingController::buildDelta` per rewrite report §7. New columns: `kyc_state`, `kyc_missing`, `tool_result_paths`, `engine_call_level_actual`. New buildDelta heuristics: `result_path`-aware "missing tool", `expected_tool_calls_absent` detection, KYC-blocked correctness, out-of-remit exact-match. Vue dashboard panel for "Prompt readiness".
  - [ ] S1.7.e — Author 14 onboarding state-machine eval YAMLs per rewrite report §10: one per non-asset_capture state transition under `tests/Feature/Fyn/Eval/scenarios/02-preview-personas/onboarding_<state>.yaml`. Add `--mode=deterministic` flag to `EvalRecordCommand` so state-machine scenarios bypass LLM calls and assert SSE shape + DB writes against `OnboardingStateMachine::transition`. Acceptance: all 14 parse, deterministic mode runs all 14 without invoking a provider.
  - [ ] S1.7.f — Author 14 write-tool-family handoff YAMLs per rewrite report §11. One per `AdviceFyn::WRITE_TOOLS` family (savings, investment, pension, property, protection_policy, asset, liability, estate_gift, trust, family_member, will, lpa, goal, life_event). All under `tests/Feature/Fyn/Eval/scenarios/04-handoffs/`. Plus `_handoff_invariants.fragment.yaml` shared fragment carrying INV-2.4.x assertions; AssertionHelpers `inherits` support (S1.7.a) lets each scenario pull the fragment in. Cross-provider drift assertions: anthropic structured-tool-use vs xAI WriteIntentClassifier server-side detection — both observable in same final DB state.
  - [ ] S1.7.g — Author 16 resume-after-disconnect YAMLs per rewrite report §12: 13 per-state under `09-canonical-behaviour/resume/resume_at_<state>.yaml` + 3 edge cases (`resume_under_5_minutes_no_bubble`, `resume_after_onboarding_completed`, `resume_with_step_null_no_bubble`). Each YAML calls `OnboardingChatDirector::resumeSummary($stateId)` from the asserter and asserts substring equality. `linked_browser_scenario: BS-04` cross-references the existing browser scenario.
  - [ ] S1.7.h — Re-record all fixtures: 6 rewritten advice + 4 new canonical (where LLM-driven) + 5 LLM-driven onboarding states (base_personal, base_spouse, base_dependants_detail, base_employment, base_employment_more) + 14 handoff turn-2-and-3 (turn-1 deterministic). State-machine deterministic + resume scenarios bypass provider calls so no fixtures recorded. Existing 4 multi-entity fixtures re-record only if their light-touch updates per S1.2.l affect their playback.
  - [ ] S1.7.i — Author hard-gate verification doc `April/April27Updates/eval-rewrite-verification.md`: every YAML's `expected_classification_shape` verified via `php artisan tinker`; every `expected_kyc_state` via `KycGateChecker::check`; every `expected_state_transition.to` via `OnboardingStateMachine::transition`; every handoff `expected_handoff_path` observable in captured SSE. **Acceptance gate for S1.10.**
  - [ ] S1.7.j — File for follow-up (NOT this sprint): Mode-2 cron (Sprint 2 Task 2.16), holistic engine internals deep-dive (`April27Updates/orchestrate-analysis-deep-dive.md`), provider-parity scenarios (Sprint 2 category 08), prompt-injection scenarios beyond Sprint 1 starters.
- [x] **S1.8** — Advice Fyn response-mode classifier — `AdviceFyn::classifyResponseMode(string): 'factual'|'recommendation'|'out_of_remit'` + `AdviceFyn::engineCallLevel(string): 'holistic'|'module'|'factual'` static maps covering all 24 `QuerySchemas` constants · `RESPONSE_MODE_MAP` (18 recommendation, 5 factual, 1 out_of_remit) · `ENGINE_CALL_LEVEL_MAP` (1 holistic = HOLISTIC_HEALTH only, 17 module, 6 factual) · `shouldEmitAdviceResponse` rewired from `QuerySchemas::isAdviceType` to `classifyResponseMode === 'recommendation'` so `INCOME` (advice type but factual mode) no longer triggers an `advice_response` panel · 15/15 Pest in `tests/Unit/Services/AI/AdviceFynResponseModeTest.php` (9 cases, 51 assertions) + `tests/Unit/Services/AI/AdviceFynEngineCallLevelTest.php` (6 cases, 150 assertions) covering exhaustive constant coverage, per-constant correctness, invariant cross-checks (recommendation ↔ engine in {holistic, module}; factual ↔ engine factual), and the orchestrateAnalysis-reserved-for-holistic property
- [ ] **S1.9** — Sprint 1 Playwright matrix (BS-03 / BS-08 / BS-09 / BS-24 + regression of BS-01 to BS-23) — NOT STARTED. **Note:** BS-09's `advice_response` SSE assertion is now a no-op per S1.6.a removal. The remaining BS-09 contract (recommendation-mode advice with FCA signposting in the assistant text) survives intact and gets cross-referenced by `09-canonical-behaviour/advice_protection_profile_setup_handoff.yaml` (S1.7.c) and the resume scenarios via `linked_browser_scenario` (S1.7.g).
- [ ] **S1.10** — Sprint 1 verification rollup — NOT STARTED. **Hard-gated on:** S1.2.l + every S1.7 sub-task green + S1.7.i `eval-rewrite-verification.md` published. The original S1.10 acceptance ("Rubric-B Mode 1 at 30/30, Rubric-A 17-18/40 🟠") is superseded by the rewrite report §9.11 acceptance: every meta-test green, every YAML's classification/KYC/state-transition/handoff-path verified at authoring time, dashboard delta produces zero false-FAILs across the 58-scenario bank.

### Output-shape contract — S1.6 scope extension (added 2026-04-27)

Sprint 1 proceeds in plan order (S1.3 → S1.4 → S1.5 → S1.6 → S1.7 → S1.8 → S1.9 → S1.10). The only deferral is S1.2.k (re-record remaining 9 fixtures), which is picked up after S1.6 ships. This note documents WHY S1.6's scope is extended beyond the source spec, so the work isn't lost when S1.6's turn comes round.

Pre-recording inspection of the first scenario `advice_protection_cover` (eval session #18, both providers, 2026-04-27) surfaced that the "structured data everywhere" guarantee promised by prior sessions is not honoured at the response layer. Evidence:

- **Tool-result lossy summarisation.** `app/Agents/CoordinatingAgent.php::summariseToolAnalysis` (line 3298) + `extractKeyMetrics` (line 3320) silently drop everything outside a 15-key whitelist (`total_value, total_cover, coverage_gaps, net_worth, monthly_surplus, emergency_fund_months, pension_projection, iht_liability, total_savings, total_investments, retirement_income, target_income, shortfall, risk_score, asset_allocation, progress_percentage`). Whatever each agent computes beyond those 15 keys (gaps, missing-data lists, completeness ratios, dependency hints) is silently dropped before the LLM ever sees it.
- **Empty agent output.** `ProtectionAgent::analyze(505)` returned `data: []` for a user with one £100k Aviva life policy — the LLM saw `metrics: [], recommendations: []` and had to compose the missing-data ask from soft prompt cues alone. Separate bug, but it compounds the contract gap.
- **No response-shape validator.** `app/Services/AI/StructuredResponseValidator.php` is a regex scrubber for banned acronyms / jargon / leaked IDs, NOT a structure validator. Its own docblock at line 21: *"Returns violations array. Does NOT block the response — logs and flags. A future iteration could strip/rewrite violations before delivery."*
- **Cross-provider drift confirmed.** Anthropic Haiku 4.5 produced a bullet-structured "missing data" list for the user's question; xAI grok-4-1-fast-reasoning produced flat prose for the same prompt. Same input, same prompt, different shape — because shape is governed by soft cues (`<response_format>` block: *"Use bullet points for summaries"*), not a contract.

**CSJ direction (2026-04-27):** *"I, as repeatedly asked, DO NOT want to rely on the LLM, as they change, and structured data is critical to everything we do. Every tool, every response, every conversation has an element of structured response."* No further eval fixtures are to be recorded until the response-shape contract is real and enforced — hence S1.2.k deferred to after S1.6.

**S1.6 must therefore deliver, beyond the source-spec scope:**

1. **Per-agent output contract.** Each `Agent::analyze()` returns a typed array asserted by Pest, including a first-class `missing_for_quality_advice` field. Replaces the 15-key whitelist.
2. **Tool-result schema validation.** `summariseToolAnalysis` returns the agent output verbatim, schema-checked against the per-agent contract. No silent drops.
3. **`advice_response` SSE event** with `headline / key_figures / breakdowns / recommendations / next_steps / signposting` (already in source spec lines 146-156), populated from the structured agent output.
4. **`AdviceResponsePanel.vue`** renders the structured fields directly. The free-text bubble carries signposting only.
5. **Real schema validator** that BLOCKS non-conforming responses (replaces the post-hoc scrubber). Pest assertion on every recommendation-mode turn.

Once items 1–5 hold, S1.2.k can re-record the 9 remaining fixtures against the new contract, then plan order resumes with S1.7.

---

### Eval expectations rewrite — S1.7 scope extension (added 2026-04-27 session 102)

Sister note to "Output-shape contract — S1.6 scope extension" above. Same shape: documents WHY S1.7's scope expanded beyond the source spec so the work isn't lost when S1.7's turn comes round. Source: [`../../April27Updates/eval-expectations-rewrite.md`](../../April27Updates/eval-expectations-rewrite.md) (1690 lines, 14 sections).

S1.6.b shipped (per-agent output contract). Session 102 ran `php artisan eval:record advice_protection_cover` against the new contract to validate the recording infrastructure before re-recording the 9 deferred fixtures (S1.2.k). Session #20 was created with both providers (`anthropic/claude-haiku-4-5-20251001` and `xai/grok-4-1-fast-reasoning`). Both providers FAILED the YAML's expectations even though both behaved correctly given the seed.

Diagnosis (full detail in the rewrite report §2):

1. **`expected_tool_calls` does not match `QuerySchemas::REQUIRED_TOOLS`.** Every advice YAML asserts 2 tools (get_module_analysis + get_recommendations). PROTECTION_COVER's actual REQUIRED_TOOLS is `get_module_analysis(protection)` + `list_records(life_insurance)` — `get_recommendations` is NEVER required for any module-scoped classification (only for HOLISTIC_HEALTH).
2. **`IMPLICIT_RELATED` merging is unsaid.** RETIREMENT_CONTRIBUTION fans out to `[TAX_OPTIMISATION, SAVINGS_EMERGENCY, AFFORDABILITY]`, merging 7 unique tools. The YAML asserts 2.
3. **`expected_advice_response` is dead.** S1.6.a removed the SSE event, the composer, the schema, and the panel.
4. **`timing_budget_ms: 5000` is path-blind.** xAI grok-4-1-fast-reasoning emits ~10× more content events than anthropic on the same prompt; xAI consistently runs 12-15s on a clean module-scoped happy path. A single budget against both providers and all 4 ToolResultContract paths (success_false, readiness_blocked, empty_state, happy) is meaningless.
5. **Classification is asserted single-string but classifier is multi-label.** `QueryClassifier::classify()` returns `{primary, related[], modules[]}`. The YAML asserts only the primary, ignoring related types entirely. For RETIREMENT_CONTRIBUTION, the actual classification has 4 modules; the prompt the LLM received was for that wider set.
6. **Readiness gates and per-agent profile gates are unsaid.** ProtectionAgent line 72 has a SECONDARY gate (`if (! $user->protectionProfile)`) that fails for any seed without a `protection_profile` row. RetirementAgent line 101 has the same shape (`if (! $profile)` retirementProfile). The seed for `advice_protection_cover` deliberately tests the success_false path — and the LLM responds correctly by asking for the missing profile fields — but the YAML treats this as a tool-call FAIL.

The keyword-collision in `advice_goals_affordability` is independently broken: the classifier matches BOTH `affordability` and `retirement_readiness` patterns and resolves to `retirement_readiness` (earlier in `KEYWORD_PATTERNS`), so the YAML's `module: holistic` arg + `expected_classifications: [affordability]` are both wrong against the running classifier.

Verbatim CSJ direction (2026-04-27 session 102): *"do not change the seed, change the expectations, for the actual fucking workflow, user flow, user experience."*

**S1.7 must therefore deliver, beyond the source-spec scope:**

1. **Rewrite the 10 existing YAMLs** (S1.2.l above) against the per-classification REQUIRED_TOOLS, the four ToolResultContract paths, the multi-label classifier shape, the response-mode + engine-call-level + KYC-state contract.
2. **Extend `AssertionHelpers`** with the new keys (S1.7.a) so the YAMLs are parseable and assertable. Includes fragment-inheritance via `inherits` for shared assertions.
3. **6 architecture meta-tests** (S1.7.b) that prevent expectation drift from re-occurring (tool-list ↔ QuerySchemas, response-mode ↔ AdviceFyn map, forbidden_tools ↔ WRITE_TOOLS, kyc_blocked ↔ absent_tools, signposting ↔ response_mode, timing budget shape).
4. **4 new canonical-behaviour scenarios** (S1.7.c) for KYC-block / handoff round-trip / holistic engine / out-of-remit canonical refusal.
5. **Dashboard delta + new EvalProviderRun fields** (S1.7.d) to surface KYC state, tool-result paths, engine-call-level actual vs expected.
6. **14 onboarding state-machine eval scenarios** (S1.7.e) — one per state transition not already covered by asset_capture multi-entity YAMLs. Plus `--mode=deterministic` flag so they bypass the LLM (state machine output is deterministic given the state and parked facts).
7. **14 write-tool-family handoff stress tests** (S1.7.f) — one per `AdviceFyn::WRITE_TOOLS` entity family — with the shared INV-2.4.x invariants fragment.
8. **16 resume-after-disconnect scenarios** (S1.7.g) — 13 per-state + 3 edge cases — binding each state's `OnboardingChatDirector::resumeSummary` output to a falsifiable assertion.
9. **All 28 fixtures re-recorded** (S1.7.h) against the new contract, with the dashboard rendering expected-vs-actual deltas with zero false-FAILs.
10. **Hard-gate verification doc** (S1.7.i) before S1.10 closes.

Total YAML deliverables: **58** (10 rewrites + 4 canonical + 14 state-machine + 14 handoff + 16 resume). Original S1.7 said 30. The 28-scenario delta reflects: the original 10 are kept and rewritten (not added); the original 20 "more query-type + multi-entity + handoff + cancel-timeout + prompt-injection" expand to the 48 enumerated above.

Once items 1-10 hold, S1.10 verification can run and Rubric-A re-score is meaningful.

---

### S1.1 — Eval harness scaffold

- **Objective:** Build the `tests/Feature/Fyn/Eval/` scaffold (runner + mocked provider client + assertion helpers + report + 9 scenario subdirectories + architecture meta-tests) so subsequent tasks can author scenarios against a working harness.
- **Spec reference:** Source spec Task 1.1 + `spec/01-invariants.md` INV-2.13.1, INV-2.13.2, INV-2.13.3, INV-2.13.4; `fyn-rubrics.md §B`.
- **Files affected:**
  - CREATE: `tests/Feature/Fyn/Eval/EvalRunner.php`, `MockedProviderClient.php`, `AssertionHelpers.php`, `EvalReport.php`.
  - CREATE: `config/fyn_eval.php` — `recall_floor`, `precision_floor`, `hard_fail_floors`, `scenario_minima` (22/6/10/5/3/10/5/4/10 per spec 10-11-plan lines 47-75).
  - CREATE: 9 subdirectories under `tests/Feature/Fyn/Eval/scenarios/{01-query-types,02-preview-personas,03-multi-entity,04-handoffs,05-cancel-timeout,06-prompt-injection,07-regulatory,08-provider-parity,09-canonical-behaviour}/` with per-category `README.md` copied from `fyn-rubrics.md §B`.
  - CREATE: `tests/Architecture/EvalScenarioCountTest.php`, `tests/Architecture/EvalFloorIntegrityTest.php`.
  - `AssertionHelpers` methods: `assertToolCallsMatch`, `assertSseEventSequence`, `assertDbWrites`, `assertForbiddenOutputsAbsent`, `assertInterpretiveTextMapsToEngineSource` (INV-2.3.2 enforcement).
- **Acceptance test:** `./vendor/bin/pest tests/Feature/Fyn/Eval/ --testsuite=Eval` boots without errors (empty scenarios → empty pass). `EvalScenarioCountTest` passes because directory counts are 0 vs placeholder 0 (once minima enforced in Task 1.2 onwards, the gate becomes active).
- **Out of scope:** Authoring any scenario YAML (Tasks 1.2, 1.7). Recording fixtures (Task 1.2 Step 2). Real-provider Mode-2 cron (Sprint 2 Task 2.16 Step 8).

---

### S1.2 — First 10 scenarios (6 query-type + 4 multi-entity)

- **Objective:** Author the first 10 Rubric-B scenarios as YAML + Anthropic/xAI JSONL fixtures so Mode 1 can run end-to-end on a seed set covering advice query types + basic multi-entity behaviour.
- **Spec reference:** Source spec Task 1.2 + INV-2.13.1.
- **Files affected:**
  - CREATE: `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_protection_cover.yaml`, `advice_savings_emergency.yaml`, `advice_investment_isa.yaml`, `advice_retirement_contribution.yaml`, `advice_estate_iht.yaml`, `advice_goals_affordability.yaml`.
  - CREATE: `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/protection_2x_known_providers.yaml`, `protection_2x_unknown_providers.yaml`, `savings_3x_mixed.yaml`, `pensions_2x_schemes.yaml`.
  - CREATE: `tests/Feature/Fyn/Eval/fixtures/anthropic/*.jsonl` + `fixtures/xai/*.jsonl` recorded by running each scenario once against real providers.
- **Acceptance test:** `./vendor/bin/pest tests/Feature/Fyn/Eval/ --testsuite=Eval` → 10/10 PASS on mocked mode.
- **Out of scope:** Adding scenarios for the other 7 categories (Task 1.7 + Sprint 2 Task 2.16). Real-provider Mode-2 runs.

---

### S1.3 — Conversation index schema + summariser job

- **Objective:** Add `summary, topics, entities_mentioned, intents_stated, summarised_at` columns to `ai_conversations`; create `ConversationSummariserJob` dispatched at `STATE_DONE` + via 30-minute-inactivity cron.
- **Spec reference:** Source spec Task 1.3 + INV-2.11.2; `spec/02-current-system.md §5`.
- **Files affected:**
  - CREATE migration `database/migrations/2026_05_02_000001_add_conversation_index_columns.php` per spec lines 225-256 — 5 columns + `index('summarised_at')`.
  - MODIFY `app/Models/AiConversation.php` — extend `$fillable` + `$casts` (topics/entities_mentioned/intents_stated → `'array'`, `summarised_at → 'datetime'`).
  - CREATE `app/Jobs/ConversationSummariserJob.php` — `ShouldQueue`, `public readonly int $conversationId`, `handle()` calls `App\Services\AI\ConversationSummariser::summarise`.
  - CREATE `app/Services/AI/ConversationSummariser.php` — prompts cheapest configured model (`grok-4-1-fast-non-reasoning` default; honour memory `feedback_fyn_model_choice_is_deliberate.md`) for structured-output `{summary, topics, entities_mentioned, intents_stated}`.
  - MODIFY `app/Services/Onboarding/OnboardingChatDirector.php` — dispatch `ConversationSummariserJob::dispatch($conversation->id)` on `STATE_DONE` transition.
  - MODIFY `app/Console/Kernel.php` — schedule summariser scan (`last_message_at > 30 min ago AND (summarised_at IS NULL OR summarised_at < last_message_at)`).
  - CREATE `tests/Feature/AI/ConversationIndexPopulationTest.php`.
- **Acceptance test:** `php artisan migrate` clean. Closing an onboarding conversation triggers the job; `AiConversation` row has non-empty `summary`, `topics`, `entities_mentioned`; `summarised_at` set. Pest green.
- **Out of scope:** Back-filling historical conversations. Moving to Option B (separate index table). Vector search.

---

### S1.4 — `MemoryRetrieverService` + `<known_facts>` prompt block

- **Objective:** Build `MemoryRetrieverService` with strict DB → parked → current → index fall-through; inject a `<known_facts>` block ending with *"Do not ask the user for any field above."* into `OnboardingPromptBuilder` grouped-extract + asset-capture prompts AND `AdvicePromptBuilder` recommendation + factual prompts.
- **Spec reference:** Source spec Task 1.4 + INV-2.2.3, INV-2.11.1.
- **Files affected:**
  - CREATE `app/Services/AI/MemoryRetrieverService.php` — methods `retrieve`, `fromAuthoritativeDb`, `fromParkedFacts`, `fromCurrentConversation`, `fromConversationIndex`. Queries `users.*`, `family_members`, linked module tables for layer 1; `ai_conversations.onboarding_parked_facts` for layer 2; derives from `ai_messages` via current conversation for layer 3; queries `ai_conversations` index columns only for fields still missing after layers 1-3.
  - MODIFY `app/Services/Onboarding/OnboardingPromptBuilder.php` — prepend `<known_facts>\n- key: json_encoded_value\n…\n</known_facts>\n\nDo not ask the user for any field above.\n` to grouped-extract + asset-capture prompt outputs.
  - MODIFY `app/Services/AI/AdvicePromptBuilder.php` — same injection.
  - CREATE `tests/Unit/Services/AI/MemoryRetrieverServiceTest.php` — parameterised over each layer; fall-through assertion.
  - CREATE `tests/Unit/Services/Onboarding/KnownFactsBlockTest.php` — seed user with every onboarding field; build base_spouse prompt; assert every field name in block; instruction suffix present.
  - CREATE `tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/memory-no-repeat-ask.yaml` — seed `marital_status='married'`, start at `base_spouse`; assert no prompt asks user's own marital status.
- **Acceptance test:** Unit asserts pass; Rubric B `09-03` scenario green; Browser `BS-03` PASS (subject to Task 1.9).
- **Out of scope:** Cross-user retrieval. Caching retrieved facts between turns.

---

### S1.5 — `search_conversation_index` tool

- **Objective:** Register `search_conversation_index` on both Anthropic + xAI; implement the handler; include on `AdviceFyn::buildToolList` (not in `WRITE_TOOLS`); allow only when `<known_facts>` silent on the needed fact.
- **Spec reference:** Source spec Task 1.5 + INV-2.11.3.
- **Files affected:**
  - MODIFY `app/Services/AI/AiToolDefinitions.php` — add tool definition per spec lines 425-440 (`topic_keywords[]`, `entity_types[]`; `additionalProperties: false`).
  - MODIFY `app/Services/AI/XaiToolDefinitions.php` — wrapped with `strict: true`.
  - MODIFY `app/Agents/CoordinatingAgent.php::executeTool` — dispatch to new `handleSearchConversationIndex($input, $user)` per spec lines 443-471 (query `AiConversation::forUser($user->id)->whereNotNull('summary')->whereJsonContains('topics', ...)->limit(10)`).
  - MODIFY `app/Services/AI/AdviceFyn.php` — tool naturally permitted (not in `WRITE_TOOLS`); verify test coverage.
  - CREATE `tests/Feature/AI/SearchConversationIndexTest.php` — seed 3 conversations with different topics + entity types; search returns correct subset ordered by `last_message_at`.
  - CREATE `tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/cross-conversation-surface.yaml` — user asks retirement question; prior conversation's `intents_stated` includes "wants to retire at 60"; assert tool_use + intent surfaced.
- **Acceptance test:** `tests/Feature/AI/SearchConversationIndexTest` green; scenario `09-10` green; Browser `BS-24` PASS.
- **Out of scope:** Full-text search on `summary` column. Fuzzy matching. Per-user ACL beyond `forUser` scope.

---

### S1.6 — `advice_response` SSE event + `AdviceResponsePanel.vue`

- **Objective:** Emit one `advice_response` SSE event per recommendation-mode turn in `AdviceFyn::handle`; render via new `AdviceResponsePanel.vue`; payload mapped from `orchestrateAnalysis` + `HolisticPlanner` output (engine-sourced only per INV-2.3.2).
- **Spec reference:** Source spec Task 1.6 + INV-2.3.5.
- **Files affected:**
  - MODIFY `app/Services/AI/AdviceFyn.php` — at end of recommendation-mode tool loop, yield `['type' => 'advice_response', 'headline' => composeHeadline($engineOutput), 'key_figures' => extractKeyFigures($engineOutput), 'breakdowns' => extractBreakdowns($engineOutput), 'recommendations' => mapRecommendations($engineOutput['ranked_recommendations']), 'next_steps' => extractNextSteps($engineOutput), 'signposting' => 'For regulated advice personal to your circumstances, speak to a qualified financial adviser.']`. Helper methods pure-function unit-testable.
  - CREATE `resources/js/components/Shared/AdviceResponsePanel.vue` per spec lines 520-589 — single-file component reading `props.response`; renders headline (`text-horizon-500`), key figures grid (`savannah-100` tiles), breakdowns list, recommendations list with `priorityBadgeClass` (raspberry/violet/savannah/neutral per design system), next-steps buttons (raspberry outline), signposting italic at bottom.
  - MODIFY `resources/js/store/modules/aiChat.js` — new `case 'advice_response':` block appending a message with `role: 'advice_response'`, `metadata: event`.
  - MODIFY `resources/js/components/Shared/AiChatPanel.vue` — render branch for `msg.role === 'advice_response'` → `<AdviceResponsePanel :response="msg.metadata" @navigate="handleNavigate" />`.
  - CREATE `tests/Feature/Fyn/AdviceResponseSseShapeTest.php` — JSON-schema validation on every recommendation scenario.
- **Acceptance test:** Mock recommendation scenario emits exactly one `advice_response`; shape validates; factual scenarios emit 0; Browser `BS-09` PASS.
- **Out of scope:** Redesigning the chat bubble layout. Adding `additionalProperties` beyond the 6 top-level fields.

---

### S1.7 — Expand eval to 30 scenarios

- **Objective:** Author 20 more scenarios so Mode 1 runs on 30 total — 16 more query-type + 6 more multi-entity + 5 handoff round-trip + 3 cancel/timeout + 2 prompt-injection starters. Record fixtures against real providers.
- **Spec reference:** Source spec Task 1.7 + INV-2.13.1.
- **Files affected:**
  - CREATE under `tests/Feature/Fyn/Eval/scenarios/01-query-types/` — 16 more scenarios covering the remaining `QuerySchemas` constants.
  - CREATE under `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/` — 6 more covering mixed-module multi-entity inputs.
  - CREATE under `tests/Feature/Fyn/Eval/scenarios/04-handoffs/` — 5 advice → inline-capture → advice round-trips.
  - CREATE under `tests/Feature/Fyn/Eval/scenarios/05-cancel-timeout/` — 3 SSE-abort + token-limit + provider-timeout scenarios.
  - CREATE under `tests/Feature/Fyn/Eval/scenarios/06-prompt-injection/` — 2 starter scenarios (full set Sprint 2 Task 2.16).
  - Record corresponding `fixtures/{anthropic,xai}/*.jsonl`.
- **Acceptance test:** Mode 1 green at 30 scenarios. Per-category minima satisfied for the 5 started categories (others remain below minima until Sprint 2).
- **Out of scope:** Provider-parity scenarios (Sprint 2 category 08). Regulatory scenarios (Sprint 2 category 07 beyond Sprint-0 coverage).

---

### S1.8 — Advice Fyn response-mode classifier

- **Objective:** Implement `AdviceFyn::classifyResponseMode(string $queryType): 'factual'|'recommendation'|'out_of_remit'` + `engineCallLevel(string $queryType): 'holistic'|'module'|'factual'` as static maps covering every `QuerySchemas` constant; wire into `AdviceFyn::handle`.
- **Spec reference:** Source spec Task 1.8 + INV-2.3.1, INV-2.3.6.
- **Files affected:**
  - MODIFY `app/Services/AI/AdviceFyn.php` — add both static maps + methods.
  - CREATE `tests/Unit/Services/AI/AdviceFynResponseModeTest.php` — parameterised over all `QuerySchemas` + billing + document-reference; every constant covered.
  - CREATE `tests/Unit/Services/AI/AdviceFynEngineCallLevelTest.php` — parameterised same; assert no level ambiguity; assert holistic/cross-module are only callers of `orchestrateAnalysis`.
- **Acceptance test:** Every constant appears in both maps exactly once.
- **Out of scope:** Dynamic classification (maps are static). Changing `QuerySchemas` constants.

---

### S1.9 — Sprint 1 Playwright matrix (4 new scenarios + regression)

- **Objective:** Author BS-03, BS-08, BS-09, BS-24 per `spec/03-test-strategy.md` + regression-run BS-01 through BS-23 → total 24 scenarios green, screenshots in `docs/sprint-1-verification/BS-NN/`.
- **Spec reference:** Source spec Task 1.9 + `spec/03-test-strategy.md §Per-sprint-scenario-index`.
- **Files affected:**
  - CREATE `tests/Browser/scenarios/BS-03-known-facts-no-repeat-ask.php`.
  - CREATE `tests/Browser/scenarios/BS-08-advice-factual-net-worth.php`.
  - CREATE `tests/Browser/scenarios/BS-09-advice-recommendation-isa.php`.
  - CREATE `tests/Browser/scenarios/BS-24-cross-conversation-surface.php`.
  - Screenshots in `docs/sprint-1-verification/BS-{03,08,09,24}/`.
- **Acceptance test:** `./dev.sh` running + `php artisan db:seed` + `./vendor/bin/pest --testsuite=Browser --filter=BS-` → 24/24 PASS.
- **Out of scope:** BS-17 batch-tool extension (Sprint 2 Task 2.19). BS-25 failover (Sprint 4 Task 4.7).

---

### S1.10 — Sprint 1 verification rollup

- **Objective:** Publish the Sprint 1 verification artefacts: full Pest green, Rubric-B Mode 1 at 30/30, Browser matrix 24/24, Rubric-A re-score 17-18/40 🟠, merge PR to `feature/fyn-persona-split`.
- **Spec reference:** Source spec §Sprint-1-verification + `spec/01-invariants.md §verification` row "Post Sprint 1".
- **Files affected:**
  - `docs/sprint-1-verification/rubric-a-score.md` — dimension-by-dimension walk.
  - PR body linking to the verification doc + screenshot directories.
- **Acceptance test:** Rubric-A ≥17/40 🟠 Limited beta (gate for Sprint 3 dev deploy). No canonical §0 violation. No Rubric-A dimension regresses.
- **Out of scope:** Dev deploy (Sprint 3). Rubric-B Mode 2 cron (Sprint 2 Task 2.16).

---

*End of plan for Sprint 1. Sprint 2 follows — batch-shaped capture tools across all 18 entity types.*

**Post-sprint priorities:** see `15-post-sprint-priorities-plan.md` for the lifestyle + campaign landing-pages workstream, queued after Sprints 0-4 hit GREEN.
