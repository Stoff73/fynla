<?php

declare(strict_types=1);

use App\Models\ExpenditureProfile;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionProfile;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Mobile\MobileDashboardAggregator;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * One figure, every surface: savings and protection (CSJ 2026-10-01; audit
 * `docs/audits/2026-10-01-one-figure-every-surface.md` items 21-24, 31, 32 and
 * the emergency-fund part of 35).
 *
 * Every figure here is computed once on the server and read as sent by web,
 * `/m`, iOS and the dashboard. Each case pins the ONE field and is built so the
 * old answer and the new answer are different numbers (no Collision): the joint
 * account is 75/25, not 50/50; resolved spending differs from the raw column;
 * the employment status is one where the two month tables disagreed.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    Cache::flush();
});

function oneFigureUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'date_of_birth' => '1980-01-01',
        'annual_employment_income' => 60_000,
        'employment_status' => 'employed',
        'monthly_expenditure' => 1_000,
    ], $attributes));
}

describe('total cash (item 21)', function () {
    it('publishes the co-owner\'s share as total_savings, per-group totals and the share percent', function () {
        $primary = oneFigureUser();
        $coOwner = oneFigureUser();

        // 75/25 joint savings account recorded by the primary owner. The stored
        // ownership_percentage (75) is the PRIMARY owner's share: a surface that
        // applies it to the co-owner shows £30,000; iOS summing full balances
        // shows £40,000. The co-owner's share is £10,000.
        $joint = SavingsAccount::factory()->create([
            'user_id' => $primary->id,
            'joint_owner_id' => $coOwner->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 75,
            'account_type' => 'easy_access',
            'is_isa' => false,
            'current_balance' => 40_000,
            'interest_rate' => 4.0,
        ]);
        $current = SavingsAccount::factory()->create([
            'user_id' => $coOwner->id,
            'joint_owner_id' => null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'account_type' => 'current_account',
            'is_isa' => false,
            'current_balance' => 3_000,
        ]);
        // A savings-type account flagged as an ISA used to sit in BOTH the
        // Savings Accounts and the ISAs cards on the web page.
        $isa = SavingsAccount::factory()->create([
            'user_id' => $coOwner->id,
            'joint_owner_id' => null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'account_type' => 'savings_account',
            'is_isa' => true,
            'isa_type' => 'cash',
            'current_balance' => 5_000,
        ]);

        $data = $this->actingAs($coOwner, 'sanctum')->getJson('/api/savings')->assertOk()->json('data');

        expect((float) $data['analysis']['summary']['total_savings'])->toEqualWithDelta(18_000.0, 0.01);

        $groups = collect($data['analysis']['summary']['cash_groups'])->keyBy('key');
        expect($groups->keys()->all())->toBe(['current_accounts', 'savings_accounts', 'isas', 'nsi'])
            ->and((float) $groups['current_accounts']['total'])->toEqualWithDelta(3_000.0, 0.01)
            ->and($groups['current_accounts']['account_ids'])->toBe([$current->id])
            ->and((float) $groups['savings_accounts']['total'])->toEqualWithDelta(10_000.0, 0.01)
            ->and($groups['savings_accounts']['account_ids'])->toBe([$joint->id])
            ->and((float) $groups['isas']['total'])->toEqualWithDelta(5_000.0, 0.01)
            ->and($groups['isas']['account_ids'])->toBe([$isa->id])
            ->and((float) $groups['nsi']['total'])->toEqualWithDelta(0.0, 0.01);

        // The groups add up to the one total, so a page showing both cannot
        // disagree with itself.
        expect((float) $groups->sum('total'))->toEqualWithDelta((float) $data['analysis']['summary']['total_savings'], 0.01);

        $row = collect($data['accounts'])->firstWhere('id', $joint->id);
        expect((float) $row['user_share'])->toEqualWithDelta(10_000.0, 0.01)
            ->and((float) $row['user_share_percent'])->toEqualWithDelta(25.0, 0.01)
            ->and((float) $row['full_balance'])->toEqualWithDelta(40_000.0, 0.01);

        // The detail endpoint publishes the same share from the same code.
        $detail = $this->actingAs($coOwner, 'sanctum')->getJson("/api/savings/accounts/{$joint->id}")->assertOk()->json('data');
        expect((float) $detail['user_share_percent'])->toEqualWithDelta(25.0, 0.01)
            ->and((float) $detail['user_share'])->toEqualWithDelta(10_000.0, 0.01);
    });
});

describe('interest (item 24)', function () {
    it('publishes annual and monthly interest on the list and the detail endpoint', function () {
        $user = oneFigureUser();
        $account = SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'joint_owner_id' => null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'is_isa' => false,
            'current_balance' => 12_345,
            'interest_rate' => 3.75,
        ]);

        $row = collect($this->actingAs($user, 'sanctum')->getJson('/api/savings')->json('data.accounts'))->firstWhere('id', $account->id);
        $detail = $this->actingAs($user, 'sanctum')->getJson("/api/savings/accounts/{$account->id}")->json('data');

        // 12,345 x 3.75% = 462.94 a year, 38.58 a month.
        foreach ([$row, $detail] as $payload) {
            expect((float) $payload['annual_interest'])->toEqualWithDelta(462.94, 0.001)
                ->and((float) $payload['monthly_interest'])->toEqualWithDelta(38.58, 0.001);
        }
    });
});

describe('emergency fund (items 22, 23 and 35)', function () {
    it('publishes one target from resolved spending and the module month table, with its percentage and shortfall', function () {
        // Resolved spending is the cashflow profile (£2,500), not the raw column
        // (£1,000). The controller's old target was raw column x its own month
        // table (12 for unemployed) = £12,000; SavingsAgent's is £2,500 x 6.
        $user = oneFigureUser(['employment_status' => 'unemployed', 'monthly_expenditure' => 1_000]);
        ExpenditureProfile::factory()->create(['user_id' => $user->id, 'total_monthly_expenditure' => 2_500]);
        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'joint_owner_id' => null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'is_isa' => false,
            'current_balance' => 6_000,
        ]);

        $data = $this->actingAs($user, 'sanctum')->getJson('/api/savings')->assertOk()->json('data');
        $ef = $data['analysis']['emergency_fund'];

        expect($ef['target_months'])->toBe(6)
            ->and((float) $ef['target']['target_amount'])->toEqualWithDelta(15_000.0, 0.01)
            ->and((float) $ef['runway_months'])->toEqualWithDelta(2.4, 0.001)
            ->and((float) $ef['current_amount'])->toEqualWithDelta(6_000.0, 0.01)
            ->and((float) $ef['percent_of_target'])->toEqualWithDelta(40.0, 0.01)
            ->and((float) $ef['shortfall'])->toEqualWithDelta(9_000.0, 0.01)
            ->and((float) $ef['target_amount_by_months']['3'])->toEqualWithDelta(7_500.0, 0.01)
            ->and((float) $ef['target_amount_by_months']['12'])->toEqualWithDelta(30_000.0, 0.01)
            ->and((float) $data['analysis']['summary']['monthly_expenditure'])->toEqualWithDelta(2_500.0, 0.01);

        // The legacy key is the SAME object, not a second engine.
        expect($data['emergency_fund_target'])->toBe($ef['target']);

        // The dashboard card reads the same target and percentage.
        $card = app(MobileDashboardAggregator::class)->getAggregatedDashboard($user->id)['modules']['savings'];
        expect($card['emergency_fund_target_months'])->toBe(6)
            ->and((float) $card['emergency_fund_percent_of_target'])->toEqualWithDelta(40.0, 0.01);
    });

    it('uses the nine-month target for the self-employed on the page and the dashboard alike', function () {
        $user = oneFigureUser(['employment_status' => 'self_employed', 'monthly_expenditure' => 2_000]);
        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'joint_owner_id' => null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'is_isa' => false,
            'current_balance' => 9_000,
        ]);

        $ef = $this->actingAs($user, 'sanctum')->getJson('/api/savings')->json('data.analysis.emergency_fund');
        $card = app(MobileDashboardAggregator::class)->getAggregatedDashboard($user->id)['modules']['savings'];

        expect($ef['target_months'])->toBe(9)
            ->and($card['emergency_fund_target_months'])->toBe(9)
            ->and((float) $card['emergency_fund_percent_of_target'])->toEqualWithDelta((float) $ef['percent_of_target'], 0.001)
            ->and((float) $ef['percent_of_target'])->toEqualWithDelta(50.0, 0.01);
    });

    it('still publishes the runway and target when the readiness gate stops the full analysis', function () {
        // No date of birth: SavingsDataReadinessService blocks the analysis. The
        // runway needs only cash and spending, and `/m` and iOS showed one from
        // the raw column for this user, so the server must still answer.
        $user = oneFigureUser(['date_of_birth' => null, 'monthly_expenditure' => 1_500]);
        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'joint_owner_id' => null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'is_isa' => false,
            'current_balance' => 4_500,
        ]);

        $data = $this->actingAs($user, 'sanctum')->getJson('/api/savings')->assertOk()->json('data');

        expect((float) $data['analysis']['summary']['total_savings'])->toEqualWithDelta(4_500.0, 0.01)
            ->and((float) $data['analysis']['emergency_fund']['runway_months'])->toEqualWithDelta(3.0, 0.001)
            ->and((float) $data['analysis']['emergency_fund']['target']['target_amount'])->toEqualWithDelta(9_000.0, 0.01)
            ->and($data['emergency_fund_target'])->toBe($data['analysis']['emergency_fund']['target']);
    });
});

describe('protection (items 31 and 32)', function () {
    it('publishes cover_amount and annual_premium per policy', function () {
        $user = oneFigureUser();
        ProtectionProfile::factory()->create(['user_id' => $user->id, 'annual_income' => 60_000, 'number_of_dependents' => 0, 'dependents_ages' => []]);
        LifeInsurancePolicy::factory()->create([
            'user_id' => $user->id,
            'sum_assured' => 250_000,
            'premium_amount' => 30,
            'premium_frequency' => 'quarterly',
        ]);
        IncomeProtectionPolicy::factory()->create([
            'user_id' => $user->id,
            'benefit_amount' => 2_000,
            'benefit_frequency' => 'monthly',
            'premium_amount' => 30,
            'premium_frequency' => 'monthly',
        ]);

        $policies = $this->actingAs($user, 'sanctum')->getJson('/api/protection')->assertOk()->json('data.policies');

        expect((float) $policies['life_insurance'][0]['cover_amount'])->toEqualWithDelta(250_000.0, 0.01)
            // PremiumAnnualiser: quarterly x4. Surfaces read this rather than
            // re-annualising (the web policy screen and iOS had their own
            // switch, with weekly as x12 against the server's x52).
            ->and((float) $policies['life_insurance'][0]['annual_premium'])->toEqualWithDelta(120.0, 0.01)
            ->and((float) $policies['income_protection'][0]['cover_amount'])->toEqualWithDelta(2_000.0, 0.01)
            ->and((float) $policies['income_protection'][0]['annual_premium'])->toEqualWithDelta(360.0, 0.01);
    });

    it('gives the dashboard the same total cover as the protection page, employer cover included', function () {
        $user = oneFigureUser();
        ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 60_000,
            'number_of_dependents' => 0,
            'dependents_ages' => [],
            'death_in_service_multiple' => 4,
        ]);
        LifeInsurancePolicy::factory()->create(['user_id' => $user->id, 'sum_assured' => 100_000]);

        $pageCover = (float) $this->actingAs($user, 'sanctum')->getJson('/api/protection')->json('data.coverage_gaps.totals.cover');
        $card = app(MobileDashboardAggregator::class)->getAggregatedDashboard($user->id)['modules']['protection'];

        // £100,000 policy + 4 x £60,000 death in service.
        expect($pageCover)->toEqualWithDelta(340_000.0, 0.01)
            ->and((float) $card['total_coverage'])->toEqualWithDelta($pageCover, 0.01);
    });

    it('still gives the dashboard the page\'s total cover when the readiness gate stops the protection agent', function () {
        // No date of birth: the protection agent returns can_proceed false and
        // the card used to carry no cover at all ("Add your cover") while the
        // protection page listed £150,000.
        $user = oneFigureUser(['date_of_birth' => null]);
        ProtectionProfile::factory()->create(['user_id' => $user->id, 'annual_income' => 60_000, 'number_of_dependents' => 0, 'dependents_ages' => []]);
        LifeInsurancePolicy::factory()->create(['user_id' => $user->id, 'sum_assured' => 150_000]);

        $pageCover = (float) $this->actingAs($user, 'sanctum')->getJson('/api/protection')->json('data.coverage_gaps.totals.cover');
        $card = app(MobileDashboardAggregator::class)->getAggregatedDashboard($user->id)['modules']['protection'];

        expect($pageCover)->toEqualWithDelta(150_000.0, 0.01)
            ->and($card)->toHaveKey('total_coverage')
            ->and((float) $card['total_coverage'])->toEqualWithDelta($pageCover, 0.01);
    });
});
