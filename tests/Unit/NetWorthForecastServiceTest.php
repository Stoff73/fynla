<?php

declare(strict_types=1);

use App\Services\NetWorth\NetWorthForecastService;

it('projects each asset and liability category independently from recorded year zero', function (): void {
    $current = [
        'assets' => [
            'property' => 500_000.0,
            'investments' => 30_000.0,
            'pensions' => 0.0,
            'cash' => 20_000.0,
            'business' => 0.0,
            'valuables' => 0.0,
        ],
        'liabilities' => [
            'mortgages' => 200_000.0,
            'other_liabilities' => 0.0,
        ],
    ];
    $assumptions = [
        'property' => ['rate_percent' => 3.0],
        'investments' => ['rate_percent' => 0.0],
        'pensions' => ['rate_percent' => 0.0],
        'cash' => ['rate_percent' => 1.0],
        'business' => ['rate_percent' => 0.0],
        'valuables' => ['rate_percent' => 0.0],
        'mortgages' => ['rate_percent' => 0.0],
        'other_liabilities' => ['rate_percent' => 0.0],
    ];
    $cashFlows = [
        'annual_contributions' => ['cash' => 0.0, 'investments' => 0.0, 'pensions' => 0.0],
        'annual_repayments' => ['mortgages' => 6_000.0, 'other_liabilities' => 0.0],
    ];

    $points = NetWorthForecastService::projectPoints(
        $current,
        $assumptions,
        $cashFlows,
        years: 1,
        startYear: 2026,
    );

    expect($points[0]['net_worth'])->toBe(350_000.0)
        ->and($points[0]['source'])->toBe('recorded')
        ->and($points[1]['categories']['property'])->toBe(515_000.0)
        ->and($points[1]['categories']['cash'])->toBe(20_200.0)
        ->and($points[1]['liabilities']['mortgages'])->toBe(194_000.0)
        ->and($points[1]['net_worth'])->toBe(371_200.0)
        ->and($points[1]['source'])->toBe('projected');
});

it('never projects a liability below zero', function (): void {
    $current = [
        'assets' => array_fill_keys(['property', 'investments', 'pensions', 'cash', 'business', 'valuables'], 0.0),
        'liabilities' => ['mortgages' => 1_000.0, 'other_liabilities' => 500.0],
    ];
    $assumptions = array_fill_keys(
        ['property', 'investments', 'pensions', 'cash', 'business', 'valuables', 'mortgages', 'other_liabilities'],
        ['rate_percent' => 0.0],
    );

    $points = NetWorthForecastService::projectPoints($current, $assumptions, [
        'annual_contributions' => [],
        'annual_repayments' => ['mortgages' => 2_000.0, 'other_liabilities' => 1_000.0],
    ], 1, 2026);

    expect($points[1]['liabilities'])->toBe([
        'mortgages' => 0.0,
        'other_liabilities' => 0.0,
    ]);
});
