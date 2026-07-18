<?php

declare(strict_types=1);

use App\Agents\EstateAgent;
use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Agents\TaxOptimisationAgent;
use App\Models\AiConversation;
use App\Models\PendingRegistration;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Mail::fake();
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
});

it('freezes registration authentication and Free entitlement response shapes', function (): void {
    $registration = $this->postJson('/api/auth/register', [
        'first_name' => 'Client',
        'surname' => 'Contract',
        'email' => 'client.contract@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertCreated();

    $pending = PendingRegistration::findOrFail($registration->json('data.pending_id'));

    $verification = $this->postJson('/api/auth/verify-code', [
        'type' => 'registration',
        'pending_id' => $pending->id,
        'code' => $pending->verification_code,
    ])->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user',
                'access_token',
                'token_type',
                'must_change_password',
                'mfa_enabled',
                'checkout_intent',
            ],
        ])
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.checkout_intent', null);

    expect($verification->json('data.access_token'))->toBeString()->not->toBe('');

    $encodedVerification = json_encode($verification->json(), JSON_THROW_ON_ERROR);
    expect($encodedVerification)->not->toContain(
        'trial',
        'trial_ends_at',
        'tier1',
        'tier2',
        'tier3',
    );

    $user = User::where('email', 'client.contract@example.com')->firstOrFail();
    expect($user->tier)->toBe('free')
        ->and($user->subscriptions()->exists())->toBeFalse();

    $profile = $this->withToken($verification->json('data.access_token'))
        ->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.tier_flags.resolved_tier', 'free')
        ->assertJsonPath('data.tier_flags.capabilities.dashboard', 'full')
        ->assertJsonPath('data.tier_flags.limits.savings_account', 2);

    expect($profile->json('data.tier_flags.capabilities'))->toBeArray()
        ->and($profile->json('data.tier_flags.limits'))->toBeArray();

    $status = $this->withToken($verification->json('data.access_token'))
        ->getJson('/api/payment/subscription-status')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'tier' => 'free',
                'provider' => null,
                'status' => 'free',
                'renews' => false,
                'current_period_end' => null,
            ],
        ])
        ->assertJsonPath('data.capabilities.dashboard', 'full')
        ->assertJsonPath('data.limits.savings_account', 2);

    expect($status->json('data.capabilities'))->toBeArray()
        ->and($status->json('data.limits'))->toBeArray();
});

it('freezes Premium entitlement keys and canonical value types', function (): void {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'auto_renew' => true,
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
    ]);
    Sanctum::actingAs($user);

    $profile = $this->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.tier_flags.resolved_tier', 'premium')
        ->assertJsonPath('data.tier_flags.capabilities.dashboard', 'full')
        ->assertJsonPath('data.tier_flags.limits.savings_account', null);

    expect($profile->json('data.tier_flags.capabilities'))->toBeArray()
        ->and($profile->json('data.tier_flags.limits'))->toBeArray();

    $status = $this->getJson('/api/payment/subscription-status')
        ->assertOk()
        ->assertJsonPath('data.tier', 'premium')
        ->assertJsonPath('data.provider', 'revolut')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.renews', true)
        ->assertJsonPath('data.capabilities.dashboard', 'full')
        ->assertJsonPath('data.limits.savings_account', null);

    expect($status->json('data.tier'))->toBeString()
        ->and($status->json('data.provider'))->toBeString()
        ->and($status->json('data.status'))->toBeString()
        ->and($status->json('data.renews'))->toBeBool()
        ->and($status->json('data.current_period_end'))->toBeString()
        ->and($status->json('data.capabilities'))->toBeArray()
        ->and($status->json('data.limits'))->toBeArray();
});

it('freezes the shared mobile dashboard envelope and gamification keys', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/mobile/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'modules',
                'net_worth' => ['total', 'breakdown'],
                'alerts',
                'fyn_insight',
                'cached_at',
                'focus_areas',
                'next_actions',
                'level' => [
                    'level',
                    'level_name',
                    'next_level_name',
                    'progress_percent',
                    'actions_completed',
                    'actions_total',
                    'actions_to_next',
                ],
                'percentile',
                'new_milestones',
                'next_milestone',
            ],
        ])
        ->assertJsonPath('success', true);

    expect($response->json('data.modules'))->toBeArray()
        ->and($response->json('data.next_actions'))->toBeArray()
        ->and($response->json('data.level.level'))->toBeInt()
        ->and($response->json('data.level.progress_percent'))->toBeInt()
        ->and($response->json('data.percentile'))->toBeInt();
});

it('freezes every shared mobile module envelope', function (string $module, string $agentClass): void {
    $agent = Mockery::mock($agentClass);
    $agent->shouldReceive('analyze')->once()->andReturn([
        'summary' => ['total_value' => 10000.0],
        'message' => 'Analysis complete',
    ]);
    $this->app->instance($agentClass, $agent);

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson("/api/v1/mobile/modules/{$module}")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => ['module', 'summary', 'cached_at'],
        ])
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.module', $module);

    expect($response->json('data.summary'))->toBeArray()
        ->and($response->json('data.cached_at'))->toBeString();
})->with([
    'protection' => ['protection', ProtectionAgent::class],
    'savings' => ['savings', SavingsAgent::class],
    'investment' => ['investment', InvestmentAgent::class],
    'retirement' => ['retirement', RetirementAgent::class],
    'estate' => ['estate', EstateAgent::class],
    'goals' => ['goals', GoalsAgent::class],
    'tax' => ['tax', TaxOptimisationAgent::class],
]);

it('freezes Fyn conversation creation loading and queued submission envelopes', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'is_preview_user' => false,
    ]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($user);

    $created = $this->postJson('/api/ai-chat/conversations', [
        'current_route' => '/dashboard',
    ])->assertCreated()
        ->assertJsonStructure([
            'success',
            'data' => ['id', 'user_id', 'status', 'metadata'],
        ])
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'active');

    $conversationId = $created->json('data.id');

    $this->getJson("/api/ai-chat/conversations/{$conversationId}")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'conversation' => ['id', 'user_id', 'status'],
                'messages',
            ],
        ])
        ->assertJsonPath('data.conversation.id', $conversationId)
        ->assertJsonCount(0, 'data.messages');

    $conversation = AiConversation::findOrFail($conversationId);
    $inflight = Cache::lock('fyn:inflight:'.$conversation->id, 300);
    expect($inflight->get())->toBeTrue();

    try {
        $queued = $this->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", [
            'message' => 'What is my current plan?',
            'current_route' => '/dashboard',
        ])->assertStatus(202)
            ->assertJsonStructure(['status', 'message_id', 'queue_position'])
            ->assertJsonPath('status', 'queued');

        expect($queued->json('message_id'))->toBeInt()
            ->and($queued->json('queue_position'))->toBeInt();
    } finally {
        $inflight->release();
    }
});
