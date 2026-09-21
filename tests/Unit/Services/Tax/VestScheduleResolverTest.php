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
