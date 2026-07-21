<?php

declare(strict_types=1);

use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Normalisers\SavingsMarketRateNormaliser;

it('normalises a complete admin payload', function () {
    $normaliser = new SavingsMarketRateNormaliser;
    $out = $normaliser->fromAdmin([
        'rate_key' => 'easy_access',
        'label' => 'Easy Access',
        'rate' => 0.045,
        'tax_year' => '2026/27',
        'effective_from' => '2026-04-06',
    ]);

    expect($out)->toBe([
        'rate_key' => 'easy_access',
        'label' => 'Easy Access',
        'rate' => 0.045,
        'tax_year' => '2026/27',
        'effective_from' => '2026-04-06',
    ]);
});

it('rejects missing required fields', function () {
    $normaliser = new SavingsMarketRateNormaliser;
    expect(fn () => $normaliser->fromAdmin(['rate_key' => 'easy_access']))
        ->toThrow(StoreValidationException::class);
});

it('rejects empty rate_key', function () {
    $normaliser = new SavingsMarketRateNormaliser;
    expect(fn () => $normaliser->fromAdmin([
        'rate_key' => '',
        'label' => 'Easy Access',
        'rate' => 0.045,
        'tax_year' => '2026/27',
        'effective_from' => '2026-04-06',
    ]))->toThrow(StoreValidationException::class);
});

it('rejects negative rates', function () {
    $normaliser = new SavingsMarketRateNormaliser;
    expect(fn () => $normaliser->fromAdmin([
        'rate_key' => 'easy_access',
        'label' => 'Easy Access',
        'rate' => -0.01,
        'tax_year' => '2026/27',
        'effective_from' => '2026-04-06',
    ]))->toThrow(StoreValidationException::class);
});
