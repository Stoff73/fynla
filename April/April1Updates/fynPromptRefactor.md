# Fyn AI — System Prompt & RAG Refactor Plan

**Branch:** `fynImprovement`
**Date:** 1 April 2026
**Status:** Plan — pending approval

---

## Current State — Problems

The current system prompt in `HasAiChat.php` is a 670-line monolith built by accretion. It has:

1. **Everything in one string** — identity, security, instructions, compliance, knowledge, user data, records, completeness, scope, formatting, personality, actions, preview mode, data creation guidance — all concatenated into a single heredoc
2. **Static knowledge mixed with dynamic data** — financial planning concepts sit alongside per-user income breakdowns
3. **Rules scattered everywhere** — "don't show IDs" is in instructions, "don't mention taper" is in both instructions AND knowledge, "use hedging language" is in compliance
4. **No query-type awareness** — the same prompt is sent regardless of whether the user asks about pensions, property, or protection
5. **Financial knowledge is a blob** — `FinancialPlanningKnowledge.php` dumps all 7 domains every time, even if the user is asking about property
6. **No RAG** — everything is in the system prompt. No retrieval of relevant context based on the question

---

## Proposed Architecture

### 1. Split the Prompt into Composable Layers

Replace the single `buildSystemPrompt()` method with a layered builder:

```
SystemPromptBuilder
  ├── Layer 1: Core Identity (STATIC — same every time)
  │     Identity, security, scope, personality, response format
  │     ~800 tokens, cached forever
  │
  ├── Layer 2: Compliance & Rules (STATIC — same every time)
  │     FCA compliance, hedging language, acronym bans, no-ID rule
  │     ~600 tokens, cached forever
  │
  ├── Layer 3: FCA Process Instructions (STATIC — same every time)
  │     The 6-step process, KYC gate rules, structured response format
  │     ~400 tokens, cached forever
  │
  ├── Layer 4: User Profile (DYNAMIC — per user, cached 2 min)
  │     Name, age, income breakdown, tax band, expenditure, surplus,
  │     employment, marital status, children, retirement target
  │     ~300 tokens
  │
  ├── Layer 5: Financial Position (DYNAMIC — per user, cached 2 min)
  │     Net worth, module summaries, ranked recommendations,
  │     cashflow, shortfall, conflicts
  │     ~800 tokens
  │
  ├── Layer 6: Existing Records (DYNAMIC — per user, cached 1 min)
  │     Compact record list with IDs (for tool use), tax annotations
  │     ~400 tokens
  │
  ├── Layer 7: Data Completeness (DYNAMIC — per user, cached 1 min)
  │     Module readiness, missing fields, navigation rules
  │     ~300 tokens
  │
  ├── Layer 8: Query-Specific Knowledge (DYNAMIC — per query type)
  │     Only the relevant financial knowledge for THIS question
  │     Only the relevant decision tree triggers
  │     Only the required tool call sequence
  │     ~300-500 tokens (vs ~1,800 today for all knowledge)
  │
  ├── Layer 9: Query-Specific KYC Check (DYNAMIC — per query)
  │     Pre-computed: what data is present, what's missing
  │     If missing: instruction to prompt user, not give advice
  │     ~100-200 tokens
  │
  └── Layer 10: Context (DYNAMIC — per message)
        Current page context, tool usage rules, preview mode,
        data creation guidance (only if non-preview)
        ~400 tokens
```

**Total: ~4,000-4,500 tokens** (down from ~6,000-7,500 today, AND more relevant)

### 2. New File Structure

```
app/
  Services/
    AI/
      SystemPromptBuilder.php        ← NEW: orchestrates all layers
      Prompts/
        CoreIdentity.php             ← NEW: Layer 1 (static text)
        ComplianceRules.php          ← NEW: Layer 2 (static text)
        FcaProcessInstructions.php   ← NEW: Layer 3 (static text)
        QueryKnowledge.php           ← NEW: Layer 8 (per-query knowledge)
      QueryClassifier.php            ← NEW: classifies user message
      KycGateChecker.php             ← NEW: checks data per query type
  Constants/
    FinancialPlanningKnowledge.php   ← REFACTOR: split into per-domain methods
    QuerySchemas.php                 ← NEW: query type definitions
```

### 3. How It Works — Flow

```
User sends message
  │
  ├── 1. QueryClassifier.classify(message, currentRoute)
  │     → returns: query_type (e.g. "retirement_contribution")
  │
  ├── 2. KycGateChecker.check(user, query_type)
  │     → returns: { passed: bool, missing: [], prompt_text: string }
  │
  ├── 3. SystemPromptBuilder.build(user, query_type, kyc_result, currentRoute)
  │     → Assembles layers 1-10
  │     → Layer 8 only includes knowledge for THIS query type
  │     → Layer 9 includes KYC result (pass or missing data instruction)
  │
  ├── 4. If KYC failed: prompt is instructed to ask for data, not give advice
  │     If KYC passed: prompt includes tool sequence + response schema
  │
  └── 5. Send to AI model with assembled prompt + message history + tools
```

### 4. Layer Details

#### Layer 1: Core Identity (~200 tokens)
```
You are Fyn, a knowledgeable UK financial planner built into the Fynla
application. You follow the FCA 6-step financial planning process:
establish, gather, analyse, recommend, implement, review. You have
access to this user's actual financial data and use it in every response.
```

Personality rules (warm, honest, plain language, celebrate progress, empathetic). Response format (bold £ amounts, numbered steps, no filler phrases, follow-up question).

#### Layer 2: Compliance & Rules (~400 tokens)
- FCA hedging language (mandatory)
- No product recommendations
- Signpost regulated advice
- Risk warnings for investments
- Tax caveats (2025/26, may change)
- No market timing
- Always use `get_tax_information` for figures
- British English, no acronyms (17 banned), no IDs, no jargon
- Joint ownership: always distinguish user's share

#### Layer 3: FCA Process Instructions (~400 tokens)
```
FOR EVERY QUESTION THAT REQUIRES FINANCIAL ADVICE:

Step 1 — CHECK DATA: Review <kyc_status> below. If any BLOCKING
fields are missing, do NOT give advice. Instead, list what's missing,
explain why it matters, and offer to help enter it.

Step 2 — FETCH DATA: Call the required tools listed in <required_tools>
before responding. Do not skip any.

Step 3 — ANALYSE: Use the data from tool calls and <financial_position>
to assess the user's situation against the triggers in <relevant_triggers>.

Step 4 — RECOMMEND: Give specific £ amounts based on the analysis.
Use numbered action steps. Reference the user's actual figures.

Step 5 — IMPLEMENT: Offer to execute (create account, set contribution,
record policy) using the appropriate tool.

Step 6 — FOLLOW UP: End with a relevant next question or offer to
review a related area.
```

#### Layer 8: Query-Specific Knowledge (DYNAMIC)

Instead of dumping all 7 knowledge domains, only include what's relevant:

| Query Type | Knowledge Included |
|-----------|-------------------|
| Pension contributions | Pension knowledge + income classifications + affordability |
| Emergency fund | Savings emergency fund triggers only |
| ISA planning | Investment tax wrappers (ISA section only) |
| Life cover | Protection concepts |
| Inheritance Tax | Estate planning concepts |
| Investment fees | Investment tax wrappers (fee-related only) |
| Property query | No knowledge needed — just data |
| General health | Recommendation framework summary |

This cuts knowledge tokens from ~1,800 to ~300-500 per query.

Also includes the specific decision tree triggers to check:
```
<relevant_triggers>
For this pension contribution question, check:
- Is employment income between £100,000 and £125,140? → PA reclaim opportunity (60% effective relief)
- Is emergency fund below 3 months' expenses? → Prioritise emergency fund alongside pension
- What is the monthly surplus? → Maximum affordable contribution
- What are relevant UK earnings? → Cap on tax-relieved contributions
</relevant_triggers>
```

And the required tool calls:
```
<required_tools>
You MUST call these tools before responding:
1. get_tax_information(pension_allowances)
2. get_tax_information(income_definitions)
3. get_module_analysis(retirement)
</required_tools>
```

#### Layer 9: KYC Check Result (DYNAMIC)

Pre-computed before sending to AI:
```
<kyc_status>
Query type: retirement_contribution
Status: PASS — all required data present
✅ Date of birth: 15 June 1980 (age 45)
✅ Employment income: £100,000
✅ Total income: £108,755 (6 sources)
✅ Monthly expenditure: £2,800
✅ Monthly surplus: £3,307.60
✅ Existing pensions: 1 (SIPP, £0 value)
⚠️ No risk profile completed (warning — not blocking)
</kyc_status>
```

Or if data is missing:
```
<kyc_status>
Query type: retirement_contribution
Status: BLOCKED — missing required data
❌ Monthly expenditure — needed to calculate surplus and affordability
❌ Target retirement age — needed for projection calculations
✅ Date of birth: confirmed
✅ Employment income: £100,000

INSTRUCTION: Do NOT give pension contribution advice. Instead:
1. Explain that you need expenditure and retirement age to give accurate advice
2. Offer to help enter monthly expenditure now ("Tell me roughly what you spend each month")
3. Offer to navigate to /valuable-info?section=expenditure
</kyc_status>
```

### 5. What Gets Removed from HasAiChat.php

| Current Section | Where It Goes |
|----------------|--------------|
| `<identity>` block (lines 462-465) | `Prompts/CoreIdentity.php` |
| `<security>` block (lines 467-478) | `Prompts/CoreIdentity.php` |
| `<instructions>` block (lines 480-492) | `Prompts/ComplianceRules.php` |
| `<regulatory_compliance>` block (lines 494-502) | `Prompts/ComplianceRules.php` |
| `<financial_knowledge>` block (line 504-506) | `QueryKnowledge.php` (split by query type) |
| `<data_completeness>` block (lines 520-545) | `KycGateChecker.php` + Layer 9 |
| `<scope>` block (lines 552-560) | `Prompts/CoreIdentity.php` |
| `<response_format>` block (lines 562-574) | `Prompts/CoreIdentity.php` |
| `<personality>` block (lines 576-588) | `Prompts/CoreIdentity.php` |
| `<available_actions>` block (lines 590-635) | `Prompts/FcaProcessInstructions.php` + Layer 10 |
| `<preview_mode>` block (lines 637-644) | Layer 10 (conditional) |
| `<data_creation_guidance>` block (lines 647-667) | Layer 10 (conditional) |
| `FinancialPlanningKnowledge.php` (all domains) | `QueryKnowledge.php` (per-domain methods) |

`buildSystemPrompt()` becomes a 20-line method that calls `SystemPromptBuilder::build()`.

### 6. RAG-Like Retrieval

Currently there's no retrieval — everything is in the prompt. The refactored system adds retrieval at two levels:

**Level 1: Query-type knowledge retrieval**
`QueryKnowledge::getForQueryType('retirement_contribution')` returns only pension + income knowledge, not estate or protection.

**Level 2: Recommendation retrieval**
Instead of dumping top 5 recommendations from `orchestrateAnalysis()` into the prompt, only include recommendations relevant to the query type:
- Pension question → only retirement + tax recommendations
- Property question → only estate + property recommendations
- General "what should I focus on" → top 5 across all modules

**Level 3: Record retrieval**
Instead of listing ALL records, only include records relevant to the query:
- Pension question → only pension records + surplus
- Property question → only property records with mortgages
- Protection question → only protection policies + family members

This further reduces tokens while increasing relevance.

### 7. HasAiChat.php Refactored

```php
protected function buildSystemPrompt(User $user, ?string $currentRoute = null, ?string $queryType = null): string
{
    $builder = app(SystemPromptBuilder::class);

    return $builder->build(
        user: $user,
        queryType: $queryType,
        currentRoute: $currentRoute,
        isPreview: $user->is_preview_user,
    );
}
```

And the `chat()` method adds the classification step:

```php
// Before calling AI, classify and check KYC
$queryType = app(QueryClassifier::class)->classify($message, $currentRoute);
$kycResult = app(KycGateChecker::class)->check($user, $queryType);

// Build query-aware prompt
$systemPrompt = $this->buildSystemPrompt($user, $currentRoute, $queryType);
```

### 8. Token Budget Comparison

| Component | Current | Refactored |
|-----------|---------|-----------|
| Static (identity, security, compliance, format, personality) | ~2,500 | ~1,000 (tighter, no duplication) |
| Financial knowledge | ~1,800 (all domains) | ~300-500 (query-relevant only) |
| User profile + surplus | ~300 | ~300 (same) |
| Financial position | ~800 | ~400 (query-relevant modules only) |
| Existing records | ~400 | ~200 (query-relevant records only) |
| Data completeness | ~300 | ~200 (KYC result, not full module list) |
| Actions + creation guidance | ~600 | ~400 (streamlined) |
| Query-specific (triggers, tools, schema) | 0 | ~300 |
| **Total** | **~6,700** | **~3,100-3,300** |

**50% token reduction with MORE relevant context.**

### 9. Implementation Sequence

| Step | What | Files | Depends On |
|------|------|-------|-----------|
| 1 | Create `SystemPromptBuilder` with layer assembly | `SystemPromptBuilder.php` | Nothing |
| 2 | Extract static layers into separate files | `CoreIdentity.php`, `ComplianceRules.php`, `FcaProcessInstructions.php` | Step 1 |
| 3 | Create `QueryClassifier` | `QueryClassifier.php` | Nothing |
| 4 | Create `KycGateChecker` | `KycGateChecker.php` | Step 3 |
| 5 | Refactor `FinancialPlanningKnowledge` into per-domain retrieval | `QueryKnowledge.php` | Step 3 |
| 6 | Add query-aware record + recommendation filtering | `SystemPromptBuilder.php` | Steps 3, 5 |
| 7 | Rewire `HasAiChat.buildSystemPrompt()` to use builder | `HasAiChat.php` | Steps 1-6 |
| 8 | Test all query types against existing conversations | Browser testing | Step 7 |

Steps 1-2 and 3-4 can be done in parallel.

---

## Key Design Decisions

1. **Static layers are PHP constants** — not database, not config files. They change once per release, not per request.

2. **Query classification happens BEFORE the AI call** — it's a simple keyword/route matcher in PHP, not an AI classification. Fast and deterministic.

3. **KYC check is pre-computed** — the result is injected into the prompt as a `<kyc_status>` block. The AI reads it and follows the instruction (give advice OR ask for data). No separate AI call needed.

4. **Knowledge is retrieved, not dumped** — `QueryKnowledge::getForQueryType()` returns only the relevant domain. This is the RAG-like component.

5. **Records and recommendations are filtered** — only query-relevant data is included. A pension question doesn't need to see chattel records.

6. **The AI model doesn't change** — still Grok or Haiku. The improvement comes from better prompting, not a different model.

7. **Backward compatible** — if query classification fails, fall back to the full prompt (all knowledge, all records). Degraded but functional.
