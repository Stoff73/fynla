<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Gift;
use App\Models\User;
use App\Services\TaxConfigService;

/**
 * Available nil-rate band derived from gift history: NRB minus non-exempt
 * gifts made within the prior 7 years (the cumulation window). Mirrors the
 * 7-year semantics of GiftingStrategy::analyzePETs.
 *
 * Exempt gift types excluded from cumulation (do not erode the NRB).
 * These correspond to the real DB enum values in the gifts.gift_type column:
 *   - annual_exemption  (£3,000/year gifting exemption + carry-forward)
 *   - small_gift        (£250 per recipient per year)
 *   - exempt            (spouse, charity, and other fully-exempt transfers)
 *
 * Non-exempt types that DO erode the NRB:
 *   - pet   (potentially exempt transfer — exempt only if donor survives 7 yrs)
 *   - clt   (chargeable lifetime transfer — immediately chargeable above NRB)
 */
final class AvailableNrbCalculator
{
    /**
     * Gift types that are fully exempt and do not erode the nil-rate band.
     * Values must match the gifts.gift_type DB enum exactly.
     */
    private const EXEMPT_GIFT_TYPES = [
        'annual_exemption',
        'small_gift',
        'exempt',
    ];

    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /**
     * Calculate the available nil-rate band for the given user, taking into
     * account non-exempt gifts made within the last 7 years.
     */
    public function forUser(User $user): float
    {
        $nrb = (float) ($this->taxConfig->getInheritanceTax()['nil_rate_band'] ?? 0);

        $gifted = (float) Gift::where('user_id', $user->id)
            ->where('gift_date', '>=', now()->subYears(7))
            ->whereNotIn('gift_type', self::EXEMPT_GIFT_TYPES)
            ->sum('gift_value');

        return max(0.0, $nrb - $gifted);
    }
}
