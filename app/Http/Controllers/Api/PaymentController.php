<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\DataDeletionConfirmation;
use App\Mail\SubscriptionCancellation;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payment\DataPurgeService;
use App\Services\Payment\RevolutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{
    /**
     * Create a Revolut subscription for the user's plan.
     *
     * Lazily creates a Revolut customer if one doesn't exist yet.
     * Returns the setup_order_id needed to initialise the checkout widget.
     *
     * POST /api/payment/subscribe
     */
    public function subscribe(Request $request, RevolutService $revolutService): JsonResponse
    {
        $user = $request->user();

        if ($user->is_preview_user) {
            return response()->json(['error' => 'Payment is not available in preview mode'], 403);
        }

        $subscription = $user->subscription;

        if (! $subscription) {
            return response()->json(['error' => 'No subscription found'], 404);
        }

        $subscriptionPlan = SubscriptionPlan::findBySlug($subscription->plan);
        if (! $subscriptionPlan) {
            return response()->json(['error' => 'Subscription plan not found'], 404);
        }

        $variationId = $subscriptionPlan->getVariationIdForCycle($subscription->billing_cycle);
        if (! $variationId) {
            return response()->json(['error' => 'Plan not yet synced with Revolut. Run revolut:seed-plans.'], 500);
        }

        try {
            // Lazily create Revolut customer with lock to prevent duplicates
            $this->ensureRevolutCustomer($user, $revolutService);

            // Use a lock to prevent duplicate Revolut subscription creation from concurrent requests
            $result = DB::transaction(function () use ($subscription, $user, $revolutService, $variationId) {
                $locked = \App\Models\Subscription::where('id', $subscription->id)->lockForUpdate()->first();

                if ($locked->status === 'active') {
                    return ['error' => 'You already have an active subscription', 'code' => 409];
                }

                // For expired/cancelled subscriptions: clear stale Revolut IDs
                if (in_array($locked->status, ['expired', 'cancelled'])) {
                    $locked->update([
                        'revolut_subscription_id' => null,
                        'revolut_order_id' => null,
                    ]);
                }

                // If a Revolut subscription already exists (e.g. pending first payment), return existing
                if ($locked->revolut_subscription_id && $locked->revolut_order_id) {
                    return ['setup_order_id' => $locked->revolut_order_id];
                }

                // Create the Revolut subscription
                $revolutSubscription = $revolutService->createSubscription(
                    $user->revolut_customer_id,
                    $variationId
                );

                $locked->update([
                    'revolut_subscription_id' => $revolutSubscription['id'] ?? null,
                    'revolut_order_id' => $revolutSubscription['setup_order_id'] ?? null,
                ]);

                return ['setup_order_id' => $revolutSubscription['setup_order_id'] ?? null];
            });

            if (isset($result['error'])) {
                return response()->json(['error' => $result['error']], $result['code']);
            }

            // Fetch the order token needed by the Revolut JS SDK
            if (! empty($result['setup_order_id'])) {
                $order = $revolutService->getOrderStatus($result['setup_order_id']);
                $result['setup_order_id'] = $order['token'] ?? $order['public_id'] ?? $result['setup_order_id'];
            }

            return response()->json($result);
        } catch (\RuntimeException $e) {
            Log::error('Subscription creation failed', [
                'user_id' => $user->id,
                'plan' => $subscription->plan,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to create subscription. Please try again.'], 500);
        }
    }

    /**
     * Create a payment order via Revolut for the user's subscription.
     *
     * POST /api/payment/create-order
     */
    public function createOrder(Request $request, RevolutService $revolutService): JsonResponse
    {
        $user = $request->user();

        if ($user->is_preview_user) {
            return response()->json(['error' => 'Payment is not available in preview mode'], 403);
        }

        $subscription = $user->subscription;

        if (! $subscription) {
            return response()->json(['error' => 'No subscription found'], 404);
        }

        $order = $revolutService->createOrder(
            $user,
            $subscription->plan,
            $subscription->billing_cycle
        );

        $subscription->update(['revolut_order_id' => $order['id'] ?? null]);

        return response()->json([
            'public_id' => $order['public_id'] ?? $order['token'] ?? null,
        ]);
    }

    /**
     * Get the status of the authenticated user's payment order.
     *
     * GET /api/payment/order-status
     */
    public function orderStatus(Request $request, RevolutService $revolutService): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;

        if (! $subscription?->revolut_order_id) {
            return response()->json(['error' => 'No active order found'], 404);
        }

        $order = $revolutService->getOrderStatus($subscription->revolut_order_id);

        return response()->json([
            'state' => $order['state'] ?? null,
            'completed_at' => $order['completed_at'] ?? null,
        ]);
    }

    /**
     * Get the current trial and subscription status for the authenticated user.
     *
     * Also returns checkout configuration so the frontend can render
     * the correct Revolut widget mode (popup, embedded, card_field).
     *
     * GET /api/payment/trial-status
     */
    public function trialStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;

        $paymentEnabled = config('app.payment_enabled', false);
        $checkoutConfig = [
            'mode' => config('services.revolut.checkout_mode', 'popup'),
            'sandbox' => (bool) config('services.revolut.sandbox', true),
        ];

        if (! $subscription) {
            return response()->json([
                'has_subscription' => false,
                'payment_enabled' => $paymentEnabled,
                'checkout' => $checkoutConfig,
            ]);
        }

        return response()->json([
            'has_subscription' => true,
            'plan' => $subscription->plan,
            'billing_cycle' => $subscription->billing_cycle,
            'status' => $subscription->status,
            'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
            'days_remaining' => $subscription->daysLeftInTrial(),
            'progress' => $subscription->trialProgress(),
            'amount' => $subscription->amount,
            'current_period_start' => $subscription->current_period_start?->toISOString(),
            'current_period_end' => $subscription->current_period_end?->toISOString(),
            'cancelled_at' => $subscription->cancelled_at?->toISOString(),
            'data_retention_starts_at' => $paymentEnabled ? $subscription->data_retention_starts_at?->toISOString() : null,
            'grace_period_ends_at' => $paymentEnabled ? $subscription->gracePeriodEndsAt()?->toISOString() : null,
            'is_in_grace_period' => $paymentEnabled && $subscription->isInGracePeriod(),
            'payment_enabled' => $paymentEnabled,
            'checkout' => $checkoutConfig,
        ]);
    }

    /**
     * Cancel the user's subscription via Revolut.
     *
     * Access continues until current_period_end. The webhook handler
     * will update the subscription status when Revolut confirms cancellation.
     *
     * POST /api/payment/cancel-subscription
     */
    public function cancelSubscription(Request $request, RevolutService $revolutService): JsonResponse
    {
        $user = $request->user();

        if ($user->is_preview_user) {
            return response()->json(['error' => 'Payment is not available in preview mode'], 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $subscription = $user->subscription;

        if (! $subscription) {
            return response()->json(['error' => 'No subscription found'], 404);
        }

        try {
            $accessUntil = DB::transaction(function () use ($subscription, $revolutService, $request) {
                $locked = \App\Models\Subscription::where('id', $subscription->id)->lockForUpdate()->first();

                if (! in_array($locked->status, ['active', 'past_due'])) {
                    return null;
                }

                if (! $locked->revolut_subscription_id) {
                    return null;
                }

                $revolutService->cancelSubscription($locked->revolut_subscription_id);

                $reason = $request->input('reason');

                $locked->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason ?: null,
                ]);

                return $locked->current_period_end?->toISOString();
            });

            if ($accessUntil === null) {
                return response()->json(['error' => 'Subscription is not active or has no Revolut subscription'], 409);
            }

            Log::info('Subscription cancelled by user', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'reason' => $request->input('reason'),
                'access_until' => $accessUntil,
            ]);

            // Send cancellation confirmation email
            $this->sendCancellationEmail($user, $subscription->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled. You retain access until the end of your current billing period.',
                'access_until' => $accessUntil,
            ]);
        } catch (\Throwable $e) {
            Log::error('Subscription cancellation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to cancel subscription. Please try again.'], 500);
        }
    }

    /**
     * Get the billing history for the authenticated user.
     *
     * Returns completed payments ordered by most recent first.
     *
     * GET /api/payment/billing-history
     */
    public function billingHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;

        if (! $subscription) {
            return response()->json(['payments' => []]);
        }

        $payments = $subscription->payments()
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->limit(24)
            ->get()
            ->map(fn ($payment) => [
                'id' => $payment->id,
                'reference' => 'FYN-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
                'description' => $payment->description ?? ucfirst($subscription->plan).' Plan',
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'date' => $payment->created_at?->toISOString(),
            ]);

        return response()->json(['payments' => $payments]);
    }

    /**
     * Delete all user data and deactivate the account.
     *
     * Available during the 30-day grace period. Requires the user to
     * type "DELETE" as confirmation to prevent accidental data loss.
     *
     * POST /api/payment/delete-all-data
     */
    public function deleteAllData(Request $request, DataPurgeService $purgeService): JsonResponse
    {
        $user = $request->user();

        if ($user->is_preview_user) {
            return response()->json(['error' => 'Data deletion is not available in preview mode'], 403);
        }

        $request->validate([
            'confirmation_text' => 'required|string',
            'current_password' => 'required|string',
        ]);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['error' => 'Incorrect password'], 422);
        }

        if ($request->input('confirmation_text') !== 'DELETE') {
            return response()->json(['error' => 'Please type DELETE to confirm data deletion'], 422);
        }

        $subscription = $user->subscription;

        if (! $subscription || ! $subscription->isInGracePeriod()) {
            return response()->json(['error' => 'Data deletion is only available during the grace period'], 403);
        }

        $firstName = $user->first_name;
        $email = $user->email;

        try {
            $result = $purgeService->purgeUserData($user);

            Log::info('User initiated data deletion', [
                'user_id' => $user->id,
                'records_deleted' => $result['records_deleted'],
                'tables_purged' => $result['tables_purged'],
            ]);

            // Send deletion confirmation email
            try {
                Mail::to($email)->send(new DataDeletionConfirmation($firstName ?? 'User', $email));
            } catch (\Exception $e) {
                Log::error('Failed to send data deletion confirmation email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'All your data has been permanently deleted.',
            ]);
        } catch (\Throwable $e) {
            Log::error('User-initiated data deletion failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to delete data. Please try again or contact support.'], 500);
        }
    }

    /**
     * Send a cancellation confirmation email to the user.
     */
    private function sendCancellationEmail(User $user, \App\Models\Subscription $subscription): void
    {
        try {
            Mail::to($user->email)->send(new SubscriptionCancellation($user, $subscription));
        } catch (\Exception $e) {
            Log::error('Failed to send cancellation confirmation email', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ensure user has a Revolut customer ID, creating one if needed.
     * Uses a database lock to prevent duplicate customer creation under concurrent requests.
     */
    private function ensureRevolutCustomer(User $user, RevolutService $revolutService): void
    {
        if ($user->revolut_customer_id) {
            return;
        }

        DB::transaction(function () use ($user, $revolutService) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

            if ($lockedUser->revolut_customer_id) {
                $user->revolut_customer_id = $lockedUser->revolut_customer_id;

                return;
            }

            $customer = $revolutService->createCustomer($lockedUser);
            $lockedUser->update(['revolut_customer_id' => $customer['id']]);
            $user->revolut_customer_id = $customer['id'];
        });
    }
}
