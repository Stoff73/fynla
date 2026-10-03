<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\TaxConfigService;
use App\Services\UserProfile\UserProfileService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/**
 * The Income tab's take-home is the one home for Income Tax, National
 * Insurance and take-home (CSJ 2026-10-02, one income figure). It is worked
 * out on the Income page's parts: other income and recorded-or-estimated
 * interest are taxed, National Insurance stops at State Pension age, and Gift
 * Aid extends the bands.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-01');
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function incomeTab(User $user): array
{
    return app(UserProfileService::class)->incomeAndTaxFor($user->fresh());
}

function earner(array $attributes = []): User
{
    return User::factory()->create($attributes + [
        'date_of_birth' => '1985-05-01',
        'employment_status' => 'employed',
        'annual_employment_income' => 40000,
        'annual_self_employment_income' => 0,
        'annual_dividend_income' => 0,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
        'annual_trust_income' => 0,
        'marital_status' => 'single',
    ]);
}

it('taxes other income, which it used to leave out', function (): void {
    $basicRate = (float) app(TaxConfigService::class)->getIncomeTax()['bands'][0]['rate'];

    $without = incomeTab(earner());
    $with = incomeTab(earner(['annual_other_income' => 5000]));

    expect($with['income_tax'] - $without['income_tax'])->toEqualWithDelta(5000 * $basicRate, 0.01)
        ->and($with['gross_income'])->toEqualWithDelta($without['gross_income'] + 5000, 0.01)
        ->and($with['national_insurance'])->toEqualWithDelta($without['national_insurance'], 0.01);
});

it('charges no Class 1 National Insurance past State Pension age (SSCBA 1992 s6(3))', function (): void {
    expect(incomeTab(earner(['date_of_birth' => '1958-03-10']))['national_insurance'])->toBe(0.0)
        ->and(incomeTab(earner())['national_insurance'])->toBeGreaterThan(0.0);
});

it('still charges Class 4 in the tax year State Pension age is reached', function (): void {
    // Born 1 August 1960 with a statement saying 66: State Pension age on
    // 1 August 2026, after this tax year began, so Class 4 is still due for
    // it; Class 1 on pay stops at once.
    $selfEmployed = earner(['date_of_birth' => '1960-08-01', 'annual_employment_income' => 0, 'annual_self_employment_income' => 30000]);
    $employed = earner(['date_of_birth' => '1960-08-01']);
    foreach ([$selfEmployed, $employed] as $person) {
        StatePension::factory()->create(['user_id' => $person->id, 'state_pension_age' => 66, 'already_receiving' => false]);
    }

    expect(incomeTab($selfEmployed)['national_insurance'])->toBeGreaterThan(0.0)
        ->and(incomeTab($employed)['national_insurance'])->toBe(0.0);
});

it('extends the bands for Gift Aid rather than taking it off income (ITA 2007 s414)', function (): void {
    $bands = app(TaxConfigService::class)->getIncomeTax()['bands'];
    $higherRelief = (float) $bands[1]['rate'] - (float) $bands[0]['rate'];

    $without = incomeTab(earner(['annual_employment_income' => 70000]));
    // £4,000 given is £5,000 gross: £5,000 more is taxed at the basic rate.
    $with = incomeTab(earner(['annual_employment_income' => 70000, 'annual_charitable_donations' => 4000, 'is_gift_aid' => true]));

    expect($without['income_tax'] - $with['income_tax'])->toEqualWithDelta(5000 * $higherRelief, 0.01)
        ->and($with['gross_income'])->toEqualWithDelta($without['gross_income'], 0.01);
});

describe('interest', function () {
    it('is what the non-ISA accounts pay, at the user\'s share, when none is recorded', function (): void {
        $user = earner();
        $partner = User::factory()->create();
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 50000, 'interest_rate' => 4.0, 'is_isa' => false, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 20000, 'interest_rate' => 5.0, 'is_isa' => true, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
        SavingsAccount::factory()->create(['user_id' => $partner->id, 'current_balance' => 10000, 'interest_rate' => 3.0, 'is_isa' => false, 'ownership_type' => 'joint', 'joint_owner_id' => $user->id, 'ownership_percentage' => 50]);

        $definitions = app(IncomeDefinitionsService::class)->calculate($user->id);
        $tab = incomeTab($user);

        // £2,000 own + £150 (half of £300 joint); the ISA's interest is exempt.
        expect($definitions['components']['interest'])->toEqualWithDelta(2150.0, 0.01)
            ->and($definitions['interest_basis'])->toBe('estimated')
            ->and($tab['income_parts']['interest'])->toEqualWithDelta(2150.0, 0.01)
            ->and($tab['gross_income'])->toEqualWithDelta((float) $definitions['total_income'], 0.01);
    });

    it('is the recorded figure when there is one', function (): void {
        $user = earner(['annual_interest_income' => 300]);
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 50000, 'interest_rate' => 4.0, 'is_isa' => false, 'ownership_type' => 'individual', 'joint_owner_id' => null]);

        $definitions = app(IncomeDefinitionsService::class)->calculate($user->id);

        expect($definitions['components']['interest'])->toBe(300.0)
            ->and($definitions['interest_basis'])->toBe('recorded');
    });

    it('says on the income rows when it was worked out from the accounts', function (): void {
        $user = earner();
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 50000, 'interest_rate' => 4.0, 'is_isa' => false, 'ownership_type' => 'individual', 'joint_owner_id' => null]);

        $row = collect(app(UserProfileService::class)->getCompleteProfile($user->fresh())['income_summary']['user']['sources'])
            ->firstWhere('key', 'interest');

        expect($row['amount'])->toEqualWithDelta(2000.0, 0.01)
            ->and($row['detail'])->toBe('worked out from the savings accounts');
    });
});
