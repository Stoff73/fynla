# SaveTax campaign capture tools (6)

These six tools are emitted by `AiToolDefinitions::campaignSaveTaxTools()` and used in the **campaign-only state-machine branch** after expenditure capture (path=campaign users only). They write to existing tables: `dc_pensions`, `users`, `tax_strategy_household_inputs`, `pension_input_histories`.

The campaign tools appear in TWO places:
1. The main `getTools()` catalogue when `! $isPreviewMode` (so the LLM can pick them post-onboarding).
2. The `onboardingExtractionTools()` override list — merged in via `array_merge($tools, $this->campaignSaveTaxTools())` at `AiToolDefinitions.php:1389`. Without this, the LLM is offered the tool by the prompt but the director's filter rejects the tool call as "not found".

> Source: `app/Services/AI/AiToolDefinitions.php` (schema) + `app/Agents/CoordinatingAgent.php` (handlers).

---

## 1. `capture_salary_sacrifice`

**Schema** (`AiToolDefinitions.php:1431-1443`):

```php
[
    'name' => 'capture_salary_sacrifice',
    'description' => 'Set salary_sacrifice flag on a specific DC pension owned by the user, with an optional employer NI rebate share. Use during the SaveTax campaign occupational-scheme capture state.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'pension_id' => ['type' => 'integer', 'description' => 'ID of the dc_pension row to update.'],
            'salary_sacrifice' => ['type' => 'boolean', 'description' => 'true if pension contributions are made via salary sacrifice.'],
            'employer_ni_rebate_pct' => ['type' => 'number', 'description' => 'Optional. Share of the employer National Insurance saving rebated back into the pension as a fraction between 0 and 1 (e.g. 0.5 for 50%).'],
        ],
        'required' => ['pension_id', 'salary_sacrifice'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3905-3937`):

```php
private function handleCaptureSalarySacrifice(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('pension');
    }

    if (! isset($input['pension_id'], $input['salary_sacrifice'])) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'pension_id and salary_sacrifice are required.'];
    }

    $pension = \App\Models\DCPension::where('id', $input['pension_id'])
        ->where('user_id', $user->id)
        ->first();

    if (! $pension) {
        return ['error' => true, 'error_type' => 'not_found', 'message' => 'Pension not found or not owned by user.'];
    }

    $payload = ['salary_sacrifice' => (bool) $input['salary_sacrifice']];

    if (array_key_exists('employer_ni_rebate_pct', $input) && $input['employer_ni_rebate_pct'] !== null) {
        $rebate = (float) $input['employer_ni_rebate_pct'];
        $payload['employer_ni_rebate_pct'] = max(0.0, min(1.0, $rebate));
    }

    $pension->update($payload);

    return [
        'updated' => true,
        'pension_id' => $pension->id,
        'message' => 'Salary sacrifice setting updated.',
    ];
}
```

---

## 2. `capture_spouse_work_status`

Routes the next state-machine state. `dual_earner` ⇒ goes to `capture_spouse_household_data`. `single_earner_couple` ⇒ goes to `capture_spouse_non_working_assets` and flags `marriage_allowance_eligible`.

**Schema** (`AiToolDefinitions.php:1445-1455`):

```php
[
    'name' => 'capture_spouse_work_status',
    'description' => 'Set whether the user\'s spouse currently works. Updates household_calculation_mode (dual_earner | single_earner_couple) and marriage_allowance_eligible accordingly. The state machine routes the next state based on the result.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'spouse_works' => ['type' => 'boolean', 'description' => 'true if spouse has earned income, false otherwise.'],
        ],
        'required' => ['spouse_works'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3939-3964`):

```php
private function handleCaptureSpouseWorkStatus(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('household');
    }

    if (! array_key_exists('spouse_works', $input)) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'spouse_works is required.'];
    }

    $works = (bool) $input['spouse_works'];

    $user->update([
        'marriage_allowance_eligible' => ! $works,
        'household_calculation_mode' => $works ? 'dual_earner' : 'single_earner_couple',
    ]);

    return [
        'updated' => true,
        'household_calculation_mode' => $user->household_calculation_mode,
        'marriage_allowance_eligible' => $user->marriage_allowance_eligible,
        'message' => $works
            ? 'Recorded that your spouse works — we\'ll capture more details next.'
            : 'Recorded that your spouse doesn\'t currently work — Marriage Allowance may apply.',
    ];
}
```

---

## 3. `capture_spouse_household_data`

Used in `dual_earner` households. Writes to `tax_strategy_household_inputs` via `updateOrCreate`.

**Schema** (`AiToolDefinitions.php:1457-1473`):

```php
[
    'name' => 'capture_spouse_household_data',
    'description' => 'Capture working-spouse data for dual_earner households (spouse_works=yes path). Writes to tax_strategy_household_inputs.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'spouse_annual_income' => ['type' => 'number', 'description' => 'Spouse gross annual income in pounds.'],
            'spouse_employment_status' => ['type' => 'string', 'enum' => ['full_time', 'part_time', 'self_employed', 'retired'], 'description' => 'Spouse employment status.'],
            'spouse_isa_balance' => ['type' => 'number', 'description' => 'Spouse current ISA balance in pounds.'],
            'spouse_psa_band' => ['type' => 'string', 'enum' => ['basic', 'higher', 'additional'], 'description' => 'Spouse Personal Savings Allowance band.'],
            'spouse_unrealised_gains' => ['type' => 'number', 'description' => 'Spouse unrealised capital gains in pounds.'],
            'spouse_annual_dividends' => ['type' => 'number', 'description' => 'Spouse annual dividend income in pounds.'],
            'spouse_pension_input_annual' => ['type' => 'number', 'description' => 'Spouse gross annual pension contribution in pounds.'],
        ],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3966-3993`):

```php
private function handleCaptureSpouseHouseholdData(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('household');
    }

    $allowed = array_intersect_key($input, array_flip([
        'spouse_annual_income',
        'spouse_employment_status',
        'spouse_isa_balance',
        'spouse_psa_band',
        'spouse_unrealised_gains',
        'spouse_annual_dividends',
        'spouse_pension_input_annual',
    ]));

    \App\Models\TaxStrategyHouseholdInput::updateOrCreate(
        ['user_id' => $user->id],
        $allowed
    );

    return [
        'onboarding_capture' => true,
        'field_group' => 'campaign_spouse_household',
        'summary' => 'Spouse household data captured.',
        'details' => $allowed,
    ];
}
```

---

## 4. `capture_spouse_non_working_assets`

Used in `single_earner_couple` path to size asset-shifting capacity (PA, SR-Sav, PSA, ISA, CGT, Dividend allowance) and the non-earner spouse pension contribution.

**Schema** (`AiToolDefinitions.php:1475-1489`):

```php
[
    'name' => 'capture_spouse_non_working_assets',
    'description' => 'Capture standalone assets owned by a non-working spouse (single_earner_couple path). Used to compute available capacity for asset-shifting strategies (Personal Allowance, Starting Rate for Savings, Personal Savings Allowance, ISA, CGT, Dividend allowance) and to size a non-earner spouse pension contribution.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'spouse_existing_isa_balance' => ['type' => 'number', 'description' => 'Spouse\'s existing standalone ISA balance.'],
            'spouse_existing_savings_balance' => ['type' => 'number', 'description' => 'Spouse\'s existing standalone bank/savings balance.'],
            'spouse_existing_investment_balance' => ['type' => 'number', 'description' => 'Spouse\'s existing standalone investment account (GIA) balance.'],
            'spouse_existing_dividend_holdings_value' => ['type' => 'number', 'description' => 'Value of spouse\'s dividend-paying holdings.'],
            'spouse_existing_pension_balance' => ['type' => 'number', 'description' => 'Spouse\'s existing personal-pension pot value (used to size the non-earner pension top-up suggestion).'],
        ],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:3995-4020`):

```php
private function handleCaptureSpouseNonWorkingAssets(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('household');
    }

    $allowed = array_intersect_key($input, array_flip([
        'spouse_existing_isa_balance',
        'spouse_existing_savings_balance',
        'spouse_existing_investment_balance',
        'spouse_existing_dividend_holdings_value',
        'spouse_existing_pension_balance',
    ]));

    \App\Models\TaxStrategyHouseholdInput::updateOrCreate(
        ['user_id' => $user->id],
        $allowed
    );

    return [
        'onboarding_capture' => true,
        'field_group' => 'campaign_spouse_non_working_assets',
        'summary' => 'Spouse standalone assets captured.',
        'details' => $allowed,
    ];
}
```

---

## 5. `capture_pension_history`

Captures up to 3 prior tax years of gross pension input. Used by the Pension Annual Allowance Carry-Forward strategy.

**Schema** (`AiToolDefinitions.php:1491-1513`):

```php
[
    'name' => 'capture_pension_history',
    'description' => 'Capture the user\'s gross pension contributions for each of the last 3 tax years. Used by the Pension Annual Allowance Carry-Forward strategy to compute unused AA the user could still pension-up. Pass each year individually using the canonical "YYYY/YY" tax-year format (e.g. "2024/25").',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'history' => [
                'type' => 'array',
                'description' => 'List of tax_year + amount pairs. The strategy reads up to the most recent 3 entries.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'tax_year' => ['type' => 'string', 'description' => 'UK tax year in "YYYY/YY" format (e.g. "2024/25").'],
                        'pension_input_amount' => ['type' => 'number', 'description' => 'Gross pension input for that year in pounds.'],
                    ],
                    'required' => ['tax_year', 'pension_input_amount'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['history'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:4022-4061`):

```php
private function handleCapturePensionHistory(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('pension');
    }

    $history = $input['history'] ?? null;
    if (! is_array($history) || $history === []) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'history must be a non-empty array.'];
    }

    $written = [];
    foreach ($history as $entry) {
        if (! is_array($entry)) {
            continue;
        }
        $taxYear = isset($entry['tax_year']) ? (string) $entry['tax_year'] : null;
        $amount = isset($entry['pension_input_amount']) ? (float) $entry['pension_input_amount'] : null;
        if ($taxYear === null || $taxYear === '' || $amount === null || $amount < 0) {
            continue;
        }

        \App\Models\PensionInputHistory::updateOrCreate(
            ['user_id' => $user->id, 'tax_year' => $taxYear],
            ['pension_input_amount' => $amount],
        );
        $written[$taxYear] = $amount;
    }

    if ($written === []) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No valid history entries provided.'];
    }

    return [
        'onboarding_capture' => true,
        'field_group' => 'campaign_pension_history',
        'summary' => sprintf('Captured %d year(s) of pension history.', count($written)),
        'details' => $written,
    ];
}
```

---

## 6. `capture_charitable_giving`

Single-field capture of annual Gift Aid donations. Used by the Gift Aid Higher-Rate Relief strategy.

**Schema** (`AiToolDefinitions.php:1515-1525`):

```php
[
    'name' => 'capture_charitable_giving',
    'description' => 'Capture the user\'s annual charitable donations covered by Gift Aid. Used by the Gift Aid Higher-Rate Relief strategy to compute the personal tax relief the user can reclaim via Self Assessment when they donate at the higher or additional rate.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'annual_donations' => ['type' => 'number', 'description' => 'Total annual Gift-Aid-eligible donations in pounds.'],
        ],
        'required' => ['annual_donations'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:4063-4088`):

```php
private function handleCaptureCharitableGiving(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('profile');
    }

    if (! array_key_exists('annual_donations', $input)) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'annual_donations is required.'];
    }

    $amount = (float) $input['annual_donations'];
    if ($amount < 0) {
        return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'annual_donations must be >= 0.'];
    }

    $user->update(['annual_charitable_donations' => $amount]);

    return [
        'onboarding_capture' => true,
        'field_group' => 'campaign_charitable_giving',
        'summary' => $amount > 0
            ? sprintf('Annual Gift Aid donations recorded as £%s.', number_format($amount, 0))
            : 'No Gift Aid donations recorded.',
        'details' => ['annual_charitable_donations' => $amount],
    ];
}
```
