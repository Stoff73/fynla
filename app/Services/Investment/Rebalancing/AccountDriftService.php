<?php

declare(strict_types=1);

namespace App\Services\Investment\Rebalancing;

use App\Constants\InvestmentDefaults;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\User;
use App\Services\Risk\RiskPreferenceService;

/**
 * Is one investment account outside its rebalancing threshold?
 *
 * The one rule (item 8, CSJ 2026-10-06 "Per account, as the page"): the
 * account's rebalancing panel, the investment position card and the dashboard
 * all read it, so they cannot disagree. The account is measured against the
 * risk level that applies to it (its own override, else the user's profile)
 * and its own threshold.
 */
class AccountDriftService
{
    /** The panel's threshold when the account has none of its own. */
    public const DEFAULT_THRESHOLD_PERCENT = 10.0;

    public function __construct(
        private readonly DriftAnalyzer $driftAnalyzer,
        private readonly RiskPreferenceService $riskPreferenceService,
    ) {}

    /**
     * @return array{
     *     risk_profile: array<string, mixed>,
     *     target_allocation: array<string, float|int>,
     *     threshold_percent: float,
     *     current_allocation: array<string, float>,
     *     unrecorded_percent: float,
     *     drifts_by_asset: array<string, array<string, float>>,
     *     drift_score: float,
     *     max_drift: float,
     *     needs_rebalancing: bool,
     *     urgency: string,
     *     recommendation: string,
     * }
     */
    public function forAccount(InvestmentAccount $account, User $user): array
    {
        $riskProfile = $this->resolveAccountRiskProfile($account, $user);
        $targetAllocation = InvestmentDefaults::getTargetAllocation($riskProfile['effective_risk_level']);
        $threshold = (float) ($account->rebalance_threshold_percent ?? self::DEFAULT_THRESHOLD_PERCENT);

        $base = [
            'risk_profile' => $riskProfile,
            'target_allocation' => $targetAllocation,
            'threshold_percent' => $threshold,
        ];

        $holdings = $account->holdings;
        if ($holdings->isEmpty()) {
            return $base + [
                'current_allocation' => ['equities' => 0, 'bonds' => 0, 'cash' => 0, 'alternatives' => 0],
                'unrecorded_percent' => 0.0,
                'drifts_by_asset' => [],
                'drift_score' => 0.0,
                'max_drift' => 0.0,
                'needs_rebalancing' => false,
                'urgency' => 'low',
                'recommendation' => '',
            ];
        }

        $drift = $this->driftAnalyzer->analyzeDrift($holdings, $targetAllocation);
        $driftScore = (float) ($drift['drift_score'] ?? 0);

        return $base + [
            'current_allocation' => $drift['current_allocation'] ?? [],
            'unrecorded_percent' => (float) ($drift['unrecorded_percent'] ?? 0),
            'drifts_by_asset' => $drift['drift_metrics']['drifts_by_asset'] ?? [],
            'drift_score' => round($driftScore, 2),
            'max_drift' => round((float) ($drift['drift_metrics']['max_drift'] ?? 0), 2),
            'needs_rebalancing' => $driftScore >= $threshold,
            'urgency' => $drift['urgency'] ?? 'low',
            'recommendation' => $drift['recommendation'] ?? '',
        ];
    }

    /**
     * The account's own risk preference if it has one, else the user's profile.
     */
    private function resolveAccountRiskProfile(InvestmentAccount $account, User $user): array
    {
        $userRiskProfile = RiskProfile::where('user_id', $user->id)->first();

        $userRiskLevel = $userRiskProfile
            ? $this->mapRiskStringToLevel($userRiskProfile->risk_level)
            : 3;
        $userRiskLabel = $this->getRiskLabel($userRiskLevel);

        // The override is the preference itself, not the `has_custom_risk` flag beside
        // it — nothing writes that flag on an investment account, so gating on it
        // rebalanced every account to the user's main profile regardless of the level
        // they had chosen for it.
        $accountRiskPreference = $this->riskPreferenceService->getProductRiskOverride($account);
        $hasCustomRisk = $accountRiskPreference !== null;

        $effectiveRiskLevel = $hasCustomRisk ? $this->mapRiskStringToLevel($accountRiskPreference) : $userRiskLevel;

        return [
            'user_risk_level' => $userRiskLevel,
            'user_risk_label' => $userRiskLabel,
            'has_custom_risk' => $hasCustomRisk,
            'account_risk_preference' => $accountRiskPreference,
            'effective_risk_level' => $effectiveRiskLevel,
            'effective_risk_label' => $this->getRiskLabel($effectiveRiskLevel),
        ];
    }

    private function getRiskLabel(int $riskLevel): string
    {
        return match ($riskLevel) {
            1 => 'Low',
            2 => 'Lower-Medium',
            3 => 'Medium',
            4 => 'Upper-Medium',
            5 => 'High',
            default => 'Medium',
        };
    }

    private function mapRiskStringToLevel(?string $riskString): int
    {
        if (! $riskString) {
            return 3;
        }

        return match (strtolower($riskString)) {
            'low', 'cautious', 'very_conservative' => 1,
            'lower_medium', 'conservative' => 2,
            'medium', 'balanced', 'moderate' => 3,
            'upper_medium', 'growth' => 4,
            'high', 'adventurous', 'aggressive' => 5,
            default => 3,
        };
    }
}
