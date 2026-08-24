# AI System Map - Fynla (February 2026)

Complete mapping of all AI and AI-mimic logic, code, data flows, prompts, tools, database storage, and frontend integration.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [LLM Connection & Configuration](#2-llm-connection--configuration)
3. [Backend Services (8 Files)](#3-backend-services)
4. [System Prompt Construction](#4-system-prompt-construction)
5. [Tool System (Function Calling)](#5-tool-system)
6. [Tool Execution & Data Creation](#6-tool-execution--data-creation)
7. [Message Processing & Streaming](#7-message-processing--streaming)
8. [Persona AI Mimic System (Simulated Mode)](#8-persona-ai-mimic-system)
9. [Frontend Components & Services](#9-frontend-components--services)
10. [Database Schema & Storage](#10-database-schema--storage)
11. [Routes & API Endpoints](#11-routes--api-endpoints)
12. [What Is Stored vs Not Stored](#12-what-is-stored-vs-not-stored)
13. [File Reference Table](#13-file-reference-table)
14. [Critical Paths](#14-critical-paths)

---

## 1. System Overview

Fynla has a **dual-mode AI chat system**:

| Mode | Trigger | LLM Used | Tools | Write Operations |
|------|---------|----------|-------|------------------|
| **Real (Production)** | `is_preview_user = false` | OpenAI GPT-5 Mini via API | 18 tools (6 read + 12 write) | Allowed |
| **Simulated (Preview)** | `is_preview_user = true` | No API call - intent matching + canned templates | Read tools only (via AiToolExecutor) | Blocked |

**Architecture flow:**

```
User Input → AiChatPanel.vue → Vuex aiChat store → fetch() SSE stream
    → AiChatController → StreamedResponse
        → [Preview?] AiSimulatedService (intent match + templates + real agent data)
        → [Real?]    AiChatService (OpenAI API + tool loop)
    → SSE events streamed back → ReadableStream parsed → UI updated
```

**Key design decisions:**
- Streaming uses Server-Sent Events (SSE) via `StreamedResponse`, not WebSockets
- Frontend uses `fetch()` with `ReadableStream`, not axios (axios doesn't support streaming)
- Preview users get real financial analysis data injected into canned response templates
- All conversations and messages persist to database regardless of mode
- Tool calls max out at 5 per turn to prevent runaway loops

---

## 2. LLM Connection & Configuration

### Provider: OpenAI

| Setting | Value |
|---------|-------|
| API Endpoint | `https://api.openai.com/v1/chat/completions` |
| Authentication | Bearer token (`OPENAI_API_KEY`) |
| Request Timeout | 180 seconds |
| Streaming | Disabled (batch mode for tool call handling) |

### Model Selection

**File:** `app/Services/AI/AiModelResolver.php`

```php
private const MODEL_PRO = 'gpt-5-mini-2025-08-07';
private const MODEL_STANDARD = 'gpt-5-mini-2025-08-07';

public function getModel(User $user): string
{
    $plan = $this->getUserPlan($user);
    return match ($plan) {
        'pro' => config('services.openai.chat_model_pro', self::MODEL_PRO),
        default => config('services.openai.chat_model_standard', self::MODEL_STANDARD),
    };
}

public function getMaxTokens(User $user): int
{
    return match ($plan) {
        'pro' => 4096,
        default => 2048,
    };
}
```

Both Pro and Standard currently use the same model. Token limits differ by tier.

### Environment Variables

| Variable | Purpose | Format |
|----------|---------|--------|
| `OPENAI_API_KEY` | API authentication (required for production) | `sk-...` |
| `OPENAI_CHAT_MODEL_PRO` | Model for Pro subscription users | Default: `gpt-5-mini-2025-08-07` |
| `OPENAI_CHAT_MODEL_STANDARD` | Model for Standard/Student users | Default: `gpt-5-mini-2025-08-07` |

### Config File

**File:** `config/services.php`

```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY', ''),
    'chat_model_pro' => env('OPENAI_CHAT_MODEL_PRO', 'gpt-5-mini-2025-08-07'),
    'chat_model_standard' => env('OPENAI_CHAT_MODEL_STANDARD', 'gpt-5-mini-2025-08-07'),
],
```

---

## 3. Backend Services

All 8 AI services live in `app/Services/AI/`. All use `declare(strict_types=1)` and `private readonly` constructor injection.

| # | File | Purpose |
|---|------|---------|
| 1 | `AiModelResolver.php` | Select model + max tokens based on user subscription tier |
| 2 | `AiContextBuilder.php` | Build the system prompt with user profile, financial data, page context |
| 3 | `AiIntentMatcher.php` | Keyword-based intent detection for simulated (preview) mode |
| 4 | `AiToolDefinitions.php` | Define 18 OpenAI-compatible tool schemas |
| 5 | `AiToolExecutor.php` | Execute tool calls, enforce preview-mode write blocking |
| 6 | `AiChatService.php` | Core orchestrator: OpenAI API calls, tool loop, SSE streaming |
| 7 | `AiSimulatedService.php` | Simulated AI for preview users: intent match + canned responses |
| 8 | `AiSimulatedResponseBuilder.php` | Build formatted response text from intent + real agent data |

### Dependency Graph

```
AiChatController
├── AiChatService (real mode)
│   ├── AiContextBuilder
│   │   └── CoordinatingAgent (for financial summary)
│   ├── AiModelResolver
│   ├── AiToolDefinitions
│   └── AiToolExecutor
│       ├── All 7 Agents (Protection, Savings, Investment, Retirement, Estate, Goals, Coordinating)
│       ├── TaxConfigService
│       └── NetWorthService
│
└── AiSimulatedService (preview mode)
    ├── AiIntentMatcher
    ├── AiSimulatedResponseBuilder
    └── AiToolExecutor (for read-only data fetching)
```

---

## 4. System Prompt Construction

**File:** `app/Services/AI/AiContextBuilder.php`

**Method:** `buildSystemPrompt(User $user, ?string $currentRoute = null): string`

The system prompt is assembled from 6 sections. Approximately 4,000 tokens reserved.

### Section 1: Identity & Rules

```
You are Fynla Assistant, a professional financial planning assistant built into
the Fynla application. You help users understand and improve their financial
position using their actual data.

## Rules
- Always use British English spelling (e.g. "personalised", "optimise", "analyse")
- Never use acronyms in responses - always spell out in full
- Format all currency values in GBP with commas (e.g. £1,250.00)
- You are not a regulated financial adviser - never give specific product recommendations
- Frame guidance as "you may want to consider" or "it could be worth exploring"
- Be concise and direct - avoid filler phrases
- When discussing the user's data, reference specific numbers from their profile
- If you do not have enough data to answer a question, say so honestly
```

### Section 2: User Profile (buildUserProfile)

Dynamically built from user model:

```
## User Profile
- Name: {user->name}
- Age: {calculated from date_of_birth}
- Employment: {employment_status}
- Marital status: {marital_status}
- Total annual income: £{sum of all income sources}
- Monthly expenditure: £{monthly_expenditure or annual_expenditure / 12}
- Target retirement date: {target_retirement_date}
- Children: {count of family members where relationship = 'child'}
```

**Income calculation:** Sums `employment_income + self_employment_income + rental_income + dividend_income + interest_income + other_income + trust_income` from user model.

### Section 3: Financial Summary (buildFinancialSummary)

Calls `CoordinatingAgent->orchestrateAnalysis($user->id)` to fetch holistic analysis, then extracts:

```
## Financial Summary
- Monthly surplus/shortfall: £{amount}
- Emergency fund: {months} months of cover
- Projected retirement income: £{amount} per year
- Estimated Inheritance Tax liability: £{amount}
- Top recommendations:
  1. {title}
  2. {title}
  3. {title}
```

### Section 4: Module Context (getModuleContext)

Route-specific context for 15+ key routes:

| Route Pattern | Context Added |
|---------------|---------------|
| `/dashboard` | "User is on their main dashboard viewing overall financial health" |
| `/net-worth/property` | "User is viewing their property portfolio" |
| `/net-worth/cash` | "User is viewing their savings and cash accounts" |
| `/net-worth/investments` | "User is viewing their investment portfolio" |
| `/net-worth/retirement` | "User is viewing their pension arrangements" |
| `/estate` | "User is viewing their estate plan" |
| `/goals` | "User is viewing their financial goals" |
| etc. | |

### Section 5: Preview Mode Warning

If `$user->is_preview_user`:

```
## Preview Mode
You are assisting a preview/demo user. You cannot create, update, or delete
any records. Focus on analysis, navigation, and educational guidance.
```

### Section 6: Available Actions

Lists all tools available to the model. Write tools hidden in preview mode:

```
## Available Actions
- You can navigate the user to relevant pages using the navigate_to_page tool
- You can fetch detailed analysis for any financial module using get_module_analysis
- You can run what-if scenarios using run_what_if_scenario
- You can look up current UK tax information using get_tax_information
- You can generate a comprehensive holistic financial plan using generate_financial_plan

[If not preview user, also includes:]
- You can create financial goals using create_goal
- You can create life events using create_life_event
- You can create savings accounts using create_savings_account
- You can create investment accounts using create_investment_account
- You can create pensions using create_pension
- You can create properties using create_property
- You can create standalone mortgages using create_mortgage
- You can create protection policies using create_protection_policy
- You can create estate planning assets using create_estate_asset
- You can create estate planning liabilities using create_estate_liability
- You can record gifts for Inheritance Tax planning using create_estate_gift

## Data Creation Guidance
When the user tells you about a financial product they hold, create it immediately
- Individual Savings Accounts must always have ownership_type 'individual'
- Default ownership to 'individual' unless the user specifically mentions joint ownership
- Set sensible defaults for fields the user does not mention
- After creating a record, briefly confirm what was created and suggest what to add next
- If the user mentions a property with a mortgage, use create_property with the
  outstanding_mortgage field to create both at once
```

---

## 5. Tool System

**File:** `app/Services/AI/AiToolDefinitions.php`

**Method:** `getTools(bool $isPreviewMode = false): array`

Returns array of OpenAI-compatible function schemas:

```php
[
    'type' => 'function',
    'function' => [
        'name' => 'tool_name',
        'description' => 'What the tool does',
        'parameters' => [
            'type' => 'object',
            'properties' => [ /* field definitions */ ],
            'required' => ['field1', 'field2']
        ]
    ]
]
```

### All 18 Tools

#### A. Navigation (1 tool, always available)

| Tool | Parameters | Description |
|------|-----------|-------------|
| `navigate_to_page` | `route_path` (string, required), `description` (string, required) | Navigate user to any app page |

#### B. Analysis (5 tools, always available)

| Tool | Parameters | Description |
|------|-----------|-------------|
| `get_module_analysis` | `module` (enum: protection, savings, investment, retirement, estate, goals, holistic) | Analyse any of 7 modules |
| `run_what_if_scenario` | `module` (enum: retirement, savings, investment, protection), `parameters` (object) | Run scenarios |
| `get_recommendations` | _(none)_ | Get ranked recommendations from CoordinatingAgent |
| `get_tax_information` | `topic` (enum: income_tax, capital_gains, inheritance_tax, isa_allowances, pension_allowances) | Current tax year config |
| `generate_financial_plan` | _(none)_ | Full holistic plan with executive summary, recommendations, allocation |

#### C. Data Creation (12 tools, hidden in preview mode)

| Tool | Key Parameters |
|------|---------------|
| `create_goal` | name, target_amount, target_date, priority, goal_type |
| `create_life_event` | event_type, event_date, description, estimated_cost |
| `create_savings_account` | account_name, account_type, institution, current_balance, interest_rate, is_isa, is_emergency_fund, regular_contribution_amount |
| `create_investment_account` | account_name, account_type, provider, current_value, monthly_contribution_amount, platform_fee_percent |
| `create_pension` | pension_category (dc/db), scheme_name, scheme_type, provider, current_fund_value, employee_contribution_percent, employer_contribution_percent, accrued_annual_pension, normal_retirement_age, pensionable_service_years |
| `create_property` | property_type, current_value, purchase_price, purchase_date, address_line_1, postcode, outstanding_mortgage, mortgage_rate, mortgage_lender, monthly_rental_income |
| `create_mortgage` | property_address_hint, lender_name, outstanding_balance, interest_rate, mortgage_type, rate_type, monthly_payment, remaining_term_months |
| `create_protection_policy` | policy_type (life_insurance, critical_illness, income_protection), provider, cover_amount, monthly_premium, policy_term_years, owner_relationship, start_date |
| `create_estate_asset` | asset_type, asset_name, current_value, ownership_type, description, location |
| `create_estate_liability` | liability_type, liability_name, outstanding_amount, interest_rate, owner_relationship, description |
| `create_estate_gift` | recipient_name, gift_amount, gift_date, relationship_to_user, description |

---

## 6. Tool Execution & Data Creation

**File:** `app/Services/AI/AiToolExecutor.php`

**Method:** `execute(string $toolName, array $input, User $user): array`

### Dependencies Injected

All 7 agents + TaxConfigService + NetWorthService:

```php
public function __construct(
    private readonly CoordinatingAgent $coordinatingAgent,
    private readonly ProtectionAgent $protectionAgent,
    private readonly SavingsAgent $savingsAgent,
    private readonly InvestmentAgent $investmentAgent,
    private readonly RetirementAgent $retirementAgent,
    private readonly EstateAgent $estateAgent,
    private readonly GoalsAgent $goalsAgent,
    private readonly TaxConfigService $taxConfig,
    private readonly NetWorthService $netWorthService,
)
```

### Execution Routing

```php
return match ($toolName) {
    'navigate_to_page' => $this->navigateToPage($input),
    'get_module_analysis' => $this->getModuleAnalysis($input, $user),
    'run_what_if_scenario' => $this->runWhatIfScenario($input, $user),
    'get_recommendations' => $this->getRecommendations($user),
    'get_tax_information' => $this->getTaxInformation($input),
    'generate_financial_plan' => $this->generateFinancialPlan($user),
    'create_goal' => $this->createGoal($input, $user, $isPreviewUser),
    'create_life_event' => $this->createLifeEvent($input, $user, $isPreviewUser),
    'create_savings_account' => $this->createSavingsAccount($input, $user, $isPreviewUser),
    'create_investment_account' => $this->createInvestmentAccount($input, $user, $isPreviewUser),
    'create_pension' => $this->createPension($input, $user, $isPreviewUser),
    'create_property' => $this->createProperty($input, $user, $isPreviewUser),
    'create_mortgage' => $this->createMortgage($input, $user, $isPreviewUser),
    'create_protection_policy' => $this->createProtectionPolicy($input, $user, $isPreviewUser),
    'create_estate_asset' => $this->createEstateAsset($input, $user, $isPreviewUser),
    'create_estate_liability' => $this->createEstateAssetLiability($input, $user, $isPreviewUser),
    'create_estate_gift' => $this->createEstateGift($input, $user, $isPreviewUser),
};
```

### Preview Mode Write Blocking

Every `create_*` method checks preview status first:

```php
private function createGoal(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return [
            'blocked' => true,
            'reason' => 'You are in preview mode. Goal creation is not available...'
        ];
    }
    // ... proceed with actual creation
}
```

### Analysis Tool Flow

Read tools call agents directly:

- `get_module_analysis('savings')` -> `$this->savingsAgent->analyze($user->id)`
- `get_module_analysis('holistic')` -> `$this->coordinatingAgent->orchestrateAnalysis($user->id)`
- `get_recommendations()` -> `$this->coordinatingAgent->orchestrateAnalysis($user->id)` then extract recommendations
- `get_tax_information('income_tax')` -> `$this->taxConfig->get('income_tax.*')`
- `run_what_if_scenario('retirement', $params)` -> `$this->retirementAgent->buildScenarios($user->id, $params)`

---

## 7. Message Processing & Streaming

### Real Mode (AiChatService)

**File:** `app/Services/AI/AiChatService.php`

**Method:** `sendMessage(User $user, AiConversation $conversation, string $message, ?string $currentRoute): Generator`

#### Step-by-step flow:

1. **Save user message** to database
2. **Build context:**
   - System prompt via `AiContextBuilder`
   - Message history (last 20 messages, sliding window)
   - Model + max tokens via `AiModelResolver`
   - Tool definitions via `AiToolDefinitions`
3. **Auto-generate conversation title** from first message (truncate to 80 chars)
4. **Call OpenAI API** (non-streaming, to handle tool calls in batch)
5. **Tool execution loop** (max 5 iterations):
   - If response has `finish_reason: 'tool_calls'`:
     - For each tool call: execute via `AiToolExecutor`, add result to history
     - Call OpenAI again with updated history
   - If response has `finish_reason: 'stop'`: break loop
6. **Save assistant message** with token counts
7. **Update conversation** token usage totals

#### SSE Event Types Yielded

| Event Type | Data | When |
|------------|------|------|
| `title` | `{ title }` | First message in conversation |
| `content` | `{ text }` | Full accumulated response text |
| `tool_use` | `{ tool, status: 'running'\|'complete' }` | Before/after each tool execution |
| `navigation` | `{ route_path, description }` | When `navigate_to_page` tool is called |
| `entity_created` | `{ entity_type, entity_id, name }` | When a `create_*` tool succeeds |
| `error` | `{ message }` | On any exception |
| `done` | `{ message_id, input_tokens, output_tokens }` | Response complete |

### Controller SSE Setup

**File:** `app/Http/Controllers/Api/AiChatController.php`

```php
return new StreamedResponse(function () use (...) {
    $generator = $user->is_preview_user
        ? $this->simulatedService->sendMessage(...)
        : $this->chatService->sendMessage(...);

    foreach ($generator as $event) {
        echo 'data: '.json_encode($event)."\n\n";
        if (ob_get_level() > 0) { ob_flush(); }
        flush();
    }
}, 200, [
    'Content-Type' => 'text/event-stream',
    'Cache-Control' => 'no-cache',
    'Connection' => 'keep-alive',
    'X-Accel-Buffering' => 'no',  // Prevents SiteGround proxy buffering
]);
```

### Message History Window

- Last 20 messages loaded from database
- Filtered to roles: `user`, `assistant` (tool results not re-loaded)
- Formatted as OpenAI message objects: `{ role, content }`

---

## 8. Persona AI Mimic System

The simulated mode provides AI-like responses for preview users without making any API calls.

### Entry Point

**File:** `app/Services/AI/AiSimulatedService.php`

**Decision point** (AiChatController):

```php
$generator = $user->is_preview_user
    ? $this->simulatedService->sendMessage(...)
    : $this->chatService->sendMessage(...);
```

### Intent Matching

**File:** `app/Services/AI/AiIntentMatcher.php`

**Method:** `match(string $message): array` returns `['intent' => string, 'params' => array]`

**Matching order** (first match wins):

| Priority | Intent | Detection Method |
|----------|--------|------------------|
| 1 | `greeting` | Exact/prefix match: "hello", "hi", "hey", etc. |
| 2 | `help` | Contains: "help", "what can you do", "how does this work" |
| 3 | `navigation` | Starts with: "take me to", "show me", "go to", "navigate to", "open" + fuzzy route match |
| 4 | `financial_plan` | Contains: "financial plan", "holistic plan", "generate a plan", "overall plan" |
| 5 | `recommendations` | Contains: "what should i do", "recommendations", "priorities", "next steps" |
| 6 | `what_if` | Contains: "what if", "what would happen", "scenario", "hypothetically" + module detection |
| 7 | `tax_info` | Matches against 5 tax topic keyword sets |
| 8 | `create_blocked` | Contains: "create a", "add a", "set up a", "open an account", "new account" |
| 9 | `module_analysis` | Contains any module keyword (protection, savings, investment, retirement, estate, goals) |
| 10 | `net_worth` | Contains: "net worth", "how much do i have", "total worth", "financial position" |
| 11 | `unknown` | Fallback |

### Navigation Route Mapping

```php
const NAVIGATION_ROUTES = [
    'dashboard' => '/dashboard',
    'savings' => '/net-worth/cash',
    'property' => '/net-worth/property',
    'investments' => '/net-worth/investments',
    'pensions' => '/net-worth/retirement',
    'estate' => '/estate',
    'goals' => '/goals',
    // ... 30+ keywords mapped to routes
];
```

### Module Keyword Mapping

```php
const MODULE_KEYWORDS = [
    'protection' => ['protection', 'cover', 'life insurance', ...],
    'savings' => ['savings', 'cash', 'emergency fund', ...],
    'investment' => ['investment', 'portfolio', 'stocks', ...],
    'retirement' => ['retirement', 'pension', 'annuity', ...],
    'estate' => ['estate', 'inheritance', 'will', 'trust', ...],
    'goals' => ['goals', 'life events', 'milestones', ...],
];
```

### Tax Keyword Mapping

```php
const TAX_KEYWORDS = [
    'income_tax' => ['income tax', 'personal allowance', 'tax bands', ...],
    'capital_gains' => ['capital gains', 'cgt', ...],
    'inheritance_tax' => ['inheritance tax', 'iht', 'nil rate band', ...],
    'isa_allowances' => ['isa allowance', 'isa limit', ...],
    'pension_allowances' => ['pension allowance', 'annual allowance', 'lifetime allowance', ...],
];
```

### Response Builder

**File:** `app/Services/AI/AiSimulatedResponseBuilder.php`

**Method:** `build(string $intent, array $params, array $agentData, User $user): array`

**Returns:** `['text' => string, 'navigation' => ?array]`

### Real Data Injection

For analysis-type intents, the simulated service fetches real data before building the response:

```php
private function fetchAgentData(string $intent, array $params, User $user): array
{
    return match ($intent) {
        'module_analysis' => $this->toolExecutor->execute('get_module_analysis', [...], $user),
        'recommendations' => $this->toolExecutor->execute('get_recommendations', [], $user),
        'financial_plan' => $this->toolExecutor->execute('generate_financial_plan', [], $user),
        'net_worth' => $this->fetchNetWorthData($user),
        'what_if' => $this->toolExecutor->execute('run_what_if_scenario', [...], $user),
    };
}
```

| Intent | Data Source | Real Data? |
|--------|-----------|------------|
| `greeting` | None | No - pure template |
| `help` | None | No - pure template |
| `navigation` | None | No - route only |
| `financial_plan` | `CoordinatingAgent->orchestrateAnalysis()` | Yes - real scores, recommendations, surplus |
| `recommendations` | `CoordinatingAgent->orchestrateAnalysis()` | Yes - real ranked recommendations |
| `module_analysis` | Respective module agent `->analyze()` | Yes - real module data |
| `what_if` | Respective agent `->buildScenarios()` | Yes - real scenario calculations |
| `tax_info` | `TaxConfigService` | Yes - real tax config |
| `net_worth` | `NetWorthService` + `CoordinatingAgent` | Yes - real totals |
| `create_blocked` | None | No - blocked message |
| `unknown` | None | No - fallback message |

### All Canned Response Templates

#### Greeting

```
Hello, {firstName}! I'm your financial planning assistant. I can help you with:

- **Analysing** your protection, savings, investments, pensions, estate, and goals
- **Navigating** to any section of your financial dashboard
- **Generating** a holistic financial plan with personalised recommendations
- **Exploring** tax allowances and rates for the current tax year
- **Running** what-if scenarios to see how changes affect your finances

What would you like to explore?
```

#### Help

Lists 6 capability categories with example prompts for each.

#### Navigation

```
Taking you to {description} now.
```

Plus `navigation` event emitted.

#### Financial Plan

```
Here's your holistic financial plan, {firstName}.

**Overall Financial Health Score: {score}/100**

{overview paragraph}

**Key Strengths**
- **{area}:** {description}
...

**Areas for Improvement**
- **{area}:** {description}
...

**Top Priorities**
1. {action}
...

**Available Monthly Surplus:** £{amount}

**Suggested Surplus Allocation**
- {category}: £{amount}/month
...

Would you like me to dive deeper into any of these areas?
```

#### Recommendations

```
Here are your prioritised recommendations, {firstName}.

**1. {title}** _{category}_
Impact: {impact}
{rationale}

... (up to 7 recommendations)

**Available Monthly Surplus:** £{amount}

Would you like me to explain any of these in more detail?
```

#### Module Analysis

Dynamically formats agent analysis output for the requested module, extracting metrics, key findings, and recommendations.

#### What-If Scenarios

```
Here are the scenario results for **{ModuleName}**.

**{Scenario Name}**
{description}
Impact: {impact}

Would you like to explore a different scenario?
```

#### Tax Information

```
Here are the current **{Topic}** rates for the **{TaxYear}** tax year.

{Formatted tax data - varies by topic}

Would you like to know about any other tax allowances or rates?
```

Formatted data includes bands, rates, thresholds, and allowances from TaxConfigService.

#### Create Blocked

```
I appreciate you wanting to create a {action}, but this isn't available in preview mode.
Preview mode lets you explore the app's features using sample data, but creating or
modifying data is restricted.

To create and save your own financial data, you can **sign up for a free account**.

In the meantime, I can still help you:
- Analyse the existing sample data
- Show you how the {action} feature works
- Navigate to the relevant section

What would you like to do instead?
```

#### Unknown (Fallback)

Generic response acknowledging the message and offering to help with analysis, navigation, or tax info.

### Streaming Simulation

**Method:** `chunkForStreaming(string $text): array`

Splits response into natural-looking chunks to simulate streaming:

1. Split on sentence boundaries (`.`, `!`, `?`)
2. Then split long sentences on clause boundaries (`,`, `;`, `:`, `-`)
3. Max 80 characters per chunk
4. Each chunk yielded with 15-40ms random delay

---

## 9. Frontend Components & Services

### API Service

**File:** `resources/js/services/aiChatService.js`

```javascript
const aiChatService = {
    getConversations()                              // GET /api/ai-chat/conversations
    createConversation(currentRoute)                 // POST /api/ai-chat/conversations
    getConversation(conversationId)                  // GET /api/ai-chat/conversations/{id}
    deleteConversation(conversationId)               // DELETE /api/ai-chat/conversations/{id}
    sendMessageStream(conversationId, message, currentRoute)
        // POST /api/ai-chat/conversations/{id}/messages
        // Uses fetch() NOT axios - returns response.body.getReader()
};
```

**Key:** `sendMessageStream` uses native `fetch()` because axios does not support `ReadableStream` for SSE parsing.

### Vuex Store Module

**File:** `resources/js/store/modules/aiChat.js`

**State:**

```javascript
{
    isOpen: false,              // Panel visibility
    conversations: [],          // List of past conversations
    currentConversation: null,  // Active conversation object
    messages: [],               // Messages in current conversation
    streaming: false,           // Currently receiving SSE stream
    streamingText: '',          // Accumulated text during streaming
    loading: false,             // API call in progress
    loadingConversations: false,// Loading conversation list
    error: null,                // Error message
    showHistory: false,         // History drawer open
    pendingNavigation: null,    // Route to navigate to (from tool)
}
```

**Key Actions:**

| Action | Description |
|--------|-------------|
| `toggle()` | Toggle panel, mutual exclusion with InfoGuide |
| `sendMessage({ message })` | Stream message via fetch + ReadableStream |
| `fetchConversations()` | Load conversation list |
| `startNewConversation()` | Create new conversation with current route |
| `loadConversation(id)` | Load conversation + message history |
| `deleteConversation(id)` | Soft-delete conversation |

**SSE Stream Parsing (in sendMessage):**

```javascript
const reader = await aiChatService.sendMessageStream(...)
const decoder = new TextDecoder()
let buffer = ''

while (true) {
    const { done, value } = await reader.read()
    if (done) break

    buffer += decoder.decode(value, { stream: true })
    const lines = buffer.split('\n\n')

    for (const line of lines.slice(0, -1)) {
        if (line.startsWith('data: ')) {
            const event = JSON.parse(line.slice(6))
            switch (event.type) {
                case 'content': commit('APPEND_STREAMING_TEXT', event.text); break
                case 'navigation': commit('SET_PENDING_NAVIGATION', event.route_path); break
                case 'entity_created': /* show success indicator */ break
                case 'done': /* finalize message */ break
            }
        }
    }
    buffer = lines[lines.length - 1]
}
```

### Vue Components

#### AiChatButton.vue

**File:** `resources/js/components/Shared/AiChatButton.vue`

- Position: `fixed bottom-6 right-24 z-40` (left of InfoGuide button)
- Hidden on public pages: `/login`, `/register`, `/forgot-password`, `/reset-password`, `/`
- Shows chat icon when closed, X icon when open
- Mutually exclusive with InfoGuide panel

#### AiChatPanel.vue

**File:** `resources/js/components/Shared/AiChatPanel.vue`

- Position: `fixed bottom-24 right-6 w-[420px] z-[70]`
- Teleported to `<body>`

**Sections:**

| Section | Content |
|---------|---------|
| **Header** | Title "Fynla Assistant", new conversation (+), history (clock), close (X) |
| **History Drawer** | Collapsible list of past conversations, delete on hover, active highlighted |
| **Messages Area** | User messages (right, blue bg), assistant messages (left, white bg), streaming dots |
| **Empty State** | 5 context-aware suggested prompts |
| **Input Area** | Auto-growing textarea, send button, Enter to submit, disabled during streaming |

#### AiMessageContent.vue

**File:** `resources/js/components/Shared/AiMessageContent.vue`

Handles rendering of different message types:

| Role | Rendering |
|------|-----------|
| `navigation` | Blue card with arrow icon, clickable |
| `entity_created` | Green card with checkmark icon |
| `user` / `assistant` | Markdown-rendered text via `formattedContent` |

**Markdown rendering** (safe subset, escaped HTML first):
- `**bold**` -> `<strong>`
- `*italic*` -> `<em>`
- `- item` -> `<ul><li>`
- `1. item` -> `<ol><li>`
- Currency values (`£X,XXX`) -> `<span class="font-semibold">`
- Line breaks preserved

---

## 10. Database Schema & Storage

### Tables

#### ai_conversations

**Migration:** `database/migrations/2026_02_27_200001_create_ai_conversations_table.php`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Auto-increment |
| `user_id` | bigint FK | References users.id, cascade on delete |
| `title` | varchar(255) null | Auto-generated from first message (max 80 chars) |
| `status` | enum('active', 'archived') | Default: 'active' |
| `model_used` | varchar(100) null | LLM model identifier (e.g. 'gpt-5-mini-2025-08-07') |
| `total_input_tokens` | int unsigned | Running total of input tokens, default 0 |
| `total_output_tokens` | int unsigned | Running total of output tokens, default 0 |
| `message_count` | int unsigned | Running count of messages, default 0 |
| `last_message_at` | timestamp null | Timestamp of most recent message |
| `metadata` | json null | Flexible metadata |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp null | Soft deletes |

**Indexes:** `(user_id, status, last_message_at)`

#### ai_messages

**Migration:** `database/migrations/2026_02_27_200002_create_ai_messages_table.php`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Auto-increment |
| `conversation_id` | bigint FK | References ai_conversations.id, cascade on delete |
| `role` | enum('user', 'assistant', 'system', 'tool_result') | Message sender |
| `content` | text | Message content |
| `tool_calls` | json null | Tool calls made by assistant |
| `tool_results` | json null | Results returned from tool execution |
| `input_tokens` | int unsigned null | Tokens used for this message's input |
| `output_tokens` | int unsigned null | Tokens used for this message's output |
| `model_used` | varchar(100) null | Model used for this response |
| `metadata` | json null | Flexible metadata |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:** `(conversation_id, created_at)`

#### users table addition

**Migration:** `database/migrations/2026_02_27_200003_add_ai_chat_enabled_to_users_table.php`

| Column | Type | Description |
|--------|------|-------------|
| `ai_chat_enabled` | boolean | Default true. Per-user feature flag. Added after `info_guide_enabled` |

### Models

**`app/Models/AiConversation.php`**

```php
class AiConversation extends Model {
    use SoftDeletes;

    public function user(): BelongsTo
    public function messages(): HasMany
    public function scopeActive(Builder $query): Builder
    public function scopeForUser(Builder $query, int $userId): Builder
    public function incrementTokenUsage(int $inputTokens, int $outputTokens): void
}
```

**`app/Models/AiMessage.php`**

```php
class AiMessage extends Model {
    public function conversation(): BelongsTo
}
```

---

## 11. Routes & API Endpoints

**File:** `routes/api.php` (lines 1031-1037)

```php
Route::middleware('auth:sanctum')->prefix('ai-chat')->group(function () {
    Route::get('/conversations', [AiChatController::class, 'index']);
    Route::post('/conversations', [AiChatController::class, 'create']);
    Route::get('/conversations/{id}', [AiChatController::class, 'show']);
    Route::delete('/conversations/{id}', [AiChatController::class, 'destroy']);
    Route::post('/conversations/{id}/messages', [AiChatController::class, 'sendMessage']);
});
```

| Method | Endpoint | Controller Method | Response |
|--------|----------|-------------------|----------|
| GET | `/api/ai-chat/conversations` | `index()` | JSON: array of conversations (max 50, active only) |
| POST | `/api/ai-chat/conversations` | `create()` | JSON: new conversation object |
| GET | `/api/ai-chat/conversations/{id}` | `show()` | JSON: conversation + messages (user/assistant only) |
| DELETE | `/api/ai-chat/conversations/{id}` | `destroy()` | JSON: `{ success: true }` (soft delete) |
| POST | `/api/ai-chat/conversations/{id}/messages` | `sendMessage()` | SSE stream (`text/event-stream`) |

**Authentication:** All routes require `auth:sanctum` middleware.

**Validation (sendMessage):**
- `message`: required, string, max 2000 characters
- `current_route`: optional, string

---

## 12. What Is Stored vs Not Stored

### Stored in Database

| Data | Table | Why |
|------|-------|-----|
| Every user message | `ai_messages` (role: user) | Conversation history, context window for future messages |
| Every assistant response | `ai_messages` (role: assistant) | Conversation history, displayed when loading past conversations |
| Tool call details | `ai_messages.tool_calls` (JSON) | Debugging, audit trail of what AI requested |
| Tool results | `ai_messages.tool_results` (JSON) | Debugging, audit trail of what data was returned |
| Token counts (per message) | `ai_messages.input_tokens`, `output_tokens` | Cost tracking, usage analytics |
| Token totals (per conversation) | `ai_conversations.total_input_tokens`, `total_output_tokens` | Quick cost overview without summing messages |
| Model used | `ai_conversations.model_used`, `ai_messages.model_used` | Audit trail, cost analysis across model changes |
| Conversation title | `ai_conversations.title` | UI display in conversation list |
| Conversation status | `ai_conversations.status` | Active vs archived filtering |
| Message count | `ai_conversations.message_count` | Quick count without counting related messages |
| Last message timestamp | `ai_conversations.last_message_at` | Sort conversations by recency |
| Per-user AI enabled flag | `users.ai_chat_enabled` | Per-user feature toggle |

### NOT Stored in Database

| Data | Why Not Stored |
|------|---------------|
| System prompt text | Regenerated dynamically each request from live user data. Storing it would be stale immediately and waste space. |
| Full OpenAI API request payload | Contains system prompt + full message history + tool definitions. Too large, redundant with stored messages. |
| Raw OpenAI API response | The meaningful parts (content, tool_calls, token counts) are extracted and stored. Raw response is transient. |
| Intermediate tool execution state | Tool results are stored in `tool_results` JSON, but the intermediate execution context (agent instances, service objects) is ephemeral. |
| Streaming chunk boundaries | The final complete text is stored, not individual SSE chunks. Chunking is a transport detail. |
| Simulated mode intent match details | The intent matched and params are not stored. Only the final response text is saved. Could be useful for analytics but currently not needed. |
| User's current route at time of message | Passed to `AiContextBuilder` for prompt context but not persisted. |
| Conversation metadata | `metadata` JSON column exists but is currently unused. Reserved for future use (e.g., user ratings, feedback). |

---

## 13. File Reference Table

### Backend (PHP)

| File | Path | Purpose |
|------|------|---------|
| AiChatController | `app/Http/Controllers/Api/AiChatController.php` | 5 REST endpoints, SSE streaming, mode selection |
| AiChatService | `app/Services/AI/AiChatService.php` | Core: OpenAI API calls, tool loop, message saving |
| AiSimulatedService | `app/Services/AI/AiSimulatedService.php` | Preview mode: intent match + templates + real data |
| AiSimulatedResponseBuilder | `app/Services/AI/AiSimulatedResponseBuilder.php` | Build canned response text from intent + agent data |
| AiIntentMatcher | `app/Services/AI/AiIntentMatcher.php` | Keyword-based intent detection (11 intents) |
| AiContextBuilder | `app/Services/AI/AiContextBuilder.php` | System prompt: identity, user profile, financial summary |
| AiModelResolver | `app/Services/AI/AiModelResolver.php` | Model + token selection by subscription tier |
| AiToolDefinitions | `app/Services/AI/AiToolDefinitions.php` | 18 OpenAI-compatible tool schemas |
| AiToolExecutor | `app/Services/AI/AiToolExecutor.php` | Execute tools, preview-mode write blocking |
| AiConversation | `app/Models/AiConversation.php` | Eloquent model with soft deletes, token tracking |
| AiMessage | `app/Models/AiMessage.php` | Eloquent model for conversation messages |
| Migration 1 | `database/migrations/2026_02_27_200001_create_ai_conversations_table.php` | Conversations table |
| Migration 2 | `database/migrations/2026_02_27_200002_create_ai_messages_table.php` | Messages table |
| Migration 3 | `database/migrations/2026_02_27_200003_add_ai_chat_enabled_to_users_table.php` | User preference |
| Config | `config/services.php` | OpenAI API key + model config |
| Routes | `routes/api.php` (lines 1031-1037) | 5 API endpoints under `/api/ai-chat/*` |

### Frontend (Vue/JS)

| File | Path | Purpose |
|------|------|---------|
| aiChatService | `resources/js/services/aiChatService.js` | API wrapper, fetch-based streaming |
| aiChat store | `resources/js/store/modules/aiChat.js` | Vuex: conversations, messages, streaming state |
| AiChatButton | `resources/js/components/Shared/AiChatButton.vue` | Floating toggle button |
| AiChatPanel | `resources/js/components/Shared/AiChatPanel.vue` | Chat panel: history, messages, input |
| AiMessageContent | `resources/js/components/Shared/AiMessageContent.vue` | Message rendering + markdown |

---

## 14. Critical Paths

### Production AI Response

```
1. User types message
2. AiChatPanel.vue → Vuex sendMessage action
3. fetch() POST /api/ai-chat/conversations/{id}/messages
4. AiChatController.sendMessage() checks is_preview_user → false
5. StreamedResponse wraps AiChatService generator
6. AiChatService:
   a. Save user message to ai_messages
   b. Build system prompt (AiContextBuilder)
   c. Load last 20 messages from database
   d. Resolve model + tokens (AiModelResolver)
   e. Get tool definitions (AiToolDefinitions)
   f. POST to OpenAI API
   g. If tool_calls in response:
      - Execute each tool (AiToolExecutor)
      - Add tool results to message history
      - POST to OpenAI again (repeat up to 5x)
   h. Save assistant message + tokens
   i. Update conversation totals
7. SSE events yielded → flushed as "data: {JSON}\n\n"
8. Frontend ReadableStream parses chunks
9. Vuex mutations update UI in real-time
```

### Preview AI Response

```
1. User types message
2. AiChatPanel.vue → Vuex sendMessage action
3. fetch() POST /api/ai-chat/conversations/{id}/messages
4. AiChatController.sendMessage() checks is_preview_user → true
5. StreamedResponse wraps AiSimulatedService generator
6. AiSimulatedService:
   a. Save user message to ai_messages
   b. AiIntentMatcher.match(message) → intent + params
   c. If analysis intent: AiToolExecutor fetches real agent data
   d. AiSimulatedResponseBuilder.build(intent, params, agentData, user)
   e. chunkForStreaming(text) → array of text chunks
   f. Yield each chunk with 15-40ms delay (simulates streaming)
   g. Save assistant message
7. SSE events yielded → flushed
8. Frontend ReadableStream parses chunks
9. Vuex mutations update UI (identical to production flow)
```

### Tool Execution

```
1. OpenAI response contains finish_reason: 'tool_calls'
2. For each tool_call:
   a. Yield { type: 'tool_use', tool: name, status: 'running' }
   b. AiToolExecutor.execute(name, args, user)
      - Check if write tool + preview user → return { blocked: true }
      - Route to appropriate handler method
      - Call agent/service directly
      - Return JSON-serializable result
   c. Add to message history: { role: 'tool', tool_call_id, content: JSON }
   d. Yield { type: 'tool_use', tool: name, status: 'complete' }
   e. If navigation tool: yield { type: 'navigation', route_path, description }
   f. If create tool: yield { type: 'entity_created', entity_type, entity_id, name }
3. Call OpenAI again with tool results in history
4. Repeat until finish_reason: 'stop' or 5 iterations reached
```
