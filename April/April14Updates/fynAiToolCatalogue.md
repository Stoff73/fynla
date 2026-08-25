# Fyn AI — Per-Tool Catalogue

*Report date: 14 April 2026*
*Companion document to `fynAiSystemReport.md` (§3 Tools and their schemas). This file is the authoritative per-tool reference.*
*Scope: PHP backend only — `AiToolDefinitions.php` (Anthropic), `XaiToolDefinitions.php` (xAI strict mode), `CoordinatingAgent.php` handlers.*
*Method: static code read. No runtime verification.*

---

## 0. How to read this document

Each tool entry follows a consistent structure:

```
### Tool: `tool_name` — one-line purpose

| Field | Value |
|---|---|
| Category | read / write / meta |
| Anthropic defn | AiToolDefinitions.php:LINE  (or "not exposed") |
| xAI defn | XaiToolDefinitions.php:LINE (or "not exposed") |
| Handler | CoordinatingAgent.php::methodName:LINE |
| Preview allowed? | yes / no |
| Side effect | what happens to the DB / the user's UI |
| Yielded event | SSE event type the client sees |

**Description** — the exact description text given to the model (truncated where repetitive).

**Parameters** — table of every parameter with type, required?, enum values, notes.

**Returns** — the shape of the array the handler returns, which is JSON-serialised and sent back to the model as a tool_result (and which drives the yielded SSE event).

**Validation rules** — the Laravel rules applied inside the handler via `validateToolInput`.

**Error cases** — what the caller sees when things go wrong.

**Prerequisite gate** — whether `PrerequisiteGateService::canExecuteTool` runs a real check or just passes through.

**Notes** — quirks, asymmetries, or behaviour that isn't obvious from the schema.
```

### 0.1 Critical architectural fact — write tools are assistive, not autonomous

Every "create" tool in this catalogue returns a `{action: 'fill_form', entity_type, route, fields, mode?, entity_id?}` shape to the LLM and yields a `fill_form` SSE event to the client. **The tool itself does not write to the database.** The client opens the relevant page, opens the entity's form modal, and pre-fills the fields. The user then reviews, optionally edits, and clicks Save — which goes through the normal REST API path (the same one used for manual data entry).

This has four important consequences:

1. **Human in the loop for writes.** Fyn cannot silently create records. A compromised conversation can queue up a `fill_form` but cannot commit it.
2. **Validation is two-layered.** The tool handler's `validateToolInput` rejects obviously malformed input before the form opens. The form's normal validation then runs on Save — any server-side rule that would reject the data is still enforced.
3. **Fyn re-uses every existing form.** There is no second "AI write" code path. The same `POST /api/savings` / `POST /api/properties` / etc. endpoints handle both AI-assisted and manually-submitted creates.
4. **Audit trail has three locations.** The assistant message stores a `tool_calls` summary, `Log::channel('single')` records an `[AI-AUDIT] Tool executed` line, and the normal REST endpoint's audit observers fire on Save.

**Exceptions** — tools that *do* write directly:

| Tool | What it writes | Why it bypasses fill_form |
|---|---|---|
| `create_what_if_scenario` | `what_if_scenarios` row | Scenarios are transient analysis artefacts, not financial records. Immediately navigates to the scenario page. |
| `delete_record` | Calls `$model->delete()` | Delete has no form UI to pre-fill. The handler requires the model to exist and belong to the user before deleting. |
| `update_profile` | `users` row (profile fields) | Direct update via Eloquent. Excludes NI number and other sensitive PII. |
| `set_expenditure` | (mechanism varies — see §E.5) | Expenditure is a profile-level set, not a record-level create. |

Everything else is `fill_form`-based.

### 0.2 Provider parity

| Tool | Anthropic | xAI | Notes |
|---|---|---|---|
| `navigate_to_page` | ✓ | ✓ | Both |
| `list_goals` | ✓ | ✓ | Both |
| `list_life_events` | ✓ | ✓ | Both |
| **`list_records`** | ✗ | ✓ | **xAI only** — handler exists but Anthropic's tool catalogue never exposes it |
| `get_module_analysis` | ✓ | ✓ | Both |
| `get_recommendations` | ✓ | ✓ | Both |
| `get_tax_information` | ✓ | ✓ | Both |
| `generate_financial_plan` | ✓ | ✓ | Both |
| `create_what_if_scenario` | ✓ | ✓ | Both (strict: false on xAI due to dynamic `parameters` object) |
| `create_goal` | ✓ | ✓ | Both |
| `create_life_event` | ✓ | ✓ | Both |
| `create_savings_account` | ✓ | ✓ | Both |
| `create_investment_account` | ✓ | ✓ | Both |
| **`create_holding`** | ✗ | ✓ | **xAI only** — handler exists but Anthropic never exposes it |
| `create_pension` | ✓ | ✓ | Both |
| `create_property` | ✓ | ✓ | xAI schema is substantially richer (address, tenure, monthly costs, BTL fields) |
| `create_mortgage` | ✓ | ✓ | Both |
| `create_protection_policy` | ✓ | ✓ | Both |
| `create_asset` | ✓ | ✓ | Both |
| `create_liability` | ✓ | ✓ | Both |
| `create_estate_gift` | ✓ | ✓ | Both |
| `create_family_member` | ✓ | ✓ | Both |
| `create_trust` | ✓ | ✓ | Both |
| `create_business_interest` | ✓ | ✓ | Both |
| `create_chattel` | ✓ | ✓ | Both |
| **`set_expenditure`** | ✗ | ✓ | **xAI only** — but `update_profile` on Anthropic redirects to this handler when `section=expenditure` |
| `update_record` | ✓ | ✓ | Both |
| `delete_record` | ✓ | ✓ | Both |
| `update_profile` | ✓ | ✓ | Both |

**3 tools are xAI-only by definition exposure.** This is a latent parity gap — if the admin toggles the active provider from `xai` back to `anthropic`, the `list_records`, `create_holding`, and `set_expenditure` tools silently disappear from Fyn's capabilities. The handlers remain in `CoordinatingAgent::executeTool` so they are not dead code, but Anthropic-driven conversations cannot reach them. This is documented at §F.4 of the main report as a parity gap to fix.

### 0.3 Preview mode

Preview users (seeded demo personas) get only this subset exposed at definition time — the other tools are not merged into the tool array:

- `navigate_to_page`
- `list_goals` (not `list_records`)
- `list_life_events`
- `get_module_analysis`
- `get_recommendations`
- `get_tax_information`
- `generate_financial_plan`

All other tools are filtered out in `AiToolDefinitions::getTools(true)` — the preview-mode LLM never sees them in its tool catalogue. As a second line of defence, every write handler starts with `if ($isPreview) return $this->previewBlocked($entityType)` which returns a fixed shape the LLM can interpret as "this is a demo persona, explain that this feature requires a real account".

### 0.4 Universal tool input coercion

Before any match statement, `CoordinatingAgent::executeTool` runs (`CoordinatingAgent.php:639`):

```php
$input = array_map(function ($v) {
    if ($v === 'null') {
        return null;
    }
    if (is_string($v)) {
        return html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $v;
}, $input);
```

- Literal string `"null"` becomes PHP `null` (xAI strict-mode quirk — when the model decides to omit a nullable field, it sometimes sends the word `"null"`).
- Every string value is HTML-entity-decoded (xAI sometimes encodes `&` as `&amp;` in tool arguments).

This runs once for every tool call before the handler sees the input. It is shallow — nested objects/arrays are not recursively coerced. For flat-schema tools this is fine; for `update_record.fields` and `create_what_if_scenario.parameters` (both of which have nested objects), the coercion stops at the first level.

### 0.5 Generic validator

`CoordinatingAgent::validateToolInput($input, $rules)` wraps Laravel's `Validator::make()`. On failure it returns `['error' => true, 'error_type' => 'validation_failed', 'message' => first error]`. On success it returns `null` and the handler continues.

The rules used in handlers are standard Laravel validation — `required|string|max:N`, `nullable|numeric|min:0|max:N`, `Rule::in([...])`, `date|after:today`, etc. Every write handler uses this; read handlers generally do not.

### 0.6 Generic error shape

Every handler that returns an error follows this shape:

```php
[
    'error' => true,
    'error_type' => 'validation_failed' | 'not_found' | 'database_error' | 'execution_failed' | 'invalid_entity' | 'unknown_tool' | 'missing_required' | ...,
    'message' => 'Human-readable explanation',
]
```

On the Anthropic path, this is returned to the LLM as a `tool_result` block with `is_error: true`. On the xAI path, it is returned as a `role: tool` message whose content is the JSON-encoded error object — no explicit `is_error` flag is set (the model must notice `error: true` in the content).

### 0.7 Audit logging

Every write-tool execution also writes a second, independent audit log entry:

```php
if (str_starts_with($toolName, 'create_') || in_array($toolName, ['update_record', 'delete_record', 'update_profile'])) {
    Log::channel('single')->info('[AI-AUDIT] Tool executed', [
        'user_id' => $user->id,
        'tool' => $toolName,
        'entity_id' => $entityId,
        'success' => ! isset($result['error']),
        'preview' => $isPreviewUser,
    ]);
}
```

This is in addition to the `tool_calls` metadata on the assistant message. Purpose: a grep-friendly, log-file-rotated audit trail that can be shipped to a SIEM independently of the database. Note that `set_expenditure` is not covered by this regex — it does not start with `create_` and it is not in the allow-list. This is a minor gap.

### 0.8 Prerequisite gate pre-flight

Every tool call, before the `match` statement, goes through:

```php
$gate = $this->prerequisiteGate->canExecuteTool($toolName, $input, $user);
if (! $gate['can_proceed']) {
    return [
        'blocked' => true,
        'reason' => $gate['guidance'],
        'missing_data' => $gate['missing'],
        'suggested_action' => $firstAction,
        'instruction' => 'Explain to the user exactly what data is missing...',
    ];
}
```

For read tools like `get_module_analysis` and `generate_financial_plan`, this is a meaningful check — e.g. "estate analysis needs at least one asset". For write tools, `PrerequisiteGateService::canExecuteTool` currently returns `pass()` unconditionally (`PrerequisiteGateService.php:202-207`), so the gate is effectively a no-op on writes. Write-side data validation happens inside each handler instead.

---

## Section A — Read / analysis / navigation tools (8 tools)

---

### A.1 Tool: `navigate_to_page` — route the user to a specific page

| Field | Value |
|---|---|
| Category | Navigation (read-only) |
| Anthropic defn | `AiToolDefinitions.php:52` |
| xAI defn | `XaiToolDefinitions.php:97` |
| Handler | `CoordinatingAgent::handleNavigation:730` |
| Preview allowed? | Yes |
| Side effect | None in PHP — client-side router navigation only |
| Yielded event | `{type: 'navigation', route_path, description}` |

**Description**: "Navigate the user to a specific page in the application. Use this when the user asks to go somewhere or when showing them relevant information would be helpful."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `route_path` | string | yes | An allow-list of ~35 routes is baked into the description (dashboard, profile, settings, valuable-info sections, net-worth sub-pages, protection, estate, trusts, goals, risk-profile, plans, actions, planning). The description explicitly tells the model "NEVER use /savings or /investment — these are legacy redirects. Use /net-worth/cash and /net-worth/investments instead." |
| `description` | string | yes | Short explanation of why the navigation is helpful, shown to the user in the notification toast |

**Returns**

```php
['action' => 'navigate', 'route_path' => $input['route_path'], 'description' => $input['description'] ?? '']
```

**Validation rules**: None. The handler trusts the `route_path` string — it does not verify it matches the allow-list. If the model hallucinates a non-existent route, the Vue router will 404 on the client.

**Error cases**: None at handler level. Frontend router handles invalid paths.

**Prerequisite gate**: passes through (no real check).

**Notes**
- The allow-list in the description is the only enforcement of valid routes. This is prompt-level, not code-level.
- Preview users can navigate freely — the navigation tool does not write anything, so it has no preview concerns.
- Because the event is a separate `navigation` SSE type (not `content`), the frontend can trigger `router.push` mid-stream while text is still being written below.

---

### A.2 Tool: `list_goals` — list the user's financial goals with IDs

| Field | Value |
|---|---|
| Category | Analysis (read-only) |
| Anthropic defn | `AiToolDefinitions.php:89` |
| xAI defn | `XaiToolDefinitions.php:148` |
| Handler | `CoordinatingAgent::handleListGoals:880` |
| Preview allowed? | Yes |
| Side effect | None — SELECT from `goals` (with joint ownership) |
| Yielded event | None (tool_result only — flows back to model) |

**Description**: "List all of the user's financial goals with their current progress, status, and IDs. Use this when the user asks about their goals, wants to see progress, or before updating/deleting a specific goal. **This is a lightweight call — use it instead of `get_module_analysis(goals)` when you just need the goal list.**"

**Parameters**: none.

**Returns**: array of goals with `id`, `goal_name`, `goal_type`, `target_amount`, `current_amount`, `target_date`, `priority`, `status`, `is_on_track`, `monthly_contribution`. (Exact fields per-handler — see `handleListGoals:880-917`.)

**Validation rules**: none.

**Error cases**: none — returns an empty array if the user has no goals.

**Prerequisite gate**: passes through.

**Notes**
- The explicit "lightweight call" language in the description is a token-saving instruction. `get_module_analysis(goals)` runs the full goals agent and returns ~20KB of analysis; `list_goals` just returns the IDs and progress. The model is told to prefer the cheap call for simple lookups.
- Uses `Goal::forUserOrJoint($user->id)` — joint goals are included.

---

### A.3 Tool: `list_life_events` — list the user's upcoming life events with IDs

| Field | Value |
|---|---|
| Category | Analysis (read-only) |
| Anthropic defn | `AiToolDefinitions.php:98` |
| xAI defn | `XaiToolDefinitions.php:154` |
| Handler | `CoordinatingAgent::handleListLifeEvents:919` |
| Preview allowed? | Yes |
| Side effect | None — SELECT from `life_events` (with joint ownership) |
| Yielded event | None |

**Description**: "List all of the user's life events with dates, amounts, and IDs. Use this when the user asks about their life events, upcoming events, or before updating/deleting a specific event. This is a lightweight call — use it instead of `get_module_analysis(goals)` when you just need the event list."

**Parameters**: none.

**Returns**: array of events with `id`, `event_name`, `event_type`, `amount`, `expected_date`, `certainty`, `impact_type`, `months_until`. (Exact fields per handler.)

**Validation rules**: none.

**Error cases**: none.

**Prerequisite gate**: passes through.

**Notes**
- Same lightweight vs heavy-analysis rationale as `list_goals`.
- Joint events are included via `LifeEvent::forUserOrJoint`.

---

### A.4 Tool: `list_records` — xAI-only — list existing records of a given entity type

| Field | Value |
|---|---|
| Category | Analysis (read-only) |
| Anthropic defn | **not exposed** |
| xAI defn | `XaiToolDefinitions.php:134` |
| Handler | `CoordinatingAgent::handleListRecords:735` |
| Preview allowed? | No (xAI-only tool, preview users default to Anthropic/anthropic is default) |
| Side effect | None — SELECT on one of ~15 entity tables |
| Yielded event | None |

**Description**: "List existing records of a given type with IDs and key details. Use this BEFORE calling `update_record` to find the correct entity_id. Also use when the user asks 'what accounts do I have?' or 'show me my pensions'. The `<existing_records>` section in the system prompt already has a snapshot — use this tool for a fresh, detailed lookup."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `entity_type` | string enum | yes | One of: `savings_account`, `investment_account`, `dc_pension`, `db_pension`, `property`, `mortgage`, `life_insurance`, `critical_illness`, `income_protection`, `trust`, `business_interest`, `chattel`, `estate_liability`, `estate_gift`, `family_member` |

**Returns** (per handler `handleListRecords:735-878`):

```php
[
    'entity_type' => $entityType,
    'records' => [ /* array of flat dicts */ ],
    'count' => int,
]
```

The record shape differs per entity. For `savings_account`: `id`, `account_name`, `institution`, `balance`, `type`, `interest_rate`, `rate_valid_until`, `access_type`, `notice_period_days`, `maturity_date`, `is_emergency_fund`, `is_isa`, `isa_type`, `isa_subscription_amount`, `regular_contribution`, `contribution_frequency`, `ownership_type`, `your_share_percent`, `co_owner_share_percent`, `co_owner`, `total_balance`, `your_share_value`. Other entity types have analogous (but entity-appropriate) field sets — the handler is ~140 lines of switch-case mapping.

**Validation rules**: returns `['error' => true, 'message' => 'entity_type is required']` if missing; returns `['error' => true, 'message' => "Unknown entity type: {$entityType}"]` for invalid values.

**Error cases**: as above.

**Prerequisite gate**: passes through.

**Notes**
- This is the most detailed read tool — the returned shape is close to the REST API's index-endpoint output but flattened for the model.
- Joint-ownership records return the user's share alongside the total, computed on the fly from `ownership_percentage`.
- **Parity gap**: the Anthropic-side tool catalogue does not include this tool, so a user on the Anthropic provider cannot benefit from the richer detail. They get the cached `<existing_records>` snapshot in the system prompt only, which is compact but uses ID tags rather than structured fields.

---

### A.5 Tool: `get_module_analysis` — run a module agent and return its analysis

| Field | Value |
|---|---|
| Category | Analysis (read-only) |
| Anthropic defn | `AiToolDefinitions.php:107` |
| xAI defn | `XaiToolDefinitions.php:160` |
| Handler | `CoordinatingAgent::handleModuleAnalysis:957` |
| Preview allowed? | Yes |
| Side effect | Cache read/write (per module); module agents run their full analyse path |
| Yielded event | None |

**Description**: "Get detailed financial analysis for a specific module. Returns personalised analysis based on the user's actual financial data."

**Parameters**

| Name | Type | Required | Enum |
|---|---|---|---|
| `module` | string | yes | `protection`, `savings`, `investment`, `retirement`, `estate`, `goals`, `holistic` |

**Returns**: module-specific analysis shape. For `protection`: coverage gap, life cover total, critical illness total, income protection monthly benefit, recommendations. For `savings`: total, emergency fund months, ISA allowance used, market rate comparison, recommendations. Each module's shape is determined by its agent's `analyze()` method — these are the same shapes consumed by the REST API and by `buildFinancialContext` in the system prompt.

The handler summarises the result via `summariseToolAnalysis($module, $analysis)` (`CoordinatingAgent.php:2077`) which extracts key metrics and truncates anything beyond the first 5 entries per key. This is a **token-cost optimisation** — the raw analysis can be 15–40KB; the summary is usually 1–3KB.

**Validation rules**: the enum is enforced at schema level by both providers; the handler does not double-check.

**Error cases**: `FinancialCalculationException` from the module agent → caught by the outer `executeTool` try/catch → `{error: true, error_type: execution_failed, message: 'An unexpected error occurred. Please try again.'}`. A prerequisite gate block (e.g. estate has no assets) is caught before the handler runs and returns `{blocked: true, reason, missing_data, suggested_action, instruction}`.

**Prerequisite gate**: **real check** — `PrerequisiteGateService::canExecuteTool` routes to `enforce($module, $user)` which runs the module-specific `canAnalyse*` method. See §5 of the main report.

**Notes**
- `holistic` is handled specially — it runs `orchestrateAnalysis` and returns the cross-module ranked recommendations. Requires the holistic gate (`canGenerateHolisticPlan`).
- Cached results from the module agents (via `BaseAgent::remember`) mean repeated calls within 60s are effectively free.
- The prompt in layer 8b tells the model exactly which module to analyse for each query type — see `QuerySchemas::REQUIRED_TOOLS`.

---

### A.6 Tool: `get_recommendations` — ranked recommendations across all modules

| Field | Value |
|---|---|
| Category | Analysis (read-only) |
| Anthropic defn | `AiToolDefinitions.php:123` |
| xAI defn | `XaiToolDefinitions.php:172` |
| Handler | `CoordinatingAgent::handleRecommendations:996` |
| Preview allowed? | Yes |
| Side effect | Cache-backed orchestrate call |
| Yielded event | None |

**Description**: "Get the user's personalised financial recommendations ranked by priority across all modules."

**Parameters**: none.

**Returns**:

```php
[
    'recommendations' => $analysis['ranked_recommendations'] ?? [],
    'total' => count(...),
    'surplus' => $analysis['available_surplus'] ?? 0,
]
```

Each recommendation has `title`, `module`, `urgency_score` (0-100), `priority`, `estimated_saving`, `action`, `description`, `decision_trace` (the triggering rule from the decision engine).

**Validation rules**: none.

**Error cases**: orchestrate failures surface via the outer try/catch.

**Prerequisite gate**: **real check** — `canGetRecommendations` requires data in at least one of the 7 financial modules. If the user has nothing entered yet, the gate returns blocked with guidance "Recommendations require data in at least one area of your financial plan."

**Notes**
- This is essentially a thin wrapper around `orchestrateAnalysis`. The real work is the cross-module coordinator.
- Recommendations are filtered in `buildFinancialContext` (system prompt layer 5) by classification modules — but this tool returns the full unfiltered list.
- The prompt explicitly tells the model to reference ranked recommendations (via the `<relevant_triggers>` block) rather than inventing its own.

---

### A.7 Tool: `get_tax_information` — fetch current UK tax config for a topic

| Field | Value |
|---|---|
| Category | Reference (read-only) |
| Anthropic defn | `AiToolDefinitions.php:138` |
| xAI defn | `XaiToolDefinitions.php:186` |
| Handler | `CoordinatingAgent::handleTaxInformation:1007` |
| Preview allowed? | Yes |
| Side effect | `Cache::remember` for 300s (per topic) or 120s (for `income_definitions`) |
| Yielded event | None |

**Description**: "Get current UK tax year information for a specific topic. **ALWAYS use this tool when the user asks about tax thresholds, allowances, rates, or any financial product tax treatment.** Never state tax values from memory — always retrieve them. Use `income_definitions` to get the user's detailed income breakdown including adjusted net income, threshold income, and tapered pension allowances."

**Parameters**

| Name | Type | Required | Enum |
|---|---|---|---|
| `topic` | string | yes | `income_tax`, `national_insurance`, `capital_gains`, `dividend_tax`, `inheritance_tax`, `gifting_exemptions`, `stamp_duty`, `isa_allowances`, `pension_allowances`, `state_pension`, `benefits`, `savings_config`, `assumptions`, `investment_bonds`, `venture_capital`, `protection_config`, `retirement_config`, `domicile`, `income_definitions` |

**Returns**: the raw shape from `TaxConfigService` for that topic. For `inheritance_tax`: `{nil_rate_band, residence_nil_rate_band, taper_threshold, rate_percent, charity_rate_percent, gifting_exemptions}`. For `pension_allowances`: `{annual_allowance, money_purchase_annual_allowance, tapered_annual_allowance, adjusted_income_threshold, threshold_income_threshold, lifetime_allowance_abolished, lump_sum_allowance}`. For `income_definitions` (the per-user topic): output of `IncomeDefinitionsService::calculate($user->id)` — adjusted net income, threshold income, tapered allowance amount, and the intermediate calculations.

**Validation rules**: enum enforced at schema level. Handler `default` branch returns `['error' => "Unknown tax topic: {$topic}"]` (note: lowercase `error`, not `error: true` — minor inconsistency with the general error shape).

**Error cases**: unknown topic only.

**Prerequisite gate**: passes through.

**Notes**
- This is the mechanism that enforces §2 of the compliance rules ("Tax data accuracy. NEVER state tax rates, thresholds, allowances, or financial product details from memory. ALWAYS use the `get_tax_information` tool").
- `TaxConfigService` is a request-scoped singleton backed by the `tax_configurations` DB table, seeded per UK tax year. Changing the active tax year in the admin panel flips what this tool returns without code changes.
- The 300-second cache is shared across users — `ai_tax_info_{topic}`. The `income_definitions` topic is per-user and cached for 120s.
- The full list of banned tax acronyms (Section 7 of main report) is enforced elsewhere; this tool is responsible for making sure the model has the real figures to quote.

---

### A.8 Tool: `generate_financial_plan` — run the holistic planner and return the top 5

| Field | Value |
|---|---|
| Category | Plan (read-only) |
| Anthropic defn | `AiToolDefinitions.php:168` |
| xAI defn | `XaiToolDefinitions.php:214` |
| Handler | `CoordinatingAgent::handleFinancialPlan:1050` |
| Preview allowed? | Yes |
| Side effect | Runs every module agent's `analyze()`, then cross-module coordination |
| Yielded event | None |

**Description**: "Generate a comprehensive holistic financial plan for the user. Analyses all modules (protection, savings, investment, retirement, estate, goals) and returns an executive summary, top recommendations, overall score, and action plan. Use this when the user asks for a financial plan, overview of their position, or wants to know what they should prioritise."

**Parameters**: none.

**Returns**:

```php
[
    'executive_summary' => [...],
    'top_recommendations' => array_slice($ranked, 0, 5),
    'action_plan' => array_slice($plan['action_plan'], 0, 5),
    'monthly_surplus' => float,
    'suggested_allocation' => [...],
]
```

Only the first 5 recommendations and first 5 action items are returned — token-cost optimisation.

**Validation rules**: none.

**Error cases**: orchestrate failure → generic error.

**Prerequisite gate**: **real check** — `canGenerateHolisticPlan` requires data across at least a threshold of modules. If most modules are empty, the gate returns blocked with a list of missing-data modules.

**Notes**
- Internally calls `generateHolisticPlan($userId)` which fans out to every module agent.
- The description mentions "overall score" but the returned shape doesn't include one — the return is trimmed by the handler. (Scores are also explicitly banned in the user-facing UI per CLAUDE.md rule 13, so this is consistent with the product direction.)
- Expensive — can take 1–3 seconds to run on a fully-populated user. This is one of the main reasons to have `MAX_TOOL_CALLS_PER_TURN = 5`.

---

## Section B — What-if (1 tool)

---

### B.1 Tool: `create_what_if_scenario` — persist a what-if and navigate to its dashboard

| Field | Value |
|---|---|
| Category | Meta (write — but not a financial record) |
| Anthropic defn | `AiToolDefinitions.php:183` |
| xAI defn | `XaiToolDefinitions.php:228` (strict: false — dynamic `parameters` object) |
| Handler | `CoordinatingAgent::handleCreateWhatIfScenario:975` |
| Preview allowed? | No — excluded from `dataCreationTools` in preview mode |
| Side effect | **Direct write** to `what_if_scenarios` table via `WhatIfScenarioService::createScenario` |
| Yielded event | `{type: 'navigation', route_path, description}` (yes — both `action: navigate` AND `action: fill_form` are side-effect keys, and this tool uses `navigate`) |

**Description**: "Create a persistent what-if scenario showing how changes would affect the user's financial plan. The scenario is saved and the user is navigated to the What If dashboard to see the comparison. Use this when the user asks 'what if' questions about their finances."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `name` | string | yes | Short descriptive name (e.g. "Retire at 55", "Sell Main Residence") |
| `scenario_type` | string enum | yes | `retirement`, `property`, `family`, `income`, `custom` |
| `parameters` | object | yes | **Dynamic key-value object.** Keys are documented as: `retirement_age`, `pension_contribution`, `sell_property`, `buy_property`, `divorce`, `marriage`, `new_child`, `income_change`, `job_loss`, `inheritance`. On xAI this is the only tool with `additionalProperties: true` so `strict: true` cannot be set — the tool is wrapped with `strict: false` (`XaiToolDefinitions.php:252`). |
| `description` | string | yes | The model's explanation of what the scenario models and key assumptions — persisted as `ai_narrative` |

**Returns**:

```php
[
    'success' => true,
    'scenario_id' => $scenarioId,
    'comparison' => $fullResult,
    'action' => 'navigate',
    'route_path' => "/planning/what-if/{$scenarioId}",
]
```

The `action: navigate` key causes the streaming loop to yield a `navigation` SSE event — the user is taken straight to the comparison page.

**Validation rules**: none at the handler level; `WhatIfScenarioService::createScenario` has its own validation.

**Error cases**: `WhatIfScenarioService` exceptions → caught by outer try/catch → generic error message.

**Prerequisite gate**: **real check** — `canRunScenario($module, $user)` is the route taken by `canExecuteTool('run_what_if_scenario', ...)`. But this tool is named `create_what_if_scenario` not `run_what_if_scenario`, so it **actually falls through to `pass()` via the default branch** of `canExecuteTool` — the real gate is `PrerequisiteGateService::canRunScenario` and it is not reached. This is a minor defect: what-if scenarios are effectively ungated.

**Notes**
- The only tool that both writes to the database AND navigates the user in a single step.
- `created_via: 'ai_chat'` is stored on the scenario row so analytics can distinguish AI-created scenarios from manual ones.
- `ai_narrative` stores the model's own explanation of the scenario, which the frontend can surface alongside the comparison numbers.
- The dynamic `parameters` object is the reason this tool is not strict-mode — xAI rejects strict schemas that have `additionalProperties: true`.

---

## Section C — Financial record creation (9 tools)

All tools in this section return `action: 'fill_form'` and open a pre-filled modal on the client. None write directly.

---

### C.1 Tool: `create_goal` — add a financial goal

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:232` |
| xAI defn | `XaiToolDefinitions.php:274` |
| Handler | `CoordinatingAgent::handleCreateGoal:1080` |
| Preview allowed? | No — returns `previewBlocked('goal')` |
| Side effect | `fill_form` event; client opens Goal modal at `/goals` |
| Yielded event | `{type: 'fill_form', entity_type, route, fields, mode, entity_id}` |

**Description (xAI)**: "Create a new financial goal. Use when the user wants to save for something specific. **Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn.**"

**Parameters**

| Name | Type | Required | Enum / constraints |
|---|---|---|---|
| `name` | string | yes | max 255 chars, used as goal_name |
| `target_amount` | number | yes | 0–999,999,999.99 |
| `target_date` | string (date) | yes | YYYY-MM-DD, must be after today |
| `priority` | string | yes | `critical`, `high`, `medium`, `low` |
| `goal_type` | string | yes | xAI enum: `emergency_fund`, `home_deposit`, `property_purchase`, `holiday`, `education`, `wedding`, `car_purchase`, `retirement`, `wealth_accumulation`, `debt_repayment`, `custom`. Anthropic enum has slightly different names (`house_deposit` not `home_deposit`, `car` not `car_purchase`, `retirement_supplement` not `retirement`, `other` not `custom`) — **provider asymmetry worth fixing** |
| `monthly_contribution` | number (nullable) | no (Anthropic) / yes on xAI | 0–999,999.99. On xAI the model must pass `null` explicitly due to strict mode |

**Validation rules** (`validateToolInput`):

```php
'name' => 'required|string|max:255',
'target_amount' => 'required|numeric|min:0|max:999999999.99',
'target_date' => 'required|date|after:today',
'priority' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
'goal_type' => ['required', Rule::in(['emergency_fund', 'home_deposit', 'property_purchase', 'holiday', 'education', 'wedding', 'car_purchase', 'retirement', 'wealth_accumulation', 'debt_repayment', 'custom'])],
'monthly_contribution' => 'nullable|numeric|min:0|max:999999.99',
```

**Returns**:

```php
[
    'action' => 'fill_form',
    'entity_type' => 'goal',
    'route' => '/goals',
    'fields' => [
        'goal_name' => $input['name'],
        'goal_type' => $input['goal_type'],
        'target_amount' => (float) $input['target_amount'],
        'target_date' => $input['target_date'],
        'priority' => $input['priority'],
        // 'monthly_contribution' if provided
        // 'custom_goal_type_name' if goal_type === 'custom' (uses the name)
    ],
    'message' => "I'll fill in the form for your \"{$name}\" goal now.",
]
```

**Error cases**: validation failure → standard error shape. Preview user → `previewBlocked('goal')`.

**Prerequisite gate**: passes through.

**Notes**
- The "IMPORTANT: Do NOT call any other creation tools in the same turn" instruction appears in the xAI description for every creation tool. Rationale: the frontend `fill_form` handler needs the page to stay on the relevant route until the user saves. Multiple simultaneous `fill_form` events confuse the UI.
- Anthropic's description does not have the same isolation warning — Anthropic's tool catalogue is slightly older/less defensive on this front.
- Goals with `goal_type: custom` require an additional `custom_goal_type_name` field, which the handler auto-sets to the goal name. This matches the form's frontend validation.

---

### C.2 Tool: `create_life_event` — add a future financial life event

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:271` |
| xAI defn | `XaiToolDefinitions.php:288` |
| Handler | `CoordinatingAgent::handleCreateLifeEvent:1124` |
| Preview allowed? | No — `previewBlocked('life event')` |
| Side effect | `fill_form` event; client opens Life Event modal at `/goals?tab=events` |
| Yielded event | `fill_form` |

**Description**: "Create a future life event that may impact the user's financial plan." (xAI adds: "Use for expected income (inheritance, bonus, property sale) or expenses (large purchase, wedding, home improvement). Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn.")

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `event_name` (xAI) / `event_type` (Anthropic) | string | yes | **Provider asymmetry.** xAI uses `event_name` (free text) + `event_type` (enum). Anthropic uses `event_type` as free text (e.g. "marriage", "graduation") + `description` for the detail |
| `event_type` (xAI) | string enum | yes on xAI | 16 values: `inheritance`, `gift_received`, `bonus`, `redundancy_payment`, `property_sale`, `business_sale`, `pension_lump_sum`, `lottery_windfall`, `custom_income`, `large_purchase`, `home_improvement`, `wedding`, `education_fees`, `gift_given`, `medical_expense`, `custom_expense` |
| `event_date` | string (date) | yes | YYYY-MM-DD, must be future (xAI description says so, but handler validation is just `'date'` — **gap: does not enforce future date in the validator**) |
| `estimated_amount` | number | yes | 0–999,999,999.99 |
| `certainty` | string | no | `confirmed`, `likely`, `possible`, `speculative` — defaults to `likely` |
| `description` | string | no | max 500 chars |

**Validation rules**: see above. Note that `event_date` is validated as `'required|date'` only — the "must be future" rule is prompt-level, not code-level.

**Returns**: standard `fill_form` shape targeting `/goals?tab=events` with the form fields mapped into the frontend's expected shape (`amount` not `estimated_amount`, `expected_date` not `event_date`).

**Error cases**: validation failure; preview.

**Prerequisite gate**: passes through.

**Notes**
- Anthropic and xAI use **different parameter shapes** here. The Anthropic version is simpler (4 fields, free-text event_type). The xAI version is richer (16 typed event_type enum values + event_name + certainty). This means model quality for life events is higher on xAI than Anthropic — a latent product asymmetry.
- The frontend Life Event form uses `amount` (not `estimated_amount`) and `expected_date` (not `event_date`) — the handler does the field mapping.

---

### C.3 Tool: `create_savings_account` — add a cash/savings account

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:305` |
| xAI defn | `XaiToolDefinitions.php:312` |
| Handler | `CoordinatingAgent::handleCreateSavingsAccount:1163` |
| Preview allowed? | No — `previewBlocked('savings account')` |
| Side effect | `fill_form` at `/net-worth/cash` |
| Yielded event | `fill_form` |

**Description**: "Create a savings account for the user. Use this when the user mentions a savings account, Cash Individual Savings Account, or cash deposit."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `account_name` | string | yes | e.g. "Nationwide Cash ISA" |
| `account_type` | string | no | `easy_access`, `notice`, `fixed_term`, `regular_saver`. Default: `easy_access` |
| `institution` | string | no | Bank/building society name |
| `current_balance` | number | yes | 0–999,999,999.99 |
| `interest_rate` | number | no | Annual percentage |
| `is_isa` | boolean | no | Default false. If true, the form will also collect `isa_type` and `isa_subscription_amount` |
| `is_emergency_fund` | boolean | no | Default false |
| `regular_contribution_amount` | number | no | Monthly top-up |

**Validation rules**: account_name required, current_balance required numeric 0–999,999,999.99, interest_rate nullable numeric 0–20, remaining fields nullable with sensible type checks.

**Returns**: `fill_form` shape targeting `/net-worth/cash` with fields mapped into the Savings form's expected shape.

**Error cases**: validation; preview.

**Prerequisite gate**: passes through.

**Notes**
- If `is_isa: true`, the frontend form automatically requires `isa_type` and `isa_subscription_amount` before allowing save — but these are not in the tool schema, so the model cannot pre-fill them. The user must fill them in manually in the modal. This is a minor UX asymmetry.
- `ownership_type` is not a parameter — savings accounts default to `individual`. If the user says "mine and my wife's joint savings", the model cannot capture that via this tool; it would need to use `update_record` after the initial save.

---

### C.4 Tool: `create_investment_account` — add an investment account (ISA, GIA, VCT, EIS, share schemes, private co, etc.)

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:349` |
| xAI defn | `XaiToolDefinitions.php:332` |
| Handler | `CoordinatingAgent::handleCreateInvestmentAccount:1220` |
| Preview allowed? | No — `previewBlocked('investment account')` |
| Side effect | `fill_form` at `/net-worth/investments` |
| Yielded event | `fill_form` |

**Description**: "Create an investment account for the user. Use this when the user mentions any investment: ISA, GIA, bond, VCT, EIS, private company shares, crowdfunding, employee share schemes (SAYE, CSOP, EMI, share options, RSUs), or other investments."

**Parameters**: the largest tool schema in the catalogue — 40+ parameters. Grouped:

- **Core** (required): `account_name`, `current_value`
- **Optional basic**: `account_type` (15 enum values), `provider`, `monthly_contribution_amount`, `platform_fee_percent`
- **Bond-specific** (onshore/offshore): `bond_purchase_date`, `bond_withdrawal_taken`
- **Private company / crowdfunding**: `company_legal_name`, `company_registration_number`, `crowdfunding_platform`, `investment_date`, `investment_amount`, `number_of_shares`, `price_per_share`, `instrument_type`, `funding_round`, `share_class`, `tax_relief_type`
- **Employee share schemes** (saye, csop, emi, unapproved_options, rsu): `employer_name`, `employer_is_listed`, `grant_date`, `units_granted`, `exercise_price`, `market_value_at_grant`, `current_share_price`, `units_vested`, `units_unvested`, `vesting_type`, `full_vest_date`, `cliff_date`, `cliff_percentage`
- **SAYE-specific**: `saye_monthly_savings`, `saye_current_savings_balance`, `scheme_start_date`, `scheme_duration_months` (36 or 60)

**account_type enum** (15 values): `stocks_shares_isa`, `lifetime_isa`, `personal_investment_account`, `onshore_bond`, `offshore_bond`, `vct`, `eis`, `private_company`, `crowdfunding`, `saye`, `csop`, `emi`, `unapproved_options`, `rsu`, `other`.

**Validation rules**: extensive — every nullable numeric validated 0–max, every enum validated against the Rule::in list, dates validated as `nullable|date`. Full list is ~40 rules in the handler.

**Returns**: `fill_form` at `/net-worth/investments` with fields mapped into the Investment form's expected shape. The form is aware of all 15 account types and shows a different set of fields per type — the tool schema matches this union.

**Error cases**: validation; preview; unknown account_type.

**Prerequisite gate**: passes through.

**Notes**
- This is the most complex schema — the Investment form itself has ~164 fillable fields on the model, and this tool schema is the biggest attempt to map the natural-language input surface to that form.
- The parameters for the various enum values are all conditional — `company_legal_name` only applies to `private_company` or `crowdfunding`, `saye_monthly_savings` only applies to `saye`. The description text tells the model this; the schema itself does not enforce it (all fields are on one flat object).
- The xAI version has `holdings` as an additional first-class parameter (from the surrounding pattern but I did not verify this in detail). This is why `create_holding` exists as a separate xAI-only tool — for adding holdings to an existing account after initial creation.

---

### C.5 Tool: `create_holding` — xAI-only — add a holding to an existing investment account

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | **not exposed** |
| xAI defn | `XaiToolDefinitions.php:426` |
| Handler | `CoordinatingAgent::handleCreateHolding:1356` |
| Preview allowed? | No |
| Side effect | `fill_form` at `/net-worth/investments` |
| Yielded event | `fill_form` |

**Description**: "Add a holding to an EXISTING investment account that was already created WITHOUT holdings. Use this ONLY when the user wants to add holdings to an account that already exists and has no holdings. If the user is creating a NEW account AND mentions holdings at the same time, use `create_investment_account` with the holdings parameter instead."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `account_name` | string | yes | Must match an existing account (fuzzy match at handler) |
| `security_name` | string | yes | Fund/ETF/share name |
| `ticker` | string \| null | yes (strict mode) | e.g. "VWRL", "SWDA" |
| `asset_type` | string enum | yes | `uk_equity`, `us_equity`, `international_equity`, `fund`, `etf`, `bond`, `cash`, `alternative`, `property` |
| `allocation_percent` | number \| null | yes | 0–100 |
| `purchase_price` | number \| null | yes | £ per unit |
| `current_price` | number \| null | yes | £ per unit |
| `ocf_percent` | number \| null | yes | OCF % |

**Validation rules**: per handler.

**Returns**: `fill_form` at `/net-worth/investments` targeting the holding modal within the existing account.

**Error cases**: if `account_name` doesn't fuzzy-match any existing account → `{error: true, message: "Account '...' not found"}`.

**Prerequisite gate**: passes through.

**Notes**
- **Parity gap**: Anthropic-side conversations cannot add holdings via Fyn, only via the manual UI. The `handleCreateHolding` method exists and would execute if somehow called, but it is not in the Anthropic tool catalogue.
- The "xAI-only" nature is probably an oversight — this tool was added after the Anthropic definitions were frozen.

---

### C.6 Tool: `create_pension` — add a DC or DB pension

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:520` |
| xAI defn | `XaiToolDefinitions.php:447` |
| Handler | `CoordinatingAgent::handleCreatePension:1423` |
| Preview allowed? | No — `previewBlocked('pension')` |
| Side effect | `fill_form` at `/net-worth/retirement` |
| Yielded event | `fill_form` |

**Description**: "Create a pension for the user. Handles both Defined Contribution (workplace, Self-Invested Personal Pension, personal) and Defined Benefit (final salary, career average) pensions."

xAI adds: "Call this tool IMMEDIATELY when the user mentions a pension. Fill in every field you can. IMPORTANT: Do NOT call any other creation tools in the same turn as create_pension. If the user mentions a pension without specifying DC or DB, ask: 'Is this a workplace pension where your employer contributes, or a final salary/career average scheme?'"

**Parameters**

| Name | Type | Required | Applies to |
|---|---|---|---|
| `pension_category` | string enum (`dc`, `db`) | yes | Both |
| `scheme_name` | string | yes | Both |
| `scheme_type` | string | no | DC: `workplace`, `sipp`, `personal_pension`, `stakeholder`. DB: `final_salary`, `career_average`, `public_sector` |
| `provider` | string | no | DC only |
| `current_fund_value` | number | no | DC only |
| `employee_contribution_percent` | number | no | DC workplace only |
| `employer_contribution_percent` | number | no | DC workplace only |
| `annual_salary` | number | xAI only | DC workplace only — needed to compute contribution amounts |
| `monthly_contribution_amount` | number | xAI only | DC personal/SIPP only |
| `retirement_age` | integer | xAI only | DC personal/SIPP only — min 55 |
| `accrued_annual_pension` | number | no | DB only |
| `pensionable_service_years` | number | no | DB only |
| `normal_retirement_age` | integer | no | DB only |
| `scheme_status` | string | xAI only | DB only: `Active`, `Deferred`, `In Payment`. Default `Active` |
| `final_salary` | number | xAI only | DB only |
| `accrual_rate` | integer | xAI only | DB only — denominator (60 = 1/60th). Common: 60 public, 80 older schemes |

**Provider asymmetry**: the xAI schema has significantly more fields (annual_salary, monthly_contribution_amount, retirement_age, scheme_status, final_salary, accrual_rate). The Anthropic schema is the minimal viable set. Again, this is a latent gap — Anthropic users get a less-rich pension creation experience.

**Validation rules**: per handler. `pension_category` is `['required', Rule::in(['dc', 'db'])]`.

**Returns**: `fill_form` at `/net-worth/retirement` with the fields mapped into the DC or DB form (whichever matches `pension_category`).

**Error cases**: validation; preview; unknown category.

**Prerequisite gate**: passes through.

**Notes**
- The form has separate tabs for DC and DB on the frontend; `pension_category` determines which tab opens.
- The "ask before guessing" instruction in the xAI description is important because a user saying "I have a pension" with no further context is ambiguous, and the financial implications of misclassifying are significant.

---

### C.7 Tool: `create_property` — add a property, optionally with mortgage

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:578` |
| xAI defn | `XaiToolDefinitions.php:489` |
| Handler | `CoordinatingAgent::handleCreateProperty:1501` |
| Preview allowed? | No — `previewBlocked('property')` |
| Side effect | `fill_form` at `/net-worth/property` — may include mortgage fields |
| Yielded event | `fill_form` |

**Description (xAI)**: "Create a property record and optionally a linked mortgage. Call this tool IMMEDIATELY when the user mentions a property — do not ask questions first. Fill in every field you can from what the user said and set null for anything not mentioned. The form will be opened, filled, and saved automatically. After saving, confirm what was added and ask if they want to update any details (postcode, monthly costs, etc.) or add another property. Infer sensible values: if they say 'my house' assume main_residence, if they say 'our house' assume joint ownership. IMPORTANT: Do NOT call any other creation tools (create_family_member, navigate_to_page, etc.) in the same turn as create_property. The property form fill needs the page to stay on /net-worth/property until saved. Add family members in a follow-up message."

**Parameters** — the xAI schema is substantially richer than Anthropic's. xAI has ~30 parameters:

- **Basic**: `property_type`, `current_value`
- **Address**: `address_line_1`, `address_line_2`, `city`, `county`, `postcode`
- **Purchase**: `purchase_price`, `purchase_date`, `valuation_date`
- **Ownership**: `ownership_type`, `ownership_percentage`, `joint_owner_name`
- **Tenure**: `tenure_type` (`freehold`/`leasehold`), `lease_remaining_years`, `lease_expiry_date`
- **Mortgage**: `has_mortgage` (bool), `mortgage_outstanding_balance`, `mortgage_interest_rate`, `mortgage_lender` (xAI) / `mortgage_lender_name`, `mortgage_type`, `mortgage_rate_type`, `mortgage_monthly_payment`, `mortgage_start_date`, `mortgage_maturity_date`
- **Monthly costs**: `monthly_council_tax`, `monthly_gas`, `monthly_electricity`, `monthly_water`, `monthly_building_insurance`, `monthly_contents_insurance`, `monthly_service_charge`, `monthly_maintenance_reserve`, `other_monthly_costs`
- **Buy-to-let**: `monthly_rental_income`, `tenant_name`, `managing_agent_name`

Anthropic exposes only: `property_type`, `current_value`, `purchase_price`, `purchase_date`, `address_line_1`, `postcode`, `outstanding_mortgage`, `mortgage_rate`, `mortgage_lender`, `monthly_rental_income` — a much smaller set.

**Handler dual-mode** (`handleCreateProperty:1597-1618`): the handler accepts both schemas — xAI's `has_mortgage` + `mortgage_outstanding_balance` and Anthropic's legacy `outstanding_mortgage` — and normalises them into the frontend form's expected shape. This is how a single handler serves both providers despite the schema asymmetry.

**Validation rules**: comprehensive — ~22 rules covering all the optional fields (`CoordinatingAgent.php:1507-1534`).

**Auto-defaults** (`CoordinatingAgent.php:1544-1557`):
- `address_line_1` defaults to the property type description
- `city` defaults to `'Unknown'`
- `postcode` defaults to `'N/A'`
- `ownership_type` defaults to `'individual'`
- `ownership_percentage` auto-set based on ownership type: individual=100, joint=50, tenants_in_common=50, trust=0

Rationale: the frontend form's `validateForm()` requires these fields to be non-empty, and the AI may not have collected them from the user. The handler fills in safe defaults rather than failing validation.

**Returns**: `fill_form` at `/net-worth/property` with the full resolved fields dict. Nulls and empty strings are stripped before returning.

**Error cases**: validation; preview.

**Prerequisite gate**: passes through.

**Notes**
- **The biggest parity gap in the catalogue.** xAI gets 30 fields of richness; Anthropic gets 10. A user on Anthropic cannot have Fyn pre-fill monthly costs, council tax, building insurance, etc.
- The "do not call other creation tools in the same turn" is the most emphatic in the xAI description. Property fills are fragile because the frontend has to stay on the page.
- If a user says "we own our house 50/50 with a £200k mortgage", the model on xAI can produce a single tool call that creates the property + mortgage + joint ownership record in one shot. The Anthropic equivalent is worse.

---

### C.8 Tool: `create_mortgage` — add a mortgage to an existing property

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:631` |
| xAI defn | `XaiToolDefinitions.php:580` (approx) |
| Handler | `CoordinatingAgent::handleCreateMortgage:1632` |
| Preview allowed? | No |
| Side effect | `fill_form` at `/net-worth/property` |
| Yielded event | `fill_form` |

**Description**: "Create a standalone mortgage linked to an existing property. Use this when the user mentions a mortgage separately from a property, or wants to add a mortgage to an existing property."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `property_address_hint` | string | no | Fuzzy-matched to existing properties (address, postcode, "my main home") |
| `lender_name` | string | no | e.g. "Halifax" |
| `outstanding_balance` | number | yes | 0–999,999,999.99 |
| `interest_rate` | number | no | 0–25% |
| `mortgage_type` | string | no | `repayment`, `interest_only`, `mixed`. Default `repayment` |
| `rate_type` | string | no | `fixed`, `variable`, `tracker`. Default `fixed` |
| `monthly_payment` | number | no | |
| `remaining_term_months` | integer | no | 1–480 |

**Validation rules**: per handler.

**Returns**: `fill_form` targeting the mortgage section of the Property form, with defaults filled in (`interest_rate` → 4.5% if not supplied, `remaining_term_months` → 300 if not supplied). **Note**: this defaulting could produce misleading form pre-fill — a user who says "I have a mortgage" and the model omits the rate will see the form default to 4.5% which is a fabricated number. Worth flagging.

**Error cases**: validation; preview. If `property_address_hint` fails to match any property, `resolvePropertyId` returns null and the handler falls back to using the user's primary property.

**Prerequisite gate**: passes through.

**Notes**
- `resolvePropertyId` (`CoordinatingAgent.php:2023`) does a fuzzy match: exact postcode > exact address substring > "main" keyword → first main_residence > fallback to first property.
- This tool is for the edge case where the user already has a property record (created elsewhere) and now wants to add a mortgage. For new properties, `create_property.has_mortgage` is the preferred path.

---

### C.9 Tool: `create_protection_policy` — life / critical illness / income protection

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:682` |
| xAI defn | `XaiToolDefinitions.php:641` (approx) |
| Handler | `CoordinatingAgent::handleCreateProtectionPolicy:1679` |
| Preview allowed? | No |
| Side effect | `fill_form` at `/protection` |
| Yielded event | `fill_form` |

**Description**: "Create a protection insurance policy for the user. Handles life insurance, critical illness cover, and income protection policies."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `policy_type` | string enum | yes | `level_term`, `term`, `whole_of_life`, `decreasing_term`, `family_income_benefit`, `standalone_ci`, `accelerated_ci`, `income_protection` |
| `provider` | string | no | |
| `sum_assured` | number | no | Life/CI only |
| `benefit_amount` | number | no | Income protection monthly benefit only |
| `premium_amount` | number | no | |
| `premium_frequency` | string | no | `monthly`, `annually`. Default `monthly` |
| `policy_term_years` | integer | no | Not applicable for whole_of_life |
| `in_trust` | boolean | no | Default false |

**Returns**: `fill_form` at `/protection`. The handler routes the fields to one of three form variants based on `policy_type`:
- life variants (`level_term`, `term`, `whole_of_life`, `decreasing_term`, `family_income_benefit`) → life insurance form
- CI variants (`standalone_ci`, `accelerated_ci`) → critical illness form
- `income_protection` → income protection form

**Error cases**: validation; preview; unknown policy_type.

**Prerequisite gate**: passes through.

**Notes**
- The model has to pick the correct `policy_type` variant. The description gives it a guidance block. Ambiguous inputs like "I have life cover" will typically map to `level_term`.
- `sum_assured` vs `benefit_amount` are mutually exclusive — the model must use the right one. No handler validation enforces "exactly one of".

---

## Section D — Estate tools (3 tools)

---

### D.1 Tool: `create_asset` — add an estate asset (catch-all)

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:733` |
| xAI defn | `XaiToolDefinitions.php:` (approx 746) |
| Handler | `CoordinatingAgent::handleCreateEstateAsset:1746` |
| Preview allowed? | No |
| Side effect | `fill_form` at `/estate` |
| Yielded event | `fill_form` |

**Description**: "Create an asset. Use this for assets not covered by other tools — such as collectibles, artwork, or other valuable items the user wants to track."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `asset_name` | string | yes | |
| `asset_type` | string enum | yes | `property`, `pension`, `investment`, `business`, `other` |
| `current_value` | number | yes | |
| `is_iht_exempt` | boolean | no | Default false |
| `exemption_reason` | string | no | e.g. "Business Property Relief" |

**Returns**: `fill_form` targeting the Estate form.

**Notes**
- This is a **catch-all** — most real-world assets map to `create_property`, `create_investment_account`, `create_business_interest`, or `create_chattel`, which are more specific. `create_asset` is the fallback.
- The enum values `property`, `pension`, `investment`, `business` overlap with dedicated tools. This is a legacy entry point.

---

### D.2 Tool: `create_liability` — add a debt/liability

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:765` |
| xAI defn | `XaiToolDefinitions.php:` (approx 788) |
| Handler | `CoordinatingAgent::handleCreateEstateLiability:1779` |
| Preview allowed? | No |
| Side effect | `fill_form` at `/net-worth/liabilities` |
| Yielded event | `fill_form` |

**Description**: "Create a liability. Use this when the user mentions any debt: credit cards, personal loans, student loans, car finance, or any other outstanding balance owed."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `liability_name` | string | yes | |
| `liability_type` | string enum | yes | `loan`, `personal_loan`, `credit_card`, `mortgage`, `student_loan`, `other`. (Note: `mortgage` is a valid enum value but mortgages should go through `create_mortgage`/`create_property` instead.) |
| `current_balance` | number | yes | |
| `monthly_payment` | number | no | |
| `interest_rate` | number | no | % |

**Returns**: `fill_form` at `/net-worth/liabilities`.

**Notes**
- Student loans should be flagged as `is_priority_debt: false` by default but the tool doesn't have this as a parameter. The frontend form defaults it.

---

### D.3 Tool: `create_estate_gift` — record a gift for Inheritance Tax planning

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:797` |
| xAI defn | `XaiToolDefinitions.php:` (approx 805) |
| Handler | `CoordinatingAgent::handleCreateEstateGift:1819` |
| Preview allowed? | No |
| Side effect | `fill_form` at `/estate` |
| Yielded event | `fill_form` |

**Description**: "Record a gift for Inheritance Tax planning. Use this when the user mentions gifts they have made or plan to make, as these affect their Inheritance Tax position under the 7-year rule."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `gift_date` | string (date) | yes | YYYY-MM-DD |
| `recipient` | string | yes | |
| `gift_type` | string enum | yes | `pet` (Potentially Exempt Transfer), `clt` (Chargeable Lifetime Transfer — gifts to trusts), `exempt` (spouse/charity), `small_gift` (up to £250 per recipient), `annual_exemption` (up to £3,000/yr) |
| `gift_value` | number | yes | |
| `notes` | string | no | |

**Returns**: `fill_form`.

**Notes**
- The enum names (`pet`, `clt`) are UK tax terminology. The description explains them inline so the model can pick the right classification.
- The 7-year rule is mentioned in the description but not enforced — any past gift date is accepted.

---

## Section E — Profile / meta (5 tools)

---

### E.1 Tool: `create_family_member` — add a spouse, child, or dependent

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:836` |
| xAI defn | `XaiToolDefinitions.php:746` |
| Handler | `CoordinatingAgent::handleCreateFamilyMember:2121` |
| Preview allowed? | No |
| Side effect | `fill_form` at `/profile` |
| Yielded event | `fill_form` |

**Description (xAI)**: "Add a family member. Use when the user mentions children, parents, step-children, dependents, or partners. **For spouse: only use if the user explicitly asks to add their spouse** — the system may already have a linked spouse account. Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn. For multiple children, call this tool ONCE per child in separate turns."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `first_name` | string | yes | |
| `surname` | string | no on xAI (nullable) | Defaults to user's surname if not given |
| `relationship` | string enum | yes | `spouse`, `partner`, `child`, `step_child`, `parent`, `other_dependent`. (Anthropic uses `other` not `other_dependent` — **provider asymmetry**.) |
| `date_of_birth` | string (date) | no | Validated: spouse must be 16+, child max 18 (or 22 if in education) |
| `gender` | string enum | no | `male`, `female`, `other`, `prefer_not_to_say` |
| `is_dependent` | boolean | no | Default true for child/step_child/other_dependent |
| `education_status` (xAI only) | string enum | no | `pre_school`, `primary`, `secondary`, `further_education`, `higher_education`, `graduated`, `not_applicable`. Child-only |
| `receives_child_benefit` (xAI only) | boolean | no | Child-only |
| `notes` (xAI only) | string | no | |

**Returns**: `fill_form` at `/profile`.

**Notes**
- The "For spouse: only use if explicitly asked" instruction exists because Fynla has a linked-spouse-account feature — a spouse with their own Fynla login becomes the `users.spouse_id` link, and creating a separate `family_members` row for them would duplicate the record. The model has to be told this.
- "ONCE per child in separate turns" is an interaction design choice — multiple simultaneous `fill_form` events would confuse the UI.

---

### E.2 Tool: `create_trust` — record a trust

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:853` |
| xAI defn | `XaiToolDefinitions.php:772` |
| Handler | `CoordinatingAgent::handleCreateTrust:2220` |
| Preview allowed? | No |
| Side effect | `fill_form` at `/trusts` |
| Yielded event | `fill_form` |

**Description**: "Record a trust for estate planning. Use for discretionary trusts, bare trusts, life insurance trusts, loan trusts, discounted gift trusts, interest in possession trusts, and other UK trust types."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `trust_name` | string | yes | |
| `trust_type` | string enum | yes | xAI: `discretionary`, `bare`, `interest_in_possession`, `life_insurance`, `loan`, `discounted_gift`, `accumulation_maintenance`, `mixed`, `settlor_interested`. Anthropic has a subset. **Provider asymmetry**. |
| `initial_value` (xAI only) | number | no | Original amount settled |
| `current_value` | number | no | |
| `trust_creation_date` | string (date) | no | (Anthropic uses `date_established`) |
| `beneficiaries` (xAI only) | string | no | Comma-separated |
| `trustees` (xAI only) | string | no | Comma-separated |
| `purpose` (xAI only) | string | no | |
| `settlor` (Anthropic only) | string | no | |

**Notes**
- Trusts are the most asymmetric entity between the two providers — the xAI schema has 5 extra fields that the Anthropic schema does not.
- `settlor_interested` trusts have specific UK tax implications (the settlor is taxed on trust income); the xAI description flags this.

---

### E.3 Tool: `create_business_interest` — record a business ownership

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:869` |
| xAI defn | `XaiToolDefinitions.php:788` (approx) |
| Handler | `CoordinatingAgent::handleCreateBusinessInterest:2299` |
| Preview allowed? | No |
| Side effect | `fill_form` at `/net-worth/business` |
| Yielded event | `fill_form` |

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `business_name` | string | yes | |
| `business_type` | string enum | yes | `sole_trader`, `partnership`, `limited_company`, `llp` |
| `ownership_percentage` | number | no | 0-100 |
| `estimated_value` | number | no | Stored as `current_valuation` on the model (field alias in `handleUpdateRecord`) |
| `annual_profit` | number | no | |

**Notes**
- Business Property Relief (BPR) eligibility affects IHT planning — the tool exposes `bpr_eligible` via the form after `fill_form`, but this is not a tool parameter (the user sets it in the modal).

---

### E.4 Tool: `create_chattel` — record a valuable possession

| Field | Value |
|---|---|
| Category | Data creation (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:885` |
| xAI defn | `XaiToolDefinitions.php:805` (approx) |
| Handler | `CoordinatingAgent::handleCreateChattel:2354` |
| Preview allowed? | No |
| Side effect | `fill_form` at `/net-worth/chattels` |
| Yielded event | `fill_form` |

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `description` | string | yes | |
| `category` | string enum | yes | `jewellery`, `art`, `antiques`, `collectibles`, `vehicles`, `other`. Stored as `chattel_type` on the model. |
| `estimated_value` | number | yes | Stored as `current_value`. |
| `purchase_value` | number | no | |
| `is_insured` | boolean | no | |

**Notes**
- Chattels over £6,000 have CGT implications — not enforced at tool level.

---

### E.5 Tool: `set_expenditure` — xAI-only — set monthly expenditure by category

| Field | Value |
|---|---|
| Category | Data modification (assistive write) |
| Anthropic defn | **not exposed** |
| xAI defn | `XaiToolDefinitions.php:695` |
| Handler | `CoordinatingAgent::handleSetExpenditure:2397` |
| Preview allowed? | No |
| Side effect | `fill_form` at `/valuable-info?section=expenditure` |
| Yielded event | `fill_form` |

**Description**: "Set the user's monthly expenditure by category. Call this IMMEDIATELY when the user mentions their spending, bills, or monthly outgoings. Fill in every category the user mentions and set null for anything not mentioned."

**Parameters**: 21 monthly expense categories, all nullable numbers:

- Essential: `rent`, `utilities`, `food_groceries`, `transport_fuel`, `healthcare_medical`, `insurance`
- Communication: `mobile_phones`, `internet_tv`, `subscriptions`
- Personal: `clothing_personal_care`, `entertainment_dining`, `holidays_travel`, `pets`
- Children: `childcare`, `school_fees`, `school_lunches`, `school_extras`, `university_fees`, `children_activities`
- Other: `gifts_charity`, `charitable_donations`, `other_expenditure`

**Validation rules**: all nullable numeric 0–some-max.

**Returns**: `fill_form` at the expenditure page with the 21-category form pre-filled.

**Notes**
- **xAI-only by schema exposure, but reachable from Anthropic** — `handleUpdateProfile` redirects to `handleSetExpenditure` when `section=expenditure`, so an Anthropic model calling `update_profile(section: expenditure, fields: {...})` gets routed to the same handler. This is a partial mitigation for the parity gap.
- The handler is not covered by the `[AI-AUDIT]` audit log prefix regex — `set_expenditure` doesn't start with `create_` and isn't in the audit allow-list. Minor gap.

---

## Section F — Modification tools (3 tools)

---

### F.1 Tool: `update_record` — polymorphic update of any financial record

| Field | Value |
|---|---|
| Category | Data modification (assistive write) |
| Anthropic defn | `AiToolDefinitions.php:907` |
| xAI defn | `XaiToolDefinitions.php:825` (approx) |
| Handler | `CoordinatingAgent::handleUpdateRecord:2451` |
| Preview allowed? | No — `previewBlocked('record')` |
| Side effect | `fill_form` with `mode: 'edit'` + `entity_id` at the route for the entity type |
| Yielded event | `fill_form` |

**Description**: "Update an existing record. Use when the user wants to change details of an existing goal, account, property, pension, policy, or other financial record. **Ask the user to confirm the changes before calling this tool.**"

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `entity_type` | string enum | yes | 18 entity types (see below) |
| `entity_id` | integer | yes | Must belong to the caller |
| `fields` | object | yes | **Dynamic key-value map of fields to update.** `additionalProperties: true` |

**Entity types**: `goal`, `life_event`, `savings_account`, `investment_account`, `dc_pension`, `db_pension`, `property`, `mortgage`, `life_insurance`, `critical_illness`, `income_protection`, `estate_asset`, `estate_liability`, `estate_gift`, `family_member`, `trust`, `business_interest`, `chattel`.

**Handler flow** (`handleUpdateRecord:2451`):

1. Check preview → `previewBlocked`.
2. Validate `fields` not empty → `validation_failed` if empty.
3. `resolveModel($entityType, $entityId, $user->id)` — fetches the model scoped to the user. If not found → `{error: true, error_type: 'not_found', message: '... not found or does not belong to you.'}`.
4. **Apply field aliases** — per-entity mapping of AI-facing field names to model column names:
   - `business_interest.estimated_value` → `current_valuation`
   - `chattel.estimated_value` → `current_value`
   - `chattel.category` → `chattel_type`
   - `dc_pension.current_value` → `current_fund_value`
   - `mortgage.current_balance` → `outstanding_balance`
   - `life_insurance.life_policy_type` → `policy_type`
   - `life_insurance.monthly_premium` → `premium_amount`
5. **Intersect with `$model->getFillable()`** — only allow updating fillable fields. Non-fillable fields are silently dropped.
6. **Explicitly unset `user_id` and `id`** — even if they were in `getFillable()`.
7. If no fields remain after filtering → `validation_failed`.
8. Return `fill_form` with `mode: edit` + the filtered fields + the entity_id + the route for the entity type.

**Cross-user protection**: three layers — (1) `resolveModel` filters by `user_id`, (2) `fillable` intersection prevents writes to user_id/id, (3) the frontend REST endpoint that receives the form save also enforces authorisation.

**Error cases**

| Condition | error_type |
|---|---|
| Preview user | N/A — returns `previewBlocked` shape |
| `fields` empty | `validation_failed` |
| Model not found or not owned | `not_found` |
| Unknown entity_type | `invalid_entity` |
| Fields filter results in empty set | `validation_failed` |

**Notes**
- **This is the only tool that dynamically routes to the correct form modal.** `getRouteForEntityType` maps each entity type to its correct URL so the client knows where to open the edit modal.
- `strict: false` on the xAI side because of `fields.additionalProperties: true` (same reason as `create_what_if_scenario`).
- The AI-facing field names generally match the frontend form field names, but a few mappings are needed where they drifted. These aliases accumulate as the model evolves.
- **Sensitive fields are implicitly filtered** by the fillable intersection. For example, `users.national_insurance_number` is not in the `FamilyMember` fillable list, so the model cannot update a family member's NI number via this tool. For writes to the `users` table itself, `update_profile` has an explicit allow-list that excludes NI number.

---

### F.2 Tool: `delete_record` — hard-delete a record

| Field | Value |
|---|---|
| Category | Data modification (direct write) |
| Anthropic defn | `AiToolDefinitions.php:929` |
| xAI defn | `XaiToolDefinitions.php:844` (approx) |
| Handler | `CoordinatingAgent::handleDeleteRecord:2532` |
| Preview allowed? | No |
| Side effect | **Direct DB delete** via `$model->delete()` |
| Yielded event | None (tool_result only) |

**Description**: "Delete an existing record. **ALWAYS confirm with the user before deleting.** Use when the user explicitly asks to remove a goal, account, property, pension, policy, or other financial record."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `entity_type` | string enum | yes | Same 18 types as `update_record` |
| `entity_id` | integer | yes | |

**Handler flow**

1. Preview check.
2. `resolveModel($entityType, $entityId, $user->id)` — scoped to user, 404 if not owned.
3. Extract a human name from the model for the confirmation message (`goal_name`, `account_name`, `trust_name`, `business_name`, `description`, `first_name`, fallback to `#{id}`).
4. `$model->delete()` — this is the one place in the AI path that directly mutates the database.
5. Return `{deleted: true, entity_type, entity_id, message: 'X "Name" deleted.'}`.

**Soft-delete handling**: models that use `SoftDeletes` trait (e.g. `AiConversation`) will be soft-deleted; others are hard-deleted. Most financial records in Fynla are hard-deleted — `SavingsAccount`, `InvestmentAccount`, `Property` etc. do not use soft deletes (I did not verify this exhaustively, but the pattern in the codebase for financial records is hard delete + audit log via observers).

**Cross-user protection**: same three layers as `update_record`.

**Error cases**

| Condition | error_type |
|---|---|
| Preview | previewBlocked |
| Model not found / not owned | `not_found` |
| Unknown entity_type | `invalid_entity` |
| Model delete throws | caught by outer try/catch → `execution_failed` |

**Notes**
- **No undo.** Once the LLM calls this and the handler runs, the row is gone (or soft-deleted, for models that support it). The only mitigation is the "ALWAYS confirm with the user before deleting" prompt instruction — which is instruction-level, not code-level.
- **A cautious design choice** would be to route deletes through `fill_form` with `mode: delete` and require the user to click a confirmation in the UI before the REST endpoint actually deletes. Today this is not done; the AI can issue a delete that executes immediately.
- **The `[AI-AUDIT]` log fires** on delete (`delete_record` is in the audit allow-list), so the second-layer audit trail is present.
- Joint assets: the handler does not check whether the record is jointly owned. A user can delete their half of a joint savings account via this tool — which would remove the record entirely, including the spouse's half. This is consistent with the existing REST API behaviour but is not obvious from the tool description.

---

### F.3 Tool: `update_profile` — update user profile (personal, income, expenditure, domicile)

| Field | Value |
|---|---|
| Category | Data modification (direct write) |
| Anthropic defn | `AiToolDefinitions.php:952` |
| xAI defn | `XaiToolDefinitions.php:865` (approx) |
| Handler | `CoordinatingAgent::handleUpdateProfile:2595` |
| Preview allowed? | No |
| Side effect | **Direct update** on the `users` row |
| Yielded event | None (tool_result only) |

**Description**: "Update the user's profile information (personal details, income, expenditure, or domicile). Use when the user provides personal information like their age, income, spending, marital status, or address. Ask clarifying questions if needed to gather required fields."

**Parameters**

| Name | Type | Required | Notes |
|---|---|---|---|
| `section` | string enum | yes | `personal`, `income_occupation`, `expenditure`, `domicile` |
| `fields` | object | yes | `additionalProperties: true`. Valid keys depend on section |

**Section allow-lists** (`handleUpdateProfile:2613-2620`):

```php
'personal' => ['first_name', 'surname', 'date_of_birth', 'gender', 'marital_status', 'phone', 'address_line_1', 'address_line_2', 'city', 'county', 'postcode'],
'income_occupation' => ['employment_status', 'occupation', 'employer', 'industry', 'annual_employment_income', 'annual_self_employment_income', 'annual_rental_income', 'annual_dividend_income', 'annual_other_income', 'target_retirement_age'],
'expenditure' => ['monthly_expenditure', 'annual_expenditure', 'expenditure_entry_mode'],
'domicile' => ['country_of_birth', 'uk_arrival_date', 'domicile_status'],
```

**Explicitly excluded** (comment at `CoordinatingAgent.php:2614`): `national_insurance_number`. Rationale: sensitive PII should not be AI-writable.

**Handler flow**

1. Preview check.
2. `expenditure` section is redirected to `handleSetExpenditure` (this is how Anthropic reaches the xAI-only expenditure tool).
3. `fields` not empty check.
4. Look up allowed fields for the section.
5. `array_intersect_key` — drop any field not on the allow-list.
6. If any remain, update the user row directly; otherwise return validation_failed.

**Cross-user protection**: the handler mutates `$user` (the caller's user object) directly — no way to target a different user.

**Error cases**

| Condition | error_type |
|---|---|
| Preview | previewBlocked |
| `fields` empty | `validation_failed` |
| Unknown section | `validation_failed` |
| All fields filtered out | `validation_failed` |

**Notes**
- **This is the second tool that writes directly, not via fill_form.** Profile fields don't have a "form modal" in the same sense — they're edited inline on the profile page. The direct write is acceptable but does skip the UI confirmation step that record creates enjoy.
- NI number exclusion is a meaningful PII carve-out. The same pattern should probably apply to `date_of_birth`, `address_line_1`, `postcode` (all of which are on the allow-list) — these are updatable by the AI today, which is a potential social-engineering vector ("update my DOB to ..."). This is a design trade-off: the AI needs to be able to complete missing profile data for KYC, so locking it out entirely would break the "tell Fyn I was born in 1985" flow.
- Employment and income are updatable via this tool — which means the AI can write data that then changes its own KYC gate outcomes on the next turn. No circularity protection. In practice the model is prompted to ask for confirmation first, but this is instruction-level only.

---

## Section G — Tools per category summary table

| Category | Tool | Anthropic | xAI | Preview | Handler LOC start | Writes? |
|---|---|---|---|---|---|---|
| Navigation | `navigate_to_page` | ✓ | ✓ | yes | 730 | no (client-side) |
| Read / list | `list_goals` | ✓ | ✓ | yes | 880 | no |
| Read / list | `list_life_events` | ✓ | ✓ | yes | 919 | no |
| Read / list | `list_records` | ✗ | ✓ | - | 735 | no |
| Read / analysis | `get_module_analysis` | ✓ | ✓ | yes | 957 | no |
| Read / analysis | `get_recommendations` | ✓ | ✓ | yes | 996 | no |
| Read / reference | `get_tax_information` | ✓ | ✓ | yes | 1007 | no |
| Read / plan | `generate_financial_plan` | ✓ | ✓ | yes | 1050 | no |
| Meta | `create_what_if_scenario` | ✓ | ✓ | no | 975 | direct write |
| Create | `create_goal` | ✓ | ✓ | no | 1080 | fill_form |
| Create | `create_life_event` | ✓ | ✓ | no | 1124 | fill_form |
| Create | `create_savings_account` | ✓ | ✓ | no | 1163 | fill_form |
| Create | `create_investment_account` | ✓ | ✓ | no | 1220 | fill_form |
| Create | `create_holding` | ✗ | ✓ | - | 1356 | fill_form |
| Create | `create_pension` | ✓ | ✓ | no | 1423 | fill_form |
| Create | `create_property` | ✓ | ✓ | no | 1501 | fill_form |
| Create | `create_mortgage` | ✓ | ✓ | no | 1632 | fill_form |
| Create | `create_protection_policy` | ✓ | ✓ | no | 1679 | fill_form |
| Create (estate) | `create_asset` | ✓ | ✓ | no | 1746 | fill_form |
| Create (estate) | `create_liability` | ✓ | ✓ | no | 1779 | fill_form |
| Create (estate) | `create_estate_gift` | ✓ | ✓ | no | 1819 | fill_form |
| Create (profile) | `create_family_member` | ✓ | ✓ | no | 2121 | fill_form |
| Create (profile) | `create_trust` | ✓ | ✓ | no | 2220 | fill_form |
| Create (profile) | `create_business_interest` | ✓ | ✓ | no | 2299 | fill_form |
| Create (profile) | `create_chattel` | ✓ | ✓ | no | 2354 | fill_form |
| Modify | `set_expenditure` | ✗* | ✓ | no | 2397 | fill_form |
| Modify | `update_record` | ✓ | ✓ | no | 2451 | fill_form (mode=edit) |
| Modify | `delete_record` | ✓ | ✓ | no | 2532 | direct delete |
| Modify | `update_profile` | ✓ | ✓ | no | 2595 | direct update |

`✗*` for `set_expenditure` — not in Anthropic's tool catalogue, but reachable via `update_profile(section: expenditure)` which handler-redirects to `handleSetExpenditure`.

---

## Section H — Cross-cutting concerns

### H.1 Field mapping between AI-facing names and model columns

The gap between how the AI describes a field and how the database stores it is bridged in two places:

1. **`update_record` field aliases** (`CoordinatingAgent.php:2471-2484`) — a per-entity mapping:

    ```php
    $fieldAliases = match ($entityType) {
        'business_interest' => ['estimated_value' => 'current_valuation'],
        'chattel' => ['estimated_value' => 'current_value', 'category' => 'chattel_type'],
        'dc_pension' => ['current_value' => 'current_fund_value'],
        'mortgage' => ['current_balance' => 'outstanding_balance'],
        'life_insurance' => ['life_policy_type' => 'policy_type', 'monthly_premium' => 'premium_amount'],
        default => [],
    };
    ```

2. **Per-create-handler body** — each create handler maps its input keys to the form's expected field names. For example, `handleCreateGoal` maps `input['name']` to `fields['goal_name']`; `handleCreateLifeEvent` maps `input['estimated_amount']` to `fields['amount']`.

These mappings are the result of incremental drift between what the AI tool schemas were originally designed for and what the frontend forms ended up using. There is no automated consistency check.

### H.2 Form fill defaults

Several create handlers inject sensible defaults when the AI omits optional fields:

- `handleCreateProperty` defaults `city` to `'Unknown'`, `postcode` to `'N/A'`, `ownership_type` to `'individual'`, `ownership_percentage` to 100/50/50/0 based on type.
- `handleCreateMortgage` defaults `interest_rate` to 4.5% and `remaining_term_months` to 300. **These are fabricated numbers** — if the user doesn't specify a rate, the form pre-fills 4.5% which looks like a real value. The user can see and change it in the modal, but it's a potentially misleading default.

Other handlers pass null through and let the form show an empty field.

### H.3 Cache invalidation

`CoordinatingAgent::invalidateModuleCache($userId, $module)` (`CoordinatingAgent.php:2000`) is called by some handlers after a successful write to clear the `ai_financial_context_{user_id}` cache and the per-module analysis caches. This is what makes "create a goal, then ask 'how are my goals doing'" return the fresh state rather than the 120-second-old snapshot.

Not every handler calls it — the ones that go via `fill_form` don't actually write anything, so there's nothing to invalidate. The direct-write tools (`create_what_if_scenario`, `delete_record`, `update_profile`) do trigger invalidation.

### H.4 Tool call summary persistence

Every tool call, regardless of success/error, is summarised and appended to `toolCallsSummary[]` in `HasAiChat::chat`, which ends up in `ai_messages.metadata.tool_calls`:

```php
$toolCallsSummary[] = [
    'tool' => $functionName,
    'input' => $this->summariseToolInput($functionArgs),    // max 5 keys, 80-char strings
    'result_summary' => $this->summariseToolResult($toolResult),
];
```

`summariseToolInput` truncates strings to 80 chars and represents arrays as `[array: N items]`.
`summariseToolResult` truncates strings to 60 chars and represents arrays as `[N items]`.

This is what you see when you query `ai_messages` for an assistant turn and want to know which tools were called without pulling the full raw input/output.

### H.5 Preview blocked shape

`previewBlocked($entityType)` (`CoordinatingAgent.php:1860`) returns:

```php
[
    'error' => true,
    'error_type' => 'preview_blocked',
    'message' => "Adding a real {$entityType} is available when you sign up for a free account. You can explore the rest of the features in this preview.",
]
```

The model sees this as an error, and per the FCA process prompt layer (plus the explicit `<preview_mode>` block), should explain warmly to the user that the feature requires a real account.

### H.6 Duplicate detection

`checkForDuplicate($modelClass, $userId, $nameField, $nameValue)` (`CoordinatingAgent.php:1980`) is a helper that several create handlers call before emitting `fill_form`:

- `handleCreateSavingsAccount` — match on `account_name`
- `handleCreateInvestmentAccount` — match on `account_name`
- `handleCreatePension` — match on `scheme_name`

If a record with the same name already exists, the handler returns:

```php
[
    'error' => true,
    'error_type' => 'duplicate_found',
    'existing' => [...],
    'message' => "You already have a {$type} called '{$name}'. Would you like to update it instead?",
]
```

The model then switches to `update_record` or asks the user to confirm. This catches the "create + immediately update" race where the model misses that a record already exists in `<existing_records>`.

**Not every create handler uses it** — `create_property`, `create_protection_policy`, `create_goal`, `create_family_member` do not. So duplicate detection is inconsistent.

### H.7 Tool call ceiling

`MAX_TOOL_CALLS_PER_TURN = 5` (`HasAiChat.php:44`). After 5 tool calls in a single turn:
- If the model is still calling tools but has no text → run one more LLM pass with tools disabled to force a text response
- If the model has text → break out of the loop and return what we have

This ceiling affects:
- **Holistic plan flows** — `generate_financial_plan` internally runs all modules, but the model might still want to call `get_tax_information` for specific topics and `list_records` for context. 5 is tight.
- **Multi-record creation** — the model cannot create 10 savings accounts in one turn; it's capped at 5 creates.

No alert fires when the ceiling is hit. It shows up as a naturally-shorter turn in the metadata.

---

## Section I — Findings and recommendations (tools-specific)

### I.1 High-impact gaps

1. **Three xAI-only tools** (`list_records`, `create_holding`, `set_expenditure`). An Anthropic-provider conversation cannot use these. Flipping `ai_provider` back to anthropic silently disables these capabilities. **Fix**: mirror the xAI definitions in `AiToolDefinitions.php`.

2. **Goal type enum drift** between providers (`home_deposit` vs `house_deposit`, `car_purchase` vs `car`, `custom` vs `other`). A create_goal call made on xAI with `goal_type: home_deposit` would be valid, but the Anthropic-side schema would reject it. **Fix**: unify the enum.

3. **Life event schema drift** — Anthropic has 4 parameters, xAI has 6 with a 16-value enum. Fyn on Anthropic produces lower-quality life event data. **Fix**: backport the richer schema.

4. **Property schema drift** — xAI has ~30 fields (monthly costs, BTL, tenure), Anthropic has ~10. **Fix**: backport.

5. **Pension schema drift** — xAI has 6 extra DB fields (scheme_status, final_salary, accrual_rate) and 3 extra DC fields (annual_salary, monthly_contribution_amount, retirement_age). **Fix**: backport.

6. **create_mortgage fabricates defaults** (4.5% interest rate, 300-month term). **Fix**: don't fabricate — leave null and let the form prompt the user.

### I.2 Medium-impact gaps

7. **`create_what_if_scenario` misses its prerequisite gate.** `PrerequisiteGateService::canExecuteTool` has a case for `run_what_if_scenario` but the actual tool is named `create_what_if_scenario`, so it falls through to `pass()`. **Fix**: add `create_what_if_scenario` to the match arm.

8. **`delete_record` is immediate and irreversible.** Unlike creates (which go through `fill_form` and require user confirmation via the modal), deletes execute as soon as the model calls them. **Fix**: consider routing deletes through a `confirm_delete` intermediate step.

9. **`update_profile` direct writes bypass the fill-form confirmation step.** Sensitive fields like DOB, address, postcode are updatable without a confirmation UI. **Fix**: consider gating "sensitive" profile fields behind a confirmation modal.

10. **Duplicate detection is inconsistent.** `create_property`, `create_protection_policy`, `create_goal`, `create_family_member` do not call `checkForDuplicate`. **Fix**: add it where it makes sense.

11. **`set_expenditure` is not in the audit log prefix check.** The `[AI-AUDIT]` regex matches `create_*` + `update_record`/`delete_record`/`update_profile`, but not `set_expenditure`. **Fix**: add it to the allow-list.

### I.3 Low-impact gaps

12. **The `navigate_to_page` route allow-list is instruction-only.** If the model hallucinates a non-existent route, the Vue router 404s. **Fix**: validate against a hardcoded list in the handler, return an error before yielding the navigation event.

13. **The `event_date` validation on life events does not enforce "must be future".** The description says so but the rule is `'required|date'`. **Fix**: change to `'required|date|after_or_equal:today'`.

14. **`get_tax_information` default branch returns `['error' => ...]` lowercase**, not the standard `['error' => true, 'error_type' => ..., 'message' => ...]`. **Fix**: harmonise.

15. **Tool coercion is shallow** — nested objects (`update_record.fields`, `create_what_if_scenario.parameters`) don't get the `"null" → null` and HTML entity decode treatment. **Fix**: recursive coercion.

### I.4 Testing recommendations (catalogue-specific)

Today there is no test that asserts:
- The tool catalogue shape at call time (e.g. "given `is_preview = true`, `getTools` returns exactly N tools and none of them are write tools")
- That a given tool input produces the expected `fill_form` fields (e.g. "create_property with mortgage_outstanding_balance=200000 produces `has_mortgage: true` and a mortgage_outstanding_balance in fields")
- That the xAI and Anthropic schemas for the same tool name have compatible required field sets
- That `resolveModel` rejects a cross-user entity_id
- That `delete_record` cannot delete another user's record
- That `update_profile` cannot write `national_insurance_number` even if passed in fields

A snapshot test harness that runs each tool handler with representative inputs and asserts on the returned shape would catch most of the gaps in this catalogue.

---

*End of catalogue.*
