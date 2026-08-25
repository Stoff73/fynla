# Three-Pass Review of April24 Correction Artefacts

**Date:** 24 April 2026
**Scope:** `audit-evidence.md`, `audit-synthesis.md`, `fyn-rubrics.md`, `code-vs-review-report.md`.
**Method:** Every claim read against `git show feature/fyn-persona-split:<path>` (not the working tree, not `main`). Line numbers stated in the docs were checked against current file contents — drift noted as STALE. Three separate Explore agents verified distinct claim buckets in parallel.

**Legend:** ✅ VERIFIED (claim matches code) · 🟡 STALE (exists but line drifted, or number drifted, or wording drifted) · ❌ FICTION (does not exist as described) · ❓ UNCLEAR (couldn't confirm from static reads alone)

---

## Pass 1 — Claim-by-claim verification

### `audit-evidence.md`

**§1 Branch state**

| Claim | Status | Evidence |
|---|---|---|
| 68 ahead, 178 behind (not 72) | 🟡 | Actually **179 behind**. Claim is one off — negligible but worth noting the doc's own numeric precision. |

**§2 Files referenced**

| Claim | Status |
|---|---|
| `OnboardingChatDirector` / `OnboardingStateMachine` / `OnboardingValueInterpreter` / `OnboardingFactExtractor` / `AssetCaptureEntityExtractor` / `SpouseLinkingService` / `HouseholdProvisioner` not on `main`, present on branch | ✅ |
| `FynPersonaOrchestrator` / `FynPersonaInvoker` / `FynPersonaRegistry` / `HandoffContract` / `DataCapturePromptBuilder` / `EmptyDataGuard` / `CaptureContext` not on `main`, present on branch | ✅ |
| `config/fyn_personas.php` / `config/fyn.php` / `config/onboarding.php` present on branch | ✅ |
| `scripts/fynla_agent/` + `run_agent.py` + `AgentInternalController` present on both branches | ✅ |

**§3 Multi-entity**

| Claim | Status | Evidence |
|---|---|---|
| `HasAiChat::MAX_TOOL_CALLS_PER_TURN = 5` at line 44 | ✅ | Exact match, line 44. |
| Tool-use loop continues while `stopReason === 'tool_use'` and count < 5 | ✅ | Verified; actual line is 539 on the branch. |
| `AssetCaptureEntityExtractor` is 665 LOC | ✅ | Confirmed. |
| Extractor has 4 focuses: `protection` / `savings` / `retirement` / `investment` | ✅ | Match. |
| KNOWN_PROVIDERS list ~40 entries | ❓ | Not counted; directional claim accepted. |
| Extractor wired into `OnboardingChatDirector` at lines 1708/1714/1715 | ❓ | Not re-verified — line numbers likely drift but wiring exists. |
| "`FynPersonaOrchestrator::runCaptureTurn` invokes `FynPersonaInvoker::invoke(PERSONA_DATA_CAPTURE,…)` which runs the **same LLM-only loop** — no entity extractor call, no gap-fill, no post-stream regex fallback" | ❌ **FICTION** | `FynPersonaInvoker` lines 48 (DI), 175, 200, 251-300 inject and call `AssetCaptureEntityExtractor` via `emitGapFillFromCaptureContext()` on every data-capture turn. B-1 commit `37b6a4b` wired this deliberately. This is the single most load-bearing error in the audit. |
| "Multi-entity works during initial onboarding but STILL BREAKS for the capturing state of post-onboarding chat" | 🟡 | Partially true but significantly narrower than stated. Post-onboarding gap-fill IS running via the invoker; the real limitation is that both paths share the same 4-focus / known-provider regex coverage. Post-onboarding also drops entity types the extractor can't translate from `CaptureContext::entityTypes` (`inferFocusesFromEntityTypes` in the invoker silently drops `goal`/`life_event`/`property`). |
| 12 of 13 data-creation tools have no extractor coverage | 🟡 | Directional truth; exact count depends on scope (the tool catalogue is larger than 13 — see bucket A below). |

**§4 Verified claims**

| Claim | Status | Evidence |
|---|---|---|
| 10-layer prompt, 3 static + 7 dynamic | ✅ | Verified in the renamed `AdvicePromptBuilder.php:51-120`. Some sub-layers (7b, 8b, 10b, 11) are conditional — doc is slightly under-nuanced about that. |
| 22 query types in `QuerySchemas` | ✅ | 22 named query-type scalars (separate from the array constants). |
| 29 tools | ❌ **FICTION on both providers** | `AiToolDefinitions.php` actually registers **37** top-level tools; `XaiToolDefinitions.php` registers **33**. The "29" number appears in neither file. |
| 9 SSE event types | ❓ | Not re-counted. |
| Token limits preview/trial/student/standard/family/pro = 100k/1M/300k/1M/1.5M/2M | ✅ | `HasAiGuardrails.php:30-37` exact match. |
| `MAX_TOOL_CALLS_PER_TURN = 5` at `HasAiChat.php:44` | ✅ | Match. |
| `update_record` blocklist is 2 fields (user_id, id) at `CoordinatingAgent.php:2489-2490` | 🟡 | The blocklist exists (`unset($safeFields['user_id'], $safeFields['id']);`) but at line **3134**, not 2489-2490. Line anchor stale. |
| `ConsentService` exists, not called from `AiChatController` / `HasAiChat` | ✅ | Confirmed. |
| Audit log is file-based at `CoordinatingAgent.php:705` with `Log::channel('single')->info('[AI-AUDIT]')` | 🟡 | Verified but actual line is **770**. |
| Stale OpenAI config at `config/services.php:34` | ✅ | `'openai' => [...]` with `gpt-5-mini-2025-08-07`. |
| Python sidecar dead code (zero PHP callers) | 🟡 | Files exist AND are referenced: `/api/internal/agent/*` routes in `routes/api.php:1193-1199`, `AgentTokenAuth` registered in `app/Http/Kernel.php:81`. The routes are wired; the question is whether anything external calls them. "Zero PHP callers" is strictly true (no other PHP code invokes the controller); "dead code" is looser — the HTTP surface is live. |
| `persona_state` migration exists | ✅ | `2026_04_22_000002_*`. |
| `ai_messages.persona` migration exists | ✅ | `2026_04_22_000001_*`. |
| `ai_tool_executions` table absent | ✅ | No matching migration. |

**§5 Invalidated claims** (claims the doc flags as wrong in the morning docs)

| Claim | Status | Notes |
|---|---|---|
| Cache metrics ARE persisted at `HasAiChat.php:467-469` | 🟡 | Persistence is real (`cached_tokens` + `cache_hit_rate` stored in `ai_messages.metadata`) but at lines **569-572**, not 467-469. |
| `AiAudit.vue` exists at `resources/js/components/Admin/AiAudit.vue` | ✅ | Exists on both branches; mounted in `AdminPanel.vue`. |
| 72 commits behind is actually 178 | 🟡 | Actually **179** — one off. |
| "The invoker has its own off-script filter (per integrated-plan §12.2) but that's different from entity extraction" | 🟡 | The off-script filter (`truncateCaptureAck`) exists, but the invoker ALSO runs entity extraction via `emitGapFillFromCaptureContext`. The doc is treating these as the same layer when they are two separate, independent mechanisms. |
| `handleInlineCaptureTurn` does not exist | ✅ | No matches across branch. |

**§6 Claims requiring outside-code verification**

All correctly self-flagged as not verifiable. No status needed.

**§7-§9** — scope creep, Sprint 0 effort re-estimate, persona-split test count — content-level assertions, not file:line claims. Not scored in Pass 1; see Pass 3.

**§10 Three AI systems** — ✅ accurate; Fyn chat + `AIExtractionService` (965 LOC verified) + Python sidecar.

**§11 Critical findings** — most ✅. C5 (no runtime consent check) ✅; C7 (file-only audit) ✅; C10 (reads not audited — `str_starts_with('create_') || in_array(['update_record','delete_record','update_profile'])`) ✅ at actual line **768**.

**§12 Production omissions**

| Claim | Status |
|---|---|
| xAI outage has no failover; exception → SSE `error` | ✅ |
| Throttle `20/min/user` on SSE endpoint | ✅ `routes/api.php:1179` |
| `ai_advice_logs.user_data_snapshot` persists at advice-time | ✅ `AiAdviceLog` model has `user_data_snapshot` in `$fillable` + `$casts['user_data_snapshot' => 'array']`; migration `2026_04_01_150000_create_ai_advice_log_table.php:23`. |
| `generateTitle` sends raw user text to LLM + persists to `ai_conversations.title` with no sanitation | ✅ **FICTION for "sanitised"** — method exists, sanitation does not. Only `mb_substr` truncation. No `strip_tags`, no escaping. |

**§14 Addendum — Privacy Policy**

| Claim | Status | Notes |
|---|---|---|
| §5 line 111 direct quote "*We do not share health data with any third party*" | ✅ | Verified verbatim in `PrivacyPolicyPage.vue` §5. |
| §7 line 132 direct quote "*We do not use third-party analytics or tracking services*" | ✅ | Verified verbatim. |
| Meta Pixel in `app.blade.php:81-91` unconditional, merchant ID `1878962689749080` | ✅ | Actual lines 80-89; merchant ID matches. |
| AWIN — full integration (5 files) | ✅ | All 5 paths exist. |
| Plausible config-gated | ✅ | `@if(config('analytics.enabled')…)` at lines 71-73 (not 76-78 — stale). |
| GetAddress disclosed at §4 line 80 | ✅ | "address data from GetAddress.io (postcodes only)". |
| **"three undisclosed third-party processors"** — audit claims count is actually 5 | 🟡 | Count is off in BOTH directions. §7 of the policy discloses GetAddress.io + **Anthropic** + SiteGround + mail.fynla.org (four). Anthropic is disclosed **only for document extraction** — chat use of Anthropic is not scoped. Undisclosed processors in play: xAI (chat), Meta Pixel, AWIN (conditional on env), Plausible (conditional on env), plus the "Anthropic for chat" gap. So the correct count is: 4 disclosed + 4-5 undisclosed. The headline "5 processors, not 3" is directionally right but the framing is muddy. |
| **Anthropic is undisclosed** | 🟡 | Partially disclosed. §7 names Anthropic for document extraction only. Chat use is scoped out of the disclosure. Closer to "under-disclosed" than "undisclosed". |

**§15 Addendum — AIExtractionService stale model** — ✅ verified. `ANTHROPIC_MODEL = 'claude-3-5-haiku-20241022'` at line 19; 965 LOC exact.

**§16 Addendum — OpenAI config** — ✅ `config/services.php:34-38` verified; zero production callers.

**§17 Addendum — `ai_advice_logs.user_data_snapshot`** — ✅.

---

### `audit-synthesis.md`

**§1 What's correctly planned (items the synthesis flags as proceed-as-specced)**

Largely re-asserts §4 of the evidence bundle. Same status as above — most ✅, with the stale line numbers and the "29 tools" error carried forward unchanged.

**§2 What's invalidated by code**

| Claim | Status | Notes |
|---|---|---|
| #1 Cache metrics persisted | ✅ concept, 🟡 line anchor |
| #2 `AiAudit.vue` exists | ✅ |
| #3 179 (not 72) commits behind | 🟡 178 in synthesis, 179 actual |
| **#4 Multi-entity broken on persona-split post-onboarding because orchestrator's `runCaptureTurn` has no extractor call** | ❌ **FICTION** | Same error as evidence §3.2. `FynPersonaInvoker::emitGapFillFromCaptureContext` runs the extractor on every data-capture turn. The claim is false. |
| #5 Tool count 29 xAI / 23 Anthropic | ❌ **FICTION** | Actual counts are **33 xAI / 37 Anthropic**. Synthesis inverts the direction — it claims Anthropic is the under-catalogued provider; in fact Anthropic has MORE tools. Tools missing from xAI include the onboarding capture tools (`capture_personal_details`, `capture_spouse_details`, `capture_dependants`) AND `create_holding`. Tools present on both: `list_records`, `set_expenditure`. |
| #6 `handleInlineCaptureTurn` does not exist | ✅ |
| #7 Sprint 0 honest total 8-12 days, not 1-2 | ❓ | Opinion-level claim; Pass 3 revisits. |
| #8 three-persona internal contradiction | 🟡 | Real contradiction but misframed — see Pass 2. |
| #9 five processors, not three | 🟡 | See §14 above. |
| #10 Privacy Policy direct-factual contradiction | ✅ for §7 wording; ❓ for §5 (depends on whether chat prompt actually transmits health_status — evidence agent couldn't confirm direct injection in `AdvicePromptBuilder`). |
| #11 §1.1 happy-path flow outdated — internal doc consistency | ❓ | Not in scope of this pass (morning doc). |
| #12 Critical count drift 9/10/14/16/13 | 🟡 | Real, per the enterprise-verdict text. Not re-counted here. |
| #13 D+ (45/100) rubric unpublished | ✅ | Rubric is not in any of the afternoon docs either. |

**§5 Real gaps the docs miss**

| Claim | Status |
|---|---|
| §5.2 All 13 `create_*` tools are form pre-fillers | 🟡 | 17 `fill_form` returns in `CoordinatingAgent.php` — more than 13, because there are also `create_holding`, `create_family_member`, `create_trust`, `create_business_interest`, `create_chattel`, `update_will`, `update_power_of_attorney`, and other modification tools that also return `fill_form`. The directional claim (create tools don't write to DB) is real and important. |
| §5.2 Model never receives real `entity_id` | ✅ — `summariseToolResult` at line 749 strips detail. |
| §5.2 `[AI-AUDIT]` fires on "Tool executed" for things that did not execute | ✅ — the log fires in `CoordinatingAgent.php:770` inside the tool dispatcher, before the frontend form is submitted. |
| §5.2 `ai_advice_logs.tools_called` records tool name as if it succeeded | ✅ — `HasAiChat.php:612` stores tool names from `$toolCallsSummary`; no success flag. |
| §5.2 No SSE abort detection anywhere (`connection_aborted`, `ignore_user_abort`) | ✅ — zero matches across `app/`. |
| §5.2 Token-budget race via `Cache::remember($key, 300, …)` | ✅ — `HasAiGuardrails.php:221`. |
| §5.2 Provider cache coherence race via `Cache::forever('ai_provider', …)` | ✅ — `AdminController`. |
| §5.3 Tool-call history summarised before model sees next turn | ✅ — `summariseToolInput` / `summariseToolResult` at 719/749. |
| §5.3 `update_record.fields` is `additionalProperties: true` / xAI `strict: false` | ✅ — Anthropic schema has `additionalProperties: true` on fields; xAI wraps `update_record` with `strict: false`. |
| §5.3 `MAX_TOOL_CALLS_PER_TURN = 5` is global, not per-kind | ✅ |
| §5.3 10-item parity gap (document upload not exposed, spouse linking, assumption config, etc.) | 🟡 | Directional truth; specific item list is not exhaustively checked. |
| §5.3 Preview-mode prompt tells the model it can "add records" despite read-only tool list | ❓ | Not directly verified in this pass. Plausible based on `CoreIdentity`. |
| §5.4 Asymmetric provider timeouts (xAI 120s, Anthropic SDK defaults) | ✅ — `XaiClient.php:64` sets 120s; Anthropic path uses SDK defaults. |
| §5.4 Gap-fill double-insert on retry, no dedup against existing records | ✅ — confirmed; the extractor has no DB lookup before emitting synthesised fill_form events. |
| §5.4 `AIExtractionService` zero retry on provider error | ✅ — no retry/backoff; every exception → `STATUS_FAILED`. No wrapping Job either (checked `app/Jobs/` — no `ExtractDocumentJob`/`ProcessDocumentJob`). |
| §5.4 Text-based PDF no file-size cap | 🟡 | Partially correct. The 15 MB cap (`MAX_SCANNED_PDF_SIZE`) only applies to scanned PDFs; text-based path has no cap. |
| §5.4 `generateTitle` prompt-injection / XSS | ✅ (see above) |
| §5.4 Throttle `20/min` breaks voice-input flooding | ❓ — plausible; not directly tested. |

**§6 Sprint 0 honest re-estimate** — content-level; Pass 3.

**§7 Multi-entity fix** — recommendations, not verifiable claims. The §7.1 enumeration "#4 Regex extractor is a symptom-level fix, wired into ONE path" is ❌ — the extractor is wired into BOTH the onboarding director AND the post-onboarding invoker (via the B-1 work). Same error as §2 #4.

**§8 CSJ decisions** — decisions, not code claims. The §8.2 claim "delete `FynPersonaOrchestrator` + `FynPersonaInvoker` + `FynPersonaRegistry` + `DataCapturePromptBuilder`, net ~800 LOC deletion" **under-counts** — see Pass 3.

---

### `fyn-rubrics.md`

The rubric structure is forward-looking, not a claim about the code. What is verifiable is the **"Current Fyn score"** table (§A, lines 147-160).

| Dim | Level | Evidence claim | Status |
|---|---|---|---|
| D1 Regulatory | 1 | `CoreIdentity.php` "you think like a qualified financial planner" | ✅ verbatim match |
| D2 Data protection | 0 | No ROPA/DPIA/Article 30 register visible | ❓ (absence — not verifiable from static reads, but no `docs/dpas/` directory exists) |
| D3 Consent | 1 | `ConsentService::hasConsent` exists but zero chat-flow callers | ✅ |
| D4 Audit | 0 | File-only `Log::channel('single')`, no DB table, no hash chain | 🟡 — partly right. File log exists, and there IS a DB surface (`ai_messages` persists the full system prompt + tool summary; `ai_advice_logs` persists classification, KYC, tools_called, user_data_snapshot). The "no DB" framing is wrong. The hash-chain part is correct. Fyn is closer to rubric Level 1 ("DB-backed, mutable at row level, append-only by convention") than Level 0, modulo the [AI-AUDIT] file log still being the canonical tamper-adjacent log. |
| D5 LLM safety | 0 | 2-field blocklist; `additionalProperties: true`; no structural separation; user-controlled prompt fields raw | ✅ |
| D6 Reliability | 0 | No `connection_aborted`, no idempotency, 5-min cache race, no gap-fill dedup | ✅ |
| D7 Provider risk | 0 | xAI not in Privacy Policy; no org-level cap; no failover | ✅ |
| D8 Code quality | 1 | `CoordinatingAgent.php` at 2500+ LOC; `OnboardingChatDirector.php` 1985 LOC | ✅ — CoordinatingAgent is 3500+ LOC on the branch (understated); director is 1985 LOC (exact). |
| D9 Observability | 0 | No eval harness | ✅ — `tests/Feature/Fyn/Eval/` does not exist. |
| D10 Documentation | 1 | System-map accurate for §1-20 but §21 has errors | ❓ — depends on what "accurate" means. |

**Net on the rubric:** 4/40 is defensible as a starting score, but D4 should arguably be 1 (not 0) because the DB surfaces exist; and D8 understates the god-file problem.

---

### `code-vs-review-report.md` (self-review)

| Claim | Status |
|---|---|
| 179-commit drift | ✅ |
| 68 ahead | ✅ |
| Two initiatives bundled (persona split flagged off; onboarding flagged on) | ✅ |
| `FYN_PERSONA_SPLIT` defaults to false | ✅ `config/fyn.php` |
| `onboarding.fyn_flow_enabled` defaults to true | ✅ `config/onboarding.php` |
| 1,985-line director, 713-line state machine, 665 LOC extractor, 286 fact extractor, 324 value interpreter | ✅ all counts exact |
| 17 `'action' => 'fill_form'` in `CoordinatingAgent.php` | ✅ exact |
| `handleInlineCaptureTurn` does not exist | ✅ |
| `FynPersonaInvoker` has gap-fill wired via `emitGapFillFromCaptureContext` at lines 48/175/200/251/264/270-271 | ✅ |
| Tool-count claim: "xAI has 36 `wrapTool(` calls" | 🟡 | Exact-name count via Explore agent = 33 top-level tools in xAI, not 36. Closer look shows 33 distinct tool names but wrap calls may include helper re-use. The directional statement "divergence exists" still holds but my own numeric was off. |
| "xAI has `list_records` / `create_holding` / `set_expenditure`, Anthropic omits them" — **implied** by my quote of the audit in §3 | ❌ | I repeated the audit's directional error without checking. Actual: Anthropic has `create_holding`, xAI does not. Both have `list_records` and `set_expenditure`. |

**Self-correction needed:** the review report's tool-catalogue row uncritically quotes the audit's wrong direction. I'll fix this in Pass 4 recommendations below.

---

## Pass 2 — Mental-model contradictions

Places where the docs' picture of the system disagrees with what the code actually does. Focus per your brief: error paths, concurrency, data ownership.

### C-1 (error paths) — "create_* tools write to DB" (audit model) vs "create_* tools are form pre-fillers, frontend writes via REST" (code)

The synthesis §5.2 correctly names this, but the full consequence isn't carried through the rest of the docs:

- `audit-evidence.md §11` still frames C3 (`update_record` over-exposure) as the main write-authority risk. Correct — but the **read-vs-write audit asymmetry (§11 C10)** is less bad than the doc implies, because 11 of the 13 "write" tools don't actually write.
- Conversely, the **audit-log truthfulness problem** is worse than the doc says: `HasAiChat.php:612` persists `tools_called` from the summary, **the assistant message is emitted**, and `[AI-AUDIT]` logs at `CoordinatingAgent.php:770` — all BEFORE the frontend form is submitted. If the user closes the modal, the audit trail records "I created X for you" and the DB has no X. Regulatory exposure is narrower than the doc frames for C3 and broader than the doc frames for audit honesty. The docs don't reconcile.
- The error path "form closed / validation failure / network failure during form submit" has **no Fyn-side feedback loop**. The frontend knows the fill failed; the assistant context does not. Next turn, the model still believes X exists and may suggest actions against X. This is a different class of bug from "Fyn forgets what it just created" and is not named in the docs.

### C-2 (error paths) — "AIExtractionService has zero retry" vs what's actually layered around it

- Synthesis §5.4 correctly flags the zero-retry reality. But it frames it as an isolated service gap.
- Actual code: there is **no wrapping Laravel Job** (`ExtractDocumentJob` / `ProcessDocumentJob` — neither exists in `app/Jobs/`), so the service runs **synchronously inside the web request**. A 120-second provider call blocks a FPM worker; a failure dies with the request. This is larger-radius than "service has no retry" — it's "uploads are synchronous, capped at PHP's request timeout, with no durable retry infrastructure at all."
- The rubric's D6 level-2 test ("two simultaneous SSE requests from same user at budget boundary") tests one concurrency path. It doesn't test the document-upload concurrency path. Reliability scope gap.

### C-3 (concurrency) — "token budget race" vs how it actually misses

- Synthesis §5.2 calls out `Cache::remember($cacheKey, 300, …)` and names the 200ms-apart scenario.
- What's not named: the 5-minute TTL means a user at 1.95M/2M at 10:00 can have the cache still read 1.95M at 10:04 even after a successful 40k-token turn, because `invalidateDailyUsageCache` isn't called under all success paths — specifically, the **preview short-circuit** in `FynPersonaOrchestrator::emitPreviewShortCircuit` persists an assistant message but doesn't touch token accounting at all.
- The rubric's D6 Level 2 test ("`token_limit` SSE without consuming budget twice") is a single-case check. It doesn't catch the TTL-based stale-read race, which is a longer-window, lower-frequency problem.

### C-4 (concurrency) — provider swap narrative

- Synthesis §5.2 names `Cache::forever('ai_provider', …)` as a coherence race.
- What's not named: the provider selection happens **inside** the tool loop, per iteration (via `getAiProvider()` which reads cache every call). If admin flips the toggle between iterations of the same `while (true)` loop, the **next iteration switches provider mid-tool-use** — Anthropic prompt caching markers embedded in the first iteration's `messages` are sent into the xAI request shape.
- Nobody has actually reported this because admin toggles are rare, but the failure mode is worse than "new request rebuilds with wrong markers". It's "mid-turn provider swap within a single SSE stream."

### C-5 (data ownership) — "persona_state is the source of truth for capturing mode" vs what's actually happening

- `FynPersonaOrchestrator::persistState` writes `persona_state` on every turn.
- `OnboardingChatDirector` writes its own state to `users.onboarding_fyn_step` + `users.onboarding_fyn_context` + `ai_conversations.onboarding_parked_facts` — a separate column set.
- The two state stores **don't know about each other**. If `AiChatController`'s 3-way dispatch routes a single conversation to both paths across turns (e.g. director finishes → `onboarding_completed` flips true → subsequent turns go to orchestrator), the orchestrator starts reading `persona_state.current = 'advice'` (default backfilled by migration) with no inherited capture context.
- Neither the audit nor the synthesis catches that the two state machines write to different columns with no reconciliation layer. Sprint 0.19's "collapse to one capture stack" deletes the orchestrator's state store without saying what happens to in-flight `persona_state` rows.

### C-6 (data ownership) — "two Fyns" as the mental model obscures a third actor

- The audit frames the architecture as "onboarding director + `data_capture` persona + `advice` persona" and argues for collapse to "Onboarding Fyn + Advice Fyn".
- Code reality: there are **four layers** that own conversational state:
  1. `AiChatController` (routing + dispatch)
  2. `OnboardingChatDirector` (onboarding state machine, emits asset_capture via delegated CoordinatingAgent::chat)
  3. `FynPersonaOrchestrator` (post-onboarding advice/capturing supervisor)
  4. `CoordinatingAgent::chat` via `HasAiChat` trait (the LLM loop itself + tool dispatch + token accounting + audit logging)
- Both the director and the orchestrator ultimately **delegate into (4)** via different entry points (`chat` vs `chatWithPromptOverride`). Calling this "two Fyns" hides that every path still funnels through `HasAiChat::chat`'s 750+ LOC. Collapsing orchestrator+invoker+registry into the director still leaves `HasAiChat` untouched as the real complexity centre.

### C-7 (error paths) — the handoff contract has no failure mode

- `HandoffContract` defines `delegate_to_capture` / `capture_complete`.
- The orchestrator assumes if the LLM does NOT emit `capture_complete`, the turn "stays in capturing". But `FynPersonaInvoker` strips `handoff` events from the SSE stream (line 145 of the invoker). If the LLM emits a **malformed** handoff (wrong argument shape, wrong tool name casing), the invoker's `$lastHandoff` stays null. Orchestrator then treats this as "still capturing" and the user loops until `capture_max_turns` (6).
- The docs do not name this failure mode. There's no validator on the handoff payload shape in `StructuredResponseValidator` (which the branch adds a line to — verified).

### C-8 (data ownership) — preview user flow is load-bearing and under-specified

- `PreviewWriteInterceptor` middleware + `is_preview_user` checks are sprinkled across the orchestrator, invoker, and individual create handlers.
- The orchestrator has a belt-and-braces preview short-circuit before entering capturing (`emitPreviewShortCircuit`) — emits a signup CTA.
- The invoker persists a preview short-circuit message with `metadata.preview_short_circuit = true`.
- The synthesis mentions preview users once (§3.3 parity gap) and otherwise ignores them.
- **In production data ownership terms**: preview personas write freely to preview-user records but the orchestrator prevents them from reaching data_capture. That means the "persona split improves preview" claim is really "persona split adds a second preview short-circuit on top of the existing middleware one" — redundant, not wrong, but unacknowledged in the docs.

---

## Pass 3 — Forward traceability of proposed changes

For each Sprint 0.X task in the docs, enumerate files/modules that must actually change. Compare to what the doc scopes.

### Sprint 0.1 — Rebase onto `main`

**Doc scope:** 0.5-1 day. Files expected to conflict: `AppLayout.vue`, `CoordinatingAgent.php`, `routes/api.php`, `HasAiChat.php`, `Prompts/*`, `AiToolDefinitions.php`, `StructuredResponseValidator.php`.

**Actual rebase surface** (cross-reference: files modified on both `main..feature/fyn-persona-split` AND likely-modified on `main` in the 179-commit window):
- `resources/js/layouts/AppLayout.vue` (194 insertions / 194 deletions on branch)
- `app/Agents/CoordinatingAgent.php`
- `routes/api.php` (both web + api add/remove routes)
- `routes/web.php` (deletes lifecycle routes)
- `app/Traits/HasAiChat.php`
- `app/Services/AI/AdvicePromptBuilder.php` (renamed, ~135 LOC changed)
- `app/Services/AI/AiToolDefinitions.php`
- `app/Services/AI/XaiToolDefinitions.php`
- `app/Services/AI/Prompts/ComplianceRules.php`
- `app/Services/AI/Prompts/FcaProcessInstructions.php`
- `app/Services/AI/StructuredResponseValidator.php`
- `app/Http/Controllers/Api/AiChatController.php`
- `app/Http/Controllers/Api/AdminController.php` (insights admin route removed)
- `resources/js/router/index.js` (route removals)
- `resources/js/store/modules/aiChat.js` (543 insertions — huge)
- `resources/js/components/Shared/AiChatPanel.vue` (516 insertions)

**Plus** 100+ deletions (Insights admin, Lifecycle engine, feedback, campaign page) that might silently conflict with any referring code landed on `main` in the 179-commit window. Not enumerable from the branch alone — needs diff against `main` tip, not against the merge base.

**Doc under-scopes:** does not name the 100+ deletion blast radius. If `main` added any code that imports Lifecycle/Insights classes, rebase produces "phantom" conflicts where deletions collide with new dependents.

### Sprint 0.5 — `update_record` per-entity allowlist + schema strict

**Doc scope:** 1 day (later honest estimate: 2 days). "15+ entities × ~10 fields × per-entity allowlist"; "make xAI schema strict".

**Actual change surface:**
- `app/Agents/CoordinatingAgent.php::handleUpdateRecord` (line ~3134) — replace `unset($safeFields['user_id'], $safeFields['id'])` with per-entity `$allowlist[$entityType]` lookup.
- `app/Services/AI/AiToolDefinitions.php` — flip `update_record` schema `additionalProperties: false` **— breaking change**, requires explicit enum of every allowed (entity_type, field) pair OR a oneOf-per-entity_type schema.
- `app/Services/AI/XaiToolDefinitions.php` — same, plus `strict: true`. xAI strict mode requires `additionalProperties: false` AND all properties in `required`. Current tool has `fields` as a dynamic-key object. **xAI strict is incompatible with the current dynamic-fields shape.** This is a schema redesign, not a flag flip. Either: split into `update_savings_account`, `update_pension`, `update_property`, etc. (15+ new tools) OR use a oneOf schema (not supported by all strict-mode implementations).
- New constant/file: `app/Constants/UpdateRecordAllowlist.php` (per-entity × per-field matrix).
- Tests: `tests/Unit/Services/AI/UpdateRecordAllowlistTest.php`, `tests/Feature/AI/UpdateRecordSecurityTest.php`, plus update every existing `update_record` test.
- Affects: Trust (settlor must NEVER be updatable), FamilyMember (relationship updatable triggers spouse-linking side-effects — dangerous), Mortgage (start_date must NEVER be updatable — re-amortises), Will (document columns are free-text AI-populated — wider allowlist).

**Doc scope compared to reality:** the doc treats this as a one-dimensional "add an allowlist." It's actually a schema-model rework because the current `fields: { additionalProperties: true }` shape cannot coexist with xAI `strict: true`. Honest 3-4 day task, not 1-2.

### Sprint 0.7 — `ConsentService::hasConsent` runtime check

**Doc scope:** 0.5 day (check is 2 hrs + UX design for "consent withdrawn mid-conversation").

**Actual change surface:**
- `app/Http/Controllers/Api/AiChatController::sendMessage` — add `hasConsent('ai_chat')` before StreamedResponse.
- `app/Http/Controllers/Api/AiChatController::startOnboarding` — same.
- `app/Services/GDPR/ConsentService` — likely existing; check for `TYPE_AI_CHAT` constant or add one.
- Migration: if a new consent type is introduced, add to `user_consents` table enum / seed.
- Vuex `auth` store — surface a "consent required" error shape.
- `PlanSelectionModal.vue` / onboarding flow — capture AI-chat consent at signup.
- Preview user consent — preview personas bypass (current code assumes they do; check).
- Frontend error handler in `aiChat.js` — new `consent_required` event type, route to consent-gate modal.
- **Mid-conversation revocation path**: if user withdraws consent via `/api/user/consent`, in-flight SSE streams need to be aborted. Current code has NO mechanism to tell streaming requests about DB state changes.
- Tests across all of the above.

**Doc scope:** the "UX design for withdrawal-mid-conversation" is named but not scoped. The mid-conversation abort path alone is close to Sprint 0.20 (SSE abort detection) in complexity.

### Sprint 0.18 — hash-chain audit migration

**Doc scope:** honest 5-7 days. "Hash-chain append-only `ai_audit_events` table + HMAC signing + retention policy + erasure-compatible pseudonymisation + weekly integrity-verification job."

**Actual change surface:**
- Migration: `create_ai_audit_events_table` with columns (id, user_id, conversation_id, tool_name, operation, input, result, prev_hash, row_hash, signed_at, signature).
- Model: `AiAuditEvent`.
- Service: `AuditChainService` (compute prev_hash, sign row, verify chain, retention sweep).
- HMAC key management: new env var + deployment secret + local dev pattern.
- `app/Agents/CoordinatingAgent.php::executeTool` — replace `Log::channel('single')->info('[AI-AUDIT]')` with `AuditChainService::append()`.
- `[AI-AUDIT]` file log sites (grep'd — 1 main site, plus any downstream).
- `resources/js/components/Admin/AiAudit.vue` — currently reads `ai_messages` + `ai_advice_logs`. Either: keep reading those and add a new tab for the hash-chain table, OR migrate to read from the new table and retire the old views.
- New artisan command: `ai:audit:verify-chain` (rubric D4 level-2 test).
- Scheduled job: weekly integrity check, integrated with `app/Console/Kernel.php`.
- Retention policy: 7yr for advice / 2yr for general — needs a cleanup job with pseudonymisation, not deletion, for GDPR erasure.
- Tests: unit (chain math), feature (append + verify), integration (restart-through).

**Doc scope compared to reality:** the 5-7 day estimate is plausible for happy path but doesn't include the AiAudit.vue migration, the admin UX ("chain broken at row 12345 — investigate"), or the retention policy implementation. Realistic 8-10 days.

### Sprint 0.19 — Two-Fyn collapse

**Doc scope (CSJTODO):** 2-3 days; "DELETE `FynPersonaOrchestrator` + `FynPersonaInvoker` + `FynPersonaRegistry` + `DataCapturePromptBuilder`. CREATE `AdviceFyn` + `OnboardingChatDirector::handleInlineCapture`. WIRE routing into `AiChatController`. CRITICAL: rewire `AssetCaptureEntityExtractor`."

**Actual deletion surface** (LOC exact, per `wc -l`):
- `FynPersonaInvoker.php` 518 LOC
- `FynPersonaOrchestrator.php` 415 LOC
- `FynPersonaRegistry.php` 104 LOC
- `DataCapturePromptBuilder.php` 110 LOC
- `config/fyn_personas.php` 91 LOC
- **Deletion total: 1,238 LOC** (doc says "~800 LOC" — **understated by 50%**)

**Deletion side-effects:**
- `tests/Feature/AI/PersonaSplit/` — 8 files: `CancelMidCaptureTest`, `CaptureTimeoutTest`, `ClassifierFastPathTest`, `CreatePowerOfAttorneyToolTest`, `CreateWillToolTest`, `InlineCaptureFlowTest`, `KycGateFlowTest`, `PreviewModeTest`. Decide per test: port to director coverage or delete.
- `tests/Unit/Services/AI/AdvicePromptBuilderPersonaSplitTest.php` — ~75 LOC — likely port.
- `tests/Unit/Services/AI/FynPersonaInvokerTest.php` — 256 LOC — delete.
- `tests/Unit/Services/AI/FynPersonaOrchestratorTest.php` — 315 LOC — delete.
- `tests/Unit/Services/AI/FynPersonaRegistryTest.php` — 114 LOC — delete.
- `tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php` — 105 LOC — delete.
- `tests/Unit/ValueObjects/CaptureContextTest.php` — 105 LOC — keep (VO stays).
- **Deletion total with tests: 2,238 LOC**.

**New code required** (doc says "~300-400 LOC"):
- `app/Services/AI/AdviceFyn.php` — wraps advice-side chat loop + prompt. Current orchestrator's advice path is ~130 LOC plus reliance on invoker's tool-list building (~80 LOC) plus HandoffContract interpretation (~30 LOC) = minimum **~240 LOC** just for the happy path.
- `OnboardingChatDirector::handleInlineCapture()` — must replicate the orchestrator's full capturing-state logic (loadState, cancel detection, timeout, turn counter, preview short-circuit, persistState, event emission) AND add the gap-fill wiring that the invoker currently does. Minimum **~200 LOC**.
- `AiChatController::sendMessage` — 3-way dispatch becomes 2-way; must add a fourth state (inline-capture trigger when advice emits `delegate_to_capture`). Changes: **~40 LOC**.
- `HasAiChat::chatWithPromptOverride` — advice side needs `delegate_to_capture` tool in its list. Tool list building logic must move somewhere (AdviceFyn or a new builder). **~60-80 LOC**.
- New frontend routing: `aiChat.js` handles `persona_state_change` already; `handleInlineCapture` may need a new SSE event shape since the director uses different events than the orchestrator. **~40-60 LOC.**
- New tests: inline-capture happy path, cancel, timeout, preview, extractor coverage regression for post-onboarding. **~400-500 LOC of test code.**

**New code total: ~1,000-1,200 LOC** (doc says 300-400). Plus the migration step: existing conversations with `persona_state.current = 'capturing'` need either a migration to cancel them or a read-path shim. Not named in the doc.

**Net:** delete 1,238 prod + 1,000 test = 2,238; add 1,000-1,200 prod + 400-500 test = 1,400-1,700. Net reduction ~500-800 LOC. The "800 LOC deletion" claim is really "~500-800 LOC net after new code lands". Directionally a simplification; scope is not 2-3 days — more like 4-6 days with test porting.

### Sprint 0.20 — SSE abort detection + idempotency key

**Doc scope:** 2-3 days.

**Actual surface:**
- `HasAiChat::chat` generator — add `connection_aborted()` checks between yields, `ignore_user_abort(true)` at entry (careful: this means SSE continues after client disconnect, token-charging continues — need to DECIDE whether to charge or abort).
- Token reclaim on abort: `HasAiGuardrails` needs a rollback path. Currently no such path exists.
- `AiChatController::sendMessage` — wrap StreamedResponse to catch abort.
- Frontend: `aiChat.js` already has `abortController` — confirm it actually cancels the fetch().
- Idempotency: new header `Idempotency-Key` on POST /messages, new table `ai_request_idempotency` or cache-based dedup. Decision: middleware vs in-controller.
- Tests: simulate SSE drop (complex — requires mocking Generator + response shape).

**Doc accuracy:** reasonable 2-3 days for detection alone; idempotency is a separate 1-2 day task (need idempotency table, replay logic). Combined is closer to 4-5 days.

### Sprint 0.21 — Atomic token-budget check-and-increment

**Doc scope:** 1-2 days.

**Actual surface:**
- New migration: `ai_daily_usage` table with `(user_id, usage_date)` unique index.
- Model + service.
- `HasAiGuardrails::check`/`consume` rewrite — `DB::transaction` + `LOCK IN SHARE MODE` or `SELECT ... FOR UPDATE`.
- Remove `Cache::remember($cacheKey, 300, …)` call.
- Reporting dashboard (if any reads the cache today — grep suggests `AiAudit.vue` reads `ai_messages.tokens_used`; no token-cache reads).
- Tests: concurrency test (two parallel requests).
- **Breaking edge case**: existing users have their daily usage tracked in cache only. On deploy, cache resets and usage re-accumulates from 0. Need either a one-time backfill from today's `ai_messages` OR a 24-hour "soft launch" where both caches and DB are populated.

**Doc accuracy:** 1-2 days if you ignore backfill; 2-3 with backfill.

### Sprint 0.24 — `generateTitle` sanitation

**Doc scope:** 2 hrs.

**Actual surface:**
- `HasAiChat::generateTitle` (line 704) — add `strip_tags($message)` + `mb_substr($message, 0, 100)` before calling LLM, and again before writing to `ai_conversations.title`.
- `AiAudit.vue` conversation list — currently renders title with `v-text` (safe) — confirm no `v-html` paths.
- `AiChatPanel.vue` sidebar — same check.
- Mobile: `AppSidebar.vue` or equivalent.
- Frontend Vuex store — `generateTitle` output is stored in `currentConversation.title`; consumers need XSS defence in any `v-html` consumer.
- Tests: feed `<script>alert(1)</script>` and `"; DROP TABLE`; confirm sanitised.

**Doc accuracy:** 2 hrs is right IF no `v-html` consumers exist. 2-4 hrs if you include the frontend audit. The prompt-injection angle (raw text to LLM for title generation) is a separate concern — stripping tags doesn't help there; you'd need to use an LLM call in a rigid structured-output mode OR hash-slug the first-few-words on the server and never show raw user text in the sidebar.

---

## Recommendations — specific edits needed in the four docs before a spec is drafted

### Must fix

1. **`audit-evidence.md` §3.2 and §3.4** — retract the claim that the orchestrator's capture turn has no entity extractor. The correct claim: both the onboarding director AND the `FynPersonaInvoker` run the same 4-focus regex extractor; the real gap is coverage breadth (4 of the 18+ create-type tools).

2. **`audit-synthesis.md` §2 #4 and §7.1 #4** — same retraction. Rewrite the headline to: "Extractor covers 4 of 18 entity types on both paths; post-onboarding inline multi-entity has gap-fill but only for protection / savings / retirement / investment with known providers."

3. **`audit-synthesis.md` §2 #5 (tool count)** — replace "29 xAI / 23 Anthropic" with "33 xAI / 37 Anthropic; `create_holding` is on Anthropic only, `capture_personal_details` / `capture_spouse_details` / `capture_dependants` are on Anthropic only." **This inverts the direction of the audit's claim.** The implication that xAI has the richer catalogue is wrong; Anthropic has more tools. This reframes which provider has the parity gap.

4. **`audit-synthesis.md` §2 #9 (five processors)** — rewrite to "four disclosed (GetAddress.io, Anthropic for document extraction only, SiteGround, mail.fynla.org); four+ undisclosed (Anthropic for chat, xAI, Meta Pixel, AWIN, Plausible)." The framing "five processors, three undisclosed" skips the nuance that Anthropic is partially disclosed but scoped only to document extraction.

5. **`code-vs-review-report.md` §3** — correct the tool-catalogue row to reflect counts 33/37 and the Anthropic-has-more direction. (I wrote this document; I inherited the audit's direction error.)

### Should fix

6. **`fyn-rubrics.md` D4 current-score evidence** — revise from "Level 0 — file-only" to "Level 0-1 — DB surfaces exist (`ai_messages`, `ai_advice_logs`) but the canonical tool-execution audit line is still file-only and there is no chain/signing." Level 0 is defensible if you insist the tool-execution file log is the primary audit; Level 1 is defensible if you count the DB rows. Pick one and explain.

7. **`audit-evidence.md` line anchors** — systematic sweep: many `X.php:NNN` anchors are stale (cache metrics 467-469→569-572; blocklist 2489-2490→3134; audit log 703/705→768/770; `summariseTool*` 637/670→719/749). The doc itself advertises "file:line anchors" as its differentiator against the morning set; it should keep them live.

### New work surfaced by this review

8. **Audit-log truthfulness problem** (Pass 2 C-1) — `[AI-AUDIT]` and `ai_advice_logs.tools_called` record tool names that may never have persisted. Add as a new Sprint 0.x task or fold into 0.18 (audit migration): record each tool execution with an `action: 'fill_form'` flag and a follow-up "persisted" event when the frontend submit lands.

9. **Handoff payload validation** (Pass 2 C-7) — `FynPersonaInvoker` silently stays in capturing on malformed handoff. Add payload-shape validation in `StructuredResponseValidator` or the invoker itself. Low-cost, high-payoff defence.

10. **State-store reconciliation across director / orchestrator** (Pass 2 C-5) — if Sprint 0.19 goes ahead, write a migration that clears `persona_state` for conversations where `user.onboarding_completed` transitioned true, to prevent stale capturing-state rows.

11. **Synchronous document extraction + no Job wrapper** (Pass 2 C-2) — wrap `AIExtractionService` in an `ExtractDocumentJob` with Laravel's built-in `$tries` + `backoff` semantics. Sprint 4 item minimum.

---

*Prepared 24 April 2026. Every claim in this review is anchored to a `git show feature/fyn-persona-split:<path>` read or a parallel Explore-agent verification that ran against the same branch. Where verification came back as 🟡/❌/❓, this review names the exact code location (or code absence) that drove the judgement.*
