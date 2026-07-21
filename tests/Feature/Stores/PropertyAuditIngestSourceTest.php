<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

// Sub-Project 1, Pass 4 — PR 8.
//
// PropertyStore::create() and ::update() thread the IngestSource through a
// scoped AuditLog::withContext() so the auto-fired Auditable trait hook
// records metadata['ingest_source'] on the data-change audit row.
//
// The store calls PropertyDerivedColumnCalculator, which needs a valid
// TaxConfiguration record — seed it exactly as the sibling store tests do.
//
// Auditable::shouldAudit() is a no-op during the test suite by default
// (runningUnitTests() && !auditInTests()); opt in via the documented
// config('audit.in_tests') gate so the audit path actually fires.
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    config(['audit.in_tests' => true]);
});

it('PropertyStore::create writes an audit row with ingest_source = fyn_ai', function () {
    $user = User::factory()->create();
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 300000,
    ], $user, IngestSource::FYN_AI);

    $auditRow = AuditLog::where('model_type', Property::class)
        ->where('model_id', $property->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('PropertyStore::create writes an audit row with ingest_source = form', function () {
    $user = User::factory()->create();
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'secondary_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 450000,
    ], $user, IngestSource::FORM);

    $auditRow = AuditLog::where('model_type', Property::class)
        ->where('model_id', $property->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('form');
});

it('PropertyStore::create writes an audit row with ingest_source = upload', function () {
    $user = User::factory()->create();
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'buy_to_let',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 200000,
    ], $user, IngestSource::UPLOAD);

    $auditRow = AuditLog::where('model_type', Property::class)
        ->where('model_id', $property->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('upload');
});

it('PropertyStore::update writes an audit row with ingest_source', function () {
    $user = User::factory()->create();
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 300000,
    ], $user, IngestSource::FORM);

    $store->update($property->id, ['current_value' => 325000], $user, IngestSource::FYN_AI);

    $auditRow = AuditLog::where('model_type', Property::class)
        ->where('model_id', $property->id)
        ->where('action', AuditLog::ACTION_UPDATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('audit context is cleared after the property store call (no leak to a later unrelated write)', function () {
    $user = User::factory()->create();
    $store = app(PropertyStore::class);

    $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 300000,
    ], $user, IngestSource::UPLOAD);

    // A subsequent audited write OUTSIDE any AuditLog::withContext() scope
    // (e.g. a non-store Property create — seeders, observers) must NOT
    // inherit the previous ingest_source: try/finally restored the context.
    $direct = Property::create([
        'user_id' => $user->id,
        'property_type' => 'buy_to_let',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 150000,
        'country' => 'United Kingdom',
    ]);

    $directAudit = AuditLog::where('model_type', Property::class)
        ->where('model_id', $direct->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($directAudit)->not->toBeNull();
    expect($directAudit->metadata['ingest_source'] ?? null)->toBeNull();
});
