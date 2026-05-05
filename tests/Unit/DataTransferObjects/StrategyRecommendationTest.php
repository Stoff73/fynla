<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;

describe('StrategyRecommendation::fromArray', function () {
    it('lifts a legacy suggestion array into the typed DTO with the given category', function () {
        $arr = [
            'type' => 'savings_to_spouse',
            'priority' => 'high',
            'title' => 'Gift £50,000 of savings to your spouse',
            'description' => 'Their unused allowances absorb up to £18,570/yr tax-free.',
            'estimated_annual_tax_saved' => 700.0,
            'suggested_transfer_amount' => 50000.0,
        ];

        $rec = StrategyRecommendation::fromArray('household', $arr);

        expect($rec->type)->toBe('savings_to_spouse')
            ->and($rec->category)->toBe('household')
            ->and($rec->priority)->toBe('high')
            ->and($rec->estimatedAnnualTaxSaved)->toBe(700.0)
            ->and($rec->requiresAdvice)->toBeFalse()
            ->and($rec->extra)->toBe(['suggested_transfer_amount' => 50000.0]);
    });

    it('defaults missing fields to safe values', function () {
        $rec = StrategyRecommendation::fromArray('warning', []);

        expect($rec->type)->toBe('')
            ->and($rec->category)->toBe('warning')
            ->and($rec->priority)->toBe('medium')
            ->and($rec->estimatedAnnualTaxSaved)->toBeNull()
            ->and($rec->requiresAdvice)->toBeFalse()
            ->and($rec->extra)->toBe([]);
    });
});

describe('StrategyRecommendation::toArray', function () {
    it('emits the canonical shape with extras merged at the top level', function () {
        $rec = new StrategyRecommendation(
            type: 'isa_topup_spouse',
            category: 'household',
            priority: 'medium',
            title: "Top up spouse's ISA",
            description: 'They have £20,000 of unused allowance.',
            estimatedAnnualTaxSaved: null,
            requiresAdvice: false,
            extra: ['available_allowance' => 20000.0],
        );

        $arr = $rec->toArray();

        expect($arr)->toHaveKeys([
            'type', 'category', 'priority', 'title', 'description',
            'estimated_annual_tax_saved', 'requires_advice', 'available_allowance',
        ])
            ->and($arr['type'])->toBe('isa_topup_spouse')
            ->and($arr['category'])->toBe('household')
            ->and($arr['estimated_annual_tax_saved'])->toBeNull()
            ->and($arr['available_allowance'])->toBe(20000.0);
    });

    it('round-trips fromArray → toArray without losing fields', function () {
        $original = [
            'type' => 'marriage_allowance_transfer',
            'priority' => 'medium',
            'title' => 'Claim Marriage Allowance',
            'description' => 'Saves £252/yr.',
            'estimated_annual_tax_saved' => 252.0,
            'amount_transferred' => 1260.0,
        ];

        $rebuilt = StrategyRecommendation::fromArray('household', $original)->toArray();

        // All original keys preserved; `category` + `requires_advice` added
        foreach ($original as $key => $value) {
            expect($rebuilt[$key])->toBe($value);
        }
        expect($rebuilt['category'])->toBe('household')
            ->and($rebuilt['requires_advice'])->toBeFalse();
    });
});
