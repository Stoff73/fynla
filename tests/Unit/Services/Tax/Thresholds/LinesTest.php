<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\Property;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\Thresholds\Lines\AdditionalRateLine;
use App\Services\Tax\Thresholds\Lines\HigherRateLine;
use App\Services\Tax\Thresholds\Lines\HighIncomeChildBenefitLine;
use App\Services\Tax\Thresholds\Lines\PensionsEnterEstateLine;
use App\Services\Tax\Thresholds\Lines\PersonalAllowanceTaperLine;
use App\Services\Tax\Thresholds\Lines\ResidenceBandTaperLine;
use App\Services\Tax\Thresholds\Lines\SalarySacrificeNiCapLine;
use App\Services\Tax\Thresholds\ThresholdContext;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // W-0532 — ChildBenefitService resolves the user's tier through TeaserGate, so
    // the rows must exist for a factory user to get a figure rather than the zero
    // position. The global Pest hook seeds these too; the seeder is firstOrCreate.
    $this->seed(TierConfigurationSeeder::class);
    Carbon::setTestNow('2026-09-21');
});

afterEach(function () {
    Carbon::setTestNow();
    Mockery::close();
});

function ctx(User $user): ThresholdContext
{
    return new ThresholdContext($user, app(IncomeDefinitionsService::class)->calculate($user->id));
}

describe('PersonalAllowanceTaperLine', function () {
    it('places a £112,400 parent inside the band with childcare in the cost and a pension lever', function () {
        $user = User::factory()->create(['annual_employment_income' => 112400, 'childcare' => 1000]);
        FamilyMember::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'relationship' => 'child', 'date_of_birth' => '2023-03-01']);

        $result = app(PersonalAllowanceTaperLine::class)->evaluate(ctx($user));

        expect($result)->not->toBeNull()
            ->and($result->position['over'])->toBeTrue()
            ->and($result->position['distance'])->toBe(12400.0)
            ->and($result->headline)->toBe('You are £12,400 into the 60% band')
            ->and(array_column($result->cost->benefits, 'label'))->toContain('Tax-Free Childcare')
            ->and($result->lever['amount'])->toBe(12400.0)
            // "Pay into", not "salary sacrifice": the cost carries no National
            // Insurance saving, so the title must not promise one.
            ->and($result->lever['title'])->toBe('Pay £12,400 into your pension')
            ->and($result->lever['downside'])->toContain('locked until you are 57');
    });

    it('does not apply to a £70,000 earner', function () {
        $user = User::factory()->create(['annual_employment_income' => 70000]);
        expect(app(PersonalAllowanceTaperLine::class)->evaluate(ctx($user)))->toBeNull();
    });

    it('names an upcoming vest in the lever downside', function () {
        $user = User::factory()->create(['annual_employment_income' => 105000]);
        InvestmentAccount::create(['user_id' => $user->id, 'account_type' => 'rsu', 'account_name' => 'Acme RSUs', 'provider' => 'Acme', 'current_value' => 0, 'scheme_status' => 'active', 'vesting_frequency_months' => 6, 'full_vest_date' => '2027-03-15', 'units_unvested' => 800, 'current_share_price' => 30]);

        $result = app(PersonalAllowanceTaperLine::class)->evaluate(ctx($user));

        expect($result->lever['downside'])->toContain('vest');
    });
});

describe('HighIncomeChildBenefitLine', function () {
    it('applies to a £66,000 parent receiving child benefit and prices the charge', function () {
        $user = User::factory()->create(['annual_employment_income' => 66000]);
        FamilyMember::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'relationship' => 'child', 'date_of_birth' => '2018-01-01', 'receives_child_benefit' => true]);

        $result = app(HighIncomeChildBenefitLine::class)->evaluate(ctx($user));

        expect($result)->not->toBeNull()
            ->and($result->position['distance'])->toBe(6000.0)
            ->and(collect($result->cost->benefits)->firstWhere('label', 'Child Benefit charge')['amount'])->toBeGreaterThan(0.0);
    });

    it('does not apply without a child receiving child benefit', function () {
        $user = User::factory()->create(['annual_employment_income' => 66000]);
        expect(app(HighIncomeChildBenefitLine::class)->evaluate(ctx($user)))->toBeNull();
    });

    it('does not apply when the tier does not carry the Child Benefit figure', function () {
        $user = User::factory()->create(['annual_employment_income' => 66000, 'tier' => 'free', 'is_admin' => false, 'is_preview_user' => false]);
        FamilyMember::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'relationship' => 'child', 'date_of_birth' => '2018-01-01', 'receives_child_benefit' => true]);

        // Withhold the capability on the resolved tier rather than stubbing the
        // gate, the way SoldCapabilitiesAreEnforcedTest does: a stubbed gate would
        // pass whether or not the line actually consults it.
        $config = TierConfiguration::where('tier', 'free')->firstOrFail();
        $matrix = $config->capability_matrix;
        $matrix['benefits_child'] = 'none';
        $config->update(['capability_matrix' => $matrix]);

        expect(app(HighIncomeChildBenefitLine::class)->evaluate(ctx($user)))->toBeNull();
    });
});

describe('band lines', function () {
    it('places a £48,000 earner below the higher-rate line within the window', function () {
        $user = User::factory()->create(['annual_employment_income' => 48000]);
        $result = app(HigherRateLine::class)->evaluate(ctx($user));
        expect($result->position['over'])->toBeFalse()->and($result->position['distance'])->toBe(-2270.0);
    });

    it('names the ISA move when the excess is dividends', function () {
        $user = User::factory()->create(['annual_employment_income' => 45000, 'annual_dividend_income' => 8000]);
        $result = app(HigherRateLine::class)->evaluate(ctx($user));
        expect($result->lever['title'])->toContain('ISA');
    });

    it('uses the Gift Aid extended limit for the additional-rate line', function () {
        $user = User::factory()->create(['annual_employment_income' => 135000, 'is_gift_aid' => true, 'annual_charitable_donations' => 12000]);
        $result = app(AdditionalRateLine::class)->evaluate(ctx($user));
        expect($result->range['from'])->toBe(125140.0 + 15000.0);
    });
});

describe('estate lines', function () {
    // The money figures on the estate lines are exercised in the Task 12 feature
    // test with a constructed estate. This one pins the guard only, which needs no
    // estate figures: it asserts the line withholds.
    it('withholds the residence band taper when there is no residence band to lose', function () {
        $user = User::factory()->create(['date_of_birth' => '1965-01-01']);
        Property::create(['user_id' => $user->id, 'property_type' => 'main_residence', 'ownership_type' => 'individual', 'current_value' => 2400000, 'address_line_1' => '1 High St', 'city' => 'London', 'postcode' => 'N1 1AA']);

        // A £2.4m estate is over the £2m taper threshold, but nothing is left to a
        // direct descendant, so the residence band is already nil and there is
        // nothing for the taper to take. The card would read "You lose £0".
        expect(app(ResidenceBandTaperLine::class)->evaluate(ctx($user)))->toBeNull();
    });
});

describe('date lines', function () {
    it('counts days to the NI cap for a £30,000 sacrificer', function () {
        $user = User::factory()->create(['annual_employment_income' => 145000, 'employment_income_basis' => 'gross']);
        DCPension::create(['user_id' => $user->id, 'scheme_name' => 'Work', 'pension_type' => 'occupational', 'current_fund_value' => 100000, 'annual_salary' => 145000, 'employee_contribution_percent' => 20.69, 'salary_sacrifice' => true]);

        $result = app(SalarySacrificeNiCapLine::class)->evaluate(ctx($user));

        expect($result)->not->toBeNull()
            ->and($result->position['unit'])->toBe('days')
            ->and($result->position['value'])->toBe((float) Carbon::today()->diffInDays(Carbon::parse('2027-04-06')))
            ->and($result->lever)->toBeNull();
    });

    it('applies the pensions-in-estate date to a DC holder', function () {
        $user = User::factory()->create();
        DCPension::create(['user_id' => $user->id, 'scheme_name' => 'SIPP', 'pension_type' => 'personal', 'current_fund_value' => 400000]);

        $result = app(PensionsEnterEstateLine::class)->evaluate(ctx($user));

        expect($result)->not->toBeNull()->and($result->position['unit'])->toBe('days');
    });
});
