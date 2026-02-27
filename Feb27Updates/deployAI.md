# Deployment Guide: AI Chat (Fynla Assistant)

## Status: READY FOR DEPLOY

## What Changed

Added an AI-powered chat assistant ("Fynla Assistant") with 17 tools across all financial modules. The AI can navigate users to pages (auto-navigation), run what-if scenarios, create financial records (savings, investments, pensions, properties, mortgages, protection policies, estate items), and generate holistic financial plans. Conversations persist in the database.

**Simulated AI for preview personas:** Preview users now get a realistic AI-like experience without any Anthropic API calls. The simulated service uses pattern-based intent matching, calls real agents for actual financial data, then formats responses using templates with real numbers. Same SSE streaming format, same navigation, same conversation persistence — zero API cost for demo users. Real users remain on the actual LLM path unchanged.

Also fixed HolisticPlan view missing its `<AppLayout>` wrapper (side menu was not showing).

## Step 1: Build Frontend

```bash
./deploy/fynla-org/build.sh
```

## Step 2: Upload Files

### New Files

```text
app/Models/AiConversation.php
app/Models/AiMessage.php
app/Services/AI/AiChatService.php
app/Services/AI/AiContextBuilder.php
app/Services/AI/AiModelResolver.php
app/Services/AI/AiToolDefinitions.php
app/Services/AI/AiToolExecutor.php
app/Services/AI/AiIntentMatcher.php
app/Services/AI/AiSimulatedResponseBuilder.php
app/Services/AI/AiSimulatedService.php
app/Http/Controllers/Api/AiChatController.php
database/migrations/2026_02_27_200001_create_ai_conversations_table.php
database/migrations/2026_02_27_200002_create_ai_messages_table.php
database/migrations/2026_02_27_200003_add_ai_chat_enabled_to_users_table.php
```

### Modified Files

```text
app/Http/Middleware/PreviewWriteInterceptor.php
config/services.php
resources/js/layouts/AppLayout.vue
resources/js/store/index.js
resources/js/store/modules/aiChat.js
resources/js/components/Shared/AiChatButton.vue
resources/js/components/Shared/AiChatPanel.vue
resources/js/components/Shared/AiMessageContent.vue
resources/js/services/aiChatService.js
resources/js/views/HolisticPlan.vue
routes/api.php
```

### Upload Paths (SiteGround)

| Local | Remote |
|-------|--------|
| `public/build/` | `~/www/fynla.org/public_html/public/build/` |
| `app/Models/AiConversation.php` | `~/www/fynla.org/public_html/app/Models/AiConversation.php` |
| `app/Models/AiMessage.php` | `~/www/fynla.org/public_html/app/Models/AiMessage.php` |
| `app/Services/AI/` (entire folder) | `~/www/fynla.org/public_html/app/Services/AI/` |
| `app/Http/Controllers/Api/AiChatController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/AiChatController.php` |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | `~/www/fynla.org/public_html/app/Http/Middleware/PreviewWriteInterceptor.php` |
| `config/services.php` | `~/www/fynla.org/public_html/config/services.php` |
| `routes/api.php` | `~/www/fynla.org/public_html/routes/api.php` |
| `database/migrations/2026_02_27_*` (3 files) | `~/www/fynla.org/public_html/database/migrations/` |

## Step 3: Environment Variables

Add to `.env` on production (already exists from document upload feature):

```text
ANTHROPIC_API_KEY=sk-ant-api03-...
```

Optional overrides (defaults are fine):

```text
ANTHROPIC_CHAT_MODEL_PRO=claude-sonnet-4-6-20250514
ANTHROPIC_CHAT_MODEL_STANDARD=claude-haiku-4-5-20251001
```

## Step 4: SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Database Migrations

Creates:
- `ai_conversations` table — conversation metadata, token usage
- `ai_messages` table — individual messages (user + assistant)
- `ai_chat_enabled` column on `users` table — user preference toggle

## API Routes Added

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/ai-chat/conversations` | List user's conversations |
| POST | `/api/ai-chat/conversations` | Start new conversation |
| GET | `/api/ai-chat/conversations/{id}` | Load conversation + messages |
| DELETE | `/api/ai-chat/conversations/{id}` | Soft-delete conversation |
| POST | `/api/ai-chat/conversations/{id}/messages` | Send message (SSE response) |

## AI Tools (17 total)

| Tool | Action | Available To |
|------|--------|-------------|
| `navigate_to_page` | Auto-navigates user to a page | All users |
| `get_module_analysis` | Detailed financial analysis per module | All users |
| `run_what_if_scenario` | What-if scenario projections | All users |
| `get_recommendations` | Ranked recommendations | All users |
| `get_tax_information` | Current UK tax info | All users |
| `generate_financial_plan` | Holistic financial plan summary | All users |
| `create_goal` | Financial goal | Real users only |
| `create_life_event` | Life event | Real users only |
| `create_savings_account` | SavingsAccount (including Cash ISA) | Real users only |
| `create_investment_account` | InvestmentAccount (including S&S ISA) | Real users only |
| `create_pension` | DCPension or DBPension | Real users only |
| `create_property` | Property + optional auto-linked Mortgage | Real users only |
| `create_mortgage` | Mortgage (fuzzy-matched to property) | Real users only |
| `create_protection_policy` | Life, Critical Illness, or Income Protection | Real users only |
| `create_estate_asset` | Estate Asset | Real users only |
| `create_estate_liability` | Estate Liability | Real users only |
| `create_estate_gift` | Estate Gift (IHT planning) | Real users only |

## Bug Fixes Included

1. **tool_use.input serialisation** (AiChatService.php): PHP `json_decode({}, true)` converts empty `{}` to `[]`, which Anthropic API rejects. Fixed with `(object)` cast.

2. **Invalid navigation routes**: Corrected `/net-worth/savings` to `/net-worth/cash`, `/net-worth/pensions` to `/net-worth/retirement`, `/net-worth/business-interests` to `/net-worth/business`. Removed non-existent top-level routes (`/retirement`, `/savings`, `/investment`).

3. **Navigation not working**: `navigate_to_page` only rendered a clickable card but did not change the page. Now auto-navigates via `$router.push()`.

4. **HolisticPlan missing layout**: View was missing `<AppLayout>` wrapper, causing side menu to disappear. Now matches all other views.

## Verification

1. Chat button visible bottom-right on all pages
2. Click to open, type "How is my financial health?" — AI streams personalised response
3. "Take me to my cash" — page auto-navigates to `/net-worth/cash`
4. "Show me my pensions" — auto-navigates to `/net-worth/retirement`
5. "I have a Cash ISA with Nationwide worth £15,000" — green entity card, account created
6. "I have a workplace pension with Aviva worth £120,000" — pension created
7. "My home is worth £450,000, I bought it for £320,000" — property created
8. "Can you generate a financial plan for me?" — holistic plan summary returned
9. Navigate to `/holistic-plan` — side menu visible, standard layout
10. Preview mode: creation tools blocked, analysis and plan generation still work
11. Conversation history accessible via clock icon, persists across sessions
