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

# Findings

## Branch state
- Working tree currently on `main` (local git says branch=`main`).
- Target implementation branch per spec: `feature/fyn-persona-split`.
- Per `spec/README.md` line 5: branch is 68 ahead / 179 behind `origin/main` at spec time.

## Spec files read
All 10 read end-to-end.

## Key code anchors cited across the spec (on `feature/fyn-persona-split`)

### Two-Fyn collapse (Sprint 0.3)
- `app/Http/Controllers/Api/AiChatController.php` — dispatch, `sendMessage`, `startOnboarding`.
- `app/Services/Onboarding/OnboardingChatDirector.php:1-1985` — kept; `resumeSummary` at lines 394-406.
- `app/Services/Onboarding/OnboardingStateMachine.php:1-713` — kept.
- `app/Services/Onboarding/OnboardingValueInterpreter.php:1-324` — kept.
- `app/Services/Onboarding/OnboardingFactExtractor.php:1-286` — kept.
- `app/Services/Onboarding/AssetCaptureEntityExtractor.php:1-665` — kept; `findMissing` lacks DB dedup.
- `app/Services/Onboarding/SpouseLinkingService.php:1-367` — kept.
- `app/Services/Onboarding/HouseholdProvisioner.php:1-61` — kept.
- `app/Services/AI/FynPersonaOrchestrator.php:1-415` — **delete**; `personaStateChangeEvent` at 382-388.
- `app/Services/AI/FynPersonaInvoker.php:1-518` — **delete**; `emitGapFillFromCaptureContext` at ~251-300.
- `app/Services/AI/FynPersonaRegistry.php:1-104` — **delete**.
- `app/Services/AI/Prompts/DataCapturePromptBuilder.php:1-110` — **delete**.
- `app/Services/AI/HandoffContract.php` — keep.
- `app/Services/AI/Prompts/EmptyDataGuard.php` — keep.
- `app/ValueObjects/CaptureContext.php` — keep.
- `config/fyn_personas.php` — **delete**.
- `config/fyn.php` — keep.
- `config/onboarding.php` — keep; add `journey_map`.

### Coordinating agent handlers (Sprint 0.5 conversions)
`app/Agents/CoordinatingAgent.php` fill_form sites (per `02-current-system.md §3.2`):
- Line 1510 `handleCreateGoal`
- Line 1549 `handleCreateLifeEvent`
- Line 1595 `handleCreateSavingsAccount` (also ~1557 per Sprint 0 task table)
- Line 1742 `handleCreateInvestmentAccount` (also 1614)
- Line 1809 `handleCreateHolding` (also 1750)
- Line 1887 `handleCreatePension` (also 1817)
- Line 2018 `handleCreateProperty` (also 1895)
- Line 2065 `handleCreateMortgage` (also 2026)
- Line 2132 `handleCreateProtectionPolicy` (also 2073)
- Line 2165 `handleCreateEstateAsset` (also 2140)
- Line 2205 `handleCreateEstateLiability` (also 2173)
- Line 2244 `handleCreateEstateGift` (also 2213)
- Line 2861 `handleCreateFamilyMember` (also 2770)
- Line 2923 `handleCreateTrust` (also 2869)
- Line 2978 `handleCreateBusinessInterest` (also 2931)
- Line 3021 `handleCreateChattel` (also 2986)
- Line 3142 `handleUpdateRecord` (fill_form path); 3134 is blocklist
- Line 770 `[AI-AUDIT] Tool executed` log; gating at 768
- Line 1390 `handleRecommendations`
- Line 158-219 `orchestrateAnalysis`

### Traits / guardrails
- `app/Traits/HasAiChat.php:287-305` — Anthropic SDK call (no explicit timeout).
- `app/Traits/HasAiChat.php:569-572` — `ai_messages` write with metadata.
- `app/Traits/HasAiChat.php:612` — `tools_called` write to `ai_advice_logs`.
- `app/Traits/HasAiChat.php:679` — `buildMessageHistory`.
- `app/Traits/HasAiChat.php:704` — `generateTitle` (no `strip_tags`).
- `app/Traits/HasAiChat.php:749` — `summariseToolResult` (strips entity_id).
- `app/Traits/HasAiGuardrails.php:221` — `Cache::remember 300` token budget race.

### Providers / tools
- `app/Services/AI/AiToolDefinitions.php::getTools` — Anthropic, 37 tools today.
- `app/Services/AI/XaiToolDefinitions.php::getTools` — xAI, 33 tools (missing capture_personal_details, capture_spouse_details, capture_dependants, create_holding).
- `app/Services/AI/AdvicePromptBuilder.php` — advice prompt.
- `app/Services/AI/Prompts/CoreIdentity.php` — contains "qualified financial planner" string (remove).
- `app/Services/AI/StructuredResponseValidator.php`.
- `app/Services/AI/Prompts/ComplianceRules.php`.
- `app/Services/AI/Prompts/FcaProcessInstructions.php`.
- `app/Services/AI/QueryClassifier.php` + `app/Constants/QuerySchemas.php`.

### Sidecar / dead code (Sprint 0.2 deletes)
- `config/services.php` lines 34-38 — OpenAI block.
- `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`.
- `app/Http/Controllers/Api/AgentInternalController.php`.
- `app/Http/Middleware/AgentTokenAuth.php`.
- `routes/api.php` lines 1193-1199 — `/api/internal/agent/*`.
- `app/Http/Kernel.php` line 81 — `agent.token` middleware.

### Frontend (Sprint 0.4 removals)
- `resources/js/store/modules/aiChat.js:511-516` — `persona_state_change` handler.
- `resources/js/components/Shared/AiChatPanel.vue` — capturing pill + placeholder swap.
- `resources/js/router/index.js`.
- `resources/js/layouts/AppLayout.vue`.
- `resources/views/app.blade.php:71-73` — Plausible; `:80-89` Meta Pixel.
- `resources/js/utils/awinTracking.js` — AWIN.

### Privacy + consent
- `resources/js/views/Public/PrivacyPolicyPage.vue:111` — §5 health-data contradiction.
- `resources/js/views/Public/PrivacyPolicyPage.vue:124` — §7 Anthropic scope mismatch.
- `resources/js/views/Public/PrivacyPolicyPage.vue:132` — §7 "no third-party analytics" contradiction.
- `app/Services/GDPR/ConsentService.php::hasConsent` — exists but not called from chat.
- `app/Http/Controllers/Api/AdminController.php` — `Cache::forever('ai_provider', ...)` toggle.

### Documents
- `app/Services/Documents/AIExtractionService.php:1-965`; scanned PDF cap at line 31 / 783. No `app/Jobs/*Extract*`.

### Sources for every plan
- Source spec file being planned (cited at top of each plan).
- `/Users/CSJ/Desktop/fynla/April/April24Updates/audit-evidence.md` — ground-truth code claims.
- `/Users/CSJ/Desktop/fynla/April/April24Updates/audit-synthesis.md` — reviewer-consolidated verdicts + CSJ decisions.
- `/Users/CSJ/Desktop/fynla/April/April24Updates/fyn-rubrics.md` — Rubric A + Rubric B definitions.

## Canonical preamble (paste at top of every plan)
```
> **Canonical contract:** [`../spec/00-canonical.md`](../spec/00-canonical.md) — two-Fyn architecture, verbatim.
> **Branch:** all implementation commits on `feature/fyn-persona-split` (or feature branches off it). See `spec/README.md` for branch mandate.
> **Sources:**
> - Source spec: `../spec/<FILE>.md`
> - Audit evidence: [`../audit-evidence.md`](../audit-evidence.md)
> - Audit synthesis: [`../audit-synthesis.md`](../audit-synthesis.md)
> - Rubrics: [`../fyn-rubrics.md`](../fyn-rubrics.md)
```

## Plan-slice template (user-specified, identical across all files)
```
### <slice-id> — <title>

- **Objective:** <one sentence>
- **Spec reference:** <property / invariant / task>
- **Files affected:** <cited paths + line numbers for existing code>
- **Acceptance test:** <command / test / observation that returns yes/no>
- **Out of scope:** <what NOT to touch>
```
