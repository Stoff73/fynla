<?php

declare(strict_types=1);

use App\Models\DiscountCode;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\RevolutService;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
    Mail::fake();
});

afterEach(fn () => Mockery::close());

/**
 * @return array{Subscription, Payment}
 */
function pendingPaymentFor(User $user, string $plan = 'premium', int $amount = 1499): array
{
    $subscription = Subscription::factory()->plan($plan)->billingCycle('monthly')->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'amount' => 0,
    ]);
    $payment = Payment::factory()->pending()->create([
        'subscription_id' => $subscription->id,
        'user_id' => $user->id,
        'revolut_order_id' => fake()->uuid(),
        'plan_slug' => $plan,
        'billing_cycle' => 'monthly',
        'amount' => $amount,
        'currency' => 'GBP',
    ]);

    return [$subscription, $payment];
}

it('does not grant Premium for a non-final Revolut order', function (string $state, string $captureMode) {
    $user = User::factory()->create(['tier' => null]);
    [$subscription, $payment] = pendingPaymentFor($user);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('getOrder')->with($payment->revolut_order_id)->once()->andReturn([
        'id' => $payment->revolut_order_id,
        'state' => $state,
        'capture_mode' => $captureMode,
        'amount' => 1499,
        'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/confirm', ['order_id' => $payment->revolut_order_id])
        ->assertBadRequest();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($subscription->fresh()->status)->toBe('pending')
        ->and($user->fresh()->tier)->toBeNull();
})->with([
    ['pending', 'automatic'],
    ['processing', 'automatic'],
    ['authorised', 'manual'],
]);

it('does not grant Premium when the completed Revolut order amount or currency differs', function (int $amount, string $currency) {
    $user = User::factory()->create(['tier' => null]);
    [$subscription, $payment] = pendingPaymentFor($user);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('getOrder')->once()->andReturn([
        'id' => $payment->revolut_order_id,
        'state' => 'completed',
        'capture_mode' => 'automatic',
        'amount' => $amount,
        'currency' => $currency,
    ]);
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/confirm', ['order_id' => $payment->revolut_order_id])
        ->assertBadRequest();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($subscription->fresh()->status)->toBe('pending')
        ->and($user->fresh()->tier)->toBeNull();
})->with([
    'amount mismatch' => [1498, 'GBP'],
    'currency mismatch' => [1499, 'USD'],
]);

it('does not reactivate a locally failed or refunded payment from confirmation', function (string $status) {
    $user = User::factory()->create(['tier' => null]);
    [$subscription, $payment] = pendingPaymentFor($user);
    $payment->update(['status' => $status]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldNotReceive('getOrder');
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/confirm', ['order_id' => $payment->revolut_order_id])
        ->assertConflict();

    expect($payment->fresh()->status)->toBe($status)
        ->and($subscription->fresh()->status)->toBe('pending')
        ->and($user->fresh()->tier)->toBeNull();
})->with(['failed', 'refunded']);

it('does not acknowledge a completion webhook for a locally failed or refunded payment', function (string $status) {
    $user = User::factory()->create(['tier' => null]);
    [$subscription, $payment] = pendingPaymentFor($user);
    $payment->update(['status' => $status]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $revolut->shouldNotReceive('getOrder');
    app()->instance(RevolutService::class, $revolut);

    $this->postJson('/api/webhooks/revolut', [
        'event' => 'ORDER_COMPLETED',
        'order_id' => $payment->revolut_order_id,
        'merchant_order_ext_ref' => "payment_{$payment->id}",
    ], [
        'Revolut-Signature' => 'v1=stub',
        'Revolut-Request-Timestamp' => (string) (int) (microtime(true) * 1000),
    ])->assertServerError();

    expect($payment->fresh()->status)->toBe($status)
        ->and($subscription->fresh()->status)->toBe('pending')
        ->and($user->fresh()->tier)->toBeNull();
})->with(['failed', 'refunded']);

it('does not acknowledge an unmatched completion webhook', function () {
    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $revolut->shouldNotReceive('getOrder');
    app()->instance(RevolutService::class, $revolut);

    $this->postJson('/api/webhooks/revolut', [
        'event' => 'ORDER_COMPLETED',
        'order_id' => fake()->uuid(),
        'merchant_order_ext_ref' => 'payment_999999',
    ], [
        'Revolut-Signature' => 'v1=stub',
        'Revolut-Request-Timestamp' => (string) (int) (microtime(true) * 1000),
    ])->assertServerError();
});

it('canonicalises a trusted retired-tier payment on confirmation without rewriting its history', function () {
    $user = User::factory()->create(['plan' => 'tier2', 'tier' => null]);
    [$subscription, $payment] = pendingPaymentFor($user, 'tier2');

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('getOrder')->once()->andReturn([
        'id' => $payment->revolut_order_id,
        'state' => 'completed',
        'capture_mode' => 'automatic',
        'amount' => 1499,
        'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/confirm', ['order_id' => $payment->revolut_order_id])
        ->assertOk();

    expect($payment->fresh()->plan_slug)->toBe('tier2')
        ->and((int) $payment->fresh()->amount)->toBe(1499)
        ->and($subscription->fresh()->plan)->toBe('premium')
        ->and($user->fresh()->plan)->toBe('premium')
        ->and($user->fresh()->tier)->toBe('premium');
});

it('canonicalises a trusted retired-tier payment completed by webhook', function () {
    $user = User::factory()->create(['plan' => 'tier3', 'tier' => null]);
    [$subscription, $payment] = pendingPaymentFor($user, 'tier3');

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $revolut->shouldReceive('getOrder')->once()->andReturn([
        'id' => $payment->revolut_order_id,
        'state' => 'completed',
        'capture_mode' => 'automatic',
        'amount' => 1499,
        'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $revolut);

    $this->postJson('/api/webhooks/revolut', [
        'event' => 'ORDER_COMPLETED',
        'order_id' => $payment->revolut_order_id,
        'merchant_order_ext_ref' => "payment_{$payment->id}",
    ], [
        'Revolut-Signature' => 'v1=stub',
        'Revolut-Request-Timestamp' => (string) (int) (microtime(true) * 1000),
    ])->assertOk();

    expect($payment->fresh()->plan_slug)->toBe('tier3')
        ->and((int) $payment->fresh()->amount)->toBe(1499)
        ->and($subscription->fresh()->plan)->toBe('premium')
        ->and($user->fresh()->plan)->toBe('premium')
        ->and($user->fresh()->tier)->toBe('premium');
});

it('preserves upgrade period dates and stores the full Premium renewal price from webhook', function () {
    $user = User::factory()->create(['plan' => 'free', 'tier' => 'free']);
    $periodStart = now()->subMonths(3);
    $periodEnd = now()->addMonths(9);
    $subscription = Subscription::factory()->plan('free')->billingCycle('yearly')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'amount' => 0,
        'current_period_start' => $periodStart,
        'current_period_end' => $periodEnd,
    ]);
    $payment = Payment::factory()->pending()->create([
        'subscription_id' => $subscription->id,
        'user_id' => $user->id,
        'revolut_order_id' => fake()->uuid(),
        'plan_slug' => 'premium',
        'billing_cycle' => 'yearly',
        'upgrade_from_plan' => 'free',
        'amount' => 11241,
        'currency' => 'GBP',
    ]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $revolut->shouldReceive('getOrder')->once()->andReturn([
        'id' => $payment->revolut_order_id,
        'state' => 'completed',
        'amount' => 11241,
        'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $revolut);

    $this->postJson('/api/webhooks/revolut', [
        'event' => 'ORDER_COMPLETED',
        'order_id' => $payment->revolut_order_id,
        'merchant_order_ext_ref' => "payment_{$payment->id}",
    ], [
        'Revolut-Signature' => 'v1=stub',
        'Revolut-Request-Timestamp' => (string) (int) (microtime(true) * 1000),
    ])->assertOk();

    $subscription->refresh();
    expect($subscription->plan)->toBe('premium')
        ->and((int) $subscription->amount)->toBe(5999)
        ->and($subscription->current_period_start->format('Y-m-d'))->toBe($periodStart->format('Y-m-d'))
        ->and($subscription->current_period_end->format('Y-m-d'))->toBe($periodEnd->format('Y-m-d'))
        ->and((int) $payment->fresh()->amount)->toBe(11241);
});

it('charges and persists the validated Premium discount amount', function () {
    $user = User::factory()->create(['revolut_customer_id' => 'cust_discount']);
    $discount = DiscountCode::factory()->percentage(10)->forPlans(['premium'])->create([
        'code' => 'PREMIUM10',
    ]);

    $merchantReference = null;
    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('createOrderWithCustomer')
        ->once()
        ->withArgs(function (int $amount, mixed ...$arguments) use (&$merchantReference): bool {
            $merchantReference = $arguments[4] ?? null;

            return $amount === 629;
        })
        ->andReturn([
            'id' => 'order_discount',
            'token' => 'token_discount',
            'state' => 'pending',
            'created_at' => now()->toIso8601String(),
        ]);
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
            'discount_code' => 'PREMIUM10',
        ])
        ->assertOk();

    $payment = Payment::where('user_id', $user->id)->latest()->first();
    expect((int) $payment->amount)->toBe(629)
        ->and((int) $payment->discount_amount)->toBe(70)
        ->and($payment->discount_code_id)->toBe($discount->id)
        ->and($merchantReference)->toBe("payment_{$payment->id}");
});
