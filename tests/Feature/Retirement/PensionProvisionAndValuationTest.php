<?php

declare(strict_types=1);

/**
 * W-0241 and W-0244, the two CSJ rulings of 2026-08-22, pinned together because
 * they are easy to mistake for each other and they are not the same statement.
 *
 * **W-0241 — valuation.** A Defined Benefit scheme is excluded from the net worth
 * CAPITAL figure, and every surface showing that figure says so. CSJ chose this
 * over adding a transfer value column or capitalising the accrued pension at a
 * multiple. No user's net worth figure moves.
 *
 * **W-0244 — provision.** What a user HAS is a fact about their pension records.
 * What they are AIMING AT is a fact about their retirement profile. The agent
 * used to return `success: false` with an empty data array when there was no
 * `retirement_profiles` row, so a household holding £500,000 of Defined
 * Contribution pensions, an NHS final salary scheme and a State Pension forecast
 * was told it had not started.
 *
 * **Both must be true of the same household at once:** a user whose only pension
 * is a final salary scheme HAS retirement provision AND HAS a £0 pensions capital
 * line. A test that could not tell those apart would pass on a broken build.
 *
 * `tests/CLAUDE.md` §4, fixture variants — two traps this suite is built to avoid:
 *
 * 1. The persona these items came from has **zero** `retirement_profiles` rows, so
 *    a fixture copied from it exercises only the no-target branch. Both branches
 *    are built here.
 * 2. **The right answer and the stale answer are the same number.** A Defined
 *    Benefit scheme read £0 under the phantom-column bug and reads £0 under the
 *    correct exclusion. Asserting "it is £0" therefore proves nothing, so the
 *    assertions below are on the disclosure flag and on figures that MOVE.
 */

use App\Agents\RetirementAgent;
use App\Constants\PensionDisclosure;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Mobile\MobileDashboardAggregator;
use App\Services\NetWorth\NetWorthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/**
 * A household whose entire retirement provision is an income, not a balance.
 *
 * **The employment income is deliberate.** `RetirementDataReadinessService`
 * blocks on missing income, and a blocked user takes a different branch of
 * `analyze()` from the one most of these tests are about. Omitting it would have
 * left this suite silently exercising the readiness gate while its names claimed
 * it was exercising the missing-profile path — the fixture-variant trap in
 * `tests/CLAUDE.md` §4, turned on the test author. The readiness branch is
 * covered explicitly, once, at the end.
 */
function definedBenefitOnlyUser(): User
{
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears(48)->toDateString(),
        'marital_status' => 'single',
        'annual_employment_income' => 62000,
    ]);

    DBPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'NHS Pension Scheme',
        'accrued_annual_pension' => 35000,
        'lump_sum_entitlement' => 105000,
        'inflation_protection' => 'none',
    ]);

    return $user;
}

describe('W-0244 — provision is a fact about the records, not about the target', function () {
    it('answers with the pension facts for a household that has never set a target', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50)->toDateString(),
            'annual_employment_income' => 85000,
        ]);
        DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 320000]);
        DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 180000]);

        expect(RetirementProfile::where('user_id', $user->id)->exists())->toBeFalse();

        $result = app(RetirementAgent::class)->analyze($user->id);

        expect($result['success'])->toBeTrue()
            ->and($result['data']['summary']['current_dc_value'])->toBe(500000.0)
            ->and($result['data']['summary']['total_pensions_count'])->toBe(2)
            ->and($result['data']['summary']['has_retirement_target'])->toBeFalse();
    });

    it('nulls every target-derived figure rather than reporting a zero income gap', function () {
        // A zero gap reads as "on track". An absent target has no gap at all, and
        // the difference between the two is the whole point of the item.
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50)->toDateString(),
            'annual_employment_income' => 85000,
        ]);
        DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 320000]);

        $summary = app(RetirementAgent::class)->analyze($user->id)['data']['summary'];

        expect($summary['income_gap'])->toBeNull()
            ->and($summary['target_retirement_income'])->toBeNull()
            ->and($summary['target_retirement_age'])->toBeNull()
            ->and($summary['projected_retirement_income'])->toBeNull()
            ->and($summary['years_to_retirement'])->toBeNull();
    });

    it('names the absent profile as the gap so the caller can ask for a target', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50)->toDateString(),
            'annual_employment_income' => 85000,
        ]);
        DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 320000]);

        $gaps = array_column(
            app(RetirementAgent::class)->analyze($user->id)['data']['missing_for_quality_advice'],
            'field'
        );

        expect($gaps)->toContain('retirement_profile');
    });

    it('reports secured income for a final salary household with no pot at all', function () {
        // The figure that MOVES. Under the old refusal this was unreachable; under
        // a correct build it is £35,000. Asserting the £0 pot instead would pass
        // against both.
        $user = definedBenefitOnlyUser();

        $summary = app(RetirementAgent::class)->analyze($user->id)['data']['summary'];

        expect($summary['guaranteed_annual_income'])->toBe(35000.0)
            ->and($summary['current_dc_value'])->toBe(0.0)
            ->and($summary['total_pensions_count'])->toBe(1);
    });

    it('shows that household as having retirement provision on the dashboard card', function () {
        $user = definedBenefitOnlyUser();

        $card = app(MobileDashboardAggregator::class)
            ->getAggregatedDashboard($user->id)['modules']['retirement'];

        expect($card['status'])->toBe('active')
            ->and($card['guaranteed_income'])->toBe(35000.0)
            ->and($card['total_pensions'])->toBe(1);
    });

    it('reports provision even when a blocking readiness check stops the analysis', function () {
        // The second path to the same wrong answer, and the one that would have
        // reinstated the bug quietly. This household has an NHS scheme and no income
        // on file, so `RetirementDataReadinessService` blocks on `income` and the
        // agent never reaches the projection at all. The facts are still facts, and
        // the card must still show them.
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(48)->toDateString(),
            'marital_status' => 'single',
        ]);
        DBPension::factory()->create([
            'user_id' => $user->id,
            'accrued_annual_pension' => 35000,
            'inflation_protection' => 'none',
        ]);

        $analysis = app(RetirementAgent::class)->analyze($user->id);
        $card = app(MobileDashboardAggregator::class)
            ->getAggregatedDashboard($user->id)['modules']['retirement'];

        expect($analysis['data']['can_proceed'])->toBeFalse()
            ->and($card['status'])->toBe('active')
            ->and($card['guaranteed_income'])->toBe(35000.0);
    });

    it('still tells a household with no pensions at all that it has not started', function () {
        // The other direction, and the reason this suite cannot pass vacuously: the
        // fix must not make every user look provisioned.
        $user = User::factory()->create(['date_of_birth' => now()->subYears(40)->toDateString()]);

        $card = app(MobileDashboardAggregator::class)
            ->getAggregatedDashboard($user->id)['modules']['retirement'];

        expect($card['status'])->toBe('not_configured')
            ->and($card)->not->toHaveKey('guaranteed_income');
    });

    it('keeps projecting against the target for a household that HAS one', function () {
        // The happy path still works, and `has_retirement_target` distinguishes it
        // from the branch above. Without this the suite could pass with the agent
        // permanently answering "no target".
        //
        // The employment income is load-bearing, not decoration: `checkIncome` is a
        // BLOCKING readiness check, and without it this user takes the
        // readiness-gated branch and never reaches the projection at all.
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50)->toDateString(),
            'annual_employment_income' => 85000,
        ]);
        DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 320000]);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'current_age' => 50,
            'target_retirement_age' => 65,
            'target_retirement_income' => 40000,
        ]);

        $summary = app(RetirementAgent::class)->analyze($user->id)['data']['summary'];

        expect($summary['has_retirement_target'])->toBeTrue()
            ->and($summary['target_retirement_income'])->toBe(40000.0)
            ->and($summary['income_gap'])->not->toBeNull()
            ->and($summary['years_to_retirement'])->toBe(15);
    });
});

describe('W-0241 — Defined Benefit is excluded from net worth, and the exclusion is disclosed', function () {
    it('discloses the exclusion on the dashboard net worth, which all three surfaces read', function () {
        // `GET /api/v1/mobile/dashboard` serves the web dashboard, `/m` and native
        // from one payload. It carried no disclosure flag at all before this, while
        // silently summing a `db_pensions.transfer_value` column that has never
        // existed. The flag is the assertion that MOVES; the £0 is not.
        $user = definedBenefitOnlyUser();

        $netWorth = app(MobileDashboardAggregator::class)
            ->getAggregatedDashboard($user->id)['net_worth'];

        expect($netWorth['has_db_pensions'])->toBeTrue()
            ->and($netWorth['breakdown']['assets']['pensions'])->toBe(0.0);
    });

    it('does not claim an exclusion for a household that holds no Defined Benefit scheme', function () {
        // Both directions again: a flag that is always true discloses nothing.
        $user = User::factory()->create(['date_of_birth' => now()->subYears(50)->toDateString()]);
        DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 250000]);

        $netWorth = app(MobileDashboardAggregator::class)
            ->getAggregatedDashboard($user->id)['net_worth'];

        expect($netWorth['has_db_pensions'])->toBeFalse()
            ->and($netWorth['breakdown']['assets']['pensions'])->toBe(250000.0);
    });

    it('gives the dashboard and /net-worth the same pensions figure and the same flag', function () {
        // One home. These two used to compute the pension contribution separately,
        // and the dashboard's copy read a column that does not exist.
        $user = definedBenefitOnlyUser();
        DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 90000]);

        $dashboard = app(MobileDashboardAggregator::class)->getAggregatedDashboard($user->id)['net_worth'];
        $netWorthPage = app(NetWorthService::class)->calculateNetWorth($user->fresh());

        expect($dashboard['breakdown']['assets']['pensions'])->toBe($netWorthPage['breakdown']['pensions'])
            ->and($dashboard['breakdown']['assets']['pensions'])->toBe(90000.0)
            ->and($dashboard['has_db_pensions'])->toBe($netWorthPage['has_db_pensions'])
            ->and($dashboard['has_db_pensions'])->toBeTrue();
    });

    it('leaves the Defined Contribution capital untouched when a Defined Benefit scheme is added', function () {
        // The ruling's own acceptance: **if a number moves, the change is wrong.**
        // Adding a £35,000-a-year scheme to a household must not alter its net worth
        // by a penny — and this asserts on the Defined Contribution total, which is
        // non-zero, rather than on the excluded scheme's zero.
        $user = User::factory()->create(['date_of_birth' => now()->subYears(50)->toDateString()]);
        DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 250000]);

        $before = app(NetWorthService::class)->calculateNetWorth($user->fresh());

        DBPension::factory()->create([
            'user_id' => $user->id,
            'accrued_annual_pension' => 35000,
            'lump_sum_entitlement' => 105000,
        ]);
        Cache::flush();

        $after = app(NetWorthService::class)->calculateNetWorth($user->fresh());

        expect($after['net_worth'])->toBe($before['net_worth'])
            ->and($after['breakdown']['pensions'])->toBe(250000.0)
            // What changes is what the user is TOLD, not what they are worth.
            ->and($before['has_db_pensions'])->toBeFalse()
            ->and($after['has_db_pensions'])->toBeTrue();
    });

    it('counts a State Pension forecast towards secured income without valuing it as capital', function () {
        // The same principle as the Defined Benefit scheme, and the two combine.
        $user = definedBenefitOnlyUser();
        StatePension::factory()->create([
            'user_id' => $user->id,
            'state_pension_forecast_annual' => 11502.40,
            'state_pension_age' => 67,
        ]);
        Cache::flush();

        $summary = app(RetirementAgent::class)->analyze($user->id)['data']['summary'];
        $netWorth = app(NetWorthService::class)->calculateNetWorth($user->fresh());

        expect($summary['guaranteed_annual_income'])->toBe(46502.40)
            ->and($netWorth['breakdown']['pensions'])->toBe(0.0);
    });
});

describe('W-0241 — the rejected capitalisation is gone from the detail view', function () {
    it('gives a Defined Benefit scheme no capital value, and its real income instead', function () {
        // `getAssetsSummaryWithDetails()` capitalised these at 20× the accrued
        // pension plus the lump sum — option 2 of W-0241, which CSJ rejected. The
        // £0 alone cannot prove the multiple is gone, because a broken read and a
        // correct exclusion both produce £0. **The £35,000 is the assertion that
        // moves**: it is what the scheme is worth to the user, it is what the
        // surfaces now render as "£35,000 a year", and it must survive.
        $user = definedBenefitOnlyUser();

        $pensions = app(NetWorthService::class)->getAssetsSummaryWithDetails($user->fresh())['pensions'];
        $scheme = $pensions['items'][0];

        expect($scheme['type'])->toBe('db')
            ->and($scheme['annual_pension'])->toBe(35000.0)
            ->and($scheme['value'])->toBe(0.0)
            // 35,000 × 20 + 105,000. If the multiple ever returns, this is the
            // number that comes back, so it is named explicitly.
            ->and($pensions['total_value'])->not->toBe(805000.0)
            ->and($pensions['total_value'])->toBe(0.0);
    });

    it('makes the asset list sum to the total printed above it', function () {
        // The user-visible symptom, asserted as arithmetic rather than as a figure.
        // Before this, Sarah's list summed to £1,666,780 against a stated £861,780
        // and the category percentages totalled 193% — on the same screen as the
        // sentence saying Defined Benefit pensions are excluded.
        $user = definedBenefitOnlyUser();
        DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 90000]);
        Cache::flush();

        $overview = app(NetWorthService::class)->calculateNetWorth($user->fresh());
        $detailed = app(NetWorthService::class)->getAssetsSummaryWithDetails($user->fresh());

        $listTotal = 0.0;
        foreach (['property', 'investments', 'pensions', 'cash', 'business', 'chattels'] as $category) {
            $listTotal += (float) ($detailed[$category]['total_value'] ?? 0);
        }

        expect(round($listTotal, 2))->toBe(round((float) $overview['total_assets'], 2))
            ->and($detailed['pensions']['total_value'])->toBe(90000.0);
    });

    it('sends the disclosure text with the figure, from its one home', function () {
        // Rule 20: the sentence lives in `PensionDisclosure` and travels with the
        // figure it qualifies, so web, `/m` and native cannot drift into three
        // different sentences. Asserting against the constant — rather than against
        // a copy of the string typed into the test — is what makes that true.
        $user = definedBenefitOnlyUser();

        $overview = app(NetWorthService::class)->calculateNetWorth($user->fresh());
        $detailed = app(NetWorthService::class)->getAssetsSummaryWithDetails($user->fresh());
        $dashboard = app(MobileDashboardAggregator::class)->getAggregatedDashboard($user->id);

        expect($overview['db_pension_disclosure'])->toBe(PensionDisclosure::DEFINED_BENEFIT_EXCLUDED)
            ->and($detailed['pensions']['disclosure'])->toBe(PensionDisclosure::DEFINED_BENEFIT_EXCLUDED)
            ->and($dashboard['net_worth']['db_pension_disclosure'])->toBe(PensionDisclosure::DEFINED_BENEFIT_EXCLUDED)
            ->and($detailed['pensions']['subtitle'])->toBe(PensionDisclosure::PENSION_CAPITAL_SUBTITLE);
    });

    it('says nothing about Defined Benefit schemes to a household that holds none', function () {
        // Both directions. A disclosure shown to everyone explains nothing, and a
        // flag that is always true discloses nothing.
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50)->toDateString(),
            'annual_employment_income' => 85000,
        ]);
        DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 250000]);

        $overview = app(NetWorthService::class)->calculateNetWorth($user->fresh());
        $detailed = app(NetWorthService::class)->getAssetsSummaryWithDetails($user->fresh());
        $dashboard = app(MobileDashboardAggregator::class)->getAggregatedDashboard($user->id);

        expect($overview['db_pension_disclosure'])->toBeNull()
            ->and($detailed['pensions']['disclosure'])->toBeNull()
            ->and($detailed['pensions']['has_db_pensions'])->toBeFalse()
            ->and($dashboard['net_worth']['db_pension_disclosure'])->toBeNull()
            // The Defined Contribution figure is untouched by any of this.
            ->and($detailed['pensions']['total_value'])->toBe(250000.0);
    });

    it('leaves the headline net worth untouched by the capitalisation removal', function () {
        // The one figure that must not move on any surface, for any user.
        $user = definedBenefitOnlyUser();
        DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 90000]);
        Cache::flush();

        $overview = app(NetWorthService::class)->calculateNetWorth($user->fresh());
        $dashboard = app(MobileDashboardAggregator::class)->getAggregatedDashboard($user->id);

        expect($overview['breakdown']['pensions'])->toBe(90000.0)
            ->and($dashboard['net_worth']['breakdown']['assets']['pensions'])->toBe(90000.0)
            ->and($dashboard['net_worth']['total'])->toBe($overview['net_worth']);
    });
});
