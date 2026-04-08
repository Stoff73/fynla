<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Mail\DataDeletionConfirmation;
use App\Mail\PaymentConfirmation;
use App\Mail\SubscriptionCancellation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payment\DataPurgeService;
use App\Services\Payment\DiscountCodeService;
use App\Services\Payment\InvoiceService;
use App\Services\Payment\RevolutService;
use App\Services\Payment\RevolutSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    use SanitizedErrorResponse;

    private const PLAN_ORDER = ['student', 'standard', 'family', 'pro'];

    public function __construct(
        private readonly RevolutService $revolutService,
        private readonly RevolutSubscriptionService $subscriptionService,
        private readonly DiscountCodeService $discountCodeService,
        private readonly InvoiceService $invoiceService,
        private readonly DataPurgeService $purgeService
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
                'launch_monthly_price' => $plan->launch_monthly_price,
                'launch_yearly_price' => $plan->launch_yearly_price,
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
            return response()->json(['success' => false, 'message' => 'Payment is not available in preview mode'], 403);
        }

        $request->validate([
            'plan' => 'required|string|in:student,standard,family,pro',
            'billing_cycle' => 'required|string|in:monthly,yearly',
            'discount_code' => 'nullable|string|max:50',
        ]);

        $plan = SubscriptionPlan::findBySlug($request->input('plan'));
        if (! $plan) {
            return response()->json(['success' => false, 'message' => 'Plan not found'], 404);
        }

        $billingCycle = $request->input('billing_cycle');
        $amount = $plan->getLaunchPriceForCycle($billingCycle) ?? $plan->getPriceForCycle($billingCycle);
        $description = "{$plan->name} — ".ucfirst($billingCycle);

        // Validate discount code if provided
        $discountResult = null;
        $discountCode = null;
        $discountAmount = 0;
        $finalAmount = $amount;

        if ($request->filled('discount_code')) {
            $discountResult = $this->discountCodeService->validate(
                $request->input('discount_code'),
                $user->id,
                $plan->slug,
                $billingCycle,
                $amount
            );

            if (! $discountResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $discountResult['message'],
                ], 422);
            }

            $discountCode = $discountResult['discount'];

            // Handle trial extension separately — no payment needed
            if ($discountCode->type === 'trial_extension') {
                $subscription = $user->subscription;
                if ($subscription && $subscription->trial_ends_at) {
                    $subscription->update([
                        'trial_ends_at' => $subscription->trial_ends_at->addDays($discountCode->value),
                    ]);
                    $user->update([
                        'trial_ends_at' => $subscription->trial_ends_at,
                    ]);
                    $this->discountCodeService->apply($discountCode, $user->id, 0, 0);
                }

                return response()->json([
                    'success' => true,
                    'message' => "Trial extended by {$discountCode->value} days.",
                    'trial_extension' => true,
                ]);
            }

            $discountAmount = $discountResult['discount_amount'];
            $finalAmount = $discountResult['final_amount'];
        }

        try {
            // Ensure subscription record exists
            $subscription = $user->subscription ?? $user->subscription()->create([
                'plan' => $plan->slug,
                'billing_cycle' => $billingCycle,
                'status' => 'trialing',
                'amount' => 0,
                'current_period_start' => now(),
                'current_period_end' => now(),
            ]);

            // Ensure Revolut customer exists
            if (! $user->revolut_customer_id) {
                $this->subscriptionService->createCustomer($user);
                $user->refresh();
            }

            // Build redirect URL
            $baseUrl = config('services.revolut.sandbox')
                ? 'https://fynla.org'
                : config('app.url');
            $redirectUrl = $baseUrl.'/checkout?plan='.$plan->slug
                .'&cycle='.$billingCycle.'&status=complete';

            // Determine order creation strategy:
            // - If discount code applied: use one-off order at discounted price
            //   (Revolut subscription plans have fixed prices — can't apply per-user discounts)
            //   The subscription for auto-renewal is created in confirmPayment after first payment.
            // - If no discount: try Revolut Subscription flow for automatic recurring billing
            $revolutOrder = null;

            if ($discountCode) {
                // Discount applied — one-off order at discounted price, save card for future
                $revolutOrder = $this->revolutService->createOrderWithCustomer(
                    $finalAmount,
                    'GBP',
                    $description . " (Discount: {$discountCode->code})",
                    $redirectUrl,
                    $user->revolut_customer_id,
                    null,
                    $user->email,
                    true
                );
            } else {
                // No discount — try Revolut Subscription flow for auto-renewal
                try {
                    $revolutPlanId = $subscription->revolut_plan_id;
                    if (! $revolutPlanId) {
                        $revolutPlan = $this->subscriptionService->createSubscriptionPlan($plan);
                        $revolutPlanId = $revolutPlan['id'];
                    }

                    $variationId = $this->subscriptionService->findVariationId($revolutPlanId, $billingCycle);

                    if ($variationId) {
                        $trialDuration = $subscription->isTrialing() ? null : 'P0D';

                        $revolutSubscription = $this->subscriptionService->createSubscription(
                            $user,
                            $variationId,
                            $redirectUrl,
                            $trialDuration,
                            "fynla_sub_{$subscription->id}"
                        );

                        $setupOrderId = $revolutSubscription['setup_order_id'] ?? null;

                        if ($setupOrderId) {
                            $revolutOrder = $this->revolutService->getOrder($setupOrderId);
                        }

                        $subscription->update([
                            'revolut_subscription_id' => $revolutSubscription['id'] ?? null,
                            'revolut_plan_id' => $revolutPlanId,
                            'revolut_plan_variation_id' => $variationId,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Revolut subscription creation failed, falling back to one-off order', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Fallback if subscription flow didn't produce an order
                if (! $revolutOrder) {
                    $revolutOrder = $this->revolutService->createOrderWithCustomer(
                        $finalAmount,
                        'GBP',
                        $description,
                        $redirectUrl,
                        $user->revolut_customer_id,
                        null,
                        $user->email,
                        true
                    );
                }
            }

            // Create pending Payment record
            $payment = Payment::create([
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'revolut_order_id' => $revolutOrder['id'],
                'amount' => $finalAmount,
                'currency' => 'GBP',
                'status' => 'pending',
                'description' => $description,
                'plan_slug' => $plan->slug,
                'billing_cycle' => $billingCycle,
                'discount_code_id' => $discountCode?->id,
                'discount_amount' => $discountAmount,
                'revolut_payment_data' => [
                    'order_id' => $revolutOrder['id'],
                    'token' => $revolutOrder['token'] ?? null,
                    'state' => $revolutOrder['state'] ?? 'pending',
                    'created_at' => $revolutOrder['created_at'] ?? now()->toIso8601String(),
                ],
            ]);

            // Intentional: Revolut SDK requires {token, order_id} at top level
            return response()->json([
                'token' => $revolutOrder['token'] ?? $revolutOrder['public_id'] ?? null,
                'order_id' => $revolutOrder['id'],
                'discount_applied' => $discountAmount > 0,
                'final_amount' => $finalAmount,
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
            return response()->json(['success' => false, 'message' => 'Payment is not available in preview mode'], 403);
        }

        $request->validate([
            'order_id' => 'required|string|uuid',
        ]);

        $orderId = $request->input('order_id');

        // Verify the order belongs to the requesting user before calling Revolut
        $payment = Payment::where('revolut_order_id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        try {
            // Verify order state with Revolut: GET /api/orders/{order_id}
            $revolutOrder = $this->revolutService->getOrder($orderId);
            $state = $revolutOrder['state'];
            $captureMode = $revolutOrder['capture_mode'] ?? 'automatic';

            $acceptableStates = $captureMode === 'manual'
                ? ['completed', 'authorised', 'processing', 'pending']
                : ['completed', 'processing', 'pending'];

            if (! in_array($state, $acceptableStates)) {
                Log::warning('Revolut order not in acceptable state for confirmation', [
                    'order_id' => $orderId,
                    'state' => $state,
                    'capture_mode' => $captureMode,
                    'acceptable_states' => $acceptableStates,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment has not been completed yet',
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
                $isUpgrade = ! empty($payment->upgrade_from_plan);

                $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug);
                $fullPrice = $subscriptionPlan
                    ? ($subscriptionPlan->getLaunchPriceForCycle($billingCycle) ?? $subscriptionPlan->getPriceForCycle($billingCycle))
                    : $payment->amount;

                // Update Payment
                $payment->update([
                    'status' => 'completed',
                    'revolut_payment_data' => $revolutOrder,
                ]);

                // Update Subscription
                $subscription = $payment->subscription;
                $subscriptionUpdate = [
                    'status' => 'active',
                    'plan' => $planSlug,
                    'billing_cycle' => $billingCycle,
                    'amount' => $fullPrice,
                    'revolut_order_id' => $orderId,
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                ];

                // Upgrades keep existing period dates; new subscriptions set fresh dates
                if (! $isUpgrade) {
                    $subscriptionUpdate['current_period_start'] = now();
                    $subscriptionUpdate['current_period_end'] = $billingCycle === 'monthly'
                        ? now()->addMonth()
                        : now()->addYear();
                }

                $subscription->update($subscriptionUpdate);

                // Update User denormalised fields
                $user->update([
                    'plan' => $planSlug,
                    'trial_ends_at' => null,
                ]);

                return ['already_completed' => false, 'payment' => $payment, 'subscription' => $subscription];
            });

            // Post-transaction: emails, invoice, discount usage
            if (! $result['already_completed']) {
                $payment = $result['payment'];

                // Apply discount code usage
                if ($payment->discount_code_id && $payment->discountCode) {
                    try {
                        $this->discountCodeService->apply(
                            $payment->discountCode,
                            $user->id,
                            $payment->id,
                            $payment->amount + $payment->discount_amount
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to apply discount code usage', [
                            'payment_id' => $payment->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Set auto-renew flags if using Revolut subscription
                $subscription = $result['subscription'] ?? $payment->subscription;
                if ($subscription->revolut_subscription_id) {
                    $subscription->update([
                        'auto_renew' => true,
                        'payment_method_saved' => true,
                    ]);
                }

                // Generate invoice
                try {
                    $invoice = $this->invoiceService->generateInvoice($payment, $payment->discountCode);
                    $this->invoiceService->emailInvoice($invoice, $user);
                } catch (\Exception $e) {
                    Log::error('Failed to generate invoice', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Send payment confirmation email
                try {
                    Mail::to($user->email)->send(new PaymentConfirmation($user, $payment));
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
     * Upgrade an active subscription to a higher-tier plan.
     *
     * POST /api/payment/upgrade
     *
     * Calculates a prorated amount for the remaining billing period
     * and creates a Revolut order for that amount.
     */
    public function upgradeSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_preview_user) {
            return response()->json(['success' => false, 'message' => 'Payment is not available in preview mode'], 403);
        }

        $request->validate([
            'plan' => 'required|string|in:student,standard,family,pro',
        ]);

        $subscription = $user->subscription;

        if (! $subscription || $subscription->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'You must have an active subscription to upgrade'], 403);
        }

        $currentPlanSlug = $subscription->plan;
        $newPlanSlug = $request->input('plan');

        $currentIndex = array_search($currentPlanSlug, self::PLAN_ORDER);
        $newIndex = array_search($newPlanSlug, self::PLAN_ORDER);

        if ($currentIndex === false || $newIndex === false || $newIndex <= $currentIndex) {
            return response()->json(['success' => false, 'message' => 'You can only upgrade to a higher-tier plan'], 422);
        }

        $currentPlan = SubscriptionPlan::findBySlug($currentPlanSlug);
        $newPlan = SubscriptionPlan::findBySlug($newPlanSlug);

        if (! $currentPlan || ! $newPlan) {
            return response()->json(['success' => false, 'message' => 'Plan not found'], 404);
        }

        $billingCycle = $subscription->billing_cycle;

        // Get effective prices (launch price if available)
        $currentPrice = $currentPlan->getLaunchPriceForCycle($billingCycle) ?? $currentPlan->getPriceForCycle($billingCycle);
        $newPrice = $newPlan->getLaunchPriceForCycle($billingCycle) ?? $newPlan->getPriceForCycle($billingCycle);
        $priceDiff = $newPrice - $currentPrice;

        if ($billingCycle === 'yearly') {
            $monthlyDiff = (int) round($priceDiff / 12);
            $monthsUsed = (int) $subscription->current_period_start->diffInMonths(now());
            $monthsRemaining = max(1, 12 - $monthsUsed);
            $upgradeAmount = $monthlyDiff * $monthsRemaining;
        } else {
            // Monthly: charge the full month difference
            $upgradeAmount = $priceDiff;
        }

        // Minimum charge of 1p (Revolut requires > 0)
        $upgradeAmount = max(1, $upgradeAmount);

        $description = 'Upgrade: '.ucfirst($currentPlanSlug)." \u{2192} ".ucfirst($newPlanSlug);

        try {
            $payment = Payment::create([
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'revolut_order_id' => 'pending',
                'amount' => $upgradeAmount,
                'currency' => 'GBP',
                'status' => 'pending',
                'description' => $description,
                'plan_slug' => $newPlanSlug,
                'billing_cycle' => $billingCycle,
                'upgrade_from_plan' => $currentPlanSlug,
            ]);

            $baseUrl = config('services.revolut.sandbox')
                ? 'https://fynla.org'
                : config('app.url');
            $redirectUrl = $baseUrl.'/checkout?plan='.$newPlanSlug
                .'&cycle='.$billingCycle.'&upgrade=true&status=complete';

            $revolutOrder = $this->revolutService->createOrder(
                $upgradeAmount,
                'GBP',
                $description,
                $redirectUrl,
                "upgrade_{$payment->id}",
                $user->email
            );

            $payment->update([
                'revolut_order_id' => $revolutOrder['id'],
                'revolut_payment_data' => [
                    'order_id' => $revolutOrder['id'],
                    'token' => $revolutOrder['token'],
                    'state' => $revolutOrder['state'],
                    'created_at' => $revolutOrder['created_at'] ?? now()->toIso8601String(),
                ],
            ]);

            return response()->json([
                'token' => $revolutOrder['token'],
                'order_id' => $revolutOrder['id'],
                'upgrade_amount' => $upgradeAmount,
                'new_plan' => $newPlanSlug,
                'months_remaining' => $billingCycle === 'yearly' ? (12 - (int) $subscription->current_period_start->diffInMonths(now())) : 1,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Creating upgrade order');
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
            'auto_renew' => $subscription->auto_renew ?? false,
            'next_renewal_date' => ($subscription->status === 'active' && $subscription->auto_renew)
                ? $subscription->current_period_end?->toISOString()
                : null,
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
            return response()->json(['success' => false, 'message' => 'Payment is not available in preview mode'], 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $subscription = $user->subscription;

        if (! $subscription) {
            return response()->json(['success' => false, 'message' => 'No subscription found'], 404);
        }

        try {
            // Cancel Revolut subscription if it exists
            if ($subscription->revolut_subscription_id) {
                try {
                    $this->subscriptionService->cancelSubscription($subscription->revolut_subscription_id);
                } catch (\Throwable $e) {
                    Log::warning('Failed to cancel Revolut subscription', [
                        'revolut_subscription_id' => $subscription->revolut_subscription_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

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
                    'auto_renew' => false,
                ]);

                return $locked->current_period_end?->toISOString();
            });

            if ($accessUntil === null) {
                return response()->json(['success' => false, 'message' => 'Subscription is not active'], 409);
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

            return response()->json(['success' => false, 'message' => 'Failed to cancel subscription. Please try again.'], 500);
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
            return response()->json(['success' => true, 'data' => ['payments' => []]]);
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
                'invoice_id' => $payment->invoice_id,
                'has_invoice' => $payment->invoice_id !== null,
                'discount_applied' => $payment->discount_amount > 0,
                'discount_amount' => $payment->discount_amount,
            ]);

        return response()->json(['success' => true, 'data' => ['payments' => $payments]]);
    }

    /**
     * Delete all user data and deactivate the account.
     *
     * POST /api/payment/delete-all-data
     */
    public function deleteAllData(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_preview_user) {
            return response()->json(['success' => false, 'message' => 'Data deletion is not available in preview mode'], 403);
        }

        $request->validate([
            'confirmation_text' => 'required|string',
            'current_password' => 'required|string',
        ]);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password'], 422);
        }

        if ($request->input('confirmation_text') !== 'DELETE') {
            return response()->json(['success' => false, 'message' => 'Please type DELETE to confirm data deletion'], 422);
        }

        $subscription = $user->subscription;

        if (! $subscription || ! $subscription->isInGracePeriod()) {
            return response()->json(['success' => false, 'message' => 'Data deletion is only available during the grace period'], 403);
        }

        $firstName = $user->first_name;
        $email = $user->email;

        try {
            $result = $this->purgeService->purgeUserData($user);

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

            return response()->json(['success' => false, 'message' => 'Failed to delete data. Please try again or contact support.'], 500);
        }
    }

    /**
     * Validate a discount code without applying it.
     *
     * POST /api/payment/validate-discount
     */
    public function validateDiscountCode(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'code' => 'required|string|max:50',
            'plan' => 'required|string|in:student,standard,family,pro',
            'billing_cycle' => 'required|string|in:monthly,yearly',
        ]);

        $plan = SubscriptionPlan::findBySlug($request->input('plan'));
        if (! $plan) {
            return response()->json(['success' => false, 'message' => 'Plan not found'], 404);
        }

        $billingCycle = $request->input('billing_cycle');
        $amount = $plan->getLaunchPriceForCycle($billingCycle) ?? $plan->getPriceForCycle($billingCycle);

        $result = $this->discountCodeService->validate(
            $request->input('code'),
            $user->id,
            $request->input('plan'),
            $billingCycle,
            $amount
        );

        return response()->json([
            'success' => $result['valid'],
            'message' => $result['message'],
            'data' => $result['valid'] ? [
                'discount_amount' => $result['discount_amount'],
                'final_amount' => $result['final_amount'],
                'discount_type' => $result['discount_type'],
                'discount_description' => $result['discount_description'],
                'original_amount' => $amount,
            ] : null,
        ]);
    }

    /**
     * Download an invoice PDF.
     *
     * GET /api/payment/invoices/{invoice}/download
     */
    public function downloadInvoice(Request $request, Invoice $invoice): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();

        if ($invoice->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        if (! $invoice->pdf_path || ! Storage::exists($invoice->pdf_path)) {
            // Regenerate if missing
            try {
                $this->invoiceService->regeneratePdf($invoice);
                $invoice->refresh();
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'Invoice PDF not available'], 404);
            }
        }

        return Storage::download($invoice->pdf_path, "{$invoice->invoice_number}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
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
