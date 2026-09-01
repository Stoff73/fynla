<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Estate\EstateProjectionService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0199. W-0137 stopped projected cash going negative and published the spending it
 * could not meet as `projected_cash_shortfall`. That closed one contradiction and
 * opened the opposite one: a household was modelled as running out of cash while its
 * investments grew untouched to the end of the projection.
 *
 * **Nobody dies owing years of unfunded spending while holding a portfolio they never
 * sold** — and every pound of that portfolio was taxed as though they had.
 *
 * The model (acceptance 1): when cash reaches zero the household sells investments to
 * cover that year's shortfall, and stops when the portfolio is exhausted. What it still
 * cannot meet stays a shortfall, because a shortfall is a planning output and a
 * negative asset is a broken model.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(EstateProjectionService::class);
    $this->user = User::factory()->create(['date_of_birth' => now()->subYears(50)]);
});

/**
 * A custom rate is used throughout rather than Monte Carlo: the simulation returns a
 * p20 percentile that moves between runs, and a test that cannot state the expected
 * number cannot tell a correct drawdown from a wrong one. The path being exercised is
 * the same either way — the implied rate is derived from whichever horizon value the
 * configured method produced.
 */
function customAssumptions(float $ratePercent): array
{
    return [
        'investment_growth_method' => 'custom',
        'custom_investment_rate' => $ratePercent,
    ];
}

describe('investments are drawn on when cash runs out', function () {
    it('leaves the projection untouched when there is no shortfall', function () {
        $withoutInvestments = $this->service->projectInvestmentsAfterCashShortfall(
            $this->user, null, 10, customAssumptions(5.0), false, []
        );

        expect($withoutInvestments['drawn_from_investments'])->toBe(0.0)
            ->and($withoutInvestments['unmet_shortfall'])->toBe(0.0);
    });

    it('reports the whole shortfall as unmet when nothing is invested', function () {
        $result = $this->service->projectInvestmentsAfterCashShortfall(
            $this->user, null, 10, customAssumptions(5.0), false, [3 => 20000.0]
        );

        // The persona holds no investment accounts, so there is nothing to sell.
        expect($result['drawn_from_investments'])->toBe(0.0)
            ->and($result['unmet_shortfall'])->toBe(20000.0)
            ->and($result['projected_investments'])->toBe(0.0);
    });
});

/**
 * Acceptance 3, and the reason a lump subtraction at the horizon is wrong: money sold
 * in year 3 must stop compounding in year 3. Subtracting the total at the end credits
 * the household with growth on money it had already spent.
 */
describe('the drawdown stops the money compounding from the year it is spent', function () {
    it('costs more than the deficit itself, because the growth goes too', function () {
        $account = \App\Models\Investment\InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_value' => 100000,
            'ownership_type' => 'individual',
        ]);

        $noDeficit = $this->service->projectInvestmentsAfterCashShortfall(
            $this->user->fresh(), null, 10, customAssumptions(5.0), false, []
        );

        $earlyDeficit = $this->service->projectInvestmentsAfterCashShortfall(
            $this->user->fresh(), null, 10, customAssumptions(5.0), false, [1 => 10000.0]
        );

        $lateDeficit = $this->service->projectInvestmentsAfterCashShortfall(
            $this->user->fresh(), null, 10, customAssumptions(5.0), false, [9 => 10000.0]
        );

        // Both sold £10,000. The early sale gives up eight more years of growth on it,
        // so it must leave the household worse off at the horizon than the late one.
        expect($earlyDeficit['drawn_from_investments'])->toBe(10000.0)
            ->and($lateDeficit['drawn_from_investments'])->toBe(10000.0)
            ->and($earlyDeficit['projected_investments'])
            ->toBeLessThan($lateDeficit['projected_investments'])
            ->and($lateDeficit['projected_investments'])
            ->toBeLessThan($noDeficit['projected_investments']);

        // And the cost of the early sale exceeds the sum itself — which a lump
        // subtraction at the horizon could never show.
        expect($noDeficit['projected_investments'] - $earlyDeficit['projected_investments'])
            ->toBeGreaterThan(10000.0);

        $account->delete();
    });

    it('empties the portfolio before reporting anything unmet', function () {
        \App\Models\Investment\InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_value' => 5000,
            'ownership_type' => 'individual',
        ]);

        $result = $this->service->projectInvestmentsAfterCashShortfall(
            $this->user->fresh(), null, 5, customAssumptions(0.0), false, [0 => 50000.0]
        );

        // At a 0% rate the portfolio is worth exactly what was put in, so the split is
        // stateable: £5,000 sold, £45,000 still unfunded, nothing left invested.
        expect($result['drawn_from_investments'])->toBe(5000.0)
            ->and($result['unmet_shortfall'])->toBe(45000.0)
            ->and($result['projected_investments'])->toBe(0.0);
    });
});

/**
 * Acceptance 2. The guard: there must be ONE investment model. A second one would be
 * invisible to every behavioural test — it would simply produce different numbers, and
 * both would look plausible.
 */
describe('one investment model, not two', function () {
    it('keeps the plain projection identical to the drawdown path with no deficit', function () {
        \App\Models\Investment\InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_value' => 250000,
            'ownership_type' => 'individual',
        ]);

        $plain = $this->service->projectInvestments(
            $this->user->fresh(), null, 15, customAssumptions(6.0), false
        );

        $viaDrawdown = $this->service->projectInvestmentsAfterCashShortfall(
            $this->user->fresh(), null, 15, customAssumptions(6.0), false, []
        )['projected_investments'];

        expect($plain)->toBe($viaDrawdown);
    });

    it('honours the configured growth method rather than assuming Monte Carlo (W-0520)', function () {
        \App\Models\Investment\InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_value' => 100000,
            'ownership_type' => 'individual',
        ]);

        $slow = $this->service->projectInvestmentsAfterCashShortfall(
            $this->user->fresh(), null, 10, customAssumptions(1.0), false, []
        )['projected_investments'];

        $fast = $this->service->projectInvestmentsAfterCashShortfall(
            $this->user->fresh(), null, 10, customAssumptions(9.0), false, []
        )['projected_investments'];

        expect($fast)->toBeGreaterThan($slow);
    });
});
