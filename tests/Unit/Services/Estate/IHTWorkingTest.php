<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\EstatePlanHandler;
use App\Services\Estate\IHTCalculationService;
use App\Support\Money;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Walk fix plan T12 part 3 (M8, Q8): the estate figure carried no working, so
 * Fyn said the £606,000 estate and £112,400 bill "don't reconcile" with the
 * allowances (the residence band was not applied: no direct descendant was
 * recorded) and gave no figure for both deaths. The engine publishes the
 * steps behind its own figure, using its own allowance sentences.
 *
 * Worked by hand from the configured bands (IHTA 1984 s7, Sch 1): a single
 * person's £500,000 with no home and no debts takes the £325,000 nil rate
 * band only, leaving £175,000 taxed at 40%: £70,000.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function ihtWorkingSaver(array $attributes): User
{
    $user = User::factory()->create($attributes + ['date_of_birth' => '1970-01-01']);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 500_000,
        'is_isa' => false,
    ]);

    return $user->fresh();
}

it('publishes the steps behind the estate figure, each one the figure it was priced on', function () {
    $user = ihtWorkingSaver(['marital_status' => 'single']);

    $calc = app(IHTCalculationService::class)->calculate($user);

    expect(array_slice($calc['working'], 0, 5))->toBe([
        'Your estate is £500,000: assets of £500,000, with no debts recorded.',
        'Nil Rate Band of £325,000 applied.',
        $calc['rnrb_message'],
        'Together the allowances come to £325,000, so £175,000 is taxable.',
        'At 40% that is £70,000.',
    ])->and($calc['rnrb_message'])->toStartWith('Residence Nil Rate Band not available');
});

it('says when a married user\'s figure leaves their spouse out', function () {
    $user = ihtWorkingSaver(['marital_status' => 'married']);

    $calc = app(IHTCalculationService::class)->calculate($user);

    expect($calc['working'])->toContain('Only your own estate is counted, because your spouse\'s records are not linked to and shared with your account.');
});

it('carries the projection at the expected age of death', function () {
    $user = ihtWorkingSaver(['marital_status' => 'single']);

    $calc = app(IHTCalculationService::class)->calculate($user);

    $projection = collect($calc['working'])->first(fn (string $line) => str_starts_with($line, 'Looking ahead to age'));

    expect($projection)->toContain('Looking ahead to age '.$calc['estimated_age_at_death'])
        ->toContain('in '.$calc['years_to_death'].' years');
});

it('carries the same working in the estate plan Fyn fetches', function () {
    $user = ihtWorkingSaver(['marital_status' => 'single']);
    $calc = app(IHTCalculationService::class)->calculate($user);

    $payload = json_decode(
        app(EstatePlanHandler::class)->fetch(new FetchContext($user, 'how much inheritance tax would there be'))->value,
        true,
    );

    expect($payload)->toHaveKey('composed_estate_plan')
        ->and($payload['inheritance_tax']['iht_liability'])->toEqual($calc['iht_liability'])
        ->and($payload['inheritance_tax']['working'])->toBe($calc['working']);
});

// Robin 509's working said "the estate is projected at £0" with no reason; the
// engine's own assumptions (no pension or State Pension recorded, spending
// carries on) and the cash it runs short by explain it.
it('gives the projection its assumptions and any cash shortfall', function () {
    $user = ihtWorkingSaver(['marital_status' => 'single', 'monthly_expenditure' => 4000, 'expenditure_entry_mode' => 'simple']);

    $calc = app(IHTCalculationService::class)->calculate($user);

    expect($calc['projected_cash_assumptions'])->not->toBeEmpty()
        ->and($calc['working'])->toContain(...$calc['projected_cash_assumptions']);
    if ((float) $calc['projected_cash_shortfall'] > 0) {
        expect($calc['working'])->toContain(sprintf(
            'On those assumptions, spending uses up the cash before then, with %s more needed.',
            Money::exact((float) $calc['projected_cash_shortfall']),
        ));
    }
});
