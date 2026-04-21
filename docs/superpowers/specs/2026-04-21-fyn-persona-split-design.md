# Fyn Persona Split — Design Spec

**Date:** 2026-04-21
**Author:** CSJ (brainstormed with Claude)
**Stage:** Spec — amended after codebase audit
**Status:** Amended 2026-04-21 — conflicts resolved against live code. Target branch `feature/fyn-persona-split` off `onboardingFyn`.

---

## Problem

Fyn currently runs a single ~1,600-token advice system prompt (`App\Services\AI\SystemPromptBuilder`) for every chat turn, including data-capture turns during onboarding. An `App\Services\Onboarding\OnboardingPromptBuilder` was introduced as a tactical workaround — a short ~500-token prompt used only for `asset_capture` turns via `OnboardingChatDirector`. All other chat — including non-capture onboarding turns (journey selection, base_personal, base_spouse, base_dependants, expenditure) and all post-onboarding data entry — still runs the full advice prompt.

The consequences:

1. **Behaviour quality suffers.** The advice prompt's FCA process instructions bias the model toward single-tool-per-turn emission, which breaks multi-entity capture. FR-M14 shipped a buffered sentence-level off-script filter in `OnboardingChatDirector::handleAssetCaptureTurn` and a streaming-duplicate fix in `resources/js/store/modules/aiChat.js` to suppress leaks. More whack-a-mole is likely as the same root cause surfaces in new shapes.
2. **Tokens are wasted.** Data-capture turns pay the full advice tax even though the advice context (financial context, existing records, data completeness, query knowledge) isn't relevant to "add my ISA".
3. **Architecture does not scale.** Adding a third persona (advisor mode, mobile quick-start, post-retirement-only, etc.) would require duplicating large chunks of `SystemPromptBuilder` or layering more flags onto it.

## Goals

1. **Clean separation of concerns:** one persona reasons and advises; another captures data. Each has a distinct system prompt, tool set, and responsibility.
2. **Token efficiency:** data-capture turns never load advice-only context. Advice turns never load capture-specific instructions.
3. **Scalability:** a config-driven persona registry supports future personas without rewriting routing. Adding a persona = a new registry entry + a new prompt builder class.

## Non-goals

- Additional personas beyond `advice` and `data_capture`. The registry must accommodate them, but none are built here.
- Backfill of historical `ai_messages.persona`. The new column is null for pre-existing rows; no retroactive tagging.
- Mobile app changes. The orchestrator sits behind the same `AiChatController`, so mobile inherits the behaviour without a dedicated iOS build change.

## Decisions made during brainstorming

**Status:** Amended 2026-04-21 after codebase audit. Changes summarised in the "Amendments after codebase audit" section below.

- **Mode fork:** wide scope for post-onboarding. Two persistent personas (`advice`, `data_capture`), intent-driven.
- **Routing:** the new `FynPersonaOrchestrator` handles **post-onboarding turns only**. `OnboardingChatDirector` remains the single entry point for the initial onboarding flow — unchanged in its role, but extended with new states and features (see "Onboarding UX overhaul" below). `AiChatController` keeps the existing `$inOnboarding` branch and adds a third branch for the post-onboarding orchestrator.
- **Tool ownership:** option A. All write tools (create / update / delete / capture / profile) live exclusively on data-capture Fyn. Advice Fyn has none of them and instead emits `delegate_to_capture` when a write is needed.
- **Handoff mechanism:** structured tool calls primary. Advice → data-capture via `delegate_to_capture`. Data-capture → advice via `capture_complete`.
- **Classifier fast-path:** the existing `QueryClassifier` is promoted to run at the orchestrator level. A confident `DATA_ENTRY` classification (with a length cap and advice-phrase absence check) preselects data-capture and skips the advice invocation. No new `FynIntentClassifier` class.
- **Action endpoint:** onboarding resume / continue / restart / skip are sent via `POST /api/ai-chat/conversations/{id}/action` with body `{action: 'resume' | 'continue' | 'restart' | 'skip'}`. Actions are **not** persisted as `AiMessage` rows. Added to `PreviewWriteInterceptor::EXCLUDED_ROUTES`.
- **UX:** the user sees one Fyn. Handoff tool calls are stripped from the SSE stream. The chat UI renders subtle status cues during handoffs.

---

## Architecture

```
AiChatController
    │
    ├── if ($inOnboarding)       → OnboardingChatDirector    (UNCHANGED role — still drives the onboarding state machine)
    │                               │                          Extended with new states/features (Phase: Onboarding UX overhaul)
    │                               ├── non-capture states → deterministic handlers (bubble matching, DOB parsing, …)
    │                               └── asset_capture      → delegates to CoordinatingAgent::chatWithPromptOverride
    │                                                        with OnboardingPromptBuilder + focus-filtered create_* tools
    │
    ├── elseif ($splitEnabled)   → FynPersonaOrchestrator    (NEW — post-onboarding only)
    │                               │
    │                               ├── reads AiConversation.persona_state
    │                               ├── runs QueryClassifier fast-path (extended) on the user message
    │                               │     ├── confident DATA_ENTRY → preselect data_capture
    │                               │     └── anything else        → default advice
    │                               │
    │                               ├── dispatches to FynPersonaInvoker
    │                               │     ├── persona=advice       → AdvicePromptBuilder (renamed SystemPromptBuilder)
    │                               │     └── persona=data_capture → DataCapturePromptBuilder (NEW, post-onboarding only)
    │                               │
    │                               ├── parses tool calls from response
    │                               ├── intercepts handoff tool calls (delegate_to_capture / capture_complete)
    │                               ├── updates persona_state
    │                               └── loops or returns per transition rules
    │
    └── default                  → CoordinatingAgent::chat() (today's fallback — preserved behind the flag)
```

**Key separation:** the onboarding director and the persona-split orchestrator are **independent subsystems** that coexist. The director keeps owning the deterministic state machine for initial onboarding; the orchestrator handles intent-driven advice/capture for everything else. They share the `CoordinatingAgent` / `HasAiChat` LLM primitives but do not depend on each other.

---

## Components

### New

- **`App\Services\AI\FynPersonaOrchestrator`** — the dispatcher. Methods: `dispatch(User $user, AiConversation $conversation, string $userMessage): StreamedResponse`. Owns the persona state transitions. Approx 150 LOC.
- **`App\Services\AI\FynPersonaRegistry`** — config-driven registry. Each persona row: `name`, `prompt_builder_class`, `allowed_tools[]` (whitelist of tool names from `AiToolDefinitions` / `XaiToolDefinitions`), `handoff_tools[]` (tool names that trigger a transition).
- **`App\Services\AI\FynPersonaInvoker`** — executes a single persona turn. Builds the system prompt via the registered builder, calls the LLM via the existing xAI client, streams response chunks to SSE, parses tool calls. Isolates "invoke one persona" from "decide which persona". Approx 200 LOC.
- **`App\Services\AI\Prompts\DataCapturePromptBuilder`** — generalised from `App\Services\Onboarding\OnboardingPromptBuilder`. Accepts a `CaptureContext` value object. Emits a short capture-focused prompt. Preserves the FR-M14 off-script guardrails (single-sentence acknowledgment, banned keyword filter, no questions). Approx 150 LOC.
- **`App\ValueObjects\CaptureContext`** — immutable value object carrying `reason: string`, `entity_types: array<string>`, `fields_needed: array<string>`, `pending_advice_question: ?string`, `originating_focus: ?string` (populated when the orchestrator is in `onboarding` mode so the data-capture prompt preserves the user's selected journey focus).

### Renamed

- **`App\Services\AI\SystemPromptBuilder` → `App\Services\AI\AdvicePromptBuilder`.** No behaviour change. All references updated (searches: `SystemPromptBuilder`, bindings in `AppServiceProvider`, `CoordinatingAgent`, `HasAiChat`, test classes). The rename is part of this change; without it the naming lies.

### Extended (not replaced)

- **`App\Services\Onboarding\OnboardingChatDirector`** — stays. Still owns the initial onboarding flow. Extended with new state transitions (`profile_review_family`, `profile_review_expenditure`), spouse-skip handling, multi-job loop, conversational retraction, and fact parking. The FR-M14 buffered off-script filter stays where it is today.
- **`App\Services\Onboarding\OnboardingPromptBuilder`** — stays. Still builds the asset-capture prompt for onboarding. Not consolidated with `DataCapturePromptBuilder` — they target different phases and have different responsibilities.

### Unchanged

- `App\Traits\HasAiChat` and `App\Agents\CoordinatingAgent` — still used internally by `FynPersonaInvoker` for prompt building, streaming, and tool-call parsing primitives. The orchestrator is a layer above, not a replacement. The director continues to call `CoordinatingAgent::chatWithPromptOverride()` for its asset-capture turns as it does today.

### New tools (in `AiToolDefinitions` and `XaiToolDefinitions`)

- **`delegate_to_capture`** — advice-only. Advice Fyn emits this when it cannot answer without data the user has not supplied, or when the user asks for an inline capture. Never shown to the user.
- **`capture_complete`** — data-capture-only. Data-capture Fyn emits this when the capture sub-conversation is done. Never shown to the user.

Schemas below under "Handoff contract".

---

## Data flow

### Example 1 — KYC-gated advice question

1. User: *"What should I do about my pensions?"*
2. `AiChatController` → `FynPersonaOrchestrator::dispatch()`.
3. Orchestrator reads `AiConversation.persona_state.current` = `advice` (default for new/post-onboarding conversations). Invokes advice Fyn via `FynPersonaInvoker`.
4. Advice Fyn's prompt (via `AdvicePromptBuilder`) shows no DC/DB pension records in `<existing_records>` and no pension values in `<financial_context>`. Advice Fyn emits:
    - `delegate_to_capture(reason: "retirement advice blocked on missing pension data", entity_types: ["dc_pension", "db_pension"], fields_needed: ["scheme_name", "current_fund_value", "pension_type"])`
    - Plus a short user-visible acknowledgment: *"Let me note your pension details first — then I can answer properly."*
5. Orchestrator intercepts the tool call (stripped from SSE). Writes `AiConversation.persona_state`:
    ```json
    {
        "current": "capturing",
        "pending_advice_question": "What should I do about my pensions?",
        "capture_context": { "reason": "...", "entity_types": [...], "fields_needed": [...] }
    }
    ```
    Persists `AiMessage(persona: "advice")` containing the acknowledgment text only.
6. Orchestrator immediately invokes data-capture Fyn with the `CaptureContext`. Data-capture prompt is ~500 tokens, exposes only the relevant `create_*` tools filtered by `entity_types`. First data-capture turn asks the user: *"Could you tell me about each pension — provider, current value, and whether it's defined contribution or defined benefit?"*
7. User: *"Scottish Widows SIPP, £50k, defined contribution."*
8. Orchestrator sees `persona_state.current = capturing`. Invokes data-capture Fyn. Data-capture Fyn calls `create_pension(...)`, then emits `capture_complete(summary: "Added Scottish Widows SIPP £50k", records_created: [{"type": "dc_pension", "id": 42}])`.
9. Orchestrator intercepts `capture_complete`. Persists `AiMessage(persona: "data_capture")`. Reads `pending_advice_question`, resets `persona_state` to `{"current": "advice"}`.
10. Same HTTP request: orchestrator re-invokes advice Fyn with the original question re-primed as a system-injected suffix:
    > *"The user's original question was: 'What should I do about my pensions?'. Data-capture has just recorded: Scottish Widows SIPP (DC) £50k. Now answer the original question using the updated records."*
11. Advice Fyn answers the question with the new data loaded in `<financial_context>` and `<existing_records>`. User sees a seamless stream: acknowledgment → capture question → record confirmation → the actual advice.

### Example 2 — mid-conversation inline capture

1. User is mid-advice thread: *"By the way, add my Nationwide cash ISA — £5,000."*
2. Orchestrator invokes advice Fyn (current state = `advice`). Advice Fyn emits:
    - `delegate_to_capture(reason: "user requested inline capture", entity_types: ["savings_account"], fields_needed: [])`
    - Acknowledgment: *"I'll add that now."*
3. Flow continues as above — data-capture Fyn creates the record, emits `capture_complete`. Because `pending_advice_question` is null (the user wasn't blocked on an advice answer), orchestrator returns to `advice` state and waits for the next user turn. No re-invocation this turn.

### Example 3 — user cancellation mid-capture

1. `persona_state.current = capturing`, `pending_advice_question` is set.
2. User: *"Actually, never mind."*
3. Orchestrator runs a pre-invocation cancel-pattern check BEFORE dispatching to data-capture Fyn. Matches the cancel list (`"stop"`, `"cancel"`, `"never mind"`, `"forget it"`, configurable).
4. Orchestrator flips `persona_state` back to `{"current": "advice"}`, drops `pending_advice_question`. Invokes advice Fyn with a system-injected note: *"The user cancelled the capture. Acknowledge briefly and ask what else they need."*
5. Advice Fyn acknowledges and returns control.

---

## Persona registry

```php
// config/fyn_personas.php
return [
    'advice' => [
        'prompt_builder' => App\Services\AI\AdvicePromptBuilder::class,
        'allowed_tools' => [
            'navigate_to_page',
            'get_module_analysis',
            'orchestrate_analysis',
            'get_risk_profile',
            'recommend_isa_contribution',
            'recommend_pension_contribution',
            // ... full list of advice-side tools drawn from AiToolDefinitions
        ],
        'handoff_tools' => ['delegate_to_capture'],
    ],
    'data_capture' => [
        'prompt_builder' => App\Services\AI\Prompts\DataCapturePromptBuilder::class,
        'allowed_tools' => [
            // Financial records
            'create_savings_account',
            'create_investment_account',
            'create_holding',
            'create_pension',
            'create_protection_policy',
            'create_property',
            'create_mortgage',
            'create_asset',
            'create_liability',
            'create_estate_gift',
            'create_trust',
            'create_chattel',
            'create_business_interest',
            // Goals and life events
            'create_goal',
            'create_life_event',
            // People and relationships
            'create_family_member',
            // User / spouse / dependants profile details
            'capture_personal_details',
            'capture_spouse_details',
            'capture_dependants',
            'capture_work_details',
            'update_profile',
            // Estate documents (see "New tools required" below — not in AiToolDefinitions today)
            'create_will',
            'update_will',
            'create_power_of_attorney',
            'update_power_of_attorney',
            // Updates and deletes on any user-owned record
            'update_record',
            'delete_record',
        ],
        'handoff_tools' => ['capture_complete'],
    ],
];
```

Adding a future persona is one new array entry plus a prompt builder class. No changes to the orchestrator or invoker.

### New tools required (not in `AiToolDefinitions` today)

The audit of write tools surfaced two estate-planning gaps. Both are in scope for this change because they belong to data-capture Fyn's responsibility.

- **`create_will` / `update_will`** — `App\Models\Estate\Will` exists as a model; the Will Builder lives at `/estate/will-builder`. There is no AI tool for creating or updating wills today. New tool definitions needed in `AiToolDefinitions` and `XaiToolDefinitions`, with handler methods on `CoordinatingAgent`. Scope: `executor_name`, `residuary_beneficiary`, `guardian_for_minors`, `specific_gifts`. **Schema change required**: `executor_name` already exists on the `wills` table; the other three columns do NOT exist and must be added by a new unconditional migration. The existing `will_documents` table has a `specific_gifts` column but is a separate model — the new `specific_gifts` column on `wills` is distinct and lives alongside the Will model directly.
- **`create_power_of_attorney` / `update_power_of_attorney`** — the `App\Models\Estate\LastingPowerOfAttorney` model and `lasting_powers_of_attorney` table **already exist**. Use the existing model. Tool schema aligns to the existing columns: `lpa_type` (not `type`) with values `property_financial` / `health_welfare` (not `property_and_finance` / `health_and_welfare`); primary attorney is captured via the existing `LpaAttorney` related table (the tool creates a minimal `LpaAttorney` record rather than storing a flat `attorney_name` string). No new model, no new migration, no new controller — only the two AI tool definitions and two `CoordinatingAgent` handlers.

The persona registry lists these four tool names. They must ship in the same release or the registry will throw the integrity test introduced in the Testing section.

---

## Handoff contract

Handoffs are **structured tool calls, never text heuristics**. Advice Fyn emits `delegate_to_capture`; data-capture Fyn emits `capture_complete`. Both have strict JSON schemas. The orchestrator validates on receipt — malformed calls are logged and the handoff is rejected (fall through to normal response handling).

### `delegate_to_capture`

```json
{
    "name": "delegate_to_capture",
    "description": "Internal. Advice Fyn emits this when it cannot answer without data the user hasn't supplied, or when the user requests an inline capture. Never shown to the user.",
    "input_schema": {
        "type": "object",
        "required": ["reason", "entity_types"],
        "properties": {
            "reason": {
                "type": "string",
                "description": "Why capture is needed (e.g. 'retirement advice blocked on missing pension data')."
            },
            "entity_types": {
                "type": "array",
                "items": { "type": "string" },
                "description": "The record types to capture (dc_pension, savings_account, property, etc.). Drawn from the data-capture persona's allowed_tools list."
            },
            "fields_needed": {
                "type": "array",
                "items": { "type": "string" },
                "description": "Optional. Specific fields required to unblock the advice answer. Used to shape the data-capture prompt."
            }
        }
    }
}
```

### `capture_complete`

```json
{
    "name": "capture_complete",
    "description": "Internal. Data-capture Fyn emits this when the capture sub-conversation is done. Never shown to the user.",
    "input_schema": {
        "type": "object",
        "required": ["summary", "records_created"],
        "properties": {
            "summary": {
                "type": "string",
                "description": "Short user-facing summary (e.g. 'Added Scottish Widows SIPP £50k')."
            },
            "records_created": {
                "type": "array",
                "items": {
                    "type": "object",
                    "required": ["type", "id"],
                    "properties": {
                        "type": { "type": "string" },
                        "id": { "type": "integer" }
                    }
                },
                "description": "Structured list of records created during the capture sub-conversation."
            }
        }
    }
}
```

### Tool stripping

Both tools are tagged `internal: true` in the orchestrator's handoff registry. When the invoker receives them, it consumes them and does not emit them to the SSE stream. The frontend sees only the persona's text output (acknowledgments, questions, final answers).

---

## Persistence

### `ai_messages.persona`

- New nullable column, type `enum('advice', 'data_capture')`.
- Null for pre-existing rows (no backfill).
- Populated going forward by the orchestrator.

### `ai_conversations.persona_state`

- New nullable JSON column.
- Structure:
    ```json
    {
        "current": "advice" | "capturing",
        "pending_advice_question": "string | null",
        "capture_context": {
            "reason": "string",
            "entity_types": ["string"],
            "fields_needed": ["string"],
            "originating_focus": "string | null"
        } | null,
        "turns_in_capture": 0
    }
    ```
- Default for new rows: `{"current": "advice", "pending_advice_question": null, "capture_context": null, "turns_in_capture": 0}`.
- Migrated with a backfill applying the default to existing rows.

### Migration safety

- Both columns are additive. No destructive change.
- Enum for `persona` uses its own migration, not an `ALTER ENUM` on an existing column, to avoid the value-truncation issue called out by `AutoRiskCalculatorTest` in CSJTODO.
- Rollback path: feature flag gate means the columns are written only when the flag is on. If we disable the flag, new rows stop writing these columns; existing rows with values stay as-is.

---

## Error handling

- **Malformed `delegate_to_capture` params** — missing `reason` or empty `entity_types`. Orchestrator logs a `warning` with the raw params, ignores the handoff, returns the advice Fyn text response to the user as a normal turn.
- **Capture mode lock-in** — if data-capture Fyn fails to emit `capture_complete` after `turns_in_capture >= 6` (configurable via `config('fyn.capture_max_turns')`), orchestrator force-returns to `advice`, writes a `warning` log entry, drops `pending_advice_question`, emits a user-visible fallback: *"Let me come back to what you were asking — it's easier if you add those details on the page rather than here."* plus `navigate_to_page` to the appropriate module.
- **User cancellation** — pre-invocation regex check on the user's raw message while `persona_state.current = capturing`. Match list in `config('fyn.cancel_patterns')`: `/^(stop|cancel|never mind|forget it|nah|skip)/i`. If matched, flip back to `advice` without running data-capture.
- **Invoker failure** — if LLM call fails (timeout, rate limit, parse error), orchestrator retries once with jitter, then surfaces the error via the existing `HasAiChat` error handler.
- **Rollback path** — feature flag `fyn.persona_split_enabled`. When `false`, `AiChatController` routes to the existing `CoordinatingAgent::chat()` path exactly as today. Columns remain untouched. Flag can be flipped off mid-incident without a redeploy.

---

## Onboarding UX overhaul (within `OnboardingChatDirector`)

The director stays. The onboarding UX improvements land as extensions to the director and its state machine. None of this work depends on the persona-split orchestrator — it can ship independently gated behind the existing `onboarding.fyn_flow_enabled` flag.

### Controller wiring

`AiChatController::chat()` keeps its existing branch (`app/Http/Controllers/Api/AiChatController.php:149-162`) and adds one more:

```php
$inOnboarding = $user->onboarding_completed === false
    && $user->onboarding_fyn_step !== null
    && (bool) config('onboarding.fyn_flow_enabled', true);

$splitEnabled = (bool) config('fyn.persona_split_enabled', false);

$generator = match (true) {
    $inOnboarding          => $this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute),
    $splitEnabled          => $this->orchestrator->dispatch($user, $conversation, $message, $currentRoute),
    default                => $this->coordinatingAgent->chat($user, $conversation, $message, $currentRoute),
};
```

Three branches. Director is invoked for onboarding regardless of the persona-split flag. Orchestrator is invoked for post-onboarding when the flag is on. CoordinatingAgent is the fallback when the flag is off. Rollback from persona-split bugs is a flag flip.

### Director extensions

The following land as code changes in `OnboardingChatDirector`, `OnboardingStateMachine`, and their direct collaborators. None of them touches the orchestrator.

1. **New state machine states** — `STATE_PROFILE_REVIEW_FAMILY` after `base_dependants`, `STATE_PROFILE_REVIEW_EXPENDITURE` after `expenditure`. Each pause state emits a `layout: 'standard'` SSE event, renders a confirmation prompt, and on confirm emits a `layout: 'wide'` event before advancing.
2. **Spouse skip link** — `STATE_BASE_SPOUSE` now emits a `skip_link` metadata object alongside the bubble question. Frontend renders as a raspberry-coloured inline text link. Click posts an `action: 'skip'` via the action endpoint (see Actions below), which the director interprets as a jump to `STATE_BASE_DEPENDANTS`.
3. **Multi-job capture loop** — new `STATE_BASE_EMPLOYMENT_MORE` state. After the first job is captured, director asks "Any other jobs to add?" with yes/no bubbles. Yes → loops back to `STATE_BASE_EMPLOYMENT`. No → advances to `STATE_EXPENDITURE`.
4. **Employment bubbles** — rename `Employed` → `Full-time`, remove `Other`.
5. **Conversational retraction** — the asset-capture prompt layer (`OnboardingPromptBuilder::assetCaptureInstructions`) gains a retraction block. When the user contradicts a prior answer ("actually I'm married"), the LLM emits an `update_profile` or `update_record` tool call. Director confirms in-chat with a brief before→after acknowledgment.
6. **Fact parking** — new `ai_conversations.onboarding_parked_facts` JSON column. Every user message passes through a new `OnboardingFactExtractor` service that regex-extracts structured facts (personal, spouse, dependants, employment, expenditure buckets) and merges them into the parking column. Each state handler consults parking before emitting its question:
    - All fields present → silently apply to backing record, advance with a brief ack.
    - Some fields present → targeted follow-up asking only for gaps ("Thanks for letting me know about Angela — could I get her email?").
    - Nothing parked → full question as today.
   No separate `OnboardingMemoryExtractor` — the parking column IS the memory. History-scan behaviour (for the resume greeting) queries the parking column directly.
7. **Resume-from-where-left-off** — fixes the broken "welcome back" flow. New `POST /api/ai-chat/conversations/{id}/action` endpoint with body `{action: 'resume' | 'continue' | 'restart' | 'skip'}`. Action payloads are NOT persisted as `AiMessage` rows. The action route is added to `PreviewWriteInterceptor::EXCLUDED_ROUTES`. On `resume`, director emits a welcome-back greeting referencing the saved `onboarding_fyn_step` and the last assistant message, with `continue` and `restart` action bubbles. Frontend triggers a `resume` action on mount of the onboarding view when `users.onboarding_completed === false` and `onboarding_fyn_step !== null`.
8. **Wide chat layout + dashboard blur** — new Vue component `FynOnboardingChat.vue` wraps the chat UI for the onboarding flow. Defaults to wide mode (Tailwind `max-w-4xl`, ≈ 56rem). Pause states (layout: 'standard') shrink it to `w-[525px]` to match the existing `AiChatPanel.vue` width. `AppLayout.vue` applies `filter: blur(4px)` to the dashboard content while onboarding chat is wide. No icons on the pill (per CLAUDE.md §14).
9. **`ProfileReviewPanel.vue`** — new read-only component showing the captured profile fields (personal, family, employment, expenditure). Rendered when onboarding layout is standard. Edits happen via chat (the user tells Fyn "my DOB is wrong, it's…" and the retraction handler amends the record).
10. **Prompt token budget** — director resets the prompt accumulator at each pause state so the subsequent LLM call starts fresh.

### Post-expenditure journey handover

When `STATE_PROFILE_REVIEW_EXPENDITURE` is confirmed, the director advances to `STATE_ASSET_CAPTURE` with `onboarding_fyn_selection` already set. The existing `OnboardingPromptBuilder::buildAssetCapturePrompt($user, $focus)` call receives the correct focus — retirement users get pension-focused prompts, protection users get protection-focused, etc. No new "journey handover" machinery is required; the existing delegation is already focus-tagged.

### Test coverage for onboarding UX

- `StateMachineWalkthroughTest` — fixtures updated to walk through the new pause states and the multi-job loop. Assertions unchanged.
- `AssetCaptureOffScriptFilterTest` (FR-M14) — passes unchanged. Filter stays in the director.
- `AssetCaptureMultiEntityTest` — passes unchanged.
- `OnboardingFactParkingTest` — new. Covers the extract-and-park logic, gap-filling follow-ups, and silent-advance-when-complete behaviour.
- `OnboardingResumeTest` — new. Covers welcome-back greeting, continue action, restart action (clears messages and resets step).
- `SpouseSkipTest` — new. Covers skip action advancing past the spouse block.
- `MultiJobCaptureTest` — new. Covers the employment loop and the bubble config changes.
- `ProfileReviewPauseTest` — new. Covers the two pause states and layout events.
- `RetractionTest` — new. Covers the retract-and-confirm flow.

---

## Routing with classifier fast-path

Tool-call handoff remains the **primary** routing mechanism. Advice Fyn is the default front door; it reasons about its context and emits `delegate_to_capture` when it needs a write. This is never bypassed for ambiguous or advice-shaped messages.

The fast-path uses the **existing `App\Services\AI\QueryClassifier`** (not a new class). `QueryClassifier` already classifies user messages against `QuerySchemas::KEYWORD_PATTERNS` and produces a `classification` array with a `primary` type. The `DATA_ENTRY` primary type already matches structural verbs like add / create / update.

### Integration point

The orchestrator calls `QueryClassifier::classify($message)` once at the top of `dispatch()` (for non-capturing states). When:

1. `classification['primary'] === QuerySchemas::DATA_ENTRY` AND
2. `str_word_count($message) <= 40` AND
3. The message contains no advice-shaped phrases (see below)

…then preselect the `data_capture` persona, bypassing the advice invocation.

Otherwise fall through to `advice`. The classifier's existing result is then also passed into `AdvicePromptBuilder::build()` as today, so the advice prompt's query-knowledge and required-tools layers continue to benefit from the classification.

### Advice-phrase absence rule

Even when `primary === DATA_ENTRY`, the message must NOT contain any of: `should I`, `what about`, `how much`, `am I`, `can you explain`, `why`, `recommend`, `advice`, `compare`, `projection`, `forecast`. This rule is encoded in `QuerySchemas::isAdviceShaped($message): bool` (new method on the existing class).

### What the fast-path never does

- Never runs when `persona_state.current = capturing`. That path is already deterministic.
- Never overrides an advice Fyn `delegate_to_capture`. That tool call is authoritative.
- Never runs an LLM classifier for routing. The existing `QueryClassifier` is rule-based (regex + keyword matching). If the rules aren't confident, we default to advice.
- Never reads anything beyond the current user message. Conversation history is not inspected by the classifier.

### Observability

Every fast-path decision is logged at `info` level with the `classification` output and the chosen persona. A weekly audit job compares fast-path decisions against the handoff signals that advice Fyn would have emitted on the same messages (re-run against a sample) to catch drift. Drift above a threshold triggers an update to `QuerySchemas::KEYWORD_PATTERNS`, not a code change in the orchestrator.

---

## Chat UI changes

The chat UI work splits by location.

### Post-onboarding chat (`resources/js/components/Shared/AiChatPanel.vue` — modify)

1. **Delegate acknowledgment** — when advice Fyn emits `delegate_to_capture` with acknowledgment text (e.g. *"Let me note your pension details first — then I can answer properly."*), the acknowledgment streams as a normal assistant bubble. No special chrome.
2. **Capturing state indicator** — while `persona_state.current = capturing`, the input placeholder changes from *"Ask Fyn anything…"* to *"Tell Fyn the details…"* and a subtle pill appears above the input: horizon-500 text on a savannah-100 background reading *"Updating your records"*. No spinner, no icon (CLAUDE.md §14). The pill disappears the moment `capture_complete` fires.
3. **Capture summary** — when `capture_complete` fires AND `pending_advice_question` is null, the summary text becomes a normal assistant bubble (e.g. *"Added Scottish Widows SIPP £50k."*). When `pending_advice_question` is set, the summary is suppressed — the advice Fyn answer that follows confirms the capture implicitly.
4. **Record cards** — when `capture_complete.records_created` is non-empty, render a thin record-card row beneath the bubble: the record's display name, captured value, and a "View" link routing to the relevant module page. Uses the existing `card-sm` class. No icons.
5. **Preview-mode CTA** — when the user is in preview mode, advice Fyn's prompt instructs it not to emit `delegate_to_capture`; it replies with *"I can't save data in preview mode — but if you sign up, I'll capture this straight away."* The chat UI renders a single "Sign up" primary button beneath the bubble, styled per the design guide's CTA pattern.

### Onboarding chat (`resources/js/components/Fyn/FynOnboardingChat.vue` — new)

Wraps or composes with `AiChatPanel.vue`, but is a dedicated onboarding component so the post-onboarding panel stays unaffected.

1. **Wide layout by default** — chat container uses `max-w-4xl` (≈ 56rem). Dashboard behind is blurred (see AppLayout below).
2. **Standard layout at pause states** — when SSE receives `layout: 'standard'` event (emitted by director on entry to `STATE_PROFILE_REVIEW_FAMILY` or `STATE_PROFILE_REVIEW_EXPENDITURE`), chat container shrinks to `w-[525px]` to match the existing `AiChatPanel.vue` width. Dashboard un-blurs. `ProfileReviewPanel.vue` renders alongside.
3. **Skip link** — message metadata `skip_link: {label, color: 'raspberry'}` renders an inline text link (`<button>` styled `text-raspberry-500 underline`) after the message content. Click calls `POST /api/ai-chat/conversations/{id}/action` with `{action: 'skip'}`.
4. **Resume bubbles** — when director's welcome-back greeting arrives, two action bubbles render: `Continue` and `Start over`. Clicks call the action endpoint with the respective action.

### New profile review panel (`resources/js/components/Onboarding/ProfileReviewPanel.vue` — new)

Read-only summary of captured profile fields (name, DOB, marital, spouse, dependants, employment, expenditure). Renders while onboarding layout is `standard`. No editing UI — edits happen via the chat using conversational retraction.

### Vuex store (`resources/js/store/modules/aiChat.js` — modify)

- New state: `personaMode` (`advice` | `capturing`), derived from SSE `persona_state_change` events emitted by the orchestrator.
- New state: `onboardingLayout` (`standard` | `wide`), derived from SSE `onboarding_layout_change` events emitted by the director.
- Existing `streamingText` and `done` handlers unchanged.

### App layout (`resources/js/layouts/AppLayout.vue` — modify)

Conditional class binding: when the current route is an onboarding route AND `onboardingLayout === 'wide'`, apply `filter: blur(4px); pointer-events: none;` to the dashboard content container via a `:deep()` selector. Smooth 0.3s transition.

### Action endpoint client (`resources/js/services/aiChatService.js` — modify)

- Add `postAction(conversationId, action)` method calling `POST /api/ai-chat/conversations/{id}/action`.
- Schema accepts the new SSE event types (`persona_state_change`, `onboarding_layout_change`, `capture_complete`, `tool_use` with `internal: true`) and strips internal tool-use events before UI consumption.

### Mobile parity

The iOS mobile app uses `resources/js/mobile/views/MobileFynChat.vue` (separate component from desktop). The mobile store path shares `resources/js/store/modules/aiChat.js`, so `personaMode` and `onboardingLayout` state are inherited. However, the MOBILE equivalent of the wide/standard layout and skip-link UX must be implemented as a separate task if iOS onboarding parity is required. Default assumption: mobile inherits the backend behaviour but uses its existing mobile chat layout; onboarding wide-layout visual treatment is NOT in scope for mobile in this release.

---

## Testing

### Unit

- `FynPersonaOrchestrator::dispatch()` — each state transition:
    - `advice → advice` (no handoff)
    - `advice → capturing` via `delegate_to_capture`
    - `capturing → capturing` (multi-turn capture)
    - `capturing → advice` via `capture_complete` with `pending_advice_question` (triggers re-invocation)
    - `capturing → advice` via `capture_complete` without `pending_advice_question` (no re-invocation)
    - `capturing → advice` via cancel pattern
    - `capturing → advice` via `turns_in_capture` timeout
    - Malformed `delegate_to_capture` → ignored, logged
- `FynPersonaRegistry` — schema integrity test. Every persona entry has a real class for `prompt_builder`, every tool name in `allowed_tools` and `handoff_tools` exists in `AiToolDefinitions` / `XaiToolDefinitions`.
- `DataCapturePromptBuilder::build()` — called with an onboarding-focus `CaptureContext`, produces a string that is byte-identical to the current `OnboardingPromptBuilder::buildAssetCapturePrompt()` output for equivalent inputs. Regression guard during the onboarding migration step (protects FR-M14 behaviour when the filter moves).
- `CaptureContext` — value object construction, immutability.

### Feature (Pest)

- Full KYC-gate scenario: user asks for retirement advice, no pensions loaded. Mock LLM to emit `delegate_to_capture`, then `create_pension` + `capture_complete`, then final advice. Assert:
    - Exactly 3 `AiMessage` rows created, with personas `[advice, data_capture, advice]`.
    - `AiConversation.persona_state` ends at `{"current": "advice", "pending_advice_question": null}`.
    - `DCPension` record created with the captured values.
    - Only one turn loaded the advice prompt; capture turn loaded the short prompt (asserted via prompt length or a builder spy).
- Mid-conversation inline capture: user adds an ISA mid-advice. Assert no `pending_advice_question`, no re-invocation after capture.
- Cancel pattern: user types "never mind" mid-capture. Assert persona flips back without invoking data-capture.
- Capture timeout: mock data-capture to never emit `capture_complete`. Assert force-flip after 6 turns, fallback message emitted.

### Classifier fast-path

- Unit: each rule-matching predicate (verb detection, entity-keyword co-occurrence, advice-phrase absence, length cap).
- Unit: confident-match cases (e.g. "add my Nationwide ISA £5k") → `data_capture`. Non-confident cases ("Should I add an ISA?") → `advice`. Boundary case (message exactly 40 words, starts with "add") → `data_capture`. 41-word message → `advice`.
- Integration: a confident-match message bypasses advice Fyn entirely, then `capture_complete` returns to advice. Assert exactly one LLM call is made for the capture and zero for advice.
- Drift audit: a Pest-level property test that runs a sample of advice-shaped messages through both paths and flags any where the fast-path would have misrouted.

### Onboarding migration

- `StateMachineWalkthroughTest` must pass on every commit of the migration. Adapt fixtures only, not assertions.
- `AssetCaptureOffScriptFilterTest` (FR-M14) must pass after the filter moves from director to orchestrator wrapper. Test-suite update is a file move plus import update; no behaviour change.
- `AssetCaptureMultiEntityTest` must pass on every commit.
- New test: `OnboardingOrchestratorTest` covers the orchestrator's `onboarding` mode dispatching to data-capture with the correct `CaptureContext.originating_focus`.

### Chat UI

- Component tests on `ChatWindow.vue` (placeholder swap on `personaMode` change; pill render/unrender on `capturing` enter/exit).
- Component tests on `MessageBubble.vue` (record-card row rendered when `records_created` non-empty; suppressed when `pending_advice_question` was set).
- Vuex test: `aiChat.personaMode` transitions on incoming SSE events.

### New estate tools

- Feature test: `create_will` via Fyn stores a `Will` row with correct user_id, executor, beneficiaries, residuary estate.
- Feature test: `create_power_of_attorney` via Fyn stores a `PowerOfAttorney` row with correct type (`property_and_finance` | `health_and_welfare`), attorney_name, registered status.
- Feature test: `update_will` modifies an existing will without creating a new row.
- Regression: the existing Will Builder UI (`/estate/will-builder`) continues to work against the same model — tool additions do not change the model's schema beyond the new LPA-related fields.

### Regression

- All existing `HasAiChat` and `CoordinatingAgent` tests must pass.
- Full smoke test suite from `April/April20Updates/deploy-PRD-P0.md` (tests 1, 2A, 2B, 3, 4, 5, 6, 6b, 7) must pass on dev after enabling `fyn.persona_split_enabled = true`.

---

## Feature flag and rollout

- Flag: `fyn.persona_split_enabled`. Default `false`. Controlled via env var `FYN_PERSONA_SPLIT=true`.
- Rollout plan:
    1. Ship dark to `onboardingFyn → dev` (csjones.co/fynla). Flag off. Verify no regressions on advice flow and no regressions on initial onboarding.
    2. Enable flag on dev. Run full smoke test suite plus new persona-split scenarios (KYC-gate, inline capture, cancel, timeout).
    3. After 48h of dev stability, merge through to `main` with flag still off. Deploy to fynla.org.
    4. Enable flag on production in a follow-up commit. Monitor `storage/logs/laravel.log` for orchestrator warnings (malformed handoffs, capture timeouts) for 24h.
    5. Once stable for 7 days, remove the flag and the fallback `CoordinatingAgent::chat()` branch in `AiChatController`.

---

## Open questions (resolve during implementation planning)

- Does the persona registry need runtime mutability (hot-reload from DB or config cache), or is file-based config sufficient? File-based for v1. Add runtime mutability only when a second non-Fyn persona (e.g. advisor product) needs it.
- For `create_will`, should Fyn be allowed to capture beneficiary shares conversationally, or should the tool force a redirect to the Will Builder for share allocation? Leaning: capture conversationally for straightforward cases (single residuary beneficiary, no specific gifts); redirect to Will Builder when the user mentions multiple beneficiaries with percentage splits.

## Resolved decisions (post-audit amendment, 2026-04-21)

- **LPA model** — use existing `App\Models\Estate\LastingPowerOfAttorney`. No new model. Tool schema uses existing column names (`lpa_type`, values `property_financial` / `health_welfare`) with primary attorney captured via the existing `LpaAttorney` related table.
- **Will column addition** — add `residuary_beneficiary`, `guardian_for_minors`, `specific_gifts` as new unconditional migration on the `wills` table.
- **Onboarding component** — new `FynOnboardingChat.vue` component wraps the Fyn flow; existing `AiChatPanel.vue` stays for post-onboarding chat.
- **Action endpoint** — `POST /api/ai-chat/conversations/{id}/action` with `{action: 'resume' | 'continue' | 'restart' | 'skip'}`. Actions not persisted as `AiMessage`. Added to `PreviewWriteInterceptor::EXCLUDED_ROUTES`.
- **Director retention** — `OnboardingChatDirector` and `OnboardingPromptBuilder` stay. Not absorbed, not deleted. Onboarding UX overhaul lands as extensions to the director.
- **Classifier** — reuse existing `QueryClassifier` promoted to the orchestrator level. No new `FynIntentClassifier`.
- **Memory vs parking** — single source of truth: `ai_conversations.onboarding_parked_facts` JSON column populated per-turn by `OnboardingFactExtractor`. No separate `OnboardingMemoryExtractor`.
- **Feature flags** — two flags only: `FYN_PERSONA_SPLIT` (master for post-onboarding orchestrator) and `FYN_CLASSIFIER_FAST_PATH` (classifier kill switch). No third flag — the onboarding UX work ships under the existing `onboarding.fyn_flow_enabled` flag.

---

## Out of scope (explicit)

- Additional personas beyond `advice` and `data_capture`.
- Backfill of historical `ai_messages.persona`.
- Mobile app changes — the orchestrator sits behind the same `AiChatController`, so mobile inherits the behaviour for free via the same SSE event schema. No dedicated iOS build change required in this release.
