<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Mobile\MobileDashboardAggregator;
use Illuminate\Support\Facades\Cache;

/**
 * W-0238 — one response must not contain two answers to the same question.
 *
 * `GET /api/v1/mobile/dashboard` returns `modules.savings.total_savings` and
 * `net_worth.breakdown.assets.savings` side by side, and they disagreed: the
 * module cards summed a `user_id`-scoped set at 100% while the net worth block
 * summed a reach-complete set at the user's share. On a household with one joint
 * account that overstates the spouse who typed it in and understates the one who
 * did not — wrong in opposite directions on the two logins, from the same row.
 *
 * These assertions are written as **equalities between the two blocks of one
 * response**, not against literals alone. A literal can be updated to match a
 * regression; an internal contradiction cannot be argued with.
 */
function householdMember(?User $spouse = null): User
{
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears(48),
        'annual_employment_income' => 90000,
        'monthly_expenditure' => 3000,
    ]);

    RiskProfile::factory()->create(['user_id' => $user->id]);

    if ($spouse) {
        $user->spouse_id = $spouse->id;
        $user->save();
        $spouse->spouse_id = $user->id;
        $spouse->save();
    }

    return $user;
}

beforeEach(function () {
    $this->recorder = householdMember();
    $this->coOwner = householdMember($this->recorder);

    // Individually held, one each.
    SavingsAccount::factory()->create([
        'user_id' => $this->recorder->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 25000,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $this->coOwner->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 6280,
    ]);

    // ONE joint record (Rule 6), recorded on the first account.
    SavingsAccount::factory()->create([
        'user_id' => $this->recorder->id,
        'joint_owner_id' => $this->coOwner->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'current_balance' => 4500,
    ]);

    InvestmentAccount::factory()->create([
        'user_id' => $this->coOwner->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 85000,
    ]);
    InvestmentAccount::factory()->create([
        'user_id' => $this->recorder->id,
        'joint_owner_id' => $this->coOwner->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'current_value' => 95000,
    ]);

    $this->dashboardFor = function (User $user): array {
        Cache::forget("mobile_dashboard_{$user->id}");

        return app(MobileDashboardAggregator::class)->getAggregatedDashboard($user->id);
    };
});

describe('the module cards agree with the net worth block beside them', function () {
    it('agrees on savings for the spouse who recorded the joint account', function () {
        $dashboard = ($this->dashboardFor)($this->recorder);

        expect($dashboard['modules']['savings']['total_savings'])
            ->toEqualWithDelta($dashboard['net_worth']['breakdown']['assets']['savings'], 0.01)
            // 25,000 individually + half of the 4,500 joint account.
            ->toEqualWithDelta(27250.0, 0.01);
    });

    it('agrees on savings for the spouse who did not record it', function () {
        $dashboard = ($this->dashboardFor)($this->coOwner);

        expect($dashboard['modules']['savings']['total_savings'])
            ->toEqualWithDelta($dashboard['net_worth']['breakdown']['assets']['savings'], 0.01)
            // 6,280 individually + the other half of the joint account, which was
            // invisible to this login entirely before the reach was fixed.
            ->toEqualWithDelta(8530.0, 0.01);
    });

    it('agrees on investments for the recording spouse', function () {
        $dashboard = ($this->dashboardFor)($this->recorder);

        expect($dashboard['modules']['investment']['portfolio_value'])
            ->toEqualWithDelta($dashboard['net_worth']['breakdown']['assets']['investments'], 0.01)
            ->toEqualWithDelta(47500.0, 0.01);
    });

    it('agrees on investments for the co-owner', function () {
        $dashboard = ($this->dashboardFor)($this->coOwner);

        expect($dashboard['modules']['investment']['portfolio_value'])
            ->toEqualWithDelta($dashboard['net_worth']['breakdown']['assets']['investments'], 0.01)
            // 85,000 of her own plus her half of the joint account.
            ->toEqualWithDelta(132500.0, 0.01);
    });

    it('splits the joint account exactly once across the household', function () {
        $recorder = ($this->dashboardFor)($this->recorder);
        $coOwner = ($this->dashboardFor)($this->coOwner);

        $householdSavings = $recorder['modules']['savings']['total_savings']
            + $coOwner['modules']['savings']['total_savings'];

        // 25,000 + 6,280 + the whole of the 4,500 joint account — counted once,
        // not twice and not not-at-all.
        expect($householdSavings)->toEqualWithDelta(35780.0, 0.01);
    });

    it('counts the joint account on both sides of the household', function () {
        expect(($this->dashboardFor)($this->recorder)['modules']['savings']['total_accounts'])->toBe(2)
            ->and(($this->dashboardFor)($this->coOwner)['modules']['savings']['total_accounts'])->toBe(2);
    });
});

describe('the retirement card of a household whose provision has no pot', function () {
    it('reports the guaranteed annual income rather than nothing', function () {
        $this->coOwner->dbPensions()->create([
            'scheme_name' => 'NHS Pension Scheme',
            'scheme_type' => 'final_salary',
            'accrued_annual_pension' => 35000,
            'pensionable_service_years' => 18,
            'inflation_protection' => 'none',
        ]);

        $dashboard = ($this->dashboardFor)($this->coOwner);

        expect($dashboard['modules']['retirement']['status'])->toBe('active')
            ->and($dashboard['modules']['retirement']['pot_value'])->toEqualWithDelta(0.0, 0.01)
            ->and($dashboard['modules']['retirement']['guaranteed_income'])->toEqualWithDelta(35000.0, 0.01);
    });

    it('still reports not_configured for a user with no pension records at all', function () {
        $dashboard = ($this->dashboardFor)($this->recorder);

        expect($dashboard['modules']['retirement']['status'])->toBe('not_configured');
    });
});
