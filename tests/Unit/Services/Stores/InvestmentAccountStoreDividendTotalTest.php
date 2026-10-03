<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/*
 * users.annual_dividend_income is the taxable dividend figure the Income page
 * reads (IncomeDefinitionsService). An account's dividends are part of it, and
 * the Store moves it on every write, whichever surface made the write.
 */

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create(['annual_dividend_income' => 500]);
    $this->store = app(InvestmentAccountStore::class);
});

function dividendAccount(int $userId, array $overrides = []): array
{
    return array_merge([
        'user_id' => $userId,
        'account_name' => 'Share dealing account',
        'account_type' => 'gia',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'current_value' => 15000.00,
        'provider' => 'Broker',
        'country' => 'United Kingdom',
        'annual_dividend_income' => 300,
    ], $overrides);
}

it('adds a new account\'s dividends to the total the user already has', function () {
    $this->store->create(dividendAccount($this->user->id), $this->user, IngestSource::FORM);

    expect((float) $this->user->fresh()->annual_dividend_income)->toBe(800.0);
});

it('moves the total by the change when an account\'s dividends are edited', function () {
    $account = $this->store->create(dividendAccount($this->user->id), $this->user, IngestSource::FORM);

    $this->store->update($account->id, ['annual_dividend_income' => 450], $this->user, IngestSource::FORM);
    expect((float) $this->user->fresh()->annual_dividend_income)->toBe(950.0);

    // Saving the same figure again changes nothing.
    $this->store->update($account->id, ['annual_dividend_income' => 450], $this->user, IngestSource::FORM);
    expect((float) $this->user->fresh()->annual_dividend_income)->toBe(950.0);
});

it('takes a deleted account\'s dividends off the total, and puts them back on restore', function () {
    $account = $this->store->create(dividendAccount($this->user->id), $this->user, IngestSource::FORM);

    $this->store->delete($account->id, $this->user, IngestSource::FORM);
    expect((float) $this->user->fresh()->annual_dividend_income)->toBe(500.0);

    $this->store->restore($account->id, $this->user, IngestSource::FORM);
    expect((float) $this->user->fresh()->annual_dividend_income)->toBe(800.0);
});

it('never counts ISA dividends, which are tax-free', function () {
    $this->store->create(dividendAccount($this->user->id, ['account_type' => 'isa']), $this->user, IngestSource::FORM);

    expect((float) $this->user->fresh()->annual_dividend_income)->toBe(500.0);
});

it('leaves the total alone when an account without dividends is written', function () {
    $account = $this->store->create(dividendAccount($this->user->id, ['annual_dividend_income' => null]), $this->user, IngestSource::FORM);
    $this->store->update($account->id, ['current_value' => 16000], $this->user, IngestSource::FORM);
    $this->store->delete($account->id, $this->user, IngestSource::FORM);

    expect((float) $this->user->fresh()->annual_dividend_income)->toBe(500.0);
});
