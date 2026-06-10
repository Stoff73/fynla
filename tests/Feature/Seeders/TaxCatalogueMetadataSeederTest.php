<?php

declare(strict_types=1);

use App\Models\TaxActionDefinition;
use Database\Seeders\TaxActionDefinitionSeeder;

it('seeds a metadata row for every emitted tax strategy type', function () {
    $this->seed(TaxActionDefinitionSeeder::class);

    $expected = [
        'pa_taper_rescue',
        'additional_rate_avoidance',
        'isa_topup_vs_psa',
        'bed_and_isa',
        'dividend_allowance_harvest',
        'marriage_allowance_transfer',
        'savings_to_spouse',
        'isa_topup_spouse',
        'gia_to_spouse',
        'gia_rebalance',
        'isa_coordination',
        'non_earner_spouse_pension',
        'pension_aa_carry_forward',
        'salary_sacrifice_ni',
        'tapered_annual_allowance',
        'gift_aid_higher_rate_relief',
        'joint_savings_psa_split',
        'lifetime_isa',
        'junior_isa',
        'junior_pension',
    ];

    foreach ($expected as $type) {
        $row = TaxActionDefinition::where('strategy_type', $type)->first();
        expect($row)->not->toBeNull("missing catalogue row for {$type}")
            ->and($row->claim_tier)->toBeIn(['mechanical', 'judgement'])
            ->and($row->source)->toBe('strategy');
    }

    expect(TaxActionDefinition::where('source', 'agent')->where('is_enabled', true)->count())->toBe(0);
});
