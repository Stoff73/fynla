<?php

declare(strict_types=1);

namespace App\Services\NetWorth;

use App\Http\Resources\Estate\LiabilityResource;
use App\Models\Mortgage;
use App\Models\User;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\Stores\LiabilityStore;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Support\Collection;

/**
 * The debts page's list: every debt the user is party to and every mortgage on
 * a property they own, at the viewer's share, with the totals per type and in
 * all (CSJ 2026-10-01: the page added these up itself). One home for the Estate
 * index and the net-worth route the Liabilities page reads (T28: the Estate
 * index gives Free users a teaser, and Liabilities is a Free module).
 *
 * A mortgage payment, rate or type never given is sent as none, not £0, 0% or
 * "Repayment mortgage" (T25 class).
 */
final class LiabilitiesList
{
    use CalculatesOwnershipShare;

    public function __construct(
        private readonly LiabilityStore $liabilities,
        private readonly CrossModuleAssetAggregator $assets,
    ) {}

    /** @return array{liabilities: list<array<string, mixed>>, liability_totals: array<string, array{balance: float, monthly_payments: float}>} */
    public function for(User $user): array
    {
        $mortgageRows = $this->assets->getMortgages($user->id)->loadMissing('property')
            ->map(fn (Mortgage $mortgage): array => $this->mortgageRow($mortgage, $user));

        $rows = collect(LiabilityResource::collection($this->liabilities->forUser($user))->resolve())
            ->merge($mortgageRows)
            ->values();

        return ['liabilities' => $rows->all(), 'liability_totals' => $this->totals($rows)];
    }

    /**
     * The share is applied here, not on the client: the page once summed
     * `current_balance` whole, so its Total Balance Owed included the 60% of a
     * tenants-in-common mortgage belonging to an off-platform co-owner (W-0237).
     * `calculateUserMortgageShare` resolves the property itself (W-0228).
     *
     * @return array<string, mixed>
     */
    private function mortgageRow(Mortgage $mortgage, User $user): array
    {
        $property = $mortgage->property;
        $payment = $mortgage->monthly_payment;

        return [
            'id' => 'mortgage_'.$mortgage->id,
            'source' => 'property_module',
            'liability_type' => 'mortgage',
            'liability_name' => 'Mortgage - '.($property->address_line_1 ?? 'Property'),
            'current_balance' => (float) ($mortgage->outstanding_balance ?? 0),
            'user_share' => round($this->calculateUserMortgageShare($mortgage, $user->id), 2),
            'monthly_payment' => $payment === null ? null : (float) $payment,
            'user_monthly_payment_share' => $payment === null ? null : round($this->calculateUserMortgageMonthlyPaymentShare($mortgage, $user->id), 2),
            'interest_rate' => $mortgage->interest_rate === null ? null : (float) $mortgage->interest_rate,
            'notes' => $mortgage->mortgage_type === null ? null : ucfirst(str_replace('_', ' ', (string) $mortgage->mortgage_type)).' mortgage',
            'ownership_type' => $property->ownership_type ?? 'individual',
            'ownership_percentage' => $property->ownership_percentage ?? 100,
        ];
    }

    /** @return array<string, array{balance: float, monthly_payments: float}> */
    private function totals(Collection $rows): array
    {
        $sum = static fn ($group): array => [
            'balance' => round((float) $group->sum(fn ($r): float => (float) ($r['user_share'] ?? 0)), 2),
            'monthly_payments' => round((float) $group->sum(fn ($r): float => (float) ($r['user_monthly_payment_share'] ?? 0)), 2),
        ];

        return ['all' => $sum($rows)] + $rows->groupBy('liability_type')->map($sum)->all();
    }
}
