<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\TaxConfigService;
use App\Traits\ResolvesIncome;

class PSACalculator
{
    use ResolvesIncome;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly IncomeDefinitionsService $incomeDefinitions,
    ) {}

    /**
     * Assess a user's Personal Savings Allowance position
     */
    public function assessPSAPosition(User $user): array
    {
        $taxBand = $this->determineTaxBand($user);
        // The user's own share of their non-ISA interest, joint accounts
        // included, from the one home every surface reads (ITA 2007 s836: each
        // joint owner is taxed on their share). This summed whole balances of
        // the rows the user is primary owner of, so the primary owner of a
        // joint account was told the whole interest breached their allowance
        // and the joint owner had none (csjones 2026-10-08, Alex and Jamie).
        $annualInterest = $this->incomeDefinitions->estimatedAnnualInterest($user);

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
        // A £0 allowance (additional rate) is used up only by interest actually
        // earned: with none, there is nothing to breach or approach.
        $utilisationPercent = match (true) {
            $psaAmount > 0 => min(100, ($annualInterest / $psaAmount) * 100),
            $annualInterest > 0 => 100,
            default => 0,
        };

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
