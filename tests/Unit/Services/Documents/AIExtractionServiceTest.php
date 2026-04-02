<?php

declare(strict_types=1);

use App\Services\Documents\AIExtractionService;

it('has Excel sheet extraction prompt with all categories', function () {
    $service = app(AIExtractionService::class);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getExcelSheetPrompt');
    $method->setAccessible(true);

    $prompt = $method->invoke($service);

    expect($prompt)->toContain('investment_holdings');
    expect($prompt)->toContain('pension_holdings');
    expect($prompt)->toContain('cash_savings');
    expect($prompt)->toContain('property');
    expect($prompt)->toContain('protection');
    expect($prompt)->toContain('ignore');
    expect($prompt)->toContain('holdings');
    expect($prompt)->toContain('category');
});

it('has extractSheet method that returns expected structure', function () {
    $service = app(AIExtractionService::class);

    expect(method_exists($service, 'extractSheet'))->toBeTrue();

    $reflection = new ReflectionMethod($service, 'extractSheet');
    $params = $reflection->getParameters();

    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('sheetName');
    expect($params[1]->getName())->toBe('sheetContent');
});
