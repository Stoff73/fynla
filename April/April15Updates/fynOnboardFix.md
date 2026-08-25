# Fyn Onboarding — Technical Specification

**Status:** Amended 20 April 2026 — reconciled against live code on `onboardingFyn` branch (commit `88018a5`). See §20 for the delta register. Original approval by CSJ 15 April 2026 (session 54) stands for the intent; individual rule changes are tracked per-line below.
**Branch:** `onboardingFyn`
**Author:** Session 53 (15 April 2026), amended session 1 (20 April 2026)
**Supersedes:** The ad-hoc `NewUserContext` mega-prompt approach. Returns to the Phase 4 plan as originally written in `/Users/CSJ/.claude/plans/structured-conjuring-kazoo.md` and fills in the gaps.

**⚠️ Icon rule (added 15 April 2026 session 54):** The Fyn chat window is a BANNED surface per `CLAUDE.md §14` and the top-of-file rule in `fynlaDesignGuide.md v1.4.0`. Every mention of an icon, emoji, or glyph field on a bubble, state prompt, or chat message in this document is RETRACTED. Bubble entries are `{id, label}` only. Do NOT implement the icon fields shown below — those are frozen historical text and must be ignored by implementers. (Note: the functional-only exception for the collapsed side nav does NOT apply to anything in this document; the onboarding flow is entirely in the chat window.)

---

## 1. Goal & non-goals

**Goal:** New users who click "Quick start with Fyn" land on the dashboard, Fyn opens, and Fyn **initiates** a structured onboarding conversation (no user message required, no hardcoded welcome in the frontend). Fyn walks the user through base KYC data and focus/journey selection via clickable bubbles, then captures existing assets in the chosen module, then hands off to the relevant module page.

**Non-goals (explicitly out of scope):**
- Changes to the legacy `/onboarding` wizard. Untouched.
- Changes to the existing authenticated Fyn chat for users who have `onboarding_completed=true`. Untouched.
- Changes to `FcaProcessInstructions` as a layer — it stays, but is **skipped** during onboarding (see §11).
- New Vue components beyond `FynQuickReplies.vue` (already built in Phase 3). No `FynConfirmationCard.vue` for MVP.
- Frontend state machine. The backend owns all state.

---

## 2. Architecture overview

**Principle: the backend owns the state machine. Claude is a narrow tool.**

```
┌─────────────────────────────────────────────────────────────────────┐
│                           FRONTEND                                  │
│  AiChatPanel.onOpen()                                               │
│    ├─ if (openFyn=journey || pendingJourneyPrompt):                 │
│    │    POST /api/ai-chat/onboarding/start  ◄── turn 1 trigger      │
│    └─ else: normal chat path                                        │
│                                                                     │
│  FynQuickReplies @select  ──►  sendMessage(bubble.label)            │
│  textarea submit          ──►  sendMessage(text)                    │
└─────────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                           BACKEND                                   │
│  AiChatController                                                   │
│    ├─ POST /onboarding/start  → OnboardingChatDirector::start()     │
│    └─ POST /conversations/{id}/messages                             │
│         if (user.onboarding_completed === false &&                  │
│             user.onboarding_fyn_step !== null):                     │
│            → OnboardingChatDirector::handleUserMessage()            │
│         else:                                                       │
│            → CoordinatingAgent::chat()   (existing path)            │
│                                                                     │
│  OnboardingChatDirector                                             │
│    ├─ reads user.onboarding_fyn_step                                │
│    ├─ looks up state in OnboardingStateMachine::STATES              │
│    ├─ captures user answer via OnboardingValueInterpreter           │
│    ├─ writes captured value (update_profile or create_* tool)       │
│    ├─ advances user.onboarding_fyn_step                             │
│    ├─ records onboarding_progress row                               │
│    └─ streams SSE for the NEXT turn (text + bubbles OR delegated)   │
│                                                                     │
│  OnboardingStateMachine (pure config)                               │
│    └─ states, transitions, prompt_text, bubbles, capture rules      │
│                                                                     │
│  OnboardingValueInterpreter                                         │
│    ├─ parseDateOfBirth(string): ?Carbon                             │
│    ├─ parseMaritalStatus(string): ?string                           │
│    ├─ parseEmploymentStatus(string): ?string                        │
│    ├─ parseIncome(string): ?float                                   │
│    └─ parseExpenditure(string): ?float                              │
└─────────────────────────────────────────────────────────────────────┘
```

**Claude involvement is narrow:**
- Turn 1 and all structured bubble turns: **no LLM call.** Director emits text + bubbles directly. Deterministic, fast, free.
- Free-text answers (DOB, income, expenditure): **parsed with PHP first** (Carbon, regex). Only if parsing fails do we fall back to a narrow LLM call. In 95% of cases, no LLM call.
- Asset capture phase: **Claude is used** — delegated to `HasAiChat` with a stripped-down system prompt (no `FcaProcessInstructions`, just `create_*` tool instructions) and the `create_savings_account` / `create_investment_account` / etc. tools. This is where the multi-entity extraction we already fixed in Phase 1a matters.

---

## 3. Data model changes

### 3.1 New migration

`database/migrations/2026_04_15_090000_add_onboarding_fyn_state_to_users.php`

```php
Schema::table('users', function (Blueprint $table) {
    if (! Schema::hasColumn('users', 'onboarding_fyn_step')) {
        $table->string('onboarding_fyn_step', 50)->nullable()->after('onboarding_asset_flags');
    }
    if (! Schema::hasColumn('users', 'onboarding_fyn_path')) {
        // 'journey' or 'focus' — the top-level path the user picked
        $table->string('onboarding_fyn_path', 20)->nullable()->after('onboarding_fyn_step');
    }
    if (! Schema::hasColumn('users', 'onboarding_fyn_selection')) {
        // the specific journey or focus name, e.g. 'savings', 'estate', 'protection'
        $table->string('onboarding_fyn_selection', 50)->nullable()->after('onboarding_fyn_path');
    }
    if (! Schema::hasColumn('users', 'onboarding_fyn_context')) {
        // scratch pad: {"dependants_count": 2, "awaiting": "dependants_ages", ...}
        $table->json('onboarding_fyn_context')->nullable()->after('onboarding_fyn_selection');
    }
});
```

Rollback in `down()`: drop each column (guarded by `Schema::hasColumn`).

### 3.2 Existing tables reused

- `users.onboarding_completed` — the gate. Director only runs when `false`.
- `onboarding_progress` — one row per completed state, for audit and resume. `step_name` = state id, `step_data` = captured value JSON, `completed = true` on advance, `completed_at = now()`.
- `ai_conversations` / `ai_messages` — unchanged. Director writes to them via the same `saveMessage()` helper `HasAiChat` uses.

### 3.3 User fillable/cast additions

`app/Models/User.php` (amended 20 April — original spec said `$fillable` but the model uses `$guarded`):
- The four columns do NOT need `$fillable` entries because the model uses `$guarded` (exclude-list pattern). The columns are implicitly mass-assignable; no action required.
- Add `'onboarding_fyn_context' => 'array'` to `$casts` so it deserialises on read.
- The other three (`onboarding_fyn_step`, `onboarding_fyn_path`, `onboarding_fyn_selection`) are plain strings — no cast needed.

---

## 4. API contracts

### 4.1 `POST /api/ai-chat/onboarding/start` (NEW)

**Auth:** `auth:sanctum`

**Body:** `{}` (empty)

**Response:** `text/event-stream` — same SSE format as `POST /messages`. Server immediately streams the first assistant turn.

**Controller handler:** `AiChatController::startOnboarding(Request $request)`

**Pre-conditions checked:**
1. Authenticated user
2. `$user->onboarding_completed === false`
3. `$user->onboarding_fyn_step === null` (not already mid-flow) — if already mid-flow, the endpoint instead returns a resume event that tells the frontend to load the existing conversation. See §13.
4. `config('onboarding.fyn_flow_enabled') === true` — kill switch (see §14)
5. `$user->is_preview_user === false` — AND `app/Http/Middleware/PreviewWriteInterceptor.php::EXCLUDED_ROUTES` must include `'api/ai-chat/onboarding'` so the middleware doesn't intercept and return fake-success before the controller runs the 403 check. This was missed in the original implementation and is a required fix (amended 20 April — see §20.3 C1).

**Behaviour when preconditions pass:**
1. Create a new `AiConversation` row: `user_id`, `title = 'Onboarding'`.
2. Set `$user->onboarding_fyn_step = 'path_choice'`.
3. Set `$user->onboarding_started_at = now()` (if null).
4. Save user.
5. Delegate to `$director->emitFirstTurn($user, $conversation)` — which yields SSE events.
6. Return a `StreamedResponse` wrapping the generator.

**Behaviour when preconditions fail:**
- `onboarding_completed === true` → return JSON `{success: false, reason: 'already_completed'}` with 409. Frontend falls back to normal chat.
- `fyn_flow_enabled === false` → return JSON `{success: false, reason: 'disabled'}` with 503. Frontend falls back to normal chat.
- Already mid-flow → stream a `resume` SSE event with the existing conversation id, then close. Frontend loads the conversation and lets the user continue.
- Preview user → return JSON `{success: false, reason: 'preview_mode'}` with 403.

### 4.2 `POST /api/ai-chat/conversations/{id}/messages` (MODIFIED)

No signature change. Inside the handler, the existing delegation to `CoordinatingAgent::chat()` is wrapped:

```php
if ($user->onboarding_completed === false
    && $user->onboarding_fyn_step !== null
    && config('onboarding.fyn_flow_enabled')) {
    $generator = $this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute);
} else {
    $generator = $this->coordinatingAgent->chat($user, $conversation, $message, $currentRoute);
}
```

Everything else (SSE wrapping, error handling) stays identical.

### 4.3 `GET /api/ai-chat/onboarding/status` (NEW)

**Auth:** `auth:sanctum`

**Response:** JSON
```json
{
  "in_progress": true,
  "current_step": "base_marital",
  "path": "focus",
  "selection": "savings",
  "conversation_id": 42
}
```
or
```json
{ "in_progress": false }
```

Used by the frontend on chat open to decide whether to call `/start` or resume an existing conversation.

---

## 5. State machine definition

**File:** `app/Services/Onboarding/OnboardingStateMachine.php`

Pure static config. No DB, no dependencies. All state data lives here as a const.

### 5.1 State record shape

```php
'state_id' => [
    'turn_type' => 'bubbles' | 'free_text' | 'delegated' | 'grouped_extract' | 'terminal',
    'prompt_text' => 'string (uses {first_name} template)',
    'bubbles' => [                                           // only if turn_type='bubbles'
        ['id' => 'slug', 'label' => 'Text'],
        // NOTE: `icon` keys shown in the frozen examples below are RETRACTED per the
        // icon rule at the top of this document. `{id, label}` only. Live code also
        // carries an optional `description` key on journey_selection bubbles for
        // richer UX — amended 20 April (see §20.2).
        ...
    ],
    'capture_field' => 'users.column_name' | null,          // what to update on answer
    'capture_section' => 'personal' | 'income_occupation' | 'expenditure' | null,
    'value_parser' => 'method_name' | null,                 // OnboardingValueInterpreter method
    'next' => 'state_id' | callable,                        // deterministic or branching
    'skip_if' => callable | null,                           // skip this state if (e.g.) user already has DOB
    'onboarding_progress_focus_area' => '__setup__' | 'depends_on_selection',
],
```

**Turn types (amended 20 April)**:
- `bubbles` — deterministic quick-reply options. No LLM call.
- `free_text` — user types a value parsed by `OnboardingValueInterpreter`. No LLM call on the happy path; falls back only on parser failure.
- `grouped_extract` — one of the director's LLM-delegated turns where Claude/Grok is handed a **single** extraction tool and a restricted system prompt. Used for `base_personal`, `base_spouse`, `base_dependants_detail`, and `base_work` (see §5.2). Expects exactly one tool call, returns partial-capture receipts (§6.2), and swallows LLM content events to prevent off-script text leaking.
- `delegated` — the asset_capture handoff. Claude/Grok is handed a focus-filtered list of `create_*` tools and builds records. Content events are passed through (this is where "Got it — recording those now." confirmations surface).
- `terminal` — the final `done` state. No further input expected.

### 5.2 Full state list (canonical 20 April — 14 states)

Amended 20 April: two pairs of states collapsed into `grouped_extract` turns for better UX (fewer round-trips, less chat noise). `base_dob` + `base_marital` → `base_personal`. `base_occupation` + `base_income` → `base_work`. The original 16-state table is preserved in git history on the approved 15 April revision of this file.

| State | turn_type | Prompt | Capture | Next |
|---|---|---|---|---|
| `path_choice` | bubbles | "Hi {first_name}, I'm Fyn — welcome to Fynla. I'll help you set up your financial plan. To start, do you want to follow a life-stage journey or pick a single module focus?" | `onboarding_fyn_path` | `journey_selection` if 'journey', `focus_selection` if 'focus' |
| `journey_selection` | bubbles | "Which journey fits your situation best?" (5 bubbles with `description` keys) | `onboarding_fyn_selection` | `base_personal` |
| `focus_selection` | bubbles | "Which area would you like me to focus on first?" | `onboarding_fyn_selection` | `base_personal` |
| `base_personal` | **grouped_extract** | "Let me grab a few basics first, {first_name}. What's your date of birth, and are you single, married, in a civil partnership, divorced, or widowed?" | `users.date_of_birth` + `users.marital_status` via `capture_personal_details` tool | `base_spouse` if married/civil, else `base_dependants` |
| `base_spouse` | grouped_extract | "Great — let's add your spouse/partner's details. Can you share their first name, date of birth, and email address? I'll create an account and link the two of you so you can plan together." | creates `FamilyMember` + linked User via `capture_spouse_details` (uses `SpouseLinkingService`) | `base_dependants` |
| `base_dependants` | bubbles | "Any children or dependants to add?" | scratch `onboarding_fyn_context.has_dependants` | `base_dependants_detail` if yes, `base_employment` if no |
| `base_dependants_detail` | grouped_extract | "Lovely. Tell me their first names, ages, and how they are related to you (child, parent, or other dependant). You can list several in one go." | creates `FamilyMember` rows via `capture_dependants` | `base_employment` |
| `base_employment` | bubbles | "And what's your employment situation at the moment?" | `users.employment_status` | `base_work` if employed/self_employed/part_time, else `base_retirement_date` if retired, else `base_expenditure` |
| `base_work` | **grouped_extract** | "Brilliant. Share the company you work for, your position, and your gross annual income — all in one go is fine." (or trade-name/self-employment variant for self_employed) | `users.employer` + `users.occupation` + `users.annual_employment_income` (or `annual_self_employment_income` for self_employed) via `capture_work_details` | `base_expenditure` |
| `base_retirement_date` | free_text | "When did you retire? A year is fine — something like '2020'." | `users.retirement_date` | `base_expenditure` |
| `base_expenditure` | free_text | `JourneyFieldResolver::getFynPrompt('monthly_expenditure')` | `users.monthly_expenditure` AND `ExpenditureProfile.total_monthly_expenditure` (both written — bug fix §4 from 88018a5) | `asset_capture` |
| `asset_capture` | delegated | Focus-specific intro (9 focuses: savings, investment, retirement, protection, estate, family, business, goals, budgeting) — see `OnboardingStateMachine::buildAssetCaptureIntro` | Claude `create_*` tools via `HasAiChat::chatWithPromptOverride` with focus-filtered tool list | `add_more` |
| `add_more` | bubbles | "Anything else you'd like to cover?" — bubbles strip already-visited focuses, always append "I'm done" | `users.onboarding_fyn_selection` updated to new focus; `onboarding_fyn_context.visited_focuses` appended (bug fix §1 from 88018a5) | loops to `asset_capture` with new selection OR advances to `done` if "I'm done" |
| `done` | terminal | "All set, {first_name}. Your {selection} module is ready to explore." + `navigation` SSE event | sets `users.onboarding_completed = true`, `onboarding_completed_at = now()`, clears all `onboarding_fyn_*` scratch columns | — |

### 5.3 `skip_if` rules (canonical 20 April)

Original 15 April spec listed per-field skip rules for `base_dob`, `base_marital`, `base_occupation`, `base_income`. Those four states no longer exist individually (see §5.2 — collapsed into `base_personal` and `base_work`). Current rules:

- `base_personal` skipped if BOTH `date_of_birth` AND `marital_status` are already set. If only one is set, the turn runs but the prompt adapts (hybrid approach — see below).
- `base_employment` skipped if `employment_status !== null`
- `base_expenditure` skipped if `monthly_expenditure > 0` OR `annual_expenditure > 0`
- `base_work` always runs (the three fields are bundled) — a user who arrives with just employer set won't skip; the `capture_work_details` handler accepts the partial payload and emits a targeted retry for the two missing fields (bug fix §3 from 88018a5).

Director applies skip rules transitively: if the next state has `skip_if` returning true, advance through it without user interaction (emit no turn, just commit the advance and recurse).

**Hybrid skip for `base_personal`** (decision locked 20 April — G2): if the user already has `date_of_birth` set but not `marital_status`, the prompt changes from "Let me grab a few basics first, {first_name}. What's your date of birth, and are you single, married..." to "Got it — I already have you as born {date}. Are you single, married, in a civil partnership, divorced, or widowed?". Reverse case: DOB-only prompt when marital is already set. Implemented by passing the current field state into `OnboardingStateMachine::resolvePromptText` and branching on which field is already populated. The `capture_personal_details` tool handler accepts whichever fields are in the message and (once F6 lands) does not overwrite existing non-empty values.

---

## 6. SSE event shapes

All events follow the existing `HasAiChat` pattern: `data: {json}\n\n`.

### 6.1 Existing events (reused, unchanged)

- `{type: "content", text: "..."}` — text chunks (director uses this for prompt_text)
- `{type: "quick_replies", prompt_text: "...", bubbles: [...]}` — already built in Phase 3
- `{type: "fill_form", entity_type: "...", route: "...", fields: {...}, mode: "create"}` — used by asset capture
- `{type: "navigation", route_path: "...", description: "..."}` — used by `done` state
- `{type: "done", message_id: N}` — end of turn

### 6.2 New events

- `{type: "onboarding_advance", from_step: "base_personal", to_step: "base_spouse"}` — informational, frontend can log/animate (state IDs updated per §5.2 canonical 14-state shape)
- `{type: "onboarding_complete", selection: "savings", nextRoute: "/net-worth/cash"}` — emitted at `done` state, tells frontend to navigate
- `{type: "resume", conversation_id: 42, current_step: "base_personal"}` — emitted by `/start` if user is already mid-flow
- `{type: "conversation_created", conversation_id: N}` — emitted by `/onboarding/start` immediately after the `AiConversation` row is created, before the first turn streams. Frontend uses this to register the conversation id in its Vuex store so subsequent messages post to the correct endpoint. Added 20 April — was in live code since implementation but undocumented in spec. Emission at `AiChatController::startOnboarding` ~lines 284–290.
- `{type: "onboarding_field_captured", field_group: "work"|"personal"|"spouse"|"dependants", summary: "...", details: {missing: [field, ...]?, ...}}` — emitted by `HasAiChat::chat()` when a `grouped_extract` tool returns `onboarding_capture: true`. When `details.missing` is non-empty, the director emits `emitPartialRetry` instead of advancing state (bug fix §3 from 88018a5).

### 6.3 No new events for update_profile

Director writes profile updates directly to the DB (via the existing `UserProfileService` or a direct `$user->update()`). No SSE event needed — the frontend doesn't need to know a write happened, it only cares about rendering the next turn.

---

## 7. Per-turn sequence diagrams

### 7.1 Turn 1 — backend-initiated (NO user message)

```
Frontend                               Backend
────────                               ───────
onOpen() detects openFyn=journey
  │
  ├── GET /api/ai-chat/onboarding/status
  │       ◄── {in_progress: false}
  │
  └── POST /api/ai-chat/onboarding/start ────► AiChatController::startOnboarding
                                                 │
                                                 ├── create AiConversation
                                                 ├── user.onboarding_fyn_step = 'path_choice'
                                                 ├── user.save()
                                                 └── OnboardingChatDirector::emitFirstTurn()
                                                       │
                                                       └── yield events:
                                                             1. {type: 'quick_replies',
                                                                 prompt_text: 'Hi Emma, I'm Fyn...',
                                                                 bubbles: [
                                                                   {id:'journey', label:'Follow a journey', icon:'🧭'},
                                                                   {id:'focus',   label:'Pick a focus',     icon:'🎯'}
                                                                 ]}
                                                             2. {type: 'done', message_id: 1}
                                                             3. persist AiMessage(role=assistant)
                                                             (note: NO user message row)
  ◄── SSE stream
aiChat store adds role='quick_replies'
  message with the bubbles
User sees in chat:
  ┌──────────────────────────────────┐
  │ Hi Emma, I'm Fyn — welcome...    │
  │                                  │
  │  [🧭 Follow a journey]           │
  │  [🎯 Pick a focus]               │
  └──────────────────────────────────┘
```

### 7.2 Turn 2 — user taps "Pick a focus"

```
User tap → FynQuickReplies @select → AiChatPanel.handleQuickReplySelect(bubble)
  │
  └── sendMessage('Pick a focus')
        │
        └── POST /conversations/{id}/messages  body: {message: 'Pick a focus'}
                │
                ├── AiChatController checks user.onboarding_fyn_step === 'path_choice'
                │      && user.onboarding_completed === false
                │
                └── OnboardingChatDirector::handleUserMessage('Pick a focus')
                      │
                      ├── save user message to AiMessage
                      ├── currentState = STATES['path_choice']
                      ├── interpret 'Pick a focus' against bubbles → match id='focus'
                      ├── user.onboarding_fyn_path = 'focus'
                      ├── record onboarding_progress row:
                      │     {step_name:'path_choice', step_data:{path:'focus'}, completed:true}
                      ├── advance user.onboarding_fyn_step = 'focus_selection'
                      └── emit next turn for STATES['focus_selection']:
                            1. yield {type: 'onboarding_advance',
                                      from_step: 'path_choice',
                                      to_step: 'focus_selection'}
                            2. yield {type: 'quick_replies',
                                      prompt_text: 'Which area would you like me to focus on first?',
                                      bubbles: [
                                        {id:'savings',    label:'Savings',    icon:'🏦'},
                                        {id:'investment', label:'Investment', icon:'📈'},
                                        {id:'retirement', label:'Retirement', icon:'👴'},
                                        {id:'protection', label:'Protection', icon:'🛡️'}
                                      ]}
                            3. yield {type: 'done', message_id: 2}
                            4. persist AiMessage(role=assistant)
  ◄── SSE stream
User sees in chat:
  Emma: Pick a focus
  Fyn:  Which area would you like me to focus on first?
        [🏦 Savings] [📈 Investment] [👴 Retirement] [🛡️ Protection]
```

### 7.3 Turn N — free-text interpretation (DOB example)

```
State: base_dob
User types "12 January 1985" → sendMessage
  │
  └── OnboardingChatDirector::handleUserMessage('12 January 1985')
        │
        ├── currentState = STATES['base_dob']
        ├── turn_type = 'free_text'
        ├── parser = 'parseDateOfBirth'
        ├── OnboardingValueInterpreter::parseDateOfBirth('12 January 1985')
        │      │
        │      ├── try Carbon::parse() → success → Carbon(1985-01-12)
        │      └── validate: age >= 18 && age <= 105 → pass
        │      └── return '1985-01-12'
        │
        ├── user.update(['date_of_birth' => '1985-01-12'])
        ├── record onboarding_progress row
        ├── advance to 'base_marital' (checks skip_if → marital_status is null → no skip)
        └── emit turn for base_marital:
              1. yield {type: 'content', text: 'Got it — 12 January 1985.\n\n'}
              2. yield {type: 'quick_replies',
                        prompt_text: 'Are you single, married, in a civil partnership, divorced, or widowed?',
                        bubbles: [...]}
              3. yield {type: 'done'}
```

If `parseDateOfBirth` returns null (couldn't parse), the director yields:
```
1. yield {type: 'content', text: "Sorry, I didn't catch that as a date. Try something like '12 January 1985' or '12/01/1985'."}
2. yield {type: 'done'}
```
State does NOT advance. User retries.

### 7.4 Turn M — asset capture (delegated to Claude)

```
State: asset_capture, selection: savings
User types "I have a £34k ISA at Vanguard and a Lloyds current account with £3,458"
  │
  └── OnboardingChatDirector::handleUserMessage('...')
        │
        ├── currentState = STATES['asset_capture']
        ├── turn_type = 'delegated'
        ├── build restricted system prompt (see §11.2):
        │     - CoreIdentity (yes)
        │     - ComplianceRules (yes)
        │     - FcaProcessInstructions (NO — stripped)
        │     - Asset capture instructions for 'savings' selection (custom)
        │     - Tool list: create_savings_account, create_investment_account only
        │
        ├── delegate to HasAiChat::chat() with the restricted prompt
        │     │
        │     └── (same as existing flow, reuses the Phase 1a fixes)
        │         - Claude emits 2 tool_use blocks
        │         - Two fill_form SSE events yielded
        │         - Two records created via the existing form-fill pipeline
        │
        ├── record onboarding_progress row (step='asset_capture', data: {created: [id1, id2]})
        ├── advance to 'add_more'
        └── emit turn for add_more (quick_replies with unvisited focuses + 'I'm done')
```

### 7.5 Final turn — done

```
State: add_more, user taps "I'm done"
  │
  └── OnboardingChatDirector::handleUserMessage("I'm done")
        │
        ├── user.onboarding_completed = true
        ├── user.onboarding_completed_at = now()
        ├── user.onboarding_fyn_step = null   ← exits the onboarding flow
        ├── record onboarding_progress row (step='done', completed=true)
        │
        ├── determine nextRoute:
        │     - if multiple focuses visited → /dashboard
        │     - else map selection to module route
        │
        └── emit:
              1. yield {type: 'content', text: 'All set, Emma. Your savings module is ready.'}
              2. yield {type: 'navigation', route_path: '/net-worth/cash', description: 'Your savings dashboard'}
              3. yield {type: 'onboarding_complete', selection: 'savings', nextRoute: '/net-worth/cash'}
              4. yield {type: 'done'}
```

---

## 8. Files created

| Path | Purpose | Approx size |
|---|---|---|
| `app/Services/Onboarding/OnboardingChatDirector.php` | Main director. Public methods: `emitFirstTurn`, `handleUserMessage`, `getOnboardingStatus`. | ~400 lines |
| `app/Services/Onboarding/OnboardingStateMachine.php` | Pure config const `STATES`. Public static methods: `getState($id)`, `getNextStateId($currentId, $answer, $user)`. | ~250 lines |
| `app/Services/Onboarding/OnboardingValueInterpreter.php` | Free-text parsers. Public methods: `parseDateOfBirth`, `parseIncomeAmount`, `parseExpenditureAmount`, `parseMaritalFromText`, `parseEmploymentFromText`. | ~150 lines |
| `app/Services/Onboarding/OnboardingPromptBuilder.php` | Short-form system prompt builder for asset_capture turns. Uses `CoreIdentity` + `ComplianceRules` + custom asset-capture instructions. Does NOT use `FcaProcessInstructions` or `NewUserContext`. | ~100 lines |
| `database/migrations/2026_04_15_090000_add_onboarding_fyn_state_to_users.php` | Schema migration (§3.1). | ~40 lines |
| `config/onboarding.php` | Kill switch + feature flags. | ~20 lines |

---

## 9. Files modified

| Path | Change |
|---|---|
| `app/Http/Controllers/Api/AiChatController.php` | Add `startOnboarding()` method, add `getOnboardingStatus()` method, modify `sendMessage()` to delegate to director when in onboarding state. ~80 new lines. |
| `routes/api.php` | Add `POST /ai-chat/onboarding/start` and `GET /ai-chat/onboarding/status`. 2 lines. |
| `app/Models/User.php` | Add 4 columns to `$fillable`, add `onboarding_fyn_context` to `$casts`. 5 lines. |
| `resources/js/components/Shared/AiChatPanel.vue` | Strip the auto-send hack and the hardcoded welcome in `onOpen()`. Replace with a call to a new store action `aiChat/startOnboardingConversation`. Remove `options: [...]` legacy support. |
| `resources/js/store/modules/aiChat.js` | Add `startOnboardingConversation()` action that calls the new endpoint and consumes the SSE stream. Add `getOnboardingStatus()` action. Handle new SSE events: `onboarding_advance`, `onboarding_complete`, `resume`. |
| `resources/js/services/aiChatService.js` | Add `startOnboardingStream()` method and `getOnboardingStatus()` method. |
| `app/Services/AI/SystemPromptBuilder.php` | Remove the `isNewUserWithNoData` substitution of `NewUserContext` (director has its own prompt builder for its own turns, and `NewUserContext` is no longer used by the onboarding flow). BUT keep the new-user guard — for any non-onboarding Fyn chat by a user with no data, still substitute a minimal "don't hallucinate figures" layer. Rename the minimal layer to `EmptyDataGuard` to separate it from the old onboarding script. |
| `app/Services/AI/Prompts/NewUserContext.php` | DELETE. (Replaced by `EmptyDataGuard` for non-onboarding turns and by `OnboardingPromptBuilder` for onboarding turns.) |

---

## 10. Files stripped / dead code removed

| Path | What to remove |
|---|---|
| `resources/js/components/Shared/AiChatPanel.vue` | The `options: [...]` rendering block (lines ~187–197) — legacy life-stage selector. |
| `resources/js/components/Shared/AiChatPanel.vue` | Any reference to `pendingJourneyPrompt` store state outside of `startOnboardingConversation`. Replace with the new action. |
| `resources/js/store/modules/aiChat.js` | `SET_PENDING_JOURNEY_PROMPT` mutation and `pendingJourneyPrompt` state — dead after the rewrite. |
| `app/Services/AI/Prompts/NewUserContext.php` | Whole file. |

### 10.3 Additional cleanup deferred from 20 April audit

- `CoordinatingAgent::educationStatusForAge()` at line 1075 duplicates `OnboardingChatDirector::educationStatusForAge()` at line 582. Extract to a `public static` helper on `OnboardingValueInterpreter` and call from both sites.
- `OnboardingChatDirector::handleAssetCaptureTurn` does not filter `content` events from the delegated generator (unlike `handleGroupedExtractTurn` which was fixed in 88018a5). This is inconsistent but not currently broken — asset_capture WANTS conversational confirmation like "Got it — recording those now." A selective filter is needed: swallow content events that contain a question mark (off-script inference) OR that arrive in a turn where zero tool calls were made. Implementation detail for the F3 fix.

---

## 11. System prompt handling per turn type

### 11.1 Structured turns (bubbles, free_text parsed in PHP) — NO LLM CALL

Director emits SSE events directly from static state config + interpolated first_name. No system prompt, no Claude, no tokens.

### 11.2 Delegated turns (asset_capture) — LLM CALL with restricted prompt

`OnboardingPromptBuilder::build($user, $focus)` produces a SHORT prompt containing only:

1. **`CoreIdentity`** — identity and security rules (must keep for safety).
2. **`ComplianceRules`** — FCA compliance and acronym rules (must keep).
3. **Custom asset capture block** — hardcoded for the onboarding scenario:
   ```
   <asset_capture_turn>
   The user is onboarding. They just selected the {focus} module and you asked them
   to tell you about their existing holdings in this module. Their next message will
   describe one or more holdings in plain language.

   YOUR SINGLE JOB: call the appropriate create_ tool for EACH holding mentioned in
   the user's message. If they mention 3 items, call 3 tools in your first response.
   If they mention 0 items (e.g. they say "I don't have any"), reply with one short
   sentence acknowledging and call no tools.

   Do NOT greet, do NOT summarise, do NOT ask follow-up questions, do NOT navigate,
   do NOT analyse. Just call the create_ tools and keep your text output minimal.

   Tools available to you in this turn:
   {list the create_* tool names relevant to the selected focus}
   </asset_capture_turn>
   ```
4. **Tool list filtered by focus:**
   - savings → `create_savings_account`
   - investment → `create_investment_account`
   - retirement → `create_pension`
   - protection → `create_protection_policy`
   - estate → `create_asset`, `create_liability`, `create_estate_gift`
   - family → `create_family_member`
   - business → `create_business_interest`
   - goals → `create_goal`

The full `FcaProcessInstructions`, `UserProfile`, `FinancialContext`, `ExistingRecords`, `DataCompleteness`, `QueryKnowledge`, and `KycGateChecker` layers are **not sent**. Total prompt size: ~500 tokens (down from ~1600).

### 11.3 Normal Fyn chat (user with data OR onboarding done) — UNCHANGED

`SystemPromptBuilder::build()` builds the full 10-layer prompt as today. Zero regression.

---

## 12. Error handling & edge cases

| Scenario | Director behaviour |
|---|---|
| Claude API fails during asset_capture | Yield `{type: 'content', text: "I had trouble reading that. Could you try listing them one at a time?"}`. Do NOT advance state. Do NOT change `onboarding_fyn_step`. |
| `parseDateOfBirth` fails | Emit retry message, do not advance. |
| User types a bubble label that doesn't exist (typos) | `OnboardingStateMachine::matchBubble($label)` uses case-insensitive + trim + substring match. If no match, emit the current state's bubbles again with a "pick one of these" hint. |
| User types free text in a bubble-only state | Same as above — try to match, else re-show. |
| User asks an unrelated question mid-onboarding ("what is an ISA?") | Out of MVP scope. Director ignores intent and re-asks the current state's question. (A future enhancement: detect question marks, call a single-turn Q&A helper, then re-ask.) |
| User navigates away and closes tab | State is persisted in `users.onboarding_fyn_step`. On next login, frontend calls `/onboarding/status`, sees `in_progress=true`, loads the existing conversation, and the next message continues from the saved state. |
| Preview user clicks onboarding | The `/start` endpoint refuses with `{reason: 'preview_mode'}`. Preview users still go through the existing preview persona flow. |
| Two browser tabs open concurrently | State is per-user, so both tabs read the same `onboarding_fyn_step`. Race condition: both could advance to the same next state. Acceptable for MVP — last write wins, `onboarding_progress` rows will show the merge. |
| Claude calls a tool we didn't whitelist | `AiChatController::executeTool` returns `unknown_tool` error. Claude receives the error and should self-correct. If it doesn't, the turn ends with an error message. |

---

## 13. Resume behavior

On chat open:
1. Frontend dispatches `aiChat/getOnboardingStatus` → hits `GET /onboarding/status`.
2. Backend checks `user.onboarding_completed` and `user.onboarding_fyn_step`.
3. If `in_progress=true`, backend returns the existing `conversation_id`.
4. Frontend loads that conversation's messages via the existing `loadConversation(id)` action.
5. Frontend does NOT call `/start` — state is already established.
6. User sees their existing chat history + they can type/click to continue from the last prompt.

If the user wants to start over: add a "start over" option in the first resume turn (bubble: `{id: 'restart', label: 'Start over'}`). On selection, director:
- Deletes `onboarding_progress` rows for this user
- Clears `user.onboarding_fyn_step`, `onboarding_fyn_path`, `onboarding_fyn_selection`, `onboarding_fyn_context`
- Deletes the existing onboarding conversation (or archives it)
- Emits the `path_choice` turn fresh

---

## 14. Rollback / kill switch

`config/onboarding.php`:
```php
return [
    'fyn_flow_enabled' => env('ONBOARDING_FYN_FLOW_ENABLED', true),
];
```

If set to `false`:
- `/onboarding/start` returns `{success: false, reason: 'disabled'}` 503
- `/messages` does NOT delegate to director even for users with `onboarding_fyn_step != null` — falls through to `CoordinatingAgent::chat()`
- The landing page CTA still routes to `/register?from=fyn`, but the onboarding chat never triggers. User lands on dashboard like any other registered user.

Frontend-side, `AiChatPanel.onOpen()` catches the 503 and falls through to normal chat open.

To hard-disable: set `ONBOARDING_FYN_FLOW_ENABLED=false` in `.env`, `php artisan config:clear`. Zero file changes needed. All existing users and in-flight sessions revert to the standard Fyn chat.

---

## 15. Implementation sequencing (order of work)

I will do this in 6 ordered commits on `onboardingFyn`, each self-contained and testable:

1. **Migration + model + config.** Add the 4 columns, add to fillable, create `config/onboarding.php`. Run `php artisan migrate`, verify columns exist. No behavioural change yet.

2. **Backend data structures (no behaviour).** Create `OnboardingStateMachine.php` with the full STATES const. Create `OnboardingValueInterpreter.php` with all parser methods. Create unit tests for the parsers (Pest). No director yet, no routes yet.

3. **Director core + endpoints.** Create `OnboardingChatDirector.php`. Create `OnboardingPromptBuilder.php`. Wire `AiChatController::startOnboarding()`, `getOnboardingStatus()`, and the delegation in `sendMessage()`. Add routes. At this point the backend is functional and can be hit directly with curl.

4. **Frontend wiring.** Modify `AiChatPanel.vue` and `aiChat.js` and `aiChatService.js`. Remove the legacy code paths. Wire the new `startOnboardingConversation` action. Handle the new SSE events. No visible behaviour change until turn 1 streams end-to-end.

5. **Strip dead code.** Delete `NewUserContext.php`. Rename any remnant references. Remove `SET_PENDING_JOURNEY_PROMPT` and `options:` legacy frontend paths.

6. **Browser test.** Delete test user, register fresh via CTA, walk through the full flow via Playwright. Verify: turn 1 appears with NO preceding user message, bubbles render, each click advances one state, free-text DOB parses, asset capture handles a multi-entity message, done state navigates. Verify DB state at each step via tinker.

If any commit fails browser verification, I stop, diagnose, fix, retest — I do not move to the next commit.

---

## 16. Test plan

**Unit (Pest):**
- `OnboardingValueInterpreter::parseDateOfBirth` — cases: `'12 January 1985'`, `'12/01/1985'`, `'1985-01-12'`, `'yesterday'` (should fail — not a DOB), `'12 Jan 1980'`, invalid formats. Assert age range 18–105.
- `OnboardingValueInterpreter::parseIncomeAmount` — `'£75,000'`, `'75k'`, `'seventy-five thousand'` (fail is OK), `'75000'`. Strip £ and commas.
- `OnboardingStateMachine::getNextStateId` — test all branching states (marital → spouse if married, else dependants; employment → occupation if employed, else retirement_date if retired, else income).

**Integration (Pest feature tests):**
- `POST /onboarding/start` as a fresh user → 200 SSE, assert `AiConversation` created, `user.onboarding_fyn_step === 'path_choice'`, first AiMessage is `role=assistant` with bubbles metadata.
- `POST /onboarding/start` as a user with `onboarding_completed=true` → 409.
- `POST /conversations/{id}/messages` with message `'Pick a focus'` in state `path_choice` → advances to `focus_selection`, user.onboarding_fyn_path === 'focus'.
- Full walkthrough from `path_choice` → `done` via a series of posts. Assert each state transition.
- Asset capture with multi-entity message → verify 2 records created.

**Browser (Playwright, end-to-end):**
1. Register fresh user via CTA → OTP verified → lands on dashboard
2. Fyn chat auto-opens
3. Verify DOM: exactly ONE message bubble from Fyn, containing the greeting + 2 bubbles. No "Welcome to Fynla" static message. No user message in the history.
4. Click "Pick a focus" → verify user message "Pick a focus" appears, then Fyn responds with 4 focus bubbles in ONE message.
5. Click "Savings" → Fyn asks DOB in plain text.
6. Type "12 January 1985" → Fyn confirms + asks marital status with bubbles.
7. Walk through all base data.
8. Reach asset capture → type "I have a £10,000 Vanguard ISA and a Barclays current account with £500" → verify 1 investment_account + 1 savings_account created in DB.
9. Reach add_more → tap "I'm done"
10. Verify navigation to `/net-worth/cash`, `user.onboarding_completed === true`.

**Regression:**
11. Log in as `phase0test@example.com` (existing user with data) → type a normal question → Fyn responds via the old path (not the director). Verify `user.onboarding_fyn_step` is untouched and no onboarding_progress rows are written.

---

## 17. What I explicitly will NOT do

- No changes to `FcaProcessInstructions.php` content.
- No changes to `HasAiChat::chat()` generator — the director uses the same streaming primitives; it doesn't rewrite them.
- No new Vue components beyond the existing `FynQuickReplies.vue`.
- No refactoring of the existing `CoordinatingAgent` or its tool handlers.
- No deletion of the legacy `/onboarding` wizard at `OnboardingView.vue`.
- No rename of existing DB columns.
- No changes to the multi-entity bug fixes from Phase 1a.

---

## 18. Gaps filled from the approved plan

| Gap in original plan | Filled here |
|---|---|
| How does turn 1 fire with no user input? | §4.1 + §7.1: new `POST /onboarding/start` endpoint + `emitFirstTurn()` method |
| Frontend trigger or backend auto-start? | §2 + §7.1: both — frontend calls the endpoint, backend decides what turn 1 looks like |
| Replace or override `FcaProcessInstructions`? | §11: skip it entirely during onboarding via a separate `OnboardingPromptBuilder`; leave it untouched for normal Fyn |
| How is state stored? | §3.1: new `users.onboarding_fyn_step` column + existing `onboarding_progress` rows |
| What triggers Claude vs not? | §11: structured turns skip Claude entirely; only asset_capture delegates to Claude |
| Resume flow? | §13: explicit status endpoint + existing conversation load |
| Kill switch? | §14: config flag |

---

## 19. Approval gate

Signed off by CSJ on 15 April 2026 (session 54). All 8 points approved as written.

- [x] Architecture (§2) — backend-authoritative state machine, Claude only for asset capture
- [x] Data model changes (§3) — 4 new columns on `users`
- [x] API surface (§4) — 2 new endpoints, 1 modified
- [x] State machine shape (§5) — 16 states, specific branching rules
- [x] System prompt handling (§11) — `FcaProcessInstructions` skipped in onboarding turns
- [x] Kill switch (§14) — config flag, default ON
- [x] Sequencing (§15) — 6 ordered commits, stop-and-diagnose on failure (preceded by commit 0 = revert of Phase 4 deviations)
- [x] Out of scope list (§17)

### Supplementary decisions recorded at approval

1. **`base_marital` branching** — married OR civil_partnership → `base_spouse`; single/divorced/widowed → `base_dependants`. Approved.
2. **`base_spouse` record shape** — creates a `FamilyMember` row with `relationship='spouse'` for both `married` and `civil_partnership`. The legal distinction is preserved on `users.marital_status`. `family_members.relationship` ENUM is `('spouse','child','parent','other_dependent')` — no dedicated partner value in the current schema, so the mapping collapses both legal forms onto `spouse`. Dependants use `child` or `other_dependent`. If a dedicated `partner` enum value is wanted later, it requires a separate enum migration — not in scope for this plan.
3. **`add_more` loop semantics** — when a user picks a second focus, the director advances straight to that focus's `asset_capture` state. Base data is NOT re-asked. Approved.
4. **Mobile (`MobileFynChat.vue`)** — deferred out of scope for this plan. The iOS app will fall back to normal Fyn chat until a follow-up task wires the new SSE events into `MobileFynChat.vue`.
5. **`dc_pensions.current_value` prod migration** — separate ops task, not rolled into this plan.

---

## 20. Delta register (20 April 2026)

Tracks every change from the 15 April approved spec to the live `onboardingFyn` HEAD, plus the forward work locked in by this amendment. Severity tags mirror the comprehensive audit at `April/April20Updates/fynComprehensiveCheck.md`.

### 20.1 Live state (shipped, commit `88018a5` or earlier)

| Item | Status |
|---|---|
| 4 new `users` columns (§3.1) | Shipped |
| `POST /onboarding/start`, `GET /onboarding/status`, modified `/messages` (§4) | Shipped |
| `OnboardingChatDirector`, `OnboardingStateMachine`, `OnboardingValueInterpreter`, `OnboardingPromptBuilder` (§8) | Shipped |
| `FynQuickReplies.vue` + frontend wiring (§9) | Shipped, with `description` shape extension |
| `NewUserContext.php` deleted; `EmptyDataGuard.php` in its place (§10) | Shipped |
| Kill switch `config('onboarding.fyn_flow_enabled')` (§14) | Shipped |
| Landing page CTA un-hidden (§15) | Shipped |
| Bug fix §1: add_more selection persistence | Shipped in `88018a5` |
| Bug fix §2: LLM content event swallow on grouped_extract | Shipped in `88018a5` |
| Bug fix §3: capture_work_details partial-capture with targeted retry | Shipped in `88018a5` |
| Bug fix §4: base_expenditure writes to ExpenditureProfile | Shipped in `88018a5` |

### 20.2 Live state (shipped with deviation from 15 April spec — canonical as of this amendment)

| Item | Original spec | Live code (now canonical) |
|---|---|---|
| State count | 16 | 14 (`base_dob+base_marital` → `base_personal`; `base_occupation+base_income` → `base_work`) |
| `turn_type` enum | `bubbles\|free_text\|delegated` | adds `grouped_extract`, `terminal` |
| Bubble shape | `{id, label}` | `{id, label, description?}` — description used on journey_selection for richer UX |
| User model fillable | "$fillable" instruction | `$guarded` in live code (spec was wrong) |
| `conversation_created` SSE event | not documented | emitted by `/start` controller |

### 20.3 Required fixes (in scope for this PRD — Must-have)

- **C1 — Preview isolation**: add `'api/ai-chat/onboarding'` to `PreviewWriteInterceptor::EXCLUDED_ROUTES`. Makes the controller's 403 actually fire.
- **G2 — Hybrid skip rule on `base_personal`**: pre-fill already-captured field in the prompt (see §5.3 amendment above).
- **G1 — Feature tests**: integration tests for `POST /onboarding/start` (200 and 409 cases), state-machine walkthrough, multi-entity asset capture.
- **F1 — Post-onboarding expenditure sync**: `handleSetExpenditure` must mirror to `ExpenditureProfile.total_monthly_expenditure` (same fix pattern as bug §4).
- **F2 — Spouse-email collision**: `SpouseLinkingService` throws a new `SpouseCollisionException`. `handleCaptureSpouseDetails` catches it and returns a distinct error receipt. Director `emitTerminalError` renders the user-facing copy: *"That email's already registered with another Fynla household. Want to use a different address for your partner, or ask them to link their own account?"*
- **F3 — Family asset_capture off-script**: tighten `OnboardingPromptBuilder::assetCaptureInstructions` with an explicit "do not ask about property, mortgages, or anything outside the listed tools". Selective content-event filter on `handleAssetCaptureTurn` (swallow off-script text; preserve single-sentence confirmations). Prompt-only approach, `tool_choice='auto'` retained so the "I don't have any" path still works.
- **F5 — Trust CLT orphan risk**: MOVE CLT auto-creation from `handleCreateTrust` to a `Trust` model observer listening on `created`. Tool returns `fill_form` as today; CLT only writes when trust actually saves.

### 20.4 Should-have fixes (in scope, next iteration)

- **F4 — `handleUpdateRecord` per-entity allowlist**: `private const ALLOWED_UPDATE_FIELDS` array on `CoordinatingAgent` keyed by the 12 entity types in `resolveModel()`. Intersect with `$fillable`.
- **F6 — `handleCapturePersonalDetails` + `handleCaptureSpouseDetails` partial-capture**: apply the `handleCaptureWorkDetails` template — save non-empty fields, compute `missing`, return `onboarding_capture: true` with `details.missing`. Director's `composePartialRetryText` already has the friendly-map entries for both tools.
- **Cleanup**: extract `educationStatusForAge` to `OnboardingValueInterpreter::educationStatusForAge`. Remove both duplicates.
- **Cleanup**: selective content-event filter in `handleAssetCaptureTurn` (matches F3's needs).

### 20.5 Nice-to-have fixes (in scope if time permits)

- **F7** — surface `users.employer` + `users.occupation` in `SystemPromptBuilder::buildUserProfile`.
- **F8** — `SystemPromptBuilder::calculateTotalExpenditure` fallback to `ExpenditureProfile.total_monthly_expenditure` (mirror `KycGateChecker` pattern).
- **F9** — duplicate-name checks on `create_trust` / `create_family_member` / `create_business_interest` / `create_asset` / `create_liability` / `create_estate_gift` / `create_chattel` (pattern from `handleCreateSavingsAccount`).
- **F10** — `handleUpdateProfile` spouse-linked-user sync.
- **F11** — `handleSetExpenditure` spouse sync for household budget.
- **F12** — add missing routes (`/estate/inheritance-tax`, `/settings/privacy`, risk sub-routes) to `navigate_to_page` allow-list.
- **F13** — `handleCreateEstateAsset` + `handleCreateEstateGift` partial-payload tolerance.
