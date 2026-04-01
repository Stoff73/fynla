# Fyn AI Audit Dashboard — Design Spec

**Date:** 1 April 2026
**Branch:** `fynImprovement`
**Status:** SPEC

---

## Purpose

Full auditability of every AI interaction in Fynla. Admin users can see exactly what system prompt was sent to the LLM, what the user asked, what tools were called, and what Fyn responded — per user, per session, sorted by date and time. This is critical for a finance app where AI-generated guidance must be reviewable and compliant.

---

## Database Changes

### Migration: add `system_prompt` to `ai_messages`

Add a `LONGTEXT` column to store the full system prompt that was sent to the LLM for each conversation turn.

```
ai_messages.system_prompt  LONGTEXT  nullable
```

Only populated on assistant messages (the prompt used to generate that response). User messages have `system_prompt = null`.

**Storage estimate:** ~6,000-8,000 tokens per prompt × ~4 chars/token = ~24-32KB per assistant message. For 1,000 conversations with 5 turns each = ~120-160MB. Acceptable for audit purposes.

---

## API Endpoints

All under `api/admin/ai-audit/`, protected by admin middleware.

### `GET /api/admin/ai-audit/users`

List all users who have AI conversations.

**Response:**
```json
{
  "users": [
    {
      "id": 20,
      "name": "John Smith",
      "email": "john@example.com",
      "is_preview_user": false,
      "conversation_count": 5,
      "total_messages": 32,
      "last_conversation_at": "2026-04-01T15:37:32Z"
    }
  ]
}
```

Sorted by `last_conversation_at` descending. Includes preview users (flagged). Paginated (25 per page).

### `GET /api/admin/ai-audit/users/{userId}/conversations`

All conversations for a user.

**Response:**
```json
{
  "conversations": [
    {
      "id": 1,
      "title": "How much pension contribution...",
      "status": "active",
      "model_used": "grok-4-1-fast-reasoning",
      "message_count": 4,
      "total_input_tokens": 12500,
      "total_output_tokens": 3200,
      "created_at": "2026-04-01T15:37:00Z",
      "last_message_at": "2026-04-01T15:38:30Z"
    }
  ]
}
```

Sorted by `created_at` descending.

### `GET /api/admin/ai-audit/conversations/{conversationId}/messages`

Full message thread with all audit data.

**Response:**
```json
{
  "conversation": {
    "id": 1,
    "title": "How much pension...",
    "user": { "id": 20, "name": "John Smith", "email": "john@example.com" },
    "model_used": "grok-4-1-fast-reasoning",
    "total_input_tokens": 12500,
    "total_output_tokens": 3200,
    "created_at": "2026-04-01T15:37:00Z"
  },
  "messages": [
    {
      "id": 101,
      "role": "user",
      "content": "How much pension contribution should I make?",
      "system_prompt": null,
      "input_tokens": null,
      "output_tokens": null,
      "model_used": null,
      "metadata": null,
      "created_at": "2026-04-01T15:37:00Z"
    },
    {
      "id": 102,
      "role": "assistant",
      "content": "To give you personalised guidance on pension contributions...",
      "system_prompt": "<identity>\nYou are Fynla Assistant...\n</identity>\n\n<security>...",
      "input_tokens": 8500,
      "output_tokens": 1200,
      "model_used": "grok-4-1-fast-reasoning",
      "metadata": {
        "tool_calls": [...],
        "validation_violations": [...]
      },
      "created_at": "2026-04-01T15:37:32Z"
    }
  ],
  "advice_log": {
    "query_type": "retirement_contribution",
    "classification": { "primary": "retirement_contribution", "related": [...], "modules": [...] },
    "kyc_status": { "passed": false, "missing": ["Monthly expenditure"] },
    "tools_called": ["get_tax_information", "get_module_analysis"],
    "user_data_snapshot": { "income": 75000, "expenditure": 0 }
  }
}
```

---

## Frontend Component

### `AiAudit.vue` — new tab in AdminPanel

**Layout:** Three-panel responsive design.

**Left panel — User list:**
- Search box at top (filter by name/email)
- List of users with conversation count and last active date
- Preview users shown with a badge
- Click to load conversations

**Middle panel — Conversation list:**
- Shows all conversations for selected user
- Each row: title (truncated), date, message count, token usage
- Click to load messages

**Right panel — Message thread:**
- Full conversation thread in chronological order
- User messages: simple text in a distinct style
- Assistant messages: response text + expandable sections for:
  - **System Prompt** — collapsible, shows the full prompt text with syntax highlighting for XML tags
  - **Tool Calls** — list of tools called with inputs and result summaries
  - **Classification** — query type badge + related types
  - **KYC Status** — PASS/BLOCKED badge with missing items if blocked
  - **Validation Violations** — red badges for any violations detected
  - **Token Usage** — input/output token counts, model name

**Empty states:**
- No user selected: "Select a user to view their AI conversations"
- No conversations: "This user has no AI conversations yet"
- No messages: "This conversation has no messages"

---

## Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/*_add_system_prompt_to_ai_messages.php` | Add system_prompt LONGTEXT column |
| `app/Http/Controllers/Api/AiAuditController.php` | Admin API for audit data |
| `resources/js/components/Admin/AiAudit.vue` | Three-panel audit dashboard component |
| `resources/js/services/aiAuditService.js` | API wrapper for audit endpoints |

## Files to Modify

| File | Changes |
|------|---------|
| `app/Traits/HasAiChat.php` | Store system_prompt on assistant message save |
| `resources/js/views/Admin/AdminPanel.vue` | Add AI Audit tab |
| `routes/api.php` | Add admin ai-audit routes |

---

## Implementation Order

1. Migration — add `system_prompt` column
2. Persist system prompt in `HasAiChat::chat()`
3. `AiAuditController` with 3 endpoints
4. Routes
5. `aiAuditService.js` API wrapper
6. `AiAudit.vue` component
7. Wire into AdminPanel tabs
8. Browser test
