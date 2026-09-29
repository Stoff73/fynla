<?php

declare(strict_types=1);

use App\Agents\InvestmentAgent;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/*
 * SaveTax run 29 Sep 2026, M3: a campaign user has a £18,000 Stocks & Shares ISA
 * but no risk profile and no expenditure yet. The investment readiness gate blocks
 * the analysis, and the blocked response used to carry `portfolio_summary: null` —
 * so the dashboard card read "0 accounts, £0, Add your investments" beside an
 * Investments page showing the ISA. What the user HOLDS does not depend on a risk
 * profile; only the advice does. The blocked response now reports the facts and
 * withholds the analysis (the W-0244 retirement precedent).
 */
beforeEach(function () {
    Cache::flush();
});

describe('InvestmentAgent readiness-blocked response', function () {
    it('reports the portfolio the user holds even when the gate blocks analysis', function () {
        // No date of birth, income, risk profile or expenditure: the gate blocks.
        $user = User::factory()->create(['date_of_birth' => null]);
        InvestmentAccount::factory()->isa()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 18000,
        ]);

        $result = app(InvestmentAgent::class)->analyze($user->id);

        expect($result['can_proceed'])->toBeFalse()
            ->and($result['portfolio_summary']['total_value'])->toEqual(18000.0)
            ->and($result['portfolio_summary']['accounts_count'])->toBe(1)
            ->and($result['asset_allocation'])->toBeNull()
            ->and($result['fee_analysis'])->toBeNull();
    });

    it('reports a joint account at the user\'s own share when blocked', function () {
        $user = User::factory()->create(['date_of_birth' => null]);
        $spouse = User::factory()->create();
        InvestmentAccount::factory()->isa()->create([
            'user_id' => $spouse->id,
            'joint_owner_id' => $user->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 60,
            'current_value' => 10000,
        ]);

        $result = app(InvestmentAgent::class)->analyze($user->id);

        expect($result['can_proceed'])->toBeFalse()
            ->and($result['portfolio_summary']['total_value'])->toEqual(4000.0)
            ->and($result['portfolio_summary']['accounts_count'])->toBe(1);
    });

    it('does not show another user\'s portfolio', function () {
        $user = User::factory()->create(['date_of_birth' => null]);
        InvestmentAccount::factory()->isa()->create([
            'user_id' => User::factory()->create()->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_value' => 50000,
        ]);

        $result = app(InvestmentAgent::class)->analyze($user->id);

        expect($result['can_proceed'])->toBeFalse()
            ->and($result['portfolio_summary']['total_value'])->toEqual(0.0)
            ->and($result['portfolio_summary']['accounts_count'])->toBe(0);
    });
});
