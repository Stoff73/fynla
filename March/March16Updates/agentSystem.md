# Fynla Agent System Architecture

**Version:** 1.0 | **Date:** 16 March 2026 | **Scope:** Complete agent system documentation

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Agent Architecture](#2-agent-architecture)
3. [Claude API Integration](#3-claude-api-integration)
4. [Decision Trees & Reasoning Engine](#4-decision-trees--reasoning-engine)
5. [Context Gathering](#5-context-gathering)
6. [Guardrails & Safety Measures](#6-guardrails--safety-measures)
7. [Agent Loop: Start & End Processes](#7-agent-loop-start--end-processes)
8. [AI Chat: Replies & Interactions](#8-ai-chat-replies--interactions)
9. [Tool Execution Framework](#9-tool-execution-framework)
10. [Structured Data Formats](#10-structured-data-formats)
11. [Caching Architecture](#11-caching-architecture)
12. [Prompt Engineering & Optimisation](#12-prompt-engineering--optimisation)
13. [Conflict Resolution & Cross-Module Coordination](#13-conflict-resolution--cross-module-coordination)
14. [Priority Ranking Algorithm](#14-priority-ranking-algorithm)
15. [Document Extraction (Claude Vision)](#15-document-extraction-claude-vision)
16. [Intent Matching & Routing](#16-intent-matching--routing)
17. [Streaming & Server-Sent Events](#17-streaming--server-sent-events)
18. [Error Handling & Resilience](#18-error-handling--resilience)
19. [File Reference Map](#19-file-reference-map)
20. [Architecture Diagrams](#20-architecture-diagrams)

---

## 1. System Overview

Fynla's agent system is a **dual-layer architecture** combining:

1. **Rule-Based Decision Agents** (9 PHP agents) — deterministic financial analysis engines that use database-driven action definitions, threshold-based triggers, and weighted scoring algorithms. These do **not** call any external AI API.

2. **Claude AI Integration Layer** (7 PHP services) — conversational AI powered by the Anthropic Messages API, providing streaming chat, tool execution, document extraction, and context-aware financial guidance.

The two layers interconnect: the AI chat layer calls the rule-based agents to gather real-time financial data, which it then weaves into personalised, conversational responses.

### Technology Stack

| Component | Technology |
|-----------|-----------|
| Rule-based agents | PHP 8.2, Laravel 10 service container |
| AI integration | Anthropic Messages API (direct HTTP via Guzzle) |
| Chat model (default) | `claude-haiku-4-5-20251001` |
| Chat model (complex/Pro) | `claude-sonnet-4-5-20241022` |
| Document extraction | `claude-3-5-haiku-20241022` (Vision) |
| Streaming protocol | Server-Sent Events (SSE) via `StreamedResponse` |
| Cache layer | Laravel Cache (file/Redis, tag-aware) |
| Queue (long-running) | Laravel Queue (`ShouldQueue` jobs) |

### Key Metrics

| Metric | Count |
|--------|-------|
| Module agents | 7 + 1 coordinating + 1 base abstract = 9 |
| AI services | 7 (`app/Services/AI/` + `AIExtractionService`) |
| Coordination services | 5 (ConflictResolver, PriorityRanker, CashFlowCoordinator, HolisticPlanner, CrossModuleStrategyService) |
| Action definition models | 5 (Protection, Savings, Investment, Retirement, Tax) |
| Data readiness services | 5 (one per module) |
| AI tool definitions | 17 tools across 5 categories |
| Observers | 11 (risk recalculation, goal tracking, Monte Carlo triggers) |

---

## 2. Agent Architecture

### 2.1 BaseAgent Abstract Class

**File:** `app/Agents/BaseAgent.php`

All module agents extend `BaseAgent`, which enforces a three-method contract and provides shared infrastructure:

```php
abstract class BaseAgent
{
    use FormatsCurrency;

    protected const CACHE_VERSION = 'v1';
    protected int $cacheTtl = TaxDefaults::CACHE_TTL_STANDARD; // 3600s

    // === Contract methods (every agent must implement) ===
    abstract public function analyze(int $userId): array;
    abstract public function generateRecommendations(array $analysisData): array;
    abstract public function buildScenarios(int $userId, array $parameters): array;

    // === Infrastructure ===
    protected function remember(string $key, callable $callback, ?int $ttl = null, array $tags = []): mixed;
    protected function rememberForUser(int $userId, string $suffix, callable $callback, ?int $ttl = null): mixed;
    public function invalidateUserCache(int $userId, array $additionalKeys = []): void;
    public function invalidateCacheForUsers(array $userIds, array $additionalKeys = []): void;
    public function clearUserCache(int $userId, array $suffixes = ['analysis', 'recommendations', 'scenarios']): void;
    protected function response(bool $success, string $message, array $data = []): array;
    protected function roundToPenny(float $value): float;
}
```

**Cache key pattern:** `v1_{agentname}_{userId}_{suffix}`

**Tag-aware caching:** Automatically detects whether the cache store supports tagging (Redis/Memcached) and falls back to key-based invalidation (file/database).

### 2.2 Module Agents

Each agent is injected with its domain-specific services via constructor DI:

| Agent | Key Dependencies | Primary Analysis Focus |
|-------|-----------------|----------------------|
| **ProtectionAgent** | CoverageGapAnalyzer, AdequacyScorer, RecommendationEngine, ScenarioBuilder, ProtectionDataReadinessService | Life, critical illness, income protection coverage gaps and adequacy |
| **SavingsAgent** | EmergencyFundCalculator, ISATracker, GoalProgressCalculator, LiquidityAnalyzer, RateComparator, SavingsActionDefinitionService, PSACalculator, FSCSAssessor | Emergency fund runway, ISA usage, liquidity, FSCS protection |
| **InvestmentAgent** | PortfolioAnalyzer, DiversificationAnalyzer, MonteCarloSimulator, AssetAllocationOptimizer, FeeAnalyzer, TaxEfficiencyCalculator, InvestmentActionDefinitionService | Portfolio health, diversification, fees, tax efficiency |
| **RetirementAgent** | PensionProjector, AnnualAllowanceChecker, ContributionOptimizer, DecumulationPlanner, PensionPortfolioAnalyzer, RetirementActionDefinitionService | Pension projections, Annual Allowance, contribution optimisation |
| **EstateAgent** | IHT calculation services, gifting strategy services, trust recommendation services, EstateDataReadinessService | Inheritance Tax liability, gifting strategies, trust recommendations |
| **GoalsAgent** | GoalAssignmentService, GoalAffordabilityService, GoalProgressService, GoalRiskService | Goal progress, contribution tracking, affordability analysis |
| **TaxOptimisationAgent** | Cross-module tax analysis services, TaxActionDefinitionService | Salary sacrifice, ISA utilisation, cross-module tax strategies |

### 2.3 CoordinatingAgent (Meta-Orchestrator)

**File:** `app/Agents/CoordinatingAgent.php` (591 lines)

The CoordinatingAgent is the system's master orchestrator. It does not perform its own financial calculations — instead, it:

1. Calls all 7 module agents
2. Maps each agent's output to a normalised format
3. Calculates available cash flow surplus
4. Identifies conflicts between recommendations
5. Resolves conflicts using weighted allocation
6. Ranks all recommendations by priority
7. Optimises cash flow allocation across competing demands
8. Generates cross-module strategies

```php
public function __construct(
    private readonly ConflictResolver $conflictResolver,
    private readonly PriorityRanker $priorityRanker,
    private readonly HolisticPlanner $holisticPlanner,
    private readonly CashFlowCoordinator $cashFlowCoordinator,
    private readonly CrossModuleStrategyService $crossModuleStrategyService,
    private readonly ProtectionAgent $protectionAgent,
    private readonly InvestmentAgent $investmentAgent,
    private readonly SavingsAgent $savingsAgent,
    private readonly RetirementAgent $retirementAgent,
    private readonly EstateAgent $estateAgent,
    private readonly GoalsAgent $goalsAgent,
    private readonly TaxOptimisationAgent $taxOptimisationAgent,
    private readonly TaxConfigService $taxConfig
) {}
```

---

## 3. Claude API Integration

### 3.1 Overview

Fynla integrates with the Anthropic Messages API in two distinct ways:

1. **Conversational AI Chat** — Streaming multi-turn conversations with tool calling (`AiChatService`)
2. **Document Extraction** — Single-shot Claude Vision calls for financial document parsing (`AIExtractionService`)

Both use direct HTTP calls via Guzzle (no SDK package). The `composer.json` and `package.json` contain **no** Anthropic or AI SDK dependencies — all integration is custom.

### 3.2 Configuration

**File:** `config/services.php`

```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY', ''),      // sk_... format
    'chat_model' => env('ANTHROPIC_CHAT_MODEL', 'claude-haiku-4-5-20251001'),
],
```

**Environment variables:**
- `ANTHROPIC_API_KEY` — API secret key
- `ANTHROPIC_CHAT_MODEL` — Default chat model override

### 3.3 Model Selection (AiModelResolver)

**File:** `app/Services/AI/AiModelResolver.php`

The model resolver implements **complexity-based model tiering**:

```
User message → classifyComplexity() → 'standard' or 'complex'
                                         ↓
                              getModel(user, complexity)
                                         ↓
                    ┌────────────────────┴────────────────────┐
                    │ Standard complexity                       │ Complex + Pro plan
                    │ claude-haiku-4-5-20251001                │ claude-sonnet-4-5-20241022
                    └──────────────────────────────────────────┘
```

**Complexity classification** uses keyword pattern matching:

```php
$complexPatterns = [
    'financial plan', 'holistic plan', 'comprehensive',
    'what if', 'scenario', 'compare',
    'inheritance tax', 'estate planning',
    'pension transfer', 'retirement projection',
    'tax efficiency', 'capital gains',
];
```

Messages matching these patterns, or conversations deeper than 6 turns, are classified as `complex`.

### 3.4 Token Budgeting

Daily token limits are enforced per subscription tier:

| Plan | Daily Token Limit | Max Response Tokens |
|------|-------------------|---------------------|
| Student | 50,000 | 4,096 |
| Standard | 200,000 | 4,096 |
| Pro | 500,000 | 8,192 |

Token usage is tracked per user per day via `AiConversation` table aggregation, cached for 5 minutes. The `hasTokenBudget()` check runs before every API call — if exceeded, the user receives a friendly daily limit message.

### 3.5 API Request Flow

**File:** `app/Services/AI/AiChatService.php`

```
1. Save user message to DB
2. Check token budget → reject if exceeded
3. Build system prompt (AiContextBuilder)
4. Build message history (last 20 messages)
5. Classify complexity → select model
6. Get tool definitions → filtered by preview mode
7. Auto-generate conversation title (first message only)
8. Enter API call loop:
   a. Stream from Anthropic Messages API
   b. Yield text deltas in real-time (SSE)
   c. Accumulate tool_use blocks
   d. Execute each tool via AiToolExecutor
   e. Append tool results to message history
   f. If stop_reason == 'tool_use' AND toolCallCount < 5 → loop
   g. Else → break
9. Save assistant message with metadata (tokens, model, tool calls)
10. Update conversation token counts
11. Invalidate daily usage cache
12. Yield 'done' event with message ID
```

**Key constants:**
- `MAX_TOOL_CALLS_PER_TURN = 5` — prevents runaway tool loops
- `MAX_HISTORY_MESSAGES = 20` — conversation window
- `TIMEOUT_SECONDS = 300` — 5-minute API timeout

### 3.6 Prompt Caching

The system prompt uses Anthropic's **ephemeral prompt caching** to reduce token costs on multi-turn conversations:

```php
'system' => [
    [
        'type' => 'text',
        'text' => $systemPrompt,
        'cache_control' => ['type' => 'ephemeral'],
    ],
],
```

Header: `'anthropic-beta' => 'prompt-caching-2024-07-31'`

This means the system prompt (which includes the full user financial summary) is cached across turns within a conversation, significantly reducing input token costs for sustained interactions.

---

## 4. Decision Trees & Reasoning Engine

### 4.1 Database-Driven Action Definitions

The core recommendation engine uses **database-stored trigger definitions** rather than hardcoded logic. This allows non-code changes to decision rules.

**Action Definition Models:**
- `ProtectionActionDefinition`
- `SavingsActionDefinition`
- `InvestmentActionDefinition`
- `RetirementActionDefinition`
- `TaxActionDefinition`

**Schema per definition:**

| Field | Type | Purpose |
|-------|------|---------|
| `trigger_config` | JSON | Condition thresholds and parameters |
| `title_template` | string | Template with `{variable}` placeholders |
| `description_template` | string | Detailed description template |
| `action_template` | string | Suggested action template |
| `source` | enum | `'agent'` or `'goal'` |
| `priority` | int | Base priority weight |
| `category` | string | Recommendation category |
| `scope` | string | Applicability scope |
| `is_enabled` | boolean | Toggle for non-code enable/disable |

### 4.2 Trigger Evaluation Pipeline

Each `ActionDefinitionService` evaluates triggers using a `match` statement pattern:

```php
// ProtectionActionDefinitionService example
match ($condition) {
    'gap_exists'                  => $this->evaluateGapCondition($definition, $plan),
    'policy_not_in_trust'         => $this->evaluatePolicyNotInTrust($definition, $plan),
    'mortgage_no_decreasing_term' => $this->evaluateMortgageNoDecreasingTerm($definition, $plan),
    'dependants_no_life_cover'    => $this->evaluateDependantsNoLifeCover($definition, $plan),
    'profile_missing'             => $this->evaluateProfileMissing($definition, $plan),
    'income_replacement_ratio'    => $this->evaluateIncomeReplacement($definition, $plan),
};
```

**Template rendering** replaces `{variable}` placeholders with calculated values:

```php
$definition->renderTitle(['shortfall' => 5000, 'gap' => '£85,000']);
// "Increase your life cover to close the £85,000 gap"
```

Unused placeholders are stripped: `preg_replace('/\{[a-z_]+\}/', '', $template)`

### 4.3 Decision Trace

Every recommendation includes a `decision_trace` array — a complete audit trail of the decision path:

```php
[
    'decision_trace' => [
        ['check' => 'dependants_count', 'value' => 2, 'passed' => true],
        ['check' => 'coverage_gap', 'value' => 85000, 'threshold' => 10000, 'passed' => true],
        ['check' => 'life_cover_exists', 'value' => false, 'passed' => true],
    ],
]
```

This provides **full transparency** into how each recommendation was generated — useful for debugging, audit compliance, and the Actions Dashboard detail views.

### 4.4 Investment Decision Tree (Reference Pipeline)

The investment module has the most elaborate decision pipeline, documented in `investmentTree/investment-decision-tree.md`:

| Phase | Name | Description |
|-------|------|-------------|
| 1 | Data Readiness Gate | Minimum data checks — blocks analysis if insufficient |
| 2a | Life Event Assessment | Modifier checks: blocks, triggers, wrappers based on life events |
| 2b | Goal Assessment | Goal-based modifiers adjusting recommendations |
| 3 | Safety Checks | Debt assessment (high-interest > 15%), protection validation |
| 4 | Contribution Waterfall | 9-step priority ordering for contribution allocation |
| 5 | Transfer Scans | 7 different transfer scenario evaluations |
| 6 | Spouse Optimisation | 6 spousal coordination strategies |
| 7 | Conflict Resolution | Merge, deduplicate, resolve inter-module conflicts |
| Output | Formatting | Sort by priority, format for API response |

---

## 5. Context Gathering

### 5.1 Data Readiness Services

Before any agent runs its analysis, a **Data Readiness Service** validates that the user has sufficient data:

**Services:**
- `ProtectionDataReadinessService`
- `SavingsDataReadinessService`
- `RetirementDataReadinessService`
- `EstateDataReadinessService`
- `Investment\Recommendation\DataReadinessService`

**Readiness check structure:**

```php
$readiness = $this->readinessService->assess($user);

// Returns:
[
    'can_proceed' => bool,        // false = block analysis entirely
    'checks' => [
        [
            'name' => 'has_income_data',
            'passed' => bool,
            'level' => 'blocking' | 'warning' | 'info',
            'message' => 'Please add your income details',
            'form_link' => '/profile/income',
        ],
    ],
]
```

**Three severity levels:**
- **Blocking** — analysis cannot proceed without this data
- **Warning** — analysis proceeds but with caveats in output
- **Info** — useful but not required

### 5.2 UserContextBuilder

The CoordinatingAgent compiles a comprehensive user profile from multiple database tables:

- **Personal Profile:** age, gender, marital status, employment, retirement age, dependants
- **Financial Profile:** gross/net income, expenditure, disposable income, tax band
- **Risk Profile:** risk level, tolerance, capacity, investment experience, ESG preference
- **Debt Profile:** high/medium interest debts, promotional expirations
- **Emergency Fund:** total balance, runway (months), target, shortfall
- **Allowances:** ISA remaining, LISA, pension Annual Allowance, carry forward, CGT, PSA
- **Spouse Profile:** income, tax band, optimisation targets (if married)

### 5.3 AI Context Builder

**File:** `app/Services/AI/AiContextBuilder.php`

For AI chat, the context builder dynamically constructs the system prompt from:

1. **User profile section** — name, age, employment, income, expenditure, tax band, children, retirement date
2. **Financial summary** — calls `CoordinatingAgent.orchestrateAnalysis()` (cached 2 minutes per user), extracts: monthly surplus, total savings, emergency fund months, investment portfolio, pension value, projected retirement income, protection cover, IHT liability, top 3 recommendations
3. **Module context** — page-specific guidance based on `currentRoute` (14 route contexts defined)
4. **Regulatory compliance** — mandatory FCA hedging language, no product recommendations, signposting
5. **Response format** — brevity, bold for key figures, follow-up questions
6. **Personality** — warm, encouraging, honest, plain language
7. **Examples** — 5 worked conversation examples
8. **Available actions** — when to navigate, fetch analysis, run scenarios, look up tax, create records
9. **Preview mode** (conditional) — restrictions for demo users
10. **Data creation guidance** (conditional, non-preview only) — proactive record creation patterns

---

## 6. Guardrails & Safety Measures

### 6.1 Data Readiness Gates (Pre-Analysis)

Every agent checks `DataReadinessService.assess()` before running calculations. If `can_proceed === false`, the agent returns early with a structured "incomplete data" response rather than producing potentially misleading analysis.

### 6.2 Safety Checks in Recommendation Pipeline

Specific safety checks prevent dangerous financial advice:

- **Critical debt blocks:** High-interest debts (> 15% APR) block investment recommendations — debt repayment takes priority
- **Emergency fund validation:** Investment recommendations are suppressed until emergency fund reaches minimum threshold (1 month)
- **Protection adequacy before growth:** Protection gaps must be addressed before recommending growth-oriented contributions
- **Pension affordability tiers:** Contribution recommendations are scaled based on disposable income percentages — never recommend unaffordable contributions

### 6.3 Preview User Isolation

**PreviewWriteInterceptor middleware** (`app/Http/Middleware/PreviewWriteInterceptor.php`):

- Intercepts all write operations (POST/PUT/PATCH/DELETE) from preview users
- Returns fake success responses (preserving UI flow without database changes)
- **Analysis endpoints pass through** — `/analyze`, `/calculate`, `/projections` are read-only
- AI tool definitions exclude data creation tools for preview users (`getTools(true)`)

### 6.4 Regulatory Compliance (AI Chat)

The system prompt enforces FCA-compliant language:

1. **Hedging language mandatory:** "you may want to consider", "it could be worth exploring" — never "you should" or "you must"
2. **No product recommendations:** Can describe product types but never name specific providers, funds, or platforms
3. **Signpost regulated advice:** Complex tax, pension transfers, investment decisions → suggest regulated financial adviser
4. **Risk warnings:** Investment values can go down; past performance not reliable indicator
5. **Tax caveats:** Based on current UK legislation and 2025/26 tax year; depends on individual circumstances
6. **No market timing:** Never suggest now is good/bad time to invest

### 6.5 Scope Restriction

The AI assistant refuses to engage with non-financial topics:

```
<scope>
You are a personal financial planning assistant. You only discuss topics directly
related to the user's personal financial planning...

If a user asks about something outside this scope — politely explain that you are
only able to help with their personal financial planning.
</scope>
```

### 6.6 Token Budget Enforcement

Daily token limits prevent cost overruns:

```php
if (!$this->modelResolver->hasTokenBudget($user)) {
    yield ['type' => 'error', 'message' => "You've reached your daily message limit..."];
    return;
}
```

### 6.7 Tool Call Limits

Maximum 5 tool calls per turn prevents runaway agent loops:

```php
if ($hasToolCalls && $stopReason === 'tool_use' && $toolCallCount < self::MAX_TOOL_CALLS_PER_TURN) {
    continue; // Loop for more tool calls
}
break; // Safety exit
```

### 6.8 Input Validation on Tool Execution

The `AiToolExecutor` validates all tool inputs using Laravel's `Validator` before executing any data creation:

```php
$validator = Validator::make($args, [
    'account_name' => 'required|string|max:255',
    'current_balance' => 'required|numeric|min:0|max:999999999.99',
    // ...
]);

if ($validator->fails()) {
    return ['error' => 'Validation failed', 'details' => $validator->errors()->toArray()];
}
```

### 6.9 Graceful Degradation

The CoordinatingAgent wraps every module call in `safeModuleAnalysis()`:

```php
private function safeModuleAnalysis(string $module, callable $analyzer, callable $defaultProvider): array
{
    try {
        return $analyzer();
    } catch (\Exception $e) {
        Log::error("{$module} analysis failed: " . $e->getMessage());
        return $defaultProvider(); // Sensible defaults, not empty
    }
}
```

A single module failure (e.g., RetirementAgent throws) does not cascade — all other modules continue and the holistic view is still produced with default values for the failed module.

---

## 7. Agent Loop: Start & End Processes

### 7.1 Rule-Based Agent Lifecycle

```
┌─────────────────────────────────────────────────────────────┐
│                    AGENT ANALYSIS LOOP                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  START                                                      │
│  ├── Controller receives request (auth:sanctum)             │
│  ├── Form Request validates input                           │
│  ├── Controller calls Agent->analyze($userId)               │
│  │                                                          │
│  │   AGENT INTERNAL:                                        │
│  │   ├── Check cache → HIT? return cached result            │
│  │   ├── MISS:                                              │
│  │   │   ├── DataReadinessService->assess()                 │
│  │   │   │   └── can_proceed = false? → return early        │
│  │   │   ├── Gather user context data from DB               │
│  │   │   ├── Run domain calculations                        │
│  │   │   │   ├── Coverage gap analysis                      │
│  │   │   │   ├── Adequacy scoring                           │
│  │   │   │   ├── Projection calculations                    │
│  │   │   │   └── Risk assessments                           │
│  │   │   ├── Generate recommendations                       │
│  │   │   │   ├── Load enabled ActionDefinitions from DB     │
│  │   │   │   ├── Evaluate each trigger against analysis     │
│  │   │   │   ├── Render title/description templates         │
│  │   │   │   └── Build decision trace arrays                │
│  │   │   ├── Cache result with TTL                          │
│  │   │   └── Return $this->response(true, 'msg', $data)    │
│  │                                                          │
│  END                                                        │
│  ├── Controller formats JSON response                       │
│  ├── Vuex store commits data                                │
│  └── Vue components re-render                               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 7.2 CoordinatingAgent Orchestration Loop

```
orchestrateAnalysis($userId)
  │
  ├── collectModuleAnalysis($userId)
  │     ├── safeModuleAnalysis('Protection', protectionAgent->analyze())
  │     │     └── mapProtectionAnalysis() → normalised format
  │     ├── safeModuleAnalysis('Savings', savingsAgent->analyze() + generateRecommendations())
  │     │     └── mapSavingsAnalysis()
  │     ├── safeModuleAnalysis('Investment', investmentAgent->analyze() + generateRecommendations())
  │     │     └── mapInvestmentAnalysis()
  │     ├── safeModuleAnalysis('Retirement', retirementAgent->analyze() + generateRecommendations())
  │     │     └── mapRetirementAnalysis()
  │     ├── safeModuleAnalysis('Estate', estateAgent->analyze() + generateRecommendations())
  │     │     └── mapEstateAnalysis()
  │     ├── safeModuleAnalysis('Goals', goalsAgent->analyze() + generateRecommendations())
  │     └── safeModuleAnalysis('TaxOptimisation', taxOptimisationAgent->analyze() + generateRecommendations())
  │
  ├── cashFlowCoordinator->calculateAvailableSurplus($userId)
  │
  ├── extractRecommendations($allAnalysis)
  │     └── Collects recommendations from all modules + module_scores
  │
  ├── conflictResolver->identifyConflicts($allRecommendations)
  │     ├── detectCashflowConflicts()
  │     ├── detectISAConflicts()
  │     ├── detectProtectionVsSavingsConflicts()
  │     └── detectEstateVsGoalsConflicts()
  │
  ├── resolveConflicts($allRecommendations, $conflicts)
  │     ├── resolveProtectionVsSavings()
  │     ├── resolveContributionConflicts()
  │     └── resolveISAAllocation()
  │
  ├── priorityRanker->rankRecommendations($resolved, $userContext)
  │     └── score = (urgency × 0.4) + (impact × 0.3) + (ease × 0.2) + (userPriority × 0.1)
  │
  ├── cashFlowCoordinator->optimizeContributionAllocation($surplus, $demands)
  │
  ├── cashFlowCoordinator->identifyCashFlowShortfalls($allocation)
  │
  └── crossModuleStrategyService->generateCrossModuleStrategies($allAnalysis, $user)
```

### 7.3 AI Chat Loop

The AI chat uses a **tool-call loop** that continues as long as Claude requests tool calls:

```
sendMessage($user, $conversation, $message)
  │
  ├── Save user message to DB
  ├── Check token budget
  ├── Build system prompt (with cached financial summary)
  ├── Build message history (last 20 messages)
  ├── Classify complexity → select model
  ├── Get tool definitions (filtered by preview mode)
  │
  ├── WHILE TRUE:  ← Tool call loop
  │     ├── Stream from Anthropic API
  │     │     ├── Yield text deltas (real-time SSE)
  │     │     ├── Accumulate tool_use blocks
  │     │     └── Track stop_reason
  │     │
  │     ├── Handle streaming errors → yield error + return
  │     │
  │     ├── IF has tool calls:
  │     │     ├── Add assistant message (with tool blocks) to history
  │     │     ├── FOR EACH tool call:
  │     │     │     ├── Yield 'tool_use' status: running
  │     │     │     ├── AiToolExecutor->execute(name, args, user)
  │     │     │     ├── Handle navigation results → yield
  │     │     │     ├── Handle entity creation results → yield
  │     │     │     ├── Collect tool_result block
  │     │     │     └── Yield 'tool_use' status: complete
  │     │     └── Add all tool_results as user message to history
  │     │
  │     ├── IF tool calls AND stop_reason == 'tool_use' AND count < 5:
  │     │     └── CONTINUE (next iteration)
  │     └── ELSE: BREAK
  │
  ├── Save assistant message (content + metadata)
  ├── Update conversation token usage
  ├── Invalidate daily usage cache
  └── Yield 'done' event
```

### 7.4 Cache Invalidation Loop (After CRUD)

When a user creates, updates, or deletes financial data, the system triggers cache invalidation:

```
User action (e.g., create savings account)
  │
  ├── Controller saves to DB
  ├── Agent->invalidateUserCache($userId)
  │     ├── Cache::tags([agent, user])->flush()  (if tagged store)
  │     └── Cache::forget() for each known key suffix
  ├── Cache::forget("module_analysis_{userId}")
  ├── IF joint account:
  │     ├── Agent->invalidateUserCache($spouseId)
  │     └── netWorthService->invalidateCache($spouseId)
  ├── Model observers fire:
  │     ├── RiskRecalculationObserver → debounce → RecalculateRiskProfileJob
  │     └── GoalTrackingObserver → update goal contribution tracking
  └── Frontend: Vuex dispatch('analyseModule') → re-fetches fresh data
```

---

## 8. AI Chat: Replies & Interactions

### 8.1 Conversation Model

Conversations are persisted in two tables:

- `ai_conversations` — metadata: user_id, title, model_used, total_input_tokens, total_output_tokens, message_count
- `ai_messages` — content: conversation_id, role (user/assistant), content, input_tokens, output_tokens, model_used, metadata (JSON)

### 8.2 Message History Construction

The last 20 messages are loaded in chronological order. For assistant messages that included tool calls, the metadata is re-injected as context:

```php
// R14: Append tool call context from metadata
if ($msg->role === 'assistant' && !empty($msg->metadata['tool_calls'])) {
    $toolContext = $this->buildToolCallContext($msg->metadata['tool_calls']);
    $content .= "\n\n" . $toolContext;
}
```

This ensures Claude maintains awareness of prior tool calls even when the actual tool_result blocks are no longer in the message array.

### 8.3 SSE Event Types

The streaming response yields these event types to the frontend:

| Event Type | Payload | Description |
|------------|---------|-------------|
| `content` | `{ text }` | Real-time text delta (streamed word-by-word) |
| `tool_use` | `{ tool, status }` | Tool call started/completed (UI shows spinner) |
| `navigation` | `{ route_path, description }` | Frontend should navigate to this route |
| `entity_created` | `{ entity_type, entity_id, name }` | Record created by tool |
| `title` | `{ title }` | Conversation title (first message only) |
| `error` | `{ message }` | User-friendly error message |
| `done` | `{ message_id, input_tokens, output_tokens }` | Stream complete |

### 8.4 Error Categorisation

API errors are translated to user-friendly messages:

| HTTP Status / Error Type | User Message |
|-------------------------|-------------|
| 429 (rate limit) | "You've sent several messages quickly. Please wait a moment..." |
| 529 (overloaded) | "The service is temporarily busy..." |
| 401/403 (auth) | "Configuration issue — please contact support." |
| context_length / token errors | "This conversation has become quite long. Starting a new conversation may help." |
| overloaded / capacity | "The service is temporarily busy..." |
| Default | "I apologise, but I encountered an issue processing your request." |

---

## 9. Tool Execution Framework

### 9.1 Tool Definitions

**File:** `app/Services/AI/AiToolDefinitions.php`

17 tools across 5 categories:

| Category | Tools | Preview Mode |
|----------|-------|-------------|
| **Navigation** | `navigate_to_page` | Available |
| **Analysis** | `get_module_analysis`, `run_what_if_scenario`, `get_recommendations` | Available |
| **Tax** | `get_tax_information` | Available |
| **Plan Generation** | `generate_financial_plan` | Available |
| **Data Creation** | `create_goal`, `create_life_event`, `create_savings_account`, `create_investment_account`, `create_pension`, `create_property`, `create_mortgage`, `create_protection_policy`, `create_estate_asset`, `create_estate_liability`, `create_estate_gift` | **Blocked in preview** |

All tools use `strict: true` mode for reliable structured outputs.

### 9.2 Tool Executor

**File:** `app/Services/AI/AiToolExecutor.php`

The executor receives tool calls from the AI loop and dispatches to the appropriate agent or service:

```php
public function execute(string $toolName, array $args, User $user): array
{
    return match ($toolName) {
        'navigate_to_page'       => $this->handleNavigation($args),
        'get_module_analysis'    => $this->handleModuleAnalysis($args, $user),
        'run_what_if_scenario'   => $this->handleWhatIfScenario($args, $user),
        'get_recommendations'    => $this->handleRecommendations($user),
        'get_tax_information'    => $this->handleTaxInformation($args),
        'generate_financial_plan'=> $this->handleFinancialPlan($user),
        'create_goal'            => $this->handleCreateGoal($args, $user),
        'create_savings_account' => $this->handleCreateSavingsAccount($args, $user),
        // ... all 17 tools
    };
}
```

**Module analysis dispatch:**

```php
private function handleModuleAnalysis(array $args, User $user): array
{
    $module = $args['module'];
    return match ($module) {
        'protection' => $this->protectionAgent->analyze($user->id),
        'savings'    => $this->savingsAgent->analyze($user->id),
        'investment' => $this->investmentAgent->analyze($user->id),
        'retirement' => $this->retirementAgent->analyze($user->id),
        'estate'     => $this->estateAgent->analyze($user->id),
        'goals'      => $this->goalsAgent->analyze($user->id),
        'holistic'   => $this->coordinatingAgent->orchestrateAnalysis($user->id),
    };
}
```

### 9.3 Tool Call Metadata (R14)

After tool execution, summaries are stored in the assistant message metadata:

```php
$toolCallsSummary[] = [
    'tool' => $functionName,
    'input' => $this->summariseToolInput($functionArgs),    // Top 5 params, truncated
    'result_summary' => $this->summariseToolResult($toolResult), // Top 5 result keys
];
```

This enables conversation continuity — when history is rebuilt, prior tool call results are re-injected as context annotations.

---

## 10. Structured Data Formats

### 10.1 Agent Response Format

All agents use `BaseAgent::response()`:

```json
{
    "success": true,
    "message": "Analysis completed",
    "data": { /* module-specific structured data */ },
    "timestamp": "2026-03-16T14:30:00+00:00"
}
```

### 10.2 Orchestrated Analysis Response

The CoordinatingAgent returns:

```json
{
    "user_id": 123,
    "analysis_date": "2026-03-16T14:30:00+00:00",
    "module_analysis": {
        "protection": { "adequacy_score": 65, "coverage_gap": 85000, "recommendations": [], "full_analysis": {} },
        "savings": { "total_savings": 18500, "emergency_fund_months": 3.2, "recommendations": [], "full_analysis": {} },
        "investment": { "total_portfolio_value": 45000, "diversification_score": 72, "recommendations": [], "full_analysis": {} },
        "retirement": { "total_pension_value": 125000, "projected_annual_income": 18200, "target_income": 25000, "income_gap": 6800, "recommendations": [] },
        "estate": { "net_worth": 450000, "iht_liability": 42000, "monthly_income": 4200, "monthly_expenses": 3100, "recommendations": [] },
        "goals": { "has_goals": true, "recommendations": [] },
        "tax_optimisation": { "strategies": [], "total_estimated_saving": 1200, "recommendations": [] }
    },
    "available_surplus": 1100,
    "conflicts": [ /* identified conflicts */ ],
    "ranked_recommendations": [ /* priority-scored and sorted */ ],
    "cashflow_allocation": { "total_demand": 850, "allocation": {}, "shortfall": 0 },
    "shortfall_analysis": { "has_shortfall": false },
    "cross_module_strategies": [],
    "summary": {
        "total_recommendations": 12,
        "conflicts_identified": 1,
        "total_monthly_demand": 850,
        "cashflow_surplus": 1100,
        "has_shortfall": false,
        "cross_module_strategies_count": 2
    }
}
```

### 10.3 Recommendation Format

```json
{
    "title": "Increase your life cover",
    "description": "Your cover falls short by £85,000 based on income and dependants",
    "category": "Increase_cover",
    "priority": 1,
    "module": "protection",
    "priority_score": 78.5,
    "urgency_score": 85,
    "impact_score": 70,
    "ease_score": 60,
    "user_priority_score": 80,
    "timeline": "immediate",
    "decision_trace": [
        { "check": "dependants_count", "value": 2, "passed": true },
        { "check": "coverage_gap", "value": 85000, "threshold": 10000, "passed": true }
    ],
    "recommended_monthly_premium": 45.00,
    "estimated_saving": null,
    "what_if_impact_type": "increase_cover_amount"
}
```

### 10.4 Conflict Format

```json
{
    "type": "cashflow_conflict",
    "total_demand": 1500,
    "available_surplus": 1100,
    "shortfall": 400,
    "demands": {
        "emergency_fund": 300,
        "protection": 150,
        "pension": 500,
        "investment": 350,
        "goals": 200
    },
    "severity": "medium"
}
```

### 10.5 Action Plan Format

```json
{
    "action_plan": {
        "immediate": [ /* urgency >= 80, do within 1 month */ ],
        "short_term": [ /* urgency 60-79, do within 3 months */ ],
        "medium_term": [ /* urgency 40-59, do within 12 months */ ],
        "long_term": [ /* urgency < 40, 12+ months */ ]
    },
    "summary": {
        "immediate_actions": 3,
        "short_term_actions": 4,
        "medium_term_actions": 3,
        "long_term_actions": 2,
        "total_actions": 12
    }
}
```

---

## 11. Caching Architecture

### 11.1 Multi-Layer Cache Strategy

| Layer | TTL | Key Pattern | Invalidation |
|-------|-----|------------|--------------|
| **Agent analysis** | 3600s (1 hour) | `v1_{agent}_{userId}_analysis` | Manual via `invalidateUserCache()` |
| **Agent recommendations** | 3600s | `v1_{agent}_{userId}_recommendations` | Manual via `invalidateUserCache()` |
| **Agent scenarios** | 3600s | `v1_{agent}_{userId}_scenarios` | Manual via `invalidateUserCache()` |
| **Agent summary** | 3600s | `v1_{agent}_{userId}_summary` | Manual via `invalidateUserCache()` |
| **Agent projection** | 3600s | `v1_{agent}_{userId}_projection` | Manual via `invalidateUserCache()` |
| **Savings (custom)** | 1800s (30 min) | Per `PlanConfigService` | Module CRUD |
| **Monte Carlo** | 86400s (24 hours) | `monte_carlo_results_{jobId}` | Job re-dispatch |
| **AI context summary** | 120s (2 min) | `ai_context_summary_{userId}` | Time-based expiry |
| **AI daily token usage** | 300s (5 min) | `ai_daily_tokens_{userId}_{date}` | After each message |
| **Controller-level** | Varies | `{module}_analysis_{userId}` | After CRUD |
| **Risk recalculation debounce** | 5s | Observer-based | Automatic |
| **Holistic analysis** | Varies | `holistic_analysis_{userId}` | Re-analysis |
| **Holistic plan** | Varies | `holistic_plan_{userId}` | Re-planning |

### 11.2 Tag-Aware Caching

```php
protected function remember(string $key, callable $callback, ?int $ttl = null, array $tags = []): mixed
{
    // Redis/Memcached: use tagged caching for efficient group invalidation
    if (!empty($tags) && $this->cacheStoreSupportsTagging()) {
        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }
    // File/database: fall back to key-based caching
    return Cache::remember($key, $ttl, $callback);
}
```

**Tags used:** `[$agentName, 'user_' . $userId]`

### 11.3 Invalidation Triggers

1. **After every CRUD operation** — controller calls `agent->invalidateUserCache()`
2. **Joint account invalidation** — both primary and spouse caches cleared
3. **Multi-user invalidation** — `invalidateCacheForUsers([$userId, $spouseId])`
4. **Observer-triggered** — model changes fire observers that dispatch cache-clearing jobs
5. **Time-based expiry** — natural TTL expiration

---

## 12. Prompt Engineering & Optimisation

### 12.1 System Prompt Structure

The AI chat system prompt is ~176+ lines, built dynamically by `AiContextBuilder`:

```
<identity>        — Role definition (financial planning assistant)
<instructions>    — British English, no acronyms, GBP formatting, data-specific responses
<regulatory_compliance> — 6 FCA compliance rules
<user_profile>    — Dynamic: name, age, income, tax band, expenditure, retirement date, children
<financial_summary> — Dynamic: from CoordinatingAgent (cached 2 min)
<current_context> — Conditional: page-specific guidance (14 routes)
<scope>           — Financial planning only
<response_format> — Concise, bold figures, numbered lists, follow-up questions
<personality>     — Warm, encouraging, honest, plain language
<examples>        — 5 worked conversation examples
<available_actions> — Tool usage guidance
<preview_mode>    — Conditional: demo user restrictions
<data_creation_guidance> — Conditional: record creation patterns
```

### 12.2 Prompt Optimisations

1. **Ephemeral caching:** `cache_control: { type: 'ephemeral' }` — system prompt cached across conversation turns, reducing redundant input tokens by 70-80% after the first message
2. **Cached financial summary:** `AiContextBuilder::buildFinancialSummary()` caches for 120 seconds — prevents expensive `orchestrateAnalysis()` on every message
3. **History window:** Limited to 20 messages — prevents context overflow
4. **Complexity-based model selection:** Simple queries use cheaper Haiku; complex queries route to Sonnet (Pro users only)
5. **Max token budgeting:** Standard users get 4096 max response tokens; Pro users get 8192
6. **Tool call summarisation:** Tool results are summarised (top 5 keys, truncated strings) and stored in message metadata — full results are not persisted, reducing history token cost
7. **No duplicate prompts:** Template variables strip unused placeholders rather than leaving them in

### 12.3 Template System (Action Definitions)

Database-stored templates avoid hardcoded recommendation text:

```php
// Title template with variables
"Increase your life cover to close the {gap} gap"

// Rendering
$definition->renderTitle(['gap' => '£85,000']);
// → "Increase your life cover to close the £85,000 gap"

// Cleanup of unused placeholders
preg_replace('/\{[a-z_]+\}/', '', $rendered);
```

---

## 13. Conflict Resolution & Cross-Module Coordination

### 13.1 Conflict Types

**File:** `app/Services/Coordination/ConflictResolver.php`

| Conflict Type | Trigger | Description |
|--------------|---------|-------------|
| `cashflow_conflict` | Total demands > available surplus | Multiple modules need monthly contributions but surplus is insufficient |
| `isa_allowance_conflict` | Cash ISA + S&S ISA demands > £20,000 | Competing demand for limited ISA allowance |
| `protection_vs_savings_conflict` | Both modules have low adequacy AND competing demands | Protection premiums compete with emergency fund contributions |
| `estate_vs_goals_conflict` | Estate + goals demands > surplus | Gifting strategies compete with goal contributions |

### 13.2 Severity Calculation

```php
$ratio = $demand / $available;

if ($ratio >= 2.0) return 'critical';   // Demand is 2x+ available
if ($ratio >= 1.5) return 'high';       // Demand is 1.5-2x available
if ($ratio >= 1.2) return 'medium';     // Demand is 1.2-1.5x available
return 'low';                            // Demand < 1.2x available
```

### 13.3 Resolution Strategies

**Contribution conflicts** — strict priority ordering:

```
Priority 1: Emergency fund
Priority 2: Protection
Priority 3: Pension
Priority 4: Investment
Priority 5: Estate
Priority 6: Goals
```

Surplus is allocated top-down: each priority level is fully funded before the next. If surplus runs out mid-priority, remaining categories get £0.

**Protection vs. savings** — adequacy-based allocation:

| Scenario | Protection | Savings | Reasoning |
|----------|-----------|---------|-----------|
| Both critical (< 50) | 60% | 40% | Protection addresses catastrophic risk |
| Protection worse | 80% | 20% | Focus on larger gap |
| Savings worse | 20% | 80% | Focus on larger gap |

**ISA allocation** — context-sensitive:

| Scenario | Cash ISA | S&S ISA | Logic |
|----------|---------|---------|-------|
| Emergency fund critical | 100% | remainder | Liquidity first |
| Low risk tolerance | 70% | 30% | Stability preference |
| High growth goals + high risk | 10% | 90% | Growth maximisation |
| Balanced | Proportional | Proportional | Based on relative demands |

---

## 14. Priority Ranking Algorithm

**File:** `app/Services/Coordination/PriorityRanker.php`

### 14.1 Scoring Formula

```
Total Score = (Urgency × 0.4) + (Impact × 0.3) + (Ease × 0.2) + (User Priority × 0.1)
```

All scores are 0-100. Higher is more important.

### 14.2 Urgency Scoring (Weight: 40%)

| Module | Condition | Score |
|--------|-----------|-------|
| Protection | Coverage gap > £100k | 95 |
| Protection | Adequacy < 30 | 90 |
| Protection | Adequacy < 50 | 75 |
| Savings | Emergency fund < 1 month | 95 |
| Savings | Emergency fund < 3 months | 85 |
| Savings | Emergency fund < 6 months | 65 |
| Retirement | Income gap > £15k/yr | 80 |
| Retirement | < 10 years to retirement | +20 boost |
| Investment | Goal probability < 30% | 75 |
| Investment | < 3 years to goal | +25 boost |
| Estate | IHT > £500k | 85 |
| Estate | Age > 70 | +15 boost |
| Goals | Safety Net category | 75 |
| Goals | Progress category | 65 |

### 14.3 Impact Scoring (Weight: 30%)

Based on financial magnitude — larger gaps/shortfalls score higher:

- Protection: coverage gap £500k+ → 95, £100k+ → 70
- Savings: emergency fund shortfall £20k+ → 90, £5k+ → 60
- Retirement: income gap £30k+ → 95, £5k+ → 65
- Investment: expected benefit £50k+ → 90, £10k+ → 60
- Estate: IHT saving £200k+ → 95, £50k+ → 70

### 14.4 Ease Scoring (Weight: 20%)

Higher score = easier to implement:

- No cost actions: 90
- Monthly cost < £50: 80
- Monthly cost £200-500: 45
- Workplace pension change: 75 (easy)
- Trust setup: 30 (complex)
- Opening savings account: 70+ (easy)

### 14.5 Timeline Assignment

| Urgency Score | Timeline | Timeframe |
|---------------|----------|-----------|
| >= 80 | Immediate | Within 1 month |
| 60-79 | Short term | Within 3 months |
| 40-59 | Medium term | Within 12 months |
| < 40 | Long term | 12+ months |

---

## 15. Document Extraction (Claude Vision)

**File:** `app/Services/Documents/AIExtractionService.php`

### 15.1 Architecture

Uses Claude Vision API for document parsing — completely separate from the chat integration:

- **Model:** `claude-3-5-haiku-20241022`
- **Max tokens:** 4,096
- **Timeout:** 120 seconds
- **Supports:** Images (JPEG, PNG) and PDFs (text extraction + vision fallback)

### 15.2 Document Types

| Type | Extracted Fields |
|------|-----------------|
| Pension (DC) | scheme_name, provider, fund_value, employee_contribution, employer_contribution, fund_name, AMC |
| Pension (DB) | scheme_name, accrued_annual_pension, normal_retirement_age, pensionable_service_years, accrual_rate |
| Insurance (Life/CI) | policy_type, provider, sum_assured, premium_amount, premium_frequency, term_years, in_trust |
| Insurance (Income Protection) | provider, benefit_amount, deferred_period, premium_amount |
| Investment | account_type, provider, current_value, fund_name, platform_fee |
| Mortgage | lender, outstanding_balance, interest_rate, rate_type, monthly_payment, remaining_term |
| Savings | account_type, provider, balance, interest_rate, is_isa |

### 15.3 Processing Flow

```
Document uploaded → TYPE_UNKNOWN
  │
  ├── Build extraction prompt (type-specific if known)
  ├── IF PDF:
  │     ├── Try text extraction (PdfParser)
  │     │     ├── Has text? → Send as text content to Claude
  │     │     └── No text (scanned)? → Check size < 15MB → Send as base64 image
  │     └── Fall back to image processing
  ├── ELSE (image):
  │     └── Resize → Base64 encode → Claude Vision API
  │
  ├── Parse JSON response
  ├── Auto-detect document type if unknown
  ├── Create DocumentExtraction record
  ├── Log extraction to audit trail
  └── Update document status
```

### 15.4 Confidence Scoring

Extraction results include confidence scores per field, enabling the UI to flag low-confidence values for manual review.

---

## 16. Intent Matching & Routing

**File:** `app/Services/AI/AiIntentMatcher.php`

A keyword-based pre-processing layer that classifies user messages before Claude processes them:

### 16.1 Intent Types

| Intent | Trigger Keywords | Action |
|--------|-----------------|--------|
| `greeting` | hello, hi, hey, good morning | Warm greeting response |
| `help` | help, what can you do, capabilities | Feature overview |
| `navigation` | take me to, show me, go to + route keywords | Frontend navigation |
| `financial_plan` | financial plan, holistic plan, comprehensive | Generate plan |
| `recommendations` | what should I do, priorities, next steps | Fetch recommendations |
| `what_if` | what if, scenario, hypothetically | Run scenario |
| `tax_info` | income tax, capital gains, ISA allowance | Tax lookup |
| `create_blocked` | create a, add a, set up | Preview mode blocking |
| `module_analysis` | Module-specific keywords | Fetch analysis |
| `net_worth` | net worth, how much do I have, balance | Net worth overview |
| `unknown` | No match | Full Claude processing |

### 16.2 Navigation Routes

58 keyword-to-route mappings covering all application pages:

```php
'dashboard'         => '/dashboard',
'savings'           => '/net-worth/cash',
'investments'       => '/net-worth/investments',
'pensions'          => '/net-worth/retirement',
'protection'        => '/protection',
'estate planning'   => '/estate',
'goals'             => '/goals',
'holistic plan'     => '/holistic-plan',
'risk profile'      => '/risk-profile',
// ... 49 more
```

---

## 17. Streaming & Server-Sent Events

### 17.1 Backend SSE Implementation

**File:** `app/Http/Controllers/Api/AiChatController.php`

```php
public function sendMessage(Request $request, int $id): StreamedResponse
{
    return new StreamedResponse(function () use ($user, $conversation, $message) {
        $generator = $this->chatService->sendMessage($user, $conversation, $message);
        foreach ($generator as $event) {
            echo 'data: ' . json_encode($event) . "\n\n";
            ob_flush();
            flush();
        }
    }, 200, [
        'Content-Type'      => 'text/event-stream',
        'Cache-Control'     => 'no-cache',
        'Connection'        => 'keep-alive',
        'X-Accel-Buffering' => 'no',  // Prevent nginx buffering
    ]);
}
```

### 17.2 Raw SSE Parsing

The `streamAnthropicApi()` method reads the HTTP response body in 8KB chunks and parses SSE events delimited by `\n\n`:

```php
while (!$body->eof()) {
    $chunk = $body->read(8192);
    $buffer .= $chunk;

    while (($pos = strpos($buffer, "\n\n")) !== false) {
        $rawEvent = substr($buffer, 0, $pos);
        $buffer = substr($buffer, $pos + 2);
        $parsed = $this->parseSseEvent($rawEvent);
        if ($parsed !== null) yield $parsed;
    }
}
```

### 17.3 Frontend Consumption

- **Web:** `fetch()` with `text/event-stream` handling
- **Mobile (Capacitor):** `fetch()` with `credentials: 'omit'` and fallback for null `response.body` (WKWebView limitation)
- **Agent analysis endpoints:** No streaming — standard JSON responses

---

## 18. Error Handling & Resilience

### 18.1 Agent-Level Error Handling

**Graceful degradation** via `safeModuleAnalysis()`:

```php
private function safeModuleAnalysis(string $module, callable $analyzer, callable $defaultProvider): array
{
    try {
        return $analyzer();
    } catch (\Exception $e) {
        Log::error("{$module} analysis failed: " . $e->getMessage());
        return $defaultProvider(); // Sensible defaults
    }
}
```

A single module failure never cascades to other modules.

### 18.2 Controller-Level Error Handling

`SanitizedErrorResponse` trait provides consistent error responses:

```php
$this->errorResponse($exception, 'Context message', 500, ['user_id' => $userId]);
// Production: sanitized message
// Debug: full exception details (file, line, trace)
```

### 18.3 AI Chat Error Handling

Three levels of error handling:

1. **HTTP status errors** (401, 403, 429, 529) — categorised to user-friendly messages
2. **Stream errors** (SSE `error` events) — caught and translated
3. **Connection errors** (timeout, network) — caught via Guzzle exceptions

### 18.4 Domain Exceptions

`FinancialCalculationException` with factory methods:

```php
FinancialCalculationException::missingData('field_name', ['user_id' => $id]);
FinancialCalculationException::taxConfigError('config_type', 'reason');
FinancialCalculationException::projectionError('reason', ['data' => $context]);
// ... 11 factory methods total
```

### 18.5 Audit Trail

`Auditable` trait auto-logs all model CRUD:

- Logs creation with new values
- Logs updates with old → new diffs
- Logs deletion with old values
- Excludes: timestamps, passwords, MFA secrets
- Skips: preview users, unit tests

### 18.6 Observer Debouncing

Risk recalculation observers use a 5-second cache window to batch rapid changes:

```
Save account → Observer fires → Check debounce cache
  ├── Cache empty? → Set debounce flag → Dispatch RecalculateRiskProfileJob
  └── Cache hit? → Skip (will be handled by existing job)
```

---

## 19. File Reference Map

### Core Agents
| File | Purpose |
|------|---------|
| `app/Agents/BaseAgent.php` | Abstract base — caching, response format, currency formatting |
| `app/Agents/ProtectionAgent.php` | Life, CI, IP analysis |
| `app/Agents/SavingsAgent.php` | Emergency fund, ISA, liquidity |
| `app/Agents/InvestmentAgent.php` | Portfolio, diversification, fees |
| `app/Agents/RetirementAgent.php` | Pension projections, AA, decumulation |
| `app/Agents/EstateAgent.php` | IHT, gifting, trusts |
| `app/Agents/GoalsAgent.php` | Goal tracking, affordability |
| `app/Agents/TaxOptimisationAgent.php` | Cross-module tax |
| `app/Agents/CoordinatingAgent.php` | Master orchestrator (591 lines) |

### AI Services
| File | Purpose |
|------|---------|
| `app/Services/AI/AiChatService.php` | Streaming chat with tool loop |
| `app/Services/AI/AiContextBuilder.php` | System prompt construction |
| `app/Services/AI/AiModelResolver.php` | Model selection, token budgeting |
| `app/Services/AI/AiToolDefinitions.php` | 17 tool schemas |
| `app/Services/AI/AiToolExecutor.php` | Tool dispatch and execution |
| `app/Services/AI/AiIntentMatcher.php` | Keyword-based intent routing |
| `app/Services/Documents/AIExtractionService.php` | Claude Vision document parsing |

### Coordination Services
| File | Purpose |
|------|---------|
| `app/Services/Coordination/ConflictResolver.php` | 4 conflict types, 3 resolution strategies |
| `app/Services/Coordination/PriorityRanker.php` | Weighted scoring (urgency/impact/ease/user) |
| `app/Services/Coordination/CashFlowCoordinator.php` | Surplus calculation, allocation |
| `app/Services/Coordination/HolisticPlanner.php` | Cross-module plan generation |
| `app/Services/Coordination/CrossModuleStrategyService.php` | Cross-module tax strategies |

### Action Definition Services
| File | Purpose |
|------|---------|
| `app/Services/Protection/ProtectionActionDefinitionService.php` | Protection triggers |
| `app/Services/Savings/SavingsActionDefinitionService.php` | Savings triggers |
| `app/Services/Investment/InvestmentActionDefinitionService.php` | Investment triggers |
| `app/Services/Retirement/RetirementActionDefinitionService.php` | Retirement triggers |
| `app/Services/Tax/TaxActionDefinitionService.php` | Tax triggers |

### Data Readiness Services
| File | Purpose |
|------|---------|
| `app/Services/Protection/ProtectionDataReadinessService.php` | Protection data validation |
| `app/Services/Savings/SavingsDataReadinessService.php` | Savings data validation |
| `app/Services/Retirement/RetirementDataReadinessService.php` | Retirement data validation |
| `app/Services/Estate/EstateDataReadinessService.php` | Estate data validation |
| `app/Services/Investment/Recommendation/DataReadinessService.php` | Investment data validation |

### Configuration & Documentation
| File | Purpose |
|------|---------|
| `config/services.php` | Anthropic API key, model config |
| `app/Constants/TaxDefaults.php` | Fallback tax values, cache TTLs |
| `app/Constants/ValidationLimits.php` | Input bounds |
| `investmentTree/investment-decision-tree.md` | Investment pipeline reference |

---

## 20. Architecture Diagrams

### 20.1 Full System Flow

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                            FRONTEND (Vue.js 3)                               │
│                                                                              │
│  ┌─────────────┐    ┌──────────────┐    ┌──────────────┐    ┌────────────┐  │
│  │  Vue        │    │  Vuex Store  │    │  API Service │    │  AI Chat   │  │
│  │  Components │◄──►│  Modules     │──►│  (Axios)     │    │  Component │  │
│  └─────────────┘    └──────────────┘    └──────┬───────┘    └─────┬──────┘  │
│                                                │                   │         │
└────────────────────────────────────────────────┼───────────────────┼─────────┘
                                                 │                   │
                                     ┌───────────┴───────────┐   SSE Stream
                                     │  Laravel API Routes   │      │
                                     │  (auth:sanctum)       │      │
                                     └───────────┬───────────┘      │
                                                 │                   │
                              ┌──────────────────┴──────────────────┴──────────┐
                              │              CONTROLLERS                        │
                              │                                                │
                              │  Module Controllers          AiChatController  │
                              │  (Protection, Savings,       (SSE streaming)   │
                              │   Investment, Retirement,         │            │
                              │   Estate, Goals, Tax)             │            │
                              └────────────┬──────────────────────┼────────────┘
                                           │                      │
                    ┌──────────────────────┼──────────────────────┼────────────┐
                    │                      │                      │            │
                    ▼                      ▼                      ▼            │
        ┌───────────────────┐  ┌────────────────────┐  ┌──────────────────┐   │
        │   MODULE AGENTS   │  │ COORDINATING AGENT │  │   AI SERVICES    │   │
        │                   │  │                    │  │                  │   │
        │ ProtectionAgent   │◄─┤ Orchestrates all 7 │  │ AiChatService    │   │
        │ SavingsAgent      │  │ module agents      │  │ AiContextBuilder │   │
        │ InvestmentAgent   │  │                    │  │ AiToolExecutor   │   │
        │ RetirementAgent   │  │ ConflictResolver   │  │ AiModelResolver  │   │
        │ EstateAgent       │  │ PriorityRanker     │──►AiToolDefinitions│   │
        │ GoalsAgent        │  │ CashFlowCoordinator│  │ AiIntentMatcher  │   │
        │ TaxOptAgent       │  │ HolisticPlanner    │  │                  │   │
        └────────┬──────────┘  └────────────────────┘  └────────┬─────────┘   │
                 │                                              │             │
                 ▼                                              │             │
        ┌───────────────────┐                                   │             │
        │  DOMAIN SERVICES  │                          ┌────────▼─────────┐   │
        │                   │                          │  Anthropic API   │   │
        │ ActionDefinition  │                          │  (Messages API)  │   │
        │   Services (5)    │                          │                  │   │
        │ DataReadiness     │                          │ claude-haiku-4-5 │   │
        │   Services (5)    │                          │ claude-sonnet-4-5│   │
        │ Calculator/       │                          └──────────────────┘   │
        │   Analyzer/       │                                                 │
        │   Projector       │                                                 │
        │   services (183)  │                                                 │
        └────────┬──────────┘                                                 │
                 │                                                            │
                 ▼                                                            │
        ┌───────────────────┐    ┌────────────────────┐                       │
        │    MODELS (78)    │    │  ACTION DEFINITIONS │                       │
        │                   │◄──►│  (5 DB tables)     │                       │
        │  Eloquent ORM     │    │                    │                       │
        │  Observers (11)   │    │  trigger_config    │                       │
        │  Auditable trait  │    │  title_template    │                       │
        │  Joint ownership  │    │  is_enabled toggle │                       │
        └────────┬──────────┘    └────────────────────┘                       │
                 │                                                            │
                 ▼                                                            │
        ┌───────────────────┐    ┌────────────────────┐                       │
        │  MySQL 8 (DB)     │    │  Laravel Cache     │                       │
        │                   │    │  (File/Redis)      │                       │
        │  78 tables        │    │  Tag-aware         │                       │
        │  TaxConfiguration │    │  TTL-based         │                       │
        │  ai_conversations │    │  User-scoped       │                       │
        │  ai_messages      │    │                    │                       │
        └───────────────────┘    └────────────────────┘                       │
                                                                              │
        ┌───────────────────┐                                                 │
        │  QUEUE (Jobs)     │                                                 │
        │                   │                                                 │
        │ RunMonteCarlo     │                                                 │
        │   Simulation      │                                                 │
        │ RecalculateRisk   │                                                 │
        │   ProfileJob      │                                                 │
        └───────────────────┘                                                 │
        ──────────────────────────────────────────────────────────────────────┘
```

### 20.2 Decision Engine Flow

```
User financial data (DB)
         │
         ▼
┌─────────────────────────┐
│  Data Readiness Gate    │──── can_proceed = false? ──► Early return
│  (blocking/warning/info)│                               (missing data guidance)
└────────────┬────────────┘
             │ can_proceed = true
             ▼
┌─────────────────────────┐
│  Agent.analyze()        │
│                         │
│  Domain calculations:   │
│  - Coverage gaps        │
│  - Projections          │
│  - Adequacy scoring     │
│  - Risk assessment      │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│  ActionDefinitionService│
│                         │
│  FOR EACH enabled       │
│  definition:            │
│  ├─ Evaluate trigger    │
│  ├─ Match condition     │
│  ├─ Calculate thresholds│
│  ├─ Build decision trace│
│  └─ Render templates    │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│  CoordinatingAgent      │
│                         │
│  ├─ Collect all         │
│  ├─ Identify conflicts  │
│  ├─ Resolve conflicts   │
│  ├─ Rank by priority    │
│  ├─ Allocate cashflow   │
│  └─ Generate strategies │
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│  Ranked Recommendations │
│  with decision traces,  │
│  timeline assignments,  │
│  and action plans       │
└─────────────────────────┘
```

---

*This document is a point-in-time snapshot of the Fynla agent system architecture as of 16 March 2026.*
