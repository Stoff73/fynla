<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Mail\PaymentFailedNotification;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SubscriptionRenewalService
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * Handle a renewal payment from an ORDER_COMPLETED webhook for a subscription cycle.
     *
     * Creates a Payment record, updates subscription period dates, generates invoice.
     */
    public function handleRenewalPayment(string $orderId, array $orderData): void
    {
        DB::transaction(function () use ($orderId, $orderData) {
            // Check if we already have a payment for this order (idempotent)
            $existing = Payment::where('revolut_order_id', $orderId)->first();
            if ($existing && $existing->status === 'completed') {
                Log::info('Renewal payment already processed', ['order_id' => $orderId]);

                return;
            }

            // Find the subscription via the existing payment or by searching
            $subscription = null;
            if ($existing) {
                $subscription = $existing->subscription;
            } else {
                // Find subscription that has this as a renewal — look for subscriptions with revolut_subscription_id
                $subscription = Subscription::whereNotNull('revolut_subscription_id')
                    ->where('status', 'active')
                    ->latest('current_period_end')
                    ->first();
            }

            if (! $subscription) {
                Log::warning('Renewal payment: subscription not found', ['order_id' => $orderId]);

                return;
            }

            $user = $subscription->user;
            $planSlug = $subscription->plan;
            $billingCycle = $subscription->billing_cycle;

            $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug);
            $amount = $subscriptionPlan
                ? ($subscriptionPlan->getLaunchPriceForCycle($billingCycle) ?? $subscriptionPlan->getPriceForCycle($billingCycle))
                : $subscription->amount;

            $periodEnd = $billingCycle === 'monthly'
                ? now()->addMonth()
                : now()->addYear();

            // Create or update payment
            $payment = $existing ?? Payment::create([
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'revolut_order_id' => $orderId,
                'amount' => $amount,
                'currency' => 'GBP',
                'status' => 'pending',
                'description' => ucfirst($planSlug) . ' — ' . ucfirst($billingCycle) . ' (Auto-renewal)',
                'plan_slug' => $planSlug,
                'billing_cycle' => $billingCycle,
                'revolut_subscription_payment' => true,
            ]);

            $payment->update([
                'status' => 'completed',
                'revolut_payment_data' => $orderData,
            ]);

            // Update subscription period
            $subscription->update([
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'revolut_order_id' => $orderId,
            ]);

            Log::info('Renewal payment processed', [
                'user_id' => $user->id,
                'order_id' => $orderId,
                'plan' => $planSlug,
                'billing_cycle' => $billingCycle,
                'next_period_end' => $periodEnd->toIso8601String(),
            ]);
        });

        // Generate invoice outside transaction (PDF generation shouldn't block DB)
        $payment = Payment::where('revolut_order_id', $orderId)->first();
        if ($payment) {
            try {
                $invoice = $this->invoiceService->generateInvoice($payment);
                $this->invoiceService->emailInvoice($invoice, $payment->user);
            } catch (\Exception $e) {
                Log::error('Failed to generate renewal invoice', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle SUBSCRIPTION_OVERDUE webhook — payment failed.
     */
    public function handleSubscriptionOverdue(array $subscriptionData): void
    {
        $revolutSubscriptionId = $subscriptionData['id'] ?? $subscriptionData['subscription_id'] ?? null;
        if (! $revolutSubscriptionId) {
            Log::warning('Subscription overdue: missing subscription ID in payload');

            return;
        }

        $subscription = Subscription::where('revolut_subscription_id', $revolutSubscriptionId)->first();
        if (! $subscription) {
            Log::warning('Subscription overdue: subscription not found', [
                'revolut_subscription_id' => $revolutSubscriptionId,
            ]);

            return;
        }

        $subscription->update(['status' => 'past_due']);

        Log::info('Subscription marked as past_due', [
            'subscription_id' => $subscription->id,
            'revolut_subscription_id' => $revolutSubscriptionId,
        ]);

        // Send payment failure notification
        try {
            Mail::to($subscription->user->email)->send(
                new PaymentFailedNotification($subscription->user, $subscription)
            );
        } catch (\Exception $e) {
            Log::error('Failed to send payment failure notification', [
                'user_id' => $subscription->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle SUBSCRIPTION_CANCELLED webhook.
     */
    public function handleSubscriptionCancelled(array $subscriptionData): void
    {
        $revolutSubscriptionId = $subscriptionData['id'] ?? $subscriptionData['subscription_id'] ?? null;
        if (! $revolutSubscriptionId) {
            return;
        }

        $subscription = Subscription::where('revolut_subscription_id', $revolutSubscriptionId)->first();
        if (! $subscription) {
            Log::warning('Subscription cancelled webhook: subscription not found', [
                'revolut_subscription_id' => $revolutSubscriptionId,
            ]);

            return;
        }

        // Only update if not already cancelled locally
        if ($subscription->status !== 'cancelled') {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'auto_renew' => false,
            ]);
        }

        Log::info('Subscription cancelled via webhook', [
            'subscription_id' => $subscription->id,
            'revolut_subscription_id' => $revolutSubscriptionId,
        ]);
    }

    /**
     * Handle SUBSCRIPTION_FINISHED webhook — all billing cycles completed.
     */
    public function handleSubscriptionFinished(array $subscriptionData): void
    {
        $revolutSubscriptionId = $subscriptionData['id'] ?? $subscriptionData['subscription_id'] ?? null;
        if (! $revolutSubscriptionId) {
            return;
        }

        $subscription = Subscription::where('revolut_subscription_id', $revolutSubscriptionId)->first();
        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => 'expired',
            'auto_renew' => false,
            'data_retention_starts_at' => $subscription->current_period_end ?? now(),
        ]);

        Log::info('Subscription finished via webhook', [
            'subscription_id' => $subscription->id,
            'revolut_subscription_id' => $revolutSubscriptionId,
        ]);
    }
}
