<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Investment\Holding;
use App\Models\User;
use App\Models\UserAssumption;
use App\Services\Risk\RiskPreferenceService;
use Illuminate\Support\Facades\Log;

/**
 * Assumptions Service
 *
 * Manages user overrides for planning assumptions used in pension and investment projections.
 */
class AssumptionsService
{
    private const DEFAULT_INFLATION_RATE = 2.0;

    private const DEFAULT_COMPOUND_PERIODS = 12;

    private const DEFAULT_RETIREMENT_AGE = 68;

    public function __construct(
        private RiskPreferenceService $riskService
    ) {}

    /**
     * Get all assumptions for a user (both pensions and investments).
     */
    public function getAssumptions(int $userId): array
    {
        $user = User::with(['dcPensions.holdings', 'investmentAccounts.holdings'])
            ->findOrFail($userId);

        return [
            'pensions' => $this->getTypeAssumptions($user, 'pensions'),
            'investments' => $this->getTypeAssumptions($user, 'investments'),
        ];
    }

    /**
     * Get assumptions for a specific type (pensions or investments).
     */
    public function getTypeAssumptions(User $user, string $type): array
    {
        $override = UserAssumption::where('user_id', $user->id)
            ->where('assumption_type', $type)
            ->first();

        $defaults = $this->getDefaults($user, $type);
        $fees = $this->calculateWeightedFees($user, $type);
        $yearsToRetirement = $this->getYearsToRetirement($user);
        $totalValue = $this->getTotalValue($user, $type);

        return [
            'inflation_rate' => $override?->inflation_rate ?? $defaults['inflation_rate'],
            'inflation_rate_default' => $defaults['inflation_rate'],
            'return_rate' => $override?->return_rate ?? $defaults['return_rate'],
            'return_rate_default' => $defaults['return_rate'],
            'risk_level' => $defaults['risk_level'],
            'compound_periods' => $override?->compound_periods ?? $defaults['compound_periods'],
            'compound_periods_default' => $defaults['compound_periods'],
            'fees' => $fees,
            'years_to_retirement' => $yearsToRetirement,
            'total_value' => $totalValue,
            'has_overrides' => $override !== null && (
                $override->inflation_rate !== null ||
                $override->return_rate !== null ||
                $override->compound_periods !== null
            ),
        ];
    }

    /**
     * Update assumptions for a specific type.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAssumptions(int $userId, string $type, array $data): array
    {
        if (! in_array($type, ['pensions', 'investments'], true)) {
            throw new \InvalidArgumentException("Invalid assumption type: {$type}");
        }

        $updateData = array_filter([
            'inflation_rate' => isset($data['inflation_rate']) ? (float) $data['inflation_rate'] : null,
            'return_rate' => isset($data['return_rate']) ? (float) $data['return_rate'] : null,
            'compound_periods' => isset($data['compound_periods']) ? (int) $data['compound_periods'] : null,
        ], fn ($value) => $value !== null);

        // If all values are being reset, delete the override
        if (empty($updateData)) {
            UserAssumption::where('user_id', $userId)
                ->where('assumption_type', $type)
                ->delete();

            Log::info('User assumptions reset to defaults', [
                'user_id' => $userId,
                'type' => $type,
            ]);
        } else {
            UserAssumption::updateOrCreate(
                [
                    'user_id' => $userId,
                    'assumption_type' => $type,
                ],
                $updateData
            );

            Log::info('User assumptions updated', [
                'user_id' => $userId,
                'type' => $type,
                'data' => $updateData,
            ]);
        }

        $user = User::with(['dcPensions.holdings', 'investmentAccounts.holdings'])
            ->findOrFail($userId);

        return $this->getTypeAssumptions($user, $type);
    }

    /**
     * Reset assumptions for a specific type back to defaults.
     */
    public function resetAssumptions(int $userId, string $type): array
    {
        UserAssumption::where('user_id', $userId)
            ->where('assumption_type', $type)
            ->delete();

        Log::info('User assumptions reset to defaults', [
            'user_id' => $userId,
            'type' => $type,
        ]);

        $user = User::with(['dcPensions.holdings', 'investmentAccounts.holdings'])
            ->findOrFail($userId);

        return $this->getTypeAssumptions($user, $type);
    }

    /**
     * Get default values for assumptions based on user's risk profile.
     *
     * @return array{inflation_rate: float, return_rate: float, compound_periods: int, risk_level: string}
     */
    public function getDefaults(User $user, string $type): array
    {
        $riskLevel = $this->riskService->getMainRiskLevel($user->id) ?? 'medium';

        try {
            $riskParams = $this->riskService->getReturnParameters($riskLevel);
            $returnRate = $riskParams['expected_return_typical'];
        } catch (\Exception $e) {
            Log::warning('Failed to get risk parameters, using default', [
                'user_id' => $user->id,
                'risk_level' => $riskLevel,
                'error' => $e->getMessage(),
            ]);
            $returnRate = 5.0; // Default medium return
        }

        return [
            'inflation_rate' => self::DEFAULT_INFLATION_RATE,
            'return_rate' => $returnRate,
            'compound_periods' => self::DEFAULT_COMPOUND_PERIODS,
            'risk_level' => $riskLevel,
        ];
    }

    /**
     * Calculate weighted average fees for a type (pensions or investments).
     *
     * @return array{platform: float, ocf: float, advisory: float|null, total: float}
     */
    public function calculateWeightedFees(User $user, string $type): array
    {
        $totalValue = 0.0;
        $weightedPlatformFee = 0.0;
        $weightedOcf = 0.0;
        $weightedAdvisoryFee = 0.0;
        $hasAdvisoryFees = false;

        if ($type === 'pensions') {
            foreach ($user->dcPensions as $pension) {
                $value = (float) ($pension->current_fund_value ?? 0);
                if ($value <= 0) {
                    continue;
                }

                $totalValue += $value;
                $platformFee = (float) ($pension->platform_fee_percent ?? 0);
                $weightedPlatformFee += $value * $platformFee;

                // Calculate weighted OCF from holdings
                $holdingsOcf = $this->calculateHoldingsWeightedOcf($pension->holdings, $value);
                $weightedOcf += $value * $holdingsOcf;
            }
        } else {
            foreach ($user->investmentAccounts as $account) {
                $value = (float) ($account->current_value ?? 0);
                if ($value <= 0) {
                    continue;
                }

                $totalValue += $value;
                $platformFee = (float) ($account->platform_fee_percent ?? 0);
                $weightedPlatformFee += $value * $platformFee;

                $advisoryFee = (float) ($account->advisor_fee_percent ?? 0);
                if ($advisoryFee > 0) {
                    $hasAdvisoryFees = true;
                    $weightedAdvisoryFee += $value * $advisoryFee;
                }

                // Calculate weighted OCF from holdings
                $holdingsOcf = $this->calculateHoldingsWeightedOcf($account->holdings, $value);
                $weightedOcf += $value * $holdingsOcf;
            }
        }

        if ($totalValue <= 0) {
            return [
                'platform' => 0.0,
                'ocf' => 0.0,
                'advisory' => $type === 'investments' ? 0.0 : null,
                'total' => 0.0,
            ];
        }

        $avgPlatformFee = round($weightedPlatformFee / $totalValue, 2);
        $avgOcf = round($weightedOcf / $totalValue, 2);
        $avgAdvisoryFee = $hasAdvisoryFees ? round($weightedAdvisoryFee / $totalValue, 2) : null;

        $total = $avgPlatformFee + $avgOcf;
        if ($avgAdvisoryFee !== null) {
            $total += $avgAdvisoryFee;
        }

        return [
            'platform' => $avgPlatformFee,
            'ocf' => $avgOcf,
            'advisory' => $type === 'investments' ? ($avgAdvisoryFee ?? 0.0) : null,
            'total' => round($total, 2),
        ];
    }

    /**
     * Calculate weighted OCF from a collection of holdings.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Holding>  $holdings
     */
    private function calculateHoldingsWeightedOcf($holdings, float $accountValue): float
    {
        if ($holdings->isEmpty() || $accountValue <= 0) {
            return 0.0;
        }

        $totalHoldingValue = 0.0;
        $weightedOcf = 0.0;

        foreach ($holdings as $holding) {
            $holdingValue = (float) ($holding->current_value ?? 0);
            if ($holdingValue <= 0) {
                continue;
            }

            $totalHoldingValue += $holdingValue;
            $ocf = (float) ($holding->ocf ?? $holding->annual_fee ?? 0);
            $weightedOcf += $holdingValue * $ocf;
        }

        if ($totalHoldingValue <= 0) {
            return 0.0;
        }

        return $weightedOcf / $totalHoldingValue;
    }

    /**
     * Get years to retirement for a user.
     */
    public function getYearsToRetirement(User $user): int
    {
        $currentAge = $user->date_of_birth?->age ?? 40;
        $retirementAge = $this->getRetirementAge($user);

        return max(0, $retirementAge - $currentAge);
    }

    /**
     * Get the user's retirement age from profile or pensions.
     */
    private function getRetirementAge(User $user): int
    {
        // First check user profile
        if ($user->target_retirement_age) {
            return $user->target_retirement_age;
        }

        // Then check DC pensions
        foreach ($user->dcPensions as $pension) {
            if ($pension->retirement_age) {
                return $pension->retirement_age;
            }
        }

        return self::DEFAULT_RETIREMENT_AGE;
    }

    /**
     * Get total value for a type (pensions or investments).
     */
    private function getTotalValue(User $user, string $type): float
    {
        if ($type === 'pensions') {
            return $user->dcPensions->sum(fn ($p) => (float) ($p->current_fund_value ?? 0));
        }

        return $user->investmentAccounts->sum(fn ($a) => (float) ($a->current_value ?? 0));
    }
}
