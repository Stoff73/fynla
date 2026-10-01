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

/*
 * The giver's test is s55C(1)(c),(ca), not GOV.UK's "income below the Personal
 * Allowance" (CSJ 2026-09-30: "widen to law"; ITA 2007 s55C,
 * https://www.legislation.gov.uk/ukpga/2007/3/section/55C). After their
 * allowance falls by the transferable amount (s55B(6)) they may pay no rate
 * above the basic rate. Income the smaller allowance no longer covers can
 * still fall at a nil rate, so the transfer costs them less than it saves.
 */
it('offers it from a linked spouse whose savings interest sits in the starting rate for savings (s12)', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000]);
    $spouse = User::factory()->create([
        'marital_status' => 'married', 'annual_employment_income' => 0,
        'annual_interest_income' => 14000, 'spouse_id' => $user->id,
    ]);
    $user->update(['spouse_id' => $spouse->id]);

    $rec = maRec($user->fresh());

    // £14,000 less a £11,310 allowance leaves £2,690, all inside the £5,000
    // starting rate for savings: the spouse pays nothing more.
    expect($rec)->not->toBeNull()
        ->and($rec['transfer_direction'])->toBe('to_user')
        ->and($rec['estimated_annual_tax_saved'])->toBe(maBasicSaving())
        ->and($rec['transferor_extra_tax'])->toBe(0.0);
});

it('offers it from a spouse with dividends above the allowance, net of the dividend tax the transfer adds (s13A, s55C(1)(ca))', function () {
    $user = maUser(
        ['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 35000],
        ['spouse_annual_income' => 0, 'spouse_annual_dividends' => 14000],
    );
    $math = app(TaxStrategyMath::class);
    $extra = round($math->marriageAllowanceAmount() * (float) app(TaxConfigService::class)->getDividendTax()['basic_rate'], 2);

    $rec = maRec($user);

    // Within a penny: the tax engine rounds each year's dividend tax to the
    // penny before the two are subtracted.
    expect($rec)->not->toBeNull()
        ->and($rec['estimated_annual_tax_saved'])->toEqualWithDelta(maBasicSaving() - $extra, 0.011)
        ->and($rec['transferor_extra_tax'])->toEqualWithDelta($extra, 0.011);
});

it('nets off the tax a giver below the allowance pays once part of it is given away', function () {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 35000], ['spouse_annual_income' => 12000]);
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];
    $math = app(TaxStrategyMath::class);
    $extra = round(($math->marriageAllowanceAmount() - ($pa - 12000)) * $math->bandRateForBand('basic'), 2);

    $rec = maRec($user);

    expect($rec['estimated_annual_tax_saved'])->toBe(round(maBasicSaving() - $extra, 2))
        ->and($rec['transferor_extra_tax'])->toBe($extra);
});

// A giver whose income above the smaller allowance is pay pays exactly the
// reduction in extra tax, so the household saves nothing and it is not shown.
it('does not offer it when the spouse earns above the Personal Allowance or their income is unknown', function (?float $spouseIncome) {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 35000], ['spouse_annual_income' => $spouseIncome]);

    expect(maRec($user))->toBeNull();
})->with(['above' => [20000.0], 'unknown' => [null]]);

it('does not offer it to a couple who are not married or in a civil partnership (B8)', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000, 'marital_status' => 'single']);

    expect(maRec($user))->toBeNull();
});

// B4: a Marriage Allowance transfer takes that slice of the spouse's Personal
// Allowance (ITA 2007 s55B(6)), so it cannot also cover gifted interest. A
// spouse on £12,000 gives £1,260: their income above the smaller allowance
// then eats into the starting rate for savings (s12) too.
it('prices the savings gift on the spouse\'s allowance after the Marriage Allowance transfer (B4)', function () {
    $user = maUser(
        ['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 35000],
        ['spouse_annual_income' => 12000, 'spouse_existing_savings_balance' => 0],
    );
    SavingsAccount::factory()->for($user)->create([
        'current_balance' => 200000, 'interest_rate' => 4.5, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    $gift = collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'savings_to_spouse');
    $math = app(TaxStrategyMath::class);
    $income = app(TaxConfigService::class)->getIncomeTax();
    $aboveAllowance = 12000 + $math->marriageAllowanceAmount() - (float) $income['personal_allowance'];
    // What the spouse can take at 0%: the starting rate left after their
    // income above the smaller allowance, and their £1,000 Savings Allowance.
    $zeroRated = (float) $income['starting_rate_for_savings']['band'] - $aboveAllowance + $math->psaForBand('basic');

    expect(maRec($user)['transfer_direction'] ?? null)->toBe('to_user')
        ->and($gift['estimated_annual_tax_saved'])->toEqualWithDelta($zeroRated * $math->bandRateForBand('basic'), 2.0)
        ->and($gift['partner_extra_tax'])->toBeLessThan(0.01);
});

/*
 * Eligibility, pinned to the law (CSJ 2026-09-28: show it only to people who
 * qualify). ITA 2007 s55B(2)(b) and (ba): the person receiving it pays no rate
 * above the basic rate, with dividends counted in full. s55C(1)(c),(ca): the
 * person giving it passes the same test once their allowance is reduced.
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

// csjones 2026-09-30 (users 434/435): the linked partner had no income on
// their own record, so the walk asked for it and £72,000 was given — then
// this read the empty record as £0 and offered £252 to a £32,000 / £72,000
// couple. Their own records win only when they hold income (2026-09-28).
it('uses the income given for a linked spouse whose own records hold none', function () {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 32000], ['spouse_annual_income' => 72000]);
    $spouse = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => null, 'spouse_id' => $user->id]);
    $user->update(['spouse_id' => $spouse->id]);

    expect(maRec($user->fresh()))->toBeNull();
});

it('offers it from the income given when a linked spouse with no records of their own earns below the allowance', function () {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 32000], ['spouse_annual_income' => 9000]);
    $spouse = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => null, 'spouse_id' => $user->id]);
    $user->update(['spouse_id' => $spouse->id]);

    $rec = maRec($user->fresh());
    expect($rec)->not->toBeNull()
        ->and($rec['spouse_income'])->toBe(9000.0);
});

it('waits for the spouse\'s income when a linked spouse has no records and none was given', function () {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 32000], ['spouse_annual_income' => null]);
    $spouse = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => null, 'spouse_id' => $user->id]);
    $user->update(['spouse_id' => $spouse->id]);

    $plan = app(ComposedTaxPlanService::class)->forUser($user->fresh());

    expect(maRec($user->fresh()))->toBeNull()
        ->and(collect($plan['locked'])->firstWhere('strategy_type', 'marriage_allowance_transfer')['missing'] ?? null)->toBe(['spouse_income_amount']);
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

function maSteps(User $user): array
{
    $rec = maRec($user);
    $entry = ActionHowToSeeder::parse((string) file_get_contents(ActionHowToSeeder::sourcePath('tax')))['marriage_allowance_transfer'];
    ['facts' => $facts, 'text' => $text] = app(ActionHowToFacts::class)->for($user, $rec);

    return [
        'why' => ActionHowTo::render($entry['steps'], $facts, $text, 'why'),
        'steps' => ActionHowTo::render($entry['steps'], $facts, $text),
    ];
}

it('tells a giver above the allowance why they can still give it, and that it costs them nothing', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000]);
    $spouse = User::factory()->create([
        'first_name' => 'Sam', 'marital_status' => 'married', 'annual_employment_income' => 0,
        'annual_interest_income' => 14000, 'spouse_id' => $user->id,
    ]);
    $user->update(['spouse_id' => $spouse->id]);

    ['why' => $why, 'steps' => $steps] = maSteps($user->fresh());

    expect(implode(' ', $why))->toContain('still pay no Income Tax above the basic rate')
        ->and(implode(' ', $steps))->toContain('uses all of their')
        ->and(implode(' ', $steps))->toContain('pays no more tax')
        ->and(implode(' ', $steps))->not->toContain('is below the')
        ->and(implode(' ', $steps))->not->toContain('more Income Tax a year');
});

it('tells a giver below the allowance what the smaller allowance costs them', function () {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 35000], ['spouse_annual_income' => 12000]);

    $rec = maRec($user);
    ['why' => $why, 'steps' => $steps] = maSteps($user);

    expect(implode(' ', $why))->toContain('going unused')
        ->and(implode(' ', $steps))->toContain('is below the')
        ->and(implode(' ', $steps))->toContain('pays about £'.number_format(floor($rec['transferor_extra_tax'])).' more Income Tax a year');
});

// Tax compliance review of #1031: Gift Aid extends the giver's basic-rate band
// (ITA 2007 s414), so the gate lets them through; their extra tax must be
// priced on the same extended band, or the £1,260 reads as taxed at the
// dividend upper rate and a real saving disappears.
it('prices a Gift Aid donor\'s extra tax on their extended band', function () {
    $user = maUser([
        'household_calculation_mode' => 'dual_earner',
        'annual_employment_income' => 0,
        'annual_dividend_income' => 52000,
        'is_gift_aid' => true,
        'annual_charitable_donations' => 4000, // £5,000 gross
    ], ['spouse_annual_income' => 35000]);
    $math = app(TaxStrategyMath::class);
    $extra = $math->marriageAllowanceAmount() * (float) app(TaxConfigService::class)->getDividendTax()['basic_rate'];

    $rec = maRec($user);

    expect($rec)->not->toBeNull()
        ->and($rec['transfer_direction'])->toBe('to_spouse')
        ->and($rec['estimated_annual_tax_saved'])->toEqualWithDelta(maBasicSaving() - $extra, 0.011);
});
