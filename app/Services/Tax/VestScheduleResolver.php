<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Investment\EmployeeSchemeCalculationService;
use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * Scheduled share-scheme vests in the current tax year, valued at the recorded
 * share price. `InvestmentAccount` has held the schedule since 2026-01-29; the
 * tax layer never read it, so a user whose RSUs vest twice a year could not be
 * told they will cross £100,000 (Threshold-First spec, 2026-09-17).
 *
 * Only schemes taxed as employment income at vest or exercise count as income:
 * RSUs (ITEPA 2003 s62) and unapproved options (s476). EMI, CSOP and SAYE gains
 * are capital and stay out.
 *
 * An RSU is taxed on the full market value of the share, because the holder pays
 * nothing for it. An unapproved option is taxed on the spread, market value less
 * the exercise price, and is **assumed exercised on its vest date** — the s476
 * charge arises on exercise, not on vest, so the year an option lands in depends
 * on a decision the holder has not made yet. Vest date is the earliest it can
 * fall and the one date the schedule actually records.
 */
final class VestScheduleResolver
{
    private const INCOME_AT_VEST = ['rsu', 'unapproved_options'];

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly EmployeeSchemeCalculationService $schemes,
    ) {}

    /**
     * Events on or after today in the current tax year, soonest first.
     *
     * @return list<array{date: Carbon, value: float, account_id: int, account_name: string}>
     */
    public function schedule(User $user): array
    {
        $today = Carbon::today();

        return array_values(array_filter(
            $this->eventsThisTaxYear($user),
            fn (array $event): bool => $event['date']->gte($today),
        ));
    }

    /** Every vest in the current tax year, past and future, valued. */
    public function annualVestIncome(User $user): float
    {
        return round(array_sum(array_column($this->eventsThisTaxYear($user), 'value')), 2);
    }

    /** @return list<array{date: Carbon, value: float, account_id: int, account_name: string}> */
    private function eventsThisTaxYear(User $user): array
    {
        [$yearStart, $yearEnd] = $this->taxYearBounds();
        $events = [];

        $user->loadMissing('investmentAccounts');
        foreach ($user->investmentAccounts as $account) {
            if (! in_array($account->account_type, self::INCOME_AT_VEST, true)
                || ($account->scheme_status ?? 'active') !== 'active'
                || (int) ($account->units_unvested ?? 0) <= 0
                || ! $account->full_vest_date
                || (float) ($account->current_share_price ?? 0) <= 0) {
                continue;
            }

            // The spread for an option, the whole share price for an RSU. Nil means an
            // underwater option: exercising it would cost more than the share is worth,
            // so there is no employment income to charge and no event to show.
            $perUnit = $this->schemes->taxableValuePerUnit($account);
            if ($perUnit <= 0) {
                continue;
            }

            foreach ($this->tranches($account) as $date => $units) {
                $date = Carbon::parse($date);
                if ($date->lt($yearStart) || $date->gt($yearEnd)) {
                    continue;
                }
                $events[] = [
                    'date' => $date,
                    'value' => round($units * $perUnit, 2),
                    'account_id' => (int) $account->id,
                    'account_name' => (string) ($account->account_name ?? $account->provider),
                ];
            }
        }

        usort($events, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $events;
    }

    /**
     * Tranche dates from the ladder floor up to `full_vest_date`, every
     * `vesting_frequency_months`. Unvested units split evenly across the dates on
     * or after today; dates earlier in this tax year carry the same units, so the
     * year's income counts what has already vested.
     *
     * Three things stop the ladder inventing vests that never happened. Nothing
     * vests before the grant was made, so the floor is the later of the tax year
     * start and `grant_date`. Nothing vests before a cliff, so a cliff still ahead
     * takes `cliff_percentage` of the granted units on `cliff_date` and every
     * earlier ladder date is dropped. And a schedule recorded as cliff-only with no
     * stated percentage has exactly one event: the lot, on the full vest date.
     *
     * ponytail: even split; per-tranche unit counts if a scheme with uneven
     * tranches is ever captured.
     *
     * @return array<string, float> date => units
     */
    private function tranches(InvestmentAccount $account): array
    {
        $today = Carbon::today();
        [$yearStart] = $this->taxYearBounds();
        $anchor = Carbon::parse($account->full_vest_date)->startOfDay();
        $unvested = (float) $account->units_unvested;

        // A cliff with no percentage is all-or-nothing: the whole grant on one date.
        if ($account->vesting_type === 'cliff' && ! $account->cliff_percentage) {
            return [$anchor->toDateString() => $unvested];
        }

        $frequency = max(1, (int) ($account->vesting_frequency_months ?? 12));
        $tranches = [];
        $floor = $this->ladderFloor($account, $yearStart);

        if ($account->cliff_date && $account->cliff_percentage && Carbon::parse($account->cliff_date)->gte($today)) {
            $cliffDate = Carbon::parse($account->cliff_date)->startOfDay();
            $cliffUnits = min($unvested, round((float) ($account->units_granted ?? $unvested) * (int) $account->cliff_percentage / 100, 4));
            $tranches[$cliffDate->toDateString()] = $cliffUnits;
            $unvested -= $cliffUnits;

            if ($cliffDate->gt($floor)) {
                $floor = $cliffDate;
            }
        }

        $dates = [];
        for ($step = 0; ; $step++) {
            // Measured from the anchor every time, never from the previous date: chaining
            // the subtraction drags a month-end vest backwards through the short months
            // (31 May quarterly reaches 3 December, not 30 November).
            $date = $anchor->copy()->subMonthsNoOverflow($step * $frequency);
            if ($date->lt($floor)) {
                break;
            }
            $dates[] = $date->toDateString();
        }
        sort($dates);

        $remaining = array_filter($dates, fn (string $d): bool => Carbon::parse($d)->gte($today));
        $perTranche = $remaining === [] ? 0.0 : $unvested / count($remaining);
        foreach ($dates as $date) {
            $tranches[$date] = ($tranches[$date] ?? 0.0) + $perTranche;
        }

        return $tranches;
    }

    /**
     * The earliest date a tranche can sit on: the tax year start, unless the grant
     * itself is later in the year, in which case no unit existed before it.
     */
    private function ladderFloor(InvestmentAccount $account, Carbon $yearStart): Carbon
    {
        $granted = $account->grant_date ?? $account->scheme_start_date;
        if (! $granted) {
            return $yearStart;
        }

        $granted = Carbon::parse($granted)->startOfDay();

        return $granted->gt($yearStart) ? $granted : $yearStart;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function taxYearBounds(): array
    {
        // TaxConfigService::getTaxYear() returns "2026/27"; the year starts 6 April.
        $startYear = (int) substr($this->taxConfig->getTaxYear(), 0, 4);

        return [Carbon::create($startYear, 4, 6)->startOfDay(), Carbon::create($startYear + 1, 4, 5)->endOfDay()];
    }
}
