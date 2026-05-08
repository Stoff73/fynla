---
type: investigation_report
date: 2026-05-08
sessions: 11, 12, 13
branch: fyn-net-worth-tool
status: investigation complete — fixes approved by CSJ, implementation pending
related_pr_to_fold_in: 263
hard_deadline: 2026-05-15 (xAI grok-4 family retirement)
---

# Fyn Net-Worth Bug — Investigation & Fix Plan

## TL;DR

User Chris Jones asked "What is my net worth?" on csjones.co/fynla and Fyn replied **£260,000**. The canonical figure from `NetWorthService::calculateNetWorth()` is **£598,250**. Under-report of **£338,250 (57%)**.

Three sessions of investigation have produced two false-starts (sessions 11 and 12) and one solid root-cause (session 12, verified live on csjones DB). This report is the audit trail of what was tried, what was wrong, what is right, and the fixes CSJ has approved for the implementation that begins next.

The bug is **not** a missing tool, **not** a broken xAI parameter, **not** a model choice problem. It is a query-classifier routing bug: "What is my net worth?" misclassifies as `general` → `factual`, which strips the canonical net-worth string AND the entity list out of the prompt. The LLM is asked to compute an answer it has no data for, and hallucinates.

## Symptom

- **User:** chris@fynla.org (user 1 on csjones), conversation 31, message 342.
- **Question:** "What is my net worth?"
- **Fyn replied:** "Your net worth is **£260,000.00**…"
- **Canonical (`NetWorthService::calculateNetWorth($user)`):** £598,250.
- **Reproducible:** identical hallucination across multiple turns; classifier reproducibly misroutes the same phrasing.

## Investigation timeline

### Session 11 (8 May, context-clear)

Surfaced the bug while browser-testing PR #263 on csjones. Diagnosed it as **two distinct bugs** based on tool-call audit alone:

- BUG A: `list_records` sequence missed `business_interest` and `chattel` entity types — £165k of Chris's assets invisible (£150k Jones Consulting + £15k Rolex).
- BUG B: even with the incomplete data, the LLM should have summed to £433,250, not £260,000 — possible `reasoning_effort=none` regression on prose arithmetic.

**Recommended fix (session 11):** add a `get_net_worth` tool wrapping `NetWorthService::calculateNetWorth` and steer the LLM to use it.

### Session 12 (8 May, context-clear)

CSJ stopped the session-11 plan before any code shipped: "I dont want you just sending requests to the LLM, as it costs money, and is not effecient" and pointed to the admin AI tab observation that the system prompt appeared to be missing. Approved a 6-phase read-only diagnostic.

Phase outputs:

- **System prompt IS being sent.** Confirmed at `app/Traits/HasAiChat.php:240-243` — `[['role'=>'system','content'=>$systemPrompt]]` is unconditionally merged with the message history. Cache-hit metric (52.7% on turn 2) further confirms.
- **`ai_messages.system_prompt` stores SHA-256 hash, not full text** (`HasAiChat.php:715-720`). That is why the admin AI audit shows no prompt content. By design (April30 F-8 PII rationale).
- **Live csjones verification** (via tinker over SSH): conversation 31 message 342 has `system_prompt = sha256:43c24e7…` identical to message 340 ("Hi Fyn" reply) — same prompt for both turns. Tool-call audit (events 114-127) shows 7× `list_records` for: `savings_account`, `investment_account`, `dc_pension`, `db_pension`, `property`, `mortgage`, `estate_liability`. **Zero** calls for `business_interest` or `chattel`. Canonical DB has `BusinessInterest id=3 "Jones Consulting" sole_trader £150,000` and `Chattel id=12 "vintage Rolex Submariner watch" jewelry £15,000` — exactly the £165k Fyn missed. `AiAdviceLog` count for conversation 31 = 0 (factual classifications don't write advice logs per `HasAiChat.php:745-746`).
- **Root cause located in classifier.** `QueryClassifier::classify("What is my net worth?", null)` returns `{primary: "general", related: [], modules: []}`. `general` → `AdviceFyn::engineCallLevelFor("general")` → `"factual"`. `app/Services/AI/AdvicePromptBuilder.php:149-169` strips Layer 5 (`<financial_context>`) and Layer 6 (`<existing_records>`) when `isFactual=true`. Layer 5 is what calls `NetWorthService::calculateNetWorth($user)` to put "Total net worth: £598,250" in the prompt. Layer 6 is what lists `BUSINESS: [ID:3 Jones Consulting £150,000]` and `CHATTELS: [ID:12 …Rolex… £15,000]`. **Net effect: chris's prompt for "What is my net worth?" had neither the canonical answer nor entity mentions of business/chattel.**
- **Six phrasings tested live on csjones** (same user, same DB):
  - `"What is my net worth?"` / `"Net worth"` / `"How much am I worth?"` → classifies as `general` (broken).
  - `"What's my financial position"` / `"How am I doing financially?"` → classifies as `holistic_health` (works).

**Recommended fix (session 12):** 4 lines in `app/Constants/QuerySchemas.php`. Move the dead `'/\bnet\s+worth\b/i'` patterns from the unreachable GENERAL block (lines 374-380, dead because `QueryClassifier::findAllMatches:157` skips GENERAL) into HOLISTIC_HEALTH (line 229). NOT a new tool.

CSJ then made two corrections mid-tripwire:

1. **"Why are you referencing OpenAI, we are using xAI?"** — Correct. The Fynla code uses the OpenAI PHP SDK pointed at xAI's base URL (`XaiClient.php:71-74` `withBaseUri($baseUrl)`), so the SDK's request shape happens to be OpenAI-compatible by SDK choice — but when reasoning about parameter semantics for grok-4.3, I MUST read xAI's grok-4.3-specific docs, not assume OpenAI parity. Session-12 report's "OpenAI-compatible shape" framing was sloppy.
2. **"reasoning_effort is documented at https://docs.x.ai/developers/model-capabilities/text/reasoning#effort-levels"** — Session 12 claimed it was undocumented, based on a generic xAI doc page. CSJ correctly pointed to the model-capabilities reasoning page which does document effort levels. Session-12 claim that `'none'` is invalid was unverified — wrong doc was read.

Context tripwire fired before re-do.

### Session 13 (8 May, this session)

Phase 5 auto-resume of the session-12 handover. Re-read the correct xAI grok-4.3 docs. Verdict below.

## What was wrong (claims withdrawn)

| Claim | Where | Status |
|---|---|---|
| "Add `get_net_worth` tool to fix the bug" | Session 11 handover | **WRONG.** `NetWorthService::calculateNetWorth()` already produces the canonical figure and would already reach the prompt if classification routed correctly. Adding a tool would mask the real bug. |
| "`reasoning_effort='none'` is undocumented for grok-4.3" | Session 12 synthesis | **WRONG.** `docs.x.ai/developers/model-capabilities/text/reasoning#effort-levels` documents four valid values (`none` / `low` / `medium` / `high`), all supported by grok-4.3. Default if omitted is `low`. `none` = "Disables reasoning entirely; no thinking tokens are used". |
| "H7: temperature=0 + reasoning_effort=none degenerate output" | Session 12 synthesis | **RETRACTED.** Both params are valid and behaving as documented. The hallucination is caused by the missing prompt content (classifier bug), not by a degenerate parameter combination. |
| "OpenAI-compatible shape" framing | Session 12 synthesis | **REPHRASED.** The OpenAI SDK is the transport, but parameter semantics must be verified against xAI grok-4.3 docs, not assumed from OpenAI parity. |

## What is right (findings retained)

The classifier root-cause is independent of the xAI question and was verified live on csjones DB. It stands:

1. `QueryClassifier::classify("What is my net worth?", null)` returns `general`.
2. `general` → `factual` engine level.
3. `factual` strips Layers 5 and 6 from the prompt.
4. LLM is asked to answer with no canonical net worth and no entity list → hallucinates.
5. The patterns at `QuerySchemas.php:374-380` (GENERAL block) include `/\bnet\s+worth\b/i` BUT GENERAL is never matched against — `QueryClassifier::findAllMatches:157` skips it. They are dead code.

## xAI grok-4.3 contract verdict (re-done against the right docs)

The five live params at `app/Traits/HasAiChat.php:245-258` are all valid for grok-4.3.

| Param sent | Verdict | Source |
|---|---|---|
| `temperature: 0` | Valid. Range 0-2 inclusive. 0 = deterministic. | `docs.x.ai/docs/api-reference` chat-completions body |
| `reasoning_effort: 'none'` | Valid for grok-4.3. Four levels: `none` / `low` / `medium` / `high`. `none` = disables reasoning entirely. Default if omitted = `low`. | `docs.x.ai/developers/model-capabilities/text/reasoning#effort-levels` |
| `stream: true` + `stream_options.include_usage: true` | Valid. Supported; `include_usage` adds a final usage chunk before `[DONE]`. | `docs.x.ai/docs/api-reference` |
| `tools` + `tool_choice: 'auto'` | Valid. Up to 128 functions. `tool_choice` accepts `auto` / `required` / `none` / specific function object. | `docs.x.ai/docs/api-reference` |
| `x-grok-conv-id` header (`XaiClient.php:77`) | Undocumented in the chat-completions API reference. Likely lives on a separate prompt-caching page. Non-blocking; verify separately. | (gap) |

### Side findings while cross-checking

1. **`max_tokens` is DEPRECATED** in the API reference; xAI prefers `max_completion_tokens`. Used at 6 send-sites across 3 files: `HasAiChat.php:248`, `ConversationSummariser.php:130`, `AIExtractionService.php:239 / 285 / 327 / 364`. Two error-string matches (`HasAiChat.php:1134`, `HasAiGuardrails.php:263`) are reading the API error response, not sending the param — they need widening to also match `max_completion_tokens` so future API errors are still caught.
2. **API reference says `reasoning_effort: "Not supported by grok-4"`** — that is the base `grok-4` model only, not `grok-4.3`. The reasoning capability page is the more specific source and confirms grok-4.3 supports all four levels. No conflict for our usage.
3. **2026-05-15 retirement list** per `docs.x.ai/docs/models`: `grok-4-1-fast`, `grok-4-fast`, `grok-4`, `grok-code-fast-1`, `grok-imagine-image-pro`. Production runs `grok-4-1-fast-reasoning`, which is **not** in the explicit retirement list — but the handover and CSJTODO have been treating it as retiring. Worth re-verifying separately before the cutoff date so prod isn't deployed unnecessarily under deadline pressure.

## Fixes — recommended and accepted

| ID | Fix | Approved? | Notes |
|---|---|---|---|
| F1 | **4-line classifier fix** in `app/Constants/QuerySchemas.php`. ADD net-worth patterns to `KEYWORD_PATTERNS[HOLISTIC_HEALTH]` (line 229); REMOVE the dead duplicates from `KEYWORD_PATTERNS[GENERAL]` (lines 374-380). | **Approved (E1).** | The user-facing fix. |
| F2 | `max_tokens` → `max_completion_tokens` swap at all 6 send-sites (HasAiChat.php, ConversationSummariser.php ×1, AIExtractionService.php ×4). Widen the 2 error-string matches in `HasAiChat.php:1134` and `HasAiGuardrails.php:263` to match both names for forward-compat. | **Approved (E4).** | Don't be lazy. Folded into this PR. |
| F3 | Fold PR #263 (temperature=0 on the remaining 5 LLM call sites — 2 files: `ConversationSummariser.php`, `AIExtractionService.php`) into this PR. Close PR #263. | **Approved (E3).** | One PR to dev, not three. F2 and F3 happen to touch the same two files — F3 work goes in alongside F2. |
| F4 | Lift `reasoning_effort` from `'none'` to `'low'` on the chat path. | **Rejected (E2).** | Per docs `'none'` is doing exactly what we asked. The classifier fix puts the answer in the prompt as text — no reasoning needed for the LLM to read it back. |
| F5 | Verify `x-grok-conv-id` header against the prompt-caching docs. | Deferred. | Non-blocking. Track separately. |
| F6 | Investigate whether `grok-4-1-fast-reasoning` actually retires on 2026-05-15 (not in the explicit retirement list). | Deferred. | Non-blocking on this PR. May reduce deploy urgency if prod is safe past 5/15. |
| F7 | `get_net_worth` tool from session 11. | **Rejected.** | Would mask the classifier bug. Session 12 investigation killed it. |

## The PR (single, atomic, dev-bound)

**Branch:** `fyn-net-worth-tool` (currently at `c939b2a`, only handover doc commits — clean for code).

**Files to change (4):**

1. `app/Constants/QuerySchemas.php` — F1 (classifier fix)
2. `app/Traits/HasAiChat.php` — F2 (max_tokens swap on send-site, error-string widening on match-site)
3. `app/Traits/HasAiGuardrails.php` — F2 (error-string widening only)
4. `app/Services/AI/ConversationSummariser.php` — F2 (max_tokens swap) + F3 (temperature=0)
5. `app/Services/Documents/AIExtractionService.php` — F2 (max_tokens swap, 4 sites) + F3 (temperature=0)

**Commit plan (3 atomic commits on `fyn-net-worth-tool`):**

1. `fix(ai): route net-worth queries to holistic_health so financial_context layer is included` — F1.
2. `chore(ai): swap deprecated max_tokens for max_completion_tokens at all xAI send-sites` — F2.
3. `chore(ai): set temperature=0 on remaining 5 LLM call sites for deterministic output` — F3 (cherry-picked or re-authored from PR #263's `temperature-zero-everywhere-v2`).

**PR target:** `fyn-net-worth-tool → dev`. **No admin-merge** — wait for CSJ review per `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`. **Close PR #263** at the same time with a reference to the new PR.

**Acceptance (live on csjones):**

- `QueryClassifier::classify("What is my net worth?", null)` returns `primary: "holistic_health"` (was `general`).
- Login as `chris@fynla.org`, ask all 5 phrasings: `"Net worth"`, `"What is my net worth?"`, `"How much am I worth?"`, `"Show me my net worth"`, `"Combined wealth"`. Each must reply matching `NetWorthService::calculateNetWorth(chris)` (£598,250 today; whatever the canonical figure is at test time).
- No xAI API errors about `max_tokens` / `max_completion_tokens` mismatch; no temperature warnings; conversation 31+ continues to land in `ai_messages` with usage chunks recorded.
- Audit chain still writes correctly (factual classifications still skip `AiAdviceLog` per `HasAiChat.php:745-746` for `holistic_health` — wait, that's a check item: `holistic_health` writes the advice log. Verify this is the desired behaviour. If yes, no change. If no, that's a follow-up.).

**Loop until correct (CLAUDE.md Rule #15):** for every red phrasing, diagnose with file:line evidence → fix → re-verify in browser → repeat until all 5 phrasings reply with the canonical figure, no early exit.

## Hard deadline

xAI grok-4 family retirement is documented for 2026-05-15. Production runs `grok-4-1-fast-reasoning`, which is **not** in the explicit retirement list per the models page — but session-12's handover and CSJTODO assume it is. F6 (verify) is deferred but should be answered before assuming we have a hard 5/15 deploy cutoff.

If the retirement does apply: dev → main release + fynla.org SiteGround upload must happen before 2026-05-15. Upload list (from session 12 handover, +1 for QuerySchemas.php):

1. `app/Http/Controllers/Api/AdminController.php`
2. `app/Services/AI/ConversationSummariser.php`
3. `app/Services/AI/XaiClient.php`
4. `app/Services/AI/XaiToolDefinitions.php`
5. `app/Services/Documents/AIExtractionService.php`
6. `app/Services/Documents/DocumentProcessor.php`
7. `app/Traits/HasAiChat.php`
8. `app/Traits/HasAiGuardrails.php`
9. `app/Agents/CoordinatingAgent.php`
10. `config/services.php`
11. `app/Constants/QuerySchemas.php`

## Audit trail of decisions

- Session 11 — `get_net_worth` tool proposed → BLOCKED by CSJ (cost concern, evidence-first request).
- Session 12 — root cause located; xAI half of report sloppy.
- Session 12 — CSJ corrected two specific xAI doc points.
- Session 13 (this session) — xAI half re-done; corrected synthesis presented; CSJ approved fixes E1, E3, E4 and rejected F4, deferred F5/F6.
- This report written before any code changes per CSJ instruction E5.
