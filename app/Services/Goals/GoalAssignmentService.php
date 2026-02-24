<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\Goal;
use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * Service for determining goal module assignment based on goal characteristics.
 */
class GoalAssignmentService
{
    private const SHORT_TERM_YEARS = 3;

    private const INVESTMENT_MIN_AMOUNT = 5000;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Determine the appropriate module for a goal.
     */
    public function determineModule(array $goalData): string
    {
        $goalType = $goalData['goal_type'] ?? 'custom';
        $targetAmount = (float) ($goalData['target_amount'] ?? 0);
        $targetDate = isset($goalData['target_date']) ? Carbon::parse($goalData['target_date']) : null;
        $timeHorizonYears = $targetDate ? now()->diffInYears($targetDate, false) : 0;

        // 1. Check goal type first (explicit assignments)
        $moduleByType = $this->getModuleByGoalType($goalType);
        if ($moduleByType) {
            return $moduleByType;
        }

        // 2. Check time horizon and amount
        if ($timeHorizonYears <= self::SHORT_TERM_YEARS) {
            return 'savings';
        }

        if ($timeHorizonYears > self::SHORT_TERM_YEARS && $targetAmount >= self::INVESTMENT_MIN_AMOUNT) {
            return 'investment';
        }

        // Default to savings for smaller or unspecified amounts
        return 'savings';
    }

    /**
     * Get module assignment based on goal type.
     */
    private function getModuleByGoalType(string $goalType): ?string
    {
        return match ($goalType) {
            'emergency_fund' => 'savings',
            'property_purchase', 'home_deposit' => 'property',
            'retirement' => 'retirement',
            'wealth_accumulation' => 'investment',
            'education' => null, // Depends on time horizon
            'debt_repayment' => 'savings',
            'wedding', 'holiday', 'car_purchase' => null, // Typically short-term
            'custom' => null, // Depends on other factors
            default => null,
        };
    }

    /**
     * Calculate property purchase costs including SDLT, legal fees, etc.
     */
    public function calculatePropertyCosts(array $goalData): array
    {
        $propertyPrice = (float) ($goalData['estimated_property_price'] ?? 0);
        $depositPercentage = (float) ($goalData['deposit_percentage'] ?? 10);
        $isFirstTimeBuyer = $goalData['is_first_time_buyer'] ?? false;

        if ($propertyPrice <= 0) {
            return [
                'deposit' => 0,
                'stamp_duty' => 0,
                'legal_fees' => 0,
                'survey_costs' => 0,
                'moving_costs' => 0,
                'total_upfront' => 0,
            ];
        }

        $deposit = $propertyPrice * ($depositPercentage / 100);
        $stampDuty = $this->calculateSDLT($propertyPrice, $isFirstTimeBuyer);
        $legalFees = $this->estimateLegalFees($propertyPrice);
        $surveyCosts = $this->estimateSurveyCosts($propertyPrice);
        $movingCosts = 1500; // Average UK moving costs

        $totalUpfront = $deposit + $stampDuty + $legalFees + $surveyCosts + $movingCosts;

        return [
            'property_price' => round($propertyPrice, 2),
            'deposit' => round($deposit, 2),
            'deposit_percentage' => $depositPercentage,
            'stamp_duty' => round($stampDuty, 2),
            'legal_fees' => round($legalFees, 2),
            'survey_costs' => round($surveyCosts, 2),
            'moving_costs' => $movingCosts,
            'total_upfront' => round($totalUpfront, 2),
            'mortgage_required' => round($propertyPrice - $deposit, 2),
            'is_first_time_buyer' => $isFirstTimeBuyer,
        ];
    }

    /**
     * Calculate Stamp Duty Land Tax.
     */
    private function calculateSDLT(float $propertyPrice, bool $isFirstTimeBuyer): float
    {
        $sdltConfig = $this->taxConfig->getStampDuty();
        $bands = $sdltConfig['residential']['standard'] ?? [];

        // First-time buyer relief (up to £625,000)
        if ($isFirstTimeBuyer && $propertyPrice <= 625000) {
            $bands = $sdltConfig['residential']['first_time_buyer'] ?? $bands;
        }

        $stampDuty = 0;
        $previousThreshold = 0;

        foreach ($bands as $band) {
            $threshold = $band['threshold'] ?? 0;
            $rate = ($band['rate'] ?? 0) / 100;

            if ($propertyPrice > $previousThreshold) {
                $taxableAmount = min($propertyPrice, $threshold) - $previousThreshold;
                if ($taxableAmount > 0) {
                    $stampDuty += $taxableAmount * $rate;
                }
            }

            $previousThreshold = $threshold;

            if ($propertyPrice <= $threshold) {
                break;
            }
        }

        // Handle amounts above the last threshold
        if ($propertyPrice > $previousThreshold && ! empty($bands)) {
            $lastBand = end($bands);
            $rate = ($lastBand['rate'] ?? 0) / 100;
            $stampDuty += ($propertyPrice - $previousThreshold) * $rate;
        }

        return max(0, $stampDuty);
    }

    /**
     * Estimate legal/conveyancing fees based on property price.
     */
    private function estimateLegalFees(float $propertyPrice): float
    {
        // UK average legal fees: £1,000-£1,500 + disbursements
        $baseFee = 1200;
        $disbursements = 400; // Searches, Land Registry, etc.

        // Higher value properties may have slightly higher fees
        if ($propertyPrice > 500000) {
            $baseFee = 1500;
        }
        if ($propertyPrice > 1000000) {
            $baseFee = 2000;
        }

        return $baseFee + $disbursements;
    }

    /**
     * Estimate survey costs based on property price.
     */
    private function estimateSurveyCosts(float $propertyPrice): float
    {
        // HomeReport in Scotland / Survey in England & Wales
        if ($propertyPrice < 250000) {
            return 400;
        }
        if ($propertyPrice < 500000) {
            return 600;
        }
        if ($propertyPrice < 1000000) {
            return 900;
        }

        return 1200;
    }

    /**
     * Get recommended asset allocation based on time horizon (glide path).
     */
    public function getRecommendedAllocation(array $goalData): array
    {
        $targetDate = isset($goalData['target_date']) ? Carbon::parse($goalData['target_date']) : null;
        $yearsToGoal = $targetDate ? max(0, now()->diffInYears($targetDate, false)) : 0;

        // Glide path: reduce equity exposure as goal approaches
        if ($yearsToGoal > 15) {
            return [
                'equities' => 80,
                'bonds' => 15,
                'cash' => 5,
                'risk_level' => 'aggressive',
                'rationale' => 'Long time horizon allows for higher equity exposure',
            ];
        }
        if ($yearsToGoal > 10) {
            return [
                'equities' => 70,
                'bonds' => 25,
                'cash' => 5,
                'risk_level' => 'growth',
                'rationale' => 'Balanced growth with moderate equity exposure',
            ];
        }
        if ($yearsToGoal > 5) {
            return [
                'equities' => 50,
                'bonds' => 40,
                'cash' => 10,
                'risk_level' => 'balanced',
                'rationale' => 'Balanced approach as goal approaches',
            ];
        }
        if ($yearsToGoal > 3) {
            return [
                'equities' => 30,
                'bonds' => 50,
                'cash' => 20,
                'risk_level' => 'cautious',
                'rationale' => 'Reduced risk as goal nears',
            ];
        }
        if ($yearsToGoal > 1) {
            return [
                'equities' => 15,
                'bonds' => 45,
                'cash' => 40,
                'risk_level' => 'defensive',
                'rationale' => 'Capital preservation priority',
            ];
        }

        return [
            'equities' => 0,
            'bonds' => 20,
            'cash' => 80,
            'risk_level' => 'cash',
            'rationale' => 'Goal imminent - capital preservation essential',
        ];
    }

    /**
     * Get available goal types with their default module assignments.
     */
    public function getGoalTypes(): array
    {
        return [
            ['type' => 'emergency_fund', 'label' => 'Emergency Fund', 'default_module' => 'savings', 'icon' => 'shield'],
            ['type' => 'property_purchase', 'label' => 'Property Purchase', 'default_module' => 'property', 'icon' => 'home'],
            ['type' => 'home_deposit', 'label' => 'Home Deposit', 'default_module' => 'property', 'icon' => 'key'],
            ['type' => 'education', 'label' => 'Education', 'default_module' => null, 'icon' => 'academic-cap'],
            ['type' => 'retirement', 'label' => 'Retirement', 'default_module' => 'retirement', 'icon' => 'sun'],
            ['type' => 'wealth_accumulation', 'label' => 'Wealth Building', 'default_module' => 'investment', 'icon' => 'trending-up'],
            ['type' => 'wedding', 'label' => 'Wedding', 'default_module' => null, 'icon' => 'heart'],
            ['type' => 'holiday', 'label' => 'Holiday', 'default_module' => null, 'icon' => 'globe'],
            ['type' => 'car_purchase', 'label' => 'Car Purchase', 'default_module' => null, 'icon' => 'truck'],
            ['type' => 'debt_repayment', 'label' => 'Debt Repayment', 'default_module' => 'savings', 'icon' => 'credit-card'],
            ['type' => 'custom', 'label' => 'Custom Goal', 'default_module' => null, 'icon' => 'star'],
        ];
    }
}
