<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\RevolutService;
use Database\Seeders\SubscriptionPlanSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(SubscriptionPlanSeeder::class);
    Mail::fake();
});

it('generates the invoice when the webhook is the only completion callback', function () {
    $user = User::factory()->create(['plan' => 'free']);
    $subscription = Subscription::create([
        'user_id' => $user->id,
        'plan' => 'standard',
        'billing_cycle' => 'yearly',
        'status' => 'pending',
        'amount' => 0,
    ]);

    $orderId = (string) Str::uuid();
    $payment = Payment::create([
        'subscription_id' => $subscription->id,
        'user_id' => $user->id,
        'revolut_order_id' => $orderId,
        'amount' => 10000,
        'currency' => 'GBP',
        'status' => 'pending',
        'description' => 'Standard Yearly',
        'plan_slug' => 'standard',
        'billing_cycle' => 'yearly',
        'discount_amount' => 0,
        'invoice_id' => null,
    ]);

    $mockRevolut = Mockery::mock(RevolutService::class);
    $mockRevolut->shouldReceive('verifyWebhookSignature')->once()->andReturnTrue();
    $mockRevolut->shouldReceive('getOrder')
        ->with($orderId)
        ->andReturn([
            'id' => $orderId,
            'state' => 'completed',
            'capture_mode' => 'automatic',
            'amount' => 10000,
            'currency' => 'GBP',
        ]);
    $this->app->instance(RevolutService::class, $mockRevolut);

    $this->postJson('/api/webhooks/revolut', [
        'event' => 'ORDER_COMPLETED',
        'order_id' => $orderId,
        'merchant_order_ext_ref' => "payment_{$payment->id}",
    ], [
        'Revolut-Signature' => 'v1=stub',
        'Revolut-Request-Timestamp' => (string) (int) (microtime(true) * 1000),
    ])->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe('completed');
    expect($payment->invoice_id)->not->toBeNull();

    $invoice = Invoice::find($payment->invoice_id);
    expect($invoice)->not->toBeNull();
    expect($invoice->user_id)->toBe($user->id);
    expect($invoice->payment_id)->toBe($payment->id);
    expect((int) $invoice->total_amount)->toBe(10000);
    expect($invoice->status)->toBe('issued');
    expect($invoice->pdf_path)->not->toBeNull();

    expect(Storage::exists($invoice->pdf_path))->toBeTrue();

    Mockery::close();
});
