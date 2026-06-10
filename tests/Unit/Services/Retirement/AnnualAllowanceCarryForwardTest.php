<?php

declare(strict_types=1);

use App\Models\PensionInputHistory;
use App\Models\RetirementProfile;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\TaxConfigService;

// Ensure TaxConfiguration is present for each test (RefreshDatabase rolls it
// back; the Pest.php global beforeEach is a guard only — not enough on its own
// when TaxConfigService is scoped and called via app() inside tests).
beforeEach(function () {
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }
    // Flush the scoped TaxConfigService instance so the fresh DB row is picked up.
    app()->forgetScopedInstances();
});

/**
 * Derive the three prior tax-year labels for a given current tax-year string.
 * E.g. '2026/27' → ['2023/24', '2024/25', '2025/26']
 */
function priorYearsFor(string $currentTaxYear): array
{
    $start = (int) substr($currentTaxYear, 0, 4);

    return [
        ($start - 3).'/'.substr((string) ($start - 2), -2),
        ($start - 2).'/'.substr((string) ($start - 1), -2),
        ($start - 1).'/'.substr((string) $start, -2),
    ];
}

it('computes carry-forward from captured pension input history when present', function () {
    $user = User::factory()->create();
    $standard = (float) (app(TaxConfigService::class)->getPensionAllowances()['annual_allowance'] ?? 60000);

    $currentTaxYear = app(TaxConfigService::class)->getTaxYear(); // e.g. '2026/27'
    $priorYears = priorYearsFor($currentTaxYear);

    foreach ($priorYears as $year) {
        PensionInputHistory::create([
            'user_id' => $user->id,
            'tax_year' => $year,
            'pension_input_amount' => 30000,
        ]);
    }

    $carryForward = app(AnnualAllowanceChecker::class)->getCarryForward($user->id, $currentTaxYear);

    expect($carryForward)->toBe(3 * ($standard - 30000.0));
});

it('falls back to the manual profile field when no history is captured', function () {
    $user = User::factory()->create();
    $currentTaxYear = app(TaxConfigService::class)->getTaxYear();
    $priorYears = priorYearsFor($currentTaxYear);

    // Set manual prior-year data only — no PensionInputHistory rows.
    RetirementProfile::factory()->create([
        'user_id' => $user->id,
        'prior_year_unused_allowance' => [
            $priorYears[0] => 5000,
            $priorYears[1] => 8000,
            $priorYears[2] => 12000,
        ],
    ]);

    $carryForward = app(AnnualAllowanceChecker::class)->getCarryForward($user->id, $currentTaxYear);

    expect($carryForward)->toBe(25000.0);
});

it('caps per-year unused at zero when a year exceeded the allowance', function () {
    $user = User::factory()->create();
    $standard = (float) (app(TaxConfigService::class)->getPensionAllowances()['annual_allowance'] ?? 60000);

    $currentTaxYear = app(TaxConfigService::class)->getTaxYear();
    $priorYears = priorYearsFor($currentTaxYear);

    // First year: over-contribution (should contribute 0 carry-forward)
    PensionInputHistory::create([
        'user_id' => $user->id,
        'tax_year' => $priorYears[0],
        'pension_input_amount' => $standard + 40000,
    ]);

    // Second and third years: 30000 each
    foreach ([$priorYears[1], $priorYears[2]] as $year) {
        PensionInputHistory::create([
            'user_id' => $user->id,
            'tax_year' => $year,
            'pension_input_amount' => 30000,
        ]);
    }

    $carryForward = app(AnnualAllowanceChecker::class)->getCarryForward($user->id, $currentTaxYear);

    // Over-contributed year = 0, two years at (standard - 30000) each
    expect($carryForward)->toBe(2 * ($standard - 30000.0));
});

it('history path and strategy path produce consistent per-year unused totals', function () {
    $user = User::factory()->create();
    $standard = (float) (app(TaxConfigService::class)->getPensionAllowances()['annual_allowance'] ?? 60000);

    $currentTaxYear = app(TaxConfigService::class)->getTaxYear();
    $priorYears = priorYearsFor($currentTaxYear);
    $amounts = [20000, 35000, 45000];

    foreach ($priorYears as $i => $year) {
        PensionInputHistory::create([
            'user_id' => $user->id,
            'tax_year' => $year,
            'pension_input_amount' => $amounts[$i],
        ]);
    }

    // AnnualAllowanceChecker path
    $checkerTotal = app(AnnualAllowanceChecker::class)->getCarryForward($user->id, $currentTaxYear);

    // Manual re-computation (mirrors PensionAACarryForwardStrategy logic)
    $strategyTotal = array_sum(array_map(
        fn (float $input) => max(0.0, $standard - $input),
        $amounts,
    ));

    expect($checkerTotal)->toBe($strategyTotal);
});
