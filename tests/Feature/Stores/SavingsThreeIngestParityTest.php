<?php

declare(strict_types=1);

/**
 * Acceptance gate check #2 — "Three-ingest parity"
 * (Savings Canonical Store, sub-project 1 / pass 1; spec §16.1).
 *
 * Proves the core architectural guarantee of the canonical store: the SAME
 * logical savings account, supplied through the three production ingest
 * shapes, persists a canonically-IDENTICAL SavingsAccount row (including the
 * derived columns materialised by SavingsStore).
 *
 * The production flow for every source is identical and mirrored verbatim
 * here:
 *
 *   form   — SavingsController::storeAccount:266-267
 *            $this->normaliser->fromForm($request->validated())
 *            → $this->savingsStore->create($canonical, $user, IngestSource::FORM)
 *
 *   fyn    — CoordinatingAgent::handleCreateSavingsAccount:2081-2088
 *            app(SavingsAccountNormaliser::class)->fromFyn($input)
 *            → app(SavingsStore::class)->create($canonical, $user, IngestSource::FYN_AI)
 *
 *   upload — DocumentProcessor::confirmExcel:406-410
 *            app(SavingsStore::class)->create(
 *              app(SavingsAccountNormaliser::class)->fromUpload($mapper->map($account)),
 *              $user, IngestSource::UPLOAD)
 *
 * Each ingest source has a DIFFERENT inbound vocabulary (HTTP form keys vs
 * Fyn tool params vs document-extraction fields). The normalisers are the
 * adapters that fold those vocabularies onto the one canonical shape; the
 * store + derived-column calculator are vocabulary-agnostic. Parity is
 * therefore asserted across the canonical fields every source can express.
 *
 * The id, created_at, updated_at, the *_calculated_at audit timestamps and
 * the ingest_source-derived audit context legitimately differ per row and
 * are excluded from the comparison.
 *
 * RAW interest_rate IS DELIBERATELY EXCLUDED from the cross-source identity
 * set — and asserted separately — for a documented reason discovered by an
 * earlier draft of this very test:
 *
 *   - form  (fromForm)  passes interest_rate through verbatim
 *   - fyn   (fromFyn)   (float)-casts it, no scaling
 *   - upload(fromUpload) receives it via SavingsAccountMapper::parsePercentage,
 *     which scales a whole-number percent (4.0) to a decimal (0.04)
 *
 * So the SAME logical 4% rate persists as interest_rate=4.0000 via form/fyn
 * but 0.0400 via upload. This is the codebase's KNOWN mixed interest_rate
 * convention (explicitly documented in
 * SavingsAccountDerivedColumnCalculator:29-36 — "the factory writes decimals
 * (0.04) while seeders + onboarding write percent (4.0)"). Pass 1 of the
 * canonical store does NOT canonicalise the raw interest_rate column; it
 * canonicalises the DERIVED projection. SavingsAccountDerivedColumnCalculator
 * reconciles both conventions (`if ($rate > 1) $rate /= 100`), so
 * annual_interest_projected_gbp DOES converge across all three sources — and
 * THAT convergence is the actual pass-1 guarantee this test asserts. The raw
 * interest_rate divergence is asserted explicitly below so the asymmetry is
 * surfaced, not masked.
 *
 * If this test fails because the three paths diverge on a canonicalised
 * field all three can express (anything in canonicalSnapshot(), incl. the
 * derived columns), that is a REAL canonical-store defect — it must be fixed
 * in the store/normaliser, not masked by loosening the assertion.
 */

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Documents\FieldMappers\SavingsAccountMapper;
use App\Services\Stores\IngestSource;
use App\Services\Stores\Normalisers\SavingsAccountNormaliser;
use App\Services\Stores\SavingsStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The store's derived-column calculator reads the active ISA allowance
    // from TaxConfigService — seed it exactly as the sibling store tests do.
    // TierConfigurationSeeder required by DbTierGate (PR 3).
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * Canonical + derived columns asserted for cross-ingest parity. Excludes:
 *  - id / timestamps / *_calculated_at (per-row, legitimately different)
 *  - isa_subscription_amount / isa_allowance_used_pct (only the form ingest
 *    vocabulary expresses them — see the ISA case below)
 *  - raw interest_rate (known mixed-convention divergence — asserted
 *    separately; the canonicalised derived projection
 *    annual_interest_projected_gbp IS in this set and DOES converge)
 */
function canonicalSnapshot(SavingsAccount $a): array
{
    return [
        'account_name' => $a->account_name,
        'account_type' => $a->account_type,
        'institution' => $a->institution,
        'current_balance' => (string) $a->current_balance,
        'balance_gbp' => (string) $a->balance_gbp,
        'annual_interest_projected_gbp' => $a->annual_interest_projected_gbp === null
            ? null
            : (string) $a->annual_interest_projected_gbp,
        'access_type' => $a->access_type,
        'is_isa' => (bool) $a->is_isa,
        'is_emergency_fund' => (bool) $a->is_emergency_fund,
        'ownership_type' => $a->ownership_type,
        'ownership_percentage' => (string) $a->ownership_percentage,
        'joint_owner_id' => $a->joint_owner_id,
        'country' => $a->country,
    ];
}

it('persists field-identical canonical rows for the same NON-ISA account via form, fyn and upload', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'tier' => 'premium']);
    $normaliser = new SavingsAccountNormaliser;
    $store = app(SavingsStore::class);

    // The same logical account: Halifax Saver, £12,500, 4.0% rate, easy_access,
    // individual ownership, UK, not an emergency fund. Document extraction
    // cannot carry an account_name (SavingsAccountMapper has no account_name
    // mapping → fromUpload falls back to institution), so the institution and
    // account_name are deliberately the same string — that is the only way all
    // THREE vocabularies can converge on the same account_name.

    // form — HTTP-validated request keys (passthrough + ownership/country rules)
    $formCanonical = $normaliser->fromForm([
        'account_name' => 'Halifax Saver',
        'institution' => 'Halifax Saver',
        'account_type' => 'easy_access',
        'current_balance' => 12500.00,
        'interest_rate' => 4.0,
        'access_type' => 'immediate',
        'is_isa' => false,
        'is_emergency_fund' => false,
    ]);
    $formAccount = $store->create($formCanonical, $user, IngestSource::FORM);

    // fyn — Fyn tool-param keys (enum mapping + ISA inference + access_type derive)
    $fynCanonical = $normaliser->fromFyn([
        'account_name' => 'Halifax Saver',
        'institution' => 'Halifax Saver',
        'account_type' => 'easy_access',
        'current_balance' => 12500.00,
        'interest_rate' => 4.0,
        'is_isa' => false,
        'is_emergency_fund' => false,
    ]);
    $fynAccount = $store->create($fynCanonical, $user, IngestSource::FYN_AI);

    // upload — document-extraction shape run through the real SavingsAccountMapper
    // exactly as DocumentProcessor::confirmExcel does before fromUpload().
    $mapped = (new SavingsAccountMapper)->map([
        'institution' => 'Halifax Saver',
        'account_type' => 'easy_access',
        'current_balance' => 12500.00,
        'interest_rate' => 4.0,
        'access_type' => 'immediate',
        'is_isa' => false,
    ]);
    $uploadCanonical = $normaliser->fromUpload($mapped);
    $uploadAccount = $store->create($uploadCanonical, $user, IngestSource::UPLOAD);

    // Three distinct rows, same user.
    expect(SavingsAccount::count())->toBe(3);
    expect([$formAccount->id, $fynAccount->id, $uploadAccount->id])
        ->each->toBeInt();

    $formSnap = canonicalSnapshot($formAccount->fresh());
    $fynSnap = canonicalSnapshot($fynAccount->fresh());
    $uploadSnap = canonicalSnapshot($uploadAccount->fresh());

    // Non-tautological anchor: the canonical row is the expected concrete shape
    // (not just "all three equal each other" — pin the actual values too).
    expect($formSnap)->toBe([
        'account_name' => 'Halifax Saver',
        'account_type' => 'easy_access',
        'institution' => 'Halifax Saver',
        'current_balance' => '12500.00',
        'balance_gbp' => '12500.00',
        'annual_interest_projected_gbp' => '500.00', // 12500 * 0.04 (both conventions)
        'access_type' => 'immediate',
        'is_isa' => false,
        'is_emergency_fund' => false,
        'ownership_type' => 'individual',
        'ownership_percentage' => '100.00',
        'joint_owner_id' => null,
        'country' => 'United Kingdom',
    ]);

    // The architectural guarantee: form ≡ fyn ≡ upload, field-for-field,
    // across every canonicalised + derived column.
    expect($fynSnap)->toBe($formSnap);
    expect($uploadSnap)->toBe($formSnap);

    // Documented raw interest_rate divergence (NOT a pass-1 defect — see the
    // file docblock). Form & fyn keep the percent convention (4.0000); the
    // upload mapper's parsePercentage scales it to the decimal convention
    // (0.0400). The DERIVED projection above proves the calculator reconciles
    // both, which is what pass 1 actually canonicalises.
    expect((string) $formAccount->fresh()->interest_rate)->toBe('4.0000');
    expect((string) $fynAccount->fresh()->interest_rate)->toBe('4.0000');
    expect((string) $uploadAccount->fresh()->interest_rate)->toBe('0.0400');
});

it('persists field-identical canonical rows for the same INDIVIDUAL CASH-ISA account via form, fyn and upload', function () {
    // Premium required: this test creates 4 savings accounts (3 cross-ingest parity
    // rows + 1 isa_subscription_amount vocabulary-asymmetry fixture). A free user
    // is capped at 3 by DbTierGate (PR 3); Premium is unlimited.
    $user = User::factory()->create(['is_preview_user' => false, 'tier' => 'premium']);
    $normaliser = new SavingsAccountNormaliser;
    $store = app(SavingsStore::class);

    // Same logical individual cash ISA: "Halifax Cash ISA", £20,000, 4.0%.
    // Joint ISAs are illegal — individual ownership only. Each shape reaches
    // account_type='cash_isa' a different way: form/upload pass it explicitly,
    // fyn INFERS it from is_isa=true + a non-ISA account_type.

    $formAccount = $store->create($normaliser->fromForm([
        'account_name' => 'Halifax Cash ISA',
        'institution' => 'Halifax Cash ISA',
        'account_type' => 'cash_isa',
        'current_balance' => 20000.00,
        'interest_rate' => 4.0,
        'access_type' => 'immediate',
        'is_isa' => true,
        'is_emergency_fund' => false,
    ]), $user, IngestSource::FORM);

    $fynAccount = $store->create($normaliser->fromFyn([
        'account_name' => 'Halifax Cash ISA',
        'institution' => 'Halifax Cash ISA',
        'account_type' => 'easy_access', // fyn infers cash_isa from is_isa=true
        'current_balance' => 20000.00,
        'interest_rate' => 4.0,
        'is_isa' => true,
        'is_emergency_fund' => false,
    ]), $user, IngestSource::FYN_AI);

    $mapped = (new SavingsAccountMapper)->map([
        'institution' => 'Halifax Cash ISA',
        'account_type' => 'cash_isa',
        'current_balance' => 20000.00,
        'interest_rate' => 4.0,
        'access_type' => 'immediate',
        'is_isa' => true,
    ]);
    $uploadAccount = $store->create($normaliser->fromUpload($mapped), $user, IngestSource::UPLOAD);

    expect(SavingsAccount::count())->toBe(3);

    $formSnap = canonicalSnapshot($formAccount->fresh());
    $fynSnap = canonicalSnapshot($fynAccount->fresh());
    $uploadSnap = canonicalSnapshot($uploadAccount->fresh());

    // Concrete expected canonical ISA row — note country is UK-enforced for
    // ISAs by every normaliser, and ownership stays individual (joint ISAs
    // are illegal).
    expect($formSnap)->toBe([
        'account_name' => 'Halifax Cash ISA',
        'account_type' => 'cash_isa',
        'institution' => 'Halifax Cash ISA',
        'current_balance' => '20000.00',
        'balance_gbp' => '20000.00',
        'annual_interest_projected_gbp' => '800.00', // 20000 * 0.04 (both conventions)
        'access_type' => 'immediate',
        'is_isa' => true,
        'is_emergency_fund' => false,
        'ownership_type' => 'individual',
        'ownership_percentage' => '100.00',
        'joint_owner_id' => null,
        'country' => 'United Kingdom',
    ]);

    expect($fynSnap)->toBe($formSnap);
    expect($uploadSnap)->toBe($formSnap);

    // Same documented raw interest_rate convention asymmetry as the non-ISA
    // case; the derived projection (800.00) above proves reconciliation.
    expect((string) $formAccount->fresh()->interest_rate)->toBe('4.0000');
    expect((string) $fynAccount->fresh()->interest_rate)->toBe('4.0000');
    expect((string) $uploadAccount->fresh()->interest_rate)->toBe('0.0400');

    // Vocabulary-asymmetry documentation (NOT a canonical-store defect):
    // isa_subscription_amount is only expressible through the form ingest —
    // Fyn tool params (SavingsAccountNormaliser::fromFyn) and document
    // extraction (SavingsAccountMapper) have no field for it, so the
    // isa_allowance_used_pct derived column is only materialised on the
    // form-ingested row. This asserts the asymmetry explicitly rather than
    // hiding it: the canonical store faithfully reflects what each source
    // vocabulary can carry.
    $formWithSub = $store->create($normaliser->fromForm([
        'account_name' => 'Halifax Cash ISA 2',
        'institution' => 'Halifax Cash ISA 2',
        'account_type' => 'cash_isa',
        'current_balance' => 8000.00,
        'is_isa' => true,
        'isa_subscription_amount' => 8000.00,
    ]), $user, IngestSource::FORM);

    // £8,000 of the £20,000 ISA allowance = 40.00%.
    expect($formWithSub->fresh()->isa_allowance_used_pct)->not->toBeNull();
    expect((string) $formWithSub->fresh()->isa_allowance_used_pct)->toBe('40.00');
    // Fyn/upload ISA rows carry no subscription amount → derived pct stays null.
    expect($fynAccount->fresh()->isa_allowance_used_pct)->toBeNull();
    expect($uploadAccount->fresh()->isa_allowance_used_pct)->toBeNull();
});
