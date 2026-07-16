<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\DCPension;
use App\Models\Goal;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\TaxConfigService;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
    TaxConfiguration::factory()->create(['is_active' => true]);
    app()->forgetInstance(TaxConfigService::class);
});

it('keeps the at-cap reply accurate without a paid action when payments are disabled', function () {
    config(['app.payment_enabled' => false]);
    $user = User::factory()->create(['tier' => 'free']);
    Goal::factory()->count(2)->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('create_goal', [
        'name' => 'Third Goal',
        'goal_type' => 'holiday',
        'target_amount' => 5000,
        'target_date' => '2028-01-01',
        'priority' => 'medium',
    ], $user);

    expect($result)->toMatchArray([
        'error' => true,
        'error_type' => 'tier_limit_reached',
        'entity_key' => 'goal',
        'current_count' => 2,
        'hard_limit' => 2,
    ])->not->toHaveKeys(['action', 'reason'])
        ->and($result['message'])->not->toContain('upgrade your plan');
});

it('streams and persists the subscription action after the accurate Fyn reply', function () {
    $user = User::factory()->create([
        'tier' => 'free',
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    Goal::factory()->count(2)->create(['user_id' => $user->id]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'At-cap action',
        'message_count' => 0,
    ]);

    FynStreamHarness::fake()
        ->toolTurn('create_goal', [
            'name' => 'Third Goal',
            'goal_type' => 'holiday',
            'target_amount' => 5000,
            'target_date' => '2028-01-01',
            'priority' => 'medium',
        ])
        ->textTurn("You've reached your plan's limit of 2 goals. To add more, upgrade your plan.")
        ->bind();

    $events = iterator_to_array(
        app(CoordinatingAgent::class)->chatWithPromptOverride(
            $user,
            $conversation,
            'Add a third goal',
            null,
            null,
            ['create_goal'],
            true,
            null,
            'data_capture',
        ),
        preserve_keys: false,
    );

    $actionIndex = collect($events)->search(fn (array $event): bool => ($event['type'] ?? null) === 'action');
    $lastContentIndex = collect($events)->keys()->filter(
        fn (int $index): bool => ($events[$index]['type'] ?? null) === 'content'
    )->last();

    expect($events[$actionIndex])->toMatchArray([
        'type' => 'action',
        'action' => 'subscription_options',
        'reason' => 'tier_limit_reached',
        'entity_key' => 'goal',
        'current_count' => 2,
        'limit' => 2,
        'tier' => 'free',
    ])->and($actionIndex)->toBeGreaterThan($lastContentIndex);

    $assistant = $conversation->messages()->where('role', 'assistant')->latest('id')->firstOrFail();
    expect($assistant->metadata['actions'][0])->toMatchArray([
        'action' => 'subscription_options',
        'reason' => 'tier_limit_reached',
        'entity_key' => 'goal',
        'limit' => 2,
        'tier' => 'free',
    ]);
});

it('returns a presentation-neutral subscription action when Fyn reaches a create cap', function (string $tool, array $input, string $model) {
    $user = User::factory()->create(['tier' => 'free']);
    $model::factory()->count(2)->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool($tool, $input, $user);

    expect($result)->toMatchArray([
        'error' => true,
        'error_type' => 'tier_limit_reached',
        'entity_key' => $model === Goal::class ? 'goal' : 'pension_account',
        'current_count' => 2,
        'hard_limit' => 2,
        'required_tier' => 'premium',
        'action' => 'subscription_options',
        'reason' => 'tier_limit_reached',
        'limit' => 2,
        'tier' => 'free',
    ])->and($result['message'])->toContain('upgrade your plan');
})->with([
    'Goal' => [
        'create_goal',
        [
            'name' => 'Third Goal',
            'goal_type' => 'holiday',
            'target_amount' => 5000,
            'target_date' => '2028-01-01',
            'priority' => 'medium',
        ],
        Goal::class,
    ],
    'Pension' => [
        'create_pension',
        [
            'pension_category' => 'dc',
            'scheme_name' => 'Third Pension',
            'current_fund_value' => 10000,
        ],
        DCPension::class,
    ],
]);
