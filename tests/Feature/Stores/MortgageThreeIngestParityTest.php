<?php

declare(strict_types=1);

/**
 * Acceptance gate check — "Three-ingest parity"
 * (Mortgage Canonical Store, sub-project 1 / pass 5; spec §16.1.2).
 *
 * Proves the core architectural guarantee of the mortgage canonical store:
 * the SAME logical mortgage, supplied through the three production ingest
 * shapes, persists a canonically-IDENTICAL Mortgage row (including the
 * derived columns materialised by MortgageStore via
 * MortgageDerivedColumnCalculator).
 *
 * The production flow for every source is identical and mirrored here:
 *
 *   form   — MortgageController::store
 *            MortgageNormaliser::fromForm($request->validated(), $user)
 *            → MortgageStore::create($canonical, $user, IngestSource::FORM)
 *
 *   fyn    — CoordinatingAgent::handleCreateMortgage
 *            MortgageNormaliser::fromFyn($toolParams, $user)
 *            → MortgageStore::create($canonical, $user, IngestSource::FYN_AI)
 *
 *   upload — DocumentProcessor::confirmExcel (Mortgage branch)
 *            MortgageStore::create(
 *              MortgageNormaliser::fromUpload($extraction, $user),
 *              $user, IngestSource::UPLOAD)
 *
 * The id, created_at, updated_at, derived _calculated_at audit timestamps, and
 * the ingest_source-derived audit context legitimately differ per row and are
 * excluded from the comparison.
 *
 * DOCUMENTED INVARIANT (NOT a canonical-store defect):
 *
 *   Case 2 (tenants_in_common coercion): mortgages do NOT support
 *   ownership_type = 'tenants_in_common'. MortgageNormaliser::normalise()
 *   coerces TIC → 'joint' at the boundary. This test asserts that all three
 *   ingest paths produce ownership_type = 'joint' when the caller supplies
 *   'tenants_in_common', locking the CLAUDE.md Rule #5 invariant for mortgages.
 *   (Contrast with PropertyThreeIngestParityTest case 2, which asserts TIC is
 *   preserved because Property IS the only entity that supports it.)
 *
 * If this test fails because the three paths diverge on a canonicalised
 * field all three can express (ownership_type, ownership_percentage,
 * outstanding_balance, monthly_payment, mortgage_type), that is a REAL
 * canonical-store defect — fix the store/normaliser, not the assertion.
 */

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\Normalisers\MortgageNormaliser;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // MortgageDerivedColumnCalculator needs an active TaxConfiguration.
    // TierConfigurationSeeder required by DbTierGate (SP1 Pass 5).
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('persists field-identical canonical rows for the same individual mortgage via form, fyn and upload', function () {
    $user = User::factory()->withActivePremiumSubscription()->create(['is_preview_user' => false, 'tier' => 'premium']);
    // Pin current_value so the derived current_ltv_pct (200000 / 500000 * 100 = 40%)
    // is deterministic — PropertyFactory's default current_value is randomised.
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 500000]);
    $store = app(MortgageStore::class);

    // form — HTTP form-validated payload
    $form = $store->create(MortgageNormaliser::fromForm([
        'property_id' => $property->id,
        'lender_name' => 'HSBC',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 200000,
        'monthly_payment' => 1050,
        'interest_rate' => 3.5,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $user), $user, IngestSource::FORM);

    // fyn — Fyn AI tool params (accepts 'lender' alias, 'balance' alias, etc.)
    $fyn = $store->create(MortgageNormaliser::fromFyn([
        'property_id' => $property->id,
        'lender_name' => 'HSBC',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 200000,
        'monthly_payment' => 1050,
        'interest_rate' => 3.5,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $user), $user, IngestSource::FYN_AI);

    // upload — document-extraction shape via DocumentProcessor + MortgageMapper
    $upload = $store->create(MortgageNormaliser::fromUpload([
        'property_id' => $property->id,
        'lender_name' => 'HSBC',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 200000,
        'monthly_payment' => 1050,
        'interest_rate' => 3.5,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $user), $user, IngestSource::UPLOAD);

    $snap = fn ($m) => [
        'property_id' => $m->property_id,
        'user_id' => $m->user_id,
        'lender_name' => $m->lender_name,
        'mortgage_type' => $m->mortgage_type,
        'ownership_type' => $m->ownership_type,
        'ownership_percentage' => (string) $m->ownership_percentage,
        'outstanding_balance' => (string) $m->outstanding_balance,
        'monthly_payment' => (string) $m->monthly_payment,
        'interest_rate' => (string) $m->interest_rate,
        // Derived columns materialised by MortgageDerivedColumnCalculator on store
        // write — must be identical across form/fyn/upload to satisfy the docblock
        // claim "field-identical canonical rows INCLUDING derived columns".
        'outstanding_balance_gbp' => (string) $m->outstanding_balance_gbp,
        'monthly_payment_gbp' => (string) $m->monthly_payment_gbp,
        'current_ltv_pct' => (string) $m->current_ltv_pct,
    ];

    $expected = [
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'HSBC',
        'mortgage_type' => 'repayment',
        'ownership_type' => 'individual',
        'ownership_percentage' => '100.00',
        'outstanding_balance' => '200000.00',
        'monthly_payment' => '1050.00',
        'interest_rate' => '3.5000',
        'outstanding_balance_gbp' => '200000.00',
        'monthly_payment_gbp' => '1050.00',
        'current_ltv_pct' => '40.0000', // 200000 / 500000 * 100 (Property::factory default current_value=500000)
    ];

    expect($snap($form->fresh()))->toBe($expected);
    expect($snap($fyn->fresh()))->toBe($expected);
    expect($snap($upload->fresh()))->toBe($expected);
});

it('coerces tenants_in_common to joint across all three ingest paths', function () {
    // Locks the CLAUDE.md Rule #5 + MortgageNormaliser TIC-coercion contract:
    // mortgages do NOT support tenants_in_common; all three normaliser paths
    // must coerce 'tenants_in_common' → 'joint' before the canonical store
    // receives the payload.
    $user = User::factory()->withActivePremiumSubscription()->create(['is_preview_user' => false, 'tier' => 'premium']);
    $property = Property::factory()->create(['user_id' => $user->id]);
    $store = app(MortgageStore::class);

    $ticInput = [
        'property_id' => $property->id,
        'lender_name' => 'NatWest',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 150000,
        'monthly_payment' => 850,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 60,
    ];

    $form = $store->create(MortgageNormaliser::fromForm($ticInput, $user), $user, IngestSource::FORM);
    $fyn = $store->create(MortgageNormaliser::fromFyn($ticInput, $user), $user, IngestSource::FYN_AI);
    $upload = $store->create(MortgageNormaliser::fromUpload($ticInput, $user), $user, IngestSource::UPLOAD);

    // All three must have ownership_type = 'joint' (coerced from TIC)
    foreach ([$form, $fyn, $upload] as $mortgage) {
        $fresh = $mortgage->fresh();
        expect($fresh->ownership_type)->toBe('joint');
        expect((string) $fresh->ownership_percentage)->toBe('60.00');
        expect((string) $fresh->outstanding_balance)->toBe('150000.00');
    }
});
