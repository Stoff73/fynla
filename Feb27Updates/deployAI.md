# Deployment Guide: AI Chat (Fynla Assistant)

## Status: READY FOR DEPLOY

## Rebuild Required: YES (frontend)

New Vue components, Vuex store module, and service file require a frontend rebuild.

```bash
./deploy/fynla-org/build.sh
```

## Summary

Added an AI-powered chat assistant ("Fynla Assistant") to the application. Integrates with the existing 7-agent system to give users personalised financial guidance based on their actual data. The AI can navigate users to relevant pages, run what-if scenarios, and (for non-preview users) create goals and life events. Conversations persist in the database.

### Features
- **Personalised responses** — uses user's real financial data (income, expenditure, savings, pensions, etc.)
- **Tool use** — AI can call analysis tools, run scenarios, fetch tax information, navigate to pages, create goals/life events
- **Navigation** — clickable navigation cards that route users to relevant sections
- **Conversation history** — persistent conversations stored in database, loadable from history drawer
- **Preview mode aware** — write tools (goal/life event creation) blocked for preview users
- **Model per tier** — Haiku 4.5 for student/standard, Sonnet 4.6 for Pro subscribers
- **Card-style UI** — matches existing dashboard card design (white bg, rounded-lg, border-gray-200)
- **Context-aware prompts** — suggested questions change based on current page (dashboard, retirement, savings, etc.)

### Architecture
```
User types message
    -> AiChatPanel.vue -> fetch POST (SSE) -> AiChatController
    -> AiChatService orchestrates:
       1. Save user message to DB
       2. Build system prompt (AiContextBuilder)
       3. Call Anthropic Messages API with tools
       4. If tool_use -> AiToolExecutor runs it -> continue
       5. Save assistant message to DB
    -> Frontend renders response + navigation/entity cards
```

## Environment Variables Required

Add to `.env` on production (already exists from document upload feature):

```
ANTHROPIC_API_KEY=sk-ant-api03-...
```

Optional overrides (defaults are fine):
```
ANTHROPIC_CHAT_MODEL_PRO=claude-sonnet-4-6-20250514
ANTHROPIC_CHAT_MODEL_STANDARD=claude-haiku-4-5-20251001
```

## Database Migration Required: YES

Three new migrations must be run on production:

```bash
php artisan migrate
```

Creates:
- `ai_conversations` table — stores conversation metadata, token usage
- `ai_messages` table — stores individual messages (user + assistant)
- `ai_chat_enabled` column on `users` table — user preference toggle

## New Files to Upload

```
# Backend - Models
app/Models/AiConversation.php
app/Models/AiMessage.php

# Backend - Services
app/Services/AI/AiChatService.php
app/Services/AI/AiContextBuilder.php
app/Services/AI/AiModelResolver.php
app/Services/AI/AiToolDefinitions.php
app/Services/AI/AiToolExecutor.php

# Backend - Controller
app/Http/Controllers/Api/AiChatController.php

# Backend - Migrations
database/migrations/2026_02_27_200001_create_ai_conversations_table.php
database/migrations/2026_02_27_200002_create_ai_messages_table.php
database/migrations/2026_02_27_200003_add_ai_chat_enabled_to_users_table.php

# Frontend - Components
resources/js/components/Shared/AiChatButton.vue
resources/js/components/Shared/AiChatPanel.vue
resources/js/components/Shared/AiMessageContent.vue

# Frontend - Service + Store
resources/js/services/aiChatService.js
resources/js/store/modules/aiChat.js
```

## Modified Files to Upload

```
app/Http/Middleware/PreviewWriteInterceptor.php
config/services.php
resources/js/layouts/AppLayout.vue
resources/js/store/index.js
routes/api.php
```

## Post-Upload: SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Upload Paths (SiteGround)

| Local File | Remote Path |
|-----------|-------------|
| `public/build/` | `~/www/fynla.org/public_html/public/build/` |
| `app/Models/AiConversation.php` | `~/www/fynla.org/public_html/app/Models/AiConversation.php` |
| `app/Models/AiMessage.php` | `~/www/fynla.org/public_html/app/Models/AiMessage.php` |
| `app/Services/AI/` (entire folder) | `~/www/fynla.org/public_html/app/Services/AI/` |
| `app/Http/Controllers/Api/AiChatController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/AiChatController.php` |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | `~/www/fynla.org/public_html/app/Http/Middleware/PreviewWriteInterceptor.php` |
| `config/services.php` | `~/www/fynla.org/public_html/config/services.php` |
| `routes/api.php` | `~/www/fynla.org/public_html/routes/api.php` |
| `database/migrations/2026_02_27_200001_create_ai_conversations_table.php` | `~/www/fynla.org/public_html/database/migrations/` |
| `database/migrations/2026_02_27_200002_create_ai_messages_table.php` | `~/www/fynla.org/public_html/database/migrations/` |
| `database/migrations/2026_02_27_200003_add_ai_chat_enabled_to_users_table.php` | `~/www/fynla.org/public_html/database/migrations/` |

## API Routes Added

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/ai-chat/conversations` | List user's conversations |
| POST | `/api/ai-chat/conversations` | Start new conversation |
| GET | `/api/ai-chat/conversations/{id}` | Load conversation + messages |
| DELETE | `/api/ai-chat/conversations/{id}` | Soft-delete conversation |
| POST | `/api/ai-chat/conversations/{id}/messages` | Send message (SSE response) |

## Verification

1. Log in or select a preview persona
2. Chat button visible bottom-right (chat bubble icon)
3. Click -> panel opens as a floating card matching dashboard style
4. Type "How is my financial health?" -> AI streams personalised response with real data
5. Type "Take me to my savings" -> navigation card appears, clicking it routes to /savings
6. Chat panel persists across page navigation
7. Conversation history accessible via clock icon in header
8. Close and reopen -> conversation preserved
