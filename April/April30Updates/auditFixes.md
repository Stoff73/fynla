# Fyn AI Audit — Fixes Applied

**Date:** 2026-04-30
**Branch:** `feature/fyn-persona-split`
**Source audit:** [`fyn-ai-audit.md`](./fyn-ai-audit.md)
**Verification:** 58/58 tests in the AI / Fyn / Onboarding / Architecture slice green. 95/95 architecture tests green. Pre-existing time-flake in `AdviceReviewServiceTest::annual review due` noted but unrelated.

This document records the implementation of the 15 findings (F-1 through F-15) from the 30 April 2026 audit. Each entry pins the change, file(s) touched, why it matters, and the test evidence.

---

## Summary table

| # | Finding | Severity | Status | Tests |
|---|---|---|---|---|
| F-1 | INV-2.4.5 enforced — `HandoffPayloadValidator` wired + `handoff_error` SSE event | P1 | ✅ Fixed | New `AdviceFynHandoffErrorTest.php` (3 cases) |
| F-2 | `UserContentSanitiser` preserves Unicode names | P1 | ✅ Fixed | Extended `UserContentSanitisationTest.php` (François / CJK / Cyrillic) |
| F-3 | Tool results compressed before re-injection into LLM context | P1 | ✅ Fixed | Existing harness covers (no regression) |
| F-4 | Anthropic `cache_read_input_tokens` captured for telemetry | P2 | ✅ Fixed | — |
| F-5 | Onboarding prompt reordered: `known_facts` last for cache prefix | P2 | ✅ Fixed | Existing onboarding tests green |
| F-6 | `WriteIntentClassifier` interrogative guard added | P1 | ✅ Fixed | New `WriteIntentClassifierTest.php` (15 cases) |
| F-7 | Dead `getDataCreationGuidance` method deleted | P2 | ✅ Fixed | — |
| F-8 | System prompt persisted as `sha256:` hash, not full text | P2 | ✅ Fixed | — |
| F-9 | Prompt caches invalidated on every successful write | P2 | ✅ Fixed | — |
| F-10 | Engine-level fallback default flipped to `factual` | P2 | ✅ Fixed | Existing exhaustive-map test green |
| F-11 | Duplicate-check guard added to onboarding asset_capture turns | P2 | ✅ Fixed | Existing onboarding tests green |
| F-12 | Eval `bypass-preview-mode` ability now requires `X-Eval-Run-Id` header | P2 | ✅ Fixed | Existing eval tests green |
| F-13 | Single retry on transient LLM errors (429 / 529 / timeout) | P2 | ✅ Fixed | — |
| F-14 | Layer 5/6 (financial_context, existing_records) skipped for factual queries | P2 | ✅ Fixed | — |
| F-15 | Dynamic tool-call cap by engine level (holistic 8 / module 5 / factual 3) | P2 | ✅ Fixed | — |

**P1 fixes:** 4. **P2 fixes:** 11. **Net Sprint 0 invariants now conformant:** 34/35 (INV-2.3.5 still deferred per spec to Sprint 1).

---

## F-1 — Wire HandoffPayloadValidator + emit `handoff_error` SSE event

**Severity:** P1 (canonical contract gap, INV-2.4.5)
**Files:**
- `app/Services/AI/AdviceFyn.php` — `wrapStream` now calls `HandoffPayloadValidator::validateDelegateToCapture()` before `CaptureContext::fromArray`. Hard malformations (entity_types missing or non-array) yield a `handoff_error` SSE event + `done`, then return. Soft malformations (only `reason` missing) log at notice level and recover via `CaptureContext`'s synthesise-reason path so the user-visible flow stays seamless.
- `resources/js/store/modules/aiChat.js` — new `case 'handoff_error':` handler appends a normal assistant bubble with the message ("I couldn't pick up that request — could you try again?"). Per INV-2.4.3, no special chrome.

**Why:** Pre-fix, malformed `delegate_to_capture` payloads were silently swallowed via `try { CaptureContext::fromArray() } catch { Log::warning(); continue; }`. The user saw a half-response with no indication that anything went wrong. INV-2.4.5 required a `handoff_error` SSE event explicitly so capture mode never silently lingers.

**Tests:** New `tests/Feature/Fyn/AdviceFynHandoffErrorTest.php`:
1. `entity_types` missing entirely → `handoff_error` + `done` emitted, no `handoff` event leaks
2. `entity_types` present but wrong type (string vs array) → `handoff_error` emitted
3. `reason` missing but `entity_types` valid → recovers via synthesis, NO `handoff_error` (the resilient path stays open for high-frequency soft errors)

---

## F-2 — UserContentSanitiser preserves Unicode names

**Severity:** P1 (inclusivity + memory consistency)
**Files:**
- `app/Services/AI/Prompts/UserContentSanitiser.php` — replaced the whitelist `[A-Za-z0-9\s'.,\-]` with a denylist that strips only injection-relevant characters: `<>{}[]();"\``|\$@#&=+*^~/:?`, control chars except `\t\n\r`, and `\p{Cf}`/`\p{Co}`/`\p{Cn}`/`\p{So}` (format / private-use / unassigned / symbols).

**Why:** The whitelist locked out non-ASCII names — "François" → "Franois", "Müller" → "Mller", "李" → "" (empty). This is a real inclusivity bug AND a memory-consistency bug: the LLM saw a different name than the DB stores, breaking the "do not re-ask known facts" contract (INV-2.2.3). The denylist preserves Unicode letters while still blocking every character used in known prompt-injection vectors.

**Tests:** Extended `tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php`:
- Replaced "strips non-ASCII letters" test with "preserves non-ASCII Latin names (François Müller)"
- Added "preserves CJK names (李四, 鈴木一郎)" + "preserves Cyrillic (Алексей) and Ñoño"
- Updated "fully stripped" test to reflect the new (looser, still safe) denylist

All 21 sanitiser tests green.

---

## F-3 — Compress tool results before re-injecting into LLM context

**Severity:** P1 (cost — material for holistic chats)
**File:** `app/Traits/HasAiChat.php` — new `compressToolResultForModel(string $toolName, array $result): array` and `trimForModel(mixed $value, int $depth): mixed`. Applied at the tool-result encoding site (replaced raw `json_encode($toolResult)` with `json_encode($this->compressToolResultForModel(...))`).

**Compression policy:**
- **Errors** pass through verbatim (the model must surface them per FcaProcessInstructions WRITE-failure rule)
- **Handoff / navigate / fill_form** action results pass through (small)
- **Direct-write** results trim to `{success, entity_type, entity_id, name}` — the chaining surface, not the persisted_fields verbosity
- **Read tools** (general path) recursively trim: list arrays >10 items collapse to head + `__truncated__: N items omitted` + tail; depth ≥3 collapses to `[nested N items]`; strings >200 chars truncated with `…`

**Why:** `get_module_analysis` returns ~5–10KB of structured JSON; un-compressed, every subsequent loop iteration eats those tokens again. Estimated 60% reduction in input tokens on holistic chats with multiple tool calls.

---

## F-4 — Capture Anthropic prompt-cache hit rate

**Severity:** P2 (observability)
**File:** `app/Traits/HasAiChat.php` — Anthropic `RawMessageStartEvent` handler now reads `$event->message->usage->cacheReadInputTokens` (and snake-case fallback) and adds to `$totalCachedTokens`.

**Why:** Anthropic system prompt caching has been configured (`cache_control: ephemeral`) since S0.x but the SDK exposes hit-rate via `cacheReadInputTokens` which the code never read. Result: `ai_messages.metadata.cache_hit_rate` was always 0 for Anthropic — caching may have been working, may have been broken, no way to tell from telemetry. Now visible.

---

## F-5 — Reorder onboarding prompt for cache prefix

**Severity:** P2 (cost)
**File:** `app/Services/Onboarding/OnboardingPromptBuilder.php` — layer order changed:

**Before:** `CoreIdentity → ComplianceRules → known_facts → assetCaptureInstructions`
**After:** `CoreIdentity → ComplianceRules → assetCaptureInstructions → known_facts`

**Why:** Anthropic prefix-cache only hits when the prefix is byte-identical across turns. The `known_facts` block grows after every capture during the 6–8 SaveTax onboarding turns, invalidating the prefix from Layer 3 onward. With the static `assetCaptureInstructions` moved up, the first three layers (≈350 tokens) are now stable for the duration of a focus block and benefit from cache hits. Estimated 60–70% input-token reduction on turns 2–N of an onboarding session.

---

## F-6 — WriteIntentClassifier interrogative guard

**Severity:** P1 (mis-routing advice questions as writes)
**File:** `app/Services/AI/WriteIntentClassifier.php` — new `INTERROGATIVE_PREFIXES` constant + `looksLikeQuestion()` private method. `classify()` now returns null when (a) the message ends with `?` OR (b) starts with one of the interrogative prefixes (`should i`, `can i`, `how do i`, `what is`, `where should`, `tell me`, `explain`, `show me`, etc.).

**Why:** Pre-fix, "Should I add to my Cash ISA?" matched verb (`add`) + entity (`cash isa`) and bypassed the LLM straight to inline-capture. The user got "What do you want to add?" instead of an answer. The guard is conservative — false negatives (real imperatives without `?`) still recover via the LLM's own `delegate_to_capture` path.

**Tests:** New `tests/Unit/Services/AI/WriteIntentClassifierTest.php` (15 cases): positive imperatives still match; 8 interrogative shapes correctly return null; negatives unaffected.

---

## F-7 — Delete dead `getDataCreationGuidance` method

**Severity:** P2 (re-enablement risk)
**File:** `app/Services/AI/Prompts/FcaProcessInstructions.php` — removed the unused private static method (lines 105-129 of the pre-fix file). The block contradicted `<handoff_guidance>` (described pre-S0.5 fill_form semantics: "the tool will open a form on screen") and was no longer called from `get()`. Leaving it as dead code created a re-enablement risk if a future engineer/agent saw no consumer and assumed it was meant to be wired.

---

## F-8 — Persist system prompt as `sha256:` hash, not full text

**Severity:** P2 (DB bloat + GDPR data minimisation)
**File:** `app/Traits/HasAiChat.php` — assistant-message `system_prompt` field now writes `'sha256:' . hash('sha256', $systemPrompt)` instead of the 1500–1800 token full prompt.

**Why:** The full system prompt embeds user PII (income, family names, financial position) and was 5–10KB per assistant message. Storing it on every row of a long conversation = ~1MB metadata bloat per 100-message conversation, plus a redundant copy of data already canonical in `users` / `family_members` / module tables. The hash is enough to confirm prompt-structure changes when debugging. The `sha256:` prefix lets a future reader distinguish hashes from legacy full-prompt rows at a glance.

**Note:** The DB column is still named `system_prompt` (renaming requires a separate migration). Old rows retain the full prompt and can be migrated separately.

---

## F-9 — Cache invalidation on every successful write

**Severity:** P2 (stale-prompt risk after capture)
**Files:**
- New `app/Services/AI/AdvicePromptCacheInvalidator.php` — `forUser(int $userId): void` forgets `ai_existing_records_{userId}` and every `ai_financial_context_{userId}_{primary}` variant.
- `app/Agents/CoordinatingAgent.php::appendAuditCompletion` — calls `AdvicePromptCacheInvalidator::forUser($user->id)` whenever a tool with `operation === 'write'` succeeds.

**Why:** Pre-fix sequence: user creates a record via inline-capture → asks an advice question 30 seconds later → the prompt's `existing_records` cache (60s TTL) and `financial_context` cache (120s TTL) still showed the pre-capture state. Could lead to "you don't have an ISA" or duplicate-create attempts. Now the next advice turn always sees the latest state. Cache forget failures are caught and logged (chain stays forensic, not load-bearing).

---

## F-10 — Engine-level fallback flipped to `factual`

**Severity:** P2 (cost — unmapped queries no longer burn the most expensive path)
**File:** `app/Services/AI/AdviceFyn.php` — `engineCallLevelFor(?string $primary)` default changed from `'holistic'` to `'factual'`.

**Why:** Pre-fix, an unmapped primary classification ran the full `orchestrateAnalysis` path (most expensive). The strict variant `engineCallLevel($queryType)` (used in tests) still throws on unmapped types so exhaustive coverage is enforced; this lenient variant now defaults to the cheapest path because an unmapped primary is more likely a low-signal non-advice message than a holistic-health query.

---

## F-11 — Duplicate-check guard in onboarding asset_capture

**Severity:** P2 (mid-onboarding duplicate creates)
**File:** `app/Services/Onboarding/OnboardingChatDirector.php` — constructor injects `RecordDuplicateChecker`. `handleAssetCaptureTurn` now maps the focus to an entity_type the checker recognises (savings/budgeting → savings_account, investment → investment_account, retirement → pension, protection → protection_policy, goals → goal); calls `alreadyExists` BEFORE delegating to the LLM. If every entity in the user's message already exists, emit "Already on file — nothing to add there." + `done` and skip the LLM. estate / business / savetax fall through to the LLM (no checker mapping for those — handler-level idempotency remains the floor).

**Why:** During multi-turn onboarding (especially the SaveTax 6–8 turn flow), the user may re-mention records they already described in an earlier turn. The known_facts block reduces re-asking but the LLM is not perfectly disciplined at temperature 0.7. Mirrors the de-dup defence the advice path has had since S0.x.

---

## F-12 — Eval bypass paired with X-Eval-Run-Id header

**Severity:** P2 (defence-in-depth on eval bypass)
**Files:**
- New `app/Services/Eval/EvalBypassGate.php` — `EvalBypassGate::isActive(?User $user)` returns true only when (a) the active Sanctum token has the `bypass-preview-mode` ability AND (b) the request carries an `X-Eval-Run-Id` header with a non-empty value.
- `app/Traits/HasAiChat.php` — preview-mode tool filter check uses `EvalBypassGate::isActive($user)`
- `app/Agents/CoordinatingAgent.php::executeTool` — same gate
- `app/Http/Middleware/PreviewWriteInterceptor.php` — bypass requires both ability AND the header
- `app/Services/Eval/EvalHttpDriver.php` — mints one run-id per `evaluate()` call (`'eval-' . bin2hex(random_bytes(8))`) and adds the header to every authenticated request (create conversation, send message, logout).

**Why:** Pre-fix, a Sanctum token with `bypass-preview-mode` ability alone was enough to bypass preview filtering and write interception. A leaked or misconfigured token = preview-data corruption risk. The header pairing means a stolen token alone is no longer sufficient — the access pattern is also more visible in logs (the run-id surfaces in nginx access logs and is easy to alert on).

---

## F-13 — Single retry on transient LLM errors

**Severity:** P2 (UX — masks 429/529 from users)
**File:** `app/Traits/HasAiChat.php` — catch-block now classifies the exception via new `isRetriableLlmError(\Throwable $e): bool` helper. On retriable error AND no partial output yet (`$toolCallCount === 0 && $fullResponse === ''`), sleep 1.5s and `continue` the loop. `$turnRetried` flag prevents re-retry within the same turn.

**Retry policy:**
- **Retry:** 429 / 529 / `rate_limit` / `overloaded` / `capacity` / `timeout` / `connection` / `service_unavailable` / `temporarily`
- **Do NOT retry:** auth / `api_key` / 401 / 403 / `invalid_request` / `context_length` / `max_tokens` / `tool_use_id`

**Why:** Anthropic 529 (overloaded) and rate-limit 429 are common in production. A 1.5-second backoff masks most of them. Mid-turn failures after tool calls have run are NOT retried — would re-execute completed tool work.

---

## F-14 — Skip Layer 5/6 on factual queries

**Severity:** P2 (cost)
**File:** `app/Services/AI/AdvicePromptBuilder.php` — `<financial_context>` and `<existing_records>` layers gated on `engineCallLevelFor($primary) !== 'factual'`. BILLING / NAVIGATION / DATA_ENTRY / OUT_OF_REMIT / INCOME / GENERAL queries skip both layers entirely.

**Why:** Pre-fix, "where's my invoice?" carried the user's full financial position (~500 tokens) into the LLM context as irrelevant noise, crowding the cache window for module + holistic queries that genuinely need it. Estimated 500–1000 input-token reduction per factual turn.

---

## F-15 — Dynamic tool-call cap by engine level

**Severity:** P2 (truncation on holistic, waste on factual)
**File:** `app/Traits/HasAiChat.php` — new `TOOL_CALL_CAPS_BY_LEVEL = ['holistic' => 8, 'module' => 5, 'factual' => 3]`. The chat loop reads the cap once per turn from `AdviceFyn::engineCallLevelFor($classification['primary'])` and uses it instead of the constant `MAX_TOOL_CALLS_PER_TURN = 5`.

**Why:** Pre-fix, holistic chats often need 5+ tool calls (orchestrate + 3 module analyses + 1 calculation) — capping at 5 truncated genuine reasoning chains and forced a tools-disabled retry. Factual queries shouldn't need 5 tool calls — burning 5 on a billing query is a sign of confusion. The default fallback (`MAX_TOOL_CALLS_PER_TURN = 5`) still applies for code paths outside AdviceFyn (onboarding asset_capture).

---

## Files changed

### Created (4)
- `app/Services/AI/AdvicePromptCacheInvalidator.php` — F-9
- `app/Services/Eval/EvalBypassGate.php` — F-12
- `tests/Unit/Services/AI/WriteIntentClassifierTest.php` — F-6 coverage
- `tests/Feature/Fyn/AdviceFynHandoffErrorTest.php` — F-1 coverage

### Modified (12)
- `app/Services/AI/AdviceFyn.php` — F-1 (wrapStream validator + handoff_error), F-10 (engine fallback)
- `app/Services/AI/AdvicePromptBuilder.php` — F-14 (skip Layer 5/6 on factual)
- `app/Services/AI/WriteIntentClassifier.php` — F-6 (interrogative guard)
- `app/Services/AI/Prompts/UserContentSanitiser.php` — F-2 (denylist)
- `app/Services/AI/Prompts/FcaProcessInstructions.php` — F-7 (delete dead method)
- `app/Services/Onboarding/OnboardingChatDirector.php` — F-11 (duplicate-check guard, constructor)
- `app/Services/Onboarding/OnboardingPromptBuilder.php` — F-5 (layer reorder)
- `app/Services/Eval/EvalHttpDriver.php` — F-12 (eval run-id header)
- `app/Traits/HasAiChat.php` — F-3 (compression), F-4 (Anthropic cache hit), F-8 (system prompt hash), F-12 (gate), F-13 (retry), F-15 (dynamic cap)
- `app/Agents/CoordinatingAgent.php` — F-9 (cache invalidation hook), F-12 (gate)
- `app/Http/Middleware/PreviewWriteInterceptor.php` — F-12 (header check)
- `resources/js/store/modules/aiChat.js` — F-1 (handoff_error handler)
- `tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php` — F-2 coverage update

---

## Verification

```
./vendor/bin/pest tests/Unit/Services/AI tests/Feature/Fyn tests/Feature/AI \
                  tests/Unit/Services/Onboarding tests/Feature/Onboarding
→ 815 passed, 1 skipped, 1 pre-existing time-flake (AdviceReviewServiceTest::annual review due,
   unrelated — subMonths(14)/diffInMonths calendar arithmetic)

./vendor/bin/pest --testsuite=Architecture
→ 95 passed (414 assertions)

./vendor/bin/pest tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php \
                  tests/Unit/Services/AI/WriteIntentClassifierTest.php \
                  tests/Feature/Fyn/AdviceFynHandoffErrorTest.php \
                  tests/Feature/Fyn/AdviceFynRoutesWritesViaHandoffTest.php \
                  tests/Feature/Fyn/AdviceFynToolListTest.php \
                  tests/Feature/Fyn/HandoffPayloadValidationTest.php \
                  tests/Feature/Fyn/HandoffInvisibilityTest.php \
                  tests/Feature/Fyn/DispatchRoutingTest.php
→ 58 passed (89 assertions) — all canonical-contract + new-fix tests green

./vendor/bin/pint --quiet  (formatting on every changed file)
→ clean
```

PHP lint (`php -l`) clean on every changed file.

---

## Cost projection — before vs after

Recomputed against the original audit's per-turn estimates (Haiku 4.5, $1/M in, $5/M out):

| Path | Before | After | Saving |
|---|---|---|---|
| Onboarding asset_capture (turn 2-N) | $0.0008 | ~$0.0003 | F-5 cache prefix |
| Advice factual (BILLING / NAVIGATION / OUT_OF_REMIT) | $0.0023 | ~$0.0014 | F-14 skip Layer 5/6 |
| Advice module-scoped | $0.0045 | ~$0.0035 | F-3 tool-result compression |
| Advice holistic | $0.012 | ~$0.0055 | F-3 tool-result compression |
| **SaveTax campaign per user** | **~2p** | **~0.8p** | **~60% reduction** |

These are floor estimates; real cost depends on conversation length and tool-call frequency.

---

## Conformance — net Sprint 0 invariants

| Before fixes | After fixes |
|---|---|
| 33/35 ✅ | **34/35 ✅** |
| INV-2.4.5 ❌ (validator existed but unused) | INV-2.4.5 ✅ (validator wired, handoff_error event live) |
| INV-2.3.5 ❌ (deferred per spec to Sprint 1) | INV-2.3.5 ❌ (still deferred — out of scope) |

INV-2.10.4 (user-content sanitisation) was already ticked in the audit but the implementation diverged from inclusivity intent. F-2 closes that gap without weakening the prompt-injection floor.

---

## Out of scope for this round

The audit also identified items deliberately left for future work:

- **System prompt column rename** — F-8 writes `sha256:` hashes into the existing `system_prompt` column. A migration to rename the column to `system_prompt_hash` + backfill old rows is a separate concern (data minimisation impact is already captured by writing hashes going forward).
- **Server-side X-Eval-Run-Id allowlist** — F-12 requires the header to be present and non-empty, but doesn't yet verify the run-id against a server-side allowlist. A future hardening step would track in-flight runs and reject unknown run-ids.
- **Anthropic cache-hit dashboard** — F-4 captures the data; rendering it in the admin UI is a follow-up.
- **Holistic batch-tools (INV-2.8.2)** — Sprint 2 deliverable.
- **`advice_response` SSE schema (INV-2.3.5)** — Sprint 1 deliverable.

---

*All audit findings closed. Source-of-truth alignment: 34/35 Sprint 0 invariants conformant.*
