<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Mobile\NextActionsService;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function reliefRecs(User $user): array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->where('type', 'pension_tax_relief')
        ->keyBy('tax_band')->all();
}

function reliefUser(float $income, array $extra = []): User
{
    return User::factory()->create($extra + [
        'household_calculation_mode' => 'single',
        'employment_status' => 'employed',
        'annual_employment_income' => $income,
        'marital_status' => 'single',
        'date_of_birth' => now()->subYears(40)->toDateString(),
    ]);
}

it('sizes a higher-rate item to the slice taxed at the higher rate', function () {
    $user = reliefUser(60000);
    $math = app(TaxStrategyMath::class);
    $slice = 60000 - $math->bandThresholdsFor($user)['higher'];
    $display = (int) (round($slice / 100) * 100);

    $rec = reliefRecs($user)['higher'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe((float) $display)
        ->and($rec['estimated_annual_tax_saved'])->toBe(round($display * $math->bandRateForBand('higher'), 2))
        ->and(reliefRecs($user))->not->toHaveKey('basic');
});

it('gives a basic-rate earner an item sized at a tenth of their earnings', function () {
    $user = reliefUser(30000);
    $basic = app(TaxStrategyMath::class)->bandRateForBand('basic');

    $rec = reliefRecs($user)['basic'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe(3000.0)
        ->and($rec['estimated_annual_tax_saved'])->toBe(round(3000 * $basic, 2));
});

it('stays silent for a basic-rate earner already paying in a tenth', function () {
    $user = reliefUser(30000);
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'workplace', 'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 8, 'employer_contribution_percent' => 3,
    ]);

    expect(reliefRecs($user))->toBe([]);
});

it('leaves the Personal Allowance taper band to pa_taper_rescue', function () {
    expect(reliefRecs(reliefUser(110000)))->toBe([]);
});

it('stays silent without taxable earnings', function (array $attrs) {
    expect(reliefRecs(reliefUser(...$attrs)))->toBe([]);
})->with([
    'below the Personal Allowance' => [[10000]],
    'aged 75' => [[30000, ['date_of_birth' => now()->subYears(76)->toDateString()]]],
    'no earnings, working status not given' => [[0, ['employment_status' => null, 'annual_other_income' => 30000]]],
    'no earnings, aged 75' => [[0, ['employment_status' => 'retired', 'annual_other_income' => 30000, 'date_of_birth' => now()->subYears(76)->toDateString()]]],
]);

it('gives someone with no earnings relief on the basic amount (FA 2004 s190, 29 Sep E5)', function (float $otherIncome, string $status, float $expectedSaving) {
    // £3,600 gross: £2,880 paid, £720 added at source whether or not tax is
    // paid (s192(1)). A higher-rate payer claims another 20% (s192(4)).
    $user = reliefUser(0, ['employment_status' => $status, 'annual_other_income' => $otherIncome]);
    // Savings cover the £2,880 payment (CSJ 2026-09-29: never suggest more
    // than recorded cash can fund).
    SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 5000, 'interest_rate' => 0, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
    $rec = reliefRecs($user)['no_earnings'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe((float) app(TaxConfigService::class)->getPensionAllowances()['relevant_earnings_minimum'])
        ->and($rec['estimated_annual_tax_saved'])->toBe($expectedSaving)
        ->and($rec['title'])->toContain('Pay £2,880 into a personal pension')
        // Money HMRC adds, not "tax relief" won by someone who may pay no tax (CSJ 2026-09-29).
        ->and($rec['title'])->toContain('HMRC adds £720')
        ->and(end($rec['working']))->toContain('you pay £2,880 and HMRC adds £720 through your pension provider')
        ->and($rec['title'])->not->toContain('of tax relief');
})->with([
    'retired basic-rate' => [30000, 'retired', 720.0],
    'not working, no income at all' => [0, 'unemployed', 720.0],
    'retired higher-rate: £3,600 at 40%' => [80000, 'retired', 1440.0],
]);

it('sizes the higher-rate slice after what the user already pays in (review I1)', function () {
    // £60,000 with 5% net-pay contributions: adjusted net income is £57,000,
    // so only £57,000 − the higher-rate threshold is relieved at 40%.
    $user = reliefUser(60000);
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'workplace', 'pension_type' => 'occupational',
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 0,
        'salary_sacrifice' => false,
    ]);
    $math = app(TaxStrategyMath::class);
    $slice = 60000 - 3000 - $math->bandThresholds()['higher'];

    $rec = reliefRecs($user)['higher'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe((float) (floor($slice / 100) * 100));
});

it('never rounds the contribution above the tax the user pays', function () {
    // £450 above the Personal Allowance: rounding to £500 would relieve tax
    // that is never paid.
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];

    $rec = reliefRecs(reliefUser($pa + 450))['basic'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe(400.0);
});

it('leaves interest the Personal Savings Allowance covers out of the higher-rate slice', function () {
    // Release walk 2026-09-27: interest inside the Personal Savings Allowance
    // is taxed at 0% (ITA 2007 s12B), so it is not income "taxed at 40%".
    // Interest set to exactly the higher-rate allowance so no ISA wrap runs.
    $user = reliefUser(72000);
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'workplace', 'pension_type' => 'occupational',
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 3,
        'salary_sacrifice' => false,
    ]);
    $math = app(TaxStrategyMath::class);
    SavingsAccount::factory()->for($user)->create([
        'current_balance' => $math->psaForBand('higher') / 0.04, 'interest_rate' => 4, 'is_isa' => false,
    ]);
    $slice = 72000 - 3600 - $math->bandThresholds()['higher'];
    $display = floor($slice / 100) * 100;

    $rec = reliefRecs($user)['higher'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe((float) $display)
        ->and($rec['estimated_annual_tax_saved'])->toBe(round($display * $math->bandRateForBand('higher'), 2));
});

it('sizes a non-earner\'s payment to what their savings cover, and offers none without savings', function () {
    // CSJ 2026-09-29: funded from savings, so £1,000 of cash buys £1,200 gross
    // (rounded down to £100), and no cash means no item.
    $withCash = reliefUser(0, ['employment_status' => 'retired', 'annual_other_income' => 20000]);
    SavingsAccount::factory()->create(['user_id' => $withCash->id, 'current_balance' => 1000, 'interest_rate' => 0, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
    $noCash = reliefUser(0, ['employment_status' => 'retired', 'annual_other_income' => 20000]);

    expect(reliefRecs($withCash)['no_earnings']['suggested_contribution'])->toBe(1200.0)
        ->and(reliefRecs($noCash))->not->toHaveKey('no_earnings');
});

it('carries the working behind a higher-rate item, from income after pension payments through pay', function () {
    // Item 18: Fyn told a user "£60,000 − £50,270 = £9,730 taxed at 40%"; the
    // plan's figure starts from income after the pension paid through pay.
    $user = reliefUser(60000);
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'workplace', 'pension_type' => 'occupational',
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 10, 'employer_contribution_percent' => 0,
        'salary_sacrifice' => false,
    ]);
    $math = app(TaxStrategyMath::class);
    $threshold = $math->bandThresholds()['higher'];
    $rate = (int) round($math->bandRateForBand('higher') * 100);
    $slice = 54000 - $threshold;
    $display = (int) (floor($slice / 100) * 100);
    $pounds = static fn (float $v): string => '£'.number_format((int) floor($v));

    $rec = reliefRecs($user)['higher'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['suggested_contribution'])->toBe((float) $display)
        ->and($rec['working'])->toBe([
            'Your income this year is £60,000.',
            '£6,000 of it goes into your pension from your pay before tax, which leaves £54,000 taxed as income.',
            sprintf('The higher rate starts at %s.', $pounds($threshold)),
            sprintf('So %s of your income is taxed at %d%%.', $pounds($slice), $rate),
            sprintf('Rounded down to the nearest £100 that is %s, and %d%% of %s is %s of tax saved.', $pounds($display), $rate, $pounds($display), $pounds($display * $rate / 100)),
        ]);
});

it('names the limit that set a basic-rate item, and the working reaches Fyn\'s actions list', function () {
    $user = reliefUser(30000, ['onboarding_completed' => true, 'monthly_expenditure' => 1500]);
    $rec = reliefRecs($user)['basic'] ?? null;

    expect($rec)->not->toBeNull()
        ->and($rec['working'][0])->toBe('Your plan suggests paying in a tenth of your earnings of £30,000, which is £3,000 a year.')
        ->and(end($rec['working']))->toStartWith('Rounded down to the nearest £100 that is £');

    $row = collect(app(NextActionsService::class)->forModel($user->id))
        ->firstWhere('recommendation_id', 'tax_pension_tax_relief');

    expect($row)->not->toBeNull()
        ->and($row['working'])->toBe($rec['working']);
});
