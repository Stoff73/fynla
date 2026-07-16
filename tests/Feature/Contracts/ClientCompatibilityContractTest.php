<?php

declare(strict_types=1);

use App\Models\PendingRegistration;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;
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
    $user = User::factory()->create(['tier' => 'premium']);
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
