<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\Benefits\ChildBenefitService;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\Thresholds\ChildcareEntitlements;
use App\Services\Tax\Thresholds\Lines\AdditionalRateLine;
use App\Services\Tax\Thresholds\Lines\HigherRateLine;
use App\Services\Tax\Thresholds\Lines\HighIncomeChildBenefitLine;
use App\Services\Tax\Thresholds\Lines\PensionsEnterEstateLine;
use App\Services\Tax\Thresholds\Lines\PersonalAllowanceTaperLine;
use App\Services\Tax\Thresholds\Lines\ResidenceBandTaperLine;
use App\Services\Tax\Thresholds\Lines\SalarySacrificeNiCapLine;
use App\Services\Tax\Thresholds\Lines\TaperedAnnualAllowanceLine;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\TaxConfigService;
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

function thresholdLineContext(User $user): ThresholdContext
{
    return new ThresholdContext($user, app(IncomeDefinitionsService::class)->calculate($user->id));
}

describe('PersonalAllowanceTaperLine', function () {
    it('places a £112,400 parent inside the band with childcare in the cost and a pension lever', function () {
        $user = User::factory()->create(['annual_employment_income' => 112400, 'childcare' => 1000]);
        FamilyMember::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'relationship' => 'child', 'date_of_birth' => '2023-03-01']);

        $result = app(PersonalAllowanceTaperLine::class)->evaluate(thresholdLineContext($user));

        // The two childcare entitlements are derived from the seeded config here, not
        // written in: a test that restated the rates would pass over a wrong rate.
        $childcare = array_sum(array_column(app(ChildcareEntitlements::class)->for($user), 'amount'));
        // 40% on the £12,400 slice plus 40% on the £6,200 of Personal Allowance it
        // withdraws, which is the 60% the headline names.
        $expectedTotal = round(7440.0 + $childcare, 2);

        expect($result)->not->toBeNull()
            ->and($result->position['over'])->toBeTrue()
            ->and($result->position['distance'])->toBe(12400.0)
            ->and($result->headline)->toBe('You are £12,400 into the 60% band')
            ->and(array_column($result->cost->benefits, 'label'))->toContain('Tax-Free Childcare')
            ->and($result->cost->incomeTax)->toBe(7440.0)
            ->and($childcare)->toBeGreaterThan(0.0)
            ->and($result->cost->total())->toBe($expectedTotal)
            ->and($result->lever['amount'])->toBe(12400.0)
            // `recovers` must be the price of the move on the button, not of a
            // larger one.
            ->and($result->lever['recovers'])->toBe($expectedTotal)
            // "Pay into", not "salary sacrifice": the cost carries no National
            // Insurance saving, so the title must not promise one.
            ->and($result->lever['title'])->toBe('Pay £12,400 into your pension')
            ->and($result->lever['downside'])->toContain('locked until you are 55');
    });

    it('does not apply to a £70,000 earner', function () {
        $user = User::factory()->create(['annual_employment_income' => 70000]);
        expect(app(PersonalAllowanceTaperLine::class)->evaluate(thresholdLineContext($user)))->toBeNull();
    });

    it('says the allowance is gone once past the top of the band', function () {
        $user = User::factory()->create(['annual_employment_income' => 200000]);

        $result = app(PersonalAllowanceTaperLine::class)->evaluate(thresholdLineContext($user));

        expect($result->headline)->toBe('You are past the 60% band')
            ->and($result->body)->toContain('your Personal Allowance is gone entirely')
            ->and($result->body)->toContain('45%')
            // Still over the line, and the distance is still measured from it.
            ->and($result->position['over'])->toBeTrue()
            ->and($result->position['distance'])->toBe(100000.0)
            ->and($result->lever)->not->toBeNull();
    });

    it('sizes the lever by the money purchase allowance and withholds childcare when it stops short', function () {
        $user = User::factory()->create(['annual_employment_income' => 120000]);
        FamilyMember::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'relationship' => 'child', 'date_of_birth' => '2023-03-01']);
        // Flexibly accessed, so the Annual Allowance is the £10,000 money purchase one
        // and the contribution cannot reach the £20,000 needed to get back under.
        DCPension::create(['user_id' => $user->id, 'scheme_name' => 'SIPP', 'pension_type' => 'personal', 'current_fund_value' => 250000, 'has_flexibly_accessed' => true]);

        $result = app(PersonalAllowanceTaperLine::class)->evaluate(thresholdLineContext($user));

        expect($result->lever['amount'])->toBe(10000.0)
            ->and($result->lever['recovers'])->toBe($result->cost->total())
            ->and(array_column($result->cost->benefits, 'label'))->not->toContain('Tax-Free Childcare')
            ->and($result->body)->toContain('gets you part of the way');
    });

    it('names an upcoming vest in the lever downside', function () {
        $user = User::factory()->create(['annual_employment_income' => 105000]);
        InvestmentAccount::create(['user_id' => $user->id, 'account_type' => 'rsu', 'account_name' => 'Acme RSUs', 'provider' => 'Acme', 'current_value' => 0, 'scheme_status' => 'active', 'vesting_frequency_months' => 6, 'full_vest_date' => '2027-03-15', 'units_unvested' => 800, 'current_share_price' => 30]);

        $result = app(PersonalAllowanceTaperLine::class)->evaluate(thresholdLineContext($user));

        expect($result->lever['downside'])->toContain('vest');
    });
});

describe('HighIncomeChildBenefitLine', function () {
    it('applies to a £66,000 parent receiving child benefit and prices the charge', function () {
        $user = User::factory()->create(['annual_employment_income' => 66000]);
        FamilyMember::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'relationship' => 'child', 'date_of_birth' => '2018-01-01', 'receives_child_benefit' => true]);

        $result = app(HighIncomeChildBenefitLine::class)->evaluate(thresholdLineContext($user));

        // 30% of the benefit: £6,000 over the line at 1% per £200. The benefit itself
        // comes from the service, so the test does not restate the weekly rates.
        $benefit = (float) app(ChildBenefitService::class)->calculateChildBenefitPosition($user, 66000.0)['benefit']['annual_amount'];
        $expectedCharge = round($benefit * 0.30, 2);

        expect($result)->not->toBeNull()
            ->and($result->position['distance'])->toBe(6000.0)
            ->and($benefit)->toBeGreaterThan(0.0)
            ->and(collect($result->cost->benefits)->firstWhere('label', 'Child Benefit charge')['amount'])->toBe($expectedCharge)
            // £6,000 at the higher rate, the slice the contribution would remove.
            ->and($result->cost->incomeTax)->toBe(2400.0)
            ->and($result->cost->total())->toBe(round(2400.0 + $expectedCharge, 2))
            ->and($result->lever['recovers'])->toBe($result->cost->total());
    });

    it('says the benefit is all repaid once past the top of the band', function () {
        $user = User::factory()->create(['annual_employment_income' => 200000]);
        FamilyMember::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'relationship' => 'child', 'date_of_birth' => '2018-01-01', 'receives_child_benefit' => true]);

        $result = app(HighIncomeChildBenefitLine::class)->evaluate(thresholdLineContext($user));

        expect($result->headline)->toBe('You are past the Child Benefit charge band')
            ->and($result->body)->toBe('Above £80,000 all of your Child Benefit is repaid.')
            ->and($result->position['over'])->toBeTrue()
            ->and($result->position['distance'])->toBe(140000.0);
    });

    it('does not apply without a child receiving child benefit', function () {
        $user = User::factory()->create(['annual_employment_income' => 66000]);
        expect(app(HighIncomeChildBenefitLine::class)->evaluate(thresholdLineContext($user)))->toBeNull();
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

        expect(app(HighIncomeChildBenefitLine::class)->evaluate(thresholdLineContext($user)))->toBeNull();
    });
});

describe('band lines', function () {
    it('places a £48,000 earner below the higher-rate line within the window', function () {
        $user = User::factory()->create(['annual_employment_income' => 48000]);
        $result = app(HigherRateLine::class)->evaluate(thresholdLineContext($user));
        expect($result->position['over'])->toBeFalse()->and($result->position['distance'])->toBe(-2270.0);
    });

    it('names the ISA move when the excess is dividends', function () {
        $user = User::factory()->create(['annual_employment_income' => 45000, 'annual_dividend_income' => 8000]);
        $result = app(HigherRateLine::class)->evaluate(thresholdLineContext($user));
        expect($result->lever['title'])->toContain('ISA')
            ->and($result->lever['downside'])->toContain('you have £20,000 of it left')
            // Under top-slicing a dividend excess never lands in income tax, so the
            // explanation must not call it that.
            ->and($result->explanation)->not->toContain('Income tax is')
            ->and($result->explanation)->toContain('Moving it into an ISA');
    });

    it('falls back to the pension when this year\'s ISA allowance is already used', function () {
        $user = User::factory()->create(['annual_employment_income' => 45000, 'annual_dividend_income' => 8000]);
        $allowance = (float) app(TaxConfigService::class)->getISAAllowances()['annual_allowance'];
        SavingsAccount::create([
            'user_id' => $user->id, 'account_name' => 'Cash ISA', 'provider' => 'Bank',
            'account_type' => 'cash_isa', 'is_isa' => true, 'current_balance' => $allowance,
            'isa_subscription_year' => app(TaxConfigService::class)->getTaxYear(),
            'isa_subscription_amount' => $allowance,
        ]);

        $result = app(HigherRateLine::class)->evaluate(thresholdLineContext($user));

        // There is savings income to move, but nowhere left to move it to.
        expect($result->lever['mechanism'])->toBe('pension')
            ->and($result->lever['title'])->toContain('into your pension')
            ->and($result->explanation)->toContain('Income tax is');
    });

    it('uses the Gift Aid extended limit for the additional-rate line', function () {
        $user = User::factory()->create(['annual_employment_income' => 135000, 'is_gift_aid' => true, 'annual_charitable_donations' => 12000]);
        $result = app(AdditionalRateLine::class)->evaluate(thresholdLineContext($user));
        expect($result->range['from'])->toBe(125140.0 + 15000.0);
    });
});

describe('ThresholdCopy', function () {
    it('says you are on the line rather than £0 under it', function () {
        // 40p either side of the line rounds to £0, and "You are £0 under the 60%
        // band" reads as a bug to the one reader standing exactly on it.
        expect(ThresholdCopy::into(0.4, '60% band'))->toBe('You are on the 60% band line')
            ->and(ThresholdCopy::into(-0.4, '60% band'))->toBe('You are on the 60% band line')
            ->and(ThresholdCopy::into(-600.0, '60% band'))->toBe('You are £600 under the 60% band');
    });

    it('says the estate is on the nil rate band line rather than £0 under it', function () {
        expect(ThresholdCopy::estate(0.4))->toBe('Your estate is on the nil rate band line')
            ->and(ThresholdCopy::estate(-0.4))->toBe('Your estate is on the nil rate band line')
            ->and(ThresholdCopy::estate(5000.0))->toBe('Your estate is £5,000 over the nil rate band');
    });
});

describe('TaperedAnnualAllowanceLine', function () {
    it('states the allowance the definitions computed, and prices what was lost', function () {
        $user = User::factory()->create(['annual_employment_income' => 300000]);
        DCPension::create(['user_id' => $user->id, 'scheme_name' => 'Work', 'pension_type' => 'occupational', 'current_fund_value' => 500000, 'annual_salary' => 300000, 'employee_contribution_percent' => 5, 'employer_contribution_percent' => 10]);

        $definitions = app(IncomeDefinitionsService::class)->calculate($user->id);
        $result = app(TaperedAnnualAllowanceLine::class)->evaluate(new ThresholdContext($user, $definitions));

        // Adjusted income £330,000: total income of £300,000 with the employee
        // contribution added back, plus £30,000 of employer contributions. £70,000
        // over the £260,000 limit withdraws £35,000, leaving £25,000.
        $allowances = $definitions['adjusted_allowances'];
        $lost = $allowances['pension_annual_allowance_full'] - $allowances['pension_annual_allowance'];

        expect($result)->not->toBeNull()
            ->and($definitions['adjusted_income'])->toBe(330000.0)
            ->and($allowances['pension_annual_allowance'])->toBe(25000.0)
            ->and($lost)->toBe(35000.0)
            ->and($result->position['value'])->toBe(330000.0)
            ->and($result->explanation)->toBe('Your allowance this year is £25,000 against the full £60,000.')
            // £35,000 of allowance at the 45% this earner pays.
            ->and($result->cost->total())->toBe(15750.0)
            ->and($result->lever)->toBeNull();
    });

    it('does not apply below the threshold income gate', function () {
        $user = User::factory()->create(['annual_employment_income' => 230000]);
        expect(app(TaperedAnnualAllowanceLine::class)->evaluate(thresholdLineContext($user)))->toBeNull();
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
        expect(app(ResidenceBandTaperLine::class)->evaluate(thresholdLineContext($user)))->toBeNull();
    });

    it('withholds the residence band taper below the taper threshold', function () {
        $user = User::factory()->create(['date_of_birth' => '1965-01-01']);
        FamilyMember::factory()->child()->create(['user_id' => $user->id, 'date_of_birth' => '1995-01-01']);
        Property::create(['user_id' => $user->id, 'property_type' => 'main_residence', 'ownership_type' => 'individual', 'current_value' => 1800000, 'address_line_1' => '2 High St', 'city' => 'London', 'postcode' => 'N1 1AB']);

        // Under £2,000,000, so no residence band has been taken away and the taper
        // has nothing to report even with a direct descendant in the household.
        expect(app(ResidenceBandTaperLine::class)->evaluate(thresholdLineContext($user)))->toBeNull();
    });
});

describe('date lines', function () {
    it('counts days to the NI cap for a £30,000 sacrificer', function () {
        $user = User::factory()->create(['annual_employment_income' => 145000, 'employment_income_basis' => 'gross']);
        DCPension::create(['user_id' => $user->id, 'scheme_name' => 'Work', 'pension_type' => 'occupational', 'current_fund_value' => 100000, 'annual_salary' => 145000, 'employee_contribution_percent' => 20.69, 'salary_sacrifice' => true]);

        $result = app(SalarySacrificeNiCapLine::class)->evaluate(thresholdLineContext($user));

        // The whole sacrifice sits above the upper earnings limit, where the employee
        // rate is 2%, not the 8% main rate. Priced through the calculator, so the
        // bands decide; a flat main rate overstated this fourfold.
        $sacrificed = 145000 * 0.2069;
        $expected = round(($sacrificed - 2000) * 0.02, 2);
        $ifMainRateWereUsed = round(($sacrificed - 2000) * 0.08, 2);

        expect($result)->not->toBeNull()
            ->and($result->position['unit'])->toBe('days')
            ->and($result->position['value'])->toBe((float) Carbon::today()->diffInDays(Carbon::parse('2027-04-06')))
            ->and($result->lever)->toBeNull()
            ->and($result->cost->total())->toBe($expected)
            ->and($result->cost->total())->not->toBe($ifMainRateWereUsed)
            ->and($result->cost->benefits[0]['detail'])->toBe('National Insurance on the £28,001 above the £2,000 cap');
    });

    it('applies the pensions-in-estate date to a DC holder', function () {
        $user = User::factory()->create();
        DCPension::create(['user_id' => $user->id, 'scheme_name' => 'SIPP', 'pension_type' => 'personal', 'current_fund_value' => 400000]);

        $result = app(PensionsEnterEstateLine::class)->evaluate(thresholdLineContext($user));

        expect($result)->not->toBeNull()->and($result->position['unit'])->toBe('days');
    });
});
