<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Investment\InvestmentProjectionService;

/**
 * W-0252 / W-0253 — the projection must answer to the data behind it.
 *
 * D-21: changing an account's risk preference moved the caption and the label and left
 * the projected value identical to the pound, because the cached simulation was keyed
 * on the user and the horizon but not on the risk-derived return and volatility.
 *
 * W-0217: an account projected higher than the portfolio containing it.
 *
 * Every assertion here is a movement or an ordering assertion. None of them compares a
 * projection to a literal, because a literal is exactly what let £4,650 survive.
 */
beforeEach(function () {
    $this->service = app(InvestmentProjectionService::class);
    $this->user = User::factory()->create();
});

function accountFor(User $user, string $risk, float $value = 100000, string $type = 'gia'): InvestmentAccount
{
    return InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => $type,
        'current_value' => $value,
        'ownership_type' => 'individual',
        'risk_preference' => $risk,
        'monthly_contribution_amount' => null,
        'contributions_ytd' => 0,
        'isa_subscription_current_year' => 0,
    ]);
}

function portfolioP20(InvestmentProjectionService $service, User $user, int $years = 10): float
{
    $result = $service->getPortfolioProjections($user->fresh(), [$years]);

    return (float) $result['portfolio']['projections'][$years]['percentiles']['p20'];
}

describe('a projection answers to its inputs', function () {
    it('moves the projected value when the risk preference moves', function () {
        $account = accountFor($this->user, 'low');

        $atLowRisk = portfolioP20($this->service, $this->user);
        $captionAtLowRisk = $this->service->getPortfolioProjections($this->user->fresh(), [10])['portfolio']['expected_return'];

        $account->update(['risk_preference' => 'high']);

        $atHighRisk = portfolioP20($this->service, $this->user);
        $captionAtHighRisk = $this->service->getPortfolioProjections($this->user->fresh(), [10])['portfolio']['expected_return'];

        // D-21 was the caption moving while the figure did not. The direction at the
        // 20th percentile is deliberately not asserted: see the ordering test below.
        expect($captionAtHighRisk)->toBeGreaterThan($captionAtLowRisk)
            ->and($atHighRisk)->not->toBe($atLowRisk);
    });

    // W-0217 asked that a higher risk preference produce a higher projection "at every
    // percentile reported". It does at the median and above. It does NOT in the left
    // tail, and that is a property of the model rather than a defect in it: added
    // volatility widens the downside faster than added expected return lifts it, so the
    // 20th percentile is hump-shaped in risk and peaks further up the risk scale the
    // longer the horizon. Asserting a monotonic p20 would require breaking the model.
    it('raises the median and widens the spread as the risk preference rises', function () {
        $account = accountFor($this->user, 'low');

        $medians = [];
        $spreads = [];

        foreach (['low', 'lower_medium', 'medium', 'upper_medium', 'high'] as $level) {
            $account->update(['risk_preference' => $level]);
            $bands = $this->service->getPortfolioProjections($this->user->fresh(), [10])['portfolio']['projections'][10]['percentiles'];

            $medians[] = (float) $bands['p50'];
            $spreads[] = (float) $bands['p90'] - (float) $bands['p10'];
        }

        $ascendingMedians = $medians;
        sort($ascendingMedians);
        $ascendingSpreads = $spreads;
        sort($ascendingSpreads);

        expect($medians)->toBe($ascendingMedians)
            ->and($spreads)->toBe($ascendingSpreads);
    });

    it('moves the projected value when capital is added', function () {
        $account = accountFor($this->user, 'medium', 100000);

        $before = portfolioP20($this->service, $this->user);
        $account->update(['current_value' => 200000]);
        $after = portfolioP20($this->service, $this->user);

        expect($after)->toBeGreaterThan($before * 1.5);
    });

    it('moves the projected value when a recorded contribution is added', function () {
        $account = accountFor($this->user, 'medium', 100000);

        $withoutContributions = portfolioP20($this->service, $this->user);
        $account->update(['monthly_contribution_amount' => 500, 'contribution_frequency' => 'monthly']);
        $withContributions = portfolioP20($this->service, $this->user);

        expect($withContributions)->toBeGreaterThan($withoutContributions + 40000);
    });

    it('grows with the horizon', function () {
        accountFor($this->user, 'medium', 100000);

        $result = $this->service->getPortfolioProjections($this->user->fresh(), [5, 10, 20, 30]);
        $byHorizon = collect([5, 10, 20, 30])
            ->map(fn (int $y) => (float) $result['portfolio']['projections'][$y]['percentiles']['p20']);

        expect($byHorizon->toArray())->toBe($byHorizon->sort()->values()->toArray());
    });

    it('assumes no contribution the user has not recorded', function () {
        accountFor($this->user, 'medium', 100000, 'isa');

        $result = $this->service->getPortfolioProjections($this->user->fresh(), [10]);

        expect($result['portfolio']['estimated_monthly_contribution'])->toBe(0.0);
    });
});

describe('a portfolio is never worth less than a part of itself', function () {
    it('projects the whole portfolio at or above each account within it', function () {
        accountFor($this->user, 'medium', 95000, 'isa');
        accountFor($this->user, 'high', 47500);
        accountFor($this->user, 'low', 30000, 'vct');

        $result = $this->service->getPortfolioProjections($this->user->fresh(), [5, 10, 20, 30]);

        foreach ([5, 10, 20, 30] as $years) {
            $portfolio = (float) $result['portfolio']['projections'][$years]['percentiles']['p20'];

            foreach ($result['accounts'] as $account) {
                expect((float) $account['projections'][$years]['percentiles']['p20'])
                    ->toBeLessThanOrEqual($portfolio);
            }
        }
    });

    it('gives a single-account portfolio the same answer as that account', function () {
        accountFor($this->user, 'medium', 85000, 'isa');

        $result = $this->service->getPortfolioProjections($this->user->fresh(), [10, 30]);

        foreach ([10, 30] as $years) {
            expect((float) $result['accounts'][0]['projections'][$years]['percentiles']['p20'])
                ->toBe((float) $result['portfolio']['projections'][$years]['percentiles']['p20']);
        }
    });
});
