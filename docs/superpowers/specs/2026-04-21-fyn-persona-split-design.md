# Fyn Persona Split — Design Spec

**Date:** 2026-04-21
**Author:** CSJ (brainstormed with Claude)
**Stage:** Spec — awaiting implementation plan
**Status:** Draft, not yet scheduled for a branch

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

- Migrating `OnboardingChatDirector` into the persona registry. Initial onboarding keeps its state-machine flow unchanged.
- Additional personas beyond `advice` and `data_capture`. Registry must accommodate them, but none are built here.
- Classifier-based routing. Handoff is via structured tool calls only; no pre-inspection LLM classifier.
- Chat UI changes. Streaming status indicators ("Fyn is updating your records…") would polish the UX but are out of scope.
- Backfill of historical `ai_messages.persona`. New column is null for pre-existing rows; no retroactive tagging.

## Decisions made during brainstorming

- **Mode fork:** wide scope. Two persistent personas (`advice`, `data_capture`), intent-driven. Lifecycle flag (`users.onboarding_completed`) is irrelevant to routing post-initial-onboarding.
- **Routing:** hybrid. During initial onboarding (`users.onboarding_completed = false` AND an active `OnboardingProgress` row), `OnboardingChatDirector` drives unchanged. Post-onboarding, a new `FynPersonaOrchestrator` drives, with advice Fyn as the default.
- **Tool ownership:** option A. All `create_*` tools live exclusively on data-capture Fyn. Advice Fyn has none of them and instead emits `delegate_to_capture` when a write is needed.
- **Handoff mechanism:** structured tool calls. Advice → data-capture via `delegate_to_capture`. Data-capture → advice via `capture_complete`. No text heuristics, no pre-inspection classifier.
- **UX:** the user sees one Fyn. Handoff tool calls are stripped from the SSE stream (same pattern used today for onboarding director state events).

---

## Architecture

```
AiChatController
    │
    ├── if (user in initial onboarding) → OnboardingChatDirector  (unchanged)
    │
    └── else                             → FynPersonaOrchestrator
                                              │
                                              ├── reads AiConversation.persona_state
                                              ├── picks persona from FynPersonaRegistry
                                              ├── dispatches to FynPersonaInvoker
                                              │       │
                                              │       ├── persona=advice
                                              │       │     → AdvicePromptBuilder (renamed SystemPromptBuilder)
                                              │       │
                                              │       └── persona=data_capture
                                              │             → DataCapturePromptBuilder (generalised OnboardingPromptBuilder)
                                              │
                                              ├── parses tool calls from response
                                              ├── intercepts handoff tool calls (delegate_to_capture / capture_complete)
                                              ├── updates persona_state
                                              └── loops or returns per transition rules
```

---

## Components

### New

- **`App\Services\AI\FynPersonaOrchestrator`** — the dispatcher. Methods: `dispatch(User $user, AiConversation $conversation, string $userMessage): StreamedResponse`. Owns the persona state transitions. Approx 150 LOC.
- **`App\Services\AI\FynPersonaRegistry`** — config-driven registry. Each persona row: `name`, `prompt_builder_class`, `allowed_tools[]` (whitelist of tool names from `AiToolDefinitions` / `XaiToolDefinitions`), `handoff_tools[]` (tool names that trigger a transition).
- **`App\Services\AI\FynPersonaInvoker`** — executes a single persona turn. Builds the system prompt via the registered builder, calls the LLM via the existing xAI client, streams response chunks to SSE, parses tool calls. Isolates "invoke one persona" from "decide which persona". Approx 200 LOC.
- **`App\Services\AI\Prompts\DataCapturePromptBuilder`** — generalised from `App\Services\Onboarding\OnboardingPromptBuilder`. Accepts a `CaptureContext` value object. Emits a short capture-focused prompt. Preserves the FR-M14 off-script guardrails (single-sentence acknowledgment, banned keyword filter, no questions). Approx 150 LOC.
- **`App\ValueObjects\CaptureContext`** — immutable value object carrying `reason: string`, `entity_types: array<string>`, `fields_needed: array<string>`, `pending_advice_question: ?string`, `originating_focus: ?string` (used when called from `OnboardingChatDirector` to preserve module focus).

### Renamed

- **`App\Services\AI\SystemPromptBuilder` → `App\Services\AI\AdvicePromptBuilder`.** No behaviour change. All references updated (searches: `SystemPromptBuilder`, bindings in `AppServiceProvider`, `CoordinatingAgent`, `HasAiChat`, test classes). The rename is part of this change; without it the naming lies.

### Unchanged

- `App\Services\Onboarding\OnboardingChatDirector`, `OnboardingStateMachine`, `OnboardingPromptBuilder` — initial onboarding keeps its own flow. See "Coexistence with OnboardingChatDirector" below.
- `App\Traits\HasAiChat` and `App\Agents\CoordinatingAgent` — still used internally by `FynPersonaInvoker`. The orchestrator is a layer above, not a replacement.

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
            'create_savings_account',
            'create_investment_account',
            'create_holding',
            'create_pension',
            'create_protection_policy',
            'create_asset',
            'create_liability',
            'create_estate_gift',
            'create_property',
            'create_chattel',
            'create_business_interest',
            'create_goal',
        ],
        'handoff_tools' => ['capture_complete'],
    ],
];
```

Adding a future persona is one new array entry plus a prompt builder class. No changes to the orchestrator or invoker.

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

## Coexistence with `OnboardingChatDirector`

The orchestrator is **not** called during initial onboarding. `AiChatController::chat()` already has the routing branch today (`app/Http/Controllers/Api/AiChatController.php:149-162`):

```php
$inOnboarding = $user->onboarding_completed === false
    && $user->onboarding_fyn_step !== null
    && (bool) config('onboarding.fyn_flow_enabled', true);

// today:
//   $generator = $inOnboarding
//       ? $this->onboardingDirector->handleUserMessage(...)
//       : $this->coordinatingAgent->chat(...);
```

The change: when `$inOnboarding` is `false` AND `config('fyn.persona_split_enabled')` is `true`, the controller routes to `FynPersonaOrchestrator::dispatch()` instead of `CoordinatingAgent::chat()`. When the flag is `false`, the existing `CoordinatingAgent::chat()` path is preserved unchanged. The `$inOnboarding` branch is untouched — initial onboarding keeps its director regardless of the flag.

Once onboarding completes (`users.onboarding_completed` flips to `true` AND `onboarding_fyn_step` clears), the user's next message routes through the orchestrator, starting in `advice` state.

The director continues to use `OnboardingPromptBuilder` for `asset_capture` turns. A follow-up item (out of scope here, flagged in open questions below) is to migrate the director to use `DataCapturePromptBuilder` with an onboarding-focus `CaptureContext`, then delete `OnboardingPromptBuilder`. This consolidation is deliberately deferred — making the director change at the same time as introducing the orchestrator doubles the blast radius.

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
- `DataCapturePromptBuilder::build()` — called with an onboarding-focus `CaptureContext`, produces the same string as current `OnboardingPromptBuilder::buildAssetCapturePrompt()` for equivalent inputs. Regression guard so the director can adopt it later without behaviour drift.
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

### Regression

- Initial onboarding state machine (`OnboardingChatDirector`) full walkthrough via existing `StateMachineWalkthroughTest` must pass unchanged.
- `AssetCaptureOffScriptFilterTest` (FR-M14) must pass unchanged.
- All existing `HasAiChat` and `CoordinatingAgent` tests must pass.

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

- Should `capture_complete` always produce a user-visible summary message, or should the orchestrator suppress the summary when `pending_advice_question` is set (so the advice answer carries all the follow-up)? Leaning: suppress when resuming; emit when standalone.
- When advice Fyn emits `delegate_to_capture` with acknowledgment text, should that text stream to the user *before* the capture prompt runs, or be buffered until `capture_complete`? Leaning: stream immediately — the user expects instant ack.
- Should `DataCapturePromptBuilder` replace `OnboardingPromptBuilder` outright in the same change, or coexist until a follow-up? Leaning: coexist for this spec (lower blast radius), consolidate in a dedicated follow-up PR.
- Does the persona registry need runtime mutability (hot-reload from DB or config cache), or is file-based config sufficient? Leaning: file-based for v1. Add runtime mutability only when a second non-Fyn persona (e.g. advisor product) needs it.
- How does the orchestrator behave for preview users? `PreviewWriteInterceptor` already blocks writes — data-capture Fyn's `create_*` calls will fail. Proposal: orchestrator detects preview mode and prevents advice Fyn from emitting `delegate_to_capture` in the first place; advice Fyn gets a prompt-layer instruction to offer a graceful "I can't save data in preview — sign up to make it stick" message instead.

---

## Out of scope (explicit)

- UI redesign of the chat window.
- Classifier-based routing.
- Migrating `OnboardingChatDirector` into the orchestrator.
- Additional personas.
- Backfill of historical `ai_messages.persona`.
- Mobile app changes (the orchestrator sits behind the same `AiChatController`, so mobile inherits the behaviour for free — no dedicated mobile changes).
