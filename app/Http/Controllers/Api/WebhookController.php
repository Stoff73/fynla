<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Jobs\FireAwinConversionJob;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\Payment\RevolutOrderVerifier;
use App\Services\Payment\RevolutService;
use App\Services\Payment\SubscriptionRenewalService;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierCollapseLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RevolutService $revolutService,
        private readonly SubscriptionRenewalService $renewalService,
        private readonly RevolutOrderVerifier $orderVerifier,
        private readonly TierCollapseLock $tierCollapseLock,
        private readonly TierConfigurationStore $tierStore,
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
            $this->tierCollapseLock->run(fn () => match ($event) {
                'ORDER_COMPLETED' => $orderId ? $this->handleOrderCompleted($orderId, $merchantRef) : null,
                'SUBSCRIPTION_INITIATED' => $this->handleSubscriptionInitiated($payload),
                'SUBSCRIPTION_OVERDUE' => $this->handleSubscriptionOverdue($payload),
                'SUBSCRIPTION_CANCELLED' => $this->handleSubscriptionCancelled($payload),
                'SUBSCRIPTION_FINISHED' => $this->handleSubscriptionFinished($payload),
                default => Log::info('Revolut webhook: unhandled event', ['event' => $event]),
            });
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
        DB::transaction(function () use ($orderId, $merchantRef) {
            $payment = Payment::where('revolut_order_id', $orderId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning('Revolut webhook: payment not found', [
                    'order_id' => $orderId,
                    'merchant_ref' => $merchantRef,
                ]);

                throw new \RuntimeException('Revolut completion webhook does not match a payment.');
            }

            // Cross-reference check
            if ($merchantRef && $merchantRef !== "payment_{$payment->id}") {
                throw new \RuntimeException('Revolut webhook merchant reference does not match the payment.');
            }

            // Idempotent: skip if already completed
            if ($payment->status === 'completed') {
                Log::info('Revolut webhook: payment already completed', ['order_id' => $orderId]);

                return;
            }

            if ($payment->status !== 'pending') {
                throw new \RuntimeException('Only a pending payment can be completed.');
            }

            // Verify with Revolut API
            $revolutOrder = $this->revolutService->getOrder($orderId);
            $verificationFailure = $this->orderVerifier->completedOrderFailure($payment, $revolutOrder);

            if ($verificationFailure !== null) {
                throw new \RuntimeException($verificationFailure);
            }

            // Read plan and billing cycle from the Payment record (source of truth)
            $planSlug = TierConfigurationStore::canonicalPlanForEntitlement($payment->plan_slug);
            $billingCycle = $payment->billing_cycle;
            $isUpgrade = ! empty($payment->upgrade_from_plan);

            $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug);
            $renewalAmount = $isUpgrade && in_array($planSlug, TierConfigurationStore::TIERS, true)
                ? $this->tierStore->priceForCycle($planSlug, $billingCycle)
                : ($subscriptionPlan ? $subscriptionPlan->getPriceForCycle($billingCycle) : $payment->amount);

            // Activate payment
            $payment->update([
                'status' => 'completed',
                'revolut_payment_data' => $revolutOrder,
            ]);

            // Update subscription from payment data
            $subscription = $payment->subscription;
            $subscriptionUpdate = [
                'status' => 'active',
                'plan' => $planSlug,
                'billing_cycle' => $billingCycle,
                'amount' => $renewalAmount,
                'auto_renew' => true,
                'payment_method_saved' => true,
                'revolut_order_id' => $orderId,
                'cancelled_at' => null,
                'cancellation_reason' => null,
            ];
            if (! $isUpgrade) {
                $subscriptionUpdate['current_period_start'] = now();
                $subscriptionUpdate['current_period_end'] = $billingCycle === 'monthly'
                    ? now()->addMonth()
                    : now()->addYear();
            }
            $subscription->update($subscriptionUpdate);

            // `plan` is the legacy billing-compat column (§5.2). For a
            // tier-key purchase ALSO set the canonical `tier` column —
            // TierResolver/DbTierGate key off `tier`, so a paying
            // customer must have it set or they resolve as Free.
            // Legacy slugs (student/standard/family/pro) leave `tier`
            // null: A9/§5.2 grandfather logic owns those.
            $user = $payment->user;
            $userUpdate = [
                'plan' => $planSlug,
                'trial_ends_at' => null,
            ];
            if (in_array($planSlug, TierConfigurationStore::TIERS, true)) {
                $userUpdate['tier'] = $planSlug;
            }
            $user->update($userUpdate);

            // Confirmation email is sent from confirmPayment() after invoice
            // generation so the PDF can be attached. Not sent here because
            // the invoice doesn't exist yet at webhook time.

            Log::info('Revolut webhook: subscription activated', [
                'user_id' => $user->id,
                'order_id' => $orderId,
                'plan' => $planSlug,
                'billing_cycle' => $billingCycle,
            ]);

            // Fire Awin conversion (idempotent — job short-circuits if
            // awin_fired_at is already set). Dispatched from both webhook
            // and confirmPayment paths; whichever arrives second is a
            // no-op. Admin accounts are excluded.
            if (config('awin.enabled') && ! $user->is_admin) {
                FireAwinConversionJob::dispatch($payment->id);
            }
        });
    }

    private function handleSubscriptionInitiated(array $payload): void
    {
        Log::info('Revolut subscription initiated', [
            'event' => $payload['event'] ?? null,
            'order_id' => $payload['order_id'] ?? null,
            'subscription_id' => $payload['subscription_id'] ?? null,
        ]);

        // The subscription is now active — Revolut will handle recurring billing.
        // The initial payment is handled via ORDER_COMPLETED for the setup order.
        // Future renewal payments also come via ORDER_COMPLETED for each cycle's order.
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
