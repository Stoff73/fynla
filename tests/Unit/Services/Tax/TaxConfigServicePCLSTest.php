<?php

declare(strict_types=1);

use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

describe('TaxConfigService::calculatePCLS — Lump Sum Allowance cap', function () {
    it('caps PCLS at the Lump Sum Allowance for pots above £1,073,100', function () {
        $svc = app(TaxConfigService::class);

        // £1.5M pot: raw 25% = £375,000, but LSA caps at £268,275.
        expect($svc->calculatePCLS(1_500_000.0))->toBe(268275.0);
    });

    it('applies 25% rate for pots below the LSA threshold', function () {
        $svc = app(TaxConfigService::class);

        // £400k pot: 25% = £100,000, well under LSA, no cap fires.
        expect($svc->calculatePCLS(400_000.0))->toBe(100_000.0);
    });

    it('returns exactly LSA for the boundary pot of £1,073,100', function () {
        $svc = app(TaxConfigService::class);

        // £1,073,100 × 0.25 = £268,275 = LSA exactly.
        expect($svc->calculatePCLS(1_073_100.0))->toBe(268275.0);
    });

    it('reduces available PCLS when prior LSA usage is provided', function () {
        $svc = app(TaxConfigService::class);

        // £1.5M pot but £100k of LSA already consumed.
        // LSA remaining = £268,275 - £100,000 = £168,275.
        // Raw 25% = £375,000 → capped at remaining.
        expect($svc->calculatePCLS(1_500_000.0, 100_000.0))->toBe(168_275.0);
    });

    it('returns zero when LSA is fully consumed', function () {
        $svc = app(TaxConfigService::class);

        expect($svc->calculatePCLS(1_000_000.0, 268_275.0))->toBe(0.0);
    });

    it('clamps negative pot values to zero', function () {
        $svc = app(TaxConfigService::class);

        expect($svc->calculatePCLS(-50_000.0))->toBe(0.0);
    });

    it('treats LSA used above the cap as fully consumed (no negative remaining)', function () {
        $svc = app(TaxConfigService::class);

        // Defensive: if upstream tracks more than the LSA somehow, don't go negative.
        expect($svc->calculatePCLS(500_000.0, 999_999.0))->toBe(0.0);
    });
});
