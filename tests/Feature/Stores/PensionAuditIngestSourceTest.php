<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\PensionInputHistory;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PensionStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

// Sub-Project 1, Pass 3 — PR 8.
//
// PensionStore::createDc / updateDc / createDb / updateDb / upsertState /
// captureInputHistory thread the IngestSource through a scoped
// AuditLog::withContext() so the auto-fired Auditable trait hook records
// metadata['ingest_source'] on the data-change audit row.
//
// The store calls PensionDerivedColumnCalculator, which needs an active
// tax year (PENSION_ANNUAL_ALLOWANCE lookup) — seed the real tax
// configuration, same pattern as SavingsAuditIngestSourceTest.
//
// Auditable::shouldAudit() is a no-op during the test suite by default
// (runningUnitTests() && !auditInTests()); opt in via the documented
// config('audit.in_tests') gate so the audit path actually fires.
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    config(['audit.in_tests' => true]);
});

it('PensionStore::createDc writes an audit row with ingest_source = fyn_ai', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDc(
        ['scheme_name' => 'Audit DC', 'current_fund_value' => 100],
        $user,
        IngestSource::FYN_AI
    );

    $auditRow = AuditLog::where('model_type', DCPension::class)
        ->where('model_id', $pension->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('PensionStore::createDc writes an audit row with ingest_source = form', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDc(
        ['scheme_name' => 'Form DC', 'current_fund_value' => 200],
        $user,
        IngestSource::FORM
    );

    $auditRow = AuditLog::where('model_type', DCPension::class)
        ->where('model_id', $pension->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('form');
});

it('PensionStore::createDb writes an audit row with ingest_source = upload', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDb(
        ['scheme_name' => 'Upload DB', 'scheme_type' => 'final_salary', 'accrued_annual_pension' => 1000],
        $user,
        IngestSource::UPLOAD
    );

    $auditRow = AuditLog::where('model_type', DBPension::class)
        ->where('model_id', $pension->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('upload');
});

it('PensionStore::updateDc writes an audit row with ingest_source', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDc(
        ['scheme_name' => 'Original', 'current_fund_value' => 100],
        $user,
        IngestSource::FORM
    );

    $store->updateDc($pension->id, ['scheme_name' => 'Renamed'], $user, IngestSource::FYN_AI);

    $auditRow = AuditLog::where('model_type', DCPension::class)
        ->where('model_id', $pension->id)
        ->where('action', AuditLog::ACTION_UPDATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('PensionStore::upsertState writes an audit row with ingest_source on first write', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $state = $store->upsertState(['ni_years_completed' => 10], $user, IngestSource::UPLOAD);

    $auditRow = AuditLog::where('model_type', StatePension::class)
        ->where('model_id', $state->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('upload');
});

it('PensionStore::captureInputHistory writes audit rows with ingest_source', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $store->captureInputHistory(
        [['tax_year' => '2024/25', 'pension_input_amount' => 10000]],
        $user,
        IngestSource::FYN_AI
    );

    $history = PensionInputHistory::where('user_id', $user->id)->latest('id')->first();

    $auditRow = AuditLog::where('model_type', PensionInputHistory::class)
        ->where('model_id', $history->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('audit context is cleared after the pension store call (no leak to a later unrelated write)', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $store->createDc(
        ['scheme_name' => 'Scoped', 'current_fund_value' => 100],
        $user,
        IngestSource::UPLOAD
    );

    // A subsequent audited write OUTSIDE any AuditLog::withContext() scope
    // (e.g. a non-store DCPension create — seeders, observers) must NOT
    // inherit the previous ingest_source: try/finally restored the context.
    $direct = DCPension::create([
        'user_id' => $user->id,
        'scheme_name' => 'Direct Non-Store Write',
        'current_fund_value' => 50,
    ]);

    $directAudit = AuditLog::where('model_type', DCPension::class)
        ->where('model_id', $direct->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($directAudit)->not->toBeNull();
    expect($directAudit->metadata['ingest_source'] ?? null)->toBeNull();
});
