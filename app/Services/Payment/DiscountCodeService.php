<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use Illuminate\Support\Facades\Log;

class DiscountCodeService
{
    /**
     * Validate a discount code for a specific user, plan, and billing cycle.
     *
     * @return array{valid: bool, message: string, discount: ?DiscountCode, discount_amount: int, final_amount: int}
     */
    public function validate(
        string $code,
        int $userId,
        string $planSlug,
        string $billingCycle,
        int $amountPence
    ): array {
        $code = strtoupper(trim($code));

        $discount = DiscountCode::where('code', $code)->first();

        if (! $discount) {
            return $this->invalid('Discount code not found.');
        }

        if (! $discount->is_active) {
            return $this->invalid('This discount code is no longer active.');
        }

        if ($discount->expires_at && $discount->expires_at->isPast()) {
            return $this->invalid('This discount code has expired.');
        }

        if ($discount->starts_at && $discount->starts_at->isFuture()) {
            return $this->invalid('This discount code is not yet active.');
        }

        if (! $discount->hasUsesRemaining()) {
            return $this->invalid('This discount code has reached its maximum number of uses.');
        }

        if ($discount->userUsageCount($userId) >= $discount->max_uses_per_user) {
            return $this->invalid('You have already used this discount code.');
        }

        if (! $discount->isValidForPlan($planSlug)) {
            return $this->invalid('This discount code is not valid for the selected plan.');
        }

        if (! $discount->isValidForCycle($billingCycle)) {
            return $this->invalid('This discount code is not valid for the selected billing cycle.');
        }

        $discountAmount = $this->calculateDiscount($discount, $amountPence);
        $finalAmount = max(0, $amountPence - $discountAmount);

        $description = match ($discount->type) {
            'percentage' => "{$discount->value}% off",
            'fixed_amount' => '£' . number_format($discount->value / 100, 2) . ' off',
            'trial_extension' => "{$discount->value} extra trial days",
            default => 'Discount applied',
        };

        return [
            'valid' => true,
            'message' => 'Discount code applied successfully.',
            'discount' => $discount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'discount_type' => $discount->type,
            'discount_description' => $description,
        ];
    }

    /**
     * Apply a discount code — record usage and increment the counter.
     *
     * @return int The discounted amount in pence
     */
    public function apply(
        DiscountCode $discount,
        int $userId,
        int $paymentId,
        int $originalAmountPence
    ): int {
        DiscountCodeUsage::create([
            'discount_code_id' => $discount->id,
            'user_id' => $userId,
            'payment_id' => $paymentId,
            'applied_at' => now(),
        ]);

        $discount->increment('times_used');

        Log::info('Discount code applied', [
            'code' => $discount->code,
            'user_id' => $userId,
            'payment_id' => $paymentId,
            'discount_amount' => $this->calculateDiscount($discount, $originalAmountPence),
        ]);

        return $this->calculateDiscount($discount, $originalAmountPence);
    }

    /**
     * Calculate the discount amount in pence.
     */
    public function calculateDiscount(DiscountCode $discount, int $amountPence): int
    {
        return match ($discount->type) {
            'percentage' => (int) round($amountPence * $discount->value / 100),
            'fixed_amount' => min($discount->value, $amountPence),
            'trial_extension' => 0,
            default => 0,
        };
    }

    private function invalid(string $message): array
    {
        return [
            'valid' => false,
            'message' => $message,
            'discount' => null,
            'discount_amount' => 0,
            'final_amount' => 0,
            'discount_type' => null,
            'discount_description' => null,
        ];
    }
}
