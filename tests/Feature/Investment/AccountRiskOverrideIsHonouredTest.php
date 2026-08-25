<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\User;
use App\Services\Investment\InvestmentProjectionService;
use App\Services\Risk\RiskPreferenceService;

/**
 * W-0264 (investment side) — a per-account risk level the user chose must reach every
 * reader of it.
 *
 * `has_custom_risk` has no client writer on an investment account: 16 accounts carried a
 * `risk_preference` and 2 carried the flag. Three readers gated on
 * `has_custom_risk && risk_preference`, so each silently discarded the user's choice and
 * fell back to their main profile.
 *
 * The discrimination test that matters is in the first case below: **the answer must not
 * depend on the flag.** Setting the flag by hand must change nothing, because the
 * preference alone is the fact.
 */
beforeEach(function () {
    $this->riskService = app(RiskPreferenceService::class);
    $this->projections = app(InvestmentProjectionService::class);
    $this->user = User::factory()->create();
});

function overrideAccount(User $user, ?string $risk, bool $flag): InvestmentAccount
{
    return InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'gia',
        'current_value' => 100000,
        'ownership_type' => 'individual',
        'risk_preference' => $risk,
        'has_custom_risk' => $flag,
        'monthly_contribution_amount' => null,
        'contributions_ytd' => 0,
    ]);
}

/**
 * Set the user's main risk level AFTER any account exists.
 *
 * Creating an InvestmentAccount fires RiskRecalculationObserver, which recalculates and
 * overwrites the stored profile — so a level set beforehand is silently replaced. The
 * service also caches per user, hence the clear.
 */
function setMainLevel(RiskPreferenceService $service, User $user, string $level): void
{
    $service->setMainRiskLevel($user->id, $level);
    $service->clearUserCache($user->id);
}

function clearMainLevel(RiskPreferenceService $service, User $user): void
{
    RiskProfile::where('user_id', $user->id)->delete();
    $service->clearUserCache($user->id);
}

describe('a per-account risk override', function () {
    // If the answer moved with the flag, the flag would still be load-bearing.
    it('is read from the preference and does not depend on the has_custom_risk flag', function () {
        $withoutFlag = overrideAccount($this->user, 'high', false);
        $withFlag = overrideAccount($this->user, 'high', true);

        expect($this->riskService->getProductRiskOverride($withoutFlag))->toBe('high')
            ->and($this->riskService->getProductRiskOverride($withFlag))->toBe('high');
    });

    it('is absent when no preference is recorded, whatever the flag says', function () {
        $flagged = overrideAccount($this->user, null, true);

        expect($this->riskService->getProductRiskOverride($flagged))->toBeNull();
    });

    it('resolves to the account preference over the user profile', function () {
        $account = overrideAccount($this->user, 'high', false);
        setMainLevel($this->riskService, $this->user, 'low');

        expect($this->riskService->resolveProductRiskLevel($account, $this->user->id))->toBe('high');
    });

    it('falls back to the user profile when the account states nothing', function () {
        $account = overrideAccount($this->user, null, false);
        setMainLevel($this->riskService, $this->user, 'upper_medium');

        expect($this->riskService->resolveProductRiskLevel($account, $this->user->id))->toBe('upper_medium');
    });

    it('falls back to medium when neither states anything', function () {
        $account = overrideAccount($this->user, null, false);
        clearMainLevel($this->riskService, $this->user);

        expect($this->riskService->resolveProductRiskLevel($account, $this->user->id))->toBe('medium');
    });

    // The projection is the reader that was already correct; this pins it so a future
    // "consistency" change cannot reintroduce the flag gate here.
    it('reaches the projection, with the flag unset', function () {
        $account = overrideAccount($this->user, 'high', false);
        setMainLevel($this->riskService, $this->user, 'low');

        $atHigh = $this->projections->getPortfolioProjections($this->user->fresh(), [10]);

        $account->update(['risk_preference' => 'low']);
        $atLow = $this->projections->getPortfolioProjections($this->user->fresh(), [10]);

        expect($atHigh['portfolio']['expected_return'])
            ->toBeGreaterThan($atLow['portfolio']['expected_return'])
            ->and($atHigh['portfolio']['projections'][10]['percentiles']['p50'])
            ->toBeGreaterThan($atLow['portfolio']['projections'][10]['percentiles']['p50']);
    });
});
