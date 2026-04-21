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

- Additional personas beyond `advice` and `data_capture`. The registry must accommodate them, but none are built here.
- Backfill of historical `ai_messages.persona`. The new column is null for pre-existing rows; no retroactive tagging.
- Mobile app changes. The orchestrator sits behind the same `AiChatController`, so mobile inherits the behaviour without a dedicated iOS build change.

## Decisions made during brainstorming

- **Mode fork:** wide scope. Two persistent personas (`advice`, `data_capture`), intent-driven. Lifecycle flag (`users.onboarding_completed`) is irrelevant to persona selection.
- **Routing:** the orchestrator is the single dispatcher for all post-registration Fyn turns. It covers three modes: `onboarding` (driven by the state machine absorbed from the director), `advice` (default post-onboarding), `capturing` (mid-handoff). The old `$inOnboarding` branch in `AiChatController` is removed.
- **Tool ownership:** option A. All write tools (create / update / delete / capture / profile) live exclusively on data-capture Fyn. Advice Fyn has none of them and instead emits `delegate_to_capture` when a write is needed.
- **Handoff mechanism:** structured tool calls primary. Advice → data-capture via `delegate_to_capture`. Data-capture → advice via `capture_complete`. A lightweight rule-based classifier runs as an **optional fast-path** for unambiguous intent (see "Routing with classifier fast-path" below). The classifier never overrides an emitted handoff tool call; it only pre-selects a persona when the signal is strong enough, and falls through to advice Fyn when unsure.
- **UX:** the user sees one Fyn. Handoff tool calls are stripped from the SSE stream. The chat UI renders subtle status cues during handoffs (see "Chat UI changes" below).

---

## Architecture

```
AiChatController
    │
    └── FynPersonaOrchestrator  (single dispatcher for all post-registration Fyn turns)
            │
            ├── reads AiConversation.persona_state
            ├── if persona_state.current == "onboarding":
            │       → delegate to OnboardingStateMachine (absorbed from the old director)
            │         └── for asset_capture steps, drive data_capture persona via FynPersonaInvoker
            │
            ├── else runs classifier fast-path on the user message
            │       ├── unambiguous "add/update/delete X" → preselect data_capture
            │       ├── unambiguous advice/question       → preselect advice
            │       └── anything else / unsure            → fall through to advice (default)
            │
            ├── dispatches to FynPersonaInvoker
            │       │
            │       ├── persona=advice        → AdvicePromptBuilder (renamed SystemPromptBuilder)
            │       └── persona=data_capture  → DataCapturePromptBuilder
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
- **`App\ValueObjects\CaptureContext`** — immutable value object carrying `reason: string`, `entity_types: array<string>`, `fields_needed: array<string>`, `pending_advice_question: ?string`, `originating_focus: ?string` (populated when the orchestrator is in `onboarding` mode so the data-capture prompt preserves the user's selected journey focus).

### Renamed

- **`App\Services\AI\SystemPromptBuilder` → `App\Services\AI\AdvicePromptBuilder`.** No behaviour change. All references updated (searches: `SystemPromptBuilder`, bindings in `AppServiceProvider`, `CoordinatingAgent`, `HasAiChat`, test classes). The rename is part of this change; without it the naming lies.

### Migrated / consolidated

- **`App\Services\Onboarding\OnboardingChatDirector` is absorbed into the orchestrator.** The state machine (`OnboardingStateMachine`) moves under the orchestrator as the `onboarding` mode's state driver. The director class itself is deleted. Its FR-M14 buffered off-script filter moves to the orchestrator's handoff wrapper where it keeps protecting data-capture turns. See "Onboarding migration" below for the step-by-step consolidation plan.
- **`App\Services\Onboarding\OnboardingPromptBuilder` is consolidated into `DataCapturePromptBuilder`.** Asset-capture turns during initial onboarding use the same builder as post-onboarding captures, with an onboarding-focus `CaptureContext`. The old class is deleted once all callers migrate.

### Unchanged

- `App\Traits\HasAiChat` and `App\Agents\CoordinatingAgent` — still used internally by `FynPersonaInvoker` for prompt building, streaming, and tool-call parsing primitives. The orchestrator is a layer above, not a replacement.

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

The audit of write tools surfaced two estate-planning gaps. Both are in scope for this change because they belong to data-capture Fyn's responsibility and their absence would block the "user can give Fyn anything and Fyn captures it" promise:

- **`create_will` / `update_will`** — `App\Models\Estate\Will` exists as a model; the Will Builder lives at `/estate/will-builder`. There is no AI tool for creating or updating wills today. New tool definitions needed in `AiToolDefinitions` and `XaiToolDefinitions`, with handler methods on `CoordinatingAgent`. Scope: core fields (executor, beneficiaries, residuary estate, guardian for minors, specific gifts). Wrap the existing will-builder service so the handler does not reimplement validation.
- **`create_power_of_attorney` / `update_power_of_attorney`** — no model, no controller, no tool. Need to scope in this work: a `PowerOfAttorney` model (fields: `type` [property_and_finance | health_and_welfare], `attorney_name`, `replacement_attorney_name?`, `status` [draft | registered], `registered_date?`, `restrictions_notes?`), migration, controller, validation, and the two tools. LPA is part of the Estate Planning module in the navigation already (`/estate/power-of-attorney`). Surface behind the same `fyn.persona_split_enabled` flag so the new model only becomes active when the persona split rolls out.

The persona registry lists these four tool names above. They must ship in the same release or the registry will throw the integrity test introduced in the Testing section.

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

## Onboarding migration

The orchestrator absorbs `OnboardingChatDirector`'s responsibilities so all Fyn turns post-registration flow through a single entry point. The migration is staged to protect the in-flight FR-M9..FR-M15 work and to keep smoke tests passing the whole way.

### Step 1 — wire the orchestrator for post-onboarding only (flag off by default)

`AiChatController::chat()` keeps its existing branch (`app/Http/Controllers/Api/AiChatController.php:149-162`) and gains one more:

```php
$inOnboarding = $user->onboarding_completed === false
    && $user->onboarding_fyn_step !== null
    && (bool) config('onboarding.fyn_flow_enabled', true);

$splitEnabled = (bool) config('fyn.persona_split_enabled', false);

$generator = match (true) {
    $inOnboarding && $splitEnabled
        => $this->orchestrator->dispatch($user, $conversation, $message, $currentRoute, mode: 'onboarding'),
    $inOnboarding
        => $this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute),
    $splitEnabled
        => $this->orchestrator->dispatch($user, $conversation, $message, $currentRoute),
    default
        => $this->coordinatingAgent->chat($user, $conversation, $message, $currentRoute),
};
```

Rollout with the flag off is behaviourally identical to today.

### Step 2 — `onboarding` mode inside the orchestrator

When dispatched with `mode: 'onboarding'`, the orchestrator:

1. Loads `OnboardingStateMachine` (unchanged — still owns state transitions, bubble matching, journey remap).
2. For non-capture states (`intro`, `base_personal`, `base_spouse`, `base_dependants`, `expenditure`, `journey_selection`, `add_more`), drives the state machine directly as the current director does.
3. For `asset_capture` state, invokes the `data_capture` persona via `FynPersonaInvoker` with a `CaptureContext` whose `originating_focus` matches the user's `onboarding_fyn_selection`. This is the consolidation point where `OnboardingPromptBuilder` is replaced by `DataCapturePromptBuilder`.
4. Applies the FR-M14 buffered sentence-level off-script filter to data-capture SSE output regardless of whether the call originated from onboarding or post-onboarding. The filter moves out of `OnboardingChatDirector::handleAssetCaptureTurn` and into the orchestrator's invoker-side wrapper, so it protects every data-capture turn, not just onboarding ones.

### Step 3 — cutover and delete the director

Once the smoke tests pass on dev with both `onboarding.fyn_flow_enabled = true` and `fyn.persona_split_enabled = true`:

1. Delete `App\Services\Onboarding\OnboardingChatDirector`.
2. Delete `App\Services\Onboarding\OnboardingPromptBuilder`.
3. Remove the `$this->onboardingDirector` injection from `AiChatController`.
4. Remove the two-flag match in `AiChatController::chat()`; replace with a single call to `$this->orchestrator->dispatch(...)` which picks `onboarding` vs `advice` internally from `$user->onboarding_completed` and `persona_state`.
5. Move `OnboardingStateMachine` into the `App\Services\AI\Onboarding` namespace to reflect its new home under the orchestrator umbrella (code move only; no logic change).

This is the last commit in the release. Revert is a git revert, not a config flip.

### Test coverage during migration

- `StateMachineWalkthroughTest` must pass on every commit.
- `AssetCaptureOffScriptFilterTest` must pass on every commit — moving the filter from director to orchestrator includes a test update to call the new wrapper, but the behavioural assertions are unchanged.
- `AssetCaptureMultiEntityTest` must pass on every commit.
- `SpouseCollisionTest`, `OnboardingChatDirectorFixesTest` — port assertions to the new orchestrator-based equivalents before deleting the director. No drop in coverage.

---

## Routing with classifier fast-path

Tool-call handoff remains the **primary** routing mechanism. Advice Fyn is the default front door; it reasons about its context and emits `delegate_to_capture` when it needs a write. This is never bypassed for ambiguous or advice-shaped messages.

A lightweight rule-based classifier sits in front of the invoker as an **optional fast-path** for obviously one-shot data-entry messages. Its job is to avoid paying the 1,600-token advice tax when the user clearly isn't asking for advice.

### Signals (rule-based, no LLM)

1. **Structural verb detection** at the start of the message (after normalising whitespace and punctuation): matches against `/^(add|create|record|save|log|put in|note (down )?|enter|update|change|edit|delete|remove)\b/i`.
2. **Entity-keyword co-occurrence**: the message contains at least one keyword from a known entity vocabulary — `isa`, `sipp`, `pension`, `mortgage`, `property`, `house`, `flat`, `goal`, `life event`, `trust`, `will`, `power of attorney`, `lpa`, `income`, `expenditure`, `salary`, `savings account`, `investment account`, plus the 23 known `create_*` / `update_*` / `capture_*` tool names mapped to their natural-language synonyms.
3. **Absence of advice-shaped phrases**: the message does NOT contain `should I`, `what about`, `how much`, `am I`, `can you explain`, `why`, `recommend`, `advice`, `compare`, `projection`, `forecast`.
4. **Length**: message is ≤ 40 words. Longer messages route to advice Fyn so reasoning isn't skipped for nuanced requests that happen to start with "add".

A message that satisfies (1) AND (2) AND (3) AND (4) is a "confident data-entry" match. Anything else falls through to advice Fyn as default.

### What the fast-path does

When a confident match is detected, the orchestrator:

1. Skips the initial advice Fyn invocation.
2. Invokes data-capture Fyn directly with a `CaptureContext` where `reason = "classifier fast-path"`, `entity_types` is inferred from the matched keywords, `pending_advice_question = null`.
3. After `capture_complete`, returns to `advice` state without re-invocation (same as the inline-capture case in Example 2).

### What the fast-path never does

- Never runs when `persona_state.current = capturing` or `onboarding`. Those paths are already deterministic.
- Never overrides an advice Fyn `delegate_to_capture`. That tool call is authoritative.
- Never runs an LLM classifier. Rule-based only — no pre-inspection cost, no silent misclassification from a probabilistic model. If the rules aren't confident, we default to advice.
- Never reads anything beyond the current user message. Conversation history is not inspected by the classifier; that's the invoker's job.

### Why not a pure LLM classifier

LLM-based routing was explicitly considered and rejected during brainstorming. The core objection: misclassification is silent. A rule-based fast-path has a deterministic failure mode (falls through to advice Fyn), so the worst case is a wasted advice-prompt tax on a message that could have fast-pathed — not a broken conversation.

### Observability

Every fast-path decision is logged at `info` level with the matched rules and the chosen persona. A weekly audit job compares fast-path decisions against the handoff signals that advice Fyn would have emitted on the same messages (re-run against a sample) to catch drift between the rules and the advice-side reasoning. Drift above a threshold triggers a rule update, not a code change — the classifier stays rule-based.

---

## Chat UI changes

The persona split creates three new user-visible moments. The chat UI needs to render them with enough signal that the user understands what's happening, but not so much that the "one Fyn" illusion breaks.

### 1. Delegate acknowledgment

When advice Fyn emits `delegate_to_capture` with acknowledgment text (e.g. *"Let me note your pension details first — then I can answer properly."*), the acknowledgment streams as a normal assistant bubble. No special chrome. It's written by advice Fyn; it should look like an advice Fyn message.

### 2. Capturing state indicator

While `persona_state.current = capturing`, the chat input placeholder changes from *"Ask Fyn anything…"* to *"Tell Fyn the details…"* and a subtle pill appears above the input: a horizon-500 text label *"Updating your records"* on a savannah-100 background. No spinner, no icon (icons are banned on the chat surface per CLAUDE.md §14). The pill disappears the moment `capture_complete` fires.

### 3. Capture summary

When `capture_complete` fires AND `pending_advice_question` is null, the summary text becomes a normal assistant bubble (e.g. *"Added Scottish Widows SIPP £50k."*). When `pending_advice_question` is set, the summary is **suppressed** — the advice Fyn answer that follows implicitly confirms the capture (*"Now that I have your SIPP on record, here's what I'd suggest…"*), which reads more naturally than two acknowledgments back to back.

### 4. Record cards (optional polish, keep in scope)

When `capture_complete.records_created` is non-empty, the frontend optionally renders a thin record-card row beneath the assistant bubble: the record's display name, the captured value, and a "View" link that routes to the relevant module page. Uses the existing `card-sm` class from `fynlaDesignGuide.md`. No icons.

### 5. Preview-mode messaging

When the user is in preview mode, advice Fyn is prompted (in its system prompt layer) not to emit `delegate_to_capture`. Instead it responds with *"I can't save data in preview mode — but if you sign up, I'll capture this straight away."* The chat UI renders this as a normal assistant bubble followed by a single "Sign up" primary button, styled per the design guide's CTA pattern.

### Frontend components touched

- `resources/js/components/AiChat/ChatWindow.vue` — the state pill and the input placeholder swap.
- `resources/js/components/AiChat/MessageBubble.vue` — render path for `capture_complete` summaries and optional record cards.
- `resources/js/store/modules/aiChat.js` — new state: `personaMode` (`advice` | `capturing` | `onboarding`), derived from incoming SSE events. The existing `streamingText` and `done` handlers stay.
- `resources/js/services/aiChatService.js` — reads and ignores the stripped internal tool calls. Schema must accept the new SSE event types without breaking on them.

### Mobile parity

The iOS `ChatWindow` components reuse the same Vuex store and SSE event schema, so mobile inherits these changes without dedicated iOS work — consistent with the "Mobile app changes" non-goal.

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

- Does the persona registry need runtime mutability (hot-reload from DB or config cache), or is file-based config sufficient? Leaning: file-based for v1. Add runtime mutability only when a second non-Fyn persona (e.g. advisor product) needs it.
- Should the LPA model live in `App\Models\Estate\PowerOfAttorney` or `App\Models\Estate\LastingPowerOfAttorney`? Leaning: `PowerOfAttorney` (shorter, navigation already uses `/estate/power-of-attorney`).
- For `create_will`, should Fyn be allowed to capture beneficiary shares conversationally, or should the tool force a redirect to the Will Builder for share allocation? Leaning: capture conversationally for straightforward cases (single residuary beneficiary, no specific gifts); redirect to Will Builder when the user mentions multiple beneficiaries with percentage splits.
- Does the classifier fast-path get its own kill switch (`fyn.classifier_fast_path_enabled`), or does it ride on `fyn.persona_split_enabled`? Leaning: separate flag so we can disable the fast-path without disabling the whole split in an incident.

---

## Out of scope (explicit)

- Additional personas beyond `advice` and `data_capture`.
- Backfill of historical `ai_messages.persona`.
- Mobile app changes — the orchestrator sits behind the same `AiChatController`, so mobile inherits the behaviour for free via the same SSE event schema. No dedicated iOS build change required in this release.
