<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\User;
use App\Services\TaxConfigService;
use App\Traits\ResolvesIncome;
use Illuminate\Support\Collection;

class PSACalculator
{
    use ResolvesIncome;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Assess a user's Personal Savings Allowance position
     */
    public function assessPSAPosition(User $user): array
    {
        $taxBand = $this->determineTaxBand($user);
        $accounts = $user->savingsAccounts()->where('is_isa', false)->get();
        $annualInterest = $this->calculateAnnualInterest($accounts);

        // Non-taxpayers pay no tax on savings interest — PSA is effectively unlimited.
        // We use PHP_INT_MAX as a sentinel; downstream code should check tax_band first.
        if ($taxBand === 'non_taxpayer') {
            return [
                'tax_band' => $taxBand,
                'psa_amount' => PHP_INT_MAX,
                'annual_interest' => round($annualInterest, 2),
                'breach_amount' => 0.0,
                'headroom' => PHP_INT_MAX,
                'utilisation_percent' => 0.0,
                'is_breached' => false,
                'is_approaching' => false,
            ];
        }

        $psaAmount = (float) $this->taxConfig->getPersonalSavingsAllowance($taxBand);

        $breachAmount = max(0, $annualInterest - $psaAmount);
        $headroom = max(0, $psaAmount - $annualInterest);
        $utilisationPercent = $psaAmount > 0 ? min(100, ($annualInterest / $psaAmount) * 100) : 100;

        return [
            'tax_band' => $taxBand,
            'psa_amount' => $psaAmount,
            'annual_interest' => round($annualInterest, 2),
            'breach_amount' => round($breachAmount, 2),
            'headroom' => round($headroom, 2),
            'utilisation_percent' => round($utilisationPercent, 1),
            'is_breached' => $breachAmount > 0,
            'is_approaching' => $utilisationPercent >= 75 && $breachAmount <= 0,
        ];
    }

    /**
     * Calculate total annual interest from non-ISA savings accounts
     */
    public function calculateAnnualInterest(Collection $accounts): float
    {
        return $accounts->sum(function ($account) {
            $balance = (float) ($account->current_balance ?? 0);
            $rate = (float) ($account->interest_rate ?? 0);

            return $balance * ($rate / 100); // rate stored as percentage
        });
    }

    /**
     * Determine user's tax band from their income.
     * Does NOT recalculate tax — derives band from stored income fields.
     */
    private function determineTaxBand(User $user): string
    {
        $grossIncome = $this->resolveGrossAnnualIncome($user);

        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) ($incomeTax['personal_allowance'] ?? 12570);
        $basicRateLimit = $personalAllowance + (float) ($incomeTax['bands'][0]['max'] ?? 37700);
        $additionalThreshold = (float) ($incomeTax['additional_rate_threshold'] ?? 125140);

        if ($grossIncome <= $personalAllowance) {
            return 'non_taxpayer';
        }

        if ($grossIncome <= $basicRateLimit) {
            return 'basic';
        }

        if ($grossIncome <= $additionalThreshold) {
            return 'higher';
        }

        return 'additional';
    }
}
