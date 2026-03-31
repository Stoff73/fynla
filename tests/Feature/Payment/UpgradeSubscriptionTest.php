<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
    $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
});

describe('POST /api/payment/upgrade', function () {

    it('calculates prorated amount for yearly Standard to Pro upgrade at 6 months', function () {
        $user = User::factory()->create();
        $periodStart = now()->subMonths(6);
        $subscription = Subscription::factory()
            ->plan('standard')
            ->billingCycle('yearly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodStart->copy()->addYear(),
                'amount' => 10000,
            ]);

        Http::fake([
            '*/api/orders' => Http::response([
                'id' => fake()->uuid(),
                'token' => 'test_token_123',
                'state' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $standardPlan = SubscriptionPlan::findBySlug('standard');
        $proPlan = SubscriptionPlan::findBySlug('pro');
        $standardPrice = $standardPlan->getLaunchPriceForCycle('yearly') ?? $standardPlan->getPriceForCycle('yearly');
        $proPrice = $proPlan->getLaunchPriceForCycle('yearly') ?? $proPlan->getPriceForCycle('yearly');
        $priceDiff = $proPrice - $standardPrice;
        $monthlyDiff = (int) round($priceDiff / 12);

        // Carbon diffInMonths counts full months, so calculate dynamically
        $monthsUsed = (int) $periodStart->diffInMonths(now());
        $monthsRemaining = max(1, 12 - $monthsUsed);
        $expectedAmount = $monthlyDiff * $monthsRemaining;

        $response = $this->actingAs($user)
            ->postJson('/api/payment/upgrade', ['plan' => 'pro']);

        $response->assertOk();
        expect($response->json('upgrade_amount'))->toBe($expectedAmount);
        expect($response->json('new_plan'))->toBe('pro');
        expect($response->json('months_remaining'))->toBe($monthsRemaining);

        // Verify Payment record has upgrade_from_plan
        $payment = Payment::where('user_id', $user->id)->latest()->first();
        expect($payment->upgrade_from_plan)->toBe('standard');
        expect($payment->plan_slug)->toBe('pro');
    });

    it('calculates prorated amount for yearly Standard to Family at 3 months', function () {
        $user = User::factory()->create();
        Subscription::factory()
            ->plan('standard')
            ->billingCycle('yearly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
                'current_period_start' => now()->subMonths(3),
                'current_period_end' => now()->addMonths(9),
                'amount' => 10000,
            ]);

        Http::fake([
            '*/api/orders' => Http::response([
                'id' => fake()->uuid(),
                'token' => 'test_token_456',
                'state' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $standardPlan = SubscriptionPlan::findBySlug('standard');
        $familyPlan = SubscriptionPlan::findBySlug('family');
        $standardPrice = $standardPlan->getLaunchPriceForCycle('yearly') ?? $standardPlan->getPriceForCycle('yearly');
        $familyPrice = $familyPlan->getLaunchPriceForCycle('yearly') ?? $familyPlan->getPriceForCycle('yearly');
        $priceDiff = $familyPrice - $standardPrice;
        $monthlyDiff = (int) round($priceDiff / 12);
        $expectedAmount = $monthlyDiff * 9; // 9 months remaining

        $response = $this->actingAs($user)
            ->postJson('/api/payment/upgrade', ['plan' => 'family']);

        $response->assertOk();
        expect($response->json('upgrade_amount'))->toBe($expectedAmount);
        expect($response->json('months_remaining'))->toBe(9);
    });

    it('charges full month difference for monthly upgrade', function () {
        $user = User::factory()->create();
        Subscription::factory()
            ->plan('standard')
            ->billingCycle('monthly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
                'current_period_start' => now()->subDays(10),
                'current_period_end' => now()->addDays(20),
                'amount' => 1099,
            ]);

        Http::fake([
            '*/api/orders' => Http::response([
                'id' => fake()->uuid(),
                'token' => 'test_token_789',
                'state' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $standardPlan = SubscriptionPlan::findBySlug('standard');
        $proPlan = SubscriptionPlan::findBySlug('pro');
        $standardPrice = $standardPlan->getLaunchPriceForCycle('monthly') ?? $standardPlan->getPriceForCycle('monthly');
        $proPrice = $proPlan->getLaunchPriceForCycle('monthly') ?? $proPlan->getPriceForCycle('monthly');
        $expectedAmount = $proPrice - $standardPrice;

        $response = $this->actingAs($user)
            ->postJson('/api/payment/upgrade', ['plan' => 'pro']);

        $response->assertOk();
        expect($response->json('upgrade_amount'))->toBe($expectedAmount);
    });

    it('rejects upgrade to same plan', function () {
        $user = User::factory()->create();
        Subscription::factory()
            ->plan('standard')
            ->billingCycle('yearly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
            ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payment/upgrade', ['plan' => 'standard']);

        $response->assertStatus(422);
    });

    it('rejects downgrade to lower plan', function () {
        $user = User::factory()->create();
        Subscription::factory()
            ->plan('pro')
            ->billingCycle('yearly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
            ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payment/upgrade', ['plan' => 'standard']);

        $response->assertStatus(422);
    });

    it('rejects upgrade without active subscription', function () {
        $user = User::factory()->create();
        Subscription::factory()
            ->trialing()
            ->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/payment/upgrade', ['plan' => 'pro']);

        $response->assertStatus(403);
    });

    it('rejects unauthenticated requests', function () {
        $response = $this->postJson('/api/payment/upgrade', ['plan' => 'pro']);
        $response->assertStatus(401);
    });
});

describe('confirmPayment keeps period dates for upgrades', function () {

    it('keeps existing period dates when confirming an upgrade payment', function () {
        $user = User::factory()->create();
        $periodStart = now()->subMonths(3);
        $periodEnd = now()->addMonths(9);

        $subscription = Subscription::factory()
            ->plan('standard')
            ->billingCycle('yearly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'amount' => 10000,
            ]);

        $orderId = fake()->uuid();

        Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => $orderId,
            'amount' => 4167,
            'currency' => 'GBP',
            'status' => 'pending',
            'description' => 'Upgrade: Standard → Pro',
            'plan_slug' => 'pro',
            'billing_cycle' => 'yearly',
            'upgrade_from_plan' => 'standard',
        ]);

        // Mock Revolut order verification
        Http::fake([
            "*/api/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'state' => 'completed',
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payment/confirm', ['order_id' => $orderId]);

        $response->assertOk();

        $subscription->refresh();
        expect($subscription->plan)->toBe('pro');
        expect($subscription->status)->toBe('active');
        // Period dates should be unchanged
        expect($subscription->current_period_start->format('Y-m-d'))->toBe($periodStart->format('Y-m-d'));
        expect($subscription->current_period_end->format('Y-m-d'))->toBe($periodEnd->format('Y-m-d'));
    });

    it('sets new period dates for non-upgrade payments', function () {
        $user = User::factory()->create();
        $subscription = Subscription::factory()
            ->trialing()
            ->create([
                'user_id' => $user->id,
            ]);

        $orderId = fake()->uuid();

        Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => $orderId,
            'amount' => 10000,
            'currency' => 'GBP',
            'status' => 'pending',
            'description' => 'Standard — Yearly',
            'plan_slug' => 'standard',
            'billing_cycle' => 'yearly',
        ]);

        Http::fake([
            "*/api/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'state' => 'completed',
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/payment/confirm', ['order_id' => $orderId]);

        $response->assertOk();

        $subscription->refresh();
        expect($subscription->plan)->toBe('standard');
        expect($subscription->status)->toBe('active');
        // Period dates should be set to now/future (not null)
        expect($subscription->current_period_start)->not->toBeNull();
        expect($subscription->current_period_end->isFuture())->toBeTrue();
    });
});
