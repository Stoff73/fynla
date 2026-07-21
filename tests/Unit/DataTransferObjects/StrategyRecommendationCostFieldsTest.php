<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;

it('omits cost keys from toArray when both costs are null (byte-identity for tax)', function (): void {
    $rec = new StrategyRecommendation(
        type: 'isa_topup_vs_psa',
        category: StrategyCategory::Allowance,
        priority: 'high',
        title: 'Wrap excess cash',
        description: 'Move taxable interest into an ISA.',
        estimatedAnnualTaxSaved: 312.5,
    );

    // No required_monthly_cost / required_lump_sum keys appear at all.
    expect($rec->toArray())->toBe([
        'type' => 'isa_topup_vs_psa',
        'category' => 'allowance',
        'priority' => 'high',
        'title' => 'Wrap excess cash',
        'description' => 'Move taxable interest into an ISA.',
        'estimated_annual_tax_saved' => 312.5,
        'requires_advice' => false,
    ]);
});

it('includes cost keys in toArray when set, and round-trips through fromArray', function (): void {
    $rec = new StrategyRecommendation(
        type: 'increase_pension_contribution',
        category: StrategyCategory::Lifecycle,
        priority: 'medium',
        title: 'Increase pension contribution',
        description: 'Raise your monthly DC contribution to use unused allowance.',
        estimatedAnnualTaxSaved: null,
        requiresAdvice: false,
        extra: [],
        requiredMonthlyCost: 250.0,
        requiredLumpSum: null,
    );

    $arr = $rec->toArray();
    expect($arr['required_monthly_cost'])->toBe(250.0)
        ->and($arr)->not->toHaveKey('required_lump_sum');

    $round = StrategyRecommendation::fromArray(StrategyCategory::Lifecycle, $arr);
    expect($round->requiredMonthlyCost)->toBe(250.0)
        ->and($round->requiredLumpSum)->toBeNull();
});

it('reads lump sum cost from a legacy array via fromArray', function (): void {
    $rec = StrategyRecommendation::fromArray(StrategyCategory::Lifecycle, [
        'type' => 'bed_and_isa',
        'priority' => 'low',
        'title' => 'Bed and ISA',
        'description' => 'Crystallise gains and re-wrap.',
        'required_lump_sum' => 20000.0,
    ]);

    expect($rec->requiredLumpSum)->toBe(20000.0)
        ->and($rec->requiredMonthlyCost)->toBeNull()
        // cost keys must NOT leak into extra (they are reserved)
        ->and($rec->extra)->not->toHaveKey('required_lump_sum');
});
