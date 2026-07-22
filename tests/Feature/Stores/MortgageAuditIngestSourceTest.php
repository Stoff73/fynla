<?php

declare(strict_types=1);

// Sub-Project 1, Pass 5 — PR 8.
//
// MortgageStore::create() threads the IngestSource through a scoped
// AuditLog::withContext() so the auto-fired Auditable trait hook records
// metadata['ingest_source'] on the data-change audit row.
//
// The store calls MortgageDerivedColumnCalculator, which needs a valid
// TaxConfiguration record — seed it exactly as the sibling store tests do.
//
// Auditable::shouldAudit() is suppressed during tests by default
// (runningUnitTests() && !auditInTests()); opt in via the documented
// config('audit.in_tests') gate so the audit path actually fires.

use App\Models\AuditLog;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    config(['audit.in_tests' => true]);
});

it('MortgageStore::create writes an audit row with ingest_source = form', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id]);
    $store = app(MortgageStore::class);

    $mortgage = $store->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Barclays',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 180000,
        'monthly_payment' => 950,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::FORM);

    $auditRow = AuditLog::where('model_type', Mortgage::class)
        ->where('model_id', $mortgage->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('form');
});

it('MortgageStore::create writes an audit row with ingest_source = fyn_ai', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id]);
    $store = app(MortgageStore::class);

    $mortgage = $store->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Halifax',
        'mortgage_type' => 'interest_only',
        'outstanding_balance' => 250000,
        'monthly_payment' => 800,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::FYN_AI);

    $auditRow = AuditLog::where('model_type', Mortgage::class)
        ->where('model_id', $mortgage->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});

it('MortgageStore::create writes an audit row with ingest_source = upload', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id]);
    $store = app(MortgageStore::class);

    $mortgage = $store->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Nationwide',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 120000,
        'monthly_payment' => 700,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::UPLOAD);

    $auditRow = AuditLog::where('model_type', Mortgage::class)
        ->where('model_id', $mortgage->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('upload');
});

it('MortgageStore::create writes an audit row with ingest_source = seeder', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id]);
    $store = app(MortgageStore::class);

    $mortgage = $store->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Lloyds',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 90000,
        'monthly_payment' => 550,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::SEEDER);

    $auditRow = AuditLog::where('model_type', Mortgage::class)
        ->where('model_id', $mortgage->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('seeder');
});

it('MortgageStore::create writes an audit row with ingest_source = admin', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id]);
    $store = app(MortgageStore::class);

    $mortgage = $store->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Santander',
        'mortgage_type' => 'mixed',
        'outstanding_balance' => 300000,
        'monthly_payment' => 1200,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::ADMIN);

    $auditRow = AuditLog::where('model_type', Mortgage::class)
        ->where('model_id', $mortgage->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('admin');
});

it('audit context is cleared after the mortgage store call (no leak to a later unrelated write)', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id]);
    $store = app(MortgageStore::class);

    $store->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Halifax',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::UPLOAD);

    // A subsequent audited write OUTSIDE any AuditLog::withContext() scope
    // (e.g. a non-store Mortgage create — seeders, observers) must NOT
    // inherit the previous ingest_source: try/finally restored the context.
    $direct = Mortgage::create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'lender_name' => 'NatWest',
        'mortgage_type' => 'interest_only',
        'outstanding_balance' => 100000,
        'monthly_payment' => 400,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $directAudit = AuditLog::where('model_type', Mortgage::class)
        ->where('model_id', $direct->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($directAudit)->not->toBeNull();
    expect($directAudit->metadata['ingest_source'] ?? null)->toBeNull();
});
