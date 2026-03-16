# Fyn Assistant — Comprehensive Audit & Optimisation Report

**Date:** 16 March 2026
**Scope:** Full audit of 9 backend AI service files + 12 frontend files
**Model:** Claude Haiku 4.5 (`claude-haiku-4-5-20251001`)
**Goal:** Identify gaps against Anthropic's 2026 best practices and provide actionable recommendations

---

## Table of Contents

1. [Current Architecture Summary](#1-current-architecture-summary)
2. [System Prompt Audit](#2-system-prompt-audit)
3. [Tool Definitions Audit](#3-tool-definitions-audit)
4. [Context Building Audit](#4-context-building-audit)
5. [Guardrails & Safety Audit](#5-guardrails--safety-audit)
6. [Model & Token Configuration Audit](#6-model--token-configuration-audit)
7. [Streaming & Error Handling Audit](#7-streaming--error-handling-audit)
8. [Claude API Best Practices vs Current Implementation](#8-claude-api-best-practices-vs-current-implementation)
9. [Prioritised Recommendations](#9-prioritised-recommendations)

---

## 1. Current Architecture Summary

### Backend Architecture (9 files, ~3,528 lines)

```
AiChatController.php (168 lines)
  ├── AiChatService.php (323 lines) ─── Claude API integration, tool loop, SSE streaming
  │     ├── AiModelResolver.php (36 lines) ─── Model/token selection
  │     ├── AiContextBuilder.php (252 lines) ─── System prompt & financial context
  │     ├── AiToolDefinitions.php (605 lines) ─── 21 tool definitions
  │     └── AiToolExecutor.php (837 lines) ─── Tool execution logic
  │
  └── AiSimulatedService.php (221 lines) ─── Preview mode (no API calls)
        ├── AiIntentMatcher.php (351 lines) ─── Keyword-based intent detection
        └── AiSimulatedResponseBuilder.php (735 lines) ─── Template response builder
```

### Frontend Architecture (12 files, ~2,035 lines)

```
Desktop:
  AiChatPanel.vue (518 lines) ─── Main chat panel (420px card)
  AiChatButton.vue (88 lines) ─── Floating action button
  AiMessageContent.vue (129 lines) ─── Message rendering with markdown

Mobile:
  MobileFynChat.vue (317 lines) ─── Full-screen chat view
  ChatBubble.vue (58 lines) ─── Mobile message bubble
  VoiceInputButton.vue (274 lines) ─── Native iOS + Web Speech API

Shared:
  QuickReplyChips.vue (31 lines)
  SuggestedPrompts.vue (65 lines)
  TypingIndicator.vue (45 lines)
  ToolExecutionStatus.vue (34 lines)

State & API:
  aiChat.js (390 lines) ─── Vuex store module
  aiChatService.js (86 lines) ─── API wrapper with fetch streaming
```

### Data Flow

```
User types message
  → Vuex ADD_MESSAGE (optimistic UI)
  → aiChatService.sendMessageStream() [fetch + ReadableStream]
  → POST /api/ai-chat/conversations/{id}/messages
  → AiChatController.sendMessage()
    → Preview user? → AiSimulatedService (keyword matching, template responses)
    → Real user? → AiChatService (Claude API call with tools)
  → SSE events streamed back
    → content → APPEND_STREAMING_TEXT
    → navigation → SET_PENDING_NAVIGATION → router.push()
    → entity_created → ADD_MESSAGE (special card)
    → done → finalise assistant message
```

### Key Metrics

| Metric | Value |
|--------|-------|
| Total tools defined | 21 (6 read-only + 15 data creation) |
| Max tool calls per turn | 5 |
| Max history messages | 20 |
| Max input length | 2,000 characters |
| API timeout | 180 seconds |
| API version | `2023-06-01` |
| Model | `claude-haiku-4-5-20251001` |
| Max tokens (Standard/Student) | 2,048 |
| Max tokens (Pro) | 4,096 |

---

## 2. System Prompt Audit

### Current Structure (`AiContextBuilder.php`, lines 20-85)

The system prompt is built as a single text block with these sections:

```
[Role definition paragraph]
## Rules (7 bullet points)
## User Profile (dynamic)
## Financial Summary (dynamic)
## Current Page Context (conditional)
## Preview Mode (conditional)
## Available Actions (6-14 bullet points)
## Data Creation Guidance (conditional, 5 rules)
```

### Strengths

1. **Clear role definition** (line 28): "You are Fynla Assistant, a professional financial planning assistant built into the Fynla application."
2. **British English rule** specified (line 32)
3. **No-acronym rule** present (line 33)
4. **Regulatory guardrail** present (line 35): "You are not a regulated financial adviser"
5. **Dynamic user data** injected (profile + financial summary)
6. **Route-aware context** for 16 pages (lines 202-218)
7. **Preview mode** properly communicated (line 52)
8. **Data creation guidance** with sensible defaults instruction (lines 76-82)

### Issues

#### Issue 2.1: No XML Tag Structure
**Current:** Plain markdown with `##` headings
**Best practice (Anthropic 2026):** Use XML tags (`<instructions>`, `<context>`, `<user_data>`) to clearly separate system instructions from dynamic context. This prevents Claude from confusing context data with instructions, especially as context grows.

#### Issue 2.2: Rules Are "Don't" Focused
**Current:** Several rules are negative — "Never use acronyms", "never give specific product recommendations"
**Best practice:** Tell Claude what to do, not what not to do. Reframe negatives as positives: "Always spell out terms in full" instead of "Never use acronyms."

#### Issue 2.3: No Response Format Guidance
The prompt doesn't specify how responses should be structured. Claude defaults to whatever style matches the prompt. No guidance on:
- Desired response length
- Use of markdown formatting in responses
- How to structure multi-part answers
- When to use bullet points vs prose

#### Issue 2.4: No Example Interactions
Zero few-shot examples. Anthropic's testing shows 3-5 examples improve output quality and consistency significantly — especially for domain-specific tone and format.

#### Issue 2.5: Available Actions Listed as Plain Text
Lines 56-82 list tool capabilities as bullet points in natural language. The model already has the tool definitions — this duplicates information and uses tokens without adding value. Instead, the prompt should explain *when* and *why* to use tools, not just *what* they do.

#### Issue 2.6: No Identity / Personality Definition
Beyond "professional financial planning assistant", there's no personality guidance. No warmth, no empathy rules, no communication style. For a financial planning context, users often need reassurance and clarity, not just data.

#### Issue 2.7: No Handling of Out-of-Scope Queries
No instruction on how to handle non-financial questions (weather, sports, general knowledge). Claude will attempt to answer them using general knowledge, breaking the assistant's domain focus.

#### Issue 2.8: No Proactive Guidance Instruction
The prompt doesn't instruct Claude to proactively surface relevant insights. For example, if a user asks about savings, Claude should also mention if their emergency fund is low. The financial summary data is there but the instruction to use it proactively is missing.

#### Issue 2.9: Financial Summary Could Be Stale
The `CoordinatingAgent->orchestrateAnalysis()` call (line 139) runs the full coordinating agent analysis on every message. This is expensive and potentially slow. No caching strategy is mentioned for the context builder.

#### Issue 2.10: Rules List is Sparse
Only 7 rules covering basics. Missing:
- Currency formatting for pence (£0.50 vs £0)
- How to reference time periods (tax years, dates)
- How to handle joint/coupled finances
- When to suggest the user adds more data
- How to handle conflicting data

---

## 3. Tool Definitions Audit

### Current Tools (`AiToolDefinitions.php`, 605 lines)

| # | Tool | Category | Required Fields | Total Fields |
|---|------|----------|-----------------|--------------|
| 1 | `navigate_to_page` | Navigation | 2 | 2 |
| 2 | `get_module_analysis` | Analysis | 1 | 1 |
| 3 | `run_what_if_scenario` | Analysis | 2 | 2 |
| 4 | `get_recommendations` | Analysis | 0 | 0 |
| 5 | `get_tax_information` | Tax | 1 | 1 |
| 6 | `generate_financial_plan` | Plan | 0 | 0 |
| 7 | `create_goal` | Creation | 5 | 5 |
| 8 | `create_life_event` | Creation | 3 | 4 |
| 9 | `create_savings_account` | Creation | 2 | 8 |
| 10 | `create_investment_account` | Creation | 2 | 6 |
| 11 | `create_pension` | Creation | 2 | 9 |
| 12 | `create_property` | Creation | 2 | 10 |
| 13 | `create_mortgage` | Creation | 1 | 8 |
| 14 | `create_protection_policy` | Creation | 1 | 8 |
| 15 | `create_estate_asset` | Creation | 3 | 5 |
| 16 | `create_estate_liability` | Creation | 3 | 5 |
| 17 | `create_estate_gift` | Creation | 4 | 5 |

**Preview mode:** 6 read-only tools. **Real users:** All 17 tools (creation tools added at line 21).

### Strengths

1. **Enum constraints** used for categorical fields (e.g., `policy_type`, `account_type`, `ownership_type`)
2. **Descriptions explain context** — e.g., pension tool explains both DC and DB categories
3. **Smart defaults guidance** — descriptions mention defaults (e.g., "Default 'repayment'" for mortgage type)
4. **Preview mode filtering** — creation tools excluded for preview users (line 21)
5. **Conditional field documentation** — e.g., mortgage fields note "Only used if outstanding_mortgage is provided"

### Issues

#### Issue 3.1: No `strict: true` Mode
**Current:** Standard JSON Schema without strict validation
**Best practice:** Add `strict: true` to all tool definitions. This guarantees Claude's tool call inputs match the schema exactly — no type mismatches, no missing required fields. Eliminates an entire class of runtime errors in `AiToolExecutor`.

#### Issue 3.2: Open `parameters` Objects
`run_what_if_scenario` (line 87-89), `get_recommendations` (line 98-101), and `generate_financial_plan` (line 133-136) use `(object) []` for properties, creating effectively empty/open schemas. With strict mode, empty properties objects are invalid. Even without strict mode, open schemas give Claude no guidance on what parameters are valid.

#### Issue 3.3: No `additionalProperties: false`
None of the 17 tool schemas include `additionalProperties: false`. This allows Claude to invent extra fields that get silently passed to the executor, potentially causing unexpected behaviour.

#### Issue 3.4: No Return Format Documentation
Tool descriptions say what the tool does but not what it returns. Anthropic recommends documenting return formats so Claude can write correct parsing logic and set user expectations. For example, `get_module_analysis` should describe the response structure (metrics, recommendations, etc.).

#### Issue 3.5: No Input Examples
Zero examples in any tool description. Anthropic's testing showed that 1-5 realistic examples improved parameter accuracy from 72% to 90% for complex tools. The `create_pension` tool with 9 fields across two categories (DC/DB) would particularly benefit.

#### Issue 3.6: `run_what_if_scenario` Schema is Underspecified
The `parameters` field (line 86-89) has an empty properties object with a vague description: "Scenario parameters. For retirement: additional_contribution (number)...". This text description should be a proper JSON Schema with typed properties so Claude knows exactly what to pass.

#### Issue 3.7: Tool Descriptions Could Be More Specific
Several descriptions are generic. For example, `get_module_analysis` (line 62) says "Get detailed financial analysis for a specific module" without explaining what "detailed analysis" includes (metrics, recommendations, gaps, scores). More specific descriptions lead to better tool selection.

#### Issue 3.8: No Tool Result Size Indication
Some tools return large payloads (e.g., `generate_financial_plan` calls `coordinatingAgent->generateHolisticPlan()` which can return extensive analysis). No indication of expected response size, which affects token budgeting.

#### Issue 3.9: Missing Validation Constraints
JSON Schema supports formats like `date` and `uuid` that could be used for date fields (`target_date`, `event_date`, `gift_date`, `purchase_date`) to catch format errors at the schema level. Currently these are just `type: string` with a description mentioning "YYYY-MM-DD format".

---

## 4. Context Building Audit

### User Profile (`AiContextBuilder.php`, lines 90-131)

**Currently included:**
- Name, Age, Employment status, Marital status
- Total annual income (calculated from 7 income sources)
- Monthly expenditure
- Target retirement date
- Number of children

**Missing context that would improve responses:**

#### Issue 4.1: No Spouse/Partner Profile
For coupled users (marital_status = 'married' or 'civil_partnership'), spouse data is critical for:
- Joint asset analysis
- Inheritance Tax double-band calculations
- Retirement income from partner's pensions
- Protection coverage analysis (surviving spouse needs)

The user model likely has a `spouse_id` or linked partner, but this isn't included in the profile.

#### Issue 4.2: No Risk Profile
The user's risk profile (tolerance, capacity, attitude) directly affects investment and pension advice quality. Without it, Claude gives generic advice rather than risk-appropriate guidance.

#### Issue 4.3: No Tax Status
No indication of the user's tax band (basic, higher, additional rate). This is critical for:
- Pension contribution advice (tax relief depends on band)
- ISA vs GIA decisions
- Dividend allowance guidance
- Salary sacrifice recommendations

#### Issue 4.4: No Home Ownership Status
Whether the user owns property affects advice on:
- Residence Nil Rate Band eligibility (Inheritance Tax)
- Lifetime ISA eligibility (first-time buyers)
- Protection needs (mortgage protection)
- Savings priorities

#### Issue 4.5: No State Pension Data
State Pension entitlement (years of National Insurance contributions, projected weekly amount) directly impacts retirement income projections. Without it, all retirement advice misses a major income source.

### Financial Summary (`AiContextBuilder.php`, lines 136-191)

**Currently included:**
- Monthly surplus/shortfall
- Emergency fund months
- Projected retirement income
- Inheritance Tax liability estimate
- Top 3 recommendations

**Missing:**

#### Issue 4.6: No Asset Summary
Total savings, total investments, total property value, total pension value — the high-level numbers Claude needs to contextualise advice. Currently the financial summary only includes specific metrics, not overall position.

#### Issue 4.7: No Net Worth
Net worth (total assets minus total liabilities) is a fundamental metric that should be in every conversation context.

#### Issue 4.8: No Protection Summary
Life cover total, critical illness cover, income protection coverage — missing from the financial summary. Claude can't give protection advice without knowing current coverage.

#### Issue 4.9: No ISA/Pension Allowance Usage
How much of the current year's ISA allowance and pension annual allowance has been used. Critical for timing-sensitive advice (approaching tax year end, for example).

#### Issue 4.10: Financial Summary Recalculated on Every Message
`buildFinancialSummary()` (line 136) calls `$this->coordinatingAgent->orchestrateAnalysis()` on every single message. This is the heaviest operation in the entire system — it orchestrates all 7 module agents. For a multi-turn conversation, this means the full analysis runs 5-10 times for the same user in quick succession.

**Recommendation:** Cache the financial summary per user with a short TTL (60-120 seconds) or until data changes. The existing agent caching (`v1_coordinating_{userId}_analysis`) may already handle this, but the context builder doesn't check for it explicitly.

### Module Context (`AiContextBuilder.php`, lines 196-221)

**Currently:** Maps 16 route paths to one-sentence descriptions.

#### Issue 4.11: Module Context Lacks Actionable Detail
Each route gets a single generic sentence like "The user is viewing their property portfolio." This doesn't tell Claude what the user can see or what actions are available on that page. A more useful context would include:
- What data is displayed on this page
- Common questions users ask on this page
- Relevant tools for this page context

---

## 5. Guardrails & Safety Audit

### Input Validation

#### Issue 5.1: Minimal Input Validation
**Current:** Only `max:2000` on message length (`AiChatController.php`, line 119). No content screening.

**Missing:**
- No profanity/toxicity filter
- No prompt injection detection
- No PII detection (users might paste bank details, National Insurance numbers)
- No jailbreak attempt detection

**Best practice:** Use Haiku 4.5 as a lightweight pre-screen classifier on user inputs. A simple classification prompt (`Is this message a financial planning question? Respond with 'yes' or 'no'`) costs ~$0.001 per message and prevents off-topic or malicious inputs from consuming expensive model calls.

#### Issue 5.2: No Rate Limiting on AI Endpoint
The `sendMessage` endpoint (`AiChatController.php`, line 116) doesn't appear to have per-user rate limiting beyond the general API throttle. A malicious or confused user could send rapid messages, incurring significant API costs.

**Recommendation:** Add per-user rate limiting (e.g., 20 messages per hour for standard, 50 for pro) tracked via the `AiConversation` model's `total_input_tokens` / `total_output_tokens` fields.

### Output Validation

#### Issue 5.3: No Output Filtering
Claude's responses are streamed directly to the user with no post-processing or validation. This means:
- If Claude hallucinates a financial figure, it goes straight to the user
- If Claude accidentally gives a specific product recommendation (violating FCA rules), there's no safety net
- If Claude generates content in the wrong format, there's no correction

**Recommendation:** At minimum, log and monitor outputs. Ideally, add a lightweight post-processing step that checks for:
- Specific product names being recommended (vs discussed)
- Regulatory phrases like "you should buy" or "I recommend you invest in"
- Unusually large or small financial figures that might be hallucinated

#### Issue 5.4: No Citation/Grounding Enforcement
Claude is told to "reference specific numbers from their profile" (line 37) but there's no mechanism to verify it actually does. The model could hallucinate data not present in the context.

**Best practice:** Anthropic recommends for documents >20k tokens requiring Claude to "extract quotes first before answering." For financial data, a similar approach would ensure Claude only references data actually present in the financial summary.

### Regulatory Compliance

#### Issue 5.5: Weak Regulatory Disclaimer
**Current** (line 35): "You are not a regulated financial adviser — never give specific product recommendations or say 'you should buy/sell X'"

**Issues:**
- This is a single sentence. UK FCA regulations require clear distinctions between "guidance" and "advice"
- No instruction on what to do if a user asks for specific product advice (e.g., "Should I buy this Vanguard fund?")
- No instruction on risk warnings
- No instruction on directing users to seek regulated advice for complex decisions
- The framing phrases ("you may want to consider") are good but should be reinforced with explicit examples

**Recommendation:** Expand to a dedicated `## Regulatory Compliance` section with:
- Clear FCA guidance vs advice distinction
- Specific phrases to use and avoid
- When to recommend the user consults a regulated adviser
- Risk warnings for certain topics (pensions, investments)

#### Issue 5.6: No Data Privacy Instructions
No instruction on how to handle user data in responses. Claude shouldn't:
- Reveal internal system details (model names, tool names, API keys)
- Quote raw database values with field names
- Reference other users' data
- Encourage users to share sensitive information (bank login details, passwords)

### Data Integrity

#### Issue 5.7: No Input Validation in Tool Executor
`AiToolExecutor.php` trusts Claude's tool call inputs implicitly. For example:
- `create_savings_account` (line 262) passes `$input['current_balance']` directly to the model. If Claude hallucinates a negative balance or an astronomically large number, it gets saved to the database
- `create_goal` (line 169-179) passes `$input['target_date']` without date validation
- `create_property` (line 395-408) passes `$input['current_value']` without range validation

**Recommendation:** Add validation in the executor matching the same rules as the Form Request validators used for manual UI input. Use `ValidationLimits::currencyRules()` for monetary values, date validation for dates, and enum validation for categoricals.

#### Issue 5.8: No Duplicate Detection in Data Creation
When Claude creates records (savings accounts, pensions, etc.), there's no check for duplicates. If a user says "I have a Nationwide ISA with £10,000" twice in a conversation, Claude will create two identical accounts.

**Recommendation:** Before creating a record, query for existing records with similar names/types for the user and return a warning or confirmation request.

---

## 6. Model & Token Configuration Audit

### Current Configuration (`AiModelResolver.php`, 36 lines)

```php
private const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';

public function getMaxTokens(User $user): int {
    return match ($plan) {
        'pro' => 4096,
        default => 2048,
    };
}
```

### Issues

#### Issue 6.1: Haiku 4.5 May Be Underpowered for Complex Financial Analysis
Haiku 4.5 is optimised for speed and cost, not depth of reasoning. For financial planning conversations that involve:
- Multi-step tax calculations
- Cross-module analysis (retirement + estate + protection interactions)
- What-if scenarios with multiple variables
- Nuanced UK regulatory guidance

Sonnet 4.6 would provide significantly better responses at a modest cost increase ($3/$15 vs $1/$5 per MTok).

**Recommendation:** Consider a tiered approach:
- **Haiku 4.5** for simple queries (navigation, greetings, tax lookups) — fast and cheap
- **Sonnet 4.6** for analysis, recommendations, and complex discussions — better quality
- Use the `effort` parameter (`low`/`medium`) on Sonnet 4.6 to control costs

#### Issue 6.2: Max Tokens Are Very Low
2,048 tokens (standard) / 4,096 tokens (pro) is extremely restrictive. For context:
- A typical detailed financial analysis response is 500-1,000 tokens
- A multi-point recommendation with explanations can be 1,500+ tokens
- If Claude needs thinking tokens, these are consumed from the same budget

**Best practice:** Anthropic recommends `max_tokens: 8192` for chat use cases and `16384` for complex analysis. Current limits force Claude to truncate responses or skip relevant details.

**Recommendation:** Increase to at least 4,096 (standard) / 8,192 (pro). The cost increase is marginal because output tokens are only charged for what's actually generated, not the maximum.

#### Issue 6.3: No Prompt Caching
**Current:** Every API call sends the full system prompt + tool definitions + message history. No prompt caching is configured.

**Impact:** The system prompt + 17 tool definitions is approximately 4,000-6,000 tokens. In a 10-message conversation, this is sent 5 times (5 assistant turns), costing 20,000-30,000 input tokens that could be cached.

**Best practice:** Add `cache_control: {"type": "ephemeral"}` to the system prompt and tool definitions. This caches them for 5 minutes (auto-renewed on reuse), reducing input token costs by up to 90% for cached content.

**Savings estimate:** For a user with 5 messages in a conversation:
- Current: 5 x ~5,000 tokens = 25,000 input tokens for system+tools
- With caching: 1 x 5,000 (full price) + 4 x 5,000 at 0.1x = 7,000 effective tokens
- **72% reduction in system prompt costs**

#### Issue 6.4: No API Version Update
**Current:** `API_VERSION = '2023-06-01'` (line 17)
The `2023-06-01` version is still the stable API version, but newer beta features (extended thinking, prompt caching, structured outputs) may require version headers or beta flags. This should be reviewed against the features being adopted.

#### Issue 6.5: No Token Usage Monitoring or Budgeting
Token usage is tracked per conversation (`incrementTokenUsage`) but there's no:
- Per-user daily/monthly token budget
- Alert when a user approaches their allocation
- Cost tracking per subscription tier
- Mechanism to gracefully degrade when budget is exceeded

#### Issue 6.6: Model Hardcoded as Constant
The model is hardcoded as `private const DEFAULT_MODEL = 'claude-haiku-4-5-20251001'` with an override via config. There's no dynamic model selection based on query complexity, conversation depth, or user tier beyond a flat config value.

---

## 7. Streaming & Error Handling Audit

### SSE Streaming Implementation

#### Backend (`AiChatService.php`, lines 37-208)

**Current approach:** Non-streaming API call (`Http::post`) that returns the full response, then yields it as a single SSE chunk. This is **not true streaming** — the entire response is generated server-side before any content reaches the user.

**Evidence:** Line 69 calls `callAnthropicApi()` which uses `Http::post()` (line 247) — a synchronous HTTP call. The response is processed in full before yielding content events. The generator pattern gives the appearance of streaming on the frontend (SSE events), but the user waits for the entire Claude response before seeing anything.

#### Issue 7.1: Not Using Anthropic's Streaming API
**Current:** Synchronous `POST` to Anthropic, wait for full response, then stream to client
**Best practice:** Use `"stream": true` in the Anthropic API request to receive server-sent events from Claude as they're generated. This enables:
- First token visible to user in <1 second (vs waiting 5-30 seconds for full response)
- Real-time text appearance (character by character)
- Ability to cancel mid-stream (abort controller)
- Better perceived performance

**Impact:** This is the single highest-impact improvement available. Users currently wait 5-30 seconds seeing nothing, then the entire response appears at once. True streaming would show text appearing word-by-word immediately.

#### Issue 7.2: No Stream Abort Support
**Current backend:** No mechanism to cancel an in-progress API call. The 180-second timeout (line 19) is the only safeguard.
**Current frontend:** `abortController` infrastructure exists in the Vuex store (`SET_ABORT_CONTROLLER` mutation, `abortStreaming` action) but is never connected to the UI — no cancel button is rendered.

**Recommendation:** Expose a cancel button during streaming. Connect the frontend abort controller to the backend via a separate endpoint or connection close detection.

#### Issue 7.3: Simulated Streaming Uses `usleep()`
`AiSimulatedService.php` (line 83) uses `usleep(random_int(15000, 40000))` to simulate streaming delays. This blocks the PHP process for each chunk, which:
- Holds a PHP-FPM worker for the entire simulated response duration
- Could cause worker exhaustion under load
- Adds unnecessary latency (500-2000ms total per response)

### Error Handling

#### Issue 7.4: Generic Error Messages
**Current** (line 80): Errors are classified into only two categories — authentication errors ("Configuration issue — please contact support") and everything else ("I apologise, but I encountered an issue").

**Missing error categories:**
- Rate limiting (429) — should say "Please wait a moment before sending another message"
- Overloaded (529) — should say "The service is busy, please try again in a moment"
- Token limit exceeded — should suggest shortening the conversation
- Content policy violation — should explain why the response was blocked
- Network timeout — should distinguish from server errors

#### Issue 7.5: No Retry Logic
If the Anthropic API returns a transient error (429, 529, 5xx), the system immediately fails without retrying. For rate limiting specifically, a simple exponential backoff (1s, 2s, 4s) would resolve most issues.

#### Issue 7.6: Error Responses Don't Provide Recovery Guidance
When an error occurs, the user sees a generic message. There's no:
- Suggestion to try again
- Option to start a new conversation if the current one is corrupted
- Indication of whether the error is temporary or permanent
- Help link or support contact

#### Issue 7.7: Tool Execution Errors Are Opaque
`AiToolExecutor.php` (line 84) returns `['error' => 'Tool execution failed. Please try again.']` for all exceptions. Claude sees this generic error and has no way to:
- Understand why the tool failed
- Retry with corrected parameters
- Communicate the specific issue to the user

**Recommendation:** Return structured error information:
```php
return [
    'error' => true,
    'error_type' => 'validation_failed',
    'message' => 'Current balance must be a positive number',
    'field' => 'current_balance',
];
```

---

## 8. Claude API Best Practices vs Current Implementation

### Gap Analysis Matrix

| Best Practice | Current State | Gap Severity | Section |
|---------------|---------------|-------------|---------|
| **XML-structured system prompt** | Plain markdown | Medium | 2.1 |
| **Positive instruction framing** | Mix of positive/negative | Low | 2.2 |
| **Few-shot examples in prompt** | None | High | 2.4 |
| **Response format guidance** | None | Medium | 2.3 |
| **`strict: true` on tools** | Not used | High | 3.1 |
| **`additionalProperties: false`** | Not used | Medium | 3.3 |
| **Tool return documentation** | Not documented | Medium | 3.4 |
| **Tool input examples** | None | High | 3.5 |
| **Prompt caching** | Not configured | High | 6.3 |
| **True SSE streaming** | Synchronous API calls | Critical | 7.1 |
| **Input screening** | Length check only | Medium | 5.1 |
| **Output validation** | None | Medium | 5.3 |
| **Retry logic** | None | Medium | 7.5 |
| **Model tiering** | Single model | Medium | 6.1 |
| **Token budgeting** | Basic tracking only | Low | 6.5 |
| **Financial compliance guardrails** | Single sentence | High | 5.5 |
| **Conversation context management** | Fixed 20-message window | Medium | 8.1 |
| **Cancel/abort streaming** | Infra ready, no UI | Low | 7.2 |
| **Hallucination prevention** | Basic instruction | Medium | 5.4 |
| **Tool input validation** | No validation | High | 5.7 |
| **Effort parameter** | Not used | Low | 6.6 |

### Detailed Gap Analysis

#### 8.1: Conversation History Management
**Current:** Fixed sliding window of 20 most recent messages (`MAX_HISTORY_MESSAGES = 20`, line 23). Messages are loaded from the database as simple role/content pairs.

**Issues:**
- 20 messages is arbitrary — could be too many for short conversations (wasted tokens) or too few for long ones (lost context)
- No summarisation of older messages — when message 21 arrives, message 1 is simply dropped
- No token counting — 20 long messages could exceed the model's token budget
- Tool call results from previous turns are not preserved in history, so Claude loses context about what tools were previously called

**Best practice:**
- Count tokens in the history and cap by budget, not message count
- Summarise older messages into a condensed context instead of dropping them
- Preserve key facts from dropped messages (e.g., "Previously discussed: retirement planning, ISA allowances")
- Consider Anthropic's recommendation: for fresh context windows, be prescriptive about what to rediscover

#### 8.2: Message History Doesn't Include Tool Interactions
**Current:** `buildMessageHistory()` (line 273-293) loads only `user` and `assistant` messages from the database. Tool call results and tool use blocks from previous turns are not preserved.

**Impact:** When Claude references a previous analysis it performed (e.g., "Based on the protection analysis I just ran..."), it has no record of what that analysis actually returned. It may hallucinate the results or give inconsistent follow-up advice.

**Recommendation:** Store tool call blocks and tool results as part of the message history, or at minimum store a summary of tool results in the assistant message metadata.

#### 8.3: No System Prompt Versioning
The system prompt is constructed dynamically with no version tracking. If the prompt changes (which it does with every user interaction due to dynamic context), there's no way to:
- A/B test different prompt versions
- Roll back to a previous version if quality degrades
- Track which prompt version produced which responses

#### 8.4: API Version is Dated
The API version `2023-06-01` is the standard stable version but doesn't leverage newer capabilities that may be available through beta headers, including:
- Extended thinking (`anthropic-beta: interleaved-thinking-2025-05-14`)
- Prompt caching (may require specific headers)
- Token-efficient tools

---

## 9. Prioritised Recommendations

### Priority 1: Critical (Immediate Impact)

#### R1. Enable True SSE Streaming from Anthropic API
**Files:** `AiChatService.php` (lines 213-268)
**Effort:** Medium (3-5 hours)
**Impact:** Transforms user experience from "wait 10-30 seconds then see everything" to "see words appear in real-time within 1 second"

**Implementation:** Replace `Http::post()` with a streaming HTTP call to Anthropic's API with `"stream": true`. Process `content_block_delta` events as they arrive and yield them to the frontend SSE stream immediately. The frontend already handles incremental text via `APPEND_STREAMING_TEXT` — it just needs real-time data instead of batched data.

#### R2. Add `strict: true` to All Tool Definitions
**Files:** `AiToolDefinitions.php` (all tool definitions)
**Effort:** Low (1-2 hours)
**Impact:** Eliminates type mismatches, missing fields, and extra properties in tool calls. Prevents database integrity issues from malformed tool inputs.

**Implementation:** Add `'strict' => true` to each tool definition in the `getTools()` method. Also add `'additionalProperties' => false` to every `properties` object. Fix the three empty-properties schemas (`run_what_if_scenario.parameters`, `get_recommendations`, `generate_financial_plan`) to have properly defined schemas or no parameters at all.

#### R3. Add Tool Input Validation in Executor
**Files:** `AiToolExecutor.php` (all `create*` methods)
**Effort:** Medium (3-4 hours)
**Impact:** Prevents invalid data from being saved to the database. Catches hallucinated values (negative balances, impossible dates, out-of-range percentages).

**Implementation:** For each creation method, add validation using the same rules as the corresponding Form Request. For example:
```php
private function createSavingsAccount(array $input, User $user, bool $isPreview): array
{
    // Validate before creating
    $validator = Validator::make($input, [
        'current_balance' => 'required|numeric|min:0|max:999999999.99',
        'interest_rate' => 'nullable|numeric|min:0|max:25',
        'account_type' => ['nullable', Rule::in(['easy_access', 'notice', 'fixed_term', 'regular_saver'])],
    ]);

    if ($validator->fails()) {
        return ['error' => true, 'message' => $validator->errors()->first()];
    }
    // ... proceed with creation
}
```

#### R4. Enable Prompt Caching
**Files:** `AiChatService.php` (line 228-233)
**Effort:** Low (1 hour)
**Impact:** 70-90% reduction in input token costs for the system prompt and tool definitions across multi-turn conversations.

**Implementation:** Add `cache_control` to the system prompt content block:
```php
$payload = [
    'model' => $model,
    'max_tokens' => $maxTokens,
    'system' => [
        [
            'type' => 'text',
            'text' => $systemPrompt,
            'cache_control' => ['type' => 'ephemeral'],
        ],
    ],
    'messages' => $messages,
];
```

### Priority 2: High (Significant Quality Improvement)

#### R5. Restructure System Prompt with XML Tags
**Files:** `AiContextBuilder.php` (lines 27-84)
**Effort:** Medium (2-3 hours)
**Impact:** Clearer separation between instructions, context, and rules. Reduces instruction-following errors.

**Implementation:**
```
<identity>
You are Fynla Assistant, a warm and knowledgeable financial planning companion...
</identity>

<instructions>
1. Always use British English spelling...
2. Spell out all terms in full (e.g., "Inheritance Tax" not "IHT")...
3. Format all currency values in GBP with commas...
</instructions>

<regulatory_compliance>
You provide financial guidance, not regulated financial advice...
When a user asks for specific product recommendations...
</regulatory_compliance>

<user_profile>
{$profile}
</user_profile>

<financial_summary>
{$financialSummary}
</financial_summary>

<response_format>
Keep responses concise (2-4 paragraphs for simple questions)...
Use bold for key figures and important terms...
</response_format>
```

#### R6. Add Few-Shot Examples to System Prompt
**Files:** `AiContextBuilder.php`
**Effort:** Medium (2-3 hours)
**Impact:** Dramatically improves response consistency and quality. Sets tone, format, and depth expectations.

**Implementation:** Add 3-5 `<example>` blocks showing ideal interactions:
```xml
<examples>
<example>
<user>How is my protection looking?</user>
<assistant>Your protection position has some areas worth reviewing.

**Current Cover**
- Life insurance: £250,000 (level term, 20 years remaining)
- Income protection: None currently in place

**Key Gap**
Based on your annual income of £65,000 and mortgage of £280,000, a general guideline suggests life cover of around £650,000 to £975,000. Your current cover of £250,000 may leave a shortfall.

You may also want to consider income protection — if you were unable to work due to illness, your household income would drop to your partner's salary alone.

Would you like me to run through the numbers in more detail, or shall I take you to the Protection section?</assistant>
</example>
</examples>
```

#### R7. Expand Financial Summary Context
**Files:** `AiContextBuilder.php` (lines 136-191)
**Effort:** Medium (2-3 hours)
**Impact:** Richer context leads to more personalised and accurate responses without additional tool calls.

**Implementation:** Add to `buildFinancialSummary()`:
- Net worth (total assets - total liabilities)
- Total savings / total investments / total pension value
- Total protection cover (life + CI + IP)
- ISA allowance used this tax year
- Pension contributions this tax year
- User's estimated tax band
- Whether they own property (for RNRB context)

#### R8. Add Regulatory Compliance Section to Prompt
**Files:** `AiContextBuilder.php`
**Effort:** Low (1-2 hours)
**Impact:** Reduces regulatory risk. Ensures Claude consistently frames guidance appropriately.

**Implementation:** Replace the single-sentence disclaimer with a structured section:
```
<regulatory_compliance>
You provide financial guidance and education, NOT regulated financial advice.

Rules:
1. Frame all suggestions using hedging language: "you may want to consider", "it could be worth exploring", "one option to think about"
2. Never recommend specific financial products by name (e.g., never say "you should buy the Vanguard LifeStrategy fund")
3. For complex decisions (pension transfers, equity release, inheritance planning), always recommend the user speaks to a regulated financial adviser
4. Always include relevant risk warnings when discussing investments: "The value of investments can go down as well as up"
5. When discussing tax, remind users that tax rules can change and their personal circumstances affect tax treatment
6. Never provide advice on specific share purchases, fund selections, or market timing
</regulatory_compliance>
```

#### R9. Increase Max Tokens
**Files:** `AiModelResolver.php` (lines 18-25)
**Effort:** Trivial (5 minutes)
**Impact:** Prevents truncated responses and allows Claude to provide properly detailed analysis.

**Implementation:**
```php
public function getMaxTokens(User $user): int {
    $plan = $this->getUserPlan($user);
    return match ($plan) {
        'pro' => 8192,
        default => 4096,
    };
}
```

### Priority 3: Medium (Quality & Safety Refinements)

#### R10. Add Duplicate Detection for Data Creation
**Files:** `AiToolExecutor.php` (all `create*` methods)
**Effort:** Medium (3-4 hours)
**Impact:** Prevents accidental duplicate records when users repeat information or when tool calls are retried.

**Implementation:** Before each `::create()`, check for existing records with similar attributes:
```php
$existing = SavingsAccount::where('user_id', $user->id)
    ->where('account_name', 'LIKE', '%' . $input['account_name'] . '%')
    ->first();

if ($existing) {
    return [
        'warning' => true,
        'message' => "A similar account '{$existing->account_name}' already exists with a balance of £" .
            number_format($existing->current_balance, 2) . ". Would you like to update it instead?",
        'existing_id' => $existing->id,
    ];
}
```

#### R11. Add Out-of-Scope Query Handling
**Files:** `AiContextBuilder.php`
**Effort:** Low (30 minutes)
**Impact:** Prevents Claude from attempting to answer non-financial questions using general knowledge.

**Implementation:** Add to the system prompt:
```
<scope>
You only discuss topics related to personal financial planning, UK tax, pensions, savings, investments, protection, estate planning, and goals. If the user asks about unrelated topics (weather, sports, general knowledge, other software), politely redirect: "I'm designed to help with financial planning. Is there anything about your finances I can help with?"
</scope>
```

#### R12. Cache Financial Summary Explicitly
**Files:** `AiContextBuilder.php` (line 139)
**Effort:** Low (30 minutes)
**Impact:** Reduces latency and server load. The coordinating agent analysis is the most expensive operation and doesn't need to run for every message.

**Implementation:**
```php
private function buildFinancialSummary(User $user): string
{
    $cacheKey = "ai_context_financial_summary_{$user->id}";

    return Cache::remember($cacheKey, 120, function () use ($user) {
        try {
            $analysis = $this->coordinatingAgent->orchestrateAnalysis($user->id);
            // ... format as before
        } catch (\Exception $e) {
            // ... handle as before
        }
    });
}
```

#### R13. Improve Error Categorisation
**Files:** `AiChatService.php` (lines 71-84)
**Effort:** Low (1 hour)
**Impact:** Users see actionable error messages instead of generic text.

**Implementation:**
```php
if (isset($response['error'])) {
    $error = $response['error'];
    $statusCode = $response['status'] ?? 0;

    $hint = match (true) {
        $statusCode === 429 => 'You\'ve sent several messages quickly. Please wait a moment before trying again.',
        $statusCode === 529 => 'The service is temporarily busy. Please try again in a moment.',
        str_contains($error, 'api_key') || str_contains($error, 'authentication')
            => 'Configuration issue — please contact support.',
        str_contains($error, 'max_tokens') || str_contains($error, 'context_length')
            => 'This conversation has become quite long. Starting a new conversation may help.',
        default => 'I apologise, but I encountered an issue processing your request. Please try again.',
    };

    yield ['type' => 'error', 'message' => $hint];
    return;
}
```

#### R14. Preserve Tool Results in Message History
**Files:** `AiChatService.php` (lines 273-293), message storage
**Effort:** Medium (2-3 hours)
**Impact:** Claude retains awareness of previous tool results across conversation turns, enabling coherent multi-turn analysis discussions.

**Implementation:** Store tool call/result metadata alongside assistant messages. When building history, include summarised tool results for context:
```php
// When saving assistant message, include tool metadata
$assistantMessage = $this->saveMessage($conversation, 'assistant', $fullResponse, [
    'input_tokens' => $totalInputTokens,
    'output_tokens' => $totalOutputTokens,
    'model_used' => $model,
    'tool_calls' => $toolCallsSummary, // [{tool: 'get_module_analysis', module: 'protection', key_metrics: {...}}]
]);
```

#### R15. Consider Model Tiering
**Files:** `AiModelResolver.php`
**Effort:** Medium (2-3 hours)
**Impact:** Better responses for complex queries while keeping costs low for simple ones.

**Implementation:**
```php
public function getModel(User $user, ?string $queryComplexity = null): string
{
    // Pro users get Sonnet for complex queries
    if ($this->getUserPlan($user) === 'pro' && $queryComplexity === 'complex') {
        return 'claude-sonnet-4-6-20250514';
    }

    return config('services.anthropic.chat_model', self::DEFAULT_MODEL);
}
```

Complexity could be inferred from: tool calls made, message length, conversation depth, or specific keywords (analysis, plan, scenario).

### Priority 4: Low (Polish & Future Improvements)

#### R16. Add Streaming Cancel Button
**Files:** Frontend (`AiChatPanel.vue`, `aiChat.js`)
**Effort:** Low (1-2 hours)
**Impact:** Users can cancel long-running responses. Infrastructure already exists in Vuex.

#### R17. Add Response Format Instructions
**Files:** `AiContextBuilder.php`
**Effort:** Low (30 minutes)
**Impact:** More consistent, well-formatted responses.

```
<response_format>
- Keep responses concise: 2-4 paragraphs for simple questions, up to 6 for analysis
- Use bold (**text**) for key figures, account names, and important terms
- Use numbered lists for recommendations and action steps
- Use bullet points for data summaries
- Always end with a follow-up question or suggested next step
- Never start with "Based on your data" or similar preambles — go straight to the insight
</response_format>
```

#### R18. Add Personality and Empathy Guidelines
**Files:** `AiContextBuilder.php`
**Effort:** Low (30 minutes)
**Impact:** Warmer, more human interaction style.

```
<personality>
Be warm, encouraging, and clear. Financial planning can feel overwhelming — your role is to make it feel manageable. Celebrate progress ("Your emergency fund is in great shape!"). Be honest about gaps without being alarming. Use the user's first name occasionally. Keep jargon to a minimum but don't oversimplify — treat the user as intelligent but potentially unfamiliar with financial terminology.
</personality>
```

#### R19. Enrich Module Context Descriptions
**Files:** `AiContextBuilder.php` (lines 202-218)
**Effort:** Low (1 hour)
**Impact:** Better page-aware responses.

#### R20. Add Token Usage Monitoring and Alerts
**Files:** `AiModelResolver.php`, new middleware or service
**Effort:** Medium (3-4 hours)
**Impact:** Cost control, prevents runaway usage.

#### R21. Add Structured Error Returns from Tool Executor
**Files:** `AiToolExecutor.php` (line 77-85)
**Effort:** Low (1 hour)
**Impact:** Claude can understand and communicate specific tool errors to users.

---

## Summary

### Impact vs Effort Matrix

```
HIGH IMPACT
    │
    │  R1 (Streaming)     R5 (XML Prompt)
    │  R4 (Caching)       R6 (Examples)
    │  R2 (Strict Tools)  R7 (Context)
    │  R3 (Validation)    R8 (Compliance)
    │  R9 (Max Tokens)
    │
    │  R14 (Tool History)  R10 (Duplicates)
    │  R13 (Errors)        R15 (Model Tier)
    │  R12 (Cache Summary)
    │  R11 (Scope)
    │
    │  R17 (Format)   R18 (Personality)
    │  R16 (Cancel)   R19 (Module Context)
    │  R21 (Errors)   R20 (Monitoring)
    │
LOW IMPACT ──────────────────────────── HIGH EFFORT
          LOW EFFORT
```

### Cost Impact Estimate

| Change | Monthly Cost Impact |
|--------|-------------------|
| Prompt caching (R4) | **-60-70%** on input tokens |
| Increase max tokens (R9) | +10-20% on output tokens |
| Model tiering to Sonnet (R15) | +200% on select queries |
| True streaming (R1) | Neutral (same tokens) |
| **Net effect** | **Likely cost-neutral or savings** |

### Recommended Implementation Order

1. **Sprint 1 (Quick Wins):** R2 (strict tools), R4 (prompt caching), R9 (max tokens), R11 (scope), R12 (cache summary)
2. **Sprint 2 (Core Quality):** R1 (true streaming), R5 (XML prompt), R6 (few-shot examples), R8 (compliance)
3. **Sprint 3 (Data Integrity):** R3 (input validation), R7 (richer context), R10 (duplicate detection)
4. **Sprint 4 (Polish):** R13 (error messages), R14 (tool history), R15 (model tiering), R17 (response format), R18 (personality)
5. **Sprint 5 (Monitoring):** R16 (cancel button), R19 (module context), R20 (monitoring), R21 (error structure)

---

*This document is research only — no code changes have been made.*
