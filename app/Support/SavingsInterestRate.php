<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The one reading of `savings_accounts.interest_rate`: it holds a percentage,
 * 4.25 meaning 4.25%, never a fraction. The evidence:
 *
 *   - `SavingsAccount::annualInterest` and `RateComparator` divide it by 100;
 *   - `SavingsStore` validates it as 0 to 20 ("cannot exceed 20%");
 *   - the factory writes percentages, 1.0 to 5.0;
 *   - migration 2026_08_25_120000 records the live values as percentages.
 *
 * Six places used to guess instead: "above 1 is a percentage, otherwise a
 * fraction". That read an account paying 1% as paying 100%, and 0.5% as 50%,
 * so a £4,000 account at 1% showed £4,000 of interest (ice-cube, PR 991).
 */
final class SavingsInterestRate
{
    /** The stored percentage as a fraction of the balance, e.g. 4.25 → 0.0425. */
    public static function fraction(mixed $storedPercent): float
    {
        return max(0.0, (float) $storedPercent) / 100;
    }
}
