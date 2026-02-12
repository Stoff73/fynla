<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('Revolut webhook: invalid signature');

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? null;
        $orderData = $payload['order'] ?? $payload;

        Log::info('Revolut webhook received', ['event' => $event, 'order_id' => $orderData['id'] ?? null]);

        match ($event) {
            'ORDER_COMPLETED' => $this->handleOrderCompleted($orderData),
            'ORDER_PAYMENT_FAILED' => $this->handlePaymentFailed($orderData),
            default => Log::info('Revolut webhook: unhandled event', ['event' => $event]),
        };

        return response()->json(['status' => 'ok']);
    }

    private function handleOrderCompleted(array $orderData): void
    {
        $orderId = $orderData['id'] ?? null;
        if (! $orderId) {
            return;
        }

        $subscription = Subscription::where('revolut_order_id', $orderId)->first();
        if (! $subscription) {
            Log::warning('Revolut webhook: subscription not found for order', ['order_id' => $orderId]);

            return;
        }

        $subscription->update([
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => $subscription->billing_cycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth(),
        ]);

        Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'revolut_order_id' => $orderId,
            'amount' => $subscription->amount,
            'currency' => 'GBP',
            'status' => 'completed',
            'revolut_payment_data' => $orderData,
        ]);

        $subscription->user()->update(['plan' => $subscription->plan]);

        Log::info('Revolut webhook: subscription activated', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
        ]);
    }

    private function handlePaymentFailed(array $orderData): void
    {
        $orderId = $orderData['id'] ?? null;
        if (! $orderId) {
            return;
        }

        $subscription = Subscription::where('revolut_order_id', $orderId)->first();
        if (! $subscription) {
            return;
        }

        $subscription->update(['status' => 'past_due']);

        Log::warning('Revolut webhook: payment failed', [
            'subscription_id' => $subscription->id,
            'order_id' => $orderId,
        ]);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = config('services.revolut.webhook_secret');
        if (empty($secret)) {
            return true; // Skip verification in dev if no secret configured
        }

        $signature = $request->header('Revolut-Signature') ?? $request->header('X-Revolut-Signature');
        if (! $signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
