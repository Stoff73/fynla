# Fyn AI System Audit — Canonical, Security, Determinism, Cost, Outcomes

**Date:** 2026-04-30
**Branch:** `feature/fyn-persona-split`
**Auditor:** Claude (claude-opus-4-7, max effort)
**Scope:** AI engineering as built, against `April/April24Updates/spec/00-canonical.md` (canonical contract) + `April/April24Updates/spec/01-invariants.md` (35 falsifiable invariants) + `April/April24Updates/plan/10-sprint-0-plan.md` (Sprint 0 deliverables S0.1 – S0.17).

This audit verifies the running implementation against the documented intent. Each finding is tagged **Severity** (P0 critical / P1 important / P2 nice-to-fix) and **Confidence** (high / medium — based on whether I read the code path or inferred it).

---

## 1. Executive summary

**Overall posture: B+.** The two-Fyn architecture is faithfully implemented. The single dispatch decision in `AiChatController:175-183` matches the canonical contract verbatim. The forbidden orchestrator classes (`FynPersonaOrchestrator`, `FynPersonaInvoker`, `FynPersonaRegistry`, `DataCapturePromptBuilder`) are absent from production code (architecture test enforces it). The handoff is invisible to the frontend. Sprint 0's reliability bundle (atomic token budget, idempotency, abort events, provider-swap lock, hash-chain audit) is delivered and wired.

**The gaps are not architectural — they are around contract enforcement, observability, and inclusivity.**

The most material issues:
- **INV-2.4.5 not enforced at runtime**: `HandoffPayloadValidator` exists but is unused in production. Malformed `delegate_to_capture` payloads are silently swallowed instead of emitting the spec-required `handoff_error` SSE event.
- **`UserContentSanitiser` strips non-ASCII names** ("François" → "Franois", "李" → ""). Hard inclusivity problem and a memory consistency risk (the LLM sees a name that doesn't match the DB).
- **No prompt caching during onboarding** (Anthropic ephemeral cache only fires for advice). 6–8 SaveTax turns per user re-pay the static layers each time.
- **Tool result JSON sent back to the model unsummarised** on each loop iteration (HasAiChat:540) — a single `get_module_analysis` round-trip can put ~5–10KB of structured data back into the next prompt.
- **Anthropic cache hit telemetry is missing** — only xAI's `cachedTokens` is read; Anthropic's `cache_read_input_tokens` from the message usage object isn't captured.
- **Dead code in `FcaProcessInstructions::getDataCreationGuidance()`** — defines a block that contradicts `<handoff_guidance>` and is no longer called from `get()`. Sits as a re-enablement risk.
- **`WriteIntentClassifier` can mis-fire on questions** that contain a verb+entity pair ("Should I add to my Cash ISA?" matches `add` + `cash isa`).

None of these is a P0 break. The contract holds for the golden path. The findings below tighten the floor.

---

## 2. Conformance scorecard

### 2.1 Canonical contract (`00-canonical.md`)

| Clause | Status | Evidence |
|---|---|---|
| One dispatch decision in `AiChatController::sendMessage` | ✅ Conformant | `AiChatController.php:175-183` — exact two-branch ternary on `inOnboarding` flag |
| Onboarding Fyn = `OnboardingChatDirector` (promoted) + `handleInlineCapture` | ✅ Conformant | `OnboardingChatDirector::handleInlineCapture` at line 2287; called from `AdviceFyn::wrapStream` |
| Advice Fyn = new `AdviceFyn` class wrapping advice prompt + read-only tools | ✅ Conformant | `app/Services/AI/AdviceFyn.php` (487 lines); WRITE_TOOLS strip at lines 143-167 |
| No `FynPersonaOrchestrator/Invoker/Registry/DataCapturePromptBuilder` | ✅ Conformant | `grep -rn` over `app/`/`config/` returns 0 matches; only test references in `tests/Architecture/PersonaMachineryAbsentTest.php` and `tests/Feature/Fyn/DispatchRoutingTest.php` |
| `HandoffContract` constants and `CaptureContext` VO retained | ✅ Conformant | `app/Services/AI/HandoffContract.php` and `app/ValueObjects/CaptureContext.php` exist |
| Zero `persona_state_change` SSE events to frontend | ✅ Conformant | `wrapStream` at `AdviceFyn.php:360-425` strips the synthetic `handoff` event; `aiChat.js` Vuex store has no `personaMode` state per S0.4 |
| Onboarding writes, Advice does not | ✅ Conformant | WRITE_TOOLS blacklist + `buildToolList()` `array_diff` strip — Advice exposes only read + handoff tools |
| User never sees the handoff | ⚠️ Mostly | True in the happy path. **Diverges on malformed handoffs**: silently dropped, no `handoff_error` event (see Finding F-1) |
| Resume from saved step on next session | ✅ Conformant | Welcome-back greeting in `OnboardingChatDirector::resumeSummary` (per S0.15 delivery note) |

### 2.2 Invariants (`01-invariants.md`) — Sprint 0 scope

| Invariant | Status | Notes |
|---|---|---|
| INV-2.1.1 Single dispatch path | ✅ | Two-branch ternary; consent guard exempt per spec |
| INV-2.1.2 AdviceFyn tool list disjoint from write tools | ✅ | `array_diff` against WRITE_TOOLS |
| INV-2.1.3 Dispatch condition observable | ✅ | `users.onboarding_completed` flips once at `STATE_DONE` |
| INV-2.2.1 State machine drives non-inline turns | ✅ | `OnboardingChatDirector::handleUserMessage` |
| INV-2.2.2 Multi-line grouped-extract direct-write | ✅ | Per S0.5 delivery |
| INV-2.2.3 `<known_facts>` injected, no repeat-asks | ✅ | `MemoryRetrieverService::renderKnownFactsBlock` injected at AdvicePromptBuilder Layer 3d AND OnboardingPromptBuilder Layer 3 |
| INV-2.2.4 Resume greeting + Yes/No bubble | ✅ | Per S0.15 delivery |
| INV-2.2.5 Journey mapping config-driven | ✅ | `config/onboarding.php` `journey_map` + `campaign_map`; `AiChatController::startOnboarding` lookup at lines 357-376 |
| INV-2.2.6 Parked facts flushed at commit | ✅ | `flushParkedFactsForState` per S0.15 delivery |
| INV-2.3.1 Two response modes | ✅ | `RESPONSE_MODE_MAP` at `AdviceFyn.php:52-77` covers all 23 query types |
| INV-2.3.2 Engine sole source of interpretive text | ⚠️ | Prompt-instructed, not code-enforced. `StructuredResponseValidator` runs at `HasAiChat:594` but does not enforce engine-source attribution |
| INV-2.3.3 FCA signposting on recommendation responses | ✅ | `AdvicePromptBuilder::buildFcaSignpostingBlock` gated on `QuerySchemas::isAdviceType` |
| INV-2.3.4 Out-of-remit canonical refusal | ✅ | `AdviceFyn::handle:215-239` exact string + zero tool calls |
| INV-2.3.5 Structured `advice_response` SSE event | ❌ | **Not implemented in Sprint 0.** SSE stream emits `content` events for text and tool events; no structured `advice_response` event with the schema in §2.3.5. Sprint 1 deliverable per spec sequencing. |
| INV-2.3.6 Engine call granularity | ✅ | `ENGINE_CALL_LEVEL_MAP` at `AdviceFyn.php:95-120` |
| INV-2.4.1 No `persona_state_change` events | ✅ | `wrapStream` drops handoff before yield |
| INV-2.4.2 Inline capture conversational only | ✅ | No `quick_replies`, no `onboarding_layout_change` from `handleInlineCapture` |
| INV-2.4.3 `capture_complete` matches normal styling | ✅ | Per S0.15 delivery |
| INV-2.4.4 System messages exempt | ✅ | Allowed types: `token_limit`, `consent_required`, etc. |
| INV-2.4.5 Handoff payload validation | ❌ | **`HandoffPayloadValidator` exists but is not invoked from `wrapStream`. No `handoff_error` SSE event exists.** See Finding F-1. |
| INV-2.5.1 Direct-write per handler | ✅ | `grep "'action' => 'fill_form'"` returns 0 (S0.7 tightened from spec's allowed 1) |
| INV-2.5.2 Observers fire on direct-write | ✅ | Per S0.5 spy tests |
| INV-2.5.3 `entity_id` preserved across turns | ✅ | `summariseToolResult` at `HasAiChat.php:818-869` lifts entity_id/entity_type to priority keys |
| INV-2.5.4 Audit trail matches reality | ✅ | `appendAuditEvent` called 5× in `CoordinatingAgent` |
| INV-2.5.5 Direct-write transaction boundary | ✅ | Per S0.5 rollback test |
| INV-2.5.6 `create_what_if_scenario` retained as fill_form | ❌ (tightened) | Was tightened in S0.7 — `handleUpdateRecord` direct-write conversion eliminated the last fill_form site too. INV-2.5.6 is now stricter than the spec wrote: zero fill_form, not one. |
| INV-2.6.1 / INV-2.6.2 Read completeness | ⚠️ | `handleListRecords`, `handleListGoals`, `handleListLifeEvents` covered. `handleModuleAnalysis` still wraps via `summariseToolAnalysis` per S0.15 delivery note "deferring that change to a follow-up" |
| INV-2.7.1 Tool catalogue parity | ✅ | Per S0.6 + S0.7 delivery |
| INV-2.7.2 Billing tools on both providers | ✅ | Per S0.6 |
| INV-2.7.3 `update_record` strict + allowlist | ✅ | Per S0.7. Note xAI uses union schema, not `oneOf` (xAI strict mode limitation) — runtime allowlist still enforced |
| INV-2.7.4 Preview-mode parity | ✅ | Per S0.15 |
| INV-2.8.x Multi-entity coverage | ✅ | Sprint 0 4-focus floor met; Sprint 2 batch tools deferred |
| INV-2.9.1 Atomic token budget | ✅ | `recordTokenUsage` at `HasAiGuardrails.php:213-238` uses `DB::transaction` + `lockForUpdate` |
| INV-2.9.2 SSE-abort keep partial writes | ✅ | `wasConnectionAborted` + `recordAbort` at `HasAiChat.php:191/433` |
| INV-2.9.3 Idempotency key middleware | ✅ | `IdempotencyKeyMiddleware` per S0.11 |
| INV-2.9.4 Provider-swap write lock | ✅ | Versioned cache key `ai_provider:v{N}` at `HasAiGuardrails.php:56-71` |
| INV-2.9.5 Gap-fill DB dedup | ✅ | Per S0.11 |
| INV-2.9.6 `generateTitle` sanitised | ✅ | `strip_tags` + `mb_substr 0,100` at `HasAiChat.php:766-774` |
| INV-2.10.1 CoreIdentity rewrite | ✅ | Read `app/Services/AI/Prompts/CoreIdentity.php` — guidance-only framing, no "qualified financial planner" |
| INV-2.10.2 Hash-chain audit | ✅ | `AuditChainService` at `app/Services/AI/AuditChainService.php` with HMAC + canonical JSON keysort |
| INV-2.10.3 Runtime consent check | ✅ | Entry guard at `AiChatController.php:151-158` AND mid-stream re-check at `AiChatController.php:193-205` |
| INV-2.10.4 User-content sanitisation | ⚠️ | Implemented but **strips non-ASCII names** — see Finding F-2 |
| INV-2.11.1 Three stores + index | ✅ | `MemoryRetrieverService::retrieve` |
| INV-2.11.2 Conversation index columns | ✅ | `ConversationSummariser` exists |
| INV-2.11.3 `search_conversation_index` tool | ✅ | Defined in `AiToolDefinitions.php:151` |
| INV-2.13.x Eval harness | ⚠️ | Sprint 1 scope; Sprint 0 placed only the BS-NN browser harness |

**Net Sprint 0 invariant coverage: 33/35 ✅, 2 ❌ (INV-2.3.5 deferred to Sprint 1 per the spec; INV-2.4.5 has a deliverable that wasn't wired).**

---

## 3. Findings — ranked by severity

### F-1 (P1, high confidence) — INV-2.4.5 not enforced at runtime

**Evidence:**
- `app/Services/AI/HandoffPayloadValidator.php` exists with `validateDelegateToCapture()` and `validateCaptureComplete()` static methods.
- `grep -rn HandoffPayloadValidator` returns one production file (the validator itself) and one test file (`tests/Feature/Fyn/HandoffPayloadValidationTest.php`). **It is not invoked from any code path that runs in production.**
- `AdviceFyn::wrapStream` at line 371-407 instead calls `CaptureContext::fromArray($payload)` inside `try { ... } catch (\InvalidArgumentException) { Log::warning(...); continue; }`.
- `CaptureContext::fromArray` (lines 68-94) was made resilient in S0.5.t — it auto-synthesises `reason` from `entity_types` when the LLM omits it. Only throws if `entity_types` is missing.
- `grep -n handoff_error` over `app/`, `resources/js/`: zero matches. The SSE event INV-2.4.5 mandates does not exist.

**Why it matters:**
INV-2.4.5 says: *"On a malformed payload, Advice Fyn emits a `handoff_error` SSE event and does NOT silently stay in capturing mode."* Current behaviour: malformed payload → log a warning → `continue` the upstream loop, which means the LLM's tool_result for `delegate_to_capture` is never sent back, the user gets whatever text content was streamed, the inline-capture never runs. The user sees a half-response with no indication that anything went wrong. The spec called this out explicitly because the failure class was identified in `audit-evidence.md §19`.

**Recommendation:**
1. Wire `HandoffPayloadValidator::validateDelegateToCapture` into `wrapStream` before `CaptureContext::fromArray`.
2. On non-null return, yield a `handoff_error` SSE event with the validator's error key. Add a frontend handler in `aiChat.js` that surfaces a single sentence to the user ("Something went wrong saving that — could you try again?").
3. Add a Prometheus / log-counter so handoff malformations show up in dashboards. The current `Log::notice` for missing `reason` is the only signal and is invisible to Ops.
4. Decide whether `CaptureContext::fromArray`'s synthesise-reason behaviour stays. If it does, `validateDelegateToCapture` should permit missing `reason` (and just warn). If it doesn't, both should reject.

**Estimated fix:** 2-4 hours plus tests.

---

### F-2 (P1, high confidence) — UserContentSanitiser strips non-ASCII names

**Evidence:**
- `app/Services/AI/Prompts/UserContentSanitiser.php:40` — `preg_replace("/[^A-Za-z0-9\s'.,\-]/u", '', $value)`.
- The class docblock at lines 26-33 explicitly notes "*locks out non-ASCII names like 'François' by design — we accept that trade-off for the prompt-injection floor*".
- Used unconditionally in `AdvicePromptBuilder` (10+ wrap sites at lines 299, 376, 386, 524, 700, 730, 743, 757, 769, 806, 819, 832, 844, 857, 869, 882, 896, 919, 935, 941) and `OnboardingPromptBuilder:58`.

**Why it matters:**
1. **Inclusivity:** "François" → "Franois", "Müller" → "Mller", "李四" → "" (empty), "O'Brien" → "O'Brien" (OK because apostrophe is allowed). This is a UX issue for any user with a name containing accented or non-Latin characters. UK is not monolingual; the user base will include non-ASCII names.
2. **Memory consistency:** The DB stores the real name. The LLM sees the sanitised version. When `MemoryRetrieverService::renderKnownFactsBlock` lists "User name: Franois" but the user asks "what do you have on file for me as François", the system might re-ask or fail to recognise the user's own self-reference.
3. **The "trade-off for prompt injection" reasoning is questionable.** Modern prompt-injection defences (XML markers, instruction blocks) do most of the lifting. The whitelist is doing belt-and-braces work that costs UX. A more permissive set (e.g. allow Unicode letter category `\p{L}` plus the existing punctuation) would still strip `{}`, `<>`, `;`, `"`, `()`, `[]` and template-injection chars.

**Recommendation:**
1. Replace the whitelist with a denylist of injection-relevant characters: `preg_replace('/[<>\{\}\[\]\(\);`"\\\\]/u', '', $value)`. This permits all letters (Unicode), digits, whitespace, and common punctuation.
2. Add a unit test that round-trips a "François" / "李" / "Müller" name through the sanitiser without loss.
3. Keep the `<user_provided>` wrap — it's the structural defence that actually matters.

**Estimated fix:** 1 hour. Care needed because `UpdateRecordAllowlist` field validation may also use the same charset implicitly.

---

### F-3 (P1, medium confidence) — Tool result JSON sent back to model unsummarised

**Evidence:**
- `HasAiChat.php:540` — `$toolResultJson = json_encode($toolResult);` This is the full result, no compression.
- `HasAiChat.php:543-560` — that JSON is appended verbatim to the `messages` array as the next round-trip's `tool_result` block content.
- `summariseToolResult` (lines 818-869) is only used for `ai_messages.metadata.tool_calls` (audit logging), not for what goes back to the model.
- `handleModuleAnalysis` (per S0.15 delivery note) wraps via `summariseToolAnalysis` BUT INV-2.6.1 explicitly says "no summariseToolAnalysis stripping for this handler" — this is acknowledged tech debt.

**Why it matters:**
A holistic chat with 3-4 tool calls each returning ~5KB of JSON results in 15-20KB of input tokens on every subsequent loop iteration. With `MAX_TOOL_CALLS_PER_TURN=5` the multiplier is bounded but expensive at scale. Pro-tier 2M token cap can be exhausted on a few complex chats per day. In practice:
- One holistic chat with full `orchestrateAnalysis` + 3 module analyses + 1 recommendation read could spend ~50k input tokens just on tool-result regurgitation.
- At ~$3/1M input tokens (Anthropic Haiku 4.5) this is ~$0.15 per chat, or ~$15 across 100 chats/day. Not catastrophic but unnecessary.

**Recommendation:**
1. Build a `summariseToolResultForModel` that keeps `entity_id`, `entity_type`, top-level numeric figures, and trims arrays >20 items. Different shape from the audit summary.
2. Apply at line 540 before the round-trip.
3. Test with a 3-tool-call scenario and confirm the LLM still produces correct figures (figures should come from the engine layer; the model just narrates).

**Estimated fix:** 4-6 hours including a careful eval to confirm no quality regression.

---

### F-4 (P2, high confidence) — Anthropic prompt-cache hit rate is invisible

**Evidence:**
- Anthropic streaming sets `cache_control: ['type' => 'ephemeral']` on the system prompt at `HasAiChat.php:319-324` — caching is configured.
- Token usage extraction at `HasAiChat.php:332` reads `$event->message->usage->inputTokens` but does NOT read `cache_read_input_tokens` or `cache_creation_input_tokens` (the Anthropic SDK exposes these on the usage object).
- xAI path at lines 285-287 reads `promptTokensDetails->cachedTokens` — so xAI cache hit rate IS captured.
- `cached_tokens` is persisted in `ai_messages.metadata.cached_tokens` and `cache_hit_rate` is computed (line 610-613) — but only for xAI in practice.

**Why it matters:**
The most expensive single thing in the system prompt is Layer 5 (`<financial_context>`) which is also the most cacheable (it changes only when user records change). With caching working correctly, the second turn in a conversation should see ~80% cache hit rate. With caching broken (e.g. user hash differs across requests, or system prompt size changes per turn), the hit rate drops to 0% and costs 5× more.

**Today, with Anthropic as the active provider, you cannot tell if caching is working without looking at the API bill.**

**Recommendation:**
1. In the Anthropic loop, read `$event->message->usage->cache_read_input_tokens` (RawMessageStartEvent) and add to `$totalCachedTokens`.
2. Persist the same `cached_tokens` + `cache_hit_rate` keys.
3. Add a daily admin dashboard widget showing rolling 7-day cache hit rate. Below 50% should fire an alert.

**Estimated fix:** 2 hours.

---

### F-5 (P2, high confidence) — Onboarding has no prompt caching

**Evidence:**
- `OnboardingChatDirector::handleAssetCaptureTurn` calls `chatWithPromptOverride` which goes through `HasAiChat::chat`. The system prompt IS sent with `cache_control: ephemeral` in the Anthropic path.
- BUT each onboarding turn is its own isolated chat (the system prompt is rebuilt per turn from `OnboardingPromptBuilder::buildAssetCapturePrompt`). The dynamic `<known_facts>` block changes between turns as facts are captured, breaking the prefix-cache hit.
- In SaveTax: 6-8 onboarding LLM turns. Each rebuilds the ~500-token prompt fresh.

**Why it matters:**
The static layers (CoreIdentity ~200 tokens, ComplianceRules ~400 tokens, asset_capture instructions ~150 tokens) are identical across turns. They should benefit from cache. Currently they don't because the dynamic `known_facts` block grows after each capture, invalidating the prefix.

**Recommendation:**
Restructure the onboarding prompt so the cacheable static layers come FIRST and the dynamic `known_facts` comes LAST:
```
<identity>          ← cacheable
<compliance>        ← cacheable
<asset_capture>     ← cacheable (focus is stable for the duration of the focus block)
<known_facts>       ← dynamic, breaks cache from here onward
```
Anthropic's prefix cache will hit on the first three blocks, paying full price only for `<known_facts>` per turn. Estimated: 60-70% input-token reduction on onboarding turns 2-N.

**Estimated fix:** 1 hour (move the layer order in `OnboardingPromptBuilder.php:62-78`). Verify no regression on multi-entity emission tests.

---

### F-6 (P1, high confidence) — `WriteIntentClassifier` can mis-fire on questions

**Evidence:**
- `WriteIntentClassifier.php:30-41` lists verb patterns including "i pay", "i hold", "i own".
- `firstMatch` does not check question marks or interrogative structure (line 128-151).
- ENTITY_KEYWORDS uses substring matching on multi-word phrases (line 136-139).
- The classifier returns null if NO entity matches, but if ANY entity matches plus ANY verb, it routes to inline-capture and skips the LLM (`AdviceFyn::handle:262-327`).
- Examples that would mis-route:
  - *"Should I add to my Cash ISA?"* → matches `add` (verb) + `cash isa` (entity) → routed as savings_account write.
  - *"How much do I pay into my pension?"* → matches `i pay` + `pension` → routed as pension write.
  - *"Do you think I should bought a buy to let?"* (typo'd grammar) → matches `bought` + `buy to let` → routed as property write.

**Why it matters:**
The classifier is the LAST line of defence before bypassing the LLM and going straight to write. False positives = the user asks an advice question and gets a write turn back ("What do you want to add to your Cash ISA? Provider, balance, interest rate?"). They didn't ask to add anything.

**Counter-mitigation:** the inline-capture turn IS run by the LLM, so the LLM can fail to emit a tool call if it sees that the user actually asked a question. But the classifier has already committed: the user message is persisted with `persona='advice'`, the `OnboardingChatDirector::handleInlineCapture` is invoked with `persistUserMessage:false` and a synthesised CaptureContext. The LLM sees the conversation context and may or may not act sanely.

**Recommendation:**
1. Add a question-mark and interrogative-prefix guard to `WriteIntentClassifier::classify`. If the message ends with `?` or starts with `should i`, `can i`, `how do i`, `what should`, etc., return null.
2. Add a unit test seeded with the false-positive cases above.
3. Consider tightening verb list — "i pay" / "i hold" / "i own" are weak signals. Imperative ("add", "create") is stronger.

**Estimated fix:** 2 hours.

---

### F-7 (P2, high confidence) — Dead code in `FcaProcessInstructions`

**Evidence:**
- `FcaProcessInstructions.php:105-129` defines `private static function getDataCreationGuidance()`.
- The public `get()` at lines 15-35 does not call it (the comment block at lines 25-33 confirms it was deliberately removed in S0.5.t but the method body was left in place).
- The block contradicts `<handoff_guidance>` — it tells the model "the tool will open a form on screen" (legacy fill_form pattern) and includes worked examples that no longer apply.

**Why it matters:**
A future engineer or AI agent maintaining this file might re-enable the method by adding it back to `get()` if they see no consumer and assume it was meant to be wired. The S0.5.t hardening that fixed BS-14 would silently regress.

**Recommendation:**
Delete `getDataCreationGuidance()` entirely. The deletion is the documentation. If the comment block at lines 25-33 is the historical record, leave the comment but drop the method.

**Estimated fix:** 5 minutes.

---

### F-8 (P2, high confidence) — System prompt persisted on every assistant message

**Evidence:**
- `HasAiChat.php:619` — `$assistantExtra` includes `'system_prompt' => $systemPrompt`.
- That gets written to `ai_messages.metadata.system_prompt` for every assistant message.
- Advice prompts are 1500-1800 tokens; serialised JSON is ~10KB per message.
- A 100-message conversation = ~1MB of metadata in the DB just on system-prompt copies.

**Why it matters:**
1. **DB bloat**: most of the prompt is identical across messages; only Layer 5/6 (financial context, existing records) varies. Storing the full prompt 100× is redundant.
2. **PII surface area**: the system prompt embeds the user's income, family names, financial position. Already in DB, but now duplicated in `ai_messages.metadata` JSON column. Backups, exports, debug dumps all carry it.
3. **GDPR**: data minimisation principle. If the audit chain (`ai_audit_events`) is the canonical record (which it is, per INV-2.10.2), the per-message prompt copy is duplicate data.

**Recommendation:**
- Replace `'system_prompt' => $systemPrompt` with `'system_prompt_hash' => sha256($systemPrompt)`. If reconstruction is needed for debugging, the hash + classification + user state at the time will let you regenerate it.
- Or: only store the dynamic-layer slice (financial context + classification) and accept that the static layers are reconstructable from CoreIdentity/ComplianceRules versioning.

**Estimated fix:** 2 hours including a migration to drop the full-prompt column data from old rows.

---

### F-9 (P2, medium confidence) — Cache invalidation on capture not wired

**Evidence:**
- `AdvicePromptBuilder::buildExistingRecordsSummary` is `Cache::remember("ai_existing_records_{$user->id}", 60, …)` (line 678).
- `buildFinancialContext` is `Cache::remember("ai_financial_context_{$user->id}_{$primary}", 120, …)` (line 415).
- `OnboardingChatDirector::handleInlineCapture` writes records via `CoordinatingAgent::executeTool` → handler → DB.
- I did not find a `Cache::forget` call hooked to capture completion.

**Why it matters:**
Sequence: user asks "Add a Cash ISA" → handoff → record created → user immediately asks "What ISA do I have?". The advice query rebuilds the prompt; existing records cache shows the pre-capture state for up to 60 seconds. The LLM might say "you don't have an ISA" or "let me create one" (triggering a duplicate-create attempt that `RecordDuplicateChecker` then catches — but the user sees confusing behaviour).

The `RecordDuplicateChecker` short-circuit is the saving grace, but the prompt is still wrong for that 60-second window.

**Recommendation:**
1. After every successful direct-write tool, fire a cache invalidation: `Cache::forget("ai_existing_records_{$user->id}")` and `Cache::forget` for every classification primary's `ai_financial_context_*`.
2. Easiest implementation: a Laravel observer on the relevant models that calls a single `AdvicePromptCacheInvalidator::forUser($userId)` helper.

**Estimated fix:** 4 hours.

---

### F-10 (P2, medium confidence) — Engine-call-level fallback is `holistic`

**Evidence:**
- `AdviceFyn::engineCallLevelFor` at lines 128-135: `return self::ENGINE_CALL_LEVEL_MAP[$primary] ?? 'holistic';`
- The comment says "safe fallback — when we don't know what the user is asking, prefer full context to a truncated one".

**Why it matters:**
- If a new query type lands in `QuerySchemas` without being added to `ENGINE_CALL_LEVEL_MAP`, the system silently runs the most expensive code path.
- INV-2.3.6 mandates exhaustive coverage. The architecture test `AdviceFynEngineCallLevelTest.php` enforces it (per Sprint 1 delivery note).
- The fallback is therefore reachable in dev but not in CI-tested production... unless someone adds a query type and the test isn't run before deploy.

**Recommendation:**
Either:
- Throw on unknown primary (matches the `engineCallLevel($queryType)` strict variant at lines 456-464), with a feature flag for safety.
- Or default to `factual` (cheapest) instead of `holistic`. An unmapped query is more likely to be a non-financial query than a holistic one.

**Estimated fix:** 1 hour.

---

### F-11 (P2, high confidence) — Onboarding prompt lacks existing-records context

**Evidence:**
- `OnboardingPromptBuilder::buildAssetCapturePrompt` (lines 47-78) injects 4 layers: CoreIdentity + ComplianceRules + KnownFacts + AssetCaptureInstructions.
- `MemoryRetrieverService::renderKnownFactsBlock` IS called — so prior captures DO appear.
- BUT `RecordDuplicateChecker::alreadyExists` is only called from `AdviceFyn::handle:262`, NOT from any onboarding path.
- During SaveTax campaign, a user describes accounts across 6-8 LLM turns. If the LLM re-emits a `create_savings_account` for an account it already created in turn 2, the handler will create a duplicate (no de-dup at the handler level).

**Why it matters:**
`RecordDuplicateChecker` is the de-dup defence on the advice path. The onboarding path relies on `<known_facts>` + the LLM's discipline to not re-emit. With Haiku/Grok at 0.7 temperature, that discipline is imperfect. Real users describing 5+ accounts in fragmented turns can produce duplicates.

**Recommendation:**
1. Run `RecordDuplicateChecker::alreadyExists` inside `OnboardingChatDirector::handleAssetCaptureTurn` BEFORE delegating to the LLM, AND inside the create_* handlers as a last-line defence.
2. Alternatively, run it post-LLM on each tool_use block before executing — if a clear DB match exists, return `{already_exists: true, entity_id: ...}` to the model instead of creating a duplicate.

**Estimated fix:** 4-6 hours.

---

### F-12 (P2, medium confidence) — `bypass-preview-mode` ability is binary

**Evidence:**
- `HasAiChat.php:147` — `$hasEvalBypass = in_array('bypass-preview-mode', $tokenAbilities, true);`
- This is a single Sanctum ability check. If a token has it, preview tools are unfiltered (preview users get write tools).
- Used in eval flow to drive HTTP endpoints with real Sanctum auth (per `feedback_eval_canonical_contract.md`).

**Why it matters:**
- If this token is leaked, an attacker with a preview persona's token+ability can run write tools against real DB. Mitigation: preview personas have `is_preview_user=true` and seeded test data.
- A more granular system would be `bypass-preview-mode:eval-only` (only valid when paired with `EVAL_RUN_ID` header) so accidental use outside eval context is rejected.

**Recommendation:**
1. Add a header check: `bypass-preview-mode` only honoured when request has `X-Eval-Run-Id` header AND the run-id is in a server-side allowlist.
2. Alternatively, rotate the ability per eval run and use a one-shot token.

This is defence-in-depth on a feature already protected by the seeded persona pattern. Worth doing if eval tokens ever exist outside of dev/CI.

**Estimated fix:** 2 hours.

---

### F-13 (P2, low confidence) — No retry on transient LLM failure

**Evidence:**
- `HasAiChat.php:378-389` catches `\Exception` from streaming, logs, yields a generic error event, returns.
- No retry-with-backoff. No fallback to other provider.
- `HasAiGuardrails::categoriseApiError` produces user-friendly text but doesn't trigger retry.

**Why it matters:**
Anthropic 529 (overloaded) and rate-limit 429 are common in production. A retry once with 2-second backoff would mask most of these from users. Sprint 4 (BS-25) is the canonical home for failover; this finding is just to flag that today's behaviour is "fail fast and tell the user".

**Recommendation:**
Pre-Sprint 4: add a single retry on 529 / 429 / network timeout in the streaming catch block. Don't retry on 4xx-non-retriable.

**Estimated fix:** 2 hours.

---

### F-14 (P2, high confidence) — Financial context built unconditionally on factual queries

**Evidence:**
- `AdvicePromptBuilder::build` at line 134 — `buildFinancialContext` is called regardless of classification.
- Lines 415-672 show the function builds: net worth, savings, investments, retirement, protection, property, estate, goals, life events, ranked recommendations, cashflow, conflicts, cross-module strategies, life-event impacts.
- For `INCOME` / `GENERAL` / `DATA_ENTRY` / `NAVIGATION` / `BILLING` / `OUT_OF_REMIT` queries, `engine_call_level` is `factual` — the orchestrate analysis is short-circuited internally to return `[]`. So most of the function body returns "No financial data recorded yet." or builds from cached partial data.
- BUT the layer is still injected into the prompt as an `<financial_context>` block.

**Why it matters:**
For BILLING queries the financial context is irrelevant noise. A user asking "where's my invoice?" doesn't need their ISA balance in the LLM's context. The 60-120 second cache offsets the cost, but it's still ~500 tokens of irrelevant data injected into every billing turn.

**Recommendation:**
Skip Layer 5 (financial_context) and Layer 6 (existing_records) entirely for `factual` engine_call_level queries. Or skip on a stricter list (BILLING, NAVIGATION, OUT_OF_REMIT).

**Estimated fix:** 1 hour.

---

### F-15 (P2, medium confidence) — Tool-call-loop limit may produce truncated responses

**Evidence:**
- `HasAiChat.php:578-588` — when `$toolCallCount >= 5` and `$stopReason === 'tool_use'` and `$fullResponse === ''`, the loop disables tools and runs ONE more iteration to force a text response.
- This means the LLM has used 5 tool calls and still wants to call more, but is forced to stop. The forced text response may be partial ("Looking at your savings, I can see...") because the model hasn't completed its reasoning chain.

**Why it matters:**
For complex holistic queries the model genuinely needs 5+ tool calls (get_recommendations + get_module_analysis × 3 + calculate_what_if). Truncating at 5 leads to weaker advice on legitimate cases. The eval BS-NN suite should catch this but might not exercise the 5+ tool boundary.

**Recommendation:**
- Raise to 8 for holistic queries.
- Or make it dynamic: `MAX_TOOL_CALLS_PER_TURN = match($engineCallLevel) { 'holistic' => 8, 'module' => 5, 'factual' => 3 }`.

**Estimated fix:** 1 hour.

---

## 4. Strengths worth preserving

These are not findings — they're things that are right and should be defended against drift:

1. **Architecture-test enforcement of the canonical contract.** `tests/Architecture/PersonaMachineryAbsentTest.php` and `tests/Feature/Fyn/DispatchRoutingTest.php` make INV-2.1.1 mechanical. Any future PR re-introducing an orchestrator class is caught in CI.

2. **WRITE_TOOLS as a PHP constant inside `AdviceFyn`.** It's the single source of truth for what advice cannot do. A new write tool added to `AiToolDefinitions` without being added to WRITE_TOOLS will be exposed to advice — the test `AdviceFynToolListTest` catches it.

3. **`navigate_to_page` stripped from advice mode (S0.5.t).** Removing the only escape hatch the LLM had for write intents was the cheapest fix — it eliminates a whole class of "fabricate a navigate then claim success" failures.

4. **Two-phase confirmation on `delete_record` with day-salted token + `hash_equals`.** Cleanly defends against the LLM auto-confirming destructive operations. Day salt invalidates replay across sessions.

5. **Hash-chain audit with canonical key-sort.** The `canonicaliseForHash` recursive `ksort` (AuditChainService:156-173) is a non-trivial detail that catches MySQL JSON column reordering. Most hash-chain implementations get this wrong.

6. **Atomic token budget with `lockForUpdate` after `firstOrCreate`.** Closes the race that the pre-S0.11.1 cache layer had. The migration from cache-with-TTL to row-with-lock is the right call for a money-relevant counter.

7. **Versioned cache key for provider toggle.** A bumped version number is atomic in a way that overwrite-the-key isn't. Prevents the half-anthropic, half-xAI prompt corruption that would otherwise be possible during admin toggles.

8. **Out-of-remit short-circuit before any LLM cost.** Financial-keyword detection in `QueryClassifier` correctly overrides incidental non-financial mentions ("depressed about my pension pot" stays in retirement mode), and pure non-financial topics get a deterministic refusal with zero LLM spend.

9. **Onboarding parking-hydration short-circuit.** When all needed fields are already extracted speculatively from prior messages, the grouped_extract turn skips the LLM entirely. Saves cost AND latency.

10. **Persona-tagged `ai_messages` (`advice` vs `data_capture`).** Future grouping, eval analysis, and cost attribution all benefit from the tag. INV-2.4.1 says the user can't see it; the audit log can.

---

## 5. Cost projection (rough)

Based on the read-out:

| Path | Tokens in (est.) | Tokens out (est.) | Cost / turn (Haiku 4.5, $1/M in, $5/M out) |
|---|---|---|---|
| Onboarding asset_capture | ~500 (no cache) | ~50 | $0.0008 |
| Onboarding asset_capture (after F-5 fix) | ~150 (cache) + 500 first-turn | ~50 | $0.0003 / turn after first |
| Advice factual (e.g. BILLING) | ~1500 | ~150 | $0.0023 |
| Advice factual (after F-14 fix) | ~700 | ~150 | $0.0014 |
| Advice module-scoped | ~1800 + 5KB tool result × 2 = ~3000 | ~300 | $0.0045 |
| Advice module-scoped (after F-3 fix) | ~2000 | ~300 | $0.0035 |
| Advice holistic | ~1800 + ~30KB tool results = ~9000 | ~500 | $0.012 |
| Advice holistic (after F-3 fix) | ~3000 | ~500 | $0.0055 |

**SaveTax campaign per user (current):** 8 onboarding turns × $0.0008 = $0.0064 + ~3 advice turns post-onboarding × $0.005 = $0.021 total ≈ **2p per user**.
**SaveTax campaign per user (after fixes F-3, F-5):** ~$0.008 ≈ **0.8p per user**. ~60% saving.

These are floor estimates — real cost depends on conversation length and engine-call frequency.

---

## 6. Recommendations summary

| # | Finding | Severity | Effort | Recommendation |
|---|---|---|---|---|
| F-1 | INV-2.4.5 not enforced | P1 | 4h | Wire `HandoffPayloadValidator` into `wrapStream`; emit `handoff_error` SSE event |
| F-2 | Sanitiser strips non-ASCII | P1 | 1h | Switch to denylist; preserve Unicode letters |
| F-3 | Tool result not summarised back to LLM | P1 | 6h | Add `summariseToolResultForModel` |
| F-4 | Anthropic cache hit invisible | P2 | 2h | Read `cache_read_input_tokens`; add dashboard |
| F-5 | No prompt cache on onboarding | P2 | 1h | Reorder layers: known_facts last |
| F-6 | WriteIntentClassifier mis-fires on questions | P1 | 2h | Add `?` and interrogative-prefix guard |
| F-7 | Dead code in FcaProcessInstructions | P2 | 5m | Delete `getDataCreationGuidance` |
| F-8 | System prompt persisted per message | P2 | 2h | Replace with hash; migrate old rows |
| F-9 | No cache invalidation on capture | P2 | 4h | Observer on direct-write models |
| F-10 | Engine-level fallback is holistic | P2 | 1h | Throw or default to factual |
| F-11 | Onboarding can create duplicates | P2 | 6h | Run RecordDuplicateChecker pre-handler |
| F-12 | bypass-preview-mode ability binary | P2 | 2h | Pair with eval-run-id header |
| F-13 | No transient LLM retry | P2 | 2h | Add single 529/429 retry |
| F-14 | Financial context unconditional | P2 | 1h | Skip Layer 5/6 for factual |
| F-15 | Tool-call cap may truncate | P2 | 1h | Dynamic cap by engine level |

**Suggested sprint slice:** F-1, F-2, F-6 in one PR (security/contract floor). F-3, F-5, F-14 in one PR (cost). The rest as a polish backlog.

---

## 7. What this audit did not cover

Out of scope, intentionally:

- **Evals (Mode 1 / Mode 2)** — Sprint 1 deliverable; covered in `01-invariants.md §2.13`.
- **`advice_response` SSE event schema (INV-2.3.5)** — Sprint 1 deliverable.
- **Multi-entity batch-shaped tools (INV-2.8.2)** — Sprint 2 deliverable.
- **Provider failover (BS-25)** — Sprint 4 deliverable.
- **The frontend chat panel rendering** — UI behaviour beyond the SSE contract.
- **Mobile (Capacitor) AI integration** — separate audit.
- **SaveTax `TaxStrategyCalculator` arithmetic** — separate audit (the calculator is deterministic; correctness is a different question from AI engineering).

A follow-up audit after Sprint 1 should cover the eval harness, INV-2.3.5 implementation, and the conversation-index population job (`ConversationSummariserJob`).

---

*Audit complete. Source-of-truth alignment: 33/35 invariants conformant; 2 gaps identified above (F-1 INV-2.4.5, INV-2.3.5 deferred per spec). Ready for ranked-PR sequencing.*
