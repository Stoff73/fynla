<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;

/**
 * Seeded tax years store every rate as a fraction (0.20 = 20%) and every
 * engine multiplies by it. The factory, and so the Pest safety-net year, must
 * store them the same way; a whole percentage (20) taxes income 100 times over.
 */
it('stores the factory\'s income tax, Capital Gains Tax and dividend rates as fractions', function () {
    $config = TaxConfiguration::factory()->make()->config_data;

    $rates = array_merge(
        array_column($config['income_tax']['bands'], 'rate'),
        array_filter(
            array_merge($config['capital_gains_tax'], $config['dividend_tax']),
            fn ($value, string $key): bool => str_ends_with($key, '_rate'),
            ARRAY_FILTER_USE_BOTH,
        ),
    );

    expect($rates)->not->toBeEmpty();
    foreach ($rates as $rate) {
        expect($rate)->toBeGreaterThan(0)->toBeLessThan(1);
    }
});
