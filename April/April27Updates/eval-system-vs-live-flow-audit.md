# Eval system vs live Fyn AI flow — audit & disconnect report

*Authored 2026-04-27. Sister document to `system-prompt-audit.md` in this folder. Branch: `feature/fyn-persona-split`. Plan reference: `April/April24Updates/plan/11-sprint-1-plan.md`.*

> **This is v2 of this document.** v1 mis-diagnosed the problem as "data sparsity, seed eval users from preview personas." That was wrong — it described a coverage gap that needs fixing later. The actual problem is a **wiring bug in `AdvicePromptBuilder`** that causes the eval recording's system prompt to be **structurally different** from the live system prompt for the same code path. v1 is left in commit history; this version replaces its content.

---

## TL;DR

The eval recording for `advice_protection_cover` (session #5, conversation 145, Anthropic Haiku 4.5) is testing a **fundamentally different prompt** from the one a live user would receive — even on the same code path, even on the same model, even with `AdviceFyn::handle` as the shared entry point. The cause is a single conditional inside `AdvicePromptBuilder::build` (line 112):

```php
if ($this->isNewUserWithNoData($user)) {
    $layers[] = EmptyDataGuard::get();      // substitute
} else {
    // Layer 5: <financial_context>
    // Layer 6: <existing_records>
    // Layer 7: <data_completeness>
}
```

The eval seed for `advice_protection_cover` populates **only a protection policy** — no income, no savings, no investments, no pensions. `isNewUserWithNoData` (line 272) checks **only** those four signals (it does not look at protection, property, family members, goals, expenditure, etc.). For this user it returns TRUE. The branch fires. **Layers 5, 6, and 7 are physically removed from the prompt** and the EmptyDataGuard block is substituted in their place.

The substituted block then **explicitly forbids** the very tool calls the scenario expects to assert: `get_module_analysis`, `get_recommendations`, `generate_financial_plan`, `get_tax_information` — and forbids any £/% figure in the response. The scenario YAML (`expected_tool_calls`) requires `get_module_analysis(protection)` + `get_recommendations`. The prompt the model receives says **NEVER call those tools.** The model is being graded against a contract its own system prompt rules out.

When CSJ ran the same query manually as a real user, that user had `annual_employment_income > 0`. `isNewUserWithNoData` returned FALSE. Layers 5/6/7 rendered. Tools were permitted. Real figures flowed. **Same code path. Different branch taken. Different prompt assembled. Different response. By design.**

This is not a coverage gap. It is a wiring defect with two co-equal symptoms:
1. The prompt builder's "new user" guard is incomplete (checks 4 of ~12 modules).
2. The eval seed YAMLs do not populate the four signals the guard does check.

The eval is therefore not measuring what the user assumes it is measuring. As wired, it cannot succeed: the prompt forbids the tools the scenario asserts. **The eval is wired wrong. It needs fixing before Sprint 1 verification can carry any weight.**

---

## 1. The captured evidence

`ai_messages.system_prompt` for `conversation_id = 145` (eval recording session #5, scenario `advice_protection_cover`, Anthropic Haiku 4.5):

- Total length: **18,378 chars**.
- Eval user: `id=483`, `first_name=Test`, `marital_status=married`, `date_of_birth=NULL`, `annual_employment_income=NULL`, `is_eval_user=1`, `is_preview_user=0`, `onboarding_completed=1`.
- DB rows for this user: 1 `life_insurance_policies` row, 0 of everything else (savings, investments, DC pensions, properties, expenditure profile).

XML blocks **physically present** in the prompt as wrapper layers:

```
<identity>            <security>           <scope>           <personality>
<response_format>     <instructions>       <regulatory_compliance>
<fca_process>         <handoff_guidance>   <billing_guidance>
<user_profile>        <new_user_state>     <available_actions>
<user_provided>
```

XML blocks **absent** that should be present for an advice query:

```
<financial_context>   ← swapped out by the EmptyDataGuard branch
<existing_records>    ← swapped out by the EmptyDataGuard branch
<data_completeness>   ← swapped out by the EmptyDataGuard branch
<known_facts>         ← Sprint 1 S1.4 not shipped (separate issue)
<query_knowledge>     ← buildKnowledgeBlock returned empty for this classification
<tools_and_triggers>  ← buildToolsAndTriggersBlock returned empty for this classification
<fca_signposting>     ← buildFcaSignpostingBlock returned empty for this classification
<review_due>          ← buildReviewDueBlock returned empty (no upcoming reviews)
```

(The four substring matches the dashboard delta picks up for `<existing_records>` are **references to the tag inside `<fca_process>` and `<available_actions>`**, not the wrapper block itself. The tag the model is told to "use data from" does not exist in the prompt the model received.)

Verbatim from the captured `<user_profile>`:

```
<user_profile>
- First name (always use in full when addressing the user; do not truncate or parse): <user_provided>Test</user_provided>
- Marital status: married
</user_profile>
```

That is the entire user profile delivered to the model. No DOB, no income, no employment, no family. Two facts.

Verbatim from the substituted `<new_user_state>`:

```
<new_user_state>
This user has ZERO financial data: no income, no savings, no investments, no pensions, no protection, no property, no goals. You know nothing about their finances yet.

THE FOLLOWING RULES OVERRIDE EVERY OTHER INSTRUCTION IN THIS PROMPT (including the FCA process and the "use tools proactively" rule in <available_actions>):

1. NEVER reference any specific figure (£, %, age, years). Any number you produce would be fabricated.
2. NEVER call get_module_analysis, get_recommendations, generate_financial_plan, get_tax_information, or run any analysis tool. There is nothing to analyse.
3. NEVER call create_savings_account, create_investment_account, create_pension, create_property, create_protection_policy, create_goal, create_liability, or any other create_* tool UNLESS the user has explicitly told you about a specific holding in the current message (e.g. "I have a £10k ISA at Vanguard").
4. NEVER call update_profile UNLESS the user has just given you a specific personal value (DOB, marital status, employment, income, expenditure) in their current message.
...
</new_user_state>
```

The block is also **factually wrong about the user**. It says "no protection" — the user has a £100,000 Aviva life policy. The block was generated by `EmptyDataGuard::get()`, a static string; it does not consult the actual data. The scenario YAML asserts the model should answer a protection question, but the prompt tells the model the user has no protection. (See `app/Services/AI/Prompts/EmptyDataGuard.php`.)

## 2. The wiring bug

### 2.1 The branching point

`app/Services/AI/AdvicePromptBuilder.php:107-126`:

```php
// For brand-new users with zero financial data, skip layers 5/6/7
// (FinancialContext, ExistingRecords, DataCompleteness) and substitute
// a lightweight EmptyDataGuard block. Running orchestrateAnalysis
// against empty data causes Fyn to hallucinate specific figures — see
// April/April9Updates/fynQuickStartBugs.md for the prior incident.
if ($this->isNewUserWithNoData($user)) {
    $layers[] = EmptyDataGuard::get();
} else {
    // Layer 5: Financial Position (DYNAMIC/user) — recommendations filtered by classification
    $financialContext = $this->buildFinancialContext($user, $orchestrateAnalysis, $classification);
    $layers[] = "<financial_context>\n{$financialContext}\n</financial_context>";

    // Layer 6: Existing Records (DYNAMIC/query) — filtered by classification
    $existingRecords = $this->buildExistingRecordsSummary($user, $classification);
    $layers[] = "<existing_records>\n{$existingRecords}\n</existing_records>";

    // Layer 7: Data Completeness (DYNAMIC/user)
    $prerequisiteState = $this->buildPrerequisiteStateContext($user);
    $layers[] = $this->buildDataCompletenessBlock($prerequisiteState);
}
```

This is a hard structural swap. Either layers 5/6/7 are present, or `EmptyDataGuard` is present. Never both. The two prompts are not "the same prompt with sparser content"; they are **two different prompts** with different rule sets, and any model running on one cannot be compared to a model running on the other.

### 2.2 The incomplete guard

`app/Services/AI/AdvicePromptBuilder.php:272-304`:

```php
private function isNewUserWithNoData(User $user): bool
{
    $totalIncome = (float) ($user->annual_employment_income ?? 0)
        + (float) ($user->annual_self_employment_income ?? 0)
        + (float) ($user->annual_rental_income ?? 0)
        + (float) ($user->annual_dividend_income ?? 0)
        + (float) ($user->annual_interest_income ?? 0)
        + (float) ($user->annual_trust_income ?? 0)
        + (float) ($user->annual_other_income ?? 0);

    if ($totalIncome > 0)                                                      return false;
    if (\App\Models\SavingsAccount::forUserOrJoint($user->id)->exists())        return false;
    if (\App\Models\Investment\InvestmentAccount::forUserOrJoint($user->id)->exists()) return false;
    if (\App\Models\DCPension::where('user_id', $user->id)->exists())           return false;
    if (\App\Models\DBPension::where('user_id', $user->id)->exists())           return false;

    return true;
}
```

Modules NOT consulted by this function: `LifeInsurancePolicy`, `CriticalIllnessPolicy`, `IncomeProtectionPolicy`, `Property`, `Mortgage`, `Asset`, `Liability`, `EstateGift`, `Chattel`, `BusinessInterest`, `Trust`, `FamilyMember`, `Will`, `PowerOfAttorney`, `Goal`, `LifeEvent`, `WhatIfScenario`, `ExpenditureProfile`. A user with a £2 M trust, three buy-to-let properties, six children, and seven protection policies — but zero employment income and zero savings — is classified as "new user with no data" and gets the EmptyDataGuard treatment.

This is wrong on its own merits, **before** considering the eval. The function name says "new user with no data" but the implementation says "user without income, savings, investments, or pensions." Those are not the same thing. A real production user could trip this branch too — for example a fully retired user with everything in protection + property + estate + trust, who lists no income because retirement income is captured per-pension not as an `annual_*_income` field.

### 2.3 The seed YAMLs that trip the branch

For the six `01-query-types` scenarios:

| Scenario | Income seeded | Savings | Investments | DC/DB Pension | `isNewUserWithNoData` | Branch taken |
|---|---|---|---|---|---|---|
| `advice_protection_cover` | none | none | none | none | **TRUE** | **EmptyDataGuard** |
| `advice_savings_emergency` | none | 1 row | none | none | FALSE | layers 5/6/7 |
| `advice_investment_isa` | none | none | 1 row | none | FALSE | layers 5/6/7 |
| `advice_retirement_contribution` | `annual_income: 50000`† | none | none | 1 row | FALSE | layers 5/6/7 |
| `advice_estate_iht` | none | seeded | seeded | none | FALSE | layers 5/6/7 |
| `advice_goals_affordability` | `annual_income: 65000`† | seeded | seeded | seeded | FALSE | layers 5/6/7 |

† **Note:** `users.annual_income` is **not a column** in the schema. The seven income columns the User model casts (`annual_employment_income`, `annual_self_employment_income`, `annual_rental_income`, `annual_dividend_income`, `annual_interest_income`, `annual_other_income`, `annual_trust_income`) are the only income fields. The `annual_income: 50000` line in two YAMLs is silently dropped by `User::create` (assigned to a non-existent attribute, not persisted, not seen by `isNewUserWithNoData`). Those two scenarios escape the EmptyDataGuard branch only because they also seed savings/pensions; the `annual_income` line is dead text.

So **one of six** advice scenarios trips the EmptyDataGuard branch end-to-end; the others escape only by accident (because they happen to seed savings/investments/pensions for unrelated reasons). None of them seed `annual_employment_income` directly. This is fragile by accident, not by design.

### 2.4 What the dashboard delta says vs reality

`EvalRecordingController::buildDelta` reports a "passes" boolean and a "diagnosis hint" per provider run. For session #5, conv 145 (Anthropic Haiku 4.5), the delta will show:

- `expected_tool_calls`: `[get_module_analysis, get_recommendations]`
- `actual_tool_calls`: whatever the model emitted under EmptyDataGuard's prohibition

If the model obeyed the EmptyDataGuard rule it called nothing → `missing_tools = [get_module_analysis, get_recommendations]`, `extra_tools = []`. The dashboard's heuristic emits *"Provider made no tool calls at all — model ignored the available tool palette, or tools were not advertised in this provider's request"* and recommends fixes targeting the tool catalogue / `tool_choice` / xAI vs Anthropic differences.

**The diagnosis is wrong.** The tools were advertised. The catalogue is correct. The model ignored them because the system prompt told it to. The dashboard sends the operator chasing a non-existent tool-catalogue bug while the actual fault is two layers up in `AdvicePromptBuilder`.

If the model disobeyed EmptyDataGuard (Haiku 4.5 sometimes does on contradictory prompts), it called the tools — but with a `<user_profile>` containing two facts and no `<existing_records>` block to ground its answer. Any £-figure it produces is a fabrication, which `assertInterpretiveTextMapsToEngineSource` correctly flags. Same fault, different surface.

Either branch of model behaviour produces a misleading dashboard signal.

## 3. Why "same model, same user, same prompt" yielded different answers

CSJ's report: "I used the same model, the same user, the same inputs, the same prompt and got a different response. The system prompt when I ran the query injected the context and user profile info, this was absent from the eval run."

That observation is correct and now has a mechanism:

- **Same model** — Anthropic Haiku 4.5 in both cases.
- **Same user message** — "Am I covered enough for protection?".
- **Same code path** — `AdviceFyn::handle` → `chatWithPromptOverride` → `HasAiChat::chat` → `buildSystemPrompt` → `AdvicePromptBuilder::build` in both cases.
- **"Same user"** — interpreted as "a user asking the same protection question". The eval-seeded user `id=483` and the live user CSJ ran the manual query as are different rows. They differ on one signal that `isNewUserWithNoData` checks (income, savings, investments, or pensions).
- **The system prompt diverged because line 112 took different branches.** Live user → FALSE → layers 5/6/7 render with `buildUserProfile` (full DOB/income/employment/family), `buildFinancialContext` (orchestrateAnalysis output), `buildExistingRecordsSummary` (real DB rows, ID-tagged for update routing). Eval user → TRUE → those three layers replaced by 220 lines of "you have nothing, do nothing."

CSJ's framing — *"this is the very behaviour we are assessing"* — is exact. The eval **must** assert that for an advice query the prompt builder produces a fully-rendered prompt with `<user_profile>`, `<financial_context>`, `<existing_records>`, `<data_completeness>` populated. If the eval cannot guarantee that, it cannot grade the model's response to that prompt. As wired, `advice_protection_cover` does not produce that prompt at all — it produces the empty-data guard prompt instead, and the dashboard reads as if it were the same artefact.

## 4. Why the eval is broken end-to-end (not just for this scenario)

Three independent faults compound:

**Fault 1 — `isNewUserWithNoData` is module-incomplete.** It looks at 4 signals, ignores 12. Easy to trip; easy to mistakenly stay out of. Even after fixing the eval seeds, a real user pattern (retiree with no employment/savings, all wealth in trust + property) trips this branch in production today and gets the EmptyDataGuard prompt for an advice query. That's a bug independent of evaluation.

**Fault 2 — The eval seed YAMLs do not populate the signals the guard checks.** Even the seeds that "look like" they have data (`annual_income: 50000`) write to a non-existent column. `seedChildEntities` (`EvalRecordCommand:753-770`) supports six entity types — `protection_policies`, `savings_accounts`, `investment_accounts`, `dc_pensions`, `properties`, `expenditure` — and silently warns on anything else. A scenario that wants to assert behaviour on a populated income field cannot do so via the current YAML schema.

**Fault 3 — The dashboard's diagnostic heuristic blames the wrong layer.** `EvalRecordingController::buildDelta` (line 246-282) emits root-cause hints about tool catalogues, tool_choice, and tool descriptions when no tool calls are made — never about the system prompt rejecting the call. Operators reading the dashboard are pointed away from the actual cause.

A scenario that hits all three faults — `advice_protection_cover` does — produces a recording that is undiagnosable from the dashboard alone, scored against a contract the prompt itself prohibits, and then surfaces a "fix the tool catalogue" suggestion that won't fix anything. That recording then influences engineering judgement about the live system. It is worse than no recording — it is misinformation with a clean UI.

## 5. The fix

Three changes, in this order:

### 5.1 Fix the prompt builder's guard (the actual root cause)

`AdvicePromptBuilder::isNewUserWithNoData` should reflect what its name claims: a user with **no records anywhere**. Either:

- (a) Extend the function to check every module the prompt's downstream layers reference (protection, property, mortgage, family, goals, life events, estate gifts, trusts, wills, business interests, chattels, expenditure, what-if). If any one returns a row, the user is not "new with no data."
- (b) Replace it with `! $user->onboarding_completed && $totalRecords === 0`. Onboarded users are by definition not "new" — they went through the onboarding flow that captures at least name + DOB + income. If `onboarding_completed = true` then the right behaviour is to render layers 5/6/7 regardless of how sparse the modules are; `buildFinancialContext` and `buildExistingRecordsSummary` already handle empty modules gracefully.

I recommend (b). It is one line of logic, it removes the module-by-module enumeration that drifts as new modules ship, and it aligns with the comment block above the function ("Spouse data (if any) still wires through the normal flow — a newly registered user with a linked spouse who already has data is not considered 'new' for prompt purposes" — i.e. the intent is to gate on the onboarding boundary, not on data shape).

The original concern (`orchestrateAnalysis hallucinating against empty modules`) is addressed inside `buildFinancialContext` — it can `return ''` for empty modules, and the wrapper renders `<financial_context></financial_context>` which the model treats as "no data, ask the user." The hard-swap structural branch is the wrong tool for that job.

**File:** `app/Services/AI/AdvicePromptBuilder.php:272-304`. **Diff:** ~5 lines.

### 5.2 Make seeds populate the signals the guard checks (defensive)

After 5.1, this is belt-and-braces. Two changes to `EvalRecordCommand`:

- Reject any seed YAML whose `seed.user` references columns that do not exist on `users`. `User::create` silently drops unknown attributes; the YAML should not. Add a fillable-check before the create.
- Promote `seed.user.annual_employment_income` (or whichever income field the scenario means) to a first-class field in every advice scenario YAML. A scenario asking "should I contribute more to my pension?" must seed an income; a scenario asking "am I covered enough?" needs whatever data the model would need to answer that question for a real user.

**Files:** `app/Console/Commands/EvalRecordCommand.php:721-748`, every `tests/Feature/Fyn/Eval/scenarios/01-query-types/*.yaml`.

### 5.3 Fix the dashboard delta hints

`EvalRecordingController::buildDelta` should detect "expected tools missing AND `<new_user_state>` present in the captured system prompt" and emit a different hint: *"The system prompt for this run substituted EmptyDataGuard for layers 5/6/7. The model was told NEVER to call analysis tools. Fix `isNewUserWithNoData` or seed data that escapes the empty-data branch — the tool catalogue is not the problem."*

**File:** `app/Http/Controllers/Api/Admin/EvalRecordingController.php:246-282`. **Diff:** ~20 lines.

### 5.4 Re-record session #5 after 5.1 lands

Sessions recorded against the buggy prompt builder are not useful as fixtures or as training-signal — they reflect the EmptyDataGuard prompt, not the live prompt. After 5.1 lands, every fixture under `tests/Feature/Fyn/Eval/fixtures/` should be re-recorded. This is a one-shot operation: `php artisan eval:record <scenario-id>` for each of the 11 authored scenarios.

`EvalPurgeCommand` already exists for cleanup. Add a `--re-record` flag that purges + re-records, preserving session ids.

**File:** `app/Console/Commands/EvalPurgeCommand.php`. **Diff:** ~30 lines. Or just run two commands sequentially.

## 6. What this means for Sprint 1 acceptance

`April/April24Updates/plan/11-sprint-1-plan.md` S1.10 verification rollup says: *"Rubric-B Mode 1 at 30/30, Rubric-A re-score 17-18/40 🟠 Limited beta, gate for Sprint 3 dev deploy."*

If Mode 1 runs at 30/30 against fixtures recorded under the buggy prompt builder, the green is meaningless — it certifies the model reproduces the EmptyDataGuard-shaped behaviour, not the layered-prompt behaviour the canonical contract assumes. Rubric-A scoring on the same artefacts is doubly compromised, because the dimension judgements (completeness, specificity, signposting) all assume the model received `<financial_context>` + `<existing_records>` content that wasn't actually there.

**S1.10 cannot honestly green** until 5.1 ships and fixtures are re-recorded. The plan's Pre-flight gate ("Rubric-A ≥13/40") and end-state gate ("Rubric-A 17-18/40") are scored against artefacts that don't reflect production behaviour. Re-scoring after 5.1 is mandatory.

This is also the kind of regression that the canonical-contract scenario category (`09-canonical-behaviour`) is supposed to catch. That category is currently empty (0 scenarios authored vs. plan target 10). The first scenario in it should be: *"For an onboarded user with any DB record in any module, AdvicePromptBuilder produces a prompt containing `<financial_context>` and `<existing_records>` wrapper blocks."* That assertion fails today.

## 7. What v1 of this document got wrong

For the record, so the failure mode does not repeat:

- v1 measured tag presence by substring count over the full prompt (`grep`), not by checking which were rendered as wrapper layers vs referenced inside other blocks. That gave a false reading that `<financial_context>` and `<existing_records>` were present. They were not — the matches were references to those tag names inside `<fca_process>` and `<available_actions>`.
- v1 reported the divergence as "data sparsity, seed eval users from preview personas." That described what would also help, but it was a workaround for a downstream symptom, not a fix for the root cause. The user's instruction was correct: investigate properly first; the same code path should not be producing different prompts for same-shape inputs.
- v1 treated "the eval system is useless and redundant" as overstated framing. It is not overstated. As wired, the eval recording for `advice_protection_cover` cannot grade the live system — it grades a different system the live user never sees. That meets "useless" by any reasonable reading.

The lesson: when the user reports a specific empirical observation about a captured artefact, open the artefact first. The root cause was twelve lines of code visible in the file the dashboard already exposes via `/admin/eval-recordings/{id}/system-prompt`.

---

## Source files referenced (re-read at audit time, not recalled)

- `app/Services/AI/AdvicePromptBuilder.php:107-126, 195-304`
- `app/Services/AI/Prompts/EmptyDataGuard.php`
- `app/Console/Commands/EvalRecordCommand.php:721-770`
- `app/Http/Controllers/Api/Admin/EvalRecordingController.php:142-308`
- `app/Models/User.php` — confirmed seven `annual_*_income` columns; `annual_income` is not a column
- `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_protection_cover.yaml`
- `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_savings_emergency.yaml`
- `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_investment_isa.yaml`
- `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_retirement_contribution.yaml`
- `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_estate_iht.yaml`
- `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_goals_affordability.yaml`
- Live DB row: `ai_messages` for `conversation_id = 145`, `users` row `id = 483`
- `April/April27Updates/system-prompt-audit.md` (sister document, same session)
