<?php

declare(strict_types=1);

use App\Models\TierConfiguration;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(fn () => $this->seed(TierConfigurationSeeder::class));

it('seeds exactly the four canonical tiers', function () {
    expect(TierConfiguration::pluck('tier')->sort()->values()->all())
        ->toBe(['free', 'tier1', 'tier2', 'tier3']);
});

it('applies the spec §7 count caps for free', function () {
    $free = TierConfiguration::where('tier', 'free')->first();
    expect($free->count_caps['savings_account'])->toBe(3)
        ->and($free->count_caps['investment'])->toBe(2)
        ->and($free->count_caps['pension_account'])->toBe(5)
        ->and($free->capability_matrix['estate'])->toBe('teaser')
        ->and($free->capability_matrix['family_module'])->toBe('full') // A5 firm: all tiers
        ->and($free->fyn_weekly_token_budget)->toBe(100_000)
        ->and($free->currency_display_mode)->toBe('gbp_only')
        ->and($free->snapshot_surfacing_window_days)->toBe(90);
});

it('makes tier1+ counts unlimited and tier3 the widest', function () {
    $t1 = TierConfiguration::where('tier', 'tier1')->first();
    $t3 = TierConfiguration::where('tier', 'tier3')->first();
    expect($t1->count_caps['savings_account'])->toBeNull()
        ->and($t1->capability_matrix['estate'])->toBe('teaser')          // teaser at tier1 too
        ->and($t1->capability_matrix['investments_exotic'])->toBe('none') // A1 default
        ->and($t1->capability_matrix['chattels'])->toBe('full')           // A2 default
        ->and($t3->capability_matrix['estate'])->toBe('full')
        ->and($t3->fyn_weekly_token_budget)->toBe(1_000_000)
        ->and($t3->currency_display_mode)->toBe('user_choice')
        ->and($t3->snapshot_surfacing_window_days)->toBe(2555)
        ->and($t3->open_api_affordance)->toBeTrue();
});

it('is idempotent (updateOrCreate)', function () {
    $this->seed(TierConfigurationSeeder::class);
    expect(TierConfiguration::count())->toBe(4);
});
