<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Mail\DataDeletionConfirmation;
use App\Mail\PaymentConfirmation;
use App\Mail\SubscriptionCancellation;
use App\Models\Payment;
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
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RevolutService $revolutService
    ) {}

    /**
     * Get available subscription plans.
     *
     * GET /api/payment/plans
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        return response()->json([
            'plans' => $plans->map(fn (SubscriptionPlan $plan) => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'monthly_price' => $plan->monthly_price,
                'yearly_price' => $plan->yearly_price,
                'features' => $plan->features,
            ]),
        ]);
    }

    /**
     * Create a Revolut payment order.
     *
     * POST /api/payment/create-order
     *
     * Called by the Revolut widget's createOrder callback via the frontend.
     */
    public function createOrder(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_preview_user) {
            return response()->json(['error' => 'Payment is not available in preview mode'], 403);
        }

        $request->validate([
            'plan' => 'required|string|in:student,standard,pro',
            'billing_cycle' => 'required|string|in:monthly,yearly',
        ]);

        $plan = SubscriptionPlan::findBySlug($request->input('plan'));
        if (! $plan) {
            return response()->json(['error' => 'Plan not found'], 404);
        }

        $billingCycle = $request->input('billing_cycle');
        $amount = $plan->getPriceForCycle($billingCycle);
        $description = "{$plan->name} — ".ucfirst($billingCycle);

        try {
            // Ensure subscription record exists
            $subscription = $user->subscription ?? $user->subscription()->create([
                'plan' => 'free',
                'billing_cycle' => $billingCycle,
                'status' => 'trialing',
                'amount' => 0,
                'current_period_start' => now(),
                'current_period_end' => now(),
            ]);

            // Create pending Payment record FIRST so we have an ID for merchant_ref
            $payment = Payment::create([
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'revolut_order_id' => 'pending',
                'amount' => $amount,
                'currency' => 'GBP',
                'status' => 'pending',
                'description' => $description,
                'plan_slug' => $plan->slug,
                'billing_cycle' => $billingCycle,
            ]);

            // Build redirect URL for redirect-based payment methods (e.g. Pay by Bank)
            // Revolut API rejects localhost — use production URL in sandbox/local dev
            $baseUrl = config('services.revolut.sandbox')
                ? 'https://fynla.org'
                : config('app.url');
            $redirectUrl = $baseUrl.'/checkout?plan='.$plan->slug
                .'&cycle='.$billingCycle.'&status=complete';

            // POST to Revolut Merchant API: /api/orders
            $revolutOrder = $this->revolutService->createOrder(
                $amount,
                'GBP',
                $description,
                $redirectUrl,
                "payment_{$payment->id}",
                $user->email
            );

            // Update Payment with real Revolut order ID
            $payment->update([
                'revolut_order_id' => $revolutOrder['id'],
                'revolut_payment_data' => [
                    'order_id' => $revolutOrder['id'],
                    'token' => $revolutOrder['token'],
                    'state' => $revolutOrder['state'],
                    'created_at' => $revolutOrder['created_at'] ?? now()->toIso8601String(),
                ],
            ]);

            // Return token for SDK and order_id for confirm endpoint
            return response()->json([
                'token' => $revolutOrder['token'],
                'order_id' => $revolutOrder['id'],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Creating payment order');
        }
    }

    /**
     * Confirm a completed payment and activate the subscription.
     *
     * POST /api/payment/confirm
     *
     * Called by the onSuccess callback. Receives the Revolut order UUID
     * (stored by frontend from createOrder response), NOT the token from the callback.
     */
    public function confirmPayment(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_preview_user) {
            return response()->json(['error' => 'Payment is not available in preview mode'], 403);
        }

        $request->validate([
            'order_id' => 'required|string|uuid',
        ]);

        $orderId = $request->input('order_id');

        try {
            // Verify order state with Revolut: GET /api/orders/{order_id}
            $revolutOrder = $this->revolutService->getOrder($orderId);
            $state = $revolutOrder['state'];
            $captureMode = $revolutOrder['capture_mode'] ?? 'automatic';

            $acceptableStates = $captureMode === 'manual'
                ? ['completed', 'authorised', 'processing']
                : ['completed', 'processing'];

            if (! in_array($state, $acceptableStates)) {
                Log::warning('Revolut order not in acceptable state for confirmation', [
                    'order_id' => $orderId,
                    'state' => $state,
                    'capture_mode' => $captureMode,
                    'acceptable_states' => $acceptableStates,
                ]);

                return response()->json([
                    'error' => 'Payment has not been completed yet',
                    'state' => $state,
                ], 400);
            }

            // Activate subscription in DB transaction
            $result = DB::transaction(function () use ($user, $orderId, $revolutOrder) {
                $payment = Payment::where('revolut_order_id', $orderId)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    throw new \RuntimeException("Payment not found for order: {$orderId}");
                }

                // Idempotent: if already completed, return early
                if ($payment->status === 'completed') {
                    return ['already_completed' => true, 'payment' => $payment];
                }

                // Read plan and billing cycle from the Payment record (source of truth)
                $planSlug = $payment->plan_slug;
                $billingCycle = $payment->billing_cycle;

                $periodEnd = $billingCycle === 'monthly'
                    ? now()->addMonth()
                    : now()->addYear();

                $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug);

                // Update Payment
                $payment->update([
                    'status' => 'completed',
                    'revolut_payment_data' => $revolutOrder,
                ]);

                // Update Subscription
                $subscription = $payment->subscription;
                $subscription->update([
                    'status' => 'active',
                    'plan' => $planSlug,
                    'billing_cycle' => $billingCycle,
                    'amount' => $subscriptionPlan ? $subscriptionPlan->getPriceForCycle($billingCycle) : $payment->amount,
                    'current_period_start' => now(),
                    'current_period_end' => $periodEnd,
                    'revolut_order_id' => $orderId,
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                ]);

                // Update User denormalised fields
                $user->update([
                    'plan' => $planSlug,
                    'trial_ends_at' => null,
                ]);

                return ['already_completed' => false, 'payment' => $payment, 'subscription' => $subscription];
            });

            // Send confirmation email (only if newly activated)
            if (! $result['already_completed']) {
                try {
                    Mail::to($user->email)->send(new PaymentConfirmation($user, $result['payment']));
                } catch (\Exception $e) {
                    Log::error('Failed to send payment confirmation email', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Confirming payment');
        }
    }

    /**
     * Get the current trial and subscription status for the authenticated user.
     *
     * GET /api/payment/trial-status
     */
    public function trialStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->subscription;

        $paymentEnabled = config('app.payment_enabled', false);

        if (! $subscription) {
            return response()->json([
                'has_subscription' => false,
                'payment_enabled' => $paymentEnabled,
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
        ]);
    }

    /**
     * Cancel the user's subscription.
     *
     * Access continues until current_period_end.
     *
     * POST /api/payment/cancel-subscription
     */
    public function cancelSubscription(Request $request): JsonResponse
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
            $accessUntil = DB::transaction(function () use ($subscription, $request) {
                $locked = \App\Models\Subscription::where('id', $subscription->id)->lockForUpdate()->first();

                if (! in_array($locked->status, ['active', 'past_due'])) {
                    return null;
                }

                $reason = $request->input('reason');

                $locked->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason ?: null,
                ]);

                return $locked->current_period_end?->toISOString();
            });

            if ($accessUntil === null) {
                return response()->json(['error' => 'Subscription is not active'], 409);
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
}
