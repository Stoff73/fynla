<?php

declare(strict_types=1);

use App\Services\Onboarding\FunnelIncomeBand;

it('knows the funnel band keys', function () {
    expect(FunnelIncomeBand::isKnown('upto_50270'))->toBeTrue()
        ->and(FunnelIncomeBand::isKnown('50271_100000'))->toBeTrue()
        ->and(FunnelIncomeBand::isKnown('100001_125140'))->toBeTrue()
        ->and(FunnelIncomeBand::isKnown('over_125140'))->toBeTrue()
        ->and(FunnelIncomeBand::isKnown('zero'))->toBeTrue()
        ->and(FunnelIncomeBand::isKnown('nonsense'))->toBeFalse();
});

it('treats a figure inside the band as in-band', function () {
    expect(FunnelIncomeBand::inBand('50271_100000', 80000))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('100001_125140', 110000))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('upto_50270', 0))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('over_125140', 200000))->toBeTrue();
});

it('treats a figure outside the band as out-of-band', function () {
    // CSJ's example: picked £100,001-£125,140, typed £50,000.
    expect(FunnelIncomeBand::inBand('100001_125140', 50000))->toBeFalse()
        ->and(FunnelIncomeBand::inBand('upto_50270', 60000))->toBeFalse()
        ->and(FunnelIncomeBand::inBand('over_125140', 90000))->toBeFalse();
});

it('honours inclusive band boundaries', function () {
    expect(FunnelIncomeBand::inBand('upto_50270', 50270))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('upto_50270', 50271))->toBeFalse()
        ->and(FunnelIncomeBand::inBand('50271_100000', 50271))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('100001_125140', 125140))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('over_125140', 125141))->toBeTrue();
});

it('treats any positive figure as out-of-band for the zero spouse band', function () {
    expect(FunnelIncomeBand::inBand('zero', 0))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('zero', 1))->toBeFalse()
        ->and(FunnelIncomeBand::inBand('zero', 40000))->toBeFalse();
});

it('returns false for an unknown key', function () {
    expect(FunnelIncomeBand::inBand('nonsense', 50000))->toBeFalse();
});

it('returns a human label for each band and empty for unknown', function () {
    expect(FunnelIncomeBand::label('100001_125140'))->toBe('£100,001–£125,140')
        ->and(FunnelIncomeBand::label('zero'))->toBe('no income')
        ->and(FunnelIncomeBand::label('over_125140'))->toBe('over £125,140')
        ->and(FunnelIncomeBand::label('nonsense'))->toBe('');
});
