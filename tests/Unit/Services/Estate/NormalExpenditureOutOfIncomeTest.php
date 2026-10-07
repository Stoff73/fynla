<?php

declare(strict_types=1);

use App\Services\Estate\GiftingStrategyOptimizer;
use App\Services\TaxConfigService;

/**
 * IHTA 1984 s21 — gifts out of surplus income.
 *
 * W-0525 moved a hardcoded `surplus * 0.5` with a `>= 1000` floor from two
 * services into one configuration block. Item 9 (2026-10-07) removed both
 * figures: s21 sets no cap, fraction or floor, and income less the usual
 * spending is already what its third test, keeping the usual standard of
 * living (s21(1)(c)), leaves the giver. An invented figure is removed on sight
 * (Rule 23).
 *
 * These tests fail if a fraction or a floor comes back, in the configuration or
 * in either service.
 */
describe('the s21 exemption carries no invented figure', function () {
    it('configures no fraction and no floor', function () {
        $service = Mockery::mock(TaxConfigService::class)->makePartial();
        $service->shouldReceive('get')
            ->with('gifting_exemptions.normal_expenditure_from_income', [])
            ->andReturn([]);

        $rules = $service->getNormalExpenditureFromIncome();

        expect($rules)->not->toHaveKey('safe_surplus_fraction')
            ->and($rules)->not->toHaveKey('minimum_annual_gift')
            ->and($rules['limit'])->toBeNull()
            ->and($rules['immediately_exempt'])->toBeTrue();
    });

    it('suggests the whole surplus, however small', function () {
        $optimizer = app(GiftingStrategyOptimizer::class);
        $method = new ReflectionMethod($optimizer, 'calculateGiftingFromIncomeStrategy');

        $strategy = $method->invoke($optimizer, 50_000.0, 49_400.0, 10, 0.4);

        expect($strategy['surplus_income'])->toBe(600.0)
            ->and($strategy['annual_amount'])->toBe(600.0)
            ->and($strategy['can_afford'])->toBeTrue();
    });

    it('leaves no fraction or floor in either service', function () {
        foreach ([
            'app/Services/Estate/PersonalizedGiftingStrategyService.php',
            'app/Services/Estate/GiftingStrategyOptimizer.php',
        ] as $path) {
            $code = (string) preg_replace(
                '/^\s*(\*|\/\/|\/\*).*$/m',
                '',
                (string) file_get_contents(base_path($path))
            );

            expect($code)->not->toMatch('/\$surplusIncome\s*\*\s*/')
                ->and($code)->not->toMatch('/safe_surplus_fraction|minimum_annual_gift/');
        }
    });
});
