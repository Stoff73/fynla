<?php

declare(strict_types=1);

use App\Models\TierConfiguration;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(fn () => $this->seed(TierConfigurationSeeder::class));

it('seeds exactly the two canonical tiers', function () {
    expect(TierConfiguration::pluck('tier')->sort()->values()->all())
        ->toBe(['free', 'premium']);
});

it('applies the spec §7 count caps for free', function () {
    $free = TierConfiguration::where('tier', 'free')->first();
    expect($free->count_caps['savings_account'])->toBe(2)
        ->and($free->count_caps['investment'])->toBe(2)
        ->and($free->count_caps['pension_account'])->toBe(2)
        ->and($free->count_caps['property'])->toBe(1)
        ->and($free->count_caps['goal'])->toBe(2)
        ->and($free->count_caps['life_event'])->toBe(1)
        ->and($free->capability_matrix['estate'])->toBe('teaser')
        ->and($free->capability_matrix['family_module'])->toBe('full') // A5 firm: all tiers
        ->and($free->fyn_weekly_token_budget)->toBe(100_000)
        ->and($free->currency_display_mode)->toBe('gbp_only')
        ->and($free->snapshot_surfacing_window_days)->toBe(90);
});

it('seeds the approved Premium economics and unlimited quotas', function () {
    $premium = TierConfiguration::where('tier', 'premium')->first();

    expect($premium->display_name)->toBe('Premium')
        ->and($premium->price_monthly_pence)->toBe(699)
        ->and($premium->price_annual_pence)->toBe(5999)
        ->and($premium->count_caps['savings_account'])->toBeNull()
        ->and($premium->capability_matrix['estate'])->toBe('full')
        ->and($premium->fyn_weekly_token_budget)->toBe(500_000)
        ->and($premium->currency_display_mode)->toBe('user_choice')
        ->and($premium->snapshot_surfacing_window_days)->toBeNull()
        ->and($premium->open_api_affordance)->toBeTrue();
});

it('is idempotent (updateOrCreate)', function () {
    $this->seed(TierConfigurationSeeder::class);
    expect(TierConfiguration::count())->toBe(2);
});
