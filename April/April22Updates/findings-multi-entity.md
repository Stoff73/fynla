# Findings — Phase I investigation (multi-entity capture)

**Date:** 22 April 2026
**Branch:** `feature/fyn-persona-split`
**Author:** Claude (session 2)
**Plan:** `plan-multi-entity-capture.md`

---

## I1 — Tool inventory: where the bad phrasing lives

### `XaiToolDefinitions.php` (Grok / xAI provider) — 15 tools carry the offending language

| Line | Tool | Current tail of description |
|---|---|---|
| 284 | `create_goal` | "Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn." |
| 298 | `create_life_event` | ditto |
| 323 | `create_savings_account` | "Call this tool IMMEDIATELY when the user mentions any bank account or cash savings. IMPORTANT: Do NOT call any other creation tools in the same turn." |
| 437 | `create_holding` | "Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn." |
| 457–458 | `create_pension` | "Call this tool IMMEDIATELY when the user mentions a pension. Fill in every field you can. IMPORTANT: Do NOT call any other creation tools in the same turn as create_pension." |
| 504 | `create_property` | "IMPORTANT: Do NOT call any other creation tools (create_family_member, navigate_to_page, etc.) in the same turn as create_property. The property form fill needs the page to stay on /net-worth/property until saved." |
| 623 | `create_protection_policy` | "Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn." |
| 663 | `create_liability` | ditto |
| 676 | `create_estate_gift` | ditto |
| 798 | `set_expenditure` | "Fill in every category the user mentions and set null for anything not mentioned. The form will be opened, filled, and saved automatically. IMPORTANT: Do NOT call any other creation tools in the same turn." |
| 848–849 | `create_family_member` | "Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn. For multiple children, call this tool ONCE per child in separate turns." ← **doubly bad** |
| 873 | `create_trust` | "Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn." |
| 889 | `create_business_interest` | ditto |

Tools **not carrying the phrase** in `XaiToolDefinitions`:
- `create_asset`, `create_chattel`, `create_mortgage`, `create_will`, `update_will`, `create_power_of_attorney`, `update_power_of_attorney`, `update_record`, `update_profile`, `capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `capture_work_details` — these are already clean and need only the positive multi-entity affordance added (if appropriate).

### `AiToolDefinitions.php` (Anthropic / Claude provider) — 1 tool carries it

| Line | Tool | Current tail |
|---|---|---|
| 1356 | `set_expenditure` | "IMPORTANT: Do NOT call any other creation tools in the same turn." |

Every other tool in `AiToolDefinitions` is functionally described without single-emission language. So the Anthropic path is already mostly clean — the bug surfaces primarily on the xAI path.

### Which provider is live?

`FynPersonaInvoker::buildToolList:198` resolves provider via `Cache::get('ai_provider', config('services.ai_provider', 'anthropic'))`. Config default is `anthropic` but production runtime has historically been `xai` (see memory `project_ai_form_fill_status` and `XaiClient.php`). Regardless — we fix **both** files since either can be active.

## I2 — Frontend tool-call queue: GREEN

**Location:** `resources/js/store/modules/aiFormFill.js`.

The queue is robust and already multi-entity aware:

- **Queue primitives.** `state.queue: []`, `ENQUEUE_FILL`, `DEQUEUE_FILL`, `CLEAR_QUEUE` mutations.
- **Serial processing.** `startFill` checks `s.pendingFill || s.filling` and enqueues if busy; otherwise processes. `completeFill` → `processNextInQueue` → re-dispatches `startFill` for the next queued item.
- **Per-fill navigation.** Each queued fill drives its own route change via `SET_PENDING_NAVIGATION` inside `startFill`. This was fixed explicitly (comment at lines 109–114): previously `aiChat.js` clobbered the first fill's route when a second `fill_form` SSE arrived.
- **Cross-tool support.** Queue is entity-agnostic. `ENTITY_LABELS` covers every create_* target: `savings_account`, `investment_account`, `dc_pension`, `db_pension`, `property`, `mortgage`, `protection_policy`, `goal`, `life_event`, `family_member`, `trust`, `business_interest`, `chattel`, `estate_asset`, `estate_liability`, `estate_gift`, `investment_holding`.
- **Race guard.** `recentCompleteFill` flag (lines 81–85, 251–253, 263–265) prevents form close-handlers from cancelling the next queued fill after a save.
- **Timeout safety.** 30s `fallbackTimer` per fill emits a chat message and advances the queue if a form doesn't acknowledge.

**Conclusion:** The queue will correctly handle multiple `fill_form` SSE events in one turn, including cross-tool (e.g. `create_savings_account` → `/cash` then `create_protection_policy` → `/protection`). No frontend change required.

## I3 — Non-prompt-layer blockers: GREEN

Checked:

- **`HasAiChat::chat`** (`app/Traits/HasAiChat.php`). Tool calls processed in a `foreach ($toolUseBlocks as $toolUseBlock)` loop (line 401). No de-duping, no index cap. Each tool call spawns its own handler call. `toolCalls` OpenAI-format loop at line 225–226 also iterates all calls.
- **`StructuredResponseValidator`**. Not in the call path for tool execution — only validates structured advice payloads. Unrelated.
- **`KycGateChecker`**. Only gates advice persona inputs (not data-capture tool dispatch). Unrelated.
- **`AdviceReviewService`**. Post-hoc review of advice narrative. Does not drop tool calls.
- **`FynPersonaInvoker`**. Strips internal handoff tool_use events (lines 114–118) but iterates all others.

No silent de-dup anywhere. All multi-entity tool calls emitted by the LLM will reach their handlers.

## I4 — Does `DataCapturePromptBuilder` reach the LLM? GREEN

Traced:

```
FynPersonaOrchestrator::runCaptureTurn
  → FynPersonaInvoker::invoke(persona=data_capture)
    → FynPersonaInvoker::buildPrompt
      → DataCapturePromptBuilder::build($user, $captureContext)
        → ::captureInstructions($context) ← contains the multi-entity rule (line 82)
      → CoordinatingAgent::chatWithPromptOverride(systemPromptOverride=$systemPrompt, ...)
```

The advice handoff also reaches this path:

```
advice Fyn emits delegate_to_capture
  → FynPersonaOrchestrator::handleAdviceTurn captures handoff payload
  → buildCaptureContextFromPayload → CaptureContext{entity_types, fields_needed, pending_advice_question}
  → runCaptureTurn (same path as above)
```

**Conclusion:** The multi-entity rule in `DataCapturePromptBuilder` is genuinely reaching the LLM on every post-onboarding capture turn, including the advice-delegated path. The rule is ineffective today only because the tool descriptions contradict it.

## I5 — `create_property` page-stay constraint

**Current claim in the tool description (line 504):** *"The property form fill needs the page to stay on /net-worth/property until saved."*

**Reality from the frontend queue:** `startFill` navigates BEFORE the fill opens, and does so per-fill. So:

- Two `create_property` calls in one turn → fill 1 opens on `/net-worth/property`, saves, `completeFill` → `processNextInQueue` → fill 2's `startFill` re-navigates to `/net-worth/property` (no-op, same route), opens fill 2, saves. OK.
- `create_property` + `create_family_member` in one turn → fill 1 property on `/net-worth/property`, save, processNextInQueue → fill 2 family_member navigates to `/family`, opens, saves. OK — the queue serialises, no concurrency.
- `create_property` + `navigate_to_page` in same turn → this IS a problem, because `navigate_to_page` is handled as a separate SSE event (`case 'navigation'` in `aiChat.js`) and sets `pendingNavigation` OUTSIDE the queue. Risk of interruption.

**Recommendation for Phase A:** narrow the property description from *"Do NOT call any other creation tools"* to **"Do NOT call `navigate_to_page` or `get_module_analysis` in the same turn — those interrupt the form fill. You MAY call `create_property` multiple times for multiple properties."**

## Summary — are we safe to proceed with the plan?

Yes. The investigation confirms:

1. The fix is genuinely a prompt/tool-description layer problem. Infrastructure (backend dispatcher + frontend queue) already supports multi-entity end-to-end.
2. The bug is concentrated on the xAI path (15 tools); the Anthropic path needs only one touch (`set_expenditure`).
3. Row 14 (cross-tool) is likely to work on the happy path — the queue handles different entity types with different routes. We test it live regardless per the plan's note.
4. No risky migrations or schema changes surfaced.
5. No observer/job race surfaced — each tool handler fires on a serialised queue.

**Green-light Phase A.**
