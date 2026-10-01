<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Actions\ActionHowTo;
use App\Services\Actions\ActionHowToFacts;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\Onboarding\CaptureForms;
use App\Services\Tax\Strategies\AssetShiftingBundleStrategy;
use App\Services\Tax\Strategies\TaxStrategyContext;
use App\Services\Tax\TaxStrategyMath;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Move savings to the partner who pays less tax (TODO item 4, CSJ 2026-09-30:
 * "Yes build it"; spec docs/superpowers/specs/2026-10-01-savings-to-lower-tax-
 * partner-design.md). Interest on savings given outright to a spouse is theirs
 * (ITTOIA 2005 s626); each partner's Personal Allowance, starting rate for
 * savings (ITA 2007 s12) and Personal Savings Allowance (s12B) decide what it
 * costs them. Expected figures are worked from the configured rates.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

function stsCouple(array $user, array $household, float $balance, float $rate): User
{
    $u = User::factory()->create($user + ['marital_status' => 'married']);
    if ($household !== []) {
        TaxStrategyHouseholdInput::create(['user_id' => $u->id] + $household);
    }
    SavingsAccount::factory()->for($u)->create([
        'current_balance' => $balance, 'interest_rate' => $rate, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    return $u->fresh();
}

function stsGift(User $user): ?array
{
    $user = $user->fresh();
    $context = new TaxStrategyContext($user, null, TaxStrategyHouseholdInput::where('user_id', $user->id)->first(), (string) $user->household_calculation_mode);
    $rec = collect(app(AssetShiftingBundleStrategy::class)->generate($context))->firstWhere('type', 'savings_to_spouse');

    return $rec?->toArray();
}

function stsRate(string $band): float
{
    return app(TaxStrategyMath::class)->bandRateForBand($band);
}

function stsPsa(string $band): float
{
    return app(TaxStrategyMath::class)->psaForBand($band);
}

// The list's worked example: £40,000 at 4.5% (£1,800 of interest) held by a
// £60,000 earner, whose partner earns £20,000.
function stsWorkedExample(): User
{
    return stsCouple(
        ['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 60000],
        ['spouse_annual_income' => 20000, 'spouse_existing_savings_balance' => 0],
        40000, 4.5,
    );
}

it('moves only the interest that saves the household tax: the list\'s £460 example', function () {
    $user = stsWorkedExample();
    $household = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();

    $move = app(TaxStrategyMath::class)->savingsMoveToPartner($user, 'dual_earner', $household, 1800);

    // The user's interest above their £500 allowance is taxed at 40%; the
    // partner, past the starting rate on £20,000, takes £1,000 at 0% and the
    // rest at 20%. Moving the £500 the user's own allowance covers saves
    // nothing more.
    $taxed = 1800 - stsPsa('higher');
    expect($move['interest_moved'])->toBe($taxed)
        ->and($move['saving'])->toEqualWithDelta($taxed * stsRate('higher') - ($taxed - stsPsa('basic')) * stsRate('basic'), 0.01);
});

it('offers a couple who both earn the gift, sized from that interest and rounded down to £100', function () {
    $gift = stsGift(stsWorkedExample());

    $transfer = floor((1800 - stsPsa('higher')) / 0.045 / 100) * 100;
    $interest = $transfer * 0.045;
    $saving = floor($interest * stsRate('higher') - ($interest - stsPsa('basic')) * stsRate('basic'));

    expect($gift)->not->toBeNull()
        ->and($gift['suggested_transfer_amount'])->toBe($transfer)
        ->and($gift['estimated_annual_tax_saved'])->toBe($saving)
        ->and($gift['title'])->toBe(sprintf('Gift £%s of savings to your spouse and save £%s in tax a year', number_format((int) $transfer), number_format((int) $saving)));
});

it('reads a linked partner\'s own records for their income and savings', function () {
    $user = stsCouple(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 60000], [], 40000, 4.5);
    $partner = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => 20000, 'spouse_id' => $user->id]);
    $user->update(['spouse_id' => $partner->id]);

    expect(stsGift($user)['suggested_transfer_amount'] ?? null)->toBe(floor((1800 - stsPsa('higher')) / 0.045 / 100) * 100);
});

// S8 / E5: a retired couple. The partner's £14,000 pension uses their whole
// Personal Allowance and £1,430 of their starting rate; the old card gave
// them the full allowance stack and proposed moving all £60,000.
it('prices a retired partner on their pension income, moving only the user\'s taxed interest', function () {
    $user = stsCouple(
        ['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 25000],
        ['spouse_annual_income' => 14000, 'spouse_existing_savings_balance' => 0],
        60000, 4.5,
    );

    $gift = stsGift($user);

    $taxed = 60000 * 0.045 - stsPsa('basic');
    $transfer = floor($taxed / 0.045 / 100) * 100;
    expect($gift['suggested_transfer_amount'])->toBe($transfer)
        ->and($gift['estimated_annual_tax_saved'])->toBe(floor($transfer * 0.045 * stsRate('basic')))
        ->and($gift['partner_extra_tax'])->toBeLessThan(0.01);
});

it('waits for the partner\'s savings when a working partner was never asked', function () {
    $user = stsCouple(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 60000], ['spouse_annual_income' => 20000], 40000, 4.5);

    $plan = app(ComposedTaxPlanService::class)->forUser($user);

    expect(stsGift($user))->toBeNull()
        ->and(app(HouseholdFinancialContext::class)->availability($user)['spouse_savings'])->toBeFalse()
        ->and(collect($plan['locked'])->firstWhere('strategy_type', 'savings_to_spouse')['missing'] ?? null)->toBe(['spouse_savings']);
});

it('prices the partner\'s own interest once it is given', function () {
    // The partner already uses £1,000 of interest: their Savings Allowance is
    // gone, so everything moved to them is taxed at 20%.
    $user = stsCouple(
        ['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 60000],
        ['spouse_annual_income' => 20000, 'spouse_existing_savings_balance' => 25000, 'spouse_annual_savings_interest' => 1000],
        40000, 4.5,
    );

    $move = app(TaxStrategyMath::class)->savingsMoveToPartner($user, 'dual_earner', TaxStrategyHouseholdInput::where('user_id', $user->id)->first(), 1800);
    $taxed = 1800 - stsPsa('higher');

    expect($move['saving'])->toEqualWithDelta($taxed * (stsRate('higher') - stsRate('basic')), 0.01);
});

it('asks nobody about a partner\'s savings when the user pays no tax on their interest', function () {
    $user = stsCouple(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 30000], ['spouse_annual_income' => 20000], 10000, 4.5);

    expect(stsGift($user))->toBeNull()
        ->and(app(HouseholdFinancialContext::class)->availability($user)['spouse_savings'])->toBeNull();
});

it('tells the user what the partner then pays on the interest', function () {
    $user = stsWorkedExample();
    $gift = stsGift($user);
    $entry = ActionHowToSeeder::parse((string) file_get_contents(ActionHowToSeeder::sourcePath('tax')))['savings_to_spouse'];
    ['facts' => $facts, 'text' => $text] = app(ActionHowToFacts::class)->for($user, $gift);

    $steps = implode(' ', ActionHowTo::render($entry['steps'], $facts, $text));
    $why = implode(' ', ActionHowTo::render($entry['steps'], $facts, $text, 'why'));

    // The figures add up as shown: you save less what they pay is the saving.
    $partner = floor($gift['user_tax_saved']) - $gift['estimated_annual_tax_saved'];
    expect($steps)->toContain('they pay about £'.number_format((int) $partner).' a year on it, and you pay about £'.number_format((int) floor($gift['user_tax_saved'])).' less')
        ->and($gift['description'])->toContain('they pay about £'.number_format((int) $partner).' more')
        ->and($steps)->toContain('from the savings that pay you the most interest')
        ->and($why)->toContain('would pay less tax on that interest than you do');
});

it('asks a working partner about their savings, and reads "Savings" left unticked as none', function () {
    $schema = CaptureForms::schema(CaptureForms::SPOUSE_HOUSEHOLD);
    $savings = collect($schema['kinds'])->firstWhere('key', 'savings');

    $unticked = CaptureForms::toolInputs(['name' => CaptureForms::SPOUSE_HOUSEHOLD, 'answers' => [
        CaptureForms::LEAD => ['spouse_annual_income' => 20000],
        'isa' => ['spouse_isa_balance' => 5000],
    ]])[CaptureForms::LEAD];
    $given = CaptureForms::toolInputs(['name' => CaptureForms::SPOUSE_HOUSEHOLD, 'answers' => [
        CaptureForms::LEAD => ['spouse_annual_income' => 20000],
        'savings' => ['spouse_existing_savings_balance' => 25000],
    ]])[CaptureForms::LEAD];

    expect($savings['fields'])->toBe(['spouse_existing_savings_balance', 'spouse_annual_savings_interest'])
        ->and($schema['fields']['spouse_annual_savings_interest']['required'])->toBeFalse()
        ->and($unticked['spouse_existing_savings_balance'])->toBe(0.0)
        ->and($unticked['spouse_annual_savings_interest'])->toBe(0.0)
        ->and($given['spouse_existing_savings_balance'])->toBe(25000.0)
        ->and($given)->not->toHaveKey('spouse_annual_savings_interest');
});

it('saves a working partner\'s savings and interest through Fyn', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'household_calculation_mode' => 'dual_earner']);

    $result = app(CoordinatingAgent::class)->executeTool('capture_spouse_household_data', [
        'spouse_annual_income' => 20000,
        'spouse_existing_savings_balance' => 25000,
        'spouse_annual_savings_interest' => 1000,
    ], $user);

    $row = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
    expect($result['error'] ?? false)->toBeFalse()
        ->and((float) $row->spouse_existing_savings_balance)->toBe(25000.0)
        ->and((float) $row->spouse_annual_savings_interest)->toBe(1000.0);
});

it('keeps the partner\'s savings when their work status changes', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'household_calculation_mode' => 'single_earner_couple']);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => 0, 'spouse_existing_savings_balance' => 25000, 'spouse_annual_savings_interest' => 1000]);

    app(CoordinatingAgent::class)->executeTool('update_profile', ['section' => 'spouse_household', 'fields' => ['spouse_works' => true]], $user);

    expect((float) TaxStrategyHouseholdInput::where('user_id', $user->id)->first()->spouse_existing_savings_balance)->toBe(25000.0);
});

it('says only that the allowances the partner has left cover it, when they pay nothing', function () {
    // A partner on £20,000 has no Personal Allowance or starting rate left:
    // only their Savings Allowance covers the interest (tax review, item 4).
    $user = stsCouple(
        ['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 60000],
        ['spouse_annual_income' => 20000, 'spouse_existing_savings_balance' => 0],
        20000, 4.5,
    );
    $gift = stsGift($user);
    $entry = ActionHowToSeeder::parse((string) file_get_contents(ActionHowToSeeder::sourcePath('tax')))['savings_to_spouse'];
    ['facts' => $facts, 'text' => $text] = app(ActionHowToFacts::class)->for($user, $gift);
    $steps = implode(' ', ActionHowTo::render($entry['steps'], $facts, $text));

    expect($gift['partner_extra_tax'])->toBeLessThan(0.01)
        ->and($steps)->toContain('the tax-free allowances they have left for savings interest cover it')
        ->and($steps)->not->toContain('Personal Allowance, starting rate');
});
