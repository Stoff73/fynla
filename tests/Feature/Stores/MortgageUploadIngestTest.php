<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\Normalisers\MortgageNormaliser;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

// Sub-Project 1, Pass 5, PR 4 — upload-ingest parity for MortgageStore.
//
// Mirrors PropertyUploadIngestTest / PensionUploadIngestTest / SavingsUploadIngestTest:
// drives the canonical upload-equivalent path through MortgageNormaliser::fromUpload
// + MortgageStore::create with IngestSource::UPLOAD, then asserts the row landed AND
// the AuditLog row carries metadata['ingest_source'] = 'upload'. This is the contract
// DocumentProcessor will hit once its mortgage extraction path is wired in.

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    config(['audit.in_tests' => true]);
});

it('creates a mortgage via upload-equivalent path with UPLOAD audit context', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id]);

    $upload = [
        'property_id' => $property->id,
        'lender_name' => 'NatWest',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 220000.00,
        'interest_rate' => 4.75,
        'rate_type' => 'fixed',
        'monthly_payment' => 1350.00,
        'start_date' => '2021-03-01',
        'maturity_date' => '2046-03-01',
        'remaining_term_months' => 270,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    $canonical = MortgageNormaliser::fromUpload($upload, $user);
    $mortgage = app(MortgageStore::class)->create($canonical, $user, IngestSource::UPLOAD);

    expect($mortgage->lender_name)->toBe('NatWest');
    expect((string) $mortgage->outstanding_balance)->toBe('220000.00');
    expect(Mortgage::where('user_id', $user->id)->count())->toBe(1);

    $auditRow = AuditLog::where('model_type', Mortgage::class)
        ->where('model_id', $mortgage->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('upload');
});
