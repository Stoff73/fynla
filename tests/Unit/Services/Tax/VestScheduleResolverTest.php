<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\VestScheduleResolver;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    Carbon::setTestNow('2026-09-21');
    $this->resolver = app(VestScheduleResolver::class);
});

afterEach(function () {
    Carbon::setTestNow();
    Mockery::close();
});

function rsuAccount(User $user, array $overrides = []): InvestmentAccount
{
    return InvestmentAccount::factory()->create(array_merge([
        'user_id' => $user->id,
        'account_type' => 'rsu',
        'account_name' => 'Acme RSUs',
        'provider' => 'Acme',
        'current_value' => 0,
        'scheme_status' => 'active',
        'vesting_type' => 'graded',
        'cliff_date' => null,
        'vesting_frequency_months' => 3,
        'full_vest_date' => '2028-03-15',
        'units_unvested' => 800,
        'current_share_price' => 30,
        // The factory randomises ownership; pin it so the fixture is deterministic.
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $overrides));
}

it('projects the quarterly vest events that fall in this tax year', function () {
    $user = User::factory()->create();
    rsuAccount($user);

    $events = $this->resolver->schedule($user);

    // Quarterly back from 2028-03-15 to the tax year start gives eight dates; six are
    // on or after today (2026-12-15 to 2028-03-15), so 800 units split six ways.
    // Two of those six fall in 2026/27.
    $dates = array_map(fn ($e) => $e['date']->toDateString(), $events);
    expect($dates)->toBe(['2026-12-15', '2027-03-15'])
        ->and($events[0]['value'])->toBe(round(800 / 6 * 30, 2));
});

it('counts vests already past in this tax year as income but not as upcoming events', function () {
    $user = User::factory()->create();
    rsuAccount($user);

    expect($this->resolver->annualVestIncome($user))
        ->toBe(round(800 / 6 * 30 * 4, 2)); // 2026-06-15, 09-15, 12-15, 2027-03-15
});

it('values unapproved options at the spread, not the whole share price', function () {
    $user = User::factory()->create();
    rsuAccount($user, [
        'account_type' => 'unapproved_options',
        'account_name' => 'Acme share options',
        'exercise_price' => 12,
    ]);

    // ITEPA 2003 s476 charges the gain: £30 market value less the £12 the holder
    // pays to exercise, so £18 a unit rather than £30.
    expect($this->resolver->annualVestIncome($user))
        ->toBe(round(800 / 6 * 18 * 4, 2))
        ->not->toBe(round(800 / 6 * 30 * 4, 2));
});

it('vests nothing before a cliff that is still ahead', function () {
    $user = User::factory()->create();
    rsuAccount($user, [
        'cliff_date' => '2027-01-15',
        'cliff_percentage' => 25,
        'units_granted' => 800,
    ]);

    $events = $this->resolver->schedule($user);
    $dates = array_map(fn ($e) => $e['date']->toDateString(), $events);

    // 25% of the 800 granted units vests on the cliff. The remaining 600 splits
    // across the five ladder dates on or after it (2027-03-15 through 2028-03-15);
    // the three quarterly dates before the cliff are dropped entirely.
    expect($dates)->toBe(['2027-01-15', '2027-03-15'])
        ->and($events[0]['value'])->toBe(200.0 * 30)
        ->and($events[1]['value'])->toBe(round(600 / 5 * 30, 2));
});

it('vests a cliff-only schedule in one event on the full vest date', function () {
    $user = User::factory()->create();
    rsuAccount($user, [
        'vesting_type' => 'cliff',
        'full_vest_date' => '2027-03-15',
    ]);

    $events = $this->resolver->schedule($user);

    // A quarterly frequency is still recorded on the row, but a cliff with no stated
    // percentage is all-or-nothing: one event, not a ladder of four.
    expect($events)->toHaveCount(1)
        ->and($events[0]['date']->toDateString())->toBe('2027-03-15')
        ->and($events[0]['value'])->toBe(800.0 * 30);
});

it('does not invent tranches from before the grant was made', function () {
    $user = User::factory()->create();
    rsuAccount($user, ['grant_date' => '2026-08-01']);

    // The ladder floors at the grant, so the 2026-06-15 tranche never exists and
    // the year holds three vests (2026-09-15, 12-15, 2027-03-15), not four.
    expect($this->resolver->annualVestIncome($user))
        ->toBe(round(800 / 6 * 30 * 3, 2))
        ->not->toBe(round(800 / 6 * 30 * 4, 2));
});

it('steps the ladder from the full vest date so a month-end vest does not drift', function () {
    $user = User::factory()->create();
    rsuAccount($user, ['full_vest_date' => '2027-05-31']);

    $dates = array_map(fn ($e) => $e['date']->toDateString(), $this->resolver->schedule($user));

    // Chaining the subtraction off each previous date walks 31 May quarterly out to
    // 3 December via the short months; measuring from the anchor holds 30 November.
    expect($dates)->toBe(['2026-11-30', '2027-02-28']);
});

it('excludes tax-advantaged schemes from income', function () {
    $user = User::factory()->create();
    rsuAccount($user, ['account_type' => 'emi', 'account_name' => 'Acme EMI']);

    expect($this->resolver->annualVestIncome($user))->toBe(0.0);
});

it('reaches adjusted net income through the income definitions', function () {
    $user = User::factory()->create(['annual_employment_income' => 90000]);
    rsuAccount($user);

    $definitions = app(IncomeDefinitionsService::class)->calculate($user->id);

    expect($definitions['components']['vesting'])->toBe(round(800 / 6 * 30 * 4, 2))
        ->and($definitions['adjusted_net_income'])->toBe(round(90000 + 800 / 6 * 30 * 4, 2));
});
