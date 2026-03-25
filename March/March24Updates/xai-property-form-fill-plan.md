# xAI Property Form Fill Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Optimise the AI form fill pipeline for xAI with strict function calling, enriched property schemas covering all 35+ fields, and contextual follow-up prompting.

**Architecture:** New `XaiToolDefinitions.php` class returns pre-wrapped OpenAI-format tools with `strict: true`. Provider routing in `HasAiChat.php` selects the right class. `CoordinatingAgent` passes through all property fields. `PropertyForm.vue` maps new fields in its fill watcher.

**Tech Stack:** Laravel 10 (PHP 8.2), Vue.js 3, OpenAI-compatible function calling (xAI Grok)

**Spec:** `docs/superpowers/specs/2026-03-24-xai-property-form-fill-design.md`

---

### Task 1: Create `XaiToolDefinitions.php` with Property Tools

**Files:**
- Create: `app/Services/AI/XaiToolDefinitions.php`
- Reference: `app/Services/AI/AiToolDefinitions.php` (for method structure — do NOT modify)

This is the largest task. The new class mirrors `AiToolDefinitions` method structure but returns tools pre-wrapped in OpenAI function format with `strict: true`.

- [ ] **Step 1: Create the class skeleton**

Create `app/Services/AI/XaiToolDefinitions.php` with this structure:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

class XaiToolDefinitions
{
    /**
     * Get all tool definitions in OpenAI function-calling format with strict mode.
     * Tools are pre-wrapped — no further wrapping needed in HasAiChat.
     */
    public function getTools(bool $isPreviewMode = false): array
    {
        $tools = [
            ...$this->navigationTools(),
            ...$this->analysisTools(),
            ...$this->taxTools(),
            ...$this->planGenerationTools(),
        ];

        if (! $isPreviewMode) {
            $tools = array_merge(
                $tools,
                $this->whatIfTools(),
                $this->dataCreationTools(),
                $this->additionalCreationTools(),
                $this->dataModificationTools(),
                $this->profileTools(),
            );
        }

        return $tools;
    }

    /**
     * Wrap a tool definition in OpenAI function-calling format with strict mode.
     */
    private function wrapTool(string $name, string $description, array $properties, array $required): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'strict' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Helper: nullable enum for strict mode.
     * OpenAI strict mode does NOT allow 'type' => ['string', 'null'] with 'enum'.
     * Must use 'anyOf' pattern instead.
     */
    private function nullableEnum(array $values, string $description): array
    {
        return [
            'anyOf' => [
                ['type' => 'string', 'enum' => $values],
                ['type' => 'null'],
            ],
            'description' => $description,
        ];
    }
}
```

- [ ] **Step 2: Copy non-property tools from `AiToolDefinitions`**

Copy all the method bodies from `AiToolDefinitions.php` for these methods, wrapping each tool through the `wrapTool()` helper:
- `navigationTools()` — copy as-is, wrap each tool
- `analysisTools()` — copy as-is, wrap each tool
- `taxTools()` — copy as-is, wrap each tool
- `planGenerationTools()` — copy as-is, wrap each tool
- `whatIfTools()` — copy as-is, wrap each tool
- `dataCreationTools()` — this contains `create_savings_account`, `create_investment_account`, `create_pension`. Copy as-is, wrap each tool
- `additionalCreationTools()` — this contains `create_property`, `create_mortgage`, and others. **Replace** `create_property` and `create_mortgage` with the enriched versions (Step 3). Copy the rest as-is.
- `dataModificationTools()` — copy as-is, wrap each tool
- `profileTools()` — copy as-is, wrap each tool

For copied tools, every property that was optional should become nullable with `'type' => ['string', 'null']` and be added to the `required` array. This ensures strict mode compliance across ALL tools, not just property.

**Important:** For any tool property that currently uses `'type' => 'object'` with nested `'properties'`, the nested object MUST also have `'additionalProperties' => false` and ALL its properties in its own `required` array.

- [ ] **Step 3: Write the enriched `create_property` tool**

Replace the existing `create_property` in `additionalCreationTools()` with this enriched version:

```php
$this->wrapTool(
    'create_property',
    'Create a property record and optionally a linked mortgage. '
    . 'IMPORTANT: Before calling this tool, gather key details from the user in conversation. '
    . 'Always confirm: property type (main home, second home, or rental), approximate value, and whether they own it alone or jointly. '
    . 'Context-appropriate follow-ups: '
    . '- If joint ownership: ask about the ownership split percentage. '
    . '- If they mention a mortgage: ask for the balance, lender, interest rate, and whether it is repayment or interest-only. '
    . '- If buy-to-let: ask about monthly rental income. '
    . '- If a flat or apartment: ask whether freehold or leasehold. '
    . 'Do not interrogate — if the user says "that\'s all" or gives a brief answer, proceed with what you have. '
    . 'Set null for any field the user has not mentioned.',
    [
        // ── Basic (truly required) ──
        'property_type' => [
            'type' => 'string',
            'enum' => ['main_residence', 'secondary_residence', 'buy_to_let'],
            'description' => 'Type of property. "main_residence" for their primary home, "secondary_residence" for holiday homes or second properties, "buy_to_let" for rental properties.',
        ],
        'current_value' => [
            'type' => 'number',
            'description' => 'Current estimated market value of the full property in pounds (e.g. 450000). Always the FULL value, not the user\'s share.',
        ],
        // ── Address ──
        'address_line_1' => [
            'type' => ['string', 'null'],
            'description' => 'Street address (e.g. "42 Oak Lane").',
        ],
        'address_line_2' => [
            'type' => ['string', 'null'],
            'description' => 'Second address line if needed.',
        ],
        'city' => [
            'type' => ['string', 'null'],
            'description' => 'City or town.',
        ],
        'county' => [
            'type' => ['string', 'null'],
            'description' => 'County.',
        ],
        'postcode' => [
            'type' => ['string', 'null'],
            'description' => 'UK postcode (e.g. "SW1A 1AA").',
        ],
        // ── Purchase ──
        'purchase_price' => [
            'type' => ['number', 'null'],
            'description' => 'Original purchase price in pounds. Null if unknown.',
        ],
        'purchase_date' => [
            'type' => ['string', 'null'],
            'description' => 'Purchase date in YYYY-MM-DD format. If the user only knows the year, use January 1st (e.g. "2015-01-01").',
        ],
        'valuation_date' => [
            'type' => ['string', 'null'],
            'description' => 'Date of most recent valuation in YYYY-MM-DD format. Null if current_value is an estimate.',
        ],
        // ── Ownership ──
        'ownership_type' => $this->nullableEnum(
            ['individual', 'joint', 'tenants_in_common', 'trust'],
            'How the property is owned. "individual" = sole owner. "joint" = joint tenancy (equal shares, passes to survivor). "tenants_in_common" = can have unequal shares, passes via will. "trust" = held in a trust. Default to "individual" if user doesn\'t specify.'
        ),
        'ownership_percentage' => [
            'type' => ['number', 'null'],
            'description' => 'The primary owner\'s percentage share (0-100). For individual = 100, for joint = typically 50, for tenants_in_common = whatever they specify. Null to use defaults.',
        ],
        'joint_owner_name' => [
            'type' => ['string', 'null'],
            'description' => 'Name of the joint owner or co-owner. Only needed if ownership is joint or tenants_in_common. If the user mentions their spouse/partner, use the spouse\'s name.',
        ],
        // ── Tenure ──
        'tenure_type' => $this->nullableEnum(
            ['freehold', 'leasehold'],
            'Freehold (owns the land) or leasehold (owns for a fixed term, common for flats). Null defaults to freehold.'
        ),
        'lease_remaining_years' => [
            'type' => ['integer', 'null'],
            'description' => 'Years remaining on the lease. Only relevant if leasehold.',
        ],
        'lease_expiry_date' => [
            'type' => ['string', 'null'],
            'description' => 'Lease expiry date in YYYY-MM-DD format. Only relevant if leasehold.',
        ],
        // ── Mortgage ──
        'has_mortgage' => [
            'type' => 'boolean',
            'description' => 'Whether the property has a mortgage. Set to true if the user mentions any mortgage, outstanding balance, or lender.',
        ],
        'mortgage_lender' => [
            'type' => ['string', 'null'],
            'description' => 'Mortgage lender name (e.g. "Halifax", "Nationwide", "Barclays").',
        ],
        'mortgage_outstanding_balance' => [
            'type' => ['number', 'null'],
            'description' => 'Outstanding mortgage balance in pounds (e.g. 275000). The full balance, not the user\'s share.',
        ],
        'mortgage_type' => $this->nullableEnum(
            ['repayment', 'interest_only', 'mixed'],
            '"repayment" = capital + interest (most common). "interest_only" = only pay interest, capital due at end. "mixed" = part repayment, part interest-only.'
        ),
        'mortgage_rate_type' => $this->nullableEnum(
            ['fixed', 'variable', 'tracker', 'discount', 'mixed'],
            'Interest rate type. "fixed" = locked rate. "variable" = lender\'s SVR. "tracker" = follows base rate. "discount" = discount off SVR. "mixed" = split fixed/variable.'
        ),
        'mortgage_interest_rate' => [
            'type' => ['number', 'null'],
            'description' => 'Current interest rate as a percentage (e.g. 4.2 for 4.2%).',
        ],
        'mortgage_monthly_payment' => [
            'type' => ['number', 'null'],
            'description' => 'Monthly mortgage payment in pounds.',
        ],
        'mortgage_start_date' => [
            'type' => ['string', 'null'],
            'description' => 'Mortgage start date in YYYY-MM-DD format.',
        ],
        'mortgage_maturity_date' => [
            'type' => ['string', 'null'],
            'description' => 'Mortgage end/maturity date in YYYY-MM-DD format.',
        ],
        // ── Monthly costs ──
        'monthly_council_tax' => [
            'type' => ['number', 'null'],
            'description' => 'Monthly council tax in pounds.',
        ],
        'monthly_gas' => [
            'type' => ['number', 'null'],
            'description' => 'Monthly gas bill in pounds.',
        ],
        'monthly_electricity' => [
            'type' => ['number', 'null'],
            'description' => 'Monthly electricity bill in pounds.',
        ],
        'monthly_water' => [
            'type' => ['number', 'null'],
            'description' => 'Monthly water bill in pounds.',
        ],
        'monthly_building_insurance' => [
            'type' => ['number', 'null'],
            'description' => 'Monthly building insurance in pounds.',
        ],
        'monthly_contents_insurance' => [
            'type' => ['number', 'null'],
            'description' => 'Monthly contents insurance in pounds.',
        ],
        'monthly_service_charge' => [
            'type' => ['number', 'null'],
            'description' => 'Monthly service charge in pounds. Common for leasehold flats.',
        ],
        'monthly_maintenance_reserve' => [
            'type' => ['number', 'null'],
            'description' => 'Monthly maintenance reserve/sinking fund in pounds.',
        ],
        'other_monthly_costs' => [
            'type' => ['number', 'null'],
            'description' => 'Any other monthly property costs in pounds.',
        ],
        // ── Buy-to-let rental ──
        'monthly_rental_income' => [
            'type' => ['number', 'null'],
            'description' => 'Monthly rental income in pounds. Only for buy_to_let properties.',
        ],
        'tenant_name' => [
            'type' => ['string', 'null'],
            'description' => 'Current tenant name. Only for buy_to_let properties.',
        ],
        'managing_agent_name' => [
            'type' => ['string', 'null'],
            'description' => 'Letting agent or managing agent name. Only for buy_to_let properties.',
        ],
    ],
    // ALL fields in required array — strict mode demands this.
    // Non-essential fields use nullable types so xAI can return null.
    [
        'property_type', 'current_value',
        'address_line_1', 'address_line_2', 'city', 'county', 'postcode',
        'purchase_price', 'purchase_date', 'valuation_date',
        'ownership_type', 'ownership_percentage', 'joint_owner_name',
        'tenure_type', 'lease_remaining_years', 'lease_expiry_date',
        'has_mortgage', 'mortgage_lender', 'mortgage_outstanding_balance',
        'mortgage_type', 'mortgage_rate_type', 'mortgage_interest_rate',
        'mortgage_monthly_payment', 'mortgage_start_date', 'mortgage_maturity_date',
        'monthly_council_tax', 'monthly_gas', 'monthly_electricity', 'monthly_water',
        'monthly_building_insurance', 'monthly_contents_insurance',
        'monthly_service_charge', 'monthly_maintenance_reserve', 'other_monthly_costs',
        'monthly_rental_income', 'tenant_name', 'managing_agent_name',
    ]
),
```

- [ ] **Step 4: Write the enriched `create_mortgage` tool**

Also in `additionalCreationTools()`, replace `create_mortgage` with an enriched version. Keep the same fields as the current `AiToolDefinitions` version but:
- Add `strict: true` via `wrapTool()`
- Make optional fields nullable with `['type', 'null']`
- Add all fields to `required` array
- Add `'discount'` and `'mixed'` to the `rate_type` enum (matching the form)
- Add `mortgage_start_date` and `mortgage_maturity_date` fields
- Add contextual gathering instruction in the description

```php
$this->wrapTool(
    'create_mortgage',
    'Add a mortgage to an existing property. Use when the user mentions a mortgage separately from a property. '
    . 'Before calling, confirm: which property (address or description), outstanding balance, and lender. '
    . 'Ask about rate type (fixed/variable) and repayment type (repayment/interest-only) if not mentioned.',
    [
        'property_address_hint' => [
            'type' => ['string', 'null'],
            'description' => 'A hint to match the property — address, postcode, or description like "my main home". The system fuzzy-matches against existing properties.',
        ],
        'lender_name' => [
            'type' => ['string', 'null'],
            'description' => 'Mortgage lender name (e.g. "Halifax").',
        ],
        'outstanding_balance' => [
            'type' => 'number',
            'description' => 'Outstanding mortgage balance in pounds.',
        ],
        'interest_rate' => [
            'type' => ['number', 'null'],
            'description' => 'Current interest rate as a percentage (e.g. 4.2).',
        ],
        'mortgage_type' => $this->nullableEnum(
            ['repayment', 'interest_only', 'mixed'],
            'Repayment type. Default "repayment".'
        ),
        'rate_type' => $this->nullableEnum(
            ['fixed', 'variable', 'tracker', 'discount', 'mixed'],
            'Interest rate type. Default "fixed".'
        ),
        'monthly_payment' => [
            'type' => ['number', 'null'],
            'description' => 'Monthly payment amount in pounds.',
        ],
        'remaining_term_months' => [
            'type' => ['integer', 'null'],
            'description' => 'Remaining mortgage term in months.',
        ],
        'start_date' => [
            'type' => ['string', 'null'],
            'description' => 'Mortgage start date in YYYY-MM-DD format.',
        ],
        'maturity_date' => [
            'type' => ['string', 'null'],
            'description' => 'Mortgage end/maturity date in YYYY-MM-DD format.',
        ],
    ],
    [
        'property_address_hint', 'lender_name', 'outstanding_balance',
        'interest_rate', 'mortgage_type', 'rate_type', 'monthly_payment',
        'remaining_term_months', 'start_date', 'maturity_date',
    ]
),
```

- [ ] **Step 5: Verify PHP syntax**

Run: `php -l app/Services/AI/XaiToolDefinitions.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/XaiToolDefinitions.php
git commit -m "feat: add XaiToolDefinitions with strict mode and enriched property schema"
```

---

### Task 2: Route xAI to `XaiToolDefinitions` in `HasAiChat.php`

**Files:**
- Modify: `app/Traits/HasAiChat.php:76,96-106`

- [ ] **Step 1: Add the import**

At the top of `HasAiChat.php` (around line 12, after the existing AI imports), add:

```php
use App\Services\AI\XaiToolDefinitions;
```

- [ ] **Step 2: Replace the tool loading line**

At line 76, change:

```php
$tools = $this->toolDefinitions->getTools($user->is_preview_user);
```

To:

```php
$isXai = $this->getAiProvider() === 'xai';
$toolDefinitions = $isXai
    ? app(XaiToolDefinitions::class)
    : $this->toolDefinitions;
$tools = $toolDefinitions->getTools($user->is_preview_user);
```

- [ ] **Step 2b: Delete the duplicate `$isXai` declaration**

Line 93 (which is now ~4 lines further down due to the expanded block above) has:

```php
$isXai = $this->getAiProvider() === 'xai';
```

DELETE this line entirely — `$isXai` is now declared in the tool-loading block above and is in scope for the rest of the function.

- [ ] **Step 3: Remove the xAI tool wrapping block**

Lines 96-106 currently wrap tools for xAI. Since `XaiToolDefinitions` returns pre-wrapped tools, this block must be bypassed. Replace:

```php
// For xAI: prepend system prompt as first message and wrap tools in OpenAI format
$xaiTools = [];
if ($isXai && ! empty($tools)) {
    $xaiTools = array_map(fn (array $tool) => [
        'type' => 'function',
        'function' => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            'parameters' => $tool['parameters'] ?? $tool['input_schema'] ?? [],
        ],
    ], $tools);
}
```

With:

```php
// For xAI: XaiToolDefinitions returns pre-wrapped tools, use directly.
// For Anthropic: AiToolDefinitions returns Anthropic format, not used here.
$xaiTools = $isXai ? $tools : [];
```

- [ ] **Step 4: Verify the `$params['tools']` assignment is unchanged**

At line ~128-131, the existing code should still read:

```php
if (! empty($xaiTools)) {
    $params['tools'] = $xaiTools;
    $params['tool_choice'] = 'auto';
}
```

This remains correct — `$xaiTools` now contains pre-wrapped tools from `XaiToolDefinitions`.

- [ ] **Step 5: Verify PHP syntax**

Run: `php -l app/Traits/HasAiChat.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add app/Traits/HasAiChat.php
git commit -m "feat: route xAI to XaiToolDefinitions, remove double-wrapping"
```

---

### Task 3: Expand `handleCreateProperty` in `CoordinatingAgent.php`

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php:1217-1266`

- [ ] **Step 1: Expand validation rules**

Replace the validation block at lines 1223-1230 with expanded rules:

```php
$validationError = $this->validateToolInput($input, [
    'property_type' => ['required', Rule::in(['main_residence', 'secondary_residence', 'buy_to_let'])],
    'current_value' => 'required|numeric|min:0|max:999999999.99',
    'purchase_price' => 'nullable|numeric|min:0|max:999999999.99',
    'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
    'ownership_percentage' => 'nullable|numeric|min:0|max:100',
    'tenure_type' => ['nullable', Rule::in(['freehold', 'leasehold'])],
    'lease_remaining_years' => 'nullable|integer|min:0|max:999',
    'has_mortgage' => 'nullable|boolean',
    'mortgage_outstanding_balance' => 'nullable|numeric|min:0|max:999999999.99',
    'mortgage_interest_rate' => 'nullable|numeric|min:0|max:25',
    'mortgage_monthly_payment' => 'nullable|numeric|min:0|max:999999.99',
    'mortgage_type' => ['nullable', Rule::in(['repayment', 'interest_only', 'mixed'])],
    'mortgage_rate_type' => ['nullable', Rule::in(['fixed', 'variable', 'tracker', 'discount', 'mixed'])],
    'monthly_rental_income' => 'nullable|numeric|min:0|max:999999.99',
    'monthly_council_tax' => 'nullable|numeric|min:0|max:99999.99',
    'monthly_gas' => 'nullable|numeric|min:0|max:99999.99',
    'monthly_electricity' => 'nullable|numeric|min:0|max:99999.99',
    'monthly_water' => 'nullable|numeric|min:0|max:99999.99',
    'monthly_building_insurance' => 'nullable|numeric|min:0|max:99999.99',
    'monthly_contents_insurance' => 'nullable|numeric|min:0|max:99999.99',
    'monthly_service_charge' => 'nullable|numeric|min:0|max:99999.99',
    'monthly_maintenance_reserve' => 'nullable|numeric|min:0|max:99999.99',
    'other_monthly_costs' => 'nullable|numeric|min:0|max:99999.99',
]);
```

- [ ] **Step 2: Expand the fields array**

Replace the ENTIRE block from line 1235 (`$propertyType =`) through line 1265 (the closing of the `return` array) — i.e. everything after the validation check up to and including the return statement. The expanded version below includes the return:

```php
$propertyType = $input['property_type'] ?? 'main_residence';
$addressLabel = $input['address_line_1'] ?? ucfirst(str_replace('_', ' ', $propertyType));

// Build property form fields — pass through all provided data
$fields = [
    'property_type' => $propertyType,
    'current_value' => (float) $input['current_value'],
    // Address
    'address_line_1' => $input['address_line_1'] ?? null,
    'address_line_2' => $input['address_line_2'] ?? null,
    'city' => $input['city'] ?? null,
    'county' => $input['county'] ?? null,
    'postcode' => $input['postcode'] ?? null,
    // Purchase
    'purchase_price' => isset($input['purchase_price']) ? (float) $input['purchase_price'] : null,
    'purchase_date' => $input['purchase_date'] ?? null,
    'valuation_date' => $input['valuation_date'] ?? null,
    // Ownership
    'ownership_type' => $input['ownership_type'] ?? null,
    'ownership_percentage' => isset($input['ownership_percentage']) ? (float) $input['ownership_percentage'] : null,
    'joint_owner_name' => $input['joint_owner_name'] ?? null,
    // Tenure
    'tenure_type' => $input['tenure_type'] ?? null,
    'lease_remaining_years' => isset($input['lease_remaining_years']) ? (int) $input['lease_remaining_years'] : null,
    'lease_expiry_date' => $input['lease_expiry_date'] ?? null,
    // Monthly costs
    'monthly_council_tax' => isset($input['monthly_council_tax']) ? (float) $input['monthly_council_tax'] : null,
    'monthly_gas' => isset($input['monthly_gas']) ? (float) $input['monthly_gas'] : null,
    'monthly_electricity' => isset($input['monthly_electricity']) ? (float) $input['monthly_electricity'] : null,
    'monthly_water' => isset($input['monthly_water']) ? (float) $input['monthly_water'] : null,
    'monthly_building_insurance' => isset($input['monthly_building_insurance']) ? (float) $input['monthly_building_insurance'] : null,
    'monthly_contents_insurance' => isset($input['monthly_contents_insurance']) ? (float) $input['monthly_contents_insurance'] : null,
    'monthly_service_charge' => isset($input['monthly_service_charge']) ? (float) $input['monthly_service_charge'] : null,
    'monthly_maintenance_reserve' => isset($input['monthly_maintenance_reserve']) ? (float) $input['monthly_maintenance_reserve'] : null,
    'other_monthly_costs' => isset($input['other_monthly_costs']) ? (float) $input['other_monthly_costs'] : null,
    // BTL rental
    'monthly_rental_income' => isset($input['monthly_rental_income']) ? (float) $input['monthly_rental_income'] : null,
    'tenant_name' => $input['tenant_name'] ?? null,
    'managing_agent_name' => $input['managing_agent_name'] ?? null,
];

// Add mortgage fields if provided (has_mortgage flag OR outstanding balance)
if (! empty($input['has_mortgage']) || (! empty($input['mortgage_outstanding_balance']) && $input['mortgage_outstanding_balance'] > 0)) {
    $fields['has_mortgage'] = true;
    $fields['mortgage_outstanding_balance'] = isset($input['mortgage_outstanding_balance']) ? (float) $input['mortgage_outstanding_balance'] : null;
    $fields['mortgage_interest_rate'] = isset($input['mortgage_interest_rate']) ? (float) $input['mortgage_interest_rate'] : null;
    // IMPORTANT: AI param is 'mortgage_lender', form field is 'mortgage_lender_name'
    $fields['mortgage_lender_name'] = $input['mortgage_lender'] ?? null;
    $fields['mortgage_type'] = $input['mortgage_type'] ?? null;
    $fields['mortgage_rate_type'] = $input['mortgage_rate_type'] ?? null;
    $fields['mortgage_monthly_payment'] = isset($input['mortgage_monthly_payment']) ? (float) $input['mortgage_monthly_payment'] : null;
    $fields['mortgage_start_date'] = $input['mortgage_start_date'] ?? null;
    $fields['mortgage_maturity_date'] = $input['mortgage_maturity_date'] ?? null;
}

// Strip nulls and empty strings — only send fields with actual values
$fields = array_filter($fields, fn ($v) => $v !== null && $v !== '');

return [
    'action' => 'fill_form',
    'entity_type' => 'property',
    'route' => '/net-worth/property',
    'fields' => $fields,
    'message' => "I'll fill in the form for your property at \"{$addressLabel}\" now.",
];
```

- [ ] **Step 3: Expand `handleCreateMortgage` similarly**

At lines 1268-1305, expand `handleCreateMortgage` to pass through the new fields. Add to the validation:

```php
'start_date' => 'nullable|date',
'maturity_date' => 'nullable|date',
```

Add to the `$fields` array (after the existing fields at line ~1296):

```php
'mortgage_start_date' => $input['start_date'] ?? null,
'mortgage_maturity_date' => $input['maturity_date'] ?? null,
```

And add null stripping at the end:

```php
$fields = array_filter($fields, fn ($v) => $v !== null && $v !== '');
```

- [ ] **Step 4: Verify PHP syntax**

Run: `php -l app/Agents/CoordinatingAgent.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add app/Agents/CoordinatingAgent.php
git commit -m "feat: expand handleCreateProperty to pass through all property fields"
```

---

### Task 4: Expand PropertyForm.vue Fill Watcher

**Files:**
- Modify: `resources/js/components/NetWorth/Property/PropertyForm.vue:1679-1703`

- [ ] **Step 1: Add new mortgage field mappings to the `highlightedField` watcher**

The existing watcher at lines 1679-1703 already handles: `has_mortgage`, `mortgage_outstanding_balance`, `mortgage_interest_rate`, `mortgage_lender_name`, `mortgage_type`, `mortgage_rate_type`, `mortgage_monthly_payment`. These are already correct.

Add the two missing mortgage field mappings. Insert before the `} else if (fieldKey in this.form) {` catch-all (line 1698):

```javascript
} else if (fieldKey === 'mortgage_start_date') {
    this.mortgageForm.start_date = value;
} else if (fieldKey === 'mortgage_maturity_date') {
    this.mortgageForm.maturity_date = value;
```

The final watcher should look like:

```javascript
highlightedField(fieldKey) {
    if (fieldKey && this.pendingFill?.fields) {
        const value = this.pendingFill.fields[fieldKey];
        if (value !== undefined && value !== null) {
            if (fieldKey === 'has_mortgage') {
                this.hasMortgage = !!value;
            } else if (fieldKey === 'mortgage_outstanding_balance') {
                this.mortgageForm.outstanding_balance = value;
            } else if (fieldKey === 'mortgage_interest_rate') {
                this.mortgageForm.interest_rate = value;
            } else if (fieldKey === 'mortgage_lender_name') {
                this.mortgageForm.lender_name = value;
            } else if (fieldKey === 'mortgage_type') {
                this.mortgageForm.mortgage_type = value;
            } else if (fieldKey === 'mortgage_rate_type') {
                this.mortgageForm.rate_type = value;
            } else if (fieldKey === 'mortgage_monthly_payment') {
                this.mortgageForm.monthly_payment = value;
            } else if (fieldKey === 'mortgage_start_date') {
                this.mortgageForm.start_date = value;
            } else if (fieldKey === 'mortgage_maturity_date') {
                this.mortgageForm.maturity_date = value;
            } else if (fieldKey in this.form) {
                this.form[fieldKey] = value;
            }
        }
    }
},
```

Note: All the monthly cost fields (`monthly_council_tax`, `monthly_gas`, etc.), ownership fields (`ownership_type`, `ownership_percentage`, `joint_owner_name`), tenure fields (`tenure_type`, `lease_remaining_years`, `lease_expiry_date`), BTL fields, and address fields all exist in `this.form` and are handled by the catch-all `else if (fieldKey in this.form)` branch. No additional explicit mappings needed for those.

- [ ] **Step 2: Add `ai-fill-highlight` class bindings for ALL newly-fillable fields**

The following fields currently have NO `ai-fill-highlight` binding in the template. Add `:class="{ 'ai-fill-highlight': highlightedField === 'FIELD_NAME' }"` to each input/select element. Find each by searching for its `v-model` binding.

**Mortgage dates** (search for `v-model="mortgageForm.start_date"` and `v-model="mortgageForm.maturity_date"`):
- `mortgage_start_date`
- `mortgage_maturity_date`

**Ownership section** (search for `v-model="form.ownership_type"` etc.):
- `ownership_type` (radio buttons — add to the parent container or each radio label)
- `ownership_percentage`
- `joint_owner_name`

**Tenure section** (search for `v-model="form.tenure_type"` etc.):
- `tenure_type` (radio buttons)
- `lease_remaining_years`
- `lease_expiry_date`

**Monthly costs** (search for `v-model="form.monthly_*"`):
- `monthly_council_tax`
- `monthly_gas`
- `monthly_electricity`
- `monthly_water`
- `monthly_building_insurance`
- `monthly_contents_insurance`
- `monthly_service_charge`
- `monthly_maintenance_reserve`
- `other_monthly_costs`

**BTL / rental** (search for `v-model="form.tenant_name"` etc.):
- `tenant_name`
- `managing_agent_name`

**Address** (search for `v-model="form.address_line_2"` etc.):
- `address_line_2`
- `city`
- `county`
- `valuation_date`

For radio button groups (ownership_type, tenure_type), add the highlight class to the wrapping `<div>` that contains the radio options:
```html
<div :class="{ 'ai-fill-highlight': highlightedField === 'ownership_type' }">
  <!-- radio buttons -->
</div>
```

For standard inputs, add to the `<input>` element itself:
```html
<input v-model="form.monthly_council_tax" :class="{ 'ai-fill-highlight': highlightedField === 'monthly_council_tax' }" ... />
```

- [ ] **Step 3: Verify the `has_mortgage` sequencing**

Check that the existing `pendingFill` watcher at line 1663-1676 correctly handles `has_mortgage`. Current code (line 1667-1669):

```javascript
if (fill.fields.has_mortgage) {
    this.hasMortgage = true;
}
```

This runs BEFORE `beginFieldSequence`, which means the mortgage step renders before fields start filling. This is correct — no change needed. The `$nextTick` delay is already handled by the 250ms per-field animation timing in `aiFormFill.js`.

- [ ] **Step 4: Verify no syntax errors**

Start the dev server if not running and check for Vue compilation errors:

Run: `./dev.sh` (if not already running)
Check terminal output for compilation errors related to PropertyForm.vue.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/NetWorth/Property/PropertyForm.vue
git commit -m "feat: expand PropertyForm AI fill watcher with mortgage date fields"
```

---

### Task 5: Smoke Test — Verify Provider Routing

**Files:**
- Reference: `app/Traits/HasAiChat.php`

This task verifies the plumbing works before browser testing.

- [ ] **Step 1: Check route:list still works**

Run: `php artisan route:list | head -10`
Expected: No errors. Routes resolve correctly.

- [ ] **Step 2: Verify XaiToolDefinitions loads without error**

Run: `php artisan tinker --execute="echo count(app(\App\Services\AI\XaiToolDefinitions::class)->getTools()) . ' tools loaded';"`
Expected: A number (e.g. "25 tools loaded") — no exceptions.

- [ ] **Step 3: Verify tool format is correct**

Run: `php artisan tinker --execute="echo json_encode(app(\App\Services\AI\XaiToolDefinitions::class)->getTools()[0], JSON_PRETTY_PRINT);"`
Expected: Tool wrapped in `{"type": "function", "function": {"name": "...", "strict": true, "parameters": {...}}}` format.

- [ ] **Step 4: Verify create_property has strict mode and all fields**

Run: `php artisan tinker --execute="foreach(app(\App\Services\AI\XaiToolDefinitions::class)->getTools() as \$t) { if(\$t['function']['name']==='create_property') { echo 'strict: ' . (\$t['function']['strict'] ? 'true' : 'false') . PHP_EOL; echo 'fields: ' . count(\$t['function']['parameters']['properties']) . PHP_EOL; echo 'required: ' . count(\$t['function']['parameters']['required']) . PHP_EOL; echo 'additionalProperties: ' . (\$t['function']['parameters']['additionalProperties'] ? 'true' : 'false') . PHP_EOL; }}"`

Expected:
```
strict: true
fields: 35
required: 35
additionalProperties: false
```

- [ ] **Step 5: Verify Anthropic path unchanged**

Run: `php artisan tinker --execute="echo json_encode(app(\App\Services\AI\AiToolDefinitions::class)->getTools()[0], JSON_PRETTY_PRINT);"`
Expected: Anthropic format with `input_schema` key (not wrapped in `type: function`). No changes from before.

- [ ] **Step 6: Seed database**

Run: `php artisan db:seed`
Expected: All seeders pass.

---

### Task 6: Browser Test — xAI Property Form Fill

**Files:**
- No code changes — this is testing only

**Prerequisites:**
- xAI API key set in `.env`: `AI_PROVIDER=xai`, `XAI_API_KEY=<key>`
- Dev server running (`./dev.sh`)
- Database seeded

- [ ] **Step 1: Test scenario 1 — Main residence, individual, no mortgage**

Open browser. Login. Open Fyn chat.
Type: "I own a 3-bed house at 42 Oak Lane, Guildford worth about £350,000"

**Verify Fyn asks follow-ups** — should ask about ownership (sole/joint) at minimum.
Answer: "It's just mine, I bought it in 2018 for £280,000"

**Verify form fill:**
- Navigates to property page
- Form opens and fills: property_type=main_residence, address_line_1="42 Oak Lane", city="Guildford", current_value=350000, purchase_price=280000, purchase_date=2018-01-01, ownership_type=individual
- Form auto-submits
- Record appears in database

- [ ] **Step 2: Test scenario 2 — Joint ownership with mortgage**

Type: "My wife and I bought our house at 15 High Street for £400,000, it's worth £500,000 now. We have a £300,000 mortgage with Halifax at 4.2%"

**Verify Fyn asks follow-ups** — should ask about mortgage type (repayment/interest-only).
Answer: "Repayment, fixed rate, we pay £1,600 a month"

**Verify form fill:**
- ownership_type=joint, ownership_percentage=50
- has_mortgage=true, mortgage fields all populated
- Mortgage step visible and filled
- Auto-submits successfully

- [ ] **Step 3: Test scenario 3 — Tenants in common with interest-only mortgage**

Type: "I have a holiday cottage in Cornwall worth £280,000. I own 70% and my partner owns 30%. We have an interest-only mortgage of £150,000 with Nationwide"

**Verify:**
- ownership_type=tenants_in_common, ownership_percentage=70
- mortgage_type=interest_only
- No BTL fields asked about

- [ ] **Step 4: Test scenario 4 — Buy-to-let with tenant details**

Type: "We have a rental flat in Manchester worth £220,000 with a £160,000 mortgage. It's rented at £1,100 a month to John Smith"

**Verify:**
- property_type=buy_to_let
- Fyn asks about mortgage details (type, rate)
- BTL step appears with rental income and tenant name filled
- monthly_rental_income=1100, tenant_name="John Smith"

- [ ] **Step 5: Test scenario 5 — Leasehold with service charges**

Type: "I own a leasehold flat in London worth £180,000 with 85 years remaining on the lease. Service charge is £200 a month and council tax is £150 a month"

**Verify:**
- tenure_type=leasehold, lease_remaining_years=85
- monthly_service_charge=200, monthly_council_tax=150
- Fyn asks if there's a mortgage

- [ ] **Step 6: Test scenario 6 — Full details across all steps**

Type a comprehensive description covering every field group. Verify all 5 form steps are filled and auto-submit succeeds.

- [ ] **Step 7: Anthropic regression test**

Switch provider: set `AI_PROVIDER=anthropic` in `.env` (or toggle via admin panel).
Repeat scenario 1 (simple main residence).

**Verify:**
- Anthropic path still works with the old `AiToolDefinitions`
- No double-wrapping errors in Laravel log
- Form fill works as before

- [ ] **Step 8: Seed database**

Run: `php artisan db:seed`
Expected: All seeders pass.
