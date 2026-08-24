# Estate creation tools (10)

Estate-related write tools: assets, liabilities, gifts, wills, LPAs, trusts, chattels, family members.
**All are stripped from advice mode** (`AdviceFyn::WRITE_TOOLS`) and **all preview-block** via `previewBlocked()`.

> Source files:
> - Schemas (Anthropic): `app/Services/AI/AiToolDefinitions.php`
> - Schemas (xAI strict): `app/Services/AI/XaiToolDefinitions.php`
> - Handlers: `app/Agents/CoordinatingAgent.php`

---

## 1. `create_asset`

**Schema** (`AiToolDefinitions.php:816-846`):

```php
[
    'name' => 'create_asset',
    'description' => 'Create an asset. Use this for assets not covered by other tools — such as collectibles, artwork, or other valuable items the user wants to track. You MAY call this tool multiple times in the same turn when the user mentions multiple assets.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'asset_name' => ['type' => 'string', 'description' => 'Name or description of the asset'],
            'asset_type' => ['type' => 'string', 'enum' => ['property', 'pension', 'investment', 'business', 'other'], 'description' => 'Type of estate asset. Use "other" for cash, collectibles, and similar.'],
            'current_value' => ['type' => 'number', 'description' => 'Current estimated value in pounds'],
            'is_iht_exempt' => ['type' => 'boolean', 'description' => 'Whether the asset is exempt from Inheritance Tax (e.g., business property relief). Default false.'],
            'exemption_reason' => ['type' => 'string', 'description' => 'Reason for Inheritance Tax exemption, if applicable'],
        ],
        'required' => ['asset_name', 'asset_type', 'current_value'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:2876-2921`):

```php
private function handleCreateEstateAsset(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('estate asset');
    }

    $validationError = $this->validateToolInput($input, [
        'asset_name' => 'required|string|max:255',
        'asset_type' => ['required', Rule::in(['property', 'pension', 'investment', 'business', 'other'])],
        'current_value' => 'required|numeric|min:0|max:999999999.99',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $payload = [
        'user_id' => $user->id,
        'asset_name' => $input['asset_name'],
        'asset_type' => $input['asset_type'],
        'current_value' => (float) $input['current_value'],
        'ownership_type' => 'individual',
        'valuation_date' => now()->toDateString(),
        'is_iht_exempt' => (bool) ($input['is_iht_exempt'] ?? false),
    ];

    if (isset($input['exemption_reason']) && $input['exemption_reason'] !== '') {
        $payload['exemption_reason'] = $input['exemption_reason'];
    }
    if (isset($input['liquidity']) && $input['liquidity'] !== '') {
        $payload['liquidity'] = $input['liquidity'];
    }

    $asset = DB::transaction(fn () => Asset::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'estate_asset',
        'entity_id' => $asset->id,
        'name' => $asset->asset_name,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added \"{$asset->asset_name}\" to your estate.",
    ];
}
```

---

## 2. `create_liability`

**Schema** (`AiToolDefinitions.php:848-878`):

```php
[
    'name' => 'create_liability',
    'description' => 'Create a liability. Use this when the user mentions any debt: credit cards, personal loans, student loans, car finance, or any other outstanding balance owed. You MAY call this tool multiple times in the same turn when the user mentions multiple liabilities.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'liability_name' => ['type' => 'string', 'description' => 'Name or description of the liability'],
            'liability_type' => ['type' => 'string', 'enum' => ['loan', 'personal_loan', 'credit_card', 'mortgage', 'student_loan', 'other'], 'description' => 'Type of liability'],
            'current_balance' => ['type' => 'number', 'description' => 'Outstanding balance in pounds'],
            'monthly_payment' => ['type' => 'number', 'description' => 'Monthly payment amount in pounds'],
            'interest_rate' => ['type' => 'number', 'description' => 'Interest rate as a percentage'],
        ],
        'required' => ['liability_name', 'liability_type', 'current_balance'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:2923-2983`):

```php
private function handleCreateEstateLiability(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('liability');
    }

    $validationError = $this->validateToolInput($input, [
        'liability_name' => 'required|string|max:255',
        'liability_type' => ['required', Rule::in(['loan', 'personal_loan', 'credit_card', 'mortgage', 'student_loan', 'secured_loan', 'overdraft', 'hire_purchase', 'business_loan', 'other'])],
        'current_balance' => 'required|numeric|min:0|max:999999999.99',
        'monthly_payment' => 'nullable|numeric|min:0|max:999999.99',
        'interest_rate' => 'nullable|numeric|min:0|max:50',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $dbLiabilityType = match ($input['liability_type']) {
        'loan' => 'personal_loan',
        default => $input['liability_type'],
    };

    $payload = [
        'user_id' => $user->id,
        'liability_name' => $input['liability_name'],
        'liability_type' => $dbLiabilityType,
        'current_balance' => (float) $input['current_balance'],
        'ownership_type' => 'individual',
    ];

    if (isset($input['monthly_payment']) && is_numeric($input['monthly_payment'])) {
        $payload['monthly_payment'] = (float) $input['monthly_payment'];
    }
    if (isset($input['interest_rate']) && is_numeric($input['interest_rate'])) {
        $payload['interest_rate'] = (float) $input['interest_rate'];
    }
    foreach (['secured_against', 'mortgage_type', 'notes'] as $f) {
        if (isset($input[$f]) && $input[$f] !== '') {
            $payload[$f] = $input[$f];
        }
    }
    foreach (['maturity_date', 'fixed_until'] as $f) {
        if (isset($input[$f]) && $input[$f] !== '') {
            $payload[$f] = $input[$f];
        }
    }

    $liability = DB::transaction(fn () => Liability::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'estate_liability',
        'entity_id' => $liability->id,
        'name' => $liability->liability_name,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added your \"{$liability->liability_name}\" liability.",
    ];
}
```

---

## 3. `create_estate_gift`

Records a lifetime gift for IHT 7-year-rule tracking. Resolves recipient names through `resolveFamilyNames` (e.g. "my wife" → "Jane Smith").

**Schema** (`AiToolDefinitions.php:880-911`):

```php
[
    'name' => 'create_estate_gift',
    'description' => 'Record a gift for Inheritance Tax planning. Use this when the user mentions gifts they have made or plan to make, as these affect their Inheritance Tax position under the 7-year rule. You MAY call this tool multiple times in the same turn when the user mentions multiple gifts.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'gift_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Date the gift was or will be made, in YYYY-MM-DD format'],
            'recipient' => ['type' => 'string', 'description' => 'Name of the recipient'],
            'gift_type' => ['type' => 'string', 'enum' => ['pet', 'clt', 'exempt', 'small_gift', 'annual_exemption'], 'description' => 'Inheritance Tax classification. "pet" for Potentially Exempt Transfer (most common — gifts to individuals), "clt" for Chargeable Lifetime Transfer (gifts to trusts), "exempt" for exempt gifts (e.g., to spouse or charity), "small_gift" for small gifts up to £250 per recipient, "annual_exemption" for annual exemption gifts up to £3,000 per year. Default to "pet" for most gifts between individuals.'],
            'gift_value' => ['type' => 'number', 'description' => 'Value of the gift in pounds'],
            'notes' => ['type' => 'string', 'description' => 'Additional notes about the gift'],
        ],
        'required' => ['gift_date', 'recipient', 'gift_type', 'gift_value'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:2985-3028`):

```php
private function handleCreateEstateGift(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('estate gift');
    }

    $validationError = $this->validateToolInput($input, [
        'gift_date' => 'required|date',
        'recipient' => 'required|string|max:255',
        'gift_type' => ['required', Rule::in(['pet', 'clt', 'exempt', 'small_gift', 'annual_exemption'])],
        'gift_value' => 'required|numeric|min:0|max:999999999.99',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $recipient = $this->resolveFamilyNames($input['recipient'], $user) ?? $input['recipient'];

    $payload = [
        'user_id' => $user->id,
        'gift_date' => substr($input['gift_date'], 0, 10),
        'recipient' => $recipient,
        'gift_type' => $input['gift_type'] ?? 'pet',
        'gift_value' => (float) $input['gift_value'],
    ];

    if (isset($input['notes']) && $input['notes'] !== '') {
        $payload['notes'] = $input['notes'];
    }

    $gift = DB::transaction(fn () => Gift::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'estate_gift',
        'entity_id' => $gift->id,
        'name' => $recipient,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've recorded your gift of £".number_format((float) $input['gift_value'])." to {$recipient}.",
    ];
}
```

---

## 4. `create_will`

Singleton — one will per user. Uses `updateOrCreate` so re-running it overwrites the existing record. If you want to update an existing will, use `update_will` (which falls through to this if no row exists yet).

**Schema** (`AiToolDefinitions.php:913-942`):

```php
[
    'name' => 'create_will',
    'description' => 'Record the user\'s will details. Use when the user tells you they have a will and shares executor, beneficiaries, guardians, or specific gifts information. For existing wills only — the Will Builder UI remains the tool for drafting a new will from scratch.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'executor_name' => ['type' => 'string', 'description' => 'Full name of the primary executor.'],
            'residuary_beneficiary' => ['type' => 'string', 'description' => 'Named primary residuary beneficiary — who receives the bulk of the estate after specific gifts and debts.'],
            'guardian_for_minors' => ['type' => 'string', 'description' => 'Named guardian for any minor children, if the user has minor dependants.'],
            'specific_gifts' => ['type' => 'string', 'description' => 'Free-text description of specific gifts (item, recipient). Leave blank if the user mentions no specific gifts.'],
            'spouse_primary_beneficiary' => ['type' => 'boolean', 'description' => 'Whether the user\'s spouse is the primary beneficiary. Defaults true if the user is married and did not specify otherwise.'],
        ],
        'required' => ['executor_name'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3032-3075`):

```php
private function handleCreateWill(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('will');
    }

    $validationError = $this->validateToolInput($input, [
        'executor_name' => 'required|string|max:255',
        'residuary_beneficiary' => 'nullable|string|max:255',
        'guardian_for_minors' => 'nullable|string|max:255',
        'specific_gifts' => 'nullable|string|max:2000',
        'spouse_primary_beneficiary' => 'nullable|boolean',
    ]);
    if ($validationError) {
        return $validationError;
    }

    // Default spouse_primary_beneficiary true for married users when not specified.
    $spousePrimary = array_key_exists('spouse_primary_beneficiary', $input)
        ? (bool) $input['spouse_primary_beneficiary']
        : in_array((string) $user->marital_status, ['married', 'civil_partnership'], true);

    $will = \App\Models\Estate\Will::updateOrCreate(
        ['user_id' => $user->id],
        [
            'has_will' => true,
            'executor_name' => $input['executor_name'],
            'residuary_beneficiary' => $input['residuary_beneficiary'] ?? null,
            'guardian_for_minors' => $input['guardian_for_minors'] ?? null,
            'specific_gifts' => $input['specific_gifts'] ?? null,
            'spouse_primary_beneficiary' => $spousePrimary,
            'will_last_updated' => now()->toDateString(),
        ],
    );

    $this->invalidateUserCache($user->id);

    return [
        'action' => 'record_saved',
        'entity_type' => 'will',
        'id' => $will->id,
        'message' => 'Recorded your will details.',
    ];
}
```

---

## 5. `update_will`

If no will exists yet, falls through to `handleCreateWill` (create-on-update semantics).

**Schema** (`AiToolDefinitions.php:944-958`):

```php
[
    'name' => 'update_will',
    'description' => 'Update an existing will record. Use when the user amends their will details (new executor, new beneficiary, updated specific gifts).',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'executor_name' => ['type' => 'string', 'description' => 'New executor name.'],
            'residuary_beneficiary' => ['type' => 'string', 'description' => 'New residuary beneficiary.'],
            'guardian_for_minors' => ['type' => 'string', 'description' => 'New guardian for minors.'],
            'specific_gifts' => ['type' => 'string', 'description' => 'New specific gifts description.'],
            'spouse_primary_beneficiary' => ['type' => 'boolean', 'description' => 'Spouse as primary beneficiary flag.'],
        ],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3077-3134`):

```php
private function handleUpdateWill(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('will');
    }

    $validationError = $this->validateToolInput($input, [
        'executor_name' => 'nullable|string|max:255',
        'residuary_beneficiary' => 'nullable|string|max:255',
        'guardian_for_minors' => 'nullable|string|max:255',
        'specific_gifts' => 'nullable|string|max:2000',
        'spouse_primary_beneficiary' => 'nullable|boolean',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $will = \App\Models\Estate\Will::where('user_id', $user->id)->first();

    if ($will === null) {
        // No existing will to update — fall through to create semantics.
        return $this->handleCreateWill($input, $user, $isPreview);
    }

    $updates = array_filter(
        [
            'executor_name' => $input['executor_name'] ?? null,
            'residuary_beneficiary' => $input['residuary_beneficiary'] ?? null,
            'guardian_for_minors' => $input['guardian_for_minors'] ?? null,
            'specific_gifts' => $input['specific_gifts'] ?? null,
            'spouse_primary_beneficiary' => array_key_exists('spouse_primary_beneficiary', $input)
                ? (bool) $input['spouse_primary_beneficiary']
                : null,
        ],
        fn ($v) => $v !== null,
    );

    if ($updates === []) {
        return [
            'error' => true,
            'error_type' => 'nothing_to_update',
            'message' => 'No will fields were provided for update.',
        ];
    }

    $updates['will_last_updated'] = now()->toDateString();

    $will->update($updates);

    $this->invalidateUserCache($user->id);

    return [
        'action' => 'record_saved',
        'entity_type' => 'will',
        'id' => $will->id,
        'message' => 'Updated your will details.',
    ];
}
```

---

## 6. `create_power_of_attorney`

Creates one LPA row + linked attorney rows (primary, optional replacement).

**Schema** (`AiToolDefinitions.php:960-1002`):

```php
[
    'name' => 'create_power_of_attorney',
    'description' => <<<'DESC'
    Record a Lasting Power of Attorney (LPA) the user already has in place. UK has two types: Property & Financial Affairs (lpa_type=property_financial) and Health & Welfare (lpa_type=health_welfare). For each, capture the primary attorney name. Replacement attorneys are optional. You MAY call this tool multiple times in the same turn — for example if the user has BOTH a property_financial AND a health_welfare LPA, call create_power_of_attorney TWICE in your first response.

    STATUS IS MANDATORY — extract it from the user's wording:
      • "registered", "in force", "active with OPG", "already registered with the Office of the Public Guardian" → status = "registered"
      • "draft", "signed but not registered", "not yet registered", "in the pipeline", "sent off for registration", "being registered", "pending registration" → status = "draft"
      • If the user gives no signal at all, default to "draft".

    NEVER drop status=registered when the user said so. Worked example:
      User: "I have a registered property and financial LPA with my brother Tom"
      Required: create_power_of_attorney(lpa_type='property_financial', primary_attorney_name='Tom', status='registered').
    DESC,
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'lpa_type' => ['type' => 'string', 'enum' => ['property_financial', 'health_welfare'], 'description' => 'Which LPA type. property_financial covers money/property decisions. health_welfare covers medical/care decisions.'],
            'primary_attorney_name' => ['type' => 'string', 'description' => 'Full name of the primary attorney (the person empowered to act for the donor).'],
            'replacement_attorney_name' => ['type' => 'string', 'description' => 'Optional. Full name of a replacement attorney who steps in if the primary is unable or unwilling to act.'],
            'status' => ['type' => 'string', 'enum' => ['draft', 'registered'], 'description' => 'LPA status. If user says "registered" / "in force" / "active with OPG" → "registered". If user says "draft" / "not registered" / "pending" / "being registered" → "draft". Default "draft" if not stated.'],
            'opg_reference' => ['type' => 'string', 'description' => 'Office of the Public Guardian registration reference, if the LPA is registered.'],
        ],
        'required' => ['lpa_type', 'primary_attorney_name'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3136-3197`):

```php
private function handleCreatePowerOfAttorney(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('lasting power of attorney');
    }

    $validationError = $this->validateToolInput($input, [
        'lpa_type' => ['required', Rule::in(['property_financial', 'health_welfare'])],
        'primary_attorney_name' => 'required|string|max:255',
        'replacement_attorney_name' => 'nullable|string|max:255',
        'status' => ['nullable', Rule::in(['draft', 'registered'])],
        'opg_reference' => 'nullable|string|max:50',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $donorName = trim(($user->first_name ?? '').' '.($user->surname ?? '')) ?: ($user->name ?? '');

    $lpa = \Illuminate\Support\Facades\DB::transaction(function () use ($input, $user, $donorName) {
        $lpa = \App\Models\Estate\LastingPowerOfAttorney::create([
            'user_id' => $user->id,
            'lpa_type' => $input['lpa_type'],
            'status' => $input['status'] ?? 'draft',
            'source' => 'created',
            'donor_full_name' => $donorName,
            'donor_date_of_birth' => $user->date_of_birth,
            'opg_reference' => $input['opg_reference'] ?? null,
            'is_registered_with_opg' => ($input['status'] ?? 'draft') === 'registered',
            'registration_date' => ($input['status'] ?? 'draft') === 'registered' ? now()->toDateString() : null,
        ]);

        \App\Models\Estate\LpaAttorney::create([
            'lasting_power_of_attorney_id' => $lpa->id,
            'attorney_type' => 'primary',
            'full_name' => $input['primary_attorney_name'],
            'sort_order' => 1,
        ]);

        if (! empty($input['replacement_attorney_name'])) {
            \App\Models\Estate\LpaAttorney::create([
                'lasting_power_of_attorney_id' => $lpa->id,
                'attorney_type' => 'replacement',
                'full_name' => $input['replacement_attorney_name'],
                'sort_order' => 2,
            ]);
        }

        return $lpa;
    });

    $this->invalidateUserCache($user->id);

    $typeLabel = $lpa->lpa_type === 'property_financial' ? 'Property & Financial Affairs' : 'Health & Welfare';

    return [
        'action' => 'record_saved',
        'entity_type' => 'lasting_power_of_attorney',
        'id' => $lpa->id,
        'message' => "Recorded your {$typeLabel} LPA.",
    ];
}
```

---

## 7. `update_power_of_attorney`

**Schema** (`AiToolDefinitions.php:1004-1018`):

```php
[
    'name' => 'update_power_of_attorney',
    'description' => 'Update an existing LPA record (e.g. status change from draft to registered, OPG reference added, replacement attorney added).',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'lpa_id' => ['type' => 'integer', 'description' => 'ID of the LPA to update.'],
            'status' => ['type' => 'string', 'enum' => ['draft', 'registered']],
            'opg_reference' => ['type' => 'string'],
            'primary_attorney_name' => ['type' => 'string'],
            'replacement_attorney_name' => ['type' => 'string'],
        ],
        'required' => ['lpa_id'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3199-3283`):

```php
private function handleUpdatePowerOfAttorney(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('lasting power of attorney');
    }

    $validationError = $this->validateToolInput($input, [
        'lpa_id' => 'required|integer|exists:lasting_powers_of_attorney,id',
        'status' => ['nullable', Rule::in(['draft', 'registered'])],
        'opg_reference' => 'nullable|string|max:50',
        'primary_attorney_name' => 'nullable|string|max:255',
        'replacement_attorney_name' => 'nullable|string|max:255',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $lpa = \App\Models\Estate\LastingPowerOfAttorney::where('user_id', $user->id)
        ->find($input['lpa_id']);

    if ($lpa === null) {
        return [
            'error' => true,
            'error_type' => 'not_found',
            'message' => 'No LPA with that ID found for this user.',
        ];
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($input, $lpa) {
        $updates = [];

        if (array_key_exists('status', $input) && $input['status'] !== null) {
            $updates['status'] = $input['status'];
            $updates['is_registered_with_opg'] = $input['status'] === 'registered';
            if ($input['status'] === 'registered' && $lpa->registration_date === null) {
                $updates['registration_date'] = now()->toDateString();
            }
        }

        if (array_key_exists('opg_reference', $input) && $input['opg_reference'] !== null) {
            $updates['opg_reference'] = $input['opg_reference'];
        }

        if ($updates !== []) {
            $lpa->update($updates);
        }

        if (! empty($input['primary_attorney_name'])) {
            $primary = $lpa->attorneys()->where('attorney_type', 'primary')->first();
            if ($primary !== null) {
                $primary->update(['full_name' => $input['primary_attorney_name']]);
            } else {
                \App\Models\Estate\LpaAttorney::create([
                    'lasting_power_of_attorney_id' => $lpa->id,
                    'attorney_type' => 'primary',
                    'full_name' => $input['primary_attorney_name'],
                    'sort_order' => 1,
                ]);
            }
        }

        if (! empty($input['replacement_attorney_name'])) {
            $replacement = $lpa->attorneys()->where('attorney_type', 'replacement')->first();
            if ($replacement !== null) {
                $replacement->update(['full_name' => $input['replacement_attorney_name']]);
            } else {
                \App\Models\Estate\LpaAttorney::create([
                    'lasting_power_of_attorney_id' => $lpa->id,
                    'attorney_type' => 'replacement',
                    'full_name' => $input['replacement_attorney_name'],
                    'sort_order' => 2,
                ]);
            }
        }
    });

    $this->invalidateUserCache($user->id);

    return [
        'action' => 'record_saved',
        'entity_type' => 'lasting_power_of_attorney',
        'id' => $lpa->id,
        'message' => 'Updated your LPA.',
    ];
}
```

---

## 8. `create_family_member`

**Schema** (`AiToolDefinitions.php:1026-1041`):

```php
[
    'name' => 'create_family_member',
    'description' => 'Add a family member (spouse, child, dependent). Use when the user mentions family members who affect their financial planning. You MAY call this tool multiple times in the same turn when the user mentions multiple family members — for two children, call create_family_member TWICE in your first response.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'first_name' => ['type' => 'string', 'description' => 'First name'],
            'surname' => ['type' => 'string', 'description' => 'Surname'],
            'relationship' => ['type' => 'string', 'enum' => ['spouse', 'child', 'parent', 'sibling', 'other'], 'description' => 'Relationship to the user'],
            'date_of_birth' => ['type' => 'string', 'description' => 'Date of birth (YYYY-MM-DD)'],
            'gender' => ['type' => 'string', 'enum' => ['male', 'female', 'other']],
            'is_dependent' => ['type' => 'boolean', 'description' => 'Whether this person is financially dependent on the user'],
        ],
        'required' => ['first_name', 'relationship'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3539-3643`):

```php
private function handleCreateFamilyMember(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('family member');
    }

    $validationError = $this->validateToolInput($input, [
        'first_name' => 'required|string|max:255',
        'relationship' => ['required', Rule::in(['spouse', 'partner', 'child', 'step_child', 'parent', 'other_dependent'])],
        'surname' => 'nullable|string|max:255',
        'date_of_birth' => 'nullable|date',
        'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
        'is_dependent' => 'nullable|boolean',
        'education_status' => ['nullable', Rule::in(['pre_school', 'primary', 'secondary', 'further_education', 'higher_education', 'graduated', 'not_applicable'])],
        'receives_child_benefit' => 'nullable|boolean',
        'notes' => 'nullable|string|max:1000',
    ]);
    if ($validationError) {
        return $validationError;
    }

    // Default surname to user's surname if not provided
    $surname = $input['surname'] ?? $user->surname;

    // Map relationships to DB-compatible values (DB enum: spouse, child, parent, other_dependent)
    $relationship = $input['relationship'];
    $dbRelationship = match ($relationship) {
        'step_child' => 'child',
        'partner' => 'other_dependent',
        default => $relationship,
    };

    // Add note for mapped relationships
    $mappingNote = match ($relationship) {
        'step_child' => 'Step child',
        'partner' => 'Partner (unmarried)',
        default => null,
    };

    // Default is_dependent for children and dependents
    $isDependent = $input['is_dependent'] ?? in_array($relationship, ['child', 'step_child', 'other_dependent']);

    $payload = [
        'user_id' => $user->id,
        'relationship' => $dbRelationship,
        'first_name' => $input['first_name'],
        'last_name' => $surname,
        'is_dependent' => $isDependent,
    ];
    if (isset($input['date_of_birth']) && $input['date_of_birth'] !== '') {
        $payload['date_of_birth'] = $input['date_of_birth'];
    }
    if (isset($input['gender']) && $input['gender'] !== '') {
        $payload['gender'] = $input['gender'];
    }

    // Notes: combine the relationship-mapping note with any AI-supplied
    // notes (in that order so the mapping context comes first).
    $aiNotes = $input['notes'] ?? '';
    if ($mappingNote) {
        $payload['notes'] = trim($mappingNote.($aiNotes !== '' ? '. '.$aiNotes : ''));
    } elseif ($aiNotes !== '') {
        $payload['notes'] = $aiNotes;
    }

    // Child-specific: education_status (inferred from DOB if absent).
    if ($dbRelationship === 'child') {
        $educationStatus = $input['education_status'] ?? null;
        if (empty($educationStatus) && ! empty($input['date_of_birth'])) {
            try {
                $age = \Carbon\Carbon::parse($input['date_of_birth'])->age;
                $educationStatus = match (true) {
                    $age < 5 => 'pre_school',
                    $age < 11 => 'primary',
                    $age < 16 => 'secondary',
                    $age < 18 => 'further_education',
                    $age < 22 => 'higher_education',
                    default => 'graduated',
                };
            } catch (\Exception $e) {
                // Unparseable DOB — leave education_status null.
            }
        }
        if (! empty($educationStatus)) {
            $payload['education_status'] = $educationStatus;
        }
        if (isset($input['receives_child_benefit'])) {
            $payload['receives_child_benefit'] = (bool) $input['receives_child_benefit'];
        }
    }

    $member = DB::transaction(fn () => FamilyMember::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'family_member',
        'entity_id' => $member->id,
        'name' => trim($member->first_name.' '.($member->last_name ?? '')),
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added {$input['first_name']} as your {$relationship}.",
    ];
}
```

---

## 9. `create_trust`

Discretionary + Accumulation/Maintenance trusts are flagged `is_relevant_property_trust` (subject to 10-yearly + exit IHT charges). `TrustObserver::created` emits a corresponding `Gift` (CLT) row when the trust persists, so no extra step here.

**Schema** (`AiToolDefinitions.php:1043-1057`):

```php
[
    'name' => 'create_trust',
    'description' => 'Record a trust for estate planning. Use when the user mentions trusts they have set up or want to document. You MAY call this tool multiple times in the same turn when the user mentions multiple trusts.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'trust_name' => ['type' => 'string', 'description' => 'Name of the trust'],
            'trust_type' => ['type' => 'string', 'enum' => ['discretionary', 'bare', 'interest_in_possession', 'life_insurance', 'loan', 'discounted_gift', 'accumulation_maintenance'], 'description' => 'Type of trust'],
            'current_value' => ['type' => 'number', 'description' => 'Current value of assets in trust (£)'],
            'date_established' => ['type' => 'string', 'description' => 'Date trust was established (YYYY-MM-DD)'],
            'settlor' => ['type' => 'string', 'description' => 'Who settled the trust'],
        ],
        'required' => ['trust_name', 'trust_type'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3645-3719`):

```php
private function handleCreateTrust(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('trust');
    }

    $validationError = $this->validateToolInput($input, [
        'trust_name' => 'required|string|max:255',
        'trust_type' => ['required', Rule::in(['discretionary', 'bare', 'interest_in_possession', 'life_insurance', 'loan', 'discounted_gift', 'accumulation_maintenance', 'mixed', 'settlor_interested'])],
        'initial_value' => 'nullable|numeric|min:0|max:999999999.99',
        'current_value' => 'nullable|numeric|min:0|max:999999999.99',
        'trust_creation_date' => 'nullable|date',
        'beneficiaries' => 'nullable|string|max:1000',
        'trustees' => 'nullable|string|max:1000',
        'purpose' => 'nullable|string|max:1000',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $initialValue = isset($input['initial_value'])
        ? (float) $input['initial_value']
        : (isset($input['current_value']) ? (float) $input['current_value'] : 0);
    $currentValue = isset($input['current_value']) ? (float) $input['current_value'] : $initialValue;

    $beneficiaries = $this->resolveFamilyNames($input['beneficiaries'] ?? null, $user);
    $trustees = $this->resolveFamilyNames($input['trustees'] ?? null, $user);
    $settlor = $input['settlor'] ?? trim($user->first_name.' '.$user->surname);
    $creationDate = $input['trust_creation_date'] ?? now()->toDateString();

    // Discretionary + A&M trusts attract relevant-property regime
    // (10-yearly + exit charges). Mirrors TrustController::createTrust.
    $isRelevantProperty = in_array($input['trust_type'], ['discretionary', 'accumulation_maintenance'], true);

    $payload = [
        'user_id' => $user->id,
        'trust_name' => $input['trust_name'],
        'trust_type' => $input['trust_type'],
        'initial_value' => $initialValue,
        'current_value' => $currentValue,
        'trust_creation_date' => $creationDate,
        'settlor' => $settlor,
        'is_relevant_property_trust' => $isRelevantProperty,
    ];
    if ($beneficiaries !== null) {
        $payload['beneficiaries'] = $beneficiaries;
    }
    if ($trustees !== null) {
        $payload['trustees'] = $trustees;
    }
    if (isset($input['purpose']) && $input['purpose'] !== '') {
        $payload['purpose'] = $input['purpose'];
    }

    // FR-M15 — TrustObserver::created emits the corresponding Gift
    // (Chargeable Lifetime Transfer) when the trust persists; we don't
    // need to do anything extra here.
    $trust = DB::transaction(fn () => Trust::create($payload));

    $this->invalidateUserCache($user->id);

    $cltMessage = $initialValue > 0
        ? " I've also recorded a Chargeable Lifetime Transfer of £".number_format($initialValue).' for Inheritance Tax tracking.'
        : '';

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'trust',
        'entity_id' => $trust->id,
        'name' => $trust->trust_name,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added your \"{$trust->trust_name}\" trust.{$cltMessage}",
    ];
}
```

---

## 10. `create_chattel`

**Schema** (`AiToolDefinitions.php:1075-1089`):

```php
[
    'name' => 'create_chattel',
    'description' => 'Record a personal valuable item (jewellery, art, collectibles, vehicles). Use when the user mentions valuable personal possessions. You MAY call this tool multiple times in the same turn when the user mentions multiple items.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'description' => ['type' => 'string', 'description' => 'Description of the item'],
            'category' => ['type' => 'string', 'enum' => ['jewellery', 'art', 'antiques', 'collectibles', 'vehicles', 'other'], 'description' => 'Category of item'],
            'estimated_value' => ['type' => 'number', 'description' => 'Estimated current value (£)'],
            'purchase_value' => ['type' => 'number', 'description' => 'Original purchase value (£)'],
            'is_insured' => ['type' => 'boolean', 'description' => 'Whether the item is insured'],
        ],
        'required' => ['description', 'estimated_value'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3781-3838`):

```php
private function handleCreateChattel(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('personal valuable');
    }

    $validationError = $this->validateToolInput($input, [
        'description' => 'required|string|max:255',
        'estimated_value' => 'required|numeric|min:0|max:999999999.99',
        'category' => ['nullable', Rule::in(['jewellery', 'art', 'antiques', 'collectibles', 'vehicles', 'other'])],
        'purchase_value' => 'nullable|numeric|min:0|max:999999999.99',
        'is_insured' => 'nullable|boolean',
    ]);
    if ($validationError) {
        return $validationError;
    }

    // AI category -> canonical DB chattel_type (singular forms).
    $chattelType = match ($input['category'] ?? 'other') {
        'jewellery' => 'jewelry',
        'art' => 'art',
        'antiques' => 'antique',
        'collectibles' => 'collectible',
        'vehicles' => 'vehicle',
        default => 'other',
    };

    $payload = [
        'user_id' => $user->id,
        'chattel_type' => $chattelType,
        'name' => $input['description'],
        'current_value' => (float) $input['estimated_value'],
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'valuation_date' => now()->toDateString(),
    ];

    if (isset($input['purchase_value']) && is_numeric($input['purchase_value'])) {
        $payload['purchase_price'] = (float) $input['purchase_value'];
    }
    if (isset($input['notes']) && $input['notes'] !== '') {
        $payload['notes'] = $input['notes'];
    }

    $chattel = DB::transaction(fn () => Chattel::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'chattel',
        'entity_id' => $chattel->id,
        'name' => $chattel->name,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added your \"{$chattel->name}\".",
    ];
}
```
