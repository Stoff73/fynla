<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * Long-term UK residence for Inheritance Tax — the ONE place it is decided.
 *
 * Since 6 April 2025 the Inheritance Tax scope test is residence, not domicile:
 * a person is a long-term UK resident when they were UK resident in at least
 * `qualifying_years` of the `lookback_years` tax years immediately before the
 * tax year in question (IHTA 1984 s6A,
 * https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm47020).
 * The numbers live in tax config (`domicile.long_term_residence`), never here.
 *
 * Residence is counted in whole tax years from the tax year the user came to
 * live in the UK (their date of birth when UK-domiciled with no arrival date),
 * assuming they have lived here since. The "tail" after leaving the UK
 * (`tail_years_by_years_resident`) is not applied: the profile records no
 * departure date.
 */
final class LongTermResidence
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /** @return array<string, mixed>|null the rule for the active tax year, or null before s6A */
    public function rule(): ?array
    {
        $rule = $this->taxConfig->getDomicile()['long_term_residence'] ?? null;

        return is_array($rule) ? $rule : null;
    }

    /**
     * @return array{is_long_term_uk_resident: ?bool, years_resident_in_lookback: ?int, qualifying_years: ?int, lookback_years: ?int, long_term_resident_from: ?string, explanation: string}
     */
    public function assess(User $user, ?Carbon $asAt = null): array
    {
        $rule = $this->rule();
        $residentFrom = $this->residentFrom($user);

        if ($rule === null) {
            return $this->result(null, null, $rule, null, 'The long-term UK residence test for Inheritance Tax is not configured for this tax year.');
        }

        $qualifying = (int) $rule['qualifying_years'];
        $lookback = (int) $rule['lookback_years'];

        if ($residentFrom === null) {
            return $this->result(null, null, $rule, null, $this->hasNoStatus($user)
                ? 'Tell us where you were born and when you came to live in the UK, and we can work out whether you are a long-term UK resident for Inheritance Tax.'
                : 'Tell us when you came to live in the UK, and we can work out whether you are a long-term UK resident for Inheritance Tax.');
        }

        $firstYear = self::taxYearStart($residentFrom);
        $currentYear = self::taxYearStart($asAt ?? Carbon::now());
        // Whole tax years resident before the current one, within the lookback.
        $yearsBefore = max(0, $currentYear->year - $firstYear->year);
        $inLookback = min($lookback, $yearsBefore);
        $isLongTerm = $inLookback >= $qualifying;
        $from = $firstYear->copy()->addYears($qualifying);

        $explanation = $isLongTerm
            ? "You are a long-term UK resident for Inheritance Tax: UK resident in at least {$qualifying} of the last {$lookback} tax years. Inheritance Tax can apply to your assets worldwide."
            : sprintf(
                'You are not yet a long-term UK resident for Inheritance Tax. You have %d %s of UK residence in the last %d. At %d you become one, from %s. Until then Inheritance Tax applies to your UK assets only.',
                $inLookback,
                $inLookback === 1 ? 'tax year' : 'tax years',
                $lookback,
                $qualifying,
                $from->format('j F Y'),
            );

        return $this->result($isLongTerm, $inLookback, $rule, $from, $explanation);
    }

    /**
     * A tax year begins on 6 April (ITA 2007 s4(2),
     * https://www.legislation.gov.uk/ukpga/2007/3/section/4).
     */
    public static function taxYearStart(Carbon $date): Carbon
    {
        $year = ($date->month > 4 || ($date->month === 4 && $date->day >= 6)) ? $date->year : $date->year - 1;

        return Carbon::create($year, 4, 6)->startOfDay();
    }

    private function residentFrom(User $user): ?Carbon
    {
        if ($user->uk_arrival_date) {
            return Carbon::parse($user->uk_arrival_date);
        }

        return $user->domicile_status === 'uk_domiciled' && $user->date_of_birth
            ? Carbon::parse($user->date_of_birth)
            : null;
    }

    private function hasNoStatus(User $user): bool
    {
        return $user->domicile_status === null && $user->country_of_birth === null;
    }

    /** @param  array<string, mixed>|null  $rule */
    private function result(?bool $isLongTerm, ?int $inLookback, ?array $rule, ?Carbon $from, string $explanation): array
    {
        return [
            'is_long_term_uk_resident' => $isLongTerm,
            'years_resident_in_lookback' => $inLookback,
            'qualifying_years' => isset($rule['qualifying_years']) ? (int) $rule['qualifying_years'] : null,
            'lookback_years' => isset($rule['lookback_years']) ? (int) $rule['lookback_years'] : null,
            'long_term_resident_from' => $from?->format('Y-m-d'),
            'explanation' => $explanation,
        ];
    }
}
