<?php

declare(strict_types=1);

use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Normalisers\CurrencyRateNormaliser;

it('normalises a valid admin payload', function () {
    $n = new CurrencyRateNormaliser;

    $out = $n->fromAdmin([
        'from_ccy' => 'gbp',
        'to_ccy' => 'eur',
        'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
        'source' => 'manual',
    ]);

    expect($out)->toBe([
        'from_ccy' => 'GBP',
        'to_ccy' => 'EUR',
        'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
        'source' => 'manual',
    ]);
});

it('defaults source to "manual" when omitted', function () {
    $n = new CurrencyRateNormaliser;

    $out = $n->fromAdmin([
        'from_ccy' => 'GBP',
        'to_ccy' => 'EUR',
        'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
    ]);

    expect($out['source'])->toBe('manual');
});

it('rejects missing required fields', function () {
    $n = new CurrencyRateNormaliser;

    expect(fn () => $n->fromAdmin([
        'from_ccy' => 'GBP',
        'to_ccy' => 'EUR',
        // rate + effective_at missing
    ]))->toThrow(StoreValidationException::class);
});

it('rejects malformed currency codes', function () {
    $n = new CurrencyRateNormaliser;

    expect(fn () => $n->fromAdmin([
        'from_ccy' => 'GB',
        'to_ccy' => 'EUR',
        'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
    ]))->toThrow(StoreValidationException::class);

    expect(fn () => $n->fromAdmin([
        'from_ccy' => 'GBP',
        'to_ccy' => 'EU',
        'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
    ]))->toThrow(StoreValidationException::class);
});

it('rejects identical from/to currencies', function () {
    $n = new CurrencyRateNormaliser;

    expect(fn () => $n->fromAdmin([
        'from_ccy' => 'GBP',
        'to_ccy' => 'GBP',
        'rate' => 1.0,
        'effective_at' => '2026-04-06 00:00:00',
    ]))->toThrow(StoreValidationException::class);
});

it('rejects non-positive rate', function () {
    $n = new CurrencyRateNormaliser;

    expect(fn () => $n->fromAdmin([
        'from_ccy' => 'GBP',
        'to_ccy' => 'EUR',
        'rate' => 0,
        'effective_at' => '2026-04-06 00:00:00',
    ]))->toThrow(StoreValidationException::class);

    expect(fn () => $n->fromAdmin([
        'from_ccy' => 'GBP',
        'to_ccy' => 'EUR',
        'rate' => -0.5,
        'effective_at' => '2026-04-06 00:00:00',
    ]))->toThrow(StoreValidationException::class);
});
