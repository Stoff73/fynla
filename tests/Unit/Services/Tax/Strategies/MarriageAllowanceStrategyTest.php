<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Actions\ActionHowTo;
use App\Services\Actions\ActionHowToFacts;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // The locked list reads each strategy's required data from its definition.
    $this->seed(TaxActionDefinitionSeeder::class);
});

function maUser(array $attrs, array $household = []): User
{
    // marriage_allowance_eligible mirrors what FunnelAnswersMapper writes for a
    // non-earning spouse, so the old flag-only gate cannot pass these alone.
    $user = User::factory()->create($attrs + ['marital_status' => 'married', 'marriage_allowance_eligible' => true]);
    // A non-working spouse's income is captured, not assumed (CSJ 2026-09-28):
    // these fixtures stand for "no income", entered as 0.
    if (($attrs['household_calculation_mode'] ?? null) === 'single_earner_couple' && ! array_key_exists('spouse_annual_income', $household)) {
        $household['spouse_annual_income'] = 0;
    }
    TaxStrategyHouseholdInput::create(['user_id' => $user->id] + $household);

    return $user;
}

function maRec(User $user): ?array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'marriage_allowance_transfer');
}

function maBasicSaving(): float
{
    $math = app(TaxStrategyMath::class);

    return round($math->marriageAllowanceAmount() * $math->bandRateForBand('basic'), 2);
}

it('does not offer Marriage Allowance when the user has no taxable income (B6)', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 0]);

    expect(maRec($user))->toBeNull();
});

it('caps the saving at the tax the user actually pays', function () {
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => $pa + 430]);

    $basic = app(TaxStrategyMath::class)->bandRateForBand('basic');
    expect(maRec($user)['estimated_annual_tax_saved'])->toBe(round(430 * $basic, 2));
});

it('gives the full saving to a basic-rate recipient with a non-earning spouse', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000]);

    expect(maRec($user)['estimated_annual_tax_saved'])->toBe(maBasicSaving());
});

it('offers Marriage Allowance in dual_earner mode when the spouse earns below the Personal Allowance (B7)', function () {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 35000], ['spouse_annual_income' => 8000]);

    expect(maRec($user)['estimated_annual_tax_saved'])->toBe(maBasicSaving());
});

it('does not offer it when the spouse earns above the Personal Allowance or their income is unknown', function (?float $spouseIncome) {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 35000], ['spouse_annual_income' => $spouseIncome]);

    expect(maRec($user))->toBeNull();
})->with(['above' => [20000.0], 'unknown' => [null]]);

it('does not offer it to a couple who are not married or in a civil partnership (B8)', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000, 'marital_status' => 'single']);

    expect(maRec($user))->toBeNull();
});

it('shrinks the spouse Personal Allowance used for the savings gift by the transferred amount (B4)', function () {
    $user = maUser(
        ['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000],
        ['spouse_existing_savings_balance' => 0],
    );
    SavingsAccount::factory()->for($user)->create([
        'current_balance' => 200000, 'interest_rate' => 4.5, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    $gift = collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'savings_to_spouse');
    $math = app(TaxStrategyMath::class);
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];

    expect(maRec($user))->not->toBeNull()
        ->and($gift['spouse_personal_allowance'])->toBe($pa - $math->marriageAllowanceAmount());
});

/*
 * Eligibility, pinned to the law (CSJ 2026-09-28: show it only to people who
 * qualify). ITA 2007 s55B(2)(b) and (ba): the person receiving it pays no rate
 * above the basic rate, with dividends counted in full. GOV.UK
 * (https://www.gov.uk/marriage-allowance): the person giving it has income
 * below the Personal Allowance. That is narrower than s55C(1)(c),(ca); in the
 * statute the income test is s55C(2), which binds only a non-resident who
 * qualifies under s56(3) (s55C(1)(d)).
 */
it('does not offer it to a recipient who pays the higher rate', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 60000]);

    expect(maRec($user))->toBeNull();
});

it('offers it to a recipient exactly at the higher-rate threshold, which is still basic rate (ITA 2007 s10)', function () {
    $limit = (float) collect(app(TaxConfigService::class)->getIncomeTax()['bands'])->firstWhere('name', 'Basic Rate')['upper_limit'];
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => $limit]);

    expect(maRec($user))->not->toBeNull();
});

it('does not offer it when dividends take the recipient into the higher rate (s55B(2)(ba))', function () {
    $user = maUser([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 45000,
        'annual_dividend_income' => 10000,
    ]);

    expect(maRec($user))->toBeNull();
});

it('uses a linked spouse\'s own income: a "non-working" spouse with income above the allowance cannot give any', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000]);
    $spouse = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => 20000, 'spouse_id' => $user->id]);
    $user->update(['spouse_id' => $spouse->id]);

    expect(maRec($user->fresh()))->toBeNull();
});

it('uses a linked spouse\'s own income when it is below the allowance, and publishes both incomes', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000]);
    $spouse = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => 6000, 'spouse_id' => $user->id]);
    $user->update(['spouse_id' => $spouse->id]);

    $rec = maRec($user->fresh());

    expect($rec)->not->toBeNull()
        ->and($rec['transfer_direction'])->toBe('to_user')
        ->and($rec['spouse_income'])->toBe(6000.0)
        ->and($rec['estimated_annual_tax_saved'])->toBe(maBasicSaving());
});

it('runs the other way when the user is the one below the allowance and the spouse pays basic rate', function () {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 5000], ['spouse_annual_income' => 35000]);

    $rec = maRec($user);

    expect($rec['transfer_direction'])->toBe('to_spouse')
        ->and($rec['user_income'])->toBe(5000.0);
});

it('waits for the spouse\'s income when the only thing known is that they do not work', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000], ['spouse_annual_income' => null]);

    $plan = app(ComposedTaxPlanService::class)->forUser($user);

    expect(maRec($user))->toBeNull()
        ->and(collect($plan['locked'])->firstWhere('strategy_type', 'marriage_allowance_transfer')['missing'] ?? null)->toBe(['spouse_income_amount']);
});

it('never asks a single person for a spouse\'s income', function () {
    $user = User::factory()->create(['marital_status' => 'single', 'annual_employment_income' => 35000]);

    $plan = app(ComposedTaxPlanService::class)->forUser($user);
    $spouseStrategies = ['marriage_allowance_transfer', 'savings_to_spouse', 'isa_topup_spouse', 'gia_to_spouse',
        'gia_rebalance', 'isa_coordination', 'non_earner_spouse_pension', 'joint_savings_psa_split'];

    // Not waiting on a spouse's income, and not waiting on anything else either:
    // none of them can apply to someone with no spouse.
    expect(collect($plan['locked'])->pluck('strategy_type')->intersect($spouseStrategies)->all())->toBe([]);
});

it('warns a recipient above the Scottish limit that it does not apply if they live in Scotland', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 48000]);
    $rec = maRec($user);
    $entry = ActionHowToSeeder::parse((string) file_get_contents(ActionHowToSeeder::sourcePath('tax')))['marriage_allowance_transfer'];
    ['facts' => $facts, 'text' => $text] = app(ActionHowToFacts::class)->for($user, $rec);

    $steps = ActionHowTo::render($entry['steps'], $facts, $text);

    expect($rec)->not->toBeNull()
        ->and(collect($steps)->first(fn ($s) => str_starts_with($s, 'If you live in Scotland')))
        ->toContain('£43,662');
});
