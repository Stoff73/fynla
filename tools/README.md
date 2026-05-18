# Fyn AI — Full Tool Audit

> Generated 2026-05-01 from branch `fix/persona-split-review-fixes`.
> Source files audited:
> - `app/Services/AI/AiToolDefinitions.php` (Anthropic-format schemas)
> - `app/Services/AI/XaiToolDefinitions.php` (xAI/OpenAI strict-mode schemas)
> - `app/Services/AI/AdviceFyn.php` (advice-mode tool stripping)
> - `app/Agents/CoordinatingAgent.php` (`executeTool` dispatch + every handler)
> - `app/Services/Onboarding/OnboardingChatDirector.php` (handoff path)

This is the complete catalogue of tools the Fyn AI ever runs. Every tool listed here corresponds to:
1. A schema definition (the JSON Schema the LLM sees).
2. A handler method in `CoordinatingAgent` (the PHP that actually runs).

## Two-Fyn architecture (canonical contract)

The Fyn AI is split into two states behind one chat surface. The user never sees the switch.

| State | Source | Mode | Tools available |
|---|---|---|---|
| **Onboarding Fyn** | `app/Services/Onboarding/OnboardingChatDirector.php` | The only state that **enters or edits** information | All capture/create/update/delete tools + the `delegate_to_capture` / `capture_complete` handoff stubs. Onboarding extraction tools (`capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `capture_work_details`) are surfaced only via `toolsListOverride` during grouped-extract turns. |
| **Advice Fyn** | `app/Services/AI/AdviceFyn.php` | **Read-only.** Answers questions using engines/modules. | Read tools only. **Every** persistent record-creation tool is in `AdviceFyn::WRITE_TOOLS` and stripped from the catalogue. Even `navigate_to_page` is stripped (BS-14: the LLM was using navigation as an escape hatch for write intents). |

**Write intents in advice mode** flow: LLM emits `delegate_to_capture` → `AdviceFyn::wrapStream` → `OnboardingChatDirector::handleInlineCapture` → the same direct-write handlers in `CoordinatingAgent`. The synthetic `handoff` SSE event is consumed internally and never reaches the frontend (INV-2.4.1).

## Tool registration (where the tools come from)

`AiToolDefinitions::getTools(bool $isPreviewMode)` in `app/Services/AI/AiToolDefinitions.php:12` returns:

```php
$tools = [
    ...$this->navigationTools(),     // navigate_to_page
    ...$this->analysisTools(),       // list_records, list_goals, list_life_events, get_module_analysis, get_recommendations, search_conversation_index
    ...$this->taxTools(),            // get_tax_information
    ...$this->planGenerationTools(), // generate_financial_plan
    ...$this->billingTools(),        // get_subscription_status, list_invoices, get_current_plan
];

if (! $isPreviewMode) {
    $tools = array_merge(
        $tools,
        $this->whatIfTools(),               // create_what_if_scenario
        $this->dataCreationTools(),         // creates: goal, life_event, savings, investment, holding, pension, property, mortgage, protection, asset, liability, gift, will, update_will, lpa, update_lpa
        $this->additionalCreationTools(),   // creates: family_member, trust, business_interest, chattel
        $this->dataModificationTools(),     // update_record, delete_record
        $this->profileTools(),              // update_profile
        $this->expenditureTools(),          // set_expenditure
        $this->campaignSaveTaxTools(),      // capture_salary_sacrifice, capture_spouse_*, capture_pension_history, capture_charitable_giving
    );
}
```

**Provider format switch** (last block of `getTools`): if `Cache::get('ai_provider')` is `xai`, returns the tool list raw (already in OpenAI function-calling shape). Otherwise converts `parameters` → `input_schema` for the Anthropic Messages API.

**Handoff tools** (`delegate_to_capture`, `capture_complete`) are emitted only by `AiToolDefinitions::handoffTools()` and surfaced via `toolsListOverride` from `OnboardingChatDirector::handleInlineCapture`. They never appear in the normal `getTools()` list.

**Onboarding extraction tools** (`capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `capture_work_details`) are emitted only by `AiToolDefinitions::onboardingExtractionTools()` and surfaced via `toolsListOverride` from `OnboardingChatDirector` during grouped-extract turns.

## Advice-mode write-tool stripping

`AdviceFyn::WRITE_TOOLS` (defined at `app/Services/AI/AdviceFyn.php:151-175`) lists every tool stripped from the advice-mode catalogue:

```php
private const WRITE_TOOLS = [
    'create_savings_account', 'create_investment_account', 'create_holding',
    'create_pension', 'create_property', 'create_mortgage',
    'create_protection_policy', 'create_asset', 'create_liability',
    'create_estate_gift', 'create_chattel', 'create_business_interest',
    'create_trust', 'create_family_member', 'create_will', 'update_will',
    'create_power_of_attorney', 'update_power_of_attorney',
    'update_record', 'delete_record', 'update_profile', 'set_expenditure',
    'capture_personal_details', 'capture_spouse_details',
    'capture_dependants', 'capture_work_details',
    'create_goal', 'create_life_event', 'create_what_if_scenario',
    'navigate_to_page',
];
```

Stripping happens in `AdviceFyn` (line ~534): `array_values(array_diff($names, self::WRITE_TOOLS))`.

## Tool dispatch (`CoordinatingAgent::executeTool`)

`app/Agents/CoordinatingAgent.php:770-941` is the single dispatch point. Behaviour:

1. Normalise input — convert string `"null"` → `null`, decode HTML entities (xAI sometimes encodes `&` as `&amp;`).
2. Compute `isPreviewUser` — `$user->is_preview_user && ! EvalBypassGate::isActive($user)`.
3. **Audit chain append** (`appendAuditEvent`) — forensic chain row, status `dispatched` (S0.12).
4. Emit eval `AgentDecision` event with name + args.
5. **Prerequisite gate check** (`PrerequisiteGateService::canExecuteTool`) — blocks if required data is missing; returns `['blocked' => true, 'reason' => …, 'missing_data' => …, 'suggested_action' => …, 'instruction' => …]`.
6. `match($toolName)` → handler method.
7. Audit chain completion row.
8. Catch `ValidationException`, `QueryException`, generic `Exception` → return `['error' => true, 'error_type' => …, 'message' => …]`.

## Master tool list

| # | Tool name | Mode | Preview-blocked? | Schema source | Handler in `CoordinatingAgent.php` |
|---|---|---|---|---|---|
| 1 | `navigate_to_page` | Onboarding only (advice strips) | No | `navigationTools()` | `handleNavigation` :1062 |
| 2 | `list_records` | Both | No | `analysisTools()` | `handleListRecords` :1398 |
| 3 | `list_goals` | Both | No | `analysisTools()` | `handleListGoals` :1543 |
| 4 | `list_life_events` | Both | No | `analysisTools()` | `handleListLifeEvents` :1582 |
| 5 | `get_module_analysis` | Both | No | `analysisTools()` | `handleModuleAnalysis` :1620 |
| 6 | `get_recommendations` | Both | No | `analysisTools()` | `handleRecommendations` :1744 |
| 7 | `search_conversation_index` | Both | No | `analysisTools()` | `handleSearchConversationIndex` :1673 |
| 8 | `get_tax_information` | Both | No | `taxTools()` | `handleTaxInformation` :1847 |
| 9 | `generate_financial_plan` | Both | No | `planGenerationTools()` | `handleFinancialPlan` :1890 |
| 10 | `get_subscription_status` | Both | No (read-only) | `billingTools()` | `handleGetSubscriptionStatus` :1764 |
| 11 | `list_invoices` | Both | No | `billingTools()` | `handleListInvoices` :1797 |
| 12 | `get_current_plan` | Both | No | `billingTools()` | `handleGetCurrentPlan` :1818 |
| 13 | `create_what_if_scenario` | Onboarding only | Yes (no whatIfTools in preview) | `whatIfTools()` | `handleCreateWhatIfScenario` :1723 |
| 14 | `create_goal` | Onboarding only | Yes | `goalAndEventTools()` | `handleCreateGoal` :1920 |
| 15 | `create_life_event` | Onboarding only | Yes | `goalAndEventTools()` | `handleCreateLifeEvent` :1975 |
| 16 | `create_savings_account` | Onboarding only | Yes | `accountCreationTools()` | `handleCreateSavingsAccount` :2023 |
| 17 | `create_investment_account` | Onboarding only | Yes | `accountCreationTools()` | `handleCreateInvestmentAccount` :2108 |
| 18 | `create_holding` | Onboarding only | Yes | `accountCreationTools()` | `handleCreateHolding` :2243 |
| 19 | `create_pension` | Onboarding only | Yes | `accountCreationTools()` | `handleCreatePension` :2325 |
| 20 | `create_property` | Onboarding only | Yes | `propertyCreationTools()` | `handleCreateProperty` :2437 |
| 21 | `create_mortgage` | Onboarding only | Yes | `propertyCreationTools()` | `handleCreateMortgage` :2568 |
| 22 | `create_protection_policy` | Onboarding only | Yes | `protectionCreationTools()` | `handleCreateProtectionPolicy` :2659 |
| 23 | `create_asset` | Onboarding only | Yes | `estateCreationTools()` | `handleCreateEstateAsset` :2876 |
| 24 | `create_liability` | Onboarding only | Yes | `estateCreationTools()` | `handleCreateEstateLiability` :2923 |
| 25 | `create_estate_gift` | Onboarding only | Yes | `estateCreationTools()` | `handleCreateEstateGift` :2985 |
| 26 | `create_will` | Onboarding only | Yes | `estateCreationTools()` | `handleCreateWill` :3032 |
| 27 | `update_will` | Onboarding only | Yes | `estateCreationTools()` | `handleUpdateWill` :3077 |
| 28 | `create_power_of_attorney` | Onboarding only | Yes | `estateCreationTools()` | `handleCreatePowerOfAttorney` :3136 |
| 29 | `update_power_of_attorney` | Onboarding only | Yes | `estateCreationTools()` | `handleUpdatePowerOfAttorney` :3199 |
| 30 | `create_family_member` | Onboarding only | Yes | `additionalCreationTools()` | `handleCreateFamilyMember` :3539 |
| 31 | `create_trust` | Onboarding only | Yes | `additionalCreationTools()` | `handleCreateTrust` :3645 |
| 32 | `create_business_interest` | Onboarding only | Yes | `additionalCreationTools()` | `handleCreateBusinessInterest` :3721 |
| 33 | `create_chattel` | Onboarding only | Yes | `additionalCreationTools()` | `handleCreateChattel` :3781 |
| 34 | `update_record` | Onboarding only | Yes | `dataModificationTools()` | `handleUpdateRecord` :4092 |
| 35 | `delete_record` | Onboarding only | Yes | `dataModificationTools()` | `handleDeleteRecord` :4200 |
| 36 | `update_profile` | Onboarding only | Yes | `profileTools()` | `handleUpdateProfile` :4301 |
| 37 | `set_expenditure` | Onboarding only | Yes | `expenditureTools()` | `handleSetExpenditure` :3840 |
| 38 | `capture_salary_sacrifice` | Onboarding (campaign) | Yes | `campaignSaveTaxTools()` | `handleCaptureSalarySacrifice` :3905 |
| 39 | `capture_spouse_work_status` | Onboarding (campaign) | Yes | `campaignSaveTaxTools()` | `handleCaptureSpouseWorkStatus` :3939 |
| 40 | `capture_spouse_household_data` | Onboarding (campaign) | Yes | `campaignSaveTaxTools()` | `handleCaptureSpouseHouseholdData` :3966 |
| 41 | `capture_spouse_non_working_assets` | Onboarding (campaign) | Yes | `campaignSaveTaxTools()` | `handleCaptureSpouseNonWorkingAssets` :3995 |
| 42 | `capture_pension_history` | Onboarding (campaign) | Yes | `campaignSaveTaxTools()` | `handleCapturePensionHistory` :4022 |
| 43 | `capture_charitable_giving` | Onboarding (campaign) | Yes | `campaignSaveTaxTools()` | `handleCaptureCharitableGiving` :4063 |
| 44 | `capture_personal_details` | Onboarding grouped-extract only | n/a | `onboardingExtractionTools()` (override) | `handleCapturePersonalDetails` :1074 |
| 45 | `capture_spouse_details` | Onboarding grouped-extract only | n/a | `onboardingExtractionTools()` (override) | `handleCaptureSpouseDetails` :1183 |
| 46 | `capture_dependants` | Onboarding grouped-extract only | n/a | `onboardingExtractionTools()` (override) | `handleCaptureDependants` :1257 |
| 47 | `capture_work_details` | Onboarding grouped-extract only | n/a | `onboardingExtractionTools()` (override) | `handleCaptureWorkDetails` :1325 |
| 48 | `delegate_to_capture` | Advice-mode handoff stub | n/a | `handoffTools()` (override) | inline (no handler — returns `['action' => 'handoff', …]`) |
| 49 | `capture_complete` | Onboarding handoff stub | n/a | `handoffTools()` (override) | inline (no handler — returns `['action' => 'handoff', …]`) |

## Per-tool source files

Full schema + handler code for every tool, grouped by category:

- [`01-read-tools.md`](01-read-tools.md) — Read-only tools available in both modes (12 tools)
- [`02-creation-financial.md`](02-creation-financial.md) — Account/property/protection creates (8 tools)
- [`03-creation-estate.md`](03-creation-estate.md) — Estate creates: gift, will, LPA, asset, liability, trust, chattel, business, family member (10 tools)
- [`04-creation-goals-whatif.md`](04-creation-goals-whatif.md) — Goal, life event, what-if scenario (3 tools)
- [`05-mutation.md`](05-mutation.md) — `update_record`, `delete_record`, `update_profile`, `set_expenditure` (4 tools)
- [`06-onboarding-capture.md`](06-onboarding-capture.md) — Grouped-extract tools (4 tools)
- [`07-savetax-campaign.md`](07-savetax-campaign.md) — SaveTax campaign capture tools (6 tools)
- [`08-handoff.md`](08-handoff.md) — `delegate_to_capture`, `capture_complete` (2 stubs)
- [`09-shared-helpers.md`](09-shared-helpers.md) — Shared helper methods called by handlers: `previewBlocked`, `validateToolInput`, `checkForDuplicate`, `resolveModel`, `resolveFamilyNames`, `summariseToolAnalysis`, `resolvePropertyId`, `invalidateModuleCache`, `educationStatusForAge`, plus the `executeTool` dispatcher itself.

## Special routing notes

- **`update_profile` with `section: expenditure`** internally redirects to `handleSetExpenditure` (CoordinatingAgent.php:4310-4312) — the LLM can use either tool, the result is the same row write.
- **`update_will` when no will exists** falls through to `handleCreateWill` (CoordinatingAgent.php:3097-3099) — create-on-update semantics for the singleton will record.
- **In-turn idempotency** — `handleCreateProtectionPolicy` (life, CI, IP branches) checks for an existing row matching `policy_type` + `sum_assured` + `provider` created within the last 60 seconds and returns `['duplicate' => true, …]` instead of creating a second row. This is BS-17 — grok-4-1-fast occasionally emits the same create twice in one multi-entity message.
- **`delete_record` two-phase confirmation** — first call returns `['requires_confirmation' => true, 'confirmation_token' => sha256(…)]`; second call must echo the exact token. Token is bound to `(user_id, entity_type, entity_id, today's date)` so it cannot be replayed across days.
- **`update_record` allowlist** — fields are validated against `App\Constants\UpdateRecordAllowlist::MAP[$entityType]`. `update_record_schema()` builds a `oneOf` JSON Schema branch per entity_type so the LLM cannot invent field names. The runtime handler re-checks the allowlist (defence-in-depth).
