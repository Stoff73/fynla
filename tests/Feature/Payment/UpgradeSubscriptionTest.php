<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Freeze "now" to a mid-month date. The proration setups use
    // now()->subMonths(N); on month-end dates (29th–31st) Carbon overflows the
    // non-existent target day (e.g. 29 May - 3mo → invalid 29 Feb → rolls to
    // 1 Mar), which makes diffInMonths off by one and the assertions flaky by
    // calendar date. The 15th is clear of every short-month boundary.
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('POST /api/payment/upgrade', function () {

    it('calculates prorated amount for yearly Free to Premium upgrade at 6 months', function () {
        $user = User::factory()->create();
        $periodStart = now()->subMonths(6);
        $subscription = Subscription::factory()
            ->plan('free')
            ->billingCycle('yearly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodStart->copy()->addYear(),
                'amount' => 0,
            ]);

        Http::fake([
            '*/api/orders' => Http::response([
                'id' => fake()->uuid(),
                'token' => 'test_token_123',
                'state' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $premiumPrice = app(TierConfigurationStore::class)->forTier('premium')->price_annual_pence;
        $monthlyDiff = (int) round($premiumPrice / 12);

        // 6 months into a 12-month period (time frozen mid-month in beforeEach)
        // → exactly 6 months remaining (day-based proration).
        $monthsRemaining = 6;
        $expectedAmount = $monthlyDiff * $monthsRemaining;

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/upgrade', ['plan' => 'premium']);

        $response->assertOk();
        expect($response->json('upgrade_amount'))->toBe($expectedAmount);
        expect($response->json('new_plan'))->toBe('premium');
        expect($response->json('months_remaining'))->toBe($monthsRemaining);

        // Verify Payment record has upgrade_from_plan
        $payment = Payment::where('user_id', $user->id)->latest()->first();
        expect($payment->upgrade_from_plan)->toBe('free');
        expect($payment->plan_slug)->toBe('premium');
        Http::assertSent(fn ($request): bool => data_get(
            $request->data(),
            'merchant_order_data.reference'
        ) === "payment_{$payment->id}");
    });

    it('calculates prorated amount for yearly Free to Premium at 3 months', function () {
        $user = User::factory()->create();
        Subscription::factory()
            ->plan('free')
            ->billingCycle('yearly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
                'current_period_start' => now()->subMonths(3),
                'current_period_end' => now()->addMonths(9),
                'amount' => 0,
            ]);

        Http::fake([
            '*/api/orders' => Http::response([
                'id' => fake()->uuid(),
                'token' => 'test_token_456',
                'state' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $premiumPrice = app(TierConfigurationStore::class)->forTier('premium')->price_annual_pence;
        $monthlyDiff = (int) round($premiumPrice / 12);
        $expectedAmount = $monthlyDiff * 9; // 9 months remaining

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/upgrade', ['plan' => 'premium']);

        $response->assertOk();
        expect($response->json('upgrade_amount'))->toBe($expectedAmount);
        expect($response->json('months_remaining'))->toBe(9);
    });

    it('uses day-based proration so a month-end yearly start is not over-billed', function () {
        // Regression guard. Start 30 Apr, viewed 15 Jun (time frozen in
        // beforeEach). The old `12 - current_period_start->diffInMonths(now())`
        // reported 11 months remaining: Carbon under-counts elapsed months when
        // the period anniversary day falls in a month that has fewer days, so
        // the proration over-billed by a whole month. Day-based proration
        // (days remaining ÷ avg days per month) correctly reports 10.
        $user = User::factory()->create();
        Subscription::factory()
            ->plan('free')
            ->billingCycle('yearly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
                'current_period_start' => Carbon::parse('2026-04-30 12:00:00'),
                'current_period_end' => Carbon::parse('2027-04-30 12:00:00'),
                'amount' => 0,
            ]);

        Http::fake([
            '*/api/orders' => Http::response([
                'id' => fake()->uuid(),
                'token' => 'test_token_month_end',
                'state' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $premiumPrice = app(TierConfigurationStore::class)->forTier('premium')->price_annual_pence;
        $monthlyDiff = (int) round($premiumPrice / 12);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/upgrade', ['plan' => 'premium']);

        $response->assertOk();
        expect($response->json('months_remaining'))->toBe(10);
        expect($response->json('upgrade_amount'))->toBe($monthlyDiff * 10);
    });

    it('charges full month difference for monthly upgrade', function () {
        $user = User::factory()->create();
        Subscription::factory()
            ->plan('free')
            ->billingCycle('monthly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
                'current_period_start' => now()->subDays(10),
                'current_period_end' => now()->addDays(20),
                'amount' => 0,
            ]);

        Http::fake([
            '*/api/orders' => Http::response([
                'id' => fake()->uuid(),
                'token' => 'test_token_789',
                'state' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $expectedAmount = app(TierConfigurationStore::class)->forTier('premium')->price_monthly_pence;

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/upgrade', ['plan' => 'premium']);

        $response->assertOk();
        expect($response->json('upgrade_amount'))->toBe($expectedAmount);
    });

    it('rejects upgrade to same plan', function () {
        $user = User::factory()->create();
        Subscription::factory()
            ->plan('premium')
            ->billingCycle('yearly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/upgrade', ['plan' => 'premium']);

        $response->assertStatus(422);
    });

    it('rejects downgrade to lower plan', function () {
        $user = User::factory()->create();
        Subscription::factory()
            ->plan('premium')
            ->billingCycle('yearly')
            ->create([
                'user_id' => $user->id,
                'status' => 'active',
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/upgrade', ['plan' => 'free']);

        $response->assertStatus(422);
    });

    it('rejects upgrade without active subscription', function () {
        $user = User::factory()->create();
        Subscription::factory()
            ->pending()
            ->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/upgrade', ['plan' => 'premium']);

        $response->assertStatus(403);
    });

    it('rejects unauthenticated requests', function () {
        $response = $this->postJson('/api/payment/upgrade', ['plan' => 'premium']);
        $response->assertStatus(401);
    });
});

describe('confirmPayment keeps period dates for upgrades', function () {

    it('keeps existing period dates when confirming an upgrade payment', function () {
        $user = User::factory()->create();
        $periodStart = now()->subMonths(3);
        $periodEnd = now()->addMonths(9);

        $subscription = Subscription::factory()
            ->plan('free')
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
            'amount' => 11241,
            'currency' => 'GBP',
            'status' => 'pending',
            'description' => 'Upgrade: Free → Premium',
            'plan_slug' => 'premium',
            'billing_cycle' => 'yearly',
            'upgrade_from_plan' => 'free',
        ]);

        // Mock Revolut order verification
        Http::fake([
            "*/api/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'state' => 'completed',
                'amount' => 11241,
                'currency' => 'GBP',
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/confirm', ['order_id' => $orderId]);

        $response->assertOk();

        $subscription->refresh();
        expect($subscription->plan)->toBe('premium');
        expect($subscription->status)->toBe('active');
        expect((int) $subscription->amount)->toBe(5999);
        expect((int) Payment::where('revolut_order_id', $orderId)->value('amount'))->toBe(11241);
        // Period dates should be unchanged
        expect($subscription->current_period_start->format('Y-m-d'))->toBe($periodStart->format('Y-m-d'));
        expect($subscription->current_period_end->format('Y-m-d'))->toBe($periodEnd->format('Y-m-d'));
    });

    it('sets new period dates for non-upgrade payments', function () {
        $user = User::factory()->create();
        $subscription = Subscription::factory()
            ->pending()
            ->create([
                'user_id' => $user->id,
            ]);

        $orderId = fake()->uuid();

        Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => $orderId,
            'amount' => 14990,
            'currency' => 'GBP',
            'status' => 'pending',
            'description' => 'Premium Yearly',
            'plan_slug' => 'premium',
            'billing_cycle' => 'yearly',
        ]);

        Http::fake([
            "*/api/orders/{$orderId}" => Http::response([
                'id' => $orderId,
                'state' => 'completed',
                'amount' => 14990,
                'currency' => 'GBP',
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/confirm', ['order_id' => $orderId]);

        $response->assertOk();

        $subscription->refresh();
        expect($subscription->plan)->toBe('premium');
        expect($subscription->status)->toBe('active');
        // Period dates should be set to now/future (not null)
        expect($subscription->current_period_start)->not->toBeNull();
        expect($subscription->current_period_end->isFuture())->toBeTrue();
    });
});
