<?php

declare(strict_types=1);

use App\Models\DCPension;
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

function takeHomeTab(User $user): array
{
    return app(UserProfileService::class)->incomeAndTaxFor($user->fresh());
}

function takeHomeEarner(array $attributes = []): User
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

    $without = takeHomeTab(takeHomeEarner());
    $with = takeHomeTab(takeHomeEarner(['annual_other_income' => 5000]));

    expect($with['income_tax'] - $without['income_tax'])->toEqualWithDelta(5000 * $basicRate, 0.01)
        ->and($with['gross_income'])->toEqualWithDelta($without['gross_income'] + 5000, 0.01)
        ->and($with['national_insurance'])->toEqualWithDelta($without['national_insurance'], 0.01);
});

it('charges no Class 1 National Insurance past State Pension age (SSCBA 1992 s6(3))', function (): void {
    expect(takeHomeTab(takeHomeEarner(['date_of_birth' => '1958-03-10']))['national_insurance'])->toBe(0.0)
        ->and(takeHomeTab(takeHomeEarner())['national_insurance'])->toBeGreaterThan(0.0);
});

it('still charges Class 4 in the tax year State Pension age is reached', function (): void {
    // Born 1 August 1960 with a statement saying 66: State Pension age on
    // 1 August 2026, after this tax year began, so Class 4 is still due.
    $selfEmployed = takeHomeEarner(['date_of_birth' => '1960-08-01', 'annual_employment_income' => 0, 'annual_self_employment_income' => 30000]);
    StatePension::factory()->create(['user_id' => $selfEmployed->id, 'state_pension_age' => 66, 'already_receiving' => false]);

    expect(takeHomeTab($selfEmployed)['national_insurance'])->toBeGreaterThan(0.0);
});

it('charges Class 1 only on the pay received before State Pension age (SSCBA 1992 s6(3))', function (): void {
    // Reached 1 August 2026. Paid on the 28th: April to July are liable, 4 of
    // the year's 12 paydays. With no payday recorded, the days before it count.
    $full = takeHomeTab(takeHomeEarner())['national_insurance'];
    $paidMonthly = takeHomeEarner(['date_of_birth' => '1960-08-01', 'payday_day_of_month' => 28]);
    $noPayday = takeHomeEarner(['date_of_birth' => '1960-08-01', 'payday_day_of_month' => null]);
    foreach ([$paidMonthly, $noPayday] as $person) {
        StatePension::factory()->create(['user_id' => $person->id, 'state_pension_age' => 66, 'already_receiving' => false]);
    }

    $start = Carbon::parse(app(TaxConfigService::class)->getEffectiveFrom());
    $daysShare = $start->diffInDays(Carbon::parse('2026-08-01')) / $start->diffInDays($start->copy()->addYear());

    expect(takeHomeTab($paidMonthly)['national_insurance'])->toEqualWithDelta($full * 4 / 12, 0.05)
        ->and(takeHomeTab($noPayday)['national_insurance'])->toEqualWithDelta($full * $daysShare, 0.05);
});

it('extends the bands for Gift Aid rather than taking it off income (ITA 2007 s414)', function (): void {
    $bands = app(TaxConfigService::class)->getIncomeTax()['bands'];
    $higherRelief = (float) $bands[1]['rate'] - (float) $bands[0]['rate'];

    $without = takeHomeTab(takeHomeEarner(['annual_employment_income' => 70000]));
    // £4,000 given is £5,000 gross: £5,000 more is taxed at the basic rate.
    $with = takeHomeTab(takeHomeEarner(['annual_employment_income' => 70000, 'annual_charitable_donations' => 4000, 'is_gift_aid' => true]));

    expect($without['income_tax'] - $with['income_tax'])->toEqualWithDelta(5000 * $higherRelief, 0.01)
        ->and($with['gross_income'])->toEqualWithDelta($without['gross_income'], 0.01);
});

describe('interest', function () {
    it('is what the non-ISA accounts pay, at the user\'s share, when none is recorded', function (): void {
        $user = takeHomeEarner();
        $partner = User::factory()->create();
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 50000, 'interest_rate' => 4.0, 'is_isa' => false, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 20000, 'interest_rate' => 5.0, 'is_isa' => true, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
        SavingsAccount::factory()->create(['user_id' => $partner->id, 'current_balance' => 10000, 'interest_rate' => 3.0, 'is_isa' => false, 'ownership_type' => 'joint', 'joint_owner_id' => $user->id, 'ownership_percentage' => 50]);

        $definitions = app(IncomeDefinitionsService::class)->calculate($user->id);
        $tab = takeHomeTab($user);

        // £2,000 own + £150 (half of £300 joint); the ISA's interest is exempt.
        expect($definitions['components']['interest'])->toEqualWithDelta(2150.0, 0.01)
            ->and($definitions['interest_basis'])->toBe('estimated')
            ->and($tab['income_parts']['interest'])->toEqualWithDelta(2150.0, 0.01)
            ->and($tab['gross_income'])->toEqualWithDelta((float) $definitions['total_income'], 0.01);
    });

    it('is the recorded figure when there is one', function (): void {
        $user = takeHomeEarner(['annual_interest_income' => 300]);
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 50000, 'interest_rate' => 4.0, 'is_isa' => false, 'ownership_type' => 'individual', 'joint_owner_id' => null]);

        $definitions = app(IncomeDefinitionsService::class)->calculate($user->id);

        expect($definitions['components']['interest'])->toBe(300.0)
            ->and($definitions['interest_basis'])->toBe('recorded');
    });

    it('says on the income rows when it was worked out from the accounts', function (): void {
        $user = takeHomeEarner();
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 50000, 'interest_rate' => 4.0, 'is_isa' => false, 'ownership_type' => 'individual', 'joint_owner_id' => null]);

        $row = collect(app(UserProfileService::class)->getCompleteProfile($user->fresh())['income_summary']['user']['sources'])
            ->firstWhere('key', 'interest');

        expect($row['amount'])->toEqualWithDelta(2000.0, 0.01)
            ->and($row['detail'])->toBe('worked out from the savings accounts');
    });
});

describe('salary sacrifice (CSJ 2026-10-03: one gross figure, then the deductions)', function () {
    function takeHomeSacrificer(array $attributes): User
    {
        $user = takeHomeEarner($attributes);
        DCPension::factory()->create([
            'user_id' => $user->id, 'scheme_type' => 'workplace', 'pension_type' => 'occupational',
            'salary_sacrifice' => true, 'monthly_contribution_amount' => 250,
            'employee_contribution_percent' => 0, 'employer_contribution_percent' => 0,
        ]);

        return $user->fresh();
    }

    it('starts from the same gross pay whichever way the pay was recorded, and deducts the sacrifice once', function () {
        $gross = takeHomeTab(takeHomeSacrificer(['annual_employment_income' => 60000, 'employment_income_basis' => 'gross']));
        $after = takeHomeTab(takeHomeSacrificer(['annual_employment_income' => 57000, 'employment_income_basis' => 'post_sacrifice']));

        foreach ([$gross, $after] as $tab) {
            expect($tab['income_parts']['employment'])->toBe(60000.0)
                ->and($tab['annual_salary_sacrificed'])->toBe(3000.0)
                ->and($tab['total_annual_income'])->toBe(57000.0)
                ->and($tab['gross_income'])->toBe(57000.0);
        }
        expect($after['income_tax'])->toBe($gross['income_tax'])
            ->and($after['national_insurance'])->toBe($gross['national_insurance']);
    });

    it('charges Class 1 on the pay after the sacrifice, not before', function () {
        $sacrificing = takeHomeTab(takeHomeSacrificer(['annual_employment_income' => 60000, 'employment_income_basis' => 'gross']));
        $paidAfter = takeHomeTab(takeHomeEarner(['annual_employment_income' => 57000]));

        expect($sacrificing['national_insurance'])->toBe($paidAfter['national_insurance'])
            ->and($sacrificing['income_tax'])->toBe($paidAfter['income_tax']);
    });

    it('lists the sacrifice as its own line, on the tax card and the income rows', function () {
        $user = takeHomeSacrificer(['annual_employment_income' => 60000, 'employment_income_basis' => 'gross']);
        $card = collect(takeHomeTab($user)['detailed_tax_breakdown']['income_breakdowns'][0]['income_components']);
        $summary = app(UserProfileService::class)->getCompleteProfile($user)['income_summary']['user'];

        expect($card->firstWhere('key', 'employment')['amount'])->toBe(60000.0)
            ->and($card->firstWhere('key', 'salary_sacrifice')['amount'])->toBe(-3000.0)
            ->and(collect($summary['sources'])->firstWhere('key', 'salary_sacrifice')['amount'])->toBe(-3000.0)
            ->and(collect($summary['sources'])->sum('amount'))->toEqualWithDelta((float) $summary['total'], 0.01);
    });
});
