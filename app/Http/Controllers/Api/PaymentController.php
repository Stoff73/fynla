<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\RevolutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Create a payment order via Revolut for the user's subscription.
     *
     * POST /api/payment/order
     *
     * @param  Request  $request  The HTTP request containing the authenticated user
     * @param  RevolutService  $revolutService  Revolut payment gateway service
     * @return JsonResponse  The order public ID and order ID
     */
    public function createOrder(Request $request, RevolutService $revolutService): JsonResponse
    {
        $user = $request->user();
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
            'order_id' => $order['id'] ?? null,
        ]);
    }

    /**
     * Get the status of an existing payment order.
     *
     * GET /api/payment/order/{id}/status
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $id  The Revolut order ID
     * @param  RevolutService  $revolutService  Revolut payment gateway service
     * @return JsonResponse  The order status details
     */
    public function orderStatus(Request $request, string $id, RevolutService $revolutService): JsonResponse
    {
        $order = $revolutService->getOrderStatus($id);

        return response()->json($order);
    }

    /**
     * Get the current trial and subscription status for the authenticated user.
     *
     * GET /api/payment/trial-status
     *
     * @param  Request  $request  The HTTP request containing the authenticated user
     * @return JsonResponse  Subscription details including trial progress and days remaining
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
            'payment_enabled' => $paymentEnabled,
        ]);
    }
}
