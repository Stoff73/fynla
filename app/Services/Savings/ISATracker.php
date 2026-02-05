<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\Investment\InvestmentAccount;
use App\Models\ISAAllowanceTracking;
use App\Models\SavingsAccount;
use App\Services\TaxConfigService;
use Carbon\Carbon;

class ISATracker
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get current UK tax year (April 6 - April 5)
     */
    public function getCurrentTaxYear(): string
    {
        $now = Carbon::now();
        $taxYearStart = Carbon::create($now->year, 4, 6);

        if ($now->lt($taxYearStart)) {
            // Before April 6, still in previous tax year
            $startYear = $now->year - 1;
            $endYear = $now->year;
        } else {
            // After April 6, in current tax year
            $startYear = $now->year;
            $endYear = $now->year + 1;
        }

        return sprintf('%d/%02d', $startYear, $endYear % 100);
    }

    /**
     * Get ISA allowance status for a user and tax year
     *
     * @return array{cash_isa_used: float, stocks_shares_isa_used: float, lisa_used: float, total_used: float, total_allowance: float, remaining: float, percentage_used: float}
     */
    public function getISAAllowanceStatus(int $userId, string $taxYear): array
    {
        // Get or create tracking record
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

        // Calculate ISA usage from savings_accounts for current tax year
        $cashIsaUsed = (float) SavingsAccount::where('user_id', $userId)
            ->where('is_isa', true)
            ->where('isa_subscription_year', $taxYear)
            ->where('isa_type', 'cash')
            ->sum('isa_subscription_amount');

        $lisaUsed = (float) SavingsAccount::where('user_id', $userId)
            ->where('is_isa', true)
            ->where('isa_subscription_year', $taxYear)
            ->where('isa_type', 'LISA')
            ->sum('isa_subscription_amount');

        // Calculate stocks & shares ISA usage from investment_accounts (cross-module)
        $stocksSharesIsaUsed = (float) InvestmentAccount::where('user_id', $userId)
            ->where('account_type', 'isa')
            ->where('tax_year', $taxYear)
            ->sum('isa_subscription_current_year');

        $totalUsed = $cashIsaUsed + $stocksSharesIsaUsed + $lisaUsed;
        $totalAllowance = (float) $tracking->total_allowance;
        $remaining = max(0, $totalAllowance - $totalUsed);
        $percentageUsed = $totalAllowance > 0
            ? ($totalUsed / $totalAllowance) * 100
            : 0;

        // Update tracking record
        $tracking->update([
            'cash_isa_used' => $cashIsaUsed,
            'stocks_shares_isa_used' => $stocksSharesIsaUsed,
            'lisa_used' => $lisaUsed,
            'total_used' => $totalUsed,
        ]);

        return [
            'cash_isa_used' => round($cashIsaUsed, 2),
            'stocks_shares_isa_used' => round($stocksSharesIsaUsed, 2),
            'lisa_used' => round($lisaUsed, 2),
            'total_used' => round($totalUsed, 2),
            'total_allowance' => round($totalAllowance, 2),
            'remaining' => round($remaining, 2),
            'percentage_used' => round($percentageUsed, 2),
        ];
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

        // Update the specific ISA type
        match ($isaType) {
            'stocks_shares' => $tracking->stocks_shares_isa_used = $amount ?? (float) InvestmentAccount::where('user_id', $userId)
                ->where('account_type', 'isa')
                ->where('tax_year', $taxYear)
                ->sum('isa_subscription_current_year'),
            'cash' => $tracking->cash_isa_used = $amount ?? (float) SavingsAccount::where('user_id', $userId)
                ->where('is_isa', true)
                ->where('isa_subscription_year', $taxYear)
                ->where('isa_type', 'cash')
                ->sum('isa_subscription_amount'),
            'LISA' => $tracking->lisa_used = $amount ?? (float) SavingsAccount::where('user_id', $userId)
                ->where('is_isa', true)
                ->where('isa_subscription_year', $taxYear)
                ->where('isa_type', 'LISA')
                ->sum('isa_subscription_amount'),
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
}
