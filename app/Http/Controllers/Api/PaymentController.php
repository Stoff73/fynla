<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\DataDeletionConfirmation;
use App\Mail\SubscriptionCancellation;
use App\Models\User;
use App\Services\Payment\DataPurgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{
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
