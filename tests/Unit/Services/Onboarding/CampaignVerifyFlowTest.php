<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\SavingsAccount;
use App\Models\StatePension;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\Cache;

it('maps every navigable campaign section to a route and capture-entry state', function (): void {
    $config = OnboardingStateMachine::campaignVerifyConfig();

    // Every section navigates to its screen for the confirm.
    expect($config['income']['route'])->toBe('/income')
        ->and($config['income']['entry'])->toBe(OnboardingStateMachine::STATE_BASE_EMPLOYMENT)
        ->and($config['savings']['route'])->toBe('/savings')
        ->and($config['investments']['route'])->toBe('/investment')
        ->and($config['pensions']['route'])->toBe('/retirement')
        ->and($config['spouse']['route'])->toBe('/income')
        ->and($config['expenditure']['route'])->toBe('/expenditure');
});

it('stamps the verify section into context and goes straight to navigate/confirm', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => [],
    ]);

    // Announce-before-navigate: enter the Okay gate first, not navigate directly.
    $next = OnboardingStateMachine::enterCampaignVerify($user, 'savings');

    expect($next)->toBe('campaign_verify_announce')
        ->and($user->fresh()->onboarding_fyn_context['verify_section'])->toBe('savings');
});

it('routes verify_more yes back to the section entry and no to navigate', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => ['verify_section' => 'savings'],
    ]);

    expect(OnboardingStateMachine::nextFromVerifyMore('yes', $user))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS)
        ->and(OnboardingStateMachine::nextFromVerifyMore('no', $user))
        ->toBe('campaign_verify_navigate');
});

it('routes verify_navigate no to edit and yes to the section advice', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => ['verify_section' => 'pensions'],
        'marital_status' => 'single',
    ]);

    expect(OnboardingStateMachine::nextFromVerifyNavigate('no', $user))
        ->toBe('campaign_verify_edit');
    // Confirmed → the section's advice turn fires (before the next section).
    expect(OnboardingStateMachine::nextFromVerifyNavigate('yes', $user))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_PENSIONS);
});

it('defines the three generic verify states with the right turn types', function (): void {
    $m = new ReflectionMethod(OnboardingStateMachine::class, 'inCodeStates');
    $m->setAccessible(true);
    $states = $m->invoke(null);

    expect($states)->toHaveKeys(['campaign_verify_announce', 'campaign_verify_more', 'campaign_verify_navigate', 'campaign_verify_edit'])
        ->and($states['campaign_verify_announce']['turn_type'])->toBe('bubbles')
        ->and($states['campaign_verify_more']['turn_type'])->toBe('bubbles')
        ->and($states['campaign_verify_navigate']['turn_type'])->toBe('bubbles')
        ->and($states['campaign_verify_edit']['turn_type'])->toBe('delegated')
        ->and($states['campaign_verify_navigate']['navigate_to'])->toBeInstanceOf(Closure::class)
        // The announce step must NOT navigate — that's the whole point of the gate.
        ->and($states['campaign_verify_announce']['navigate_to'] ?? null)->toBeNull();
});

it('narrows xAI verify-edit tools to the current profile section and record ids', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Cache::put('ai_provider', 'xai');
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'verifyEditToolDefinitions');
    $method->setAccessible(true);
    $user = User::factory()->create(['is_preview_user' => false]);
    TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'spouse_annual_income' => 35000,
    ]);
    $reviewed = SavingsAccount::factory()->create(['user_id' => $user->id]);

    $spouseTools = $method->invoke($director, $user, 'spouse', 'Change my spouse income to £38,000.');
    $spouseTool = collect($spouseTools)->firstWhere('function.name', 'update_profile');
    $savingsTools = $method->invoke($director, $user, 'savings', 'Change the balance to £13,250.');
    $savingsTool = collect($savingsTools)->firstWhere('function.name', 'update_record');

    expect($spouseTools)->toHaveCount(1)
        ->and($spouseTool['function']['parameters']['properties']['section']['enum'])
        ->toBe(['spouse_household'])
        ->and($spouseTool['function']['description'])->not->toContain('NEVER use this for any expenditure')
        ->and($savingsTools)->toHaveCount(1)
        ->and($savingsTool['function']['parameters']['properties']['entity_type']['enum'])
        ->toBe(['savings_account'])
        ->and($savingsTool['function']['parameters']['properties']['entity_id']['enum'])
        ->toBe([$reviewed->id]);
});

it('requires every user-requested field in the narrowed verify-edit tool schema', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Cache::put('ai_provider', 'xai');
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'verifyEditToolDefinitions');
    $method->setAccessible(true);
    $user = User::factory()->create(['is_preview_user' => false]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'is_isa' => true,
        'isa_type' => 'cash',
    ]);
    StatePension::factory()->create(['user_id' => $user->id]);

    $recordTools = $method->invoke(
        $director,
        $user,
        'savings',
        'Set the balance to £20,000, subscriptions this tax year to £5,000, and interest rate to 4.2%.'
    );
    $recordTool = collect($recordTools)->firstWhere('function.name', 'update_record');
    $profileTools = $method->invoke(
        $director,
        $user,
        'income',
        'Set my employment income to £82,000 and my self-employed income to £5,000.'
    );
    $profileTool = collect($profileTools)->firstWhere('function.name', 'update_profile');
    $captureTools = $method->invoke(
        $director,
        $user,
        'state_pension',
        'Set my forecast to £12,000 a year and my qualifying years to 30.'
    );
    $captureTool = collect($captureTools)->firstWhere('function.name', 'capture_state_pension');

    expect($recordTool['function']['parameters']['properties']['fields']['required'])
        ->toEqualCanonicalizing([
            'current_balance',
            'interest_rate',
            'isa_subscription_amount',
        ])
        ->and($profileTool['function']['parameters']['properties']['fields']['required'])
        ->toEqualCanonicalizing([
            'annual_employment_income',
            'annual_self_employment_income',
        ])
        ->and($captureTool['function']['parameters']['required'])
        ->toEqualCanonicalizing([
            'forecast_annual',
            'ni_years_completed',
        ]);
});

it('offers no record-update tool when the reviewed section has no existing records', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Cache::put('ai_provider', 'xai');
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'verifyEditToolDefinitions');
    $method->setAccessible(true);
    $user = User::factory()->create(['is_preview_user' => false]);

    expect($method->invoke($director, $user, 'investments', 'Change the value to £50,000.'))->toBe([]);
});

it('derives a field-level verify scope from the correction message', function (): void {
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'verifyEditScope');
    $method->setAccessible(true);
    $user = User::factory()->create(['is_preview_user' => false]);
    SavingsAccount::factory()->create(['user_id' => $user->id]);

    $income = $method->invoke($director, $user, 'income', 'Change my salary to £82,000.');
    $savings = $method->invoke($director, $user, 'savings', 'The balance should be £13,250, not the interest rate.');

    expect($income['profile_fields']['income_occupation'])->toBe(['annual_employment_income'])
        ->and($savings['record_fields']['savings_account'])->toBe(['current_balance']);
});

it('does not widen a savings field scope from words inside the selected record label', function (): void {
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'verifyEditScope');
    $method->setAccessible(true);
    $user = User::factory()->create(['is_preview_user' => false]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'Everyday Saver',
        'institution' => 'Halifax Bank',
    ]);

    $scope = $method->invoke($director, $user, 'savings', 'Change my Halifax Bank balance to £13,250.');

    expect($scope['record_fields']['savings_account'])->toBe(['current_balance']);
});

it('does not match employed fields inside a self-employed correction', function (): void {
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'verifyEditScope');
    $method->setAccessible(true);
    $user = User::factory()->create(['is_preview_user' => false]);

    $scope = $method->invoke($director, $user, 'income', 'My self-employed income should be £40,000.');

    expect($scope['profile_fields']['income_occupation'])->toBe(['annual_self_employment_income']);
});

it('keeps the corrected field after a negated old value', function (): void {
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'verifyEditScope');
    $method->setAccessible(true);
    $user = User::factory()->create(['is_preview_user' => false]);

    $scope = $method->invoke($director, $user, 'income', 'It is not £30,000; my self-employed income is £40,000.');

    expect($scope['profile_fields']['income_occupation'])->toBe(['annual_self_employment_income']);
});

it('arms only fields that are actually shown on the campaign recap', function (): void {
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'verifyEditScope');
    $method->setAccessible(true);
    $user = User::factory()->create(['is_preview_user' => false]);
    DCPension::factory()->create(['user_id' => $user->id]);

    $hiddenIncome = $method->invoke($director, $user, 'recap', 'Change my dividend income to £5,000.');
    $hiddenPersonal = $method->invoke($director, $user, 'recap', 'Change my date of birth to 1 January 1980.');
    $visiblePension = $method->invoke($director, $user, 'recap', 'Change my pension pot value to £50,000.');

    expect($hiddenIncome['tools'])->toBe([])
        ->and($hiddenPersonal['tools'])->toBe([])
        ->and($visiblePension['record_fields']['dc_pension'])->toBe(['current_fund_value']);
});

it('provides update tools for State Pension and retirement-goal review corrections', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Cache::put('ai_provider', 'xai');
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'verifyEditToolDefinitions');
    $method->setAccessible(true);
    $user = User::factory()->create(['is_preview_user' => false]);
    StatePension::factory()->create(['user_id' => $user->id]);
    RetirementProfile::create([
        'user_id' => $user->id,
        'current_age' => 40,
        'target_retirement_age' => 67,
        'target_retirement_income' => 28000,
    ]);

    $statePensionTools = $method->invoke($director, $user, 'state_pension', 'My forecast is £12,000 a year.');
    $goalTools = $method->invoke($director, $user, 'retirement_goals', 'I want to retire at 65.');

    expect(collect($statePensionTools)->pluck('function.name'))->toContain('capture_state_pension')
        ->and(collect($goalTools)->pluck('function.name'))->toContain('capture_retirement_goals');
});

it('uses the versioned provider snapshot for verify-edit tool schemas', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Cache::put('ai_provider', 'anthropic');
    Cache::put('ai_provider_version', 7);
    Cache::put('ai_provider:v7', 'xai');
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'verifyEditToolDefinitions');
    $method->setAccessible(true);
    $user = User::factory()->create(['is_preview_user' => false]);

    $tools = $method->invoke($director, $user, 'income', 'Change my salary to £82,000.');

    expect($tools)->not->toBeEmpty()
        ->and($tools[0])->toHaveKey('function');
});

it('reads back a changed defined benefit pension after a verify edit', function (): void {
    $director = app(OnboardingChatDirector::class);
    $snapshot = new ReflectionMethod($director, 'verifyEditSnapshot');
    $snapshot->setAccessible(true);
    $readBack = new ReflectionMethod($director, 'verifyEditReadBack');
    $readBack->setAccessible(true);
    $user = User::factory()->create(['is_preview_user' => false]);
    $pension = DBPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'NHS Pension',
        'accrued_annual_pension' => 12000,
    ]);

    $before = $snapshot->invoke($director, $user, 'pensions');
    $pension->update(['accrued_annual_pension' => 14000]);
    $text = $readBack->invoke($director, $user, 'pensions', $before);

    expect($text)->toContain('NHS Pension')
        ->and($text)->toContain('£14,000');
});

it('reads an idempotent verify edit back from the database instead of model prose', function (): void {
    $director = app(OnboardingChatDirector::class);
    $scopeMethod = new ReflectionMethod($director, 'verifyEditScope');
    $scopeMethod->setAccessible(true);
    $snapshot = new ReflectionMethod($director, 'verifyEditFieldSnapshot');
    $snapshot->setAccessible(true);
    $readBack = new ReflectionMethod($director, 'verifyEditFieldReadBack');
    $readBack->setAccessible(true);
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_employment_income' => 82000,
    ]);
    $scope = $scopeMethod->invoke($director, $user, 'income', 'Change my salary to £82,000.');
    $before = $snapshot->invoke($director, $user, $scope);

    $text = $readBack->invoke($director, $user, $scope, $before);

    expect($text)->not->toBeNull()
        ->and($text)->toContain('£82,000');
});

it('routes each section CAPTURE-end straight into navigate/confirm (no extra gate)', function (): void {
    $m = new ReflectionMethod(OnboardingStateMachine::class, 'inCodeStates');
    $m->setAccessible(true);
    $states = $m->invoke(null);
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_context' => [],
        'marital_status' => 'married',
    ]);

    // Capture-ends enter the announce gate (Okay → navigate/confirm) — never the
    // (now removed) redundant "anything else?" verify gate.
    foreach ([
        OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS,
        OnboardingStateMachine::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS,
        OnboardingStateMachine::STATE_CAMPAIGN_PENSION_CONTRIBS,
        OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD,
    ] as $stateId) {
        $next = $states[$stateId]['next'];
        $resolved = is_callable($next) ? $next('', $user) : $next;
        expect($resolved)->toBe('campaign_verify_announce', "state {$stateId} should enter the announce gate");
    }

    // Advice now fires AFTER the confirm and continues to the next section —
    // never back into the verify flow.
    foreach ([
        OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_INCOME,
        OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_SAVINGS,
    ] as $stateId) {
        $next = $states[$stateId]['next'];
        $resolved = is_callable($next) ? $next('', $user) : $next;
        expect($resolved)->not->toBe('campaign_verify_navigate')
            ->and($resolved)->not->toBe('campaign_verify_more');
    }
});

it('emits a navigation event for a navigable verify section and none for giving', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $director = app(OnboardingChatDirector::class);

    $emit = new ReflectionMethod($director, 'emitTurnForState');
    $emit->setAccessible(true);

    foreach ([['savings', true], ['giving', false]] as [$section, $expectNav]) {
        $user = User::factory()->create([
            'onboarding_fyn_path' => 'campaign', 'onboarding_completed' => false,
            'onboarding_fyn_step' => 'campaign_verify_navigate',
            'onboarding_fyn_context' => ['verify_section' => $section],
        ]);
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $state = OnboardingStateMachine::getState('campaign_verify_navigate');
        $events = iterator_to_array($emit->invoke($director, $user, $conversation, 'campaign_verify_navigate', $state), false);
        $types = array_column($events, 'type');

        expect(in_array('navigation', $types, true))->toBe($expectNav, "section {$section}");
        expect($types)->toContain('quick_replies');
    }
});

// D1 Fix 5 — the navigation event carried 'description' => $stateId, and the web
// store renders a navigation message's content from event.description, so the raw
// state id "campaign_verify_navigate" leaked into the chat as a plain text bubble.
// The navigation event must not carry the internal state id in a user-facing field.
it('navigation event does not leak the internal state id in its description', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $director = app(OnboardingChatDirector::class);

    $emit = new ReflectionMethod($director, 'emitTurnForState');
    $emit->setAccessible(true);

    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign', 'onboarding_completed' => false,
        'onboarding_fyn_step' => 'campaign_verify_navigate',
        'onboarding_fyn_context' => ['verify_section' => 'savings'],
    ]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $state = OnboardingStateMachine::getState('campaign_verify_navigate');
    $events = iterator_to_array($emit->invoke($director, $user, $conversation, 'campaign_verify_navigate', $state), false);

    $nav = collect($events)->firstWhere('type', 'navigation');
    expect($nav)->not->toBeNull();
    expect($nav['description'] ?? '')->not->toContain('campaign_verify_navigate');
    expect($nav['description'] ?? '')->not->toContain('campaign_verify');
    // The route + section still ride the event for the surfaces that navigate.
    expect($nav['route_path'])->toBe('/savings');
});
