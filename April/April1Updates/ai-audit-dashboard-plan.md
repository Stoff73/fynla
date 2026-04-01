# AI Audit Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin dashboard showing every system prompt sent to the LLM and every response received, per user, per session, with full audit trail.

**Architecture:** Migration adds `system_prompt` LONGTEXT to `ai_messages`. New `AiAuditController` serves 3 read-only admin endpoints. Vue `AiAudit.vue` component renders a three-panel layout (users → conversations → messages) as a new tab in AdminPanel.

**Tech Stack:** Laravel 10 (migration, controller, routes), Vue 2 Options API, Tailwind CSS (fynlaDesignGuide palette), existing admin middleware (`auth:sanctum` + `permission:admin.access`).

**Spec:** `April/April1Updates/ai-audit-dashboard-design.md`

---

### Task 1: Migration — add system_prompt column

**Files:**
- Create: `database/migrations/2026_04_01_160000_add_system_prompt_to_ai_messages_table.php`

- [ ] **Step 1: Create migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->longText('system_prompt')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn('system_prompt');
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: `add_system_prompt_to_ai_messages_table ... DONE`

- [ ] **Step 3: Verify column exists**

Run: `php artisan tinker --execute="echo in_array('system_prompt', \Illuminate\Support\Facades\Schema::getColumnListing('ai_messages')) ? 'OK' : 'FAIL';"`
Expected: `OK`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_01_160000_add_system_prompt_to_ai_messages_table.php
git commit -m "feat: add system_prompt column to ai_messages for audit trail"
```

---

### Task 2: Persist system prompt in HasAiChat::chat()

**Files:**
- Modify: `app/Traits/HasAiChat.php` — the `saveMessage()` call for assistant messages (~line 436)

- [ ] **Step 1: Update the assistant message save to include system_prompt**

Find the existing code in `HasAiChat::chat()` (around line 436):

```php
        // Save assistant message
        $assistantMessage = $this->saveMessage($conversation, 'assistant', $fullResponse, array_merge([
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
            'model_used' => $model,
        ], ! empty($messageMetadata) ? ['metadata' => $messageMetadata] : []));
```

Replace with:

```php
        // Save assistant message with system prompt for audit trail
        $assistantMessage = $this->saveMessage($conversation, 'assistant', $fullResponse, array_merge([
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
            'model_used' => $model,
            'system_prompt' => $systemPrompt,
        ], ! empty($messageMetadata) ? ['metadata' => $messageMetadata] : []));
```

The `$systemPrompt` variable already exists in scope — it's set on line ~80 of `chat()`.

- [ ] **Step 2: Add system_prompt to AiMessage fillable**

Read `app/Models/AiMessage.php` and add `'system_prompt'` to the `$fillable` array.

- [ ] **Step 3: Verify with syntax check**

Run: `php -l app/Traits/HasAiChat.php && php -l app/Models/AiMessage.php`
Expected: No syntax errors

- [ ] **Step 4: Commit**

```bash
git add app/Traits/HasAiChat.php app/Models/AiMessage.php
git commit -m "feat: persist system prompt on assistant messages for audit"
```

---

### Task 3: AiAuditController — 3 admin endpoints

**Files:**
- Create: `app/Http/Controllers/Api/AiAuditController.php`

- [ ] **Step 1: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAdviceLog;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAuditController extends Controller
{
    /**
     * List users who have AI conversations.
     * GET /api/admin/ai-audit/users
     */
    public function users(Request $request): JsonResponse
    {
        $search = $request->query('search', '');

        $query = User::whereHas('aiConversations')
            ->withCount('aiConversations as conversation_count')
            ->withMax('aiConversations', 'last_message_at')
            ->select(['id', 'first_name', 'surname', 'email', 'is_preview_user']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('ai_conversations_max_last_message_at')
            ->paginate(25);

        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->surname ?? '')),
                'email' => $user->email,
                'is_preview_user' => (bool) $user->is_preview_user,
                'conversation_count' => (int) $user->conversation_count,
                'last_conversation_at' => $user->ai_conversations_max_last_message_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * List conversations for a user.
     * GET /api/admin/ai-audit/users/{userId}/conversations
     */
    public function conversations(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $conversations = AiConversation::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'status' => $c->status,
                'model_used' => $c->model_used,
                'message_count' => $c->message_count,
                'total_input_tokens' => $c->total_input_tokens,
                'total_output_tokens' => $c->total_output_tokens,
                'created_at' => $c->created_at?->toIso8601String(),
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->surname ?? '')),
                    'email' => $user->email,
                ],
                'conversations' => $conversations,
            ],
        ]);
    }

    /**
     * Get full message thread for a conversation with audit data.
     * GET /api/admin/ai-audit/conversations/{conversationId}/messages
     */
    public function messages(int $conversationId): JsonResponse
    {
        $conversation = AiConversation::with('user')->findOrFail($conversationId);
        $user = $conversation->user;

        $messages = AiMessage::where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'system_prompt' => $m->system_prompt,
                'input_tokens' => $m->input_tokens,
                'output_tokens' => $m->output_tokens,
                'model_used' => $m->model_used,
                'metadata' => $m->metadata,
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        // Get advice log for this conversation (if any)
        $adviceLog = AiAdviceLog::where('conversation_id', $conversationId)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'user' => [
                        'id' => $user->id,
                        'name' => trim(($user->first_name ?? '') . ' ' . ($user->surname ?? '')),
                        'email' => $user->email,
                    ],
                    'model_used' => $conversation->model_used,
                    'total_input_tokens' => $conversation->total_input_tokens,
                    'total_output_tokens' => $conversation->total_output_tokens,
                    'created_at' => $conversation->created_at?->toIso8601String(),
                ],
                'messages' => $messages,
                'advice_log' => $adviceLog ? [
                    'query_type' => $adviceLog->query_type,
                    'classification' => $adviceLog->classification,
                    'kyc_status' => $adviceLog->kyc_status,
                    'tools_called' => $adviceLog->tools_called,
                    'user_data_snapshot' => $adviceLog->user_data_snapshot,
                ] : null,
            ],
        ]);
    }
}
```

- [ ] **Step 2: Verify AiConversation has user relationship and User has aiConversations**

Check `app/Models/AiConversation.php` has `public function user()` returning `belongsTo(User::class)`.
Check `app/Models/User.php` has `public function aiConversations()` returning `hasMany(AiConversation::class)`. If missing, add it.

- [ ] **Step 3: Syntax check**

Run: `php -l app/Http/Controllers/Api/AiAuditController.php`
Expected: No syntax errors

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/AiAuditController.php
git commit -m "feat: AiAuditController — admin endpoints for AI audit trail"
```

---

### Task 4: Add routes

**Files:**
- Modify: `routes/api.php` — inside the existing admin route group

- [ ] **Step 1: Add ai-audit routes to the admin group**

Find the admin route group (around line 1005):
```php
Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')->group(function () {
```

Add inside this group (at the end, before the closing `});`):

```php
    // AI Audit trail
    Route::prefix('ai-audit')->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\AiAuditController::class, 'users']);
        Route::get('/users/{userId}/conversations', [\App\Http\Controllers\Api\AiAuditController::class, 'conversations']);
        Route::get('/conversations/{conversationId}/messages', [\App\Http\Controllers\Api\AiAuditController::class, 'messages']);
    });
```

- [ ] **Step 2: Verify routes registered**

Run: `php artisan route:list --path=ai-audit`
Expected: 3 routes listed (GET users, GET users/{}/conversations, GET conversations/{}/messages)

- [ ] **Step 3: Commit**

```bash
git add routes/api.php
git commit -m "feat: admin ai-audit API routes"
```

---

### Task 5: API service wrapper

**Files:**
- Create: `resources/js/services/aiAuditService.js`

- [ ] **Step 1: Create the service**

```javascript
import api from './api';

const aiAuditService = {
    async getUsers(search = '', page = 1) {
        const response = await api.get('/admin/ai-audit/users', {
            params: { search, page },
        });
        return response.data;
    },

    async getUserConversations(userId) {
        const response = await api.get(`/admin/ai-audit/users/${userId}/conversations`);
        return response.data;
    },

    async getConversationMessages(conversationId) {
        const response = await api.get(`/admin/ai-audit/conversations/${conversationId}/messages`);
        return response.data;
    },
};

export default aiAuditService;
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/services/aiAuditService.js
git commit -m "feat: aiAuditService — API wrapper for admin AI audit"
```

---

### Task 6: AiAudit.vue component

**Files:**
- Create: `resources/js/components/Admin/AiAudit.vue`

This is the largest task. The component uses the fynlaDesignGuide palette: `horizon-500` for text, `raspberry-*` for accents, `eggshell-500` for backgrounds, `spring-500` for success badges, `violet-500` for warning badges.

- [ ] **Step 1: Create the three-panel component**

Create `resources/js/components/Admin/AiAudit.vue` with:

**Template:** Three columns — user list (left, 1/4 width), conversation list (middle, 1/4 width), message thread (right, 1/2 width). Each column scrollable independently.

**Left panel:**
- Text input for search (debounced 300ms, calls `loadUsers`)
- List of users: each row shows name, email, conversation count, last active relative time
- Preview users get a small `Preview` badge
- Selected user highlighted with `bg-raspberry-50 border-l-2 border-raspberry-500`
- Clicking a user calls `selectUser(user)` → loads conversations

**Middle panel:**
- Header showing selected user name
- List of conversations: title (truncated 60 chars), date (`formatDate`), message count badge, total tokens
- Selected conversation highlighted
- Clicking calls `selectConversation(conv)` → loads messages

**Right panel:**
- Header showing conversation title + user email + model + token totals
- Scrollable message list:
  - **User messages:** `bg-raspberry-50 rounded-lg p-3`, role label "User", timestamp
  - **Assistant messages:** `bg-white border border-light-gray rounded-lg p-3`, role label "Fyn", timestamp
    - Response content rendered as HTML (markdown already converted by frontend)
    - Below content, a row of expandable sections (collapsed by default):
      - **System Prompt** button → expands to show full prompt in a `<pre>` block with `bg-horizon-50 text-xs font-mono overflow-x-auto max-h-96 overflow-y-auto`
      - **Tool Calls** button → expands to show tool name + input summary + result summary for each
      - **Tokens** label showing `input_tokens / output_tokens` + model name
    - If `metadata.validation_violations` exists and non-empty, show red `Violations` badge that expands to list them

- If `advice_log` exists for the conversation, show a summary card at the top of the message panel:
  - Query type badge (e.g. `retirement_contribution`)
  - Classification: primary + related types as small tags
  - KYC: PASS (green) or BLOCKED (red) badge with missing items
  - User data snapshot: income, expenditure values at time of advice

**Empty states:**
- No user selected: centred text "Select a user to view their AI conversations" with a chat icon
- User selected, no conversations: "No AI conversations for this user"
- Conversation selected, loading: spinner

**Script:**
- `data()`: `users`, `conversations`, `messages`, `adviceLog`, `selectedUser`, `selectedConversation`, `searchQuery`, `loadingUsers`, `loadingConversations`, `loadingMessages`, `expandedPrompts` (Set of message IDs), `expandedTools` (Set of message IDs)
- `methods`: `loadUsers()`, `selectUser(user)`, `selectConversation(conv)`, `togglePrompt(messageId)`, `toggleTools(messageId)`, `formatDate(iso)`, `formatTokens(n)`
- `watch`: `searchQuery` with 300ms debounce → `loadUsers()`
- `mounted()`: calls `loadUsers()`

- [ ] **Step 2: Syntax check via dev server**

Run: `./dev.sh` (if not running) and check for compile errors in the terminal.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Admin/AiAudit.vue
git commit -m "feat: AiAudit.vue — three-panel admin AI audit dashboard"
```

---

### Task 7: Wire into AdminPanel

**Files:**
- Modify: `resources/js/views/Admin/AdminPanel.vue`

- [ ] **Step 1: Add the tab definition**

In the `tabs` array in `data()`, add after the `ai-settings` entry:

```javascript
        {
          id: 'ai-audit',
          label: 'AI Audit',
        },
```

- [ ] **Step 2: Add the async component import**

After the existing `const UserMetrics = defineAsyncComponent(...)` line:

```javascript
const AiAudit = defineAsyncComponent(() => import('../../components/Admin/AiAudit.vue'));
```

- [ ] **Step 3: Register the component**

Add `AiAudit` to the `components` object.

- [ ] **Step 4: Add the tab content**

After the `<AiSettings>` conditional render:

```html
        <!-- AI Audit Tab -->
        <AiAudit v-if="activeTab === 'ai-audit'" />
```

- [ ] **Step 5: Add tab icon and short label**

In `getTabIcon(id)` method, add a case for `'ai-audit'` returning an eye/document icon path:
```javascript
case 'ai-audit': return 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
```

In `getTabShortLabel(id)` method, add:
```javascript
case 'ai-audit': return 'Audit';
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/views/Admin/AdminPanel.vue
git commit -m "feat: add AI Audit tab to admin panel"
```

---

### Task 8: Seed + browser test

- [ ] **Step 1: Seed database**

Run: `php artisan db:seed`

- [ ] **Step 2: Log in as admin**

Navigate to `http://localhost:8000/login`, log in with `admin@fps.com` / `admin123`, enter verification code from DB.

- [ ] **Step 3: Navigate to admin panel**

Go to `http://localhost:8000/admin` (or however the admin route is mapped).

- [ ] **Step 4: Click AI Audit tab**

Verify the three-panel layout renders. If no conversations exist yet, verify the empty state shows correctly.

- [ ] **Step 5: Send a test message as a regular user**

In a separate session or via tinker, trigger an AI conversation. Then refresh the admin AI Audit tab.

- [ ] **Step 6: Verify user appears in left panel**

The user who sent the message should appear with conversation count = 1.

- [ ] **Step 7: Click user → verify conversations load**

Middle panel should show the conversation with title, date, message count.

- [ ] **Step 8: Click conversation → verify messages load**

Right panel should show user message and assistant response. The assistant message should have:
- Expandable system prompt section (click to view full prompt)
- Tool calls section (if any tools were called)
- Token counts
- Validation violations (if any)

- [ ] **Step 9: Take screenshot**

Capture the working three-panel view with a conversation expanded.

- [ ] **Step 10: Commit any fixes**

```bash
git add -A
git commit -m "fix: AI Audit dashboard browser test fixes"
```

---

### Task 9: Final verification + docs

- [ ] **Step 1: Run all AI tests**

Run: `./vendor/bin/pest tests/Unit/Services/AI/ tests/Unit/Constants/`
Expected: All 71 tests pass

- [ ] **Step 2: Run full regression**

Run: `./vendor/bin/pest`
Expected: No new failures

- [ ] **Step 3: Update fyn2Tasks.md**

Add a Phase 7 section documenting the AI Audit Dashboard implementation.

- [ ] **Step 4: Update CSJTODO.md**

Add AI Audit Dashboard to session 25 completed items.

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "docs: AI Audit Dashboard implementation complete"
```
