<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Property;
use App\Models\User;
use App\Services\TaxConfigService;

class IncomeDefinitionsService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    public function calculate(int $userId): array
    {
        $user = User::with(['dcPensions', 'dbPensions', 'statePension'])->findOrFail($userId);
        $pensionContributions = $this->getPensionContributions($user);

        // 1. Total Income — from all sources including computed rental and pension income
        $components = $this->getIncomeComponents($user);
        $totalIncome = array_sum($components);

        // 2. Net Income
        $pensionRelief = $pensionContributions['employee'];
        $giftAidGross = $this->calculateGiftAidGrossUp($user);
        $netIncome = $totalIncome - $pensionRelief - $giftAidGross;

        // 3. Adjusted Net Income
        $bpa = $user->is_registered_blind ? $this->taxConfig->getBlindPersonsAllowance() : 0.0;
        $adjustedNetIncome = $netIncome - $bpa;

        // 4. Threshold Income
        $thresholdIncome = $adjustedNetIncome - $pensionContributions['employee'];

        // 5. Adjusted Income
        $adjustedIncome = $thresholdIncome + $pensionContributions['employer'];

        // Ensure no negative values
        $totalIncome = max(0.0, $totalIncome);
        $netIncome = max(0.0, $netIncome);
        $adjustedNetIncome = max(0.0, $adjustedNetIncome);
        $thresholdIncome = max(0.0, $thresholdIncome);
        $adjustedIncome = max(0.0, $adjustedIncome);

        $adjustedAllowances = $this->calculateAdjustedAllowances($adjustedNetIncome, $thresholdIncome, $adjustedIncome);

        return [
            'total_income' => round($totalIncome, 2),
            'net_income' => round($netIncome, 2),
            'adjusted_net_income' => round($adjustedNetIncome, 2),
            'threshold_income' => round($thresholdIncome, 2),
            'adjusted_income' => round($adjustedIncome, 2),
            'components' => $components,
            'deductions' => [
                'pension_relief' => round($pensionRelief, 2),
                'gift_aid_gross' => round($giftAidGross, 2),
                'blind_persons_allowance' => round($bpa, 2),
                'employee_pension_contributions' => round($pensionContributions['employee'], 2),
                'employer_pension_contributions' => round($pensionContributions['employer'], 2),
            ],
            'adjusted_allowances' => $adjustedAllowances,
        ];
    }

    private function getIncomeComponents(User $user): array
    {
        return [
            'employment' => round((float) ($user->annual_employment_income ?? 0), 2),
            'self_employment' => round((float) ($user->annual_self_employment_income ?? 0), 2),
            'rental' => round($this->calculateRentalIncome($user), 2),
            'dividend' => round((float) ($user->annual_dividend_income ?? 0), 2),
            'interest' => round((float) ($user->annual_interest_income ?? 0), 2),
            'other' => round((float) ($user->annual_other_income ?? 0), 2),
            'trust' => round((float) ($user->annual_trust_income ?? 0), 2),
            'pension_income' => round($this->calculatePensionIncome($user), 2),
        ];
    }

    /**
     * Calculate annual rental income from buy-to-let properties.
     * Uses monthly_rental_income from Property model, applying ownership share.
     */
    private function calculateRentalIncome(User $user): float
    {
        $properties = Property::where('property_type', 'buy_to_let')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->get();

        $total = 0.0;
        foreach ($properties as $property) {
            $monthlyRental = (float) ($property->monthly_rental_income ?? 0);
            $annualRental = $monthlyRental * 12;

            // Apply ownership share for joint properties
            if ($property->joint_owner_id && $property->ownership_percentage) {
                $share = $property->user_id === $user->id
                    ? (float) $property->ownership_percentage / 100
                    : (100 - (float) $property->ownership_percentage) / 100;
                $annualRental *= $share;
            }

            $total += $annualRental;
        }

        return $total;
    }

    /**
     * Calculate annual pension income from DB pensions in payment and state pension.
     */
    private function calculatePensionIncome(User $user): float
    {
        $income = 0.0;

        // DB pensions in payment
        foreach ($user->dbPensions as $dbPension) {
            if ($dbPension->accrued_annual_pension > 0) {
                $income += (float) $dbPension->accrued_annual_pension;
            }
        }

        // State pension if receiving
        if ($user->statePension && $user->statePension->already_receiving) {
            $income += (float) ($user->statePension->state_pension_forecast_annual ?? 0);
        }

        return $income;
    }

    private function getPensionContributions(User $user): array
    {
        $employee = 0.0;
        $employer = 0.0;

        foreach ($user->dcPensions as $pension) {
            $salary = (float) ($pension->annual_salary ?? 0);
            $employee += $salary * ((float) ($pension->employee_contribution_percent ?? 0) / 100);
            $employer += $salary * ((float) ($pension->employer_contribution_percent ?? 0) / 100);
        }

        return [
            'employee' => round($employee, 2),
            'employer' => round($employer, 2),
        ];
    }

    private function calculateGiftAidGrossUp(User $user): float
    {
        if (! $user->is_gift_aid || ! $user->annual_charitable_donations) {
            return 0.0;
        }

        return round((float) $user->annual_charitable_donations * 1.25, 2);
    }

    private function calculateAdjustedAllowances(float $adjustedNetIncome, float $thresholdIncome, float $adjustedIncome): array
    {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        $fullPA = (float) ($incomeTax['personal_allowance'] ?? 12570);
        $paTaperThreshold = (float) ($incomeTax['personal_allowance_taper_threshold'] ?? 100000);

        $fullAA = (float) ($pensionConfig['annual_allowance'] ?? 60000);
        $taper = $pensionConfig['tapered_annual_allowance'] ?? [];
        $aaThresholdIncome = (float) ($taper['threshold_income'] ?? 200000);
        $aaAdjustedIncome = (float) ($taper['adjusted_income_threshold'] ?? $taper['adjusted_income'] ?? 260000);
        $aaMinimum = (float) ($taper['minimum_allowance'] ?? 10000);
        $aaTaperRate = (float) ($taper['taper_rate'] ?? 0.5);

        // Personal Allowance taper
        $adjustedPA = $fullPA;
        $paTapered = false;
        if ($adjustedNetIncome > $paTaperThreshold) {
            $excess = $adjustedNetIncome - $paTaperThreshold;
            $reduction = floor($excess / 2);
            $adjustedPA = max(0.0, $fullPA - $reduction);
            $paTapered = $adjustedPA < $fullPA;
        }

        // Pension AA taper — both conditions must be met
        $adjustedAA = $fullAA;
        $aaTapered = false;
        if ($thresholdIncome > $aaThresholdIncome && $adjustedIncome > $aaAdjustedIncome) {
            $excess = $adjustedIncome - $aaAdjustedIncome;
            $reduction = floor($excess * $aaTaperRate);
            $adjustedAA = max($aaMinimum, $fullAA - $reduction);
            $aaTapered = $adjustedAA < $fullAA;
        }

        return [
            'personal_allowance' => round($adjustedPA, 2),
            'personal_allowance_full' => round($fullPA, 2),
            'personal_allowance_tapered' => $paTapered,
            'pension_annual_allowance' => round($adjustedAA, 2),
            'pension_annual_allowance_full' => round($fullAA, 2),
            'pension_aa_tapered' => $aaTapered,
        ];
    }
}
