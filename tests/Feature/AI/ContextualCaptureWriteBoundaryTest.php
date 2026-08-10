<?php

declare(strict_types=1);

use App\Models\AiAuditEvent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AI\AuditChainService;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TierConfigurationSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

beforeEach(function (): void {
    $this->seed(TierConfigurationSeeder::class);
    config()->set('app.ai_audit_hmac_key', 'contextual-boundary-test-key');
});

function contextualBoundaryPayload(array $overrides = []): array
{
    $payload = array_replace_recursive([
        'action' => 'edit',
        'resource_type' => 'savings_account',
        'resource_id' => 1,
        'current_destination' => [
            'screen' => 'savings_account_detail',
            'params' => ['account_id' => 1],
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

function contextualBoundaryOverviewPayload(): array
{
    return [
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
    ];
}

function grantContextualBoundaryConsent(User $user): void
{
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
}

it('keeps contextual conversation creation and transcript loading read only', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    $account = SavingsAccount::factory()->for($user)->create([
        'account_name' => 'Rainy Day Account',
        'current_balance' => 12500,
        'interest_rate' => 4.25,
    ])->fresh();
    Sanctum::actingAs($user);

    $before = collect(['account_name', 'current_balance', 'interest_rate', 'updated_at'])
        ->mapWithKeys(fn (string $key): array => [$key => $account->getRawOriginal($key)])
        ->all();
    $payload = contextualBoundaryPayload([
        'resource_id' => $account->id,
        'current_destination' => ['params' => ['account_id' => $account->id]],
    ]);

    $firstId = $this->postJson('/api/ai-chat/contextual-conversations', $payload)
        ->assertCreated()
        ->json('data.conversation.id');
    $secondId = $this->postJson('/api/ai-chat/contextual-conversations', $payload)
        ->assertCreated()
        ->json('data.conversation.id');
    $this->getJson("/api/ai-chat/conversations/{$firstId}")
        ->assertOk()
        ->assertJsonPath('data.conversation.id', $firstId);

    expect($secondId)->not->toBe($firstId)
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(1)
        ->and(collect(array_keys($before))
            ->mapWithKeys(fn (string $key): array => [$key => $account->fresh()->getRawOriginal($key)])
            ->all())->toBe($before)
        ->and(AiAuditEvent::where('user_id', $user->id)->count())->toBe(0);
});

it('persists unconfirmed text as conversation evidence but rejects a write tool in advice mode', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    $account = SavingsAccount::factory()->for($user)->create([
        'account_name' => 'Rainy Day Account',
        'current_balance' => 12500,
    ]);
    grantContextualBoundaryConsent($user);
    Sanctum::actingAs($user);

    $conversationId = $this->postJson('/api/ai-chat/contextual-conversations', contextualBoundaryPayload([
        'resource_id' => $account->id,
        'current_destination' => ['params' => ['account_id' => $account->id]],
    ]))->assertCreated()->json('data.conversation.id');

    FynStreamHarness::fake()
        ->toolTurn('create_savings_account', [
            'account_name' => 'Unconfirmed Account',
            'account_type' => 'easy_access',
            'institution' => 'Halifax',
            'current_balance' => 20000,
            'ownership_type' => 'individual',
        ], 'toolu_unconfirmed')
        ->textTurn('I can discuss that estimate, but I have not changed your records.')
        ->bind();

    $message = 'Could the balance perhaps be about £20,000?';
    $response = $this->postJson("/api/ai-chat/conversations/{$conversationId}/messages", [
        'message' => $message,
    ])->assertOk();
    $response->streamedContent();

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(1)
        ->and((float) $account->fresh()->current_balance)->toEqual(12500.0)
        ->and(AiMessage::where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->where('content', $message)
            ->exists())->toBeTrue();

    expect(AiAuditEvent::where('user_id', $user->id)
        ->where('conversation_id', $conversationId)
        ->where('tool_name', 'create_savings_account')
        ->exists())->toBeFalse();
});

it('writes confirmed user supplied facts only through validated inline capture with an intact audit chain', function (): void {
    $user = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    grantContextualBoundaryConsent($user);
    Sanctum::actingAs($user);

    $conversationId = $this->postJson(
        '/api/ai-chat/contextual-conversations',
        contextualBoundaryOverviewPayload(),
    )->assertCreated()->json('data.conversation.id');

    FynStreamHarness::fake()
        ->toolTurn('create_savings_account', [
            'account_name' => 'Rainy Day Account',
            'account_type' => 'easy_access',
            'institution' => 'Halifax',
            'current_balance' => 5000,
            'interest_rate' => 4.5,
            'ownership_type' => 'individual',
        ], 'toolu_confirmed')
        ->textTurn('Saved your Rainy Day Account.')
        ->bind();

    $message = 'Add my individually owned Halifax easy access savings account named Rainy Day Account, with a £5,000 balance and 4.5% interest.';
    $response = $this->postJson("/api/ai-chat/conversations/{$conversationId}/messages", [
        'message' => $message,
    ])->assertOk();
    $response->streamedContent();

    $account = SavingsAccount::where('user_id', $user->id)
        ->where('account_name', 'Rainy Day Account')
        ->sole();

    expect((float) $account->current_balance)->toEqual(5000.0)
        ->and((float) $account->interest_rate)->toEqual(4.5)
        ->and($account->ownership_type)->toBe('individual')
        ->and(AiMessage::where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->where('content', $message)
            ->count())->toBe(1)
        ->and(app(AuditChainService::class)->verifyChain()['chain_valid'])->toBeTrue();

    $this->assertDatabaseHas('ai_audit_events', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
        'tool_name' => 'create_savings_account',
        'operation' => 'write',
        'status' => 'dispatched',
    ]);
    $this->assertDatabaseHas('ai_audit_events', [
        'user_id' => $user->id,
        'conversation_id' => $conversationId,
        'tool_name' => 'create_savings_account',
        'operation' => 'write',
        'status' => 'persisted',
        'entity_type' => 'savings_account',
        'entity_id' => $account->id,
    ]);
});

it('rejects foreign identifiers before any conversation or capture context exists', function (): void {
    $owner = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    $attacker = User::factory()->create(['onboarding_completed' => true, 'is_preview_user' => false]);
    $foreign = SavingsAccount::factory()->for($owner)->create([
        'account_name' => 'Owner Only Account',
        'current_balance' => 33333,
    ]);
    Sanctum::actingAs($attacker);

    $this->postJson('/api/ai-chat/contextual-conversations', contextualBoundaryPayload([
        'resource_id' => $foreign->id,
        'current_destination' => ['params' => ['account_id' => $foreign->id]],
    ]))->assertNotFound();

    expect(AiConversation::where('user_id', $attacker->id)->count())->toBe(0)
        ->and(AiMessage::query()->count())->toBe(0)
        ->and(AiAuditEvent::where('user_id', $attacker->id)->count())->toBe(0)
        ->and((float) $foreign->fresh()->current_balance)->toEqual(33333.0);
});
