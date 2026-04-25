<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
});

// ─── get_subscription_status ─────────────────────────────────────────

it('get_subscription_status returns active subscription shape', function (): void {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'standard',
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'amount' => 10.99,
        'current_period_start' => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_subscription_status', [], $user->fresh());

    expect($result)->toHaveKeys([
        'status', 'plan_name', 'billing_cycle', 'trial_ends_at', 'current_period_end',
        'next_charge_amount', 'is_cancelled',
    ]);
    expect($result['status'])->toBe('active');
    expect($result['is_cancelled'])->toBeFalse();
    expect($result['next_charge_amount'])->toBe(10.99);
    expect($result['billing_cycle'])->toBe('monthly');
});

it('get_subscription_status returns status none when user has no subscription', function (): void {
    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->executeTool('get_subscription_status', [], $user);

    expect($result)->toHaveKey('status');
    expect($result['status'])->toBe('none');
});

it('get_subscription_status flags cancelled subscriptions', function (): void {
    $user = User::factory()->create();
    Subscription::factory()->cancelled()->create([
        'user_id' => $user->id,
        'plan' => 'standard',
        'billing_cycle' => 'monthly',
        'amount' => 10.99,
        'current_period_end' => now()->addDays(10),
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_subscription_status', [], $user->fresh());

    expect($result['is_cancelled'])->toBeTrue();
});

it('get_subscription_status returns trial dates when trialing', function (): void {
    $user = User::factory()->create();
    Subscription::factory()->trialing()->create([
        'user_id' => $user->id,
        'plan' => 'standard',
        'billing_cycle' => 'monthly',
        'amount' => 10.99,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_subscription_status', [], $user->fresh());

    expect($result['status'])->toBe('trialing');
    expect($result['trial_ends_at'])->not->toBeNull();
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

it('get_current_plan returns plan shape', function (): void {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'standard',
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'amount' => 10.99,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_current_plan', [], $user->fresh());

    expect($result)->toHaveKeys(['plan_name', 'tier', 'billing_cycle', 'price_gbp', 'features']);
    expect($result['tier'])->toBe('standard');
    expect($result['billing_cycle'])->toBe('monthly');
    expect($result['features'])->toBeArray();
});

it('get_current_plan returns none shape when user has no subscription', function (): void {
    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->executeTool('get_current_plan', [], $user);

    expect($result['tier'])->toBe('none');
    expect($result['plan_name'])->toBe('none');
    expect($result['price_gbp'])->toBe(0.0);
    expect($result['features'])->toBe([]);
});

it('get_current_plan resolves yearly price for yearly cycle', function (): void {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'standard',
        'billing_cycle' => 'yearly',
        'status' => 'active',
        'amount' => 100.00,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_current_plan', [], $user->fresh());

    $plan = SubscriptionPlan::findBySlug('standard');
    $expected = $plan->getLaunchPriceForCycle('yearly') ?? $plan->getPriceForCycle('yearly');
    expect($result['price_gbp'])->toBe(round($expected / 100, 2));
});
