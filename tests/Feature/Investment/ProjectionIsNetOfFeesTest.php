<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Investment\InvestmentProjectionService;

/**
 * W-0008 — a fee that is entered, displayed, and never charged.
 *
 * The adviser fee became enterable on 2026-08-21, but quality-lead rejected the item
 * twice on one criterion: "the fee is enterable and has never been shown to reach the
 * projection it is entered for". It had not, because InvestmentProjectionService drove
 * every one of its four Monte Carlo call sites with the gross risk-derived return and
 * contained no reference to a fee of any kind. Setting the adviser fee to 0.75 or to 0
 * produced projections identical to the pound.
 *
 * The same silence covered the platform fee and the fund OCF, so the fee card could
 * read "total 1.42%" directly above a chart compounding the full gross return. These
 * tests pin all three, because deducting only the adviser fee would leave the screen
 * contradicting itself.
 *
 * Assertions here are movement, ordering and magnitude-band assertions. None compares
 * a projection to a literal — see PortfolioProjectionRespondsToInputsTest for why.
 */
beforeEach(function () {
    $this->service = app(InvestmentProjectionService::class);
    $this->user = User::factory()->create();
});

/**
 * An account with every fee pinned to zero, so a test moves one fee at a time.
 * The factory randomises platform_fee_percent, which would otherwise be noise.
 */
function feelessAccount(User $user, float $value = 100000, string $type = 'gia'): InvestmentAccount
{
    return InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => $type,
        'current_value' => $value,
        'ownership_type' => 'individual',
        'risk_preference' => 'medium',
        'monthly_contribution_amount' => null,
        'contributions_ytd' => 0,
        'isa_subscription_current_year' => 0,
        'platform_fee_percent' => 0,
        'platform_fee_type' => 'percentage',
        'advisor_fee_percent' => 0,
    ]);
}

function accountP50(InvestmentProjectionService $service, InvestmentAccount $account, User $user, int $years = 10): float
{
    $result = $service->getAccountProjectionWithRiskOverride($account->fresh(), $user->fresh(), null, [$years]);

    return (float) $result['projections'][$years]['percentiles']['p50'];
}

describe('an entered fee reaches the projection', function () {
    it('lowers the projected value when an adviser fee is entered', function () {
        $account = feelessAccount($this->user);

        $withoutFee = accountP50($this->service, $account, $this->user);
        $account->update(['advisor_fee_percent' => 0.75]);
        $withFee = accountP50($this->service, $account, $this->user);

        expect($withFee)->toBeLessThan($withoutFee);
    });

    it('lowers the projected value when a platform fee is entered', function () {
        $account = feelessAccount($this->user);

        $withoutFee = accountP50($this->service, $account, $this->user);
        $account->update(['platform_fee_percent' => 0.45]);
        $withFee = accountP50($this->service, $account, $this->user);

        expect($withFee)->toBeLessThan($withoutFee);
    });

    it('lowers the projected value when a holding carries an OCF', function () {
        $account = feelessAccount($this->user);

        $withoutFee = accountP50($this->service, $account, $this->user);

        Holding::factory()->create([
            'holdable_id' => $account->id,
            'holdable_type' => InvestmentAccount::class,
            'current_value' => 100000,
            'ocf_percent' => 0.22,
        ]);

        $withFee = accountP50($this->service, $account->fresh(), $this->user);

        expect($withFee)->toBeLessThan($withoutFee);
    });

    it('charges a fixed platform fee as its percentage of the account', function () {
        $account = feelessAccount($this->user, 100000);

        $withoutFee = accountP50($this->service, $account, $this->user);

        // £1,000 a year on £100,000 is 1% — the same drag as platform_fee_percent 1.0.
        $account->update([
            'platform_fee_type' => 'fixed',
            'platform_fee_amount' => 1000,
            'platform_fee_frequency' => 'annually',
        ]);
        $asFixed = accountP50($this->service, $account, $this->user);

        $account->update([
            'platform_fee_type' => 'percentage',
            'platform_fee_percent' => 1.0,
            'platform_fee_amount' => null,
        ]);
        $asPercentage = accountP50($this->service, $account, $this->user);

        expect($asFixed)->toBeLessThan($withoutFee)
            ->and($asFixed)->toBe($asPercentage);
    });
});

describe('the fees compound together', function () {
    it('drags further as each fee is added on top of the last', function () {
        $account = feelessAccount($this->user);

        $none = accountP50($this->service, $account, $this->user);

        $account->update(['platform_fee_percent' => 0.45]);
        $platformOnly = accountP50($this->service, $account, $this->user);

        $account->update(['advisor_fee_percent' => 0.75]);
        $platformAndAdviser = accountP50($this->service, $account, $this->user);

        expect($platformOnly)->toBeLessThan($none)
            ->and($platformAndAdviser)->toBeLessThan($platformOnly);
    });

    it('reduces the projection by roughly the compounded value of the fee', function () {
        $account = feelessAccount($this->user, 100000);

        $withoutFee = accountP50($this->service, $account, $this->user, 10);
        $account->update(['advisor_fee_percent' => 1.0]);
        $withFee = accountP50($this->service, $account, $this->user, 10);

        // A 1% annual drag over 10 years costs about 1 - 0.99^10 = 9.6% of the
        // terminal value. The band is wide because the figure is a simulated
        // percentile, not a closed form — but a rounding error or a single year's
        // deduction both fall outside it.
        $reduction = ($withoutFee - $withFee) / $withoutFee;

        expect($reduction)->toBeGreaterThan(0.05)
            ->and($reduction)->toBeLessThan(0.15);
    });
});

describe('the stated return matches the projection it produced', function () {
    it('reports the return net of fees, and the gross return beside it', function () {
        $account = feelessAccount($this->user);
        $account->update(['platform_fee_percent' => 0.45, 'advisor_fee_percent' => 0.75]);

        $result = $this->service->getAccountProjectionWithRiskOverride($account->fresh(), $this->user->fresh(), null, [10]);

        expect($result['fee_drag_percent'])->toEqualWithDelta(1.20, 0.001)
            ->and($result['expected_return'])->toEqualWithDelta($result['gross_expected_return'] - 1.20, 0.001)
            ->and($result['expected_return'])->toBeLessThan($result['gross_expected_return']);
    });

    it('states no drag when no fee is recorded', function () {
        $account = feelessAccount($this->user);

        $result = $this->service->getAccountProjectionWithRiskOverride($account->fresh(), $this->user->fresh(), null, [10]);

        expect($result['fee_drag_percent'])->toBe(0.0)
            ->and($result['expected_return'])->toBe($result['gross_expected_return']);
    });
});

describe('the portfolio charges the fees of the accounts in it', function () {
    it('lowers the portfolio projection when an account gains an adviser fee', function () {
        $account = feelessAccount($this->user);

        $before = (float) $this->service->getPortfolioProjections($this->user->fresh(), [10])['portfolio']['projections'][10]['percentiles']['p50'];
        $account->update(['advisor_fee_percent' => 0.75]);
        $after = (float) $this->service->getPortfolioProjections($this->user->fresh(), [10])['portfolio']['projections'][10]['percentiles']['p50'];

        expect($after)->toBeLessThan($before);
    });

    it('weights each account fee by that account share of the portfolio', function () {
        // £75,000 at no fee and £25,000 at 4% is a 1% drag on the whole.
        feelessAccount($this->user, 75000, 'gia');
        $small = feelessAccount($this->user, 25000, 'isa');
        $small->update(['advisor_fee_percent' => 4.0]);

        $result = $this->service->getPortfolioProjections($this->user->fresh(), [10]);

        expect((float) $result['portfolio']['fee_drag_percent'])->toEqualWithDelta(1.0, 0.001);
    });
});
