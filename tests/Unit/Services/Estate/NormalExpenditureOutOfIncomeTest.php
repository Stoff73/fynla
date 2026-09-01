<?php

declare(strict_types=1);

use App\Services\TaxConfigService;

/**
 * W-0525 — the IHTA 1984 s21 exemption is configured, not invented twice.
 *
 * The item filed this as "a label on a strategy, never computed". Reading the
 * code, it is worse than that and also better: an amount IS computed, but TWICE
 * — `PersonalizedGiftingStrategyService` and `GiftingStrategyOptimizer` each
 * hardcoded `surplus * 0.5` with a `>= 1000` floor — and the configured
 * `gifting_exemptions.normal_expenditure_from_income` block governed neither,
 * because `getNormalExpenditureFromIncome()` had zero callers.
 *
 * So one exemption had two mechanisms and no configuration. Move the admin
 * setting and nothing happened; edit either service and the two answers drifted
 * apart silently, because nothing compared them.
 *
 * These tests fail if either literal comes back, or if the two services stop
 * agreeing.
 */
function s21RulesWith(float $fraction, float $minimum): array
{
    $service = Mockery::mock(TaxConfigService::class)->makePartial();
    $service->shouldReceive('get')
        ->with('gifting_exemptions.normal_expenditure_from_income', [])
        ->andReturn([
            'limit' => null,
            'immediately_exempt' => true,
            'safe_surplus_fraction' => $fraction,
            'minimum_annual_gift' => $minimum,
        ]);

    return $service->getNormalExpenditureFromIncome();
}

describe('the s21 exemption reads its configuration', function () {
    it('exposes the safe fraction and the minimum the strategies act on', function () {
        $rules = s21RulesWith(0.5, 1000.0);

        expect($rules['safe_surplus_fraction'])->toBe(0.5)
            ->and($rules['minimum_annual_gift'])->toBe(1000.0)
            ->and($rules['immediately_exempt'])->toBeTrue();
    });

    it('moves when the configured fraction moves', function () {
        // The point of the item. A 25% conservative fraction must reach the
        // strategies; a hardcoded 0.5 cannot.
        expect(s21RulesWith(0.25, 500.0)['safe_surplus_fraction'])->toBe(0.25)
            ->and(s21RulesWith(0.25, 500.0)['minimum_annual_gift'])->toBe(500.0);
    });

    it('defaults without inventing a rule where the block is silent', function () {
        $service = Mockery::mock(TaxConfigService::class)->makePartial();
        $service->shouldReceive('get')
            ->with('gifting_exemptions.normal_expenditure_from_income', [])
            ->andReturn([]);

        $rules = $service->getNormalExpenditureFromIncome();

        expect($rules['safe_surplus_fraction'])->toBe(0.5)
            ->and($rules['minimum_annual_gift'])->toBe(1000.0);
    });
});

describe('both gifting services read that one home', function () {
    it('leaves no surplus literal in either service', function () {
        foreach ([
            'app/Services/Estate/PersonalizedGiftingStrategyService.php',
            'app/Services/Estate/GiftingStrategyOptimizer.php',
        ] as $path) {
            $code = (string) preg_replace(
                '/^\s*(\*|\/\/|\/\*).*$/m',
                '',
                (string) file_get_contents(base_path($path))
            );

            expect($code)->not->toMatch('/\$surplusIncome\s*\*\s*0\.5/')
                ->and($code)->not->toMatch('/\$safeGiftingAmount\s*>=\s*1000/');
        }
    });

    it('is the accessor both services actually call', function () {
        foreach ([
            'app/Services/Estate/PersonalizedGiftingStrategyService.php',
            'app/Services/Estate/GiftingStrategyOptimizer.php',
        ] as $path) {
            expect((string) file_get_contents(base_path($path)))
                ->toContain('getNormalExpenditureFromIncome()');
        }
    });
});
