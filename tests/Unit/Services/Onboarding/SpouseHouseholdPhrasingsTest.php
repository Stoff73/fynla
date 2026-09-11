<?php

declare(strict_types=1);

use App\Services\Onboarding\SpouseHouseholdPhrasings;

/** Live prod 2026-09-11 sentences — pot and contributions read from the user's own words. */
it('reads the pension pot and the monthly contribution from the live sentence, typos included', function (): void {
    $text = "6500, one it's with 6700 no contributions and a Aviva pension with 75680 in it and she contributes 500 per month. The USA is with Halifax";

    expect(SpouseHouseholdPhrasings::pensionPotValue($text))->toBe(75680.0)
        ->and(SpouseHouseholdPhrasings::pensionContributionAnnual($text))->toBe(6000.0);
});

it('handles the corrected phrasing, pounds, thousands and yearly cadence', function (): void {
    expect(SpouseHouseholdPhrasings::pensionPotValue('65000, an ISA with Halifax with 6700 in it, no contributions, and an Aviva pension with 75680 in it, she contributes 500 per month'))->toBe(75680.0)
        ->and(SpouseHouseholdPhrasings::pensionContributionAnnual('an Aviva pension with 75680 in it, she contributes 500 per month'))->toBe(6000.0)
        ->and(SpouseHouseholdPhrasings::pensionPotValue('her pension pot of £75,680'))->toBe(75680.0)
        ->and(SpouseHouseholdPhrasings::pensionPotValue('a pension worth 75k'))->toBe(75000.0)
        ->and(SpouseHouseholdPhrasings::pensionContributionAnnual('she pays £250 a month into her pension'))->toBe(3000.0)
        ->and(SpouseHouseholdPhrasings::pensionContributionAnnual('puts in 6,000 a year'))->toBe(6000.0);
});

it('never guesses: no amount or no cadence yields nothing', function (): void {
    expect(SpouseHouseholdPhrasings::pensionPotValue('she has a pension with Aviva'))->toBeNull()
        ->and(SpouseHouseholdPhrasings::pensionContributionAnnual('she contributes to a pension'))->toBeNull()
        ->and(SpouseHouseholdPhrasings::pensionContributionAnnual('she contributes 500'))->toBeNull()
        ->and(SpouseHouseholdPhrasings::pensionContributionAnnual('65000 a year salary, no pension'))->toBeNull();
});
