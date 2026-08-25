# maxAuditEval — three-way audit anchored to canonical contract

**Date:** 2026-04-28 (revised after CSJ canonical clarification)
**Branch:** `feature/fyn-persona-split` @ HEAD = `dd2942f`
**Scope:**
- **Doc A** (design spec): `April/April27Updates/eval-http-driven-rewrite-plan.md` (1046 lines)
- **Doc B** (impl plan): `April/April27Updates/eval-http-driven-rewrite-implementation-plan.md` (2990 lines)
- **Doc C** (audit): `April/April28Updates/iLovetoLeavestuffOut.md` (451 lines)
- **Code** under `app/`, `routes/`, `tests/`, `resources/js/components/Admin/eval/`

---

## Section 0 — THE CANONICAL CONTRACT (binding, owned by CSJ)

This is the single source of truth. Every plan, doc, test, and line of code must align with this. Where any document — Doc A, Doc B, Doc C, or any earlier note — disagrees, **the canonical wins** and the conflicting text must be deleted or rewritten.

### 0.1 Persona reset rule

> **A reset is ONLY triggered when an eval has actually changed data on the persona record.** A "change" means the eval wrote, edited, added, or deleted a row owned by the persona user.
>
> - **Non-mutating evals** (advice / navigation / information requests / factual queries / classification-only) **DO NOT** trigger a reset. Ever. Not pre-flight, not in finally, not between providers, not after the session.
> - **Mutating evals** (any scenario where `is_mutating: true` OR the captured `db_writes` snapshot diff is non-empty) trigger a reset **only after** the captured change + the result are persisted to `eval_recording_sessions` + `eval_provider_runs`. The reset returns the persona to the same state it was in when the eval began.
>
> All 10 current mitchell scenarios are non-mutating (`is_mutating: false`, `expected_db_writes.expected_count: 0`). **None of them should ever invoke `preview:reset`.**

### 0.2 No mirror user, no seed, no alteration

> **The eval runs against the actual seeded `peak_earners` preview user. Period.**
>
> - There is **no mirror user.**
> - There is **no `EvalUserSeeder`.**
> - There is **no `is_eval_user` flag.**
> - The eval **does not** seed, reset, or alter the persona record before the run.
> - The mechanism that lets the eval write (when it needs to, on mutating scenarios) is the Sanctum token's `bypass-preview-mode` ability checked at the 3 server-side write-block sites. That token is the only thing about the eval that differs from a real preview-user browser session.
>
> This was planned, agreed, and confirmed implemented in sessions 105-106. Any document or test that still references `is_eval_user`, mirror users, `EvalUserSeeder`, or pre-flight seeding/resetting is **stale and must be deleted**.

### 0.3 What follows from the canonical

- The current code on `feature/fyn-persona-split` HEAD `dd2942f` (no resets in `EvalHttpDriver`, login uses `is_preview_user=true` against the seeded peak_earners user) **matches the canonical** for all 10 current scenarios.
- Doc A §3.2 step 1, §8.1 (layers 1 & 2 as written), §8.4 ("at start AND end, defensively"), §6.1 example code (uses `is_eval_user`), and §14 acceptance criterion #2 ("user with `is_eval_user=true, is_preview_user=false`") **violate the canonical and must be edited.**
- Doc B Task 10 step 10.3 (pre-flight + post-flight reset inside `EvalHttpDriver::run`) **violated the canonical** and was correctly removed in commit `dd2942f`. The plan text itself is now historical.
- Doc C iLovetoLeavestuffOut Part D items 1, 4 (description of "spec said reset before/after capture") were correctly identifying the spec contradiction; under the canonical, those spec lines are simply wrong.

---

## Section 1 — Verdict (one paragraph)

The HTTP-driven eval is **structurally complete and aligned with the canonical for all 10 current scenarios** (no resets, no mirror, real preview user, Sanctum bypass token). It is **operationally broken** by three independent defects unrelated to the canonical: (1) the trace collector is request-scoped while the trace endpoint runs in a separate request, so `/api/eval/trace/{id}` always returns an empty list; (2) `EvalRecordCommand::extractToolCallsFromEvents` reads SSE key `name` while `HasAiChat` emits the key `tool`, so every captured `tool_calls[*].name` is `"unknown"`; (3) all 10 mitchell scenarios assert `expected_http_log.calls=4` but `EvalHttpDriver` makes 5 calls (login, create_conv, send_msg, **trace_fetch**, logout). Plus a spec-coverage gap: of the 11 trace fire sites Doc A §5.3 specified, the recommendation engines (6) and individual module-agent `analyze()` exits (5) are missing, though the central dispatcher at `CoordinatingAgent::handleModuleAnalysis:1556` covers the engine-trace requirement for the 10 current scenarios. **Three concrete code changes (P0.1, P0.2, P0.3 below) clear all four blockers.**

---

## Section 2 — Code reality vs canonical contract

### 2.1 Where code already matches canonical (do not regress)

| Canonical rule | Code evidence | Status |
|---|---|---|
| Eval logs in as actual `peak_earners` preview user | `EvalAuthController::login:47` queries `User::where('is_preview_user', true)->where('preview_persona_id', $personaId)` | ✅ aligned |
| No mirror user, no `EvalUserSeeder` | No `EvalUserSeeder` exists in `database/seeders/`; no `is_eval_user` column queried by `EvalAuthController` | ✅ aligned |
| `bypass-preview-mode` is the write mechanism | `PreviewWriteInterceptor`, `HasAiChat:144`, `CoordinatingAgent:699`, `EvalTraceListener:45` all check `in_array('bypass-preview-mode', $token->abilities ?? [], true)` | ✅ aligned (and using `in_array` not `can()` — correct because Sanctum's wildcard `['*']` would falsely pass `can()`) |
| Non-mutating evals do not reset | `EvalHttpDriver.php` 1-241 contains zero `Artisan::call('preview:reset', ...)` lines (verified post-`dd2942f`); `EvalRecordCommand.php` also contains zero such lines | ✅ aligned |
| Token bypass works concurrently per-token, not globally | Verified by `tests/Feature/PreviewBypassAbilityTest` | ✅ aligned |

### 2.2 Where code is silent on canonical (no future regression risk)

| Canonical rule | Code state | Status |
|---|---|---|
| Mutating evals reset AFTER capture | No mutating scenario currently exists; no reset orchestration code exists either | ⚪ deferred — when first mutating scenario lands, see §5.6 |

### 2.3 Where code does NOT match canonical

**None.** The code is canonical-clean as of `dd2942f`. All defects identified in §3 are unrelated to the canonical.

---

## Section 3 — Defects unrelated to the canonical (the actual blockers)

These are the four real obstacles to a green Task 16 acceptance gate. None of them have anything to do with reset behaviour or mirror users.

### 3.1 — Trace collector cross-request bug (BLOCKER)

**File evidence:** `app/Providers/EvalServiceProvider.php:20`:
```php
$this->app->scoped(EvalTraceCollector::class, fn () => new EvalTraceCollector);
```

`scoped()` creates one collector per request lifecycle. The chat-send (`POST /api/ai-chat/conversations/{id}/messages`) populates ITS request's collector. The trace fetch (`GET /api/eval/trace/{id}`) is a SEPARATE request and gets a fresh, empty collector. `EvalAuthController::trace:102` reads `app(EvalTraceCollector::class)->all()` from that empty fresh instance.

**Result:** every captured `engine_trace` is `[]`.

**This is a Doc A § 5.5 + Doc B Task 5 step 5.5 design flaw — both wrote "request-scoped collector + separate trace endpoint" without noticing the contradiction.**

**Fix:** see §5.1 below.

### 3.2 — `tool_calls[*].name === "unknown"` (BLOCKER)

**File evidence:** `app/Console/Commands/EvalRecordCommand.php:308-323`:
```php
private function extractToolCallsFromEvents(array $events): array
{
    $calls = [];
    foreach ($events as $event) {
        if (($event['type'] ?? null) !== 'tool_use') {
            continue;
        }
        $calls[] = [
            'name' => (string) ($event['name'] ?? 'unknown'),  // ← reads 'name'
            ...
```

**Actual SSE event shape:** `app/Traits/HasAiChat.php:440-441` emits:
```php
[
    'type' => 'tool_use',
    'tool' => $functionName,  // ← key is 'tool', NOT 'name'
    ...
]
```

Verified at `tests/Feature/Fyn/Eval/fixtures/anthropic/claude-haiku-4-5-20251001/mitchell_advice_protection_cover.jsonl` line 6:
```json
{"type":"tool_use","tool":"get_module_analysis","status":"running"}
```

**Result:** every recorded `tool_calls[*].name` is `"unknown"`. Acceptance gate criteria #3, #4, #5 cannot be evaluated.

**Fix:** see §5.2 below.

### 3.3 — HTTP call count mismatch (BLOCKER)

**File evidence:** `app/Services/Eval/EvalHttpDriver.php` makes 5 HTTP calls — login (line 74), create_conv (line 87), send_message (line 119), **trace_fetch** (line 141), logout (line 157). All 5 are appended to `http_log`.

**Scenario expectation:** all 10 files at `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_*.json` declare `"calls": 4` in `expected_http_log`. The `must_have_status_200` array lists exactly 4 names (login, create_conversation, send_message, logout) — `trace_fetch` is missing.

**Result:** `EvalDeltaBuilder` will mark `expected_http_log.calls` as failing for every scenario.

**Fix:** see §5.3 below — note that the cleanest fix is option (b) in §5.1 (eliminate the trace endpoint as a separate HTTP call), which closes both 3.1 and 3.3 in one change.

### 3.4 — Trace fire sites short of spec §5.3 (NOT a Task 16 blocker for the 10 current scenarios, but a spec-coverage gap)

Of the 11 fire sites Doc A §5.3 specified:

| # | Spec call site | Code reality | Match? |
|---|---|---|---|
| 1 | KycGateChecker per universal field (5 fields) | One emit at `KycGateChecker:51` for the universal stage as a whole | 🔶 simplified (5 → 1) |
| 2 | KycGateChecker per module | One emit at `KycGateChecker:67` per module loop iteration | ✅ |
| 3 | 5 DataReadinessService classes | All 5 fire (`Protection:57`, `Savings:54`, `Investment/Recommendation/DataReadinessService:46`, `Retirement:56`, `Estate:49`) | ✅ |
| 4 | Profile gate at ProtectionAgent + RetirementAgent | `ProtectionAgent:73`, `RetirementAgent:102` | ✅ |
| 5 | PrerequisiteGateService::canGetRecommendations | `PrerequisiteGateService:234` fires `recommendation_eligibility` | ✅ (Doc C audit said this was missing — Doc C was wrong) |
| 6 | CoordinatingAgent::orchestrateAnalysis (entry + exit) | One emit at exit (`:219`) | 🔶 simplified (2 → 1) |
| 7 | 6 module agents' analyze() returns | Zero per-agent emits; the central dispatcher `CoordinatingAgent::handleModuleAnalysis:1556` emits one event per module dispatch | 🔶 centralised (6 → 1) |
| 8 | Recommendation engines per module (6 engines) | Zero emits | ❌ missing |
| 9 | QueryClassifier::classify | `QueryClassifier:235` | ✅ |
| 10 | AdviceFyn::classifyResponseMode + engineCallLevel | `AdviceFyn:182` + `:191` | ✅ |
| 11 | CoordinatingAgent::executeTool | `CoordinatingAgent:734` | ✅ |

**For the 10 current mitchell scenarios:** every event the scenarios assert in `expected_engine_trace.must_contain` IS reachable from the current emit set, except scenarios that try to assert on `EngineCalled:{module}_recommendation` (none do, currently). So the 10 scenarios are SATISFIABLE once 3.1, 3.2, 3.3 are fixed.

**For spec parity:** items 1, 6, 7, 8 are the gaps. Items 7 (per-agent) and 8 (per-engine) matter most because the architecture meta-test `tests/Architecture/EvalScenarioEngineTraceConsistencyTest.php:8-22` legalises engine names like `protection_recommendation` that the runtime never emits.

---

## Section 4 — Documents to align with the canonical

Every line in every plan that contradicts §0 must be edited. Below is the exact list, document by document, line-anchored.

### 4.1 — Doc A (`eval-http-driven-rewrite-plan.md`) — REQUIRED EDITS

| Location | Current text (paraphrase or quote) | Required action | Reason |
|---|---|---|---|
| §3.2 lines 145-208 (HTTP loop pseudocode) | Step 1: "Reset persona to baseline (covers any prior leak)" | **DELETE** step 1 entirely. Renumber subsequent steps. | Violates canonical 0.1 — non-mutating evals must not reset. |
| §3.2 finally-block | "if scenario.is_mutating OR len(db_writes) > 0: eval_persona_reset_service.reset(scenario.persona)" | **MOVE** out of the driver into `EvalRecordCommand` after `EvalProviderRun::create()` persists. **REVISE** condition wording: "if persisted db_writes diff is non-empty (which is implied by is_mutating: true)". | Violates canonical 0.1 location ("only after change + result are persisted"). The driver runs before persistence; the caller runs after. |
| §4.5 lines 311-318 | "The eval driver invokes it via `Artisan::call('preview:reset', ...)` between provider runs (when a write is detected) and after the whole session (always, defensively)." | **REWRITE** to: "After capture and after the recording session row is persisted, the caller (`EvalRecordCommand`) invokes `Artisan::call('preview:reset', ['persona' => $persona])` if and only if the captured `db_writes` diff is non-empty. Non-mutating scenarios never reset." | Violates canonical 0.1 — "always defensively" wipes data unnecessarily. |
| §6.1 lines 503-506 | Example code: `User::where('is_eval_user', true)->where('preview_persona_id', $personaId)` | **REPLACE** with `User::where('is_preview_user', true)->where('preview_persona_id', $personaId)` to match the actual implementation. | Violates canonical 0.2 — no `is_eval_user`. Stale example from earlier draft. |
| §6.1 line 510 | Hint: "php artisan db:seed --class=EvalUserSeeder" | **REPLACE** with "php artisan db:seed --class=PreviewUserSeeder" | Same — `EvalUserSeeder` doesn't exist and never should. |
| §6.1 line 518 | `'is_eval_user' => true` in response shape | **REPLACE** with `'is_preview_user' => true` to match controller actual response | Same. |
| §8.1 layer 1 ("Per-provider-run reset...runs only if any write was detected") | Text implies driver-internal between providers | **REVISE** to: "After each provider run, the recording is persisted; the caller MAY reset between providers if the persisted diff is non-empty AND the next provider's run requires a clean slate (e.g. mutating scenario)." For all non-mutating scenarios this layer is a no-op and can be skipped. | Wording suggests reset orchestration belongs in driver; canonical says caller, only after persistence. |
| §8.1 layer 2 ("Per-session reset...Always runs if `is_mutating: true` OR if any write was detected") | "Always" wording | **REWRITE** to: "After all provider runs and persistence complete, the caller resets if and only if the persisted diff is non-empty (which `is_mutating: true` should always produce)." Drop the word "always". | Canonical 0.1 says reset is gated on actual change, not on the `is_mutating` flag alone. |
| §8.4 line 728 | "the eval driver leans on this — it always reset-runs at the start of a session AND at the end, defensively" | **DELETE** the entire sentence. | Directly violates canonical 0.1 "non-mutating evals do not trigger a reset, ever". |
| §11.5 line 890 | "`database/seeders/data/eval/<persona_id>.json` — IF the `EvalUserSeeder` decides..." | **DELETE** this list item. There is no `EvalUserSeeder`. | Canonical 0.2. |
| §13.1 risk 1 | Mitigation 1 mentions short token TTL | Keep; this is a separate hardening concern unrelated to canonical. | — |
| §13.1 risk 3 | "Mitigation: the eval driver's `finally` block ALWAYS calls `preview:reset peak_earners` regardless of how the run terminated." | **DELETE** this sentence. | Violates canonical 0.1. The new mitigation is: an `EvalAuthController::login` opportunistic check that warns if eval-source metadata exists from a prior crash, but does NOT reset. |
| §13.2 decision 4 | "Should the multi-entity scenarios bind to a separate persona? Options: ... `peak_earners_in_onboarding` mirror user" | **REWRITE** to: "Multi-entity scenarios needing in-flight onboarding state set `expected_persona_state.onboarding_completed: false` and the eval driver does NOT switch users — instead it momentarily flips the persona's `onboarding_completed` flag via the bypass-write capability before recording, then flips back. No mirror user." OR resolve to deferring multi-entity to a later piece of work that updates the canonical first. | Violates canonical 0.2 (no mirror users). |
| §14 line 984 acceptance criterion #2 | "Sanctum-authenticated user IS the persona-mirror user. Verifiable via `eval_recording_sessions.eval_user_id` resolving to a user with `is_eval_user=true, is_preview_user=false, preview_persona_id='peak_earners'`." | **REWRITE** to: "Sanctum-authenticated user IS the actual seeded `peak_earners` preview user. Verifiable via `eval_recording_sessions.eval_user_id` resolving to a user with `is_preview_user=true, preview_persona_id='peak_earners'`. There is no mirror user." | Directly violates canonical 0.2. This is the most important edit in Doc A — it's the acceptance criterion. |
| §15.1 read-first list | (no edits needed) | — | — |

**Total Doc A edits: 13 surgical changes across 11 sections.** Net effect: ~30 lines deleted or rewritten.

### 4.2 — Doc B (`eval-http-driven-rewrite-implementation-plan.md`) — REQUIRED EDITS

| Location | Current text | Required action | Reason |
|---|---|---|---|
| Task 10 (EvalHttpDriver), step 10.3 lines ~1828, 1922-1924 | Pre-flight `Artisan::call('preview:reset', ['persona' => $scenario['persona']])` and post-flight conditional reset INSIDE `EvalHttpDriver::run` | **DELETE both reset blocks from Task 10.** Mark Task 10 step 10.3 as "[REVISED 2026-04-28] — reset orchestration moved to caller per canonical 0.1; driver does NOT reset." Add a NEW Task 10.3a: "If the scenario `is_mutating: true` OR the persisted db_writes diff is non-empty, `EvalRecordCommand` calls `Artisan::call('preview:reset', ['persona' => $persona])` AFTER `EvalProviderRun::create()`." | Violates canonical 0.1 (location and conditionality). Already done in code via `dd2942f` but the plan text still reads as if pre-flight is correct. |
| Task 7 step 7.3 (EvalAuthController) | Already correct — uses `is_preview_user` | No change needed. | — |
| Task 11 step 11.x (EvalRecordCommand rewire) | Currently does not include reset orchestration | **ADD** explicit step: "After `EvalProviderRun::create()` returns successfully, if `$result['db_writes']` is non-empty, call `Artisan::call('preview:reset', ['persona' => $scenario['persona']])`." | Canonical 0.1 makes the caller responsible. |

**Total Doc B edits: 2 surgical changes (one DELETE + one ADD).** Net effect: ~10 lines.

### 4.3 — Doc C (`iLovetoLeavestuffOut.md`) — REQUIRED EDITS

Doc C is a session-end audit, not a forward-looking spec. Its contradiction-callouts are largely accurate but a few sentences imply the spec was right and the impl-plan was wrong, when under the canonical, the spec was ALSO wrong on those points. Edits:

| Location | Current text | Required action | Reason |
|---|---|---|---|
| Part A §3.2 IMPACT block | "the per-provider pre-flight reset is the root cause" | Annotate: "[Resolved 2026-04-28 by canonical 0.1 — pre-flight reset is forbidden, not just misplaced.]" | Canonical clarifies the rule, not just the location. |
| Part A §4.5 DIVERGENCE points 1-2 | "Pre-flight reset is unconditional, spec says it should not run pre-flight at all (§8.1, §4.5)." & "Post-flight reset is per-provider, spec says it should be per-session." | Annotate: "[Per canonical 0.1, both spec and impl plan were wrong on different counts. Canonical says: post-capture, caller-side, conditional on detected changes only.]" | The spec wording itself also contradicts canonical. |
| Part A §8 (PLANNED text) | "Three layers — per-provider-run reset (between providers, only on detected writes), per-session reset (after the whole session, always if mutating or any write detected), manual reset endpoint." | Annotate: "[Per canonical 0.1, layer 2's 'always if mutating' is wrong — must be 'only on persisted db_writes diff non-empty'.]" | Doc A §8 violates canonical. |
| Part A §14 DIVERGENCE | "criterion 3/5 fail because of the `name: 'unknown'` issue" | Already correct — defect is real. | No edit needed. |
| Part E item 1 | "Remove the pre-flight reset from `EvalHttpDriver::run`. Keep the post-flight reset but make it per-session..." | Update: item 1 is DONE (commit `dd2942f`). The "keep post-flight" guidance is also superseded — canonical 0.1 puts post-capture reset in the caller, not the driver, and only on persisted writes. | Reflect that `dd2942f` already removed both, and the new guidance is mutating-scenario reset goes in `EvalRecordCommand`. |
| Throughout | Various references to "pre-flight reset would defeat the bypass-preview-mode toggle" | Keep as historical record but add a final note: "[Canonical 0.1 issued 2026-04-28 makes this explicit: non-mutating evals must never reset.]" | Cements the rule. |

**Total Doc C edits: 5 inline annotations.** Net effect: roughly preserve as a session record while explicitly cross-referencing the canonical.

---

## Section 5 — Bringing the eval online (priority-ordered fix list)

These are the only code changes required to satisfy the Task 16 acceptance gate. None of them touch reset behaviour or mirror users — those are already canonical-clean.

### 5.1 (P0) — Fix the trace cross-request bug

**Recommended approach (b) — persist trace to the run row directly:**

In whichever method completes the chat-send (`HasAiChat::handleStream` or its caller — locate via the `StreamedResponse` close-out), if `request()->user()?->currentAccessToken()?->can('bypass-preview-mode')` (or the `in_array` equivalent) is true, dump the collector contents to a per-conversation cache key:

```php
\Illuminate\Support\Facades\Cache::put(
    "eval_trace:conversation:{$conversation->id}",
    app(\App\Services\Eval\EvalTraceCollector::class)->all(),
    now()->addMinutes(10)
);
```

Then in `EvalAuthController::trace`, replace `app(EvalTraceCollector::class)->all()` with:

```php
$events = \Illuminate\Support\Facades\Cache::pull(
    "eval_trace:conversation:{$conversationId}",
    []
);
```

**Alternative (cleaner long-term):** drop the trace endpoint entirely. At end of the chat-send, if eval is active, write the trace directly to `eval_provider_runs.engine_trace` (the column already exists per Task 1). `EvalRecordCommand` reads it back from the DB after `EvalProviderRun::create()`. This also lets §5.3 be solved by removing the trace fetch from the driver — see §5.3.

**Deliverable:** captured `engine_trace` in the recording is non-empty and contains the events from the live request.

### 5.2 (P0) — Fix the SSE key in tool-call extraction

`app/Console/Commands/EvalRecordCommand.php:316`:

```php
'name' => (string) ($event['name'] ?? 'unknown'),
```

Change to:

```php
'name' => (string) ($event['tool'] ?? 'unknown'),
```

Add a unit test `tests/Unit/Console/Commands/EvalRecordCommandToolExtractionTest`:
- Load `tests/Feature/Fyn/Eval/fixtures/anthropic/claude-haiku-4-5-20251001/mitchell_advice_protection_cover.jsonl`.
- Filter to `type=tool_use` events.
- Pass through `extractToolCallsFromEvents` (extract via reflection or by making the method `public static`).
- Assert `tool_calls[0]['name'] === 'get_module_analysis'`.

**Deliverable:** captured `tool_calls[*].name` matches the actual tool names.

### 5.3 (P0) — Reconcile HTTP call count to 4

**If §5.1 took alternative approach (drop trace endpoint):** `EvalHttpDriver::run` now makes 4 calls. All 10 mitchell scenarios already declare `calls: 4`. Done.

**If §5.1 took recommended approach (cache + endpoint):** the driver still makes 5 calls. Either:
- Update all 10 scenarios: `calls: 5`, add `trace_fetch` to `must_have_status_200`. 10 small JSON edits.
- Or: don't log the trace_fetch in `http_log` (skip the `$this->logCall(...)` line for the trace fetch in `EvalHttpDriver:144`). Driver still makes 5 calls but only 4 appear in `http_log`. Slightly dishonest record-keeping.

**Recommendation:** do alternative §5.1 + drop the trace fetch entirely. Cleanest.

**Deliverable:** `http_log.calls` matches scenario `expected_http_log.calls` exactly.

### 5.4 (P1) — Spec-parity emits (optional for Task 16, required for full Doc A §5.3 compliance)

Pick one of two postures:

**(a) Implement the missing emits.** Add `event(new EngineCalled(...))` to:
- The 6 `app/Agents/{Protection,Savings,Investment,Retirement,Estate,Goals}Agent::analyze()` methods at the return statement.
- The 6 `app/Services/{Protection,Savings,Investment,Retirement,Estate,Goals}/Recommendation/*Engine.php` `generate()` methods at the return.
- A second event at `CoordinatingAgent::orchestrateAnalysis` entry (currently only exit fires).

**(b) Codify the simplification.** Update Doc A §5.3 to reflect that:
- KYC fires per-stage not per-field (1 universal + N per-module).
- Module-agent EngineCalled is emitted by the central dispatcher at `CoordinatingAgent::handleModuleAnalysis:1556`, not per-agent.
- Recommendation engines do not emit EngineCalled (and remove `*_recommendation` strings from `EvalScenarioEngineTraceConsistencyTest::$validEngines:16-21` so the architecture meta-test reflects reality).
- `orchestrateAnalysis` fires once at exit (drop "entry+exit" wording).

**Recommendation:** (b) for now — none of the 10 current scenarios require the missing emits, and (a) adds 13 minor edits. Revisit (a) if and when a future scenario asserts on `protection_recommendation` etc.

### 5.5 (P1) — Reconcile 3 scenario classifications

Three of 10 scenarios have a primary classification that diverges from Doc A §10.2:

| Scenario | Doc A §10.2 primary | Live primary | Recommended action |
|---|---|---|---|
| `mitchell_advice_investment_isa` | `investment_portfolio` | `investment_tax` | Change scenario user message from "How is our ISA portfolio doing?" to "How are our investments performing?" — strips the ISA keyword that triggers the tax classifier. Then re-verify primary. |
| `mitchell_advice_goals_affordability` | `affordability` | `goals_progress` | Update Doc A §10.2 row 8 primary to `goals_progress`. The classifier has no `affordability` primary. |
| `mitchell_factual_net_worth` | `net_worth` | `general` | Update Doc A §10.2 row 9 primary to `general`. `QuerySchemas` has no `net_worth` primary. |

Either approach produces a coherent classifier ↔ scenario contract. Don't widen `KEYWORD_PATTERNS` unless there's a live-product reason — that change affects real users.

### 5.6 (P1) — Add reset orchestration to `EvalRecordCommand` for future mutating scenarios

When the first mutating scenario lands (none yet), add to `EvalRecordCommand::recordOne` after `EvalProviderRun::create([...])` persists successfully:

```php
$dbWrites = $result['db_writes'] ?? [];
if (! empty($dbWrites)) {
    $this->info("Mutating recording detected ".count($dbWrites)." diff entries — resetting persona.");
    \Illuminate\Support\Facades\Artisan::call('preview:reset', ['persona' => $scenario['persona']]);
}
```

Reset runs once per session (not per provider). Reset runs only if the persisted diff is non-empty. This is the canonical 0.1 implementation in code.

**Deferred:** until first mutating scenario lands. All 10 current scenarios are non-mutating.

### 5.7 (P1) — Add HTTP-log panel to `RunPanel.vue`

Data is already in the API response (`session.http_log`, surfaced by `EvalRecordingController:118`). Add a new panel between the engine-timeline (currently at lines 172-196) and the fixture/system-prompt links. Iterate `session.http_log`, render a 4-column table: method, URL, status, duration_ms. ~30 lines of template + 5 lines of script.

### 5.8 (P2) — Risk mitigations (Doc A §13)

| # | Mitigation | Code state | Action |
|---|---|---|---|
| Token TTL | Sanctum token never expires | `EvalAuthController::login:58-61` add `expiresAt: now()->addMinutes((int) config('fyn_eval.token_ttl_minutes', 15))` |
| `connectTimeout(5)` on HTTP calls | absent | `EvalHttpDriver` lines 74, 86, 117, 142, 156 — chain `->connectTimeout(5)` |
| Pre-flight server health check | absent | First action in `EvalHttpDriver::run`: `Http::timeout(2)->get("{$baseUrl}/api/preview/personas")`, throw if non-200 |
| Token cleanup at driver setup | absent | Before login, `PersonalAccessToken::where('name', 'like', 'eval-%')->where('created_at', '<', now()->subHour())->delete();` |
| `PreviewBlockSitesCheckBypassTest` actually scans | hard-coded 3 paths | Make it scan `app/Http/Middleware/`, `app/Traits/`, `app/Agents/` for `is_preview_user` reads in write-context, assert each also contains `bypass-preview-mode`. Use ignore-list for false positives. |

---

## Section 6 — Acceptance gate (Doc A §12.16) status

Re-evaluated under the canonical:

| # | Criterion | State | Blocker |
|---|---|---|---|
| 1 | `db:seed --class=PreviewUserSeeder` produces peak_earners with full data | ✅ | none |
| 2 | `eval:record mitchell_advice_protection_cover` runs end-to-end via HTTP loop, both providers' rows populated, `http_log` populated, `engine_trace` populated, `status=completed` | 🚫 | `engine_trace` empty (§5.1); `http_log.calls=5` vs scenario `4` (§5.3) |
| 3 | `tool_calls[*].result` non-empty AND `tool_calls[*].name` matches actual tool name | 🚫 | `name === "unknown"` (§5.2) |
| 4 | `get_module_analysis(protection)` returns happy path | ✅ visible in fixture text; structural check blocked by §5.2 |
| 5 | Assistant text contains FCA signposting AND real persona data | ✅ — fixture contains "Vitality", "Legal & General", "£500,000", "income protection", "Sarah" |
| 6 | engine_trace contains 7 expected events in order | 🚫 | empty due to §5.1; once populated, all 7 events have working emit sites and will appear |
| 7 | EvalDeltaBuilder grades both runs as PASS | 🚫 | blocked by §5.1, §5.2, §5.3 |
| **Plus canonical** | No `preview:reset` invoked; no `db:seed` invoked; persona `users.id` byte-identical before and after | ✅ aligned in code (no reset orchestration anywhere yet) — verify by capturing `User::where('preview_persona_id', 'peak_earners')->first()->id` before and after a recording attempt |

**3 ✅, 4 🚫.** Three concrete code changes (§5.1, §5.2, §5.3) clear all 4 blockers. The canonical is already satisfied.

---

## Section 7 — Definition of done

The eval is online when ALL of the following are true:

1. §5.1, §5.2, §5.3 shipped and committed.
2. `php artisan eval:record mitchell_advice_protection_cover --provider=anthropic --provider=xai` produces 1 row in `eval_recording_sessions` and 2 in `eval_provider_runs` with: `http_log` populated and matching scenario expectation, `engine_trace` populated, `tool_calls[*].name` set to actual tool names, `assistant_text` non-empty.
3. The captured `engine_trace` for protection_cover contains, in order: `AgentDecision:classify_query`, `AgentDecision:response_mode`, `GateChecked:kyc:global`, `GateChecked:kyc:protection`, `GateChecked:data_readiness:protection`, `GateChecked:profile_gate:protection`, `EngineCalled:protection_analysis`. Verified by reading the JSON.
4. `EvalDeltaBuilder::gradeEngineTrace` returns PASS for both providers' runs.
5. **Canonical compliance:** `users.where('preview_persona_id', 'peak_earners')->first()->id` is byte-identical before and after the recording. No `preview:reset` invocation in any logs. No `db:seed` invocation.
6. Doc A and Doc B edits per §4.1 and §4.2 above are merged so the source of truth matches the canonical.

After step 6, repeat for the remaining 9 mitchell scenarios in batch. That is v1 of the HTTP-driven eval.

---

## Section 8 — What I am NOT recommending

To make scope explicit:

- **I am not recommending any reset behaviour for the 10 current scenarios.** None of them are mutating. The canonical forbids reset on non-mutating scenarios. The current code (`dd2942f`) is correct.
- **I am not recommending introducing a mirror user, `EvalUserSeeder`, or `is_eval_user` flag.** The canonical forbids these. The current code is correct.
- **I am not recommending widening `QuerySchemas::KEYWORD_PATTERNS`** to satisfy the 3 mismatched primaries. Live-product changes require live-product justification. Update the spec or the user message instead.
- **I am not recommending dropping the `engine_trace` column** from `eval_provider_runs`. The architecture is sound; only the cross-request retrieval mechanism is broken (§3.1).
- **I am not recommending re-recording** until §5.1, §5.2, §5.3 land. Re-recording with the current bugs produces fixtures with `tool_calls.name="unknown"` and empty `engine_trace`, which can't be ungenerated cheaply (each costs LLM tokens).

---

## Appendix A — File:line reference map (verified)

| Component | File | Lines |
|---|---|---|
| EvalHttpDriver — no resets | `app/Services/Eval/EvalHttpDriver.php` | 1-241 (full) |
| EvalHttpDriver — explicit no-reset doc | `app/Services/Eval/EvalHttpDriver.php` | 32-37 |
| EvalAuthController login (uses `is_preview_user`) | `app/Http/Controllers/Api/EvalAuthController.php` | 47 |
| EvalAuthController production guards | `app/Http/Controllers/Api/EvalAuthController.php` | 39, 76, 91 |
| Routes — eval prefix with prod gate | `routes/api.php` | 1269-1281 |
| EvalServiceProvider — scoped binding (the bug) | `app/Providers/EvalServiceProvider.php` | 20 |
| EvalTraceCollector | `app/Services/Eval/EvalTraceCollector.php` | 1-73 |
| EvalTraceListener — bypass check | `app/Listeners/Eval/EvalTraceListener.php` | 33-46 |
| EvalRecordCommand — wrong-key bug | `app/Console/Commands/EvalRecordCommand.php` | 308-323, esp. 316 |
| EvalRecordCommand — no reset orchestration | `app/Console/Commands/EvalRecordCommand.php` | (no Artisan::call('preview:reset', …) anywhere — verified by grep) |
| EvalRecordingController — persona+http_log surfacing | `app/Http/Controllers/Api/Admin/EvalRecordingController.php` | 117-118 |
| EvalRecordingController — `_full` strip | `app/Http/Controllers/Api/Admin/EvalRecordingController.php` | 79-80 |
| EvalDeltaBuilder gradeEngineTrace | `app/Services/Eval/EvalDeltaBuilder.php` | 511 |
| Trace fire site — KYC global stage | `app/Services/AI/KycGateChecker.php` | 51-60 |
| Trace fire site — KYC per-module | `app/Services/AI/KycGateChecker.php` | 67-76 |
| Trace fire site — Protection profile_gate | `app/Agents/ProtectionAgent.php` | 73 |
| Trace fire site — Retirement profile_gate | `app/Agents/RetirementAgent.php` | 102 |
| Trace fire site — orchestrate exit | `app/Agents/CoordinatingAgent.php` | 219-229 |
| Trace fire site — tool_dispatch | `app/Agents/CoordinatingAgent.php` | 734 |
| Trace fire site — handleModuleAnalysis (covers 6 modules) | `app/Agents/CoordinatingAgent.php` | 1556-1565 |
| Trace fire site — Protection readiness | `app/Services/Protection/ProtectionDataReadinessService.php` | 57 |
| Trace fire site — Savings readiness | `app/Services/Savings/SavingsDataReadinessService.php` | 54 |
| Trace fire site — Investment readiness | `app/Services/Investment/Recommendation/DataReadinessService.php` | 46 |
| Trace fire site — Retirement readiness | `app/Services/Retirement/RetirementDataReadinessService.php` | 56 |
| Trace fire site — Estate readiness | `app/Services/Estate/EstateDataReadinessService.php` | 49 |
| Trace fire site — QueryClassifier | `app/Services/AI/QueryClassifier.php` | 235 |
| Trace fire site — AdviceFyn response_mode | `app/Services/AI/AdviceFyn.php` | 182 |
| Trace fire site — AdviceFyn engine_call_level | `app/Services/AI/AdviceFyn.php` | 191 |
| Trace fire site — recommendation_eligibility | `app/Services/PrerequisiteGateService.php` | 234-244 |
| HasAiChat tool_use SSE emit (key is `tool` not `name`) | `app/Traits/HasAiChat.php` | 440-441 |
| RunPanel engine timeline (no http_log panel) | `resources/js/components/Admin/eval/RunPanel.vue` | 172-196 + whole template 1-220 |
| Fixture confirming SSE shape | `tests/Feature/Fyn/Eval/fixtures/anthropic/claude-haiku-4-5-20251001/mitchell_advice_protection_cover.jsonl` | line 6 |
| Mitchell scenarios http_log expectation (calls=4) | `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_*.json` | per-file `expected_http_log.calls` |
| Mitchell scenarios primaries | `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_*.json` | per-file `expected_classification_shape.primary` |
| Architecture meta-test — engine list | `tests/Architecture/EvalScenarioEngineTraceConsistencyTest.php` | 8-22 |
| Architecture meta-test — bypass-sites narrow | `tests/Architecture/PreviewBlockSitesCheckBypassTest.php` | 1-22 (full) |

---

## Appendix B — Commits map (`feature/fyn-persona-split`)

| Commit | What |
|---|---|
| `dd2942f` | Remove pre-flight + post-flight reset from EvalHttpDriver — **canonical 0.1 alignment** |
| `dac1a66` | Surface persona + engine_trace + http_log on admin dashboard |
| `dc962f0` | 5 architecture meta-tests for scenario integrity |
| `ab89fd4` | Wire EvalDeltaBuilder + EvalRecordingController to JSON; add gradeEngineTrace |
| `f13208c` | Author 10 mitchell JSON scenarios; delete superseded advice YAMLs |
| `df51cd3` | Rewire EvalRecordCommand to use EvalHttpDriver |
| `3378f03` | Add EvalHttpDriver — HTTP-driven eval loop |
| `9b4170b` | Add JSON Schema for scenario files |
| `5cf51d4` | Wire trace events at gate, agent, and engine call sites |
| `ab00ded` | Add EvalSseConsumer for SSE frame parsing |
| `dc76112` | PROTECTION_COVER must surface all 3 protection types |
| `84e43c7` | Add EvalAuthController with login + reset + trace endpoints — **canonical 0.2 alignment (uses is_preview_user)** |
| `8e0bb16` | Add EvalTraceCollector + EvalTraceListener |
| `235a019` | Add bypass-preview-mode token ability to 3 write-block sites — **canonical 0.2 mechanism** |
| `8fe5698` | Add GateChecked + EngineCalled + AgentDecision event classes |
| `a6531f3` | Extend preview:reset to all persona-touched tables + SoftDeletes fix |
| `67a0b08` | Add persona + http_log + engine_trace columns to eval recording tables |

17 commits since the rewrite started; all on `feature/fyn-persona-split`; all pushed to origin.

---

*End of audit. Verified 2026-04-28 against `feature/fyn-persona-split` HEAD `dd2942f`. Anchored to canonical contract issued by CSJ in this session.*
