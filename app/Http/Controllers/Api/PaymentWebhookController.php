<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PaymentConfirmation;
use App\Mail\SubscriptionCancellation;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentWebhookController extends Controller
{
    /**
     * Maximum age (in seconds) for a webhook signature timestamp.
     * Rejects replayed webhooks older than 5 minutes.
     */
    private const SIGNATURE_MAX_AGE_SECONDS = 300;

    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('Revolut webhook: invalid signature');

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? null;

        Log::info('Revolut webhook received', [
            'event' => $event,
            'order_id' => $payload['order_id'] ?? $payload['order']['id'] ?? null,
            'subscription_id' => $payload['subscription_id'] ?? null,
        ]);

        match ($event) {
            // Order events (v1.0 — initial payment completion)
            'ORDER_COMPLETED' => $this->handleOrderCompleted($payload['order'] ?? $payload),
            'ORDER_PAYMENT_FAILED' => $this->handlePaymentFailed($payload['order'] ?? $payload),

            // Subscription events (Subscriptions API)
            'SUBSCRIPTION_INITIATED' => $this->handleSubscriptionInitiated($payload),
            'SUBSCRIPTION_FINISHED' => $this->handleSubscriptionFinished($payload),
            'SUBSCRIPTION_CANCELLED' => $this->handleSubscriptionCancelled($payload),
            'SUBSCRIPTION_OVERDUE' => $this->handleSubscriptionOverdue($payload),

            default => Log::info('Revolut webhook: unhandled event', ['event' => $event]),
        };

        return response()->json(['status' => 'ok']);
    }

    // -------------------------------------------------------------------------
    // Order event handlers
    // -------------------------------------------------------------------------

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

        // Clear grace period and cancellation data on resubscription
        $subscription->update([
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => $subscription->billing_cycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth(),
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'data_retention_starts_at' => null,
        ]);

        $billingLabel = $subscription->billing_cycle === 'yearly' ? 'Yearly' : 'Monthly';

        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'revolut_order_id' => $orderId,
            'amount' => $subscription->amount,
            'currency' => 'GBP',
            'status' => 'completed',
            'description' => ucfirst($subscription->plan).' Plan - '.$billingLabel,
            'revolut_payment_data' => array_intersect_key($orderData, array_flip([
                'id', 'type', 'state', 'created_at', 'completed_at',
                'order_amount', 'settlement_amount',
            ])),
        ]);

        $subscription->user()->update(['plan' => $subscription->plan]);

        // Send payment confirmation email
        $this->sendPaymentConfirmation($subscription, $payment);

        Log::info('Revolut webhook: subscription activated via order', [
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

    // -------------------------------------------------------------------------
    // Subscription event handlers
    // -------------------------------------------------------------------------

    /**
     * SUBSCRIPTION_INITIATED — Subscription created, awaiting first payment.
     * The setup order has been created but the user hasn't completed checkout yet.
     */
    private function handleSubscriptionInitiated(array $payload): void
    {
        $subscription = $this->findSubscriptionFromPayload($payload);
        if (! $subscription) {
            return;
        }

        Log::info('Revolut webhook: subscription initiated', [
            'subscription_id' => $subscription->id,
            'revolut_subscription_id' => $subscription->revolut_subscription_id,
        ]);
    }

    /**
     * SUBSCRIPTION_FINISHED — A billing cycle completed successfully.
     * Payment was collected, subscription continues.
     * This fires on each successful renewal.
     */
    private function handleSubscriptionFinished(array $payload): void
    {
        $subscription = $this->findSubscriptionFromPayload($payload);
        if (! $subscription) {
            return;
        }

        $orderId = $payload['order_id'] ?? null;

        // Require order_id for idempotency — cannot safely create a payment without it
        if (! $orderId) {
            Log::warning('Revolut webhook: SUBSCRIPTION_FINISHED missing order_id', [
                'subscription_id' => $subscription->id,
                'revolut_subscription_id' => $subscription->revolut_subscription_id,
            ]);

            return;
        }

        // Idempotency: skip if we already recorded a payment for this order
        if (Payment::where('revolut_order_id', $orderId)->exists()) {
            Log::info('Revolut webhook: duplicate SUBSCRIPTION_FINISHED ignored', [
                'order_id' => $orderId,
                'subscription_id' => $subscription->id,
            ]);

            return;
        }

        // Clear cancellation and grace period data on successful renewal
        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'data_retention_starts_at' => null,
            'current_period_start' => now(),
            'current_period_end' => $subscription->billing_cycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth(),
        ]);

        $billingLabel = $subscription->billing_cycle === 'yearly' ? 'Yearly' : 'Monthly';

        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'revolut_order_id' => $orderId,
            'amount' => $subscription->amount,
            'currency' => 'GBP',
            'status' => 'completed',
            'description' => ucfirst($subscription->plan).' Plan - '.$billingLabel.' Renewal',
            'revolut_payment_data' => array_intersect_key($payload, array_flip([
                'subscription_id', 'order_id', 'event', 'timestamp',
            ])),
        ]);

        $subscription->user()->update(['plan' => $subscription->plan]);

        // Send payment confirmation email
        $this->sendPaymentConfirmation($subscription, $payment);

        Log::info('Revolut webhook: subscription renewed', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'billing_cycle' => $subscription->billing_cycle,
        ]);
    }

    /**
     * SUBSCRIPTION_CANCELLED — Subscription was cancelled (by user or admin).
     * Access continues until current_period_end.
     */
    private function handleSubscriptionCancelled(array $payload): void
    {
        $subscription = $this->findSubscriptionFromPayload($payload);
        if (! $subscription) {
            return;
        }

        // Check if already cancelled by user (their cancel endpoint sends the email immediately)
        $wasAlreadyCancelled = ! is_null($subscription->cancelled_at);

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        // Only send email for admin/Revolut-initiated cancellations
        if (! $wasAlreadyCancelled) {
            $this->sendCancellationEmail($subscription);
        }

        Log::info('Revolut webhook: subscription cancelled', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'access_until' => $subscription->current_period_end?->toISOString(),
        ]);
    }

    /**
     * SUBSCRIPTION_OVERDUE — Payment failed on renewal attempt.
     * Revolut will retry automatically. Access continues during the grace period
     * (past_due status still passes isActive() if current_period_end is in the future).
     * If Revolut retries succeed, a SUBSCRIPTION_FINISHED event will follow and reset to active.
     */
    private function handleSubscriptionOverdue(array $payload): void
    {
        $subscription = $this->findSubscriptionFromPayload($payload);
        if (! $subscription) {
            return;
        }

        $subscription->update(['status' => 'past_due']);

        Log::warning('Revolut webhook: subscription overdue — payment failed, grace period active', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'revolut_subscription_id' => $subscription->revolut_subscription_id,
            'access_until' => $subscription->current_period_end?->toISOString(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Find a local Subscription from a subscription webhook payload.
     * Subscription events include a `subscription_id` field at the top level.
     */
    private function findSubscriptionFromPayload(array $payload): ?Subscription
    {
        $revolutSubscriptionId = $payload['subscription_id'] ?? null;
        if (! $revolutSubscriptionId) {
            Log::warning('Revolut webhook: missing subscription_id in payload', [
                'event' => $payload['event'] ?? 'unknown',
            ]);

            return null;
        }

        $subscription = Subscription::where('revolut_subscription_id', $revolutSubscriptionId)->first();
        if (! $subscription) {
            Log::warning('Revolut webhook: subscription not found', [
                'revolut_subscription_id' => $revolutSubscriptionId,
                'event' => $payload['event'] ?? 'unknown',
            ]);
        }

        return $subscription;
    }

    /**
     * Send a cancellation confirmation email to the subscription owner.
     */
    private function sendCancellationEmail(Subscription $subscription): void
    {
        try {
            $user = $subscription->user;
            if ($user) {
                Mail::to($user->email)->send(new SubscriptionCancellation($user, $subscription));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send cancellation confirmation email', [
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a payment confirmation email to the subscription owner.
     */
    private function sendPaymentConfirmation(Subscription $subscription, Payment $payment): void
    {
        try {
            $user = $subscription->user;
            if ($user) {
                Mail::to($user->email)->send(new PaymentConfirmation($user, $payment));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation email', [
                'user_id' => $subscription->user_id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Signature verification
    // -------------------------------------------------------------------------

    /**
     * Verify the webhook signature using Revolut's format.
     *
     * Revolut sends a Revolut-Signature header with format:
     *   v1=<signature>,t=<timestamp>
     *
     * The signature is HMAC-SHA256 of: "{timestamp}.{payload}"
     * Timestamp validation prevents replay attacks.
     */
    private function verifySignature(Request $request): bool
    {
        $secret = config('services.revolut.webhook_secret');
        if (empty($secret)) {
            if (app()->environment('local', 'testing')) {
                return true;
            }

            Log::critical('Revolut webhook secret not configured in non-local environment');

            return false;
        }

        $signatureHeader = $request->header('Revolut-Signature') ?? $request->header('X-Revolut-Signature');
        if (! $signatureHeader) {
            return false;
        }

        // Parse the v1=<sig>,t=<timestamp> format
        $parts = $this->parseSignatureHeader($signatureHeader);
        if (! $parts) {
            return false;
        }

        // Validate timestamp freshness (prevent replay attacks)
        if (abs(time() - $parts['timestamp']) > self::SIGNATURE_MAX_AGE_SECONDS) {
            Log::warning('Revolut webhook: signature timestamp too old', [
                'timestamp' => $parts['timestamp'],
                'age_seconds' => abs(time() - $parts['timestamp']),
            ]);

            return false;
        }

        // Compute expected signature: HMAC-SHA256 of "{timestamp}.{payload}"
        $payload = $request->getContent();
        $signedPayload = $parts['timestamp'].'.'.$payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSignature, $parts['signature']);
    }

    /**
     * Parse the Revolut-Signature header into components.
     *
     * Format: "v1=<hex_signature>,t=<unix_timestamp>"
     * Both v1 and t components are required. Rejects malformed headers.
     *
     * @return array{signature: string, timestamp: int}|null
     */
    private function parseSignatureHeader(string $header): ?array
    {
        $signature = null;
        $timestamp = null;

        // Try structured format: v1=xxx,t=yyy
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'v1=')) {
                $signature = substr($part, 3);
            } elseif (str_starts_with($part, 't=')) {
                $timestamp = (int) substr($part, 2);
            }
        }

        if ($signature && $timestamp > 0) {
            return ['signature' => $signature, 'timestamp' => $timestamp];
        }

        return null;
    }
}
