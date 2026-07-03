<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Services\Marketing\PensionEstimateService;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Locks the PensionEstimateService deterministic model to the constants agreed
 * in the pensioncheck plan (task-B1-brief.md). Every expected value is derived
 * in the test from the same band midpoints and growth constants the service
 * uses — no bare asserted literals without a derivation comment.
 *
 * TaxConfigurationSeeder is re-seeded in beforeEach so TaxConfigService returns
 * the canonical 2026/27 figures (higher-rate threshold, state pension age, etc.).
 */
beforeEach(function () {
    TaxConfiguration::query()->delete();
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(PensionEstimateService::class);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Compute the expected projected pot from the same constants the service uses.
 *
 * @param  float  $potMidpoint  POT_MIDPOINTS[band]
 * @param  float  $monthlyContrib  AUTO_ENROLMENT_TOTAL_PCT * income / 12; pass 0 for non-contributors
 * @param  int  $years  max(0, retirement_age - age_midpoint)
 */
function expectedPot(float $potMidpoint, float $monthlyContrib, int $years): float
{
    if ($years <= 0) {
        return round($potMidpoint);
    }

    $months = $years * 12;
    // REAL_GROWTH_RATE = 0.025 — conservative real-terms marketing assumption
    $monthlyRate = 1.025 ** (1.0 / 12) - 1.0;
    $growthFactor = (1.0 + $monthlyRate) ** $months;

    $potFv = $potMidpoint * $growthFactor;
    $contribFv = $monthlyContrib > 0.0
        ? $monthlyContrib * ($growthFactor - 1.0) / $monthlyRate
        : 0.0;

    return round($potFv + $contribFv);
}

// ---------------------------------------------------------------------------
// Known-band deterministic projection
// ---------------------------------------------------------------------------

it('projects a 40s full-time earner with a medium pot correctly', function () {
    $result = $this->service->estimate([
        'employment' => 'full-time',
        'income' => 'upto_50270',
        'age' => '40s',
        'pensions' => ['workplace'],
        'pot' => '25k_100k',
        'spouse' => 'no',
    ]);

    // Constants used — matches service private consts:
    $ageMidpoint = 45;      // AGE_MIDPOINTS['40s']
    $potMidpoint = 62500.0; // POT_MIDPOINTS['25k_100k']
    $incomeMidpoint = 30000.0; // INCOME_MIDPOINTS['upto_50270']
    $retirementAge = 67;      // pension.state_pension.future_spa from TaxConfigService
    $yearsToRetirement = $retirementAge - $ageMidpoint; // 22
    // AUTO_ENROLMENT_TOTAL_PCT = 0.08 — statutory auto-enrolment minimum
    $monthlyContrib = round($incomeMidpoint * 0.08 / 12, 2); // £200.00

    expect($result['retirement_age'])->toBe($retirementAge)
        ->and($result['years_to_retirement'])->toBe($yearsToRetirement)
        ->and($result['monthly_contribution_assumed'])->toBe($monthlyContrib)
        ->and($result['projected_pot'])->toBe(expectedPot($potMidpoint, $monthlyContrib, $yearsToRetirement))
        ->and($result['already_retired'])->toBeFalse();
});

it('projects a 30s higher-rate earner with a large pot correctly', function () {
    $result = $this->service->estimate([
        'employment' => 'full-time',
        'income' => '50271_100000',
        'age' => '30s',
        'pensions' => ['personal_sipp'],
        'pot' => '100k_250k',
        'spouse' => 'yes',
    ]);

    $ageMidpoint = 35;       // AGE_MIDPOINTS['30s']
    $potMidpoint = 175000.0; // POT_MIDPOINTS['100k_250k']
    $incomeMidpoint = 75000.0;  // INCOME_MIDPOINTS['50271_100000']
    $retirementAge = 67;
    $yearsToRetirement = $retirementAge - $ageMidpoint; // 32
    $monthlyContrib = round($incomeMidpoint * 0.08 / 12, 2); // £500.00

    expect($result['years_to_retirement'])->toBe($yearsToRetirement)
        ->and($result['monthly_contribution_assumed'])->toBe($monthlyContrib)
        ->and($result['projected_pot'])->toBe(expectedPot($potMidpoint, $monthlyContrib, $yearsToRetirement));
});

it('projects a 50s self-employed earner correctly', function () {
    $result = $this->service->estimate([
        'employment' => 'self-employed',
        'income' => '100001_125140',
        'age' => '50s',
        'pensions' => ['personal_sipp'],
        'pot' => '100k_250k',
        'spouse' => 'no',
    ]);

    $ageMidpoint = 55;       // AGE_MIDPOINTS['50s']
    $potMidpoint = 175000.0; // POT_MIDPOINTS['100k_250k']
    $incomeMidpoint = 112500.0; // INCOME_MIDPOINTS['100001_125140']
    $retirementAge = 67;
    $yearsToRetirement = $retirementAge - $ageMidpoint; // 12
    $monthlyContrib = round($incomeMidpoint * 0.08 / 12, 2); // £750.00

    expect($result['years_to_retirement'])->toBe($yearsToRetirement)
        ->and($result['monthly_contribution_assumed'])->toBe($monthlyContrib)
        ->and($result['projected_pot'])->toBe(expectedPot($potMidpoint, $monthlyContrib, $yearsToRetirement));
});

it('projects a 60_plus part-time earner with 4 years to retirement', function () {
    $result = $this->service->estimate([
        'employment' => 'part-time',
        'income' => 'upto_50270',
        'age' => '60_plus',
        'pensions' => ['workplace'],
        'pot' => 'over_250k',
        'spouse' => 'no',
    ]);

    $ageMidpoint = 63;       // AGE_MIDPOINTS['60_plus']
    $potMidpoint = 300000.0; // POT_MIDPOINTS['over_250k']
    $incomeMidpoint = 30000.0;  // INCOME_MIDPOINTS['upto_50270']
    $retirementAge = 67;
    $yearsToRetirement = $retirementAge - $ageMidpoint; // 4 years — retirement not yet passed
    $monthlyContrib = round($incomeMidpoint * 0.08 / 12, 2);

    expect($result['already_retired'])->toBeFalse()
        ->and($result['years_to_retirement'])->toBe($yearsToRetirement)
        ->and($result['projected_pot'])->toBe(expectedPot($potMidpoint, $monthlyContrib, $yearsToRetirement));
});

// ---------------------------------------------------------------------------
// Non-contributing employment statuses
// ---------------------------------------------------------------------------

it('returns 0 monthly contribution for not-employed and still projects pot growth', function () {
    $result = $this->service->estimate([
        'employment' => 'not-employed',
        'income' => 'upto_50270',
        'age' => '30s',
        'pensions' => ['none'],
        'pot' => 'under_25k',
        'spouse' => 'no',
    ]);

    $potMidpoint = 12500.0; // POT_MIDPOINTS['under_25k']
    $ageMidpoint = 35;      // AGE_MIDPOINTS['30s']
    $retirementAge = 67;
    $yearsToRetirement = $retirementAge - $ageMidpoint; // 32

    expect($result['monthly_contribution_assumed'])->toBe(0.0)
        ->and($result['already_retired'])->toBeFalse()
        ->and($result['years_to_retirement'])->toBe($yearsToRetirement)
        // Pot still compounds at REAL_GROWTH_RATE with 0 contributions
        ->and($result['projected_pot'])->toBe(expectedPot($potMidpoint, 0.0, $yearsToRetirement));
});

// ---------------------------------------------------------------------------
// Already-retired shape (employment === 'retired')
// ---------------------------------------------------------------------------

it('returns the already-retired graceful shape when employment is retired', function () {
    $result = $this->service->estimate([
        'employment' => 'retired',
        'income' => '50271_100000',
        'age' => '60_plus',
        'pensions' => ['final_salary', 'workplace'],
        'pot' => 'over_250k',
        'spouse' => 'yes',
    ]);

    $potMidpoint = 300000.0; // POT_MIDPOINTS['over_250k'] — no compounding when already retired

    expect($result['already_retired'])->toBeTrue()
        ->and($result['projected_pot'])->toBe($potMidpoint)
        ->and($result['years_to_retirement'])->toBe(0)
        ->and($result['monthly_contribution_assumed'])->toBe(0.0)
        ->and($result['retirement_age'])->toBe(67); // retirement_age still reported
});

it('returns already-retired shape even when age band suggests years remaining', function () {
    // employment=retired overrides the age-band calculation
    $result = $this->service->estimate([
        'employment' => 'retired',
        'income' => 'upto_50270',
        'age' => '40s',  // midpoint 45 — would give 22 years if not retired
        'pensions' => ['workplace'],
        'pot' => '25k_100k',
        'spouse' => 'no',
    ]);

    $potMidpoint = 62500.0; // POT_MIDPOINTS['25k_100k']

    expect($result['already_retired'])->toBeTrue()
        ->and($result['projected_pot'])->toBe($potMidpoint)
        ->and($result['years_to_retirement'])->toBe(0)
        ->and($result['monthly_contribution_assumed'])->toBe(0.0);
});

// ---------------------------------------------------------------------------
// Pot band = none and age = under_30 (start from zero)
// ---------------------------------------------------------------------------

it('projects from zero pot for a young earner with no existing pension pot', function () {
    $result = $this->service->estimate([
        'employment' => 'full-time',
        'income' => 'upto_50270',
        'age' => 'under_30',
        'pensions' => [],
        'pot' => 'none',
        'spouse' => 'no',
    ]);

    $potMidpoint = 0.0;     // POT_MIDPOINTS['none']
    $incomeMidpoint = 30000.0; // INCOME_MIDPOINTS['upto_50270']
    $ageMidpoint = 25;      // AGE_MIDPOINTS['under_30']
    $retirementAge = 67;
    $yearsToRetirement = $retirementAge - $ageMidpoint; // 42
    $monthlyContrib = round($incomeMidpoint * 0.08 / 12, 2);

    expect($result['years_to_retirement'])->toBe($yearsToRetirement)
        ->and($result['projected_pot'])->toBe(expectedPot($potMidpoint, $monthlyContrib, $yearsToRetirement))
        ->and($result['projected_pot'])->toBeGreaterThan(0.0); // contributions alone build the pot
});

// ---------------------------------------------------------------------------
// Missing / unknown band keys → documented defaults
// ---------------------------------------------------------------------------

it('applies documented defaults when the answers array is empty', function () {
    $result = $this->service->estimate([]);

    // Default bands: income=upto_50270 (midpoint 30000), age=under_30 (midpoint 25),
    // pot=none (midpoint 0), employment=full-time
    $potMidpoint = 0.0;     // POT_MIDPOINTS['none']
    $incomeMidpoint = 30000.0; // INCOME_MIDPOINTS['upto_50270']
    $ageMidpoint = 25;      // AGE_MIDPOINTS['under_30']
    $retirementAge = 67;
    $yearsToRetirement = $retirementAge - $ageMidpoint;
    $monthlyContrib = round($incomeMidpoint * 0.08 / 12, 2);

    expect($result['already_retired'])->toBeFalse()
        ->and($result['years_to_retirement'])->toBe($yearsToRetirement)
        ->and($result['monthly_contribution_assumed'])->toBe($monthlyContrib)
        ->and($result['projected_pot'])->toBe(expectedPot($potMidpoint, $monthlyContrib, $yearsToRetirement));
});

it('falls back to the basic default for an unrecognised income band', function () {
    $result = $this->service->estimate([
        'employment' => 'full-time',
        'income' => 'unknown_band',    // not in INCOME_MIDPOINTS
        'age' => '40s',
        'pensions' => [],
        'pot' => 'none',
        'spouse' => 'no',
    ]);

    // Falls back to default income band (upto_50270 midpoint 30000)
    $incomeMidpoint = 30000.0;
    $expectedMonthly = round($incomeMidpoint * 0.08 / 12, 2);

    expect($result['monthly_contribution_assumed'])->toBe($expectedMonthly);
});

it('falls back to the under_30 default for an unrecognised age band', function () {
    $result = $this->service->estimate([
        'employment' => 'full-time',
        'income' => 'upto_50270',
        'age' => 'invalid_age',     // not in AGE_MIDPOINTS
        'pensions' => [],
        'pot' => 'none',
        'spouse' => 'no',
    ]);

    $ageMidpoint = 25; // AGE_MIDPOINTS['under_30']
    $retirementAge = 67;

    expect($result['years_to_retirement'])->toBe($retirementAge - $ageMidpoint); // 42
});

it('falls back to none (0) for an unrecognised pot band', function () {
    $result = $this->service->estimate([
        'employment' => 'full-time',
        'income' => 'upto_50270',
        'age' => '40s',
        'pensions' => [],
        'pot' => 'unknown_pot',     // not in POT_MIDPOINTS
        'spouse' => 'no',
    ]);

    // With pot=none (0) and contributions, the whole pot comes from contributions
    $potMidpoint = 0.0;
    $incomeMidpoint = 30000.0;
    $ageMidpoint = 45;
    $retirementAge = 67;
    $yearsToRetirement = $retirementAge - $ageMidpoint;
    $monthlyContrib = round($incomeMidpoint * 0.08 / 12, 2);

    expect($result['projected_pot'])->toBe(expectedPot($potMidpoint, $monthlyContrib, $yearsToRetirement));
});

// ---------------------------------------------------------------------------
// Tax relief notes — sourced from TaxConfigService (thresholds not hardcoded)
// ---------------------------------------------------------------------------

it('gives a basic-rate tax relief note for upto_50270 earners', function () {
    $result = $this->service->estimate(['income' => 'upto_50270']);

    expect($result['tax_relief_note'])
        ->toContain('basic-rate')
        ->toContain('20%');
});

it('gives a higher-rate tax relief note for 50271_100000 earners', function () {
    $result = $this->service->estimate(['income' => '50271_100000']);

    expect($result['tax_relief_note'])
        ->toContain('higher-rate')
        ->toContain('Self Assessment');
});

it('gives a taper-zone note for the 100001_125140 band mentioning Personal Allowance', function () {
    $result = $this->service->estimate(['income' => '100001_125140']);

    // Note must mention the 60% effective rate and the Personal Allowance — no acronyms
    expect($result['tax_relief_note'])
        ->toContain('60%')
        ->toContain('Personal Allowance');
});

it('gives an additional-rate relief note for over_125140 earners', function () {
    $result = $this->service->estimate(['income' => 'over_125140']);

    expect($result['tax_relief_note'])
        ->toContain('45%')
        ->toContain('Self Assessment');
});

it('the tax relief note contains no acronyms (Rule 9)', function () {
    foreach (['upto_50270', '50271_100000', '100001_125140', 'over_125140'] as $band) {
        $note = $this->service->estimate(['income' => $band])['tax_relief_note'];

        // Banned acronyms per Rule 9 (ISA is the only permitted exception)
        expect($note)->not->toContain(' AA ')    // must say Annual Allowance
            ->not->toContain('SIPP')
            ->not->toContain('DB')
            ->not->toContain('DC')
            ->not->toContain('PA ')              // must say Personal Allowance
            ->not->toContain('HMRC');
    }
});

// ---------------------------------------------------------------------------
// Output contract shape
// ---------------------------------------------------------------------------

it('always returns all required output keys', function () {
    $requiredKeys = [
        'projected_pot',
        'retirement_age',
        'years_to_retirement',
        'monthly_contribution_assumed',
        'tax_relief_note',
        'already_retired',
    ];

    foreach (['full-time', 'part-time', 'self-employed', 'retired', 'not-employed'] as $employment) {
        $result = $this->service->estimate(['employment' => $employment]);

        foreach ($requiredKeys as $key) {
            expect(array_key_exists($key, $result))->toBeTrue("Key '{$key}' missing for employment='{$employment}'");
        }
    }
});

it('projected_pot and monthly_contribution_assumed are non-negative', function () {
    $employments = ['full-time', 'part-time', 'self-employed', 'retired', 'not-employed'];
    $incomeBands = ['upto_50270', '50271_100000', '100001_125140', 'over_125140'];
    $ageBands = ['under_30', '30s', '40s', '50s', '60_plus'];

    foreach ($employments as $emp) {
        foreach ($incomeBands as $inc) {
            foreach ($ageBands as $age) {
                $result = $this->service->estimate([
                    'employment' => $emp,
                    'income' => $inc,
                    'age' => $age,
                    'pot' => 'none',
                ]);

                $label = "emp={$emp} inc={$inc} age={$age}";
                expect($result['projected_pot'])->toBeGreaterThanOrEqual(0.0, "negative pot [{$label}]")
                    ->and($result['monthly_contribution_assumed'])->toBeGreaterThanOrEqual(0.0, "negative contribution [{$label}]")
                    ->and($result['years_to_retirement'])->toBeGreaterThanOrEqual(0, "negative years [{$label}]");
            }
        }
    }
});
