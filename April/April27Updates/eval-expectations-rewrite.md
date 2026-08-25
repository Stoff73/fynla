# Eval scenario expectations — line-by-line rewrite

*Authored 2026-04-27 session 102. Branch: `feature/fyn-persona-split`. Trigger: eval session #20 of `advice_protection_cover` showed both providers FAIL the YAML's expectations even though both behaved correctly given the seed.*

> **This report and the Sprint 1 plan live together. Read both.**
>
> **Sprint 1 plan (the contract that schedules this work):** [`../April24Updates/plan/11-sprint-1-plan.md`](../April24Updates/plan/11-sprint-1-plan.md) — Sprint 1 plan with the new sub-tasks **S1.2.l** (rewrite 10 existing YAMLs) and **S1.7.a-S1.7.j** (asserter, meta-tests, dashboard delta, canonical-behaviour, state-machine, handoff, resume, re-record, verification, follow-up). The sprint plan's "Eval expectations rewrite — S1.7 scope extension (added 2026-04-27 session 102)" narrative section mirrors §13/§14 of THIS report and points back here.
>
> **THIS report (the rewrite specification):** Section 4 contains every per-scenario line-by-line change. Sections 6, 10, 11, 12 contain the 48 NEW YAMLs to author. Section 9 is the numbered execution order — every item there maps 1:1 to a sub-task in S1.7 of the sprint plan.
>
> Together they form the complete Sprint 1 closure contract for the eval workstream. Section 14 of THIS report is the file-pointer index for the next instance.

> **CSJ direction:** "Do not change the seed, change the expectations, for the actual fucking workflow, user flow, user experience." This report does that. Every change names the line it touches, the source-of-truth file:line that justifies the new value, and why the user experience demands it.

---

## TL;DR

The 6 advice-mode YAMLs and the 4 multi-entity YAMLs were authored before the canonical contract (`spec/00-canonical.md`), the response-mode classifier (`AdviceFyn::classifyResponseMode`, S1.8), the per-agent output contract (`ToolResultContract`, S1.6.b), and the per-classification required-tools table (`QuerySchemas::REQUIRED_TOOLS`) were finalised. Every advice scenario's `expected_tool_calls`, `expected_sse_events`, and `timing_budget_ms` is structurally wrong against the running system. The session-101 removal of `advice_response` SSE invalidated `expected_advice_response` in every YAML.

This report rewrites every YAML line. It also catalogues:

- the per-classification tool list every scenario should assert (sourced from `QuerySchemas::REQUIRED_TOOLS` + `IMPLICIT_RELATED`);
- the per-agent readiness gate every scenario hits (sourced from each `*DataReadinessService::assess()` + agent-internal `if (! $profile)` checks);
- the per-agent secondary preconditions that the Readiness service does NOT cover (`ProtectionAgent` line 72 `if (! $user->protectionProfile)`, `RetirementAgent` line 101 `if (! $profile)` retirementProfile);
- **4 new canonical-behaviour scenarios** for KYC-block / handoff round-trip / holistic engine / out-of-remit (§6);
- **14 new onboarding state-machine scenarios** — one per state transition — so a regression in any one state's prompt-builder, parking logic, or commit handler fails one specific scenario, not the long BS-01 walk (§10);
- **14 new write-tool-family handoff stress tests** — one per `AdviceFyn::WRITE_TOOLS` family — covering the full advice→inline-capture→advice round-trip with cross-provider drift assertions and the shared INV-2.4.x invariants fragment (§11);
- **16 new resume-after-disconnect scenarios** — 13 per-state + 3 edge cases — that bind each state's `OnboardingChatDirector::resumeSummary` output to a falsifiable assertion (§12);
- admin dashboard delta logic that needs to read the new contract (§7);
- 6 architecture meta-tests that prevent expectation drift from re-occurring (§8);
- a complete pointer index for the next instance — every plan, spec, source file, test file, vault doc, and memory reference needed to execute this work (§14).

Total new YAMLs: **48** (4 canonical + 14 state-machine + 14 handoff + 16 resume). Plus 6 advice rewrites + 4 multi-entity light-touch updates. Total YAML deliverables: **58**.

Nothing in this report touches the seed. Every change is in the YAMLs and adjacent infrastructure.

---

## Section 1 — Source-of-truth references (re-read at audit time, not recalled)

Every assertion in this report cites one of these files. They are listed once here and referenced by name + line below.

| Ref | File | What it defines |
|---|---|---|
| **CANON** | `April/April24Updates/spec/00-canonical.md` lines 9-21 | The Two-Fyn contract. Onboarding writes; AdviceFyn reads. |
| **INV-2.1.2** | `April/April24Updates/spec/01-invariants.md` lines 84-88 | AdviceFyn tool list disjoint from write tools (which are in `AdviceFyn::WRITE_TOOLS`). |
| **INV-2.3.1** | `01-invariants.md` lines 140-144 | `classifyResponseMode` must map every `QuerySchemas` constant. Two modes for advice scenarios: `factual` and `recommendation`. |
| **INV-2.3.2** | `01-invariants.md` lines 146-150 | Engine is the sole source of interpretive text. Forbidden phrases ("I think you should") trace here. |
| **INV-2.3.3** | `01-invariants.md` lines 152-156 | Every `recommendation`-mode response ends with the exact signposting string. Factual + out-of-remit do NOT append it. |
| **INV-2.3.4** | `01-invariants.md` lines 158-162 | Out-of-remit canonical refusal: *"I'm able to help you with your finances. {context} is out of scope."* Zero tool calls. |
| **INV-2.3.5** | `01-invariants.md` lines 164-195 | **OBSOLETE per session 101.** `advice_response` SSE was removed. Spec line not yet updated; this report treats it as null. |
| **INV-2.3.6** | `01-invariants.md` lines 197-200 | Engine call granularity: `holistic` → `orchestrateAnalysis`; `module` → single-agent `analyze()`; `factual` → no engine call. |
| **QSCH** | `app/Constants/QuerySchemas.php` | Single source of truth for: classification constants (lines 17-77), MODULE_MAP (126-151), IMPLICIT_RELATED (159-184), KEYWORD_PATTERNS (192-373), REQUIRED_TOOLS (419-503), MODULE_KYC (391-412). |
| **AFYN-MODE** | `app/Services/AI/AdviceFyn.php` lines 52-77 | `RESPONSE_MODE_MAP` — every `QuerySchemas` constant → `'factual' / 'recommendation' / 'out_of_remit'`. |
| **AFYN-ENG** | `app/Services/AI/AdviceFyn.php` lines 95-120 | `ENGINE_CALL_LEVEL_MAP` — every constant → `'holistic' / 'module' / 'factual'`. |
| **AFYN-WRITE** | `app/Services/AI/AdviceFyn.php` lines 128-152 | `WRITE_TOOLS` — every tool stripped from the advice tool list. **Includes `navigate_to_page` per S0.5.t**. |
| **TRC** | `app/Services/AI/ToolResultContract.php` | Per-module REQUIRED_KEYS (45-77) + PARTIAL_KEYS (85-91) + 4 validate paths (103-143). |
| **KYC** | `app/Services/AI/KycGateChecker.php` | `check()` — universal then per-module. Bypass for FACTUAL_TYPES (lines 43-45). Universal: DOB, marital_status, employment_status, income, expenditure (lines 94-130). |
| **PRG** | `app/Services/PrerequisiteGateService.php` | Module action map → DataReadinessService delegation (46-59). |
| **RDY-PROT** | `app/Services/Protection/ProtectionDataReadinessService.php` lines 74-99 | Blocking: DOB, income, marital_status. Warnings: expenditure, employment, dependants, existing policies, employer benefits, debts. |
| **RDY-SAV** | `app/Services/Savings/SavingsDataReadinessService.php` lines 30-61 | Blocking: DOB, income, expenditure. Warning: employment, savings accounts, marital_status. |
| **RDY-RET** | `app/Services/Retirement/RetirementDataReadinessService.php` lines 36-64 | Blocking: DOB, marital_status, income. Warning: pension_data, target_retirement_age, target_retirement_income, expenditure. |
| **RDY-INV** | `app/Services/Investment/Recommendation/DataReadinessService.php` lines 65-83 | **Blocking: DOB, income, RISK_PROFILE, expenditure** — risk_profile is a hard block. |
| **RDY-EST** | `app/Services/Estate/EstateDataReadinessService.php` lines 65-81 | Blocking: DOB, marital_status, at_least_one_asset. Warnings: residency, property_data, liabilities, family_members, gifts, will. |
| **AGT-PROT** | `app/Agents/ProtectionAgent.php` lines 38-78 | Two-stage gate: (1) readinessService->assess(), (2) `if (! $user->protectionProfile)` returns Path 0. |
| **AGT-SAV** | `app/Agents/SavingsAgent.php` lines 53-71 | One-stage: readinessService only. No second profile gate. |
| **AGT-INV** | `app/Agents/InvestmentAgent.php` lines 42-79 | One-stage readiness, then `if ($accounts->isEmpty()) return ['accounts_count' => 0, ...]` — Path 3 empty state. |
| **AGT-RET** | `app/Agents/RetirementAgent.php` lines 67-103 | Two-stage: (1) readinessService, (2) `if (! $profile)` retirementProfile returns Path 0 "No retirement profile found". |
| **AGT-EST** | `app/Agents/EstateAgent.php` lines 58-96 | One-stage: readinessService only. Loads ihtProfile but doesn't gate on it. |
| **AGT-GOA** | `app/Agents/GoalsAgent.php` lines 31-87 | No readinessService. `if ($goals->isEmpty()) return ['has_goals' => false, ...]` — Path 3 empty state. |
| **CLS** | `app/Services/AI/QueryClassifier.php` lines 64-114 | Order: data_entry → navigation → keyword → out_of_remit → route fallback → general. **Multi-label**: a message matching multiple types collects all of them. |
| **TOOLS** | `app/Services/AI/AiToolDefinitions.php` lines 124-148 | `get_module_analysis(module)` enum: `protection, savings, investment, retirement, estate, goals, holistic`. **`affordability` is NOT a valid module argument.** |
| **POST-S101** | `April/April27Updates/CSJTODO.md` lines 31, 89, 95-96 | S1.6.a removed. The `advice_response` SSE event no longer exists in code. |

---

## Section 2 — The seven defects in every advice YAML

Identified by reading every YAML line against the running system. None of these are seed problems.

### Defect A — `expected_tool_calls` does not match `QuerySchemas::REQUIRED_TOOLS`

**What's wrong:** every advice YAML asserts exactly two tools — `get_module_analysis` + `get_recommendations`. **Source-of-truth: QSCH lines 419-503.** Per-classification REQUIRED_TOOLS are different in count and shape:

| Classification | YAML asserts | QSCH actually requires (line) |
|---|---|---|
| PROTECTION_COVER | `get_module_analysis(protection)` + `get_recommendations` | `get_module_analysis(protection)` + `list_records(life_insurance)` (462-465) |
| SAVINGS_EMERGENCY | `get_module_analysis(savings)` + `get_recommendations` | `get_module_analysis(savings)` + `list_records(savings_account)` (436-439) |
| INVESTMENT_PORTFOLIO | `get_module_analysis(investment)` + `get_recommendations` | `get_module_analysis(investment)` + `list_records(investment_account)` (449-452) |
| RETIREMENT_CONTRIBUTION | `get_module_analysis(retirement)` + `get_recommendations` | 4 tools: `get_tax_information(pension_allowances)` + `get_tax_information(income_definitions)` + `get_module_analysis(retirement)` + `list_records(dc_pension)` (420-425) |
| ESTATE_IHT | `get_module_analysis(estate)` + `get_recommendations` | 3 tools: `get_tax_information(inheritance_tax)` + `get_module_analysis(estate)` + `list_records(property)` (470-474) |
| AFFORDABILITY | `get_module_analysis(holistic)` + `get_recommendations` | `get_module_analysis(savings)` only (497-499). **The `module: holistic` arg is invalid for AFFORDABILITY.** |

`get_recommendations` is **never** required for any module-scoped advice classification. It is only required for HOLISTIC_HEALTH (line 492-495). The YAMLs invented the requirement.

### Defect B — IMPLICIT_RELATED additions are not asserted

**What's wrong:** `QueryClassifier::buildResult()` (CLS lines 206-220) merges `IMPLICIT_RELATED` types into every classification. `getRequiredToolsForClassification()` (QSCH lines 716-725) then dedup-merges the related types' tool lists. The YAMLs assert only the primary type's tools.

For RETIREMENT_CONTRIBUTION (the worst case), IMPLICIT_RELATED = `[TAX_OPTIMISATION, SAVINGS_EMERGENCY, AFFORDABILITY]` (QSCH line 160). Merged tool list:

- From RETIREMENT_CONTRIBUTION: `get_tax_information(pension_allowances)`, `get_tax_information(income_definitions)`, `get_module_analysis(retirement)`, `list_records(dc_pension)`
- From TAX_OPTIMISATION: `get_tax_information(income_tax)`, `get_tax_information(isa_allowances)`, `get_tax_information(pension_allowances)` (dedup)
- From SAVINGS_EMERGENCY: `get_module_analysis(savings)`, `list_records(savings_account)`
- From AFFORDABILITY: `get_module_analysis(savings)` (dedup)

Total: 7 unique tools. The YAML asserts 2.

### Defect C — `expected_sse_events` is not the actual SSE shape

**What's wrong:** every advice YAML has:

```yaml
expected_sse_events:
  - { type: content }
  - { type: done }
```

The actual SSE stream from `AdviceFyn::handle` → `CoordinatingAgent::chatWithPromptOverride` emits:

- `title` (1) — conversation title set on first turn
- `content` (N) — token-by-token streamed text (often 18-200 events per response)
- `tool_use` (M) — one per tool call (usually 1-7 per advice turn)
- `tool_result` (M) — sometimes omitted depending on provider
- `done` (1) — terminal

A two-event assertion `[content, done]` cannot meaningfully grade a stream of 18-200 events. It also doesn't gate on tool_use events at all, leaving `expected_tool_calls` to do that work standalone. Per session #20 evidence: anthropic emitted 19 events, xAI emitted 198 events — the "content + done" expectation matched both even though the responses differed substantially.

### Defect D — `expected_advice_response` is dead

**Source-of-truth: POST-S101.** The `advice_response` SSE event was removed in session 101. The composer, schema, panel, and SSE emit block are deleted. Every YAML's `expected_advice_response.signposting_suffix_present: true` and `has_recommendations: true` cannot be evaluated — the event never fires.

The signposting suffix per **INV-2.3.3** still applies, but to the LLM's free text response, not to a separate SSE event. The assertion needs to move to `assistant_text contains "For regulated advice personal to your circumstances, speak to a qualified financial adviser."`

### Defect E — `timing_budget_ms: 5000` doesn't reflect any actual path

**Evidence:** session #20 anthropic 5898ms, xAI 13995ms, both for a turn that the model completed correctly per the seed. Eval session #18 — same scenario, same shape — anthropic 4761ms, xAI 14468ms. Eval session #19 — anthropic 6048ms, xAI 14224ms.

5000ms is roughly the median of anthropic-only timings on a clean module-scoped happy path. xAI consistently runs 12000-15000ms because grok-4-1-fast-reasoning emits ~10x more content events. The budget should be path-aware:

- module-scoped happy path (single agent, 2-3 tools): anthropic ≤7000ms, xAI ≤16000ms
- module-scoped readiness-blocked path (KYC PASS, agent's profile gate fails): anthropic ≤6000ms, xAI ≤14000ms (less because no get_recommendations follow-up)
- KYC-blocked path (no analysis tools fire): anthropic ≤5000ms, xAI ≤12000ms (just text output)
- holistic happy path (orchestrateAnalysis + 5+ tools): anthropic ≤12000ms, xAI ≤25000ms

A single 5000ms budget against all of these is meaningless. The dashboard's "X ms over budget" diagnostic for session #20 was technically correct but also irrelevant — both providers were operating in their normal ranges.

### Defect F — `expected_classifications` is single-string but classifier emits multi-label

**Source-of-truth: CLS lines 64, 86-91, 206-220.** `QueryClassifier::classify()` returns `{primary, related[], modules[]}`. Every YAML asserts `expected_classifications: [single_value]` against the primary, ignoring related types entirely. For RETIREMENT_CONTRIBUTION, the actual classification is:

```php
['primary' => 'retirement_contribution',
 'related' => ['tax_optimisation', 'savings_emergency', 'affordability'],
 'modules' => ['retirement', 'tax', 'savings', 'income']]
```

The YAML also doesn't assert what goes into the `<kyc_status>` block — which depends on `modules[]`, not `primary`. A scenario can pass on `expected_classifications` but the system prompt the LLM received was for a different module set.

### Defect G — readiness gates and per-agent profile gates are not asserted

**Source-of-truth: TRC paths 0-4 (lines 103-143), AGT-PROT line 72, AGT-RET line 101.** Each scenario's seed determines which of the 4 ToolResultContract paths the tool result takes:

- Path 0 (success: false): seed has data but agent's *secondary* profile gate fails. Protection without a `protectionProfile` row, Retirement without a `retirementProfile` row.
- Path 1 (wrapped happy): seed has all required entities for the module agent to compute results.
- Path 2 (readiness blocked): seed misses a `*DataReadinessService::assess()` blocking field. **Investment with no `risk_profile` is the canonical case** — that field is BLOCKING per RDY-INV.
- Path 3 (empty state): Investment with `accounts.isEmpty()`, Goals with `goals.isEmpty()`.
- Path 4 (full happy with all REQUIRED_KEYS): seed includes everything the module agent needs to populate the contract.

The YAMLs don't say which path they expect. The dashboard's FAIL/PASS verdict is meaningless without that information — a "happy path" YAML and a "readiness-blocked" YAML can both produce one tool call, but they mean different things and need different downstream assertions.

---

## Section 3 — Universal rewrite rules (apply to every advice YAML)

Three new top-level YAML keys, two existing keys reshaped, one removed. Order matches the canonical YAML reading order.

### 3.1 NEW `expected_response_mode` (string)

**Source: AFYN-MODE.** One of `recommendation` / `factual` / `out_of_remit`. Drives downstream assertions:

- `recommendation` — assistant text must end with INV-2.3.3 signposting; one or more analysis tools should fire (subject to readiness path).
- `factual` — no signposting suffix; no engine call; tool list scoped to `get_tax_information` / `list_records` only.
- `out_of_remit` — INV-2.3.4 canonical refusal verbatim; zero tool calls; zero signposting.

### 3.2 NEW `expected_engine_call_level` (string)

**Source: AFYN-ENG.** One of `holistic` / `module` / `factual`. Drives:

- `holistic` — assert `orchestrateAnalysis` was invoked (CoordinatingAgent line 163-…). Only `HOLISTIC_HEALTH` resolves here.
- `module` — assert exactly one `{Module}Agent::analyze()` was invoked, primary module per `MODULE_MAP[primary]`.
- `factual` — assert no `*Agent::analyze()` was invoked.

### 3.3 NEW `expected_classification_shape` (object)

Replaces the brittle single-string `expected_classifications`. Fully captures CLS output:

```yaml
expected_classification_shape:
  primary: retirement_contribution
  related:
    - tax_optimisation
    - savings_emergency
    - affordability
  modules:
    - retirement
    - tax
    - savings
    - income
```

The asserter compares `primary` strict, `related` and `modules` as set equality. **Sourced from**: classifier's actual output for the user message, computed once at YAML authoring time via `php artisan tinker --execute="dump(app(QueryClassifier::class)->classify('...'));"` and pasted in.

### 3.4 NEW `expected_kyc_state` (string + structured missing)

**Source: KYC.** One of `passed` / `bypass` / `blocked`.

- `passed` — universal + every classified module's PrerequisiteGateService all PASS.
- `bypass` — classification is in FACTUAL_TYPES (`general`, `billing`).
- `blocked` — at least one universal field or per-module block fails.

When `blocked`, also include:

```yaml
expected_kyc_state: blocked
expected_kyc_missing:
  - { label: "Risk profile", route: "/investment/risk-profile" }
  - { label: "At least one investment account", route: "/investment/accounts" }
```

This lets the asserter check the KYC BLOCKED prompt block was correctly generated and the LLM was given the exact navigation routes.

### 3.5 NEW `expected_tool_result_path` per tool (enum)

For each entry in `expected_tool_calls`, add a `result_path` field. **Source: TRC paths 0-4.**

```yaml
expected_tool_calls:
  - tool: get_module_analysis
    args: { module: protection }
    result_path: success_false        # AGT-PROT line 72
    result_message_contains: "Protection profile not found"
  - tool: list_records
    args: { record_type: life_insurance }
    result_path: happy
```

Allowed values: `success_false`, `readiness_blocked`, `empty_state`, `happy` (per TRC). The asserter looks at the captured tool_result content and validates against the contract path that was supposed to fire.

### 3.6 RESHAPED `expected_tool_calls` (full per-classification list)

Sourced from `QuerySchemas::getRequiredToolsForClassification($classification)` (QSCH line 716-725) at YAML authoring time. **Tools that fire conditionally** (e.g. `get_recommendations` only when at least one module is ready per PRG line 217-239) get a `conditional: true` flag and a `condition` description so the asserter can decide whether absence is a failure or a correct skip.

```yaml
expected_tool_calls:
  - tool: get_module_analysis
    args: { module: retirement }
    required: true
  - tool: list_records
    args: { record_type: dc_pension }
    required: true
  - tool: get_tax_information
    args: { topic: pension_allowances }
    required: true
  - tool: get_tax_information
    args: { topic: income_definitions }
    required: true
  - tool: get_recommendations
    required: false
    condition: "Fires only after get_module_analysis returns happy-path data per PrerequisiteGateService::canGetRecommendations"
```

### 3.7 RESHAPED `expected_sse_events` (counts + invariants, not list-equality)

Replace the "[content, done]" list with structural assertions:

```yaml
expected_sse_events:
  must_contain_types: [title, content, tool_use, done]
  must_emit_exactly_once:
    - done
    - title
  must_not_emit:
    - persona_state_change      # INV-2.4.1
    - handoff                   # INV-2.4.1 — internal, never reaches frontend
  content_event_minimum: 5      # below this means model produced no real text
  tool_use_count_min: 1
  tool_use_count_max: 8         # holistic ceiling
```

### 3.8 REMOVED `expected_advice_response`

**Source: POST-S101.** The block, the SSE event, the composer, and the panel are all gone. Replace with:

```yaml
expected_assistant_text:
  must_contain_substrings:
    - "For regulated advice personal to your circumstances, speak to a qualified financial adviser."
  must_not_contain_substrings:
    - "I think you should"
    - "I'd recommend"
    - "In my opinion"
    - "you should definitely"
  minimum_length_chars: 200
  maximum_length_chars: 2500
```

The signposting assertion enforces INV-2.3.3 against the actual user-visible text. Only emit `must_contain_substrings: ["For regulated advice..."]` when `expected_response_mode: recommendation` — factual and out-of-remit modes do NOT append it (also INV-2.3.3).

### 3.9 RESHAPED `timing_budget_ms` (per-provider, per-path)

Replace the single number with a path-aware map:

```yaml
timing_budget_ms:
  anthropic:
    happy: 7000
    readiness_blocked: 6000
    success_false: 6000
    kyc_blocked: 5000
  xai:
    happy: 16000
    readiness_blocked: 14000
    success_false: 14000
    kyc_blocked: 12000
```

The asserter reads the path the run actually took (from the tool_result analysis) and applies the matching budget. xAI's higher budgets reflect the documented `grok-4-1-fast-reasoning` emit-rate (10x more content events on the same prompt — see CSJTODO `feedback_fyn_model_choice_is_deliberate.md`).

---

## Section 4 — Per-scenario rewrite (line by line)

For each YAML I list every line that changes. Every change cites a source. Lines I do NOT touch are listed at the end of each scenario as "preserved".

### 4.1 `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_protection_cover.yaml`

**User message:** *"Am I covered enough for protection?"*

**Classification (verified via CLS keyword pattern at QSCH line 258):** primary `protection_cover`, related `[]` (QSCH line 171), modules `[protection]`.

**KYC trace (verified by reading KYC against the seed):**
- Universal: DOB ✓, marital_status ✓, employment_status ✓, income £50000 ✓, expenditure £2500 ✓ → all PASS.
- Module `protection` → PRG line 49 → RDY-PROT lines 76-99 → blocking checks: DOB ✓, income ✓, marital_status ✓ → can_proceed=true → PASS.
- KYC overall: **PASSED**.

**Engine path (verified by reading AGT-PROT against the seed):**
- ProtectionAgent::analyze() line 38 readinessService->assess() → can_proceed=true (same checks as above) → continues.
- ProtectionAgent::analyze() line 72 `if (! $user->protectionProfile)` → seed has no `protection_profile` row (only one `life_insurance_policies` row) → **FAILS** → returns `['success' => false, 'message' => 'Protection profile not found. Please create a protection profile first.', 'data' => []]`.
- TRC path 0 — passes through verbatim with `module: protection` prepended.

**Tool firing prediction:**
- `list_records(life_insurance)` — fires (no readiness gate per PRG line 199 default-pass).
- `get_module_analysis(protection)` — fires, returns success_false.
- `get_recommendations` — does NOT fire because `canGetRecommendations` (PRG line 217-239) loops over all modules and protection failed; depending on what other modules look like for the eval user (none seeded except protection), it returns "no modules ready" and blocks. Even if it did fire, the LLM has no reason to ask for recommendations on a profile-not-found path.

**Line-by-line changes:**

| Line | Current | Change to | Source | Why |
|---|---|---|---|---|
| 3-6 description | "calls get_module_analysis(protection) + get_recommendations, emits content (no advice_response until S1.6) + done" | "calls list_records(life_insurance) + get_module_analysis(protection); the agent's secondary protection_profile gate fails (no protection_profile row in seed) so the tool returns success_false; LLM identifies the missing profile fields (spouse income, dependants, debts) and asks the user without calling get_recommendations" | AGT-PROT line 72; QSCH lines 462-465 | Match actual flow |
| 25-26 expected_classifications | `- protection_cover` | Replace with `expected_classification_shape: { primary: protection_cover, related: [], modules: [protection] }` | CLS + QSCH lines 127, 171 | Multi-label shape per §3.3 |
| (new before 28) | — | `expected_response_mode: recommendation` | AFYN-MODE line 53 | §3.1 |
| (new) | — | `expected_engine_call_level: module` | AFYN-ENG line 97 | §3.2 |
| (new) | — | `expected_kyc_state: passed` | KYC trace above | §3.4 |
| 28-31 expected_tool_calls | 2 entries asserting get_module_analysis + get_recommendations | Rewrite to 3 entries: `list_records(life_insurance) required:true result_path:happy`, `get_module_analysis(protection) required:true result_path:success_false result_message_contains:"Protection profile not found"`, `get_recommendations required:false condition:"will not fire on profile-not-found path"` | QSCH 462-465, AGT-PROT 72, TRC path 0 | §3.5, §3.6 |
| 33-35 expected_sse_events | `[content, done]` | Replace with §3.7 structural form. Add `must_emit_at_least_once: [tool_use]` since at least `list_records` will fire. | Run evidence + AdviceFyn yields | §3.7 |
| 37-40 expected_advice_response | block | Delete entire block | POST-S101 | Event removed |
| (new) | — | `expected_assistant_text` block per §3.8: must_contain "For regulated advice...", must_not_contain "I think you should" / "I'd recommend" / "In my opinion" / "you should definitely", minimum 200 chars (the model has to explain the missing data and ask for it). | INV-2.3.3 + existing forbidden_outputs | §3.8 |
| 42-46 forbidden_outputs | preserve | (unchanged — these are still the right forbidden phrases) | INV-2.3.2 | — |
| 47-51 forbidden_tools | preserve | (unchanged — protection write tools must never fire in advice mode) | INV-2.1.2 + AFYN-WRITE | — |
| 53 timing_budget_ms | `5000` | Replace with §3.9 per-provider per-path map. Path = `success_false`. anthropic 6000, xai 14000. | Session #18-#20 evidence | §3.9 |
| 55-58 tags | preserve, add | Add `success-false-path`, `protection-profile-required` for filterability | — | New paths get tagged so meta-tests can find them |

### 4.2 `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_savings_emergency.yaml`

**User message:** *"Do I have enough emergency savings?"*

**Classification:** primary `savings_emergency` (QSCH line 273), related `[affordability]` (QSCH line 163), modules `[savings, income]`.

**KYC trace:**
- Universal: all 5 fields seeded → PASS.
- Module `savings` → RDY-SAV lines 35-37 blocking: DOB ✓, income ✓, expenditure ✓ → PASS.
- Module `income` → KYC line 150 `actionMap['income']` is undefined → returns `[]` (no checks).
- KYC overall: **PASSED**.

**Engine path:**
- SavingsAgent::analyze() line 53 readinessService → can_proceed=true → continues to happy path.
- SavingsAgent has NO secondary profile gate (verified AGT-SAV) — runs full analysis: total_savings=£4500, monthly_expenditure=£2500, runway=1.8 months, isa_allowance status, liquidity, etc.
- TRC path 4 — full payload, all REQUIRED_KEYS present (TRC line 51-54): `summary, emergency_fund, isa_allowance, liquidity, rate_comparisons, goals, missing_for_quality_advice`.

**Tool firing prediction:**
- `list_records(savings_account)` — fires (QSCH line 437-439).
- `get_module_analysis(savings)` — fires, returns happy path.
- `get_recommendations` — fires because at least one module is ready (PRG line 217-239 returns can_proceed=true). LLM asks for ranked recommendations across all the user's modules.

**Line-by-line changes:**

| Line | Current | Change to | Source | Why |
|---|---|---|---|---|
| 3-6 description | "calls get_module_analysis(savings) + get_recommendations, emits content + done" | "Universal KYC passes (DOB/marital/employment/income/expenditure all seeded); Savings module readiness PASSES (DOB/income/expenditure all seeded); SavingsAgent runs full analysis showing 1.8 months of runway against the £2500 monthly expenditure (≈18% of the typical 6-month target). Expected tools: list_records(savings_account) → get_module_analysis(savings) → get_recommendations. Implicit related type AFFORDABILITY does not add tools because its REQUIRED_TOOLS is just get_module_analysis(savings) (already present)." | RDY-SAV, QSCH lines 436-439 + 163 | Match actual flow |
| 25-26 expected_classifications | `- savings_emergency` | Replace with `expected_classification_shape: { primary: savings_emergency, related: [affordability], modules: [savings, income] }` | CLS + QSCH 129, 163 | Multi-label |
| (new) | — | `expected_response_mode: recommendation` | AFYN-MODE line 55 | §3.1 |
| (new) | — | `expected_engine_call_level: module` | AFYN-ENG line 99 | §3.2 |
| (new) | — | `expected_kyc_state: passed` | KYC trace above | §3.4 |
| 28-31 expected_tool_calls | 2 entries | Rewrite to 3 entries: `list_records(savings_account) required:true result_path:happy`, `get_module_analysis(savings) required:true result_path:happy`, `get_recommendations required:true condition:"savings module is ready, get_recommendations gate passes"` | QSCH 436-439, AGT-SAV, PRG 217-239 | §3.5, §3.6 |
| 33-35 expected_sse_events | `[content, done]` | §3.7 structural form. tool_use_count_min:2, tool_use_count_max:4. content_event_minimum:8 (the model needs to explain runway, target, and recommendations). | — | §3.7 |
| 37-40 expected_advice_response | block | Delete | POST-S101 | — |
| (new) | — | `expected_assistant_text`: must_contain ["For regulated advice...", "1.8" or "1.8 months" or "less than 2 months" or similar, "£4,500" or "£4500"]. must_not_contain forbidden phrases. minimum 250 chars. | INV-2.3.3, INV-2.3.2 | §3.8 |
| 42-45 forbidden_outputs | preserve | unchanged | — | — |
| 47-50 forbidden_tools | preserve, add | Add `update_record`, `delete_record` for completeness | INV-2.1.2 | — |
| 52 timing_budget_ms | `5000` | §3.9 per-path. Path=happy. anthropic 7000, xai 16000. | §3.9 | — |
| 54-57 tags | preserve, add | Add `happy-path`, `affordability-implicit-related` | — | — |

### 4.3 `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_investment_isa.yaml`

**User message:** *"Should I contribute more to my Stocks & Shares ISA?"*

**Classification:** primary `investment_portfolio` (QSCH line 296), related `[affordability]` (QSCH line 166), modules `[investment, savings, income]`.

**KYC trace — THIS ONE BLOCKS:**
- Universal: all 5 fields → PASS.
- Module `investment` → PRG line 51 → RDY-INV lines 65-83 blocking: DOB ✓, income ✓, **risk_profile ✗** (seed has no risk_profile), expenditure ✓ → can_proceed=**FALSE**.
- KYC line 56-58: collects `[Risk profile required]` from the investment module → BLOCKED.
- LLM gets `<kyc_status>BLOCKED ... Do NOT give advice ... navigate to /investment/risk-profile</kyc_status>`.

**But — `navigate_to_page` is in AFYN-WRITE line 151!** S0.5.t stripped it from advice mode. So the LLM is told to use `navigate_to_page` but the tool isn't available. The LLM has to fall back to text-only navigation guidance.

**Engine path:**
- LLM does not call `get_module_analysis(investment)` because the prompt says NEVER call analysis tools when KYC blocked.
- LLM does not call `get_recommendations` either.
- LLM produces text explaining what's missing (risk profile) and how to enter it.

**Line-by-line changes:**

| Line | Current | Change to | Source | Why |
|---|---|---|---|---|
| 3-6 description | "calls get_module_analysis(investment) + get_recommendations" | "InvestmentDataReadinessService BLOCKING checks include risk_profile (RDY-INV line 67-69, 127-140) which the seed does not provide. KYC returns BLOCKED with route /investment/risk-profile. The LLM is instructed not to call analysis tools and to navigate the user to set up their risk profile. Note: navigate_to_page is stripped from AdviceFyn (AFYN-WRITE line 151 per S0.5.t) so the LLM provides text-only navigation guidance." | RDY-INV, KYC, AFYN-WRITE | This scenario tests the KYC-blocked path |
| 25-26 expected_classifications | `- investment_portfolio` | Replace with `expected_classification_shape: { primary: investment_portfolio, related: [affordability], modules: [investment, savings, income] }` | CLS + QSCH 135, 166 | Multi-label |
| (new) | — | `expected_response_mode: recommendation` | AFYN-MODE line 61 | §3.1 |
| (new) | — | `expected_engine_call_level: module` (the *intended* level if KYC had passed) | AFYN-ENG line 105 | §3.2 |
| (new) | — | `expected_kyc_state: blocked` + `expected_kyc_missing: [{label: "A completed risk profile is essential...", route: "/investment/risk-profile"}]` | RDY-INV line 127-140 | §3.4 |
| 28-31 expected_tool_calls | 2 entries | Rewrite to: NONE required. Add explicit `expected_tool_calls_absent: [get_module_analysis, get_recommendations, generate_financial_plan, navigate_to_page]` to capture the KYC-block contract. | KYC blocked instructions + AFYN-WRITE | §3.5/3.6 |
| 33-35 expected_sse_events | `[content, done]` | §3.7. tool_use_count_min:0, tool_use_count_max:1 (the LLM may attempt navigate_to_page and fail because it's not exposed; depending on provider, it may try `list_records` to see what's there — both should be allowed-but-not-required). | — | §3.7 |
| 37-40 expected_advice_response | block | Delete | POST-S101 | — |
| (new) | — | `expected_assistant_text` block: must_contain ["risk profile" or "/investment/risk-profile", explanation of why risk profile is needed]. must_not_contain ["For regulated advice..." — recommendation-mode signposting does NOT apply because the model is not giving advice, INV-2.3.3]. must_not_contain forbidden phrases. minimum 150 chars. | INV-2.3.3, KYC blocked text | §3.8 |
| 42-45 forbidden_outputs | preserve, add | Add fabricated-figure detection: `"You should contribute"`, `"You can contribute £20,000"` — guard against the model fabricating ISA allowance advice without the risk profile context. | INV-2.3.2 | — |
| 47-51 forbidden_tools | preserve, **add** | Add `get_module_analysis` and `get_recommendations` to forbidden_tools — KYC blocked means the LLM is explicitly told NOT to call them. | KYC `Do NOT give advice` instruction | This is the contract |
| 53 timing_budget_ms | `5000` | §3.9. Path=kyc_blocked. anthropic 5000, xai 12000. | §3.9 | — |
| 55-60 tags | preserve, add | Add `kyc-blocked-path`, `risk-profile-required`, `navigate-to-page-stripped` | — | — |

### 4.4 `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_retirement_contribution.yaml`

**User message:** *"Should I increase my pension contributions?"*

**Classification:** primary `retirement_contribution` (QSCH line 234), related `[tax_optimisation, savings_emergency, affordability]` (QSCH line 160), modules `[retirement, tax, savings, income]`.

**KYC trace:**
- Universal: all 5 fields → PASS.
- Module `retirement` → RDY-RET line 36-49 blocking: DOB ✓, marital ✓, income ✓ → can_proceed=true → PASS.
- Module `tax` → PRG line 126-142 `canAnalyseTax` → income > 0 ✓, employment_status ✓ → PASS.
- Module `savings` → RDY-SAV blocking → PASS.
- Module `income` → no actionMap → no checks.
- KYC overall: **PASSED**.

**Engine path:**
- RetirementAgent::analyze() line 67 → readinessService passes → continues.
- RetirementAgent::analyze() line 101 `if (! $profile)` — seed creates `dc_pensions` row but NO `retirement_profile` row → **FAILS** → returns `['success' => false, 'message' => 'No retirement profile found', 'data' => []]`.
- TRC path 0 — passes through verbatim.

**This is the same shape as `advice_protection_cover` for a different agent** — KYC PASSES, but the agent's secondary profile gate fails because the seed has the related entity (DCPension / LifeInsurancePolicy) but not the profile entity (RetirementProfile / ProtectionProfile).

**Tool firing prediction:**
- `list_records(dc_pension)` — fires.
- `get_module_analysis(retirement)` — fires, success_false.
- `get_tax_information(pension_allowances)` — fires (no readiness gate per PRG line 198).
- `get_tax_information(income_definitions)` — fires.
- Cross-module via IMPLICIT_RELATED: `get_tax_information(income_tax)`, `get_tax_information(isa_allowances)`, `list_records(savings_account)`, `get_module_analysis(savings)` — may fire depending on how aggressively the LLM batches related tools. Realistic minimum: 4-5 tools.
- `get_recommendations` — does NOT fire on the profile-not-found path.

**Line-by-line changes:**

| Line | Current | Change to | Source | Why |
|---|---|---|---|---|
| 3-6 description | "calls get_module_analysis(retirement) + get_recommendations" | "Universal KYC passes; retirement readiness passes; but RetirementAgent::analyze() (AGT-RET line 101) requires a retirement_profile entity which the seed does not create — only dc_pensions. Returns success_false. LLM should call list_records(dc_pension), get_module_analysis(retirement), and the four tax_information tools per merged QuerySchemas REQUIRED_TOOLS (retirement_contribution + IMPLICIT_RELATED tax_optimisation/savings_emergency/affordability). LLM identifies the missing retirement profile fields (target_retirement_age, target_retirement_income) and asks the user." | RDY-RET, AGT-RET 101, QSCH 420-425 + 160 | Match actual flow |
| 25-26 expected_classifications | `- retirement_contribution` | `expected_classification_shape: { primary: retirement_contribution, related: [tax_optimisation, savings_emergency, affordability], modules: [retirement, tax, savings, income] }` | CLS + QSCH 132, 160 | Multi-label |
| (new) | — | `expected_response_mode: recommendation` | AFYN-MODE line 58 | §3.1 |
| (new) | — | `expected_engine_call_level: module` | AFYN-ENG line 102 | §3.2 |
| (new) | — | `expected_kyc_state: passed` | KYC trace | §3.4 |
| 28-31 expected_tool_calls | 2 entries | Rewrite to merged set per `getRequiredToolsForClassification`. **5 required + 1 conditional**: `get_tax_information(pension_allowances) required:true result_path:happy`, `get_tax_information(income_definitions) required:true result_path:happy`, `get_module_analysis(retirement) required:true result_path:success_false result_message_contains:"No retirement profile found"`, `list_records(dc_pension) required:true result_path:happy`, `get_tax_information(income_tax) required:false condition:"from IMPLICIT_RELATED tax_optimisation"`, `get_recommendations required:false condition:"will not fire on profile-not-found"`. | QSCH 420-425 + IMPLICIT_RELATED merge | §3.5/3.6 |
| 33-35 expected_sse_events | `[content, done]` | §3.7. tool_use_count_min:3 (mandatory tax_information + module_analysis + list_records), max:8. | — | §3.7 |
| 37-40 expected_advice_response | block | Delete | POST-S101 | — |
| (new) | — | `expected_assistant_text`: must_contain ["For regulated advice...", "retirement profile" or "target retirement age" or "retirement age", explanation of pension contribution context]. must_not_contain forbidden phrases. minimum 200 chars. | INV-2.3.3, success_false text | §3.8 |
| 47-50 forbidden_tools | preserve | unchanged | — | — |
| 52 timing_budget_ms | `5000` | §3.9. Path=success_false. anthropic 7000, xai 15000 (this scenario fires more tools, allow more time). | §3.9 | — |
| 54-57 tags | preserve, add | Add `success-false-path`, `retirement-profile-required`, `multi-tool-tax-information` | — | — |

### 4.5 `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_estate_iht.yaml`

**User message:** *"How much inheritance tax will my estate pay?"*

**Classification:** primary `estate_iht` (QSCH line 316), related `[property]` (QSCH line 168), modules `[estate, property]`.

**KYC trace:**
- Universal: all 5 fields → PASS.
- Module `estate` → RDY-EST lines 67-81 blocking: DOB ✓, marital_status ✓, **at_least_one_asset** — seed has property + savings + investment so has_asset=true → PASS.
- Module `property` → KYC actionMap line 142 has no `property` mapping → returns [].
- KYC overall: **PASSED**.

**Engine path:**
- EstateAgent::analyze() line 58 loads user with relations, line 78 readinessService passes, line 101+ runs full IHT calculation.
- No secondary profile gate (no `if (! $user->ihtProfile)` block — the agent uses ihtProfile if present but doesn't gate on it).
- TRC path 4 — full happy with REQUIRED_KEYS `summary, asset_breakdown, iht_calculation, trust_recommendations, gifting_opportunities, life_cover, profile, missing_for_quality_advice` (TRC line 64-68).

**This is the cleanest happy-path scenario in the suite.**

**Tool firing prediction:**
- `get_tax_information(inheritance_tax)` — fires.
- `get_module_analysis(estate)` — fires, happy path with full IHT calculation. £600k property + £50k savings + £80k ISA - £100k mortgage = £630k gross, IHT calculation runs against NRB £325k + RNRB £175k.
- `list_records(property)` — fires.
- IMPLICIT_RELATED `property` adds `list_records(property)` (already present, dedup).
- `get_recommendations` — fires; estate is ready → can_proceed=true.

**Line-by-line changes:**

| Line | Current | Change to | Source | Why |
|---|---|---|---|---|
| 3-6 description | "calls get_module_analysis(estate) + get_recommendations" | "Universal KYC passes (DOB 1968 → age 58, married, employed, £75k income, £3500 expenditure); estate readiness passes (has at_least_one_asset via property + savings + investment); EstateAgent runs full IHT calculation against the £730k gross estate, applies NRB+RNRB, returns iht_calculation + trust_recommendations + gifting_opportunities. Expected tools: get_tax_information(inheritance_tax), get_module_analysis(estate), list_records(property), get_recommendations." | RDY-EST, AGT-EST, QSCH 470-474 | Match actual flow |
| 25-26 expected_classifications | `- estate_iht` | `expected_classification_shape: { primary: estate_iht, related: [property], modules: [estate, property] }` | CLS + QSCH 138, 168 | Multi-label |
| (new) | — | `expected_response_mode: recommendation` | AFYN-MODE line 64 | §3.1 |
| (new) | — | `expected_engine_call_level: module` | AFYN-ENG line 108 | §3.2 |
| (new) | — | `expected_kyc_state: passed` | KYC trace | §3.4 |
| 28-31 expected_tool_calls | 2 entries | Rewrite to 4 entries: `get_tax_information(inheritance_tax) required:true result_path:happy`, `get_module_analysis(estate) required:true result_path:happy`, `list_records(property) required:true result_path:happy`, `get_recommendations required:true condition:"estate ready, can_proceed=true"`. | QSCH 470-474 | §3.5/3.6 |
| 33-35 expected_sse_events | `[content, done]` | §3.7. tool_use_count_min:3, max:5. content_event_minimum:15 (the model has to walk through gross estate, NRB/RNRB, taper, gifts, recommendations). | — | §3.7 |
| 37-40 expected_advice_response | block | Delete | POST-S101 | — |
| (new) | — | `expected_assistant_text`: must_contain ["For regulated advice...", "£325" or "Nil Rate Band" or "NRB", "£175" or "Residence Nil Rate Band" or "RNRB", a £-figure for IHT liability that traces to iht_calculation.iht_liability per INV-2.3.2]. must_not_contain forbidden phrases. minimum 400 chars. | INV-2.3.3, INV-2.3.2 | §3.8 |
| 47-51 forbidden_tools | preserve | unchanged | — | — |
| 53 timing_budget_ms | `5000` | §3.9. Path=happy. anthropic 9000 (estate calculation is heavier), xai 18000. | §3.9 | — |
| 55-60 tags | preserve, add | Add `happy-path`, `iht-calculation`, `cleanest-recommendation-scenario` | — | — |

### 4.6 `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_goals_affordability.yaml`

**User message:** *"Can I afford to retire at 60?"*

**Classification — THIS IS THE TRICKY ONE.** Two patterns match this message:

- AFFORDABILITY at QSCH line 359: `/\bcan\s+i\s+afford\b/i` matches "Can I afford"
- RETIREMENT_READINESS at QSCH line 245: `/\b(when|can)\s+.*retire/i` matches "can I afford to retire"

Per CLS lines 153-172 `findAllMatches`, both match. The order in `KEYWORD_PATTERNS` (QSCH lines 192-372) is: `data_entry, navigation, billing, holistic_health, retirement_contribution, retirement_readiness, retirement_decumulation, protection_cover, ..., affordability, general`. **RETIREMENT_READINESS comes first**, so it becomes primary; AFFORDABILITY becomes secondary.

The YAML's `expected_classifications: [affordability]` is wrong about which is primary. The actual classification is:

- primary `retirement_readiness`
- related `[retirement_contribution, tax_optimisation]` (IMPLICIT for retirement_readiness, QSCH line 161) + `[affordability]` (secondary keyword match) + `[]` (IMPLICIT for affordability, QSCH line 177)
- modules `[retirement, tax, savings, income]`

**KYC trace (against retirement_readiness primary):**
- Universal: all 5 fields → PASS.
- Module `retirement` → RDY-RET → blocking PASS.
- Module `tax` → PRG line 126-142 → income + employment ✓ → PASS.
- Module `savings` → RDY-SAV → PASS.
- Module `income` → no checks.
- KYC: **PASSED**.

**Engine path:**
- RetirementAgent::analyze() → readinessService passes → line 101 `if (! $profile)` retirementProfile — **seed has no retirement_profile** (only dc_pensions, investment_accounts, savings_accounts) → **FAILS** → success_false "No retirement profile found".

**Tool firing prediction:**
- `get_module_analysis(retirement)` — fires, success_false.
- `get_tax_information(pension_allowances)`, `get_tax_information(state_pension)` — fire (RETIREMENT_READINESS's REQUIRED_TOOLS, QSCH 426-430).
- IMPLICIT_RELATED `retirement_contribution` adds 4 more tools (line 420-425), `tax_optimisation` adds 3 (line 481-485). Many dedup.
- `get_module_analysis(savings)` — added by AFFORDABILITY secondary match (QSCH line 497-499).
- `get_recommendations` — does not fire on retirement success_false.

**Note: the YAML expects `module: holistic` arg.** Per TOOLS line 124-138, the `get_module_analysis` enum is `[protection, savings, investment, retirement, estate, goals, holistic]`. So `holistic` IS a valid arg, but **it would invoke `orchestrateAnalysis` (the holistic engine)**, which is reserved for HOLISTIC_HEALTH classification only per AFYN-ENG. AFFORDABILITY's engine_call_level is `module`, not `holistic`. So the YAML asserts the wrong arg.

**Line-by-line changes:**

| Line | Current | Change to | Source | Why |
|---|---|---|---|---|
| 3-7 description | "classifies as `affordability`, calls get_module_analysis(holistic) + get_recommendations" | "Classifier matches both retirement_readiness and affordability keyword patterns; retirement_readiness comes first in KEYWORD_PATTERNS so wins as primary. Universal KYC passes; retirement readiness PASSES (DOB/marital/income); but RetirementAgent::analyze() requires retirement_profile (AGT-RET line 101) which the seed does not create — only dc_pensions/investment_accounts/savings_accounts. Returns success_false. Expected tools include the merged set from retirement_readiness + IMPLICIT_RELATED retirement_contribution + tax_optimisation + secondary affordability." | CLS, RDY-RET, AGT-RET 101, QSCH | The keyword-collision is the actual behaviour; document it |
| 25-26 expected_classifications | `- affordability` | **`expected_classification_shape: { primary: retirement_readiness, related: [retirement_contribution, tax_optimisation, affordability], modules: [retirement, tax, savings, income] }`** | CLS findAllMatches order + QSCH 161 + 177 | Match actual classifier output |
| (new) | — | `expected_response_mode: recommendation` | AFYN-MODE line 59 (retirement_readiness) | §3.1 |
| (new) | — | `expected_engine_call_level: module` | AFYN-ENG line 103 | §3.2 |
| (new) | — | `expected_kyc_state: passed` | KYC trace | §3.4 |
| 28-31 expected_tool_calls | `get_module_analysis(holistic)` + `get_recommendations` | Rewrite to merged retirement_readiness REQUIRED_TOOLS: `get_tax_information(pension_allowances) required:true result_path:happy`, `get_tax_information(state_pension) required:true result_path:happy`, `get_module_analysis(retirement) required:true result_path:success_false result_message_contains:"No retirement profile found"`, `get_module_analysis(savings) required:false condition:"from secondary affordability match"`, `get_tax_information(income_definitions) required:false condition:"from IMPLICIT_RELATED retirement_contribution"`. | QSCH 426-430 + 161 + 177 | §3.5/3.6 |
| 33-35 expected_sse_events | `[content, done]` | §3.7. tool_use_count_min:3, max:8. | — | §3.7 |
| 37-40 expected_advice_response | block | Delete | POST-S101 | — |
| (new) | — | `expected_assistant_text`: must_contain ["For regulated advice...", "retirement profile" or "target retirement age" — to confirm the LLM identified the missing entity]. must_not_contain forbidden phrases. minimum 250 chars. | INV-2.3.3 | §3.8 |
| 47-57 forbidden_tools | preserve, add | Add `update_record`, `delete_record`. Keep `create_what_if_scenario` (already there) — INV-2.5.6 explicit exception — but verify against ProtectionAgent test suite. | INV-2.1.2, INV-2.5.6 | — |
| 59 timing_budget_ms | `5000` | §3.9. Path=success_false. anthropic 8000, xai 17000 (more tools, more analysis). | §3.9 | — |
| 61-66 tags | preserve, add | Add `success-false-path`, `keyword-collision-retirement-readiness-vs-affordability`, `multi-implicit-related` | — | — |

---

## Section 5 — Multi-entity scenarios (4 YAMLs in 03-multi-entity/)

These are different — they target Onboarding Fyn (`onboarding_fyn_step: asset_capture`), not Advice Fyn. The dispatch decision is at `AiChatController::sendMessage` based on `users.onboarding_completed = false`. None of the advice-mode infrastructure (AdviceFyn, KycGateChecker, ProtectionAgent::analyze) fires.

What fires instead: `OnboardingChatDirector::handleUserMessage` → asset_capture handler → `OnboardingPromptBuilder::buildAssetCapturePrompt` → LLM with the create_* tool catalogue → entity-by-entity write tool calls.

**Defects in these YAMLs are different:**

### 5.1 `protection_2x_known_providers.yaml`, `protection_2x_unknown_providers.yaml`, `savings_3x_mixed.yaml`, `pensions_2x_schemes.yaml`

**Common defects across all 4:**

| Field | Current | Should be | Source |
|---|---|---|---|
| `expected_classifications: [data_entry]` | OK as-is | preserve — DATA_ENTRY is what `OnboardingChatDirector` operates under for asset_capture (per CANON line 11) | — |
| `expected_tool_calls: 2-3 create_* entries` | Argument shape | **Add `result_path: success` to each entry**. Add `expected_db_writes_persistent: true` to flag that these create writes (unlike eval recordings, which restore between provider runs — see EvalRecordCommand line 195-200). | — |
| `expected_sse_events` | List of `tool_use, entity_created, content, done` events | Acceptable but should add `must_emit_exactly: { entity_created: N }` where N = number of expected entities. The current list has order assertions which are not actually enforced by AssertionHelpers. | — |
| `expected_db_writes` | `protection_policies: 2` etc. | preserve | — |
| `forbidden_outputs` | list of strings | preserve, but ALSO add: `"For regulated advice personal to your circumstances..."` — INV-2.3.3 signposting must NOT appear on capture-mode responses, this is an onboarding turn. | INV-2.3.3 |
| `forbidden_tools` | excludes wrong-module create_* | preserve, but ADD `delegate_to_capture` — onboarding mode does not handoff to itself; handoff is advice→onboarding only (CANON line 26-29). | CANON, AFYN handoff path |
| `eval_focus` block | OK | preserve — recall/precision/value floors are correct per `fyn_eval.php` config | — |
| `timing_budget_ms` | 8000-9000ms | Per-provider split: anthropic 8000, xai 18000 (xAI's reasoning model is slower on multi-tool turns). | Sessions evidence |
| (new) | — | Add `expected_response_mode: factual` (DATA_ENTRY is factual per AFYN-MODE line 73). | AFYN-MODE | 
| (new) | — | Add `expected_engine_call_level: factual` (no engine call on capture turns per AFYN-ENG line 116). | AFYN-ENG |
| (new) | — | Add `expected_kyc_state: bypass` — KYC bypass for BYPASS_TYPES (KYC line 36-38, QSCH line 108). | KYC, QSCH |

**Specific to `protection_2x_unknown_providers.yaml`:** the seed is correct (no KNOWN_PROVIDERS list constraint applies — `provider` is a free-text string). But the YAML's forbidden_outputs should add `"I don't recognise that provider"` AND `"please use a known provider"` AND `"Quilter Mutual is not a recognised provider"` — the test is specifically that providers off any known list are accepted as-is. Current line 42-43 covers this partially but should be more explicit.

**Specific to `pensions_2x_schemes.yaml`:** the YAML asserts `scheme_type: workplace` for both pensions. The user message says "old workplace pension at Standard Life" + "current workplace pension is with Aviva". The "old" qualifier should be captured in the `is_active` boolean (default false for old) per `DCPension` model. Add to expected_tool_calls args: `is_active: false` for the Standard Life entry, `is_active: true` for the Aviva entry. Otherwise the asserter cannot verify the model preserved the temporal qualifier.

---

## Section 6 — New scenarios that must exist

The YAML suite is missing four canonical-behaviour scenarios that the live user flow demands.

### 6.1 `09-canonical-behaviour/advice_kyc_blocked_no_dob.yaml` (NEW)

**Why:** the KYC universal-block path is currently asserted only via `tests/Unit/Services/AI/AdvicePromptBuilderStructuralLayersTest.php` (Task 1 acceptance). It needs an end-to-end browser-mode equivalent so a regression in the KYC prompt rendering fails an eval.

**Shape:**

```yaml
id: advice_kyc_blocked_no_dob
category: 09-canonical-behaviour
description: |
  KYC universal block path. User asks an advice question without DOB seeded.
  KycGateChecker returns BLOCKED with route /profile. LLM does NOT call any
  analysis tools and produces text directing the user to /profile.

input:
  turns:
    - user: "How much should I be saving each month?"

seed:
  user:
    first_name: Test
    surname: User
    marital_status: married
    employment_status: employed
    annual_employment_income: 50000
    monthly_expenditure: 2500
    onboarding_completed: true
    # NOTE: date_of_birth deliberately omitted

expected_classification_shape:
  primary: savings_emergency       # or similar — verify with classifier
  related: [affordability]
  modules: [savings, income]

expected_response_mode: recommendation
expected_engine_call_level: module
expected_kyc_state: blocked
expected_kyc_missing:
  - { label: "Date of birth", route: "/profile" }

expected_tool_calls: []
expected_tool_calls_absent:
  - get_module_analysis
  - get_recommendations
  - generate_financial_plan

expected_assistant_text:
  must_contain_substrings:
    - "date of birth"
    - "/profile"
  must_not_contain_substrings:
    - "For regulated advice"        # not a recommendation, no signposting
    - "I think you should"

forbidden_tools:
  - get_module_analysis
  - get_recommendations
  - navigate_to_page                # stripped from advice mode

timing_budget_ms:
  anthropic: { kyc_blocked: 5000 }
  xai: { kyc_blocked: 12000 }

tags:
  - canonical-behaviour
  - kyc-universal-blocked
  - regression-band-0
```

**Source:** KYC lines 94-130 (universal requirements), KYC blocked prompt builder lines 214-260, AFYN-WRITE line 151 (navigate_to_page stripped).

### 6.2 `09-canonical-behaviour/advice_protection_profile_setup_handoff.yaml` (NEW)

**Why:** the `success_false → user fixes data → re-asks → happy` flow is the real user journey when the protection-profile gate fails. This scenario exercises the full handoff: the LLM correctly identifies the missing fields, the user provides them, and the second turn classifies as data_entry → AdviceFyn routes through `delegate_to_capture` → OnboardingChatDirector::handleInlineCapture → ProtectionProfile created → user re-asks the original question → happy path runs.

**Shape (3 turns):**

```yaml
id: advice_protection_profile_setup_handoff
category: 09-canonical-behaviour
description: |
  Two-Fyn handoff via delegate_to_capture. Turn 1: user asks protection
  question; agent's profile gate fails; LLM asks for the missing fields.
  Turn 2: user provides spouse income + 2 dependants + £200k mortgage in
  one message; AdviceFyn classifies as data_entry, hands off to onboarding
  inline capture, ProtectionProfile + FamilyMember rows created. Turn 3:
  user re-asks the original question; happy path runs.

input:
  turns:
    - user: "Am I covered enough for protection?"
    - user: "My spouse earns £30k, we have 2 children aged 5 and 8, and our mortgage balance is £200,000."
    - user: "OK so given that — am I covered enough?"

seed:
  user:
    # Same as advice_protection_cover

expected_per_turn:
  turn_1:
    expected_response_mode: recommendation
    expected_kyc_state: passed
    expected_tool_calls:
      - { tool: get_module_analysis, args: { module: protection }, result_path: success_false }
    expected_assistant_text:
      must_contain_substrings: ["spouse", "dependants", "mortgage"]

  turn_2:
    expected_response_mode: factual          # data_entry routes through onboarding
    expected_classification_shape:
      primary: data_entry
    expected_tool_calls:
      - { tool: delegate_to_capture, args: { entity_types: [protection_profile, family_member] } }
      # then onboarding inline-capture writes:
      - { tool: capture_spouse_details, ... }
      - { tool: capture_dependants, ... }
      - { tool: update_profile, args: { mortgage_balance: 200000 } }    # via ProtectionProfile
    expected_db_writes:
      protection_profiles: 1
      family_members: 2

  turn_3:
    expected_response_mode: recommendation
    expected_tool_calls:
      - { tool: get_module_analysis, args: { module: protection }, result_path: happy }
      - { tool: list_records, args: { record_type: life_insurance }, result_path: happy }
      - { tool: get_recommendations, result_path: happy }
    expected_assistant_text:
      must_contain_substrings:
        - "For regulated advice"
        - "£" # actual coverage gap figure traced to engine output

forbidden_outputs:
  # Turn 2 specifically — onboarding handoff must be invisible
  - "switching to capture mode"     # INV-2.4.1
  - "let me hand you over"           # INV-2.4.1

timing_budget_ms:
  per_turn:
    anthropic: { turn_1: 6000, turn_2: 9000, turn_3: 8000 }
    xai: { turn_1: 14000, turn_2: 18000, turn_3: 16000 }

tags:
  - canonical-behaviour
  - two-fyn-handoff
  - delegate-to-capture
  - regression-band-0
```

**Source:** CANON lines 26-29 (the handoff path), AFYN handle() line 322-387 (wrapStream intercepts handoff), CSJTODO §"Top laws".

### 6.3 `09-canonical-behaviour/advice_holistic_health.yaml` (NEW)

**Why:** the only `engine_call_level: holistic` classification per AFYN-ENG. `orchestrateAnalysis` is the engine method that fires **only** here per INV-2.3.6. There's no scenario testing it.

**Shape:**

```yaml
id: advice_holistic_health
category: 09-canonical-behaviour
description: |
  HOLISTIC_HEALTH classification — the only path that calls
  CoordinatingAgent::orchestrateAnalysis (per INV-2.3.6). Tests the
  engine_call_level=holistic branch and the cross-module HOLISTIC_PRIORITY
  ordering (QSCH 670-679).

input:
  turns:
    - user: "How am I doing financially overall?"

seed:
  user:
    # Full universal KYC + a LifeInsurancePolicy + SavingsAccount + DCPension + Property
    # so multiple modules are READY and ranked_recommendations has variety
  # Use peak_earners-like seed: married, 45, £85k income, £4k expenditure, mortgage,
  # ISA, pension, life policy. This is the canonical "comfortable but with gaps" shape.

expected_classification_shape:
  primary: holistic_health
  related: [savings_emergency, affordability, tax_optimisation]
  modules: [savings, investment, retirement, protection, estate, goals, tax, property, income]

expected_response_mode: recommendation
expected_engine_call_level: holistic
expected_kyc_state: passed

expected_tool_calls:
  - { tool: get_recommendations, required: true, result_path: happy }
  - { tool: get_module_analysis, args: { module: holistic }, required: true, result_path: happy }
  - { tool: generate_financial_plan, required: true, result_path: happy }
expected_orchestrate_analysis_called: true     # asserts the holistic engine fired

expected_tool_result_holistic_keys:
  - user_id
  - analysis_date
  - module_analysis
  - available_surplus
  - ranked_recommendations
  - cashflow_allocation
  - summary
  # Per TRC line 73-77 REQUIRED_KEYS['holistic']

expected_assistant_text:
  must_contain_substrings:
    - "For regulated advice"
  # The HOLISTIC_PRIORITY ordering should be visible in the response.
  must_contain_at_least_one_of:
    - "emergency fund"
    - "protection"
    - "pension"
    - "ISA"
    - "estate"

timing_budget_ms:
  anthropic: { happy: 12000 }
  xai: { happy: 25000 }

tags:
  - canonical-behaviour
  - holistic-engine
  - orchestrate-analysis
  - regression-band-0
```

**Source:** AFYN-ENG line 96 (only HOLISTIC_HEALTH → holistic), QSCH 144 (modules), 169 (related), 670-679 (HOLISTIC_PRIORITY), TRC 73-77 (required keys).

### 6.4 `09-canonical-behaviour/advice_out_of_remit_medical.yaml` (NEW)

**Why:** INV-2.3.4 canonical refusal must be regression-protected. Currently no scenario asserts the exact refusal shape.

**Shape:**

```yaml
id: advice_out_of_remit_medical
category: 09-canonical-behaviour
description: |
  Non-financial topic detection. Classifier returns out_of_remit per
  CLS lines 35-57 (Medical advice patterns). AdviceFyn::handle short-
  circuits to canonical refusal per INV-2.3.4. Zero tool calls.

input:
  turns:
    - user: "I have a headache, what should I take?"

seed:
  user:
    # standard KYC-passing seed

expected_classification_shape:
  primary: out_of_remit
  detected_topic: "Medical advice"
  related: []
  modules: []

expected_response_mode: out_of_remit
expected_engine_call_level: factual
expected_kyc_state: bypass

expected_tool_calls: []
expected_tool_calls_absent:
  - get_module_analysis
  - get_recommendations
  - list_records
  - get_tax_information

expected_assistant_text:
  exact_match: "I'm able to help you with your finances. Medical advice is out of scope."
  # Verbatim per INV-2.3.4. The substitution {context} = detected_topic.

forbidden_outputs:
  - "For regulated advice"          # signposting MUST NOT append (INV-2.3.3)
  - "talk to a doctor"               # we don't redirect, we refuse politely
  - "see your GP"                    # same

timing_budget_ms:
  anthropic: { factual: 1000 }       # short-circuit, no LLM call expected
  xai: { factual: 1000 }

tags:
  - canonical-behaviour
  - out-of-remit
  - canonical-refusal
  - regression-band-0
```

**Source:** INV-2.3.4 lines 158-162, CLS lines 35-57 OUT_OF_REMIT_PATTERNS, AFYN handle() lines 177-201 (short-circuit branch).

---

## Section 7 — Admin dashboard delta logic that must be updated

Source: `app/Http/Controllers/Api/Admin/EvalRecordingController.php::buildDelta` (lines 246-282 per fixEvalTask.md Task 4).

The current delta logic asserts `expected_tool_calls` ⊆ `actual_tool_calls` and emits "Provider partially completed" when a tool is missing. This is wrong for the rewritten YAMLs because:

- `result_path: success_false` and `result_path: readiness_blocked` paths legitimately have FEWER tool calls than the YAML's `required: true` list, because downstream tools (`get_recommendations`) correctly do not fire.
- `expected_tool_calls_absent` must produce a NEW assertion type (a tool that DID fire when it should not have).
- `expected_kyc_state: blocked` runs should expect ZERO analysis tool calls; the dashboard's "no tool calls" hint should be SUPPRESSED for these and replaced with a hint that confirms the KYC block worked.

**Required dashboard changes:**

| File:Line | Current | Change |
|---|---|---|
| `EvalRecordingController.php:246-282` | "missing tool" hint | Read `expected_tool_calls[*].required` AND `expected_tool_calls[*].result_path`. A "missing tool" with `required: true` AND `result_path != success_false/readiness_blocked` is a real fail. A "missing tool" with `required: false` (conditional) is informational only. |
| Same controller | (no equivalent) | NEW: detect `expected_tool_calls_absent` — any tool in that list that DID fire produces a "forbidden tool fired" hint. |
| Same controller | (no equivalent) | NEW: detect `expected_kyc_state: blocked` runs. If kyc_status is BLOCKED in the captured prompt AND no analysis tools fired AND `expected_tool_calls` is empty, mark as PASS rather than FAIL even though zero tools fired. |
| Same controller | (no equivalent) | NEW: detect `expected_response_mode: out_of_remit` runs. Assert exact-match `assistant_text` against INV-2.3.4 canonical refusal. Anything else is a fail. |
| `EvalProviderRun` model | (no field) | ADD `kyc_state` (passed/bypass/blocked), `kyc_missing` (jsonb), `tool_result_paths` (jsonb mapping tool_name → path enum), `engine_call_level_actual` (holistic/module/factual). Extracted from the captured ai_messages.system_prompt + tool_call metadata at recording time. (fixEvalTask.md §3c already proposed this — make it concrete now.) |

---

## Section 8 — Meta-tests that must enforce expectation integrity

Architecture-level tests that prevent the same kind of drift from happening again. All under `tests/Architecture/` and run as `--testsuite=Architecture`.

### 8.1 `tests/Architecture/EvalScenarioToolListMatchesQuerySchemasTest.php` (NEW)

For every scenario YAML under `01-query-types/`, parse its `expected_classification_shape.primary`, look up `QuerySchemas::getRequiredToolsForClassification`, and assert every tool in that list appears in `expected_tool_calls` (either as `required: true` or as a member of `IMPLICIT_RELATED` merged-in).

**Source:** QSCH 716-725.

### 8.2 `tests/Architecture/EvalScenarioResponseModeConsistencyTest.php` (NEW)

For every scenario YAML, parse its `expected_response_mode` and `expected_engine_call_level` and verify they match `AdviceFyn::classifyResponseMode($primary)` and `AdviceFyn::engineCallLevel($primary)` exactly.

**Source:** AFYN-MODE, AFYN-ENG.

### 8.3 `tests/Architecture/EvalScenarioForbiddenToolsContainsAdviceWriteToolsTest.php` (NEW)

For every advice-mode YAML (where `expected_response_mode != out_of_remit` AND seed has `onboarding_completed: true`), assert `forbidden_tools` is a superset of `AdviceFyn::WRITE_TOOLS` filtered to the modules in `expected_classification_shape.modules`.

**Source:** AFYN-WRITE, INV-2.1.2.

### 8.4 `tests/Architecture/EvalScenarioKycBlockedHasAbsentToolsTest.php` (NEW)

For every YAML with `expected_kyc_state: blocked`, assert `expected_tool_calls_absent` includes at least: `get_module_analysis`, `get_recommendations`, `generate_financial_plan`. Also assert `expected_tool_calls` does NOT contain any of those (i.e. the YAML doesn't accidentally require what it forbids).

**Source:** KYC blocked instructions (line 214-260).

### 8.5 `tests/Architecture/EvalScenarioSignpostingMatchesResponseModeTest.php` (NEW)

For every YAML, parse `expected_response_mode` and `expected_assistant_text.must_contain_substrings`. If mode == `recommendation`, the FCA signposting string must be in the must_contain list. If mode == `factual` or `out_of_remit`, it must NOT be.

**Source:** INV-2.3.3.

### 8.6 `tests/Architecture/EvalScenarioTimingBudgetIsPathAwareTest.php` (NEW)

For every YAML, assert `timing_budget_ms` is an object with `anthropic` and `xai` keys, each with at least one path key (`happy`, `success_false`, `readiness_blocked`, `kyc_blocked`, `factual`). Reject the legacy `timing_budget_ms: <int>` shape.

**Source:** §3.9.

---

## Section 9 — Order of operations + acceptance gates

Tasks listed in execution order. Every task is self-contained.

| # | Task | Blocks | Acceptance |
|---|---|---|---|
| **9.1** | Update `tests/Feature/Fyn/Eval/AssertionHelpers.php` to support new YAML keys: `expected_response_mode`, `expected_engine_call_level`, `expected_classification_shape`, `expected_kyc_state` + `expected_kyc_missing`, `expected_tool_result_path` per tool, `expected_tool_calls_absent`, `expected_assistant_text` (with sub-keys), `expected_orchestrate_analysis_called`, `expected_per_turn`. | All YAML rewrites | Pest unit tests for each helper assertion pass; helper rejects legacy `expected_advice_response` / single-int `timing_budget_ms` with a clear deprecation message. |
| **9.2** | Add 4 architecture meta-tests per Section 8. | All YAML rewrites | All 4 meta-tests pass against the rewritten YAMLs. |
| **9.3** | Rewrite the 6 advice YAMLs per §4. | Re-recording fixtures (CSJTODO S1.2.k) | Each YAML parses; meta-tests pass; classifier-output assertions verified via `php artisan tinker` against each user message. |
| **9.4** | Add 4 new canonical-behaviour YAMLs per §6. | Re-recording fixtures | Same as 9.3. |
| **9.5** | Update 4 multi-entity YAMLs per §5.1 (light-touch — add response_mode, engine_call_level, kyc_state). | Re-recording fixtures | Same as 9.3. |
| **9.6** | Update `EvalProviderRun` model + `EvalRecordingController::buildDelta` per Section 7. | Dashboard correctness | Dashboard for session #20 (re-recorded) shows the new fields; KYC-blocked scenarios show as PASS with zero tool calls; success_false scenarios show the result_message correctly. |
| **9.7** | Author 14 onboarding state-machine eval scenarios per §10 + add `recording_mode: deterministic` flag to `EvalRecordCommand` so state-machine scenarios bypass LLM calls. | Sprint 1 onboarding regression net | All 14 YAMLs parse; meta-tests pass; `php artisan eval:record onboarding_<state> --mode=deterministic` runs without invoking a provider; SSE shape + DB writes asserted against the state machine's transition table. |
| **9.8** | Author 14 write-tool-family handoff scenarios per §11 + add `inherits` fragment-inheritance support to AssertionHelpers + create `_handoff_invariants.fragment.yaml` shared fragment. | Sprint 1 two-Fyn handoff regression net | All 14 YAMLs parse; meta-tests pass; the shared fragment's INV-2.4.x assertions fire across all 14. |
| **9.9** | Author 13 per-state resume scenarios + 3 edge-case resume scenarios per §12. Cross-link each to BS-04 via `linked_browser_scenario` field. | Sprint 1 resume regression net | All 16 YAMLs parse; meta-tests pass; `OnboardingChatDirector::resumeSummary($state)` is called from the asserter and matches each scenario's `expected_quick_replies.prompt_text.must_match_resume_summary` substring. |
| **9.10** | Re-record all 28 scenarios (10 existing rewritten + 4 new canonical + 14 handoff + state-machine deterministic). Multi-entity stays at existing fixtures because their behaviour is provider-driven. | Sprint 1 S1.2.k completion + Sprint 1 S1.7 partial | Every scenario's session row in `eval_recording_sessions` has status=completed; every fixture file exists; admin dashboard shows expected-vs-actual deltas with zero false-FAILs across the full bank. |
| **9.11** | Hard gate: every meta-test green; every YAML's `expected_classification_shape` verified against `php artisan tinker` classifier output; every `expected_kyc_state` verified against `KycGateChecker::check` output for the seeded user; every state-machine YAML's `expected_state_transition.to` verified against `OnboardingStateMachine::transition`; every handoff YAML's `expected_handoff_path` observable in the captured SSE. | Sprint 1 S1.10 verification rollup | Document the verification in `April/April27Updates/eval-rewrite-verification.md` (separate file, not this one). |

---

## Section 10 — Onboarding state-machine eval scenarios (IN SCOPE)

**Source-of-truth: `app/Services/Onboarding/OnboardingStateMachine.php` lines 36-76 (state constants), 83-291 (transition table). Driver: `app/Services/Onboarding/OnboardingChatDirector::handleUserMessage`. Browser-mode equivalent: BS-01 (the long path-choice-to-done walk). Test-strategy index: `April/April24Updates/spec/03-test-strategy.md` lines 134-139 (INV-2.2.1 through INV-2.2.6).**

The state machine has 17 states. Browser scenario BS-01 walks the entire path end-to-end as one test (`tests/Browser/scenarios/BS-01-onboarding-path-choice-to-done.php`). The Rubric-B eval suite needs **per-state-transition** scenarios so a regression in one state's prompt builder, parking logic, or commit handler fails one specific scenario rather than blowing up BS-01 with no signal about which state broke.

### 10.1 The 17 states + which need a scenario

| State (constant) | Turn type (`OnboardingChatDirector::buildResponse`) | Expected SSE shape | Needs eval scenario? | Source line |
|---|---|---|---|---|
| `STATE_PATH_CHOICE` (`path_choice`) | bubbles | `quick_replies` SSE with 2 bubbles | YES — entry point per INV-2.2.5 | line 36 |
| `STATE_JOURNEY_SELECTION` (`journey_selection`) | bubbles | 4 bubbles for 4 journeys | YES | line 38 |
| `STATE_FOCUS_SELECTION` (`focus_selection`) | bubbles | bubbles for focus topics | YES — alternate to journey_selection | line 40 |
| `STATE_BASE_PERSONAL` (`base_personal`) | grouped_extract | text + persona_state_change-internal | YES — first grouped extract | line 44 |
| `STATE_BASE_SPOUSE` (`base_spouse`) | grouped_extract | direct-write to `users` + `family_members` + `SpousePermission` | YES — covered by INV-2.2.2 | line 46 |
| `STATE_BASE_DEPENDANTS` (`base_dependants`) | bubbles | Yes/No bubbles | YES | line 48 |
| `STATE_BASE_DEPENDANTS_DETAIL` (`base_dependants_detail`) | grouped_extract | direct-write per dependant | YES — multi-entity dependant capture | line 50 |
| `STATE_BASE_EMPLOYMENT` (`base_employment`) | grouped_extract | direct-write `users.employment_status` + income | YES | line 52 |
| `STATE_BASE_WORK` (`base_work`) | grouped_extract | direct-write `users.occupation` + work fields | YES — fires only when employment branched | line 57 |
| `STATE_BASE_EMPLOYMENT_MORE` (`base_employment_more`) | grouped_extract | direct-write spouse income / additional sources | YES | line 64 |
| `STATE_BASE_RETIREMENT_DATE` (`base_retirement_date`) | grouped_extract | direct-write `retirement_profile.target_retirement_age` | YES — tests RetirementProfile creation in onboarding | line 59 |
| `STATE_BASE_EXPENDITURE` (`base_expenditure`) | grouped_extract | direct-write `expenditure_profile` row | YES — covers INV-2.2.6 parked-facts flush at expenditure commit | line 61 |
| `STATE_PROFILE_REVIEW_FAMILY` (`profile_review_family`) | bubbles | review summary + Yes/No | YES — review/edit branch | line 66 |
| `STATE_PROFILE_REVIEW_EXPENDITURE` (`profile_review_expenditure`) | bubbles | review summary + Yes/No | YES — review/edit branch | line 68 |
| `STATE_ASSET_CAPTURE` (`asset_capture`) | free_text | LLM with create_* tool catalogue | YES — but **already covered** by `03-multi-entity/protection_2x_known_providers`, `03-multi-entity/savings_3x_mixed`, `03-multi-entity/pensions_2x_schemes`, `03-multi-entity/protection_2x_unknown_providers` | line 70 |
| `STATE_ADD_MORE` (`add_more`) | bubbles | Yes/No bubbles for "another asset?" | YES — terminal-loop branch | line 72 |
| `STATE_DONE` (`done`) | terminal | `done` SSE + `users.onboarding_completed = true` flip | YES — INV-2.1.3 single-flip invariant | line 74 |

**14 NEW Rubric-B scenarios needed** (asset_capture covered by 4 existing multi-entity scenarios).

### 10.2 Authoring template — apply to every state

Filename pattern: `tests/Feature/Fyn/Eval/scenarios/02-preview-personas/onboarding_<state_id>.yaml` (per `April/April24Updates/spec/03-test-strategy.md` Rubric-B category map — onboarding scenarios live in category 02 because they exercise persona-tied flows).

```yaml
id: onboarding_<state_id>
category: 02-preview-personas
description: |
  State <STATE_NAME> drives a <bubbles|grouped_extract|free_text|terminal> turn.
  OnboardingChatDirector::buildResponse for this state emits <expected_sse>.
  Direct-writes go to <table_name> (per INV-2.2.2). Parked facts handled per
  INV-2.2.6.

input:
  turns:
    - user: "<message that triggers a transition out of this state>"

seed:
  user:
    first_name: Test
    surname: User
    onboarding_completed: false
    onboarding_fyn_step: <STATE_NAME>
    # Plus minimum fields needed for the state to be valid (e.g. base_spouse
    # requires marital_status: married already set).

# OnboardingChatDirector turns are FACTUAL by definition — the prompt is
# driven by the state machine, not by classification. There is no advice
# response mode. These three keys document that fact.
expected_response_mode: factual
expected_engine_call_level: factual
expected_kyc_state: bypass

expected_classification_shape:
  primary: data_entry
  related: []
  modules: []

# Per-state expected SSE shape — NOT free-form. Each state's
# OnboardingChatDirector::buildResponse method returns a fixed shape.
expected_sse_events:
  must_contain_types: [<state-specific>]
  must_emit_exactly_once: [done]
  must_not_emit:
    - persona_state_change   # INV-2.4.1
    - handoff                # INV-2.4.1

# Per-state expected DB writes (post-commit). These are the rows that MUST
# exist after the turn, sourced from the state's commit handler.
expected_db_writes:
  <table>: <count>
  # e.g. for base_spouse: family_members: 1, users (spouse): 1, spouse_permissions: 2 (bidirectional)

# State machine asserts: after this turn, what state should the user be in?
expected_state_transition:
  from: <STATE_NAME>
  to: <NEXT_STATE>     # Per OnboardingStateMachine line 83-291 transition table

# Parked-facts assertion (INV-2.2.6) — what keys should be in
# ai_conversations.onboarding_parked_facts BEFORE and AFTER this turn?
expected_parked_facts:
  before: [<key1>, <key2>]
  after_commit: []     # Empty after a commit point per INV-2.2.6

forbidden_outputs:
  - "switching to capture mode"
  - "let me hand you over"
  - "For regulated advice"     # INV-2.3.3 — onboarding turns NEVER signpost

forbidden_tools:
  # Onboarding mode does not use AdviceFyn tools
  - get_module_analysis
  - get_recommendations
  - get_tax_information
  - delegate_to_capture        # onboarding does not handoff to itself

timing_budget_ms:
  anthropic: { factual: 4000 }
  xai: { factual: 8000 }

tags:
  - canonical-behaviour
  - onboarding-state-machine
  - state-<state_id>
  - regression-band-0
```

### 10.3 The 14 specific scenarios — per-state assertions

Listed in canonical traversal order. For each: the user-input that triggers the transition, the expected destination state, and any state-specific assertions that override the template above.

#### 10.3.1 `onboarding_path_choice.yaml`
- **Input:** `"Follow a journey"` (bubble click — emitted as text content).
- **Expected transition:** PATH_CHOICE → JOURNEY_SELECTION (per OnboardingStateMachine line 83-93).
- **Specific assertion:** `expected_sse_events.must_contain_types: [quick_replies]` with 4 journey bubbles.
- **Parked facts:** none before, none after.

#### 10.3.2 `onboarding_journey_selection.yaml`
- **Input:** `"Protecting What Matters"`.
- **Expected transition:** JOURNEY_SELECTION → BASE_PERSONAL.
- **Specific assertion:** `expected_db_writes: { ai_conversations: { metadata.journey_selected: protection } }` — verify the journey choice is recorded.

#### 10.3.3 `onboarding_focus_selection.yaml`
- **Input:** `"Pick a focus"` then a focus-bubble click.
- **Expected transition:** FOCUS_SELECTION → BASE_PERSONAL.
- **Note:** alternate entry; tests INV-2.2.5 path divergence.

#### 10.3.4 `onboarding_base_personal.yaml`
- **Input:** multi-line `"My name is Test User, born 15 April 1985, married."`
- **Expected transition:** BASE_PERSONAL → BASE_SPOUSE (per state machine `marital_status === 'married'` branch line 422-432).
- **Specific assertion:** `expected_db_writes: { users: { date_of_birth: '1985-04-15', marital_status: 'married' } }` — direct-write per INV-2.2.2.

#### 10.3.5 `onboarding_base_spouse.yaml`
- **Input:** `"Angela, DOB 12 January 1976, email aslater@gmail.com"`.
- **Expected transition:** BASE_SPOUSE → BASE_DEPENDANTS.
- **Specific assertion:** `expected_db_writes: { family_members: 1 (spouse), users: 1 (spouse account creation), spouse_permissions: 2 }`.
- **Mail assertion:** `Mail::assertSent(SpouseAccountCreated::class)` per INV-2.2.2.
- **This is the same shape as BS-02** but as a Rubric-B replay-able scenario.

#### 10.3.6 `onboarding_base_dependants.yaml`
- **Input:** `"Yes"` (Yes/No bubble for "do you have dependants?").
- **Expected transition:** BASE_DEPENDANTS → BASE_DEPENDANTS_DETAIL.
- **Specific assertion:** parked_facts.before = `{has_dependants_question_pending: true}`; after = parked at higher level.

#### 10.3.7 `onboarding_base_dependants_detail.yaml`
- **Input:** multi-line `"James born 2018, Sophie born 2020, both depend on me financially."`
- **Expected transition:** BASE_DEPENDANTS_DETAIL → PROFILE_REVIEW_FAMILY.
- **Specific assertion:** `expected_db_writes: { family_members: 2 (children), with is_dependent=true }`.

#### 10.3.8 `onboarding_profile_review_family.yaml`
- **Input:** `"Looks good"` (review-confirm bubble).
- **Expected transition:** PROFILE_REVIEW_FAMILY → BASE_EMPLOYMENT.
- **Specific assertion:** `expected_sse_events.must_contain_substring: "Angela"` AND `"James"` AND `"Sophie"` — the review summary surfaces all family members previously captured.

#### 10.3.9 `onboarding_base_employment.yaml`
- **Input:** `"Employed, software engineer at Acme Ltd, £75,000 a year."`
- **Expected transition:** BASE_EMPLOYMENT → BASE_WORK (employed branch) OR → BASE_EMPLOYMENT_MORE (other branches per state machine line 198-214).
- **Specific assertion:** `expected_db_writes: { users: { employment_status: 'employed', occupation: 'software engineer', annual_employment_income: 75000 } }`.

#### 10.3.10 `onboarding_base_work.yaml`
- **Input:** any work-detail follow-up (specific to employed branch).
- **Expected transition:** BASE_WORK → BASE_EMPLOYMENT_MORE.

#### 10.3.11 `onboarding_base_employment_more.yaml`
- **Input:** `"My spouse earns £30k as a teacher, also a small dividend income from a side business of about £2,000 a year."`
- **Expected transition:** BASE_EMPLOYMENT_MORE → BASE_RETIREMENT_DATE.
- **Specific assertion:** `expected_db_writes: { users: { annual_dividend_income: 2000 }, spouse_user: { annual_employment_income: 30000 } }` — INV-2.2.2 cross-entity.

#### 10.3.12 `onboarding_base_retirement_date.yaml`
- **Input:** `"I'd like to retire at 60 on £40,000 a year."`
- **Expected transition:** BASE_RETIREMENT_DATE → BASE_EXPENDITURE.
- **Specific assertion:** `expected_db_writes: { retirement_profiles: { target_retirement_age: 60, target_retirement_income: 40000 } }`. **This is critical** — it's the entity that `advice_retirement_contribution.yaml` flagged as missing (AGT-RET line 101). The fact that onboarding creates it here proves the post-onboarding advice happy-path is achievable.

#### 10.3.13 `onboarding_base_expenditure.yaml`
- **Input:** `"About £2,500 a month all in."`
- **Expected transition:** BASE_EXPENDITURE → PROFILE_REVIEW_EXPENDITURE.
- **Specific assertion:** `expected_db_writes: { expenditure_profiles: { total_monthly_expenditure: 2500, breakdown_by_category: ... } }`. **Parked-facts INV-2.2.6 critical assertion:** `expected_parked_facts.after_commit: []` — the parked-keys map for expenditure must be empty after the commit, otherwise the next prompt re-asks.

#### 10.3.14 `onboarding_profile_review_expenditure.yaml`
- **Input:** `"Looks good"`.
- **Expected transition:** PROFILE_REVIEW_EXPENDITURE → ASSET_CAPTURE.

#### 10.3.15 `onboarding_add_more.yaml`
- **Input:** `"No, that's everything for now."` (or "Finish for now" bubble).
- **Expected transition:** ADD_MORE → DONE.
- **Specific assertion:** `expected_db_writes: { users: { onboarding_completed: true } }` per **INV-2.1.3** — the single-flip lifecycle assertion. Cross-reference the `OnboardingStateMachine::transition` test in `tests/Feature/Onboarding/OnboardingCompletionFlagTest.php`.

#### 10.3.16 `onboarding_done_terminal.yaml`
- **Input:** any (this state should be unreachable as input; assert it returns the same dashboard-redirect SSE).
- **Specific assertion:** `expected_sse_events.must_emit_exactly_once: [navigation]` with `route: /dashboard`. Dispatch routing flips to AdviceFyn next turn (INV-2.1.1).

### 10.4 Implementation order for Section 10

1. Author the 16 YAMLs above (15 transition scenarios + the alternate focus_selection).
2. **No fixture recording for these is needed initially** — `onboarding` turns are deterministic on the state machine, not on the LLM. The `EvalRecorder` should support a `recording_mode: deterministic` flag that asserts SSE shape + DB writes against the state machine's transition table without invoking any LLM provider. Add a feature task: `app/Console/Commands/EvalRecordCommand.php` line 53 to accept `--mode=deterministic` and skip the provider call when the YAML is in category 02 with `expected_response_mode: factual` AND `seed.user.onboarding_completed: false`.
3. The 4 grouped-extract states that DO involve the LLM (base_personal, base_spouse, base_dependants_detail, base_employment, base_employment_more) use the existing fixture-recording flow because their multi-line user input goes through `AssetCaptureEntityExtractor` (which calls the LLM). Record those 5 against both providers.

---

## Section 11 — Write-tool-family handoff stress tests (IN SCOPE)

**Source-of-truth: `app/Services/AI/AdviceFyn.php` lines 128-152 (`WRITE_TOOLS` constant — 24 tool names). `app/Services/AI/HandoffContract.php` (DELEGATE_TO_CAPTURE constant). `app/Services/Onboarding/OnboardingChatDirector::handleInlineCapture`. Browser-mode equivalent: BS-11 (handoff invisibility), BS-14 (direct-write savings). Test-strategy index: `April/April24Updates/spec/03-test-strategy.md` lines 146-149 (INV-2.4.x), 151-154 (INV-2.5.x).**

The `delegate_to_capture` round-trip is the canonical user-invisible handoff. AdviceFyn detects a write intent (either via `WriteIntentClassifier` upstream or via the LLM emitting `delegate_to_capture` mid-stream), the synthetic `handoff` event is intercepted in `wrapStream`, control passes to `OnboardingChatDirector::handleInlineCapture`, the inline capture writes to DB, and the same SSE stream continues with `entity_created` / `content` / `done`. The user never sees the switch (INV-2.4.1).

§6.2 already authored ONE handoff scenario (`advice_protection_profile_setup_handoff`). That covers the **protection_profile** family. There are **13 more write-tool families** the suite must regression-protect.

### 11.1 The 14 write-tool families

Sourced verbatim from `AdviceFyn::WRITE_TOOLS` (lines 128-152). Grouped by the entity table they write to.

| # | Tool family | Primary tool | Entity table | Coverage status |
|---|---|---|---|---|
| 1 | Savings account | `create_savings_account` | `savings_accounts` | **NEEDED** |
| 2 | Investment account | `create_investment_account` + `create_holding` | `investment_accounts` + `investment_holdings` | **NEEDED** |
| 3 | DC pension | `create_pension` | `dc_pensions` | **NEEDED** |
| 4 | Property + mortgage | `create_property` + `create_mortgage` | `properties` + `mortgages` | **NEEDED** |
| 5 | Protection policy | `create_protection_policy` | `life_insurance_policies` / `critical_illness_policies` / `income_protection_policies` | **NEEDED** (separate from §6.2 — that one tests the *profile* setup, this tests the *policy* creation) |
| 6 | Asset (chattel/business/other) | `create_asset` + `create_chattel` + `create_business_interest` | `assets` + `chattels` + `business_interests` | **NEEDED** |
| 7 | Liability | `create_liability` | `liabilities` | **NEEDED** |
| 8 | Estate gift | `create_estate_gift` | `gifts` | **NEEDED** |
| 9 | Trust | `create_trust` | `trusts` | **NEEDED** |
| 10 | Family member | `create_family_member` | `family_members` | **NEEDED** |
| 11 | Will | `create_will` + `update_will` | `wills` | **NEEDED** |
| 12 | Power of Attorney | `create_power_of_attorney` + `update_power_of_attorney` | `lasting_powers_of_attorney` | **NEEDED** |
| 13 | Goal | `create_goal` | `goals` | **NEEDED** |
| 14 | Life event | `create_life_event` | `life_events` | **NEEDED** |
| (15) | What-if scenario | `create_what_if_scenario` | `what_if_scenarios` | **§6.2 already authored protection_profile setup which exercises this exception** — INV-2.5.6 explicit carve-out. No new scenario needed but **add an assertion** to BS-09 / `advice_holistic_health.yaml` that this tool IS allowed in advice mode despite being a write-tool. |

**14 NEW canonical-behaviour eval scenarios needed.** All under `tests/Feature/Fyn/Eval/scenarios/04-handoffs/`. (Existing category per `April/April24Updates/spec/03-test-strategy.md` and CSJTODO Sprint 1 S1.7 "5 advice → inline-capture → advice round-trips". This expands that scope from 5 to 14, one per write-tool family.)

### 11.2 Authoring template — handoff round-trip (3-turn shape)

Same 3-turn pattern as §6.2 `advice_protection_profile_setup_handoff`. Adjust message + entity per family.

```yaml
id: handoff_<entity_family>_round_trip
category: 04-handoffs
description: |
  Two-Fyn handoff for <entity_family>. Turn 1: user asks an advice question
  that requires <entity> data; agent identifies the missing field. Turn 2:
  user volunteers the <entity> in plain text; AdviceFyn classifies as
  data_entry (or LLM emits delegate_to_capture); handoff fires; inline
  capture writes the entity. Turn 3: user re-asks the original question;
  happy-path runs.

input:
  turns:
    - user: "<advice question that exercises module without entity>"
    - user: "<plain-text statement of the entity, e.g. 'I have a £15k cash ISA at HSBC at 4.5%'>"
    - user: "<re-asked advice question or 'OK so given that...'>"

seed:
  user:
    # Full universal KYC seed (DOB, marital, employment, income, expenditure)
    onboarding_completed: true
    # Module-specific entities for the agent to find SOMETHING but be missing
    # the entity this scenario tests. E.g. for handoff_savings:
    #   - SavingsDataReadinessService passes (DOB+income+expenditure seeded)
    #   - But user has zero savings_accounts; the agent's empty-state path fires

expected_per_turn:
  turn_1:
    expected_response_mode: recommendation
    expected_engine_call_level: module
    expected_kyc_state: passed
    expected_tool_calls:
      - { tool: list_records, args: { record_type: <entity_table> }, result_path: empty_state }
      - { tool: get_module_analysis, args: { module: <module> }, result_path: empty_state_or_happy }
    expected_assistant_text:
      must_contain_substrings:
        - "<keyword identifying the missing entity, e.g. 'savings'>"

  turn_2:
    # AdviceFyn::handle line 213 WriteIntentClassifier picks up the intent
    # OR the LLM emits delegate_to_capture mid-stream. Either path produces
    # the same observable behaviour.
    expected_response_mode: factual          # data_entry classification
    expected_engine_call_level: factual
    expected_classification_shape:
      primary: data_entry
    expected_handoff_path: delegate_to_capture
    expected_tool_calls:
      - { tool: delegate_to_capture, args: { entity_types: [<entity_type>] }, result_path: synthetic_handoff }
      - { tool: <create_*_tool>, args: { ...captured fields... }, result_path: success }
    expected_db_writes:
      <entity_table>: 1
    expected_sse_events:
      must_contain_types: [tool_use, entity_created, content, done]
      must_not_emit:
        - persona_state_change           # INV-2.4.1
        - handoff                        # INV-2.4.1 — synthetic, never reaches frontend
    expected_assistant_text:
      must_not_contain_substrings:
        - "switching to capture mode"     # INV-2.4.1
        - "let me hand you over"          # INV-2.4.1
        - "I'll add that"                 # avoid the "fabricated success text" bug from BS-14 history (S0.5.t)

  turn_3:
    expected_response_mode: recommendation
    expected_engine_call_level: module
    expected_tool_calls:
      - { tool: list_records, args: { record_type: <entity_table> }, result_path: happy }
      - { tool: get_module_analysis, args: { module: <module> }, result_path: happy }
      - { tool: get_recommendations, result_path: happy }
    expected_assistant_text:
      must_contain_substrings:
        - "For regulated advice"
        - "<the entity value the user just added, e.g. '£15,000' or '£15k' or 'HSBC'>"

forbidden_outputs:
  # User must NEVER perceive the handoff per CANON line 20 + INV-2.4.1
  - "switching to capture mode"
  - "let me hand you over to"
  - "Onboarding Fyn"

forbidden_tools:
  # Direct write-tools must NOT appear at AdviceFyn's top-level tool list
  # per INV-2.1.2 — they only fire INSIDE handleInlineCapture's nested
  # invocation. The asserter checks AdviceFyn::buildToolList output here.
  - <write_tool_in_advice_top_level>

timing_budget_ms:
  per_turn:
    anthropic: { turn_1: 6000, turn_2: 9000, turn_3: 8000 }
    xai: { turn_1: 14000, turn_2: 18000, turn_3: 16000 }

tags:
  - canonical-behaviour
  - two-fyn-handoff
  - delegate-to-capture
  - <entity_family>
  - regression-band-0
```

### 11.3 The 14 specific scenarios — message content per family

| # | Filename | Turn 1 advice question | Turn 2 capture trigger | Turn 3 re-ask |
|---|---|---|---|---|
| 1 | `handoff_savings_round_trip.yaml` | "Do I have enough emergency savings?" | "I have £4,500 in a Nationwide easy-access account at 4.5%" | "Now am I covered?" |
| 2 | `handoff_investment_round_trip.yaml` | "What's my ISA allowance position?" | "I've got £12,000 in a Vanguard Stocks & Shares ISA, contributed £4,000 this year" | "Should I top up?" |
| 3 | `handoff_pension_round_trip.yaml` | "Am I on track for retirement?" | "I have a workplace pension at Standard Life worth £45k, I contribute £250/month and my employer matches" | "Same question — am I on track?" |
| 4 | `handoff_property_round_trip.yaml` | "How much is my estate worth?" | "I own my main residence, valued at £600k with a £100k mortgage outstanding" | "And the IHT picture?" |
| 5 | `handoff_protection_policy_round_trip.yaml` | "Do I have life cover?" | "I have an Aviva life policy for £300,000, monthly premium £25" | "Am I covered enough now?" |
| 6 | `handoff_asset_round_trip.yaml` | "What's in my net worth?" | "I have a vintage Rolex worth around £15,000 and a small business interest valued at £80k" | "Updated picture?" |
| 7 | `handoff_liability_round_trip.yaml` | "How much do I owe?" | "Credit card balance of £4,500 at 22% APR" | "What's the priority?" |
| 8 | `handoff_estate_gift_round_trip.yaml` | "How does gifting affect my IHT?" | "I gave my daughter £50,000 in March 2024 toward her house deposit" | "Updated IHT?" |
| 9 | `handoff_trust_round_trip.yaml` | "Do I need a trust?" | "I set up a discretionary trust for my children with £200k in 2023" | "Updated estate plan?" |
| 10 | `handoff_family_member_round_trip.yaml` | "Are my dependants protected?" | "Two children, James born 2018 and Sophie born 2020, both depend on me" | "Updated cover?" |
| 11 | `handoff_will_round_trip.yaml` | "What does my estate look like for executors?" | "I made a will in 2022 leaving everything to my spouse with my brother as executor" | "Updated picture?" |
| 12 | `handoff_lpa_round_trip.yaml` | "What if I lose capacity?" | "I have a registered LPA for property and finance, my brother is the attorney" | "Updated estate plan?" |
| 13 | `handoff_goal_round_trip.yaml` | "Am I saving enough for the kids' uni?" | "Goal: £80,000 by 2032 for university fees, £400 a month going in" | "Am I on track?" |
| 14 | `handoff_life_event_round_trip.yaml` | "What if I have another child?" | "Expecting a third child in October 2026, planning 6 months parental leave" | "Updated affordability?" |

### 11.4 Provider-specific assertions for handoff scenarios

The handoff path has known cross-provider drift documented in `app/Services/AI/AdviceFyn.php` lines 203-213 (the `WriteIntentClassifier` was added because grok-4-1-fast emits `delegate_to_capture` as plain text instead of the structured tool API). Each handoff scenario must assert **both branches**:

```yaml
expected_handoff_path:
  anthropic: structured_tool_use     # LLM uses the tool API
  xai: write_intent_classifier        # AdviceFyn::handle line 213-289 server-side classifier fires before LLM
  # Either path is correct — what matters is the user-observable outcome.
  observable_assertion: db_write_committed
```

The asserter looks at the captured tool_calls and decides which path fired. Both paths must produce the same DB state and the same final user-visible response. The dashboard renders the path taken so operators can see grok-vs-anthropic-vs-haiku divergence at a glance.

### 11.5 Cross-cutting handoff invariants every scenario must assert

All from `April/April24Updates/spec/01-invariants.md` §2.4:

| INV | Property | Assertion in YAML |
|---|---|---|
| **INV-2.4.1** | Zero `persona_state_change` SSE events reach the frontend | `expected_sse_events.must_not_emit: [persona_state_change, handoff]` on EVERY turn |
| **INV-2.4.2** | Inline capture does not emit `quick_replies` | `must_not_emit: [quick_replies]` on turn_2 |
| **INV-2.4.3** | `capture_complete` styling matches advice persona, not data_capture | Asserter checks the `persona` field on the assistant message — must be `advice` not `data_capture` per AdviceFyn line 188-194 |
| **INV-2.4.4** | System messages exempt from handoff invisibility | If the turn produced a system message (token-limit, consent, etc.), the assertion above is waived |
| **INV-2.5.1** | Every `create_*` handler writes to DB synchronously | `expected_db_writes` is asserted POST-TURN, not eventually-consistent |
| **INV-2.5.2** | Observer chain fires on every direct-write | `expected_audit_log_entries: { create: 1 }` per write |
| **INV-2.5.4** | Audit trail matches reality | Hash chain assertion via `tests/Feature/Audit/HashChainTest.php` (cross-reference) |

These assertions go into a SHARED YAML fragment (`tests/Feature/Fyn/Eval/scenarios/04-handoffs/_handoff_invariants.fragment.yaml`) that each handoff scenario inherits via `inherits: _handoff_invariants` (new asserter feature — see §9 task additions).

---

## Section 12 — Resume-after-disconnect canonical-behaviour scenarios (IN SCOPE)

**Source-of-truth: `April/April24Updates/spec/01-invariants.md` lines 118-122 (INV-2.2.4). `app/Services/Onboarding/OnboardingChatDirector::resumeSummary` (line 394-406 — referenced by spec but verify against current code). Browser-mode equivalent: `tests/Browser/scenarios/BS-04-resume-after-disconnect.php` (already authored, Sprint 0). Test-strategy index: `April/April24Updates/spec/03-test-strategy.md` lines 137 + 268-...**

INV-2.2.4 says:

> For a user with `onboarding_completed = false` AND `onboarding_fyn_step != null` AND last `ai_messages.created_at` older than 5 minutes, the next session emits an opening SSE turn whose `quick_replies.prompt_text` contains the output of `OnboardingChatDirector::resumeSummary($stateId)`, and whose bubbles are `[{id: 'resume', label: 'Yes, continue'}, {id: 'restart', label: 'Start over'}]`.

The browser scenario BS-04 covers ONE state (`base_dependants_detail`). The Rubric-B suite needs **per-state resume scenarios** because each state's `resumeSummary` is a different string (bound to that state's `STATE_*` constant per spec line 122 acceptance criterion). A regression in any one summary string only shows up if a scenario tests THAT state.

### 12.1 Resume scenarios — 1 per non-terminal state

Every state where the user can plausibly disconnect needs a resume scenario. Excludes `STATE_DONE` (terminal — resume doesn't apply) and `STATE_PATH_CHOICE` / `STATE_JOURNEY_SELECTION` / `STATE_FOCUS_SELECTION` (no parked data yet — resume into entry bubbles is the same as a fresh entry, no special summary).

**13 NEW eval scenarios**, all under `tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/resume/`:

| # | Filename | State user disconnected at | Expected `resumeSummary` content |
|---|---|---|---|
| 1 | `resume_at_base_personal.yaml` | `base_personal` | "we were getting your personal details — your name, date of birth, and marital status" |
| 2 | `resume_at_base_spouse.yaml` | `base_spouse` | "we were busy entering your spouse's details" |
| 3 | `resume_at_base_dependants.yaml` | `base_dependants` | "we were checking whether you have dependants" |
| 4 | `resume_at_base_dependants_detail.yaml` | `base_dependants_detail` | "we were busy entering your dependants — you'd told me about [N children]" |
| 5 | `resume_at_profile_review_family.yaml` | `profile_review_family` | "we were reviewing your family details" |
| 6 | `resume_at_base_employment.yaml` | `base_employment` | "we were entering your employment status and income" |
| 7 | `resume_at_base_work.yaml` | `base_work` | "we were filling in your work details" |
| 8 | `resume_at_base_employment_more.yaml` | `base_employment_more` | "we were adding any other income sources or your spouse's income" |
| 9 | `resume_at_base_retirement_date.yaml` | `base_retirement_date` | "we were setting your target retirement age" |
| 10 | `resume_at_base_expenditure.yaml` | `base_expenditure` | "we were entering your monthly expenditure" |
| 11 | `resume_at_profile_review_expenditure.yaml` | `profile_review_expenditure` | "we were reviewing your expenditure" |
| 12 | `resume_at_asset_capture.yaml` | `asset_capture` | "we were busy adding your assets — you'd already entered [details of any captured]" |
| 13 | `resume_at_add_more.yaml` | `add_more` | "we were checking whether you wanted to add anything else" |

### 12.2 Authoring template

```yaml
id: resume_at_<state_id>
category: 09-canonical-behaviour
description: |
  INV-2.2.4 — user with onboarding_completed=false, onboarding_fyn_step=<STATE>,
  last ai_messages.created_at > 5 minutes ago receives a resume bubble on next
  session entry. The resume summary is bound to the state's STATE_<X> constant
  via OnboardingChatDirector::resumeSummary($stateId).

input:
  turns:
    - user: ""        # No user input — the resume bubble is emitted on session
                      # initialisation, before any user turn. Asserter handles
                      # the empty-input case as "open chat panel + assert
                      # opening SSE shape".

seed:
  user:
    first_name: Test
    surname: User
    onboarding_completed: false
    onboarding_fyn_step: <STATE>
    # Plus enough seeded fields to be valid for that state. e.g. for
    # base_dependants_detail: marital_status: married, family_members: 1
    # (the spouse).

  ai_conversations:
    - user_id: <self>
      status: active
      onboarding_parked_facts: { ... }   # Whatever was parked at that state

  ai_messages:
    - conversation_id: <above>
      role: assistant
      content: <last assistant message before disconnect>
      created_at: <NOW - 6 minutes>      # > 5 minutes per INV-2.2.4

expected_response_mode: factual
expected_engine_call_level: factual
expected_kyc_state: bypass

expected_classification_shape:
  primary: data_entry      # onboarding turns classify here per AFYN-MODE
  related: []
  modules: []

expected_sse_events:
  must_contain_types: [quick_replies, content, done]
  must_emit_exactly_once: [done]
  must_not_emit:
    - persona_state_change

expected_quick_replies:
  bubble_count: 2
  bubbles:
    - { id: resume, label: "Yes, continue" }
    - { id: restart, label: "Start over" }
  prompt_text:
    must_contain_substrings:
      - "<state-specific summary substring per table 12.1>"
    must_match_resume_summary:
      method: OnboardingChatDirector::resumeSummary
      arg: <STATE_constant>
      # Asserter calls the method directly and asserts substring equality
      # against the captured prompt_text. Catches drift where the method
      # name moves but the spec line 122 expectation lags.

expected_db_writes: {}     # Resume turn emits no writes — it's a re-entry

forbidden_outputs:
  - "Welcome to Fynla"             # not a fresh-entry greeting
  - "Let's start by asking"        # not a fresh-state question
  - "switching to capture mode"

forbidden_tools:
  - delegate_to_capture
  - get_module_analysis

timing_budget_ms:
  anthropic: { factual: 2000 }     # No LLM call — server-side bubble construction
  xai: { factual: 2000 }

tags:
  - canonical-behaviour
  - resume-after-disconnect
  - state-<state_id>
  - regression-band-0
```

### 12.3 Edge-case resume scenarios

In addition to the 13 per-state scenarios, **3 edge cases** that test boundary behaviour:

#### 12.3.1 `resume_under_5_minutes_no_bubble.yaml`

User disconnected at any state, last message **< 5 minutes ago**. The resume bubble must NOT fire — INV-2.2.4 explicitly bounds at 5 minutes.

**Assertion:** `expected_sse_events.must_not_emit: [quick_replies]` AND no bubble in the captured prompt_text.

#### 12.3.2 `resume_after_onboarding_completed.yaml`

User completed onboarding (`onboarding_completed = true`), came back after 6 hours. Dispatch routes to AdviceFyn (INV-2.1.1), no resume bubble fires (the resume is onboarding-only).

**Assertion:** classification = whatever the user types (or general greeting), NO `quick_replies`, NO resume_summary.

#### 12.3.3 `resume_with_step_null_no_bubble.yaml`

`onboarding_completed = false` BUT `onboarding_fyn_step = null` (user registered but hasn't started). No prior state to resume.

**Assertion:** dispatch routes to OnboardingChatDirector, lands at STATE_PATH_CHOICE, fresh-entry bubbles fire (NOT resume bubbles).

### 12.4 Browser-eval cross-reference

Each Rubric-B resume scenario should have a `linked_browser_scenario: BS-04` field so the dashboard can show the linkage and operators jumping into a failure can immediately see the live UI test. BS-04 already covers the `base_dependants_detail` case end-to-end; the Rubric-B equivalent (`resume_at_base_dependants_detail.yaml`) is the per-state regression net.

---

## Section 13 — Truly out-of-scope (deferred to other workstreams)

What Sections 10-12 brought into scope leaves only:

- **Mode-2 (real-provider) cron scheduling.** Plan reference: `April/April24Updates/plan/12-sprint-2-plan.md` Task 2.16. This is the cron that fires real provider runs against the same scenario bank to detect provider regressions. Not part of the rewrite — the rewrite makes the scenarios correct, Mode-2 makes them run on a schedule.
- **Holistic engine internals deep-dive.** `CoordinatingAgent::orchestrateAnalysis` (line 163-…) is the largest method in the codebase. §6.3 (`advice_holistic_health.yaml`) asserts the OUTPUT shape per `ToolResultContract::REQUIRED_KEYS['holistic']`. A full deep-dive on the 7-module aggregation logic, the priority ordering algorithm, and the cross-module recommendation ranking belongs in a separate audit. Filed for `April/April27Updates/orchestrate-analysis-deep-dive.md` (next session).
- **Provider-parity scenarios** (Rubric-B category 08). Cross-provider drift is acknowledged in `feedback_fyn_model_choice_is_deliberate.md` and visible in every session #18-#20 recording. After §9.7 lands and the rewritten scenarios run cleanly, an automatic delta report between anthropic-Haiku and xAI-grok runs becomes meaningful. The category-08 scenarios are deliberately constructed to maximise provider-detectable drift (numeric reasoning edge cases, long-context anchor stability, tool-choice tie-breaking). Plan reference: `April/April24Updates/plan/12-sprint-2-plan.md` Task 2.16 Step 4.
- **Prompt-injection scenarios** (Rubric-B category 06). 2 starters in Sprint 1 plan; full set in Sprint 2 Task 2.16 Step 5. Out of scope here — the existing security work (`tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php` + 10 injection scenarios) covers the unit tests; the eval-replay versions are sprint-2 scope.

---

## Section 14 — Pointers for the next instance

The next session executing this plan needs to read these files in this order. Every file is named with its full path and a one-line description of why it matters.

### 14.1 MUST READ FIRST — operating context

1. `/Users/CSJ/Desktop/fynla/CLAUDE.md` — project laws (rule #15 LOOP UNTIL CORRECT, rule #14 icons, deployment, mandatory pre-flight reseed). Already auto-loaded by the harness.
2. `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/MEMORY.md` — index of feedback memories. Already auto-loaded. Particularly relevant: `critical_browser_testing_law.md`, `feedback_loop_until_correct.md`, `feedback_never_claim_verified.md`, `feedback_advice_fyn_is_read_only.md`.
3. `/Users/CSJ/Desktop/fynla/April/April27Updates/CSJTODO.md` — current session handover. Read top-to-bottom. Flags S1.6.a removal (advice_response panel deleted), S1.6.b shipped (per-agent output contract), S1.8 shipped (response-mode classifier), and the fact that S1.2.k (re-record 9 fixtures) is **next-session's first task** — but THIS report says re-recording must wait for §9.1-9.6 to land first.
4. `/Users/CSJ/Desktop/fynla/April/April27Updates/eval-expectations-rewrite.md` — **THIS REPORT.** Re-read it before starting work. It is the contract.

### 14.2 Companion April27Updates audit docs

These three documented the prompt-builder failures and shipped the fixes that make THIS report's expectations achievable. Read in this order:

5. `/Users/CSJ/Desktop/fynla/April/April27Updates/eval-system-vs-live-flow-audit.md` — v2 audit of the EmptyDataGuard structural prompt-swap that broke session #5. Tasks 1, 2, 3, 3b shipped 2026-04-27. Sections 5.4 and 6 still relevant.
6. `/Users/CSJ/Desktop/fynla/April/April27Updates/fixEvalTask.md` — task list. Tasks 1, 2, 3, 3b ✅. Tasks 3c, 4, 5, 6, 7 ⬜. **Task 5 (re-record fixtures) blocks on THIS report's §9.7.** **Task 4 (dashboard delta heuristic) is folded into THIS report's Section 7.**
7. `/Users/CSJ/Desktop/fynla/April/April27Updates/system-prompt-audit.md` — billing-block-not-classification-gated audit. Closed by Task 2 of fixEvalTask (Option A — classification-gated billing). Background context only; nothing in THIS report depends on it.

### 14.3 Plan and spec — the contract this report enforces

8. `/Users/CSJ/Desktop/fynla/April/April24Updates/plan/11-sprint-1-plan.md` — **PRIMARY COMPANION DOCUMENT.** THIS report and the sprint plan live together. The plan's **S1.2.l** schedules the rewrite of the 10 existing YAMLs (this report's §4 + §5). The plan's **S1.7.a through S1.7.j** schedule everything else (this report's §6, §8, §10, §11, §12, plus §3/§7 infra). The plan's **"Eval expectations rewrite — S1.7 scope extension (added 2026-04-27 session 102)"** narrative section mirrors §13 and §14 of this report. **Cross-reference both the Status block (lines 68-95) AND the S1.7 sub-task body when scheduling work.**
9. `/Users/CSJ/Desktop/fynla/April/April24Updates/plan/12-sprint-2-plan.md` — Sprint 2 plan. References the 30-scenario expansion (S1.7 is sprint-1, but the full 75 from rubric-B is sprint-2). Out-of-scope items in §13 above all live here.
10. `/Users/CSJ/Desktop/fynla/April/April24Updates/plan/10-sprint-0-plan.md` — Sprint 0 plan. Closed. Reference for "what shipped before this".
11. `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/00-canonical.md` — **THE Two-Fyn contract.** Top of this report is built on it. Re-read.
12. `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/01-invariants.md` — ~35 falsifiable invariants. THIS report cites INV-2.1.x, INV-2.2.x, INV-2.3.x, INV-2.4.x, INV-2.5.x, INV-2.11.x. Read end-to-end before authoring any new scenario.
13. `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/02-current-system.md` — anchors invariants in current code state. Reference for "where does X live in the codebase right now".
14. `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/03-test-strategy.md` — Pest + Playwright + Rubric-B mapping. **Lines 131-182 are the invariant-to-test index.** Lines 188-... are the BS-NN browser scenario specs. THIS report's Section 11.5 cross-references it.
15. `/Users/CSJ/Desktop/fynla/April/April24Updates/fyn-rubrics.md` — Rubric-A (40 dimensions) + Rubric-B (75 golden conversations). **Section §B is the canonical source for what each scenario should test.** The 14 onboarding state-machine scenarios in §10 above and the 14 handoff scenarios in §11 trace back to §B subsections.
16. `/Users/CSJ/Desktop/fynla/April/April24Updates/audit-evidence.md` — original audit findings. Background.
17. `/Users/CSJ/Desktop/fynla/April/April24Updates/audit-synthesis.md` — synthesised findings. Reference for "the 17 fill_form handlers that became direct-write" (cited in INV-2.5.1).

### 14.4 Source code — the implementation surface

The actual files THIS report touches OR references. Listed in dependency order:

18. `/Users/CSJ/Desktop/fynla/app/Constants/QuerySchemas.php` — **single source of truth** for classifications, modules, IMPLICIT_RELATED, REQUIRED_TOOLS, KYC requirements, keyword patterns. Every Section 4 per-scenario rewrite cites a line here.
19. `/Users/CSJ/Desktop/fynla/app/Services/AI/AdviceFyn.php` — RESPONSE_MODE_MAP (lines 52-77), ENGINE_CALL_LEVEL_MAP (lines 95-120), WRITE_TOOLS (lines 128-152). Section 11 enumerates all 24 write tools from this file.
20. `/Users/CSJ/Desktop/fynla/app/Services/AI/QueryClassifier.php` — `classify()` flow (lines 64-114). Multi-label resolution, route fallback, OUT_OF_REMIT detection. Section 4.6 (`advice_goals_affordability`) keyword-collision finding traces here.
21. `/Users/CSJ/Desktop/fynla/app/Services/AI/KycGateChecker.php` — universal + per-module checks. Section 3.4 (`expected_kyc_state` shape) maps directly to its return shape.
22. `/Users/CSJ/Desktop/fynla/app/Services/PrerequisiteGateService.php` — module-action map (lines 46-59). Bridges KycGateChecker to the 5 DataReadinessServices.
23. `/Users/CSJ/Desktop/fynla/app/Services/Protection/ProtectionDataReadinessService.php` — RDY-PROT, blocking checks at lines 74-99.
24. `/Users/CSJ/Desktop/fynla/app/Services/Savings/SavingsDataReadinessService.php` — RDY-SAV, blocking checks at lines 35-37.
25. `/Users/CSJ/Desktop/fynla/app/Services/Retirement/RetirementDataReadinessService.php` — RDY-RET, blocking at lines 36-49.
26. `/Users/CSJ/Desktop/fynla/app/Services/Investment/Recommendation/DataReadinessService.php` — RDY-INV, blocking at lines 65-83 (note: **risk_profile is BLOCKING**).
27. `/Users/CSJ/Desktop/fynla/app/Services/Estate/EstateDataReadinessService.php` — RDY-EST, blocking at lines 67-81.
28. `/Users/CSJ/Desktop/fynla/app/Agents/ProtectionAgent.php` — line 38-57 (readiness gate), line 72 (secondary protection_profile gate). Section 4.1 traces here.
29. `/Users/CSJ/Desktop/fynla/app/Agents/SavingsAgent.php` — line 53-71 (readiness only, no secondary gate).
30. `/Users/CSJ/Desktop/fynla/app/Agents/InvestmentAgent.php` — line 42-79 (readiness + accounts.isEmpty empty-state).
31. `/Users/CSJ/Desktop/fynla/app/Agents/RetirementAgent.php` — line 67-103 (readiness + line 101 retirement_profile gate).
32. `/Users/CSJ/Desktop/fynla/app/Agents/EstateAgent.php` — line 58-96 (readiness only).
33. `/Users/CSJ/Desktop/fynla/app/Agents/GoalsAgent.php` — line 31-87 (no readiness, goals.isEmpty empty-state).
34. `/Users/CSJ/Desktop/fynla/app/Agents/CoordinatingAgent.php` — `orchestrateAnalysis` line 163, tool dispatch lines 1505-1510. Single caller of holistic engine per INV-2.3.6.
35. `/Users/CSJ/Desktop/fynla/app/Services/AI/ToolResultContract.php` — REQUIRED_KEYS (lines 45-77), PARTIAL_KEYS (lines 85-91), 4 validate paths (lines 103-143). Section 3.5 (`expected_tool_result_path`) maps to this.
36. `/Users/CSJ/Desktop/fynla/app/Services/AI/AdvicePromptBuilder.php` — post-Task-1: layers 5/6/7 unconditional, no EmptyDataGuard branch. Reference for "what's in the system prompt the LLM saw".
37. `/Users/CSJ/Desktop/fynla/app/Services/Onboarding/OnboardingStateMachine.php` — 17 states (lines 36-76), transition table (lines 83-291). Section 10 enumerates from this file.
38. `/Users/CSJ/Desktop/fynla/app/Services/Onboarding/OnboardingChatDirector.php` — `handleUserMessage`, `handleInlineCapture`, `resumeSummary` (line 394-406 per spec INV-2.2.4). Sections 10, 11, 12 all reference handlers here.
39. `/Users/CSJ/Desktop/fynla/app/Services/AI/MemoryRetrieverService.php` — `<known_facts>` block builder (Sprint 1 S1.4). Reference for the "no repeat-ask" assertion in §6.1.
40. `/Users/CSJ/Desktop/fynla/app/Services/AI/AiToolDefinitions.php` — Anthropic tool catalogue. `get_module_analysis` enum at line 132. Section 4.6 cites this for the `module: holistic` arg validity.
41. `/Users/CSJ/Desktop/fynla/app/Services/AI/XaiToolDefinitions.php` — xAI strict-mode tool catalogue. Mirror of #40 for grok.

### 14.5 Test infrastructure — what to extend

42. `/Users/CSJ/Desktop/fynla/tests/Feature/Fyn/Eval/AssertionHelpers.php` — **Section 9.1 modifies this.** New keys to support: `expected_response_mode`, `expected_engine_call_level`, `expected_classification_shape`, `expected_kyc_state`, `expected_kyc_missing`, `expected_tool_result_path`, `expected_tool_calls_absent`, `expected_assistant_text` (must_contain_substrings, must_not_contain_substrings, exact_match, minimum_length_chars, maximum_length_chars), `expected_orchestrate_analysis_called`, `expected_per_turn`, `expected_state_transition`, `expected_parked_facts`, `expected_quick_replies`, `expected_handoff_path`, `expected_db_writes`, `inherits` (fragment inheritance per §11.5), `linked_browser_scenario`.
43. `/Users/CSJ/Desktop/fynla/tests/Feature/Fyn/Eval/EvalRunner.php` — runner. Add `recording_mode: deterministic` flag per §10.4 (skips LLM call for state-machine scenarios).
44. `/Users/CSJ/Desktop/fynla/tests/Feature/Fyn/Eval/MockedProviderClient.php` — Mode-1 replay. Reference; not modified.
45. `/Users/CSJ/Desktop/fynla/tests/Feature/Fyn/Eval/EvalReport.php` — output formatting. Update to surface new assertion types in the failure messages.
46. `/Users/CSJ/Desktop/fynla/tests/Feature/Fyn/Eval/scenarios/01-query-types/` — 6 advice YAMLs to rewrite per §4.
47. `/Users/CSJ/Desktop/fynla/tests/Feature/Fyn/Eval/scenarios/02-preview-personas/` — 14+ NEW state-machine scenarios per §10.
48. `/Users/CSJ/Desktop/fynla/tests/Feature/Fyn/Eval/scenarios/03-multi-entity/` — 4 onboarding YAMLs to update per §5.
49. `/Users/CSJ/Desktop/fynla/tests/Feature/Fyn/Eval/scenarios/04-handoffs/` — 14 NEW handoff scenarios per §11. Plus the shared `_handoff_invariants.fragment.yaml`.
50. `/Users/CSJ/Desktop/fynla/tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/` — 4 NEW per §6 + 13 + 3 NEW resume scenarios per §12.
51. `/Users/CSJ/Desktop/fynla/tests/Architecture/` — 6 NEW meta-tests per §8.
52. `/Users/CSJ/Desktop/fynla/tests/Browser/scenarios/` — 23 existing BS-NN scenarios. **Don't modify.** They are the live-UI complement to the Rubric-B replay scenarios. Each Section 12 resume YAML cites BS-04 as `linked_browser_scenario`.
53. `/Users/CSJ/Desktop/fynla/app/Console/Commands/EvalRecordCommand.php` — `eval:record` command. Section 10.4 adds `--mode=deterministic` flag.
54. `/Users/CSJ/Desktop/fynla/app/Console/Commands/EvalShowCommand.php` — `eval:show` command. Reference; minor updates to display new fields.
55. `/Users/CSJ/Desktop/fynla/app/Console/Commands/EvalPurgeCommand.php` — `eval:purge` command. Section 5 of `eval-system-vs-live-flow-audit.md` proposed `--re-record` flag; not yet shipped.
56. `/Users/CSJ/Desktop/fynla/app/Models/EvalRecordingSession.php`, `EvalProviderRun.php` — model fields. Section 7 adds `kyc_state`, `kyc_missing`, `tool_result_paths`, `engine_call_level_actual`.
57. `/Users/CSJ/Desktop/fynla/app/Http/Controllers/Api/Admin/EvalRecordingController.php` — admin viewer + `buildDelta`. Section 7 modifies `buildDelta`.
58. `/Users/CSJ/Desktop/fynla/resources/js/components/Admin/EvalRecordings.vue`, `Admin/eval/EvalDataModal.vue` — Vue dashboard. Section 7 adds `Prompt readiness` panel.

### 14.6 Vault references (read-only — do not write to vault from this work)

59. `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/spec/` — vault mirror of #11-#15. Used when local files are gitignored or the vault has a more recent edit. **Default: trust the local repo files.**
60. `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/plan/` — vault mirror of #8-#10.
61. `/Users/CSJ/Desktop/fynlaBrain/v083/10-NEW-SYSTEMS.md` — Fyn AI / chat architecture in production. Reference for "what the live system looks like" without diving into code.
62. `/Users/CSJ/Desktop/fynlaBrain/v083/04-BACKEND.md` — overall backend architecture.

### 14.7 Memory references (auto-loaded — do not read manually unless confused)

The auto-memory system at `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/` carries these files that are particularly relevant:

- `feedback_advice_fyn_is_read_only.md` — AdviceFyn = zero write tools, writes go via delegate_to_capture handoff. Sections 6.2 + 11 build on this.
- `feedback_loop_until_correct.md` — when re-recording fixtures (§9.7), DO NOT stop after the first scenario failure. Loop until GREEN per the YAML's contract.
- `critical_browser_testing_law.md` — relevant for Section 12's BS-04 cross-reference.
- `feedback_never_hardcode_tax_values.md` — relevant for Sections 4.4 (retirement contribution) + 4.5 (estate IHT) — the tools `get_tax_information(*)` are called precisely so the model never hardcodes tax values.
- `project_advanced_chat_model_branch.md` — flagged dead code in `HasAiGuardrails`; unrelated to this work but useful context for any future model-routing changes.

### 14.8 What the next instance should do FIRST

In the order they appear:

1. **Reseed:** `php artisan db:seed --force` per CLAUDE.md mandatory pre-flight.
2. **Re-read CSJTODO:** `/Users/CSJ/Desktop/fynla/April/April27Updates/CSJTODO.md` end-to-end. Confirm session 101 still ended where this report says it did.
3. **Re-read THIS report (`eval-expectations-rewrite.md`).** It IS the contract.
4. **Re-read the sprint plan:** `/Users/CSJ/Desktop/fynla/April/April24Updates/plan/11-sprint-1-plan.md`. The Status block lists S1.2.l (rewrite 10 YAMLs) and S1.7.a-S1.7.j (everything else) as the open work. The sprint plan's narrative "Eval expectations rewrite — S1.7 scope extension (added 2026-04-27 session 102)" mirrors what's here.
5. **Verify Tasks 1, 2, 3, 3b from `fixEvalTask.md` are still shipped** by running:
   ```
   ./vendor/bin/pest tests/Unit/Services/AI/AdvicePromptBuilderStructuralLayersTest.php
   ./vendor/bin/pest tests/Architecture/
   ```
   Both should be green. If not, this report's expectations are not yet achievable — fix the prompt-builder regression FIRST.
6. **Start at S1.7.a (extend AssertionHelpers).** This is the foundation — S1.2.l, S1.7.b through S1.7.h all block on it. The work maps directly to §9.1 of this report. Ship S1.7.a green before any other Sprint 1 sub-task.
7. **Then S1.7.b (architecture meta-tests).** §9.2 of this report. Ship green.
8. **Then S1.2.l (rewrite the 10 YAMLs).** §9.3 + §9.5 of this report. With S1.7.a/S1.7.b green, the rewrites can be authored one-by-one and verified by the meta-tests.
9. **Then S1.7.c (canonical-behaviour) → S1.7.d (dashboard) → S1.7.e (state-machine) → S1.7.f (handoff) → S1.7.g (resume).** §9.4, §9.6, §9.7, §9.8, §9.9 in order.
10. **Then S1.7.h (re-record fixtures).** §9.10. **DO NOT re-record before this point** — recording against a broken asserter produces fixtures that match the broken contract.
11. **Then S1.7.i (publish `eval-rewrite-verification.md`).** §9.11. Hard-gate doc for S1.10.
12. **DO NOT batch the YAML rewrites.** Author one scenario, run the asserter + meta-tests, fix, commit, move on. Per `feedback_incremental_verification.md`.

### 14.9 Suggested agent prompt for the next session

When dispatching this work to a sub-agent or the next session, use this prompt skeleton (copy-paste-ready):

> Read `/Users/CSJ/Desktop/fynla/April/April27Updates/eval-expectations-rewrite.md` end-to-end before doing anything else. It is the contract for the eval-scenario rewrite work in Sprint 1.
>
> Then read these in order: `April/April27Updates/CSJTODO.md`, `April/April27Updates/fixEvalTask.md` (status board), `April/April24Updates/spec/00-canonical.md`, `April/April24Updates/spec/01-invariants.md` §2.1-2.5 + §2.11, `April/April24Updates/plan/11-sprint-1-plan.md` Status block.
>
> Your job is to execute Section 9 of the rewrite report in numbered order. Section 9.1 (extend AssertionHelpers) blocks everything else; ship it green before moving to 9.2.
>
> Constraints: do not change the seed of any scenario. Do not change the prompt-builder code (Tasks 1, 2, 3, 3b shipped 2026-04-27 per fixEvalTask.md). Do not re-record fixtures until §9.1-9.6 are green. Loop per CLAUDE.md rule #15 — diagnose, fix, re-verify; do not hand back partial work.
>
> Verify each YAML's `expected_classification_shape` against the actual classifier output via `php artisan tinker --execute="dump(app(App\Services\AI\QueryClassifier::class)->classify('<message>'));"` BEFORE committing the YAML. Same for `expected_kyc_state` — call `KycGateChecker::check` against the seeded user.
>
> Browser-test only when explicitly required (§9.7 re-recording). Do not assume the dashboard "PASS/FAIL" verdict means anything until §9.6 ships the new buildDelta heuristic.

---

*End of rewrite report. Every line traceable to a source file. No seed changes. No prompt-builder changes (Tasks 1, 2, 3, 3b shipped 2026-04-27 per fixEvalTask.md). All changes confined to YAMLs, asserter, dashboard delta, meta-tests, and the new canonical-behaviour + state-machine + handoff + resume YAMLs catalogued in Sections 6, 10, 11, 12.*
