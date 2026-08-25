<?php

declare(strict_types=1);

namespace App\Services\History;

use App\Models\DBPensionValueSnapshot;
use App\Models\DCPensionValueSnapshot;
use App\Models\InvestmentAccountValueSnapshot;
use App\Models\LiabilityValueSnapshot;
use App\Models\MortgageValueSnapshot;
use App\Models\PropertyValueSnapshot;
use App\Models\SavingsAccountValueSnapshot;
use App\Models\StatePensionValueSnapshot;
use App\Models\User;
use App\Services\Stores\Snapshots\SnapshotPolicies;
use App\Services\Tiers\TierResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BalanceHistoryService
{
    private const RETENTION_DAYS = 2555;

    public function __construct(
        private readonly TierResolver $tierResolver,
        private readonly SnapshotPolicies $snapshotPolicies,
    ) {}

    public function forUser(User $user, ?Carbon $requestedFrom = null, ?Carbon $requestedTo = null): array
    {
        $tier = $this->tierResolver->resolve($user);
        $policy = $this->snapshotPolicies->investmentAccountValue();
        $windowDays = $policy->surfacingWindow($tier);

        $requestedFrom = ($requestedFrom ?? now()->subDays(self::RETENTION_DAYS))->startOfDay();
        $requestedTo = ($requestedTo ?? now())->endOfDay();
        $windowStart = now()->subDays($windowDays ?? self::RETENTION_DAYS)->startOfDay();
        $effectiveFrom = $windowDays !== null && $requestedFrom->lessThan($windowStart)
            ? $windowStart
            : $requestedFrom->copy();

        $series = collect()
            ->concat($this->standardSeries(
                InvestmentAccountValueSnapshot::class,
                'investmentAccount',
                'investment_account_id',
                'investment',
                'asset',
                'current_value_gbp',
                $user,
                $effectiveFrom,
                $requestedTo,
                true,
            ))
            ->concat($this->standardSeries(
                SavingsAccountValueSnapshot::class,
                'savingsAccount',
                'savings_account_id',
                'savings',
                'asset',
                'balance_gbp',
                $user,
                $effectiveFrom,
                $requestedTo,
                true,
            ))
            ->concat($this->standardSeries(
                DCPensionValueSnapshot::class,
                'dcPension',
                'dc_pension_id',
                'pension',
                'asset',
                'current_fund_value_gbp',
                $user,
                $effectiveFrom,
                $requestedTo,
                false,
            ))
            ->concat($this->standardSeries(
                PropertyValueSnapshot::class,
                'property',
                'property_id',
                'property',
                'asset',
                'current_value_gbp',
                $user,
                $effectiveFrom,
                $requestedTo,
                true,
            ))
            ->concat($this->mortgageSeries($user, $effectiveFrom, $requestedTo))
            ->concat($this->standardSeries(
                LiabilityValueSnapshot::class,
                'liability',
                'liability_id',
                'liability',
                'liability',
                'current_balance',
                $user,
                $effectiveFrom,
                $requestedTo,
                true,
            ))
            ->concat($this->standardSeries(
                DBPensionValueSnapshot::class,
                'dbPension',
                'db_pension_id',
                'pension_income',
                'income',
                'projected_annual_pension_at_nra_gbp',
                $user,
                $effectiveFrom,
                $requestedTo,
                false,
            ))
            ->concat($this->standardSeries(
                StatePensionValueSnapshot::class,
                'statePension',
                'state_pension_id',
                'pension_income',
                'income',
                'state_pension_forecast_annual_gbp',
                $user,
                $effectiveFrom,
                $requestedTo,
                false,
            ))
            ->values();

        $totals = $this->buildTotals($series);

        return [
            'requested_range' => [
                'from' => $requestedFrom->toDateString(),
                'to' => $requestedTo->toDateString(),
            ],
            'effective_range' => [
                'from' => $effectiveFrom->toDateString(),
                'to' => $requestedTo->toDateString(),
            ],
            'visibility' => [
                'window_days' => $windowDays,
                'label' => $windowDays === null ? 'Full available history' : "{$windowDays} days of history",
            ],
            'series' => $series->all(),
            'totals' => $totals,
            'year_on_year' => $this->yearOnYear($totals),
        ];
    }

    /**
     * @param  class-string<Model>  $snapshotClass
     * @return Collection<int, array<string, mixed>>
     */
    private function standardSeries(
        string $snapshotClass,
        string $relation,
        string $parentKey,
        string $category,
        string $balanceType,
        string $columnName,
        User $user,
        Carbon $from,
        Carbon $to,
        bool $supportsJointOwnership,
    ): Collection {
        $snapshots = $snapshotClass::query()
            ->where('column_name', $columnName)
            ->whereBetween('taken_at', [$from, $to])
            ->whereHas($relation, function ($query) use ($user, $supportsJointOwnership) {
                $query->where('user_id', $user->id);
                if ($supportsJointOwnership) {
                    $query->orWhere('joint_owner_id', $user->id);
                }
            })
            ->with($relation)
            ->orderBy('taken_at')
            ->get();

        return $snapshots->groupBy($parentKey)->map(function (Collection $rows, int|string $parentId) use ($relation, $category, $balanceType, $supportsJointOwnership, $user) {
            $parent = $rows->first()->getRelation($relation);
            $ownershipFraction = $supportsJointOwnership
                ? $this->ownershipFraction($parent, $user->id)
                : 1.0;

            return [
                'id' => $category.'-'.$parentId,
                'category' => $category,
                'name' => $this->labelFor($category, $parent),
                'balance_type' => $balanceType,
                'currency' => 'GBP',
                'points' => $this->pointsFrom($rows, 'taken_at', 'value_gbp', $ownershipFraction),
            ];
        })->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function mortgageSeries(User $user, Carbon $from, Carbon $to): Collection
    {
        $snapshots = MortgageValueSnapshot::query()
            ->where('snapshot_type', 'mortgageBalance')
            ->whereBetween('snapshotted_at', [$from, $to])
            ->whereHas('mortgage', fn ($query) => $query
                ->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id))
            ->with('mortgage')
            ->orderBy('snapshotted_at')
            ->get();

        return $snapshots->groupBy('mortgage_id')->map(function (Collection $rows, int|string $parentId) use ($user) {
            $mortgage = $rows->first()->mortgage;

            return [
                'id' => 'mortgage-'.$parentId,
                'category' => 'mortgage',
                'name' => $this->labelFor('mortgage', $mortgage),
                'balance_type' => 'liability',
                'currency' => 'GBP',
                'points' => $this->pointsFrom(
                    $rows,
                    'snapshotted_at',
                    'value',
                    $this->ownershipFraction($mortgage, $user->id),
                ),
            ];
        })->values();
    }

    /** @return array<int, array{date:string, value:float}> */
    private function pointsFrom(
        Collection $rows,
        string $dateField,
        string $valueField,
        float $ownershipFraction = 1.0,
    ): array {
        return $rows
            ->groupBy(fn ($row) => $row->{$dateField}->toDateString())
            ->map(fn (Collection $dayRows, string $date): array => [
                'date' => $date,
                'value' => round(
                    (float) ($dayRows->last()->{$valueField} ?? $dayRows->last()->value) * $ownershipFraction,
                    2,
                ),
            ])
            ->values()
            ->all();
    }

    private function ownershipFraction(Model $parent, int $userId): float
    {
        $ownershipType = $parent->ownership_type ?? 'individual';
        if (in_array($ownershipType, ['individual', 'trust'], true)) {
            return (int) $parent->user_id === $userId ? 1.0 : 0.0;
        }

        $percentage = (float) ($parent->ownership_percentage ?? 50);
        if ($percentage === 100.0) {
            $percentage = 50.0;
        }

        if ((int) $parent->user_id === $userId) {
            return $percentage / 100;
        }

        if ((int) ($parent->joint_owner_id ?? 0) === $userId) {
            return (100 - $percentage) / 100;
        }

        return 0.0;
    }

    private function labelFor(string $category, Model $parent): string
    {
        return match ($category) {
            'investment' => $parent->account_name ?? $parent->provider ?? 'Investment account',
            'savings' => $parent->account_name ?? $parent->institution ?? 'Savings account',
            'property' => $parent->property_name ?? $parent->address_line_1 ?? 'Property',
            'mortgage' => $parent->lender ?? 'Mortgage',
            'liability' => $parent->liability_name ?? 'Liability',
            'pension', 'pension_income' => $parent->scheme_name ?? $parent->provider ?? 'Pension',
            default => 'Balance',
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $series
     * @return array<int, array{date:string, assets:float, liabilities:float, net_worth:float}>
     */
    private function buildTotals(Collection $series): array
    {
        $balanceSeries = $series->whereIn('balance_type', ['asset', 'liability'])->values();
        $dates = $balanceSeries
            ->flatMap(fn (array $item) => array_column($item['points'], 'date'))
            ->unique()
            ->sort()
            ->values();

        return $dates->map(function (string $date) use ($balanceSeries) {
            $assets = 0.0;
            $liabilities = 0.0;

            foreach ($balanceSeries as $item) {
                $point = collect($item['points'])->last(fn (array $candidate) => $candidate['date'] <= $date);
                if ($point === null) {
                    continue;
                }

                if ($item['balance_type'] === 'asset') {
                    $assets += (float) $point['value'];
                } else {
                    $liabilities += (float) $point['value'];
                }
            }

            return [
                'date' => $date,
                'assets' => round($assets, 2),
                'liabilities' => round($liabilities, 2),
                'net_worth' => round($assets - $liabilities, 2),
            ];
        })->all();
    }

    private function yearOnYear(array $totals): ?array
    {
        if ($totals === []) {
            return null;
        }

        $latest = $totals[array_key_last($totals)];
        $cutoff = Carbon::parse($latest['date'])->subYear()->toDateString();
        $comparison = collect($totals)->last(fn (array $point) => $point['date'] <= $cutoff);
        if ($comparison === null) {
            return null;
        }

        $change = round($latest['net_worth'] - $comparison['net_worth'], 2);
        $percentage = $comparison['net_worth'] == 0.0
            ? null
            : round(($change / abs($comparison['net_worth'])) * 100, 2);

        return [
            'as_at' => $latest['date'],
            'compared_with' => $comparison['date'],
            'currency' => 'GBP',
            'net_worth_change' => $change,
            'percentage_change' => $percentage,
        ];
    }
}
