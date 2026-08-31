<?php

declare(strict_types=1);

namespace App\Http\Traits;

/**
 * A holding may not state units, a price and a value that contradict.
 *
 * **W-0127.** `HoldingValuation::reconcile()` resolves the three by precedence —
 * where units are supplied they win, and the value follows from them. That is
 * right for a FORM, where a user edits one field at a time and expects the
 * others to follow.
 *
 * **It is wrong for an IMPORT**, which supplies all three at once from a
 * statement. There, a value disagreeing with units × price is not a stale
 * derived figure to be refreshed; it is a contradiction in the source data, and
 * silently overwriting it discards the only evidence the import was wrong. The
 * user then reconciles against their platform and sees a figure Fynla computed,
 * not the one their provider sent, with no way to tell the difference.
 *
 * Refused at the boundary rather than inside `reconcile()`, which is a pure
 * function with thirteen callers and no business raising errors.
 */
trait ValidatesHoldingValuation
{
    /**
     * One per cent of tolerance, because rounding in a provider's own export is
     * ordinary and failing on a penny would refuse good data.
     */
    private const VALUATION_TOLERANCE = 0.01;

    protected function validateHoldingValuationAgrees($validator): void
    {
        $quantity = $this->input('quantity');
        $price = $this->input('current_price');
        $value = $this->input('current_value');

        if (! is_numeric($quantity) || ! is_numeric($price) || ! is_numeric($value)) {
            return;
        }

        $derived = (float) $quantity * (float) $price;

        if ($derived <= 0.0 || (float) $value <= 0.0) {
            return;
        }

        if (abs($derived - (float) $value) / $derived > self::VALUATION_TOLERANCE) {
            $validator->errors()->add(
                'current_value',
                'The value does not match the units and price: '.number_format((float) $quantity, 4)
                    .' × £'.number_format((float) $price, 4).' is £'.number_format($derived, 2)
                    .', not £'.number_format((float) $value, 2).'. Send whichever two are correct.'
            );
        }
    }
}
