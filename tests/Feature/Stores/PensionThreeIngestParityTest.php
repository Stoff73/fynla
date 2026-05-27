<?php

declare(strict_types=1);

/**
 * Acceptance gate check #2 — "Three-ingest parity"
 * (Pensions Canonical Store, sub-project 1 / pass 3; spec §16.1.2 + §16.2.4).
 *
 * Proves the core architectural guarantee of the pensions canonical store:
 * the SAME logical DC pension, supplied through the three production ingest
 * shapes, persists a canonically-IDENTICAL DCPension row (including the
 * derived columns materialised by PensionStore via
 * PensionDerivedColumnCalculator).
 *
 * The production flow for every source is identical and mirrored verbatim
 * here:
 *
 *   form   — RetirementController::storeDcPension:333-334
 *            $this->pensionNormaliser->fromFormDc($data)
 *            → $this->pensionStore->createDc($canonical, $user, IngestSource::FORM)
 *
 *   fyn    — CoordinatingAgent::2406-2407 (post-PR-5g routing)
 *            app(PensionNormaliser::class)->fromFynPension($input)
 *            → app(PensionStore::class)->createDc($canonical, $user, IngestSource::FYN_AI)
 *
 *   upload — DocumentProcessor::confirmExcel:391-392
 *            app(PensionStore::class)->createDc(
 *              app(PensionNormaliser::class)->fromUploadDc($accountData),
 *              $user, IngestSource::UPLOAD)
 *
 * Each ingest source has a DIFFERENT inbound vocabulary (HTTP form keys vs
 * Fyn tool params vs document-extraction fields). The PensionNormaliser is
 * the adapter that folds those vocabularies onto the one canonical shape;
 * the store + derived-column calculator are vocabulary-agnostic. Parity is
 * therefore asserted across the canonical fields every source can express.
 *
 * The id, created_at, updated_at, the *_calculated_at audit timestamps and
 * the ingest_source-derived audit context legitimately differ per row and
 * are excluded from the comparison.
 *
 * DOCUMENTED VOCABULARY ASYMMETRIES (NOT canonical-store defects — the
 * canonical store faithfully reflects what each source can carry):
 *
 *   - `expected_return_percent` — form + fyn (`fromFynPension`'s whitelist)
 *     express it; upload (`fromUploadDc`'s whitelist) does not. Without it,
 *     `projected_value_at_retirement_gbp` is null per the calculator's null
 *     guard. The case below deliberately omits expected_return_percent
 *     across all three paths so all three converge on null projection — the
 *     point is parity, not testing the projection formula (that belongs in
 *     PensionDerivedColumnCalculatorTest).
 *
 *   - Form-only DC fields (has_flexibly_accessed, flexible_access_date,
 *     salary_sacrifice, beneficiary_id, beneficiary_name, risk_preference,
 *     has_custom_risk) — not in fromFyn / fromUpload whitelists. Omitted
 *     here; the store accepts their absence (`sometimes` validators).
 *
 *   - pension_type vs scheme_type — form passes pension_type directly,
 *     upload passes pension_type directly, fyn passes scheme_type which
 *     fromFynPension maps via match() to pension_type. All three converge
 *     on the same canonical pension_type.
 *
 * If this test fails because the three paths diverge on a canonicalised
 * field all three can express (anything in canonicalSnapshot(), incl. the
 * derived columns), that is a REAL canonical-store defect — it must be fixed
 * in the store/normaliser, not masked by loosening the assertion.
 */

use App\Models\DCPension;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\Normalisers\PensionNormaliser;
use App\Services\Stores\PensionStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The store's derived-column calculator reads the pension annual allowance
    // from TaxConfigService — seed it exactly as the sibling store tests do.
    // TierConfigurationSeeder required by DbTierGate (SP1 PR 3).
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * Canonical + derived columns asserted for cross-ingest parity. Excludes:
 *  - id / timestamps / *_calculated_at (per-row, legitimately different)
 *  - projected_value_at_retirement_gbp (vocabulary asymmetry — see docblock;
 *    expected_return_percent is only expressible via form + fyn, not upload,
 *    so all three converge on null projection in the parity case below — but
 *    we exclude it from the snapshot helper so the helper stays reusable
 *    across other parity cases that may supply expected_return_percent via
 *    form/fyn and accept the documented divergence)
 *  - Form-only DC fields (see docblock vocabulary-asymmetry list)
 */
function canonicalDcPensionSnapshot(DCPension $p): array
{
    return [
        'scheme_name' => $p->scheme_name,
        'pension_type' => $p->pension_type,
        'provider' => $p->provider,
        'current_fund_value' => (string) $p->current_fund_value,
        'current_fund_value_gbp' => $p->current_fund_value_gbp === null
            ? null
            : (string) $p->current_fund_value_gbp,
        'annual_contribution_gbp' => $p->annual_contribution_gbp === null
            ? null
            : (string) $p->annual_contribution_gbp,
        'annual_allowance_used_gbp' => $p->annual_allowance_used_gbp === null
            ? null
            : (string) $p->annual_allowance_used_gbp,
        'years_to_drawdown' => $p->years_to_drawdown,
        'monthly_contribution_amount' => $p->monthly_contribution_amount === null
            ? null
            : (string) $p->monthly_contribution_amount,
        'retirement_age' => $p->retirement_age,
        'annual_salary' => $p->annual_salary === null
            ? null
            : (string) $p->annual_salary,
    ];
}

it('persists field-identical canonical rows for the same DC pension via form, fyn and upload', function () {
    // tier1: the test creates 3 DC pensions and the free-tier cap is 5
    // (pension_account, DC + DB combined) — so a free user would also pass.
    // Using tier1 keeps the test independent of future cap changes.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'tier' => 'tier1',
        // years_to_drawdown calculator requires user.date_of_birth — pin it
        // so the derived column is deterministic across all three rows.
        'date_of_birth' => '1985-01-15',
    ]);
    $normaliser = new PensionNormaliser;
    $store = app(PensionStore::class);

    // The same logical DC pension: Aviva SIPP, £50,000 current fund,
    // monthly contribution £500, retirement age 65. expected_return_percent
    // is deliberately omitted across all three (documented asymmetry —
    // upload's fromUploadDc whitelist has no field for it; all three
    // therefore converge on null projection).
    //
    // pension_type/scheme_type asymmetry: form + upload pass pension_type
    // directly; fyn passes scheme_type='sipp' which fromFynPension maps to
    // pension_type='sipp' via match().

    // form — HTTP-validated request keys (RetirementController::storeDcPension)
    $formCanonical = $normaliser->fromFormDc([
        'scheme_name' => 'Aviva SIPP',
        'pension_type' => 'sipp',
        'current_fund_value' => 50000.00,
        'monthly_contribution_amount' => 500.00,
        'retirement_age' => 65,
        'annual_salary' => 45000.00,
    ]);
    $formPension = $store->createDc($formCanonical, $user, IngestSource::FORM);

    // fyn — Fyn tool-param keys (CoordinatingAgent → fromFynPension)
    $fynCanonical = $normaliser->fromFynPension([
        'scheme_name' => 'Aviva SIPP',
        'scheme_type' => 'sipp', // fyn vocabulary; normaliser maps to pension_type='sipp'
        'current_fund_value' => 50000.00,
        'monthly_contribution_amount' => 500.00,
        'retirement_age' => 65,
        'annual_salary' => 45000.00,
    ]);
    $fynPension = $store->createDc($fynCanonical, $user, IngestSource::FYN_AI);

    // upload — document-extraction shape (DocumentProcessor → fromUploadDc)
    $uploadCanonical = $normaliser->fromUploadDc([
        'scheme_name' => 'Aviva SIPP',
        'pension_type' => 'sipp',
        'current_fund_value' => 50000.00,
        'monthly_contribution_amount' => 500.00,
        'retirement_age' => 65,
        'annual_salary' => 45000.00,
    ]);
    $uploadPension = $store->createDc($uploadCanonical, $user, IngestSource::UPLOAD);

    // Three distinct rows, same user.
    expect(DCPension::where('user_id', $user->id)->count())->toBe(3);
    expect([$formPension->id, $fynPension->id, $uploadPension->id])
        ->each->toBeInt();

    $formSnap = canonicalDcPensionSnapshot($formPension->fresh());
    $fynSnap = canonicalDcPensionSnapshot($fynPension->fresh());
    $uploadSnap = canonicalDcPensionSnapshot($uploadPension->fresh());

    // Non-tautological anchor: the canonical row is the expected concrete
    // shape (not just "all three equal each other" — pin the actual values
    // too). Derived columns computed by PensionDerivedColumnCalculator::calculateDc:
    //   - current_fund_value_gbp = 50000 (Pass 3 stores pension fund values in GBP)
    //   - annual_contribution_gbp = monthly_contribution × 12 = 6000
    //   - annual_allowance_used_gbp = 6000 / 60000 AA × 100 = 10
    //   - years_to_drawdown = 65 - (today - 1985-01-15 in years)
    //   - provider falls back to scheme_name across all three normalisers
    expect($formSnap['scheme_name'])->toBe('Aviva SIPP');
    expect($formSnap['pension_type'])->toBe('sipp');
    expect($formSnap['provider'])->toBe('Aviva SIPP');
    expect($formSnap['current_fund_value'])->toBe('50000.00');
    expect($formSnap['current_fund_value_gbp'])->toBe('50000.00');
    expect($formSnap['annual_contribution_gbp'])->toBe('6000.00');
    expect($formSnap['annual_allowance_used_gbp'])->toBe('10.00');
    expect($formSnap['monthly_contribution_amount'])->toBe('500.00');
    expect($formSnap['retirement_age'])->toBe(65);
    expect($formSnap['annual_salary'])->toBe('45000.00');
    expect($formSnap['years_to_drawdown'])->toBeInt()->toBeGreaterThan(20)->toBeLessThan(31);

    // The architectural guarantee: form ≡ fyn ≡ upload, field-for-field,
    // across every canonicalised + derived column the three vocabularies
    // can express.
    expect($fynSnap)->toBe($formSnap);
    expect($uploadSnap)->toBe($formSnap);

    // Documented vocabulary-asymmetry assertion: projected_value_at_retirement_gbp
    // is null on all three rows because expected_return_percent is only in
    // fromForm + fromFyn whitelists, not fromUpload's. Omitting it across
    // all three is the only way to get a clean parity assertion; the
    // calculator's projection formula is tested in
    // PensionDerivedColumnCalculatorTest.
    expect($formPension->fresh()->projected_value_at_retirement_gbp)->toBeNull();
    expect($fynPension->fresh()->projected_value_at_retirement_gbp)->toBeNull();
    expect($uploadPension->fresh()->projected_value_at_retirement_gbp)->toBeNull();
});

it('persists field-identical canonical rows for the same DB pension via form and fyn (upload supported by fromUploadDb too)', function () {
    // DB pensions don't currently have a production upload path in
    // DocumentProcessor — only DC pensions are extracted from uploads
    // today (DocumentProcessor:391 routes DC only; DB pension upload is a
    // future enhancement). fromUploadDb exists in PensionNormaliser as a
    // ready hook for when extraction adds DB recognition; we exercise it
    // here so the contract surface is locked, but the production-path
    // story for DB is form + fyn today.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'tier' => 'tier1',
        'date_of_birth' => '1985-01-15',
    ]);
    $normaliser = new PensionNormaliser;
    $store = app(PensionStore::class);

    // Same logical DB pension: NHS Pension, career_average, £12,000 accrued
    // annual pension, 60% spouse pension, normal retirement age 65.

    $formPension = $store->createDb($normaliser->fromFormDb([
        'scheme_name' => 'NHS Pension',
        'scheme_type' => 'career_average',
        'accrued_annual_pension' => 12000.00,
        'spouse_pension_percent' => 60.00,
        'normal_retirement_age' => 65,
    ]), $user, IngestSource::FORM);

    $fynPension = $store->createDb($normaliser->fromFynPension([
        'pension_category' => 'db', // fyn discriminator → fromFynPension's DB branch
        'scheme_name' => 'NHS Pension',
        'scheme_type' => 'career_average',
        'accrued_annual_pension' => 12000.00,
        'spouse_pension_percent' => 60.00,
        'normal_retirement_age' => 65,
    ]), $user, IngestSource::FYN_AI);

    $uploadPension = $store->createDb($normaliser->fromUploadDb([
        'scheme_name' => 'NHS Pension',
        'scheme_type' => 'career_average',
        'accrued_annual_pension' => 12000.00,
        'spouse_pension_percent' => 60.00,
        'normal_retirement_age' => 65,
    ]), $user, IngestSource::UPLOAD);

    // Canonical + derived DB columns: projected_annual_pension_at_nra_gbp
    // = accrued_annual_pension; spouse_pension_projected_gbp = annual × spouse_pct/100.
    foreach ([$formPension, $fynPension, $uploadPension] as $p) {
        $fresh = $p->fresh();
        expect($fresh->scheme_name)->toBe('NHS Pension');
        expect($fresh->scheme_type)->toBe('career_average');
        expect((string) $fresh->accrued_annual_pension)->toBe('12000.00');
        expect((string) $fresh->spouse_pension_percent)->toBe('60.00');
        expect($fresh->normal_retirement_age)->toBe(65);
        expect((string) $fresh->projected_annual_pension_at_nra_gbp)->toBe('12000.00');
        expect((string) $fresh->spouse_pension_projected_gbp)->toBe('7200.00');
    }
});
