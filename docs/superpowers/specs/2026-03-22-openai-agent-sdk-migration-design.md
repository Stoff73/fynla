# OpenAI Migration — Design Spec

**Date:** 22 March 2026
**Branch:** `aiChange`

## Summary

Swap the LLM call in `HasAiChat.php` from Anthropic to OpenAI. Everything else stays identical — same agents, same tools, same prompts, same SSE events, same frontend.

## What Changes

### 1. The API call (HasAiChat.php lines 100-163)

**Before:** Anthropic PHP SDK `createStream()` with typed event objects
**After:** PHP `curl` streaming to `POST https://api.openai.com/v1/chat/completions` with SSE parsing

Key format translations:
- **System prompt:** Anthropic uses a separate `system` param → OpenAI uses a `{"role": "system", "content": "..."}` message
- **Tools:** Anthropic `{name, description, input_schema}` → OpenAI `{type: "function", function: {name, description, parameters}}`
- **Tool choice:** Anthropic `{type: "auto"}` → OpenAI `"auto"`
- **Streaming events:** Anthropic typed SDK events → OpenAI SSE `data: {...}` lines with `choices[0].delta`
- **Tool calls in response:** Anthropic `tool_use` content blocks → OpenAI `choices[0].delta.tool_calls` array
- **Tool results back to model:** Anthropic `{role: "user", content: [{type: "tool_result", tool_use_id, content}]}` → OpenAI `{role: "tool", tool_call_id, content}`
- **Stop reason:** Anthropic `"tool_use"` → OpenAI `"tool_calls"`
- **Stream end:** Anthropic SDK iterator ends → OpenAI sends `data: [DONE]`
- **Token usage:** Anthropic `inputTokens/outputTokens` → OpenAI `prompt_tokens/completion_tokens` (available when `stream_options: {include_usage: true}`)

### 2. Tool definitions format (AiToolDefinitions.php line 32-37)

Add a method to output OpenAI format alongside existing Anthropic format:
```php
// Existing (kept for Anthropic doc upload / fallback)
public function getTools(bool $isPreviewMode = false): array  // → input_schema format

// New
public function getToolsForOpenAi(bool $isPreviewMode = false): array  // → function format
```

### 3. Model references (HasAiGuardrails.php)

- `DEFAULT_MODEL` → `'gpt-5-nano-2025-08-07'`
- `getAiModel()` → read from `config('services.openai.chat_model_standard')` and `config('services.openai.chat_model_pro')` instead of `config('services.anthropic.*')`

### 4. Config (config/services.php)

Update existing OpenAI defaults from `gpt-5-mini` to `gpt-5-nano-2025-08-07`.

### 5. DI wiring (CoordinatingAgent.php + AppServiceProvider.php)

Remove `AnthropicClient` from CoordinatingAgent constructor (no longer needed for chat). Keep Anthropic singleton in AppServiceProvider for `AIExtractionService`.

### 6. Streaming implementation (new private method in HasAiChat)

Replace the Anthropic SDK stream processing (lines 100-163) with a `curl`-based OpenAI streamer. New private method `streamOpenAiCompletion()` that:

1. Builds the OpenAI request body (messages with system prompt prepended, tools in OpenAI format, model, max_tokens, stream: true, stream_options: {include_usage: true})
2. Opens a curl connection with `CURLOPT_WRITEFUNCTION` callback
3. Parses incoming SSE `data: {...}` lines
4. For text deltas (`choices[0].delta.content`): yields `['type' => 'content', 'text' => $text]` — same as line 137 today
5. For tool calls (`choices[0].delta.tool_calls`): accumulates function name + arguments JSON across chunks, builds tool use blocks — same as the current `$currentToolUseBlock` / `$accumulatedToolJson` pattern
6. For `finish_reason`: captures `"stop"`, `"tool_calls"`, or `"length"`
7. For `data: [DONE]`: breaks out of stream
8. For usage chunk: captures `prompt_tokens` and `completion_tokens`

The tool execution loop (lines 177-267) stays **identical** — it already works with generic `$toolUseBlocks` arrays. Only the field mapping changes: Anthropic `id` → OpenAI `id`, Anthropic `name` → OpenAI `function.name`, Anthropic `input` → OpenAI `function.arguments` (parsed JSON).

The message format for feeding tool results back changes:
- **Before (Anthropic):** `{role: "user", content: [{type: "tool_result", tool_use_id, content}]}`
- **After (OpenAI):** `{role: "tool", tool_call_id, content}`

And the assistant message with tool calls changes:
- **Before (Anthropic):** `{role: "assistant", content: [text blocks + tool_use blocks]}`
- **After (OpenAI):** `{role: "assistant", content: "text", tool_calls: [{id, type: "function", function: {name, arguments}}]}`

## Files Changed

| # | File | Change |
|---|------|--------|
| 1 | `app/Traits/HasAiChat.php` | Replace Anthropic stream (lines 100-163) with curl-based OpenAI stream. Adapt tool result message format (lines 240-260). Remove Anthropic SDK imports. |
| 2 | `app/Traits/HasAiGuardrails.php` | Update `DEFAULT_MODEL`, rewrite `getAiModel()` to use OpenAI config keys, update error patterns. |
| 3 | `app/Services/AI/AiToolDefinitions.php` | Add `getToolsForOpenAi()` method. Keep existing `getTools()` for Anthropic. |
| 4 | `app/Agents/CoordinatingAgent.php` | Remove `AnthropicClient` from constructor. |
| 5 | `app/Providers/AppServiceProvider.php` | Keep Anthropic singleton for doc upload. Remove it from CoordinatingAgent DI. |
| 6 | `config/services.php` | Update OpenAI model defaults to `gpt-5-nano-2025-08-07`. |

## What Does NOT Change

- All prompt building methods (`buildSystemPrompt`, `buildUserProfile`, `buildFinancialContext`, etc.)
- All tool execution code (`executeTool()` and everything below line 177)
- All SSE event yielding (navigation, form_fill, entity_created, tool_use status events)
- All message persistence (`saveMessage`, `buildMessageHistory`, token tracking)
- All guardrail logic (token budgets, plan tiers, preview mode)
- All frontend code
- Document upload AI (`AIExtractionService.php` — stays Anthropic)
- Python batch analysis (`PythonAgentBridge` + `fynla_agent/` — stays Anthropic)
- Database schema
- `AiToolDefinitions.php` internal tool definitions (just adding an output format method)

## Rollback

Revert the 6 files. No database changes, no frontend changes, no new dependencies.
