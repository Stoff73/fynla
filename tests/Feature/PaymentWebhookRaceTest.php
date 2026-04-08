<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PaymentController;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\RevolutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
});

it('generates invoice when webhook beats confirmPayment (race condition)', function () {
    // Arrange: create user with subscription and a payment that
    // the webhook has already marked as 'completed' — but with no invoice
    $user = User::factory()->create(['plan' => 'standard']);
    $subscription = Subscription::create([
        'user_id' => $user->id,
        'plan' => 'standard',
        'billing_cycle' => 'yearly',
        'status' => 'active',
        'amount' => 10000,
        'current_period_start' => now(),
        'current_period_end' => now()->addYear(),
    ]);

    $orderId = (string) Str::uuid();
    $payment = Payment::create([
        'subscription_id' => $subscription->id,
        'user_id' => $user->id,
        'revolut_order_id' => $orderId,
        'amount' => 10000,
        'currency' => 'GBP',
        'status' => 'completed',        // Webhook already set this
        'description' => 'Standard Yearly',
        'plan_slug' => 'standard',
        'billing_cycle' => 'yearly',
        'discount_amount' => 0,
        'invoice_id' => null,            // No invoice — webhook doesn't generate one
        'revolut_payment_data' => ['state' => 'completed', 'id' => $orderId],
    ]);

    // Verify precondition: payment is completed but has no invoice
    expect($payment->status)->toBe('completed');
    expect($payment->invoice_id)->toBeNull();

    // Mock RevolutService so it doesn't call the real API
    $mockRevolut = Mockery::mock(RevolutService::class);
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

    // Act: call confirmPayment (simulating frontend onSuccess callback)
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/confirm', ['order_id' => $orderId]);

    // Assert: response is success
    $response->assertOk();
    $response->assertJsonPath('success', true);

    // Assert: invoice was generated despite 'already_completed'
    $payment->refresh();
    expect($payment->invoice_id)->not->toBeNull();

    // Assert: invoice record exists with correct amounts
    $invoice = \App\Models\Invoice::find($payment->invoice_id);
    expect($invoice)->not->toBeNull();
    expect($invoice->user_id)->toBe($user->id);
    expect($invoice->payment_id)->toBe($payment->id);
    expect((int) $invoice->total_amount)->toBe(10000);
    expect($invoice->status)->toBe('issued');
    expect($invoice->pdf_path)->not->toBeNull();

    // Assert: PDF file was created
    expect(\Illuminate\Support\Facades\Storage::exists($invoice->pdf_path))->toBeTrue();

    Mockery::close();
});
