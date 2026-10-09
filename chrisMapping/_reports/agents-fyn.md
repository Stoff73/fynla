# Fyn AI agent system — verified map

Every claim below was read in code at the cited `path:line`. The canonical contract checked against is `.claude/skills/fyn-architecture/SKILL.md`. Nothing was changed.

Scope note: the `Memory/` directory and `MemoryRetrieverService` are covered by a separate agent; this report only records where Fyn calls into them.

---

## 1. The distinct agents, personas and modes

**Two write states behind one chat surface** (contract held).

| Agent | Class / entry point | Writes | Model / provider |
|---|---|---|---|
| Advice Fyn (read-only) | `app/Services/AI/AdviceFyn.php:217` `handle()` | No | Active provider, chat model |
| Onboarding / campaign Fyn (write) | `app/Services/Onboarding/OnboardingChatDirector.php:188` `handleUserMessage()`, `:7876` `handleInlineCapture()`, `:697` `handleAction()`, `:166` `emitFirstTurn()` | Yes, the only writer | Active provider, or no LLM at all on deterministic turns |
| Turn planner | `app/Services/AI/Loop/Planner.php:73` `plan()` | No | Own call, forced `plan` tool, `Planner.php:208-213` |
| Shared turn loop | `app/Services/AI/Loop/FynLoop.php:194` `run()`, `:113` `stream()`, `:618` `interceptHandoff()` | Routes both | n/a |
| Query classifier | `app/Services/AI/QueryClassifier.php:65` `classify()` | No | Deterministic regex, no LLM |
| Write-intent classifier | `app/Services/AI/WriteIntentClassifier.php:192` `classify()` | No | Deterministic regex, no LLM |
| Conversation summariser | `app/Services/AI/ConversationSummariser.php:57` `summarise()` | Writes index columns only | Direct HTTP to xAI, `ConversationSummariser.php:45,137` |
| Proposed-fact synthesiser | `app/Services/AI/Learning/ProposedFactSynthesiser.php:21` `synthesise()` | Stages only | Direct HTTP to xAI, `:18,42` |
| Advice review service | `app/Services/AI/AdviceReviewService.php:23` `checkForChanges()` | No | No LLM, pure DB comparison |
| Module agents | `app/Agents/CoordinatingAgent.php:365` plus seven `{Module}Agent` classes in `app/Agents/` | No | No LLM, calculation engines |

Two further pieces are agent-adjacent rather than agents: `app/Services/AI/AuditChainService.php:89` writes the hash chain, and `app/Services/AI/Cost/AiCostAttributionService.php:27` writes the per-action cost ledger.

The `session_mode` enum that distinguishes the two states is `app/Services/AI/Loop/SessionMode.php:23-39`. Advice maps to persona `advice`; onboarding maps to persona `data_capture`. `SessionMode::isReadOnly()` at `:45-48` is the bridge to the surface allowlist.

### Additional per-turn modes inside the write state

- Asset-capture turn — capture instructions rendered by `app/Services/AI/Fyn/FynCaptureTurnInstructions.php:24`.
- Verify/edit turn — `app/Services/AI/Fyn/FynVerifyEditTurnInstructions.php:38`, keyed off a focus prefixed `verify_edit_` (`FynContextAssembler.php:307-310`).
- Inline-capture handoff turn — focus falls back to `inline_capture` (`OnboardingChatDirector.php:92`, `:7968-7971`), deliberately NOT `savings`, because the old fallback framed household records as out of scope and the model's only scripted exit was the security refusal.
- Grouped-extract turn — uses `AiToolDefinitions::onboardingExtractionTools()` (`AiToolDefinitions.php:409`) passed as a `toolsListOverride`.

---

## 2. Personality and voice — where the words live

The unified prompt is a single static string, `app/Services/AI/Fyn/FynSystemPrompt.php:70-216`, with only two placeholder substitutions (`{{RECORD_TYPES}}`, `{{FCA_PROCESS}}`) so the whole thing is byte-identical per user and prefix-cacheable.

- **Voice and tone**: `FynSystemPrompt.php:48-58` (`PERSONALITY`). Key lines: "Warm, encouraging, and clear — like a knowledgeable friend who understands financial planning deeply"; "Celebrate progress"; "Never be condescending or make the user feel bad about their financial position"; "British spelling. Currency in £. Calm, plain-English tone — never patronising, never alarmist"; "Always signpost regulated advice when the user's query asks 'what should I do?'".
- **Response shape**: `FynSystemPrompt.php:60-68` (`RESPONSE_FORMAT`). Bold for key figures, numbered lists for sequences, always end on a natural follow-up question, never open with "Certainly!", "Of course!", "Great question!", "Absolutely!", occasional first-name use but "do not overdo it".
- **Identity and the not-regulated-advice framing**: `FynSystemPrompt.php:76-80`.
- **Security rules**: `FynSystemPrompt.php:83-93`. Rule 6 carries the verbatim canned refusal and two carve-outs: legitimate requests to manage the user's own data, and any message that answers a question Fyn asked ("It NEVER applies to a message that answers a question you asked"). The refusal string has one home as `FynSystemPrompt::CANNED_REFUSAL` at `:26`, read back by the history builder at `HasAiChat.php:1810`.
- **Scope**: `FynSystemPrompt.php:95-99`.
- **British English and no acronyms**: `FynSystemPrompt.php:110-111`. ISA is the single permitted abbreviation.
- **No tool narration, no record IDs, no route paths, no planning jargon, no inapplicable concepts**: `FynSystemPrompt.php:117-122`.
- **Regulatory compliance** — mandatory hedging, no product recommendations, signpost regulated advice, risk warnings, tax caveats, no market timing, never state tax figures from memory: `FynSystemPrompt.php:126-132`.
- **Read-only declaration**: `FynSystemPrompt.php:149`.
- **Handoff guidance (top-priority rule)**: `FynSystemPrompt.php:198-204`.
- **FCA six-step process**: `FynSystemPrompt.php:223-242` — the one home, spliced in via the `{{FCA_PROCESS}}` token; the legacy builder reads it from here too (`Prompts/FcaProcessInstructions.php:39-43`).
- **The writable-record vocabulary**: `FynSystemPrompt.php:41` (`WRITABLE_RECORD_TYPES`) — one constant, deliberately including household and profile records after a live failure where a charitable-giving request matched no entity and fell to the refusal.
- **FCA signposting sentence**: `FynSystemPrompt.php:206-212`, verbatim, final line only.

### Per-turn layers

`app/Services/AI/Fyn/FynContextAssembler.php:64-334` builds the dynamic `<context>...</context>` + `<user_message>...</user_message>` block prepended in memory to the current user turn. Voice-adjacent layers:

- **Claim tiers and proactivity**: `FynContextAssembler.php:634-645` (`voicingRules()`). Mechanical claims stated directly with working shown; judgement claims hedged and signposted; at most one additional strategy surfaced after the answer; ask the one clarifying question before computing from an ambiguous figure.
- **Will-structure refusal**: `FynContextAssembler.php:582-621` — quote the `WillTypePolicy` refusal unchanged rather than composing one.
- **Tax-plan grounding**: `FynContextAssembler.php:547-573` — must call `get_recommendations` and use the composed plan as authoritative.
- **Saved household finances**: `FynContextAssembler.php:487-541`.
- **Savings getting-started**: `FynContextAssembler.php:453-482` — lead with the emergency-fund buffer.
- **Preview mode**: `FynContextAssembler.php:320-326`.

### Prompt fragments outside PHP

Two live in the procedural corpus and are injected as `<overlay>` blocks (`FynContextAssembler.php:204,212-214`):

- `fyn-memory/procedural/system_prompt_overlay/general/a1-answer-first.md:10-16` — "Answer the user first. A user question is never acknowledged-and-advanced past."
- `fyn-memory/procedural/system_prompt_overlay/general/a2-ack-hygiene.md:10-16` — "No standalone acknowledgement bubbles… Emit an acknowledgement only when a write actually occurred."

One workflow procedure feeds the planner: `fyn-memory/procedural/recommendation-routing.md:11-25`.

### Capture-turn rules

`app/Services/AI/Fyn/FynCaptureTurnInstructions.php:41-158` carries, in order: the capture-accuracy rule (never infer an ISA subtype or non-ISA ownership), the clarification follow-up rule, the multi-entity rule with five worked examples, "YOUR SINGLE JOB", the intent exception, the question exception, the one-sentence off-script guardrail, the retraction rule, and the allowed tool list. `:24-39` parameterises only the focus label, the tool list and whether this is a module walk. The edit variant is `app/Services/AI/Fyn/FynVerifyEditTurnInstructions.php:59-75`, with its per-section tool map at `:10-24`.

### Legacy prompt path — and a bug in it

`config/fyn.php:17` defaults `prompt_architecture` to `unified`; `app/Services/AI/Fyn/FynPromptMode.php:13-16` is fail-safe, only the exact string `unified` enables the new path. The legacy 12-layer builder is `app/Services/AI/AdvicePromptBuilder.php:82` with its layer list documented at `:42-57`.

**`CoreIdentity.php` renders its placeholders literally.** `app/Services/AI/Prompts/CoreIdentity.php:52` and `:56` write `{\$personality}` and `{\$responseFormat}` inside an INTERPOLATING heredoc (`<<<PROMPT`, not nowdoc). The backslash escapes the dollar sign, so the rendered prompt contains the literal text `{$personality}` and `{$responseFormat}` instead of the tone blocks. Verified by running the same heredoc through PHP directly. The variables assigned at `:22-23` are never used in the output. Impact is limited to `FYN_PROMPT_ARCH=legacy`, the emergency rollback path, which would therefore ship with no personality and no response-format block at all.

Other legacy fragments, for completeness: `Prompts/ComplianceRules.php:17-42`, `Prompts/FcaProcessInstructions.php:47-80`, `Prompts/EmptyDataGuard.php:24-37`, `Prompts/QueryKnowledge.php:27-75`, and the legacy handoff guidance at `AdvicePromptBuilder.php:259-289` (which reads the record vocabulary from `FynSystemPrompt::WRITABLE_RECORD_TYPES`, so the two cannot drift).

User free text is sanitised at every interpolation site by `app/Services/AI/Prompts/UserContentSanitiser.php:68-78` — a denylist (not a whitelist, so non-ASCII names survive) plus `<user_provided>` structural wrapping.

---

## 3. Providers

**xAI is live.** `.env` carries `AI_PROVIDER=xai`. The config default is Anthropic (`config/services.php:54`).

### xAI

- Client: `app/Services/AI/XaiClient.php:24-121`. Wraps the OpenAI PHP SDK against `https://api.x.ai/v1` (`config/services.php:49`), 120-second Guzzle timeout and 10-second connect timeout (`XaiClient.php:66-69`), and sets the `x-grok-conv-id` header for prompt-cache routing at 75% discount on cached input (`:42-59,77`).
- Models: `grok-4.3` for chat, advanced and vision (`config/services.php:42-48`; defaults repeated at `XaiClient.php:104,112,120`). The class docblock at `:18-22` records grok-4.3 as the successor to the retired grok-4-1-fast family.
- `degrade_chat_model` (`config/services.php:47`) is unset by default, so xAI soft-degrade keeps the standard model and chat stays open.

### Anthropic

- Client resolved from the container as `Anthropic\Client`, used at `app/Services/AI/Loop/Planner.php:106`.
- Chat model default `claude-haiku-4-5-20251001`, advanced `claude-sonnet-4-6-20260320` (`config/services.php:36-37`), mirrored as constants at `HasAiGuardrails.php:20,29`.

No OpenAI provider exists. The OpenAI SDK is only the transport for xAI.

### How the provider is chosen

One canonical reader, `app/Traits/HasAiGuardrails.php:64-79`: versioned cache key `ai_provider:v{N}` where N is `ai_provider_version`, then the legacy `ai_provider` key, then `config('services.ai_provider')`. The admin toggle writes all three atomically (`app/Http/Controllers/Api/AdminController.php:708-712`). `Planner::resolveProvider()` duplicates the same lookup at `Planner.php:197-206` so planner and reasoner agree within a turn. The chat loop captures the provider ONCE at the top (`HasAiChat.php:461`) so a mid-stream admin toggle cannot swap providers mid-loop.

**Finding (low severity): two readers skip the versioned key.** `app/Services/AI/AdviceFyn.php:835` and `app/Services/AI/AiToolDefinitions.php:54` both read the bare `ai_provider` key with no version check. In practice the admin toggle writes that key too, so they agree today. If the legacy key were evicted while the versioned key survived, the tool catalogue would be assembled in the wrong wire format for the active provider.

### The dual-provider tool catalogue

- `app/Services/AI/AiToolDefinitions.php:59-63` emits Anthropic shape (`name` / `description` / `input_schema`).
- `app/Services/AI/XaiToolDefinitions.php:188` emits pre-wrapped OpenAI function objects (`{type: function, function: {...}}`), no further wrapping in `HasAiChat`.

Neither holds schemas in code any more. Both read the same markdown corpus under `fyn-memory/procedural/tool_schema/`: Anthropic via `AiToolDefinitions::toolsFromCorpus()` (`:145-161`) reading the default variant, xAI via `XaiToolDefinitions::toolsFromCorpus()` (`:133-149`) asking the corpus for the `xai` provider variant at `:140`.

**How they stay in sync:**
1. Ordered `ORDER` constants pin assembly order — `AiToolDefinitions.php:70-133` and `XaiToolDefinitions.php:66-122` — described in both files as byte-for-byte guarded by `ToolSchemaGoldenMasterTest` / `XaiToolSchemaGoldenMasterTest`. The xAI map deliberately nests `set_expenditure` at the tail of dataCreation, which differs from the Anthropic order (`XaiToolDefinitions.php:63-64`).
2. Every tool file has a paired `.md` and `.xai.md`, except the five onboarding `capture_*` schemas which have no xAI variant. That is consistent: `XaiToolDefinitions` has no onboarding ORDER entry, and `AiToolDefinitions::onboardingExtractionTools('xai')` (`:409-448`) re-wraps the Anthropic bodies itself with `strict: false`.
3. Runtime degradation: a malformed or missing corpus body is skipped with a `report()` rather than emptying the catalogue (`AiToolDefinitions.php:171-189`, `XaiToolDefinitions.php:160-189`). The golden-master tests and `fyn:procedural:validate` are the real completeness guards.
4. Empty `properties` objects are re-objectified after `json_decode` so byte-identity with the original literal is preserved (`AiToolDefinitions.php:195-202`, `XaiToolDefinitions.php:179-186`).

`update_record`'s schema is the one runtime-generated shape: a `{"$allowlist":"update_record"}` sentinel in the corpus body is replaced by `AiToolDefinitions::updateRecordSchema()` (`:191-193`, `:328-355`), a `oneOf` of per-entity branches sourced from `App\Constants\UpdateRecordAllowlist::MAP`, with the runtime handler re-checking the allowlist as defence in depth.

---

## 4. Tools

### Catalogue

Assembled from `AiToolDefinitions.php:70-133`.

**Reads:** `navigate_to_page`, `list_records`, `list_goals`, `list_life_events`, `get_module_analysis`, `get_recommendations`, `search_conversation_index`, `get_tax_information`, `generate_financial_plan`, `get_subscription_status`, `list_invoices`, `get_current_plan`.

**Writes:** `create_what_if_scenario`, `create_goal`, `create_life_event`, `create_savings_account`, `create_investment_account`, `create_holding`, `create_pension`, `create_property`, `create_mortgage`, `create_protection_policy`, `create_asset`, `create_liability`, `create_estate_gift`, `create_will`, `update_will`, `create_power_of_attorney`, `update_power_of_attorney`, `create_family_member`, `create_trust`, `create_business_interest`, `create_chattel`, `update_record`, `delete_record`, `update_profile`, `set_expenditure`.

**Campaign captures (writes):** `capture_salary_sacrifice`, `capture_spouse_work_status`, `capture_spouse_household_data`, `capture_spouse_non_working_assets`, `capture_pension_history`, `capture_charitable_giving`, `capture_retirement_goals`, `capture_state_pension`.

**Onboarding grouped-extract (writes, not in the base catalogue):** `capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `capture_work_details`, `capture_monthly_expenditure`.

**Handoff (internal, never user-visible):** `delegate_to_capture`, `capture_complete`.

**Pointer fetch tools (read-only, synthesised):** one `fetch_{pointer_id}` per tool-mode pointer — `AiToolDefinitions.php:224-241`, xAI equivalent `XaiToolDefinitions.php:197-219`, dispatched at `CoordinatingAgent.php:1156-1163`. Exposed in preview mode too, because they are reads.

Preview mode strips every write group: `AiToolDefinitions.php:29-40`, `XaiToolDefinitions.php:40-50`.

Execution is one dispatch match at `app/Agents/CoordinatingAgent.php:1166-1233`. Read-vs-write is classified for the audit chain by `CoordinatingAgent.php:1578-1593` (`create_`/`update_`/`capture_` prefixes plus `delete_record` and `set_expenditure` are writes; the two handoff tools are `handoff`; everything else is a read).

### `delegate_to_capture` and the two write states

Tool names are constants so a typo fails at parse time: `app/Services/AI/HandoffContract.php:22-24`, with `isInternalTool()` at `:37-40`.

The schema (`fyn-memory/procedural/tool_schema/handoff/delegate_to_capture.md`, version 2) requires `reason` and `entity_types`, accepts optional `fields_needed`, and instructs the model that this is "the ONLY way a write reaches the database from advice mode, so emit it rather than refusing". Its `entity_types` description explicitly lists household and profile record types alongside assets, and tells the model to invent a description rather than omit the call.

Dispatch stubs the tool and returns a handoff marker (`CoordinatingAgent.php:1172-1176`), which `HasAiChat.php:993-1000` turns into a synthetic `handoff` SSE event, which `FynLoop::interceptHandoff()` consumes at `FynLoop.php:629-734`. The path is:

```
LLM emits delegate_to_capture
  -> CoordinatingAgent yields {type: handoff, handoff_type: delegate_to_capture}
  -> FynLoop::interceptHandoff consumes it (never forwarded — INV-2.4.1)
  -> OnboardingChatDirector::handleInlineCapture
  -> the same direct-write handlers in CoordinatingAgent
```

`FynLoop.php:724-734` returns immediately after the inline capture so the outer advice turn cannot emit a second, duplicate confirmation (the BS-14 regression). `AdviceFyn::wrapStream()` at `:782-790` is a thin forwarder kept only so an existing reflection-based regression test still exercises the real behaviour.

### Write safety — three enforcement points, one list

`AdviceFyn::WRITE_TOOLS` (`AdviceFyn.php:170-203`) is the single denylist.

1. **Catalogue strip** — `array_diff` in `AdviceFyn::buildToolList()` at `:850`. The model never sees a write tool in advice mode.
2. **Dispatch rejection** — `app/Services/AI/Actions/SurfaceAllowlist.php:47-54` via `ToolActionMapper::isWriteSurface()` (`ToolActionMapper.php:49-52`), called from the tool-use loop at `app/Traits/HasAiChat.php:940-945`. A denied call never executes: `rejectGroundSurface()` at `HasAiChat.php:1697-1721` writes an audit row with `status: stripped`, logs a warning, and returns a safe observation so the loop continues and the model can explain the limitation in words.
3. **`GroundGate`** — `app/Services/AI/Ground/GroundGate.php:44-51`, the standalone predicate the typed-action allowlist superseded, retained and parity-tested against the same list.

Modes other than `advice` are intentionally pass-through (`SurfaceAllowlist.php:22-33,49`) so legitimate onboarding and legacy writes are never disturbed.

`create_what_if_scenario` is on the write list despite the name (`AdviceFyn.php:188-193`) because it persists a `WhatIfScenario` row. `navigate_to_page` is on the list for a different reason (`AdviceFyn.php:194-202`): the LLM was using it as an escape hatch for write intents, then fabricating success text, so the tool was removed entirely from advice.

Because of that, the capture side re-adds every write tool EXCEPT `navigate_to_page`: `OnboardingChatDirector::captureToolSet()` at `:8472-8481` — "Rule 20, one list… every one of them must be dispatchable here or the strip creates a dead end the user cannot escape".

Note that `delegate_to_capture` and `capture_complete` are NOT on `WRITE_TOOLS`, so they survive the `array_diff` and remain available to advice mode (`AdviceFyn.php:839-843`). That is the intended design.

### Supporting contracts

- **`KycGateChecker`** — `app/Services/AI/KycGateChecker.php:32-69`. Checks only the data the PRIMARY classification needs; related classifications enrich but never block. Emits a `GateChecked` eval event per requirement (`:47-57`). Requirement matching at `:74-100`.
- **`RecordDuplicateChecker`** — `RecordDuplicateChecker.php:44-91`. Conservative: suppresses the capture route only when EVERY entity the deterministic extractor finds is already persisted (`allEntitiesExist()` at `:80-91`). Partial cases fall through so new entities still persist via gap-fill.
- **`DuplicateAcknowledgement`** — `DuplicateAcknowledgement.php:53-63` plus per-entity descriptor builders at `:131-496`. Builds a deterministic, non-LLM acknowledgement from the matching DB rows, explicitly because LLM phrasing had produced replies that read as "I've added these" when nothing was added (gaslighting).
- **`HandoffContract` / `HandoffPayloadValidator`** — `HandoffPayloadValidator.php:16-31` returns a typed error key. A missing `reason` is soft (logged at notice, recovered by `CaptureContext::fromArray`); missing or non-array `entity_types` is hard and terminates the turn with a `handoff_error` frame (`FynLoop.php:644-676`).
- **`ToolResultContract`** — `ToolResultContract.php:45-143`. Per-module required-key contract with four paths (happy, readiness-gated, success-false, module-specific empty state). Drift throws `ToolResultContractException` rather than silently truncating what the model sees. Required keys per module at `:45-77`, gate keys at `:85-91`.
- **`StructuredResponseValidator`** — `StructuredResponseValidator.php:72-175`. Flags banned acronyms (`:30-49`), exposed record IDs, emoji and tick/arrow glyphs, banned jargon (`:53-61`), filler openers, advice responses with no £ amount, HTML injection, and leaked `[Context:` blocks. It flags, it does not block. `sanitiseWithViolations()` at `:216-257` strips leaked context/system/debug blocks, record IDs, echoed tool-name lines and dangerous tags, and collapses repeated blocks. `data_capture` turns are exempt from the missing-amounts rule (`:141-152`).
- **`RecaptureGuard`** — `app/Services/AI/Fyn/RecaptureGuard.php:243` `inspect()`. The one place that decides what happens when Fyn captures a record the user already has, extracted after an audit found twenty-four handlers each failing differently. A match splits three ways, and an edit must be explicit.
- **`ToolResults::isDuplicateSkip()`** — `app/Services/AI/ToolResults.php:22-26`. A dedupe skip means the record exists; ledgering it as a failed write voiced a false "I couldn't record anything" above a landed write.
- **`AckSentenceDeduper`** — `app/Services/AI/Support/AckSentenceDeduper.php`. Shared by the director's live stream and `HasAiChat`'s persistence so streamed text and reloaded transcript match.

---

## 5. Dispatch, the endpoint and the loop

### One endpoint for every surface

`routes/api.php:1507-1530` defines the whole `ai-chat` group behind `auth:sanctum` and `throttle:60,1`:

| Route | Controller method | Line |
|---|---|---|
| `GET /conversations` | `index` | `routes/api.php:1509` |
| `POST /conversations` | `create` | `:1510` |
| `POST /contextual-conversations` | `createContextual` | `:1511` |
| `GET /conversations/{id}` | `show` | `:1512` |
| `DELETE /conversations/{id}` | `destroy` | `:1513` |
| `POST /conversations/{id}/messages` | `sendMessage` | `:1514` (`throttle:ai-chat`, `idempotent`) |
| `POST /conversations/{id}/messages/{messageId}/stream` | `streamQueuedMessage` | `:1518` |
| `DELETE /conversations/{id}/messages/{messageId}` | `cancelQueuedMessage` | `:1521` |
| `GET /resumption` | `getResumption` | `:1523` |
| `DELETE /conversations/{id}/resumption` | `clearResumption` | `:1524` |
| `POST /conversations/{id}/action` | `action` | `:1525` |
| `GET /onboarding/status` | `getOnboardingStatus` | `:1528` |
| `POST /onboarding/start` | `startOnboarding` | `:1529` |

iOS posts to exactly these paths (`ios-native/Fynla/Features/Fyn/FynClient.swift:84,94,104,116,126,173,190,209,223`). Web and `/m` use the same. The only surface signal on the wire is the `X-Fynla-Forms: 1` header, read once per request at `AiChatController.php:1074-1078` and pushed into the director at `:232,454,855,952`; native does not send it yet and keeps the typed prompt for a form turn (`OnboardingChatDirector.php:135-141`).

### The dispatch predicate

One home: `app/Services/AI/ContextualConversation/ConversationModeResolver.php:12-35`, called from all three stream entry points (`AiChatController.php:277`, `:456`, `:947`).

```
routesToOnboarding() is TRUE when:
  config('onboarding.fyn_flow_enabled', true)          (:14-16)
  AND conversation.metadata.source !== 'surface_action' (:19-21)
  AND EITHER
      source === 'fyn_onboarding' AND user.onboarding_fyn_step !== null   (:23-31)
    OR
      (user.onboarding_completed === false OR user.active_campaign !== null)
      AND user.onboarding_fyn_step !== null                                (:33-34)
```

This matches the canonical contract: a completed user, or one paused with the step nulled, routes to read-only advice even inside an onboarding conversation. The comment at `:26-29` records the live incident (csjones, conversation 245, 2026-09-19) where a message into a finished walk reached the director with no step and the web panel hung on "Onboarding state lost".

Two controller-level extensions widen it:
- A form naming the record it edits is the director's edit pathway whether or not the user is onboarding (`AiChatController.php:278-280`, `:453,456`).
- `edit:<section>:<id>` and `edit_section:<section>` actions are director actions during and after onboarding (`AiChatController.php:947-951`).

Campaign re-entry is signalled purely by `active_campaign` being non-null; `onboarding_completed` is never modified by re-entry (`AiChatController.php:268-274`). The `/onboarding/start` entry-source dispatch — campaign map first, then journey map, then paused-campaign fallback, then funnel fallback — is `AiChatController.php:737-827`.

### SSE contract

Frames are written as `data: {json}\n\n` with `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `Connection: keep-alive`, `X-Accel-Buffering: no` (`AiChatController.php:386-391`, `:530-535`, `:891-896`, `:1003-1008`). Every frame goes through one writer, `AiChatController::writeClientEvent()` at `:1060-1072`.

Frame types emitted by the backend: `content`, `done`, `thinking`, `title`, `tool_use`, `navigation`, `quick_replies`, `skip_link`, `fill_form`, `form_received`, `capture_form`, `capture_form_errors`, `capture_write_result`, `capture_complete`, `entity_created`, `entity_updated`, `entity_deleted`, `onboarding_advance`, `onboarding_field_captured`, `onboarding_layout_change`, `onboarding_complete`, `onboarding_capture_error`, `conversation_created`, `resume`, `level_up`, `token_limit`, `consent_required`, `handoff_error`, `error`, `action`.

Two classes of frame never reach a client:
- **`handoff`** — consumed internally; any non-delegate handoff type is dropped with a warning (`FynLoop.php:737-754`). INV-2.4.1 holds; there is no `persona_state_change` event anywhere.
- **The four record-card frames** (`entity_created`, `entity_updated`, `entity_deleted`, `capture_complete`) are withheld for the whole of onboarding, in one place, for every surface (`AiChatController.php:41` and `:1062-1064`). Advice Fyn keeps its cards.

The terminal `level_up` frame is built by one static helper shared by all three stream methods (`AiChatController::levelUpFrame()` at `:175-191`, used at `:334-345`, `:493-504`, `:973-984`).

### The agentic loop

`FynLoop::run()` at `FynLoop.php:194-299`:

1. Build the planner system prompt, layered with the procedural corpus, recalled episodes and the episodic rubric (`:319-333`).
2. Emit `thinking` (`:215`).
3. Loop up to the cycle cap (`:217`): call `Planner::plan()`, record planner cost, dispatch the typed action.
   - `no_action` -> canonical defer line and return (`:227-230`).
   - `learn` -> stage to episodic / semantic / procedural, then re-plan (`:232-243`).
   - `retrieve` -> no-op and re-plan while under budget, then fall through to answering (`:245-256`).
   - `reason` or `ground` -> run the streamed reasoner and return (`:258-268`). Ground routes to the reasoner because under Option A the reasoner owns its own tool-use loop and its own GroundGate.
4. Cap exhausted -> one optional failure-consult when learning is enabled (`:280-296`), then the defer line.

Constants: `CYCLE_CAP = 8` (`FynLoop.php:67`), configurable per session mode (`:305-311`, `config/fyn.php:37-41`); `RETRIEVE_BUDGET = 3` (`:70`); planner history 6 turns capped at 600 chars each (`:443-446`, `:462-485`). The planner history fix at `:452-458` records BUG-02, where a terse answer reached the planner context-free and was discarded behind the defer line.

The planner system prompt is `FynLoop.php:77-86`: choose `reason` for anything answerable, read a short message as the answer to the previous question, and never choose `learn` for a user's own financial data. The closed action vocabulary and per-variant fields are `Planner::planSchema()` at `:303-325`, shared by both providers' tool definitions so they cannot drift. A planner failure degrades to a default `reason` rather than erroring the turn (`Planner.php:88-95`); an unknown action type does the same (`:244`); a `ground` action with no surface degrades too (`:254-262`).

`FynLoop::stream()` at `:113-179` is the raw streamed-turn primitive. Its single job is to keep the focus-set and the stream that reads it on the SAME `CoordinatingAgent` instance, since that agent is container-transient, and to always clear in `finally`. Everything the shells set — unified focus, verify-edit scope, explicit-edit entity, confirmed facts — travels through here.

The reasoner's own tool-use loop is `app/Traits/HasAiChat.php:385` onward: token-budget check (`:404-414`), classification and KYC (`:417-429`), prompt build (`:432-447`), unified turn-context injection (`:437-447`), model selection and soft-degrade (`:450-463`), tool-list resolution with the three-level priority `toolsListOverride > allowedToolsOverride > getTools()` (`:468-509`), an engine-level-aware tool-call cap (`:516-523`), then the API loop with the typed-action gate at `:940-953`.

### Concurrency, resumption, consent

- A 300-second per-conversation cache lock serialises turns (`AiChatController.php:241`); a turn arriving behind an in-flight one is queued or rejected past the depth cap (`:243-258`). Depth cap 3, TTL 10 minutes (`config/fyn.php:27-30`); state transitions in `app/Services/AI/Loop/ConcurrentTurnQueue.php:29-44`. The frontend streams the next queued turn on `done`.
- A fatal mid-stream error flags a resumption (`AiChatController.php:364-370`, `app/Services/AI/Loop/ResumptionService.php:23-32`), surfaced on the next session via `GET /resumption` (`:54-60`).
- Consent is gated at entry on all four stream endpoints (`AiChatController.php:208-213`, `:413-418`, `:631-636`, `:937-942`) and re-checked at most every 2 seconds in-stream (`:306-315`), bounding withdrawal latency without 50-100 queries per second.
- A `surface_action` conversation whose resource has been deleted returns 410 with a fallback destination (`AiChatController.php:1011-1046`).

---

## 6. Integration with the module agents

### Engine-call granularity

`AdviceFyn` maps every classification to one of three engine-call levels (`AdviceFyn.php:101-126`): `holistic` (only `HOLISTIC_HEALTH`), `module`, or `factual`. The lenient lookup `engineCallLevelFor()` at `:142-149` defaults unmapped types to `factual`, explicitly so production cannot regress to running the most expensive path on an unmapped or non-financial query; the strict `engineCallLevel()` at `:821-830` still throws, so tests enforce exhaustive coverage. Response mode maps separately at `:58-83`.

### The engine

`CoordinatingAgent::orchestrateAnalysis()` (`CoordinatingAgent.php:365-440`): collect all module analyses (`:378`), compute available surplus (`:381`), extract recommendations (`:385`), identify conflicts (`:388`), resolve them (`:391`), rank via `PriorityRanker` (`:394`), optimise cashflow allocation and identify shortfalls (`:397-399`), generate cross-module strategies (`:402-406`). Bookended with `EngineCalled` eval events at entry and exit (`:369-376`, `:432-441`).

Conflict resolution handles protection-vs-savings, cashflow and ISA-allowance conflicts (`:477-519`); the ISA allowance is read from `TaxConfigService` (`:507`). Ranking delegates to `PriorityRanker` (`:521-524`). `generateHolisticPlan()` at `:451-470` layers `HolisticPlanner` and an action plan on top.

### How Fyn reads ranked recommendations

Two paths, deliberately kept in provenance parity:

- **Tool path** — `get_recommendations` -> `CoordinatingAgent::handleRecommendations()` (`:2737-2772`). Returns `ranked_recommendations` from `orchestrateAnalysis`, the `composed_tax_plan` from `ComposedTaxPlanService`, and records strategy-id provenance plus the plan digest into the request-scoped `FetchProvenanceCollector` (`:2750-2763`). The collector is resolved at call time, not constructor-injected, because `AdviceFyn` is a singleton and would otherwise freeze the first request's collector.
- **Pointer path** — `RecommendationHandler` (`app/Services/AI/Pointers/Handlers/RecommendationHandler.php:24-44`), whose value encoding is pinned byte-equal to `ComposedTaxPlanService::planDigest`'s encoding so skill and tool cannot disagree.

Module analyses reach the model through `get_module_analysis` -> `handleModuleAnalysis()` (`:2454-2505`), which matches the module to one of seven agents (or `orchestrateAnalysis` for `holistic`), applies the question-scoped fallback when the primary KYC has passed (`:2475-2482`), emits an `EngineCalled` event (`:2488-2498`) and summarises for the model (`:2504`). Raw agent output becomes prompt shape via `mappedModuleAnalysis()` at `:534`.

### The context assembler

`FynContextAssembler::build()` (`:64-334`) is what actually puts engine output in the prompt.

Always present: tax year (`:74`), sanitised first name (`:75`), situation line naming the onboarding focus or `advice` (`:76-78`), `<user_profile>` (`:81`), `<current_context>` (`:82`), `<surface_action>` when the conversation came from a surface Add/Edit action (`:83-86`, built at `:336-401`), `<known_facts>` when non-empty (`:89-92`), `<procedures>` relevance-filtered to this turn (`:100-113`), `<remembered>` (`:114-117`), `<knowledge>` from the semantic corpus (`:124-146`), `<live_data>` from matching prefetch pointers (`:151-165`), plus the overlay and FCA blocks from the procedural corpus (`:200-217`).

Bucket-gated:
- **POSITION** -> `<financial_context>` and `<existing_records>` (`:219-224`).
- **READINESS** -> the lean per-user READY/BLOCKED matrix (`:226-236`); the static navigation and blocked-module rules were moved into the cached system prompt to save ~595 tokens per advice turn.
- **CAPTURE** -> the capture or verify-edit instruction block (`:305-318`).

Ungated but conditional: the KYC prompt text (`:243-247`), classification-scoped financial knowledge (`:254-258`), the required-tools-and-triggers block (`:265-268`), and billing guidance (`:301-303`).

Bucket membership is `app/Services/AI/Fyn/FynContextSelector.php:17-36` — onboarding gets IDENTITY + CAPTURE; a factual advice turn gets IDENTITY only; everything else gets IDENTITY + POSITION + READINESS. The bucket enum is `ContextBucket.php:15-21`; the immutable turn description is `FynTurnContext.php:15-55`.

The assembler receives the same sized-analysis closure the legacy builder used, so POSITION carries real financial context rather than the "analysis service not provided" sentinel (`FynContextAssembler.php:40-44`, wired at `HasAiChat.php:1663-1680`). The assembled block replaces the last user message's content in memory only; the persisted row keeps the raw message (`HasAiChat.php:1632-1681`). It is also captured to `$this->assembledContext` for the admin AI-Audit forensic view (`:1672-1674`).

### Pointers — the pointer model

`PointerRegistry` (`app/Services/AI/Pointers/PointerRegistry.php:29-135`) fail-closed loads `fyn-memory/procedural/pointers/*.md`, refusing any pointer whose `handler` is not on the code whitelist (`:120-123`) and any duplicate id (`:49-52`). Frontmatter requires `pointer_id`, `topic`, `mode`, `handler`, `source_label`, `version`; prefetch and both modes additionally require triggers (`:110-133`).

Nine pointers exist in `fyn-memory/procedural/pointers/`: `recommendations`, `cross-module-plan`, `retirement-plan`, `savings-plan`, `investment-plan`, `protection-plan`, `estate-plan`, `isa-annual-allowance`, `user-financial-position`.

Mode `prefetch` fires on sparse trigger match during context assembly (`PointerRegistry.php:60-78`, called at `FynContextAssembler.php:153`); mode `tool` becomes a `fetch_*` tool (`:81-84`). Handlers, all read-only:

| Handler id | Class | What it fetches |
|---|---|---|
| `recommendations` | `Handlers/RecommendationHandler.php:24` | Live composed tax plan |
| `cross-module-plan` | `Handlers/CrossModulePlanHandler.php:21` | Every module's plan ranked by affordability |
| `{module}-plan` x5 | `Handlers/ModulePlanHandler.php:22` + five subclasses | One module's composed plan |
| `tax_allowance` | `Handlers/TaxAllowanceHandler.php:17` | ISA and pension allowances from `TaxConfigService`, plus saved ISA usage |
| `user_financial` | `Handlers/UserFinancialHandler.php:13` | The user's existing-records summary |

`FetchDispatcher::run()` (`Pointers/FetchDispatcher.php:23-40`) runs the handler, records provenance, and degrades a throw to `null` plus a `report()` — never a broken turn. `FetchResult::make()` (`FetchResult.php:24-27`) derives a 16-char SHA-256 digest of the value; `provenance()` at `:30-38` is the tuple written to `ai_messages.metadata`.

---

## 7. Cost, audit chain and learning

### Cost

- **`AiCostCalculator::compute()`** (`Cost/AiCostCalculator.php:27-49`) prices input, output, cache-read and cache-write separately from `config/ai_pricing.php` in GBP per 1,000 tokens. Model lookup is exact, then prefix, then `default` (`:58-73`); an unpriced model returns `priced: false` so the gap is visible rather than silently £0.
- **`AiCostAttributionService::record()`** (`Cost/AiCostAttributionService.php:27-38`) writes one `ai_cost_attributions` row per LLM call.
- **`FynLoop` instruments both stages**: planner at `:504-544` (`stage: planner`, token usage read from `Planner::$lastUsage` at `Planner.php:65`), reasoner at `:555-596` (`stage: reasoner`, read back from the persisted assistant message). Each row carries `session_mode`, `action_type`, `cycle_id`, `procedural_version`, model, token split and GBP cost. Both are fully guarded — the answer has already streamed, so telemetry must never break a turn.
- Provider token semantics are normalised before costing (`HasAiChat.php:1734-1760`): Anthropic reports cache read/creation separately from `input_tokens`; xAI folds cached reads into `prompt_tokens` and has no cache-creation tier.

### Token budgets — `HasAiGuardrails`

- **Weekly soft-degrade**: rolling 7-day total vs the tier's `fyn_weekly_token_budget` (`:96-120`). Exceeding it swaps to the cheapest model — provider-aware, because returning an Anthropic model name under xAI would break chat rather than degrade it (`:151-164`) — and prepends a plain-text notice to the system prompt (`HasAiChat.php:459-463`). Chat is never hard-walled by this.
- **Daily hard backstop**: today's total vs `fyn_daily_hard_backstop` (`:128-139`), an abuse ceiling only, surfaced as a `token_limit` frame (`HasAiChat.php:404-414`).
- Both limits are read from `TierConfigurationStore`; no hardcoded constants remain. Preview personas are never metered or degraded (`:113-115`, `:132-134`, `:237-238`).
- Output cap: 8192 tokens on premium, 4096 otherwise (`:177-185`). Complexity classification at `:190-213`.

### Audit chain

`AuditChainService` (`app/Services/AI/AuditChainService.php`):

- `append()` (`:89`) serialises the event, prefixes the previous row's `row_hash`, SHA-256s the lot, then HMAC-signs it — inside a `DB::transaction` with `lockForUpdate` so concurrent writers serialise and the chain stays single-threaded (`:11-18`).
- Hashed fields are fixed at `:77-87`: `user_id`, `conversation_id`, `tool_name`, `operation`, `status`, `input_summary`, `result_summary`, `entity_type`, `entity_id`. The chain and signature columns are deliberately excluded to avoid self-reference.
- JSON is deep-ksorted before encoding (`:28-36`) because MySQL's binary JSON column reorders object keys on storage, which would otherwise make read-back verification impossible.
- A missing `app.ai_audit_hmac_key` throws on the first audit write rather than signing with a constant fallback (`:52-69`).
- `verifyChain()` (`:196`) walks the table in id order and returns the first breakpoint id; `verifySignature()` at `:245`. Exposed via `ai:audit:verify-chain`.
- `appendEpisode()` (`:133`) writes the per-turn v2 episode attestation under the sentinel tool name `__episode__` (`:50`), binding the v1 payload plus the episodic blob SHA, the semantic snapshot id and a fetch-provenance digest.

Call sites: at tool dispatch (`CoordinatingAgent.php:984-991`, `status: dispatched`), at completion (`appendAuditCompletion()` at `CoordinatingAgent.php:1545`), on a GroundGate rejection (`HasAiChat.php:1699-1706`, `status: stripped`), and per turn from `persistEpisode()` (`HasAiChat.php:1525`, `:1575`).

### Learning — off by default, never auto-applied

`config/fyn.php:61-68`: `learning_enabled` defaults to `false`, and when false the pipeline is fully inactive.

When enabled:
- A planner `learn` action stages an episode, a `ProposedSemanticFact` (`status: pending`) or a `ProposedProcedureAmendment` (`status: pending`) — `FynLoop.php:232-243`, `:360-379`, `:388-407`. All three are flag-gated and try/catch-guarded.
- A turn that exhausts the cycle cap gets ONE extra planner consult framed on the failure, so it may propose a procedure amendment for engineering review (`FynLoop.php:280-296`).
- `ProposedFactSynthesiser` (`Learning/ProposedFactSynthesiser.php:21-72`) extracts candidate durable facts from a finished conversation, called from `ConversationSummariser::emitProposedFacts()` (`:96-122`). Its prompt at `:28-36` permits only durable traits and intentions and explicitly forbids monetary amounts, balances, any figure with a live source, and regulatory or tax facts — the pointer model, enforced in the prompt.
- `SemanticFactPromoter` (`Learning/SemanticFactPromoter.php:18-23`) is the ONLY path that writes a per-user semantic fact, and only from an approved proposal. It never touches the global corpus.

Storage paths for all three memory stores are `config/fyn.php:49-59`.

### Where Fyn calls into memory (detail out of scope here)

- `FynLoop.php:94,323-331` — procedural context, recall context and the episodic rubric layered into the planner prompt.
- `FynContextAssembler.php:53-54,89,100-117,125` — known facts, procedures, recalled episodes, semantic facts.
- `AdvicePromptBuilder.php:65` and `OnboardingPromptBuilder.php:38` and `OnboardingChatDirector.php:120` — `MemoryRetrieverService`.
- `app/Services/Account/RetentionPurgeService.php:47` — `FynMemoryStore::forget()` on account purge.

---

## 8. Where Fyn renders, per surface

**Web (`resources/js/`)**
- `components/Shared/AiChatPanel.vue`
- `components/Shared/AiChatButton.vue`
- `components/Fyn/FynOnboardingChat.vue`
- `components/Fyn/FynQuickReplies.vue`
- `store/modules/aiChat.js`
- `store/modules/aiFormFill.js`
- `services/aiChatService.js`
- `layouts/AppLayout.vue`, `components/SideMenu.vue`, `views/Dashboard.vue`, `views/GamifiedDashboard.vue` (entry points)

**`/m` (`resources/mobile/`)**
- `mixins/onboardingChat.js` — the single SSE consumer
- `components/FynCaptureForm.vue`
- `components/MobileChrome.vue`
- `views/ConversationHistory.vue`
- `views/Dashboard.vue`
- `utils/fynText.js`
- `utils/captureFormState.js`
- `api.js`

**iOS (`ios-native/Fynla/Features/Fyn/`)**
- `FynView.swift`
- `FynClient.swift`
- `FynEvent.swift`
- `FynEventReducer.swift`
- `FynConversationModel.swift`
- `FynMessageView.swift`
- `FynComposerView.swift`
- `FynQuickRepliesView.swift`
- `FynCaptureConfirmationView.swift`
- `FynModels.swift`
- `ConversationHistoryView.swift`
- `ConversationHistoryModel.swift`
- plus `ios-native/Fynla/Core/FynEditing/FynEditIntent.swift`

---

## 9. Findings worth raising

### A. The web store has four separate SSE switch statements, and they are not equivalent

`resources/js/store/modules/aiChat.js` handles events in four places:

| Action | Switch at | Notable missing cases |
|---|---|---|
| `sendMessage` (`:581`) | `:680` | — (the fullest consumer) |
| `streamNextQueued` (`:1056`) | `:1097` | `quick_replies`, `skip_link`, `onboarding_advance`, `onboarding_layout_change`, `fill_form`, `preview_cta`, `conversation_created`, `resume`, `title` present but no bubbles path |
| `postAction` (`:1300`) | `:1355` | `entity_created` / `entity_updated` / `entity_deleted`, `capture_complete`, `handoff_error`, `token_limit`, `consent_required`, `title` |
| `startOnboardingConversation` (`:1556`) | `:1644` | `entity_*`, `capture_complete`, `handoff_error`, `token_limit`, `consent_required`, `navigation` present but no `fill_form` |

The practical consequence of the second row: a queued turn that ends with quick-reply bubbles would stream its text with no bubbles rendered. This is the exact multi-consumer pattern the Rule 20 note in the `fyn-architecture` skill was written against ("three SSE consumers with one missing `navigation`"), now one consumer larger. Reported as a code shape; not browser-tested, so not a confirmed live defect.

By contrast `/m` has ONE consumer (`resources/mobile/mixins/onboardingChat.js:375-590`) and iOS has one (`ios-native/Fynla/Features/Fyn/FynEvent.swift:55-133`).

### B. `CoreIdentity.php` emits its placeholders literally

`app/Services/AI/Prompts/CoreIdentity.php:52,56` — `{\$personality}` and `{\$responseFormat}` inside an interpolating heredoc render as the literal strings `{$personality}` and `{$responseFormat}`. Verified against PHP directly. The legacy rollback prompt therefore ships with no personality block and no response-format block. Latent, not live, since `config/fyn.php:17` defaults to `unified`.

### C. Two provider readers skip the versioned cache key

`app/Services/AI/AdviceFyn.php:835` and `app/Services/AI/AiToolDefinitions.php:54` read the bare `ai_provider` key while `HasAiGuardrails.php:64-79` and `Planner.php:197-206` prefer `ai_provider:v{N}`. The admin toggle writes both (`AdminController.php:710-712`), so they agree today; an eviction of the legacy key alone would desync the tool catalogue's wire format from the active provider. Low severity.

### D. iOS does not consume the form frames

`ios-native/Fynla/Features/Fyn/FynEvent.swift:55-133` has no case for `capture_form`, `capture_form_errors`, `form_received`, `fill_form`, `thinking`, `onboarding_field_captured`, `onboarding_layout_change` or `capture_write_result`. This is consistent and intended: native does not send `X-Fynla-Forms: 1` (`AiChatController.php:1074-1078`), so the backend keeps the typed prompt for a form turn (`OnboardingChatDirector.php:135-141`). Recorded as a known surface gap, not a defect.
