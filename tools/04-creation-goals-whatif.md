# Goals, Life Events & What-If (3)

These three tools are stripped from advice mode (`AdviceFyn::WRITE_TOOLS`) and preview-block via `previewBlocked()`. `create_what_if_scenario` is gated behind `isPreviewMode === false` at the registration layer (`AiToolDefinitions::getTools` only adds `whatIfTools()` when `! $isPreviewMode`).

> Source: `app/Services/AI/AiToolDefinitions.php` (schema) + `app/Agents/CoordinatingAgent.php` (handlers).

---

## 1. `create_goal`

**Schema** (`AiToolDefinitions::goalAndEventTools()` — `AiToolDefinitions.php:272-309`):

```php
[
    'name' => 'create_goal',
    'description' => 'Create a new financial goal for the user. Use this when the user says they want to save for something specific. You MAY call this tool multiple times in the same turn when the user mentions multiple goals.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Name of the goal (e.g., "Holiday Fund", "House Deposit")'],
            'target_amount' => ['type' => 'number', 'description' => 'Target amount in pounds'],
            'target_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Target date in YYYY-MM-DD format'],
            'priority' => ['type' => 'string', 'enum' => ['critical', 'high', 'medium', 'low'], 'description' => 'Priority level of the goal'],
            'goal_type' => ['type' => 'string', 'enum' => ['emergency_fund', 'house_deposit', 'holiday', 'education', 'wedding', 'car', 'retirement_supplement', 'other'], 'description' => 'Type of goal'],
            'monthly_contribution' => ['type' => 'number', 'description' => 'Optional monthly contribution amount in pounds. If provided, Fyn will assess whether this is sufficient to reach the target by the deadline.'],
        ],
        'required' => ['name', 'target_amount', 'target_date', 'priority', 'goal_type'],
        'additionalProperties' => false,
    ],
],
```

> **Note:** the schema enum (`emergency_fund`, `house_deposit`, …) and the handler validation (`emergency_fund`, `home_deposit`, `property_purchase`, …) drift slightly. The handler accepts a wider canonical set; the schema name `house_deposit` is mapped to handler `home_deposit` upstream by xAI's looser binding. Watch for this when testing.

**Handler** (`CoordinatingAgent.php:1920-1973`):

```php
private function handleCreateGoal(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('goal');
    }

    $validationError = $this->validateToolInput($input, [
        'name' => 'required|string|max:255',
        'target_amount' => 'required|numeric|min:0|max:999999999.99',
        'target_date' => 'required|date|after:today',
        'priority' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
        'goal_type' => ['required', Rule::in(['emergency_fund', 'home_deposit', 'property_purchase', 'holiday', 'education', 'wedding', 'car_purchase', 'retirement', 'wealth_accumulation', 'debt_repayment', 'custom'])],
        'monthly_contribution' => 'nullable|numeric|min:0|max:999999.99',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $payload = [
        'user_id' => $user->id,
        'goal_name' => $input['name'],
        'goal_type' => $input['goal_type'],
        'target_amount' => (float) $input['target_amount'],
        'target_date' => $input['target_date'],
        'priority' => $input['priority'],
    ];

    // Custom goals require custom_goal_type_name; reuse the goal name
    // because the AI tool doesn't expose a separate slot for it.
    if ($input['goal_type'] === 'custom') {
        $payload['custom_goal_type_name'] = $input['name'];
    }

    if (isset($input['monthly_contribution']) && is_numeric($input['monthly_contribution'])) {
        $payload['monthly_contribution'] = (float) $input['monthly_contribution'];
    }
    if (isset($input['description']) && $input['description'] !== '') {
        $payload['description'] = $input['description'];
    }

    $goal = DB::transaction(fn () => Goal::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'goal',
        'entity_id' => $goal->id,
        'name' => $goal->goal_name,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added your \"{$goal->goal_name}\" goal.",
    ];
}
```

---

## 2. `create_life_event`

**Schema** (`AiToolDefinitions.php:310-336`):

```php
[
    'name' => 'create_life_event',
    'description' => 'Create a future life event that may impact the user\'s financial plan. You MAY call this tool multiple times in the same turn when the user mentions multiple events.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'event_type' => ['type' => 'string', 'description' => 'Type of life event (e.g., "marriage", "graduation", "career_change", "property_purchase", "retirement")'],
            'event_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Expected date in YYYY-MM-DD format'],
            'description' => ['type' => 'string', 'description' => 'Description of the event'],
            'estimated_cost' => ['type' => 'number', 'description' => 'Estimated cost in pounds (if applicable)'],
        ],
        'required' => ['event_type', 'event_date', 'description'],
        'additionalProperties' => false,
    ],
],
```

> **Note:** Anthropic schema field names (`event_type`, `event_date`, `description`, `estimated_cost`) differ from xAI strict-mode names (`event_name`, `event_type`, `event_date`, `estimated_amount`, `certainty`, `description`) and from the handler's accepted names. The handler validates against `event_name`, `event_type`, `event_date`, `estimated_amount`, `certainty`, `description`. The xAI side is canonical; the Anthropic schema is older and may need realignment.

**Handler** (`CoordinatingAgent.php:1975-2021`):

```php
private function handleCreateLifeEvent(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('life event');
    }

    $validationError = $this->validateToolInput($input, [
        'event_name' => 'required|string|max:255',
        'event_type' => ['required', Rule::in(['inheritance', 'gift_received', 'bonus', 'redundancy_payment', 'property_sale', 'business_sale', 'pension_lump_sum', 'lottery_windfall', 'custom_income', 'large_purchase', 'home_improvement', 'wedding', 'education_fees', 'gift_given', 'medical_expense', 'custom_expense'])],
        'event_date' => 'required|date',
        'estimated_amount' => 'required|numeric|min:0|max:999999999.99',
        'certainty' => ['nullable', Rule::in(['confirmed', 'likely', 'possible', 'speculative'])],
        'description' => 'nullable|string|max:500',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $payload = [
        'user_id' => $user->id,
        'event_name' => $input['event_name'],
        'event_type' => $input['event_type'],
        'amount' => (float) $input['estimated_amount'],
        'expected_date' => $input['event_date'],
        'certainty' => $input['certainty'] ?? 'likely',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    if (isset($input['description']) && $input['description'] !== '') {
        $payload['description'] = $input['description'];
    }

    $event = DB::transaction(fn () => LifeEvent::create($payload));

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'life_event',
        'entity_id' => $event->id,
        'name' => $event->event_name,
        'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
        'message' => "I've added your \"{$event->event_name}\" life event.",
    ];
}
```

---

## 3. `create_what_if_scenario`

**Important**: This tool persists a `WhatIfScenario` row, so it is in `AdviceFyn::WRITE_TOOLS` and stripped from advice mode. (S0.5.r commit comment: *"create_what_if_scenario persists a WhatIfScenario row and must therefore be written by Onboarding Fyn like every other create_*."*)

The xAI version has `strict: false` because the `parameters` object has dynamic keys (cannot be validated under strict mode).

**Schema** (`AiToolDefinitions::whatIfTools()` — `AiToolDefinitions.php:222-249`):

```php
[
    'name' => 'create_what_if_scenario',
    'description' => 'Create a persistent what-if scenario showing how changes would affect the user\'s financial plan. The scenario is saved and the user is navigated to the What If dashboard to see the comparison. Use this when the user asks "what if" questions about their finances.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Short descriptive name for the scenario (e.g. "Retire at 55", "Sell Main Residence")'],
            'scenario_type' => ['type' => 'string', 'enum' => ['retirement', 'property', 'family', 'income', 'custom'], 'description' => 'Category of the what-if scenario'],
            'parameters' => ['type' => 'object', 'description' => 'The what-if parameter overrides. Keys: retirement_age, pension_contribution, sell_property, buy_property, divorce, marriage, new_child, income_change, job_loss, inheritance'],
            'description' => ['type' => 'string', 'description' => 'Your explanation of what this scenario models and the key assumptions'],
        ],
        'required' => ['name', 'scenario_type', 'parameters', 'description'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1723-1742`):

Delegates to `App\Services\WhatIf\WhatIfScenarioService::createScenario`. The `'action' => 'navigate'` key in the result tells `HasAiChat::stream` to yield a `navigation` SSE event — the user lands on the persisted scenario detail page.

```php
private function handleCreateWhatIfScenario(array $input, User $user): array
{
    $service = app(\App\Services\WhatIf\WhatIfScenarioService::class);

    $result = $service->createScenario($user, [
        'name' => $input['name'],
        'scenario_type' => $input['scenario_type'] ?? 'custom',
        'parameters' => $input['parameters'],
        'created_via' => 'ai_chat',
        'ai_narrative' => $input['description'] ?? null,
    ]);

    return [
        'success' => true,
        'scenario_id' => $result['scenario_id'],
        'comparison' => $result,
        'action' => 'navigate',
        'route_path' => '/planning/what-if/'.$result['scenario_id'],
    ];
}
```

> **Note:** `handleCreateWhatIfScenario` does **not** check `$isPreview` because the tool is gated at registration (`whatIfTools()` is only added to the catalogue when `! $isPreviewMode`). If a preview user somehow invoked it, the handler would still write — the registration gate is the only line of defence.
