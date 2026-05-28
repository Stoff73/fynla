<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\Normalisers\InvestmentAccountNormaliser;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// SP1 Pass 6 PR 4 — upload + seeder ingest parity for InvestmentAccountStore.
//
// Mirrors MortgageUploadIngestTest / PensionUploadIngestTest / SavingsUploadIngestTest:
// drives the canonical upload-equivalent path through InvestmentAccountNormaliser::fromUpload
// + InvestmentAccountStore::create with IngestSource::UPLOAD (the exact contract
// DocumentProcessor::confirmExcel now hits for the investment_holdings branch),
// then asserts the row landed AND the AuditLog row carries
// metadata['ingest_source'] = 'upload'. Also covers the seeder updateOrCreate
// idempotency contract that ChrisUserSeeder relies on.

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    config(['audit.in_tests' => true]);
});

it('creates an investment account via upload-equivalent path with UPLOAD audit context', function () {
    $user = User::factory()->create(['tier' => 'tier1']);

    $upload = [
        'account_name' => 'Vanguard Stocks & Shares ISA',
        'provider' => 'Vanguard',
        'account_type' => 'isa',
        'current_value' => 42000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'isa_type' => 'stocks_and_shares',
        'country' => 'United Kingdom',
    ];

    $canonical = InvestmentAccountNormaliser::fromUpload($upload, $user);
    $account = app(InvestmentAccountStore::class)->create($canonical, $user, IngestSource::UPLOAD);

    expect($account->provider)->toBe('Vanguard');
    expect($account->account_type)->toBe('isa');
    expect((float) $account->current_value)->toEqual(42000.00);
    expect(InvestmentAccount::where('user_id', $user->id)->count())->toBe(1);

    $auditRow = AuditLog::where('model_type', InvestmentAccount::class)
        ->where('model_id', $account->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('upload');
});

it('updateOrCreate through the store is idempotent for the ChrisUserSeeder canonical', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(InvestmentAccountStore::class);

    // Mirrors ChrisUserSeeder: match on (provider, account_type), data carries
    // the stable account_name and the per-reseed values.
    $match = ['provider' => 'Vanguard', 'account_type' => 'isa'];
    $build = static fn (float $value): array => [
        'account_name' => 'Vanguard Stocks & Shares ISA',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'country' => 'United Kingdom',
        'current_value' => $value,
        'isa_type' => 'stocks_and_shares',
    ];

    $first = $store->updateOrCreate($match, $build(95000.00), $user, IngestSource::SEEDER);
    expect($first->wasRecentlyCreated)->toBeTrue();
    expect(InvestmentAccount::where('user_id', $user->id)->count())->toBe(1);

    // Second reseed with the same match keys must UPDATE the existing row, not
    // insert a duplicate.
    $second = $store->updateOrCreate($match, $build(110000.00), $user, IngestSource::SEEDER);

    expect($second->id)->toBe($first->id);
    expect(InvestmentAccount::where('user_id', $user->id)->count())->toBe(1);
    expect((float) $second->fresh()->current_value)->toEqual(110000.00);
});
