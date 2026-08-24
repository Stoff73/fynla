# Mutation tools (4)

The four "destructive" tools: `update_record`, `delete_record`, `update_profile`, `set_expenditure`. **All are stripped from advice mode** (`AdviceFyn::WRITE_TOOLS`) and **all preview-block** via `previewBlocked()`.

> Source: `app/Services/AI/AiToolDefinitions.php` (schema) + `app/Agents/CoordinatingAgent.php` (handlers).

---

## 1. `update_record`

The schema is dynamically built as a `oneOf` per entity_type using `App\Constants\UpdateRecordAllowlist::MAP`. This means the LLM sees one branch per entity_type with **only** the fields that entity allows — invented field names are rejected at the JSON-schema layer. The runtime handler re-checks the allowlist (defence-in-depth).

**Schema construction** (`AiToolDefinitions::dataModificationTools()` — `AiToolDefinitions.php:1097-1101` + `AiToolDefinitions::updateRecordSchema()` — `AiToolDefinitions.php:1135-1162`):

```php
[
    'name' => 'update_record',
    'description' => 'Update an existing record. Use when the user wants to change details of an existing goal, account, property, pension, policy, or other financial record. Ask the user to confirm the changes before calling this tool. You MAY call this tool multiple times in the same turn when the user retracts or amends multiple records in one message. The schema restricts which fields are editable per entity_type — invented field names will be rejected.',
    'parameters' => $this->updateRecordSchema(),
],
```

```php
/**
 * Build the `update_record` parameter schema as a oneOf of per-entity
 * branches, sourcing allowed fields from {@see UpdateRecordAllowlist}.
 *
 * Each branch pins `entity_type` to a const and restricts `fields` to
 * the per-entity allowlist with `additionalProperties: false`. The
 * runtime handler still re-checks the allowlist (defence-in-depth)
 * because the LLM occasionally ignores schema constraints.
 */
private function updateRecordSchema(): array
{
    $oneOf = [];
    foreach (\App\Constants\UpdateRecordAllowlist::MAP as $entityType => $allowedFields) {
        $properties = [];
        foreach ($allowedFields as $field) {
            $properties[$field] = ['type' => ['string', 'number', 'boolean', 'null']];
        }

        $oneOf[] = [
            'type' => 'object',
            'properties' => [
                'entity_type' => ['const' => $entityType],
                'entity_id' => ['type' => 'integer', 'description' => 'The ID of the record to update.'],
                'fields' => [
                    'type' => 'object',
                    'description' => 'Key-value pairs to update. Only the fields listed for this entity_type are accepted.',
                    'properties' => $properties,
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['entity_type', 'entity_id', 'fields'],
            'additionalProperties' => false,
        ];
    }

    return ['oneOf' => $oneOf];
}
```

**Handler** (`CoordinatingAgent.php:4092-4175`):

```php
private function handleUpdateRecord(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('record');
    }

    $entityType = (string) ($input['entity_type'] ?? '');
    $entityId = (int) ($input['entity_id'] ?? 0);
    $fields = $input['fields'] ?? [];

    if (empty($fields)) {
        return ['error' => 'validation_failed', 'message' => 'No fields provided to update.'];
    }

    // Map AI tool field names to actual model field names. Aliasing happens
    // BEFORE the allowlist check so the LLM may use either the schema
    // name or any legacy alias and still pass.
    $fieldAliases = match ($entityType) {
        'business_interest' => ['estimated_value' => 'current_valuation', 'value' => 'current_valuation'],
        'chattel' => ['estimated_value' => 'current_value', 'category' => 'chattel_type', 'value' => 'current_value', 'chattel_name' => 'name'],
        'dc_pension' => ['current_value' => 'current_fund_value', 'pot_value' => 'current_fund_value', 'monthly_contribution' => 'monthly_contribution_amount', 'employer_contribution' => 'employer_contribution_percent'],
        'mortgage' => ['current_balance' => 'outstanding_balance', 'term_years' => 'remaining_term_months'],
        'life_insurance' => ['life_policy_type' => 'policy_type', 'monthly_premium' => 'premium_amount', 'end_date' => 'policy_end_date'],
        'critical_illness' => ['monthly_premium' => 'premium_amount'],
        'income_protection' => ['monthly_premium' => 'premium_amount', 'monthly_benefit' => 'benefit_amount', 'deferred_period' => 'deferred_period_weeks'],
        'savings_account' => ['balance' => 'current_balance', 'provider' => 'institution'],
        'investment_account' => ['total_value' => 'current_value'],
        'db_pension' => ['annual_pension_amount' => 'accrued_annual_pension'],
        'estate_asset' => ['value' => 'current_value'],
        'estate_liability' => ['outstanding_amount' => 'current_balance'],
        'estate_gift' => ['value' => 'gift_value', 'gift_description' => 'gift_type', 'recipient_name' => 'recipient'],
        'trust' => ['value' => 'current_value'],
        'family_member' => ['surname' => 'last_name'],
        default => [],
    };
    foreach ($fieldAliases as $aiName => $dbName) {
        if (array_key_exists($aiName, $fields) && ! array_key_exists($dbName, $fields)) {
            $fields[$dbName] = $fields[$aiName];
            unset($fields[$aiName]);
        }
    }

    // Per-entity allowlist enforcement (INV-2.7.3). Replaces the prior
    // `unset($safeFields['user_id'], $safeFields['id'])` blocklist with
    // a positive list — every other column (identity FKs, audit
    // timestamps, ownership-changing fields like Trust.settlor or
    // FamilyMember.relationship) is rejected explicitly.
    $allowed = \App\Constants\UpdateRecordAllowlist::allowedFields($entityType);
    if (empty($allowed)) {
        return [
            'error' => 'unsupported_entity_type',
            'entity_type' => $entityType,
            'message' => "The entity type '{$entityType}' cannot be updated via this tool.",
        ];
    }

    $disallowed = array_diff(array_keys($fields), $allowed);
    if (! empty($disallowed)) {
        return [
            'error' => 'fields_not_allowed',
            'entity_type' => $entityType,
            'disallowed_fields' => array_values($disallowed),
            'allowed_fields' => $allowed,
        ];
    }

    $model = $this->resolveModel($entityType, $entityId, $user->id);
    if (is_array($model) && isset($model['error'])) {
        return $model;
    }

    return DB::transaction(function () use ($model, $fields, $entityType) {
        $model->fill($fields);
        $model->save();

        return [
            'success' => true,
            'entity_type' => $entityType,
            'entity_id' => $model->id,
            'fields_updated' => array_keys($fields),
            'message' => 'Updated '.str_replace('_', ' ', $entityType).' successfully.',
        ];
    });
}
```

The handler depends on `resolveModel` (full source in `09-shared-helpers.md`).

---

## 2. `delete_record`

**Two-phase confirmation (Rubric-A D5 Level 3):** the first call returns a deterministic SHA-256 token bound to `(user_id, entity_type, entity_id, today's date)`. The LLM must echo the exact token on the second call to actually delete. The same-day salt means tokens cannot be replayed across days.

**Schema** (`AiToolDefinitions.php:1102-1122`):

```php
[
    'name' => 'delete_record',
    'description' => 'Delete an existing record. Two-phase confirmation: the first call returns a confirmation_token and a preview_message — DO NOT delete on that turn; show the user the preview_message and ask them to confirm. Only on a second call, with the exact same confirmation_token echoed back, does the deletion proceed. Tokens are bound to (user, entity_type, entity_id, today\'s date) and cannot be replayed across days.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'entity_type' => [
                'type' => 'string',
                'enum' => ['goal', 'life_event', 'savings_account', 'investment_account', 'dc_pension', 'db_pension', 'property', 'mortgage', 'life_insurance', 'critical_illness', 'income_protection', 'estate_asset', 'estate_liability', 'estate_gift', 'family_member', 'trust', 'business_interest', 'chattel'],
                'description' => 'The type of record to delete',
            ],
            'entity_id' => ['type' => 'integer', 'description' => 'The ID of the record to delete'],
            'confirmation_token' => [
                'type' => 'string',
                'description' => 'Optional. Omit on the first call (you will receive a token). On the second call, pass the exact 64-character token from the first response. Without a matching token the call returns requires_confirmation again and does NOT delete.',
            ],
        ],
        'required' => ['entity_type', 'entity_id'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:4200-4257`):

```php
private function handleDeleteRecord(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('record');
    }

    $entityType = (string) ($input['entity_type'] ?? '');
    $entityId = (int) ($input['entity_id'] ?? 0);

    // Two-phase confirmation (Rubric-A D5 Level 3): the first call
    // never deletes — it returns a deterministic SHA-256 token bound
    // to (user_id, entity_type, entity_id, today's date). The LLM
    // must echo that exact token on the second call to proceed.
    // The same-day salt means tokens cannot be replayed across days.
    $expectedToken = hash(
        'sha256',
        $user->id.'|'.$entityType.'|'.$entityId.'|'.now()->format('Y-m-d')
    );

    $providedToken = (string) ($input['confirmation_token'] ?? '');

    if (! hash_equals($expectedToken, $providedToken)) {
        return [
            'requires_confirmation' => true,
            'confirmation_token' => $expectedToken,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'preview_message' => 'This will permanently delete '
                .str_replace('_', ' ', $entityType)." #{$entityId}. "
                .'Confirm with the user before re-calling delete_record with this confirmation_token.',
        ];
    }

    $model = $this->resolveModel($entityType, $entityId, $user->id);
    if (is_array($model) && isset($model['error'])) {
        return $model;
    }

    $name = $model->goal_name
        ?? $model->account_name
        ?? $model->trust_name
        ?? $model->business_name
        ?? $model->description
        ?? $model->first_name
        ?? "#{$entityId}";

    return DB::transaction(function () use ($model, $entityType, $entityId, $name) {
        $model->delete();

        return [
            'success' => true,
            'deleted' => true,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'message' => ucfirst(str_replace('_', ' ', $entityType))." \"{$name}\" deleted.",
        ];
    });
}
```

---

## 3. `update_profile`

If `section === 'expenditure'`, redirects internally to `handleSetExpenditure` (so the same write path runs regardless of which tool the LLM picked).

**Schema** (`AiToolDefinitions::profileTools()` — `AiToolDefinitions.php:1614-1635`):

```php
[
    'name' => 'update_profile',
    'description' => 'Update the user\'s profile information (personal details, income, expenditure, or domicile). Use when the user provides personal information like their age, income, spending, marital status, or address. Ask clarifying questions if needed to gather required fields.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'section' => [
                'type' => 'string',
                'enum' => ['personal', 'income_occupation', 'expenditure', 'domicile'],
                'description' => 'Which profile section to update. personal: name, DOB, gender, marital status, address, phone. income_occupation: employment status, income, employer. expenditure: monthly spending. domicile: country of birth, UK arrival date.',
            ],
            'fields' => [
                'type' => 'object',
                'description' => 'Key-value pairs of fields to update. For personal: first_name, surname, date_of_birth (YYYY-MM-DD), gender (male/female/other), marital_status (single/married/divorced/widowed), phone, address_line_1, city, postcode. For income_occupation: employment_status (employed/full_time/part_time/self_employed/retired/unemployed/other), occupation, employer, annual_employment_income, annual_self_employment_income. For expenditure: monthly_expenditure, annual_expenditure. For domicile: country_of_birth, uk_arrival_date.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['section', 'fields'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:4301-4340`):

```php
private function handleUpdateProfile(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('profile');
    }

    $section = $input['section'];

    // Redirect expenditure to set_expenditure tool
    if ($section === 'expenditure') {
        return $this->handleSetExpenditure($input['fields'] ?? $input, $user, $isPreview);
    }
    $fields = $input['fields'] ?? [];

    if (empty($fields)) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No fields provided to update.'];
    }

    $allowedFields = match ($section) {
        // NI number excluded — sensitive PII should not be AI-writable
        'personal' => ['first_name', 'surname', 'date_of_birth', 'gender', 'marital_status', 'phone', 'address_line_1', 'address_line_2', 'city', 'county', 'postcode'],
        'income_occupation' => ['employment_status', 'occupation', 'employer', 'industry', 'annual_employment_income', 'annual_self_employment_income', 'annual_rental_income', 'annual_dividend_income', 'annual_other_income', 'target_retirement_age'],
        'expenditure' => ['monthly_expenditure', 'annual_expenditure', 'expenditure_entry_mode'],
        'domicile' => ['country_of_birth', 'uk_arrival_date', 'domicile_status'],
        default => [],
    };

    if (empty($allowedFields)) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => "Unknown profile section: {$section}"];
    }

    $safeFields = array_intersect_key($fields, array_flip($allowedFields));
    if (empty($safeFields)) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'None of the provided fields are valid for this profile section.'];
    }

    $user->update($safeFields);

    return ['updated' => true, 'section' => $section, 'fields_updated' => array_keys($safeFields), 'message' => 'Profile ('.str_replace('_', ' ', $section).') updated successfully.'];
}
```

---

## 4. `set_expenditure`

Single-call expenditure capture across 22 categories. **Note:** the handler also mirrors the monthly total into `ExpenditureProfile` (FR-M12) so the dashboard widget reflects the change immediately — `users.monthly_expenditure` alone is not enough.

**Schema** (`AiToolDefinitions::expenditureTools()` — `AiToolDefinitions.php:1533-1565`):

```php
[
    'name' => 'set_expenditure',
    'description' => 'Set the user\'s monthly expenditure by category. Call this IMMEDIATELY when the user mentions their spending, bills, or monthly outgoings. Fill in every category the user mentions and omit anything not mentioned. The form will be opened, filled, and saved. This tool captures all categories in a SINGLE call — do NOT call it multiple times per turn.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'rent' => ['type' => 'number', 'description' => 'Monthly rent in pounds.'],
            'utilities' => ['type' => 'number', 'description' => 'Monthly utilities (gas, electricity, water) in pounds.'],
            'food_groceries' => ['type' => 'number', 'description' => 'Monthly food and groceries in pounds.'],
            'transport_fuel' => ['type' => 'number', 'description' => 'Monthly transport/fuel costs in pounds.'],
            'healthcare_medical' => ['type' => 'number', 'description' => 'Monthly healthcare costs in pounds.'],
            'insurance' => ['type' => 'number', 'description' => 'Monthly non-property insurance in pounds.'],
            'mobile_phones' => ['type' => 'number', 'description' => 'Monthly mobile phone costs in pounds.'],
            'internet_tv' => ['type' => 'number', 'description' => 'Monthly broadband/TV costs in pounds.'],
            'subscriptions' => ['type' => 'number', 'description' => 'Monthly subscriptions in pounds.'],
            'clothing_personal_care' => ['type' => 'number', 'description' => 'Monthly clothing and personal care in pounds.'],
            'entertainment_dining' => ['type' => 'number', 'description' => 'Monthly entertainment and dining in pounds.'],
            'holidays_travel' => ['type' => 'number', 'description' => 'Monthly holidays/travel in pounds.'],
            'pets' => ['type' => 'number', 'description' => 'Monthly pet costs in pounds.'],
            'childcare' => ['type' => 'number', 'description' => 'Monthly childcare costs in pounds.'],
            'school_fees' => ['type' => 'number', 'description' => 'Monthly school fees in pounds.'],
            'school_lunches' => ['type' => 'number', 'description' => 'Monthly school lunches in pounds.'],
            'school_extras' => ['type' => 'number', 'description' => 'Monthly school extras in pounds.'],
            'university_fees' => ['type' => 'number', 'description' => 'Monthly university costs in pounds.'],
            'children_activities' => ['type' => 'number', 'description' => 'Monthly children activities in pounds.'],
            'gifts_charity' => ['type' => 'number', 'description' => 'Monthly gifts in pounds.'],
            'charitable_donations' => ['type' => 'number', 'description' => 'Monthly charitable donations in pounds.'],
            'other_expenditure' => ['type' => 'number', 'description' => 'Other monthly expenses in pounds.'],
        ],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3840-3901`):

```php
private function handleSetExpenditure(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('expenditure');
    }

    // All expenditure category fields (monthly amounts)
    $categoryFields = [
        'rent', 'utilities', 'food_groceries', 'transport_fuel', 'healthcare_medical', 'insurance',
        'mobile_phones', 'internet_tv', 'subscriptions',
        'clothing_personal_care', 'entertainment_dining', 'holidays_travel', 'pets',
        'childcare', 'school_fees', 'school_lunches', 'school_extras', 'university_fees', 'children_activities',
        'gifts_charity', 'charitable_donations', 'other_expenditure',
    ];

    $updateData = [];
    $total = 0;
    foreach ($categoryFields as $field) {
        if (isset($input[$field]) && is_numeric($input[$field]) && $input[$field] > 0) {
            $updateData[$field] = (float) $input[$field];
            $total += (float) $input[$field];
        }
    }

    if (empty($updateData)) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No expenditure amounts provided.'];
    }

    // Save directly to user model (same as manual form save)
    $updateData['monthly_expenditure'] = $total;
    $updateData['annual_expenditure'] = $total * 12;
    $updateData['use_simple_entry'] = false;
    $user->update($updateData);

    // FR-M12 — mirror the monthly total into ExpenditureProfile so the
    // dashboard expenditure widget (which reads total_monthly_expenditure
    // off the profile, not users.monthly_expenditure) reflects the change
    // immediately. Without this, Fyn confirms "captured" but the
    // dashboard stays blank. Same pattern as the onboarding fix in
    // OnboardingChatDirector::persistCapture (commit 88018a5).
    ExpenditureProfile::updateOrCreate(
        ['user_id' => $user->id],
        ['total_monthly_expenditure' => $total],
    );

    $formatted = collect($updateData)
        ->except(['monthly_expenditure', 'annual_expenditure', 'use_simple_entry'])
        ->map(fn ($v, $k) => str_replace('_', ' ', ucfirst($k)).': £'.number_format($v, 2))
        ->values()
        ->implode(', ');

    return [
        'updated' => true,
        'action' => 'navigate',
        'route_path' => '/valuable-info?section=expenditure',
        'section' => 'expenditure',
        'fields_updated' => array_keys($updateData),
        'total_monthly' => $total,
        'total_annual' => $total * 12,
        'message' => "Expenditure updated: {$formatted}. Total: £".number_format($total, 2).'/month.',
    ];
}
```
