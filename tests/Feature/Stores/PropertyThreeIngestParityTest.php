<?php

declare(strict_types=1);

/**
 * Acceptance gate check — "Three-ingest parity"
 * (Property Canonical Store, sub-project 1 / pass 4; spec §16.1.2).
 *
 * Proves the core architectural guarantee of the property canonical store:
 * the SAME logical property, supplied through the three production ingest
 * shapes, persists a canonically-IDENTICAL Property row (including the
 * derived columns materialised by PropertyStore via
 * PropertyDerivedColumnCalculator).
 *
 * The production flow for every source is identical and mirrored here:
 *
 *   form   — PropertyController::store
 *            $this->propertyNormaliser->fromForm($request->validated())
 *            → $this->propertyStore->create($canonical, $user, IngestSource::FORM)
 *
 *   fyn    — CoordinatingAgent::handleCreateProperty
 *            app(PropertyNormaliser::class)->fromFyn($toolParams)
 *            → app(PropertyStore::class)->create($canonical, $user, IngestSource::FYN_AI)
 *
 *   upload — DocumentProcessor::confirmExcel (Property branch)
 *            app(PropertyStore::class)->create(
 *              app(PropertyNormaliser::class)->fromUpload($extraction),
 *              $user, IngestSource::UPLOAD)
 *
 * Each ingest source has a DIFFERENT inbound vocabulary (HTTP form keys vs
 * Fyn tool params vs document-extraction fields). The PropertyNormaliser is
 * the adapter that folds those vocabularies onto the one canonical shape;
 * the store + derived-column calculator are vocabulary-agnostic. Parity is
 * therefore asserted across the canonical fields every source can express.
 *
 * The id, created_at, updated_at, the *_calculated_at audit timestamps and
 * the ingest_source-derived audit context legitimately differ per row and
 * are excluded from the comparison.
 *
 * DOCUMENTED VOCABULARY ASYMMETRY (NOT a canonical-store defect):
 *
 *   Case 2 (tenants_in_common): `joint_owner_name` — form and fyn both
 *   carry this field (fromForm passes all non-mortgage fields through;
 *   fromFyn explicitly whitelists joint_owner_name). fromUpload does NOT
 *   include joint_owner_name in its extraction whitelist — the document
 *   extraction pipeline does not surface joint-owner identity text from
 *   uploaded documents today. The upload-ingest row will have a null
 *   joint_owner_name; form + fyn rows will have 'Jane Doe'. This is a
 *   vocabulary asymmetry, not a canonical-store defect. The assertion
 *   below therefore only asserts joint_owner_name for form + fyn rows,
 *   and explicitly checks it is null for the upload row.
 *
 * If this test fails because the three paths diverge on a canonicalised
 * field all three can express (ownership_type, ownership_percentage,
 * current_value, current_value_gbp, equity_gbp, loan_to_value_pct),
 * that is a REAL canonical-store defect — it must be fixed in the
 * store/normaliser, not masked by loosening the assertion.
 */

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\Normalisers\PropertyNormaliser;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // PropertyDerivedColumnCalculator needs an active TaxConfiguration.
    // TierConfigurationSeeder required by DbTierGate (SP1 Pass 4).
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('persists field-identical canonical rows for the same individual property via form, fyn and upload', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'tier' => 'tier1']);
    $normaliser = new PropertyNormaliser;
    $store = app(PropertyStore::class);

    // Pass 5 PR 6: outstanding_mortgage is canonically derived from MortgageStore.
    // Seed a mortgage for each property via the store so the cross-store recalc
    // reconciles equity_gbp / loan_to_value_pct identically across the three paths.
    $seedMortgage = function (Property $property) use ($user) {
        app(MortgageStore::class)->create([
            'property_id' => $property->id,
            'user_id' => $user->id,
            'lender_name' => 'Test Bank',
            'mortgage_type' => 'repayment',
            'outstanding_balance' => 100000,
            'monthly_payment' => 600,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100.00,
        ], $user, IngestSource::FORM);
    };

    // Form ingest — HTTP form-request validated payload.
    $form = $store->create($normaliser->fromForm([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'country' => 'United Kingdom',
        'current_value' => 350000,
        'outstanding_mortgage' => 100000,
    ]), $user, IngestSource::FORM);
    $seedMortgage($form);

    // Fyn ingest — uses fromFyn pre-split-field vocabulary. Note: when
    // `address` shorthand is passed alongside city/postcode, fromFyn only
    // sets address_line_1 and skips city/postcode (they are in the else
    // branch that fires when address_line_1 is passed directly). We use
    // the pre-split form here so all three ingest paths produce matching
    // city/postcode columns. Fyn also accepts `value` as shorthand for
    // current_value — verified in PropertyNormaliser::fromFyn().
    $fyn = $store->create($normaliser->fromFyn([
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'country' => 'United Kingdom',
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'value' => 350000,
        'outstanding_mortgage' => 100000,
    ]), $user, IngestSource::FYN_AI);
    $seedMortgage($fyn);

    // Upload ingest — document-extraction shape via DocumentProcessor + PropertyMapper.
    $upload = $store->create($normaliser->fromUpload([
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'country' => 'United Kingdom',
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 350000,
        'outstanding_mortgage' => 100000,
    ]), $user, IngestSource::UPLOAD);
    $seedMortgage($upload);

    $snap = fn (Property $p) => [
        'property_type' => $p->property_type,
        'ownership_type' => $p->ownership_type,
        'ownership_percentage' => (string) $p->ownership_percentage,
        'address_line_1' => $p->address_line_1,
        'city' => $p->city,
        'postcode' => $p->postcode,
        'country' => $p->country,
        'current_value' => (string) $p->current_value,
        'current_value_gbp' => (string) $p->current_value_gbp,
        'outstanding_mortgage' => (string) $p->outstanding_mortgage,
        'equity_gbp' => (string) $p->equity_gbp,
        'loan_to_value_pct' => (string) $p->loan_to_value_pct,
    ];

    $expected = [
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => '100.00',
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'country' => 'United Kingdom',
        'current_value' => '350000.00',
        'current_value_gbp' => '350000.00',
        'outstanding_mortgage' => '100000.00',
        'equity_gbp' => '250000.00',
        'loan_to_value_pct' => '28.57',
    ];

    expect($snap($form->fresh()))->toBe($expected);
    expect($snap($fyn->fresh()))->toBe($expected);
    expect($snap($upload->fresh()))->toBe($expected);
});

it('persists field-identical canonical rows for a tenants_in_common property via form, fyn and upload', function () {
    // Case 2 pins the CLAUDE.md Rule #5 invariant: tenants_in_common is
    // property-only. The Savings/Investment/Estate stores deliberately
    // exclude tenants_in_common; only PropertyStore::validateCanonical
    // allows it. This test locks that contract.
    $user = User::factory()->create(['is_preview_user' => false, 'tier' => 'tier1']);
    $normaliser = new PropertyNormaliser;
    $store = app(PropertyStore::class);

    $base = [
        'property_type' => 'main_residence',
        'ownership_type' => 'tenants_in_common',
        'joint_ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 60,
        'joint_owner_name' => 'Jane Doe',
        'address_line_1' => '10 Tenancy Road',
        'city' => 'Manchester',
        'postcode' => 'M1 1AA',
        'country' => 'United Kingdom',
        'current_value' => 500000,
    ];

    $form = $store->create($normaliser->fromForm($base), $user, IngestSource::FORM);
    $fyn = $store->create($normaliser->fromFyn($base), $user, IngestSource::FYN_AI);
    $upload = $store->create($normaliser->fromUpload($base), $user, IngestSource::UPLOAD);

    // Assert canonical fields that all three ingest paths can express.
    foreach ([$form, $fyn, $upload] as $p) {
        $fresh = $p->fresh();
        expect($fresh->ownership_type)->toBe('tenants_in_common');
        expect((string) $fresh->ownership_percentage)->toBe('60.00');
        expect((string) $fresh->current_value)->toBe('500000.00');
        expect((string) $fresh->current_value_gbp)->toBe('500000.00');
    }

    // joint_owner_name vocabulary asymmetry: fromForm + fromFyn carry the
    // field; fromUpload does not include joint_owner_name in its extraction
    // whitelist (document extraction does not surface joint-owner identity).
    // Assert the asymmetry explicitly so regressions are visible.
    expect($form->fresh()->joint_owner_name)->toBe('Jane Doe');
    expect($fyn->fresh()->joint_owner_name)->toBe('Jane Doe');
    expect($upload->fresh()->joint_owner_name)->toBeNull();
});
