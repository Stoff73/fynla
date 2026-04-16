<?php

declare(strict_types=1);

use App\Jobs\FireAwinConversionJob;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\RevolutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
    $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);

    config()->set('awin.enabled', true);
    config()->set('awin.merchant_id', '126105');
    config()->set('awin.s2s_base_url', 'https://www.awin1.com/sread.php');
    config()->set('awin.default_commission_group', 'SUB');
    config()->set('awin.cookie_domain', 'fynla.org');
});

/**
 * Helper: mock RevolutService so we do not hit the real API in tests.
 */
function mockRevolutForCreateOrder(): void
{
    $mock = Mockery::mock(RevolutService::class);
    $mock->shouldReceive('createOrderWithCustomer')
        ->andReturn([
            'id' => (string) Str::uuid(),
            'token' => 'test_token',
            'state' => 'pending',
            'created_at' => now()->toIso8601String(),
        ]);
    app()->instance(RevolutService::class, $mock);
}

function mockRevolutForConfirm(string $orderId): void
{
    $mock = Mockery::mock(RevolutService::class);
    $mock->shouldReceive('getOrder')
        ->with($orderId)
        ->andReturn([
            'id' => $orderId,
            'state' => 'completed',
            'capture_mode' => 'automatic',
            'amount' => 1099,
            'currency' => 'GBP',
        ]);
    app()->instance(RevolutService::class, $mock);
}

afterEach(function () {
    Mockery::close();
});

describe('createOrder captures Awin attribution', function () {
    it('stores awc cookie into payments.awin_cks', function () {
        mockRevolutForCreateOrder();
        $user = User::factory()->create(['revolut_customer_id' => 'cust_123']);

        $response = $this->actingAs($user)
            ->withCredentials()
            ->withUnencryptedCookie('awc', 'click-ref-xyz')
            ->postJson('/api/payment/create-order', [
                'plan' => 'standard',
                'billing_cycle' => 'monthly',
            ]);

        $response->assertOk();

        $payment = Payment::where('user_id', $user->id)->latest()->first();
        expect($payment)->not->toBeNull();
        expect($payment->awin_cks)->toBe('click-ref-xyz');
        expect($payment->awin_order_ref)->toBe("FYN-PAY-{$payment->id}");
    });

    it('marks first-time buyer as customer_acquisition=new', function () {
        mockRevolutForCreateOrder();
        $user = User::factory()->create(['revolut_customer_id' => 'cust_123']);

        $this->actingAs($user)
            ->postJson('/api/payment/create-order', [
                'plan' => 'standard',
                'billing_cycle' => 'monthly',
            ])->assertOk();

        $payment = Payment::where('user_id', $user->id)->latest()->first();
        expect($payment->awin_customer_acquisition)->toBe('new');
    });

    it('marks repeat buyer as customer_acquisition=existing', function () {
        mockRevolutForCreateOrder();
        $user = User::factory()->create(['revolut_customer_id' => 'cust_123']);

        // Give the user a prior completed payment
        Payment::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->postJson('/api/payment/create-order', [
                'plan' => 'standard',
                'billing_cycle' => 'monthly',
            ])->assertOk();

        $payment = Payment::where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->first();
        expect($payment->awin_customer_acquisition)->toBe('existing');
    });

    it('skips Awin capture when AWIN_ENABLED is false', function () {
        config()->set('awin.enabled', false);
        mockRevolutForCreateOrder();
        $user = User::factory()->create(['revolut_customer_id' => 'cust_123']);

        $this->actingAs($user)
            ->withCredentials()
            ->withUnencryptedCookie('awc', 'should-be-ignored')
            ->postJson('/api/payment/create-order', [
                'plan' => 'standard',
                'billing_cycle' => 'monthly',
            ])->assertOk();

        $payment = Payment::where('user_id', $user->id)->latest()->first();
        expect($payment->awin_cks)->toBeNull();
        expect($payment->awin_order_ref)->toBeNull();
        expect($payment->awin_customer_acquisition)->toBeNull();
    });

    it('does not capture Awin fields for admin users', function () {
        mockRevolutForCreateOrder();
        $user = User::factory()->create([
            'revolut_customer_id' => 'cust_123',
            'is_admin' => true,
        ]);

        $this->actingAs($user)
            ->withCredentials()
            ->withUnencryptedCookie('awc', 'should-be-ignored')
            ->postJson('/api/payment/create-order', [
                'plan' => 'standard',
                'billing_cycle' => 'monthly',
            ])->assertOk();

        $payment = Payment::where('user_id', $user->id)->latest()->first();
        expect($payment->awin_cks)->toBeNull();
        expect($payment->awin_customer_acquisition)->toBeNull();
    });
});

describe('confirmPayment dispatches the conversion job', function () {
    it('dispatches FireAwinConversionJob after successful confirmation', function () {
        Bus::fake([FireAwinConversionJob::class]);

        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'amount' => 1099,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $orderId = (string) Str::uuid();
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => $orderId,
            'amount' => 1099,
            'currency' => 'GBP',
            'status' => 'pending',
            'description' => 'Standard Monthly',
            'plan_slug' => 'standard',
            'billing_cycle' => 'monthly',
            'discount_amount' => 0,
            'revolut_payment_data' => ['state' => 'pending', 'id' => $orderId],
            'awin_order_ref' => null,
            'awin_customer_acquisition' => 'new',
        ]);

        mockRevolutForConfirm($orderId);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/confirm', ['order_id' => $orderId])
            ->assertOk();

        Bus::assertDispatched(FireAwinConversionJob::class, function ($job) use ($payment) {
            return $job->paymentId === $payment->id;
        });
    });

    it('does not dispatch the job when AWIN_ENABLED is false', function () {
        config()->set('awin.enabled', false);
        Bus::fake([FireAwinConversionJob::class]);

        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'amount' => 1099,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $orderId = (string) Str::uuid();
        Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => $orderId,
            'amount' => 1099,
            'currency' => 'GBP',
            'status' => 'pending',
            'description' => 'Standard Monthly',
            'plan_slug' => 'standard',
            'billing_cycle' => 'monthly',
            'discount_amount' => 0,
            'revolut_payment_data' => ['state' => 'pending', 'id' => $orderId],
        ]);

        mockRevolutForConfirm($orderId);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/confirm', ['order_id' => $orderId])
            ->assertOk();

        Bus::assertNotDispatched(FireAwinConversionJob::class);
    });

    it('does not dispatch the job for admin users', function () {
        Bus::fake([FireAwinConversionJob::class]);

        $user = User::factory()->create(['is_admin' => true]);
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'amount' => 1099,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $orderId = (string) Str::uuid();
        Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => $orderId,
            'amount' => 1099,
            'currency' => 'GBP',
            'status' => 'pending',
            'description' => 'Standard Monthly',
            'plan_slug' => 'standard',
            'billing_cycle' => 'monthly',
            'discount_amount' => 0,
            'revolut_payment_data' => ['state' => 'pending', 'id' => $orderId],
        ]);

        mockRevolutForConfirm($orderId);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/confirm', ['order_id' => $orderId])
            ->assertOk();

        Bus::assertNotDispatched(FireAwinConversionJob::class);
    });
});

describe('end-to-end: createOrder → confirmPayment → Awin S2S', function () {
    it('fires the full conversion when run synchronously', function () {
        Http::fake(['www.awin1.com/*' => Http::response('OK', 200)]);
        // Run jobs synchronously so we can verify the full chain
        config()->set('queue.default', 'sync');

        mockRevolutForCreateOrder();
        $user = User::factory()->create(['revolut_customer_id' => 'cust_123']);

        // Step 1: createOrder — captures awc cookie
        $createResponse = $this->actingAs($user, 'sanctum')
            ->withCredentials()
            ->withUnencryptedCookie('awc', 'e2e-test-click')
            ->postJson('/api/payment/create-order', [
                'plan' => 'standard',
                'billing_cycle' => 'monthly',
            ]);

        $createResponse->assertOk();
        $orderId = $createResponse->json('order_id');

        $payment = Payment::where('revolut_order_id', $orderId)->first();
        expect($payment->awin_cks)->toBe('e2e-test-click');
        expect($payment->awin_customer_acquisition)->toBe('new');

        // Step 2: confirmPayment — fires the job (sync) → hits Http fake
        mockRevolutForConfirm($orderId);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/confirm', ['order_id' => $orderId])
            ->assertOk();

        // Step 3: assert Awin S2S call contained the right params
        Http::assertSent(function ($request) use ($payment) {
            $data = $request->data();

            return str_starts_with($request->url(), 'https://www.awin1.com/sread.php')
                && $data['merchant'] === '126105'
                && $data['amount'] === '10.99'
                && $data['ref'] === "FYN-PAY-{$payment->id}"
                && $data['parts'] === 'SUB:10.99'
                && $data['customeracquisition'] === 'new'
                && $data['cks'] === 'e2e-test-click';
        });

        // Step 4: awin_fired_at populated → idempotency guard active
        expect($payment->fresh()->awin_fired_at)->not->toBeNull();
    });
});
