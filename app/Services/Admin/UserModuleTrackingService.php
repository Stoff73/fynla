<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Estate\Will;
use App\Models\Investment\RiskProfile;
use App\Models\User;

class UserModuleTrackingService
{
    public function getModuleStatus(User $user): array
    {
        $user->loadMissing([
            'lifeInsurancePolicies', 'criticalIllnessPolicies',
            'incomeProtectionPolicies', 'disabilityPolicies', 'sicknessIllnessPolicies',
            'cashAccounts', 'savingsAccounts',
            'investmentAccounts.holdings',
            'dcPensions', 'dbPensions', 'statePension', 'retirementProfile',
            'lastingPowersOfAttorney', 'trusts', 'gifts', 'assets',
        ]);

        $spouse = null;
        if ($user->spouse_id) {
            $spouse = User::with([
                'lifeInsurancePolicies', 'criticalIllnessPolicies',
                'cashAccounts', 'savingsAccounts',
                'investmentAccounts', 'dcPensions', 'dbPensions',
            ])->find($user->spouse_id);
        }

        return [
            'protection' => $this->protectionStatus($user, $spouse),
            'savings' => $this->savingsStatus($user, $spouse),
            'investment' => $this->investmentStatus($user, $spouse),
            'retirement' => $this->retirementStatus($user, $spouse),
            'estate' => $this->estateStatus($user, $spouse),
        ];
    }

    private function protectionStatus(User $user, ?User $spouse): array
    {
        $skipped = in_array('protection', $user->onboarding_skipped_steps ?? []);
        if ($skipped) {
            return ['status' => 'skipped', 'details' => []];
        }

        $life = $user->lifeInsurancePolicies->count() + ($spouse?->lifeInsurancePolicies->count() ?? 0);
        $ci = $user->criticalIllnessPolicies->count() + ($spouse?->criticalIllnessPolicies->count() ?? 0);
        $ip = $user->incomeProtectionPolicies->count();
        $disability = $user->disabilityPolicies->count();
        $sickness = $user->sicknessIllnessPolicies->count();

        $total = $life + $ci + $ip + $disability + $sickness;
        $status = $total === 0 ? 'empty' : ($life > 0 && $ci > 0 ? 'complete' : 'partial');

        return ['status' => $status, 'details' => compact('life', 'ci', 'ip', 'disability', 'sickness')];
    }

    private function savingsStatus(User $user, ?User $spouse): array
    {
        $skipped = in_array('savings', $user->onboarding_skipped_steps ?? []);
        if ($skipped) {
            return ['status' => 'skipped', 'details' => []];
        }

        $cash = $user->cashAccounts->count() + ($spouse?->cashAccounts->count() ?? 0);
        $savings = $user->savingsAccounts->count() + ($spouse?->savingsAccounts->count() ?? 0);
        $isa = $user->savingsAccounts->where('is_isa', true)->count();
        $cashTotal = $user->cashAccounts->sum('current_balance') + ($spouse?->cashAccounts->sum('current_balance') ?? 0);
        $savingsTotal = $user->savingsAccounts->sum('current_balance') + ($spouse?->savingsAccounts->sum('current_balance') ?? 0);

        $total = $cash + $savings;
        $hasEmergencyFund = $cash > 0;
        $status = $total === 0 ? 'empty' : ($cash > 0 && $savings > 0 ? 'complete' : 'partial');

        return ['status' => $status, 'details' => compact('cash', 'savings', 'isa', 'cashTotal', 'savingsTotal', 'hasEmergencyFund')];
    }

    private function investmentStatus(User $user, ?User $spouse): array
    {
        $skipped = in_array('investment', $user->onboarding_skipped_steps ?? []);
        if ($skipped) {
            return ['status' => 'skipped', 'details' => []];
        }

        $accounts = $user->investmentAccounts->count() + ($spouse?->investmentAccounts->count() ?? 0);
        $holdings = $user->investmentAccounts->sum(fn ($a) => $a->holdings->count());
        $totalValue = $user->investmentAccounts->sum('current_value') + ($spouse?->investmentAccounts->sum('current_value') ?? 0);
        $hasRiskProfile = RiskProfile::where('user_id', $user->id)->exists();

        $status = $accounts === 0 ? 'empty' : ($accounts > 0 && $hasRiskProfile ? 'complete' : 'partial');

        return ['status' => $status, 'details' => compact('accounts', 'holdings', 'totalValue', 'hasRiskProfile')];
    }

    private function retirementStatus(User $user, ?User $spouse): array
    {
        $skipped = in_array('retirement', $user->onboarding_skipped_steps ?? []);
        if ($skipped) {
            return ['status' => 'skipped', 'details' => []];
        }

        $dc = $user->dcPensions->count() + ($spouse?->dcPensions->count() ?? 0);
        $db = $user->dbPensions->count() + ($spouse?->dbPensions->count() ?? 0);
        $hasStatePension = $user->statePension !== null;
        $hasRetirementProfile = $user->retirementProfile !== null;
        $dcTotal = $user->dcPensions->sum('current_fund_value') + ($spouse?->dcPensions->sum('current_fund_value') ?? 0);

        $total = $dc + $db + ($hasStatePension ? 1 : 0);
        $status = $total === 0 ? 'empty' : ($dc > 0 && $hasStatePension && $hasRetirementProfile ? 'complete' : 'partial');

        return ['status' => $status, 'details' => compact('dc', 'db', 'hasStatePension', 'hasRetirementProfile', 'dcTotal')];
    }

    private function estateStatus(User $user, ?User $spouse): array
    {
        $skipped = in_array('estate', $user->onboarding_skipped_steps ?? []);
        if ($skipped) {
            return ['status' => 'skipped', 'details' => []];
        }

        $hasWill = Will::where('user_id', $user->id)->exists();
        $lpa = $user->lastingPowersOfAttorney->count();
        $trustCount = $user->trusts->count();
        $trustValue = $user->trusts->sum('current_value');
        $giftCount = $user->gifts->count();
        $assetCount = $user->assets->count();

        $total = ($hasWill ? 1 : 0) + $lpa + $trustCount + $giftCount + $assetCount;
        $status = $total === 0 ? 'empty' : ($hasWill && $lpa > 0 ? 'complete' : 'partial');

        return ['status' => $status, 'details' => compact('hasWill', 'lpa', 'trustCount', 'trustValue', 'giftCount', 'assetCount')];
    }
}
