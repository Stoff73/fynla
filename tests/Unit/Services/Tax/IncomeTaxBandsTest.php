<?php

declare(strict_types=1);

use App\Services\Tax\IncomeTaxBands;

/**
 * W-0174 — the basic-rate band width is the constant, the £50,270 higher-rate
 * threshold is what it plus a full Personal Allowance adds up to.
 *
 * These are pure arithmetic tests over config arrays built here, so nothing can
 * quietly agree with the code: every case that pins a number also has a sibling
 * that MOVES the configured input and requires the output to follow. A test that
 * only ever sees £37,700 could not tell a read of `bands[0].max` apart from a
 * hardcoded literal.
 */
function bandConfig(array $overrides = []): array
{
    return array_replace([
        'personal_allowance' => 12570,
        'personal_allowance_taper_threshold' => 100000,
        'personal_allowance_taper_rate' => 0.5,
        'higher_rate_threshold' => 50270,
        'additional_rate_threshold' => 125140,
        'bands' => [
            ['name' => 'Basic Rate', 'min' => 0, 'max' => 37700, 'lower_limit' => 12570, 'upper_limit' => 50270, 'rate' => 0.20],
            ['name' => 'Higher Rate', 'min' => 37700, 'max' => 125140, 'lower_limit' => 50270, 'upper_limit' => 125140, 'rate' => 0.40],
            ['name' => 'Additional Rate', 'min' => 125140, 'max' => null, 'lower_limit' => 125140, 'upper_limit' => null, 'rate' => 0.45],
        ],
    ], $overrides);
}

describe('basic-rate band width holds while the allowance moves', function () {
    it('keeps the 20% band at the configured width across the whole taper', function () {
        $config = bandConfig();

        foreach ([12570.0, 7570.0, 1.0, 0.0] as $allowance) {
            $bands = IncomeTaxBands::forPersonalAllowance($config, $allowance);

            expect($bands->basicRateBandWidth())->toBe(37700.0)
                ->and($bands->basicRateLimit)->toBe($allowance + 37700.0);
        }
    });

    it('follows a different configured band width rather than a £37,700 literal', function () {
        $bands = IncomeTaxBands::forPersonalAllowance(
            bandConfig(['bands' => array_replace(bandConfig()['bands'], [
                0 => ['name' => 'Basic Rate', 'min' => 0, 'max' => 40000, 'lower_limit' => 12570, 'upper_limit' => 52570, 'rate' => 0.20],
            ])]),
            0.0
        );

        expect($bands->basicRateBandWidth())->toBe(40000.0)
            ->and($bands->basicRateLimit)->toBe(40000.0);
    });

    it('reconstructs the width from the higher-rate threshold when no width is configured', function () {
        $config = bandConfig();
        unset($config['bands'][0]['max']);

        // £50,270 threshold − £12,570 full allowance = £37,700, and it must be
        // the FULL allowance that reconstructs it, not the tapered one.
        expect(IncomeTaxBands::forPersonalAllowance($config, 0.0)->basicRateBandWidth())->toBe(37700.0);
    });
});

describe('the higher-rate band absorbs whatever the allowance gives up', function () {
    it('widens the 40% band by exactly the allowance withdrawn', function () {
        $config = bandConfig();

        $full = IncomeTaxBands::forPersonalAllowance($config, 12570.0);
        $none = IncomeTaxBands::forPersonalAllowance($config, 0.0);

        expect($full->higherRateBandWidth())->toBe(74870.0)
            ->and($none->higherRateBandWidth())->toBe(87440.0)
            ->and($none->higherRateBandWidth() - $full->higherRateBandWidth())->toBe(12570.0);
    });

    it('holds the additional-rate threshold still — it does not move with the allowance', function () {
        $config = bandConfig();

        expect(IncomeTaxBands::forPersonalAllowance($config, 12570.0)->higherRateLimit)->toBe(125140.0)
            ->and(IncomeTaxBands::forPersonalAllowance($config, 0.0)->higherRateLimit)->toBe(125140.0);
    });

    it('follows a different configured additional-rate threshold', function () {
        $bands = IncomeTaxBands::forPersonalAllowance(
            bandConfig(['additional_rate_threshold' => 150000]),
            0.0
        );

        expect($bands->higherRateLimit)->toBe(150000.0)
            ->and($bands->higherRateBandWidth())->toBe(112300.0);
    });
});

describe('the Personal Allowance taper', function () {
    it('leaves the allowance whole at and below the threshold', function () {
        $config = bandConfig();

        expect(IncomeTaxBands::taperedPersonalAllowance($config, 99999.0))->toBe(12570.0)
            ->and(IncomeTaxBands::taperedPersonalAllowance($config, 100000.0))->toBe(12570.0);
    });

    it('withdraws £1 for every £2 above the threshold and extinguishes it exactly', function () {
        $config = bandConfig();

        expect(IncomeTaxBands::taperedPersonalAllowance($config, 110000.0))->toBe(7570.0)
            ->and(IncomeTaxBands::taperedPersonalAllowance($config, 125140.0))->toBe(0.0)
            ->and(IncomeTaxBands::taperedPersonalAllowance($config, 200000.0))->toBe(0.0);
    });

    it('follows a different configured taper threshold', function () {
        $config = bandConfig(['personal_allowance_taper_threshold' => 120000]);

        expect(IncomeTaxBands::taperedPersonalAllowance($config, 110000.0))->toBe(12570.0)
            ->and(IncomeTaxBands::taperedPersonalAllowance($config, 130000.0))->toBe(7570.0);
    });

    it('follows a different configured taper rate rather than a hardcoded halving', function () {
        $config = bandConfig(['personal_allowance_taper_rate' => 0.25]);

        // £10,000 over the threshold at £1 in £4 withdraws £2,500, not £5,000.
        expect(IncomeTaxBands::taperedPersonalAllowance($config, 110000.0))->toBe(10070.0);
    });

    it('follows a different configured full allowance', function () {
        $config = bandConfig(['personal_allowance' => 15000]);

        expect(IncomeTaxBands::taperedPersonalAllowance($config, 50000.0))->toBe(15000.0)
            ->and(IncomeTaxBands::taperedPersonalAllowance($config, 110000.0))->toBe(10000.0);
    });

    it('builds the bands off the tapered allowance when given an income', function () {
        $bands = IncomeTaxBands::forAdjustedNetIncome(bandConfig(), 147690.0);

        expect($bands->personalAllowance)->toBe(0.0)
            ->and($bands->basicRateLimit)->toBe(37700.0)
            ->and($bands->basicRateBandWidth())->toBe(37700.0)
            ->and($bands->higherRateBandWidth())->toBe(87440.0);
    });
});

describe('band extension', function () {
    it('moves both limits by the extension and leaves the allowance alone', function () {
        $extended = IncomeTaxBands::forPersonalAllowance(bandConfig(), 12570.0)->extendedBy(10000.0);

        expect($extended->basicRateLimit)->toBe(60270.0)
            ->and($extended->higherRateLimit)->toBe(135140.0)
            ->and($extended->personalAllowance)->toBe(12570.0)
            ->and($extended->basicRateBandWidth())->toBe(47700.0);
    });

    it('is a no-op for nothing to extend by', function () {
        $bands = IncomeTaxBands::forPersonalAllowance(bandConfig(), 12570.0);

        expect($bands->extendedBy(0.0)->basicRateLimit)->toBe($bands->basicRateLimit);
    });
});

describe('rates come from the configuration', function () {
    it('follows configured rates rather than 20/40/45', function () {
        $config = bandConfig();
        $config['bands'][0]['rate'] = 0.19;
        $config['bands'][1]['rate'] = 0.42;
        $config['bands'][2]['rate'] = 0.47;

        $bands = IncomeTaxBands::forPersonalAllowance($config);

        expect($bands->basicRate)->toBe(0.19)
            ->and($bands->higherRate)->toBe(0.42)
            ->and($bands->additionalRate)->toBe(0.47);
    });
});
