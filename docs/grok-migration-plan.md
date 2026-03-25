# Grok AI Migration Plan: Anthropic Claude to xAI Grok

**Date:** 23 March 2026
**Version:** 1.0
**Status:** Planning

---

## Table of Contents

1. [Current State](#1-current-state)
2. [Grok API Compatibility](#2-grok-api-compatibility)
3. [Model Comparison](#3-model-comparison)
4. [Migration Steps](#4-migration-steps)
5. [Structured Output](#5-structured-output)
6. [Environment Variables](#6-environment-variables)
7. [Risk Assessment](#7-risk-assessment)
8. [Rollback Plan](#8-rollback-plan)

---

## 1. Current State

### Architecture Overview

Fynla uses AI in three distinct systems:

| System | Purpose | API Method | SDK |
|--------|---------|-----------|-----|
| **AI Chat (Fyn)** | Interactive financial assistant with tool calling and SSE streaming | Anthropic PHP SDK (`anthropic-ai/sdk ^0.6.0`) | PHP SDK with `createStream()` |
| **Document Extraction** | Extract data from uploaded financial documents (PDFs, images) | Raw HTTP to `https://api.anthropic.com/v1/messages` | Laravel `Http` facade |
| **Python Agent Sidecar** | Deep analysis tasks (holistic plans, scenarios, recommendations) | Anthropic Python SDK (`anthropic>=0.40.0`) | Python `anthropic.Anthropic` |

### File Inventory

#### PHP Backend (Primary Chat System)

| File | Role | Anthropic Coupling |
|------|------|-------------------|
| `app/Traits/HasAiChat.php` | Core chat trait - streaming, tool loop, prompt building | **HEAVY** - imports 9 Anthropic SDK classes (`AnthropicClient`, `RawContentBlockStartEvent`, `TextBlock`, `ToolUseBlock`, `TextDelta`, `InputJSONDelta`, `RawMessageStartEvent`, `RawMessageDeltaEvent`, `RawContentBlockStopEvent`, `RawContentBlockDeltaEvent`). Uses `$this->anthropicClient->messages->createStream()` with Anthropic-specific streaming events. |
| `app/Traits/HasAiGuardrails.php` | Model selection, token budgets, error handling | **MEDIUM** - references `config('services.anthropic.*')` for model names, has `DEFAULT_MODEL = 'claude-haiku-4-5-20251001'` |
| `app/Agents/CoordinatingAgent.php` | Main agent orchestrator | **MEDIUM** - imports `Anthropic\Client as AnthropicClient`, injects it via constructor |
| `app/Services/AI/AiToolDefinitions.php` | Tool definitions for function calling | **LIGHT** - converts tools to Anthropic format (`parameters` -> `input_schema`). Comment says "Anthropic Messages API" |
| `app/Providers/AppServiceProvider.php` | Registers Anthropic SDK singleton | **MEDIUM** - creates `\Anthropic\Client::class` singleton with API key |
| `config/services.php` | API key and model config | **LIGHT** - `anthropic` section with `api_key`, `chat_model`, `advanced_chat_model`, `agent_internal_token` |
| `app/Http/Middleware/AgentTokenAuth.php` | Agent token auth | **LIGHT** - reads `config('services.anthropic.agent_internal_token')` |

#### Document Extraction System

| File | Role | Anthropic Coupling |
|------|------|-------------------|
| `app/Services/Documents/AIExtractionService.php` | Document data extraction via vision API | **HEAVY** - hardcoded `API_URL = 'https://api.anthropic.com/v1/messages'`, `MODEL = 'claude-3-5-haiku-20241022'`, custom headers (`x-api-key`, `anthropic-version`), Anthropic-specific request format |
| `app/Services/Documents/ImageResizeService.php` | Resize images for API limits | **LIGHT** - references "Claude API 5MB limit" in comments only |

#### Python Agent Sidecar

| File | Role | Anthropic Coupling |
|------|------|-------------------|
| `scripts/fynla_agent/agent.py` | Main agent loop | **HEAVY** - `import anthropic`, `anthropic.Anthropic(api_key=...)`, `client.messages.create()`, Anthropic tool format (`input_schema`), Anthropic `stop_reason` values |
| `scripts/fynla_agent/config.py` | Agent configuration | **MEDIUM** - `ANTHROPIC_API_KEY`, claude model defaults |
| `scripts/run_agent.py` | CLI entry point | **LIGHT** - passes `model` default `'claude-sonnet-4-6-20260320'` |
| `scripts/requirements.txt` | Python dependencies | `anthropic>=0.40.0` |

#### Frontend (No API coupling, just text references)

| File | Content |
|------|---------|
| `resources/js/views/Version.vue` | Changelog mentions "Anthropic SDK Integration" |
| `resources/js/views/Public/PrivacyPolicyPage.vue` | Privacy policy names Anthropic as data processor |
| `resources/js/views/Public/TermsOfServicePage.vue` | Terms of service names Anthropic |
| `resources/js/components/Shared/DocumentUploadModal.vue` | Disclaimer: "goes through to Anthropic, we use the Haiku 3.5 model" |

#### Config and Environment

| File | Content |
|------|---------|
| `.env.example` | `ANTHROPIC_API_KEY=`, `ANTHROPIC_CHAT_MODEL=claude-haiku-4-5-20251001`, `ANTHROPIC_ADVANCED_CHAT_MODEL=claude-sonnet-4-6-20260320`, `AGENT_INTERNAL_TOKEN=` |

### Current Models in Use

| Use Case | Current Model | Context |
|----------|--------------|---------|
| Standard chat | `claude-haiku-4-5-20251001` | Default for most users |
| Complex queries (Pro users) | `claude-sonnet-4-6-20260320` | Selected when complexity=complex and user is on Pro plan |
| Document extraction | `claude-3-5-haiku-20241022` | Vision API for PDFs and images |
| Python agent | `claude-sonnet-4-6-20260320` | Deep analysis tasks |

### Current API Patterns

**Chat streaming (PHP):** Uses Anthropic's proprietary streaming format with typed event classes:
```php
$stream = $this->anthropicClient->messages->createStream(
    maxTokens: $maxTokens,
    messages: $messages,
    model: $model,
    system: [['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']]],
    tools: $tools,
    toolChoice: ['type' => 'auto'],
);
```

**Tool format (Anthropic):**
```php
['name' => '...', 'description' => '...', 'input_schema' => ['type' => 'object', 'properties' => [...], 'required' => [...]]]
```

**Tool result format (Anthropic):**
```php
['type' => 'tool_result', 'tool_use_id' => '...', 'content' => '...', 'is_error' => true/false]
```

**System prompt format (Anthropic):**
```php
'system' => [['type' => 'text', 'text' => $prompt, 'cache_control' => ['type' => 'ephemeral']]]
```

---

## 2. Grok API Compatibility

### API Format

The xAI Grok API is **OpenAI-compatible**. It uses the same Chat Completions format as OpenAI.

| Feature | Anthropic (Current) | Grok (xAI) |
|---------|---------------------|-------------|
| Base URL | `https://api.anthropic.com/v1` | `https://api.x.ai/v1` |
| Auth header | `x-api-key: {key}` | `Authorization: Bearer {key}` |
| Chat endpoint | `/v1/messages` | `/v1/chat/completions` |
| System prompt | Separate `system` parameter (array of objects) | `messages[0]` with `role: "system"` |
| Streaming | Proprietary event types (`RawContentBlockStartEvent`, `TextDelta`, etc.) | Standard OpenAI SSE format (`data: {"choices": [...]}`) |
| Tool format | `input_schema` key | `parameters` key (inside `function` wrapper) |
| Tool call response | `stop_reason: "tool_use"` | `finish_reason: "tool_calls"` |
| Tool result format | `type: "tool_result"`, `tool_use_id` | `role: "tool"`, `tool_call_id` |
| Prompt caching | `cache_control: {"type": "ephemeral"}` | Automatic (cached tokens at 10% cost) |
| SDK | `anthropic-ai/sdk` (PHP), `anthropic` (Python) | OpenAI SDK with custom `base_url` |

### Key Differences

1. **System prompt handling:** Anthropic takes `system` as a separate parameter with cache control. Grok takes it as the first message with `role: "system"` (or `role: "developer"`).

2. **Streaming event format:** Anthropic uses proprietary typed events (`RawContentBlockStartEvent`, `TextDelta`, `InputJSONDelta`). Grok uses standard OpenAI SSE delta format (`choices[0].delta.content`, `choices[0].delta.tool_calls`).

3. **Tool definition format:** Anthropic uses `input_schema` at the top level. Grok wraps tools in `{"type": "function", "function": {"name": ..., "parameters": ...}}`.

4. **Tool call in response:** Anthropic returns `ToolUseBlock` with `id`, `name`, `input`. Grok returns `tool_calls[{"id": ..., "function": {"name": ..., "arguments": ...}}]`.

5. **Tool results back to model:** Anthropic uses `{"type": "tool_result", "tool_use_id": ..., "content": ...}` in a `user` role message. Grok uses `{"role": "tool", "tool_call_id": ..., "content": ...}` as a separate message.

6. **Prompt caching:** Anthropic requires explicit `cache_control` headers. Grok caches automatically at 10% input cost -- no code changes needed, just a cost benefit.

7. **Reasoning:** Grok reasoning models think internally. Some parameters are incompatible with reasoning models: `presencePenalty`, `frequencyPenalty`, `stop`.

8. **Vision/Image format:** Anthropic uses `{"type": "image", "source": {"type": "base64", "media_type": ..., "data": ...}}`. Grok uses OpenAI format `{"type": "image_url", "image_url": {"url": "data:image/jpeg;base64,..."}}`.

9. **PDF handling:** Anthropic supports native PDF with `{"type": "document", "source": {"type": "base64", ...}}`. Grok supports images (jpg/png) up to 20MiB but PDF support needs verification -- may need to convert PDFs to images or extract text first.

### SDK Strategy

Since Grok is OpenAI-compatible, we have two options:

**Option A: Use OpenAI PHP SDK** (recommended)
- Install `openai-php/client` via Composer
- Set `base_url` to `https://api.x.ai/v1`
- Uses the well-maintained OpenAI PHP SDK with full streaming support
- The OpenAI PHP SDK has mature streaming support with typed events

**Option B: Raw HTTP with Laravel Http facade**
- Use `Http::withHeaders(...)->post(...)` like the current document extraction service
- More control but more code to maintain
- Would need manual SSE parsing for streaming

**Recommendation: Option A** for the main chat system and Python agent. **Option B** (raw HTTP) for document extraction since it is already using raw HTTP and the endpoint/format differences are minimal.

---

## 3. Model Comparison

### Target Model: `grok-4-1-fast-reasoning`

| Attribute | Claude Haiku 4.5 (current standard) | Claude Sonnet 4.6 (current advanced) | Grok 4-1 Fast Reasoning (target) |
|-----------|--------------------------------------|--------------------------------------|----------------------------------|
| Context window | 200K tokens | 200K tokens | **2M tokens** |
| Input pricing | $1.00/M tokens | $3.00/M tokens | **$0.20/M tokens** |
| Cached input | $0.10/M tokens | $0.30/M tokens | **$0.05/M tokens** |
| Output pricing | $5.00/M tokens | $15.00/M tokens | **$0.50/M tokens** |
| Tool calling | Yes | Yes | Yes |
| Structured output | Yes | Yes | Yes |
| Vision | Yes | Yes | Yes |
| Streaming | Yes | Yes | Yes |
| Reasoning | No | No | **Yes (built-in)** |
| Rate limit | Varies by tier | Varies by tier | 4M TPM, 607 RPM |

### Cost Comparison

Based on typical Fynla usage (estimated per chat message):
- Average input: ~4,000 tokens (system prompt + context + history)
- Average output: ~800 tokens

| | Claude Haiku 4.5 | Grok 4-1 Fast Reasoning | Saving |
|---|---|---|---|
| Per message (no cache) | $0.008 | **$0.0012** | **85%** |
| Per message (cached prompt) | $0.0044 | **$0.0006** | **86%** |
| 10,000 messages/month | $80 | **$12** | **$68/month** |

### Model Selection Mapping

| Use Case | Current | Proposed |
|----------|---------|----------|
| Standard chat | `claude-haiku-4-5-20251001` | `grok-4-1-fast-reasoning` |
| Complex queries | `claude-sonnet-4-6-20260320` | `grok-4-1-fast-reasoning` (same model, reasoning handles complexity) |
| Document extraction | `claude-3-5-haiku-20241022` | `grok-4-1-fast-non-reasoning` (vision, no reasoning needed for extraction) |
| Python agent | `claude-sonnet-4-6-20260320` | `grok-4-1-fast-reasoning` |

**Note:** With the 2M context window and built-in reasoning, a single model (`grok-4-1-fast-reasoning`) may serve all use cases, simplifying the architecture. The non-reasoning variant is ideal for document extraction where structured data extraction does not benefit from chain-of-thought.

---

## 4. Migration Steps

### Phase 1: Configuration and Dependencies

#### 1.1 Install OpenAI PHP SDK

```bash
composer require openai-php/client
```

#### 1.2 Remove Anthropic PHP SDK

```bash
composer remove anthropic-ai/sdk
```

#### 1.3 Update `config/services.php`

**Current:**
```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY', ''),
    'chat_model' => env('ANTHROPIC_CHAT_MODEL', 'claude-haiku-4-5-20251001'),
    'advanced_chat_model' => env('ANTHROPIC_ADVANCED_CHAT_MODEL', 'claude-sonnet-4-6-20260320'),
    'agent_internal_token' => env('AGENT_INTERNAL_TOKEN', ''),
],
```

**New:**
```php
'xai' => [
    'api_key' => env('XAI_API_KEY', ''),
    'chat_model' => env('XAI_CHAT_MODEL', 'grok-4-1-fast-reasoning'),
    'advanced_chat_model' => env('XAI_ADVANCED_CHAT_MODEL', 'grok-4-1-fast-reasoning'),
    'extraction_model' => env('XAI_EXTRACTION_MODEL', 'grok-4-1-fast-non-reasoning'),
    'agent_internal_token' => env('AGENT_INTERNAL_TOKEN', ''),
],
```

#### 1.4 Update `.env.example`

**Replace:**
```env
# Anthropic AI Chat
ANTHROPIC_API_KEY=
ANTHROPIC_CHAT_MODEL=claude-haiku-4-5-20251001
ANTHROPIC_ADVANCED_CHAT_MODEL=claude-sonnet-4-6-20260320
```

**With:**
```env
# xAI Grok AI Chat
XAI_API_KEY=
XAI_CHAT_MODEL=grok-4-1-fast-reasoning
XAI_ADVANCED_CHAT_MODEL=grok-4-1-fast-reasoning
XAI_EXTRACTION_MODEL=grok-4-1-fast-non-reasoning
```

#### 1.5 Update `.env` (production and local)

Set `XAI_API_KEY` to your xAI API key (obtain from https://console.x.ai).

---

### Phase 2: Service Provider

#### 2.1 Update `app/Providers/AppServiceProvider.php`

**Replace** the Anthropic singleton with an OpenAI client configured for xAI:

```php
use OpenAI;

// In register():
$this->app->singleton('xai.client', function () {
    $apiKey = config('services.xai.api_key');

    if (empty($apiKey)) {
        throw new \RuntimeException('XAI_API_KEY is not configured.');
    }

    return OpenAI::factory()
        ->withApiKey($apiKey)
        ->withBaseUri('https://api.x.ai/v1')
        ->withHttpClient(new \GuzzleHttp\Client(['timeout' => 120]))
        ->make();
});
```

---

### Phase 3: Core Chat System (Biggest Change)

#### 3.1 Rewrite `app/Traits/HasAiChat.php`

This is the most significant change. The entire streaming event loop must be rewritten from Anthropic's proprietary event types to OpenAI's SSE delta format.

**Key changes:**
- Remove all 9 Anthropic SDK imports
- Replace `$this->anthropicClient->messages->createStream(...)` with OpenAI SDK `$client->chat()->createStreamed(...)`
- Replace Anthropic event type handling with OpenAI delta format
- Change system prompt from separate parameter to first message
- Change tool format from `input_schema` to wrapped `function.parameters`
- Change tool result format from `tool_result` type to `role: "tool"` message
- Remove `cache_control` (Grok caches automatically)

**Streaming event mapping:**

| Anthropic Event | OpenAI/Grok Equivalent |
|-----------------|----------------------|
| `RawMessageStartEvent` (usage) | `usage` in final chunk |
| `RawContentBlockStartEvent` (TextBlock) | `delta.content` starts appearing |
| `RawContentBlockStartEvent` (ToolUseBlock) | `delta.tool_calls[0]` with `function.name` |
| `RawContentBlockDeltaEvent` (TextDelta) | `delta.content` string |
| `RawContentBlockDeltaEvent` (InputJSONDelta) | `delta.tool_calls[0].function.arguments` string |
| `RawContentBlockStopEvent` | Implicit (next block starts or stream ends) |
| `RawMessageDeltaEvent` (stopReason) | `finish_reason` in chunk |

**New streaming loop structure:**
```php
$stream = app('xai.client')->chat()->createStreamed([
    'model' => $model,
    'max_tokens' => $maxTokens,
    'messages' => array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $messages
    ),
    'tools' => $this->convertToolsToOpenAIFormat($tools),
    'tool_choice' => !empty($tools) ? 'auto' : null,
    'stream' => true,
]);

foreach ($stream as $response) {
    $delta = $response->choices[0]->delta ?? null;
    $finishReason = $response->choices[0]->finishReason ?? null;

    if ($delta?->content) {
        // Text content
        $text = $delta->content;
        $currentTextBlock .= $text;
        $fullResponse .= $text;
        yield ['type' => 'content', 'text' => $text];
    }

    if ($delta?->toolCalls) {
        foreach ($delta->toolCalls as $toolCall) {
            // Accumulate tool call data
            if ($toolCall->id) {
                $currentToolCallId = $toolCall->id;
                $currentToolCallName = $toolCall->function?->name ?? '';
                $accumulatedToolJson = '';
            }
            if ($toolCall->function?->arguments) {
                $accumulatedToolJson .= $toolCall->function->arguments;
            }
        }
    }

    if ($finishReason === 'tool_calls') {
        $stopReason = 'tool_use';
        // Finalise accumulated tool call
    }

    if ($finishReason === 'stop') {
        $stopReason = 'end_turn';
    }

    // Usage tracking from final chunk
    if ($response->usage) {
        $totalInputTokens += $response->usage->promptTokens ?? 0;
        $totalOutputTokens += $response->usage->completionTokens ?? 0;
    }
}
```

**Tool format conversion:**
```php
private function convertToolsToOpenAIFormat(array $anthropicTools): array
{
    return array_map(fn (array $tool) => [
        'type' => 'function',
        'function' => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'parameters' => $tool['input_schema'],
        ],
    ], $anthropicTools);
}
```

**Tool result message format change:**

Current (Anthropic):
```php
$messages[] = ['role' => 'user', 'content' => [
    ['type' => 'tool_result', 'tool_use_id' => $id, 'content' => json_encode($result)]
]];
```

New (OpenAI/Grok):
```php
$messages[] = ['role' => 'tool', 'tool_call_id' => $id, 'content' => json_encode($result)];
```

The assistant message with tool calls also changes format:
```php
// Anthropic format
$messages[] = ['role' => 'assistant', 'content' => $contentBlocks];  // content blocks include tool_use type

// OpenAI/Grok format
$messages[] = ['role' => 'assistant', 'content' => $textContent, 'tool_calls' => [
    ['id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => $args]]
]];
```

#### 3.2 Update `app/Traits/HasAiGuardrails.php`

- Change `DEFAULT_MODEL` from `'claude-haiku-4-5-20251001'` to `'grok-4-1-fast-reasoning'`
- Change all `config('services.anthropic.*')` to `config('services.xai.*')`
- Update error categorisation if Grok uses different error codes/messages

#### 3.3 Update `app/Agents/CoordinatingAgent.php`

- Remove `use Anthropic\Client as AnthropicClient;`
- Change constructor: replace `private readonly AnthropicClient $anthropicClient` with the OpenAI client type (or a custom wrapper)
- The `HasAiChat` trait references `$this->anthropicClient` -- this must match the new property name

#### 3.4 Update `app/Services/AI/AiToolDefinitions.php`

Two options:

**Option A:** Change `getTools()` to return OpenAI format directly (wrap in `function` key, rename `input_schema` to `parameters`).

**Option B:** Keep the current internal format and convert in HasAiChat. This is cleaner since the tool definitions are already clean -- only the final conversion in `getTools()` needs to change.

Current conversion:
```php
return array_map(fn (array $tool) => [
    'name' => $tool['name'],
    'description' => $tool['description'],
    'input_schema' => $tool['parameters'],  // Anthropic format
], $tools);
```

New conversion:
```php
return array_map(fn (array $tool) => [
    'type' => 'function',
    'function' => [
        'name' => $tool['name'],
        'description' => $tool['description'],
        'parameters' => $tool['parameters'],  // OpenAI format - key stays 'parameters'
    ],
], $tools);
```

This is actually simpler -- the internal tools already use `parameters`, so the Anthropic conversion to `input_schema` is removed and the OpenAI wrapping with `type: function` is added.

---

### Phase 4: Document Extraction System

#### 4.1 Update `app/Services/Documents/AIExtractionService.php`

This uses raw HTTP, so no SDK change needed -- just endpoint and format changes.

**Changes:**
- `API_URL`: `'https://api.anthropic.com/v1/messages'` -> `'https://api.x.ai/v1/chat/completions'`
- `MODEL`: `'claude-3-5-haiku-20241022'` -> `'grok-4-1-fast-non-reasoning'`
- Auth header: `'x-api-key' => $apiKey` -> `'Authorization' => 'Bearer ' . $apiKey`
- Remove `'anthropic-version' => '2023-06-01'` header
- API key config: `config('services.anthropic.api_key')` -> `config('services.xai.api_key')`
- Request body format: Anthropic Messages -> OpenAI Chat Completions
- Response parsing: `$response['content'][0]['text']` -> `$response['choices'][0]['message']['content']`

**Vision/Image format change:**

Current (Anthropic):
```php
['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $base64]]
```

New (Grok/OpenAI):
```php
['type' => 'image_url', 'image_url' => ['url' => "data:{$mediaType};base64,{$base64}"]]
```

**PDF handling:** Anthropic supports native PDF base64 upload. Grok's image API supports jpg/png. For PDFs, Fynla already has a text extraction fallback path (`processPdfDocument` -> `callClaudeAPIWithText`). The text extraction path will work as-is with format changes. For scanned PDFs (image-only), pages would need to be rendered to images first, or the existing `callClaudeAPI` with converted image pages would work.

**Important:** The `buildContentBlock` method that returns `type: "document"` for PDFs needs attention. This Anthropic-specific format does not exist in OpenAI/Grok. Options:
1. Use text extraction (already implemented) as the primary path for PDFs
2. Convert PDF pages to images using existing `PdfParser` + image rendering
3. Send extracted text via the text-only chat endpoint

#### 4.2 Update `app/Services/Documents/ImageResizeService.php`

- Update comments referencing "Claude API" to "Grok API" or make generic
- Grok's image limit is 20MB (vs Claude's 5MB) -- the resize logic can stay but the threshold could be increased
- Keep the resize logic as a safety net

---

### Phase 5: Python Agent Sidecar

#### 5.1 Update `scripts/requirements.txt`

**Replace:**
```
anthropic>=0.40.0
```

**With:**
```
openai>=1.0.0
```

#### 5.2 Update `scripts/fynla_agent/config.py`

```python
XAI_API_KEY = os.environ.get('XAI_API_KEY', '')
XAI_BASE_URL = 'https://api.x.ai/v1'
DEFAULT_MODEL = 'grok-4-1-fast-reasoning'
ADVANCED_MODEL = os.environ.get('XAI_ADVANCED_CHAT_MODEL', 'grok-4-1-fast-reasoning')
```

#### 5.3 Update `scripts/fynla_agent/agent.py`

Replace Anthropic SDK with OpenAI SDK pointed at xAI:

```python
from openai import OpenAI

client = OpenAI(api_key=api_key, base_url='https://api.x.ai/v1')

response = client.chat.completions.create(
    model=model,
    max_tokens=max_tokens,
    messages=[
        {'role': 'system', 'content': config['system']},
        *messages,
    ],
    tools=[{
        'type': 'function',
        'function': {
            'name': tool['name'],
            'description': tool['description'],
            'parameters': tool['input_schema'],
        },
    } for tool in TOOL_DEFINITIONS],
)
```

**Key changes in the tool loop:**
- `response.stop_reason` -> `response.choices[0].finish_reason`
- `'tool_use'` -> `'tool_calls'`
- `block.type == 'text'` -> `response.choices[0].message.content`
- `block.type == 'tool_use'` -> iterate `response.choices[0].message.tool_calls`
- `block.id` -> `tool_call.id`
- `block.name` -> `tool_call.function.name`
- `block.input` -> `json.loads(tool_call.function.arguments)`
- Tool result message: `{'role': 'tool', 'tool_call_id': id, 'content': json.dumps(result)}`

#### 5.4 Update `scripts/run_agent.py`

- Change default model from `'claude-sonnet-4-6-20260320'` to `'grok-4-1-fast-reasoning'`

#### 5.5 Update `app/Services/PythonAgentBridge.php`

- Change `config('services.anthropic.api_key')` to `config('services.xai.api_key')`
- Change `config('services.anthropic.advanced_chat_model', ...)` to `config('services.xai.advanced_chat_model', 'grok-4-1-fast-reasoning')`

---

### Phase 6: Middleware and Auth

#### 6.1 Update `app/Http/Middleware/AgentTokenAuth.php`

Change:
```php
$expectedToken = config('services.anthropic.agent_internal_token');
```
To:
```php
$expectedToken = config('services.xai.agent_internal_token');
```

---

### Phase 7: Frontend Text Updates

These are not functional changes but keep the application accurate.

#### 7.1 Update `resources/js/components/Shared/DocumentUploadModal.vue`

Change the disclaimer text from mentioning Anthropic/Haiku 3.5 to reference xAI/Grok.

#### 7.2 Update `resources/js/views/Public/PrivacyPolicyPage.vue`

Update the data processor section to reference xAI instead of Anthropic. Update the international data transfer section.

#### 7.3 Update `resources/js/views/Public/TermsOfServicePage.vue`

Update AI provider references from Anthropic to xAI.

#### 7.4 `resources/js/views/Version.vue`

Add a new version entry documenting the migration. Previous version entries mentioning Anthropic remain as historical record.

---

### Phase 8: System Prompt Updates

The system prompt in `HasAiChat.php::buildSystemPrompt()` currently says "You are Fynla Assistant" -- this is provider-agnostic and needs **no changes**. The prompt does not mention Claude or Anthropic anywhere.

However, verify that Grok handles the XML-like prompt structure (`<identity>`, `<instructions>`, `<regulatory_compliance>`, etc.) well. Grok models should handle this fine as it is a common prompting pattern, but test with representative queries.

---

## 5. Structured Output

### Current Usage

Fynla uses structured output in two ways:

1. **Tool calling** (primary) -- the AI calls tools with structured JSON arguments and receives structured JSON results. This is the main mechanism for entity creation, navigation, form filling, and data retrieval.

2. **Python agent** -- expects the final response to be parseable as a Pydantic model (`HolisticPlanOutput`, `ScenarioOutput`, `DeepRecommendationOutput`).

### Grok Structured Output Support

Grok supports structured outputs via:

1. **Function calling** -- fully supported on all Grok 4 models. The format is OpenAI-compatible:
   ```json
   {
     "type": "function",
     "function": {
       "name": "create_savings_account",
       "parameters": {
         "type": "object",
         "properties": { ... },
         "required": [...]
       }
     }
   }
   ```

2. **`response_format`** -- for guaranteed JSON schema output:
   ```json
   {
     "response_format": {
       "type": "json_schema",
       "json_schema": { "name": "...", "schema": { ... } }
     }
   }
   ```

3. **`client.beta.chat.completions.parse()`** -- OpenAI SDK method that handles schema validation automatically. Works with Grok.

### Migration Impact

**Tool calling:** All 30+ Fynla tools will work with Grok's function calling. The only change is the format wrapper (`type: "function"` + `function:` nesting). The internal schema (`properties`, `required`, `enum`, `additionalProperties`) is identical.

**Python agent structured output:** Currently relies on the model producing valid JSON that matches a Pydantic schema. With Grok, this can be improved by using `response_format` to guarantee schema compliance:

```python
completion = client.beta.chat.completions.parse(
    model='grok-4-1-fast-reasoning',
    messages=messages,
    response_format=HolisticPlanOutput,  # Pydantic model
)
result = completion.choices[0].message.parsed
```

This eliminates the current try/except JSON parsing fallback in the Python agent.

### Supported Schema Types

| JSON Schema Feature | Grok Support |
|--------------------|-------------|
| `string` | Yes |
| `number` / `integer` | Yes |
| `boolean` | Yes |
| `object` | Yes |
| `array` | Yes |
| `enum` | Yes |
| `anyOf` | Yes |
| `allOf` | **No** |
| `minLength` / `maxLength` | **No** (ignored) |
| `minItems` / `maxItems` | **No** (ignored) |

The current Fynla tool schemas do not use `allOf`, `minLength`, `maxLength`, `minItems`, or `maxItems`, so there is no compatibility issue.

---

## 6. Environment Variables

### Changes Required

| Variable | Current Value | New Variable | New Value |
|----------|--------------|-------------|-----------|
| `ANTHROPIC_API_KEY` | `sk-ant-...` | `XAI_API_KEY` | (from https://console.x.ai) |
| `ANTHROPIC_CHAT_MODEL` | `claude-haiku-4-5-20251001` | `XAI_CHAT_MODEL` | `grok-4-1-fast-reasoning` |
| `ANTHROPIC_ADVANCED_CHAT_MODEL` | `claude-sonnet-4-6-20260320` | `XAI_ADVANCED_CHAT_MODEL` | `grok-4-1-fast-reasoning` |
| (new) | -- | `XAI_EXTRACTION_MODEL` | `grok-4-1-fast-non-reasoning` |
| `AGENT_INTERNAL_TOKEN` | (unchanged) | `AGENT_INTERNAL_TOKEN` | (unchanged) |
| `OPENAI_API_KEY` | (can be removed if not used elsewhere) | -- | -- |

### Production Deployment Steps

1. Add new env vars to SiteGround via `.env` file
2. Set `XAI_API_KEY` to the production xAI API key
3. Keep `ANTHROPIC_API_KEY` temporarily during transition (for rollback)
4. After successful migration, remove `ANTHROPIC_*` variables
5. Clear config cache: `php artisan config:clear && php artisan optimize`

---

## 7. Risk Assessment

### High Risk

| Risk | Impact | Mitigation |
|------|--------|-----------|
| **Streaming format differences cause broken SSE** | Chat completely non-functional | Thorough testing of streaming with tool calls. The OpenAI PHP SDK handles SSE parsing, so this is mainly about correct delta accumulation. |
| **Tool calling behaviour differences** | Tools not called or called incorrectly, entities not created | Test every tool (30+) with representative prompts. Grok may interpret tool descriptions differently -- may need prompt tuning. |
| **PDF document extraction** | Document upload feature broken | Anthropic supports native PDF base64; Grok may not. The text extraction fallback path already exists. Test with actual financial documents. |
| **Reasoning model token overhead** | Higher costs than expected, slower responses | `grok-4-1-fast-reasoning` includes reasoning tokens in output. Monitor token usage. Consider `grok-4-1-fast-non-reasoning` for simple queries. |

### Medium Risk

| Risk | Impact | Mitigation |
|------|--------|-----------|
| **System prompt interpretation** | AI responds differently, misses compliance rules | Test with all prompt sections. Grok may handle XML-like tags differently. May need prompt restructuring. |
| **Tool call streaming differences** | Grok streams tool calls in a single chunk (per docs), not incrementally | This actually simplifies the code -- no need to accumulate `InputJSONDelta`. Verify behaviour. |
| **Image size limits** | Grok's 20MB vs Claude's 5MB is more permissive, but format differences matter | Test with actual uploaded documents. The resize service stays as a safety net. |
| **Rate limits** | 607 RPM may be lower than current Anthropic tier | Monitor in production. Contact xAI for higher limits if needed. |

### Low Risk

| Risk | Impact | Mitigation |
|------|--------|-----------|
| **British English compliance** | Grok may default to American English | The system prompt already instructs British English. Test and verify. |
| **Regulatory compliance** | Grok may not follow hedging language as strictly | The detailed system prompt covers this. Test with edge cases. |
| **Frontend text references** | Incorrect provider names in legal pages | Simple text updates, no functional impact. |
| **Python agent schema parsing** | Grok's structured output may differ slightly | Can use `response_format` for guaranteed compliance. |

### Critical Testing Required

1. **Full chat conversation** with tool calling (navigate, create entity, analyse module, get tax info)
2. **Streaming behaviour** -- verify SSE events arrive correctly for text and tool calls
3. **Document extraction** -- upload a pension statement PDF, investment statement image
4. **Preview mode** -- verify preview users cannot trigger write tools
5. **Token budget tracking** -- verify input/output token counts are correctly tracked
6. **Error handling** -- test with invalid API key, rate limiting, model errors
7. **Mobile SSE** -- verify streaming works on iOS (WKWebView fallback)
8. **All 6 preview personas** -- verify chat works for each
9. **Complex queries** -- test with multi-tool-call responses
10. **Long conversations** -- test message history with 20+ messages

---

## 8. Rollback Plan

### Preparation (Before Migration)

1. **Keep Anthropic SDK installed during initial deployment** -- add `openai-php/client` alongside `anthropic-ai/sdk`
2. **Maintain both config sections** in `config/services.php` (both `anthropic` and `xai`)
3. **Keep `ANTHROPIC_API_KEY` in production** `.env`
4. **Tag a git release** before starting migration: `git tag v0.9.3-pre-grok-migration`

### Rollback Steps

If issues are found in production:

1. **Revert PHP files** -- upload the pre-migration versions of:
   - `app/Traits/HasAiChat.php`
   - `app/Traits/HasAiGuardrails.php`
   - `app/Agents/CoordinatingAgent.php`
   - `app/Services/AI/AiToolDefinitions.php`
   - `app/Providers/AppServiceProvider.php`
   - `app/Services/Documents/AIExtractionService.php`
   - `app/Http/Middleware/AgentTokenAuth.php`
   - `config/services.php`

2. **Revert Python files** -- upload pre-migration versions of:
   - `scripts/fynla_agent/agent.py`
   - `scripts/fynla_agent/config.py`
   - `scripts/run_agent.py`
   - `scripts/requirements.txt`

3. **Clear caches:**
   ```bash
   php artisan cache:clear && php artisan config:clear && php artisan optimize
   ```

4. **Verify** -- test chat with a preview persona

### Feature Flag Approach (Alternative)

Instead of a full migration, implement a feature flag:

```php
// config/services.php
'ai_provider' => env('AI_PROVIDER', 'xai'),  // 'anthropic' or 'xai'
```

Then in `AppServiceProvider.php`:
```php
if (config('services.ai_provider') === 'xai') {
    // Register OpenAI client for xAI
} else {
    // Register Anthropic client
}
```

This allows switching providers via a single env var without code changes. However, this adds complexity and should only be used if there is concern about Grok's reliability.

---

## Summary of All Files to Change

### PHP Files (13 files)

| # | File | Change Type |
|---|------|-------------|
| 1 | `config/services.php` | Config rename |
| 2 | `app/Providers/AppServiceProvider.php` | SDK registration |
| 3 | `app/Traits/HasAiChat.php` | **Major rewrite** (streaming + tool loop) |
| 4 | `app/Traits/HasAiGuardrails.php` | Model names + config refs |
| 5 | `app/Agents/CoordinatingAgent.php` | Import + constructor |
| 6 | `app/Services/AI/AiToolDefinitions.php` | Tool format conversion |
| 7 | `app/Services/Documents/AIExtractionService.php` | API URL, auth, request/response format |
| 8 | `app/Services/Documents/ImageResizeService.php` | Comments only |
| 9 | `app/Services/PythonAgentBridge.php` | Config references |
| 10 | `app/Http/Middleware/AgentTokenAuth.php` | Config reference |
| 11 | `composer.json` | Add openai-php/client, remove anthropic-ai/sdk |

### Python Files (4 files)

| # | File | Change Type |
|---|------|-------------|
| 12 | `scripts/fynla_agent/agent.py` | SDK swap + tool loop rewrite |
| 13 | `scripts/fynla_agent/config.py` | Variable names + defaults |
| 14 | `scripts/run_agent.py` | Default model |
| 15 | `scripts/requirements.txt` | anthropic -> openai |

### Frontend Files (4 files, text only)

| # | File | Change Type |
|---|------|-------------|
| 16 | `resources/js/components/Shared/DocumentUploadModal.vue` | Provider name |
| 17 | `resources/js/views/Public/PrivacyPolicyPage.vue` | Legal text |
| 18 | `resources/js/views/Public/TermsOfServicePage.vue` | Legal text |
| 19 | `resources/js/views/Version.vue` | New changelog entry |

### Environment Files (2 files)

| # | File | Change Type |
|---|------|-------------|
| 20 | `.env.example` | Variable names |
| 21 | `.env` (production) | API key + model names |

**Total: 21 files, with `HasAiChat.php` being the most complex change.**

---

## Implementation Order

1. **Phase 1-2:** Config, dependencies, service provider (foundation)
2. **Phase 3:** Core chat system rewrite (highest risk, most complex)
3. **Phase 4:** Document extraction (independent, can be done in parallel)
4. **Phase 5-6:** Python agent + middleware (dependent on config)
5. **Phase 7:** Frontend text updates (lowest risk, can be done last)
6. **Phase 8:** System prompt verification (testing phase)

**Estimated effort:** 2-3 days for implementation, 1-2 days for thorough testing.
