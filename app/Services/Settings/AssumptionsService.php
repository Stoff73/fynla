<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Investment\Holding;
use App\Models\User;
use App\Models\UserAssumption;
use App\Services\Retirement\RetirementAgeResolver;
use App\Services\Retirement\StatePensionAgeResolver;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * W-0196. Was a private 68 — one of the two outliers against the 67 anchored by
     * W-0036. Reads the one home now.
     *
     * NOTE the conflation at the only call site below: it is used as a fallback for
     * STATE PENSION age, which is a different question with a different answer
     * (legislated by cohort — W-0197, W-0516). Left pointing here so the number stops
     * disagreeing with everything else, but W-0516 owns replacing it properly.
     */
    private const DEFAULT_RETIREMENT_AGE = RetirementAgeResolver::DEFAULT_RETIREMENT_AGE;

    private const DEFAULT_PROPERTY_GROWTH_RATE = 3.0;

    private const DEFAULT_INVESTMENT_GROWTH_METHOD = 'monte_carlo';

    /** W-0334 — the fallback both copies of this rule carried, now in one place. */
    private const DEFAULT_INVESTMENT_GROWTH_RATE = 0.047;

    private const VALID_ASSUMPTION_TYPES = ['pensions', 'investments', 'estate_planning'];

    public function __construct(
        private readonly RiskPreferenceService $riskService,
        private readonly TaxConfigService $taxConfig,
        // W-0197 — State Pension age by cohort, not a scalar.
        private readonly StatePensionAgeResolver $statePensionAge
    ) {}

    /**
     * Get all assumptions for a user (pensions, investments, and estate planning).
     */
    public function getAssumptions(int $userId): array
    {
        $user = User::with(['dcPensions.holdings', 'investmentAccounts.holdings'])
            ->findOrFail($userId);

        return [
            'pensions' => $this->getTypeAssumptions($user, 'pensions'),
            'investments' => $this->getTypeAssumptions($user, 'investments'),
            'estate_planning' => $this->getEstateAssumptions($user),
        ];
    }

    /**
     * Get estate planning assumptions for a user.
     */
    public function getEstateAssumptions(User $user): array
    {
        $override = UserAssumption::where('user_id', $user->id)
            ->where('assumption_type', 'estate_planning')
            ->first();

        return [
            'inflation_rate' => $override?->inflation_rate ?? self::DEFAULT_INFLATION_RATE,
            'inflation_rate_default' => self::DEFAULT_INFLATION_RATE,
            'property_growth_rate' => $override?->property_growth_rate ?? self::DEFAULT_PROPERTY_GROWTH_RATE,
            'property_growth_rate_default' => self::DEFAULT_PROPERTY_GROWTH_RATE,
            'investment_growth_method' => $override?->investment_growth_method ?? self::DEFAULT_INVESTMENT_GROWTH_METHOD,
            'investment_growth_method_default' => self::DEFAULT_INVESTMENT_GROWTH_METHOD,
            'custom_investment_rate' => $override?->custom_investment_rate,
            'has_overrides' => $override !== null && (
                $override->inflation_rate !== null ||
                $override->property_growth_rate !== null ||
                $override->investment_growth_method !== 'monte_carlo' ||
                $override->custom_investment_rate !== null
            ),
        ];
    }

    /**
     * Get assumptions for a specific type (pensions, investments, or estate_planning).
     */
    public function getTypeAssumptions(User $user, string $type): array
    {
        // Handle estate_planning separately
        if ($type === 'estate_planning') {
            return $this->getEstateAssumptions($user);
        }

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
        if (! in_array($type, self::VALID_ASSUMPTION_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid assumption type: {$type}");
        }

        // Handle estate_planning separately with its specific fields
        if ($type === 'estate_planning') {
            return $this->updateEstateAssumptions($userId, $data);
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
                'fields' => array_keys($updateData),
            ]);
        }

        $user = User::with(['dcPensions.holdings', 'investmentAccounts.holdings'])
            ->findOrFail($userId);

        return $this->getTypeAssumptions($user, $type);
    }

    /**
     * Update estate planning assumptions.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateEstateAssumptions(int $userId, array $data): array
    {
        $updateData = [];

        if (isset($data['inflation_rate'])) {
            $updateData['inflation_rate'] = (float) $data['inflation_rate'];
        }

        if (isset($data['property_growth_rate'])) {
            $updateData['property_growth_rate'] = (float) $data['property_growth_rate'];
        }

        if (isset($data['investment_growth_method'])) {
            $method = $data['investment_growth_method'];
            if (in_array($method, ['monte_carlo', 'custom'], true)) {
                $updateData['investment_growth_method'] = $method;
            }
        }

        if (isset($data['custom_investment_rate'])) {
            $updateData['custom_investment_rate'] = (float) $data['custom_investment_rate'];
        }

        // If all values are being reset, delete the override
        if (empty($updateData)) {
            UserAssumption::where('user_id', $userId)
                ->where('assumption_type', 'estate_planning')
                ->delete();

            Log::info('Estate planning assumptions reset to defaults', [
                'user_id' => $userId,
            ]);
        } else {
            UserAssumption::updateOrCreate(
                [
                    'user_id' => $userId,
                    'assumption_type' => 'estate_planning',
                ],
                $updateData
            );

            Log::info('Estate planning assumptions updated', [
                'user_id' => $userId,
                'fields' => array_keys($updateData),
            ]);
        }

        $user = User::findOrFail($userId);

        return $this->getEstateAssumptions($user);
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

        // BUG-02 (2026-08-17): both loops below read `->holdings` per record. Left
        // lazy, that threw LazyLoadingViolationException outside production — so
        // /api/retirement/projections returned 500 for any user with two funded
        // pensions and the native app rendered a £0 projected income. Production
        // hid it as an N+1. Eager-load once here rather than per record.
        if ($type === 'pensions') {
            $user->loadMissing('dcPensions.holdings');

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
            $user->loadMissing('investmentAccounts.holdings');

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
     * @param  Collection<int, Holding>  $holdings
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

        // W-0197. Was `current_spa` with a RETIREMENT-age default behind it — two
        // different questions answering each other. Both are now resolved by the
        // service that owns them.
        return $this->statePensionAge->forDateOfBirth($user->date_of_birth);
    }

    /**
     * W-0334 — the one home for "what annual growth rate does this user's assumption
     * imply", and the one place `investment_growth_method` is interpreted.
     *
     * `EstateProjectionService::getFallbackGrowthRate()` and
     * `LifeCoverCalculator::getInvestmentReturnRate()` were **byte-identical copies**
     * of this rule, hardcoded 4.7% fallback included. They agreed only because one was
     * copied from the other — and that arrangement is exactly what produced a setting
     * honoured by life-cover sizing and silently ignored by the estate projection,
     * which is the defect this item records.
     *
     * The default is the same 4.7% both copies carried; it lives here now so a change
     * cannot reach one consumer and not the other (Rule 20).
     */
    public function investmentGrowthRateFor(User $user): float
    {
        $assumptions = $this->getEstateAssumptions($user);

        if (($assumptions['investment_growth_method'] ?? self::DEFAULT_INVESTMENT_GROWTH_METHOD) === 'custom'
            && isset($assumptions['custom_investment_rate'])) {
            return (float) $assumptions['custom_investment_rate'] / 100;
        }

        return self::DEFAULT_INVESTMENT_GROWTH_RATE;
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
