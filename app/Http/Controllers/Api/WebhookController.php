<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Mail\PaymentConfirmation;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\Payment\RevolutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WebhookController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RevolutService $revolutService
    ) {}

    /**
     * Handle Revolut webhook events.
     *
     * POST /api/webhooks/revolut
     *
     * Headers: Revolut-Signature, Revolut-Request-Timestamp
     * Body: { event, order_id }
     *
     * Responds 200 to acknowledge. Revolut retries 3x with 10-min delay on failure.
     */
    public function handleRevolut(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signatureHeader = $request->header('Revolut-Signature', '');
        $timestampHeader = $request->header('Revolut-Request-Timestamp', '');

        // Verify HMAC signature (v1.{timestamp}.{payload})
        if (! $this->revolutService->verifyWebhookSignature($rawPayload, $signatureHeader, $timestampHeader)) {
            Log::warning('Revolut webhook signature verification failed');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawPayload, true);
        $event = $payload['event'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $merchantRef = $payload['merchant_order_ext_ref'] ?? null;

        Log::info('Revolut webhook received', [
            'event' => $event,
            'order_id' => $orderId,
            'merchant_order_ext_ref' => $merchantRef,
        ]);

        if (in_array($event, ['ORDER_COMPLETED', 'ORDER_AUTHORISED']) && $orderId) {
            $this->handleOrderCompleted($orderId, $merchantRef);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleOrderCompleted(string $orderId, ?string $merchantRef): void
    {
        try {
            DB::transaction(function () use ($orderId, $merchantRef) {
                $payment = Payment::where('revolut_order_id', $orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    Log::warning('Revolut webhook: payment not found', [
                        'order_id' => $orderId,
                        'merchant_ref' => $merchantRef,
                    ]);

                    return;
                }

                // Cross-reference check
                if ($merchantRef && $merchantRef !== "payment_{$payment->id}") {
                    Log::warning('Revolut webhook: merchant_ref mismatch', [
                        'order_id' => $orderId,
                        'expected' => "payment_{$payment->id}",
                        'received' => $merchantRef,
                    ]);
                }

                // Idempotent: skip if already completed
                if ($payment->status === 'completed') {
                    Log::info('Revolut webhook: payment already completed', ['order_id' => $orderId]);

                    return;
                }

                // Verify with Revolut API
                $revolutOrder = $this->revolutService->getOrder($orderId);
                $captureMode = $revolutOrder['capture_mode'] ?? 'automatic';
                $acceptableStates = $captureMode === 'manual'
                    ? ['completed', 'authorised']
                    : ['completed'];

                if (! in_array($revolutOrder['state'], $acceptableStates)) {
                    Log::warning('Revolut webhook: order not in acceptable state', [
                        'order_id' => $orderId,
                        'state' => $revolutOrder['state'],
                        'capture_mode' => $captureMode,
                    ]);

                    return;
                }

                // Read plan and billing cycle from the Payment record (source of truth)
                $planSlug = $payment->plan_slug;
                $billingCycle = $payment->billing_cycle;

                $periodEnd = $billingCycle === 'monthly'
                    ? now()->addMonth()
                    : now()->addYear();

                $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug);

                // Activate payment
                $payment->update([
                    'status' => 'completed',
                    'revolut_payment_data' => $revolutOrder,
                ]);

                // Update subscription from payment data
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

                $user = $payment->user;
                $user->update([
                    'plan' => $planSlug,
                    'trial_ends_at' => null,
                ]);

                // Send confirmation email
                try {
                    Mail::to($user->email)->send(new PaymentConfirmation($user, $payment));
                } catch (\Exception $e) {
                    Log::error('Webhook: failed to send payment confirmation email', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                Log::info('Revolut webhook: subscription activated', [
                    'user_id' => $user->id,
                    'order_id' => $orderId,
                    'plan' => $planSlug,
                    'billing_cycle' => $billingCycle,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Revolut webhook processing failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
