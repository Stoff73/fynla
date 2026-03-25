# xAI Property Form Fill Optimisation

**Date:** 24 March 2026
**Status:** Approved
**Branch:** grokAI

## Problem

The AI form fill system uses a single `AiToolDefinitions.php` for both Anthropic and xAI providers. The property tool only defines ~10 fields, but the actual PropertyForm accepts 40+ fields. Anthropic scores 36% on form fill tests while xAI scores significantly better — but both are limited by the sparse tool schema. There is no structured response enforcement, so the AI can return partial or malformed JSON.

## Goal

Optimise the AI form fill pipeline exclusively for xAI (Grok). Create a separate tool definitions class with strict function calling, enriched property schemas, and contextual follow-up prompting so Fyn gathers relevant information before filling forms.

## Design

### 1. New File: `app/Services/AI/XaiToolDefinitions.php`

A separate class that mirrors the method structure of `AiToolDefinitions` but is optimised for xAI:

- **Pre-wrapped tools** — Returns tools already in `{type: "function", function: {...}}` OpenAI format with `"strict": true`
- **All fields required + nullable** — Every field is in the `required` array. Optional fields use `type: ["string", "null"]` or `type: ["number", "null"]`. xAI always returns complete JSON with `null` for unknowns.
- **`additionalProperties: false`** at every object level — Prevents invented fields
- **Richer descriptions** with inline examples tailored to Grok's interpretation style

The existing `AiToolDefinitions.php` is NOT modified. No Anthropic regression risk.

### 2. Enriched `create_property` Tool Schema

All fields the PropertyForm accepts, grouped logically:

| Group | Fields | Types |
|-------|--------|-------|
| **Basic** (truly required) | `property_type` | enum: main_residence, secondary_residence, buy_to_let |
| | `current_value` | number |
| **Address** | `address_line_1`, `address_line_2`, `city`, `county`, `postcode` | string/null |
| **Purchase** | `purchase_price`, `purchase_date`, `valuation_date` | number/null, string(YYYY-MM-DD)/null |
| **Ownership** | `ownership_type` | enum/null: individual, joint, tenants_in_common, trust |
| | `ownership_percentage` | number/null (0-100) |
| | `joint_owner_name` | string/null |
| **Tenure** | `tenure_type` | enum/null: freehold, leasehold |
| | `lease_remaining_years` | integer/null |
| | `lease_expiry_date` | string(YYYY-MM-DD)/null |
| **Mortgage** | `has_mortgage` | boolean |
| | `mortgage_lender` (AI param name), `mortgage_outstanding_balance`, `mortgage_type`, `mortgage_rate_type`, `mortgage_interest_rate`, `mortgage_monthly_payment`, `mortgage_start_date`, `mortgage_maturity_date` | Various nullable types |

**Field name mapping note:** The AI tool parameter is `mortgage_lender` (what the user sees as "lender"). In `CoordinatingAgent::handleCreateProperty`, this maps to `$fields['mortgage_lender_name']` (what the form/backend expects). This existing remapping (`$input['mortgage_lender']` → `$fields['mortgage_lender_name']`) MUST be preserved.
| **Monthly costs** | `monthly_council_tax`, `monthly_gas`, `monthly_electricity`, `monthly_water`, `monthly_building_insurance`, `monthly_contents_insurance`, `monthly_service_charge`, `monthly_maintenance_reserve`, `other_monthly_costs` | number/null |
| **BTL rental** | `monthly_rental_income`, `tenant_name`, `managing_agent_name` | number/null, string/null |

Mortgage enums:
- `mortgage_type`: repayment, interest_only, mixed
- `mortgage_rate_type`: fixed, variable, tracker, discount, mixed

### 3. Contextual Follow-Up Prompting

The tool description instructs Grok to gather information conversationally before calling the tool:

> "Before calling this tool, gather the key details from the user. Always confirm: property type, approximate value, and ownership (sole or joint). Then ask context-appropriate follow-ups:
> - If joint ownership: ask about the ownership split percentage
> - If they mention a mortgage: ask for balance, lender, rate, and type (repayment/interest-only)
> - If buy-to-let: ask about monthly rental income
> - If a flat or apartment: ask whether freehold or leasehold
> - If the user provides running costs, include them. Don't interrogate — if the user says 'that's all' or gives a brief answer, proceed with what you have."

This is prompt-level guidance only — no code logic for question trees. The LLM handles conversational flow naturally.

### 4. Provider Routing in `HasAiChat.php`

```php
$definitions = $isXai
    ? app(XaiToolDefinitions::class)
    : app(AiToolDefinitions::class);
$tools = $definitions->getTools($isPreviewMode);
```

**Critical: Prevent double-wrapping.** The existing code at ~lines 97-105 of `HasAiChat.php` unconditionally wraps all tools in `{type: "function", function: {...}}` when `$isXai` is true. Since `XaiToolDefinitions` returns pre-wrapped tools, this wrapping block MUST be bypassed for xAI. Guard it:

```php
// Only wrap if using AiToolDefinitions (Anthropic class returns unwrapped tools)
if ($isXai && !($definitions instanceof XaiToolDefinitions)) {
    $tools = array_map(fn($tool) => ['type' => 'function', 'function' => $tool], $tools);
}
```

Or more simply: since xAI always uses `XaiToolDefinitions` which returns pre-wrapped tools, remove the wrapping block from the xAI path entirely and pass `$tools` directly to `$params['tools']`.

### 5. Backend: Expand `CoordinatingAgent::handleCreateProperty`

Current state: Cherry-picks 7 fields, hardcodes mortgage defaults (type → repayment, rate_type → fixed).

New state: Pass through all fields from enriched tool:
- Ownership: `ownership_type`, `ownership_percentage`, `joint_owner_name`
- Tenure: `tenure_type`, `lease_remaining_years`, `lease_expiry_date`
- Full mortgage: `mortgage_type`, `mortgage_rate_type`, `mortgage_monthly_payment`, `mortgage_start_date`, `mortgage_maturity_date` (from AI, not hardcoded)
- Monthly costs: all 9 fields
- BTL: `tenant_name`, `managing_agent_name`

Expanded validation rules for new fields. Strip nulls AND empty strings before sending to frontend: `array_filter($fields, fn($v) => $v !== null && $v !== '')`. This aligns with the frontend's `beginFieldSequence` which also filters out null and empty string values. Both sides agree on the contract: only non-empty, non-null values are passed through.

Same expansion for `handleCreateMortgage` (standalone mortgage case).

### 6. Frontend: Expand `PropertyForm.vue` Fill Watcher

New field mappings in `highlightedField` watcher:
- `tenure_type` → `form.tenure_type`
- `lease_remaining_years` → `form.lease_remaining_years`
- `lease_expiry_date` → `form.lease_expiry_date`
- `mortgage_start_date` → `mortgageForm.start_date`
- `mortgage_maturity_date` → `mortgageForm.maturity_date`
- `mortgage_rate_type` → `mortgageForm.rate_type`
- `mortgage_monthly_payment` → `mortgageForm.monthly_payment`
- All monthly cost fields → `form.*` (direct mapping)
- BTL fields → `form.*` (direct mapping)

Handle `has_mortgage` sequencing: when AI sends `has_mortgage: true`, tick the mortgage checkbox first (renders the mortgage step), then fill mortgage fields. Add a short delay or `$nextTick` to ensure the DOM renders before setting mortgage field values.

**Template bindings required:** New fields (`mortgage_start_date`, `mortgage_maturity_date`, and any other newly-mapped fields) need `:class="{ 'ai-fill-highlight': highlightedField === 'field_name' }"` bindings added to their template `<input>` elements. Without these, the fill animation won't fire even though the watcher correctly sets the value.

Null filtering: the fill sequence only includes fields with non-null values (already handled by existing `beginFieldSequence` logic filtering on provided fields).

### 7. Files Changed

| Action | File | Change |
|--------|------|--------|
| **Create** | `app/Services/AI/XaiToolDefinitions.php` | Full xAI-optimised tool definitions with strict mode |
| **Modify** | `app/Traits/HasAiChat.php` | Route xAI to XaiToolDefinitions, skip manual wrapping |
| **Modify** | `app/Agents/CoordinatingAgent.php` | Expand handleCreateProperty + handleCreateMortgage |
| **Modify** | `resources/js/components/NetWorth/Property/PropertyForm.vue` | Expand fill watcher with new field mappings + mortgage checkbox sequencing |
| **No change** | `app/Services/AI/AiToolDefinitions.php` | Anthropic untouched |
| **No change** | `resources/js/store/modules/aiFormFill.js` | Already field-agnostic |
| **No change** | `app/Http/Requests/StorePropertyRequest.php` | Already validates all fields |
| **No change** | `app/Http/Controllers/Api/PropertyController.php` | Already handles all fields |

### 8. Test Plan

Browser test each scenario with xAI provider:

1. **Main residence, individual, no mortgage** — "I own a 3-bed house at 42 Oak Lane worth about £350k"
2. **Main residence, joint, repayment mortgage** — "My wife and I bought our house for £400k, it's worth £500k now, we have a £300k mortgage with Halifax at 4.2%"
3. **Secondary residence, tenants in common (70/30), interest-only mortgage** — "I have a holiday cottage worth £280k, owned 70/30 with my partner, interest-only mortgage of £150k"
4. **Buy-to-let, joint, mixed mortgage + tenant** — "We have a rental flat worth £220k, £160k mortgage, rented at £1,100/month to John Smith"
5. **Leasehold flat with service charges** — "I own a leasehold flat worth £180k, 85 years remaining, service charge £200/month, council tax £150/month"
6. **Full details** — Every field populated, verify complete form fill across all 5 steps

7. **Anthropic regression** — Switch provider back to Anthropic (`AI_PROVIDER=anthropic`), repeat scenario 1. Verify no regression — form fills correctly, no double-wrapped tool errors in logs. Confirms the provider routing change doesn't break existing Anthropic path.

Each test verifies:
- Fyn asks relevant follow-up questions (not for irrelevant fields)
- Form fill animates all provided fields correctly
- Multi-step navigation works (mortgage step appears when needed)
- Auto-submit succeeds
- Record appears in database with correct values
