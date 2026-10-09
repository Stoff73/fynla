<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\TaxActionDefinition;
use App\Services\Coordination\StrategyPlanComposer;
use Database\Seeders\TaxActionDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('orders by sequencing, sums savings, and marks conflicts', function () {
    $recs = [
        new StrategyRecommendation('savings_to_spouse', StrategyCategory::Household, StrategyPriority::High,
            'Gift savings to spouse', 'desc', 530.0),
        new StrategyRecommendation('isa_topup_vs_psa', StrategyCategory::Allowance, StrategyPriority::High,
            'Wrap cash in ISA', 'desc', 259.0),
        new StrategyRecommendation('joint_savings_psa_split', StrategyCategory::Household, StrategyPriority::High,
            'Split savings', 'desc', 330.0),
    ];

    $metadata = [
        'isa_topup_vs_psa' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => ['savings_to_spouse'], 'conflicts_with' => []]],
        'savings_to_spouse' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['joint_savings_psa_split']]],
        'joint_savings_psa_split' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['savings_to_spouse']]],
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: []);

    $types = array_column($plan['items'], 'type');
    // isa_topup must precede savings_to_spouse (do_before).
    expect(array_search('isa_topup_vs_psa', $types))
        ->toBeLessThan(array_search('savings_to_spouse', $types));
    // Conflict pair: the lower-saving one carries the note naming its alternative.
    $joint = collect($plan['items'])->firstWhere('type', 'joint_savings_psa_split');
    expect($joint['conflict_note'])->toContain('"Gift savings to spouse"');
    // The higher-saving member of the pair carries no note.
    $gift = collect($plan['items'])->firstWhere('type', 'savings_to_spouse');
    expect($gift['conflict_note'])->toBeNull();
    // Combined total excludes the lower-saving alternative (joint_savings_psa_split = 330).
    // Realisable total: isa_topup_vs_psa (259) + savings_to_spouse (530) = 789.
    expect($plan['combined_annual_saving'])->toBe(789.0);
    expect($plan['items'][0]['claim_tier'])->toBe('mechanical');
    // sequence_position is 1-based and contiguous.
    expect(array_column($plan['items'], 'sequence_position'))->toBe([1, 2, 3]);
});

it('defaults claim_tier to judgement for types without metadata', function () {
    $recs = [new StrategyRecommendation('mystery_type', StrategyCategory::Allowance, StrategyPriority::Low, 't', 'd', 10.0)];

    $plan = app(StrategyPlanComposer::class)->compose($recs, [], lockedStrategies: []);

    expect($plan['items'][0]['claim_tier'])->toBe('judgement');
});

it('lists locked strategies with their missing data points', function () {
    $plan = app(StrategyPlanComposer::class)->compose([], [], lockedStrategies: [
        ['strategy_type' => 'salary_sacrifice_ni', 'missing' => ['workplace_pension']],
    ]);

    expect($plan['locked'][0]['strategy_type'])->toBe('salary_sacrifice_ni')
        ->and($plan['locked'][0]['missing'])->toBe(['workplace_pension'])
        ->and($plan['items'])->toBe([])
        ->and($plan['combined_annual_saving'])->toBe(0.0);
});

it('lets composer-owned fields win over clashing keys in extra', function () {
    // The DTO merges extra[] into the top level on toArray(); if extra carries
    // a key the composer also writes (claim_tier, sequence_position,
    // conflict_note), the composer's value must win.
    $recs = [
        new StrategyRecommendation('sneaky_type', StrategyCategory::Allowance, StrategyPriority::High,
            't', 'd', 25.0, false, [
                'claim_tier' => 'from_extra',
                'sequence_position' => 999,
                'conflict_note' => 'bogus note from extra',
            ]),
    ];

    $metadata = [
        'sneaky_type' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: []);

    expect($plan['items'][0]['claim_tier'])->toBe('mechanical')
        ->and($plan['items'][0]['sequence_position'])->toBe(1)
        ->and($plan['items'][0]['conflict_note'])->toBeNull();
});

it('notes the null-saving member of a mutual conflict pair', function () {
    // A (null saving) and B (50.0) MUTUALLY conflict: A must carry the note
    // naming B, B carries none, and the total counts only B.
    $recs = [
        new StrategyRecommendation('null_saver', StrategyCategory::Household, StrategyPriority::High,
            'A', 'desc', null),
        new StrategyRecommendation('valued_saver', StrategyCategory::Household, StrategyPriority::High,
            'B', 'desc', 50.0),
    ];

    $metadata = [
        'null_saver' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['valued_saver']]],
        'valued_saver' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['null_saver']]],
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: []);

    $nullSaver = collect($plan['items'])->firstWhere('type', 'null_saver');
    $valuedSaver = collect($plan['items'])->firstWhere('type', 'valued_saver');

    expect($nullSaver['conflict_note'])->toContain('"B"')
        ->and($valuedSaver['conflict_note'])->toBeNull()
        ->and($plan['combined_annual_saving'])->toBe(50.0);
});

it('keeps the chain tail realisable when its only conflict is itself excluded', function () {
    // Chain: A=300 ↔ B=200 ↔ C=100. B loses to A and is excluded; C's only
    // conflict (B) is itself excluded, so C stays realisable. Total = A + C = 400.
    $recs = [
        new StrategyRecommendation('strategy_a', StrategyCategory::Household, StrategyPriority::High,
            'A', 'desc', 300.0),
        new StrategyRecommendation('strategy_b', StrategyCategory::Household, StrategyPriority::High,
            'B', 'desc', 200.0),
        new StrategyRecommendation('strategy_c', StrategyCategory::Household, StrategyPriority::High,
            'C', 'desc', 100.0),
    ];

    $metadata = [
        'strategy_a' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['strategy_b']]],
        'strategy_b' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['strategy_a', 'strategy_c']]],
        'strategy_c' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['strategy_b']]],
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: []);

    $a = collect($plan['items'])->firstWhere('type', 'strategy_a');
    $b = collect($plan['items'])->firstWhere('type', 'strategy_b');
    $c = collect($plan['items'])->firstWhere('type', 'strategy_c');

    expect($plan['combined_annual_saving'])->toBe(400.0)
        ->and($b['conflict_note'])->toContain('"A"')
        ->and($c['conflict_note'])->toBeNull()
        ->and($a['conflict_note'])->toBeNull();
});

it('excludes shared-ISA-allowance losers from the combined total and surfaces their note', function () {
    // The calculator's ISA allocation pass flags a pool-exhausted consumer
    // with isa_allowance_excluded + isa_allowance_note in extra. The composer
    // must keep the item in the plan, leave it out of combined_annual_saving,
    // and surface the note through the conflict_note channel so frontends
    // render it exactly like a conflict alternative.
    $recs = [
        new StrategyRecommendation('lifetime_isa', StrategyCategory::Lifecycle, StrategyPriority::Medium,
            'Open a Lifetime ISA', 'desc', 1000.0),
        new StrategyRecommendation('isa_topup_vs_psa', StrategyCategory::Allowance, StrategyPriority::High,
            'Wrap cash in ISA', 'desc', 72.0, false, [
                'isa_allowance_excluded' => true,
                'isa_allowance_note' => 'Draws on the same ISA allowance, already fully used by lifetime_isa.',
            ]),
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, [], lockedStrategies: []);

    $topup = collect($plan['items'])->firstWhere('type', 'isa_topup_vs_psa');
    expect($topup)->not->toBeNull()
        ->and($topup['conflict_note'])->toContain('same ISA allowance')
        ->and($plan['combined_annual_saving'])->toBe(1000.0);
});

it('counts a pool-constrained ISA consumer and carries its note', function () {
    // Constrained-but-realisable consumers carry only isa_allowance_note —
    // they still count toward the total at their re-evaluated saving.
    $recs = [
        new StrategyRecommendation('lifetime_isa', StrategyCategory::Lifecycle, StrategyPriority::Medium,
            'Open a Lifetime ISA', 'desc', 1000.0),
        new StrategyRecommendation('isa_topup_vs_psa', StrategyCategory::Allowance, StrategyPriority::High,
            'Wrap cash in ISA', 'desc', 256.0, false, [
                'isa_allowance_note' => 'Sized to the ISA allowance left after lifetime_isa — every ISA type shares one annual ISA allowance per tax year.',
            ]),
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, [], lockedStrategies: []);

    $topup = collect($plan['items'])->firstWhere('type', 'isa_topup_vs_psa');
    expect($topup['conflict_note'])->toContain('shares one annual ISA allowance')
        ->and($plan['combined_annual_saving'])->toBe(1256.0);
});

it('lets a conflict-pair note win over an ISA allowance note', function () {
    // When an item both loses a conflict pair AND carries an ISA note, the
    // conflict mechanism's own wording takes precedence (first note wins).
    $recs = [
        new StrategyRecommendation('strategy_a', StrategyCategory::Household, StrategyPriority::High,
            'A', 'desc', 300.0),
        new StrategyRecommendation('strategy_b', StrategyCategory::Household, StrategyPriority::High,
            'B', 'desc', 200.0, false, [
                'isa_allowance_note' => 'Sized to the ISA allowance left after strategy_x.',
            ]),
    ];

    $metadata = [
        'strategy_a' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['strategy_b']]],
        'strategy_b' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['strategy_a']]],
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: []);

    $b = collect($plan['items'])->firstWhere('type', 'strategy_b');
    expect($b['conflict_note'])->toContain('Alternative to "A"')
        ->and($plan['combined_annual_saving'])->toBe(300.0);
});

it('handles null savings and keeps ordering stable', function () {
    // Recs with null estimatedAnnualTaxSaved sort after valued ones;
    // combined total ignores nulls; sequence_positions remain 1-based and contiguous.
    $recs = [
        new StrategyRecommendation('no_saving_a', StrategyCategory::Allowance, StrategyPriority::Low, 'A', 'desc', null),
        new StrategyRecommendation('has_saving', StrategyCategory::Allowance, StrategyPriority::High, 'B', 'desc', 100.0),
        new StrategyRecommendation('no_saving_b', StrategyCategory::Allowance, StrategyPriority::Low, 'C', 'desc', null),
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, [], lockedStrategies: []);

    $types = array_column($plan['items'], 'type');
    // The valued rec should come first.
    expect($types[0])->toBe('has_saving');
    // Combined total only counts the valued rec (100); nulls are excluded.
    expect($plan['combined_annual_saving'])->toBe(100.0);
    // Positions contiguous 1-based.
    expect(array_column($plan['items'], 'sequence_position'))->toBe([1, 2, 3]);
});

it('never sums pa_taper_rescue with additional_rate_avoidance once the seeder declares the pair', function () {
    $this->seed(TaxActionDefinitionSeeder::class);
    $metadata = TaxActionDefinition::whereNotNull('strategy_type')
        ->get()
        ->keyBy('strategy_type')
        ->map(fn ($row) => ['claim_tier' => $row->claim_tier, 'sequencing' => $row->sequencing])
        ->all();

    // A non-donor at £150,000 taxable with £30,000 of pension relief: adjusted
    // net income £120,000, both strategies fire.
    $recs = [
        new StrategyRecommendation('pa_taper_rescue', StrategyCategory::IncomeBand, StrategyPriority::High, 'PA', 'd', 12000.0),
        new StrategyRecommendation('additional_rate_avoidance', StrategyCategory::IncomeBand, StrategyPriority::High, 'AR', 'd', 29521.0),
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: []);

    expect($plan['combined_annual_saving'])->toBe(29521.0);
});

// L3-3 (fynla.org /m, 29 Sep 2026): asked "ISA, gift to my wife, or 50/50 — do
// they add up?", Fyn called the ISA and the gift alternatives, then told the
// user to do "the ISA top-up and either the gift or the joint split". The gift
// — the one the total counts — carried no note at all, so nothing told Fyn the
// three are one choice. Every member of a conflict now names the others.
it('names every alternative from both ends of the seeded savings three-way choice', function () {
    $this->seed(TaxActionDefinitionSeeder::class);
    $metadata = TaxActionDefinition::whereNotNull('strategy_type')
        ->get()
        ->keyBy('strategy_type')
        ->map(fn ($row) => ['claim_tier' => $row->claim_tier, 'sequencing' => $row->sequencing])
        ->all();

    // The live £110,000 household: £50,000 of sole-name savings at 4.5%.
    $recs = [
        new StrategyRecommendation('savings_to_spouse', StrategyCategory::Household, StrategyPriority::High,
            'Gift savings to your spouse', 'd', 1150.0),
        new StrategyRecommendation('joint_savings_psa_split', StrategyCategory::Household, StrategyPriority::Low,
            'Share savings 50/50', 'd', 675.0),
        new StrategyRecommendation('isa_topup_vs_psa', StrategyCategory::Allowance, StrategyPriority::High,
            'Wrap savings in an ISA', 'd', 540.0),
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: []);
    $item = fn (string $type): array => collect($plan['items'])->firstWhere('type', $type);

    expect($plan['combined_annual_saving'])->toBe(1150.0)
        ->and($item('savings_to_spouse')['alternatives'])->toEqualCanonicalizing(['Share savings 50/50', 'Wrap savings in an ISA'])
        ->and($item('isa_topup_vs_psa')['alternatives'])->toEqualCanonicalizing(['Gift savings to your spouse', 'Share savings 50/50'])
        ->and($item('joint_savings_psa_split')['alternatives'])->toEqualCanonicalizing(['Gift savings to your spouse', 'Wrap savings in an ISA'])
        ->and($item('savings_to_spouse')['alternatives_note'])
        ->toContain('"Gift savings to your spouse" is an alternative to')
        ->toContain('so their savings do not add up')
        ->toEndWith('The plan total counts this one.')
        ->and($item('isa_topup_vs_psa')['alternatives_note'])
        ->toEndWith('The plan total counts "Gift savings to your spouse" instead of this one.')
        ->and($item('joint_savings_psa_split')['alternatives_note'])
        ->toEndWith('The plan total counts "Gift savings to your spouse" instead of this one.');
});

it('says which alternatives a chain total counts, and leaves unpaired items without a note', function () {
    $recs = [
        new StrategyRecommendation('strategy_a', StrategyCategory::Household, StrategyPriority::High, 'A', 'desc', 300.0),
        new StrategyRecommendation('strategy_b', StrategyCategory::Household, StrategyPriority::High, 'B', 'desc', 200.0),
        new StrategyRecommendation('strategy_c', StrategyCategory::Household, StrategyPriority::High, 'C', 'desc', 100.0),
        new StrategyRecommendation('strategy_d', StrategyCategory::Allowance, StrategyPriority::High, 'D', 'desc', 50.0),
    ];
    $metadata = [
        'strategy_a' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['strategy_b']]],
        'strategy_b' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['strategy_a', 'strategy_c']]],
        'strategy_c' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['strategy_b']]],
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: []);
    $item = fn (string $type): array => collect($plan['items'])->firstWhere('type', $type);

    expect($item('strategy_b')['alternatives'])->toBe(['A', 'C'])
        ->and($item('strategy_b')['alternatives_note'])->toBe('"B" is an alternative to "A" and "C": doing one changes or removes the saving from the other, so their savings do not add up. The plan total counts "A" and "C" instead of this one.')
        ->and($item('strategy_c')['alternatives_note'])->toEndWith('The plan total counts this one.')
        ->and($item('strategy_d')['alternatives'])->toBe([])
        ->and($item('strategy_d')['alternatives_note'])->toBeNull();
});

it('says in the note itself when an alternative is not counted in the total (SaveTax matrix L3-5)', function () {
    $recs = [
        new StrategyRecommendation('savings_to_spouse', StrategyCategory::Household, StrategyPriority::High,
            'Gift savings to spouse', 'desc', 710.0),
        new StrategyRecommendation('joint_savings_psa_split', StrategyCategory::Household, StrategyPriority::High,
            'Split savings', 'desc', 460.0),
        new StrategyRecommendation('strategy_x', StrategyCategory::Household, StrategyPriority::High,
            'X', 'desc', 100.0),
        new StrategyRecommendation('strategy_y', StrategyCategory::Household, StrategyPriority::High,
            'Y', 'desc', 100.0),
    ];
    $metadata = [
        'savings_to_spouse' => ['sequencing' => ['do_before' => [], 'conflicts_with' => ['joint_savings_psa_split']]],
        'joint_savings_psa_split' => ['sequencing' => ['do_before' => [], 'conflicts_with' => ['savings_to_spouse']]],
        'strategy_x' => ['sequencing' => ['do_before' => [], 'conflicts_with' => ['strategy_y']]],
        'strategy_y' => ['sequencing' => ['do_before' => [], 'conflicts_with' => ['strategy_x']]],
    ];

    $items = collect(app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: [])['items'])->keyBy('type');

    // The excluded alternative says so, on every surface that shows the note.
    expect($items['joint_savings_psa_split']['counted_in_total'])->toBeFalse()
        ->and($items['joint_savings_psa_split']['conflict_note'])->toEndWith('Not counted in your total.')
        ->and($items['savings_to_spouse']['conflict_note'])->toBeNull();

    // A tie: both carry the note, but the one that counts must not say it doesn't.
    $counted = $items['strategy_x']['counted_in_total'] ? $items['strategy_x'] : $items['strategy_y'];
    $excluded = $items['strategy_x']['counted_in_total'] ? $items['strategy_y'] : $items['strategy_x'];
    expect($counted['conflict_note'])->toContain('Alternative to')->not->toContain('Not counted')
        ->and($excluded['conflict_note'])->toEndWith('Not counted in your total.');
});

// Rule 20: one reader of alternatives_note for every place Fyn is told about or
// voices a plan item, so a blank note is treated the same everywhere.
it('reads an item\'s alternatives sentence one way for every consumer', function () {
    expect(StrategyPlanComposer::alternativesNoteOf(['alternatives_note' => '  Choose one.  ']))->toBe('Choose one.')
        ->and(StrategyPlanComposer::alternativesNoteOf(['alternatives_note' => '   ']))->toBeNull()
        ->and(StrategyPlanComposer::alternativesNoteOf(['alternatives_note' => null]))->toBeNull()
        ->and(StrategyPlanComposer::alternativesNoteOf([]))->toBeNull()
        ->and(StrategyPlanComposer::withAlternativesNote('Wrap cash in an ISA.', ['alternatives_note' => 'Choose one.']))->toBe('Wrap cash in an ISA. Choose one.')
        ->and(StrategyPlanComposer::withAlternativesNote('- Gift savings', ['alternatives_note' => 'Choose one.'], '. '))->toBe('- Gift savings. Choose one.')
        ->and(StrategyPlanComposer::withAlternativesNote('Salary sacrifice.', ['alternatives_note' => '  ']))->toBe('Salary sacrifice.');
});

it('names each item\'s next step and its screen, one map for every surface', function () {
    // Regression walk 2026-10-09, R6: web linked pension items to "/pension"
    // and investment items to "/investments" (neither a web page; both fell
    // through to the dashboard) and Gift Aid to Personal details; /m kept its
    // own labels. The plan item now carries the step (StrategyNextStep).
    $recs = [
        new StrategyRecommendation('salary_sacrifice_ni', StrategyCategory::Allowance, StrategyPriority::Medium, 'Switch to salary sacrifice', 'desc', 72.0),
        new StrategyRecommendation('gift_aid_higher_rate_relief', StrategyCategory::Allowance, StrategyPriority::Medium, 'Reclaim Gift Aid', 'desc', 150.0),
        new StrategyRecommendation('bed_and_isa', StrategyCategory::Allowance, StrategyPriority::Medium, 'Bed and ISA', 'desc', 50.0),
        new StrategyRecommendation('savings_to_spouse', StrategyCategory::Household, StrategyPriority::High, 'Gift savings', 'desc', 30.0),
    ];

    $items = collect(app(StrategyPlanComposer::class)->compose($recs, [], [])['items'])->keyBy('type');

    expect($items['salary_sacrifice_ni']['next_step']['label'])->toBe('Open pensions')
        ->and($items['salary_sacrifice_ni']['next_step']['destination']['screen'])->toBe('retirement')
        ->and($items['gift_aid_higher_rate_relief']['next_step']['destination']['screen'])->toBe('income')
        ->and($items['bed_and_isa']['next_step']['destination']['screen'])->toBe('investment')
        ->and($items['savings_to_spouse']['next_step']['destination']['screen'])->toBe('savings');
});
