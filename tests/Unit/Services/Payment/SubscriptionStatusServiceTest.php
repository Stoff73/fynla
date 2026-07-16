<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\SubscriptionStatusService;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
});

it('returns the exact Free entitlement contract without trial fields', function (): void {
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);

    $status = app(SubscriptionStatusService::class)->forUser($user);

    expect($status)
        ->toMatchArray([
            'has_subscription' => false,
            'tier' => 'free',
            'tier_display_name' => 'Free',
            'subscription_status' => null,
            'count_caps' => [
                'savings_account' => 2,
                'investment' => 2,
                'pension_account' => 2,
                'property' => 1,
                'mortgage' => 10,
                'goal' => 2,
                'life_event' => 1,
            ],
            'payment_enabled' => true,
        ])
        ->not->toHaveKeys(['status', 'trial_started_at', 'trial_ends_at', 'days_remaining']);
});

it('reports payments unavailable for preview users', function (): void {
    $user = User::factory()->create([
        'tier' => 'free',
        'plan' => 'free',
        'is_preview_user' => true,
    ]);

    $status = app(SubscriptionStatusService::class)->forUser($user);

    expect($status['payment_enabled'])->toBeFalse();
});

it('returns a pending checkout as a non-entitling state', function (): void {
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);
    Subscription::factory()->pending()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'billing_cycle' => 'monthly',
        'amount' => 699,
    ]);

    $status = app(SubscriptionStatusService::class)->forUser($user->fresh());

    expect($status)
        ->toMatchArray([
            'has_subscription' => true,
            'tier' => 'free',
            'tier_display_name' => 'Free',
            'subscription_status' => 'pending',
            'plan' => 'premium',
        ])
        ->not->toHaveKeys(['status', 'trial_started_at', 'trial_ends_at', 'days_remaining']);
});

it('reports Free while a stale paid tier has only ended and provisional rows', function (): void {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => false,
        'current_period_end' => now()->subMinute(),
    ]);
    Subscription::factory()->pending()->plan('premium')->create(['user_id' => $user->id]);

    $status = app(SubscriptionStatusService::class)->forUser($user->fresh());

    expect($status)->toMatchArray([
        'tier' => 'free',
        'tier_display_name' => 'Free',
        'subscription_status' => 'pending',
        'count_caps' => [
            'savings_account' => 2,
            'investment' => 2,
            'pension_account' => 2,
            'property' => 1,
            'mortgage' => 10,
            'goal' => 2,
            'life_event' => 1,
        ],
    ]);
});

it('returns the complete Premium contract from the latest active subscription', function (): void {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    Subscription::factory()->expired()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'created_at' => now()->subMonth(),
    ]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'billing_cycle' => 'yearly',
        'status' => 'active',
        'amount' => 5999,
        'auto_renew' => true,
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->addMonths(11),
    ]);

    $status = app(SubscriptionStatusService::class)->forUser($user->fresh());

    expect($status)->toMatchArray([
        'has_subscription' => true,
        'tier' => 'premium',
        'tier_display_name' => 'Premium',
        'subscription_status' => 'active',
        'plan' => 'premium',
        'billing_cycle' => 'yearly',
        'auto_renew' => true,
        'count_caps' => [
            'savings_account' => null,
            'investment' => null,
            'pension_account' => null,
            'property' => null,
            'mortgage' => null,
            'goal' => null,
            'life_event' => null,
        ],
        'document_upload_allowance' => null,
        'document_storage_gb' => '1.00',
        'fyn_weekly_token_budget' => 500_000,
        'fyn_daily_hard_backstop' => 2_000_000,
        'currency_display_mode' => 'user_choice',
        'snapshot_surfacing_window_days' => null,
        'open_api_affordance' => true,
        'payment_enabled' => true,
    ]);
    expect($status)->not->toHaveKey('status');
    expect($status['capability_matrix']['holistic_plan'])->toBe('full')
        ->and($status['next_renewal_date'])->not->toBeNull()
        ->and($status['tier'])->not->toBeIn(['tier1', 'tier2', 'tier3']);
});

it('prefers an older active entitlement over a newer provisional row', function (): void {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    $active = Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => false,
        'current_period_end' => now()->addMonth(),
    ]);
    Subscription::factory()->pending()->plan('premium')->create(['user_id' => $user->id]);

    $status = app(SubscriptionStatusService::class)->forUser($user->fresh());

    expect($status)->toMatchArray([
        'tier' => 'premium',
        'subscription_status' => 'active',
        'plan' => 'premium',
        'current_period_end' => $active->current_period_end->toISOString(),
    ]);
});

it('keeps a cancelled subscription inside its paid period non-terminal', function (): void {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    Subscription::factory()->cancelled()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'current_period_end' => now()->addDays(10),
        'auto_renew' => false,
    ]);

    $status = app(SubscriptionStatusService::class)->forUser($user->fresh());

    expect($status)->toMatchArray([
        'tier' => 'premium',
        'subscription_status' => 'cancelled',
        'is_terminal_paid' => false,
        'auto_renew' => false,
        'next_renewal_date' => null,
    ]);
});

it('returns a terminal paid grace state without restoring Premium entitlement', function (): void {
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);
    Subscription::factory()->expired()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'data_retention_starts_at' => now()->subDays(5),
    ]);

    $status = app(SubscriptionStatusService::class)->forUser($user->fresh());

    expect($status)->toMatchArray([
        'tier' => 'free',
        'subscription_status' => 'expired',
        'is_terminal_paid' => true,
        'is_in_grace_period' => true,
    ]);
    expect($status['grace_period_ends_at'])->not->toBeNull();
});

it('returns an expired terminal state outside retention grace', function (): void {
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);
    Subscription::factory()->expired()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'data_retention_starts_at' => now()->subDays(31),
    ]);

    $status = app(SubscriptionStatusService::class)->forUser($user->fresh());

    expect($status)->toMatchArray([
        'tier' => 'free',
        'subscription_status' => 'expired',
        'is_terminal_paid' => true,
        'is_in_grace_period' => false,
    ]);
});

it('suppresses retention dates when payments are disabled', function (): void {
    config(['app.payment_enabled' => false]);
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);
    Subscription::factory()->expired()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'data_retention_starts_at' => now()->subDays(5),
    ]);

    $status = app(SubscriptionStatusService::class)->forUser($user->fresh());

    expect($status)->toMatchArray([
        'payment_enabled' => false,
        'data_retention_starts_at' => null,
        'grace_period_ends_at' => null,
        'is_in_grace_period' => false,
    ]);
});
