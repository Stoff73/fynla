# Read-only tools (12)

These tools are available in **both** Advice and Onboarding mode. None of them are stripped by `AdviceFyn::WRITE_TOOLS` (except `navigate_to_page`, which IS stripped — see below). All are exposed to preview users.

> Source files:
> - Schemas (Anthropic): `app/Services/AI/AiToolDefinitions.php`
> - Schemas (xAI strict): `app/Services/AI/XaiToolDefinitions.php`
> - Handlers: `app/Agents/CoordinatingAgent.php`

---

## 1. `navigate_to_page`

**Mode:** Onboarding only. Stripped from advice mode (BS-14: LLM was using navigation as a write-intent escape hatch). See `AdviceFyn::WRITE_TOOLS`.

**Schema** (`AiToolDefinitions::navigationTools()` — `AiToolDefinitions.php:51-86`):

```php
[
    'name' => 'navigate_to_page',
    'description' => 'Navigate the user to a specific page in the application. Use this when the user asks to go somewhere or when showing them relevant information would be helpful.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'route_path' => [
                'type' => 'string',
                'description' => 'The application route path. Valid routes: '
                    .'MAIN: /dashboard, /profile, /settings, /settings/security, /settings/assumptions, /help. '
                    .'INCOME & EXPENDITURE: /valuable-info?section=income (Income tab), /valuable-info?section=expenditure (Expenditure tab), /valuable-info?section=letter (Letter to Spouse tab), /valuable-info?section=risk (Risk Profile summary tab). '
                    .'NET WORTH: /net-worth/wealth-summary, /net-worth/property, /net-worth/investments, /net-worth/retirement, /net-worth/cash (Bank Accounts & Savings), /net-worth/business, /net-worth/chattels, /net-worth/liabilities. '
                    .'PROTECTION: /protection. '
                    .'ESTATE: /estate (Estate Planning dashboard), /estate/will-builder (Will Builder), /estate/power-of-attorney (Power of Attorney). '
                    .'TRUSTS: /trusts. '
                    .'GOALS: /goals (Goals & Life Events), /goals?tab=events (Life Events tab). '
                    .'RISK: /risk-profile. '
                    .'PLANS: /plans (all plans), /plans/investment, /plans/retirement, /plans/protection, /plans/estate, /holistic-plan (Holistic Financial Plan). '
                    .'ACTIONS: /actions. '
                    .'PLANNING: /planning/journeys, /planning/what-if (What-If Scenarios). '
                    .'NEVER use /savings or /investment — these are legacy redirects. Use /net-worth/cash and /net-worth/investments instead.',
            ],
            'description' => [
                'type' => 'string',
                'description' => 'Brief explanation of why navigating here is helpful',
            ],
        ],
        'required' => ['route_path', 'description'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1062-1065`):

```php
private function handleNavigation(array $input): array
{
    return ['action' => 'navigate', 'route_path' => $input['route_path'], 'description' => $input['description'] ?? ''];
}
```

The `'action' => 'navigate'` key is the contract: `HasAiChat::stream` sees that key and yields a synthetic `navigation` SSE event consumed by `AiChatPanel.vue`.

---

## 2. `list_records`

**Schema** (`AiToolDefinitions::analysisTools()` — `AiToolDefinitions.php:91-106`):

```php
[
    'name' => 'list_records',
    'description' => 'List existing records of a given type with IDs, key details, balances, interest rates, and values. Use this BEFORE calling update_record to find the correct entity_id. Use this for factual questions about the user\'s accounts — balances, interest rates, providers, policy details. For example: "how much interest will I earn?" → list_records(savings_account). "What pensions do I have?" → list_records(dc_pension). Returns raw data; for full module analysis use get_module_analysis instead.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'entity_type' => [
                'type' => 'string',
                'enum' => ['savings_account', 'investment_account', 'dc_pension', 'db_pension', 'property', 'mortgage', 'life_insurance', 'critical_illness', 'income_protection', 'trust', 'business_interest', 'chattel', 'estate_liability', 'estate_gift', 'family_member'],
                'description' => 'The type of record to list.',
            ],
        ],
        'required' => ['entity_type'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1398-1541`):

```php
private function handleListRecords(array $input, User $user): array
{
    $entityType = $input['entity_type'] ?? null;
    if (! $entityType) {
        return ['error' => true, 'message' => 'entity_type is required'];
    }

    $userId = $user->id;
    $records = [];

    // Helper to build ownership fields from the record's own data
    $ownershipFields = function ($record) use ($userId) {
        $type = $record->ownership_type ?? 'individual';
        if ($type === 'individual') {
            return ['ownership_type' => 'individual'];
        }

        $userPct = (float) ($record->user_id === $userId
            ? ($record->ownership_percentage ?? 100)
            : (100 - ($record->ownership_percentage ?? 100)));

        $coOwnerName = $record->joint_owner_name
            ?? ($record->jointOwner?->first_name)
            ?? null;

        $fields = [
            'ownership_type' => $type,
            'your_share_percent' => $userPct,
            'co_owner_share_percent' => 100 - $userPct,
        ];
        if ($coOwnerName) {
            $fields['co_owner'] = $coOwnerName;
        }

        return $fields;
    };

    switch ($entityType) {
        case 'savings_account':
            $items = \App\Models\SavingsAccount::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
            $records = $items->map(function ($a) use ($ownershipFields) {
                $fields = $ownershipFields($a);
                $total = (float) $a->current_balance;
                if (isset($fields['your_share_percent']) && $fields['your_share_percent'] < 100) {
                    $fields['total_balance'] = $total;
                    $fields['your_share_value'] = round($total * $fields['your_share_percent'] / 100, 2);
                }

                return array_merge(['id' => $a->id, 'account_name' => $a->account_name, 'institution' => $a->institution, 'balance' => $total, 'type' => $a->account_type, 'interest_rate' => (float) $a->interest_rate, 'rate_valid_until' => $a->rate_valid_until?->format('Y-m-d'), 'access_type' => $a->access_type, 'notice_period_days' => $a->notice_period_days, 'maturity_date' => $a->maturity_date?->format('Y-m-d'), 'is_emergency_fund' => (bool) $a->is_emergency_fund, 'is_isa' => (bool) $a->is_isa, 'isa_type' => $a->isa_type, 'isa_subscription_amount' => $a->isa_subscription_amount ? (float) $a->isa_subscription_amount : null, 'regular_contribution' => $a->regular_contribution_amount ? (float) $a->regular_contribution_amount : null, 'contribution_frequency' => $a->contribution_frequency], $fields);
            })->toArray();
            break;
        case 'investment_account':
            $items = \App\Models\Investment\InvestmentAccount::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
            $records = $items->map(function ($a) use ($ownershipFields) {
                $fields = $ownershipFields($a);
                $total = (float) $a->current_value;
                if (isset($fields['your_share_percent']) && $fields['your_share_percent'] < 100) {
                    $fields['total_value'] = $total;
                    $fields['your_share_value'] = round($total * $fields['your_share_percent'] / 100, 2);
                }

                return array_merge(['id' => $a->id, 'account_name' => $a->account_name, 'provider' => $a->provider, 'platform' => $a->platform, 'account_type' => $a->account_type, 'current_value' => $total, 'holdings_count' => $a->holdings()->count(), 'contributions_ytd' => $a->contributions_ytd ? (float) $a->contributions_ytd : null, 'monthly_contribution' => $a->monthly_contribution_amount ? (float) $a->monthly_contribution_amount : null, 'contribution_frequency' => $a->contribution_frequency, 'platform_fee_percent' => $a->platform_fee_percent ? (float) $a->platform_fee_percent : null, 'advisor_fee_percent' => $a->advisor_fee_percent ? (float) $a->advisor_fee_percent : null, 'isa_type' => $a->isa_type, 'isa_subscription_current_year' => $a->isa_subscription_current_year ? (float) $a->isa_subscription_current_year : null, 'include_in_retirement' => (bool) $a->include_in_retirement], $fields);
            })->toArray();
            break;
        case 'dc_pension':
            $items = \App\Models\DCPension::where('user_id', $userId)->get();
            $records = $items->map(fn ($p) => ['id' => $p->id, 'scheme_name' => $p->scheme_name, 'pension_type' => $p->pension_type, 'provider' => $p->provider, 'current_value' => (float) $p->current_fund_value, 'employee_contribution' => (float) ($p->employee_contribution_percent ?? 0), 'employer_contribution' => (float) ($p->employer_contribution_percent ?? 0), 'employer_matching_limit' => $p->employer_matching_limit ? (float) $p->employer_matching_limit : null, 'monthly_contribution' => $p->monthly_contribution_amount ? (float) $p->monthly_contribution_amount : null, 'platform_fee_percent' => $p->platform_fee_percent ? (float) $p->platform_fee_percent : null, 'retirement_age' => $p->retirement_age, 'projected_value_at_retirement' => $p->projected_value_at_retirement ? (float) $p->projected_value_at_retirement : null, 'has_flexibly_accessed' => (bool) $p->has_flexibly_accessed])->toArray();
            break;
        case 'db_pension':
            $items = \App\Models\DBPension::where('user_id', $userId)->get();
            $records = $items->map(fn ($p) => ['id' => $p->id, 'scheme_name' => $p->scheme_name, 'scheme_type' => $p->scheme_type, 'annual_pension' => (float) ($p->accrued_annual_pension ?? 0), 'service_years' => $p->pensionable_service_years, 'pensionable_salary' => $p->pensionable_salary ? (float) $p->pensionable_salary : null, 'normal_retirement_age' => $p->normal_retirement_age, 'spouse_pension_percent' => $p->spouse_pension_percent ? (float) $p->spouse_pension_percent : null, 'lump_sum_entitlement' => $p->lump_sum_entitlement ? (float) $p->lump_sum_entitlement : null, 'inflation_protection' => $p->inflation_protection])->toArray();
            break;
        case 'property':
            $items = \App\Models\Property::with('mortgages')->where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
            $records = $items->map(function ($p) use ($ownershipFields) {
                $fields = $ownershipFields($p);
                $total = (float) $p->current_value;
                $mortgageTotal = (float) $p->mortgages->sum('outstanding_balance');
                if (isset($fields['your_share_percent']) && $fields['your_share_percent'] < 100) {
                    $pct = $fields['your_share_percent'] / 100;
                    $fields['total_value'] = $total;
                    $fields['your_share_value'] = round($total * $pct, 2);
                    if ($mortgageTotal > 0) {
                        $fields['total_mortgage'] = $mortgageTotal;
                        $fields['your_mortgage_share'] = round($mortgageTotal * $pct, 2);
                    }
                }

                // Embed mortgage detail so the model gets it from property queries
                $mortgages = $p->mortgages->map(fn ($m) => ['lender' => $m->lender_name, 'outstanding_balance' => (float) $m->outstanding_balance, 'interest_rate' => (float) ($m->interest_rate ?? 0), 'rate_type' => $m->rate_type, 'rate_fix_end_date' => $m->rate_fix_end_date?->format('Y-m-d'), 'monthly_payment' => (float) ($m->monthly_payment ?? 0), 'mortgage_type' => $m->mortgage_type, 'remaining_term_months' => $m->remaining_term_months])->toArray();

                return array_merge(['id' => $p->id, 'address' => $p->address_line_1, 'property_type' => $p->property_type, 'current_value' => $total, 'outstanding_mortgage' => $mortgageTotal, 'mortgages' => $mortgages], $fields);
            })->toArray();
            break;
        case 'mortgage':
            $items = \App\Models\Mortgage::whereHas('property', fn ($q) => $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId))->with('property')->get();
            $records = $items->map(fn ($m) => ['id' => $m->id, 'property' => $m->property->address_line_1 ?? 'Unknown', 'lender' => $m->lender_name, 'outstanding_balance' => (float) $m->outstanding_balance, 'interest_rate' => (float) ($m->interest_rate ?? 0), 'rate_type' => $m->rate_type, 'rate_fix_end_date' => $m->rate_fix_end_date?->format('Y-m-d'), 'monthly_payment' => (float) ($m->monthly_payment ?? 0), 'mortgage_type' => $m->mortgage_type, 'remaining_term_months' => $m->remaining_term_months, 'start_date' => $m->start_date?->format('Y-m-d'), 'maturity_date' => $m->maturity_date?->format('Y-m-d'), 'original_loan_amount' => (float) ($m->original_loan_amount ?? 0)])->toArray();
            break;
        case 'life_insurance':
            $items = \App\Models\LifeInsurancePolicy::where('user_id', $userId)->get();
            $records = $items->map(fn ($p) => ['id' => $p->id, 'provider' => $p->provider, 'type' => $p->policy_type, 'sum_assured' => (float) $p->sum_assured, 'premium' => (float) ($p->premium_amount ?? 0), 'premium_frequency' => $p->premium_frequency, 'policy_start_date' => $p->policy_start_date?->format('Y-m-d'), 'policy_end_date' => $p->policy_end_date?->format('Y-m-d'), 'policy_term_years' => $p->policy_term_years, 'in_trust' => (bool) $p->in_trust, 'is_mortgage_protection' => (bool) $p->is_mortgage_protection, 'joint_life' => (bool) $p->joint_life, 'ownership_type' => $p->ownership_type])->toArray();
            break;
        case 'critical_illness':
            $items = \App\Models\CriticalIllnessPolicy::where('user_id', $userId)->get();
            $records = $items->map(fn ($p) => ['id' => $p->id, 'provider' => $p->provider, 'policy_type' => $p->policy_type, 'sum_assured' => (float) $p->sum_assured, 'premium' => (float) ($p->premium_amount ?? 0), 'premium_frequency' => $p->premium_frequency, 'policy_start_date' => $p->policy_start_date?->format('Y-m-d'), 'policy_term_years' => $p->policy_term_years, 'ownership_type' => $p->ownership_type])->toArray();
            break;
        case 'income_protection':
            $items = \App\Models\IncomeProtectionPolicy::where('user_id', $userId)->get();
            $records = $items->map(fn ($p) => ['id' => $p->id, 'provider' => $p->provider, 'benefit_amount' => (float) $p->benefit_amount, 'benefit_frequency' => $p->benefit_frequency, 'premium' => (float) ($p->premium_amount ?? 0), 'premium_frequency' => $p->premium_frequency, 'deferred_period_weeks' => $p->deferred_period_weeks, 'policy_start_date' => $p->policy_start_date?->format('Y-m-d'), 'ownership_type' => $p->ownership_type])->toArray();
            break;
        case 'trust':
            $items = \App\Models\Estate\Trust::where('user_id', $userId)->get();
            $records = $items->map(fn ($t) => ['id' => $t->id, 'trust_name' => $t->trust_name, 'trust_type' => $t->trust_type, 'current_value' => (float) $t->current_value, 'initial_value' => $t->initial_value ? (float) $t->initial_value : null, 'creation_date' => $t->trust_creation_date?->format('Y-m-d'), 'settlor' => $t->settlor, 'beneficiaries' => $t->beneficiaries, 'trustees' => $t->trustees, 'purpose' => $t->purpose, 'is_relevant_property_trust' => (bool) $t->is_relevant_property_trust, 'retained_income_annual' => $t->retained_income_annual ? (float) $t->retained_income_annual : null, 'loan_amount' => $t->loan_amount ? (float) $t->loan_amount : null, 'is_active' => (bool) $t->is_active])->toArray();
            break;
        case 'business_interest':
            $items = \App\Models\BusinessInterest::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
            $records = $items->map(fn ($b) => array_merge(['id' => $b->id, 'business_name' => $b->business_name, 'business_type' => $b->business_type, 'estimated_value' => (float) $b->current_valuation, 'annual_revenue' => $b->annual_revenue ? (float) $b->annual_revenue : null, 'annual_profit' => $b->annual_profit ? (float) $b->annual_profit : null, 'annual_dividend_income' => $b->annual_dividend_income ? (float) $b->annual_dividend_income : null, 'trading_status' => $b->trading_status, 'employee_count' => $b->employee_count, 'acquisition_date' => $b->acquisition_date?->format('Y-m-d'), 'acquisition_cost' => $b->acquisition_cost ? (float) $b->acquisition_cost : null, 'bpr_eligible' => $b->bpr_eligible], $ownershipFields($b)))->toArray();
            break;
        case 'chattel':
            $items = \App\Models\Chattel::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
            $records = $items->map(fn ($c) => array_merge(['id' => $c->id, 'name' => $c->name, 'description' => $c->description, 'category' => $c->chattel_type, 'estimated_value' => (float) $c->current_value, 'purchase_price' => $c->purchase_price ? (float) $c->purchase_price : null, 'purchase_date' => $c->purchase_date?->format('Y-m-d'), 'make' => $c->make, 'model' => $c->model, 'year' => $c->year], $ownershipFields($c)))->toArray();
            break;
        case 'estate_liability':
            $items = \App\Models\Estate\Liability::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
            $records = $items->map(fn ($l) => array_merge(['id' => $l->id, 'liability_name' => $l->liability_name, 'type' => $l->liability_type, 'balance' => (float) $l->current_balance, 'interest_rate' => $l->interest_rate ? (float) $l->interest_rate : null, 'monthly_payment' => $l->monthly_payment ? (float) $l->monthly_payment : null, 'maturity_date' => $l->maturity_date?->format('Y-m-d'), 'is_priority_debt' => (bool) $l->is_priority_debt], $ownershipFields($l)))->toArray();
            break;
        case 'estate_gift':
            $items = \App\Models\Estate\Gift::where('user_id', $userId)->get();
            $records = $items->map(fn ($g) => ['id' => $g->id, 'recipient' => $g->recipient, 'gift_type' => $g->gift_type, 'value' => (float) $g->gift_value, 'date' => $g->gift_date?->format('Y-m-d'), 'status' => $g->status, 'taper_relief_applicable' => (bool) $g->taper_relief_applicable, 'notes' => $g->notes])->toArray();
            break;
        case 'family_member':
            $items = \App\Models\FamilyMember::where('user_id', $userId)->get();
            $records = $items->map(fn ($m) => ['id' => $m->id, 'name' => trim($m->first_name.' '.$m->last_name), 'relationship' => $m->relationship, 'age' => $m->date_of_birth ? now()->diffInYears($m->date_of_birth) : null, 'date_of_birth' => $m->date_of_birth?->format('Y-m-d'), 'gender' => $m->gender, 'annual_income' => $m->annual_income ? (float) $m->annual_income : null, 'is_dependent' => (bool) $m->is_dependent, 'education_status' => $m->education_status, 'receives_child_benefit' => (bool) $m->receives_child_benefit])->toArray();
            break;
        default:
            return ['error' => true, 'message' => "Unknown entity type: {$entityType}"];
    }

    return [
        'entity_type' => $entityType,
        'count' => count($records),
        'records' => $records,
    ];
}
```

---

## 3. `list_goals`

**Schema** (`AiToolDefinitions.php:107-115`):

```php
[
    'name' => 'list_goals',
    'description' => 'List all of the user\'s financial goals with their current progress, status, and IDs. Use this when the user asks about their goals, wants to see progress, or before updating/deleting a specific goal. This is a lightweight call — use it instead of get_module_analysis(goals) when you just need the goal list.',
    'parameters' => [
        'type' => 'object',
        'properties' => (object) [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1543-1580`):

```php
private function handleListGoals(User $user): array
{
    $goals = \App\Models\Goal::forUserOrJoint($user->id)
        ->orderByRaw("FIELD(status, 'active', 'paused', 'completed', 'abandoned')")
        ->orderBy('priority')
        ->get();

    if ($goals->isEmpty()) {
        return [
            'has_goals' => false,
            'count' => 0,
            'goals' => [],
            'message' => 'No goals set yet. You can create goals to track savings targets, house deposits, holidays, and more.',
        ];
    }

    return [
        'has_goals' => true,
        'count' => $goals->count(),
        'active_count' => $goals->where('status', 'active')->count(),
        'on_track_count' => $goals->filter(fn ($g) => $g->is_on_track)->count(),
        'goals' => $goals->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->goal_name,
            'type' => $g->goal_type,
            'status' => $g->status,
            'priority' => $g->priority,
            'target_amount' => round((float) $g->target_amount, 2),
            'current_amount' => round((float) $g->current_amount, 2),
            'remaining' => round(max(0, (float) $g->target_amount - (float) $g->current_amount), 2),
            'progress_percentage' => $g->progress_percentage,
            'is_on_track' => $g->is_on_track,
            'monthly_contribution' => round((float) ($g->monthly_contribution ?? 0), 2),
            'target_date' => $g->target_date?->format('Y-m-d'),
            'assigned_module' => $g->assigned_module,
        ])->toArray(),
    ];
}
```

---

## 4. `list_life_events`

**Schema** (`AiToolDefinitions.php:116-124`):

```php
[
    'name' => 'list_life_events',
    'description' => 'List all of the user\'s life events with dates, amounts, and IDs. Use this when the user asks about their life events, upcoming events, or before updating/deleting a specific event. This is a lightweight call — use it instead of get_module_analysis(goals) when you just need the event list.',
    'parameters' => [
        'type' => 'object',
        'properties' => (object) [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1582-1618`):

```php
private function handleListLifeEvents(User $user): array
{
    $events = \App\Models\LifeEvent::forUserOrJoint($user->id)
        ->orderBy('expected_date')
        ->get();

    if ($events->isEmpty()) {
        return [
            'has_events' => false,
            'count' => 0,
            'events' => [],
            'message' => 'No life events recorded. You can add upcoming events like weddings, property purchases, inheritance, or career changes to see how they affect your financial plan.',
        ];
    }

    $active = $events->whereIn('status', ['expected', 'confirmed']);
    $completed = $events->where('status', 'completed');

    return [
        'has_events' => true,
        'count' => $events->count(),
        'active_count' => $active->count(),
        'completed_count' => $completed->count(),
        'events' => $events->map(fn ($e) => [
            'id' => $e->id,
            'name' => $e->event_name,
            'type' => $e->event_type,
            'display_type' => $e->display_event_type,
            'status' => $e->status,
            'impact_type' => $e->impact_type,
            'amount' => round((float) $e->amount, 2),
            'expected_date' => $e->expected_date?->format('Y-m-d'),
            'months_until' => $e->expected_date ? max(0, (int) now()->diffInMonths($e->expected_date, false)) : null,
            'certainty' => $e->certainty,
        ])->toArray(),
    ];
}
```

---

## 5. `get_module_analysis`

**Schema** (`AiToolDefinitions.php:125-140`):

```php
[
    'name' => 'get_module_analysis',
    'description' => 'Get detailed financial analysis for a specific module. Returns personalised analysis based on the user\'s actual financial data.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'module' => [
                'type' => 'string',
                'enum' => ['protection', 'savings', 'investment', 'retirement', 'estate', 'goals', 'holistic'],
                'description' => 'The financial planning module to analyse',
            ],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1620-1654`):

```php
private function handleModuleAnalysis(array $input, User $user): array
{
    $module = $input['module'];

    $analyzeStart = microtime(true);
    $analysis = match ($module) {
        'protection' => $this->protectionAgent->analyze($user->id),
        'savings' => $this->savingsAgent->analyze($user->id),
        'investment' => $this->investmentAgent->analyze($user->id),
        'retirement' => $this->retirementAgent->analyze($user->id),
        'estate' => $this->estateAgent->analyze($user->id),
        'goals' => $this->goalsAgent->analyze($user->id),
        'holistic' => $this->orchestrateAnalysis($user->id),
        default => ['error' => "Unknown module: {$module}"],
    };
    $analyzeDuration = (int) round((microtime(true) - $analyzeStart) * 1000);

    // Eval trace — every module analyze invocation through this tool
    // gets one EngineCalled. result_path inferred from response shape:
    // success_false when the agent returns ['success' => false, ...],
    // happy otherwise.
    $resultPath = (isset($analysis['success']) && $analysis['success'] === false) ? 'success_false' : 'happy';
    event(new \App\Events\Eval\EngineCalled(
        engine: $module === 'holistic' ? 'orchestrate_analysis' : "{$module}_analysis",
        params: ['user_id' => $user->id, 'module' => $module],
        resultSummary: [
            'keys_returned' => array_keys($analysis),
            'result_path' => $resultPath,
        ],
        durationMs: $analyzeDuration,
        atMicrotime: microtime(true),
    ));

    return $this->summariseToolAnalysis($module, $analysis);
}
```

**Result post-processing**: result is passed through `summariseToolAnalysis` (`CoordinatingAgent.php:3512-3535`) which validates against `App\Services\AI\ToolResultContract` — see `09-shared-helpers.md`.

---

## 6. `get_recommendations`

**Schema** (`AiToolDefinitions.php:141-149`):

```php
[
    'name' => 'get_recommendations',
    'description' => 'Get the user\'s personalised financial recommendations ranked by priority across all modules.',
    'parameters' => [
        'type' => 'object',
        'properties' => (object) [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1744-1753`):

```php
private function handleRecommendations(User $user): array
{
    $analysis = $this->orchestrateAnalysis($user->id);

    return [
        'recommendations' => $analysis['ranked_recommendations'] ?? [],
        'total' => count($analysis['ranked_recommendations'] ?? []),
        'surplus' => $analysis['available_surplus'] ?? 0,
    ];
}
```

---

## 7. `search_conversation_index`

**Schema** (`AiToolDefinitions.php:150-170`):

```php
[
    'name' => 'search_conversation_index',
    'description' => 'Search the user\'s prior conversations for context on a topic or entity. Returns up to 10 prior conversations matching the supplied keywords/entity types, ordered by recency. Use ONLY when the `<known_facts>` block is silent on the field you need and you need to know what the user has discussed in earlier sessions (e.g. they say "as we talked about last time" — search for the relevant topic to recover the thread). Do NOT use this as a substitute for list_records or get_module_analysis — those return current authoritative data; this returns historical conversational context.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'topic_keywords' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Module-level topic tags to match against the conversation index `topics` field. Allowed values: protection, savings, investment, retirement, estate_planning, goals_life_events, tax_optimisation, family, property, mortgage, billing, general.',
            ],
            'entity_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Entity types to match against the `entities_mentioned` field. Allowed values: life_insurance_policy, dc_pension, db_pension, isa, gia, savings_account, property, mortgage, credit_card, family_member, goal, life_event, will, trust, business_interest, chattel.',
            ],
        ],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1673-1721`):

```php
private function handleSearchConversationIndex(array $input, User $user, ?int $activeConversationId = null): array
{
    $topicKeywords = array_values(array_filter(
        (array) ($input['topic_keywords'] ?? []),
        fn ($v) => is_string($v) && $v !== ''
    ));

    $entityTypes = array_values(array_filter(
        (array) ($input['entity_types'] ?? []),
        fn ($v) => is_string($v) && $v !== ''
    ));

    $query = \App\Models\AiConversation::query()
        ->where('user_id', $user->id)
        ->whereNotNull('summary');

    if ($activeConversationId !== null) {
        $query->where('id', '!=', $activeConversationId);
    }

    if ($topicKeywords !== [] || $entityTypes !== []) {
        $query->where(function ($outer) use ($topicKeywords, $entityTypes): void {
            foreach ($topicKeywords as $topic) {
                $outer->orWhereJsonContains('topics', $topic);
            }
            foreach ($entityTypes as $entityType) {
                $outer->orWhereJsonContains('entities_mentioned', ['type' => $entityType]);
            }
        });
    }

    $rows = $query
        ->orderByDesc('last_message_at')
        ->limit(10)
        ->get(['id', 'title', 'summary', 'topics', 'intents_stated', 'entities_mentioned', 'last_message_at']);

    return [
        'count' => $rows->count(),
        'conversations' => $rows->map(fn ($row) => [
            'id' => $row->id,
            'title' => $row->title,
            'summary' => $row->summary,
            'topics' => $row->topics ?? [],
            'intents_stated' => $row->intents_stated ?? [],
            'entities_mentioned' => $row->entities_mentioned ?? [],
            'last_message_at' => $row->last_message_at?->toIso8601String(),
        ])->all(),
    ];
}
```

---

## 8. `get_tax_information`

**Schema** (`AiToolDefinitions.php:177-200`):

```php
[
    'name' => 'get_tax_information',
    'description' => 'Get current UK tax year information for a specific topic. ALWAYS use this tool when the user asks about tax thresholds, allowances, rates, or any financial product tax treatment. Never state tax values from memory — always retrieve them. Use income_definitions to get the user\'s detailed income breakdown including adjusted net income, threshold income, and tapered pension allowances.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'topic' => [
                'type' => 'string',
                'enum' => [
                    'income_tax', 'national_insurance', 'capital_gains', 'dividend_tax',
                    'inheritance_tax', 'gifting_exemptions', 'stamp_duty',
                    'isa_allowances', 'pension_allowances', 'state_pension',
                    'benefits', 'savings_config', 'assumptions',
                    'investment_bonds', 'venture_capital',
                    'protection_config', 'retirement_config', 'domicile',
                    'income_definitions',
                ],
                'description' => 'The tax or financial configuration topic to retrieve. Use income_definitions for the user\'s adjusted net income, threshold income, and tapered allowances.',
            ],
        ],
        'required' => ['topic'],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1847-1888`):

```php
private function handleTaxInformation(array $input, User $user): array
{
    $topic = $input['topic'];

    // income_definitions is per-user (not cacheable globally)
    if ($topic === 'income_definitions') {
        return Cache::remember("ai_income_defs_{$user->id}", 120, function () use ($user) {
            $incomeService = app(\App\Services\Tax\IncomeDefinitionsService::class);

            return $incomeService->calculate($user->id);
        });
    }

    // Cache tax config lookups for 5 minutes to save token cost on repeated queries
    return Cache::remember("ai_tax_info_{$topic}", 300, function () use ($topic) {
        return match ($topic) {
            'income_tax' => $this->taxConfig->getIncomeTax(),
            'national_insurance' => $this->taxConfig->getNationalInsurance(),
            'capital_gains' => $this->taxConfig->getCapitalGainsTax(),
            'dividend_tax' => $this->taxConfig->getDividendTax(),
            'inheritance_tax' => $this->taxConfig->getInheritanceTax(),
            'gifting_exemptions' => $this->taxConfig->getGiftingExemptions(),
            'stamp_duty' => $this->taxConfig->getStampDuty(),
            'isa_allowances' => $this->taxConfig->getISAAllowances(),
            'pension_allowances' => $this->taxConfig->getPensionAllowances(),
            'state_pension' => $this->taxConfig->get('pension.state_pension', []),
            'benefits' => $this->taxConfig->getBenefits(),
            'savings_config' => $this->taxConfig->getSavingsConfig(),
            'assumptions' => $this->taxConfig->getAssumptions(),
            'investment_bonds' => [
                'onshore_bond_minimum' => $this->taxConfig->get('investment.waterfall.onshore_bond_minimum'),
                'offshore_bond_minimum' => $this->taxConfig->get('investment.waterfall.offshore_bond_minimum'),
                'tax_treatment' => 'Onshore bonds have 20% tax credit, 5% annual tax-deferred withdrawals, and top-slicing relief. Offshore bonds have gross roll-up with no tax credit, same 5% withdrawals, and time apportionment relief.',
            ],
            'venture_capital' => $this->taxConfig->get('investment.venture_capital', []),
            'protection_config' => $this->taxConfig->getProtectionConfig(),
            'retirement_config' => $this->taxConfig->getRetirementConfig(),
            'domicile' => $this->taxConfig->getDomicile(),
            default => ['error' => "Unknown tax topic: {$topic}"],
        };
    });
}
```

---

## 9. `generate_financial_plan`

**Schema** (`AiToolDefinitions.php:206-216`):

```php
[
    'name' => 'generate_financial_plan',
    'description' => 'Generate a comprehensive holistic financial plan for the user. Analyses all modules (protection, savings, investment, retirement, estate, goals) and returns an executive summary, top recommendations, overall score, and action plan. Use this when the user asks for a financial plan, overview of their position, or wants to know what they should prioritise.',
    'parameters' => [
        'type' => 'object',
        'properties' => (object) [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1890-1916`):

```php
private function handleFinancialPlan(User $user): array
{
    $plan = $this->generateHolisticPlan($user->id);

    $summary = [];

    if (isset($plan['executive_summary'])) {
        $summary['executive_summary'] = $plan['executive_summary'];
    }

    $recommendations = $plan['ranked_recommendations'] ?? $plan['recommendations'] ?? [];
    $summary['top_recommendations'] = array_slice($recommendations, 0, 5);

    if (isset($plan['action_plan'])) {
        $summary['action_plan'] = array_slice($plan['action_plan'], 0, 5);
    }

    if (isset($plan['available_surplus'])) {
        $summary['monthly_surplus'] = $plan['available_surplus'];
    }

    if (isset($plan['cashflow_allocation'])) {
        $summary['suggested_allocation'] = $plan['cashflow_allocation'];
    }

    return $summary;
}
```

---

## 10. `get_subscription_status`

**Schema** (`AiToolDefinitions.php:1578-1588`):

```php
[
    'name' => 'get_subscription_status',
    'description' => 'Get the user\'s current subscription status — plan, billing cycle, current period end, trial end, next charge, and whether they have cancelled. Use when the user asks about their subscription, billing, when they will be charged next, whether their trial has ended, or whether their subscription is still active.',
    'parameters' => [
        'type' => 'object',
        'properties' => (object) [],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1764-1795`, plus `resolveSubscription` helper at :1759):

```php
/**
 * Resolve the user's current subscription. Read-only — returns null if absent.
 * Mirrors the controller-side resolution so chat-tool callers see the same row.
 */
private function resolveSubscription(User $user): ?\App\Models\Subscription
{
    return $user->subscription()->latest('id')->first();
}

private function handleGetSubscriptionStatus(User $user): array
{
    $sub = $this->resolveSubscription($user);

    if (! $sub) {
        return ['status' => 'none'];
    }

    $plan = SubscriptionPlan::findBySlug($sub->plan);

    // S0.5.u (BS-16): when the user has any real subscription (active,
    // trialing, paused, or cancelled) we surface the Subscription
    // Management page so they can act on the answer. HasAiChat::stream
    // turns this into a `navigation` SSE event consumed by
    // AiChatPanel; the user lands on /settings/subscription where
    // their invoices and billing details are managed. INV-2.7.2 only
    // mandates parity of the read tools, not their result shape, so
    // BillingToolsTest::list_invoices stays green (extra keys are
    // accepted by toHaveKeys).
    return [
        'status' => $sub->status,
        'plan_name' => $plan?->name ?? ucfirst((string) $sub->plan),
        'billing_cycle' => $sub->billing_cycle,
        'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
        'current_period_end' => $sub->current_period_end?->toIso8601String(),
        'next_charge_amount' => round((float) $sub->amount, 2),
        'is_cancelled' => $sub->cancelled_at !== null,
        'action' => 'navigate',
        'route_path' => '/settings/subscription',
        'description' => 'View your subscription and invoices',
    ];
}
```

---

## 11. `list_invoices`

**Schema** (`AiToolDefinitions.php:1589-1598`):

```php
[
    'name' => 'list_invoices',
    'description' => 'List the user\'s invoices in reverse chronological order (most recent first). Each row includes the invoice number, issued date, amount in pounds, currency, status, plan name, billing cycle, and a PDF download URL. Use when the user asks for their billing history, past invoices, or wants to download a receipt.',
    'parameters' => [
        'type' => 'object',
        'properties' => (object) [],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1797-1816`):

```php
private function handleListInvoices(User $user): array
{
    return Invoice::query()
        ->where('user_id', $user->id)
        ->orderByDesc('issued_at')
        ->orderByDesc('id')
        ->get()
        ->map(fn (Invoice $i) => [
            'invoice_id' => $i->id,
            'invoice_number' => $i->invoice_number,
            'issued_at' => $i->issued_at?->toIso8601String(),
            'amount' => round($i->total_amount / 100, 2),
            'currency' => $i->currency ?? 'GBP',
            'status' => $i->status,
            'plan_name' => $i->plan_name,
            'billing_cycle' => $i->billing_cycle,
            'pdf_url' => '/api/payment/invoices/'.$i->id.'/download',
        ])
        ->all();
}
```

---

## 12. `get_current_plan`

**Schema** (`AiToolDefinitions.php:1599-1608`):

```php
[
    'name' => 'get_current_plan',
    'description' => 'Get the details of the user\'s current subscription plan — name, tier slug, billing cycle, price in pounds, and the list of features included. Use when the user asks what plan they are on, what features they have, or what they are paying.',
    'parameters' => [
        'type' => 'object',
        'properties' => (object) [],
        'required' => [],
        'additionalProperties' => false,
    ],
],
```

**Handler** (`CoordinatingAgent.php:1818-1845`):

```php
private function handleGetCurrentPlan(User $user): array
{
    $sub = $this->resolveSubscription($user);

    if (! $sub) {
        return [
            'plan_name' => 'none',
            'tier' => 'none',
            'billing_cycle' => null,
            'price_gbp' => 0.0,
            'features' => [],
        ];
    }

    $plan = SubscriptionPlan::findBySlug($sub->plan);

    $pricePence = $plan
        ? ($plan->getLaunchPriceForCycle($sub->billing_cycle) ?? $plan->getPriceForCycle($sub->billing_cycle))
        : (int) round(((float) $sub->amount) * 100);

    return [
        'plan_name' => $plan?->name ?? ucfirst((string) $sub->plan),
        'tier' => $sub->plan,
        'billing_cycle' => $sub->billing_cycle,
        'price_gbp' => round($pricePence / 100, 2),
        'features' => $plan?->features ?? [],
    ];
}
```
