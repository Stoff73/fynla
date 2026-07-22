<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Jobs\FireAwinConversionJob;
use App\Models\Payment;
use App\Services\Payment\PaymentFinalizationService;
use App\Services\Payment\PaymentSettlementService;
use App\Services\Payment\RevolutOrderVerifier;
use App\Services\Payment\RevolutService;
use App\Services\Payment\SubscriptionRenewalService;
use App\Services\Tiers\TierCollapseLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RevolutService $revolutService,
        private readonly SubscriptionRenewalService $renewalService,
        private readonly RevolutOrderVerifier $orderVerifier,
        private readonly TierCollapseLock $tierCollapseLock,
        private readonly PaymentSettlementService $paymentSettlementService,
        private readonly PaymentFinalizationService $paymentFinalizationService,
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

            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
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

        try {
            if ($event === 'ORDER_COMPLETED' && $orderId) {
                $this->handleOrderCompleted($orderId, $merchantRef);
            } else {
                $lockScope = 'webhook:'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
                $this->tierCollapseLock->run(fn () => match ($event) {
                    'SUBSCRIPTION_INITIATED' => $this->handleSubscriptionInitiated($payload),
                    'SUBSCRIPTION_OVERDUE' => $this->handleSubscriptionOverdue($payload),
                    'SUBSCRIPTION_CANCELLED' => $this->handleSubscriptionCancelled($payload),
                    'SUBSCRIPTION_FINISHED' => $this->handleSubscriptionFinished($payload),
                    default => Log::info('Revolut webhook: unhandled event', ['event' => $event]),
                }, $lockScope);
            }
        } catch (\Throwable $e) {
            Log::error('Revolut webhook processing failed', [
                'event' => $event,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return response()->json(['success' => true, 'message' => 'Webhook processed']);
    }

    private function handleOrderCompleted(string $orderId, ?string $merchantRef): void
    {
        $payment = Payment::where('revolut_order_id', $orderId)->first();

        if (! $payment) {
            Log::warning('Revolut webhook: payment not found', [
                'order_id' => $orderId,
                'merchant_ref' => $merchantRef,
            ]);

            throw new \RuntimeException('Revolut completion webhook does not match a payment.');
        }

        if ($merchantRef && $merchantRef !== "payment_{$payment->id}") {
            throw new \RuntimeException('Revolut webhook merchant reference does not match the payment.');
        }

        if (! in_array($payment->status, ['pending', 'completed'], true)) {
            throw new \RuntimeException('Only a pending payment can be completed.');
        }

        $revolutOrder = $this->revolutService->getOrder($orderId);
        $verificationFailure = $this->orderVerifier->completedOrderFailure($payment, $revolutOrder);
        if ($verificationFailure !== null) {
            throw new \RuntimeException($verificationFailure);
        }

        $payment = $this->tierCollapseLock->run(function () use ($payment, $revolutOrder): Payment {
            $settledPayment = $this->paymentSettlementService->settle($payment, $revolutOrder);

            return $this->paymentFinalizationService->finalize($settledPayment);
        }, "user:{$payment->user_id}");

        $user = $payment->user;
        Log::info('Revolut webhook: subscription activated', [
            'user_id' => $user->id,
            'order_id' => $orderId,
            'plan' => $payment->subscription->plan,
            'billing_cycle' => $payment->billing_cycle,
        ]);

        if (config('awin.enabled') && ! $user->is_admin) {
            FireAwinConversionJob::dispatch($payment->id);
        }
    }

    private function handleSubscriptionInitiated(array $payload): void
    {
        Log::info('Revolut subscription initiated', [
            'event' => $payload['event'] ?? null,
            'order_id' => $payload['order_id'] ?? null,
            'subscription_id' => $payload['subscription_id'] ?? null,
        ]);

        // Retained for compatibility with historical Revolut subscription rows.
        // Canonical Premium checkout uses one-time orders and never reaches this event.
    }

    private function handleSubscriptionOverdue(array $payload): void
    {
        $this->renewalService->handleSubscriptionOverdue($payload);
    }

    private function handleSubscriptionCancelled(array $payload): void
    {
        $this->renewalService->handleSubscriptionCancelled($payload);
    }

    private function handleSubscriptionFinished(array $payload): void
    {
        $this->renewalService->handleSubscriptionFinished($payload);
    }
}
