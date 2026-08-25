<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

// ─── get_subscription_status ─────────────────────────────────────────

it('get_subscription_status returns active subscription shape', function (): void {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'free']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'amount' => 699,
        'current_period_start' => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_subscription_status', [], $user->fresh());

    expect($result)->toMatchArray([
        'subscription_status' => 'active',
        'tier' => 'premium',
        'tier_display_name' => 'Premium',
        'billing_cycle' => 'monthly',
        'action' => 'navigate',
        'route_path' => '/settings/subscription',
    ])->not->toHaveKeys(['status', 'trial_started_at', 'trial_ends_at', 'days_remaining']);
});

it('get_subscription_status returns the Free state when user has no subscription', function (): void {
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);

    $result = app(CoordinatingAgent::class)->executeTool('get_subscription_status', [], $user);

    expect($result)->toMatchArray([
        'has_subscription' => false,
        'subscription_status' => null,
        'tier' => 'free',
        'tier_display_name' => 'Free',
        'action' => 'navigate',
        'route_path' => '/settings/subscription',
    ])->not->toHaveKey('status');
});

it('get_subscription_status flags cancelled subscriptions', function (): void {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    Subscription::factory()->cancelled()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'billing_cycle' => 'monthly',
        'amount' => 699,
        'current_period_end' => now()->addDays(10),
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_subscription_status', [], $user->fresh());

    expect($result['subscription_status'])->toBe('cancelled')
        ->and($result['tier_display_name'])->toBe('Premium');
});

it('get_subscription_status returns pending checkout state without compatibility fields', function (): void {
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);
    Subscription::factory()->pending()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'billing_cycle' => 'monthly',
        'amount' => 699,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_subscription_status', [], $user->fresh());

    expect($result)->toMatchArray([
        'subscription_status' => 'pending',
        'tier' => 'free',
        'tier_display_name' => 'Free',
    ])->not->toHaveKeys(['status', 'trial_started_at', 'trial_ends_at', 'days_remaining']);
});

// ─── list_invoices ───────────────────────────────────────────────────

it('list_invoices returns invoice shape', function (): void {
    $user = User::factory()->create();
    Invoice::factory()->count(3)->create(['user_id' => $user->id]);

    $result = app(CoordinatingAgent::class)->executeTool('list_invoices', [], $user->fresh());

    expect($result)->toHaveCount(3);
    expect($result[0])->toHaveKeys(['invoice_id', 'invoice_number', 'issued_at', 'amount', 'currency', 'status', 'pdf_url']);
    expect($result[0]['amount'])->toBeFloat();
    expect($result[0]['currency'])->toBe('GBP');
});

it('list_invoices returns empty array when user has no invoices', function (): void {
    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->executeTool('list_invoices', [], $user);

    expect($result)->toBe([]);
});

it('list_invoices converts pence to pounds for amount', function (): void {
    $user = User::factory()->create();
    Invoice::factory()->create([
        'user_id' => $user->id,
        'total_amount' => 1499,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('list_invoices', [], $user->fresh());

    expect($result[0]['amount'])->toBe(14.99);
});

it('list_invoices orders most recent first', function (): void {
    $user = User::factory()->create();
    $older = Invoice::factory()->create(['user_id' => $user->id, 'issued_at' => now()->subDays(5)]);
    $newer = Invoice::factory()->create(['user_id' => $user->id, 'issued_at' => now()->subDays(1)]);

    $result = app(CoordinatingAgent::class)->executeTool('list_invoices', [], $user->fresh());

    expect($result[0]['invoice_id'])->toBe($newer->id);
    expect($result[1]['invoice_id'])->toBe($older->id);
});

// ─── get_current_plan ────────────────────────────────────────────────

it('get_current_plan returns canonical Premium details with pence converted to pounds', function (): void {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'amount' => 699,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_current_plan', [], $user->fresh());

    expect($result)->toHaveKeys(['plan_name', 'tier', 'billing_cycle', 'price_gbp', 'features']);
    expect($result['plan_name'])->toBe('Premium')
        ->and($result['tier'])->toBe('premium')
        ->and($result['billing_cycle'])->toBe('monthly')
        ->and($result['price_gbp'])->toBe(6.99)
        ->and($result['features'])->toContain('dashboard', 'holistic_plan');
});

it('get_current_plan returns canonical Free details when user has no subscription', function (): void {
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);

    $result = app(CoordinatingAgent::class)->executeTool('get_current_plan', [], $user);

    expect($result['tier'])->toBe('free')
        ->and($result['plan_name'])->toBe('Free')
        ->and($result['billing_cycle'])->toBeNull()
        ->and($result['price_gbp'])->toBe(0.0)
        ->and($result['features'])->toContain('dashboard', 'tax_strategy')
        ->and($result['features'])->not->toContain('holistic_plan');
});

it('get_current_plan converts the canonical Premium yearly price from pence', function (): void {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'billing_cycle' => 'yearly',
        'status' => 'active',
        'amount' => 5999,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_current_plan', [], $user->fresh());

    expect($result['price_gbp'])->toBe(59.99);
});
