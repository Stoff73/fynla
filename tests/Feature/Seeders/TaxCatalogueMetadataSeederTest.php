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

it('seeds the sequencing and claim-tier metadata the plan composer relies on', function () {
    $this->seed(TaxActionDefinitionSeeder::class);

    // (a) ISA top-up sequences before the spouse savings gift.
    $isaTopUp = TaxActionDefinition::where('strategy_type', 'isa_topup_vs_psa')->first();
    expect($isaTopUp->sequencing['do_before'])->toContain('savings_to_spouse');

    // (b) Spouse savings gift and joint savings split conflict with each other.
    $savingsToSpouse = TaxActionDefinition::where('strategy_type', 'savings_to_spouse')->first();
    $jointSplit = TaxActionDefinition::where('strategy_type', 'joint_savings_psa_split')->first();
    expect($savingsToSpouse->sequencing['conflicts_with'])->toContain('joint_savings_psa_split')
        ->and($jointSplit->sequencing['conflicts_with'])->toContain('savings_to_spouse');

    // (c) The judgement tier is exactly these six strategies.
    $judgement = TaxActionDefinition::where('source', 'strategy')
        ->where('claim_tier', 'judgement')
        ->pluck('strategy_type')
        ->sort()
        ->values()
        ->all();
    expect($judgement)->toBe([
        'bed_and_isa',
        'gia_rebalance',
        'gia_to_spouse',
        'junior_isa',
        'junior_pension',
        'lifetime_isa',
    ]);
});
