<?php

declare(strict_types=1);

use App\Models\ISAContribution;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\SavingsStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('records annual ISA summaries at the canonical savings and investment store boundaries', function () {
    $user = User::factory()->create(['tier' => 'premium']);

    $cash = app(SavingsStore::class)->create([
        'account_name' => 'Captured Cash ISA',
        'account_type' => 'cash_isa',
        'current_balance' => 5000,
        'is_isa' => true,
        'isa_type' => 'cash',
        'isa_subscription_year' => '2025/26',
        'isa_subscription_amount' => 2500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $user, IngestSource::FORM);

    $shares = app(InvestmentAccountStore::class)->create([
        'account_name' => 'Captured S&S ISA',
        'account_type' => 'isa',
        'current_value' => 5000,
        'isa_type' => 'stocks_and_shares',
        'tax_year' => '2025/26',
        'isa_subscription_current_year' => 3000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $user, IngestSource::FYN_AI);

    expect(ISAContribution::where('account_type', SavingsAccount::class)->where('account_id', $cash->id)->sole())
        ->amount->toBe('2500.00')
        ->source->toBe('form')
        ->provenance->toBe('captured_annual_summary')
        ->and(ISAContribution::where('account_type', InvestmentAccount::class)->where('account_id', $shares->id)->sole())
        ->amount->toBe('3000.00')
        ->source->toBe('fyn_ai');

    app(InvestmentAccountStore::class)->update(
        $shares->id,
        ['isa_subscription_current_year' => 3500],
        $user,
        IngestSource::FORM,
    );

    expect(ISAContribution::where('account_type', InvestmentAccount::class)->where('account_id', $shares->id)->count())->toBe(1)
        ->and((float) ISAContribution::where('account_type', InvestmentAccount::class)->where('account_id', $shares->id)->value('amount'))->toBe(3500.0);
});

it('publishes canonical ISA tax-year ownership ledger and exact allowance totals', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $cash = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'Cash ISA',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'isa_type' => 'cash',
        'isa_subscription_year' => '2025/26',
        'isa_subscription_amount' => 2500,
    ]);
    $shares = InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'Stocks & Shares ISA',
        'account_type' => 'isa',
        'isa_type' => 'stocks_and_shares',
        'tax_year' => '2025/26',
        'isa_subscription_current_year' => 5000,
    ]);
    ISAContribution::create([
        'user_id' => $user->id,
        'account_type' => InvestmentAccount::class,
        'account_id' => $shares->id,
        'tax_year' => '2025/26',
        'contribution_date' => '2025-05-01',
        'entry_type' => 'subscription',
        'amount' => 4000,
        'source' => 'form',
        'provenance' => 'recorded_ledger',
    ]);

    $data = $this->getJson('/api/savings/isa-allowance/2025%2F26')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['tax_year'])->toBe('2025/26')
        ->and($data['total_used'])->toBe(6500)
        ->and(collect($data['account_breakdown'])->sum('contributed'))->toBe(6500)
        ->and(collect($data['account_breakdown'])->pluck('account_id'))->toContain($cash->id, $shares->id)
        ->and($data['account_breakdown'])->each->toHaveKeys([
            'owner',
            'provenance',
            'contributions',
        ]);
});
