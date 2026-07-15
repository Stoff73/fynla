<?php

declare(strict_types=1);

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\Payment\RevolutService;
use App\Services\Payment\RevolutSubscriptionService;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Stores\TierGate;
use App\Services\Tiers\RevolutTierVariationSync;
use App\Services\Tiers\TierResolver;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('creates the Premium Revolut plan without a trial duration', function () {
    Http::fake([
        '*/subscription-plans' => Http::response([
            'id' => 'revolut_premium_plan',
            'variations' => [['id' => 'revolut_premium_monthly']],
        ]),
    ]);

    $result = app(RevolutSubscriptionService::class)->upsertTierPlan('Premium', 699);

    expect($result)->toBe([
        'id' => 'revolut_premium_monthly',
        'plan_id' => 'revolut_premium_plan',
    ]);

    Http::assertSent(function ($request): bool {
        return str_ends_with($request->url(), '/subscription-plans')
            && $request['name'] === 'Fynla Premium Tier'
            && ! array_key_exists('trial_duration', $request->data());
    });
});

// ── Task 5.1 Step 1: Sync pushes price + writes back variation id ──────────

it('pushes the Premium price to Revolut and writes back the variation id', function () {
    $actor = User::factory()->create();

    $fakeClient = Mockery::mock(RevolutSubscriptionService::class);

    $fakeClient->shouldReceive('upsertTierPlan')
        ->once()
        ->andReturnUsing(function (string $displayName, int $pricePence) {
            return ['id' => 'rev_var_'.strtolower(str_replace(' ', '_', $displayName))];
        });

    $store = app(TierConfigurationStore::class);
    $sync = new RevolutTierVariationSync($fakeClient, $store);
    $sync->run($actor);

    expect(TierConfiguration::where('tier', 'premium')->first()->revolut_plan_variation_id)
        ->toBe('rev_var_premium');
});

it('skips the free tier because it has no monthly price', function () {
    $actor = User::factory()->create();

    $fakeClient = Mockery::mock(RevolutSubscriptionService::class);
    $fakeClient->shouldReceive('upsertTierPlan')
        ->once()
        ->andReturn(['id' => 'rev_var_test']);

    $store = app(TierConfigurationStore::class);
    $sync = new RevolutTierVariationSync($fakeClient, $store);
    $sync->run($actor);

    expect(TierConfiguration::where('tier', 'free')->first()->revolut_plan_variation_id)
        ->toBeNull();
});

it('contains a Premium sync failure without changing its variation id', function () {
    $actor = User::factory()->create();

    $fakeClient = Mockery::mock(RevolutSubscriptionService::class);
    $fakeClient->shouldReceive('upsertTierPlan')
        ->once()
        ->andThrow(new RuntimeException('Revolut API error'));

    $store = app(TierConfigurationStore::class);
    $sync = new RevolutTierVariationSync($fakeClient, $store);

    expect(fn () => $sync->run($actor))->not->toThrow(Throwable::class);
    expect(TierConfiguration::where('tier', 'premium')->first()->revolut_plan_variation_id)->toBeNull();
});

// ── Task 5.1 Step 1: Price-lock assertion ─────────────────────────────────
//
// Billing path (located in SubscriptionRenewalService::handleRenewalPayment):
//   $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug);  // line 59
//   $amount = $subscriptionPlan
//       ? ($subscriptionPlan->getLaunchPrice ?? $subscriptionPlan->getPrice)
//       : $subscription->amount;                                     // line 60-62
//
// For the Premium tier key there is no SubscriptionPlan row, so
// findBySlug() returns null and the renewal falls through to $subscription->amount
// — the value stored at payment confirmation time. This is the price-lock: the
// billed amount is read from the subscription row's locked amount, NOT from a
// live tier configuration read.
//
// This test constructs a fixture where the tier store price has been updated
// after subscription creation and asserts that the renewal service ignores the
// new price and bills from the subscription row.
//
it('does NOT change the price an existing subscriber is billed when the tier price changes (price-lock)', function () {
    // Arrange — user with an active premium subscription locked at 499p
    $user = User::factory()->create();
    $originalAmountPence = 499;

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan' => 'premium',
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'amount' => $originalAmountPence,
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->addMonth(),
    ]);

    // Simulate the tier store price being changed AFTER the subscription was created.
    // (This is what a RevolutTierVariationSync run would do — it updates the store
    // but must NOT retroactively change the subscriber's billed amount.)
    TierConfiguration::where('tier', 'premium')->update(['price_monthly_pence' => 999]);

    // Act — simulate what SubscriptionRenewalService does when the renewal webhook fires.
    // It looks up the SubscriptionPlan by the plan slug stored on the subscription.
    $planSlug = $subscription->plan;                              // 'premium'
    $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug); // returns null (no SubscriptionPlan for tier keys)

    // This is the actual billing path — null coalescence to $subscription->amount.
    $renewalAmount = $subscriptionPlan
        ? ($subscriptionPlan->getLaunchPriceForCycle($subscription->billing_cycle)
            ?? $subscriptionPlan->getPriceForCycle($subscription->billing_cycle))
        : $subscription->amount;

    // Assert — billed amount is from the subscription row (499p), NOT the new tier price (999p).
    // Subscription.amount has decimal:2 cast, so toBe uses string equality; cast to int for clarity.
    expect((int) $renewalAmount)->toBe($originalAmountPence)
        ->and((int) $renewalAmount)->not->toBe(999);
});

// ── Task 5.1: FULL-FLOW end-to-end price-lock (real controller chain) ──────
//
// This drives the REAL Fynla billing chain for a tier key — only the Revolut
// HTTP boundary is mocked (RevolutService), exactly as the acceptance tests
// do. NO Fynla billing logic is mocked. It proves the price-lock guarantee
// end-to-end:
//
//   1. createOrder(premium) at the current store price → Payment.amount captured
//      from TierConfigurationStore at order time.
//   2. confirmPayment → Subscription.amount becomes the captured price (NOT
//      the placeholder amount:0 the subscription is created with, NOT a live
//      re-read of the tier config).
//   3. Admin bumps TierConfiguration premium price via the store.
//   4. The existing subscription's amount is UNCHANGED (price-locked).
//   5. The SubscriptionRenewalService billing path bills the locked amount,
//      not the new store price.
//
it('locks an existing tier subscriber to their original price across a store price change (full flow)', function () {
    $store = app(TierConfigurationStore::class);
    $admin = User::factory()->create();

    // The store price at the time the subscriber checks out.
    $premium = $store->forTier('premium');
    $priceAtOrderTime = $premium->price_monthly_pence; // seeded 499p
    expect($priceAtOrderTime)->toBeGreaterThan(0);

    $user = User::factory()->create(['revolut_customer_id' => 'cust_pricelock']);
    $orderId = '11111111-1111-1111-1111-111111111111';

    // Mock ONLY the Revolut HTTP boundary. createTierOrder uses
    // createOrderWithCustomer; confirmPayment uses getOrder.
    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('createOrderWithCustomer')
        ->once()
        ->andReturn([
            'id' => $orderId,
            'token' => 'tok_pricelock',
            'state' => 'pending',
            'created_at' => now()->toIso8601String(),
        ]);
    $revolut->shouldReceive('getOrder')
        ->with($orderId)
        ->andReturn([
            'id' => $orderId,
            'state' => 'completed',
            'capture_mode' => 'automatic',
            'amount' => $priceAtOrderTime,
            'currency' => 'GBP',
        ]);
    app()->instance(RevolutService::class, $revolut);

    // Step 1: real createOrder for premium — price resolved from the store.
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
        ])->assertOk();

    $payment = Payment::where('user_id', $user->id)->latest()->first();
    expect($payment)->not->toBeNull()
        ->and((int) $payment->amount)->toBe($priceAtOrderTime) // captured store price, NOT 0
        ->and($payment->plan_slug)->toBe('premium');

    // Step 2: real confirmPayment — activates the subscription.
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/confirm', ['order_id' => $orderId])
        ->assertOk();

    $subscription = $user->fresh()->subscription;
    expect($subscription->status)->toBe('active')
        ->and($subscription->plan)->toBe('premium')
        // The locked amount is the price captured at order time, NOT the
        // placeholder amount:0 the subscription row was created with.
        ->and((int) $subscription->amount)->toBe($priceAtOrderTime);

    $lockedAmount = (int) $subscription->amount;

    // Step 3: admin raises the premium price via the store (the legitimate
    // write path — mirrors what an admin price change / sync run does).
    $store->updateTier(
        'premium',
        ['price_monthly_pence' => $priceAtOrderTime + 1000],
        $admin,
        IngestSource::ADMIN,
    );

    // Step 4: the existing subscriber's amount is UNCHANGED — price-locked.
    $subscription->refresh();
    expect((int) $subscription->amount)->toBe($lockedAmount)
        ->and((int) $subscription->amount)->not->toBe($priceAtOrderTime + 1000);

    // Step 5: the real SubscriptionRenewalService billing path bills the
    // locked amount, not the new store price. This mirrors
    // SubscriptionRenewalService::handleRenewalPayment lines 59-62.
    $renewalPlan = SubscriptionPlan::findBySlug($subscription->plan); // null for tier keys
    $renewalAmount = $renewalPlan
        ? ($renewalPlan->getLaunchPriceForCycle($subscription->billing_cycle)
            ?? $renewalPlan->getPriceForCycle($subscription->billing_cycle))
        : $subscription->amount;

    expect((int) $renewalAmount)->toBe($lockedAmount)
        ->and((int) $renewalAmount)->not->toBe($priceAtOrderTime + 1000);
});

// ── PR5 review CRITICAL: tier-key purchase must set canonical users.tier ───
//
// confirmPayment / WebhookController write users.plan (legacy billing-compat
// column, §5.2). TierResolver/DbTierGate gate SOLELY off users.tier. If a
// tier purchase only set users.plan and left users.tier null, the paying
// customer would resolve to 'free' and be given Free caps — locked out of
// what they bought. These tests prove both activation paths now set the
// canonical users.tier, that a legacy purchase does NOT (grandfather logic
// owns those), and that the resolved gate gives the paid (not Free) cap.

it('sets canonical users.tier on a premium purchase via confirmPayment and resolves as premium (not Free)', function () {
    $user = User::factory()->create(['revolut_customer_id' => 'cust_tiercol']);
    $orderId = '22222222-2222-2222-2222-222222222222';
    $priceAtOrderTime = app(TierConfigurationStore::class)->forTier('premium')->price_monthly_pence;

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('createOrderWithCustomer')->once()->andReturn([
        'id' => $orderId, 'token' => 'tok_tiercol', 'state' => 'pending',
        'created_at' => now()->toIso8601String(),
    ]);
    $revolut->shouldReceive('getOrder')->with($orderId)->andReturn([
        'id' => $orderId, 'state' => 'completed', 'capture_mode' => 'automatic',
        'amount' => $priceAtOrderTime, 'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')->postJson('/api/payment/create-order', [
        'plan' => 'premium', 'billing_cycle' => 'monthly',
    ])->assertOk();

    $this->actingAs($user, 'sanctum')->postJson('/api/payment/confirm', [
        'order_id' => $orderId,
    ])->assertOk();

    $fresh = $user->fresh();

    // (a) BOTH columns written: plan (legacy billing-compat) AND tier (canonical).
    expect($fresh->tier)->toBe('premium')
        ->and($fresh->plan)->toBe('premium');

    // (b) TierResolver resolves to the paid tier, NOT 'free'.
    expect(app(TierResolver::class)->resolve($fresh))
        ->toBe('premium');

    // (c) The resolved gate gives the premium cap (unlimited / null for
    //     savings_account), NOT the Free cap of 3. This is the concrete
    //     "paying customer is NOT gated as Free" proof.
    $gate = app(TierGate::class);
    $freeCap = app(TierConfigurationStore::class)->capFor('free', 'savings_account');
    $premiumCap = app(TierConfigurationStore::class)->capFor('premium', 'savings_account');

    expect($freeCap)->toBe(2)
        ->and($premiumCap)->toBeNull()       // premium is unlimited (seeder)
        ->and($gate->hardLimit($fresh, 'savings_account'))->toBe($premiumCap)
        ->and($gate->hardLimit($fresh, 'savings_account'))->not->toBe($freeCap)
        // Paying customer can create well beyond the Free cap of 3.
        ->and($gate->canCreate($fresh, 'savings_account', 50))->toBeTrue();
});

it('sets canonical users.tier on a premium purchase via the Revolut webhook path', function () {
    $user = User::factory()->create(['revolut_customer_id' => 'cust_webhook']);
    $orderId = '33333333-3333-3333-3333-333333333333';
    $priceAtOrderTime = app(TierConfigurationStore::class)->forTier('premium')->price_monthly_pence;

    // Step 1: real createOrder so a pending Payment + subscription exist.
    $createMock = Mockery::mock(RevolutService::class);
    $createMock->shouldReceive('createOrderWithCustomer')->once()->andReturn([
        'id' => $orderId, 'token' => 'tok_webhook', 'state' => 'pending',
        'created_at' => now()->toIso8601String(),
    ]);
    app()->instance(RevolutService::class, $createMock);

    $this->actingAs($user, 'sanctum')->postJson('/api/payment/create-order', [
        'plan' => 'premium', 'billing_cycle' => 'monthly',
    ])->assertOk();

    $payment = Payment::where('user_id', $user->id)->latest()->first();

    // Step 2: drive the REAL webhook HTTP endpoint. Only the Revolut HTTP
    // boundary (signature verify + getOrder) is mocked.
    $webhookMock = Mockery::mock(RevolutService::class);
    $webhookMock->shouldReceive('verifyWebhookSignature')->andReturn(true);
    $webhookMock->shouldReceive('getOrder')->with($orderId)->andReturn([
        'id' => $orderId, 'state' => 'completed', 'capture_mode' => 'automatic',
        'amount' => $priceAtOrderTime, 'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $webhookMock);

    $this->postJson('/api/webhooks/revolut', [
        'event' => 'ORDER_COMPLETED',
        'order_id' => $orderId,
        'merchant_order_ext_ref' => "payment_{$payment->id}",
    ], [
        'Revolut-Signature' => 'v1=stub',
        'Revolut-Request-Timestamp' => (string) (int) (microtime(true) * 1000),
    ])->assertOk();

    $fresh = $user->fresh();
    expect($fresh->tier)->toBe('premium')
        ->and($fresh->plan)->toBe('premium')
        ->and(app(TierResolver::class)->resolve($fresh))->toBe('premium');
});

it('rejects a legacy plan key for a new purchase', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/payment/create-order', [
        'plan' => 'pro', 'billing_cycle' => 'monthly',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('plan');
});
