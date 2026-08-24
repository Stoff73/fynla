# Handoff stub tools (2)

These two tools are special: they are NEVER in the main `getTools()` catalogue. They are surfaced **only** by `AiToolDefinitions::handoffTools($provider)` and passed via `toolsListOverride` from `OnboardingChatDirector::handleInlineCapture` (advice-mode write-intent handoff path).

Their handlers in `executeTool` are **stubs** — they don't run any business logic. They simply return an `'action' => 'handoff'` payload that the streaming layer (`HasAiChat::stream`) emits as a synthetic `handoff` SSE event. That event is consumed internally by `OnboardingChatDirector::handleInlineCapture` and never reaches the frontend (INV-2.4.1).

The contract names live as constants on `App\Services\AI\HandoffContract`:
- `HandoffContract::DELEGATE_TO_CAPTURE` = `'delegate_to_capture'`
- `HandoffContract::CAPTURE_COMPLETE` = `'capture_complete'`

> Source:
> - Constants: `app/Services/AI/HandoffContract.php`.
> - Schema builder: `AiToolDefinitions::handoffTools()` — `app/Services/AI/AiToolDefinitions.php:1176-1250`.
> - Validator: `app/Services/AI/HandoffPayloadValidator.php`.
> - Dispatch stub: `app/Agents/CoordinatingAgent.php:839-848`.

---

## 1. `delegate_to_capture` (Advice-mode write-intent handoff)

**Purpose**: Advice Fyn emits this when it cannot answer without data the user has not supplied, OR when the user asks for an inline capture mid-conversation. The orchestrator (`AdviceFyn::wrapStream` ⇒ `OnboardingChatDirector::handleInlineCapture`) consumes it, runs the data-capture turn against the same direct-write handlers in `CoordinatingAgent`, then returns control to advice Fyn.

**Schema** (`AiToolDefinitions.php:1179-1203`):

```php
[
    'name' => \App\Services\AI\HandoffContract::DELEGATE_TO_CAPTURE,  // 'delegate_to_capture'
    'description' => 'Internal. Emit this when you (advice Fyn) cannot answer without data the user has not supplied, or when the user asks for an inline capture mid-conversation. Never shown to the user. The orchestrator will hand off to data-capture Fyn and re-invoke you once capture is complete.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'reason' => [
                'type' => 'string',
                'description' => 'Why capture is needed (e.g. "retirement advice blocked on missing pension data").',
            ],
            'entity_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Record types to capture (dc_pension, savings_account, property, etc.). Drawn from data_capture persona allowed_tools.',
            ],
            'fields_needed' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional. Specific fields required to unblock the advice answer.',
            ],
        ],
        'required' => ['reason', 'entity_types'],
        'additionalProperties' => false,
    ],
],
```

**Dispatch stub** (`CoordinatingAgent.php:839-843`):

```php
// Handoff tools — stubbed so HasAiChat doesn't error. The
// synthetic 'handoff' SSE event yielded downstream from this
// result is consumed by OnboardingChatDirector::handleInlineCapture.
'delegate_to_capture' => [
    'action' => 'handoff',
    'handoff_type' => 'delegate_to_capture',
    'payload' => $input,
],
```

The `'action' => 'handoff'` key is the contract that `HasAiChat::stream` watches for. Once it sees it, the stream control transfers to `OnboardingChatDirector::handleInlineCapture` which:
1. Validates the payload via `HandoffPayloadValidator`.
2. Re-runs the user's message against the onboarding capture path (with the relevant `create_*` tools available).
3. Captures the records.
4. Returns control to Advice Fyn for the actual answer.

The frontend never sees the handoff event — it just sees the eventual answer text.

---

## 2. `capture_complete` (Data-capture done signal)

**Purpose**: Data-capture Fyn emits this when it has finished capturing the records the user described. The orchestrator then returns control to advice Fyn for the answer.

**Schema** (`AiToolDefinitions.php:1204-1230`):

```php
[
    'name' => \App\Services\AI\HandoffContract::CAPTURE_COMPLETE,  // 'capture_complete'
    'description' => 'Internal. Emit this when you (data-capture Fyn) have finished capturing the records the user described. The orchestrator will return control to advice Fyn.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'summary' => [
                'type' => 'string',
                'description' => 'Short user-facing recap (e.g. "Added Scottish Widows SIPP £50k").',
            ],
            'records_created' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'id' => ['type' => ['integer', 'string']],
                    ],
                    'required' => ['type', 'id'],
                    'additionalProperties' => false,
                ],
                'description' => 'Structured list of records created or updated this sub-conversation.',
            ],
        ],
        'required' => ['summary', 'records_created'],
        'additionalProperties' => false,
    ],
],
```

**Dispatch stub** (`CoordinatingAgent.php:844-848`):

```php
'capture_complete' => [
    'action' => 'handoff',
    'handoff_type' => 'capture_complete',
    'payload' => $input,
],
```

---

## Provider-format wrapping

`AiToolDefinitions::handoffTools()` returns the right shape for each provider:

```php
public function handoffTools(string $provider = 'anthropic'): array
{
    $tools = [ /* ... DELEGATE_TO_CAPTURE + CAPTURE_COMPLETE ... */ ];

    if ($provider === 'xai') {
        return array_map(fn (array $tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['parameters'],
            ],
        ], $tools);
    }

    // Anthropic format
    return array_map(fn (array $tool) => [
        'name' => $tool['name'],
        'description' => $tool['description'],
        'input_schema' => $tool['parameters'],
    ], $tools);
}
```
