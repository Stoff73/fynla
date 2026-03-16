# Full Reconciliation Report: AI Agent Upgrade

**Date:** 2026-03-16
**Audited by:** Claude against live database schema and model definitions
**Method:** Every field access, relationship call, model creation, route, and middleware verified against `Schema::getColumnListing()`, model `$fillable` arrays, and `User` relationship methods.

---

## 1. PrerequisiteGateService — Field Access Audit

### User model direct field access

| Code | Field | On `users` table? | Status |
|------|-------|--------------------|--------|
| `$user->date_of_birth` | `date_of_birth` | YES (cast: date) | PASS |
| `$user->retirement_date` | `retirement_date` | YES (cast: date) | PASS |
| `$user->employment_status` | `employment_status` | YES | PASS |
| `$user->marital_status` | `marital_status` | YES | PASS |
| `$user->monthly_expenditure` | `monthly_expenditure` | YES (cast: float) | PASS |
| `$user->annual_expenditure` | `annual_expenditure` | YES (cast: float) | PASS |
| `$user->annual_employment_income` | `annual_employment_income` | YES (cast: float) | PASS |
| `$user->annual_self_employment_income` | `annual_self_employment_income` | YES (cast: float) | PASS |
| `$user->annual_rental_income` | `annual_rental_income` | YES (cast: float) | PASS |
| `$user->annual_dividend_income` | `annual_dividend_income` | YES (cast: float) | PASS |
| `$user->annual_interest_income` | `annual_interest_income` | YES (cast: float) | PASS |
| `$user->annual_other_income` | `annual_other_income` | YES | PASS |
| `$user->annual_trust_income` | `annual_trust_income` | YES | PASS |
| `$user->is_preview_user` | `is_preview_user` | YES (cast: boolean) | PASS |
| `$user->name` | accessor (appended) | YES (accessor on first_name + surname) | PASS |

### User model relationship access

| Code | Method on User model | Return type | Status |
|------|---------------------|-------------|--------|
| `$user->retirementProfile` | `retirementProfile(): HasOne` (line 496) | RetirementProfile | PASS |
| `$user->familyMembers()` | `familyMembers(): HasMany` (line 352) | FamilyMember | PASS |
| `$user->dcPensions()` | `dcPensions(): HasMany` (line 472) | DCPension | PASS |
| `$user->dbPensions()` | `dbPensions(): HasMany` (line 480) | DBPension | PASS |
| `$user->investmentAccounts()` | `investmentAccounts(): HasMany` (line 464) | InvestmentAccount | PASS |
| `$user->savingsAccounts()` | `savingsAccounts(): HasMany` (line 536) | SavingsAccount | PASS |
| `$user->properties()` | `properties(): HasMany` (line 368) | Property | PASS |
| `$user->goals()` | `goals(): HasMany` (line 544) | Goal | PASS |
| `$user->subscription()` | `subscription(): HasOne` (line 159) | Subscription | PASS |

### Related model field access

| Code | Field | On table? | Status |
|------|-------|-----------|--------|
| `$user->retirementProfile->target_retirement_age` | `target_retirement_age` | YES on `retirement_profiles` (cast: integer) | PASS |
| `family_members.relationship` (WHERE clause) | `relationship` | YES on `family_members` | PASS |

### Query against `family_members` table

| Code | Column | Exists? | Status |
|------|--------|---------|--------|
| `->where('relationship', 'child')` | `relationship` | YES on `family_members` | PASS |

---

## 2. HasAiChat Trait — Field Access Audit

### buildUserProfile()

| Code | Field | Source | Status |
|------|-------|--------|--------|
| `$user->name` | accessor | User model appended attribute | PASS |
| `$user->date_of_birth` | `date_of_birth` | `users` table | PASS |
| `$user->date_of_birth->age` | Carbon `age` property | Carbon cast | PASS |
| `$user->employment_status` | `employment_status` | `users` table | PASS |
| `$user->marital_status` | `marital_status` | `users` table | PASS |
| `$user->retirement_date` | `retirement_date` | `users` table | PASS |
| `$user->retirement_date->format(...)` | Carbon `format` | Carbon cast | PASS |
| `$user->monthly_expenditure` | `monthly_expenditure` | `users` table | PASS |
| `$user->annual_expenditure` | `annual_expenditure` | `users` table | PASS |
| `$user->annual_employment_income` | `annual_employment_income` | `users` table | PASS |
| `$user->annual_self_employment_income` | `annual_self_employment_income` | `users` table | PASS |
| `$user->annual_rental_income` | `annual_rental_income` | `users` table | PASS |
| `$user->annual_dividend_income` | `annual_dividend_income` | `users` table | PASS |
| `$user->annual_interest_income` | `annual_interest_income` | `users` table | PASS |
| `$user->annual_other_income` | `annual_other_income` | `users` table | PASS |
| `$user->annual_trust_income` | `annual_trust_income` | `users` table | PASS |
| `$user->familyMembers()->where('relationship', 'child')->count()` | relationship + column | Both exist | PASS |
| `$user->is_preview_user` | `is_preview_user` | `users` table | PASS |

### buildFinancialContext()

Calls `$this->orchestrateAnalysis($user->id)` — this is CoordinatingAgent's existing method (pre-existing, unchanged, already working in production).

### Streaming (Anthropic SDK property access)

| Code | SDK class | Property | Actual name | Status |
|------|-----------|----------|-------------|--------|
| `$event->message->usage->inputTokens` | RawMessageStartEvent > Message > Usage | `inputTokens` | `inputTokens` | PASS |
| `$event->contentBlock->id` | ToolUseBlock | `id` | `id` | PASS |
| `$event->contentBlock->name` | ToolUseBlock | `name` | `name` | PASS |
| `$event->delta->text` | TextDelta | `text` | `text` | PASS |
| `$event->delta->partialJSON` | InputJSONDelta | `partialJSON` | `partialJSON` (capital JSON) | PASS (fixed) |
| `$event->delta->stopReason` | Delta | `stopReason` | `stopReason` (?string) | PASS |
| `$event->usage->outputTokens` | MessageDeltaUsage | `outputTokens` | `outputTokens` | PASS |

---

## 3. HasAiGuardrails Trait — Field Access Audit

| Code | Source | Status |
|------|--------|--------|
| `$user->is_preview_user` | `users.is_preview_user` | PASS |
| `$user->subscription()` | User relationship (line 159) | PASS |
| `$subscription->plan` | `subscriptions.plan` | PASS |
| `AiConversation::where('user_id', ...)` | `ai_conversations.user_id` | PASS |
| `->whereDate('updated_at', ...)` | `ai_conversations.updated_at` | PASS |
| `DB::raw('total_input_tokens + total_output_tokens')` | `ai_conversations.total_input_tokens`, `ai_conversations.total_output_tokens` | PASS |

---

## 4. CoordinatingAgent — Entity Creation Field Audit

### Goal::create()

| Field set | In `goals` fillable? | Status |
|-----------|---------------------|--------|
| `user_id` | YES | PASS |
| `goal_name` | YES | PASS |
| `goal_type` | YES | PASS |
| `target_amount` | YES | PASS |
| `target_date` | YES | PASS |
| `priority` | YES | PASS |
| `status` | YES | PASS |
| `current_amount` | YES | PASS |
| `start_date` | YES | PASS |

### LifeEvent::create()

| Field set | In `life_events` fillable? | Status |
|-----------|---------------------------|--------|
| `user_id` | YES | PASS |
| `event_name` | YES | PASS |
| `event_type` | YES | PASS |
| `description` | YES | PASS |
| `amount` | YES | PASS |
| `impact_type` | YES | PASS |
| `expected_date` | YES | PASS |
| `certainty` | YES | PASS |
| `status` | YES | PASS |

### SavingsAccount::create()

| Field set | In `savings_accounts` fillable? | Status |
|-----------|-------------------------------|--------|
| `user_id` | YES | PASS |
| `account_name` | YES | PASS |
| `account_type` | YES | PASS |
| `institution` | YES | PASS |
| `current_balance` | YES | PASS |
| `interest_rate` | YES | PASS |
| `access_type` | YES | PASS |
| `is_isa` | YES | PASS |
| `isa_type` | YES | PASS |
| `is_emergency_fund` | YES | PASS |
| `regular_contribution_amount` | YES | PASS |
| `contribution_frequency` | YES | PASS |
| `ownership_type` | YES | PASS |
| `ownership_percentage` | YES | PASS |
| `country` | YES | PASS |

### InvestmentAccount::create()

| Field set | In `investment_accounts` fillable? | Status |
|-----------|-----------------------------------|--------|
| `user_id` | YES | PASS |
| `account_name` | YES | PASS |
| `account_type` | YES | PASS |
| `provider` | YES | PASS |
| `current_value` | YES | PASS |
| `monthly_contribution_amount` | YES | PASS |
| `contribution_frequency` | YES | PASS |
| `platform_fee_percent` | YES | PASS |
| `ownership_type` | YES | PASS |
| `ownership_percentage` | YES | PASS |
| `country` | YES | PASS |
| `tax_year` | YES | PASS |

### DCPension::create()

| Field set | In `dc_pensions` fillable? | Status |
|-----------|---------------------------|--------|
| `user_id` | YES | PASS |
| `scheme_name` | YES | PASS |
| `scheme_type` | YES | PASS |
| `provider` | YES | PASS |
| `current_fund_value` | YES | PASS |
| `employee_contribution_percent` | YES | PASS |
| `employer_contribution_percent` | YES | PASS |
| `retirement_age` | YES | PASS |

### DBPension::create()

| Field set | In `db_pensions` fillable? | Status |
|-----------|---------------------------|--------|
| `user_id` | YES | PASS |
| `scheme_name` | YES | PASS |
| `scheme_type` | YES | PASS |
| `accrued_annual_pension` | YES | PASS |
| `normal_retirement_age` | YES | PASS |
| `pensionable_service_years` | YES | PASS |

### Property::create()

| Field set | In `properties` fillable? | Status |
|-----------|--------------------------|--------|
| `user_id` | YES | PASS |
| `property_type` | YES | PASS |
| `current_value` | YES | PASS |
| `purchase_price` | YES | PASS |
| `purchase_date` | YES | PASS |
| `address_line_1` | YES | PASS |
| `postcode` | YES | PASS |
| `outstanding_mortgage` | YES | PASS |
| `monthly_rental_income` | YES | PASS |
| `ownership_type` | YES | PASS |
| `ownership_percentage` | YES | PASS |
| `country` | YES | PASS |

### Mortgage::create()

| Field set | In `mortgages` fillable? | Status |
|-----------|-------------------------|--------|
| `property_id` | YES | PASS |
| `user_id` | YES | PASS |
| `outstanding_balance` | YES | PASS |
| `interest_rate` | YES | PASS |
| `lender_name` | YES | PASS |
| `mortgage_type` | YES | PASS |
| `rate_type` | YES | PASS |
| `ownership_type` | YES | PASS |
| `ownership_percentage` | YES | PASS |
| `country` | YES | PASS |
| `monthly_payment` | YES | PASS |
| `remaining_term_months` | YES | PASS |

### LifeInsurancePolicy::create()

| Field set | In fillable? | Status |
|-----------|-------------|--------|
| `user_id` | YES | PASS |
| `policy_type` | YES | PASS |
| `provider` | YES | PASS |
| `sum_assured` | YES | PASS |
| `premium_amount` | YES | PASS |
| `premium_frequency` | YES | PASS |
| `policy_term_years` | YES | PASS |
| `in_trust` | YES | PASS |

### CriticalIllnessPolicy::create()

| Field set | In fillable? | Status |
|-----------|-------------|--------|
| `user_id` | YES | PASS |
| `policy_type` | YES | PASS |
| `provider` | YES | PASS |
| `sum_assured` | YES | PASS |
| `premium_amount` | YES | PASS |
| `premium_frequency` | YES | PASS |
| `policy_term_years` | YES | PASS |

### IncomeProtectionPolicy::create()

| Field set | In fillable? | Status |
|-----------|-------------|--------|
| `user_id` | YES | PASS |
| `provider` | YES | PASS |
| `benefit_amount` | YES | PASS |
| `benefit_frequency` | YES | PASS |
| `premium_amount` | YES | PASS |

### Asset::create()

| Field set | In `assets` fillable? | Status |
|-----------|----------------------|--------|
| `user_id` | YES | PASS |
| `asset_name` | YES | PASS |
| `asset_type` | YES | PASS |
| `current_value` | YES | PASS |
| `is_iht_exempt` | YES | PASS |
| `exemption_reason` | YES | PASS |
| `ownership_type` | YES | PASS |
| `valuation_date` | YES | PASS |

### Liability::create()

| Field set | In `liabilities` fillable? | Status |
|-----------|---------------------------|--------|
| `user_id` | YES | PASS |
| `liability_name` | YES | PASS |
| `liability_type` | YES | PASS |
| `current_balance` | YES | PASS |
| `monthly_payment` | YES | PASS |
| `interest_rate` | YES | PASS |
| `ownership_type` | YES | PASS |
| `country` | YES | PASS |

### Gift::create()

| Field set | In `gifts` fillable? | Status |
|-----------|---------------------|--------|
| `user_id` | YES | PASS |
| `gift_date` | YES | PASS |
| `recipient` | YES | PASS |
| `gift_type` | YES | PASS |
| `gift_value` | YES | PASS |
| `status` | YES | PASS |
| `notes` | YES | PASS |

---

## 5. Routes & Middleware Audit

### AI Chat routes (existing, modified controller only)

| Route | Method | Controller | Exists? | Status |
|-------|--------|-----------|---------|--------|
| `GET /api/ai-chat/conversations` | index | AiChatController | YES | PASS |
| `POST /api/ai-chat/conversations` | create | AiChatController | YES | PASS |
| `GET /api/ai-chat/conversations/{id}` | show | AiChatController | YES | PASS |
| `DELETE /api/ai-chat/conversations/{id}` | destroy | AiChatController | YES | PASS |
| `POST /api/ai-chat/conversations/{id}/messages` | sendMessage | AiChatController | YES | PASS |

### Internal Agent API routes (new)

| Route | Method | Controller method | Method exists? | Status |
|-------|--------|------------------|---------------|--------|
| `GET /api/internal/agent/analysis/{module}` | moduleAnalysis | YES (line 50) | PASS |
| `GET /api/internal/agent/tax/{topic}` | taxInformation | YES (line 94) | PASS |
| `POST /api/internal/agent/scenario` | scenario | YES (line 127) | PASS |
| `POST /api/internal/agent/prerequisite-check` | prerequisiteCheck | YES (line 168) | PASS |
| `GET /api/internal/agent/user-context/{userId}` | userContext | YES (line 203) | PASS |
| **MISSING** | recommendations | YES (line 235) | **FAIL — no route** |

### Middleware

| Alias | Class | Registered in Kernel? | Config key | In `.env`? | Status |
|-------|-------|----------------------|-----------|-----------|--------|
| `agent.token` | `AgentTokenAuth` | YES (Kernel line 79) | `services.anthropic.agent_internal_token` | YES (`AGENT_INTERNAL_TOKEN`) | PASS |

---

## 6. Config Audit

| Config key | `.env` variable | In `config/services.php`? | Status |
|-----------|----------------|--------------------------|--------|
| `services.anthropic.api_key` | `ANTHROPIC_API_KEY` | YES | PASS |
| `services.anthropic.chat_model` | `ANTHROPIC_CHAT_MODEL` | YES | PASS |
| `services.anthropic.advanced_chat_model` | `ANTHROPIC_ADVANCED_CHAT_MODEL` | YES | PASS |
| `services.anthropic.agent_internal_token` | `AGENT_INTERNAL_TOKEN` | YES | PASS |

---

## 7. Python Sidecar Audit

### Python tools.py API calls vs Laravel routes

| Python function | Calls | Route exists? | Status |
|----------------|-------|--------------|--------|
| `get_module_analysis(module)` | `GET /api/internal/agent/analysis/{module}` | YES | PASS |
| `get_tax_information(topic)` | `GET /api/internal/agent/tax/{topic}` | YES | PASS |
| `run_what_if_scenario(module, params)` | `POST /api/internal/agent/scenario` | YES | PASS |
| `get_recommendations()` | `GET /api/internal/agent/recommendations` | **NO — missing route** | **FAIL** |
| `get_user_context(user_id)` | `GET /api/internal/agent/user-context/{user_id}` | YES | PASS |

### Python hooks.py API calls

| Function | Calls | Route exists? | Status |
|----------|-------|--------------|--------|
| `prerequisite_hook` | `POST /api/internal/agent/prerequisite-check` | YES | PASS |

---

## 8. AppServiceProvider Audit

| Registration | Class | Import correct? | Status |
|-------------|-------|----------------|--------|
| `AnthropicClient` singleton | `Anthropic\Client` | YES (imported as `AnthropicClient`) | PASS |
| Constructor: `new AnthropicClient(apiKey: $apiKey)` | Matches SDK constructor | YES (`?string $apiKey = null`) | PASS |

---

## 9. AiChatController Audit

| Dependency | Type | Resolved by container? | Status |
|-----------|------|----------------------|--------|
| `CoordinatingAgent` | Constructor injection | YES (auto-resolved, all deps injectable) | PASS |

| Method call | Exists on CoordinatingAgent? | Status |
|------------|------------------------------|--------|
| `$this->coordinatingAgent->chat(...)` | YES (via HasAiChat trait) | PASS |

---

## 10. AiToolDefinitions Audit

| Issue | Detail | Status |
|-------|--------|--------|
| `strict: true` removed | Was causing "too many optional parameters (51), limit: 24" | FIXED |

---

## 11. Navigation Route Audit

Routes the AI can navigate to (via `navigate_to_page` tool) vs actual Vue router paths:

| Tool definition route | Vue router path | Correct? |
|-----------------------|----------------|----------|
| `/dashboard` | `/dashboard` | PASS |
| `/profile` | `/profile` | PASS |
| `/settings` | `/settings` | PASS |
| `/net-worth/wealth-summary` | `/net-worth/wealth-summary` | PASS |
| `/net-worth/property` | `/net-worth/property` | PASS |
| `/net-worth/investments` | `/net-worth/investments` | PASS |
| `/net-worth/retirement` | `/net-worth/retirement` | PASS |
| `/net-worth/cash` | `/net-worth/cash` | PASS |
| `/net-worth/business` | `/net-worth/business` | PASS |
| `/net-worth/chattels` | `/net-worth/chattels` | PASS |
| `/net-worth/liabilities` | `/net-worth/liabilities` | PASS |
| `/valuable-info?section=income` | `/valuable-info` + query param | PASS |
| `/valuable-info?section=expenditure` | `/valuable-info` + query param | PASS |
| `/valuable-info?section=letter` | `/valuable-info` + query param | PASS |
| `/protection` | `/protection` | PASS |
| `/estate` | `/estate` | PASS |
| `/estate/will-builder` | `/estate/will-builder` | PASS |
| `/estate/power-of-attorney` | `/estate/power-of-attorney` | PASS |
| `/goals` | `/goals` | PASS |
| `/holistic-plan` | `/holistic-plan` | PASS |
| `/trusts` | `/trusts` | PASS |
| `/risk-profile` | `/risk-profile` | PASS |
| `/plans` | `/plans` | PASS |
| `/actions` | `/actions` | PASS |
| `/planning/journeys` | `/planning/journeys` | PASS |
| `/planning/what-if` | `/planning/what-if` | PASS |

### PrerequisiteGateService navigation routes

| Gate action | Route sent | Correct destination? | Status |
|-------------|-----------|---------------------|--------|
| Complete your profile (DOB) | `/profile` | YES — Personal Information section | PASS |
| Set your marital status | `/profile` | YES — Personal Information section | PASS |
| Set your retirement date | `/profile` | YES — Personal Information section | PASS |
| Set your employment status | `/profile` | YES — Personal Information section | PASS |
| Add your income details | `/valuable-info?section=income` | YES — Income section | PASS |
| Add your expenditure | `/valuable-info?section=expenditure` | YES — Expenditure section | PASS |
| Add a pension | `/net-worth/retirement` | YES — Pension list | PASS |
| Add an investment account | `/net-worth/investments` | YES — Investment list | PASS |
| Add an asset | `/net-worth/wealth-summary` | YES — Net worth overview | PASS |
| Create a goal | `/goals` | YES — Goals dashboard | PASS |
| Add financial data | `/dashboard` | YES — Main dashboard | PASS |

---

## Issues Found (all fixed)

### ISSUE 1: Missing `/api/internal/agent/recommendations` route
**File:** `routes/api.php`
**Impact:** Python sidecar `get_recommendations()` tool would 404
**Fix:** Added route pointing to `AgentInternalController::recommendations`

### ISSUE 2: Gate checks did not match actual agent DataReadinessService blocking checks
**File:** `PrerequisiteGateService.php`
**Impact:** Gates were inventing their own blocking rules instead of mirroring what the agents actually block on. This caused false blocks (e.g. retirement blocked when agent would proceed) and missed blocks (e.g. investment not checking risk_profile when agent requires it).
**Root cause:** Gate was written from the plan spec, not verified against the actual DataReadinessService code.
**Fix:** Complete rewrite of all module gates to mirror the exact blocking checks from each agent's DataReadinessService:

| Module | Before (wrong) | After (matches agent) |
|--------|----------------|----------------------|
| Protection | `date_of_birth`, `income OR employment_status`, `marital_status` | `date_of_birth`, `income`, `marital_status` |
| Savings | `expenditure` only | `date_of_birth`, `income`, `expenditure` (3 sources) |
| Retirement | `date_of_birth`, `retirement_date` (3 sources), `income`, `pensions` | `date_of_birth`, `marital_status`, `risk_profile` |
| Investment | `date_of_birth`, `investmentAccounts` | `date_of_birth`, `income`, `risk_profile`, `expenditure` (3 sources) |
| Estate | `date_of_birth`, `marital_status`, `any asset` | `date_of_birth`, `marital_status`, `any asset` (unchanged) |

Additional fixes:
- Expenditure now checks all 3 sources (ExpenditureProfile, User.monthly_expenditure, User.annual_expenditure) via `hasExpenditure()` helper, matching the `ResolvesExpenditure` trait
- Investment now checks `RiskProfile` existence, matching `InvestmentDataReadinessService`
- Retirement no longer blocks on `retirement_date`/`target_retirement_age`/`pensions` — these are WARNING level in `RetirementDataReadinessService`, not blocking (agent defaults to State Pension age)
- Retirement now blocks on `risk_profile` per product requirement

### ISSUE 3: `partialJson` vs `partialJSON`
**File:** `HasAiChat.php`
**Impact:** Tool input JSON not accumulated during streaming, causing second API call to fail
**Fix:** Changed to `partialJSON` matching SDK property name

### ISSUE 4: `strict: true` on tool schemas
**File:** `AiToolDefinitions.php`
**Impact:** Anthropic API rejected all requests with 400 error
**Fix:** Removed `strict: true`

### ISSUE 5: Docked chat `AiMessageContent` prop
**File:** `AiChatPanel.vue`
**Impact:** Messages rendered blank in docked mode
**Fix:** Changed `:content` to `:message` with correct object shape

### ISSUE 6: Navigation routes pointing to wrong pages
**Files:** `PrerequisiteGateService.php`, `AiToolDefinitions.php`, `HasAiChat.php`
**Impact:** AI navigated users to `/profile` when they needed income (`/valuable-info?section=income`) or expenditure (`/valuable-info?section=expenditure`). Tool definition was missing routes for income, expenditure, will builder, LPA, plans, actions, and what-if scenarios.
**Fix:**
- `PrerequisiteGateService.php`: All income routes now `/valuable-info?section=income`, expenditure routes now `/valuable-info?section=expenditure`, risk profile routes now `/risk-profile`
- `AiToolDefinitions.php`: Expanded valid routes list from 17 to 26, adding `/valuable-info?section=income`, `/valuable-info?section=expenditure`, `/valuable-info?section=letter`, `/estate/will-builder`, `/estate/power-of-attorney`, `/plans`, `/actions`, `/planning/journeys`, `/planning/what-if`
- `HasAiChat.php`: Expanded module context map from 15 to 24 entries matching all new routes; retirement age now resolved from all 3 sources in user profile prompt

### ISSUE 7: System prompt missing user's retirement age
**File:** `HasAiChat.php`
**Impact:** AI could not reference the user's actual retirement age in responses because `buildUserProfile()` only checked `$user->retirement_date`. If that was null, no retirement info appeared in the prompt.
**Fix:** Now checks `$user->retirement_date`, then `$user->target_retirement_age`, then `$user->retirementProfile->target_retirement_age` — matching the 3 places this data can live.

---

## Flaky Test Note

`Tests\Unit\Services\Estate\WillDocumentServiceTest > generateMirrorWill` fails intermittently in the full suite but passes consistently in isolation. This is a pre-existing test ordering issue (likely shared database state from a preceding test). Not introduced by this upgrade. Needs investigation separately.
