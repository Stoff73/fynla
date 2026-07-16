<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\DiscountCode;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
use Illuminate\Support\Facades\DB;

class PaymentSettlementService
{
    public function __construct(
        private readonly TierConfigurationStore $tierStore,
        private readonly DiscountCodeService $discountCodeService,
    ) {}

    /**
     * Atomically convert a verified provider order into paid entitlement.
     */
    public function settle(Payment $sourcePayment, array $providerOrder): Payment
    {
        return DB::transaction(function () use ($sourcePayment, $providerOrder): Payment {
            $user = User::query()->lockForUpdate()->findOrFail($sourcePayment->user_id);
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($sourcePayment->subscription_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($sourcePayment->id);

            if ($payment->status === 'completed') {
                return $payment;
            }

            if ($payment->status !== 'pending') {
                throw new \RuntimeException('Only a pending payment can be completed.');
            }

            $planSlug = TierConfigurationStore::canonicalPlanForEntitlement($payment->plan_slug);
            $billingCycle = $payment->billing_cycle;
            $isUpgrade = ! empty($payment->upgrade_from_plan);

            if ($isUpgrade && in_array($planSlug, TierConfigurationStore::TIERS, true)) {
                $fullPrice = $this->tierStore->priceForCycle($planSlug, $billingCycle);
            } else {
                $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug);
                $fullPrice = $subscriptionPlan
                    ? $subscriptionPlan->getPriceForCycle($billingCycle)
                    : $payment->amount;
            }

            if ($payment->discount_code_id !== null) {
                $discount = DiscountCode::query()->findOrFail($payment->discount_code_id);
                $this->discountCodeService->apply(
                    $discount,
                    $user->id,
                    $payment->id,
                    (int) ($payment->amount + $payment->discount_amount),
                );
            }

            $subscriptionUpdate = [
                'status' => 'active',
                'plan' => $planSlug,
                'billing_cycle' => $billingCycle,
                'amount' => $fullPrice,
                'auto_renew' => false,
                'payment_method_saved' => false,
                'revolut_order_id' => $payment->revolut_order_id,
                'revolut_subscription_id' => null,
                'revolut_plan_id' => null,
                'revolut_plan_variation_id' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'data_retention_starts_at' => null,
            ];

            if (! $isUpgrade) {
                $subscriptionUpdate['current_period_start'] = now();
                $subscriptionUpdate['current_period_end'] = $billingCycle === 'monthly'
                    ? now()->addMonth()
                    : now()->addYear();
            }

            $subscription->update($subscriptionUpdate);

            $userUpdate = [
                'plan' => $planSlug,
                'trial_ends_at' => null,
            ];
            if (in_array($planSlug, TierConfigurationStore::TIERS, true)) {
                $userUpdate['tier'] = $planSlug;
            }
            $user->update($userUpdate);

            $payment->update([
                'status' => 'completed',
                'revolut_payment_data' => $providerOrder,
            ]);

            return $payment;
        });
    }
}
