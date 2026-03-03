# Plan: AI Chat — Fynla Assistant

## Context

Add an AI-powered chat assistant ("Fynla") to the application. The chat integrates with the existing 7-agent system to give users personalised financial guidance based on their actual data. The AI can navigate users to relevant pages, run what-if scenarios, and (for non-preview users) create goals, accounts, and other records. Conversations persist in the database for history. Available to all users including preview mode.

## Architecture Overview

```
User types message
    ↓
AiChatPanel.vue → fetch POST (SSE stream) → AiChatController
    ↓
AiChatService orchestrates:
  1. Save user message to DB
  2. Build system prompt (AiContextBuilder — user profile + holistic analysis summary)
  3. Call Anthropic Messages API with tools + streaming
  4. Stream text chunks back via SSE
  5. If model calls a tool → AiToolExecutor runs it → send result back → model continues
  6. Save assistant message to DB
    ↓
Frontend renders streamed response, handles navigation/entity-created events
```

## Decisions

| Decision | Choice |
|----------|--------|
| Persistence | Database — `ai_conversations` + `ai_messages` tables |
| Access | All users including preview (write tools blocked in preview) |
| Persona | "Fynla Assistant" — professional, no separate character |
| Model | Configurable: Haiku 4.5 for student/standard, Sonnet 4.6 for Pro |
| Streaming | SSE via `StreamedResponse` + fetch `ReadableStream` on frontend |
| UI Pattern | Mirrors InfoGuidePanel — Teleport to body, slide-in from right, z-[70] |

## Phase 1: Database

### Migration 1: `create_ai_conversations_table`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | foreignId → users | cascadeOnDelete |
| title | varchar(255) nullable | Auto-generated from first message |
| status | enum('active','archived') | default 'active' |
| model_used | varchar(100) | |
| total_input_tokens | int unsigned | default 0 |
| total_output_tokens | int unsigned | default 0 |
| message_count | int unsigned | default 0 |
| last_message_at | timestamp nullable | |
| metadata | json nullable | current_route, etc. |
| timestamps + softDeletes | | |

Index: `(user_id, status, last_message_at)`

### Migration 2: `create_ai_messages_table`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| conversation_id | foreignId → ai_conversations | cascadeOnDelete |
| role | enum('user','assistant','system','tool_result') | |
| content | text | |
| tool_calls | json nullable | Anthropic tool_use blocks |
| tool_results | json nullable | Executed tool results |
| input_tokens | int unsigned nullable | |
| output_tokens | int unsigned nullable | |
| model_used | varchar(100) nullable | |
| metadata | json nullable | navigation_target, created_entities |
| timestamps | | |

Index: `(conversation_id, created_at)`

### Migration 3: `add_ai_chat_enabled_to_users_table`

Add `ai_chat_enabled` boolean default true — user preference toggle.

## Phase 2: Backend Models

**`app/Models/AiConversation.php`** — Standard model with SoftDeletes. Relations: `belongsTo(User)`, `hasMany(AiMessage)`. Scopes: `scopeActive()`, `scopeForUser()`. Helper: `incrementTokenUsage()`.

**`app/Models/AiMessage.php`** — Standard model. Relation: `belongsTo(AiConversation)`. Casts: `tool_calls`, `tool_results`, `metadata` as array.

## Phase 3: Backend Services (`app/Services/AI/`)

### 3a. `AiModelResolver.php`

Resolves Claude model based on user subscription plan:
- Pro → `claude-sonnet-4-6-20250514`
- Standard/Student/default → `claude-haiku-4-5-20241001`

Config keys in `config/services.php` for easy override.

### 3b. `AiContextBuilder.php`

Builds system prompt with:
1. **Identity & rules** — British English, no acronyms, not regulated advice, GBP formatting
2. **User profile** — name, age, income, family status, current page, preview mode flag
3. **Financial summary** — from cached `CoordinatingAgent->orchestrateAnalysis()`: net worth, surplus, emergency fund, pension projection, IHT estimate, top 3 recommendations
4. **Module context** — deeper summary for the current page's module

Token budget: ~4000 tokens for system prompt. Aggressively summarise holistic analysis.

### 3c. `AiToolDefinitions.php`

Returns Anthropic tool definitions array:

**Navigation:**
- `navigate_to_page(route_path, description)` — returns route for frontend to navigate

**Analysis (read-only, works in preview):**
- `get_module_analysis(module)` — calls agent's `analyze()` for protection/savings/investment/retirement/estate/goals/holistic
- `run_what_if_scenario(module, parameters)` — calls agent's `buildScenarios()`
- `get_recommendations()` — ranked recommendations from CoordinatingAgent
- `get_tax_information(topic)` — current tax year config from TaxConfigService

**Data creation (blocked in preview mode):**
- `create_goal(name, target_amount, target_date, priority, goal_type)`
- `create_life_event(event_type, event_date, description, estimated_cost)`
- `create_savings_account(institution, account_type, current_balance, interest_rate)`

### 3d. `AiToolExecutor.php`

Executes tool calls. Injects all 7 agents + relevant services. For write tools in preview mode: returns `{ blocked: true, reason: "Preview mode" }`. For analysis tools: calls the agent method directly.

### 3e. `AiChatService.php` (Core orchestrator)

`sendMessage(User, AiConversation, string $message, ?string $currentRoute): Generator`

1. Save user message to `ai_messages`
2. Build message history from conversation (sliding window — system + last N messages)
3. Build system prompt via `AiContextBuilder`
4. Call Anthropic API with `stream: true`
5. Yield SSE chunks: `{ type: 'content', text: '...' }`
6. On `tool_use` block → call `AiToolExecutor` → yield progress → send result back → continue loop
7. Save assistant message, update token counts
8. Yield `{ type: 'done', message_id, tokens }`

Uses Laravel's `Http` facade with streaming, following the pattern from `AIExtractionService.php` (lines 185-212).

## Phase 4: Controller & Routes

### `app/Http/Controllers/Api/AiChatController.php`

| Method | Endpoint | Description |
|--------|----------|-------------|
| index | GET `/api/ai-chat/conversations` | List user's conversations |
| create | POST `/api/ai-chat/conversations` | Start new conversation |
| show | GET `/api/ai-chat/conversations/{id}` | Load conversation + messages |
| destroy | DELETE `/api/ai-chat/conversations/{id}` | Soft-delete conversation |
| sendMessage | POST `/api/ai-chat/conversations/{id}/messages` | **SSE streaming** response |

`sendMessage()` returns `StreamedResponse` with headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `X-Accel-Buffering: no`.

### Rate Limiting

Register in `RouteServiceProvider`:
- Pro: 20/min, 200/hour
- Standard: 10/min, 100/hour
- Student/default: 5/min, 50/hour

### Route Registration (`routes/api.php`)

Inside `auth:sanctum` group with `throttle:ai-chat`.

### PreviewWriteInterceptor

Add to `EXCLUDED_ROUTES`: `api/ai-chat/conversations`, `api/ai-chat/conversations/` (POST must work in preview — tool executor handles write blocking internally).

## Phase 5: Frontend Service

### `resources/js/services/aiChatService.js`

Standard API service following `holisticService.js` pattern. The `sendMessage()` method uses `fetch()` (not axios) for SSE streaming from a POST endpoint — `EventSource` only supports GET.

## Phase 6: Vuex Store

### `resources/js/store/modules/aiChat.js`

**State:** `isOpen`, `isEnabled`, `conversations`, `currentConversation`, `messages`, `streaming`, `streamingText`, `loading`, `error`

**Key action — `sendMessage({ message })`:**
1. Add user message to local state immediately
2. Set `streaming: true`, clear `streamingText`
3. `fetch()` to streaming endpoint
4. Read `ReadableStream` with `getReader()`, parse SSE lines
5. On `content` chunks → `APPEND_STREAMING_TEXT`
6. On `navigation` → add navigation message for component to handle
7. On `done` → finalise assistant message, clear streaming state
8. On `error` → set error state

Register in `store/index.js`.

## Phase 7: Frontend Components

### `resources/js/components/Shared/AiChatButton.vue`

Teleport to body. `fixed bottom-6 right-24 z-40` (left of InfoGuideButton at right-6). Chat bubble icon → X when open. Hidden when InfoGuidePanel is open (mutual exclusion to avoid clutter).

### `resources/js/components/Shared/AiChatPanel.vue`

Teleport to body. `fixed right-0 top-0 bottom-0 w-[440px] max-w-full z-[70]`. Slide-in transition from right (same as InfoGuidePanel).

**Structure:**
- **Header** — "Fynla Assistant" title, new conversation / history / close buttons
- **History drawer** — collapsible list of past conversations
- **Messages area** — scrollable, user messages right-aligned (primary-600 bg), assistant left-aligned (white bg with border)
- **Streaming indicator** — pulsing cursor while AI responds
- **Suggested prompts** — context-aware based on current route (e.g. retirement page shows pension questions)
- **Input area** — textarea + send button, Enter to send, disabled during streaming

### `resources/js/components/Shared/AiMessageContent.vue`

Renders assistant message content with basic markdown: bold, lists, links, currency formatting. Handles navigation action cards (clickable routes).

### AppLayout.vue Integration

Add `<AiChatButton />` and `<AiChatPanel />` alongside existing InfoGuide components (line 40-41).

## Files Summary

### New Files (16)

| File | Purpose |
|------|---------|
| `database/migrations/..._create_ai_conversations_table.php` | Conversations table |
| `database/migrations/..._create_ai_messages_table.php` | Messages table |
| `database/migrations/..._add_ai_chat_enabled_to_users_table.php` | User preference |
| `app/Models/AiConversation.php` | Conversation model |
| `app/Models/AiMessage.php` | Message model |
| `app/Services/AI/AiChatService.php` | Core orchestrator |
| `app/Services/AI/AiModelResolver.php` | Model per subscription tier |
| `app/Services/AI/AiContextBuilder.php` | System prompt builder |
| `app/Services/AI/AiToolDefinitions.php` | Tool schemas |
| `app/Services/AI/AiToolExecutor.php` | Tool execution + preview guard |
| `app/Http/Controllers/Api/AiChatController.php` | API controller with SSE |
| `resources/js/services/aiChatService.js` | Frontend API service |
| `resources/js/store/modules/aiChat.js` | Vuex store module |
| `resources/js/components/Shared/AiChatButton.vue` | Floating trigger button |
| `resources/js/components/Shared/AiChatPanel.vue` | Chat panel |
| `resources/js/components/Shared/AiMessageContent.vue` | Message renderer |

### Modified Files (5)

| File | Change |
|------|--------|
| `routes/api.php` | Add AI chat route group |
| `resources/js/store/index.js` | Register aiChat module |
| `resources/js/layouts/AppLayout.vue` | Add AiChatButton + AiChatPanel |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Exclude chat routes |
| `config/services.php` | Add chat model config keys |

## Implementation Order

1. **Database** — 3 migrations + 2 models
2. **Backend services** — AiModelResolver → AiToolDefinitions → AiToolExecutor → AiContextBuilder → AiChatService
3. **Controller + routes** — AiChatController, api.php, rate limiter, PreviewWriteInterceptor
4. **Frontend foundation** — aiChatService.js, aiChat.js store, register in index.js
5. **Frontend UI** — AiMessageContent → AiChatPanel → AiChatButton → AppLayout integration

## Verification

1. `./dev.sh` → log in or select preview persona
2. Floating chat button visible bottom-right (next to InfoGuide button)
3. Click → panel slides in from right, above all other content
4. Type "What is my net worth?" → AI streams a personalised response using agent data
5. Type "Take me to my pensions" → AI responds with navigation link, clicking it navigates
6. Type "Create a goal to save £10,000 for a holiday by December 2027" → AI creates the goal (non-preview only)
7. In preview mode → write tools blocked, AI explains it cannot modify data
8. Navigate between pages → chat panel persists, context-aware prompts update
9. Close and reopen → conversation history preserved
10. Check conversation list → previous chats listed and loadable

## Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| SSE buffering on SiteGround hosting | `X-Accel-Buffering: no` header; fallback to polling if needed |
| Long conversations exceed context window | Sliding window: system prompt + last 20 messages + summary of earlier |
| API costs | Rate limiting per tier; Haiku for most users; token tracking per conversation |
| Preview user attempting writes | AiToolExecutor blocks write tools; system prompt instructs model to not attempt |
| Tool use loop hangs | Max 5 tool calls per turn; 180-second timeout on API call |
