# Fix Eval — task list

*Authored 2026-04-27. Sources: `eval-system-vs-live-flow-audit.md` (this folder, v2) and `system-prompt-audit.md` (this folder). Branch: `feature/fyn-persona-split`.*

Tasks listed in execution order. Each task is self-contained and lists file/line, change, rationale, acceptance.

## Status board

| # | Task | Status |
|---|---|---|
| 1 | Delete `isNewUserWithNoData` + `EmptyDataGuard` branch | ✅ Complete (2026-04-27) |
| 2 | Move `<billing_guidance>` out of unconditional system prompt (Option A) | ✅ Complete (2026-04-27) |
| 3 | Validate eval seed YAML against User schema | ✅ Complete (2026-04-27) |
| 3b | Seed every advice scenario with universal KYC requirements | ✅ Complete (2026-04-27) |
| 3c | Surface KYC + DataReadiness state in dashboard | ⬜ Pending |
| 4 | Fix `EvalRecordingController::buildDelta` heuristic | ⬜ Pending |
| 5 | Re-record fixtures (depends on 1, 3, 3b) | ⬜ Ready to run |
| 6 | Add `09-canonical-behaviour` regression scenario | ⬜ Pending |
| 7 | Backlog — `<known_facts>` (Sprint 1 S1.4) | ⬜ Pending |

---

## 1. Delete `isNewUserWithNoData` and the `EmptyDataGuard` branch — trust the existing KYC + DataReadiness machinery

**Status: ✅ COMPLETE — landed 2026-04-27.**

**Files changed:**
- `app/Services/AI/AdvicePromptBuilder.php` — removed `EmptyDataGuard` import (line 12), removed the `if/else` structural-swap branch at lines 107-126 (now always renders Layers 5/6/7 unconditionally), removed orphan doc comment at lines 195-206, removed `isNewUserWithNoData` function at lines 272-304.
- `app/Services/AI/Prompts/EmptyDataGuard.php` — **deleted**.
- `tests/Unit/Services/AI/AdvicePromptBuilderStructuralLayersTest.php` — **added** (8 tests, 37 assertions).

**Test results (against the change):**
- New test: 8/8 passing.
- `tests/Unit/Services/AI` suite: 87/87 passing (174 assertions).
- `tests/Architecture` suite: 200/200 passing.
- 3 pre-existing failures in `tests/Feature/AI` (`classifyComplexity` null at `HasAiChat:130` — `AssistantHonestyOnWriteFailureTest`, `ConsentRuntimeCheckTest` × 2). Confirmed pre-existing by stashing the change and re-running: same 3 failures present without this fix. Tracked separately, not blocked by this task.

**Original location references (for archival traceability):**
`app/Services/AI/AdvicePromptBuilder.php:107-126` (call site), `:272-304` (function), `app/Services/AI/Prompts/EmptyDataGuard.php` (the substituted block), `app/Services/AI/AdvicePromptBuilder.php:12` (use statement).

### Why a structural swap is the wrong shape

Two reasons gating data context on a single boolean is wrong, beyond the module-incomplete bug already documented in the audit:

**a) Partial-onboarding users are mis-served.** A user who paused onboarding via the "Something else" handoff has `onboarding_completed = false` AND `onboarding_fyn_step = null` — they route through `AdviceFyn` (not the director), per `AiChatController:174-176`. They may have entered name, DOB, marital status, employment, but no income/savings/investments/pensions yet. Today they trip the EmptyDataGuard branch even though the KYC gate would correctly route them to `/valuable-info?section=income`. Yesterday's proposed fix (gate on `onboarding_completed`) would have mis-served them too — it would have returned TRUE for a user who has data, just not finished. There is no single boolean that gets this right.

**b) The infrastructure to do this correctly already exists, runs on every advice turn, and tracks every input field-by-field.** It just isn't trusted, because EmptyDataGuard short-circuits over the top of it.

### What is already wired and running every turn

| Layer | Source | What it tracks | Block injected |
|---|---|---|---|
| **Layer 7 — `<data_completeness>`** | `PrerequisiteGateService::buildCompletenessContext($user)` (`PrerequisiteGateService.php:275-312`) | Per-module READY/BLOCKED state with completeness percentage. Delegates to five `DataReadinessService` classes (`ProtectionDataReadinessService`, `SavingsDataReadinessService`, `RetirementDataReadinessService`, `InvestmentDataReadinessService`, `EstateDataReadinessService`) — each running the module's blocking + warning checks against the user's actual fields and rows. | "Protection: READY (75% complete) (2 optional fields missing)" / "Estate: BLOCKED -- date_of_birth: required for IHT calc -- navigate user to: /profile" |
| **Layer 9 — `<kyc_status>`** | `KycGateChecker::check($user, $classification)` (called from `HasAiChat:117-122`, injected via `buildSystemPrompt`'s `$kycResult` arg). | Per-classification, per-turn gate. Universal requirements: DOB, marital_status, employment_status, income (any of 7 sources > 0), expenditure. Module requirements: delegated via `PrerequisiteGateService::enforce` per classified module. | PASSED with summary OR BLOCKED with missing-list + exact navigation routes + mandatory instructions ("Do NOT give advice... navigate to /profile..."). |
| **Layer 5 — `<financial_context>`** | `buildFinancialContext` (`AdvicePromptBuilder.php:420-682`). | Net worth, surplus, per-module totals. Each line guarded with `($x ?? 0) > 0` — empty modules render no lines. **Already defensive against the original April 9 hallucination incident** that prompted EmptyDataGuard. | Lines for what exists; silent on what doesn't. |
| **Layer 6 — `<existing_records>`** | `buildExistingRecordsSummary($user, $classification)`. | Lists actual DB rows by ID for the classified modules, used for duplicate detection and update-vs-create routing. | One line per row, filtered by classification. |

`EmptyDataGuard` was added defensively (`AdvicePromptBuilder.php:107-111` comment refers to `April/April9Updates/fynQuickStartBugs.md`) **before** `KycGateChecker` and the DataReadiness services were built out. The KYC block now does the same job correctly:

- For an empty-data user asking an advice question, KYC returns BLOCKED with "Date of birth, Annual income, Monthly expenditure" missing and routes to `/profile` + `/valuable-info`. The block contains **MANDATORY INSTRUCTIONS** stronger than EmptyDataGuard's: "Do NOT give advice, estimates, or general guidance on this topic". The model receives a clearer, more specific signal than EmptyDataGuard's "ZERO financial data, NEVER reference figures".
- For a partial-data user, KYC returns BLOCKED on the specific missing fields. The model knows exactly what to ask for.
- For a full-data user, KYC returns PASSED with a per-module summary. Layers 5/6/7 render. Tools permitted.

**EmptyDataGuard is doing a job KYC already does — worse, less specifically, and bypassing the field-level tracking the user explicitly asked us to use.**

### Change

```php
// AdvicePromptBuilder::build, replace lines 107-126 with:

// Layer 5: Financial Position (DYNAMIC/user) — recommendations filtered by classification.
// buildFinancialContext is defensive: empty modules render no lines, missing values
// guarded with `?? 0 > 0`. The historical hallucination bug (April9 fynQuickStartBugs)
// is closed by those guards + the KYC BLOCKED block at Layer 9.
$financialContext = $this->buildFinancialContext($user, $orchestrateAnalysis, $classification);
$layers[] = "<financial_context>\n{$financialContext}\n</financial_context>";

// Layer 6: Existing Records (DYNAMIC/query) — filtered by classification.
$existingRecords = $this->buildExistingRecordsSummary($user, $classification);
$layers[] = "<existing_records>\n{$existingRecords}\n</existing_records>";

// Layer 7: Data Completeness (DYNAMIC/user) — per-module READY/BLOCKED via
// PrerequisiteGateService → DataReadinessService. Field-level tracking.
$prerequisiteState = $this->buildPrerequisiteStateContext($user);
$layers[] = $this->buildDataCompletenessBlock($prerequisiteState);
```

Then:
- **Delete** `AdvicePromptBuilder::isNewUserWithNoData` (lines 195-304).
- **Delete** the `use App\Services\AI\Prompts\EmptyDataGuard;` import (line 12).
- **Delete** `app/Services/AI/Prompts/EmptyDataGuard.php`.
- Layer 9 (`<kyc_status>`) already runs unconditionally for non-bypass classifications via `HasAiChat:117-122`. Confirm the KycGateChecker is producing BLOCKED output for empty-data users by adding the universal-requirements unit test in §1.acceptance below.

### Rationale

- **Removes the structural prompt swap.** Same code path produces the same prompt structure for any user, regardless of data shape. That is the eval/live divergence cause.
- **Uses what's already tracking every input.** PrerequisiteGateService + 5 × DataReadinessService + KycGateChecker collectively assess every blocking and warning field per module. The user explicitly asked for this. They produce more specific guidance than EmptyDataGuard's blunt "you have nothing".
- **Correctly handles partial-onboarding users.** No `onboarding_completed` gate. Whatever the user has, layers render. Whatever they don't, KYC blocks specifically and the model navigates them to fix it.
- **Removes the lie.** The captured prompt for session #5 says "no protection" while the user has a protection policy. After the change, `<existing_records>` lists the policy and KYC's per-classification check evaluates the protection module honestly.

### Acceptance

- ✅ **New unit test `tests/Unit/Services/AI/AdvicePromptBuilderStructuralLayersTest.php`** — 8 tests, 37 assertions, all green. Covers: empty user, partial-data user (DOB + marital + protection-only — the case the deleted function mis-classified), full-data user, kyc-result passthrough, and a parameterised "same code path produces same prompt structure" assertion across all three user shapes.
- ✅ **No remaining references to `EmptyDataGuard` or `isNewUserWithNoData` in production code or tests** — verified via `grep -rn`.
- ✅ **Adjacent suites green** — `tests/Unit/Services/AI` 87/87, `tests/Architecture` 200/200.
- ⏳ **Pending — Task 5:** Re-record session #5 (and all 10 authored scenarios) and verify the captured `system_prompt` contains `<financial_context>` + `<existing_records>` + `<data_completeness>` wrapper blocks with no `<new_user_state>`. Requires Tasks 3 + 3b first so the seed YAMLs populate the universal KYC requirements; otherwise KYC will (correctly) block the recording.
- ⏳ **Pending — separate ticket:** Pre-existing `classifyComplexity` null bug at `HasAiChat:130` causes 3 feature tests in `tests/Feature/AI` to fail. Not caused by this task; blocking those tests' assertions but not this task's acceptance.

### Out of scope for this task

- Reviewing whether `buildFinancialContext` / `buildExistingRecordsSummary` need additional defensive guards — they already have `?? 0 > 0` guards on every numeric line. If a regression surfaces during re-recording, treat as a separate bug.

---

## 2. Move `<billing_guidance>` out of the unconditional system prompt (Option A)

**Status: ✅ COMPLETE (Option A) — landed 2026-04-27.**

**Files changed:**
- `app/Constants/QuerySchemas.php` — added `BILLING` constant; added it to `FACTUAL_TYPES`, `MODULE_MAP` (no Fynla module), `IMPLICIT_RELATED` (no implicit related types), and a new `KEYWORD_PATTERNS` block (invoice / receipt / billing / subscription status / next charge / when am I charged / current plan / my plan).
- `app/Services/AI/KycGateChecker.php` — replaced the explicit `$primary === GENERAL` early-bypass with `in_array($primary, FACTUAL_TYPES, true)` so any factual type (GENERAL, BILLING, future additions) auto-bypasses KYC. Billing queries no longer get blocked by missing DOB/income/expenditure.
- `app/Services/AI/AdvicePromptBuilder.php` — added `isBillingQuery(?array $classification): bool` guard. Replaced the unconditional `getBillingGuidance()` injection with `if (! $isPreview && $this->isBillingQuery($classification))`. Block now fires only when classifier returns BILLING.
- `tests/Unit/Services/AI/AdvicePromptBuilderStructuralLayersTest.php` — added 4 billing-gate tests (omit on advice classifications, omit on general/data-entry/navigation/out-of-remit, inject on BILLING, omit in preview mode even on BILLING).

**Why Option A (not B):** Option B (move guidance into tool descriptions) was rejected by CSJ. Tool descriptions are picked individually per call; the dual-call requirement ("ALWAYS call BOTH `get_subscription_status` AND `list_invoices` in the same turn") is a higher-level rule that fits naturally as a classification-gated prompt layer. Option A also pins the response shape (subscription line + count phrase + reverse-chronological list) which BS-16 acceptance requires.

**Test results:**
- New billing tests: 4/4 passing.
- Full structural-layers test: 12/12 passing (37 → 49 assertions).
- AI unit suite: 91/91 passing (was 87/87 — 4 new billing tests).
- Architecture suite: 200/200 passing.
- Total `tests/Unit/Services/AI` + `tests/Architecture`: 204/204 (1 skipped, 504 assertions).

**Token-budget impact:** ~830 chars no longer ship on every protection / savings / investment / retirement / estate / goals / affordability / holistic-health turn. Only billing-classified turns load the block.

**Original location references (for archival traceability):**
`app/Services/AI/AdvicePromptBuilder.php:88-101` (old unconditional injection site), `:250-270` (`getBillingGuidance` helper, kept), `app/Services/AI/AiToolDefinitions.php:1440-1471` (tool descriptions, unchanged).

### Why is the billing block in the system prompt today?

Per `system-prompt-audit.md`, the block was added on 2026-04-26 03:40 in commit `c51e7ff` to make BS-16 GREEN. grok-4-1-fast-reasoning was answering "Where's my invoice?" by calling `list_invoices` only — producing a list-only reply that omitted the subscription status line and the "You have N invoices" count phrasing the BS-16 acceptance criteria require. The fix forced the dual-tool call and pinned the reply shape via a flat (non-classification-gated) prompt block.

It works for BS-16. But it ships ~830 chars of billing instructions on **every advice turn** for non-preview users — every protection, savings, investment, retirement, estate, goals query loads billing guidance the model does not need, crowding the token budget that should be going to module-relevant context.

### Why is it not a tool call?

It should be. Two co-equal reasons it ended up in the prompt instead:

1. **Anthropic + xAI tool descriptions are short.** `get_subscription_status.description` (line 1443) is one sentence; `list_invoices.description` (line 1453) is one sentence. Neither encodes the "lead with subscription line / state count phrase / list reverse-chronologically" response shape. The author chose to encode that shape in a system-prompt block rather than expanding the tool descriptions.

2. **The dual-call requirement (BOTH tools in one turn) is hard to encode in a single tool's description.** The model picks tools individually; "always call this AND that for the same query" is naturally a higher-level rule. But that higher-level rule belongs in a **classification-gated** prompt layer (only fires when the classifier returns `billing` / `subscription` / `invoice`), not in the unconditional layer that every advice turn loads.

### Change

**Option A — preferred: classification-gated layer.**

```php
// In AdvicePromptBuilder::build, after the existing layer 8b:
$billingBlock = $this->buildBillingGuidanceBlock($classification);
if ($billingBlock !== '') {
    $layers[] = $billingBlock;
}

// New helper:
private function buildBillingGuidanceBlock(?array $classification): string
{
    $primary = $classification['primary'] ?? null;
    $billingTypes = [
        QuerySchemas::BILLING_INVOICE,
        QuerySchemas::BILLING_SUBSCRIPTION,
        QuerySchemas::BILLING_PAYMENT,
    ]; // add to QuerySchemas if not present
    if (! in_array($primary, $billingTypes, true)) {
        return '';
    }
    return $this->getBillingGuidance(); // existing string
}
```

Remove the unconditional injection at line 99-101. The block now fires only when `QueryClassifier` returns a billing classification — i.e. when it actually applies.

**Option B — alternative: expand tool descriptions.** Move the response-shape guidance into the `description` fields of `get_subscription_status` and `list_invoices`. Drop the prompt block entirely. This is cleaner architecturally (tool guidance lives next to the tool) but harder to express the dual-call requirement in a single tool's description. Acceptable if A is rejected.

**Rationale:**
- Removes ~830 chars from every non-billing advice turn — measurable token savings on the hottest path.
- Pins the contract that response-shape guidance is classification-gated (matches how `<knowledge>`, `<tools_and_triggers>`, `<fca_signposting>` already behave).
- Eval scenarios that don't classify as billing can no longer pass-or-fail on the model's reading of an unrelated billing block.

**Acceptance:**
- `KnownFactsBlockTest` analogue for billing: build base prompt with non-billing classification → `<billing_guidance>` not present. Build with billing classification → block present.
- BS-16 still GREEN after the change. (Re-run live; do not assume.)
- `system-prompt-audit.md` Q1/Q3 close out.

---

## 3. Validate eval seed YAML against the User schema

**Status: ✅ COMPLETE — landed 2026-04-27.**

**Files changed:**
- `app/Console/Commands/EvalRecordCommand.php` — added schema validation in `seedUser` before `User::create`. Uses `Schema::getColumnListing('users')` (the actual database table columns, not `$fillable` — User uses `$guarded = []` so there's no fillable list to intersect against). Any seed.user key not in the column list triggers a `RuntimeException` with a message naming the unknown keys and pointing at the `annual_income` → `annual_employment_income` example. Fail-loud at the seed stage stops bad recordings from ever reaching the LLM.

**Original location references:**
`app/Console/Commands/EvalRecordCommand.php:721-748` (`seedUser`).

**Verified behaviour:**
- Before Task 3b's YAML fixes: `php artisan eval:record advice_retirement_contribution --dry-run` failed with `Scenario seed.user contains keys that are not columns on users: annual_income. Either fix the YAML (e.g. annual_income → annual_employment_income) or add the column via a migration first.` ✓
- After Task 3b's YAML fixes: dry-run passes for all 10 authored scenarios.

---

## 3b. Seed every advice scenario with the universal KYC requirements

**Status: ✅ COMPLETE — landed 2026-04-27.**

**Files changed:** all six advice scenarios in `tests/Feature/Fyn/Eval/scenarios/01-query-types/`:
- `advice_protection_cover.yaml` — added `date_of_birth`, `employment_status`, `annual_employment_income`, `monthly_expenditure`. Also fixed `monthly_premium` → `premium_amount` + `premium_frequency` on the seeded protection policy (matches `life_insurance_policies` schema).
- `advice_savings_emergency.yaml` — added `date_of_birth`, `marital_status`, `employment_status`, `annual_employment_income`, `monthly_expenditure`.
- `advice_investment_isa.yaml` — same five additions.
- `advice_retirement_contribution.yaml` — replaced `annual_income: 50000` → `annual_employment_income: 50000`. Added `date_of_birth`, `marital_status`, `employment_status`, `monthly_expenditure`.
- `advice_estate_iht.yaml` — added `date_of_birth`, `employment_status`, `annual_employment_income`, `monthly_expenditure` (kept `marital_status: married`, age tuned for IHT realism: DOB 1968).
- `advice_goals_affordability.yaml` — replaced `annual_income: 65000` → `annual_employment_income: 65000`. Added `marital_status`, `employment_status`, `monthly_expenditure: 2800`. Removed the stray top-level `expenditure: { monthly_total: 2800 }` block (key was not a column on `expenditure_profiles`; KYC reads `users.monthly_expenditure` first).

The four authored multi-entity scenarios in `03-multi-entity/` were left unchanged — they route through `OnboardingChatDirector` (`asset_capture` step), not `AdviceFyn`. No KYC gate runs on those turns.

**End-to-end verification (post-fix, dry-run + tinker):**
For `advice_protection_cover` the seeded eval user (id=494) had:
- `date_of_birth=1985-04-15`, `marital_status=married`, `employment_status=employed`
- `annual_employment_income=50000.00`, `monthly_expenditure=2500.00`
- 1 `life_insurance_policies` row

`KycGateChecker::check($user, ['primary' => 'protection_cover', 'modules' => ['protection']])` returned **`passed=1`, `missing=[]`**. The model would receive `<kyc_status>KYC CHECK: PASSED</kyc_status>` and be permitted to call `get_module_analysis(protection)` + `get_recommendations` — exactly what the scenario YAML asserts.

All 10 scenarios pass `php artisan eval:record <id> --dry-run` (validator + seed phase) cleanly.

**Original location references and acceptance criteria** (for archival):

**Change:** After Task 1 lands, `<kyc_status>` becomes the gate that decides whether the model can call analysis tools. `KycGateChecker::checkUniversalRequirements` (KycGateChecker.php:92-130) blocks unless ALL of these are present:

- `date_of_birth` (non-null)
- `marital_status` (non-null)
- `employment_status` (non-null)
- Income: at least one of `annual_employment_income` / `annual_self_employment_income` / `annual_rental_income` / `annual_dividend_income` / `annual_interest_income` / `annual_other_income` / `annual_trust_income` > 0
- Expenditure: `monthly_expenditure > 0` OR `annual_expenditure > 0` OR `expenditureProfile.total_monthly_expenditure > 0`

Plus per-module requirements via `PrerequisiteGateService::enforce` for whichever modules the scenario's classification touches.

Each advice scenario YAML's `seed.user` block must populate the universal set unless the scenario is **specifically testing the KYC-blocked path**. For `advice_protection_cover`, the seed becomes:

```yaml
seed:
  user:
    first_name: Test
    surname: User
    date_of_birth: '1985-04-15'
    marital_status: married
    employment_status: employed
    annual_employment_income: 50000
    onboarding_completed: true
  expenditure:
    total_monthly_expenditure: 2500
  protection_policies:
    - { provider: Aviva, policy_type_group: life, sum_assured: 100000, monthly_premium: 25 }
```

Plus any module-specific requirements (`ProtectionDataReadinessService::assess` for `advice_protection_cover` — verify with `php artisan tinker` against a freshly-seeded eval user).

For scenarios deliberately testing the KYC-BLOCKED path, name them explicitly: `advice_kyc_blocked_no_dob.yaml`, etc., under a new `01-query-types/` sub-section or `09-canonical-behaviour/`.

**Rationale:** After Task 1 lands, the prompt structure is correct, but KYC will (correctly) block every scenario that doesn't seed the universal requirements. The current six query-type scenarios all fail this — none seed DOB, none seed `employment_status`, only two seed `expenditure`, only one seeds an income column directly. Without this task, Task 1 fixes the structure but every advice scenario still fails on the KYC gate.

**Acceptance:**
- For each advice scenario, after re-recording (Task 5), the captured `<kyc_status>` block contains "PASSED" not "BLOCKED" — verified via `php artisan eval:show --session=N`.
- A new scenario `tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/kyc-blocked-no-dob.yaml` deliberately omits DOB and asserts the model receives `<kyc_status>BLOCKED` and produces a navigation/ask response, not analysis tool calls.

---

## 3c. Surface KYC + DataReadiness state in the dashboard recording

**Files:** `app/Models/EvalProviderRun.php`, `app/Http/Controllers/Api/Admin/EvalRecordingController.php` (show endpoint), Vue dashboard component.

**Change:** Persist on each `EvalProviderRun`:

- `kyc_status` — `passed` / `blocked` / `bypass`
- `kyc_missing` — JSON list of missing universal/module requirements
- `data_readiness` — JSON map per module: `{ protection: { completeness_percent: 75, blocking_passed: true, ... }, savings: ... }` (the `assessAll` output)

Captured from the AI message metadata (already produced by KycGateChecker + PrerequisiteGateService) at the same point `tool_calls` are extracted (`EvalRecordCommand::extractToolCalls`).

Dashboard `show` endpoint exposes them; the Vue component renders KYC pass/block + per-module readiness alongside the system-prompt and tool-call panels.

**Rationale:** When the eval recording's response is "wrong", the operator's first question should be "did the prompt have what it needed?" — i.e. did KYC pass, are blocking checks satisfied per module. Today they have to inspect the system prompt manually. After this task, the dashboard shows it directly. Removes the diagnostic gap that led the system-prompt audit and the eval-vs-live audit to be necessary in the first place.

**Acceptance:**
- Dashboard for any session shows a "Prompt readiness" panel: "KYC: PASSED", per-module "Protection: READY 75%", etc.
- For a deliberately KYC-blocked scenario, the panel shows "KYC: BLOCKED — DOB, Income missing".

---

## 4. Fix `EvalRecordingController::buildDelta` heuristic

**File:** `app/Http/Controllers/Api/Admin/EvalRecordingController.php:246-282`.

**Change:** Add a check before the existing "no tool calls" hint: if the captured system prompt contains `<new_user_state>` (i.e. EmptyDataGuard fired) AND `expected_tool_calls` is non-empty, emit a new hint:

```
"The system prompt for this run substituted EmptyDataGuard for layers 5/6/7
(the `<new_user_state>` block is present). The model was instructed NEVER to
call analysis tools. The tool catalogue is NOT the problem — fix
AdvicePromptBuilder::isNewUserWithNoData (audit §2.2) or seed data that
escapes the empty-data branch (audit §2.3)."
```

**Rationale:** Today the dashboard tells the operator to investigate xAI vs Anthropic tool catalogues, `tool_choice`, and tool descriptions when no tool calls are made. For sessions that hit the EmptyDataGuard branch, that hint sends the operator chasing a non-existent bug. See audit §2.4.

**Acceptance:**
- Open session #5 in dashboard before re-recording → see new hint pointing at `isNewUserWithNoData`.
- Open a session for a scenario that genuinely doesn't advertise tools (e.g. preview persona without tools) → see the existing hint.

---

## 5. Re-record fixtures after Tasks 1 and 3 land

**Files:** `tests/Feature/Fyn/Eval/fixtures/{anthropic,xai}/{model}/*.jsonl`.

**Change:** Run for each authored scenario:

```bash
php artisan eval:record advice_protection_cover
php artisan eval:record advice_savings_emergency
php artisan eval:record advice_investment_isa
php artisan eval:record advice_retirement_contribution
php artisan eval:record advice_estate_iht
php artisan eval:record advice_goals_affordability
php artisan eval:record protection_2x_known_providers
php artisan eval:record protection_2x_unknown_providers
php artisan eval:record savings_3x_mixed
php artisan eval:record pensions_2x_schemes
```

**Rationale:** Fixtures recorded before Task 1 reflect the EmptyDataGuard prompt. They cannot be replayed as canonical for the live system. Mode 1 replays under `MockedProviderClient` would assert a contract that doesn't match production behaviour. See audit §6.

**Acceptance:**
- `EvalProviderRun.fynla_sha` for every fresh run is the SHA after Tasks 1 + 3 land.
- For `advice_protection_cover` specifically, the captured `system_prompt` for the new conv contains `<financial_context>` and `<existing_records>` wrapper blocks (verified via `php artisan eval:show --session=N`).

---

## 6. Add `09-canonical-behaviour` regression scenario for the prompt-builder bug

**File:** `tests/Feature/Fyn/Eval/scenarios/09-canonical-behaviour/empty-data-guard-only-when-truly-empty.yaml` (new).

**Change:** Author a scenario that seeds a user with `onboarding_completed: true` plus exactly one record in a single non-money module (one protection policy, no income). User asks an advice question. Assert:

```yaml
expected_system_prompt_contains:
  - "<financial_context>"
  - "<existing_records>"
expected_system_prompt_absent:
  - "<new_user_state>"
expected_tool_calls:
  - tool: get_module_analysis
    args: { module: protection }
```

This requires extending `AssertionHelpers` with `assertSystemPromptContains` / `assertSystemPromptAbsent` checks against the captured `ai_messages.system_prompt`. ~30 lines.

**Rationale:** Sprint 1 plan S1.10 names `09-canonical-behaviour` as the merge-blocking category. The first scenario in it should be the regression that just bit us. If this scenario fails, no merge to `feature/fyn-persona-split` should land.

**Acceptance:**
- Scenario green after Task 1 lands.
- Scenario red if Task 1 reverts (regression-protected).

---

## 7. Backlog — `<known_facts>` block (Sprint 1 S1.4)

Not part of this fix-set, but flagged here so it's visible in one place.

`MemoryRetrieverService` does not exist on the branch. Sprint 1 S1.4 is unshipped. Once it lands, the `<known_facts>` block joins the layered prompt and the eval scenarios that depend on it (`memory-no-repeat-ask.yaml` per S1.4 acceptance) become authorable. Tracked under Sprint 1 S1.4 in `April/April24Updates/plan/11-sprint-1-plan.md`; not a fix for this audit, but the eval cannot fully grade Advice Fyn until it ships.

---

## Execution order & gating

```
1 ✅ ──► 3 ✅ ──► 3b ✅ ──► 5 (ready) ──► 6 (regression net)
                            │
2 ✅ (Option A, classification-gated billing)
3c (independent — dashboard observability)
4  (independent — dashboard delta heuristic)
```

**Hard gate:** Tasks 1, 3, 3b, 5, 6 must all land before Sprint 1 S1.10 verification rollup can honestly green. Tasks 2, 3c, and 4 are separately blocking on their own merits but do not block S1.10 directly.

**Sprint 1 S1.10 progress as of 2026-04-27:** Tasks 1, 2, 3, 3b done end-to-end. Code-side prerequisites for re-recording are complete. Task 5 (re-record fixtures against the corrected pipeline) and Task 6 (canonical-behaviour regression scenario) are next.

**Why 3b matters:** Task 1 fixes the prompt-structure swap. But after Task 1 lands, the KYC gate becomes the gate. Without Task 3b populating universal KYC fields in every advice seed, every scenario would (correctly) hit `<kyc_status>BLOCKED` and the model would (correctly) refuse analysis. Task 1 alone moved the failure from "EmptyDataGuard forbids tools" to "KYC blocks tools" — same outward symptom, different layer. 3b made the seeds match what a real onboarded user looks like for the universal requirements; verified end-to-end that KYC now returns PASSED for the seeded eval user.
