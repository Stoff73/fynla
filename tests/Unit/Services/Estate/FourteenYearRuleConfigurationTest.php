<?php

declare(strict_types=1);

use App\Services\TaxConfigService;

/**
 * W-0526 — the fourteen-year rule has ONE configured home.
 *
 * The rule was implemented correctly all along: `FailedGiftTaxCalculator` searches
 * back (death window + lifetime lookback) years, because a chargeable transfer
 * inside the death window cumulates the seven years before ITSELF. That is where
 * the fourteen years come from — two independent seven-year windows, not one
 * fourteen-year one (IHTM14513).
 *
 * What was wrong is that the configuration said so TWICE. The calculator read
 * `potentially_exempt_transfers` and `chargeable_lifetime_transfers`, while a
 * separate `fourteen_year_rule` block held its own `lookback_for_failed_pets`,
 * `lookback_for_clts` and `maximum_window: 14` that nothing read. An admin moving
 * `maximum_window` to 10 changed nothing; moving the CLT block changed the answer
 * silently. And `maximum_window` is a SUM of the other two, so the block could
 * contradict itself.
 *
 * These tests fail if either home is re-established, or if a literal 7 or 14
 * creeps back into the window arithmetic (Rule 2).
 */
/** The derivation's only collaborator is `get()`, so stubbing it is the whole surface. */
function ruleFor(int $deathWindow, int $lifetimeLookback): array
{
    $service = Mockery::mock(TaxConfigService::class)->makePartial();
    $service->shouldReceive('get')
        ->with('inheritance_tax.chargeable_lifetime_transfers', [])
        ->andReturn(['cumulation_period' => $deathWindow, 'lookback_period' => $lifetimeLookback]);
    $service->shouldReceive('get')
        ->with('inheritance_tax.fourteen_year_rule', [])
        ->andReturn(['description' => 'x', 'calculation_steps' => []]);

    return $service->getFourteenYearRule();
}

describe('the fourteen-year rule reads one configured home', function () {
    it('derives its windows from the transfer rules rather than holding copies', function () {
        $rule = ruleFor(deathWindow: 7, lifetimeLookback: 7);

        expect($rule['lookback_for_failed_pets'])->toBe(7)
            ->and($rule['lookback_for_clts'])->toBe(7)
            ->and($rule['maximum_window'])->toBe(14);
    });

    it('moves the whole rule when a configured window moves', function () {
        // The point of the item. Under a five-year lifetime lookback the outer
        // search bound is twelve years, not fourteen — and nothing may still say
        // fourteen. A hardcoded 14 fails here.
        //
        // Both windows come from the CLT block. `years_to_exemption` on the PET
        // block also holds 7 and answers a DIFFERENT question, so reading it here
        // would be a silent change of meaning while the two keys agree.
        $rule = ruleFor(deathWindow: 7, lifetimeLookback: 5);

        expect($rule['lookback_for_failed_pets'])->toBe(5)
            ->and($rule['maximum_window'])->toBe(12);
    });

    it('is the accessor the estate path actually calls', function () {
        // W-0463's whole point: an accessor nobody calls is configuration that
        // does not govern anything. `getFourteenYearRule()` had zero callers.
        $calculator = (string) file_get_contents(
            base_path('app/Services/Estate/FailedGiftTaxCalculator.php')
        );

        expect($calculator)->toContain('getFourteenYearRule()');
    });

    it('leaves no literal window in the calculator to disagree with the configuration', function () {
        $calculator = (string) file_get_contents(
            base_path('app/Services/Estate/FailedGiftTaxCalculator.php')
        );

        // Strip comments — the legal reasoning cites seven and fourteen years in
        // prose, which is the explanation, not the arithmetic.
        $code = (string) preg_replace('/^\s*(\*|\/\/|\/\*).*$/m', '', $calculator);

        expect($code)->not->toMatch('/\$searchBound\s*=\s*\d+/');
    });
});
