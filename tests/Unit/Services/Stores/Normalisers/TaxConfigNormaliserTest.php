<?php

declare(strict_types=1);

use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Normalisers\TaxConfigNormaliser;

beforeEach(function () {
    $this->normaliser = new TaxConfigNormaliser;
});

describe('TaxConfigNormaliser::fromAdmin', function () {
    it('accepts a valid admin payload and returns the canonical shape', function () {
        $result = $this->normaliser->fromAdmin([
            'tax_year' => '2026/27',
            'effective_from' => '2026-04-06',
            'effective_to' => '2027-04-05',
            'config_data' => ['income_tax' => ['personal_allowance' => 12570]],
            'is_active' => true,
            'notes' => 'UK 2026/27',
        ]);

        expect($result)->toBe([
            'tax_year' => '2026/27',
            'effective_from' => '2026-04-06',
            'effective_to' => '2027-04-05',
            'config_data' => ['income_tax' => ['personal_allowance' => 12570]],
            'is_active' => true,
            'notes' => 'UK 2026/27',
        ]);
    });

    it('defaults is_active to false and notes to null when omitted', function () {
        $result = $this->normaliser->fromAdmin([
            'tax_year' => '2026/27',
            'effective_from' => '2026-04-06',
            'effective_to' => '2027-04-05',
            'config_data' => [],
        ]);

        expect($result['is_active'])->toBeFalse();
        expect($result['notes'])->toBeNull();
    });

    it('rejects when required fields are missing', function () {
        expect(fn () => $this->normaliser->fromAdmin([
            'effective_from' => '2026-04-06',
            'effective_to' => '2027-04-05',
            'config_data' => [],
        ]))->toThrow(StoreValidationException::class);
    });

    it('rejects malformed tax_year', function () {
        expect(fn () => $this->normaliser->fromAdmin([
            'tax_year' => '2026-27',
            'effective_from' => '2026-04-06',
            'effective_to' => '2027-04-05',
            'config_data' => [],
        ]))->toThrow(StoreValidationException::class);
    });

    it('rejects when effective_to is not after effective_from', function () {
        expect(fn () => $this->normaliser->fromAdmin([
            'tax_year' => '2026/27',
            'effective_from' => '2026-04-06',
            'effective_to' => '2026-04-06',
            'config_data' => [],
        ]))->toThrow(StoreValidationException::class);
    });

    it('rejects non-array config_data', function () {
        expect(fn () => $this->normaliser->fromAdmin([
            'tax_year' => '2026/27',
            'effective_from' => '2026-04-06',
            'effective_to' => '2027-04-05',
            'config_data' => 'not-an-array',
        ]))->toThrow(StoreValidationException::class);
    });

    it('canonicalises ISO 8601 datetime strings to Y-m-d', function () {
        $result = $this->normaliser->fromAdmin([
            'tax_year' => '2026/27',
            'effective_from' => '2026-04-06T00:00:00.000000Z',
            'effective_to' => '2027-04-05T00:00:00.000000Z',
            'config_data' => [],
        ]);

        expect($result['effective_from'])->toBe('2026-04-06');
        expect($result['effective_to'])->toBe('2027-04-05');
    });
});
