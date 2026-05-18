<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsStore;
use Database\Seeders\TaxConfigurationSeeder;

// Sub-Project 1, Pass 1 — PR 8.
//
// SavingsStore::create() threads the IngestSource through a scoped
// AuditLog::withContext() so the auto-fired Auditable trait hook records
// metadata['ingest_source'] on the data-change audit row.
//
// The store calls SavingsAccountDerivedColumnCalculator (ISA-allowance %),
// which needs an active tax year — seed the real tax configuration, same
// pattern as SavingsStoreTest / SavingsReadConsumerParityTest.
//
// Auditable::shouldAudit() is a no-op during the test suite by default
// (runningUnitTests() && !auditInTests()); opt in via the documented
// config('audit.in_tests') gate so the audit path actually fires.
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    config(['audit.in_tests' => true]);
});

it('SavingsStore::create writes an audit row with ingest_source = fyn_ai', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->create([
        'account_name' => 'Audit Test',
        'account_type' => 'savings',
        'institution' => 'Test Bank',
        'current_balance' => 100,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'country' => 'United Kingdom',
    ], $user, IngestSource::FYN_AI);

    $auditRow = AuditLog::where('model_type', SavingsAccount::class)
        ->where('model_id', $account->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('SavingsStore::create writes an audit row with ingest_source = form', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->create([
        'account_name' => 'Form Account',
        'account_type' => 'savings',
        'institution' => 'Test Bank',
        'current_balance' => 250,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    $auditRow = AuditLog::where('model_type', SavingsAccount::class)
        ->where('model_id', $account->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('form');
});

it('SavingsStore::create writes an audit row with ingest_source = upload', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->create([
        'account_name' => 'Uploaded Account',
        'account_type' => 'savings',
        'institution' => 'Test Bank',
        'current_balance' => 750,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'country' => 'United Kingdom',
    ], $user, IngestSource::UPLOAD);

    $auditRow = AuditLog::where('model_type', SavingsAccount::class)
        ->where('model_id', $account->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('upload');
});

it('SavingsStore::update writes an audit row with ingest_source', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->create([
        'account_name' => 'Original',
        'account_type' => 'savings',
        'institution' => 'Test Bank',
        'current_balance' => 100,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    $store->update($account->id, ['account_name' => 'Renamed'], $user, IngestSource::FYN_AI);

    $auditRow = AuditLog::where('model_type', SavingsAccount::class)
        ->where('model_id', $account->id)
        ->where('action', AuditLog::ACTION_UPDATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('audit context is cleared after the store call (no leak to a later unrelated write)', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $store->create([
        'account_name' => 'Scoped',
        'account_type' => 'savings',
        'institution' => 'Test Bank',
        'current_balance' => 100,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'country' => 'United Kingdom',
    ], $user, IngestSource::UPLOAD);

    // A subsequent audited write OUTSIDE any AuditLog::withContext() scope
    // (e.g. a non-store SavingsAccount create — seeders, observers) must NOT
    // inherit the previous ingest_source: try/finally restored the context.
    $direct = SavingsAccount::create([
        'user_id' => $user->id,
        'account_name' => 'Direct Non-Store Write',
        'account_type' => 'savings',
        'institution' => 'Test Bank',
        'current_balance' => 50,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'country' => 'United Kingdom',
    ]);

    $directAudit = AuditLog::where('model_type', SavingsAccount::class)
        ->where('model_id', $direct->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($directAudit)->not->toBeNull();
    expect($directAudit->metadata['ingest_source'] ?? null)->toBeNull();
});
