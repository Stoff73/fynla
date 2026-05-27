<?php

declare(strict_types=1);

namespace App\Services\Stores\Recalc;

use App\Models\Mortgage;

/**
 * Recomputes canonical derived columns on a Mortgage row:
 *  - outstanding_balance_gbp: outstanding_balance × FX-rate (GBP-only today, so === outstanding_balance)
 *  - monthly_payment_gbp: monthly_payment × FX-rate (GBP-only today, so === monthly_payment)
 *  - current_ltv_pct: (outstanding_balance / property.current_value) × 100
 *
 * Called from MortgageStore::create + ::update + via cross-store recalc.
 *
 * Pass 5 PR 6 — currency conversion lands in a later sub-project pass. Mortgages
 * are GBP-denominated by canonical contract today so _gbp == raw value.
 */
class MortgageDerivedColumnCalculator
{
    /**
     * @return array<string, mixed> changes applied to the model (empty if no-op)
     */
    public function recalculate(Mortgage $mortgage): array
    {
        $changes = [];

        // outstanding_balance_gbp: GBP-only today (mortgages are GBP-denominated by canonical contract)
        $newBalanceGbp = (float) ($mortgage->outstanding_balance ?? 0);
        if ((float) ($mortgage->outstanding_balance_gbp ?? 0) !== $newBalanceGbp) {
            $changes['outstanding_balance_gbp'] = $newBalanceGbp;
            $changes['outstanding_balance_gbp_calculated_at'] = now();
        }

        // monthly_payment_gbp: same — GBP-only
        $newPaymentGbp = (float) ($mortgage->monthly_payment ?? 0);
        if ((float) ($mortgage->monthly_payment_gbp ?? 0) !== $newPaymentGbp) {
            $changes['monthly_payment_gbp'] = $newPaymentGbp;
            $changes['monthly_payment_gbp_calculated_at'] = now();
        }

        // current_ltv_pct: requires the Property row
        $mortgage->loadMissing('property');
        if ($mortgage->property && (float) $mortgage->property->current_value > 0) {
            $newLtv = round(($newBalanceGbp / (float) $mortgage->property->current_value) * 100, 4);
            if ((float) ($mortgage->current_ltv_pct ?? -1) !== $newLtv) {
                $changes['current_ltv_pct'] = $newLtv;
                $changes['current_ltv_pct_calculated_at'] = now();
            }
        }

        if (! empty($changes)) {
            $mortgage->forceFill($changes)->saveQuietly();
        }

        return $changes;
    }
}
