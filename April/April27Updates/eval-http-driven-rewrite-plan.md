# Eval system — HTTP-driven rewrite plan

*Authored 2026-04-27 session 104. Branch: `feature/fyn-persona-split`. Trigger: every prior eval session has been measuring the wrong thing — the eval invokes `AdviceFyn::handle()` directly from a CLI artisan command and bypasses the entire HTTP/auth/middleware/controller stack a real user goes through. Prior sessions verified system-prompt parity and called it flow parity. It isn't. CSJ: "I want the eval to follow the user journey EXACTLY, otherwise it is not an eval."*

> **Source of truth.** This is the plan that supersedes:
> - `April/April27Updates/eval-expectations-rewrite.md` Section 4 (per-scenario YAML rewrite — assertion *shape* still useful, but the YAML format is replaced by JSON and the synthetic `seed:` block is replaced by persona binding).
> - `April/April24Updates/plan/11-sprint-1-plan.md` S1.2.l (rewrite 10 YAMLs against running contract — done in session 102, the rewrite outputs become the assertion-shape template for the JSON scenarios but the synthetic seeds get scrapped).
> - `April/April24Updates/plan/11-sprint-1-plan.md` S1.7.d Path A (`EvalDeltaBuilder` wired into `EvalRecordingController` — the delta-builder service survives unchanged, only the input shape changes).
>
> Everything in those documents that depends on `EvalRecordCommand::seedUser` creating synthetic eval users and calling `AdviceFyn::handle()` directly is OBSOLETE. The assertion-shape work (multi-label classification, KYC state, `result_path`, per-provider per-path timing, `EvalDeltaBuilder`, `AssertionHelpers` extensions) is REUSED.

---

## TL;DR

The eval today calls `AdviceFyn::handle($user, $conversation, $message, null)` straight from the CLI. A real user calls `POST /api/ai-chat/conversations/{id}/messages` over HTTP with a Sanctum token. Those two paths share the inner agent code but differ in everything around it: auth context, middleware (`SanitizeInput`, `PreviewWriteInterceptor`, consent gate, rate limits), `current_route`, conversation creation, request lifecycle, provider selection, SSE wrapping. Anything in the stack that branches on those — and there's a lot — runs differently in the eval than in the live app.

This plan replaces the CLI-direct eval with an **HTTP-driven eval**:

1. The eval logs in **as the actual `peak_earners` preview user** via a new `POST /api/eval/login/{personaId}` endpoint that issues a Sanctum token **with the `bypass-preview-mode` ability**. The 3 server-side write-block checkpoints (`PreviewWriteInterceptor`, `HasAiChat` tool-list filter, `CoordinatingAgent` per-tool handlers) check the token's ability and let writes through when it's set.
2. The eval creates a conversation via `POST /api/ai-chat/conversations`.
3. The eval sends the message via `POST /api/ai-chat/conversations/{id}/messages`, using the Sanctum token as the bearer, identical to what the chat panel sends.
4. The eval consumes the SSE response stream byte-for-byte, the same way the frontend Vuex `aiChat.js` module does.
5. **In parallel**, an in-process `EvalTraceCollector` listens for `GateChecked` / `EngineCalled` / `AgentDecision` events emitted by the gate services, agents, and engines — capturing every KYC check, every readiness check, every secondary profile gate, every recommendation-waterfall step, every `orchestrateAnalysis` call. This is the engine/gate trace.
6. The eval asserts against the captured stream + the post-message DB state + the engine/gate trace using a JSON scenario file.
7. After mutating runs, the eval restores the `peak_earners` preview user via the existing `php artisan preview:reset peak_earners` command.

Scenario format moves from YAML to JSON. The first 10 scenarios bind to `peak_earners` (David & Sarah Mitchell). The synthetic `seed:` block and `User::create()` per scenario are GONE. **No persona-mirror users, no `EvalUserSeeder`** — the eval uses the actual seeded preview data.

The session-102 assertion shape (`expected_classification_shape`, `expected_response_mode`, `expected_engine_call_level`, `expected_kyc_state`, per-tool `result_path`, `expected_assistant_text`, per-provider per-path `timing_budget_ms`, `EvalDeltaBuilder`, `AssertionHelpers`) is reused. Only the result_path values get re-tuned because peak_earners has full data and most scenarios will hit `happy` not `success_false`.

---

## Section 1 — The lie, named precisely

### 1.1 What the eval does today

`app/Console/Commands/EvalRecordCommand.php` line 296:
```php
$generator = $inOnboarding
    ? $this->onboardingDirector->handleUserMessage($user, $conversation, $userMessage, null)
    : $this->adviceFyn->handle($user, $conversation, $userMessage, null);
```

Direct method call from a CLI artisan command on a CLI-built `User` and `AiConversation`.

### 1.2 What a real user does

`app/Http/Controllers/Api/AiChatController.php::sendMessage` lines 141-237:
```php
HTTP POST /api/ai-chat/conversations/{id}/messages
  → Sanctum guard (auth:sanctum middleware)
  → throttle:20,1
  → SanitizeInput middleware (strips HTML, trims whitespace)
  → PreviewWriteInterceptor middleware (blocks writes from preview users)
  → AiChatController::sendMessage
    → $request->validate(['message' => 'required|string|max:2000', ...])
    → $user = $request->user()                      // Sanctum-resolved
    → consent gate: ConsentService::hasConsent($user, AI_CHAT)
    → $conversation = AiConversation::forUser($user->id)->findOrFail($id)
    → $currentRoute = $request->input('current_route')
    → dispatch decision: $inOnboarding = onboarding_completed === false && step !== null
    → return new StreamedResponse(function () { ... }, 200, ['Content-Type' => 'text/event-stream', ...])
      → $this->adviceFyn->handle($user, $conversation, $message, $currentRoute)
        → mid-stream consent re-check after every event
        → echo 'data: ' . json_encode($event) . "\n\n"; ob_flush(); flush();
```

The inner `adviceFyn->handle()` call is identical between the two paths. **Everything outside that call is different.**

### 1.3 Concrete divergences — every one of these is a real risk surface

| # | Live flow | Eval flow today | Risk |
|---|---|---|---|
| 1 | `Auth::user()` returns the Sanctum-resolved user | `Auth::user()` returns `null` (CLI has no auth context) | Anything in agents/tools that reads `Auth::user()` instead of the passed-in `$user` sees nothing |
| 2 | `current_route` arrives from the frontend (e.g. `/dashboard`, `/protection`) | Hard-coded `null` (line 296) | Any prompt builder or tool that branches on route context behaves differently |
| 3 | Provider selection: `Cache::get('ai_provider', config('services.ai_provider', 'anthropic'))` (`AdviceFyn:432`) — global cache key set by user/system config | `Cache::forever('ai_provider', $provider)` set IMMEDIATELY before the call (`EvalRecordCommand:241`), restored after | Eval forces provider; real users don't. Cache key leaks across concurrent requests (CLI cache and HTTP cache may be the same store) |
| 4 | Conversation loaded by `AiConversation::forUser($user->id)->findOrFail($id)` — must already exist via the create endpoint | Conversation hand-built via `AiConversation::create([...])` with `'persona_state' => ['source' => 'eval-record']` (`EvalRecordCommand:842-852`) | Hand-built conversation has fields a real one wouldn't, and may lack fields a real one would |
| 5 | `SanitizeInput` middleware strips HTML and trims whitespace before the message reaches the agent | Message passed verbatim from YAML | LLM input differs for any message with leading/trailing whitespace or HTML-like tokens |
| 6 | `PreviewWriteInterceptor` blocks writes from `is_preview_user=true` users | [REVISED 2026-04-28 per canonical 0.2] Eval logs in as the actual seeded `peak_earners` preview user; the Sanctum token's `bypass-preview-mode` ability is what lets writes through at the 3 write-block sites. The interceptor DOES fire and DOES check the token ability per request — there is no `is_eval_user` flag and no mirror user. | Today: byte-identical interceptor execution to a real user, gated only by the per-token bypass ability. |
| 7 | Consent gate runs at controller entry AND mid-stream after every event | No consent gate at all | A consent withdrawal during eval is invisible. Tool calls that look at consent state behave differently |
| 8 | `request()->ip()`, `request()->bearerToken()`, `request()->header('User-Agent')` are populated | All return `null` in CLI | Audit logs, rate-limit keys, anything that uses request headers all see CLI null |
| 9 | SSE streaming: events written to PHP output buffer, flushed via `ob_flush(); flush();` | SSE events collected into an in-memory PHP array via `foreach ($generator as $event) { $events[] = $event; }` | Real users see events as they arrive (mid-stream timing matters); eval sees them all at the end. Any timing-sensitive tool (cancel, timeout) behaves differently. The `--mode=deterministic` flag in `eval-expectations-rewrite.md` §10.4 only widens this gap |
| 10 | Conversation creation goes through `AiChatController::create` → `AiConversation::create([...])` with controller defaults | Direct `AiConversation::create([...])` from the CLI command | Controller-level defaults (status, model, persona_state shape) are bypassed |

Prior sessions checked the rendered system prompt (the one that goes to the LLM) and verified it matched line-for-line between eval and live. They saw `<financial_context>` + `<existing_records>` + `<data_completeness>` PRESENT and `<new_user_state>` + `<billing_guidance>` ABSENT in both, declared parity, and called it. **Prompt parity is necessary but not sufficient for flow parity.** The flow parity check was never done.

That's the lie. Not deliberate. But the result is the same: every eval recording on this branch has been measuring a code path that no user ever takes.

---

## Section 2 — Goal

The eval drives **the same HTTP endpoints, in the same order, with the same payloads** that a logged-in browser session drives. The eval's authentication is a real Sanctum token. The eval's chat send is `POST /api/ai-chat/conversations/{id}/messages`. The eval's SSE consumption is identical to the frontend's `EventSource`-style reader.

**[REVISED 2026-04-28 per canonical 0.2.]** The eval runs against the **actual seeded `peak_earners` preview user** (David & Sarah Mitchell — full multi-module data: protection, savings, investments, DC pensions, properties, estate (LPAs + wills + gifts), goals). There is no mirror user, no `EvalPersonaResetService`, no separate row. Mutating evals restore the persona via the canonical 0.1 reset orchestration in `EvalRecordCommand` (caller-side, post-capture, only when persisted `db_writes` is non-empty).

The first 10 scenarios all bind to `peak_earners`. Subsequent scenarios pick the persona that exercises the path being measured (e.g. `young_saver` for emergency-fund-blocked, `student` for LISA, etc.).

---

## Section 3 — Architecture

### 3.1 Components — what's new, what changes, what dies, what survives

| Component | Status | Notes |
|---|---|---|
| `app/Console/Commands/EvalRecordCommand.php` | **REWRITTEN** | Becomes a thin wrapper around `EvalHttpDriver`. No more `seedUser`, no more `seedChildEntities`, no more direct `$this->adviceFyn->handle()` call, no more `createConversation` from the command. The `Cache::forever('ai_provider', ...)` block moves into `EvalHttpDriver`. Roughly 70% of the file deletes. |
| `app/Services/Eval/EvalHttpDriver.php` | **NEW** | The HTTP loop. Logs in via `POST /api/eval/login/{personaId}`, creates conversation via `POST /api/ai-chat/conversations`, sends message via `POST /api/ai-chat/conversations/{id}/messages`, consumes SSE, returns events + final state + engine/gate trace. ~280 lines. |
| `app/Services/Eval/EvalSseConsumer.php` | **NEW** | Reads an SSE response body byte-for-byte using PHP's HTTP client streaming (`Http::withOptions(['stream' => true])` or a Guzzle stream), parses `data: ...\n\n` frames, yields events. ~80 lines. |
| `app/Services/Eval/EvalTraceCollector.php` | **NEW** | Request-scoped singleton. Collects `GateChecked`, `EngineCalled`, `AgentDecision` events into an ordered timeline. Read by `EvalHttpDriver` after the request completes via the `GET /api/eval/trace/{conversationId}` endpoint (see §5.5). ~120 lines. |
| `app/Listeners/Eval/EvalTraceListener.php` | **NEW** | Subscribes to the 3 trace events. Only forwards to `EvalTraceCollector` when the active token carries the `bypass-preview-mode` ability (so non-eval requests pay zero overhead). ~60 lines. |
| `app/Events/Eval/GateChecked.php` + `EngineCalled.php` + `AgentDecision.php` | **NEW** | 3 thin event classes (~30 lines each). Fired by gate services / agents / engines. See §5.3 for the call-site list. |
| `app/Http/Controllers/Api/EvalAuthController.php` | **NEW** | Implements `POST /api/eval/login/{personaId}` (issues a Sanctum token with `bypass-preview-mode` ability for the existing peak_earners preview user), `POST /api/eval/reset/{personaId}` (wraps `Artisan::call('preview:reset', ...)`), and `GET /api/eval/trace/{conversationId}` (returns the captured `EvalTraceCollector` timeline for a recording). All 3 gated to `APP_ENV !== 'production'`. ~120 lines. |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | **AMENDED** | Add a 5-line check at line ~104: if `$user->is_preview_user` AND the bearer token has `bypass-preview-mode` ability, skip the interceptor. |
| `app/Traits/HasAiChat.php` | **AMENDED** | One-line change at line 144: factor in token ability when computing the preview-mode flag. |
| `app/Agents/CoordinatingAgent.php` | **AMENDED** | One-line change at line 699: factor in token ability when computing `$isPreviewUser`. The 24 downstream tool handlers are NOT touched — they keep reading the flag the same way. |
| `app/Console/Commands/ResetPreviewData.php` | **AMENDED** | Extend `deleteUserData` to cover the missing 13 child-entity types (profiles, goals, LPAs, wills, trusts, etc.). Add a regression test that asserts every table the persona seeder writes to is in the reset's deletion list. |
| `routes/api.php` | **AMENDED** | Add the 3 eval endpoints in a `Route::middleware(['throttle:20,1'])->prefix('eval')` group, gated by an `if (! app()->environment('production'))` block. |
| `tests/Feature/Fyn/Eval/scenarios/01-query-types/*.yaml` | **CONVERTED → .json** | Scenario format becomes JSON. Synthetic `seed:` block REMOVED entirely. New top-level `persona: peak_earners` field. Everything else (assertion shape from session 102) preserved. 10 files renamed and re-keyed. |
| `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/*.yaml` | **CONVERTED → .json** | Same conversion. 4 files. |
| `tests/Feature/Fyn/Eval/AssertionHelpers.php` | **AMENDED (additive)** | Add 3 new helpers: `assertSseStreamComplete($events)` (asserts `done` was emitted exactly once and no `error` events), `assertHttpStatusCodes($responseLog)` (asserts every HTTP call returned the expected status), `assertNoMiddlewareBypass($events)` (asserts the consent_required SSE event never fired mid-stream — proves the consent gate ran). Existing 9 helpers from session 102 unchanged. |
| `app/Services/Eval/EvalDeltaBuilder.php` | **AMENDED (small)** | Already builds delta from the captured `EvalProviderRun`. Now reads scenario JSON instead of YAML. Single `Yaml::parse` → `json_decode` swap on line ~65. 5-line change. |
| `app/Http/Controllers/Api/Admin/EvalRecordingController.php` | **AMENDED (small)** | Same Yaml→JSON swap on line ~190 in `parseExpectations`. The new top-level fields (`persona`, `http_log`) are passed through to the dashboard. |
| `app/Models/EvalRecordingSession.php` | **AMENDED** | Add `persona` column, `http_log` JSON column (every HTTP call: method, url, status, duration_ms, request_headers (sanitized), response_headers, sse_event_count). |
| `app/Models/EvalProviderRun.php` | **NO CHANGE** | Stores assistant_text, tool_calls, sse_event_count, db_writes_made, end_state_snapshot, fixture_path, duration_ms — all unchanged. The data still lands here, sourced from the HTTP-captured stream. |
| `database/migrations/2026_04_27_000002_add_persona_and_http_log_to_eval_recording_sessions.php` | **NEW** | Adds `persona VARCHAR(64) NULL` and `http_log JSON NULL` columns. |
| `resources/js/components/Admin/eval/RunPanel.vue` | **AMENDED (small)** | Add a new "HTTP log" panel showing each HTTP call: method, URL, status, duration, header summary. Displays `http_log` from the session. ~60 lines added. |
| `tests/Feature/Fyn/Eval/EvalRunner.php` | **AMENDED** | Reads `.json` scenarios. Calls `EvalHttpDriver` instead of `MockedProviderClient` for live recording. Mocked-mode replay (Mode 1) still uses `MockedProviderClient` for fixture replay. |
| `tests/Feature/Fyn/Eval/MockedProviderClient.php` | **NO CHANGE** | The mocked-mode replay path is unchanged. The mocked client returns the recorded jsonl byte-for-byte; what the eval ASSERTS about the stream is what changes. |
| `app/Services/AI/AdviceFyn.php` | **NO CHANGE** | The `Cache::get('ai_provider', config('services.ai_provider', 'anthropic'))` pattern stays. Eval sets the global cache key before the HTTP call from outside the agent — same way config or admin would. Documented but not changed. |

### 3.2 The HTTP loop

Pseudocode for `EvalHttpDriver::run()`:

```
function run(scenario_json, provider, model):
    # Set provider for this run via the same global cache key the agent reads.
    # This is exactly what an admin would do to switch providers system-wide.
    previous_provider = Cache::get('ai_provider')
    Cache::forever('ai_provider', provider)
    config_set("services.{provider}.chat_model", model)

    try:
        # [REVISED 2026-04-28] Pre-flight reset removed per canonical 0.1
        # (April/April28Updates/maxAuditEval.md §0). Non-mutating evals must
        # NEVER reset the persona. Mutating-scenario reset moved to the
        # CALLER (EvalRecordCommand), runs AFTER capture, only on persisted
        # writes. The driver itself does not reset.

        # 1. Snapshot start state for diff
        start_state = snapshot_state(preview_user_for_persona(scenario.persona))

        # 2. Login via real HTTP — gets a real Sanctum token
        login_response = http.post(
            "http://localhost:8000/api/eval/login/{scenario.persona}",
            timeout: 5
        )
        assert login_response.status == 200
        token = login_response.json["token"]
        user_id = login_response.json["user"]["id"]
        http_log.append({method: POST, url: ..., status: 200, duration_ms: ...})

        # 4. Create conversation via real HTTP
        conv_response = http.post(
            "http://localhost:8000/api/ai-chat/conversations",
            headers: {Authorization: "Bearer " + token},
            json: {title: "Eval recording", model_used: model},
            timeout: 5
        )
        assert conv_response.status == 200 OR 201
        conversation_id = conv_response.json["data"]["id"]
        http_log.append(...)

        # 5. For each turn, send message via real HTTP and consume SSE
        for turn in scenario.input.turns:
            send_response = http.post(
                "http://localhost:8000/api/ai-chat/conversations/{conversation_id}/messages",
                headers: {Authorization: "Bearer " + token, Accept: "text/event-stream"},
                json: {message: turn.user, current_route: turn.current_route OR null},
                stream: true,
                timeout: 60        # SSE streams can run long
            )
            assert send_response.status == 200
            assert send_response.headers["Content-Type"] starts with "text/event-stream"

            events = sse_consumer.consume(send_response.body)
            all_events.append(...events)
            http_log.append(...)

        # 6. Snapshot end state, compute diff
        end_state = snapshot_state(eval_user_for_persona(scenario.persona))
        db_writes = diff_snapshots(start_state, end_state)

        # 7. Logout (cleanup token)
        http.post("http://localhost:8000/api/auth/logout", headers: {Authorization: "Bearer " + token})

        return {events, db_writes, http_log, start_state, end_state}

    finally:
        # Restore provider
        if previous_provider is None:
            Cache::forget('ai_provider')
        else:
            Cache::forever('ai_provider', previous_provider)

        # [REVISED 2026-04-28] Reset moved out of driver per canonical 0.1.
        # Reset orchestration belongs in the CALLER (EvalRecordCommand),
        # AFTER EvalProviderRun::create() persists the captured change,
        # AND ONLY if the persisted db_writes diff is non-empty.
        # Non-mutating scenarios (all 10 current mitchell scenarios) never
        # reset. The driver does NOT reset in finally — it only restores
        # the provider cache key.
```

### 3.3 Why this is byte-identical to a real user

Every line above corresponds to something a real browser session does:

| Step | Real user equivalent |
|---|---|
| `POST /api/eval/login/{persona}` | `POST /api/auth/login` + `POST /api/auth/verify-code` (the eval skips MFA via the eval-only login endpoint, but the rest of the flow is identical) |
| `POST /api/ai-chat/conversations` | Frontend calls this when opening a new conversation in the chat panel |
| `POST /api/ai-chat/conversations/{id}/messages` | Frontend sends this on every chat turn |
| Consuming SSE from response body | Frontend's `aiChat.js` Vuex store does this with `EventSource` / `fetch` streaming |
| `POST /api/auth/logout` | User clicks Logout |

The only deviation from the literal browser flow is the eval-login endpoint replacing the password+MFA pair. Everything from `POST /api/ai-chat/conversations` onwards goes through the SAME middleware stack, the SAME controller, the SAME everything that a real Sanctum-authenticated browser session does. **There is no longer any code path the eval invokes that a real user does not.**

---

## Section 4 — Run against the actual `peak_earners` preview user via a Sanctum token ability

### 4.1 The decision

The eval logs in as the **actual `peak_earners` preview user** — the same `User` row the persona selector logs into when CSJ tests via the landing page. No mirror user. No duplication. No `EvalUserSeeder`. The data the eval reasons against is the data already seeded by `PreviewUserSeeder`, exactly as `php artisan db:seed` puts it there.

The reason this works — even though preview users normally have writes blocked — is that the eval's Sanctum token carries a `bypass-preview-mode` ability, and the 3 server-side write-block checkpoints check the token's ability before applying the block.

### 4.2 The 3 write-block checkpoints

Counted from the live source. Every place a write decision branches on `is_preview_user`:

| # | File:line | What it does | After this rewrite |
|---|---|---|---|
| 1 | `app/Http/Middleware/PreviewWriteInterceptor.php:102` (`if (! $user || ! $user->is_preview_user)`) | HTTP-level write interceptor; returns fake-success for preview users on POST/PUT/PATCH/DELETE | Add: `if ($this->tokenHasBypass($request)) { return $next($request); }` directly after the existing exclusion checks. ~5 lines added. |
| 2 | `app/Traits/HasAiChat.php:144` (`$tools = $toolDefinitions->getTools($user->is_preview_user)`) | Strips write tools from the LLM tool catalogue for preview users | Change: `$tools = $toolDefinitions->getTools($user->is_preview_user && ! $this->currentTokenHasBypass())`. |
| 3 | `app/Agents/CoordinatingAgent.php:699` (`$isPreviewUser = (bool) $user->is_preview_user`) | Per-tool handlers receive this flag and short-circuit to fake-success | Change: `$isPreviewUser = (bool) $user->is_preview_user && ! $this->currentTokenHasBypass()`. The 24 tool handlers below it are unchanged — they read the flag the same way. |

Three small, explicit changes. Each is a one-line diff. **No tool handler logic changes** — they keep reading `$isPreviewUser`; the flag just resolves differently when the active token has the bypass ability. This means the eval and a real preview-user browser session diverge in exactly one place — the bypass check — and the divergence is per-token, deterministic, and auditable.

`tokenHasBypass()` and `currentTokenHasBypass()` both resolve to:

```php
$token = request()->user()?->currentAccessToken();
return $token instanceof PersonalAccessToken
    && $token->can('bypass-preview-mode');
```

(`PersonalAccessToken::can()` is the standard Sanctum ability check.)

### 4.3 The other 36 `is_preview_user` references — verified non-blocking

The remaining 36 places in the codebase that touch `is_preview_user` are:

- **Read-side flags only:** subscription bypass (`CheckSubscription:51` — preview users get a free pass; we keep this on for evals because real users with subscriptions don't get blocked anyway), audit log skip (`Auditable:59` — preview users skip audit; we keep this off for evals so audit chain assertions work), bug-report metadata (`BugReportMail:33`), guardrail rate-limit plan (`HasAiGuardrails`).
- **Frontend:** `v-preview-disabled` directive + `previewModeMixin` — only affect the browser UI, not the API. The eval doesn't drive the browser, so these don't apply.
- **Persona metadata:** `User` model `$fillable` / `$casts`, `PendingRegistration`, persona seeders. Static config, not runtime branches.

None of these block writes. None need toggling.

### 4.4 The eval-login endpoint — what it does

```php
// EvalAuthController::login
public function login(Request $request, string $personaId): JsonResponse
{
    if (app()->environment('production')) {
        return response()->json(['error' => 'eval login disabled in production'], 403);
    }

    if (! in_array($personaId, self::VALID_PERSONAS, true)) {
        return response()->json(['error' => 'invalid persona'], 400);
    }

    $user = User::where('is_preview_user', true)
        ->where('preview_persona_id', $personaId)
        ->first();

    if (! $user) {
        return response()->json([
            'error' => 'preview user not seeded',
            'hint' => 'php artisan db:seed --class=PreviewUserSeeder',
        ], 404);
    }

    // Ability-tagged token. Standard Sanctum — same code path real tokens use.
    $token = $user->createToken(
        name: 'eval-' . now()->timestamp,
        abilities: ['bypass-preview-mode']
    )->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'persona' => $personaId,
            'is_preview_user' => true,
            'token_abilities' => ['bypass-preview-mode'],
        ],
    ]);
}
```

The token is a real Sanctum token; it just carries one extra ability. Everything downstream — the consent gate, the chat send, the SSE — runs the same code as a real preview-mode browser session, with the only divergence being the 3 ability checks added above.

### 4.5 Restoration after mutating runs

`php artisan preview:reset peak_earners` already exists (`app/Console/Commands/ResetPreviewData.php`) and does exactly what we need: hard-deletes all child entities for the persona, re-runs `PreviewUserSeeder` for that persona only. Adopted as-is. **No `EvalPersonaResetService` needed** — the existing command is the service.

**[REVISED 2026-04-28]** Per canonical 0.1, the **CALLER** (`EvalRecordCommand`), not the driver, invokes `Artisan::call('preview:reset', ['persona' => $scenario['persona']])` **AFTER** the captured change + result are persisted to `eval_provider_runs`, and **ONLY** if the persisted `db_writes` diff is non-empty. Non-mutating scenarios never reset. The earlier wording — "between provider runs" and "after the whole session, always defensively" — violated the canonical and is deleted.

One small extension to `ResetPreviewData::deleteUserData`: it currently deletes 12 child-entity types. peak_earners writes to ~25. The missing 13 are: `protection_profiles`, `retirement_profiles`, `iht_profiles`, `expenditure_profiles`, `goals`, `life_events`, `lasting_powers_of_attorney`, `wills`, `trusts`, `gifts`, `chattels`, `business_interests`, `assets`, `family_members`, `ai_conversations`, `ai_messages`. Add a `tests/Feature/PreviewResetCompletenessTest` that asserts every child table the persona seeder writes to is in the reset's deletion list.

### 4.6 Concurrency: per-token, not global

This is the big win over the persona-mirror design and over a global toggle.

The bypass is **per-token**. If CSJ is browsing local dev as a normal preview user (no `bypass-preview-mode` ability on their token) WHILE an eval is running (its token DOES have the ability), the two sessions don't interfere. The interceptor checks each request's bearer token independently. CSJ's writes are still blocked; the eval's writes go through.

The cache-key concurrency hazard (`Cache::forever('ai_provider', X)` leaking to concurrent browser sessions) DOES still apply for provider selection — that's a separate global-state issue handled in §9.3. The write-block toggle has no global state.

---

## Section 5 — Engine + gate trace observability

### 5.1 Why this exists

CSJ: *"I want a trace of when Fyn the agent checks various gates (KYC gate) and engines such as the recommendation engine, how the waterfalls are applied etc."*

The captured SSE stream tells you what the LLM said and which tools fired. It does NOT tell you what the agent's gate logic decided server-side: whether the KYC gate passed for each module, whether the protection profile gate fired, whether `PrerequisiteGateService::canGetRecommendations` short-circuited because no module was ready, whether `orchestrateAnalysis` was called (and what it returned), whether the agent's secondary profile gate (e.g. ProtectionAgent line 72) intercepted before the analysis ran. Without that, "the eval passed" tells you nothing about WHY the response came out the way it did.

This section adds an in-process trace that captures those decision points, attaches them to the recording, and renders them as a timeline on the dashboard.

### 5.2 The 3 events

```php
// app/Events/Eval/GateChecked.php
final class GateChecked
{
    public function __construct(
        public readonly string $gate,           // 'kyc' | 'data_readiness' | 'profile_gate' | 'recommendation_eligibility'
        public readonly string $module,         // 'protection' | 'savings' | ... | 'global' (KYC universal stage)
        public readonly bool $passed,
        public readonly array $context,         // {missing: [...], reason: '...', user_id: int}
        public readonly float $atMicrotime,
    ) {}
}

// app/Events/Eval/EngineCalled.php
final class EngineCalled
{
    public function __construct(
        public readonly string $engine,         // 'orchestrate_analysis' | 'protection_recommendation' | 'savings_recommendation' | 'iht_calculator' | ...
        public readonly array $params,          // sanitised input
        public readonly array $resultSummary,   // {keys_returned: [...], result_path: 'happy'|'success_false'|..., row_counts: {protection: 3}}
        public readonly int $durationMs,
        public readonly float $atMicrotime,
    ) {}
}

// app/Events/Eval/AgentDecision.php
final class AgentDecision
{
    public function __construct(
        public readonly string $agent,          // 'ProtectionAgent' | 'CoordinatingAgent' | 'AdviceFyn'
        public readonly string $decisionPoint,  // 'classify_query' | 'profile_gate' | 'analyze_complete' | 'tool_dispatch' | 'response_mode' | 'engine_call_level'
        public readonly string $outcome,        // 'pass' | 'fail' | 'success_false' | 'happy' | 'recommendation' | 'factual' | etc.
        public readonly array $context,         // {primary: 'protection_cover', related: [...], user_id: int}
        public readonly float $atMicrotime,
    ) {}
}
```

### 5.3 Where each event fires (call sites)

The event classes are passive — they're only useful when fired from the gate/engine code. The plan adds `event(...)` calls at 11 call sites. None of them affect the live flow (events with no listener evaporate; cost ~1µs per fired event).

| Call site | Event | When |
|---|---|---|
| `KycGateChecker::check` (per universal field check) | `GateChecked('kyc', 'global', $passed, ['missing' => [...], 'field' => 'dob'])` | Each of the 5 universal field checks (DOB, marital, employment, income, expenditure) |
| `KycGateChecker::check` (per-module) | `GateChecked('kyc', $module, $passed, [...])` | Each of the per-module KYC checks (protection, savings, retirement, investment, estate) |
| `ProtectionDataReadinessService::assess` (and the 4 sibling services) | `GateChecked('data_readiness', $module, $can_proceed, ['blocking' => [...], 'warnings' => [...]])` | Once per call |
| `ProtectionAgent::analyze` line 72 + `RetirementAgent::analyze` line 101 | `GateChecked('profile_gate', $module, $exists, ['profile_table' => 'protection_profiles', 'user_id' => ...])` | Once per agent invocation |
| `PrerequisiteGateService::canGetRecommendations` | `GateChecked('recommendation_eligibility', 'global', $can_proceed, ['ready_modules' => [...], 'blocked_modules' => [...]])` | Once per `get_recommendations` tool call |
| `CoordinatingAgent::orchestrateAnalysis` (entry + exit) | `EngineCalled('orchestrate_analysis', [...], [...], $durationMs)` | When the holistic engine fires |
| `ProtectionAgent::analyze` (and 5 sibling agents) at the return statement | `EngineCalled("{$module}_analysis", [...], [...], $durationMs)` | When a module agent returns |
| Recommendation engines per module (e.g. `ProtectionRecommendationEngine::generate`) | `EngineCalled("{$module}_recommendation", [...], [...], $durationMs)` | When a module's recommendation engine fires |
| `QueryClassifier::classify` | `AgentDecision('CoordinatingAgent', 'classify_query', 'success', ['primary' => $primary, 'related' => $related, 'modules' => $modules])` | Once per chat send |
| `AdviceFyn::classifyResponseMode` + `engineCallLevel` | `AgentDecision('AdviceFyn', 'response_mode', $mode, [...])` and one for engine_call_level | Once per chat send |
| `CoordinatingAgent::executeTool` | `AgentDecision('CoordinatingAgent', 'tool_dispatch', $toolName, ['args' => [...], 'duration_ms' => ...])` | Once per tool call |

### 5.4 The collector + listener

`EvalTraceCollector` is a request-scoped singleton (registered in a service provider via `singleton()`). It accumulates events into an ordered list with relative-microtime offsets.

`EvalTraceListener` subscribes to all 3 events. On each fired event, the listener:

```php
public function handle(GateChecked|EngineCalled|AgentDecision $event): void
{
    $token = request()->user()?->currentAccessToken();
    if (! $token instanceof PersonalAccessToken) return;
    if (! $token->can('bypass-preview-mode')) return;

    app(EvalTraceCollector::class)->record($event);
}
```

**Listener early-returns when the active token doesn't have the `bypass-preview-mode` ability.** This means: zero overhead in normal user requests (the listener fires, immediately returns); full capture in eval requests. The same ability that gates write-bypass also gates trace capture — one ability, two effects, both safe.

### 5.5 Storage

The `EvalHttpDriver` reads the collector via the new `GET /api/eval/trace/{conversationId}` endpoint immediately after the SSE stream closes (the HTTP request that drove the chat send is finished by then; the conversation_id is the join key). The endpoint returns:

```json
{
  "conversation_id": 123,
  "events": [
    {"t_ms": 0, "event": "AgentDecision", "agent": "CoordinatingAgent", "decisionPoint": "classify_query", "outcome": "success", "context": {"primary": "protection_cover", ...}},
    {"t_ms": 12, "event": "AgentDecision", "agent": "AdviceFyn", "decisionPoint": "response_mode", "outcome": "recommendation"},
    {"t_ms": 14, "event": "GateChecked", "gate": "kyc", "module": "global", "passed": true, "context": {"field": "dob"}},
    ...
    {"t_ms": 1820, "event": "EngineCalled", "engine": "protection_analysis", "durationMs": 340, "resultSummary": {"keys_returned": [...], "result_path": "happy"}}
  ]
}
```

The `EvalHttpDriver` persists this list onto the `EvalProviderRun` row in a new `engine_trace JSON` column.

### 5.6 Assertions in scenario JSON

The scenario file gets new `expected_engine_trace` block:

```json
"expected_engine_trace": {
  "must_contain": [
    {"event": "AgentDecision", "decisionPoint": "classify_query", "context.primary": "protection_cover"},
    {"event": "GateChecked", "gate": "kyc", "module": "global", "passed": true},
    {"event": "GateChecked", "gate": "kyc", "module": "protection", "passed": true},
    {"event": "GateChecked", "gate": "data_readiness", "module": "protection", "passed": true},
    {"event": "GateChecked", "gate": "profile_gate", "module": "protection", "passed": true},
    {"event": "EngineCalled", "engine": "protection_analysis"}
  ],
  "must_not_contain": [
    {"event": "EngineCalled", "engine": "orchestrate_analysis"}
  ],
  "ordered": [
    "AgentDecision:classify_query",
    "AgentDecision:response_mode",
    "GateChecked:kyc:global",
    "GateChecked:kyc:protection",
    "GateChecked:data_readiness:protection",
    "GateChecked:profile_gate:protection",
    "EngineCalled:protection_analysis"
  ]
}
```

`must_contain`: every entry must match at least one event in the trace (object-shape match, like `expect.objectContaining`).
`must_not_contain`: every entry must NOT match any event in the trace.
`ordered`: the listed event-keys must appear in the trace in the listed order (other events may interleave between them).

### 5.7 Dashboard rendering

`resources/js/components/Admin/eval/RunPanel.vue` gets a new "Engine + gate timeline" panel showing the trace as a vertical timeline with:
- Time offset (in ms from request start)
- Event type (color-coded: GateChecked = horizon-500, EngineCalled = raspberry-500, AgentDecision = spring-500)
- One-line summary
- Expand-to-see-full-context affordance

Same panel pattern as the existing tool-call timeline. ~150 lines added.

### 5.8 Performance

The 11 call sites add `event(...)` calls. Laravel's event dispatcher with no listeners costs ~1µs per fired event. With the trace listener registered but the request token lacking the bypass ability, cost is one method call + one `if` + return — measured at ~3µs in similar Laravel apps. Total per-request overhead in normal operation: under 50µs across all 11 fire sites. Negligible.

In eval mode (listener actually capturing): ~60µs per event for the array push + microtime read. With ~30 events per scenario, that's <2ms per recording. Negligible.

---

## Section 6 — Auth flow

### 6.1 The eval-login endpoint

`POST /api/eval/login/{personaId}`. New route in `routes/api.php`. New controller `app/Http/Controllers/Api/EvalAuthController.php`.

```php
public function login(Request $request, string $personaId): JsonResponse
{
    if (! App::environment(['local', 'staging', 'testing'])) {
        return response()->json(['error' => 'eval login disabled in production'], 403);
    }

    if (! in_array($personaId, self::VALID_PERSONAS, true)) {
        return response()->json(['error' => 'invalid persona'], 400);
    }

    // [REVISED 2026-04-28] Per canonical 0.2 the eval logs in as the actual
    // seeded preview user — there is NO mirror user, NO `is_eval_user` flag,
    // NO `EvalUserSeeder`. The Sanctum token's `bypass-preview-mode` ability
    // is the mechanism that lets writes through.
    $user = User::where('is_preview_user', true)
        ->where('preview_persona_id', $personaId)
        ->first();

    if (! $user) {
        return response()->json([
            'error' => 'preview user not seeded',
            'hint' => 'php artisan db:seed --class=PreviewUserSeeder',
        ], 404);
    }

    $token = $user->createToken('eval-' . now()->timestamp)->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => ['id' => $user->id, 'persona' => $personaId, 'is_preview_user' => true],
    ]);
}
```

Gated by `App::environment` so production refuses outright. Returns a real Sanctum token. The token has the same TTL and behaviour as any other Sanctum token — it's not special.

### 6.2 Why not real login + MFA

The fully-real-flow alternative is `POST /api/auth/login` (email + password) → fetch verification code from DB → `POST /api/auth/verify-code` → token. The CLAUDE.md "Authentication for Testing" section already documents this for local dev browser tests, and it's what BS-NN scenarios use.

Reasons to NOT do this for the eval:

1. **MFA codes are emailed.** Local dev rolls them into the DB so we can read them. Staging emails them via SES. We'd need an environment-aware code-fetch layer.
2. **It adds three HTTP calls (login → fetch code → verify) per recording**, doubling auth latency from ~50ms to ~300ms. The eval already has variable provider latency to deal with; piling on 250ms of auth wait per run muddies timing budgets.
3. **The eval's purpose is to measure the AI behaviour from the chat send onwards.** The login flow is exercised separately by BS-01-style browser scenarios. Repeating it inside the eval doesn't add information.
4. **The eval-login endpoint IS itself a real HTTP-controller-middleware path.** It just skips the password+MFA stage. Everything downstream of it (`POST /api/ai-chat/conversations`, `POST /messages`) is byte-identical to a logged-in browser.

The trade is honest: we accept that the eval doesn't measure the login flow, and document that BS-NN browser scenarios are the regression net for login. The eval measures chat behaviour, which is what evals are for.

### 6.3 Production guard

The `App::environment(['local', 'staging', 'testing'])` check is the gate. Belt-and-braces: also add the eval routes to `routes/api.php` inside an `if (! app()->environment('production'))` block so they don't even register in production. **Both checks together, not one or the other.**

---

## Section 7 — Scenario JSON shape

### 7.1 Top-level structure

```json
{
  "id": "advice_protection_cover",
  "category": "01-query-types",
  "persona": "peak_earners",
  "is_mutating": false,
  "description": "User asks 'Am I covered enough?' against David Mitchell's full peak_earners data...",

  "input": {
    "turns": [
      {
        "user": "Am I covered enough for protection?",
        "current_route": "/protection"
      }
    ]
  },

  "expected_classification_shape": {
    "primary": "protection_cover",
    "related": [],
    "modules": ["protection"]
  },

  "expected_response_mode": "recommendation",
  "expected_engine_call_level": "module",
  "expected_kyc_state": "passed",

  "expected_tool_calls": [
    {
      "tool": "list_records",
      "args": {"entity_type": "life_insurance"},
      "required": true,
      "result_path": "happy"
    },
    {
      "tool": "get_module_analysis",
      "args": {"module": "protection"},
      "required": true,
      "result_path": "happy"
    },
    {
      "tool": "get_recommendations",
      "required": false,
      "condition": "fires when canGetRecommendations returns can_proceed=true"
    }
  ],

  "expected_tool_calls_absent": [
    "create_protection_policy",
    "delete_protection_policy",
    "delegate_to_capture"
  ],

  "expected_sse_events": {
    "must_contain_types": ["title", "content", "tool_use", "done"],
    "must_emit_exactly_once": ["done", "title"],
    "must_not_emit": ["persona_state_change", "handoff", "consent_required", "error"],
    "content_event_minimum": 5,
    "tool_use_count_min": 1,
    "tool_use_count_max": 8
  },

  "expected_assistant_text": {
    "must_contain_substrings": [
      "For regulated advice personal to your circumstances, speak to a qualified financial adviser."
    ],
    "must_not_contain_substrings": [
      "I think you should",
      "I'd recommend",
      "In my opinion"
    ],
    "minimum_length_chars": 200,
    "maximum_length_chars": 2500
  },

  "expected_db_writes": {
    "expected_count": 0,
    "expected_no_writes_to": ["life_insurance_policies", "users"]
  },

  "expected_http_log": {
    "calls": 4,
    "must_have_status_200": ["login", "create_conversation", "send_message", "logout"]
  },

  "timing_budget_ms": {
    "anthropic": {"happy": 7000, "success_false": 6000},
    "xai": {"happy": 16000, "success_false": 14000}
  },

  "tags": ["regression-band-0", "recommendation-mode", "protection", "peak_earners"]
}
```

### 7.2 What's NEW vs the YAML format

| New field | Why |
|---|---|
| `persona` | [REVISED 2026-04-28 per canonical 0.2] Specifies which seeded preview user the eval logs in as (the actual `peak_earners` row, NOT a mirror). Validated against the 6 valid personas. |
| `is_mutating` | [REVISED 2026-04-28 per canonical 0.1] Documentary marker. The reset decision in `EvalRecordCommand` is gated on the persisted `db_writes` diff being non-empty, NOT on this flag. Non-mutating scenarios (`is_mutating: false`, `expected_db_writes.expected_count: 0`) never reset, regardless of the flag. |
| `input.turns[*].current_route` | Currently always `null` in eval (line 296 of EvalRecordCommand). Now passable per turn. |
| `expected_db_writes` (object instead of bool) | Asserts a specific count of writes and which tables MUST and MUST NOT be touched. The session-102 YAML had no per-table writes assertion — only `expected_db_writes_persistent: true|false`. |
| `expected_http_log` | Asserts every HTTP call returned the expected status. Catches any controller-level failure (consent gate denies, validation fails, conversation not found) that would otherwise look like a stream-level failure. |

### 7.3 What's REMOVED vs the YAML format

| Removed field | Why |
|---|---|
| `seed:` block (top-level) | Replaced by `persona:` reference. Synthetic seed data is gone. |
| `expected_advice_response` (already removed in session 101) | Stays removed. |
| `forbidden_outputs` (top-level) | Folded into `expected_assistant_text.must_not_contain_substrings`. |
| `forbidden_tools` (top-level) | Folded into `expected_tool_calls_absent`. |

### 7.4 What's UNCHANGED from session 102

`expected_classification_shape`, `expected_response_mode`, `expected_engine_call_level`, `expected_kyc_state`, `expected_tool_calls[*].result_path`, `expected_tool_calls[*].required`, `expected_assistant_text` (rebadged from `forbidden_outputs`), `timing_budget_ms` (per-provider per-path), `tags`. All assertion-shape work from session 102 is reused.

### 7.5 Why JSON not YAML

Three reasons:

1. **JSON is what the API speaks.** The scenario file is fundamentally a request/response specification. Aligning the scenario format with the wire format reduces translation noise.
2. **Schema validation is trivial.** `tests/Architecture/EvalScenarioJsonSchemaTest.php` can validate every scenario against a single JSON Schema document. YAML schema validation (`yaml-language-server`-style) exists but is more fragile.
3. **No more "is this a string or a number" YAML ambiguity.** Every test result path so far has had to deal with YAML interpreting `5000` as an int and `5000ms` as a string and `'5000'` as a string. Not in JSON.

### 7.6 Schema validation

`tests/Feature/Fyn/Eval/scenarios/_schema.json` is the JSON Schema document. Every scenario validates against it via:

```php
arch('every eval scenario validates against the schema')
    ->expect(...)->...
```

(or a Pest unit test if `arch()` doesn't fit). Failing schema validation rejects the scenario at architecture-test time, not at recording time.

---

## Section 8 — Restore strategy

### 8.1 Three layers of restoration

**[REVISED 2026-04-28 per canonical 0.1.]** Reset is a CALLER-side operation that runs AFTER capture, AND ONLY when the persisted `db_writes` diff is non-empty. Non-mutating scenarios (all 10 current mitchell scenarios, all advice/factual queries) NEVER reset.

1. **Post-recordOne reset.** After `EvalProviderRun::create()` persists the captured run, the caller (`EvalRecordCommand::recordOne`) inspects the persisted `db_writes_made`. If non-empty (mutating scenario actually wrote rows), it calls `Artisan::call('preview:reset', ['persona' => $scenario['persona']])`. This restores the persona to its pre-eval state for the next provider run. For non-mutating scenarios this entire layer is skipped — no diff inspection, no reset call, no churn.

2. **Post-session reset is REDUNDANT and is removed.** Layer 1 already handles "reset between provider runs only on writes" AND "reset after the last provider run on writes". A separate per-session reset would either duplicate layer 1 or run unconditionally — both wrong under canonical 0.1.

3. **Manual reset via the new endpoint** `POST /api/eval/reset/{personaId}`. Operator-driven, for the dashboard / dev usage. Same gating as eval-login (non-production only). Calls the same `Artisan::call('preview:reset', ...)` underneath. Outside the eval recording loop entirely.

### 8.2 The reset path (`preview:reset`)

The existing `php artisan preview:reset peak_earners` command (`app/Console/Commands/ResetPreviewData.php`) does exactly what we need:

```php
DB::transaction(function () use ($user, $spouse) {
    $this->deleteUserData($user);
    if ($spouse) {
        $this->deleteUserData($spouse);
        $spouse->tokens()->delete();
        $spouse->delete();
    }
    $user->spouse_id = null;
    $user->save();
    $user->tokens()->delete();
    $user->delete();
});
$this->call('db:seed', ['--class' => 'PreviewUserSeeder']);
```

Adopted as-is. The eval driver invokes it via `Artisan::call('preview:reset', ['persona' => $scenario->persona])`.

The one extension needed: `ResetPreviewData::deleteUserData` currently deletes 12 child entity types. peak_earners writes to ~25. The missing 13 are: `protection_profiles`, `retirement_profiles`, `iht_profiles`, `expenditure_profiles`, `goals`, `life_events`, `lasting_powers_of_attorney`, `wills`, `trusts`, `gifts`, `chattels`, `business_interests`, `assets`, `family_members`, `ai_conversations`, `ai_messages`. Add these to `deleteUserData` plus a Pest meta-test (`tests/Feature/PreviewResetCompletenessTest`) that asserts every table the persona seeder writes to is in the deletion list — locks against drift.

### 8.3 Why not snapshot/restore (the current per-table pattern)

The current `EvalRecordCommand::snapshotState` / `restoreToSnapshot` pattern works for 7 tables (`SNAPSHOT_TABLES` constant). peak_earners writes to ~25 tables. Extending the snapshot list to cover them all is doable but adds a maintenance burden every time a new entity type lands. The reset-from-template pattern delegates that to the seeder, which has to know the entity list anyway.

Trade: ~2-5 sec slower per mutating session (vs ~200ms for snapshot diff). Acceptable for a tool that's running interactively and recording fixtures.

### 8.4 Idempotent reset

`preview:reset peak_earners` is idempotent — running it twice in a row is the same as running it once. This is a safety net: any code path that's uncertain whether a reset is needed can call it without worrying about corrupting state. **[REVISED 2026-04-28]** The earlier wording — "the eval driver always reset-runs at the start of a session AND at the end, defensively" — directly violated canonical 0.1 and is deleted. Idempotency exists as a defensive property of the command itself; the eval flow does NOT exercise that property by reset-running on non-mutating scenarios.

---

## Section 9 — Provider selection

### 9.1 The current pattern

`AdviceFyn::handle` at line 432:
```php
$provider = Cache::get('ai_provider', config('services.ai_provider', 'anthropic'));
```

A global cache key. Real users don't set this — it's set by the system (admin override, config default, etc.).

### 9.2 The eval pattern

Same key. The eval driver sets it BEFORE the HTTP call (so the controller picks it up via `Auth::user()` → conversation → agent), and restores it AFTER:

```php
$previousProvider = Cache::get('ai_provider');
Cache::forever('ai_provider', $provider);
config(["services.{$provider}.chat_model" => $model]);

try {
    // run the HTTP loop
} finally {
    if ($previousProvider === null) {
        Cache::forget('ai_provider');
    } else {
        Cache::forever('ai_provider', $previousProvider);
    }
    config(["services.{$provider}.chat_model" => $previousModel]);
}
```

This is the SAME pattern `EvalRecordCommand` uses today (lines 240-244). The difference is:

- **Today:** the cache key is set right before `$this->adviceFyn->handle()` is called direct. The key affects only the eval's CLI process because nothing else is happening. But it also leaks into the cache store and persists if the command crashes.
- **Plan:** the cache key is set right before the HTTP loop opens. The key affects the local Laravel server's view of provider for the duration of the eval. Because the local dev server is single-tenant during eval (CSJ runs evals while logged out / not browsing), no real user is affected. The `finally` block restores it.

### 9.3 Concurrency hazard — flagged

If a real user is browsing fynla.org-equivalent local dev WHILE an eval runs, the cache key flip would briefly affect them. **Mitigation:** the eval-login endpoint emits a warning log if any other Sanctum tokens have been used in the last 60 seconds. Not a hard block — just a flag for CSJ to know.

### 9.4 Why not a per-request header

Tempting alternative: `X-Eval-Provider: anthropic` header on the eval's chat-send call, decoded by the agent. Rejected because:

1. It introduces a code path real users don't exercise — the agent now reads a header it never reads otherwise. That's the exact kind of divergence this whole plan is trying to eliminate.
2. The cache key IS what real users use (when set by admin). Having the eval set it the same way is more representative.

---

## Section 10 — First 10 scenarios mapped to peak_earners

### 10.1 Why peak_earners

The brief says first 10 scenarios use Mitchell. peak_earners maps to David & Sarah Mitchell, fully built out across every module. The data shape (per `PreviewUserSeeder` + `peak_earners.json` persona template):

- **User**: David Mitchell, born ~1979, married, employed, ~£120k income, full universal KYC seeded
- **Spouse**: Sarah Mitchell, born ~1981, employed, ~£75k income
- **Family**: 2 dependent children (James, Elizabeth) + sibling beneficiaries
- **Properties**: main residence + buy-to-let, with mortgages
- **Savings**: cash ISA, multiple savings accounts
- **Investments**: GIA + Stocks & Shares ISA, with multiple holdings
- **Pensions**: DC workplace pension + SIPP for both David and Sarah
- **Protection**: life policies + critical illness for both
- **Estate**: full LPAs (PF + HW for both), full mirror wills, gifts, trusts
- **Goals**: education funding, retirement, mortgage payoff
- **Profiles**: protection_profile, retirement_profile, iht_profile, expenditure_profile all populated

This means EVERY module's readiness gate passes, EVERY agent's secondary profile gate passes, EVERY tool returns a populated `happy` payload. The first 10 scenarios are all `result_path: happy`. No `success_false`, no `readiness_blocked`, no `empty_state` — those are tested later with personas designed to trigger them (`young_saver` for empty savings, etc.).

### 10.2 The 10 scenarios

| # | Scenario id | Question | Expected response_mode | Expected engine_call_level | Expected primary classification |
|---|---|---|---|---|---|
| 1 | `mitchell_advice_protection_cover` | "Am I covered enough for protection?" | recommendation | module | protection_cover (scope: life + critical illness + income protection — see §10.3) |
| 2 | `mitchell_advice_savings_emergency` | "Do we have enough emergency savings?" | recommendation | module | savings_emergency |
| 3 | `mitchell_advice_investment_isa` | "Should I use ISA vs GIA for my investments?" | recommendation | module | investment_tax (related: tax_optimisation) [REVISED 2026-04-28 — live classifier emits `investment_tax` for the ISA-vs-GIA framing; the original `investment_portfolio` mapping never fired for this message and was stale] |
| 4 | `mitchell_advice_retirement_contribution` | "Are we contributing enough to our pensions?" | recommendation | module | retirement_contribution |
| 5 | `mitchell_advice_estate_iht` | "What's our IHT liability?" | recommendation | module | estate_iht |
| 6 | `mitchell_advice_holistic_health` | "How are we doing financially overall?" | recommendation | holistic | holistic_health |
| 7 | `mitchell_advice_tax_optimisation` | "How can we optimise our tax position?" | recommendation | module | tax_optimisation |
| 8 | `mitchell_advice_goals_affordability` | "Are we on track for our savings goals?" | recommendation | module | goals_progress (related: affordability) [REVISED 2026-04-28 — `affordability` is not a top-level primary in `QuerySchemas`; the live classifier resolves it to `goals_progress` with `affordability` as a related type] |
| 9 | `mitchell_factual_net_worth` | "What is my net worth?" | factual | factual | general [REVISED 2026-04-28 — `net_worth` is not a primary in `QuerySchemas`; the live classifier falls through to `general` (no advice keyword fires), which is the correct factual-mode behaviour] |
| 10 | `mitchell_factual_income` | "What's our combined household income?" | factual | factual | income |

Scenarios 1-8 exercise advice mode with full data. Scenarios 9-10 exercise factual mode (no engine call, no signposting).

**[REVISED 2026-04-28 per canonical 0.2.]** The 4 multi-entity scenarios from `03-multi-entity/` are onboarding-mode capture scenarios and need an in-flight onboarding state. They are bound to the actual `peak_earners` preview user (NOT a mirror) and authored as MUTATING scenarios — at the start of the recording the bypass-write capability flips the persona's `onboarding_completed` flag to `false` and sets `onboarding_fyn_step: asset_capture`; the canonical 0.1 reset orchestration in the caller restores the flag (and any captured rows) afterwards. No `peak_earners_in_onboarding` mirror, no clone, no separate row. See §13.2 decision 4.

### 10.3 Protection scope correction — "Am I covered enough?" must surface ALL protection types

The session-102 YAML for `advice_protection_cover` only required `list_records(life_insurance)`. CSJ's correction (2026-04-27 session 104): protection covers **life insurance AND critical illness AND income protection**. A user asking "am I covered enough?" expects all three types analysed — they don't differentiate by table. The current `QuerySchemas::REQUIRED_TOOLS[PROTECTION_COVER]` list is too narrow.

**Two changes flow from this:**

1. **In the JSON scenario**, `expected_tool_calls` for `mitchell_advice_protection_cover` lists THREE `list_records` calls as `required: true` — `life_insurance`, `critical_illness`, `income_protection`. The LLM is expected to call all three; missing any of the three is a fail.

2. **In `app/Constants/QuerySchemas.php` `REQUIRED_TOOLS[PROTECTION_COVER]`**, expand the tool list from `[get_module_analysis(protection), list_records(life_insurance)]` to `[get_module_analysis(protection), list_records(life_insurance), list_records(critical_illness), list_records(income_protection)]`. This is a one-line change in the constant. It makes the live system prompt (built from `QuerySchemas::getRequiredToolsForClassification`) instruct the LLM to fetch all three. **The eval and live behaviour both update; the system gets MORE complete responses for protection queries, not just better evals.** This is a correction to the live product, not just to the eval.

The same scrutiny should be applied to the other 9 scenarios as they're authored — for each, ask "is the REQUIRED_TOOLS list scoped to ONLY what the user expects, or is it artificially narrow?" Any narrowing surfaces during JSON authoring (per §10.4 below) and corrects QuerySchemas at the same time.

### 10.4 Per-scenario JSON authoring

Each scenario's JSON is authored by:

1. Run the question against David Mitchell live (`http://localhost:8000` while logged in as peak_earners).
2. Read the captured prompt + tool calls + response from `ai_messages` table.
3. Verify the classification with `php artisan tinker --execute="dump(app(QueryClassifier::class)->classify('...'));"`.
4. Verify the KYC state with `KycGateChecker::check`.
5. Identify the `result_path` from the captured `tool_result`s.
6. Author the JSON: copy the verified classification, paste the verified path, paste a tight `must_contain_substrings` list, set timing budgets at +30% of the live timing.
7. Re-record via the eval against both providers; calibrate timing if needed.

This is the same pattern session 102 used for `advice_protection_cover` against the old synthetic seed. The same pattern works for the new persona-bound scenarios.

---

## Section 11 — Migration / branch impact

### 11.1 What ships in this rewrite

A single set of commits on `feature/fyn-persona-split` (or a sub-branch off it). Acceptance is: the 10 new JSON scenarios run against peak_earners via the HTTP loop, both providers, fixtures captured, dashboard renders.

### 11.2 What from session 102 survives

- `app/Services/Eval/EvalDeltaBuilder.php` — UNCHANGED logic, 5-line YAML→JSON swap.
- `tests/Feature/Fyn/Eval/AssertionHelpers.php` — UNCHANGED 9 helpers; +3 new HTTP helpers.
- `tests/Unit/Services/Eval/EvalDeltaBuilderTest.php` — 20 tests; updated fixtures from YAML to JSON; same logic.
- The session-102 YAML structural rewrites — the FIELDS (`expected_classification_shape`, `result_path`, `expected_kyc_state`, etc.) carry over verbatim into the JSON. Only the YAML-encoded `seed:` blocks die.
- Vue dashboard fields restored in commit `89611d4` — survive unchanged.
- `EvalRecordingSession` + `EvalProviderRun` schema — unchanged except for 2 new columns (`persona`, `http_log`).

### 11.3 What from session 102 dies

- `EvalRecordCommand::seedUser` — DELETED.
- `EvalRecordCommand::seedChildEntities` — DELETED.
- `EvalRecordCommand::seedProtectionPolicies` / `seedRows` / `seedExpenditure` — DELETED.
- `EvalRecordCommand::createConversation` — DELETED (conversation creation moves to the HTTP loop).
- `EvalRecordCommand::recordOne` — REWRITTEN to call `EvalHttpDriver`.
- The 6 advice YAML files at `tests/Feature/Fyn/Eval/scenarios/01-query-types/*.yaml` — DELETED, replaced by 10 JSON files all bound to peak_earners (per §10.2).
- The 4 multi-entity YAML files at `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/*.yaml` — STAY AS YAML for now. They're out of scope for this rewrite. Their JSON conversion + persona binding (likely to a `peak_earners_in_onboarding` mirror per §13.2 decision 4) lands in a subsequent piece of work, not this one.
- The `seed:` block in the 6 deleted advice YAMLs — DELETED with them. The 4 multi-entity YAMLs keep their `seed:` blocks until their JSON conversion happens.

### 11.4 Sprint 1 plan impact

Per `April/April24Updates/plan/11-sprint-1-plan.md`:

- **S1.2.l** ("rewrite 10 YAMLs against running contract") — DONE in session 102 BUT the seed-bearing YAMLs go away. The rewrite work survives as the assertion-shape template; the YAMLs themselves are scrapped.
- **S1.2.k** ("re-record 9 fixtures") — UNBLOCKED but RE-SCOPED. **[REVISED 2026-04-28 per canonical 0.2.]** The 9 fixtures are now re-recorded against the actual seeded `peak_earners` preview user via the HTTP loop, not against synthetic seeds via direct calls and not against any mirror user.
- **S1.7.a-S1.7.j** (asserter / meta-tests / handoff / state-machine / resume scenarios) — STILL VALID. The new YAML keys session 102 was extending AssertionHelpers for (`expected_per_turn`, `expected_state_transition`, `expected_parked_facts`, `expected_handoff_path`, `expected_db_writes`, `inherits`, `linked_browser_scenario`) carry over to JSON unchanged. The 48 new scenarios from rewrite-report §6 + §10 + §11 + §12 still need to be authored, just in JSON not YAML.
- **S1.7.d Path A** (`EvalDeltaBuilder` wired) — DONE; survives the YAML→JSON swap.

The `eval-expectations-rewrite.md` document (1690 lines, 14 sections) is NOT discarded. It becomes the **assertion-shape spec** for the JSON scenarios. Section 4 (per-scenario rewrite) gets re-keyed: "the assertion shape is the same; the seed is replaced by `persona: peak_earners`; the `result_path` for most scenarios flips from `success_false` / `readiness_blocked` to `happy` because peak_earners has full data".

### 11.5 New documents created

1. `April/April27Updates/eval-http-driven-rewrite-plan.md` — THIS FILE.
2. `tests/Feature/Fyn/Eval/scenarios/_schema.json` — JSON Schema for scenarios (created during S2).
3. ~~`database/seeders/data/eval/<persona_id>.json` — IF the `EvalUserSeeder` decides to externalise persona templates rather than copy them inline from `PreviewUserSeeder`. Decision deferred to S2.~~ **[DELETED 2026-04-28 per canonical 0.2.]** There is no `EvalUserSeeder` and there never will be. The eval uses the actual seeded `peak_earners` preview user directly.

### 11.6 What from session 102 needs an explicit RIP

1. The 10 YAML files at `tests/Feature/Fyn/Eval/scenarios/01-query-types/*.yaml` and `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/*.yaml`. **Deleted, replaced by JSON.**
2. `EvalRecordCommand`'s `seedUser`, `seedChildEntities`, `seedProtectionPolicies`, `seedRows`, `seedExpenditure`, `createConversation` methods. **Deleted.**
3. The `SNAPSHOT_TABLES` constant in `EvalRecordCommand`. **Deleted** (snapshot/restore moves to `EvalPersonaResetService`).
4. The `Cache::forever('ai_provider', ...)` block in `EvalRecordCommand::recordOne` lines 240-244. **Moved** to `EvalHttpDriver::run`.

Everything explicitly listed above is the migration surface. Anything not listed is either unchanged or out of scope.

---

## Section 12 — Implementation order with acceptance gates

Each task is self-contained. Each has a single acceptance test. No task depends on a future task except where listed.

| # | Task | Acceptance | Blocks |
|---|---|---|---|
| **12.1** | Add `persona` + `http_log` + `engine_trace` columns to `eval_recording_sessions` and `eval_provider_runs`. (Note: `is_eval_user` migration is NOT needed in this design — we use the existing `peak_earners` preview user directly.) | `php artisan migrate` clean; columns visible. | All other tasks |
| **12.2** | Extend `app/Console/Commands/ResetPreviewData.php::deleteUserData` to cover the 13 missing child-entity types per §8.2. Add `tests/Feature/PreviewResetCompletenessTest`. | Test green; `php artisan preview:reset peak_earners` reaches every persona-touched table. | 12.7 |
| **12.3** | Create the 3 Eval events: `app/Events/Eval/GateChecked.php`, `EngineCalled.php`, `AgentDecision.php`. Pure value objects. | Pest unit test `EvalEventsTest` constructs each with sample data; events serialise/deserialise cleanly. | 12.5 |
| **12.4** | Add `bypass-preview-mode` ability check to the 3 write-block sites per §4.2: `PreviewWriteInterceptor` (~5 lines), `HasAiChat:144` (1 line), `CoordinatingAgent:699` (1 line). Add `tests/Feature/PreviewBypassAbilityTest` covering: preview user without ability → blocked; preview user WITH ability → write goes through; non-preview user → unaffected. | Test green. Existing preview-user functional tests still green (no regression on the default block). | 12.6, 12.7 |
| **12.5** | Create `app/Services/Eval/EvalTraceCollector.php` (request-scoped singleton) + `app/Listeners/Eval/EvalTraceListener.php` (subscribes to the 3 events; early-returns when token lacks bypass ability). Register in a service provider. | Pest test fires each event with + without bypass-ability token; collector captures only when ability present. Zero overhead measured for non-eval requests. | 12.8 |
| **12.6** | Add `event(...)` calls at the 11 trace call sites listed in §5.3 — KycGateChecker (per-field + per-module), DataReadinessService and 4 siblings, Protection/Retirement secondary profile gates, PrerequisiteGateService::canGetRecommendations, CoordinatingAgent::orchestrateAnalysis, the 6 module Agents' `analyze()` returns, recommendation engines per module, QueryClassifier::classify, AdviceFyn::classifyResponseMode + engineCallLevel, CoordinatingAgent::executeTool. | Pest integration test: send a chat message via `EvalHttpDriver` → trace contains entries from all 11 source classes. | 12.8 |
| **12.7** | Create `app/Http/Controllers/Api/EvalAuthController.php` with 3 endpoints (`POST /eval/login/{persona}`, `POST /eval/reset/{persona}`, `GET /eval/trace/{conversationId}`) + add routes to `routes/api.php` inside `if (! app()->environment('production'))` block. The login endpoint issues a Sanctum token with `bypass-preview-mode` ability for the matching preview user. | Pest feature test `EvalAuthControllerTest` — 200 with ability-tagged token in dev; 403 in prod (mock environment); 404 for unseeded persona. The token's abilities include exactly `bypass-preview-mode`. | 12.8 |
| **12.8** | Create `app/Services/Eval/EvalSseConsumer.php`. Reads SSE response body byte-by-byte, parses `data: ...\n\n` frames, returns event list. | Pest test with a captured SSE fixture — assert events parsed in order; assert partial frames buffered correctly. | 12.9 |
| **12.9** | Create `app/Services/Eval/EvalHttpDriver.php`. Logs in, creates conversation, sends message, consumes SSE, fetches trace via `/eval/trace`, returns events + http_log + start/end snapshots + engine trace. | Pest feature test `EvalHttpDriverTest` running against the live local dev server (uses `Http::fake` is INSUFFICIENT — must hit the real local server, gated to `local` env). One scenario captured: peak_earners → "What's our net worth?" → factual mode → ~5 events → trace populated → assertion-shape valid. | 12.10, 12.11 |
| **12.10** | Update `app/Console/Commands/EvalRecordCommand.php`. Delete `seedUser`, `seedChildEntities`, `seedProtectionPolicies`, `seedRows`, `seedExpenditure`, `createConversation`, `SNAPSHOT_TABLES`. Wire to `EvalHttpDriver`. Move the `Cache::forever('ai_provider', X)` block into `EvalHttpDriver`. | `php artisan eval:record mitchell_advice_protection_cover` succeeds end-to-end against a running local server, both providers. Fixture written. Session row in `eval_recording_sessions` with `persona='peak_earners'`, populated `http_log`, populated `engine_trace`. | 12.11, 12.12 |
| **12.11** | Update `QuerySchemas::REQUIRED_TOOLS[PROTECTION_COVER]` per §10.3 — add `list_records(critical_illness)` and `list_records(income_protection)`. One-line constant change + the resulting prompt-builder change. | Pest unit test `QuerySchemasTest::it_protection_cover_requires_all_three_protection_types` green. Live "Am I covered enough?" responses now address all 3 protection types. | 12.12 |
| **12.12** | Author 10 NEW JSON scenarios per §10.2 (all bound to `peak_earners`). Each scenario includes `expected_engine_trace` with `must_contain` / `must_not_contain` / `ordered` lists. Delete the 6 advice YAMLs at `01-query-types/*.yaml`. The 4 multi-entity YAMLs at `03-multi-entity/*.yaml` are NOT touched. | All 10 JSON scenarios validate against `_schema.json`. `php artisan eval:record mitchell_advice_*` runs each end-to-end on both providers; engine trace asserted. | 12.13, 12.14 |
| **12.13** | Wire `EvalDeltaBuilder` to JSON. 5-line `Yaml::parse` → `json_decode` swap in 2 sites. Add a `gradeEngineTrace` method that grades the captured trace against `expected_engine_trace`. | All 20 unit tests in `EvalDeltaBuilderTest` pass against JSON-shaped fixtures + 6 new tests for engine-trace grading. | 12.14 |
| **12.14** | Re-record all 10 scenarios. Calibrate timing budgets. Dashboard renders the new fields (persona, http_log, engine_trace timeline). `RunPanel.vue` gets the engine/gate timeline panel per §5.7. | All 10 sessions show `status='completed'`, both providers `passed`. Admin dashboard `EvalRecordings.vue` shows persona + HTTP log; `RunPanel.vue` shows trace timeline. | None (this is the goal) |
| **12.15** | New Pest meta-tests under `tests/Architecture/`: `EvalScenarioJsonSchemaTest`, `EvalScenarioPersonaIsValidTest`, `EvalScenarioMutatingFlagMatchesWritesTest`, `EvalScenarioEngineTraceConsistencyTest` (asserts every `expected_engine_trace.must_contain[*].engine` is one we actually fire from). | All meta-tests green against the 10 scenarios. | None (regression net) |

### 12.16 Acceptance gate (the single hard test)

The whole rewrite is GREEN if and only if:

1. `php artisan db:seed --class=PreviewUserSeeder` produces a `peak_earners` user with full data (no new seeder needed).
2. `php artisan eval:record mitchell_advice_protection_cover` runs end-to-end via the HTTP loop. Session row in `eval_recording_sessions` with `persona='peak_earners'`, `http_log` populated, `engine_trace` populated, `status='completed'`. Both providers' runs in `eval_provider_runs` with `assistant_text` non-empty, `tool_calls` containing `list_records(life_insurance)` AND `list_records(critical_illness)` AND `list_records(income_protection)` AND `get_module_analysis(protection)`, `db_writes_made` empty (it's a non-mutating advice scenario).
3. The captured `tool_calls[*].result` for each of the three `list_records` calls is a non-empty array (the LLM SAW David Mitchell's actual policies in all three categories).
4. The captured `tool_calls.get_module_analysis(protection)` returns a `happy`-path payload (NOT `success_false`) — peak_earners has a populated `protection_profile`.
5. The assistant text contains the FCA signposting string AND references something specific from the data (e.g. "your £400k Aviva life policy and your £150k critical illness cover" — the LLM read real data across all three protection types and used it).
6. The captured `engine_trace` contains: `AgentDecision:classify_query` (primary `protection_cover`), `AgentDecision:response_mode` (recommendation), `GateChecked:kyc:global` (passed), `GateChecked:kyc:protection` (passed), `GateChecked:data_readiness:protection` (passed), `GateChecked:profile_gate:protection` (passed), `EngineCalled:protection_analysis` (happy path) — in order, with no `EngineCalled:orchestrate_analysis` (this is module-scoped, not holistic).
7. `EvalDeltaBuilder` grades both runs as PASS against the JSON expectations including engine-trace assertions.

Anything short of that is a fail. The eval is "working" only when it's measuring what it's supposed to measure.

---

## Section 13 — Risks + open decisions

### 13.1 Risks (known)

1. **`bypass-preview-mode` token leaks outside the eval flow.** If an eval token gets pasted into a curl session or tested in a browser, writes go through to the real `peak_earners` preview user. **Mitigation 1:** short token TTL — set `EVAL_TOKEN_TTL_MINUTES=15` env var with that default; tokens auto-expire 15 min after issue. **Mitigation 2:** all eval tokens are tagged `name='eval-{timestamp}'` for visibility — easy to find and revoke. **Mitigation 3:** the `bypass-preview-mode` ability is checked at the 3 sites only; nothing else honours it.

2. **A future write-block site forgets to check the ability.** If a new piece of preview-block logic gets added without checking the ability, eval writes start being silently swallowed at that site, the recording looks "passing" but nothing wrote. **Mitigation:** Pest meta-test `tests/Architecture/PreviewBlockSitesCheckBypassTest` greps the codebase for `is_preview_user` reads in write-blocking contexts and asserts each one also checks `currentAccessToken()->can('bypass-preview-mode')`. Catches the regression at architecture-test time, not at eval-recording time.

3. **`peak_earners` preview user gets corrupted mid-session.** If a MUTATING eval crashes mid-write, the preview user is left with partially-written eval data. The next time CSJ logs in as peak_earners through the persona selector, they see that partial state. **[REVISED 2026-04-28 per canonical 0.1.]** Mitigation is NOT "always call `preview:reset` in finally" — that would violate canonical 0.1 by wiping non-mutating runs. Mitigation: (a) for mutating scenarios, the caller (`EvalRecordCommand::recordOne`) wraps the `EvalHttpDriver::run + EvalProviderRun::create + Artisan::call('preview:reset', …)` sequence in a try/catch — on exception it persists a partial run row with the captured diff and STILL runs the reset for that scenario; (b) `EvalAuthController::login` opportunistically detects prior-crash leftover state by checking `ai_conversations` for rows tagged with eval-source metadata and surfaces a warning, but does NOT reset on its own — operator decides via the manual reset endpoint. Non-mutating scenarios cannot corrupt the persona because they cannot write.

4. **Local dev server must be running for the eval to work.** The HTTP loop hits `http://localhost:8000` (the URL `./dev.sh` exposes; configurable via a new `EVAL_HTTP_BASE_URL` env var with that default). If the server is down, the eval fails opaquely. **Mitigation:** `EvalHttpDriver::run` does a pre-flight health check (`GET /api/health` or `GET /api/preview/personas` if no health endpoint exists yet) and fails loudly if the server isn't up.

5. **Sanctum tokens accumulate in the DB.** Each eval run creates a new token and the cleanup logout-call may fail. **Mitigation:** add a token-cleanup step that deletes all eval-tagged tokens older than 1 hour in the `EvalHttpDriver` setup, AND mark eval tokens with `name='eval-{timestamp}'` so they're identifiable.

6. **Provider cache key leaks across concurrent requests.** If CSJ browses fynla on local while an eval is running, the global `ai_provider` cache key gets flipped under them. **Mitigation:** the eval prints a "DO NOT BROWSE LOCAL DEV WHILE EVALS ARE RUNNING" warning at start. Belt: log a warning if any non-eval Sanctum token has been used in the last 60 seconds before kicking off.

7. **The 2-5 sec per-session reset eats wall time.** 10 scenarios × 2 providers × 2 resets = 40 resets × ~3 sec = ~2 minutes total. Tolerable. **Mitigation:** parallelise reset and provider run for non-overlapping work? No — too much complexity. Live with it.

8. **HTTP timeout might be too tight or too loose.** SSE streams can run up to 30 seconds for xAI scenarios. The default Laravel `Http::timeout` is 60s. Should hold but document. **Mitigation:** set explicit `->timeout(120)->connectTimeout(5)` on every eval HTTP call.

9. **Some tools may rely on `Auth::user()` being set.** If any tool somewhere reads `Auth::user()` instead of the passed-in `$user`, the live flow works (Sanctum populates it) but a hand-built `$user` wouldn't. The HTTP loop fixes this naturally — the controller IS Sanctum-authenticated so `Auth::user()` resolves correctly. **No mitigation needed; this is exactly the gap the plan closes.**

### 13.2 Open decisions (pre-implementation)

1. **JSON file extension.** `.json` (most natural) or `.eval.json` (more searchable, distinguishes from arbitrary data JSON)? Recommend `.json` — the directory `tests/Feature/Fyn/Eval/scenarios/` is the namespace.

2. ~~**Where do persona templates live?** Option A: copy from `PreviewUserSeeder` into `EvalUserSeeder` (duplication). Option B: extract to `database/seeders/data/personas/<id>.json` (decoupled). Option C: extract to a shared `PersonaDataBuilder` service that both seeders call (DRY). Recommend C if the preview seeder's methods are easily extractable; B if the cost is high.~~ **[RESOLVED 2026-04-28 per canonical 0.2.]** There is no `EvalUserSeeder`. Persona templates live exactly where they always have — inside `PreviewUserSeeder`. The eval uses the actual seeded preview user, so there is nothing to duplicate, decouple, or share. This decision is moot.

3. **Should the eval auto-start the local dev server?** Today CSJ runs `./dev.sh` manually before evals. If the eval fails its pre-flight health check, do we (a) error out and tell CSJ to start the server, or (b) attempt to start it and fail more loudly if that fails? Recommend (a) — auto-starting servers from a CLI command is too magical for a tool that's already complex.

4. **Should the multi-entity scenarios bind to a separate persona?** **[REVISED 2026-04-28 per canonical 0.2.]** Mirror users are forbidden — there is no `peak_earners_in_onboarding`, no `EvalUserSeeder`, no duplicate row. The 4 multi-entity YAMLs at `03-multi-entity/` stay as YAML for now (per §11.3, out of scope for this rewrite). When their JSON conversion lands, they will be authored as MUTATING scenarios that flip the persona's `onboarding_completed` flag via the bypass-write capability at the start of the recording, run the capture flow, and rely on canonical 0.1 reset orchestration in the caller to restore the flag (and any captured rows) afterwards. No mirror user. No persona duplication.

5. **Provider switch timing.** The cache-key flip happens in `EvalHttpDriver::run` immediately before login. Does the local Laravel server pick up the flip in time for the FIRST chat send? Yes — Laravel's cache is shared across processes via the configured driver (file by default in local). One-line `Cache::forever` is synchronous. **No issue, just confirming.**

6. **Should `EvalRecordCommand` keep the `--dry-run` flag?** The current dry-run mode skips provider calls but still seeds + creates session. With the HTTP rewrite, dry-run could mean (a) skip the HTTP loop entirely, (b) hit the local server but bypass the provider via a fake-provider config. Recommend (a) — dry-run becomes "validate scenario JSON + check pre-flight without running".

---

## Section 14 — Acceptance summary

The eval is correct when, for any one of the 10 mitchell scenarios:

- The recording was driven by **HTTP calls to the local Laravel server**, not by direct method invocation. Verifiable via `eval_recording_sessions.http_log[*].url` matching `/api/eval/login/...`, `/api/ai-chat/conversations`, `/api/ai-chat/conversations/{id}/messages`.
- **[REVISED 2026-04-28 per canonical 0.2.]** The Sanctum-authenticated user IS the actual seeded `peak_earners` preview user — there is NO mirror user. Verifiable via `eval_recording_sessions.eval_user_id` resolving to a user with `is_preview_user=true, preview_persona_id='peak_earners'`. The token's `bypass-preview-mode` ability is the sole differentiator from a real preview-user browser session. Any document or test asserting `is_eval_user=true` is stale and must be deleted.
- The captured `tool_calls[*].result` payloads contain real persona data. Verifiable by inspection: `list_records(life_insurance)` returns David Mitchell's actual life policies.
- The assistant text references real persona data by figure or name. Verifiable by `expected_assistant_text.must_contain_at_least_one_of` matching e.g. "Aviva", "£400,000", "critical illness", "Sarah", "your spouse".
- The `EvalDeltaBuilder`-rendered delta on the admin dashboard shows the correct per-tool result_path (`happy` for peak_earners' rich-data scenarios) and the correct per-provider per-path timing within budget.
- `is_mutating: false` scenarios produce zero `db_writes_made`. `is_mutating: true` scenarios produce the expected writes AND `EvalPersonaResetService` restores the user before the next session.

If all six hold, the eval measures the AI's actual user-journey behaviour. That's the goal.

---

## Section 15 — File-pointer index for the next instance

In execution order, every file the next instance needs to read or modify.

### 15.1 Read-first (context)

1. `April/April27Updates/CSJTODO.md` — session 103 closure, session 102 outputs, current state of branch.
2. `April/April24Updates/spec/00-canonical.md` — Two-Fyn contract (re-read top to bottom).
3. `April/April24Updates/spec/01-invariants.md` — INV-2.1.x through INV-2.13.x.
4. `April/April27Updates/eval-expectations-rewrite.md` — Sections 1, 3 (universal rewrite rules), 4 (per-scenario shape), 8 (meta-tests). Sections 6, 10, 11, 12 still apply for future scenario authoring.
5. `April/April24Updates/plan/11-sprint-1-plan.md` — for the broader sprint context.
6. THIS FILE.

### 15.2 Modify (in execution order)

1. `database/migrations/2026_04_27_*.php` (new) — `persona` + `http_log` + `engine_trace` columns on `eval_recording_sessions` and `eval_provider_runs`. (No `is_eval_user` migration — this design uses the existing peak_earners preview user directly.)
2. `app/Console/Commands/ResetPreviewData.php` (amended — extend `deleteUserData` per §8.2 + add Pest completeness test).
3. `app/Events/Eval/GateChecked.php` + `EngineCalled.php` + `AgentDecision.php` (new — 3 thin event classes).
4. `app/Http/Middleware/PreviewWriteInterceptor.php` (amended — 5-line ability check).
5. `app/Traits/HasAiChat.php` (amended — 1-line ability check at line 144).
6. `app/Agents/CoordinatingAgent.php` (amended — 1-line ability check at line 699).
7. `app/Services/Eval/EvalTraceCollector.php` (new — request-scoped singleton).
8. `app/Listeners/Eval/EvalTraceListener.php` (new — subscribes to 3 events; ability-gated).
9. Service provider register (amended — bind `EvalTraceCollector` as singleton + register listener).
10. Add `event(...)` calls at the 11 trace sites per §5.3 — KycGateChecker, 5x DataReadinessService, ProtectionAgent line 72, RetirementAgent line 101, PrerequisiteGateService::canGetRecommendations, CoordinatingAgent::orchestrateAnalysis, 6 module Agents' analyze, recommendation engines per module, QueryClassifier::classify, AdviceFyn::classifyResponseMode + engineCallLevel, CoordinatingAgent::executeTool.
11. `app/Constants/QuerySchemas.php` (amended — extend `REQUIRED_TOOLS[PROTECTION_COVER]` per §10.3).
12. `app/Services/Eval/EvalSseConsumer.php` (new).
13. `app/Services/Eval/EvalHttpDriver.php` (new).
14. `app/Http/Controllers/Api/EvalAuthController.php` (new — 3 endpoints).
15. `routes/api.php` (amended — add eval routes inside `if (! app()->environment('production'))` block).
16. `app/Console/Commands/EvalRecordCommand.php` (rewritten — ~70% deletion).
17. `app/Models/EvalRecordingSession.php` + `EvalProviderRun.php` (amended — fillable + casts for new columns).
18. `app/Services/Eval/EvalDeltaBuilder.php` (amended — Yaml→JSON swap + `gradeEngineTrace` method).
19. `app/Http/Controllers/Api/Admin/EvalRecordingController.php` (amended — Yaml→JSON swap; surface engine_trace).
20. `tests/Feature/Fyn/Eval/scenarios/01-query-types/*.json` (new) — 10 scenarios.
21. `tests/Feature/Fyn/Eval/scenarios/_schema.json` (new) — JSON Schema.
22. `tests/Feature/Fyn/Eval/AssertionHelpers.php` (amended — add 3 HTTP helpers + 1 engine-trace helper).
23. `tests/Architecture/` — new meta-tests: `EvalScenarioJsonSchemaTest`, `EvalScenarioPersonaIsValidTest`, `EvalScenarioMutatingFlagMatchesWritesTest`, `EvalScenarioEngineTraceConsistencyTest`, `PreviewBlockSitesCheckBypassTest`.
24. `tests/Feature/Fyn/Eval/EvalRunner.php` (amended — JSON-aware).
25. `resources/js/components/Admin/eval/RunPanel.vue` (amended — show persona + http_log + engine/gate timeline panel per §5.7).
26. Delete: `tests/Feature/Fyn/Eval/scenarios/01-query-types/*.yaml` (6 files — superseded). KEEP: `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/*.yaml` (4 files — out of scope for this rewrite per §11.3).

### 15.3 Verify

1. `php artisan test --filter=Eval` — all eval tests green.
2. `php artisan test --filter=PreviewBypass` — token-ability tests green; live preview-mode regression tests still green.
3. `php artisan eval:record mitchell_advice_protection_cover` — both providers green, fixture written, session row populated, `engine_trace` populated with the 7 expected events from §12.16 step 6.
4. Open admin dashboard at `/admin/eval-recordings/{id}` — persona shown, HTTP log shown (4 calls), engine/gate timeline shown.
5. Visual verification: log into peak_earners via the persona selector and ask the same question — response should now address all 3 protection types per §10.3 (the `QuerySchemas` correction).

---

*End of plan. Hand to writing-plans skill for the implementation sequencing once CSJ approves.*
