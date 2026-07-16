<?php

declare(strict_types=1);

use App\Jobs\FireAwinConversionJob;
use App\Mail\PaymentConfirmation;
use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\PaymentFinalizationService;
use App\Services\Payment\RevolutService;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

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
        ->and($subscription->fresh()->auto_renew)->toBeFalse()
        ->and($subscription->fresh()->payment_method_saved)->toBeFalse()
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
        ->and($subscription->fresh()->auto_renew)->toBeFalse()
        ->and($subscription->fresh()->payment_method_saved)->toBeFalse()
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

it('rejects a discount that would create a zero-value Premium order', function () {
    $user = User::factory()->create(['revolut_customer_id' => 'customer_zero_discount']);
    DiscountCode::factory()->fixedAmount(1000)->forPlans(['premium'])->create([
        'code' => 'TOO-MUCH',
    ]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldNotReceive('createOrderWithCustomer');
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
            'discount_code' => 'TOO-MUCH',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    expect(Payment::where('user_id', $user->id)->exists())->toBeFalse();
});

it('records a completed Premium order as one-time access without renewal claims', function () {
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);
    [$subscription, $payment] = pendingPaymentFor($user, 'premium', 699);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('getOrder')->once()->andReturn([
        'id' => $payment->revolut_order_id,
        'state' => 'completed',
        'capture_mode' => 'automatic',
        'amount' => 699,
        'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/confirm', ['order_id' => $payment->revolut_order_id])
        ->assertOk();

    $subscription->refresh();
    expect($subscription->auto_renew)->toBeFalse()
        ->and($subscription->payment_method_saved)->toBeFalse();

    $this->actingAs($user->fresh(), 'sanctum')
        ->getJson('/api/payment/subscription-status')
        ->assertOk()
        ->assertJsonPath('auto_renew', false)
        ->assertJsonPath('next_renewal_date', null);
});

it('detaches legacy recurring state when reactivating a terminal row as one-time Premium', function () {
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);
    $subscription = Subscription::factory()->expired()->plan('premium')->create([
        'user_id' => $user->id,
        'revolut_subscription_id' => 'legacy_recurring_subscription',
        'auto_renew' => true,
        'data_retention_starts_at' => now()->subDays(5),
    ]);
    $payment = Payment::factory()->pending()->create([
        'subscription_id' => $subscription->id,
        'user_id' => $user->id,
        'revolut_order_id' => fake()->uuid(),
        'plan_slug' => 'premium',
        'billing_cycle' => 'monthly',
        'amount' => 699,
        'currency' => 'GBP',
    ]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('getOrder')->once()->andReturn([
        'id' => $payment->revolut_order_id,
        'state' => 'completed',
        'capture_mode' => 'automatic',
        'amount' => 699,
        'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/confirm', ['order_id' => $payment->revolut_order_id])
        ->assertOk();

    expect($subscription->fresh()->status)->toBe('active')
        ->and($subscription->fresh()->auto_renew)->toBeFalse()
        ->and($subscription->fresh()->revolut_subscription_id)->toBeNull()
        ->and($subscription->fresh()->data_retention_starts_at)->toBeNull();
});

it('does not replace an active Premium access period with a new checkout', function () {
    $user = User::factory()->create([
        'tier' => 'premium',
        'plan' => 'premium',
        'revolut_customer_id' => 'active_premium_customer',
    ]);
    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => false,
        'current_period_end' => now()->addMonth(),
    ]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldNotReceive('createOrderWithCustomer');
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
        ])
        ->assertConflict();

    expect(Payment::where('user_id', $user->id)->exists())->toBeFalse();
});

it('does not miss an older active Premium period when a newer provisional subscription exists', function () {
    $user = User::factory()->create([
        'tier' => 'premium',
        'plan' => 'premium',
        'revolut_customer_id' => 'active_premium_with_newer_row',
    ]);
    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => false,
        'current_period_end' => now()->addMonth(),
    ]);
    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'pending',
        'current_period_start' => null,
        'current_period_end' => null,
    ]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldNotReceive('createOrderWithCustomer');
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
        ])
        ->assertConflict();

    expect(Payment::where('user_id', $user->id)->exists())->toBeFalse();
});

it('returns an existing open order for an identical checkout retry', function () {
    $user = User::factory()->create([
        'tier' => 'free',
        'plan' => 'free',
        'revolut_customer_id' => 'customer_retry',
    ]);
    [, $payment] = pendingPaymentFor($user, 'premium', 699);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('getOrder')->with($payment->revolut_order_id)->once()->andReturn([
        'id' => $payment->revolut_order_id,
        'token' => 'existing_token',
        'state' => 'pending',
    ]);
    $revolut->shouldNotReceive('cancelOrder');
    $revolut->shouldNotReceive('createOrderWithCustomer');
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
        ])
        ->assertOk()
        ->assertJsonPath('order_id', $payment->revolut_order_id)
        ->assertJsonPath('token', 'existing_token');

    expect(Payment::where('user_id', $user->id)->count())->toBe(1)
        ->and($payment->fresh()->status)->toBe('pending');
});

it('cancels an open remote order before replacing it with different checkout terms', function () {
    $user = User::factory()->create([
        'tier' => 'free',
        'plan' => 'free',
        'revolut_customer_id' => 'customer_replace',
    ]);
    [$subscription, $oldPayment] = pendingPaymentFor($user, 'premium', 699);
    DiscountCode::factory()->percentage(10)->forPlans(['premium'])->create([
        'code' => 'REPLACE10',
    ]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('getOrder')->with($oldPayment->revolut_order_id)->once()->andReturn([
        'id' => $oldPayment->revolut_order_id,
        'state' => 'pending',
    ]);
    $revolut->shouldReceive('cancelOrder')->with($oldPayment->revolut_order_id)->once()->andReturn([
        'id' => $oldPayment->revolut_order_id,
        'state' => 'cancelled',
    ]);
    $revolut->shouldReceive('createOrderWithCustomer')
        ->once()
        ->withArgs(fn (int $amount, string $currency, string $description, string $redirectUrl, string $customerId, string $merchantRef, string $email, bool $savePaymentMethod): bool => $amount === 629
            && $currency === 'GBP'
            && $customerId === 'customer_replace'
            && str_starts_with($merchantRef, 'payment_')
            && $savePaymentMethod === false)
        ->andReturn([
            'id' => 'replacement_order',
            'token' => 'replacement_token',
            'state' => 'pending',
            'created_at' => now()->toIso8601String(),
        ]);
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
            'discount_code' => 'REPLACE10',
        ])
        ->assertOk()
        ->assertJsonPath('order_id', 'replacement_order');

    expect($oldPayment->fresh()->status)->toBe('failed')
        ->and(Payment::withTrashed()->find($oldPayment->id)->deleted_at)->toBeNull()
        ->and(Payment::where('revolut_order_id', 'replacement_order')->exists())->toBeTrue()
        ->and($subscription->fresh()->status)->toBe('pending');
});

it('cancels a monthly checkout before opening a yearly checkout for the same user', function () {
    $user = User::factory()->create([
        'tier' => 'free',
        'plan' => 'free',
        'revolut_customer_id' => 'customer_cycle_change',
    ]);
    [, $monthlyPayment] = pendingPaymentFor($user, 'premium', 699);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('getOrder')->with($monthlyPayment->revolut_order_id)->once()->andReturn([
        'id' => $monthlyPayment->revolut_order_id,
        'state' => 'pending',
    ]);
    $revolut->shouldReceive('cancelOrder')->with($monthlyPayment->revolut_order_id)->once()->andReturn([
        'id' => $monthlyPayment->revolut_order_id,
        'state' => 'cancelled',
    ]);
    $revolut->shouldReceive('createOrderWithCustomer')
        ->once()
        ->withArgs(fn (int $amount): bool => $amount === 5999)
        ->andReturn([
            'id' => 'yearly_order',
            'token' => 'yearly_token',
            'state' => 'pending',
            'created_at' => now()->toIso8601String(),
        ]);
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'premium',
            'billing_cycle' => 'yearly',
        ])
        ->assertOk()
        ->assertJsonPath('order_id', 'yearly_order');

    expect($monthlyPayment->fresh()->status)->toBe('failed')
        ->and(Payment::where('user_id', $user->id)->where('status', 'pending')->count())->toBe(1)
        ->and(Payment::where('revolut_order_id', 'yearly_order')->sole()->active_checkout_user_id)->toBe($user->id);
});

it('reconciles a provider timeout when Revolut created the order', function () {
    $user = User::factory()->create([
        'tier' => 'free',
        'plan' => 'free',
        'revolut_customer_id' => 'customer_timeout_reconciled',
    ]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('createOrderWithCustomer')->once()->andThrow(new RuntimeException('timeout'));
    $revolut->shouldReceive('findOrderByMerchantReference')
        ->once()
        ->withArgs(fn (string $reference): bool => str_starts_with($reference, 'payment_'))
        ->andReturn([
            'id' => 'reconciled_order',
            'token' => 'reconciled_token',
            'state' => 'pending',
            'created_at' => now()->toIso8601String(),
        ]);
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
        ])
        ->assertOk()
        ->assertJsonPath('order_id', 'reconciled_order')
        ->assertJsonPath('token', 'reconciled_token');

    expect(Payment::where('user_id', $user->id)->sole()->revolut_order_id)->toBe('reconciled_order');
});

it('retains an uncertain checkout and blocks a duplicate provider order', function () {
    $user = User::factory()->create([
        'tier' => 'free',
        'plan' => 'free',
        'revolut_customer_id' => 'customer_timeout_unknown',
    ]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('createOrderWithCustomer')->once()->andThrow(new RuntimeException('timeout'));
    $revolut->shouldReceive('findOrderByMerchantReference')->twice()->andReturnNull();
    app()->instance(RevolutService::class, $revolut);

    $payload = ['plan' => 'premium', 'billing_cycle' => 'monthly'];
    $this->actingAs($user, 'sanctum')->postJson('/api/payment/create-order', $payload)->assertServerError();
    $this->actingAs($user, 'sanctum')->postJson('/api/payment/create-order', $payload)->assertConflict();

    $payment = Payment::where('user_id', $user->id)->sole();
    expect($payment->status)->toBe('pending')
        ->and($payment->revolut_order_id)->toStartWith('pending_');
});

it('releases an old uncertain checkout only after two authoritative reconciliation misses', function () {
    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);
    [$subscription, $payment] = pendingPaymentFor($user, 'premium', 699);
    $payment->forceFill([
        'revolut_order_id' => 'pending_'.$payment->id,
        'created_at' => now()->subHour(),
    ])->save();

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('findOrderByMerchantReference')->twice()->andReturnNull();
    app()->instance(RevolutService::class, $revolut);

    expect(Artisan::call('payments:reconcile-pending', ['--older-than' => 15]))->toBe(0)
        ->and($payment->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->reconciliation_misses)->toBe(1)
        ->and(Artisan::call('payments:reconcile-pending', ['--older-than' => 15]))->toBe(0)
        ->and($payment->fresh()->status)->toBe('failed')
        ->and($payment->fresh()->active_checkout_user_id)->toBeNull()
        ->and($subscription->fresh()->status)->toBe('pending');
});

it('recovers a settled payment after transient finalization failure and dispatches Awin', function () {
    config(['awin.enabled' => true]);
    Queue::fake();

    $user = User::factory()->create(['tier' => 'free', 'plan' => 'free']);
    [, $payment] = pendingPaymentFor($user, 'premium', 699);
    $payment->forceFill(['created_at' => now()->subHour()])->save();

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('getOrder')->once()->andReturn([
        'id' => $payment->revolut_order_id,
        'state' => 'completed',
        'capture_mode' => 'automatic',
        'amount' => 699,
        'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $revolut);

    $attempts = 0;
    $finalization = Mockery::mock(PaymentFinalizationService::class);
    $finalization->shouldReceive('finalize')->twice()->andReturnUsing(
        function (Payment $current) use (&$attempts): Payment {
            $attempts++;
            if ($attempts === 1) {
                throw new RuntimeException('temporary invoice storage failure');
            }

            $current->update([
                'financial_finalized_at' => now(),
                'confirmation_email_sent_at' => now(),
            ]);

            return $current->fresh(['user']);
        }
    );
    app()->instance(PaymentFinalizationService::class, $finalization);

    expect(Artisan::call('payments:reconcile-pending', ['--older-than' => 15]))->toBe(0)
        ->and($payment->fresh()->status)->toBe('completed')
        ->and($payment->fresh()->financial_finalized_at)->toBeNull()
        ->and(Artisan::call('payments:reconcile-pending', ['--older-than' => 15]))->toBe(0)
        ->and($payment->fresh()->financial_finalized_at)->not->toBeNull();

    Queue::assertPushed(
        FireAwinConversionJob::class,
        fn (FireAwinConversionJob $job): bool => $job->paymentId === $payment->id
    );
});

it('does not duplicate a confirmation email while another finalizer owns a fresh claim', function () {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    [, $payment] = pendingPaymentFor($user, 'premium', 699);
    $payment->update([
        'status' => 'completed',
        'confirmation_email_claimed_at' => now(),
    ]);

    app(PaymentFinalizationService::class)->finalize($payment);

    expect($payment->fresh()->financial_finalized_at)->not->toBeNull()
        ->and($payment->fresh()->confirmation_email_sent_at)->toBeNull()
        ->and($payment->fresh()->confirmation_email_claimed_at)->not->toBeNull();
    Mail::assertNotSent(PaymentConfirmation::class);
});

it('does not replace a pending local payment when Revolut has already completed it', function () {
    $user = User::factory()->create([
        'tier' => 'free',
        'plan' => 'free',
        'revolut_customer_id' => 'customer_completed',
    ]);
    [, $oldPayment] = pendingPaymentFor($user, 'premium', 699);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('getOrder')->with($oldPayment->revolut_order_id)->once()->andReturn([
        'id' => $oldPayment->revolut_order_id,
        'state' => 'completed',
    ]);
    $revolut->shouldNotReceive('cancelOrder');
    $revolut->shouldNotReceive('createOrderWithCustomer');
    app()->instance(RevolutService::class, $revolut);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
        ])
        ->assertConflict();

    expect($oldPayment->fresh()->status)->toBe('pending')
        ->and(Payment::where('user_id', $user->id)->count())->toBe(1);
});

it('reserves the last discount use while its Premium order is pending', function () {
    $discount = DiscountCode::factory()->percentage(10)->forPlans(['premium'])->create([
        'code' => 'LASTUSE',
        'max_uses' => 1,
        'times_used' => 0,
    ]);
    $first = User::factory()->create(['revolut_customer_id' => 'customer_first']);
    $second = User::factory()->create(['revolut_customer_id' => 'customer_second']);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('createOrderWithCustomer')->once()->andReturn([
        'id' => 'reserved_order',
        'token' => 'reserved_token',
        'state' => 'pending',
        'created_at' => now()->toIso8601String(),
    ]);
    app()->instance(RevolutService::class, $revolut);

    $payload = [
        'plan' => 'premium',
        'billing_cycle' => 'monthly',
        'discount_code' => 'LASTUSE',
    ];
    $this->actingAs($first, 'sanctum')->postJson('/api/payment/create-order', $payload)->assertOk();
    $this->actingAs($second, 'sanctum')->postJson('/api/payment/create-order', $payload)
        ->assertUnprocessable();

    expect(Payment::where('discount_code_id', $discount->id)->where('status', 'pending')->count())->toBe(1);
});

it('keeps payment finalisation effects idempotent when confirmation is replayed', function () {
    $referrer = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    $referrerSubscription = Subscription::factory()->plan('premium')->create([
        'user_id' => $referrer->id,
        'status' => 'active',
        'current_period_end' => now()->addMonth(),
    ]);
    $user = User::factory()->create([
        'tier' => 'free',
        'plan' => 'free',
        'referred_by_code' => 'FYN-REPLAY',
    ]);
    [$subscription, $payment] = pendingPaymentFor($user, 'premium', 629);
    $originalRefereeEnd = now()->addMonth()->startOfSecond();
    $subscription->update(['current_period_end' => $originalRefereeEnd]);
    $discount = DiscountCode::factory()->percentage(10)->forPlans(['premium'])->create();
    $payment->update(['discount_code_id' => $discount->id, 'discount_amount' => 70]);
    Referral::create([
        'referrer_id' => $referrer->id,
        'referee_id' => $user->id,
        'referral_code' => 'FYN-REPLAY',
        'referee_email' => $user->email,
        'status' => 'registered',
        'bonus_applied' => false,
        'referred_at' => now(),
    ]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('getOrder')->twice()->andReturn([
        'id' => $payment->revolut_order_id,
        'state' => 'completed',
        'capture_mode' => 'automatic',
        'amount' => 629,
        'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $revolut);

    $payload = ['order_id' => $payment->revolut_order_id];
    $this->actingAs($user, 'sanctum')->postJson('/api/payment/confirm', $payload)->assertOk();
    $this->actingAs($user, 'sanctum')->postJson('/api/payment/confirm', $payload)->assertOk();

    expect(Invoice::where('payment_id', $payment->id)->count())->toBe(1)
        ->and(DiscountCodeUsage::where('payment_id', $payment->id)->count())->toBe(1)
        ->and($discount->fresh()->times_used)->toBe(1)
        ->and(Referral::where('referee_id', $user->id)->firstOrFail()->bonus_applied)->toBeTrue()
        ->and($subscription->fresh()->current_period_end->equalTo($originalRefereeEnd->copy()->addWeek()))->toBeTrue()
        ->and($referrerSubscription->fresh()->current_period_end->greaterThan(now()->addMonth()))->toBeTrue();
    Mail::assertSent(PaymentConfirmation::class, 1);
});

it('completes every financial effect when the webhook is the only callback', function () {
    $referrer = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    $referrerSubscription = Subscription::factory()->plan('premium')->create([
        'user_id' => $referrer->id,
        'status' => 'active',
        'current_period_end' => now()->addMonth(),
    ]);
    $user = User::factory()->create([
        'tier' => 'free',
        'plan' => 'free',
        'referred_by_code' => 'FYN-WEBHOOK',
    ]);
    [$subscription, $payment] = pendingPaymentFor($user, 'premium', 629);
    $discount = DiscountCode::factory()->percentage(10)->forPlans(['premium'])->create();
    $payment->update(['discount_code_id' => $discount->id, 'discount_amount' => 70]);
    Referral::create([
        'referrer_id' => $referrer->id,
        'referee_id' => $user->id,
        'referral_code' => 'FYN-WEBHOOK',
        'referee_email' => $user->email,
        'status' => 'registered',
        'bonus_applied' => false,
        'referred_at' => now(),
    ]);

    $revolut = Mockery::mock(RevolutService::class);
    $revolut->shouldReceive('verifyWebhookSignature')->twice()->andReturnTrue();
    $revolut->shouldReceive('getOrder')->twice()->andReturn([
        'id' => $payment->revolut_order_id,
        'state' => 'completed',
        'capture_mode' => 'automatic',
        'amount' => 629,
        'currency' => 'GBP',
    ]);
    app()->instance(RevolutService::class, $revolut);

    $headers = [
        'Revolut-Signature' => 'v1=stub',
        'Revolut-Request-Timestamp' => (string) (int) (microtime(true) * 1000),
    ];
    $payload = [
        'event' => 'ORDER_COMPLETED',
        'order_id' => $payment->revolut_order_id,
        'merchant_order_ext_ref' => "payment_{$payment->id}",
    ];
    $this->postJson('/api/webhooks/revolut', $payload, $headers)->assertOk();
    $this->postJson('/api/webhooks/revolut', $payload, $headers)->assertOk();

    expect($payment->fresh()->status)->toBe('completed')
        ->and($payment->fresh()->financial_finalized_at)->not->toBeNull()
        ->and(Invoice::where('payment_id', $payment->id)->count())->toBe(1)
        ->and(DiscountCodeUsage::where('payment_id', $payment->id)->count())->toBe(1)
        ->and($discount->fresh()->times_used)->toBe(1)
        ->and(Referral::where('referee_id', $user->id)->firstOrFail()->bonus_applied)->toBeTrue()
        ->and($subscription->fresh()->status)->toBe('active')
        ->and($referrerSubscription->fresh()->current_period_end->greaterThan(now()->addMonth()))->toBeTrue();
    Mail::assertSent(PaymentConfirmation::class, 1);
});
