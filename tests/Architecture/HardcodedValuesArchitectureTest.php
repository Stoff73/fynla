<?php

declare(strict_types=1);

/**
 * Hardcoded Financial Values Architecture Tests (Sprint 5 Task 5.1)
 *
 * These tests ensure that financial values (tax allowances, growth rates,
 * withdrawal rates, protection thresholds) are sourced from TaxConfigService
 * rather than being hardcoded in service files.
 *
 * Allowable patterns:
 *   - Fallback defaults in $this->taxConfig->get('key', fallback) calls
 *   - Constants named FALLBACK_*
 *   - Asset class lookup tables (HoldingsDataExtractor, PortfolioStatisticsCalculator)
 *   - TaxConfigService itself
 *   - Test files
 *   - Default parameter values in function signatures
 */

// Project root resolved from tests/Architecture/
$projectRoot = realpath(__DIR__.'/../../');

/**
 * Scan PHP files in a directory for a pattern, excluding lines matching exclusions.
 * Returns the count of violations found.
 */
function countHardcodedViolations(string $directory, string $pattern, array $exclusions = []): int
{
    if (! is_dir($directory)) {
        return 0;
    }

    $violations = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $lineNum => $line) {
            if (preg_match($pattern, $line)) {
                $excluded = false;
                foreach ($exclusions as $exclusion) {
                    if (str_contains($line, $exclusion) || str_contains($file->getFilename(), $exclusion)) {
                        $excluded = true;
                        break;
                    }
                }
                if (! $excluded) {
                    $violations++;
                }
            }
        }
    }

    return $violations;
}

describe('Hardcoded Financial Values', function () use ($projectRoot) {

    it('has no hardcoded PSA values in services', function () use ($projectRoot) {
        // Search for standalone assignments of PSA amounts (1000, 500) directly to
        // personalSavingsAllowance or psa variables, outside of TaxConfigService and tests.
        // Legitimate patterns: $this->taxConfig->getPersonalSavingsAllowance() and
        // ->get('...personal_savings_allowance...', fallback) are fine.
        $count = countHardcodedViolations(
            $projectRoot.'/app/Services/',
            '/personalSavingsAllowance\s*=\s*(1000|500|0)\b/',
            ['TaxConfig', 'taxConfig', 'getPersonal', '->get(']
        );

        expect($count)->toBe(0, 'Hardcoded PSA values found in services. Use TaxConfigService::getPersonalSavingsAllowance() instead.');
    });

    it('has no hardcoded growth rates in Investment services', function () use ($projectRoot) {
        // Search for standalone assignments like `= 0.06` in Investment services.
        // Exclude asset class lookup tables in HoldingsDataExtractor and
        // PortfolioStatisticsCalculator which legitimately define per-asset-class returns.
        $count = countHardcodedViolations(
            $projectRoot.'/app/Services/Investment/',
            '/=\s*0\.06[^0-9]/',
            ['HoldingsDataExtractor', 'PortfolioStatisticsCalculator', 'FALLBACK_', 'taxConfig', '->get(']
        );

        expect($count)->toBe(0, 'Hardcoded 0.06 growth rates found in Investment services. Use TaxConfigService or named FALLBACK_ constants.');
    });

    it('has no hardcoded withdrawal rates in Retirement services', function () use ($projectRoot) {
        // Search for standalone assignments of 0.047 withdrawal rate.
        // Fallback defaults in ->get('retirement.withdrawal_rates.sustainable', 0.047) are OK.
        $count = countHardcodedViolations(
            $projectRoot.'/app/Services/Retirement/',
            '/=\s*0\.047/',
            ['->get(', 'FALLBACK_', 'taxConfig', 'TaxConfig']
        );

        expect($count)->toBe(0, 'Hardcoded 0.047 withdrawal rates found in Retirement services. Use TaxConfigService with fallback defaults.');
    });

    it('has no hardcoded growth rates in Estate services', function () use ($projectRoot) {
        // Search for standalone 0.047 return rates in Estate services.
        // FALLBACK_INVESTMENT_RETURN_RATE constants are acceptable.
        // Fallback defaults in ->get() calls are acceptable.
        // Default parameter values in function signatures (float $var = 0.047) are acceptable.
        // Method-level fallback returns (return 0.047) in methods that check config first are acceptable.
        $count = countHardcodedViolations(
            $projectRoot.'/app/Services/Estate/',
            '/=\s*0\.047/',
            ['FALLBACK_', '->get(', 'taxConfig', 'TaxConfig', 'float $']
        );

        expect($count)->toBe(0, 'Hardcoded 0.047 growth rates found in Estate services. Use FALLBACK_ constants or TaxConfigService.');
    });

    it('has no hardcoded protection values as standalone assignments', function () use ($projectRoot) {
        // Search for hardcoded protection values (7500 final expenses, 9000 education cost,
        // 0.60 IP max, 0.50 premium base) as standalone assignments outside of ->get() fallbacks.
        // All Protection services should source these from $this->taxConfig->get('protection.*', fallback).
        $protectionDir = $projectRoot.'/app/Services/Protection/';
        $commonExclusions = ['->get(', 'taxConfig', 'TaxConfig', 'FALLBACK_'];

        $violations = 0;

        // Final expenses hardcoded outside fallback
        $violations += countHardcodedViolations(
            $protectionDir,
            '/final_expenses\s*=\s*7500|FINAL_EXPENSES\s*=\s*7500/',
            $commonExclusions
        );

        // Education cost hardcoded outside fallback
        $violations += countHardcodedViolations(
            $protectionDir,
            '/education_cost\s*=\s*9000|EDUCATION_COST\s*=\s*9000/',
            $commonExclusions
        );

        // IP max benefit hardcoded outside fallback
        $violations += countHardcodedViolations(
            $protectionDir,
            '/ip_max.*=\s*0\.60|IP_MAX.*=\s*0\.60/',
            $commonExclusions
        );

        // Premium base rate hardcoded outside fallback
        $violations += countHardcodedViolations(
            $protectionDir,
            '/base_rate.*=\s*0\.50|BASE_RATE.*=\s*0\.50/',
            $commonExclusions
        );

        expect($violations)->toBe(0, 'Hardcoded protection values found. Use TaxConfigService->get() with fallback defaults.');
    });

    it('has no deprecated EstateDefaults estimated or default constants', function () use ($projectRoot) {
        // EstateDefaults::ESTIMATED_* and EstateDefaults::DEFAULT_LIFE_*,
        // EstateDefaults::DEFAULT_CURRENT_* were migrated to TaxConfigService.
        // No code should reference these removed constants.
        $count = countHardcodedViolations(
            $projectRoot.'/app/',
            '/EstateDefaults::ESTIMATED_|EstateDefaults::DEFAULT_LIFE|EstateDefaults::DEFAULT_CURRENT/',
            []
        );

        expect($count)->toBe(0, 'Deprecated EstateDefaults::ESTIMATED_*/DEFAULT_LIFE*/DEFAULT_CURRENT* references found. These have been migrated to TaxConfigService.');
    });

    it('has no hardcoded tax band thresholds in service files', function () use ($projectRoot) {
        // Search for hardcoded personal allowance (12570), higher rate threshold (50270),
        // or additional rate threshold (125140) as standalone assignments in services.
        // TaxConfigService, TaxDefaults constants, and ->get() fallbacks are excluded.
        $count = countHardcodedViolations(
            $projectRoot.'/app/Services/',
            '/=\s*(12570|50270|125140)\b/',
            ['TaxConfig', 'taxConfig', 'TaxDefaults', '->get(', 'FALLBACK_']
        );

        expect($count)->toBe(0, 'Hardcoded tax band thresholds found in services. Use TaxConfigService or TaxDefaults constants.');
    });

    it('has no hardcoded IHT nil rate band in service files', function () use ($projectRoot) {
        // IHT NRB (325000) and RNRB (175000) should come from TaxConfigService.
        // TaxDefaults constants and EstateDefaults threshold constants are acceptable.
        $count = countHardcodedViolations(
            $projectRoot.'/app/Services/',
            '/=\s*(325000|175000)\b/',
            ['TaxConfig', 'taxConfig', 'TaxDefaults', 'EstateDefaults', '->get(', 'FALLBACK_', 'getInheritanceTax']
        );

        expect($count)->toBe(0, 'Hardcoded IHT nil rate band values found in services. Use TaxConfigService::getInheritanceTax().');
    });
});

describe('Strict Types in Service Files', function () {

    arch('all investment recommendation services use strict types')
        ->expect('App\Services\Investment\Recommendation')
        ->toUseStrictTypes();

    arch('all savings services use strict types')
        ->expect('App\Services\Savings')
        ->toUseStrictTypes();

    arch('all retirement services use strict types')
        ->expect('App\Services\Retirement')
        ->toUseStrictTypes();

    arch('all estate services use strict types')
        ->expect('App\Services\Estate')
        ->toUseStrictTypes();

    arch('all protection services use strict types')
        ->expect('App\Services\Protection')
        ->toUseStrictTypes();

    arch('all coordination services use strict types')
        ->expect('App\Services\Coordination')
        ->toUseStrictTypes();
});
