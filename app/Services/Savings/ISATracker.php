<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\Investment\InvestmentAccount;
use App\Models\ISAAllowanceTracking;
use App\Models\ISAContribution;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\SavingsStore;
use App\Services\TaxConfigService;
use Carbon\Carbon;

class ISATracker
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get the tax year the app is configured to treat as active.
     * This reads from the TaxConfiguration DB record marked is_active
     * and may differ from the calendar tax year if an admin has switched
     * the year via the admin panel.
     */
    public function getCurrentTaxYear(): string
    {
        return $this->taxConfig->getTaxYear();
    }

    /**
     * Get the tax year based on today's calendar date (April 6 - April 5).
     * Used to decide whether "ongoing contribution" estimates apply to the
     * requested tax year, or whether they'd be misattributed across years.
     */
    public function getCalendarTaxYear(): string
    {
        $start = $this->getTaxYearStartDate();
        $startYear = $start->year;

        return $startYear.'/'.substr((string) ($startYear + 1), -2);
    }

    /**
     * Get ISA allowance status for a user and tax year
     *
     * @return array{cash_isa_used: float, stocks_shares_isa_used: float, lisa_used: float, total_used: float, total_allowance: float, remaining: float, percentage_used: float}
     */
    public function getISAAllowanceStatus(int $userId, string $taxYear): array
    {
        $taxYear = $this->normaliseTaxYear($taxYear);
        $user = User::with('spouse')->find($userId);
        $ownerStatus = $user
            ? $this->buildOwnerStatus($user, $taxYear, 'self')
            : $this->emptyOwnerStatus(null, 'self');

        $tracking = ISAAllowanceTracking::firstOrCreate(
            [
                'user_id' => $userId,
                'tax_year' => $taxYear,
            ],
            [
                'cash_isa_used' => 0.00,
                'stocks_shares_isa_used' => 0.00,
                'lisa_used' => 0.00,
                'total_used' => 0.00,
                'total_allowance' => $this->getTotalAllowance($taxYear),
            ]
        );

        $cashIsaUsed = $ownerStatus['cash_isa_used'];
        $stocksSharesIsaUsed = $ownerStatus['stocks_shares_isa_used'];
        $lisaUsed = $ownerStatus['lisa_used'];
        $totalUsed = $ownerStatus['total_used'];
        $totalAllowance = (float) $tracking->total_allowance;
        $remaining = max(0, $totalAllowance - $totalUsed);
        $percentageUsed = $totalAllowance > 0
            ? ($totalUsed / $totalAllowance) * 100
            : 0;

        // Update tracking record only if values changed
        $tracking->fill([
            'cash_isa_used' => $cashIsaUsed,
            'stocks_shares_isa_used' => $stocksSharesIsaUsed,
            'lisa_used' => $lisaUsed,
            'total_used' => $totalUsed,
        ]);

        if ($tracking->isDirty()) {
            $tracking->save();
        }

        $isCalendarYear = $taxYear === $this->getCalendarTaxYear();
        if ($isCalendarYear) {
            $projectedCashIsa = $this->calculateProjectedSubscriptions($userId, $taxYear, 'cash');
            $projectedStocksSharesIsa = $this->estimateStocksSharesIsaUsage($userId, true);
        } else {
            $projectedCashIsa = 0.0;
            $projectedStocksSharesIsa = 0.0;
        }
        $projectedTotal = $projectedCashIsa + round(max($stocksSharesIsaUsed, $projectedStocksSharesIsa), 2) + round($lisaUsed, 2);

        $owners = [$ownerStatus];
        if ($user && $user->spouse && $user->hasAcceptedSpousePermission()) {
            $owners[] = $this->buildOwnerStatus($user->spouse, $taxYear, 'spouse');
        }

        return array_merge($ownerStatus, [
            'tax_year' => $taxYear,
            'current_tax_year' => $this->getCurrentTaxYear(),
            'prior_tax_year' => $this->previousTaxYear($taxYear),
            'available_tax_years' => $user ? $this->availableTaxYears($user, $taxYear) : [$taxYear],
            'cash_isa_used' => round($cashIsaUsed, 2),
            'stocks_shares_isa_used' => round($stocksSharesIsaUsed, 2),
            'lisa_used' => round($lisaUsed, 2),
            'total_used' => round($totalUsed, 2),
            'total_allowance' => round($totalAllowance, 2),
            'remaining' => round($remaining, 2),
            'percentage_used' => round($percentageUsed, 2),
            'projected_usage' => [
                'cash_isa_projected' => round($projectedCashIsa, 2),
                'total_projected' => round($projectedTotal, 2),
                'projected_remaining' => round(max(0, $totalAllowance - $projectedTotal), 2),
            ],
            'owners' => $owners,
        ]);
    }

    private function buildOwnerStatus(User $owner, string $taxYear, string $relationship): array
    {
        $breakdown = collect();

        $savingsAccounts = SavingsAccount::query()
            ->where('user_id', $owner->id)
            ->where('is_isa', true)
            ->where('isa_subscription_year', $taxYear)
            ->get();

        foreach ($savingsAccounts as $account) {
            $isaType = in_array(strtolower((string) $account->isa_type), ['lisa', 'lifetime_isa'], true)
                || in_array(strtolower((string) $account->account_type), ['lisa', 'lifetime_isa'], true)
                ? 'lifetime_isa'
                : 'cash_isa';
            $breakdown->push($this->accountBreakdown(
                $owner,
                $relationship,
                $account,
                $taxYear,
                $isaType,
                (float) ($account->isa_subscription_amount ?? 0),
                'legacy_annual_summary',
            ));
        }

        $investmentAccounts = InvestmentAccount::query()
            ->where('user_id', $owner->id)
            ->where('account_type', 'isa')
            ->where(function ($query) use ($taxYear) {
                $query->where('tax_year', $taxYear);
                if ($taxYear === $this->getCalendarTaxYear()) {
                    $query->orWhereNull('tax_year');
                }
            })
            ->get();

        foreach ($investmentAccounts as $account) {
            $legacyAmount = (float) ($account->isa_subscription_current_year ?? $account->contributions_ytd ?? 0);
            $breakdown->push($this->accountBreakdown(
                $owner,
                $relationship,
                $account,
                $taxYear,
                'stocks_and_shares_isa',
                $legacyAmount,
                'legacy_current_year_summary',
            ));
        }

        $breakdown = $breakdown
            ->filter(fn (array $row) => $row['contributed'] > 0 || $row['contributions'] !== [])
            ->values();
        $cashUsed = (float) $breakdown->where('isa_type', 'cash_isa')->sum('contributed');
        $sharesUsed = (float) $breakdown->where('isa_type', 'stocks_and_shares_isa')->sum('contributed');
        $lisaUsed = (float) $breakdown->where('isa_type', 'lifetime_isa')->sum('contributed');

        return [
            'owner' => $this->ownerPresentation($owner, $relationship),
            'cash_isa_used' => round($cashUsed, 2),
            'stocks_shares_isa_used' => round($sharesUsed, 2),
            'lisa_used' => round($lisaUsed, 2),
            'total_used' => round($cashUsed + $sharesUsed + $lisaUsed, 2),
            'account_breakdown' => $breakdown->all(),
        ];
    }

    private function accountBreakdown(
        User $owner,
        string $relationship,
        SavingsAccount|InvestmentAccount $account,
        string $taxYear,
        string $isaType,
        float $legacyAmount,
        string $legacyProvenance,
    ): array {
        $entries = ISAContribution::query()
            ->where('user_id', $owner->id)
            ->where('account_type', $account::class)
            ->where('account_id', $account->id)
            ->where('tax_year', $taxYear)
            ->orderBy('contribution_date')
            ->orderBy('id')
            ->get();
        $subscriptions = $entries->where('entry_type', 'subscription');
        $summary = $entries->where('entry_type', 'annual_summary')->last();

        if ($subscriptions->isNotEmpty()) {
            $amount = (float) $subscriptions->sum('amount');
            $provenance = 'recorded_ledger';
            $contributions = $subscriptions->map(fn (ISAContribution $entry) => $this->contributionPresentation($entry))->values()->all();
        } elseif ($summary) {
            $amount = (float) $summary->amount;
            $provenance = $summary->provenance;
            $contributions = [$this->contributionPresentation($summary)];
        } else {
            $amount = $legacyAmount;
            $provenance = $legacyProvenance;
            $contributions = $legacyAmount > 0 ? [[
                'id' => null,
                'date' => null,
                'amount' => round($legacyAmount, 2),
                'entry_type' => 'annual_summary',
                'source' => 'legacy_account_record',
                'provenance' => $legacyProvenance,
            ]] : [];
        }

        return [
            'account_id' => $account->id,
            'account_type' => $account::class,
            'account_name' => $account instanceof SavingsAccount
                ? ($account->account_name ?: $account->institution ?: 'ISA')
                : ($account->account_name ?: $account->provider ?: 'Stocks & Shares ISA'),
            'isa_type' => $isaType,
            'owner' => $this->ownerPresentation($owner, $relationship),
            'contributed' => round($amount, 2),
            'provenance' => $provenance,
            'contributions' => $contributions,
        ];
    }

    private function contributionPresentation(ISAContribution $entry): array
    {
        return [
            'id' => $entry->id,
            'date' => $entry->contribution_date?->toDateString(),
            'amount' => round((float) $entry->amount, 2),
            'entry_type' => $entry->entry_type,
            'source' => $entry->source,
            'provenance' => $entry->provenance,
        ];
    }

    private function ownerPresentation(?User $owner, string $relationship): array
    {
        $name = $owner ? trim(($owner->first_name ?? '').' '.($owner->surname ?? '')) : null;

        return [
            'id' => $owner?->id,
            'relationship' => $relationship,
            'label' => $relationship === 'self' ? 'You' : ($name ?: 'Your partner'),
            'name' => $name ?: null,
        ];
    }

    private function emptyOwnerStatus(?User $owner, string $relationship): array
    {
        return [
            'owner' => $this->ownerPresentation($owner, $relationship),
            'cash_isa_used' => 0.0,
            'stocks_shares_isa_used' => 0.0,
            'lisa_used' => 0.0,
            'total_used' => 0.0,
            'account_breakdown' => [],
        ];
    }

    private function availableTaxYears(User $user, string $requested): array
    {
        $years = collect([$requested, $this->getCurrentTaxYear(), $this->previousTaxYear($requested)])
            ->merge(ISAContribution::where('user_id', $user->id)->pluck('tax_year'))
            ->merge(SavingsAccount::where('user_id', $user->id)->whereNotNull('isa_subscription_year')->pluck('isa_subscription_year'))
            ->merge(InvestmentAccount::where('user_id', $user->id)->whereNotNull('tax_year')->pluck('tax_year'))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $years;
    }

    private function previousTaxYear(string $taxYear): string
    {
        $startYear = (int) substr($taxYear, 0, 4) - 1;

        return $startYear.'/'.substr((string) ($startYear + 1), -2);
    }

    private function normaliseTaxYear(string $taxYear): string
    {
        return preg_replace('/^(\d{4})-(\d{2})$/', '$1/$2', $taxYear) ?? $taxYear;
    }

    /**
     * Update ISA usage for a specific type
     * Note: For stocks_shares, this now auto-calculates from investment_accounts
     */
    public function updateISAUsage(int $userId, string $isaType, ?float $amount = null, ?string $taxYear = null): void
    {
        $taxYear = $taxYear ?? $this->getCurrentTaxYear();

        $tracking = ISAAllowanceTracking::firstOrCreate(
            [
                'user_id' => $userId,
                'tax_year' => $taxYear,
            ],
            [
                'cash_isa_used' => 0.00,
                'stocks_shares_isa_used' => 0.00,
                'lisa_used' => 0.00,
                'total_used' => 0.00,
                'total_allowance' => $this->getTotalAllowance($taxYear),
            ]
        );

        $isaUser = User::find($userId);

        // Update the specific ISA type
        match ($isaType) {
            'stocks_shares' => $tracking->stocks_shares_isa_used = $amount ?? (float) InvestmentAccount::where('user_id', $userId)
                ->where('account_type', 'isa')
                ->where('tax_year', $taxYear)
                ->sum('isa_subscription_current_year'),
            'cash' => $tracking->cash_isa_used = $amount ?? ($isaUser
                ? (float) app(SavingsStore::class)->forUser($isaUser)
                    ->where('user_id', $userId)
                    ->where('is_isa', true)
                    ->where('isa_subscription_year', $taxYear)
                    ->where('isa_type', 'cash')
                    ->sum('isa_subscription_amount')
                : 0.0),
            'LISA' => $tracking->lisa_used = $amount ?? ($isaUser
                ? (float) app(SavingsStore::class)->forUser($isaUser)
                    ->where('user_id', $userId)
                    ->where('is_isa', true)
                    ->where('isa_subscription_year', $taxYear)
                    ->where('isa_type', 'LISA')
                    ->sum('isa_subscription_amount')
                : 0.0),
            default => null,
        };

        // Recalculate total
        $tracking->total_used = $tracking->cash_isa_used + $tracking->stocks_shares_isa_used + $tracking->lisa_used;
        $tracking->save();
    }

    /**
     * Get total ISA allowance for a tax year
     */
    public function getTotalAllowance(string $taxYear): float
    {
        $isaConfig = $this->taxConfig->getISAAllowances();

        return (float) $isaConfig['annual_allowance'];
    }

    /**
     * Get LISA specific allowance
     */
    public function getLISAAllowance(): float
    {
        $isaConfig = $this->taxConfig->getISAAllowances();

        return (float) $isaConfig['lifetime_isa']['annual_allowance'];
    }

    /**
     * Calculate projected ISA subscription for a single account
     * based on regular contributions and planned lump sums.
     */
    public function calculateProjectedSubscription(SavingsAccount $account): float
    {
        if (! $account->is_isa || ! $account->regular_contribution_amount) {
            return 0.0;
        }

        $taxYearStart = $this->getTaxYearStartDate();
        $taxYearEnd = $taxYearStart->copy()->addYear()->subDay();
        $now = Carbon::now();

        $monthsElapsed = (int) $taxYearStart->diffInMonths($now);
        $monthsRemaining = (int) $now->diffInMonths($taxYearEnd);

        $frequencyMultiplier = match ($account->contribution_frequency) {
            'monthly' => 1,
            'quarterly' => 1 / 3,
            'annually' => 1 / 12,
            default => 1,
        };

        $contributionsPerMonth = (float) $account->regular_contribution_amount * $frequencyMultiplier;
        $totalProjected = $contributionsPerMonth * ($monthsElapsed + $monthsRemaining);

        // Add planned lump sum if within tax year
        if ($account->planned_lump_sum_amount
            && $account->planned_lump_sum_date
            && $account->planned_lump_sum_date->between($taxYearStart, $taxYearEnd)
        ) {
            $totalProjected += (float) $account->planned_lump_sum_amount;
        }

        return round($totalProjected, 2);
    }

    /**
     * Calculate total projected ISA subscriptions for a user and ISA type.
     */
    private function calculateProjectedSubscriptions(int $userId, string $taxYear, string $isaType): float
    {
        $isaUser = User::find($userId);
        $accounts = $isaUser
            ? app(SavingsStore::class)->forUser($isaUser)
                ->where('user_id', $userId)
                ->where('is_isa', true)
                ->filter(fn ($a) => $a->isa_type === $isaType
                    || ($isaType === 'cash' && $a->account_type === 'cash_isa')
                    || (($isaType === 'LISA' || $isaType === 'lisa') && $a->account_type === 'lisa'))
            : collect();

        $total = 0.0;
        foreach ($accounts as $account) {
            $projected = $this->calculateProjectedSubscription($account);
            $total += $projected > 0 ? $projected : (float) ($account->isa_subscription_amount ?? 0);
        }

        return $total;
    }

    /**
     * Estimate S&S ISA usage from monthly contributions on investment accounts.
     */
    private function estimateStocksSharesIsaUsage(int $userId, bool $fullYear = false): float
    {
        $accounts = InvestmentAccount::where('user_id', $userId)
            ->where('account_type', 'isa')
            ->where('monthly_contribution_amount', '>', 0)
            ->get();

        if ($accounts->isEmpty()) {
            return 0.0;
        }

        $taxYearStart = $this->getTaxYearStartDate();
        $now = Carbon::now();
        $monthsElapsed = max(1, (int) $taxYearStart->diffInMonths($now));

        $total = 0.0;
        foreach ($accounts as $account) {
            $monthly = (float) $account->monthly_contribution_amount;
            if ($fullYear) {
                $total += $monthly * 12;
            } else {
                $total += $monthly * $monthsElapsed;
            }
        }

        return round($total, 2);
    }

    /**
     * Get the start date of the current tax year.
     */
    private function getTaxYearStartDate(): Carbon
    {
        $now = Carbon::now();
        $taxYearStart = Carbon::create($now->year, 4, 6);

        if ($now->lt($taxYearStart)) {
            return Carbon::create($now->year - 1, 4, 6);
        }

        return $taxYearStart;
    }
}
