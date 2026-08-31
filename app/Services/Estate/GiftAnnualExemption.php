<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * The IHTA 1984 s19 annual exemption, applied to a household's gifts.
 *
 * **W-0367.** `FailedGiftTaxCalculator` took `gift_value` gross, so no lifetime
 * exemption ever reduced a chargeable transfer. A donor giving exactly the
 * annual allowance in a tax year — nothing chargeable at all — had the whole
 * amount cumulated against their nil rate band, overstating the estate's
 * cumulation and understating the band available at death.
 *
 * **No new data was needed, which is why this could be fixed rather than
 * deferred.** s19 applies the exemption chronologically within a tax year, so
 * `gift_date` and `gift_value` are sufficient. The `gifts` table records no
 * exemption columns and does not need to: the allocation is derived, not stated,
 * and a stored allocation would go stale the moment a gift was edited.
 *
 * Two rules that are easy to get subtly wrong, both configured and both tested:
 *
 *   * **The year starts on 6 April.** 5 and 6 April fall in different tax years,
 *     and treating the year as calendar gives a donor two allowances in one year
 *     or one across two.
 *   * **One unused year carries forward, and the CURRENT year is spent first**
 *     — the configured note says so. Spending the brought-forward allowance
 *     first would let it survive into a year it has already expired in.
 *
 * Deliberately NOT handling the small-gifts exemption (s20), gifts in
 * consideration of marriage (s22) or normal expenditure out of income (s21):
 * each needs a fact the application does not record — who the recipient is,
 * whether the gift was made on marriage, whether it formed a regular pattern.
 * Guessing any of them would invent a tax position. s21 is W-0525.
 */
class GiftAnnualExemption
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /**
     * Reduce each gift by the annual exemption available to it.
     *
     * Returns the same rows with `value` net of exemption and an `exempt` term
     * saying how much was relieved, so a surface can show the working rather
     * than a figure that silently differs from what the user entered.
     *
     * @param  list<array<string, mixed>>  $gifts  each with `gift_date` and `value`
     * @return list<array<string, mixed>>
     */
    public function applyTo(array $gifts): array
    {
        $rules = $this->taxConfig->get('gifting_exemptions', []);
        $annual = (float) ($rules['annual_exemption'] ?? 0);

        if ($annual <= 0 || $gifts === []) {
            return $gifts;
        }

        $canCarry = (bool) ($rules['annual_exemption_can_carry_forward'] ?? false);
        $carryYears = $canCarry ? (int) ($rules['carry_forward_years'] ?? 0) : 0;

        // Chronological, because s19 relieves the earliest gift first. Keys are
        // preserved so the caller gets its rows back in its own order.
        $order = array_keys($gifts);
        usort($order, fn ($a, $b) => strcmp(
            (string) $gifts[$a]['gift_date'],
            (string) $gifts[$b]['gift_date']
        ));

        /** @var array<int, float> $spent  tax year => amount of that year's allowance used */
        $spent = [];

        foreach ($order as $key) {
            $gift = $gifts[$key];
            $value = (float) $gift['value'];
            $year = self::taxYearOf((string) $gift['gift_date']);

            $relieved = 0.0;

            // Current year first, then each carried-forward year oldest-first.
            // The oldest is used before the newer one because it expires sooner.
            $years = [$year];
            for ($back = $carryYears; $back >= 1; $back--) {
                array_unshift($years, $year - $back);
            }
            $years = array_reverse($years);

            foreach ([$year, ...array_slice($years, 1)] as $candidate) {
                if ($value <= 0.0) {
                    break;
                }

                $available = $annual - ($spent[$candidate] ?? 0.0);

                if ($available <= 0.0) {
                    continue;
                }

                $take = min($available, $value);
                $spent[$candidate] = ($spent[$candidate] ?? 0.0) + $take;
                $relieved += $take;
                $value -= $take;
            }

            $gifts[$key]['exempt'] = round($relieved, 2);
            $gifts[$key]['value'] = round($value, 2);
        }

        return $gifts;
    }

    /**
     * The UK tax year a date falls in, named by the year it starts.
     *
     * 6 April 2024 to 5 April 2025 is "2024".
     */
    private static function taxYearOf(string $date): int
    {
        $moment = Carbon::parse($date);

        return $moment->month > 4 || ($moment->month === 4 && $moment->day >= 6)
            ? $moment->year
            : $moment->year - 1;
    }
}
