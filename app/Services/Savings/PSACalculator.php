<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Constants\TaxDefaults;
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
        $psaAmount = (float) $this->taxConfig->getPersonalSavingsAllowance($taxBand);

        $accounts = $user->savingsAccounts()->where('is_isa', false)->get();
        $annualInterest = $this->calculateAnnualInterest($accounts);

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

        if ($grossIncome <= TaxDefaults::PERSONAL_ALLOWANCE) {
            return 'basic';
        }

        if ($grossIncome <= TaxDefaults::HIGHER_RATE_THRESHOLD) {
            return 'basic';
        }

        if ($grossIncome <= TaxDefaults::ADDITIONAL_RATE_THRESHOLD) {
            return 'higher';
        }

        return 'additional';
    }
}
