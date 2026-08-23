<?php

declare(strict_types=1);

namespace App\Support;

/**
 * THE conversion from a premium and its frequency to an annual cost (Rule 20).
 *
 * There were two. `ComprehensiveProtectionPlanService::convertToAnnualPremium()` was
 * private, so `/m` could not reach it and wrote its own `switch` in
 * `ProtectionPolicy.vue` — a client-side financial calculation, which CSJ ruled out
 * on 2026-08-23: **`/m` displays what the backend computed; it never works anything
 * out.** Two copies of a mapping is also two places for a frequency to be added to
 * one and missed in the other.
 *
 * The default is deliberate and matches both originals: an unrecognised or missing
 * frequency is treated as monthly, because that is what the overwhelming majority of
 * policies are and because reading a monthly premium as annual would understate a
 * household's protection cost twelvefold.
 */
final class PremiumAnnualiser
{
    public static function toAnnual(?float $amount, ?string $frequency): float
    {
        if ($amount === null || $amount <= 0.0) {
            return 0.0;
        }

        return match ($frequency) {
            'quarterly' => $amount * 4,
            'annually', 'annual', 'yearly' => $amount,
            'weekly' => $amount * 52,
            default => $amount * 12,
        };
    }
}
