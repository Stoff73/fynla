<?php

declare(strict_types=1);

use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\DisabilityPolicy;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\SicknessIllnessPolicy;
use App\Models\StatePension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function contextualConversationPayload(array $overrides = []): array
{
    $payload = array_replace_recursive([
        'action' => 'edit',
        'resource_type' => 'savings_account',
        'resource_id' => 8472,
        'current_destination' => [
            'screen' => 'savings_account_detail',
            'params' => ['account_id' => 8472],
            'fallback' => 'savings',
        ],
        'origin' => [
            'kind' => 'surface_action',
            'recommendation_id' => null,
        ],
    ], $overrides);

    if (array_key_exists('params', $overrides['current_destination'] ?? [])) {
        $payload['current_destination']['params'] = $overrides['current_destination']['params'];
    }

    return $payload;
}

it('requires authentication for contextual conversation creation', function (): void {
    $this->postJson('/api/ai-chat/contextual-conversations', contextualConversationPayload())
        ->assertUnauthorized();
});

it('rejects actions outside the contextual allowlist', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/ai-chat/contextual-conversations', contextualConversationPayload([
        'action' => 'delete',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('action');
});

it('rejects unexpected client context instead of silently trusting or discarding it', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $payload = contextualConversationPayload([
        'balance' => 3000000,
        'current_destination' => [
            'params' => [
                'account_id' => 8472,
                'current_balance' => 3000000,
            ],
        ],
        'origin' => [
            'kind' => 'surface_action',
            'recommendation_id' => null,
            'provider' => 'Client supplied provider',
        ],
    ]);

    $this->postJson('/api/ai-chat/contextual-conversations', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['balance', 'current_destination.params.current_balance', 'origin']);
});

it('accepts identifier-only overview context without a resource id', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/ai-chat/contextual-conversations', [
        'action' => 'add',
        'resource_type' => 'savings',
        'resource_id' => null,
        'current_destination' => [
            'screen' => 'savings',
            'params' => [],
            'fallback' => 'dashboard',
        ],
        'origin' => [
            'kind' => 'surface_action',
            'recommendation_id' => null,
        ],
    ])->assertCreated()
        ->assertJsonPath('data.conversation.metadata.resource_type', 'savings')
        ->assertJsonPath('data.conversation.metadata.resource_id', null);
});

it('requires a resource id for entity context', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/ai-chat/contextual-conversations', contextualConversationPayload([
        'resource_id' => null,
        'current_destination' => [
            'params' => [],
        ],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('resource_id');
});

it('does not disclose whether another user owns a requested resource', function (): void {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $account = SavingsAccount::factory()->for($owner)->create();
    Sanctum::actingAs($attacker);

    $foreign = contextualConversationPayload([
        'resource_id' => $account->id,
        'current_destination' => [
            'params' => ['account_id' => $account->id],
        ],
    ]);
    $missing = contextualConversationPayload([
        'resource_id' => 999999,
        'current_destination' => [
            'params' => ['account_id' => 999999],
        ],
    ]);

    $this->postJson('/api/ai-chat/contextual-conversations', $foreign)->assertNotFound();
    $this->postJson('/api/ai-chat/contextual-conversations', $missing)->assertNotFound();
});

it('resolves owned entity types using server records', function (
    string $resourceType,
    string $screen,
    string $idKey,
    string $modelClass,
    array $attributes,
): void {
    $user = User::factory()->create();
    $resource = $modelClass::factory()->for($user)->create($attributes);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/ai-chat/contextual-conversations', contextualConversationPayload([
        'resource_type' => $resourceType,
        'resource_id' => $resource->id,
        'current_destination' => [
            'screen' => $screen,
            'params' => [$idKey => $resource->id],
        ],
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.conversation.metadata.resource_type', $resourceType)
        ->assertJsonPath('data.conversation.metadata.resource_id', $resource->id)
        ->assertJsonPath('data.conversation.metadata.context_provenance.authority', 'server');
})->with([
    'savings account' => [
        'savings_account',
        'savings_account_detail',
        'account_id',
        SavingsAccount::class,
        ['account_name' => 'Rainy Day Account', 'current_balance' => 12500],
    ],
    'investment account' => [
        'investment_account',
        'investment_account_detail',
        'account_id',
        InvestmentAccount::class,
        ['account_name' => 'Long-term ISA', 'current_value' => 88000],
    ],
    'defined contribution pension' => [
        'dc_pension',
        'pension_detail',
        'pension_id',
        DCPension::class,
        ['scheme_name' => 'Workplace Pension', 'current_fund_value' => 184500],
    ],
    'defined benefit pension' => [
        'db_pension',
        'pension_detail',
        'pension_id',
        DBPension::class,
        ['scheme_name' => 'Final Salary Pension', 'accrued_annual_pension' => 18000],
    ],
    'state pension' => [
        'state_pension',
        'pension_detail',
        'pension_id',
        StatePension::class,
        ['state_pension_forecast_annual' => 11976],
    ],
    'goal' => [
        'goal',
        'goals',
        'goal_id',
        Goal::class,
        ['goal_name' => 'Home deposit', 'target_amount' => 75000],
    ],
    'life policy' => [
        'life_insurance_policy',
        'protection_policy_detail',
        'policy_id',
        LifeInsurancePolicy::class,
        ['provider' => 'Example Mutual', 'sum_assured' => 250000],
    ],
    'critical illness policy' => [
        'critical_illness_policy',
        'protection_policy_detail',
        'policy_id',
        CriticalIllnessPolicy::class,
        ['provider' => 'Example Mutual', 'sum_assured' => 75000],
    ],
    'income protection policy' => [
        'income_protection_policy',
        'protection_policy_detail',
        'policy_id',
        IncomeProtectionPolicy::class,
        ['provider' => 'Example Mutual', 'benefit_amount' => 2500],
    ],
    'disability policy' => [
        'disability_policy',
        'protection_policy_detail',
        'policy_id',
        DisabilityPolicy::class,
        ['provider' => 'Example Mutual', 'benefit_amount' => 2000],
    ],
    'sickness and illness policy' => [
        'sickness_illness_policy',
        'protection_policy_detail',
        'policy_id',
        SicknessIllnessPolicy::class,
        ['provider' => 'Example Mutual', 'benefit_amount' => 1800],
    ],
]);
