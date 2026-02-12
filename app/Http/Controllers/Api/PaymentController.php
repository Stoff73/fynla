<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\RevolutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
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

    public function orderStatus(Request $request, string $id, RevolutService $revolutService): JsonResponse
    {
        $order = $revolutService->getOrderStatus($id);

        return response()->json($order);
    }

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
